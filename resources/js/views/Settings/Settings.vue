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

            <div v-if="message" class="mb-6">
                <div :class="['rounded-md p-4', message.type === 'success' ? 'bg-green-50' : message.type === 'warning' ? 'bg-yellow-50' : 'bg-red-50']">
                    <p :class="['text-sm', message.type === 'success' ? 'text-green-800' : message.type === 'warning' ? 'text-yellow-800' : 'text-red-800']">{{ message.text }}</p>
                </div>
            </div>

            <form @submit.prevent="saveSettings" class="bg-white shadow rounded-lg overflow-hidden">
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
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100"
                                        :class="{ 'bg-gray-50': !ffmpegEdit }"
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
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                        :class="{ 'bg-gray-50': !ffprobeEdit }"
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
                                <input v-model="form.webip" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="example.com" />
                                <p class="mt-1 text-xs text-gray-500">Without trailing slash (e.g., example.com NOT example.com/)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Web Port *
                                </label>
                                <div class="flex space-x-2">
                                    <input v-model.number="form.webport" type="number" required class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="8000" />
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
                                    HLS Folder *
                                </label>
                                <input v-model="form.hlsfolder" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="/var/www/hls" />
                                <p class="mt-1 text-xs text-gray-500">Directory for HLS stream segments</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    User Agent *
                                </label>
                                <input v-model="form.user_agent" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Mozilla/5.0..." />
                                <p class="mt-1 text-xs text-gray-500">User agent for HTTP requests</p>
                            </div>
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

                    <!-- Submit Button -->
                    <div class="flex items-center justify-between pt-4">
                        <button type="button" @click="loadSystemInfo" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Refresh System Info
                        </button>
                        <button type="submit" :disabled="saving" class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                            <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ saving ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </div>
            </form>

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
import { ref, onMounted, watch } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { settingsAPI, systemCommandsAPI } from '../../services/api';

const loading = ref(false);
const saving = ref(false);
const message = ref(null);
const systemInfo = ref(null);

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
    last_command_at: null
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
            last_command_at: data.last_command_at || null
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
    // Validate FFmpeg/FFprobe if in edit mode
    if (ffmpegEdit.value) {
        showMessage('Please test FFmpeg path before saving', 'error');
        return;
    }
    if (ffprobeEdit.value) {
        showMessage('Please test FFprobe path before saving', 'error');
        return;
    }
    if (sudoEdit.value && form.value.sudo_password) {
        showMessage('Please test sudo password before saving', 'error');
        return;
    }

    saving.value = true;
    try {
        const response = await settingsAPI.update(form.value);
        const data = response.data;

        showMessage('Settings saved successfully');

        await loadSettings();
        await loadSystemInfo();

        // Port change flag will be recalculated by watcher
    } catch (error) {
        showMessage('Error saving settings: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        saving.value = false;
    }
};

const showMessage = (text, type = 'success') => {
    message.value = { text, type };
    setTimeout(() => message.value = null, 8000);
};

onMounted(() => {
    loadSettings();
    loadSystemInfo();
});
</script>
