<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * แสดงรายชื่อผู้ใช้ทั้งหมด
     * GET /api/admin/users
     */
    public function index()
    {
        // ดึงข้อมูลผู้ใช้ทั้งหมด ยกเว้น superadmin คนปัจจุบัน
        // และเรียงตาม ID ล่าสุด พร้อมแบ่งหน้า
        $users = User::where('id', '!=', auth()->id())
                     ->latest()
                     ->paginate(10);

        return response()->json($users);
    }

    /**
     * สร้างผู้ใช้ใหม่
     * POST /api/admin/users
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in(['admin', 'user'])], // จำกัด role ที่สร้างได้
        ]);

        $user = User::create([
            'name' => $validatedData['name'],
            'username' => $validatedData['username'],
            'password' => Hash::make($validatedData['password']),
            'role' => $validatedData['role'],
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user
        ], 201);
    }

    /**
     * แสดงข้อมูลผู้ใช้คนเดียว
     * GET /api/admin/users/{user}
     */
    public function show(User $user)
    {
        return response()->json($user);
    }

    /**
     * อัปเดตข้อมูลผู้ใช้
     * PUT /api/admin/users/{user}
     */
    public function update(Request $request, User $user)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'password' => 'nullable|string|min:8|confirmed', // ทำให้ password ไม่บังคับเปลี่ยน
        ]);

        $user->name = $validatedData['name'];
        $user->username = $validatedData['username'];
        $user->role = $validatedData['role'];

        if (!empty($validatedData['password'])) {
            $user->password = Hash::make($validatedData['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $user
        ]);
    }

    /**
     * ลบผู้ใช้
     * DELETE /api/admin/users/{user}
     */
    public function destroy(User $user)
    {
        // ป้องกันการลบ superadmin คนอื่น (ถ้ามี) หรือลบตัวเอง
        if ($user->role === 'superadmin' || $user->id === auth()->id()) {
            return response()->json(['message' => 'Action not allowed.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
