<template>
  <div class="space-y-4">
    <!-- Kontainer Tunggal Terpadu (Judul, Tombol Aksi, Filter, Pencarian, dan Tabel Menyatu) -->
    <Card class="overflow-hidden">
      <!-- Toolbar Atas di Dalam Kontainer Card (Padding Mepet & Rapi) -->
      <div class="px-2 pt-2 pb-2 sm:p-4 border-b border-border bg-card space-y-2.5 sm:space-y-3">
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
          <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
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
                {{ k.nama_kelas }}
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
                    variant="ghost"
                    size="icon-sm"
                    class="size-8 text-blue-600 dark:text-blue-400 hover:bg-blue-500/10"
                    title="Edit Santri"
                    @click="openEditModal(s)"
                  >
                    <Pencil class="size-3.5" />
                  </Button>
                  <Button
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
                <option v-for="k in kelasList" :key="k.id" :value="k.id">{{ k.nama_kelas }}</option>
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

    <!-- Dialog Cetak Kartu QR Santri -->
    <Dialog :open="!!selectedCardStudent" @update:open="(val) => { if (!val) selectedCardStudent = null; }">
      <DialogContent class="sm:max-w-sm text-center print:border-none print:shadow-none print:p-0 print:static print:transform-none print:w-auto">
        <DialogHeader class="print:hidden">
          <DialogTitle class="text-base font-semibold">Kartu Presensi QR</DialogTitle>
          <DialogDescription class="text-xs">
            Kartu pengenal santri siap dicetak untuk presensi harian.
          </DialogDescription>
        </DialogHeader>

        <!-- Printable Card Area -->
        <div id="printCardArea" class="p-6 rounded-2xl border-2 border-dashed border-primary/50 bg-muted/20 my-2 text-center print:border-2 print:border-solid print:border-black print:bg-white print:p-6 print:m-0">
          <p class="text-[10px] font-bold uppercase tracking-widest text-primary print:text-black">KARTU PRESENSI SANTRI</p>
          <h4 class="text-base font-bold text-foreground mt-1 print:text-black">{{ selectedCardStudent?.nama_lengkap }}</h4>
          
          <Badge
            variant="outline"
            class="my-2 text-[10px] font-semibold print:text-black print:border-black"
            :style="selectedCardStudent?.kelas?.warna_label ? { borderColor: selectedCardStudent.kelas.warna_label, color: selectedCardStudent.kelas.warna_label } : {}"
          >
            {{ selectedCardStudent?.kelas?.nama_kelas || '-' }}
          </Badge>

          <!-- QR Canvas -->
          <div class="flex items-center justify-center p-3 my-2 bg-white rounded-xl shadow-xs max-w-[170px] mx-auto border print:border-black">
            <canvas ref="qrCanvasRef"></canvas>
          </div>

          <p class="text-xs font-mono font-bold text-foreground print:text-black">NISN: {{ selectedCardStudent?.nisn }}</p>
        </div>

        <DialogFooter class="flex gap-2 sm:justify-center pt-2 print:hidden">
          <Button
            size="sm"
            class="w-full gap-1.5 text-xs"
            @click="printCard"
          >
            <Printer class="size-3.5" />
            <span>Cetak Kartu QR</span>
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
import { ref, onMounted, nextTick } from 'vue';
import QRCode from 'qrcode';
import {
  Upload,
  Plus,
  Search,
  QrCode,
  Pencil,
  Trash2,
  Printer,
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
    QRCode.toCanvas(qrCanvasRef.value, student.nisn, {
      width: 140,
      margin: 1,
      color: {
        dark: '#000000',
        light: '#ffffff',
      },
    });
  }
};

const printCard = () => {
  window.print();
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
