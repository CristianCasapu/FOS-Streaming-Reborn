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
                                <option value="1">Online</option>
                                <option value="2">Offline</option>
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

            <div v-if="message" class="px-4 sm:px-0 mb-6">
                <div :class="['rounded-md p-4', message.type === 'success' ? 'bg-green-50' : 'bg-red-50']">
                    <p :class="['text-sm', message.type === 'success' ? 'text-green-800' : 'text-red-800']">{{ message.text }}</p>
                </div>
            </div>

            <div v-if="selectedStreams.length > 0" class="px-4 sm:px-0 mb-6">
                <div class="bg-indigo-50 p-4 rounded-md flex justify-between items-center">
                    <p class="text-sm text-indigo-700"><strong>{{ selectedStreams.length }}</strong> selected</p>
                    <div class="space-x-2">
                        <button @click="bulkStart" class="px-3 py-1 bg-green-600 text-white text-sm rounded">Start</button>
                        <button @click="bulkStop" class="px-3 py-1 bg-yellow-600 text-white text-sm rounded">Stop</button>
                        <button @click="bulkDelete" class="px-3 py-1 bg-red-600 text-white text-sm rounded">Delete</button>
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
                                <th class="px-6 py-3 text-left"><input type="checkbox" @change="toggleSelectAll" :checked="selectedStreams.length === streams.length && streams.length > 0" class="rounded" /></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="stream in streams" :key="stream.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4"><input type="checkbox" :value="stream.id" v-model="selectedStreams" class="rounded" /></td>
                                <td class="px-6 py-4 text-sm font-medium">{{ stream.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 truncate max-w-xs">{{ stream.stream_source }}</td>
                                <td class="px-6 py-4 text-sm">{{ stream.category }}</td>
                                <td class="px-6 py-4"><span :class="['px-2 py-1 text-xs rounded-full', stream.status === 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">{{ stream.status === 1 ? 'RUNNING' : 'STOPPED' }}</span></td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <router-link :to="`/streams/${stream.id}`" class="text-blue-600 hover:text-blue-900">View</router-link>
                                    <button v-if="stream.status === 1" @click="previewStream(stream)" class="text-purple-600 hover:text-purple-900 inline-flex items-center" title="Preview Stream">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                                        </svg>
                                    </button>
                                    <button v-if="stream.status !== 1" @click="startStream(stream.id)" class="text-green-600 hover:text-green-900">Start</button>
                                    <button v-else @click="stopStream(stream.id)" class="text-yellow-600 hover:text-yellow-900">Stop</button>
                                    <button @click="confirmDelete(stream)" class="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="streams.length === 0"><td colspan="6" class="px-6 py-12 text-center text-gray-500">No streams found</td></tr>
                        </tbody>
                    </table>
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
                <!-- Close Button -->
                <button @click="closePreview" class="absolute -top-12 right-0 text-white hover:text-gray-300 transition-colors">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Stream Info -->
                <div class="bg-gray-900 text-white px-6 py-4 rounded-t-lg">
                    <h3 class="text-xl font-semibold">{{ previewStreamData?.name }}</h3>
                    <p class="text-sm text-gray-400 mt-1">{{ previewStreamData?.stream_source }}</p>
                </div>

                <!-- Video Player -->
                <div class="bg-black rounded-b-lg overflow-hidden">
                    <video
                        ref="videoPlayer"
                        class="w-full h-auto"
                        controls
                        autoplay
                        :key="previewStreamData?.id"
                    >
                        <source :src="getPlaybackUrl(previewStreamData)" type="video/mp4">
                        <source :src="getPlaybackUrl(previewStreamData)" type="application/x-mpegURL">
                        Your browser does not support video playback.
                    </video>

                    <!-- Error Message -->
                    <div v-if="videoError" class="p-8 text-center text-gray-400">
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
import { ref, computed, onMounted } from 'vue';
import { streamsAPI, categoriesAPI } from '../../services/api';
import AppLayout from '../../components/AppLayout.vue';
import StreamImportWizard from '../../components/StreamImportWizard.vue';

const streams = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const filterStatus = ref('');
const perPage = ref(20);
const currentPage = ref(1);
const pagination = ref({ total: 0, per_page: 20, current_page: 1, last_page: 1 });
const selectedStreams = ref([]);
const showDeleteModal = ref(false);
const streamToDelete = ref(null);
const message = ref(null);

// Preview modal
const showPreviewModal = ref(false);
const previewStreamData = ref(null);
const videoPlayer = ref(null);
const videoError = ref(null);

// Add/Import stream modals
const showAddModal = ref(false);
const showImportWizard = ref(false);
const categories = ref([]);
const newStream = ref({
    name: '',
    stream_source: '',
    cat_id: 0
});

const title = computed(() => filterStatus.value === '1' ? 'Running Streams' : filterStatus.value === '2' ? 'Stopped Streams' : 'All Streams');

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
        showMessage('Error: ' + error.message, 'error');
    } finally {
        loading.value = false;
    }
};

let searchTimeout;
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { currentPage.value = 1; fetchStreams(); }, 500);
};

const toggleSelectAll = (e) => { selectedStreams.value = e.target.checked ? streams.value.map(s => s.id) : []; };
const startStream = async (id) => { try { await streamsAPI.start(id); showMessage('Started'); fetchStreams(); } catch (e) { showMessage('Error', 'error'); } };
const stopStream = async (id) => { try { await streamsAPI.stop(id); showMessage('Stopped'); fetchStreams(); } catch (e) { showMessage('Error', 'error'); } };
const confirmDelete = (stream) => { streamToDelete.value = stream; showDeleteModal.value = true; };
const deleteStream = async () => { try { await streamsAPI.delete(streamToDelete.value.id); showMessage('Deleted'); showDeleteModal.value = false; fetchStreams(); } catch (e) { showMessage('Error', 'error'); } };
const bulkStart = async () => { if (!confirm('Start selected?')) return; try { await streamsAPI.massStart(selectedStreams.value); showMessage('Started'); fetchStreams(); } catch (e) { showMessage('Error', 'error'); } };
const bulkStop = async () => { if (!confirm('Stop selected?')) return; try { await streamsAPI.massStop(selectedStreams.value); showMessage('Stopped'); fetchStreams(); } catch (e) { showMessage('Error', 'error'); } };
const bulkDelete = async () => { if (!confirm('Delete selected?')) return; try { await streamsAPI.massDelete(selectedStreams.value); showMessage('Deleted'); fetchStreams(); } catch (e) { showMessage('Error', 'error'); } };
const showMessage = (text, type = 'success') => { message.value = { text, type }; setTimeout(() => message.value = null, 5000); };

// Preview stream functions
const getPlaybackUrl = (stream) => {
    if (!stream) return '';

    // If stream has a custom output URL, use that
    if (stream.stream_output && stream.stream_output !== stream.stream_source) {
        return stream.stream_output;
    }

    // For HTTP/HTTPS URLs (HLS .m3u8 or direct MP4), use as-is
    if (stream.stream_source.startsWith('http://') || stream.stream_source.startsWith('https://')) {
        return stream.stream_source;
    }

    // For local streams, construct the playback URL
    // Assuming streams are served from the streaming port (8000) in HLS format
    return `http://127.0.0.1:8000/live/${stream.id}/index.m3u8`;
};

const previewStream = (stream) => {
    previewStreamData.value = stream;
    videoError.value = null;
    showPreviewModal.value = true;

    // Set up error handling for video element
    setTimeout(() => {
        if (videoPlayer.value) {
            videoPlayer.value.addEventListener('error', () => {
                videoError.value = 'The stream format may not be supported by your browser, or the stream is not accessible.';
            });
        }
    }, 100);
};

const closePreview = () => {
    showPreviewModal.value = false;

    // Stop video playback and clean up
    if (videoPlayer.value) {
        videoPlayer.value.pause();
        videoPlayer.value.src = '';
        videoPlayer.value.load();
    }

    previewStreamData.value = null;
    videoError.value = null;
};

// Fetch categories
const fetchCategories = async () => {
    try {
        const response = await categoriesAPI.getAll();
        categories.value = response.data.data || [];
    } catch (error) {
        console.error('Failed to load categories:', error);
    }
};

// Create new stream
const createStream = async () => {
    try {
        await streamsAPI.create(newStream.value);
        showMessage('Stream created successfully', 'success');
        showAddModal.value = false;
        newStream.value = { name: '', stream_source: '', cat_id: 0 };
        fetchStreams();
    } catch (error) {
        showMessage('Failed to create stream: ' + (error.response?.data?.message || error.message), 'error');
    }
};

// Handle wizard import completion
const handleImported = (count) => {
    showMessage(`Successfully imported ${count} stream(s)`, 'success');
    fetchStreams();
    fetchCategories(); // Refresh categories in case new ones were created
};

onMounted(() => {
    fetchStreams();
    fetchCategories();
});
</script>
