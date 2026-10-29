import { test, expect } from '@playwright/test';

test.describe('Autentikasi & Navigasi Dashboard', () => {
  test('Halaman login berhasil dimuat dengan form yang benar', async ({ page }) => {
    await page.goto('/login');

    // Cek judul dan branding
    await expect(page).toHaveTitle(/Masuk ke Sistem|Sistem Terpadu/);
    await expect(page.getByRole('heading', { name: 'Sistem Terpadu' })).toBeVisible();

    // Cek input field
    await expect(page.locator('#username')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.getByRole('button', { name: /Masuk ke Sistem/i })).toBeVisible();
  });

  test('Gagal login dengan kata sandi salah menampilkan pesan error', async ({ page }) => {
    await page.goto('/login');

    await page.locator('#username').fill('admin');
    await page.locator('#password').fill('passwordsalah123');
    await page.getByRole('button', { name: /Masuk ke Sistem/i }).click();

    // Tunggu pesan error muncul
    const alert = page.locator('text=Username atau kata sandi tidak valid').or(page.getByRole('alert'));
    await expect(alert).toBeVisible();
  });

  test('Berhasil login sebagai Admin dan diarahkan ke Dashboard', async ({ page }) => {
    await page.goto('/login');

    await page.locator('#username').fill('admin');
    await page.locator('#password').fill('admin123');
    await page.getByRole('button', { name: /Masuk ke Sistem/i }).click();

    // URL harus berpindah ke /dashboard
    await page.waitForURL('**/dashboard');
    await expect(page).toHaveURL(/.*dashboard/);

    // Sidebar dan konten dashboard harus terlihat
    await expect(page.locator('text=Dashboard').first()).toBeVisible();
    await expect(page.locator('text=Administrator').first()).toBeVisible();
  });

  test('Logout mengembalikan pengguna ke halaman login', async ({ page }) => {
    await page.goto('/login');

    await page.locator('#username').fill('admin');
    await page.locator('#password').fill('admin123');
    await page.getByRole('button', { name: /Masuk ke Sistem/i }).click();

    await page.waitForURL('**/dashboard');

    // Tombol logout ada di dalam menu pengguna pada header
    await page.getByRole('button', { name: 'Menu pengguna' }).click();
    await page.getByRole('menuitem', { name: /Keluar/i }).click();

    await page.waitForURL('**/login');
    await expect(page).toHaveURL(/.*login/);
  });
});
