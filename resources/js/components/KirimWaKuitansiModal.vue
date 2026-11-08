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
              Format gambar resmi &bull; Langsung ke nomor tujuan wali santri
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
          <div v-else-if="phoneWarning" class="p-2 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-[10px] leading-relaxed">
            <p class="font-semibold">
              ⚠️ {{ phoneWarning }}
            </p>
            <p class="mt-0.5 text-zinc-600 dark:text-zinc-300">
              Silakan periksa kembali nomor HP di profil santri atau gunakan tombol <b>Buka Chat WA</b> di bawah.
            </p>
          </div>
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

        <!-- Info Singkat Metode Pengiriman -->
        <div class="p-2.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-800/40 text-[11px] text-emerald-800 dark:text-emerald-300 space-y-1">
          <div class="flex items-center gap-1.5 font-bold">
            <span>💡</span>
            <span>Pilihan Pengiriman WhatsApp:</span>
          </div>
          <ul class="list-disc pl-5 space-y-0.5 text-[10.5px] leading-relaxed text-zinc-700 dark:text-zinc-300">
            <li><b>Kirim via Gateway:</b> Mengirim gambar kuitansi otomatis langsung dari server ke WhatsApp wali santri tanpa perlu membuka WhatsApp.</li>
            <li><b>Buka Chat WA:</b> Langsung membuka chat nomor wali santri dengan rincian bukti pembayaran otomatis terisi (tinggal klik kirim di WhatsApp).</li>
          </ul>
        </div>
      </div>

      <!-- Action Buttons (Sticky Footer) -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 pt-2.5 border-t border-zinc-100 dark:border-zinc-800 shrink-0">
        <div class="flex items-center gap-2 order-2 sm:order-1">
          <button
            type="button"
            @click="emit('close')"
            class="h-8 px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 text-xs font-semibold hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition cursor-pointer"
          >
            Tutup
          </button>

          <!-- Tombol Unduh PNG -->
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
          <!-- Tombol Kirim Gateway (Fonnte API) -->
          <button
            type="button"
            @click="sendViaGateway"
            :disabled="!targetPhone || isSendingGateway || isGenerating"
            class="h-8 px-3.5 rounded-lg border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs"
            title="Kirim gambar kuitansi langsung ke WhatsApp wali santri via gateway server"
          >
            <RefreshCw v-if="isSendingGateway" class="w-3.5 h-3.5 animate-spin" />
            <Send v-else class="w-3.5 h-3.5" />
            <span>{{ isSendingGateway ? 'Mengirim...' : 'Kirim via Gateway' }}</span>
          </button>

          <!-- Tombol Buka Chat WhatsApp Langsung ke Nomor Wali Santri -->
          <button
            type="button"
            @click="sendDirectWhatsApp"
            :disabled="!targetPhone || isGenerating"
            class="h-8 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20 transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            title="Buka obrolan WhatsApp langsung ke nomor wali santri dengan teks otomatis terisi"
          >
            <BaseIcon name="whatsapp" class="w-3.5 h-3.5" />
            <span>Buka Chat WA</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
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
const phoneWarning = ref('');

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

// Resolusi nama periode/bulan pembayaran kuitansi secara cerdas dan akurat
function getPeriodeText(k) {
  if (!k) return 'SPP';

  // 1. Cek properti periode eksplisit (misal: "November 2026")
  if (k.periode && String(k.periode).trim()) {
    return String(k.periode).replace(/^SPP\s+/i, '').trim();
  }

  // 2. Cek daftar_bulan_str (misal: "November 2026" atau "November 2026, Desember 2026")
  if (k.daftar_bulan_str && String(k.daftar_bulan_str).trim()) {
    return String(k.daftar_bulan_str).trim();
  }

  // 3. Cek pembayaran_untuk (misal: "SPP November 2026" -> hilangkan awalan "SPP ")
  if (k.pembayaran_untuk && String(k.pembayaran_untuk).trim()) {
    const cleaned = String(k.pembayaran_untuk).replace(/^SPP\s+/i, '').trim();
    if (cleaned) return cleaned;
  }

  // 4. Cek bulan_tagihan (misal: "November 2026")
  if (k.bulan_tagihan && String(k.bulan_tagihan).trim()) {
    return String(k.bulan_tagihan).replace(/^SPP\s+/i, '').trim();
  }

  const namaBulanMap = {
    1: 'Januari', 2: 'Februari', 3: 'Maret', 4: 'April',
    5: 'Mei', 6: 'Juni', 7: 'Juli', 8: 'Agustus',
    9: 'September', 10: 'Oktober', 11: 'November', 12: 'Desember',
  };

  // 5. Cek bulan dan tahun langsung dari data
  const bln = k.bulan || k.tagihan?.bulan;
  const thn = k.tahun || k.tagihan?.tahun;
  if (bln && namaBulanMap[Number(bln)]) {
    return `${namaBulanMap[Number(bln)]} ${thn || ''}`.trim();
  }

  // 6. Cek array daftar_bulan
  if (Array.isArray(k.daftar_bulan) && k.daftar_bulan.length > 0) {
    return k.daftar_bulan.map(b => b.label || b.nama_bulan || (namaBulanMap[b.bulan] ? `${namaBulanMap[b.bulan]} ${b.tahun}` : b)).join(', ');
  }

  // 7. Ekstraksi otomatis dari nomor kuitansi (Format standar: KW-YYYYMM-XXXXX, misal KW-202611-00031 -> November 2026)
  if (k.nomor_kuitansi) {
    const match = String(k.nomor_kuitansi).match(/KW-(\d{4})(\d{2})-/i);
    if (match) {
      const year = match[1];
      const monthNum = parseInt(match[2], 10);
      if (namaBulanMap[monthNum]) {
        return `${namaBulanMap[monthNum]} ${year}`;
      }
    }
  }

  return 'SPP Bulanan';
}

// Render Gambar Kuitansi setiap kali modal dibuka atau data kuitansi tersedia
async function renderImage() {
  if (!props.kuitansiData) return;
  isGenerating.value = true;
  try {
    if (props.kuitansiData.no_hp_ortu) {
      targetPhone.value = props.kuitansiData.no_hp_ortu;
    }
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
  [() => props.show, () => props.kuitansiData],
  ([showVal, dataVal]) => {
    phoneWarning.value = '';
    if (showVal && dataVal) {
      targetPhone.value = dataVal.no_hp_ortu || targetPhone.value || '';
      renderImage();
    } else if (!showVal) {
      imageUrl.value = '';
      imageBlob.value = null;
      imageFile.value = null;
    }
  },
  { immediate: true, deep: true },
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

// 1. Pengiriman Otomatis via Gateway Fonnte API (Server Mengirim Gambar Kuitansi Langsung ke Wali Santri)
async function sendViaGateway() {
  phoneWarning.value = '';
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
    const k = props.kuitansiData || {};
    const santri = k.nama_santri || 'Santri';
    const kelas = k.kelas ? ` (${k.kelas})` : '';
    const noKw = k.nomor_kuitansi || '-';
    const periode = getPeriodeText(k);
    const nominal = Number(k.jumlah_bayar || k.total_bayar || 0).toLocaleString('id-ID');
    const tanggal = k.tanggal_bayar || new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    const bendahara = k.nama_bendahara || 'Bendahara TPQ';
    const catatanTambahan = (k.catatan && k.catatan !== 'Input Cepat SPP' && !k.catatan.toLowerCase().startsWith('pelunasan multi-bulan'))
      ? `\n• Catatan: _${k.catatan}_`
      : '';

    const caption = `*BUKTI PEMBAYARAN SPP RESMI*\nTPQ Nurul Mun'im\n\n• No. Kuitansi: *${noKw}*\n• Nama: *${santri}*${kelas}\n• Periode: *${periode}*\n• Total: *Rp ${nominal}*\n• Tanggal: *${tanggal}*\n• Status: *LUNAS*\n• Bendahara: *${bendahara}*${catatanTambahan}\n\nJazakumullah khairan katsiran.`;

    const res = await api.post('/wa/send-kuitansi-image', {
      phone: cleanPhone,
      message: caption,
      image_base64: imageUrl.value,
      nomor_kuitansi: props.kuitansiData?.nomor_kuitansi,
      nama_penerima: props.kuitansiData?.nama_santri,
    });

    if (res.data?.success) {
      toast.success(res.data?.message || 'Kuitansi berhasil dikirim ke nomor wali santri via Gateway!');
      emit('sent');
      emit('close');
    } else {
      toast.error(res.data?.message || 'Gagal mengirim kuitansi via Gateway.');
    }
  } catch (err) {
    const errMessage = err.response?.data?.message || err.message || 'Terjadi kesalahan gateway.';
    toast.error(`Kirim Gateway Gagal: ${errMessage}`);
    if (errMessage.toLowerCase().includes('tidak terdaftar')) {
      phoneWarning.value = errMessage;
    }
  } finally {
    isSendingGateway.value = false;
  }
}

// 2. Buka Chat WhatsApp Langsung Mengarah Otomatis ke Nomor Wali Santri dengan Teks Otomatis Terisi
async function sendDirectWhatsApp() {
  const cleanPhone = cleanPhoneNumber(targetPhone.value);
  if (!cleanPhone) {
    toast.warning('Nomor WhatsApp tujuan belum diisi.');
    return;
  }

  // Salin gambar kuitansi ke Clipboard secara otomatis (jika didukung peramban desktop untuk Ctrl+V)
  try {
    if (imageBlob.value && navigator.clipboard && window.ClipboardItem) {
      await navigator.clipboard.write([
        new ClipboardItem({ 'image/png': imageBlob.value }),
      ]);
    }
  } catch (err) {
    // Graceful ignore
  }

  const k = props.kuitansiData || {};
  const namaSantri = k.nama_santri || 'Santri';
  const kelas = k.kelas ? ` (${k.kelas})` : '';
  const noKw = k.nomor_kuitansi || '-';
  const periode = getPeriodeText(k);
  const nominal = Number(k.jumlah_bayar || k.total_bayar || 0).toLocaleString('id-ID');
  const tanggal = k.tanggal_bayar || new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
  const bendahara = k.nama_bendahara || 'Bendahara TPQ';
  const catatanTambahan = (k.catatan && k.catatan !== 'Input Cepat SPP' && !k.catatan.toLowerCase().startsWith('pelunasan multi-bulan'))
    ? `\n• Catatan: _${k.catatan}_`
    : '';

  const textMessage = `Assalamu'alaikum Warahmatullahi Wabarakatuh.
Yth. Bapak/Ibu Wali dari ananda *${namaSantri}*${kelas},

Alhamdulillah, pembayaran SPP telah kami terima dan tercatat di sistem:
• No. Kuitansi: *${noKw}*
• Periode: *${periode}*
• Total Bayar: *Rp ${nominal}*
• Tanggal: *${tanggal}*
• Status: *LUNAS*
• Bendahara: *${bendahara}*${catatanTambahan}

Jazakumullah khairan katsiran atas pembayaran kewajiban administrasi santri.
Wassalamu'alaikum Warahmatullahi Wabarakatuh.`;

  const encodedText = encodeURIComponent(textMessage);
  const waUrl = `https://api.whatsapp.com/send?phone=${cleanPhone}&text=${encodedText}`;
  window.open(waUrl, '_blank');

  toast.success('Membuka chat WhatsApp nomor wali santri dengan rincian kuitansi otomatis terisi.');
  emit('sent');
}
</script>
