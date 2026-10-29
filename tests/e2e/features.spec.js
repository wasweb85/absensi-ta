import { test, expect } from '@playwright/test';

test.describe.serial('Alur Modul Absensi & SPP', () => {
  let page;

  test.beforeAll(async ({ browser, baseURL }) => {
    page = await browser.newPage({ baseURL });

    // Login sekali di awal suite
    await page.goto('/login');
    await page.locator('#username').fill('admin');
    await page.locator('#password').fill('admin123');
    await page.getByRole('button', { name: /Masuk ke Sistem/i }).click();
    await page.waitForURL('**/dashboard');
    await expect(page.locator('text=Dashboard').first()).toBeVisible();
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('Membuka halaman Scanner Absensi dan memuat komponen pemindai', async () => {
    await page.goto('/absensi/scanner');
    await expect(page).toHaveURL(/.*absensi\/scanner/);

    // Verifikasi elemen halaman scanner
    await expect(page.locator('text=Scanner Absensi Pintar').first()).toBeVisible();
    await expect(page.locator('input[placeholder*="NISN"]').or(page.locator('#manualNisn')).or(page.locator('input[type="text"]')).first()).toBeVisible();
  });

  test('Membuka halaman Data Santri dan menampilkan tabel serta aksi', async () => {
    await page.goto('/absensi/siswa');
    await expect(page).toHaveURL(/.*absensi\/siswa/);

    // Verifikasi header dan tabel
    await expect(page.locator('text=Data Santri & Cetak QR Code').or(page.locator('text=Data Santri')).first()).toBeVisible();
    await expect(page.locator('table').or(page.locator('.grid')).first()).toBeVisible();
  });

  test('Membuka halaman Tagihan SPP dan memuat ringkasan keuangan', async () => {
    await page.goto('/spp/tagihan');
    await expect(page).toHaveURL(/.*spp\/tagihan/);

    // Verifikasi tabel tagihan
    await expect(page.locator('text=Tagihan').first()).toBeVisible();
  });

  test('Membuka halaman Input Pembayaran SPP Cepat', async () => {
    await page.goto('/spp/input-cepat');
    await expect(page).toHaveURL(/.*spp\/input-cepat/);

    // Verifikasi form input pembayaran
    await expect(page.locator('text=Pembayaran').first()).toBeVisible();
  });
});
