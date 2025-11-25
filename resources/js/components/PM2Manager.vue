<template>
    <div class="space-y-6">
        <!-- PM2 Workers Section -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-purple-500 to-indigo-600 text-white">
                <h3 class="text-lg font-semibold flex items-center">
                    <svg class="h-6 w-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                    </svg>
                    PM2 Background Workers
                </h3>
                <p class="text-sm opacity-90 mt-1">Manage background job processors for stream imports and FFprobe analysis</p>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="p-12 text-center">
                <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-4 text-gray-600">Loading PM2 status...</p>
            </div>

            <!-- PM2 Not Installed Warning -->
            <div v-else-if="!pm2Installed" class="p-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-yellow-400 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <div class="flex-1">
                            <div class="text-sm text-yellow-800">
                                <p class="font-medium">PM2 Not Installed</p>
                                <p class="mt-1">PM2 is not installed on this system. Click the button below to install it automatically, or install manually using:</p>
                                <ul class="list-disc ml-5 mt-2 space-y-1">
                                    <li><strong>Manual:</strong> <code class="bg-yellow-100 px-1 rounded">npm install -g pm2</code></li>
                                </ul>
                                <p class="mt-2 text-xs">Note: When using NVM, PM2 will be installed in your user directory (no sudo required).</p>
                            </div>
                            <div class="mt-4">
                                <button
                                    @click="installPM2"
                                    :disabled="installing"
                                    class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 disabled:opacity-50 text-sm flex items-center"
                                >
                                    <svg v-if="!installing" class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <svg v-else class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ installing ? 'Installing PM2...' : 'Install PM2 Automatically' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PM2 Workers Table -->
            <div v-else class="p-6">
                <!-- Ecosystem Status Banner -->
                <div v-if="!ecosystemRunning" class="mb-4 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <strong>PM2 Ecosystem Not Running</strong> - No workers are currently active. Click "Start All" below to launch all workers from the ecosystem configuration.
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="mb-4 bg-green-50 border-l-4 border-green-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700">
                                <strong>PM2 Ecosystem Running</strong> - {{ workers.filter(w => w.status === 'online').length }} of {{ workers.length }} workers online
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Control Buttons -->
                <div class="mb-4 flex space-x-2">
                    <button
                        @click="controlWorkers('start')"
                        :disabled="processing"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 text-sm flex items-center"
                    >
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Start All
                    </button>
                    <button
                        @click="controlWorkers('stop')"
                        :disabled="processing"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 text-sm flex items-center"
                    >
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                        </svg>
                        Stop All
                    </button>
                    <button
                        @click="controlWorkers('restart')"
                        :disabled="processing"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 text-sm flex items-center"
                    >
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Restart All
                    </button>
                    <button
                        @click="syncWorkers"
                        :disabled="processing || syncing"
                        class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 disabled:opacity-50 text-sm flex items-center"
                        title="Regenerate PM2 config from database and reload workers"
                    >
                        <svg v-if="!syncing" class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <svg v-else class="animate-spin h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ syncing ? 'Syncing...' : 'Sync Workers' }}
                    </button>
                    <button
                        @click="refreshStatus"
                        :disabled="processing"
                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 disabled:opacity-50 text-sm flex items-center ml-auto"
                    >
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh
                    </button>
                </div>

                <!-- Workers Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Worker</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPU</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Memory</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uptime</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Restarts</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-for="worker in workers" :key="worker.name">
                                <!-- Main Worker Row -->
                                <tr :class="expandedWorker === worker.name ? 'bg-gray-50' : ''">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <button
                                                type="button"
                                                @click.prevent="toggleWorkerDetails(worker.name)"
                                                class="mr-2 text-gray-400 hover:text-gray-600"
                                                title="Show/hide details"
                                            >
                                                <svg
                                                    class="h-4 w-4 transform transition-transform"
                                                    :class="expandedWorker === worker.name ? 'rotate-90' : ''"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </button>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ worker.name }}</div>
                                                <div class="text-xs text-gray-500">ID: {{ worker.pm_id }} | PID: {{ worker.pid || 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="getStatusClass(worker.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            {{ worker.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ worker.exec_mode || 'fork' }}</div>
                                        <div class="text-xs text-gray-500">{{ worker.instances || 1 }} instance(s)</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ worker.cpu }}%
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatMemory(worker.memory) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatUptime(worker.uptime) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ worker.restarts }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                                        <button
                                            v-if="worker.status !== 'online'"
                                            @click="controlWorker(worker.name, 'start')"
                                            :disabled="processing"
                                            class="text-green-600 hover:text-green-900 disabled:opacity-50 font-medium"
                                            title="Start worker"
                                        >
                                            Start
                                        </button>
                                        <button
                                            v-if="worker.status === 'online'"
                                            @click="controlWorker(worker.name, 'stop')"
                                            :disabled="processing"
                                            class="text-red-600 hover:text-red-900 disabled:opacity-50 font-medium"
                                            title="Stop worker"
                                        >
                                            Stop
                                        </button>
                                        <button
                                            @click="controlWorker(worker.name, 'restart')"
                                            :disabled="processing"
                                            class="text-indigo-600 hover:text-indigo-900 disabled:opacity-50 font-medium"
                                            title="Restart worker"
                                        >
                                            Restart
                                        </button>
                                        <button
                                            @click="editWorker(worker.name)"
                                            :disabled="processing"
                                            class="text-blue-600 hover:text-blue-900 disabled:opacity-50 font-medium"
                                            title="Edit worker configuration"
                                        >
                                            Edit
                                        </button>
                                    </td>
                                </tr>

                                <!-- Expanded Details Row -->
                                <tr v-if="expandedWorker === worker.name">
                                    <td colspan="8" class="px-6 py-4 bg-gray-50">
                                        <!-- Worker Description -->
                                        <div v-if="worker.description" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                            <p class="text-sm text-blue-800">
                                                <svg class="h-4 w-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                </svg>
                                                <strong>Purpose:</strong> {{ worker.description }}
                                            </p>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <!-- Worker Configuration -->
                                            <div>
                                                <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
                                                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                    PM2 Configuration
                                                </h4>
                                                <div v-if="workerDetails[worker.name]" class="space-y-2 text-sm">
                                                    <div>
                                                        <span class="text-gray-500">Script:</span>
                                                        <span class="ml-2 text-gray-900 font-mono text-xs">{{ workerDetails[worker.name].script || 'N/A' }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-gray-500">Exec Mode:</span>
                                                        <span class="ml-2 text-gray-900">{{ workerDetails[worker.name].exec_mode || 'fork' }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-gray-500">Memory Limit:</span>
                                                        <span class="ml-2 text-gray-900">{{ workerDetails[worker.name].max_memory_restart || 'N/A' }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-gray-500">Max Restarts:</span>
                                                        <span class="ml-2 text-gray-900">{{ workerDetails[worker.name].max_restarts || 'N/A' }}</span>
                                                    </div>
                                                    <div v-if="workerDetails[worker.name].cron_restart">
                                                        <span class="text-gray-500">Cron:</span>
                                                        <span class="ml-2 text-gray-900 font-mono text-xs">{{ workerDetails[worker.name].cron_restart }}</span>
                                                    </div>
                                                </div>
                                                <div v-else class="text-sm text-gray-500">
                                                    <svg class="animate-spin h-4 w-4 inline mr-2" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Loading...
                                                </div>
                                            </div>

                                            <!-- Worker Parameters (Configurable) -->
                                            <div v-if="Object.keys(getWorkerSchema(worker.name)).length > 0">
                                                <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
                                                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                                    </svg>
                                                    Worker Parameters
                                                </h4>
                                                <div v-if="editingWorkerParams[worker.name]" class="space-y-3">
                                                    <div v-for="(config, paramKey) in getWorkerSchema(worker.name)" :key="paramKey">
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">
                                                            {{ config.label }}
                                                        </label>
                                                        <input
                                                            v-model.number="editingWorkerParams[worker.name][paramKey]"
                                                            :type="config.type"
                                                            :min="config.min"
                                                            :max="config.max"
                                                            class="block w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                                        />
                                                        <p class="text-xs text-gray-500 mt-0.5">{{ config.description }}</p>
                                                    </div>
                                                    <button
                                                        @click="saveWorkerParams(worker.name)"
                                                        :disabled="savingWorkerParams[worker.name]"
                                                        class="mt-2 w-full px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 disabled:opacity-50 flex items-center justify-center"
                                                    >
                                                        <svg v-if="savingWorkerParams[worker.name]" class="animate-spin h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                        </svg>
                                                        {{ savingWorkerParams[worker.name] ? 'Saving...' : 'Save & Restart' }}
                                                    </button>
                                                </div>
                                                <div v-else class="text-sm text-gray-500">
                                                    <svg class="animate-spin h-4 w-4 inline mr-2" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    Loading...
                                                </div>
                                            </div>

                                            <!-- Quick Actions -->
                                            <div>
                                                <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
                                                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                    </svg>
                                                    Quick Actions
                                                </h4>
                                                <div class="space-y-2">
                                                    <button
                                                        v-if="worker.status !== 'online'"
                                                        @click="controlWorker(worker.name, 'start')"
                                                        :disabled="processing"
                                                        class="w-full px-3 py-1.5 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 disabled:opacity-50 flex items-center justify-center"
                                                    >
                                                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                        </svg>
                                                        Start Worker
                                                    </button>
                                                    <button
                                                        v-if="worker.status === 'online'"
                                                        @click="controlWorker(worker.name, 'stop')"
                                                        :disabled="processing"
                                                        class="w-full px-3 py-1.5 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 disabled:opacity-50 flex items-center justify-center"
                                                    >
                                                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                                        </svg>
                                                        Stop Worker
                                                    </button>
                                                    <button
                                                        @click="controlWorker(worker.name, 'restart')"
                                                        :disabled="processing"
                                                        class="w-full px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 disabled:opacity-50 flex items-center justify-center"
                                                    >
                                                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                        </svg>
                                                        Restart Worker
                                                    </button>
                                                    <button
                                                        @click="editWorker(worker.name)"
                                                        :disabled="processing"
                                                        class="w-full px-3 py-1.5 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700 disabled:opacity-50 flex items-center justify-center"
                                                    >
                                                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        Advanced Settings
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Job Queue Statistics -->
        <div v-if="pm2Installed && queueStats" class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-blue-500 to-cyan-600 text-white">
                <h3 class="text-lg font-semibold">Job Queue Statistics</h3>
                <p class="text-sm opacity-90 mt-1">Current status of background job queues</p>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Stream Import Queue -->
                    <div v-if="queueStats.stream_import" class="border border-gray-200 rounded-lg p-4">
                        <h4 class="text-md font-semibold text-gray-900 mb-3">Stream Import Queue</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Pending:</span>
                                <span class="text-sm font-medium text-yellow-600">{{ queueStats.stream_import.pending }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Processing:</span>
                                <span class="text-sm font-medium text-blue-600">{{ queueStats.stream_import.processing }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Completed:</span>
                                <span class="text-sm font-medium text-green-600">{{ queueStats.stream_import.completed }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Failed:</span>
                                <span class="text-sm font-medium text-red-600">{{ queueStats.stream_import.failed }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-gray-200">
                                <span class="text-sm font-semibold text-gray-900">Total:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ queueStats.stream_import.total }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- FFprobe Analysis Queue -->
                    <div v-if="queueStats.ffprobe_analysis" class="border border-gray-200 rounded-lg p-4">
                        <h4 class="text-md font-semibold text-gray-900 mb-3">FFprobe Analysis Queue</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Pending:</span>
                                <span class="text-sm font-medium text-yellow-600">{{ queueStats.ffprobe_analysis.pending }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Processing:</span>
                                <span class="text-sm font-medium text-blue-600">{{ queueStats.ffprobe_analysis.processing }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Completed:</span>
                                <span class="text-sm font-medium text-green-600">{{ queueStats.ffprobe_analysis.completed }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Failed:</span>
                                <span class="text-sm font-medium text-red-600">{{ queueStats.ffprobe_analysis.failed }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-gray-200">
                                <span class="text-sm font-semibold text-gray-900">Total:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ queueStats.ffprobe_analysis.total }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Services Section -->
        <div v-if="pm2Installed && services" class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white">
                <h3 class="text-lg font-semibold">System Services</h3>
                <p class="text-sm opacity-90 mt-1">Core system services status and controls</p>
            </div>

            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="service in services" :key="service.name">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ service.display_name }}</div>
                                    <div class="text-xs text-gray-500">{{ service.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ service.description }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="service.status.active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                        {{ service.status.active ? 'Active' : 'Inactive' }}
                                    </span>
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ service.status.enabled ? 'Enabled' : 'Disabled' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                                    <button
                                        @click="controlService(service.name, 'start')"
                                        :disabled="processingService"
                                        class="text-green-600 hover:text-green-900 disabled:opacity-50"
                                    >
                                        Start
                                    </button>
                                    <button
                                        @click="controlService(service.name, 'stop')"
                                        :disabled="processingService"
                                        class="text-red-600 hover:text-red-900 disabled:opacity-50"
                                    >
                                        Stop
                                    </button>
                                    <button
                                        @click="controlService(service.name, 'restart')"
                                        :disabled="processingService"
                                        class="text-indigo-600 hover:text-indigo-900 disabled:opacity-50"
                                    >
                                        Restart
                                    </button>
                                    <button
                                        v-if="service.name === 'nginx'"
                                        @click="controlService(service.name, 'reload')"
                                        :disabled="processingService"
                                        class="text-blue-600 hover:text-blue-900 disabled:opacity-50"
                                    >
                                        Reload
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Message Display -->
        <div v-if="message" class="rounded-md p-4" :class="message.type === 'success' ? 'bg-green-50' : message.type === 'warning' ? 'bg-yellow-50' : 'bg-red-50'">
            <p :class="message.type === 'success' ? 'text-green-800' : message.type === 'warning' ? 'text-yellow-800' : 'text-red-800'" class="text-sm">
                {{ message.text }}
            </p>
        </div>

        <!-- Edit Worker Modal -->
        <div v-if="showEditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click.self="closeEditModal">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3 border-b">
                    <h3 class="text-xl font-semibold text-gray-900">Edit Worker Configuration: {{ editingWorker }}</h3>
                    <button @click="closeEditModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="mt-4">
                    <div v-if="loadingConfig" class="text-center py-8">
                        <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-2 text-gray-600">Loading configuration...</p>
                    </div>

                    <form v-else @submit.prevent="saveWorkerConfig" class="space-y-4">
                        <!-- Worker Name (Read-only) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Worker Name</label>
                            <input type="text" :value="workerConfig.name" disabled class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-500" />
                        </div>

                        <!-- Script Path -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Script Path</label>
                            <input v-model="workerConfig.script" type="text" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>

                        <!-- Instances -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Instances</label>
                                <input v-model.number="workerConfig.instances" type="number" min="1" max="16" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                                <p class="mt-1 text-xs text-gray-500">Number of instances to run (1-16)</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Execution Mode</label>
                                <select v-model="workerConfig.exec_mode" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="fork">Fork (single instance)</option>
                                    <option value="cluster">Cluster (multiple instances)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Memory Limit -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Max Memory Restart</label>
                            <input v-model="workerConfig.max_memory_restart" type="text" placeholder="500M" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            <p class="mt-1 text-xs text-gray-500">Auto-restart when memory exceeds this limit (e.g., 500M, 1G)</p>
                        </div>

                        <!-- Auto-restart Settings -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Max Restarts</label>
                                <input v-model.number="workerConfig.max_restarts" type="number" min="1" max="100" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                                <p class="mt-1 text-xs text-gray-500">Max restart attempts before giving up</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Min Uptime</label>
                                <input v-model="workerConfig.min_uptime" type="text" placeholder="10s" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                                <p class="mt-1 text-xs text-gray-500">Minimum uptime before restart (e.g., 10s, 1m)</p>
                            </div>
                        </div>

                        <!-- Cron Restart -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Cron Restart Schedule</label>
                            <input v-model="workerConfig.cron_restart" type="text" placeholder="0 3 * * *" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            <p class="mt-1 text-xs text-gray-500">Cron expression for scheduled restarts (leave empty to disable)</p>
                        </div>

                        <!-- Environment Variables -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Log Level</label>
                            <select v-model="workerConfig.log_level" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="debug">Debug</option>
                                <option value="info">Info</option>
                                <option value="warn">Warn</option>
                                <option value="error">Error</option>
                            </select>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button
                                type="button"
                                @click="closeEditModal"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="savingConfig"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 flex items-center"
                            >
                                <svg v-if="savingConfig" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ savingConfig ? 'Saving...' : 'Save Configuration' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { pm2API } from '../services/api';

const loading = ref(false);
const processing = ref(false);
const processingService = ref(false);
const installing = ref(false);
const syncing = ref(false);
const message = ref(null);
const pm2Installed = ref(false);
const ecosystemRunning = ref(false);
const workers = ref([]);
const services = ref([]);
const queueStats = ref(null);

// Worker details expansion
const expandedWorker = ref(null);
const workerDetails = ref({});

// Worker-specific configurable parameters schema
const workerParameterSchemas = {
    'stream-manager-worker': {
        POLL_INTERVAL: { type: 'number', label: 'Poll Interval (ms)', default: 5000, min: 1000, max: 60000, description: 'How often to check for stream commands' },
    },
    'stream-monitor-worker': {
        POLL_INTERVAL: { type: 'number', label: 'Poll Interval (ms)', default: 10000, min: 1000, max: 60000, description: 'How often to check stream health' },
    },
    'stream-import-worker': {
        POLL_INTERVAL: { type: 'number', label: 'Poll Interval (ms)', default: 5000, min: 1000, max: 60000, description: 'How often to poll the import queue' },
    },
    'ffprobe-worker': {
        POLL_INTERVAL: { type: 'number', label: 'Poll Interval (ms)', default: 10000, min: 1000, max: 60000, description: 'How often to poll for analysis jobs' },
    },
    'website-health-worker': {
        POLL_INTERVAL: { type: 'number', label: 'Poll Interval (ms)', default: 30000, min: 5000, max: 300000, description: 'How often to check system health' },
    },
    'srt-proxy-worker': {
        SRT_PORT: { type: 'number', label: 'SRT Port', default: 9000, min: 1024, max: 65535, description: 'Port for SRT connections' },
    },
    'quic-proxy-worker': {
        QUIC_PORT: { type: 'number', label: 'QUIC Port', default: 443, min: 1, max: 65535, description: 'Port for QUIC connections' },
    },
    'v2ray-proxy-worker': {
        V2RAY_PORT: { type: 'number', label: 'V2Ray Port', default: 10086, min: 1024, max: 65535, description: 'Port for V2Ray connections' },
    }
};

// Inline editing state for worker parameters
const editingWorkerParams = ref({});
const savingWorkerParams = ref({});

// Get parameter schema for a worker
const getWorkerSchema = (workerName) => {
    return workerParameterSchemas[workerName] || {};
};

// Initialize editing parameters when expanding a worker
const initializeWorkerParams = (workerName, currentEnvVars) => {
    const schema = getWorkerSchema(workerName);
    const params = {};
    for (const [key, config] of Object.entries(schema)) {
        params[key] = currentEnvVars?.[key] ?? config.default;
    }
    editingWorkerParams.value[workerName] = params;
};

// Save worker parameters
const saveWorkerParams = async (workerName) => {
    savingWorkerParams.value[workerName] = true;
    try {
        const params = editingWorkerParams.value[workerName];
        const details = workerDetails.value[workerName];

        // Merge with existing config
        const config = {
            ...details,
            env_vars: {
                ...(details.env_vars || {}),
                ...params
            }
        };

        const response = await pm2API.updateConfig(workerName, config);

        if (response.data.success) {
            showMessage(`Parameters saved for ${workerName}. Restarting worker...`, 'success');
            // Update local details
            workerDetails.value[workerName] = {
                ...details,
                env_vars: config.env_vars
            };
            // Restart worker to apply changes
            setTimeout(async () => {
                await controlWorker(workerName, 'restart');
            }, 500);
        } else {
            showMessage('Error saving parameters: ' + response.data.message, 'error');
        }
    } catch (error) {
        showMessage('Error saving parameters: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        savingWorkerParams.value[workerName] = false;
    }
};

// Edit modal state
const showEditModal = ref(false);
const editingWorker = ref('');
const loadingConfig = ref(false);
const savingConfig = ref(false);
const workerConfig = ref({
    name: '',
    script: '',
    instances: 1,
    exec_mode: 'fork',
    max_memory_restart: '500M',
    max_restarts: 10,
    min_uptime: '10s',
    cron_restart: '',
    log_level: 'warn'
});

let refreshInterval = null;

const loadStatus = async () => {
    loading.value = true;
    try {
        const response = await pm2API.getStatus();

        if (response.data.success) {
            const data = response.data.data;
            pm2Installed.value = data.pm2_installed;
            workers.value = data.pm2_workers || [];
            services.value = data.system_services || [];
            queueStats.value = data.queue_stats || null;

            // Check if ecosystem is running (at least one worker online)
            ecosystemRunning.value = workers.value.some(w => w.status === 'online');

            // Debug: Log worker count and ecosystem status
            console.log('[PM2Manager] Loaded workers:', workers.value.length, workers.value.map(w => w.name));
            console.log('[PM2Manager] Ecosystem running:', ecosystemRunning.value);
        }
    } catch (error) {
        showMessage('Error loading PM2 status: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        loading.value = false;
    }
};

const refreshStatus = async () => {
    await loadStatus();
    showMessage('Status refreshed', 'success');
};

const controlWorkers = async (action) => {
    processing.value = true;
    try {
        let response;
        if (action === 'start') {
            response = await pm2API.start();
        } else if (action === 'stop') {
            response = await pm2API.stop();
        } else if (action === 'restart') {
            response = await pm2API.restart();
        }

        if (response.data.success) {
            showMessage(response.data.message, 'success');
            // Wait a bit for PM2 to update
            setTimeout(async () => {
                await loadStatus();
            }, 2000);
        }
    } catch (error) {
        showMessage('Error: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        processing.value = false;
    }
};

const syncWorkers = async () => {
    if (!confirm('Sync PM2 workers from database?\n\nThis will regenerate ecosystem.config.cjs from the database and reload all workers.')) {
        return;
    }

    syncing.value = true;
    try {
        const response = await pm2API.sync();

        if (response.data.success) {
            showMessage(`${response.data.message} (${response.data.workers_count} workers)`, 'success');
            // Wait for PM2 to fully reload
            setTimeout(async () => {
                await loadStatus();
            }, 3000);
        } else {
            showMessage('Sync failed: ' + response.data.message, 'error');
        }
    } catch (error) {
        showMessage('Error syncing workers: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        syncing.value = false;
    }
};

const controlService = async (service, action) => {
    if (!confirm(`Are you sure you want to ${action} ${service}?`)) {
        return;
    }

    processingService.value = true;
    try {
        const response = await pm2API.serviceAction(service, action);

        if (response.data.success) {
            showMessage(response.data.message, 'success');
            // Refresh status after a delay
            setTimeout(async () => {
                await loadStatus();
            }, 2000);
        }
    } catch (error) {
        const errorMsg = error.response?.data?.message || error.message;

        // Check if it's a sudo password error
        if (errorMsg.includes('Sudo password not configured')) {
            showMessage('Please configure sudo password in System Commands Configuration section above, then try again.', 'error');
        } else {
            showMessage('Error: ' + errorMsg, 'error');
        }
    } finally {
        processingService.value = false;
    }
};

const getStatusClass = (status) => {
    switch (status) {
        case 'online':
            return 'bg-green-100 text-green-800';
        case 'stopped':
            return 'bg-gray-100 text-gray-800';
        case 'errored':
            return 'bg-red-100 text-red-800';
        case 'launching':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-yellow-100 text-yellow-800';
    }
};

const formatMemory = (bytes) => {
    if (!bytes) return '0 MB';
    const mb = bytes / (1024 * 1024);
    return mb.toFixed(1) + ' MB';
};

const formatUptime = (timestamp) => {
    if (!timestamp) return 'N/A';
    const now = Date.now();
    const uptime = now - timestamp;
    const seconds = Math.floor(uptime / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (days > 0) return `${days}d ${hours % 24}h`;
    if (hours > 0) return `${hours}h ${minutes % 60}m`;
    if (minutes > 0) return `${minutes}m ${seconds % 60}s`;
    return `${seconds}s`;
};

const showMessage = (text, type = 'success') => {
    message.value = { text, type };
    setTimeout(() => message.value = null, 5000);
};

// Install PM2
const installPM2 = async () => {
    if (!confirm('Install PM2 globally using npm?\n\nIf using NVM, this will install to your user directory.\nIf using system npm, sudo privileges may be required.')) {
        return;
    }

    installing.value = true;
    try {
        const response = await pm2API.install();

        if (response.data.success) {
            showMessage('PM2 installed successfully! Refreshing status...', 'success');
            setTimeout(async () => {
                await loadStatus();
            }, 2000);
        }
    } catch (error) {
        showMessage('Error installing PM2: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        installing.value = false;
    }
};

// Control individual worker
const controlWorker = async (workerName, action) => {
    processing.value = true;
    try {
        let response;
        if (action === 'start') {
            response = await pm2API.start(workerName);
        } else if (action === 'stop') {
            response = await pm2API.stop(workerName);
        } else if (action === 'restart') {
            response = await pm2API.restart(workerName);
        }

        if (response.data.success) {
            showMessage(response.data.message, 'success');
            // Wait a bit for PM2 to update
            setTimeout(async () => {
                await loadStatus();
            }, 2000);
        }
    } catch (error) {
        showMessage('Error: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        processing.value = false;
    }
};

// Edit worker configuration
const editWorker = async (workerName) => {
    editingWorker.value = workerName;
    showEditModal.value = true;
    loadingConfig.value = true;

    try {
        const response = await pm2API.getConfig(workerName);

        if (response.data.success) {
            const config = response.data.data;
            workerConfig.value = {
                name: config.name || workerName,
                script: config.script || '',
                instances: config.instances || 1,
                exec_mode: config.exec_mode || 'fork',
                max_memory_restart: config.max_memory_restart || '500M',
                max_restarts: config.max_restarts || 10,
                min_uptime: config.min_uptime || '10s',
                cron_restart: config.cron_restart || '',
                log_level: config.log_level || 'warn'
            };
        }
    } catch (error) {
        showMessage('Error loading configuration: ' + (error.response?.data?.message || error.message), 'error');
        closeEditModal();
    } finally {
        loadingConfig.value = false;
    }
};

// Save worker configuration
const saveWorkerConfig = async () => {
    savingConfig.value = true;
    try {
        const response = await pm2API.updateConfig(editingWorker.value, workerConfig.value);

        if (response.data.success) {
            showMessage('Configuration saved! Restarting worker to apply changes...', 'success');
            closeEditModal();

            // Restart the worker to apply new configuration
            setTimeout(async () => {
                await controlWorker(editingWorker.value, 'restart');
            }, 1000);
        }
    } catch (error) {
        showMessage('Error saving configuration: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        savingConfig.value = false;
    }
};

// Close edit modal
const closeEditModal = () => {
    showEditModal.value = false;
    editingWorker.value = '';
    workerConfig.value = {
        name: '',
        script: '',
        instances: 1,
        exec_mode: 'fork',
        max_memory_restart: '500M',
        max_restarts: 10,
        min_uptime: '10s',
        cron_restart: '',
        log_level: 'warn'
    };
};

// Toggle worker details expansion
const toggleWorkerDetails = async (workerName) => {
    if (expandedWorker.value === workerName) {
        // Collapse if already expanded
        expandedWorker.value = null;
    } else {
        // Expand and load configuration
        expandedWorker.value = workerName;

        // Load worker configuration if not already loaded
        if (!workerDetails.value[workerName]) {
            try {
                const response = await pm2API.getConfig(workerName);
                if (response.data.success) {
                    workerDetails.value[workerName] = response.data.data;
                    // Initialize editable parameters from loaded config
                    initializeWorkerParams(workerName, response.data.data.env_vars);
                }
            } catch (error) {
                console.error('Error loading worker details:', error);
            }
        } else {
            // Details already loaded, just initialize parameters if not already done
            if (!editingWorkerParams.value[workerName]) {
                initializeWorkerParams(workerName, workerDetails.value[workerName].env_vars);
            }
        }
    }
};

onMounted(() => {
    loadStatus();
    // Auto-refresh every 30 seconds
    refreshInterval = setInterval(loadStatus, 30000);
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>
