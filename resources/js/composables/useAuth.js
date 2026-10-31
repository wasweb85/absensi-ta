import { ref, computed } from 'vue';
import api from '../services/api';

const user = ref(JSON.parse(localStorage.getItem('auth_user') || 'null'));
const token = ref(localStorage.getItem('auth_token') || null);
const loading = ref(false);
const error = ref(null);

export function useAuth() {
    const isAuthenticated = computed(() => !!token.value);
    const role = computed(() => user.value?.role || '');

    const isAdmin = computed(() => role.value === 'admin');
    const isKepala = computed(() => role.value === 'kepala');
    const isSekretaris = computed(() => role.value === 'sekretaris');
    const isBendahara = computed(() => role.value === 'bendahara');
    const isGuru = computed(() => role.value === 'guru');

    // Matriks Wewenang Baru (RBAC Standar Lembaga Pendidikan)
    const canScanQr = computed(() => ['admin', 'sekretaris', 'guru'].includes(role.value));
    const canEditAbsensi = computed(() => ['admin', 'sekretaris', 'guru'].includes(role.value));
    const canDoAbsensi = computed(() => ['admin', 'kepala', 'sekretaris', 'bendahara', 'guru'].includes(role.value));
    const canViewRekapMatrix = computed(() => ['admin', 'kepala', 'sekretaris', 'bendahara', 'guru'].includes(role.value));
    
    // Master Santri & Cetak QR: Khusus Struktural (Admin, Kepala, Sekretaris, Bendahara)
    const canViewSantri = computed(() => ['admin', 'kepala', 'sekretaris', 'bendahara'].includes(role.value));
    const canManageSantri = computed(() => ['admin', 'sekretaris'].includes(role.value));
    const canPrintQr = computed(() => ['admin', 'sekretaris'].includes(role.value));

    // Data Guru & Pengurus (Admin dapat mengelola, Kepala dapat memonitor/melihat)
    const canViewGuru = computed(() => ['admin', 'kepala'].includes(role.value));
    const canManageGuru = computed(() => ['admin'].includes(role.value));

    // Layanan WhatsApp
    const canViewWaLogs = computed(() => ['admin', 'kepala', 'sekretaris', 'bendahara'].includes(role.value));
    const canManageWaLogs = computed(() => ['admin', 'sekretaris'].includes(role.value));

    // Agenda & Pengaturan
    const canViewAgenda = computed(() => ['admin', 'kepala', 'sekretaris', 'bendahara', 'guru'].includes(role.value));
    const canManageAgenda = computed(() => ['admin'].includes(role.value));
    const canManageSettings = computed(() => ['admin'].includes(role.value));
    const canAccessChatbot = computed(() => ['admin'].includes(role.value));

    const kelasBinaan = computed(() => user.value?.nama_kelas || null);

    const roleLabel = computed(() => {
        switch (role.value) {
            case 'admin': return 'Administrator';
            case 'kepala': return 'Kepala TPQ';
            case 'sekretaris': return 'Sekretaris';
            case 'bendahara': return 'Bendahara';
            case 'guru': return 'Pengajar';
            default: return 'Pengguna';
        }
    });

    const login = async (username, password) => {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.post('/login', { username, password });
            if (response.data.success) {
                token.value = response.data.token;
                user.value = response.data.user;
                localStorage.setItem('auth_token', response.data.token);
                localStorage.setItem('auth_user', JSON.stringify(response.data.user));
                return { success: true };
            }
            return { success: false, message: response.data.message };
        } catch (err) {
            const msg = err.response?.data?.message || err.response?.data?.errors?.username?.[0] || 'Login gagal, periksa kredensial Anda.';
            error.value = msg;
            return { success: false, message: msg };
        } finally {
            loading.value = false;
        }
    };

    const logout = async () => {
        try {
            if (token.value) {
                await api.post('/logout');
            }
        } catch (e) {
            // Abaikan error saat logout
        } finally {
            token.value = null;
            user.value = null;
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.location.href = '/login';
        }
    };

    const fetchUser = async () => {
        if (!token.value) return null;
        try {
            const response = await api.get('/me');
            if (response.data.success) {
                user.value = response.data.user;
                localStorage.setItem('auth_user', JSON.stringify(response.data.user));
                return user.value;
            }
        } catch (e) {
            token.value = null;
            user.value = null;
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
        }
        return null;
    };

    return {
        user,
        token,
        loading,
        error,
        isAuthenticated,
        role,
        isAdmin,
        isKepala,
        isSekretaris,
        isBendahara,
        isGuru,
        canViewSantri,
        canManageSantri,
        canPrintQr,
        canViewGuru,
        canManageGuru,
        canViewWaLogs,
        canManageWaLogs,
        canManageAgenda,
        canViewAgenda,
        canManageSettings,
        canDoAbsensi,
        canViewRekapMatrix,
        canScanQr,
        canEditAbsensi,
        canAccessChatbot,
        kelasBinaan,
        roleLabel,
        login,
        logout,
        fetchUser,
    };
}
