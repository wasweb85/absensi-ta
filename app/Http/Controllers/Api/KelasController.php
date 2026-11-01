<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KelasController extends Controller
{
    public function index(): JsonResponse
    {
        $kelas = Kelas::with(['guru' => function ($q) {
            $q->select('id', 'name', 'username', 'email', 'role', 'id_kelas');
        }, 'waliGuru'])->withCount('siswa')->orderBy('urutan')->get();

        // Label wali jilid diturunkan dari data guru (putra/putri/campur)
        foreach ($kelas as $item) {
            // Bersihkan nama_kelas jika di database tersimpan dengan imbuhan tanda kurung nama wali
            if (preg_match('/\s*\([^)]+\)\s*$/', (string) $item->nama_kelas)) {
                $cleanedName = trim(preg_replace('/\s*\([^)]+\)\s*$/', '', (string) $item->nama_kelas));
                if (! empty($cleanedName)) {
                    try {
                        DB::table('kelas')->where('id', $item->id)->update(['nama_kelas' => $cleanedName]);
                        $item->nama_kelas = $cleanedName;
                    } catch (\Throwable) {
                        $item->nama_kelas = $cleanedName;
                    }
                }
            }

            $label = Kelas::buildWaliLabel($item->waliGuru);
            if ($label !== null) {
                $item->wali_kelas = $label;
            } elseif ($item->guru) {
                $item->wali_kelas = $item->guru->name;
            }
            $item->unsetRelation('waliGuru');
        }

        return response()->json([
            'success' => true,
            'data' => $kelas,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->has('nama_kelas')) {
            $request->merge([
                'nama_kelas' => trim(preg_replace('/\s*\([^)]+\)\s*$/', '', (string) $request->input('nama_kelas'))),
            ]);
        }

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

        if ($request->has('nama_kelas')) {
            $request->merge([
                'nama_kelas' => trim(preg_replace('/\s*\([^)]+\)\s*$/', '', (string) $request->input('nama_kelas'))),
            ]);
        }

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
