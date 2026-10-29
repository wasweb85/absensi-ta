<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between pb-2 border-b">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-lg font-semibold tracking-tight text-foreground">
            Chatbot
          </h2>
          <Badge variant="outline" class="font-normal text-xs text-muted-foreground gap-1">
            <Bot class="size-3 text-emerald-500" />
            <span>AI Auto-Reply</span>
          </Badge>
        </div>
        <p class="text-xs text-muted-foreground mt-0.5">
          Monitoring percakapan bot otomatis, uji coba pertanyaan di simulator, dan pengaturan webhook Fonnte.
        </p>
      </div>

      <!-- Tab Switcher (Shadcn Tabs) -->
      <Tabs v-model="activeTab" class="w-auto">
        <TabsList class="grid grid-cols-3 h-8">
          <TabsTrigger value="logs" class="text-xs px-3 gap-1.5">
            <MessageSquare class="size-3.5" />
            <span>Log Percakapan</span>
          </TabsTrigger>
          <TabsTrigger value="simulator" class="text-xs px-3 gap-1.5">
            <Sparkles class="size-3.5" />
            <span>Simulator Chat</span>
          </TabsTrigger>
          <TabsTrigger value="config" class="text-xs px-3 gap-1.5">
            <Settings class="size-3.5" />
            <span>Webhook Fonnte</span>
          </TabsTrigger>
        </TabsList>
      </Tabs>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 1: LOG PERCAKAPAN REALTIME -->
    <!-- ================================================================= -->
    <div v-if="activeTab === 'logs'" class="space-y-6">
      <!-- KPI Stats Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <Card class="p-3.5">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Chat Hari Ini</span>
            <MessageSquare class="size-4 text-emerald-600 dark:text-emerald-400" />
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-foreground">{{ stats.total_today }}</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Bulan ini: {{ stats.total_month }} pesan</p>
          </div>
        </Card>

        <Card class="p-3.5">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Cek Presensi</span>
            <span class="size-2 rounded-full bg-teal-500"></span>
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-foreground">{{ stats.intent_distribution?.absensi || 0 }}</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Pertanyaan kehadiran santri</p>
          </div>
        </Card>

        <Card class="p-3.5">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Cek Agenda & Libur</span>
            <span class="size-2 rounded-full bg-blue-500"></span>
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-foreground">{{ stats.intent_distribution?.agenda || 0 }}</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Pertanyaan jadwal & hari libur</p>
          </div>
        </Card>

        <Card class="p-3.5">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Tingkat Keberhasilan</span>
            <Check class="size-4 text-emerald-600" />
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ stats.success_rate || 100 }}%</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Respon berhasil diproses</p>
          </div>
        </Card>
      </div>

      <!-- Filters Card -->
      <Card>
        <CardContent class="p-3.5">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <div class="space-y-1">
              <label class="text-[11px] font-medium text-muted-foreground">Filter Intent / Topik</label>
              <select
                v-model="filters.intent"
                class="w-full h-9 px-3 rounded-md border border-input bg-background text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring cursor-pointer"
                @change="loadLogs(1)"
              >
                <option value="">Semua Topik</option>
                <option value="absensi">Presensi Santri</option>
                <option value="agenda">Agenda & Libur</option>
                <option value="bantuan">Menu Bantuan</option>
                <option value="multi_santri">Multi Santri</option>
                <option value="unregistered">Nomor Belum Terdaftar</option>
                <option value="unknown">Tidak Dikenali</option>
              </select>
            </div>

            <div class="space-y-1">
              <label class="text-[11px] font-medium text-muted-foreground">Status Respon</label>
              <select
                v-model="filters.status"
                class="w-full h-9 px-3 rounded-md border border-input bg-background text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring cursor-pointer"
                @change="loadLogs(1)"
              >
                <option value="">Semua Status</option>
                <option value="sukses">Sukses</option>
                <option value="gagal">Gagal</option>
              </select>
            </div>

            <div class="space-y-1">
              <label class="text-[11px] font-medium text-muted-foreground">Tanggal</label>
              <Input
                v-model="filters.tanggal"
                type="date"
                class="h-9 text-xs"
                @change="loadLogs(1)"
              />
            </div>

            <div class="space-y-1">
              <label class="text-[11px] font-medium text-muted-foreground">Cari Pesan / Nomor</label>
              <div class="relative">
                <Search class="size-3.5 absolute left-2.5 top-2.5 text-muted-foreground" />
                <Input
                  v-model="filters.search"
                  type="text"
                  placeholder="Ketik kata kunci..."
                  class="h-9 pl-8 text-xs"
                  @input="debounceSearch"
                />
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Logs Table Card -->
      <Card class="overflow-hidden">
        <div class="overflow-x-auto">
          <Table class="w-full">
            <TableHeader>
              <TableRow class="bg-muted/30">
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Waktu</TableHead>
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Pengirim / Santri</TableHead>
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 max-w-xs">Pesan Masuk</TableHead>
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Intent</TableHead>
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 max-w-xs">Balasan Bot</TableHead>
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 text-center">Status</TableHead>
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 text-right">Aksi</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-if="logs.length === 0">
                <TableCell colspan="7" class="h-32 text-center text-muted-foreground text-xs">
                  Belum ada log percakapan yang sesuai dengan filter.
                </TableCell>
              </TableRow>

              <TableRow
                v-for="item in logs"
                :key="item.id"
                class="transition-colors hover:bg-muted/40"
              >
                <TableCell class="py-2.5 px-3 sm:px-4 font-mono text-xs whitespace-nowrap text-muted-foreground">
                  {{ formatDateTime(item.created_at) }}
                </TableCell>

                <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap">
                  <p class="font-mono font-medium text-foreground text-xs">{{ item.no_pengirim }}</p>
                  <p v-if="item.siswa" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                    {{ item.siswa.nama_lengkap }} ({{ item.siswa.kelas?.nama_kelas || '-' }})
                  </p>
                  <p v-else class="text-[10px] text-muted-foreground italic">Non-Santri</p>
                </TableCell>

                <TableCell class="py-2.5 px-3 sm:px-4 max-w-xs">
                  <p class="text-xs text-foreground truncate font-medium" :title="item.pesan_masuk">
                    "{{ item.pesan_masuk }}"
                  </p>
                </TableCell>

                <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap">
                  <Badge variant="outline" class="text-[10px] uppercase tracking-wider font-medium">
                    {{ item.intent_terdeteksi }}
                  </Badge>
                </TableCell>

                <TableCell class="py-2.5 px-3 sm:px-4 max-w-xs">
                  <p class="text-xs text-muted-foreground truncate" :title="item.pesan_keluar">
                    {{ item.pesan_keluar }}
                  </p>
                </TableCell>

                <TableCell class="py-2.5 px-3 sm:px-4 text-center whitespace-nowrap">
                  <Badge
                    :variant="item.status === 'sukses' ? 'default' : 'destructive'"
                    class="text-[10px] uppercase"
                  >
                    {{ item.status }}
                  </Badge>
                </TableCell>

                <TableCell class="py-2.5 px-3 sm:px-4 text-right whitespace-nowrap">
                  <Button
                    variant="outline"
                    size="sm"
                    class="h-7 px-2 text-xs"
                    @click="openDetailModal(item)"
                  >
                    Detail
                  </Button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="p-3.5 border-t flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground">
          <span>Halaman {{ currentPage }} dari {{ totalPages }} (Total {{ totalLogs }} log)</span>
          <div class="flex items-center gap-1.5">
            <Button
              variant="outline"
              size="sm"
              class="h-7 text-xs"
              :disabled="currentPage <= 1"
              @click="loadLogs(currentPage - 1)"
            >
              Sebelumnya
            </Button>
            <Button
              variant="outline"
              size="sm"
              class="h-7 text-xs"
              :disabled="currentPage >= totalPages"
              @click="loadLogs(currentPage + 1)"
            >
              Selanjutnya
            </Button>
          </div>
        </div>
      </Card>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 2: INTERACTIVE CHATBOT SIMULATOR (LIVE DEMO) -->
    <!-- ================================================================= -->
    <div v-if="activeTab === 'simulator'" class="space-y-4">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Kolom Kiri: Pengaturan Nomor Simulasi & Rekomendasi Pertanyaan -->
        <div class="space-y-4">
          <Card class="p-4 space-y-4">
            <div>
              <h3 class="text-sm font-semibold tracking-tight text-foreground flex items-center gap-1.5">
                <Smartphone class="size-4 text-primary" />
                <span>Nomor HP Simulasi</span>
              </h3>
              <p class="text-xs text-muted-foreground mt-0.5">
                Pilih santri sampel atau masukkan nomor WhatsApp untuk menguji bot.
              </p>
            </div>

            <div class="space-y-1.5">
              <label class="text-[11px] font-medium text-muted-foreground">Nomor Pengirim (Wali Santri)</label>
              <Input
                v-model="simPhone"
                type="text"
                placeholder="Contoh: 081234567890"
                class="font-mono text-xs h-9"
              />
            </div>

            <!-- Santri Samples Quick Select -->
            <div class="space-y-1.5">
              <label class="text-[11px] font-medium text-muted-foreground">Pilih Cepat Sampel Santri</label>
              <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                <button
                  v-for="s in sampleStudents"
                  :key="s.id"
                  type="button"
                  class="w-full text-left p-2 rounded-lg border text-xs transition cursor-pointer flex items-center justify-between"
                  :class="simPhone === s.no_hp_ortu ? 'border-primary bg-primary/5 text-foreground' : 'border-border hover:bg-muted/50 text-muted-foreground hover:text-foreground'"
                  @click="selectSampleStudent(s)"
                >
                  <div class="min-w-0 pr-1">
                    <p class="font-medium text-xs truncate">{{ s.nama_lengkap }}</p>
                    <p class="text-[10px] text-muted-foreground font-mono">{{ s.no_hp_ortu || 'Tanpa no HP' }}</p>
                  </div>
                  <Badge variant="outline" class="text-[10px] shrink-0 font-normal">
                    {{ s.kelas?.nama_kelas || '-' }}
                  </Badge>
                </button>
              </div>
            </div>

            <div class="pt-2 border-t">
              <Button
                variant="outline"
                size="sm"
                class="w-full text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/30 border-rose-200 dark:border-rose-900/40 gap-1.5"
                @click="clearChatHistory"
              >
                <Trash2 class="size-3.5" />
                <span>Bersihkan Layar Chat</span>
              </Button>
            </div>
          </Card>

          <!-- Suggested Prompt Chips -->
          <Card class="p-4 space-y-3">
            <h4 class="text-xs font-semibold text-foreground flex items-center gap-1.5">
              <Sparkles class="size-3.5 text-amber-500" />
              <span>Pertanyaan Siap Coba</span>
            </h4>
            <div class="flex flex-col gap-1.5">
              <button
                v-for="(prompt, idx) in suggestedPrompts"
                :key="idx"
                type="button"
                class="text-left px-3 py-2 rounded-lg bg-muted/40 hover:bg-muted border border-border text-foreground text-xs transition cursor-pointer flex items-center justify-between"
                @click="sendPrompt(prompt.text)"
              >
                <span class="truncate">"{{ prompt.text }}"</span>
                <span class="text-xs shrink-0 ml-1">{{ prompt.icon }}</span>
              </button>
            </div>
          </Card>
        </div>

        <!-- Kolom Kanan: Simulator Chat Screen (Phone Mockup) -->
        <div class="lg:col-span-2">
          <Card class="overflow-hidden flex flex-col h-[620px] border">
            <!-- WhatsApp Phone Top Bar -->
            <div class="bg-emerald-700 dark:bg-emerald-800 px-4 py-3 text-white flex items-center justify-between shadow-xs">
              <div class="flex items-center gap-2.5">
                <div class="size-9 rounded-full bg-white/20 backdrop-blur-xs flex items-center justify-center text-white">
                  <Bot class="size-5" />
                </div>
                <div>
                  <h4 class="text-xs font-semibold leading-tight">Chatbot TPQ Al-Hidayah</h4>
                  <p class="text-[10px] text-emerald-100 flex items-center gap-1 mt-0.5">
                    <span class="size-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
                    <span>Online &bull; Layanan Otomatis</span>
                  </p>
                </div>
              </div>
              <Badge variant="secondary" class="font-mono text-[10px] bg-white/10 text-white border-white/20">
                {{ simPhone || 'Pilih Nomor' }}
              </Badge>
            </div>

            <!-- Messages Stream Area -->
            <div ref="chatContainerRef" class="flex-1 p-4 overflow-y-auto space-y-3 bg-muted/20 text-xs">
              <!-- Welcome Info Pill -->
              <div class="text-center my-1">
                <Badge variant="outline" class="font-normal text-[10px] text-muted-foreground bg-background">
                  Pesan diproses otomatis oleh Chatbot Engine Laravel & MySQL
                </Badge>
              </div>

              <!-- Message Bubbles -->
              <div
                v-for="(msg, i) in messages"
                :key="i"
                class="flex flex-col"
                :class="msg.role === 'user' ? 'items-end' : 'items-start'"
              >
                <div
                  class="max-w-[85%] sm:max-w-[75%] p-3 rounded-xl shadow-xs whitespace-pre-line leading-relaxed"
                  :class="msg.role === 'user' ? 'bg-emerald-600 text-white rounded-br-none' : 'bg-background border text-foreground rounded-bl-none'"
                >
                  <p class="text-xs">{{ msg.text }}</p>
                  <div
                    class="flex items-center justify-end gap-1 mt-1 text-[9px] font-mono"
                    :class="msg.role === 'user' ? 'text-emerald-100' : 'text-muted-foreground'"
                  >
                    <span>{{ msg.time }}</span>
                    <span v-if="msg.role === 'user'" class="text-emerald-200 font-bold">✓✓</span>
                  </div>
                </div>

                <!-- Intent Tag (Bot only) -->
                <span v-if="msg.intent" class="text-[9px] px-1 py-0.5 mt-0.5 text-muted-foreground font-mono">
                  Intent: {{ msg.intent }}
                </span>
              </div>

              <!-- Typing Indicator -->
              <div v-if="isSimulating" class="flex items-center gap-1.5 p-2.5 rounded-xl bg-background border text-muted-foreground w-20">
                <span class="size-1.5 bg-muted-foreground rounded-full animate-bounce"></span>
                <span class="size-1.5 bg-muted-foreground rounded-full animate-bounce delay-100"></span>
                <span class="size-1.5 bg-muted-foreground rounded-full animate-bounce delay-200"></span>
              </div>
            </div>

            <!-- Bottom Chat Input Bar -->
            <div class="p-3 bg-background border-t flex items-center gap-2">
              <Input
                v-model="inputSimMessage"
                type="text"
                placeholder="Ketik pertanyaan untuk disimulasikan..."
                :disabled="isSimulating"
                class="h-9 text-xs flex-1"
                @keydown.enter.prevent="sendSimMessage"
              />
              <Button
                size="sm"
                class="h-9 text-xs gap-1.5 px-3"
                :disabled="!inputSimMessage.trim() || isSimulating"
                @click="sendSimMessage"
              >
                <span>Kirim</span>
                <Send class="size-3.5" />
              </Button>
            </div>
          </Card>
        </div>

      </div>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 3: WEBHOOK FONNTE & KONFIGURASI -->
    <!-- ================================================================= -->
    <div v-if="activeTab === 'config'" class="space-y-6">
      <Card class="p-6 space-y-4">
        <div>
          <h3 class="text-base font-semibold tracking-tight text-foreground flex items-center gap-2">
            <Settings class="size-4 text-primary" />
            <span>URL Webhook WhatsApp Gateway Fonnte</span>
          </h3>
          <p class="text-xs text-muted-foreground mt-1 leading-relaxed">
            Salin alamat endpoint di bawah ini dan tempelkan pada kolom <b>Webhook URL</b> di akun <b>Fonnte.com</b> Anda. Setiap kali wali santri mengirim pesan ke WhatsApp TPQ, Fonnte akan otomatis meneruskannya ke server sistem ini.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <Input
            :value="webhookUrl"
            readonly
            class="font-mono text-xs select-all bg-muted/40"
          />
          <Button
            size="sm"
            class="text-xs gap-1.5 shrink-0"
            @click="copyWebhookUrl"
          >
            <Copy class="size-3.5" />
            <span>{{ copied ? 'Tersalin!' : 'Salin URL' }}</span>
          </Button>
        </div>

        <div class="rounded-lg border p-4 bg-muted/20 space-y-2 text-xs">
          <p class="font-semibold text-foreground">Panduan Pemasangan Webhook Fonnte:</p>
          <ol class="list-decimal list-inside space-y-1 text-muted-foreground">
            <li>Buka dashboard akun Fonnte Anda di <code class="text-foreground">https://fonnte.com</code></li>
            <li>Masuk ke menu <b>Device Setting</b> &gt; tab <b>Webhook</b></li>
            <li>Tempelkan URL Webhook di atas pada kolom Webhook URL</li>
            <li>Pilih opsi kirim pesan masuk (Incoming Message) lalu klik <b>Save</b></li>
          </ol>
        </div>
      </Card>
    </div>

    <!-- DIALOG: DETAIL LOG PERCAKAPAN -->
    <Dialog :open="!!selectedLog" @update:open="(val) => { if (!val) selectedLog = null; }">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold flex items-center gap-2">
            <MessageSquare class="size-4 text-primary" />
            <span>Detail Log Percakapan Bot</span>
          </DialogTitle>
          <DialogDescription class="text-xs">
            Rincian interaksi pesan masuk dan respon yang dikeluarkan engine.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-3.5 text-xs pt-1">
          <div class="flex items-center justify-between p-2.5 rounded-lg bg-muted/50 border">
            <span class="text-muted-foreground">Waktu:</span>
            <span class="font-mono font-medium text-foreground">{{ formatDateTime(selectedLog?.created_at) }}</span>
          </div>

          <div class="space-y-1">
            <label class="font-medium text-muted-foreground">Nomor Pengirim & Santri:</label>
            <p class="font-mono font-semibold text-foreground">{{ selectedLog?.no_pengirim }}</p>
            <p v-if="selectedLog?.siswa" class="text-emerald-600 dark:text-emerald-400 font-medium">
              Santri: {{ selectedLog.siswa.nama_lengkap }} ({{ selectedLog.siswa.kelas?.nama_kelas || '-' }})
            </p>
          </div>

          <div class="space-y-1">
            <label class="font-medium text-muted-foreground">Pesan Masuk (Wali):</label>
            <div class="p-2.5 rounded-lg border bg-muted/30 whitespace-pre-wrap text-foreground">
              {{ selectedLog?.pesan_masuk }}
            </div>
          </div>

          <div class="space-y-1">
            <div class="flex items-center justify-between">
              <label class="font-medium text-muted-foreground">Balasan Bot:</label>
              <Badge variant="outline" class="text-[10px] uppercase">
                {{ selectedLog?.intent_terdeteksi }}
              </Badge>
            </div>
            <div class="p-2.5 rounded-lg border bg-emerald-500/5 border-emerald-500/20 whitespace-pre-wrap text-foreground">
              {{ selectedLog?.pesan_keluar }}
            </div>
          </div>
        </div>

        <DialogFooter class="pt-2">
          <Button
            variant="outline"
            size="sm"
            class="text-xs"
            @click="selectedLog = null"
          >
            Tutup
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, nextTick, onMounted } from 'vue';
import {
  Bot,
  MessageSquare,
  Sparkles,
  Settings,
  Send,
  Copy,
  Trash2,
  Search,
  Check,
  Smartphone,
} from '@lucide/vue';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';

const toast = useToast();

const activeTab = ref('logs');
const copied = ref(false);
const webhookUrl = ref(`${window.location.origin}/api/webhook/whatsapp`);

// KPI Stats
const stats = ref({
  total_today: 0,
  total_month: 0,
  success_rate: 100,
  intent_distribution: {},
});

// Logs state
const logs = ref([]);
const totalLogs = ref(0);
const totalPages = ref(1);
const currentPage = ref(1);
const filters = ref({
  intent: '',
  status: '',
  tanggal: '',
  search: '',
});
let searchTimeout = null;

// Modal
const selectedLog = ref(null);

// Simulator State
const simPhone = ref('');
const inputSimMessage = ref('');
const isSimulating = ref(false);
const chatContainerRef = ref(null);
const sampleStudents = ref([]);

const suggestedPrompts = [
  { icon: '✅', text: 'Apakah anak saya hadir hari ini?' },
  { icon: '📊', text: 'Rekap kehadiran bulan ini' },
  { icon: '📅', text: 'Kapan jadwal libur TPQ?' },
  { icon: '❓', text: 'Menu bantuan' },
];

const messages = ref([
  {
    role: 'bot',
    text: "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\nSelamat datang di Layanan WhatsApp Otomatis TPQ Al-Hidayah. Ketik *MENU* atau *BANTUAN* untuk melihat daftar layanan yang tersedia.",
    time: '12:00',
    intent: 'bantuan',
  },
]);

const debounceSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadLogs(1);
  }, 350);
};

const loadStats = async () => {
  try {
    const res = await api.get('/chatbot/stats');
    if (res.data.success) {
      stats.value = res.data.data;
    }
  } catch (e) {}
};

const loadLogs = async (page = 1) => {
  try {
    currentPage.value = page;
    const params = {
      page,
      per_page: 15,
      ...filters.value,
    };
    const res = await api.get('/chatbot/logs', { params });
    if (res.data.success) {
      logs.value = res.data.data.data;
      totalLogs.value = res.data.data.total;
      totalPages.value = res.data.data.last_page;
    }
  } catch (e) {
    toast.error('Gagal memuat log percakapan.');
  }
};

const loadSampleStudents = async () => {
  try {
    const res = await api.get('/siswa', { params: { per_page: 8 } });
    if (res.data.success) {
      sampleStudents.value = res.data.data || [];
      if (sampleStudents.value.length > 0 && sampleStudents.value[0].no_hp_ortu) {
        simPhone.value = sampleStudents.value[0].no_hp_ortu;
      }
    }
  } catch (e) {}
};

function selectSampleStudent(s) {
  simPhone.value = s.no_hp_ortu || '';
  toast.success(`Santri "${s.nama_lengkap}" dipilih untuk simulasi.`);
}

function clearChatHistory() {
  messages.value = [
    {
      role: 'bot',
      text: "Riwayat chat telah dibersihkan. Silakan ajukan pertanyaan baru.",
      time: getCurrentTime(),
      intent: 'info',
    },
  ];
}

function sendPrompt(text) {
  inputSimMessage.value = text;
  sendSimMessage();
}

async function sendSimMessage() {
  const text = inputSimMessage.value.trim();
  if (!text || isSimulating.value) return;

  // Add User Message
  messages.value.push({
    role: 'user',
    text,
    time: getCurrentTime(),
  });

  inputSimMessage.value = '';
  isSimulating.value = true;
  scrollChatToBottom();

  try {
    const res = await api.post('/chatbot/simulate', {
      phone: simPhone.value || '081234567890',
      message: text,
    });

    if (res.data.success) {
      const data = res.data.data;
      messages.value.push({
        role: 'bot',
        text: data.reply,
        time: getCurrentTime(),
        intent: data.intent,
      });
      loadStats();
    }
  } catch (e) {
    messages.value.push({
      role: 'bot',
      text: "Mohon maaf, terjadi gangguan saat menghubungi server chatbot.",
      time: getCurrentTime(),
      intent: 'error',
    });
  } finally {
    isSimulating.value = false;
    scrollChatToBottom();
  }
}

function scrollChatToBottom() {
  nextTick(() => {
    if (chatContainerRef.value) {
      chatContainerRef.value.scrollTop = chatContainerRef.value.scrollHeight;
    }
  });
}

function getCurrentTime() {
  const d = new Date();
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

function formatDateTime(val) {
  if (!val) return '-';
  const d = new Date(val);
  return d.toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function copyWebhookUrl() {
  navigator.clipboard.writeText(webhookUrl.value);
  copied.value = true;
  toast.success('URL Webhook berhasil disalin!');
  setTimeout(() => {
    copied.value = false;
  }, 2500);
}

function openDetailModal(item) {
  selectedLog.value = item;
}

onMounted(() => {
  loadStats();
  loadLogs();
  loadSampleStudents();
});
</script>
