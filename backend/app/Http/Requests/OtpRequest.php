<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OtpRequest extends FormRequest
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
            'target' => 'required|string',
            'service' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'target.required' => 'กรุณาระบุอีเมลผู้ส่ง (target) ใน request',
            'target.string' => 'target ต้องเป็นข้อความ (string) เท่านั้น',
            'service.string' => 'service ต้องเป็นข้อความ (string) เท่านั้น',
            'service.nullable' => 'service สามารถเว้นว่างได้',
        ];
    }
}
