<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiswaRequest;
use App\Http\Requests\UpdateSiswaRequest;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\User;
use App\Services\GoogleSheetSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SiswaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Siswa::with('kelas');
        $user = $request->user();

        // Guru hanya dapat melihat santri di kelas binaannya
        if ($user && $user->role === 'guru' && $user->id_kelas) {
            $query->where('id_kelas', $user->id_kelas);
        } elseif ($request->filled('id_kelas')) {
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

    public function store(StoreSiswaRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $query = Siswa::with(['kelas', 'absensi' => function ($q) {
            $q->orderBy('tanggal', 'desc')->limit(30);
        }]);

        if ($user && $user->role === 'guru' && $user->id_kelas) {
            $query->where('id_kelas', $user->id_kelas);
        }

        $siswa = $query->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $siswa,
        ]);
    }

    public function update(UpdateSiswaRequest $request, int $id): JsonResponse
    {
        $siswa = Siswa::findOrFail($id);
        $validated = $request->validated();

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

            $items = $request->input('items');
            $batchNisns = collect($items)
                ->map(fn ($item) => trim((string) ($item['nisn'] ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $existingUsers = $batchNisns !== []
                ? User::whereIn('username', $batchNisns)->orWhereIn('nisn', $batchNisns)->get()->keyBy(fn ($u) => $u->nisn ?: $u->username)
                : collect();

            foreach ($items as $idx => $item) {
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

                $user = $existingUsers->get($nisn);
                if ($user) {
                    $user->update([
                        'name' => $siswa->nama_lengkap,
                        'id_kelas' => $kelas->id,
                        'nisn' => $nisn,
                    ]);
                } else {
                    $newUser = User::create([
                        'name' => $siswa->nama_lengkap,
                        'username' => $nisn,
                        'email' => $nisn.'@santri.sekolah.id',
                        'password' => Hash::make($nisn),
                        'role' => 'siswa',
                        'id_kelas' => $kelas->id,
                        'nisn' => $nisn,
                    ]);
                    $existingUsers->put($nisn, $newUser);
                }

                $imported++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengimpor {$imported} data santri.",
            'errors' => $errors,
        ]);
    }

    /**
     * Dapatkan URL spreadsheet yang tersimpan
     */
    public function getSpreadsheetInfo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'spreadsheet_url' => Pengaturan::getValue('spreadsheet_santri_url', ''),
                'auto_create_kelas' => Pengaturan::getValue('spreadsheet_auto_create_kelas', '1') === '1',
            ],
        ]);
    }

    /**
     * Sinkronisasi data santri langsung dari Google Sheets
     */
    public function syncSpreadsheet(Request $request, GoogleSheetSyncService $syncService): JsonResponse
    {
        $validated = $request->validate([
            'spreadsheet_url' => 'required|url',
            'auto_create_kelas' => 'nullable|boolean',
            'save_as_default' => 'nullable|boolean',
        ]);

        $url = $validated['spreadsheet_url'];
        $autoCreateKelas = $request->boolean('auto_create_kelas', true);
        $saveAsDefault = $request->boolean('save_as_default', true);

        if ($saveAsDefault) {
            Pengaturan::setValue('spreadsheet_santri_url', $url);
            Pengaturan::setValue('spreadsheet_auto_create_kelas', $autoCreateKelas ? '1' : '0');
        }

        try {
            $rows = $syncService->fetchAndParse($url);
            $result = $syncService->syncData($rows, $autoCreateKelas);

            $msg = "Sinkronisasi selesai! {$result['created']} data baru ditambahkan, {$result['updated']} diperbarui.";
            if ($result['failed'] > 0) {
                $msg .= " ({$result['failed']} baris perlu diperiksa).";
            }

            return response()->json([
                'success' => true,
                'message' => $msg,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
