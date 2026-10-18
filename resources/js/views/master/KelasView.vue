<template>
  <div class="space-y-6 animate-fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>🏫</span>
          <span>Master Data Jilid & Tarif SPP</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Pengaturan tingkatan jilid/kelas santri, tarif nominal SPP bulanan, dan wali kelas
        </p>
      </div>

      <button
        @click="openAddModal"
        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer"
      >
        <span>➕</span>
        <span>Tambah Jilid Baru</span>
      </button>
    </div>

    <!-- Kelas Card Grid -->
    <div v-if="isLoading" class="p-12 text-center text-zinc-400">
      <p class="text-3xl mb-2 animate-spin">⏳</p>
      <p class="text-sm font-medium">Memuat data jilid & tarif SPP...</p>
    </div>

    <div v-else-if="kelasList.length === 0" class="p-12 text-center text-zinc-400 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800">
      <p class="text-4xl mb-2">🏫</p>
      <p class="text-sm font-semibold">Belum ada jilid / kelas yang didaftarkan.</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="k in kelasList"
        :key="k.id"
        class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm hover:shadow-md transition flex flex-col justify-between space-y-4"
      >
        <div>
          <!-- Top Row: Badge Color & Order -->
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <span
                class="w-3.5 h-3.5 rounded-full shadow-sm"
                :style="{ backgroundColor: k.warna_label || '#059669' }"
              ></span>
              <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">
                Urutan #{{ k.urutan || 0 }}
              </span>
            </div>

            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
              👥 {{ k.siswa_count || 0 }} Santri
            </span>
          </div>

          <!-- Class Title -->
          <h3 class="text-lg font-extrabold text-zinc-900 dark:text-white">
            {{ k.nama_kelas }}
          </h3>

          <!-- Fee -->
          <div class="mt-2 p-3 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-800/40">
            <span class="text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-400 block">Tarif SPP Bulanan</span>
            <span class="text-base font-extrabold text-emerald-700 dark:text-emerald-300">
              Rp {{ formatRupiah(k.nominal_spp) }}
            </span>
          </div>

          <!-- Wali Kelas Info -->
          <div class="mt-3 text-xs space-y-1 text-zinc-600 dark:text-zinc-400">
            <div class="flex items-center justify-between">
              <span class="text-zinc-400">Wali Kelas:</span>
              <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ k.wali_kelas || '-' }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-zinc-400">No. WhatsApp:</span>
              <span class="font-mono font-medium">{{ k.no_hp_wali || '-' }}</span>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-end gap-2">
          <button
            @click="openEditModal(k)"
            class="px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-xs font-semibold text-zinc-700 dark:text-zinc-300 transition flex items-center gap-1 cursor-pointer"
          >
            ✏️ Edit
          </button>
          <button
            @click="deleteKelas(k)"
            :disabled="k.siswa_count > 0"
            :title="k.siswa_count > 0 ? 'Kelas masih memiliki santri aktif' : 'Hapus kelas'"
            class="px-3 py-1.5 rounded-lg border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-600 dark:text-rose-400 text-xs font-semibold transition flex items-center gap-1 cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
          >
            🗑️ Hapus
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Form (Tambah / Edit Kelas) -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fadeIn"
    >
      <div class="w-full max-w-md p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
          <h3 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
            <span>{{ isEditing ? '✏️' : '➕' }}</span>
            <span>{{ isEditing ? 'Edit Data Jilid / Kelas' : 'Tambah Jilid / Kelas Baru' }}</span>
          </h3>
          <button @click="closeModal" class="text-zinc-400 hover:text-zinc-600 cursor-pointer text-lg font-bold">
            &times;
          </button>
        </div>

        <form @submit.prevent="saveKelas" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Nama Jilid / Kelas *</label>
            <input
              v-model="form.nama_kelas"
              type="text"
              required
              placeholder="Contoh: JILID 1, JILID 2, AL-QUR'AN"
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Tarif SPP Bulanan (Rp) *</label>
              <input
                v-model.number="form.nominal_spp"
                type="number"
                min="0"
                step="1000"
                required
                placeholder="50000"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-bold text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              />
            </div>

            <div>
              <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Nomor Urutan</label>
              <input
                v-model.number="form.urutan"
                type="number"
                min="1"
                placeholder="1"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              />
            </div>
          </div>

          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">Nama Wali Kelas / Guru</label>
            <input
              v-model="form.wali_kelas"
              type="text"
              placeholder="Contoh: Ustadzah Fatimah"
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">No. WhatsApp Wali Kelas</label>
            <input
              v-model="form.no_hp_wali"
              type="text"
              placeholder="Contoh: 081234567890"
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">Warna Label / Badge</label>
            <div class="flex items-center gap-2">
              <input
                v-model="form.warna_label"
                type="color"
                class="w-9 h-9 rounded-xl border border-zinc-300 dark:border-zinc-700 cursor-pointer p-0.5 bg-transparent"
              />
              <!-- Preset Colors -->
              <div class="flex items-center gap-1.5">
                <button
                  type="button"
                  v-for="c in presetColors"
                  :key="c"
                  @click="form.warna_label = c"
                  :style="{ backgroundColor: c }"
                  class="w-6 h-6 rounded-full border border-black/10 cursor-pointer shadow-sm hover:scale-110 transition"
                ></button>
              </div>
            </div>
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
              <span>{{ isEditing ? 'Simpan Perubahan' : 'Tambah Jilid' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';

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
  nominal_spp: 50000,
  wali_kelas: '',
  no_hp_wali: '',
  warna_label: '#059669',
  urutan: 1,
});

function formatRupiah(val) {
  if (!val) return '0';
  return Number(val).toLocaleString('id-ID');
}

async function loadKelas() {
  isLoading.value = true;
  try {
    const res = await api.get('/kelas');
    kelasList.value = res.data.data || [];
  } catch (err) {
    toast.error('Gagal mengambil daftar jilid / kelas.');
  } finally {
    isLoading.value = false;
  }
}

function openAddModal() {
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    nama_kelas: '',
    nominal_spp: 50000,
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
    nominal_spp: Number(k.nominal_spp),
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
      toast.success('Data jilid berhasil diperbarui.');
    } else {
      await api.post('/kelas', form.value);
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
  if (!confirm(`Apakah Anda yakin ingin menghapus "${k.nama_kelas}"?`)) return;

  try {
    const res = await api.delete(`/kelas/${k.id}`);
    toast.success(res.data.message || 'Kelas berhasil dihapus.');
    await loadKelas();
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal menghapus kelas.';
    toast.error(msg);
  }
}

onMounted(() => {
  loadKelas();
});
</script>
