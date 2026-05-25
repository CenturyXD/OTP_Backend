<?php

namespace App\Http\Controllers;

use App\Models\Otp;
use App\Http\Requests\OtpRequest;
use Illuminate\Http\Request;
use App\Services\ImapOtpService;
use App\Http\Requests\PermissionRequest;

class OtpController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // แสดง OTP ทั้งหมดถ้าเป็น admin, ถ้าไม่ใช่แสดงเฉพาะของตัวเอง
        if (auth()->user() && auth()->user()->role === 'admin') {
            $otps = Otp::all();
        } else {
            $otps = Otp::where('owner', auth()->id())->get();
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
            'owner' => auth()->id(),
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
        if ((int)$otp->owner !== (int)auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $otp->update([
            'email' => $request->validated()['emails'],
            'otp' => $request->validated()['otp'] ?? $otp->otp,
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
    }

    public function fetchOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'service' => 'required|string',
        ]);
        $email = $request->input('email');
        $service = $request->input('service');
        $mailbox = '{imap.gmail.com:993/imap/ssl}INBOX';
        // ดึง password จาก table otp ตาม email
        $otpRow = Otp::where('email', $email)
            ->where('is_verified', true)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();
        $password = $otpRow ? $otpRow->password : null;
        try {
            $imapService = new ImapOtpService();
            $result = $imapService->fetchLatestOtpFromInbox($email, $password, $service, $mailbox);
            if ($result && !empty($result['otp'])) {
                return response()->json($result);
            } else {
                // คืน debug_subjects กลับไปด้วยถ้าไม่พบ OTP
                return response()->json([
                    'message' => 'ไม่พบ OTP ในอีเมลล่าสุดที่เกี่ยวข้องกับบริการนี้',
                    'debug_subjects' => $result['debug_subjects'] ?? [],
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'เกิดข้อผิดพลาดในการค้นหา OTP: ' . $e->getMessage()
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
