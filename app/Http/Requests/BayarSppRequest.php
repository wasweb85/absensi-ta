<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BayarSppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'bendahara', 'sekretaris'], true);
    }

    public function rules(): array
    {
        return [
            'id_tagihan' => 'required|exists:tagihan_spp,id',
            'jumlah_bayar' => 'required|numeric|min:1',
            'metode_pembayaran' => 'nullable|in:Tunai,Transfer,tunai,transfer,qris',
            'catatan' => 'nullable|string|max:255',
        ];
    }
}

