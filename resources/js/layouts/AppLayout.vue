<template>
  <div class="min-h-screen bg-muted/40 font-sans text-foreground antialiased">
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-sidebar-border lg:block">
      <AppSidebar />
    </aside>

    <Sheet v-model:open="mobileMenuOpen">
      <SheetContent side="left" class="w-72 gap-0 p-0 sm:max-w-72">
        <SheetTitle class="sr-only">Menu navigasi</SheetTitle>
        <SheetDescription class="sr-only">Daftar halaman aplikasi</SheetDescription>
        <AppSidebar @navigate="mobileMenuOpen = false" />
      </SheetContent>
    </Sheet>

    <div class="flex min-h-screen min-w-0 flex-col lg:pl-64">
      <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b bg-background/80 px-4 backdrop-blur-md sm:px-6">
        <Button variant="ghost" size="icon" class="lg:hidden" aria-label="Buka menu" @click="mobileMenuOpen = true">
          <Menu />
        </Button>

        <div class="min-w-0 flex-1">
          <p class="flex items-center gap-1.5 text-xs text-muted-foreground tabular-nums">
            <Clock class="size-3.5" />
            <span>{{ serverClock }} WIB</span>
          </p>
        </div>

        <Button
          variant="ghost"
          size="icon"
          :aria-label="isDark ? 'Ganti ke tema terang' : 'Ganti ke tema gelap'"
          title="Ganti Tema Gelap/Terang"
          @click="toggleDarkMode"
        >
          <Sun v-if="isDark" />
          <Moon v-else />
        </Button>

        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" class="h-10 gap-2 px-2" aria-label="Menu pengguna">
              <Avatar class="size-8">
                <AvatarFallback class="bg-primary/10 text-xs font-semibold text-primary">
                  {{ userInitial }}
                </AvatarFallback>
              </Avatar>
              <span class="hidden max-w-40 truncate text-sm font-medium md:inline">{{ user?.name }}</span>
              <ChevronsUpDown class="hidden text-muted-foreground md:block" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuLabel class="font-normal">
              <p class="truncate text-sm font-medium text-foreground">{{ user?.name || 'User' }}</p>
              <p class="truncate text-xs text-muted-foreground">
                {{ roleLabel }}<template v-if="user?.nama_kelas"> &bull; Wali {{ user.nama_kelas }}</template>
              </p>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem variant="destructive" @select="logout">
              <LogOut />
              Keluar (Logout)
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </header>

      <main class="flex-1 overflow-y-auto px-4 pt-2.5 pb-6 sm:px-6 lg:px-8">
        <router-view />
      </main>
    </div>

    <ToastContainer />
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { ChevronsUpDown, Clock, LogOut, Menu, Moon, Sun } from '@lucide/vue';
import AppSidebar from '@/components/AppSidebar.vue';
import ToastContainer from '@/components/ToastContainer.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { useAuth } from '@/composables/useAuth';

const { user, roleLabel, logout } = useAuth();

const mobileMenuOpen = ref(false);
const isDark = ref(false);
const serverClock = ref('');
let clockInterval = null;

const userInitial = computed(() => user.value?.name?.charAt(0)?.toUpperCase() || 'U');

const toggleDarkMode = () => {
  isDark.value = !isDark.value;
  document.documentElement.classList.toggle('dark', isDark.value);
  localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};

const updateClock = () => {
  serverClock.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

onMounted(() => {
  const savedTheme = localStorage.getItem('theme');
  isDark.value = savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.classList.toggle('dark', isDark.value);

  updateClock();
  clockInterval = setInterval(updateClock, 1000);
});

onUnmounted(() => {
  if (clockInterval) {
    clearInterval(clockInterval);
  }
});
</script>
