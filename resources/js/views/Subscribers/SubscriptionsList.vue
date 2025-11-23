<template>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold text-gray-900">Subscriptions</h2>
                    <p class="mt-1 text-sm text-gray-500">Manage subscriber subscriptions and renewals</p>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button @click="openCreateModal" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        New Subscription
                    </button>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Search</label>
                        <input v-model="filters.search" @input="debouncedFetch" type="text" placeholder="Search subscribers..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Package</label>
                        <select v-model="filters.package" @change="fetchSubscriptions" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">All Packages</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select v-model="filters.status" @change="fetchSubscriptions" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                            <option value="expiring_soon">Expiring Soon (7 days)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Per Page</label>
                        <select v-model="filters.perPage" @change="fetchSubscriptions" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div v-if="loading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                </div>

                <table v-else-if="subscriptions.length > 0" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subscriber</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Connections</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="sub in subscriptions" :key="sub.id">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ sub.subscriber_name }}</div>
                                <div class="text-sm text-gray-500">{{ sub.subscriber_email }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ sub.package_name }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ sub.active_connections }} / {{ sub.max_connections }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ formatDate(sub.expire_date) }}</div>
                                <div class="text-sm" :class="sub.days_until_expiration <= 7 ? 'text-red-600' : 'text-gray-500'">
                                    {{ sub.days_until_expiration }} days
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="sub.is_expired" class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Expired</span>
                                <span v-else-if="sub.is_active" class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Active</span>
                                <span v-else class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">Inactive</span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <button @click="viewSubscription(sub.id)" class="text-indigo-600 hover:text-indigo-900 mr-2">View</button>
                                <button @click="renewSubscription(sub)" class="text-green-600 hover:text-green-900 mr-2">Renew</button>
                                <button @click="toggleStatus(sub)" class="text-yellow-600 hover:text-yellow-900 mr-2">{{ sub.is_active ? 'Disable' : 'Enable' }}</button>
                                <button @click="deleteSubscription(sub)" class="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="text-center py-12">
                    <p class="text-gray-500">No subscriptions found</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { subscriptionsAPI } from '../../services/api';

const subscriptions = ref([]);
const loading = ref(false);
const filters = ref({ search: '', package: '', status: '', perPage: 20, page: 1 });

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        filters.value.page = 1;
        fetchSubscriptions();
    }, 300);
};

const fetchSubscriptions = async () => {
    loading.value = true;
    try {
        const response = await subscriptionsAPI.getAll(filters.value);
        if (response.data.success) {
            subscriptions.value = response.data.data;
        }
    } catch (error) {
        console.error('Error:', error);
    } finally {
        loading.value = false;
    }
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString();
};

const openCreateModal = () => {
    alert('Create subscription modal - to be implemented');
};

const viewSubscription = (id) => {
    alert(`View subscription ${id} - to be implemented`);
};

const renewSubscription = async (sub) => {
    const days = prompt('Enter number of days to add:', '30');
    if (!days) return;

    try {
        const response = await subscriptionsAPI.renew(sub.id, parseInt(days));
        if (response.data.success) {
            alert(response.data.message);
            fetchSubscriptions();
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Failed to renew');
    }
};

const toggleStatus = async (sub) => {
    try {
        const response = await subscriptionsAPI.toggle(sub.id);
        if (response.data.success) {
            fetchSubscriptions();
        }
    } catch (error) {
        alert('Failed to toggle status');
    }
};

const deleteSubscription = async (sub) => {
    if (!confirm(`Delete subscription for ${sub.subscriber_name}?`)) return;

    try {
        const response = await subscriptionsAPI.delete(sub.id);
        if (response.data.success) {
            fetchSubscriptions();
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Failed to delete');
    }
};

onMounted(() => {
    fetchSubscriptions();
});
</script>
