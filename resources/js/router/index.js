import { createRouter, createWebHistory } from 'vue-router';
import LoginView from '../views/auth/LoginView.vue';
import AppLayout from '../layouts/AppLayout.vue';
import DashboardView from '../views/dashboard/DashboardView.vue';

// Views Absensi
import ScannerView from '../views/absensi/ScannerView.vue';
import MonitoringView from '../views/absensi/MonitoringView.vue';
import RekapMatrixView from '../views/absensi/RekapMatrixView.vue';
import RiwayatAbsensiView from '../views/absensi/RiwayatAbsensiView.vue';
import SiswaView from '../views/absensi/SiswaView.vue';
import AgendaLiburView from '../views/absensi/AgendaLiburView.vue';

// Views SPP
import TransaksiSppView from '../views/spp/TransaksiSppView.vue';
import TagihanSppView from '../views/spp/TagihanSppView.vue';
import InputCepatView from '../views/spp/InputCepatView.vue';
import PengingatWaView from '../views/spp/PengingatWaView.vue';
import RiwayatSppView from '../views/spp/RiwayatSppView.vue';

// Views Master
import GuruView from '../views/master/GuruView.vue';
import KelasView from '../views/master/KelasView.vue';
import PengaturanView from '../views/master/PengaturanView.vue';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: LoginView,
        meta: { guestOnly: true, title: 'Masuk ke Sistem' },
    },
    {
        path: '/',
        component: AppLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                redirect: '/dashboard',
            },
            {
                path: 'dashboard',
                name: 'dashboard',
                component: DashboardView,
                meta: { title: 'Dashboard Utama' },
            },
            // Modul Absensi
            {
                path: 'absensi/scanner',
                name: 'absensi-scanner',
                component: ScannerView,
                meta: { title: 'Scanner Absensi' },
            },
            {
                path: 'absensi/monitoring',
                name: 'absensi-monitoring',
                component: MonitoringView,
                meta: { title: 'Monitoring Absensi Realtime' },
            },
            {
                path: 'absensi/rekap',
                name: 'absensi-rekap',
                component: RekapMatrixView,
                meta: { title: 'Rekap Matrix Absensi' },
            },
            {
                path: 'absensi/riwayat',
                name: 'absensi-riwayat',
                component: RiwayatAbsensiView,
                meta: { title: 'Riwayat Absensi' },
            },
            {
                path: 'absensi/siswa',
                name: 'absensi-siswa',
                component: SiswaView,
                meta: { title: 'Data Santri' },
            },
            {
                path: 'absensi/agenda-libur',
                name: 'absensi-agenda-libur',
                component: AgendaLiburView,
                meta: { title: 'Agenda & Hari Libur', roles: ['admin', 'sekretaris'] },
            },
            // Modul SPP
            {
                path: 'spp/transaksi',
                name: 'spp-transaksi',
                component: TransaksiSppView,
                meta: { title: 'Kasir Pembayaran SPP', roles: ['admin', 'bendahara'] },
            },
            {
                path: 'spp/tagihan',
                name: 'spp-tagihan',
                component: TagihanSppView,
                meta: { title: 'Tagihan & Tunggakan SPP', roles: ['admin', 'bendahara', 'kepala'] },
            },
            {
                path: 'spp/input-cepat',
                name: 'spp-input-cepat',
                component: InputCepatView,
                meta: { title: 'Input Cepat SPP', roles: ['admin', 'bendahara'] },
            },
            {
                path: 'spp/pengingat-wa',
                name: 'spp-pengingat-wa',
                component: PengingatWaView,
                meta: { title: 'Broadcast Pengingat WA', roles: ['admin', 'bendahara'] },
            },
            {
                path: 'spp/riwayat',
                name: 'spp-riwayat',
                component: RiwayatSppView,
                meta: { title: 'Riwayat Transaksi SPP', roles: ['admin', 'bendahara', 'kepala'] },
            },
            // Master & Pengaturan
            {
                path: 'guru',
                name: 'guru',
                component: GuruView,
                meta: { title: 'Manajemen Guru & Pengurus', roles: ['admin'] },
            },
            {
                path: 'kelas',
                name: 'kelas',
                component: KelasView,
                meta: { title: 'Data Jilid & Tarif SPP', roles: ['admin'] },
            },
            {
                path: 'pengaturan',
                name: 'pengaturan',
                component: PengaturanView,
                meta: { title: 'Pengaturan Sistem', roles: ['admin'] },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/dashboard',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('auth_token');
    const userStr = localStorage.getItem('auth_user');
    const user = userStr ? JSON.parse(userStr) : null;
    const userRole = user?.role || '';

    if (to.meta.title) {
        document.title = `${to.meta.title} - Sistem Terpadu TA`;
    }

    if (to.meta.requiresAuth && !token) {
        return next({ name: 'login' });
    }

    if (to.meta.guestOnly && token) {
        return next({ name: 'dashboard' });
    }

    // Role-based route authorization
    if (to.meta.roles && to.meta.roles.length > 0) {
        if (!to.meta.roles.includes(userRole)) {
            return next({ name: 'dashboard' });
        }
    }

    next();
});

export default router;
