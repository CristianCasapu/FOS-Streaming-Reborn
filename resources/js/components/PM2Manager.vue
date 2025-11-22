<template>
    <div class="space-y-6">
        <!-- PM2 Workers Section -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-purple-500 to-indigo-600 text-white">
                <h3 class="text-lg font-semibold flex items-center">
                    <svg class="h-6 w-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                    </svg>
                    PM2 Background Workers
                </h3>
                <p class="text-sm opacity-90 mt-1">Manage background job processors for stream imports and FFprobe analysis</p>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="p-12 text-center">
                <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-4 text-gray-600">Loading PM2 status...</p>
            </div>

            <!-- PM2 Not Installed Warning -->
            <div v-else-if="!pm2Installed" class="p-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-yellow-400 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <div class="text-sm text-yellow-800">
                            <p class="font-medium">PM2 Not Installed</p>
                            <p class="mt-1">PM2 is not installed on this system. Install it with: <code class="bg-yellow-100 px-1 rounded">npm install -g pm2</code></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PM2 Workers Table -->
            <div v-else class="p-6">
                <!-- Control Buttons -->
                <div class="mb-4 flex space-x-2">
                    <button
                        @click="controlWorkers('start')"
                        :disabled="processing"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 text-sm flex items-center"
                    >
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Start All
                    </button>
                    <button
                        @click="controlWorkers('stop')"
                        :disabled="processing"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 text-sm flex items-center"
                    >
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                        </svg>
                        Stop All
                    </button>
                    <button
                        @click="controlWorkers('restart')"
                        :disabled="processing"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 text-sm flex items-center"
                    >
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Restart All
                    </button>
                    <button
                        @click="refreshStatus"
                        :disabled="processing"
                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 disabled:opacity-50 text-sm flex items-center ml-auto"
                    >
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh
                    </button>
                </div>

                <!-- Workers Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Worker</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPU</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Memory</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uptime</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Restarts</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-if="!workers || workers.length === 0">
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500 text-sm">
                                    No PM2 workers running. Click "Start All" to start them.
                                </td>
                            </tr>
                            <tr v-for="worker in workers" :key="worker.pm_id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ worker.name }}</div>
                                    <div class="text-xs text-gray-500">ID: {{ worker.pm_id }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getStatusClass(worker.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                        {{ worker.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ worker.cpu }}%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ formatMemory(worker.memory) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ formatUptime(worker.uptime) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ worker.restarts }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Job Queue Statistics -->
        <div v-if="pm2Installed && queueStats" class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-blue-500 to-cyan-600 text-white">
                <h3 class="text-lg font-semibold">Job Queue Statistics</h3>
                <p class="text-sm opacity-90 mt-1">Current status of background job queues</p>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Stream Import Queue -->
                    <div v-if="queueStats.stream_import" class="border border-gray-200 rounded-lg p-4">
                        <h4 class="text-md font-semibold text-gray-900 mb-3">Stream Import Queue</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Pending:</span>
                                <span class="text-sm font-medium text-yellow-600">{{ queueStats.stream_import.pending }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Processing:</span>
                                <span class="text-sm font-medium text-blue-600">{{ queueStats.stream_import.processing }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Completed:</span>
                                <span class="text-sm font-medium text-green-600">{{ queueStats.stream_import.completed }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Failed:</span>
                                <span class="text-sm font-medium text-red-600">{{ queueStats.stream_import.failed }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-gray-200">
                                <span class="text-sm font-semibold text-gray-900">Total:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ queueStats.stream_import.total }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- FFprobe Analysis Queue -->
                    <div v-if="queueStats.ffprobe_analysis" class="border border-gray-200 rounded-lg p-4">
                        <h4 class="text-md font-semibold text-gray-900 mb-3">FFprobe Analysis Queue</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Pending:</span>
                                <span class="text-sm font-medium text-yellow-600">{{ queueStats.ffprobe_analysis.pending }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Processing:</span>
                                <span class="text-sm font-medium text-blue-600">{{ queueStats.ffprobe_analysis.processing }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Completed:</span>
                                <span class="text-sm font-medium text-green-600">{{ queueStats.ffprobe_analysis.completed }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Failed:</span>
                                <span class="text-sm font-medium text-red-600">{{ queueStats.ffprobe_analysis.failed }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-gray-200">
                                <span class="text-sm font-semibold text-gray-900">Total:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ queueStats.ffprobe_analysis.total }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Services Section -->
        <div v-if="pm2Installed && services" class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white">
                <h3 class="text-lg font-semibold">System Services</h3>
                <p class="text-sm opacity-90 mt-1">Core system services status and controls</p>
            </div>

            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="service in services" :key="service.name">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ service.display_name }}</div>
                                    <div class="text-xs text-gray-500">{{ service.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ service.description }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="service.status.active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                        {{ service.status.active ? 'Active' : 'Inactive' }}
                                    </span>
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ service.status.enabled ? 'Enabled' : 'Disabled' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                                    <button
                                        @click="controlService(service.name, 'start')"
                                        :disabled="processingService"
                                        class="text-green-600 hover:text-green-900 disabled:opacity-50"
                                    >
                                        Start
                                    </button>
                                    <button
                                        @click="controlService(service.name, 'stop')"
                                        :disabled="processingService"
                                        class="text-red-600 hover:text-red-900 disabled:opacity-50"
                                    >
                                        Stop
                                    </button>
                                    <button
                                        @click="controlService(service.name, 'restart')"
                                        :disabled="processingService"
                                        class="text-indigo-600 hover:text-indigo-900 disabled:opacity-50"
                                    >
                                        Restart
                                    </button>
                                    <button
                                        v-if="service.name === 'nginx'"
                                        @click="controlService(service.name, 'reload')"
                                        :disabled="processingService"
                                        class="text-blue-600 hover:text-blue-900 disabled:opacity-50"
                                    >
                                        Reload
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Message Display -->
        <div v-if="message" class="rounded-md p-4" :class="message.type === 'success' ? 'bg-green-50' : message.type === 'warning' ? 'bg-yellow-50' : 'bg-red-50'">
            <p :class="message.type === 'success' ? 'text-green-800' : message.type === 'warning' ? 'text-yellow-800' : 'text-red-800'" class="text-sm">
                {{ message.text }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { pm2API } from '../services/api';

const loading = ref(false);
const processing = ref(false);
const processingService = ref(false);
const message = ref(null);
const pm2Installed = ref(false);
const workers = ref([]);
const services = ref([]);
const queueStats = ref(null);

let refreshInterval = null;

const loadStatus = async () => {
    loading.value = true;
    try {
        const response = await pm2API.getStatus();

        if (response.data.success) {
            const data = response.data.data;
            pm2Installed.value = data.pm2_installed;
            workers.value = data.pm2_workers || [];
            services.value = data.system_services || [];
            queueStats.value = data.queue_stats || null;
        }
    } catch (error) {
        showMessage('Error loading PM2 status: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        loading.value = false;
    }
};

const refreshStatus = async () => {
    await loadStatus();
    showMessage('Status refreshed', 'success');
};

const controlWorkers = async (action) => {
    processing.value = true;
    try {
        let response;
        if (action === 'start') {
            response = await pm2API.start();
        } else if (action === 'stop') {
            response = await pm2API.stop();
        } else if (action === 'restart') {
            response = await pm2API.restart();
        }

        if (response.data.success) {
            showMessage(response.data.message, 'success');
            // Wait a bit for PM2 to update
            setTimeout(async () => {
                await loadStatus();
            }, 2000);
        }
    } catch (error) {
        showMessage('Error: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        processing.value = false;
    }
};

const controlService = async (service, action) => {
    if (!confirm(`Are you sure you want to ${action} ${service}?`)) {
        return;
    }

    processingService.value = true;
    try {
        const response = await pm2API.serviceAction(service, action);

        if (response.data.success) {
            showMessage(response.data.message, 'success');
            // Refresh status after a delay
            setTimeout(async () => {
                await loadStatus();
            }, 2000);
        }
    } catch (error) {
        const errorMsg = error.response?.data?.message || error.message;

        // Check if it's a sudo password error
        if (errorMsg.includes('Sudo password not configured')) {
            showMessage('Please configure sudo password in System Commands Configuration section above, then try again.', 'error');
        } else {
            showMessage('Error: ' + errorMsg, 'error');
        }
    } finally {
        processingService.value = false;
    }
};

const getStatusClass = (status) => {
    switch (status) {
        case 'online':
            return 'bg-green-100 text-green-800';
        case 'stopped':
            return 'bg-gray-100 text-gray-800';
        case 'errored':
            return 'bg-red-100 text-red-800';
        case 'launching':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-yellow-100 text-yellow-800';
    }
};

const formatMemory = (bytes) => {
    if (!bytes) return '0 MB';
    const mb = bytes / (1024 * 1024);
    return mb.toFixed(1) + ' MB';
};

const formatUptime = (timestamp) => {
    if (!timestamp) return 'N/A';
    const now = Date.now();
    const uptime = now - timestamp;
    const seconds = Math.floor(uptime / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (days > 0) return `${days}d ${hours % 24}h`;
    if (hours > 0) return `${hours}h ${minutes % 60}m`;
    if (minutes > 0) return `${minutes}m ${seconds % 60}s`;
    return `${seconds}s`;
};

const showMessage = (text, type = 'success') => {
    message.value = { text, type };
    setTimeout(() => message.value = null, 5000);
};

onMounted(() => {
    loadStatus();
    // Auto-refresh every 30 seconds
    refreshInterval = setInterval(loadStatus, 30000);
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>
