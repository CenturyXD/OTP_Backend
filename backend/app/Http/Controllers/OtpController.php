<?php

namespace App\Http\Controllers;

use App\Models\Otp;
use Illuminate\Http\Request;
use App\Services\GraphMailService;
use App\Services\ImapOtpService;
use App\Http\Requests\PermissionRequest;
use Illuminate\Support\Facades\Auth;

class OtpController extends Controller
{
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

        $otp->update([
            'email' => $data['emails'],
            'service' => $data['service'] ?? $otp->service,
            'password' => $data['password'] ?? $otp->password,
            'is_verified' => $data['is_verified'] ?? $otp->is_verified,
            'expires_at' => $data['expires_at'] ?? $otp->expires_at,
            'mail_type' => $data['mail_type'] ?? $otp->mail_type,
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
            'mailbox' => 'nullable|string',
            'provider' => 'nullable|in:imap,graph',
            'access_token' => 'nullable|string',
            'mail_folder' => 'nullable|string',
        ]);

        $email = (string)$data['email'];
        $service = (string)$data['service'];
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
            ->where('is_verified', 1)
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first(['is_verified', 'password', 'mail_type']);

        $mailType = strtolower(trim((string)($otpRow->mail_type ?? '')));
        $graphMailTypes = ['hotmail', 'outlook', 'live', 'msn', 'microsoft', 'office365', 'graph'];
        $provider = $providerInput !== ''
            ? $providerInput
            : (in_array($mailType, $graphMailTypes, true) ? 'graph' : 'imap');

        $verify = $otpRow->is_verified ?? false;
        $password = $otpRow->password ?? null;
        if ($provider === 'graph' && $accessToken === '') {
            $accessToken = trim((string)($otpRow->password ?? ''));
        }
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
                $result = $graphService->fetchLatestOtpFromInbox($accessToken, $service, $mailFolder);
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
