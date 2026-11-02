<template>
  <div class="space-y-4 animate-fadeIn">
    <!-- Header Page: Clean, Proportional & Tanpa Card Berlebihan -->
    <div class="flex flex-wrap items-center justify-between gap-2.5 print:hidden">
      <div>
        <h2 class="text-sm sm:text-base md:text-lg font-bold tracking-tight text-zinc-900 dark:text-white">
          Riwayat Transaksi SPP
        </h2>
      </div>

      <div class="flex items-center gap-1.5 sm:gap-2">
        <button
          @click="printLaporan"
          :disabled="pembayaranList.length === 0"
          class="h-8 px-2.5 sm:px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40 shadow-sm"
          title="Cetak Laporan Rekapitulasi Pembayaran SPP"
        >
          <BaseIcon name="printer" class="w-3.5 h-3.5 text-zinc-400" />
          <span class="hidden xs:inline sm:inline">Cetak Laporan</span>
          <span class="inline xs:hidden sm:hidden">Cetak</span>
        </button>

        <button
          @click="exportCsv"
          :disabled="pembayaranList.length === 0"
          class="h-8 px-2.5 sm:px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40 shadow-sm"
        >
          <BaseIcon name="download" class="w-3.5 h-3.5 text-zinc-400" />
          <span class="hidden xs:inline sm:inline">Ekspor CSV</span>
          <span class="inline xs:hidden sm:hidden">CSV</span>
        </button>

        <router-link
          to="/spp/input-cepat"
          class="h-8 px-2.5 sm:px-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm transition flex items-center gap-1.5"
        >
          <BaseIcon name="wallet" class="w-3.5 h-3.5" />
          <span>Bayar &rarr;</span>
        </router-link>
      </div>
    </div>

    <!-- Filter Card: Responsive Mobile 2 Kolom Sejajar & Proposional -->
    <div class="p-3 sm:p-4 rounded-xl sm:rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-2.5 sm:space-y-3 print:hidden">
      <!-- Baris 1: Dari Tanggal & Sampai Tanggal Sejajar 2 Kolom -->
      <div class="grid grid-cols-2 gap-2 sm:gap-3">
        <div>
          <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 mb-1">
            Dari Tanggal
          </label>
          <input
            v-model="filters.tanggal_mulai"
            type="date"
            @change="loadRiwayat(1)"
            class="w-full h-9 px-2.5 sm:px-3 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          />
        </div>

        <div>
          <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 mb-1">
            Sampai Tanggal
          </label>
          <input
            v-model="filters.tanggal_selesai"
            type="date"
            @change="loadRiwayat(1)"
            class="w-full h-9 px-2.5 sm:px-3 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          />
        </div>
      </div>

      <!-- Baris 2: Jilid/Kelas & Status Transaksi Sejajar 2 Kolom -->
      <div class="grid grid-cols-2 gap-2 sm:gap-3">
        <div>
          <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 mb-1">
            Jilid / Kelas
          </label>
          <select
            v-model="filters.id_kelas"
            @change="loadRiwayat(1)"
            class="w-full h-9 px-2.5 sm:px-3 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          >
            <option value="">Semua Kelas</option>
            <option v-for="k in kelasList" :key="k.id" :value="k.id">{{ k.nama_kelas }}</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 mb-1">
            Status Transaksi
          </label>
          <select
            v-model="filters.status_transaksi"
            @change="loadRiwayat(1)"
            class="w-full h-9 px-2.5 sm:px-3 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          >
            <option value="">Semua Status</option>
            <option value="valid">Valid / Sukses</option>
            <option value="dibatalkan">Dibatalkan</option>
          </select>
        </div>
      </div>

      <!-- Baris 3: Kolom Pencarian Nama Santri / No. Kuitansi -->
      <div>
        <label class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 mb-1">
          Pencarian Santri / No. Kuitansi
        </label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-2.5 sm:pl-3 flex items-center pointer-events-none text-zinc-400">
            <BaseIcon name="search" class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
          </span>
          <input
            v-model="filters.search"
            @input="onSearchInput"
            type="text"
            placeholder="Ketik nama santri, NISN, atau no. kuitansi..."
            class="w-full h-9 pl-8 sm:pl-9 pr-8 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:ring-2 focus:ring-emerald-500 focus:outline-none transition"
          />
          <button
            v-if="filters.search"
            @click="clearSearch"
            type="button"
            class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 cursor-pointer"
          >
            <BaseIcon name="x-circle" class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- KOP RESMI LAPORAN REKAPITULASI PEMBAYARAN SPP (Tampil saat cetak laporan rekap) -->
    <div v-if="!showKuitansiModal" class="hidden print:block mb-2 text-black">
      <div class="flex items-center justify-between gap-3 pb-1">
        <!-- Logo Lembaga di Kiri -->
        <div class="w-20 sm:w-24 shrink-0 flex items-center justify-center">
          <img
            :src="appLogo || '/LOGOTPQ.png'"
            alt="Logo Lembaga"
            class="max-h-16 max-w-20 sm:max-h-20 sm:max-w-24 object-contain"
          />
        </div>

        <!-- Teks Kop Surat di Tengah -->
        <div class="flex-1 text-center px-1">
          <h1 class="text-xl sm:text-2xl font-serif font-bold text-[#7b95c0] tracking-[0.16em] uppercase leading-none">
            YAYASAN NURUL JADID
          </h1>
          <h2 class="text-sm sm:text-[15px] font-serif font-bold text-[#52525b] tracking-[0.06em] uppercase leading-tight mt-1">
            TAMAN PENDIDIKAN AL-QUR'AN NURUL MUN'IM
          </h2>
          <h3 class="text-xs sm:text-[13px] font-serif font-bold text-[#52525b] tracking-[0.1em] uppercase leading-tight mt-0.5">
            KARANGANYAR PAITON PROBOLINGGO
          </h3>
          <p class="text-[9px] sm:text-[10px] font-serif italic text-[#cca843] leading-tight mt-0.5">
            Alamat: PP. Nurul Jadid PO. Box 1 Paiton Probolinggo 67291 Email: tpqnm.nurja@gmail.com
          </p>
        </div>

        <!-- Spacer Kanan Penyeimbang -->
        <div class="w-20 sm:w-24 shrink-0"></div>
      </div>

      <!-- Garis Ganda Kop Surat Resmi -->
      <div class="border-t-[2.5px] border-black mt-1"></div>
      <div class="border-t border-black mt-[1.5px] mb-2"></div>

      <!-- Judul Dokumen & Periode Laporan -->
      <div class="text-center my-1">
        <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wider underline">
          LAPORAN REKAPITULASI TRANSAKSI PEMBAYARAN SPP
        </h2>
        <div class="flex justify-between items-center text-[10px] mt-1 px-1 font-medium text-black">
          <div>
            <span><strong>Filter:</strong> {{ filters.tanggal_mulai ? filters.tanggal_mulai : 'Awal' }} s/d {{ filters.tanggal_selesai ? filters.tanggal_selesai : 'Sekarang' }}</span>
            <span class="mx-2">&bull;</span>
            <span><strong>Jilid:</strong> {{ getNamaKelas(filters.id_kelas) }}</span>
            <span class="mx-2" v-if="filters.status_transaksi">&bull;</span>
            <span v-if="filters.status_transaksi"><strong>Status:</strong> {{ filters.status_transaksi.toUpperCase() }}</span>
          </div>
          <div>
            <span><strong>Dicetak pada:</strong> {{ formattedCurrentDate }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Card -->
    <div
      class="rounded-xl sm:rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm overflow-hidden"
      :class="showKuitansiModal ? 'print:hidden' : 'print:border-none print:shadow-none print:p-0 print:overflow-visible'"
    >
      <!-- Loading State -->
      <div v-if="isLoading" class="p-12 text-center text-zinc-400 print:hidden">
        <BaseIcon name="refresh" class="w-8 h-8 mx-auto mb-2 animate-spin text-zinc-400" />
        <p class="text-sm font-medium">Memuat riwayat pembayaran SPP...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="pembayaranList.length === 0" class="p-12 text-center text-zinc-400 print:hidden">
        <BaseIcon name="riwayat" class="w-10 h-10 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
        <p class="text-sm font-semibold">Belum ada riwayat transaksi pembayaran sesuai filter.</p>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto print:overflow-visible">
        <table class="w-full text-left text-xs print:text-[10px]">
          <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 uppercase tracking-wider font-semibold border-b border-zinc-200/80 dark:border-zinc-800 print:bg-transparent print:text-black print:border-b-2 print:border-black">
            <tr>
              <th class="p-2.5 sm:p-3 print:p-1.5 font-bold">No. Kuitansi</th>
              <th class="p-2.5 sm:p-3 print:p-1.5 font-bold">Tanggal</th>
              <th class="p-2.5 sm:p-3 print:p-1.5 font-bold">Santri</th>
              <th class="p-2.5 sm:p-3 print:p-1.5 font-bold">Kelas</th>
              <th class="p-2.5 sm:p-3 print:p-1.5 font-bold">Tagihan</th>
              <th class="p-2.5 sm:p-3 print:p-1.5 text-right font-bold">Nominal</th>
              <th class="p-2.5 sm:p-3 print:p-1.5 font-bold">Metode</th>
              <th class="p-2.5 sm:p-3 print:p-1.5 font-bold">Petugas</th>
              <th class="p-2.5 sm:p-3 print:p-1.5 text-center font-bold">Status</th>
              <th class="p-2.5 sm:p-3 text-center print:hidden">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 print:divide-zinc-300">
            <tr
              v-for="p in pembayaranList"
              :key="p.id"
              :class="p.status_transaksi === 'dibatalkan' ? 'bg-rose-50/40 dark:bg-rose-950/20 opacity-70' : 'hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40'"
              class="transition print:text-black"
            >
              <td class="p-2.5 sm:p-3 print:p-1.5 font-mono font-bold text-zinc-900 dark:text-zinc-100 print:text-black whitespace-nowrap">
                {{ p.nomor_kuitansi }}
              </td>
              <td class="p-2.5 sm:p-3 print:p-1.5 text-zinc-500 print:text-black whitespace-nowrap">
                {{ formatTanggal(p.tanggal_bayar) }}
              </td>
              <td class="p-2.5 sm:p-3 print:p-1.5 min-w-[140px] sm:min-w-[180px] max-w-[220px]">
                <p class="font-bold text-zinc-900 dark:text-white print:text-black whitespace-normal break-words leading-tight">
                  {{ p.siswa?.nama_lengkap || '-' }}
                </p>
                <p class="text-[10px] text-zinc-400 print:text-zinc-600 font-mono truncate">NISN: {{ p.siswa?.nisn || '-' }}</p>
              </td>
              <td class="p-2.5 sm:p-3 print:p-1.5 font-semibold text-zinc-700 dark:text-zinc-300 print:text-black">
                {{ p.siswa?.kelas?.nama_kelas || '-' }}
              </td>
              <td class="p-2.5 sm:p-3 print:p-1.5 font-medium text-zinc-600 dark:text-zinc-400 print:text-black">
                {{ getNamaBulan(p.tagihan?.bulan) }} {{ p.tagihan?.tahun }}
              </td>
              <td class="p-2.5 sm:p-3 print:p-1.5 text-right font-bold text-emerald-600 dark:text-emerald-400 print:text-black whitespace-nowrap">
                Rp {{ formatRupiah(p.jumlah_bayar) }}
              </td>
              <td class="p-2.5 sm:p-3 print:p-1.5 text-zinc-600 dark:text-zinc-400 print:text-black font-medium">
                {{ p.metode_pembayaran }}
              </td>
              <td class="p-2.5 sm:p-3 print:p-1.5 text-zinc-500 print:text-black text-[11px] print:text-[10px]">
                {{ p.petugas?.name || '-' }}
              </td>
              <td class="p-2.5 sm:p-3 print:p-1.5 text-center whitespace-nowrap">
                <span
                  v-if="p.status_transaksi === 'valid' || p.status_transaksi === 'sukses'"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300 print:bg-transparent print:text-black print:border print:border-black"
                >
                  &check; Sukses
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300 print:bg-transparent print:text-black print:border print:border-black"
                  :title="p.alasan_pembatalan"
                >
                  &cross; Dibatalkan
                </span>
              </td>
              <td class="p-2.5 sm:p-3 text-center whitespace-nowrap print:hidden">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    @click="previewKuitansi(p)"
                    title="Cetak Ulang Kuitansi"
                    class="p-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 text-zinc-700 dark:text-zinc-300 text-xs transition cursor-pointer"
                  >
                    <BaseIcon name="printer" class="w-3.5 h-3.5" />
                  </button>

                  <button
                    v-if="canManageSpp && (p.status_transaksi === 'valid' || p.status_transaksi === 'sukses')"
                    @click="openBatalModal(p)"
                    title="Batalkan Transaksi"
                    class="p-1.5 rounded-lg border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-600 dark:text-rose-400 text-xs transition cursor-pointer"
                  >
                    <BaseIcon name="x-circle" class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Total Akumulasi (Hanya tampil saat dicetak laporan) -->
      <div v-if="!showKuitansiModal && pembayaranList.length > 0" class="hidden print:flex justify-between items-center px-4 py-2 border-t-2 border-black text-xs font-bold text-black">
        <span>Total Penerimaan Terbayar ({{ pembayaranList.length }} transaksi):</span>
        <span class="font-mono text-sm">Rp {{ formatRupiah(totalNominalHalaman) }}</span>
      </div>

      <!-- Pagination Footer -->
      <div v-if="totalPages > 1" class="p-3 sm:p-4 border-t border-zinc-100 dark:border-zinc-800 flex flex-wrap items-center justify-between gap-2 text-xs text-zinc-500 print:hidden">
        <div>
          Halaman {{ currentPage }} dari {{ totalPages }} ({{ totalRecords }} data)
        </div>
        <div class="flex items-center gap-1">
          <button
            :disabled="currentPage <= 1"
            @click="loadRiwayat(currentPage - 1)"
            class="h-8 px-2.5 sm:px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 disabled:opacity-40 cursor-pointer hover:bg-zinc-50 text-xs"
          >
            &larr; Prev
          </button>
          <button
            :disabled="currentPage >= totalPages"
            @click="loadRiwayat(currentPage + 1)"
            class="h-8 px-2.5 sm:px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 disabled:opacity-40 cursor-pointer hover:bg-zinc-50 text-xs"
          >
            Next &rarr;
          </button>
        </div>
      </div>
    </div>

    <!-- Blok Pengesahan Tanda Tangan Resmi Laporan Pembayaran (Cetak) -->
    <div v-if="!showKuitansiModal && pembayaranList.length > 0" class="hidden print:flex justify-between items-start mt-6 pt-2 text-black text-[10px] break-inside-avoid">
      <!-- Sisi Kiri: Mengetahui Kepala TPQ -->
      <div class="text-center min-w-[180px]">
        <p class="font-normal">Mengetahui,</p>
        <p class="font-semibold">Kepala TPQ</p>
        <div class="h-12"></div>
        <p class="font-bold underline tracking-wide">( Ny. Shofiyah )</p>
        <p class="text-[9px] text-neutral-600 mt-0.5">Kepala TPQ Nurul Mun'im</p>
      </div>

      <!-- Sisi Kanan: Bendahara TPQ -->
      <div class="text-center min-w-[180px]">
        <p class="font-normal">Paiton, {{ formattedCurrentDate }}</p>
        <p class="font-semibold">Bendahara TPQ</p>
        <div class="h-12"></div>
        <p class="font-bold underline tracking-wide">( {{ namaBendahara }} )</p>
        <p class="text-[9px] text-neutral-600 mt-0.5">Bendahara TPQ Nurul Mun'im</p>
      </div>
    </div>

    <!-- Kuitansi Modal Ready for Print -->
    <div
      v-if="showKuitansiModal && activeKuitansi"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fadeIn print:static print:p-0 print:bg-white print:backdrop-blur-none"
    >
      <div class="w-full max-w-xl p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl space-y-4 print:border-none print:shadow-none print:p-0 print:max-w-none">
        <!-- Printable Area -->
        <div id="printKuitansiArea" class="border border-zinc-300 dark:border-zinc-700 p-6 rounded-2xl bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 space-y-4 text-xs font-sans print:border print:border-black print:text-black print:bg-white print:p-5">
          <!-- Kop Surat Resmi TPQ Nurul Mun'im -->
          <div class="text-black dark:text-white print:text-black pb-1">
            <div class="flex items-center justify-between gap-3 pb-1">
              <!-- Logo Lembaga di Kiri -->
              <div class="w-16 sm:w-20 shrink-0 flex items-center justify-center">
                <img
                  :src="appLogo || '/LOGOTPQ.png'"
                  alt="Logo Lembaga"
                  class="max-h-16 max-w-20 object-contain"
                />
              </div>

              <!-- Teks Kop Surat di Tengah -->
              <div class="flex-1 text-center px-1">
                <h1 class="text-base sm:text-lg font-serif font-bold text-[#7b95c0] tracking-[0.16em] uppercase leading-none">
                  YAYASAN NURUL JADID
                </h1>
                <h2 class="text-xs sm:text-[13px] font-serif font-bold text-[#52525b] dark:text-zinc-300 print:text-[#52525b] tracking-[0.06em] uppercase leading-tight mt-1">
                  TAMAN PENDIDIKAN AL-QUR'AN NURUL MUN'IM
                </h2>
                <h3 class="text-[10px] sm:text-[11px] font-serif font-bold text-[#52525b] dark:text-zinc-400 print:text-[#52525b] tracking-[0.1em] uppercase leading-tight mt-0.5">
                  KARANGANYAR PAITON PROBOLINGGO
                </h3>
                <p class="text-[8px] sm:text-[9px] font-serif italic text-[#cca843] leading-tight mt-0.5">
                  Alamat: PP. Nurul Jadid PO. Box 1 Paiton Probolinggo 67291 Email: tpqnm.nurja@gmail.com
                </p>
              </div>

              <!-- Spacer Kanan Penyeimbang -->
              <div class="w-16 sm:w-20 shrink-0"></div>
            </div>

            <!-- Garis Ganda Kop Surat Resmi -->
            <div class="border-t-[2.5px] border-black dark:border-white print:border-black mt-1"></div>
            <div class="border-t border-black dark:border-white print:border-black mt-[1.5px] mb-2"></div>

            <!-- Judul Dokumen Kuitansi -->
            <div class="text-center my-1">
              <h2 class="text-xs font-bold uppercase tracking-wider underline">
                KUITANSI PEMBAYARAN SPP SANTRI
              </h2>
            </div>
          </div>

          <!-- Meta -->
          <div class="grid grid-cols-2 gap-2 text-[11px]">
            <div>
              <span class="text-zinc-400 dark:text-zinc-500 print:text-zinc-600 block text-[10px]">No. Kuitansi</span>
              <span class="font-mono font-bold">{{ activeKuitansi.nomor_kuitansi }}</span>
            </div>
            <div class="text-right">
              <span class="text-zinc-400 dark:text-zinc-500 print:text-zinc-600 block text-[10px]">Tanggal Bayar</span>
              <span class="font-bold">{{ formatTanggal(activeKuitansi.tanggal_bayar) }}</span>
            </div>
          </div>

          <!-- Rincian -->
          <div class="p-3 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 space-y-1.5 print:bg-white print:border-zinc-300">
            <div class="flex justify-between">
              <span class="text-zinc-500 print:text-zinc-600">Nama Santri:</span>
              <span class="font-bold">{{ activeKuitansi.siswa?.nama_lengkap }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-zinc-500 print:text-zinc-600">NISN / Jilid:</span>
              <span class="font-mono">{{ activeKuitansi.siswa?.nisn }} &bull; {{ activeKuitansi.siswa?.kelas?.nama_kelas || '-' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-zinc-500 print:text-zinc-600">Pembayaran Periode:</span>
              <span class="font-semibold">{{ getNamaBulan(activeKuitansi.tagihan?.bulan) }} {{ activeKuitansi.tagihan?.tahun }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-zinc-500 print:text-zinc-600">Metode Bayar:</span>
              <span class="font-semibold">{{ activeKuitansi.metode_pembayaran }}</span>
            </div>
            <div v-if="activeKuitansi.catatan" class="flex justify-between">
              <span class="text-zinc-500 print:text-zinc-600">Catatan:</span>
              <span class="italic text-zinc-600 dark:text-zinc-300 print:text-zinc-700">{{ activeKuitansi.catatan }}</span>
            </div>
            <div class="flex justify-between pt-1 border-t border-zinc-200 dark:border-zinc-800 print:border-zinc-300 text-sm font-bold text-emerald-600 print:text-black">
              <span>Jumlah Disetor:</span>
              <span>Rp {{ formatRupiah(activeKuitansi.jumlah_bayar) }}</span>
            </div>
          </div>

          <!-- Blok Pengesahan Tanda Tangan Resmi Kuitansi -->
          <div class="grid grid-cols-2 gap-4 text-center mt-4 pt-3 border-t border-zinc-200 dark:border-zinc-800 print:border-zinc-300 text-[10px]">
            <div>
              <p class="text-zinc-500 dark:text-zinc-400 print:text-zinc-600">Penyetor / Wali Santri,</p>
              <div class="h-10"></div>
              <p class="font-semibold text-zinc-800 dark:text-zinc-200 print:text-black">( ........................................ )</p>
            </div>
            <div>
              <p class="text-zinc-500 dark:text-zinc-400 print:text-zinc-600">Paiton, {{ formatTanggalRingkas(activeKuitansi.tanggal_bayar) }}</p>
              <p class="font-semibold text-zinc-700 dark:text-zinc-300 print:text-black">Bendahara TPQ,</p>
              <div class="h-10"></div>
              <p class="font-bold underline tracking-wide text-zinc-900 dark:text-white print:text-black">( {{ namaBendahara }} )</p>
              <p class="text-[8px] text-zinc-500 dark:text-zinc-400 print:text-zinc-600 mt-0.5">
                <span v-if="activeKuitansi.petugas?.name && activeKuitansi.petugas.name !== namaBendahara">
                  Petugas Input: {{ activeKuitansi.petugas.name }}
                </span>
                <span v-else>TPQ Nurul Mun'im</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Buttons (Hidden when printing) -->
        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800 print:hidden">
          <button
            type="button"
            @click="sendKuitansiViaWa"
            class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20 cursor-pointer flex items-center gap-1.5 transition"
            title="Kirim Kuitansi ke WhatsApp Wali Santri"
          >
            <BaseIcon name="whatsapp" class="w-4 h-4" />
            <span>Kirim WA ke Wali Santri</span>
          </button>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="showKuitansiModal = false"
              class="px-4 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 text-xs font-semibold cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition"
            >
              Tutup
            </button>
            <button
              type="button"
              @click="printKuitansi"
              class="px-4 py-2 rounded-xl bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 text-xs font-bold shadow-md cursor-pointer flex items-center gap-1.5 hover:opacity-90 transition"
            >
              <BaseIcon name="printer" class="w-4 h-4" />
              <span>Cetak Kuitansi</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Batalkan Pembayaran (Audit Trail) -->
    <div
      v-if="showBatalModal && targetBatal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fadeIn"
    >
      <div class="w-full max-w-md p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-rose-100 dark:bg-rose-950/60 flex items-center justify-center text-rose-600">
            <BaseIcon name="alert" class="w-5 h-5 text-rose-600" />
          </div>
          <div>
            <h3 class="text-base font-bold text-zinc-900 dark:text-white">Batalkan Transaksi Pembayaran?</h3>
            <p class="text-xs text-zinc-500">Kuitansi: <span class="font-mono font-bold">{{ targetBatal.nomor_kuitansi }}</span></p>
          </div>
        </div>

        <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
          Pembatalan transaksi akan mengembalikan status tagihan SPP santri <b>{{ targetBatal.siswa?.nama_lengkap }}</b> menjadi belum lunas / kurang bayar.
        </p>

        <div>
          <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1">
            Alasan Pembatalan (Audit Trail) *
          </label>
          <textarea
            v-model="alasanBatal"
            rows="3"
            placeholder="Contoh: Salah input nominal / Santri batal transfer..."
            class="w-full px-3 py-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-rose-500 focus:outline-none"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            @click="closeBatalModal"
            class="px-4 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 text-xs font-semibold text-zinc-700 dark:text-zinc-300 cursor-pointer"
          >
            Batal
          </button>
          <button
            @click="submitBatalPembayaran"
            :disabled="!alasanBatal.trim() || isCancelling"
            class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold shadow-md shadow-rose-600/20 flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
          >
            <BaseIcon v-if="isCancelling" name="refresh" class="w-4 h-4 animate-spin" />
            <span>Konfirmasi Batalkan</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';
import { useAuth } from '../../composables/useAuth';

const toast = useToast();
const { canManageSpp } = useAuth();

const namaBulanMap = {
  1: 'Januari', 2: 'Februari', 3: 'Maret', 4: 'April',
  5: 'Mei', 6: 'Juni', 7: 'Juli', 8: 'Agustus',
  9: 'September', 10: 'Oktober', 11: 'November', 12: 'Desember'
};

const kelasList = ref([]);
const pembayaranList = ref([]);
const isLoading = ref(false);
const namaBendahara = ref('Ust. Bendahara');
const appLogo = ref('/LOGOTPQ.png');

const currentPage = ref(1);
const totalPages = ref(1);
const totalRecords = ref(0);

const filters = ref({
  tanggal_mulai: '',
  tanggal_selesai: '',
  id_kelas: '',
  status_transaksi: '',
  search: '',
});

let searchDebounceTimer = null;
function onSearchInput() {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    loadRiwayat(1);
  }, 350);
}

function clearSearch() {
  filters.value.search = '';
  loadRiwayat(1);
}

// Modal Kuitansi
const showKuitansiModal = ref(false);
const activeKuitansi = ref(null);

// Modal Pembatalan
const showBatalModal = ref(false);
const targetBatal = ref(null);
const alasanBatal = ref('');
const isCancelling = ref(false);

const totalNominalHalaman = computed(() => {
  return pembayaranList.value.reduce((acc, curr) => {
    if (curr.status_transaksi === 'valid' || curr.status_transaksi === 'sukses') {
      return acc + (Number(curr.jumlah_bayar) || 0);
    }
    return acc;
  }, 0);
});

const formattedCurrentDate = computed(() => {
  const today = new Date();
  return today.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
});

function formatRupiah(val) {
  if (!val) return '0';
  return Number(val).toLocaleString('id-ID');
}

function formatTanggal(str) {
  if (!str) return '-';
  const cleanStr = String(str).replace(' ', 'T').replace('Z', '');
  const d = new Date(cleanStr);
  if (isNaN(d.getTime())) return str;
  return d.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).replace(/\./g, ':');
}

function formatTanggalRingkas(str) {
  if (!str) return formattedCurrentDate.value;
  const cleanStr = String(str).replace(' ', 'T').replace('Z', '');
  const d = new Date(cleanStr);
  if (isNaN(d.getTime())) return formattedCurrentDate.value;
  return d.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

function getNamaBulan(b) {
  return namaBulanMap[b] || `Bulan ${b}`;
}

function getNamaKelas(id) {
  if (!id) return 'Semua Jilid / Kelas';
  const found = kelasList.value.find(k => k.id == id);
  return found ? found.nama_kelas : 'Semua Jilid / Kelas';
}

async function loadKelas() {
  try {
    const res = await api.get('/kelas');
    kelasList.value = res.data.data || [];
  } catch (err) {
    console.error(err);
  }
}

async function loadRiwayat(page = 1) {
  isLoading.value = true;
  currentPage.value = page;

  try {
    const res = await api.get('/spp/riwayat', {
      params: {
        page,
        ...filters.value,
      }
    });

    const data = res.data.data;
    pembayaranList.value = data.data || [];
    totalPages.value = data.last_page || 1;
    totalRecords.value = data.total || 0;

    if (res.data?.nama_bendahara) {
      namaBendahara.value = res.data.nama_bendahara;
    }
  } catch (err) {
    toast.error('Gagal mengambil data riwayat pembayaran SPP.');
  } finally {
    isLoading.value = false;
  }
}

function previewKuitansi(p) {
  activeKuitansi.value = p;
  showKuitansiModal.value = true;
}

function printKuitansi() {
  window.print();
}

function printLaporan() {
  showKuitansiModal.value = false;
  window.print();
}

function sendKuitansiViaWa() {
  if (!activeKuitansi.value) return;
  const k = activeKuitansi.value;

  let phone = k.siswa?.no_hp_ortu;
  if (!phone) {
    phone = prompt('Nomor WhatsApp wali santri belum terdaftar. Masukkan nomor WhatsApp tujuan (contoh: 08123456789):');
  }
  if (!phone) return;

  let clean = phone.replace(/[^0-9]/g, '');
  if (clean.startsWith('0')) {
    clean = '62' + clean.slice(1);
  }

  const namaSantri = k.siswa?.nama_lengkap || '-';
  const nisn = k.siswa?.nisn || '-';
  const kelas = k.siswa?.kelas?.nama_kelas || '-';
  const bulanTagihan = `${getNamaBulan(k.tagihan?.bulan)} ${k.tagihan?.tahun || ''}`.trim();
  const tgl = formatTanggal(k.tanggal_bayar);
  const nominal = formatRupiah(k.jumlah_bayar);
  const petugasInput = k.petugas?.name || namaBendahara.value;

  const text = `*BUKTI PEMBAYARAN SPP TPQ*
---------------------------------------
No. Kuitansi : ${k.nomor_kuitansi}
Tanggal      : ${tgl}
Nama Santri  : ${namaSantri}
NISN / Jilid : ${nisn} / ${kelas}
Pembayaran   : SPP ${bulanTagihan}
Metode Bayar : ${k.metode_pembayaran || 'Tunai'}
Status       : ${k.status_transaksi?.toUpperCase() || 'LUNAS'}
---------------------------------------
*TOTAL DIBAYAR: Rp ${nominal}*
Bendahara    : ${namaBendahara.value}
${petugasInput !== namaBendahara.value ? 'Petugas Input: ' + petugasInput + '\n' : ''}${k.catatan ? 'Catatan      : ' + k.catatan + '\n' : ''}---------------------------------------
Terima kasih atas pembayaran SPP ananda. Semoga menjadi amal jariyah dan membawa barokah bagi ananda serta keluarga tercinta.

*TPQ Nurul Mun'im*
Karanganyar Paiton Probolinggo`;

  const url = `https://wa.me/${clean}?text=${encodeURIComponent(text)}`;
  window.open(url, '_blank');
}

function openBatalModal(p) {
  targetBatal.value = p;
  alasanBatal.value = '';
  showBatalModal.value = true;
}

function closeBatalModal() {
  showBatalModal.value = false;
  targetBatal.value = null;
  alasanBatal.value = '';
}

async function submitBatalPembayaran() {
  if (!targetBatal.value || !alasanBatal.value.trim()) return;

  isCancelling.value = true;
  try {
    const res = await api.post('/spp/batal-bayar', {
      id_pembayaran: targetBatal.value.id,
      alasan: alasanBatal.value.trim(),
    });

    toast.success(res.data.message || 'Pembayaran berhasil dibatalkan.');
    closeBatalModal();
    await loadRiwayat(currentPage.value);
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal membatalkan pembayaran.';
    toast.error(msg);
  } finally {
    isCancelling.value = false;
  }
}

function exportCsv() {
  if (pembayaranList.value.length === 0) return;

  const headers = ['No Kuitansi', 'Tanggal Bayar', 'NISN', 'Nama Santri', 'Kelas', 'Bulan Tagihan', 'Tahun', 'Nominal (Rp)', 'Metode', 'Petugas', 'Status'];
  const rows = pembayaranList.value.map(p => [
    `"${p.nomor_kuitansi}"`,
    `"${p.tanggal_bayar}"`,
    `"${p.siswa?.nisn || ''}"`,
    `"${p.siswa?.nama_lengkap || ''}"`,
    `"${p.siswa?.kelas?.nama_kelas || ''}"`,
    `"${getNamaBulan(p.tagihan?.bulan)}"`,
    `"${p.tagihan?.tahun || ''}"`,
    p.jumlah_bayar,
    `"${p.metode_pembayaran}"`,
    `"${p.petugas?.name || ''}"`,
    `"${p.status_transaksi}"`,
  ]);

  const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement('a');
  link.setAttribute('href', encodedUri);
  link.setAttribute('download', `Riwayat_SPP_${new Date().toISOString().slice(0, 10)}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

onMounted(async () => {
  loadKelas();
  loadRiwayat(1);

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
