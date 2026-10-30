<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    public function index(): JsonResponse
    {
        $kelas = Kelas::with(['guru' => function ($q) {
            $q->select('id', 'name', 'username', 'email', 'role', 'id_kelas');
        }])->withCount('siswa')->orderBy('urutan')->get();

        // Pastikan atribut wali_kelas konsisten dengan relasi guru tanpa memicu query UPDATE di GET request
        foreach ($kelas as $item) {
            if ($item->guru && $item->wali_kelas !== $item->guru->name) {
                $item->wali_kelas = $item->guru->name;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $kelas,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_kelas' => 'required|string|max:50|unique:kelas,nama_kelas',
            'id_guru' => 'nullable|exists:users,id',
            'wali_kelas' => 'nullable|string|max:100',
            'no_hp_wali' => 'nullable|string|max:20',
            'warna_label' => 'nullable|string|max:20',
            'urutan' => 'nullable|integer',
        ]);

        $idGuru = $validated['id_guru'] ?? null;
        unset($validated['id_guru']);

        if ($idGuru) {
            $guru = User::whereIn('role', ['guru', 'sekretaris', 'bendahara'])->find($idGuru);
            if ($guru) {
                $validated['wali_kelas'] = $guru->name;
            }
        }

        $kelas = Kelas::create($validated);

        if ($idGuru) {
            User::where('id_kelas', $kelas->id)
                ->where('id', '!=', $idGuru)
                ->whereIn('role', ['guru', 'sekretaris', 'bendahara'])
                ->update(['id_kelas' => null]);

            User::where('id', $idGuru)->update(['id_kelas' => $kelas->id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jilid berhasil ditambahkan',
            'data' => $kelas->load('guru'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $kelas = Kelas::findOrFail($id);

        $validated = $request->validate([
            'nama_kelas' => 'required|string|max:50|unique:kelas,nama_kelas,'.$id,
            'id_guru' => 'nullable|exists:users,id',
            'wali_kelas' => 'nullable|string|max:100',
            'no_hp_wali' => 'nullable|string|max:20',
            'warna_label' => 'nullable|string|max:20',
            'urutan' => 'nullable|integer',
        ]);

        $hasIdGuruKey = array_key_exists('id_guru', $validated);
        $idGuru = $validated['id_guru'] ?? null;
        unset($validated['id_guru']);

        if ($hasIdGuruKey) {
            if ($idGuru) {
                $guru = User::whereIn('role', ['guru', 'sekretaris', 'bendahara'])->find($idGuru);
                if ($guru) {
                    $validated['wali_kelas'] = $guru->name;

                    User::where('id_kelas', $kelas->id)
                        ->where('id', '!=', $idGuru)
                        ->whereIn('role', ['guru', 'sekretaris', 'bendahara'])
                        ->update(['id_kelas' => null]);

                    $guru->update(['id_kelas' => $kelas->id]);
                }
            } else {
                User::where('id_kelas', $kelas->id)
                    ->whereIn('role', ['guru', 'sekretaris', 'bendahara'])
                    ->update(['id_kelas' => null]);

                if (! isset($validated['wali_kelas'])) {
                    $validated['wali_kelas'] = null;
                }
            }
        }

        $kelas->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jilid berhasil diperbarui',
            'data' => $kelas->fresh()->load('guru'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $kelas = Kelas::findOrFail($id);

        if ($kelas->siswa()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Jilid tidak dapat dihapus karena masih memiliki santri aktif.',
            ], 422);
        }

        // Lepas guru yang ditugaskan ke kelas ini
        User::where('id_kelas', $kelas->id)
            ->whereIn('role', ['guru', 'sekretaris', 'bendahara'])
            ->update(['id_kelas' => null]);

        $kelas->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jilid berhasil dihapus',
        ]);
    }
}
