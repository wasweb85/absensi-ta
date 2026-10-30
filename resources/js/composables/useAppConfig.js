import { ref } from 'vue';
import api from '@/services/api';

const appName = ref('Sistem Terpadu Presensi');
const appLogo = ref('');
const appAddress = ref('');
const appContact = ref('');
const appTahunAjaran = ref('2026/2027');
const isConfigLoaded = ref(false);

export function setBrowserFavicon(iconUrl) {
  if (typeof document === 'undefined') return;

  const targetUrl = iconUrl || '/favicon.ico';
  let links = document.querySelectorAll("link[rel*='icon']");

  if (links.length === 0) {
    const link = document.createElement('link');
    link.type = targetUrl.startsWith('data:image/svg') ? 'image/svg+xml' : 'image/png';
    link.rel = 'icon';
    link.href = targetUrl;
    document.getElementsByTagName('head')[0].appendChild(link);
  } else {
    links.forEach((el) => {
      el.href = targetUrl;
    });
  }
}

export function useAppConfig() {
  const loadAppConfig = async (force = false) => {
    // 1. Cek cache session agar instan tanpa loading
    if (!force) {
      const cached = sessionStorage.getItem('app_public_config');
      if (cached) {
        try {
          const parsed = JSON.parse(cached);
          appName.value = parsed.nama_instansi || 'Sistem Terpadu Presensi';
          appLogo.value = parsed.logo_lembaga || '';
          appAddress.value = parsed.alamat_instansi || '';
          appContact.value = parsed.kontak_instansi || '';
          appTahunAjaran.value = parsed.tahun_ajaran_aktif || '2026/2027';
          if (appLogo.value) {
            setBrowserFavicon(appLogo.value);
          }
          isConfigLoaded.value = true;
        } catch (e) {}
      }
    }

    // 2. Fetch fresh dari server
    try {
      const res = await api.get('/pengaturan/public');
      if (res.data?.success && res.data?.data) {
        const data = res.data.data;
        appName.value = data.nama_instansi || 'Sistem Terpadu Presensi';
        appLogo.value = data.logo_lembaga || '';
        appAddress.value = data.alamat_instansi || '';
        appContact.value = data.kontak_instansi || '';
        appTahunAjaran.value = data.tahun_ajaran_aktif || '2026/2027';

        sessionStorage.setItem('app_public_config', JSON.stringify(data));

        if (appLogo.value) {
          setBrowserFavicon(appLogo.value);
        }
        isConfigLoaded.value = true;
      }
    } catch (e) {
      // Biarkan fallback default jika offline
    }
  };

  const updateConfig = (newConfig) => {
    if (!newConfig) return;
    if (newConfig.nama_instansi !== undefined) appName.value = newConfig.nama_instansi;
    if (newConfig.logo_lembaga !== undefined) {
      appLogo.value = newConfig.logo_lembaga;
      setBrowserFavicon(newConfig.logo_lembaga);
    }
    if (newConfig.alamat_instansi !== undefined) appAddress.value = newConfig.alamat_instansi;
    if (newConfig.kontak_instansi !== undefined) appContact.value = newConfig.kontak_instansi;
    if (newConfig.tahun_ajaran_aktif !== undefined) appTahunAjaran.value = newConfig.tahun_ajaran_aktif;

    sessionStorage.setItem('app_public_config', JSON.stringify({
      nama_instansi: appName.value,
      logo_lembaga: appLogo.value,
      alamat_instansi: appAddress.value,
      kontak_instansi: appContact.value,
      tahun_ajaran_aktif: appTahunAjaran.value,
    }));
  };

  return {
    appName,
    appLogo,
    appAddress,
    appContact,
    appTahunAjaran,
    isConfigLoaded,
    loadAppConfig,
    updateConfig,
    setBrowserFavicon,
  };
}

