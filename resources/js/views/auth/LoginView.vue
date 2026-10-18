<template>
  <div class="min-h-screen w-full flex items-center justify-center p-4 bg-gradient-to-br from-zinc-100 via-emerald-50/30 to-blue-50/30 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950 text-zinc-900 dark:text-zinc-100 font-sans transition-colors duration-300">
    <div class="w-full max-w-md bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl rounded-3xl shadow-2xl border border-zinc-200/80 dark:border-zinc-800 p-8 sm:p-10 transition-all duration-300 animate-fadeIn">
      
      <!-- Brand Logo & Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-400 text-white shadow-lg shadow-emerald-500/25 mb-4 transform hover:scale-105 transition-transform">
          <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
          Sistem Terpadu
        </h1>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-1">
          Aplikasi Terintegrasi Absensi & SPP Santri
        </p>
      </div>

      <!-- Error Alert -->
      <div v-if="error" class="mb-5 p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-700 dark:text-rose-300 text-xs sm:text-sm flex items-center gap-2.5">
        <span class="text-base">⚠</span>
        <span>{{ error }}</span>
      </div>

      <!-- Login Form -->
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label for="username" class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
            Username / NISN
          </label>
          <div class="relative">
            <input
              id="username"
              v-model="username"
              type="text"
              required
              placeholder="Masukkan username atau NISN..."
              class="w-full px-4 py-3 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/60 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
            />
          </div>
        </div>

        <div>
          <label for="password" class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
            Password
          </label>
          <div class="relative">
            <input
              id="password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              required
              placeholder="Masukkan kata sandi..."
              class="w-full px-4 py-3 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/60 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition font-medium"
            >
              {{ showPassword ? 'Sembunyikan' : 'Lihat' }}
            </button>
          </div>
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold text-sm shadow-lg shadow-emerald-600/25 active:scale-[0.99] transition duration-200 flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed cursor-pointer"
        >
          <span v-if="loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span>{{ loading ? 'Memproses...' : 'Masuk ke Sistem' }}</span>
        </button>
      </form>

      <!-- Demo Quick Fill Buttons (Sangat berguna untuk presentasi dan pengujian skripsi) -->
      <div class="mt-8 pt-6 border-t border-zinc-200 dark:border-zinc-800">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 text-center mb-3">
          Akses Cepat Pengujian Role (Demo 1-Klik)
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <button
            type="button"
            @click="fillCredentials('admin', 'admin123')"
            class="px-2 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/40 hover:bg-zinc-100 text-[11px] font-medium text-zinc-700 dark:text-zinc-300 transition text-center cursor-pointer"
          >
            ⚙️ Admin
          </button>
          <button
            type="button"
            @click="fillCredentials('kepala', 'kepala123')"
            class="px-2 py-1.5 rounded-lg border border-purple-200 dark:border-purple-900 bg-purple-50/50 dark:bg-purple-950/30 hover:bg-purple-100 text-[11px] font-medium text-purple-700 dark:text-purple-300 transition text-center cursor-pointer"
          >
            👑 Kepala TPQ
          </button>
          <button
            type="button"
            @click="fillCredentials('sekretaris', 'sekretaris123')"
            class="px-2 py-1.5 rounded-lg border border-blue-200 dark:border-blue-900 bg-blue-50/50 dark:bg-blue-950/30 hover:bg-blue-100 text-[11px] font-medium text-blue-700 dark:text-blue-300 transition text-center cursor-pointer"
          >
            📝 Sekretaris
          </button>
          <button
            type="button"
            @click="fillCredentials('bendahara', 'bendahara123')"
            class="px-2 py-1.5 rounded-lg border border-amber-200 dark:border-amber-900 bg-amber-50/50 dark:bg-amber-950/30 hover:bg-amber-100 text-[11px] font-medium text-amber-700 dark:text-amber-300 transition text-center cursor-pointer"
          >
            💰 Bendahara
          </button>
          <button
            type="button"
            @click="fillCredentials('guru', 'guru123')"
            class="px-2 py-1.5 rounded-lg border border-emerald-200 dark:border-emerald-900 bg-emerald-50/50 dark:bg-emerald-950/30 hover:bg-emerald-100 text-[11px] font-medium text-emerald-700 dark:text-emerald-300 transition text-center cursor-pointer"
          >
            📖 Pengajar
          </button>
          <button
            type="button"
            @click="fillCredentials('1001', '1001')"
            class="px-2 py-1.5 rounded-lg border border-teal-200 dark:border-teal-900 bg-teal-50/50 dark:bg-teal-950/30 hover:bg-teal-100 text-[11px] font-medium text-teal-700 dark:text-teal-300 transition text-center cursor-pointer"
          >
            🎓 Santri
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../../composables/useAuth';
import { useToast } from '../../composables/useToast';

const router = useRouter();
const { login, loading, error } = useAuth();
const toast = useToast();

const username = ref('');
const password = ref('');
const showPassword = ref(false);

const fillCredentials = (u, p) => {
  username.value = u;
  password.value = p;
};

const handleSubmit = async () => {
  if (!username.value || !password.value) return;

  const result = await login(username.value, password.value);
  if (result.success) {
    toast.success('Selamat datang kembali!');
    router.push('/dashboard');
  }
};
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.animate-fadeIn {
  animation: fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
