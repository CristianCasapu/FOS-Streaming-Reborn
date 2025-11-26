<template>
    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
        <!-- Header -->
        <nav class="bg-gray-900/50 backdrop-blur-lg border-b border-gray-700/50 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center space-x-3">
                        <router-link to="/" class="flex items-center space-x-3 hover:opacity-80 transition-opacity">
                            <div class="h-10 w-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center shadow-lg">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                            </div>
                            <span class="text-white font-medium">Back to Channels</span>
                        </router-link>
                    </div>
                    <div v-if="stream" class="text-right">
                        <h1 class="text-lg font-bold text-white">{{ stream.name }}</h1>
                        <p class="text-xs text-gray-400">{{ stream.category || 'Uncategorized' }}</p>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Loading State -->
            <div v-if="loading" class="flex justify-center items-center py-32">
                <div class="text-center">
                    <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-indigo-500 mx-auto"></div>
                    <p class="mt-4 text-gray-400">Loading stream...</p>
                </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="text-center py-20">
                <div class="bg-red-900/20 border border-red-500 rounded-lg p-8 max-w-lg mx-auto">
                    <svg class="mx-auto h-12 w-12 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <h3 class="text-xl font-bold text-red-300 mb-2">Access Denied</h3>
                    <p class="text-red-400">{{ error }}</p>
                    <router-link to="/" class="inline-block mt-6 px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
                        Return to Channels
                    </router-link>
                </div>
            </div>

            <!-- Stream Player -->
            <div v-else-if="stream" class="space-y-6">
                <!-- Video Player Container -->
                <div class="bg-black rounded-xl overflow-hidden shadow-2xl">
                    <div class="aspect-video relative">
                        <!-- Loading Secure URL -->
                        <div v-if="loadingSecureUrl" class="absolute inset-0 flex items-center justify-center bg-gray-900">
                            <div class="text-center">
                                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-indigo-500 mx-auto"></div>
                                <p class="mt-4 text-gray-400">Securing stream access...</p>
                            </div>
                        </div>

                        <!-- Stream Offline -->
                        <div v-else-if="!stream.is_running" class="absolute inset-0 flex items-center justify-center bg-gray-900">
                            <div class="text-center">
                                <svg class="mx-auto h-16 w-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                <p class="mt-4 text-xl text-gray-400">Stream is currently offline</p>
                                <p class="text-sm text-gray-500 mt-2">Please check back later</p>
                            </div>
                        </div>

                        <!-- Video Player (HLS.js) -->
                        <div v-else-if="secureUrl" class="w-full h-full">
                            <video
                                ref="videoPlayer"
                                class="w-full h-full"
                                controls
                                autoplay
                                playsinline
                            >
                                Your browser does not support the video tag.
                            </video>
                        </div>

                        <!-- No Secure URL -->
                        <div v-else class="absolute inset-0 flex items-center justify-center bg-gray-900">
                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <p class="mt-4 text-gray-400">Generating secure access token...</p>
                                <button @click="generateSecureUrl" class="mt-4 px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                                    Generate Secure URL
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stream Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Stream Details -->
                    <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <svg class="h-5 w-5 mr-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Stream Information
                        </h3>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-400">Channel Name</dt>
                                <dd class="text-white font-medium">{{ stream.name }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-400">Category</dt>
                                <dd class="text-white">{{ stream.category || 'Uncategorized' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-400">Status</dt>
                                <dd :class="stream.is_running ? 'text-green-400' : 'text-red-400'">
                                    {{ stream.is_running ? 'Live' : 'Offline' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Security Info -->
                    <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <svg class="h-5 w-5 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Security
                        </h3>
                        <div v-if="secureUrlData" class="space-y-3 text-sm">
                            <div class="flex items-center text-green-400">
                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Token-authenticated stream
                            </div>
                            <div class="flex justify-between text-gray-400">
                                <span>Token Expires</span>
                                <span class="text-white">{{ formatDateTime(secureUrlData.expires_at) }}</span>
                            </div>
                            <button
                                @click="generateSecureUrl"
                                class="w-full mt-4 px-4 py-2 bg-green-600/20 border border-green-500 text-green-400 rounded-lg hover:bg-green-600/30 transition-colors text-sm"
                            >
                                Refresh Token
                            </button>
                        </div>
                        <div v-else class="text-gray-400 text-sm">
                            <p>Secure access token will be generated automatically.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const streamId = route.params.id;

const loading = ref(true);
const error = ref(null);
const stream = ref(null);
const loadingSecureUrl = ref(false);
const secureUrl = ref(null);
const secureUrlData = ref(null);
const videoPlayer = ref(null);

let hlsInstance = null;

const fetchStream = async () => {
    loading.value = true;
    error.value = null;

    try {
        // First get stream list to verify access
        const response = await fetch('/subscriber/api/streams.php?action=list');
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load streams');
        }

        // Find the specific stream
        const foundStream = data.data.find(s => s.id == streamId);
        if (!foundStream) {
            throw new Error('You do not have access to this stream');
        }

        stream.value = foundStream;

        // Auto-generate secure URL if stream is running
        if (stream.value.is_running) {
            await generateSecureUrl();
        }
    } catch (err) {
        console.error('Error fetching stream:', err);
        error.value = err.message || 'Failed to load stream';
    } finally {
        loading.value = false;
    }
};

const generateSecureUrl = async () => {
    loadingSecureUrl.value = true;

    try {
        const response = await fetch(`/subscriber/api/streams.php?action=get_secure_url&stream_id=${streamId}&format=hls`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to generate secure URL');
        }

        secureUrlData.value = data.data;
        secureUrl.value = data.data.url;

        // Initialize video player after URL is ready
        await nextTick();
        initializePlayer();
    } catch (err) {
        console.error('Error generating secure URL:', err);
        error.value = err.message || 'Failed to generate secure URL';
    } finally {
        loadingSecureUrl.value = false;
    }
};

const initializePlayer = async () => {
    if (!secureUrl.value || !videoPlayer.value) return;

    // Destroy existing HLS instance if any
    if (hlsInstance) {
        hlsInstance.destroy();
        hlsInstance = null;
    }

    const video = videoPlayer.value;

    // Dynamically import HLS.js
    try {
        const { default: Hls } = await import('hls.js');

        if (Hls.isSupported()) {
            hlsInstance = new Hls({
                enableWorker: true,
                lowLatencyMode: true,
                maxBufferLength: 30,
                maxMaxBufferLength: 60,
            });

            hlsInstance.loadSource(secureUrl.value);
            hlsInstance.attachMedia(video);

            hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
                video.play().catch(e => console.log('Autoplay prevented:', e));
            });

            hlsInstance.on(Hls.Events.ERROR, (event, data) => {
                if (data.fatal) {
                    switch (data.type) {
                        case Hls.ErrorTypes.NETWORK_ERROR:
                            console.log('Network error, attempting recovery...');
                            hlsInstance.startLoad();
                            break;
                        case Hls.ErrorTypes.MEDIA_ERROR:
                            console.log('Media error, attempting recovery...');
                            hlsInstance.recoverMediaError();
                            break;
                        default:
                            console.error('Fatal error:', data);
                            error.value = 'Stream playback error. Please try refreshing the page.';
                            hlsInstance.destroy();
                            break;
                    }
                }
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            // Native HLS support (Safari)
            video.src = secureUrl.value;
            video.addEventListener('loadedmetadata', () => {
                video.play().catch(e => console.log('Autoplay prevented:', e));
            });
        } else {
            error.value = 'Your browser does not support HLS playback.';
        }
    } catch (err) {
        // HLS.js not available, try native playback
        console.warn('HLS.js not available, trying native playback');
        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = secureUrl.value;
            video.addEventListener('loadedmetadata', () => {
                video.play().catch(e => console.log('Autoplay prevented:', e));
            });
        } else {
            error.value = 'HLS playback not supported. Please use a compatible browser.';
        }
    }
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Watch for route changes
watch(() => route.params.id, (newId) => {
    if (newId) {
        fetchStream();
    }
});

onMounted(() => {
    fetchStream();
});

onUnmounted(() => {
    if (hlsInstance) {
        hlsInstance.destroy();
        hlsInstance = null;
    }
});
</script>
