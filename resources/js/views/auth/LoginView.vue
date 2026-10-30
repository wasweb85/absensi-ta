<template>
  <div class="flex min-h-screen w-full items-center justify-center bg-muted/40 p-4">
    <div class="w-full max-w-sm">
      <div class="mb-6 flex flex-col items-center text-center">
        <div class="mb-3 flex size-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground overflow-hidden shadow-sm">
          <img v-if="appLogo" :src="appLogo" alt="Logo" class="size-full object-contain p-1" />
          <QrCode v-else class="size-7" />
        </div>
        <h1 class="text-xl font-semibold tracking-tight">{{ appName || 'Sistem Terpadu' }}</h1>
        <p class="text-sm text-muted-foreground">Presensi QR &amp; Notifikasi WhatsApp Santri</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Masuk ke Sistem</CardTitle>
          <CardDescription>Gunakan username atau NISN dan kata sandi Anda.</CardDescription>
        </CardHeader>

        <CardContent>
          <div
            v-if="error"
            role="alert"
            class="mb-4 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2.5 text-sm text-destructive"
          >
            <CircleAlert class="mt-0.5 size-4 shrink-0" />
            <span>{{ error }}</span>
          </div>

          <form class="space-y-4" @submit.prevent="handleSubmit">
            <div class="space-y-2">
              <Label for="username">Username / NISN</Label>
              <Input
                id="username"
                v-model="username"
                type="text"
                required
                autocomplete="username"
                placeholder="Masukkan username atau NISN"
                class="h-10"
              />
            </div>

            <div class="space-y-2">
              <Label for="password">Password</Label>
              <div class="relative">
                <Input
                  id="password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  required
                  autocomplete="current-password"
                  placeholder="Masukkan kata sandi"
                  class="h-10 pr-10"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  class="absolute top-1/2 right-1.5 -translate-y-1/2 text-muted-foreground"
                  :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Lihat kata sandi'"
                  @click="showPassword = !showPassword"
                >
                  <EyeOff v-if="showPassword" />
                  <Eye v-else />
                </Button>
              </div>
            </div>

            <Button type="submit" class="h-10 w-full" :disabled="loading">
              <LoaderCircle v-if="loading" class="animate-spin" />
              <LogIn v-else />
              {{ loading ? 'Memproses...' : 'Masuk ke Sistem' }}
            </Button>
          </form>
        </CardContent>
      </Card>

      <!-- Demo Quick Fill Buttons (Sangat berguna untuk presentasi dan pengujian skripsi) -->
      <div class="mt-6">
        <p class="mb-2 text-center text-xs text-muted-foreground">Akses Cepat Pengujian Role (Demo 1-Klik)</p>
        <div class="grid grid-cols-3 gap-2">
          <Button
            v-for="account in demoAccounts"
            :key="account.username"
            type="button"
            variant="outline"
            size="sm"
            @click="fillCredentials(account.username, account.password)"
          >
            {{ account.label }}
          </Button>
        </div>
      </div>

      <!-- Tombol Install PWA di Halaman Login -->
      <div v-if="isInstallable && !isInstalled" class="mt-4 text-center">
        <Button
          type="button"
          variant="outline"
          size="sm"
          class="h-8 gap-1.5 text-xs border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-500/20 shadow-2xs"
          @click="installApp"
        >
          <Download class="size-3.5" />
          <span>Install Aplikasi Presensi (PWA)</span>
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { CircleAlert, Download, Eye, EyeOff, LoaderCircle, LogIn, QrCode } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';
import { useAppConfig } from '@/composables/useAppConfig';
import { usePwa } from '@/composables/usePwa';

const router = useRouter();
const { login, loading, error } = useAuth();
const toast = useToast();
const { appName, appLogo, loadAppConfig } = useAppConfig();
const { isInstallable, isInstalled, installApp } = usePwa();

onMounted(() => {
  loadAppConfig();
});

const username = ref('');
const password = ref('');
const showPassword = ref(false);

const demoAccounts = [
  { label: 'Admin', username: 'admin', password: 'admin123' },
  { label: 'Kepala TPQ', username: 'kepala', password: 'kepala123' },
  { label: 'Sekretaris', username: 'sekretaris', password: 'sekretaris123' },
  { label: 'Bendahara', username: 'bendahara', password: 'bendahara123' },
  { label: 'Pengajar', username: 'guru', password: 'guru123' },
  { label: 'Santri', username: '1001', password: '1001' },
];

const fillCredentials = (u, p) => {
  username.value = u;
  password.value = p;
};

const handleSubmit = async () => {
  if (!username.value || !password.value) {
    return;
  }

  const result = await login(username.value, password.value);
  if (result.success) {
    toast.success('Selamat datang kembali!');
    router.push('/dashboard');
  }
};
</script>
