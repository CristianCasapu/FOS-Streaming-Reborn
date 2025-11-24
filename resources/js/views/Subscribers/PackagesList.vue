<template>
    <AppLayout>
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
                            <option :value="null">All Packages</option>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Devices / Quality</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price / Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bouquets / Streams</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="pkg in packages" :key="pkg.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ pkg.name }}</div>
                                <div class="text-sm text-gray-500 truncate max-w-xs">{{ pkg.description || 'No description' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ pkg.max_concurrent_devices || 'Unlimited' }} devices</div>
                                <div class="text-sm text-gray-500">{{ pkg.video_quality || 'Any' }} quality</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">${{ pkg.price || 'N/A' }}</div>
                                <div class="text-sm text-gray-500">{{ pkg.duration_days }} days</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ pkg.bouquet_count }} bouquets</div>
                                <div class="text-sm text-gray-500">{{ pkg.stream_count || 0 }} streams</div>
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
                                <button @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Previous
                                </button>
                                <button @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
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
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-visible shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <form @submit.prevent="savePackage">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[calc(100vh-10rem)] overflow-y-auto">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                {{ editingPackage ? 'Edit Package' : 'Create New Package' }}
                            </h3>

                            <!-- Basic Info -->
                            <div class="space-y-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                                    <input v-model="formData.name" type="text" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea v-model="formData.description" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Price ($) <span class="text-red-500">*</span></label>
                                        <input v-model.number="formData.price" type="number" step="0.01" min="0" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration (days) <span class="text-red-500">*</span></label>
                                        <input v-model.number="formData.duration_days" type="number" min="1" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Concurrent Devices</label>
                                        <input v-model.number="formData.max_concurrent_devices" type="number" min="0" placeholder="0 = Unlimited" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <p class="mt-1 text-xs text-gray-500">0 or empty = unlimited</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Bandwidth Limit (Mbps)</label>
                                        <input v-model.number="formData.bandwidth_limit_mbps" type="number" min="0" placeholder="0 = Unlimited" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <p class="mt-1 text-xs text-gray-500">0 or empty = unlimited</p>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Video Quality</label>
                                    <select v-model="formData.video_quality" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Any Quality</option>
                                        <option value="4K">4K UHD</option>
                                        <option value="FHD">Full HD (1080p)</option>
                                        <option value="HD">HD (720p)</option>
                                        <option value="SD">SD (480p)</option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex items-center">
                                        <input v-model="formData.allow_recording" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <label class="ml-2 block text-sm text-gray-900">Allow Recording</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input v-model="formData.allow_timeshifting" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <label class="ml-2 block text-sm text-gray-900">Allow Timeshifting</label>
                                    </div>
                                </div>

                                <div class="flex items-center">
                                    <input v-model="formData.is_active" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label class="ml-2 block text-sm text-gray-900">Active</label>
                                </div>
                            </div>

                            <!-- Bouquet Selection -->
                            <div class="border-t pt-4">
                                <h4 class="text-md font-medium text-gray-900 mb-4">Bouquet Selection</h4>

                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Available Bouquets -->
                                    <div>
                                        <div class="flex justify-between items-center mb-2">
                                            <h5 class="text-sm font-medium text-gray-700">Available Bouquets ({{ availableBouquets.length }})</h5>
                                            <button type="button" @click="addAllBouquets" v-if="availableBouquets.length > 0" class="text-xs text-indigo-600 hover:text-indigo-800">Add All</button>
                                        </div>
                                        <div class="border rounded-lg p-2 bg-gray-50 max-h-80 overflow-y-auto">
                                            <div v-if="loadingBouquets" class="text-center py-8">
                                                <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                                                <p class="mt-2 text-xs text-gray-500">Loading bouquets...</p>
                                            </div>
                                            <div v-else-if="availableBouquets.length === 0" class="text-sm text-gray-500 text-center py-8">
                                                No available bouquets
                                            </div>
                                            <div v-else class="space-y-1">
                                                <div
                                                    v-for="bouquet in availableBouquets"
                                                    :key="bouquet.id"
                                                    class="flex items-center justify-between p-2 bg-white hover:bg-indigo-50 rounded border border-gray-200 cursor-pointer transition-colors"
                                                    @click="addBouquet(bouquet)"
                                                >
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">{{ bouquet.name }}</p>
                                                        <p class="text-xs text-gray-500 truncate">{{ bouquet.stream_count }} streams</p>
                                                    </div>
                                                    <button type="button" class="ml-2 text-green-600 hover:text-green-700 flex-shrink-0">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Selected Bouquets -->
                                    <div>
                                        <div class="flex justify-between items-center mb-2">
                                            <h5 class="text-sm font-medium text-gray-700">Selected Bouquets ({{ selectedBouquets.length }})</h5>
                                            <button type="button" @click="removeAllBouquets" v-if="selectedBouquets.length > 0" class="text-xs text-red-600 hover:text-red-800">Remove All</button>
                                        </div>
                                        <div class="border rounded-lg p-2 bg-gray-50 max-h-80 overflow-y-auto">
                                            <div v-if="selectedBouquets.length === 0" class="text-sm text-gray-500 text-center py-8">
                                                No bouquets selected<br>
                                                <span class="text-xs">Click bouquets from the left to add them</span>
                                            </div>
                                            <div v-else class="space-y-1">
                                                <div
                                                    v-for="bouquet in selectedBouquets"
                                                    :key="bouquet.id"
                                                    class="flex items-center justify-between p-2 bg-white hover:bg-red-50 rounded border border-gray-200 transition-colors"
                                                >
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">{{ bouquet.name }}</p>
                                                        <p class="text-xs text-gray-500 truncate">{{ bouquet.stream_count }} streams</p>
                                                    </div>
                                                    <button type="button" @click="removeBouquet(bouquet)" class="ml-2 text-red-600 hover:text-red-700 flex-shrink-0">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" :disabled="saving" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ saving ? 'Saving...' : 'Save Package' }}
                            </button>
                            <button @click="closeModal" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { packagesAPI, bouquetsAPI } from '../../services/api';

const packages = ref([]);
const allBouquets = ref([]);
const loading = ref(false);
const loadingBouquets = ref(false);
const saving = ref(false);
const showModal = ref(false);
const editingPackage = ref(null);
const selectedBouquets = ref([]);

const filters = ref({
    search: '',
    active: null, // null = show all, not empty string!
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
    max_concurrent_devices: null,
    bandwidth_limit_mbps: null,
    video_quality: '',
    allow_recording: false,
    allow_timeshifting: false,
    price: null,
    duration_days: 30,
    is_active: true
});

const availableBouquets = computed(() => {
    const selectedIds = new Set(selectedBouquets.value.map(b => b.id));
    return allBouquets.value.filter(b => !selectedIds.has(b.id) && b.is_active);
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
        const params = {
            search: filters.value.search,
            page: filters.value.page,
            per_page: filters.value.perPage
        };

        if (filters.value.active !== null) {
            params.active = filters.value.active;
        }

        console.log('Fetching packages with params:', params);
        const response = await packagesAPI.getAll(params);

        console.log('Packages API Response:', response.data);

        if (response.data.success) {
            packages.value = response.data.data;
            pagination.value = response.data.pagination;
            console.log('Loaded packages:', packages.value);
        } else {
            console.error('API returned success=false:', response.data);
            alert('Failed to load packages: ' + (response.data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error fetching packages:', error);
        alert('Failed to load packages: ' + (error.response?.data?.message || error.message));
    } finally {
        loading.value = false;
    }
};

const fetchAllBouquets = async () => {
    loadingBouquets.value = true;
    try {
        const response = await bouquetsAPI.getAll({ per_page: 1000, active: 1 });
        console.log('Bouquets API Response:', response.data);

        if (response.data.success) {
            allBouquets.value = response.data.data.map(bouquet => ({
                id: bouquet.id,
                name: bouquet.name,
                description: bouquet.description,
                stream_count: bouquet.stream_count || 0,
                is_active: bouquet.is_active
            }));
            console.log('Loaded bouquets:', allBouquets.value.length);
        }
    } catch (error) {
        console.error('Error fetching bouquets:', error);
    } finally {
        loadingBouquets.value = false;
    }
};

const openCreateModal = async () => {
    editingPackage.value = null;
    formData.value = {
        name: '',
        description: '',
        max_concurrent_devices: null,
        bandwidth_limit_mbps: null,
        video_quality: '',
        allow_recording: false,
        allow_timeshifting: false,
        price: null,
        duration_days: 30,
        is_active: true
    };
    selectedBouquets.value = [];

    if (allBouquets.value.length === 0) {
        await fetchAllBouquets();
    }

    showModal.value = true;
};

const editPackage = async (pkg) => {
    editingPackage.value = pkg;
    formData.value = {
        name: pkg.name,
        description: pkg.description || '',
        max_concurrent_devices: pkg.max_concurrent_devices,
        bandwidth_limit_mbps: pkg.bandwidth_limit_mbps,
        video_quality: pkg.video_quality || '',
        allow_recording: pkg.allow_recording || false,
        allow_timeshifting: pkg.allow_timeshifting || false,
        price: pkg.price,
        duration_days: pkg.duration_days,
        is_active: pkg.is_active
    };

    if (allBouquets.value.length === 0) {
        await fetchAllBouquets();
    }

    // Fetch package details to get bouquets
    try {
        const response = await packagesAPI.getOne(pkg.id);
        if (response.data.success) {
            const packageData = response.data.data;
            selectedBouquets.value = packageData.bouquets || [];
        }
    } catch (error) {
        console.error('Error loading package bouquets:', error);
        selectedBouquets.value = [];
    }

    showModal.value = true;
};

const addBouquet = (bouquet) => {
    if (!selectedBouquets.value.find(b => b.id === bouquet.id)) {
        selectedBouquets.value.push({...bouquet});
    }
};

const removeBouquet = (bouquet) => {
    selectedBouquets.value = selectedBouquets.value.filter(b => b.id !== bouquet.id);
};

const addAllBouquets = () => {
    availableBouquets.value.forEach(bouquet => {
        if (!selectedBouquets.value.find(b => b.id === bouquet.id)) {
            selectedBouquets.value.push({...bouquet});
        }
    });
};

const removeAllBouquets = () => {
    if (confirm('Are you sure you want to remove all bouquets from this package?')) {
        selectedBouquets.value = [];
    }
};

const savePackage = async () => {
    saving.value = true;
    try {
        const data = {
            ...formData.value,
            bouquet_ids: selectedBouquets.value.map(b => b.id)
        };

        console.log('Saving package data:', data);

        const response = editingPackage.value
            ? await packagesAPI.update(editingPackage.value.id, data)
            : await packagesAPI.create(data);

        if (response.data.success) {
            // If we have bouquet IDs, sync them
            if (data.bouquet_ids.length > 0 && response.data.data?.id) {
                const packageId = editingPackage.value?.id || response.data.data.id;
                await packagesAPI.assignBouquets(packageId, data.bouquet_ids);
            }

            closeModal();

            if (!editingPackage.value) {
                filters.value.page = 1;
                filters.value.search = '';
            }

            await fetchPackages();
            alert(response.data.message || 'Package saved successfully');
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
            alert(response.data.message || 'Package deleted successfully');
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
    selectedBouquets.value = [];
};

onMounted(() => {
    fetchPackages();
});
</script>
