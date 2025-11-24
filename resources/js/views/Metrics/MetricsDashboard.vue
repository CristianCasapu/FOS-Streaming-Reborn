<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { metricsAPI } from '../../services/api';

const metrics = ref(null);
const loading = ref(true);

const fetchMetrics = async () => {
    try {
        const response = await metricsAPI.dashboard();
        metrics.value = response.data.data;
    } catch (error) {
        console.error('Failed to fetch metrics:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchMetrics();
});
</script>

<template>
    <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold mb-8">Metrics Dashboard</h1>
        <div v-if="loading" class="text-center py-12">Loading metrics...</div>
        <div v-else-if="metrics" class="space-y-8">
            <!-- Platform Metrics -->
            <div>
                <h2 class="text-xl font-semibold mb-4">Platform</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-500">Total Streams</div>
                        <div class="mt-2 text-3xl font-semibold">{{ metrics.platform.total_streams }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-500">Active Streams</div>
                        <div class="mt-2 text-3xl font-semibold text-green-600">{{ metrics.platform.active_streams }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-500">Total Subscribers</div>
                        <div class="mt-2 text-3xl font-semibold">{{ metrics.platform.total_subscribers }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-500">Active Subscriptions</div>
                        <div class="mt-2 text-3xl font-semibold text-blue-600">{{ metrics.platform.active_subscriptions }}</div>
                    </div>
                </div>
            </div>

            <!-- Revenue Metrics -->
            <div>
                <h2 class="text-xl font-semibold mb-4">Revenue</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-500">Monthly Revenue</div>
                        <div class="mt-2 text-3xl font-semibold text-green-600">${{ metrics.revenue.monthly_revenue }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-500">Total Revenue</div>
                        <div class="mt-2 text-3xl font-semibold">${{ metrics.revenue.total_revenue }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-500">Pending Commissions</div>
                        <div class="mt-2 text-3xl font-semibold text-yellow-600">${{ metrics.revenue.pending_commissions }}</div>
                    </div>
                </div>
            </div>

            <!-- Security Metrics -->
            <div>
                <h2 class="text-xl font-semibold mb-4">Security</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-500">Device Fingerprints</div>
                        <div class="mt-2 text-3xl font-semibold">{{ metrics.security.device_fingerprints }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-500">Active Devices</div>
                        <div class="mt-2 text-3xl font-semibold text-green-600">{{ metrics.security.active_devices }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-500">Violations Today</div>
                        <div class="mt-2 text-3xl font-semibold text-red-600">{{ metrics.security.violations_today }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-500">Blocked Devices</div>
                        <div class="mt-2 text-3xl font-semibold">{{ metrics.security.blocked_devices }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </AppLayout>
</template>
