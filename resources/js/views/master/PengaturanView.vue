<template>
  <div class="space-y-6 animate-fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>⚙️</span>
          <span>Pengaturan Sistem Terpadu</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Konfigurasi jam operasional absensi, profil lembaga, dan integrasi WhatsApp Gateway Fonnte
        </p>
      </div>

      <button
        @click="saveSettings"
        :disabled="isSaving"
        class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/20 transition flex items-center gap-2 cursor-pointer disabled:opacity-40"
      >
        <span v-if="isSaving" class="animate-spin">⏳</span>
        <span v-else>💾</span>
        <span>Simpan Semua Pengaturan</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="p-12 text-center text-zinc-400">
      <p class="text-3xl mb-2 animate-spin">⏳</p>
      <p class="text-sm font-medium">Memuat pengaturan sistem...</p>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      
      <!-- Card 1: Profil Lembaga -->
      <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-4">
        <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-2">
          <span>🏢</span>
          <span>1. Profil & Identitas Lembaga</span>
        </h3>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Nama Instansi / TPQ / Lembaga *</label>
            <input
              v-model="settings.nama_instansi"
              type="text"
              placeholder="Contoh: TPQ & Pesantren Nurul Mun'im"
              class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Tahun Ajaran Aktif</label>
            <input
              v-model="settings.tahun_ajaran_aktif"
              type="text"
              placeholder="Contoh: 2026/2027"
              class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>
        </div>
      </div>

      <!-- Card 2: Jam Operasional & Batas Terlambat -->
      <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-4">
        <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-2">
          <span>⏰</span>
          <span>2. Jam Operasional Absensi</span>
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Jam Masuk (Hadir)</label>
            <input
              v-model="settings.jam_masuk"
              type="time"
              class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
            <p class="text-[10px] text-zinc-400 mt-1">Batas awal jam tap masuk</p>
          </div>

          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Batas Terlambat</label>
            <input
              v-model="settings.jam_terlambat"
              type="time"
              class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
            <p class="text-[10px] text-zinc-400 mt-1">Tap lewat jam ini = 'Terlambat'</p>
          </div>

          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Jam Pulang</label>
            <input
              v-model="settings.jam_pulang"
              type="time"
              class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
            <p class="text-[10px] text-zinc-400 mt-1">Waktu tap kepulangan</p>
          </div>
        </div>

        <div>
          <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1.5 text-xs">Hari Aktif Kegiatan</label>
          <div class="flex flex-wrap gap-2 text-xs">
            <label
              v-for="day in ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']"
              :key="day"
              class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 cursor-pointer"
            >
              <input
                type="checkbox"
                :value="day"
                v-model="activeDays"
                class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 w-3.5 h-3.5"
              />
              <span class="text-zinc-800 dark:text-zinc-200">{{ day }}</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Card 3: WhatsApp Gateway Fonnte -->
      <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-2">
            <span>💬</span>
            <span>3. WhatsApp Gateway (Fonnte API)</span>
          </h3>

          <span
            v-if="settings.fonnte_token"
            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
          >
            Token Terisi
          </span>
          <span
            v-else
            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
          >
            Belum Diisi
          </span>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">API Token Fonnte</label>
            <input
              v-model="settings.fonnte_token"
              type="password"
              placeholder="Masukkan token dari fonnte.com"
              class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-mono text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
            <p class="text-[10px] text-zinc-400 mt-1">
              Dapatkan token gratis atau berbayar dengan mendaftar di <b>fonnte.com</b> dan sambungkan perangkat WhatsApp Anda.
            </p>
          </div>

          <div class="pt-1">
            <button
              type="button"
              @click="testWaGateway"
              :disabled="!settings.fonnte_token || isTestingWa"
              class="px-3.5 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
            >
              <span v-if="isTestingWa" class="animate-spin">⏳</span>
              <span v-else>📡</span>
              <span>Uji Koneksi Gateway Fonnte</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Card 4: Notifikasi Telegram (Opsional) -->
      <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-4">
        <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-2">
          <span>✈️</span>
          <span>4. Bot Telegram (Opsional)</span>
        </h3>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Bot Token</label>
            <input
              v-model="settings.telegram_bot_token"
              type="password"
              placeholder="Contoh: 123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
              class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-mono text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Chat ID Admin / Grup</label>
            <input
              v-model="settings.telegram_chat_id"
              type="text"
              placeholder="Contoh: -100123456789 atau ID Chat Anda"
              class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-mono text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
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

const isLoading = ref(false);
const isSaving = ref(false);
const isTestingWa = ref(false);

const settings = ref({
  nama_instansi: "Sistem Terpadu Absensi & SPP Santri",
  tahun_ajaran_aktif: "2026/2027",
  jam_masuk: "07:00",
  jam_terlambat: "07:15",
  jam_pulang: "12:00",
  hari_aktif: "Senin,Selasa,Rabu,Kamis,Jumat,Sabtu",
  fonnte_token: "",
  telegram_bot_token: "",
  telegram_chat_id: "",
});

const activeDays = ref(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']);

async function loadSettings() {
  isLoading.value = true;
  try {
    const res = await api.get('/pengaturan');
    const data = res.data.data || {};
    settings.value = {
      ...settings.value,
      ...data,
    };

    if (data.hari_aktif) {
      activeDays.value = data.hari_aktif.split(',').map(s => s.trim()).filter(Boolean);
    }
  } catch (err) {
    toast.error('Gagal memuat data pengaturan.');
  } finally {
    isLoading.value = false;
  }
}

async function saveSettings() {
  isSaving.value = true;
  try {
    settings.value.hari_aktif = activeDays.value.join(',');

    await api.post('/pengaturan', {
      settings: settings.value,
    });

    toast.success('Pengaturan sistem berhasil disimpan!');
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal menyimpan pengaturan.';
    toast.error(msg);
  } finally {
    isSaving.value = false;
  }
}

async function testWaGateway() {
  isTestingWa.value = true;
  try {
    const res = await api.post('/wa/test', { token: settings.value.fonnte_token });
    toast.success(res.data.message || 'Token Fonnte aktif & terkonfigurasi.');
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal menghubungi server Fonnte.';
    toast.error(msg);
  } finally {
    isTestingWa.value = false;
  }
}

onMounted(() => {
  loadSettings();
});
</script>
