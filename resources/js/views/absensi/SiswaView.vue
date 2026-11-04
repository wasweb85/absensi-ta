<template>
  <div class="space-y-6">
    <!-- Kontainer Tunggal Terpadu (Judul, Tombol Aksi, Filter, Pencarian, dan Tabel Menyatu) -->
    <Card class="overflow-hidden py-0 gap-0">
      <!-- Toolbar Atas di Dalam Kontainer Card (py-0 pada Card menghilangkan padding bawaan 16px sehingga teks mepet rapi ke garis atas kontainer) -->
      <div class="px-3 pt-1.5 pb-2.5 sm:px-4 sm:pt-3 sm:pb-3.5 border-b border-border bg-card space-y-2 sm:space-y-3">
        <!-- Baris 1: Judul & Badge [Kiri] sejajar dengan Tombol Import CSV & Tambah Santri [Kanan] -->
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-1.5 sm:gap-2">
            <h2 class="text-sm sm:text-lg font-semibold tracking-tight text-foreground">
              Data Santri
            </h2>
            <Badge variant="outline" class="font-normal text-[11px] sm:text-xs text-muted-foreground px-1.5 py-0">
              {{ siswaList.length }} Santri
            </Badge>
          </div>

          <!-- Tombol Aksi (Import CSV & Tambah Santri - Di Mobile Cukup Icon Saja) -->
          <div v-if="canManageSiswa" class="flex items-center gap-1.5 sm:gap-2 shrink-0">
            <Button
              variant="outline"
              size="sm"
              class="h-8 sm:h-9 px-2.5 sm:px-3 gap-1.5 text-xs"
              title="Import CSV"
              @click="showImportModal = true"
            >
              <Upload class="size-3.5" />
              <span class="hidden sm:inline">Import CSV</span>
            </Button>
            <Button
              size="sm"
              class="h-8 sm:h-9 px-2.5 sm:px-3 gap-1.5 text-xs"
              title="Tambah Santri"
              @click="openAddModal"
            >
              <Plus class="size-3.5" />
              <span class="hidden sm:inline">Tambah Santri</span>
            </Button>
          </div>
        </div>

        <!-- Baris 2: Filter Jilid & Status dan Kolom Pencarian -->
        <!-- Di Mobile: Filter di atas (order-1), Kolom Pencarian di paling bawah (order-2) -->
        <!-- Di Desktop: Kolom Pencarian di kiri (md:order-1), Filter di kanan (md:order-2) -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2.5">
          <!-- Filter Jilid & Status (Mobile: order-1 di atas kolom pencarian, Desktop: md:order-2) -->
          <div class="order-1 md:order-2 flex items-center gap-2">
            <select
              id="filterKelas"
              v-model="filterKelas"
              class="h-9 flex-1 md:flex-none rounded-lg border border-input bg-background px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer min-w-[130px]"
              title="Filter Jilid"
              @change="loadSiswa"
            >
              <option value="">Semua Jilid</option>
              <option v-for="k in kelasList" :key="k.id" :value="k.id">
                {{ getCleanNamaKelas(k.nama_kelas) }}
              </option>
            </select>

            <select
              id="filterStatus"
              v-model="filterStatus"
              class="h-9 flex-1 md:flex-none rounded-lg border border-input bg-background px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer min-w-[125px]"
              title="Status Keaktifan"
              @change="loadSiswa"
            >
              <option value="">Semua Status</option>
              <option value="aktif">Status: Aktif</option>
              <option value="nonaktif">Status: Nonaktif</option>
              <option value="lulus">Status: Lulus</option>
              <option value="pindah">Status: Pindah</option>
            </select>
          </div>

          <!-- Kolom Pencarian (Mobile: order-2 di paling bawah, Desktop: md:order-1 di kiri) -->
          <div class="order-2 md:order-1 relative flex-1 min-w-[220px] max-w-md">
            <Search class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
            <Input
              id="searchSantri"
              v-model="search"
              type="text"
              placeholder="Cari nama santri, NISN, atau no HP..."
              class="h-9 pl-9 text-xs bg-background w-full"
              @input="onSearchInput"
            />
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow class="bg-muted/30">
              <TableHead class="w-12 text-center text-[10px] uppercase">No</TableHead>
              <TableHead class="text-[10px] uppercase">NISN</TableHead>
              <TableHead class="text-[10px] uppercase">Nama Lengkap</TableHead>
              <TableHead class="text-[10px] uppercase text-center">L/P</TableHead>
              <TableHead class="text-[10px] uppercase">Jilid</TableHead>
              <TableHead class="text-[10px] uppercase">Wali &amp; WhatsApp</TableHead>
              <TableHead class="text-[10px] uppercase text-center">Status</TableHead>
              <TableHead class="text-[10px] uppercase text-center">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="isLoading">
              <TableCell colspan="8" class="h-36 text-center text-muted-foreground text-xs">
                <div class="flex flex-col items-center justify-center gap-2">
                  <LoaderCircle class="size-6 animate-spin text-primary" />
                  <span>Memuat data santri...</span>
                </div>
              </TableCell>
            </TableRow>

            <TableRow v-else-if="siswaList.length === 0">
              <TableCell colspan="8" class="h-32 text-center text-muted-foreground text-xs">
                Tidak ada data santri yang ditemukan.
              </TableCell>
            </TableRow>

            <TableRow
              v-else
              v-for="(s, idx) in siswaList"
              :key="s.id"
              class="transition-colors hover:bg-muted/40"
            >
              <TableCell class="text-center font-mono text-xs text-muted-foreground">{{ idx + 1 }}</TableCell>
              <TableCell class="font-mono text-xs font-medium text-foreground">{{ s.nisn }}</TableCell>
              
              <TableCell>
                <div class="flex items-center gap-2.5">
                  <Avatar class="size-7">
                    <AvatarFallback class="text-[10px] bg-primary/10 text-primary font-semibold">
                      {{ s.nama_lengkap.charAt(0).toUpperCase() }}
                    </AvatarFallback>
                  </Avatar>
                  <span class="font-medium text-foreground">{{ s.nama_lengkap }}</span>
                </div>
              </TableCell>

              <TableCell class="text-center font-medium text-xs">
                <span :class="s.jenis_kelamin === 'L' ? 'text-blue-600 dark:text-blue-400' : 'text-pink-600 dark:text-pink-400'">
                  {{ s.jenis_kelamin }}
                </span>
              </TableCell>

              <TableCell>
                <Badge
                  variant="outline"
                  class="font-medium text-[11px]"
                  :style="s.kelas?.warna_label ? { borderColor: s.kelas.warna_label, color: s.kelas.warna_label } : {}"
                >
                  {{ s.kelas?.nama_kelas || '-' }}
                </Badge>
              </TableCell>

              <TableCell class="text-xs">
                <p class="font-medium text-foreground">{{ s.nama_ortu || '-' }}</p>
                <p class="text-muted-foreground font-mono text-[11px]">{{ s.no_hp_ortu || '-' }}</p>
              </TableCell>

              <TableCell class="text-center">
                <Badge
                  :variant="s.status === 'aktif' ? 'default' : 'secondary'"
                  class="text-[10px] uppercase font-semibold tracking-wider"
                >
                  {{ s.status }}
                </Badge>
              </TableCell>

              <TableCell class="text-center">
                <div class="inline-flex items-center gap-1">
                  <Button
                    variant="ghost"
                    size="icon-sm"
                    class="size-8 text-primary hover:bg-primary/10"
                    title="Cetak Kartu QR"
                    @click="showQrCard(s)"
                  >
                    <QrCode class="size-3.5" />
                  </Button>
                  <Button
                    v-if="canManageSiswa"
                    variant="ghost"
                    size="icon-sm"
                    class="size-8 text-blue-600 dark:text-blue-400 hover:bg-blue-500/10"
                    title="Edit Santri"
                    @click="openEditModal(s)"
                  >
                    <Pencil class="size-3.5" />
                  </Button>
                  <Button
                    v-if="canManageSiswa"
                    variant="ghost"
                    size="icon-sm"
                    class="size-8 text-destructive hover:bg-destructive/10"
                    title="Hapus Santri"
                    @click="deleteStudent(s)"
                  >
                    <Trash2 class="size-3.5" />
                  </Button>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </Card>

    <!-- Dialog Form Santri (Tambah / Edit) -->
    <Dialog :open="showFormModal" @update:open="(val) => { showFormModal = val; }">
      <DialogContent class="sm:max-w-lg max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold">
            {{ isEditing ? 'Edit Data Santri' : 'Tambah Santri Baru' }}
          </DialogTitle>
          <DialogDescription class="text-xs">
            Lengkapi identitas santri, jilid, dan kontak wali santri untuk notifikasi WA.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4 pt-2" @submit.prevent="saveStudent">
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <Label for="formNisn" class="text-xs font-medium">NISN *</Label>
              <Input
                id="formNisn"
                v-model="form.nisn"
                type="text"
                required
                placeholder="Cth: 1001"
                class="h-9 font-mono text-xs"
              />
            </div>
            <div class="space-y-1.5">
              <Label for="formJk" class="text-xs font-medium">Jenis Kelamin *</Label>
              <select
                id="formJk"
                v-model="form.jenis_kelamin"
                required
                class="h-9 w-full rounded-lg border border-input bg-transparent px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              >
                <option value="L">Laki-laki (L)</option>
                <option value="P">Perempuan (P)</option>
              </select>
            </div>
          </div>

          <div class="space-y-1.5">
            <Label for="formNama" class="text-xs font-medium">Nama Lengkap Santri *</Label>
            <Input
              id="formNama"
              v-model="form.nama_lengkap"
              type="text"
              required
              placeholder="Nama lengkap santri..."
              class="h-9 text-xs"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <Label for="formKelas" class="text-xs font-medium">Jilid *</Label>
              <select
                id="formKelas"
                v-model="form.id_kelas"
                required
                class="h-9 w-full rounded-lg border border-input bg-transparent px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              >
                <option value="">Pilih Jilid</option>
                <option v-for="k in kelasList" :key="k.id" :value="k.id">{{ getCleanNamaKelas(k.nama_kelas) }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <Label for="formStatus" class="text-xs font-medium">Status Keaktifan *</Label>
              <select
                id="formStatus"
                v-model="form.status"
                required
                class="h-9 w-full rounded-lg border border-input bg-transparent px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              >
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Nonaktif</option>
                <option value="lulus">Lulus</option>
                <option value="pindah">Pindah</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <Label for="formOrtu" class="text-xs font-medium">Nama Orang Tua / Wali</Label>
              <Input
                id="formOrtu"
                v-model="form.nama_ortu"
                type="text"
                placeholder="Nama ayah/ibu..."
                class="h-9 text-xs"
              />
            </div>
            <div class="space-y-1.5">
              <Label for="formHpOrtu" class="text-xs font-medium">No. WhatsApp Wali</Label>
              <Input
                id="formHpOrtu"
                v-model="form.no_hp_ortu"
                type="text"
                placeholder="Cth: 081234567890"
                class="h-9 font-mono text-xs"
              />
            </div>
          </div>

          <div class="space-y-1.5">
            <Label for="formAlamat" class="text-xs font-medium">Alamat Rumah</Label>
            <Textarea
              id="formAlamat"
              v-model="form.alamat"
              rows="2"
              placeholder="Alamat tempat tinggal santri..."
              class="text-xs"
            />
          </div>

          <DialogFooter class="flex gap-2 sm:justify-end pt-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="text-xs"
              @click="showFormModal = false"
            >
              Batal
            </Button>
            <Button
              type="submit"
              size="sm"
              class="text-xs"
            >
              Simpan Data
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Dialog Cetak Kartu QR Santri (Ukuran KTP) -->
    <Dialog :open="!!selectedCardStudent" @update:open="(val) => { if (!val) selectedCardStudent = null; }">
      <DialogContent class="sm:max-w-md text-center p-4 print:border-none print:shadow-none print:p-0 print:static print:transform-none print:w-auto print:max-w-none">
        <DialogHeader class="print:hidden">
          <div class="flex items-center justify-center gap-1.5 mb-0.5">
            <QrCode class="size-4 text-primary" />
            <DialogTitle class="text-base font-semibold">Kartu Santri (Ukuran KTP)</DialogTitle>
          </div>
          <DialogDescription class="text-xs">
            Desain resmi kartu santri standar ID-1 (KTP) siap dicetak atau diunduh.
          </DialogDescription>
        </DialogHeader>

        <!-- Printable Card Wrapper (Ukuran Standar ID-1 / KTP) -->
        <div class="ktp-print-wrapper py-1 mx-auto flex items-center justify-center">
          <div
            id="printCardArea"
            class="ktp-card-root mx-auto w-[280px] h-[444px] rounded-[22px] overflow-hidden border border-[#D4E0EC] bg-[#F4F7FB] shadow-xl flex flex-col justify-between text-center select-none"
          >
            <!-- Header Kartu: Deep Navy Kompak dengan Radial Glow & Pola Bintang Halus -->
            <div class="relative pt-2.5 pb-2 px-3 bg-[#0C1830] text-center overflow-hidden shrink-0 header-navy-bg flex flex-col items-center justify-center">
              <div class="absolute inset-0 opacity-10 pointer-events-none stars-pattern"></div>
              
              <div class="relative z-10 flex flex-col items-center">
                <p class="text-[8.5px] font-bold text-[#F5B82E] tracking-[0.16em] uppercase leading-none">
                  TAMAN PENDIDIKAN AL-QUR'AN
                </p>
                <h3 class="text-[18px] font-black text-white tracking-wide uppercase mt-1 mb-1 leading-none">
                  NURUL MUN'IM
                </h3>
                <div class="inline-flex items-center justify-center px-4 py-0.5 rounded-full bg-gradient-to-b from-[#FCD34D] via-[#F59E0B] to-[#D97706] shadow-sm shadow-amber-500/25">
                  <span class="text-[9px] font-black tracking-[0.12em] text-[#1E1B18] uppercase">
                    KARTU SANTRI
                  </span>
                </div>
              </div>
            </div>

            <!-- Garis Aksen Oranye Bawah Header -->
            <div class="h-[2.5px] w-full bg-gradient-to-r from-[#B45309] via-[#F59E0B] to-[#D97706] shrink-0"></div>

            <!-- Badan Kartu: Soft Light Blue-White -->
            <div class="flex-1 flex flex-col items-center justify-between pt-3 pb-2.5 px-3 bg-[#F4F7FB]">
              <!-- Wadah QR Code Putih Sudut Membulat -->
              <div class="bg-white rounded-[18px] p-2 border border-[#E2E8F0] shadow-[0_4px_12px_rgba(0,0,0,0.03)] flex items-center justify-center">
                <canvas ref="qrCanvasRef" class="w-[145px] h-[145px] block"></canvas>
              </div>

              <!-- Bagian Nama Santri -->
              <div class="w-full text-center mt-1">
                <span class="block text-[8.5px] font-bold text-[#7E8B9B] tracking-[0.18em] uppercase mb-0.5 leading-none">
                  NAMA
                </span>
                <h4 class="text-[15px] font-black text-[#0B132B] uppercase tracking-normal leading-tight text-center max-w-[245px] mx-auto break-words line-clamp-2">
                  {{ selectedCardStudent?.nama_lengkap }}
                </h4>
              </div>

              <!-- Lencana Kapsul ID / NISN -->
              <div class="inline-flex items-center justify-center gap-1.5 px-3.5 py-0.5 rounded-xl bg-[#EDF3F8] border border-[#D2DDE8]">
                <span class="text-[9.5px] font-bold text-[#64748B] tracking-wider">ID</span>
                <span class="text-[12.5px] font-black text-[#0B132B] tracking-wider">{{ selectedCardStudent?.nisn }}</span>
              </div>

              <!-- Garis Pembatas Putus-putus -->
              <div class="w-[74%] border-t border-dashed border-[#CBD5E1] my-1"></div>

              <!-- Branding Footer -->
              <p class="text-[8px] font-bold text-[#7E8B9B] tracking-[0.14em] uppercase mb-0.5 leading-none">
                TPQ NURUL MUN'IM . SISTEM DIGITAL
              </p>
            </div>
          </div>
        </div>

        <DialogFooter class="flex flex-col sm:flex-row gap-2 pt-3 print:hidden">
          <Button
            type="button"
            variant="outline"
            size="sm"
            class="flex-1 gap-1.5 text-xs h-9"
            @click="downloadCardImage"
          >
            <Download class="size-3.5" />
            <span>Unduh Gambar (PNG)</span>
          </Button>

          <Button
            type="button"
            size="sm"
            class="flex-1 gap-1.5 text-xs h-9 bg-emerald-600 hover:bg-emerald-700 text-white"
            @click="printCard"
          >
            <Printer class="size-3.5" />
            <span>Cetak Kartu (KTP)</span>
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Dialog Import Massal CSV -->
    <Dialog :open="showImportModal" @update:open="(val) => { showImportModal = val; }">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold">Import Massal Santri</DialogTitle>
          <DialogDescription class="text-xs">
            Tempelkan (paste) data santri berformat CSV (dipisahkan koma atau tab):
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 pt-2">
          <div class="p-2.5 rounded-lg bg-muted text-[11px] font-mono text-muted-foreground">
            nisn, nama_lengkap, kelas, jenis_kelamin, no_hp_ortu, nama_ortu
          </div>

          <Textarea
            v-model="importText"
            rows="6"
            placeholder="1001, Ahmad Fauzi, JILID 1, L, 081234567890, Bapak Fauzi&#10;1002, Muhammad Bilal, JILID 1, L, 081234567891, Bapak Bilal"
            class="font-mono text-xs"
          />

          <DialogFooter class="flex gap-2 sm:justify-end">
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="text-xs"
              @click="showImportModal = false"
            >
              Batal
            </Button>
            <Button
              size="sm"
              class="text-xs gap-1.5"
              @click="handleImportSubmit"
            >
              <Upload class="size-3.5" />
              <span>Mulai Import</span>
            </Button>
          </DialogFooter>
        </div>
      </DialogContent>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import QRCode from 'qrcode';
import {
  Upload,
  Plus,
  Search,
  QrCode,
  Pencil,
  Trash2,
  Printer,
  Download,
  LoaderCircle,
} from '@lucide/vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';
import { useAuth } from '@/composables/useAuth';

const { role } = useAuth();
const canManageSiswa = computed(() => ['admin', 'sekretaris'].includes(role.value));

const toast = useToast();

const isLoading = ref(true);
const siswaList = ref([]);
const kelasList = ref([]);
const filterKelas = ref('');
const filterStatus = ref('');
const search = ref('');
let searchDebounceTimer = null;

const showFormModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const selectedCardStudent = ref(null);
const qrCanvasRef = ref(null);
const showImportModal = ref(false);
const importText = ref('');

const form = ref({
  nisn: '',
  nama_lengkap: '',
  jenis_kelamin: 'L',
  id_kelas: '',
  no_hp_ortu: '',
  nama_ortu: '',
  alamat: '',
  status: 'aktif',
});

function getCleanNamaKelas(name) {
  return (name || '').replace(/\s*\([^)]+\)\s*$/, '').trim();
}

const loadKelas = async () => {
  try {
    const cached = sessionStorage.getItem('cached_kelas_list');
    if (cached) {
      kelasList.value = JSON.parse(cached);
    }
    const res = await api.get('/kelas');
    if (res.data.success) {
      kelasList.value = res.data.data;
      sessionStorage.setItem('cached_kelas_list', JSON.stringify(res.data.data));
    }
  } catch (e) {}
};

const loadSiswa = async () => {
  isLoading.value = true;
  try {
    const params = {};
    if (filterKelas.value) params.id_kelas = filterKelas.value;
    if (filterStatus.value) params.status = filterStatus.value;
    if (search.value) params.search = search.value;

    const res = await api.get('/siswa', { params });
    if (res.data.success) {
      siswaList.value = res.data.data;
    }
  } catch (e) {
    toast.error('Gagal memuat data santri.');
  } finally {
    isLoading.value = false;
  }
};

const onSearchInput = () => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    loadSiswa();
  }, 300);
};

const openAddModal = () => {
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    nisn: '',
    nama_lengkap: '',
    jenis_kelamin: 'L',
    id_kelas: kelasList.value[0]?.id || '',
    no_hp_ortu: '',
    nama_ortu: '',
    alamat: '',
    status: 'aktif',
  };
  showFormModal.value = true;
};

const openEditModal = (student) => {
  isEditing.value = true;
  editingId.value = student.id;
  form.value = {
    nisn: student.nisn,
    nama_lengkap: student.nama_lengkap,
    jenis_kelamin: student.jenis_kelamin,
    id_kelas: student.id_kelas,
    no_hp_ortu: student.no_hp_ortu || '',
    nama_ortu: student.nama_ortu || '',
    alamat: student.alamat || '',
    status: student.status,
  };
  showFormModal.value = true;
};

const saveStudent = async () => {
  try {
    if (isEditing.value) {
      await api.put(`/siswa/${editingId.value}`, form.value);
      toast.success('Data santri berhasil diperbarui!');
    } else {
      await api.post('/siswa', form.value);
      toast.success('Santri baru berhasil ditambahkan!');
    }
    showFormModal.value = false;
    loadSiswa();
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan data santri.');
  }
};

const deleteStudent = async (student) => {
  if (confirm(`Yakin ingin menghapus santri "${student.nama_lengkap}"? Akun dan data absensi akan terhapus.`)) {
    try {
      await api.delete(`/siswa/${student.id}`);
      toast.success('Santri berhasil dihapus.');
      loadSiswa();
    } catch (e) {
      toast.error('Gagal menghapus santri.');
    }
  }
};

const showQrCard = async (student) => {
  selectedCardStudent.value = student;
  await nextTick();
  if (qrCanvasRef.value) {
    QRCode.toCanvas(qrCanvasRef.value, String(student.nisn || ''), {
      width: 145,
      margin: 1,
      color: {
        dark: '#0B132B',
        light: '#ffffff',
      },
    });
  }
};

const printCard = () => {
  window.print();
};

const downloadCardImage = () => {
  if (!selectedCardStudent.value) return;

  const student = selectedCardStudent.value;
  // Canvas resolusi tinggi standar KTP ID-1 (54mm × 85.6mm skala 10x = 540 × 856 px)
  const canvas = document.createElement('canvas');
  const width = 540;
  const height = 856;
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  // Helper menggambar rounded rect
  const drawRoundedRect = (x, y, w, h, radius) => {
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + w - radius, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + radius);
    ctx.lineTo(x + w, y + h - radius);
    ctx.quadraticCurveTo(x + w, y + h, x + w - radius, y + h);
    ctx.lineTo(x + radius, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();
  };

  // 1. Klip kartu dengan sudut membulat standar
  ctx.save();
  drawRoundedRect(0, 0, width, height, 44);
  ctx.clip();

  // 2. Warna dasar kartu
  ctx.fillStyle = '#F4F7FB';
  ctx.fillRect(0, 0, width, height);

  // 3. Header Navy Gradient (Tinggi 22.9% sesuai referensi)
  const headerHeight = 196;
  const headerGrad = ctx.createRadialGradient(width / 2, 60, 20, width / 2, 85, 200);
  headerGrad.addColorStop(0, '#1b3560');
  headerGrad.addColorStop(0.55, '#0c1830');
  headerGrad.addColorStop(1, '#070e1c');
  ctx.fillStyle = headerGrad;
  ctx.fillRect(0, 0, width, headerHeight);

  // Pola titik halus (micro-stars) pada header yang lembut
  ctx.fillStyle = 'rgba(255, 255, 255, 0.08)';
  for (let x = 16; x < width; x += 22) {
    for (let y = 14; y < headerHeight - 8; y += 22) {
      ctx.beginPath();
      ctx.arc(x, y, 1.1, 0, Math.PI * 2);
      ctx.fill();
    }
  }

  // 4. Subtitle: TAMAN PENDIDIKAN AL-QUR'AN (posisi kompak di atas)
  ctx.textAlign = 'center';
  ctx.fillStyle = '#F5B82E';
  ctx.font = 'bold 16px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  try { ctx.letterSpacing = '2.5px'; } catch (_) {}
  ctx.fillText("TAMAN PENDIDIKAN AL-QUR'AN", width / 2, 48);

  // 5. Title Utama: NURUL MUN'IM
  ctx.fillStyle = '#FFFFFF';
  ctx.font = '900 36px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  try { ctx.letterSpacing = '1.5px'; } catch (_) {}
  ctx.fillText("NURUL MUN'IM", width / 2, 88);

  // 6. Tombol Lencana Emas: KARTU SANTRI (posisi kompak proporsional)
  const pillW = 210;
  const pillH = 40;
  const pillX = (width - pillW) / 2;
  const pillY = 120;
  ctx.save();
  drawRoundedRect(pillX, pillY, pillW, pillH, 20);
  const pillGrad = ctx.createLinearGradient(pillX, pillY, pillX, pillY + pillH);
  pillGrad.addColorStop(0, '#FCD34D');
  pillGrad.addColorStop(0.5, '#F59E0B');
  pillGrad.addColorStop(1, '#D97706');
  ctx.fillStyle = pillGrad;
  ctx.shadowColor = 'rgba(217, 119, 6, 0.35)';
  ctx.shadowBlur = 10;
  ctx.shadowOffsetY = 3;
  ctx.fill();
  ctx.restore();

  ctx.fillStyle = '#1E1B18';
  ctx.font = '900 17px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  try { ctx.letterSpacing = '2px'; } catch (_) {}
  ctx.fillText('KARTU SANTRI', width / 2, 146);

  // 7. Garis Pembatas Oranye
  const divGrad = ctx.createLinearGradient(0, 0, width, 0);
  divGrad.addColorStop(0, '#B45309');
  divGrad.addColorStop(0.5, '#F59E0B');
  divGrad.addColorStop(1, '#D97706');
  ctx.fillStyle = divGrad;
  ctx.fillRect(0, headerHeight, width, 5);

  // 8. Wadah Putih QR Code
  const qrBoxSize = 310;
  const qrBoxX = (width - qrBoxSize) / 2;
  const qrBoxY = 226;
  ctx.save();
  drawRoundedRect(qrBoxX, qrBoxY, qrBoxSize, qrBoxSize, 38);
  ctx.fillStyle = '#FFFFFF';
  ctx.shadowColor = 'rgba(0, 0, 0, 0.04)';
  ctx.shadowBlur = 14;
  ctx.shadowOffsetY = 4;
  ctx.fill();
  ctx.strokeStyle = '#E2E8F0';
  ctx.lineWidth = 2;
  ctx.stroke();
  ctx.restore();

  // 9. Gambar QR Canvas ke Wadah
  if (qrCanvasRef.value) {
    const qrInnerSize = 270;
    const qrInnerX = (width - qrInnerSize) / 2;
    const qrInnerY = qrBoxY + (qrBoxSize - qrInnerSize) / 2;
    ctx.drawImage(qrCanvasRef.value, qrInnerX, qrInnerY, qrInnerSize, qrInnerSize);
  }

  // 10. Label NAMA
  ctx.fillStyle = '#7E8B9B';
  ctx.font = 'bold 17px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  try { ctx.letterSpacing = '3px'; } catch (_) {}
  ctx.fillText('NAMA', width / 2, 574);

  // 11. Nama Santri
  const nameStr = (student.nama_lengkap || '').toUpperCase();
  ctx.fillStyle = '#0B132B';
  try { ctx.letterSpacing = '0.5px'; } catch (_) {}
  if (nameStr.length > 25) {
    ctx.font = '900 24px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  } else {
    ctx.font = '900 28px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  }
  ctx.fillText(nameStr, width / 2, 612);

  // 12. Lencana Kapsul ID
  const idBadgeW = 250;
  const idBadgeH = 44;
  const idBadgeX = (width - idBadgeW) / 2;
  const idBadgeY = 642;
  ctx.save();
  drawRoundedRect(idBadgeX, idBadgeY, idBadgeW, idBadgeH, 16);
  ctx.fillStyle = '#EDF3F8';
  ctx.fill();
  ctx.strokeStyle = '#D1DCE7';
  ctx.lineWidth = 2;
  ctx.stroke();
  ctx.restore();

  ctx.fillStyle = '#64748B';
  ctx.font = 'bold 18px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  try { ctx.letterSpacing = '1px'; } catch (_) {}
  ctx.fillText('ID', width / 2 - 38, 671);

  ctx.fillStyle = '#0B132B';
  ctx.font = '900 24px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  try { ctx.letterSpacing = '2px'; } catch (_) {}
  ctx.fillText(String(student.nisn || '-'), width / 2 + 18, 672);

  // 13. Garis Putus-putus
  ctx.save();
  ctx.setLineDash([8, 8]);
  ctx.strokeStyle = '#CBD5E1';
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.moveTo(75, 724);
  ctx.lineTo(width - 75, 724);
  ctx.stroke();
  ctx.restore();

  // 14. Footer
  ctx.fillStyle = '#7E8B9B';
  ctx.font = 'bold 15px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  try { ctx.letterSpacing = '2.5px'; } catch (_) {}
  ctx.fillText("TPQ NURUL MUN'IM . SISTEM DIGITAL", width / 2, 768);

  // 15. Garis Tepi Luar Kartu
  ctx.strokeStyle = '#D4E0EC';
  ctx.lineWidth = 3;
  drawRoundedRect(1, 1, width - 2, height - 2, 44);
  ctx.stroke();
  ctx.restore();

  // Unduh otomatis file PNG
  const link = document.createElement('a');
  link.download = `Kartu-Santri-${student.nisn || 'TPQ'}.png`;
  link.href = canvas.toDataURL('image/png');
  link.click();
  toast.success('Kartu santri berhasil diunduh dalam resolusi tinggi.');
};

const handleImportSubmit = async () => {
  const lines = importText.value.trim().split('\n');
  const items = [];

  for (const line of lines) {
    const parts = line.split(/[,\t]/).map(p => p.trim());
    if (parts.length >= 3) {
      items.push({
        nisn: parts[0],
        nama_lengkap: parts[1],
        kelas: parts[2],
        jenis_kelamin: parts[3] || 'L',
        no_hp_ortu: parts[4] || null,
        nama_ortu: parts[5] || null,
      });
    }
  }

  if (items.length === 0) {
    toast.warning('Format data tidak valid atau kosong.');
    return;
  }

  try {
    const res = await api.post('/siswa/import', { items });
    toast.success(res.data.message);
    showImportModal.value = false;
    importText.value = '';
    loadSiswa();
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal mengimpor data santri.');
  }
};

onMounted(() => {
  loadKelas();
  loadSiswa();
});
</script>

<style scoped>
.header-navy-bg {
  background: radial-gradient(circle at 50% 25%, #1b3560 0%, #0c1830 55%, #070e1c 100%);
  background-color: #0c1830;
}

.stars-pattern {
  background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 14px 14px;
}

@media print {
  @page {
    size: auto;
    margin: 5mm;
  }

  /* Wadah cetak standar ID-1 (KTP: 54mm × 85.6mm) */
  .ktp-print-wrapper {
    width: 54mm !important;
    height: 85.6mm !important;
    margin: 0 auto !important;
    padding: 0 !important;
    overflow: hidden !important;
    display: block !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }

  .ktp-card-root {
    width: 280px !important;
    height: 444px !important;
    /* Skala presisi proporsi dari 280px ke 54mm */
    transform: scale(0.7289) !important;
    transform-origin: top left !important;
    box-shadow: none !important;
    border-radius: 4.8mm !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>
