<template>
    <div class="min-h-screen bg-gray-100">
        <nav class="bg-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <!-- Logo (Custom or Default) -->
                            <div class="flex items-center">
                                <img :src="logoUrl" alt="FOS Streaming Reborn" class="h-10 w-auto max-w-xs object-contain">
                            </div>
                        </div>
                        <div class="hidden sm:ml-8 sm:flex sm:space-x-4">
                            <router-link to="/dashboard" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" active-class="!border-indigo-500 !text-gray-900">
                                Dashboard
                            </router-link>

                            <!-- Streams Management Dropdown -->
                            <div class="relative inline-flex items-center" @mouseenter="showStreamsDropdown = true" @mouseleave="showStreamsDropdown = false">
                                <button class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" :class="isStreamsRoute ? '!border-indigo-500 !text-gray-900' : ''">
                                    Streams
                                    <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div v-show="showStreamsDropdown" class="absolute left-0 top-full w-64 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                    <div class="py-1">
                                        <router-link to="/streams" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Manage Streams
                                        </router-link>
                                        <router-link to="/streams/bouquets" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Bouquets
                                        </router-link>
                                        <router-link to="/streams/categories" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Categories
                                        </router-link>
                                        <router-link to="/streams/packages" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Packages
                                        </router-link>
                                    </div>
                                </div>
                            </div>

                            <!-- Subscribers Dropdown -->
                            <div class="relative inline-flex items-center" @mouseenter="showSubscribersDropdown = true" @mouseleave="showSubscribersDropdown = false">
                                <button class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" :class="isSubscribersRoute ? '!border-indigo-500 !text-gray-900' : ''">
                                    Subscribers
                                    <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div v-show="showSubscribersDropdown" class="absolute left-0 top-full w-64 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                    <div class="py-1">
                                        <router-link to="/subscribers" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Manage Subscribers
                                        </router-link>
                                        <router-link to="/subscribers/subscriptions" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Subscriptions
                                        </router-link>
                                        <router-link to="/subscribers/trials" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Trials
                                        </router-link>
                                        <router-link to="/subscribers/activity" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Activity
                                        </router-link>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Dropdown -->
                            <div class="relative inline-flex items-center" @mouseenter="showSecurityDropdown = true" @mouseleave="showSecurityDropdown = false">
                                <button class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" :class="isSecurityRoute ? '!border-indigo-500 !text-gray-900' : ''">
                                    Security
                                    <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div v-show="showSecurityDropdown" class="absolute left-0 top-full w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                    <div class="py-1">
                                        <router-link to="/security/ipblocks" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            IP Blocks
                                        </router-link>
                                        <router-link to="/security/useragents" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            User Agent Blocks
                                        </router-link>
                                        <router-link to="/security/advanced" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Advanced Security
                                        </router-link>
                                    </div>
                                </div>
                            </div>

                            <router-link to="/activities" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" active-class="!border-indigo-500 !text-gray-900">
                                Activities
                            </router-link>
                            <router-link to="/admins" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" active-class="!border-indigo-500 !text-gray-900">
                                Admins
                            </router-link>
                            <router-link to="/settings" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" active-class="!border-indigo-500 !text-gray-900">
                                Settings
                            </router-link>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-sm text-gray-700 mr-4">{{ user?.username || 'Admin' }}</span>
                        <button @click="handleLogout" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { settingsAPI } from '../services/api';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const user = computed(() => authStore.currentUser);

// Streams dropdown state
const showStreamsDropdown = ref(false);
const isStreamsRoute = computed(() => route.path.startsWith('/streams') || route.path.startsWith('/categories'));

// Security dropdown state
const showSecurityDropdown = ref(false);
const isSecurityRoute = computed(() => route.path.startsWith('/security'));

// Subscribers dropdown state
const showSubscribersDropdown = ref(false);
const isSubscribersRoute = computed(() => route.path.startsWith('/subscribers'));

// Branding - Default logo fallback
const logoUrl = ref('/assets/logo-default.svg');

// Load logo from settings
onMounted(async () => {
    try {
        const response = await settingsAPI.get();
        if (response.data.success && response.data.data.logourl) {
            logoUrl.value = response.data.data.logourl;
        }
    } catch (error) {
        console.error('Failed to load logo:', error);
        // Fail silently - use default logo already set
    }
});

const handleLogout = async () => {
    await authStore.logout();
    router.push({ name: 'Login' });
};
</script>
