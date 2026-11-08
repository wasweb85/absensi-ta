<template>
  <Dialog
    :open="isOpen"
    @update:open="(val) => { if (!val) handleCancel(); }"
  >
    <DialogContent class="sm:max-w-md p-5 rounded-2xl border bg-card shadow-2xl backdrop-blur-xl">
      <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 pt-1">
        <!-- Icon Container with Animated Badge -->
        <div
          class="size-12 rounded-2xl flex items-center justify-center shrink-0 transition-transform duration-200"
          :class="{
            'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 ring-4 ring-rose-500/5': variant === 'destructive',
            'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 ring-4 ring-amber-500/5': variant === 'warning',
            'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 ring-4 ring-blue-500/5': icon === 'refresh',
            'bg-primary/10 text-primary border border-primary/20 ring-4 ring-primary/5': variant !== 'destructive' && variant !== 'warning' && icon !== 'refresh',
          }"
        >
          <Trash2 v-if="icon === 'trash'" class="size-6" />
          <AlertTriangle v-else-if="icon === 'alert' || variant === 'warning'" class="size-6" />
          <RefreshCw v-else-if="icon === 'refresh'" class="size-6" />
          <CheckCircle2 v-else-if="icon === 'check'" class="size-6" />
          <Info v-else class="size-6" />
        </div>

        <!-- Title & Description -->
        <div class="space-y-1.5 flex-1 text-center sm:text-left min-w-0">
          <DialogTitle class="text-base sm:text-lg font-semibold text-foreground tracking-tight leading-snug">
            {{ title }}
          </DialogTitle>
          <DialogDescription class="text-xs sm:text-sm text-muted-foreground leading-relaxed whitespace-pre-line">
            {{ description }}
          </DialogDescription>
        </div>
      </div>

      <DialogFooter class="pt-4 mt-2 border-t border-border/50 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
        <Button
          v-if="!isAlert"
          type="button"
          variant="outline"
          size="sm"
          class="h-9 px-4 text-xs font-medium cursor-pointer"
          @click="handleCancel"
        >
          {{ cancelText }}
        </Button>

        <Button
          type="button"
          :variant="variant === 'destructive' ? 'destructive' : 'default'"
          size="sm"
          class="h-9 px-4 text-xs font-medium gap-1.5 cursor-pointer shadow-sm"
          :class="{
            'bg-rose-600 hover:bg-rose-700 text-white': variant === 'destructive',
          }"
          @click="handleConfirm"
        >
          <Trash2 v-if="icon === 'trash' && !isAlert" class="size-3.5" />
          <RefreshCw v-else-if="icon === 'refresh' && !isAlert" class="size-3.5" />
          <span>{{ confirmText }}</span>
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup>
import {
  AlertTriangle,
  CheckCircle2,
  Info,
  RefreshCw,
  Trash2,
} from '@lucide/vue';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';

const {
  isOpen,
  title,
  description,
  confirmText,
  cancelText,
  variant,
  icon,
  isAlert,
  handleConfirm,
  handleCancel,
} = useConfirm();
</script>

