<template>
  <div class="space-y-6 animate-fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>👥</span>
          <span>Manajemen Guru & Pengurus TPQ</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Kelola akun pengajar, penetapan jabatan struktural (Kepala TPQ, Sekretaris, Bendahara), dan penugasan wali jilid
        </p>
      </div>

      <button
        @click="openAddModal"
        class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer"
      >
        <span>➕</span>
        <span>Tambah Guru / Pengurus</span>
      </button>
    </div>

    <!-- Filters Row -->
    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm text-xs">
      <div class="sm:col-span-8">
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Cari Nama / Username</label>
        <input
          v-model="searchQuery"
          @input="loadUsers"
          type="text"
          placeholder="Ketik nama ustadz/ustadzah atau username..."
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        />
      </div>

      <div class="sm:col-span-4">
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Filter Jabatan / Role</label>
        <select
          v-model="filterRole"
          @change="loadUsers"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        >
          <option value="">Semua Jabatan</option>
          <option value="kepala">👑 Kepala TPQ</option>
          <option value="sekretaris">📝 Sekretaris</option>
          <option value="bendahara">💰 Bendahara</option>
          <option value="guru">📖 Pengajar / Guru</option>
          <option value="admin">⚙️ Administrator</option>
        </select>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="p-12 text-center text-zinc-400">
      <p class="text-3xl mb-2 animate-spin">⏳</p>
      <p class="text-sm font-medium">Memuat data guru dan pengurus...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="usersList.length === 0" class="p-12 text-center text-zinc-400 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800">
      <p class="text-4xl mb-2">👥</p>
      <p class="text-sm font-semibold">Tidak ditemukan data guru atau pengurus sesuai filter.</p>
    </div>

    <!-- User Grid Cards -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="u in usersList"
        :key="u.id"
        class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm hover:shadow-md transition flex flex-col justify-between space-y-4"
      >
        <div>
          <!-- Top Row: Role Badge & Kelas Badge -->
          <div class="flex items-center justify-between gap-2 mb-3">
            <span
              class="px-2.5 py-1 rounded-lg text-[10px] font-bold tracking-wider"
              :class="getRoleBadgeClass(u.role)"
            >
              {{ getRoleLabel(u.role) }}
            </span>

            <span
              v-if="u.kelas"
              class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60"
            >
              Wali {{ u.kelas.nama_kelas }}
            </span>
            <span
              v-else
              class="text-[10px] text-zinc-400 font-medium"
            >
              Tidak Mengajar
            </span>
          </div>

          <!-- Profile info -->
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 font-extrabold flex items-center justify-center text-base border border-emerald-200 dark:border-emerald-800/80">
              {{ u.name.charAt(0) }}
            </div>
            <div class="overflow-hidden">
              <h3 class="text-sm font-bold text-zinc-900 dark:text-white truncate">
                {{ u.name }}
              </h3>
              <p class="text-[11px] font-mono text-zinc-400 truncate">
                @{{ u.username }}
              </p>
            </div>
          </div>

          <!-- Email info -->
          <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800 text-xs space-y-1 text-zinc-500">
            <div class="flex items-center justify-between">
              <span class="text-zinc-400 text-[11px]">Email:</span>
              <span class="font-medium text-zinc-700 dark:text-zinc-300 text-[11px] truncate max-w-[180px]">
                {{ u.email || '—' }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-zinc-400 text-[11px]">Status Akun:</span>
              <span class="inline-flex items-center text-[10px] font-bold text-emerald-600">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1 animate-pulse"></span> Aktif
              </span>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-end gap-2">
          <button
            @click="openEditModal(u)"
            class="px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-xs font-semibold text-zinc-700 dark:text-zinc-300 transition flex items-center gap-1 cursor-pointer"
          >
            ✏️ Edit Peran
          </button>
          <button
            @click="deleteUser(u)"
            :disabled="currentUser?.id === u.id"
            :title="currentUser?.id === u.id ? 'Tidak dapat menghapus akun Anda sendiri' : 'Hapus akun'"
            class="px-3 py-1.5 rounded-lg border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-600 dark:text-rose-400 text-xs font-semibold transition flex items-center gap-1 cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
          >
            🗑️ Hapus
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Form (Tambah / Edit Guru & Pengurus) -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fadeIn"
    >
      <div class="w-full max-w-lg p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
          <h3 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
            <span>{{ isEditing ? '✏️' : '➕' }}</span>
            <span>{{ isEditing ? 'Edit Akun & Peran Pengurus' : 'Tambah Guru / Pengurus Baru' }}</span>
          </h3>
          <button @click="closeModal" class="text-zinc-400 hover:text-zinc-600 cursor-pointer text-lg font-bold">
            &times;
          </button>
        </div>

        <form @submit.prevent="saveUser" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Nama Lengkap & Gelar *</label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="Contoh: Ustadzah Fatimah, S.Pd.I"
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Username Login *</label>
              <input
                v-model="form.username"
                type="text"
                required
                placeholder="fatimah"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-mono text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              />
            </div>

            <div>
              <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Email (Opsional)</label>
              <input
                v-model="form.email"
                type="email"
                placeholder="fatimah@sekolah.id"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              />
            </div>
          </div>

          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">
              Password {{ isEditing ? '(Kosongkan jika tidak diubah)' : '*' }}
            </label>
            <input
              v-model="form.password"
              type="password"
              :required="!isEditing"
              placeholder="Minimal 6 karakter"
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Jabatan / Peran (Role) *</label>
              <select
                v-model="form.role"
                @change="handleRoleChange"
                required
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-semibold text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              >
                <option value="guru">📖 Pengajar / Guru</option>
                <option value="bendahara">💰 Bendahara (Kasir & SPP)</option>
                <option value="sekretaris">📝 Sekretaris (Administrasi)</option>
                <option value="kepala">👑 Kepala TPQ (Supervisi)</option>
                <option value="admin">⚙️ Administrator (IT)</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">
                Wali Jilid / Kelas Binaan
              </label>
              <select
                v-model="form.id_kelas"
                :disabled="isRoleNonTeaching"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:opacity-40"
              >
                <option :value="null">-- Tidak Mengajar --</option>
                <option v-for="k in kelasList" :key="k.id" :value="k.id">
                  {{ k.nama_kelas }}
                </option>
              </select>
            </div>
          </div>

          <div v-if="isRoleNonTeaching" class="p-2.5 rounded-xl bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-900/60 text-[11px] text-purple-700 dark:text-purple-300">
            ℹ️ Jabatan <b>{{ getRoleLabel(form.role) }}</b> tidak memegang kelas mengajar langsung.
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 text-xs font-semibold text-zinc-700 dark:text-zinc-300 cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSaving"
              class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20 flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
            >
              <span v-if="isSaving" class="animate-spin">⏳</span>
              <span>{{ isEditing ? 'Simpan Perubahan' : 'Tambah Akun' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../services/api';
import { useAuth } from '../../composables/useAuth';
import { useToast } from '../../composables/useToast';

const toast = useToast();
const { user: currentUser } = useAuth();

const usersList = ref([]);
const kelasList = ref([]);
const isLoading = ref(false);
const isSaving = ref(false);

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
});

const isRoleNonTeaching = computed(() => {
  return ['kepala', 'admin'].includes(form.value.role);
});

function handleRoleChange() {
  if (isRoleNonTeaching.value) {
    form.value.id_kelas = null;
  }
}

function getRoleLabel(r) {
  switch (r) {
    case 'kepala': return '👑 Kepala TPQ';
    case 'sekretaris': return '📝 Sekretaris';
    case 'bendahara': return '💰 Bendahara';
    case 'guru': return '📖 Pengajar / Guru';
    case 'admin': return '⚙️ Administrator';
    default: return r;
  }
}

function getRoleBadgeClass(r) {
  switch (r) {
    case 'kepala': return 'bg-purple-100 text-purple-800 dark:bg-purple-950/80 dark:text-purple-300';
    case 'sekretaris': return 'bg-blue-100 text-blue-800 dark:bg-blue-950/80 dark:text-blue-300';
    case 'bendahara': return 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300';
    case 'guru': return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300';
    case 'admin': return 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200';
    default: return 'bg-zinc-100 text-zinc-700';
  }
}

async function loadKelas() {
  try {
    const res = await api.get('/kelas');
    kelasList.value = res.data.data || [];
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
    usersList.value = res.data.data || [];
  } catch (err) {
    toast.error('Gagal memuat daftar guru dan pengurus.');
  } finally {
    isLoading.value = false;
  }
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
  };
  showModal.value = true;
}

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
  };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  isEditing.value = false;
  editingId.value = null;
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
