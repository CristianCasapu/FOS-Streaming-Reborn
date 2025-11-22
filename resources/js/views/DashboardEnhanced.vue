<template>
    <!-- Loading State -->
    <div v-if="loading" class="min-h-screen bg-gray-100 flex items-center justify-center">
        <div class="text-center">
            <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-gray-600">Loading dashboard...</p>
        </div>
    </div>

    <!-- Main Dashboard with AppLayout -->
    <AppLayout v-if="!loading">
        <!-- Page Header -->
        <div class="px-4 py-6 sm:px-0">
            <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
            <p class="mt-2 text-sm text-gray-600">Monitor your streaming platform at a glance</p>
        </div>

        <!-- System Alerts -->
        <div v-if="alerts.length > 0" class="mt-4 px-4 sm:px-0">
                <div v-for="(alert, index) in alerts" :key="index"
                     :class="['rounded-md p-4 mb-3', alert.type === 'error' ? 'bg-red-50 border-l-4 border-red-400' : 'bg-yellow-50 border-l-4 border-yellow-400']">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg :class="['h-5 w-5', alert.type === 'error' ? 'text-red-400' : 'text-yellow-400']" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p :class="['text-sm font-medium', alert.type === 'error' ? 'text-red-800' : 'text-yellow-800']">
                                {{ alert.message }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 px-4 sm:px-0">
            <!-- Online Streams -->
            <div @click="router.push('/streams')" class="bg-white overflow-hidden shadow-lg rounded-lg hover:shadow-xl transition duration-300 cursor-pointer">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Online Streams</dt>
                                    <dd class="text-3xl font-bold text-green-600">{{ stats.onlineStreams || 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Offline Streams -->
            <div @click="router.push('/streams')" class="bg-white overflow-hidden shadow-lg rounded-lg hover:shadow-xl transition duration-300 cursor-pointer">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Offline Streams</dt>
                                    <dd class="text-3xl font-bold text-gray-900">{{ stats.offlineStreams || 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Total Streams -->
            <div @click="router.push('/streams')" class="bg-white overflow-hidden shadow-lg rounded-lg hover:shadow-xl transition duration-300 cursor-pointer">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Streams</dt>
                                    <dd class="text-3xl font-bold text-gray-900">{{ stats.totalStreams || 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Total Subscribers -->
            <div @click="router.push('/subscribers')" class="bg-white overflow-hidden shadow-lg rounded-lg hover:shadow-xl transition duration-300 cursor-pointer">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Subscribers</dt>
                                    <dd class="text-3xl font-bold text-gray-900">{{ stats.totalSubscribers || 0 }}</dd>
                                    <dd class="text-sm text-gray-500">{{ stats.activeSubscribers || 0 }} Active</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Resources & Security -->
            <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2 px-4 sm:px-0">
                <!-- System Resources Panel -->
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                            <svg class="h-5 w-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                            </svg>
                            System Resources
                        </h2>
                    </div>
                    <div class="p-6 space-y-6">
                        <!-- Disk Space -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700">Disk Space</span>
                                <span class="text-sm text-gray-600">{{ systemStats.disk?.percentage || 0 }}% Free</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div
                                    :class="['h-2.5 rounded-full transition-all', systemStats.disk?.percentage < 20 ? 'bg-red-600' : 'bg-green-600']"
                                    :style="{ width: `${systemStats.disk?.percentage || 0}%` }"
                                ></div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ systemStats.disk?.free || 0 }} MB / {{ systemStats.disk?.total || 0 }} MB</p>
                        </div>

                        <!-- CPU Usage -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700">CPU Usage</span>
                                <span class="text-sm text-gray-600">{{ systemStats.cpu?.percentage || 0 }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div
                                    :class="['h-2.5 rounded-full transition-all', systemStats.cpu?.percentage > 75 ? 'bg-red-600' : 'bg-blue-600']"
                                    :style="{ width: `${systemStats.cpu?.percentage || 0}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Memory Usage -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700">Memory Usage</span>
                                <span class="text-sm text-gray-600">{{ systemStats.memory?.percentage || 0 }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div
                                    :class="['h-2.5 rounded-full transition-all', systemStats.memory?.percentage > 75 ? 'bg-red-600' : 'bg-purple-600']"
                                    :style="{ width: `${systemStats.memory?.percentage || 0}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Overview Panel -->
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                            <svg class="h-5 w-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Security Overview
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-4 bg-red-50 rounded-lg">
                                <div class="text-2xl font-bold text-red-600">{{ securityStats.banned_ips || 0 }}</div>
                                <div class="text-sm text-gray-600 mt-1">Banned IPs</div>
                            </div>
                            <div class="text-center p-4 bg-green-50 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">{{ securityStats.whitelisted_ips || 0 }}</div>
                                <div class="text-sm text-gray-600 mt-1">Whitelisted</div>
                            </div>
                        </div>
                        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                            <div class="text-center">
                                <div :class="['text-2xl font-bold', securityStats.recent_events > 10 ? 'text-red-600' : 'text-gray-900']">
                                    {{ securityStats.recent_events || 0 }}
                                </div>
                                <div class="text-sm text-gray-600 mt-1">Security Events (24h)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities & Active Streams -->
            <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2 px-4 sm:px-0">
                <!-- Recent Activities -->
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Recent Activities</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stream</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="activity in activities" :key="activity.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ activity.user }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ activity.stream }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ activity.formatted_date }}</td>
                                </tr>
                                <tr v-if="activities.length === 0">
                                    <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">No recent activities</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Active Streams -->
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Active Streams</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stream</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="stream in activeStreams" :key="stream.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ stream.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ stream.category }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            LIVE
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="activeStreams.length === 0">
                                    <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">No active streams</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Category Distribution -->
            <div class="mt-8 px-4 sm:px-0">
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Stream Distribution by Category</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div v-for="category in categories" :key="category.id" class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-700">{{ category.name }}</span>
                                <span class="text-sm text-gray-600">{{ category.streams_count }} streams ({{ category.percentage }}%)</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    class="h-2 rounded-full bg-indigo-600 transition-all"
                                    :style="{ width: `${category.percentage}%` }"
                                ></div>
                            </div>
                        </div>
                        <p v-if="categories.length === 0" class="text-center text-sm text-gray-500 py-4">
                            No categories with streams found
                        </p>
                    </div>
                </div>
            </div>

        <!-- Quick Actions -->
        <div class="mt-8 px-4 sm:px-0">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <button @click="router.push('/streams')" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition duration-300 text-center">
                    <div class="flex flex-col items-center">
                        <svg class="h-8 w-8 text-indigo-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <h3 class="text-sm font-medium text-gray-900">Streams</h3>
                    </div>
                </button>
                <button @click="router.push('/subscribers')" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition duration-300 text-center">
                    <div class="flex flex-col items-center">
                        <svg class="h-8 w-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <h3 class="text-sm font-medium text-gray-900">Subscribers</h3>
                    </div>
                </button>
                <button @click="router.push('/categories')" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition duration-300 text-center">
                    <div class="flex flex-col items-center">
                        <svg class="h-8 w-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <h3 class="text-sm font-medium text-gray-900">Categories</h3>
                    </div>
                </button>
                <button @click="router.push('/security/ipblocks')" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition duration-300 text-center">
                    <div class="flex flex-col items-center">
                        <svg class="h-8 w-8 text-yellow-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        <h3 class="text-sm font-medium text-gray-900">IP Blocks</h3>
                    </div>
                </button>
                <button @click="router.push('/settings')" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition duration-300 text-center">
                    <div class="flex flex-col items-center">
                        <svg class="h-8 w-8 text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <h3 class="text-sm font-medium text-gray-900">Settings</h3>
                    </div>
                </button>
                <button @click="router.push('/transcodes')" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition duration-300 text-center">
                    <div class="flex flex-col items-center">
                        <svg class="h-8 w-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                        </svg>
                        <h3 class="text-sm font-medium text-gray-900">Transcodes</h3>
                    </div>
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { dashboardAPI } from '../services/api';
import AppLayout from '../components/AppLayout.vue';

const router = useRouter();

// Reactive data with default values
const stats = ref({
    totalStreams: 0,
    onlineStreams: 0,
    offlineStreams: 0,
    totalSubscribers: 0,
    activeSubscribers: 0,
    inactiveSubscribers: 0
});
const activities = ref([]);
const activeStreams = ref([]);
const categories = ref([]);
const securityStats = ref({
    banned_ips: 0,
    whitelisted_ips: 0,
    recent_events: 0,
    failed_logins_1h: 0
});
const systemStats = ref({
    disk: { free: 0, total: 0, used: 0, percentage: 0 },
    cpu: { percentage: 0 },
    memory: { used: 0, total: 0, percentage: 0 }
});
const alerts = ref([]);

const loading = ref(true);

// Fetch all dashboard data
const fetchDashboardData = async () => {
    try {
        loading.value = true;

        // Fetch all data in parallel
        const [
            statsRes,
            activitiesRes,
            streamsRes,
            categoriesRes,
            securityRes,
            systemRes,
            alertsRes
        ] = await Promise.all([
            dashboardAPI.getStats(),
            dashboardAPI.getActivities(10),
            dashboardAPI.getStreams(5),
            dashboardAPI.getCategories(5),
            dashboardAPI.getSecurity(),
            dashboardAPI.getSystem(),
            dashboardAPI.getAlerts()
        ]);

        // Update reactive data - only if data exists
        if (statsRes?.data?.data) {
            stats.value = statsRes.data.data;
        }
        if (activitiesRes?.data?.data) {
            activities.value = activitiesRes.data.data;
        }
        if (streamsRes?.data?.data) {
            activeStreams.value = streamsRes.data.data;
        }
        if (categoriesRes?.data?.data?.categories) {
            categories.value = categoriesRes.data.data.categories;
        }
        if (securityRes?.data?.data) {
            securityStats.value = securityRes.data.data;
        }
        if (systemRes?.data?.data) {
            systemStats.value = systemRes.data.data;
        }
        if (alertsRes?.data?.data) {
            alerts.value = alertsRes.data.data;
        }

    } catch (error) {
        console.error('Error fetching dashboard data:', error);
        console.error('Error details:', error.response || error.message);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchDashboardData();
});
</script>
