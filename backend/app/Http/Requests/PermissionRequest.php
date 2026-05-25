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
        ];
    }
}
