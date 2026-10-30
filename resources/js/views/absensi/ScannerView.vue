<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between pb-2 border-b">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-lg font-semibold tracking-tight text-foreground">
            Scanner
          </h2>
          <Badge variant="outline" class="font-normal text-xs text-muted-foreground">
            QR &amp; Barcode
          </Badge>
        </div>
        <p class="text-xs text-muted-foreground mt-0.5">
          Pindai kartu santri secara otomatis menggunakan kamera perangkat atau webcam.
        </p>
      </div>

      <!-- Jadwal Presensi Badges & Filter Jilid Guru -->
      <div class="flex items-center gap-2 text-xs flex-wrap">
        <label v-if="isGuru && kelasBinaan" class="inline-flex items-center gap-1.5 text-xs cursor-pointer text-muted-foreground select-none mr-1">
          <input
            v-model="restrictKelas"
            type="checkbox"
            class="rounded border-input text-primary focus:ring-ring size-3.5 cursor-pointer"
          />
          <span>Hanya santri {{ kelasBinaan }}</span>
        </label>

        <Badge variant="outline" class="gap-1 font-normal text-xs py-1">
          <span class="text-muted-foreground">Masuk:</span>
          <strong class="font-semibold text-foreground">{{ configData.jam_masuk }}</strong>
        </Badge>
        <Badge variant="secondary" class="gap-1 font-normal text-xs py-1 border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-400">
          <Clock class="size-3" />
          <span>Terlambat:</span>
          <strong class="font-semibold">{{ configData.jam_terlambat }}</strong>
        </Badge>
      </div>
    </div>

    <!-- Main Scanner Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      
      <!-- LEFT: Camera Scanner & Manual Input (7 Cols) -->
      <div class="lg:col-span-7 space-y-6">
        
        <!-- Camera Scanner Card -->
        <Card class="overflow-hidden">
          <!-- Compact Single-Row Toolbar (Sejajar & Bersih) -->
          <div class="px-3 py-2 border-b bg-muted/20 flex items-center justify-between gap-2">
            <!-- Pilihan Kamera (Depan / Belakang) -->
            <div class="relative flex-1 min-w-0 max-w-[240px] sm:max-w-xs">
              <select
                v-model="selectedCameraMode"
                class="w-full h-8 rounded-lg border border-input bg-background pl-2.5 pr-7 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer shadow-2xs appearance-none truncate"
                title="Pilih Kamera Depan atau Belakang"
                :disabled="cameraLoading"
                @change="onCameraModeChange"
              >
                <option value="environment">📷 Kamera Belakang (Utama)</option>
                <option value="user">🤳 Kamera Depan (Selfie)</option>
                <optgroup v-if="cameras.length > 1" label="Daftar Perangkat Terdeteksi">
                  <option v-for="cam in cameras" :key="cam.id" :value="cam.id">
                    {{ cam.label || 'Kamera ' + cam.id.slice(0, 5) }}
                  </option>
                </optgroup>
              </select>
              <ChevronDown class="size-3.5 opacity-50 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" />
            </div>

            <!-- Tombol Aksi Kamera (Sejajar dengan Pilihan Kamera, Cukup Icon Saja) -->
            <div class="flex items-center gap-1.5 shrink-0">
              <!-- Tombol Balik Kamera (Icon Saja) -->
              <Button
                v-if="cameraActive"
                type="button"
                variant="outline"
                size="sm"
                class="size-8 p-0 shrink-0 shadow-2xs"
                :disabled="cameraLoading"
                title="Balik Kamera (Depan / Belakang)"
                @click="flipCamera"
              >
                <SwitchCamera class="size-4" />
                <span class="sr-only">Balik Kamera</span>
              </Button>

              <!-- Tombol Matikan / Nyalakan Kamera (Icon Saja) -->
              <Button
                size="sm"
                :variant="cameraActive ? 'destructive' : 'default'"
                class="size-8 p-0 shrink-0 shadow-2xs"
                :disabled="cameraLoading"
                :title="cameraActive ? 'Matikan Kamera' : 'Nyalakan Kamera'"
                @click="toggleCamera"
              >
                <LoaderCircle v-if="cameraLoading" class="size-4 animate-spin" />
                <VideoOff v-else-if="cameraActive" class="size-4 text-destructive-foreground" />
                <Camera v-else class="size-4" />
                <span class="sr-only">{{ cameraActive ? 'Matikan Kamera' : 'Nyalakan Kamera' }}</span>
              </Button>
            </div>
          </div>

          <CardContent class="p-3">
            <!-- Video Reader Container with Overlay -->
            <div
              class="relative bg-zinc-950 rounded-xl overflow-hidden min-h-[320px] flex items-center justify-center border transition-all duration-300"
              :class="{
                'border-emerald-500 ring-4 ring-emerald-500/20': isScanSuccessFlash,
                'border-rose-500 ring-4 ring-rose-500/20': isScanErrorFlash,
                'border-border': !isScanSuccessFlash && !isScanErrorFlash
              }"
            >
              <!-- HTML5 QR Container -->
              <div id="reader" class="w-full h-full max-w-md mx-auto"></div>

              <!-- Viewfinder Laser & Corners when active -->
              <div v-if="cameraActive && !cameraLoading" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                <div class="relative size-56 border-2 border-emerald-400/50 rounded-2xl shadow-sm">
                  <!-- Corner Accents -->
                  <div class="absolute -top-1 -left-1 size-5 border-t-3 border-l-3 border-emerald-400 rounded-tl"></div>
                  <div class="absolute -top-1 -right-1 size-5 border-t-3 border-r-3 border-emerald-400 rounded-tr"></div>
                  <div class="absolute -bottom-1 -left-1 size-5 border-b-3 border-l-3 border-emerald-400 rounded-bl"></div>
                  <div class="absolute -bottom-1 -right-1 size-5 border-b-3 border-r-3 border-emerald-400 rounded-br"></div>
                  
                  <!-- Animated Scanning Laser Bar -->
                  <div class="w-full h-0.5 bg-gradient-to-r from-transparent via-emerald-400 to-transparent shadow-[0_0_10px_rgba(52,211,153,0.9)] animate-laser"></div>
                </div>
              </div>

              <!-- Loading overlay when camera is starting -->
              <div v-if="cameraLoading" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center text-zinc-300 bg-zinc-950/80 backdrop-blur-xs z-10">
                <LoaderCircle class="size-8 text-primary animate-spin mb-3" />
                <p class="text-xs font-medium">Menghubungkan Kamera...</p>
              </div>

              <!-- Standby Overlay when inactive -->
              <div v-if="!cameraActive && !cameraLoading" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center text-zinc-400 bg-zinc-950/90 backdrop-blur-xs">
                <div class="size-14 rounded-2xl bg-zinc-900 border border-zinc-800 flex items-center justify-center mb-3 text-zinc-400">
                  <Camera class="size-7" />
                </div>
                <p class="text-sm font-semibold text-zinc-200">Kamera Siap Dinyalakan</p>
                <p class="text-xs text-zinc-400 mt-1 max-w-xs">
                  Gunakan kamera ponsel atau webcam laptop untuk memindai kartu presensi santri.
                </p>
                <Button
                  size="sm"
                  class="mt-4 gap-2 text-xs"
                  :disabled="cameraLoading"
                  @click="startCamera"
                >
                  <Camera class="size-3.5" />
                  <span>Nyalakan Kamera Sekarang</span>
                </Button>
              </div>

              <!-- Processing Scan Feedback Overlay -->
              <div
                v-if="isProcessing"
                class="absolute inset-0 bg-black/60 backdrop-blur-xs flex flex-col items-center justify-center text-white z-20"
              >
                <LoaderCircle class="size-8 text-primary animate-spin mb-2" />
                <p class="text-xs font-semibold tracking-wide">Mencatat Presensi...</p>
              </div>
            </div>

            <!-- Error Alert if camera fails -->
            <div
              v-if="cameraError"
              class="mt-3 p-3.5 rounded-lg border border-destructive/30 bg-destructive/10 text-xs text-destructive flex items-start gap-2.5"
            >
              <AlertCircle class="size-4 shrink-0 mt-0.5" />
              <div class="flex-1">
                <p class="font-semibold">Kendala Kamera:</p>
                <p class="mt-0.5 text-muted-foreground">{{ cameraError }}</p>
                <div class="mt-2.5">
                  <Button
                    variant="outline"
                    size="sm"
                    class="h-7 text-xs border-destructive/40 hover:bg-destructive/10"
                    @click="startCamera"
                  >
                    Coba Sambungkan Lagi
                  </Button>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

      </div>

      <!-- RIGHT: Last Scan Result & Scan Logs (5 Cols) -->
      <div class="lg:col-span-5 space-y-6">
        
        <!-- Live Scan Result Feedback Box -->
        <Card
          v-if="lastResult"
          class="transition-all duration-300"
          :class="{
            'border-emerald-500/50 bg-emerald-500/5': lastResult.success && lastResult.status === 'Hadir',
            'border-amber-500/50 bg-amber-500/5': lastResult.success && lastResult.status === 'Terlambat',
            'border-destructive/50 bg-destructive/5': !lastResult.success
          }"
        >
          <CardContent class="p-5">
            <div class="flex items-start gap-4">
              <div
                class="flex size-11 shrink-0 items-center justify-center rounded-xl text-white shadow-xs"
                :class="{
                  'bg-emerald-600': lastResult.success && lastResult.status === 'Hadir',
                  'bg-amber-600': lastResult.success && lastResult.status === 'Terlambat',
                  'bg-destructive': !lastResult.success
                }"
              >
                <X v-if="!lastResult.success" class="size-5" />
                <Check v-else class="size-5" />
              </div>

              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <Badge
                    :variant="lastResult.success ? (lastResult.status === 'Terlambat' ? 'secondary' : 'default') : 'destructive'"
                    class="text-[10px] font-semibold uppercase tracking-wider"
                  >
                    {{ lastResult.success ? 'Presensi Masuk' : 'Scan Ditolak' }}
                  </Badge>
                  <span v-if="lastResult.data?.status" class="text-xs font-semibold text-foreground">
                    {{ lastResult.data.status }}
                  </span>
                </div>

                <h4 class="text-base font-semibold text-foreground mt-1.5 truncate">
                  {{ lastResult.data?.nama_lengkap || 'Gagal Memproses' }}
                </h4>
                <p class="text-xs text-muted-foreground mt-0.5">
                  {{ lastResult.message }}
                </p>

                <!-- Student Details Grid -->
                <div v-if="lastResult.data" class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t text-xs">
                  <div>
                    <span class="text-[10px] text-muted-foreground block">NISN / Kelas</span>
                    <span class="font-semibold text-foreground">{{ lastResult.data.nisn }} / {{ lastResult.data.kelas || '-' }}</span>
                  </div>
                  <div>
                    <span class="text-[10px] text-muted-foreground block">Waktu Presensi</span>
                    <span class="font-semibold text-foreground">
                      {{ lastResult.data.waktu_masuk || '-' }} WIB
                    </span>
                  </div>
                </div>

                <!-- WhatsApp Notification Status Banner -->
                <div v-if="lastResult.data?.wa_status" class="mt-2.5 pt-2 border-t flex items-center justify-between gap-2 text-[11px]">
                  <div class="flex items-center gap-1.5">
                    <span
                      class="size-2 rounded-full shrink-0"
                      :class="{
                        'bg-emerald-500 animate-pulse': lastResult.data.wa_status === 'sent',
                        'bg-amber-500': lastResult.data.wa_status === 'no_phone',
                        'bg-rose-500': lastResult.data.wa_status === 'failed' || lastResult.data.wa_status === 'error',
                        'bg-muted-foreground/40': lastResult.data.wa_status === 'disabled' || lastResult.data.wa_status === 'no_token',
                      }"
                    ></span>
                    <span class="font-medium text-foreground">
                      <template v-if="lastResult.data.wa_status === 'sent'">
                        Notifikasi WA Terjadwal (Anti-Spam)
                      </template>
                      <template v-else-if="lastResult.data.wa_status === 'no_phone'">
                        No. WA Wali Belum Ada
                      </template>
                      <template v-else-if="lastResult.data.wa_status === 'disabled'">
                        Notifikasi WA Nonaktif
                      </template>
                      <template v-else>
                        WA Gateway: {{ lastResult.data.wa_status }}
                      </template>
                    </span>
                  </div>
                  <span v-if="lastResult.data.wa_target" class="font-mono text-muted-foreground text-[10px]">
                    {{ lastResult.data.wa_target }}
                  </span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Standby State Placeholder when no recent result -->
        <Card v-else class="border-dashed">
          <CardContent class="p-8 text-center text-muted-foreground">
            <ScanLine class="size-8 mx-auto mb-2 text-muted-foreground/60" />
            <p class="text-xs font-medium">Arahkan kartu QR code santri ke kamera untuk memulai...</p>
          </CardContent>
        </Card>

        <!-- Recent Scans History in this Session -->
        <Card>
          <CardHeader class="pb-3 border-b">
            <div class="flex items-center justify-between">
              <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Riwayat Scan Sesi Ini
              </CardTitle>
              <Badge variant="secondary" class="text-xs font-semibold">
                {{ scanLogs.length }} Santri
              </Badge>
            </div>
          </CardHeader>

          <CardContent class="p-0">
            <div v-if="scanLogs.length === 0" class="py-8 text-center text-xs text-muted-foreground">
              Belum ada santri yang discan pada sesi ini.
            </div>

            <div v-else class="divide-y max-h-72 overflow-y-auto px-4 py-1">
              <div
                v-for="(log, idx) in scanLogs"
                :key="idx"
                class="py-2.5 flex items-center justify-between gap-3 text-xs transition-colors hover:bg-muted/40 rounded-lg px-2"
              >
                <div class="flex items-center gap-2.5 min-w-0">
                  <span
                    class="size-2 rounded-full shrink-0"
                    :class="{
                      'bg-emerald-500': log.status === 'Hadir',
                      'bg-amber-500': log.status === 'Terlambat',
                      'bg-destructive': !log.success
                    }"
                  ></span>
                  <div class="min-w-0 truncate">
                    <div class="flex items-center gap-1.5">
                      <p class="font-medium text-foreground truncate">{{ log.nama }}</p>
                      <span
                        v-if="log.wa_status === 'sent'"
                        class="text-[9px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-1 py-0.2 rounded"
                        title="Notifikasi WA terkirim/terjadwal ke orang tua"
                      >
                        WA
                      </span>
                    </div>
                    <p class="text-[10px] text-muted-foreground">NISN: {{ log.nisn }} &bull; {{ log.kelas }}</p>
                  </div>
                </div>
                <div class="text-right shrink-0">
                  <span class="font-mono font-medium text-foreground">{{ log.waktu }}</span>
                  <span class="block text-[10px] text-muted-foreground capitalize">{{ log.status }}</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

      </div>

    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import {
  Camera,
  VideoOff,
  Clock,
  ScanLine,
  Check,
  X,
  LoaderCircle,
  AlertCircle,
  ChevronDown,
  SwitchCamera,
} from '@lucide/vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import api from '@/services/api';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';

const { role, user, isGuru, kelasBinaan } = useAuth();
const toast = useToast();

const isProcessing = ref(false);
const cameraActive = ref(false);
const cameraLoading = ref(false);
const cameraError = ref('');
const cameras = ref([]);
const selectedCameraMode = ref('environment'); // 'environment' (belakang) | 'user' (depan) | specific deviceId
const restrictKelas = ref(false);

const isScanSuccessFlash = ref(false);
const isScanErrorFlash = ref(false);

const lastScannedCode = ref('');
const lastScannedTime = ref(0);
const lastResult = ref(null);
const scanLogs = ref([]);

const configData = ref({
  jam_masuk: '07:00',
  jam_terlambat: '07:15',
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
      osc.type = 'sine';
      osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
      osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1); // A5
      gain.gain.setValueAtTime(0.15, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
      osc.start();
      osc.stop(ctx.currentTime + 0.3);
    } else {
      osc.type = 'sawtooth';
      osc.frequency.setValueAtTime(220, ctx.currentTime);
      gain.gain.setValueAtTime(0.2, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
      osc.start();
      osc.stop(ctx.currentTime + 0.35);
    }
  } catch (e) {
    //
  }
};

function extractNisn(raw) {
  if (!raw) return '';
  let str = String(raw).trim();

  if (str.startsWith('{') && str.endsWith('}')) {
    try {
      const p = JSON.parse(str);
      if (p.nisn) return String(p.nisn).trim();
      if (p.id) return String(p.id).trim();
    } catch (e) {}
  }

  if (str.includes('/')) {
    const parts = str.split('?')[0].split('/');
    const last = parts[parts.length - 1];
    if (last && /^[a-zA-Z0-9_-]+$/.test(last)) {
      return last;
    }
  }

  if (str.toLowerCase().startsWith('nisn:')) {
    return str.substring(5).trim();
  }

  return str;
}

const executeScan = async (rawCode, metode = 'qr_kamera') => {
  const nisnToScan = extractNisn(rawCode);
  if (!nisnToScan || isProcessing.value) return;

  isProcessing.value = true;
  lastScannedCode.value = nisnToScan;
  lastScannedTime.value = Date.now();

  try {
    const response = await api.post('/absensi/scan', {
      nisn: nisnToScan,
      scanner_role: role.value || 'guru',
      scanner_kelas: user.value?.nama_kelas || null,
      restrict_kelas: restrictKelas.value,
      metode_scan: metode,
    });

    const res = response.data;
    lastResult.value = res;
    playSound(true);

    isScanSuccessFlash.value = true;
    setTimeout(() => { isScanSuccessFlash.value = false; }, 600);

    scanLogs.value.unshift({
      nama: res.data?.nama_lengkap || nisnToScan,
      nisn: res.data?.nisn || nisnToScan,
      kelas: res.data?.kelas || '-',
      waktu: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
      status: res.status,
      success: true,
      wa_status: res.data?.wa_status,
      wa_target: res.data?.wa_target,
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

    isScanErrorFlash.value = true;
    setTimeout(() => { isScanErrorFlash.value = false; }, 600);

    toast.error(msg);
  } finally {
    isProcessing.value = false;
  }
};

const handleCameraScan = (decodedText) => {
  if (isProcessing.value) return;

  const code = extractNisn(decodedText);
  if (!code) return;

  const now = Date.now();
  if (code === lastScannedCode.value && (now - lastScannedTime.value) < 3000) {
    return;
  }

  executeScan(code, 'qr_kamera');
};

const cleanHtml5QrCode = async () => {
  if (html5QrCode) {
    try {
      if (html5QrCode.isScanning) {
        await html5QrCode.stop();
      }
      html5QrCode.clear();
    } catch (e) {
      console.warn('Gagal membersihkan scanner:', e);
    }
    html5QrCode = null;
  }

  const readerEl = document.getElementById('reader');
  if (readerEl) {
    readerEl.innerHTML = '';
  }
};

const startCamera = async (forceMode = null) => {
  cameraError.value = '';
  cameraLoading.value = true;
  cameraActive.value = true;

  if (forceMode) {
    selectedCameraMode.value = forceMode;
  }

  await nextTick();
  await cleanHtml5QrCode();

  // Ambil daftar kamera jika belum pernah diambil
  try {
    const devices = await Html5Qrcode.getCameras();
    if (devices && devices.length > 0) {
      cameras.value = devices;
    }
  } catch (e) {
    console.warn('Gagal deteksi kamera awal:', e);
  }

  const mode = selectedCameraMode.value || 'environment';
  const strategies = [];

  const isSpecificDevice = mode !== 'environment' && mode !== 'user';
  if (isSpecificDevice) {
    strategies.push({ name: 'specific_device', camera: mode });
  } else if (mode === 'environment') {
    // 1. Prioritas utama: facingMode 'environment' (kamera belakang)
    strategies.push({ name: 'facingMode_environment', camera: { facingMode: 'environment' } });

    // 2. Coba cari device yang labelnya menandakan kamera belakang
    const backCam = cameras.value.find(c => {
      const l = (c.label || '').toLowerCase();
      return l.includes('back') || l.includes('rear') || l.includes('environment') || l.includes('belakang') || l.includes('0, facing back');
    });
    if (backCam) {
      strategies.push({ name: 'device_back', camera: backCam.id });
    }

    // 3. Fallback jika di PC/Laptop yang tidak punya kamera belakang
    if (cameras.value.length > 0) {
      strategies.push({ name: 'device_fallback', camera: cameras.value[0].id });
    }
    strategies.push({ name: 'facingMode_user_fallback', camera: { facingMode: 'user' } });
  } else {
    // mode === 'user' (kamera depan)
    strategies.push({ name: 'facingMode_user', camera: { facingMode: 'user' } });

    const frontCam = cameras.value.find(c => {
      const l = (c.label || '').toLowerCase();
      return l.includes('front') || l.includes('user') || l.includes('depan') || l.includes('facing front');
    });
    if (frontCam) {
      strategies.push({ name: 'device_front', camera: frontCam.id });
    }

    if (cameras.value.length > 0) {
      strategies.push({ name: 'device_fallback', camera: cameras.value[0].id });
    }
  }

  // PENTING: JANGAN cantumkan videoConstraints di qrConfig agar html5-qrcode
  // tidak mengabaikan konfigurasi camera/facingMode yang diberikan!
  const qrConfig = {
    fps: 15,
    disableFlip: false,
  };

  let success = false;
  let lastErr = null;

  for (const strat of strategies) {
    try {
      await cleanHtml5QrCode();

      const scanner = new Html5Qrcode('reader', {
        formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
        verbose: false,
        experimentalFeatures: {
          useBarCodeDetectorIfSupported: true,
        },
      });

      await scanner.start(
        strat.camera,
        qrConfig,
        handleCameraScan,
        () => {}
      );

      html5QrCode = scanner;
      success = true;
      cameraActive.value = true;

      // Coba aktifkan continuous autofocus jika didukung
      try {
        const capabilities = scanner.getRunningTrackCapabilities();
        if (capabilities && capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
          await scanner.applyVideoConstraints({
            advanced: [{ focusMode: 'continuous' }]
          });
        }
      } catch (focusErr) {}

      // Perbarui daftar perangkat setelah izin kamera diberikan
      try {
        const updatedDevices = await Html5Qrcode.getCameras();
        if (updatedDevices && updatedDevices.length > 0) {
          cameras.value = updatedDevices;
        }
      } catch (e) {}

      // Sinkronkan facingMode aktual jika terdeteksi
      try {
        const settings = scanner.getRunningTrackSettings();
        if (settings && settings.facingMode) {
          if (settings.facingMode === 'environment' || settings.facingMode === 'user') {
            selectedCameraMode.value = settings.facingMode;
          }
        }
      } catch (e) {}

      break;
    } catch (err) {
      lastErr = err;
      console.warn(`Percobaan kamera [${strat.name}] gagal:`, err);
    }
  }

  if (!success) {
    await cleanHtml5QrCode();
    cameraActive.value = false;
    let errMsg = 'Kamera tidak dapat diakses.';
    if (lastErr) {
      if (lastErr.name === 'NotAllowedError') {
        errMsg = 'Izin kamera ditolak. Silakan izinkan akses kamera di pengaturan browser Anda.';
      } else if (lastErr.name === 'NotFoundError') {
        errMsg = 'Perangkat kamera tidak ditemukan pada komputer/ponsel ini.';
      } else if (lastErr.name === 'NotReadableError') {
        errMsg = 'Kamera sedang digunakan oleh aplikasi lain. Tutup aplikasi tersebut dan coba lagi.';
      } else {
        errMsg = lastErr.message || 'Kamera gagal diaktifkan.';
      }
    }
    cameraError.value = errMsg;
    toast.error(errMsg);
  }

  cameraLoading.value = false;
};

const stopCamera = async () => {
  await cleanHtml5QrCode();
  cameraActive.value = false;
};

const toggleCamera = async () => {
  if (cameraActive.value) {
    await stopCamera();
  } else {
    await startCamera();
  }
};

const onCameraModeChange = async () => {
  if (selectedCameraMode.value) {
    await startCamera(selectedCameraMode.value);
  }
};

const flipCamera = async () => {
  const newMode = selectedCameraMode.value === 'environment' ? 'user' : 'environment';
  selectedCameraMode.value = newMode;
  toast.info(newMode === 'environment' ? 'Beralih ke Kamera Belakang...' : 'Beralih ke Kamera Depan...');
  await startCamera(newMode);
};

const loadConfig = async () => {
  try {
    const res = await api.get('/absensi/config');
    if (res.data.success) {
      configData.value = {
        jam_masuk: res.data.jam_masuk,
        jam_terlambat: res.data.jam_terlambat,
      };
    }
  } catch (e) {}
};

onMounted(() => {
  loadConfig();
  startCamera();
});

onUnmounted(() => {
  stopCamera();
});
</script>

<style scoped>
@keyframes laser {
  0% { transform: translateY(0); }
  50% { transform: translateY(220px); }
  100% { transform: translateY(0); }
}
.animate-laser {
  animation: laser 2s ease-in-out infinite;
}

:deep(#reader video) {
  width: 100% !important;
  height: 100% !important;
  object-fit: cover !important;
  border-radius: 0.75rem;
}
</style>
