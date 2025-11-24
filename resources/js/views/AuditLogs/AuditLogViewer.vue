<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { auditLogsAPI } from '../../services/api';

// State
const logs = ref([]);
const loading = ref(false);
const pagination = reactive({
    page: 1,
    perPage: 20,
    total: 0,
    totalPages: 0,
});

// Filters
const filters = reactive({
    userType: '',
    action: '',
    entityType: '',
    startDate: '',
    endDate: '',
    search: '',
});

// Statistics
const stats = ref(null);

// UI State
const selectedLog = ref(null);
const showLogModal = ref(false);

// Fetch audit logs
const fetchLogs = async () => {
    loading.value = true;
    try {
        const params = {
            page: pagination.page,
            per_page: pagination.perPage,
            ...filters,
        };

        const response = await auditLogsAPI.list(params);
        logs.value = response.data.data;
        pagination.total = response.data.total;
        pagination.totalPages = response.data.total_pages;
    } catch (error) {
        console.error('Failed to fetch audit logs:', error);
    } finally {
        loading.value = false;
    }
};

// Fetch statistics
const fetchStats = async () => {
    try {
        const response = await auditLogsAPI.stats({ days: 30 });
        stats.value = response.data.data;
    } catch (error) {
        console.error('Failed to fetch stats:', error);
    }
};

// Apply filters
const applyFilters = () => {
    pagination.page = 1;
    fetchLogs();
};

// Reset filters
const resetFilters = () => {
    Object.keys(filters).forEach(key => filters[key] = '');
    pagination.page = 1;
    fetchLogs();
};

// View log details
const viewLog = (log) => {
    selectedLog.value = log;
    showLogModal.value = true;
};

// Change page
const changePage = (page) => {
    pagination.page = page;
    fetchLogs();
};

// Format date
const formatDate = (date) => {
    return new Date(date).toLocaleString();
};

// Get status badge class
const getStatusClass = (status) => {
    const classes = {
        success: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
        error: 'bg-orange-100 text-orange-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

// Get user type badge class
const getUserTypeClass = (userType) => {
    const classes = {
        admin: 'bg-purple-100 text-purple-800',
        subscriber: 'bg-blue-100 text-blue-800',
        reseller: 'bg-indigo-100 text-indigo-800',
        system: 'bg-gray-100 text-gray-800',
    };
    return classes[userType] || 'bg-gray-100 text-gray-800';
};

onMounted(() => {
    fetchLogs();
    fetchStats();
});
</script>

<template>
    <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Audit Logs</h1>
            <p class="mt-2 text-sm text-gray-600">Complete audit trail of all system actions</p>
        </div>

        <!-- Statistics Cards -->
        <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Total Actions</div>
                <div class="mt-2 text-3xl font-semibold text-gray-900">{{ stats.total_actions }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Success Rate</div>
                <div class="mt-2 text-3xl font-semibold text-green-600">
                    {{ Math.round(stats.success_rate) }}%
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Failed Actions</div>
                <div class="mt-2 text-3xl font-semibold text-red-600">{{ stats.failed_actions }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Last 24h</div>
                <div class="mt-2 text-3xl font-semibold text-blue-600">{{ stats.last_24h }}</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Filters</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">User Type</label>
                    <select v-model="filters.userType" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">All</option>
                        <option value="admin">Admin</option>
                        <option value="subscriber">Subscriber</option>
                        <option value="reseller">Reseller</option>
                        <option value="system">System</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                    <input
                        v-model="filters.action"
                        type="text"
                        placeholder="e.g. login, create"
                        class="w-full rounded-md border-gray-300 shadow-sm"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Entity Type</label>
                    <input
                        v-model="filters.entityType"
                        type="text"
                        placeholder="e.g. Stream"
                        class="w-full rounded-md border-gray-300 shadow-sm"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input
                        v-model="filters.startDate"
                        type="date"
                        class="w-full rounded-md border-gray-300 shadow-sm"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input
                        v-model="filters.endDate"
                        type="date"
                        class="w-full rounded-md border-gray-300 shadow-sm"
                    />
                </div>

                <div class="flex items-end gap-2">
                    <button
                        @click="applyFilters"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        Apply
                    </button>
                    <button
                        @click="resetFilters"
                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-if="loading">
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">Loading...</td>
                        </tr>
                        <tr v-else-if="logs.length === 0">
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No audit logs found</td>
                        </tr>
                        <tr v-else v-for="log in logs" :key="log.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ formatDate(log.created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="`px-2 py-1 text-xs rounded-full ${getUserTypeClass(log.user_type)}`">
                                    {{ log.user_type }}
                                </span>
                                <div class="text-xs text-gray-500 mt-1">ID: {{ log.user_id || 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ log.action }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div>{{ log.entity_type || 'N/A' }}</div>
                                <div class="text-xs text-gray-500">{{ log.entity_id ? `#${log.entity_id}` : '' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="`px-2 py-1 text-xs rounded-full ${getStatusClass(log.status)}`">
                                    {{ log.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ log.ip_address }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button
                                    @click="viewLog(log)"
                                    class="text-blue-600 hover:text-blue-900"
                                >
                                    View Details
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.totalPages > 1" class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t">
                <div class="text-sm text-gray-700">
                    Showing {{ ((pagination.page - 1) * pagination.perPage) + 1 }}
                    to {{ Math.min(pagination.page * pagination.perPage, pagination.total) }}
                    of {{ pagination.total }} results
                </div>
                <div class="flex gap-2">
                    <button
                        @click="changePage(pagination.page - 1)"
                        :disabled="pagination.page === 1"
                        class="px-3 py-1 rounded-md bg-white border disabled:opacity-50"
                    >
                        Previous
                    </button>
                    <button
                        @click="changePage(pagination.page + 1)"
                        :disabled="pagination.page === pagination.totalPages"
                        class="px-3 py-1 rounded-md bg-white border disabled:opacity-50"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- Log Details Modal -->
        <div v-if="showLogModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Audit Log Details</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Description</label>
                            <div class="mt-1 text-sm text-gray-900">{{ selectedLog.description || 'N/A' }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500">User Agent</label>
                                <div class="mt-1 text-sm text-gray-900 truncate">{{ selectedLog.user_agent }}</div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Method</label>
                                <div class="mt-1 text-sm text-gray-900">{{ selectedLog.method }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-500">URL</label>
                            <div class="mt-1 text-sm text-gray-900 truncate">{{ selectedLog.url }}</div>
                        </div>

                        <div v-if="selectedLog.old_values">
                            <label class="text-sm font-medium text-gray-500">Old Values</label>
                            <pre class="mt-1 text-xs bg-gray-100 p-3 rounded overflow-x-auto">{{ JSON.stringify(selectedLog.old_values, null, 2) }}</pre>
                        </div>

                        <div v-if="selectedLog.new_values">
                            <label class="text-sm font-medium text-gray-500">New Values</label>
                            <pre class="mt-1 text-xs bg-gray-100 p-3 rounded overflow-x-auto">{{ JSON.stringify(selectedLog.new_values, null, 2) }}</pre>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            @click="showLogModal = false"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </AppLayout>
</template>
