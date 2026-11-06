<template>
  <div class="space-y-3.5 sm:space-y-4 animate-fadeIn">
    <!-- Header: Proporsional, Rapi & Elegan pada Mobile & Desktop -->
    <div class="flex items-center justify-between gap-2.5 print:hidden">
      <h2 class="text-sm sm:text-base md:text-lg font-bold tracking-tight text-zinc-900 dark:text-white">
        Input Pembayaran SPP
      </h2>
      <router-link
        to="/spp/tagihan"
        class="h-8 px-2.5 sm:px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition flex items-center gap-1.5 shadow-2xs shrink-0"
      >
        <BaseIcon name="tagihan" class="w-3.5 h-3.5 text-zinc-600 dark:text-zinc-300" />
        <span class="hidden sm:inline">Lihat Data Tagihan &rarr;</span>
        <span class="sm:hidden">Tagihan &rarr;</span>
      </router-link>
    </div>

    <!-- Parameter Filter Box:
         - Baris 1: Jilid & Bulan Sejajar 2 Kolom (Bahkan pada Mobile)
         - Baris 2: Tahun & Metode Sejajar 2 Kolom Kanan-Kiri (Bahkan pada Mobile)
         - Baris 3: Pencarian Nama Santri Full Width
    -->
    <div class="p-3 sm:p-4 rounded-xl sm:rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-xs space-y-2.5 sm:space-y-3 print:hidden">
      <!-- Baris 1: Jilid & Bulan Sejajar 2 Kolom di Mobile & Desktop -->
      <div class="grid grid-cols-2 gap-2 sm:gap-3 text-xs">
        <div>
          <label class="block font-medium text-zinc-700 dark:text-zinc-300 text-[11px] sm:text-xs mb-1">Jilid / Kelas *</label>
          <select
            v-model="selectedKelasId"
            @change="onKelasChange"
            class="w-full h-8 sm:h-9 px-2 sm:px-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none transition truncate"
          >
            <option value="">-- Pilih Jilid --</option>
            <option v-for="k in kelasList" :key="k.id" :value="k.id">
              {{ k.nama_kelas }} (Rp {{ formatRupiah(k.nominal_spp) }})
            </option>
          </select>
        </div>

        <div>
          <label class="block font-medium text-zinc-700 dark:text-zinc-300 text-[11px] sm:text-xs mb-1">Bulan Tagihan *</label>
          <select
            v-model="selectedBulan"
            @change="fetchSantriByKelas"
            class="w-full h-8 sm:h-9 px-2 sm:px-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none transition"
          >
            <option v-for="(nama, num) in bulanList" :key="num" :value="num">
              {{ nama }}
            </option>
          </select>
        </div>
      </div>

      <!-- Baris 2: Tahun & Status Pembayaran Sejajar 2 Kolom Kanan-Kiri di Mobile & Desktop -->
      <div class="grid grid-cols-2 gap-2 sm:gap-3 text-xs">
        <div>
          <label class="block font-medium text-zinc-700 dark:text-zinc-300 text-[11px] sm:text-xs mb-1">Tahun *</label>
          <select
            v-model="selectedTahun"
            @change="fetchSantriByKelas"
            class="w-full h-8 sm:h-9 px-2 sm:px-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none transition"
          >
            <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <div>
          <label class="block font-medium text-zinc-700 dark:text-zinc-300 text-[11px] sm:text-xs mb-1">Status Pembayaran</label>
          <select
            v-model="statusFilter"
            class="w-full h-8 sm:h-9 px-2 sm:px-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none transition truncate"
          >
            <option value="">Semua Status</option>
            <option value="belum_lunas">Belum Lunas</option>
            <option value="lunas">Sudah Lunas</option>
          </select>
        </div>
      </div>

      <!-- Baris 3: Kolom Pencarian Nama Santri (Full Width) -->
      <div class="text-xs">
        <label class="block font-medium text-zinc-700 dark:text-zinc-300 text-[11px] sm:text-xs mb-1">Pencarian Nama Santri</label>
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-zinc-400">
            <BaseIcon name="search" class="w-3.5 h-3.5" />
          </div>
          <input
            v-model="searchKeyword"
            type="text"
            placeholder="Ketik nama santri atau NISN..."
            class="w-full h-8 sm:h-9 pl-8 pr-7 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none transition"
          />
          <button
            v-if="searchKeyword"
            type="button"
            @click="searchKeyword = ''"
            class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-zinc-400 hover:text-zinc-600 text-sm"
            title="Hapus Pencarian"
          >
            &times;
          </button>
        </div>
      </div>
    </div>

    <!-- Batch Table -->
    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-sm overflow-hidden print:hidden">
      <!-- Toolbar (Tombol 'Pilih Semua Yang Belum Lunas' & 'Kosongkan Pilihan' Telah Dihapus Sesuai Permintaan) -->
      <div class="p-3.5 sm:p-4 border-b border-zinc-100 dark:border-zinc-800 flex flex-wrap items-center justify-between gap-3 bg-zinc-50/50 dark:bg-zinc-800/20">
        <div class="flex items-center gap-2">
          <span class="text-xs font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
            Daftar Santri ({{ filteredSantriItems.length }} Orang)
          </span>
          <span v-if="searchKeyword" class="text-[11px] text-zinc-400 italic">
            (difilter dari {{ santriItems.length }} santri)
          </span>
        </div>

        <div v-if="selectedCount > 0" class="flex items-center gap-4 text-xs font-bold">
          <span class="text-zinc-600 dark:text-zinc-300">
            Terpilih: <span class="text-emerald-600">{{ selectedCount }}</span> santri
          </span>
          <span class="text-zinc-600 dark:text-zinc-300">
            Total Bayar: <span class="text-emerald-600">Rp {{ formatRupiah(totalNominalBayar) }}</span>
          </span>
        </div>
      </div>

      <!-- Banner Notifikasi Auto-Select dari Halaman Tunggakan -->
      <div
        v-if="targetSiswaId && santriItems.some(i => i.id_siswa === targetSiswaId)"
        class="mx-4 mt-4 p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/80 text-xs text-emerald-900 dark:text-emerald-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 shadow-xs"
      >
        <div class="flex items-center gap-2.5">
          <div class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
            <BaseIcon name="check-circle" class="w-4 h-4" />
          </div>
          <div>
            <span class="font-bold">Otomatis Terpilih:</span>
            <span class="ml-1 text-emerald-800 dark:text-emerald-300">
              Santri yang Anda pilih dari data tunggakan telah dicentang. Silakan periksa nominal dan klik <b>Proses Pembayaran</b>.
            </span>
          </div>
        </div>
      </div>

      <!-- State: Not Selected -->
      <div v-if="!selectedKelasId" class="p-12 text-center text-zinc-400">
        <BaseIcon name="riwayat" class="w-10 h-10 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
        <p class="text-sm font-semibold">Silakan pilih Jilid / Kelas terlebih dahulu untuk menampilkan daftar santri.</p>
      </div>

      <!-- State: Loading -->
      <div v-else-if="isLoading" class="p-12 text-center text-zinc-400">
        <BaseIcon name="refresh" class="w-8 h-8 mx-auto mb-2 animate-spin text-zinc-400" />
        <p class="text-sm font-medium">Memuat data santri dan tagihan...</p>
      </div>

      <!-- State: Empty -->
      <div v-else-if="santriItems.length === 0" class="p-12 text-center text-zinc-400">
        <BaseIcon name="santri" class="w-10 h-10 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
        <p class="text-sm font-semibold">Tidak ada santri aktif di jilid / kelas ini.</p>
      </div>

      <!-- State: Search Not Found -->
      <div v-else-if="filteredSantriItems.length === 0 && searchKeyword" class="p-10 text-center text-zinc-400">
        <BaseIcon name="search" class="w-8 h-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
        <p class="text-xs font-semibold">Tidak ada santri yang cocok dengan pencarian "{{ searchKeyword }}".</p>
      </div>

      <!-- Table of items: Kolom [Checkbox] [Nama] [Status] [Nominal] [Metode] Sesuai Permintaan -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 uppercase tracking-wider font-semibold border-b border-zinc-200/80 dark:border-zinc-800">
            <tr>
              <th class="p-3 w-10 text-center">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  @change="toggleSelectAll"
                  title="Pilih / Batalkan Semua yang Belum Lunas"
                  class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer"
                />
              </th>
              <th class="p-3 font-bold text-zinc-700 dark:text-zinc-300">Nama</th>
              <th class="p-3 font-bold text-zinc-700 dark:text-zinc-300">Status</th>
              <th class="p-3 font-bold text-zinc-700 dark:text-zinc-300 w-44">Nominal</th>
              <th class="p-3 font-bold text-zinc-700 dark:text-zinc-300 w-36">Metode</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
            <tr
              v-for="item in filteredSantriItems"
              :key="item.id_siswa"
              :id="'santri-row-' + item.id_siswa"
              :class="[
                item.id_siswa === targetSiswaId && item.selected
                  ? 'bg-emerald-100/70 dark:bg-emerald-950/50 ring-2 ring-emerald-500/60 dark:ring-emerald-400/50'
                  : item.selected
                    ? 'bg-emerald-50/60 dark:bg-emerald-950/20'
                    : 'hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40',
                item.status === 'lunas' ? 'opacity-70' : ''
              ]"
              class="transition"
            >
              <td class="p-3 text-center">
                <input
                  type="checkbox"
                  v-model="item.selected"
                  :disabled="item.status === 'lunas'"
                  class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer disabled:opacity-30"
                />
              </td>
              <td class="p-3 min-w-[140px] sm:min-w-[180px] max-w-[220px]">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <p class="font-bold text-zinc-900 dark:text-white whitespace-normal break-words leading-tight">
                    {{ item.nama_lengkap }}
                  </p>
                  <span
                    v-if="item.id_siswa === targetSiswaId"
                    class="px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-emerald-600 text-white shadow-xs shrink-0"
                  >
                    Target
                  </span>
                </div>
                <p class="text-[11px] font-mono text-zinc-400 truncate">NISN: {{ item.nisn }} &bull; {{ item.nama_kelas }}</p>
              </td>
              <td class="p-3">
                <span
                  v-if="item.status === 'lunas'"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300"
                >
                  &check; Lunas
                </span>
                <span
                  v-else-if="item.status === 'kurang_bayar'"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300"
                >
                  Kurang: Rp {{ formatRupiah(item.sisa_kurang) }}
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300"
                >
                  Belum Bayar
                </span>
              </td>
              <td class="p-3">
                <input
                  type="number"
                  v-model.number="item.nominal"
                  :disabled="!item.selected || item.status === 'lunas'"
                  class="w-full px-2.5 py-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-bold focus:ring-2 focus:ring-emerald-500 text-xs disabled:opacity-40"
                />
                <p
                  v-if="getAlokasiMultiBulanLabel(item)"
                  class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold mt-1 flex items-center gap-1"
                >
                  {{ getAlokasiMultiBulanLabel(item) }}
                </p>
              </td>
              <td class="p-3">
                <select
                  v-model="item.metode"
                  :disabled="!item.selected || item.status === 'lunas'"
                  class="w-full px-2 py-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-xs disabled:opacity-40"
                >
                  <option value="Tunai">Tunai</option>
                  <option value="Transfer">Transfer</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Action Footer -->
      <div v-if="santriItems.length > 0" class="p-4 bg-zinc-50 dark:bg-zinc-800/40 border-t border-zinc-200/80 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="text-xs text-zinc-500">
          * Santri dengan status <b>Lunas</b> tidak dapat dipilih untuk input pembayaran periode ini.
        </div>

        <button
          @click="submitBatchPayment"
          :disabled="selectedCount === 0 || isSubmitting"
          class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/30 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
        >
          <BaseIcon v-if="isSubmitting" name="refresh" class="w-4 h-4 animate-spin text-white" />
          <BaseIcon v-else name="wallet" class="w-4 h-4 text-white" />
          <span>Proses Pembayaran ({{ selectedCount }} Santri &bull; Rp {{ formatRupiah(totalNominalBayar) }})</span>
        </button>
      </div>
    </div>

    <!-- Modal Pop-up Kuitansi Resmi Pembayaran SPP (Responsive Mobile & Sticky Action Buttons) -->
    <div
      v-if="showResultModal && resultKuitansiList.length > 0"
      class="fixed inset-0 z-50 flex items-center justify-center p-2.5 sm:p-4 bg-black/70 backdrop-blur-sm animate-fadeIn print:static print:p-0 print:bg-white print:backdrop-blur-none"
    >
      <div class="w-full max-w-lg max-h-[92vh] flex flex-col rounded-2xl sm:rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl p-3.5 sm:p-5 text-xs animate-slideUp print:border-none print:shadow-none print:p-0 print:max-w-none print:max-h-none">
        
        <!-- Header Info & Multi-Kuitansi Selector (Fixed Top, Hidden saat cetak) -->
        <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-zinc-100 dark:border-zinc-800 shrink-0 print:hidden">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
              <BaseIcon name="check-circle" class="w-4 h-4 text-emerald-600" />
            </div>
            <div>
              <h3 class="text-xs sm:text-sm font-bold text-zinc-900 dark:text-white leading-tight">Bukti Pembayaran SPP</h3>
              <p class="text-[10px] text-zinc-400">{{ resultMessage }}</p>
            </div>
          </div>

          <div class="flex items-center gap-1.5">
            <!-- Selector jika ada lebih dari 1 santri yang dibayar sekaligus -->
            <div v-if="resultKuitansiList.length > 1" class="flex items-center gap-1 mr-1">
              <button
                v-for="(kw, idx) in resultKuitansiList"
                :key="idx"
                @click="activeKuitansiIndex = idx"
                :class="[
                  activeKuitansiIndex === idx
                    ? 'bg-emerald-600 text-white font-bold shadow-xs'
                    : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700'
                ]"
                class="w-6 h-6 rounded-md text-[11px] font-mono transition cursor-pointer flex items-center justify-center"
                :title="kw.nama_santri"
              >
                {{ idx + 1 }}
              </button>
            </div>

            <!-- Tombol Close (X) di Pojok Kanan Atas Modal -->
            <button
              type="button"
              @click="closeResultModal"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition cursor-pointer"
              title="Tutup Modal"
            >
              <BaseIcon name="x-circle" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Kuitansi Printable Area (Scrollable di layar kecil agar tidak terpotong) -->
        <div class="flex-1 overflow-y-auto my-2 pr-0.5 print:overflow-visible print:my-0 print:pr-0">
          <div
            v-if="activeKuitansi"
            id="inputCepatKuitansiArea"
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
                <span class="font-mono font-bold">{{ activeKuitansi.nomor_kuitansi }}</span>
              </div>
              <div class="text-right">
                <span class="text-zinc-400 dark:text-zinc-500 print:text-zinc-600 block text-[9.5px]">Tanggal Bayar</span>
                <span class="font-bold">{{ activeKuitansi.tanggal_bayar }}</span>
              </div>
            </div>

            <!-- Rincian Kuitansi -->
            <div class="p-2.5 sm:p-3 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 space-y-1.5 print:bg-white print:border-zinc-300">
              <div class="flex justify-between">
                <span class="text-zinc-500 print:text-zinc-600">Nama Santri:</span>
                <span class="font-bold text-right">{{ activeKuitansi.nama_santri }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-zinc-500 print:text-zinc-600">NISN / Jilid:</span>
                <span class="font-mono">{{ activeKuitansi.nisn }} &bull; {{ activeKuitansi.kelas }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-zinc-500 print:text-zinc-600">Pembayaran Periode:</span>
                <span class="font-semibold text-right">{{ activeKuitansi.pembayaran_untuk }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-zinc-500 print:text-zinc-600">Metode Bayar:</span>
                <span class="font-semibold">{{ activeKuitansi.metode }}</span>
              </div>
              <div v-if="activeKuitansi.catatan" class="flex justify-between">
                <span class="text-zinc-500 print:text-zinc-600">Catatan:</span>
                <span class="italic text-zinc-600 dark:text-zinc-300 print:text-zinc-700">{{ activeKuitansi.catatan }}</span>
              </div>
              <div class="flex justify-between pt-1.5 border-t border-zinc-200 dark:border-zinc-800 print:border-zinc-300 text-xs sm:text-sm font-bold text-emerald-600 print:text-black">
                <span>Total Dibayar:</span>
                <span>Rp {{ formatRupiah(activeKuitansi.jumlah_bayar) }}</span>
              </div>
              <div class="flex justify-between py-0.5">
                <span class="text-zinc-500 print:text-zinc-600">Status Transaksi:</span>
                <span class="font-extrabold uppercase text-[11px] text-emerald-600">LUNAS</span>
              </div>
            </div>

            <!-- Blok Pengesahan Tanda Tangan Resmi Kuitansi -->
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
                  Paiton, {{ formatTanggalRingkas(activeKuitansi.tanggal_bayar) }}
                </p>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300 print:text-black text-[9px] sm:text-[10px]">
                  Bendahara TPQ,
                </p>
                <div class="h-8 sm:h-10"></div>
                <p class="font-bold underline tracking-wide text-zinc-900 dark:text-white print:text-black text-[9px] sm:text-[10px]">
                  ( {{ activeKuitansi.nama_bendahara || namaBendahara }} )
                </p>
                <p class="text-[8px] text-zinc-500 dark:text-zinc-400 print:text-zinc-600 mt-0.5" v-if="activeKuitansi.petugas && activeKuitansi.petugas !== (activeKuitansi.nama_bendahara || namaBendahara)">
                  Petugas: {{ activeKuitansi.petugas }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons (Fixed Bottom, Always Visible in Mobile, Hidden saat cetak) -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 pt-2.5 border-t border-zinc-100 dark:border-zinc-800 shrink-0 print:hidden">
          <button
            type="button"
            @click="sendKuitansiViaWa"
            class="h-8 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm transition flex items-center justify-center gap-1.5 cursor-pointer order-1 sm:order-none"
            title="Kirim Kuitansi ke WhatsApp Wali Santri"
          >
            <BaseIcon name="whatsapp" class="w-3.5 h-3.5" />
            <span>Kirim WA ke Wali Santri</span>
          </button>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="closeResultModal"
              class="flex-1 sm:flex-none h-8 px-3.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 text-xs font-semibold hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition cursor-pointer"
            >
              Tutup
            </button>
            <button
              type="button"
              @click="printKuitansi"
              class="flex-1 sm:flex-none h-8 px-3.5 rounded-lg bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 text-xs font-bold shadow-sm hover:opacity-90 transition flex items-center justify-center gap-1.5 cursor-pointer"
            >
              <BaseIcon name="printer" class="w-3.5 h-3.5" />
              <span>Cetak</span>
            </button>
          </div>
        </div>
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
import KirimWaKuitansiModal from '../../components/KirimWaKuitansiModal.vue';

const route = useRoute();
const toast = useToast();

const bulanList = {
  1: 'Januari',
  2: 'Februari',
  3: 'Maret',
  4: 'April',
  5: 'Mei',
  6: 'Juni',
  7: 'Juli',
  8: 'Agustus',
  9: 'September',
  10: 'Oktober',
  11: 'November',
  12: 'Desember',
};

const currentDate = new Date();
const selectedBulan = ref(currentDate.getMonth() + 1);
const selectedTahun = ref(currentDate.getFullYear());
const selectedKelasId = ref('');
const defaultMetode = ref('Tunai');
const statusFilter = ref('');
const targetSiswaId = ref(null);
const searchKeyword = ref('');

const kelasList = ref([]);
const santriItems = ref([]);
const isLoading = ref(false);
const isSubmitting = ref(false);

const namaBendahara = ref('Ust. Bendahara');
const appLogo = ref('/LOGOTPQ.png');

// Modal Result / Kuitansi
const showResultModal = ref(false);
const showKirimWaModal = ref(false);
const resultMessage = ref('');
const resultKuitansiList = ref([]);
const activeKuitansiIndex = ref(0);

const activeKuitansi = computed(() => {
  return resultKuitansiList.value[activeKuitansiIndex.value] || null;
});

const kuitansiDataForWa = computed(() => {
  if (!activeKuitansi.value) return null;
  const kw = activeKuitansi.value;
  const namaBln = NAMA_BULAN[selectedBulan.value] || `Bulan ${selectedBulan.value}`;
  const periodeStr = kw.daftar_bulan_str || kw.periode || `${namaBln} ${selectedTahun.value}`;

  // Ambil fallback data santri dari tabel jika di kw belum terisi lengkap
  const matchingSantri = santriItems.value.find(s => s.id_siswa === kw.id_siswa || s.nisn === kw.nisn);

  return {
    ...kw,
    nama_santri: kw.nama_santri || matchingSantri?.nama_lengkap || '-',
    nisn: kw.nisn || matchingSantri?.nisn || '-',
    kelas: kw.kelas || matchingSantri?.nama_kelas || '-',
    no_hp_ortu: kw.no_hp_ortu || matchingSantri?.no_hp_ortu || '',
    bulan: kw.bulan || selectedBulan.value,
    tahun: kw.tahun || selectedTahun.value,
    nama_bulan: kw.nama_bulan || namaBln,
    periode: periodeStr,
    daftar_bulan_str: kw.daftar_bulan_str || periodeStr,
    bulan_tagihan: kw.bulan_tagihan || periodeStr,
    pembayaran_untuk: kw.pembayaran_untuk || `SPP ${periodeStr}`,
    appLogo: appLogo.value,
    nama_bendahara: kw.nama_bendahara || namaBendahara.value,
  };
});

function getAlokasiMultiBulanLabel(item) {
  if (!item.nominal || item.status === 'lunas') return null;
  const kData = kelasList.value.find(k => k.id === Number(selectedKelasId.value));
  const tarif = kData ? Number(kData.nominal_spp) : 15000;
  if (item.nominal <= tarif) return null;

  const jmlBulan = Math.floor(item.nominal / tarif);
  const sisa = item.nominal % tarif;

  const startBln = Number(selectedBulan.value);
  const startThn = Number(selectedTahun.value);

  const blnNames = [];
  for (let i = 0; i < jmlBulan; i++) {
    const m = ((startBln - 1 + i) % 12) + 1;
    blnNames.push(NAMA_BULAN[m]);
  }

  if (sisa > 0) {
    const mNext = ((startBln - 1 + jmlBulan) % 12) + 1;
    return `✨ Otomatis ${jmlBulan} Bulan (${blnNames.join(', ')}) + Cicil Rp ${formatRupiah(sisa)} ${NAMA_BULAN[mNext]}`;
  }
  return `✨ Otomatis ${jmlBulan} Bulan (${blnNames.join(', ')})`;
}

function formatRupiah(val) {
  if (!val) return '0';
  return Number(val).toLocaleString('id-ID');
}

function formatTanggalRingkas(str) {
  if (!str) {
    const today = new Date();
    return today.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
  }
  const cleanStr = String(str).replace(' ', 'T').replace('Z', '');
  const d = new Date(cleanStr);
  if (isNaN(d.getTime())) return str;
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

async function loadKelas() {
  try {
    const res = await api.get('/kelas');
    kelasList.value = res.data.data || [];
  } catch (err) {
    toast.error('Gagal memuat data kelas.');
  }
}

async function fetchSantriByKelas() {
  if (!selectedKelasId.value) {
    santriItems.value = [];
    return;
  }

  isLoading.value = true;
  try {
    // 1. Ambil santri di kelas tersebut
    const resSiswa = await api.get('/siswa', {
      params: { id_kelas: selectedKelasId.value, status: 'aktif' }
    });
    const siswaList = resSiswa.data.data?.data || resSiswa.data.data || [];

    // 2. Ambil data tagihan kelas tersebut di bulan/tahun yang dipilih
    const resTagihan = await api.get('/spp/tagihan', {
      params: {
        id_kelas: selectedKelasId.value,
        bulan: selectedBulan.value,
        tahun: selectedTahun.value,
        per_page: 200,
      }
    });
    const tagihanList = resTagihan.data.data?.data || [];

    const tagihanMap = {};
    for (const tg of tagihanList) {
      tagihanMap[tg.id_siswa] = tg;
    }

    // Dapatkan nominal standar kelas
    const kData = kelasList.value.find(k => k.id === Number(selectedKelasId.value));
    const nominalKelas = kData ? Number(kData.nominal_spp) : 50000;

    santriItems.value = siswaList.map(s => {
      const tg = tagihanMap[s.id];
      let status = 'belum_lunas';
      let nominal = nominalKelas;
      let sisaKurang = 0;

      if (tg) {
        status = tg.status;
        if (tg.status === 'lunas') {
          nominal = 0;
        } else if (tg.status === 'kurang_bayar') {
          sisaKurang = Number(tg.nominal_tagihan) - Number(tg.terbayar || 0);
          nominal = sisaKurang > 0 ? sisaKurang : nominalKelas;
        } else {
          nominal = Number(tg.nominal_tagihan);
        }
      }

      return {
        id_siswa: s.id,
        nisn: s.nisn,
        nama_lengkap: s.nama_lengkap,
        nama_kelas: s.kelas?.nama_kelas || kData?.nama_kelas || '',
        no_hp_ortu: s.no_hp_ortu || '',
        status,
        sisa_kurang: sisaKurang,
        nominal,
        metode: defaultMetode.value,
        selected: status !== 'lunas',
      };
    });
  } catch (err) {
    toast.error('Gagal mengambil data tagihan santri.');
  } finally {
    isLoading.value = false;
  }
}

function applyDefaultMetode() {
  for (const item of santriItems.value) {
    item.metode = defaultMetode.value;
  }
}

// Filtered Santri berdasarkan Status Pembayaran & Pencarian Nama Santri
const filteredSantriItems = computed(() => {
  let list = santriItems.value;
  if (statusFilter.value === 'belum_lunas') {
    list = list.filter(s => s.status !== 'lunas');
  } else if (statusFilter.value === 'lunas') {
    list = list.filter(s => s.status === 'lunas');
  }
  if (!searchKeyword.value.trim()) {
    return list;
  }
  const q = searchKeyword.value.toLowerCase().trim();
  return list.filter(s =>
    (s.nama_lengkap && s.nama_lengkap.toLowerCase().includes(q)) ||
    (s.nisn && s.nisn.toLowerCase().includes(q))
  );
});

const selectedCount = computed(() => {
  return filteredSantriItems.value.filter(i => i.selected && i.status !== 'lunas').length;
});

const totalNominalBayar = computed(() => {
  return filteredSantriItems.value
    .filter(i => i.selected && i.status !== 'lunas')
    .reduce((acc, curr) => acc + (Number(curr.nominal) || 0), 0);
});

const isAllSelected = computed(() => {
  const eligible = filteredSantriItems.value.filter(i => i.status !== 'lunas');
  return eligible.length > 0 && eligible.every(i => i.selected);
});

function toggleSelectAll(e) {
  const checked = e.target.checked;
  for (const item of filteredSantriItems.value) {
    if (item.status !== 'lunas') {
      item.selected = checked;
    }
  }
}

async function submitBatchPayment() {
  const selectedItems = filteredSantriItems.value.filter(i => i.selected && i.status !== 'lunas');
  if (selectedItems.length === 0) {
    toast.warning('Pilih minimal satu santri yang belum lunas.');
    return;
  }

  isSubmitting.value = true;
  try {
    const payload = {
      bulan: Number(selectedBulan.value),
      tahun: Number(selectedTahun.value),
      id_kelas: selectedKelasId.value ? Number(selectedKelasId.value) : null,
      items: selectedItems.map(item => ({
        id_siswa: item.id_siswa,
        nominal: Number(item.nominal),
        metode: item.metode,
      })),
    };

    const res = await api.post('/spp/input-cepat', payload);

    resultMessage.value = res.data.message || 'Pembayaran berhasil diproses.';
    resultKuitansiList.value = res.data.kuitansi_list || [];
    activeKuitansiIndex.value = 0;
    showResultModal.value = true;
    toast.success('Pembayaran SPP santri berhasil disimpan.');

    // Reload state santri
    await fetchSantriByKelas();
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal memproses pembayaran.';
    toast.error(msg);
  } finally {
    isSubmitting.value = false;
  }
}

function onKelasChange() {
  targetSiswaId.value = null;
  searchKeyword.value = '';
  fetchSantriByKelas();
}

function closeResultModal() {
  showResultModal.value = false;
  resultKuitansiList.value = [];
  activeKuitansiIndex.value = 0;
}

function printKuitansi() {
  window.print();
}

function sendKuitansiViaWa() {
  if (!activeKuitansi.value) return;
  showKirimWaModal.value = true;
}

onMounted(async () => {
  await loadKelas();

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

  const qKelasId = route.query.id_kelas ? Number(route.query.id_kelas) : null;
  const qSiswaId = route.query.id_siswa ? Number(route.query.id_siswa) : null;

  if (route.query.bulan) {
    selectedBulan.value = Number(route.query.bulan);
  }
  if (route.query.tahun) {
    selectedTahun.value = Number(route.query.tahun);
  }

  if (qKelasId) {
    selectedKelasId.value = qKelasId;
    await fetchSantriByKelas();

    if (qSiswaId) {
      targetSiswaId.value = qSiswaId;
      for (const item of santriItems.value) {
        item.selected = (item.id_siswa === qSiswaId && item.status !== 'lunas');
      }

      setTimeout(() => {
        const el = document.getElementById(`santri-row-${qSiswaId}`);
        if (el) {
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }, 300);
    }
  }
});
</script>

<style scoped>
@media print {
  @page {
    size: A5 landscape;
    margin: 8mm;
  }
}
</style>
