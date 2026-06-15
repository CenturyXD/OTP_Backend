<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'emails' => 'required|string',
            'service' => 'required|string',
            'password' => 'nullable|string',
            'expires_at' => 'nullable|date',
            'is_verified' => 'nullable|boolean',
            'mail_type' => 'nullable|string',
            'screen_locks' => 'nullable|array',
            'screen_locks.*.screen_name' => 'required_with:screen_locks|string|max:50',
            'screen_locks.*.lock_code' => 'required_with:screen_locks|string|min:4|max:32',
            'refresh_token' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'emails.required' => 'กรุณาระบุอีเมลล์',
            'emails.array' => 'emails ต้องเป็นอาร์เรย์ของอีเมล',
            'otp.required' => 'กรุณาระบุ บริการ ',
            'otp.string' => 'บริการ ต้องเป็นข้อความ เท่านั้น',
            'expires_at.date' => 'expires_at ต้องเป็นวันที่ที่ถูกต้อง',
            'expires_at.nullable' => 'expires_at สามารถเว้นว่างได้',
            'is_verified.boolean' => 'is_verified ต้องเป็นค่า true หรือ false',
            'is_verified.nullable' => 'is_verified สามารถเว้นว่างได้',
            'mail_type.string' => 'mail_type ต้องเป็นข้อความ เท่านั้น',
            'mail_type.nullable' => 'mail_type สามารถเว้นว่างได้',
            'screen_locks.array' => 'screen_locks ต้องเป็นรายการของหน้าจอ',
            'screen_locks.*.screen_name.required_with' => 'กรุณาระบุชื่อหน้าจอของ lock code',
            'screen_locks.*.screen_name.string' => 'ชื่อหน้าจอต้องเป็นข้อความ',
            'screen_locks.*.lock_code.required_with' => 'กรุณาระบุรหัสล็อคจอ',
            'screen_locks.*.lock_code.min' => 'รหัสล็อคจอต้องมีอย่างน้อย 4 ตัวอักษร',
        ];
    }
}
