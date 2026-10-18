<template>
  <div class="space-y-6 animate-fadeIn">
    
    <!-- Header & Config Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>📷</span>
          <span>Scanner Absensi Pintar</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Scan QR Code via kamera perangkat atau gunakan Barcode/RFID Scanner USB
        </p>
      </div>

      <!-- Schedule Badges -->
      <div class="flex flex-wrap items-center gap-2 text-xs">
        <div class="px-3 py-1.5 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700/60">
          <span class="text-zinc-400 block text-[10px]">Jam Masuk</span>
          <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ configData.jam_masuk }}</span>
        </div>
        <div class="px-3 py-1.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-300">
          <span class="text-amber-600/80 dark:text-amber-400/80 block text-[10px]">Batas Telat</span>
          <span class="font-bold">{{ configData.jam_terlambat }}</span>
        </div>
        <div class="px-3 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-blue-800 dark:text-blue-300">
          <span class="text-blue-600/80 dark:text-blue-400/80 block text-[10px]">Jam Pulang</span>
          <span class="font-bold">{{ configData.jam_pulang }}</span>
        </div>
      </div>
    </div>

    <!-- Main Scanner Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      
      <!-- LEFT: Camera & Barcode Input (7 Cols) -->
      <div class="lg:col-span-7 space-y-5">
        
        <!-- Scan Input Box (Barcode / USB Scanner / Manual NISN) -->
        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
          <label for="nisnInput" class="block text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">
            Input Scanner USB / Barcode / NISN Manual
          </label>
          <form @submit.prevent="handleManualSubmit" class="flex gap-2">
            <div class="relative flex-1">
              <input
                id="nisnInput"
                ref="barcodeInputRef"
                v-model="manualNisn"
                type="text"
                autocomplete="off"
                placeholder="Scan barcode kartu atau ketik NISN (cth: 1001) lalu Enter..."
                class="w-full px-4 py-3 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/60 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono transition"
                :disabled="isProcessing"
              />
            </div>
            <button
              type="submit"
              :disabled="!manualNisn || isProcessing"
              class="px-5 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-semibold text-sm transition shadow-md shadow-emerald-600/20 cursor-pointer flex items-center gap-1.5"
            >
              <span v-if="isProcessing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>Proses</span>
            </button>
          </form>
          <p class="text-[11px] text-zinc-400 mt-2 flex items-center gap-1">
            <span>💡</span>
            <span>Kolom ini otomatis mendeteksi pemindaian scanner barcode/RFID gun eksternal.</span>
          </p>
        </div>

        <!-- Camera Scanner Section -->
        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm overflow-hidden">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
              <span>📹</span>
              <span>Scanner Kamera QR Code</span>
            </h3>
            <button
              @click="toggleCamera"
              class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer"
              :class="cameraActive ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'"
            >
              {{ cameraActive ? '⏹ Matikan Kamera' : '▶ Nyalakan Kamera' }}
            </button>
          </div>

          <!-- Video Reader Container -->
          <div class="relative bg-zinc-950 rounded-2xl overflow-hidden min-h-[260px] flex items-center justify-center border border-zinc-800">
            <div id="reader" class="w-full h-full max-w-md mx-auto"></div>
            <div v-if="!cameraActive" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center text-zinc-500">
              <span class="text-4xl mb-2">📷</span>
              <p class="text-xs font-medium">Kamera sedang nonaktif.</p>
              <p class="text-[11px] text-zinc-600 dark:text-zinc-400 mt-1">Klik "Nyalakan Kamera" untuk memindai QR Code santri.</p>
            </div>
          </div>
        </div>

      </div>

      <!-- RIGHT: Last Scan Result & Scan Logs (5 Cols) -->
      <div class="lg:col-span-5 space-y-5">
        
        <!-- Live Scan Result Feedback Box -->
        <div
          v-if="lastResult"
          class="p-6 rounded-3xl border shadow-lg transition-all duration-300 animate-slideUp"
          :class="{
            'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800': lastResult.success && lastResult.status === 'Hadir',
            'bg-amber-50/80 dark:bg-amber-950/40 border-amber-300 dark:border-amber-800': lastResult.success && lastResult.status === 'Terlambat',
            'bg-blue-50/80 dark:bg-blue-950/40 border-blue-300 dark:border-blue-800': lastResult.success && lastResult.type === 'pulang',
            'bg-rose-50/80 dark:bg-rose-950/40 border-rose-300 dark:border-rose-800': !lastResult.success
          }"
        >
          <div class="flex items-start gap-4">
            <div
              class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl font-bold shrink-0 shadow-inner"
              :class="{
                'bg-emerald-500 text-white': lastResult.success && lastResult.status === 'Hadir',
                'bg-amber-500 text-white': lastResult.success && lastResult.status === 'Terlambat',
                'bg-blue-500 text-white': lastResult.success && lastResult.type === 'pulang',
                'bg-rose-500 text-white': !lastResult.success
              }"
            >
              {{ lastResult.success ? (lastResult.type === 'pulang' ? '🏠' : '✓') : '✕' }}
            </div>

            <div class="flex-1 overflow-hidden">
              <div class="flex items-center gap-2">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider text-white"
                  :class="{
                    'bg-emerald-600': lastResult.success && lastResult.status === 'Hadir',
                    'bg-amber-600': lastResult.success && lastResult.status === 'Terlambat',
                    'bg-blue-600': lastResult.success && lastResult.type === 'pulang',
                    'bg-rose-600': !lastResult.success
                  }"
                >
                  {{ lastResult.success ? (lastResult.type === 'pulang' ? 'Absen Pulang' : 'Absen Masuk') : 'Scan Ditolak' }}
                </span>
                <span v-if="lastResult.data?.status" class="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                  {{ lastResult.data.status }}
                </span>
              </div>

              <h4 class="text-lg font-bold text-zinc-900 dark:text-white mt-1 truncate">
                {{ lastResult.data?.nama_lengkap || 'Gagal' }}
              </h4>
              <p class="text-xs text-zinc-600 dark:text-zinc-300 mt-0.5">
                {{ lastResult.message }}
              </p>

              <!-- Student Details Grid -->
              <div v-if="lastResult.data" class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-zinc-200/60 dark:border-zinc-800/60 text-xs">
                <div>
                  <span class="text-[10px] text-zinc-400 block">NISN / Kelas</span>
                  <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ lastResult.data.nisn }} / {{ lastResult.data.kelas || '-' }}</span>
                </div>
                <div>
                  <span class="text-[10px] text-zinc-400 block">Waktu Tercatat</span>
                  <span class="font-bold text-zinc-800 dark:text-zinc-200">
                    {{ lastResult.type === 'pulang' ? (lastResult.data.waktu_pulang || '-') : (lastResult.data.waktu_masuk || '-') }} WIB
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Standby State Placeholder when no recent result -->
        <div v-else class="p-6 rounded-3xl bg-zinc-50 dark:bg-zinc-900/40 border border-dashed border-zinc-300 dark:border-zinc-800 text-center text-zinc-400">
          <span class="text-3xl block mb-1">🎯</span>
          <p class="text-xs font-medium">Menunggu pemindaian kartu/QR Code santri...</p>
        </div>

        <!-- Recent Scans History in this Session -->
        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
          <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-3 flex items-center justify-between">
            <span>Riwayat Scan Sesi Ini</span>
            <span>{{ scanLogs.length }} Catatan</span>
          </h3>

          <div v-if="scanLogs.length === 0" class="py-6 text-center text-zinc-400 text-xs">
            Belum ada santri yang discan pada sesi ini.
          </div>

          <div v-else class="space-y-2 max-h-72 overflow-y-auto pr-1">
            <div
              v-for="(log, idx) in scanLogs"
              :key="idx"
              class="p-2.5 rounded-xl border border-zinc-100 dark:border-zinc-800/80 flex items-center justify-between text-xs transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40"
            >
              <div class="flex items-center gap-2.5 overflow-hidden">
                <span
                  class="w-2 h-2 rounded-full shrink-0"
                  :class="{
                    'bg-emerald-500': log.status === 'Hadir',
                    'bg-amber-500': log.status === 'Terlambat',
                    'bg-blue-500': log.type === 'pulang',
                    'bg-rose-500': !log.success
                  }"
                ></span>
                <div class="truncate">
                  <p class="font-bold text-zinc-900 dark:text-zinc-100 truncate">{{ log.nama }}</p>
                  <p class="text-[10px] text-zinc-400">{{ log.nisn }} &bull; {{ log.kelas }}</p>
                </div>
              </div>
              <div class="text-right shrink-0">
                <span class="font-mono font-bold text-zinc-700 dark:text-zinc-300">{{ log.waktu }}</span>
                <span class="block text-[9px] text-zinc-400 capitalize">{{ log.status }}</span>
              </div>
            </div>
          </div>
        </div>

      </div>

    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';
import api from '../../services/api';
import { useAuth } from '../../composables/useAuth';
import { useToast } from '../../composables/useToast';

const { role, user } = useAuth();
const toast = useToast();

const manualNisn = ref('');
const barcodeInputRef = ref(null);
const isProcessing = ref(false);
const cameraActive = ref(false);
const lastResult = ref(null);
const scanLogs = ref([]);
const configData = ref({
  jam_masuk: '07:00',
  jam_terlambat: '07:15',
  jam_pulang: '12:00',
});

let html5QrCode = null;

// Audio Chime Synthesis using Web Audio API
const playSound = (isSuccess) => {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);

    if (isSuccess) {
      // Pleasant double high tone
      osc.type = 'sine';
      osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
      osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1); // A5
      gain.gain.setValueAtTime(0.15, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
      osc.start();
      osc.stop(ctx.currentTime + 0.3);
    } else {
      // Low buzz tone
      osc.type = 'sawtooth';
      osc.frequency.setValueAtTime(220, ctx.currentTime);
      gain.gain.setValueAtTime(0.2, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
      osc.start();
      osc.stop(ctx.currentTime + 0.35);
    }
  } catch (e) {
    // Ignore audio context errors
  }
};

const executeScan = async (nisnToScan, metode = 'manual') => {
  if (isProcessing.value || !nisnToScan) return;
  isProcessing.value = true;

  try {
    const response = await api.post('/absensi/scan', {
      nisn: nisnToScan,
      scanner_role: role.value || 'admin',
      scanner_kelas: user.value?.nama_kelas || null,
      metode_scan: metode,
    });

    const res = response.data;
    lastResult.value = res;
    playSound(true);

    scanLogs.value.unshift({
      nama: res.data?.nama_lengkap || nisnToScan,
      nisn: res.data?.nisn || nisnToScan,
      kelas: res.data?.kelas || '-',
      waktu: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
      status: res.status,
      type: res.type,
      success: true,
    });

    toast.success(res.message);
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal memproses absensi.';
    const data = err.response?.data?.data || null;
    
    lastResult.value = {
      success: false,
      message: msg,
      data: data,
    };
    playSound(false);
    toast.error(msg);
  } finally {
    isProcessing.value = false;
    manualNisn.value = '';
    nextTick(() => {
      barcodeInputRef.value?.focus();
    });
  }
};

const handleManualSubmit = () => {
  const code = manualNisn.value.trim();
  if (code) {
    executeScan(code, 'scanner_usb');
  }
};

const toggleCamera = async () => {
  if (cameraActive.value) {
    stopCamera();
  } else {
    startCamera();
  }
};

const startCamera = async () => {
  try {
    cameraActive.value = true;
    await nextTick();

    html5QrCode = new Html5Qrcode('reader');
    await html5QrCode.start(
      { facingMode: 'environment' },
      {
        fps: 10,
        qrbox: { width: 220, height: 220 },
      },
      (decodedText) => {
        executeScan(decodedText, 'qr_kamera');
      },
      (errorMessage) => {
        // scan error per frame, ignore
      }
    );
  } catch (err) {
    cameraActive.value = false;
    toast.error('Gagal mengakses kamera: ' + (err.message || 'Izin ditolak'));
  }
};

const stopCamera = async () => {
  if (html5QrCode) {
    try {
      await html5QrCode.stop();
      html5QrCode.clear();
    } catch (e) {
      //
    }
  }
  cameraActive.value = false;
};

const loadConfig = async () => {
  try {
    const res = await api.get('/absensi/config');
    if (res.data.success) {
      configData.value = {
        jam_masuk: res.data.jam_masuk,
        jam_terlambat: res.data.jam_terlambat,
        jam_pulang: res.data.jam_pulang,
      };
    }
  } catch (e) {
    //
  }
};

onMounted(() => {
  loadConfig();
  nextTick(() => {
    barcodeInputRef.value?.focus();
  });
});

onUnmounted(() => {
  stopCamera();
});
</script>

<style scoped>
@keyframes slideUp {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-slideUp {
  animation: slideUp 0.25s ease-out forwards;
}
</style>
