<template>
  <div class="fixed top-5 right-5 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none">
    <transition-group name="toast">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-center justify-between p-4 rounded-xl shadow-lg border text-sm backdrop-blur-md transition-all duration-300"
        :class="{
          'bg-emerald-50 dark:bg-emerald-950/80 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200': toast.type === 'success',
          'bg-rose-50 dark:bg-rose-950/80 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200': toast.type === 'error',
          'bg-amber-50 dark:bg-amber-950/80 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200': toast.type === 'warning',
          'bg-blue-50 dark:bg-blue-950/80 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200': toast.type === 'info',
        }"
      >
        <div class="flex items-center gap-3">
          <span v-if="toast.type === 'success'" class="text-base">✓</span>
          <span v-else-if="toast.type === 'error'" class="text-base">✕</span>
          <span v-else-if="toast.type === 'warning'" class="text-base">⚠</span>
          <span v-else class="text-base">ℹ</span>
          <span class="font-medium leading-snug">{{ toast.message }}</span>
        </div>
        <button
          @click="remove(toast.id)"
          class="ml-3 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition font-bold"
        >
          &times;
        </button>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { useToast } from '../composables/useToast';

const { toasts, remove } = useToast();
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateY(-20px) scale(0.95);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(100px);
}
</style>
