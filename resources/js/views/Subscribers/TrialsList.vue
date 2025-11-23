<template>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold text-gray-900">Trial Subscriptions</h2>
                    <p class="mt-1 text-sm text-gray-500">Manage time-limited trial access for subscribers</p>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button @click="openCreateModal" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        New Trial
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
                        <select v-model="filters.package" @change="fetchTrials" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">All Packages</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select v-model="filters.status" @change="fetchTrials" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                            <option value="converted">Converted</option>
                            <option value="not_converted">Not Converted</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Per Page</label>
                        <select v-model="filters.perPage" @change="fetchTrials" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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

                <table v-else-if="trials.length > 0" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subscriber</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remaining</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="trial in trials" :key="trial.id">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ trial.subscriber_name }}</div>
                                <div class="text-sm text-gray-500">{{ trial.subscriber_email }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ trial.package_name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ trial.trial_duration_hours }}h</td>
                            <td class="px-6 py-4">
                                <div class="text-sm" :class="trial.is_expired ? 'text-red-600' : 'text-gray-900'">
                                    {{ trial.remaining_time }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-indigo-600 h-2 rounded-full" :style="`width: ${trial.progress_percentage}%`"></div>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ trial.progress_percentage }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="trial.converted_to_subscription" class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Converted</span>
                                <span v-else-if="trial.is_expired" class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Expired</span>
                                <span v-else-if="trial.is_active" class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Active</span>
                                <span v-else class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">Inactive</span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <button @click="viewTrial(trial.id)" class="text-indigo-600 hover:text-indigo-900 mr-2">View</button>
                                <button v-if="!trial.is_expired && !trial.converted_to_subscription" @click="extendTrial(trial)" class="text-green-600 hover:text-green-900 mr-2">Extend</button>
                                <button v-if="!trial.converted_to_subscription" @click="convertToSubscription(trial)" class="text-blue-600 hover:text-blue-900 mr-2">Convert</button>
                                <button @click="deleteTrial(trial)" class="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="text-center py-12">
                    <p class="text-gray-500">No trials found</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { trialsAPI } from '../../services/api';

const trials = ref([]);
const loading = ref(false);
const filters = ref({ search: '', package: '', status: '', perPage: 20, page: 1 });

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        filters.value.page = 1;
        fetchTrials();
    }, 300);
};

const fetchTrials = async () => {
    loading.value = true;
    try {
        const response = await trialsAPI.getAll(filters.value);
        if (response.data.success) {
            trials.value = response.data.data;
        }
    } catch (error) {
        console.error('Error:', error);
    } finally {
        loading.value = false;
    }
};

const openCreateModal = () => {
    alert('Create trial modal - to be implemented');
};

const viewTrial = (id) => {
    alert(`View trial ${id} - to be implemented`);
};

const extendTrial = async (trial) => {
    const hours = prompt('Enter number of hours to add:', '24');
    if (!hours) return;

    try {
        const response = await trialsAPI.extend(trial.id, parseInt(hours));
        if (response.data.success) {
            alert(response.data.message);
            fetchTrials();
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Failed to extend trial');
    }
};

const convertToSubscription = async (trial) => {
    if (!confirm(`Convert ${trial.subscriber_name}'s trial to a subscription?`)) return;

    try {
        const response = await trialsAPI.convert(trial.id, {});
        if (response.data.success) {
            alert(response.data.message);
            fetchTrials();
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Failed to convert');
    }
};

const deleteTrial = async (trial) => {
    if (!confirm(`Delete trial for ${trial.subscriber_name}?`)) return;

    try {
        const response = await trialsAPI.delete(trial.id);
        if (response.data.success) {
            fetchTrials();
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Failed to delete');
    }
};

onMounted(() => {
    fetchTrials();
});
</script>
