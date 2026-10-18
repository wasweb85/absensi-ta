<template>
  <div class="space-y-6 animate-fadeIn">
    
    <!-- Welcome Hero Banner -->
    <div class="relative overflow-hidden p-6 sm:p-8 rounded-3xl bg-gradient-to-r from-emerald-700 via-teal-700 to-emerald-800 text-white shadow-xl shadow-emerald-800/15">
      <div class="relative z-10 max-w-2xl">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-xs font-semibold mb-3">
          <span>🕌</span>
          <span>Sistem Informasi Terpadu TPQ & Pesantren</span>
        </div>
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
          Assalamu'alaikum, {{ user?.name || 'Ustadz / Ustadzah' }}!
        </h2>
        <p class="text-emerald-100 text-xs sm:text-sm mt-2 leading-relaxed">
          Semua sistem absensi santri dan administrasi pembayaran SPP terpadu dalam satu basis data. Pantau kehadiran realtime dan kelancaran pembayaran santri secara transparan.
        </p>

        <!-- Quick Action Buttons -->
        <div class="flex flex-wrap items-center gap-2.5 mt-6">
          <router-link
            to="/absensi/scanner"
            class="px-4 py-2.5 rounded-xl bg-white text-emerald-900 hover:bg-emerald-50 font-bold text-xs shadow transition flex items-center gap-1.5"
          >
            <span>📷</span>
            <span>Buka Scanner Presensi</span>
          </router-link>

          <router-link
            v-if="canManageSpp"
            to="/spp/transaksi"
            class="px-4 py-2.5 rounded-xl bg-emerald-900/60 hover:bg-emerald-900 text-white font-bold text-xs border border-white/20 transition flex items-center gap-1.5"
          >
            <span>💳</span>
            <span>Kasir Pembayaran SPP</span>
          </router-link>

          <router-link
            to="/absensi/monitoring"
            class="px-4 py-2.5 rounded-xl bg-emerald-900/40 hover:bg-emerald-900/80 text-emerald-100 font-semibold text-xs border border-white/10 transition flex items-center gap-1.5"
          >
            <span>⏱</span>
            <span>Live Monitoring</span>
          </router-link>
        </div>
      </div>
      
      <!-- Decorative Backdrop circles -->
      <div class="absolute -right-10 -bottom-10 w-72 h-72 rounded-full bg-white/10 blur-3xl pointer-events-none"></div>
    </div>

    <!-- Top KPI Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      
      <!-- Card 1: Kehadiran Hari Ini -->
      <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl font-bold">
          ✓
        </div>
        <div>
          <p class="text-[11px] uppercase tracking-wider font-bold text-zinc-400">Presensi Hari Ini</p>
          <h3 class="text-xl font-extrabold text-zinc-900 dark:text-white mt-0.5">
            {{ absensiStats.hadir }} / {{ absensiStats.total }} <span class="text-xs font-normal text-zinc-500">Santri</span>
          </h3>
          <p class="text-[11px] text-zinc-500 mt-0.5">
            Terlambat: <span class="font-bold text-amber-600">{{ absensiStats.terlambat }}</span> santri
          </p>
        </div>
      </div>

      <!-- Card 2: Izin & Sakit Hari Ini -->
      <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xl font-bold">
          📋
        </div>
        <div>
          <p class="text-[11px] uppercase tracking-wider font-bold text-zinc-400">Izin & Sakit Hari Ini</p>
          <h3 class="text-xl font-extrabold text-zinc-900 dark:text-white mt-0.5">
            {{ absensiStats.izin + absensiStats.sakit }} <span class="text-xs font-normal text-zinc-500">Santri</span>
          </h3>
          <p class="text-[11px] text-zinc-500 mt-0.5">
            Izin: {{ absensiStats.izin }} &bull; Sakit: {{ absensiStats.sakit }} &bull; Alpa: {{ absensiStats.alpa }}
          </p>
        </div>
      </div>

      <!-- Card 3: Pemasukan SPP Bulan Ini -->
      <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 flex items-center justify-center text-xl font-bold">
          💰
        </div>
        <div>
          <p class="text-[11px] uppercase tracking-wider font-bold text-zinc-400">Pemasukan SPP Bulan Ini</p>
          <h3 class="text-lg font-extrabold text-zinc-900 dark:text-white mt-0.5">
            Rp {{ formatRupiah(sppStats.pemasukan_bulan_ini) }}
          </h3>
          <p class="text-[11px] text-zinc-500 mt-0.5">
            Kelunasan: <span class="font-bold text-emerald-600">{{ sppStats.persentase_lunas }}%</span> ({{ sppStats.santri_lunas }} Lunas)
          </p>
        </div>
      </div>

      <!-- Card 4: Tunggakan SPP Aktif -->
      <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl font-bold">
          ⚠️
        </div>
        <div>
          <p class="text-[11px] uppercase tracking-wider font-bold text-zinc-400">Total Tunggakan Aktif</p>
          <h3 class="text-lg font-extrabold text-rose-600 dark:text-rose-400 mt-0.5">
            Rp {{ formatRupiah(sppStats.total_tunggakan_aktif) }}
          </h3>
          <p class="text-[11px] text-zinc-500 mt-0.5">
            Belum Lunas: <span class="font-bold text-rose-600">{{ sppStats.santri_belum_lunas }}</span> Santri
          </p>
        </div>
      </div>

    </div>

    <!-- Middle Section: Financial Chart & Attendance Summary -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      
      <!-- LEFT: Chart Pendapatan SPP 12 Bulan (8 cols) -->
      <div class="lg:col-span-8 p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-base font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
              <span>📊</span>
              <span>Tren Penerimaan SPP Santri Tahun {{ currentYear }}</span>
            </h3>
            <p class="text-xs text-zinc-500 mt-0.5">Grafik total nominal kas pembayaran SPP per bulan</p>
          </div>
          <span class="text-xs font-bold px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400">
            Realtime
          </span>
        </div>

        <!-- Bar Visualizer -->
        <div class="pt-4">
          <div class="grid grid-cols-12 gap-1.5 sm:gap-2 items-end h-52 pb-6 border-b border-zinc-200 dark:border-zinc-800">
            <div
              v-for="g in sppStats.grafik_bulanan"
              :key="g.bulan"
              class="flex flex-col items-center h-full justify-end group"
            >
              <!-- Tooltip popup on hover -->
              <div class="hidden group-hover:block absolute -translate-y-12 bg-zinc-900 text-white text-[10px] px-2 py-1 rounded shadow-lg whitespace-nowrap z-20 font-mono">
                {{ g.nama_bulan }}: Rp {{ formatRupiah(g.total) }}
              </div>

              <!-- Bar -->
              <div
                class="w-full max-w-[28px] rounded-t-lg transition-all duration-500 group-hover:brightness-110"
                :class="g.total > 0 ? 'bg-gradient-to-t from-emerald-600 to-teal-400 shadow-sm' : 'bg-zinc-100 dark:bg-zinc-800'"
                :style="{ height: getBarHeight(g.total) }"
              ></div>

              <!-- Label -->
              <span class="text-[9px] sm:text-[10px] text-zinc-400 font-semibold mt-2 truncate w-full text-center">
                {{ g.nama_bulan.slice(0, 3) }}
              </span>
            </div>
          </div>

          <div class="flex items-center justify-between pt-3 text-xs text-zinc-500">
            <span>Rata-rata Bulanan: <b class="text-zinc-800 dark:text-zinc-200">Rp {{ formatRupiah(averageRevenue) }}</b></span>
            <span>Total Akumulasi: <b class="text-emerald-600">Rp {{ formatRupiah(yearlyRevenue) }}</b></span>
          </div>
        </div>
      </div>

      <!-- RIGHT: Today's Attendance Progress (4 cols) -->
      <div class="lg:col-span-4 p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-4">
        <div>
          <h3 class="text-base font-extrabold text-zinc-900 dark:text-white flex items-center gap-2">
            <span>🎯</span>
            <span>Rasio Kehadiran Hari Ini</span>
          </h3>
          <p class="text-xs text-zinc-500 mt-0.5">Persentase status presensi seluruh jilid</p>
        </div>

        <!-- Large Progress Ring/Bar -->
        <div class="space-y-4 pt-2">
          <div>
            <div class="flex justify-between text-xs font-bold mb-1">
              <span class="text-emerald-600">Hadir Tepat Waktu</span>
              <span>{{ attendancePercentage(absensiStats.hadir - absensiStats.terlambat) }}%</span>
            </div>
            <div class="w-full h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
              <div
                class="h-full bg-emerald-500 rounded-full transition-all duration-500"
                :style="{ width: `${attendancePercentage(absensiStats.hadir - absensiStats.terlambat)}%` }"
              ></div>
            </div>
          </div>

          <div>
            <div class="flex justify-between text-xs font-bold mb-1">
              <span class="text-amber-500">Terlambat</span>
              <span>{{ attendancePercentage(absensiStats.terlambat) }}%</span>
            </div>
            <div class="w-full h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
              <div
                class="h-full bg-amber-500 rounded-full transition-all duration-500"
                :style="{ width: `${attendancePercentage(absensiStats.terlambat)}%` }"
              ></div>
            </div>
          </div>

          <div>
            <div class="flex justify-between text-xs font-bold mb-1">
              <span class="text-blue-500">Izin / Sakit</span>
              <span>{{ attendancePercentage(absensiStats.izin + absensiStats.sakit) }}%</span>
            </div>
            <div class="w-full h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
              <div
                class="h-full bg-blue-500 rounded-full transition-all duration-500"
                :style="{ width: `${attendancePercentage(absensiStats.izin + absensiStats.sakit)}%` }"
              ></div>
            </div>
          </div>

          <div>
            <div class="flex justify-between text-xs font-bold mb-1">
              <span class="text-rose-500">Alpa / Belum Presensi</span>
              <span>{{ attendancePercentage(absensiStats.alpa) }}%</span>
            </div>
            <div class="w-full h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
              <div
                class="h-full bg-rose-500 rounded-full transition-all duration-500"
                :style="{ width: `${attendancePercentage(absensiStats.alpa)}%` }"
              ></div>
            </div>
          </div>
        </div>

        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
          <router-link
            to="/absensi/monitoring"
            class="w-full py-2.5 rounded-xl bg-zinc-50 dark:bg-zinc-800 hover:bg-zinc-100 text-xs font-bold text-zinc-700 dark:text-zinc-200 transition flex items-center justify-center gap-1.5"
          >
            <span>Buka Monitoring Realtime &rarr;</span>
          </router-link>
        </div>
      </div>

    </div>

    <!-- Quick Navigation Grid -->
    <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
      <h3 class="text-sm font-extrabold uppercase tracking-wider text-zinc-400 mb-4">
        Akses Cepat Modul Sistem
      </h3>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <router-link
          to="/absensi/scanner"
          class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-zinc-200/60 dark:border-zinc-800 text-center transition group"
        >
          <span class="text-3xl block mb-2 group-hover:scale-110 transition-transform">📷</span>
          <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 block">Scanner QR</span>
          <span class="text-[10px] text-zinc-400">Presensi santri</span>
        </router-link>

        <router-link
          to="/absensi/monitoring"
          class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-zinc-200/60 dark:border-zinc-800 text-center transition group"
        >
          <span class="text-3xl block mb-2 group-hover:scale-110 transition-transform">⏱</span>
          <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 block">Monitoring</span>
          <span class="text-[10px] text-zinc-400">Status harian</span>
        </router-link>

        <router-link
          to="/absensi/rekap"
          class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-zinc-200/60 dark:border-zinc-800 text-center transition group"
        >
          <span class="text-3xl block mb-2 group-hover:scale-110 transition-transform">📑</span>
          <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 block">Rekap Matrix</span>
          <span class="text-[10px] text-zinc-400">Tabel 1-31 hari</span>
        </router-link>

        <router-link
          v-if="canManageSpp"
          to="/spp/transaksi"
          class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-zinc-200/60 dark:border-zinc-800 text-center transition group"
        >
          <span class="text-3xl block mb-2 group-hover:scale-110 transition-transform">💳</span>
          <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 block">Kasir SPP</span>
          <span class="text-[10px] text-zinc-400">Input kuitansi</span>
        </router-link>

        <router-link
          v-if="canViewSppReports"
          to="/spp/tagihan"
          class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-zinc-200/60 dark:border-zinc-800 text-center transition group"
        >
          <span class="text-3xl block mb-2 group-hover:scale-110 transition-transform">📜</span>
          <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 block">Tagihan</span>
          <span class="text-[10px] text-zinc-400">Cek tunggakan</span>
        </router-link>

        <router-link
          v-if="canManageSpp"
          to="/spp/pengingat-wa"
          class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-zinc-200/60 dark:border-zinc-800 text-center transition group"
        >
          <span class="text-3xl block mb-2 group-hover:scale-110 transition-transform">📢</span>
          <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 block">Broadcast WA</span>
          <span class="text-[10px] text-zinc-400">Pengingat Fonnte</span>
        </router-link>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuth } from '../../composables/useAuth';
import api from '../../services/api';

const { user, isSiswa, canManageSpp, canViewSppReports } = useAuth();

const currentYear = new Date().getFullYear();

const absensiStats = ref({
  total: 0,
  hadir: 0,
  terlambat: 0,
  izin: 0,
  sakit: 0,
  alpa: 0,
});

const sppStats = ref({
  pemasukan_bulan_ini: 0,
  total_tagihan_nominal: 0,
  total_tunggakan_aktif: 0,
  santri_lunas: 0,
  santri_belum_lunas: 0,
  persentase_lunas: 0,
  grafik_bulanan: [],
});

function formatRupiah(val) {
  if (!val) return '0';
  return Number(val).toLocaleString('id-ID');
}

function attendancePercentage(count) {
  if (!absensiStats.value.total) return 0;
  return Math.round(((count || 0) / absensiStats.value.total) * 100);
}

const maxRevenue = computed(() => {
  const values = sppStats.value.grafik_bulanan.map(g => Number(g.total) || 0);
  return Math.max(...values, 100000);
});

const yearlyRevenue = computed(() => {
  return sppStats.value.grafik_bulanan.reduce((acc, curr) => acc + (Number(curr.total) || 0), 0);
});

const averageRevenue = computed(() => {
  if (sppStats.value.grafik_bulanan.length === 0) return 0;
  return Math.round(yearlyRevenue.value / sppStats.value.grafik_bulanan.length);
});

function getBarHeight(total) {
  if (!total || total <= 0) return '6px';
  const pct = Math.max(8, Math.round((Number(total) / maxRevenue.value) * 100));
  return `${pct}%`;
}

async function loadDashboard() {
  try {
    const [monRes, sppRes] = await Promise.all([
      api.get('/absensi/monitoring'),
      api.get('/spp/dashboard'),
    ]);

    if (monRes.data.success) {
      const s = monRes.data.data?.stats || {};
      absensiStats.value = {
        total: s.total || 0,
        hadir: s.hadir || 0,
        terlambat: s.terlambat || 0,
        izin: s.izin || 0,
        sakit: s.sakit || 0,
        alpa: s.alpa || 0,
      };
    }

    if (sppRes.data.success) {
      sppStats.value = sppRes.data.data || sppStats.value;
    }
  } catch (err) {
    console.error('Failed to load dashboard data:', err);
  }
}

onMounted(() => {
  loadDashboard();
});
</script>
