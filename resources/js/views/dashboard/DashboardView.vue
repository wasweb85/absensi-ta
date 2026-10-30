<template>
  <div class="space-y-6">
    <!-- Header Dashboard -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between pb-2 border-b">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-lg font-semibold tracking-tight text-foreground">Dashboard</h2>
          <Badge variant="outline" class="font-normal text-muted-foreground text-xs">
            {{ currentDateFormatted }}
          </Badge>
        </div>
        <p class="text-xs text-muted-foreground mt-0.5">
          Monitoring aktivitas presensi santri, data ustadz/ustadzah per jilid, dan layanan notifikasi secara realtime.
        </p>
      </div>
    </div>

    <!-- Skeleton Loading State -->
    <div v-if="isLoading && !hasDataLoaded" class="space-y-6">
      <div v-if="isGuru" class="grid grid-cols-4 sm:grid-cols-4 lg:grid-cols-7 gap-1.5 sm:gap-3">
        <Skeleton v-for="i in 7" :key="i" class="h-16 sm:h-20 w-full rounded-xl" />
      </div>
      <div v-else class="grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4">
        <Skeleton v-for="i in 4" :key="i" class="h-24 sm:h-28 w-full rounded-xl" />
      </div>
      <div class="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-12">
        <Skeleton class="h-72 lg:col-span-5 rounded-xl" />
        <Skeleton class="h-72 lg:col-span-7 rounded-xl" />
      </div>
      <Skeleton class="h-32 w-full rounded-xl" />
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-4 sm:space-y-6">
      <!-- 1. JIKA ROLE GURU BIASA: 7 Stat Metrics Cards Jilid Binaan (Menggantikan Card Presensi Jilid & Izin/Sakit/Alpa) -->
      <div v-if="isGuru" class="grid grid-cols-4 sm:grid-cols-4 lg:grid-cols-7 gap-1.5 sm:gap-3">
        <router-link to="/absensi/monitoring" class="block outline-none">
          <Card class="p-2 sm:p-3 text-center transition-all hover:border-primary/50 shadow-2xs h-full flex flex-col justify-center">
            <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-muted-foreground block truncate">Total</span>
            <span class="text-base sm:text-xl font-bold tracking-tight text-foreground">{{ guruClassStats.total }}</span>
          </Card>
        </router-link>
        
        <router-link to="/absensi/monitoring" class="block outline-none">
          <Card class="p-2 sm:p-3 text-center border-emerald-500/20 bg-emerald-500/5 transition-all hover:border-emerald-500/50 shadow-2xs h-full flex flex-col justify-center">
            <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block truncate">Hadir</span>
            <span class="text-base sm:text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ guruClassStats.hadir }}</span>
          </Card>
        </router-link>

        <router-link to="/absensi/monitoring" class="block outline-none">
          <Card class="p-2 sm:p-3 text-center border-amber-500/20 bg-amber-500/5 transition-all hover:border-amber-500/50 shadow-2xs h-full flex flex-col justify-center">
            <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400 block truncate">Telat</span>
            <span class="text-base sm:text-xl font-bold tracking-tight text-amber-600 dark:text-amber-400">{{ guruClassStats.terlambat }}</span>
          </Card>
        </router-link>

        <router-link to="/absensi/monitoring" class="block outline-none">
          <Card class="p-2 sm:p-3 text-center border-blue-500/20 bg-blue-500/5 transition-all hover:border-blue-500/50 shadow-2xs h-full flex flex-col justify-center">
            <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400 block truncate">Sakit</span>
            <span class="text-base sm:text-xl font-bold tracking-tight text-blue-600 dark:text-blue-400">{{ guruClassStats.sakit }}</span>
          </Card>
        </router-link>

        <router-link to="/absensi/monitoring" class="block outline-none">
          <Card class="p-2 sm:p-3 text-center border-indigo-500/20 bg-indigo-500/5 transition-all hover:border-indigo-500/50 shadow-2xs h-full flex flex-col justify-center">
            <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 block truncate">Izin</span>
            <span class="text-base sm:text-xl font-bold tracking-tight text-indigo-600 dark:text-indigo-400">{{ guruClassStats.izin }}</span>
          </Card>
        </router-link>

        <router-link to="/absensi/monitoring" class="block outline-none">
          <Card class="p-2 sm:p-3 text-center border-rose-500/20 bg-rose-500/5 transition-all hover:border-rose-500/50 shadow-2xs h-full flex flex-col justify-center">
            <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400 block truncate">Alpa</span>
            <span class="text-base sm:text-xl font-bold tracking-tight text-rose-600 dark:text-rose-400">{{ guruClassStats.alpa }}</span>
          </Card>
        </router-link>

        <router-link to="/absensi/monitoring" class="block outline-none col-span-2 sm:col-span-1">
          <Card class="p-2 sm:p-3 text-center bg-muted/40 transition-all hover:border-primary/50 shadow-2xs h-full flex flex-col justify-center">
            <span class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-muted-foreground block truncate">Belum Absen</span>
            <span class="text-base sm:text-xl font-bold tracking-tight text-foreground">{{ guruClassStats.belum_absen }}</span>
          </Card>
        </router-link>
      </div>

      <!-- 2. JIKA BUKAN GURU (Admin, Kepala TPQ): Top Metric Cards Biasa -->
      <div
        v-else
        class="grid grid-cols-2 gap-2.5 sm:gap-4"
        :class="topGridColsClass"
      >
        <!-- Card 1: Presensi Total (Hadir + Terlambat) -->
        <router-link to="/absensi/monitoring" class="block group outline-none">
          <Card class="relative overflow-hidden transition-all group-hover:border-emerald-500/50 group-hover:shadow-xs h-full">
            <CardContent class="p-3 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
              <div class="flex size-8 sm:size-11 shrink-0 items-center justify-center rounded-lg sm:rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 transition-transform group-hover:scale-105">
                <CheckCircle2 class="size-4 sm:size-5" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase tracking-wider truncate">
                  Kehadiran Hari Ini
                </p>
                <div class="flex items-baseline gap-1 mt-0.5">
                  <span class="text-lg sm:text-2xl font-bold tracking-tight text-foreground">
                    {{ absensiStats.hadir + absensiStats.terlambat }}
                  </span>
                  <span class="text-[10px] sm:text-xs text-muted-foreground">
                    / {{ absensiStats.total }} santri
                  </span>
                </div>
                <p class="text-[10px] sm:text-xs text-muted-foreground mt-0.5 sm:mt-1 truncate">
                  Hadir: <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ absensiStats.hadir }}</span>
                  &bull; Telat: <span class="font-medium text-amber-600 dark:text-amber-400">{{ absensiStats.terlambat }}</span>
                </p>
              </div>
            </CardContent>
          </Card>
        </router-link>

        <!-- Card 2: Izin, Sakit & Belum Hadir -->
        <router-link to="/absensi/monitoring" class="block group outline-none">
          <Card class="relative overflow-hidden transition-all group-hover:border-blue-500/50 group-hover:shadow-xs h-full">
            <CardContent class="p-3 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
              <div class="flex size-8 sm:size-11 shrink-0 items-center justify-center rounded-lg sm:rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 transition-transform group-hover:scale-105">
                <Clock class="size-4 sm:size-5" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase tracking-wider truncate">
                  Izin, Sakit &amp; Alpa
                </p>
                <div class="flex items-baseline gap-1 mt-0.5">
                  <span class="text-lg sm:text-2xl font-bold tracking-tight text-foreground">
                    {{ absensiStats.izin + absensiStats.sakit + absensiStats.alpa }}
                  </span>
                  <span class="text-[10px] sm:text-xs text-muted-foreground truncate">
                    ({{ absensiStats.belum_absen }} blm scan)
                  </span>
                </div>
                <p class="text-[10px] sm:text-xs text-muted-foreground mt-0.5 sm:mt-1 truncate">
                  I: {{ absensiStats.izin }} &bull; S: {{ absensiStats.sakit }} &bull; A: <span class="text-rose-500 font-medium">{{ absensiStats.alpa }}</span>
                </p>
              </div>
            </CardContent>
          </Card>
        </router-link>

        <!-- Card 3: Data Guru / Ustadz & Ustadzah (Tampil untuk Kepala TPQ & Admin) -->
        <router-link
          v-if="canViewGuru"
          to="/guru"
          class="block group outline-none"
        >
          <Card class="relative overflow-hidden transition-all group-hover:border-violet-500/50 group-hover:shadow-xs h-full">
            <CardContent class="p-3 sm:p-5 flex items-center justify-between gap-2">
              <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 min-w-0">
                <div class="flex size-8 sm:size-11 shrink-0 items-center justify-center rounded-lg sm:rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400 transition-transform group-hover:scale-105">
                  <UserCog class="size-4 sm:size-5" />
                </div>
                <div class="min-w-0">
                  <p class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase tracking-wider truncate">
                    Data Guru &amp; Jilid
                  </p>
                  <div class="flex items-baseline gap-1 mt-0.5">
                    <span class="text-lg sm:text-2xl font-bold tracking-tight text-foreground">
                      {{ guruStats.totalPengajar }}
                    </span>
                    <span class="text-[10px] sm:text-xs text-muted-foreground truncate">pengajar</span>
                  </div>
                  <p class="text-[10px] sm:text-xs text-muted-foreground mt-0.5 sm:mt-1 truncate">
                    Staf: <span class="font-medium text-foreground">{{ guruStats.totalStaff }}</span> &bull; <span class="font-medium text-violet-600 dark:text-violet-400">{{ kelasBreakdown.length }} Jilid</span>
                  </p>
                </div>
              </div>
              <ChevronRight class="hidden sm:block size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
            </CardContent>
          </Card>
        </router-link>

        <!-- Card 4: WhatsApp Delivery Log -->
        <router-link
          v-if="canViewWaLogs"
          to="/wa/delivery-log"
          class="block group outline-none"
        >
          <Card class="relative overflow-hidden transition-all group-hover:border-teal-500/50 group-hover:shadow-xs h-full">
            <CardContent class="p-3 sm:p-5 flex items-center justify-between gap-2">
              <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 min-w-0">
                <div class="flex size-8 sm:size-11 shrink-0 items-center justify-center rounded-lg sm:rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400 transition-transform group-hover:scale-105">
                  <Send class="size-4 sm:size-5" />
                </div>
                <div class="min-w-0">
                  <p class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase tracking-wider truncate">
                    Delivery Log WA
                  </p>
                  <div class="flex items-baseline gap-1 mt-0.5">
                    <span class="text-lg sm:text-2xl font-bold tracking-tight text-foreground">
                      {{ waStats.delivered + waStats.read }}
                    </span>
                    <span class="text-[10px] sm:text-xs text-muted-foreground truncate">/ {{ waStats.total }} sampai</span>
                  </div>
                  <p class="text-[10px] sm:text-xs text-muted-foreground mt-0.5 sm:mt-1 truncate">
                    Baca: <span class="font-medium text-teal-600 dark:text-teal-400">{{ waStats.read }}</span> &bull; Gagal: <span class="font-medium text-rose-500">{{ waStats.failed }}</span>
                  </p>
                </div>
              </div>
              <ChevronRight class="hidden sm:block size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
            </CardContent>
          </Card>
        </router-link>
      </div>

      <!-- Middle Section: Visualisasi Presensi & Fokus Kerja -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- 1. JIKA ROLE GURU -->
        <template v-if="isGuru">
          <!-- Col 1: Progress Presensi Jilid Binaan -->
          <Card class="lg:col-span-5 flex flex-col justify-between">
            <CardHeader class="pb-3">
              <div class="flex items-center justify-between">
                <div>
                  <CardTitle class="text-sm font-semibold flex items-center gap-2">
                    <Target class="size-4 text-primary" />
                    <span>Rasio Presensi {{ kelasBinaan || 'Jilid' }}</span>
                  </CardTitle>
                  <CardDescription class="text-xs mt-0.5">
                    Persentase kehadiran santri jilid binaan hari ini
                  </CardDescription>
                </div>
                <Badge variant="secondary" class="font-semibold text-xs">
                  {{ guruClassStats.total }} Santri
                </Badge>
              </div>
            </CardHeader>

            <CardContent class="space-y-4 pt-2">
              <div>
                <div class="flex justify-between text-xs font-medium mb-1.5">
                  <span class="text-emerald-600 dark:text-emerald-400">Hadir Tepat Waktu</span>
                  <span class="text-foreground">
                    {{ attendancePercentage(guruClassStats.hadir, guruClassStats.total) }}% ({{ guruClassStats.hadir }})
                  </span>
                </div>
                <div class="w-full h-2 rounded-full bg-muted overflow-hidden">
                  <div
                    class="h-full bg-emerald-500 rounded-full transition-all duration-500"
                    :style="{ width: `${attendancePercentage(guruClassStats.hadir, guruClassStats.total)}%` }"
                  ></div>
                </div>
              </div>

              <div>
                <div class="flex justify-between text-xs font-medium mb-1.5">
                  <span class="text-amber-600 dark:text-amber-400">Terlambat</span>
                  <span class="text-foreground">
                    {{ attendancePercentage(guruClassStats.terlambat, guruClassStats.total) }}% ({{ guruClassStats.terlambat }})
                  </span>
                </div>
                <div class="w-full h-2 rounded-full bg-muted overflow-hidden">
                  <div
                    class="h-full bg-amber-500 rounded-full transition-all duration-500"
                    :style="{ width: `${attendancePercentage(guruClassStats.terlambat, guruClassStats.total)}%` }"
                  ></div>
                </div>
              </div>

              <div>
                <div class="flex justify-between text-xs font-medium mb-1.5">
                  <span class="text-blue-600 dark:text-blue-400">Izin / Sakit</span>
                  <span class="text-foreground">
                    {{ attendancePercentage(guruClassStats.izin + guruClassStats.sakit, guruClassStats.total) }}% ({{ guruClassStats.izin + guruClassStats.sakit }})
                  </span>
                </div>
                <div class="w-full h-2 rounded-full bg-muted overflow-hidden">
                  <div
                    class="h-full bg-blue-500 rounded-full transition-all duration-500"
                    :style="{ width: `${attendancePercentage(guruClassStats.izin + guruClassStats.sakit, guruClassStats.total)}%` }"
                  ></div>
                </div>
              </div>

              <div>
                <div class="flex justify-between text-xs font-medium mb-1.5">
                  <span class="text-rose-600 dark:text-rose-400">Belum Presensi / Alpa</span>
                  <span class="text-foreground">
                    {{ attendancePercentage(guruClassStats.belum_absen + guruClassStats.alpa, guruClassStats.total) }}% ({{ guruClassStats.belum_absen + guruClassStats.alpa }})
                  </span>
                </div>
                <div class="w-full h-2 rounded-full bg-muted overflow-hidden">
                  <div
                    class="h-full bg-rose-500 rounded-full transition-all duration-500"
                    :style="{ width: `${attendancePercentage(guruClassStats.belum_absen + guruClassStats.alpa, guruClassStats.total)}%` }"
                  ></div>
                </div>
              </div>
            </CardContent>

            <CardFooter class="pt-4 border-t flex gap-2">
              <Button as-child variant="outline" size="sm" class="flex-1 text-xs">
                <router-link to="/absensi/monitoring" class="flex items-center justify-center gap-1.5">
                  <span>Live Monitoring</span>
                  <ChevronRight class="size-3.5" />
                </router-link>
              </Button>
              <Button as-child v-if="canScanQr" size="sm" class="px-4 text-xs">
                <router-link to="/absensi/scanner" class="flex items-center justify-center gap-1.5">
                  <ScanLine class="size-3.5" />
                  <span>Scan QR</span>
                </router-link>
              </Button>
            </CardFooter>
          </Card>

          <!-- Col 2: Daftar Santri Binaan Realtime -->
          <Card class="lg:col-span-7 flex flex-col justify-between">
            <div>
              <CardHeader class="pb-3 border-b">
                <div class="flex items-center justify-between">
                  <div>
                    <CardTitle class="text-sm font-semibold flex items-center gap-2">
                      <GraduationCap class="size-4 text-primary" />
                      <span>Presensi Santri {{ kelasBinaan || 'Jilid' }}</span>
                    </CardTitle>
                    <CardDescription class="text-xs mt-0.5">
                      Daftar status absensi realtime santri jilid hari ini
                    </CardDescription>
                  </div>
                  <Badge variant="outline" class="text-xs font-medium">
                    {{ guruClassStats.hadir + guruClassStats.terlambat }} / {{ guruClassStats.total }} Hadir
                  </Badge>
                </div>
              </CardHeader>

              <CardContent class="p-0">
                <div v-if="guruStudents.length > 0" class="divide-y max-h-80 overflow-y-auto px-4 py-1">
                  <div
                    v-for="s in guruStudents"
                    :key="s.id_siswa"
                    class="py-2.5 flex items-center justify-between gap-3 text-xs transition-colors hover:bg-muted/50 rounded-lg px-2"
                  >
                    <div class="min-w-0">
                      <p class="font-medium text-foreground truncate">{{ s.nama_lengkap }}</p>
                      <p class="text-[11px] text-muted-foreground">
                        NISN: {{ s.nisn }} &bull; {{ s.jenis_kelamin === 'L' ? 'L' : 'P' }}
                      </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <span v-if="s.waktu_masuk" class="text-[10px] text-muted-foreground font-mono">
                        {{ s.waktu_masuk }}
                      </span>
                      <Badge
                        :variant="getStatusBadgeVariant(s.status)"
                        class="text-[10px] font-medium"
                      >
                        {{ s.status }}
                      </Badge>
                    </div>
                  </div>
                </div>

                <!-- Empty State -->
                <div v-else class="py-12 text-center text-muted-foreground">
                  <Users class="size-8 mx-auto mb-2 text-muted-foreground/50" />
                  <p class="text-xs font-semibold text-foreground">Belum ada santri di jilid {{ kelasBinaan || 'ini' }}.</p>
                  <p class="text-[11px] text-muted-foreground mt-0.5">Plot santri dilakukan oleh Pengurus / Sekretaris.</p>
                </div>
              </CardContent>
            </div>

            <CardFooter class="pt-3 border-t flex justify-between items-center text-xs text-muted-foreground">
              <span>Ubah status absensi manual dapat dilakukan di Monitoring.</span>
              <router-link
                to="/absensi/monitoring"
                class="font-medium text-primary hover:underline flex items-center gap-1"
              >
                <span>Monitoring Lengkap</span>
                <ChevronRight class="size-3.5" />
              </router-link>
            </CardFooter>
          </Card>
        </template>

        <!-- 2. JIKA ROLE NON-GURU (Kepala TPQ, Admin, Sekretaris, Bendahara) -->
        <template v-else>
          <!-- Col 1: Progress Rasio Seluruh Santri -->
          <Card class="lg:col-span-5 flex flex-col justify-between">
            <CardHeader class="pb-3">
              <div class="flex items-center justify-between">
                <div>
                  <CardTitle class="text-sm font-semibold flex items-center gap-2">
                    <Target class="size-4 text-primary" />
                    <span>Rasio Presensi Seluruh Santri</span>
                  </CardTitle>
                  <CardDescription class="text-xs mt-0.5">
                    Distribusi kehadiran dari total {{ absensiStats.total }} santri aktif hari ini
                  </CardDescription>
                </div>
                <Badge variant="secondary" class="font-semibold text-xs">
                  {{ absensiStats.total }} Santri
                </Badge>
              </div>
            </CardHeader>

            <CardContent class="space-y-4 pt-1">
              <div>
                <div class="flex justify-between text-xs font-medium mb-1.5">
                  <span class="text-emerald-600 dark:text-emerald-400">Hadir Tepat Waktu</span>
                  <span class="text-foreground">
                    {{ attendancePercentage(absensiStats.hadir) }}% ({{ absensiStats.hadir }} santri)
                  </span>
                </div>
                <div class="w-full h-2 rounded-full bg-muted overflow-hidden">
                  <div
                    class="h-full bg-emerald-500 rounded-full transition-all duration-500"
                    :style="{ width: `${attendancePercentage(absensiStats.hadir)}%` }"
                  ></div>
                </div>
              </div>

              <div>
                <div class="flex justify-between text-xs font-medium mb-1.5">
                  <span class="text-amber-600 dark:text-amber-400">Terlambat</span>
                  <span class="text-foreground">
                    {{ attendancePercentage(absensiStats.terlambat) }}% ({{ absensiStats.terlambat }} santri)
                  </span>
                </div>
                <div class="w-full h-2 rounded-full bg-muted overflow-hidden">
                  <div
                    class="h-full bg-amber-500 rounded-full transition-all duration-500"
                    :style="{ width: `${attendancePercentage(absensiStats.terlambat)}%` }"
                  ></div>
                </div>
              </div>

              <div>
                <div class="flex justify-between text-xs font-medium mb-1.5">
                  <span class="text-blue-600 dark:text-blue-400">Izin / Sakit</span>
                  <span class="text-foreground">
                    {{ attendancePercentage(absensiStats.izin + absensiStats.sakit) }}% ({{ absensiStats.izin + absensiStats.sakit }} santri)
                  </span>
                </div>
                <div class="w-full h-2 rounded-full bg-muted overflow-hidden">
                  <div
                    class="h-full bg-blue-500 rounded-full transition-all duration-500"
                    :style="{ width: `${attendancePercentage(absensiStats.izin + absensiStats.sakit)}%` }"
                  ></div>
                </div>
              </div>

              <div>
                <div class="flex justify-between text-xs font-medium mb-1.5">
                  <span class="text-rose-600 dark:text-rose-400">Belum Presensi / Alpa</span>
                  <span class="text-foreground">
                    {{ attendancePercentage(absensiStats.alpa + absensiStats.belum_absen) }}% ({{ absensiStats.alpa + absensiStats.belum_absen }} santri)
                  </span>
                </div>
                <div class="w-full h-2 rounded-full bg-muted overflow-hidden">
                  <div
                    class="h-full bg-rose-500 rounded-full transition-all duration-500"
                    :style="{ width: `${attendancePercentage(absensiStats.alpa + absensiStats.belum_absen)}%` }"
                  ></div>
                </div>
              </div>
            </CardContent>

            <CardFooter class="pt-4 border-t flex gap-2">
              <Button as-child variant="outline" size="sm" class="flex-1 text-xs">
                <router-link to="/absensi/monitoring" class="flex items-center justify-center gap-1.5">
                  <span>Buka Monitoring Realtime</span>
                  <ChevronRight class="size-3.5" />
                </router-link>
              </Button>
              <Button as-child v-if="canScanQr" size="sm" class="px-4 text-xs">
                <router-link to="/absensi/scanner" class="flex items-center justify-center gap-1.5">
                  <ScanLine class="size-3.5" />
                  <span>Scan QR</span>
                </router-link>
              </Button>
            </CardFooter>
          </Card>

          <!-- Col 2: Monitoring Aktivitas Presensi Per Jilid & Ustadz/Ustadzah -->
          <Card class="lg:col-span-7 flex flex-col justify-between">
            <div>
              <CardHeader class="pb-3 border-b">
                <div class="flex items-center justify-between gap-2">
                  <div>
                    <CardTitle class="text-sm font-semibold flex items-center gap-2">
                      <Layers class="size-4 text-primary" />
                      <span>Monitoring Presensi Per Jilid &amp; Wali Jilid</span>
                    </CardTitle>
                    <CardDescription class="text-xs mt-0.5">
                      Rekapitulasi aktivitas kehadiran santri hari ini di setiap jilid beserta ustadz/ustadzah pengampu
                    </CardDescription>
                  </div>
                  <Button v-if="canViewGuru" as-child variant="outline" size="sm" class="h-7 px-2.5 text-xs shrink-0">
                    <router-link to="/guru" class="flex items-center gap-1">
                      <UserCog class="size-3.5" />
                      <span>Data Guru</span>
                    </router-link>
                  </Button>
                </div>
              </CardHeader>

              <CardContent class="p-3 sm:p-4">
                <div v-if="kelasBreakdown.length > 0" class="grid grid-cols-2 gap-2.5 max-h-80 overflow-y-auto pr-0.5">
                  <div
                    v-for="item in kelasBreakdown"
                    :key="item.id"
                    class="p-2.5 sm:p-3 rounded-xl border bg-card/60 hover:bg-muted/40 transition-colors flex flex-col justify-between gap-2 text-xs"
                  >
                    <div class="min-w-0">
                      <div class="flex items-center justify-between gap-1">
                        <div class="flex items-center gap-1.5 min-w-0">
                          <span
                            class="size-2 rounded-full shrink-0"
                            :style="{ backgroundColor: item.warna_label || '#10b981' }"
                          ></span>
                          <span class="font-semibold text-foreground text-xs truncate">{{ item.nama_kelas }}</span>
                        </div>
                        <Badge variant="outline" class="text-[9px] px-1.5 py-0 font-normal shrink-0">
                          {{ item.total }}
                        </Badge>
                      </div>
                      <p class="text-[10px] text-muted-foreground mt-0.5 truncate" :title="item.wali_kelas || 'Belum ditentukan'">
                        {{ item.wali_kelas || 'Wali belum diatur' }}
                      </p>
                    </div>

                    <div class="grid grid-cols-2 gap-1 text-[10px] pt-1 border-t">
                      <span class="text-emerald-600 dark:text-emerald-400 font-medium">
                        Hadir: {{ item.hadir + item.terlambat }}
                      </span>
                      <span class="text-muted-foreground text-right">
                        I/S: {{ item.izinSakit }} &bull; A: {{ item.belumAlpa }}
                      </span>
                    </div>
                  </div>
                </div>

                <div v-else class="py-10 text-center text-muted-foreground text-xs">
                  Belum ada data jilid yang terdaftar.
                </div>
              </CardContent>
            </div>

            <CardFooter class="pt-3 border-t flex justify-between items-center text-xs text-muted-foreground">
              <span>Total {{ kelasBreakdown.length }} Jilid &bull; {{ guruStats.totalPengajar }} Ustadz/Ustadzah Pengajar</span>
              <router-link
                to="/absensi/rekap"
                class="font-medium text-primary hover:underline flex items-center gap-1"
              >
                <span>Buka Rekap Bulanan</span>
                <ChevronRight class="size-3.5" />
              </router-link>
            </CardFooter>
          </Card>
        </template>
      </div>

      <!-- Quick Navigation Grid -->
      <Card>
        <CardHeader class="pb-3">
          <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Akses Cepat Modul Sistem
          </CardTitle>
        </CardHeader>
        <CardContent class="p-3 sm:p-6 pt-0 sm:pt-0">
          <div class="grid grid-cols-3 sm:grid-cols-3 lg:grid-cols-6 gap-2 sm:gap-3">
            <!-- Scanner QR -->
            <router-link
              v-if="canScanQr"
              to="/absensi/scanner"
              class="p-2.5 sm:p-3.5 rounded-xl border bg-card hover:bg-muted/60 text-center transition-all group flex flex-col items-center"
            >
              <div class="flex size-8 sm:size-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 mb-1.5 sm:mb-2 transition-transform group-hover:scale-110">
                <ScanLine class="size-4 sm:size-5" />
              </div>
              <span class="text-[11px] sm:text-xs font-medium text-foreground block truncate max-w-full">Scanner</span>
              <span class="hidden sm:block text-[10px] text-muted-foreground mt-0.5">Presensi QR santri</span>
            </router-link>

            <!-- Live Monitoring -->
            <router-link
              to="/absensi/monitoring"
              class="p-2.5 sm:p-3.5 rounded-xl border bg-card hover:bg-muted/60 text-center transition-all group flex flex-col items-center"
            >
              <div class="flex size-8 sm:size-10 items-center justify-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400 mb-1.5 sm:mb-2 transition-transform group-hover:scale-110">
                <Activity class="size-4 sm:size-5" />
              </div>
              <span class="text-[11px] sm:text-xs font-medium text-foreground block truncate max-w-full">Monitoring</span>
              <span class="hidden sm:block text-[10px] text-muted-foreground mt-0.5">Status harian</span>
            </router-link>

            <!-- Rekap Matrix -->
            <router-link
              v-if="canViewRekapMatrix"
              to="/absensi/rekap"
              class="p-2.5 sm:p-3.5 rounded-xl border bg-card hover:bg-muted/60 text-center transition-all group flex flex-col items-center"
            >
              <div class="flex size-8 sm:size-10 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 mb-1.5 sm:mb-2 transition-transform group-hover:scale-110">
                <TableProperties class="size-4 sm:size-5" />
              </div>
              <span class="text-[11px] sm:text-xs font-medium text-foreground block truncate max-w-full">Rekap</span>
              <span class="hidden sm:block text-[10px] text-muted-foreground mt-0.5">Tabel 1-31 hari</span>
            </router-link>

            <!-- Kalender & Agenda -->
            <router-link
              v-if="canViewAgenda"
              to="/absensi/agenda-libur"
              class="p-2.5 sm:p-3.5 rounded-xl border bg-card hover:bg-muted/60 text-center transition-all group flex flex-col items-center"
            >
              <div class="flex size-8 sm:size-10 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 mb-1.5 sm:mb-2 transition-transform group-hover:scale-110">
                <CalendarDays class="size-4 sm:size-5" />
              </div>
              <span class="text-[11px] sm:text-xs font-medium text-foreground block truncate max-w-full">Agenda &amp; Libur</span>
              <span class="hidden sm:block text-[10px] text-muted-foreground mt-0.5">Kalender kegiatan</span>
            </router-link>

            <!-- Data Santri -->
            <router-link
              v-if="canViewSantri"
              to="/absensi/siswa"
              class="p-2.5 sm:p-3.5 rounded-xl border bg-card hover:bg-muted/60 text-center transition-all group flex flex-col items-center"
            >
              <div class="flex size-8 sm:size-10 items-center justify-center rounded-lg bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 mb-1.5 sm:mb-2 transition-transform group-hover:scale-110">
                <GraduationCap class="size-4 sm:size-5" />
              </div>
              <span class="text-[11px] sm:text-xs font-medium text-foreground block truncate max-w-full">Data Santri</span>
              <span class="hidden sm:block text-[10px] text-muted-foreground mt-0.5">Daftar santri aktif</span>
            </router-link>

            <!-- Data Guru -->
            <router-link
              v-if="canViewGuru"
              to="/guru"
              class="p-2.5 sm:p-3.5 rounded-xl border bg-card hover:bg-muted/60 text-center transition-all group flex flex-col items-center"
            >
              <div class="flex size-8 sm:size-10 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400 mb-1.5 sm:mb-2 transition-transform group-hover:scale-110">
                <UserCog class="size-4 sm:size-5" />
              </div>
              <span class="text-[11px] sm:text-xs font-medium text-foreground block truncate max-w-full">Data Guru</span>
              <span class="hidden sm:block text-[10px] text-muted-foreground mt-0.5">Ustadz &amp; pengurus</span>
            </router-link>

            <!-- Delivery Log WA -->
            <router-link
              v-if="canViewWaLogs && !canViewGuru"
              to="/wa/delivery-log"
              class="p-2.5 sm:p-3.5 rounded-xl border bg-card hover:bg-muted/60 text-center transition-all group flex flex-col items-center"
            >
              <div class="flex size-8 sm:size-10 items-center justify-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400 mb-1.5 sm:mb-2 transition-transform group-hover:scale-110">
                <Send class="size-4 sm:size-5" />
              </div>
              <span class="text-[11px] sm:text-xs font-medium text-foreground block truncate max-w-full">Delivery Log</span>
              <span class="hidden sm:block text-[10px] text-muted-foreground mt-0.5">Status notifikasi</span>
            </router-link>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import {
  CheckCircle2,
  Clock,
  Send,
  ChevronRight,
  Target,
  GraduationCap,
  Users,
  UserCog,
  Layers,
  ScanLine,
  Activity,
  TableProperties,
  CalendarDays,
} from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/composables/useAuth';
import api from '@/services/api';

const {
  user,
  isGuru,
  canScanQr,
  canViewRekapMatrix,
  canViewSantri,
  canViewGuru,
  canViewWaLogs,
  canViewAgenda,
  kelasBinaan,
} = useAuth();

const isLoading = ref(false);
const hasDataLoaded = ref(false);

const currentDateFormatted = computed(() => {
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  return new Intl.DateTimeFormat('id-ID', options).format(new Date());
});

const topGridColsClass = computed(() => {
  if (canViewGuru.value && canViewWaLogs.value) return 'lg:grid-cols-4';
  if (canViewWaLogs.value || canViewGuru.value) return 'lg:grid-cols-3';
  return 'lg:grid-cols-2';
});

const absensiStats = ref({
  total: 0,
  hadir: 0,
  terlambat: 0,
  izin: 0,
  sakit: 0,
  alpa: 0,
  belum_absen: 0,
});

const guruClassStats = ref({
  total: 0,
  hadir: 0,
  terlambat: 0,
  izin: 0,
  sakit: 0,
  alpa: 0,
  belum_absen: 0,
});
const guruStudents = ref([]);
const allStudentsMonitoring = ref([]);
const kelasList = ref([]);

const guruStats = ref({
  totalStaff: 0,
  totalPengajar: 0,
});

const waStats = ref({
  total: 0,
  sent: 0,
  delivered: 0,
  read: 0,
  failed: 0,
});

const kelasBreakdown = computed(() => {
  return kelasList.value.map((k) => {
    const santriKelas = allStudentsMonitoring.value.filter(s => Number(s.id_kelas) === Number(k.id));
    const hadir = santriKelas.filter(s => s.status === 'Hadir').length;
    const terlambat = santriKelas.filter(s => s.status === 'Terlambat').length;
    const izinSakit = santriKelas.filter(s => s.status === 'Izin' || s.status === 'Sakit').length;
    const belumAlpa = santriKelas.filter(s => s.status === 'Alpa' || s.status === 'Belum Absen').length;

    return {
      id: k.id,
      nama_kelas: k.nama_kelas,
      wali_kelas: k.wali_kelas || k.guru?.name || null,
      warna_label: k.warna_label,
      total: santriKelas.length,
      hadir,
      terlambat,
      izinSakit,
      belumAlpa,
    };
  });
});

function getStatusBadgeVariant(status) {
  switch (status) {
    case 'Hadir':
      return 'default';
    case 'Terlambat':
      return 'secondary';
    case 'Izin':
    case 'Sakit':
      return 'outline';
    case 'Alpa':
      return 'destructive';
    default:
      return 'outline';
  }
}

function attendancePercentage(count, customTotal = null) {
  const baseTotal = customTotal !== null ? customTotal : (isGuru.value ? guruClassStats.value.total : absensiStats.value.total);
  if (!baseTotal) return 0;
  return Math.round(((count || 0) / baseTotal) * 100);
}

async function loadDashboard() {
  isLoading.value = true;
  try {
    const requests = [];

    if (isGuru.value && user.value?.id_kelas) {
      requests.push(
        api.get('/absensi/monitoring', { params: { id_kelas: user.value.id_kelas } }).then((monRes) => {
          if (monRes.data.success) {
            const s = monRes.data.data?.stats || {};
            guruClassStats.value = {
              total: s.total || 0,
              hadir: s.hadir || 0,
              terlambat: s.terlambat || 0,
              izin: s.izin || 0,
              sakit: s.sakit || 0,
              alpa: s.alpa || 0,
              belum_absen: s.belum_absen || 0,
            };
            guruStudents.value = monRes.data.data?.students || [];
          }
        })
      );
    } else {
      requests.push(
        api.get('/absensi/monitoring').then((monRes) => {
          if (monRes.data.success) {
            const s = monRes.data.data?.stats || {};
            absensiStats.value = {
              total: s.total || 0,
              hadir: s.hadir || 0,
              terlambat: s.terlambat || 0,
              izin: s.izin || 0,
              sakit: s.sakit || 0,
              alpa: s.alpa || 0,
              belum_absen: s.belum_absen || 0,
            };
            allStudentsMonitoring.value = monRes.data.data?.students || [];
          }
        }),
        api.get('/kelas').then((kelasRes) => {
          if (kelasRes.data.success) {
            kelasList.value = kelasRes.data.data || [];
          }
        })
      );
    }

    if (canViewGuru.value) {
      requests.push(
        api.get('/users').then((usersRes) => {
          if (usersRes.data.success) {
            const list = usersRes.data.data || [];
            guruStats.value = {
              totalStaff: list.length,
              totalPengajar: list.filter(u => u.role === 'guru' || !!u.id_kelas).length,
            };
          }
        })
      );
    }

    if (canViewWaLogs.value) {
      requests.push(
        api.get('/wa/logs?per_page=1').then((waRes) => {
          if (waRes.data.success && waRes.data.summary) {
            waStats.value = {
              total: waRes.data.summary.total || 0,
              sent: waRes.data.summary.sent || 0,
              delivered: waRes.data.summary.delivered || 0,
              read: waRes.data.summary.read || 0,
              failed: waRes.data.summary.failed || 0,
            };
          }
        })
      );
    }

    await Promise.allSettled(requests);
    hasDataLoaded.value = true;
  } catch (err) {
    console.error('Failed to load dashboard data:', err);
  } finally {
    isLoading.value = false;
  }
}

onMounted(() => {
  loadDashboard();
});
</script>
