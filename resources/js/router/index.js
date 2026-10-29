import { createRouter, createWebHistory } from 'vue-router';
import AppLayout from '../layouts/AppLayout.vue';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/auth/LoginView.vue'),
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
                component: () => import('../views/dashboard/DashboardView.vue'),
                meta: { title: 'Dashboard' },
            },
            // Modul Absensi
            {
                path: 'absensi/scanner',
                name: 'absensi-scanner',
                component: () => import('../views/absensi/ScannerView.vue'),
                meta: { title: 'Scanner', roles: ['admin', 'sekretaris', 'guru'] },
            },
            {
                path: 'absensi/monitoring',
                name: 'absensi-monitoring',
                component: () => import('../views/absensi/MonitoringView.vue'),
                meta: { title: 'Monitoring' },
            },
            {
                path: 'absensi/rekap',
                name: 'absensi-rekap',
                component: () => import('../views/absensi/RekapMatrixView.vue'),
                meta: { title: 'Rekap', roles: ['admin', 'kepala', 'sekretaris', 'bendahara', 'guru'] },
            },
            {
                path: 'absensi/riwayat',
                redirect: '/absensi/monitoring',
            },
            {
                path: 'absensi/siswa',
                name: 'absensi-siswa',
                component: () => import('../views/absensi/SiswaView.vue'),
                meta: { title: 'Data Santri', roles: ['admin', 'kepala', 'sekretaris', 'bendahara'] },
            },
            {
                path: 'absensi/agenda-libur',
                name: 'absensi-agenda-libur',
                component: () => import('../views/absensi/AgendaLiburView.vue'),
                meta: { title: 'Agenda & Libur', roles: ['admin', 'sekretaris', 'kepala', 'bendahara', 'guru'] },
            },
            // Layanan WhatsApp & Notifikasi
            {
                path: 'wa/delivery-log',
                name: 'wa-delivery-log',
                component: () => import('../views/wa/DeliveryLogView.vue'),
                meta: { title: 'Delivery Log', roles: ['admin', 'kepala', 'sekretaris', 'bendahara'] },
            },
            {
                path: 'chatbot',
                name: 'chatbot',
                component: () => import('../views/chatbot/ChatbotView.vue'),
                meta: { title: 'Chatbot', roles: ['admin'] },
            },
            // Master & Pengaturan
            {
                path: 'guru',
                name: 'guru',
                component: () => import('../views/master/GuruView.vue'),
                meta: { title: 'Data Guru', roles: ['admin', 'kepala'] },
            },
            {
                path: 'kelas',
                name: 'kelas',
                component: () => import('../views/master/KelasView.vue'),
                meta: { title: 'Data Jilid', roles: ['admin'] },
            },
            {
                path: 'pengaturan',
                name: 'pengaturan',
                component: () => import('../views/master/PengaturanView.vue'),
                meta: { title: 'Pengaturan', roles: ['admin'] },
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
