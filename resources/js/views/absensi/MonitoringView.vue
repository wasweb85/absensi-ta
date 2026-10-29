<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between pb-2 border-b">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-lg font-semibold tracking-tight text-foreground">
            Monitoring
          </h2>
          <Badge variant="outline" class="font-normal text-xs text-muted-foreground gap-1.5">
            <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Live Monitoring</span>
          </Badge>
        </div>
        <p class="text-xs text-muted-foreground mt-0.5">
          Pantau status presensi santri per kelas, ubah kehadiran manual, dan tambahkan catatan izin/sakit.
        </p>
      </div>
    </div>

    <!-- Filter Card -->
    <Card>
      <CardContent class="p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
          <!-- Date Filter -->
          <div class="lg:col-span-3 space-y-1.5">
            <Label for="filterDate" class="text-xs font-medium text-muted-foreground">
              Tanggal Absensi
            </Label>
            <Input
              id="filterDate"
              v-model="filterDate"
              type="date"
              class="h-9 text-xs"
              @change="loadMonitoring"
            />
          </div>

          <!-- Class Dropdown -->
          <div class="lg:col-span-3 space-y-1.5">
            <div class="flex items-center justify-between">
              <Label for="filterKelas" class="text-xs font-medium text-muted-foreground">
                Filter Jilid / Kelas
              </Label>
              <span v-if="user?.nama_kelas && selectedKelas == user?.id_kelas" class="text-[10px] text-primary font-medium truncate">
                ({{ user.nama_kelas }})
              </span>
            </div>
            <select
              id="filterKelas"
              v-model="selectedKelas"
              class="h-9 w-full rounded-lg border border-input bg-transparent px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              @change="loadMonitoring"
            >
              <option value="">Semua Jilid / Kelas</option>
              <option v-for="k in kelasList" :key="k.id" :value="k.id">
                {{ k.nama_kelas }} ({{ k.wali_kelas || 'Wali -' }})
              </option>
            </select>
          </div>

          <!-- Search Box -->
          <div :class="canScanQr ? 'lg:col-span-4' : 'lg:col-span-6'" class="space-y-1.5">
            <Label for="searchSantri" class="text-xs font-medium text-muted-foreground">
              Cari Santri
            </Label>
            <div class="relative">
              <Search class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <Input
                id="searchSantri"
                v-model="searchQuery"
                type="text"
                placeholder="Ketik nama atau NISN..."
                class="h-9 pl-9 text-xs"
              />
            </div>
          </div>

          <!-- Buka Scanner Button -->
          <div v-if="canScanQr" class="lg:col-span-2">
            <Button as-child class="h-9 w-full gap-1.5 text-xs">
              <router-link to="/absensi/scanner">
                <ScanLine class="size-3.5" />
                <span>Buka Scanner</span>
              </router-link>
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Stat Metrics Cards -->
    <div class="grid grid-cols-4 sm:grid-cols-4 lg:grid-cols-7 gap-1.5 sm:gap-3">
      <Card class="p-2 sm:p-3 text-center">
        <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-muted-foreground block truncate">Total</span>
        <span class="text-base sm:text-xl font-bold tracking-tight text-foreground">{{ stats.total }}</span>
      </Card>
      
      <Card class="p-2 sm:p-3 text-center border-emerald-500/20 bg-emerald-500/5">
        <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block truncate">Hadir</span>
        <span class="text-base sm:text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ stats.hadir }}</span>
      </Card>

      <Card class="p-2 sm:p-3 text-center border-amber-500/20 bg-amber-500/5">
        <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400 block truncate">Telat</span>
        <span class="text-base sm:text-xl font-bold tracking-tight text-amber-600 dark:text-amber-400">{{ stats.terlambat }}</span>
      </Card>

      <Card class="p-2 sm:p-3 text-center border-blue-500/20 bg-blue-500/5">
        <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400 block truncate">Sakit</span>
        <span class="text-base sm:text-xl font-bold tracking-tight text-blue-600 dark:text-blue-400">{{ stats.sakit }}</span>
      </Card>

      <Card class="p-2 sm:p-3 text-center border-indigo-500/20 bg-indigo-500/5">
        <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 block truncate">Izin</span>
        <span class="text-base sm:text-xl font-bold tracking-tight text-indigo-600 dark:text-indigo-400">{{ stats.izin }}</span>
      </Card>

      <Card class="p-2 sm:p-3 text-center border-rose-500/20 bg-rose-500/5">
        <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400 block truncate">Alpa</span>
        <span class="text-base sm:text-xl font-bold tracking-tight text-rose-600 dark:text-rose-400">{{ stats.alpa }}</span>
      </Card>

      <Card class="p-2 sm:p-3 text-center bg-muted/40 col-span-2 sm:col-span-1">
        <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-muted-foreground block truncate">Belum Absen</span>
        <span class="text-base sm:text-xl font-bold tracking-tight text-foreground">{{ stats.belum_absen }}</span>
      </Card>
    </div>

    <!-- Live Data Table -->
    <Card class="overflow-hidden">
      <div class="overflow-x-auto">
        <Table class="w-full table-fixed">
          <TableHeader>
            <TableRow class="bg-muted/30">
              <TableHead class="w-7 sm:w-12 text-center text-[10px] uppercase whitespace-nowrap px-1 sm:px-4">No</TableHead>
              <TableHead class="text-[10px] uppercase px-1.5 sm:px-4">Santri</TableHead>
              <TableHead class="w-12 sm:w-16 text-center text-[10px] uppercase whitespace-nowrap px-0.5 sm:px-3">Kelas</TableHead>
              <TableHead class="w-[122px] sm:w-36 text-right sm:text-left text-[10px] uppercase whitespace-nowrap px-1 sm:px-4">Status</TableHead>
              <TableHead class="hidden md:table-cell text-[10px] uppercase whitespace-nowrap px-2 sm:px-4 w-28">Jam Masuk</TableHead>
              <TableHead class="hidden md:table-cell text-[10px] uppercase whitespace-nowrap px-2 sm:px-4 w-52">Keterangan / Catatan</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="filteredStudents.length === 0">
              <TableCell colspan="6" class="h-32 text-center text-muted-foreground text-xs">
                Tidak ada data santri yang cocok dengan kriteria filter.
              </TableCell>
            </TableRow>

            <TableRow
              v-for="(s, idx) in filteredStudents"
              :key="s.id_siswa"
              class="transition-colors hover:bg-muted/40"
            >
              <TableCell class="text-center font-mono text-[11px] sm:text-xs text-muted-foreground px-1 sm:px-4 w-7 sm:w-12 align-middle">
                {{ idx + 1 }}
              </TableCell>
              
              <TableCell class="py-2 px-1.5 sm:px-4 align-middle">
                <div class="min-w-0 pr-0.5">
                  <p class="font-semibold text-foreground text-xs sm:text-sm leading-tight break-words">
                    {{ s.nama_lengkap }}
                  </p>
                  <p class="text-[10px] sm:text-[11px] text-muted-foreground font-mono mt-0.5">
                    {{ s.nisn }}
                  </p>
                  <!-- Jam Masuk & Catatan di Mobile jika ada -->
                  <div v-if="s.waktu_masuk || s.keterangan" class="mt-1 flex flex-wrap items-center gap-1 md:hidden">
                    <span v-if="s.waktu_masuk" class="inline-flex items-center text-[9px] text-muted-foreground font-mono bg-muted/60 px-1 py-0.2 rounded">
                      {{ s.waktu_masuk }} WIB
                    </span>
                    <span v-if="s.keterangan" class="inline-flex items-center text-[9px] text-amber-600 dark:text-amber-400 italic bg-amber-500/10 px-1 py-0.2 rounded">
                      {{ s.keterangan }}
                    </span>
                  </div>
                </div>
              </TableCell>

              <!-- Jilid / Kelas Compact Badge Mirip Desain Mobile Gambar 2 -->
              <TableCell class="px-0.5 sm:px-3 text-center align-middle w-12 sm:w-16">
                <div
                  class="inline-flex flex-col items-center justify-center rounded-md px-1 py-0.5 text-center border font-semibold leading-tight shadow-2xs w-full max-w-[46px] mx-auto"
                  :class="s.warna_label ? '' : 'bg-primary/10 text-primary border-primary/20'"
                  :style="s.warna_label ? { borderColor: s.warna_label, color: s.warna_label, backgroundColor: s.warna_label + '15' } : {}"
                >
                  <span class="text-[8px] uppercase tracking-wider opacity-80">{{ s.nama_kelas && s.nama_kelas.toLowerCase().includes('jilid') ? 'JILID' : 'KELAS' }}</span>
                  <span class="text-xs font-bold leading-none mt-0.5">{{ s.nama_kelas ? s.nama_kelas.replace(/jilid\s*/i, '').replace(/kelas\s*/i, '') : '-' }}</span>
                </div>
              </TableCell>

              <!-- Status Kehadiran (Dengan Shadcn DropdownMenu jika punya izin edit) -->
              <TableCell class="py-2 px-1 sm:px-4 text-right sm:text-left align-middle w-[122px] sm:w-36">
                <DropdownMenu v-if="canEditAbsensi">
                  <DropdownMenuTrigger as-child>
                    <Button
                      variant="outline"
                      size="sm"
                      class="h-7 w-full sm:w-auto justify-between gap-1 px-1.5 text-[10.5px] sm:text-xs font-medium bg-muted/30 hover:bg-muted/60 border-input shadow-2xs"
                      :disabled="updatingStudentId === s.id_siswa"
                    >
                      <span class="size-1.5 shrink-0 rounded-full" :class="getStatusDotClass(s.status)"></span>
                      <span class="truncate tracking-tight">{{ s.status }}</span>
                      <LoaderCircle v-if="updatingStudentId === s.id_siswa" class="size-3 shrink-0 animate-spin text-muted-foreground ml-auto" />
                      <ChevronDown v-else class="size-3 shrink-0 text-muted-foreground ml-auto" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" class="w-48">
                    <DropdownMenuLabel class="text-[10px] uppercase tracking-wider text-muted-foreground">
                      Ubah Status
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                      v-for="opt in statusOptions"
                      :key="opt.value"
                      class="flex items-center justify-between text-xs"
                      @select="selectStatus(s, opt.value)"
                    >
                      <div class="flex items-center gap-2">
                        <span class="size-2 rounded-full" :class="opt.dotClass"></span>
                        <span>{{ opt.label }}</span>
                      </div>
                      <Check v-if="s.status === opt.value" class="size-3.5 text-primary" />
                    </DropdownMenuItem>

                    <!-- Opsi Tambah / Edit Catatan Khusus Mobile -->
                    <DropdownMenuSeparator class="md:hidden" />
                    <DropdownMenuItem class="md:hidden text-xs" @select="openCatatanModal(s)">
                      <div class="flex items-center gap-2 text-foreground">
                        <Pencil class="size-3 text-muted-foreground" />
                        <span>{{ s.keterangan ? 'Edit Catatan' : 'Tambah Catatan' }}</span>
                      </div>
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <!-- Static Badge jika bukan role editor -->
                <Badge
                  v-else
                  :variant="getStatusBadgeVariant(s.status)"
                  class="gap-1 text-[11px] sm:text-xs font-medium w-full sm:w-auto justify-center px-1.5 py-1"
                >
                  <span class="size-1.5 rounded-full" :class="getStatusDotClass(s.status)"></span>
                  <span>{{ s.status }}</span>
                </Badge>
              </TableCell>

              <!-- Jam Masuk (Hanya desktop) -->
              <TableCell class="hidden md:table-cell font-mono text-xs text-foreground whitespace-nowrap px-2 sm:px-4 align-middle">
                {{ s.waktu_masuk ? s.waktu_masuk + ' WIB' : '-' }}
              </TableCell>

              <!-- Kolom Keterangan / Catatan langsung diisi di kolom ini (Hanya desktop) -->
              <TableCell class="hidden md:table-cell py-2 px-2 sm:px-4 align-middle">
                <div v-if="canEditAbsensi" class="relative flex items-center max-w-[240px]">
                  <Input
                    v-model="s.keterangan"
                    type="text"
                    placeholder="Tulis catatan..."
                    class="h-7 text-xs pr-7 bg-background/50 hover:bg-background focus:bg-background"
                    :disabled="updatingKeteranganId === s.id_siswa"
                    @change="saveDirectKeterangan(s)"
                    @keyup.enter="$event.target.blur()"
                  />
                  <LoaderCircle
                    v-if="updatingKeteranganId === s.id_siswa"
                    class="size-3 animate-spin text-muted-foreground absolute right-2 pointer-events-none"
                  />
                </div>
                <span v-else class="text-xs text-muted-foreground">
                  {{ s.keterangan || '-' }}
                </span>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </Card>

    <!-- Dialog Catatan untuk Mobile -->
    <Dialog :open="catatanModalOpen" @update:open="catatanModalOpen = $event">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-sm font-semibold">Catatan Kehadiran</DialogTitle>
          <DialogDescription class="text-xs text-muted-foreground">
            Santri: <strong class="text-foreground">{{ activeStudentForCatatan?.nama_lengkap }}</strong>
          </DialogDescription>
        </DialogHeader>
        <div class="py-2">
          <Label for="modalKeterangan" class="text-xs text-muted-foreground mb-1 block">Catatan / Alasan Izin / Sakit</Label>
          <Input
            id="modalKeterangan"
            v-model="catatanInput"
            placeholder="Contoh: Izin acara keluarga, Sakit demam..."
            class="text-xs h-9"
            @keyup.enter="saveModalCatatan"
          />
        </div>
        <DialogFooter class="flex gap-2 sm:justify-end">
          <Button variant="outline" size="sm" class="text-xs" @click="catatanModalOpen = false">Batal</Button>
          <Button size="sm" class="text-xs" :disabled="savingCatatan" @click="saveModalCatatan">
            <LoaderCircle v-if="savingCatatan" class="size-3 animate-spin mr-1" />
            Simpan Catatan
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import {
  Search,
  ScanLine,
  ChevronDown,
  Check,
  LoaderCircle,
  Pencil,
} from '@lucide/vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import api from '@/services/api';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';

const toast = useToast();
const { user, canEditAbsensi, canScanQr } = useAuth();

const loading = ref(false);
const filterDate = ref(new Date().toISOString().split('T')[0]);
const selectedKelas = ref(user.value?.id_kelas ? user.value.id_kelas : '');
const searchQuery = ref('');
const kelasList = ref([]);
const students = ref([]);
const stats = ref({ total: 0, hadir: 0, terlambat: 0, sakit: 0, izin: 0, alpa: 0, belum_absen: 0 });

const updatingStudentId = ref(null);
const updatingKeteranganId = ref(null);

const statusOptions = [
  { value: 'Hadir', label: 'Hadir', dotClass: 'bg-emerald-500' },
  { value: 'Terlambat', label: 'Terlambat', dotClass: 'bg-amber-500' },
  { value: 'Sakit', label: 'Sakit', dotClass: 'bg-blue-500' },
  { value: 'Izin', label: 'Izin', dotClass: 'bg-indigo-500' },
  { value: 'Alpa', label: 'Alpa', dotClass: 'bg-rose-500' },
];

const getStatusBadgeVariant = (status) => {
  switch (status) {
    case 'Hadir': return 'default';
    case 'Terlambat': return 'secondary';
    case 'Sakit':
    case 'Izin': return 'outline';
    case 'Alpa': return 'destructive';
    default: return 'outline';
  }
};

const getStatusDotClass = (status) => {
  switch (status) {
    case 'Hadir': return 'bg-emerald-500';
    case 'Terlambat': return 'bg-amber-500';
    case 'Sakit': return 'bg-blue-500';
    case 'Izin': return 'bg-indigo-500';
    case 'Alpa': return 'bg-rose-500';
    default: return 'bg-muted-foreground/60';
  }
};

const filteredStudents = computed(() => {
  return students.value.filter(s => {
    if (!searchQuery.value) return true;
    const q = searchQuery.value.toLowerCase();
    return s.nama_lengkap.toLowerCase().includes(q) || s.nisn.toLowerCase().includes(q);
  });
});

const recalculateStats = () => {
  const list = students.value;
  stats.value = {
    total: list.length,
    hadir: list.filter(s => s.status === 'Hadir').length,
    terlambat: list.filter(s => s.status === 'Terlambat').length,
    sakit: list.filter(s => s.status === 'Sakit').length,
    izin: list.filter(s => s.status === 'Izin').length,
    alpa: list.filter(s => s.status === 'Alpa').length,
    belum_absen: list.filter(s => s.status === 'Belum Absen').length,
  };
};

const selectStatus = async (student, statusVal) => {
  if (student.status === statusVal) return;

  updatingStudentId.value = student.id_siswa;

  try {
    const res = await api.post('/absensi/status', {
      id_siswa: student.id_siswa,
      tanggal: filterDate.value,
      status: statusVal,
      keterangan: student.keterangan || null,
    });

    if (res.data.success) {
      toast.success(`Status ${student.nama_lengkap} diubah ke ${statusVal}`);
      student.status = statusVal;
      if (res.data.data?.waktu_masuk) {
        student.waktu_masuk = res.data.data.waktu_masuk;
      } else if (['Sakit', 'Izin', 'Alpa'].includes(statusVal)) {
        student.waktu_masuk = null;
      }
      recalculateStats();
    }
  } catch (e) {
    toast.error('Gagal memperbarui status absensi.');
  } finally {
    updatingStudentId.value = null;
  }
};

const saveDirectKeterangan = async (student) => {
  updatingKeteranganId.value = student.id_siswa;

  try {
    const res = await api.post('/absensi/status', {
      id_siswa: student.id_siswa,
      tanggal: filterDate.value,
      status: student.status === 'Belum Absen' ? 'Hadir' : student.status,
      keterangan: student.keterangan || null,
    });

    if (res.data.success) {
      toast.success(`Catatan untuk ${student.nama_lengkap} disimpan`);
      if (student.status === 'Belum Absen') {
        student.status = 'Hadir';
        if (res.data.data?.waktu_masuk) {
          student.waktu_masuk = res.data.data.waktu_masuk;
        }
        recalculateStats();
      }
    }
  } catch (e) {
    toast.error('Gagal menyimpan keterangan.');
  } finally {
    updatingKeteranganId.value = null;
  }
};

const catatanModalOpen = ref(false);
const activeStudentForCatatan = ref(null);
const catatanInput = ref('');
const savingCatatan = ref(false);

const openCatatanModal = (student) => {
  activeStudentForCatatan.value = student;
  catatanInput.value = student.keterangan || '';
  catatanModalOpen.value = true;
};

const saveModalCatatan = async () => {
  if (!activeStudentForCatatan.value) return;
  savingCatatan.value = true;
  try {
    const student = activeStudentForCatatan.value;
    const res = await api.post('/absensi/status', {
      id_siswa: student.id_siswa,
      tanggal: filterDate.value,
      status: student.status === 'Belum Absen' ? 'Hadir' : student.status,
      keterangan: catatanInput.value || null,
    });
    if (res.data.success) {
      toast.success(`Catatan untuk ${student.nama_lengkap} disimpan`);
      student.keterangan = catatanInput.value || null;
      if (student.status === 'Belum Absen') {
        student.status = 'Hadir';
        if (res.data.data?.waktu_masuk) {
          student.waktu_masuk = res.data.data.waktu_masuk;
        }
        recalculateStats();
      }
      catatanModalOpen.value = false;
    }
  } catch (e) {
    toast.error('Gagal menyimpan catatan.');
  } finally {
    savingCatatan.value = false;
  }
};

const loadKelas = async () => {
  try {
    const res = await api.get('/kelas');
    if (res.data.success) {
      kelasList.value = res.data.data;
    }
  } catch (e) {}
};

const loadMonitoring = async () => {
  loading.value = true;
  try {
    const params = { tanggal: filterDate.value };
    if (selectedKelas.value) {
      params.id_kelas = selectedKelas.value;
    }
    const res = await api.get('/absensi/monitoring', { params });
    if (res.data.success) {
      stats.value = res.data.data.stats;
      students.value = res.data.data.students;
    }
  } catch (e) {
    toast.error('Gagal memuat data monitoring.');
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  if (user.value?.id_kelas && !selectedKelas.value) {
    selectedKelas.value = user.value.id_kelas;
  }
  await Promise.all([loadKelas(), loadMonitoring()]);
});
</script>
