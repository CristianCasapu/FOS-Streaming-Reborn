/**
 * Vue Router Configuration
 */

import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

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
import AdminsList from '../views/Admins/AdminsList.vue';
import ActivitiesList from '../views/Activities/ActivitiesList.vue';
import Settings from '../views/Settings/Settings.vue';

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
        path: '/subscribers',
        name: 'Subscribers',
        component: SubscribersList,
        meta: { requiresAuth: true },
    },
    {
        path: '/categories',
        name: 'Categories',
        component: CategoriesList,
        meta: { requiresAuth: true },
    },
    {
        path: '/transcodes',
        name: 'Transcodes',
        component: TranscodesList,
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
        path: '/admins',
        name: 'Admins',
        component: AdminsList,
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
];

const router = createRouter({
    history: createWebHistory('/admin'),
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
