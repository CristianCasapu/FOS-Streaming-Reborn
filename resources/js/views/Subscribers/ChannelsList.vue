<template>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        TV Channels
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Manage TV channels and link them to streams
                    </p>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button @click="syncFromStreams" type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Sync from Streams
                    </button>
                    <button @click="openCreateModal" type="button" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        New Channel
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white shadow rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Search</label>
                        <input v-model="filters.search" @input="debouncedFetch" type="text" placeholder="Search channels..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Category</label>
                        <select v-model="filters.category" @change="fetchChannels" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">All Categories</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select v-model="filters.active" @change="fetchChannels" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">All Channels</option>
                            <option value="1">Active Only</option>
                            <option value="0">Inactive Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Per Page</label>
                        <select v-model="filters.perPage" @change="fetchChannels" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Channels Grid -->
            <div v-if="loading" class="text-center py-12 bg-white shadow rounded-lg">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                <p class="mt-2 text-sm text-gray-500">Loading channels...</p>
            </div>

            <div v-else-if="channels.length === 0" class="text-center py-12 bg-white shadow rounded-lg">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No channels</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating a new channel or syncing from streams.</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="channel in channels" :key="channel.id" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-lg transition-shadow">
                    <div class="p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <div v-if="channel.logo_url" class="flex-shrink-0 h-12 w-12 rounded-lg overflow-hidden bg-gray-100">
                                    <img :src="channel.logo_url" :alt="channel.name" class="h-full w-full object-cover">
                                </div>
                                <div v-else class="flex-shrink-0 h-12 w-12 rounded-lg bg-indigo-100 flex items-center justify-center">
                                    <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-gray-900">{{ channel.name }}</h3>
                                    <p class="text-xs text-gray-500">{{ channel.category_name || 'No category' }}</p>
                                </div>
                            </div>
                            <span v-if="channel.is_active" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                Active
                            </span>
                            <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                Inactive
                            </span>
                        </div>

                        <div class="text-sm text-gray-500 mb-3 line-clamp-2">
                            {{ channel.description || 'No description available' }}
                        </div>

                        <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
                            <div>
                                <span class="font-medium">Stream:</span>
                                <span v-if="channel.stream_name" class="ml-1">{{ channel.stream_name }}</span>
                                <span v-else class="ml-1 text-red-500">Not linked</span>
                            </div>
                            <div v-if="channel.bouquet_count > 0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    {{ channel.bouquet_count }} {{ channel.bouquet_count === 1 ? 'bouquet' : 'bouquets' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex justify-between items-center pt-3 border-t border-gray-200">
                            <button @click="viewChannel(channel.id)" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">
                                View Details
                            </button>
                            <div class="flex space-x-2">
                                <button @click="editChannel(channel)" class="text-yellow-600 hover:text-yellow-900" title="Edit">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button @click="toggleStatus(channel)" class="text-blue-600 hover:text-blue-900" :title="channel.is_active ? 'Deactivate' : 'Activate'">
                                    <svg v-if="channel.is_active" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <button @click="deleteChannel(channel)" class="text-red-600 hover:text-red-900" title="Delete">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.total > 0" class="mt-6 bg-white shadow rounded-lg px-4 py-3 flex items-center justify-between">
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

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <form @submit.prevent="saveChannel">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                {{ editingChannel ? 'Edit Channel' : 'Create New Channel' }}
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
                                        <label class="block text-sm font-medium text-gray-700">Stream</label>
                                        <select v-model="formData.stream_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option :value="null">No stream linked</option>
                                            <option v-for="stream in availableStreams" :key="stream.id" :value="stream.id">
                                                {{ stream.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Category</label>
                                        <select v-model="formData.category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option :value="null">No category</option>
                                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                                {{ cat.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Logo URL</label>
                                    <input v-model="formData.logo_url" type="url" placeholder="https://example.com/logo.png" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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
        <div v-if="showDetailsModal && selectedChannel" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeDetailsModal"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center">
                                <div v-if="selectedChannel.logo_url" class="flex-shrink-0 h-16 w-16 rounded-lg overflow-hidden bg-gray-100">
                                    <img :src="selectedChannel.logo_url" :alt="selectedChannel.name" class="h-full w-full object-cover">
                                </div>
                                <div v-else class="flex-shrink-0 h-16 w-16 rounded-lg bg-indigo-100 flex items-center justify-center">
                                    <svg class="h-8 w-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">{{ selectedChannel.name }}</h3>
                                    <p class="text-sm text-gray-500">{{ selectedChannel.category_name || 'No category' }}</p>
                                </div>
                            </div>
                            <button @click="closeDetailsModal" class="text-gray-400 hover:text-gray-500">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mb-4">
                            <p class="text-sm text-gray-600">{{ selectedChannel.description || 'No description available' }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Linked Stream</p>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedChannel.stream_name || 'Not linked' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Status</p>
                                <p class="mt-1">
                                    <span v-if="selectedChannel.is_active" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inactive</span>
                                </p>
                            </div>
                        </div>

                        <div v-if="selectedChannel.bouquets && selectedChannel.bouquets.length > 0">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Included in Bouquets</h4>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="bouquet in selectedChannel.bouquets" :key="bouquet.id" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    {{ bouquet.name }}
                                </span>
                            </div>
                        </div>

                        <div v-if="selectedChannel.created_at" class="mt-4 pt-4 border-t border-gray-200">
                            <div class="grid grid-cols-2 gap-4 text-xs text-gray-500">
                                <div>
                                    <p>Created: {{ new Date(selectedChannel.created_at).toLocaleDateString() }}</p>
                                </div>
                                <div>
                                    <p>Updated: {{ new Date(selectedChannel.updated_at).toLocaleDateString() }}</p>
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
import { ref, onMounted } from 'vue';
import { channelsAPI } from '../../services/api';

const channels = ref([]);
const categories = ref([]);
const availableStreams = ref([]);
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const showDetailsModal = ref(false);
const editingChannel = ref(null);
const selectedChannel = ref(null);

const filters = ref({
    search: '',
    category: '',
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
    stream_id: null,
    category_id: null,
    logo_url: '',
    is_active: 1
});

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        filters.value.page = 1;
        fetchChannels();
    }, 300);
};

const fetchChannels = async () => {
    loading.value = true;
    try {
        const response = await channelsAPI.getAll({
            search: filters.value.search,
            category_id: filters.value.category,
            active: filters.value.active,
            page: filters.value.page,
            per_page: filters.value.perPage
        });
        if (response.data.success) {
            channels.value = response.data.data;
            pagination.value = response.data.pagination;
        }
    } catch (error) {
        console.error('Error fetching channels:', error);
        alert('Failed to load channels');
    } finally {
        loading.value = false;
    }
};

const fetchAvailableStreams = async () => {
    try {
        const response = await channelsAPI.getAvailableStreams();
        if (response.data.success) {
            availableStreams.value = response.data.data;
        }
    } catch (error) {
        console.error('Error fetching available streams:', error);
    }
};

const syncFromStreams = async () => {
    if (!confirm('This will create channels for all streams that do not have a linked channel. Continue?')) {
        return;
    }

    try {
        const response = await channelsAPI.syncFromStreams();
        if (response.data.success) {
            alert(response.data.message);
            fetchChannels();
        }
    } catch (error) {
        console.error('Error syncing from streams:', error);
        alert(error.response?.data?.message || 'Failed to sync from streams');
    }
};

const viewChannel = async (id) => {
    try {
        const response = await channelsAPI.getOne(id);
        if (response.data.success) {
            selectedChannel.value = response.data.data;
            showDetailsModal.value = true;
        }
    } catch (error) {
        console.error('Error fetching channel details:', error);
        alert('Failed to load channel details');
    }
};

const openCreateModal = () => {
    editingChannel.value = null;
    formData.value = {
        name: '',
        description: '',
        stream_id: null,
        category_id: null,
        logo_url: '',
        is_active: 1
    };
    showModal.value = true;
};

const editChannel = (channel) => {
    editingChannel.value = channel;
    formData.value = {
        name: channel.name,
        description: channel.description || '',
        stream_id: channel.stream_id,
        category_id: channel.category_id,
        logo_url: channel.logo_url || '',
        is_active: channel.is_active
    };
    showModal.value = true;
};

const saveChannel = async () => {
    saving.value = true;
    try {
        const response = editingChannel.value
            ? await channelsAPI.update(editingChannel.value.id, formData.value)
            : await channelsAPI.create(formData.value);

        if (response.data.success) {
            closeModal();
            fetchChannels();
            alert(response.data.message);
        }
    } catch (error) {
        console.error('Error saving channel:', error);
        alert(error.response?.data?.message || 'Failed to save channel');
    } finally {
        saving.value = false;
    }
};

const toggleStatus = async (channel) => {
    try {
        const response = await channelsAPI.toggle(channel.id);
        if (response.data.success) {
            fetchChannels();
        }
    } catch (error) {
        console.error('Error toggling status:', error);
        alert('Failed to update channel status');
    }
};

const deleteChannel = async (channel) => {
    if (!confirm(`Are you sure you want to delete "${channel.name}"? This action cannot be undone.`)) {
        return;
    }

    try {
        const response = await channelsAPI.delete(channel.id);
        if (response.data.success) {
            fetchChannels();
            alert(response.data.message);
        }
    } catch (error) {
        console.error('Error deleting channel:', error);
        alert(error.response?.data?.message || 'Failed to delete channel');
    }
};

const changePage = (page) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        filters.value.page = page;
        fetchChannels();
    }
};

const closeModal = () => {
    showModal.value = false;
    editingChannel.value = null;
};

const closeDetailsModal = () => {
    showDetailsModal.value = false;
    selectedChannel.value = null;
};

onMounted(() => {
    fetchChannels();
    fetchAvailableStreams();
    // TODO: Fetch categories when categories API is ready
    // For now using mock categories
    categories.value = [
        { id: 1, name: 'Sports' },
        { id: 2, name: 'Movies' },
        { id: 3, name: 'News' },
        { id: 4, name: 'Entertainment' }
    ];
});
</script>
