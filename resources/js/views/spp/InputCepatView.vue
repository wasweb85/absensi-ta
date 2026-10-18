<template>
  <div class="space-y-6 animate-fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>⚡</span>
          <span>Input Cepat Pembayaran SPP (Batch)</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Terima pembayaran SPP kolektif untuk banyak santri sekaligus dalam satu jilid/kelas hanya dengan satu kali klik
        </p>
      </div>

      <div class="flex items-center gap-2">
        <router-link
          to="/spp/transaksi"
          class="px-4 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition"
        >
          Kasir Satuan &rarr;
        </router-link>
      </div>
    </div>

    <!-- Parameter Filter Box -->
    <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 mb-4">
        1. Pilih Periode & Jilid Kelas
      </h3>

      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
        <div>
          <label class="block font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Pilih Jilid / Kelas *</label>
          <select
            v-model="selectedKelasId"
            @change="fetchSantriByKelas"
            class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          >
            <option value="">-- Pilih Kelas --</option>
            <option v-for="k in kelasList" :key="k.id" :value="k.id">
              {{ k.nama_kelas }} (Rp {{ formatRupiah(k.nominal_spp) }})
            </option>
          </select>
        </div>

        <div>
          <label class="block font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Bulan Tagihan *</label>
          <select
            v-model="selectedBulan"
            @change="fetchSantriByKelas"
            class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          >
            <option v-for="(nama, num) in bulanList" :key="num" :value="num">
              {{ nama }}
            </option>
          </select>
        </div>

        <div>
          <label class="block font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Tahun *</label>
          <select
            v-model="selectedTahun"
            @change="fetchSantriByKelas"
            class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          >
            <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <div>
          <label class="block font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Metode Default</label>
          <select
            v-model="defaultMetode"
            @change="applyDefaultMetode"
            class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          >
            <option value="Tunai">Tunai / Cash</option>
            <option value="Transfer">Transfer Bank</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Batch Table -->
    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm overflow-hidden">
      <!-- Toolbar -->
      <div class="p-4 border-b border-zinc-100 dark:border-zinc-800 flex flex-wrap items-center justify-between gap-3 bg-zinc-50/50 dark:bg-zinc-800/20">
        <div class="flex items-center gap-3">
          <span class="text-xs font-bold uppercase tracking-wider text-zinc-500">
            Daftar Santri ({{ santriItems.length }} Orang)
          </span>
          <div v-if="santriItems.length > 0" class="flex items-center gap-2">
            <button
              @click="selectAllUnpaid"
              class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-200 transition cursor-pointer"
            >
              Pilih Semua Yang Belum Lunas
            </button>
            <button
              @click="deselectAll"
              class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-zinc-200 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-300 transition cursor-pointer"
            >
              Kosongkan Pilihan
            </button>
          </div>
        </div>

        <div v-if="selectedCount > 0" class="flex items-center gap-4 text-xs font-bold">
          <span class="text-zinc-600 dark:text-zinc-300">
            Terpilih: <span class="text-emerald-600">{{ selectedCount }}</span> santri
          </span>
          <span class="text-zinc-600 dark:text-zinc-300">
            Total Bayar: <span class="text-emerald-600">Rp {{ formatRupiah(totalNominalBayar) }}</span>
          </span>
        </div>
      </div>

      <!-- State: Not Selected -->
      <div v-if="!selectedKelasId" class="p-12 text-center text-zinc-400">
        <p class="text-4xl mb-2">📋</p>
        <p class="text-sm font-semibold">Silakan pilih Jilid / Kelas terlebih dahulu untuk menampilkan daftar santri.</p>
      </div>

      <!-- State: Loading -->
      <div v-else-if="isLoading" class="p-12 text-center text-zinc-400">
        <p class="text-3xl mb-2 animate-spin">⏳</p>
        <p class="text-sm font-medium">Memuat data santri dan tagihan...</p>
      </div>

      <!-- State: Empty -->
      <div v-else-if="santriItems.length === 0" class="p-12 text-center text-zinc-400">
        <p class="text-4xl mb-2">🤷‍♂️</p>
        <p class="text-sm font-semibold">Tidak ada santri aktif di jilid / kelas ini.</p>
      </div>

      <!-- Table of items -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 uppercase tracking-wider font-semibold border-b border-zinc-200/80 dark:border-zinc-800">
            <tr>
              <th class="p-3 w-10 text-center">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  @change="toggleSelectAll"
                  class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer"
                />
              </th>
              <th class="p-3">NISN & Nama Santri</th>
              <th class="p-3">Status Saat Ini</th>
              <th class="p-3 w-40">Nominal Bayar (Rp)</th>
              <th class="p-3 w-36">Metode</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
            <tr
              v-for="item in santriItems"
              :key="item.id_siswa"
              :class="[
                item.selected ? 'bg-emerald-50/60 dark:bg-emerald-950/20' : 'hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40',
                item.status === 'lunas' ? 'opacity-70' : ''
              ]"
              class="transition"
            >
              <td class="p-3 text-center">
                <input
                  type="checkbox"
                  v-model="item.selected"
                  :disabled="item.status === 'lunas'"
                  class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer disabled:opacity-30"
                />
              </td>
              <td class="p-3">
                <p class="font-bold text-zinc-900 dark:text-white">{{ item.nama_lengkap }}</p>
                <p class="text-[11px] font-mono text-zinc-400">NISN: {{ item.nisn }} &bull; {{ item.nama_kelas }}</p>
              </td>
              <td class="p-3">
                <span
                  v-if="item.status === 'lunas'"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300"
                >
                  &check; Lunas
                </span>
                <span
                  v-else-if="item.status === 'kurang_bayar'"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300"
                >
                  Kurang: Rp {{ formatRupiah(item.sisa_kurang) }}
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300"
                >
                  Belum Bayar
                </span>
              </td>
              <td class="p-3">
                <input
                  type="number"
                  v-model.number="item.nominal"
                  :disabled="!item.selected || item.status === 'lunas'"
                  class="w-full px-2.5 py-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-bold focus:ring-2 focus:ring-emerald-500 text-xs disabled:opacity-40"
                />
              </td>
              <td class="p-3">
                <select
                  v-model="item.metode"
                  :disabled="!item.selected || item.status === 'lunas'"
                  class="w-full px-2 py-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs disabled:opacity-40"
                >
                  <option value="Tunai">Tunai</option>
                  <option value="Transfer">Transfer</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Action Footer -->
      <div v-if="santriItems.length > 0" class="p-4 bg-zinc-50 dark:bg-zinc-800/40 border-t border-zinc-200/80 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="text-xs text-zinc-500">
          * Santri dengan status <b>Lunas</b> tidak dapat dipilih untuk input cepat tagihan periode ini.
        </div>

        <button
          @click="submitBatchPayment"
          :disabled="selectedCount === 0 || isSubmitting"
          class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/30 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
        >
          <span v-if="isSubmitting" class="animate-spin">⏳</span>
          <span v-else>💳</span>
          <span>Proses Pembayaran ({{ selectedCount }} Santri &bull; Rp {{ formatRupiah(totalNominalBayar) }})</span>
        </button>
      </div>
    </div>

    <!-- Result Modal (Success Report) -->
    <div
      v-if="showResultModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fadeIn"
    >
      <div class="w-full max-w-lg p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-emerald-100 dark:bg-emerald-950/60 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-xl font-bold">
            &check;
          </div>
          <div>
            <h3 class="text-base font-bold text-zinc-900 dark:text-white">Pembayaran Batch Berhasil!</h3>
            <p class="text-xs text-zinc-500">{{ resultMessage }}</p>
          </div>
        </div>

        <div class="max-h-60 overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-800 p-3 bg-zinc-50 dark:bg-zinc-800/40 text-xs space-y-1.5 font-mono">
          <div v-for="(kw, idx) in resultKuitansiList" :key="idx" class="flex items-center justify-between py-1 border-b border-zinc-200/50 dark:border-zinc-700/50 last:border-none">
            <span class="text-zinc-600 dark:text-zinc-300 font-bold">{{ kw }}</span>
            <span class="text-[10px] text-emerald-600 font-sans font-semibold">Tercatat</span>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            @click="closeResultModal"
            class="px-4 py-2 rounded-xl bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 text-xs font-bold hover:opacity-90 transition cursor-pointer"
          >
            Tutup & Lanjutkan
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';

const toast = useToast();

const bulanList = {
  1: 'Januari',
  2: 'Februari',
  3: 'Maret',
  4: 'April',
  5: 'Mei',
  6: 'Juni',
  7: 'Juli',
  8: 'Agustus',
  9: 'September',
  10: 'Oktober',
  11: 'November',
  12: 'Desember',
};

const currentDate = new Date();
const selectedBulan = ref(currentDate.getMonth() + 1);
const selectedTahun = ref(currentDate.getFullYear());
const selectedKelasId = ref('');
const defaultMetode = ref('Tunai');

const kelasList = ref([]);
const santriItems = ref([]);
const isLoading = ref(false);
const isSubmitting = ref(false);

const showResultModal = ref(false);
const resultMessage = ref('');
const resultKuitansiList = ref([]);

function formatRupiah(val) {
  if (!val) return '0';
  return Number(val).toLocaleString('id-ID');
}

async function loadKelas() {
  try {
    const res = await api.get('/kelas');
    kelasList.value = res.data.data || [];
  } catch (err) {
    toast.error('Gagal memuat data kelas.');
  }
}

async function fetchSantriByKelas() {
  if (!selectedKelasId.value) {
    santriItems.value = [];
    return;
  }

  isLoading.value = true;
  try {
    // 1. Ambil santri di kelas tersebut
    const resSiswa = await api.get('/siswa', {
      params: { id_kelas: selectedKelasId.value, status: 'aktif' }
    });
    const siswaList = resSiswa.data.data?.data || resSiswa.data.data || [];

    // 2. Ambil data tagihan kelas tersebut di bulan/tahun yang dipilih
    const resTagihan = await api.get('/spp/tagihan', {
      params: {
        id_kelas: selectedKelasId.value,
        bulan: selectedBulan.value,
        tahun: selectedTahun.value,
        per_page: 200,
      }
    });
    const tagihanList = resTagihan.data.data?.data || [];

    const tagihanMap = {};
    for (const tg of tagihanList) {
      tagihanMap[tg.id_siswa] = tg;
    }

    // Dapatkan nominal standar kelas
    const kData = kelasList.value.find(k => k.id === Number(selectedKelasId.value));
    const nominalKelas = kData ? Number(kData.nominal_spp) : 50000;

    santriItems.value = siswaList.map(s => {
      const tg = tagihanMap[s.id];
      let status = 'belum_lunas';
      let nominal = nominalKelas;
      let sisaKurang = 0;

      if (tg) {
        status = tg.status;
        if (tg.status === 'lunas') {
          nominal = 0;
        } else if (tg.status === 'kurang_bayar') {
          sisaKurang = Number(tg.nominal_tagihan) - Number(tg.terbayar || 0);
          nominal = sisaKurang > 0 ? sisaKurang : nominalKelas;
        } else {
          nominal = Number(tg.nominal_tagihan);
        }
      }

      return {
        id_siswa: s.id,
        nisn: s.nisn,
        nama_lengkap: s.nama_lengkap,
        nama_kelas: s.kelas?.nama_kelas || kData?.nama_kelas || '',
        status,
        sisa_kurang: sisaKurang,
        nominal,
        metode: defaultMetode.value,
        selected: status !== 'lunas',
      };
    });
  } catch (err) {
    toast.error('Gagal mengambil data tagihan santri.');
  } finally {
    isLoading.value = false;
  }
}

function applyDefaultMetode() {
  for (const item of santriItems.value) {
    item.metode = defaultMetode.value;
  }
}

const selectedCount = computed(() => {
  return santriItems.value.filter(i => i.selected && i.status !== 'lunas').length;
});

const totalNominalBayar = computed(() => {
  return santriItems.value
    .filter(i => i.selected && i.status !== 'lunas')
    .reduce((acc, curr) => acc + (Number(curr.nominal) || 0), 0);
});

const isAllSelected = computed(() => {
  const eligible = santriItems.value.filter(i => i.status !== 'lunas');
  return eligible.length > 0 && eligible.every(i => i.selected);
});

function toggleSelectAll(e) {
  const checked = e.target.checked;
  for (const item of santriItems.value) {
    if (item.status !== 'lunas') {
      item.selected = checked;
    }
  }
}

function selectAllUnpaid() {
  for (const item of santriItems.value) {
    if (item.status !== 'lunas') {
      item.selected = true;
    }
  }
}

function deselectAll() {
  for (const item of santriItems.value) {
    item.selected = false;
  }
}

async function submitBatchPayment() {
  const selectedItems = santriItems.value.filter(i => i.selected && i.status !== 'lunas');
  if (selectedItems.length === 0) {
    toast.warning('Pilih minimal satu santri yang belum lunas.');
    return;
  }

  isSubmitting.value = true;
  try {
    const payload = {
      bulan: Number(selectedBulan.value),
      tahun: Number(selectedTahun.value),
      items: selectedItems.map(item => ({
        id_siswa: item.id_siswa,
        nominal: Number(item.nominal),
        metode: item.metode,
      })),
    };

    const res = await api.post('/spp/input-cepat', payload);

    resultMessage.value = res.data.message || 'Pembayaran berhasil diproses.';
    resultKuitansiList.value = res.data.kuitansi_list || [];
    showResultModal.value = true;
    toast.success('Batch pembayaran SPP berhasil diselesaikan.');

    // Reload state santri
    await fetchSantriByKelas();
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal memproses batch pembayaran.';
    toast.error(msg);
  } finally {
    isSubmitting.value = false;
  }
}

function closeResultModal() {
  showResultModal.value = false;
  resultKuitansiList.value = [];
}

onMounted(() => {
  loadKelas();
});
</script>
