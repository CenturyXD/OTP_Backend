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
        //
        $otp = Otp::create([
            'email' => $request->validated()['emails'], // ใช้แค่ email แรกจากอาร์เรย์
            'otp' => $request->validated()['otp'] ?? null,
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

    public function fetchOtp(OtpRequest $request)
    {
        $targetSender = $request->validated()['target'];
        $service = $request->validated()['service'] ?? null; // รับค่า service เพิ่มเติม (optional)
        // เช็คใน table otp ก่อนว่ามี email นี้, is_verified = true และ expires_at ยังไม่หมดอายุ
        $otpExists = Otp::where('email', $targetSender)
            ->where('is_verified', true)
            ->where('expires_at', '>', now())
            ->exists();
        if (!$otpExists) {
            return response()->json(['message' => 'email not found or not verified or expired contact support'], 404);
        }
        // ดึง OTP จากอีเมล sattawat.ticket@gmail.com ที่ถูก forward มาจาก $targetSender และ filter ด้วย service ถ้ามี
        $imapService = new ImapOtpService();
        $otp = $imapService->fetchLatestOtp($targetSender, $service); // ส่งค่า $service ไปยัง ImapOtpService เพื่อใช้ในการกรอง OTP ตาม service

        if (!empty($otp['otp'])) {
            return response()->json($otp);
        }
        return response()->json(['message' => 'No OTP found'], 404);
    }
}
