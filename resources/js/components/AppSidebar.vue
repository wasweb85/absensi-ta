<template>
  <div class="flex h-full flex-col bg-sidebar text-sidebar-foreground">
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-sidebar-border px-5">
      <router-link to="/dashboard" class="flex items-center gap-3" @click="emit('navigate')">
        <div class="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
          <QrCode class="size-5" />
        </div>
        <div class="leading-tight">
          <p class="text-sm font-semibold tracking-tight">Sistem Terpadu</p>
          <p class="text-xs text-muted-foreground">Presensi QR &amp; Notifikasi WA</p>
        </div>
      </router-link>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
      <div v-for="section in visibleSections" :key="section.title">
        <p v-if="section.title" class="mb-1.5 px-3 text-xs font-medium text-muted-foreground">
          {{ section.title }}
        </p>
        <div class="space-y-0.5">
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
      </div>
    </nav>

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
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  Activity,
  Bot,
  CalendarDays,
  GraduationCap,
  Layers,
  LayoutDashboard,
  QrCode,
  ScanLine,
  Send,
  Settings,
  TableProperties,
  UserCog,
} from '@lucide/vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useAuth } from '@/composables/useAuth';

const emit = defineEmits(['navigate']);

const route = useRoute();
const router = useRouter();
const { user, role, roleLabel } = useAuth();

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
    items: [
      { to: '/absensi/scanner', label: 'Scanner', icon: ScanLine },
      { to: '/absensi/monitoring', label: 'Monitoring', icon: Activity },
      { to: '/absensi/rekap', label: 'Rekap', icon: TableProperties },
      { to: '/absensi/agenda-libur', label: 'Agenda & Libur', icon: CalendarDays },
    ],
  },
  {
    title: 'Data Master',
    items: [
      { to: '/absensi/siswa', label: 'Data Santri', icon: GraduationCap },
      { to: '/guru', label: 'Data Guru', icon: UserCog },
      { to: '/kelas', label: 'Data Jilid', icon: Layers },
    ],
  },
  {
    title: 'WhatsApp',
    items: [
      { to: '/wa/delivery-log', label: 'Delivery Log', icon: Send },
      { to: '/chatbot', label: 'Chatbot', icon: Bot },
    ],
  },
  {
    title: 'Sistem',
    items: [
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
</script>

