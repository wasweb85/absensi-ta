<template>
  <div class="space-y-6 animate-fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>📢</span>
          <span>Broadcast & Pengingat WhatsApp SPP</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Kirim notifikasi tagihan SPP langsung ke nomor WhatsApp orang tua atau wali kelas via Gateway Fonnte & tautan wa.me
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="testGateway"
          :disabled="isTesting"
          class="px-3.5 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
        >
          <span v-if="isTesting" class="animate-spin">⏳</span>
          <span v-else>📡</span>
          <span>Tes Gateway Fonnte</span>
        </button>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 space-x-2">
      <button
        @click="activeTab = 'japri'"
        :class="[
          activeTab === 'japri'
            ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400 font-bold border-b-2'
            : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 font-medium'
        ]"
        class="pb-3 px-4 text-xs sm:text-sm flex items-center gap-2 transition cursor-pointer"
      >
        <span>📱</span>
        <span>Japri Orang Tua Santri</span>
      </button>

      <button
        @click="activeTab = 'grup'"
        :class="[
          activeTab === 'grup'
            ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400 font-bold border-b-2'
            : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 font-medium'
        ]"
        class="pb-3 px-4 text-xs sm:text-sm flex items-center gap-2 transition cursor-pointer"
      >
        <span>👥</span>
        <span>Himbauan Grup Jilid</span>
      </button>

      <button
        @click="activeTab = 'wali_jilid'"
        :class="[
          activeTab === 'wali_jilid'
            ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400 font-bold border-b-2'
            : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 font-medium'
        ]"
        class="pb-3 px-4 text-xs sm:text-sm flex items-center gap-2 transition cursor-pointer"
      >
        <span>👨‍🏫</span>
        <span>Rekap ke Wali Jilid</span>
      </button>
    </div>

    <!-- TAB 1: JAPRI ORTU SANTRI -->
    <div v-if="activeTab === 'japri'" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Selector & Student Info (5 cols) -->
      <div class="lg:col-span-5 space-y-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-4">
          <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
            Pilih Santri Penerima
          </h3>

          <div>
            <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">Cari Nama / NISN Santri</label>
            <input
              v-model="japriSearch"
              type="text"
              placeholder="Ketik nama atau NISN..."
              @input="filterJapriStudents"
              class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>

          <!-- Dropdown Matches -->
          <div v-if="suggestedJapriStudents.length > 0 && !selectedJapriStudent" class="max-h-48 overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-800 divide-y divide-zinc-100 dark:divide-zinc-800 bg-white dark:bg-zinc-900 shadow-lg text-xs">
            <div
              v-for="s in suggestedJapriStudents"
              :key="s.id"
              @click="chooseJapriStudent(s)"
              class="p-2.5 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 cursor-pointer transition flex items-center justify-between"
            >
              <div>
                <p class="font-bold text-zinc-900 dark:text-white">{{ s.nama_lengkap }}</p>
                <p class="text-[10px] text-zinc-400 font-mono">NISN: {{ s.nisn }} &bull; {{ s.kelas?.nama_kelas || '-' }}</p>
              </div>
              <span class="text-[10px] font-bold text-emerald-600">Pilih &rarr;</span>
            </div>
          </div>

          <!-- Profile Card -->
          <div v-if="selectedJapriStudent" class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-700 space-y-2 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Santri Terpilih</span>
              <button @click="resetJapriStudent" class="text-rose-500 hover:underline font-bold text-[11px] cursor-pointer">Ganti</button>
            </div>
            <h4 class="text-sm font-bold text-zinc-900 dark:text-white">{{ selectedJapriStudent.nama_lengkap }}</h4>
            <div class="grid grid-cols-2 gap-2 text-[11px] pt-1">
              <div>
                <span class="text-zinc-400 block">Jilid / Kelas</span>
                <span class="font-bold">{{ selectedJapriStudent.kelas?.nama_kelas || '-' }}</span>
              </div>
              <div>
                <span class="text-zinc-400 block">No. WhatsApp Ortu</span>
                <span class="font-bold font-mono" :class="selectedJapriStudent.no_hp_ortu ? 'text-zinc-900 dark:text-zinc-100' : 'text-rose-500'">
                  {{ selectedJapriStudent.no_hp_ortu || 'Belum diisi' }}
                </span>
              </div>
            </div>

            <!-- Tunggakan Badge -->
            <div v-if="japriTunggakan" class="mt-2 pt-2 border-t border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
              <span class="text-[11px] text-zinc-500">Total Tunggakan:</span>
              <span class="text-xs font-bold" :class="japriTunggakan.total_tunggakan > 0 ? 'text-rose-600' : 'text-emerald-600'">
                Rp {{ formatRupiah(japriTunggakan.total_tunggakan) }} ({{ japriTunggakan.total_bulan }} Bulan)
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Preview & Send Actions (7 cols) -->
      <div class="lg:col-span-7 space-y-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
              Preview Pesan WhatsApp
            </h3>
            <span v-if="japriTargetPhone" class="text-[11px] font-mono font-semibold text-zinc-500">
              Tujuan: {{ japriTargetPhone }}
            </span>
          </div>

          <!-- Message Box mimicking WhatsApp chat bubble -->
          <div class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/50 font-mono text-xs text-zinc-800 dark:text-zinc-200 whitespace-pre-wrap leading-relaxed min-h-[160px]">
            <span v-if="isLoadingPreview" class="text-zinc-400 italic">Membuat draft pesan WhatsApp...</span>
            <span v-else-if="!japriMessage" class="text-zinc-400 italic">Silakan pilih santri terlebih dahulu untuk memunculkan preview pesan pengingat SPP.</span>
            <span v-else>{{ japriMessage }}</span>
          </div>

          <!-- Actions Row -->
          <div class="flex flex-wrap items-center gap-2 pt-2">
            <button
              @click="sendViaFonnte(japriTargetPhone, japriMessage)"
              :disabled="!japriMessage || !japriTargetPhone || isSending"
              class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <span v-if="isSending" class="animate-spin">⏳</span>
              <span v-else>🚀</span>
              <span>Kirim Otomatis (Fonnte API)</span>
            </button>

            <a
              v-if="japriWaMeLink"
              :href="japriWaMeLink"
              target="_blank"
              class="px-4 py-2.5 rounded-xl bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 hover:opacity-90 font-bold text-xs transition flex items-center gap-1.5"
            >
              <span>💬</span>
              <span>Buka di WA Web / App</span>
            </a>

            <button
              @click="copyText(japriMessage)"
              :disabled="!japriMessage"
              class="px-3.5 py-2.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
            >
              <span>📋</span>
              <span>Salin Teks</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: BROADCAST GRUP JILID -->
    <div v-else-if="activeTab === 'grup'" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <div class="lg:col-span-5 space-y-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-4">
          <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
            Pilih Jilid & Periode
          </h3>

          <div>
            <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">Jilid / Kelas *</label>
            <select
              v-model="grupKelasId"
              @change="fetchGrupPreview"
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            >
              <option value="">-- Pilih Kelas --</option>
              <option v-for="k in kelasList" :key="k.id" :value="k.id">
                {{ k.nama_kelas }} (Rp {{ formatRupiah(k.nominal_spp) }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <label class="block font-medium text-zinc-600 dark:text-zinc-400 mb-1">Bulan</label>
              <select
                v-model="grupBulan"
                @change="fetchGrupPreview"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              >
                <option v-for="(nama, num) in bulanList" :key="num" :value="num">{{ nama }}</option>
              </select>
            </div>
            <div>
              <label class="block font-medium text-zinc-600 dark:text-zinc-400 mb-1">Tahun</label>
              <select
                v-model="grupTahun"
                @change="fetchGrupPreview"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              >
                <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-7 space-y-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-3">
          <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
            Preview Pesan Himbauan Grup Jilid
          </h3>

          <div class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/50 font-mono text-xs text-zinc-800 dark:text-zinc-200 whitespace-pre-wrap leading-relaxed min-h-[160px]">
            <span v-if="isLoadingGrup" class="text-zinc-400 italic">Menyiapkan pengumuman grup...</span>
            <span v-else-if="!grupMessage" class="text-zinc-400 italic">Pilih Jilid/Kelas untuk melihat preview pesan siaran grup.</span>
            <span v-else>{{ grupMessage }}</span>
          </div>

          <div class="flex items-center gap-2 pt-2">
            <button
              @click="copyText(grupMessage)"
              :disabled="!grupMessage"
              class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
            >
              <span>📋</span>
              <span>Salin Pesan untuk Grup WA</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 3: REKAP KE WALI JILID -->
    <div v-else-if="activeTab === 'wali_jilid'" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <div class="lg:col-span-5 space-y-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-4">
          <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
            Pilih Jilid & Wali Kelas
          </h3>

          <div>
            <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">Jilid / Kelas *</label>
            <select
              v-model="waliKelasId"
              @change="fetchWaliPreview"
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            >
              <option value="">-- Pilih Kelas --</option>
              <option v-for="k in kelasList" :key="k.id" :value="k.id">
                {{ k.nama_kelas }} (Wali: {{ k.wali_kelas || '-' }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <label class="block font-medium text-zinc-600 dark:text-zinc-400 mb-1">Bulan</label>
              <select
                v-model="waliBulan"
                @change="fetchWaliPreview"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              >
                <option v-for="(nama, num) in bulanList" :key="num" :value="num">{{ nama }}</option>
              </select>
            </div>
            <div>
              <label class="block font-medium text-zinc-600 dark:text-zinc-400 mb-1">Tahun</label>
              <select
                v-model="waliTahun"
                @change="fetchWaliPreview"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              >
                <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-7 space-y-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
              Preview Laporan Rekap ke Wali Jilid
            </h3>
            <span v-if="waliTargetPhone" class="text-[11px] font-mono font-semibold text-zinc-500">
              WA Wali: {{ waliTargetPhone }}
            </span>
          </div>

          <div class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/50 font-mono text-xs text-zinc-800 dark:text-zinc-200 whitespace-pre-wrap leading-relaxed min-h-[160px]">
            <span v-if="isLoadingWali" class="text-zinc-400 italic">Mengkalkulasi rekap santri menunggak...</span>
            <span v-else-if="!waliMessage" class="text-zinc-400 italic">Pilih Jilid/Kelas untuk melihat rekap santri menunggak yang siap dikirimkan ke Ustadz/Ustadzah Wali.</span>
            <span v-else>{{ waliMessage }}</span>
          </div>

          <div class="flex flex-wrap items-center gap-2 pt-2">
            <button
              @click="sendViaFonnte(waliTargetPhone, waliMessage)"
              :disabled="!waliMessage || !waliTargetPhone || isSending"
              class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <span v-if="isSending" class="animate-spin">⏳</span>
              <span v-else>🚀</span>
              <span>Kirim ke Wali (Fonnte API)</span>
            </button>

            <a
              v-if="waliWaMeLink"
              :href="waliWaMeLink"
              target="_blank"
              class="px-4 py-2.5 rounded-xl bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 hover:opacity-90 font-bold text-xs transition flex items-center gap-1.5"
            >
              <span>💬</span>
              <span>Buka di WA Web / App</span>
            </a>

            <button
              @click="copyText(waliMessage)"
              :disabled="!waliMessage"
              class="px-3.5 py-2.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
            >
              <span>📋</span>
              <span>Salin Teks</span>
            </button>
          </div>
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

const activeTab = ref('japri');
const kelasList = ref([]);
const allStudents = ref([]);

// Tab 1: Japri
const japriSearch = ref('');
const suggestedJapriStudents = ref([]);
const selectedJapriStudent = ref(null);
const japriTunggakan = ref(null);
const japriMessage = ref('');
const japriTargetPhone = ref('');
const japriWaMeLink = ref('');
const isLoadingPreview = ref(false);

// Tab 2: Broadcast Grup
const currDate = new Date();
const grupKelasId = ref('');
const grupBulan = ref(currDate.getMonth() + 1);
const grupTahun = ref(currDate.getFullYear());
const grupMessage = ref('');
const isLoadingGrup = ref(false);

// Tab 3: Rekap Wali
const waliKelasId = ref('');
const waliBulan = ref(currDate.getMonth() + 1);
const waliTahun = ref(currDate.getFullYear());
const waliMessage = ref('');
const waliTargetPhone = ref('');
const waliWaMeLink = ref('');
const isLoadingWali = ref(false);

// General
const isTesting = ref(false);
const isSending = ref(false);

function formatRupiah(val) {
  if (!val) return '0';
  return Number(val).toLocaleString('id-ID');
}

async function loadKelas() {
  try {
    const res = await api.get('/kelas');
    kelasList.value = res.data.data || [];
  } catch (err) {
    toast.error('Gagal memuat master kelas.');
  }
}

async function loadAllStudents() {
  try {
    const res = await api.get('/siswa', { params: { per_page: 500, status: 'aktif' } });
    allStudents.value = res.data.data?.data || res.data.data || [];
  } catch (err) {
    console.error(err);
  }
}

function filterJapriStudents() {
  const q = japriSearch.value.trim().toLowerCase();
  if (q.length < 2) {
    suggestedJapriStudents.value = [];
    return;
  }
  suggestedJapriStudents.value = allStudents.value
    .filter(s => s.nama_lengkap.toLowerCase().includes(q) || s.nisn.toLowerCase().includes(q))
    .slice(0, 10);
}

async function chooseJapriStudent(s) {
  selectedJapriStudent.value = s;
  suggestedJapriStudents.value = [];
  japriSearch.value = s.nama_lengkap;
  isLoadingPreview.value = true;

  try {
    const resTunggakan = await api.get(`/spp/santri/${s.id}/tunggakan`);
    japriTunggakan.value = resTunggakan.data.data;

    const resPreview = await api.get('/wa/preview', {
      params: {
        type: 'japri',
        id_siswa: s.id,
      }
    });

    japriMessage.value = resPreview.data.message || '';
    japriTargetPhone.value = resPreview.data.target_phone || '';
    japriWaMeLink.value = resPreview.data.wa_me_link || '';
  } catch (err) {
    toast.error('Gagal memuat preview pesan WA santri.');
  } finally {
    isLoadingPreview.value = false;
  }
}

function resetJapriStudent() {
  selectedJapriStudent.value = null;
  japriSearch.value = '';
  japriTunggakan.value = null;
  japriMessage.value = '';
  japriTargetPhone.value = '';
  japriWaMeLink.value = '';
}

async function fetchGrupPreview() {
  if (!grupKelasId.value) {
    grupMessage.value = '';
    return;
  }

  isLoadingGrup.value = true;
  try {
    const res = await api.get('/wa/preview', {
      params: {
        type: 'grup',
        id_kelas: grupKelasId.value,
        bulan: grupBulan.value,
        tahun: grupTahun.value,
      }
    });
    grupMessage.value = res.data.message || '';
  } catch (err) {
    toast.error('Gagal membuat preview himbauan grup.');
  } finally {
    isLoadingGrup.value = false;
  }
}

async function fetchWaliPreview() {
  if (!waliKelasId.value) {
    waliMessage.value = '';
    waliTargetPhone.value = '';
    waliWaMeLink.value = '';
    return;
  }

  isLoadingWali.value = true;
  try {
    const res = await api.get('/wa/preview', {
      params: {
        type: 'wali_jilid',
        id_kelas: waliKelasId.value,
        bulan: waliBulan.value,
        tahun: waliTahun.value,
      }
    });
    waliMessage.value = res.data.message || '';
    waliTargetPhone.value = res.data.target_phone || '';
    waliWaMeLink.value = res.data.wa_me_link || '';
  } catch (err) {
    toast.error('Gagal membuat rekap wali jilid.');
  } finally {
    isLoadingWali.value = false;
  }
}

async function sendViaFonnte(phone, message) {
  if (!phone) {
    toast.warning('Nomor WhatsApp tujuan kosong atau belum didaftarkan.');
    return;
  }

  isSending.value = true;
  try {
    const res = await api.post('/wa/send', { phone, message });
    if (res.data.success) {
      toast.success(res.data.message || 'Pesan berhasil dikirim via Fonnte Gateway!');
    } else {
      toast.error(res.data.message || 'Gagal mengirim pesan.');
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal mengirim pesan via Fonnte Gateway. Periksa token Fonnte pada menu Pengaturan.';
    toast.error(msg);
  } finally {
    isSending.value = false;
  }
}

async function testGateway() {
  isTesting.value = true;
  try {
    const res = await api.post('/wa/test');
    toast.success(res.data.message || 'Gateway Fonnte terhubung.');
  } catch (err) {
    const msg = err.response?.data?.message || 'Gateway belum terkonfigurasi. Silakan isi token Fonnte di menu Pengaturan.';
    toast.error(msg);
  } finally {
    isTesting.value = false;
  }
}

function copyText(text) {
  if (!text) return;
  navigator.clipboard.writeText(text);
  toast.success('Pesan disalin ke clipboard!');
}

onMounted(() => {
  loadKelas();
  loadAllStudents();
});
</script>
