<template>
  <div class="space-y-6 animate-fadeIn">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <div>
        <h2 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
          <span>📅</span>
          <span>Agenda & Kalender Libur</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
          Kelola agenda kegiatan sekolah dan penetapan tanggal libur presensi
        </p>
      </div>

      <!-- Tab Switcher -->
      <div class="flex items-center p-1 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-semibold">
        <button
          @click="activeTab = 'agenda'"
          class="px-4 py-1.5 rounded-lg transition cursor-pointer"
          :class="activeTab === 'agenda' ? 'bg-white dark:bg-zinc-700 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-zinc-500 hover:text-zinc-800'"
        >
          📋 Agenda Kegiatan
        </button>
        <button
          @click="activeTab = 'libur'"
          class="px-4 py-1.5 rounded-lg transition cursor-pointer"
          :class="activeTab === 'libur' ? 'bg-white dark:bg-zinc-700 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-zinc-500 hover:text-zinc-800'"
        >
          🏖 Hari Libur
        </button>
      </div>
    </div>

    <!-- TAB 1: AGENDA -->
    <div v-if="activeTab === 'agenda'" class="space-y-4">
      <div class="flex justify-end">
        <button
          @click="openAddAgenda"
          class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>➕</span>
          <span>Tambah Agenda</span>
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-if="agendaList.length === 0" class="col-span-full py-12 text-center text-zinc-400 text-xs">
          Belum ada jadwal agenda yang tersimpan.
        </div>

        <div
          v-for="item in agendaList"
          :key="item.id"
          class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm flex flex-col justify-between"
        >
          <div>
            <div class="flex items-center justify-between mb-2">
              <span class="px-2.5 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 font-mono text-[11px] font-bold">
                {{ item.tanggal }}
              </span>
              <button
                @click="deleteAgenda(item.id)"
                class="text-zinc-400 hover:text-rose-600 text-xs transition cursor-pointer"
              >
                Hapus
              </button>
            </div>
            <h4 class="text-sm font-bold text-zinc-900 dark:text-white">{{ item.judul }}</h4>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 leading-relaxed">{{ item.keterangan || '-' }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: HARI LIBUR -->
    <div v-if="activeTab === 'libur'" class="space-y-4">
      <div class="flex justify-end">
        <button
          @click="showLiburModal = true"
          class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>➕</span>
          <span>Tambah Hari Libur</span>
        </button>
      </div>

      <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200/80 dark:border-zinc-800 shadow-sm overflow-hidden">
        <table class="w-full text-left text-xs">
          <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-500 font-bold border-b border-zinc-200 dark:border-zinc-800 text-[10px] uppercase">
            <tr>
              <th class="py-3 px-4">Tanggal Mulai</th>
              <th class="py-3 px-4">Tanggal Selesai</th>
              <th class="py-3 px-4">Keterangan Libur</th>
              <th class="py-3 px-4 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-200/60 dark:divide-zinc-800">
            <tr v-if="liburList.length === 0">
              <td colspan="4" class="py-8 text-center text-zinc-400">
                Belum ada data hari libur yang terdaftar.
              </td>
            </tr>

            <tr v-for="lib in liburList" :key="lib.id" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
              <td class="py-3 px-4 font-mono font-bold text-zinc-800 dark:text-zinc-200">{{ lib.tanggal_mulai }}</td>
              <td class="py-3 px-4 font-mono font-bold text-zinc-800 dark:text-zinc-200">{{ lib.tanggal_selesai }}</td>
              <td class="py-3 px-4 font-semibold text-zinc-900 dark:text-white">{{ lib.keterangan }}</td>
              <td class="py-3 px-4 text-center">
                <button
                  @click="deleteLibur(lib.id)"
                  class="px-2.5 py-1 rounded-lg border border-rose-200 dark:border-rose-900 text-rose-600 text-xs hover:bg-rose-50 dark:hover:bg-rose-950 transition cursor-pointer"
                >
                  Hapus
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Form Agenda -->
    <div v-if="showAgendaModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
      <div class="w-full max-w-sm rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-6 shadow-2xl animate-slideUp">
        <h3 class="text-base font-bold text-zinc-900 dark:text-white mb-4">Tambah Agenda Kegiatan</h3>
        <form @submit.prevent="saveAgenda" class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-zinc-500 mb-1">Tanggal</label>
            <input v-model="agendaForm.tanggal" type="date" required class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800" />
          </div>
          <div>
            <label class="block font-bold text-zinc-500 mb-1">Judul Agenda</label>
            <input v-model="agendaForm.judul" type="text" required placeholder="Judul kegiatan..." class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800" />
          </div>
          <div>
            <label class="block font-bold text-zinc-500 mb-1">Keterangan</label>
            <textarea v-model="agendaForm.keterangan" rows="2" placeholder="Detail agenda..." class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800"></textarea>
          </div>
          <div class="flex justify-end gap-2 pt-3">
            <button type="button" @click="showAgendaModal = false" class="px-4 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700">Batal</button>
            <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-semibold">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Form Libur -->
    <div v-if="showLiburModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
      <div class="w-full max-w-sm rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-6 shadow-2xl animate-slideUp">
        <h3 class="text-base font-bold text-zinc-900 dark:text-white mb-4">Tambah Hari Libur</h3>
        <form @submit.prevent="saveLibur" class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-zinc-500 mb-1">Tanggal Mulai</label>
            <input v-model="liburForm.tanggal_mulai" type="date" required class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800" />
          </div>
          <div>
            <label class="block font-bold text-zinc-500 mb-1">Tanggal Selesai</label>
            <input v-model="liburForm.tanggal_selesai" type="date" required class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800" />
          </div>
          <div>
            <label class="block font-bold text-zinc-500 mb-1">Keterangan Libur</label>
            <input v-model="liburForm.keterangan" type="text" required placeholder="Cth: Libur Idul Fitri" class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800" />
          </div>
          <div class="flex justify-end gap-2 pt-3">
            <button type="button" @click="showLiburModal = false" class="px-4 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700">Batal</button>
            <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-semibold">Simpan</button>
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

const activeTab = ref('agenda');
const agendaList = ref([]);
const liburList = ref([]);

const showAgendaModal = ref(false);
const agendaForm = ref({ tanggal: '', judul: '', keterangan: '' });

const showLiburModal = ref(false);
const liburForm = ref({ tanggal_mulai: '', tanggal_selesai: '', keterangan: '' });

const loadAgenda = async () => {
  try {
    const res = await api.get('/agenda');
    if (res.data.success) {
      agendaList.value = res.data.data;
    }
  } catch (e) {}
};

const loadLibur = async () => {
  try {
    const res = await api.get('/hari-libur');
    if (res.data.success) {
      liburList.value = res.data.data;
    }
  } catch (e) {}
};

const openAddAgenda = () => {
  agendaForm.value = {
    tanggal: new Date().toISOString().split('T')[0],
    judul: '',
    keterangan: '',
  };
  showAgendaModal.value = true;
};

const saveAgenda = async () => {
  try {
    await api.post('/agenda', agendaForm.value);
    toast.success('Agenda berhasil ditambahkan!');
    showAgendaModal.value = false;
    loadAgenda();
  } catch (e) {
    toast.error('Gagal menyimpan agenda.');
  }
};

const deleteAgenda = async (id) => {
  if (confirm('Hapus agenda ini?')) {
    try {
      await api.delete(`/agenda/${id}`);
      toast.success('Agenda berhasil dihapus.');
      loadAgenda();
    } catch (e) {
      toast.error('Gagal menghapus agenda.');
    }
  }
};

const saveLibur = async () => {
  try {
    await api.post('/hari-libur', liburForm.value);
    toast.success('Hari libur berhasil ditambahkan!');
    showLiburModal.value = false;
    loadLibur();
  } catch (e) {
    toast.error('Gagal menyimpan hari libur.');
  }
};

const deleteLibur = async (id) => {
  if (confirm('Hapus tanggal libur ini?')) {
    try {
      await api.delete(`/hari-libur/${id}`);
      toast.success('Hari libur berhasil dihapus.');
      loadLibur();
    } catch (e) {
      toast.error('Gagal menghapus hari libur.');
    }
  }
};

onMounted(() => {
  loadAgenda();
  loadLibur();
});
</script>
