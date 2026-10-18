/**
 * ============================================================================
 * SISTEM INFORMASI PEMBAYARAN SPP SANTRI
 * Backend: Google Apps Script
 * Database: Google Spreadsheet
 * Desain & Arsitektur: Bernuansa Hijau Islami Modern (Sesuai Standar Absensi Update)
 * ============================================================================
 */

// ============================================================================
// KONFIGURASI SPREADSHEET
// ============================================================================
// 1. ID Google Spreadsheet Database SPP:
// (Biarkan kosong '' jika skrip Apps Script ini dibuat langsung dari dalam Google Sheet SPP)
var SPREADSHEET_ID = '';

// 2. ID Google Spreadsheet yang menyimpan Data Santri / Siswa (Absensi):
// Data siswa dan jilid akan diambil otomatis dari spreadsheet ini tanpa perlu input manual di web SPP.
var SPREADSHEET_SISWA_ID = '1mVn1iXEGDYQoQ1Ft-_nHgC7WOGJeuAg--Bu-cdoiTNk';
var SHEET_SISWA_NAME = 'siswa';

/**
 * Mendapatkan instance Spreadsheet dengan penanganan error yang jelas dan ramah pengguna
 */
function getSpreadsheet() {
  if (SPREADSHEET_ID && SPREADSHEET_ID.trim() !== '') {
    try {
      return SpreadsheetApp.openById(SPREADSHEET_ID.trim());
    } catch (e) {
      throw new Error('Gagal membuka Spreadsheet SPP ID "' + SPREADSHEET_ID + '". Pastikan ID benar dan akun memiliki akses edit: ' + e.message);
    }
  }

  // Jika script terikat langsung (Container-Bound) dari menu Ekstensi > Apps Script di Google Sheet
  var active = SpreadsheetApp.getActiveSpreadsheet();
  if (active) {
    return active;
  }

  // Fallback: Jika skrip standalone dan SPREADSHEET_ID belum diisi secara terpisah, gunakan SPREADSHEET_SISWA_ID
  if (SPREADSHEET_SISWA_ID && SPREADSHEET_SISWA_ID.trim() !== '') {
    try {
      return SpreadsheetApp.openById(SPREADSHEET_SISWA_ID.trim());
    } catch (e) {
      // Abaikan dan lanjut ke throw error
    }
  }

  throw new Error('ID Spreadsheet belum diisi! Silakan buka file code.gs dan isi variabel SPREADSHEET_ID atau SPREADSHEET_SISWA_ID.');
}

/**
 * Fungsi uji coba koneksi & otorisasi izin akses (Spreadsheet & Internet/Fonnte API)
 * Jalankan fungsi ini 1x di Apps Script Editor jika muncul pesan perizinan ("You do not have permission")
 */
function testKoneksi() {
  Logger.log('[TEST] Memulai uji coba koneksi...');
  var ss = getSpreadsheet();
  Logger.log('[TEST SUKSES] Berhasil terhubung ke Spreadsheet: "' + ss.getName() + '" (ID: ' + ss.getId() + ')');
  
  // Pemicu izin UrlFetchApp
  try {
    var checkNet = UrlFetchApp.fetch('https://api.fonnte.com', { muteHttpExceptions: true });
    Logger.log('[TEST NET] Izin UrlFetchApp aktif (Status HTTP: ' + checkNet.getResponseCode() + ')');
  } catch (errNet) {
    Logger.log('[TEST NET CATATAN] ' + errNet.message);
  }

  return 'Koneksi Berhasil: ' + ss.getName();
}

/**
 * Jalankan fungsi ini 1x dari Apps Script Editor untuk memunculkan pop-up izin otorisasi
 */
function otorisasiIzinAkses() {
  var ss = getSpreadsheet();
  var res = UrlFetchApp.fetch('https://api.fonnte.com', { muteHttpExceptions: true });
  Logger.log('Otorisasi berhasil diverifikasi! Kode status: ' + res.getResponseCode());
  return 'Izin akses Spreadsheet dan Internet (Fonnte) telah berhasil diotorisasi!';
}

/**
 * Entry point Web App
 */
function doGet(e) {
  var template = HtmlService.createTemplateFromFile('index');
  return template.evaluate()
    .setTitle('Sistem Informasi SPP Santri')
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL)
    .addMetaTag('viewport', 'width=device-width, initial-scale=1')
    .setFaviconUrl('https://cdn-icons-png.flaticon.com/512/2942/2942821.png');
}

// ============================================================================
// INISIALISASI & STRUKTUR DATABASE SPREADSHEET
// ============================================================================

/**
 * Nama sheet dan header standar yang wajib ada sesuai spesifikasi
 */
var DB_SCHEMA = {
  Santri: ['ID_Santri', 'NIS', 'Nama', 'Jenis_Kelamin', 'ID_Kelas', 'Tanggal_Masuk', 'Status', 'No_HP'],
  Kelas: ['ID_Kelas', 'Nama_Kelas', 'Nominal_SPP', 'Nama_Wali_Kelas', 'No_WA_Wali_Kelas', 'Status'],
  Tagihan: ['ID_Tagihan', 'ID_Santri', 'Bulan', 'Tahun', 'Nominal_Tagihan', 'Status'],
  Pembayaran: ['ID_Pembayaran', 'ID_Tagihan', 'Tanggal', 'Nominal_Bayar', 'Metode', 'Petugas', 'Keterangan'],
  Pengaturan: ['Nama_Pengaturan', 'Nilai']
};

/**
 * Menginisialisasi sheet dan header jika belum ada tanpa merusak data lama
 */
function initDatabase() {
  try {
    Logger.log('[INIT] Memulai inisialisasi database...');
    var ss = getSpreadsheet();
    Logger.log('[INIT] Berhasil membuka Spreadsheet: "' + ss.getName() + '"');

    var headerBgColor = '#065f46'; // Emerald 800
    var headerFontColor = '#ffffff';

    for (var sheetName in DB_SCHEMA) {
      var sheet = ss.getSheetByName(sheetName);
      var headers = DB_SCHEMA[sheetName];

      if (!sheet) {
        sheet = ss.insertSheet(sheetName);
        Logger.log('[INIT] Membuat sheet baru: ' + sheetName);
      } else {
        Logger.log('[INIT] Sheet sudah ada: ' + sheetName);
      }

      // Jika sheet masih kosong sama sekali (baris = 0)
      if (sheet.getLastRow() === 0) {
        sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
        var headerRange = sheet.getRange(1, 1, 1, headers.length);
        headerRange.setBackground(headerBgColor)
          .setFontColor(headerFontColor)
          .setFontWeight('bold')
          .setHorizontalAlignment('center');
        try { sheet.setFrozenRows(1); } catch (e) { }
      }
    }

    // Inisialisasi data default pada Pengaturan jika masih kosong
    var sheetPengaturan = ss.getSheetByName('Pengaturan');
    if (sheetPengaturan && sheetPengaturan.getLastRow() <= 1) {
      var defaultSettings = [
        ['Nama_Lembaga', 'TPQ Nurul Mun\'im'],
        ['Alamat_Lembaga', 'Dusun Tanjung Lor RT. 08 RW .04 Desa Karang Anyar Paiton Probolinggo'],
        ['Telepon_Lembaga', '085119920988'],
        ['Nama_Bendahara', 'Ustadzah Ummi Kultsum'],
        ['Tahun_Ajaran_Aktif', '2026/2027'],
        ['Format_Kwitansi', 'KW/{TAHUN}/{BULAN}/{ID}'],
        ['Fonnte_Token', ''],
        ['Tanggal_Jatuh_Tempo', '15'],
      ];
      sheetPengaturan.getRange(2, 1, defaultSettings.length, 2).setValues(defaultSettings);
      Logger.log('[INIT] Mengisi default pengaturan lembaga.');
    }

    // Inisialisasi data contoh Kelas jika masih kosong
    var sheetKelas = ss.getSheetByName('Kelas');
    if (sheetKelas && sheetKelas.getLastRow() <= 1) {
      var defaultKelas = [
        ['KLS-01', 'Jilid 1', 50000, 'Ust. Ahmad Al-Hafidz', '081234567890', 'Aktif'],
        ['KLS-02', 'Jilid 2', 50000, 'Usth. Siti Khadijah', '081234567891', 'Aktif'],
        ['KLS-03', 'Jilid 3', 60000, 'Ust. Zaid Ibrahim', '081234567892', 'Aktif'],
        ['KLS-04', 'Jilid 4', 60000, 'Ust. Bilal Manshur', '081234567893', 'Aktif'],
        ['KLS-05', 'Jilid 5', 75000, 'Ust. Salman Al-Farisi', '081234567894', 'Aktif'],
        ['KLS-06', 'Jilid 6 & Al-Qur\'an', 75000, 'Ust. Hamzah Asadullah', '081234567895', 'Aktif']
      ];
      sheetKelas.getRange(2, 1, defaultKelas.length, 6).setValues(defaultKelas);
      Logger.log('[INIT] Mengisi default daftar kelas beserta Wali Jilid.');
    }

    // Inisialisasi data Santri:
    // Jika SPREADSHEET_SISWA_ID aktif, tidak perlu isi dummy santri karena data diambil langsung secara realtime.
    var sheetSantri = ss.getSheetByName('Santri');
    if (sheetSantri && sheetSantri.getLastRow() <= 1 && (!SPREADSHEET_SISWA_ID || SPREADSHEET_SISWA_ID.trim() === '')) {
      var defaultSantri = [
        ['STR-001', '1001', 'Muhammad Fatih', 'Laki-laki', 'KLS-01', '2026-01-10', 'Aktif'],
        ['STR-002', '1002', 'Aisyah Humaira', 'Perempuan', 'KLS-01', '2026-01-12', 'Aktif']
      ];
      sheetSantri.getRange(2, 1, defaultSantri.length, 7).setValues(defaultSantri);
    }

    // Terapkan perubahan ke spreadsheet secara aman
    SpreadsheetApp.flush();

    Logger.log('[INIT] Inisialisasi database BERHASIL!');
    return {
      success: true,
      message: 'Inisialisasi database berhasil!',
      spreadsheetTitle: ss.getName()
    };
  } catch (error) {
    Logger.log('[ERROR INIT] ' + error.message);
    return { success: false, message: 'Gagal inisialisasi database: ' + error.message };
  }
}

/**
 * Tes koneksi dan status spreadsheet
 */
function checkConnection() {
  try {
    var ss = getSpreadsheet();
    var sheets = ss.getSheets().map(function (s) { return s.getName(); });
    var missing = [];
    for (var req in DB_SCHEMA) {
      if (sheets.indexOf(req) === -1) missing.push(req);
    }
    return {
      success: true,
      spreadsheetName: ss.getName(),
      spreadsheetId: ss.getId(),
      sheets: sheets,
      missingSheets: missing,
      isReady: missing.length === 0
    };
  } catch (e) {
    return {
      success: false,
      message: e.message
    };
  }
}

// ============================================================================
// HELPER INTERNAL DATABASE & PEMBACA LANGSUNG REALTIME
// ============================================================================

var _liveSantriCache = null;

/**
 * Mengambil data santri LANGSUNG SECARA REALTIME dari Spreadsheet Siswa (Absensi)
 * Tanpa perlu klik tombol apa pun, web SPP langsung menampilkan data siswa & jilid sesuai spreadsheet tersebut!
 */
function getSantriFromExternalSpreadsheet() {
  if (_liveSantriCache !== null) {
    return _liveSantriCache;
  }

  var ssSiswaId = (typeof SPREADSHEET_SISWA_ID !== 'undefined' && SPREADSHEET_SISWA_ID)
    ? SPREADSHEET_SISWA_ID.trim()
    : '1mVn1iXEGDYQoQ1Ft-_nHgC7WOGJeuAg--Bu-cdoiTNk';
  var sheetSiswaName = (typeof SHEET_SISWA_NAME !== 'undefined' && SHEET_SISWA_NAME)
    ? SHEET_SISWA_NAME.trim()
    : 'siswa';

  // 1. Ambil data Kelas dari database SPP untuk mencocokkan Jilid -> ID_Kelas & Tarif SPP
  var ssSPP = getSpreadsheet();
  var sheetKelas = ssSPP.getSheetByName('Kelas');
  var kelasList = [];
  if (sheetKelas) {
    ensureKelasSheetMigrated(sheetKelas);
  }
  if (sheetKelas && sheetKelas.getLastRow() > 1) {
    var kValues = sheetKelas.getRange(2, 1, sheetKelas.getLastRow() - 1, 6).getValues();
    kelasList = kValues.map(function(r, idx) {
      return {
        _rowIndex: idx + 2,
        ID_Kelas: String(r[0] || '').trim(),
        Nama_Kelas: String(r[1] || '').trim(),
        Nominal_SPP: Number(r[2]) || 0,
        Nama_Wali_Kelas: String(r[3] || '').trim(),
        No_WA_Wali_Kelas: String(r[4] || '').trim(),
        Status: (String(r[5] || '').toLowerCase() === 'nonaktif') ? 'Nonaktif' : 'Aktif'
      };
    });
  }

  var kelasMap = {};
  kelasList.forEach(function (k) {
    var norm = String(k.Nama_Kelas || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    kelasMap[norm] = k.ID_Kelas;
  });

  function resolveKelasId(rawJilid) {
    if (!rawJilid) rawJilid = 'Jilid 1';
    var clean = String(rawJilid).trim();
    var norm = clean.toLowerCase().replace(/[^a-z0-9]/g, '');
    if (kelasMap[norm]) return kelasMap[norm];

    // Cek kecocokan parsial (misal "jilid 1" ada di "jilid 1 a")
    for (var kNorm in kelasMap) {
      if (norm.indexOf(kNorm) !== -1 || kNorm.indexOf(norm) !== -1) {
        return kelasMap[kNorm];
      }
    }

    // Jika tingkatan jilid belum ada di tabel kelas SPP, auto daftarkan ke sheet Kelas (6 kolom)
    var nextNum = kelasList.length + 1;
    var newId = 'KLS-' + Utilities.formatString('%02d', nextNum);
    var defNominal = 50000;
    if (sheetKelas) {
      sheetKelas.appendRow([newId, clean, defNominal, '', '', 'Aktif']);
    }
    kelasMap[norm] = newId;
    kelasList.push({ ID_Kelas: newId, Nama_Kelas: clean, Nominal_SPP: defNominal, Nama_Wali_Kelas: '', No_WA_Wali_Kelas: '', Status: 'Aktif' });
    return newId;
  }

  // 2. Ambil data siswa (UTAMAKAN BACA LANGSUNG DARI SHEET LOKAL YANG SUDAH DI-IMPORTRANGE!)
  var sheetSiswa = null;

  // Cek apakah sheet Santri lokal berisi data IMPORTRANGE
  var localSantriSheet = ssSPP.getSheetByName('Santri');
  if (localSantriSheet && localSantriSheet.getLastRow() > 1) {
    var checkHeaders = localSantriSheet.getRange(1, 1, 1, Math.min(localSantriSheet.getLastColumn(), 10)).getValues()[0];
    var hStr = checkHeaders.join(' ').toLowerCase();
    if (hStr.indexOf('nama') !== -1 && (hStr.indexOf('jilid') !== -1 || hStr.indexOf('kelas') !== -1 || hStr.indexOf('nis') !== -1)) {
      sheetSiswa = localSantriSheet;
      Logger.log('[LIVE SANTRI] Membaca data santri langsung dari sheet lokal "Santri" (IMPORTRANGE).');
    }
  }

  // Jika di sheet Santri belum ada, periksa sheet lokal alternatif (siswa, siswa_absensi, siswa_import)
  if (!sheetSiswa) {
    var altNames = ['siswa', 'siswa_absensi', 'siswa_import', 'Sheet1'];
    for (var a = 0; a < altNames.length; a++) {
      var sAlt = ssSPP.getSheetByName(altNames[a]);
      if (sAlt && sAlt.getLastRow() > 1) {
        var hAlt = sAlt.getRange(1, 1, 1, Math.min(sAlt.getLastColumn(), 10)).getValues()[0].join(' ').toLowerCase();
        if (hAlt.indexOf('nama') !== -1) {
          sheetSiswa = sAlt;
          Logger.log('[LIVE SANTRI] Membaca data santri dari sheet lokal "' + altNames[a] + '".');
          break;
        }
      }
    }
  }

  // Fallback: Jika belum ada sheet lokal yang berisi data impor, buka spreadsheet eksternal
  if (!sheetSiswa && typeof SPREADSHEET_SISWA_ID !== 'undefined' && SPREADSHEET_SISWA_ID && SPREADSHEET_SISWA_ID.trim() !== '') {
    try {
      var ssSiswa = SpreadsheetApp.openById(SPREADSHEET_SISWA_ID.trim());
      var sName = (typeof SHEET_SISWA_NAME !== 'undefined' && SHEET_SISWA_NAME) ? SHEET_SISWA_NAME.trim() : 'siswa';
      sheetSiswa = ssSiswa.getSheetByName(sName);
      if (!sheetSiswa) {
        var all = ssSiswa.getSheets();
        for (var i = 0; i < all.length; i++) {
          if (all[i].getName().toLowerCase() === sName.toLowerCase()) {
            sheetSiswa = all[i];
            break;
          }
        }
      }
    } catch (errExt) {
      Logger.log('[WARN BUKA EXTERNAL] ' + errExt.message);
    }
  }

  if (!sheetSiswa) {
    throw new Error('Data santri tidak ditemukan di sheet lokal maupun spreadsheet eksternal.');
  }

  var dataSiswa = sheetSiswa.getDataRange().getValues();
  if (dataSiswa.length <= 1) {
    _liveSantriCache = [];
    return [];
  }

  // Deteksi indeks kolom: Nama, NISN/NIS, Jenis Kelamin, Kelas/Jilid, No HP
  var header = dataSiswa[0].map(function (h) { return String(h || '').toLowerCase().trim(); });
  var colNama = -1, colNis = -1, colJk = -1, colKelas = -1, colTgl = -1, colHp = -1;

  for (var c = 0; c < header.length; c++) {
    var h = header[c];
    if (colNama === -1 && h.indexOf('nama') !== -1 && h.indexOf('ayah') === -1 && h.indexOf('ibu') === -1) colNama = c;
    if (colNis === -1 && h.indexOf('nis') !== -1) colNis = c;
    if (colJk === -1 && (h.indexOf('kelamin') !== -1 || h === 'jk' || h === 'gender')) colJk = c;
    if (colKelas === -1 && (h.indexOf('kelas') !== -1 || h.indexOf('jilid') !== -1)) colKelas = c;
    if (colTgl === -1 && (h.indexOf('tanggal') !== -1 || h.indexOf('tgl') !== -1 || h.indexOf('lahir') !== -1)) colTgl = c;
    if (colHp === -1 && (h.indexOf('hp') !== -1 || h.indexOf('telepon') !== -1 || h.indexOf('telp') !== -1 || h.indexOf('wa') !== -1 || h.indexOf('kontak') !== -1)) colHp = c;
  }

  // Fallback standar sesuai struktur tabel siswa absensi (Kolom 0: Nama, 1: NISN, 2: JK, 7: No HP, 8: Jilid)
  if (colNama === -1) colNama = 0;
  if (colNis === -1) colNis = 1;
  if (colJk === -1) colJk = 2;
  if (colHp === -1) colHp = (dataSiswa[0].length > 7) ? 7 : -1;
  if (colKelas === -1) colKelas = (dataSiswa[0].length > 8) ? 8 : -1;

  var result = [];
  var todayStr = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');

  for (var r = 1; r < dataSiswa.length; r++) {
    var row = dataSiswa[r];
    var nama = String(row[colNama] || '').trim();
    var nis = String(row[colNis] || '').trim();
    var jk = (colJk !== -1 && row[colJk]) ? String(row[colJk]).trim() : 'Laki-laki';
    var jilid = (colKelas !== -1 && row[colKelas]) ? String(row[colKelas]).trim() : 'Jilid 1';
    var rawHp = (colHp !== -1 && row[colHp]) ? String(row[colHp]).trim() : '';

    if (!nama && !nis) continue;
    if (!nis) nis = 'STR' + Utilities.formatString('%04d', r);

    var jkFormatted = 'Laki-laki';
    var jkLower = jk.toLowerCase();
    if (jkLower === 'p' || jkLower.indexOf('perempuan') !== -1 || jkLower.indexOf('wanita') !== -1) {
      jkFormatted = 'Perempuan';
    }

    var idKls = resolveKelasId(jilid);
    var idSantri = 'STR-' + nis;

    var tglMasuk = todayStr;
    if (colTgl !== -1 && row[colTgl]) {
      var tVal = row[colTgl];
      if (tVal instanceof Date) {
        tglMasuk = Utilities.formatDate(tVal, 'Asia/Jakarta', 'yyyy-MM-dd');
      } else {
        tglMasuk = String(tVal);
      }
    }

    var cleanHp = normalizePhoneForWa(rawHp);

    result.push({
      _rowIndex: r + 1,
      ID_Santri: idSantri,
      NIS: nis,
      Nama: nama,
      Jenis_Kelamin: jkFormatted,
      ID_Kelas: idKls,
      Tanggal_Masuk: tglMasuk,
      No_HP: cleanHp,
      Status: 'Aktif'
    });
  }

  _liveSantriCache = result;
  return result;
}

function getSheetData(sheetName) {
  // Jika meminta data Santri, BACA LANGSUNG SECARA REALTIME!
  // (Membaca langsung dari sheet Santri lokal hasil IMPORTRANGE atau fallback eksternal)
  if (sheetName === 'Santri') {
    try {
      return getSantriFromExternalSpreadsheet();
    } catch (eLive) {
      Logger.log('[WARN LIVE SANTRI] ' + eLive.message + '. Mencoba sheet lokal...');
    }
  }

  var ss = getSpreadsheet();
  var sheet = ss.getSheetByName(sheetName);
  if (!sheet) {
    throw new Error('Sheet "' + sheetName + '" tidak ditemukan. Silakan jalankan inisialisasi database.');
  }

  var lastRow = sheet.getLastRow();
  var lastCol = sheet.getLastColumn();

  if (lastRow <= 1 || lastCol === 0) {
    return [];
  }

  var values = sheet.getRange(1, 1, lastRow, lastCol).getValues();
  var headers = values[0];
  var rows = [];

  for (var i = 1; i < values.length; i++) {
    var row = values[i];
    // Abaikan baris kosong
    var isBlank = row.every(function (v) { return v === '' || v === null; });
    if (isBlank) continue;

    var obj = { _rowIndex: i + 1 };
    for (var j = 0; j < headers.length; j++) {
      var headerKey = headers[j];
      var cellVal = row[j];
      if (cellVal instanceof Date) {
        cellVal = Utilities.formatDate(cellVal, 'Asia/Jakarta', 'yyyy-MM-dd');
      }
      obj[headerKey] = cellVal;
    }
    rows.push(obj);
  }
  return rows;
}

var NAMA_BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

function normalizeBulan(b) {
  if (typeof b === 'number') {
    return NAMA_BULAN[b - 1] || 'Januari';
  }
  var str = String(b || '').trim();
  var num = parseInt(str, 10);
  if (!isNaN(num) && num >= 1 && num <= 12) {
    return NAMA_BULAN[num - 1];
  }
  return str;
}

// ============================================================================
// MODUL PENGATURAN
// ============================================================================

function getPengaturan() {
  try {
    var rows = getSheetData('Pengaturan');
    var settings = {};
    rows.forEach(function (r) {
      if (r.Nama_Pengaturan) {
        settings[r.Nama_Pengaturan] = r.Nilai;
      }
    });
    return { success: true, data: settings };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

function simpanPengaturan(dataSettings) {
  try {
    var ss = getSpreadsheet();
    var sheet = ss.getSheetByName('Pengaturan');
    if (!sheet) throw new Error('Sheet Pengaturan tidak ditemukan.');

    var existingRows = getSheetData('Pengaturan');
    var mapRowIndex = {};
    existingRows.forEach(function (r) {
      mapRowIndex[r.Nama_Pengaturan] = r._rowIndex;
    });

    for (var key in dataSettings) {
      var val = dataSettings[key];
      if (mapRowIndex[key]) {
        sheet.getRange(mapRowIndex[key], 2).setValue(val);
      } else {
        sheet.appendRow([key, val]);
      }
    }

    return { success: true, message: 'Pengaturan berhasil disimpan!' };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

// ============================================================================
// MODUL KELAS (CRUD)
// ============================================================================

/**
 * Memastikan sheet Kelas memiliki struktur 6 kolom resmi:
 * [ID_Kelas, Nama_Kelas, Nominal_SPP, Nama_Wali_Kelas, No_WA_Wali_Kelas, Status]
 * Serta secara otomatis menata kembali data jika masih berada dalam skema lama 4 kolom
 * atau jika data bergeser karena pembaruan sebelumnya tanpa merusak input nama ustadzah & nomor WA.
 */
function ensureKelasSheetMigrated(sheet) {
  if (!sheet) return;
  var lastRow = sheet.getLastRow();
  if (lastRow === 0) return;

  var lastCol = Math.max(sheet.getLastColumn(), 6);
  var range = sheet.getRange(1, 1, lastRow, lastCol);
  var values = range.getValues();

  var headers = values[0];
  var expectedHeaders = ['ID_Kelas', 'Nama_Kelas', 'Nominal_SPP', 'Nama_Wali_Kelas', 'No_WA_Wali_Kelas', 'Status'];

  var needsMigration = false;

  // Cek apakah header baris 1 belum sesuai
  if (headers.length < 6 ||
      headers[3] !== 'Nama_Wali_Kelas' ||
      headers[4] !== 'No_WA_Wali_Kelas' ||
      headers[5] !== 'Status') {
    needsMigration = true;
  }

  // Cek juga apakah ada baris data di mana kolom D berisi 'Aktif' / 'Nonaktif' (skema 4 kolom lama)
  for (var r = 1; r < values.length; r++) {
    var colD = String(values[r][3] || '').trim().toLowerCase();
    var colF = String(values[r][5] || '').trim();
    if ((colD === 'aktif' || colD === 'nonaktif') && !colF) {
      needsMigration = true;
      break;
    }
  }

  if (!needsMigration) return;

  Logger.log('[MIGRASI KELAS] Menata dan menstandarisasi struktur 6 kolom sheet Kelas...');

  var newValues = [];
  newValues.push(expectedHeaders);

  for (var i = 1; i < values.length; i++) {
    var row = values[i];
    var idKelas = String(row[0] || '').trim();
    var namaKelas = String(row[1] || '').trim();
    var nominalSpp = Number(row[2]) || 0;

    if (!idKelas && !namaKelas) continue;

    var col3 = String(row[3] || '').trim();
    var col4 = String(row[4] || '').trim();
    var col5 = String(row[5] || '').trim();

    var namaWali = '';
    var noWaWali = '';
    var status = 'Aktif';

    var col3Lower = col3.toLowerCase();
    if ((col3Lower === 'aktif' || col3Lower === 'nonaktif') && !col5) {
      // Skema 4 kolom lama: ID, Nama, Nominal, Status
      namaWali = '';
      noWaWali = '';
      status = (col3Lower === 'nonaktif') ? 'Nonaktif' : 'Aktif';
    } else {
      // Skema 6 kolom (atau baris yang sudah pernah diinput nama wali & nomor WA)
      namaWali = col3;
      noWaWali = col4;
      if (col5) {
        status = (col5.toLowerCase() === 'nonaktif') ? 'Nonaktif' : 'Aktif';
      } else {
        status = 'Aktif';
      }
    }

    newValues.push([idKelas, namaKelas, nominalSpp, namaWali, noWaWali, status]);
  }

  // Tuliskan kembali ke sheet
  sheet.clearContents();
  var outRange = sheet.getRange(1, 1, newValues.length, 6);
  outRange.setValues(newValues);

  // Format header
  var headerRange = sheet.getRange(1, 1, 1, 6);
  headerRange.setBackground('#065f46')
    .setFontColor('#ffffff')
    .setFontWeight('bold')
    .setHorizontalAlignment('center');

  try { sheet.setFrozenRows(1); } catch (e) { }
  SpreadsheetApp.flush();
  Logger.log('[MIGRASI KELAS SUKSES] Sheet Kelas berhasil distandarisasi ke 6 kolom.');
}

function getKelasList() {
  try {
    var ss = getSpreadsheet();
    var sheet = ss.getSheetByName('Kelas');
    if (sheet) {
      ensureKelasSheetMigrated(sheet);
    }

    var kelasList = getSheetData('Kelas');
    var santriList = getSheetData('Santri');

    // Hitung santri per kelas
    var countMap = {};
    santriList.forEach(function (s) {
      if (String(s.Status).toLowerCase() === 'aktif') {
        countMap[s.ID_Kelas] = (countMap[s.ID_Kelas] || 0) + 1;
      }
    });

    kelasList.forEach(function (k) {
      k.Nominal_SPP = Number(k.Nominal_SPP) || 0;
      k.Nama_Wali_Kelas = k.Nama_Wali_Kelas || '';
      k.No_WA_Wali_Kelas = k.No_WA_Wali_Kelas || '';
      var rawStatus = String(k.Status || '').trim().toLowerCase();
      k.Status = (rawStatus === 'nonaktif') ? 'Nonaktif' : 'Aktif';
      k.Jumlah_Santri = countMap[k.ID_Kelas] || 0;
    });

    return { success: true, data: kelasList };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

function simpanKelas(kelasData) {
  try {
    var ss = getSpreadsheet();
    var sheet = ss.getSheetByName('Kelas');
    if (!sheet) throw new Error('Sheet Kelas tidak ditemukan.');

    ensureKelasSheetMigrated(sheet);

    if (!kelasData.Nama_Kelas || kelasData.Nama_Kelas.trim() === '') {
      throw new Error('Nama kelas wajib diisi.');
    }

    var nominal = Number(kelasData.Nominal_SPP);
    if (isNaN(nominal) || nominal < 0) {
      throw new Error('Nominal SPP harus berupa angka yang valid dan tidak boleh negatif.');
    }

    var namaWali = (kelasData.Nama_Wali_Kelas || '').trim();
    var noWaWali = normalizePhoneForWa(kelasData.No_WA_Wali_Kelas || '');
    var status = (String(kelasData.Status || '').toLowerCase() === 'nonaktif') ? 'Nonaktif' : 'Aktif';

    var isEdit = Boolean(kelasData.ID_Kelas);
    var existingKelas = getSheetData('Kelas');

    if (isEdit) {
      // Update
      var foundRow = existingKelas.find(function (k) { return k.ID_Kelas === kelasData.ID_Kelas; });
      if (!foundRow) throw new Error('Data kelas dengan ID ' + kelasData.ID_Kelas + ' tidak ditemukan.');

      sheet.getRange(foundRow._rowIndex, 2, 1, 5).setValues([[
        kelasData.Nama_Kelas.trim(),
        nominal,
        namaWali,
        noWaWali,
        status
      ]]);

      return { success: true, message: 'Data kelas & Wali Jilid berhasil diperbarui!', id: kelasData.ID_Kelas };
    } else {
      // Tambah baru
      var nextId = 'KLS-' + Utilities.formatString('%02d', existingKelas.length + 1);
      var idExists = existingKelas.some(function (k) { return k.ID_Kelas === nextId; });
      if (idExists) {
        nextId = 'KLS-' + Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyMMddHHmmss');
      }

      sheet.appendRow([
        nextId,
        kelasData.Nama_Kelas.trim(),
        nominal,
        namaWali,
        noWaWali,
        status
      ]);

      return { success: true, message: 'Kelas baru berhasil ditambahkan!', id: nextId };
    }
  } catch (e) {
    return { success: false, message: e.message };
  }
}

function hapusKelas(idKelas) {
  try {
    var ss = getSpreadsheet();
    var sheet = ss.getSheetByName('Kelas');
    if (!sheet) throw new Error('Sheet Kelas tidak ditemukan.');

    ensureKelasSheetMigrated(sheet);

    // Cek apakah ada santri di kelas ini
    var santriList = getSheetData('Santri');
    var adaSantri = santriList.some(function (s) {
      return s.ID_Kelas === idKelas && String(s.Status).toLowerCase() === 'aktif';
    });
    if (adaSantri) {
      throw new Error('Kelas tidak dapat dihapus karena masih memiliki santri aktif. Nonaktifkan santri terlebih dahulu atau ubah status kelas.');
    }

    var existingKelas = getSheetData('Kelas');
    var foundRow = existingKelas.find(function (k) { return k.ID_Kelas === idKelas; });
    if (!foundRow) throw new Error('Data kelas tidak ditemukan.');

    // Soft delete: ubah status ke Nonaktif (Kolom 6)
    sheet.getRange(foundRow._rowIndex, 6).setValue('Nonaktif');

    return { success: true, message: 'Kelas berhasil dinonaktifkan.' };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

// ============================================================================
// MODUL SANTRI (CRUD & SINKRONISASI DARI SPREADSHEET SISWA)
// ============================================================================

/**
 * Sinkronisasi data santri langsung dari Spreadsheet Siswa (Absensi)
 * Menarik Nama, NISN/NIS, Jenis Kelamin, dan Jilid/Kelas secara otomatis
 * Tanpa perlu input data siswa secara manual di website SPP
 */
function syncSantriDariSpreadsheetLain() {
  try {
    var ssSiswaId = (typeof SPREADSHEET_SISWA_ID !== 'undefined' && SPREADSHEET_SISWA_ID) 
      ? SPREADSHEET_SISWA_ID.trim() 
      : '1mVn1iXEGDYQoQ1Ft-_nHgC7WOGJeuAg--Bu-cdoiTNk';
    
    var sheetSiswaName = (typeof SHEET_SISWA_NAME !== 'undefined' && SHEET_SISWA_NAME)
      ? SHEET_SISWA_NAME.trim()
      : 'siswa';

    Logger.log('[SYNC] Membuka Spreadsheet Siswa ID: ' + ssSiswaId);
    var ssSiswa;
    try {
      ssSiswa = SpreadsheetApp.openById(ssSiswaId);
    } catch (errOpen) {
      throw new Error('Gagal membuka Spreadsheet Siswa (ID: ' + ssSiswaId + '). Pastikan akun memiliki izin akses edit/view: ' + errOpen.message);
    }

    var sheetSiswa = ssSiswa.getSheetByName(sheetSiswaName);
    if (!sheetSiswa) {
      var allSheets = ssSiswa.getSheets();
      for (var sIdx = 0; sIdx < allSheets.length; sIdx++) {
        if (allSheets[sIdx].getName().toLowerCase() === sheetSiswaName.toLowerCase()) {
          sheetSiswa = allSheets[sIdx];
          break;
        }
      }
    }

    if (!sheetSiswa) {
      throw new Error('Sheet "' + sheetSiswaName + '" tidak ditemukan di Spreadsheet data siswa (ID: ' + ssSiswaId + ').');
    }

    var dataSiswa = sheetSiswa.getDataRange().getValues();
    if (dataSiswa.length <= 1) {
      return { success: false, message: 'Sheet data siswa masih kosong (tidak ada baris data).' };
    }

    // Deteksi index kolom dari header sheet siswa
    var headerRow = dataSiswa[0].map(function (h) { return String(h || '').toLowerCase().trim(); });
    var colNama = -1;
    var colNis = -1;
    var colJk = -1;
    var colKelas = -1;

    for (var c = 0; c < headerRow.length; c++) {
      var h = headerRow[c];
      if (colNama === -1 && (h === 'nama' || h.indexOf('nama') !== -1) && h.indexOf('ayah') === -1 && h.indexOf('ibu') === -1) {
        colNama = c;
      }
      if (colNis === -1 && (h === 'nis' || h === 'nisn' || h.indexOf('nis') !== -1)) {
        colNis = c;
      }
      if (colJk === -1 && (h.indexOf('kelamin') !== -1 || h === 'jk' || h === 'gender')) {
        colJk = c;
      }
      if (colKelas === -1 && (h === 'kelas' || h.indexOf('kelas') !== -1 || h.indexOf('jilid') !== -1)) {
        colKelas = c;
      }
    }

    // Fallback default index jika tidak terdeteksi dari header:
    // Sesuai susunan sheet siswa absensi: Kolom 0 = Nama, Kolom 1 = NISN, Kolom 2 = JK, Kolom 8 = Kelas/Jilid
    if (colNama === -1) colNama = 0;
    if (colNis === -1) colNis = 1;
    if (colJk === -1) colJk = 2;
    if (colKelas === -1) colKelas = (dataSiswa[0].length > 8) ? 8 : -1;

    // Siapkan database SPP lokal
    var ssSPP = getSpreadsheet();
    var sheetSantri = ssSPP.getSheetByName('Santri');
    var sheetKelas = ssSPP.getSheetByName('Kelas');

    if (!sheetSantri || !sheetKelas) {
      initDatabase();
      sheetSantri = ssSPP.getSheetByName('Santri');
      sheetKelas = ssSPP.getSheetByName('Kelas');
    }

    // Ambil data Kelas yang sudah ada di SPP
    var kelasList = getSheetData('Kelas');
    var kelasMap = {};
    kelasList.forEach(function (k) {
      var normName = String(k.Nama_Kelas || '').toLowerCase().replace(/[^a-z0-9]/g, '');
      kelasMap[normName] = k.ID_Kelas;
    });

    // Helper untuk mencocokkan Jilid atau otomatis membuat Kelas jika belum ada di SPP
    function resolveIdKelas(rawJilid) {
      if (!rawJilid) rawJilid = 'Jilid 1';
      var cleanJilid = String(rawJilid).trim();
      var normJilid = cleanJilid.toLowerCase().replace(/[^a-z0-9]/g, '');

      if (kelasMap[normJilid]) {
        return kelasMap[normJilid];
      }

      // Cocokkan substring (contoh: "jilid1" cocok ke "jilid 1")
      for (var kNorm in kelasMap) {
        if (normJilid.indexOf(kNorm) !== -1 || kNorm.indexOf(normJilid) !== -1) {
          return kelasMap[kNorm];
        }
      }

      // Jika belum terdaftar sama sekali di sheet Kelas, buat kelas baru otomatis
      var nextKlsNum = kelasList.length + 1;
      var newKlsId = 'KLS-' + Utilities.formatString('%02d', nextKlsNum);
      var defaultNominal = 50000;
      sheetKelas.appendRow([newKlsId, cleanJilid, defaultNominal, '', '', 'Aktif']);
      
      kelasMap[normJilid] = newKlsId;
      kelasList.push({ ID_Kelas: newKlsId, Nama_Kelas: cleanJilid, Nominal_SPP: defaultNominal, Status: 'Aktif' });
      Logger.log('[SYNC] Menambahkan Kelas baru otomatis ke SPP: ' + cleanJilid + ' (' + newKlsId + ')');
      return newKlsId;
    }

    // Ambil data santri SPP saat ini (dicocokkan berdasarkan NIS)
    var existingSantri = getSheetData('Santri');
    var existingNisMap = {};
    existingSantri.forEach(function (s) {
      var n = String(s.NIS || '').trim();
      if (n) existingNisMap[n] = s;
    });

    var countBaru = 0;
    var countUpdate = 0;
    var countTetap = 0;
    var todayStr = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');
    var newRows = [];

    for (var i = 1; i < dataSiswa.length; i++) {
      var row = dataSiswa[i];
      var rawNama = String(row[colNama] || '').trim();
      var rawNis = String(row[colNis] || '').trim();
      var rawJk = (colJk !== -1 && row[colJk]) ? String(row[colJk]).trim() : 'Laki-laki';
      var rawJilid = (colKelas !== -1 && row[colKelas]) ? String(row[colKelas]).trim() : 'Jilid 1';

      if (!rawNama && !rawNis) continue; // Abaikan baris kosong
      if (!rawNis) {
        rawNis = 'STR' + Utilities.formatString('%04d', i);
      }

      // Standarisasi format Jenis Kelamin
      var jkFormatted = rawJk;
      var jkLower = rawJk.toLowerCase();
      if (jkLower === 'l' || jkLower.indexOf('laki') !== -1) {
        jkFormatted = 'Laki-laki';
      } else if (jkLower === 'p' || jkLower.indexOf('perempuan') !== -1 || jkLower.indexOf('wanita') !== -1) {
        jkFormatted = 'Perempuan';
      }

      var idKelasTujuan = resolveIdKelas(rawJilid);

      if (existingNisMap[rawNis]) {
        // Data sudah ada di SPP: cek apakah ada pembaruan Nama, Jilid/Kelas, atau JK
        var existingObj = existingNisMap[rawNis];

        if (existingObj.Nama !== rawNama ||
            existingObj.ID_Kelas !== idKelasTujuan ||
            existingObj.Jenis_Kelamin !== jkFormatted) {
          
          sheetSantri.getRange(existingObj._rowIndex, 3, 1, 3).setValues([[
            rawNama,
            jkFormatted,
            idKelasTujuan
          ]]);
          countUpdate++;
        } else {
          countTetap++;
        }
      } else {
        // Santri Baru: tambahkan baris baru
        var idSantriBaru = 'STR-' + rawNis;
        newRows.push([
          idSantriBaru,
          rawNis,
          rawNama,
          jkFormatted,
          idKelasTujuan,
          todayStr,
          'Aktif'
        ]);
        countBaru++;
      }
    }

    if (newRows.length > 0) {
      var startRow = sheetSantri.getLastRow() + 1;
      sheetSantri.getRange(startRow, 1, newRows.length, 7).setValues(newRows);
    }

    SpreadsheetApp.flush();

    var msg = 'Sinkronisasi santri berhasil! ';
    if (countBaru > 0) msg += countBaru + ' santri baru ditambahkan. ';
    if (countUpdate > 0) msg += countUpdate + ' santri diperbarui (naik jilid/data berubah). ';
    if (countBaru === 0 && countUpdate === 0) msg += 'Semua ' + countTetap + ' santri sudah mutakhir sesuai spreadsheet absensi.';

    Logger.log('[SYNC SELESAI] ' + msg);
    return {
      success: true,
      message: msg,
      countBaru: countBaru,
      countUpdate: countUpdate,
      countTetap: countTetap,
      totalSantri: (existingSantri.length + countBaru)
    };
  } catch (err) {
    Logger.log('[SYNC ERROR] ' + err.message);
    return {
      success: false,
      message: 'Gagal sinkronisasi data santri: ' + err.message
    };
  }
}

function getSantriList(filters) {
  try {
    var santriList = getSheetData('Santri');
    var kelasList = getSheetData('Kelas');

    var kelasMap = {};
    kelasList.forEach(function (k) {
      kelasMap[k.ID_Kelas] = {
        nama: k.Nama_Kelas,
        nominal: Number(k.Nominal_SPP) || 0
      };
    });

    var result = santriList.map(function (s) {
      var kInfo = kelasMap[s.ID_Kelas] || { nama: 'Tidak Diketahui', nominal: 0 };
      return {
        ID_Santri: s.ID_Santri,
        NIS: s.NIS ? String(s.NIS) : '',
        Nama: s.Nama || '',
        Jenis_Kelamin: s.Jenis_Kelamin || '',
        ID_Kelas: s.ID_Kelas || '',
        Nama_Kelas: kInfo.nama,
        Nominal_SPP: kInfo.nominal,
        Tanggal_Masuk: s.Tanggal_Masuk || '',
        Status: s.Status || 'Aktif',
        No_HP: s.No_HP || ''
      };
    });

    if (filters) {
      if (filters.status && filters.status !== 'Semua') {
        result = result.filter(function (s) {
          return String(s.Status).toLowerCase() === String(filters.status).toLowerCase();
        });
      }
      if (filters.idKelas && filters.idKelas !== 'Semua') {
        result = result.filter(function (s) { return s.ID_Kelas === filters.idKelas; });
      }
      if (filters.search) {
        var q = filters.search.toLowerCase().trim();
        result = result.filter(function (s) {
          return s.Nama.toLowerCase().indexOf(q) !== -1 || s.NIS.toLowerCase().indexOf(q) !== -1;
        });
      }
    }

    return { success: true, data: result };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

function simpanSantri(santriData) {
  try {
    var ss = getSpreadsheet();
    var sheet = ss.getSheetByName('Santri');
    if (!sheet) throw new Error('Sheet Santri tidak ditemukan.');

    if (!santriData.Nama || santriData.Nama.trim() === '') {
      throw new Error('Nama santri wajib diisi.');
    }
    if (!santriData.NIS || santriData.NIS.trim() === '') {
      throw new Error('NIS santri wajib diisi.');
    }
    if (!santriData.ID_Kelas) {
      throw new Error('Kelas santri wajib dipilih.');
    }

    var nisClean = String(santriData.NIS).trim();
    var noHpClean = normalizePhoneForWa(santriData.No_HP || '');
    var isEdit = Boolean(santriData.ID_Santri);
    var existingSantri = getSheetData('Santri');

    // Cek duplikasi NIS
    var nisDuplicate = existingSantri.some(function (s) {
      if (isEdit && s.ID_Santri === santriData.ID_Santri) return false;
      return String(s.NIS).trim() === nisClean;
    });
    if (nisDuplicate) {
      throw new Error('NIS "' + nisClean + '" sudah digunakan oleh santri lain.');
    }

    var tglMasuk = santriData.Tanggal_Masuk || Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');

    if (isEdit) {
      var found = existingSantri.find(function (s) { return s.ID_Santri === santriData.ID_Santri; });
      if (!found) throw new Error('Data santri tidak ditemukan.');

      sheet.getRange(found._rowIndex, 2, 1, 7).setValues([[
        nisClean,
        santriData.Nama.trim(),
        santriData.Jenis_Kelamin || 'Laki-laki',
        santriData.ID_Kelas,
        tglMasuk,
        santriData.Status || 'Aktif',
        noHpClean
      ]]);

      return { success: true, message: 'Data santri berhasil diperbarui!', id: santriData.ID_Santri };
    } else {
      var nextId = 'STR-' + Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyMMddHHmmss');
      sheet.appendRow([
        nextId,
        nisClean,
        santriData.Nama.trim(),
        santriData.Jenis_Kelamin || 'Laki-laki',
        santriData.ID_Kelas,
        tglMasuk,
        santriData.Status || 'Aktif',
        noHpClean
      ]);

      return { success: true, message: 'Santri baru berhasil ditambahkan!', id: nextId };
    }
  } catch (e) {
    return { success: false, message: e.message };
  }
}

function ubahStatusSantri(idSantri, status) {
  try {
    var ss = getSpreadsheet();
    var sheet = ss.getSheetByName('Santri');
    if (!sheet) throw new Error('Sheet Santri tidak ditemukan.');

    var existingSantri = getSheetData('Santri');
    var found = existingSantri.find(function (s) { return s.ID_Santri === idSantri; });
    if (!found) throw new Error('Data santri tidak ditemukan.');

    sheet.getRange(found._rowIndex, 7).setValue(status);
    return { success: true, message: 'Status santri berhasil diubah menjadi ' + status };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

// ============================================================================
// MODUL LOGIKA PEMBAYARAN, TAGIHAN & STATUS (CORE ACCOUNTING)
// ============================================================================

/**
 * Menghitung status berdasarkan aturan bisnis:
 * - Rp0: Belum Bayar
 * - > 0 dan < Tagihan: Cicilan
 * - == Tagihan: Sudah Bayar
 * - > Tagihan: Kelebihan Bayar
 */
function hitungStatusPembayaran(totalBayar, nominalTagihan) {
  var bayar = Number(totalBayar) || 0;
  var tagihan = Number(nominalTagihan) || 0;

  if (bayar <= 0) return 'Belum Bayar';
  if (bayar < tagihan) return 'Cicilan';
  if (bayar === tagihan) return 'Sudah Bayar';
  return 'Kelebihan Bayar';
}

/**
 * Helper untuk mengambil tahun mulai dari string Tahun_Ajaran_Aktif (contoh: "2026/2027" -> 2026)
 */
function getTahunAjaranStartYear(tahunAjaranStr, fallbackYear) {
  if (tahunAjaranStr) {
    var match = String(tahunAjaranStr).match(/(\d{4})/);
    if (match) {
      return parseInt(match[1], 10);
    }
  }
  return parseInt(fallbackYear || new Date().getFullYear(), 10);
}

/**
 * Menghasilkan daftar periode bulan yang sudah berjalan dalam Tahun Ajaran
 * mulai dari bulan Juli tahun ajaran sampai bulan cut-off / bulan sekarang
 */
function getDaftarBulanBerjalanTahunAjaran(tahunAjaranStr, cutOffBulan, cutOffTahun) {
  var now = new Date();
  var cBulan = normalizeBulan(cutOffBulan || (now.getMonth() + 1));
  var cTahun = parseInt(cutOffTahun || now.getFullYear(), 10);

  var cIdx = getBulanIndex(cBulan);
  var fallbackStart = (cIdx <= 5) ? (cTahun - 1) : cTahun; // 0..5 = Januari..Juni
  var startYear = getTahunAjaranStartYear(tahunAjaranStr, fallbackStart);

  var startTotal = startYear * 12 + 6; // Juli = index 6
  var cutOffTotal = cTahun * 12 + (cIdx === -1 ? 9 : cIdx);

  if (cutOffTotal < startTotal) {
    return [{ bulan: cBulan, tahun: cTahun }];
  }

  return generateDaftarBulan('Juli', startYear, cBulan, cTahun);
}

/**
 * Menghitung rincian dan total tunggakan untuk satu santri dalam tahun ajaran berjalan (Juli s/d Bulan Sekarang)
 */
function hitungTunggakanSantri(idSantri, nominalSPPKelas, tagihanSantriMap, pembayaranMap, bulanBerjalanList) {
  var tunggakanList = [];
  var totalNominal = 0;

  bulanBerjalanList.forEach(function (periode) {
    var key = idSantri + '_' + periode.bulan + '_' + periode.tahun;
    var tgh = tagihanSantriMap[key];
    var nomTagihan = tgh ? (Number(tgh.Nominal_Tagihan) || 0) : nominalSPPKelas;
    var totalBayar = (tgh && pembayaranMap[tgh.ID_Tagihan]) ? (Number(pembayaranMap[tgh.ID_Tagihan]) || 0) : 0;
    var sisa = Math.max(0, nomTagihan - totalBayar);

    if (sisa > 0) {
      totalNominal += sisa;
      tunggakanList.push({
        idTagihan: tgh ? tgh.ID_Tagihan : null,
        bulan: periode.bulan,
        tahun: periode.tahun,
        nominalTagihan: nomTagihan,
        totalBayar: totalBayar,
        sisaTagihan: sisa,
        status: totalBayar > 0 ? 'Cicilan' : 'Belum Bayar'
      });
    }
  });

  var daftarBulanStr = tunggakanList.map(function (it) {
    return it.bulan + ' ' + it.tahun;
  }).join(', ');

  return {
    totalBulan: tunggakanList.length,
    totalNominal: totalNominal,
    daftarBulan: daftarBulanStr,
    daftar: tunggakanList
  };
}

/**
 * Mengambil status pembayaran per periode (Bulan & Tahun) lengkap dengan filter
 */
function getStatusPembayaran(filters) {
  try {
    filters = filters || {};
    var bulan = normalizeBulan(filters.bulan || (new Date().getMonth() + 1));
    var tahun = Number(filters.tahun || new Date().getFullYear());
    var idKelasFilter = filters.idKelas || 'Semua';
    var statusFilter = filters.status || 'Semua';
    var search = (filters.search || '').toLowerCase().trim();

    var santriList = getSheetData('Santri');
    var kelasList = getSheetData('Kelas');
    var tagihanList = getSheetData('Tagihan');
    var pembayaranList = getSheetData('Pembayaran');
    var pRes = getPengaturan();
    var settings = pRes.success ? pRes.data : {};
    var tahunAjaranAktif = settings.Tahun_Ajaran_Aktif || '2026/2027';

    // Buat map kelas
    var kelasMap = {};
    kelasList.forEach(function (k) {
      kelasMap[k.ID_Kelas] = {
        nama: k.Nama_Kelas,
        nominal: Number(k.Nominal_SPP) || 0
      };
    });

    // Buat map tagihan berdasarkan ID_Santri + Bulan + Tahun
    var tagihanMap = {};
    tagihanList.forEach(function (t) {
      var tBulan = normalizeBulan(t.Bulan);
      var tTahun = Number(t.Tahun);
      var key = t.ID_Santri + '_' + tBulan + '_' + tTahun;
      tagihanMap[key] = t;
    });

    // Hitung total pembayaran sah per ID_Tagihan
    // Transaksi yang memiliki awalan [BATAL] pada keterangan dianggap tidak sah (audit)
    var pembayaranMap = {};
    pembayaranList.forEach(function (p) {
      var ket = String(p.Keterangan || '');
      if (ket.indexOf('[BATAL') === 0) return; // Lewati transaksi yang dibatalkan

      var idTagihan = p.ID_Tagihan;
      pembayaranMap[idTagihan] = (pembayaranMap[idTagihan] || 0) + (Number(p.Nominal_Bayar) || 0);
    });

    // Ambil daftar bulan berjalan dalam tahun ajaran aktif (mulai Juli s/d bulan cut-off yang sedang difilter)
    var bulanBerjalanList = getDaftarBulanBerjalanTahunAjaran(tahunAjaranAktif, bulan, tahun);

    var rows = [];
    var summary = {
      totalSantri: 0,
      totalTagihan: 0,
      totalBayar: 0,
      totalTunggakan: 0,
      sudahBayarCount: 0,
      cicilanCount: 0,
      belumBayarCount: 0,
      kelebihanBayarCount: 0
    };

    santriList.forEach(function (s) {
      var isAktif = String(s.Status).toLowerCase() === 'aktif';
      var keyTagihan = s.ID_Santri + '_' + bulan + '_' + tahun;
      var existingTagihan = tagihanMap[keyTagihan];

      // Aturan: Santri nonaktif yang tidak memiliki riwayat tagihan pada periode ini tidak dimunculkan
      if (!isAktif && !existingTagihan) {
        return;
      }

      var kelasInfo = kelasMap[s.ID_Kelas] || { nama: 'Tidak Ada Kelas', nominal: 0 };
      var nominalTagihan = existingTagihan ? (Number(existingTagihan.Nominal_Tagihan) || 0) : kelasInfo.nominal;
      var idTagihan = existingTagihan ? existingTagihan.ID_Tagihan : null;

      var totalBayar = idTagihan ? (pembayaranMap[idTagihan] || 0) : 0;
      var status = hitungStatusPembayaran(totalBayar, nominalTagihan);
      var sisaTagihan = Math.max(0, nominalTagihan - totalBayar);

      // Filter Kelas
      if (idKelasFilter !== 'Semua' && s.ID_Kelas !== idKelasFilter) return;

      // Filter Pencarian
      if (search) {
        var namaMatch = (s.Nama || '').toLowerCase().indexOf(search) !== -1;
        var nisMatch = String(s.NIS || '').toLowerCase().indexOf(search) !== -1;
        if (!namaMatch && !nisMatch) return;
      }

      // Filter Status
      if (statusFilter !== 'Semua' && status !== statusFilter) return;

      summary.totalSantri++;
      summary.totalTagihan += nominalTagihan;
      summary.totalBayar += totalBayar;
      summary.totalTunggakan += sisaTagihan;

      if (status === 'Sudah Bayar') summary.sudahBayarCount++;
      else if (status === 'Cicilan') summary.cicilanCount++;
      else if (status === 'Belum Bayar') summary.belumBayarCount++;
      else if (status === 'Kelebihan Bayar') summary.kelebihanBayarCount++;

      // Hitung tunggakan hanya untuk bulan berjalan (Juli s/d bulan yang sedang dilihat)
      var tgk = hitungTunggakanSantri(s.ID_Santri, kelasInfo.nominal, tagihanMap, pembayaranMap, bulanBerjalanList);

      rows.push({
        ID_Santri: s.ID_Santri,
        NIS: s.NIS,
        Nama: s.Nama,
        Jenis_Kelamin: s.Jenis_Kelamin,
        ID_Kelas: s.ID_Kelas,
        Nama_Kelas: kelasInfo.nama,
        Bulan: bulan,
        Tahun: tahun,
        ID_Tagihan: idTagihan,
        Nominal_Tagihan: nominalTagihan,
        Total_Bayar: totalBayar,
        Sisa_Tagihan: sisaTagihan,
        Status: status,
        Status_Santri: s.Status,
        No_HP: s.No_HP || '',
        Total_Bulan_Nunggak: tgk.totalBulan,
        Total_Semua_Tunggakan: tgk.totalNominal,
        Daftar_Bulan_Nunggak: tgk.daftarBulan
      });
    });

    return {
      success: true,
      data: rows,
      summary: summary,
      periode: { bulan: bulan, tahun: tahun }
    };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

/**
 * Generate massal tagihan untuk seluruh santri aktif pada bulan & tahun tertentu
 */
function generateTagihanBulanan(bulan, tahun) {
  try {
    bulan = normalizeBulan(bulan);
    tahun = Number(tahun);

    // Otomatis sinkronkan santri terlebih dahulu agar tagihan selalu memakai data jilid terbaru dari spreadsheet siswa
    try {
      syncSantriDariSpreadsheetLain();
    } catch (syncErr) {
      Logger.log('[WARN] Sinkronisasi sebelum generate tagihan: ' + syncErr.message);
    }

    var ss = getSpreadsheet();
    var sheetTagihan = ss.getSheetByName('Tagihan');
    if (!sheetTagihan) throw new Error('Sheet Tagihan tidak ditemukan.');

    var santriList = getSheetData('Santri');
    var kelasList = getSheetData('Kelas');
    var existingTagihan = getSheetData('Tagihan');

    var kelasMap = {};
    kelasList.forEach(function (k) {
      kelasMap[k.ID_Kelas] = Number(k.Nominal_SPP) || 0;
    });

    var tagihanSet = {};
    existingTagihan.forEach(function (t) {
      var key = t.ID_Santri + '_' + normalizeBulan(t.Bulan) + '_' + Number(t.Tahun);
      tagihanSet[key] = true;
    });

    var newRows = [];
    var countCreated = 0;

    santriList.forEach(function (s) {
      if (String(s.Status).toLowerCase() !== 'aktif') return;

      var key = s.ID_Santri + '_' + bulan + '_' + tahun;
      if (!tagihanSet[key]) {
        var nominal = kelasMap[s.ID_Kelas] || 0;
        var idTagihan = 'TGH-' + s.ID_Santri + '-' + tahun + '-' + bulan.substring(0, 3).toUpperCase();
        newRows.push([
          idTagihan,
          s.ID_Santri,
          bulan,
          tahun,
          nominal,
          'Belum Bayar'
        ]);
        tagihanSet[key] = true;
        countCreated++;
      }
    });

    if (newRows.length > 0) {
      var startRow = sheetTagihan.getLastRow() + 1;
      sheetTagihan.getRange(startRow, 1, newRows.length, 6).setValues(newRows);
      SpreadsheetApp.flush();
    }

    return {
      success: true,
      message: 'Berhasil membuat ' + countCreated + ' tagihan baru untuk periode ' + bulan + ' ' + tahun + '.',
      countCreated: countCreated
    };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

/**
 * Memastikan tagihan ada untuk seorang santri pada periode tertentu (otomatis jika belum ada)
 */
function ensureTagihanExists(idSantri, bulan, tahun) {
  var ss = getSpreadsheet();
  var sheetTagihan = ss.getSheetByName('Tagihan');
  bulan = normalizeBulan(bulan);
  tahun = Number(tahun);

  var tagihanList = getSheetData('Tagihan');
  var found = tagihanList.find(function (t) {
    return t.ID_Santri === idSantri && normalizeBulan(t.Bulan) === bulan && Number(t.Tahun) === tahun;
  });

  if (found) {
    return {
      ID_Tagihan: found.ID_Tagihan,
      Nominal_Tagihan: Number(found.Nominal_Tagihan) || 0,
      Status: found.Status
    };
  }

  // Jika belum ada, ambil data santri dan kelas untuk menentukan nominal SPP
  var santriList = getSheetData('Santri');
  var santri = santriList.find(function (s) { return s.ID_Santri === idSantri; });
  if (!santri) throw new Error('Data santri tidak ditemukan.');

  var kelasList = getSheetData('Kelas');
  var kelas = kelasList.find(function (k) { return k.ID_Kelas === santri.ID_Kelas; });
  var nominal = kelas ? (Number(kelas.Nominal_SPP) || 0) : 0;

  var idTagihan = 'TGH-' + idSantri + '-' + tahun + '-' + bulan.substring(0, 3).toUpperCase() + '-' + Utilities.formatDate(new Date(), 'Asia/Jakarta', 'mmss');

  sheetTagihan.appendRow([
    idTagihan,
    idSantri,
    bulan,
    tahun,
    nominal,
    'Belum Bayar'
  ]);

  return {
    ID_Tagihan: idTagihan,
    Nominal_Tagihan: nominal,
    Status: 'Belum Bayar'
  };
}

/**
 * Menyimpan transaksi pembayaran dengan LockService aman, validasi ketat, dan update status otomatis
 */
function simpanPembayaran(paymentData) {
  var lock = LockService.getScriptLock();
  var hasLock = false;
  try {
    try {
      hasLock = lock.tryLock(15000);
    } catch (lockErr) {
      hasLock = false;
    }

    var idSantri = paymentData.ID_Santri;
    var bulan = normalizeBulan(paymentData.Bulan);
    var tahun = Number(paymentData.Tahun);
    var nominalBayar = Number(paymentData.Nominal_Bayar);
    var tanggal = paymentData.Tanggal || Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');
    var metode = paymentData.Metode || 'Tunai';
    var petugas = paymentData.Petugas || 'Bendahara';
    var keterangan = paymentData.Keterangan || 'Pembayaran SPP';

    if (!idSantri) throw new Error('Santri wajib dipilih.');
    if (!bulan) throw new Error('Bulan tagihan wajib ditentukan.');
    if (!tahun || isNaN(tahun)) throw new Error('Tahun tagihan wajib ditentukan.');
    if (isNaN(nominalBayar) || nominalBayar <= 0) {
      throw new Error('Nominal bayar harus lebih besar dari Rp 0.');
    }

    var ss = getSpreadsheet();
    var sheetPembayaran = ss.getSheetByName('Pembayaran');
    var sheetTagihan = ss.getSheetByName('Tagihan');

    // 1. Pastikan tagihan untuk santri dan bulan tersebut ada
    var tagihanInfo = ensureTagihanExists(idSantri, bulan, tahun);
    var idTagihan = tagihanInfo.ID_Tagihan;

    // 2. Proteksi Transaksi Ganda yang tidak disengaja (duplikasi persis dalam 1 menit)
    var pembayaranList = getSheetData('Pembayaran');
    var isDuplicate = pembayaranList.some(function (p) {
      var ket = String(p.Keterangan || '');
      if (ket.indexOf('[BATAL') === 0) return false;
      return p.ID_Tagihan === idTagihan &&
        Number(p.Nominal_Bayar) === nominalBayar &&
        p.Tanggal === tanggal &&
        p.Metode === metode;
    });

    if (isDuplicate && !paymentData.allowDuplicate) {
      throw new Error('Peringatan: Transaksi identik pada tagihan dan tanggal yang sama baru saja tercatat. Jika ini bukan ketidaksengajaan, centang konfirmasi transaksi.');
    }

    // 3. Simpan baris pembayaran baru
    var idPembayaran = 'PAY-' + Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyMMddHHmmss') + '-' + Math.floor(Math.random() * 900 + 100);
    sheetPembayaran.appendRow([
      idPembayaran,
      idTagihan,
      tanggal,
      nominalBayar,
      metode,
      petugas,
      keterangan
    ]);

    // 4. Hitung ulang total pembayaran sah untuk tagihan ini dan update sheet Tagihan
    var totalBayarSemua = 0;
    var updatedPembayaranList = getSheetData('Pembayaran');
    updatedPembayaranList.forEach(function (p) {
      var ket = String(p.Keterangan || '');
      if (ket.indexOf('[BATAL') === 0) return;
      if (p.ID_Tagihan === idTagihan) {
        totalBayarSemua += (Number(p.Nominal_Bayar) || 0);
      }
    });

    var statusTerbaru = hitungStatusPembayaran(totalBayarSemua, tagihanInfo.Nominal_Tagihan);

    // Update status di sheet Tagihan
    var updatedTagihanList = getSheetData('Tagihan');
    var foundTagihanRow = updatedTagihanList.find(function (t) { return t.ID_Tagihan === idTagihan; });
    if (foundTagihanRow) {
      sheetTagihan.getRange(foundTagihanRow._rowIndex, 6).setValue(statusTerbaru);
    }

    SpreadsheetApp.flush();

    // Ambil data santri untuk keperluan nota/kuitansi
    var santriList = getSheetData('Santri');
    var santri = santriList.find(function (s) { return s.ID_Santri === idSantri; }) || {};
    var kelasList = getSheetData('Kelas');
    var kelas = kelasList.find(function (k) { return k.ID_Kelas === santri.ID_Kelas; }) || {};

    return {
      success: true,
      message: 'Pembayaran berhasil disimpan!',
      data: {
        ID_Pembayaran: idPembayaran,
        ID_Tagihan: idTagihan,
        Nama: santri.Nama || '',
        NIS: santri.NIS || '',
        Nama_Kelas: kelas.Nama_Kelas || '',
        Bulan: bulan,
        Tahun: tahun,
        Tanggal: tanggal,
        Nominal_Bayar: nominalBayar,
        Nominal_Tagihan: tagihanInfo.Nominal_Tagihan,
        Total_Terbayar: totalBayarSemua,
        Sisa_Tagihan: Math.max(0, tagihanInfo.Nominal_Tagihan - totalBayarSemua),
        Status: statusTerbaru,
        Metode: metode,
        Petugas: petugas,
        Keterangan: keterangan
      }
    };
  } catch (e) {
    return { success: false, message: e.message };
  } finally {
    if (hasLock) {
      try { lock.releaseLock(); } catch (e) { }
    }
  }
}

/**
 * Membatalkan transaksi pembayaran dengan pencatatan audit trail yang lengkap
 */
function batalkanPembayaran(idPembayaran, alasan, namaPetugas) {
  var lock = LockService.getScriptLock();
  var hasLock = false;
  try {
    try {
      hasLock = lock.tryLock(15000);
    } catch (lockErr) {
      hasLock = false;
    }

    if (!idPembayaran) throw new Error('ID Pembayaran wajib disertakan.');
    if (!alasan || alasan.trim() === '') throw new Error('Alasan pembatalan wajib diisi untuk catatan audit.');

    var ss = getSpreadsheet();
    var sheetPembayaran = ss.getSheetByName('Pembayaran');
    var sheetTagihan = ss.getSheetByName('Tagihan');

    var pembayaranList = getSheetData('Pembayaran');
    var paymentRow = pembayaranList.find(function (p) { return p.ID_Pembayaran === idPembayaran; });
    if (!paymentRow) throw new Error('Data pembayaran tidak ditemukan.');

    var currentKet = String(paymentRow.Keterangan || '');
    if (currentKet.indexOf('[BATAL') === 0) {
      throw new Error('Transaksi ini sudah dibatalkan sebelumnya.');
    }

    var timestamp = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd HH:mm:ss');
    var auditNote = '[BATAL: ' + timestamp + ' oleh ' + (namaPetugas || 'Petugas') + ' - Alasan: ' + alasan.trim() + '] ' + currentKet;

    // Update kolom keterangan di Pembayaran
    sheetPembayaran.getRange(paymentRow._rowIndex, 7).setValue(auditNote);

    // Hitung ulang status tagihan
    var idTagihan = paymentRow.ID_Tagihan;
    var tagihanList = getSheetData('Tagihan');
    var tagihanRow = tagihanList.find(function (t) { return t.ID_Tagihan === idTagihan; });

    if (tagihanRow) {
      var recomputedPembayaran = getSheetData('Pembayaran');
      var totalBayarSah = 0;
      recomputedPembayaran.forEach(function (p) {
        var ket = String(p.Keterangan || '');
        if (ket.indexOf('[BATAL') === 0) return;
        if (p.ID_Tagihan === idTagihan) {
          totalBayarSah += (Number(p.Nominal_Bayar) || 0);
        }
      });

      var statusTerbaru = hitungStatusPembayaran(totalBayarSah, tagihanRow.Nominal_Tagihan);
      sheetTagihan.getRange(tagihanRow._rowIndex, 6).setValue(statusTerbaru);
    }

    SpreadsheetApp.flush();

    return {
      success: true,
      message: 'Transaksi berhasil dibatalkan dan catatan audit telah disimpan.',
      auditNote: auditNote
    };
  } catch (e) {
    return { success: false, message: e.message };
  } finally {
    if (hasLock) {
      try { lock.releaseLock(); } catch (e) { }
    }
  }
}

// ============================================================================
// MODUL RIWAYAT TRANSAKSI & DETAIL SANTRI
// ============================================================================

function getRiwayatSantri(idSantri, cutOffBulan, cutOffTahun) {
  try {
    var santriList = getSheetData('Santri');
    var santri = santriList.find(function (s) { return s.ID_Santri === idSantri; });
    if (!santri) throw new Error('Santri tidak ditemukan.');

    var kelasList = getSheetData('Kelas');
    var kelas = kelasList.find(function (k) { return k.ID_Kelas === santri.ID_Kelas; }) || {};

    var pRes = getPengaturan();
    var settings = pRes.success ? pRes.data : {};
    var tahunAjaranAktif = settings.Tahun_Ajaran_Aktif || '2026/2027';

    var tagihanList = getSheetData('Tagihan').filter(function (t) { return t.ID_Santri === idSantri; });
    var tagihanMap = {};
    tagihanList.forEach(function (t) {
      tagihanMap[t.ID_Tagihan] = t;
    });

    var pembayaranList = getSheetData('Pembayaran');
    var riwayat = [];

    pembayaranList.forEach(function (p) {
      var tgh = tagihanMap[p.ID_Tagihan];
      if (tgh) {
        var isBatal = String(p.Keterangan || '').indexOf('[BATAL') === 0;
        riwayat.push({
          ID_Pembayaran: p.ID_Pembayaran,
          ID_Tagihan: p.ID_Tagihan,
          Bulan: tgh.Bulan,
          Tahun: tgh.Tahun,
          Tanggal: p.Tanggal,
          Nominal_Bayar: Number(p.Nominal_Bayar) || 0,
          Metode: p.Metode,
          Petugas: p.Petugas,
          Keterangan: p.Keterangan,
          Is_Batal: isBatal
        });
      }
    });

    // Urutkan riwayat pembayaran dari yang terbaru
    riwayat.sort(function (a, b) {
      return new Date(b.Tanggal) - new Date(a.Tanggal);
    });

    // Hitung seluruh pembayaran sah per ID_Tagihan
    var pembayaranTagihanMap = {};
    pembayaranList.forEach(function (p) {
      if (String(p.Keterangan || '').indexOf('[BATAL') === 0) return;
      pembayaranTagihanMap[p.ID_Tagihan] = (pembayaranTagihanMap[p.ID_Tagihan] || 0) + (Number(p.Nominal_Bayar) || 0);
    });

    // Buat map tagihan santri berdasarkan ID_Santri + Bulan + Tahun
    var tagihanSantriMap = {};
    tagihanList.forEach(function (t) {
      var key = idSantri + '_' + normalizeBulan(t.Bulan) + '_' + Number(t.Tahun);
      tagihanSantriMap[key] = t;
    });

    // Ambil daftar bulan berjalan dalam tahun ajaran aktif (mulai Juli s/d bulan sekarang)
    var bulanBerjalanList = getDaftarBulanBerjalanTahunAjaran(tahunAjaranAktif, cutOffBulan, cutOffTahun);

    // Hitung tunggakan hanya untuk bulan yang sudah berjalan di tahun ajaran aktif
    var tgk = hitungTunggakanSantri(santri.ID_Santri, Number(kelas.Nominal_SPP) || 0, tagihanSantriMap, pembayaranTagihanMap, bulanBerjalanList);

    return {
      success: true,
      santri: {
        ID_Santri: santri.ID_Santri,
        NIS: santri.NIS,
        Nama: santri.Nama,
        Jenis_Kelamin: santri.Jenis_Kelamin,
        Nama_Kelas: kelas.Nama_Kelas || 'Tidak Diketahui',
        Nominal_SPP: Number(kelas.Nominal_SPP) || 0,
        Status: santri.Status,
        No_HP: santri.No_HP || ''
      },
      tunggakan: tgk,
      riwayat: riwayat
    };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

// ============================================================================
// MODUL DASHBOARD & STATISTIK
// ============================================================================

function getDashboardData(bulan, tahun) {
  try {
    bulan = normalizeBulan(bulan || (new Date().getMonth() + 1));
    tahun = Number(tahun || new Date().getFullYear());

    var statusResult = getStatusPembayaran({ bulan: bulan, tahun: tahun });
    if (!statusResult.success) throw new Error(statusResult.message);

    var pembayaranList = getSheetData('Pembayaran');
    var totalSemuaPemasukan = 0;
    var latestTransactions = [];

    // Ambil data santri dan kelas untuk deskripsi transaksi terbaru
    var santriList = getSheetData('Santri');
    var santriMap = {};
    santriList.forEach(function (s) { santriMap[s.ID_Santri] = s; });

    var tagihanList = getSheetData('Tagihan');
    var tagihanMap = {};
    tagihanList.forEach(function (t) { tagihanMap[t.ID_Tagihan] = t; });

    pembayaranList.forEach(function (p) {
      var ket = String(p.Keterangan || '');
      if (ket.indexOf('[BATAL') === 0) return;
      var nominal = Number(p.Nominal_Bayar) || 0;
      totalSemuaPemasukan += nominal;

      var tgh = tagihanMap[p.ID_Tagihan];
      var snt = tgh ? santriMap[tgh.ID_Santri] : null;

      latestTransactions.push({
        ID_Pembayaran: p.ID_Pembayaran,
        Tanggal: p.Tanggal,
        Nama: snt ? snt.Nama : 'Santri',
        NIS: snt ? snt.NIS : '-',
        Bulan: tgh ? tgh.Bulan : '-',
        Tahun: tgh ? tgh.Tahun : '-',
        Nominal_Bayar: nominal,
        Metode: p.Metode,
        Petugas: p.Petugas
      });
    });

    // Urutkan transaksi terbaru
    latestTransactions.sort(function (a, b) {
      return new Date(b.Tanggal) - new Date(a.Tanggal);
    });
    var top5Transactions = latestTransactions.slice(0, 7);

    // Distribusi kelas
    var kelasSummary = {};
    statusResult.data.forEach(function (item) {
      var kName = item.Nama_Kelas;
      if (!kelasSummary[kName]) {
        kelasSummary[kName] = { terbayar: 0, tunggakan: 0, santriCount: 0 };
      }
      kelasSummary[kName].terbayar += item.Total_Bayar;
      kelasSummary[kName].tunggakan += item.Sisa_Tagihan;
      kelasSummary[kName].santriCount++;
    });

    var chartKelasLabels = [];
    var chartKelasTerbayar = [];
    var chartKelasTunggakan = [];

    for (var kName in kelasSummary) {
      chartKelasLabels.push(kName);
      chartKelasTerbayar.push(kelasSummary[kName].terbayar);
      chartKelasTunggakan.push(kelasSummary[kName].tunggakan);
    }

    return {
      success: true,
      summary: {
        santriAktif: statusResult.summary.totalSantri,
        sudahBayar: statusResult.summary.sudahBayarCount,
        cicilan: statusResult.summary.cicilanCount,
        belumBayar: statusResult.summary.belumBayarCount,
        kelebihanBayar: statusResult.summary.kelebihanBayarCount,
        totalPemasukanBulan: statusResult.summary.totalBayar,
        totalTunggakanBulan: statusResult.summary.totalTunggakan,
        totalSemuaPemasukan: totalSemuaPemasukan
      },
      chartStatus: {
        sudahBayar: statusResult.summary.sudahBayarCount,
        cicilan: statusResult.summary.cicilanCount,
        belumBayar: statusResult.summary.belumBayarCount,
        kelebihanBayar: statusResult.summary.kelebihanBayarCount
      },
      chartKelas: {
        labels: chartKelasLabels,
        terbayar: chartKelasTerbayar,
        tunggakan: chartKelasTunggakan
      },
      transaksiTerbaru: top5Transactions,
      periode: { bulan: bulan, tahun: tahun }
    };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

// ============================================================================
// MODUL LAPORAN BULANAN & PER KELAS
// ============================================================================

function getLaporanSPP(bulan, tahun, idKelas) {
  try {
    var statusResult = getStatusPembayaran({
      bulan: bulan,
      tahun: tahun,
      idKelas: idKelas || 'Semua',
      status: 'Semua'
    });

    if (!statusResult.success) throw new Error(statusResult.message);

    var settings = getPengaturan().data || {};

    return {
      success: true,
      laporan: statusResult.data,
      summary: statusResult.summary,
      lembaga: {
        nama: settings.Nama_Lembaga || 'TPQ / Pesantren',
        alamat: settings.Alamat_Lembaga || '-',
        telepon: settings.Telepon_Lembaga || '-',
        bendahara: settings.Nama_Bendahara || 'Bendahara'
      },
      periode: statusResult.periode
    };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

// ============================================================================
// MODUL NOTIFIKASI PENGINGAT SPP VIA WALI JILID & ORANG TUA (WHATSAPP)
// ============================================================================

function normalizePhoneForWa(phone) {
  if (!phone) return '';
  var clean = String(phone).replace(/[^0-9]/g, '');
  if (clean.indexOf('08') === 0) {
    clean = '628' + clean.substring(2);
  } else if (clean.indexOf('8') === 0) {
    clean = '628' + clean.substring(1);
  }
  return clean;
}

/**
 * Format pengumuman untuk Grup WhatsApp Wali Santri per Jilid
 */
function buildPesanGrupJilid(namaKelas, nominalSPP, bulan, tahun, jatuhTempo) {
  bulan = normalizeBulan(bulan);
  jatuhTempo = jatuhTempo || '15';
  var formatRupiahStr = 'Rp ' + Number(nominalSPP || 0).toLocaleString('id-ID');

  var msg = 'Assalamu\'alaikum Warahmatullahi Wabarakatuh.\n' +
    'Bapak/Ibu Wali Santri *' + namaKelas + '* yang dirahmati Allah,\n\n' +
    'Semoga senantiasa dalam limpahan kesehatan dan keberkahan. 🤲\n\n' +
    'Kami menginfokan perihal administrasi iuran SPP santri untuk bulan *' + bulan + ' ' + tahun + '*:\n' +
    '• *Nominal:* ' + formatRupiahStr + ' / santri\n' +
    '• *Batas Pembayaran:* Tanggal ' + jatuhTempo + ' ' + bulan + '\n' +
    '• *Metode Pembayaran:* Tunai (Cash) diserahkan via Wali Jilid / Kantor Kantor TPQ Nurul Munim\n\n' +
    'Bagi Bapak/Ibu yang telah menyelesaikan pembayaran, kami haturkan _jazakumullahu khairan katsiran_. Bagi yang belum, mohon kerjasamanya untuk dapat diselesaikan sebelum tanggal jatuh tempo demi kelancaran kegiatan belajar santri.\n\n' +
    'Wassalamu\'alaikum Warahmatullahi Wabarakatuh.\n' +
    '_Wali Jilid ' + namaKelas + '_';

  return msg;
}

/**
 * Format pesan penagihan personal (Japri) ke orang tua santri
 */
function buildPesanJapriOrtu(namaSantri, nominalTunggakan, bulan, tahun) {
  bulan = normalizeBulan(bulan);
  var formatRupiahStr = 'Rp ' + Number(nominalTunggakan || 0).toLocaleString('id-ID');

  var msg = 'Assalamu\'alaikum Wr. Wb.\n' +
    'Bapak/Ibu Wali dari Ananda *' + namaSantri + '*,\n\n' +
    'Mohon maaf mengganggu waktunya. Mengingatkan dengan hormat terkait kewajiban iuran SPP Ananda untuk bulan *' + bulan + ' ' + tahun + '* sebesar *' + formatRupiahStr + '*.\n\n' +
    'Pembayaran dapat diserahkan secara tunai (cash) melalui santri atau langsung ke Wali Jilid / Bendahara.\n\n' +
    'Terima kasih banyak atas perhatian dan kerjasamanya. 🙏\n\n' +
    'Wassalamu\'alaikum Wr. Wb.';

  return msg;
}

/**
 * Format pesan penagihan akumulasi tunggakan jika santri menunggak lebih dari 1 bulan
 */
function buildPesanJapriOrtuAkumulasi(namaSantri, totalNominal, totalBulan, daftarBulan) {
  var formatRupiahStr = 'Rp ' + Number(totalNominal || 0).toLocaleString('id-ID');

  var msg = 'Assalamu\'alaikum Wr. Wb.\n' +
    'Bapak/Ibu Wali dari Ananda *' + namaSantri + '*,\n\n' +
    'Mohon maaf mengganggu waktunya. Mengingatkan dengan hormat terkait kewajiban iuran SPP Ananda yang tercatat masih memiliki tunggakan selama *' + totalBulan + ' bulan* (' + (daftarBulan || '') + ') dengan total *' + formatRupiahStr + '*.\n\n' +
    'Pembayaran dapat diserahkan secara tunai (cash) melalui santri atau langsung ke Wali Jilid / Bendahara.\n\n' +
    'Terima kasih banyak atas perhatian dan kerjasamanya. 🙏\n\n' +
    'Wassalamu\'alaikum Wr. Wb.';

  return msg;
}

/**
 * Membuat tautan 1-klik wa.me
 */
function generateWaMeLink(noHp, textPesan) {
  var clean = normalizePhoneForWa(noHp);
  if (!clean) return '';
  return 'https://wa.me/' + clean + '?text=' + encodeURIComponent(textPesan);
}

/**
 * Format rekap lengkap yang dikirim ke nomor WhatsApp Wali Jilid via Fonnte
 */
function buildPesanWaliJilid(jilidData, santriMenunggakList, bulan, tahun, settings, totalSantriKelas) {
  bulan = normalizeBulan(bulan);
  var namaWali = jilidData.Nama_Wali_Kelas || 'Ustadz/Ustadzah';
  var namaKelas = jilidData.Nama_Kelas;
  var jatuhTempo = settings.Tanggal_Jatuh_Tempo || '15';

  var totalBelumLunas = santriMenunggakList.length;
  var totalSantri = (typeof totalSantriKelas === 'number' && totalSantriKelas >= 0)
    ? totalSantriKelas
    : (jilidData.Jumlah_Santri || totalBelumLunas);

  if (totalSantri < totalBelumLunas) {
    totalSantri = totalBelumLunas;
  }

  var totalLunas = Math.max(0, totalSantri - totalBelumLunas);

  var totalTunggakan = 0;
  santriMenunggakList.forEach(function (s) {
    totalTunggakan += (Number(s.Sisa_Tagihan) || 0);
  });

  var msg = 'Assalamu\'alaikum Wr. Wb. ' + namaWali + ',\n' +
    'Berikut laporan administrasi SPP Santri *' + namaKelas + '* periode *' + bulan + ' ' + tahun + '*:\n\n' +
    '📊 *Rekapitulasi:*\n' +
    '• Total Santri: ' + totalSantri + ' santri\n' +
    '• Sudah Lunas: ' + totalLunas + ' santri\n' +
    '• Belum Lunas: ' + totalBelumLunas + ' santri\n' +
    '• Total Tunggakan: Rp ' + totalTunggakan.toLocaleString('id-ID') + '\n\n';

  if (santriMenunggakList.length > 0) {
    msg += '📋 *DAFTAR SANTRI BELUM LUNAS:*\n';
    santriMenunggakList.forEach(function (s, idx) {
      var rawHp = normalizePhoneForWa(s.No_HP);
      var sisaStr = 'Rp ' + Number(s.Sisa_Tagihan || 0).toLocaleString('id-ID');
      var nunggakDetail = (s.Total_Bulan_Nunggak && s.Total_Bulan_Nunggak > 1)
        ? ' [⚠️ Nunggak ' + s.Total_Bulan_Nunggak + ' Bln: Rp ' + Number(s.Total_Semua_Tunggakan || s.Sisa_Tagihan).toLocaleString('id-ID') + ']'
        : '';

      msg += (idx + 1) + '. *' + s.Nama + '* (' + sisaStr + ')' + nunggakDetail + '\n';
      if (rawHp) {
        msg += '   👉 Chat WA Ortu: https://wa.me/' + rawHp + '\n';
      } else {
        msg += '   👉 _(No. WA belum terdaftar)_\n';
      }
    });
    msg += '\n';
  } else {
    msg += '🎉 Alhamdulillah, seluruh santri di ' + namaKelas + ' sudah lunas untuk periode ini!\n\n';
  }

  // Sertakan draf pengumuman grup
  msg += '────────────────────\n' +
    '💬 *TEMPLATE PENGUMUMAN GRUP WALI SANTRI:*\n' +
    '_(Bisa langsung di-copy/teruskan ke grup WA kelas)_\n\n' +
    buildPesanGrupJilid(namaKelas, jilidData.Nominal_SPP, bulan, tahun, jatuhTempo) + '\n\n' +
    '────────────────────\n' +
    'Jazakumullah khairan katsiran atas kerjasamanya.\n' +
    '- Bendahara ' + (settings.Nama_Lembaga || 'Lembaga');

  return msg;
}

/**
 * Mengirim pesan WhatsApp via Fonnte API
 */
function kirimWaFonnte(targetPhone, messageText, customToken) {
  try {
    var target = normalizePhoneForWa(targetPhone);
    if (!target) {
      throw new Error('Nomor WhatsApp tujuan tidak valid atau kosong.');
    }

    var token = customToken;
    if (!token) {
      var pRes = getPengaturan();
      token = (pRes.success && pRes.data.Fonnte_Token) ? pRes.data.Fonnte_Token.trim() : '';
    }

    if (!token) {
      throw new Error('Token Fonnte belum diatur. Silakan buka menu Pengaturan dan masukkan Token API Fonnte Anda.');
    }

    var url = 'https://api.fonnte.com/send';
    var payload = {
      target: target,
      message: messageText
    };

    var options = {
      method: 'post',
      headers: {
        Authorization: token
      },
      payload: payload,
      muteHttpExceptions: true
    };

    var response = UrlFetchApp.fetch(url, options);
    var respText = response.getContentText();
    var respJson = {};
    try {
      respJson = JSON.parse(respText);
    } catch (e) {
      respJson = { raw: respText };
    }

    if (response.getResponseCode() === 200 && (respJson.status === true || respJson.status === 'true')) {
      return {
        success: true,
        message: 'Pesan berhasil terkirim ke ' + target,
        data: respJson
      };
    } else {
      return {
        success: false,
        message: 'Gagal kirim via Fonnte: ' + (respJson.reason || respJson.message || respText)
      };
    }
  } catch (err) {
    return {
      success: false,
      message: 'Kesalahan Fonnte: ' + err.message
    };
  }
}

/**
 * Uji coba koneksi token Fonnte
 */
function testKoneksiFonnte(token) {
  try {
    if (!token || token.trim() === '') {
      var pRes = getPengaturan();
      token = (pRes.success && pRes.data.Fonnte_Token) ? pRes.data.Fonnte_Token.trim() : '';
    }
    if (!token) {
      return { success: false, message: 'Token Fonnte belum diisi.' };
    }

    var url = 'https://api.fonnte.com/get-devices';
    var options = {
      method: 'post',
      headers: { Authorization: token.trim() },
      muteHttpExceptions: true
    };

    var response = UrlFetchApp.fetch(url, options);
    var resCode = response.getResponseCode();
    var resText = response.getContentText();
    var json = {};
    try { json = JSON.parse(resText); } catch (e) {}

    if (resCode === 200 && (json.status === true || json.status === 'true')) {
      var deviceName = (json.data && json.data.length > 0) ? (json.data[0].name || json.data[0].device) : 'Perangkat Terhubung';
      return {
        success: true,
        message: 'Koneksi Fonnte Berhasil! Status: ' + deviceName,
        data: json
      };
    } else {
      return {
        success: false,
        message: 'Koneksi Fonnte Gagal: ' + (json.reason || json.message || resText)
      };
    }
  } catch (e) {
    return { success: false, message: 'Gagal tes koneksi: ' + e.message };
  }
}

/**
 * Mengambil paket data pratinjau pengingat untuk halaman web (modal Salin Teks Grup / Info Wali)
 */
function getPesanPengingatKelas(idKelas, bulan, tahun) {
  try {
    bulan = normalizeBulan(bulan || (new Date().getMonth() + 1));
    tahun = Number(tahun || new Date().getFullYear());

    var statusRes = getStatusPembayaran({ bulan: bulan, tahun: tahun, idKelas: idKelas });
    if (!statusRes.success) throw new Error(statusRes.message);

    var rows = statusRes.data;
    var ss = getSpreadsheet();
    var sheetKelas = ss.getSheetByName('Kelas');
    if (sheetKelas) {
      ensureKelasSheetMigrated(sheetKelas);
    }

    var kelasList = getSheetData('Kelas');
    var kelas = kelasList.find(function (k) { return k.ID_Kelas === idKelas; });
    if (!kelas) throw new Error('Data kelas tidak ditemukan.');

    var pRes = getPengaturan();
    var settings = pRes.success ? pRes.data : {};

    var santriMenunggak = rows.filter(function (r) {
      return r.Status === 'Belum Bayar' || r.Status === 'Cicilan';
    });

    kelas.Jumlah_Santri = rows.length;
    var teksGrup = buildPesanGrupJilid(kelas.Nama_Kelas, kelas.Nominal_SPP, bulan, tahun, settings.Tanggal_Jatuh_Tempo);
    var pesanWali = buildPesanWaliJilid(kelas, santriMenunggak, bulan, tahun, settings, rows.length);

    var santriListWithLinks = santriMenunggak.map(function (s) {
      var pesanJapri = (s.Total_Bulan_Nunggak && s.Total_Bulan_Nunggak > 1)
        ? buildPesanJapriOrtuAkumulasi(s.Nama, s.Total_Semua_Tunggakan, s.Total_Bulan_Nunggak, s.Daftar_Bulan_Nunggak)
        : buildPesanJapriOrtu(s.Nama, s.Sisa_Tagihan, bulan, tahun);
      return {
        ID_Santri: s.ID_Santri,
        Nama: s.Nama,
        NIS: s.NIS,
        Sisa_Tagihan: s.Sisa_Tagihan,
        Total_Bulan_Nunggak: s.Total_Bulan_Nunggak || 1,
        Total_Semua_Tunggakan: s.Total_Semua_Tunggakan || s.Sisa_Tagihan,
        Daftar_Bulan_Nunggak: s.Daftar_Bulan_Nunggak || (bulan + ' ' + tahun),
        No_HP: s.No_HP || '',
        Pesan_Japri: pesanJapri,
        Link_WaMe: generateWaMeLink(s.No_HP, pesanJapri)
      };
    });

    return {
      success: true,
      data: {
        ID_Kelas: kelas.ID_Kelas,
        Nama_Kelas: kelas.Nama_Kelas,
        Nama_Wali_Kelas: kelas.Nama_Wali_Kelas || '',
        No_WA_Wali_Kelas: kelas.No_WA_Wali_Kelas || '',
        Bulan: bulan,
        Tahun: tahun,
        Teks_Grup: teksGrup,
        Pesan_Wali: pesanWali,
        Total_Santri: rows.length,
        Total_Menunggak: santriMenunggak.length,
        Santri_Menunggak: santriListWithLinks
      },
      pesanGrup: teksGrup,
      pesanWali: pesanWali,
      waliKelas: {
        nama: kelas.Nama_Wali_Kelas || '',
        noHp: kelas.No_WA_Wali_Kelas || ''
      },
      jumlahMenunggak: santriMenunggak.length
    };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

/**
 * Mengirimkan pesan rekap SPP ke 1 Wali Jilid tertentu
 */
function kirimRekapWaliJilid(idKelas, bulan, tahun) {
  try {
    var dataRes = getPesanPengingatKelas(idKelas, bulan, tahun);
    if (!dataRes.success) throw new Error(dataRes.message);

    var info = dataRes.data;
    var targetPhone = normalizePhoneForWa(info.No_WA_Wali_Kelas);
    if (!targetPhone) {
      throw new Error('Nomor WhatsApp Wali Jilid ' + info.Nama_Kelas + ' belum diisi atau tidak valid di menu Data Kelas.');
    }

    var sendRes = kirimWaFonnte(targetPhone, info.Pesan_Wali);
    if (!sendRes.success) throw new Error(sendRes.message);

    return {
      success: true,
      message: 'Berhasil mengirim rekap SPP ke Wali Jilid ' + info.Nama_Kelas + ' (' + (info.Nama_Wali_Kelas || info.No_WA_Wali_Kelas) + ')!'
    };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

/**
 * Mengirimkan rekap SPP ke SELURUH Wali Jilid aktif yang memiliki santri
 */
function kirimSemuaRekapWaliJilid(bulan, tahun) {
  try {
    bulan = normalizeBulan(bulan || (new Date().getMonth() + 1));
    tahun = Number(tahun || new Date().getFullYear());

    var ss = getSpreadsheet();
    var sheetKelas = ss.getSheetByName('Kelas');
    if (sheetKelas) {
      ensureKelasSheetMigrated(sheetKelas);
    }

    var kelasList = getSheetData('Kelas').filter(function (k) {
      return String(k.Status).toLowerCase() === 'aktif';
    });

    if (kelasList.length === 0) {
      return { success: false, message: 'Tidak ada kelas aktif yang ditemukan.' };
    }

    var suksesList = [];
    var gagalList = [];

    kelasList.forEach(function (k) {
      var rawNoWa = normalizePhoneForWa(k.No_WA_Wali_Kelas);
      if (!rawNoWa) {
        gagalList.push(k.Nama_Kelas + ' (No WA Wali belum diisi)');
        return;
      }

      var res = kirimRekapWaliJilid(k.ID_Kelas, bulan, tahun);
      if (res.success) {
        suksesList.push(k.Nama_Kelas);
      } else {
        gagalList.push(k.Nama_Kelas + ' (' + res.message + ')');
      }

      Utilities.sleep(1200); // Jeda 1.2 detik antar pengiriman pesan Fonnte
    });

    var msg = 'Selesai! Berhasil terkirim ke ' + suksesList.length + ' Wali Jilid.';
    if (gagalList.length > 0) {
      msg += ' Dilewati/Gagal pada ' + gagalList.length + ' kelas: ' + gagalList.join(', ');
    }

    return {
      success: suksesList.length > 0,
      message: msg,
      suksesCount: suksesList.length,
      gagalCount: gagalList.length
    };
  } catch (e) {
    return { success: false, message: e.message };
  }
}

/**
 * Pemicu harian otomatis: jika tanggal hari ini = tanggal jatuh tempo, kirim ke seluruh Wali Jilid
 */
function triggerBulananPengingatSPP() {
  try {
    var today = new Date();
    var tanggalHariIni = today.getDate();

    var pRes = getPengaturan();
    var settings = pRes.success ? pRes.data : {};
    var jatuhTempo = parseInt(settings.Tanggal_Jatuh_Tempo || '10', 10);

    if (tanggalHariIni === jatuhTempo) {
      var bulan = normalizeBulan(today.getMonth() + 1);
      var tahun = today.getFullYear();
      Logger.log('[TRIGGER SPP] Hari ini tanggal jatuh tempo (' + jatuhTempo + '). Memulai pengiriman rekap ke Wali Jilid...');
      var res = kirimSemuaRekapWaliJilid(bulan, tahun);
      Logger.log('[TRIGGER RESULT] ' + JSON.stringify(res));
      return res;
    } else {
      Logger.log('[TRIGGER SPP] Hari ini tanggal ' + tanggalHariIni + ', bukan tanggal jatuh tempo (' + jatuhTempo + '). Tidak ada pengiriman.');
      return { success: true, message: 'Bukan tanggal jatuh tempo.' };
    }
  } catch (e) {
    Logger.log('[TRIGGER ERROR] ' + e.message);
    return { success: false, message: e.message };
  }
}

// ============================================================================
// MODUL INPUT CEPAT & MIGRASI DATA PEMBAYARAN MASSAL (OPSI A)
// ============================================================================

/**
 * Menu otomatis Google Spreadsheet saat file dibuka
 */
function onOpen() {
  try {
    var ui = SpreadsheetApp.getUi();
    ui.createMenu('🕌 Sistem SPP')
      .addItem('📋 1. Siapkan Template Input Cepat', 'siapkanTemplateInputCepat')
      .addItem('⚡ 2. Proses Data Input Cepat', 'prosesInputCepatPembayaran')
      .addSeparator()
      .addItem('🔄 Sinkronkan Data Santri & Kelas', 'menuSyncSantri')
      .addToUi();
  } catch (e) {
    Logger.log('[onOpen Notice] Bukan lingkungan UI Spreadsheet: ' + e.message);
  }
}

/**
 * Trigger menu untuk sinkronisasi data santri langsung dari menu Spreadsheet
 */
function menuSyncSantri() {
  var ui;
  try { ui = SpreadsheetApp.getUi(); } catch (e) { }
  try {
    var res = syncSantriDariSpreadsheetLain();
    if (ui) {
      ui.alert('Sinkronisasi Santri', res.message || 'Data santri berhasil disinkronkan.', ui.ButtonSet.OK);
    }
    return res;
  } catch (e) {
    if (ui) {
      ui.alert('Error', 'Gagal sinkronisasi: ' + e.message, ui.ButtonSet.OK);
    }
    return { success: false, message: e.message };
  }
}

/**
 * Helper: Index bulan dari 0 (Januari) sampai 11 (Desember)
 */
function getBulanIndex(bulan) {
  var bNorm = normalizeBulan(bulan);
  for (var i = 0; i < NAMA_BULAN.length; i++) {
    if (NAMA_BULAN[i].toLowerCase() === String(bNorm).toLowerCase().trim()) {
      return i;
    }
  }
  return -1;
}

/**
 * Helper: Generate urutan bulan dan tahun dari (startBulan, startTahun) s/d (endBulan, endTahun)
 */
function generateDaftarBulan(dariBulan, dariTahun, sampaiBulan, sampaiTahun) {
  var startIdx = getBulanIndex(dariBulan);
  var endIdx = getBulanIndex(sampaiBulan);
  var startY = parseInt(dariTahun, 10);
  var endY = parseInt(sampaiTahun, 10);

  if (startIdx === -1) throw new Error('Bulan awal tidak valid: ' + dariBulan);
  if (endIdx === -1) throw new Error('Bulan akhir tidak valid: ' + sampaiBulan);
  if (isNaN(startY) || isNaN(endY)) throw new Error('Tahun tidak valid.');

  var startTotal = startY * 12 + startIdx;
  var endTotal = endY * 12 + endIdx;

  if (endTotal < startTotal) {
    throw new Error('Periode akhir (' + sampaiBulan + ' ' + sampaiTahun + ') tidak boleh mendahului periode awal (' + dariBulan + ' ' + dariTahun + ').');
  }

  var list = [];
  var curY = startY;
  var curM = startIdx;
  while (curY < endY || (curY === endY && curM <= endIdx)) {
    list.push({ bulan: NAMA_BULAN[curM], tahun: curY });
    curM++;
    if (curM > 11) {
      curM = 0;
      curY++;
    }
  }
  return list;
}

/**
 * 1. Menyiapkan sheet 'Input_Cepat' dan otomatis memasukkan seluruh data santri aktif
 */
function siapkanTemplateInputCepat() {
  var ui;
  try { ui = SpreadsheetApp.getUi(); } catch (e) { }

  try {
    var ss = getSpreadsheet();

    // 1. Pastikan data santri di sheet 'Santri' selalu yang paling baru dari Spreadsheet siswa
    try {
      syncSantriDariSpreadsheetLain();
    } catch (eSync) {
      Logger.log('[WARN Sync Santri] ' + eSync.message);
    }

    var sheetSantri = ss.getSheetByName('Santri');
    if (!sheetSantri || sheetSantri.getLastRow() <= 1) {
      throw new Error('Data santri tidak ditemukan di sheet "Santri". Silakan periksa koneksi data siswa.');
    }

    var santriList = getSheetData('Santri');
    var kelasList = getSheetData('Kelas');

    var kelasMap = {};
    kelasList.forEach(function (k) {
      kelasMap[k.ID_Kelas] = k.Nama_Kelas;
    });

    var sheetName = 'Input_Cepat';
    var sheetInput = ss.getSheetByName(sheetName);

    if (sheetInput) {
      if (ui) {
        var resp = ui.alert(
          'Konfirmasi Template Input Cepat',
          'Sheet "Input_Cepat" sudah ada. Apakah Anda ingin membuat ulang template ini?\n\n(Catatan: Isian yang sudah Anda ketik di sheet ini akan ditimpa dengan data santri terbaru)',
          ui.ButtonSet.YES_NO
        );
        if (resp !== ui.Button.YES) {
          return { success: false, message: 'Dibatalkan oleh pengguna.' };
        }
      }
      sheetInput.clear();
      sheetInput.clearFormats();
    } else {
      sheetInput = ss.insertSheet(sheetName);
    }

    var headers = [
      'NIS',
      'Nama_Santri',
      'Kelas',
      'Dari_Bulan',
      'Dari_Tahun',
      'Sampai_Bulan',
      'Sampai_Tahun',
      'Tanggal_Bayar',
      'Metode',
      'Petugas',
      'Keterangan',
      'Status_Proses'
    ];

    sheetInput.getRange(1, 1, 1, headers.length).setValues([headers]);
    var headerRange = sheetInput.getRange(1, 1, 1, headers.length);
    headerRange.setBackground('#065f46')
      .setFontColor('#ffffff')
      .setFontWeight('bold')
      .setHorizontalAlignment('center');
    try { sheetInput.setFrozenRows(1); } catch (e) { }

    var todayStr = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');
    var currentYear = new Date().getFullYear();

    var rows = [];
    santriList.forEach(function (s) {
      // Lewati santri nonaktif
      if (String(s.Status || '').toLowerCase() === 'nonaktif') return;

      var namaKls = kelasMap[s.ID_Kelas] || 'Jilid 1';
      rows.push([
        s.NIS || '',
        s.Nama || '',
        namaKls,
        'Juli',
        2026,
        'Oktober',
        currentYear,
        todayStr,
        'Tunai',
        'Bendahara',
        'Migrasi Pembayaran',
        'Siap Diproses'
      ]);
    });

    if (rows.length > 0) {
      sheetInput.getRange(2, 1, rows.length, headers.length).setValues(rows);

      // Tambahkan Dropdown validasi nama bulan untuk kolom Dari_Bulan (col 4) & Sampai_Bulan (col 6)
      var ruleBulan = SpreadsheetApp.newDataValidation()
        .requireValueInList(NAMA_BULAN, true)
        .setAllowInvalid(false)
        .build();
      sheetInput.getRange(2, 4, rows.length, 1).setDataValidation(ruleBulan);
      sheetInput.getRange(2, 6, rows.length, 1).setDataValidation(ruleBulan);

      // Format kolom Tanggal_Bayar (col 8) sebagai yyyy-mm-dd
      sheetInput.getRange(2, 8, rows.length, 1).setNumberFormat('yyyy-mm-dd');

      // Format alignment teks
      sheetInput.getRange(2, 1, rows.length, 1).setHorizontalAlignment('center');
      sheetInput.getRange(2, 3, rows.length, 7).setHorizontalAlignment('center');
      sheetInput.getRange(2, 12, rows.length, 1).setHorizontalAlignment('center');

      // Auto resize kolom
      for (var col = 1; col <= headers.length; col++) {
        sheetInput.autoResizeColumn(col);
      }
    }

    SpreadsheetApp.flush();

    var msg = 'Alhamdulillah! Template "Input_Cepat" berhasil disiapkan untuk ' + rows.length + ' santri aktif.\n\n' +
      'Silakan buka sheet "Input_Cepat" di Spreadsheet, sesuaikan kolom "Sampai_Bulan" dan "Sampai_Tahun" dengan catatan kertas Anda, ' +
      'lalu jalankan "⚡ 2. Proses Data Input Cepat".';

    if (ui) {
      ui.alert('Template Siap Digunakan!', msg, ui.ButtonSet.OK);
    }

    return {
      success: true,
      message: msg,
      totalSantri: rows.length
    };
  } catch (err) {
    if (ui) {
      ui.alert('Gagal Menyiapkan Template', err.message, ui.ButtonSet.OK);
    }
    return { success: false, message: err.message };
  }
}

/**
 * 2. Memproses data dari sheet 'Input_Cepat' secara massal dan otomatis membuat Tagihan & Pembayaran lunas
 */
function prosesInputCepatPembayaran() {
  var lock = LockService.getScriptLock();
  var hasLock = false;
  var ui;
  try { ui = SpreadsheetApp.getUi(); } catch (e) { }

  try {
    try {
      hasLock = lock.tryLock(30000);
    } catch (eLock) {
      hasLock = false;
    }

    var ss = getSpreadsheet();
    var sheetInput = ss.getSheetByName('Input_Cepat');
    if (!sheetInput || sheetInput.getLastRow() <= 1) {
      throw new Error('Sheet "Input_Cepat" belum ada atau masih kosong. Silakan jalankan "1. Siapkan Template Input Cepat" terlebih dahulu.');
    }

    var sheetTagihan = ss.getSheetByName('Tagihan');
    var sheetPembayaran = ss.getSheetByName('Pembayaran');
    if (!sheetTagihan || !sheetPembayaran) {
      throw new Error('Sheet Tagihan atau Pembayaran tidak ditemukan di Spreadsheet ini.');
    }

    var numRows = sheetInput.getLastRow() - 1;
    var dataInput = sheetInput.getRange(2, 1, numRows, 12).getValues();

    // Data Master
    var santriList = getSheetData('Santri');
    var kelasList = getSheetData('Kelas');
    var existingTagihan = getSheetData('Tagihan');
    var existingPembayaran = getSheetData('Pembayaran');

    // Map tarif kelas
    var kelasMap = {};
    kelasList.forEach(function (k) {
      kelasMap[k.ID_Kelas] = Number(k.Nominal_SPP) || 0;
    });

    // Map Santri berdasarkan NIS dan Nama (huruf kecil)
    var santriByNis = {};
    var santriByName = {};
    santriList.forEach(function (s) {
      if (s.NIS) santriByNis[String(s.NIS).trim()] = s;
      if (s.Nama) santriByName[String(s.Nama).toLowerCase().trim()] = s;
    });

    // Map Tagihan yang sudah ada: key = ID_Santri + '_' + BulanNorm + '_' + Tahun
    var tagihanMap = {};
    existingTagihan.forEach(function (t) {
      var key = t.ID_Santri + '_' + normalizeBulan(t.Bulan) + '_' + Number(t.Tahun);
      tagihanMap[key] = {
        _rowIndex: t._rowIndex,
        ID_Tagihan: t.ID_Tagihan,
        Nominal_Tagihan: Number(t.Nominal_Tagihan) || 0,
        Status: t.Status
      };
    });

    // Map Total Bayar Sah per ID_Tagihan
    var totalBayarPerTagihan = {};
    existingPembayaran.forEach(function (p) {
      var ket = String(p.Keterangan || '');
      if (ket.indexOf('[BATAL') === 0) return;
      var idTgh = p.ID_Tagihan;
      totalBayarPerTagihan[idTgh] = (totalBayarPerTagihan[idTgh] || 0) + (Number(p.Nominal_Bayar) || 0);
    });

    var newTagihanRows = [];
    var newPembayaranRows = [];
    var tagihanStatusUpdates = [];
    var statusColValues = [];

    var countSantriSukses = 0;
    var countBulanLunasBaru = 0;
    var countBulanSudahLunas = 0;
    var totalUangMasuk = 0;

    for (var r = 0; r < dataInput.length; r++) {
      var row = dataInput[r];
      var nis = String(row[0] || '').trim();
      var nama = String(row[1] || '').trim();
      var dariBulan = row[3];
      var dariTahun = row[4];
      var sampaiBulan = row[5];
      var sampaiTahun = row[6];
      var tglRaw = row[7];
      var metode = String(row[8] || 'Tunai').trim();
      var petugas = String(row[9] || 'Bendahara').trim();
      var keterangan = String(row[10] || 'Migrasi Pembayaran').trim();

      if (!nis && !nama) {
        statusColValues.push(['']);
        continue;
      }

      var tglBayarStr = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');
      if (tglRaw instanceof Date) {
        tglBayarStr = Utilities.formatDate(tglRaw, 'Asia/Jakarta', 'yyyy-MM-dd');
      } else if (typeof tglRaw === 'string' && tglRaw.trim() !== '') {
        tglBayarStr = tglRaw.trim();
      }

      var santri = (nis && santriByNis[nis]) ? santriByNis[nis] : (nama ? santriByName[nama.toLowerCase()] : null);
      if (!santri) {
        statusColValues.push(['❌ Santri tidak ditemukan']);
        continue;
      }

      var statusProsesRaw = String(row[11] || '').toLowerCase().trim();
      var dariBulanStr = String(dariBulan || '').toLowerCase().trim();
      var sampaiBulanStr = String(sampaiBulan || '').toLowerCase().trim();

      // Jika santri belum bayar sama sekali / dilewati (bulan dikosongkan, diisi strip '-', atau ditandai belum bayar)
      if (!dariBulan || !sampaiBulan || 
          dariBulanStr === '-' || sampaiBulanStr === '-' ||
          dariBulanStr.indexOf('belum') !== -1 || sampaiBulanStr.indexOf('belum') !== -1 ||
          statusProsesRaw.indexOf('belum') !== -1 || statusProsesRaw.indexOf('lewati') !== -1 || statusProsesRaw.indexOf('skip') !== -1) {
        statusColValues.push(['⚪ Dilewati (Belum Bayar)']);
        continue;
      }

      var daftarBulan;
      try {
        daftarBulan = generateDaftarBulan(dariBulan, dariTahun, sampaiBulan, sampaiTahun);
      } catch (errRange) {
        statusColValues.push(['❌ Periode salah: ' + errRange.message]);
        continue;
      }

      var nominalTarif = kelasMap[santri.ID_Kelas] || 0;

      for (var b = 0; b < daftarBulan.length; b++) {
        var itemBulan = daftarBulan[b];
        var keyTagihan = santri.ID_Santri + '_' + itemBulan.bulan + '_' + itemBulan.tahun;
        var tagihanInfo = tagihanMap[keyTagihan];
        var idTagihan;
        var nominalTagihan = nominalTarif;

        if (tagihanInfo) {
          idTagihan = tagihanInfo.ID_Tagihan;
          nominalTagihan = tagihanInfo.Nominal_Tagihan || nominalTarif;
        } else {
          idTagihan = 'TGH-' + santri.ID_Santri + '-' + itemBulan.tahun + '-' + itemBulan.bulan.substring(0, 3).toUpperCase() + '-' + Utilities.formatString('%03d', Math.floor(Math.random() * 1000));
          newTagihanRows.push([
            idTagihan,
            santri.ID_Santri,
            itemBulan.bulan,
            itemBulan.tahun,
            nominalTagihan,
            'Sudah Bayar'
          ]);

          tagihanInfo = {
            ID_Tagihan: idTagihan,
            Nominal_Tagihan: nominalTagihan,
            Status: 'Sudah Bayar',
            isNew: true
          };
          tagihanMap[keyTagihan] = tagihanInfo;
        }

        var sudahTerbayar = totalBayarPerTagihan[idTagihan] || 0;
        if (sudahTerbayar >= nominalTagihan) {
          countBulanSudahLunas++;
          if (!tagihanInfo.isNew && tagihanInfo.Status !== 'Sudah Bayar' && tagihanInfo._rowIndex) {
            tagihanStatusUpdates.push({ row: tagihanInfo._rowIndex, status: 'Sudah Bayar' });
            tagihanInfo.Status = 'Sudah Bayar';
          }
        } else {
          var sisaHarusBayar = nominalTagihan - sudahTerbayar;
          var idPembayaran = 'PAY-' + Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyMMddHHmmss') + '-' + Utilities.formatString('%03d', Math.floor(Math.random() * 1000));

          newPembayaranRows.push([
            idPembayaran,
            idTagihan,
            tglBayarStr,
            sisaHarusBayar,
            metode,
            petugas,
            keterangan + ' (' + itemBulan.bulan + ' ' + itemBulan.tahun + ')'
          ]);

          totalBayarPerTagihan[idTagihan] = (totalBayarPerTagihan[idTagihan] || 0) + sisaHarusBayar;
          totalUangMasuk += sisaHarusBayar;
          countBulanLunasBaru++;

          if (!tagihanInfo.isNew && tagihanInfo.Status !== 'Sudah Bayar' && tagihanInfo._rowIndex) {
            tagihanStatusUpdates.push({ row: tagihanInfo._rowIndex, status: 'Sudah Bayar' });
            tagihanInfo.Status = 'Sudah Bayar';
          }
        }
      }

      statusColValues.push(['✅ Lunas (' + daftarBulan.length + ' Bulan)']);
      countSantriSukses++;
    }

    // Tulis ke sheet secara massal (Batch write untuk performa kilat)
    if (newTagihanRows.length > 0) {
      var startRowTgh = sheetTagihan.getLastRow() + 1;
      sheetTagihan.getRange(startRowTgh, 1, newTagihanRows.length, 6).setValues(newTagihanRows);
    }

    if (newPembayaranRows.length > 0) {
      var startRowPay = sheetPembayaran.getLastRow() + 1;
      sheetPembayaran.getRange(startRowPay, 1, newPembayaranRows.length, 7).setValues(newPembayaranRows);
    }

    if (tagihanStatusUpdates.length > 0) {
      tagihanStatusUpdates.forEach(function (up) {
        sheetTagihan.getRange(up.row, 6).setValue(up.status);
      });
    }

    // Update status proses di sheet Input_Cepat
    sheetInput.getRange(2, 12, statusColValues.length, 1).setValues(statusColValues);
    SpreadsheetApp.flush();

    var formatUang = function (num) {
      return 'Rp ' + Number(num).toLocaleString('id-ID');
    };

    var ringkasan = 'Alhamdulillah! Proses Input Cepat selesai:\n\n' +
      '• Santri Berhasil Diproses: ' + countSantriSukses + ' santri\n' +
      '• Tagihan Lunas Baru Dicatat: ' + countBulanLunasBaru + ' bulan transaksi\n' +
      '• Bulan Yang Sudah Lunas Sebelumnya: ' + countBulanSudahLunas + ' bulan\n' +
      '• Total Nominal Tercatat: ' + formatUang(totalUangMasuk) + '\n\n' +
      'Seluruh data pembayaran sekarang sudah sinkron dan lunas di aplikasi!';

    if (ui) {
      ui.alert('Proses Input Cepat Selesai!', ringkasan, ui.ButtonSet.OK);
    }

    return {
      success: true,
      message: ringkasan,
      data: {
        santriSukses: countSantriSukses,
        bulanLunasBaru: countBulanLunasBaru,
        bulanSudahLunas: countBulanSudahLunas,
        totalNominal: totalUangMasuk
      }
    };
  } catch (e) {
    if (ui) {
      ui.alert('Error Proses Input Cepat', e.message, ui.ButtonSet.OK);
    }
    return { success: false, message: e.message };
  } finally {
    if (hasLock) {
      try { lock.releaseLock(); } catch (e) { }
    }
  }
}
