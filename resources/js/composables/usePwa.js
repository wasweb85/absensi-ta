import { ref, onMounted } from 'vue';

const deferredPrompt = ref(null);
const isInstallable = ref(false);
const isInstalled = ref(false);
const isIos = ref(false);

// Cek apakah sudah berjalan dalam mode PWA Standalone (Sudah Terinstall)
const checkInstalled = () => {
  if (typeof window === 'undefined') return false;
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    window.navigator.standalone === true ||
    document.referrer.includes('android-app://')
  );
};

// Daftarkan Service Worker
export function initPwa() {
  if (typeof window === 'undefined') return;

  isInstalled.value = checkInstalled();

  // Deteksi iOS Safari
  const userAgent = window.navigator.userAgent.toLowerCase();
  isIos.value = /iphone|ipad|ipod/.test(userAgent) && !window.MSStream;

  // Tangkap event sebelum browser memunculkan prompt bawaan
  window.addEventListener('beforeinstallprompt', (e) => {
    // Cegah mini-infobar default Chrome di Android
    e.preventDefault();
    deferredPrompt.value = e;
    isInstallable.value = true;
  });

  // Tangkap event setelah aplikasi berhasil di-install
  window.addEventListener('appinstalled', () => {
    deferredPrompt.value = null;
    isInstallable.value = false;
    isInstalled.value = true;
    console.log('PWA Presensi TPQ berhasil diinstall di perangkat.');
  });

  // Daftarkan Service Worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker
        .register('/sw.js')
        .then((registration) => {
          console.log('PWA ServiceWorker registered with scope:', registration.scope);
        })
        .catch((error) => {
          console.warn('PWA ServiceWorker registration failed:', error);
        });
    });
  }
}

export function usePwa() {
  const installApp = async () => {
    if (!deferredPrompt.value) {
      return false;
    }

    try {
      // Munculkan popup instalasi resmi dari browser
      deferredPrompt.value.prompt();
      const choiceResult = await deferredPrompt.value.userChoice;

      if (choiceResult.outcome === 'accepted') {
        console.log('Pengguna menyetujui instalasi PWA');
        isInstalled.value = true;
      } else {
        console.log('Pengguna menolak instalasi PWA');
      }

      deferredPrompt.value = null;
      isInstallable.value = false;
      return choiceResult.outcome === 'accepted';
    } catch (err) {
      console.error('Error saat menjalankan instalasi PWA:', err);
      return false;
    }
  };

  return {
    isInstallable,
    isInstalled,
    isIos,
    installApp,
  };
}

