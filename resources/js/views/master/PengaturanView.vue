<template>
  <div class="space-y-5 max-w-5xl mx-auto pb-16">
    <!-- Header Halaman -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-3 border-b">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-lg font-semibold tracking-tight text-foreground">
            Pengaturan Sistem
          </h2>
          <Badge variant="outline" class="font-normal text-[11px] text-muted-foreground gap-1">
            <SlidersHorizontal class="size-3 text-primary" />
            <span>Konfigurasi</span>
          </Badge>
        </div>
        <p class="text-xs text-muted-foreground mt-0.5">
          Kelola profil lembaga, jam operasional, integrasi gateway, dan sinkronisasi sistem.
        </p>
      </div>

      <!-- Header Action Buttons -->
      <div class="flex items-center gap-2">
        <Button
          type="button"
          size="sm"
          class="h-8 gap-1.5 text-xs shadow-2xs"
          :disabled="isSaving"
          @click="saveSettings"
        >
          <LoaderCircle v-if="isSaving" class="size-3.5 animate-spin" />
          <Check v-else class="size-3.5" />
          <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Pengaturan' }}</span>
        </Button>
      </div>
    </div>

    <!-- Category Dropdown Selector for Mobile & Pills for Desktop -->
    <div class="space-y-2">
      <!-- Mobile View Dropdown (< sm) -->
      <div class="sm:hidden">
        <label class="text-[11px] font-medium text-muted-foreground block mb-1">
          Kategori Pengaturan:
        </label>
        <div class="relative">
          <select
            v-model="activeCategoryFilter"
            class="w-full h-10 pl-3 pr-8 rounded-xl border border-input bg-card text-xs font-semibold text-foreground shadow-2xs focus:outline-none focus:ring-2 focus:ring-primary/20 appearance-none cursor-pointer"
            @change="onFilterChange"
          >
            <option value="all">Semua Kategori (7 Bagian)</option>
            <option value="profil">1. Profil Lembaga</option>
            <option value="jam">2. Jam Operasional Presensi</option>
            <option value="whatsapp">3. WhatsApp Gateway (Fonnte)</option>
            <option value="telegram">4. Bot Telegram (Pengingat H-1)</option>
            <option value="pwa">5. Aplikasi Mobile (PWA)</option>
            <option value="spreadsheet">6. Google Spreadsheet</option>
            <option value="database">7. Pemeliharaan Database</option>
          </select>
          <ChevronDown class="size-4 text-muted-foreground absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        </div>
      </div>

      <!-- Desktop View Pills (>= sm) -->
      <div class="hidden sm:flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
        <button
          v-for="cat in categories"
          :key="cat.id"
          type="button"
          class="px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition-all cursor-pointer flex items-center gap-1.5"
          :class="activeCategoryFilter === cat.id
            ? 'bg-primary text-primary-foreground shadow-2xs'
            : 'bg-muted/40 hover:bg-muted text-muted-foreground hover:text-foreground border border-border/40'"
          @click="setCategoryFilter(cat.id)"
        >
          <component :is="cat.icon" class="size-3.5" />
          <span>{{ cat.shortLabel }}</span>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="p-12 text-center text-muted-foreground">
      <LoaderCircle class="size-8 mx-auto animate-spin mb-2 text-primary" />
      <p class="text-xs font-medium">Memuat pengaturan sistem...</p>
    </div>

    <!-- Accordion Category Dropdowns Container -->
    <div v-else class="space-y-3">

      <!-- 1. Profil & Identitas Lembaga -->
      <div
        v-show="shouldShowCategory('profil')"
        class="rounded-xl border border-border/70 bg-card overflow-hidden shadow-2xs transition-all"
        :class="{ 'ring-1 ring-primary/30': openCategories.profil }"
      >
        <!-- Dropdown Header Trigger -->
        <button
          type="button"
          class="w-full flex items-center justify-between p-3.5 sm:p-4 text-left cursor-pointer hover:bg-muted/30 transition-colors select-none"
          :aria-expanded="openCategories.profil"
          @click="toggleCategory('profil')"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="size-8 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
              <Building2 class="size-4" />
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold text-foreground">Profil Lembaga</span>
                <span class="text-[10px] text-muted-foreground font-mono bg-muted px-1.5 py-0.5 rounded">
                  TA: {{ settings.tahun_ajaran_aktif || '-' }}
                </span>
              </div>
              <p class="text-xs text-muted-foreground truncate mt-0.5">
                {{ settings.nama_instansi || 'Belum diisi' }}
                <template v-if="settings.kontak_instansi"> &bull; {{ settings.kontak_instansi }}</template>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0 ml-2">
            <Badge v-if="settings.logo_lembaga" variant="outline" class="hidden sm:inline-flex text-[10px] text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/30">
              Logo Ada
            </Badge>
            <ChevronDown
              class="size-4 text-muted-foreground transition-transform duration-200"
              :class="{ 'rotate-180': openCategories.profil }"
            />
          </div>
        </button>

        <!-- Dropdown Body Content -->
        <div v-show="openCategories.profil" class="px-4 pb-4 pt-1 border-t border-border/40 space-y-4">
          <!-- Logo Lembaga -->
          <div class="pt-2">
            <Label class="text-xs font-medium text-foreground block mb-2">Logo Lembaga (Favicon &amp; Kop Laporan)</Label>
            <div class="flex items-center gap-3.5">
              <div class="relative flex size-16 shrink-0 items-center justify-center rounded-xl border border-dashed border-input bg-muted/40 p-1 overflow-hidden">
                <img
                  v-if="settings.logo_lembaga"
                  :src="settings.logo_lembaga"
                  alt="Logo"
                  class="size-full object-contain"
                />
                <ImageIcon v-else class="size-6 text-muted-foreground/50" />
              </div>

              <div class="flex items-center gap-2 flex-wrap">
                <input
                  ref="logoFileInputRef"
                  type="file"
                  accept="image/png,image/jpeg,image/webp,image/svg+xml"
                  class="hidden"
                  @change="handleLogoUpload"
                />
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  class="h-8 gap-1.5 text-xs"
                  @click="triggerLogoSelect"
                >
                  <Upload class="size-3.5" />
                  <span>{{ settings.logo_lembaga ? 'Ganti Logo' : 'Pilih Logo' }}</span>
                </Button>
                <Button
                  v-if="settings.logo_lembaga"
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="h-8 gap-1.5 text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                  @click="removeLogo"
                >
                  <Trash2 class="size-3.5" />
                  <span>Hapus</span>
                </Button>
                <span class="text-[11px] text-muted-foreground">PNG / JPG transparan</span>
              </div>
            </div>
          </div>

          <!-- Form Fields Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div class="space-y-1.5 sm:col-span-2">
              <Label for="namaInstansi" class="text-xs font-medium">Nama Lembaga / TPQ *</Label>
              <Input
                id="namaInstansi"
                v-model="settings.nama_instansi"
                type="text"
                placeholder="Contoh: TPQ Nurul Mun'im"
                class="h-9 text-xs"
              />
            </div>

            <div class="space-y-1.5">
              <Label for="tahunAjaran" class="text-xs font-medium">Tahun Ajaran Aktif</Label>
              <Input
                id="tahunAjaran"
                v-model="settings.tahun_ajaran_aktif"
                type="text"
                placeholder="2026/2027"
                class="h-9 text-xs"
              />
            </div>

            <div class="space-y-1.5">
              <Label for="kontakInstansi" class="text-xs font-medium">Kontak Sekretariat</Label>
              <Input
                id="kontakInstansi"
                v-model="settings.kontak_instansi"
                type="text"
                placeholder="0812-3456-7890"
                class="h-9 text-xs"
              />
            </div>

            <div class="space-y-1.5">
              <Label for="noHpBendahara" class="text-xs font-medium">No. WhatsApp Bendahara (Info SPP)</Label>
              <Input
                id="noHpBendahara"
                v-model="settings.no_hp_bendahara"
                type="text"
                placeholder="085235567818"
                class="h-9 text-xs"
              />
            </div>

            <div class="space-y-1.5 sm:col-span-2">
              <Label for="alamatInstansi" class="text-xs font-medium">Alamat Lembaga (Kop Cetak)</Label>
              <Input
                id="alamatInstansi"
                v-model="settings.alamat_instansi"
                type="text"
                placeholder="Jl. Raya Karanganyar No. 15, Paiton, Probolinggo"
                class="h-9 text-xs"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Jam Operasional & Batas Terlambat -->
      <div
        v-show="shouldShowCategory('jam')"
        class="rounded-xl border border-border/70 bg-card overflow-hidden shadow-2xs transition-all"
        :class="{ 'ring-1 ring-primary/30': openCategories.jam }"
      >
        <!-- Dropdown Header Trigger -->
        <button
          type="button"
          class="w-full flex items-center justify-between p-3.5 sm:p-4 text-left cursor-pointer hover:bg-muted/30 transition-colors select-none"
          :aria-expanded="openCategories.jam"
          @click="toggleCategory('jam')"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="size-8 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
              <Clock class="size-4" />
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold text-foreground">Jam Operasional Presensi</span>
                <span class="text-[10px] text-muted-foreground font-mono bg-muted px-1.5 py-0.5 rounded">
                  {{ settings.jam_masuk }} - {{ settings.jam_terlambat }} WIB
                </span>
              </div>
              <p class="text-xs text-muted-foreground truncate mt-0.5">
                {{ activeDays.length }} hari aktif kegiatan per minggu
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0 ml-2">
            <ChevronDown
              class="size-4 text-muted-foreground transition-transform duration-200"
              :class="{ 'rotate-180': openCategories.jam }"
            />
          </div>
        </button>

        <!-- Dropdown Body Content -->
        <div v-show="openCategories.jam" class="px-4 pb-4 pt-1 border-t border-border/40 space-y-4">
          <!-- Time Inputs -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
            <div class="space-y-1.5">
              <Label for="jamMasuk" class="text-xs font-medium">Jam Masuk</Label>
              <Input
                id="jamMasuk"
                v-model="settings.jam_masuk"
                type="time"
                class="h-9 text-xs"
              />
              <p class="text-[10px] text-muted-foreground">Mulai tap scan masuk</p>
            </div>

            <div class="space-y-1.5">
              <Label for="jamTerlambat" class="text-xs font-medium">Batas Terlambat</Label>
              <Input
                id="jamTerlambat"
                v-model="settings.jam_terlambat"
                type="time"
                class="h-9 text-xs"
              />
              <p class="text-[10px] text-muted-foreground">Lewat jam ini dihitung terlambat</p>
            </div>

            <div class="space-y-1.5">
              <Label for="jamPulang" class="text-xs font-medium">Jam Pulang</Label>
              <Input
                id="jamPulang"
                v-model="settings.jam_pulang"
                type="time"
                class="h-9 text-xs"
              />
              <p class="text-[10px] text-muted-foreground">Waktu tap kepulangan santri</p>
            </div>
          </div>

          <!-- Hari Aktif Selector -->
          <div class="space-y-2 pt-1 border-t border-border/40">
            <div class="flex items-center justify-between">
              <Label class="text-xs font-medium">Hari Aktif Pembelajaran</Label>
              <span class="text-[11px] text-muted-foreground font-medium">
                {{ activeDays.length }} hari dipilih
              </span>
            </div>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="day in allDays"
                :key="day"
                type="button"
                class="px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all cursor-pointer"
                :class="activeDays.includes(day)
                  ? 'bg-primary text-primary-foreground shadow-2xs font-semibold'
                  : 'bg-muted/40 hover:bg-muted text-muted-foreground hover:text-foreground border border-border/50'"
                @click="toggleDay(day)"
              >
                {{ day }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. WhatsApp Gateway (Fonnte API) -->
      <div
        v-show="shouldShowCategory('whatsapp')"
        class="rounded-xl border border-border/70 bg-card overflow-hidden shadow-2xs transition-all"
        :class="{ 'ring-1 ring-primary/30': openCategories.whatsapp }"
      >
        <!-- Dropdown Header Trigger -->
        <button
          type="button"
          class="w-full flex items-center justify-between p-3.5 sm:p-4 text-left cursor-pointer hover:bg-muted/30 transition-colors select-none"
          :aria-expanded="openCategories.whatsapp"
          @click="toggleCategory('whatsapp')"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="size-8 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
              <MessageSquare class="size-4" />
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold text-foreground">WhatsApp Gateway (Fonnte)</span>
                <Badge
                  :variant="settings.fonnte_token ? 'default' : 'secondary'"
                  class="text-[10px] px-1.5 py-0"
                >
                  {{ settings.fonnte_token ? 'Token Ada' : 'Belum Ada Token' }}
                </Badge>
              </div>
              <p class="text-xs text-muted-foreground truncate mt-0.5">
                Notif Presensi: {{ settings.wa_notif_presensi === '1' ? 'Aktif' : 'Non-Aktif' }}
                &bull; Jeda antrean: {{ settings.wa_notif_delay || '3-5' }}s
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0 ml-2">
            <ChevronDown
              class="size-4 text-muted-foreground transition-transform duration-200"
              :class="{ 'rotate-180': openCategories.whatsapp }"
            />
          </div>
        </button>

        <!-- Dropdown Body Content -->
        <div v-show="openCategories.whatsapp" class="px-4 pb-4 pt-1 border-t border-border/40 space-y-4">
          <!-- Token Input & Test Connection -->
          <div class="space-y-2 pt-2">
            <Label for="fonnteToken" class="text-xs font-medium">API Token Fonnte</Label>
            <div class="flex flex-col sm:flex-row gap-2">
              <Input
                id="fonnteToken"
                v-model="settings.fonnte_token"
                type="password"
                placeholder="Masukkan API Token Fonnte..."
                class="h-9 font-mono text-xs flex-1"
              />
              <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-9 gap-1.5 text-xs shrink-0"
                :disabled="!settings.fonnte_token || isTestingWa"
                @click="testWaGateway"
              >
                <LoaderCircle v-if="isTestingWa" class="size-3.5 animate-spin" />
                <Radio v-else class="size-3.5 text-emerald-600" />
                <span>Uji Koneksi Gateway</span>
              </Button>
            </div>
          </div>

          <!-- Toggle Notifikasi Presensi Otomatis -->
          <div class="pt-2 border-t border-border/40 space-y-3">
            <div class="flex items-center justify-between gap-3 p-2.5 rounded-lg border bg-muted/20">
              <div class="space-y-0.5">
                <span class="text-xs font-semibold text-foreground">Kirim Notifikasi WA Presensi Otomatis</span>
                <p class="text-[11px] text-muted-foreground">Kirim otomatis pesan WhatsApp ke wali saat santri scan masuk</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input
                  type="checkbox"
                  v-model="settings.wa_notif_presensi"
                  true-value="1"
                  false-value="0"
                  class="sr-only peer"
                />
                <div class="w-9 h-5 bg-zinc-200 peer-focus:outline-none rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-zinc-600 peer-checked:bg-emerald-600"></div>
              </label>
            </div>

            <!-- Anti Spam Delay -->
            <div v-if="settings.wa_notif_presensi === '1'" class="space-y-1.5">
              <Label for="waNotifDelay" class="text-xs font-medium">Jeda Antrean Scan Bersamaan (Anti-Spam)</Label>
              <select
                id="waNotifDelay"
                v-model="settings.wa_notif_delay"
                class="w-full h-9 rounded-lg border border-input bg-background px-3 text-xs focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer"
              >
                <option value="3-5">3 - 5 detik (Direkomendasikan - Alami & Aman)</option>
                <option value="5-10">5 - 10 detik (Sangat Aman)</option>
                <option value="2-3">2 - 3 detik (Cepat)</option>
                <option value="1">1 detik (Minimum)</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- 4. Bot Telegram (Pengingat H-1 Kegiatan) -->
      <div
        v-show="shouldShowCategory('telegram')"
        class="rounded-xl border border-border/70 bg-card overflow-hidden shadow-2xs transition-all"
        :class="{ 'ring-1 ring-primary/30': openCategories.telegram }"
      >
        <!-- Dropdown Header Trigger -->
        <button
          type="button"
          class="w-full flex items-center justify-between p-3.5 sm:p-4 text-left cursor-pointer hover:bg-muted/30 transition-colors select-none"
          :aria-expanded="openCategories.telegram"
          @click="toggleCategory('telegram')"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="size-8 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
              <Send class="size-4" />
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold text-foreground">Bot Telegram (Pengingat H-1)</span>
                <Badge
                  variant="outline"
                  :class="settings.telegram_notif_agenda_aktif === '1' ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/30' : 'text-muted-foreground bg-muted'"
                  class="text-[10px] px-1.5 py-0"
                >
                  {{ settings.telegram_notif_agenda_aktif === '1' ? 'Aktif' : 'Non-Aktif' }}
                </Badge>
              </div>
              <p class="text-xs text-muted-foreground truncate mt-0.5">
                Pengingat kegiatan kalender jam {{ settings.telegram_notif_agenda_jam || '07:00' }} WIB
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0 ml-2">
            <ChevronDown
              class="size-4 text-muted-foreground transition-transform duration-200"
              :class="{ 'rotate-180': openCategories.telegram }"
            />
          </div>
        </button>

        <!-- Dropdown Body Content -->
        <div v-show="openCategories.telegram" class="px-4 pb-4 pt-1 border-t border-border/40 space-y-4">
          <!-- Toggle Pengingat -->
          <div class="flex items-center justify-between gap-3 p-2.5 rounded-lg border bg-muted/20 mt-2">
            <div class="space-y-0.5">
              <span class="text-xs font-semibold text-foreground">Pengingat H-1 Kegiatan Kalender</span>
              <p class="text-[11px] text-muted-foreground">Kirim pesan otomatis 1 hari sebelum agenda berlangsung ke Telegram</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
              <input
                type="checkbox"
                v-model="settings.telegram_notif_agenda_aktif"
                true-value="1"
                false-value="0"
                class="sr-only peer"
              />
              <div class="w-9 h-5 bg-zinc-200 peer-focus:outline-none rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-zinc-600 peer-checked:bg-sky-600"></div>
            </label>
          </div>

          <!-- Form Fields -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div class="space-y-1.5">
              <Label for="teleJamNotif" class="text-xs font-medium">Jam Pengiriman (H-1)</Label>
              <Input
                id="teleJamNotif"
                v-model="settings.telegram_notif_agenda_jam"
                type="time"
                class="h-9 font-mono text-xs"
              />
            </div>

            <div class="space-y-1.5">
              <Label for="teleChatId" class="text-xs font-medium">Chat ID Admin / Grup</Label>
              <Input
                id="teleChatId"
                v-model="settings.telegram_chat_id"
                type="text"
                placeholder="Contoh: -100123456789"
                class="h-9 font-mono text-xs"
              />
            </div>

            <div class="space-y-1.5 sm:col-span-2">
              <Label for="teleBotToken" class="text-xs font-medium">Bot Token (@BotFather)</Label>
              <Input
                id="teleBotToken"
                v-model="settings.telegram_bot_token"
                type="password"
                placeholder="123456:ABC-DEF1234ghIkl..."
                class="h-9 font-mono text-xs"
              />
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-border/40">
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="h-8 text-xs gap-1.5"
              :disabled="isTestingTelegram || !settings.telegram_bot_token || !settings.telegram_chat_id"
              @click="handleTestTelegram"
            >
              <LoaderCircle v-if="isTestingTelegram" class="size-3.5 animate-spin" />
              <Send v-else class="size-3.5" />
              <span>{{ isTestingTelegram ? 'Menguji...' : 'Uji Bot' }}</span>
            </Button>

            <Button
              type="button"
              variant="secondary"
              size="sm"
              class="h-8 text-xs gap-1.5"
              :disabled="isTriggeringAgenda || !settings.telegram_bot_token || !settings.telegram_chat_id"
              @click="handleTriggerAgendaTelegram"
            >
              <LoaderCircle v-if="isTriggeringAgenda" class="size-3.5 animate-spin" />
              <Clock v-else class="size-3.5" />
              <span>Cek H-1 Sekarang</span>
            </Button>
          </div>
        </div>
      </div>

      <!-- 5. Aplikasi Mobile (PWA) -->
      <div
        v-show="shouldShowCategory('pwa')"
        class="rounded-xl border border-border/70 bg-card overflow-hidden shadow-2xs transition-all"
        :class="{ 'ring-1 ring-primary/30': openCategories.pwa }"
      >
        <!-- Dropdown Header Trigger -->
        <button
          type="button"
          class="w-full flex items-center justify-between p-3.5 sm:p-4 text-left cursor-pointer hover:bg-muted/30 transition-colors select-none"
          :aria-expanded="openCategories.pwa"
          @click="toggleCategory('pwa')"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="size-8 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
              <Smartphone class="size-4" />
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold text-foreground">Aplikasi Mobile (PWA)</span>
                <Badge variant="outline" class="text-[10px] text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/30 px-1.5 py-0">
                  {{ isInstalled ? 'Terpasang' : 'Tersedia' }}
                </Badge>
              </div>
              <p class="text-xs text-muted-foreground truncate mt-0.5">
                Dapat dipasang langsung di layar utama Android, iOS &amp; Komputer
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0 ml-2">
            <ChevronDown
              class="size-4 text-muted-foreground transition-transform duration-200"
              :class="{ 'rotate-180': openCategories.pwa }"
            />
          </div>
        </button>

        <!-- Dropdown Body Content -->
        <div v-show="openCategories.pwa" class="px-4 pb-4 pt-1 border-t border-border/40 space-y-3">
          <div class="flex items-center justify-between gap-3 p-3 rounded-lg border bg-muted/20 mt-2">
            <div class="flex items-center gap-3 min-w-0">
              <div class="size-10 rounded-lg bg-white border p-0.5 shrink-0 overflow-hidden flex items-center justify-center shadow-2xs">
                <img :src="'/icons/pwa-192x192.png'" alt="Ikon PWA" class="size-full object-contain" />
              </div>
              <div class="min-w-0">
                <p class="text-xs font-semibold text-foreground truncate">Presensi Santri TPQ</p>
                <p class="text-[11px] text-muted-foreground">Mendukung mode offline &amp; layar penuh</p>
              </div>
            </div>

            <Button
              v-if="isInstallable && !isInstalled"
              type="button"
              size="sm"
              class="h-8 gap-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white shrink-0"
              @click="installApp"
            >
              <Download class="size-3.5" />
              <span>Install</span>
            </Button>
            <Badge v-else-if="isInstalled" variant="secondary" class="text-xs font-medium shrink-0">
              Terpasang
            </Badge>
          </div>
        </div>
      </div>

      <!-- 6. Google Spreadsheet (Sheet Absensi Realtime) -->
      <div
        v-show="shouldShowCategory('spreadsheet')"
        class="rounded-xl border border-border/70 bg-card overflow-hidden shadow-2xs transition-all"
        :class="{ 'ring-1 ring-primary/30': openCategories.spreadsheet }"
      >
        <!-- Dropdown Header Trigger -->
        <button
          type="button"
          class="w-full flex items-center justify-between p-3.5 sm:p-4 text-left cursor-pointer hover:bg-muted/30 transition-colors select-none"
          :aria-expanded="openCategories.spreadsheet"
          @click="toggleCategory('spreadsheet')"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="size-8 rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400 flex items-center justify-center shrink-0">
              <FileSpreadsheet class="size-4" />
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold text-foreground">Google Spreadsheet</span>
                <Badge
                  :variant="settings.spreadsheet_absensi_webhook_url ? 'default' : 'secondary'"
                  class="text-[10px] px-1.5 py-0"
                >
                  {{ settings.spreadsheet_absensi_webhook_url ? 'Webhook Terisi' : 'Belum Terhubung' }}
                </Badge>
              </div>
              <p class="text-xs text-muted-foreground truncate mt-0.5">
                Sinkronisasi otomatis setiap scan santri ke sheet 'absensi'
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0 ml-2">
            <ChevronDown
              class="size-4 text-muted-foreground transition-transform duration-200"
              :class="{ 'rotate-180': openCategories.spreadsheet }"
            />
          </div>
        </button>

        <!-- Dropdown Body Content -->
        <div v-show="openCategories.spreadsheet" class="px-4 pb-4 pt-1 border-t border-border/40 space-y-3">
          <div class="space-y-1.5 pt-2">
            <Label for="sheetWebhookUrl" class="text-xs font-medium">URL Web App Google Apps Script</Label>
            <div class="flex flex-col sm:flex-row gap-2">
              <Input
                id="sheetWebhookUrl"
                v-model="settings.spreadsheet_absensi_webhook_url"
                type="text"
                placeholder="https://script.google.com/macros/s/.../exec"
                class="h-9 font-mono text-xs flex-1"
              />
              <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-9 gap-1.5 text-xs shrink-0"
                :disabled="!settings.spreadsheet_absensi_webhook_url || isTestingSpreadsheet"
                @click="testSpreadsheetWebhook"
              >
                <LoaderCircle v-if="isTestingSpreadsheet" class="size-3.5 animate-spin" />
                <Radio v-else class="size-3.5 text-teal-600" />
                <span>Uji Kirim Baris</span>
              </Button>
            </div>
          </div>

          <!-- Toggle Panduan Kode Apps Script -->
          <div class="pt-2 border-t border-border/40">
            <div class="flex items-center justify-between">
              <button
                type="button"
                class="text-xs font-medium text-primary hover:underline flex items-center gap-1.5 cursor-pointer"
                @click="showScriptTemplate = !showScriptTemplate"
              >
                <Code class="size-3.5" />
                <span>{{ showScriptTemplate ? 'Sembunyikan Panduan Kode Apps Script' : 'Lihat Kode Google Apps Script' }}</span>
              </button>
              <Button
                v-if="showScriptTemplate"
                type="button"
                variant="outline"
                size="sm"
                class="h-6 text-[10px] px-2 gap-1"
                @click="copyAppsScriptCode"
              >
                <Check v-if="isScriptCopied" class="size-3 text-emerald-600" />
                <Copy v-else class="size-3" />
                <span>{{ isScriptCopied ? 'Tersalin' : 'Salin Kode' }}</span>
              </Button>
            </div>

            <div v-if="showScriptTemplate" class="mt-3 space-y-2">
              <div class="flex items-center gap-1.5 text-xs">
                <button
                  type="button"
                  class="px-2.5 py-1 rounded text-[11px] font-medium transition-colors cursor-pointer"
                  :class="scriptMode === 'insert' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:text-foreground'"
                  @click="scriptMode = 'insert'"
                >
                  Sisipkan ke Code.gs
                </button>
                <button
                  type="button"
                  class="px-2.5 py-1 rounded text-[11px] font-medium transition-colors cursor-pointer"
                  :class="scriptMode === 'full' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:text-foreground'"
                  @click="scriptMode = 'full'"
                >
                  Kode Lengkap
                </button>
              </div>

              <pre class="text-[10px] font-mono p-2.5 rounded-lg bg-zinc-950 text-zinc-100 dark:bg-black dark:text-zinc-200 border overflow-x-auto max-h-44 leading-relaxed select-all">{{ activeScriptCode }}</pre>
            </div>
          </div>
        </div>
      </div>

      <!-- 7. Pemeliharaan Database (TiDB Cloud / MySQL) -->
      <div
        v-show="shouldShowCategory('database')"
        class="rounded-xl border border-border/70 bg-card overflow-hidden shadow-2xs transition-all"
        :class="{ 'ring-1 ring-primary/30': openCategories.database }"
      >
        <!-- Dropdown Header Trigger -->
        <button
          type="button"
          class="w-full flex items-center justify-between p-3.5 sm:p-4 text-left cursor-pointer hover:bg-muted/30 transition-colors select-none"
          :aria-expanded="openCategories.database"
          @click="toggleCategory('database')"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="size-8 rounded-lg bg-zinc-500/10 text-zinc-600 dark:text-zinc-400 flex items-center justify-center shrink-0">
              <Database class="size-4" />
            </div>
            <div class="min-w-0">
              <span class="text-sm font-semibold text-foreground">Pemeliharaan Database</span>
              <p class="text-xs text-muted-foreground truncate mt-0.5">
                Sinkronkan skema tabel &amp; migrasi database untuk lingkungan server/cloud
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0 ml-2">
            <ChevronDown
              class="size-4 text-muted-foreground transition-transform duration-200"
              :class="{ 'rotate-180': openCategories.database }"
            />
          </div>
        </button>

        <!-- Dropdown Body Content -->
        <div v-show="openCategories.database" class="px-4 pb-4 pt-1 border-t border-border/40 space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
            <p class="text-xs text-muted-foreground">
              Jalankan migrasi jika terdapat tabel baru yang belum dibuat di database remote.
            </p>
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="h-8 gap-1.5 text-xs shrink-0"
              :disabled="isMigrating"
              @click="runMigration"
            >
              <LoaderCircle v-if="isMigrating" class="size-3.5 animate-spin" />
              <Database v-else class="size-3.5" />
              <span>{{ isMigrating ? 'Menjalankan...' : 'Sinkronkan Database' }}</span>
            </Button>
          </div>

          <div v-if="migrationOutput" class="space-y-1 pt-1">
            <span class="text-[10px] text-muted-foreground font-mono">Hasil Output Migrasi:</span>
            <pre class="text-[10px] font-mono p-2.5 rounded bg-muted/40 border overflow-x-auto max-h-32 text-muted-foreground">{{ migrationOutput }}</pre>
          </div>
        </div>
      </div>

    </div>

    <!-- Floating Sticky Save Bar (for mobile & unsaved changes) -->
    <div
      v-if="isDirty"
      class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 sm:w-auto z-40 animate-in fade-in slide-in-from-bottom-2 duration-200"
    >
      <div class="flex items-center justify-between sm:justify-start gap-3 p-3 rounded-2xl bg-zinc-900 text-zinc-100 dark:bg-zinc-100 dark:text-zinc-900 shadow-2xl border border-zinc-800 dark:border-zinc-200">
        <div class="flex items-center gap-2">
          <div class="size-2 rounded-full bg-amber-400 animate-pulse"></div>
          <span class="text-xs font-medium">Ada perubahan belum disimpan</span>
        </div>
        <Button
          type="button"
          size="sm"
          class="h-8 px-3 text-xs bg-primary hover:bg-primary/90 text-primary-foreground gap-1.5 shrink-0"
          :disabled="isSaving"
          @click="saveSettings"
        >
          <LoaderCircle v-if="isSaving" class="size-3.5 animate-spin" />
          <Check v-else class="size-3.5" />
          <span>Simpan Sekarang</span>
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import {
  Building2,
  Check,
  ChevronDown,
  Clock,
  Code,
  Copy,
  Database,
  Download,
  FileSpreadsheet,
  Image as ImageIcon,
  LoaderCircle,
  MessageSquare,
  Radio,
  Send,
  SlidersHorizontal,
  Smartphone,
  Trash2,
  Upload,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';
import { useAppConfig } from '@/composables/useAppConfig';
import { usePwa } from '@/composables/usePwa';

const toast = useToast();
const { updateConfig } = useAppConfig();
const { isInstallable, isInstalled, installApp } = usePwa();

// Categories definitions
const categories = [
  { id: 'all', shortLabel: 'Semua (7)', icon: SlidersHorizontal },
  { id: 'profil', shortLabel: 'Profil', icon: Building2 },
  { id: 'jam', shortLabel: 'Jam & Hari', icon: Clock },
  { id: 'whatsapp', shortLabel: 'WhatsApp', icon: MessageSquare },
  { id: 'telegram', shortLabel: 'Telegram', icon: Send },
  { id: 'pwa', shortLabel: 'PWA Mobile', icon: Smartphone },
  { id: 'spreadsheet', shortLabel: 'Spreadsheet', icon: FileSpreadsheet },
  { id: 'database', shortLabel: 'Database', icon: Database },
];

// Dropdown Accordion States
const openCategories = ref({
  profil: true,
  jam: false,
  whatsapp: false,
  telegram: false,
  pwa: false,
  spreadsheet: false,
  database: false,
});

const activeCategoryFilter = ref('all');

function toggleCategory(catId) {
  openCategories.value[catId] = !openCategories.value[catId];
}

function setCategoryFilter(catId) {
  activeCategoryFilter.value = catId;
  if (catId !== 'all') {
    // Automatically open the selected category
    openCategories.value[catId] = true;
  }
}

function onFilterChange() {
  setCategoryFilter(activeCategoryFilter.value);
}

function shouldShowCategory(catId) {
  if (activeCategoryFilter.value === 'all') return true;
  return activeCategoryFilter.value === catId;
}

// Days of week
const allDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
const activeDays = ref(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']);

function toggleDay(day) {
  const index = activeDays.value.indexOf(day);
  if (index === -1) {
    activeDays.value.push(day);
  } else {
    if (activeDays.value.length > 1) {
      activeDays.value.splice(index, 1);
    } else {
      toast.warning('Minimal harus ada 1 hari aktif kegiatan.');
    }
  }
}

// Loading & Execution States
const isLoading = ref(false);
const isSaving = ref(false);
const isTestingWa = ref(false);
const isTestingTelegram = ref(false);
const isTriggeringAgenda = ref(false);
const isTestingSpreadsheet = ref(false);
const isMigrating = ref(false);
const migrationOutput = ref('');
const showScriptTemplate = ref(false);
const scriptMode = ref('insert');
const isScriptCopied = ref(false);
const logoFileInputRef = ref(null);

const settings = ref({
  nama_instansi: "TPQ Nurul Mun'im",
  tahun_ajaran_aktif: '2026/2027',
  alamat_instansi: '',
  kontak_instansi: '',
  no_hp_bendahara: '085235567818',
  logo_lembaga: '',
  jam_masuk: '07:00',
  jam_terlambat: '07:15',
  jam_pulang: '12:00',
  hari_aktif: 'Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
  fonnte_token: '',
  wa_notif_presensi: '1',
  wa_notif_delay: '3-5',
  spreadsheet_absensi_webhook_url: '',
  telegram_bot_token: '',
  telegram_chat_id: '',
  telegram_notif_agenda_aktif: '1',
  telegram_notif_agenda_jam: '07:00',
});

// Dirty state tracking
const originalStateString = ref('');

const isDirty = computed(() => {
  if (!originalStateString.value) return false;
  const currentSnapshot = JSON.stringify({
    ...settings.value,
    hari_aktif: [...activeDays.value].sort().join(','),
  });
  return currentSnapshot !== originalStateString.value;
});

function captureOriginalState() {
  originalStateString.value = JSON.stringify({
    ...settings.value,
    hari_aktif: [...activeDays.value].sort().join(','),
  });
}

// Logo Handling
function triggerLogoSelect() {
  if (logoFileInputRef.value) {
    logoFileInputRef.value.click();
  }
}

function handleLogoUpload(e) {
  const file = e.target.files?.[0];
  if (!file) return;

  if (!file.type.startsWith('image/')) {
    toast.error('File yang dipilih harus berupa format gambar.');
    return;
  }

  const reader = new FileReader();
  reader.onload = (readerEvent) => {
    const img = new Image();
    img.onload = () => {
      let targetDim = 160;
      let compressedDataUrl = '';

      for (let attempt = 0; attempt < 5; attempt++) {
        let width = img.width;
        let height = img.height;

        if (width > height) {
          if (width > targetDim) {
            height = Math.round((height * targetDim) / width);
            width = targetDim;
          }
        } else {
          if (height > targetDim) {
            width = Math.round((width * targetDim) / height);
            height = targetDim;
          }
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, width);
        canvas.height = Math.max(1, height);
        const ctx = canvas.getContext('2d');
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        compressedDataUrl = canvas.toDataURL('image/png');

        if (compressedDataUrl.length < 40000 || targetDim <= 70) {
          break;
        }
        targetDim -= 25;
      }

      settings.value.logo_lembaga = compressedDataUrl;
      toast.success('Logo berhasil dipilih. Klik "Simpan Pengaturan" untuk menerapkan.');
    };
    img.src = readerEvent.target.result;
  };
  reader.readAsDataURL(file);
}

function removeLogo() {
  settings.value.logo_lembaga = '';
  if (logoFileInputRef.value) {
    logoFileInputRef.value.value = '';
  }
  toast.info('Logo dihapus.');
}

// API Calls
async function loadSettings() {
  isLoading.value = true;
  try {
    const res = await api.get('/pengaturan');
    const data = res.data.data || {};
    settings.value = {
      ...settings.value,
      ...data,
    };

    if (data.hari_aktif) {
      activeDays.value = data.hari_aktif.split(',').map(s => s.trim()).filter(Boolean);
    }
    captureOriginalState();
  } catch (err) {
    toast.error('Gagal memuat data pengaturan.');
  } finally {
    isLoading.value = false;
  }
}

async function saveSettings() {
  isSaving.value = true;
  try {
    const payload = {
      ...settings.value,
      hari_aktif: activeDays.value.join(','),
    };

    const res = await api.post('/pengaturan', payload);
    updateConfig(settings.value);
    captureOriginalState();
    toast.success(res.data.message || 'Pengaturan sistem berhasil disimpan.');
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal menyimpan pengaturan sistem.';
    toast.error(msg);
  } finally {
    isSaving.value = false;
  }
}

async function testWaGateway() {
  if (!settings.value.fonnte_token) {
    toast.warning('Silakan masukkan token Fonnte terlebih dahulu.');
    return;
  }

  isTestingWa.value = true;
  try {
    const res = await api.post('/pengaturan/test-wa', {
      token: settings.value.fonnte_token,
    });
    if (res.data.success) {
      toast.success(res.data.message || 'Koneksi ke gateway WhatsApp berhasil!');
    } else {
      toast.error(res.data.message || 'Koneksi ke gateway WhatsApp gagal.');
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal menghubungi server Fonnte.';
    toast.error(msg);
  } finally {
    isTestingWa.value = false;
  }
}

async function handleTestTelegram() {
  if (!settings.value.telegram_bot_token || !settings.value.telegram_chat_id) {
    toast.warning('Token Bot dan Chat ID Telegram wajib diisi.');
    return;
  }

  isTestingTelegram.value = true;
  try {
    const res = await api.post('/pengaturan/test-telegram', {
      bot_token: settings.value.telegram_bot_token,
      chat_id: settings.value.telegram_chat_id,
    });
    if (res.data?.success) {
      toast.success(res.data?.message || 'Uji koneksi Telegram berhasil! Pesan tes terkirim.');
    } else {
      toast.error(res.data?.message || 'Uji koneksi Telegram gagal.');
    }
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Gagal menghubungi server Telegram.';
    toast.error(`Uji Telegram Gagal: ${msg}`);
  } finally {
    isTestingTelegram.value = false;
  }
}

async function handleTriggerAgendaTelegram() {
  if (!settings.value.telegram_bot_token || !settings.value.telegram_chat_id) {
    toast.warning('Token Bot dan Chat ID Telegram wajib diisi.');
    return;
  }

  isTriggeringAgenda.value = true;
  try {
    const res = await api.post('/pengaturan/trigger-agenda-telegram', { force: true });
    if (res.data?.success) {
      if (res.data?.sent) {
        toast.success(res.data?.message || 'Pengingat kegiatan (H-1) berhasil dikirim ke Telegram!');
      } else {
        toast.info(res.data?.message || 'Tidak ada agenda kegiatan besok.');
      }
    } else {
      toast.error(res.data?.message || 'Gagal mengirim pengingat agenda ke Telegram.');
    }
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Gagal memproses pengingat agenda.';
    toast.error(`Error: ${msg}`);
  } finally {
    isTriggeringAgenda.value = false;
  }
}

// Google Apps Script Snippets
const appsScriptInsertSnippet = `      // === SINKRONISASI OTOMATIS DARI WEBSITE LARAVEL KE SHEET 'absensi' ===
      if (data.action === 'syncAbsensi') {
        var ss = null;
        try {
          ss = SpreadsheetApp.getActiveSpreadsheet();
        } catch (e1) {}

        if (!ss) {
          ss = getSpreadsheet();
        }

        var sheet = ss.getSheetByName('absensi');
        if (!sheet) {
          sheet = ss.insertSheet('absensi');
          sheet.appendRow(['Tanggal', 'NISN', 'Nama', 'Kelas', 'Jam Datang', 'Jam Pulang', 'Keterangan Waktu', 'Status']);
        }

        var newRow = [
          data.tanggal || Utilities.formatDate(new Date(), 'Asia/Jakarta', 'MM/dd/yyyy'),
          "'" + String(data.nisn || '').replace(/^'/, ''),
          data.nama || '-',
          data.kelas || '-',
          data.jam_datang || '-',
          data.jam_pulang || '-',
          data.keterangan_waktu || '-',
          data.status || 'Hadir'
        ];

        sheet.appendRow(newRow);
        SpreadsheetApp.flush();

        return ContentService.createTextOutput(JSON.stringify({
          success: true,
          message: 'Baris kehadiran berhasil ditambahkan ke sheet absensi!',
          row: newRow
        })).setMimeType(ContentService.MimeType.JSON);
      }`;

const appsScriptFullTemplate = `/**
 * KODE SINKRONISASI ABSENSI OTOMATIS (WEB LARAVEL -> GOOGLE SPREADSHEET)
 */
function doGet(e) {
  return handleSyncAbsensi(e);
}

function doPost(e) {
  return handleSyncAbsensi(e);
}

function handleSyncAbsensi(e) {
  try {
    var data = {};
    if (e && e.postData && e.postData.contents) {
      try {
        data = JSON.parse(e.postData.contents);
      } catch (errJson) {
        data = e.parameter || {};
      }
    } else if (e && e.parameter) {
      data = e.parameter;
    }

    if (data.action === 'syncAbsensi') {
      var ss = null;
      try {
        ss = SpreadsheetApp.getActiveSpreadsheet();
      } catch (e1) {}

      var sheet = ss.getSheetByName('absensi');
      if (!sheet) {
        sheet = ss.insertSheet('absensi');
        sheet.appendRow(['Tanggal', 'NISN', 'Nama', 'Kelas', 'Jam Datang', 'Jam Pulang', 'Keterangan Waktu', 'Status']);
      }

      sheet.appendRow([
        data.tanggal || Utilities.formatDate(new Date(), 'Asia/Jakarta', 'MM/dd/yyyy'),
        "'" + String(data.nisn || '').replace(/^'/, ''),
        data.nama || '-',
        data.kelas || '-',
        data.jam_datang || '-',
        data.jam_pulang || '-',
        data.keterangan_waktu || '-',
        data.status || 'Hadir'
      ]);

      SpreadsheetApp.flush();

      return ContentService.createTextOutput(JSON.stringify({ 
        success: true, 
        message: 'Baris kehadiran berhasil ditambahkan ke sheet absensi!' 
      })).setMimeType(ContentService.MimeType.JSON);
    }

    return ContentService.createTextOutput(JSON.stringify({ 
      success: false, 
      message: 'Aksi tidak dikenali' 
    })).setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ 
      success: false, 
      error: err.message 
    })).setMimeType(ContentService.MimeType.JSON);
  }
}`;

const activeScriptCode = computed(() => {
  return scriptMode.value === 'insert' ? appsScriptInsertSnippet : appsScriptFullTemplate;
});

async function testSpreadsheetWebhook() {
  if (!settings.value.spreadsheet_absensi_webhook_url) {
    toast.warning('Silakan masukkan URL Web App Google Apps Script terlebih dahulu.');
    return;
  }

  isTestingSpreadsheet.value = true;
  try {
    const res = await api.post('/pengaturan/test-spreadsheet', {
      webhook_url: settings.value.spreadsheet_absensi_webhook_url,
    });
    if (res.data.success) {
      toast.success(res.data.message || 'Berhasil terhubung ke Google Spreadsheet!');
    } else {
      toast.error(res.data.message || 'Gagal mengirim ke Google Spreadsheet.');
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal menghubungi Google Apps Script.';
    toast.error(msg);
  } finally {
    isTestingSpreadsheet.value = false;
  }
}

function copyAppsScriptCode() {
  if (navigator?.clipboard) {
    navigator.clipboard.writeText(activeScriptCode.value);
    isScriptCopied.value = true;
    toast.success('Kode Google Apps Script berhasil disalin!');
    setTimeout(() => {
      isScriptCopied.value = false;
    }, 3000);
  }
}

// Database Migration
async function runMigration() {
  isMigrating.value = true;
  migrationOutput.value = '';
  try {
    const res = await api.post('/pengaturan/migrate');
    migrationOutput.value = res.data.output || 'Semua migrasi sudah up-to-date.';
    toast.success(res.data.message || 'Migrasi database berhasil dijalankan.');
  } catch (err) {
    const msg = err.response?.data?.message || err.message;
    migrationOutput.value = msg;
    toast.error('Gagal menjalankan migrasi: ' + msg);
  } finally {
    isMigrating.value = false;
  }
}

onMounted(() => {
  loadSettings();
});
</script>
