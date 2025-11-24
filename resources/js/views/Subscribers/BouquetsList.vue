<template>
    <AppLayout>
        <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        Stream Bouquets
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Manage groups of streams and assign them to packages
                    </p>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button @click="openCreateModal" type="button" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        New Bouquet
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white shadow rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Search</label>
                        <input v-model="filters.search" @input="debouncedFetch" type="text" placeholder="Search bouquets..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select v-model="filters.active" @change="fetchBouquets" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option :value="null">All Bouquets</option>
                            <option value="1">Active Only</option>
                            <option value="0">Inactive Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Per Page</label>
                        <select v-model="filters.perPage" @change="fetchBouquets" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Bouquets Table -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div v-if="loading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    <p class="mt-2 text-sm text-gray-500">Loading bouquets...</p>
                </div>

                <div v-else-if="bouquets.length === 0" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No bouquets</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new bouquet.</p>
                </div>

                <table v-else class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Streams</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Packages</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sort Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="bouquet in bouquets" :key="bouquet.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ bouquet.name }}</div>
                                <div class="text-sm text-gray-500">{{ bouquet.description || 'No description' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ bouquet.stream_count }} streams
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    {{ bouquet.package_count }} packages
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ bouquet.sort_order }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span v-if="bouquet.is_active" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Active
                                </span>
                                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Inactive
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button @click="editBouquet(bouquet)" class="text-yellow-600 hover:text-yellow-900 mr-3" title="Edit">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button @click="toggleStatus(bouquet)" class="text-blue-600 hover:text-blue-900 mr-3" :title="bouquet.is_active ? 'Deactivate' : 'Activate'">
                                    <svg v-if="bouquet.is_active" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <button @click="deleteBouquet(bouquet)" class="text-red-600 hover:text-red-900" title="Delete">
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
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-visible shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
                    <form @submit.prevent="saveBouquet">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                {{ editingBouquet ? 'Edit Bouquet' : 'Create New Bouquet' }}
                            </h3>

                            <!-- Basic Info -->
                            <div class="grid grid-cols-1 gap-4 mb-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                                        <input v-model="formData.name" type="text" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                                        <input v-model.number="formData.sort_order" type="number" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea v-model="formData.description" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                </div>
                                <div class="flex items-center">
                                    <input v-model="formData.is_active" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label class="ml-2 block text-sm text-gray-900">Active</label>
                                </div>
                            </div>

                            <!-- Stream Selection -->
                            <div class="border-t pt-4">
                                <h4 class="text-md font-medium text-gray-900 mb-4">Stream Selection</h4>

                                <!-- Search and Filter -->
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Search Streams</label>
                                        <input v-model="streamSearch" type="text" placeholder="Search by name..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Category</label>
                                        <select v-model="streamCategoryFilter" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="">All Categories</option>
                                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                                {{ category.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Available Streams -->
                                    <div>
                                        <div class="flex justify-between items-center mb-2">
                                            <h5 class="text-sm font-medium text-gray-700">Available Streams ({{ filteredAvailableStreams.length }})</h5>
                                            <button type="button" @click="addAllVisibleStreams" class="text-xs text-indigo-600 hover:text-indigo-800">Add All Visible</button>
                                        </div>
                                        <div class="border rounded-lg p-2 bg-gray-50 max-h-96 overflow-y-auto">
                                            <div v-if="loadingStreams" class="text-center py-8">
                                                <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                                                <p class="mt-2 text-xs text-gray-500">Loading streams...</p>
                                            </div>
                                            <div v-else-if="filteredAvailableStreams.length === 0" class="text-sm text-gray-500 text-center py-8">
                                                No available streams
                                            </div>
                                            <div v-else class="space-y-1">
                                                <div
                                                    v-for="stream in filteredAvailableStreams"
                                                    :key="stream.id"
                                                    class="flex items-center justify-between p-2 bg-white hover:bg-indigo-50 rounded border border-gray-200 cursor-pointer transition-colors"
                                                    @click="addStreamToBouquet(stream)"
                                                >
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">{{ stream.name }}</p>
                                                        <p class="text-xs text-gray-500 truncate">{{ stream.category_name || 'Uncategorized' }}</p>
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

                                    <!-- Selected Streams (Draggable) -->
                                    <div>
                                        <div class="flex justify-between items-center mb-2">
                                            <h5 class="text-sm font-medium text-gray-700">Selected Streams ({{ selectedStreams.length }})</h5>
                                            <button type="button" @click="removeAllStreams" v-if="selectedStreams.length > 0" class="text-xs text-red-600 hover:text-red-800">Remove All</button>
                                        </div>
                                        <div class="border rounded-lg p-2 bg-gray-50 max-h-96 overflow-y-auto">
                                            <div v-if="selectedStreams.length === 0" class="text-sm text-gray-500 text-center py-8">
                                                No streams selected<br>
                                                <span class="text-xs">Click streams from the left to add them</span>
                                            </div>
                                            <draggable
                                                v-else
                                                v-model="selectedStreams"
                                                item-key="id"
                                                :animation="200"
                                                ghost-class="opacity-50"
                                                class="space-y-1"
                                            >
                                                <template #item="{element: stream, index}">
                                                    <div class="flex items-center justify-between p-2 bg-white hover:bg-red-50 rounded border border-gray-200 cursor-move transition-colors">
                                                        <div class="flex items-center flex-1 min-w-0">
                                                            <svg class="h-5 w-5 text-gray-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                                            </svg>
                                                            <div class="flex-1 min-w-0">
                                                                <div class="flex items-center">
                                                                    <span class="text-xs font-medium text-gray-500 mr-2">#{{ index + 1 }}</span>
                                                                    <p class="text-sm font-medium text-gray-900 truncate">{{ stream.name }}</p>
                                                                </div>
                                                                <p class="text-xs text-gray-500 truncate">{{ stream.category_name || 'Uncategorized' }}</p>
                                                            </div>
                                                        </div>
                                                        <button type="button" @click="removeStreamFromBouquet(stream)" class="ml-2 text-red-600 hover:text-red-700 flex-shrink-0">
                                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </template>
                                            </draggable>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">💡 Drag to reorder streams</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" :disabled="saving" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ saving ? 'Saving...' : 'Save Bouquet' }}
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
import draggable from 'vuedraggable';
import AppLayout from '../../components/AppLayout.vue';
import { bouquetsAPI, streamsAPI, categoriesAPI } from '../../services/api';

const bouquets = ref([]);
const allStreams = ref([]);
const categories = ref([]);
const loading = ref(false);
const loadingStreams = ref(false);
const saving = ref(false);
const showModal = ref(false);
const editingBouquet = ref(null);
const selectedStreams = ref([]);
const streamSearch = ref('');
const streamCategoryFilter = ref('');

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
    sort_order: 0,
    is_active: true,
    stream_ids: []
});

const filteredAvailableStreams = computed(() => {
    const selectedIds = new Set(selectedStreams.value.map(s => s.id));
    let available = allStreams.value.filter(s => !selectedIds.has(s.id));

    if (streamSearch.value) {
        const search = streamSearch.value.toLowerCase();
        available = available.filter(s =>
            s.name.toLowerCase().includes(search)
        );
    }

    if (streamCategoryFilter.value) {
        available = available.filter(s => s.category_id == streamCategoryFilter.value);
    }

    return available;
});

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        filters.value.page = 1;
        fetchBouquets();
    }, 300);
};

const fetchBouquets = async () => {
    loading.value = true;
    try {
        const params = {
            search: filters.value.search,
            page: filters.value.page,
            per_page: filters.value.perPage
        };

        // Only include active filter if it's not null
        if (filters.value.active !== null) {
            params.active = filters.value.active;
        }

        console.log('Fetching bouquets with params:', params);
        const response = await bouquetsAPI.getAll(params);

        console.log('Bouquets API Response:', response.data);

        if (response.data.success) {
            bouquets.value = response.data.data;
            pagination.value = response.data.pagination;
            console.log('Loaded bouquets:', bouquets.value);
            console.log('Pagination:', pagination.value);
        } else {
            console.error('API returned success=false:', response.data);
            alert('Failed to load bouquets: ' + (response.data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error fetching bouquets:', error);
        console.error('Error details:', error.response?.data);
        alert('Failed to load bouquets: ' + (error.response?.data?.message || error.message));
    } finally {
        loading.value = false;
    }
};

const fetchAllStreams = async () => {
    loadingStreams.value = true;
    try {
        // Ensure categories are loaded first
        if (categories.value.length === 0) {
            await fetchCategories();
        }

        console.log('Categories loaded:', categories.value.length, categories.value);

        const response = await streamsAPI.getAll({ per_page: 1000 });
        console.log('Streams API Response:', response.data);

        if (response.data.success) {
            allStreams.value = response.data.data.map(stream => {
                // Find the category name from the categories array
                const category = categories.value.find(cat => cat.id == stream.cat_id);

                const mappedStream = {
                    id: stream.id,
                    name: stream.stream_display_name || stream.name || `Stream ${stream.id}`,
                    category_id: stream.cat_id,
                    category_name: category ? category.name : null,
                    running: stream.running
                };

                if (!category && stream.cat_id) {
                    console.warn('Stream missing category:', stream.id, 'cat_id:', stream.cat_id);
                }

                return mappedStream;
            });
            console.log('Loaded streams:', allStreams.value.length);
        }
    } catch (error) {
        console.error('Error fetching streams:', error);
    } finally {
        loadingStreams.value = false;
    }
};

const fetchCategories = async () => {
    try {
        const response = await categoriesAPI.getAll({ per_page: 1000 });
        if (response.data.success) {
            categories.value = response.data.data;
        }
    } catch (error) {
        console.error('Error fetching categories:', error);
    }
};

const openCreateModal = async () => {
    editingBouquet.value = null;

    // Calculate next sort order (max + 1, or 1 if no bouquets)
    const maxSortOrder = bouquets.value.length > 0
        ? Math.max(...bouquets.value.map(b => b.sort_order || 0))
        : 0;

    formData.value = {
        name: '',
        description: '',
        sort_order: maxSortOrder + 1,
        is_active: true,
        stream_ids: []
    };
    selectedStreams.value = [];
    streamSearch.value = '';
    streamCategoryFilter.value = '';

    if (allStreams.value.length === 0) {
        await fetchAllStreams();
    }
    if (categories.value.length === 0) {
        await fetchCategories();
    }

    showModal.value = true;
};

const editBouquet = async (bouquet) => {
    editingBouquet.value = bouquet;
    formData.value = {
        name: bouquet.name,
        description: bouquet.description || '',
        sort_order: bouquet.sort_order,
        is_active: bouquet.is_active,
        stream_ids: []
    };

    streamSearch.value = '';
    streamCategoryFilter.value = '';

    if (allStreams.value.length === 0) {
        await fetchAllStreams();
    }
    if (categories.value.length === 0) {
        await fetchCategories();
    }

    // Fetch full bouquet details to get streams
    try {
        const response = await bouquetsAPI.getOne(bouquet.id);
        if (response.data.success) {
            const bouquetData = response.data.data;
            selectedStreams.value = bouquetData.streams || [];
        }
    } catch (error) {
        console.error('Error loading bouquet streams:', error);
        selectedStreams.value = [];
    }

    showModal.value = true;
};

const addStreamToBouquet = (stream) => {
    if (!selectedStreams.value.find(s => s.id === stream.id)) {
        selectedStreams.value.push({...stream});
    }
};

const removeStreamFromBouquet = (stream) => {
    selectedStreams.value = selectedStreams.value.filter(s => s.id !== stream.id);
};

const addAllVisibleStreams = () => {
    filteredAvailableStreams.value.forEach(stream => {
        if (!selectedStreams.value.find(s => s.id === stream.id)) {
            selectedStreams.value.push({...stream});
        }
    });
};

const removeAllStreams = () => {
    if (confirm('Are you sure you want to remove all streams from this bouquet?')) {
        selectedStreams.value = [];
    }
};

const saveBouquet = async () => {
    saving.value = true;
    try {
        const data = {
            ...formData.value,
            stream_ids: selectedStreams.value.map(s => s.id)
        };

        const response = editingBouquet.value
            ? await bouquetsAPI.update(editingBouquet.value.id, data)
            : await bouquetsAPI.create(data);

        if (response.data.success) {
            closeModal();

            // Reset to first page and clear search to ensure new bouquet is visible
            if (!editingBouquet.value) {
                filters.value.page = 1;
                filters.value.search = '';
                // Keep active filter as is, since we're creating active bouquets by default
            }

            await fetchBouquets();
            alert(response.data.message || 'Bouquet saved successfully');
        }
    } catch (error) {
        console.error('Error saving bouquet:', error);
        alert(error.response?.data?.message || 'Failed to save bouquet');
    } finally {
        saving.value = false;
    }
};

const toggleStatus = async (bouquet) => {
    try {
        const response = await bouquetsAPI.toggle(bouquet.id);
        if (response.data.success) {
            fetchBouquets();
        }
    } catch (error) {
        console.error('Error toggling status:', error);
        alert('Failed to update bouquet status');
    }
};

const deleteBouquet = async (bouquet) => {
    if (!confirm(`Are you sure you want to delete "${bouquet.name}"? This action cannot be undone.`)) {
        return;
    }

    try {
        const response = await bouquetsAPI.delete(bouquet.id);
        if (response.data.success) {
            fetchBouquets();
            alert(response.data.message || 'Bouquet deleted successfully');
        }
    } catch (error) {
        console.error('Error deleting bouquet:', error);
        alert(error.response?.data?.message || 'Failed to delete bouquet');
    }
};

const changePage = (page) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        filters.value.page = page;
        fetchBouquets();
    }
};

const closeModal = () => {
    showModal.value = false;
    editingBouquet.value = null;
    selectedStreams.value = [];
    streamSearch.value = '';
    streamCategoryFilter.value = '';
};

onMounted(() => {
    fetchBouquets();
});
</script>
