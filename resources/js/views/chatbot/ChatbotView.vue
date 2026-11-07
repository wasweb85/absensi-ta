<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between pb-2 border-b">
      <div class="flex items-center gap-2">
        <h2 class="text-lg font-semibold tracking-tight text-foreground">
          Chatbot
        </h2>
        <Badge variant="outline" class="font-normal text-xs text-muted-foreground gap-1">
          <Bot class="size-3 text-emerald-500" />
          <span>AI Auto-Reply</span>
        </Badge>
      </div>

      <!-- Tab Switcher (Shadcn Tabs) -->
      <Tabs v-model="activeTab" class="w-auto">
        <TabsList class="grid grid-cols-3 h-8">
          <TabsTrigger value="logs" class="text-xs px-3 gap-1.5">
            <MessageSquare class="size-3.5" />
            <span>Log Percakapan</span>
          </TabsTrigger>
          <TabsTrigger value="knowledge" class="text-xs px-3 gap-1.5" @click="loadKnowledgeBase">
            <BookOpen class="size-3.5" />
            <span>Knowledge Base</span>
          </TabsTrigger>
          <TabsTrigger value="config" class="text-xs px-3 gap-1.5">
            <Settings class="size-3.5" />
            <span>Webhook Fonnte</span>
          </TabsTrigger>
        </TabsList>
      </Tabs>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 1: LOG PERCAKAPAN REALTIME -->
    <!-- ================================================================= -->
    <div v-if="activeTab === 'logs'" class="space-y-6">
      <!-- KPI Stats Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <Card class="p-3.5">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Chat Hari Ini</span>
            <MessageSquare class="size-4 text-emerald-600 dark:text-emerald-400" />
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-foreground">{{ stats.total_today }}</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Bulan ini: {{ stats.total_month }} pesan</p>
          </div>
        </Card>

        <Card class="p-3.5">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Cek Presensi</span>
            <span class="size-2 rounded-full bg-teal-500"></span>
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-foreground">{{ stats.intent_distribution?.absensi || 0 }}</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Pertanyaan kehadiran santri</p>
          </div>
        </Card>

        <Card class="p-3.5">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Cek Hari Libur</span>
            <span class="size-2 rounded-full bg-blue-500"></span>
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-foreground">{{ stats.intent_distribution?.agenda || 0 }}</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Pertanyaan informasi hari libur</p>
          </div>
        </Card>

        <Card class="p-3.5">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-[11px] font-semibold uppercase tracking-wider">Tingkat Keberhasilan</span>
            <Check class="size-4 text-emerald-600" />
          </div>
          <div class="mt-2">
            <div class="text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ stats.success_rate || 100 }}%</div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Respon berhasil diproses</p>
          </div>
        </Card>
      </div>

      <!-- Filters Card -->
      <Card>
        <CardContent class="p-3.5">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <div class="space-y-1">
              <label class="text-[11px] font-medium text-muted-foreground">Filter Intent / Topik</label>
              <select
                v-model="filters.intent"
                class="w-full h-9 px-3 rounded-md border border-input bg-background text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring cursor-pointer"
                @change="loadLogs(1)"
              >
                <option value="">Semua Topik</option>
                <option value="absensi">Presensi Santri</option>
                <option value="spp_notice">Info Administrasi / SPP</option>
                <option value="agenda">Agenda & Libur</option>
                <option value="info_tpq">Informasi Umum TPQ</option>
                <option value="izin_sakit">Konfirmasi Izin / Sakit</option>
                <option value="apresiasi">Apresiasi / Terima Kasih</option>
                <option value="bantuan">Menu Bantuan</option>
                <option value="multi_santri">Multi Santri</option>
                <option value="unregistered">Nomor Belum Terdaftar</option>
                <option value="unknown">Tidak Dikenali</option>
              </select>
            </div>

            <div class="space-y-1">
              <label class="text-[11px] font-medium text-muted-foreground">Status Respon</label>
              <select
                v-model="filters.status"
                class="w-full h-9 px-3 rounded-md border border-input bg-background text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring cursor-pointer"
                @change="loadLogs(1)"
              >
                <option value="">Semua Status</option>
                <option value="sukses">Sukses</option>
                <option value="gagal">Gagal</option>
              </select>
            </div>

            <div class="space-y-1">
              <label class="text-[11px] font-medium text-muted-foreground">Tanggal</label>
              <Input
                v-model="filters.tanggal"
                type="date"
                class="h-9 text-xs"
                @change="loadLogs(1)"
              />
            </div>

            <div class="space-y-1">
              <label class="text-[11px] font-medium text-muted-foreground">Cari Pesan / Nomor</label>
              <div class="relative">
                <Search class="size-3.5 absolute left-2.5 top-2.5 text-muted-foreground" />
                <Input
                  v-model="filters.search"
                  type="text"
                  placeholder="Ketik kata kunci..."
                  class="h-9 pl-8 text-xs"
                  @input="debounceSearch"
                />
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Logs Table Card -->
      <Card class="overflow-hidden">
        <div class="overflow-x-auto">
          <Table class="w-full">
            <TableHeader>
              <TableRow class="bg-muted/30">
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Waktu</TableHead>
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Pengirim / Santri</TableHead>
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 max-w-xs">Pesan Masuk</TableHead>
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4">Intent</TableHead>
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 max-w-xs">Balasan Bot</TableHead>
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 text-center">Status</TableHead>
                <TableHead class="text-[10px] uppercase whitespace-nowrap px-3 sm:px-4 text-right">Aksi</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-if="logs.length === 0">
                <TableCell colspan="7" class="h-32 text-center text-muted-foreground text-xs">
                  Belum ada log percakapan yang sesuai dengan filter.
                </TableCell>
              </TableRow>

              <TableRow
                v-for="item in logs"
                :key="item.id"
                class="transition-colors hover:bg-muted/40"
              >
                <TableCell class="py-2.5 px-3 sm:px-4 font-mono text-xs whitespace-nowrap text-muted-foreground">
                  {{ formatDateTime(item.created_at) }}
                </TableCell>

                <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap">
                  <p class="font-mono font-medium text-foreground text-xs">{{ item.no_pengirim }}</p>
                  <p v-if="item.siswa" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                    {{ item.siswa.nama_lengkap }} ({{ item.siswa.kelas?.nama_kelas || '-' }})
                  </p>
                  <p v-else class="text-[10px] text-muted-foreground italic">Non-Santri</p>
                </TableCell>

                <TableCell class="py-2.5 px-3 sm:px-4 max-w-xs">
                  <p class="text-xs text-foreground truncate font-medium" :title="item.pesan_masuk">
                    "{{ item.pesan_masuk }}"
                  </p>
                </TableCell>

                <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap">
                  <Badge variant="outline" class="text-[10px] uppercase tracking-wider font-medium">
                    {{ item.intent_terdeteksi }}
                  </Badge>
                </TableCell>

                <TableCell class="py-2.5 px-3 sm:px-4 max-w-xs">
                  <p class="text-xs text-muted-foreground truncate" :title="item.pesan_keluar">
                    {{ item.pesan_keluar }}
                  </p>
                </TableCell>

                <TableCell class="py-2.5 px-3 sm:px-4 text-center whitespace-nowrap">
                  <Badge
                    :variant="item.status === 'sukses' ? 'default' : 'destructive'"
                    class="text-[10px] uppercase"
                  >
                    {{ item.status }}
                  </Badge>
                </TableCell>

                <TableCell class="py-2.5 px-3 sm:px-4 text-right whitespace-nowrap">
                  <Button
                    variant="outline"
                    size="sm"
                    class="h-7 px-2 text-xs"
                    @click="openDetailModal(item)"
                  >
                    Detail
                  </Button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="p-3.5 border-t flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground">
          <span>Halaman {{ currentPage }} dari {{ totalPages }} (Total {{ totalLogs }} log)</span>
          <div class="flex items-center gap-1.5">
            <Button
              variant="outline"
              size="sm"
              class="h-7 text-xs"
              :disabled="currentPage <= 1"
              @click="loadLogs(currentPage - 1)"
            >
              Sebelumnya
            </Button>
            <Button
              variant="outline"
              size="sm"
              class="h-7 text-xs"
              :disabled="currentPage >= totalPages"
              @click="loadLogs(currentPage + 1)"
            >
              Selanjutnya
            </Button>
          </div>
        </div>
      </Card>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 2: KNOWLEDGE BASE TPQ & STATUS HYBRID AI GEMINI -->
    <!-- ================================================================= -->
    <div v-if="activeTab === 'knowledge'" class="space-y-6">
      <Card class="p-4 sm:p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h3 class="text-sm font-semibold text-foreground flex items-center gap-2">
              <Bot class="size-4 text-emerald-600" />
              <span>Knowledge Base TPQ Nurul Mun'im &amp; Hybrid AI Gemini</span>
            </h3>
            <p class="text-xs text-muted-foreground mt-0.5">
              Kelola informasi umum TPQ (jadwal, jam masuk/pulang, peraturan, pembayaran, dan kegiatan) tanpa perlu mengubah kode program.
            </p>
          </div>
          <div class="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              class="text-xs gap-1.5"
              :disabled="testingGemini"
              @click="testGemini"
            >
              <Bot class="size-3.5 text-emerald-600" />
              <span>{{ testingGemini ? 'Menguji Gemini...' : 'Uji Koneksi Gemini AI' }}</span>
            </Button>
            <Button
              size="sm"
              class="text-xs gap-1.5"
              @click="openKbModal()"
            >
              <span>+ Tambah Topik</span>
            </Button>
          </div>
        </div>

        <div v-if="geminiStatusMsg" class="mt-3 p-2.5 rounded-lg border text-xs bg-muted/40 text-foreground">
          {{ geminiStatusMsg }}
        </div>
      </Card>

      <Card class="overflow-hidden">
        <div class="overflow-x-auto">
          <Table class="w-full">
            <TableHeader>
              <TableRow class="bg-muted/30">
                <TableHead class="text-[10px] uppercase px-3 sm:px-4">Kategori</TableHead>
                <TableHead class="text-[10px] uppercase px-3 sm:px-4">Judul Topik</TableHead>
                <TableHead class="text-[10px] uppercase px-3 sm:px-4">Kata Kunci</TableHead>
                <TableHead class="text-[10px] uppercase px-3 sm:px-4 max-w-md">Isi Informasi</TableHead>
                <TableHead class="text-[10px] uppercase px-3 sm:px-4 text-right">Aksi</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-if="kbEntries.length === 0">
                <TableCell colspan="5" class="h-24 text-center text-muted-foreground text-xs">
                  Memuat daftar Knowledge Base TPQ...
                </TableCell>
              </TableRow>
              <TableRow v-for="kb in kbEntries" :key="kb.id" class="hover:bg-muted/40">
                <TableCell class="py-2.5 px-3 sm:px-4 whitespace-nowrap">
                  <Badge variant="outline" class="text-[10px] uppercase">{{ kb.kategori }}</Badge>
                </TableCell>
                <TableCell class="py-2.5 px-3 sm:px-4 font-medium text-xs text-foreground">
                  {{ kb.judul }}
                </TableCell>
                <TableCell class="py-2.5 px-3 sm:px-4 text-xs text-muted-foreground">
                  {{ kb.kata_kunci || '-' }}
                </TableCell>
                <TableCell class="py-2.5 px-3 sm:px-4 max-w-md">
                  <p class="text-xs text-muted-foreground line-clamp-2 whitespace-pre-line">{{ kb.konten }}</p>
                </TableCell>
                <TableCell class="py-2.5 px-3 sm:px-4 text-right whitespace-nowrap space-x-1.5">
                  <Button variant="outline" size="sm" class="h-7 px-2 text-xs" @click="openKbModal(kb)">
                    Edit
                  </Button>
                  <Button variant="destructive" size="sm" class="h-7 px-2 text-xs" @click="deleteKb(kb)">
                    Hapus
                  </Button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </Card>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 3: WEBHOOK FONNTE & KONFIGURASI -->
    <!-- ================================================================= -->
    <div v-if="activeTab === 'config'" class="space-y-6">
      <Card class="p-6 space-y-5">
        <div>
          <h3 class="text-base font-semibold tracking-tight text-foreground flex items-center gap-2">
            <Settings class="size-4 text-primary" />
            <span>Konfigurasi Webhook Fonnte (3 Kolom Webhook)</span>
          </h3>
          <p class="text-xs text-muted-foreground mt-1 leading-relaxed">
            Di dashboard Fonnte terdapat 3 kolom webhook utama. Masukkan URL endpoint berikut ke masing-masing kolom di Fonnte agar <b>pesan masuk (Chatbot)</b> dan <b>status centang dua (Delivery Log)</b> terdeteksi secara otomatis.
          </p>
        </div>

        <div class="space-y-4">
          <!-- Kolom 1: Webhook (Pesan Masuk / Chatbot) -->
          <div class="p-3.5 rounded-xl border bg-muted/20 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                <span class="size-2 rounded-full bg-emerald-500"></span>
                <span>1. Kolom "Webhook ?" (Pesan Masuk / Chatbot)</span>
              </span>
              <Badge variant="outline" class="text-[10px] text-emerald-600 dark:text-emerald-400">Incoming Messages</Badge>
            </div>
            <p class="text-[11px] text-muted-foreground">
              Menerima pesan yang diketik oleh wali santri ke WhatsApp TPQ untuk dijawab otomatis oleh Chatbot.
            </p>
            <div class="flex items-center gap-2 pt-1">
              <Input
                :value="webhookIncomingUrl"
                readonly
                class="font-mono text-xs select-all bg-background"
              />
              <Button
                size="sm"
                class="text-xs gap-1.5 shrink-0"
                @click="copyUrl(webhookIncomingUrl, 'incoming')"
              >
                <Copy class="size-3.5" />
                <span>{{ copiedKey === 'incoming' ? 'Tersalin!' : 'Salin' }}</span>
              </Button>
            </div>
          </div>

          <!-- Kolom 2: Webhook Connect (Status Perangkat) -->
          <div class="p-3.5 rounded-xl border bg-muted/20 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                <span class="size-2 rounded-full bg-sky-500"></span>
                <span>2. Kolom "Webhook Connect ?" (Status Sambungan WhatsApp)</span>
              </span>
              <Badge variant="outline" class="text-[10px] text-sky-600 dark:text-sky-400">Device Events</Badge>
            </div>
            <p class="text-[11px] text-muted-foreground">
              Memantau status perangkat WhatsApp (apakah sedang terhubung atau terputus/disconnect).
            </p>
            <div class="flex items-center gap-2 pt-1">
              <Input
                :value="webhookConnectUrl"
                readonly
                class="font-mono text-xs select-all bg-background"
              />
              <Button
                size="sm"
                variant="outline"
                class="text-xs gap-1.5 shrink-0"
                @click="copyUrl(webhookConnectUrl, 'connect')"
              >
                <Copy class="size-3.5" />
                <span>{{ copiedKey === 'connect' ? 'Tersalin!' : 'Salin' }}</span>
              </Button>
            </div>
          </div>

          <!-- Kolom 3: Webhook Message Status (KUNCI DELIVERY LOG) -->
          <div class="p-3.5 rounded-xl border-2 border-emerald-500/40 bg-emerald-500/5 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5">
                <span class="size-2 rounded-full bg-emerald-600 animate-pulse"></span>
                <span>3. Kolom "Webhook Message Status ?" (Delivery Log: Centang ✓✓ &amp; Dibaca)</span>
              </span>
              <Badge class="text-[10px] bg-emerald-600 text-white">Wajib untuk Delivery Log</Badge>
            </div>
            <p class="text-[11px] text-zinc-700 dark:text-zinc-300 leading-relaxed">
              <b>PENTING:</b> Masukkan URL ini ke kolom ketiga di Fonnte agar sistem otomatis mencatat kapan pesan <b>Tersampaikan (✓✓ Abu)</b> di HP wali dan kapan <b>Dibaca (✓✓ Biru)</b> oleh wali santri.
            </p>
            <div class="flex items-center gap-2 pt-1">
              <Input
                :value="webhookStatusUrl"
                readonly
                class="font-mono text-xs select-all bg-background font-semibold text-emerald-700 dark:text-emerald-300"
              />
              <Button
                size="sm"
                class="text-xs gap-1.5 shrink-0 bg-emerald-600 hover:bg-emerald-500 text-white"
                @click="copyUrl(webhookStatusUrl, 'status')"
              >
                <Copy class="size-3.5" />
                <span>{{ copiedKey === 'status' ? 'Tersalin!' : 'Salin' }}</span>
              </Button>
            </div>
          </div>
        </div>

        <!-- WARNING AUTO-READ OFF DARI DASHBOARD FONNTE -->
        <div class="p-3.5 rounded-xl border-2 border-amber-400 dark:border-amber-600 bg-amber-50 dark:bg-amber-950/40 space-y-1.5 shadow-sm text-xs">
          <div class="flex items-center gap-2 text-amber-900 dark:text-amber-200 font-bold">
            <span class="size-2 rounded-full bg-amber-600 animate-ping"></span>
            <span>LANGKAH WAJIB: Aktifkan "autoread" di Dashboard Fonnte!</span>
          </div>
          <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-relaxed font-medium">
            Di dashboard Fonnte tepat di bawah form Webhook, pastikan toggle <b>autoread</b> diubah ke posisi <b>ON</b>. Jika bernilai <b>Off</b>, WhatsApp tidak akan meneruskan event status terbaca (Centang 2 Biru) ke Fonnte.
          </p>
        </div>

        <div class="rounded-lg border p-4 bg-muted/20 space-y-2 text-xs">
          <p class="font-semibold text-foreground">Langkah Pemasangan di Dashboard Fonnte:</p>
          <ol class="list-decimal list-inside space-y-1.5 text-muted-foreground">
            <li>Buka dashboard akun Fonnte Anda di <code class="text-foreground">https://fonnte.com</code></li>
            <li>Masuk ke menu <b>Device Setting</b> &gt; tab <b>Webhook</b></li>
            <li>Tempelkan masing-masing URL di atas ke kolom yang sesuai:
              <ul class="list-disc pl-5 mt-1 space-y-0.5 text-[11px]">
                <li>Kolom <b>Webhook ?</b>: Tempelkan URL #1</li>
                <li>Kolom <b>Webhook Connect ?</b>: Tempelkan URL #2</li>
                <li>Kolom <b>Webhook Message Status ?</b>: Tempelkan URL #3 (agar centang dua terdeteksi)</li>
              </ul>
            </li>
            <li>Geser toggle <b>autoread</b> di bagian bawah menjadi <b>ON</b>.</li>
            <li>Klik tombol <b>Save</b> di Fonnte.</li>
          </ol>
        </div>
      </Card>
    </div>

    <!-- DIALOG: DETAIL LOG PERCAKAPAN -->
    <Dialog :open="!!selectedLog" @update:open="(val) => { if (!val) selectedLog = null; }">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold flex items-center gap-2">
            <MessageSquare class="size-4 text-primary" />
            <span>Detail Log Percakapan Bot</span>
          </DialogTitle>
          <DialogDescription class="text-xs">
            Rincian interaksi pesan masuk dan respon yang dikeluarkan engine.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-3.5 text-xs pt-1">
          <div class="flex items-center justify-between p-2.5 rounded-lg bg-muted/50 border">
            <span class="text-muted-foreground">Waktu:</span>
            <span class="font-mono font-medium text-foreground">{{ formatDateTime(selectedLog?.created_at) }}</span>
          </div>

          <div class="space-y-1">
            <label class="font-medium text-muted-foreground">Nomor Pengirim & Santri:</label>
            <p class="font-mono font-semibold text-foreground">{{ selectedLog?.no_pengirim }}</p>
            <p v-if="selectedLog?.siswa" class="text-emerald-600 dark:text-emerald-400 font-medium">
              Santri: {{ selectedLog.siswa.nama_lengkap }} ({{ selectedLog.siswa.kelas?.nama_kelas || '-' }})
            </p>
          </div>

          <div class="space-y-1">
            <label class="font-medium text-muted-foreground">Pesan Masuk (Wali):</label>
            <div class="p-2.5 rounded-lg border bg-muted/30 whitespace-pre-wrap text-foreground">
              {{ selectedLog?.pesan_masuk }}
            </div>
          </div>

          <div class="space-y-1">
            <div class="flex items-center justify-between">
              <label class="font-medium text-muted-foreground">Balasan Bot:</label>
              <Badge variant="outline" class="text-[10px] uppercase">
                {{ selectedLog?.intent_terdeteksi }}
              </Badge>
            </div>
            <div class="p-2.5 rounded-lg border bg-emerald-500/5 border-emerald-500/20 whitespace-pre-wrap text-foreground">
              {{ selectedLog?.pesan_keluar }}
            </div>
          </div>
        </div>

        <DialogFooter class="pt-2">
          <Button
            variant="outline"
            size="sm"
            class="text-xs"
            @click="selectedLog = null"
          >
            Tutup
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- DIALOG: TAMBAH / EDIT KNOWLEDGE BASE TPQ -->
    <Dialog :open="showKbModal" @update:open="(val) => { if (!val) showKbModal = false; }">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold">
            {{ kbForm.id ? 'Edit Topik Knowledge Base' : 'Tambah Topik Knowledge Base' }}
          </DialogTitle>
          <DialogDescription class="text-xs">
            Informasi yang disimpan di sini langsung digunakan oleh AI Chatbot saat menjawab wali santri.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 text-xs pt-1">
          <div class="space-y-1">
            <label class="font-medium text-muted-foreground">Kategori</label>
            <select
              v-model="kbForm.kategori"
              class="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
            >
              <option value="jadwal">Jadwal Kegiatan</option>
              <option value="jam_operasional">Jam Masuk &amp; Pulang</option>
              <option value="peraturan">Peraturan &amp; Tata Tertib</option>
              <option value="pembayaran">Informasi Pembayaran</option>
              <option value="kegiatan">Kegiatan &amp; Agenda</option>
              <option value="administrasi">Administrasi</option>
              <option value="umum">Informasi Umum Lainnya</option>
            </select>
          </div>

          <div class="space-y-1">
            <label class="font-medium text-muted-foreground">Judul Informasi</label>
            <Input v-model="kbForm.judul" placeholder="Contoh: Jadwal Munaqosyah Semester" class="h-9 text-xs" />
          </div>

          <div class="space-y-1">
            <label class="font-medium text-muted-foreground">Kata Kunci Pencarian (pisahkan koma)</label>
            <Input v-model="kbForm.kata_kunci" placeholder="Contoh: munaqosyah, ujian, kenaikan jilid" class="h-9 text-xs" />
          </div>

          <div class="space-y-1">
            <label class="font-medium text-muted-foreground">Isi Jawaban / Penjelasan</label>
            <textarea
              v-model="kbForm.konten"
              rows="4"
              placeholder="Tuliskan penjelasan lengkap..."
              class="w-full p-2.5 rounded-md border border-input bg-background text-xs focus:outline-none focus:ring-1 focus:ring-ring"
            ></textarea>
          </div>
        </div>

        <DialogFooter class="pt-2 flex justify-end gap-2">
          <Button variant="outline" size="sm" class="text-xs" @click="showKbModal = false">
            Batal
          </Button>
          <Button size="sm" class="text-xs" :disabled="savingKb" @click="saveKb">
            {{ savingKb ? 'Menyimpan...' : 'Simpan' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import {
  Bot,
  BookOpen,
  MessageSquare,
  Settings,
  Send,
  Copy,
  Search,
  Check,
} from '@lucide/vue';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';

const toast = useToast();

const activeTab = ref('logs');
const copiedKey = ref('');
const webhookIncomingUrl = ref(`${window.location.origin}/api/webhook/whatsapp`);
const webhookConnectUrl = ref(`${window.location.origin}/api/webhook/whatsapp/connect`);
const webhookStatusUrl = ref(`${window.location.origin}/api/webhook/whatsapp/status`);

// KPI Stats
const stats = ref({
  total_today: 0,
  total_month: 0,
  success_rate: 100,
  intent_distribution: {},
});

// Logs state
const logs = ref([]);
const totalLogs = ref(0);
const totalPages = ref(1);
const currentPage = ref(1);
const filters = ref({
  intent: '',
  status: '',
  tanggal: '',
  search: '',
});
let searchTimeout = null;

// Modal
const selectedLog = ref(null);

const debounceSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadLogs(1);
  }, 350);
};

const loadStats = async () => {
  try {
    const res = await api.get('/chatbot/stats');
    if (res.data.success) {
      stats.value = res.data.data;
    }
  } catch (e) {}
};

const loadLogs = async (page = 1) => {
  try {
    currentPage.value = page;
    const params = {
      page,
      per_page: 15,
      ...filters.value,
    };
    const res = await api.get('/chatbot/logs', { params });
    if (res.data.success) {
      logs.value = res.data.data.data;
      totalLogs.value = res.data.data.total;
      totalPages.value = res.data.data.last_page;
    }
  } catch (e) {
    toast.error('Gagal memuat log percakapan.');
  }
};

function formatDateTime(val) {
  if (!val) return '-';
  const d = new Date(val);
  return d.toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function copyUrl(url, key) {
  navigator.clipboard.writeText(url);
  copiedKey.value = key;
  toast.success('URL Webhook berhasil disalin!');
  setTimeout(() => {
    copiedKey.value = '';
  }, 2500);
}

function openDetailModal(item) {
  selectedLog.value = item;
}

// Knowledge Base & Gemini state
const kbEntries = ref([]);
const showKbModal = ref(false);
const savingKb = ref(false);
const testingGemini = ref(false);
const geminiStatusMsg = ref('');
const kbForm = ref({
  id: null,
  kategori: 'umum',
  judul: '',
  kata_kunci: '',
  konten: '',
  urutan: 10,
});

const loadKnowledgeBase = async () => {
  try {
    const res = await api.get('/chatbot/knowledge-base');
    if (res.data.success) {
      kbEntries.value = res.data.data || [];
    }
  } catch (e) {
    toast.error('Gagal memuat Knowledge Base TPQ.');
  }
};

const openKbModal = (item = null) => {
  if (item) {
    kbForm.value = {
      id: item.id,
      kategori: item.kategori || 'umum',
      judul: item.judul || '',
      kata_kunci: item.kata_kunci || '',
      konten: item.konten || '',
      urutan: item.urutan ?? 10,
    };
  } else {
    kbForm.value = {
      id: null,
      kategori: 'umum',
      judul: '',
      kata_kunci: '',
      konten: '',
      urutan: (kbEntries.value.length || 5) + 1,
    };
  }
  showKbModal.value = true;
};

const saveKb = async () => {
  if (!kbForm.value.judul || !kbForm.value.konten) {
    toast.error('Judul dan isi informasi wajib diisi.');
    return;
  }
  savingKb.value = true;
  try {
    if (kbForm.value.id) {
      await api.put(`/chatbot/knowledge-base/${kbForm.value.id}`, kbForm.value);
      toast.success('Topik Knowledge Base berhasil diperbarui.');
    } else {
      await api.post('/chatbot/knowledge-base', kbForm.value);
      toast.success('Topik Knowledge Base berhasil ditambahkan.');
    }
    showKbModal.value = false;
    await loadKnowledgeBase();
  } catch (e) {
    toast.error('Gagal menyimpan topik Knowledge Base.');
  } finally {
    savingKb.value = false;
  }
};

const deleteKb = async (item) => {
  if (!window.confirm(`Hapus topik "${item.judul}" dari Knowledge Base?`)) return;
  try {
    await api.delete(`/chatbot/knowledge-base/${item.id}`);
    toast.success('Topik berhasil dihapus.');
    await loadKnowledgeBase();
  } catch (e) {
    toast.error('Gagal menghapus topik.');
  }
};

const testGemini = async () => {
  testingGemini.value = true;
  geminiStatusMsg.value = '';
  try {
    const res = await api.post('/chatbot/test-gemini');
    const data = res.data?.data || {};
    geminiStatusMsg.value = `✅ Terhubung (${data.model}): ${data.reply || data.message}`;
    toast.success('Koneksi Gemini AI berhasil!');
  } catch (e) {
    const errMsg = e.response?.data?.data?.message || 'GEMINI_API_KEY belum dikonfigurasi (Mode Fallback Lokal Aktif).';
    geminiStatusMsg.value = `ℹ️ Status Gemini: ${errMsg}`;
    toast.info(errMsg);
  } finally {
    testingGemini.value = false;
  }
};

onMounted(() => {
  loadStats();
  loadLogs();
});
</script>
