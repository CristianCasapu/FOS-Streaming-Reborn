<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { healthAPI } from '../../services/api';

const health = ref(null);
const loading = ref(true);
const autoRefresh = ref(true);
let refreshInterval = null;

const fetchHealth = async () => {
    try {
        const response = await healthAPI.all();
        health.value = response.data.data;
        loading.value = false;
    } catch (error) {
        console.error('Failed to fetch health data:', error);
        loading.value = false;
    }
};

const toggleAutoRefresh = () => {
    autoRefresh.value = !autoRefresh.value;
    if (autoRefresh.value) {
        startAutoRefresh();
    } else {
        stopAutoRefresh();
    }
};

const startAutoRefresh = () => {
    refreshInterval = setInterval(fetchHealth, 5000); // Refresh every 5 seconds
};

const stopAutoRefresh = () => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
};

const getStatusColor = (status) => {
    const colors = {
        healthy: 'text-green-600',
        warning: 'text-yellow-600',
        critical: 'text-red-600',
        unhealthy: 'text-red-600',
        disabled: 'text-gray-400',
        unknown: 'text-gray-600',
    };
    return colors[status] || 'text-gray-600';
};

const getStatusIcon = (status) => {
    const icons = {
        healthy: '✓',
        warning: '⚠',
        critical: '✗',
        unhealthy: '✗',
        disabled: '○',
        unknown: '?',
    };
    return icons[status] || '?';
};

onMounted(() => {
    fetchHealth();
    startAutoRefresh();
});

onUnmounted(() => {
    stopAutoRefresh();
});
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">System Health</h1>
                <p class="mt-2 text-sm text-gray-600">Real-time monitoring of all system components</p>
            </div>
            <div class="flex gap-4">
                <button
                    @click="fetchHealth"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                >
                    Refresh Now
                </button>
                <button
                    @click="toggleAutoRefresh"
                    :class="autoRefresh ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-600 hover:bg-gray-700'"
                    class="px-4 py-2 text-white rounded-md"
                >
                    Auto-refresh: {{ autoRefresh ? 'ON' : 'OFF' }}
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="bg-white rounded-lg shadow p-12 text-center">
            <div class="text-gray-500">Loading health data...</div>
        </div>

        <!-- Health Dashboard -->
        <div v-else-if="health">
            <!-- Overall Status -->
            <div class="mb-8 bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">Overall Status</h2>
                        <p class="text-sm text-gray-500">{{ health.timestamp }}</p>
                    </div>
                    <div class="text-right">
                        <div :class="`text-4xl font-bold ${getStatusColor(health.overall_status)}`">
                            {{ getStatusIcon(health.overall_status) }} {{ health.overall_status.toUpperCase() }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Health Checks Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Database -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold">Database</h3>
                        <span :class="`text-2xl ${getStatusColor(health.checks.database.status)}`">
                            {{ getStatusIcon(health.checks.database.status) }}
                        </span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Latency:</span>
                            <span class="font-medium">{{ health.checks.database.latency_ms }}ms</span>
                        </div>
                        <div v-if="health.checks.database.connections" class="flex justify-between">
                            <span class="text-gray-600">Connections:</span>
                            <span class="font-medium">
                                {{ health.checks.database.connections.current }} / {{ health.checks.database.connections.max }}
                                ({{ health.checks.database.connections.usage_percent }}%)
                            </span>
                        </div>
                        <div class="text-xs text-gray-500 mt-2">{{ health.checks.database.message }}</div>
                    </div>
                </div>

                <!-- Redis -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold">Redis</h3>
                        <span :class="`text-2xl ${getStatusColor(health.checks.redis.status)}`">
                            {{ getStatusIcon(health.checks.redis.status) }}
                        </span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Latency:</span>
                            <span class="font-medium">{{ health.checks.redis.latency_ms }}ms</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Memory:</span>
                            <span class="font-medium">{{ health.checks.redis.memory_used }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Clients:</span>
                            <span class="font-medium">{{ health.checks.redis.connected_clients }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-2">{{ health.checks.redis.message }}</div>
                    </div>
                </div>

                <!-- Disk -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold">Disk Space</h3>
                        <span :class="`text-2xl ${getStatusColor(health.checks.disk.status)}`">
                            {{ getStatusIcon(health.checks.disk.status) }}
                        </span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Usage:</span>
                            <span class="font-medium">{{ health.checks.disk.used_percent }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Free:</span>
                            <span class="font-medium">{{ health.checks.disk.free_gb }} GB</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total:</span>
                            <span class="font-medium">{{ health.checks.disk.total_gb }} GB</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                            <div
                                class="h-2 rounded-full"
                                :class="health.checks.disk.used_percent > 90 ? 'bg-red-600' : health.checks.disk.used_percent > 80 ? 'bg-yellow-600' : 'bg-green-600'"
                                :style="`width: ${health.checks.disk.used_percent}%`"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- Memory -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold">Memory</h3>
                        <span :class="`text-2xl ${getStatusColor(health.checks.memory.status)}`">
                            {{ getStatusIcon(health.checks.memory.status) }}
                        </span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Usage:</span>
                            <span class="font-medium">{{ health.checks.memory.used_percent }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Free:</span>
                            <span class="font-medium">{{ health.checks.memory.free_mb }} MB</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total:</span>
                            <span class="font-medium">{{ health.checks.memory.total_mb }} MB</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                            <div
                                class="h-2 rounded-full"
                                :class="health.checks.memory.used_percent > 90 ? 'bg-red-600' : health.checks.memory.used_percent > 80 ? 'bg-yellow-600' : 'bg-green-600'"
                                :style="`width: ${health.checks.memory.used_percent}%`"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- CPU -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold">CPU</h3>
                        <span :class="`text-2xl ${getStatusColor(health.checks.cpu.status)}`">
                            {{ getStatusIcon(health.checks.cpu.status) }}
                        </span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Load (1m):</span>
                            <span class="font-medium">{{ health.checks.cpu.load_1min }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Cores:</span>
                            <span class="font-medium">{{ health.checks.cpu.cpu_cores }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Load %:</span>
                            <span class="font-medium">{{ health.checks.cpu.load_percent }}%</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-2">{{ health.checks.cpu.message }}</div>
                    </div>
                </div>

                <!-- Services -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold">Services</h3>
                        <span :class="`text-2xl ${getStatusColor(health.checks.services.status)}`">
                            {{ getStatusIcon(health.checks.services.status) }}
                        </span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div v-for="service in health.checks.services.services" :key="service.name" class="flex justify-between">
                            <span class="text-gray-600">{{ service.name }}:</span>
                            <span :class="service.status === 'running' ? 'text-green-600 font-medium' : 'text-red-600 font-medium'">
                                {{ service.status }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-500 mt-2">{{ health.checks.services.message }}</div>
                    </div>
                </div>

                <!-- Streams -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold">Streams</h3>
                        <span :class="`text-2xl ${getStatusColor(health.checks.streams.status)}`">
                            {{ getStatusIcon(health.checks.streams.status) }}
                        </span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total:</span>
                            <span class="font-medium">{{ health.checks.streams.total_enabled }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Active:</span>
                            <span class="font-medium">{{ health.checks.streams.active_streams }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Proxy:</span>
                            <span class="font-medium">{{ health.checks.streams.proxy_streams }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-2">{{ health.checks.streams.message }}</div>
                    </div>
                </div>

                <!-- Workers -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold">PM2 Workers</h3>
                        <span :class="`text-2xl ${getStatusColor(health.checks.workers.status)}`">
                            {{ getStatusIcon(health.checks.workers.status) }}
                        </span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total:</span>
                            <span class="font-medium">{{ health.checks.workers.total_workers }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Online:</span>
                            <span class="text-green-600 font-medium">{{ health.checks.workers.online }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Stopped:</span>
                            <span :class="health.checks.workers.stopped > 0 ? 'text-red-600 font-medium' : 'font-medium'">
                                {{ health.checks.workers.stopped }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-500 mt-2">{{ health.checks.workers.message }}</div>
                    </div>
                </div>

                <!-- V2Ray -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold">V2Ray</h3>
                        <span :class="`text-2xl ${getStatusColor(health.checks.v2ray.status)}`">
                            {{ getStatusIcon(health.checks.v2ray.status) }}
                        </span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Active Users:</span>
                            <span class="font-medium">{{ health.checks.v2ray.active_users }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Servers:</span>
                            <span class="font-medium">
                                {{ health.checks.v2ray.healthy_servers }} / {{ health.checks.v2ray.total_servers }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-500 mt-2">{{ health.checks.v2ray.message }}</div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </AppLayout>
</template>
