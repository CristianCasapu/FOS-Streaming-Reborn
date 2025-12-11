/**
 * Vue Router Configuration
 */

import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { ADMIN_PATH } from '../config';

// Import views
import Login from '../views/Login.vue';
import Dashboard from '../views/DashboardEnhanced.vue';
import StreamsList from '../views/Streams/StreamsList.vue';
import SubscribersList from '../views/Subscribers/SubscribersList.vue';
import CategoriesList from '../views/Categories/CategoriesList.vue';
import TranscodesList from '../views/Transcodes/TranscodesList.vue';
import IPBlocks from '../views/Security/IPBlocks.vue';
import UserAgentBlocks from '../views/Security/UserAgentBlocks.vue';
import AdvancedSecurity from '../views/Security/AdvancedSecurity.vue';
import StaffList from '../views/Staff/StaffList.vue';
import ActivitiesList from '../views/Activities/ActivitiesList.vue';
import Settings from '../views/Settings/Settings.vue';
import About from '../views/About.vue';

const routes = [
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/login',
        name: 'Login',
        component: Login,
        meta: { requiresGuest: true },
    },
    {
        path: '/dashboard',
        name: 'Dashboard',
        component: Dashboard,
        meta: { requiresAuth: true },
    },
    {
        path: '/streams',
        name: 'Streams',
        component: StreamsList,
        meta: { requiresAuth: true },
    },
    {
        path: '/streams/bouquets',
        name: 'Bouquets',
        component: () => import('../views/Subscribers/BouquetsList.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/streams/categories',
        name: 'Categories',
        component: CategoriesList,
        meta: { requiresAuth: true },
    },
    {
        path: '/streams/packages',
        name: 'Packages',
        component: () => import('../views/Subscribers/PackagesList.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/subscribers',
        name: 'Subscribers',
        component: SubscribersList,
        meta: { requiresAuth: true },
    },
    {
        path: '/subscribers/subscriptions',
        name: 'Subscriptions',
        component: () => import('../views/Subscribers/SubscriptionsList.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/subscribers/trials',
        name: 'Trials',
        component: () => import('../views/Subscribers/TrialsList.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/subscribers/activity',
        name: 'SubscriberActivity',
        component: () => import('../views/Subscribers/SubscriberActivity.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/security/ipblocks',
        name: 'IPBlocks',
        component: IPBlocks,
        meta: { requiresAuth: true },
    },
    {
        path: '/security/useragents',
        name: 'UserAgentBlocks',
        component: UserAgentBlocks,
        meta: { requiresAuth: true },
    },
    {
        path: '/security/advanced',
        name: 'AdvancedSecurity',
        component: AdvancedSecurity,
        meta: { requiresAuth: true },
    },
    {
        path: '/staff',
        name: 'Staff',
        component: StaffList,
        meta: { requiresAuth: true },
    },
    {
        path: '/activities',
        name: 'Activities',
        component: ActivitiesList,
        meta: { requiresAuth: true },
    },
    {
        path: '/settings',
        name: 'Settings',
        component: Settings,
        meta: { requiresAuth: true },
    },
    {
        path: '/about',
        name: 'About',
        component: About,
        meta: { requiresAuth: true },
    },
    // Detail Pages
    {
        path: '/subscribers/:id',
        name: 'SubscriberDetail',
        component: () => import('../views/Subscribers/SubscriberDetail.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/streams/:id',
        name: 'StreamDetail',
        component: () => import('../views/Streams/StreamDetail.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/streams/bouquets/:id',
        name: 'BouquetDetail',
        component: () => import('../views/Subscribers/BouquetDetail.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/streams/packages/:id',
        name: 'PackageDetail',
        component: () => import('../views/Subscribers/PackageDetail.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/subscribers/subscriptions/:id',
        name: 'SubscriptionDetail',
        component: () => import('../views/Subscribers/SubscriptionDetail.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/subscribers/trials/:id',
        name: 'TrialDetail',
        component: () => import('../views/Subscribers/TrialDetail.vue'),
        meta: { requiresAuth: true },
    },
    // New Routes - Phase 1 Foundation
    {
        path: '/audit-logs',
        name: 'AuditLogs',
        component: () => import('../views/AuditLogs/AuditLogViewer.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/resellers',
        name: 'Resellers',
        component: () => import('../views/Resellers/ResellersManagement.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/resellers/:id',
        name: 'ResellerDetail',
        component: () => import('../views/Resellers/ResellerDashboard.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/v2ray/nodes',
        name: 'V2RayNodes',
        component: () => import('../views/V2Ray/NodeManagement.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/metrics',
        name: 'Metrics',
        component: () => import('../views/Metrics/MetricsDashboard.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/devices',
        name: 'Devices',
        component: () => import('../views/Devices/DeviceManagement.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/health',
        name: 'Health',
        component: () => import('../views/Health/HealthMonitor.vue'),
        meta: { requiresAuth: true },
    },
];

const router = createRouter({
    history: createWebHistory(ADMIN_PATH),
    routes,
});

// Navigation guard
router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    // Check authentication status
    if (!authStore.isAuthenticated && !authStore.user) {
        await authStore.checkAuth();
    }

    // Handle routes that require authentication
    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        next({ name: 'Login' });
        return;
    }

    // Handle routes for guests only (like login)
    if (to.meta.requiresGuest && authStore.isAuthenticated) {
        next({ name: 'Dashboard' });
        return;
    }

    next();
});

export default router;
