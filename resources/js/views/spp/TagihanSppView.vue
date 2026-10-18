<template>
  <div class="space-y-6 animate-fadeIn">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>📜</span>
          <span>Data Tagihan & Tunggakan SPP</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Monitoring status kewajiban SPP seluruh santri per periode bulan dan tahun ajaran
        </p>
      </div>

      <!-- Action: Generate Tagihan -->
      <button
        @click="showGenerateModal = true"
        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer"
      >
        <span>⚡</span>
        <span>Generate Tagihan Bulanan</span>
      </button>
    </div>

    <!-- Filters Row -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm text-xs">
      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Bulan</label>
        <select
          v-model="filters.bulan"
          @change="loadTagihan(1)"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
        >
          <option value="">Semua Bulan</option>
          <option v-for="(nama, num) in bulanList" :key="num" :value="num">{{ nama }}</option>
        </select>
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Tahun</label>
        <select
          v-model="filters.tahun"
          @change="loadTagihan(1)"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
        >
          <option value="">Semua Tahun</option>
          <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Kelas / Jilid</label>
        <select
          v-model="filters.id_kelas"
          @change="loadTagihan(1)"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
        >
          <option value="">Semua Kelas</option>
          <option v-for="k in kelasList" :key="k.id" :value="k.id">{{ k.nama_kelas }}</option>
        </select>
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Status Pembayaran</label>
        <select
          v-model="filters.status"
          @change="loadTagihan(1)"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
        >
          <option value="">Semua Status</option>
          <option value="belum_lunas">Belum Lunas</option>
          <option value="lunas">Lunas</option>
          <option value="kurang_bayar">Kurang Bayar</option>
        </select>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200/80 dark:border-zinc-800 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-500 font-bold border-b border-zinc-200 dark:border-zinc-800 text-[10px] uppercase">
            <tr>
              <th class="py-3 px-4">No</th>
              <th class="py-3 px-4">Santri</th>
              <th class="py-3 px-4">Jilid / Kelas</th>
              <th class="py-3 px-4">Periode Tagihan</th>
              <th class="py-3 px-4">Nominal Tagihan</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Jatuh Tempo</th>
              <th class="py-3 px-4 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-200/60 dark:divide-zinc-800">
            <tr v-if="tagihanList.length === 0">
              <td colspan="8" class="py-8 text-center text-zinc-400">
                Tidak ada data tagihan yang sesuai dengan filter.
              </td>
            </tr>

            <tr
              v-for="(t, idx) in tagihanList"
              :key="t.id"
              class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition"
            >
              <td class="py-3 px-4 text-zinc-400">{{ idx + 1 }}</td>
              <td class="py-3 px-4">
                <p class="font-bold text-zinc-900 dark:text-zinc-100">{{ t.siswa?.nama_lengkap }}</p>
                <p class="text-[10px] text-zinc-400 font-mono">NISN: {{ t.siswa?.nisn }}</p>
              </td>
              <td class="py-3 px-4 font-semibold text-zinc-700 dark:text-zinc-300">
                {{ t.siswa?.kelas?.nama_kelas || '-' }}
              </td>
              <td class="py-3 px-4 font-bold text-zinc-800 dark:text-zinc-200">
                {{ bulanList[t.bulan] }} {{ t.tahun }}
              </td>
              <td class="py-3 px-4 font-mono font-bold text-zinc-900 dark:text-zinc-100">
                Rp {{ formatRupiah(t.nominal_tagihan) }}
              </td>
              <td class="py-3 px-4">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase"
                  :class="{
                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': t.status === 'lunas',
                    'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': t.status === 'belum_lunas',
                    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': t.status === 'kurang_bayar',
                  }"
                >
                  {{ t.status.replace('_', ' ') }}
                </span>
              </td>
              <td class="py-3 px-4 text-zinc-400 font-mono text-[11px]">
                {{ t.jatuh_tempo || '-' }}
              </td>
              <td class="py-3 px-4 text-center">
                <router-link
                  v-if="t.status !== 'lunas'"
                  to="/spp/transaksi"
                  class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-[11px] font-semibold transition"
                >
                  Bayar &rarr;
                </router-link>
                <span v-else class="text-zinc-400 text-xs font-semibold">✓ Lunas</span>
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
            @click="loadTagihan(currentPage - 1)"
            :disabled="currentPage <= 1"
            class="px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 disabled:opacity-40 cursor-pointer"
          >
            Sebelumnya
          </button>
          <button
            @click="loadTagihan(currentPage + 1)"
            :disabled="currentPage >= totalPages"
            class="px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 disabled:opacity-40 cursor-pointer"
          >
            Berikutnya
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Generate Tagihan Massal -->
    <div
      v-if="showGenerateModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
    >
      <div class="w-full max-w-sm rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-6 shadow-2xl animate-slideUp">
        <h3 class="text-base font-bold text-zinc-900 dark:text-white mb-2">Generate Tagihan Bulanan</h3>
        <p class="text-xs text-zinc-500 mb-4">
          Sistem akan membuat tagihan SPP untuk seluruh santri yang berstatus aktif berdasarkan tarif jilid masing-masing.
        </p>

        <form @submit.prevent="executeGenerate" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Pilih Bulan *</label>
            <select
              v-model.number="genForm.bulan"
              required
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
            >
              <option v-for="(nama, num) in bulanList" :key="num" :value="num">{{ nama }}</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Pilih Tahun *</label>
            <input
              v-model.number="genForm.tahun"
              type="number"
              required
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
            />
          </div>

          <div class="flex items-center justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
            <button
              type="button"
              @click="showGenerateModal = false"
              class="px-4 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 text-xs font-semibold cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isGenerating"
              class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-md shadow-emerald-600/20 cursor-pointer flex items-center gap-1.5"
            >
              <span v-if="isGenerating" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>{{ isGenerating ? 'Memproses...' : 'Mulai Generate' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';

const toast = useToast();

const tagihanList = ref([]);
const kelasList = ref([]);
const currentPage = ref(1);
const totalPages = ref(1);

const filters = ref({
  bulan: new Date().getMonth() + 1,
  tahun: new Date().getFullYear(),
  id_kelas: '',
  status: '',
});

const showGenerateModal = ref(false);
const isGenerating = ref(false);
const genForm = ref({
  bulan: new Date().getMonth() + 1,
  tahun: new Date().getFullYear(),
});

const bulanList = {
  1: 'Januari', 2: 'Februari', 3: 'Maret', 4: 'April',
  5: 'Mei', 6: 'Juni', 7: 'Juli', 8: 'Agustus',
  9: 'September', 10: 'Oktober', 11: 'November', 12: 'Desember'
};

const formatRupiah = (val) => new Intl.NumberFormat('id-ID').format(val || 0);

const loadKelas = async () => {
  try {
    const res = await api.get('/kelas');
    if (res.data.success) {
      kelasList.value = res.data.data;
    }
  } catch (e) {}
};

const loadTagihan = async (page = 1) => {
  currentPage.value = page;
  try {
    const params = {
      page: page,
      ...filters.value,
    };
    const res = await api.get('/spp/tagihan', { params });
    if (res.data.success) {
      tagihanList.value = res.data.data.data;
      totalPages.value = res.data.data.last_page;
    }
  } catch (e) {
    toast.error('Gagal memuat data tagihan SPP.');
  }
};

const executeGenerate = async () => {
  isGenerating.value = true;
  try {
    const res = await api.post('/spp/generate-tagihan', genForm.value);
    if (res.data.success) {
      toast.success(res.data.message);
      showGenerateModal.value = false;
      filters.value.bulan = genForm.value.bulan;
      filters.value.tahun = genForm.value.tahun;
      loadTagihan(1);
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal generate tagihan.');
  } finally {
    isGenerating.value = false;
  }
};

onMounted(() => {
  loadKelas();
  loadTagihan(1);
});
</script>
