<template>
  <div class="space-y-6 animate-fadeIn">
    
    <!-- Header & Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm print:hidden">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>📑</span>
          <span>Rekapitulasi Matrix Absensi</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Matriks laporan kehadiran tanggal 1 s/d 31 dalam satu bulan penuh
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <button
          @click="printMatrix"
          class="px-4 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-semibold shadow transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>🖨</span>
          <span>Cetak Laporan</span>
        </button>
        <button
          @click="exportCsv"
          class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>📥</span>
          <span>Export CSV</span>
        </button>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm text-xs print:hidden">
      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Bulan</label>
        <select
          v-model="selectedBulan"
          @change="loadMatrix"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        >
          <option v-for="(nama, num) in bulanList" :key="num" :value="num">
            {{ nama }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Tahun</label>
        <select
          v-model="selectedTahun"
          @change="loadMatrix"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        >
          <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Kelas / Jilid</label>
        <select
          v-model="selectedKelas"
          @change="loadMatrix"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        >
          <option value="">Semua Kelas</option>
          <option v-for="k in kelasList" :key="k.id" :value="k.id">{{ k.nama_kelas }}</option>
        </select>
      </div>
    </div>

    <!-- Printable Header Banner (Only visible during print) -->
    <div class="hidden print:block text-center border-b pb-4 mb-4">
      <h1 class="text-lg font-bold">REKAPITULASI PRESENSI SANTRI</h1>
      <p class="text-xs">Periode: {{ bulanList[selectedBulan] }} {{ selectedTahun }}</p>
    </div>

    <!-- Matrix Table Container -->
    <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200/80 dark:border-zinc-800 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-center text-[11px] border-collapse">
          <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 font-bold border-b border-zinc-200 dark:border-zinc-800">
            <tr>
              <th class="py-2.5 px-3 text-left sticky left-0 bg-zinc-50 dark:bg-zinc-800 z-10">No</th>
              <th class="py-2.5 px-3 text-left sticky left-8 bg-zinc-50 dark:bg-zinc-800 z-10 min-w-[150px]">Santri</th>
              <th class="py-2.5 px-2">Jilid</th>
              <!-- Days of Month -->
              <th
                v-for="day in daysInMonth"
                :key="day"
                class="py-2 px-1 min-w-[22px] border-l border-zinc-200/40 dark:border-zinc-800 text-[10px]"
              >
                {{ day }}
              </th>
              <!-- Summary Totals -->
              <th class="py-2 px-2 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border-l">H</th>
              <th class="py-2 px-2 bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300">T</th>
              <th class="py-2 px-2 bg-blue-50 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300">S</th>
              <th class="py-2 px-2 bg-purple-50 dark:bg-purple-950/60 text-purple-800 dark:text-purple-300">I</th>
              <th class="py-2 px-2 bg-rose-50 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300">A</th>
              <th class="py-2 px-2 bg-zinc-100 dark:bg-zinc-800 font-extrabold">%</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-200/60 dark:divide-zinc-800 font-mono">
            <tr v-if="matrixData.length === 0">
              <td :colspan="daysInMonth + 9" class="py-8 text-center text-zinc-400 font-sans">
                Tidak ada data rekap untuk periode ini.
              </td>
            </tr>

            <tr
              v-for="(row, idx) in matrixData"
              :key="row.id_siswa"
              class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition"
            >
              <td class="py-2 px-3 text-left sticky left-0 bg-white dark:bg-zinc-900 z-10 text-zinc-400 font-sans">{{ idx + 1 }}</td>
              <td class="py-2 px-3 text-left sticky left-8 bg-white dark:bg-zinc-900 z-10 font-bold font-sans text-zinc-900 dark:text-zinc-100 truncate max-w-[160px]">
                {{ row.nama_lengkap }}
              </td>
              <td class="py-2 px-2 font-sans text-zinc-500 text-[10px]">{{ row.nama_kelas || '-' }}</td>

              <!-- 1..31 Days -->
              <td
                v-for="d in daysInMonth"
                :key="d"
                class="py-1 px-1 border-l border-zinc-100 dark:border-zinc-800/60 text-[10px]"
                :class="{
                  'bg-emerald-100/70 text-emerald-800 font-bold dark:bg-emerald-950/50 dark:text-emerald-300': row.daily[d] === 'H',
                  'bg-amber-100/70 text-amber-800 font-bold dark:bg-amber-950/50 dark:text-amber-300': row.daily[d] === 'T',
                  'bg-blue-100/70 text-blue-800 font-bold dark:bg-blue-950/50 dark:text-blue-300': row.daily[d] === 'S',
                  'bg-purple-100/70 text-purple-800 font-bold dark:bg-purple-950/50 dark:text-purple-300': row.daily[d] === 'I',
                  'bg-rose-100/70 text-rose-800 font-bold dark:bg-rose-950/50 dark:text-rose-300': row.daily[d] === 'A',
                  'text-zinc-300 dark:text-zinc-700': row.daily[d] === '-' || !row.daily[d]
                }"
              >
                {{ row.daily[d] || '-' }}
              </td>

              <!-- Summary Columns -->
              <td class="py-2 px-1 font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50/40 dark:bg-emerald-950/20 border-l">{{ row.summary.hadir }}</td>
              <td class="py-2 px-1 font-bold text-amber-700 dark:text-amber-400 bg-amber-50/40 dark:bg-amber-950/20">{{ row.summary.terlambat }}</td>
              <td class="py-2 px-1 font-bold text-blue-700 dark:text-blue-400 bg-blue-50/40 dark:bg-blue-950/20">{{ row.summary.sakit }}</td>
              <td class="py-2 px-1 font-bold text-purple-700 dark:text-purple-400 bg-purple-50/40 dark:bg-purple-950/20">{{ row.summary.izin }}</td>
              <td class="py-2 px-1 font-bold text-rose-700 dark:text-rose-400 bg-rose-50/40 dark:bg-rose-950/20">{{ row.summary.alpa }}</td>
              <td class="py-2 px-1 font-extrabold text-zinc-900 dark:text-zinc-100 bg-zinc-100/60 dark:bg-zinc-800/40">{{ row.summary.persentase }}%</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap items-center gap-4 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 text-xs">
      <span class="text-zinc-400 font-bold text-[10px] uppercase">Keterangan:</span>
      <div class="flex items-center gap-1.5"><span class="w-4 h-4 rounded bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-[10px]">H</span><span>Hadir</span></div>
      <div class="flex items-center gap-1.5"><span class="w-4 h-4 rounded bg-amber-100 text-amber-800 font-bold flex items-center justify-center text-[10px]">T</span><span>Terlambat</span></div>
      <div class="flex items-center gap-1.5"><span class="w-4 h-4 rounded bg-blue-100 text-blue-800 font-bold flex items-center justify-center text-[10px]">S</span><span>Sakit</span></div>
      <div class="flex items-center gap-1.5"><span class="w-4 h-4 rounded bg-purple-100 text-purple-800 font-bold flex items-center justify-center text-[10px]">I</span><span>Izin</span></div>
      <div class="flex items-center gap-1.5"><span class="w-4 h-4 rounded bg-rose-100 text-rose-800 font-bold flex items-center justify-center text-[10px]">A</span><span>Alpa</span></div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';

const toast = useToast();

const selectedBulan = ref(new Date().getMonth() + 1);
const selectedTahun = ref(new Date().getFullYear());
const selectedKelas = ref('');
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
  } catch (e) {
    //
  }
};

const loadMatrix = async () => {
  try {
    const params = {
      bulan: selectedBulan.value,
      tahun: selectedTahun.value,
    };
    if (selectedKelas.value) {
      params.id_kelas = selectedKelas.value;
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
  link.setAttribute('download', `Rekap_Matrix_Absensi_${selectedBulan.value}_${selectedTahun.value}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  toast.success('File CSV berhasil diunduh!');
};

onMounted(() => {
  loadKelas();
  loadMatrix();
});
</script>
