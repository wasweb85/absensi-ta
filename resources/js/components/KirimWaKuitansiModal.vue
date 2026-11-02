<template>
  <div
    v-if="show"
    class="fixed inset-0 z-50 flex items-center justify-center p-2.5 sm:p-4 bg-black/75 backdrop-blur-sm animate-fadeIn"
  >
    <div
      class="w-full max-w-lg max-h-[94vh] flex flex-col rounded-2xl sm:rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl p-3.5 sm:p-5 text-xs animate-slideUp overflow-hidden"
    >
      <!-- Header Modal -->
      <div class="flex items-center justify-between pb-2.5 border-b border-zinc-100 dark:border-zinc-800 shrink-0">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold shrink-0">
            <BaseIcon name="whatsapp" class="w-4 h-4 text-emerald-600" />
          </div>
          <div>
            <h3 class="text-xs sm:text-sm font-bold text-zinc-900 dark:text-white leading-tight">
              Kirim Kuitansi WhatsApp ke Wali Santri
            </h3>
            <p class="text-[10px] text-zinc-400 truncate max-w-[220px] sm:max-w-none">
              Format gambar resmi &bull; Langsung ke nomor tujuan &bull; Tanpa teks penjelasan
            </p>
          </div>
        </div>

        <button
          type="button"
          @click="emit('close')"
          class="w-7 h-7 rounded-lg flex items-center justify-center text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition cursor-pointer"
          title="Tutup Modal"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Body: Scrollable -->
      <div class="flex-1 overflow-y-auto my-2 pr-0.5 space-y-3">
        
        <!-- Input Nomor WhatsApp Tujuan -->
        <div class="p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-700/60 space-y-1.5">
          <div class="flex items-center justify-between">
            <label class="font-bold text-zinc-700 dark:text-zinc-300 text-[11px]">
              Nomor WhatsApp Tujuan:
            </label>
            <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
              {{ kuitansiData?.nama_santri }} ({{ kuitansiData?.kelas }})
            </span>
          </div>

          <div class="relative">
            <input
              v-model="targetPhone"
              type="text"
              placeholder="Contoh: 08123456789 atau 628123456789"
              class="w-full px-3 py-2 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 font-mono text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>
          <p v-if="!targetPhone" class="text-[10px] text-rose-500 font-medium">
            * Nomor belum terdaftar pada profil santri. Silakan ketik nomor WhatsApp wali santri.
          </p>
        </div>

        <!-- Pratinjau Kuitansi Gambar -->
        <div class="space-y-1.5">
          <div class="flex items-center justify-between">
            <span class="font-bold text-zinc-700 dark:text-zinc-300 text-[11px] flex items-center gap-1.5">
              <span>Pratinjau Gambar Kuitansi</span>
              <span v-if="isGenerating" class="text-[10px] text-emerald-600 flex items-center gap-1">
                <RefreshCw class="w-3 h-3 animate-spin" /> Merender...
              </span>
            </span>

            <button
              v-if="imageUrl"
              type="button"
              @click="downloadImage"
              class="text-[10px] font-semibold text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-200 flex items-center gap-1 cursor-pointer"
              title="Unduh file gambar kuitansi ke perangkat"
            >
              <Download class="w-3 h-3" />
              <span>Unduh PNG</span>
            </button>
          </div>

          <!-- Container Image Preview -->
          <div class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-950 p-2 overflow-hidden flex items-center justify-center min-h-[240px]">
            <div v-if="isGenerating" class="p-8 text-center text-zinc-400">
              <RefreshCw class="w-6 h-6 mx-auto mb-2 animate-spin text-emerald-600" />
              <p class="text-[11px] font-medium">Sedang membuat gambar kuitansi resmi...</p>
            </div>

            <img
              v-else-if="imageUrl"
              :src="imageUrl"
              alt="Pratinjau Kuitansi SPP"
              class="w-full max-h-[340px] object-contain rounded-lg shadow-sm border border-zinc-200 dark:border-zinc-800 bg-white"
            />

            <div v-else class="p-6 text-center text-rose-500 text-[11px]">
              Gagal memuat pratinjau gambar kuitansi.
            </div>
          </div>
        </div>

        <!-- Info Card Singkat -->
        <div class="p-2.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-800/40 text-[11px] text-emerald-800 dark:text-emerald-300 space-y-1.5">
          <div class="flex items-start gap-1.5 font-bold">
            <span>💡</span>
            <span>Panduan Pengiriman di HP:</span>
          </div>
          <ul class="list-disc pl-5 space-y-0.5 text-[10.5px] leading-relaxed text-zinc-700 dark:text-zinc-300">
            <li><b>Kirim via Gateway (Paling Praktis):</b> Otomatis terkirim dari server ke WhatsApp wali santri tanpa perlu membuka WhatsApp atau paste.</li>
            <li><b>Bagikan ke WA (Bawaan HP):</b> Menempelkan gambar kuitansi langsung ke WhatsApp via menu bagikan HP tanpa perlu simpan ke galeri atau paste.</li>
          </ul>
        </div>

      </div>

      <!-- Action Buttons (Sticky Footer) -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 pt-2.5 border-t border-zinc-100 dark:border-zinc-800 shrink-0">
        <div class="flex items-center gap-2 order-3 sm:order-1">
          <button
            type="button"
            @click="emit('close')"
            class="h-8 px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 text-xs font-semibold hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition cursor-pointer"
          >
            Tutup
          </button>

          <!-- Tombol Unduh PNG Eksplisit (HANYA unduh bila tombol ini diklik) -->
          <button
            v-if="imageUrl"
            type="button"
            @click="downloadImage"
            class="h-8 px-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-xs font-semibold transition flex items-center justify-center gap-1 cursor-pointer shadow-2xs"
            title="Unduh file gambar kuitansi ke perangkat"
          >
            <Download class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400" />
            <span>Unduh PNG</span>
          </button>
        </div>

        <div class="order-1 sm:order-2 flex flex-col sm:flex-row items-stretch sm:items-center gap-1.5">
          <!-- Tombol Kirim Gateway (Fonnte API) Mengirimkan Gambar Murni Langsung ke Wali Santri -->
          <button
            type="button"
            @click="sendViaGateway"
            :disabled="!targetPhone || isSendingGateway || isGenerating"
            class="h-8 px-3 rounded-lg border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs"
            title="Kirim gambar kuitansi langsung ke WhatsApp wali santri via gateway server"
          >
            <RefreshCw v-if="isSendingGateway" class="w-3.5 h-3.5 animate-spin" />
            <Send v-else class="w-3.5 h-3.5" />
            <span>{{ isSendingGateway ? 'Mengirim...' : 'Kirim via Gateway' }}</span>
          </button>

          <!-- Tombol Bagikan Gambar HP (Web Share API - Langsung Nempel Gambar Tanpa Paste & Tanpa Galeri) -->
          <button
            v-if="canShareMobile"
            type="button"
            @click="shareViaMobile"
            :disabled="isGenerating || !imageFile"
            class="h-8 px-3.5 rounded-lg bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold shadow-md shadow-teal-600/20 transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            title="Bagikan kuitansi langsung ke WhatsApp via menu HP (tanpa paste)"
          >
            <Share2 class="w-3.5 h-3.5" />
            <span>Bagikan ke WA (HP)</span>
          </button>

          <!-- Tombol Buka Chat WhatsApp Langsung ke Nomor Wali Santri -->
          <button
            type="button"
            @click="sendDirectWhatsApp"
            :disabled="!targetPhone || isGenerating"
            class="h-8 px-3.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20 transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            :title="canShareMobile ? 'Buka ruang obrolan nomor wali santri' : 'Buka obrolan WhatsApp langsung ke nomor wali santri'"
          >
            <BaseIcon name="whatsapp" class="w-3.5 h-3.5" />
            <span>{{ canShareMobile ? 'Buka Chat WA' : 'Kirim ke WA Wali Santri' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import BaseIcon from './BaseIcon.vue';
import { X, RefreshCw, Send, Download, Share2 } from '@lucide/vue';
import { generateKuitansiCanvas } from '../utils/kuitansiCanvas';
import api from '../services/api';
import { useToast } from '../composables/useToast';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  kuitansiData: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'sent']);
const toast = useToast();

const isGenerating = ref(false);
const isSendingGateway = ref(false);
const imageUrl = ref('');
const imageBlob = ref(null);
const imageFile = ref(null);
const targetPhone = ref('');

// Cek apakah browser mendukung Web Share dengan file (fitur bawaan smartphone Android & iOS)
const canShareMobile = computed(() => {
  if (typeof navigator === 'undefined' || !navigator.share || !navigator.canShare || !imageFile.value) {
    return false;
  }
  try {
    return navigator.canShare({ files: [imageFile.value] });
  } catch (e) {
    return false;
  }
});

// Format nomor internasional (62...)
function cleanPhoneNumber(phone) {
  if (!phone) return '';
  let cleaned = String(phone).replace(/[^0-9]/g, '');
  if (cleaned.startsWith('0')) {
    cleaned = '62' + cleaned.slice(1);
  } else if (cleaned.startsWith('8')) {
    cleaned = '62' + cleaned;
  }
  return cleaned;
}

// Render Gambar Kuitansi setiap kali modal dibuka
async function renderImage() {
  if (!props.kuitansiData) return;
  isGenerating.value = true;
  try {
    targetPhone.value = props.kuitansiData.no_hp_ortu || '';
    const result = await generateKuitansiCanvas(props.kuitansiData);
    imageUrl.value = result.dataUrl;
    imageBlob.value = result.blob;
    imageFile.value = result.file;
  } catch (err) {
    console.error('Gagal membuat gambar kuitansi:', err);
    toast.error('Gagal membuat gambar kuitansi.');
  } finally {
    isGenerating.value = false;
  }
}

watch(
  () => props.show,
  (val) => {
    if (val && props.kuitansiData) {
      renderImage();
    } else {
      imageUrl.value = '';
      imageBlob.value = null;
      imageFile.value = null;
    }
  },
  { immediate: true }
);

// Download Gambar Kuitansi (HANYA dijalankan saat tombol 'Unduh PNG' diklik secara eksplisit)
function downloadImage() {
  if (!imageUrl.value) return;
  const link = document.createElement('a');
  link.href = imageUrl.value;
  link.download = `Kuitansi-SPP-${props.kuitansiData?.nomor_kuitansi || 'Resmi'}.png`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  toast.success('Gambar kuitansi berhasil diunduh ke perangkat.');
}

// Fitur Bagikan Bawaan Smartphone (Web Share API: Langsung nempel di WA tanpa copy-paste dan tanpa galeri)
async function shareViaMobile() {
  if (!imageFile.value) {
    toast.warning('Gambar kuitansi belum siap.');
    return;
  }

  try {
    await navigator.share({
      files: [imageFile.value],
      title: 'Kuitansi Pembayaran SPP',
    });
    toast.success('Membuka menu bagikan. Pilih WhatsApp untuk langsung mengirim kuitansi.');
    emit('sent');
  } catch (err) {
    if (err.name !== 'AbortError') {
      console.warn('Gagal membagikan kuitansi via Web Share:', err);
    }
  }
}

// 1. Pengiriman Langsung Mengarah Otomatis ke Nomor Wali Santri (Tanpa Perlu Memilih Kontak & TANPA auto download)
async function sendDirectWhatsApp() {
  const cleanPhone = cleanPhoneNumber(targetPhone.value);
  if (!cleanPhone) {
    toast.warning('Nomor WhatsApp tujuan belum diisi.');
    return;
  }

  // A. Salin gambar kuitansi ke Clipboard secara otomatis (jika didukung peramban desktop untuk Ctrl+V)
  try {
    if (imageBlob.value && navigator.clipboard && window.ClipboardItem) {
      await navigator.clipboard.write([
        new ClipboardItem({ 'image/png': imageBlob.value })
      ]);
    }
  } catch (err) {
    // Graceful ignore jika peramban membatasi akses clipboard image
  }

  // PENTING: TIDAK ADA downloadImage() di sini!
  // Unduh kuitansi HANYA berjalan jika user secara sengaja mengklik tombol 'Unduh PNG'.

  // B. Buka WhatsApp LANGSUNG ke nomor tujuan wali santri tanpa perlu mencari/memilih kontak
  const waUrl = `https://api.whatsapp.com/send?phone=${cleanPhone}`;
  window.open(waUrl, '_blank');

  toast.success('Membuka chat nomor wali santri. Gambar telah disalin ke clipboard (Ctrl+V jika di komputer).');
  emit('sent');
}

// 2. Pengiriman Otomatis via Gateway Fonnte API (Server Mengirim Gambar Murni Tanpa Tulisan)
async function sendViaGateway() {
  const cleanPhone = cleanPhoneNumber(targetPhone.value);
  if (!cleanPhone) {
    toast.warning('Nomor WhatsApp tujuan belum diisi.');
    return;
  }

  if (!imageUrl.value) {
    toast.warning('Gambar kuitansi belum siap.');
    return;
  }

  isSendingGateway.value = true;
  try {
    // Sesuai permintaan: hanya mengirimkan gambar murni tanpa teks penjelasan di bawahnya
    const res = await api.post('/wa/send-kuitansi-image', {
      phone: cleanPhone,
      message: '', // Kosongkan caption: hanya gambar kuitansi murni
      image_base64: imageUrl.value,
      nomor_kuitansi: props.kuitansiData?.nomor_kuitansi,
      nama_penerima: props.kuitansiData?.nama_santri,
    });

    if (res.data?.success) {
      toast.success('Kuitansi gambar berhasil dikirim ke nomor wali santri via Gateway!');
      emit('sent');
      emit('close');
    } else {
      toast.error(res.data?.message || 'Gagal mengirim kuitansi via Gateway.');
    }
  } catch (err) {
    const errMessage = err.response?.data?.message || err.message || 'Terjadi kesalahan gateway.';
    toast.error(`Kirim Gateway Gagal: ${errMessage}`);
  } finally {
    isSendingGateway.value = false;
  }
}
</script>
