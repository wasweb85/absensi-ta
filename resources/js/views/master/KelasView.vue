<template>
  <div class="space-y-6">
    <!-- Kontainer Tunggal Terpadu (Judul, Tombol Tambah, Pencarian, dan Tabel Menyatu) -->
    <Card class="overflow-hidden py-0 gap-0">
      <!-- Toolbar Atas di Dalam Kontainer Card -->
      <div class="px-3 py-2.5 sm:px-4 sm:py-3 border-b border-border bg-card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-3">
          <!-- Kiri: Judul & Badge (dan Tombol Tambah di Mobile) -->
          <div class="flex items-center justify-between sm:justify-start gap-2">
            <div class="flex items-center gap-1.5 sm:gap-2">
              <h2 class="text-sm sm:text-base font-semibold tracking-tight text-foreground whitespace-nowrap">
                Data Jilid
              </h2>
              <Badge variant="outline" class="font-normal text-[11px] sm:text-xs text-muted-foreground px-1.5 py-0 whitespace-nowrap">
                {{ filteredKelasList.length }} Jilid
              </Badge>
            </div>

            <!-- Tombol Tambah Jilid Baru (Khusus Mobile di Baris Atas) -->
            <Button
              size="sm"
              class="h-8 px-2.5 gap-1.5 text-xs sm:hidden shrink-0"
              title="Tambah Jilid Baru"
              @click="openAddModal"
            >
              <Plus class="size-3.5" />
              <span>Tambah</span>
            </Button>
          </div>

          <!-- Kanan: Kolom Pencarian (Lebar Proporsional Mengikuti Tulisan) & Tombol Tambah (Desktop) -->
          <div class="flex items-center gap-2 w-full sm:w-auto">
            <!-- Kolom Pencarian Mengikuti Panjang Teks -->
            <div class="relative w-full sm:w-60 md:w-64">
              <Search class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
              <Input
                id="searchKelas"
                v-model="searchQuery"
                type="text"
                placeholder="Cari nama jilid atau wali..."
                class="h-8 sm:h-9 pl-9 text-xs bg-background w-full"
              />
            </div>

            <!-- Tombol Tambah Jilid Baru (Desktop) -->
            <Button
              size="sm"
              class="hidden sm:inline-flex h-8 sm:h-9 px-2.5 sm:px-3 gap-1.5 text-xs shrink-0"
              title="Tambah Jilid Baru"
              @click="openAddModal"
            >
              <Plus class="size-3.5" />
              <span>Tambah Jilid Baru</span>
            </Button>
          </div>
        </div>
      </div>

      <!-- Tabel Terpadu Data Jilid -->
      <div class="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow class="bg-muted/30">
              <TableHead class="w-14 text-center text-[10px] uppercase">No</TableHead>
              <TableHead class="text-[10px] uppercase min-w-[160px]">Tingkatan Jilid</TableHead>
              <TableHead class="text-[10px] uppercase text-center min-w-[110px]">Santri Aktif</TableHead>
              <TableHead class="text-[10px] uppercase min-w-[180px]">Wali Jilid Binaan</TableHead>
              <TableHead class="text-[10px] uppercase min-w-[140px]">No. WhatsApp</TableHead>
              <TableHead class="text-[10px] uppercase text-center w-24 min-w-[90px]">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <!-- Loading State -->
            <TableRow v-if="isLoading">
              <TableCell colspan="6" class="h-36 text-center text-muted-foreground text-xs">
                <div class="flex flex-col items-center justify-center gap-2">
                  <LoaderCircle class="size-6 animate-spin text-primary" />
                  <span>Memuat data jilid...</span>
                </div>
              </TableCell>
            </TableRow>

            <!-- Empty State -->
            <TableRow v-else-if="filteredKelasList.length === 0">
              <TableCell colspan="6" class="h-32 text-center text-muted-foreground text-xs">
                <div class="flex flex-col items-center justify-center gap-1.5 py-4">
                  <Layers class="size-8 text-muted-foreground/40" />
                  <p class="font-medium text-foreground">
                    {{ searchQuery ? 'Tidak ditemukan jilid yang sesuai pencarian.' : 'Belum ada jilid yang didaftarkan.' }}
                  </p>
                  <p class="text-[11px] text-muted-foreground">
                    {{ searchQuery ? 'Coba ubah kata kunci pencarian Anda.' : 'Klik tombol Tambah Jilid Baru untuk membuat tingkatan jilid.' }}
                  </p>
                </div>
              </TableCell>
            </TableRow>

            <!-- Rows Data Jilid -->
            <TableRow
              v-else
              v-for="(k, idx) in filteredKelasList"
              :key="k.id"
              class="transition-colors hover:bg-muted/40"
            >
              <!-- Kolom No / Urutan -->
              <TableCell class="text-center font-mono text-xs text-muted-foreground">
                #{{ k.urutan || idx + 1 }}
              </TableCell>

              <!-- Tingkatan Jilid -->
              <TableCell>
                <div class="flex items-center gap-2.5">
                  <span
                    class="size-3 rounded-full ring-1 ring-border shadow-2xs shrink-0"
                    :style="{ backgroundColor: k.warna_label || '#059669' }"
                    :title="'Warna: ' + (k.warna_label || '#059669')"
                  ></span>
                  <div class="min-w-0">
                    <p class="font-semibold text-foreground text-xs leading-tight truncate">
                      {{ getCleanNamaKelas(k.nama_kelas) }}
                    </p>
                    <p class="text-[10px] text-muted-foreground font-mono">
                      Urutan ke-{{ k.urutan || idx + 1 }}
                    </p>
                  </div>
                </div>
              </TableCell>

              <!-- Jumlah Santri -->
              <TableCell class="text-center whitespace-nowrap">
                <Badge variant="secondary" class="font-normal text-[11px] gap-1 py-0.5">
                  <Users class="size-3 text-primary" />
                  <span>{{ k.siswa_count || 0 }} Santri</span>
                </Badge>
              </TableCell>

              <!-- Wali Jilid -->
              <TableCell>
                <span v-if="k.wali_kelas" class="font-medium text-foreground text-xs leading-tight">
                  {{ k.wali_kelas }}
                </span>
                <span v-else class="text-xs text-muted-foreground italic">
                  Belum diatur
                </span>
              </TableCell>

              <!-- No. WhatsApp -->
              <TableCell class="whitespace-nowrap">
                <a
                  v-if="k.no_hp_wali"
                  :href="'https://wa.me/' + k.no_hp_wali.replace(/\D/g, '')"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 font-mono text-xs text-primary hover:underline"
                  title="Hubungi via WhatsApp"
                >
                  <Phone class="size-3 shrink-0" />
                  <span>{{ k.no_hp_wali }}</span>
                </a>
                <span v-else class="text-muted-foreground font-mono text-xs">
                  —
                </span>
              </TableCell>

              <!-- Tombol Aksi -->
              <TableCell class="text-center whitespace-nowrap">
                <div class="flex items-center justify-center gap-1">
                  <Button
                    variant="ghost"
                    size="icon"
                    class="size-7 text-muted-foreground hover:text-foreground"
                    title="Edit Jilid"
                    @click="openEditModal(k)"
                  >
                    <Pencil class="size-3" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    class="size-7 text-destructive hover:bg-destructive/10"
                    :disabled="k.siswa_count > 0"
                    :title="k.siswa_count > 0 ? 'Jilid masih memiliki santri aktif dan tidak dapat dihapus' : 'Hapus jilid'"
                    @click="deleteKelas(k)"
                  >
                    <Trash2 class="size-3" />
                  </Button>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </Card>

    <!-- Dialog Form (Tambah / Edit Kelas) -->
    <Dialog :open="showModal" @update:open="(val) => { if (!val) closeModal(); }">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold">
            {{ isEditing ? 'Edit Data Jilid' : 'Tambah Jilid Baru' }}
          </DialogTitle>
          <DialogDescription class="text-xs">
            Lengkapi nama tingkatan jilid dan nomor urutan. Wali jilid diatur otomatis melalui menu Data Guru.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4 pt-2" @submit.prevent="saveKelas">
          <div class="space-y-1.5">
            <Label for="namaKelas" class="text-xs font-medium">Nama Jilid *</Label>
            <Input
              id="namaKelas"
              v-model="form.nama_kelas"
              type="text"
              required
              placeholder="Contoh: JILID 1, JILID 2, AL-QUR'AN"
              class="h-9 text-xs"
            />
          </div>

          <div class="space-y-1.5">
            <Label for="urutanKelas" class="text-xs font-medium">Nomor Urutan</Label>
            <Input
              id="urutanKelas"
              v-model.number="form.urutan"
              type="number"
              min="1"
              placeholder="1"
              class="h-9 text-xs"
            />
          </div>

          <!-- Info Wali Jilid (Otomatis dari Data Guru) -->
          <div class="space-y-1.5">
            <Label class="text-xs font-medium">Wali Jilid Binaan</Label>
            <p class="flex min-h-9 items-center rounded-lg border border-dashed bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
              {{ currentWaliLabel || 'Belum ada wali — atur melalui menu Data Guru' }}
            </p>
            <p class="text-[10px] text-muted-foreground leading-normal">
              Wali jilid (putra, putri, atau campur) ditentukan otomatis dari penugasan di menu <b>Data Guru</b>.
            </p>
          </div>

          <div class="space-y-1.5">
            <Label for="noHpWali" class="text-xs font-medium">No. WhatsApp Wali Jilid</Label>
            <Input
              id="noHpWali"
              v-model="form.no_hp_wali"
              type="text"
              placeholder="Contoh: 081234567890"
              class="h-9 font-mono text-xs"
            />
          </div>

          <div class="space-y-1.5">
            <Label class="text-xs font-medium">Warna Label / Badge</Label>
            <div class="flex items-center gap-2">
              <input
                v-model="form.warna_label"
                type="color"
                class="size-8 rounded-lg border border-input cursor-pointer p-0.5 bg-transparent"
              />
              <div class="flex items-center gap-1.5">
                <button
                  v-for="c in presetColors"
                  :key="c"
                  type="button"
                  :style="{ backgroundColor: c }"
                  class="size-5 rounded-full border border-border cursor-pointer shadow-2xs hover:scale-110 transition-transform"
                  @click="form.warna_label = c"
                ></button>
              </div>
            </div>
          </div>

          <DialogFooter class="flex gap-2 sm:justify-end pt-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="text-xs"
              @click="closeModal"
            >
              Batal
            </Button>
            <Button
              type="submit"
              size="sm"
              class="text-xs"
              :disabled="isSaving"
            >
              <LoaderCircle v-if="isSaving" class="size-3.5 animate-spin" />
              <span>{{ isEditing ? 'Simpan Perubahan' : 'Tambah Jilid' }}</span>
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import {
  Plus,
  Layers,
  Users,
  Pencil,
  Trash2,
  LoaderCircle,
  Search,
  Phone,
} from '@lucide/vue';
import { Card } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
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
import api from '@/services/api';
import { useToast } from '@/composables/useToast';

const toast = useToast();

const kelasList = ref([]);
const isLoading = ref(true);
const isSaving = ref(false);
const searchQuery = ref('');

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const presetColors = ['#f44336', '#e91e63', '#f2a93b', '#2196f3', '#4caf50', '#9c27b0', '#009688'];

const form = ref({
  nama_kelas: '',
  no_hp_wali: '',
  warna_label: '#059669',
  urutan: 1,
});
const currentWaliLabel = ref('');

/**
 * Membersihkan nama jilid dari teks nama wali di dalam tanda kurung jika tersimpan lama
 */
function getCleanNamaKelas(name) {
  return (name || '').replace(/\s*\([^)]+\)\s*$/, '').trim();
}

const filteredKelasList = computed(() => {
  if (!searchQuery.value.trim()) return kelasList.value;
  const q = searchQuery.value.toLowerCase().trim();
  return kelasList.value.filter((k) => {
    const cleanName = getCleanNamaKelas(k.nama_kelas).toLowerCase();
    const rawName = (k.nama_kelas || '').toLowerCase();
    const wali = (k.wali_kelas || '').toLowerCase();
    const hp = (k.no_hp_wali || '').toLowerCase();
    const urutanStr = String(k.urutan || '');

    return (
      cleanName.includes(q) ||
      rawName.includes(q) ||
      wali.includes(q) ||
      hp.includes(q) ||
      urutanStr.includes(q)
    );
  });
});

async function loadKelas() {
  isLoading.value = true;
  try {
    const res = await api.get('/kelas');
    kelasList.value = res.data.data || [];
    sessionStorage.setItem('cached_kelas_list', JSON.stringify(kelasList.value));
  } catch {
    toast.error('Gagal memuat data jilid.');
  } finally {
    isLoading.value = false;
  }
}

function openAddModal() {
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    nama_kelas: '',
    no_hp_wali: '',
    warna_label: '#059669',
    urutan: kelasList.value.length + 1,
  };
  currentWaliLabel.value = '';
  showModal.value = true;
}

function openEditModal(k) {
  isEditing.value = true;
  editingId.value = k.id;
  form.value = {
    nama_kelas: getCleanNamaKelas(k.nama_kelas),
    no_hp_wali: k.no_hp_wali || '',
    warna_label: k.warna_label || '#059669',
    urutan: k.urutan || 1,
  };
  currentWaliLabel.value = k.wali_kelas || '';
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  isEditing.value = false;
  editingId.value = null;
}

async function saveKelas() {
  isSaving.value = true;
  try {
    const payload = {
      ...form.value,
      nama_kelas: getCleanNamaKelas(form.value.nama_kelas),
    };

    if (isEditing.value) {
      await api.put(`/kelas/${editingId.value}`, payload);
      toast.success('Data jilid berhasil diperbarui.');
    } else {
      await api.post('/kelas', payload);
      toast.success('Jilid baru berhasil ditambahkan.');
    }
    closeModal();
    await loadKelas();
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal menyimpan data jilid.';
    toast.error(msg);
  } finally {
    isSaving.value = false;
  }
}

async function deleteKelas(k) {
  if (k.siswa_count > 0) {
    toast.warning('Jilid ini masih memiliki santri aktif dan tidak dapat dihapus.');
    return;
  }

  const cleanName = getCleanNamaKelas(k.nama_kelas);
  if (!confirm(`Apakah Anda yakin ingin menghapus jilid "${cleanName}"?`)) return;

  try {
    const res = await api.delete(`/kelas/${k.id}`);
    toast.success(res.data.message || 'Jilid berhasil dihapus.');
    await loadKelas();
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal menghapus jilid.';
    toast.error(msg);
  }
}

onMounted(() => {
  // Ambil cache awal jika ada untuk tampilan cepat
  const cached = sessionStorage.getItem('cached_kelas_list');
  if (cached) {
    try {
      kelasList.value = JSON.parse(cached);
      isLoading.value = false;
    } catch {
      // Abaikan jika format cache tidak valid
    }
  }
  loadKelas();
});
</script>
