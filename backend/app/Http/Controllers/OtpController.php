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
    private const MICROSOFT_DOMAINS = ['@outlook.com', '@hotmail.com', '@live.com', '@msn.com'];

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

    private function buildMailboxWithFolder(string $mailbox, string $folder): string
    {
        $folder = trim($folder);
        if ($folder === '') {
            return $mailbox;
        }

        $rewritten = preg_replace('/\}.*$/', '}' . $folder, $mailbox, 1);
        return is_string($rewritten) && $rewritten !== '' ? $rewritten : $mailbox;
    }

    private function isMicrosoftDomain(string $emailLower): bool
    {
        foreach (self::MICROSOFT_DOMAINS as $domain) {
            if (str_ends_with($emailLower, $domain)) {
                return true;
            }
        }
        return false;
    }

    private function resolveDefaultMailbox(string $emailLower): string
    {
        if (str_ends_with($emailLower, '@gmail.com')) {
            return '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX';
        }
        return (string)env('IMAP_MAILBOX', '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX');
    }

    private function buildImapMailboxesForEmail(string $emailLower, string $baseMailbox): array
    {
        if (str_ends_with($emailLower, '@gmail.com')) {
            return [$baseMailbox, $this->buildMailboxWithFolder($baseMailbox, '[Gmail]/Spam')];
        }
        return [
            $baseMailbox,
            $this->buildMailboxWithFolder($baseMailbox, 'Spam'),
            $this->buildMailboxWithFolder($baseMailbox, 'Junk'),
        ];
    }

    private function resolveCentralImapConfig(): array
    {
        $mailbox = trim((string)env('CENTRAL_IMAP_MAILBOX', env('IMAP_MAILBOX', '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX')));
        $folders = array_values(array_filter(array_map(
            'trim',
            explode(',', (string)env('CENTRAL_IMAP_FOLDERS', 'INBOX,Spam,[Gmail]/Spam,Junk'))
        )));

        // บังคับเติมโฟลเดอร์ขยะ/สแปมที่พบบ่อย เพื่อกัน config ไม่ครบใน runtime
        $folders = array_values(array_unique(array_merge(
            $folders,
            ['INBOX', 'Spam', '[Gmail]/Spam', 'Junk', 'Junk Email', '[Gmail]/Trash']
        )));

        if (empty($folders)) {
            $folders = ['INBOX'];
        }

        return [
            'email'     => trim((string)env('CENTRAL_IMAP_EMAIL', env('IMAP_USER', ''))),
            'password'  => trim((string)env('CENTRAL_IMAP_PASSWORD', env('IMAP_PASSWORD', ''))),
            'mailbox'   => $mailbox,
            'mailboxes' => array_map(fn(string $f) => $this->buildMailboxWithFolder($mailbox, $f), $folders),
        ];
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
            'oauth_client_id' => $data['oauth_client_id'] ?? null,
            'oauth_client_secret' => $data['oauth_client_secret'] ?? null,
            'oauth_tenant_id' => $data['oauth_tenant_id'] ?? null,
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
            'oauth_client_id' => array_key_exists('oauth_client_id', $data)
                ? ($data['oauth_client_id'] ?? null)
                : $otp->oauth_client_id,
            'oauth_client_secret' => array_key_exists('oauth_client_secret', $data)
                ? ($data['oauth_client_secret'] ?? null)
                : $otp->oauth_client_secret,
            'oauth_tenant_id' => array_key_exists('oauth_tenant_id', $data)
                ? ($data['oauth_tenant_id'] ?? null)
                : $otp->oauth_tenant_id,
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
            'email'        => 'required|email',
            'service'      => 'required|string',
            'unlock_token' => 'nullable|string',
            'screen_name'  => 'required_without:unlock_token|string|max:50',
            'screen_code'  => 'required_without:unlock_token|string|min:4|max:32',
            'mailbox'      => 'nullable|string',
        ]);

        $email          = (string)$data['email'];
        $requestedEmail = $email;
        $emailLower     = strtolower($email);
        $service        = (string)$data['service'];
        $unlockToken    = trim((string)($data['unlock_token'] ?? ''));
        $screenName     = trim((string)($data['screen_name'] ?? ''));
        $screenCode     = (string)($data['screen_code'] ?? '');

        $otpRow = Otp::where('email', $email)
            ->where('service', $service)
            ->where('is_verified', 1)
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first(['id', 'password', 'screen_locks']);

        if (!$otpRow) {
            return response()->json(['message' => 'ไม่พบการตั้งค่าอีเมลหรือสิทธิ์หมดอายุ'], 404);
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

        $usingCentralMailbox = false;

        try {
            $imapService = new ImapOtpService();

            if ($this->isMicrosoftDomain($emailLower)) {
                // Microsoft domains ทั้งหมด → forward มาที่ central IMAP
                $central = $this->resolveCentralImapConfig();
                if ($central['email'] === '' || $central['password'] === '') {
                    return response()->json([
                        'message' => 'ยังไม่ได้ตั้งค่าเมลกลาง (CENTRAL_IMAP_EMAIL/CENTRAL_IMAP_PASSWORD)'
                    ], 500);
                }

                $usingCentralMailbox = true;

                $result = $imapService->fetchLatestOtpFromMailboxes(
                    $central['email'],
                    $central['password'],
                    $service,
                    $central['mailboxes'],
                    $requestedEmail
                );
            } else {
                $password = $otpRow->password;
                if (!$password) {
                    return response()->json([
                        'message' => 'ไม่พบรหัสผ่านสำหรับ email นี้ กรุณาติดต่อผู้ดูแลระบบเพื่อขออนุญาตใช้งานใหม่'
                    ], 404);
                }

                $mailbox   = (string)($data['mailbox'] ?? '') ?: $this->resolveDefaultMailbox($emailLower);
                $mailboxes = $this->buildImapMailboxesForEmail($emailLower, $mailbox);

                $result = $imapService->fetchLatestOtpFromMailboxes(
                    $email,
                    $password,
                    $service,
                    $mailboxes,
                    null
                );
            }

            if (empty($result) || !is_array($result)) {
                return response()->json(['message' => 'ไม่พบอีเมลในกล่องขาเข้า'], 404);
            }

            if (!empty($result['error_type'])) {
                $errType = $result['error_type'];
                $errorMap = [
                    'auth_failed'      => ['message' => $usingCentralMailbox
                        ? 'เข้าสู่ระบบ IMAP ของเมลกลางไม่สำเร็จ กรุณาตรวจสอบ CENTRAL_IMAP_EMAIL/CENTRAL_IMAP_PASSWORD และสิทธิ์ IMAP ของเมลกลาง'
                        : 'เข้าสู่ระบบ IMAP ไม่สำเร็จ กรุณาตรวจสอบ App Password หรือการเปิดใช้ IMAP ของอีเมล', 'status' => 401],
                    'imap_unavailable' => ['message' => 'เซิร์ฟเวอร์อีเมลไม่พร้อมใช้งานหรือปิดให้บริการ', 'status' => 503],
                ];

                if (isset($errorMap[$errType])) {
                    return response()->json([
                        'message'    => $errorMap[$errType]['message'],
                        'error_type' => $errType,
                        'detail'     => $result['error_message'] ?? null,
                    ], $errorMap[$errType]['status']);
                }

                return response()->json([
                    'message'    => $result['error_message'] ?? 'เกิดข้อผิดพลาดในการเชื่อมต่ออีเมล',
                    'error_type' => $errType,
                ], 500);
            }

            if (!empty($result['otp'])) {
                return response()->json($result);
            }

            return response()->json([
                'message'        => 'ไม่พบ OTP ในอีเมลล่าสุดที่เกี่ยวข้องกับบริการนี้',
                'debug_subjects' => $result['debug_subjects'] ?? [],
                'error_message'  => $result['error_message'] ?? null,
            ], 404);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Authentication failed') !== false || stripos($msg, 'Invalid credentials') !== false) {
                return response()->json([
                    'message' => $usingCentralMailbox
                        ? 'ยืนยันตัวตน IMAP ของเมลกลางไม่สำเร็จ กรุณาตรวจสอบ CENTRAL_IMAP_EMAIL/CENTRAL_IMAP_PASSWORD'
                        : 'รหัสผ่านอีเมลไม่ถูกต้อง',
                    'error_type' => 'auth_failed'
                ], 401);
            }
            if (stripos($msg, 'Connection refused') !== false || stripos($msg, 'Cannot connect') !== false || stripos($msg, 'IMAP server closed') !== false) {
                return response()->json(['message' => 'เซิร์ฟเวอร์อีเมลไม่พร้อมใช้งานหรือปิดให้บริการ', 'error_type' => 'imap_unavailable'], 503);
            }
            return response()->json(['message' => 'เกิดข้อผิดพลาดในการค้นหา OTP: ' . $msg], 500);
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
