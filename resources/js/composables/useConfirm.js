import { ref } from 'vue';

const isOpen = ref(false);
const title = ref('');
const description = ref('');
const confirmText = ref('Ya, Lanjutkan');
const cancelText = ref('Batal');
const variant = ref('destructive'); // 'destructive' | 'default' | 'warning' | 'info'
const icon = ref('trash'); // 'trash' | 'alert' | 'info' | 'refresh' | 'check'
const isAlert = ref(false);
const resolvePromise = ref(null);

export function useConfirm() {
  /**
   * Tampilkan dialog konfirmasi modern dan kembalikan Promise<boolean>.
   *
   * @param {Object} options
   * @param {string} options.title Judul dialog
   * @param {string} options.description Rincian keterangan aksi
   * @param {string} [options.confirmText] Label tombol konfirmasi
   * @param {string} [options.cancelText] Label tombol batal
   * @param {'destructive'|'default'|'warning'|'info'} [options.variant] Gaya visual tombol
   * @param {'trash'|'alert'|'info'|'refresh'|'check'} [options.icon] Ikon di header
   * @returns {Promise<boolean>}
   */
  const confirm = (options = {}) => {
    return new Promise((resolve) => {
      title.value = options.title || 'Konfirmasi Tindakan';
      description.value = options.description || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
      confirmText.value = options.confirmText || (options.variant === 'destructive' ? 'Ya, Hapus' : 'Ya, Lanjutkan');
      cancelText.value = options.cancelText || 'Batal';
      variant.value = options.variant || 'destructive';
      icon.value = options.icon || (options.variant === 'destructive' ? 'trash' : 'alert');
      isAlert.value = false;
      resolvePromise.value = resolve;
      isOpen.value = true;
    });
  };

  /**
   * Tampilkan alert dialog modern (hanya 1 tombol).
   *
   * @param {Object} options
   * @returns {Promise<void>}
   */
  const alert = (options = {}) => {
    return new Promise((resolve) => {
      title.value = options.title || 'Informasi';
      description.value = options.description || '';
      confirmText.value = options.confirmText || 'Mengerti';
      variant.value = options.variant || 'default';
      icon.value = options.icon || 'info';
      isAlert.value = true;
      resolvePromise.value = () => resolve();
      isOpen.value = true;
    });
  };

  const handleConfirm = () => {
    isOpen.value = false;
    if (resolvePromise.value) {
      resolvePromise.value(true);
      resolvePromise.value = null;
    }
  };

  const handleCancel = () => {
    isOpen.value = false;
    if (resolvePromise.value) {
      resolvePromise.value(false);
      resolvePromise.value = null;
    }
  };

  return {
    isOpen,
    title,
    description,
    confirmText,
    cancelText,
    variant,
    icon,
    isAlert,
    confirm,
    alert,
    handleConfirm,
    handleCancel,
  };
}

