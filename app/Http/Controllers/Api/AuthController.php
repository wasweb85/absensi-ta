<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login multi-role (Admin, Guru, Siswa) menggunakan Username / NISN dan Password
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = trim($request->input('username'));
        $password = $request->input('password');

        // Cari user berdasarkan username, email, atau nisn
        $user = User::with('kelas')->where('username', $loginInput)
            ->orWhere('email', $loginInput)
            ->orWhere('nisn', $loginInput)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Kredensial login (Username / NISN atau Password) tidak sesuai.'],
            ]);
        }

        // Buat access token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'id_kelas' => $user->id_kelas,
                'nama_kelas' => $user->kelas?->nama_kelas,
                'nisn' => $user->nisn,
            ],
        ]);
    }

    /**
     * Mengambil profil pengguna yang sedang login
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('kelas', 'siswa');

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'id_kelas' => $user->id_kelas,
                'nama_kelas' => $user->kelas?->nama_kelas,
                'nisn' => $user->nisn,
                'siswa' => $user->siswa,
            ],
        ]);
    }

    /**
     * Logout dan cabut token aktif
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    }
}
