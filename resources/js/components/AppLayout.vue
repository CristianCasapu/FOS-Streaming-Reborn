<template>
    <div class="min-h-screen bg-gray-100">
        <nav class="bg-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <!-- Custom Logo -->
                            <div v-if="logoUrl" class="flex items-center">
                                <img :src="logoUrl" alt="FOS Streaming v70" class="h-10 w-auto max-w-xs object-contain">
                            </div>
                            <!-- Default Logo -->
                            <div v-else class="flex items-center">
                                <div class="h-10 w-10 bg-indigo-600 rounded-lg flex items-center justify-center">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <span class="ml-3 text-xl font-bold text-gray-900">FOS Streaming v70</span>
                            </div>
                        </div>
                        <div class="hidden sm:ml-8 sm:flex sm:space-x-4">
                            <router-link to="/dashboard" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" active-class="!border-indigo-500 !text-gray-900">
                                Dashboard
                            </router-link>
                            <router-link to="/streams" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" active-class="!border-indigo-500 !text-gray-900">
                                Streams
                            </router-link>
                            <router-link to="/subscribers" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" active-class="!border-indigo-500 !text-gray-900">
                                Subscribers
                            </router-link>
                            <router-link to="/categories" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" active-class="!border-indigo-500 !text-gray-900">
                                Categories
                            </router-link>
                            <router-link to="/transcodes" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" active-class="!border-indigo-500 !text-gray-900">
                                Transcodes
                            </router-link>

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

// Security dropdown state
const showSecurityDropdown = ref(false);
const isSecurityRoute = computed(() => route.path.startsWith('/security'));

// Branding
const logoUrl = ref(null);

// Load logo from settings
onMounted(async () => {
    try {
        const response = await settingsAPI.get();
        if (response.data.success && response.data.data.logourl) {
            logoUrl.value = response.data.data.logourl;
        }
    } catch (error) {
        console.error('Failed to load logo:', error);
        // Fail silently - use default logo
    }
});

const handleLogout = async () => {
    await authStore.logout();
    router.push({ name: 'Login' });
};
</script>
