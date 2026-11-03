<template>
  <div class="flex h-full flex-col bg-sidebar text-sidebar-foreground">
    <!-- Brand Header -->
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-sidebar-border px-5">
      <router-link to="/dashboard" class="flex items-center gap-3" @click="emit('navigate')">
        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground overflow-hidden">
          <img v-if="appLogo" :src="appLogo" alt="Logo" class="size-full object-cover" />
          <QrCode v-else class="size-5" />
        </div>
        <div class="leading-tight min-w-0">
          <p class="text-sm font-semibold tracking-tight truncate">{{ appName || 'Sistem Terpadu' }}</p>
          <p class="text-xs text-muted-foreground truncate">Presensi QR &amp; Notifikasi WA</p>
        </div>
      </router-link>
    </div>

    <!-- Navigation List (Dropdowns for Mobile & Desktop) -->
    <nav class="flex-1 space-y-1.5 overflow-y-auto px-3 py-3">
      <div v-for="section in visibleSections" :key="section.title || 'root'">
        <!-- Standalone Menu Item (e.g. Dashboard) -->
        <template v-if="!section.title">
          <div class="space-y-0.5 pb-1">
            <router-link
              v-for="item in section.items"
              :key="item.to"
              :to="item.to"
              class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors"
              :class="isActive(item.to)
                ? 'bg-primary/10 font-medium text-primary'
                : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'"
              @click="emit('navigate')"
            >
              <component :is="item.icon" class="size-4 shrink-0" />
              <span class="truncate">{{ item.label }}</span>
            </router-link>
          </div>
        </template>

        <!-- Dropdown Accordion Section -->
        <template v-else>
          <div class="space-y-0.5">
            <!-- Dropdown Trigger Button -->
            <button
              type="button"
              class="w-full flex items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wider transition-colors cursor-pointer select-none"
              :class="isSectionActive(section)
                ? 'text-primary bg-primary/5'
                : 'text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'"
              :aria-expanded="isSectionOpen(section.title)"
              @click="toggleSection(section.title)"
            >
              <div class="flex items-center gap-2.5 min-w-0">
                <component :is="section.icon" class="size-3.5 shrink-0" />
                <span class="truncate">{{ section.title }}</span>
                <span
                  v-if="isSectionActive(section)"
                  class="size-1.5 rounded-full bg-primary shrink-0"
                ></span>
              </div>
              <ChevronDown
                class="size-3.5 shrink-0 transition-transform duration-200 text-muted-foreground/70"
                :class="{ 'rotate-180': isSectionOpen(section.title) }"
              />
            </button>

            <!-- Dropdown Menu Items -->
            <div
              v-show="isSectionOpen(section.title)"
              class="space-y-0.5 pl-3 ml-3 border-l border-sidebar-border/70 py-0.5 animate-in fade-in slide-in-from-top-1 duration-150"
            >
              <router-link
                v-for="item in section.items"
                :key="item.to"
                :to="item.to"
                class="flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-xs transition-colors"
                :class="isActive(item.to)
                  ? 'bg-primary/10 font-medium text-primary'
                  : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'"
                @click="emit('navigate')"
              >
                <component :is="item.icon" class="size-3.5 shrink-0" />
                <span class="truncate">{{ item.label }}</span>
              </router-link>
            </div>
          </div>
        </template>
      </div>
    </nav>

    <!-- Tombol Install PWA di Sidebar -->
    <div v-if="isInstallable && !isInstalled" class="shrink-0 px-3 pb-2">
      <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-2.5 space-y-2">
        <div class="flex items-center gap-2">
          <div class="size-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
            <Download class="size-4" />
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-foreground truncate">Aplikasi Presensi</p>
            <p class="text-[10px] text-muted-foreground truncate">Pasang di Layar Utama</p>
          </div>
        </div>
        <button
          type="button"
          class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1.5 text-xs font-medium shadow-2xs transition-colors cursor-pointer"
          @click="installApp"
        >
          <Download class="size-3.5" />
          <span>Install Aplikasi</span>
        </button>
      </div>
    </div>

    <!-- User Profile Footer -->
    <div class="shrink-0 border-t border-sidebar-border p-3">
      <div class="flex items-center gap-3 rounded-lg px-2 py-1.5">
        <Avatar class="size-8">
          <AvatarFallback class="bg-primary/10 text-xs font-semibold text-primary">
            {{ userInitial }}
          </AvatarFallback>
        </Avatar>
        <div class="min-w-0 flex-1 leading-tight">
          <p class="truncate text-sm font-medium">{{ user?.name || 'User' }}</p>
          <p class="truncate text-xs text-muted-foreground">
            {{ roleLabel }}<template v-if="user?.nama_kelas"> &bull; Wali {{ user.nama_kelas }}</template>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  Activity,
  Bot,
  CalendarDays,
  ChevronDown,
  Download,
  FileText,
  GraduationCap,
  History,
  Layers,
  LayoutDashboard,
  MessageSquare,
  MessageSquareShare,
  QrCode,
  ReceiptText,
  ScanLine,
  Send,
  Settings,
  TableProperties,
  UserCog,
  Wallet,
} from '@lucide/vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useAuth } from '@/composables/useAuth';
import { useAppConfig } from '@/composables/useAppConfig';
import { usePwa } from '@/composables/usePwa';

const emit = defineEmits(['navigate']);

const route = useRoute();
const router = useRouter();
const { user, role, roleLabel } = useAuth();
const { appName, appLogo } = useAppConfig();
const { isInstallable, isInstalled, installApp } = usePwa();

const openSections = ref({});

/**
 * Menu visibility follows the `meta.roles` of each route, so the sidebar
 * only shows pages the router guard will actually let the user open.
 */
const sections = [
  {
    title: null,
    items: [{ to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard }],
  },
  {
    title: 'Presensi',
    icon: ScanLine,
    items: [
      { to: '/absensi/scanner', label: 'Scanner', icon: ScanLine },
      { to: '/absensi/monitoring', label: 'Monitoring', icon: Activity },
      { to: '/absensi/rekap', label: 'Rekap', icon: TableProperties },
      { to: '/absensi/agenda-libur', label: 'Agenda & Libur', icon: CalendarDays },
    ],
  },
  {
    title: 'Keuangan SPP',
    icon: Wallet,
    items: [
      { to: '/spp/input-cepat', label: 'Input Pembayaran', icon: Wallet },
      { to: '/spp/tagihan', label: 'Data Tagihan', icon: FileText },
      { to: '/spp/riwayat', label: 'Riwayat Transaksi', icon: ReceiptText },
      { to: '/spp/pengingat-wa', label: 'Himbauan WA', icon: MessageSquareShare },
    ],
  },
  {
    title: 'Data Master',
    icon: Layers,
    items: [
      { to: '/absensi/siswa', label: 'Data Santri', icon: GraduationCap },
      { to: '/guru', label: 'Data Guru', icon: UserCog },
      { to: '/kelas', label: 'Data Jilid', icon: Layers },
    ],
  },
  {
    title: 'WhatsApp',
    icon: MessageSquare,
    items: [
      { to: '/wa/delivery-log', label: 'Delivery Log', icon: Send },
      { to: '/chatbot', label: 'Chatbot', icon: Bot },
    ],
  },
  {
    title: 'Sistem',
    icon: Settings,
    items: [
      { to: '/log-aktivitas', label: 'Log Aktivitas', icon: History },
      { to: '/pengaturan', label: 'Pengaturan', icon: Settings },
    ],
  },
];

const canOpen = (path) => {
  const allowedRoles = router.resolve(path).meta.roles;
  return !allowedRoles?.length || allowedRoles.includes(role.value);
};

const visibleSections = computed(() =>
  sections
    .map((section) => ({ ...section, items: section.items.filter((item) => canOpen(item.to)) }))
    .filter((section) => section.items.length > 0),
);

const userInitial = computed(() => user.value?.name?.charAt(0)?.toUpperCase() || 'U');

const isActive = (path) => route.path === path || route.path.startsWith(path + '/');

function isSectionOpen(title) {
  return !!openSections.value[title];
}

function toggleSection(title) {
  openSections.value[title] = !openSections.value[title];
}

function isSectionActive(section) {
  if (!section.items) return false;
  return section.items.some(item => isActive(item.to));
}

function syncActiveSection() {
  for (const section of sections) {
    if (section.title && isSectionActive(section)) {
      openSections.value[section.title] = true;
    }
  }
}

watch(
  () => route.path,
  () => {
    syncActiveSection();
  },
  { immediate: true },
);
</script>
