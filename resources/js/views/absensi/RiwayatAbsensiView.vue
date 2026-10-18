<template>
  <div class="space-y-6 animate-fadeIn">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>📋</span>
          <span>Riwayat Absensi Santri</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Daftar seluruh catatan presensi masuk dan pulang berdasarkan tanggal & filter
        </p>
      </div>

      <button
        @click="loadRiwayat(1)"
        :disabled="loading"
        class="px-4 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer"
      >
        <span :class="{'animate-spin': loading}">🔄</span>
        <span>Filter Ulang</span>
      </button>
    </div>

    <!-- Filters Row -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm text-xs">
      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Dari Tanggal</label>
        <input
          v-model="filters.tanggal_mulai"
          type="date"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs"
        />
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Sampai Tanggal</label>
        <input
          v-model="filters.tanggal_selesai"
          type="date"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs"
        />
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Kelas / Jilid</label>
        <select
          v-model="filters.id_kelas"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs"
        >
          <option value="">Semua Kelas</option>
          <option v-for="k in kelasList" :key="k.id" :value="k.id">{{ k.nama_kelas }}</option>
        </select>
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Status Kehadiran</label>
        <select
          v-model="filters.status"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs"
        >
          <option value="">Semua Status</option>
          <option value="Hadir">Hadir</option>
          <option value="Terlambat">Terlambat</option>
          <option value="Sakit">Sakit</option>
          <option value="Izin">Izin</option>
          <option value="Alpa">Alpa</option>
        </select>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200/80 dark:border-zinc-800 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-500 font-bold border-b border-zinc-200 dark:border-zinc-800 text-[10px] uppercase">
            <tr>
              <th class="py-3 px-4">Tanggal</th>
              <th class="py-3 px-4">Santri</th>
              <th class="py-3 px-4">Kelas</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Waktu Masuk</th>
              <th class="py-3 px-4">Waktu Pulang</th>
              <th class="py-3 px-4">Metode</th>
              <th class="py-3 px-4">Keterangan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-200/60 dark:divide-zinc-800">
            <tr v-if="riwayatList.length === 0">
              <td colspan="8" class="py-8 text-center text-zinc-400">
                Tidak ada data riwayat absensi.
              </td>
            </tr>

            <tr
              v-for="item in riwayatList"
              :key="item.id"
              class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition"
            >
              <td class="py-3 px-4 font-mono font-bold text-zinc-800 dark:text-zinc-200">
                {{ item.tanggal }}
              </td>
              <td class="py-3 px-4">
                <p class="font-bold text-zinc-900 dark:text-zinc-100">{{ item.siswa?.nama_lengkap }}</p>
                <p class="text-[10px] text-zinc-400 font-mono">NISN: {{ item.siswa?.nisn }}</p>
              </td>
              <td class="py-3 px-4 font-semibold text-zinc-700 dark:text-zinc-300">
                {{ item.siswa?.kelas?.nama_kelas || '-' }}
              </td>
              <td class="py-3 px-4">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[11px] font-bold"
                  :class="{
                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': item.status === 'Hadir',
                    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': item.status === 'Terlambat',
                    'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300': item.status === 'Sakit',
                    'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300': item.status === 'Izin',
                    'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': item.status === 'Alpa',
                  }"
                >
                  {{ item.status }}
                </span>
              </td>
              <td class="py-3 px-4 font-mono text-zinc-700 dark:text-zinc-300">
                {{ item.waktu_masuk ? item.waktu_masuk.substring(0, 5) + ' WIB' : '-' }}
              </td>
              <td class="py-3 px-4 font-mono text-zinc-700 dark:text-zinc-300">
                {{ item.waktu_pulang ? item.waktu_pulang.substring(0, 5) + ' WIB' : '-' }}
              </td>
              <td class="py-3 px-4 text-zinc-400 font-mono text-[10px] uppercase">
                {{ item.metode_scan }}
              </td>
              <td class="py-3 px-4 text-zinc-500 text-[11px]">
                {{ item.keterangan || '-' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="p-4 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between text-xs">
        <span class="text-zinc-400">Halaman {{ currentPage }} dari {{ totalPages }}</span>
        <div class="flex items-center gap-1.5">
          <button
            @click="loadRiwayat(currentPage - 1)"
            :disabled="currentPage <= 1"
            class="px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 disabled:opacity-40 cursor-pointer"
          >
            Sebelumnya
          </button>
          <button
            @click="loadRiwayat(currentPage + 1)"
            :disabled="currentPage >= totalPages"
            class="px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 disabled:opacity-40 cursor-pointer"
          >
            Berikutnya
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';

const toast = useToast();

const loading = ref(false);
const riwayatList = ref([]);
const kelasList = ref([]);
const currentPage = ref(1);
const totalPages = ref(1);

const filters = ref({
  tanggal_mulai: '',
  tanggal_selesai: '',
  id_kelas: '',
  status: '',
});

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

const loadRiwayat = async (page = 1) => {
  loading.value = true;
  currentPage.value = page;

  try {
    const params = {
      page: page,
      ...filters.value,
    };
    const res = await api.get('/absensi/riwayat', { params });
    if (res.data.success) {
      riwayatList.value = res.data.data.data;
      totalPages.value = res.data.data.last_page;
    }
  } catch (e) {
    toast.error('Gagal memuat data riwayat.');
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadKelas();
  loadRiwayat(1);
});
</script>
