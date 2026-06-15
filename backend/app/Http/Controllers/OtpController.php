<?php

namespace App\Http\Controllers;

use App\Models\Otp;
use Illuminate\Http\Request;
use App\Services\GraphMailService;
use App\Services\ImapOtpService;
use App\Http\Requests\PermissionRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OtpController extends Controller
{
    private const OTP_UNLOCK_TTL_MINUTES = 5;

    private function normalizeScreenLocks(array $locks): array
    {
        return collect($locks)
            ->filter(fn($lock) => is_array($lock))
            ->map(function (array $lock) {
                $screenName = trim((string)($lock['screen_name'] ?? ''));
                $code = (string)($lock['lock_code'] ?? '');

                if ($screenName === '' || $code === '') {
                    return null;
                }

                return [
                    'screen_name' => $screenName,
                    'lock_code' => $code,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function consumeUnlockToken(string $unlockToken, string $email, string $service): bool
    {
        $cacheKey = 'otp_unlock:' . $unlockToken;
        $payload = Cache::pull($cacheKey);

        if (!is_array($payload)) {
            return false;
        }

        return ($payload['email'] ?? null) === strtolower($email)
            && ($payload['service'] ?? null) === strtolower($service);
    }

    private function verifyScreenLockForOtp(Otp $otpRow, string $screenName, string $screenCode): array
    {
        $locks = $otpRow->screen_locks;
        if (!is_array($locks) || count($locks) === 0) {
            return [
                'ok' => false,
                'message' => 'อีเมลนี้ยังไม่ได้ตั้งรหัสล็อคจอ กรุณาให้แอดมินตั้งค่าก่อน',
                'status' => 403,
            ];
        }

        $matched = collect($locks)
            ->first(function ($lock) use ($screenName) {
                if (!is_array($lock)) {
                    return false;
                }

                return strtolower((string)($lock['screen_name'] ?? '')) === strtolower($screenName);
            });

        if (!$matched) {
            return [
                'ok' => false,
                'message' => 'ไม่พบหน้าจอที่ระบุสำหรับอีเมลนี้',
                'status' => 403,
            ];
        }

        $storedCode = (string)($matched['lock_code'] ?? '');
        if ($storedCode === '' || $storedCode !== $screenCode) {
            return [
                'ok' => false,
                'message' => 'รหัสล็อคจอไม่ถูกต้อง',
                'status' => 403,
            ];
        }

        return ['ok' => true];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        // แสดง OTP ทั้งหมดถ้าเป็น admin, ถ้าไม่ใช่แสดงเฉพาะของตัวเอง
        if ($user && $user->role === 'admin') {
            $otps = Otp::all();
        } else {
            $otps = Otp::where('owner', Auth::id())->get();
        }

        return response()->json(['data' => $otps, 'user' => $user]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(PermissionRequest $request) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(PermissionRequest $request)
    {
        $data = $request->validated();
        $email = $data['emails'];
        $service = $data['service'] ?? null;
        $screenLocks = $this->normalizeScreenLocks($data['screen_locks'] ?? []);

        // ถ้ามี email+service ซ้ำใน table otp แล้วจะไม่อนุญาตให้เพิ่ม
        $exists = Otp::where('email', $email)
            ->where('service', $service)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'มีข้อมูล email และ service นี้อยู่แล้ว ไม่สามารถเพิ่มซ้ำได้'
            ], 409);
        }

        $otp = Otp::create([
            'email' => $email, // ใช้แค่ email แรกจากอาร์เรย์
            'service' => $service,
            'password' => $data['password'] ?? null,
            'owner' => Auth::id(),
            'is_verified' => true,
            'expires_at' => $data['expires_at'] ?? now()->addDays(30),
            'mail_type' => $data['mail_type'] ?? null,
            'screen_locks' => $screenLocks,
            'refresh_token' => $data['refresh_token'] ?? null,
        ]);

        return response()->json(['message' => 'created successfully', 'data' => $otp]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //


    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PermissionRequest $request, string $id)
    {
        $data = $request->validated();
        $otp = Otp::findOrFail($id);

        // ตรวจสอบว่า OTP นี้เป็นของ user ที่ login หรือไม่
        if ((int)$otp->owner !== (int)Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $hasScreenLocksInput = array_key_exists('screen_locks', $data);

        $otp->update([
            'email' => $data['emails'],
            'service' => $data['service'] ?? $otp->service,
            'password' => $data['password'] ?? $otp->password,
            'is_verified' => $data['is_verified'] ?? $otp->is_verified,
            'expires_at' => $data['expires_at'] ?? $otp->expires_at,
            'mail_type' => $data['mail_type'] ?? $otp->mail_type,
            'screen_locks' => $hasScreenLocksInput
                ? $this->normalizeScreenLocks($data['screen_locks'] ?? [])
                : $otp->screen_locks,
            'refresh_token' => array_key_exists('refresh_token', $data)
                ? ($data['refresh_token'] ?? null)
                : $otp->refresh_token,
        ]);

        return response()->json(['message' => 'updated successfully', 'data' => $otp]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $otp = Otp::findOrFail($id);
        // ตรวจสอบว่า OTP นี้เป็นของ user ที่ login หรือไม่
        if ((int)$otp->owner !== (int)Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $otp->delete();
        return response()->json(['message' => 'deleted successfully']);
    }

    public function fetchOtp(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'service' => 'required|string',
            'unlock_token' => 'nullable|string',
            'screen_name' => 'required_without:unlock_token|string|max:50',
            'screen_code' => 'required_without:unlock_token|string|min:4|max:32',
            'mailbox' => 'nullable|string',
            'provider' => 'nullable|in:imap,graph',
            'access_token' => 'nullable|string',
            'mail_folder' => 'nullable|string',
        ]);

        $email = (string)$data['email'];
        $service = (string)$data['service'];
        $unlockToken = trim((string)($data['unlock_token'] ?? ''));
        $screenName = trim((string)($data['screen_name'] ?? ''));
        $screenCode = (string)($data['screen_code'] ?? '');

        $providerInput = strtolower(trim((string)($data['provider'] ?? '')));
        $accessToken = trim((string)($data['access_token'] ?? ''));
        $mailFolder = (string)($data['mail_folder'] ?? 'inbox');

        $emailLower = strtolower($email);
        $mailbox = (string)($data['mailbox'] ?? '');
        if ($mailbox === '') {
            if (str_ends_with($emailLower, '@gmail.com')) {
                $mailbox = '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX';
            } elseif (
                str_ends_with($emailLower, '@outlook.com') ||
                str_ends_with($emailLower, '@hotmail.com') ||
                str_ends_with($emailLower, '@live.com') ||
                str_ends_with($emailLower, '@msn.com')
            ) {
                $mailbox = '{outlook.office365.com:993/imap/ssl/novalidate-cert}INBOX';
            } else {
                $mailbox = (string)env('IMAP_MAILBOX', '{outlook.office365.com:993/imap/ssl/novalidate-cert}INBOX');
            }
        }

        // ดึง OTP row ที่ยืนยันแล้วและยังไม่หมดอายุ
        $otpRow = Otp::where('email', $email)
            ->where('service', $service)
            ->where('is_verified', 1)
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first(['id', 'is_verified', 'password', 'mail_type', 'screen_locks', 'refresh_token']);

        if (!$otpRow) {
            return response()->json([
                'message' => 'ไม่พบการตั้งค่าอีเมลหรือสิทธิ์หมดอายุ'
            ], 404);
        }

        if ($unlockToken !== '') {
            if (!$this->consumeUnlockToken($unlockToken, $email, $service)) {
                return response()->json([
                    'message' => 'สิทธิ์ปลดล็อคหมดอายุหรือไม่ถูกต้อง กรุณากดรหัสล็อคจอใหม่ก่อนขอ OTP'
                ], 403);
            }
        } else {
            $verifyLock = $this->verifyScreenLockForOtp($otpRow, $screenName, $screenCode);
            if (!($verifyLock['ok'] ?? false)) {
                return response()->json([
                    'message' => $verifyLock['message'] ?? 'รหัสล็อคจอไม่ถูกต้อง'
                ], $verifyLock['status'] ?? 403);
            }
        }

        $mailType = strtolower(trim((string)($otpRow->mail_type ?? '')));
        $graphMailTypes = ['hotmail', 'outlook', 'live', 'msn', 'microsoft', 'office365', 'graph'];
        $isMicrosoftDomain =
            str_ends_with($emailLower, '@outlook.com') ||
            str_ends_with($emailLower, '@hotmail.com') ||
            str_ends_with($emailLower, '@live.com') ||
            str_ends_with($emailLower, '@msn.com');

        $provider = $providerInput !== ''
            ? $providerInput
            : ($isMicrosoftDomain || in_array($mailType, $graphMailTypes, true) ? 'graph' : 'imap');

        $verify = $otpRow->is_verified ?? false;
        $password = $otpRow->password ?? null;
        if ($provider === 'graph' && $accessToken === '') {
            $accessToken = trim((string)($otpRow->password ?? ''));
        }
        $storedRefreshToken = trim((string)($otpRow->refresh_token ?? ''));
        if (!$verify) {
            return response()->json([
                'message' => 'Email นี้หมดอายุแล้ว กรุณาติดต่อผู้ดูแลระบบเพื่อขออนุญาตใช้งานใหม่'
            ], 403);
        }
        if ($provider === 'imap' && !$password) {
            return response()->json([
                'message' => 'ไม่พบรหัสผ่านสำหรับ email นี้ กรุณาติดต่อผู้ดูแลระบบเพื่อขออนุญาตใช้งานใหม่'
            ], 404);
        }
        if ($provider === 'graph' && $accessToken === '') {
            return response()->json([
                'message' => 'ไม่พบ access token สำหรับ provider เป็น graph (โปรดบันทึก token ในฐานข้อมูลหรือส่ง access_token มาในคำขอ)'
            ], 422);
        }

        try {
            if ($provider === 'graph') {
                $graphService = new GraphMailService();

                // proactive refresh: ถ้า token ดูไม่ใช่ JWT (ไม่มีจุด 2 ตัว) และมี refresh_token ให้ refresh ก่อนเลย
                $looksInvalidJwt = substr_count($accessToken, '.') < 2;
                if ($looksInvalidJwt && $storedRefreshToken !== '') {
                    $refreshed = $graphService->refreshAccessToken($storedRefreshToken);
                    if (!isset($refreshed['error_type'])) {
                        $accessToken = $refreshed['access_token'];
                        Otp::where('id', $otpRow->id)->update([
                            'password'      => $accessToken,
                            'refresh_token' => $refreshed['refresh_token'],
                        ]);
                    } elseif (($refreshed['error_type'] ?? '') === 'refresh_token_expired') {
                        return response()->json([
                            'message' => 'Refresh token หมดอายุหรือถูกยกเลิก กรุณาล็อกอิน Microsoft ใหม่และอัปเดต token ในระบบ',
                            'error_type' => 'refresh_token_expired',
                        ], 401);
                    } elseif (($refreshed['error_type'] ?? '') === 'config_missing') {
                        return response()->json([
                            'message' => $refreshed['error_message'],
                            'error_type' => 'config_missing',
                        ], 500);
                    }
                }

                $result = $graphService->fetchLatestOtpFromInbox($accessToken, $service, $mailFolder);

                // ถ้า auth_failed และมี refresh_token ให้ลอง refresh แล้วยิงซ้ำ
                if (
                    is_array($result) &&
                    ($result['error_type'] ?? null) === 'auth_failed' &&
                    $storedRefreshToken !== ''
                ) {
                    $refreshed = $graphService->refreshAccessToken($storedRefreshToken);

                    if (!isset($refreshed['error_type'])) {
                        $newAccessToken     = $refreshed['access_token'];
                        $newRefreshToken    = $refreshed['refresh_token'];

                        // บันทึก token ใหม่กลับเข้า DB ทันที
                        Otp::where('id', $otpRow->id)->update([
                            'password'      => $newAccessToken,
                            'refresh_token' => $newRefreshToken,
                        ]);

                        // ยิงซ้ำด้วย access_token ใหม่
                        $result = $graphService->fetchLatestOtpFromInbox($newAccessToken, $service, $mailFolder);
                        $accessToken = $newAccessToken;
                    } elseif (($refreshed['error_type'] ?? '') === 'refresh_token_expired') {
                        return response()->json([
                            'message' => 'Refresh token หมดอายุหรือถูกยกเลิก กรุณาล็อกอิน Microsoft ใหม่และอัปเดต token ในระบบ',
                            'error_type' => 'refresh_token_expired',
                        ], 401);
                    }
                }
            } else {
                $imapService = new ImapOtpService();
                $result = $imapService->fetchLatestOtpFromInbox($email, $password, $service, $mailbox);
            }

            if (empty($result) || !is_array($result)) {
                return response()->json([
                    'message' => 'ไม่พบอีเมลในกล่องขาเข้า'
                ], 404);
            }

            // กรณี error เฉพาะทาง
            if (!empty($result['error_type'])) {
                if ($result['error_type'] === 'auth_failed') {
                    return response()->json([
                        'message' => $provider === 'graph'
                            ? 'เข้าสู่ระบบ Microsoft Graph ไม่สำเร็จ กรุณาตรวจสอบ Access Token'
                            : 'เข้าสู่ระบบ IMAP ไม่สำเร็จ กรุณาตรวจสอบ App Password หรือการเปิดใช้ IMAP ของอีเมล',
                        'error_type' => 'auth_failed',
                        'detail' => $result['error_message'] ?? null,
                    ], 401);
                }
                if ($result['error_type'] === 'imap_unavailable') {
                    return response()->json([
                        'message' => 'เซิร์ฟเวอร์อีเมลไม่พร้อมใช้งานหรือปิดให้บริการ',
                        'error_type' => 'imap_unavailable',
                        'detail' => $result['error_message'] ?? null,
                    ], 503);
                }
                if ($result['error_type'] === 'graph_unavailable') {
                    return response()->json([
                        'message' => 'Microsoft Graph API ไม่พร้อมใช้งานหรือปฏิเสธคำขอ',
                        'error_type' => 'graph_unavailable',
                        'detail' => $result['error_message'] ?? null,
                    ], 503);
                }

                return response()->json([
                    'message' => $result['error_message'] ?? 'เกิดข้อผิดพลาดในการเชื่อมต่ออีเมล',
                    'error_type' => $result['error_type'],
                ], 500);
            }

            // สำเร็จ
            if (!empty($result['otp'])) {
                return response()->json($result);
            }

            // ไม่พบ OTP แต่มี debug/error
            return response()->json([
                'message' => 'ไม่พบ OTP ในอีเมลล่าสุดที่เกี่ยวข้องกับบริการนี้',
                'debug_subjects' => $result['debug_subjects'] ?? [],
                'error_message' => $result['error_message'] ?? null,
            ], 404);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Authentication failed') !== false || stripos($msg, 'Invalid credentials') !== false) {
                return response()->json([
                    'message' => 'รหัสผ่านอีเมลไม่ถูกต้อง',
                    'error_type' => 'auth_failed',
                ], 401);
            }
            if (stripos($msg, 'Connection refused') !== false || stripos($msg, 'Cannot connect') !== false || stripos($msg, 'IMAP server closed') !== false) {
                return response()->json([
                    'message' => 'เซิร์ฟเวอร์อีเมลไม่พร้อมใช้งานหรือปิดให้บริการ',
                    'error_type' => 'imap_unavailable',
                ], 503);
            }
            return response()->json([
                'message' => 'เกิดข้อผิดพลาดในการค้นหา OTP: ' . $msg
            ], 500);
        }
    }

    public function unlockOtpAccess(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'service' => 'required|string',
            'screen_name' => 'required|string|max:50',
            'screen_code' => 'required|string|min:4|max:32',
        ]);

        $email = strtolower(trim((string)$data['email']));
        $service = strtolower(trim((string)$data['service']));
        $screenName = trim((string)$data['screen_name']);
        $screenCode = (string)$data['screen_code'];

        $otpRow = Otp::where('email', $email)
            ->where('service', $service)
            ->where('is_verified', 1)
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first(['id', 'screen_locks']);

        if (!$otpRow) {
            return response()->json([
                'message' => 'ไม่พบการตั้งค่าอีเมลหรือสิทธิ์หมดอายุ'
            ], 404);
        }

        $verify = $this->verifyScreenLockForOtp($otpRow, $screenName, $screenCode);
        if (!($verify['ok'] ?? false)) {
            return response()->json([
                'message' => $verify['message'] ?? 'ปลดล็อคไม่สำเร็จ'
            ], $verify['status'] ?? 403);
        }

        $unlockToken = Str::random(64);
        Cache::put('otp_unlock:' . $unlockToken, [
            'email' => $email,
            'service' => $service,
            'screen_name' => $screenName,
        ], now()->addMinutes(self::OTP_UNLOCK_TTL_MINUTES));

        return response()->json([
            'message' => 'ปลดล็อคสำเร็จ สามารถขอ OTP ได้',
            'unlock_token' => $unlockToken,
            'expires_in_seconds' => self::OTP_UNLOCK_TTL_MINUTES * 60,
        ]);
    }

    public function fetchInboxEmails(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'access_token' => 'required|string',
            'mail_folder' => 'nullable|string',
        ]);
        $accessToken = $request->input('access_token');
        $mailFolder = $request->input('mail_folder', 'inbox');

        try {
            $graphService = new GraphMailService();
            $emails = $graphService->fetchInboxEmails(30, $accessToken, $mailFolder);

            if (is_array($emails) && !empty($emails['error_type'])) {
                $status = match ($emails['error_type']) {
                    'auth_failed' => 401,
                    'graph_unavailable' => 503,
                    default => 500,
                };

                return response()->json([
                    'message' => $emails['error_message'] ?? 'เกิดข้อผิดพลาดในการเชื่อมต่อ Microsoft Graph',
                    'error_type' => $emails['error_type'],
                    'detail' => $emails['error_message'] ?? null,
                ], $status);
            }

            return response()->json([
                'message' => 'ดึงอีเมลจากกล่องขาเข้าสำเร็จ',
                'data' => $emails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'เกิดข้อผิดพลาดในการดึงอีเมล: ' . $e->getMessage()
            ], 500);
        }
    }
}
