<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiswaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nisn' => $this->nisn,
            'nama_lengkap' => $this->nama_lengkap,
            'jenis_kelamin' => $this->jenis_kelamin,
            'id_kelas' => $this->id_kelas,
            'kelas' => $this->whenLoaded('kelas'),
            'no_hp_ortu' => $this->no_hp_ortu,
            'nama_ortu' => $this->nama_ortu,
            'alamat' => $this->alamat,
            'status' => $this->status,
            'foto' => $this->foto,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
