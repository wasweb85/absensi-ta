<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanAbsensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nisn' => 'required|string',
            'scanner_role' => 'nullable|string',
            'scanner_kelas' => 'nullable|string',
            'metode_scan' => 'nullable|string|in:qr_kamera,scanner_usb,manual',
            'restrict_kelas' => 'nullable|boolean',
        ];
    }
}
