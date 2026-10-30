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

          <!-- Pengaturan Otomatis Notifikasi Presensi Santri -->
          <div class="pt-3 border-t space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div class="space-y-0.5">
                <Label for="waNotifPresensi" class="text-xs font-semibold cursor-pointer">
                  Kirim Notifikasi WA Presensi Otomatis
                </Label>
                <p class="text-[11px] text-muted-foreground leading-relaxed">
                  Kirim otomatis notifikasi WhatsApp ke nomor HP orang tua begitu santri berhasil scan presensi masuk.
                </p>
              </div>
              <input
                id="waNotifPresensi"
                v-model="settings.wa_notif_presensi"
                type="checkbox"
                true-value="1"
                false-value="0"
                class="size-4 rounded border-input text-primary focus:ring-ring cursor-pointer mt-0.5"
              />
            </div>

            <!-- Pengaturan Jeda Waktu (Anti-Spam Fonnte & WA) -->
            <div v-if="settings.wa_notif_presensi === '1'" class="space-y-1.5 pt-1">
              <div class="flex items-center justify-between">
                <Label for="waNotifDelay" class="text-xs font-medium">
                  Jeda Antrean Scan Bersamaan (Anti-Spam Delay)
                </Label>
                <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full">
                  Proteksi Anti-Spam
                </span>
              </div>
              <select
                id="waNotifDelay"
                v-model="settings.wa_notif_delay"
                class="w-full h-9 rounded-lg border border-input bg-background px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring shadow-2xs cursor-pointer"
              >
                <option value="3-5">3 - 5 detik (Direkomendasikan - Aman & Alami)</option>
                <option value="5-10">5 - 10 detik (Sangat Aman untuk Akun Baru)</option>
                <option value="2-3">2 - 3 detik (Cepat)</option>
                <option value="1">1 detik (Minimum Jeda)</option>
              </select>
              <p class="text-[10px] text-muted-foreground leading-relaxed">
                Jika beberapa santri scan dalam waktu berdekatan, sistem otomatis memberikan jeda bertingkat pada antrean pesan sehingga nomor WhatsApp lembaga terhindar dari pemblokiran spam.
              </p>
            </div>
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

      <!-- Card 5: Aplikasi Mobile PWA (Progressive Web App) -->
      <Card class="flex flex-col justify-between">
        <CardHeader class="pb-3 border-b">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Smartphone class="size-4 text-primary" />
              <CardTitle class="text-sm font-semibold">5. Aplikasi Mobile (PWA)</CardTitle>
            </div>
            <Badge variant="outline" class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/30">
              PWA Aktif
            </Badge>
          </div>
          <CardDescription class="text-xs">
            Aplikasi dapat dipasang di layar utama HP (Android / iOS) atau Desktop tanpa melalui Play Store.
          </CardDescription>
        </CardHeader>

        <CardContent class="space-y-4 pt-4">
          <div class="flex items-center gap-3 rounded-xl border p-3 bg-muted/20">
            <div class="size-12 rounded-xl bg-white border p-1 shadow-2xs shrink-0 overflow-hidden flex items-center justify-center">
              <img :src="'/icons/pwa-192x192.png'" alt="Ikon PWA" class="size-full object-contain" />
            </div>
            <div class="min-w-0 flex-1 text-xs">
              <p class="font-semibold text-foreground">Presensi Santri TPQ</p>
              <p class="text-muted-foreground text-[11px]">Ikon terverifikasi (192x192, 512x512, Maskable &amp; iOS)</p>
            </div>
            <Button
              v-if="isInstallable && !isInstalled"
              size="sm"
              class="h-8 gap-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white shrink-0"
              @click="installApp"
            >
              <Download class="size-3.5" />
              <span>Install</span>
            </Button>
            <Badge v-else-if="isInstalled" variant="secondary" class="text-[11px] font-medium shrink-0">
              Terpasang
            </Badge>
          </div>

          <div class="space-y-1.5 text-xs text-muted-foreground leading-relaxed">
            <p class="font-medium text-foreground">Panduan Instalasi di HP:</p>
            <ul class="list-disc list-inside space-y-1 text-[11px]">
              <li><strong>Android (Chrome/Edge):</strong> Klik tombol <em>"Install App"</em> di bilah atas/sidebar atau buka menu titik tiga ⋮ lalu pilih <em>"Tambahkan ke Layar Utama" / "Install Aplikasi"</em>.</li>
              <li><strong>iPhone / iPad (Safari):</strong> Buka website ini di Safari, ketuk tombol <strong>Bagikan (Share ⎋)</strong>, lalu pilih <strong>"Tambah ke Layar Utama"</strong>.</li>
            </ul>
          </div>
        </CardContent>
      </Card>

      <!-- Card 6: Integrasi Google Spreadsheet (Sheet Absensi Realtime) -->
      <Card class="flex flex-col justify-between">
        <CardHeader class="pb-3 border-b">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <FileSpreadsheet class="size-4 text-emerald-600 dark:text-emerald-400" />
              <CardTitle class="text-sm font-semibold">6. Google Spreadsheet (Sheet Absensi)</CardTitle>
            </div>
            <Badge
              :variant="settings.spreadsheet_absensi_webhook_url ? 'default' : 'secondary'"
              class="text-[10px] font-semibold"
            >
              {{ settings.spreadsheet_absensi_webhook_url ? 'Webhook Terisi' : 'Belum Diisi' }}
            </Badge>
          </div>
          <CardDescription class="text-xs">
            Sinkronkan otomatis data kehadiran dari web/scanner ke Google Spreadsheet sheet 'absensi' secara realtime.
          </CardDescription>
        </CardHeader>

        <CardContent class="space-y-4 pt-4">
          <div class="space-y-1.5">
            <Label for="sheetWebhookUrl" class="text-xs font-medium">URL Web App Google Apps Script</Label>
            <Input
              id="sheetWebhookUrl"
              v-model="settings.spreadsheet_absensi_webhook_url"
              type="text"
              placeholder="https://script.google.com/macros/s/.../exec"
              class="h-9 font-mono text-xs"
            />
            <p class="text-[10px] text-muted-foreground leading-relaxed">
              Database utama <strong>tetap di MySQL (TiDB Cloud)</strong>. Setiap santri scan, data otomatis ditambahkan ke sheet <code>absensi</code> di Google Spreadsheet Anda.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2 pt-1">
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="h-8 gap-1.5 text-xs"
              :disabled="!settings.spreadsheet_absensi_webhook_url || isTestingSpreadsheet"
              @click="testSpreadsheetWebhook"
            >
              <LoaderCircle v-if="isTestingSpreadsheet" class="size-3.5 animate-spin" />
              <Radio v-else class="size-3.5 text-emerald-600" />
              <span>Uji Kirim Baris ke Spreadsheet</span>
            </Button>

            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="h-8 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
              @click="showScriptTemplate = !showScriptTemplate"
            >
              <Code class="size-3.5" />
              <span>{{ showScriptTemplate ? 'Tutup Panduan Kode' : 'Lihat Kode Apps Script' }}</span>
            </Button>
          </div>

          <!-- Template Kode Apps Script -->
          <div v-if="showScriptTemplate" class="mt-3 rounded-lg border bg-muted/40 p-3 space-y-2.5">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-1.5 text-xs">
                <button
                  type="button"
                  class="px-2.5 py-1 rounded text-[11px] font-medium transition-colors"
                  :class="scriptMode === 'insert' ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-background text-muted-foreground hover:text-foreground border'"
                  @click="scriptMode = 'insert'"
                >
                  Sisipkan ke Code.gs Lama (Aman)
                </button>
                <button
                  type="button"
                  class="px-2.5 py-1 rounded text-[11px] font-medium transition-colors"
                  :class="scriptMode === 'full' ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-background text-muted-foreground hover:text-foreground border'"
                  @click="scriptMode = 'full'"
                >
                  Kode Lengkap Baru
                </button>
              </div>

              <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-6 px-2 text-[10px] gap-1"
                @click="copyAppsScriptCode"
              >
                <Check v-if="isScriptCopied" class="size-3 text-emerald-600" />
                <Copy v-else class="size-3" />
                <span>{{ isScriptCopied ? 'Tersalin!' : 'Salin Kode' }}</span>
              </Button>
            </div>

            <div v-if="scriptMode === 'insert'" class="text-[11px] text-muted-foreground leading-relaxed bg-amber-500/10 border border-amber-500/20 rounded p-2 text-amber-900 dark:text-amber-200">
              <strong>PENTING:</strong> Jangan hapus kode <code>Code.gs</code> lama agar website absensi Apps Script Anda tidak rusak. Cukup buka <code>Code.gs</code>, gulir ke bagian paling bawah ke fungsi <code>function doPost(e)</code>, lalu sisipkan potongan kode di bawah ini tepat di bawah <code>if (data.action === 'scanAbsensi') { ... }</code>.
            </div>

            <div v-else class="text-[11px] text-muted-foreground leading-relaxed bg-blue-500/10 border border-blue-500/20 rounded p-2 text-blue-900 dark:text-blue-200">
              Gunakan kode lengkap ini jika Anda membuat proyek Apps Script baru yang terpisah dari website lama.
            </div>

            <pre class="text-[10px] font-mono p-2.5 rounded bg-background border overflow-x-auto max-h-48 leading-relaxed text-muted-foreground select-all">{{ activeScriptCode }}</pre>

            <div class="space-y-1 text-[10px] text-muted-foreground">
              <p><strong>Langkah wajib setelah simpan kode di Apps Script:</strong></p>
              <ol class="list-decimal list-inside space-y-0.5">
                <li>Klik tombol <strong>Deploy (Terapkan)</strong> &gt; pilih <strong>Kelola Penerapan (Manage Deployments)</strong>.</li>
                <li>Klik ikon <strong>Pensil (Edit)</strong> pada penerapan aktif.</li>
                <li>Pada bagian <strong>Versi (Version)</strong>, pilih <strong>Versi baru (New version)</strong>.</li>
                <li>Klik <strong>Deploy (Terapkan)</strong>, lalu klik <em>"Uji Kirim Baris ke Spreadsheet"</em> di atas untuk memastikan.</li>
              </ol>
            </div>
          </div>
        </CardContent>
      </Card>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import {
  Check,
  Building2,
  Clock,
  Code,
  Copy,
  Download,
  FileSpreadsheet,
  MessageSquare,
  Send,
  Radio,
  LoaderCircle,
  Image as ImageIcon,
  Smartphone,
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
import { usePwa } from '@/composables/usePwa';

const toast = useToast();
const { updateConfig } = useAppConfig();
const { isInstallable, isInstalled, installApp } = usePwa();

const isLoading = ref(false);
const isSaving = ref(false);
const isTestingWa = ref(false);
const isTestingSpreadsheet = ref(false);
const showScriptTemplate = ref(false);
const scriptMode = ref('insert');
const isScriptCopied = ref(false);
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
  wa_notif_presensi: "1",
  wa_notif_delay: "3-5",
  spreadsheet_absensi_webhook_url: "",
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
      // Optimasi ukuran gambar agar Base64 ringkas (< 40.000 karakter)
      // Hal ini menjamin data selalu muat di kolom database tanpa error 'Data too long'
      let targetDim = 160;
      let compressedDataUrl = '';

      for (let attempt = 0; attempt < 5; attempt++) {
        let width = img.width;
        let height = img.height;

        if (width > height) {
          if (width > targetDim) {
            height = Math.round((height * targetDim) / width);
            width = targetDim;
          }
        } else {
          if (height > targetDim) {
            width = Math.round((width * targetDim) / height);
            height = targetDim;
          }
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, width);
        canvas.height = Math.max(1, height);
        const ctx = canvas.getContext('2d');
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        // Format PNG untuk menjaga transparansi logo
        compressedDataUrl = canvas.toDataURL('image/png');

        if (compressedDataUrl.length < 40000 || targetDim <= 70) {
          break;
        }
        targetDim -= 25;
      }

      settings.value.logo_lembaga = compressedDataUrl;
      toast.success('Logo berhasil dioptimalkan & dipilih! Klik "Simpan Pengaturan" untuk menerapkan.');
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

const appsScriptInsertSnippet = `      // === SINKRONISASI OTOMATIS DARI WEBSITE LARAVEL BARU KE SHEET 'absensi' ===
      if (data.action === 'syncAbsensi') {
        var ss = null;
        try {
          ss = SpreadsheetApp.getActiveSpreadsheet();
        } catch (e1) {}

        if (!ss) {
          ss = getSpreadsheet();
        }

        var sheet = ss.getSheetByName('absensi');
        if (!sheet) {
          sheet = ss.insertSheet('absensi');
          sheet.appendRow(['Tanggal', 'NISN', 'Nama', 'Kelas', 'Jam Datang', 'Jam Pulang', 'Keterangan Waktu', 'Status']);
        }

        var newRow = [
          data.tanggal || Utilities.formatDate(new Date(), 'Asia/Jakarta', 'MM/dd/yyyy'),
          "'" + String(data.nisn || '').replace(/^'/, ''),
          data.nama || '-',
          data.kelas || '-',
          data.jam_datang || '-',
          data.jam_pulang || '-',
          data.keterangan_waktu || '-',
          data.status || 'Hadir'
        ];

        sheet.appendRow(newRow);
        SpreadsheetApp.flush(); // Terapkan langsung ke spreadsheet

        return ContentService.createTextOutput(JSON.stringify({
          success: true,
          message: 'Baris kehadiran berhasil ditambahkan ke sheet absensi!',
          row: newRow
        })).setMimeType(ContentService.MimeType.JSON);
      }`;

const appsScriptFullTemplate = `/**
 * KODE SINKRONISASI ABSENSI OTOMATIS (WEB LARAVEL -> GOOGLE SPREADSHEET)
 * Gunakan ini jika spreadsheet belum memiliki script sama sekali.
 */
function doGet(e) {
  return handleSyncAbsensi(e);
}

function doPost(e) {
  return handleSyncAbsensi(e);
}

function handleSyncAbsensi(e) {
  try {
    var data = {};
    if (e && e.postData && e.postData.contents) {
      try {
        data = JSON.parse(e.postData.contents);
      } catch (errJson) {
        data = e.parameter || {};
      }
    } else if (e && e.parameter) {
      data = e.parameter;
    }

    if (data.action === 'syncAbsensi') {
      var ss = null;
      try {
        ss = SpreadsheetApp.getActiveSpreadsheet();
      } catch (e1) {}

      if (!ss) {
        ss = SpreadsheetApp.openById('1mVn1iXEGDYQoQ1Ft-_nHgC7WOGJeuAg--Bu-cdoiTNk');
      }

      var sheet = ss.getSheetByName('absensi');
      if (!sheet) {
        sheet = ss.insertSheet('absensi');
        sheet.appendRow(['Tanggal', 'NISN', 'Nama', 'Kelas', 'Jam Datang', 'Jam Pulang', 'Keterangan Waktu', 'Status']);
      }

      sheet.appendRow([
        data.tanggal || Utilities.formatDate(new Date(), 'Asia/Jakarta', 'MM/dd/yyyy'),
        "'" + String(data.nisn || '').replace(/^'/, ''),
        data.nama || '-',
        data.kelas || '-',
        data.jam_datang || '-',
        data.jam_pulang || '-',
        data.keterangan_waktu || '-',
        data.status || 'Hadir'
      ]);

      SpreadsheetApp.flush();

      return ContentService.createTextOutput(JSON.stringify({ 
        success: true, 
        message: 'Baris kehadiran berhasil ditambahkan ke sheet absensi!' 
      })).setMimeType(ContentService.MimeType.JSON);
    }

    return ContentService.createTextOutput(JSON.stringify({ 
      success: false, 
      message: 'Aksi tidak dikenali: ' + (data.action || 'kosong') 
    })).setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ 
      success: false, 
      error: err.message 
    })).setMimeType(ContentService.MimeType.JSON);
  }
}`;

const activeScriptCode = computed(() => {
  return scriptMode.value === 'insert' ? appsScriptInsertSnippet : appsScriptFullTemplate;
});

async function testSpreadsheetWebhook() {
  if (!settings.value.spreadsheet_absensi_webhook_url) {
    toast.warning('Silakan masukkan URL Web App Google Apps Script terlebih dahulu.');
    return;
  }

  isTestingSpreadsheet.value = true;
  try {
    const res = await api.post('/pengaturan/test-spreadsheet', {
      webhook_url: settings.value.spreadsheet_absensi_webhook_url,
    });
    if (res.data.success) {
      toast.success(res.data.message || 'Berhasil terhubung ke Google Spreadsheet!');
    } else {
      toast.error(res.data.message || 'Gagal mengirim ke Google Spreadsheet.');
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal menghubungi Google Apps Script.';
    toast.error(msg);
  } finally {
    isTestingSpreadsheet.value = false;
  }
}

function copyAppsScriptCode() {
  if (navigator?.clipboard) {
    navigator.clipboard.writeText(activeScriptCode.value);
    isScriptCopied.value = true;
    toast.success('Kode Google Apps Script berhasil disalin!');
    setTimeout(() => {
      isScriptCopied.value = false;
    }, 3000);
  }
}

onMounted(() => {
  loadSettings();
});
</script>
