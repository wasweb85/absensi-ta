<template>
  <div class="space-y-6 print:space-y-0">
    <!-- KOP RESMI LAPORAN (Hanya tampil saat dicetak) -->
    <div class="hidden print:block mb-1.5 text-black">
      <div class="flex items-center justify-between gap-3 pb-1">
        <!-- Logo Lembaga di Kiri -->
        <div class="w-24 shrink-0 flex items-center justify-center">
          <img
            :src="appLogo || '/LOGOTPQ.png'"
            alt="Logo Lembaga"
            class="max-h-20 max-w-24 object-contain"
          />
        </div>

        <!-- Teks Kop Surat di Tengah -->
        <div class="flex-1 text-center px-1">
          <h1 class="text-2xl sm:text-[26px] font-serif font-bold text-[#7b95c0] tracking-[0.16em] uppercase leading-none">
            YAYASAN NURUL JADID
          </h1>
          <h2 class="text-[15px] sm:text-base font-serif font-bold text-[#52525b] tracking-[0.06em] uppercase leading-tight mt-1">
            TAMAN PENDIDIKAN AL-QUR'AN NURUL MUN'IM
          </h2>
          <h3 class="text-[13px] sm:text-sm font-serif font-bold text-[#52525b] tracking-[0.1em] uppercase leading-tight mt-0.5">
            KARANGANYAR PAITON PROBOLINGGO
          </h3>
          <p class="text-[10px] sm:text-[11px] font-serif italic text-[#cca843] leading-tight mt-1">
            Alamat: PP. Nurul Jadid PO. Box 1 Paiton Probolinggo 67291 Email: tpqnm.nurja@gmail.com
          </p>
        </div>

        <!-- Spacer Kanan Penyeimbang -->
        <div class="w-24 shrink-0"></div>
      </div>

      <!-- Garis Ganda Kop Surat Resmi -->
      <div class="border-t-[2.5px] border-black mt-1"></div>
      <div class="border-t border-black mt-[1.5px] mb-1.5"></div>

      <!-- Judul Dokumen & Periode Laporan -->
      <div class="text-center my-1">
        <h2 class="text-[11px] font-bold uppercase tracking-wider underline">
          LAPORAN REKAPITULASI PRESENSI SANTRI
        </h2>
        <div class="flex justify-between items-center text-[9px] mt-1 px-0.5 font-medium">
          <div>
            <span><strong>Periode:</strong> {{ bulanList[selectedBulan] }} {{ selectedTahun }}</span>
            <span class="mx-2">&bull;</span>
            <span><strong>Jilid:</strong> {{ currentKelasLabel }}</span>
          </div>
          <div>
            <span><strong>Tahun Ajaran:</strong> {{ appTahunAjaran || '2026/2027' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Kontainer Tunggal Terpadu (Toolbar & Tabel Matrix Menyatu Sesuai Model Data Santri) -->
    <Card class="overflow-hidden py-0 gap-0 print:border-none print:shadow-none print:bg-transparent print:p-0 print:m-0">
      <!-- Toolbar Atas di Dalam Kontainer Card (Sembunyi saat cetak) -->
      <div class="p-3 sm:p-4 border-b border-border bg-card space-y-2.5 sm:space-y-3 print:hidden">
        <!-- Baris 1: Judul Halaman [Kiri] sejajar dengan Tombol Aksi Cetak & Export [Kanan] -->
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-1.5 sm:gap-2">
            <h2 class="text-sm sm:text-base font-semibold tracking-tight text-foreground whitespace-nowrap">
              Rekap Presensi
            </h2>
            <Badge variant="outline" class="font-normal text-[11px] sm:text-xs text-muted-foreground px-2 py-0.5 whitespace-nowrap">
              {{ bulanList[selectedBulan] }} {{ selectedTahun }}
            </Badge>
          </div>

          <!-- Tombol Aksi (Cetak & Export CSV) -->
          <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
            <Button
              variant="outline"
              size="sm"
              class="h-8 sm:h-9 px-2.5 sm:px-3 gap-1.5 text-xs"
              title="Cetak Laporan Rekap"
              @click="printMatrix"
            >
              <Printer class="size-3.5" />
              <span>Cetak</span>
            </Button>
            <Button
              size="sm"
              class="h-8 sm:h-9 px-2.5 sm:px-3 gap-1.5 text-xs"
              title="Export CSV"
              @click="exportCsv"
            >
              <Download class="size-3.5" />
              <span class="hidden sm:inline">Export CSV</span>
              <span class="sm:hidden">CSV</span>
            </Button>
          </div>
        </div>

        <!-- Baris Filter & Pencarian:
             - Di Mobile:
               * Baris atas: Bulan sejajar dengan Tahun (grid-cols-2)
               * Baris bawah: Jilid & Gender (atau Gender sejajar Pencarian jika guru)
             - Di Desktop (md:): Satu baris horizontal rapi dan proporsional
        -->
        <div class="flex flex-col md:flex-row md:items-center gap-2 sm:gap-2.5">
          <!-- Grup 1: Bulan & Tahun (Mobile: Sejajar 50:50, Desktop: natural) -->
          <div class="grid grid-cols-2 md:flex md:items-center gap-2 sm:gap-2.5 shrink-0">
            <!-- Bulan Selector -->
            <select
              id="selectBulan"
              v-model="selectedBulan"
              class="h-8 sm:h-9 w-full md:w-36 rounded-lg border border-input bg-background px-2.5 sm:px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
              title="Pilih Bulan"
              @change="loadMatrix"
            >
              <option v-for="(nama, num) in bulanList" :key="num" :value="num">
                Bulan: {{ nama }}
              </option>
            </select>

            <!-- Tahun Selector -->
            <select
              id="selectTahun"
              v-model="selectedTahun"
              class="h-8 sm:h-9 w-full md:w-28 rounded-lg border border-input bg-background px-2.5 sm:px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
              title="Pilih Tahun"
              @change="loadMatrix"
            >
              <option v-for="y in [2024, 2025, 2026, 2027, 2028]" :key="y" :value="y">
                Tahun {{ y }}
              </option>
            </select>
          </div>

          <!-- Grup 2: Jilid & Gender (Jika admin) -->
          <div v-if="canFilterAllKelas" class="grid grid-cols-2 md:flex md:items-center gap-2 sm:gap-2.5 shrink-0">
            <!-- Filter Jilid -->
            <select
              id="selectKelas"
              v-model="selectedKelas"
              class="h-8 sm:h-9 w-full md:w-36 lg:w-40 rounded-lg border border-input bg-background px-2.5 sm:px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
              title="Pilih Jilid"
              @change="loadMatrix"
            >
              <option value="">Semua Jilid</option>
              <option v-for="k in kelasList" :key="k.id" :value="k.id">
                {{ getCleanNamaKelas(k.nama_kelas) }}
              </option>
            </select>

            <!-- Filter Gender (Admin) -->
            <select
              id="selectGenderAdmin"
              v-model="selectedGender"
              class="h-8 sm:h-9 w-full md:w-32 rounded-lg border border-input bg-background px-2.5 sm:px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
              title="Pilih Kelompok Gender"
              @change="loadMatrix"
            >
              <option value="">Semua Gender</option>
              <option value="L">Putra (L)</option>
              <option value="P">Putri (P)</option>
            </select>
          </div>

          <!-- Jika Guru: Gender sejajar Pencarian (Pencarian lebih lebar dari gender) -->
          <div v-else class="flex items-center gap-2 sm:gap-2.5 flex-1 min-w-0">
            <select
              id="selectGenderGuru"
              v-model="selectedGender"
              class="h-8 sm:h-9 w-[115px] sm:w-[130px] md:w-32 shrink-0 rounded-lg border border-input bg-background px-2 sm:px-2.5 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
              title="Pilih Kelompok Gender"
              @change="loadMatrix"
            >
              <option value="">Semua Gender</option>
              <option value="L">Putra (L)</option>
              <option value="P">Putri (P)</option>
            </select>

            <div class="relative flex-1 min-w-0 md:max-w-xs lg:max-w-sm">
              <Search class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
              <Input
                id="searchSantriGuru"
                v-model="searchQuery"
                type="text"
                placeholder="Cari santri..."
                class="h-8 sm:h-9 pl-9 text-xs bg-background w-full"
              />
            </div>
          </div>

          <!-- Kolom Pencarian Tambahan untuk Admin (Di desktop flex-1, di mobile baris penuh) -->
          <div v-if="canFilterAllKelas" class="relative flex-1 min-w-0 md:max-w-xs lg:max-w-sm">
            <Search class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
            <Input
              id="searchSantriAdmin"
              v-model="searchQuery"
              type="text"
              placeholder="Cari santri..."
              class="h-8 sm:h-9 pl-9 text-xs bg-background w-full"
            />
          </div>
        </div>
      </div>

      <!-- Matrix Table Container -->
      <div class="overflow-x-auto print:overflow-visible">
        <Table class="text-center text-[11px] print:text-[8.5px] print:w-full print:border-collapse">
          <TableHeader>
            <TableRow class="bg-muted/40 font-semibold print:bg-neutral-100 print:text-black">
              <TableHead class="w-10 text-center sticky left-0 bg-muted/90 backdrop-blur-xs z-10 text-[10px] print:static print:bg-neutral-100 print:text-black print:w-5 print:py-0.5 print:px-1 print:text-[8px]">No</TableHead>
              <TableHead class="text-left sticky left-10 bg-muted/90 backdrop-blur-xs z-10 min-w-[160px] text-[10px] print:static print:bg-neutral-100 print:text-black print:min-w-0 print:py-0.5 print:px-1 print:text-[8.5px]">Santri</TableHead>
              <TableHead class="text-center text-[10px] print:text-[8px] print:py-0.5 print:px-1 print:text-black">Jilid</TableHead>
              
              <!-- Days of Month -->
              <TableHead
                v-for="day in daysInMonth"
                :key="day"
                class="px-1 text-center min-w-[22px] border-l border-border/60 text-[10px] print:min-w-0 print:px-0.5 print:text-[8px] print:text-black"
              >
                {{ day }}
              </TableHead>
              
              <!-- Summary Totals -->
              <TableHead class="px-1.5 text-center bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-l font-bold text-[10px] print:bg-neutral-100 print:text-black print:px-1 print:text-[8px]">H</TableHead>
              <TableHead class="px-1.5 text-center bg-amber-500/10 text-amber-700 dark:text-amber-400 font-bold text-[10px] print:bg-neutral-100 print:text-black print:px-1 print:text-[8px]">T</TableHead>
              <TableHead class="px-1.5 text-center bg-blue-500/10 text-blue-700 dark:text-blue-400 font-bold text-[10px] print:bg-neutral-100 print:text-black print:px-1 print:text-[8px]">S</TableHead>
              <TableHead class="px-1.5 text-center bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-bold text-[10px] print:bg-neutral-100 print:text-black print:px-1 print:text-[8px]">I</TableHead>
              <TableHead class="px-1.5 text-center bg-rose-500/10 text-rose-700 dark:text-rose-400 font-bold text-[10px] print:bg-neutral-100 print:text-black print:px-1 print:text-[8px]">A</TableHead>
              <TableHead class="px-2 text-center bg-muted/60 font-bold text-[10px] print:bg-neutral-100 print:text-black print:px-1 print:text-[8px]">%</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody class="font-mono print:text-black">
            <TableRow v-if="isLoading">
              <TableCell :colspan="daysInMonth + 9" class="h-36 text-center text-muted-foreground font-sans text-xs">
                <div class="flex flex-col items-center justify-center gap-2">
                  <LoaderCircle class="size-6 animate-spin text-primary" />
                  <span>Memuat data rekapitulasi presensi...</span>
                </div>
              </TableCell>
            </TableRow>

            <TableRow v-else-if="filteredMatrixData.length === 0">
              <TableCell :colspan="daysInMonth + 9" class="h-32 text-center text-muted-foreground font-sans text-xs">
                Tidak ada data rekap untuk periode ini.
              </TableCell>
            </TableRow>

            <TableRow
              v-else
              v-for="(row, idx) in filteredMatrixData"
              :key="row.id_siswa"
              class="transition-colors hover:bg-muted/40 print:hover:bg-transparent"
            >
              <TableCell class="text-center sticky left-0 bg-card z-10 text-muted-foreground font-sans text-xs print:static print:bg-transparent print:text-black print:p-0.5 print:text-[8px]">{{ idx + 1 }}</TableCell>
              <TableCell class="text-left sticky left-10 bg-card z-10 font-semibold font-sans text-foreground truncate max-w-[170px] print:static print:bg-transparent print:text-black print:max-w-none print:p-0.5 print:text-[8.5px]">
                {{ row.nama_lengkap }}
              </TableCell>
              <TableCell class="font-sans text-muted-foreground text-[10px] print:text-black print:p-0.5 print:text-[8px]">{{ row.nama_kelas || '-' }}</TableCell>

              <!-- Daily 1..31 Status -->
              <TableCell
                v-for="d in daysInMonth"
                :key="d"
                class="px-1 border-l border-border/40 text-[10px] print:p-0.5 print:text-[8px]"
                :class="{
                  'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-bold print:bg-transparent print:text-black': row.daily[d] === 'H',
                  'bg-amber-500/15 text-amber-700 dark:text-amber-400 font-bold print:bg-transparent print:text-black': row.daily[d] === 'T',
                  'bg-blue-500/15 text-blue-700 dark:text-blue-400 font-bold print:bg-transparent print:text-black': row.daily[d] === 'S',
                  'bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 font-bold print:bg-transparent print:text-black': row.daily[d] === 'I',
                  'bg-rose-500/15 text-rose-700 dark:text-rose-400 font-bold print:bg-transparent print:text-black': row.daily[d] === 'A',
                  'text-muted-foreground/30 print:text-neutral-300': row.daily[d] === '-' || !row.daily[d]
                }"
              >
                {{ row.daily[d] || '-' }}
              </TableCell>

              <!-- Summary Columns -->
              <TableCell class="font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-500/5 border-l print:text-black print:bg-transparent print:p-0.5 print:text-[8px]">{{ row.summary.hadir }}</TableCell>
              <TableCell class="font-bold text-amber-700 dark:text-amber-400 bg-amber-500/5 print:text-black print:bg-transparent print:p-0.5 print:text-[8px]">{{ row.summary.terlambat }}</TableCell>
              <TableCell class="font-bold text-blue-700 dark:text-blue-400 bg-blue-500/5 print:text-black print:bg-transparent print:p-0.5 print:text-[8px]">{{ row.summary.sakit }}</TableCell>
              <TableCell class="font-bold text-indigo-700 dark:text-indigo-400 bg-indigo-500/5 print:text-black print:bg-transparent print:p-0.5 print:text-[8px]">{{ row.summary.izin }}</TableCell>
              <TableCell class="font-bold text-rose-700 dark:text-rose-400 bg-rose-500/5 print:text-black print:bg-transparent print:p-0.5 print:text-[8px]">{{ row.summary.alpa }}</TableCell>
              <TableCell class="font-bold text-foreground bg-muted/30 print:text-black print:bg-transparent print:p-0.5 print:text-[8px]">{{ row.summary.persentase }}%</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </Card>

    <!-- Legend Indicator Cetak (Mepet ke tabel) -->
    <div class="hidden print:flex items-center gap-3 text-[7.5px] text-black mt-0.5 pt-0.5">
      <span class="font-bold uppercase tracking-wider">Keterangan:</span>
      <span><strong>H:</strong> Hadir</span>
      <span><strong>T:</strong> Terlambat</span>
      <span><strong>S:</strong> Sakit</span>
      <span><strong>I:</strong> Izin</span>
      <span><strong>A:</strong> Alpa</span>
    </div>

    <!-- Blok Pengesahan Tanda Tangan Resmi (Cetak - Muat dalam 1 Lembar A4) -->
    <div class="hidden print:flex justify-between items-start mt-2 pt-0.5 text-black text-[9px] break-inside-avoid">
      <!-- Sisi Kiri: Mengetahui Kepala TPQ -->
      <div class="text-center min-w-[170px]">
        <p class="font-normal">Mengetahui,</p>
        <p class="font-semibold">Kepala TPQ</p>
        <div class="h-10"></div>
        <p class="font-bold underline tracking-wide">( Ny. Shofiyah )</p>
        <p class="text-[8px] text-neutral-600 mt-0.5">Kepala TPQ Nurul Mun'im</p>
      </div>

      <!-- Sisi Kanan: Wali Jilid (satu wali campur, atau wali putra & putri) -->
      <div class="flex items-start gap-10">
        <div
          v-for="(wali, idx) in waliSignatories"
          :key="idx"
          class="text-center min-w-[170px]"
        >
          <p class="font-normal" :class="{ invisible: idx < waliSignatories.length - 1 }">{{ formattedCurrentDate }}</p>
          <p class="font-semibold">{{ wali.jabatan }}</p>
          <div class="h-10"></div>
          <p class="font-bold underline tracking-wide">( {{ wali.nama }} )</p>
          <p class="text-[8px] text-neutral-600 mt-0.5">{{ wali.keterangan }}</p>
        </div>
      </div>
    </div>

    <!-- Legend Indicator -->
    <Card class="print:hidden">
      <CardContent class="p-4 flex flex-wrap items-center gap-4 text-xs">
        <span class="text-muted-foreground font-semibold text-[10px] uppercase tracking-wider">Keterangan:</span>
        <div class="flex items-center gap-1.5">
          <Badge class="size-5 p-0 flex items-center justify-center font-bold text-[10px]">H</Badge>
          <span>Hadir</span>
        </div>
        <div class="flex items-center gap-1.5">
          <Badge variant="secondary" class="size-5 p-0 flex items-center justify-center font-bold text-[10px] text-amber-700 dark:text-amber-400 bg-amber-500/15 border-amber-500/20">T</Badge>
          <span>Terlambat</span>
        </div>
        <div class="flex items-center gap-1.5">
          <Badge variant="outline" class="size-5 p-0 flex items-center justify-center font-bold text-[10px] text-blue-700 dark:text-blue-400 bg-blue-500/15 border-blue-500/20">S</Badge>
          <span>Sakit</span>
        </div>
        <div class="flex items-center gap-1.5">
          <Badge variant="outline" class="size-5 p-0 flex items-center justify-center font-bold text-[10px] text-indigo-700 dark:text-indigo-400 bg-indigo-500/15 border-indigo-500/20">I</Badge>
          <span>Izin</span>
        </div>
        <div class="flex items-center gap-1.5">
          <Badge variant="destructive" class="size-5 p-0 flex items-center justify-center font-bold text-[10px]">A</Badge>
          <span>Alpa</span>
        </div>
      </CardContent>
    </Card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import {
  Printer,
  Download,
  LoaderCircle,
  Search,
} from '@lucide/vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import api from '@/services/api';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';
import { useAppConfig } from '@/composables/useAppConfig';

const toast = useToast();
const { user, role, fetchUser } = useAuth();
const { appName, appLogo, appAddress, appContact, appTahunAjaran } = useAppConfig();

const canFilterAllKelas = computed(() => ['admin', 'kepala', 'sekretaris'].includes(role.value));

const isLoading = ref(true);
const selectedBulan = ref(new Date().getMonth() + 1);
const selectedTahun = ref(new Date().getFullYear());
const selectedKelas = ref(user.value?.id_kelas || '');
const selectedGender = ref('');
const waliInfo = ref(null);
const kelasList = ref([]);
const matrixData = ref([]);
const daysInMonth = ref(31);
const searchQuery = ref('');

const filteredMatrixData = computed(() => {
  if (!searchQuery.value.trim()) return matrixData.value;
  const q = searchQuery.value.toLowerCase().trim();
  return matrixData.value.filter((row) => {
    const nama = (row.nama_lengkap || '').toLowerCase();
    const nisn = (row.nisn || '').toLowerCase();
    const kelas = (row.nama_kelas || '').toLowerCase();
    return nama.includes(q) || nisn.includes(q) || kelas.includes(q);
  });
});

function getCleanNamaKelas(name) {
  return (name || '').replace(/\s*\([^)]+\)\s*$/, '').trim();
}

const currentKelasLabel = computed(() => {
  let label = 'Semua Jilid';
  if (canFilterAllKelas.value) {
    if (selectedKelas.value) {
      const found = kelasList.value.find(k => String(k.id) === String(selectedKelas.value));
      label = getCleanNamaKelas(found?.nama_kelas || waliInfo.value?.nama_kelas || 'Jilid');
    }
  } else {
    label = getCleanNamaKelas(waliInfo.value?.nama_kelas || user.value?.nama_kelas || 'Jilid');
  }

  if (selectedGender.value === 'L') {
    return `${label} (PUTRA)`;
  }
  if (selectedGender.value === 'P') {
    return `${label} (PUTRI)`;
  }
  return label;
});

const EMPTY_SIGNATURE = '............................................';

/**
 * Daftar penandatangan wali jilid. Diambil hanya dari akun guru (bukan santri) pada jilid terpilih.
 * - Filter Putra/Putri  -> wali sesuai gender (fallback ke wali campur)
 * - Semua gender        -> wali campur, atau wali putra & putri berdampingan jika jilid dipisah
 */
const waliSignatories = computed(() => {
  const info = waliInfo.value || {};
  const kelasName = currentKelasLabel.value;

  if (selectedGender.value === 'L') {
    return [{ jabatan: 'Wali Jilid Putra', nama: info.wali_putra || info.wali_umum || EMPTY_SIGNATURE, keterangan: `Wali ${kelasName}` }];
  }
  if (selectedGender.value === 'P') {
    return [{ jabatan: 'Wali Jilid Putri', nama: info.wali_putri || info.wali_umum || EMPTY_SIGNATURE, keterangan: `Wali ${kelasName}` }];
  }

  if (info.wali_umum) {
    return [{ jabatan: 'Wali Jilid', nama: info.wali_umum, keterangan: `Wali ${kelasName}` }];
  }

  if (info.wali_putra || info.wali_putri) {
    return [
      { jabatan: 'Wali Jilid Putra', nama: info.wali_putra || EMPTY_SIGNATURE, keterangan: `Wali ${kelasName} (Putra)` },
      { jabatan: 'Wali Jilid Putri', nama: info.wali_putri || EMPTY_SIGNATURE, keterangan: `Wali ${kelasName} (Putri)` },
    ];
  }

  return [{ jabatan: 'Wali Jilid', nama: EMPTY_SIGNATURE, keterangan: `Wali ${kelasName}` }];
});

const formattedCurrentDate = computed(() => {
  const today = new Date();
  return today.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
});

const bulanList = {
  1: 'Januari', 2: 'Februari', 3: 'Maret', 4: 'April',
  5: 'Mei', 6: 'Juni', 7: 'Juli', 8: 'Agustus',
  9: 'September', 10: 'Oktober', 11: 'November', 12: 'Desember'
};

const loadKelas = async () => {
  try {
    const cached = sessionStorage.getItem('cached_kelas_list');
    if (cached) {
      kelasList.value = JSON.parse(cached);
    }
    const res = await api.get('/kelas');
    if (res.data.success) {
      kelasList.value = res.data.data;
      sessionStorage.setItem('cached_kelas_list', JSON.stringify(res.data.data));
    }
  } catch (e) {}
};

const loadMatrix = async () => {
  isLoading.value = true;
  try {
    const params = {
      bulan: selectedBulan.value,
      tahun: selectedTahun.value,
      jenis_kelamin: selectedGender.value || '',
    };

    if (canFilterAllKelas.value) {
      if (selectedKelas.value) {
        params.id_kelas = selectedKelas.value;
      }
    } else {
      if (user.value?.id_kelas) {
        params.id_kelas = user.value.id_kelas;
      }
    }

    const res = await api.get('/absensi/matrix', { params });
    if (res.data.success) {
      matrixData.value = res.data.data.data;
      daysInMonth.value = res.data.data.days_in_month;
      waliInfo.value = res.data.data.wali_info || null;
    }
  } catch (e) {
    toast.error('Gagal memuat rekap matrix absensi.');
  } finally {
    isLoading.value = false;
  }
};

const printMatrix = () => {
  window.print();
};

const exportCsv = () => {
  if (matrixData.value.length === 0) {
    toast.warning('Tidak ada data untuk diekspor.');
    return;
  }

  const rows = [];
  // Kop Surat & Metadata Lembaga
  rows.push(['YAYASAN NURUL JADID']);
  rows.push(["TAMAN PENDIDIKAN AL-QUR'AN NURUL MUN'IM"]);
  rows.push(['KARANGANYAR PAITON PROBOLINGGO']);
  rows.push(['Alamat: PP. Nurul Jadid PO. Box 1 Paiton Probolinggo 67291 Email: tpqnm.nurja@gmail.com']);
  rows.push([]);
  rows.push(['LAPORAN REKAPITULASI PRESENSI SANTRI']);
  rows.push([`Periode: ${bulanList[selectedBulan.value]} ${selectedTahun.value}`]);
  rows.push([`Jilid: ${currentKelasLabel.value}`]);
  rows.push([`Tahun Ajaran: ${appTahunAjaran.value || '2026/2027'}`]);
  rows.push([`Tanggal Unduh: ${formattedCurrentDate.value}`]);
  rows.push([]);

  // Table Headers
  const header = ['No', 'NISN', 'Nama Santri', 'Jilid'];
  for (let d = 1; d <= daysInMonth.value; d++) {
    header.push(`Tgl ${d}`);
  }
  header.push('Hadir (H)', 'Terlambat (T)', 'Sakit (S)', 'Izin (I)', 'Alpa (A)', 'Persentase (%)');
  rows.push(header);

  let totalH = 0;
  let totalT = 0;
  let totalS = 0;
  let totalI = 0;
  let totalA = 0;
  let totalPersen = 0;

  // Data Rows
  matrixData.value.forEach((row, idx) => {
    const r = [
      idx + 1,
      `'${row.nisn}`,
      row.nama_lengkap,
      row.nama_kelas || '-',
    ];
    for (let d = 1; d <= daysInMonth.value; d++) {
      r.push(row.daily[d] || '-');
    }
    r.push(
      row.summary.hadir,
      row.summary.terlambat,
      row.summary.sakit,
      row.summary.izin,
      row.summary.alpa,
      `${row.summary.persentase}%`
    );
    rows.push(r);

    totalH += Number(row.summary.hadir) || 0;
    totalT += Number(row.summary.terlambat) || 0;
    totalS += Number(row.summary.sakit) || 0;
    totalI += Number(row.summary.izin) || 0;
    totalA += Number(row.summary.alpa) || 0;
    totalPersen += Number(row.summary.persentase) || 0;
  });

  // Total & Rata-rata
  const avgPersen = matrixData.value.length > 0 ? (totalPersen / matrixData.value.length).toFixed(1) : 0;
  const summaryRow = ['TOTAL / RATA-RATA', '', '', ''];
  for (let d = 1; d <= daysInMonth.value; d++) {
    summaryRow.push('');
  }
  summaryRow.push(totalH, totalT, totalS, totalI, totalA, `${avgPersen}%`);
  rows.push(summaryRow);

  rows.push([]);
  rows.push(['KETERANGAN STATUS:']);
  rows.push(['H = Hadir', 'T = Terlambat', 'S = Sakit', 'I = Izin', 'A = Alpa']);
  rows.push([]);
  const walis = waliSignatories.value;
  const waliCols = (pick) => walis.flatMap((w) => [pick(w), '']);
  rows.push(['Mengetahui,', '', '', ...walis.flatMap((w, i) => [i === walis.length - 1 ? formattedCurrentDate.value : '', ''])]);
  rows.push(['Kepala TPQ', '', '', ...waliCols((w) => w.jabatan)]);
  rows.push([]);
  rows.push([]);
  rows.push(['( Ny. Shofiyah )', '', '', ...waliCols((w) => `( ${w.nama} )`)]);
  rows.push(["Kepala TPQ Nurul Mun'im", '', '', ...waliCols((w) => w.keterangan)]);

  // Convert to CSV string with escaping
  const csvContent = rows
    .map(row => row.map(cell => {
      const str = String(cell ?? '');
      if (str.includes(',') || str.includes('"') || str.includes('\n')) {
        return `"${str.replace(/"/g, '""')}"`;
      }
      return `"${str}"`;
    }).join(','))
    .join('\r\n');

  // Add BOM \uFEFF for proper UTF-8 Excel handling
  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  const genderSuffix = selectedGender.value === 'L' ? '_Putra' : (selectedGender.value === 'P' ? '_Putri' : '');
  const baseKelas = !canFilterAllKelas.value && user.value?.nama_kelas
    ? user.value.nama_kelas.replace(/\s+/g, '_')
    : (selectedKelas.value ? (kelasList.value.find(k => String(k.id) === String(selectedKelas.value))?.nama_kelas || 'Santri').replace(/\s+/g, '_') : 'Santri');
  link.setAttribute('download', `Rekap_Presensi_${baseKelas}${genderSuffix}_${selectedBulan.value}_${selectedTahun.value}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  toast.success('File CSV berhasil diunduh!');
};

onMounted(async () => {
  if (!canFilterAllKelas.value) {
    selectedKelas.value = user.value?.id_kelas || '';
  }
  if (role.value === 'guru') {
    // Ambil profil terbaru agar perubahan kelompok binaan oleh admin langsung berlaku
    await fetchUser();
    const binaan = user.value?.gender_binaan;
    selectedGender.value = ['L', 'P'].includes(binaan) ? binaan : '';
  }
  await Promise.all([
    canFilterAllKelas.value ? loadKelas() : Promise.resolve(),
    loadMatrix(),
  ]);
});
</script>

<style scoped>
@media print {
  @page {
    size: landscape;
    margin: 6mm 5mm 6mm 5mm;
  }

  body {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  :deep(table) {
    border-collapse: collapse !important;
    width: 100% !important;
  }

  :deep(th),
  :deep(td) {
    border: 1px solid #111827 !important;
    color: #111827 !important;
    line-height: 1.15 !important;
  }

  .break-inside-avoid {
    break-inside: avoid !important;
    page-break-inside: avoid !important;
  }
}
</style>
