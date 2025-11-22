<template>
    <AppLayout>
        <div class="px-4 py-6 sm:px-0">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Transcode Profiles</h1>
                    <p class="mt-2 text-sm text-gray-600">Manage FFmpeg transcode profiles for stream processing</p>
                </div>
                <button @click="showCreateModal = true" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Profile
                </button>
            </div>

            <div class="bg-white shadow rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input v-model="searchQuery" @input="debouncedSearch" type="text" placeholder="Search profiles..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
                        <select v-model="perPage" @change="fetchTranscodes" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>

            <div v-if="message" class="mb-6">
                <div :class="['rounded-md p-4', message.type === 'success' ? 'bg-green-50' : 'bg-red-50']">
                    <p :class="['text-sm', message.type === 'success' ? 'text-green-800' : 'text-red-800']">{{ message.text }}</p>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div v-if="loading" class="p-12 text-center">
                    <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-gray-600">Loading profiles...</p>
                </div>
                <table v-else class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profile Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Video</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Audio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resolution</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="profile in transcodes" :key="profile.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ profile.name }}</div>
                                        <div class="text-xs text-gray-500">{{ profile.preset_values }} preset</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <div>{{ profile.video_codec }}</div>
                                <div class="text-xs text-gray-500">{{ profile.video_bitrate }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <div>{{ profile.audio_codec }}</div>
                                <div class="text-xs text-gray-500">{{ profile.audio_bitrate }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <div>{{ profile.scale }}</div>
                                <div class="text-xs text-gray-500">{{ profile.aspect_ratio }} • {{ profile.fps }}fps</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ formatDate(profile.created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <button @click="editProfile(profile)" class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                <button @click="confirmDelete(profile)" class="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="transcodes.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">No transcode profiles found</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="pagination.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }} results
                        </div>
                        <div class="flex space-x-2">
                            <button @click="currentPage--; fetchTranscodes()" :disabled="currentPage === 1" class="px-3 py-1 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed">Previous</button>
                            <button @click="currentPage++; fetchTranscodes()" :disabled="currentPage === pagination.last_page" class="px-3 py-1 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showCreateModal || showEditModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 overflow-y-auto">
            <div class="bg-white rounded-lg p-6 max-w-4xl w-full m-4 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-medium mb-4">{{ showEditModal ? 'Edit Transcode Profile' : 'Create Transcode Profile' }}</h3>
                <form @submit.prevent="showEditModal ? updateProfile() : createProfile()">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Profile Name -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Profile Name *</label>
                            <input v-model="form.name" type="text" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., 1080p High Quality" />
                        </div>

                        <!-- Video Settings -->
                        <div class="md:col-span-2 mt-4">
                            <h4 class="text-md font-semibold text-gray-800 mb-2">Video Settings</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Video Codec</label>
                            <select v-model="form.video_codec" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="libx264">H.264 (libx264)</option>
                                <option value="libx265">H.265 (libx265)</option>
                                <option value="copy">Copy (No Transcode)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Profile</label>
                            <select v-model="form.profile" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="baseline">Baseline</option>
                                <option value="main">Main</option>
                                <option value="high">High</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Preset</label>
                            <select v-model="form.preset_values" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="ultrafast">Ultra Fast</option>
                                <option value="superfast">Super Fast</option>
                                <option value="veryfast">Very Fast</option>
                                <option value="faster">Faster</option>
                                <option value="fast">Fast</option>
                                <option value="medium">Medium</option>
                                <option value="slow">Slow</option>
                                <option value="slower">Slower</option>
                                <option value="veryslow">Very Slow</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Video Bitrate</label>
                            <input v-model="form.video_bitrate" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., 2500k" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Scale (Resolution)</label>
                            <input v-model="form.scale" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., 1920:1080" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Aspect Ratio</label>
                            <input v-model="form.aspect_ratio" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., 16:9" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">FPS (Frames Per Second)</label>
                            <input v-model="form.fps" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., 25" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">CRF (Quality)</label>
                            <input v-model="form.crf" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="18-28 (lower = better)" />
                        </div>

                        <!-- Audio Settings -->
                        <div class="md:col-span-2 mt-4">
                            <h4 class="text-md font-semibold text-gray-800 mb-2">Audio Settings</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Audio Codec</label>
                            <select v-model="form.audio_codec" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="aac">AAC</option>
                                <option value="mp3">MP3</option>
                                <option value="copy">Copy (No Transcode)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Audio Bitrate</label>
                            <input v-model="form.audio_bitrate" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., 128k" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Audio Channels</label>
                            <select v-model="form.audio_channel" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="1">Mono (1)</option>
                                <option value="2">Stereo (2)</option>
                                <option value="6">5.1 Surround (6)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Audio Sampling Rate</label>
                            <input v-model="form.audio_sampling_rate" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., 44100" />
                        </div>

                        <!-- Advanced Settings -->
                        <div class="md:col-span-2 mt-4">
                            <h4 class="text-md font-semibold text-gray-800 mb-2">Advanced Settings</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Min Rate</label>
                            <input v-model="form.minrate" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., 2000k" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Max Rate</label>
                            <input v-model="form.maxrate" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., 3000k" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Buffer Size</label>
                            <input v-model="form.bufsize" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., 5000k" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Threads</label>
                            <input v-model="form.threads" type="number" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., 4" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Probe Size</label>
                            <input v-model="form.probesize" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., 5000000" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Analyze Duration</label>
                            <input v-model="form.analyzeduration" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., 5000000" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="flex items-center">
                                <input v-model="form.deinterlance" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" />
                                <span class="ml-2 text-sm text-gray-900">Enable Deinterlacing</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="closeModal" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">{{ showEditModal ? 'Update' : 'Create' }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium mb-4">Confirm Delete</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Are you sure you want to delete the transcode profile "{{ profileToDelete?.name }}"? This action cannot be undone.
                </p>
                <div class="flex justify-end space-x-3">
                    <button @click="showDeleteModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button @click="deleteProfile" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { transcodesAPI } from '../../services/api';

const transcodes = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const perPage = ref(20);
const currentPage = ref(1);
const pagination = ref({ total: 0, per_page: 20, current_page: 1, last_page: 1 });
const message = ref(null);

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const profileToDelete = ref(null);
const form = ref({
    id: null,
    name: '',
    video_codec: 'libx264',
    audio_codec: 'aac',
    profile: 'main',
    preset_values: 'veryfast',
    scale: '1920:1080',
    aspect_ratio: '16:9',
    video_bitrate: '2500k',
    audio_bitrate: '128k',
    audio_channel: '2',
    fps: '25',
    probesize: '5000000',
    analyzeduration: '5000000',
    minrate: '2000k',
    maxrate: '3000k',
    bufsize: '5000k',
    audio_sampling_rate: '44100',
    crf: '23',
    threads: '4',
    deinterlance: false
});

const fetchTranscodes = async () => {
    loading.value = true;
    try {
        const params = { page: currentPage.value, per_page: perPage.value };
        if (searchQuery.value) params.search = searchQuery.value;
        const response = await transcodesAPI.getAll(params);
        transcodes.value = response.data.data;
        pagination.value = response.data.pagination;
    } catch (error) {
        showMessage('Error loading transcode profiles: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        loading.value = false;
    }
};

let searchTimeout;
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        fetchTranscodes();
    }, 500);
};

const createProfile = async () => {
    try {
        await transcodesAPI.create({
            ...form.value,
            deinterlance: form.value.deinterlance ? 1 : 0
        });
        showMessage('Transcode profile created successfully');
        closeModal();
        fetchTranscodes();
    } catch (error) {
        showMessage('Error creating profile: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const editProfile = (profile) => {
    form.value = {
        id: profile.id,
        name: profile.name,
        video_codec: profile.video_codec,
        audio_codec: profile.audio_codec,
        profile: profile.profile,
        preset_values: profile.preset_values,
        scale: profile.scale,
        aspect_ratio: profile.aspect_ratio,
        video_bitrate: profile.video_bitrate,
        audio_bitrate: profile.audio_bitrate,
        audio_channel: profile.audio_channel,
        fps: profile.fps,
        probesize: profile.probesize,
        analyzeduration: profile.analyzeduration,
        minrate: profile.minrate,
        maxrate: profile.maxrate,
        bufsize: profile.bufsize,
        audio_sampling_rate: profile.audio_sampling_rate,
        crf: profile.crf,
        threads: profile.threads,
        deinterlance: profile.deinterlance === 1
    };
    showEditModal.value = true;
};

const updateProfile = async () => {
    try {
        await transcodesAPI.update(form.value.id, {
            ...form.value,
            deinterlance: form.value.deinterlance ? 1 : 0
        });
        showMessage('Transcode profile updated successfully');
        closeModal();
        fetchTranscodes();
    } catch (error) {
        showMessage('Error updating profile: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const confirmDelete = (profile) => {
    profileToDelete.value = profile;
    showDeleteModal.value = true;
};

const deleteProfile = async () => {
    try {
        await transcodesAPI.delete(profileToDelete.value.id);
        showMessage('Transcode profile deleted successfully');
        showDeleteModal.value = false;
        profileToDelete.value = null;
        fetchTranscodes();
    } catch (error) {
        showMessage('Error deleting profile: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const closeModal = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    form.value = {
        id: null,
        name: '',
        video_codec: 'libx264',
        audio_codec: 'aac',
        profile: 'main',
        preset_values: 'veryfast',
        scale: '1920:1080',
        aspect_ratio: '16:9',
        video_bitrate: '2500k',
        audio_bitrate: '128k',
        audio_channel: '2',
        fps: '25',
        probesize: '5000000',
        analyzeduration: '5000000',
        minrate: '2000k',
        maxrate: '3000k',
        bufsize: '5000k',
        audio_sampling_rate: '44100',
        crf: '23',
        threads: '4',
        deinterlance: false
    };
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const showMessage = (text, type = 'success') => {
    message.value = { text, type };
    setTimeout(() => message.value = null, 5000);
};

onMounted(() => fetchTranscodes());
</script>
