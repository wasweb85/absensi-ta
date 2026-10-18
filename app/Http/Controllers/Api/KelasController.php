<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    public function index(): JsonResponse
    {
        $kelas = Kelas::withCount('siswa')->orderBy('urutan')->get();

        return response()->json([
            'success' => true,
            'data' => $kelas,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_kelas' => 'required|string|max:50|unique:kelas,nama_kelas',
            'nominal_spp' => 'required|numeric|min:0',
            'wali_kelas' => 'nullable|string|max:100',
            'no_hp_wali' => 'nullable|string|max:20',
            'warna_label' => 'nullable|string|max:20',
            'urutan' => 'nullable|integer',
        ]);

        $kelas = Kelas::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kelas berhasil ditambahkan',
            'data' => $kelas,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $kelas = Kelas::findOrFail($id);

        $validated = $request->validate([
            'nama_kelas' => 'required|string|max:50|unique:kelas,nama_kelas,'.$id,
            'nominal_spp' => 'required|numeric|min:0',
            'wali_kelas' => 'nullable|string|max:100',
            'no_hp_wali' => 'nullable|string|max:20',
            'warna_label' => 'nullable|string|max:20',
            'urutan' => 'nullable|integer',
        ]);

        $kelas->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kelas berhasil diperbarui',
            'data' => $kelas,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $kelas = Kelas::findOrFail($id);

        if ($kelas->siswa()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak dapat dihapus karena masih memiliki santri aktif.',
            ], 422);
        }

        $kelas->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kelas berhasil dihapus',
        ]);
    }
}
