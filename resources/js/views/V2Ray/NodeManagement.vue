<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { v2rayAPI } from '../../services/api';

const servers = ref([]);
const loading = ref(true);

const fetchServers = async () => {
    try {
        const response = await v2rayAPI.getServers();
        servers.value = response.data.data || [];
    } catch (error) {
        console.error('Failed to fetch V2Ray servers:', error);
    } finally {
        loading.value = false;
    }
};

const getStatusColor = (status) => {
    return status === 'healthy' ? 'text-green-600' : status === 'unhealthy' ? 'text-red-600' : 'text-gray-600';
};

onMounted(() => {
    fetchServers();
});
</script>

<template>
    <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold mb-8">V2Ray Node Management</h1>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Server</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Protocol</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Connections</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Load</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-if="loading">
                        <td colspan="6" class="px-6 py-4 text-center">Loading...</td>
                    </tr>
                    <tr v-else-if="servers.length === 0">
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No servers configured</td>
                    </tr>
                    <tr v-else v-for="server in servers" :key="server.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">{{ server.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ server.protocol }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ server.location }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ server.current_connections }} / {{ server.max_connections }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ server.load }}%</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span :class="`font-medium ${getStatusColor(server.health_status)}`">
                                {{ server.health_status }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    </AppLayout>
</template>
