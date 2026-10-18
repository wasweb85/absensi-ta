<template>
  <div class="space-y-6 animate-fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm print:hidden">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>🧾</span>
          <span>Riwayat Transaksi Pembayaran SPP</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Daftar seluruh kuitansi penerimaan SPP, cetak ulang bukti pembayaran, dan audit pembatalan transaksi
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="exportCsv"
          :disabled="pembayaranList.length === 0"
          class="px-3.5 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
        >
          <span>📥</span>
          <span>Ekspor CSV</span>
        </button>

        <router-link
          to="/spp/transaksi"
          class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5"
        >
          <span>💳</span>
          <span>Kasir Baru</span>
        </router-link>
      </div>
    </div>

    <!-- Filters Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm text-xs print:hidden">
      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Dari Tanggal</label>
        <input
          v-model="filters.tanggal_mulai"
          type="date"
          @change="loadRiwayat(1)"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        />
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Sampai Tanggal</label>
        <input
          v-model="filters.tanggal_selesai"
          type="date"
          @change="loadRiwayat(1)"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        />
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Jilid / Kelas</label>
        <select
          v-model="filters.id_kelas"
          @change="loadRiwayat(1)"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        >
          <option value="">Semua Kelas</option>
          <option v-for="k in kelasList" :key="k.id" :value="k.id">{{ k.nama_kelas }}</option>
        </select>
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Status Transaksi</label>
        <select
          v-model="filters.status_transaksi"
          @change="loadRiwayat(1)"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        >
          <option value="">Semua Status</option>
          <option value="valid">Valid / Sukses</option>
          <option value="dibatalkan">Dibatalkan</option>
        </select>
      </div>
    </div>

    <!-- Table Card -->
    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm overflow-hidden print:hidden">
      <!-- Loading State -->
      <div v-if="isLoading" class="p-12 text-center text-zinc-400">
        <p class="text-3xl mb-2 animate-spin">⏳</p>
        <p class="text-sm font-medium">Memuat riwayat pembayaran SPP...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="pembayaranList.length === 0" class="p-12 text-center text-zinc-400">
        <p class="text-4xl mb-2">🧾</p>
        <p class="text-sm font-semibold">Belum ada riwayat transaksi pembayaran sesuai filter.</p>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 uppercase tracking-wider font-semibold border-b border-zinc-200/80 dark:border-zinc-800">
            <tr>
              <th class="p-3">No. Kuitansi</th>
              <th class="p-3">Tanggal</th>
              <th class="p-3">Santri</th>
              <th class="p-3">Kelas</th>
              <th class="p-3">Tagihan</th>
              <th class="p-3 text-right">Nominal Bayar</th>
              <th class="p-3">Metode</th>
              <th class="p-3">Petugas</th>
              <th class="p-3 text-center">Status</th>
              <th class="p-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
            <tr
              v-for="p in pembayaranList"
              :key="p.id"
              :class="p.status_transaksi === 'dibatalkan' ? 'bg-rose-50/40 dark:bg-rose-950/20 opacity-70' : 'hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40'"
              class="transition"
            >
              <td class="p-3 font-mono font-bold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                {{ p.nomor_kuitansi }}
              </td>
              <td class="p-3 text-zinc-500 whitespace-nowrap">
                {{ formatTanggal(p.tanggal_bayar) }}
              </td>
              <td class="p-3">
                <p class="font-bold text-zinc-900 dark:text-white">{{ p.siswa?.nama_lengkap || '-' }}</p>
                <p class="text-[10px] text-zinc-400 font-mono">NISN: {{ p.siswa?.nisn || '-' }}</p>
              </td>
              <td class="p-3 font-semibold text-zinc-700 dark:text-zinc-300">
                {{ p.siswa?.kelas?.nama_kelas || '-' }}
              </td>
              <td class="p-3 font-medium text-zinc-600 dark:text-zinc-400">
                {{ getNamaBulan(p.tagihan?.bulan) }} {{ p.tagihan?.tahun }}
              </td>
              <td class="p-3 text-right font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                Rp {{ formatRupiah(p.jumlah_bayar) }}
              </td>
              <td class="p-3 text-zinc-600 dark:text-zinc-400 font-medium">
                {{ p.metode_pembayaran }}
              </td>
              <td class="p-3 text-zinc-500 text-[11px]">
                {{ p.petugas?.name || '-' }}
              </td>
              <td class="p-3 text-center whitespace-nowrap">
                <span
                  v-if="p.status_transaksi === 'valid' || p.status_transaksi === 'sukses'"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300"
                >
                  &check; Sukses
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300"
                  :title="p.alasan_pembatalan"
                >
                  &cross; Dibatalkan
                </span>
              </td>
              <td class="p-3 text-center whitespace-nowrap">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    @click="previewKuitansi(p)"
                    title="Cetak Ulang Kuitansi"
                    class="p-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-zinc-700 dark:text-zinc-300 text-xs transition cursor-pointer"
                  >
                    🖨️
                  </button>

                  <button
                    v-if="p.status_transaksi === 'valid' || p.status_transaksi === 'sukses'"
                    @click="openBatalModal(p)"
                    title="Batalkan Transaksi"
                    class="p-1.5 rounded-lg border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-600 dark:text-rose-400 text-xs transition cursor-pointer"
                  >
                    ❌
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div v-if="totalPages > 1" class="p-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between text-xs text-zinc-500">
        <div>
          Menampilkan halaman {{ currentPage }} dari {{ totalPages }} (Total {{ totalRecords }} transaksi)
        </div>
        <div class="flex items-center gap-1">
          <button
            :disabled="currentPage <= 1"
            @click="loadRiwayat(currentPage - 1)"
            class="px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 disabled:opacity-40 cursor-pointer"
          >
            &larr; Prev
          </button>
          <button
            :disabled="currentPage >= totalPages"
            @click="loadRiwayat(currentPage + 1)"
            class="px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 disabled:opacity-40 cursor-pointer"
          >
            Next &rarr;
          </button>
        </div>
      </div>
    </div>

    <!-- Kuitansi Modal Ready for Print -->
    <div
      v-if="showKuitansiModal && activeKuitansi"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fadeIn"
    >
      <div class="w-full max-w-lg p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl space-y-4">
        <!-- Printable Area -->
        <div id="printKuitansiArea" class="border border-zinc-300 dark:border-zinc-700 p-6 rounded-2xl bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 space-y-4 text-xs font-sans">
          <!-- Kop -->
          <div class="text-center border-b border-zinc-300 dark:border-zinc-700 pb-3">
            <h3 class="text-base font-extrabold uppercase tracking-wide">TPQ & PESANTREN NURUL MUN'IM</h3>
            <p class="text-[10px] text-zinc-500 dark:text-zinc-400">Dusun Tanjung Lor RT. 08 RW. 04 Desa Karang Anyar Paiton Probolinggo</p>
            <p class="text-[10px] font-bold text-emerald-600 mt-1 uppercase tracking-wider">KUITANSI PEMBAYARAN SPP</p>
          </div>

          <!-- Meta -->
          <div class="grid grid-cols-2 gap-2 text-[11px]">
            <div>
              <span class="text-zinc-400 block text-[10px]">No. Kuitansi</span>
              <span class="font-mono font-bold">{{ activeKuitansi.nomor_kuitansi }}</span>
            </div>
            <div class="text-right">
              <span class="text-zinc-400 block text-[10px]">Tanggal Bayar</span>
              <span class="font-bold">{{ formatTanggal(activeKuitansi.tanggal_bayar) }}</span>
            </div>
          </div>

          <!-- Rincian -->
          <div class="p-3 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 space-y-1.5">
            <div class="flex justify-between">
              <span class="text-zinc-500">Nama Santri:</span>
              <span class="font-bold">{{ activeKuitansi.siswa?.nama_lengkap }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-zinc-500">NISN / Jilid:</span>
              <span class="font-mono">{{ activeKuitansi.siswa?.nisn }} &bull; {{ activeKuitansi.siswa?.kelas?.nama_kelas }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-zinc-500">Pembayaran Periode:</span>
              <span class="font-semibold">{{ getNamaBulan(activeKuitansi.tagihan?.bulan) }} {{ activeKuitansi.tagihan?.tahun }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-zinc-500">Metode Bayar:</span>
              <span class="font-semibold">{{ activeKuitansi.metode_pembayaran }}</span>
            </div>
            <div class="flex justify-between pt-1 border-t border-zinc-200 dark:border-zinc-800 text-sm font-bold text-emerald-600">
              <span>Jumlah Disetor:</span>
              <span>Rp {{ formatRupiah(activeKuitansi.jumlah_bayar) }}</span>
            </div>
          </div>

          <!-- TTD Kasir -->
          <div class="pt-2 flex justify-between text-[10px] text-zinc-500">
            <div>
              <p>Status: <span class="font-bold text-emerald-600 uppercase">{{ activeKuitansi.status_transaksi }}</span></p>
            </div>
            <div class="text-right">
              <p>Petugas Kasir,</p>
              <p class="font-bold mt-6 text-zinc-900 dark:text-white">{{ activeKuitansi.petugas?.name || 'Ust. Bendahara' }}</p>
            </div>
          </div>
        </div>

        <!-- Buttons -->
        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            @click="showKuitansiModal = false"
            class="px-4 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 text-xs font-semibold cursor-pointer"
          >
            Tutup
          </button>
          <button
            @click="printKuitansi"
            class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20 cursor-pointer"
          >
            🖨️ Cetak Dokumen
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Batalkan Pembayaran (Audit Trail) -->
    <div
      v-if="showBatalModal && targetBatal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fadeIn"
    >
      <div class="w-full max-w-md p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-rose-100 dark:bg-rose-950/60 flex items-center justify-center text-rose-600 text-xl font-bold">
            ⚠️
          </div>
          <div>
            <h3 class="text-base font-bold text-zinc-900 dark:text-white">Batalkan Transaksi Pembayaran?</h3>
            <p class="text-xs text-zinc-500">Kuitansi: <span class="font-mono font-bold">{{ targetBatal.nomor_kuitansi }}</span></p>
          </div>
        </div>

        <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
          Pembatalan transaksi akan mengembalikan status tagihan SPP santri <b>{{ targetBatal.siswa?.nama_lengkap }}</b> menjadi belum lunas / kurang bayar.
        </p>

        <div>
          <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1">
            Alasan Pembatalan (Audit Trail) *
          </label>
          <textarea
            v-model="alasanBatal"
            rows="3"
            placeholder="Contoh: Salah input nominal / Santri batal transfer..."
            class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-rose-500 focus:outline-none"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            @click="closeBatalModal"
            class="px-4 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 text-xs font-semibold text-zinc-700 dark:text-zinc-300 cursor-pointer"
          >
            Batal
          </button>
          <button
            @click="submitBatalPembayaran"
            :disabled="!alasanBatal.trim() || isCancelling"
            class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold shadow-md shadow-rose-600/20 flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
          >
            <span v-if="isCancelling" class="animate-spin">⏳</span>
            <span>Konfirmasi Batalkan</span>
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

const namaBulanMap = {
  1: 'Januari', 2: 'Februari', 3: 'Maret', 4: 'April',
  5: 'Mei', 6: 'Juni', 7: 'Juli', 8: 'Agustus',
  9: 'September', 10: 'Oktober', 11: 'November', 12: 'Desember'
};

const kelasList = ref([]);
const pembayaranList = ref([]);
const isLoading = ref(false);

const currentPage = ref(1);
const totalPages = ref(1);
const totalRecords = ref(0);

const filters = ref({
  tanggal_mulai: '',
  tanggal_selesai: '',
  id_kelas: '',
  status_transaksi: '',
});

// Modal Kuitansi
const showKuitansiModal = ref(false);
const activeKuitansi = ref(null);

// Modal Pembatalan
const showBatalModal = ref(false);
const targetBatal = ref(null);
const alasanBatal = ref('');
const isCancelling = ref(false);

function formatRupiah(val) {
  if (!val) return '0';
  return Number(val).toLocaleString('id-ID');
}

function formatTanggal(str) {
  if (!str) return '-';
  const d = new Date(str);
  return d.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function getNamaBulan(b) {
  return namaBulanMap[b] || `Bulan ${b}`;
}

async function loadKelas() {
  try {
    const res = await api.get('/kelas');
    kelasList.value = res.data.data || [];
  } catch (err) {
    console.error(err);
  }
}

async function loadRiwayat(page = 1) {
  isLoading.value = true;
  currentPage.value = page;

  try {
    const res = await api.get('/spp/riwayat', {
      params: {
        page,
        ...filters.value,
      }
    });

    const data = res.data.data;
    pembayaranList.value = data.data || [];
    totalPages.value = data.last_page || 1;
    totalRecords.value = data.total || 0;
  } catch (err) {
    toast.error('Gagal mengambil data riwayat pembayaran SPP.');
  } finally {
    isLoading.value = false;
  }
}

function previewKuitansi(p) {
  activeKuitansi.value = p;
  showKuitansiModal.value = true;
}

function printKuitansi() {
  window.print();
}

function openBatalModal(p) {
  targetBatal.value = p;
  alasanBatal.value = '';
  showBatalModal.value = true;
}

function closeBatalModal() {
  showBatalModal.value = false;
  targetBatal.value = null;
  alasanBatal.value = '';
}

async function submitBatalPembayaran() {
  if (!targetBatal.value || !alasanBatal.value.trim()) return;

  isCancelling.value = true;
  try {
    const res = await api.post('/spp/batal-bayar', {
      id_pembayaran: targetBatal.value.id,
      alasan: alasanBatal.value.trim(),
    });

    toast.success(res.data.message || 'Pembayaran berhasil dibatalkan.');
    closeBatalModal();
    await loadRiwayat(currentPage.value);
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal membatalkan pembayaran.';
    toast.error(msg);
  } finally {
    isCancelling.value = false;
  }
}

function exportCsv() {
  if (pembayaranList.value.length === 0) return;

  const headers = ['No Kuitansi', 'Tanggal Bayar', 'NISN', 'Nama Santri', 'Kelas', 'Bulan Tagihan', 'Tahun', 'Nominal (Rp)', 'Metode', 'Petugas', 'Status'];
  const rows = pembayaranList.value.map(p => [
    `"${p.nomor_kuitansi}"`,
    `"${p.tanggal_bayar}"`,
    `"${p.siswa?.nisn || ''}"`,
    `"${p.siswa?.nama_lengkap || ''}"`,
    `"${p.siswa?.kelas?.nama_kelas || ''}"`,
    `"${getNamaBulan(p.tagihan?.bulan)}"`,
    `"${p.tagihan?.tahun || ''}"`,
    p.jumlah_bayar,
    `"${p.metode_pembayaran}"`,
    `"${p.petugas?.name || ''}"`,
    `"${p.status_transaksi}"`,
  ]);

  const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement('a');
  link.setAttribute('href', encodedUri);
  link.setAttribute('download', `Riwayat_SPP_${new Date().toISOString().slice(0, 10)}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

onMounted(() => {
  loadKelas();
  loadRiwayat(1);
});
</script>
