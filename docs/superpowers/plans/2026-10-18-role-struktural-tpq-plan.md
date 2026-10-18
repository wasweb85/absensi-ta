# Rencana Implementasi: Role Struktural TPQ (Kepala, Sekretaris, Bendahara, Guru, Admin)

**Tanggal:** 18 Oktober 2026  
**Dokumen Spesifikasi:** `docs/superpowers/specs/2026-10-18-role-struktural-tpq-design.md`  
**Tujuan:** Menerapkan pembagian wewenang role struktural (Kepala TPQ, Sekretaris, Bendahara) yang terintegrasi dengan penugasan mengajar/wali jilid, disertai proteksi API backend dan navigasi frontend Vue 3.

---

### Task 1: Pembaruan Seeder & Akun Demo Default
- [ ] Buka file `database/seeders/DatabaseSeeder.php`
- [ ] Daftarkan 6 akun pengguna:
  - `admin` (role: `admin`, `id_kelas: null`)
  - `kepala` (role: `kepala`, `id_kelas: null`)
  - `sekretaris` (role: `sekretaris`, `id_kelas: JILID 1`)
  - `bendahara` (role: `bendahara`, `id_kelas: JILID 2`)
  - `guru` (role: `guru`, `id_kelas: JILID 3`)
  - `1001` (role: `siswa`, santri Ahmad Fauzi)
- [ ] Jalankan `php artisan db:seed` untuk memperbarui data pengguna di database MySQL.

### Task 2: Middleware & Otorisasi API Backend (Laravel)
- [ ] Buat Middleware Role Authorization `app/Http/Middleware/CheckRole.php` atau filter role pada route API.
- [ ] Daftarkan middleware alias di `bootstrap/app.php`: `'role' => \App\Http\Middleware\CheckRole::class`.
- [ ] Terapkan pembatasan di `routes/api.php`:
  - Kasir & Pembayaran SPP (`/api/spp/bayar`, `/input-cepat`, `/batal-bayar`, `/generate-tagihan`): `role:admin,bendahara`
  - Laporan & Ringkasan SPP (`/api/spp/dashboard`, `/tagihan`, `/riwayat`, `/santri/{id}/tunggakan`): `role:admin,bendahara,kepala`
  - Mutasi Data Santri & Agenda Libur (`POST /api/siswa`, `PUT /api/siswa/{id}`, `DELETE /api/siswa/{id}`, `POST /api/siswa/import`, `/api/agenda/*`, `/api/hari-libur/*`): `role:admin,sekretaris`
  - Presensi Scanner (`/api/absensi/status`, `/scan`, `/monitoring`, `/matrix`, `/riwayat`): `role:admin,kepala,sekretaris,bendahara,guru`
  - Konfigurasi Sistem & Master Kelas (`/api/pengaturan/*`, `POST/PUT/DELETE /api/kelas/*`): `role:admin`

### Task 3: Composable & Matriks Permission Frontend (`useAuth.js`)
- [ ] Buka `resources/js/composables/useAuth.js`
- [ ] Tambahkan computed helpers:
  - `isKepala`, `isSekretaris`, `isBendahara`
  - `canManageSpp` (`['admin', 'bendahara']`)
  - `canViewSppReports` (`['admin', 'bendahara', 'kepala']`)
  - `canManageSantri` (`['admin', 'sekretaris']`)
  - `canManageAgenda` (`['admin', 'sekretaris']`)
  - `canManageSettings` (`['admin']`)
  - `canDoAbsensi` (`['admin', 'kepala', 'sekretaris', 'bendahara', 'guru']`)
- [ ] Sertakan relasi `kelas` pengguna saat `fetchUser` atau login.

### Task 4: Navigasi Sidebar Dinamis (`AppLayout.vue`)
- [ ] Buka `resources/js/layouts/AppLayout.vue`
- [ ] Modifikasi kotak profil di sidebar:
  - Tampilkan label peran dengan badge warna khusus (Kepala = Indigo/Emas, Sekretaris = Biru, Bendahara = Amber, Guru = Emerald, Admin = Abu-abu).
  - Jika pengguna memiliki `user.kelas`, tampilkan badge kelas binaan (cth: "Wali JILID 1").
- [ ] Sesuaikan visibilitas menu:
  - Menu SPP operasional (Kasir, Input Cepat, Broadcast WA) hanya untuk `canManageSpp`.
  - Menu SPP supervisi (Tagihan, Riwayat Kuitansi) untuk `canViewSppReports`.
  - Menu Agenda Libur & tombol tambah/import santri hanya untuk `canManageSantri` / `canManageAgenda`.
  - Menu Master Jilid & Pengaturan Sistem hanya untuk `canManageSettings`.

### Task 5: Router Guard Frontend (`router/index.js`)
- [ ] Buka `resources/js/router/index.js`
- [ ] Beri metadata `meta: { roles: [...] }` pada setiap rute:
  - `/spp/transaksi`, `/spp/input-cepat`, `/spp/pengingat-wa`: `roles: ['admin', 'bendahara']`
  - `/spp/tagihan`, `/spp/riwayat`: `roles: ['admin', 'bendahara', 'kepala']`
  - `/absensi/agenda-libur`: `roles: ['admin', 'sekretaris']`
  - `/kelas`, `/pengaturan`: `roles: ['admin']`
- [ ] Modifikasi `router.beforeEach`:
  - Jika rute memiliki `meta.roles` dan role pengguna tidak termasuk dalam daftar yang diizinkan, batalkan navigasi dan arahkan ke `/dashboard`.
  - Munculkan pesan notifikasi toast peringatan akses ditolak.

### Task 6: Pilihan Demo Login 1-Klik (`LoginView.vue`)
- [ ] Buka `resources/js/views/auth/LoginView.vue`
- [ ] Tambahkan tombol demo cepat 1-klik untuk seluruh peran:
  - Admin (`admin` / `admin123`)
  - Kepala TPQ (`kepala` / `kepala123`)
  - Sekretaris (`sekretaris` / `sekretaris123`)
  - Bendahara (`bendahara` / `bendahara123`)
  - Guru / Wali Jilid (`guru` / `guru123`)
  - Santri (`1001` / `1001`)

### Task 7: Pengujian Otomatis & Verifikasi Build
- [ ] Buat Feature Test di `tests/Feature/RolePermissionTest.php` untuk menguji hak akses otorisasi seluruh peran.
- [ ] Jalankan `php artisan test --compact` untuk memastikan seluruh test lulus.
- [ ] Jalankan `npm run build` untuk memverifikasi tidak ada error sintaks/bundle pada frontend.
