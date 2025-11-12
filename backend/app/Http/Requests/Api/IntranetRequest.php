<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IntranetRequest extends FormRequest
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
        $ipId = $this->route('intra_ip') ? $this->route('intra_ip')->id : null;

        return [
            'ip_address' => ['required', 'string', 'max:255', Rule::unique('intranet', 'ip_address')->ignore($ipId)],
            'customer'   => 'required|string|max:255',
            'contact'    => 'required|string|max:255',
            'phone'      => 'required|string|max:255',
            'remark'     => 'nullable|string',
            'status'     => ['required', Rule::in(['active', 'inactive', 'reserved', 'maintenance'])],
        ];
    }
}
