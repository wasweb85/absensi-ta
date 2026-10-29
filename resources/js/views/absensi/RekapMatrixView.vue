<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between pb-2 border-b print:hidden">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-lg font-semibold tracking-tight text-foreground">
            Rekap
          </h2>
          <Badge variant="outline" class="font-normal text-xs text-muted-foreground">
            {{ bulanList[selectedBulan] }} {{ selectedTahun }}
          </Badge>
        </div>
        <p class="text-xs text-muted-foreground mt-0.5">
          Matriks presensi santri harian dari tanggal 1 sampai 31 dengan ringkasan persentase kehadiran bulanan.
        </p>
      </div>

      <!-- Action Buttons (Cetak & Export CSV) -->
      <div class="flex items-center gap-2 shrink-0">
        <Button
          variant="outline"
          size="sm"
          class="h-8 gap-1.5 text-xs"
          @click="printMatrix"
        >
          <Printer class="size-3.5" />
          <span>Cetak</span>
        </Button>
        <Button
          size="sm"
          class="h-8 gap-1.5 text-xs"
          @click="exportCsv"
        >
          <Download class="size-3.5" />
          <span>Export CSV</span>
        </Button>
      </div>
    </div>

    <!-- Filter Card -->
    <Card class="print:hidden">
      <CardContent class="p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:flex md:items-end gap-3 flex-1">
          <!-- Bulan Selector -->
          <div class="w-full md:w-44 space-y-1.5">
            <Label for="selectBulan" class="text-xs font-medium text-muted-foreground">Bulan</Label>
            <select
              id="selectBulan"
              v-model="selectedBulan"
              class="h-9 w-full rounded-lg border border-input bg-transparent px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              @change="loadMatrix"
            >
              <option v-for="(nama, num) in bulanList" :key="num" :value="num">
                {{ nama }}
              </option>
            </select>
          </div>

          <!-- Tahun Selector -->
          <div class="w-full md:w-32 space-y-1.5">
            <Label for="selectTahun" class="text-xs font-medium text-muted-foreground">Tahun</Label>
            <select
              id="selectTahun"
              v-model="selectedTahun"
              class="h-9 w-full rounded-lg border border-input bg-transparent px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              @change="loadMatrix"
            >
              <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>

          <!-- Filter Kelas (Hanya untuk Admin & Sekretaris) -->
          <div v-if="canFilterAllKelas" class="w-full md:w-60 space-y-1.5">
            <Label for="selectKelas" class="text-xs font-medium text-muted-foreground">Filter Jilid / Kelas</Label>
            <select
              id="selectKelas"
              v-model="selectedKelas"
              class="h-9 w-full rounded-lg border border-input bg-transparent px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              @change="loadMatrix"
            >
              <option value="">Semua Jilid / Kelas</option>
              <option v-for="k in kelasList" :key="k.id" :value="k.id">
                {{ k.nama_kelas }} ({{ k.wali_kelas || 'Wali -' }})
              </option>
            </select>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Printable Header Banner (Only visible during print) -->
    <div class="hidden print:block text-center border-b pb-4 mb-4">
      <h1 class="text-lg font-bold">REKAPITULASI PRESENSI SANTRI</h1>
      <p class="text-xs">
        Periode: {{ bulanList[selectedBulan] }} {{ selectedTahun }}
        <span v-if="!canFilterAllKelas && user?.nama_kelas"> - Jilid {{ user.nama_kelas }}</span>
      </p>
    </div>

    <!-- Matrix Table Container -->
    <Card class="overflow-hidden">
      <div class="overflow-x-auto">
        <Table class="text-center text-[11px]">
          <TableHeader>
            <TableRow class="bg-muted/40 font-semibold">
              <TableHead class="w-10 text-center sticky left-0 bg-muted/90 backdrop-blur-xs z-10 text-[10px]">No</TableHead>
              <TableHead class="text-left sticky left-10 bg-muted/90 backdrop-blur-xs z-10 min-w-[160px] text-[10px]">Santri</TableHead>
              <TableHead class="text-center text-[10px]">Jilid</TableHead>
              
              <!-- Days of Month -->
              <TableHead
                v-for="day in daysInMonth"
                :key="day"
                class="px-1 text-center min-w-[22px] border-l border-border/60 text-[10px]"
              >
                {{ day }}
              </TableHead>
              
              <!-- Summary Totals -->
              <TableHead class="px-1.5 text-center bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-l font-bold text-[10px]">H</TableHead>
              <TableHead class="px-1.5 text-center bg-amber-500/10 text-amber-700 dark:text-amber-400 font-bold text-[10px]">T</TableHead>
              <TableHead class="px-1.5 text-center bg-blue-500/10 text-blue-700 dark:text-blue-400 font-bold text-[10px]">S</TableHead>
              <TableHead class="px-1.5 text-center bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-bold text-[10px]">I</TableHead>
              <TableHead class="px-1.5 text-center bg-rose-500/10 text-rose-700 dark:text-rose-400 font-bold text-[10px]">A</TableHead>
              <TableHead class="px-2 text-center bg-muted/60 font-bold text-[10px]">%</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody class="font-mono">
            <TableRow v-if="matrixData.length === 0">
              <TableCell :colspan="daysInMonth + 9" class="h-32 text-center text-muted-foreground font-sans text-xs">
                Tidak ada data rekap untuk periode ini.
              </TableCell>
            </TableRow>

            <TableRow
              v-for="(row, idx) in matrixData"
              :key="row.id_siswa"
              class="transition-colors hover:bg-muted/40"
            >
              <TableCell class="text-center sticky left-0 bg-card z-10 text-muted-foreground font-sans text-xs">{{ idx + 1 }}</TableCell>
              <TableCell class="text-left sticky left-10 bg-card z-10 font-semibold font-sans text-foreground truncate max-w-[170px]">
                {{ row.nama_lengkap }}
              </TableCell>
              <TableCell class="font-sans text-muted-foreground text-[10px]">{{ row.nama_kelas || '-' }}</TableCell>

              <!-- Daily 1..31 Status -->
              <TableCell
                v-for="d in daysInMonth"
                :key="d"
                class="px-1 border-l border-border/40 text-[10px]"
                :class="{
                  'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-bold': row.daily[d] === 'H',
                  'bg-amber-500/15 text-amber-700 dark:text-amber-400 font-bold': row.daily[d] === 'T',
                  'bg-blue-500/15 text-blue-700 dark:text-blue-400 font-bold': row.daily[d] === 'S',
                  'bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 font-bold': row.daily[d] === 'I',
                  'bg-rose-500/15 text-rose-700 dark:text-rose-400 font-bold': row.daily[d] === 'A',
                  'text-muted-foreground/30': row.daily[d] === '-' || !row.daily[d]
                }"
              >
                {{ row.daily[d] || '-' }}
              </TableCell>

              <!-- Summary Columns -->
              <TableCell class="font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-500/5 border-l">{{ row.summary.hadir }}</TableCell>
              <TableCell class="font-bold text-amber-700 dark:text-amber-400 bg-amber-500/5">{{ row.summary.terlambat }}</TableCell>
              <TableCell class="font-bold text-blue-700 dark:text-blue-400 bg-blue-500/5">{{ row.summary.sakit }}</TableCell>
              <TableCell class="font-bold text-indigo-700 dark:text-indigo-400 bg-indigo-500/5">{{ row.summary.izin }}</TableCell>
              <TableCell class="font-bold text-rose-700 dark:text-rose-400 bg-rose-500/5">{{ row.summary.alpa }}</TableCell>
              <TableCell class="font-bold text-foreground bg-muted/30">{{ row.summary.persentase }}%</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </Card>

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
} from '@lucide/vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

const toast = useToast();
const { user, role } = useAuth();

const canFilterAllKelas = computed(() => ['admin', 'kepala', 'sekretaris'].includes(role.value));

const selectedBulan = ref(new Date().getMonth() + 1);
const selectedTahun = ref(new Date().getFullYear());
const selectedKelas = ref(user.value?.id_kelas || '');
const kelasList = ref([]);
const matrixData = ref([]);
const daysInMonth = ref(31);

const bulanList = {
  1: 'Januari', 2: 'Februari', 3: 'Maret', 4: 'April',
  5: 'Mei', 6: 'Juni', 7: 'Juli', 8: 'Agustus',
  9: 'September', 10: 'Oktober', 11: 'November', 12: 'Desember'
};

const loadKelas = async () => {
  try {
    const res = await api.get('/kelas');
    if (res.data.success) {
      kelasList.value = res.data.data;
    }
  } catch (e) {}
};

const loadMatrix = async () => {
  try {
    const params = {
      bulan: selectedBulan.value,
      tahun: selectedTahun.value,
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
    }
  } catch (e) {
    toast.error('Gagal memuat rekap matrix absensi.');
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

  let csv = 'No,NISN,Nama Lengkap,Kelas,';
  for (let d = 1; d <= daysInMonth.value; d++) {
    csv += `Tgl ${d},`;
  }
  csv += 'Hadir,Terlambat,Sakit,Izin,Alpa,Persentase Kehadiran\n';

  matrixData.value.forEach((row, idx) => {
    csv += `"${idx + 1}","${row.nisn}","${row.nama_lengkap}","${row.nama_kelas || '-'}",`;
    for (let d = 1; d <= daysInMonth.value; d++) {
      csv += `"${row.daily[d] || '-'}",`;
    }
    csv += `"${row.summary.hadir}","${row.summary.terlambat}","${row.summary.sakit}","${row.summary.izin}","${row.summary.alpa}","${row.summary.persentase}%"\n`;
  });

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  const filenamePrefix = !canFilterAllKelas.value && user.value?.nama_kelas
    ? `Rekap_Matrix_${user.value.nama_kelas.replace(/\s+/g, '_')}`
    : 'Rekap_Matrix_Absensi';
  link.setAttribute('download', `${filenamePrefix}_${selectedBulan.value}_${selectedTahun.value}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  toast.success('File CSV berhasil diunduh!');
};

onMounted(async () => {
  if (!canFilterAllKelas.value) {
    selectedKelas.value = user.value?.id_kelas || '';
  }
  await Promise.all([
    canFilterAllKelas.value ? loadKelas() : Promise.resolve(),
    loadMatrix(),
  ]);
});
</script>
