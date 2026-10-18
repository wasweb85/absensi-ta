<template>
  <div class="space-y-6 animate-fadeIn">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>🎓</span>
          <span>Data Santri & Cetak QR Code</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Kelola master data santri terpusat untuk absensi dan keuangan SPP
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <button
          @click="showImportModal = true"
          class="px-3.5 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>📥</span>
          <span>Import CSV/Excel</span>
        </button>
        <button
          @click="openAddModal"
          class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>➕</span>
          <span>Tambah Santri</span>
        </button>
      </div>
    </div>

    <!-- Filters Row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm text-xs">
      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Filter Jilid / Kelas</label>
        <select
          v-model="filterKelas"
          @change="loadSiswa"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs"
        >
          <option value="">Semua Kelas</option>
          <option v-for="k in kelasList" :key="k.id" :value="k.id">{{ k.nama_kelas }}</option>
        </select>
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Status Santri</label>
        <select
          v-model="filterStatus"
          @change="loadSiswa"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs"
        >
          <option value="">Semua Status</option>
          <option value="aktif">Aktif</option>
          <option value="nonaktif">Nonaktif</option>
          <option value="lulus">Lulus</option>
          <option value="pindah">Pindah</option>
        </select>
      </div>

      <div>
        <label class="block text-[11px] font-bold text-zinc-500 mb-1">Cari Nama / NISN</label>
        <input
          v-model="search"
          type="text"
          placeholder="Cari santri..."
          @input="loadSiswa"
          class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs"
        />
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200/80 dark:border-zinc-800 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-500 font-bold border-b border-zinc-200 dark:border-zinc-800 text-[10px] uppercase">
            <tr>
              <th class="py-3 px-4">No</th>
              <th class="py-3 px-4">NISN</th>
              <th class="py-3 px-4">Nama Lengkap</th>
              <th class="py-3 px-4">L/P</th>
              <th class="py-3 px-4">Jilid / Kelas</th>
              <th class="py-3 px-4">Wali & WhatsApp</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-200/60 dark:divide-zinc-800">
            <tr v-if="siswaList.length === 0">
              <td colspan="8" class="py-8 text-center text-zinc-400">
                Tidak ada data santri yang ditemukan.
              </td>
            </tr>

            <tr
              v-for="(s, idx) in siswaList"
              :key="s.id"
              class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition"
            >
              <td class="py-3 px-4 text-zinc-400">{{ idx + 1 }}</td>
              <td class="py-3 px-4 font-mono font-bold text-zinc-900 dark:text-zinc-100">{{ s.nisn }}</td>
              <td class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100">{{ s.nama_lengkap }}</td>
              <td class="py-3 px-4 font-bold" :class="s.jenis_kelamin === 'L' ? 'text-blue-600' : 'text-pink-600'">
                {{ s.jenis_kelamin }}
              </td>
              <td class="py-3 px-4">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[11px] font-bold text-white"
                  :style="{ backgroundColor: s.kelas?.warna_label || '#3b82f6' }"
                >
                  {{ s.kelas?.nama_kelas || '-' }}
                </span>
              </td>
              <td class="py-3 px-4 text-[11px]">
                <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ s.nama_ortu || '-' }}</p>
                <p class="text-zinc-400 font-mono">{{ s.no_hp_ortu || '-' }}</p>
              </td>
              <td class="py-3 px-4">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase"
                  :class="{
                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': s.status === 'aktif',
                    'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400': s.status !== 'aktif',
                  }"
                >
                  {{ s.status }}
                </span>
              </td>
              <td class="py-3 px-4 text-center">
                <div class="inline-flex items-center gap-1.5">
                  <button
                    @click="showQrCard(s)"
                    title="Cetak Kartu QR"
                    class="p-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:bg-emerald-50 dark:hover:bg-emerald-950 text-emerald-600 text-xs transition cursor-pointer"
                  >
                    🪪 QR
                  </button>
                  <button
                    @click="openEditModal(s)"
                    title="Edit Santri"
                    class="p-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:bg-blue-50 dark:hover:bg-blue-950 text-blue-600 text-xs transition cursor-pointer"
                  >
                    ✏
                  </button>
                  <button
                    @click="deleteStudent(s)"
                    title="Hapus"
                    class="p-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:bg-rose-50 dark:hover:bg-rose-950 text-rose-600 text-xs transition cursor-pointer"
                  >
                    🗑
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Form Santri (Tambah / Edit) -->
    <div
      v-if="showFormModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
    >
      <div class="w-full max-w-lg rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-6 shadow-2xl animate-slideUp max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-base font-bold text-zinc-900 dark:text-white">
            {{ isEditing ? 'Edit Data Santri' : 'Tambah Santri Baru' }}
          </h3>
          <button @click="showFormModal = false" class="text-zinc-400 hover:text-zinc-600 font-bold">✕</button>
        </div>

        <form @submit.prevent="saveStudent" class="space-y-3.5 text-xs">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">NISN *</label>
              <input
                v-model="form.nisn"
                type="text"
                required
                placeholder="Cth: 1001"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
              />
            </div>
            <div>
              <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Jenis Kelamin *</label>
              <select
                v-model="form.jenis_kelamin"
                required
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
              >
                <option value="L">Laki-laki (L)</option>
                <option value="P">Perempuan (P)</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Nama Lengkap Santri *</label>
            <input
              v-model="form.nama_lengkap"
              type="text"
              required
              placeholder="Nama lengkap santri..."
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Jilid / Kelas *</label>
              <select
                v-model="form.id_kelas"
                required
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
              >
                <option value="">Pilih Jilid/Kelas</option>
                <option v-for="k in kelasList" :key="k.id" :value="k.id">{{ k.nama_kelas }}</option>
              </select>
            </div>
            <div>
              <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Status Keaktifan *</label>
              <select
                v-model="form.status"
                required
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
              >
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Nonaktif</option>
                <option value="lulus">Lulus</option>
                <option value="pindah">Pindah</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Nama Orang Tua / Wali</label>
              <input
                v-model="form.nama_ortu"
                type="text"
                placeholder="Nama ayah/ibu..."
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
              />
            </div>
            <div>
              <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">No. WhatsApp Wali</label>
              <input
                v-model="form.no_hp_ortu"
                type="text"
                placeholder="Cth: 081234567890"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
              />
            </div>
          </div>

          <div>
            <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Alamat Rumah</label>
            <textarea
              v-model="form.alamat"
              rows="2"
              placeholder="Alamat tempat tinggal..."
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
            <button
              type="button"
              @click="showFormModal = false"
              class="px-4 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 font-semibold text-zinc-600 dark:text-zinc-300 cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold shadow-md shadow-emerald-600/20 cursor-pointer"
            >
              Simpan Data
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Cetak Kartu QR Santri -->
    <div
      v-if="selectedCardStudent"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
    >
      <div class="w-full max-w-sm rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-6 shadow-2xl animate-slideUp text-center">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Kartu QR Presensi Santri</h3>
          <button @click="selectedCardStudent = null" class="text-zinc-400 hover:text-zinc-600 font-bold">✕</button>
        </div>

        <!-- The Printable Card -->
        <div id="printCardArea" class="p-6 rounded-2xl border-2 border-dashed border-emerald-500/50 bg-gradient-to-b from-emerald-50/50 to-white dark:from-zinc-900 dark:to-zinc-900 shadow-md text-center">
          <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-700 dark:text-emerald-400">KARTU PRESENSI SANTRI</p>
          <h4 class="text-base font-extrabold text-zinc-900 dark:text-white mt-1">{{ selectedCardStudent.nama_lengkap }}</h4>
          
          <span
            class="inline-block px-3 py-0.5 rounded-full text-[10px] font-bold text-white my-2"
            :style="{ backgroundColor: selectedCardStudent.kelas?.warna_label || '#059669' }"
          >
            {{ selectedCardStudent.kelas?.nama_kelas || '-' }}
          </span>

          <!-- QR Canvas Container -->
          <div class="flex items-center justify-center p-3 my-2 bg-white rounded-xl shadow-inner max-w-[180px] mx-auto border">
            <canvas ref="qrCanvasRef"></canvas>
          </div>

          <p class="text-xs font-mono font-bold text-zinc-700 dark:text-zinc-300">NISN: {{ selectedCardStudent.nisn }}</p>
        </div>

        <div class="flex items-center justify-center gap-2 mt-5">
          <button
            @click="printCard"
            class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/25 transition flex items-center gap-1.5 cursor-pointer"
          >
            <span>🖨</span>
            <span>Cetak Kartu QR</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Import Massal CSV -->
    <div
      v-if="showImportModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
    >
      <div class="w-full max-w-lg rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-6 shadow-2xl animate-slideUp">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-base font-bold text-zinc-900 dark:text-white">Import Massal Santri</h3>
          <button @click="showImportModal = false" class="text-zinc-400 hover:text-zinc-600 font-bold">✕</button>
        </div>

        <p class="text-xs text-zinc-500 mb-3">
          Paste data santri berformat CSV (pisahkan dengan koma atau tab):<br />
          Format kolom: <code>nisn, nama_lengkap, kelas, jenis_kelamin, no_hp_ortu, nama_ortu</code>
        </p>

        <textarea
          v-model="importText"
          rows="6"
          placeholder="1001, Ahmad Fauzi, JILID 1, L, 081234567890, Bapak Fauzi&#10;1002, Muhammad Bilal, JILID 1, L, 081234567891, Bapak Bilal"
          class="w-full px-3 py-2 font-mono rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        ></textarea>

        <div class="flex items-center justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800 mt-4">
          <button
            @click="showImportModal = false"
            class="px-4 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 text-xs font-semibold cursor-pointer"
          >
            Batal
          </button>
          <button
            @click="handleImportSubmit"
            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-md shadow-emerald-600/20 cursor-pointer"
          >
            Mulai Import
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import QRCode from 'qrcode';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';

const toast = useToast();

const siswaList = ref([]);
const kelasList = ref([]);
const filterKelas = ref('');
const filterStatus = ref('');
const search = ref('');

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
    const res = await api.get('/kelas');
    if (res.data.success) {
      kelasList.value = res.data.data;
    }
  } catch (e) {}
};

const loadSiswa = async () => {
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
  }
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
