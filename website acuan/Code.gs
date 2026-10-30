  // KONFIGURASI SPREADSHEET
  const SPREADSHEET_ID = '1mVn1iXEGDYQoQ1Ft-_nHgC7WOGJeuAg--Bu-cdoiTNk';

  // Fungsi untuk mendapatkan spreadsheet
  function getSpreadsheet() {
    return SpreadsheetApp.openById(SPREADSHEET_ID);
  }

  // ====================================
  // MAIN ENTRY POINT
  // ====================================
  function doGet(e) {
    // Jika dipanggil via API query parameter untuk scanner eksternal
    if (e && e.parameter && e.parameter.action === 'scanAbsensi') {
      const result = scanAbsensi(e.parameter.nisn, e.parameter.scannerRole || 'guru', e.parameter.scannerKelas || '');
      if (e.parameter.callback) {
        return ContentService.createTextOutput(e.parameter.callback + '(' + JSON.stringify(result) + ')')
          .setMimeType(ContentService.MimeType.JAVASCRIPT);
      }
      return ContentService.createTextOutput(JSON.stringify(result))
        .setMimeType(ContentService.MimeType.JSON);
    }

    // Endpoint Scanner: Ambil konfigurasi jadwal waktu secara realtime
    if (e && e.parameter && e.parameter.action === 'getConfig') {
      const result = getAppConfig();
      if (result.success) {
        result.serverTime = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'HH:mm');
        result.serverDate = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');
      }
      if (e.parameter.callback) {
        return ContentService.createTextOutput(e.parameter.callback + '(' + JSON.stringify(result) + ')')
          .setMimeType(ContentService.MimeType.JAVASCRIPT);
      }
      return ContentService.createTextOutput(JSON.stringify(result))
        .setMimeType(ContentService.MimeType.JSON);
    }

    const template = HtmlService.createTemplateFromFile('index');
    return template.evaluate()
      .setTitle('Aplikasi Absensi Sekolah')
      .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL)
      .addMetaTag('viewport', 'width=device-width, initial-scale=1')
      .setFaviconUrl('https://if.polibatam.ac.id/pamerin/uploads/pbl/3312011063/3312011063_gambar1_20220710.png');
  }


  function onEdit(e) {
    var sheet = e.source.getActiveSheet();
    
    // Hanya berjalan jika pengeditan dilakukan pada sheet "siswa"
    if (sheet.getName() === "siswa") {
      var lastRow = sheet.getLastRow();
      if (lastRow > 1) {
        
        // 1. Mengurutkan tabel A2:J bertingkat:
        //    Pertama: Berdasarkan Kolom I (Kelas) secara Urut (A-Z / JILID 1 -> JILID 2 -> dst)
        //    Kedua : Berdasarkan Kolom C (Jenis Kelamin)
        var range = sheet.getRange("A2:J" + lastRow);
        range.sort([
          {column: 9, ascending: true}, // Kolom I (Kelas)
          {column: 3, ascending: true}  // Kolom C (Jenis Kelamin)
        ]);
        
        // 2. Ambil data Kelas (Kolom I) untuk menentukan warna
        var classRange = sheet.getRange("I2:I" + lastRow);
        var values = classRange.getValues();
        var backgrounds = [];

        // Aturan warna masing-masing jilid
        for (var i = 0; i < values.length; i++) {
          var val = values[i][0].toString().trim().toUpperCase();
          
          if (val === "JILID 1") {
            backgrounds.push(["#f44336"]); // Merah
          } else if (val === "JILID 2") {
            backgrounds.push(["#e91e63"]); // Pink / Merah Muda
          } else if (val === "JILID 3") {
            backgrounds.push(["#f2a93b"]); // Jingga / Kuning
          } else if (val === "JILID 4") {
            backgrounds.push(["#2196f3"]); // Biru
          } else if (val === "JILID 5") {
            backgrounds.push(["#4caf50"]); // Hijau
          } else if (val === "JILID 6") {
            backgrounds.push(["#9c27b0"]); // Ungu
          } else {
            backgrounds.push(["#ffffff"]); // Putih
          }
        }

        // 3. Terapkan warna ke Kolom Nama (Kolom A) dan Kolom Kelas (Kolom I)
        var nameRange = sheet.getRange("A2:A" + lastRow);
        
        nameRange.setBackgrounds(backgrounds);  // Warnai Kolom Nama (A)
        classRange.setBackgrounds(backgrounds); // Warnai Kolom Kelas (I)
      }
    }
  }
  // ====================================
  // AUTENTIKASI & SESSION
  // ====================================
  function login(username, password, nisn) {
    try {
      const ss = getSpreadsheet();
      const usersSheet = ss.getSheetByName('users');
      const siswaSheet = ss.getSheetByName('siswa');

      let userFound = null;

      // -------------------------------------------
      // 1. LOGIKA CEK KREDENSIAL (SAMA SEPERTI LAMA)
      // -------------------------------------------
      
      // A. Login SISWA (Pakai NISN)
      if (nisn) {
        const siswaData = siswaSheet.getDataRange().getValues();
        for (let i = 1; i < siswaData.length; i++) {
          if (String(siswaData[i][1]) === String(nisn)) { 
            userFound = {
              role: 'siswa',
              identifier: siswaData[i][1], // NISN
              nama: siswaData[i][0],
              kelas: siswaData[i][8]
            };
            break;
          }
        }
        if (!userFound) return { success: false, message: 'NISN tidak ditemukan' };
      } 
      // B. Login ADMIN & GURU (Username/Pass)
      else {
        const userData = usersSheet.getDataRange().getValues();
        for (let i = 1; i < userData.length; i++) {
          if (userData[i][0] == username && userData[i][1] == password) {
            userFound = {
              role: userData[i][2],
              identifier: userData[i][0], // Username
              nama: userData[i][0],       // Gunakan username sbg nama
              kelas: userData[i][3] || "" 
            };
            break;
          }
        }
        if (!userFound) return { success: false, message: 'Username atau password salah' };
      }

      // -------------------------------------------
      // 2. GENERATE & SIMPAN TOKEN (KEAMANAN BARU)
      // -------------------------------------------
      
      // Buat Token Unik (UUID)
      const token = Utilities.getUuid();
      
      // Set Waktu Expired (Minimal 1 Minggu / 7 Hari dari sekarang)
      const SESSION_DURATION_DAYS = 7;
      const expiry = new Date();
      expiry.setTime(expiry.getTime() + (SESSION_DURATION_DAYS * 24 * 60 * 60 * 1000)); 

      // Siapkan Sheet Sessions
      let sessionSheet = ss.getSheetByName('sessions');
      if (!sessionSheet) {
        sessionSheet = ss.insertSheet('sessions');
        sessionSheet.appendRow(['Token', 'Identifier', 'Role', 'Expiry']);
        // Format kolom tanggal agar mudah dibaca (Opsional)
        sessionSheet.getRange("D:D").setNumberFormat("yyyy-mm-dd hh:mm:ss");
      }

      // Simpan data sesi baru ke Sheet
      sessionSheet.appendRow([
        token, 
        userFound.identifier, 
        userFound.role, 
        expiry
      ]);

      // -------------------------------------------
      // 3. KEMBALIKAN TOKEN KE FRONTEND
      // -------------------------------------------
      return {
        success: true,
        token: token,      // Token dikirim ke frontend
        role: userFound.role,
        username: userFound.identifier,
        nama: userFound.nama,
        kelas: userFound.kelas,
        nisn: (userFound.role === 'siswa') ? userFound.identifier : null
      };

    } catch (error) {
      return { success: false, message: "Login Error: " + error.toString() };
    }
  }


  function verifyUser(token, requiredRole) {
    const ss = SpreadsheetApp.openById(SPREADSHEET_ID); // Pastikan ID Spreadsheet global terbaca
    const sheet = ss.getSheetByName('sessions');
    const data = sheet.getDataRange().getValues();
    const now = new Date();

    for (let i = 1; i < data.length; i++) {
      // data[i][0] = Token, data[i][2] = Role, data[i][3] = Expiry
      if (data[i][0] === token) {
        // Cek apakah token expired (misal 24 jam)
        if (data[i][3] instanceof Date && data[i][3] > now) {
          // Cek Role
          if (requiredRole && data[i][2] !== requiredRole && data[i][2] !== 'admin') {
            // Admin biasanya boleh akses semua, jika role user != required, tolak
            throw new Error("Akses Ditolak: Anda tidak memiliki izin.");
          }
          return true; // Valid
        } else {
          throw new Error("Sesi berakhir. Silakan login ulang.");
        }
      }
    }
    throw new Error("Token tidak valid atau tidak ditemukan.");
  }
  // ====================================
  // SISWA - CRUD OPERATIONS
  // ====================================
  function getSiswaList() {
    try {
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('siswa');
      const data = sheet.getDataRange().getValues();
      const siswaList = [];
      
      for (let i = 1; i < data.length; i++) {
        if (data[i][0]) { 
          
          // --- PERUBAHAN LOGIKA BACA TANGGAL ---
          let rawTgl = data[i][3];
          let tglLahir = '';

          if (rawTgl instanceof Date) {
            // Jika Sheet masih membacanya sebagai Date Object
            tglLahir = Utilities.formatDate(rawTgl, 'Asia/Jakarta', 'yyyy-MM-dd');
          } else if (typeof rawTgl === 'string') {
            // Jika format string 'dd-mm-yyyy' (cth: 06-01-2026)
            // Kita harus balik jadi yyyy-mm-dd agar bisa dibaca input HTML
            let cleanTgl = rawTgl.replace(/'/g, "").trim(); // Hapus kutip jika ada
            if (cleanTgl.includes('-')) {
              let parts = cleanTgl.split('-');
              // Cek apakah formatnya dd-mm-yyyy (tahun di belakang)
              if (parts[2] && parts[2].length === 4) {
                tglLahir = parts[2] + '-' + parts[1] + '-' + parts[0];
              } else {
                tglLahir = cleanTgl; // Asumsi sudah benar
              }
            }
          }
          // ---------------------------------------

          siswaList.push({
            nama: data[i][0],
            nisn: data[i][1],
            jenisKelamin: data[i][2],
            tanggalLahir: tglLahir, // Hasil konversi untuk HTML Input
            agama: data[i][4],
            namaAyah: data[i][5],
            namaIbu: data[i][6],
            noHp: data[i][7],
            kelas: data[i][8],
            alamat: data[i][9]
          });
        }
      }
      
      return { success: true, data: siswaList };
    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }

  function getSiswaByNisn(nisn) {
    try {
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('siswa');
      const data = sheet.getDataRange().getValues();
      
      for (let i = 1; i < data.length; i++) {
        if (data[i][1] == nisn) {
          
          // FORMAT TANGGAL LAHIR DISINI JUGA
          let tglLahir = '';
          if (data[i][3]) {
            tglLahir = Utilities.formatDate(new Date(data[i][3]), 'Asia/Jakarta', 'yyyy-MM-dd');
          }

          return {
            success: true,
            data: {
              nama: data[i][0],
              nisn: data[i][1],
              jenisKelamin: data[i][2],
              tanggalLahir: tglLahir,
              agama: data[i][4],
              namaAyah: data[i][5],
              namaIbu: data[i][6],
              noHp: data[i][7],
              kelas: data[i][8],
              alamat: data[i][9]
            }
          };
        }
      }
      
      return { success: false, message: 'Siswa tidak ditemukan' };
    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }

  function addSiswa(token, siswaData) { 
    try {
      // --- 1. PASANG SATPAM DISINI ---
      // Cek apakah pengirim punya token admin yang valid?
      verifyUser(token, 'admin'); 
      // -------------------------------

      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('siswa');

      // Bersihkan data validation yang salah pada kolom Nama Ayah (F), Nama Ibu (G), No HP (H), dan Alamat (J)
      sheet.getRange('F2:H').clearDataValidations();
      sheet.getRange('J2:J').clearDataValidations();

      // Cek NISN duplikat
      const data = sheet.getDataRange().getValues();
      for (let i = 1; i < data.length; i++) {
        if (data[i][1] == siswaData.nisn) {
          return { success: false, message: 'NISN sudah terdaftar' };
        }
      }
      
      // Format Tanggal
      let tglSimpan = siswaData.tanggalLahir;
      if (tglSimpan && tglSimpan.includes('-')) {
        let parts = tglSimpan.split('-');
        tglSimpan = "'" + parts[2] + '-' + parts[1] + '-' + parts[0];
      }

      sheet.appendRow([
        siswaData.nama,
        siswaData.nisn,
        siswaData.jenisKelamin,
        tglSimpan,
        siswaData.agama,
        siswaData.namaAyah,
        siswaData.namaIbu,
        siswaData.noHp,
        siswaData.kelas,
        siswaData.alamat
      ]);
      return { success: true, message: 'Siswa berhasil ditambahkan (Aman)' };

    } catch (error) {
      // Jika token salah, akan masuk ke sini
      return { success: false, message: "GAGAL: " + error.message };
    }
  }

  function updateSiswa(token, oldNisn, siswaData) {
    try {
      verifyUser(token, 'admin');
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('siswa');
      
      // Bersihkan data validation yang salah pada kolom Nama Ayah (F), Nama Ibu (G), No HP (H), dan Alamat (J)
      sheet.getRange('F2:H').clearDataValidations();
      sheet.getRange('J2:J').clearDataValidations();
      
      const data = sheet.getDataRange().getValues();
      
      // --- PERUBAHAN DISINI: Format Tanggal Lahir (YYYY-MM-DD -> dd-MM-yyyy) ---
      let tglSimpan = siswaData.tanggalLahir;
      if (tglSimpan && tglSimpan.includes('-')) {
        // Cek apakah format input masih yyyy-mm-dd (default HTML)
        let parts = tglSimpan.split('-');
        if(parts[0].length === 4) { // Jika tahun di depan (2026-...)
            tglSimpan = "'" + parts[2] + '-' + parts[1] + '-' + parts[0];
        }
      }
      // ------------------------------------------------------------------------

      for (let i = 1; i < data.length; i++) {
        if (data[i][1] == oldNisn) {
          sheet.getRange(i + 1, 1, 1, 10).setValues([[
            siswaData.nama,
            siswaData.nisn,
            siswaData.jenisKelamin,
            tglSimpan, // Gunakan variabel yang sudah diformat
            siswaData.agama,
            siswaData.namaAyah,
            siswaData.namaIbu,
            siswaData.noHp,
            siswaData.kelas,
            siswaData.alamat
          ]]);
          return { success: true, message: 'Siswa berhasil diupdate' };
        }
      }
      
      return { success: false, message: 'Siswa tidak ditemukan' };
    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }

  function deleteSiswa(token, nisn) {
    try {
      // 1. CEK KEAMANAN (Server Side Validation)
      // Hanya user dengan role 'admin' yang memiliki token valid yang boleh menghapus
      verifyUser(token, 'admin'); 

      // 2. LOGIKA HAPUS (Jika token valid, kode lanjut ke sini)
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('siswa');
      const data = sheet.getDataRange().getValues();
      
      for (let i = 1; i < data.length; i++) {
        // Cek NISN pada kolom index 1
        if (String(data[i][1]) === String(nisn)) {
          sheet.deleteRow(i + 1);
          return { success: true, message: 'Data siswa berhasil dihapus.' };
        }
      }
      
      return { success: false, message: 'Data siswa tidak ditemukan.' };

    } catch (error) {
      // Jika verifyUser gagal, error akan tertangkap di sini
      return { success: false, message: error.message };
    }
  }

  // ====================================
  // GURU - CRUD OPERATIONS
  // ====================================
  function getGuruList(token) {
    try {
      // 1. CEK KEAMANAN (Server Side Validation)
      // Wajib role 'admin'. Token siswa/guru akan ditolak disini.
      verifyUser(token, 'admin'); 

      // 2. LOGIKA AMBIL DATA (Hanya jalan jika token valid & admin)
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('users');
      const data = sheet.getDataRange().getValues();
      const guruList = [];

      // Loop data mulai baris 1 (lewati header)
      for (let i = 1; i < data.length; i++) {
        // Cek kolom Role (Index 2) apakah 'guru'
        if (data[i][2] == 'guru') {
          guruList.push({
            username: data[i][0], // Kolom A
            password: data[i][1], // Kolom B (Sensitif)
            role: data[i][2],     // Kolom C
            kelas: data[i][3] || "" // Kolom D (Wali Kelas)
          });
        }
      }

      return { success: true, data: guruList };

    } catch (error) {
      // Jika verifyUser melempar error (Akses Ditolak), akan ditangkap disini
      return { success: false, message: error.message };
    }
  }

  function addGuru(token, username, password, kelas) { // Tambah param token
    try {
      verifyUser(token, 'admin'); // <-- SATPAM: Cek Token Admin

      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('users');
      const data = sheet.getDataRange().getValues();
      for (let i = 1; i < data.length; i++) {
        if (data[i][0] == username) {
          return { success: false, message: 'Username sudah terdaftar' };
        }
      }
      sheet.appendRow([username, password, 'guru', kelas]);
      return { success: true, message: 'Guru berhasil ditambahkan' };
    } catch (error) {
      return { success: false, message: "Akses Ditolak: " + error.message };
    }
  }

  function updateGuru(token, oldUsername, newUsername, password, kelas) { // Tambah param token
    try {
      verifyUser(token, 'admin'); // <-- SATPAM

      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('users');
      const data = sheet.getDataRange().getValues();
      
      for (let i = 1; i < data.length; i++) {
        if (data[i][0] == oldUsername && data[i][2] == 'guru') {
          sheet.getRange(i + 1, 1, 1, 4).setValues([[newUsername, password, 'guru', kelas]]);
          return { success: true, message: 'Guru berhasil diupdate' };
        }
      }
      return { success: false, message: 'Guru tidak ditemukan' };
    } catch (error) {
      return { success: false, message: "Akses Ditolak: " + error.message };
    }
  }

  function deleteGuru(token, username) { // Tambah param token
    try {
      verifyUser(token, 'admin'); // <-- SATPAM

      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('users');
      const data = sheet.getDataRange().getValues();
      
      for (let i = 1; i < data.length; i++) {
        if (data[i][0] == username && data[i][2] == 'guru') {
          sheet.deleteRow(i + 1);
          return { success: true, message: 'Guru berhasil dihapus' };
        }
      }
      return { success: false, message: 'Guru tidak ditemukan' };
    } catch (error) {
      return { success: false, message: "Akses Ditolak: " + error.message };
    }
  }
  // ====================================
  // ABSENSI OPERATIONS
  // ====================================
  function scanAbsensi(nisn, scannerRole, scannerKelas) {
    try {
      const ss = getSpreadsheet();
      const now = new Date();
      const today = Utilities.formatDate(now, 'Asia/Jakarta', 'yyyy-MM-dd');
      const nowTime = Utilities.formatDate(now, 'Asia/Jakarta', 'HH:mm');
      const nowMinutes = timeToMinutes(nowTime);
      
      // 1. AMBIL KONFIGURASI (Cepat via Script Cache)
      const cache = CacheService.getScriptCache();
      let configStr = cache.get('app_config_cache');
      let config;
      if (configStr) {
        try {
          config = JSON.parse(configStr);
        } catch (e) {
          config = null;
        }
      }
      if (!config) {
        const configResult = getAppConfig();
        config = configResult.success ? configResult.data : {
          jam_masuk_mulai: '06:00',
          jam_masuk_akhir: '07:15',
          jam_pulang_mulai: '15:00',
          jam_pulang_akhir: '17:00'
        };
        cache.put('app_config_cache', JSON.stringify(config), 21600); // 6 jam
      }

      const jamMasukMulai = (config && config.jam_masuk_mulai && String(config.jam_masuk_mulai).trim()) ? String(config.jam_masuk_mulai).trim() : '06:00';
      const jamMasukAkhir = (config && config.jam_masuk_akhir && String(config.jam_masuk_akhir).trim()) ? String(config.jam_masuk_akhir).trim() : '07:15';
      const jamPulangMulai = (config && config.jam_pulang_mulai && String(config.jam_pulang_mulai).trim()) ? String(config.jam_pulang_mulai).trim() : '15:00';
      const jamPulangAkhir = (config && config.jam_pulang_akhir && String(config.jam_pulang_akhir).trim()) ? String(config.jam_pulang_akhir).trim() : '17:00';

      const masukMulaiMin = timeToMinutes(jamMasukMulai);
      const masukAkhirMin = timeToMinutes(jamMasukAkhir);
      const pulangMulaiMin = timeToMinutes(jamPulangMulai);
      const pulangAkhirMin = timeToMinutes(jamPulangAkhir);

      // ==========================================
      // 2. VALIDASI BATAS OPERASIONAL ABSENSI
      // ==========================================
      // A. Blokir jika waktu scan SEBELUM jam buka absensi
      if (nowMinutes < masukMulaiMin) {
        return { 
          success: false, 
          isTimeRestricted: true,
          message: `Absensi Belum Dibuka! Jam buka absensi hari ini pukul ${jamMasukMulai} WIB (Saat ini pukul ${nowTime} WIB).` 
        };
      }

      // B. Blokir jika waktu scan SUDAH LEWAT batas akhir operasional absensi
      if (nowMinutes > pulangAkhirMin) {
        return { 
          success: false, 
          isTimeRestricted: true,
          message: `Absensi Ditutup! Batas akhir absensi hari ini (${jamPulangAkhir} WIB) sudah lewat (Saat ini pukul ${nowTime} WIB).` 
        };
      }

      // 3. CEK HARI LIBUR (Cepat via Cache per hari: Rutin Selasa/Jumat & Sheet 'hari_libur')
      let isLiburToday = cache.get('libur_' + today);
      if (isLiburToday === null) {
        const liburCheck = checkHariLibur(today);
        isLiburToday = liburCheck.isLibur ? liburCheck.keterangan : '';
        cache.put('libur_' + today, isLiburToday, 21600); // 6 jam
      }
      if (isLiburToday !== '') {
        return { success: false, message: 'Absensi DITUTUP. Hari ini libur: ' + isLiburToday };
      }

      const absensiSheet = ss.getSheetByName('absensi');
      const siswaSheet = ss.getSheetByName('siswa');
      
      // 4. VALIDASI INPUT
      const scannedNisn = String(nisn).replace(/^'/, '').trim();
      if (scannedNisn === "" || scannedNisn === "undefined") {
        return { success: false, message: 'QR Code tidak valid atau kosong.' };
      }

      // 5. CARI DATA SISWA
      const siswaData = siswaSheet.getDataRange().getValues();
      let siswa = null;
      
      for (let i = 1; i < siswaData.length; i++) {
        if (String(siswaData[i][1]).replace(/^'/, '').trim() === scannedNisn) {
          siswa = {
            nama: siswaData[i][0],
            nisn: siswaData[i][1],
            kelas: siswaData[i][8]
          };
          break;
        }
      }
      
      if (!siswa) {
        return { success: false, message: 'NISN tidak terdaftar di database.' };
      }

      // VALIDASI KELAS GURU
      if (scannerRole === 'guru') {
        const kelasSiswa = String(siswa.kelas).trim().toUpperCase();
        const kelasGuru = String(scannerKelas).trim().toUpperCase();
        if (kelasGuru && kelasSiswa !== kelasGuru) {
          return { 
            success: false, 
            message: `Ditolak! Siswa ini kelas ${siswa.kelas}. Anda hanya bisa scan kelas ${scannerKelas}.` 
          };
        }
      }

      // 6. PROSES ABSENSI: CARI DATA HARI INI
      const absensiData = absensiSheet.getDataRange().getValues();
      let foundRowIndex = -1;
      let jamDatangStr = '';
      let jamPulangStr = '';

      for (let i = absensiData.length - 1; i >= 1; i--) {
        const rowDateCell = absensiData[i][0];
        if (!rowDateCell) continue;

        let rowDateStr = '';
        try {
          rowDateStr = Utilities.formatDate(new Date(rowDateCell), 'Asia/Jakarta', 'yyyy-MM-dd');
        } catch (e) {
          continue;
        }

        const rowNisn = String(absensiData[i][1]).replace(/^'/, '').trim();

        if (rowDateStr === today && rowNisn === scannedNisn) {
          foundRowIndex = i;
          
          let jamDatangRaw = absensiData[i][4];
          jamDatangStr = (jamDatangRaw instanceof Date) ? 
              Utilities.formatDate(jamDatangRaw, 'Asia/Jakarta', 'HH:mm') : 
              String(jamDatangRaw || '').substring(0, 5);

          let jamPulangRaw = absensiData[i][5];
          jamPulangStr = (jamPulangRaw instanceof Date) ?
              Utilities.formatDate(jamPulangRaw, 'Asia/Jakarta', 'HH:mm') : 
              String(jamPulangRaw || '').substring(0, 5);
          break;
        }
      }

      // ==========================================
      // KASUS 1: SISWA SUDAH TERDAFTAR HARI INI
      // ==========================================
      if (foundRowIndex !== -1) {
        // 1. Jika sudah checkout / pulang sebelumnya
        if (absensiData[foundRowIndex][5]) { 
          return { 
            success: false, 
            alreadyScanned: true,
            type: 'sudah_pulang',
            nama: siswa.nama,
            kelas: siswa.kelas,
            jam: jamPulangStr,
            message: `Siswa ${siswa.nama} (${siswa.kelas}) sudah absen pulang hari ini (Pukul ${jamPulangStr}).` 
          };
        }

        // 2. Jika belum masuk jam pulang, tolak scan pulang dan beri info jelas
        if (nowMinutes < pulangMulaiMin) {
          return {
            success: false,
            alreadyScanned: true,
            type: 'sudah_hadir',
            nama: siswa.nama,
            kelas: siswa.kelas,
            jam: jamDatangStr,
            message: `Siswa ${siswa.nama} sudah hadir (Pukul ${jamDatangStr}). Absen pulang belum dibuka (Buka pukul ${jamPulangMulai} WIB).`
          };
        }

        // 3. Jika sudah masuk jam pulang (nowMinutes >= pulangMulaiMin)
        // Cek jeda waktu jika baru saja scan masuk di jam pulang (anti double-scan)
        const minutesDiff = calculateTimeDiff(jamDatangStr, nowTime);
        if (minutesDiff < 1) {
          return { 
            success: false, 
            alreadyScanned: true,
            type: 'sudah_hadir',
            nama: siswa.nama,
            kelas: siswa.kelas,
            jam: jamDatangStr,
            message: `Siswa ${siswa.nama} (${siswa.kelas}) baru saja scan presensi masuk (Pukul ${jamDatangStr}). Tunggu sebentar.` 
          };
        }

        // UPDATE KE SHEET: ABSEN PULANG
        const jamPulang = Utilities.formatDate(now, 'Asia/Jakarta', 'HH:mm:ss');
        let ketSaatIni = absensiData[foundRowIndex][6] || ''; 
        absensiSheet.getRange(foundRowIndex + 1, 6).setValue(jamPulang);
        absensiSheet.getRange(foundRowIndex + 1, 7).setValue(ketSaatIni);
        
        return {
          success: true,
          message: 'Absen Pulang Berhasil',
          type: 'pulang',
          jamPulang: jamPulang,
          nama: siswa.nama,
          kelas: siswa.kelas,
          status: 'Hadir'
        };
      }

      // ==========================================
      // KASUS 2: SISWA BELUM ADA REKOR HARI INI
      // ==========================================
      // A. Jika waktu scan sudah masuk jam pulang (nowMinutes >= pulangMulaiMin)
      if (nowMinutes >= pulangMulaiMin) {
        const jamPulang = Utilities.formatDate(now, 'Asia/Jakarta', 'HH:mm:ss');
        absensiSheet.appendRow([
          new Date(),        
          "'" + scannedNisn, 
          siswa.nama,        
          siswa.kelas,       
          '-',                  // Jam Datang tidak ada / lewat
          jamPulang,            // Jam Pulang
          'Hanya Absen Pulang', // Kolom G: Keterangan
          'Hadir'               // Kolom H: Status Kehadiran
        ]);

        return {
          success: true,
          message: 'Absen Pulang Berhasil (Tidak Scan Masuk)',
          type: 'pulang',
          jamPulang: jamPulang,
          nama: siswa.nama,
          kelas: siswa.kelas,
          status: 'Hadir'
        };
      }

      // B. Waktu scan berada di jam masuk (masukMulaiMin <= nowMinutes < pulangMulaiMin)
      let keteranganWaktu = 'Tepat Waktu'; // Default
      let statusKehadiran = 'Hadir';       // Default

      if (nowMinutes > masukAkhirMin) {
        const lateMinutes = nowMinutes - masukAkhirMin;
        keteranganWaktu = `Terlambat (${lateMinutes} m)`;
      }

      const jamDatang = Utilities.formatDate(now, 'Asia/Jakarta', 'HH:mm:ss');
      
      // INSERT DATA BARU (APPEND)
      // Urutan: [Tanggal, NISN, Nama, Kelas, JamDatang, JamPulang, Keterangan, Status]
      absensiSheet.appendRow([
        new Date(),        
        "'" + scannedNisn, 
        siswa.nama,        
        siswa.kelas,       
        jamDatang,         
        '',                // Jam Pulang kosong
        keteranganWaktu,   // Kolom G: Keterangan Waktu
        statusKehadiran    // Kolom H: Status Kehadiran
      ]);

      let responseMessage = 'Absen Masuk Berhasil';
      if (keteranganWaktu.includes('Terlambat')) {
        responseMessage = `Absen Masuk (${keteranganWaktu})`;
      }

      return {
        success: true,
        message: responseMessage,
        type: 'datang',
        jamDatang: jamDatang,
        nama: siswa.nama,
        kelas: siswa.kelas,
        status: statusKehadiran
      };

    } catch (error) {
      return { success: false, message: "Error Server: " + error.toString() };
    }
  }

  // Pastikan helper ini ada di paling bawah file code.gs
  function calculateTimeDiff(startTime, endTime) {
    const [h1, m1] = startTime.split(':').map(Number);
    const [h2, m2] = endTime.split(':').map(Number);
    
    const totalMinutes1 = h1 * 60 + m1;
    const totalMinutes2 = h2 * 60 + m2;
    
    return totalMinutes2 - totalMinutes1;
  }

  function getAbsensiToday(nisn) {
    try {
      const ss = getSpreadsheet();
      const todayStr = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');

      // --- CEK APAKAH HARI INI LIBUR? ---
      const libCheck = checkHariLibur(todayStr);
      let isLibur = libCheck.isLibur;
      let keteranganLibur = libCheck.keterangan;
      // ----------------------------------

      const sheet = ss.getSheetByName('absensi');
      const data = sheet.getDataRange().getValues();
      const searchNisn = String(nisn).trim();

      let absensiData = null;
      
      // Loop cari data absen siswa hari ini
      for (let i = 1; i < data.length; i++) {
        const rowDateCell = data[i][0];
        if (!rowDateCell) continue;
        
        const rowDateStr = Utilities.formatDate(new Date(rowDateCell), 'Asia/Jakarta', 'yyyy-MM-dd');
        const rowNisn = String(data[i][1]).trim();

        if (rowDateStr === todayStr && rowNisn === searchNisn) {
          // Format Jam Datang
          let jamDatang = data[i][4];
          if (jamDatang instanceof Date) {
            jamDatang = Utilities.formatDate(jamDatang, 'Asia/Jakarta', 'HH:mm:ss');
          }
          
          // Format Jam Pulang
          let jamPulang = data[i][5];
          if (jamPulang instanceof Date) {
            jamPulang = Utilities.formatDate(jamPulang, 'Asia/Jakarta', 'HH:mm:ss');
          } else if (!jamPulang) {
            jamPulang = ""; // Pastikan string kosong jika belum pulang
          }

          absensiData = {
            tanggal: rowDateStr,
            jamDatang: jamDatang,
            jamPulang: jamPulang,
            status: data[i][6]
          };
          break; 
        }
      }

      // Kembalikan data absen BESERTA status libur
      return { 
        success: true, 
        data: absensiData,
        isLibur: isLibur,
        keteranganLibur: keteranganLibur
      };

    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }

  // ====================================
  // GANTI FUNCTION getAbsensiList LAMA DENGAN INI
  // ====================================
  function getAbsensiList(filter = {}) {
    try {
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('absensi');
      const data = sheet.getDataRange().getValues();
      const absensiList = [];
      
      const fStart = filter.tanggalMulai || "";
      const fEnd = filter.tanggalAkhir || "";
      const fKelas = filter.kelas || ""; // Filter Kelas

      // Loop data (Mulai dari baris ke-1, lewati header)
      for (let i = 1; i < data.length; i++) {
        if (data[i][0]) {
          
          let rawDate = new Date(data[i][0]);
          let tanggalStr = Utilities.formatDate(rawDate, 'Asia/Jakarta', 'yyyy-MM-dd');

          // Format Jam
          let jamDatangStr = data[i][4];
          if (data[i][4] instanceof Date) {
              jamDatangStr = Utilities.formatDate(data[i][4], 'Asia/Jakarta', 'HH:mm:ss');
          }

          let jamPulangStr = data[i][5];
          if (data[i][5] && data[i][5] instanceof Date) {
              jamPulangStr = Utilities.formatDate(data[i][5], 'Asia/Jakarta', 'HH:mm:ss');
          } else if (!jamPulangStr) {
              jamPulangStr = "-";
          }
          
          // Mapping Data (SESUAI STRUKTUR 8 KOLOM)
          const item = {
            tanggal: tanggalStr, 
            nisn: data[i][1],
            nama: data[i][2],
            kelas: data[i][3],
            jamDatang: jamDatangStr,
            jamPulang: jamPulangStr,
            keterangan: data[i][6], // Kolom G: Keterangan Waktu
            status: data[i][7]      // Kolom H: Status Kehadiran
          };

          // Logika Filter
          let match = true;
          if (fStart && tanggalStr < fStart) match = false;
          if (fEnd && tanggalStr > fEnd) match = false;
          if (filter.nama && !String(item.nama).toLowerCase().includes(filter.nama.toLowerCase())) match = false;
          if (fKelas && item.kelas != fKelas) match = false;

          if (match) {
            absensiList.push(item);
          }
        }
      }
      
      // Urutkan data dari yang terbaru
      absensiList.sort((a, b) => b.tanggal.localeCompare(a.tanggal));

      return { success: true, data: absensiList };
    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }

  function getKelasList() {
    try {
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('siswa');
      const data = sheet.getDataRange().getValues();
      const kelasSet = new Set();
      
      for (let i = 1; i < data.length; i++) {
        if (data[i][8]) {
          kelasSet.add(data[i][8]);
        }
      }
      
      return { success: true, data: Array.from(kelasSet).sort() };
    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }

  // ====================================
  // SETUP INITIAL DATA
  // ====================================
  // ====================================
  // SETUP INITIAL DATA (DATABASE)
  // ====================================
  function setupInitialData() {
    try {
      const ss = getSpreadsheet();

      // 1. Setup sheet 'users' (Admin & Guru)
      let usersSheet = ss.getSheetByName('users');
      if (!usersSheet) {
        usersSheet = ss.insertSheet('users');
        // Header: Username, Password, Role, Kelas (Wajib ada kolom Kelas untuk Guru)
        usersSheet.appendRow(['Username', 'Password', 'Role', 'Kelas']);
        
        // Data Default Admin
        usersSheet.appendRow(['admin', 'admin123', 'admin', '']);
        // Data Default Guru (Contoh Wali Kelas)
        usersSheet.appendRow(['guru1', 'guru123', 'guru', 'JILID 1']);
        usersSheet.appendRow(['guru1', 'guru123', 'guru', 'JILID 2']);
        usersSheet.appendRow(['guru1', 'guru123', 'guru', 'JILID 3']);
        usersSheet.appendRow(['guru1', 'guru123', 'guru', 'JILID 4']);
        usersSheet.appendRow(['guru1', 'guru123', 'guru', 'JILID ALQURAN']);
      }

      // 2. Setup sheet 'siswa'
      let siswaSheet = ss.getSheetByName('siswa');
      if (!siswaSheet) {
        siswaSheet = ss.insertSheet('siswa');
        siswaSheet.appendRow([
          'Nama Lengkap', 
          'NISN', 
          'Jenis Kelamin', 
          'Tanggal Lahir', 
          'Agama',
          'Nama Ayah', 
          'Nama Ibu', 
          'No Handphone', 
          'Kelas', 
          'Alamat'
        ]);
        // Sample Data Siswa
        siswaSheet.appendRow([
          'Ahmad Rizki', 
          '1234567890', 
          'Laki-laki', 
          '2008-05-15', 
          'Islam',
          'Budi Santoso', 
          'Siti Aminah', 
          '081234567890', 
          'VI B',
          'Jl. Merdeka No. 10, Bengkulu'
        ]);
      }

      // 3. Setup sheet 'absensi' (DIPERBARUI: 8 KOLOM)
      let absensiSheet = ss.getSheetByName('absensi');
      if (!absensiSheet) {
        absensiSheet = ss.insertSheet('absensi');
        // Perubahan struktur header:
        // Kolom G (Index 7) = Keterangan Waktu (Terlambat/Pulang Cepat)
        // Kolom H (Index 8) = Status (Hadir/Sakit/Izin/Alpa)
        absensiSheet.appendRow([
          'Tanggal', 
          'NISN', 
          'Nama', 
          'Kelas', 
          'Jam Datang', 
          'Jam Pulang', 
          'Keterangan Waktu', 
          'Status'
        ]);
      }

      // 4. Setup sheet 'hari_libur'
      let liburSheet = ss.getSheetByName('hari_libur');
      if (!liburSheet) {
        liburSheet = ss.insertSheet('hari_libur');
        liburSheet.appendRow(['Tanggal', 'Keterangan']);
      }

      // 5. Setup sheet 'konfigurasi'
      let configSheet = ss.getSheetByName('konfigurasi');
      if (!configSheet) {
        configSheet = ss.insertSheet('konfigurasi');
        configSheet.appendRow(['Key', 'Value', 'Keterangan']);
        
        // Default Config
        configSheet.appendRow(['jam_masuk_mulai', '06:00', 'Waktu absen datang dibuka']);
        configSheet.appendRow(['jam_masuk_akhir', '07:15', 'Batas waktu terlambat']);
        configSheet.appendRow(['jam_pulang_mulai', '15:00', 'Waktu absen pulang dibuka']);
        configSheet.appendRow(['jam_pulang_akhir', '17:00', 'Batas akhir absen pulang']);
      }
      
      return { success: true, message: 'Setup database berhasil. Struktur 8 kolom diterapkan pada sheet Absensi.' };
    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }
  // ====================================
  // KELOLA HARI LIBUR (BARU)
  // ====================================
  function getHariLibur() {
    try {
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('hari_libur');
      const data = sheet.getDataRange().getValues();
      const list = [];
      
      // Loop dari baris 1 (lewati header)
      for (let i = 1; i < data.length; i++) {
        if (data[i][0]) {
          let tgl = Utilities.formatDate(new Date(data[i][0]), 'Asia/Jakarta', 'yyyy-MM-dd');
          list.push({
            tanggal: tgl,
            keterangan: data[i][1]
          });
        }
      }
      // Urutkan tanggal descending
      list.sort((a, b) => b.tanggal.localeCompare(a.tanggal));
      return { success: true, data: list };
    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }

  /**
  * Tambah hari libur satu hari atau rentang tanggal (tanggalMulai sampai tanggalSelesai).
  * Mencegah duplikasi tanggal dan secara otomatis menulis seluruh tanggal dalam rentang ke sheet 'hari_libur'.
  */
  function addHariLibur(tanggalMulai, tanggalSelesai, keterangan) {
    try {
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('hari_libur');
      if (!sheet) return { success: false, message: 'Sheet hari_libur tidak ditemukan' };
      
      // Dukungan kompatibilitas jika dipanggil 2 parameter: addHariLibur(tanggal, keterangan)
      let tglStart = tanggalMulai;
      let tglEnd = tanggalSelesai;
      let ket = keterangan;
      
      if (arguments.length === 2 || !ket) {
        ket = tanggalSelesai;
        tglEnd = tanggalMulai;
      }
      if (!tglEnd) {
        tglEnd = tglStart;
      }

      tglStart = String(tglStart || '').trim();
      tglEnd   = String(tglEnd || '').trim();
      ket      = String(ket || 'Hari Libur').trim();

      if (!tglStart) {
        return { success: false, message: 'Tanggal mulai tidak boleh kosong' };
      }

      // Ambil daftar tanggal yang sudah terdaftar di sheet untuk mencegah duplikasi
      const data = sheet.getDataRange().getValues();
      const existingSet = new Set();
      for (let i = 1; i < data.length; i++) {
        if (data[i][0]) {
          let dStr = '';
          if (data[i][0] instanceof Date) {
            dStr = Utilities.formatDate(data[i][0], 'Asia/Jakarta', 'yyyy-MM-dd');
          } else {
            const raw = String(data[i][0]).trim();
            dStr = raw.substring(0, 10);
          }
          if (dStr) existingSet.add(dStr);
        }
      }

      // Validasi rentang tanggal
      const pStart = tglStart.split('-');
      const pEnd   = tglEnd.split('-');
      if (pStart.length !== 3 || pEnd.length !== 3) {
        return { success: false, message: 'Format tanggal harus yyyy-MM-dd' };
      }

      let dCur = new Date(parseInt(pStart[0], 10), parseInt(pStart[1], 10) - 1, parseInt(pStart[2], 10), 12, 0, 0);
      const dEnd = new Date(parseInt(pEnd[0], 10), parseInt(pEnd[1], 10) - 1, parseInt(pEnd[2], 10), 12, 0, 0);

      if (dEnd.getTime() < dCur.getTime()) {
        return { success: false, message: 'Tanggal berakhir tidak boleh lebih awal dari tanggal mulai' };
      }

      const rowsToAppend = [];
      let addedCount = 0;
      let loopSafety = 0;

      while (dCur.getTime() <= dEnd.getTime() && loopSafety < 366) {
        const ymd = Utilities.formatDate(dCur, 'Asia/Jakarta', 'yyyy-MM-dd');
        if (!existingSet.has(ymd)) {
          rowsToAppend.push([ymd, ket]);
          existingSet.add(ymd);
          addedCount++;
        }
        dCur.setDate(dCur.getDate() + 1);
        loopSafety++;
      }

      if (rowsToAppend.length > 0) {
        const lastRow = sheet.getLastRow();
        sheet.getRange(lastRow + 1, 1, rowsToAppend.length, 2).setValues(rowsToAppend);
      }

      // Bersihkan cache libur jika ada
      try {
        const cache = CacheService.getScriptCache();
        for (let r of rowsToAppend) {
          cache.remove('libur_' + r[0]);
        }
      } catch(e) {}

      let successMsg = '';
      if (addedCount === 1) {
        successMsg = `Hari libur (${tglStart}) berhasil ditambahkan.`;
      } else if (addedCount > 1) {
        successMsg = `Rentang libur (${tglStart} s/d ${tglEnd}, total ${addedCount} hari) berhasil ditambahkan.`;
      } else {
        successMsg = `Tanggal dalam rentang tersebut sudah terdaftar di daftar hari libur.`;
      }

      return { success: true, message: successMsg, count: addedCount };
    } catch (error) {
      console.error("Error addHariLibur:", error);
      return { success: false, message: error.toString() };
    }
  }

  function addHariLiburRange(tanggalMulai, tanggalSelesai, keterangan) {
    return addHariLibur(tanggalMulai, tanggalSelesai, keterangan);
  }

  function deleteHariLibur(tanggalStr) {
    try {
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('hari_libur');
      const data = sheet.getDataRange().getValues();
      
      for (let i = 1; i < data.length; i++) {
        let rowDate = Utilities.formatDate(new Date(data[i][0]), 'Asia/Jakarta', 'yyyy-MM-dd');
        if (rowDate === tanggalStr) {
          sheet.deleteRow(i + 1);
          return { success: true, message: 'Hari libur dihapus' };
        }
      }
      return { success: false, message: 'Data tidak ditemukan' };
    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }

  // ====================================
  // FITUR MONITORING & UPDATE STATUS (BARU)
  // ====================================

  // Function untuk mengambil data Monitoring (Versi Update: Pisah Terlambat & Pulang Cepat)
  function getMonitoringRealtime(filterKelas = null, dateVal = null) {
    try {
      const ss = getSpreadsheet();
      const targetDateStr = dateVal ? dateVal : Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');
      const siswaSheet = ss.getSheetByName('siswa');
      const dataSiswa = siswaSheet.getDataRange().getValues();
      const absensiSheet = ss.getSheetByName('absensi');
      const dataAbsensi = absensiSheet.getDataRange().getValues();
      
      // Mapping data absensi hari ini
      let absensiMap = {};
      for (let i = 1; i < dataAbsensi.length; i++) {
        let rowDate = dataAbsensi[i][0];
        if (!rowDate) continue; // Skip baris kosong

        let tgl = Utilities.formatDate(new Date(rowDate), 'Asia/Jakarta', 'yyyy-MM-dd');
        let nisn = String(dataAbsensi[i][1]).replace(/^'/, '').trim();
        
        if (tgl === targetDateStr) {
          absensiMap[nisn] = {
            jamDatang: dataAbsensi[i][4],
            jamPulang: dataAbsensi[i][5],
            keterangan: dataAbsensi[i][6], // Kolom G (Index 6) -> Terlambat/Tepat Waktu
            status: dataAbsensi[i][7]      // Kolom H (Index 7) -> Hadir/Sakit/Izin
          };
        }
      }

      let result = [];
      for (let i = 1; i < dataSiswa.length; i++) {
        let nama = dataSiswa[i][0];
        let nisn = String(dataSiswa[i][1]).replace(/^'/, '').trim();
        let kelas = dataSiswa[i][8];

        // Filter Kelas
        if (filterKelas && kelas !== filterKelas) continue;
        
        let statusInfo = absensiMap[nisn];
        
        // Default Value (Jika siswa belum absen)
        let jamDatang = '-';
        let jamPulang = '-';
        let displayStatus = 'Belum Absen'; 
        let keteranganWaktu = '-';         

        if (statusInfo) {
          // 1. Ambil Jam
          if (statusInfo.jamDatang instanceof Date) {
              jamDatang = Utilities.formatDate(statusInfo.jamDatang, 'Asia/Jakarta', 'HH:mm');
          } else if (statusInfo.jamDatang) jamDatang = String(statusInfo.jamDatang);

          if (statusInfo.jamPulang instanceof Date) {
              jamPulang = Utilities.formatDate(statusInfo.jamPulang, 'Asia/Jakarta', 'HH:mm');
          } else if (statusInfo.jamPulang) jamPulang = String(statusInfo.jamPulang);

          // 2. Ambil Status & Keterangan LANGSUNG DARI SHEET
          // Kita tidak mengubah logika di sini, kita percaya data di Sheet sudah benar
          let rawKet = statusInfo.keterangan; 
          let rawStat = statusInfo.status;

          displayStatus = (rawStat && String(rawStat).trim() !== "") ? String(rawStat) : "Belum Absen";

          // Logika tampilan Keterangan Waktu
          if (rawKet && String(rawKet).trim() !== "") {
              // Jika di sheet ada tulisan (misal: "Terlambat (900 m)"), tampilkan itu
              keteranganWaktu = String(rawKet);
          } else {
              // Jika kosong di sheet, tapi status Hadir, anggap Tepat Waktu
              if (displayStatus === 'Hadir') {
                  keteranganWaktu = 'Tepat Waktu';
              } else {
                  keteranganWaktu = '-';
              }
          }
        }

        result.push({
          nama: nama,
          nisn: nisn,
          kelas: kelas,
          jamDatang: jamDatang,
          jamPulang: jamPulang,
          status: displayStatus,       // Dropdown
          keterangan: keteranganWaktu  // Kolom Teks (Terlambat/Tepat Waktu)
        });
      }

      // Sort: Kelas dulu, baru Nama
      result.sort((a, b) => {
        if (a.kelas === b.kelas) return a.nama.localeCompare(b.nama);
        return a.kelas.localeCompare(b.kelas);
      });
      
      return { success: true, data: result };

    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }

  // 2. Update Status oleh Guru (Hadir/Izin/Sakit/Alpa)
  // [code.gs] - TIMPA FUNGSI LAMA

  function updateAbsensiStatus(token, nisn, nama, kelas, newStatus, dateVal = null) {
    try {
      // SATPAM: Hanya Guru atau Admin yang boleh ubah status manual
      verifyUser(token, 'guru'); 

      const ss = getSpreadsheet();
      const absensiSheet = ss.getSheetByName('absensi');
      const targetDateStr = dateVal ? dateVal : Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');
      const data = absensiSheet.getDataRange().getValues();
      
      let found = false;
      let rowIndex = -1;

      for (let i = 1; i < data.length; i++) {
        let tgl = Utilities.formatDate(new Date(data[i][0]), 'Asia/Jakarta', 'yyyy-MM-dd');
        let rowNisn = String(data[i][1]).replace(/^'/, '').trim();
        
        if (tgl === targetDateStr && rowNisn === String(nisn).replace(/^'/, '').trim()) {
          found = true;
          rowIndex = i + 1;
          break;
        }
      }

      if (found) {
        // Kolom 8 (Kolom H) adalah Kolom Status Kehadiran
        absensiSheet.getRange(rowIndex, 8).setValue(newStatus); 
      } else {
        let jamDatang = '-';
        let ketWaktu = '-';
        if (newStatus === 'Hadir') {
          jamDatang = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'HH:mm:ss');
          ketWaktu = 'Tepat Waktu';
        }
        
        let recordDate = dateVal ? new Date(dateVal) : new Date();
        // Struktur 8 Kolom: [Tanggal, NISN, Nama, Kelas, Jam Datang, Jam Pulang, Keterangan Waktu, Status]
        absensiSheet.appendRow([recordDate, "'" + String(nisn).replace(/^'/, ''), nama, kelas, jamDatang, '', ketWaktu, newStatus]);
      }

      return { success: true, message: 'Status berhasil diubah' };
    } catch (error) {
      return { success: false, message: "Gagal: " + error.message };
    }
  }

  function updateHariLibur(oldDateStr, newDateStr, newKeterangan) {
    try {
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('hari_libur');
      const data = sheet.getDataRange().getValues();
      
      let found = false;
      
      // Loop dari baris 1 (lewati header)
      for (let i = 1; i < data.length; i++) {
        // Format tanggal dari sheet agar sama dengan format string input (yyyy-MM-dd)
        let rowDate = Utilities.formatDate(new Date(data[i][0]), 'Asia/Jakarta', 'yyyy-MM-dd');
        
        if (rowDate === oldDateStr) {
          // Update baris: Kolom 1 (Tanggal), Kolom 2 (Keterangan)
          // Gunakan new Date() untuk kolom tanggal agar format di sheet tetap Date Object
          sheet.getRange(i + 1, 1, 1, 2).setValues([[new Date(newDateStr), newKeterangan]]);
          found = true;
          break;
        }
      }
      
      if (found) {
        return { success: true, message: 'Hari libur berhasil diperbarui' };
      } else {
        return { success: false, message: 'Data tanggal lama tidak ditemukan' };
      }
      
    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }

  // ====================================
  // FITUR EXPORT EXCEL
  // ====================================


  function getExportData(type, filters) {
    const ss = getSpreadsheet();
    
    // --- 1. LOGIKA UNTUK LAPORAN PERIODE (YANG SUDAH ADA) ---
    if (type === 'laporan_absensi') {
      const sheet = ss.getSheetByName('absensi');
      const data = sheet.getDataRange().getValues();
      const result = [];
      
      const fStart = filters.tanggalMulai || "";
      const fEnd = filters.tanggalAkhir || "";    const fKelas = filters.kelas || ""; 
      
      let no = 1;
      for (let i = 1; i < data.length; i++) {
        if (!data[i][0]) continue;
        let rawDate = new Date(data[i][0]);
        let tanggalStr = Utilities.formatDate(rawDate, 'Asia/Jakarta', 'dd-MM-yyyy');
        let dateForFilter = Utilities.formatDate(rawDate, 'Asia/Jakarta', 'yyyy-MM-dd');
        let rowKelas = data[i][3];

        let match = true;
        if (fStart && dateForFilter < fStart) match = false;
        if (fEnd && dateForFilter > fEnd) match = false;
        if (fKelas && rowKelas != fKelas) match = false;

        if (match) {
          let jamDatang = data[i][4];
          if (data[i][4] instanceof Date) {
              jamDatang = Utilities.formatDate(data[i][4], 'Asia/Jakarta', 'HH:mm:ss');
          }
          let jamPulang = data[i][5];
          if (data[i][5] && data[i][5] instanceof Date) {
              jamPulang = Utilities.formatDate(data[i][5], 'Asia/Jakarta', 'HH:mm:ss');
          }

          result.push([
            no++,                        
            tanggalStr,             
            "'" + data[i][1], 
            data[i][2],                  
            rowKelas,                    
            jamDatang,             
            jamPulang || '-',            
            data[i][6],                  
            data[i][7]                   
          ]);
        }
      }
      return result;
    }
    
    // --- 2. LOGIKA BARU: UNTUK MONITORING (REALTIME HARI INI) ---
    else if (type === 'monitoring') {
      // Kita gunakan ulang fungsi getMonitoringRealtime agar datanya konsisten
      // filters.kelas bisa dikirim jika yang request adalah Guru Wali Kelas
      const realtimeData = getMonitoringRealtime(filters.kelas);
      
      if (!realtimeData.success) return [];
      
      const data = realtimeData.data; // Array object hasil monitoring
      const result = [];
      
      // Mapping dari JSON Object ke Array Row Excel
      // Header nanti: No, Nama Siswa, NISN, Kelas, Jam Datang, Jam Pulang, Keterangan, Status
      data.forEach((item, index) => {
        result.push([
          index + 1,
          item.nama,
          "'" + item.nisn, // Pakai kutip agar tidak jadi scientific number
          item.kelas,
          item.jamDatang,
          item.jamPulang,
          item.keterangan, // Terlambat / Tepat Waktu
          item.status      // Hadir / Sakit / Izin / Alpa / Belum Absen
        ]);
      });
      
      return result;
    }
    
    return [];
  }

  function getColumnLetter(colIndex) {
    let letter = "";
    while (colIndex > 0) {
      let temp = (colIndex - 1) % 26;
      letter = String.fromCharCode(65 + temp) + letter;
      colIndex = Math.floor((colIndex - temp - 1) / 26);
    }
    return letter;
  }

  function generateMatrixLaporanExcel(filters) {
    const ssApp = getSpreadsheet();
    const timestamp = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'dd-MM-yyyy HHmm');
    const fileName = "Daftar Hadir Santri TPQ - " + timestamp;
    
    const ss = SpreadsheetApp.create(fileName);
    const sheet = ss.getActiveSheet();
    sheet.setHiddenGridlines(false);

    let reqDate = new Date();
    if (filters && filters.tanggalMulai) {
      let parts = filters.tanggalMulai.split('-');
      if (parts.length === 3) {
        reqDate = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, 1);
      }
    }
    
    const year = reqDate.getFullYear();
    const monthIdx = reqDate.getMonth();
    const numDays = new Date(year, monthIdx + 1, 0).getDate();
    
    const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const monthStr = monthNames[monthIdx] + " " + year;
    const kelasStr = (filters && filters.kelas && String(filters.kelas).trim() !== '') ? filters.kelas : 'SEMUA KELAS';

    const totalCols = 2 + numDays + 5; // NO, NAMA, 1..numDays, H, S, I, A, Jml

    // --- 1. HEADER TITLE BLOCK ---
    sheet.getRange(1, 1, 1, totalCols).merge().setValue("DAFTAR HADIR SANTRI TPQ NURUL MU'IM")
        .setFontWeight('bold').setFontSize(14).setHorizontalAlignment('center').setVerticalAlignment('middle');
    sheet.setRowHeight(1, 25);

    const academicYearStr = "TAHUN AJARAN " + (monthIdx >= 6 ? (year + "/" + (year + 1)) : ((year - 1) + "/" + year));
    sheet.getRange(2, 1, 1, totalCols).merge().setValue(academicYearStr)
        .setFontWeight('bold').setFontSize(11).setHorizontalAlignment('center').setVerticalAlignment('middle');
    sheet.setRowHeight(2, 20);

    sheet.getRange(4, 1).setValue("JILID").setFontWeight('bold');
    sheet.getRange(4, 2).setValue(": " + kelasStr);

    sheet.getRange(5, 1).setValue("Bulan").setFontWeight('bold');
    sheet.getRange(5, 2).setValue(": " + monthStr);

    // --- 2. TABLE HEADER (ROW 7 & 8) ---
    sheet.getRange(7, 1, 2, 1).merge().setValue("NO").setFontWeight('bold')
        .setHorizontalAlignment('center').setVerticalAlignment('middle').setBackground('#FFFFFF');
    
    sheet.getRange(7, 2, 2, 1).merge().setValue("NAMA").setFontWeight('bold')
        .setHorizontalAlignment('center').setVerticalAlignment('middle').setBackground('#FFFFFF');

    sheet.getRange(7, 3, 1, numDays).merge().setValue("Tanggal").setFontWeight('bold')
        .setHorizontalAlignment('center').setVerticalAlignment('middle').setBackground('#FFFFFF');

    const dateSubheaders = [];
    for (let d = 1; d <= numDays; d++) {
      dateSubheaders.push(d);
    }
    sheet.getRange(8, 3, 1, numDays).setValues([dateSubheaders]).setFontWeight('bold')
        .setHorizontalAlignment('center').setVerticalAlignment('middle').setBackground('#FFFFFF');

    const summaryCols = ["H", "S", "I", "A", "Jml"];
    for (let s = 0; s < summaryCols.length; s++) {
      const cIdx = 3 + numDays + s;
      sheet.getRange(7, cIdx, 2, 1).merge().setValue(summaryCols[s]).setFontWeight('bold')
          .setHorizontalAlignment('center').setVerticalAlignment('middle').setBackground('#FFFFFF');
    }

    sheet.setRowHeight(7, 22);
    sheet.setRowHeight(8, 22);

    // --- 3. FETCH STUDENT & ATTENDANCE DATA ---
    const siswaSheet = ssApp.getSheetByName('siswa');
    const siswaData = siswaSheet.getDataRange().getValues();
    let students = [];
    for (let i = 1; i < siswaData.length; i++) {
      let nama = siswaData[i][0];
      let nisn = String(siswaData[i][1]).trim();
      let kelas = siswaData[i][8];
      if (kelasStr !== 'SEMUA KELAS' && kelas !== kelasStr) continue;
      if (nama && nisn) {
        students.push({ nama: nama, nisn: nisn, kelas: kelas });
      }
    }
    students.sort((a, b) => a.kelas === b.kelas ? a.nama.localeCompare(b.nama) : a.kelas.localeCompare(b.kelas));

    const absensiSheet = ssApp.getSheetByName('absensi');
    const absensiData = absensiSheet.getDataRange().getValues();
    let attMap = {};
    for (let i = 1; i < absensiData.length; i++) {
      let rDate = absensiData[i][0];
      if (!rDate) continue;
      let dObj = new Date(rDate);
      if (dObj.getFullYear() === year && dObj.getMonth() === monthIdx) {
        let rNisn = String(absensiData[i][1]).trim();
        let day = dObj.getDate();
        let status = absensiData[i][7] || absensiData[i][6];
        attMap[rNisn + '_' + day] = status;
      }
    }

    // --- 4. BUILD DATA ROWS ---
    const startDataRow = 9;
    const dataTable = [];

    const isHolidayDay = [];
    for (let d = 1; d <= numDays; d++) {
      let checkD = new Date(year, monthIdx, d);
      let dow = checkD.getDay(); // 2 = Selasa, 5 = Jumat
      isHolidayDay[d] = (dow === 2 || dow === 5);
    }

    for (let idx = 0; idx < students.length; idx++) {
      const st = students[idx];
      const rowValues = [idx + 1, st.nama];

      for (let d = 1; d <= numDays; d++) {
        if (isHolidayDay[d]) {
          rowValues.push('L');
        } else {
          let status = attMap[st.nisn + '_' + d];
          if (status === 'Hadir') rowValues.push('H');
          else if (status === 'Sakit') rowValues.push('S');
          else if (status === 'Izin') rowValues.push('I');
          else if (status === 'Alpa') rowValues.push('A');
          else rowValues.push('');
        }
      }

      const rNum = startDataRow + idx;
      const startColLetter = 'C';
      const endColLetter = getColumnLetter(2 + numDays);
      
      rowValues.push(`=COUNTIF(${startColLetter}${rNum}:${endColLetter}${rNum}, "H")`);
      rowValues.push(`=COUNTIF(${startColLetter}${rNum}:${endColLetter}${rNum}, "S")`);
      rowValues.push(`=COUNTIF(${startColLetter}${rNum}:${endColLetter}${rNum}, "I")`);
      rowValues.push(`=COUNTIF(${startColLetter}${rNum}:${endColLetter}${rNum}, "A")`);
      
      const hColLetter = getColumnLetter(3 + numDays);
      const aColLetter = getColumnLetter(6 + numDays);
      rowValues.push(`=SUM(${hColLetter}${rNum}:${aColLetter}${rNum})`);

      dataTable.push(rowValues);
    }

    if (dataTable.length > 0) {
      const dataRange = sheet.getRange(startDataRow, 1, dataTable.length, totalCols);
      dataRange.setValues(dataTable);
      dataRange.setFontFamily('Arial').setFontSize(10).setVerticalAlignment('middle');
      
      sheet.getRange(startDataRow, 1, dataTable.length, 1).setHorizontalAlignment('center');
      sheet.getRange(startDataRow, 2, dataTable.length, 1).setHorizontalAlignment('left');
      sheet.getRange(startDataRow, 3, dataTable.length, totalCols - 2).setHorizontalAlignment('center');

      const entireTableRange = sheet.getRange(7, 1, 2 + dataTable.length, totalCols);
      entireTableRange.setBorder(true, true, true, true, true, true, '#000000', SpreadsheetApp.BorderStyle.SOLID);
    } else {
      sheet.getRange(9, 1, 1, totalCols).merge().setValue("Tidak ada data santri ditemukan").setHorizontalAlignment('center');
    }

    sheet.setColumnWidth(1, 35);
    sheet.setColumnWidth(2, 200);
    for (let d = 1; d <= numDays; d++) {
      sheet.setColumnWidth(2 + d, 28);
    }
    for (let s = 0; s < 5; s++) {
      sheet.setColumnWidth(3 + numDays + s, 38);
    }

    const fileId = ss.getId();
    const file = DriveApp.getFileById(fileId);
    file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
    return { success: true, url: "https://docs.google.com/spreadsheets/d/" + fileId + "/export?format=xlsx" };
  }

  function generateExcel(type, filters) {
    try {
      if (type === 'laporan_absensi') {
        return generateMatrixLaporanExcel(filters);
      }

      var timestamp = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'dd-MM-yyyy HHmm');
      var fileName = "Monitoring Harian - " + timestamp;
      var headers = ["No", "Nama Siswa", "NISN", "Kelas", "Jam Datang", "Jam Pulang", "Keterangan Waktu", "Status Terkini"];

      var ss = SpreadsheetApp.create(fileName);
      var sheet = ss.getActiveSheet();
      var data = getExportData(type, filters);

      var headerRange = sheet.getRange(1, 1, 1, headers.length);
      headerRange.setValues([headers]);
      headerRange.setFontWeight('bold').setFontColor('#FFFFFF').setBackground('#4F46E5')
        .setHorizontalAlignment('center').setVerticalAlignment('middle').setFontFamily('Roboto').setFontSize(11);
      sheet.setRowHeight(1, 45); 
      
      if (data && data.length > 0) {
        var numRows = data.length;
        var numCols = headers.length;
        var startRow = 2;
        
        var cleanData = data.map(function(row) { return row; });
        var dataRange = sheet.getRange(startRow, 1, numRows, numCols);
        dataRange.setValues(cleanData);
        dataRange.setFontFamily('Roboto').setFontSize(10).setVerticalAlignment('middle').setHorizontalAlignment('center');
        sheet.setRowHeights(startRow, numRows, 30); 
        
        var namaColIndex = headers.findIndex(h => h.includes("Nama"));
        if (namaColIndex > -1) {
            sheet.getRange(startRow, namaColIndex + 1, numRows, 1).setHorizontalAlignment('left');
        }

        dataRange.setBorder(true, true, true, true, true, true, '#E2E8F0', SpreadsheetApp.BorderStyle.SOLID);
        for (var i = 0; i < numRows; i++) {
          if (i % 2 === 1) { 
            sheet.getRange(startRow + i, 1, 1, numCols).setBackground('#F8FAFC');
          }
        }

        var statusColIndex = headers.length; 
        var ketColIndex = headers.length - 1;
        var statusRange = sheet.getRange(startRow, statusColIndex, numRows, 1);
        var ketRange = sheet.getRange(startRow, ketColIndex, numRows, 1);
        
        var rules = sheet.getConditionalFormatRules();
        rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("Hadir").setBackground("#DCFCE7").setFontColor("#166534").setBold(true).setRanges([statusRange]).build());
        rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("Sakit").setBackground("#FEF9C3").setFontColor("#854D0E").setBold(true).setRanges([statusRange]).build());
        rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("Izin").setBackground("#DBEAFE").setFontColor("#1E40AF").setBold(true).setRanges([statusRange]).build());
        rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("Alpa").setBackground("#FEE2E2").setFontColor("#991B1B").setBold(true).setRanges([statusRange]).build());
        rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("Belum Absen").setBackground("#F3F4F6").setFontColor("#6B7280").setItalic(true).setRanges([statusRange]).build());
        rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextContains("Terlambat").setFontColor("#DC2626").setBold(true).setRanges([ketRange]).build());
        rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextContains("Pulang Cepat").setFontColor("#C2410C").setBold(true).setRanges([ketRange]).build());
        sheet.setConditionalFormatRules(rules);
      }
      
      sheet.autoResizeColumns(1, headers.length);
      for(var c=1; c<=headers.length; c++) {
        var w = sheet.getColumnWidth(c);
        sheet.setColumnWidth(c, w + 20); 
      }
      
      sheet.setFrozenRows(1);
      sheet.setHiddenGridlines(true);

      var fileId = ss.getId();
      var file = DriveApp.getFileById(fileId);
      file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
      return { success: true, url: "https://docs.google.com/spreadsheets/d/" + fileId + "/export?format=xlsx" };

    } catch (e) {
      return { success: false, message: 'Gagal generate Excel: ' + e.toString() };
    }
  }

  function getAppConfig() {
    try {
      const ss = getSpreadsheet();
      let sheet = ss.getSheetByName('konfigurasi');
      // Default config jika sheet belum ada/kosong
      let config = {
        jam_masuk_mulai: '06:00',
        jam_masuk_akhir: '07:15',
        jam_pulang_mulai: '15:00',
        jam_pulang_akhir: '17:00'
      };
      
      if (!sheet) {
        sheet = ss.insertSheet('konfigurasi');
        sheet.appendRow(['Key', 'Value', 'Keterangan']);
        sheet.appendRow(['jam_masuk_mulai', '06:00', 'Waktu absen datang dibuka']);
        sheet.appendRow(['jam_masuk_akhir', '07:15', 'Batas waktu terlambat']);
        sheet.appendRow(['jam_pulang_mulai', '15:00', 'Waktu absen pulang dibuka']);
        sheet.appendRow(['jam_pulang_akhir', '17:00', 'Batas akhir absen pulang']);
        return { success: true, data: config };
      }

      const data = sheet.getDataRange().getValues();
      for (let i = 1; i < data.length; i++) {
        const key = String(data[i][0]).trim();
        const val = data[i][1];
        if (config.hasOwnProperty(key) && val !== undefined && val !== null && String(val).trim() !== '') {
          if (val instanceof Date) {
            config[key] = Utilities.formatDate(val, 'Asia/Jakarta', 'HH:mm');
          } else {
            let str = String(val).trim().replace(/^'/, '');
            if (/^\d:\d\d$/.test(str)) {
              str = '0' + str;
            }
            config[key] = str;
          }
        }
      }
      return { success: true, data: config };
    } catch (e) {
      return { success: false, message: e.toString() };
    }
  }

  // 3. Simpan Konfigurasi dari Frontend & Sinkronkan Cache Realtime
  function saveAppConfig(newConfig) {
    try {
      const ss = getSpreadsheet();
      let sheet = ss.getSheetByName('konfigurasi');
      if (!sheet) {
        sheet = ss.insertSheet('konfigurasi');
        sheet.appendRow(['Key', 'Value', 'Keterangan']);
      }
      
      const data = sheet.getDataRange().getValues();
      
      // Helper untuk update atau insert jika belum ada barisnya
      const updateOrInsert = (key, val, desc) => {
        let formattedVal = String(val || '').trim();
        if (/^\d:\d\d$/.test(formattedVal)) {
          formattedVal = '0' + formattedVal;
        }
        for (let i = 1; i < data.length; i++) {
          if (String(data[i][0]).trim() === key) {
            sheet.getRange(i + 1, 2).setValue("'" + formattedVal); 
            return;
          }
        }
        sheet.appendRow([key, "'" + formattedVal, desc || '']);
      };

      updateOrInsert('jam_masuk_mulai', newConfig.jam_masuk_mulai, 'Waktu absen datang dibuka');
      updateOrInsert('jam_masuk_akhir', newConfig.jam_masuk_akhir, 'Batas waktu terlambat');
      updateOrInsert('jam_pulang_mulai', newConfig.jam_pulang_mulai, 'Waktu absen pulang dibuka');
      updateOrInsert('jam_pulang_akhir', newConfig.jam_pulang_akhir, 'Batas akhir absen pulang');

      // SINKRONISASI CACHE INSTAN (Hapus cache lama & isi dengan config baru agar langsung aktif di scanner)
      const cache = CacheService.getScriptCache();
      cache.remove('app_config_cache');
      const cleanConfig = {
        jam_masuk_mulai: String(newConfig.jam_masuk_mulai || '06:00').trim(),
        jam_masuk_akhir: String(newConfig.jam_masuk_akhir || '07:15').trim(),
        jam_pulang_mulai: String(newConfig.jam_pulang_mulai || '15:00').trim(),
        jam_pulang_akhir: String(newConfig.jam_pulang_akhir || '17:00').trim()
      };
      cache.put('app_config_cache', JSON.stringify(cleanConfig), 21600); // 6 jam

      return { success: true, message: 'Konfigurasi waktu berhasil disimpan dan disinkronkan' };
    } catch (e) {
      return { success: false, message: e.toString() };
    }
  }

  // Helper: Konversi waktu (HH:mm atau H:mm) ke total menit dari jam 00:00
  function timeToMinutes(timeStr) {
    if (!timeStr) return 0;
    const parts = String(timeStr).trim().split(':');
    const h = parseInt(parts[0], 10) || 0;
    const m = parseInt(parts[1], 10) || 0;
    return h * 60 + m;
  }

  // Helper: Menghitung selisih menit antara dua waktu (HH:mm)
  function calculateTimeDiff(startTime, endTime) {
    return timeToMinutes(endTime) - timeToMinutes(startTime);
  }

  // ====================================
  // IMPORT SISWA DARI EXCEL (BULK)
  // ====================================
  function importSiswaBulk(dataArray) {
    try {
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('siswa');
      const existingData = sheet.getDataRange().getValues();
      
      // 1. Ambil daftar NISN yang sudah ada untuk cek duplikasi
      const existingNISN = new Set();
      for (let i = 1; i < existingData.length; i++) {
        existingNISN.add(String(existingData[i][1]).trim());
      }

      const rowsToAdd = [];
      let addedCount = 0;
      let skippedCount = 0;

      // 2. Loop data import
      for (let i = 0; i < dataArray.length; i++) {
        const item = dataArray[i];
        const nisn = String(item.nisn).trim();

        // Validasi dasar
        if (!item.nama || !nisn) {
          skippedCount++;
          continue;
        }

        // Cek Duplikasi NISN
        if (existingNISN.has(nisn)) {
          skippedCount++;
          continue;
        }

        // Format Tanggal Lahir (Jika Excel mengirim format angka tanggal)
        // SheetJS kadang mengirim string, kadang angka. Kita simpan string aman.
        let tglLahir = item.tanggalLahir;
        
        // Siapkan baris
        rowsToAdd.push([
          item.nama,
          "'" + nisn, // Pakai kutip satu agar format text terjaga
          item.jenisKelamin,
          tglLahir,
          item.agama,
          item.namaAyah,
          item.namaIbu,
          "'" + item.noHp,
          item.kelas,
          item.alamat
        ]);
        
        // Tambahkan ke Set agar tidak duplikat di dalam file import itu sendiri
        existingNISN.add(nisn);
        addedCount++;
      }

      // 3. Tulis ke Sheet sekaligus (Batch Operation) agar cepat
      if (rowsToAdd.length > 0) {
        sheet.getRange(sheet.getLastRow() + 1, 1, rowsToAdd.length, rowsToAdd[0].length).setValues(rowsToAdd);
      }

      return { 
        success: true, 
        added: addedCount, 
        skipped: skippedCount, 
        message: `Import selesai. Berhasil: ${addedCount}, Duplikat/Gagal: ${skippedCount}` 
      };

    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }

  // ====================================
  // IMPORT GURU DARI EXCEL (BULK)
  // ====================================
  function importGuruBulk(dataArray) {
    try {
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('users');
      const existingData = sheet.getDataRange().getValues();
      
      // 1. Ambil daftar Username yang sudah ada untuk cek duplikasi
      const existingUsernames = new Set();
      for (let i = 1; i < existingData.length; i++) {
        existingUsernames.add(String(existingData[i][0]).trim());
      }

      const rowsToAdd = [];
      let addedCount = 0;
      let skippedCount = 0;

      // 2. Loop data import
      for (let i = 0; i < dataArray.length; i++) {
        const item = dataArray[i];
        const username = String(item.username).trim();

        // Validasi dasar
        if (!username || !item.password) {
          skippedCount++;
          continue;
        }

        // Cek Duplikasi Username
        if (existingUsernames.has(username)) {
          skippedCount++;
          continue;
        }

        // Siapkan baris: [Username, Password, Role, Kelas]
        rowsToAdd.push([
          "'" + username, // Pakai kutip satu agar format text terjaga
          "'" + item.password,
          'guru',         // Role otomatis di-set 'guru'
          item.kelas || '' // Kelas opsional
        ]);

        // Tambahkan ke Set agar tidak duplikat di dalam file import itu sendiri
        existingUsernames.add(username);
        addedCount++;
      }

      // 3. Tulis ke Sheet sekaligus (Batch Operation)
      if (rowsToAdd.length > 0) {
        sheet.getRange(sheet.getLastRow() + 1, 1, rowsToAdd.length, rowsToAdd[0].length).setValues(rowsToAdd);
      }

      return { 
        success: true, 
        added: addedCount, 
        skipped: skippedCount, 
        message: `Import selesai. Berhasil: ${addedCount}, Duplikat/Gagal: ${skippedCount}` 
      };

    } catch (error) {
      return { success: false, message: error.toString() };
    }
  }

  // ====================================
  // KALENDER & AGENDA
  // ====================================

  /**
  * Pastikan sheet 'agenda' tersedia dengan header yang benar.
  */
  function ensureAgendaSheet() {
    const ss = getSpreadsheet();
    let sheet = ss.getSheetByName('agenda');
    if (!sheet) {
      sheet = ss.insertSheet('agenda');
      sheet.appendRow(['ID', 'Judul', 'TanggalMulai', 'TanggalSelesai', 'Kategori', 'Deskripsi', 'Telegram', 'CreatedAt']);
      sheet.setFrozenRows(1);
      // Format kolom tanggal
      sheet.getRange('C:D').setNumberFormat('yyyy-mm-dd');
    }
    return sheet;
  }

  /**
  * Pastikan sheet 'config' tersedia untuk menyimpan konfigurasi Telegram.
  */
  function ensureConfigSheet() {
    const ss = getSpreadsheet();
    let sheet = ss.getSheetByName('config');
    if (!sheet) {
      sheet = ss.insertSheet('config');
      sheet.appendRow(['Key', 'Value']);
      sheet.setFrozenRows(1);
    }
    return sheet;
  }

  /**
  * Ambil semua agenda dari sheet 'agenda'.
  */
  function getAgendaList() {
    try {
      const sheet = ensureAgendaSheet();
      const data  = sheet.getDataRange().getValues();
      if (data.length <= 1) return { success: true, data: [] };

      const list = [];
      for (let i = 1; i < data.length; i++) {
        const row = data[i];
        if (!row[0]) continue; // Skip baris kosong
        list.push({
          id:             String(row[0]),
          judul:          String(row[1] || ''),
          tanggalMulai:   row[2] ? Utilities.formatDate(new Date(row[2]), 'Asia/Jakarta', 'yyyy-MM-dd') : '',
          tanggalSelesai: row[3] ? Utilities.formatDate(new Date(row[3]), 'Asia/Jakarta', 'yyyy-MM-dd') : '',
          kategori:       String(row[4] || 'Umum'),
          deskripsi:      String(row[5] || ''),
          telegram:       row[6] === true || row[6] === 'TRUE',
          createdAt:      row[7] ? String(row[7]) : ''
        });
      }
      return { success: true, data: list };
    } catch (e) {
      return { success: false, message: e.toString() };
    }
  }

  /**
  * Tambah agenda baru.
  */
  function addAgenda(token, data) {
    try {
      verifyUser(token, null); // Minimal harus login (admin atau guru)
      const sheet = ensureAgendaSheet();
      const id    = Utilities.getUuid();
      const now   = new Date();

      sheet.appendRow([
        id,
        data.judul        || '',
        data.tanggalMulai || '',
        data.tanggalSelesai || '',
        data.kategori     || 'Umum',
        data.deskripsi    || '',
        data.telegram     ? true : false,
        now
      ]);

      // Kirim Telegram jika diminta
      if (data.telegram) {
        const cfg = getTelegramConfig();
        if (cfg.success && cfg.botToken && cfg.chatId) {
          const msg = `🔔 *NOTIFIKASI KEGIATAN TPQNM*\n\n📌 *${data.judul}*\n🗓 Tanggal: ${formatTanggalIndo(data.tanggalMulai)}${data.tanggalSelesai && data.tanggalSelesai !== data.tanggalMulai ? ' s/d ' + formatTanggalIndo(data.tanggalSelesai) : ''}\n🏷 Kategori: ${data.kategori || 'Umum'}${data.deskripsi ? '\n📝 Deskripsi: ' + data.deskripsi : ''}\n\n_Catatan: Jadwal kegiatan dapat berubah sewaktu-waktu._`;
          sendTelegramNotif(cfg.botToken, cfg.chatId, msg);
        }
      }

      return { success: true, id: id };
    } catch (e) {
      return { success: false, message: e.toString() };
    }
  }

  /**
  * Update agenda berdasarkan ID.
  */
  function updateAgenda(token, id, data) {
    try {
      verifyUser(token, 'admin');
      const sheet  = ensureAgendaSheet();
      const values = sheet.getDataRange().getValues();

      for (let i = 1; i < values.length; i++) {
        if (String(values[i][0]) === String(id)) {
          const row = i + 1; // 1-indexed
          sheet.getRange(row, 2).setValue(data.judul        || '');
          sheet.getRange(row, 3).setValue(data.tanggalMulai || '');
          sheet.getRange(row, 4).setValue(data.tanggalSelesai || '');
          sheet.getRange(row, 5).setValue(data.kategori     || 'Umum');
          sheet.getRange(row, 6).setValue(data.deskripsi    || '');
          sheet.getRange(row, 7).setValue(data.telegram     ? true : false);
          return { success: true };
        }
      }
      return { success: false, message: 'Agenda tidak ditemukan.' };
    } catch (e) {
      return { success: false, message: e.toString() };
    }
  }

  /**
  * Hapus agenda berdasarkan ID.
  */
  function deleteAgenda(token, id) {
    try {
      verifyUser(token, 'admin');
      const sheet  = ensureAgendaSheet();
      const values = sheet.getDataRange().getValues();

      for (let i = 1; i < values.length; i++) {
        if (String(values[i][0]) === String(id)) {
          sheet.deleteRow(i + 1);
          return { success: true };
        }
      }
      return { success: false, message: 'Agenda tidak ditemukan.' };
    } catch (e) {
      return { success: false, message: e.toString() };
    }
  }

  /**
  * Ambil daftar hari libur dari sheet 'hari_libur' (atau fallback 'libur').
  */
  function getLiburList() {
    try {
      const ss    = getSpreadsheet();
      const sheet = ss.getSheetByName('hari_libur') || ss.getSheetByName('libur');
      if (!sheet) return { success: true, data: [] };

      const data = sheet.getDataRange().getValues();
      if (data.length <= 1) return { success: true, data: [] };

      const list = [];
      for (let i = 1; i < data.length; i++) {
        const row = data[i];
        if (!row[0]) continue;
        let tglStr = '';
        if (row[0] instanceof Date) {
          tglStr = Utilities.formatDate(row[0], 'Asia/Jakarta', 'yyyy-MM-dd');
        } else {
          const raw = String(row[0]).trim();
          if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
            tglStr = raw.substring(0, 10);
          } else {
            try {
              tglStr = Utilities.formatDate(new Date(raw), 'Asia/Jakarta', 'yyyy-MM-dd');
            } catch(e) {
              tglStr = raw;
            }
          }
        }
        list.push({
          tanggal:     tglStr,
          keterangan:  String(row[1] || 'Hari Libur')
        });
      }
      return { success: true, data: list };
    } catch (e) {
      return { success: false, message: e.toString() };
    }
  }

  /**
  * Cek apakah tanggal tertentu (yyyy-MM-dd) adalah hari libur (Libur rutin TPQ Selasa & Jumat, atau terdaftar di sheet 'hari_libur'/'libur').
  */
  function checkHariLibur(targetDateYMD) {
    try {
      if (!targetDateYMD) {
        targetDateYMD = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');
      }

      // 1. CEK LIBUR RUTIN MINGGUAN TPQ: SELASA (2) & JUM'AT (5)
      const parts = String(targetDateYMD).split('-');
      if (parts.length === 3) {
        const dObj = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10), 12, 0, 0);
        const dow = dObj.getDay(); // 0 = Min, 1 = Sen, 2 = Sel, 3 = Rab, 4 = Kam, 5 = Jum, 6 = Sab
        if (dow === 2) {
          return { isLibur: true, keterangan: 'Libur Rutin TPQ (Hari Selasa)' };
        }
        if (dow === 5) {
          return { isLibur: true, keterangan: 'Libur Rutin TPQ (Hari Jumat)' };
        }
      }

      // 2. CEK SHEET 'hari_libur' ATAU 'libur'
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('hari_libur') || ss.getSheetByName('libur');
      if (!sheet) return { isLibur: false, keterangan: '' };

      const data = sheet.getDataRange().getValues();
      for (let i = 1; i < data.length; i++) {
        const cell = data[i][0];
        if (!cell) continue;

        let dateStr = '';
        if (cell instanceof Date) {
          dateStr = Utilities.formatDate(cell, 'Asia/Jakarta', 'yyyy-MM-dd');
        } else {
          const raw = String(cell).trim();
          if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
            dateStr = raw.substring(0, 10);
          } else {
            try {
              dateStr = Utilities.formatDate(new Date(raw), 'Asia/Jakarta', 'yyyy-MM-dd');
            } catch(e) {
              dateStr = raw;
            }
          }
        }

        if (dateStr === targetDateYMD) {
          return { isLibur: true, keterangan: String(data[i][1] || 'Hari Libur') };
        }
      }
    } catch(e) {
      console.warn("Gagal cek hari libur:", e);
    }
    return { isLibur: false, keterangan: '' };
  }

  // ====================================
  // KONFIGURASI TELEGRAM
  // ====================================

  /**
  * Simpan konfigurasi Bot Token, Chat ID Telegram, Waktu Notif Kegiatan, Waktu Notif Absensi, dan Link Web App.
  * Jam disimpan sebagai Text murni (format '@' dan prefix "'") agar tidak terkonversi otomatis menjadi Date oleh Google Sheets.
  */
  function saveTelegramConfig(token, botToken, chatId, notifTime, absenTime, webUrl) {
    try {
      verifyUser(token, 'admin');
      const sheet  = ensureConfigSheet();
      const values = sheet.getDataRange().getValues();

      // Normalisasi format waktu ke strict 'HH:mm'
      const cleanTime = (t, fallback) => {
        const s = String(t || '').replace(/^'/, '').trim();
        if (/^\d{1,2}:\d{2}$/.test(s)) {
          const [h, m] = s.split(':');
          return `${String(h).padStart(2, '0')}:${m}`;
        }
        return fallback;
      };

      const cleanNotifTime = cleanTime(notifTime, '07:00');
      const cleanAbsenTime = cleanTime(absenTime, '15:30');

      const updateOrAppend = (key, value, isText) => {
        for (let i = 1; i < values.length; i++) {
          if (String(values[i][0]).trim().toLowerCase() === key.toLowerCase()) {
            const cell = sheet.getRange(i + 1, 2);
            if (isText) cell.setNumberFormat('@');
            cell.setValue(isText ? "'" + value : value);
            return;
          }
        }
        sheet.appendRow([key, isText ? "'" + value : value]);
      };

      updateOrAppend('telegram_bot_token', botToken, false);
      updateOrAppend('telegram_chat_id',   chatId, false);
      updateOrAppend('telegram_notif_time', cleanNotifTime, true);
      updateOrAppend('telegram_absen_time', cleanAbsenTime, true);
      updateOrAppend('telegram_web_url',   webUrl || '', false);

      // Atur trigger otomatis pengingat kegiatan H-1
      setupCronTrigger(cleanNotifTime);

      // Atur trigger otomatis pengingat absensi harian (Hari H)
      setupAbsenCronTrigger(cleanAbsenTime);

      return { 
        success: true, 
        notifTime: cleanNotifTime, 
        absenTime: cleanAbsenTime 
      };
    } catch (e) {
      return { success: false, message: e.toString() };
    }
  }

  /**
  * Baca konfigurasi Telegram dari sheet 'config'.
  * Selalu mengonversi Date/Time dengan zona waktu 'Asia/Jakarta' agar tidak bergeser waktu saat refresh.
  */
  function getTelegramConfig(token) {
    try {
      if (token) {
        verifyUser(token, 'admin');
      }
      const sheet  = ensureConfigSheet();
      const values = sheet.getDataRange().getValues();

      let botToken  = '';
      let chatId    = '';
      let notifTime = '07:00';
      let absenTime = '15:30';
      let webUrl    = '';

      const formatTimeVal = (raw, defaultVal) => {
        if (!raw && raw !== 0) return defaultVal;
        if (raw instanceof Date) {
          // SELALU gunakan Asia/Jakarta (WIB)
          return Utilities.formatDate(raw, 'Asia/Jakarta', 'HH:mm');
        }
        const str = String(raw).replace(/^'/, '').trim();
        if (/^\d{1,2}:\d{2}$/.test(str)) {
          const [h, m] = str.split(':');
          return `${String(h).padStart(2, '0')}:${m}`;
        }
        return defaultVal;
      };

      for (let i = 1; i < values.length; i++) {
        const key = String(values[i][0]).trim().toLowerCase();
        if (key === 'telegram_bot_token') botToken  = String(values[i][1] || '').trim();
        if (key === 'telegram_chat_id')   chatId    = String(values[i][1] || '').trim();
        if (key === 'telegram_notif_time') notifTime = formatTimeVal(values[i][1], '07:00');
        if (key === 'telegram_absen_time') absenTime = formatTimeVal(values[i][1], '15:30');
        if (key === 'telegram_web_url')   webUrl    = String(values[i][1] || '').trim();
      }

      if (!webUrl) {
        try {
          webUrl = ScriptApp.getService().getUrl() || '';
        } catch(e) {}
      }

      return { 
        success: true, 
        botToken: botToken, 
        chatId: chatId, 
        notifTime: notifTime, 
        absenTime: absenTime, 
        webUrl: webUrl 
      };
    } catch (e) {
      return { success: false, message: e.toString() };
    }
  }

  /**
  * Kirim pesan notifikasi ke Telegram via Bot API.
  * @param {string} botToken  - Token bot dari @BotFather
  * @param {string} chatId    - Chat ID tujuan (bisa grup atau personal)
  * @param {string} message   - Teks pesan (mendukung HTML atau Markdown)
  * @param {string} parseMode - 'HTML' | 'Markdown' (opsional, auto-detect jika kosong)
  */
  function sendTelegramNotif(botToken, chatId, message, parseMode) {
    try {
      const url = `https://api.telegram.org/bot${botToken}/sendMessage`;
      
      // Tentukan parse_mode otomatis jika tidak ditentukan
      let mode = parseMode;
      if (!mode) {
        if (/<[a-z][\s\S]*>/i.test(message)) {
          mode = 'HTML';
        } else if (/\*|_|`|\[/.test(message)) {
          mode = 'Markdown';
        }
      }

      const payload = {
        chat_id: String(chatId),
        text:    message
      };
      if (mode) {
        payload.parse_mode = mode;
      }

      const options = {
        method:             'post',
        contentType:        'application/json',
        payload:            JSON.stringify(payload),
        muteHttpExceptions: true
      };

      let response = UrlFetchApp.fetch(url, options);
      let code     = response.getResponseCode();
      let body     = JSON.parse(response.getContentText());

      // Jika gagal karena parse entities (error format Markdown/HTML),
      // kirim ulang sebagai plain text agar pesan tetap sampai!
      if (code !== 200 && body.description && body.description.toLowerCase().includes("can't parse entities")) {
        console.warn("Telegram parse error, retrying without parse_mode:", body.description);
        delete payload.parse_mode;
        options.payload = JSON.stringify(payload);
        response = UrlFetchApp.fetch(url, options);
        code     = response.getResponseCode();
        body     = JSON.parse(response.getContentText());
      }

      if (code === 200 && body.ok) {
        return { success: true };
      } else {
        return { success: false, message: body.description || 'Telegram API error (code: ' + code + ')' };
      }
    } catch (e) {
      return { success: false, message: 'Koneksi gagal: ' + e.toString() };
    }
  }

  /**
  * Helper untuk normalisasi tanggal ke string 'yyyy-MM-dd' dengan zona waktu Asia/Jakarta.
  */
  function normalizeDateToYMD(val) {
    if (!val) return '';
    if (val instanceof Date) {
      return Utilities.formatDate(val, 'Asia/Jakarta', 'yyyy-MM-dd');
    }
    const str = String(val).trim();
    if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
      return str.substring(0, 10);
    }
    try {
      const d = new Date(val);
      if (!isNaN(d.getTime())) {
        return Utilities.formatDate(d, 'Asia/Jakarta', 'yyyy-MM-dd');
      }
    } catch(e) {}
    return str;
  }

  /**
  * Menghitung waktu eksekusi Date berikutnya dalam zona waktu WIB (Asia/Jakarta / UTC+7).
  * Kebal terhadap perbedaan timezone server / ScriptApp.
  */
  function calculateNextRunWIB(hour, minute) {
    const now = new Date();
    const todayWIBStr = Utilities.formatDate(now, 'Asia/Jakarta', 'yyyy-MM-dd');
    const hStr = String(hour).padStart(2, '0');
    const mStr = String(minute).padStart(2, '0');

    // Buat timestamp ISO dengan offset eksplisit +07:00 (WIB)
    const targetToday = new Date(`${todayWIBStr}T${hStr}:${mStr}:00+07:00`);

    // Jika waktu hari ini sudah lewat (atau sisa < 60 detik), jadwalkan untuk BESOK
    if (targetToday.getTime() <= now.getTime() + 60000) {
      const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000);
      const tomorrowWIBStr = Utilities.formatDate(tomorrow, 'Asia/Jakarta', 'yyyy-MM-dd');
      return new Date(`${tomorrowWIBStr}T${hStr}:${mStr}:00+07:00`);
    }
    return targetToday;
  }

  /**
  * Menyiapkan trigger harian untuk mengecek jadwal H-1 pada jam dan menit yang ditentukan (WIB).
  */
  function setupCronTrigger(timeStr) {
    try {
      // 1. Hapus SEMUA trigger checkHMin1Agenda yang lama agar tidak menumpuk / ganda
      const triggers = ScriptApp.getProjectTriggers();
      for (let t of triggers) {
        if (t.getHandlerFunction() === 'checkHMin1Agenda') {
          ScriptApp.deleteTrigger(t);
        }
      }
      
      if (!timeStr) return;
      const parts = timeStr.replace(/^'/, '').split(':');
      const hour = parseInt(parts[0], 10);
      const minute = parts.length > 1 ? parseInt(parts[1], 10) : 0;
      if (isNaN(hour)) return;
      
      // 2. Hitung waktu eksekusi berikutnya pada jam WIB yang tepat
      const nextRun = calculateNextRunWIB(hour, minute);
      
      // 3. Buat trigger waktu presisi
      ScriptApp.newTrigger('checkHMin1Agenda')
        .timeBased()
        .at(nextRun)
        .create();
        
      console.log("Trigger checkHMin1Agenda dijadwalkan pada WIB: " + Utilities.formatDate(nextRun, 'Asia/Jakarta', 'yyyy-MM-dd HH:mm'));
    } catch(e) {
      console.error("Gagal membuat trigger checkHMin1Agenda:", e);
    }
  }

  /**
  * Mengecek agenda yang akan berlangsung BESOK (H-1) dan mengirimkan pengingat ke Telegram.
  */
  function checkHMin1Agenda(isManualTest) {
    try {
      const cfg = getTelegramConfig();
      if (!cfg.success || !cfg.botToken || !cfg.chatId) {
        console.warn("Konfigurasi Telegram belum lengkap.");
        return { success: false, message: 'Bot Token atau Chat ID belum dikonfigurasi.' };
      }

      // Selalu jadwalkan otomatis untuk hari berikutnya agar trigger harian tidak putus
      if (cfg.notifTime) {
        setupCronTrigger(cfg.notifTime);
      }
      
      const ss = getSpreadsheet();
      const sheet = ss.getSheetByName('agenda');
      if (!sheet) {
        return { success: false, message: 'Sheet agenda tidak ditemukan.' };
      }
      
      const data = sheet.getDataRange().getValues();
      if (data.length <= 1) {
        return { success: true, count: 0, message: 'Tidak ada agenda di sheet.' };
      }
      
      // Hitung tanggal besok dalam zona waktu Asia/Jakarta (WIB)
      const now = new Date();
      const besokDate = new Date(now.getTime() + 24 * 60 * 60 * 1000);
      const besokStr = Utilities.formatDate(besokDate, 'Asia/Jakarta', 'yyyy-MM-dd');
      
      console.log("Mengecek agenda untuk tanggal besok: " + besokStr);
      
      let sentCount = 0;
      for (let i = 1; i < data.length; i++) {
        const row = data[i];
        if (!row[0]) continue;
        
        const tglMulai = normalizeDateToYMD(row[2]);
        const tglSelesai = normalizeDateToYMD(row[3]);
        
        if (tglMulai === besokStr) {
          const judul = String(row[1] || 'Tanpa Judul');
          const kategori = String(row[4] || 'Umum');
          const deskripsi = String(row[5] || '');
          
          const rentangTgl = (tglSelesai && tglSelesai !== tglMulai)
            ? ` s/d ${formatTanggalIndo(tglSelesai)}`
            : '';
          
          const msg = `🔔 *PENGINGAT (H-1) KEGIATAN TPQNM* 🔔\n\nBesok akan ada kegiatan:\n\n📌 *${judul}*\n🗓 Tanggal: ${formatTanggalIndo(tglMulai)}${rentangTgl}\n🏷 Kategori: ${kategori || 'Umum'}${deskripsi ? '\n📝 Deskripsi: ' + deskripsi : ''}\n\n_Catatan: Jadwal kegiatan dapat berubah sewaktu-waktu._`;
          
          const res = sendTelegramNotif(cfg.botToken, cfg.chatId, msg);
          if (res && res.success) {
            sentCount++;
            console.log("Notifikasi H-1 berhasil dikirim: " + judul);
          } else {
            console.error("Gagal kirim notif H-1 (" + judul + "): " + (res ? res.message : 'Unknown error'));
          }
        }
      }
      
      console.log("Pengecekan H-1 selesai. Total pesan terkirim: " + sentCount);
      return { success: true, count: sentCount, message: `Pengecekan selesai. ${sentCount} pengingat terkirim.` };
    } catch(e) {
      console.error("Error pada checkHMin1Agenda:", e);
      return { success: false, message: e.toString() };
    }
  }

  /**
  * Menyiapkan trigger harian untuk pengingat absensi santri pada jam dan menit yang ditentukan (WIB).
  */
  function setupAbsenCronTrigger(timeStr) {
    try {
      // 1. Hapus SEMUA trigger sendDailyAbsenReminder yang lama agar tidak menumpuk / ganda
      const triggers = ScriptApp.getProjectTriggers();
      for (let t of triggers) {
        if (t.getHandlerFunction() === 'sendDailyAbsenReminder') {
          ScriptApp.deleteTrigger(t);
        }
      }
      
      if (!timeStr) return;
      const parts = timeStr.replace(/^'/, '').split(':');
      const hour = parseInt(parts[0], 10);
      const minute = parts.length > 1 ? parseInt(parts[1], 10) : 0;
      if (isNaN(hour)) return;
      
      // 2. Hitung waktu eksekusi berikutnya pada jam WIB yang tepat
      const nextRun = calculateNextRunWIB(hour, minute);
      
      // 3. Buat trigger waktu presisi
      ScriptApp.newTrigger('sendDailyAbsenReminder')
        .timeBased()
        .at(nextRun)
        .create();
        
      console.log("Trigger sendDailyAbsenReminder dijadwalkan pada WIB: " + Utilities.formatDate(nextRun, 'Asia/Jakarta', 'yyyy-MM-dd HH:mm'));
    } catch(e) {
      console.error("Gagal membuat trigger pengingat absensi:", e);
    }
  }

  /**
  * Fungsi ini dijalankan otomatis oleh Trigger setiap hari pada jam absensi,
  * atau bisa dipanggil manual untuk mengetes pengingat absensi santri.
  * Memeriksa hari libur sebelum mengirim dan dilengkapi proteksi anti-duplikasi per hari.
  */
  function sendDailyAbsenReminder(isManualTest) {
    try {
      const cfg = getTelegramConfig();
      if (!cfg.success || !cfg.botToken || !cfg.chatId) {
        console.warn("Konfigurasi Telegram belum lengkap.");
        return { success: false, message: 'Bot Token atau Chat ID belum dikonfigurasi.' };
      }

      // 1. Reschedule otomatis untuk hari berikutnya agar rantai cron harian tetap aktif
      if (cfg.absenTime) {
        setupAbsenCronTrigger(cfg.absenTime);
      }
      
      // 2. CEK APAKAH HARI INI HARI LIBUR (Libur Rutin Selasa/Jumat & Sheet 'hari_libur')
      const todayStr = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');
      const liburCheck = checkHariLibur(todayStr);

      if (liburCheck.isLibur && !isManualTest) {
        const msgLibur = `Hari ini libur (${liburCheck.keterangan}). Pengingat presensi TIDAK dikirim.`;
        console.log(msgLibur);
        return { success: true, skipped: true, message: msgLibur };
      }

      // 3. PROTEKSI ANTI-DUPLIKASI (Mencegah terkirim ganda pada hari yang sama jika bukan test manual)
      const cache = CacheService.getScriptCache();
      const sentKey = 'absen_reminder_sent_' + todayStr;
      if (!isManualTest && cache.get(sentKey)) {
        console.log(`Pengingat absensi hari ini (${todayStr}) sudah pernah dikirim sebelumnya. Skip duplicate.`);
        return { success: true, skipped: true, message: `Pengingat absensi hari ini sudah pernah dikirim sebelumnya.` };
      }
      
      // 4. Format tanggal Indonesia (WIB)
      const nowWIB = new Date();
      const hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      const dayOfWeekStr = Utilities.formatDate(nowWIB, 'Asia/Jakarta', 'u'); // 1 = Senin, ..., 7 = Minggu
      const dayOfWeek = parseInt(dayOfWeekStr, 10) % 7;
      const namaHari = hariIndo[dayOfWeek];
      const tglFormatted = formatTanggalIndo(todayStr);
      
      // 5. Siapkan URL Web Absensi (format HTML aman)
      let webUrl = cfg.webUrl ? cfg.webUrl.trim() : '';
      if (!webUrl) {
        try {
          webUrl = ScriptApp.getService().getUrl() || '';
        } catch(e) {}
      }
      
      let linkSection = '';
      if (webUrl) {
        linkSection = `Silakan klik tautan di bawah ini untuk membuka aplikasi presensi:\n👉 <a href="${webUrl}">Buka Aplikasi Presensi</a>\n\n`;
      } else {
        linkSection = `Silakan buka aplikasi web absensi sekolah untuk mengisi presensi santri hari ini.\n\n`;
      }
      
      const msg = `📢 <b>PENGINGAT PRESENSI SANTRI TPQ</b> 📢\n\n` +
        `Assalamu'alaikum Wr. Wb.\n` +
        `Yth. Bapak/Ibu Ustadz & Ustadzah, mohon kesediaannya untuk mengisi presensi kehadiran santri hari ini:\n\n` +
        `🗓 <b>Hari/Tanggal:</b> ${namaHari}, ${tglFormatted}\n\n` +
        linkSection +
        `<i>Jazakumullahu khairan katsiran atas dedikasi dan bimbingannya.</i>`;
      
      const res = sendTelegramNotif(cfg.botToken, cfg.chatId, msg, 'HTML');
      
      if (res && res.success) {
        // Tandai di cache agar tidak bisa terkirim dua kali pada hari yang sama
        cache.put(sentKey, 'true', 86400); // 24 jam
        return { success: true, message: 'Pesan pengingat presensi berhasil dikirim ke Telegram!' };
      } else {
        return { success: false, message: res ? res.message : 'Gagal mengirim pesan ke Telegram' };
      }
    } catch(e) {
      console.error("Error sendDailyAbsenReminder:", e);
      return { success: false, message: e.toString() };
    }
  }





  /**
  * Helper mengubah yyyy-MM-dd menjadi dd-Bulan-yyyy
  */
  function formatTanggalIndo(dateStr) {
    if (!dateStr) return '';
    const bulanArr = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    try {
      const parts = dateStr.split('-');
      if (parts.length === 3) {
        const year = parts[0];
        const month = parseInt(parts[1], 10) - 1;
        const day = parts[2];
        return `${day}-${bulanArr[month]}-${year}`;
      }
      return dateStr;
    } catch(e) {
      return dateStr;
    }
  }

  /**
  * API POST Endpoint untuk Web Scanner Mandiri / Eksternal
  */
  function doPost(e) {
    try {
      let data = {};
      if (e && e.postData && e.postData.contents) {
        try {
          data = JSON.parse(e.postData.contents);
        } catch (parseErr) {
          data = e.parameter || {};
        }
      } else if (e && e.parameter) {
        data = e.parameter;
      }

      if (data.action === 'scanAbsensi') {
        const result = scanAbsensi(data.nisn, data.scannerRole || 'guru', data.scannerKelas || '');
        return ContentService.createTextOutput(JSON.stringify(result))
          .setMimeType(ContentService.MimeType.JSON);
      }

      // === SINKRONISASI OTOMATIS DARI WEBSITE LARAVEL BARU KE SHEET 'absensi' ===
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
        SpreadsheetApp.flush(); // Terapkan langsung ke spreadsheet

        return ContentService.createTextOutput(JSON.stringify({
          success: true,
          message: 'Baris kehadiran berhasil ditambahkan ke sheet absensi!',
          row: newRow
        })).setMimeType(ContentService.MimeType.JSON);
      }

      return ContentService.createTextOutput(JSON.stringify({ success: false, message: 'Aksi tidak dikenali' }))
        .setMimeType(ContentService.MimeType.JSON);
    } catch (error) {
      return ContentService.createTextOutput(JSON.stringify({ success: false, message: 'Server Error: ' + error.message }))
        .setMimeType(ContentService.MimeType.JSON);
    }
  }



