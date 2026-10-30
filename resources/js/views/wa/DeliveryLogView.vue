<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between pb-2 border-b">
      <div class="flex items-center gap-2">
        <h2 class="text-lg font-semibold tracking-tight text-foreground">
          Delivery Log
        </h2>
        <Badge variant="outline" class="font-normal text-xs text-muted-foreground gap-1">
          <CheckCheck class="size-3 text-emerald-500" />
          <span>Gateway Monitor</span>
        </Badge>
      </div>

      <div v-if="canManageWaLogs" class="flex items-center gap-2">
        <Button
          variant="destructive"
          size="sm"
          class="h-8 gap-1.5 text-xs"
          :disabled="isRetryingBulk || summary.failed === 0"
          @click="confirmBulkRetry"
        >
          <RotateCw v-if="isRetryingBulk" class="size-3.5 animate-spin" />
          <Send v-else class="size-3.5" />
          <span>Kirim Ulang Gagal ({{ summary.failed }})</span>
        </Button>
      </div>
    </div>

    <!-- 5 Summary Quick Filter Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
      <!-- Total Pesan -->
      <Card
        class="cursor-pointer transition hover:bg-muted/30 border-muted"
        :class="{ 'ring-2 ring-primary border-primary/50 bg-primary/5': statusFilter === 'all' }"
        @click="setStatusFilter('all')"
      >
        <CardContent class="p-3.5">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Total Pesan</span>
            <Mail class="size-4" />
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-foreground">{{ summary.total }}</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Semua pesan keluar</p>
          </div>
        </CardContent>
      </Card>

      <!-- Terkirim (Centang 1) -->
      <Card
        class="cursor-pointer transition hover:bg-muted/30"
        :class="{ 'ring-2 ring-zinc-500 border-zinc-400 dark:border-zinc-600 bg-muted/20': statusFilter === 'sent' }"
        @click="setStatusFilter('sent')"
      >
        <CardContent class="p-3.5">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Terkirim (✓)</span>
            <Check class="size-4" />
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-foreground">{{ summary.sent }}</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Sampai server gateway</p>
          </div>
        </CardContent>
      </Card>

      <!-- Tersampaikan (Centang 2 Abu) -->
      <Card
        class="cursor-pointer transition hover:bg-muted/30"
        :class="{ 'ring-2 ring-emerald-500 border-emerald-500/50 bg-emerald-500/5': statusFilter === 'delivered' }"
        @click="setStatusFilter('delivered')"
      >
        <CardContent class="p-3.5">
          <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Tersampaikan (✓✓)</span>
            <CheckCheck class="size-4" />
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ summary.delivered }}</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Diterima di HP wali</p>
          </div>
        </CardContent>
      </Card>

      <!-- Sudah Dibaca (Centang 2 Biru) -->
      <Card
        class="cursor-pointer transition hover:bg-muted/30"
        :class="{ 'ring-2 ring-sky-500 border-sky-500/50 bg-sky-500/5': statusFilter === 'read' }"
        @click="setStatusFilter('read')"
      >
        <CardContent class="p-3.5">
          <div class="flex items-center justify-between text-sky-600 dark:text-sky-400">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Dibaca (✓✓ Biru)</span>
            <CheckCheck class="size-4 text-sky-500" />
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-sky-600 dark:text-sky-400">{{ summary.read }}</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Pesan dibuka wali</p>
          </div>
        </CardContent>
      </Card>

      <!-- Gagal / Pending -->
      <Card
        class="cursor-pointer transition hover:bg-muted/30 col-span-2 sm:col-span-1"
        :class="{ 'ring-2 ring-rose-500 border-rose-500/50 bg-rose-500/5': statusFilter === 'failed' }"
        @click="setStatusFilter('failed')"
      >
        <CardContent class="p-3.5">
          <div class="flex items-center justify-between text-rose-600 dark:text-rose-400">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Gagal / Antre</span>
            <AlertCircle class="size-4" />
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400">{{ summary.failed }}</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Perlu tindak lanjut</p>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Filter & Search Toolbar Card -->
    <Card>
      <CardContent class="p-3.5">
        <div class="flex flex-col md:flex-row gap-3 items-center justify-between">
          <div class="flex flex-col sm:flex-row items-center gap-2.5 w-full md:w-auto flex-1">
            <!-- Search -->
            <div class="relative w-full sm:w-72">
              <Search class="size-4 absolute left-2.5 top-2.5 text-muted-foreground" />
              <Input
                v-model="searchQuery"
                type="text"
                placeholder="Cari nama, nomor HP, atau isi pesan..."
                class="pl-9 h-9 text-xs"
                @input="onSearchInput"
              />
            </div>

            <!-- Tipe Pesan Dropdown -->
            <select
              v-model="tipeFilter"
              class="w-full sm:w-auto h-9 px-3 rounded-md border border-input bg-background text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring cursor-pointer"
              @change="fetchLogs(1)"
            >
              <option value="all">Semua Kategori Pesan</option>
              <option value="presensi">Notifikasi Presensi</option>
              <option value="broadcast">Broadcast Pengumuman</option>
              <option value="chatbot_reply">Balasan Chatbot</option>
              <option value="manual">Manual / Lainnya</option>
            </select>
          </div>

          <div class="flex items-center gap-2 w-full md:w-auto justify-end">
            <!-- Date Filter -->
            <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
              <Input
                v-model="startDate"
                type="date"
                class="h-9 w-auto text-xs"
                @change="fetchLogs(1)"
              />
              <span class="text-[11px]">s/d</span>
              <Input
                v-model="endDate"
                type="date"
                class="h-9 w-auto text-xs"
                @change="fetchLogs(1)"
              />
            </div>

            <!-- Reset Button -->
            <Button
              v-if="hasActiveFilter"
              variant="ghost"
              size="sm"
              class="h-9 text-xs"
              @click="resetFilters"
            >
              Reset
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Data Table Card -->
    <Card class="overflow-hidden">
      <!-- Loading State -->
      <div v-if="isLoading" class="p-12 text-center space-y-3">
        <RotateCw class="size-7 mx-auto animate-spin text-primary" />
        <p class="text-xs text-muted-foreground">Memuat data log pengiriman WhatsApp...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="logs.length === 0" class="p-12 text-center space-y-3">
        <div class="size-12 rounded-xl bg-muted text-muted-foreground flex items-center justify-center mx-auto">
          <Mail class="size-6" />
        </div>
        <h4 class="text-sm font-semibold text-foreground">Belum ada riwayat pengiriman</h4>
        <p class="text-xs text-muted-foreground max-w-sm mx-auto">
          Pesan WhatsApp yang dikirim melalui sistem (Notifikasi Presensi, Broadcast Pengumuman, atau Chatbot) akan otomatis tercatat di sini.
        </p>
      </div>

      <!-- Table Content -->
      <div v-else class="overflow-x-auto">
        <Table class="w-full">
          <TableHeader>
            <TableRow class="bg-muted/30">
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Waktu</TableHead>
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Penerima</TableHead>
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Kategori</TableHead>
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 min-w-[200px]">Ringkasan Pesan</TableHead>
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Status Delivery</TableHead>
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 text-center">Percobaan</TableHead>
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="item in logs"
              :key="item.id"
              class="transition-colors hover:bg-muted/40"
            >
              <!-- Waktu -->
              <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap font-mono text-xs">
                <div class="font-medium text-foreground">{{ formatDate(item.created_at) }}</div>
                <div class="text-[10px] text-muted-foreground">{{ formatTime(item.created_at) }} WIB</div>
              </TableCell>

              <!-- Penerima -->
              <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap">
                <div class="font-medium text-foreground">{{ item.nama_penerima || 'Tanpa Nama' }}</div>
                <div class="flex items-center gap-1.5 mt-0.5 text-muted-foreground font-mono text-[11px]">
                  <span>{{ item.nomor_tujuan }}</span>
                  <a
                    :href="'https://wa.me/' + item.nomor_tujuan"
                    target="_blank"
                    class="text-emerald-600 hover:text-emerald-500 inline-flex items-center"
                    title="Buka Chat WhatsApp"
                  >
                    <ExternalLink class="size-3" />
                  </a>
                </div>
              </TableCell>

              <!-- Kategori -->
              <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap">
                <Badge :variant="getTipeBadgeVariant(item.tipe_pesan)" class="text-[10px] font-medium">
                  {{ formatTipe(item.tipe_pesan) }}
                </Badge>
              </TableCell>

              <!-- Ringkasan Pesan -->
              <TableCell class="py-2.5 px-3 sm:px-4 max-w-xs">
                <div class="truncate text-foreground text-xs" :title="item.pesan">
                  {{ item.pesan }}
                </div>
                <button
                  type="button"
                  class="text-[11px] font-medium text-primary hover:underline mt-0.5 inline-block cursor-pointer"
                  @click="previewMessage(item)"
                >
                  Lihat Pesan Lengkap
                </button>
              </TableCell>

              <!-- Status Delivery -->
              <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap">
                <!-- Read: Centang 2 Biru -->
                <div v-if="item.status === 'read'" class="flex items-center gap-1.5 text-sky-600 dark:text-sky-400 font-medium text-xs">
                  <CheckCheck class="size-4 text-sky-500 shrink-0" />
                  <div>
                    <span>Dibaca</span>
                    <div v-if="item.read_at" class="text-[10px] text-muted-foreground font-mono font-normal">
                      {{ formatTime(item.read_at) }}
                    </div>
                  </div>
                </div>

                <!-- Delivered: Centang 2 Abu/Hijau -->
                <div v-else-if="item.status === 'delivered'" class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-medium text-xs">
                  <CheckCheck class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                  <div>
                    <span>Tersampaikan</span>
                    <div v-if="item.delivered_at" class="text-[10px] text-muted-foreground font-mono font-normal">
                      {{ formatTime(item.delivered_at) }}
                    </div>
                  </div>
                </div>

                <!-- Sent: Centang 1 -->
                <div v-else-if="item.status === 'sent'" class="flex items-center gap-1.5 text-muted-foreground text-xs">
                  <Check class="size-4 shrink-0" />
                  <div>
                    <span>Terkirim</span>
                    <div v-if="item.sent_at" class="text-[10px] text-muted-foreground font-mono">
                      {{ formatTime(item.sent_at) }}
                    </div>
                  </div>
                </div>

                <!-- Failed: Badge Merah + Error Info -->
                <div v-else-if="item.status === 'failed'" class="space-y-0.5">
                  <Badge variant="destructive" class="text-[10px] gap-1 py-0 px-1.5">
                    <AlertCircle class="size-3" />
                    <span>Gagal</span>
                  </Badge>
                  <p v-if="item.keterangan_error" class="text-[10px] text-rose-500 truncate max-w-[170px]" :title="item.keterangan_error">
                    {{ item.keterangan_error }}
                  </p>
                </div>

                <!-- Pending -->
                <div v-else class="flex items-center gap-1.5 text-amber-600 dark:text-amber-400 text-xs">
                  <RotateCw class="size-3.5 animate-spin text-amber-500 shrink-0" />
                  <span>Dalam Antrean</span>
                </div>
              </TableCell>

              <!-- Percobaan Retry -->
              <TableCell class="py-2.5 px-3 sm:px-4 text-center whitespace-nowrap">
                <Badge
                  v-if="item.retry_count > 0"
                  variant="outline"
                  class="text-[10px] border-amber-500/40 text-amber-600 dark:text-amber-400"
                  :title="'Kirim ulang terakhir: ' + (item.last_retried_at ? formatDateTime(item.last_retried_at) : '-')"
                >
                  Retry {{ item.retry_count }}x
                </Badge>
                <span v-else class="text-muted-foreground text-xs">-</span>
              </TableCell>

              <!-- Aksi -->
              <TableCell class="py-2.5 px-3 sm:px-4 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1.5">
                  <Button
                    v-if="canManageWaLogs && (item.status === 'failed' || item.status === 'pending')"
                    size="sm"
                    class="h-7 px-2 text-xs gap-1"
                    :disabled="retryingId === item.id"
                    @click="retryMessage(item)"
                  >
                    <RotateCw v-if="retryingId === item.id" class="size-3 animate-spin" />
                    <Send v-else class="size-3" />
                    <span>Kirim Ulang</span>
                  </Button>

                  <Button
                    variant="outline"
                    size="icon"
                    class="size-7"
                    title="Detail Timeline"
                    @click="showDetail(item)"
                  >
                    <Info class="size-3.5" />
                  </Button>

                  <Button
                    v-if="canManageWaLogs"
                    variant="ghost"
                    size="icon"
                    class="size-7 text-muted-foreground hover:text-destructive"
                    title="Hapus Log"
                    @click="confirmDelete(item)"
                  >
                    <Trash2 class="size-3.5" />
                  </Button>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <!-- Pagination Footer -->
      <div v-if="pagination.total > 0" class="p-3.5 border-t flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground">
        <div>
          Menampilkan <span class="font-medium text-foreground">{{ fromEntry }}</span> -
          <span class="font-medium text-foreground">{{ toEntry }}</span> dari
          <span class="font-medium text-foreground">{{ pagination.total }}</span> pesan
        </div>

        <div class="flex items-center gap-1.5">
          <Button
            variant="outline"
            size="icon"
            class="size-7"
            :disabled="pagination.current_page <= 1"
            @click="fetchLogs(pagination.current_page - 1)"
          >
            <ChevronLeft class="size-3.5" />
          </Button>

          <span class="px-2 font-medium text-foreground">
            Hal {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>

          <Button
            variant="outline"
            size="icon"
            class="size-7"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="fetchLogs(pagination.current_page + 1)"
          >
            <ChevronRight class="size-3.5" />
          </Button>
        </div>
      </div>
    </Card>

    <!-- DIALOG 1: PREVIEW BUBBLE CHAT WHATSAPP -->
    <Dialog :open="previewModalOpen" @update:open="(val) => previewModalOpen = val">
      <DialogContent class="sm:max-w-md p-0 overflow-hidden">
        <!-- Top bar WhatsApp style -->
        <div class="p-3.5 bg-emerald-700 text-white flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <div class="size-8 rounded-full bg-emerald-800 flex items-center justify-center text-white">
              <MessageSquare class="size-4" />
            </div>
            <div>
              <h4 class="text-xs font-semibold">{{ selectedItem?.nama_penerima || 'Penerima WhatsApp' }}</h4>
              <p class="text-[10px] text-emerald-100 font-mono">{{ selectedItem?.nomor_tujuan }}</p>
            </div>
          </div>
        </div>

        <!-- Chat Area (WhatsApp Pattern Background) -->
        <div class="p-4 bg-muted/40 min-h-48 max-h-[55vh] overflow-y-auto space-y-3">
          <div class="flex justify-end">
            <div class="max-w-[85%] bg-emerald-500/10 border border-emerald-500/20 rounded-xl rounded-tr-xs p-3 shadow-xs space-y-2">
              <p class="text-xs text-foreground whitespace-pre-wrap leading-relaxed select-text">
                {{ selectedItem?.pesan }}
              </p>
              <div class="flex items-center justify-end gap-1.5 text-[10px] text-muted-foreground font-mono">
                <span>{{ selectedItem?.created_at ? formatTime(selectedItem.created_at) : '' }}</span>
                <CheckCheck
                  v-if="selectedItem?.status === 'read'"
                  class="size-3.5 text-sky-500"
                  title="Dibaca"
                />
                <CheckCheck
                  v-else-if="selectedItem?.status === 'delivered'"
                  class="size-3.5 text-emerald-600 dark:text-emerald-400"
                  title="Tersampaikan"
                />
                <Check
                  v-else-if="selectedItem?.status === 'sent'"
                  class="size-3.5 text-muted-foreground"
                  title="Terkirim"
                />
                <AlertCircle
                  v-else-if="selectedItem?.status === 'failed'"
                  class="size-3.5 text-rose-500"
                  title="Gagal"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Dialog Footer -->
        <DialogFooter class="p-3 bg-muted/20 border-t flex items-center justify-between sm:justify-between">
          <Button
            variant="outline"
            size="sm"
            class="text-xs gap-1.5"
            @click="copyMessage(selectedItem?.pesan)"
          >
            <Copy class="size-3.5" />
            <span>Salin Pesan</span>
          </Button>

          <Button
            variant="secondary"
            size="sm"
            class="text-xs"
            @click="previewModalOpen = false"
          >
            Tutup
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- DIALOG 2: DETAIL TIMELINE & INFO GATEWAY -->
    <Dialog :open="detailModalOpen" @update:open="(val) => detailModalOpen = val">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold flex items-center gap-2">
            <Info class="size-4 text-primary" />
            <span>Detail Log & Jejak Pengiriman</span>
          </DialogTitle>
          <DialogDescription class="text-xs">
            Informasi status pengiriman gateway WhatsApp Fonnte untuk pesan ini.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-3.5 text-xs pt-1">
          <!-- Gateway Message ID -->
          <div class="flex items-center justify-between p-2.5 rounded-lg bg-muted/50 border">
            <span class="text-muted-foreground font-medium">ID Pesan Gateway:</span>
            <span class="font-mono font-semibold text-foreground">{{ selectedItem?.fonnte_id || 'Belum Terbit' }}</span>
          </div>

          <!-- Status Timeline Cards -->
          <div class="space-y-2 border-l-2 border-primary/50 pl-3.5 ml-1">
            <div>
              <p class="font-medium text-foreground">1. Dibuat Sistem (Antrean)</p>
              <p class="text-[10px] text-muted-foreground font-mono">{{ selectedItem?.created_at ? formatDateTime(selectedItem.created_at) : '-' }}</p>
            </div>
            <div>
              <p class="font-medium text-foreground">2. Terkirim ke Server WhatsApp (Centang 1)</p>
              <p class="text-[10px] text-muted-foreground font-mono">{{ selectedItem?.sent_at ? formatDateTime(selectedItem.sent_at) : 'Belum terkirim' }}</p>
            </div>
            <div>
              <p class="font-medium text-foreground">3. Tersampaikan di HP Penerima (Centang 2 Abu)</p>
              <p class="text-[10px] text-muted-foreground font-mono">{{ selectedItem?.delivered_at ? formatDateTime(selectedItem.delivered_at) : 'Belum tersampaikan' }}</p>
            </div>
            <div>
              <p class="font-medium text-foreground">4. Dibaca oleh Penerima (Centang 2 Biru)</p>
              <p class="text-[10px] text-muted-foreground font-mono">{{ selectedItem?.read_at ? formatDateTime(selectedItem.read_at) : 'Belum dibaca' }}</p>
            </div>
          </div>

          <!-- Error details if any -->
          <div v-if="selectedItem?.keterangan_error" class="p-2.5 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400">
            <p class="font-semibold text-[11px] mb-0.5">Keterangan Error:</p>
            <p class="text-[11px] leading-relaxed">{{ selectedItem.keterangan_error }}</p>
          </div>
        </div>

        <DialogFooter class="pt-2">
          <Button
            variant="outline"
            size="sm"
            class="text-xs"
            @click="detailModalOpen = false"
          >
            Tutup
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import {
  CheckCheck,
  Check,
  RotateCw,
  Send,
  AlertCircle,
  ExternalLink,
  Copy,
  Trash2,
  Search,
  Mail,
  Info,
  ChevronLeft,
  ChevronRight,
  MessageSquare,
} from '@lucide/vue';
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
import { useAuth } from '@/composables/useAuth';

const toast = useToast();
const { canManageWaLogs } = useAuth();

const logs = ref([]);
const isLoading = ref(false);
const isRetryingBulk = ref(false);
const retryingId = ref(null);

const summary = ref({
  total: 0,
  sent: 0,
  delivered: 0,
  read: 0,
  failed: 0,
});

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});

// Filters
const statusFilter = ref('all');
const tipeFilter = ref('all');
const searchQuery = ref('');
const startDate = ref('');
const endDate = ref('');
let searchTimer = null;

// Modals
const previewModalOpen = ref(false);
const detailModalOpen = ref(false);
const selectedItem = ref(null);

const hasActiveFilter = computed(() => {
  return statusFilter.value !== 'all' || tipeFilter.value !== 'all' || searchQuery.value !== '' || startDate.value !== '' || endDate.value !== '';
});

const fromEntry = computed(() => {
  if (pagination.value.total === 0) return 0;
  return (pagination.value.current_page - 1) * pagination.value.per_page + 1;
});

const toEntry = computed(() => {
  return Math.min(pagination.value.current_page * pagination.value.per_page, pagination.value.total);
});

async function fetchLogs(page = 1) {
  isLoading.value = true;
  try {
    const res = await api.get('/wa/logs', {
      params: {
        page,
        per_page: pagination.value.per_page,
        status: statusFilter.value,
        tipe_pesan: tipeFilter.value,
        search: searchQuery.value,
        start_date: startDate.value,
        end_date: endDate.value,
      },
    });

    if (res.data.success) {
      logs.value = res.data.data;
      pagination.value = res.data.pagination;
      summary.value = res.data.summary;
    }
  } catch (err) {
    toast.error('Gagal mengambil data log pengiriman WhatsApp.');
  } finally {
    isLoading.value = false;
  }
}

function setStatusFilter(status) {
  statusFilter.value = status;
  fetchLogs(1);
}

function onSearchInput() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    fetchLogs(1);
  }, 350);
}

function resetFilters() {
  statusFilter.value = 'all';
  tipeFilter.value = 'all';
  searchQuery.value = '';
  startDate.value = '';
  endDate.value = '';
  fetchLogs(1);
}

async function retryMessage(item) {
  retryingId.value = item.id;
  try {
    const res = await api.post(`/wa/logs/${item.id}/retry`);
    if (res.data.success) {
      toast.success(res.data.message || 'Pesan berhasil dikirim ulang!');
      await fetchLogs(pagination.value.current_page);
    } else {
      toast.error(res.data.message || 'Gagal mengirim ulang pesan.');
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal mengirim ulang pesan via WhatsApp Gateway.';
    toast.error(msg);
  } finally {
    retryingId.value = null;
  }
}

async function confirmBulkRetry() {
  if (summary.value.failed === 0) {
    toast.info('Tidak ada pesan gagal untuk dikirim ulang.');
    return;
  }

  const ok = window.confirm(`Apakah Anda yakin ingin mengirim ulang seluruh ${summary.value.failed} pesan yang gagal?`);
  if (!ok) return;

  isRetryingBulk.value = true;
  try {
    const res = await api.post('/wa/logs/retry-failed');
    if (res.data.success) {
      toast.success(res.data.message || 'Pesan berhasil dimasukkan ke antrean pengiriman ulang.');
      await fetchLogs(1);
    }
  } catch (err) {
    toast.error('Gagal memproses pengiriman ulang massal.');
  } finally {
    isRetryingBulk.value = false;
  }
}

async function confirmDelete(item) {
  const ok = window.confirm(`Hapus catatan log pesan untuk ${item.nama_penerima || item.nomor_tujuan}?`);
  if (!ok) return;

  try {
    const res = await api.delete(`/wa/logs/${item.id}`);
    if (res.data.success) {
      toast.success('Log pengiriman berhasil dihapus.');
      await fetchLogs(pagination.value.current_page);
    }
  } catch (err) {
    toast.error('Gagal menghapus catatan log.');
  }
}

function previewMessage(item) {
  selectedItem.value = item;
  previewModalOpen.value = true;
}

function showDetail(item) {
  selectedItem.value = item;
  detailModalOpen.value = true;
}

function copyMessage(text) {
  if (!text) return;
  navigator.clipboard.writeText(text);
  toast.success('Pesan disalin ke clipboard!');
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatTime(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function formatDateTime(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return `${d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })} ${d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} WIB`;
}

function formatTipe(tipe) {
  switch (tipe) {
    case 'presensi': return 'Presensi';
    case 'broadcast': return 'Broadcast';
    case 'chatbot_reply': return 'Chatbot';
    case 'manual': return 'Manual';
    default: return tipe || 'Umum';
  }
}

function getTipeBadgeVariant(tipe) {
  switch (tipe) {
    case 'presensi': return 'default';
    case 'broadcast': return 'secondary';
    case 'chatbot_reply': return 'outline';
    default: return 'outline';
  }
}

onMounted(() => {
  fetchLogs(1);
});
</script>
