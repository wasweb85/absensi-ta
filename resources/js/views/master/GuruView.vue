<template>
  <div class="space-y-6">
    <!-- Kontainer Tunggal Terpadu (Judul, Tombol Tambah, Pencarian, dan Tabel Menyatu) -->
    <Card class="overflow-hidden py-0 gap-0">
      <!-- Toolbar Atas di Dalam Kontainer Card -->
      <div class="px-3 pt-1.5 pb-2.5 sm:px-4 sm:pt-3 sm:pb-3.5 border-b border-border bg-card space-y-2 sm:space-y-3">
        <!-- Baris 1: Judul & Badge [Kiri] sejajar dengan Tombol Tambah Guru [Kanan] -->
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-1.5 sm:gap-2">
            <h2 class="text-sm sm:text-lg font-semibold tracking-tight text-foreground">
              Data Guru
            </h2>
            <Badge variant="outline" class="font-normal text-[11px] sm:text-xs text-muted-foreground px-1.5 py-0">
              {{ usersList.length }} Akun
            </Badge>
          </div>

          <!-- Tombol Aksi Tambah Guru (Di Mobile Responsif) -->
          <div v-if="canManageGuru" class="flex items-center gap-1.5 sm:gap-2 shrink-0">
            <Button
              size="sm"
              class="h-8 sm:h-9 px-2.5 sm:px-3 gap-1.5 text-xs"
              title="Tambah Guru / Pengurus"
              @click="openAddModal"
            >
              <Plus class="size-3.5" />
              <span class="hidden sm:inline">Tambah Guru / Pengurus</span>
              <span class="sm:hidden">Tambah</span>
            </Button>
          </div>
        </div>

        <!-- Baris 2: Kolom Pencarian (Penuh & Bersih, tanpa filter jabatan) -->
        <div class="relative w-full">
          <Search class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
          <Input
            id="searchUser"
            v-model="searchQuery"
            type="text"
            placeholder="Cari nama guru, username, atau email..."
            class="h-9 pl-9 text-xs bg-background w-full"
            @input="onSearchInput"
          />
        </div>
      </div>

      <!-- Tabel Terpadu Data Guru -->
      <div class="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow class="bg-muted/30">
              <TableHead class="w-12 text-center text-[10px] uppercase">No</TableHead>
              <TableHead class="text-[10px] uppercase min-w-[150px]">Guru / Pengurus</TableHead>
              <TableHead class="text-[10px] uppercase text-center min-w-[110px]">Jabatan</TableHead>
              <TableHead class="text-[10px] uppercase min-w-[130px]">Penugasan Jilid</TableHead>
              <TableHead class="text-[10px] uppercase min-w-[140px]">Email</TableHead>
              <TableHead class="text-[10px] uppercase text-center min-w-[80px]">Status</TableHead>
              <TableHead v-if="canManageGuru" class="text-[10px] uppercase text-center w-20 min-w-[80px]">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="isLoading">
              <TableCell :colspan="canManageGuru ? 7 : 6" class="h-36 text-center text-muted-foreground text-xs">
                <div class="flex flex-col items-center justify-center gap-2">
                  <LoaderCircle class="size-6 animate-spin text-primary" />
                  <span>Memuat data guru dan pengurus...</span>
                </div>
              </TableCell>
            </TableRow>

            <TableRow v-else-if="usersList.length === 0">
              <TableCell :colspan="canManageGuru ? 7 : 6" class="h-32 text-center text-muted-foreground text-xs">
                Tidak ditemukan data guru atau pengurus.
              </TableCell>
            </TableRow>

            <TableRow
              v-else
              v-for="(u, idx) in usersList"
              :key="u.id"
              class="transition-colors hover:bg-muted/40"
            >
              <TableCell class="text-center font-mono text-xs text-muted-foreground">
                {{ idx + 1 }}
              </TableCell>

              <!-- Nama & Username Guru -->
              <TableCell>
                <div class="flex items-center gap-2.5">
                  <Avatar class="size-7 shrink-0">
                    <AvatarFallback class="text-[10px] bg-primary/10 text-primary font-semibold">
                      {{ u.name.charAt(0).toUpperCase() }}
                    </AvatarFallback>
                  </Avatar>
                  <div class="min-w-0">
                    <p class="font-medium text-foreground text-xs leading-tight truncate">
                      {{ u.name }}
                    </p>
                    <p class="text-[10.5px] font-mono text-muted-foreground truncate">
                      @{{ u.username }}
                    </p>
                  </div>
                </div>
              </TableCell>

              <!-- Jabatan / Role -->
              <TableCell class="text-center whitespace-nowrap">
                <Badge
                  :variant="getRoleBadgeVariant(u.role)"
                  class="text-[10px] uppercase font-semibold tracking-wider"
                >
                  {{ getRoleLabel(u.role) }}
                </Badge>
              </TableCell>

              <!-- Jilid Binaan -->
              <TableCell class="whitespace-nowrap">
                <template v-if="u.nama_kelas || u.kelas?.nama_kelas">
                  <Badge
                    v-if="u.gender_binaan === 'L'"
                    variant="outline"
                    class="text-[10.5px] font-medium border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-300"
                  >
                    {{ u.nama_kelas || u.kelas?.nama_kelas }} (Putra)
                  </Badge>
                  <Badge
                    v-else-if="u.gender_binaan === 'P'"
                    variant="outline"
                    class="text-[10.5px] font-medium border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300"
                  >
                    {{ u.nama_kelas || u.kelas?.nama_kelas }} (Putri)
                  </Badge>
                  <Badge
                    v-else
                    variant="outline"
                    class="text-[10.5px] font-medium"
                  >
                    {{ u.nama_kelas || u.kelas?.nama_kelas }}
                  </Badge>
                </template>
                <span v-else class="text-xs text-muted-foreground">—</span>
              </TableCell>

              <!-- Email -->
              <TableCell class="text-xs text-muted-foreground whitespace-nowrap">
                {{ u.email || '—' }}
              </TableCell>

              <!-- Status -->
              <TableCell class="text-center whitespace-nowrap">
                <span class="inline-flex items-center text-[10px] font-medium text-emerald-600 dark:text-emerald-400 gap-1">
                  <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  Aktif
                </span>
              </TableCell>

              <!-- Aksi -->
              <TableCell v-if="canManageGuru" class="text-center whitespace-nowrap">
                <div class="inline-flex items-center gap-1">
                  <Button
                    variant="ghost"
                    size="icon-sm"
                    class="size-8 text-blue-600 dark:text-blue-400 hover:bg-blue-500/10"
                    title="Edit Peran"
                    @click="openEditModal(u)"
                  >
                    <Pencil class="size-3.5" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon-sm"
                    class="size-8 text-destructive hover:bg-destructive/10"
                    :disabled="currentUser?.id === u.id"
                    :title="currentUser?.id === u.id ? 'Tidak dapat menghapus akun Anda sendiri' : 'Hapus akun'"
                    @click="deleteUser(u)"
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

    <!-- Dialog Form (Tambah / Edit Guru & Pengurus) -->
    <Dialog :open="showModal" @update:open="(val) => { if (!val) closeModal(); }">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold">
            {{ isEditing ? 'Edit Akun & Peran Pengurus' : 'Tambah Guru / Pengurus Baru' }}
          </DialogTitle>
          <DialogDescription class="text-xs">
            Lengkapi data akun login, penetapan peran (role), dan jilid binaan.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4 pt-2" @submit.prevent="saveUser">
          <div class="space-y-1.5">
            <Label for="userName" class="text-xs font-medium">Nama Lengkap &amp; Gelar *</Label>
            <Input
              id="userName"
              v-model="form.name"
              type="text"
              required
              placeholder="Contoh: Ustadzah Fatimah, S.Pd.I"
              class="h-9 text-xs"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <Label for="userUsername" class="text-xs font-medium">Username Login *</Label>
              <Input
                id="userUsername"
                v-model="form.username"
                type="text"
                required
                placeholder="fatimah"
                class="h-9 font-mono text-xs"
              />
            </div>

            <div class="space-y-1.5">
              <Label for="userEmail" class="text-xs font-medium">Email (Opsional)</Label>
              <Input
                id="userEmail"
                v-model="form.email"
                type="email"
                placeholder="fatimah@sekolah.id"
                class="h-9 text-xs"
              />
            </div>
          </div>

          <div class="space-y-1.5">
            <Label for="userPassword" class="text-xs font-medium">
              Password {{ isEditing ? '(Kosongkan jika tidak diubah)' : '*' }}
            </Label>
            <Input
              id="userPassword"
              v-model="form.password"
              type="password"
              :required="!isEditing"
              placeholder="Minimal 6 karakter"
              class="h-9 text-xs"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <Label for="userRole" class="text-xs font-medium">Jabatan / Peran (Role) *</Label>
              <select
                id="userRole"
                v-model="form.role"
                required
                class="h-9 w-full rounded-lg border border-input bg-transparent px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                @change="handleRoleChange"
              >
                <option value="guru">Pengajar / Guru</option>
                <option value="bendahara">Bendahara</option>
                <option value="sekretaris">Sekretaris</option>
                <option value="kepala">Kepala TPQ</option>
                <option value="admin">Administrator</option>
              </select>
            </div>

            <div class="space-y-1.5">
              <Label for="userKelas" class="text-xs font-medium">Wali Jilid Binaan</Label>
              <select
                id="userKelas"
                v-model="form.id_kelas"
                :disabled="isRoleNonTeaching"
                class="h-9 w-full rounded-lg border border-input bg-transparent px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-40"
              >
                <option :value="null">-- Tidak Mengajar --</option>
                <option v-for="k in kelasList" :key="k.id" :value="k.id">
                  {{ k.nama_kelas }}
                </option>
              </select>
            </div>
          </div>

          <div v-if="form.id_kelas && !isRoleNonTeaching" class="space-y-1.5">
            <Label for="userGenderBinaan" class="text-xs font-medium">Kelompok Binaan</Label>
            <select
              id="userGenderBinaan"
              v-model="form.gender_binaan"
              class="h-9 w-full rounded-lg border border-input bg-transparent px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              <option value="all">Semua Santri / Campur (Jilid 1 & 2)</option>
              <option value="L">Khusus Santri Putra (Ustadz)</option>
              <option value="P">Khusus Santri Putri (Ustadzah)</option>
            </select>
          </div>

          <div v-if="isRoleNonTeaching" class="p-2.5 rounded-lg border border-indigo-500/20 bg-indigo-500/10 text-[11px] text-indigo-700 dark:text-indigo-400">
            Jabatan <strong>{{ getRoleLabel(form.role) }}</strong> tidak memegang jilid mengajar langsung.
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
              <span>{{ isEditing ? 'Simpan Perubahan' : 'Tambah Akun' }}</span>
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
  Search,
  Users,
  Pencil,
  Trash2,
  LoaderCircle,
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
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import api from '@/services/api';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';

const toast = useToast();
const { user: currentUser, canManageGuru } = useAuth();

const usersList = ref([]);
const kelasList = ref([]);
const isLoading = ref(true);
const isSaving = ref(false);
let searchDebounceTimer = null;

const totalPengajarCount = computed(() => {
  return usersList.value.filter(u => u.role === 'guru' || u.id_kelas).length;
});

const searchQuery = ref('');

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = ref({
  name: '',
  username: '',
  email: '',
  password: '',
  role: 'guru',
  id_kelas: null,
  gender_binaan: 'all',
});

const isRoleNonTeaching = computed(() => {
  return ['kepala', 'admin'].includes(form.value.role);
});

function handleRoleChange() {
  if (isRoleNonTeaching.value) {
    form.value.id_kelas = null;
    form.value.gender_binaan = 'all';
  }
}

function getRoleLabel(r) {
  switch (r) {
    case 'kepala': return 'Kepala TPQ';
    case 'sekretaris': return 'Sekretaris';
    case 'bendahara': return 'Bendahara';
    case 'guru': return 'Pengajar / Guru';
    case 'admin': return 'Administrator';
    default: return r;
  }
}

function getRoleBadgeVariant(r) {
  switch (r) {
    case 'admin': return 'default';
    case 'kepala': return 'secondary';
    case 'guru': return 'outline';
    default: return 'outline';
  }
}

async function loadKelas() {
  try {
    const cached = sessionStorage.getItem('cached_kelas_list');
    if (cached) {
      kelasList.value = JSON.parse(cached);
    }
    const res = await api.get('/kelas');
    kelasList.value = res.data.data || [];
    sessionStorage.setItem('cached_kelas_list', JSON.stringify(kelasList.value));
  } catch (err) {
    console.error(err);
  }
}

async function loadUsers() {
  isLoading.value = true;
  try {
    const res = await api.get('/users', {
      params: {
        search: searchQuery.value || undefined,
      }
    });
    usersList.value = (res.data.data || []).map(u => ({
      ...u,
      nama_kelas: u.nama_kelas || u.kelas?.nama_kelas || null,
    }));
  } catch (err) {
    toast.error('Gagal memuat daftar guru dan pengurus.');
  } finally {
    isLoading.value = false;
  }
}

function onSearchInput() {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    loadUsers();
  }, 300);
}

function openAddModal() {
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    name: '',
    username: '',
    email: '',
    password: '',
    role: 'guru',
    id_kelas: null,
    gender_binaan: 'all',
  };
  showModal.value = true;
}

const openCreateModal = openAddModal;

function openEditModal(u) {
  isEditing.value = true;
  editingId.value = u.id;
  form.value = {
    name: u.name,
    username: u.username,
    email: u.email || '',
    password: '',
    role: u.role,
    id_kelas: u.id_kelas || null,
    gender_binaan: u.gender_binaan || 'all',
  };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    name: '',
    username: '',
    email: '',
    password: '',
    role: 'guru',
    id_kelas: null,
    gender_binaan: 'all',
  };
}

async function saveUser() {
  isSaving.value = true;
  try {
    if (isEditing.value) {
      await api.put(`/users/${editingId.value}`, form.value);
      toast.success('Data guru/pengurus berhasil diperbarui.');
    } else {
      await api.post('/users', form.value);
      toast.success('Akun guru/pengurus baru berhasil ditambahkan.');
    }
    closeModal();
    await loadUsers();
  } catch (err) {
    const msg = err.response?.data?.message || err.response?.data?.errors?.username?.[0] || 'Gagal menyimpan data akun.';
    toast.error(msg);
  } finally {
    isSaving.value = false;
  }
}

async function deleteUser(u) {
  if (!confirm(`Apakah Anda yakin ingin menghapus akun "${u.name}" (@${u.username})?`)) return;

  try {
    const res = await api.delete(`/users/${u.id}`);
    toast.success(res.data.message || 'Akun berhasil dihapus.');
    await loadUsers();
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal menghapus akun.';
    toast.error(msg);
  }
}

onMounted(() => {
  loadKelas();
  loadUsers();
});
</script>
