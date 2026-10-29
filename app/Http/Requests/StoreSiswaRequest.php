<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'sekretaris', 'bendahara'], true);
    }

    public function rules(): array
    {
        return [
            'nisn' => 'required|string|max:50|unique:siswa,nisn',
            'nama_lengkap' => 'required|string|max:150',
            'jenis_kelamin' => 'required|in:L,P',
            'id_kelas' => 'required|exists:kelas,id',
            'no_hp_ortu' => 'nullable|string|max:20',
            'nama_ortu' => 'nullable|string|max:150',
            'alamat' => 'nullable|string',
            'status' => 'nullable|in:aktif,nonaktif,lulus,pindah',
        ];
    }
}
