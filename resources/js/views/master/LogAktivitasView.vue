<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-2 border-b">
      <div class="space-y-0.5">
        <div class="flex items-center gap-2">
          <h2 class="text-lg font-semibold tracking-tight text-foreground">
            Log Aktivitas Sistem
          </h2>
          <Badge variant="outline" class="font-normal text-xs text-muted-foreground gap-1">
            <ShieldCheck class="size-3 text-emerald-500" />
            <span>Audit Trail</span>
          </Badge>
        </div>
        <p class="text-xs text-muted-foreground">
          Audit jejak aktivitas autentikasi, master data, scan presensi, pembayaran SPP, pengaturan, dan WhatsApp.
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <Button
          variant="outline"
          size="sm"
          class="h-8 gap-1.5 text-xs"
          :disabled="isLoading"
          @click="refreshData"
        >
          <RefreshCw class="size-3.5" :class="{ 'animate-spin': isLoading }" />
          <span>Segarkan</span>
        </Button>

        <Button
          variant="outline"
          size="sm"
          class="h-8 gap-1.5 text-xs text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/30 hover:bg-rose-100 dark:hover:bg-rose-900/40"
          @click="showClearModal = true"
        >
          <Trash2 class="size-3.5" />
          <span>Bersihkan Log Lama</span>
        </Button>
      </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2.5">
      <!-- Total Hari Ini -->
      <Card
        class="cursor-pointer transition-all hover:bg-muted/30 border-muted"
        :class="{ 'ring-2 ring-primary border-primary/50 bg-primary/5': selectedDateFilter === 'today' }"
        @click="filterToday"
      >
        <CardContent class="p-3">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[10px] font-semibold uppercase tracking-wider">Hari Ini</span>
            <Clock class="size-3.5 text-sky-500" />
          </div>
          <div class="mt-1.5">
            <div class="text-xl font-bold tracking-tight text-foreground">
              {{ stats.total_hari_ini ?? 0 }}
            </div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Semua aksi hari ini</p>
          </div>
        </CardContent>
      </Card>

      <!-- Autentikasi -->
      <Card
        class="cursor-pointer transition-all hover:bg-muted/30 border-muted"
        :class="{ 'ring-2 ring-indigo-500 border-indigo-400 bg-indigo-500/5': filterModul === 'auth' }"
        @click="setModulFilter('auth')"
      >
        <CardContent class="p-3">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[10px] font-semibold uppercase tracking-wider">Auth</span>
            <Lock class="size-3.5 text-indigo-500" />
          </div>
          <div class="mt-1.5">
            <div class="text-xl font-bold tracking-tight text-foreground">
              {{ stats.total_auth ?? 0 }}
            </div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Login / Logout</p>
          </div>
        </CardContent>
      </Card>

      <!-- Presensi -->
      <Card
        class="cursor-pointer transition-all hover:bg-muted/30 border-muted"
        :class="{ 'ring-2 ring-emerald-500 border-emerald-400 bg-emerald-500/5': filterModul === 'presensi' }"
        @click="setModulFilter('presensi')"
      >
        <CardContent class="p-3">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[10px] font-semibold uppercase tracking-wider">Presensi</span>
            <ScanLine class="size-3.5 text-emerald-500" />
          </div>
          <div class="mt-1.5">
            <div class="text-xl font-bold tracking-tight text-foreground">
              {{ stats.total_presensi ?? 0 }}
            </div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Scan QR / Manual</p>
          </div>
        </CardContent>
      </Card>

      <!-- SPP -->
      <Card
        class="cursor-pointer transition-all hover:bg-muted/30 border-muted"
        :class="{ 'ring-2 ring-amber-500 border-amber-400 bg-amber-500/5': filterModul === 'spp' }"
        @click="setModulFilter('spp')"
      >
        <CardContent class="p-3">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[10px] font-semibold uppercase tracking-wider">SPP</span>
            <Wallet class="size-3.5 text-amber-500" />
          </div>
          <div class="mt-1.5">
            <div class="text-xl font-bold tracking-tight text-foreground">
              {{ stats.total_spp ?? 0 }}
            </div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Bayar &amp; Batal SPP</p>
          </div>
        </CardContent>
      </Card>

      <!-- Master Data -->
      <Card
        class="cursor-pointer transition-all hover:bg-muted/30 border-muted"
        :class="{ 'ring-2 ring-purple-500 border-purple-400 bg-purple-500/5': filterModul === 'master' }"
        @click="setModulFilter('master')"
      >
        <CardContent class="p-3">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[10px] font-semibold uppercase tracking-wider">Master</span>
            <Database class="size-3.5 text-purple-500" />
          </div>
          <div class="mt-1.5">
            <div class="text-xl font-bold tracking-tight text-foreground">
              {{ stats.total_master ?? 0 }}
            </div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Santri, Guru, Jilid</p>
          </div>
        </CardContent>
      </Card>

      <!-- WhatsApp -->
      <Card
        class="cursor-pointer transition-all hover:bg-muted/30 border-muted"
        :class="{ 'ring-2 ring-green-600 border-green-500 bg-green-500/5': filterModul === 'whatsapp' }"
        @click="setModulFilter('whatsapp')"
      >
        <CardContent class="p-3">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[10px] font-semibold uppercase tracking-wider">WhatsApp</span>
            <MessageSquare class="size-3.5 text-green-600" />
          </div>
          <div class="mt-1.5">
            <div class="text-xl font-bold tracking-tight text-foreground">
              {{ stats.total_whatsapp ?? 0 }}
            </div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Pesan &amp; Chatbot</p>
          </div>
        </CardContent>
      </Card>

      <!-- Pengaturan -->
      <Card
        class="cursor-pointer transition-all hover:bg-muted/30 border-muted"
        :class="{ 'ring-2 ring-zinc-500 border-zinc-400 bg-zinc-500/5': filterModul === 'pengaturan' }"
        @click="setModulFilter('pengaturan')"
      >
        <CardContent class="p-3">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[10px] font-semibold uppercase tracking-wider">Sistem</span>
            <Settings class="size-3.5 text-zinc-500" />
          </div>
          <div class="mt-1.5">
            <div class="text-xl font-bold tracking-tight text-foreground">
              {{ stats.total_pengaturan ?? 0 }}
            </div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Konfigurasi</p>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Filter & Search Toolbar Card -->
    <Card>
      <CardContent class="p-3.5 space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-2.5">
          <!-- Input Search -->
          <div class="md:col-span-4 relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
            <Input
              v-model="searchQuery"
              placeholder="Cari aksi, santri, user, IP..."
              class="h-8 pl-8 text-xs"
              @input="onSearchInput"
            />
          </div>

          <!-- Filter Modul -->
          <div class="md:col-span-2">
            <select
              v-model="filterModul"
              class="h-8 w-full rounded-md border border-input bg-background px-2.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring cursor-pointer"
              @change="fetchLogs(1)"
            >
              <option value="all">Semua Modul</option>
              <option value="auth">Autentikasi</option>
              <option value="presensi">Presensi</option>
              <option value="spp">Keuangan SPP</option>
              <option value="master">Master Data (Santri/Guru/Jilid)</option>
              <option value="santri">Santri</option>
              <option value="guru">Guru / Akun</option>
              <option value="jilid">Jilid / Kelas</option>
              <option value="agenda">Agenda Kegiatan</option>
              <option value="libur">Hari Libur</option>
              <option value="pengaturan">Pengaturan</option>
              <option value="whatsapp">Layanan WhatsApp</option>
            </select>
          </div>

          <!-- Filter Aksi -->
          <div class="md:col-span-2">
            <select
              v-model="filterAksi"
              class="h-8 w-full rounded-md border border-input bg-background px-2.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring cursor-pointer"
              @change="fetchLogs(1)"
            >
              <option value="all">Semua Aksi</option>
              <option value="login">Login</option>
              <option value="logout">Logout</option>
              <option value="tambah">Tambah Data</option>
              <option value="ubah">Ubah Data</option>
              <option value="hapus">Hapus Data</option>
              <option value="scan">Scan Presensi</option>
              <option value="bayar">Bayar SPP</option>
              <option value="batal">Batal Bayar</option>
              <option value="kirim">Kirim Pesan WA</option>
              <option value="respon">Respon Chatbot</option>
            </select>
          </div>

          <!-- Rentang Tanggal -->
          <div class="md:col-span-3 flex items-center gap-1.5">
            <Input
              v-model="startDate"
              type="date"
              class="h-8 text-xs px-2"
              title="Tanggal Mulai"
              @change="fetchLogs(1)"
            />
            <span class="text-xs text-muted-foreground shrink-0">s/d</span>
            <Input
              v-model="endDate"
              type="date"
              class="h-8 text-xs px-2"
              title="Tanggal Selesai"
              @change="fetchLogs(1)"
            />
          </div>

          <!-- Reset Filter -->
          <div class="md:col-span-1 flex items-center justify-end">
            <Button
              variant="ghost"
              size="sm"
              class="h-8 w-full px-2 text-xs text-muted-foreground hover:text-foreground"
              @click="resetFilters"
            >
              <RotateCcw class="size-3 mr-1" />
              <span>Reset</span>
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Data Table Card -->
    <Card class="overflow-hidden">
      <!-- Loading State -->
      <div v-if="isLoading" class="p-12 text-center space-y-3">
        <RefreshCw class="size-7 mx-auto animate-spin text-primary" />
        <p class="text-xs text-muted-foreground">Memuat data log aktivitas sistem...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="logs.length === 0" class="p-12 text-center space-y-3">
        <div class="size-12 rounded-xl bg-muted text-muted-foreground flex items-center justify-center mx-auto">
          <History class="size-6" />
        </div>
        <h4 class="text-sm font-semibold text-foreground">Tidak ada aktivitas ditemukan</h4>
        <p class="text-xs text-muted-foreground max-w-sm mx-auto">
          Belum ada log yang sesuai dengan filter atau kata kunci pencarian Anda.
        </p>
      </div>

      <!-- Table Content -->
      <div v-else class="overflow-x-auto">
        <Table class="w-full">
          <TableHeader>
            <TableRow class="bg-muted/30">
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Waktu</TableHead>
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Pengguna</TableHead>
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Modul &amp; Aksi</TableHead>
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 min-w-[260px]">Deskripsi Aktivitas</TableHead>
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">IP &amp; Perangkat</TableHead>
              <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 text-right">Detail</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="item in logs"
              :key="item.id"
              class="transition-colors hover:bg-muted/40 cursor-pointer"
              @click="openDetail(item)"
            >
              <!-- Waktu -->
              <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap font-mono text-xs">
                <div class="font-medium text-foreground">{{ formatDate(item.created_at) }}</div>
                <div class="text-[10px] text-muted-foreground">{{ formatTime(item.created_at) }} WIB</div>
              </TableCell>

              <!-- Pengguna -->
              <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <Avatar class="size-7">
                    <AvatarFallback class="text-[10px] font-semibold bg-primary/10 text-primary">
                      {{ (item.nama_user || 'S').charAt(0).toUpperCase() }}
                    </AvatarFallback>
                  </Avatar>
                  <div class="leading-tight">
                    <div class="font-medium text-foreground text-xs">{{ item.nama_user || 'Sistem' }}</div>
                    <div class="text-[10px] text-muted-foreground">
                      <span class="inline-block px-1.5 py-0.2 rounded bg-muted font-medium text-[9px] uppercase tracking-wide">
                        {{ item.role_user || 'system' }}
                      </span>
                    </div>
                  </div>
                </div>
              </TableCell>

              <!-- Modul & Aksi -->
              <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <Badge :class="getModulBadgeClass(item.modul)" class="text-[10px] font-medium px-2 py-0.5">
                    {{ formatModul(item.modul) }}
                  </Badge>
                  <Badge :class="getAksiBadgeClass(item.aksi)" class="text-[10px] font-medium px-1.5 py-0.2 border">
                    {{ formatAksi(item.aksi) }}
                  </Badge>
                </div>
              </TableCell>

              <!-- Deskripsi Aktivitas -->
              <TableCell class="py-2.5 px-3 sm:px-4 text-xs">
                <div class="text-foreground font-medium line-clamp-2" :title="item.deskripsi">
                  {{ item.deskripsi }}
                </div>
              </TableCell>

              <!-- IP & Perangkat -->
              <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap text-xs">
                <div class="font-mono text-foreground text-[11px]">{{ item.ip_address || '-' }}</div>
                <div class="text-[10px] text-muted-foreground truncate max-w-[140px]" :title="item.user_agent">
                  {{ parseUserAgent(item.user_agent) }}
                </div>
              </TableCell>

              <!-- Detail Action Button -->
              <TableCell class="py-2.5 px-3 sm:px-4 text-right whitespace-nowrap" @click.stop>
                <Button
                  variant="ghost"
                  size="sm"
                  class="h-7 w-7 p-0"
                  title="Lihat Detail Lengkap"
                  @click="openDetail(item)"
                >
                  <Eye class="size-3.5 text-muted-foreground hover:text-foreground" />
                </Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="pagination.total > 0"
        class="p-3 sm:p-4 border-t border-muted/50 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
      >
        <div class="flex items-center gap-2">
          <span>
            Menampilkan {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} -
            {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
            dari {{ pagination.total }} data
          </span>
          <select
            v-model="perPage"
            class="h-7 rounded border border-input bg-background px-1.5 text-[11px] focus:outline-none"
            @change="fetchLogs(1)"
          >
            <option :value="15">15 / hal</option>
            <option :value="25">25 / hal</option>
            <option :value="50">50 / hal</option>
            <option :value="100">100 / hal</option>
          </select>
        </div>

        <div class="flex items-center gap-1.5">
          <Button
            variant="outline"
            size="sm"
            class="h-7 px-2.5 text-xs"
            :disabled="pagination.current_page <= 1"
            @click="fetchLogs(pagination.current_page - 1)"
          >
            &larr; Prev
          </Button>
          <span class="px-2 text-xs font-medium text-foreground">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>
          <Button
            variant="outline"
            size="sm"
            class="h-7 px-2.5 text-xs"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="fetchLogs(pagination.current_page + 1)"
          >
            Next &rarr;
          </Button>
        </div>
      </div>
    </Card>

    <!-- Modal Detail Log Aktivitas -->
    <Dialog :open="showDetailModal" @update:open="showDetailModal = $event">
      <DialogContent class="sm:max-w-xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div class="flex items-center gap-2">
            <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold">
              <History class="size-4" />
            </div>
            <div>
              <DialogTitle class="text-sm font-semibold">Detail Log Aktivitas #{{ selectedLog?.id }}</DialogTitle>
              <DialogDescription class="text-[11px] text-muted-foreground">
                Tercatat pada {{ formatDateTime(selectedLog?.created_at) }}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div v-if="selectedLog" class="space-y-4 py-2 text-xs">
          <!-- Overview Cards -->
          <div class="grid grid-cols-2 gap-2 p-3 rounded-lg bg-muted/40 border">
            <div>
              <span class="text-[10px] text-muted-foreground uppercase font-semibold">Pengguna</span>
              <p class="font-medium text-foreground mt-0.5">{{ selectedLog.nama_user || 'Sistem' }}</p>
              <p class="text-[10px] text-muted-foreground">Role: {{ selectedLog.role_user || 'system' }}</p>
            </div>
            <div>
              <span class="text-[10px] text-muted-foreground uppercase font-semibold">Modul &amp; Aksi</span>
              <div class="flex items-center gap-1.5 mt-0.5">
                <Badge :class="getModulBadgeClass(selectedLog.modul)" class="text-[10px]">
                  {{ formatModul(selectedLog.modul) }}
                </Badge>
                <Badge :class="getAksiBadgeClass(selectedLog.aksi)" class="text-[10px] border">
                  {{ formatAksi(selectedLog.aksi) }}
                </Badge>
              </div>
            </div>
          </div>

          <!-- Deskripsi Lengkap -->
          <div class="space-y-1">
            <span class="text-[10px] text-muted-foreground uppercase font-semibold">Deskripsi Aktivitas</span>
            <div class="p-2.5 rounded-lg bg-muted/20 border text-foreground font-medium leading-relaxed">
              {{ selectedLog.deskripsi }}
            </div>
          </div>

          <!-- Structured Properties Viewer -->
          <div v-if="selectedLog.properties && Object.keys(selectedLog.properties).length > 0" class="space-y-1.5">
            <span class="text-[10px] text-muted-foreground uppercase font-semibold">Data Parameter / Metadata</span>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-2.5 rounded-lg bg-muted/20 border">
              <div
                v-for="(val, key) in selectedLog.properties"
                :key="key"
                class="text-[11px] py-1 border-b border-border/40 last:border-none"
              >
                <span class="font-medium text-muted-foreground capitalize">{{ formatKey(key) }}:</span>
                <span class="ml-1 text-foreground font-mono">{{ formatValue(val) }}</span>
              </div>
            </div>
          </div>

          <!-- Network & Client Info -->
          <div class="space-y-1">
            <span class="text-[10px] text-muted-foreground uppercase font-semibold">Informasi Jaringan &amp; Client</span>
            <div class="space-y-1 p-2.5 rounded-lg bg-muted/20 border font-mono text-[11px]">
              <div><span class="text-muted-foreground">IP Address:</span> <span class="text-foreground">{{ selectedLog.ip_address || '-' }}</span></div>
              <div class="break-all"><span class="text-muted-foreground">User Agent:</span> <span class="text-foreground text-[10px]">{{ selectedLog.user_agent || '-' }}</span></div>
            </div>
          </div>

          <!-- Raw JSON View Toggle -->
          <div class="space-y-1.5 pt-1">
            <div class="flex items-center justify-between">
              <button
                type="button"
                class="text-[11px] text-primary hover:underline cursor-pointer"
                @click="showRawJson = !showRawJson"
              >
                {{ showRawJson ? 'Sembunyikan Raw JSON' : 'Tampilkan Raw JSON' }}
              </button>
              <Button
                v-if="showRawJson"
                variant="outline"
                size="sm"
                class="h-6 text-[10px] px-2 gap-1"
                @click="copyJson"
              >
                <Copy class="size-3" />
                <span>Salin</span>
              </Button>
            </div>
            <pre
              v-if="showRawJson"
              class="p-2.5 rounded-lg bg-zinc-950 text-zinc-100 dark:bg-black dark:text-zinc-200 text-[10px] font-mono overflow-x-auto max-h-48"
            >{{ JSON.stringify(selectedLog, null, 2) }}</pre>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" size="sm" class="text-xs" @click="showDetailModal = false">
            Tutup
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Modal Konfirmasi Bersihkan Log Lama -->
    <Dialog :open="showClearModal" @update:open="showClearModal = $event">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <div class="flex items-center gap-2">
            <div class="size-8 rounded-lg bg-rose-500/10 text-rose-600 flex items-center justify-center font-bold">
              <Trash2 class="size-4" />
            </div>
            <div>
              <DialogTitle class="text-sm font-semibold">Bersihkan Log Aktivitas Lama</DialogTitle>
              <DialogDescription class="text-[11px] text-muted-foreground">
                Hapus riwayat log lama untuk menghemat ruang penyimpanan sistem.
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
          <p class="text-muted-foreground">
            Pilih batas penyimpanan data log. Data log yang lebih baru dari batas yang dipilih akan tetap aman tersimpan.
          </p>

          <div class="space-y-1.5">
            <label class="text-[11px] font-semibold text-foreground">Hapus log yang lebih lama dari:</label>
            <select
              v-model="clearDays"
              class="h-9 w-full rounded-md border border-input bg-background px-3 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
              <option :value="30">30 Hari yang lalu (Disarankan)</option>
              <option :value="60">60 Hari yang lalu</option>
              <option :value="90">90 Hari yang lalu (3 Bulan)</option>
              <option :value="180">180 Hari yang lalu (6 Bulan)</option>
            </select>
          </div>

          <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-300 text-[11px]">
            ⚠️ Tindakan ini bersifat permanen dan tidak dapat dibatalkan. Jejak pembersihan log ini akan tetap tercatat di sistem audit.
          </div>
        </div>

        <DialogFooter class="gap-2 sm:gap-0">
          <Button variant="outline" size="sm" class="text-xs" :disabled="isClearing" @click="showClearModal = false">
            Batal
          </Button>
          <Button
            variant="destructive"
            size="sm"
            class="text-xs gap-1.5"
            :disabled="isClearing"
            @click="handleClearLogs"
          >
            <RefreshCw v-if="isClearing" class="size-3.5 animate-spin" />
            <Trash2 v-else class="size-3.5" />
            <span>Ya, Bersihkan Log</span>
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import {
  Clock,
  Copy,
  Database,
  Eye,
  History,
  Lock,
  MessageSquare,
  RefreshCw,
  RotateCcw,
  ScanLine,
  Search,
  Settings,
  ShieldCheck,
  Trash2,
  Wallet,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import api from '@/services/api';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

// States
const isLoading = ref(false);
const logs = ref([]);
const stats = ref({
  total_hari_ini: 0,
  total_auth: 0,
  total_presensi: 0,
  total_spp: 0,
  total_master: 0,
  total_pengaturan: 0,
  total_whatsapp: 0,
  total_keseluruhan: 0,
});

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 25,
  total: 0,
});

const perPage = ref(25);
const searchQuery = ref('');
const filterModul = ref('all');
const filterAksi = ref('all');
const startDate = ref('');
const endDate = ref('');
const selectedDateFilter = ref('');

// Modals
const showDetailModal = ref(false);
const selectedLog = ref(null);
const showRawJson = ref(false);

const showClearModal = ref(false);
const clearDays = ref(30);
const isClearing = ref(false);

let searchTimer = null;

// Fetch logs
async function fetchLogs(page = 1) {
  isLoading.value = true;
  try {
    const res = await api.get('/activity-logs', {
      params: {
        page,
        per_page: perPage.value,
        modul: filterModul.value,
        aksi: filterAksi.value,
        search: searchQuery.value,
        start_date: startDate.value,
        end_date: endDate.value,
      },
    });

    if (res.data.success) {
      logs.value = res.data.data;
      pagination.value = res.data.meta;
    }
  } catch (err) {
    toast.error('Gagal mengambil data log aktivitas sistem.');
  } finally {
    isLoading.value = false;
  }
}

// Fetch stats
async function fetchStats() {
  try {
    const res = await api.get('/activity-logs/stats');
    if (res.data.success) {
      stats.value = res.data.data;
    }
  } catch (err) {
    console.error('Error fetching stats:', err);
  }
}

function refreshData() {
  fetchLogs(pagination.value.current_page);
  fetchStats();
}

function onSearchInput() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    fetchLogs(1);
  }, 350);
}

function setModulFilter(modul) {
  if (filterModul.value === modul) {
    filterModul.value = 'all';
  } else {
    filterModul.value = modul;
  }
  selectedDateFilter.value = '';
  fetchLogs(1);
}

function filterToday() {
  const todayStr = new Date().toISOString().slice(0, 10);
  if (selectedDateFilter.value === 'today') {
    selectedDateFilter.value = '';
    startDate.value = '';
    endDate.value = '';
  } else {
    selectedDateFilter.value = 'today';
    startDate.value = todayStr;
    endDate.value = todayStr;
  }
  fetchLogs(1);
}

function resetFilters() {
  filterModul.value = 'all';
  filterAksi.value = 'all';
  searchQuery.value = '';
  startDate.value = '';
  endDate.value = '';
  selectedDateFilter.value = '';
  fetchLogs(1);
}

function openDetail(log) {
  selectedLog.value = log;
  showRawJson.value = false;
  showDetailModal.value = true;
}

async function copyJson() {
  if (!selectedLog.value) return;
  try {
    await navigator.clipboard.writeText(JSON.stringify(selectedLog.value, null, 2));
    toast.success('JSON berhasil disalin ke clipboard');
  } catch (err) {
    toast.error('Gagal menyalin JSON');
  }
}

async function handleClearLogs() {
  isClearing.value = true;
  try {
    const res = await api.delete('/activity-logs/clear', {
      data: { days: clearDays.value },
    });
    if (res.data.success) {
      toast.success(res.data.message || 'Pembersihan log berhasil dilakukan.');
      showClearModal.value = false;
      refreshData();
    } else {
      toast.error(res.data.message || 'Gagal membersihkan log.');
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Terjadi kesalahan saat membersihkan log.';
    toast.error(msg);
  } finally {
    isClearing.value = false;
  }
}

// Helpers Formatter
function formatDate(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatTime(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function formatDateTime(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return `${d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })} ${d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} WIB`;
}

function formatModul(modul) {
  const map = {
    auth: 'Autentikasi',
    santri: 'Data Santri',
    guru: 'Data Guru',
    jilid: 'Data Jilid',
    agenda: 'Agenda',
    libur: 'Hari Libur',
    presensi: 'Presensi',
    spp: 'Keuangan SPP',
    pengaturan: 'Pengaturan',
    whatsapp: 'WhatsApp',
  };
  return map[modul] || modul;
}

function getModulBadgeClass(modul) {
  switch (modul) {
    case 'auth':
      return 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800';
    case 'presensi':
      return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800';
    case 'spp':
      return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800';
    case 'santri':
    case 'guru':
    case 'jilid':
    case 'agenda':
    case 'libur':
      return 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-800';
    case 'whatsapp':
      return 'bg-green-600/10 text-green-700 dark:text-green-400 border border-green-300 dark:border-green-800';
    case 'pengaturan':
    default:
      return 'bg-zinc-500/10 text-zinc-700 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-800';
  }
}

function formatAksi(aksi) {
  const map = {
    login: 'Login',
    logout: 'Logout',
    tambah: 'Tambah',
    ubah: 'Ubah',
    hapus: 'Hapus',
    scan: 'Scan',
    bayar: 'Bayar',
    batal: 'Batal',
    kirim: 'Kirim',
    respon: 'Respon',
  };
  return map[aksi] || aksi;
}

function getAksiBadgeClass(aksi) {
  switch (aksi) {
    case 'login':
    case 'logout':
      return 'border-indigo-300 bg-indigo-50/50 text-indigo-700 dark:border-indigo-700 dark:text-indigo-300';
    case 'tambah':
    case 'bayar':
    case 'scan':
      return 'border-emerald-300 bg-emerald-50/50 text-emerald-700 dark:border-emerald-700 dark:text-emerald-300';
    case 'ubah':
      return 'border-amber-300 bg-amber-50/50 text-amber-700 dark:border-amber-700 dark:text-amber-300';
    case 'hapus':
    case 'batal':
      return 'border-rose-300 bg-rose-50/50 text-rose-700 dark:border-rose-700 dark:text-rose-300';
    case 'kirim':
    case 'respon':
      return 'border-green-300 bg-green-50/50 text-green-700 dark:border-green-700 dark:text-green-300';
    default:
      return 'border-muted text-muted-foreground';
  }
}

function parseUserAgent(ua) {
  if (!ua) return '-';
  if (ua.includes('Chrome')) return 'Chrome / PC';
  if (ua.includes('Firefox')) return 'Firefox';
  if (ua.includes('Safari')) return 'Safari';
  if (ua.includes('Edge')) return 'MS Edge';
  if (ua.includes('Fonnte')) return 'Webhook Fonnte';
  if (ua.includes('Postman')) return 'Postman API';
  return 'Browser / App';
}

function formatKey(key) {
  return String(key).replace(/_/g, ' ');
}

function formatValue(val) {
  if (val === null || val === undefined) return '-';
  if (typeof val === 'object') return JSON.stringify(val);
  return String(val);
}

onMounted(() => {
  fetchLogs(1);
  fetchStats();
});
</script>

