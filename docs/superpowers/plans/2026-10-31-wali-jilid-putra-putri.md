# Implementasi Manajemen Wali Jilid Putra & Putri Fleksibel

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mendukung penugasan wali jilid fleksibel per gender (1 Jilid dapat memiliki 1 Wali Putra dan 1 Wali Putri) dengan penyaringan absensi serta tanda tangan cetak laporan yang otomatis tepat sasaran.

**Architecture:** Menambahkan kolom `gender_binaan` pada tabel `users`, menyesuaikan validasi penugasan di `UserController` dan filter absensi di `AbsensiController`, serta memperbarui UI `GuruView`, `MonitoringView`, dan `RekapMatrixView` tanpa menambahkan card berlebihan.

**Tech Stack:** Laravel 11 (PHP 8.3), SQLite/MySQL, Vue 3, Tailwind CSS, Vite.

## Global Constraints
- Tidak menambahkan card-card baru yang berlebihan pada UI; gunakan dropdown dan badge ringkas pada toolbar yang sudah ada.
- Menjaga cetak laporan A4 landscape tetap pas dalam 1 lembar (Kop resmi, tabel, keterangan mepet, tanda tangan).
- Menjaga seluruh 59+ test PHPUnit tetap 100% lulus.

---

### Task 1: Database Migration & Model Updates

**Files:**
- Create: `database/migrations/2026_10_31_000001_add_gender_binaan_to_users_table.php`
- Modify: `app/Models/User.php:10-25`
- Test: `tests/Feature/WaliJilidGenderTest.php`

**Interfaces:**
- Produces: `users.gender_binaan` string column (`all`, `L`, `P`), default `all`.

- [ ] **Step 1: Write the failing test**

Buat file test `tests/Feature/WaliJilidGenderTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaliJilidGenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_gender_binaan_attribute_default_all(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'gender_binaan' => 'all',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_user_has_gender_binaan_attribute_default_all`
Expected: FAIL (column gender_binaan does not exist).

- [ ] **Step 3: Create migration and update User model**

Buat migration `database/migrations/2026_10_31_000001_add_gender_binaan_to_users_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender_binaan', 10)->default('all')->after('id_kelas');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('gender_binaan');
        });
    }
};
```

Update `app/Models/User.php`:
Tambahkan `'gender_binaan'` ke atribut `#[Fillable(['name', 'username', 'email', 'password', 'role', 'id_kelas', 'gender_binaan', 'nisn'])]`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_user_has_gender_binaan_attribute_default_all`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_10_31_000001_add_gender_binaan_to_users_table.php app/Models/User.php tests/Feature/WaliJilidGenderTest.php
git commit -m "feat: add gender_binaan column to users table"
```

---

### Task 2: Backend Multi-Wali Assignment & Absensi Filtering

**Files:**
- Modify: `app/Http/Controllers/Api/UserController.php:45-120`
- Modify: `app/Http/Controllers/Api/AbsensiController.php:30-150`
- Test: `tests/Feature/WaliJilidGenderTest.php`

**Interfaces:**
- Consumes: `users.gender_binaan`, `kelas.id`
- Produces: API response with Wali Putra & Putri support, `wali_kelas` combined summary, and gender filtering in `/absensi/monitoring` and `/absensi/matrix`.

- [ ] **Step 1: Write failing tests for multi-wali assignment and gender filtering**

Tambahkan method test berikut pada `tests/Feature/WaliJilidGenderTest.php`:
```php
    public function test_can_assign_two_teachers_to_same_class_with_different_gender_binaan(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = Kelas::create(['nama_kelas' => 'JILID 3', 'warna_label' => '#2563eb']);

        // 1. Assign Ustadz (Putra)
        $res1 = $this->actingAs($admin, 'sanctum')->postJson('/api/users', [
            'name' => 'Ustadz Fairus',
            'username' => 'fairus',
            'password' => 'secret123',
            'role' => 'guru',
            'id_kelas' => $kelas->id,
            'gender_binaan' => 'L',
        ]);
        $res1->assertStatus(201);

        // 2. Assign Ustadzah (Putri) to the same class
        $res2 = $this->actingAs($admin, 'sanctum')->postJson('/api/users', [
            'name' => 'Ustadzah Siti',
            'username' => 'siti',
            'password' => 'secret123',
            'role' => 'guru',
            'id_kelas' => $kelas->id,
            'gender_binaan' => 'P',
        ]);
        $res2->assertStatus(201);

        // 3. Reject second Ustadz with same gender on the same class
        $res3 = $this->actingAs($admin, 'sanctum')->postJson('/api/users', [
            'name' => 'Ustadz Ali',
            'username' => 'ali',
            'password' => 'secret123',
            'role' => 'guru',
            'id_kelas' => $kelas->id,
            'gender_binaan' => 'L',
        ]);
        $res3->assertStatus(422);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_can_assign_two_teachers_to_same_class_with_different_gender_binaan`
Expected: FAIL.

- [ ] **Step 3: Implement validation and wali sync in `UserController.php`**

Sesuaikan `UserController.php` pada `store()` dan `update()`:
1. Validasi `gender_binaan`: `['nullable', Rule::in(['all', 'L', 'P'])]`. Default ke `'all'`.
2. Cek collision: jika `id_kelas` terisi:
   - Jika `gender_binaan == 'all'`, tidak boleh ada guru lain di jilid tersebut.
   - Jika `gender_binaan == 'L'`, tidak boleh ada guru lain ber-gender `'L'` atau `'all'` di jilid tersebut.
   - Jika `gender_binaan == 'P'`, tidak boleh ada guru lain ber-gender `'P'` atau `'all'` di jilid tersebut.
3. Sinkronkan `wali_kelas` di tabel `kelas`:
   Gabungkan nama guru yang aktif, misal `"Ustadz Fairus (Putra) & Ustadzah Siti (Putri)"` atau `"Ustadzah Fatimah"`.

- [ ] **Step 4: Implement gender filtering in `AbsensiController.php`**

1. Pada method `monitoring()`:
   - Tambahkan filter `$request->input('jenis_kelamin')` jika terisi.
   - Default ke `$user->gender_binaan` jika user adalah guru dan memiliki binaan `'L'` atau `'P'`.
2. Pada method `matrix()`:
   - Tambahkan filter `$request->input('jenis_kelamin')` jika terisi (`L` atau `P`).
   - Lampirkan daftar wali pengampu jilid (wali putra & wali putri) di objek payload respon.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=WaliJilidGenderTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/UserController.php app/Http/Controllers/Api/AbsensiController.php tests/Feature/WaliJilidGenderTest.php
git commit -m "feat: implement dual-wali assignment and gender filtering in backend"
```

---

### Task 3: Frontend Guru Management UI (`GuruView.vue`)

**Files:**
- Modify: `resources/js/views/master/GuruView.vue`

**Interfaces:**
- Consumes: `users.gender_binaan`, `kelasList`
- Produces: Dropdown pemilihan kelompok binaan & badge rapi tanpa card tambahan.

- [ ] **Step 1: Add `gender_binaan` to form and table in `GuruView.vue`**

1. Pada reactive form:
   `gender_binaan: 'all'`
2. Pada template dialog Tambah/Edit Guru:
   Di bawah pilihan `Wali Jilid Binaan`, tambahkan dropdown jika `form.id_kelas` terisi:
   ```html
   <div v-if="form.id_kelas" class="space-y-1.5">
     <Label for="userGenderBinaan" class="text-xs font-medium">Kelompok Binaan</Label>
     <select
       id="userGenderBinaan"
       v-model="form.gender_binaan"
       class="h-9 w-full rounded-lg border border-input bg-transparent px-3 text-xs"
     >
       <option value="all">Semua Santri / Campur (Jilid 1-2)</option>
       <option value="L">Khusus Santri Putra (Ustadz)</option>
       <option value="P">Khusus Santri Putri (Ustadzah)</option>
     </select>
   </div>
   ```
3. Pada tabel daftar guru:
   Tampilkan badge jilid binaan yang menyertakan info gender:
   - `JILID 3 (Putra)`
   - `JILID 3 (Putri)`
   - `JILID 1`

- [ ] **Step 2: Verify Vite compilation**

Run: `npm run build`
Expected: 0 errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/master/GuruView.vue
git commit -m "feat: add gender_binaan selection and badge indicators in GuruView"
```

---

### Task 4: Frontend Monitoring & Rekapitulasi Presensi UI (`MonitoringView.vue` & `RekapMatrixView.vue`)

**Files:**
- Modify: `resources/js/views/absensi/MonitoringView.vue`
- Modify: `resources/js/views/absensi/RekapMatrixView.vue`

**Interfaces:**
- Consumes: `user.gender_binaan`, `jenis_kelamin` filter parameter
- Produces: Sleek gender dropdowns in existing toolbars, dynamic print title, and auto-matching signature names.

- [ ] **Step 1: Add gender filter in `MonitoringView.vue`**

1. Tambahkan state `selectedGender = ref('')`.
2. Pada `onMounted`: jika `user.role === 'guru'` dan `user.gender_binaan !== 'all'`, set default `selectedGender.value = user.gender_binaan`.
3. Tambahkan dropdown gender ringkas di grid toolbar yang sudah ada (tanpa card tambahan).
4. Kirim `jenis_kelamin: selectedGender.value` ke API monitoring.

- [ ] **Step 2: Add gender filter and dynamic signatures in `RekapMatrixView.vue`**

1. Tambahkan state `selectedGender = ref('')`.
2. Pada `onMounted`: jika `user.role === 'guru'` dan `user.gender_binaan !== 'all'`, set default `selectedGender.value = user.gender_binaan`.
3. Tambahkan dropdown gender ringkas di baris toolbar rekap di samping dropdown Jilid:
   ```html
   <select
     id="selectGender"
     v-model="selectedGender"
     class="h-9 rounded-lg border border-input bg-card px-3 text-xs font-medium text-foreground cursor-pointer"
     @change="loadMatrix"
   >
     <option value="">Semua Gender</option>
     <option value="L">Santri Putra (L)</option>
     <option value="P">Santri Putri (P)</option>
   </select>
   ```
4. Perbarui judul dokumen cetak:
   - Jika filter `L`: `JILID 3 (PUTRA)`
   - Jika filter `P`: `JILID 3 (PUTRI)`
   - Jika `''`: `JILID 3`
5. Perbarui nama tanda tangan kanan (`ustadzPengampuName`):
   - Jika filter `L`: cari nama ustadz pembina putra pada jilid terpilih.
   - Jika filter `P`: cari nama ustadzah pembina putri pada jilid terpilih.
6. Perbarui CSV export untuk menyertakan jilid dan kelompok gender pada baris header dan nama wali yang sesuai pada tanda tangan bawah.

- [ ] **Step 3: Verify Vite compilation**

Run: `npm run build`
Expected: 0 errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/absensi/MonitoringView.vue resources/js/views/absensi/RekapMatrixView.vue
git commit -m "feat: add gender filtering and auto-matching signatures in Monitoring and Rekap views"
```

---

### Task 5: Quality Gate & Full Regression Suite

**Files:**
- Test all components

- [ ] **Step 1: Format code style with Pint**

Run: `vendor/bin/pint --format agent`
Expected: passed.

- [ ] **Step 2: Run all PHPUnit feature and unit tests**

Run: `php artisan test --compact`
Expected: 60+ passed, 0 failures.

- [ ] **Step 3: Run frontend production build**

Run: `npm run build`
Expected: 0 errors.

- [ ] **Step 4: Commit and push**

```bash
git push origin main
git push origin main:master
```
