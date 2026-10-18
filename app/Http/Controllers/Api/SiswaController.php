<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SiswaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Siswa::with('kelas');

        if ($request->filled('id_kelas')) {
            $query->where('id_kelas', $request->input('id_kelas'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%")
                    ->orWhere('nama_ortu', 'like', "%{$search}%");
            });
        }

        $siswa = $query->orderBy('id_kelas')->orderBy('nama_lengkap')->get();

        return response()->json([
            'success' => true,
            'data' => $siswa,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nisn' => 'required|string|max:50|unique:siswa,nisn',
            'nama_lengkap' => 'required|string|max:150',
            'jenis_kelamin' => 'required|in:L,P',
            'id_kelas' => 'required|exists:kelas,id',
            'no_hp_ortu' => 'nullable|string|max:20',
            'nama_ortu' => 'nullable|string|max:150',
            'alamat' => 'nullable|string',
            'status' => 'nullable|in:aktif,nonaktif,lulus,pindah',
        ]);

        $siswa = DB::transaction(function () use ($validated) {
            $siswa = Siswa::create($validated);

            // Auto-create akun login siswa
            User::updateOrCreate(
                ['username' => $siswa->nisn],
                [
                    'name' => $siswa->nama_lengkap,
                    'email' => $siswa->nisn.'@santri.sekolah.id',
                    'password' => Hash::make($siswa->nisn),
                    'role' => 'siswa',
                    'id_kelas' => $siswa->id_kelas,
                    'nisn' => $siswa->nisn,
                ]
            );

            return $siswa;
        });

        return response()->json([
            'success' => true,
            'message' => 'Data santri berhasil ditambahkan',
            'data' => $siswa->load('kelas'),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $siswa = Siswa::with(['kelas', 'absensi' => function ($q) {
            $q->orderBy('tanggal', 'desc')->limit(30);
        }, 'tagihanSpp' => function ($q) {
            $q->orderBy('tahun', 'desc')->orderBy('bulan', 'desc');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $siswa,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $siswa = Siswa::findOrFail($id);

        $validated = $request->validate([
            'nisn' => 'required|string|max:50|unique:siswa,nisn,'.$id,
            'nama_lengkap' => 'required|string|max:150',
            'jenis_kelamin' => 'required|in:L,P',
            'id_kelas' => 'required|exists:kelas,id',
            'no_hp_ortu' => 'nullable|string|max:20',
            'nama_ortu' => 'nullable|string|max:150',
            'alamat' => 'nullable|string',
            'status' => 'required|in:aktif,nonaktif,lulus,pindah',
        ]);

        DB::transaction(function () use ($siswa, $validated) {
            $oldNisn = $siswa->nisn;
            $siswa->update($validated);

            // Sync user login data jika NISN / Nama berubah
            $user = User::where('nisn', $oldNisn)->first();
            if ($user) {
                $user->update([
                    'name' => $siswa->nama_lengkap,
                    'username' => $siswa->nisn,
                    'nisn' => $siswa->nisn,
                    'id_kelas' => $siswa->id_kelas,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Data santri berhasil diperbarui',
            'data' => $siswa->fresh()->load('kelas'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $siswa = Siswa::findOrFail($id);

        DB::transaction(function () use ($siswa) {
            User::where('nisn', $siswa->nisn)->delete();
            $siswa->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Data santri berhasil dihapus',
        ]);
    }

    /**
     * Import massal data santri dari array data (Excel/CSV)
     */
    public function importBulk(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.nisn' => 'required|string',
            'items.*.nama_lengkap' => 'required|string',
            'items.*.kelas' => 'required|string',
        ]);

        $imported = 0;
        $errors = [];

        DB::transaction(function () use ($request, &$imported, &$errors) {
            $kelases = Kelas::all()->keyBy(function ($k) {
                return strtoupper(trim($k->nama_kelas));
            });

            foreach ($request->input('items') as $idx => $item) {
                $rawKelas = strtoupper(trim($item['kelas'] ?? ''));
                $kelas = $kelases[$rawKelas] ?? null;

                if (! $kelas) {
                    $errors[] = "Baris #{$idx}: Kelas '{$item['kelas']}' tidak ditemukan.";

                    continue;
                }

                $nisn = trim((string) $item['nisn']);
                $jk = strtoupper(trim($item['jenis_kelamin'] ?? 'L')) === 'P' ? 'P' : 'L';

                $siswa = Siswa::updateOrCreate(
                    ['nisn' => $nisn],
                    [
                        'nama_lengkap' => trim($item['nama_lengkap']),
                        'jenis_kelamin' => $jk,
                        'id_kelas' => $kelas->id,
                        'no_hp_ortu' => $item['no_hp_ortu'] ?? null,
                        'nama_ortu' => $item['nama_ortu'] ?? null,
                        'alamat' => $item['alamat'] ?? null,
                        'status' => 'aktif',
                    ]
                );

                User::updateOrCreate(
                    ['username' => $nisn],
                    [
                        'name' => $siswa->nama_lengkap,
                        'email' => $nisn.'@santri.sekolah.id',
                        'password' => Hash::make($nisn),
                        'role' => 'siswa',
                        'id_kelas' => $kelas->id,
                        'nisn' => $nisn,
                    ]
                );

                $imported++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengimpor {$imported} data santri.",
            'errors' => $errors,
        ]);
    }
}
