<?php

namespace App\Http\Controllers;

use App\Models\Otp;
use App\Http\Requests\OtpRequest;
use Illuminate\Http\Request;
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
        // แสดง OTP ทั้งหมดถ้าเป็น admin, ถ้าไม่ใช่แสดงเฉพาะของตัวเอง
        if (Auth::user() && Auth::user()->role === 'admin') {
            $otps = Otp::all();
        } else {
            $otps = Otp::where('owner', Auth::id())->get();
        }
        return response()->json(['data' => $otps]);
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
        $email = $request->validated()['emails'];
        $service = $request->validated()['service'] ?? null;
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
            'password' => $request->validated()['password'] ?? null,
            'owner' => Auth::id(),
            'is_verified' => true,
            'expires_at' => $request->validated()['expires_at'] ?? now()->addDays(30),
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
        //
        $otp = Otp::findOrFail($id);
        // ตรวจสอบว่า OTP นี้เป็นของ user ที่ login หรือไม่
        if ((int)$otp->owner !== (int)Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $otp->update([
            'email' => $request->validated()['emails'],
            'service' => $request->validated()['service'] ?? $otp->service,
            'password' => $request->validated()['password'] ?? $otp->password,
            'is_verified' => $request->validated()['is_verified'] ?? $otp->is_verified,
            'expires_at' => $request->validated()['expires_at'] ?? $otp->expires_at,
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
        $request->validate([
            'email' => 'required|email',
            'service' => 'required|string',
            'mailbox' => 'nullable|string',
        ]);
        $email = $request->input('email');
        $service = $request->input('service');
        $mailbox = $request->input('mailbox', '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX');

        // ดึง OTP row ที่ยืนยันแล้วและยังไม่หมดอายุ
        $otpRow = Otp::where([
            ['email', '=', $email],
            ['is_verified', '=', 1],
            ['expires_at', '>', now()],
        ])->orderByDesc('created_at')->first();

        $verify = $otpRow->is_verified ?? false;
        $password = $otpRow->password?? null;
        if (!$verify) {
            return response()->json([
                'message' => 'Email นี้หมดอายุแล้ว กรุณาติดต่อผู้ดูแลระบบเพื่อขออนุญาตใช้งานใหม่'
            ], 403);
        }
        if (!$password) {
            return response()->json([
                'message' => 'ไม่พบรหัสผ่านสำหรับ email นี้ กรุณาติดต่อผู้ดูแลระบบเพื่อขออนุญาตใช้งานใหม่'
            ], 404);
        }



        try {
            $imapService = new ImapOtpService();
            $result = $imapService->fetchLatestOtpFromInbox($email, $password, $service, $mailbox);

            if (empty($result) || !is_array($result)) {
                return response()->json([
                    'message' => 'ไม่พบอีเมลในกล่องขาเข้า'
                ], 404);
            }

            // กรณี error เฉพาะทาง
            if (!empty($result['error_type'])) {
                if ($result['error_type'] === 'auth_failed') {
                    return response()->json([
                        'message' => 'เข้าสู่ระบบ IMAP ไม่สำเร็จ กรุณาตรวจสอบ App Password หรือการเปิดใช้ IMAP ของอีเมล',
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

                return response()->json([
                    'message' => $result['error_message'] ?? 'เกิดข้อผิดพลาดในการเชื่อมต่อ IMAP',
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
            'password' => 'required|string',
            'mailbox' => 'nullable|string',
        ]);
        $email = $request->input('email');
        $password = $request->input('password');
        $mailbox = $request->input('mailbox', '{imap.gmail.com:993/imap/ssl}INBOX');
        try {
            $imapService = new ImapOtpService();
            $emails = $imapService->fetchInboxEmails(30, $email, $password, $mailbox);
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
