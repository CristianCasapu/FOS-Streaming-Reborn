<template>
    <AppLayout>
        <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Subscriber Activity</h2>
                <p class="mt-1 text-sm text-gray-500">Monitor subscriber connections and usage patterns</p>
            </div>

            <div class="bg-white shadow rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Search</label>
                        <input v-model="filters.search" @input="debouncedFetch" type="text" placeholder="Search subscribers..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Time Period</label>
                        <select v-model="filters.period" @change="fetchActivity" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="today">Today</option>
                            <option value="week">Last 7 Days</option>
                            <option value="month">Last 30 Days</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Access Type</label>
                        <select v-model="filters.type" @change="fetchActivity" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">All</option>
                            <option value="subscription">Subscription</option>
                            <option value="trial">Trial</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Per Page</label>
                        <select v-model="filters.perPage" @change="fetchActivity" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div v-if="loading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    <p class="mt-2 text-sm text-gray-500">Loading activity...</p>
                </div>

                <div v-else-if="activities.length === 0" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No activity</h3>
                    <p class="mt-1 text-sm text-gray-500">No subscriber activity found for the selected filters.</p>
                </div>

                <table v-else class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subscriber</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Package</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Connection</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Connections</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device Info</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP / ISP</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="activity in activities" :key="activity.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ activity.subscriber_name }}</div>
                                <div class="text-sm text-gray-500">{{ activity.subscriber_email }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span v-if="activity.access_type === 'trial'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    Trial
                                </span>
                                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Subscription
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ activity.package_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ formatDateTime(activity.last_connected) }}</div>
                                <div class="text-sm text-gray-500">{{ getTimeAgo(activity.last_connected) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    {{ activity.connection_count }} connections
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ activity.device || 'Unknown' }}</div>
                                <div class="text-sm text-gray-500">{{ activity.device_mac || 'No MAC' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ activity.ip_address || 'N/A' }}</div>
                                <div class="text-sm text-gray-500">{{ activity.isp || 'Unknown ISP' }}</div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="pagination.total > 0" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium">{{ (pagination.current_page - 1) * pagination.per_page + 1 }}</span>
                                to <span class="font-medium">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}</span>
                                of <span class="font-medium">{{ pagination.total }}</span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <button @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    Previous
                                </button>
                                <button @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    Next
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { subscriptionsAPI, trialsAPI } from '../../services/api';

const activities = ref([]);
const loading = ref(false);

const filters = ref({
    search: '',
    period: 'week',
    type: '',
    perPage: 20,
    page: 1
});

const pagination = ref({
    total: 0,
    per_page: 20,
    current_page: 1,
    last_page: 1
});

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        filters.value.page = 1;
        fetchActivity();
    }, 300);
};

const fetchActivity = async () => {
    loading.value = true;
    try {
        // This is a simplified implementation
        // In a real application, you would have a dedicated activity endpoint
        // For now, we'll combine subscription and trial connection data

        const subscriptionsPromise = subscriptionsAPI.getAll({
            search: filters.value.search,
            page: filters.value.page,
            per_page: filters.value.perPage
        });

        const trialsPromise = trialsAPI.getAll({
            search: filters.value.search,
            page: filters.value.page,
            per_page: filters.value.perPage
        });

        const [subsResponse, trialsResponse] = await Promise.all([
            filters.value.type !== 'trial' ? subscriptionsPromise : Promise.resolve({ data: { data: [] } }),
            filters.value.type !== 'subscription' ? trialsPromise : Promise.resolve({ data: { data: [] } })
        ]);

        // Combine and format the data
        const subsActivities = subsResponse.data.data.map(sub => ({
            ...sub,
            access_type: 'subscription',
            id: `sub_${sub.id}`
        }));

        const trialsActivities = trialsResponse.data.data.map(trial => ({
            ...trial,
            access_type: 'trial',
            id: `trial_${trial.id}`
        }));

        activities.value = [...subsActivities, ...trialsActivities]
            .filter(a => a.last_connected) // Only show items with connection history
            .sort((a, b) => new Date(b.last_connected) - new Date(a.last_connected));

        pagination.value = subsResponse.data.pagination || trialsResponse.data.pagination || pagination.value;

    } catch (error) {
        console.error('Error fetching activity:', error);
        alert('Failed to load activity data');
    } finally {
        loading.value = false;
    }
};

const formatDateTime = (dateTime) => {
    if (!dateTime) return 'Never';
    const date = new Date(dateTime);
    return date.toLocaleString();
};

const getTimeAgo = (dateTime) => {
    if (!dateTime) return '';

    const now = new Date();
    const then = new Date(dateTime);
    const diffMs = now - then;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} min${diffMins !== 1 ? 's' : ''} ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)} week${Math.floor(diffDays / 7) !== 1 ? 's' : ''} ago`;
    return `${Math.floor(diffDays / 30)} month${Math.floor(diffDays / 30) !== 1 ? 's' : ''} ago`;
};

const changePage = (page) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        filters.value.page = page;
        fetchActivity();
    }
};

onMounted(() => {
    fetchActivity();
});
</script>
