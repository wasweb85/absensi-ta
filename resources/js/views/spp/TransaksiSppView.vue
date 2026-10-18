<template>
  <div class="space-y-6 animate-fadeIn">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm print:hidden">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>💳</span>
          <span>Kasir & Pembayaran SPP Santri</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Proses penerimaan pembayaran SPP santri, cetak bukti kuitansi, dan pembaruan status lunas otomatis
        </p>
      </div>

      <router-link
        to="/spp/riwayat"
        class="px-4 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-xs font-semibold flex items-center gap-1.5 transition"
      >
        <span>🧾</span>
        <span>Riwayat Transaksi</span>
      </router-link>
    </div>

    <!-- Main Grid: Search & Payment Form (Print hidden) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 print:hidden">
      
      <!-- LEFT: Student Selector & Info (5 Cols) -->
      <div class="lg:col-span-5 space-y-5">
        
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
          <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">
            Pilih Santri yang Membayar
          </label>
          <div class="relative">
            <input
              v-model="studentSearch"
              type="text"
              placeholder="Ketik NISN atau Nama Santri..."
              @input="filterStudents"
              class="w-full px-4 py-3 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/60 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>

          <!-- Filtered Student Dropdown List -->
          <div v-if="suggestedStudents.length > 0 && !selectedStudent" class="mt-2 max-h-48 overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-800 divide-y divide-zinc-100 dark:divide-zinc-800 bg-white dark:bg-zinc-900 shadow-lg">
            <div
              v-for="s in suggestedStudents"
              :key="s.id"
              @click="chooseStudent(s)"
              class="p-2.5 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 cursor-pointer transition flex items-center justify-between text-xs"
            >
              <div>
                <p class="font-bold text-zinc-800 dark:text-zinc-200">{{ s.nama_lengkap }}</p>
                <p class="text-[10px] text-zinc-400 font-mono">NISN: {{ s.nisn }} &bull; {{ s.kelas?.nama_kelas || '-' }}</p>
              </div>
              <span class="text-[10px] font-bold text-emerald-600">Pilih &rarr;</span>
            </div>
          </div>

          <!-- Selected Student Profile Card -->
          <div v-if="selectedStudent" class="mt-5 p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/80 dark:border-zinc-700 text-xs space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Santri Terpilih</span>
              <button @click="resetSelectedStudent" class="text-rose-500 hover:underline font-bold text-[11px] cursor-pointer">Ganti</button>
            </div>
            <h4 class="text-base font-bold text-zinc-900 dark:text-white">{{ selectedStudent.nama_lengkap }}</h4>
            <div class="grid grid-cols-2 gap-2 text-[11px] pt-1">
              <div>
                <span class="text-zinc-400 block">NISN</span>
                <span class="font-bold font-mono">{{ selectedStudent.nisn }}</span>
              </div>
              <div>
                <span class="text-zinc-400 block">Jilid / Kelas</span>
                <span class="font-bold">{{ selectedStudent.kelas?.nama_kelas || '-' }}</span>
              </div>
              <div>
                <span class="text-zinc-400 block">Tarif SPP Bulanan</span>
                <span class="font-bold text-emerald-600">Rp {{ formatRupiah(selectedStudent.kelas?.nominal_spp) }}</span>
              </div>
              <div>
                <span class="text-zinc-400 block">Wali Santri</span>
                <span class="font-semibold">{{ selectedStudent.nama_ortu || '-' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Arrears Summary Card -->
        <div v-if="selectedStudent" class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400">Status Kewajiban SPP</h4>
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-bold"
              :class="tunggakanData.total_bulan === 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'"
            >
              {{ tunggakanData.total_bulan === 0 ? 'Lunas / Tidak Ada Tunggakan' : `${tunggakanData.total_bulan} Bulan Belum Lunas` }}
            </span>
          </div>

          <div class="p-4 rounded-2xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200/60 dark:border-rose-900/40 mb-3">
            <span class="text-[10px] text-zinc-500 dark:text-zinc-400 block">Total Nominal Tunggakan</span>
            <span class="text-xl font-extrabold text-rose-600 dark:text-rose-400">
              Rp {{ formatRupiah(tunggakanData.total_tunggakan) }}
            </span>
          </div>

          <!-- Unpaid Months List -->
          <div v-if="tunggakanData.daftar_tunggakan?.length > 0" class="space-y-1.5 max-h-48 overflow-y-auto text-xs pr-1">
            <div
              v-for="item in tunggakanData.daftar_tunggakan"
              :key="item.id_tagihan"
              @click="selectBill(item)"
              class="p-2.5 rounded-xl border transition cursor-pointer flex items-center justify-between"
              :class="selectedBill?.id_tagihan === item.id_tagihan ? 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-500 font-bold' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800 border-zinc-200 dark:border-zinc-800'"
            >
              <div>
                <p class="text-zinc-800 dark:text-zinc-200">{{ item.label }}</p>
                <p class="text-[10px] text-zinc-400">Tagihan: Rp {{ formatRupiah(item.nominal_tagihan) }}</p>
              </div>
              <div class="text-right">
                <span class="font-bold text-rose-600 dark:text-rose-400">Rp {{ formatRupiah(item.sisa) }}</span>
                <span class="block text-[9px] text-emerald-600 font-semibold">Klik untuk bayar &rarr;</span>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- RIGHT: Payment Input Form (7 Cols) -->
      <div class="lg:col-span-7">
        <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-5">
          <div class="border-b border-zinc-200 dark:border-zinc-800 pb-4">
            <h3 class="text-base font-bold text-zinc-900 dark:text-white">Form Input Pembayaran</h3>
            <p class="text-xs text-zinc-500">Isi detail pembayaran dan cetak kuitansi resmi</p>
          </div>

          <form @submit.prevent="submitPayment" class="space-y-4 text-xs">
            <!-- Selected Bill Info Box -->
            <div>
              <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Tagihan yang Dibayar *</label>
              <div v-if="selectedBill" class="p-3.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between">
                <div>
                  <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold uppercase">Periode Pembayaran</span>
                  <p class="text-sm font-bold text-zinc-900 dark:text-white">{{ selectedBill.label }}</p>
                </div>
                <div class="text-right">
                  <span class="text-[10px] text-zinc-500 block">Sisa Kewajiban</span>
                  <span class="text-sm font-bold text-rose-600">Rp {{ formatRupiah(selectedBill.sisa) }}</span>
                </div>
              </div>
              <div v-else class="p-4 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 text-center text-zinc-400">
                Pilih salah satu bulan tagihan di kolom sebelah kiri.
              </div>
            </div>

            <!-- Amount Input -->
            <div>
              <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Nominal yang Dibayarkan (Rp) *</label>
              <input
                v-model.number="paymentForm.jumlah_bayar"
                type="number"
                min="1"
                required
                placeholder="Cth: 50000"
                class="w-full px-4 py-3 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-sm font-bold text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              />
            </div>

            <!-- Payment Method & Notes -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Metode Pembayaran</label>
                <select
                  v-model="paymentForm.metode_pembayaran"
                  class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                >
                  <option value="Tunai">💵 Tunai (Cash)</option>
                  <option value="Transfer">🏦 Transfer Bank</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Catatan Transaksi</label>
                <input
                  v-model="paymentForm.catatan"
                  type="text"
                  placeholder="Keterangan opsional..."
                  class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                />
              </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
              <button
                type="submit"
                :disabled="!selectedBill || isSubmitting || paymentForm.jumlah_bayar <= 0"
                class="w-full py-3.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold text-sm shadow-lg shadow-emerald-600/25 transition cursor-pointer flex items-center justify-center gap-2"
              >
                <span v-if="isSubmitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span>{{ isSubmitting ? 'Menyimpan Pembayaran...' : 'Proses Pembayaran & Cetak Kuitansi' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>

    <!-- POP-UP MODAL KUITANSI PEMBAYARAN SPP (Printable) -->
    <div
      v-if="activeKuitansi"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs print:p-0 print:bg-white print:static"
    >
      <div class="w-full max-w-md rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-6 sm:p-8 shadow-2xl animate-slideUp print:border-none print:shadow-none print:p-0">
        
        <!-- Printable Area -->
        <div id="kuitansiArea" class="border border-zinc-300 dark:border-zinc-700 rounded-2xl p-6 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100">
          <!-- Receipt Header -->
          <div class="text-center border-b border-zinc-200 dark:border-zinc-800 pb-3 mb-4">
            <h4 class="text-base font-extrabold uppercase tracking-wide">KUITANSI PEMBAYARAN SPP</h4>
            <p class="text-[10px] text-zinc-500 font-mono mt-0.5">No: {{ activeKuitansi.nomor_kuitansi }}</p>
            <p class="text-[10px] text-zinc-400">{{ activeKuitansi.tanggal_bayar }}</p>
          </div>

          <!-- Receipt Details Table -->
          <div class="space-y-2 text-xs">
            <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
              <span class="text-zinc-400">Nama Santri:</span>
              <span class="font-bold">{{ activeKuitansi.nama_santri }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
              <span class="text-zinc-400">NISN / Kelas:</span>
              <span class="font-semibold">{{ activeKuitansi.nisn }} / {{ activeKuitansi.kelas }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
              <span class="text-zinc-400">Pembayaran Untuk:</span>
              <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ activeKuitansi.pembayaran_untuk }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
              <span class="text-zinc-400">Metode Bayar:</span>
              <span>{{ activeKuitansi.metode }}</span>
            </div>
            <div class="flex justify-between py-2 border-b-2 border-zinc-300 dark:border-zinc-700">
              <span class="font-bold text-sm">JUMLAH DIBAYAR:</span>
              <span class="font-extrabold text-base text-emerald-600">Rp {{ formatRupiah(activeKuitansi.jumlah_bayar) }}</span>
            </div>
            <div class="flex justify-between py-1">
              <span class="text-zinc-400">Status Tagihan:</span>
              <span class="font-bold uppercase text-[11px] text-emerald-600">{{ activeKuitansi.status_tagihan }}</span>
            </div>
          </div>

          <!-- Signatures Footer -->
          <div class="grid grid-cols-2 gap-4 text-center mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-800 text-[11px]">
            <div>
              <p class="text-zinc-400">Penyetor,</p>
              <div class="h-12"></div>
              <p class="font-semibold">(............................)</p>
            </div>
            <div>
              <p class="text-zinc-400">Petugas Kasir,</p>
              <div class="h-12"></div>
              <p class="font-bold underline">Ustadz / Ustadzah</p>
            </div>
          </div>
        </div>

        <!-- Modal Actions (Hidden when printing) -->
        <div class="flex items-center justify-end gap-2 mt-5 print:hidden">
          <button
            @click="activeKuitansi = null"
            class="px-4 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 text-xs font-semibold cursor-pointer"
          >
            Tutup
          </button>
          <button
            @click="printKuitansi"
            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer"
          >
            <span>🖨</span>
            <span>Cetak Kuitansi</span>
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

const allStudents = ref([]);
const studentSearch = ref('');
const suggestedStudents = ref([]);
const selectedStudent = ref(null);
const tunggakanData = ref({ total_tunggakan: 0, total_bulan: 0, daftar_tunggakan: [] });
const selectedBill = ref(null);
const isSubmitting = ref(false);
const activeKuitansi = ref(null);

const paymentForm = ref({
  jumlah_bayar: 0,
  metode_pembayaran: 'Tunai',
  catatan: '',
});

const formatRupiah = (val) => {
  return new Intl.NumberFormat('id-ID').format(val || 0);
};

const loadStudents = async () => {
  try {
    const res = await api.get('/siswa?status=aktif');
    if (res.data.success) {
      allStudents.value = res.data.data;
    }
  } catch (e) {}
};

const filterStudents = () => {
  const q = studentSearch.value.trim().toLowerCase();
  if (!q) {
    suggestedStudents.value = [];
    return;
  }
  suggestedStudents.value = allStudents.value.filter(s =>
    s.nama_lengkap.toLowerCase().includes(q) || s.nisn.toLowerCase().includes(q)
  ).slice(0, 8);
};

const chooseStudent = async (student) => {
  selectedStudent.value = student;
  studentSearch.value = student.nama_lengkap;
  suggestedStudents.value = [];
  selectedBill.value = null;
  paymentForm.value.jumlah_bayar = 0;

  try {
    const res = await api.get(`/spp/santri/${student.id}/tunggakan`);
    if (res.data.success) {
      tunggakanData.value = res.data.data;
      if (tunggakanData.value.daftar_tunggakan.length > 0) {
        selectBill(tunggakanData.value.daftar_tunggakan[0]);
      }
    }
  } catch (e) {
    toast.error('Gagal mengambil data tunggakan.');
  }
};

const resetSelectedStudent = () => {
  selectedStudent.value = null;
  studentSearch.value = '';
  selectedBill.value = null;
  tunggakanData.value = { total_tunggakan: 0, total_bulan: 0, daftar_tunggakan: [] };
};

const selectBill = (bill) => {
  selectedBill.value = bill;
  paymentForm.value.jumlah_bayar = bill.sisa;
};

const submitPayment = async () => {
  if (!selectedBill.value) return;
  isSubmitting.value = true;

  try {
    const res = await api.post('/spp/bayar', {
      id_tagihan: selectedBill.value.id_tagihan,
      jumlah_bayar: paymentForm.value.jumlah_bayar,
      metode_pembayaran: paymentForm.value.metode_pembayaran,
      catatan: paymentForm.value.catatan,
    });

    if (res.data.success) {
      toast.success(res.data.message);
      activeKuitansi.value = res.data.kuitansi;
      // Refresh tunggakan
      if (selectedStudent.value) {
        chooseStudent(selectedStudent.value);
      }
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan pembayaran.');
  } finally {
    isSubmitting.value = false;
  }
};

const printKuitansi = () => {
  window.print();
};

onMounted(() => {
  loadStudents();
});
</script>
