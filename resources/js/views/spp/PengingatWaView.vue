<template>
  <div class="space-y-4 animate-fadeIn">
    <!-- Header Page: Clean, Proportional & Tanpa Card Berlebihan -->
    <div class="flex flex-wrap items-center justify-between gap-2.5 print:hidden">
      <div>
        <h2 class="text-sm sm:text-base md:text-lg font-bold tracking-tight text-zinc-900 dark:text-white">
          Himbauan WhatsApp SPP
        </h2>
      </div>

      <div class="flex items-center gap-1.5 sm:gap-2">
        <button
          @click="testGateway"
          :disabled="isTesting"
          class="h-8 px-2.5 sm:px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50 shadow-sm"
        >
          <BaseIcon v-if="isTesting" name="refresh" class="w-3.5 h-3.5 animate-spin text-emerald-600" />
          <BaseIcon v-else name="antenna" class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
          <span>Tes Gateway</span>
        </button>

        <router-link
          to="/spp/tagihan"
          class="h-8 px-2.5 sm:px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 text-xs font-semibold hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition flex items-center gap-1.5 shadow-sm"
        >
          <BaseIcon name="document-text" class="w-3.5 h-3.5 text-zinc-400" />
          <span>Tagihan &rarr;</span>
        </router-link>
      </div>
    </div>

    <!-- Navigation Tabs: Compact & Proporsional -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 space-x-1 sm:space-x-2">
      <button
        @click="activeTab = 'grup'"
        :class="[
          activeTab === 'grup'
            ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400 font-bold border-b-2'
            : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 font-medium'
        ]"
        class="pb-2.5 px-3 text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 transition cursor-pointer"
      >
        <BaseIcon name="guru" class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
        <span>Himbauan Grup Jilid</span>
      </button>

      <button
        @click="activeTab = 'wali_jilid'"
        :class="[
          activeTab === 'wali_jilid'
            ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400 font-bold border-b-2'
            : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 font-medium'
        ]"
        class="pb-2.5 px-3 text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 transition cursor-pointer"
      >
        <BaseIcon name="user" class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
        <span>Rekap ke Wali Jilid</span>
      </button>
    </div>

    <!-- TAB 1: BROADCAST GRUP JILID -->
    <div v-if="activeTab === 'grup'" class="grid grid-cols-1 lg:grid-cols-12 gap-3 sm:gap-4">
      <div class="lg:col-span-5 space-y-3">
        <div class="p-3.5 sm:p-4 rounded-xl sm:rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-2.5 sm:space-y-3">
          <h3 class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
            <BaseIcon name="filter" class="w-3.5 h-3.5" />
            <span>Pilih Jilid & Periode</span>
          </h3>

          <div>
            <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 mb-1">
              Jilid / Kelas *
            </label>
            <select
              v-model="grupKelasId"
              @change="fetchGrupPreview"
              class="w-full h-9 px-2.5 sm:px-3 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            >
              <option value="">-- Pilih Kelas --</option>
              <option v-for="k in kelasList" :key="k.id" :value="k.id">
                {{ k.nama_kelas }} (Rp {{ formatRupiah(k.nominal_spp) }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-2 sm:gap-3 text-xs">
            <div>
              <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 mb-1">
                Bulan
              </label>
              <select
                v-model="grupBulan"
                @change="fetchGrupPreview"
                class="w-full h-9 px-2.5 sm:px-3 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              >
                <option v-for="(nama, num) in bulanList" :key="num" :value="num">{{ nama }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 mb-1">
                Tahun
              </label>
              <select
                v-model="grupTahun"
                @change="fetchGrupPreview"
                class="w-full h-9 px-2.5 sm:px-3 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              >
                <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-7 space-y-3">
        <div class="p-3.5 sm:p-4 rounded-xl sm:rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-2.5 sm:space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
              <BaseIcon name="message-square" class="w-3.5 h-3.5" />
              <span>Preview Pesan Himbauan Grup Jilid</span>
            </h3>
          </div>

          <div class="p-3 sm:p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/70 border border-zinc-200/80 dark:border-zinc-700/60 font-mono text-xs text-zinc-800 dark:text-zinc-200 whitespace-pre-wrap leading-relaxed min-h-[160px] max-h-[380px] overflow-y-auto">
            <span v-if="isLoadingGrup" class="text-zinc-400 italic">Menyiapkan pengumuman grup...</span>
            <span v-else-if="!grupMessage" class="text-zinc-400 italic">Pilih Jilid / Kelas untuk melihat preview pesan siaran grup.</span>
            <span v-else>{{ grupMessage }}</span>
          </div>

          <div class="flex items-center gap-2 pt-1">
            <button
              @click="copyText(grupMessage)"
              :disabled="!grupMessage"
              class="h-8 px-3 sm:px-3.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
            >
              <BaseIcon name="copy" class="w-3.5 h-3.5" />
              <span>Salin Pesan untuk Grup WA</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: REKAP KE WALI JILID -->
    <div v-else-if="activeTab === 'wali_jilid'" class="grid grid-cols-1 lg:grid-cols-12 gap-3 sm:gap-4">
      <div class="lg:col-span-5 space-y-3">
        <div class="p-3.5 sm:p-4 rounded-xl sm:rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-2.5 sm:space-y-3">
          <h3 class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
            <BaseIcon name="filter" class="w-3.5 h-3.5" />
            <span>Pilih Jilid & Wali Kelas</span>
          </h3>

          <div>
            <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 mb-1">
              Jilid / Kelas *
            </label>
            <select
              v-model="waliKelasId"
              @change="fetchWaliPreview"
              class="w-full h-9 px-2.5 sm:px-3 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            >
              <option value="">-- Pilih Kelas --</option>
              <option v-for="k in kelasList" :key="k.id" :value="k.id">
                {{ k.nama_kelas }} (Wali: {{ k.wali_kelas || '-' }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-2 sm:gap-3 text-xs">
            <div>
              <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 mb-1">
                Bulan
              </label>
              <select
                v-model="waliBulan"
                @change="fetchWaliPreview"
                class="w-full h-9 px-2.5 sm:px-3 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              >
                <option v-for="(nama, num) in bulanList" :key="num" :value="num">{{ nama }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 mb-1">
                Tahun
              </label>
              <select
                v-model="waliTahun"
                @change="fetchWaliPreview"
                class="w-full h-9 px-2.5 sm:px-3 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              >
                <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-7 space-y-3">
        <div class="p-3.5 sm:p-4 rounded-xl sm:rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-2.5 sm:space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
              <BaseIcon name="message-square" class="w-3.5 h-3.5" />
              <span>Preview Laporan Rekap ke Wali Jilid</span>
            </h3>
            <span v-if="waliTargetPhone" class="text-[11px] font-mono font-semibold text-zinc-500">
              WA: {{ waliTargetPhone }}
            </span>
          </div>

          <div class="p-3 sm:p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/70 border border-zinc-200/80 dark:border-zinc-700/60 font-mono text-xs text-zinc-800 dark:text-zinc-200 whitespace-pre-wrap leading-relaxed min-h-[160px] max-h-[380px] overflow-y-auto">
            <span v-if="isLoadingWali" class="text-zinc-400 italic">Mengkalkulasi rekap santri menunggak...</span>
            <span v-else-if="!waliMessage" class="text-zinc-400 italic">Pilih Jilid / Kelas untuk melihat rekap santri menunggak yang siap dikirimkan ke Ustadz/Ustadzah Wali.</span>
            <span v-else>{{ waliMessage }}</span>
          </div>

          <div class="flex flex-wrap items-center gap-2 pt-1">
            <button
              @click="sendViaFonnte(waliTargetPhone, waliMessage)"
              :disabled="!waliMessage || !waliTargetPhone || isSending"
              class="h-8 px-3 sm:px-3.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <BaseIcon v-if="isSending" name="refresh" class="w-3.5 h-3.5 animate-spin" />
              <BaseIcon v-else name="send" class="w-3.5 h-3.5" />
              <span>Kirim ke Wali (Fonnte API)</span>
            </button>

            <a
              v-if="waliWaMeLink"
              :href="waliWaMeLink"
              target="_blank"
              class="h-8 px-3 sm:px-3.5 rounded-lg bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 hover:opacity-90 font-bold text-xs transition flex items-center gap-1.5 shadow-sm"
            >
              <BaseIcon name="whatsapp" class="w-3.5 h-3.5 text-emerald-500" />
              <span>Buka di WA</span>
            </a>

            <button
              @click="copyText(waliMessage)"
              :disabled="!waliMessage"
              class="h-8 px-2.5 sm:px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40 shadow-sm"
            >
              <BaseIcon name="copy" class="w-3.5 h-3.5" />
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

const activeTab = ref('grup');
const kelasList = ref([]);

// Tab 1: Broadcast Grup
const currDate = new Date();
const grupKelasId = ref('');
const grupBulan = ref(currDate.getMonth() + 1);
const grupTahun = ref(currDate.getFullYear());
const grupMessage = ref('');
const isLoadingGrup = ref(false);

// Tab 2: Rekap Wali
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

const sanitizeWaText = (text) => {
  if (!text) return '';
  return text.replace(/\*(?:E\s*ABSENSI|E-ABSENSI|Sistem Terpadu[^*]*)\*/gi, "*TPQ Nurul Mun'im*")
             .replace(/kami dari[^*]*\*TPQ/gi, "Kami dari pihak *TPQ");
};

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
    grupMessage.value = sanitizeWaText(res.data.message || '');
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
    waliMessage.value = sanitizeWaText(res.data.message || '');
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
});
</script>
