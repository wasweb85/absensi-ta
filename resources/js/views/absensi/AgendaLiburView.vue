<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between pb-2 border-b">
      <div class="flex items-center gap-2">
        <h2 class="text-lg font-semibold tracking-tight text-foreground">
          Agenda &amp; Libur
        </h2>
        <Badge variant="outline" class="font-normal text-xs text-muted-foreground">
          Kalender Akademik
        </Badge>
      </div>

      <!-- Tombol Tambah Manual (Hanya Admin) -->
      <div v-if="canManageAgenda" class="flex items-center gap-2">
        <Button
          size="sm"
          variant="outline"
          class="h-8 gap-1.5 text-xs"
          @click="openQuickAddOnDate(todayStr, 'libur')"
        >
          <CalendarOff class="size-3.5 text-rose-500" />
          <span>Tambah Libur</span>
        </Button>
        <Button
          size="sm"
          class="h-8 gap-1.5 text-xs"
          @click="openQuickAddOnDate(todayStr, 'agenda')"
        >
          <Plus class="size-3.5" />
          <span>Tambah Agenda</span>
        </Button>
      </div>
    </div>

    <!-- KALENDER INTERAKTIF BULANAN -->
    <Card class="overflow-hidden">
      <CardHeader class="p-4 border-b bg-muted/20">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <!-- Navigasi Bulan & Tahun -->
          <div class="flex items-center gap-2">
            <Button
              variant="outline"
              size="icon-sm"
              class="size-8"
              title="Bulan Sebelumnya"
              @click="prevMonth"
            >
              <ChevronLeft class="size-4" />
            </Button>
            <div class="min-w-[160px] text-center">
              <h3 class="text-sm font-semibold text-foreground">
                {{ monthNames[currentMonth] }} {{ currentYear }}
              </h3>
            </div>
            <Button
              variant="outline"
              size="icon-sm"
              class="size-8"
              title="Bulan Berikutnya"
              @click="nextMonth"
            >
              <ChevronRight class="size-4" />
            </Button>
            <Button
              variant="ghost"
              size="sm"
              class="h-8 px-2.5 text-xs ml-1"
              @click="goToToday"
            >
              Hari Ini
            </Button>
          </div>

          <!-- Legend Indikator Kalender -->
          <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
            <div class="flex items-center gap-1.5">
              <span class="size-2.5 rounded-full bg-emerald-500"></span>
              <span>Agenda Kegiatan</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="size-2.5 rounded-full bg-rose-500"></span>
              <span>Hari Libur</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="size-2.5 rounded-full ring-2 ring-primary bg-primary/20"></span>
              <span>Hari Ini</span>
            </div>
          </div>
        </div>
      </CardHeader>

      <CardContent class="p-0">
        <!-- Header Nama Hari (Senin - Minggu) -->
        <div class="grid grid-cols-7 border-b bg-muted/40 text-center">
          <div
            v-for="(dayName, idx) in dayHeaders"
            :key="dayName"
            class="py-2 text-[11px] font-semibold uppercase tracking-wider"
            :class="idx === 6 ? 'text-rose-500' : 'text-muted-foreground'"
          >
            {{ dayName }}
          </div>
        </div>

        <!-- Grid Tanggal Kalender (42 Sel) -->
        <div class="grid grid-cols-7 divide-x divide-y border-b">
          <div
            v-for="cell in calendarCells"
            :key="cell.dateStr"
            class="min-h-[88px] sm:min-h-[108px] p-1.5 sm:p-2 transition-colors flex flex-col justify-between cursor-pointer group relative"
            :class="[
              cell.isCurrentMonth ? 'bg-card hover:bg-muted/50' : 'bg-muted/20 text-muted-foreground/50 hover:bg-muted/40',
              cell.liburItems.length > 0 && cell.isCurrentMonth ? 'bg-rose-500/[0.04]' : '',
              cell.isToday ? 'ring-1 ring-inset ring-primary bg-primary/[0.03]' : ''
            ]"
            @click="onSelectDateCell(cell)"
          >
            <!-- Baris Atas: Nomor Tanggal & Ikon Tambah (Admin) -->
            <div class="flex items-center justify-between">
              <span
                class="inline-flex size-6 items-center justify-center rounded-full text-xs font-semibold transition-colors"
                :class="[
                  cell.isToday
                    ? 'bg-primary text-primary-foreground shadow-2xs'
                    : (cell.isSunday || cell.liburItems.length > 0) && cell.isCurrentMonth
                      ? 'text-rose-600 dark:text-rose-400'
                      : cell.isCurrentMonth
                        ? 'text-foreground'
                        : 'text-muted-foreground/40'
                ]"
              >
                {{ cell.dayNumber }}
              </span>

              <!-- Indikator Hover '+' khusus Admin -->
              <span
                v-if="canManageAgenda"
                class="opacity-0 group-hover:opacity-100 transition-opacity inline-flex size-5 items-center justify-center rounded-md bg-primary/10 text-primary"
                title="Tambah Kegiatan / Libur di tanggal ini"
              >
                <Plus class="size-3" />
              </span>
            </div>

            <!-- Daftar Badge Kegiatan & Libur di Dalam Sel Tanggal -->
            <div class="mt-1 space-y-1 overflow-hidden">
              <!-- Chip Hari Libur -->
              <div
                v-for="lib in cell.liburItems.slice(0, 2)"
                :key="'lib-' + lib.id"
                class="px-1.5 py-0.5 rounded text-[10px] font-medium truncate bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/20"
                :title="'Libur: ' + lib.keterangan"
              >
                {{ lib.keterangan }}
              </div>

              <!-- Chip Agenda Kegiatan -->
              <div
                v-for="ag in cell.agendaItems.slice(0, 2)"
                :key="'ag-' + ag.id"
                class="px-1.5 py-0.5 rounded text-[10px] font-medium truncate bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20"
                :title="'Agenda: ' + ag.judul"
              >
                {{ ag.judul }}
              </div>

              <!-- Indikator jika lebih dari 2 item -->
              <div
                v-if="(cell.liburItems.length + cell.agendaItems.length) > 2"
                class="text-[10px] font-medium text-muted-foreground pl-1"
              >
                +{{ (cell.liburItems.length + cell.agendaItems.length) - 2 }} lainnya
              </div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- DAFTAR RINGKASAN AGENDA & HARI LIBUR DI BAWAH KALENDER -->
    <Tabs v-model="activeTab" class="space-y-4">
      <TabsList class="grid w-full sm:w-80 grid-cols-2">
        <TabsTrigger value="agenda" class="gap-1.5 text-xs">
          <CalendarDays class="size-3.5" />
          <span>Daftar Agenda ({{ agendaList.length }})</span>
        </TabsTrigger>
        <TabsTrigger value="libur" class="gap-1.5 text-xs">
          <CalendarOff class="size-3.5" />
          <span>Daftar Hari Libur ({{ liburList.length }})</span>
        </TabsTrigger>
      </TabsList>

      <!-- TAB 1: AGENDA KEGIATAN -->
      <TabsContent value="agenda" class="space-y-4">
        <div v-if="agendaList.length === 0" class="py-12 text-center text-muted-foreground border rounded-xl bg-card">
          <CalendarDays class="size-9 mx-auto mb-2 text-muted-foreground/40" />
          <p class="text-xs font-semibold text-foreground">Belum ada agenda kegiatan.</p>
          <p v-if="canManageAgenda" class="text-[11px] text-muted-foreground mt-0.5">
            Klik salah satu tanggal pada kalender di atas untuk membuat jadwal kegiatan baru.
          </p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <Card
            v-for="item in agendaList"
            :key="item.id"
            class="flex flex-col justify-between transition-all hover:shadow-xs"
          >
            <CardHeader class="pb-2">
              <div class="flex items-center justify-between gap-2">
                <Badge variant="secondary" class="font-mono text-xs font-semibold">
                  {{ formatDateShort(item.tanggal) }}
                </Badge>
                <Button
                  v-if="canManageAgenda"
                  variant="ghost"
                  size="icon-xs"
                  class="size-7 text-destructive hover:bg-destructive/10"
                  title="Hapus Agenda"
                  @click="deleteAgenda(item.id)"
                >
                  <Trash2 class="size-3.5" />
                </Button>
              </div>
              <CardTitle class="text-sm font-semibold text-foreground pt-1">
                {{ item.judul }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p class="text-xs text-muted-foreground leading-relaxed line-clamp-3">
                {{ item.keterangan || 'Tidak ada catatan tambahan.' }}
              </p>
            </CardContent>
          </Card>
        </div>
      </TabsContent>

      <!-- TAB 2: HARI LIBUR -->
      <TabsContent value="libur" class="space-y-4">
        <Card class="overflow-hidden">
          <div class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow class="bg-muted/30">
                  <TableHead class="text-[10px] uppercase font-semibold">Tanggal Mulai</TableHead>
                  <TableHead class="text-[10px] uppercase font-semibold">Tanggal Selesai</TableHead>
                  <TableHead class="text-[10px] uppercase font-semibold">Keterangan Libur</TableHead>
                  <TableHead v-if="canManageAgenda" class="text-[10px] uppercase font-semibold text-center w-24">Aksi</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="liburList.length === 0">
                  <TableCell :colspan="canManageAgenda ? 4 : 3" class="h-28 text-center text-muted-foreground text-xs">
                    Belum ada data hari libur yang terdaftar.
                  </TableCell>
                </TableRow>

                <TableRow
                  v-for="lib in liburList"
                  :key="lib.id"
                  class="transition-colors hover:bg-muted/40"
                >
                  <TableCell class="font-mono font-medium text-xs text-foreground">{{ formatDateShort(lib.tanggal_mulai) }}</TableCell>
                  <TableCell class="font-mono font-medium text-xs text-foreground">{{ formatDateShort(lib.tanggal_selesai) }}</TableCell>
                  <TableCell class="text-xs font-medium text-foreground">{{ lib.keterangan }}</TableCell>
                  <TableCell v-if="canManageAgenda" class="text-center">
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      class="size-8 text-destructive hover:bg-destructive/10"
                      title="Hapus Hari Libur"
                      @click="deleteLibur(lib.id)"
                    >
                      <Trash2 class="size-3.5" />
                    </Button>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </Card>
      </TabsContent>
    </Tabs>

    <!-- DIALOG INTERAKTIF SAAT TANGGAL DIKLIK -->
    <Dialog :open="showDateModal" @update:open="(val) => { showDateModal = val; }">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold flex items-center gap-2">
            <CalendarDays class="size-4 text-primary" />
            <span>{{ formatFullDateIndo(selectedDateStr) }}</span>
          </DialogTitle>
          <DialogDescription class="text-xs">
            <template v-if="canManageAgenda">
              Rincian kegiatan pada tanggal ini. Anda dapat langsung menambahkan Agenda atau Hari Libur baru di bawah.
            </template>
            <template v-else>
              Informasi jadwal kegiatan santri dan hari libur TPQ pada tanggal ini.
            </template>
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4 pt-1">
          <!-- Daftar Kegiatan & Libur yang Sudah Ada pada Tanggal Terpilih -->
          <div class="space-y-2">
            <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
              Jadwal pada Tanggal Ini ({{ selectedDateAgendas.length + selectedDateLiburs.length }})
            </h4>

            <div
              v-if="selectedDateAgendas.length === 0 && selectedDateLiburs.length === 0"
              class="p-3.5 rounded-lg border bg-muted/20 text-center text-xs text-muted-foreground"
            >
              Tidak ada agenda kegiatan atau hari libur pada tanggal ini.
            </div>

            <div v-else class="space-y-2 max-h-48 overflow-y-auto pr-1">
              <!-- Item Libur -->
              <div
                v-for="lib in selectedDateLiburs"
                :key="'modal-lib-' + lib.id"
                class="p-3 rounded-lg border border-rose-500/30 bg-rose-500/5 flex items-start justify-between gap-3"
              >
                <div class="space-y-0.5">
                  <div class="flex items-center gap-1.5">
                    <Badge variant="destructive" class="text-[10px] px-1.5 py-0">Hari Libur</Badge>
                    <span class="text-[11px] font-mono text-muted-foreground">
                      {{ formatDateShort(lib.tanggal_mulai) }} s/d {{ formatDateShort(lib.tanggal_selesai) }}
                    </span>
                  </div>
                  <p class="text-xs font-semibold text-foreground pt-0.5">{{ lib.keterangan }}</p>
                </div>
                <Button
                  v-if="canManageAgenda"
                  variant="ghost"
                  size="icon-xs"
                  class="size-7 text-destructive hover:bg-destructive/10 shrink-0"
                  title="Hapus Hari Libur"
                  @click="deleteLibur(lib.id)"
                >
                  <Trash2 class="size-3.5" />
                </Button>
              </div>

              <!-- Item Agenda -->
              <div
                v-for="ag in selectedDateAgendas"
                :key="'modal-ag-' + ag.id"
                class="p-3 rounded-lg border border-emerald-500/30 bg-emerald-500/5 flex items-start justify-between gap-3"
              >
                <div class="space-y-0.5">
                  <div class="flex items-center gap-1.5">
                    <Badge class="text-[10px] px-1.5 py-0 bg-emerald-600 text-white">Agenda Kegiatan</Badge>
                    <span class="text-[11px] font-mono text-muted-foreground">
                      {{ formatDateShort(ag.tanggal) }}
                    </span>
                  </div>
                  <p class="text-xs font-semibold text-foreground pt-0.5">{{ ag.judul }}</p>
                  <p v-if="ag.keterangan" class="text-xs text-muted-foreground">{{ ag.keterangan }}</p>
                </div>
                <Button
                  v-if="canManageAgenda"
                  variant="ghost"
                  size="icon-xs"
                  class="size-7 text-destructive hover:bg-destructive/10 shrink-0"
                  title="Hapus Agenda"
                  @click="deleteAgenda(ag.id)"
                >
                  <Trash2 class="size-3.5" />
                </Button>
              </div>
            </div>
          </div>

          <!-- FORM TAMBAH LANGSUNG DARI KALENDER (KHUSUS ADMIN) -->
          <div v-if="canManageAgenda" class="border-t pt-4 space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-foreground">Tambah Jadwal Baru di Tanggal Ini:</span>
              <div class="inline-flex rounded-lg border p-0.5 bg-muted/40">
                <button
                  type="button"
                  class="px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer"
                  :class="modalFormType === 'agenda' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                  @click="modalFormType = 'agenda'"
                >
                  Agenda Kegiatan
                </button>
                <button
                  type="button"
                  class="px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer"
                  :class="modalFormType === 'libur' ? 'bg-background text-rose-600 dark:text-rose-400 shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                  @click="modalFormType = 'libur'"
                >
                  Hari Libur
                </button>
              </div>
            </div>

            <!-- Form Tambah Agenda -->
            <form v-if="modalFormType === 'agenda'" class="space-y-3" @submit.prevent="saveAgenda">
              <div class="space-y-1.5">
                <Label for="modalAgendaTanggal" class="text-xs font-medium">Tanggal Kegiatan *</Label>
                <Input
                  id="modalAgendaTanggal"
                  v-model="agendaForm.tanggal"
                  type="date"
                  required
                  class="h-9 text-xs font-mono"
                />
              </div>

              <div class="space-y-1.5">
                <Label for="modalAgendaJudul" class="text-xs font-medium">Judul Kegiatan *</Label>
                <Input
                  id="modalAgendaJudul"
                  v-model="agendaForm.judul"
                  type="text"
                  required
                  placeholder="Contoh: Ujian Kenaikan Jilid / Khataman"
                  class="h-9 text-xs"
                />
              </div>

              <div class="space-y-1.5">
                <Label for="modalAgendaKeterangan" class="text-xs font-medium">Keterangan / Detail</Label>
                <Textarea
                  id="modalAgendaKeterangan"
                  v-model="agendaForm.keterangan"
                  rows="2"
                  placeholder="Catatan tambahan kegiatan..."
                  class="text-xs"
                />
              </div>

              <DialogFooter class="flex gap-2 sm:justify-end pt-2">
                <Button type="button" variant="outline" size="sm" class="text-xs" @click="showDateModal = false">
                  Tutup
                </Button>
                <Button type="submit" size="sm" class="text-xs" :disabled="isSubmitting">
                  Simpan Agenda
                </Button>
              </DialogFooter>
            </form>

            <!-- Form Tambah Hari Libur -->
            <form v-else class="space-y-3" @submit.prevent="saveLibur">
              <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                  <Label for="modalLiburMulai" class="text-xs font-medium">Tanggal Mulai *</Label>
                  <Input
                    id="modalLiburMulai"
                    v-model="liburForm.tanggal_mulai"
                    type="date"
                    required
                    class="h-9 text-xs font-mono"
                  />
                </div>
                <div class="space-y-1.5">
                  <Label for="modalLiburSelesai" class="text-xs font-medium">Tanggal Selesai *</Label>
                  <Input
                    id="modalLiburSelesai"
                    v-model="liburForm.tanggal_selesai"
                    type="date"
                    required
                    class="h-9 text-xs font-mono"
                  />
                </div>
              </div>

              <div class="space-y-1.5">
                <Label for="modalLiburKeterangan" class="text-xs font-medium">Keterangan Hari Libur *</Label>
                <Input
                  id="modalLiburKeterangan"
                  v-model="liburForm.keterangan"
                  type="text"
                  required
                  placeholder="Contoh: Libur Nasional / Libur Awal Ramadhan"
                  class="h-9 text-xs"
                />
              </div>

              <DialogFooter class="flex gap-2 sm:justify-end pt-2">
                <Button type="button" variant="outline" size="sm" class="text-xs" @click="showDateModal = false">
                  Tutup
                </Button>
                <Button type="submit" size="sm" variant="destructive" class="text-xs" :disabled="isSubmitting">
                  Simpan Hari Libur
                </Button>
              </DialogFooter>
            </form>
          </div>

          <!-- Footer Tutup untuk Non-Admin (Read-Only) -->
          <DialogFooter v-else class="pt-2">
            <Button type="button" variant="outline" size="sm" class="text-xs" @click="showDateModal = false">
              Tutup
            </Button>
          </DialogFooter>
        </div>
      </DialogContent>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import {
  CalendarDays,
  CalendarOff,
  ChevronLeft,
  ChevronRight,
  Plus,
  Trash2,
} from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
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
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';

const toast = useToast();
const { canManageAgenda } = useAuth();

const activeTab = ref('agenda');
const agendaList = ref([]);
const liburList = ref([]);
const isSubmitting = ref(false);

const now = new Date();
const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

const currentYear = ref(now.getFullYear());
const currentMonth = ref(now.getMonth()); // 0 - 11

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];
const dayHeaders = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

// State Dialog Klik Tanggal
const showDateModal = ref(false);
const selectedDateStr = ref(todayStr);
const modalFormType = ref('agenda');

const agendaForm = ref({ tanggal: todayStr, judul: '', keterangan: '' });
const liburForm = ref({ tanggal_mulai: todayStr, tanggal_selesai: todayStr, keterangan: '' });

function formatYmd(year, monthZeroBased, day) {
  return `${year}-${String(monthZeroBased + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function normalizeDateStr(val) {
  if (!val) return '';
  return String(val).slice(0, 10);
}

function formatDateShort(val) {
  const clean = normalizeDateStr(val);
  if (!clean) return '-';
  const [y, m, d] = clean.split('-').map(Number);
  if (!y || !m || !d) return clean;
  return `${String(d).padStart(2, '0')} ${monthNames[m - 1]?.slice(0, 3) || ''} ${y}`;
}

function formatFullDateIndo(val) {
  const clean = normalizeDateStr(val);
  if (!clean) return '-';
  const [y, m, d] = clean.split('-').map(Number);
  const dateObj = new Date(y, m - 1, d);
  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(dateObj);
}

function getAgendasForDate(dateStr) {
  return agendaList.value.filter((ag) => {
    const start = normalizeDateStr(ag.tanggal);
    const end = normalizeDateStr(ag.tanggal_selesai || ag.tanggal);
    return dateStr >= start && dateStr <= end;
  });
}

function getLibursForDate(dateStr) {
  return liburList.value.filter((lib) => {
    const start = normalizeDateStr(lib.tanggal_mulai);
    const end = normalizeDateStr(lib.tanggal_selesai || lib.tanggal_mulai);
    return dateStr >= start && dateStr <= end;
  });
}

const selectedDateAgendas = computed(() => getAgendasForDate(selectedDateStr.value));
const selectedDateLiburs = computed(() => getLibursForDate(selectedDateStr.value));

const calendarCells = computed(() => {
  const year = currentYear.value;
  const month = currentMonth.value;

  const firstDayOfMonth = new Date(year, month, 1);
  // Konversi Minggu=0..Sabtu=6 menjadi Senin=0..Minggu=6
  const startOffset = (firstDayOfMonth.getDay() + 6) % 7;
  const daysInCurrentMonth = new Date(year, month + 1, 0).getDate();
  const daysInPrevMonth = new Date(year, month, 0).getDate();

  const cells = [];

  // 1. Padding bulan sebelumnya
  for (let i = startOffset - 1; i >= 0; i--) {
    const d = daysInPrevMonth - i;
    const prevDate = new Date(year, month - 1, d);
    const dateStr = formatYmd(prevDate.getFullYear(), prevDate.getMonth(), prevDate.getDate());
    cells.push({
      dateStr,
      dayNumber: d,
      isCurrentMonth: false,
      isToday: dateStr === todayStr,
      isSunday: prevDate.getDay() === 0,
      agendaItems: getAgendasForDate(dateStr),
      liburItems: getLibursForDate(dateStr),
    });
  }

  // 2. Tanggal bulan berjalan
  for (let d = 1; d <= daysInCurrentMonth; d++) {
    const curDate = new Date(year, month, d);
    const dateStr = formatYmd(year, month, d);
    cells.push({
      dateStr,
      dayNumber: d,
      isCurrentMonth: true,
      isToday: dateStr === todayStr,
      isSunday: curDate.getDay() === 0,
      agendaItems: getAgendasForDate(dateStr),
      liburItems: getLibursForDate(dateStr),
    });
  }

  // 3. Padding bulan berikutnya hingga genap 42 sel (6 baris x 7 hari)
  const remaining = 42 - cells.length;
  for (let d = 1; d <= remaining; d++) {
    const nextDate = new Date(year, month + 1, d);
    const dateStr = formatYmd(nextDate.getFullYear(), nextDate.getMonth(), nextDate.getDate());
    cells.push({
      dateStr,
      dayNumber: d,
      isCurrentMonth: false,
      isToday: dateStr === todayStr,
      isSunday: nextDate.getDay() === 0,
      agendaItems: getAgendasForDate(dateStr),
      liburItems: getLibursForDate(dateStr),
    });
  }

  return cells;
});

function prevMonth() {
  if (currentMonth.value === 0) {
    currentMonth.value = 11;
    currentYear.value--;
  } else {
    currentMonth.value--;
  }
}

function nextMonth() {
  if (currentMonth.value === 11) {
    currentMonth.value = 0;
    currentYear.value++;
  } else {
    currentMonth.value++;
  }
}

function goToToday() {
  const d = new Date();
  currentYear.value = d.getFullYear();
  currentMonth.value = d.getMonth();
}

function onSelectDateCell(cell) {
  selectedDateStr.value = cell.dateStr;
  agendaForm.value = {
    tanggal: cell.dateStr,
    judul: '',
    keterangan: '',
  };
  liburForm.value = {
    tanggal_mulai: cell.dateStr,
    tanggal_selesai: cell.dateStr,
    keterangan: '',
  };
  showDateModal.value = true;
}

function openQuickAddOnDate(dateStr, formType = 'agenda') {
  selectedDateStr.value = dateStr;
  modalFormType.value = formType;
  agendaForm.value = {
    tanggal: dateStr,
    judul: '',
    keterangan: '',
  };
  liburForm.value = {
    tanggal_mulai: dateStr,
    tanggal_selesai: dateStr,
    keterangan: '',
  };
  showDateModal.value = true;
}

const loadAgenda = async () => {
  try {
    const res = await api.get('/agenda');
    if (res.data.success) {
      agendaList.value = res.data.data || [];
    }
  } catch (e) {}
};

const loadLibur = async () => {
  try {
    const res = await api.get('/hari-libur');
    if (res.data.success) {
      liburList.value = res.data.data || [];
    }
  } catch (e) {}
};

const saveAgenda = async () => {
  if (!canManageAgenda.value) return;
  isSubmitting.value = true;
  try {
    await api.post('/agenda', agendaForm.value);
    toast.success('Agenda kegiatan berhasil ditambahkan!');
    agendaForm.value.judul = '';
    agendaForm.value.keterangan = '';
    await loadAgenda();
  } catch (e) {
    toast.error('Gagal menyimpan agenda kegiatan.');
  } finally {
    isSubmitting.value = false;
  }
};

const deleteAgenda = async (id) => {
  if (!canManageAgenda.value) return;
  if (confirm('Hapus agenda kegiatan ini?')) {
    try {
      await api.delete(`/agenda/${id}`);
      toast.success('Agenda berhasil dihapus.');
      await loadAgenda();
    } catch (e) {
      toast.error('Gagal menghapus agenda.');
    }
  }
};

const saveLibur = async () => {
  if (!canManageAgenda.value) return;
  isSubmitting.value = true;
  try {
    await api.post('/hari-libur', liburForm.value);
    toast.success('Hari libur berhasil ditambahkan!');
    liburForm.value.keterangan = '';
    await loadLibur();
  } catch (e) {
    toast.error('Gagal menyimpan hari libur.');
  } finally {
    isSubmitting.value = false;
  }
};

const deleteLibur = async (id) => {
  if (!canManageAgenda.value) return;
  if (confirm('Hapus hari libur ini?')) {
    try {
      await api.delete(`/hari-libur/${id}`);
      toast.success('Hari libur berhasil dihapus.');
      await loadLibur();
    } catch (e) {
      toast.error('Gagal menghapus hari libur.');
    }
  }
};

onMounted(() => {
  loadAgenda();
  loadLibur();
});
</script>
