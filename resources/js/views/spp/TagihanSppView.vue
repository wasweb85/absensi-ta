<template>
  <div class="space-y-6 animate-fadeIn">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 rounded-xl sm:rounded-2xl bg-card border border-border shadow-xs print:hidden">
      <div>
        <h2 class="text-base sm:text-xl font-bold text-foreground flex items-center gap-2">
          <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
            <CreditCard class="size-4" />
          </div>
          <span>Data Tagihan &amp; Tunggakan SPP</span>
        </h2>
        <p class="text-xs text-muted-foreground mt-0.5">
          Monitoring status kewajiban SPP seluruh santri per periode dan rekapitulasi akumulasi tunggakan semester
        </p>
      </div>

      <!-- Actions: Sinkronkan Semester & Generate Tagihan -->
      <div v-if="canManageSpp" class="flex flex-wrap items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          :disabled="isSyncingSemester"
          class="h-8 sm:h-9 text-xs gap-1.5 border-emerald-300 dark:border-emerald-700/80 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
          title="Sinkronkan seluruh tagihan santri aktif untuk tahun ajaran aktif (Juli 2026 - Juni 2027)"
          @click="executeSinkronkanSemester"
        >
          <RefreshCw :class="['size-3.5', isSyncingSemester ? 'animate-spin' : '']" />
          <span>{{ isSyncingSemester ? 'Menyinkronkan...' : 'Sinkronkan Tahun Ajaran' }}</span>
        </Button>

        <Button
          size="sm"
          class="h-8 sm:h-9 text-xs gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
          @click="showGenerateModal = true"
        >
          <Zap class="size-3.5" />
          <span>Generate Tagihan Bulanan</span>
        </Button>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex border-b border-border space-x-2 print:hidden">
      <button
        @click="switchTab('rekap')"
        :class="[
          activeTab === 'rekap'
            ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400 font-bold border-b-2'
            : 'text-muted-foreground hover:text-foreground font-medium'
        ]"
        class="pb-2.5 px-3 sm:px-4 text-xs sm:text-sm flex items-center gap-2 transition cursor-pointer"
      >
        <AlertTriangle class="size-3.5 text-rose-500" />
        <span>Rekap Santri Menunggak</span>
        <Badge
          v-if="rekapSummary.total_santri_menunggak > 0"
          variant="destructive"
          class="text-[10px] px-1.5 py-0 font-bold"
        >
          {{ rekapSummary.total_santri_menunggak }} Santri
        </Badge>
      </button>

      <button
        @click="switchTab('semua')"
        :class="[
          activeTab === 'semua'
            ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400 font-bold border-b-2'
            : 'text-muted-foreground hover:text-foreground font-medium'
        ]"
        class="pb-2.5 px-3 sm:px-4 text-xs sm:text-sm flex items-center gap-2 transition cursor-pointer"
      >
        <CreditCard class="size-3.5" />
        <span>Semua Tagihan (Per Bulan)</span>
      </button>
    </div>

    <!-- TAB 1: REKAP SANTRI MENUNGGAK (AKUMULASI SEMESTER BERJALAN) -->
    <div v-if="activeTab === 'rekap'" class="space-y-4 print:hidden">
      <!-- 3 Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <!-- Card 1: Total Santri Menunggak -->
        <Card class="p-3.5 flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
            <Users class="size-5" />
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Santri Menunggak</p>
            <h4 class="text-lg sm:text-xl font-bold text-rose-600 dark:text-rose-400 mt-0.5">
              {{ rekapSummary.total_santri_menunggak }} Santri
            </h4>
            <p class="text-[10px] text-muted-foreground mt-0.5">Memiliki sisa tagihan aktif</p>
          </div>
        </Card>

        <!-- Card 2: Total Nominal Tunggakan (Juli s/d Cut-off) -->
        <Card class="p-3.5 flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
            <Wallet class="size-5" />
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total Tunggakan Riil</p>
            <h4 class="text-lg sm:text-xl font-bold text-foreground mt-0.5">
              Rp {{ formatRupiah(rekapSummary.total_nominal_tunggakan) }}
            </h4>
            <p class="text-[10px] text-muted-foreground mt-0.5">Periode Juli 2026 s/d {{ rekapSummary.nama_bulan_cut_off }} {{ rekapSummary.cut_off_tahun }}</p>
          </div>
        </Card>

        <!-- Card 3: Rata-rata Bulan Tunggakan -->
        <Card class="p-3.5 flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
            <Clock class="size-5" />
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Rata-rata Durasi</p>
            <h4 class="text-lg sm:text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">
              {{ rekapSummary.rata_rata_bulan }} Bulan
            </h4>
            <p class="text-[10px] text-muted-foreground mt-0.5">Rata-rata bulan nunggak per santri</p>
          </div>
        </Card>
      </div>

      <!-- Kontainer Tunggal Terpadu Rekap Santri Menunggak (Filter & Tabel Menyatu Sesuai Model Data Santri) -->
      <Card class="overflow-hidden py-0 gap-0">
        <!-- Toolbar Atas di Dalam Kontainer Card -->
        <div class="px-3 pt-2 pb-2.5 sm:px-4 sm:pt-3 sm:pb-3.5 border-b border-border bg-card space-y-2 sm:space-y-3">
          <!-- Baris 1: Judul & Badge [Kiri] sejajar dengan Pilihan Cut-Off [Kanan] -->
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-1.5 sm:gap-2">
              <h2 class="text-sm sm:text-base font-semibold tracking-tight text-foreground whitespace-nowrap">
                Rekap Santri Menunggak
              </h2>
              <Badge variant="outline" class="font-normal text-[11px] sm:text-xs text-muted-foreground px-1.5 py-0">
                {{ rekapSantriList.length }} Santri
              </Badge>
            </div>

            <!-- Kontrol Batas Cut-Off Sejajar di Kanan -->
            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
              <span class="text-xs text-muted-foreground hidden md:inline">Batas Cut-Off:</span>
              <select
                v-model.number="rekapCutOffBulan"
                @change="loadRekapTunggakan"
                class="h-8 sm:h-9 rounded-lg border border-input bg-background px-2 sm:px-2.5 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer font-medium"
                title="Bulan Cut-Off"
              >
                <option v-for="(nama, num) in bulanList" :key="num" :value="num">{{ nama }}</option>
              </select>
              <select
                v-model.number="rekapCutOffTahun"
                @change="loadRekapTunggakan"
                class="h-8 sm:h-9 rounded-lg border border-input bg-background px-2 sm:px-2.5 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer font-medium"
                title="Tahun Cut-Off"
              >
                <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>

          <!-- Baris 2: Filter Jilid & Kolom Pencarian -->
          <!-- Di Mobile: Filter Jilid di atas (order-1), Kolom Pencarian di bawah (order-2) -->
          <!-- Di Desktop: Kolom Pencarian di kiri (md:order-1), Filter Jilid di kanan (md:order-2) -->
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 sm:gap-2.5">
            <!-- Filter Jilid / Kelas -->
            <div class="order-1 md:order-2 flex items-center gap-2">
              <select
                id="filterKelasRekap"
                v-model="rekapKelasId"
                class="h-8 sm:h-9 flex-1 md:flex-none rounded-lg border border-input bg-background px-2.5 sm:px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer min-w-[130px] sm:min-w-[140px]"
                title="Filter Jilid"
                @change="loadRekapTunggakan"
              >
                <option value="">Semua Jilid</option>
                <option v-for="k in kelasList" :key="k.id" :value="k.id">
                  {{ getCleanNamaKelas(k.nama_kelas) }}
                </option>
              </select>
            </div>

            <!-- Kolom Pencarian (Di Desktop di kiri, di mobile di bawah) -->
            <div class="order-2 md:order-1 relative flex-1 min-w-[200px] max-w-md">
              <Search class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
              <Input
                id="searchRekap"
                v-model="rekapSearch"
                type="text"
                placeholder="Cari nama santri atau NISN..."
                class="h-8 sm:h-9 pl-9 text-xs bg-background w-full"
                @input="onRekapSearch"
              />
            </div>
          </div>
        </div>

        <!-- Tabel Rekap Santri Menunggak -->
        <div class="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow class="bg-muted/30">
                <TableHead class="w-12 text-center text-[10px] uppercase font-semibold">No</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold">Santri</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold">Jilid</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold text-center whitespace-nowrap">Durasi Nunggak</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold">Rincian Bulan Belum Lunas</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold text-right whitespace-nowrap">Total Tunggakan</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold text-center whitespace-nowrap">Aksi Cepat</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <!-- 1. Sedang Memuat -->
              <TableRow v-if="isLoadingRekap">
                <TableCell colspan="7" class="h-36 text-center text-muted-foreground text-xs">
                  <div class="flex flex-col items-center justify-center gap-2">
                    <LoaderCircle class="size-6 animate-spin text-primary" />
                    <span>Mengkalkulasi rekapitulasi santri menunggak...</span>
                  </div>
                </TableCell>
              </TableRow>

              <!-- 2. Empty State saat Pencarian atau Filter Jilid Aktif -->
              <TableRow v-else-if="rekapSantriList.length === 0 && (rekapSearch || rekapKelasId)">
                <TableCell colspan="7" class="h-32 text-center text-muted-foreground text-xs">
                  Tidak ada data santri yang ditemukan.
                </TableCell>
              </TableRow>

              <!-- 3. Kondisi Alhamdulillah (Jika Tanpa Filter Pencarian dan Memang Tidak Ada Tunggakan) -->
              <TableRow v-else-if="rekapSantriList.length === 0">
                <TableCell colspan="7" class="h-44 text-center">
                  <div class="flex flex-col items-center justify-center py-6 space-y-2">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto">
                      <CheckCircle2 class="size-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <h4 class="text-sm font-bold text-foreground">Alhamdulillah, Tidak Ada Tunggakan</h4>
                    <p class="text-xs text-muted-foreground max-w-sm mx-auto">
                      Seluruh santri aktif telah melunasi kewajiban SPP sampai dengan periode {{ rekapSummary.nama_bulan_cut_off }} {{ rekapSummary.cut_off_tahun }}.
                    </p>
                  </div>
                </TableCell>
              </TableRow>

              <!-- 4. Baris Data Rekap Santri Menunggak -->
              <TableRow
                v-else
                v-for="(s, idx) in rekapSantriList"
                :key="s.id_siswa"
                class="transition-colors hover:bg-muted/40"
              >
                <TableCell class="text-center font-mono text-xs text-muted-foreground">{{ idx + 1 }}</TableCell>

                <!-- Santri -->
                <TableCell>
                  <div class="font-medium text-foreground">{{ s.nama_lengkap }}</div>
                  <div class="flex items-center gap-1.5 text-muted-foreground font-mono text-[11px] mt-0.5">
                    <span>NISN: {{ s.nisn }}</span>
                    <span v-if="s.no_hp_ortu">&bull;</span>
                    <a
                      v-if="s.no_hp_ortu"
                      :href="'https://wa.me/' + cleanPhone(s.no_hp_ortu)"
                      target="_blank"
                      class="text-emerald-600 hover:underline inline-flex items-center gap-0.5"
                      title="Buka WA Ortu"
                    >
                      <span>{{ s.no_hp_ortu }}</span>
                      <ExternalLink class="size-3" />
                    </a>
                  </div>
                </TableCell>

                <!-- Kelas -->
                <TableCell class="whitespace-nowrap">
                  <Badge variant="outline" class="font-medium text-[11px]">
                    {{ getCleanNamaKelas(s.nama_kelas) }}
                  </Badge>
                </TableCell>

                <!-- Durasi Nunggak Badge -->
                <TableCell class="text-center whitespace-nowrap">
                  <Badge
                    :variant="s.total_bulan >= 3 ? 'destructive' : 'secondary'"
                    class="font-bold text-[11px]"
                  >
                    {{ s.total_bulan }} Bulan
                  </Badge>
                </TableCell>

                <!-- Rincian Bulan -->
                <TableCell class="max-w-xs">
                  <div class="flex flex-wrap gap-1">
                    <span
                      v-for="b in s.daftar_bulan"
                      :key="b.id_tagihan"
                      class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-muted text-muted-foreground border border-border"
                    >
                      {{ b.nama_bulan }}
                    </span>
                  </div>
                </TableCell>

                <!-- Total Tunggakan -->
                <TableCell class="text-right font-mono font-bold text-rose-600 dark:text-rose-400 text-sm whitespace-nowrap">
                  Rp {{ formatRupiah(s.total_tunggakan) }}
                </TableCell>

                <!-- Aksi Cepat -->
                <TableCell class="text-center whitespace-nowrap">
                  <div class="flex items-center justify-center gap-1.5">
                    <Button
                      size="sm"
                      class="h-7 px-2.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white gap-1"
                      title="Bayar SPP Santri"
                      @click="openBayarModal(s)"
                    >
                      <Wallet class="size-3.5" />
                      <span>Bayar</span>
                    </Button>

                    <Button
                      variant="outline"
                      size="sm"
                      class="h-7 px-2 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                      title="Kirim Japri Pengingat WA"
                      @click="openJapriModal(s)"
                    >
                      <BaseIcon name="whatsapp" class="size-3.5" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </Card>
    </div>

    <!-- TAB 2: SEMUA TAGIHAN (PER BULAN) -->
    <div v-else class="space-y-4 print:hidden">
      <!-- Kontainer Tunggal Terpadu Semua Tagihan -->
      <Card class="overflow-hidden py-0 gap-0">
        <!-- Toolbar Atas di Dalam Kontainer Card -->
        <div class="px-3 pt-2 pb-2.5 sm:px-4 sm:pt-3 sm:pb-3.5 border-b border-border bg-card space-y-2 sm:space-y-3">
          <!-- Baris 1: Judul & Badge Tagihan -->
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-1.5 sm:gap-2">
              <h2 class="text-sm sm:text-base font-semibold tracking-tight text-foreground whitespace-nowrap">
                Semua Tagihan (Per Bulan)
              </h2>
              <Badge variant="outline" class="font-normal text-[11px] sm:text-xs text-muted-foreground px-1.5 py-0">
                {{ tagihanTotalCount || tagihanList.length }} Tagihan
              </Badge>
            </div>
          </div>

          <!-- Baris 2: Filter & Kolom Pencarian -->
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-2 sm:gap-2.5">
            <!-- Filter Dropdowns (Bulan, Tahun, Jilid, Status) -->
            <div class="order-1 lg:order-2 grid grid-cols-2 sm:flex sm:flex-wrap items-center gap-2">
              <select
                v-model="filters.bulan"
                @change="loadTagihan(1)"
                class="h-8 sm:h-9 rounded-lg border border-input bg-background px-2.5 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
                title="Filter Bulan"
              >
                <option value="">Semua Bulan</option>
                <option v-for="(nama, num) in bulanList" :key="num" :value="num">{{ nama }}</option>
              </select>

              <select
                v-model="filters.tahun"
                @change="loadTagihan(1)"
                class="h-8 sm:h-9 rounded-lg border border-input bg-background px-2.5 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
                title="Filter Tahun"
              >
                <option value="">Semua Tahun</option>
                <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
              </select>

              <select
                v-model="filters.id_kelas"
                @change="loadTagihan(1)"
                class="h-8 sm:h-9 rounded-lg border border-input bg-background px-2.5 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer min-w-[110px]"
                title="Filter Jilid"
              >
                <option value="">Semua Jilid</option>
                <option v-for="k in kelasList" :key="k.id" :value="k.id">
                  {{ getCleanNamaKelas(k.nama_kelas) }}
                </option>
              </select>

              <select
                v-model="filters.status"
                @change="loadTagihan(1)"
                class="h-8 sm:h-9 rounded-lg border border-input bg-background px-2.5 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
                title="Status Pembayaran"
              >
                <option value="">Semua Status</option>
                <option value="belum_lunas">Belum Lunas</option>
                <option value="lunas">Lunas</option>
                <option value="kurang_bayar">Kurang Bayar</option>
              </select>
            </div>

            <!-- Kolom Pencarian -->
            <div class="order-2 lg:order-1 relative flex-1 min-w-[200px] max-w-sm">
              <Search class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
              <Input
                id="searchTagihan"
                v-model="filters.search"
                type="text"
                placeholder="Cari santri atau NISN..."
                class="h-8 sm:h-9 pl-9 text-xs bg-background w-full"
                @input="onTagihanSearch"
              />
            </div>
          </div>
        </div>

        <!-- Tabel Semua Tagihan -->
        <div class="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow class="bg-muted/30">
                <TableHead class="w-12 text-center text-[10px] uppercase font-semibold">No</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold">Santri</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold">Jilid</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold">Periode Tagihan</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold">Nominal Tagihan</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold">Status</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold">Jatuh Tempo</TableHead>
                <TableHead class="text-[10px] uppercase font-semibold text-center">Aksi</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-if="isLoadingTagihan">
                <TableCell colspan="8" class="h-36 text-center text-muted-foreground text-xs">
                  <div class="flex flex-col items-center justify-center gap-2">
                    <LoaderCircle class="size-6 animate-spin text-primary" />
                    <span>Memuat data tagihan...</span>
                  </div>
                </TableCell>
              </TableRow>

              <TableRow v-else-if="tagihanList.length === 0">
                <TableCell colspan="8" class="h-32 text-center text-muted-foreground text-xs">
                  {{ filters.search ? 'Tidak ada data tagihan yang sesuai dengan pencarian.' : 'Tidak ada data tagihan yang sesuai filter.' }}
                </TableCell>
              </TableRow>

              <TableRow
                v-else
                v-for="(t, index) in tagihanList"
                :key="t.id"
                class="transition-colors hover:bg-muted/40"
              >
                <TableCell class="text-center font-mono text-xs text-muted-foreground">
                  {{ (currentPage - 1) * 50 + (index + 1) }}
                </TableCell>
                <TableCell class="font-medium text-foreground">
                  <div>{{ t.siswa?.nama_lengkap || '-' }}</div>
                  <div class="text-[11px] text-muted-foreground font-mono mt-0.5">NISN: {{ t.siswa?.nisn || '-' }}</div>
                </TableCell>
                <TableCell>
                  <Badge variant="outline" class="font-medium text-[11px]">
                    {{ getCleanNamaKelas(t.siswa?.kelas?.nama_kelas) || '-' }}
                  </Badge>
                </TableCell>
                <TableCell class="font-semibold text-foreground text-xs">
                  {{ bulanList[t.bulan] }} {{ t.tahun }}
                </TableCell>
                <TableCell class="font-mono font-bold text-foreground text-xs">
                  Rp {{ formatRupiah(t.nominal_tagihan) }}
                </TableCell>
                <TableCell>
                  <span
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase inline-block"
                    :class="{
                      'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': t.status === 'lunas',
                      'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': t.status === 'belum_lunas',
                      'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': t.status === 'kurang_bayar',
                    }"
                  >
                    {{ t.status.replace('_', ' ') }}
                  </span>
                </TableCell>
                <TableCell class="text-muted-foreground font-mono text-xs">
                  {{ t.jatuh_tempo || '-' }}
                </TableCell>
                <TableCell class="text-center">
                  <Button
                    v-if="canManageSpp && t.status !== 'lunas'"
                    size="sm"
                    class="h-7 text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                    @click="openBayarModalFromTagihan(t)"
                  >
                    Bayar &rarr;
                  </Button>
                  <span v-else-if="t.status !== 'lunas'" class="text-rose-500 font-semibold text-xs">
                    Belum Lunas
                  </span>
                  <span v-else class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 text-xs font-semibold">
                    <Check class="size-3.5" />
                    <span>Lunas</span>
                  </span>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="px-4 py-3 border-t border-border bg-card flex items-center justify-between text-xs">
          <span class="text-muted-foreground">Halaman {{ currentPage }} dari {{ totalPages }}</span>
          <div class="flex items-center gap-1.5">
            <Button
              variant="outline"
              size="sm"
              class="h-8 text-xs"
              :disabled="currentPage <= 1"
              @click="loadTagihan(currentPage - 1)"
            >
              Sebelumnya
            </Button>
            <Button
              variant="outline"
              size="sm"
              class="h-8 text-xs"
              :disabled="currentPage >= totalPages"
              @click="loadTagihan(currentPage + 1)"
            >
              Berikutnya
            </Button>
          </div>
        </div>
      </Card>
    </div>

    <!-- Modal Generate Tagihan Massal -->
    <div
      v-if="showGenerateModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs print:hidden"
    >
      <div class="w-full max-w-sm rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-6 shadow-2xl animate-slideUp">
        <h3 class="text-base font-bold text-zinc-900 dark:text-white mb-2">Generate Tagihan Bulanan</h3>
        <p class="text-xs text-zinc-500 mb-4">
          Sistem akan membuat tagihan SPP untuk seluruh santri yang berstatus aktif berdasarkan tarif jilid masing-masing.
        </p>

        <form @submit.prevent="executeGenerate" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Periode Generate *</label>
            <select
              v-model="genForm.mode"
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
            >
              <option value="single">Bulan Tertentu</option>
              <option value="semester_1">Semester Ganjil (Juli - Desember)</option>
              <option value="semester_2">Semester Genap (Januari - Juni)</option>
              <option value="full_year">1 Tahun Ajaran Penuh (12 Bulan)</option>
            </select>
          </div>

          <div v-if="genForm.mode === 'single'">
            <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Pilih Bulan *</label>
            <select
              v-model.number="genForm.bulan"
              required
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
            >
              <option v-for="(nama, num) in bulanList" :key="num" :value="num">{{ nama }}</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-zinc-600 dark:text-zinc-400 mb-1">Pilih Tahun *</label>
            <input
              v-model.number="genForm.tahun"
              type="number"
              required
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs"
            />
          </div>

          <div class="flex items-center justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
            <button
              type="button"
              @click="showGenerateModal = false"
              class="px-4 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 text-xs font-semibold cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isGenerating"
              class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-md shadow-emerald-600/20 cursor-pointer flex items-center gap-1.5"
            >
              <span v-if="isGenerating" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>{{ isGenerating ? 'Memproses...' : 'Mulai Generate' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Pop-up Japri WhatsApp Santri Menunggak -->
    <div
      v-if="showJapriModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fadeIn print:hidden"
    >
      <div class="w-full max-w-lg rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl p-6 space-y-4 animate-slideUp text-xs">
        <!-- Header Modal -->
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
              <BaseIcon name="whatsapp" class="w-5 h-5" />
            </div>
            <div>
              <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Japri Pengingat SPP Santri</h3>
              <p class="text-[11px] text-zinc-500">Kirim pesan langsung ke wali santri via Gateway Fonnte atau WhatsApp Web</p>
            </div>
          </div>
          <button
            type="button"
            @click="closeJapriModal"
            class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Info Ringkas Santri -->
        <div v-if="selectedJapriSantri" class="p-3.5 rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/80 dark:border-zinc-700/60 space-y-2">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="font-bold text-zinc-900 dark:text-white text-sm">{{ selectedJapriSantri.nama_lengkap }}</h4>
              <p class="text-[11px] text-zinc-400 font-mono">NISN: {{ selectedJapriSantri.nisn }} &bull; {{ selectedJapriSantri.nama_kelas }}</p>
            </div>
            <div class="text-right">
              <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                {{ selectedJapriSantri.total_bulan }} Bulan Nunggak
              </span>
              <p class="text-xs font-mono font-bold text-rose-600 dark:text-rose-400 mt-0.5">
                Rp {{ formatRupiah(selectedJapriSantri.total_tunggakan) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Input No HP Ortu -->
        <div>
          <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">
            Nomor WhatsApp Wali Santri *
          </label>
          <div class="relative">
            <input
              v-model="japriPhone"
              type="text"
              placeholder="Contoh: 08123456789 atau 628123456789"
              class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-mono text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>
          <p v-if="!japriPhone" class="text-[11px] text-rose-500 mt-1">
            * Nomor belum terdaftar pada profil santri. Silakan ketik nomor tujuan.
          </p>
        </div>

        <!-- Textarea Draft Pesan (WhatsApp Chat Bubble style) -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="font-bold text-zinc-700 dark:text-zinc-300">
              Draft Pesan WhatsApp (Dapat Diedit)
            </label>
            <span v-if="isLoadingJapriPreview" class="text-[10px] text-emerald-600 flex items-center gap-1">
              <RefreshCw class="w-3 h-3 animate-spin" />
              Menyiapkan draft...
            </span>
          </div>

          <div v-if="isLoadingJapriPreview" class="p-8 text-center rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-800 text-zinc-400">
            <RefreshCw class="w-6 h-6 mx-auto mb-1 animate-spin text-emerald-600" />
            <p class="text-[11px]">Membuat rincian pesan tunggakan santri...</p>
          </div>

          <textarea
            v-else
            v-model="japriMessage"
            rows="7"
            class="w-full p-3.5 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/60 font-mono text-xs text-zinc-800 dark:text-zinc-200 leading-relaxed focus:ring-2 focus:ring-emerald-500 focus:outline-none resize-y"
            placeholder="Draft pesan pengingat SPP..."
          ></textarea>
        </div>

        <!-- Tombol Aksi -->
        <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
          <div class="flex items-center gap-1.5">
            <button
              type="button"
              @click="copyJapriText"
              :disabled="!japriMessage"
              class="px-3 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-[11px] font-semibold text-zinc-700 dark:text-zinc-200 transition flex items-center gap-1 cursor-pointer disabled:opacity-40"
              title="Salin isi pesan ke clipboard"
            >
              <Copy class="w-3.5 h-3.5" />
              <span>Salin Teks</span>
            </button>
            <button
              type="button"
              @click="openJapriWhatsAppDirect"
              :disabled="!japriMessage || !japriPhone"
              class="px-3 py-2 rounded-xl bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 hover:opacity-90 text-[11px] font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
              title="Buka chat WhatsApp Web / Desktop"
            >
              <BaseIcon name="whatsapp" class="w-3.5 h-3.5 text-emerald-500" />
              <span>Buka di WA</span>
            </button>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="closeJapriModal"
              class="px-3.5 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 text-[11px] font-semibold text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
            >
              Tutup
            </button>
            <button
              type="button"
              @click="sendJapriViaFonnte"
              :disabled="!japriMessage || !japriPhone || isSendingJapri"
              class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <RefreshCw v-if="isSendingJapri" class="w-3.5 h-3.5 animate-spin" />
              <Send v-else class="w-3.5 h-3.5" />
              <span>Kirim Gateway (Fonnte)</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Pop-up Pembayaran SPP Multi-Bulan (Printable Kuitansi) -->
    <div
      v-if="showBayarModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fadeIn print:p-0 print:bg-white print:static"
    >
      <div
        class="w-full max-w-xl rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl p-6 space-y-4 animate-slideUp text-xs print:border-none print:shadow-none print:p-0"
      >
        <!-- STATE 2: BUKTI KUITANSI PEMBAYARAN RESMI -->
        <div v-if="bayarSuccessResult" class="space-y-4">
          <!-- Kuitansi Printable Card -->
          <div
            id="multiKuitansiArea"
            class="border border-zinc-300 dark:border-zinc-700 rounded-2xl p-6 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 space-y-3 print:border print:p-4"
          >
            <!-- Receipt Header -->
            <div class="text-center border-b border-zinc-200 dark:border-zinc-800 pb-3 mb-3">
              <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center print:hidden">
                <Check class="w-6 h-6 text-emerald-600" />
              </div>
              <h4 class="text-base font-extrabold uppercase tracking-wide">KUITANSI PEMBAYARAN SPP</h4>
              <p class="text-[11px] text-zinc-500 font-mono mt-0.5">No: {{ bayarSuccessResult.nomor_kuitansi }}</p>
              <p class="text-[10px] text-zinc-400">{{ bayarSuccessResult.tanggal_bayar }}</p>
            </div>

            <!-- Receipt Details Table -->
            <div class="space-y-2 text-xs">
              <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
                <span class="text-zinc-400">Nama Santri:</span>
                <span class="font-bold text-zinc-900 dark:text-white">{{ bayarSuccessResult.nama_santri }}</span>
              </div>
              <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
                <span class="text-zinc-400">NISN / Kelas:</span>
                <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ bayarSuccessResult.nisn }} / {{ bayarSuccessResult.kelas }}</span>
              </div>
              <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
                <span class="text-zinc-400">Pembayaran Untuk:</span>
                <span class="font-bold text-emerald-600 dark:text-emerald-400 text-right max-w-xs">
                  {{ bayarSuccessResult.daftar_bulan_str || (bayarSuccessResult.daftar_bulan ? bayarSuccessResult.daftar_bulan.map(b => b.label || b).join(', ') : '-') }}
                </span>
              </div>
              <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
                <span class="text-zinc-400">Jumlah Bulan:</span>
                <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ bayarSuccessResult.total_bulan || (bayarSuccessResult.daftar_bulan ? bayarSuccessResult.daftar_bulan.length : 1) }} Bulan</span>
              </div>
              <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
                <span class="text-zinc-400">Metode Bayar:</span>
                <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ bayarSuccessResult.metode }}</span>
              </div>
              <div v-if="bayarSuccessResult.catatan" class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
                <span class="text-zinc-400">Catatan:</span>
                <span class="text-zinc-600 dark:text-zinc-300 italic">{{ bayarSuccessResult.catatan }}</span>
              </div>
              <div class="flex justify-between py-2.5 border-b-2 border-zinc-300 dark:border-zinc-700 bg-emerald-50/50 dark:bg-emerald-950/20 px-2 rounded-lg">
                <span class="font-bold text-xs uppercase text-zinc-700 dark:text-zinc-300">TOTAL DIBAYAR:</span>
                <span class="font-black text-sm text-emerald-600 dark:text-emerald-400 font-mono">Rp {{ formatRupiah(bayarSuccessResult.total_bayar) }}</span>
              </div>
              <div class="flex justify-between py-1">
                <span class="text-zinc-400">Status Transaksi:</span>
                <span class="font-extrabold uppercase text-[11px] text-emerald-600">LUNAS</span>
              </div>
            </div>

            <!-- Signatures Footer -->
            <div class="grid grid-cols-2 gap-4 text-center mt-5 pt-3 border-t border-zinc-200 dark:border-zinc-800 text-[11px]">
              <div>
                <p class="text-zinc-400">Wali Santri / Penyetor,</p>
                <div class="h-10"></div>
                <p class="font-semibold">(............................)</p>
              </div>
              <div>
                <p class="text-zinc-400">Bendahara,</p>
                <div class="h-10"></div>
                <p class="font-bold underline text-emerald-600">{{ bayarSuccessResult.petugas || 'Bendahara' }}</p>
              </div>
            </div>
          </div>

          <!-- Action Buttons Post-Payment -->
          <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800 print:hidden">
            <div class="flex items-center gap-1.5">
              <button
                type="button"
                @click="printKuitansi"
                class="px-3 py-2 rounded-xl bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 hover:opacity-90 text-[11px] font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs"
                title="Cetak Kuitansi Resmi"
              >
                <Printer class="w-3.5 h-3.5" />
                <span>Cetak Kuitansi</span>
              </button>
              <button
                type="button"
                @click="sendKuitansiViaWa"
                :disabled="!selectedBayarSantri?.no_hp_ortu"
                class="px-3 py-2 rounded-xl border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 text-[11px] font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
                title="Kirim Bukti Kuitansi ke WhatsApp Wali Santri"
              >
                <BaseIcon name="whatsapp" class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                <span>Kirim Bukti WA</span>
              </button>
            </div>
            <button
              type="button"
              @click="closeBayarModal"
              class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-[11px] font-bold transition cursor-pointer shadow-md shadow-emerald-600/20"
            >
              Selesai &amp; Tutup
            </button>
          </div>
        </div>

        <!-- STATE 1: FORM PEMBAYARAN MULTI-BULAN -->
        <div v-else class="space-y-4">
          <!-- Header Modal -->
          <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
            <div class="flex items-center gap-2.5">
              <div class="w-9 h-9 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                <Wallet class="w-5 h-5" />
              </div>
              <div>
                <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Pembayaran SPP Santri</h3>
                <p class="text-[11px] text-zinc-500">Pilih beberapa bulan sekaligus untuk pelunasan dalam satu transaksi</p>
              </div>
            </div>
            <button
              type="button"
              @click="closeBayarModal"
              class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Info Ringkas Santri -->
          <div v-if="selectedBayarSantri" class="p-3.5 rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/80 dark:border-zinc-700/60 flex items-center justify-between">
            <div>
              <h4 class="font-bold text-zinc-900 dark:text-white text-sm">{{ selectedBayarSantri.nama_lengkap }}</h4>
              <p class="text-[11px] text-zinc-400 font-mono">NISN: {{ selectedBayarSantri.nisn }} &bull; {{ selectedBayarSantri.nama_kelas }}</p>
            </div>
            <div class="text-right">
              <span v-if="selectedBayarSantri.no_hp_ortu" class="text-[11px] text-zinc-500 font-mono flex items-center gap-1 justify-end">
                <BaseIcon name="whatsapp" class="w-3.5 h-3.5 text-emerald-500" />
                {{ selectedBayarSantri.no_hp_ortu }}
              </span>
              <span v-else class="text-[10px] text-zinc-400 italic">No WA belum terdaftar</span>
            </div>
          </div>

          <!-- Checklist Bulan Tagihan -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="font-bold text-zinc-700 dark:text-zinc-300 text-xs">
                Pilih Bulan Kewajiban ({{ selectedTagihanIds.length }} dari {{ bayarTagihanList.length }} Bulan)
              </label>
              <div class="flex items-center gap-1.5 text-[11px]">
                <button
                  type="button"
                  @click="selectAllBulanBayar"
                  class="text-emerald-600 dark:text-emerald-400 hover:underline font-semibold cursor-pointer"
                >
                  Pilih Semua
                </button>
                <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                <button
                  type="button"
                  @click="clearAllBulanBayar"
                  class="text-zinc-500 hover:underline cursor-pointer"
                >
                  Kosongkan
                </button>
              </div>
            </div>

            <!-- Loading Bills -->
            <div v-if="isLoadingBayarBills" class="p-6 text-center rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-800 text-zinc-400">
              <RefreshCw class="w-5 h-5 mx-auto mb-1 animate-spin text-emerald-600" />
              <p class="text-[11px]">Memuat daftar tagihan santri...</p>
            </div>

            <!-- Empty Bills -->
            <div v-else-if="bayarTagihanList.length === 0" class="p-6 text-center rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-800 text-zinc-400">
              <CheckCircle2 class="w-6 h-6 mx-auto mb-1 text-emerald-500" />
              <p class="text-xs font-semibold text-zinc-600 dark:text-zinc-300">Santri tidak memiliki tunggakan tagihan aktif.</p>
            </div>

            <!-- List of Months -->
            <div v-else class="max-h-48 overflow-y-auto space-y-1.5 pr-1">
              <div
                v-for="b in bayarTagihanList"
                :key="b.id_tagihan"
                @click="toggleBulanItem(b.id_tagihan)"
                :class="[
                  selectedTagihanIds.includes(b.id_tagihan)
                    ? 'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-700/80 shadow-xs'
                    : 'bg-white dark:bg-zinc-800/60 border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800'
                ]"
                class="flex items-center justify-between p-2.5 rounded-xl border transition cursor-pointer select-none"
              >
                <div class="flex items-center gap-2.5">
                  <input
                    type="checkbox"
                    :checked="selectedTagihanIds.includes(b.id_tagihan)"
                    @click.stop="toggleBulanItem(b.id_tagihan)"
                    class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-zinc-300 dark:border-zinc-600 cursor-pointer"
                  />
                  <div>
                    <span class="font-bold text-zinc-900 dark:text-zinc-100 text-xs">
                      {{ b.label || (b.nama_bulan + ' ' + b.tahun) }}
                    </span>
                    <span v-if="b.sudah_dibayar && b.sudah_dibayar > 0" class="ml-2 px-1.5 py-0.5 rounded text-[10px] bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 font-medium">
                      Kurang Bayar
                    </span>
                  </div>
                </div>
                <div class="text-right">
                  <span class="font-mono font-bold text-xs" :class="selectedTagihanIds.includes(b.id_tagihan) ? 'text-emerald-700 dark:text-emerald-300' : 'text-zinc-700 dark:text-zinc-300'">
                    Rp {{ formatRupiah(b.sisa || b.nominal_tagihan) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Total Summary Card -->
          <div class="p-3.5 rounded-2xl bg-emerald-50/90 dark:bg-emerald-950/30 border border-emerald-200/80 dark:border-emerald-800/60 flex items-center justify-between">
            <div>
              <p class="text-[11px] font-bold text-emerald-800 dark:text-emerald-400">Total Yang Akan Dibayar</p>
              <p class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                {{ selectedTagihanIds.length }} Bulan kewajiban SPP terpilih
              </p>
            </div>
            <div class="text-right">
              <p class="text-lg font-black text-emerald-600 dark:text-emerald-400 font-mono">
                Rp {{ formatRupiah(totalNominalBayarModal) }}
              </p>
            </div>
          </div>

          <!-- Form Metode & Catatan -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">
                Metode Pembayaran *
              </label>
              <select
                v-model="bayarMetode"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              >
                <option value="Tunai">Tunai (Cash)</option>
                <option value="Transfer">Transfer Bank</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-zinc-700 dark:text-zinc-300 mb-1">
                Catatan Transaksi (Opsional)
              </label>
              <input
                v-model="bayarCatatan"
                type="text"
                placeholder="Contoh: Titipan orang tua / TF BCA"
                class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              />
            </div>
          </div>

          <!-- Footer Aksi -->
          <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
            <button
              type="button"
              @click="closeBayarModal"
              class="px-3.5 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 text-[11px] font-semibold text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
            >
              Batal
            </button>
            <button
              type="button"
              @click="submitBayarMulti"
              :disabled="selectedTagihanIds.length === 0 || isSubmittingBayar"
              class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <RefreshCw v-if="isSubmittingBayar" class="w-3.5 h-3.5 animate-spin" />
              <Wallet v-else class="w-3.5 h-3.5" />
              <span>
                {{ isSubmittingBayar ? 'Memproses...' : `Bayar Sekarang (${selectedTagihanIds.length} Bulan • Rp ${formatRupiah(totalNominalBayarModal)})` }}
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';
import { useAuth } from '../../composables/useAuth';
import BaseIcon from '../../components/BaseIcon.vue';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Search,
  LoaderCircle,
  CheckCircle2,
  Check,
  RefreshCw,
  Zap,
  CreditCard,
  Wallet,
  Users,
  Clock,
  AlertTriangle,
  X,
  ExternalLink,
  Copy,
  Printer,
  Send,
} from '@lucide/vue';

const route = useRoute();
const toast = useToast();
const { canManageSpp } = useAuth();

const activeTab = ref(route.query.tab === 'semua' ? 'semua' : 'rekap');

// Data Tab Semua Tagihan
const isLoadingTagihan = ref(false);
const tagihanList = ref([]);
const tagihanTotalCount = ref(0);
const kelasList = ref([]);
const currentPage = ref(1);
const totalPages = ref(1);
let tagihanSearchTimer = null;

const filters = ref({
  bulan: new Date().getMonth() + 1,
  tahun: new Date().getFullYear(),
  id_kelas: '',
  status: '',
  search: '',
});

// Data Tab Rekap Santri Menunggak
const isLoadingRekap = ref(false);
const rekapSantriList = ref([]);
const rekapSearch = ref('');
const rekapKelasId = ref('');
const rekapCutOffBulan = ref(new Date().getMonth() + 1);
const rekapCutOffTahun = ref(new Date().getFullYear());
let rekapSearchTimer = null;

const rekapSummary = ref({
  total_santri_menunggak: 0,
  total_nominal_tunggakan: 0,
  rata_rata_bulan: 0,
  cut_off_bulan: new Date().getMonth() + 1,
  cut_off_tahun: new Date().getFullYear(),
  nama_bulan_cut_off: '',
});

const showGenerateModal = ref(false);
const isGenerating = ref(false);
const isSyncingSemester = ref(false);
const genForm = ref({
  mode: 'single',
  bulan: new Date().getMonth() + 1,
  tahun: new Date().getFullYear(),
});

const getCleanNamaKelas = (nama) => {
  if (!nama) return '';
  return nama.replace(/\s*\([^)]*\)/g, '').trim();
};

const cleanPhone = (phone) => {
  if (!phone) return '';
  let clean = phone.replace(/[^0-9]/g, '');
  if (clean.startsWith('0')) {
    clean = '62' + clean.slice(1);
  }
  return clean;
};

const executeSinkronkanSemester = async () => {
  isSyncingSemester.value = true;
  try {
    const res = await api.post('/spp/sinkronkan-semester', {
      from_bulan: 7,
      from_tahun: 2026,
      to_bulan: 6,
      to_tahun: 2027,
    });
    if (res.data.success) {
      toast.success(res.data.message || 'Sinkronisasi tagihan tahun ajaran berhasil!');
      loadRekapTunggakan();
      loadTagihan(currentPage.value);
    } else {
      toast.error(res.data.message || 'Gagal menyinkronkan tagihan tahun ajaran.');
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Terjadi kesalahan saat menyinkronkan tagihan.');
  } finally {
    isSyncingSemester.value = false;
  }
};

const bulanList = {
  1: 'Januari', 2: 'Februari', 3: 'Maret', 4: 'April',
  5: 'Mei', 6: 'Juni', 7: 'Juli', 8: 'Agustus',
  9: 'September', 10: 'Oktober', 11: 'November', 12: 'Desember'
};

const formatRupiah = (val) => new Intl.NumberFormat('id-ID').format(val || 0);

function switchTab(tab) {
  activeTab.value = tab;
  if (tab === 'rekap') {
    loadRekapTunggakan();
  } else {
    loadTagihan(1);
  }
}

const loadKelas = async () => {
  try {
    const res = await api.get('/kelas');
    if (res.data.success) {
      kelasList.value = res.data.data;
    }
  } catch (e) {}
};

const loadTagihan = async (page = 1) => {
  isLoadingTagihan.value = true;
  currentPage.value = page;
  try {
    const params = {
      page: page,
      bulan: filters.value.bulan || undefined,
      tahun: filters.value.tahun || undefined,
      id_kelas: filters.value.id_kelas || undefined,
      status: filters.value.status || undefined,
      search: filters.value.search || undefined,
    };
    const res = await api.get('/spp/tagihan', { params });
    if (res.data.success) {
      tagihanList.value = res.data.data.data || [];
      totalPages.value = res.data.data.last_page || 1;
      tagihanTotalCount.value = res.data.data.total || 0;
    }
  } catch (e) {
    toast.error('Gagal memuat data tagihan SPP.');
  } finally {
    isLoadingTagihan.value = false;
  }
};

function onTagihanSearch() {
  clearTimeout(tagihanSearchTimer);
  tagihanSearchTimer = setTimeout(() => {
    loadTagihan(1);
  }, 350);
}

const loadRekapTunggakan = async () => {
  isLoadingRekap.value = true;
  try {
    const res = await api.get('/spp/rekap-tunggakan', {
      params: {
        bulan: rekapCutOffBulan.value,
        tahun: rekapCutOffTahun.value,
        id_kelas: rekapKelasId.value || undefined,
        search: rekapSearch.value || undefined,
      },
    });

    if (res.data.success) {
      rekapSantriList.value = res.data.data.santri || [];
      rekapSummary.value = res.data.data.summary || rekapSummary.value;
    }
  } catch (err) {
    toast.error('Gagal memuat rekapitulasi santri menunggak.');
  } finally {
    isLoadingRekap.value = false;
  }
};

function onRekapSearch() {
  clearTimeout(rekapSearchTimer);
  rekapSearchTimer = setTimeout(() => {
    loadRekapTunggakan();
  }, 350);
}

// State Modal Pop-up Pembayaran SPP Multi-Bulan
const showBayarModal = ref(false);
const selectedBayarSantri = ref(null);
const bayarTagihanList = ref([]);
const selectedTagihanIds = ref([]);
const bayarMetode = ref('Tunai');
const bayarCatatan = ref('');
const isSubmittingBayar = ref(false);
const isLoadingBayarBills = ref(false);
const bayarSuccessResult = ref(null);

const totalNominalBayarModal = computed(() => {
  return bayarTagihanList.value
    .filter(b => selectedTagihanIds.value.includes(b.id_tagihan))
    .reduce((sum, b) => sum + (Number(b.sisa) || Number(b.nominal_tagihan) || 0), 0);
});

const openBayarModal = async (santri) => {
  selectedBayarSantri.value = {
    id_siswa: santri.id_siswa,
    nama_lengkap: santri.nama_lengkap,
    nisn: santri.nisn,
    nama_kelas: santri.nama_kelas,
    no_hp_ortu: santri.no_hp_ortu,
  };
  bayarTagihanList.value = santri.daftar_bulan || [];
  selectedTagihanIds.value = (santri.daftar_bulan || []).map(b => b.id_tagihan);
  bayarMetode.value = 'Tunai';
  bayarCatatan.value = '';
  bayarSuccessResult.value = null;
  isLoadingBayarBills.value = true;
  showBayarModal.value = true;

  try {
    const res = await api.get(`/spp/santri/${santri.id_siswa}/tunggakan?all=1`);
    if (res.data.success && (res.data.data?.daftar_tunggakan || res.data.data?.daftar_bulan)) {
      const allBills = res.data.data.daftar_tunggakan || res.data.data.daftar_bulan || [];
      if (allBills.length > 0) {
        bayarTagihanList.value = allBills;
        if (santri.daftar_bulan && santri.daftar_bulan.length > 0) {
          selectedTagihanIds.value = santri.daftar_bulan.map(b => b.id_tagihan);
        } else {
          selectedTagihanIds.value = allBills.map(b => b.id_tagihan);
        }
      }
    }
  } catch (err) {
    // Tetap gunakan data santri.daftar_bulan
  } finally {
    isLoadingBayarBills.value = false;
  }
};

const openBayarModalFromTagihan = async (tagihan) => {
  selectedBayarSantri.value = {
    id_siswa: tagihan.id_siswa,
    nama_lengkap: tagihan.siswa?.nama_lengkap || 'Santri',
    nisn: tagihan.siswa?.nisn || '-',
    nama_kelas: tagihan.siswa?.kelas?.nama_kelas || '-',
    no_hp_ortu: tagihan.siswa?.no_hp_ortu || '',
  };
  bayarTagihanList.value = [];
  selectedTagihanIds.value = [tagihan.id];
  bayarMetode.value = 'Tunai';
  bayarCatatan.value = '';
  bayarSuccessResult.value = null;
  isLoadingBayarBills.value = true;
  showBayarModal.value = true;

  try {
    const res = await api.get(`/spp/santri/${tagihan.id_siswa}/tunggakan?all=1`);
    const allBills = res.data.data?.daftar_tunggakan || res.data.data?.daftar_bulan || [];
    if (res.data.success && allBills.length > 0) {
      bayarTagihanList.value = allBills;
      const ids = bayarTagihanList.value.map(b => b.id_tagihan);
      if (ids.includes(tagihan.id)) {
        selectedTagihanIds.value = [tagihan.id];
      } else if (ids.length > 0) {
        selectedTagihanIds.value = ids;
      }
    }
  } catch (err) {
    toast.error('Gagal memuat rincian tagihan santri.');
  } finally {
    isLoadingBayarBills.value = false;
  }
};

const toggleBulanItem = (idTagihan) => {
  const idx = selectedTagihanIds.value.indexOf(idTagihan);
  if (idx >= 0) {
    selectedTagihanIds.value.splice(idx, 1);
  } else {
    selectedTagihanIds.value.push(idTagihan);
  }
};

const selectAllBulanBayar = () => {
  selectedTagihanIds.value = bayarTagihanList.value.map(b => b.id_tagihan);
};

const clearAllBulanBayar = () => {
  selectedTagihanIds.value = [];
};

const submitBayarMulti = async () => {
  if (!selectedBayarSantri.value || selectedTagihanIds.value.length === 0) {
    toast.warning('Pilih minimal satu bulan tagihan untuk dibayar.');
    return;
  }

  isSubmittingBayar.value = true;
  try {
    const res = await api.post('/spp/bayar-multi', {
      id_siswa: selectedBayarSantri.value.id_siswa,
      tagihan_ids: selectedTagihanIds.value,
      metode_pembayaran: bayarMetode.value,
      catatan: bayarCatatan.value || null,
    });

    if (res.data.success) {
      toast.success(res.data.message || 'Pembayaran SPP berhasil dicatat!');
      bayarSuccessResult.value = res.data.kuitansi;
      loadRekapTunggakan();
      loadTagihan(currentPage.value);
    } else {
      toast.error(res.data.message || 'Gagal memproses pembayaran.');
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Terjadi kesalahan saat memproses pembayaran.';
    toast.error(msg);
  } finally {
    isSubmittingBayar.value = false;
  }
};

const closeBayarModal = () => {
  showBayarModal.value = false;
  selectedBayarSantri.value = null;
  bayarTagihanList.value = [];
  selectedTagihanIds.value = [];
  bayarSuccessResult.value = null;
  bayarCatatan.value = '';
};

const printKuitansi = () => {
  window.print();
};

const sendKuitansiViaWa = () => {
  if (!bayarSuccessResult.value) return;
  const phone = selectedBayarSantri.value?.no_hp_ortu || '';
  if (!phone) {
    toast.warning('Nomor WhatsApp wali santri belum terdaftar.');
    return;
  }

  let clean = phone.replace(/[^0-9]/g, '');
  if (clean.startsWith('0')) {
    clean = '62' + clean.slice(1);
  }

  const k = bayarSuccessResult.value;
  const text = `*BUKTI PEMBAYARAN SPP TPQ*
---------------------------------------
No. Kuitansi : ${k.nomor_kuitansi}
Tanggal      : ${k.tanggal_bayar}
Nama Santri  : ${k.nama_santri}
NISN / Kelas : ${k.nisn} / ${k.kelas}
Bulan SPP    : ${k.daftar_bulan_str || (k.daftar_bulan ? k.daftar_bulan.map(b => b.label || b).join(', ') : '-')}
Metode Bayar : ${k.metode}
Status       : LUNAS
---------------------------------------
*TOTAL DIBAYAR: Rp ${formatRupiah(k.total_bayar)}*
Petugas      : ${k.petugas || 'Bendahara'}
${k.catatan ? 'Catatan: ' + k.catatan + '\n' : ''}
Terima kasih atas pembayaran SPP ananda. Semoga menjadi amal jariyah dan berkah bagi ananda tercinta.
---------------------------------------
*TPQ Nurul Mun'im*`;

  const url = `https://wa.me/${clean}?text=${encodeURIComponent(text)}`;
  window.open(url, '_blank');
};

// State Modal Pop-up Japri WhatsApp
const showJapriModal = ref(false);
const selectedJapriSantri = ref(null);
const japriPhone = ref('');
const japriMessage = ref('');
const isLoadingJapriPreview = ref(false);
const isSendingJapri = ref(false);

const openJapriModal = async (santri) => {
  selectedJapriSantri.value = santri;
  japriPhone.value = santri.no_hp_ortu || '';
  japriMessage.value = '';
  showJapriModal.value = true;
  isLoadingJapriPreview.value = true;

  try {
    const res = await api.get('/wa/preview', {
      params: {
        type: 'japri',
        id_siswa: santri.id_siswa,
        bulan: rekapCutOffBulan.value,
        tahun: rekapCutOffTahun.value,
      },
    });

    if (res.data.success) {
      let rawMsg = res.data.message || '';
      rawMsg = rawMsg.replace(/\*(?:E\s*ABSENSI|E-ABSENSI|Sistem Terpadu[^*]*)\*/gi, "*TPQ Nurul Mun'im*")
                     .replace(/kami dari[^*]*\*TPQ/gi, "Kami dari pihak *TPQ");
      japriMessage.value = rawMsg;
      if (!japriPhone.value && res.data.target_phone) {
        japriPhone.value = res.data.target_phone;
      }
    }
  } catch (err) {
    toast.error('Gagal membuat preview pesan pengingat WA.');
  } finally {
    isLoadingJapriPreview.value = false;
  }
};

const closeJapriModal = () => {
  showJapriModal.value = false;
  selectedJapriSantri.value = null;
  japriMessage.value = '';
  japriPhone.value = '';
};

const sendJapriViaFonnte = async () => {
  if (!japriPhone.value) {
    toast.warning('Nomor WhatsApp tujuan wajib diisi.');
    return;
  }
  if (!japriMessage.value) {
    toast.warning('Pesan WhatsApp tidak boleh kosong.');
    return;
  }

  isSendingJapri.value = true;
  try {
    const res = await api.post('/wa/send', {
      phone: japriPhone.value,
      message: japriMessage.value,
      nama_penerima: selectedJapriSantri.value?.nama_lengkap,
      tipe_pesan: 'spp_japri',
    });

    if (res.data.success) {
      toast.success(res.data.message || 'Pesan pengingat berhasil dikirim via Fonnte!');
      closeJapriModal();
    } else {
      toast.error(res.data.message || 'Gagal mengirim pesan.');
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal mengirim via Gateway Fonnte. Periksa token di Pengaturan.';
    toast.error(msg);
  } finally {
    isSendingJapri.value = false;
  }
};

const openJapriWhatsAppDirect = () => {
  if (!japriPhone.value) {
    toast.warning('Nomor WhatsApp tujuan wajib diisi.');
    return;
  }
  let clean = japriPhone.value.replace(/[^0-9]/g, '');
  if (clean.startsWith('0')) {
    clean = '62' + clean.slice(1);
  }
  const url = `https://wa.me/${clean}?text=${encodeURIComponent(japriMessage.value)}`;
  window.open(url, '_blank');
};

const copyJapriText = () => {
  if (!japriMessage.value) return;
  navigator.clipboard.writeText(japriMessage.value);
  toast.success('Pesan pengingat disalin ke clipboard!');
};

const executeGenerate = async () => {
  isGenerating.value = true;
  try {
    const res = await api.post('/spp/generate-tagihan', genForm.value);
    if (res.data.success) {
      toast.success(res.data.message);
      showGenerateModal.value = false;
      filters.value.bulan = genForm.value.bulan;
      filters.value.tahun = genForm.value.tahun;
      loadTagihan(1);
      loadRekapTunggakan();
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal generate tagihan.');
  } finally {
    isGenerating.value = false;
  }
};

onMounted(() => {
  loadKelas();
  loadRekapTunggakan();
  loadTagihan(1);
});
</script>
