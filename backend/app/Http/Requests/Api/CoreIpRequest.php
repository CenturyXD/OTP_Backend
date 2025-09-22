<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CoreIpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        // $ipId = $this->route('coreIp') ? $this->route('coreIp')->id : null;
        $ipId = $this->route('core_ip') ? $this->route('core_ip')->id : null;

        return [
            'ip_address' => ['required', 'string', 'max:255', Rule::unique('core_ips')->ignore($ipId)],
            'division'   => 'required|string|max:255',
            'contact'    => 'required|string|max:255',
            'phone'      => 'required|string|max:255',
            'remark'     => 'nullable|string',
            'status'     => ['required', Rule::in(['active', 'inactive', 'reserved', 'maintenance'])],
        ];
    }
}
