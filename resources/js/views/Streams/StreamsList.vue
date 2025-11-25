<template>
    <AppLayout>
        <div class="py-6">
            <div class="px-4 py-6 sm:px-0">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">{{ title }}</h1>
                        <p class="mt-2 text-sm text-gray-600">Manage your streaming sources</p>
                    </div>
                    <div class="flex space-x-3">
                        <button @click="showAddModal = true" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Stream
                        </button>
                        <button @click="showImportWizard = true" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            Import Streams
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div v-if="stats" class="px-4 sm:px-0 mb-6">
                <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="text-2xl font-bold text-gray-900">{{ stats.total }}</div>
                        <div class="text-sm text-gray-500">Total</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="text-2xl font-bold text-green-600">{{ stats.enabled }}</div>
                        <div class="text-sm text-gray-500">Enabled</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="text-2xl font-bold text-gray-400">{{ stats.disabled }}</div>
                        <div class="text-sm text-gray-500">Disabled</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="text-2xl font-bold text-blue-600">{{ stats.running }}</div>
                        <div class="text-sm text-gray-500">Running</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="text-2xl font-bold text-gray-500">{{ stats.stopped }}</div>
                        <div class="text-sm text-gray-500">Stopped</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="text-2xl font-bold text-red-600">{{ stats.error }}</div>
                        <div class="text-sm text-gray-500">Errors</div>
                    </div>
                </div>
            </div>

            <!-- Mass Actions Toolbar -->
            <div class="px-4 sm:px-0 mb-6">
                <div class="bg-white shadow rounded-lg p-4">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="text-sm font-medium text-gray-700">Mass Actions (All Streams)</div>
                        <div class="flex flex-wrap gap-2">
                            <button @click="enableAll" class="px-3 py-1.5 bg-green-100 text-green-700 text-sm rounded hover:bg-green-200">Enable All</button>
                            <button @click="disableAll" class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Disable All</button>
                            <span class="border-l border-gray-300 mx-2"></span>
                            <button @click="startAll" class="px-3 py-1.5 bg-blue-100 text-blue-700 text-sm rounded hover:bg-blue-200">Start All</button>
                            <button @click="stopAll" class="px-3 py-1.5 bg-yellow-100 text-yellow-700 text-sm rounded hover:bg-yellow-200">Stop All</button>
                            <button @click="restartAll" class="px-3 py-1.5 bg-purple-100 text-purple-700 text-sm rounded hover:bg-purple-200">Restart All</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="px-4 sm:px-0 mb-6">
                <div class="bg-white shadow rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input v-model="searchQuery" @input="debouncedSearch" type="text" placeholder="Search..." class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select v-model="filterStatus" @change="fetchStreams" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="">All</option>
                                <option value="1">Running</option>
                                <option value="2">Error/Crashed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
                            <select v-model="perPage" @change="fetchStreams" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option :value="10">10</option>
                                <option :value="20">20</option>
                                <option :value="50">50</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selection Actions -->
            <div v-if="selectedStreams.length > 0" class="px-4 sm:px-0 mb-6">
                <div class="bg-indigo-50 p-4 rounded-md flex flex-wrap justify-between items-center gap-4">
                    <p class="text-sm text-indigo-700"><strong>{{ selectedStreams.length }}</strong> stream(s) selected</p>
                    <div class="flex flex-wrap gap-2">
                        <button @click="bulkEnable" class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">Enable</button>
                        <button @click="bulkDisable" class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">Disable</button>
                        <span class="border-l border-indigo-200 mx-1"></span>
                        <button @click="bulkStart" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Start</button>
                        <button @click="bulkStop" class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">Stop</button>
                        <button @click="bulkRestart" class="px-3 py-1 bg-purple-600 text-white text-sm rounded hover:bg-purple-700">Restart</button>
                        <span class="border-l border-indigo-200 mx-1"></span>
                        <button @click="bulkDelete" class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>

            <div class="px-4 sm:px-0">
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div v-if="loading" class="p-12 text-center">
                        <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <p class="mt-4 text-gray-600">Loading...</p>
                    </div>
                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left"><input type="checkbox" @change="toggleSelectAll" :checked="selectedStreams.length === streams.length && streams.length > 0" class="rounded" /></th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Enabled</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">State</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="stream in streams" :key="stream.id" :class="['hover:bg-gray-50', !stream.enabled && 'bg-gray-50 opacity-60']">
                                <td class="px-4 py-4"><input type="checkbox" :value="stream.id" v-model="selectedStreams" class="rounded" /></td>
                                <td class="px-4 py-4 text-sm font-medium">
                                    <div class="flex items-center">
                                        <button
                                            v-if="stream.status === 1"
                                            @click="previewStream(stream)"
                                            class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-purple-100 hover:bg-purple-200 text-purple-600 hover:text-purple-800 transition-colors mr-3"
                                            title="Preview Stream"
                                        >
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                                            </svg>
                                        </button>
                                        <span v-else class="flex-shrink-0 w-8 h-8 mr-3"></span>
                                        <div>
                                            <div>{{ stream.name }}</div>
                                            <div class="text-xs text-gray-400 truncate max-w-xs">{{ stream.stream_source }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-sm">{{ stream.category }}</td>
                                <td class="px-4 py-4">
                                    <button
                                        @click="toggleEnabled(stream)"
                                        :class="[
                                            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                                            stream.enabled ? 'bg-green-500' : 'bg-gray-300'
                                        ]"
                                        :title="stream.enabled ? 'Click to disable' : 'Click to enable'"
                                    >
                                        <span
                                            :class="[
                                                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                                stream.enabled ? 'translate-x-5' : 'translate-x-0'
                                            ]"
                                        ></span>
                                    </button>
                                </td>
                                <td class="px-4 py-4">
                                    <span :class="[
                                        'px-2 py-1 text-xs rounded-full',
                                        stream.state === 'running' ? 'bg-green-100 text-green-800' :
                                        stream.state === 'starting' ? 'bg-blue-100 text-blue-800' :
                                        stream.state === 'stopping' ? 'bg-yellow-100 text-yellow-800' :
                                        stream.state === 'error' || stream.state === 'crashed' ? 'bg-red-100 text-red-800' :
                                        'bg-gray-100 text-gray-800'
                                    ]">{{ stream.state?.toUpperCase() || 'STOPPED' }}</span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex justify-end space-x-1">
                                        <router-link :to="`/streams/${stream.id}`" class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200">View</router-link>
                                        <button
                                            v-if="stream.enabled && stream.state !== 'running' && stream.state !== 'starting'"
                                            @click="startStream(stream.id)"
                                            class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200"
                                        >Start</button>
                                        <button
                                            v-if="stream.state === 'running' || stream.state === 'starting'"
                                            @click="stopStream(stream.id)"
                                            class="px-2 py-1 text-xs bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200"
                                        >Stop</button>
                                        <button
                                            v-if="stream.enabled && (stream.state === 'running' || stream.state === 'starting')"
                                            @click="restartStream(stream.id)"
                                            class="px-2 py-1 text-xs bg-purple-100 text-purple-700 rounded hover:bg-purple-200"
                                        >Restart</button>
                                        <button @click="confirmDelete(stream)" class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="streams.length === 0"><td colspan="6" class="px-6 py-12 text-center text-gray-500">No streams found</td></tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="pagination.last_page > 1" class="bg-gray-50 px-4 py-3 flex items-center justify-between border-t border-gray-200">
                        <div class="text-sm text-gray-700">
                            Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }} to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }}
                        </div>
                        <div class="flex space-x-2">
                            <button
                                @click="goToPage(pagination.current_page - 1)"
                                :disabled="pagination.current_page === 1"
                                class="px-3 py-1 border rounded text-sm disabled:opacity-50"
                            >Previous</button>
                            <button
                                @click="goToPage(pagination.current_page + 1)"
                                :disabled="pagination.current_page === pagination.last_page"
                                class="px-3 py-1 border rounded text-sm disabled:opacity-50"
                            >Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium mb-4">Confirm Delete</h3>
                <p class="text-sm text-gray-500 mb-6">Delete "{{ streamToDelete?.name }}"?</p>
                <div class="flex justify-end space-x-3">
                    <button @click="showDeleteModal = false" class="px-4 py-2 border rounded-md">Cancel</button>
                    <button @click="deleteStream" class="px-4 py-2 bg-red-600 text-white rounded-md">Delete</button>
                </div>
            </div>
        </div>

        <!-- Add Stream Modal -->
        <div v-if="showAddModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium mb-4">Add New Stream</h3>
                <form @submit.prevent="createStream">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stream Name</label>
                            <input v-model="newStream.name" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="My Stream" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stream Source (URL)</label>
                            <input v-model="newStream.stream_source" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="rtmp://example.com/live/stream" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category (Optional)</label>
                            <select v-model="newStream.cat_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="0">None</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="showAddModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Create Stream</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Import Wizard -->
        <StreamImportWizard
            :show="showImportWizard"
            :categories="categories"
            @close="showImportWizard = false"
            @imported="handleImported"
        />

        <!-- Preview Modal -->
        <div v-if="showPreviewModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50" @click.self="closePreview">
            <div class="relative w-full max-w-5xl mx-4">
                <button @click="closePreview" class="absolute -top-12 right-0 text-white hover:text-gray-300 transition-colors">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="bg-gray-900 text-white px-6 py-4 rounded-t-lg">
                    <h3 class="text-xl font-semibold">{{ previewStreamData?.name }}</h3>
                    <p class="text-sm text-gray-400 mt-1">{{ previewStreamData?.stream_source }}</p>
                </div>
                <div class="bg-black rounded-b-lg overflow-hidden">
                    <div v-if="loadingPreview" class="p-12 text-center text-gray-400">
                        <svg class="animate-spin h-12 w-12 mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-lg">Loading preview...</p>
                    </div>
                    <video v-else-if="previewUrls && !videoError" ref="videoPlayer" class="w-full h-auto" controls autoplay :key="previewStreamData?.id">
                        <source v-if="previewUrls.proxy" :src="previewUrls.proxy" type="video/mp2t">
                        <source v-if="previewUrls.hls" :src="previewUrls.hls" type="application/x-mpegURL">
                        <source v-if="previewUrls.direct" :src="previewUrls.direct" type="video/mp4">
                        Your browser does not support video playback.
                    </video>
                    <div v-if="previewUrls && !loadingPreview && !videoError" class="bg-gray-900 px-6 py-3 text-xs text-gray-400">
                        <p>
                            <span v-if="previewUrls.proxy" class="text-green-400">Proxy Stream</span>
                            <span v-if="previewUrls.hls" class="text-blue-400 ml-2">HLS</span>
                            <span v-if="previewUrls.direct" class="text-yellow-400 ml-2">Direct</span>
                        </p>
                    </div>
                    <div v-if="videoError && !loadingPreview" class="p-8 text-center text-gray-400">
                        <svg class="h-16 w-16 mx-auto mb-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-lg font-medium">Unable to play stream</p>
                        <p class="text-sm mt-2">{{ videoError }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { streamsAPI, categoriesAPI } from '../../services/api';
import { useToastStore } from '../../stores/toast';
import AppLayout from '../../components/AppLayout.vue';
import StreamImportWizard from '../../components/StreamImportWizard.vue';
import Hls from 'hls.js';

const toast = useToastStore();

const streams = ref([]);
const stats = ref(null);
const loading = ref(false);
const searchQuery = ref('');
const filterStatus = ref('');
const perPage = ref(20);
const currentPage = ref(1);
const pagination = ref({ total: 0, per_page: 20, current_page: 1, last_page: 1 });
const selectedStreams = ref([]);
const showDeleteModal = ref(false);
const streamToDelete = ref(null);

const showPreviewModal = ref(false);
const previewStreamData = ref(null);
const videoPlayer = ref(null);
const videoError = ref(null);
const previewUrls = ref(null);
const loadingPreview = ref(false);
const hlsInstance = ref(null);

const showAddModal = ref(false);
const showImportWizard = ref(false);
const categories = ref([]);
const newStream = ref({ name: '', stream_source: '', cat_id: 0 });

const title = computed(() => filterStatus.value === '1' ? 'Running Streams' : filterStatus.value === '2' ? 'Error Streams' : 'All Streams');

const fetchStreams = async () => {
    loading.value = true;
    try {
        const params = { page: currentPage.value, per_page: perPage.value };
        if (filterStatus.value) params.running = filterStatus.value;
        if (searchQuery.value) params.search = searchQuery.value;
        const response = await streamsAPI.getAll(params);
        streams.value = response.data.data;
        pagination.value = response.data.pagination;
        selectedStreams.value = [];
    } catch (error) {
        toast.error('Failed to load streams', { title: 'Error', details: error.message });
    } finally {
        loading.value = false;
    }
};

const fetchStats = async () => {
    try {
        const response = await streamsAPI.getStats();
        stats.value = response.data.data;
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
};

let searchTimeout;
const debouncedSearch = () => { clearTimeout(searchTimeout); searchTimeout = setTimeout(() => { currentPage.value = 1; fetchStreams(); }, 500); };
const goToPage = (page) => { currentPage.value = page; fetchStreams(); };
const toggleSelectAll = (e) => { selectedStreams.value = e.target.checked ? streams.value.map(s => s.id) : []; };

const startStream = async (id) => { try { await streamsAPI.start(id); toast.success('Stream queued to start', { title: 'Started' }); fetchStreams(); fetchStats(); } catch (e) { toast.error(e.response?.data?.message || 'Failed to start stream', { title: 'Error' }); } };
const stopStream = async (id) => { try { await streamsAPI.stop(id); toast.success('Stream queued to stop', { title: 'Stopped' }); fetchStreams(); fetchStats(); } catch (e) { toast.error(e.response?.data?.message || 'Failed to stop stream', { title: 'Error' }); } };
const restartStream = async (id) => { try { await streamsAPI.massRestart([id]); toast.success('Stream queued to restart', { title: 'Restarting' }); fetchStreams(); } catch (e) { toast.error(e.response?.data?.message || 'Failed to restart stream', { title: 'Error' }); } };

const toggleEnabled = async (stream) => {
    try {
        if (stream.enabled) { await streamsAPI.disable(stream.id); toast.success('Stream disabled', { title: 'Disabled' }); }
        else { await streamsAPI.enable(stream.id); toast.success('Stream enabled', { title: 'Enabled' }); }
        fetchStreams(); fetchStats();
    } catch (e) { toast.error(e.response?.data?.message || 'Failed to toggle stream', { title: 'Error' }); }
};

const confirmDelete = (stream) => { streamToDelete.value = stream; showDeleteModal.value = true; };
const deleteStream = async () => { try { await streamsAPI.delete(streamToDelete.value.id); toast.success('Stream deleted', { title: 'Deleted' }); showDeleteModal.value = false; fetchStreams(); fetchStats(); } catch (e) { toast.error(e.response?.data?.message || 'Failed to delete stream', { title: 'Error' }); } };

const bulkEnable = async () => { if (!confirm('Enable selected streams?')) return; try { await streamsAPI.massEnable(selectedStreams.value); toast.success(`${selectedStreams.value.length} stream(s) enabled`, { title: 'Bulk Enable' }); fetchStreams(); fetchStats(); } catch (e) { toast.error('Failed to enable streams', { title: 'Error' }); } };
const bulkDisable = async () => { if (!confirm('Disable selected streams?')) return; try { await streamsAPI.massDisable(selectedStreams.value); toast.success(`${selectedStreams.value.length} stream(s) disabled`, { title: 'Bulk Disable' }); fetchStreams(); fetchStats(); } catch (e) { toast.error('Failed to disable streams', { title: 'Error' }); } };
const bulkStart = async () => { if (!confirm('Start selected streams?')) return; try { await streamsAPI.massStart(selectedStreams.value); toast.success(`${selectedStreams.value.length} stream(s) queued to start`, { title: 'Bulk Start' }); fetchStreams(); fetchStats(); } catch (e) { toast.error('Failed to start streams', { title: 'Error' }); } };
const bulkStop = async () => { if (!confirm('Stop selected streams?')) return; try { await streamsAPI.massStop(selectedStreams.value); toast.success(`${selectedStreams.value.length} stream(s) queued to stop`, { title: 'Bulk Stop' }); fetchStreams(); fetchStats(); } catch (e) { toast.error('Failed to stop streams', { title: 'Error' }); } };
const bulkRestart = async () => { if (!confirm('Restart selected streams?')) return; try { await streamsAPI.massRestart(selectedStreams.value); toast.success(`${selectedStreams.value.length} stream(s) queued to restart`, { title: 'Bulk Restart' }); fetchStreams(); } catch (e) { toast.error('Failed to restart streams', { title: 'Error' }); } };
const bulkDelete = async () => { if (!confirm('Delete selected streams? This cannot be undone.')) return; try { await streamsAPI.massDelete(selectedStreams.value); toast.success(`${selectedStreams.value.length} stream(s) deleted`, { title: 'Bulk Delete' }); fetchStreams(); fetchStats(); } catch (e) { toast.error('Failed to delete streams', { title: 'Error' }); } };

const enableAll = async () => { if (!confirm('Enable ALL streams? This will affect all streams in the database.')) return; try { const response = await streamsAPI.enableAll(); toast.success(response.data.message, { title: 'Enable All' }); fetchStreams(); fetchStats(); } catch (e) { toast.error('Failed to enable all streams', { title: 'Error' }); } };
const disableAll = async () => { if (!confirm('Disable ALL streams? This will stop all running streams.')) return; try { const response = await streamsAPI.disableAll(); toast.success(response.data.message, { title: 'Disable All' }); fetchStreams(); fetchStats(); } catch (e) { toast.error('Failed to disable all streams', { title: 'Error' }); } };
const startAll = async () => { if (!confirm('Start ALL enabled streams?')) return; try { const response = await streamsAPI.startAll(); toast.success(response.data.message, { title: 'Start All' }); fetchStreams(); fetchStats(); } catch (e) { toast.error('Failed to start all streams', { title: 'Error' }); } };
const stopAll = async () => { if (!confirm('Stop ALL running streams?')) return; try { const response = await streamsAPI.stopAll(); toast.success(response.data.message, { title: 'Stop All' }); fetchStreams(); fetchStats(); } catch (e) { toast.error('Failed to stop all streams', { title: 'Error' }); } };
const restartAll = async () => { if (!confirm('Restart ALL enabled running streams?')) return; try { const response = await streamsAPI.restartAll(); toast.success(response.data.message, { title: 'Restart All' }); fetchStreams(); } catch (e) { toast.error('Failed to restart all streams', { title: 'Error' }); } };

const initHlsPlayer = (url) => {
    if (!videoPlayer.value) return;
    if (hlsInstance.value) { hlsInstance.value.destroy(); hlsInstance.value = null; }
    if (Hls.isSupported()) {
        const hls = new Hls({ enableWorker: true, lowLatencyMode: true, backBufferLength: 90 });
        hls.loadSource(url); hls.attachMedia(videoPlayer.value);
        hls.on(Hls.Events.MANIFEST_PARSED, () => { videoPlayer.value.play().catch(() => {}); });
        hls.on(Hls.Events.ERROR, (event, data) => { if (data.fatal) { switch (data.type) { case Hls.ErrorTypes.NETWORK_ERROR: videoError.value = 'Network error - trying to recover...'; hls.startLoad(); break; case Hls.ErrorTypes.MEDIA_ERROR: videoError.value = 'Media error - trying to recover...'; hls.recoverMediaError(); break; default: videoError.value = 'Fatal error loading stream'; hls.destroy(); break; } } });
        hlsInstance.value = hls;
    } else if (videoPlayer.value.canPlayType('application/vnd.apple.mpegurl')) { videoPlayer.value.src = url; videoPlayer.value.play().catch(() => {}); }
    else { videoError.value = 'HLS is not supported in this browser'; }
};

const previewStream = async (stream) => {
    previewStreamData.value = stream; videoError.value = null; previewUrls.value = null; loadingPreview.value = true; showPreviewModal.value = true;
    try {
        const response = await streamsAPI.getPreviewUrls(stream.id); previewUrls.value = response.data.data.urls;
        await nextTick();
        setTimeout(() => {
            if (videoPlayer.value && previewUrls.value) {
                if (previewUrls.value.hls) { initHlsPlayer(previewUrls.value.hls); }
                else if (previewUrls.value.proxy) { videoPlayer.value.src = previewUrls.value.proxy; videoPlayer.value.play().catch(() => {}); }
                else if (previewUrls.value.direct) { videoPlayer.value.src = previewUrls.value.direct; videoPlayer.value.play().catch(() => {}); }
                videoPlayer.value.addEventListener('error', () => { if (!videoError.value) { videoError.value = 'Unable to play stream with current format'; } });
            }
        }, 100);
    } catch (error) { videoError.value = 'Failed to load preview URLs: ' + (error.response?.data?.message || error.message); }
    finally { loadingPreview.value = false; }
};

const closePreview = () => {
    showPreviewModal.value = false;
    if (hlsInstance.value) { hlsInstance.value.destroy(); hlsInstance.value = null; }
    if (videoPlayer.value) { videoPlayer.value.pause(); videoPlayer.value.src = ''; videoPlayer.value.load(); }
    previewStreamData.value = null; videoError.value = null; previewUrls.value = null; loadingPreview.value = false;
};

const fetchCategories = async () => { try { const response = await categoriesAPI.getAll(); categories.value = response.data.data || []; } catch (error) { console.error('Failed to load categories:', error); } };
const createStream = async () => { try { await streamsAPI.create(newStream.value); toast.success('Stream created successfully', { title: 'Created' }); showAddModal.value = false; newStream.value = { name: '', stream_source: '', cat_id: 0 }; fetchStreams(); fetchStats(); } catch (error) { toast.error('Failed to create stream', { title: 'Error', details: error.response?.data?.message || error.message }); } };
const handleImported = (count) => { toast.success(`Successfully imported ${count} stream(s). Streams are disabled by default.`, { title: 'Import Complete' }); fetchStreams(); fetchStats(); fetchCategories(); };

onMounted(() => { fetchStreams(); fetchStats(); fetchCategories(); });
</script>
