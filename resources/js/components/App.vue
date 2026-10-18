<template>
  <div class="w-full max-w-xl mx-auto bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md rounded-2xl shadow-xl border border-zinc-200/80 dark:border-zinc-800 p-8 transition-all duration-300">
    <!-- Header -->
    <div class="flex items-center gap-4 mb-6">
      <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center shadow-inner">
        <!-- Vue Logo SVG -->
        <svg class="w-8 h-8" viewBox="0 0 128 128" width="32" height="32">
          <path fill="#42b883" d="M78.8,10L64,35.4L49.2,10H0l64,110l64-110H78.8z"/>
          <path fill="#35495e" d="M78.8,10L64,35.4L49.2,10H25.6L64,76l38.4-66H78.8z"/>
        </svg>
      </div>
      <div>
        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300 mb-1">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          Vue 3 Terpasang & Berjalan
        </div>
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
          Laravel + Vue 3
        </h2>
      </div>
    </div>

    <p class="text-zinc-600 dark:text-zinc-300 text-sm leading-relaxed mb-6">
      Vue 3 Composition API berhasil terintegrasi dengan Laravel 13, Vite, dan Tailwind CSS.
    </p>

    <!-- Interactive Section -->
    <div class="space-y-4 mb-6">
      <!-- Reactive Input -->
      <div>
        <label for="nama" class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">Uji Reaktivitas (v-model)</label>
        <input
          id="nama"
          v-model="name"
          type="text"
          placeholder="Ketik nama Anda..."
          class="w-full px-4 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all text-sm"
        />
      </div>

      <!-- Preview -->
      <div v-if="name.trim()" class="p-4 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-300 text-sm animate-fadeIn">
        Halo, <span class="font-bold">{{ name }}</span>! Selamat datang di aplikasi Vue 3 Anda 🎉
      </div>

      <!-- Counter Demo -->
      <div class="flex items-center justify-between p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-800">
        <div>
          <span class="block text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Reactive State</span>
          <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Nilai Hitungan: {{ count }}</span>
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="count > 0 ? count-- : null"
            class="w-10 h-10 rounded-xl bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-600 flex items-center justify-center font-bold text-lg active:scale-95 transition"
            title="Kurang"
          >
            -
          </button>
          <button
            @click="count++"
            class="px-4 h-10 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white font-medium text-sm flex items-center gap-1 shadow-md shadow-emerald-600/20 transition"
            title="Tambah"
          >
            <span>+ Tambah</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Tech Stack Pills -->
    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800/80 flex flex-wrap items-center justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400">
      <span>Fitur siap pakai:</span>
      <div class="flex items-center gap-2 flex-wrap">
        <span class="px-2.5 py-1 rounded-md bg-zinc-100 dark:bg-zinc-800 font-mono text-[11px] text-zinc-700 dark:text-zinc-300">Vue v3.5</span>
        <span class="px-2.5 py-1 rounded-md bg-zinc-100 dark:bg-zinc-800 font-mono text-[11px] text-zinc-700 dark:text-zinc-300">Vite v8</span>
        <span class="px-2.5 py-1 rounded-md bg-zinc-100 dark:bg-zinc-800 font-mono text-[11px] text-zinc-700 dark:text-zinc-300">Tailwind v4</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const name = ref('');
const count = ref(0);
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
  animation: fadeIn 0.2s ease-out forwards;
}
</style>
