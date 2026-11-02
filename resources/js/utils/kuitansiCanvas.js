/**
 * kuitansiCanvas.js
 * Merender dokumen kuitansi SPP TPQ Nurul Mun'im menjadi gambar (Canvas / PNG)
 * Khusus untuk kirim WhatsApp wali santri:
 * - Kop resmi Yayasan Nurul Jadid & TPQ Nurul Mun'im
 * - Rincian transaksi lengkap dan rapi
 * - Stempel LUNAS sah
 * - Hanya tanda tangan Bendahara TPQ (tanpa kolom Penyetor / Wali Santri, dan tanpa teks Petugas)
 * - Resolusi tinggi (crisp & jernih di layar HP dan WhatsApp)
 */

function formatRupiah(val) {
  if (!val) return '0';
  return Number(val).toLocaleString('id-ID');
}

function formatTanggalIndo(str) {
  if (!str) {
    const today = new Date();
    return today.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
  }
  const cleanStr = String(str).replace(' ', 'T').replace('Z', '');
  const d = new Date(cleanStr);
  if (isNaN(d.getTime())) return str;
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

function loadImage(src) {
  return new Promise((resolve) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => resolve(img);
    img.onerror = () => resolve(null);
    img.src = src;
  });
}

/**
 * Menggambar rounded rectangle pada canvas
 */
function roundRect(ctx, x, y, width, height, radius, fill = true, stroke = true) {
  ctx.beginPath();
  ctx.moveTo(x + radius, y);
  ctx.lineTo(x + width - radius, y);
  ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
  ctx.lineTo(x + width, y + height - radius);
  ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
  ctx.lineTo(x + radius, y + height);
  ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
  ctx.lineTo(x, y + radius);
  ctx.quadraticCurveTo(x, y, x + radius, y);
  ctx.closePath();
  if (fill) ctx.fill();
  if (stroke) ctx.stroke();
}

/**
 * Render Kuitansi ke HTML5 Canvas
 * @param {Object} data 
 * @returns {Promise<{ canvas: HTMLCanvasElement, dataUrl: string, blob: Blob, file: File }>}
 */
export async function generateKuitansiCanvas(data) {
  const width = 760;
  const height = 980;

  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');

  // 1. Background Dasar & Border Card Utama
  ctx.fillStyle = '#FFFFFF';
  ctx.fillRect(0, 0, width, height);

  // Outer border dengan sudut melengkung halus
  ctx.strokeStyle = '#E2E8F0';
  ctx.lineWidth = 3;
  ctx.fillStyle = '#FFFFFF';
  roundRect(ctx, 12, 12, width - 24, height - 24, 20, true, true);

  // 2. Load Logo TPQ
  const logoSrc = data.appLogo || '/LOGOTPQ.png';
  const logoImg = await loadImage(logoSrc);

  const startY = 38;
  const logoSize = 72;
  const logoX = 40;
  const logoY = startY + 2;

  if (logoImg) {
    ctx.drawImage(logoImg, logoX, logoY, logoSize, logoSize);
  }

  // 3. Kop Surat Teks (Tengah)
  const kopCenterX = (width / 2) + 20;

  ctx.textAlign = 'center';
  ctx.textBaseline = 'top';

  // Baris 1: YAYASAN NURUL JADID
  ctx.font = 'bold 22px "Times New Roman", Times, serif';
  ctx.fillStyle = '#7b95c0'; // Biru lembut khas Kop Yayasan
  ctx.fillText('YAYASAN NURUL JADID', kopCenterX, startY);

  // Baris 2: TAMAN PENDIDIKAN AL-QUR'AN NURUL MUN'IM
  ctx.font = 'bold 15px "Times New Roman", Times, serif';
  ctx.fillStyle = '#3f3f46';
  ctx.fillText("TAMAN PENDIDIKAN AL-QUR'AN NURUL MUN'IM", kopCenterX, startY + 27);

  // Baris 3: KARANGANYAR PAITON PROBOLINGGO
  ctx.font = 'bold 12px "Times New Roman", Times, serif';
  ctx.fillStyle = '#52525b';
  ctx.fillText('KARANGANYAR PAITON PROBOLINGGO', kopCenterX, startY + 47);

  // Baris 4: Alamat & Kontak
  ctx.font = 'italic 10.5px "Times New Roman", Times, serif';
  ctx.fillStyle = '#cca843'; // Warna emas khas kop
  ctx.fillText('Alamat: PP. Nurul Jadid PO. Box 1 Paiton Probolinggo 67291 • Email: tpqnm.nurja@gmail.com', kopCenterX, startY + 65);

  // 4. Garis Ganda Kop Surat Resmi
  const lineY = startY + 86;
  ctx.strokeStyle = '#18181b';
  ctx.lineWidth = 2.5;
  ctx.beginPath();
  ctx.moveTo(35, lineY);
  ctx.lineTo(width - 35, lineY);
  ctx.stroke();

  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.moveTo(35, lineY + 4);
  ctx.lineTo(width - 35, lineY + 4);
  ctx.stroke();

  // 5. Judul Dokumen Kuitansi
  const titleY = lineY + 16;
  ctx.font = 'bold 15px Arial, Helvetica, sans-serif';
  ctx.fillStyle = '#0f172a';
  ctx.textAlign = 'center';
  ctx.fillText('KUITANSI PEMBAYARAN SPP RESMI', width / 2, titleY);

  // Garis bawah judul kuitansi
  const titleWidth = ctx.measureText('KUITANSI PEMBAYARAN SPP RESMI').width;
  ctx.strokeStyle = '#0f172a';
  ctx.lineWidth = 1.2;
  ctx.beginPath();
  ctx.moveTo((width / 2) - (titleWidth / 2), titleY + 19);
  ctx.lineTo((width / 2) + (titleWidth / 2), titleY + 19);
  ctx.stroke();

  // 6. Metadata Row (Nomor Kuitansi & Tanggal Bayar)
  const metaY = titleY + 30;
  ctx.font = '11.5px monospace';
  ctx.fillStyle = '#64748b';
  ctx.textAlign = 'left';
  ctx.fillText(`No. Kuitansi: ${data.nomor_kuitansi || '-'}`, 40, metaY);

  ctx.textAlign = 'right';
  ctx.fillText(`Tanggal: ${data.tanggal_bayar || formatTanggalIndo(null)}`, width - 40, metaY);

  // 7. Box Card Rincian Kuitansi
  const cardX = 35;
  const cardY = metaY + 22;
  const cardW = width - 70;
  const cardH = 360;

  ctx.fillStyle = '#F8FAFC';
  ctx.strokeStyle = '#E2E8F0';
  ctx.lineWidth = 1.5;
  roundRect(ctx, cardX, cardY, cardW, cardH, 16, true, true);

  // Item-item di dalam card
  const padLeft = cardX + 24;
  const padRight = cardX + cardW - 24;
  let currY = cardY + 22;
  const rowGap = 36;

  function drawItemRow(label, value, isBold = false, isEmerald = false, isMono = false) {
    ctx.textAlign = 'left';
    ctx.font = '13px Arial, Helvetica, sans-serif';
    ctx.fillStyle = '#64748b';
    ctx.fillText(label, padLeft, currY);

    ctx.textAlign = 'right';
    if (isEmerald) {
      ctx.font = 'bold 18px monospace';
      ctx.fillStyle = '#059669';
    } else if (isBold) {
      ctx.font = 'bold 14px Arial, Helvetica, sans-serif';
      ctx.fillStyle = '#0f172a';
    } else if (isMono) {
      ctx.font = 'bold 13px monospace';
      ctx.fillStyle = '#1e293b';
    } else {
      ctx.font = '13.5px Arial, Helvetica, sans-serif';
      ctx.fillStyle = '#1e293b';
    }
    ctx.fillText(value, padRight, currY);

    currY += rowGap;
  }

  drawItemRow('Nama Santri:', data.nama_santri || '-', true);
  drawItemRow('NISN / Jilid:', `${data.nisn || '-'} • ${data.kelas || '-'}`, false, false, true);
  drawItemRow('Pembayaran Periode:', data.pembayaran_untuk || '-', true);
  drawItemRow('Metode Pembayaran:', data.metode || 'Tunai', false);

  if (data.catatan) {
    ctx.textAlign = 'left';
    ctx.font = '13px Arial, Helvetica, sans-serif';
    ctx.fillStyle = '#64748b';
    ctx.fillText('Catatan:', padLeft, currY);

    ctx.textAlign = 'right';
    ctx.font = 'italic 12.5px Arial, Helvetica, sans-serif';
    ctx.fillStyle = '#475569';
    ctx.fillText(data.catatan, padRight, currY);
    currY += rowGap;
  } else {
    currY += 4;
  }

  // Garis pemisah sebelum total
  ctx.strokeStyle = '#CBD5E1';
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.moveTo(cardX + 16, currY - 8);
  ctx.lineTo(cardX + cardW - 16, currY - 8);
  ctx.stroke();

  // Total Bayar
  ctx.textAlign = 'left';
  ctx.font = 'bold 14px Arial, Helvetica, sans-serif';
  ctx.fillStyle = '#0f172a';
  ctx.fillText('TOTAL DIBAYAR:', padLeft, currY + 6);

  ctx.textAlign = 'right';
  ctx.font = 'bold 21px monospace';
  ctx.fillStyle = '#059669';
  ctx.fillText(`Rp ${formatRupiah(data.jumlah_bayar)}`, padRight, currY + 4);

  currY += rowGap + 2;

  // Status Transaksi: LUNAS
  ctx.textAlign = 'left';
  ctx.font = '13px Arial, Helvetica, sans-serif';
  ctx.fillStyle = '#64748b';
  ctx.fillText('Status Transaksi:', padLeft, currY);

  // Badge LUNAS
  const badgeW = 76;
  const badgeH = 24;
  const badgeX = padRight - badgeW;
  const badgeY = currY - 3;
  ctx.fillStyle = '#ECFDF5';
  ctx.strokeStyle = '#A7F3D0';
  ctx.lineWidth = 1;
  roundRect(ctx, badgeX, badgeY, badgeW, badgeH, 6, true, true);

  ctx.textAlign = 'center';
  ctx.font = 'bold 11px Arial, Helvetica, sans-serif';
  ctx.fillStyle = '#047857';
  ctx.fillText('✓ LUNAS', badgeX + (badgeW / 2), badgeY + 5);

  // 8. Stempel Sah & Verifikasi Sistem (Cap Bulat / Stempel Lunas Miring)
  ctx.save();
  ctx.translate(220, cardY + cardH + 55);
  ctx.rotate(-10 * Math.PI / 180);

  ctx.strokeStyle = 'rgba(5, 150, 105, 0.75)';
  ctx.lineWidth = 2;
  roundRect(ctx, -75, -28, 150, 56, 10, false, true);

  ctx.strokeStyle = 'rgba(5, 150, 105, 0.4)';
  ctx.lineWidth = 1;
  roundRect(ctx, -71, -24, 142, 48, 8, false, true);

  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillStyle = 'rgba(5, 150, 105, 0.85)';
  ctx.font = 'bold 10px Arial, sans-serif';
  ctx.fillText("TPQ NURUL MUN'IM", 0, -12);

  ctx.font = 'bold 14px Arial, sans-serif';
  ctx.fillText('★ LUNAS • SAH ★', 0, 3);

  ctx.font = '9px monospace';
  ctx.fillText('TERVERIFIKASI', 0, 16);
  ctx.restore();

  // 9. Tanda Tangan Resmi Bendahara TPQ (HANYA BENDAHARA, TANPA PENYETOR & TANPA PETUGAS)
  const sigX = width - 180;
  const sigStartY = cardY + cardH + 24;

  ctx.textBaseline = 'top';
  ctx.textAlign = 'center';

  // Tanggal & Titik Kota
  ctx.font = '12px Arial, Helvetica, sans-serif';
  ctx.fillStyle = '#475569';
  ctx.fillText(`Paiton, ${formatTanggalIndo(data.tanggal_bayar)}`, sigX, sigStartY);

  // Jabatan Resmi
  ctx.font = 'bold 13px Arial, Helvetica, sans-serif';
  ctx.fillStyle = '#1e293b';
  ctx.fillText('Bendahara TPQ,', sigX, sigStartY + 20);

  // Nama Bendahara (Underlined)
  const namaBendaharaStr = `( ${data.nama_bendahara || 'Ustadzah Ummi'} )`;
  const nameY = sigStartY + 96;

  ctx.font = 'bold 13.5px Arial, Helvetica, sans-serif';
  ctx.fillStyle = '#0f172a';
  ctx.fillText(namaBendaharaStr, sigX, nameY);

  const nameWidth = ctx.measureText(namaBendaharaStr).width;
  ctx.strokeStyle = '#0f172a';
  ctx.lineWidth = 1.2;
  ctx.beginPath();
  ctx.moveTo(sigX - (nameWidth / 2) + 10, nameY + 18);
  ctx.lineTo(sigX + (nameWidth / 2) - 10, nameY + 18);
  ctx.stroke();

  // 10. Footer Catatan Keabsahan
  ctx.textAlign = 'center';
  ctx.font = 'italic 10.5px Arial, Helvetica, sans-serif';
  ctx.fillStyle = '#94a3b8';
  ctx.fillText(
    'Bukti pembayaran ini sah dan diterbitkan secara digital oleh Sistem Administrasi TPQ Nurul Mun\'im.',
    width / 2,
    height - 52
  );

  ctx.font = '9.5px monospace';
  ctx.fillStyle = '#cbd5e1';
  ctx.fillText(
    `ID: ${data.nomor_kuitansi || 'KW-SPP'} • Simpan dokumen ini sebagai tanda bukti resmi pelunasan SPP`,
    width / 2,
    height - 34
  );

  // Return Format Data (Canvas, DataUrl, Blob, File)
  const dataUrl = canvas.toDataURL('image/png');

  const blob = await new Promise((resolve) => {
    canvas.toBlob((b) => resolve(b), 'image/png');
  });

  const fileName = `Kuitansi-${data.nomor_kuitansi || 'SPP'}.png`;
  const file = new File([blob], fileName, { type: 'image/png' });

  return {
    canvas,
    dataUrl,
    blob,
    file,
  };
}
