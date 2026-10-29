<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between pb-2 border-b">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-lg font-semibold tracking-tight text-foreground">
            Data Jilid
          </h2>
          <Badge variant="outline" class="font-normal text-xs text-muted-foreground">
            {{ kelasList.length }} Jilid
          </Badge>
        </div>
        <p class="text-xs text-muted-foreground mt-0.5">
          Pengaturan tingkatan jilid/kelas santri, urutan jilid, dan wali kelas masing-masing.
        </p>
      </div>

      <!-- Action Button -->
      <Button
        size="sm"
        class="h-8 gap-1.5 text-xs"
        @click="openAddModal"
      >
        <Plus class="size-3.5" />
        <span>Tambah Jilid Baru</span>
      </Button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="p-12 text-center text-muted-foreground">
      <LoaderCircle class="size-8 mx-auto animate-spin mb-2 text-primary" />
      <p class="text-xs font-medium">Memuat data jilid &amp; kelas...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="kelasList.length === 0" class="p-16 text-center text-muted-foreground border rounded-xl bg-card">
      <Layers class="size-10 mx-auto mb-2 text-muted-foreground/40" />
      <p class="text-xs font-semibold text-foreground">Belum ada jilid / kelas yang didaftarkan.</p>
      <p class="text-[11px] text-muted-foreground mt-0.5">Klik tombol Tambah Jilid Baru untuk membuat tingkatan kelas pertama.</p>
    </div>

    <!-- Kelas Card Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <Card
        v-for="k in kelasList"
        :key="k.id"
        class="flex flex-col justify-between transition-all hover:shadow-xs"
      >
        <CardHeader class="pb-3">
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
              <span
                class="size-3 rounded-full ring-1 ring-border shadow-xs shrink-0"
                :style="{ backgroundColor: k.warna_label || '#059669' }"
              ></span>
              <span class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                Urutan #{{ k.urutan || 0 }}
              </span>
            </div>

            <Badge variant="secondary" class="font-normal text-[11px] gap-1">
              <Users class="size-3" />
              <span>{{ k.siswa_count || 0 }} Santri</span>
            </Badge>
          </div>

          <CardTitle class="text-base font-semibold text-foreground">
            {{ k.nama_kelas }}
          </CardTitle>
        </CardHeader>

        <CardContent class="py-2 text-xs space-y-1.5 border-t">
          <div class="flex items-center justify-between pt-2">
            <span class="text-muted-foreground text-[11px]">Wali Kelas:</span>
            <span class="font-medium text-foreground text-[11px] truncate max-w-[170px]">
              {{ k.wali_kelas || '-' }}
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground text-[11px]">No. WhatsApp:</span>
            <span class="font-mono text-muted-foreground text-[11px]">
              {{ k.no_hp_wali || '-' }}
            </span>
          </div>
        </CardContent>

        <CardFooter class="pt-3 border-t flex items-center justify-end gap-1.5">
          <Button
            variant="ghost"
            size="sm"
            class="h-8 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
            @click="openEditModal(k)"
          >
            <Pencil class="size-3.5" />
            <span>Edit</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            class="h-8 gap-1.5 text-xs text-destructive hover:bg-destructive/10"
            :disabled="k.siswa_count > 0"
            :title="k.siswa_count > 0 ? 'Kelas masih memiliki santri aktif' : 'Hapus kelas'"
            @click="deleteKelas(k)"
          >
            <Trash2 class="size-3.5" />
            <span>Hapus</span>
          </Button>
        </CardFooter>
      </Card>
    </div>

    <!-- Dialog Form (Tambah / Edit Kelas) -->
    <Dialog :open="showModal" @update:open="(val) => { if (!val) closeModal(); }">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold">
            {{ isEditing ? 'Edit Data Jilid / Kelas' : 'Tambah Jilid / Kelas Baru' }}
          </DialogTitle>
          <DialogDescription class="text-xs">
            Lengkapi nama tingkatan jilid, nomor urutan, dan identitas wali kelas.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4 pt-2" @submit.prevent="saveKelas">
          <div class="space-y-1.5">
            <Label for="namaKelas" class="text-xs font-medium">Nama Jilid / Kelas *</Label>
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

          <div class="space-y-1.5">
            <Label for="waliKelas" class="text-xs font-medium">Nama Wali Kelas / Guru</Label>
            <Input
              id="waliKelas"
              v-model="form.wali_kelas"
              type="text"
              placeholder="Contoh: Ustadzah Fatimah"
              class="h-9 text-xs"
            />
          </div>

          <div class="space-y-1.5">
            <Label for="noHpWali" class="text-xs font-medium">No. WhatsApp Wali Kelas</Label>
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
import { ref, onMounted } from 'vue';
import {
  Plus,
  Layers,
  Users,
  Pencil,
  Trash2,
  LoaderCircle,
} from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
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
const isLoading = ref(false);
const isSaving = ref(false);

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const presetColors = ['#f44336', '#e91e63', '#f2a93b', '#2196f3', '#4caf50', '#9c27b0', '#009688'];

const form = ref({
  nama_kelas: '',
  wali_kelas: '',
  no_hp_wali: '',
  warna_label: '#059669',
  urutan: 1,
});

async function loadKelas() {
  isLoading.value = true;
  try {
    const res = await api.get('/kelas');
    kelasList.value = res.data.data || [];
  } catch (err) {
    toast.error('Gagal memuat data kelas.');
  } finally {
    isLoading.value = false;
  }
}

function openAddModal() {
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    nama_kelas: '',
    wali_kelas: '',
    no_hp_wali: '',
    warna_label: '#059669',
    urutan: kelasList.value.length + 1,
  };
  showModal.value = true;
}

function openEditModal(k) {
  isEditing.value = true;
  editingId.value = k.id;
  form.value = {
    nama_kelas: k.nama_kelas,
    wali_kelas: k.wali_kelas || '',
    no_hp_wali: k.no_hp_wali || '',
    warna_label: k.warna_label || '#059669',
    urutan: k.urutan || 1,
  };
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
    if (isEditing.value) {
      await api.put(`/kelas/${editingId.value}`, form.value);
      toast.success('Data jilid/kelas berhasil diperbarui.');
    } else {
      await api.post('/kelas', form.value);
      toast.success('Jilid/kelas baru berhasil ditambahkan.');
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
    toast.warning('Jilid/kelas ini masih memiliki santri aktif dan tidak dapat dihapus.');
    return;
  }

  if (!confirm(`Apakah Anda yakin ingin menghapus jilid "${k.nama_kelas}"?`)) return;

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
  loadKelas();
});
</script>
