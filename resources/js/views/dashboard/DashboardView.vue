<template>
  <div class="space-y-6">
    <!-- Header Dashboard Sesuai Target Desain -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-3 border-b">
      <div>
        <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">Dashboard</h2>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <Badge variant="outline" class="bg-card px-3 py-1.5 text-xs font-normal text-muted-foreground border-border/80">
          Periode: {{ currentPeriodName }}
        </Badge>
        <Button
          v-if="canScanQr"
          as-child
          size="sm"
          class="h-8 sm:h-9 px-3.5 text-xs font-medium bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs gap-1.5"
        >
          <router-link to="/absensi/scanner">
            <ScanLine class="size-4" />
            <span>Buka Scanner QR</span>
          </router-link>
        </Button>
      </div>
    </div>

    <!-- Skeleton Loading State Sesuai Grid Baru -->
    <div v-if="isLoading && !hasDataLoaded" class="space-y-6">
      <div v-if="isGuru || isBendaharaWithKelas" class="grid grid-cols-4 sm:grid-cols-4 lg:grid-cols-7 gap-1.5 sm:gap-3">
        <Skeleton v-for="i in 7" :key="i" class="h-16 sm:h-20 w-full rounded-xl" />
      </div>
      <div v-if="isBendaharaWithKelas" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <Skeleton v-for="i in 4" :key="i" class="h-24 w-full rounded-xl" />
      </div>
      <div v-else-if="!isGuru" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <Skeleton v-for="i in 6" :key="i" class="h-24 w-full rounded-xl" />
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <Skeleton class="h-80 lg:col-span-7 rounded-xl" />
        <Skeleton class="h-80 lg:col-span-5 rounded-xl" />
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <Skeleton v-for="i in 3" :key="i" class="h-64 rounded-xl" />
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <Skeleton class="h-64 lg:col-span-7 rounded-xl" />
        <Skeleton class="h-64 lg:col-span-5 rounded-xl" />
      </div>
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-4 sm:space-y-6">
      <!-- 1. JIKA ROLE GURU ATAU BENDAHARA YANG PUNYA JILID BINAAN: 7 Stat Metrics Cards Jilid Binaan -->
      <div v-if="isGuru || isBendaharaWithKelas" class="grid grid-cols-4 sm:grid-cols-4 lg:grid-cols-7 gap-1.5 sm:gap-3">
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

      <!-- 1B. JIKA BENDAHARA YANG PUNYA JILID BINAAN: 4 Kartu Ringkasan Keuangan SPP & WA -->
      <div v-if="isBendaharaWithKelas" class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          <!-- Tagihan SPP Bulan Ini -->
          <router-link to="/spp/tagihan" class="block group outline-none">
            <Card class="p-3.5 sm:p-4 rounded-xl border bg-card hover:border-primary/40 hover:shadow-2xs transition-all h-full flex flex-col justify-between">
              <div>
                <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-muted-foreground block truncate">
                  Tagihan SPP Bulan Ini
                </span>
                <div class="mt-1.5">
                  <span class="text-base sm:text-lg font-bold tracking-tight text-foreground truncate block">
                    Rp {{ formatRupiah(sppStats.total_tagihan_nominal) }}
                  </span>
                </div>
              </div>
              <p class="text-[10px] sm:text-xs text-muted-foreground mt-2 truncate">
                {{ (sppStats.santri_lunas || 0) + (sppStats.santri_belum_lunas || 0) }} tagihan aktif
              </p>
            </Card>
          </router-link>

          <!-- Terkumpul -->
          <router-link to="/spp/riwayat" class="block group outline-none">
            <Card class="p-3.5 sm:p-4 rounded-xl border bg-card hover:border-emerald-500/40 hover:shadow-2xs transition-all h-full flex flex-col justify-between">
              <div>
                <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-muted-foreground block truncate">
                  Terkumpul
                </span>
                <div class="mt-1.5">
                  <span class="text-base sm:text-lg font-bold tracking-tight text-emerald-600 dark:text-emerald-400 truncate block">
                    Rp {{ formatRupiah(sppStats.pemasukan_bulan_ini) }}
                  </span>
                </div>
              </div>
              <p class="text-[10px] sm:text-xs text-muted-foreground mt-2 truncate">
                {{ sppStats.persentase_lunas }}% &bull; {{ sppStats.santri_lunas }} lunas
              </p>
            </Card>
          </router-link>

          <!-- Tunggakan -->
          <router-link to="/spp/tagihan?tab=rekap" class="block group outline-none">
            <Card class="p-3.5 sm:p-4 rounded-xl border bg-card hover:border-rose-500/40 hover:shadow-2xs transition-all h-full flex flex-col justify-between">
              <div>
                <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-muted-foreground block truncate">
                  Tunggakan
                </span>
                <div class="mt-1.5">
                  <span class="text-base sm:text-lg font-bold tracking-tight text-rose-600 dark:text-rose-400 truncate block">
                    Rp {{ formatRupiah(sppStats.total_tunggakan_aktif) }}
                  </span>
                </div>
              </div>
              <p class="text-[10px] sm:text-xs text-muted-foreground mt-2 truncate">
                {{ sppStats.santri_belum_lunas }} santri belum bayar
              </p>
            </Card>
          </router-link>

          <!-- Notifikasi WA -->
          <router-link to="/wa/delivery-log" class="block group outline-none">
            <Card class="p-3.5 sm:p-4 rounded-xl border bg-card hover:border-teal-500/40 hover:shadow-2xs transition-all h-full flex flex-col justify-between">
              <div>
                <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-muted-foreground block truncate">
                  Notifikasi WA
                </span>
                <div class="mt-1.5 flex items-baseline gap-1">
                  <span class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                    {{ waStats.delivered + waStats.read }}
                  </span>
                  <span class="text-xs sm:text-sm text-muted-foreground font-medium">
                    / {{ waStats.total }}
                  </span>
                </div>
              </div>
              <p class="text-[10px] sm:text-xs text-muted-foreground mt-2 truncate">
                Terbaca {{ waStats.read }} &bull; Gagal {{ waStats.failed }}
              </p>
            </Card>
          </router-link>
        </div>

      <!-- 2. JIKA ADMIN, KEPALA TPQ, SEKRETARIS, ATAU BENDAHARA TANPA JILID: 6 Kartu Ringkasan Sesuai Target Desain -->
      <div
        v-else-if="!isGuru"
        class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4"
      >
        <!-- Card 1: Hadir Hari Ini -->
        <router-link to="/absensi/monitoring" class="block group outline-none">
          <Card class="p-3.5 sm:p-4 rounded-xl border bg-card hover:border-emerald-500/40 hover:shadow-2xs transition-all h-full flex flex-col justify-between">
            <div>
              <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-muted-foreground block truncate">
                Hadir Hari Ini
              </span>
              <div class="mt-1.5 flex items-baseline gap-1">
                <span class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                  {{ absensiStats.hadir + absensiStats.terlambat }}
                </span>
                <span class="text-xs sm:text-sm text-muted-foreground font-medium">
                  / {{ absensiStats.total }}
                </span>
              </div>
            </div>
            <p class="text-[10px] sm:text-xs text-muted-foreground mt-2 truncate">
              Tepat waktu {{ absensiStats.hadir }} &bull; Telat {{ absensiStats.terlambat }}
            </p>
          </Card>
        </router-link>

        <!-- Card 2: Belum Scan / Alpa -->
        <router-link to="/absensi/monitoring" class="block group outline-none">
          <Card class="p-3.5 sm:p-4 rounded-xl border bg-card hover:border-rose-500/40 hover:shadow-2xs transition-all h-full flex flex-col justify-between">
            <div>
              <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-muted-foreground block truncate">
                Belum Scan / Alpa
              </span>
              <div class="mt-1.5">
                <span class="text-xl sm:text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400">
                  {{ absensiStats.belum_absen + absensiStats.alpa }}
                </span>
              </div>
            </div>
            <p class="text-[10px] sm:text-xs text-muted-foreground mt-2 truncate">
              Izin {{ absensiStats.izin }} &bull; Sakit {{ absensiStats.sakit }} &bull; Alpa {{ absensiStats.alpa }}
            </p>
          </Card>
        </router-link>

        <!-- Card 3: Tagihan SPP Bulan Ini -->
        <router-link to="/spp/tagihan" class="block group outline-none">
          <Card class="p-3.5 sm:p-4 rounded-xl border bg-card hover:border-primary/40 hover:shadow-2xs transition-all h-full flex flex-col justify-between">
            <div>
              <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-muted-foreground block truncate">
                Tagihan SPP Bulan Ini
              </span>
              <div class="mt-1.5">
                <span class="text-base sm:text-lg font-bold tracking-tight text-foreground truncate block">
                  Rp {{ formatRupiah(sppStats.total_tagihan_nominal) }}
                </span>
              </div>
            </div>
            <p class="text-[10px] sm:text-xs text-muted-foreground mt-2 truncate">
              {{ absensiStats.total }} santri terdaftar
            </p>
          </Card>
        </router-link>

        <!-- Card 4: Terkumpul -->
        <router-link to="/spp/riwayat" class="block group outline-none">
          <Card class="p-3.5 sm:p-4 rounded-xl border bg-card hover:border-emerald-500/40 hover:shadow-2xs transition-all h-full flex flex-col justify-between">
            <div>
              <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-muted-foreground block truncate">
                Terkumpul
              </span>
              <div class="mt-1.5">
                <span class="text-base sm:text-lg font-bold tracking-tight text-emerald-600 dark:text-emerald-400 truncate block">
                  Rp {{ formatRupiah(sppStats.pemasukan_bulan_ini) }}
                </span>
              </div>
            </div>
            <p class="text-[10px] sm:text-xs text-muted-foreground mt-2 truncate">
              {{ sppStats.persentase_lunas }}% &bull; {{ sppStats.santri_lunas }} lunas
            </p>
          </Card>
        </router-link>

        <!-- Card 5: Tunggakan -->
        <router-link to="/spp/tagihan?tab=rekap" class="block group outline-none">
          <Card class="p-3.5 sm:p-4 rounded-xl border bg-card hover:border-rose-500/40 hover:shadow-2xs transition-all h-full flex flex-col justify-between">
            <div>
              <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-muted-foreground block truncate">
                Tunggakan
              </span>
              <div class="mt-1.5">
                <span class="text-base sm:text-lg font-bold tracking-tight text-rose-600 dark:text-rose-400 truncate block">
                  Rp {{ formatRupiah(sppStats.total_tunggakan_aktif) }}
                </span>
              </div>
            </div>
            <p class="text-[10px] sm:text-xs text-muted-foreground mt-2 truncate">
              {{ sppStats.santri_belum_lunas }} santri belum bayar
            </p>
          </Card>
        </router-link>

        <!-- Card 6: Notifikasi WA -->
        <router-link to="/wa/delivery-log" class="block group outline-none">
          <Card class="p-3.5 sm:p-4 rounded-xl border bg-card hover:border-teal-500/40 hover:shadow-2xs transition-all h-full flex flex-col justify-between">
            <div>
              <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-muted-foreground block truncate">
                Notifikasi WA
              </span>
              <div class="mt-1.5 flex items-baseline gap-1">
                <span class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                  {{ waStats.delivered + waStats.read }}
                </span>
                <span class="text-xs sm:text-sm text-muted-foreground font-medium">
                  / {{ waStats.total }}
                </span>
              </div>
            </div>
            <p class="text-[10px] sm:text-xs text-muted-foreground mt-2 truncate">
              Terbaca {{ waStats.read }} &bull; Gagal {{ waStats.failed }}
            </p>
          </Card>
        </router-link>
      </div>


      <!-- Middle Section: Visualisasi Presensi & Fokus Kerja -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- 1. JIKA ROLE GURU -->
        <template v-if="isGuru">
          <!-- Progress Presensi Jilid Binaan (Full Width) -->
          <Card class="lg:col-span-12 flex flex-col justify-between">
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

            <CardContent class="pt-2">
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
              </div>
            </CardContent>

            <CardFooter class="pt-4 border-t flex flex-wrap items-center justify-between gap-2">
              <span class="text-xs text-muted-foreground">Detail dan input kehadiran santri dapat dikelola di Live Monitoring.</span>
              <div class="flex items-center gap-2">
                <Button as-child variant="outline" size="sm" class="text-xs">
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
              </div>
            </CardFooter>
          </Card>
        </template>

        <!-- 2. JIKA BENDAHARA YANG JUGA WALI JILID (Contoh: Ustadzah Ummi - JILID 3) -->
        <template v-else-if="isBendaharaWithKelas">
          <!-- Kiri (col-7): Donut Chart Kehadiran Anak Binaan Jilid -->
          <Card class="lg:col-span-7 flex flex-col justify-between overflow-hidden shadow-2xs">
            <div>
              <CardHeader class="pb-3 border-b">
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <CardTitle class="text-base font-semibold text-foreground flex items-center gap-2">
                      <Target class="size-4 text-emerald-600 dark:text-emerald-400" />
                      <span>Kehadiran Anak Binaan &mdash; {{ kelasBinaan }}</span>
                    </CardTitle>
                    <CardDescription class="text-xs mt-0.5">
                      Ringkasan rasio presensi {{ guruClassStats.total }} santri binaan hari ini
                    </CardDescription>
                  </div>
                  <Badge variant="outline" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 border-emerald-500/30 bg-emerald-500/10">
                    SPP Lunas: {{ bendaharaKelasSppSummary.lunas }}/{{ bendaharaKelasSppSummary.total }}
                  </Badge>
                </div>
              </CardHeader>

              <CardContent class="p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row items-center justify-center gap-6 sm:gap-10">
                  <!-- SVG Donut Chart -->
                  <div class="relative size-40 sm:size-44 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 120 120" class="size-full -rotate-90">
                      <!-- Background Ring -->
                      <circle
                        cx="60"
                        cy="60"
                        r="46"
                        fill="none"
                        class="stroke-muted/70"
                        stroke-width="14"
                      />
                      <!-- Donut Segments -->
                      <circle
                        v-for="seg in bendaharaDonutSegments"
                        :key="seg.label"
                        cx="60"
                        cy="60"
                        r="46"
                        fill="none"
                        :stroke="seg.color"
                        stroke-width="14"
                        :stroke-dasharray="`${seg.dash} ${289.03 - seg.dash}`"
                        :stroke-dashoffset="-seg.offset"
                        class="transition-all duration-500"
                      >
                        <title>{{ seg.label }}: {{ seg.count }} santri ({{ seg.percent }}%)</title>
                      </circle>
                    </svg>
                    <!-- Center Label -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
                      <span class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                        {{ attendancePercentage(guruClassStats.hadir + guruClassStats.terlambat, guruClassStats.total) }}%
                      </span>
                      <span class="text-[11px] font-medium text-muted-foreground mt-0.5">
                        {{ guruClassStats.hadir + guruClassStats.terlambat }}/{{ guruClassStats.total }} Hadir
                      </span>
                    </div>
                  </div>

                  <!-- Legend Ringkasan Status -->
                  <div class="grid grid-cols-2 gap-3 w-full max-w-xs">
                    <div
                      v-for="item in bendaharaLegendItems"
                      :key="item.label"
                      class="p-2.5 rounded-xl border bg-muted/20 flex items-center justify-between gap-2"
                    >
                      <div class="flex items-center gap-2 min-w-0">
                        <span class="size-2.5 rounded-full shrink-0" :style="{ backgroundColor: item.color }"></span>
                        <span class="text-xs text-muted-foreground truncate">{{ item.label }}</span>
                      </div>
                      <span class="text-xs font-bold text-foreground shrink-0">{{ item.count }}</span>
                    </div>
                  </div>
                </div>
              </CardContent>
            </div>

            <div class="p-3 pt-2.5 flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted-foreground border-t bg-muted/10">
              <span>Detail nama santri tersedia di halaman Live Monitoring</span>
              <div class="flex items-center gap-2">
                <Button as-child variant="outline" size="sm" class="h-7 text-xs px-2.5">
                  <router-link to="/absensi/monitoring" class="flex items-center gap-1">
                    <span>Live Monitoring</span>
                    <ChevronRight class="size-3" />
                  </router-link>
                </Button>
                <Button v-if="canScanQr" as-child size="sm" class="h-7 text-xs px-2.5 bg-emerald-600 hover:bg-emerald-700 text-white">
                  <router-link to="/absensi/scanner" class="flex items-center gap-1">
                    <ScanLine class="size-3" />
                    <span>Scan QR</span>
                  </router-link>
                </Button>
              </div>
            </div>
          </Card>

          <!-- Kanan (col-5): Panel Perlu Tindakan Khusus Bendahara & Wali Jilid -->
          <Card class="lg:col-span-5 flex flex-col justify-between">
            <div>
              <CardHeader class="pb-3 border-b">
                <div class="flex items-center justify-between gap-2">
                  <div>
                    <CardTitle class="text-sm font-semibold text-foreground">
                      Perlu Tindakan
                    </CardTitle>
                    <CardDescription class="text-xs mt-0.5 text-muted-foreground">
                      Prioritas Bendahara &amp; Wali {{ kelasBinaan }} hari ini
                    </CardDescription>
                  </div>
                  <Badge variant="outline" class="bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20 text-xs px-2.5 py-0.5 rounded-full font-medium">
                    {{ pendingActionCount }} item
                  </Badge>
                </div>
              </CardHeader>

              <CardContent class="p-3 sm:p-4 space-y-3">
                <!-- Item 1: SPP Belum Bayar -->
                <div class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-muted/40 transition-colors">
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex items-center justify-center px-2 py-1 rounded-md text-[10px] font-bold tracking-wide uppercase bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 shrink-0">
                      SPP
                    </span>
                    <div class="min-w-0">
                      <p class="text-xs sm:text-sm font-semibold text-foreground truncate">
                        {{ sppStats.santri_belum_lunas || 0 }} santri belum bayar SPP {{ monthNamesIndo[new Date().getMonth()] }}
                      </p>
                      <p class="text-[11px] text-muted-foreground truncate">
                        {{ sppStats.santri_menunggak_2_bulan || 0 }} di antaranya menunggak &ge; 2 bulan
                      </p>
                    </div>
                  </div>
                  <Button as-child variant="outline" size="sm" class="h-7 px-2.5 text-xs font-medium rounded-lg shrink-0">
                    <router-link to="/spp/tagihan">
                      Tagihan
                    </router-link>
                  </Button>
                </div>

                <!-- Item 2: Input / Verifikasi Pembayaran -->
                <div class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-muted/40 transition-colors">
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex items-center justify-center px-2 py-1 rounded-md text-[10px] font-bold tracking-wide uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 shrink-0">
                      Kas
                    </span>
                    <div class="min-w-0">
                      <p class="text-xs sm:text-sm font-semibold text-foreground truncate">
                        {{ (sppStats.pembayaran_menunggu_verifikasi || 0) > 0 ? `${sppStats.pembayaran_menunggu_verifikasi} pembayaran menunggu verifikasi` : 'Pencatatan pembayaran SPP' }}
                      </p>
                      <p class="text-[11px] text-muted-foreground truncate">
                        Catat setoran SPP santri per jilid atau individu
                      </p>
                    </div>
                  </div>
                  <Button as-child variant="outline" size="sm" class="h-7 px-2.5 text-xs font-medium rounded-lg shrink-0">
                    <router-link to="/spp/input-cepat">
                      Input SPP
                    </router-link>
                  </Button>
                </div>

                <!-- Item 3: Kehadiran Anak Binaan -->
                <div class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-muted/40 transition-colors">
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex items-center justify-center px-2 py-1 rounded-md text-[10px] font-bold tracking-wide uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 shrink-0">
                      Jilid
                    </span>
                    <div class="min-w-0">
                      <p class="text-xs sm:text-sm font-semibold text-foreground truncate">
                        {{ guruClassStats.alpa > 0 ? `${guruClassStats.alpa} santri ${kelasBinaan} alpa hari ini` : `${guruClassStats.belum_absen} santri ${kelasBinaan} belum presensi` }}
                      </p>
                      <p class="text-[11px] text-muted-foreground truncate">
                        Hadir {{ guruClassStats.hadir + guruClassStats.terlambat }} dari {{ guruClassStats.total }} santri binaan Anda
                      </p>
                    </div>
                  </div>
                  <Button as-child variant="outline" size="sm" class="h-7 px-2.5 text-xs font-medium rounded-lg shrink-0">
                    <router-link to="/absensi/monitoring">
                      Pantau
                    </router-link>
                  </Button>
                </div>

                <!-- Item 4: Pesan WA -->
                <div class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-muted/40 transition-colors">
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex items-center justify-center px-2 py-1 rounded-md text-[10px] font-bold tracking-wide uppercase bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 shrink-0">
                      WA
                    </span>
                    <div class="min-w-0">
                      <p class="text-xs sm:text-sm font-semibold text-foreground truncate">
                        {{ waStats.total > 0 ? (waStats.total - waStats.read) : 0 }} notifikasi WA belum dibaca
                      </p>
                      <p class="text-[11px] text-muted-foreground truncate">
                        {{ waStats.total > 0 ? `Terkirim ${waStats.sent + waStats.delivered} • Gagal ${waStats.failed}` : 'Gateway WhatsApp siap mengirimkan notifikasi' }}
                      </p>
                    </div>
                  </div>
                  <Button as-child variant="outline" size="sm" class="h-7 px-2.5 text-xs font-medium rounded-lg shrink-0">
                    <router-link to="/wa/delivery-log">
                      Log
                    </router-link>
                  </Button>
                </div>
              </CardContent>
            </div>
          </Card>
        </template>

        <!-- 3. JIKA ROLE KEPALA TPQ, ADMIN, SEKRETARIS, ATAU BENDAHARA TANPA JILID -->
        <template v-else>
          <!-- Kiri: Presensi Hari Ini per Jilid Sesuai Target Desain -->
          <Card class="lg:col-span-7 flex flex-col justify-between overflow-hidden shadow-2xs">
            <CardHeader class="pb-3 border-b">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <CardTitle class="text-base font-semibold text-foreground">
                    Presensi Hari Ini per Jilid
                  </CardTitle>
                  <CardDescription class="text-xs mt-0.5">
                    {{ absensiStats.total }} santri aktif &bull; {{ kelasBreakdown.length }} jilid &bull; {{ guruStats.totalPengajar }} pengajar
                  </CardDescription>
                </div>
                <Badge variant="outline" class="text-xs font-normal text-sky-600 dark:text-sky-400 border-sky-500/30 bg-sky-500/10">
                  Realtime
                </Badge>
              </div>

              <!-- Legend Dots Sesuai Target Desain -->
              <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 pt-2 text-xs">
                <div class="flex items-center gap-1.5">
                  <span class="size-2 rounded-full bg-emerald-500"></span>
                  <span class="text-muted-foreground text-[11px]">Tepat waktu <b class="text-foreground font-semibold">{{ absensiStats.hadir }}</b></span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="size-2 rounded-full bg-amber-500"></span>
                  <span class="text-muted-foreground text-[11px]">Terlambat <b class="text-foreground font-semibold">{{ absensiStats.terlambat }}</b></span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="size-2 rounded-full bg-blue-500"></span>
                  <span class="text-muted-foreground text-[11px]">Izin/Sakit <b class="text-foreground font-semibold">{{ absensiStats.izin + absensiStats.sakit }}</b></span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="size-2 rounded-full bg-rose-500"></span>
                  <span class="text-muted-foreground text-[11px]">Belum presensi <b class="text-foreground font-semibold">{{ absensiStats.belum_absen + absensiStats.alpa }}</b></span>
                </div>
              </div>
            </CardHeader>

            <CardContent class="p-0">
              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                  <thead>
                    <tr class="border-b bg-muted/30 text-[10px] uppercase font-semibold text-muted-foreground tracking-wider">
                      <th class="py-2.5 px-3">Jilid</th>
                      <th class="py-2.5 px-3">Pengajar</th>
                      <th class="py-2.5 px-2 text-center">Santri</th>
                      <th class="py-2.5 px-2 text-center">Hadir</th>
                      <th class="py-2.5 px-2 text-center">I/S</th>
                      <th class="py-2.5 px-2 text-center">Alpa</th>
                      <th class="py-2.5 px-3 text-center">SPP Lunas</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-border/50">
                    <tr
                      v-if="kelasBreakdown.length === 0"
                    >
                      <td colspan="7" class="py-8 text-center text-muted-foreground text-xs">
                        Belum ada data jilid yang terdaftar.
                      </td>
                    </tr>
                    <tr
                      v-else
                      v-for="item in kelasBreakdown"
                      :key="item.id"
                      class="hover:bg-muted/30 transition-colors"
                    >
                      <td class="py-2.5 px-3 font-medium text-foreground whitespace-nowrap">
                        <div class="flex items-center gap-1.5">
                          <span
                            class="size-2 rounded-full shrink-0"
                            :style="{ backgroundColor: item.warna_label || '#10b981' }"
                          ></span>
                          <span>{{ item.nama_kelas }}</span>
                        </div>
                      </td>
                      <td class="py-2.5 px-3 text-muted-foreground truncate max-w-[150px]" :title="item.wali_kelas || '-'">
                        {{ item.wali_kelas || '-' }}
                      </td>
                      <td class="py-2.5 px-2 text-center font-semibold text-foreground">
                        {{ item.total }}
                      </td>
                      <td class="py-2.5 px-2 text-center text-emerald-600 dark:text-emerald-400 font-medium">
                        {{ item.hadir + item.terlambat }}
                      </td>
                      <td class="py-2.5 px-2 text-center text-blue-600 dark:text-blue-400 font-medium">
                        {{ item.izinSakit }}
                      </td>
                      <td class="py-2.5 px-2 text-center text-rose-600 dark:text-rose-400 font-medium">
                        {{ item.belumAlpa }}
                      </td>
                      <td class="py-2.5 px-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                          {{ item.sppLunas }}/{{ item.sppTotal }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="p-3 pt-2 flex items-center justify-between text-[11px] text-muted-foreground border-t bg-muted/10">
                <router-link to="/absensi/monitoring" class="text-primary hover:underline flex items-center gap-1 font-medium">
                  <span>Lihat di Live Monitoring</span>
                  <ChevronRight class="size-3" />
                </router-link>
                <span class="text-[10px] text-muted-foreground">Status SPP periode {{ currentPeriodName }}</span>
              </div>
            </CardContent>
          </Card>

          <!-- Col 2: Panel Perlu Tindakan / Pantauan Supervisi -->
          <Card class="lg:col-span-5 flex flex-col justify-between">
            <div>
              <CardHeader class="pb-3 border-b">
                <div class="flex items-center justify-between gap-2">
                  <div>
                    <CardTitle class="text-sm font-semibold text-foreground">
                      {{ isKepala ? 'Pantauan Supervisi' : 'Perlu Tindakan' }}
                    </CardTitle>
                    <CardDescription class="text-xs mt-0.5 text-muted-foreground">
                      {{ isKepala ? 'Ringkasan pantauan untuk Kepala TPQ hari ini' : isSekretaris ? 'Prioritas untuk sekretaris hari ini' : 'Prioritas untuk admin hari ini' }}
                    </CardDescription>
                  </div>
                  <Badge variant="outline" class="bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20 text-xs px-2.5 py-0.5 rounded-full font-medium">
                    {{ pendingActionCount }} item
                  </Badge>
                </div>
              </CardHeader>

              <CardContent class="p-3 sm:p-4 space-y-3">
                <!-- Item 1: SPP Belum Bayar -->
                <div class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-muted/40 transition-colors">
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex items-center justify-center px-2 py-1 rounded-md text-[10px] font-bold tracking-wide uppercase bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 shrink-0">
                      SPP
                    </span>
                    <div class="min-w-0">
                      <p class="text-xs sm:text-sm font-semibold text-foreground truncate">
                        {{ sppStats.santri_belum_lunas || 0 }} santri belum bayar SPP {{ monthNamesIndo[new Date().getMonth()] }}
                      </p>
                      <p class="text-[11px] text-muted-foreground truncate">
                        {{ sppStats.santri_menunggak_2_bulan || 0 }} di antaranya menunggak &ge; 2 bulan
                      </p>
                    </div>
                  </div>
                  <Button as-child variant="outline" size="sm" class="h-7 px-2.5 text-xs font-medium rounded-lg shrink-0">
                    <router-link to="/spp/tagihan">
                      {{ isKepala ? 'Lihat' : 'Kirim WA' }}
                    </router-link>
                  </Button>
                </div>

                <!-- Item 2: Verifikasi Pembayaran -->
                <div class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-muted/40 transition-colors">
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex items-center justify-center px-2 py-1 rounded-md text-[10px] font-bold tracking-wide uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 shrink-0">
                      Bayar
                    </span>
                    <div class="min-w-0">
                      <p class="text-xs sm:text-sm font-semibold text-foreground truncate">
                        {{ sppStats.pembayaran_menunggu_verifikasi || 0 }} pembayaran menunggu verifikasi
                      </p>
                      <p class="text-[11px] text-muted-foreground truncate">
                        {{ (sppStats.pembayaran_menunggu_verifikasi || 0) > 0 ? 'Bukti transfer perlu diverifikasi' : 'Semua transaksi kas SPP telah diverifikasi' }}
                      </p>
                    </div>
                  </div>
                  <Button as-child variant="outline" size="sm" class="h-7 px-2.5 text-xs font-medium rounded-lg shrink-0">
                    <router-link :to="isKepala ? '/spp/riwayat' : '/spp/input-cepat'">
                      {{ isKepala ? 'Lihat' : 'Verifikasi' }}
                    </router-link>
                  </Button>
                </div>

                <!-- Item 3: Santri Alpa / Belum Presensi -->
                <div class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-muted/40 transition-colors">
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex items-center justify-center px-2 py-1 rounded-md text-[10px] font-bold tracking-wide uppercase bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20 shrink-0">
                      Alpa
                    </span>
                    <div class="min-w-0">
                      <p class="text-xs sm:text-sm font-semibold text-foreground truncate">
                        {{ absensiStats.alpa > 0 ? `${absensiStats.alpa} santri berstatus alpa hari ini` : `${absensiStats.belum_absen} santri belum presensi` }}
                      </p>
                      <p class="text-[11px] text-muted-foreground truncate">
                        {{ absensiStats.alpa > 0 ? 'Perlu konfirmasi kehadiran wali santri' : 'Scan QR santri di gerbang masuk TPQ' }}
                      </p>
                    </div>
                  </div>
                  <Button as-child variant="outline" size="sm" class="h-7 px-2.5 text-xs font-medium rounded-lg shrink-0">
                    <router-link to="/absensi/monitoring">
                      Lihat
                    </router-link>
                  </Button>
                </div>

                <!-- Item 4: Pesan WA -->
                <div class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-muted/40 transition-colors">
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex items-center justify-center px-2 py-1 rounded-md text-[10px] font-bold tracking-wide uppercase bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 shrink-0">
                      WA
                    </span>
                    <div class="min-w-0">
                      <p class="text-xs sm:text-sm font-semibold text-foreground truncate">
                        {{ waStats.total > 0 ? (waStats.total - waStats.read) : 0 }} notifikasi WA belum dibaca
                      </p>
                      <p class="text-[11px] text-muted-foreground truncate">
                        {{ waStats.total > 0 ? `Terkirim ${waStats.sent + waStats.delivered} • Gagal ${waStats.failed}` : 'Gateway WhatsApp siap mengirimkan notifikasi' }}
                      </p>
                    </div>
                  </div>
                  <Button as-child variant="outline" size="sm" class="h-7 px-2.5 text-xs font-medium rounded-lg shrink-0">
                    <router-link to="/wa/delivery-log">
                      {{ isKepala ? 'Lihat' : 'Log' }}
                    </router-link>
                  </Button>
                </div>
              </CardContent>
            </div>
          </Card>
        </template>
      </div>

      <!-- 3. BARIS GRAFIK & SPP (Tren Pemasukan SPP, Tren Kehadiran Mingguan, Status Pembayaran Bulan Ini) -->
      <div v-if="!isGuru" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- 3.1 Tren Pemasukan SPP -->
        <Card class="flex flex-col justify-between">
          <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
              <div>
                <CardTitle class="text-sm font-semibold text-foreground">
                  Tren Pemasukan SPP
                </CardTitle>
                <CardDescription class="text-xs text-muted-foreground mt-0.5">
                  6 bulan s/d {{ currentPeriodName }}
                </CardDescription>
              </div>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                {{ growthVsLastMonth }}
              </span>
            </div>
          </CardHeader>
          <CardContent class="p-4 pt-1">
            <!-- Bar Chart -->
            <div class="relative h-36 flex items-end justify-between pt-6 px-1">
              <!-- Guidelines -->
              <div class="absolute inset-x-0 bottom-6 border-b border-border/60 pointer-events-none flex justify-between text-[10px] text-muted-foreground">
                <span class="px-1 -mt-2.5">0</span>
              </div>

              <!-- Bars -->
              <div
                v-for="(bar, idx) in last6MonthsRevenue"
                :key="bar.label"
                @click="selectedRevenueIdx = selectedRevenueIdx === idx ? null : idx"
                class="flex flex-col items-center h-full justify-end flex-1 z-10 group relative max-w-[36px] cursor-pointer select-none"
              >
                <!-- Tooltip hover (desktop) & tap/click (mobile) -->
                <div
                  :class="selectedRevenueIdx === idx ? 'block' : 'hidden group-hover:block'"
                  class="absolute -top-7 bg-zinc-900 text-white text-[10px] px-2 py-0.5 rounded shadow-lg whitespace-nowrap z-20 font-mono pointer-events-none"
                >
                  {{ bar.fullName }}: Rp {{ formatRupiah(bar.nominal) }}
                </div>
                <div
                  class="w-full rounded-t-sm transition-all duration-300"
                  :class="bar.isActive || selectedRevenueIdx === idx ? 'bg-emerald-800 dark:bg-emerald-600' : 'bg-emerald-600/40 dark:bg-emerald-500/30'"
                  :style="{ height: get6MonthBarHeight(bar.nominal) }"
                ></div>
                <span
                  class="text-[10px] font-medium mt-1"
                  :class="selectedRevenueIdx === idx ? 'text-emerald-700 dark:text-emerald-400 font-bold' : 'text-muted-foreground'"
                >
                  {{ bar.label }}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- 3.2 Tren Kehadiran Mingguan -->
        <Card class="flex flex-col justify-between">
          <CardHeader class="pb-2">
            <CardTitle class="text-sm font-semibold text-foreground">
              {{ isBendaharaWithKelas ? `Tren Kehadiran ${kelasBinaan}` : 'Tren Kehadiran Mingguan' }}
            </CardTitle>
            <CardDescription class="text-xs text-muted-foreground mt-0.5">
              {{ isBendaharaWithKelas ? `Kehadiran anak binaan 6 pertemuan terakhir` : 'Tingkat kehadiran 6 pertemuan terakhir' }}
            </CardDescription>
          </CardHeader>
          <CardContent class="p-4 pt-1 flex flex-col justify-end">
            <!-- Line Chart SVG -->
            <div class="relative h-36 flex flex-col justify-end">
              <svg viewBox="0 0 280 100" class="w-full h-28 overflow-visible">
                <!-- Line -->
                <polyline
                  v-if="weeklyTrendPoints"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  class="text-emerald-800 dark:text-emerald-500"
                  :points="weeklyTrendPoints"
                />
                <!-- Points -->
                <circle
                  v-for="(p, idx) in trenMingguan"
                  :key="idx"
                  :cx="20 + idx * 48"
                  :cy="85 - Math.round(((p.persen || 0) / 100) * 65)"
                  r="4"
                  class="fill-card stroke-emerald-800 dark:stroke-emerald-500 stroke-2"
                >
                  <title>{{ p.tanggal }}: {{ p.persen }}% ({{ p.hadir }} santri)</title>
                </circle>
              </svg>
              <!-- X Axis Labels -->
              <div class="flex justify-between px-2 pt-1 text-[10px] text-muted-foreground font-medium border-t border-border/40">
                <span v-for="p in trenMingguan" :key="p.label" :title="`${p.tanggal}: ${p.persen}%`">
                  {{ p.label }}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- 3.3 Status Pembayaran Bulan Ini -->
        <Card class="flex flex-col justify-between">
          <CardHeader class="pb-2">
            <CardTitle class="text-sm font-semibold text-foreground">
              Status Pembayaran Bulan Ini
            </CardTitle>
            <CardDescription class="text-xs text-muted-foreground mt-0.5">
              Per jilid, periode {{ currentPeriodName }}
            </CardDescription>
          </CardHeader>
          <CardContent class="p-4 pt-1 space-y-2.5">
            <div v-if="jilidPaymentStatus.length === 0" class="py-6 text-center text-xs text-muted-foreground">
              Belum ada data jilid.
            </div>
            <div
              v-else
              v-for="item in jilidPaymentStatus"
              :key="item.nama"
              class="space-y-1"
              :title="`${item.lunas} dari ${item.total} santri lunas`"
            >
              <div class="flex items-center justify-between text-xs">
                <span class="text-foreground font-medium">{{ item.nama }}</span>
                <span class="text-foreground font-semibold text-[11px]">{{ item.persen }}%</span>
              </div>
              <div class="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="item.persen < 50 ? 'bg-rose-500' : 'bg-emerald-800 dark:bg-emerald-600'"
                  :style="{ width: `${item.persen}%` }"
                ></div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- 4. BARIS BAWAH: Pembayaran SPP Terbaru (col-7) & Akses Cepat (col-5) -->
      <div v-if="!isGuru" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- 4.1 Pembayaran SPP Terbaru -->
        <Card class="lg:col-span-7 flex flex-col justify-between">
          <div>
            <CardHeader class="pb-3 border-b">
              <div class="flex items-center justify-between gap-2">
                <div>
                  <CardTitle class="text-sm font-semibold text-foreground">
                    Pembayaran SPP Terbaru
                  </CardTitle>
                  <CardDescription class="text-xs text-muted-foreground mt-0.5">
                    Transaksi kas masuk terakhir
                  </CardDescription>
                </div>
                <Button as-child variant="outline" size="sm" class="h-7 text-xs px-2.5 rounded-lg">
                  <router-link to="/spp/riwayat">
                    Semua pembayaran
                  </router-link>
                </Button>
              </div>
            </CardHeader>

            <CardContent class="p-0">
              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                  <thead>
                    <tr class="border-b bg-muted/20 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">
                      <th class="py-2.5 px-3">Santri</th>
                      <th class="py-2.5 px-3">Jilid</th>
                      <th class="py-2.5 px-3">Periode</th>
                      <th class="py-2.5 px-3">Metode</th>
                      <th class="py-2.5 px-3">Jumlah</th>
                      <th class="py-2.5 px-3 text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-border/40">
                    <tr v-if="recentPayments.length === 0">
                      <td colspan="6" class="py-10 text-center text-xs text-muted-foreground">
                        Belum ada transaksi pembayaran SPP yang tercatat.
                      </td>
                    </tr>
                    <tr
                      v-else
                      v-for="(tx, idx) in recentPayments"
                      :key="idx"
                      class="hover:bg-muted/30 transition-colors"
                    >
                      <td class="py-2.5 px-3 font-medium text-foreground whitespace-nowrap">{{ tx.santri }}</td>
                      <td class="py-2.5 px-3 text-muted-foreground whitespace-nowrap">{{ tx.jilid }}</td>
                      <td class="py-2.5 px-3 text-muted-foreground whitespace-nowrap">{{ tx.periode }}</td>
                      <td class="py-2.5 px-3 text-muted-foreground whitespace-nowrap">{{ tx.metode }}</td>
                      <td class="py-2.5 px-3 font-medium text-foreground whitespace-nowrap">{{ tx.jumlah }}</td>
                      <td class="py-2.5 px-3 text-center whitespace-nowrap">
                        <span
                          class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold"
                          :class="tx.status === 'Lunas' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20'"
                        >
                          {{ tx.status }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </CardContent>
          </div>
        </Card>

        <!-- 4.2 Akses Cepat 6 Tombol Sesuai Hak Akses Role -->
        <Card class="lg:col-span-5 flex flex-col justify-between">
          <div>
            <CardHeader class="pb-3 border-b">
              <CardTitle class="text-sm font-semibold text-foreground">
                {{ isKepala ? 'Menu Supervisi Cepat' : 'Akses Cepat' }}
              </CardTitle>
              <CardDescription class="text-xs text-muted-foreground mt-0.5">
                {{ isKepala ? 'Pantauan laporan & data utama TPQ' : isBendahara ? 'Aksi keuangan SPP & pemantauan jilid' : 'Aksi yang paling sering dipakai' }}
              </CardDescription>
            </CardHeader>

            <CardContent class="p-3 sm:p-4">
              <!-- Akses Cepat: KEPALA TPQ (Read-only Supervision) -->
              <div v-if="isKepala" class="grid grid-cols-2 gap-2.5 sm:gap-3">
                <router-link
                  to="/spp/tagihan"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Lihat Tagihan &amp; Tunggakan
                </router-link>

                <router-link
                  to="/spp/riwayat"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Laporan Riwayat SPP
                </router-link>

                <router-link
                  to="/absensi/rekap"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Rekap Presensi Bulanan
                </router-link>

                <router-link
                  to="/absensi/monitoring"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Live Monitoring Harian
                </router-link>

                <router-link
                  to="/absensi/siswa"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Data Santri Aktif
                </router-link>

                <router-link
                  to="/guru"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Data Guru &amp; Pengajar
                </router-link>
              </div>

              <!-- Akses Cepat: BENDAHARA (SPP + Jilid Binaan) -->
              <div v-else-if="isBendahara" class="grid grid-cols-2 gap-2.5 sm:gap-3">
                <router-link
                  to="/spp/input-cepat"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Catat Pembayaran SPP
                </router-link>

                <router-link
                  to="/spp/tagihan"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Data Tagihan &amp; Tunggakan
                </router-link>

                <router-link
                  to="/spp/riwayat"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Laporan Riwayat SPP
                </router-link>

                <router-link
                  to="/absensi/monitoring"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Monitoring {{ kelasBinaan || 'Presensi' }}
                </router-link>

                <router-link
                  to="/absensi/rekap"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Rekap Presensi Bulanan
                </router-link>

                <router-link
                  to="/absensi/siswa"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Data Santri
                </router-link>
              </div>

              <!-- Akses Cepat: ADMIN & SEKRETARIS -->
              <div v-else class="grid grid-cols-2 gap-2.5 sm:gap-3">
                <router-link
                  to="/spp/input-cepat"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Catat Pembayaran SPP
                </router-link>

                <router-link
                  to="/spp/tagihan"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Buat Tagihan Bulanan
                </router-link>

                <router-link
                  to="/absensi/rekap"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Rekap Presensi Bulanan
                </router-link>

                <router-link
                  to="/spp/riwayat"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Laporan Keuangan
                </router-link>

                <router-link
                  to="/absensi/siswa"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Tambah Santri
                </router-link>

                <router-link
                  to="/absensi/agenda-libur"
                  class="border rounded-xl py-3 px-3 text-xs font-medium text-foreground hover:bg-muted/60 hover:border-foreground/20 transition-all text-center flex items-center justify-center bg-card shadow-2xs"
                >
                  Atur Agenda &amp; Libur
                </router-link>
              </div>
            </CardContent>
          </div>
        </Card>
      </div>

      <!-- Quick Navigation Grid (Khusus Role Guru) -->
      <Card v-if="isGuru">
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

            <!-- Input Bayar SPP (Khusus Sekretaris & Bendahara) -->
            <router-link
              v-if="showSppDashboard && canManageSpp"
              to="/spp/input-cepat"
              class="p-2.5 sm:p-3.5 rounded-xl border bg-card hover:bg-muted/60 text-center transition-all group flex flex-col items-center"
            >
              <div class="flex size-8 sm:size-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 mb-1.5 sm:mb-2 transition-transform group-hover:scale-110">
                <Wallet class="size-4 sm:size-5" />
              </div>
              <span class="text-[11px] sm:text-xs font-medium text-foreground block truncate max-w-full">Input Bayar SPP</span>
              <span class="hidden sm:block text-[10px] text-muted-foreground mt-0.5">Bayar per jilid/santri</span>
            </router-link>

            <!-- Data Tagihan SPP (Khusus Sekretaris, Bendahara, Kepala) -->
            <router-link
              v-if="showSppDashboard"
              to="/spp/tagihan"
              class="p-2.5 sm:p-3.5 rounded-xl border bg-card hover:bg-muted/60 text-center transition-all group flex flex-col items-center"
            >
              <div class="flex size-8 sm:size-10 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 mb-1.5 sm:mb-2 transition-transform group-hover:scale-110">
                <FileText class="size-4 sm:size-5" />
              </div>
              <span class="text-[11px] sm:text-xs font-medium text-foreground block truncate max-w-full">Tagihan SPP</span>
              <span class="hidden sm:block text-[10px] text-muted-foreground mt-0.5">Cek tunggakan</span>
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
  Wallet,
  AlertTriangle,
  FileText,
} from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/composables/useAuth';
import api from '@/services/api';

const {
  user,
  role,
  isAdmin,
  isKepala,
  isSekretaris,
  isBendahara,
  isGuru,
  canScanQr,
  canViewRekapMatrix,
  canViewSantri,
  canViewGuru,
  canViewWaLogs,
  canManageSpp,
  canViewAgenda,
  kelasBinaan,
} = useAuth();

// Apakah user adalah Bendahara yang juga merangkap Wali Jilid (memiliki id_kelas)
const isBendaharaWithKelas = computed(() => isBendahara.value && !!user.value?.id_kelas);

// Dashboard SPP ditampilkan untuk Admin, Sekretaris, Bendahara, dan Kepala TPQ
const showSppDashboard = computed(() => ['admin', 'sekretaris', 'bendahara', 'kepala'].includes(role.value));
const currentYear = new Date().getFullYear();

const monthNamesIndo = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

const currentPeriodName = computed(() => {
  const now = new Date();
  return `${monthNamesIndo[now.getMonth()]} ${now.getFullYear()}`;
});

const isLoading = ref(false);
const hasDataLoaded = ref(false);

const currentDateFormatted = computed(() => {
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  return new Intl.DateTimeFormat('id-ID', options).format(new Date());
});

const topGridColsClass = computed(() => {
  if (showSppDashboard.value) {
    return canViewGuru.value ? 'lg:grid-cols-5' : 'lg:grid-cols-4';
  }
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

const maxRevenue = computed(() => {
  const values = (sppStats.value.grafik_bulanan || []).map(g => Number(g.total) || 0);
  return Math.max(...values, 100000);
});

const yearlyRevenue = computed(() => {
  return (sppStats.value.grafik_bulanan || []).reduce((acc, curr) => acc + (Number(curr.total) || 0), 0);
});

const averageRevenue = computed(() => {
  const list = sppStats.value.grafik_bulanan || [];
  if (list.length === 0) return 0;
  return Math.round(yearlyRevenue.value / list.length);
});

function getBarHeight(total) {
  if (!total || total <= 0) return '6px';
  const pct = Math.max(8, Math.round((Number(total) / maxRevenue.value) * 100));
  return `${pct}%`;
}

const trenMingguan = ref([]);

const weeklyTrendPoints = computed(() => {
  if (!trenMingguan.value || !trenMingguan.value.length) return '';
  return trenMingguan.value.map((p, idx) => {
    const x = 20 + idx * 48;
    const y = 85 - Math.round(((p.persen || 0) / 100) * 65);
    return `${x},${y}`;
  }).join(' ');
});

const kelasBreakdown = computed(() => {
  return kelasList.value.map((k) => {
    const santriKelas = allStudentsMonitoring.value.filter(s => Number(s.id_kelas) === Number(k.id));
    const hadir = santriKelas.filter(s => s.status === 'Hadir').length;
    const terlambat = santriKelas.filter(s => s.status === 'Terlambat').length;
    const izinSakit = santriKelas.filter(s => s.status === 'Izin' || s.status === 'Sakit').length;
    const belumAlpa = santriKelas.filter(s => s.status === 'Alpa' || s.status === 'Belum Absen').length;

    // SPP lunas riil dari database tagihan SPP
    const sppInfo = sppStats.value.kelas_spp_stats?.[k.id];
    const sppLunas = sppInfo ? sppInfo.lunas : 0;
    const sppTotal = sppInfo ? sppInfo.total : santriKelas.length;

    return {
      id: k.id,
      nama_kelas: (k.nama_kelas || '').replace(/\s*\([^)]+\)\s*$/, '').trim(),
      wali_kelas: k.wali_kelas || k.guru?.name || null,
      warna_label: k.warna_label,
      total: santriKelas.length,
      hadir,
      terlambat,
      izinSakit,
      belumAlpa,
      sppLunas,
      sppTotal,
    };
  });
});

const last6MonthsRevenue = computed(() => {
  const list = sppStats.value.grafik_bulanan || [];
  if (!list.length) return [];
  const currentMonth = new Date().getMonth() + 1; // 1-12
  const startIdx = Math.max(0, currentMonth - 6);
  const sliced = list.slice(startIdx, currentMonth);

  return sliced.map((g, idx, arr) => ({
    label: (g.nama_bulan || '').slice(0, 3),
    fullName: g.nama_bulan,
    nominal: Number(g.total) || 0,
    isActive: idx === arr.length - 1,
  }));
});

const maxRevenue6Months = computed(() => {
  const vals = last6MonthsRevenue.value.map(m => m.nominal);
  return Math.max(...vals, 100000);
});

function get6MonthBarHeight(nominal) {
  if (!nominal || nominal <= 0) return '8%';
  const pct = Math.min(100, Math.max(12, Math.round((nominal / maxRevenue6Months.value) * 100)));
  return `${pct}%`;
}

const growthVsLastMonth = computed(() => {
  const list = sppStats.value.grafik_bulanan || [];
  const now = new Date();
  const m = now.getMonth() + 1;
  const currentTotal = Number(list.find(item => item.bulan === m)?.total || 0);
  const prevTotal = Number(list.find(item => item.bulan === (m > 1 ? m - 1 : 12))?.total || 0);
  if (prevTotal > 0) {
    const diff = Math.round(((currentTotal - prevTotal) / prevTotal) * 100);
    return diff >= 0 ? `+${diff}% vs bln lalu` : `${diff}% vs bln lalu`;
  }
  return currentTotal > 0 ? `Rp ${formatRupiah(currentTotal)}` : 'Rp 0 bln ini';
});

const jilidPaymentStatus = computed(() => {
  if (kelasList.value && kelasList.value.length > 0) {
    return kelasList.value.map((k) => {
      const sppInfo = sppStats.value.kelas_spp_stats?.[k.id];
      return {
        nama: (k.nama_kelas || '').replace(/\s*\([^)]+\)\s*$/, '').trim(),
        persen: sppInfo ? sppInfo.persen : 0,
        lunas: sppInfo ? sppInfo.lunas : 0,
        total: sppInfo ? sppInfo.total : 0,
      };
    });
  }
  return [];
});

const recentPayments = computed(() => {
  return sppStats.value.recent_payments || [];
});

const selectedRevenueIdx = ref(null);

const bendaharaKelasSppSummary = computed(() => {
  const idKelas = user.value?.id_kelas;
  const sppInfo = idKelas ? sppStats.value.kelas_spp_stats?.[idKelas] : null;
  return {
    lunas: sppInfo ? sppInfo.lunas : 0,
    total: sppInfo ? sppInfo.total : (guruClassStats.value.total || 0),
  };
});

const bendaharaLegendItems = computed(() => {
  const s = guruClassStats.value;
  return [
    { label: 'Hadir', count: s.hadir || 0, color: '#10b981' },
    { label: 'Telat', count: s.terlambat || 0, color: '#f59e0b' },
    { label: 'Sakit', count: s.sakit || 0, color: '#3b82f6' },
    { label: 'Izin', count: s.izin || 0, color: '#6366f1' },
    { label: 'Alpa', count: s.alpa || 0, color: '#f43f5e' },
    { label: 'Belum Absen', count: s.belum_absen || 0, color: '#94a3b8' },
  ];
});

const bendaharaDonutSegments = computed(() => {
  const total = guruClassStats.value.total || 0;
  if (!total) return [];
  const circumference = 2 * Math.PI * 46; // ~289.0265
  let accumulatedOffset = 0;

  return bendaharaLegendItems.value
    .filter(item => item.count > 0)
    .map((item) => {
      const fraction = item.count / total;
      const dash = Number((fraction * circumference).toFixed(2));
      const segment = {
        label: item.label,
        count: item.count,
        color: item.color,
        percent: Math.round(fraction * 100),
        dash,
        offset: Number(accumulatedOffset.toFixed(2)),
      };
      accumulatedOffset += dash;
      return segment;
    });
});

const pendingActionCount = computed(() => {
  let count = 0;
  if ((sppStats.value.santri_belum_lunas || 0) > 0) count++;
  if ((sppStats.value.pembayaran_menunggu_verifikasi || 0) > 0) count++;
  if (isBendaharaWithKelas.value) {
    if ((guruClassStats.value.alpa || 0) > 0 || (guruClassStats.value.belum_absen || 0) > 0) count++;
  } else {
    if ((absensiStats.value.alpa || 0) > 0 || (absensiStats.value.belum_absen || 0) > 0) count++;
  }
  if ((waStats.value.total - waStats.value.read) > 0) count++;
  return count;
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
  const baseTotal = customTotal !== null
    ? customTotal
    : ((isGuru.value || isBendaharaWithKelas.value) ? guruClassStats.value.total : absensiStats.value.total);
  if (!baseTotal) return 0;
  return Math.round(((count || 0) / baseTotal) * 100);
}

async function loadDashboard() {
  isLoading.value = true;
  try {
    const requests = [];

    if ((isGuru.value || isBendaharaWithKelas.value) && user.value?.id_kelas) {
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
            trenMingguan.value = monRes.data.data?.tren_mingguan || [];
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
            trenMingguan.value = monRes.data.data?.tren_mingguan || [];
          }
        })
      );
    }

    if (!isGuru.value) {
      requests.push(
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

    if (showSppDashboard.value) {
      requests.push(
        api.get('/spp/dashboard').then((sppRes) => {
          if (sppRes.data.success && sppRes.data.data) {
            sppStats.value = sppRes.data.data;
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
