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
              Format gambar resmi &bull; Tanpa kolom penyetor &bull; Langsung ke HP wali
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
      <div class="flex-1 overflow-y-auto my-2 pr-0.5 space-y-3.5">
        
        <!-- Input Nomor WhatsApp Tujuan -->
        <div class="p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-700/60 space-y-1.5">
          <div class="flex items-center justify-between">
            <label class="font-bold text-zinc-700 dark:text-zinc-300 text-[11px]">
              Nomor WhatsApp Wali Santri:
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
            * Nomor belum terdaftar pada profil santri. Silakan ketikkan nomor WhatsApp orang tua/wali.
          </p>
        </div>

        <!-- Pratinjau Kuitansi Gambar -->
        <div class="space-y-1.5">
          <div class="flex items-center justify-between">
            <span class="font-bold text-zinc-700 dark:text-zinc-300 text-[11px] flex items-center gap-1.5">
              <span>Pratinjau Kuitansi Gambar (Siap Kirim)</span>
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
          <div class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-950 p-2 overflow-hidden flex items-center justify-center min-h-[220px]">
            <div v-if="isGenerating" class="p-8 text-center text-zinc-400">
              <RefreshCw class="w-6 h-6 mx-auto mb-2 animate-spin text-emerald-600" />
              <p class="text-[11px] font-medium">Sedang membuat gambar kuitansi resmi...</p>
            </div>

            <img
              v-else-if="imageUrl"
              :src="imageUrl"
              alt="Pratinjau Kuitansi SPP"
              class="w-full max-h-[320px] object-contain rounded-lg shadow-sm border border-zinc-200 dark:border-zinc-800 bg-white"
            />

            <div v-else class="p-6 text-center text-rose-500 text-[11px]">
              Gagal memuat pratinjau gambar kuitansi.
            </div>
          </div>
        </div>

        <!-- Ringkasan Teks Keterangan WhatsApp -->
        <details class="group rounded-xl border border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 p-2.5">
          <summary class="font-semibold text-zinc-600 dark:text-zinc-400 text-[11px] cursor-pointer flex items-center justify-between select-none">
            <span>Lihat Draf Teks Keterangan WA</span>
            <span class="text-[10px] text-emerald-600 group-open:rotate-180 transition-transform">&darr;</span>
          </summary>
          <pre class="mt-2 p-2 rounded-lg bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-[10px] font-mono whitespace-pre-wrap text-zinc-700 dark:text-zinc-300 max-h-36 overflow-y-auto leading-relaxed">{{ captionMessage }}</pre>
        </details>

      </div>

      <!-- Action Buttons (Sticky Footer) -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 pt-2.5 border-t border-zinc-100 dark:border-zinc-800 shrink-0">
        <button
          type="button"
          @click="emit('close')"
          class="order-3 sm:order-1 h-8 px-3.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 text-xs font-semibold hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition cursor-pointer"
        >
          Tutup
        </button>

        <div class="order-1 sm:order-2 flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
          <!-- Tombol Kirim Gateway (Fonnte API) jika ingin otomatis dari server -->
          <button
            type="button"
            @click="sendViaGateway"
            :disabled="!targetPhone || isSendingGateway || isGenerating"
            class="h-8 px-3 rounded-lg border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs"
            title="Kirim gambar kuitansi langsung via gateway Fonnte tanpa membuka WhatsApp"
          >
            <RefreshCw v-if="isSendingGateway" class="w-3.5 h-3.5 animate-spin" />
            <Send v-else class="w-3.5 h-3.5" />
            <span>{{ isSendingGateway ? 'Mengirim...' : 'Kirim via Gateway' }}</span>
          </button>

          <!-- Tombol Utama: Kirim WhatsApp Langsung (Mobile Native Share / WhatsApp Web Copy) -->
          <button
            type="button"
            @click="sendDirectWhatsApp"
            :disabled="!targetPhone || isGenerating"
            class="h-8 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20 transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            title="Kirim kuitansi langsung ke WhatsApp wali santri"
          >
            <BaseIcon name="whatsapp" class="w-3.5 h-3.5" />
            <span>Kirim ke WhatsApp</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import BaseIcon from './BaseIcon.vue';
import { X, RefreshCw, Send, Download } from '@lucide/vue';
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

function formatRupiah(val) {
  if (!val) return '0';
  return Number(val).toLocaleString('id-ID');
}

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

// Generate Draf Pesan Keterangan WhatsApp Resmi
const captionMessage = computed(() => {
  if (!props.kuitansiData) return '';
  const kw = props.kuitansiData;
  const bendaharaName = kw.nama_bendahara || 'Ustadzah Ummi';
  const tglLengkap = kw.tanggal_bayar ? `${kw.tanggal_bayar} WIB` : '-';

  return `*BUKTI PEMBAYARAN RESMI SPP*
*TPQ NURUL MUN'IM PAITON*
━━━━━━━━━━━━━━━━━━━━━━━

_Assalamu’alaikum Warahmatullahi Wabarakatuh_

Yth. Bapak/Ibu Wali dari ananda:
👤 *${kw.nama_santri || '-'}* (NISN: ${kw.nisn || '-'})
📚 Kelas / Jilid: *${kw.kelas || '-'}*

Alhamdulillah, pembayaran iuran SPP ananda telah kami terima dan tercatat sah pada sistem administrasi TPQ Nurul Mun'im dengan rincian:

🧾 *No. Kuitansi* : ${kw.nomor_kuitansi || '-'}
🗓 *Tanggal Bayar*: ${tglLengkap}
📌 *Keperluan*    : ${kw.pembayaran_untuk || '-'}
💳 *Metode Bayar* : ${kw.metode || 'Tunai'}
💰 *Jumlah Bayar* : *Rp ${formatRupiah(kw.jumlah_bayar)}*
✅ *Status*       : *LUNAS (SAH)*
${kw.catatan ? '📝 *Catatan*      : ' + kw.catatan + '\n' : ''}━━━━━━━━━━━━━━━━━━━━━━━

_Jazakumullahu khairan katsiran_ atas kepedulian Bapak/Ibu dalam mendukung pendidikan Al-Qur'an ananda. Semoga Allah SWT membalas dengan keberkahan rezeki yang berlimpah, serta ananda menjadi generasi sholeh/sholehah yang mencintai Al-Qur'an. Aamiin ya Rabbal 'Alamin.

_Wassalamu’alaikum Warahmatullahi Wabarakatuh_

Hormat kami,
*Bendahara TPQ Nurul Mun'im*
(${bendaharaName})
_Karanganyar, Paiton, Probolinggo_`;
});

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
    toast.error('Gagal membuat gambar kuitansi kuitansi.');
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

// Download Gambar Kuitansi
function downloadImage() {
  if (!imageUrl.value) return;
  const link = document.createElement('a');
  link.href = imageUrl.value;
  link.download = `Kuitansi-SPP-${props.kuitansiData?.nomor_kuitansi || 'Resmi'}.png`;
  link.click();
}

// 1. Pengiriman Langsung via WhatsApp (Web Share API di HP, Copy to Clipboard di Desktop)
async function sendDirectWhatsApp() {
  const cleanPhone = cleanPhoneNumber(targetPhone.value);
  if (!cleanPhone) {
    toast.warning('Nomor WhatsApp tujuan belum diisi.');
    return;
  }

  const text = captionMessage.value;

  // A. Cek apakah Web Share API mendukung berbagi file (fitur native di smartphone Android/iOS)
  if (navigator.share && imageFile.value && navigator.canShare && navigator.canShare({ files: [imageFile.value] })) {
    try {
      await navigator.share({
        title: 'Kuitansi Pembayaran SPP',
        text: text,
        files: [imageFile.value],
      });
      toast.success('Kuitansi berhasil dibagikan ke WhatsApp.');
      emit('sent');
      return;
    } catch (err) {
      if (err.name !== 'AbortError') {
        console.warn('Web share file gagal, beralih ke fallback WhatsApp Web:', err);
      } else {
        return; // Dibatalkan oleh pengguna
      }
    }
  }

  // B. Fallback Desktop / Browser Standar:
  // Salin gambar kuitansi ke clipboard, lalu buka WhatsApp Web
  try {
    if (imageBlob.value && navigator.clipboard && window.ClipboardItem) {
      await navigator.clipboard.write([
        new ClipboardItem({ 'image/png': imageBlob.value })
      ]);
      toast.info('📋 Gambar kuitansi berhasil disalin ke clipboard! Tekan Ctrl+V di chat WhatsApp untuk menempelkannya.');
    }
  } catch (err) {
    console.warn('Gagal menyalin gambar ke clipboard:', err);
  }

  // Buka WhatsApp Web / Link wa.me
  const waUrl = `https://web.whatsapp.com/send?phone=${cleanPhone}&text=${encodeURIComponent(text)}`;
  const fallbackUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(text)}`;

  const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
  window.open(isMobile ? fallbackUrl : waUrl, '_blank');
  emit('sent');
}

// 2. Pengiriman Otomatis via Gateway Fonnte API (Server ke WhatsApp Wali)
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
    const res = await api.post('/wa/send-kuitansi-image', {
      phone: cleanPhone,
      message: captionMessage.value,
      image_base64: imageUrl.value,
      nomor_kuitansi: props.kuitansiData?.nomor_kuitansi,
      nama_penerima: props.kuitansiData?.nama_santri,
    });

    if (res.data?.success) {
      toast.success('Kuitansi gambar berhasil dikirim ke WhatsApp via Gateway Fonnte!');
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
