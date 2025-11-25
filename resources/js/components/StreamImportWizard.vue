<template>
    <div v-if="show" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col border-2 border-gray-200">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Import Streams</h2>
                    <p class="text-sm text-gray-500 mt-1">Step {{ currentStep }} of 3: {{ stepTitles[currentStep - 1] }}</p>
                </div>
                <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Progress Bar -->
            <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                <div class="flex items-center">
                    <div v-for="step in 3" :key="step" class="flex-1 flex items-center">
                        <div class="flex items-center w-full">
                            <div :class="[
                                'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
                                step < currentStep ? 'bg-green-500 text-white' : step === currentStep ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500'
                            ]">
                                <svg v-if="step < currentStep" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span v-else>{{ step }}</span>
                            </div>
                            <div v-if="step < 3" :class="[
                                'flex-1 h-1 mx-2',
                                step < currentStep ? 'bg-green-500' : 'bg-gray-200'
                            ]"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto px-6 py-6">
                <!-- Step 1: Playlist Source -->
                <div v-show="currentStep === 1" class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Choose Import Method</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <button @click="importMethod = 'url'" :class="[
                                'p-6 border-2 rounded-lg text-left transition-all',
                                importMethod === 'url' ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'
                            ]">
                                <svg class="w-10 h-10 mb-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                <h4 class="font-semibold text-gray-900">From URL</h4>
                                <p class="text-sm text-gray-500 mt-1">Import from M3U playlist URL</p>
                            </button>
                            <button @click="importMethod = 'paste'" :class="[
                                'p-6 border-2 rounded-lg text-left transition-all',
                                importMethod === 'paste' ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'
                            ]">
                                <svg class="w-10 h-10 mb-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h4 class="font-semibold text-gray-900">Paste Content</h4>
                                <p class="text-sm text-gray-500 mt-1">Paste M3U playlist content</p>
                            </button>
                        </div>
                    </div>

                    <div v-if="importMethod === 'url'" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Playlist URL</label>
                            <input
                                v-model="playlistUrl"
                                type="url"
                                placeholder="https://example.com/playlist.m3u"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                            <p class="text-xs text-gray-500 mt-2">Enter the URL to your M3U playlist file</p>
                        </div>
                        <button
                            @click="fetchPlaylist"
                            :disabled="!playlistUrl || loading"
                            class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center"
                        >
                            <svg v-if="loading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ loading ? 'Fetching...' : 'Fetch Playlist' }}
                        </button>
                    </div>

                    <div v-else-if="importMethod === 'paste'" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Playlist Content</label>
                            <textarea
                                v-model="playlistContent"
                                rows="15"
                                placeholder="#EXTM3U&#10;#EXTINF:-1 tvg-logo=&quot;...&quot; group-title=&quot;News&quot;,Channel Name&#10;http://example.com/stream.m3u8"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg font-mono text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            ></textarea>
                            <p class="text-xs text-gray-500 mt-2">Paste your M3U playlist content here</p>
                        </div>
                        <button
                            @click="parsePlaylist"
                            :disabled="!playlistContent || loading"
                            class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
                        >
                            Parse Playlist
                        </button>
                    </div>

                    <!-- Parsing Results -->
                    <div v-if="parsedStreams.length > 0" class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-600 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <div class="flex-1">
                                <h4 class="font-semibold text-green-900">Playlist Parsed Successfully</h4>
                                <p class="text-sm text-green-700 mt-1">
                                    Found {{ parsedStreams.length }} streams
                                    ({{ liveChannelCount }} live channels, {{ vodCount }} VOD - will be skipped)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Server Selection -->
                <div v-show="currentStep === 2" class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Select Server</h3>
                        <p class="text-sm text-gray-600 mb-6">Choose the server where streams will be processed</p>

                        <div class="space-y-3">
                            <div
                                v-for="server in servers"
                                :key="server.id"
                                @click="selectedServer = server.id"
                                :class="[
                                    'p-4 border-2 rounded-lg cursor-pointer transition-all',
                                    selectedServer === server.id ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'
                                ]"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div :class="[
                                            'w-4 h-4 rounded-full border-2 mr-3',
                                            selectedServer === server.id ? 'border-indigo-600 bg-indigo-600' : 'border-gray-300'
                                        ]">
                                            <div v-if="selectedServer === server.id" class="w-full h-full rounded-full bg-white scale-50"></div>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">{{ server.name }}</h4>
                                            <p class="text-sm text-gray-500">{{ server.description }}</p>
                                        </div>
                                    </div>
                                    <span :class="[
                                        'px-3 py-1 rounded-full text-xs font-medium',
                                        server.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                    ]">
                                        {{ server.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Category Mapping -->
                <div v-show="currentStep === 3" class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Organize Streams</h3>
                        <p class="text-sm text-gray-600 mb-6">Map playlist groups to categories and filter streams</p>

                        <!-- Category Mapping Options -->
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <h4 class="font-medium text-gray-900 mb-3">Import Mode</h4>
                            <div class="space-y-2">
                                <label class="flex items-start cursor-pointer">
                                    <input type="radio" v-model="categoryMode" value="auto" class="mt-1 mr-3" />
                                    <div>
                                        <span class="font-medium text-gray-900">Auto-map by group</span>
                                        <p class="text-sm text-gray-500">Create categories based on playlist groups ({{ uniqueGroups.length }} groups found)</p>
                                    </div>
                                </label>
                                <label class="flex items-start cursor-pointer">
                                    <input type="radio" v-model="categoryMode" value="single" class="mt-1 mr-3" />
                                    <div>
                                        <span class="font-medium text-gray-900">Import to single category</span>
                                        <p class="text-sm text-gray-500">Put all streams in one category</p>
                                    </div>
                                </label>
                                <label class="flex items-start cursor-pointer">
                                    <input type="radio" v-model="categoryMode" value="manual" class="mt-1 mr-3" />
                                    <div>
                                        <span class="font-medium text-gray-900">Manual mapping</span>
                                        <p class="text-sm text-gray-500">Map each group to specific category</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Single Category Selection -->
                        <div v-if="categoryMode === 'single'" class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Category</label>
                            <select v-model="singleCategory" class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                                <option value="">Choose a category</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>

                        <!-- Manual Mapping -->
                        <div v-if="categoryMode === 'manual'" class="mb-6 space-y-3">
                            <h4 class="font-medium text-gray-900 mb-3">Group to Category Mapping</h4>
                            <div v-for="group in uniqueGroups" :key="group" class="flex items-center space-x-3">
                                <div class="flex-1 font-medium text-gray-700">{{ group || 'Uncategorized' }}</div>
                                <div class="flex-1">
                                    <select v-model="groupCategoryMap[group]" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                        <option value="">Skip this group</option>
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                </div>
                                <div class="text-sm text-gray-500">{{ getGroupStreamCount(group) }} streams</div>
                            </div>
                        </div>

                        <!-- Stream Preview -->
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h4 class="font-medium text-gray-900">Stream Preview ({{ filteredStreams.length }} streams to import)</h4>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Group</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="stream in filteredStreams.slice(0, 50)" :key="stream.url" class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-sm text-gray-900">{{ stream.name }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ stream.group || '-' }}</td>
                                            <td class="px-4 py-3">
                                                <span :class="[
                                                    'px-2 py-1 text-xs rounded-full',
                                                    stream.type === 'live' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'
                                                ]">
                                                    {{ stream.type }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ getCategoryName(stream.categoryId) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-if="filteredStreams.length > 50" class="px-4 py-3 bg-gray-50 text-sm text-gray-500 text-center">
                                    Showing first 50 of {{ filteredStreams.length }} streams
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Messages -->
                <div v-if="error" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex">
                        <svg class="w-5 h-5 text-red-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-sm text-red-700">{{ error }}</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-between">
                <button
                    v-if="currentStep > 1"
                    @click="previousStep"
                    class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100"
                >
                    Back
                </button>
                <div v-else></div>

                <div class="flex space-x-3">
                    <button
                        @click="closeModal"
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100"
                    >
                        Cancel
                    </button>
                    <button
                        v-if="currentStep < 3"
                        @click="nextStep"
                        :disabled="!canProceed"
                        class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
                    >
                        Next
                    </button>
                    <button
                        v-else
                        @click="finishImport"
                        :disabled="importing || filteredStreams.length === 0"
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center"
                    >
                        <svg v-if="importing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ importing ? 'Importing...' : 'Finish & Import' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { streamsAPI, categoriesAPI } from '../services/api';
import { useToastStore } from '../stores/toast';
import {
    categorizeStream,
    detectCountry,
    generateCategoryName,
    autoMapStreams,
    cleanCategoryName
} from '../utils/channelCategorizer';

const toast = useToastStore();

const props = defineProps({
    show: Boolean,
    categories: Array
});

const emit = defineEmits(['close', 'imported']);

const stepTitles = ['Select Playlist Source', 'Choose Server', 'Organize & Filter'];
const currentStep = ref(1);
const importMethod = ref('url');
const playlistUrl = ref('');
const playlistContent = ref('');
const loading = ref(false);
const importing = ref(false);
const error = ref('');
const parsedStreams = ref([]);
const selectedServer = ref(1); // Default to main server
const categoryMode = ref('auto');
const singleCategory = ref('');
const groupCategoryMap = ref({});

// Server list (can be fetched from API later)
const servers = ref([
    {
        id: 1,
        name: 'Main Server',
        description: 'Primary streaming server (current)',
        status: 'active'
    }
]);

const categories = computed(() => props.categories || []);

const uniqueGroups = computed(() => {
    const groups = [...new Set(parsedStreams.value.map(s => s.group || ''))];
    return groups.sort();
});

const liveChannelCount = computed(() =>
    parsedStreams.value.filter(s => s.type === 'live').length
);

const vodCount = computed(() =>
    parsedStreams.value.filter(s => s.type === 'vod').length
);

const filteredStreams = computed(() => {
    // Only live channels
    let streams = parsedStreams.value.filter(s => s.type === 'live');

    // Apply category mapping
    return streams.map(stream => {
        let categoryId = null;
        let categoryName = null;

        if (categoryMode.value === 'single') {
            categoryId = singleCategory.value;
        } else if (categoryMode.value === 'manual') {
            categoryId = groupCategoryMap.value[stream.group || ''] || null;
        } else if (categoryMode.value === 'auto') {
            // Use intelligent auto-mapped category
            categoryName = stream.suggestedCategory || stream.detectedContentType || 'General';
            categoryId = cleanCategoryName(categoryName);
        }

        return {
            ...stream,
            categoryId,
            categoryName: categoryName || categoryId
        };
    }).filter(s => s.categoryId !== null && s.categoryId !== '');
});

const canProceed = computed(() => {
    if (currentStep.value === 1) {
        return parsedStreams.value.length > 0;
    }
    if (currentStep.value === 2) {
        return selectedServer.value !== null;
    }
    if (currentStep.value === 3) {
        if (categoryMode.value === 'single') {
            return singleCategory.value !== '';
        }
        return filteredStreams.value.length > 0;
    }
    return false;
});

// Parse M3U content - extracts all available attributes from M3U/M3U_Plus format
const parseM3UContent = (content) => {
    const lines = content.split('\n');
    const streams = [];
    let currentStream = null;

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();

        if (!line) continue;

        // Parse #EXTINF line
        if (line.startsWith('#EXTINF:')) {
            currentStream = {
                name: '',
                url: '',
                logo: '',
                group: '',
                tvg_id: '',
                xui_id: '',
                timeshift: null,
                type: 'live' // default
            };

            // Extract all M3U_Plus attributes
            const logoMatch = line.match(/tvg-logo="([^"]*)"/);
            if (logoMatch) currentStream.logo = logoMatch[1];

            const groupMatch = line.match(/group-title="([^"]*)"/);
            if (groupMatch) currentStream.group = groupMatch[1];

            // Extract tvg-id (channel identifier)
            const tvgIdMatch = line.match(/tvg-id="([^"]*)"/);
            if (tvgIdMatch) currentStream.tvg_id = tvgIdMatch[1];

            // Extract tvg-name (can be different from display name)
            const tvgNameMatch = line.match(/tvg-name="([^"]*)"/);
            if (tvgNameMatch) currentStream.tvg_name = tvgNameMatch[1];

            // Extract xui-id (external provider ID)
            const xuiIdMatch = line.match(/xui-id="([^"]*)"/);
            if (xuiIdMatch) currentStream.xui_id = xuiIdMatch[1];

            // Extract timeshift (catchup/rewind capability in days)
            const timeshiftMatch = line.match(/timeshift="([^"]*)"/);
            if (timeshiftMatch) currentStream.timeshift = parseInt(timeshiftMatch[1], 10) || null;

            // Extract catchup-days (alternative timeshift attribute)
            const catchupDaysMatch = line.match(/catchup-days="([^"]*)"/);
            if (catchupDaysMatch && !currentStream.timeshift) {
                currentStream.timeshift = parseInt(catchupDaysMatch[1], 10) || null;
            }

            // Extract tvg-chno (channel number)
            const tvgChnoMatch = line.match(/tvg-chno="([^"]*)"/);
            if (tvgChnoMatch) currentStream.tvg_chno = tvgChnoMatch[1];

            // Extract name (after last comma)
            const nameMatch = line.match(/,(.+)$/);
            if (nameMatch) currentStream.name = nameMatch[1].trim();

            // Detect VOD content (movies/series)
            const lowerLine = line.toLowerCase();
            const lowerName = currentStream.name.toLowerCase();
            if (
                lowerLine.includes('movie') ||
                lowerLine.includes('series') ||
                lowerName.includes('s0') && lowerName.includes('e0') || // S01E01 pattern
                currentStream.group.toLowerCase().includes('movie') ||
                currentStream.group.toLowerCase().includes('series') ||
                currentStream.group.toLowerCase().includes('film')
            ) {
                currentStream.type = 'vod';
            }
        }
        // Parse URL line
        else if ((line.startsWith('http') || line.startsWith('rtmp') || line.startsWith('rtsp')) && currentStream) {
            currentStream.url = line;

            // Additional VOD detection by URL
            if (line.includes('/movie/') || line.includes('/series/') || line.match(/\.(mp4|mkv|avi)$/i)) {
                currentStream.type = 'vod';
            }

            streams.push(currentStream);
            currentStream = null;
        }
    }

    return streams;
};

const fetchPlaylist = async () => {
    loading.value = true;
    error.value = '';

    try {
        // Call API to fetch playlist from URL
        const response = await streamsAPI.fetchM3U(playlistUrl.value);
        playlistContent.value = response.data.content;
        parsePlaylist();
        toast.success('Playlist fetched successfully', { title: 'Fetched' });
    } catch (err) {
        error.value = 'Failed to fetch playlist: ' + (err.response?.data?.message || err.message);
        toast.error('Failed to fetch playlist', { title: 'Error', details: err.response?.data?.message || err.message });
    } finally {
        loading.value = false;
    }
};

const parsePlaylist = () => {
    loading.value = true;
    error.value = '';

    try {
        const rawStreams = parseM3UContent(playlistContent.value);

        if (rawStreams.length === 0) {
            error.value = 'No valid streams found in playlist';
            toast.warning('No valid streams found in the playlist', { title: 'Empty Playlist' });
            loading.value = false;
            return;
        }

        // Apply intelligent auto-mapping
        const mappingResult = autoMapStreams(rawStreams);

        // Enhance streams with detection results
        parsedStreams.value = mappingResult.streams.map(stream => ({
            ...stream,
            detectedCountry: stream.detectedCountry,
            detectedContentType: stream.detectedContentType,
            suggestedCategory: stream.suggestedCategory
        }));

        console.log('Auto-mapping results:', {
            totalStreams: parsedStreams.value.length,
            categories: mappingResult.categories,
            categoryMap: mappingResult.categoryMap
        });

        // Show success toast only for paste method (fetch method has its own toast)
        if (importMethod.value === 'paste') {
            toast.success(`Found ${parsedStreams.value.length} streams`, { title: 'Playlist Parsed' });
        }
    } catch (err) {
        error.value = 'Failed to parse playlist: ' + err.message;
        toast.error('Failed to parse playlist', { title: 'Parse Error', details: err.message });
    } finally {
        loading.value = false;
    }
};

const nextStep = () => {
    if (canProceed.value && currentStep.value < 3) {
        currentStep.value++;
    }
};

const previousStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const getGroupStreamCount = (group) => {
    return parsedStreams.value.filter(s => (s.group || '') === group && s.type === 'live').length;
};

const getCategoryName = (categoryId) => {
    if (categoryMode.value === 'auto') {
        return categoryId;
    }
    const cat = categories.value.find(c => c.id == categoryId);
    return cat ? cat.name : '-';
};

const finishImport = async () => {
    importing.value = true;
    error.value = '';

    try {
        let importCount = 0;

        // Auto-create categories if needed
        const categoryIdMap = {};

        if (categoryMode.value === 'auto') {
            // Get unique intelligent category names
            const uniqueCategories = [...new Set(
                filteredStreams.value.map(s => s.categoryName).filter(Boolean)
            )];

            for (const categoryName of uniqueCategories) {
                // Check if category exists (case-insensitive)
                let category = categories.value.find(c =>
                    c.name.toLowerCase() === categoryName.toLowerCase()
                );

                if (!category) {
                    // Create new category
                    const cleanedName = cleanCategoryName(categoryName);
                    const response = await categoriesAPI.create({ name: cleanedName });
                    categoryIdMap[categoryName] = response.data.data.id;
                } else {
                    categoryIdMap[categoryName] = category.id;
                }
            }
        }

        // Import streams and collect their IDs
        const importedStreamIds = [];

        for (const stream of filteredStreams.value) {
            let catId = stream.categoryId;

            if (categoryMode.value === 'auto') {
                catId = categoryIdMap[stream.categoryName] || 0;
            } else if (categoryMode.value === 'single') {
                catId = singleCategory.value;
            } else if (categoryMode.value === 'manual') {
                // catId already set from groupCategoryMap
            }

            const response = await streamsAPI.create({
                name: stream.name,
                stream_source: stream.url,
                cat_id: parseInt(catId) || 0,
                // Include all M3U_Plus fields
                logo: stream.logo || '',
                tvid: stream.tvg_id || '',
                xui_id: stream.xui_id || '',
                timeshift: stream.timeshift || null
            });

            // Collect stream ID for batch analysis
            if (response.data.success && response.data.data?.id) {
                importedStreamIds.push(response.data.data.id);
            }

            importCount++;
        }

        // Trigger batch analysis for imported streams in the background
        if (importedStreamIds.length > 0) {
            // Don't wait for analysis to complete - run in background
            streamsAPI.analyzeBatch(importedStreamIds).catch(err => {
                console.error('Background analysis failed:', err);
                // Silent fail - analysis can be retried later
            });
        }

        toast.success(`Successfully imported ${importCount} streams`, { title: 'Import Complete' });
        emit('imported', importCount);
        closeModal();
    } catch (err) {
        error.value = 'Failed to import streams: ' + (err.response?.data?.message || err.message);
        toast.error('Failed to import streams', { title: 'Import Error', details: err.response?.data?.message || err.message });
    } finally {
        importing.value = false;
    }
};

const closeModal = () => {
    // Reset state
    currentStep.value = 1;
    importMethod.value = 'url';
    playlistUrl.value = '';
    playlistContent.value = '';
    parsedStreams.value = [];
    error.value = '';
    categoryMode.value = 'auto';
    singleCategory.value = '';
    groupCategoryMap.value = {};

    emit('close');
};

// Initialize group category map when groups change
watch(uniqueGroups, (newGroups) => {
    const newMap = {};
    newGroups.forEach(group => {
        newMap[group] = groupCategoryMap.value[group] || '';
    });
    groupCategoryMap.value = newMap;
});
</script>
