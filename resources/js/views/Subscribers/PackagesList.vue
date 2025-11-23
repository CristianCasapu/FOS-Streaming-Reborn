<template>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        Subscription Packages
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Manage packages with connection limits and bouquet assignments
                    </p>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button @click="openCreateModal" type="button" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        New Package
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white shadow rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Search</label>
                        <input v-model="filters.search" @input="debouncedFetch" type="text" placeholder="Search packages..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select v-model="filters.active" @change="fetchPackages" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">All Packages</option>
                            <option value="1">Active Only</option>
                            <option value="0">Inactive Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Per Page</label>
                        <select v-model="filters.perPage" @change="fetchPackages" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Packages Table -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div v-if="loading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    <p class="mt-2 text-sm text-gray-500">Loading packages...</p>
                </div>

                <div v-else-if="packages.length === 0" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No packages</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new package.</p>
                </div>

                <table v-else class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max Connections</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price / Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bouquets / Channels</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subscriptions / Trials</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="pkg in packages" :key="pkg.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ pkg.name }}</div>
                                <div class="text-sm text-gray-500">{{ pkg.description || 'No description' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ pkg.max_connections }} {{ pkg.max_connections === 1 ? 'connection' : 'connections' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">${{ pkg.price || 'N/A' }}</div>
                                <div class="text-sm text-gray-500">{{ pkg.duration_days }} days</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ pkg.bouquet_count }} bouquets</div>
                                <div class="text-sm text-gray-500">{{ pkg.channel_count }} channels</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ pkg.active_subscriptions_count }} active subs</div>
                                <div class="text-sm text-gray-500">{{ pkg.active_trials_count }} active trials</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span v-if="pkg.is_active" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Active
                                </span>
                                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Inactive
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button @click="viewPackage(pkg.id)" class="text-indigo-600 hover:text-indigo-900 mr-3" title="View Details">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                                <button @click="editPackage(pkg)" class="text-yellow-600 hover:text-yellow-900 mr-3" title="Edit">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button @click="toggleStatus(pkg)" class="text-blue-600 hover:text-blue-900 mr-3" :title="pkg.is_active ? 'Deactivate' : 'Activate'">
                                    <svg v-if="pkg.is_active" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <button @click="deletePackage(pkg)" class="text-red-600 hover:text-red-900" title="Delete">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="pagination.total > 0" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <button @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </button>
                        <button @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </button>
                    </div>
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

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form @submit.prevent="savePackage">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                {{ editingPackage ? 'Edit Package' : 'Create New Package' }}
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name</label>
                                    <input v-model="formData.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Description</label>
                                    <textarea v-model="formData.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Max Connections</label>
                                        <input v-model.number="formData.max_connections" type="number" min="1" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Price ($)</label>
                                        <input v-model.number="formData.price" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Duration (days)</label>
                                    <input v-model.number="formData.duration_days" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                                <div class="flex items-center">
                                    <input v-model="formData.is_active" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label class="ml-2 block text-sm text-gray-900">Active</label>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" :disabled="saving" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                                {{ saving ? 'Saving...' : 'Save' }}
                            </button>
                            <button @click="closeModal" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Details Modal -->
        <div v-if="showDetailsModal && selectedPackage" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeDetailsModal"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">{{ selectedPackage.name }}</h3>
                            <button @click="closeDetailsModal" class="text-gray-400 hover:text-gray-500">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Max Connections</p>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedPackage.max_connections }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Price</p>
                                <p class="mt-1 text-sm text-gray-900">${{ selectedPackage.price || 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Duration</p>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedPackage.duration_days }} days</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Status</p>
                                <p class="mt-1">
                                    <span v-if="selectedPackage.is_active" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inactive</span>
                                </p>
                            </div>
                        </div>

                        <div v-if="packageStats" class="mb-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Statistics</h4>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="bg-blue-50 p-3 rounded-lg">
                                    <p class="text-xs text-blue-600 font-medium">Total Subscriptions</p>
                                    <p class="text-2xl font-bold text-blue-900">{{ packageStats.total_subscriptions }}</p>
                                    <p class="text-xs text-blue-600">{{ packageStats.active_subscriptions }} active</p>
                                </div>
                                <div class="bg-green-50 p-3 rounded-lg">
                                    <p class="text-xs text-green-600 font-medium">Total Trials</p>
                                    <p class="text-2xl font-bold text-green-900">{{ packageStats.total_trials }}</p>
                                    <p class="text-xs text-green-600">{{ packageStats.active_trials }} active</p>
                                </div>
                                <div class="bg-purple-50 p-3 rounded-lg">
                                    <p class="text-xs text-purple-600 font-medium">Content</p>
                                    <p class="text-2xl font-bold text-purple-900">{{ packageStats.total_bouquets }}</p>
                                    <p class="text-xs text-purple-600">{{ packageStats.total_channels }} channels</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedPackage.bouquets">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Assigned Bouquets ({{ selectedPackage.bouquets.length }})</h4>
                            <div class="max-h-60 overflow-y-auto">
                                <div v-if="selectedPackage.bouquets.length === 0" class="text-sm text-gray-500 text-center py-4">
                                    No bouquets assigned
                                </div>
                                <div v-else class="space-y-2">
                                    <div v-for="bouquet in selectedPackage.bouquets" :key="bouquet.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ bouquet.name }}</p>
                                            <p class="text-xs text-gray-500">{{ bouquet.description || 'No description' }}</p>
                                        </div>
                                        <span class="text-xs text-gray-500">{{ bouquet.channel_count }} channels</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button @click="closeDetailsModal" type="button" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { packagesAPI } from '../../services/api';

const packages = ref([]);
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const showDetailsModal = ref(false);
const editingPackage = ref(null);
const selectedPackage = ref(null);
const packageStats = ref(null);

const filters = ref({
    search: '',
    active: '',
    perPage: 20,
    page: 1
});

const pagination = ref({
    total: 0,
    per_page: 20,
    current_page: 1,
    last_page: 1
});

const formData = ref({
    name: '',
    description: '',
    max_connections: 1,
    price: null,
    duration_days: 30,
    is_active: 1
});

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        filters.value.page = 1;
        fetchPackages();
    }, 300);
};

const fetchPackages = async () => {
    loading.value = true;
    try {
        const response = await packagesAPI.getAll({
            search: filters.value.search,
            active: filters.value.active,
            page: filters.value.page,
            per_page: filters.value.perPage
        });
        if (response.data.success) {
            packages.value = response.data.data;
            pagination.value = response.data.pagination;
        }
    } catch (error) {
        console.error('Error fetching packages:', error);
        alert('Failed to load packages');
    } finally {
        loading.value = false;
    }
};

const viewPackage = async (id) => {
    try {
        const [detailsResponse, statsResponse] = await Promise.all([
            packagesAPI.getOne(id),
            packagesAPI.getStats(id)
        ]);

        if (detailsResponse.data.success) {
            selectedPackage.value = detailsResponse.data.data;
        }

        if (statsResponse.data.success) {
            packageStats.value = statsResponse.data.data;
        }

        showDetailsModal.value = true;
    } catch (error) {
        console.error('Error fetching package details:', error);
        alert('Failed to load package details');
    }
};

const openCreateModal = () => {
    editingPackage.value = null;
    formData.value = {
        name: '',
        description: '',
        max_connections: 1,
        price: null,
        duration_days: 30,
        is_active: 1
    };
    showModal.value = true;
};

const editPackage = (pkg) => {
    editingPackage.value = pkg;
    formData.value = {
        name: pkg.name,
        description: pkg.description || '',
        max_connections: pkg.max_connections,
        price: pkg.price,
        duration_days: pkg.duration_days,
        is_active: pkg.is_active
    };
    showModal.value = true;
};

const savePackage = async () => {
    saving.value = true;
    try {
        const response = editingPackage.value
            ? await packagesAPI.update(editingPackage.value.id, formData.value)
            : await packagesAPI.create(formData.value);

        if (response.data.success) {
            closeModal();
            fetchPackages();
            alert(response.data.message);
        }
    } catch (error) {
        console.error('Error saving package:', error);
        alert(error.response?.data?.message || 'Failed to save package');
    } finally {
        saving.value = false;
    }
};

const toggleStatus = async (pkg) => {
    try {
        const response = await packagesAPI.toggle(pkg.id);
        if (response.data.success) {
            fetchPackages();
        }
    } catch (error) {
        console.error('Error toggling status:', error);
        alert('Failed to update package status');
    }
};

const deletePackage = async (pkg) => {
    if (!confirm(`Are you sure you want to delete "${pkg.name}"? This action cannot be undone.`)) {
        return;
    }

    try {
        const response = await packagesAPI.delete(pkg.id);
        if (response.data.success) {
            fetchPackages();
            alert(response.data.message);
        }
    } catch (error) {
        console.error('Error deleting package:', error);
        alert(error.response?.data?.message || 'Failed to delete package');
    }
};

const changePage = (page) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        filters.value.page = page;
        fetchPackages();
    }
};

const closeModal = () => {
    showModal.value = false;
    editingPackage.value = null;
};

const closeDetailsModal = () => {
    showDetailsModal.value = false;
    selectedPackage.value = null;
    packageStats.value = null;
};

onMounted(() => {
    fetchPackages();
});
</script>
