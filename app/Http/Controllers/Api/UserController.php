<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Menampilkan daftar staf, guru, dan pengurus TPQ
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('kelas')->whereIn('role', ['admin', 'kepala', 'sekretaris', 'bendahara', 'guru']);

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('role')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Menambahkan akun guru / staf baru
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'nullable|email|max:100|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(['admin', 'kepala', 'sekretaris', 'bendahara', 'guru'])],
            'id_kelas' => 'nullable|exists:kelas,id',
            'gender_binaan' => ['nullable', Rule::in(['all', 'L', 'P'])],
        ]);

        $validated['gender_binaan'] = $validated['gender_binaan'] ?? 'all';
        $validated['password'] = Hash::make($validated['password']);

        // Jika role adalah kepala atau admin, id_kelas otomatis null
        if (in_array($validated['role'], ['kepala', 'admin'], true)) {
            $validated['id_kelas'] = null;
        }

        if (! empty($validated['id_kelas'])) {
            $this->validateWaliCollision((int) $validated['id_kelas'], $validated['gender_binaan'], $validated['role']);
        }

        $user = User::create($validated);

        // Jika ditugaskan sebagai wali jilid, update wali_kelas di tabel kelas
        if ($user->id_kelas) {
            $this->syncWaliKelas((int) $user->id_kelas);
        }

        return response()->json([
            'success' => true,
            'message' => 'Akun guru/pengurus berhasil ditambahkan.',
            'data' => $user->load('kelas'),
        ], 201);
    }

    /**
     * Menampilkan detail satu akun
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with('kelas')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Memperbarui data akun guru / staf
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role' => ['required', Rule::in(['admin', 'kepala', 'sekretaris', 'bendahara', 'guru'])],
            'id_kelas' => 'nullable|exists:kelas,id',
            'gender_binaan' => ['nullable', Rule::in(['all', 'L', 'P'])],
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (in_array($validated['role'], ['kepala', 'admin'], true)) {
            $validated['id_kelas'] = null;
        }

        $genderBinaan = array_key_exists('gender_binaan', $validated)
            ? ($validated['gender_binaan'] ?? 'all')
            : ($user->gender_binaan ?? 'all');
        $validated['gender_binaan'] = $genderBinaan;

        $targetKelasId = array_key_exists('id_kelas', $validated)
            ? $validated['id_kelas']
            : $user->id_kelas;

        $targetRole = $validated['role'] ?? $user->role;

        if (! empty($targetKelasId)) {
            $this->validateWaliCollision((int) $targetKelasId, $genderBinaan, $targetRole, $user->id);
        }

        $oldKelasId = $user->id_kelas;
        $user->update($validated);
        $newKelasId = $user->id_kelas;

        // Sinkronisasi kelas lama jika berpindah atau dilepas
        if ($oldKelasId && (int) $oldKelasId !== (int) $newKelasId) {
            $this->syncWaliKelas((int) $oldKelasId);
        }

        // Sinkronisasi kelas baru jika ada
        if ($newKelasId) {
            $this->syncWaliKelas((int) $newKelasId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data guru/pengurus berhasil diperbarui.',
            'data' => $user->fresh()->load('kelas'),
        ]);
    }

    /**
     * Menghapus akun pengguna
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($request->user()->id === $id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang login.',
            ], 422);
        }

        $user = User::findOrFail($id);
        $kelasId = $user->id_kelas;
        $user->delete();

        if ($kelasId) {
            $this->syncWaliKelas((int) $kelasId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Akun guru/pengurus berhasil dihapus.',
        ]);
    }

    /**
     * Memeriksa bentrokan wali jilid binaan pada kelas tertentu
     *
     * @throws ValidationException
     */
    protected function validateWaliCollision(int $kelasId, string $genderBinaan, string $role = 'guru', ?int $ignoreUserId = null): void
    {
        if ($role !== 'guru') {
            return;
        }

        $existing = User::where('id_kelas', $kelasId)
            ->where('role', 'guru')
            ->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))
            ->get();

        $hasCollision = false;
        if ($genderBinaan === 'all') {
            $hasCollision = $existing->isNotEmpty();
        } elseif ($genderBinaan === 'L') {
            $hasCollision = $existing->contains(fn ($u) => in_array($u->gender_binaan, ['L', 'all', null], true));
        } elseif ($genderBinaan === 'P') {
            $hasCollision = $existing->contains(fn ($u) => in_array($u->gender_binaan, ['P', 'all', null], true));
        }

        if ($hasCollision) {
            throw ValidationException::withMessages([
                'id_kelas' => 'Jilid ini sudah memiliki wali untuk kelompok binaan tersebut.',
            ]);
        }
    }

    /**
     * Menyinkronkan kolom wali_kelas pada tabel kelas berdasarkan guru aktif
     */
    protected function syncWaliKelas(int $kelasId): void
    {
        $kelas = Kelas::find($kelasId);
        if (! $kelas) {
            return;
        }

        $teachers = User::where('id_kelas', $kelasId)
            ->where('role', 'guru')
            ->get();

        if ($teachers->isEmpty()) {
            $otherStaff = User::where('id_kelas', $kelasId)
                ->whereIn('role', ['sekretaris', 'bendahara'])
                ->first();

            $kelas->update(['wali_kelas' => $otherStaff ? $otherStaff->name : null]);

            return;
        }

        $waliAll = $teachers->first(fn ($u) => $u->gender_binaan === 'all' || empty($u->gender_binaan));
        if ($waliAll) {
            $kelas->update(['wali_kelas' => $waliAll->name]);

            return;
        }

        $putra = $teachers->firstWhere('gender_binaan', 'L');
        $putri = $teachers->firstWhere('gender_binaan', 'P');

        $label = null;
        if ($putra && $putri) {
            $label = "{$putra->name} (Putra) & {$putri->name} (Putri)";
        } elseif ($putra) {
            $label = "{$putra->name} (Putra)";
        } elseif ($putri) {
            $label = "{$putri->name} (Putri)";
        }

        $kelas->update(['wali_kelas' => $label]);
    }
}
