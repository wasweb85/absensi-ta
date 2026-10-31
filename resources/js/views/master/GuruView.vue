<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between pb-2 border-b">
      <div class="flex items-center gap-2">
        <h2 class="text-lg font-semibold tracking-tight text-foreground">
          Data Guru
        </h2>
        <Badge variant="outline" class="font-normal text-xs text-muted-foreground">
          {{ usersList.length }} Akun
        </Badge>
      </div>

      <!-- Action Button (Khusus Admin) -->
      <Button
        v-if="canManageGuru"
        size="sm"
        class="h-8 gap-1.5 text-xs"
        @click="openAddModal"
      >
        <Plus class="size-3.5" />
        <span>Tambah Guru / Pengurus</span>
      </Button>
    </div>

    <!-- Summary Cards Ustadz/Ustadzah & Pengurus TPQ -->
    <div class="grid grid-cols-3 gap-2 sm:gap-3">
      <Card class="p-2.5 sm:p-3.5 flex items-center justify-between">
        <div class="min-w-0">
          <span class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-muted-foreground block truncate">
            Ustadz / Pengajar
          </span>
          <span class="text-lg sm:text-2xl font-bold tracking-tight text-foreground mt-0.5 block">
            {{ totalPengajarCount }}
          </span>
          <span class="hidden sm:block text-[10px] text-muted-foreground">Guru &amp; wali jilid aktif</span>
        </div>
        <div class="hidden sm:flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
          <Users class="size-5" />
        </div>
      </Card>

      <Card class="p-2.5 sm:p-3.5 flex items-center justify-between">
        <div class="min-w-0">
          <span class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-muted-foreground block truncate">
            Wali Jilid
          </span>
          <span class="text-lg sm:text-2xl font-bold tracking-tight text-foreground mt-0.5 block">
            {{ totalWaliKelasCount }} <span class="text-[10px] sm:text-xs font-normal text-muted-foreground">/ {{ kelasList.length }}</span>
          </span>
          <span class="hidden sm:block text-[10px] text-muted-foreground">Jilid dengan ustadz/ustadzah</span>
        </div>
        <div class="hidden sm:flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
          <Users class="size-5" />
        </div>
      </Card>

      <Card class="p-2.5 sm:p-3.5 flex items-center justify-between">
        <div class="min-w-0">
          <span class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-muted-foreground block truncate">
            Total Staf
          </span>
          <span class="text-lg sm:text-2xl font-bold tracking-tight text-foreground mt-0.5 block">
            {{ usersList.length }}
          </span>
          <span class="hidden sm:block text-[10px] text-muted-foreground">Seluruh akun struktural &amp; guru</span>
        </div>
        <div class="hidden sm:flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400">
          <Users class="size-5" />
        </div>
      </Card>
    </div>

    <!-- Toolbar Pencarian & Filter Jabatan (Clean & Profesional) -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
      <div class="relative flex-1 max-w-md">
        <Search class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
        <Input
          id="searchUser"
          v-model="searchQuery"
          type="text"
          placeholder="Cari nama atau username..."
          class="h-9 pl-9 text-xs bg-card"
          @input="onSearchInput"
        />
      </div>

      <div class="flex items-center gap-2">
        <select
          id="filterRole"
          v-model="filterRole"
          class="h-9 rounded-lg border border-input bg-card px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
          @change="loadUsers"
        >
          <option value="">Semua Jabatan</option>
          <option value="kepala">Jabatan: Kepala TPQ</option>
          <option value="sekretaris">Jabatan: Sekretaris</option>
          <option value="bendahara">Jabatan: Bendahara</option>
          <option value="guru">Jabatan: Pengajar / Guru</option>
          <option value="admin">Jabatan: Administrator</option>
        </select>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="p-12 text-center text-muted-foreground">
      <LoaderCircle class="size-8 mx-auto animate-spin mb-2 text-primary" />
      <p class="text-xs font-medium">Memuat data guru dan pengurus...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="usersList.length === 0" class="p-16 text-center text-muted-foreground border rounded-xl bg-card">
      <Users class="size-10 mx-auto mb-2 text-muted-foreground/40" />
      <p class="text-xs font-semibold text-foreground">Tidak ditemukan data guru atau pengurus.</p>
      <p class="text-[11px] text-muted-foreground mt-0.5">Coba ubah kata kunci pencarian atau filter peran.</p>
    </div>

    <!-- User Grid Cards -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <Card
        v-for="u in usersList"
        :key="u.id"
        class="flex flex-col justify-between transition-all hover:shadow-xs"
      >
        <CardHeader class="pb-3">
          <div class="flex items-center justify-between gap-2 mb-2">
            <Badge
              :variant="getRoleBadgeVariant(u.role)"
              class="text-[10px] uppercase font-semibold tracking-wider"
            >
              {{ getRoleLabel(u.role) }}
            </Badge>

            <template v-if="u.nama_kelas || u.kelas?.nama_kelas">
              <Badge
                v-if="u.gender_binaan === 'L'"
                variant="outline"
                class="text-[10px] font-medium border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-300"
              >
                {{ u.nama_kelas || u.kelas?.nama_kelas }} (Putra)
              </Badge>
              <Badge
                v-else-if="u.gender_binaan === 'P'"
                variant="outline"
                class="text-[10px] font-medium border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300"
              >
                {{ u.nama_kelas || u.kelas?.nama_kelas }} (Putri)
              </Badge>
              <Badge
                v-else
                variant="outline"
                class="text-[10px] font-medium"
              >
                {{ u.nama_kelas || u.kelas?.nama_kelas }}
              </Badge>
            </template>
            <span v-else class="text-[10px] text-muted-foreground">
              -
            </span>
          </div>

          <div class="flex items-center gap-3">
            <Avatar class="size-10">
              <AvatarFallback class="bg-primary/10 text-sm font-semibold text-primary">
                {{ u.name.charAt(0).toUpperCase() }}
              </AvatarFallback>
            </Avatar>
            <div class="min-w-0 flex-1">
              <h3 class="text-sm font-semibold text-foreground truncate">
                {{ u.name }}
              </h3>
              <p class="text-[11px] font-mono text-muted-foreground truncate">
                @{{ u.username }}
              </p>
            </div>
          </div>
        </CardHeader>

        <CardContent class="py-2 text-xs space-y-1.5 border-t">
          <div class="flex items-center justify-between pt-2">
            <span class="text-muted-foreground text-[11px]">Email:</span>
            <span class="font-medium text-foreground text-[11px] truncate max-w-[170px]">
              {{ u.email || '—' }}
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground text-[11px]">Status Akun:</span>
            <span class="inline-flex items-center text-[10px] font-medium text-emerald-600 dark:text-emerald-400 gap-1">
              <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
              Aktif
            </span>
          </div>
        </CardContent>

        <CardFooter v-if="canManageGuru" class="pt-3 border-t flex items-center justify-end gap-1.5">
          <Button
            variant="ghost"
            size="sm"
            class="h-8 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
            @click="openEditModal(u)"
          >
            <Pencil class="size-3.5" />
            <span>Edit Peran</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            class="h-8 gap-1.5 text-xs text-destructive hover:bg-destructive/10"
            :disabled="currentUser?.id === u.id"
            :title="currentUser?.id === u.id ? 'Tidak dapat menghapus akun Anda sendiri' : 'Hapus akun'"
            @click="deleteUser(u)"
          >
            <Trash2 class="size-3.5" />
            <span>Hapus</span>
          </Button>
        </CardFooter>
      </Card>
    </div>

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
import { Card, CardContent, CardHeader, CardFooter } from '@/components/ui/card';
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

const totalWaliKelasCount = computed(() => {
  return usersList.value.filter(u => !!u.id_kelas).length;
});

const searchQuery = ref('');
const filterRole = ref('');

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
        role: filterRole.value || undefined,
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
