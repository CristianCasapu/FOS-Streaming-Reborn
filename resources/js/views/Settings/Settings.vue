<template>
    <AppLayout>
        <div class="px-4 py-6 sm:px-0">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">System Settings</h1>
                <p class="mt-2 text-sm text-gray-600">Configure streaming server and system parameters</p>
            </div>

            <!-- System Information Card -->
            <div v-if="systemInfo" class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg shadow-lg p-6 mb-6 text-white">
                <h2 class="text-xl font-semibold mb-4">System Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm opacity-90">PHP Version</p>
                        <p class="text-lg font-semibold">{{ systemInfo.php_version }}</p>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Server Software</p>
                        <p class="text-lg font-semibold">{{ systemInfo.server_software }}</p>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Current Port</p>
                        <p class="text-lg font-semibold">{{ systemInfo.current_port }}</p>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Server IP</p>
                        <p class="text-lg font-semibold">{{ systemInfo.server_ip }}</p>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Server Name</p>
                        <p class="text-lg font-semibold">{{ systemInfo.server_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">HLS Folder Status</p>
                        <p class="text-lg font-semibold">
                            <span v-if="systemInfo.hls_folder_exists && systemInfo.hls_folder_writable" class="text-green-300">✓ OK</span>
                            <span v-else class="text-red-300">✗ Error</span>
                        </p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="saveSettings" class="bg-white shadow rounded-lg overflow-hidden pb-24">
                <div v-if="loading" class="p-12 text-center">
                    <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-gray-600">Loading settings...</p>
                </div>

                <div v-else class="p-6 space-y-6">
                    <!-- FFmpeg Configuration Section -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="h-6 w-6 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                            </svg>
                            FFmpeg Configuration
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- FFmpeg Path -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center justify-between">
                                    <span>FFmpeg Path *</span>
                                    <button
                                        v-if="!ffmpegEdit"
                                        type="button"
                                        @click="ffmpegEdit = true"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 flex items-center"
                                    >
                                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                        Edit
                                    </button>
                                </label>
                                <div class="flex space-x-2">
                                    <input
                                        v-model="form.ffmpeg_path"
                                        type="text"
                                        :readonly="!ffmpegEdit"
                                        required
                                        data-field="ffmpeg_path"
                                        id="ffmpeg_path"
                                        class="flex-1 px-3 py-2 border rounded-md font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100 transition-colors"
                                        :class="[
                                            !ffmpegEdit ? 'bg-gray-50' : '',
                                            validationErrors.ffmpeg_path ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-300'
                                        ]"
                                        placeholder="/usr/bin/ffmpeg"
                                    />
                                    <button
                                        v-if="!ffmpegEdit"
                                        type="button"
                                        @click="detectFFmpeg"
                                        class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 text-sm whitespace-nowrap"
                                    >
                                        Detect
                                    </button>
                                    <button
                                        v-if="!ffmpegEdit && ffmpegNotFound"
                                        type="button"
                                        @click="installFFmpeg"
                                        :disabled="installingFFmpeg"
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm disabled:opacity-50 whitespace-nowrap"
                                    >
                                        {{ installingFFmpeg ? 'Installing...' : 'Install' }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="testFFmpeg"
                                        :disabled="testingFFmpeg"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm disabled:opacity-50"
                                    >
                                        {{ testingFFmpeg ? 'Testing...' : 'Test' }}
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Path to the FFmpeg binary</p>
                            </div>

                            <!-- FFprobe Path -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center justify-between">
                                    <span>FFprobe Path *</span>
                                    <button
                                        v-if="!ffprobeEdit"
                                        type="button"
                                        @click="ffprobeEdit = true"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 flex items-center"
                                    >
                                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                        Edit
                                    </button>
                                </label>
                                <div class="flex space-x-2">
                                    <input
                                        v-model="form.ffprobe_path"
                                        type="text"
                                        :readonly="!ffprobeEdit"
                                        required
                                        data-field="ffprobe_path"
                                        id="ffprobe_path"
                                        class="flex-1 px-3 py-2 border rounded-md font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                        :class="[
                                            !ffprobeEdit ? 'bg-gray-50' : '',
                                            validationErrors.ffprobe_path ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-300'
                                        ]"
                                        placeholder="/usr/bin/ffprobe"
                                    />
                                    <button
                                        v-if="!ffprobeEdit"
                                        type="button"
                                        @click="detectFFprobe"
                                        class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 text-sm whitespace-nowrap"
                                    >
                                        Detect
                                    </button>
                                    <button
                                        v-if="!ffprobeEdit && ffprobeNotFound"
                                        type="button"
                                        @click="installFFprobe"
                                        :disabled="installingFFprobe"
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm disabled:opacity-50 whitespace-nowrap"
                                    >
                                        {{ installingFFprobe ? 'Installing...' : 'Install' }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="testFFprobe"
                                        :disabled="testingFFprobe"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm disabled:opacity-50"
                                    >
                                        {{ testingFFprobe ? 'Testing...' : 'Test' }}
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Path to the FFprobe binary</p>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200" />

                    <!-- Server Configuration Section -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="h-6 w-6 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                            </svg>
                            Server Configuration
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Web IP/Domain *
                                </label>
                                <input
                                    v-model="form.webip"
                                    type="text"
                                    required
                                    data-field="webip"
                                    id="webip"
                                    class="w-full px-3 py-2 border rounded-md focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                    :class="validationErrors.webip ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-300'"
                                    placeholder="example.com"
                                />
                                <p class="mt-1 text-xs text-gray-500">Without trailing slash (e.g., example.com NOT example.com/)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Web Port *
                                </label>
                                <div class="flex space-x-2">
                                    <input
                                        v-model.number="form.webport"
                                        type="number"
                                        required
                                        data-field="webport"
                                        id="webport"
                                        class="flex-1 px-3 py-2 border rounded-md focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                        :class="validationErrors.webport ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-300'"
                                        placeholder="8000"
                                    />
                                    <button
                                        v-if="portChanged"
                                        type="button"
                                        @click="restartNginx"
                                        :disabled="restartingNginx"
                                        class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 text-sm disabled:opacity-50 whitespace-nowrap"
                                    >
                                        {{ restartingNginx ? 'Restarting...' : 'Restart Nginx' }}
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Port for the web interface</p>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200" />

                    <!-- Streaming Configuration Section -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="h-6 w-6 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            Streaming Configuration
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    HLS Folder (Legacy) *
                                </label>
                                <input
                                    v-model="form.hlsfolder"
                                    type="text"
                                    required
                                    data-field="hlsfolder"
                                    id="hlsfolder"
                                    class="w-full px-3 py-2 border rounded-md font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                    :class="validationErrors.hlsfolder ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-300'"
                                    placeholder="/var/www/hls"
                                />
                                <p class="mt-1 text-xs text-gray-500">Legacy directory for HLS stream segments</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    User Agent *
                                </label>
                                <input
                                    v-model="form.user_agent"
                                    type="text"
                                    required
                                    data-field="user_agent"
                                    id="user_agent"
                                    class="w-full px-3 py-2 border rounded-md font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                    :class="validationErrors.user_agent ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-300'"
                                    placeholder="Mozilla/5.0..."
                                />
                                <p class="mt-1 text-xs text-gray-500">User agent for HTTP requests</p>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200" />

                    <!-- Streaming Protocol Configuration Section -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="h-6 w-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Streaming Protocol (MPEG-DASH / HLS)
                        </h3>

                        <!-- System Services Status -->
                        <div class="mb-6 space-y-3">
                            <!-- Nginx Streaming Service -->
                            <div class="p-3 rounded-lg border" :class="services.nginxStreaming.running ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 rounded-full mr-3" :class="services.nginxStreaming.running ? 'bg-green-500 animate-pulse' : 'bg-gray-400'"></div>
                                        <div>
                                            <p class="font-medium text-sm" :class="services.nginxStreaming.running ? 'text-green-800' : 'text-gray-700'">
                                                Nginx Streaming Service
                                            </p>
                                            <p class="text-xs" :class="services.nginxStreaming.running ? 'text-green-600' : 'text-gray-500'">
                                                <span v-if="services.nginxStreaming.running">Running | PID: {{ services.nginxStreaming.pid }} | RTMP: {{ form.rtmp_port }} | HTTP: {{ form.streaming_port }}</span>
                                                <span v-else>Stopped | RTMP: {{ form.rtmp_port }} | HTTP: {{ form.streaming_port }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button
                                            type="button"
                                            @click="testNginxConfig"
                                            :disabled="testingNginxConfig"
                                            class="px-2.5 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 disabled:opacity-50"
                                        >
                                            {{ testingNginxConfig ? 'Testing...' : 'Test' }}
                                        </button>
                                        <button
                                            v-if="!services.nginxStreaming.running"
                                            type="button"
                                            @click="startService('nginxStreaming')"
                                            :disabled="services.nginxStreaming.starting"
                                            class="px-2.5 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 disabled:opacity-50"
                                        >
                                            {{ services.nginxStreaming.starting ? 'Starting...' : 'Start' }}
                                        </button>
                                        <button
                                            v-if="services.nginxStreaming.running"
                                            type="button"
                                            @click="restartService('nginxStreaming')"
                                            :disabled="services.nginxStreaming.restarting"
                                            class="px-2.5 py-1 bg-yellow-600 text-white text-xs rounded hover:bg-yellow-700 disabled:opacity-50"
                                        >
                                            {{ services.nginxStreaming.restarting ? 'Restarting...' : 'Restart' }}
                                        </button>
                                        <button
                                            v-if="services.nginxStreaming.running"
                                            type="button"
                                            @click="stopService('nginxStreaming')"
                                            :disabled="services.nginxStreaming.stopping"
                                            class="px-2.5 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700 disabled:opacity-50"
                                        >
                                            {{ services.nginxStreaming.stopping ? 'Stopping...' : 'Stop' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- PHP-FPM Streaming Service -->
                            <div class="p-3 rounded-lg border" :class="services.phpFpmStreaming.running ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 rounded-full mr-3" :class="services.phpFpmStreaming.running ? 'bg-green-500 animate-pulse' : 'bg-gray-400'"></div>
                                        <div>
                                            <p class="font-medium text-sm" :class="services.phpFpmStreaming.running ? 'text-green-800' : 'text-gray-700'">
                                                PHP-FPM Streaming Service
                                            </p>
                                            <p class="text-xs" :class="services.phpFpmStreaming.running ? 'text-green-600' : 'text-gray-500'">
                                                <span v-if="services.phpFpmStreaming.running">Running | PID: {{ services.phpFpmStreaming.pid }}</span>
                                                <span v-else>Stopped</span>
                                                <span v-if="services.phpFpmStreaming.socket" class="ml-1">| Socket: {{ services.phpFpmStreaming.socket.split('/').pop() }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button
                                            v-if="!services.phpFpmStreaming.running"
                                            type="button"
                                            @click="startService('phpFpmStreaming')"
                                            :disabled="services.phpFpmStreaming.starting"
                                            class="px-2.5 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 disabled:opacity-50"
                                        >
                                            {{ services.phpFpmStreaming.starting ? 'Starting...' : 'Start' }}
                                        </button>
                                        <button
                                            v-if="services.phpFpmStreaming.running"
                                            type="button"
                                            @click="restartService('phpFpmStreaming')"
                                            :disabled="services.phpFpmStreaming.restarting"
                                            class="px-2.5 py-1 bg-yellow-600 text-white text-xs rounded hover:bg-yellow-700 disabled:opacity-50"
                                        >
                                            {{ services.phpFpmStreaming.restarting ? 'Restarting...' : 'Restart' }}
                                        </button>
                                        <button
                                            v-if="services.phpFpmStreaming.running"
                                            type="button"
                                            @click="stopService('phpFpmStreaming')"
                                            :disabled="services.phpFpmStreaming.stopping"
                                            class="px-2.5 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700 disabled:opacity-50"
                                        >
                                            {{ services.phpFpmStreaming.stopping ? 'Stopping...' : 'Stop' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Refresh Button -->
                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    @click="loadServicesStatus"
                                    :disabled="loadingServices"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 flex items-center"
                                >
                                    <svg v-if="loadingServices" class="animate-spin mr-1 h-3 w-3" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    {{ loadingServices ? 'Refreshing...' : 'Refresh Status' }}
                                </button>
                            </div>
                        </div>

                        <!-- Protocol Selection -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Streaming Protocol *</label>
                                <select
                                    v-model="form.streaming_protocol"
                                    data-field="streaming_protocol"
                                    id="streaming_protocol"
                                    class="w-full px-3 py-2 border rounded-md focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                    :class="validationErrors.streaming_protocol ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-300'"
                                >
                                    <option value="both">Both DASH + HLS (Recommended)</option>
                                    <option value="dash">MPEG-DASH Only</option>
                                    <option value="hls">HLS Only (Apple)</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">DASH for modern browsers, HLS for Safari</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Streams Storage Path *</label>
                                <input
                                    v-model="form.streams_path"
                                    type="text"
                                    required
                                    readonly
                                    data-field="streams_path"
                                    id="streams_path"
                                    class="w-full px-3 py-2 border rounded-md font-mono text-sm bg-gray-50 cursor-not-allowed transition-colors"
                                    :class="validationErrors.streams_path ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-300'"
                                    placeholder="/tmp/streams"
                                />
                                <p class="mt-1 text-xs text-gray-500">Directory for DASH/HLS segments</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">RTMP Ingest Port *</label>
                                <input
                                    v-model.number="form.rtmp_port"
                                    type="number"
                                    required
                                    data-field="rtmp_port"
                                    id="rtmp_port"
                                    class="w-full px-3 py-2 border rounded-md focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                    :class="validationErrors.rtmp_port ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-300'"
                                    placeholder="1935"
                                />
                                <p class="mt-1 text-xs text-gray-500">FFmpeg RTMP push (default: 1935)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">HTTP Streaming Port *</label>
                                <input
                                    v-model.number="form.streaming_port"
                                    type="number"
                                    required
                                    data-field="streaming_port"
                                    id="streaming_port"
                                    class="w-full px-3 py-2 border rounded-md focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                    :class="validationErrors.streaming_port ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-300'"
                                    placeholder="8080"
                                />
                                <p class="mt-1 text-xs text-gray-500">DASH/HLS HTTP delivery (default: 8080)</p>
                            </div>
                        </div>

                        <!-- DASH Settings -->
                        <div v-if="form.streaming_protocol === 'dash' || form.streaming_protocol === 'both'" class="mb-6">
                            <h4 class="text-md font-medium text-gray-800 mb-3 flex items-center">
                                <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2.5 py-0.5 rounded mr-2">DASH</span>
                                MPEG-DASH Settings
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-purple-50 rounded-lg border border-purple-200">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Fragment (seconds)</label>
                                    <input v-model.number="form.dash_fragment" type="number" min="1" max="30" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-purple-500 focus:border-purple-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Playlist Length (seconds)</label>
                                    <input v-model.number="form.dash_playlist_length" type="number" min="5" max="300" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-purple-500 focus:border-purple-500" />
                                </div>
                                <div>
                                    <label class="flex items-center h-full pt-5">
                                        <input v-model="form.dash_nested" type="checkbox" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded" />
                                        <span class="ml-2 text-sm text-gray-700">Nested Directories</span>
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center h-full pt-5">
                                        <input v-model="form.dash_cleanup" type="checkbox" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded" />
                                        <span class="ml-2 text-sm text-gray-700">Auto Cleanup</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- HLS Settings -->
                        <div v-if="form.streaming_protocol === 'hls' || form.streaming_protocol === 'both'" class="mb-4">
                            <h4 class="text-md font-medium text-gray-800 mb-3 flex items-center">
                                <span class="bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded mr-2">HLS</span>
                                HLS Settings (Apple)
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-orange-50 rounded-lg border border-orange-200">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Fragment (seconds)</label>
                                    <input v-model.number="form.hls_fragment" type="number" min="1" max="30" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Playlist Length (seconds)</label>
                                    <input v-model.number="form.hls_playlist_length" type="number" min="5" max="300" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500" />
                                </div>
                                <div>
                                    <label class="flex items-center h-full pt-5">
                                        <input v-model="form.hls_nested" type="checkbox" class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded" />
                                        <span class="ml-2 text-sm text-gray-700">Nested Directories</span>
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center h-full pt-5">
                                        <input v-model="form.hls_cleanup" type="checkbox" class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded" />
                                        <span class="ml-2 text-sm text-gray-700">Auto Cleanup</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Info Box -->
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <div class="flex">
                                <svg class="h-5 w-5 text-blue-400 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <div class="text-sm text-blue-800">
                                    <p class="font-medium">How Streaming Works</p>
                                    <p class="mt-1">FFmpeg pushes to <code class="bg-blue-100 px-1 rounded">rtmp://localhost:{{ form.rtmp_port }}/live/{stream_id}</code> → Nginx converts to DASH/HLS → Served at <code class="bg-blue-100 px-1 rounded">http://server:8000/dash/{stream_id}/index.mpd</code></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200" />

                    <!-- Path Auto-Detection Section -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="h-6 w-6 text-cyan-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            Path Auto-Detection
                        </h3>

                        <div class="bg-cyan-50 border border-cyan-200 rounded-md p-4 mb-4">
                            <div class="flex">
                                <svg class="h-5 w-5 text-cyan-400 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <div class="text-sm text-cyan-800">
                                    <p class="font-medium">Automatic Path Detection</p>
                                    <p>The system can automatically detect the current user, group, and all required paths for nginx and PHP-FPM configuration. Click "Detect Paths" to scan your system.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Auto-Detect Button -->
                        <div class="mb-4">
                            <button
                                type="button"
                                @click="autoDetectPaths"
                                :disabled="detectingPaths"
                                class="px-4 py-2 bg-cyan-600 text-white rounded-md hover:bg-cyan-700 disabled:opacity-50 flex items-center"
                            >
                                <svg v-if="detectingPaths" class="animate-spin -ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                {{ detectingPaths ? 'Detecting...' : 'Detect Paths' }}
                            </button>
                        </div>

                        <!-- Detected Paths Display -->
                        <div v-if="detectedPaths" class="space-y-4">
                            <!-- System Info -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 uppercase">Detected User</label>
                                    <p class="mt-1 text-sm font-mono text-gray-900">{{ detectedPaths.user }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 uppercase">Detected Group</label>
                                    <p class="mt-1 text-sm font-mono text-gray-900">{{ detectedPaths.group }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 uppercase">Project Root</label>
                                    <p class="mt-1 text-sm font-mono text-gray-900 truncate" :title="detectedPaths.project_root">{{ detectedPaths.project_root }}</p>
                                </div>
                            </div>

                            <!-- Detected Paths -->
                            <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <h4 class="text-sm font-medium text-gray-700 mb-3">Detected Paths</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div v-for="(value, key) in detectedPaths.paths" :key="key" class="flex items-start">
                                        <span class="text-xs font-medium text-gray-500 w-36 flex-shrink-0">{{ formatPathLabel(key) }}:</span>
                                        <span class="text-xs font-mono text-gray-700 truncate" :title="value">{{ value || 'Not found' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Detected Binaries -->
                            <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <h4 class="text-sm font-medium text-gray-700 mb-3">Detected Binaries</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div v-for="(value, key) in detectedPaths.binaries" :key="key" class="flex items-center">
                                        <span class="w-3 h-3 rounded-full mr-2" :class="value ? 'bg-green-500' : 'bg-red-500'"></span>
                                        <span class="text-xs font-medium text-gray-500 w-24">{{ key }}:</span>
                                        <span class="text-xs font-mono text-gray-700 truncate" :title="value">{{ value || 'Not found' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- PHP-FPM Config -->
                            <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <h4 class="text-sm font-medium text-gray-700 mb-3">PHP-FPM Configuration</h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <div>
                                        <span class="text-xs text-gray-500">Binary:</span>
                                        <p class="text-xs font-mono text-gray-700 truncate">{{ detectedPaths.php_fpm?.binary || 'Not found' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Socket:</span>
                                        <p class="text-xs font-mono text-gray-700">{{ detectedPaths.php_fpm?.socket }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Max Children:</span>
                                        <p class="text-xs font-mono text-gray-700">{{ detectedPaths.php_fpm?.pm_max_children }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">PM Mode:</span>
                                        <p class="text-xs font-mono text-gray-700">{{ detectedPaths.php_fpm?.pm }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    @click="applyDetectedPaths"
                                    :disabled="applyingPaths"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 flex items-center"
                                >
                                    <svg v-if="applyingPaths" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ applyingPaths ? 'Applying...' : 'Apply Detected Paths' }}
                                </button>

                                <button
                                    type="button"
                                    @click="generateNginxConfig"
                                    :disabled="generatingConfig"
                                    class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 disabled:opacity-50 flex items-center"
                                >
                                    <svg v-if="generatingConfig" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                    </svg>
                                    {{ generatingConfig ? 'Generating...' : 'Generate Nginx Config' }}
                                </button>

                                <button
                                    type="button"
                                    @click="saveNginxConfig"
                                    :disabled="savingNginxConfig"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 flex items-center"
                                >
                                    <svg v-if="savingNginxConfig" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                    </svg>
                                    {{ savingNginxConfig ? 'Saving...' : 'Save Nginx Config' }}
                                </button>
                            </div>

                            <!-- Generated Config Preview -->
                            <div v-if="generatedConfig" class="mt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-medium text-gray-700">Generated Nginx Configuration</h4>
                                    <button
                                        type="button"
                                        @click="copyConfig"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 flex items-center"
                                    >
                                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        Copy
                                    </button>
                                </div>
                                <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs overflow-x-auto max-h-64 overflow-y-auto font-mono">{{ generatedConfig }}</pre>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200" />

                    <!-- Trial Configuration Section -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="h-6 w-6 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Trial Subscriptions Configuration
                        </h3>
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-4">
                            <div class="flex">
                                <svg class="h-5 w-5 text-blue-400 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <div class="text-sm text-blue-800">
                                    <p class="font-medium">About Trial Subscriptions</p>
                                    <p>Trial subscriptions allow subscribers to test your service for a limited time before purchasing. Each subscriber can have only one trial.</p>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Default Trial Duration (hours) *
                                </label>
                                <input
                                    v-model.number="form.trial_duration_hours"
                                    type="number"
                                    min="1"
                                    max="720"
                                    required
                                    data-field="trial_duration_hours"
                                    id="trial_duration_hours"
                                    class="w-full px-3 py-2 border rounded-md focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                    :class="validationErrors.trial_duration_hours ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-300'"
                                    placeholder="24"
                                />
                                <p class="mt-1 text-xs text-gray-500">Default duration for new trials (1-720 hours, recommended: 24-48)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Max Trials Per Subscriber
                                </label>
                                <input v-model.number="form.max_trials_per_user" type="number" min="1" max="10" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="1" />
                                <p class="mt-1 text-xs text-gray-500">Maximum number of trials a subscriber can have (database enforces unique constraint: 1 trial per subscriber)</p>
                            </div>
                        </div>
                        <div class="mt-4 space-y-3">
                            <label class="flex items-center">
                                <input
                                    v-model="form.trial_enabled"
                                    type="checkbox"
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                />
                                <span class="ml-2 text-sm text-gray-700">Enable Trial Subscriptions</span>
                            </label>
                            <p class="text-xs text-gray-500 ml-6">Allow new trial subscriptions to be created</p>

                            <label class="flex items-center">
                                <input
                                    v-model="form.trial_requires_approval"
                                    type="checkbox"
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                />
                                <span class="ml-2 text-sm text-gray-700">Trial Requires Admin Approval</span>
                            </label>
                            <p class="text-xs text-gray-500 ml-6">Trials must be manually activated by an administrator</p>
                        </div>
                    </div>

                    <hr class="border-gray-200" />

                    <!-- System Commands & Sudo Password Section -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="h-6 w-6 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            System Commands Configuration
                        </h3>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-4">
                            <div class="flex">
                                <svg class="h-5 w-5 text-yellow-400 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <div class="text-sm text-yellow-800">
                                    <p class="font-medium">Security Notice</p>
                                    <p>The sudo password will be encrypted using AES-256 before storage. Enable this feature only if you need to execute system commands from the web interface (package installation, service management, etc.).</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Sudo User
                                </label>
                                <input
                                    v-model="form.sudo_user"
                                    type="text"
                                    :disabled="sudoPasswordSet && !sudoEdit"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100 disabled:text-gray-500"
                                    placeholder="casapu"
                                />
                                <p class="mt-1 text-xs text-gray-500">System user for sudo commands</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center justify-between">
                                    <span>Sudo Password</span>
                                    <button
                                        v-if="sudoPasswordSet && !sudoEdit"
                                        type="button"
                                        @click="enableSudoEdit"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 flex items-center"
                                    >
                                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                        Edit
                                    </button>
                                </label>
                                <div class="flex space-x-2">
                                    <div class="flex-1 relative">
                                        <input
                                            v-model="form.sudo_password"
                                            :type="showSudoPassword ? 'text' : 'password'"
                                            :disabled="sudoPasswordSet && !sudoEdit"
                                            class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100"
                                            :placeholder="sudoPasswordSet && !sudoEdit ? '••••••••' : 'Enter sudo password'"
                                        />
                                        <button
                                            v-if="!sudoPasswordSet || sudoEdit"
                                            type="button"
                                            @click="showSudoPassword = !showSudoPassword"
                                            class="absolute right-2 top-2.5 text-gray-400 hover:text-gray-600"
                                        >
                                            <svg v-if="!showSudoPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            </svg>
                                        </button>
                                    </div>
                                    <button
                                        v-if="!sudoPasswordSet || sudoEdit"
                                        type="button"
                                        @click="testSudoPassword"
                                        :disabled="testingSudo || !form.sudo_password"
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
                                    >
                                        {{ testingSudo ? 'Testing...' : 'Test' }}
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    <span v-if="sudoPasswordSet && !sudoEdit" class="text-green-600 font-medium">✓ Password configured (click Edit to change)</span>
                                    <span v-else>Password will be tested before saving</span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="flex items-center">
                                <input
                                    v-model="form.system_commands_enabled"
                                    type="checkbox"
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                />
                                <span class="ml-2 text-sm text-gray-700">Enable System Commands</span>
                            </label>
                            <p class="mt-1 text-xs text-gray-500 ml-6">Allow executing system commands (package installation, service restart, etc.)</p>
                        </div>

                        <div v-if="form.last_command_at" class="mt-4 text-xs text-gray-500">
                            Last command executed: {{ form.last_command_at }}
                        </div>
                    </div>

                    <hr class="border-gray-200" />

                    <!-- PM2 Process Manager Section -->
                    <div>
                        <PM2Manager />
                    </div>

                    <hr class="border-gray-200" />

                    <!-- Branding Section -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="h-6 w-6 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Branding
                        </h3>

                        <!-- Logo Upload Section -->
                        <div class="space-y-4">
                            <!-- Current Logo Preview -->
                            <div v-if="form.logourl" class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <img :src="form.logourl" alt="Current Logo" class="h-16 w-auto max-w-xs object-contain border border-gray-200 rounded-md p-2 bg-white">
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">Current Logo</p>
                                    <p class="text-xs text-gray-500">{{ form.logourl }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="removeLogo"
                                    class="px-3 py-1 text-sm text-red-600 hover:text-red-800 border border-red-300 rounded-md hover:bg-red-50"
                                >
                                    Remove
                                </button>
                            </div>

                            <!-- Upload Area -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Upload Logo
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-indigo-400 transition-colors"
                                     @dragover.prevent="isDragging = true"
                                     @dragleave.prevent="isDragging = false"
                                     @drop.prevent="handleDrop"
                                     :class="{ 'border-indigo-500 bg-indigo-50': isDragging }">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="logo-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                <span>Upload a file</span>
                                                <input
                                                    id="logo-upload"
                                                    ref="logoInput"
                                                    type="file"
                                                    class="sr-only"
                                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/svg+xml"
                                                    @change="handleFileSelect"
                                                >
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            JPG, PNG, GIF, WebP, SVG up to 2MB
                                        </p>
                                        <p class="text-xs text-indigo-600 font-medium mt-2">
                                            Recommended: 200x50px or 400x100px (landscape)
                                        </p>
                                        <p class="text-xs text-green-600 mt-1">
                                            ✓ Images automatically converted to WebP (except SVG)
                                        </p>
                                    </div>
                                </div>

                                <!-- Upload Progress -->
                                <div v-if="uploadingLogo" class="mt-2">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">Uploading logo...</span>
                                        <span class="text-indigo-600">{{ uploadProgress }}%</span>
                                    </div>
                                    <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300" :style="{ width: uploadProgress + '%' }"></div>
                                    </div>
                                </div>

                                <p class="mt-2 text-xs text-gray-500">
                                    File types: JPEG, PNG, GIF, WebP, SVG | Max size: 2MB | Single file only
                                </p>
                            </div>
                        </div>

                        <!-- Favicon Upload Section -->
                        <div class="space-y-4 mt-8">
                            <h4 class="text-md font-medium text-gray-900">Favicon</h4>

                            <!-- Current Favicon Preview -->
                            <div v-if="form.faviconurl" class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <img :src="form.faviconurl" alt="Current Favicon" class="h-8 w-8 object-contain border border-gray-200 rounded-md p-1 bg-white">
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">Current Favicon</p>
                                    <p class="text-xs text-gray-500">{{ form.faviconurl }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="removeFavicon"
                                    class="px-3 py-1 text-sm text-red-600 hover:text-red-800 border border-red-300 rounded-md hover:bg-red-50"
                                >
                                    Remove
                                </button>
                            </div>

                            <!-- Upload Area -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Upload Favicon
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-indigo-400 transition-colors"
                                     @dragover.prevent="isDraggingFavicon = true"
                                     @dragleave.prevent="isDraggingFavicon = false"
                                     @drop.prevent="handleFaviconDrop"
                                     :class="{ 'border-indigo-500 bg-indigo-50': isDraggingFavicon }">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="favicon-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                <span>Upload a file</span>
                                                <input
                                                    id="favicon-upload"
                                                    ref="faviconInput"
                                                    type="file"
                                                    class="sr-only"
                                                    accept="image/x-icon,image/png,image/jpeg,image/jpg"
                                                    @change="handleFaviconSelect"
                                                >
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            ICO, PNG, JPG up to 1MB
                                        </p>
                                        <p class="text-xs text-indigo-600 font-medium mt-2">
                                            Recommended: 16x16px, 32x32px, or 48x48px
                                        </p>
                                        <p class="text-xs text-green-600 mt-1">
                                            ✓ Images automatically converted to multi-resolution ICO
                                        </p>
                                    </div>
                                </div>

                                <!-- Upload Progress -->
                                <div v-if="uploadingFavicon" class="mt-2">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">Uploading favicon...</span>
                                        <span class="text-indigo-600">{{ faviconUploadProgress }}%</span>
                                    </div>
                                    <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300" :style="{ width: faviconUploadProgress + '%' }"></div>
                                    </div>
                                </div>

                                <p class="mt-2 text-xs text-gray-500">
                                    File types: ICO, PNG, JPG | Max size: 1MB | Single file only
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Refresh System Info Button (inline) -->
                    <div class="flex items-center pt-4">
                        <button type="button" @click="loadSystemInfo" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Refresh System Info
                        </button>
                    </div>
                </div>
            </form>

            <!-- Floating Save Button -->
            <div class="fixed bottom-6 right-6 z-50">
                <button
                    type="button"
                    @click="saveSettings"
                    :disabled="saving"
                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105"
                >
                    <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    {{ saving ? 'Saving...' : 'Save Settings' }}
                </button>
            </div>

            <!-- Important Notes -->
            <div class="mt-6 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Important Notes</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Use <strong>Detect</strong> buttons to automatically find FFmpeg/FFprobe paths</li>
                                <li>Always <strong>Test</strong> new paths before saving to ensure they work</li>
                                <li>Test sudo password before enabling System Commands</li>
                                <li>Changing the <strong>Web Port</strong> requires manual Nginx restart</li>
                                <li>HLS folder must be writable by the web server user (nginx/www-data)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import PM2Manager from '../../components/PM2Manager.vue';
import { settingsAPI, systemCommandsAPI } from '../../services/api';
import { useToastStore } from '../../stores/toast';

const toast = useToastStore();
const loading = ref(false);
const saving = ref(false);
const systemInfo = ref(null);

// Form validation state
const validationErrors = ref({});

// Define mandatory fields with their display names and refs
const mandatoryFields = {
    ffmpeg_path: { label: 'FFmpeg Path', section: 'FFmpeg Configuration' },
    ffprobe_path: { label: 'FFprobe Path', section: 'FFmpeg Configuration' },
    webip: { label: 'Web IP/Domain', section: 'Server Configuration' },
    webport: { label: 'Web Port', section: 'Server Configuration' },
    hlsfolder: { label: 'HLS Folder', section: 'Streaming Configuration' },
    user_agent: { label: 'User Agent', section: 'Streaming Configuration' },
    streaming_protocol: { label: 'Streaming Protocol', section: 'Streaming Protocol' },
    streams_path: { label: 'Streams Storage Path', section: 'Streaming Protocol' },
    rtmp_port: { label: 'RTMP Ingest Port', section: 'Streaming Protocol' },
    trial_duration_hours: { label: 'Trial Duration', section: 'Trial Configuration' },
};

// FFmpeg/FFprobe edit states
const ffmpegEdit = ref(false);
const ffprobeEdit = ref(false);
const testingFFmpeg = ref(false);
const testingFFprobe = ref(false);
const ffmpegNotFound = ref(false);
const ffprobeNotFound = ref(false);
const installingFFmpeg = ref(false);
const installingFFprobe = ref(false);

// Sudo password edit states
const sudoEdit = ref(false);
const sudoPasswordSet = ref(false);
const showSudoPassword = ref(false);
const testingSudo = ref(false);

// Port change tracking
const originalPort = ref(null);
const portChanged = ref(false);
const restartingNginx = ref(false);

// Logo upload states
const uploadingLogo = ref(false);
const uploadProgress = ref(0);
const isDragging = ref(false);
const logoInput = ref(null);

// Favicon upload states
const uploadingFavicon = ref(false);
const faviconUploadProgress = ref(0);
const isDraggingFavicon = ref(false);
const faviconInput = ref(null);

// Nginx streaming status
const nginxStatus = ref(null);
const testingNginxConfig = ref(false);
const startingNginx = ref(false);
const stoppingNginx = ref(false);
const restartingNginxStreaming = ref(false);

// Path auto-detection states
const detectingPaths = ref(false);
const detectedPaths = ref(null);
const applyingPaths = ref(false);
const generatingConfig = ref(false);
const savingNginxConfig = ref(false);
const generatedConfig = ref(null);

// System Services state
const loadingServices = ref(false);
// Platform Services (Streaming Protocol) - Manageable
const services = ref({
    nginxStreaming: {
        enabled: true,
        running: false,
        pid: null,
        binary: null,
        starting: false,
        stopping: false,
        restarting: false
    },
    phpFpmStreaming: {
        enabled: true,
        running: false,
        pid: null,
        socket: null,
        starting: false,
        stopping: false,
        restarting: false
    }
});

// System Services - Read-only status (managed by systemd)
const systemServices = ref({
    nginxAdmin: {
        name: 'Nginx Admin Panel',
        running: false,
        pid: null,
        port: null
    },
    phpFpmAdmin: {
        name: 'PHP-FPM Admin',
        running: false,
        pid: null
    },
    mariadb: {
        name: 'MariaDB Database',
        running: false,
        pid: null
    }
});

const form = ref({
    ffmpeg_path: '',
    ffprobe_path: '',
    webip: '',
    webport: 8000,
    hlsfolder: '',
    logourl: '',
    faviconurl: '',
    user_agent: '',
    sudo_user: '',
    sudo_password: '',
    system_commands_enabled: false,
    last_command_at: null,
    trial_duration_hours: 24,
    trial_enabled: true,
    trial_requires_approval: false,
    max_trials_per_user: 1,
    // Streaming Protocol Settings
    streaming_protocol: 'both',
    streams_path: '/home/casapu/projects/FOS-Streaming-v69/fospackv69/fos/streams',
    rtmp_port: 1935,
    streaming_port: 8000,
    // DASH Settings
    dash_fragment: 4,
    dash_playlist_length: 30,
    dash_nested: true,
    dash_cleanup: true,
    // HLS Settings
    hls_fragment: 3,
    hls_playlist_length: 60,
    hls_nested: true,
    hls_cleanup: true
});

const loadSettings = async () => {
    loading.value = true;
    try {
        const response = await settingsAPI.get();
        const data = response.data.data;

        form.value = {
            ffmpeg_path: data.ffmpeg_path || '',
            ffprobe_path: data.ffprobe_path || '',
            webip: data.webip || '',
            webport: data.webport || 8000,
            hlsfolder: data.hlsfolder || '',
            logourl: data.logourl || '',
            faviconurl: data.faviconurl || '',
            user_agent: data.user_agent || '',
            sudo_user: data.sudo_user || 'casapu',
            sudo_password: '',
            system_commands_enabled: data.system_commands_enabled || false,
            last_command_at: data.last_command_at || null,
            trial_duration_hours: data.trial_duration_hours || 24,
            trial_enabled: data.trial_enabled !== undefined ? data.trial_enabled : true,
            trial_requires_approval: data.trial_requires_approval || false,
            max_trials_per_user: data.max_trials_per_user || 1,
            // Streaming Protocol Settings
            streaming_protocol: data.streaming_protocol || 'both',
            streams_path: data.streams_path || '/home/casapu/projects/FOS-Streaming-v69/fospackv69/fos/streams',
            rtmp_port: data.rtmp_port || 1935,
            streaming_port: data.streaming_port || 8000,
            // DASH Settings
            dash_fragment: data.dash_fragment || 4,
            dash_playlist_length: data.dash_playlist_length || 30,
            dash_nested: data.dash_nested !== undefined ? data.dash_nested : true,
            dash_cleanup: data.dash_cleanup !== undefined ? data.dash_cleanup : true,
            // HLS Settings
            hls_fragment: data.hls_fragment || 3,
            hls_playlist_length: data.hls_playlist_length || 60,
            hls_nested: data.hls_nested !== undefined ? data.hls_nested : true,
            hls_cleanup: data.hls_cleanup !== undefined ? data.hls_cleanup : true
        };

        // Update favicon if set
        if (data.faviconurl) {
            updatePageFavicon(data.faviconurl);
        }

        sudoPasswordSet.value = data.sudo_password_set || false;

        // Store original port for change detection
        originalPort.value = data.webport || 8000;
    } catch (error) {
        showMessage('Error loading settings: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        loading.value = false;
    }
};

// Watch for port changes
watch(() => form.value.webport, (newPort) => {
    portChanged.value = originalPort.value !== null && originalPort.value !== newPort;
});

const loadSystemInfo = async () => {
    try {
        const response = await settingsAPI.getSystemInfo();
        systemInfo.value = response.data.data;
    } catch (error) {
        console.error('Error loading system info:', error);
    }
};

const detectFFmpeg = async () => {
    try {
        const result = await systemCommandsAPI.execute('which ffmpeg', 'Detect FFmpeg path');
        if (result.data.success && result.data.data.output && result.data.data.output.trim()) {
            form.value.ffmpeg_path = result.data.data.output.trim();
            ffmpegNotFound.value = false;
            showMessage('FFmpeg detected at: ' + form.value.ffmpeg_path);
        } else {
            ffmpegNotFound.value = true;
            showMessage('FFmpeg not found in PATH. Click Install to install it.', 'warning');
        }
    } catch (error) {
        ffmpegNotFound.value = true;
        showMessage('Error detecting FFmpeg: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const detectFFprobe = async () => {
    try {
        const result = await systemCommandsAPI.execute('which ffprobe', 'Detect FFprobe path');
        if (result.data.success && result.data.data.output && result.data.data.output.trim()) {
            form.value.ffprobe_path = result.data.data.output.trim();
            ffprobeNotFound.value = false;
            showMessage('FFprobe detected at: ' + form.value.ffprobe_path);
        } else {
            ffprobeNotFound.value = true;
            showMessage('FFprobe not found in PATH. Click Install to install it.', 'warning');
        }
    } catch (error) {
        ffprobeNotFound.value = true;
        showMessage('Error detecting FFprobe: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const installFFmpeg = async () => {
    // Check if sudo password is set
    if (!sudoPasswordSet.value) {
        showMessage('Please configure sudo password first. Click Edit next to Sudo Password, enter password, click Test, then Save.', 'error');
        return;
    }

    installingFFmpeg.value = true;
    try {
        const result = await systemCommandsAPI.installPackage('ffmpeg');
        if (result.data.success) {
            showMessage('FFmpeg installed successfully!');
            ffmpegNotFound.value = false;
            // Auto-detect after installation
            await detectFFmpeg();
        } else {
            const errorMsg = result.data.data?.output || result.data.message || 'Unknown error';
            showMessage('Failed to install FFmpeg: ' + errorMsg, 'error');
        }
    } catch (error) {
        const errorMsg = error.response?.data?.data?.output || error.response?.data?.message || error.message;
        showMessage('Error installing FFmpeg: ' + errorMsg, 'error');
    } finally {
        installingFFmpeg.value = false;
    }
};

const installFFprobe = async () => {
    // Check if sudo password is set
    if (!sudoPasswordSet.value) {
        showMessage('Please configure sudo password first. Click Edit next to Sudo Password, enter password, click Test, then Save.', 'error');
        return;
    }

    installingFFprobe.value = true;
    try {
        // FFprobe comes with FFmpeg package
        const result = await systemCommandsAPI.installPackage('ffmpeg');
        if (result.data.success) {
            showMessage('FFprobe installed successfully (included with FFmpeg)!');
            ffprobeNotFound.value = false;
            // Auto-detect after installation
            await detectFFprobe();
        } else {
            const errorMsg = result.data.data?.output || result.data.message || 'Unknown error';
            showMessage('Failed to install FFprobe: ' + errorMsg, 'error');
        }
    } catch (error) {
        const errorMsg = error.response?.data?.data?.output || error.response?.data?.message || error.message;
        showMessage('Error installing FFprobe: ' + errorMsg, 'error');
    } finally {
        installingFFprobe.value = false;
    }
};

const testFFmpeg = async () => {
    testingFFmpeg.value = true;
    try {
        const response = await settingsAPI.testFFmpeg();
        showMessage('FFmpeg test successful: ' + response.data.data.version);
        ffmpegEdit.value = false;
    } catch (error) {
        showMessage('FFmpeg test failed: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        testingFFmpeg.value = false;
    }
};

const testFFprobe = async () => {
    testingFFprobe.value = true;
    try {
        const response = await settingsAPI.testFFprobe();
        showMessage('FFprobe test successful: ' + response.data.data.version);
        ffprobeEdit.value = false;
    } catch (error) {
        showMessage('FFprobe test failed: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        testingFFprobe.value = false;
    }
};

const enableSudoEdit = () => {
    sudoEdit.value = true;
    form.value.sudo_password = '';
};

const testSudoPassword = async () => {
    testingSudo.value = true;
    try {
        // First update with the test password
        await settingsAPI.update({
            sudo_user: form.value.sudo_user,
            sudo_password: form.value.sudo_password
        });

        // Then test it
        const response = await settingsAPI.testSudo();
        if (response.data.success) {
            showMessage('Sudo password test successful! User: ' + response.data.data.output.trim());
            sudoPasswordSet.value = true;
            sudoEdit.value = false;
            form.value.sudo_password = '';
        } else {
            showMessage('Sudo password test failed. Please check the password.', 'error');
        }
    } catch (error) {
        showMessage('Sudo test failed: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        testingSudo.value = false;
    }
};

const restartNginx = async () => {
    restartingNginx.value = true;
    try {
        const result = await systemCommandsAPI.restartService('nginx');
        if (result.data.success) {
            showMessage('Nginx restarted successfully!');
            originalPort.value = form.value.webport;
            portChanged.value = false;
        } else {
            showMessage('Failed to restart Nginx: ' + (result.data.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        showMessage('Error restarting Nginx: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        restartingNginx.value = false;
    }
};

// Logo upload handlers
const validateLogoFile = (file) => {
    // Check file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    if (!allowedTypes.includes(file.type)) {
        showMessage('Invalid file type. Only JPG, PNG, GIF, WebP, and SVG images are allowed.', 'error');
        return false;
    }

    // Check file size (2MB)
    const maxSize = 2 * 1024 * 1024;
    if (file.size > maxSize) {
        showMessage('File size exceeds 2MB limit.', 'error');
        return false;
    }

    return true;
};

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file && validateLogoFile(file)) {
        uploadLogo(file);
    }
};

const handleDrop = (event) => {
    isDragging.value = false;
    const file = event.dataTransfer.files[0];
    if (file && validateLogoFile(file)) {
        uploadLogo(file);
    }
};

const uploadLogo = async (file) => {
    uploadingLogo.value = true;
    uploadProgress.value = 0;

    try {
        const formData = new FormData();
        formData.append('logo', file);

        const response = await settingsAPI.uploadLogo(formData);

        if (response.data.success) {
            form.value.logourl = response.data.data.logo_url;
            showMessage('Logo uploaded successfully!');
            uploadProgress.value = 100;

            // Clear the input
            if (logoInput.value) {
                logoInput.value.value = '';
            }
        } else {
            showMessage('Failed to upload logo: ' + (response.data.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        const errorMsg = error.response?.data?.message || error.message || 'Unknown error';
        showMessage('Error uploading logo: ' + errorMsg, 'error');
    } finally {
        setTimeout(() => {
            uploadingLogo.value = false;
            uploadProgress.value = 0;
        }, 1000);
    }
};

const removeLogo = () => {
    if (confirm('Are you sure you want to remove the current logo?')) {
        form.value.logourl = '';
        showMessage('Logo removed. Click Save Settings to apply changes.', 'warning');
    }
};

// Favicon upload functions
const validateFaviconFile = (file) => {
    const allowedTypes = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/jpeg', 'image/jpg'];

    if (!allowedTypes.includes(file.type)) {
        showMessage('Invalid file type. Only ICO and PNG images are allowed for favicons.', 'error');
        return false;
    }

    const maxSize = 1 * 1024 * 1024; // 1MB
    if (file.size > maxSize) {
        showMessage('File size exceeds 1MB limit.', 'error');
        return false;
    }

    return true;
};

const uploadFavicon = async (file) => {
    uploadingFavicon.value = true;
    faviconUploadProgress.value = 0;

    try {
        const formData = new FormData();
        formData.append('favicon', file);

        const response = await settingsAPI.uploadFavicon(formData);

        if (response.data.success) {
            form.value.faviconurl = response.data.data.favicon_url;
            showMessage('Favicon uploaded successfully!');
            faviconUploadProgress.value = 100;

            // Clear the input
            if (faviconInput.value) {
                faviconInput.value.value = '';
            }

            // Update favicon in page
            updatePageFavicon(response.data.data.favicon_url);
        } else {
            showMessage('Failed to upload favicon: ' + (response.data.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        const errorMsg = error.response?.data?.message || error.message || 'Unknown error';
        showMessage('Error uploading favicon: ' + errorMsg, 'error');
    } finally {
        setTimeout(() => {
            uploadingFavicon.value = false;
            faviconUploadProgress.value = 0;
        }, 1000);
    }
};

const handleFaviconSelect = (event) => {
    const file = event.target.files[0];
    if (file && validateFaviconFile(file)) {
        uploadFavicon(file);
    }
};

const handleFaviconDrop = (event) => {
    isDraggingFavicon.value = false;
    const file = event.dataTransfer.files[0];
    if (file && validateFaviconFile(file)) {
        uploadFavicon(file);
    }
};

const removeFavicon = () => {
    if (confirm('Are you sure you want to remove the current favicon?')) {
        form.value.faviconurl = '';
        showMessage('Favicon removed. Click Save Settings to apply changes.', 'warning');
        // Revert to default favicon
        updatePageFavicon(null);
    }
};

const updatePageFavicon = (url) => {
    const link = document.querySelector("link[rel~='icon']");
    if (link) {
        link.href = url || '/favicon.ico';
    }
};

const saveSettings = async () => {
    // Validate mandatory fields first
    const errors = validateForm();
    if (errors.length > 0) {
        await scrollToFirstError(errors);
        return;
    }

    // Validate FFmpeg/FFprobe if in edit mode
    if (ffmpegEdit.value) {
        toast.error('Please test FFmpeg path before saving', { title: 'Action Required' });
        return;
    }
    if (ffprobeEdit.value) {
        toast.error('Please test FFprobe path before saving', { title: 'Action Required' });
        return;
    }
    if (sudoEdit.value && form.value.sudo_password) {
        toast.error('Please test sudo password before saving', { title: 'Action Required' });
        return;
    }

    saving.value = true;
    try {
        const response = await settingsAPI.update(form.value);
        const data = response.data;

        toast.success('Settings saved successfully', { title: 'Saved' });

        await loadSettings();
        await loadSystemInfo();

        // Port change flag will be recalculated by watcher
    } catch (error) {
        const errorMsg = error.response?.data?.message || error.message;
        toast.error('Failed to save settings', {
            title: 'Save Error',
            details: errorMsg,
            duration: 8000,
        });
    } finally {
        saving.value = false;
    }
};

const showMessage = (text, type = 'success', details = null) => {
    // Map old type names to toast types
    const typeMap = {
        success: 'success',
        error: 'error',
        warning: 'warning',
        info: 'info',
    };
    const toastType = typeMap[type] || 'info';

    if (details) {
        toast[toastType](text, { details });
    } else {
        toast[toastType](text);
    }
};

// Validate mandatory fields and return first invalid field
const validateForm = () => {
    validationErrors.value = {};
    const errors = [];

    for (const [field, config] of Object.entries(mandatoryFields)) {
        const value = form.value[field];
        if (value === '' || value === null || value === undefined) {
            validationErrors.value[field] = true;
            errors.push({ field, ...config });
        }
    }

    return errors;
};

// Scroll to and highlight the first invalid field
const scrollToFirstError = async (errors) => {
    if (errors.length === 0) return;

    const firstError = errors[0];

    // Wait for DOM to update
    await nextTick();

    // Find the input element by searching for data attribute or by name
    const inputElement = document.querySelector(`[data-field="${firstError.field}"]`)
        || document.querySelector(`input[name="${firstError.field}"]`)
        || document.querySelector(`#${firstError.field}`);

    if (inputElement) {
        // Scroll to element with offset
        inputElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Focus the element
        setTimeout(() => {
            inputElement.focus();
        }, 300);
    }

    // Show error toast with details about which fields are missing
    const fieldNames = errors.map(e => e.label).join(', ');
    toast.error(`Please fill in all required fields`, {
        title: 'Validation Error',
        details: `Missing: ${fieldNames}`,
        duration: 6000,
    });
};

// Clear validation error when field value changes
const clearValidationError = (field) => {
    if (validationErrors.value[field]) {
        delete validationErrors.value[field];
    }
};

// Watch form values to clear validation errors
watch(() => form.value, () => {
    for (const field of Object.keys(mandatoryFields)) {
        if (form.value[field] !== '' && form.value[field] !== null && form.value[field] !== undefined) {
            clearValidationError(field);
        }
    }
}, { deep: true });

// Nginx Streaming Server Control Functions
const loadNginxStatus = async () => {
    try {
        const response = await settingsAPI.getNginxStatus();
        nginxStatus.value = response.data.data;
        // Also update services state
        services.value.nginxStreaming.running = response.data.data?.running || false;
        services.value.nginxStreaming.pid = response.data.data?.pid || null;
        services.value.nginxStreaming.binary = response.data.data?.nginx_binary_path || null;
    } catch (error) {
        console.error('Error loading nginx status:', error);
        nginxStatus.value = { running: false };
        services.value.nginxStreaming.running = false;
    }
};

const testNginxConfig = async () => {
    testingNginxConfig.value = true;
    try {
        const response = await settingsAPI.testNginxConfig();
        if (response.data.success) {
            showMessage('Nginx configuration is valid!');
        } else {
            showMessage('Nginx configuration has errors: ' + response.data.data.output, 'error');
        }
    } catch (error) {
        showMessage('Error testing nginx config: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        testingNginxConfig.value = false;
    }
};

const startNginxStreaming = async () => {
    startingNginx.value = true;
    try {
        const response = await settingsAPI.startNginxStreaming();
        if (response.data.success) {
            showMessage('Nginx streaming server started successfully!');
            await loadNginxStatus();
        } else {
            showMessage('Failed to start nginx: ' + response.data.message, 'error');
        }
    } catch (error) {
        showMessage('Error starting nginx: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        startingNginx.value = false;
    }
};

const stopNginxStreaming = async () => {
    stoppingNginx.value = true;
    try {
        const response = await settingsAPI.stopNginxStreaming();
        if (response.data.success) {
            showMessage('Nginx streaming server stopped');
            await loadNginxStatus();
        } else {
            showMessage('Failed to stop nginx: ' + response.data.message, 'error');
        }
    } catch (error) {
        showMessage('Error stopping nginx: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        stoppingNginx.value = false;
    }
};

const restartNginxStreaming = async () => {
    restartingNginxStreaming.value = true;
    try {
        const response = await settingsAPI.restartNginxStreaming();
        if (response.data.success) {
            showMessage('Nginx streaming server restarted!');
            await loadNginxStatus();
        } else {
            showMessage('Failed to restart nginx: ' + response.data.message, 'error');
        }
    } catch (error) {
        showMessage('Error restarting nginx: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        restartingNginxStreaming.value = false;
    }
};

// Path Auto-Detection Functions
const autoDetectPaths = async () => {
    detectingPaths.value = true;
    try {
        const response = await settingsAPI.autoDetectPaths();
        if (response.data.success) {
            detectedPaths.value = response.data.data.detected;
            showMessage('Paths detected successfully!');
        } else {
            showMessage('Failed to detect paths: ' + response.data.message, 'error');
        }
    } catch (error) {
        showMessage('Error detecting paths: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        detectingPaths.value = false;
    }
};

const applyDetectedPaths = async () => {
    if (!detectedPaths.value) {
        showMessage('Please detect paths first', 'warning');
        return;
    }

    applyingPaths.value = true;
    try {
        const response = await settingsAPI.applyDetectedPaths({
            apply_streams_path: true,
            apply_nginx_paths: true,
            apply_binaries: true
        });

        if (response.data.success) {
            showMessage('Detected paths applied successfully!');
            // Reload settings to reflect changes
            await loadSettings();
        } else {
            showMessage('Failed to apply paths: ' + response.data.message, 'error');
        }
    } catch (error) {
        showMessage('Error applying paths: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        applyingPaths.value = false;
    }
};

const generateNginxConfig = async () => {
    generatingConfig.value = true;
    try {
        const response = await settingsAPI.generateNginxConfig();
        if (response.data.success) {
            generatedConfig.value = response.data.data.config;
            showMessage('Nginx configuration generated!');
        } else {
            showMessage('Failed to generate config: ' + response.data.message, 'error');
        }
    } catch (error) {
        showMessage('Error generating config: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        generatingConfig.value = false;
    }
};

const saveNginxConfig = async () => {
    savingNginxConfig.value = true;
    try {
        const response = await settingsAPI.saveNginxConfig();
        if (response.data.success) {
            showMessage('Nginx configuration saved to: ' + response.data.data.path);
            generatedConfig.value = response.data.data.config;
        } else {
            showMessage('Failed to save config: ' + response.data.message, 'error');
        }
    } catch (error) {
        showMessage('Error saving config: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        savingNginxConfig.value = false;
    }
};

const copyConfig = async () => {
    if (!generatedConfig.value) return;

    try {
        await navigator.clipboard.writeText(generatedConfig.value);
        showMessage('Configuration copied to clipboard!');
    } catch (error) {
        showMessage('Failed to copy to clipboard', 'error');
    }
};

const formatPathLabel = (key) => {
    // Convert snake_case to Title Case
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, l => l.toUpperCase())
        .replace(/Path$/, '')
        .trim();
};

// Services Status Management Functions
const loadServicesStatus = async () => {
    loadingServices.value = true;
    try {
        // Use the unified all_services_status endpoint for efficiency
        const response = await settingsAPI.getAllServicesStatus();
        if (response.data.success) {
            const data = response.data.data;

            // Support both new structure (platform/system) and legacy structure
            const platformData = data.platform || data;
            const systemData = data.system || data;

            // Update Platform Services (Nginx Streaming, PHP-FPM Streaming)
            const nginxStreamingData = platformData.nginxStreaming || data.nginxStreaming;
            if (nginxStreamingData) {
                services.value.nginxStreaming.running = nginxStreamingData.running || false;
                services.value.nginxStreaming.pid = nginxStreamingData.pid || null;
                services.value.nginxStreaming.binary = nginxStreamingData.binary || null;
                // Also update the legacy nginxStatus ref
                nginxStatus.value = nginxStreamingData;
            }

            const phpFpmStreamingData = platformData.phpFpmStreaming || data.phpFpmStreaming;
            if (phpFpmStreamingData) {
                services.value.phpFpmStreaming.running = phpFpmStreamingData.running || false;
                services.value.phpFpmStreaming.pid = phpFpmStreamingData.pid || null;
                services.value.phpFpmStreaming.socket = phpFpmStreamingData.socket || null;
            }

            // Update System Services (read-only status)
            const nginxAdminData = systemData.nginxAdmin || data.nginxAdmin;
            if (nginxAdminData) {
                systemServices.value.nginxAdmin.running = nginxAdminData.running || false;
                systemServices.value.nginxAdmin.pid = nginxAdminData.pid || null;
                systemServices.value.nginxAdmin.port = nginxAdminData.port || form.value.webport;
            }

            const phpFpmAdminData = systemData.phpFpmAdmin;
            if (phpFpmAdminData) {
                systemServices.value.phpFpmAdmin.running = phpFpmAdminData.running || false;
                systemServices.value.phpFpmAdmin.pid = phpFpmAdminData.pid || null;
            }

            const mariadbData = systemData.mariadb;
            if (mariadbData) {
                systemServices.value.mariadb.running = mariadbData.running || false;
                systemServices.value.mariadb.pid = mariadbData.pid || null;
            }
        }
    } catch (error) {
        console.error('Error loading services status:', error);
        // Fallback: try individual status checks
        try {
            await loadNginxStatus();
        } catch (e) {
            console.error('Fallback nginx status failed:', e);
        }
    } finally {
        loadingServices.value = false;
    }
};

// Map Vue service names to PHP API service names
const getApiServiceName = (serviceName) => {
    const map = {
        nginxStreaming: 'nginx_streaming',
        phpFpmStreaming: 'php_fpm_streaming',
        nginxAdmin: 'nginx_admin'
    };
    return map[serviceName] || serviceName;
};

const startService = async (serviceName) => {
    services.value[serviceName].starting = true;
    try {
        // For nginx streaming, regenerate config first to ensure port settings are applied
        if (serviceName === 'nginxStreaming') {
            showMessage('Regenerating nginx config with current settings...');
            await settingsAPI.saveNginxConfig();
        }

        const apiServiceName = getApiServiceName(serviceName);
        const response = await settingsAPI.startService(apiServiceName);

        if (response.data.success) {
            showMessage(`${getServiceLabel(serviceName)} started successfully!`);
            await loadServicesStatus();
        } else {
            showMessage(`Failed to start ${getServiceLabel(serviceName)}: ${response.data.message}`, 'error');
        }
    } catch (error) {
        showMessage(`Error starting ${getServiceLabel(serviceName)}: ${error.response?.data?.message || error.message}`, 'error');
    } finally {
        services.value[serviceName].starting = false;
    }
};

const stopService = async (serviceName) => {
    services.value[serviceName].stopping = true;
    try {
        const apiServiceName = getApiServiceName(serviceName);
        const response = await settingsAPI.stopService(apiServiceName);

        if (response.data.success) {
            showMessage(`${getServiceLabel(serviceName)} stopped`);
            await loadServicesStatus();
        } else {
            showMessage(`Failed to stop ${getServiceLabel(serviceName)}: ${response.data.message}`, 'error');
        }
    } catch (error) {
        showMessage(`Error stopping ${getServiceLabel(serviceName)}: ${error.response?.data?.message || error.message}`, 'error');
    } finally {
        services.value[serviceName].stopping = false;
    }
};

const restartService = async (serviceName) => {
    services.value[serviceName].restarting = true;
    try {
        // For nginx streaming, regenerate config first to ensure port settings are applied
        if (serviceName === 'nginxStreaming') {
            showMessage('Regenerating nginx config with current settings...');
            await settingsAPI.saveNginxConfig();
        }

        const apiServiceName = getApiServiceName(serviceName);
        const response = await settingsAPI.restartService(apiServiceName);

        if (response.data.success) {
            showMessage(`${getServiceLabel(serviceName)} restarted!`);
            await loadServicesStatus();
        } else {
            showMessage(`Failed to restart ${getServiceLabel(serviceName)}: ${response.data.message}`, 'error');
        }
    } catch (error) {
        showMessage(`Error restarting ${getServiceLabel(serviceName)}: ${error.response?.data?.message || error.message}`, 'error');
    } finally {
        services.value[serviceName].restarting = false;
    }
};

const getServiceLabel = (serviceName) => {
    const labels = {
        nginxStreaming: 'Nginx Streaming Service',
        phpFpmStreaming: 'PHP-FPM Streaming Service',
        nginxAdmin: 'Nginx Admin Panel'
    };
    return labels[serviceName] || serviceName;
};

onMounted(() => {
    loadSettings();
    loadSystemInfo();
    loadNginxStatus();
    loadServicesStatus();
});
</script>
