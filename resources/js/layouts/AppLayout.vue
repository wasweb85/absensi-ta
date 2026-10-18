<template>
  <div class="min-h-screen flex bg-zinc-100 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 font-sans antialiased selection:bg-emerald-500 selection:text-white transition-colors duration-200">
    
    <!-- Mobile Sidebar Backdrop -->
    <div
      v-if="mobileMenuOpen"
      @click="mobileMenuOpen = false"
      class="fixed inset-0 z-40 bg-black/50 backdrop-blur-xs lg:hidden transition-opacity"
    ></div>

    <!-- SIDEBAR -->
    <aside
      :class="[
        'fixed top-0 bottom-0 left-0 z-40 w-64 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-r border-zinc-200/80 dark:border-zinc-800 flex flex-col transition-transform duration-300 lg:translate-x-0',
        mobileMenuOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'
      ]"
    >
      <!-- Brand Header -->
      <div class="h-16 px-6 flex items-center justify-between border-b border-zinc-200/80 dark:border-zinc-800">
        <router-link to="/dashboard" class="flex items-center gap-3 group">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 text-white flex items-center justify-center font-bold text-lg shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
            TA
          </div>
          <div>
            <h1 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white leading-tight">
              Sistem Terpadu
            </h1>
            <p class="text-[10px] text-zinc-400 font-medium">Absensi & SPP Santri</p>
          </div>
        </router-link>
        <button
          @click="mobileMenuOpen = false"
          class="lg:hidden p-1.5 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition"
        >
          ✕
        </button>
      </div>

      <!-- User Mini Profile in Sidebar -->
      <div class="px-5 py-3 border-b border-zinc-100 dark:border-zinc-800/60 bg-zinc-50/50 dark:bg-zinc-800/20">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-bold flex items-center justify-center text-sm border border-emerald-300 dark:border-emerald-800">
            {{ user?.name?.charAt(0) || 'U' }}
          </div>
          <div class="overflow-hidden">
            <p class="text-xs font-semibold truncate text-zinc-900 dark:text-zinc-100">{{ user?.name || 'User' }}</p>
            <div class="flex items-center gap-1.5 mt-0.5">
              <span
                class="px-2 py-0.5 rounded text-[10px] font-bold tracking-wider"
                :class="{
                  'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200': role === 'admin',
                  'bg-purple-100 text-purple-800 dark:bg-purple-950/80 dark:text-purple-300': role === 'kepala',
                  'bg-blue-100 text-blue-800 dark:bg-blue-950/80 dark:text-blue-300': role === 'sekretaris',
                  'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300': role === 'bendahara',
                  'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300': role === 'guru',
                  'bg-teal-100 text-teal-800 dark:bg-teal-950/80 dark:text-teal-300': role === 'siswa',
                }"
              >
                {{ roleLabel }}
              </span>
              <span v-if="user?.nama_kelas" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 truncate">
                Wali {{ user.nama_kelas }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Navigation Links -->
      <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        
        <!-- Section: Overview -->
        <div>
          <router-link
            to="/dashboard"
            @click="mobileMenuOpen = false"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition"
            :class="isActive('/dashboard') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/30 font-bold' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60 hover:text-zinc-900 dark:hover:text-zinc-200'"
          >
            <span class="text-base">📊</span>
            <span>Dashboard Utama</span>
          </router-link>
        </div>

        <!-- Section: Modul Absensi -->
        <div>
          <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">
            Modul Absensi
          </p>
          <div class="space-y-1">
            <router-link
              to="/absensi/scanner"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/absensi/scanner') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">📷</span>
              <span>Scanner QR & Barcode</span>
            </router-link>
            
            <router-link
              to="/absensi/monitoring"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/absensi/monitoring') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">⏱</span>
              <span>Monitoring Realtime</span>
            </router-link>

            <router-link
              to="/absensi/rekap"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/absensi/rekap') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">📑</span>
              <span>Rekap Matrix 1-31 Hari</span>
            </router-link>

            <router-link
              to="/absensi/riwayat"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/absensi/riwayat') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">📋</span>
              <span>Riwayat Absensi</span>
            </router-link>

            <router-link
              v-if="!isSiswa"
              to="/absensi/siswa"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/absensi/siswa') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">🎓</span>
              <span>Data Santri & QR</span>
            </router-link>

            <router-link
              v-if="canManageAgenda"
              to="/absensi/agenda-libur"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/absensi/agenda-libur') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">📅</span>
              <span>Agenda & Hari Libur</span>
            </router-link>
          </div>
        </div>

        <!-- Section: Modul SPP & Keuangan -->
        <div v-if="canViewSppReports">
          <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">
            Modul SPP & Keuangan
          </p>
          <div class="space-y-1">
            <router-link
              v-if="canManageSpp"
              to="/spp/transaksi"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/spp/transaksi') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">💳</span>
              <span>Kasir / Bayar SPP</span>
            </router-link>

            <router-link
              v-if="canViewSppReports"
              to="/spp/tagihan"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/spp/tagihan') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">📜</span>
              <span>Tagihan & Tunggakan</span>
            </router-link>

            <router-link
              v-if="canManageSpp"
              to="/spp/input-cepat"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/spp/input-cepat') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">⚡</span>
              <span>Input Cepat (Batch)</span>
            </router-link>

            <router-link
              v-if="canManageSpp"
              to="/spp/pengingat-wa"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/spp/pengingat-wa') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">💬</span>
              <span>Pengingat WhatsApp</span>
            </router-link>

            <router-link
              v-if="canViewSppReports"
              to="/spp/riwayat"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/spp/riwayat') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">🧾</span>
              <span>Riwayat Kuitansi SPP</span>
            </router-link>
          </div>
        </div>

        <!-- Section: Master & Pengaturan -->
        <div v-if="canManageSettings">
          <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">
            Master & Pengaturan
          </p>
          <div class="space-y-1">
            <router-link
              to="/kelas"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/kelas') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">🏷</span>
              <span>Data Jilid & Tarif SPP</span>
            </router-link>

            <router-link
              to="/pengaturan"
              @click="mobileMenuOpen = false"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
              :class="isActive('/pengaturan') ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60'"
            >
              <span class="text-base">⚙</span>
              <span>Pengaturan Sistem</span>
            </router-link>
          </div>
        </div>

      </nav>

      <!-- Sidebar Footer (Logout) -->
      <div class="p-3 border-t border-zinc-200/80 dark:border-zinc-800">
        <button
          @click="handleLogout"
          class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
        >
          <span class="text-base">🚪</span>
          <span>Keluar (Logout)</span>
        </button>
      </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col min-w-0 lg:pl-64">
      
      <!-- TOPBAR -->
      <header class="h-16 sticky top-0 z-30 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border-b border-zinc-200/80 dark:border-zinc-800 px-4 sm:px-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button
            @click="mobileMenuOpen = true"
            class="lg:hidden p-2 rounded-xl text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition"
          >
            ☰
          </button>
          <div class="hidden sm:flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 font-mono">
            <span>⏰ Jam Server:</span>
            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ serverClock }} WIB</span>
          </div>
        </div>

        <!-- Right Tools -->
        <div class="flex items-center gap-3">
          <!-- Dark Mode Toggle -->
          <button
            @click="toggleDarkMode"
            class="p-2 rounded-xl border border-zinc-200 dark:border-zinc-800 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition text-sm cursor-pointer"
            title="Ganti Tema Gelap/Terang"
          >
            {{ isDark ? '🌙' : '☀️' }}
          </button>

          <!-- User Display -->
          <div class="flex items-center gap-2 pl-3 border-l border-zinc-200 dark:border-zinc-800">
            <div class="hidden md:block text-right">
              <p class="text-xs font-bold leading-tight">{{ user?.name }}</p>
              <p class="text-[10px] text-zinc-400 capitalize">{{ role }}</p>
            </div>
            <div class="w-8 h-8 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-xs shadow-sm">
              {{ user?.name?.charAt(0) || 'U' }}
            </div>
          </div>
        </div>
      </header>

      <!-- PAGE BODY -->
      <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
        <router-view />
      </main>

    </div>

    <!-- Toast Notifications Container -->
    <ToastContainer />

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuth } from '../composables/useAuth';
import ToastContainer from '../components/ToastContainer.vue';

const route = useRoute();
const {
  user,
  role,
  roleLabel,
  isAdmin,
  isKepala,
  isSekretaris,
  isBendahara,
  isGuru,
  isSiswa,
  canManageSpp,
  canViewSppReports,
  canManageSantri,
  canManageAgenda,
  canManageSettings,
  canDoAbsensi,
  logout
} = useAuth();

const mobileMenuOpen = ref(false);
const isDark = ref(false);
const serverClock = ref('');
let clockInterval = null;

const isActive = (path) => {
  return route.path === path || route.path.startsWith(path + '/');
};

const handleLogout = () => {
  logout();
};

const toggleDarkMode = () => {
  isDark.value = !isDark.value;
  if (isDark.value) {
    document.documentElement.classList.add('dark');
    localStorage.setItem('theme', 'dark');
  } else {
    document.documentElement.classList.remove('dark');
    localStorage.setItem('theme', 'light');
  }
};

const updateClock = () => {
  const now = new Date();
  serverClock.value = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

onMounted(() => {
  if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    isDark.value = true;
    document.documentElement.classList.add('dark');
  } else {
    isDark.value = false;
    document.documentElement.classList.remove('dark');
  }

  updateClock();
  clockInterval = setInterval(updateClock, 1000);
});

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval);
});
</script>
