<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between pb-2 border-b">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-lg font-semibold tracking-tight text-foreground">
            Pengaturan
          </h2>
          <Badge variant="outline" class="font-normal text-xs text-muted-foreground">
            Konfigurasi
          </Badge>
        </div>
        <p class="text-xs text-muted-foreground mt-0.5">
          Atur identitas lembaga, jam operasional presensi, dan koneksi WhatsApp Gateway Fonnte.
        </p>
      </div>

      <!-- Action Button -->
      <Button
        size="sm"
        class="h-8 gap-1.5 text-xs"
        :disabled="isSaving"
        @click="saveSettings"
      >
        <LoaderCircle v-if="isSaving" class="size-3.5 animate-spin" />
        <Check v-else class="size-3.5" />
        <span>Simpan Pengaturan</span>
      </Button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="p-12 text-center text-muted-foreground">
      <LoaderCircle class="size-8 mx-auto animate-spin mb-2 text-primary" />
      <p class="text-xs font-medium">Memuat pengaturan sistem...</p>
    </div>

    <!-- Settings Grid -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      
      <!-- Card 1: Profil Lembaga -->
      <Card class="flex flex-col justify-between">
        <CardHeader class="pb-3 border-b">
          <div class="flex items-center gap-2">
            <Building2 class="size-4 text-primary" />
            <CardTitle class="text-sm font-semibold">1. Profil &amp; Identitas Lembaga</CardTitle>
          </div>
          <CardDescription class="text-xs">
            Identitas resmi lembaga, logo favicon web, dan kop cetak laporan presensi.
          </CardDescription>
        </CardHeader>

        <CardContent class="space-y-4 pt-4">
          <!-- Logo Lembaga (Favicon & Kop Surat) -->
          <div class="space-y-2">
            <Label class="text-xs font-medium">Logo Lembaga (Favicon &amp; Kop Laporan)</Label>
            <div class="flex items-center gap-4">
              <!-- Preview Box -->
              <div class="relative flex size-20 shrink-0 items-center justify-center rounded-xl border-2 border-dashed border-input bg-muted/40 p-1.5 overflow-hidden group">
                <img
                  v-if="settings.logo_lembaga"
                  :src="settings.logo_lembaga"
                  alt="Logo Lembaga"
                  class="size-full object-contain"
                />
                <div v-else class="flex flex-col items-center justify-center text-muted-foreground text-[10px]">
                  <ImageIcon class="size-6 text-muted-foreground/60 mb-1" />
                  <span>Tanpa Logo</span>
                </div>
              </div>

              <!-- Upload Actions -->
              <div class="flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                  <input
                    ref="logoFileInputRef"
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                    class="hidden"
                    @change="handleLogoUpload"
                  />
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1.5 text-xs"
                    @click="triggerLogoSelect"
                  >
                    <Upload class="size-3.5" />
                    <span>{{ settings.logo_lembaga ? 'Ganti Logo' : 'Pilih Logo' }}</span>
                  </Button>
                  <Button
                    v-if="settings.logo_lembaga"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-8 gap-1.5 text-xs text-destructive hover:text-destructive hover:bg-destructive/10"
                    @click="removeLogo"
                  >
                    <Trash2 class="size-3.5" />
                    <span>Hapus</span>
                  </Button>
                </div>
                <p class="text-[10px] text-muted-foreground leading-relaxed">
                  Format PNG / JPG / SVG (disarankan transparan). Otomatis dijadikan <strong>Favicon</strong> browser dan <strong>Kop Surat</strong> cetak laporan.
                </p>
              </div>
            </div>
          </div>

          <div class="space-y-1.5">
            <Label for="namaInstansi" class="text-xs font-medium">Nama Instansi / TPQ / Lembaga *</Label>
            <Input
              id="namaInstansi"
              v-model="settings.nama_instansi"
              type="text"
              placeholder="Contoh: TPQ & Pesantren Nurul Mun'im"
              class="h-9 text-xs"
            />
          </div>

          <div class="space-y-1.5">
            <Label for="alamatInstansi" class="text-xs font-medium">Alamat Lembaga (Kop Laporan)</Label>
            <Input
              id="alamatInstansi"
              v-model="settings.alamat_instansi"
              type="text"
              placeholder="Contoh: Jl. Raya Karanganyar No. 15, Paiton, Probolinggo"
              class="h-9 text-xs"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <Label for="kontakInstansi" class="text-xs font-medium">Kontak / Telepon / Email</Label>
              <Input
                id="kontakInstansi"
                v-model="settings.kontak_instansi"
                type="text"
                placeholder="Contoh: Telp: 0812-3456-7890"
                class="h-9 text-xs"
              />
            </div>

            <div class="space-y-1.5">
              <Label for="tahunAjaran" class="text-xs font-medium">Tahun Ajaran Aktif</Label>
              <Input
                id="tahunAjaran"
                v-model="settings.tahun_ajaran_aktif"
                type="text"
                placeholder="Contoh: 2026/2027"
                class="h-9 text-xs"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Card 2: Jam Operasional & Batas Terlambat -->
      <Card class="flex flex-col justify-between">
        <CardHeader class="pb-3 border-b">
          <div class="flex items-center gap-2">
            <Clock class="size-4 text-primary" />
            <CardTitle class="text-sm font-semibold">2. Jam Operasional Absensi</CardTitle>
          </div>
          <CardDescription class="text-xs">
            Jadwal batas waktu jam masuk presensi santri dan status otomatis terlambat.
          </CardDescription>
        </CardHeader>

        <CardContent class="space-y-4 pt-4">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="space-y-1.5">
              <Label for="jamMasuk" class="text-xs font-medium">Jam Masuk</Label>
              <Input
                id="jamMasuk"
                v-model="settings.jam_masuk"
                type="time"
                class="h-9 text-xs"
              />
              <p class="text-[10px] text-muted-foreground">Mulai tap masuk</p>
            </div>

            <div class="space-y-1.5">
              <Label for="jamTerlambat" class="text-xs font-medium">Batas Terlambat</Label>
              <Input
                id="jamTerlambat"
                v-model="settings.jam_terlambat"
                type="time"
                class="h-9 text-xs"
              />
              <p class="text-[10px] text-muted-foreground">Lewat jam ini = Terlambat</p>
            </div>

            <div class="space-y-1.5">
              <Label for="jamPulang" class="text-xs font-medium">Jam Pulang</Label>
              <Input
                id="jamPulang"
                v-model="settings.jam_pulang"
                type="time"
                class="h-9 text-xs"
              />
              <p class="text-[10px] text-muted-foreground">Waktu tap kepulangan</p>
            </div>
          </div>

          <div class="space-y-2 pt-1">
            <Label class="text-xs font-medium">Hari Aktif Kegiatan</Label>
            <div class="flex flex-wrap gap-2">
              <label
                v-for="day in ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']"
                :key="day"
                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-xs cursor-pointer select-none transition-colors"
                :class="activeDays.includes(day) ? 'bg-primary/10 border-primary/40 text-primary font-medium' : 'bg-muted/30 text-muted-foreground hover:bg-muted/60'"
              >
                <input
                  type="checkbox"
                  :value="day"
                  v-model="activeDays"
                  class="rounded border-input text-primary focus:ring-ring size-3.5 cursor-pointer"
                />
                <span>{{ day }}</span>
              </label>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Card 3: WhatsApp Gateway Fonnte -->
      <Card class="flex flex-col justify-between">
        <CardHeader class="pb-3 border-b">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <MessageSquare class="size-4 text-primary" />
              <CardTitle class="text-sm font-semibold">3. WhatsApp Gateway (Fonnte API)</CardTitle>
            </div>
            <Badge
              :variant="settings.fonnte_token ? 'default' : 'secondary'"
              class="text-[10px] font-semibold"
            >
              {{ settings.fonnte_token ? 'Token Terisi' : 'Belum Diisi' }}
            </Badge>
          </div>
          <CardDescription class="text-xs">
            Integrasi pengiriman pesan notifikasi otomatis absensi dan chatbot wali santri.
          </CardDescription>
        </CardHeader>

        <CardContent class="space-y-4 pt-4">
          <div class="space-y-1.5">
            <Label for="fonnteToken" class="text-xs font-medium">API Token Fonnte</Label>
            <Input
              id="fonnteToken"
              v-model="settings.fonnte_token"
              type="password"
              placeholder="Masukkan API Token Fonnte..."
              class="h-9 font-mono text-xs"
            />
            <p class="text-[10px] text-muted-foreground leading-relaxed">
              Dapatkan token dengan mendaftar di <strong>fonnte.com</strong> dan hubungkan nomor WhatsApp lembaga Anda.
            </p>
          </div>

          <div class="pt-1">
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="h-8 gap-1.5 text-xs"
              :disabled="!settings.fonnte_token || isTestingWa"
              @click="testWaGateway"
            >
              <LoaderCircle v-if="isTestingWa" class="size-3.5 animate-spin" />
              <Radio v-else class="size-3.5 text-primary" />
              <span>Uji Koneksi Gateway Fonnte</span>
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- Card 4: Notifikasi Telegram (Opsional) -->
      <Card class="flex flex-col justify-between">
        <CardHeader class="pb-3 border-b">
          <div class="flex items-center gap-2">
            <Send class="size-4 text-primary" />
            <CardTitle class="text-sm font-semibold">4. Bot Telegram (Opsional)</CardTitle>
          </div>
          <CardDescription class="text-xs">
            Notifikasi rekapitulasi ringkasan ke grup telegram admin atau pengurus TPQ.
          </CardDescription>
        </CardHeader>

        <CardContent class="space-y-4 pt-4">
          <div class="space-y-1.5">
            <Label for="teleBotToken" class="text-xs font-medium">Bot Token</Label>
            <Input
              id="teleBotToken"
              v-model="settings.telegram_bot_token"
              type="password"
              placeholder="Contoh: 123456:ABC-DEF1234ghIkl..."
              class="h-9 font-mono text-xs"
            />
          </div>

          <div class="space-y-1.5">
            <Label for="teleChatId" class="text-xs font-medium">Chat ID Admin / Grup</Label>
            <Input
              id="teleChatId"
              v-model="settings.telegram_chat_id"
              type="text"
              placeholder="Contoh: -100123456789 atau ID Chat Anda"
              class="h-9 font-mono text-xs"
            />
          </div>
        </CardContent>
      </Card>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import {
  Check,
  Building2,
  Clock,
  MessageSquare,
  Send,
  Radio,
  LoaderCircle,
  Image as ImageIcon,
  Upload,
  Trash2,
} from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';
import { useAppConfig } from '@/composables/useAppConfig';

const toast = useToast();
const { updateConfig } = useAppConfig();

const isLoading = ref(false);
const isSaving = ref(false);
const isTestingWa = ref(false);
const logoFileInputRef = ref(null);

const settings = ref({
  nama_instansi: "Sistem Terpadu Absensi Santri",
  tahun_ajaran_aktif: "2026/2027",
  alamat_instansi: "",
  kontak_instansi: "",
  logo_lembaga: "",
  jam_masuk: "07:00",
  jam_terlambat: "07:15",
  jam_pulang: "12:00",
  hari_aktif: "Senin,Selasa,Rabu,Kamis,Jumat,Sabtu",
  fonnte_token: "",
  telegram_bot_token: "",
  telegram_chat_id: "",
});

const activeDays = ref(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']);

const triggerLogoSelect = () => {
  if (logoFileInputRef.value) {
    logoFileInputRef.value.click();
  }
};

const handleLogoUpload = (e) => {
  const file = e.target.files?.[0];
  if (!file) return;

  if (!file.type.startsWith('image/')) {
    toast.error('File yang dipilih harus berupa format gambar.');
    return;
  }

  const reader = new FileReader();
  reader.onload = (readerEvent) => {
    const img = new Image();
    img.onload = () => {
      // Resize to max 256x256 to save bandwidth and fit neatly in TiDB
      const maxDim = 256;
      let width = img.width;
      let height = img.height;

      if (width > height) {
        if (width > maxDim) {
          height = Math.round((height * maxDim) / width);
          width = maxDim;
        }
      } else {
        if (height > maxDim) {
          width = Math.round((width * maxDim) / height);
          height = maxDim;
        }
      }

      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0, width, height);

      // PNG format to preserve alpha channel transparency
      const compressedDataUrl = canvas.toDataURL('image/png', 0.9);
      settings.value.logo_lembaga = compressedDataUrl;
      toast.success('Logo berhasil dipilih! Klik "Simpan Pengaturan" untuk menerapkan.');
    };
    img.src = readerEvent.target.result;
  };
  reader.readAsDataURL(file);
};

const removeLogo = () => {
  settings.value.logo_lembaga = '';
  if (logoFileInputRef.value) {
    logoFileInputRef.value.value = '';
  }
  toast.info('Logo dihapus. Klik "Simpan Pengaturan" untuk menerapkan.');
};

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
    const payload = {
      ...settings.value,
      hari_aktif: activeDays.value.join(','),
    };

    const res = await api.post('/pengaturan', payload);
    updateConfig(settings.value);
    toast.success(res.data.message || 'Pengaturan sistem berhasil disimpan.');
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal menyimpan pengaturan sistem.';
    toast.error(msg);
  } finally {
    isSaving.value = false;
  }
}

async function testWaGateway() {
  if (!settings.value.fonnte_token) {
    toast.warning('Silakan masukkan token Fonnte terlebih dahulu.');
    return;
  }

  isTestingWa.value = true;
  try {
    const res = await api.post('/pengaturan/test-wa', {
      token: settings.value.fonnte_token,
    });
    if (res.data.success) {
      toast.success(res.data.message || 'Koneksi ke gateway WhatsApp berhasil!');
    } else {
      toast.error(res.data.message || 'Koneksi ke gateway WhatsApp gagal.');
    }
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
