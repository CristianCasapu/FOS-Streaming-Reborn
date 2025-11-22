/**
 * Subscriber Portal Router Configuration
 */

import { createRouter, createWebHistory } from 'vue-router';

// Import views
import Home from '../views/subscriber/Home.vue';
import CategoryView from '../views/subscriber/CategoryView.vue';
import StreamView from '../views/subscriber/StreamView.vue';

const routes = [
    {
        path: '/',
        name: 'Home',
        component: Home,
    },
    {
        path: '/category/:id',
        name: 'Category',
        component: CategoryView,
    },
    {
        path: '/stream/:id',
        name: 'Stream',
        component: StreamView,
    },
];

const router = createRouter({
    history: createWebHistory('/'),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        } else {
            return { top: 0 };
        }
    },
});

export default router;
