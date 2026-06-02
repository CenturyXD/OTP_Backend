<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Exception;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\ProfileRequest;
use App\Http\Requests\Api\PasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        DB::beginTransaction();
        $logoPath = null;

        try {
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('logos', 'public');
            }

            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => 'deactive',
                'role' => 'user',
                'logo' => $logoPath,
                'theme' => $request->theme,
                'theme[primary]' => $request->theme['primary'] ?? null,
                'theme[primary-dark]' => $request->theme['primary-dark'] ?? null,
                'theme[accent]' => $request->theme['accent'] ?? null,
                'theme[secondary]' => $request->theme['secondary'] ?? null,
                'theme[gradient]' => $request->theme['gradient'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'user' => $user,
                'logo_url' => $user->logo ? asset('storage/' . $user->logo) : null,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'User registration failed due to a server error.'
            ], 500);
        }
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password'
            ], 401);
        }

        // เช็คสถานะ active
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active. Please contact the administrator.'
            ], 401);
        }

        $token = $user->createToken('auth_token', [], now()->addHour())->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'role' => $user->role,
            'name' => $user->name,
            'email' => $user->email
        ]);
    }

    public function updateprofile(ProfileRequest $request)
    {
        DB::beginTransaction();
        $newLogoPath = null;

        try {
            $user = User::findOrFail(Auth::id());

            if ($request->has('name')) {
                $user->name = $request->name;
            }
            if ($request->has('username')) {
                $user->username = $request->username;
            }
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            if ($request->hasFile('logo')) {
                $newLogoPath = $request->file('logo')->store('logos', 'public');

                if ($user->logo && Storage::disk('public')->exists($user->logo)) {
                    Storage::disk('public')->delete($user->logo);
                }

                $user->logo = $newLogoPath;
            }
            if ($request->has('theme')) {
                $user->theme = $request->theme;
            }
            if ($request->has('theme[primary]')) {
                $user->theme['primary'] = $request->input('theme[primary]');
            }
            if ($request->has('theme[primary-dark]')) {
                $user->theme['primary-dark'] = $request->input('theme[primary-dark]');
            }
            if ($request->has('theme[accent]')) {
                $user->theme['accent'] = $request->input('theme[accent]');
            }
            if ($request->has('theme[secondary]')) {
                $user->theme['secondary'] = $request->input('theme[secondary]');
            }
            if ($request->has('theme[gradient]')) {
                $user->theme['gradient'] = $request->input('theme[gradient]');
            }

            $user->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => $user,
                'logo_url' => $user->logo ? asset('storage/' . $user->logo) : null,
                'theme' => $user->theme,
                'theme[primary]' => $user->theme['primary'] ?? null,
                'theme[primary-dark]' => $user->theme['primary-dark'] ?? null,
                'theme[accent]' => $user->theme['accent'] ?? null,
                'theme[secondary]' => $user->theme['secondary'] ?? null,
                'theme[gradient]' => $user->theme['gradient'] ?? null,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            if ($newLogoPath && Storage::disk('public')->exists($newLogoPath)) {
                Storage::disk('public')->delete($newLogoPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Profile update failed due to a server error.'
            ], 500);
        }
    }

    public function changepassword(PasswordRequest $request)
    {
        $user = User::findOrFail(Auth::id());

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    public function info($id)
    {
        try {
            $user = User::findOrFail($id);

            return response()->json([
                'success' => true,
                'logo_url' => $user->logo ? asset('storage/' . $user->logo) : null,
                'theme' => $user->theme,
                'theme[primary]' => $user->theme['primary'] ?? null,
                'theme[primary-dark]' => $user->theme['primary-dark'] ?? null,
                'theme[accent]' => $user->theme['accent'] ?? null,
                'theme[secondary]' => $user->theme['secondary'] ?? null,
                'theme[gradient]' => $user->theme['gradient'] ?? null,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user info due to a server error.'
            ], 500);
        }
    }

    public function profile()
    {
        $user = User::findOrFail(Auth::id());

        return response()->json([
            'success' => true,
            'user' => $user,
            'logo_url' => $user->logo ? asset('storage/' . $user->logo) : null,
            'theme' => $user->theme,
            'theme[primary]' => $user->theme['primary'] ?? null,
            'theme[primary-dark]' => $user->theme['primary-dark'] ?? null,
            'theme[accent]' => $user->theme['accent'] ?? null,
            'theme[secondary]' => $user->theme['secondary'] ?? null,
            'theme[gradient]' => $user->theme['gradient'] ?? null,
        ]);
    }
}
