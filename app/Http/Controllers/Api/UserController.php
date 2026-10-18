<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Menampilkan daftar staf, guru, dan pengurus TPQ (selain siswa)
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('kelas')->where('role', '!=', 'siswa');

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
        ]);

        $validated['password'] = Hash::make($validated['password']);

        // Jika role adalah kepala atau admin, id_kelas otomatis null
        if (in_array($validated['role'], ['kepala', 'admin'], true)) {
            $validated['id_kelas'] = null;
        }

        $user = User::create($validated);

        // Jika ditugaskan sebagai wali jilid, update wali_kelas di tabel kelas
        if ($user->id_kelas) {
            Kelas::where('id', $user->id_kelas)->update(['wali_kelas' => $user->name]);
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
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (in_array($validated['role'], ['kepala', 'admin'], true)) {
            $validated['id_kelas'] = null;
        }

        $user->update($validated);

        // Sinkronisasi wali kelas jika id_kelas terisi
        if ($user->id_kelas) {
            Kelas::where('id', $user->id_kelas)->update(['wali_kelas' => $user->name]);
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
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Akun guru/pengurus berhasil dihapus.',
        ]);
    }
}
