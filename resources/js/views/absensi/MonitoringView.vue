<template>
  <div class="space-y-6 animate-fadeIn">
    
    <!-- Top Filter & Actions Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>⏱</span>
          <span>Monitoring Kehadiran Realtime</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Pantau status kehadiran santri hari ini secara langsung per kelas/jilid
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2.5">
        <button
          @click="loadMonitoring"
          :disabled="loading"
          class="px-3.5 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700/60 text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer"
        >
          <span :class="{'animate-spin': loading}">🔄</span>
          <span>Segarkan</span>
        </button>
        <router-link
          to="/absensi/scanner"
          class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5"
        >
          <span>📷</span>
          <span>Buka Scanner</span>
        </router-link>
      </div>
    </div>

    <!-- Filters Row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm text-xs">
      <!-- Date Picker -->
      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Tanggal Absensi</label>
        <input
          v-model="filterDate"
          type="date"
          @change="loadMonitoring"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        />
      </div>

      <!-- Class Dropdown -->
      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Filter Jilid / Kelas</label>
        <select
          v-model="selectedKelas"
          @change="loadMonitoring"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        >
          <option value="">Semua Jilid / Kelas</option>
          <option v-for="k in kelasList" :key="k.id" :value="k.id">
            {{ k.nama_kelas }} ({{ k.wali_kelas || 'Wali -' }})
          </option>
        </select>
      </div>

      <!-- Search Box -->
      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Cari Santri</label>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Ketik nama atau NISN..."
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        />
      </div>
    </div>

    <!-- Stat Metrics Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
      <div class="p-3.5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 text-center">
        <span class="text-[10px] font-bold uppercase text-zinc-400 block">Total Santri</span>
        <span class="text-xl font-extrabold text-zinc-800 dark:text-zinc-200">{{ stats.total }}</span>
      </div>
      <div class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-center">
        <span class="text-[10px] font-bold uppercase text-emerald-600 dark:text-emerald-400 block">Hadir</span>
        <span class="text-xl font-extrabold text-emerald-700 dark:text-emerald-300">{{ stats.hadir }}</span>
      </div>
      <div class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-center">
        <span class="text-[10px] font-bold uppercase text-amber-600 dark:text-amber-400 block">Terlambat</span>
        <span class="text-xl font-extrabold text-amber-700 dark:text-amber-300">{{ stats.terlambat }}</span>
      </div>
      <div class="p-3.5 rounded-2xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-center">
        <span class="text-[10px] font-bold uppercase text-blue-600 dark:text-blue-400 block">Sakit</span>
        <span class="text-xl font-extrabold text-blue-700 dark:text-blue-300">{{ stats.sakit }}</span>
      </div>
      <div class="p-3.5 rounded-2xl bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800 text-center">
        <span class="text-[10px] font-bold uppercase text-purple-600 dark:text-purple-400 block">Izin</span>
        <span class="text-xl font-extrabold text-purple-700 dark:text-purple-300">{{ stats.izin }}</span>
      </div>
      <div class="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-center">
        <span class="text-[10px] font-bold uppercase text-rose-600 dark:text-rose-400 block">Alpa</span>
        <span class="text-xl font-extrabold text-rose-700 dark:text-rose-300">{{ stats.alpa }}</span>
      </div>
      <div class="p-3.5 rounded-2xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-center">
        <span class="text-[10px] font-bold uppercase text-zinc-500 block">Belum Absen</span>
        <span class="text-xl font-extrabold text-zinc-700 dark:text-zinc-300">{{ stats.belum_absen }}</span>
      </div>
    </div>

    <!-- Santri Live Table -->
    <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200/80 dark:border-zinc-800 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-zinc-50 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-800 text-zinc-500 uppercase tracking-wider text-[10px]">
            <tr>
              <th class="py-3 px-4">No</th>
              <th class="py-3 px-4">Santri</th>
              <th class="py-3 px-4">Jilid / Kelas</th>
              <th class="py-3 px-4">Status Kehadiran</th>
              <th class="py-3 px-4">Waktu Masuk</th>
              <th class="py-3 px-4">Waktu Pulang</th>
              <th class="py-3 px-4">Keterangan</th>
              <th class="py-3 px-4 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-200/60 dark:divide-zinc-800">
            <tr v-if="filteredStudents.length === 0">
              <td colspan="8" class="py-8 text-center text-zinc-400">
                Tidak ada data santri yang cocok dengan filter.
              </td>
            </tr>

            <tr
              v-for="(s, idx) in filteredStudents"
              :key="s.id_siswa"
              class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition"
            >
              <td class="py-3 px-4 text-zinc-400">{{ idx + 1 }}</td>
              <td class="py-3 px-4">
                <p class="font-bold text-zinc-900 dark:text-zinc-100">{{ s.nama_lengkap }}</p>
                <p class="text-[10px] text-zinc-400 font-mono">NISN: {{ s.nisn }} ({{ s.jenis_kelamin }})</p>
              </td>
              <td class="py-3 px-4">
                <span
                  class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold text-white shadow-xs"
                  :style="{ backgroundColor: s.warna_label || '#3b82f6' }"
                >
                  {{ s.nama_kelas || '-' }}
                </span>
              </td>
              <td class="py-3 px-4">
                <span
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold"
                  :class="{
                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': s.status === 'Hadir',
                    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': s.status === 'Terlambat',
                    'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300': s.status === 'Sakit',
                    'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300': s.status === 'Izin',
                    'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': s.status === 'Alpa',
                    'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400': s.status === 'Belum Absen',
                  }"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="{
                    'bg-emerald-500': s.status === 'Hadir',
                    'bg-amber-500': s.status === 'Terlambat',
                    'bg-blue-500': s.status === 'Sakit',
                    'bg-purple-500': s.status === 'Izin',
                    'bg-rose-500': s.status === 'Alpa',
                    'bg-zinc-400': s.status === 'Belum Absen',
                  }"></span>
                  {{ s.status }}
                </span>
              </td>
              <td class="py-3 px-4 font-mono text-zinc-700 dark:text-zinc-300">
                {{ s.waktu_masuk ? s.waktu_masuk + ' WIB' : '-' }}
              </td>
              <td class="py-3 px-4 font-mono text-zinc-700 dark:text-zinc-300">
                {{ s.waktu_pulang ? s.waktu_pulang + ' WIB' : '-' }}
              </td>
              <td class="py-3 px-4 text-zinc-500 text-[11px]">
                {{ s.keterangan || '-' }}
              </td>
              <td class="py-3 px-4 text-center">
                <button
                  @click="openStatusModal(s)"
                  class="px-2.5 py-1 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:bg-emerald-50 dark:hover:bg-emerald-950 text-emerald-700 dark:text-emerald-300 text-[11px] font-semibold transition cursor-pointer"
                >
                  Ubah Status
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Ubah Status Manual -->
    <div
      v-if="editingStudent"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
    >
      <div class="w-full max-w-sm rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-6 shadow-2xl animate-slideUp">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-base font-bold text-zinc-900 dark:text-white">Ubah Status Absensi</h3>
          <button @click="editingStudent = null" class="text-zinc-400 hover:text-zinc-600 font-bold">✕</button>
        </div>

        <p class="text-xs text-zinc-500 mb-4">
          Santri: <strong class="text-zinc-800 dark:text-zinc-200">{{ editingStudent.nama_lengkap }}</strong> ({{ editingStudent.nisn }})
        </p>

        <form @submit.prevent="saveStatusChange" class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Status Baru</label>
            <select
              v-model="newStatus"
              class="w-full px-3 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            >
              <option value="Hadir">Hadir</option>
              <option value="Terlambat">Terlambat</option>
              <option value="Sakit">Sakit</option>
              <option value="Izin">Izin</option>
              <option value="Alpa">Alpa</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Keterangan (Opsional)</label>
            <input
              v-model="newKeterangan"
              type="text"
              placeholder="Cth: Surat dokter / izin acara keluarga..."
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              @click="editingStudent = null"
              class="px-4 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 font-semibold text-zinc-600 dark:text-zinc-300 cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold shadow-md shadow-emerald-600/20 cursor-pointer"
            >
              Simpan Status
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
import { useToast } from '../../composables/useToast';

const toast = useToast();

const loading = ref(false);
const filterDate = ref(new Date().toISOString().split('T')[0]);
const selectedKelas = ref('');
const searchQuery = ref('');
const kelasList = ref([]);
const students = ref([]);
const stats = ref({ total: 0, hadir: 0, terlambat: 0, sakit: 0, izin: 0, alpa: 0, belum_absen: 0 });

const editingStudent = ref(null);
const newStatus = ref('Hadir');
const newKeterangan = ref('');

const filteredStudents = computed(() => {
  return students.value.filter(s => {
    if (!searchQuery.value) return true;
    const q = searchQuery.value.toLowerCase();
    return s.nama_lengkap.toLowerCase().includes(q) || s.nisn.toLowerCase().includes(q);
  });
});

const loadKelas = async () => {
  try {
    const res = await api.get('/kelas');
    if (res.data.success) {
      kelasList.value = res.data.data;
    }
  } catch (e) {
    //
  }
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

const openStatusModal = (student) => {
  editingStudent.value = student;
  newStatus.value = student.status === 'Belum Absen' ? 'Hadir' : student.status;
  newKeterangan.value = student.keterangan || '';
};

const saveStatusChange = async () => {
  if (!editingStudent.value) return;

  try {
    const res = await api.post('/absensi/status', {
      id_siswa: editingStudent.value.id_siswa,
      tanggal: filterDate.value,
      status: newStatus.value,
      keterangan: newKeterangan.value,
    });

    if (res.data.success) {
      toast.success('Status santri berhasil diperbarui!');
      editingStudent.value = null;
      loadMonitoring();
    }
  } catch (e) {
    toast.error('Gagal memperbarui status absensi.');
  }
};

onMounted(() => {
  loadKelas();
  loadMonitoring();
});
</script>
