<template>
  <div class="space-y-3.5 sm:space-y-4 animate-fadeIn">
    
    <!-- Header: Proporsional Tanpa Card dan Tanpa Penjelasan (Sesuai Halaman Input Pembayaran) -->
    <div class="flex items-center justify-between gap-2.5 print:hidden">
      <h2 class="text-sm sm:text-base md:text-lg font-bold tracking-tight text-foreground">
        Data Tagihan SPP
      </h2>
      <router-link
        v-if="!isKepala"
        to="/spp/input-cepat"
        class="h-8 px-2.5 sm:px-3 rounded-lg border border-border bg-card hover:bg-muted text-xs font-semibold text-foreground transition flex items-center gap-1.5 shadow-2xs shrink-0"
      >
        <Wallet class="size-3.5 text-emerald-600 dark:text-emerald-400" />
        <span class="hidden sm:inline">Input Pembayaran &rarr;</span>
        <span class="sm:hidden">Bayar &rarr;</span>
      </router-link>
    </div>

    <!-- 2 Summary Cards: Ultra Responsif pada Mobile & Desktop -->
    <div class="grid grid-cols-2 gap-2 sm:gap-3 print:hidden">
      <!-- Card 1: Total Santri Menunggak -->
      <Card class="p-3 sm:p-4 rounded-xl border border-border bg-card shadow-xs">
        <div class="flex items-center justify-between gap-1.5 mb-1 sm:mb-1.5">
          <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-muted-foreground truncate">
            Santri Nunggak
          </span>
          <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
            <Users class="size-3.5 sm:size-4" />
          </div>
        </div>
        <div class="text-base sm:text-xl font-extrabold text-rose-600 dark:text-rose-400 tracking-tight">
          {{ rekapSummary.total_santri_menunggak }} <span class="text-xs font-semibold">Santri</span>
        </div>
        <p class="text-[10px] sm:text-[11px] text-muted-foreground mt-0.5 truncate">
          Memiliki sisa tagihan aktif
        </p>
      </Card>

      <!-- Card 2: Total Nominal Tunggakan Riil -->
      <Card class="p-3 sm:p-4 rounded-xl border border-border bg-card shadow-xs">
        <div class="flex items-center justify-between gap-1.5 mb-1 sm:mb-1.5">
          <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-muted-foreground truncate">
            Total Tunggakan
          </span>
          <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
            <Wallet class="size-3.5 sm:size-4" />
          </div>
        </div>
        <div class="text-base sm:text-xl font-extrabold text-foreground tracking-tight truncate">
          Rp {{ formatRupiah(rekapSummary.total_nominal_tunggakan) }}
        </div>
        <p class="text-[10px] sm:text-[11px] text-muted-foreground mt-0.5 truncate">
          s/d {{ rekapSummary.nama_bulan_cut_off }} {{ rekapSummary.cut_off_tahun }}
        </p>
      </Card>
    </div>

    <!-- Kontainer Tunggal Terpadu Rekap Santri Menunggak (Filter & Tabel Menyatu Sesuai Model Data Santri) -->
    <Card class="overflow-hidden py-0 gap-0">
        <!-- Toolbar Atas di Dalam Kontainer Card -->
        <div class="p-2.5 sm:p-3.5 border-b border-border bg-card space-y-2 sm:space-y-2.5">
          <!-- Baris 1: Filter Jilid & Batas Cut-Off Sejajar di Mobile & Desktop -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <!-- Filter Jilid -->
            <div class="flex items-center gap-1.5 flex-1">
              <select
                id="filterKelasRekap"
                v-model="rekapKelasId"
                class="w-full sm:w-auto h-8 sm:h-9 rounded-lg border border-input bg-background px-2.5 sm:px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer min-w-[130px]"
                title="Filter Jilid"
                @change="loadRekapTunggakan"
              >
                <option value="">Semua Jilid</option>
                <option v-for="k in kelasList" :key="k.id" :value="k.id">
                  {{ getCleanNamaKelas(k.nama_kelas) }}
                </option>
              </select>
            </div>

            <!-- Batas Cut-Off -->
            <div class="grid grid-cols-2 sm:flex sm:items-center gap-1.5 shrink-0">
              <div class="flex items-center gap-1">
                <span class="text-xs text-muted-foreground hidden sm:inline whitespace-nowrap">Cut-Off:</span>
                <select
                  v-model.number="rekapCutOffBulan"
                  @change="loadRekapTunggakan"
                  class="w-full sm:w-auto h-8 sm:h-9 rounded-lg border border-input bg-background px-2 sm:px-2.5 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer font-medium"
                  title="Bulan Cut-Off"
                >
                  <option v-for="(nama, num) in bulanList" :key="num" :value="num">{{ nama }}</option>
                </select>
              </div>

              <select
                v-model.number="rekapCutOffTahun"
                @change="loadRekapTunggakan"
                class="w-full sm:w-24 h-8 sm:h-9 rounded-lg border border-input bg-background px-2 sm:px-2.5 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer font-medium"
                title="Tahun Cut-Off"
              >
                <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>

          <!-- Baris 2: Kolom Pencarian Full Width -->
          <div class="relative w-full">
            <Search class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
            <Input
              id="searchRekap"
              v-model="rekapSearch"
              type="text"
              placeholder="Cari nama santri atau NISN..."
              class="h-8 sm:h-9 pl-9 pr-7 text-xs bg-background w-full"
              @input="onRekapSearch"
            />
            <button
              v-if="rekapSearch"
              type="button"
              @click="rekapSearch = ''; onRekapSearch()"
              class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-muted-foreground hover:text-foreground text-sm"
              title="Hapus Pencarian"
            >
              &times;
            </button>
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

                <!-- Santri (Nama panjang otomatis turun ke baris ke-2 tanpa terpotong/tertutup) -->
                <TableCell class="min-w-[140px] sm:min-w-[180px] max-w-[220px]">
                  <div class="font-medium text-foreground whitespace-normal break-words leading-tight">
                    {{ s.nama_lengkap }}
                  </div>
                  <div class="flex items-center gap-1.5 text-muted-foreground font-mono text-[11px] mt-0.5 truncate">
                    <span>NISN: {{ s.nisn }}</span>
                    <span v-if="s.no_hp_ortu">&bull;</span>
                    <a
                      v-if="s.no_hp_ortu"
                      :href="'https://wa.me/' + cleanPhone(s.no_hp_ortu)"
                      target="_blank"
                      class="text-emerald-600 hover:underline inline-flex items-center gap-0.5 truncate"
                      title="Buka WA Ortu"
                    >
                      <span class="truncate">{{ s.no_hp_ortu }}</span>
                      <ExternalLink class="size-3 shrink-0" />
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
                  <!-- KHUSUS ROLE KEPALA: Hanya boleh melihat rincian tunggakan (Read-Only) -->
                  <div v-if="isKepala" class="flex items-center justify-center">
                    <Button
                      variant="outline"
                      size="sm"
                      class="h-7 px-2.5 text-xs text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40 border-blue-200 dark:border-blue-800 gap-1 font-semibold cursor-pointer"
                      title="Lihat Rincian Tunggakan"
                      @click="openDetailModal(s)"
                    >
                      <Eye class="size-3.5" />
                      <span>Lihat</span>
                    </Button>
                  </div>

                  <!-- ROLE LAIN (Admin, Bendahara, Sekretaris): Tetap memiliki tombol Bayar & Japri WA -->
                  <div v-else class="flex items-center justify-center gap-1.5">
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

    <!-- Modal Pop-up Japri WhatsApp Santri Menunggak -->
    <div
      v-if="showJapriModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-2.5 sm:p-4 bg-black/60 backdrop-blur-sm animate-fadeIn print:hidden"
    >
      <div class="w-full max-w-lg max-h-[92vh] flex flex-col rounded-2xl sm:rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl p-3.5 sm:p-6 text-xs animate-slideUp">
        <!-- Header Modal -->
        <div class="flex items-center justify-between pb-2.5 sm:pb-3 border-b border-zinc-100 dark:border-zinc-800 shrink-0">
          <div class="flex items-center gap-2 sm:gap-2.5">
            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
              <BaseIcon name="whatsapp" class="w-4 h-4 sm:w-5 sm:h-5" />
            </div>
            <div>
              <h3 class="text-xs sm:text-sm font-bold text-zinc-900 dark:text-white leading-tight">Japri Pengingat SPP Santri</h3>
              <p class="text-[10px] sm:text-[11px] text-zinc-500 truncate max-w-[200px] sm:max-w-none">Kirim pesan langsung ke wali santri</p>
            </div>
          </div>
          <button
            type="button"
            @click="closeJapriModal"
            class="w-7 h-7 rounded-lg flex items-center justify-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
            title="Tutup Modal"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Body (Scrollable in mobile) -->
        <div class="flex-1 overflow-y-auto my-2 pr-0.5 space-y-3 sm:space-y-4">
          <!-- Info Ringkas Santri -->
          <div v-if="selectedJapriSantri" class="p-3 sm:p-3.5 rounded-xl sm:rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/80 dark:border-zinc-700/60 space-y-2">
            <div class="flex items-center justify-between">
              <div>
                <h4 class="font-bold text-zinc-900 dark:text-white text-xs sm:text-sm">{{ selectedJapriSantri.nama_lengkap }}</h4>
                <p class="text-[10px] sm:text-[11px] text-zinc-400 font-mono">NISN: {{ selectedJapriSantri.nisn }} &bull; {{ selectedJapriSantri.nama_kelas }}</p>
              </div>
              <div class="text-right">
                <span class="px-2 py-0.5 rounded-full text-[9px] sm:text-[10px] font-extrabold uppercase bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
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
            <p v-if="!japriPhone" class="text-[10px] sm:text-[11px] text-rose-500 mt-1">
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
              rows="6"
              class="w-full p-3 rounded-xl sm:rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/60 font-mono text-xs text-zinc-800 dark:text-zinc-200 leading-relaxed focus:ring-2 focus:ring-emerald-500 focus:outline-none resize-y"
              placeholder="Draft pesan pengingat SPP..."
            ></textarea>
          </div>
        </div>

        <!-- Tombol Aksi (Fixed Sticky Footer) -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 pt-2.5 border-t border-zinc-100 dark:border-zinc-800 shrink-0">
          <div class="flex items-center gap-1.5 order-2 sm:order-1">
            <button
              type="button"
              @click="copyJapriText"
              :disabled="!japriMessage"
              class="flex-1 sm:flex-none px-3 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-[11px] font-semibold text-zinc-700 dark:text-zinc-200 transition flex items-center justify-center gap-1 cursor-pointer disabled:opacity-40"
              title="Salin isi pesan ke clipboard"
            >
              <Copy class="w-3.5 h-3.5" />
              <span>Salin</span>
            </button>
            <button
              type="button"
              @click="openJapriWhatsAppDirect"
              :disabled="!japriMessage || !japriPhone"
              class="flex-1 sm:flex-none px-3 py-2 rounded-xl bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 hover:opacity-90 text-[11px] font-bold transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-40"
              title="Buka chat WhatsApp Web / Desktop"
            >
              <BaseIcon name="whatsapp" class="w-3.5 h-3.5 text-emerald-500" />
              <span>Buka di WA</span>
            </button>
          </div>

          <div class="flex items-center gap-2 order-1 sm:order-2">
            <button
              type="button"
              @click="closeJapriModal"
              class="flex-1 sm:flex-none px-3.5 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 text-[11px] font-semibold text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
            >
              Tutup
            </button>
            <button
              type="button"
              @click="sendJapriViaFonnte"
              :disabled="!japriMessage || !japriPhone || isSendingJapri"
              class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] shadow-md shadow-emerald-600/20 transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <RefreshCw v-if="isSendingJapri" class="w-3.5 h-3.5 animate-spin" />
              <Send v-else class="w-3.5 h-3.5" />
              <span>Kirim Gateway</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Pop-up Detail Rincian Tunggakan SPP (Khusus Role Kepala - Read Only) -->
    <div
      v-if="showDetailModal && selectedDetailSantri"
      class="fixed inset-0 z-50 flex items-center justify-center p-2.5 sm:p-4 bg-black/60 backdrop-blur-sm animate-fadeIn"
    >
      <div
        class="w-full max-w-lg max-h-[90vh] flex flex-col rounded-2xl sm:rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl p-4 sm:p-5 text-xs animate-slideUp"
      >
        <!-- Header Modal -->
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800 shrink-0">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
              <Eye class="w-4 h-4 sm:w-5 sm:h-5" />
            </div>
            <div>
              <div class="flex items-center gap-1.5 flex-wrap">
                <h3 class="text-xs sm:text-sm font-bold text-zinc-900 dark:text-white leading-tight">
                  Rincian Tunggakan Santri
                </h3>
                <span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800/80">
                  Hanya Lihat
                </span>
              </div>
              <p class="text-[10px] sm:text-[11px] text-zinc-500 truncate">Mode Pengawasan &amp; Supervisi Kepala TPQ</p>
            </div>
          </div>
          <button
            type="button"
            @click="closeDetailModal"
            class="w-7 h-7 rounded-lg flex items-center justify-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
            title="Tutup Modal"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Body Scrollable -->
        <div class="flex-1 overflow-y-auto my-3 pr-0.5 space-y-3">
          <!-- Info Santri -->
          <div class="p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/80 dark:border-zinc-700/60 flex items-center justify-between">
            <div>
              <h4 class="font-bold text-zinc-900 dark:text-white text-xs sm:text-sm">
                {{ selectedDetailSantri.nama_lengkap }}
              </h4>
              <p class="text-[10px] sm:text-[11px] text-zinc-400 font-mono">
                NISN: {{ selectedDetailSantri.nisn }} &bull; {{ selectedDetailSantri.nama_kelas }}
              </p>
            </div>
            <div class="text-right">
              <span v-if="selectedDetailSantri.no_hp_ortu" class="text-[10px] sm:text-[11px] text-zinc-500 font-mono flex items-center gap-1 justify-end">
                <BaseIcon name="whatsapp" class="w-3.5 h-3.5 text-emerald-500" />
                {{ selectedDetailSantri.no_hp_ortu }}
              </span>
              <span v-else class="text-[10px] text-zinc-400 italic">No WA belum ada</span>
            </div>
          </div>

          <!-- Loading state -->
          <div v-if="isLoadingDetailBills" class="p-6 text-center rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-800 text-zinc-400">
            <RefreshCw class="w-5 h-5 mx-auto mb-1 animate-spin text-blue-600" />
            <p class="text-[11px]">Memuat rincian bulan tagihan...</p>
          </div>

          <!-- Daftar Rincian Bulan Menunggak -->
          <div v-else class="space-y-1.5">
            <label class="font-bold text-zinc-700 dark:text-zinc-300 text-xs block mb-1">
              Daftar Bulan Belum Lunas ({{ detailTagihanList.length }} Bulan)
            </label>
            <div v-if="detailTagihanList.length === 0" class="p-4 text-center rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-800 text-zinc-400 text-xs">
              Santri tidak memiliki tunggakan tagihan aktif.
            </div>
            <div v-else class="max-h-52 overflow-y-auto space-y-1.5 pr-1">
              <div
                v-for="b in detailTagihanList"
                :key="b.id_tagihan"
                class="flex items-center justify-between p-2.5 rounded-xl border border-zinc-200 dark:border-zinc-700/70 bg-white dark:bg-zinc-800/60"
              >
                <div>
                  <span class="font-bold text-zinc-900 dark:text-zinc-100 text-xs">
                    {{ b.label || (b.nama_bulan + ' ' + b.tahun) }}
                  </span>
                  <span
                    v-if="b.sudah_dibayar && b.sudah_dibayar > 0"
                    class="ml-2 px-1.5 py-0.5 rounded text-[10px] bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 font-medium"
                  >
                    Kurang: Rp {{ formatRupiah(b.sisa || b.nominal_tagihan) }}
                  </span>
                  <span
                    v-else
                    class="ml-2 px-1.5 py-0.5 rounded text-[10px] bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 font-medium"
                  >
                    Belum Bayar
                  </span>
                </div>
                <div class="text-right">
                  <span class="font-mono font-bold text-xs text-rose-600 dark:text-rose-400">
                    Rp {{ formatRupiah(b.sisa || b.nominal_tagihan) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Total Ringkasan -->
          <div class="p-3 rounded-xl bg-zinc-100 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
            <div>
              <p class="text-[11px] font-bold text-zinc-700 dark:text-zinc-300">Total Akumulasi Tunggakan</p>
              <p class="text-[10px] text-zinc-400 mt-0.5">{{ detailTagihanList.length }} Bulan tagihan</p>
            </div>
            <p class="text-base sm:text-lg font-black text-rose-600 dark:text-rose-400 font-mono">
              Rp {{ formatRupiah(selectedDetailSantri.total_tunggakan) }}
            </p>
          </div>

          <!-- Catatan Hak Akses Kepala TPQ -->
          <div class="p-2.5 rounded-lg bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/60 dark:border-blue-900/40 text-[11px] text-blue-800 dark:text-blue-300 leading-relaxed">
            <span class="font-bold">&bull; Informasi Hak Akses:</span> Akun Kepala TPQ hanya memiliki wewenang pengawasan untuk melihat data tunggakan (read-only). Perubahan status pembayaran hanya dapat diproses oleh Bendahara atau Sekretaris.
          </div>
        </div>

        <!-- Footer Modal: Hanya tombol Tutup (Tidak ada tombol aksi ubah/bayar) -->
        <div class="flex items-center justify-end pt-2.5 border-t border-zinc-100 dark:border-zinc-800 shrink-0">
          <button
            type="button"
            @click="closeDetailModal"
            class="px-4 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white dark:bg-zinc-100 dark:hover:bg-white dark:text-zinc-900 font-bold text-xs shadow-sm transition cursor-pointer"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Pop-up Pembayaran SPP Multi-Bulan (Printable Kuitansi) -->
    <div
      v-if="showBayarModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-2.5 sm:p-4 bg-black/60 backdrop-blur-sm animate-fadeIn print:p-0 print:bg-white print:static"
    >
      <div
        class="w-full max-w-xl max-h-[92vh] flex flex-col rounded-2xl sm:rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl p-3.5 sm:p-5 text-xs animate-slideUp print:border-none print:shadow-none print:p-0 print:max-w-none print:max-h-none"
      >
        <!-- STATE 2: BUKTI KUITANSI PEMBAYARAN RESMI -->
        <template v-if="bayarSuccessResult">
          <!-- Header Info Kuitansi (Fixed Top) -->
          <div class="flex items-center justify-between pb-2.5 border-b border-zinc-100 dark:border-zinc-800 shrink-0 print:hidden">
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                <CheckCircle2 class="w-4 h-4 text-emerald-600" />
              </div>
              <div>
                <h3 class="text-xs sm:text-sm font-bold text-zinc-900 dark:text-white leading-tight">Bukti Kuitansi Pembayaran SPP</h3>
                <p class="text-[10px] text-zinc-400">Kuitansi resmi terverifikasi sistem</p>
              </div>
            </div>

            <button
              type="button"
              @click="closeBayarModal"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition cursor-pointer"
              title="Tutup Modal"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Kuitansi Printable Body (Scrollable on small screens) -->
          <div class="flex-1 overflow-y-auto my-2 pr-0.5 print:overflow-visible print:my-0 print:pr-0">
            <div
              id="multiKuitansiArea"
              class="relative border border-zinc-200 dark:border-zinc-700 rounded-xl sm:rounded-2xl p-3 sm:p-5 bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 space-y-2.5 sm:space-y-3.5 print:border print:border-black print:text-black print:bg-white print:p-5"
            >
              <!-- Badge Cap Sah Lunas saat Dicetak -->
              <div class="absolute right-4 top-20 pointer-events-none border-2 border-emerald-700 text-emerald-800 font-black text-xs px-2.5 py-0.5 rounded rotate-[-8deg] uppercase tracking-widest hidden print:block opacity-80">
                LUNAS &bull; SAH
              </div>

              <!-- Kop Surat Resmi TPQ Nurul Mun'im -->
              <div class="text-black dark:text-white print:text-black pb-1">
                <div class="flex items-center justify-between gap-2 pb-1">
                  <!-- Logo Lembaga di Kiri -->
                  <div class="w-12 h-12 sm:w-16 sm:h-16 shrink-0 flex items-center justify-center">
                    <img
                      :src="appLogo || '/LOGOTPQ.png'"
                      alt="Logo Lembaga"
                      class="max-h-12 max-w-12 sm:max-h-16 sm:max-w-16 object-contain"
                    />
                  </div>

                  <!-- Teks Kop Surat di Tengah -->
                  <div class="flex-1 text-center px-0.5 min-w-0">
                    <h1 class="text-[13px] sm:text-base font-serif font-bold text-[#7b95c0] tracking-wide uppercase leading-tight">
                      YAYASAN NURUL JADID
                    </h1>
                    <h2 class="text-[10px] sm:text-xs font-serif font-bold text-[#52525b] dark:text-zinc-300 print:text-[#52525b] tracking-wide uppercase leading-tight mt-0.5">
                      TAMAN PENDIDIKAN AL-QUR'AN NURUL MUN'IM
                    </h2>
                    <h3 class="text-[9px] sm:text-[10px] font-serif font-bold text-[#52525b] dark:text-zinc-400 print:text-[#52525b] tracking-wider uppercase leading-tight mt-0.5">
                      KARANGANYAR PAITON PROBOLINGGO
                    </h3>
                    <p class="text-[7.5px] sm:text-[8.5px] font-serif italic text-[#cca843] leading-tight mt-0.5">
                      Alamat: PP. Nurul Jadid PO. Box 1 Paiton Probolinggo 67291
                    </p>
                  </div>

                  <!-- Spacer Kanan Penyeimbang -->
                  <div class="w-12 sm:w-16 shrink-0"></div>
                </div>

                <!-- Garis Ganda Kop Surat Resmi -->
                <div class="border-t-[2px] border-black dark:border-white print:border-black mt-1"></div>
                <div class="border-t border-black dark:border-white print:border-black mt-[1px] mb-2"></div>

                <!-- Judul Dokumen Kuitansi -->
                <div class="text-center my-0.5">
                  <h4 class="text-[11px] sm:text-xs font-bold uppercase tracking-wider underline">
                    KUITANSI PEMBAYARAN SPP RESMI
                  </h4>
                </div>
              </div>

              <!-- Meta Kuitansi -->
              <div class="grid grid-cols-2 gap-2 text-[10.5px] sm:text-[11px]">
                <div>
                  <span class="text-zinc-400 dark:text-zinc-500 print:text-zinc-600 block text-[9.5px]">No. Kuitansi</span>
                  <span class="font-mono font-bold">{{ bayarSuccessResult.nomor_kuitansi }}</span>
                </div>
                <div class="text-right">
                  <span class="text-zinc-400 dark:text-zinc-500 print:text-zinc-600 block text-[9.5px]">Tanggal Bayar</span>
                  <span class="font-bold">{{ bayarSuccessResult.tanggal_bayar }}</span>
                </div>
              </div>

              <!-- Receipt Details Table -->
              <div class="p-2.5 sm:p-3 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 space-y-1.5 print:bg-white print:border-zinc-300">
                <div class="flex justify-between">
                  <span class="text-zinc-500 print:text-zinc-600">Nama Santri:</span>
                  <span class="font-bold text-right">{{ bayarSuccessResult.nama_santri }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-zinc-500 print:text-zinc-600">NISN / Jilid:</span>
                  <span class="font-mono">{{ bayarSuccessResult.nisn }} &bull; {{ bayarSuccessResult.kelas }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-zinc-500 print:text-zinc-600">Pembayaran Periode:</span>
                  <span class="font-bold text-emerald-600 dark:text-emerald-400 text-right max-w-[220px] truncate" :title="bayarSuccessResult.daftar_bulan_str">
                    {{ bayarSuccessResult.periode || bayarSuccessResult.periode_ringkas || bayarSuccessResult.daftar_bulan_str || (bayarSuccessResult.daftar_bulan ? bayarSuccessResult.daftar_bulan.map(b => b.label || b).join(', ') : '-') }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-zinc-500 print:text-zinc-600">Jumlah Bulan:</span>
                  <span class="font-semibold">{{ bayarSuccessResult.total_bulan || (bayarSuccessResult.daftar_bulan ? bayarSuccessResult.daftar_bulan.length : 1) }} Bulan</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-zinc-500 print:text-zinc-600">Metode Bayar:</span>
                  <span class="font-semibold">{{ bayarSuccessResult.metode }}</span>
                </div>
                <div v-if="bayarSuccessResult.catatan" class="flex justify-between">
                  <span class="text-zinc-500 print:text-zinc-600">Catatan:</span>
                  <span class="italic text-zinc-600 dark:text-zinc-300 print:text-zinc-700">{{ bayarSuccessResult.catatan }}</span>
                </div>
                <div class="flex justify-between pt-1.5 border-t border-zinc-200 dark:border-zinc-800 print:border-zinc-300 text-xs sm:text-sm font-bold text-emerald-600 print:text-black">
                  <span>TOTAL DIBAYAR:</span>
                  <span class="font-mono text-sm sm:text-base font-black">Rp {{ formatRupiah(bayarSuccessResult.total_bayar) }}</span>
                </div>
                <div class="flex justify-between text-[10px] text-zinc-500 print:text-zinc-600">
                  <span>Status Transaksi:</span>
                  <span class="font-bold text-emerald-600 dark:text-emerald-400 uppercase">LUNAS</span>
                </div>
              </div>

              <!-- Signatures Footer -->
              <div class="grid grid-cols-2 gap-2 text-center mt-3 pt-2 border-t border-zinc-200 dark:border-zinc-800 print:border-zinc-300 text-[10px]">
                <div>
                  <p class="text-zinc-500 dark:text-zinc-400 print:text-zinc-600 text-[9px] sm:text-[10px]">Penyetor / Wali Santri,</p>
                  <div class="h-8 sm:h-10"></div>
                  <p class="font-semibold text-zinc-800 dark:text-zinc-200 print:text-black text-[9px] sm:text-[10px]">
                    ( ............................. )
                  </p>
                </div>
                <div>
                  <p class="text-zinc-500 dark:text-zinc-400 print:text-zinc-600 text-[9px] sm:text-[10px]">
                    Paiton, {{ formatTanggalRingkas(bayarSuccessResult.tanggal_bayar) }}
                  </p>
                  <p class="font-semibold text-zinc-700 dark:text-zinc-300 print:text-black text-[9px] sm:text-[10px]">
                    Bendahara TPQ,
                  </p>
                  <div class="h-8 sm:h-10"></div>
                  <p class="font-bold underline tracking-wide text-zinc-900 dark:text-white print:text-black text-[9px] sm:text-[10px]">
                    ( {{ bayarSuccessResult.nama_bendahara || namaBendahara }} )
                  </p>
                  <p class="text-[8px] text-zinc-500 dark:text-zinc-400 print:text-zinc-600 mt-0.5" v-if="bayarSuccessResult.petugas && bayarSuccessResult.petugas !== (bayarSuccessResult.nama_bendahara || namaBendahara)">
                    Petugas Input: {{ bayarSuccessResult.petugas }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Sticky Action Buttons Post-Payment -->
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 pt-2.5 border-t border-zinc-100 dark:border-zinc-800 shrink-0 print:hidden">
            <button
              type="button"
              @click="sendKuitansiViaWa"
              class="h-8 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm transition flex items-center justify-center gap-1.5 cursor-pointer order-1 sm:order-none"
              title="Kirim Bukti Kuitansi ke WhatsApp Wali Santri"
            >
              <BaseIcon name="whatsapp" class="w-3.5 h-3.5" />
              <span>Kirim WA ke Wali Santri</span>
            </button>

            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="closeBayarModal"
                class="flex-1 sm:flex-none h-8 px-3.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 text-xs font-semibold hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition cursor-pointer"
              >
                Tutup
              </button>
              <button
                type="button"
                @click="printKuitansi"
                class="flex-1 sm:flex-none h-8 px-3.5 rounded-lg bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 text-xs font-bold shadow-sm hover:opacity-90 transition flex items-center justify-center gap-1.5 cursor-pointer"
                title="Cetak Kuitansi Resmi"
              >
                <Printer class="w-3.5 h-3.5" />
                <span>Cetak</span>
              </button>
            </div>
          </div>
        </template>

        <!-- STATE 1: FORM PEMBAYARAN MULTI-BULAN -->
        <template v-else>
          <!-- Header Modal -->
          <div class="flex items-center justify-between pb-2.5 sm:pb-3 border-b border-zinc-100 dark:border-zinc-800 shrink-0">
            <div class="flex items-center gap-2 sm:gap-2.5">
              <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <Wallet class="w-4 h-4 sm:w-5 sm:h-5" />
              </div>
              <div>
                <h3 class="text-xs sm:text-sm font-bold text-zinc-900 dark:text-white leading-tight">Pembayaran SPP Santri</h3>
                <p class="text-[10px] sm:text-[11px] text-zinc-500 truncate max-w-[200px] sm:max-w-none">Pilih bulan kewajiban untuk pelunasan</p>
              </div>
            </div>
            <button
              type="button"
              @click="closeBayarModal"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Body Scrollable -->
          <div class="flex-1 overflow-y-auto my-2 pr-0.5 space-y-3">
            <!-- Info Ringkas Santri -->
            <div v-if="selectedBayarSantri" class="p-3 sm:p-3.5 rounded-xl sm:rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/80 dark:border-zinc-700/60 flex items-center justify-between">
              <div>
                <h4 class="font-bold text-zinc-900 dark:text-white text-xs sm:text-sm">{{ selectedBayarSantri.nama_lengkap }}</h4>
                <p class="text-[10px] sm:text-[11px] text-zinc-400 font-mono">NISN: {{ selectedBayarSantri.nisn }} &bull; {{ selectedBayarSantri.nama_kelas }}</p>
              </div>
              <div class="text-right">
                <span v-if="selectedBayarSantri.no_hp_ortu" class="text-[10px] sm:text-[11px] text-zinc-500 font-mono flex items-center gap-1 justify-end">
                  <BaseIcon name="whatsapp" class="w-3.5 h-3.5 text-emerald-500" />
                  {{ selectedBayarSantri.no_hp_ortu }}
                </span>
                <span v-else class="text-[10px] text-zinc-400 italic">No WA belum ada</span>
              </div>
            </div>

            <!-- Checklist Bulan Tagihan -->
            <div class="space-y-1.5 sm:space-y-2">
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
            <div class="p-3 sm:p-3.5 rounded-xl sm:rounded-2xl bg-emerald-50/90 dark:bg-emerald-950/30 border border-emerald-200/80 dark:border-emerald-800/60 flex items-center justify-between">
              <div>
                <p class="text-[11px] font-bold text-emerald-800 dark:text-emerald-400">Total Yang Akan Dibayar</p>
                <p class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                  {{ selectedTagihanIds.length }} Bulan kewajiban SPP terpilih
                </p>
              </div>
              <div class="text-right">
                <p class="text-base sm:text-lg font-black text-emerald-600 dark:text-emerald-400 font-mono">
                  Rp {{ formatRupiah(totalNominalBayarModal) }}
                </p>
              </div>
            </div>

            <!-- Form Metode & Catatan -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
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
          </div>

          <!-- Footer Aksi (Sticky Bottom) -->
          <div class="flex items-center justify-end gap-2 pt-2.5 border-t border-zinc-100 dark:border-zinc-800 shrink-0">
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
        </template>
      </div>
    </div>

    <!-- Modal Kirim Kuitansi WhatsApp Gambar -->
    <KirimWaKuitansiModal
      :show="showKirimWaModal"
      :kuitansi-data="kuitansiDataForWa"
      @close="showKirimWaModal = false"
    />

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';
import { useAuth } from '../../composables/useAuth';
import BaseIcon from '../../components/BaseIcon.vue';
import KirimWaKuitansiModal from '../../components/KirimWaKuitansiModal.vue';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Search,
  LoaderCircle,
  CheckCircle2,
  RefreshCw,
  Wallet,
  Users,
  X,
  ExternalLink,
  Copy,
  Printer,
  Send,
  Eye,
} from '@lucide/vue';

const route = useRoute();
const toast = useToast();
const { canManageSpp, isKepala } = useAuth();

// Data Rekap Santri Menunggak
const kelasList = ref([]);
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

const syncTahunAjaranOtomatis = async () => {
  try {
    await api.post('/spp/sinkronkan-semester', {
      from_bulan: 7,
      from_tahun: 2026,
      to_bulan: 6,
      to_tahun: 2027,
    });
  } catch (e) {
    // Background sync silent
  }
};

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

const namaBendahara = ref('Ust. Bendahara');
const appLogo = ref('/LOGOTPQ.png');

const formatTanggalRingkas = (str) => {
  if (!str) {
    const today = new Date();
    return today.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
  }
  const cleanStr = String(str).replace(' ', 'T').replace('Z', '');
  const d = new Date(cleanStr);
  if (isNaN(d.getTime())) return str;
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
};

const bulanList = {
  1: 'Januari', 2: 'Februari', 3: 'Maret', 4: 'April',
  5: 'Mei', 6: 'Juni', 7: 'Juli', 8: 'Agustus',
  9: 'September', 10: 'Oktober', 11: 'November', 12: 'Desember'
};

const formatRupiah = (val) => new Intl.NumberFormat('id-ID').format(val || 0);

const loadKelas = async () => {
  try {
    const res = await api.get('/kelas');
    if (res.data.success) {
      kelasList.value = res.data.data;
    }
  } catch (e) {}
};

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
const showKirimWaModal = ref(false);

const kuitansiDataForWa = computed(() => {
  if (!bayarSuccessResult.value) return null;
  const k = bayarSuccessResult.value;
  const smartPeriode = k.periode || k.periode_ringkas || k.daftar_bulan_str || (k.daftar_bulan ? k.daftar_bulan.map(b => b.label || b).join(', ') : '-');
  const fullBulanStr = k.daftar_bulan_str || (k.daftar_bulan ? k.daftar_bulan.map(b => b.label || b).join(', ') : '-');
  const totalNominal = k.total_bayar ?? k.jumlah_bayar ?? 0;

  return {
    ...k,
    id_pembayaran: k.id_pembayaran,
    invoice_token: k.invoice_token,
    secure_invoice_url: k.secure_invoice_url || k.invoice_url,
    invoice_url: k.invoice_url || k.secure_invoice_url,
    cloudinary_url: k.cloudinary_url,
    nomor_kuitansi: k.nomor_kuitansi,
    nomor_kuitansi_display: k.nomor_kuitansi,
    tanggal_bayar: k.tanggal_bayar,
    nama_santri: k.nama_santri,
    nisn: k.nisn,
    kelas: k.kelas,
    periode: smartPeriode,
    daftar_bulan_str: fullBulanStr,
    bulan_tagihan: smartPeriode,
    pembayaran_untuk: `SPP ${String(smartPeriode).replace(/^SPP\s+/i, '')}`.trim(),
    metode: k.metode,
    jumlah_bayar: totalNominal,
    total_bayar: totalNominal,
    total_bulan: k.total_bulan || (k.daftar_bulan ? k.daftar_bulan.length : 1),
    is_multi_bulan: Boolean(k.total_bulan > 1 || k.daftar_bulan?.length > 1),
    catatan: k.catatan,
    nama_bendahara: k.nama_bendahara || namaBendahara.value,
    no_hp_ortu: selectedBayarSantri.value?.no_hp_ortu || k.no_hp_ortu || '',
    appLogo: appLogo.value,
  };
});

const totalNominalBayarModal = computed(() => {
  return bayarTagihanList.value
    .filter(b => selectedTagihanIds.value.includes(b.id_tagihan))
    .reduce((sum, b) => sum + (Number(b.sisa) || Number(b.nominal_tagihan) || 0), 0);
});

// State Modal Detail Rincian (Khusus Role Kepala - Read-Only)
const showDetailModal = ref(false);
const selectedDetailSantri = ref(null);
const detailTagihanList = ref([]);
const isLoadingDetailBills = ref(false);

const openDetailModal = async (santri) => {
  selectedDetailSantri.value = {
    id_siswa: santri.id_siswa,
    nama_lengkap: santri.nama_lengkap,
    nisn: santri.nisn,
    nama_kelas: santri.nama_kelas,
    no_hp_ortu: santri.no_hp_ortu,
    total_tunggakan: santri.total_tunggakan,
    jumlah_bulan: santri.jumlah_bulan,
  };
  detailTagihanList.value = santri.daftar_bulan || [];
  isLoadingDetailBills.value = true;
  showDetailModal.value = true;

  try {
    const res = await api.get(`/spp/santri/${santri.id_siswa}/tunggakan?all=1`);
    if (res.data.success && (res.data.data?.daftar_tunggakan || res.data.data?.daftar_bulan)) {
      const allBills = res.data.data.daftar_tunggakan || res.data.data.daftar_bulan || [];
      if (allBills.length > 0) {
        detailTagihanList.value = allBills;
      }
    }
  } catch (err) {
    // Fallback data santri.daftar_bulan
  } finally {
    isLoadingDetailBills.value = false;
  }
};

const closeDetailModal = () => {
  showDetailModal.value = false;
  selectedDetailSantri.value = null;
  detailTagihanList.value = [];
};

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
      try {
        await loadRekapTunggakan();
      } catch (reloadErr) {
        // Abaikan jika reload rekap gagal sesaat
      }
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
  showKirimWaModal.value = true;
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

onMounted(async () => {
  loadKelas();
  // Otomatis sinkronkan dengan tahun ajaran aktif secara silent
  await syncTahunAjaranOtomatis();
  loadRekapTunggakan();

  try {
    const pub = await api.get('/pengaturan/public');
    if (pub.data?.data?.nama_bendahara) {
      namaBendahara.value = pub.data.data.nama_bendahara;
    }
    if (pub.data?.data?.logo_lembaga) {
      appLogo.value = pub.data.data.logo_lembaga;
    }
  } catch (e) {
    // fallback
  }
});
</script>
