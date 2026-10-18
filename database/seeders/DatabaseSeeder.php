<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Kelas / Jilid (Master Data untuk Absensi & SPP)
        $kelasList = [
            [
                'nama_kelas' => 'JILID 1',
                'nominal_spp' => 50000,
                'wali_kelas' => 'Ustadzah Fatimah',
                'no_hp_wali' => '081234567801',
                'warna_label' => '#f44336',
                'urutan' => 1,
            ],
            [
                'nama_kelas' => 'JILID 2',
                'nominal_spp' => 50000,
                'wali_kelas' => 'Ustadzah Aisyah',
                'no_hp_wali' => '081234567802',
                'warna_label' => '#e91e63',
                'urutan' => 2,
            ],
            [
                'nama_kelas' => 'JILID 3',
                'nominal_spp' => 60000,
                'wali_kelas' => 'Ustadz Abdullah',
                'no_hp_wali' => '081234567803',
                'warna_label' => '#f2a93b',
                'urutan' => 3,
            ],
            [
                'nama_kelas' => 'JILID 4',
                'nominal_spp' => 60000,
                'wali_kelas' => 'Ustadz Hasan',
                'no_hp_wali' => '081234567804',
                'warna_label' => '#2196f3',
                'urutan' => 4,
            ],
            [
                'nama_kelas' => 'JILID 5',
                'nominal_spp' => 75000,
                'wali_kelas' => 'Ustadz Ali',
                'no_hp_wali' => '081234567805',
                'warna_label' => '#4caf50',
                'urutan' => 5,
            ],
            [
                'nama_kelas' => 'JILID 6',
                'nominal_spp' => 75000,
                'wali_kelas' => 'Ustadz Ibrahim',
                'no_hp_wali' => '081234567806',
                'warna_label' => '#9c27b0',
                'urutan' => 6,
            ],
        ];

        $kelasModels = [];
        foreach ($kelasList as $data) {
            $kelasModels[$data['nama_kelas']] = Kelas::updateOrCreate(
                ['nama_kelas' => $data['nama_kelas']],
                $data
            );
        }

        // 2. Seed Users (Admin, Kepala, Sekretaris, Bendahara, Guru)
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'email' => 'admin@sekolah.id',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'id_kelas' => null,
            ]
        );

        User::updateOrCreate(
            ['username' => 'kepala'],
            [
                'name' => 'K.H. Hasan Basri (Kepala TPQ)',
                'email' => 'kepala@sekolah.id',
                'password' => Hash::make('kepala123'),
                'role' => 'kepala',
                'id_kelas' => null,
            ]
        );

        User::updateOrCreate(
            ['username' => 'sekretaris'],
            [
                'name' => 'Ustadzah Fatimah (Sekretaris)',
                'email' => 'sekretaris@sekolah.id',
                'password' => Hash::make('sekretaris123'),
                'role' => 'sekretaris',
                'id_kelas' => $kelasModels['JILID 1']->id,
            ]
        );

        User::updateOrCreate(
            ['username' => 'bendahara'],
            [
                'name' => 'Ustadzah Aisyah (Bendahara)',
                'email' => 'bendahara@sekolah.id',
                'password' => Hash::make('bendahara123'),
                'role' => 'bendahara',
                'id_kelas' => $kelasModels['JILID 2']->id,
            ]
        );

        User::updateOrCreate(
            ['username' => 'guru'],
            [
                'name' => 'Ustadz Abdullah (Pengajar)',
                'email' => 'guru@sekolah.id',
                'password' => Hash::make('guru123'),
                'role' => 'guru',
                'id_kelas' => $kelasModels['JILID 3']->id,
            ]
        );

        // 3. Seed Siswa / Santri Contoh
        $siswaData = [
            [
                'nisn' => '1001',
                'nama_lengkap' => 'Ahmad Fauzi',
                'jenis_kelamin' => 'L',
                'id_kelas' => $kelasModels['JILID 1']->id,
                'no_hp_ortu' => '081234567890',
                'nama_ortu' => 'Bapak Fauzi',
                'alamat' => 'Jl. Mawar No. 12',
                'status' => 'aktif',
            ],
            [
                'nisn' => '1002',
                'nama_lengkap' => 'Muhammad Bilal',
                'jenis_kelamin' => 'L',
                'id_kelas' => $kelasModels['JILID 1']->id,
                'no_hp_ortu' => '081234567891',
                'nama_ortu' => 'Bapak Bilal',
                'alamat' => 'Jl. Melati No. 5',
                'status' => 'aktif',
            ],
            [
                'nisn' => '1003',
                'nama_lengkap' => 'Zahra Amelia',
                'jenis_kelamin' => 'P',
                'id_kelas' => $kelasModels['JILID 1']->id,
                'no_hp_ortu' => '081234567892',
                'nama_ortu' => 'Ibu Amelia',
                'alamat' => 'Jl. Kenanga No. 8',
                'status' => 'aktif',
            ],
            [
                'nisn' => '2001',
                'nama_lengkap' => 'Farhan Al-Farisi',
                'jenis_kelamin' => 'L',
                'id_kelas' => $kelasModels['JILID 2']->id,
                'no_hp_ortu' => '081234567893',
                'nama_ortu' => 'Bapak Salman',
                'alamat' => 'Jl. Dahlia No. 15',
                'status' => 'aktif',
            ],
            [
                'nisn' => '2002',
                'nama_lengkap' => 'Siti Khadijah',
                'jenis_kelamin' => 'P',
                'id_kelas' => $kelasModels['JILID 2']->id,
                'no_hp_ortu' => '081234567894',
                'nama_ortu' => 'Bapak Khuwailid',
                'alamat' => 'Jl. Cempaka No. 3',
                'status' => 'aktif',
            ],
            [
                'nisn' => '3001',
                'nama_lengkap' => 'Hamzah Asadullah',
                'jenis_kelamin' => 'L',
                'id_kelas' => $kelasModels['JILID 3']->id,
                'no_hp_ortu' => '081234567895',
                'nama_ortu' => 'Bapak Abdul Muthalib',
                'alamat' => 'Jl. Anggrek No. 20',
                'status' => 'aktif',
            ],
        ];

        foreach ($siswaData as $santri) {
            $siswaModel = Siswa::updateOrCreate(['nisn' => $santri['nisn']], $santri);

            // Buat akun login untuk siswa (password default = nisn)
            User::updateOrCreate(
                ['username' => $santri['nisn']],
                [
                    'name' => $santri['nama_lengkap'],
                    'email' => $santri['nisn'].'@santri.sekolah.id',
                    'password' => Hash::make($santri['nisn']),
                    'role' => 'siswa',
                    'id_kelas' => $santri['id_kelas'],
                    'nisn' => $santri['nisn'],
                ]
            );
        }

        // 4. Seed Pengaturan Default
        $settings = [
            'nama_instansi' => 'Sistem Terpadu Absensi & SPP Santri',
            'jam_masuk' => '07:00',
            'jam_terlambat' => '07:15',
            'jam_pulang' => '12:00',
            'hari_aktif' => 'Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'tahun_ajaran_aktif' => '2026/2027',
            'fonnte_token' => '',
            'telegram_bot_token' => '',
            'telegram_chat_id' => '',
        ];

        foreach ($settings as $key => $val) {
            Pengaturan::setValue($key, $val);
        }
    }
}
