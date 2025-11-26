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
                                <button @click="activeTab = 'history'; loadHistory()" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'history' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                    History & Logs
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
                                    <!-- Analysis Status -->
                                    <div class="flex items-center justify-between p-4 rounded-lg" :class="technicalInfo.analysis_status === 'completed' ? 'bg-green-50' : technicalInfo.analysis_status === 'failed' ? 'bg-red-50' : 'bg-yellow-50'">
                                        <div>
                                            <span class="text-sm font-medium" :class="technicalInfo.analysis_status === 'completed' ? 'text-green-800' : technicalInfo.analysis_status === 'failed' ? 'text-red-800' : 'text-yellow-800'">
                                                Analysis Status: {{ technicalInfo.analysis_status?.toUpperCase() || 'PENDING' }}
                                            </span>
                                            <p v-if="technicalInfo.last_analyzed" class="text-xs text-gray-500 mt-1">
                                                Last analyzed: {{ formatDateTime(technicalInfo.last_analyzed) }}
                                            </p>
                                        </div>
                                        <button @click="analyzeStream" :disabled="analyzing" class="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                                            {{ analyzing ? 'Analyzing...' : 'Re-Analyze' }}
                                        </button>
                                    </div>

                                    <!-- Transcode Profile -->
                                    <div class="p-4 bg-blue-50 rounded-lg">
                                        <h4 class="text-sm font-semibold text-blue-800 mb-2">Transcode Profile</h4>
                                        <p class="text-sm text-blue-900">
                                            {{ technicalInfo.transcode_name || 'Auto-detect (not yet set)' }}
                                        </p>
                                        <p v-if="technicalInfo.profile" class="text-xs text-blue-700 mt-1">
                                            FFprobe Recommended: {{ technicalInfo.profile }}
                                        </p>
                                    </div>

                                    <!-- Video Information -->
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Video Information</h4>
                                        <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Codec</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.video_codec || 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Resolution</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.resolution || 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Quality</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.quality || 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Bitrate</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ formatBitrate(technicalInfo.bitrate) }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Frame Rate</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.fps ? technicalInfo.fps + ' fps' : 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Profile</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.video_profile || 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Pixel Format</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.pixel_format || 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Aspect Ratio</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.aspect_ratio || 'N/A' }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <!-- Audio Information -->
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Audio Information</h4>
                                        <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Codec</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.audio_codec || 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Sample Rate</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.sample_rate || 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Channels</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.audio_channels || 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Bitrate</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ formatBitrate(technicalInfo.audio_bitrate) }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Language</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.audio_language || 'N/A' }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <!-- Container Information -->
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Container Information</h4>
                                        <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Format</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.container || 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Duration</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ technicalInfo.duration || 'Live' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Health Score</dt>
                                                <dd class="mt-1 text-sm font-semibold" :class="technicalInfo.health_score >= 80 ? 'text-green-600' : technicalInfo.health_score >= 50 ? 'text-yellow-600' : 'text-red-600'">
                                                    {{ technicalInfo.health_score || 'N/A' }}{{ technicalInfo.health_score ? '/100' : '' }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <!-- Raw Data -->
                                    <div v-if="technicalInfo.raw_data">
                                        <details class="group">
                                            <summary class="text-sm font-semibold text-gray-700 mb-3 cursor-pointer hover:text-indigo-600">
                                                Raw FFprobe Data (click to expand)
                                            </summary>
                                            <pre class="bg-gray-50 p-4 rounded-lg text-xs overflow-x-auto mt-2">{{ JSON.stringify(technicalInfo.raw_data, null, 2) }}</pre>
                                        </details>
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
                        <div v-show="activeTab === 'access'" class="space-y-6">
                            <!-- Secure URLs Section (Token-based) -->
                            <div class="bg-white shadow rounded-lg p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Secure Stream URLs</h3>
                                        <p class="text-sm text-gray-500">Token-authenticated URLs with enterprise-grade security</p>
                                    </div>
                                    <button @click="generateSecureUrls" :disabled="loadingSecureUrls" class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 disabled:opacity-50">
                                        <svg v-if="loadingSecureUrls" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ loadingSecureUrls ? 'Generating...' : 'Generate Secure URLs' }}
                                    </button>
                                </div>

                                <div v-if="secureUrls" class="space-y-4">
                                    <!-- Token Info -->
                                    <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                                        <div>
                                            <p class="text-sm font-medium text-green-800">Token Active</p>
                                            <p class="text-xs text-green-600">Expires: {{ formatDateTime(secureUrls.expires_at) }}</p>
                                        </div>
                                        <button @click="revokeTokens" class="text-sm text-red-600 hover:text-red-800">Revoke All Tokens</button>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Secure HLS URL</label>
                                        <div class="flex">
                                            <input type="text" readonly :value="secureUrls.urls?.hls" class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md bg-gray-50 text-sm font-mono" />
                                            <button @click="copyToClipboard(secureUrls.urls?.hls)" class="px-4 py-2 bg-green-600 text-white rounded-r-md hover:bg-green-700">
                                                Copy
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Secure DASH URL</label>
                                        <div class="flex">
                                            <input type="text" readonly :value="secureUrls.urls?.dash" class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md bg-gray-50 text-sm font-mono" />
                                            <button @click="copyToClipboard(secureUrls.urls?.dash)" class="px-4 py-2 bg-green-600 text-white rounded-r-md hover:bg-green-700">
                                                Copy
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Secure Direct URL</label>
                                        <div class="flex">
                                            <input type="text" readonly :value="secureUrls.urls?.direct" class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md bg-gray-50 text-sm font-mono" />
                                            <button @click="copyToClipboard(secureUrls.urls?.direct)" class="px-4 py-2 bg-green-600 text-white rounded-r-md hover:bg-green-700">
                                                Copy
                                            </button>
                                        </div>
                                    </div>

                                    <div class="pt-2 text-xs text-gray-500">
                                        <p><strong>Security Features:</strong> IP-bound tokens, 1-hour expiration, usage tracking, revocation support</p>
                                    </div>
                                </div>

                                <div v-else class="text-center py-8 text-gray-500">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    <p class="mt-2">Click "Generate Secure URLs" to create token-authenticated streaming links</p>
                                </div>
                            </div>

                            <!-- Public URLs Section (Non-authenticated) -->
                            <div class="bg-white shadow rounded-lg p-6">
                                <div class="mb-4">
                                    <h3 class="text-lg font-medium text-gray-900">Public Stream URLs</h3>
                                    <p class="text-sm text-yellow-600">Warning: These URLs are not authenticated - use secure URLs for production</p>
                                </div>
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

                        <!-- History Tab -->
                        <div v-show="activeTab === 'history'" class="space-y-6">
                            <!-- Loading -->
                            <div v-if="loadingHistory" class="text-center py-12">
                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                                <p class="mt-2 text-sm text-gray-500">Loading history...</p>
                            </div>

                            <template v-else-if="historyData">
                                <!-- State Info Cards -->
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="bg-white rounded-lg shadow p-4">
                                        <p class="text-sm font-medium text-gray-500">Current State</p>
                                        <p class="text-xl font-bold" :class="getStateColor(historyData.state_info?.state)">
                                            {{ historyData.state_info?.state?.toUpperCase() || 'N/A' }}
                                        </p>
                                    </div>
                                    <div class="bg-white rounded-lg shadow p-4">
                                        <p class="text-sm font-medium text-gray-500">Total Crashes</p>
                                        <p class="text-xl font-bold text-red-600">{{ historyData.state_info?.crash_count || 0 }}</p>
                                    </div>
                                    <div class="bg-white rounded-lg shadow p-4">
                                        <p class="text-sm font-medium text-gray-500">Crashes (24h)</p>
                                        <p class="text-xl font-bold text-orange-600">{{ historyData.stats?.crashes_24h || 0 }}</p>
                                    </div>
                                    <div class="bg-white rounded-lg shadow p-4">
                                        <p class="text-sm font-medium text-gray-500">Health Checks (24h)</p>
                                        <p class="text-xl font-bold text-blue-600">{{ historyData.stats?.checks_24h || 0 }}</p>
                                    </div>
                                </div>

                                <!-- State Details -->
                                <div class="bg-white shadow rounded-lg p-6">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">Stream State Details</h3>
                                    <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Enabled</dt>
                                            <dd class="mt-1">
                                                <span :class="['px-2 py-1 text-xs rounded-full', historyData.state_info?.enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                                    {{ historyData.state_info?.enabled ? 'Yes' : 'No' }}
                                                </span>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">PID</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ historyData.state_info?.pid || 'None' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Restart Attempts</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ historyData.state_info?.restart_attempts || 0 }} / {{ historyData.state_info?.max_restart_attempts || 3 }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Auto-Restart</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ historyData.state_info?.auto_restart_enabled ? 'Enabled' : 'Disabled' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Last Crash</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(historyData.state_info?.last_crash_at) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Last Command</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ historyData.state_info?.last_command_result || 'N/A' }} ({{ formatDateTime(historyData.state_info?.last_command_at) }})</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Stream Started</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(historyData.state_info?.stream_started_at) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Stream Stopped</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(historyData.state_info?.stream_stopped_at) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Scheduled Command</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ historyData.state_info?.scheduled_command || 'none' }}</dd>
                                        </div>
                                        <div v-if="historyData.state_info?.analysis_error">
                                            <dt class="text-sm font-medium text-gray-500">Analysis Error</dt>
                                            <dd class="mt-1 text-sm text-red-600">{{ historyData.state_info?.analysis_error }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <!-- Health Logs Table -->
                                <div class="bg-white shadow rounded-lg p-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-medium text-gray-900">Health Check Logs</h3>
                                        <button @click="loadHistory()" class="text-sm text-indigo-600 hover:text-indigo-900">Refresh</button>
                                    </div>

                                    <div v-if="historyData.health_logs?.length > 0" class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PID</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Error</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <tr v-for="log in historyData.health_logs" :key="log.id" :class="log.status === 'failed' ? 'bg-red-50' : ''">
                                                    <td class="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">{{ formatDateTime(log.checked_at) }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-900">{{ log.check_type }}</td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <span :class="['px-2 py-1 text-xs rounded-full', log.status === 'healthy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                                            {{ log.status }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900">
                                                        {{ log.pid || '-' }}
                                                        <span v-if="log.pid" :class="log.pid_exists ? 'text-green-600' : 'text-red-600'">
                                                            ({{ log.pid_exists ? 'alive' : 'dead' }})
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900">{{ log.action_taken || '-' }}</td>
                                                    <td class="px-4 py-3 text-sm text-red-600 max-w-xs truncate" :title="log.error_message">{{ log.error_message || '-' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div v-else class="text-center py-8 text-gray-500">
                                        No health check logs available
                                    </div>

                                    <!-- Pagination -->
                                    <div v-if="historyData.pagination?.total_pages > 1" class="flex justify-between items-center mt-4 pt-4 border-t">
                                        <p class="text-sm text-gray-700">
                                            Page {{ historyData.pagination.page }} of {{ historyData.pagination.total_pages }}
                                            ({{ historyData.pagination.total }} total logs)
                                        </p>
                                        <div class="flex space-x-2">
                                            <button
                                                @click="loadHistory(historyData.pagination.page - 1)"
                                                :disabled="historyData.pagination.page <= 1"
                                                class="px-3 py-1 border rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                                            >
                                                Previous
                                            </button>
                                            <button
                                                @click="loadHistory(historyData.pagination.page + 1)"
                                                :disabled="historyData.pagination.page >= historyData.pagination.total_pages"
                                                class="px-3 py-1 border rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                                            >
                                                Next
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
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
                            <select v-model="editForm.cat_id" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="0">None</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">FFmpeg Profile</label>
                            <select v-model="editForm.trans_id" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="0">Auto-detect (ffprobe will set optimal)</option>
                                <option v-for="transcode in transcodes" :key="transcode.id" :value="transcode.id">{{ transcode.name }}</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Select a profile for transcoding or leave as auto-detect for FFprobe to determine the optimal settings.</p>
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
import { streamsAPI, categoriesAPI, transcodesAPI, settingsAPI } from '../../services/api';
import { useToastStore } from '../../stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const streamId = route.params.id;

const stream = ref(null);
const technicalInfo = ref(null);
const loading = ref(true);
const loadingTechnical = ref(false);
const analyzing = ref(false);
const checkingAccess = ref(false);
const error = ref(null);
const loadingHistory = ref(false);
const historyData = ref(null);
const loadingSecureUrls = ref(false);
const secureUrls = ref(null);

const activeTab = ref('overview');
const showEditModal = ref(false);
const categories = ref([]);
const transcodes = ref([]);
const settings = ref({
    streaming_port: 8000,
    rtmp_port: 1935
});

const editForm = ref({
    name: '',
    stream_source: '',
    cat_id: 0,
    trans_id: 0
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
                cat_id: stream.value.cat_id || 0,
                trans_id: stream.value.trans_id || 0
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

const fetchCategories = async () => {
    try {
        const response = await categoriesAPI.getAll();
        categories.value = response.data.data || [];
    } catch (err) {
        console.error('Error fetching categories:', err);
    }
};

const fetchTranscodes = async () => {
    try {
        const response = await transcodesAPI.getAll();
        transcodes.value = response.data.data || [];
    } catch (err) {
        console.error('Error fetching transcodes:', err);
    }
};

const fetchSettings = async () => {
    try {
        const response = await settingsAPI.get();
        if (response.data.success && response.data.data) {
            settings.value = {
                streaming_port: response.data.data.streaming_port || 8000,
                rtmp_port: response.data.data.rtmp_port || 1935
            };
        }
    } catch (err) {
        console.error('Error fetching settings:', err);
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
        toast.error('Failed to load technical information', { title: 'Error' });
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
            toast.success('Stream analyzed successfully', { title: 'Analysis Complete' });
        }
    } catch (err) {
        console.error('Error analyzing stream:', err);
        toast.error('Failed to analyze stream', { title: 'Error', details: err.response?.data?.message || err.message });
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
            if (result.accessible) {
                toast.success('Stream is accessible', { title: 'Access Check', details: result.message || '' });
            } else {
                toast.warning('Stream is not accessible', { title: 'Access Check', details: result.message || '' });
            }
        }
    } catch (err) {
        console.error('Error checking accessibility:', err);
        toast.error('Failed to check stream accessibility', { title: 'Error' });
    } finally {
        checkingAccess.value = false;
    }
};

const startStream = async () => {
    try {
        const response = await streamsAPI.start(streamId);
        if (response.data.success) {
            await fetchStream();
            toast.success('Stream started successfully', { title: 'Started' });
        }
    } catch (err) {
        console.error('Error starting stream:', err);
        toast.error('Failed to start stream', { title: 'Error', details: err.response?.data?.message || err.message });
    }
};

const stopStream = async () => {
    try {
        const response = await streamsAPI.stop(streamId);
        if (response.data.success) {
            await fetchStream();
            toast.success('Stream stopped successfully', { title: 'Stopped' });
        }
    } catch (err) {
        console.error('Error stopping stream:', err);
        toast.error('Failed to stop stream', { title: 'Error', details: err.response?.data?.message || err.message });
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
            toast.success('Stream updated successfully', { title: 'Updated' });
        }
    } catch (err) {
        console.error('Error updating stream:', err);
        toast.error('Failed to update stream', { title: 'Error', details: err.response?.data?.message || err.message });
    }
};

const refreshTechnical = async () => {
    await fetchStream();
    await getTechnicalInfo();
};

const loadHistory = async (page = 1) => {
    loadingHistory.value = true;
    try {
        const response = await streamsAPI.getHistory(streamId, { page, limit: 50 });
        if (response.data.success) {
            historyData.value = response.data.data;
        }
    } catch (err) {
        console.error('Error loading history:', err);
        toast.error('Failed to load stream history', { title: 'Error' });
    } finally {
        loadingHistory.value = false;
    }
};

const generateSecureUrls = async () => {
    loadingSecureUrls.value = true;
    try {
        const response = await streamsAPI.getSecureUrls(streamId);
        if (response.data.success) {
            secureUrls.value = response.data.data;
            toast.success('Secure URLs generated successfully', { title: 'Security', details: `Token valid for 1 hour` });
        } else {
            throw new Error(response.data.message || 'Failed to generate secure URLs');
        }
    } catch (err) {
        console.error('Error generating secure URLs:', err);
        toast.error('Failed to generate secure URLs', { title: 'Error', details: err.response?.data?.message || err.message });
    } finally {
        loadingSecureUrls.value = false;
    }
};

const revokeTokens = async () => {
    if (!confirm('Are you sure you want to revoke all streaming tokens for this stream? This will invalidate all current secure URLs.')) {
        return;
    }
    try {
        const response = await streamsAPI.revokeTokens(streamId);
        if (response.data.success) {
            secureUrls.value = null;
            toast.success('All tokens revoked successfully', { title: 'Security' });
        }
    } catch (err) {
        console.error('Error revoking tokens:', err);
        toast.error('Failed to revoke tokens', { title: 'Error', details: err.response?.data?.message || err.message });
    }
};

const getStateColor = (state) => {
    const colors = {
        'running': 'text-green-600',
        'starting': 'text-blue-600',
        'stopped': 'text-gray-600',
        'stopping': 'text-yellow-600',
        'error': 'text-red-600',
        'crashed': 'text-red-600',
    };
    return colors[state] || 'text-gray-600';
};

const getStreamUrl = (type) => {
    const hostname = window.location.hostname;
    const protocol = window.location.protocol;
    const streamingPort = settings.value.streaming_port || 8000;
    const rtmpPort = settings.value.rtmp_port || 1935;

    switch (type) {
        case 'hls':
            return `${protocol}//${hostname}:${streamingPort}/live/${stream.value.id}/index.m3u8`;
        case 'rtmp':
            return `rtmp://${hostname}:${rtmpPort}/live/${stream.value.id}`;
        case 'direct':
            return `${protocol}//${hostname}:${streamingPort}/live/${stream.value.id}`;
        default:
            return '';
    }
};

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        toast.success('Copied to clipboard!', { title: 'Copied' });
    } catch (err) {
        console.error('Failed to copy:', err);
        toast.error('Failed to copy to clipboard', { title: 'Error' });
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
    fetchCategories();
    fetchTranscodes();
    fetchSettings();
});
</script>
