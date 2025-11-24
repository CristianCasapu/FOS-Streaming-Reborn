<template>
    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Loading State -->
                <div v-if="loading" class="flex items-center justify-center min-h-screen">
                    <div class="text-center">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                        <p class="mt-4 text-gray-600">Loading stream details...</p>
                    </div>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <div class="flex items-center">
                        <svg class="h-6 w-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <h3 class="text-lg font-medium text-red-900">Error Loading Stream</h3>
                            <p class="text-sm text-red-700 mt-1">{{ error }}</p>
                        </div>
                    </div>
                    <button @click="router.back()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Go Back
                    </button>
                </div>

                <!-- Main Content -->
                <div v-else-if="stream">
                    <!-- Header -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <button @click="router.back()" class="p-2 hover:bg-gray-100 rounded-lg">
                                    <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                </button>
                                <div>
                                    <h1 class="text-3xl font-bold text-gray-900">{{ stream.name }}</h1>
                                    <p class="text-sm text-gray-600 mt-1">Stream ID: {{ stream.id }}</p>
                                </div>
                            </div>
                            <div class="flex space-x-3">
                                <button v-if="stream.status !== 1" @click="startStream" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Start Stream
                                </button>
                                <button v-else @click="stopStream" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700">
                                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                    </svg>
                                    Stop Stream
                                </button>
                                <button @click="showEditModal = true" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Edit
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div class="mb-6">
                        <span :class="['inline-flex items-center px-3 py-1 rounded-full text-sm font-medium', stream.status === 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
                            <span class="w-2 h-2 rounded-full mr-2" :class="stream.status === 1 ? 'bg-green-600 animate-pulse' : 'bg-gray-600'"></span>
                            {{ stream.status === 1 ? 'RUNNING' : 'STOPPED' }}
                        </span>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Category</p>
                                    <p class="text-lg font-bold text-gray-900">{{ stream.category || 'Uncategorized' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Status</p>
                                    <p class="text-lg font-bold text-gray-900">{{ stream.status === 1 ? 'Online' : 'Offline' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-purple-100 rounded-md p-3">
                                    <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Codec</p>
                                    <p class="text-lg font-bold text-gray-900">{{ technicalInfo?.video_codec || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Bitrate</p>
                                    <p class="text-lg font-bold text-gray-900">{{ formatBitrate(technicalInfo?.bitrate) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="mb-6">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <button @click="activeTab = 'overview'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'overview' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                    Overview
                                </button>
                                <button @click="activeTab = 'technical'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'technical' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                    Technical Details
                                </button>
                                <button @click="activeTab = 'access'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'access' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                    Access URLs
                                </button>
                            </nav>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div>
                        <!-- Overview Tab -->
                        <div v-show="activeTab === 'overview'" class="space-y-6">
                            <!-- Stream Information -->
                            <div class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Stream Information</h3>
                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Stream Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ stream.name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Stream ID</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ stream.id }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Category</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ stream.category || 'Uncategorized' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                        <dd class="mt-1">
                                            <span :class="['px-2 py-1 text-xs rounded-full', stream.status === 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
                                                {{ stream.status === 1 ? 'Running' : 'Stopped' }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div class="col-span-2">
                                        <dt class="text-sm font-medium text-gray-500">Source URL</dt>
                                        <dd class="mt-1 text-sm text-gray-900 break-all">{{ stream.stream_source }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Created At</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(stream.created_at) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(stream.updated_at) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Quick Actions -->
                            <div class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <button @click="analyzeStream" :disabled="analyzing" class="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition">
                                        <svg class="h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        <span class="text-sm font-medium text-gray-900">{{ analyzing ? 'Analyzing...' : 'Analyze Stream' }}</span>
                                    </button>

                                    <button @click="checkAccessibility" :disabled="checkingAccess" class="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition">
                                        <svg class="h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                        <span class="text-sm font-medium text-gray-900">{{ checkingAccess ? 'Checking...' : 'Check Access' }}</span>
                                    </button>

                                    <button @click="restartStream" class="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition">
                                        <svg class="h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        <span class="text-sm font-medium text-gray-900">Restart Stream</span>
                                    </button>

                                    <button @click="refreshTechnical" class="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition">
                                        <svg class="h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        <span class="text-sm font-medium text-gray-900">Refresh Info</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Technical Details Tab -->
                        <div v-show="activeTab === 'technical'">
                            <div class="bg-white shadow rounded-lg p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-medium text-gray-900">Technical Analysis</h3>
                                    <button @click="getTechnicalInfo" :disabled="loadingTechnical" class="px-4 py-2 text-sm text-indigo-600 hover:text-indigo-900">
                                        {{ loadingTechnical ? 'Loading...' : 'Refresh' }}
                                    </button>
                                </div>

                                <div v-if="loadingTechnical" class="text-center py-12">
                                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                                    <p class="mt-2 text-sm text-gray-500">Analyzing stream...</p>
                                </div>

                                <div v-else-if="technicalInfo" class="space-y-6">
                                    <!-- Video Information -->
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Video Information</h4>
                                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Codec</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.video_codec || 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Resolution</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.resolution || 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Bitrate</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ formatBitrate(technicalInfo.bitrate) }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Frame Rate</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.fps || 'N/A' }} fps</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <!-- Audio Information -->
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Audio Information</h4>
                                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Codec</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.audio_codec || 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Sample Rate</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.sample_rate || 'N/A' }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <!-- Raw Data -->
                                    <div v-if="technicalInfo.raw_data">
                                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Raw FFprobe Data</h4>
                                        <pre class="bg-gray-50 p-4 rounded-lg text-xs overflow-x-auto">{{ JSON.stringify(technicalInfo.raw_data, null, 2) }}</pre>
                                    </div>
                                </div>

                                <div v-else class="text-center py-12">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No technical data available</h3>
                                    <p class="mt-1 text-sm text-gray-500">Click "Refresh" to analyze the stream.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Access URLs Tab -->
                        <div v-show="activeTab === 'access'">
                            <div class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Stream Access URLs</h3>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">HLS Playlist URL</label>
                                        <div class="flex">
                                            <input type="text" readonly :value="getStreamUrl('hls')" class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md bg-gray-50 text-sm" />
                                            <button @click="copyToClipboard(getStreamUrl('hls'))" class="px-4 py-2 bg-indigo-600 text-white rounded-r-md hover:bg-indigo-700">
                                                Copy
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">RTMP URL</label>
                                        <div class="flex">
                                            <input type="text" readonly :value="getStreamUrl('rtmp')" class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md bg-gray-50 text-sm" />
                                            <button @click="copyToClipboard(getStreamUrl('rtmp'))" class="px-4 py-2 bg-indigo-600 text-white rounded-r-md hover:bg-indigo-700">
                                                Copy
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Direct Stream URL</label>
                                        <div class="flex">
                                            <input type="text" readonly :value="getStreamUrl('direct')" class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md bg-gray-50 text-sm" />
                                            <button @click="copyToClipboard(getStreamUrl('direct'))" class="px-4 py-2 bg-indigo-600 text-white rounded-r-md hover:bg-indigo-700">
                                                Copy
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Stream Modal -->
        <div v-if="showEditModal && stream" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full">
                <h3 class="text-lg font-medium mb-4">Edit Stream</h3>
                <form @submit.prevent="updateStream">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stream Name</label>
                            <input v-model="editForm.name" type="text" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stream Source URL</label>
                            <input v-model="editForm.stream_source" type="text" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Category</label>
                            <input v-model="editForm.category" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" />
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '../../components/AppLayout.vue';
import { streamsAPI } from '../../services/api';

const route = useRoute();
const router = useRouter();
const streamId = route.params.id;

const stream = ref(null);
const technicalInfo = ref(null);
const loading = ref(true);
const loadingTechnical = ref(false);
const analyzing = ref(false);
const checkingAccess = ref(false);
const error = ref(null);

const activeTab = ref('overview');
const showEditModal = ref(false);

const editForm = ref({
    name: '',
    stream_source: '',
    category: ''
});

const fetchStream = async () => {
    loading.value = true;
    error.value = null;
    try {
        const response = await streamsAPI.getOne(streamId);
        if (response.data.success) {
            stream.value = response.data.data;
            editForm.value = {
                name: stream.value.name,
                stream_source: stream.value.stream_source,
                category: stream.value.category || ''
            };
        } else {
            error.value = response.data.message || 'Failed to load stream';
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load stream details';
        console.error('Error fetching stream:', err);
    } finally {
        loading.value = false;
    }
};

const getTechnicalInfo = async () => {
    loadingTechnical.value = true;
    try {
        const response = await streamsAPI.getTechnicalInfo(streamId);
        if (response.data.success) {
            technicalInfo.value = response.data.data;
        }
    } catch (err) {
        console.error('Error fetching technical info:', err);
        alert('Failed to load technical information');
    } finally {
        loadingTechnical.value = false;
    }
};

const analyzeStream = async () => {
    analyzing.value = true;
    try {
        const response = await streamsAPI.analyze(streamId);
        if (response.data.success) {
            await getTechnicalInfo();
            alert('Stream analyzed successfully');
        }
    } catch (err) {
        console.error('Error analyzing stream:', err);
        alert(err.response?.data?.message || 'Failed to analyze stream');
    } finally {
        analyzing.value = false;
    }
};

const checkAccessibility = async () => {
    checkingAccess.value = true;
    try {
        const response = await streamsAPI.checkAccessibility(streamId);
        if (response.data.success) {
            const result = response.data.data;
            alert(`Stream is ${result.accessible ? 'accessible' : 'not accessible'}${result.message ? ': ' + result.message : ''}`);
        }
    } catch (err) {
        console.error('Error checking accessibility:', err);
        alert('Failed to check stream accessibility');
    } finally {
        checkingAccess.value = false;
    }
};

const startStream = async () => {
    try {
        const response = await streamsAPI.start(streamId);
        if (response.data.success) {
            await fetchStream();
            alert('Stream started successfully');
        }
    } catch (err) {
        console.error('Error starting stream:', err);
        alert(err.response?.data?.message || 'Failed to start stream');
    }
};

const stopStream = async () => {
    try {
        const response = await streamsAPI.stop(streamId);
        if (response.data.success) {
            await fetchStream();
            alert('Stream stopped successfully');
        }
    } catch (err) {
        console.error('Error stopping stream:', err);
        alert(err.response?.data?.message || 'Failed to stop stream');
    }
};

const restartStream = async () => {
    if (!confirm('Are you sure you want to restart this stream?')) return;

    try {
        await stopStream();
        setTimeout(async () => {
            await startStream();
        }, 2000);
    } catch (err) {
        console.error('Error restarting stream:', err);
    }
};

const updateStream = async () => {
    try {
        const response = await streamsAPI.update(streamId, editForm.value);
        if (response.data.success) {
            showEditModal.value = false;
            await fetchStream();
            alert('Stream updated successfully');
        }
    } catch (err) {
        console.error('Error updating stream:', err);
        alert(err.response?.data?.message || 'Failed to update stream');
    }
};

const refreshTechnical = async () => {
    await fetchStream();
    await getTechnicalInfo();
};

const getStreamUrl = (type) => {
    const baseUrl = window.location.origin;
    switch (type) {
        case 'hls':
            return `${baseUrl}:8000/live/${stream.value.id}/index.m3u8`;
        case 'rtmp':
            return `rtmp://${window.location.hostname}:1935/live/${stream.value.id}`;
        case 'direct':
            return `${baseUrl}:8000/live/${stream.value.id}`;
        default:
            return '';
    }
};

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        alert('Copied to clipboard!');
    } catch (err) {
        console.error('Failed to copy:', err);
        alert('Failed to copy to clipboard');
    }
};

const formatBitrate = (bitrate) => {
    if (!bitrate) return 'N/A';
    const kbps = parseInt(bitrate) / 1000;
    if (kbps > 1000) {
        return `${(kbps / 1000).toFixed(2)} Mbps`;
    }
    return `${kbps.toFixed(0)} Kbps`;
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

onMounted(async () => {
    await fetchStream();
    await getTechnicalInfo();
});
</script>
