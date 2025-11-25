<template>
    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Loading State -->
                <div v-if="loading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                    <p class="mt-4 text-gray-600">Loading subscription details...</p>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <div class="flex items-center">
                        <svg class="h-6 w-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="text-lg font-medium text-red-900">{{ error }}</h3>
                    </div>
                    <button @click="router.back()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        Go Back
                    </button>
                </div>

                <!-- Subscription Details -->
                <div v-else-if="subscription">
                    <!-- Header -->
                    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center space-x-4">
                            <button @click="router.back()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                                <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                            </button>
                            <div>
                                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Subscription #{{ subscription.id }}</h1>
                                <p class="text-sm text-gray-600 mt-1">
                                    <router-link :to="`/subscribers/${subscription.subscriber_id}`" class="text-indigo-600 hover:text-indigo-800">
                                        {{ subscription.subscriber_name || subscription.subscriber?.username || 'Unknown Subscriber' }}
                                    </router-link>
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button @click="showEditModal = true" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors">
                                <svg class="h-4 w-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit
                            </button>
                            <button @click="showRenewModal = true" class="px-4 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 transition-colors">
                                <svg class="h-4 w-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Renew
                            </button>
                            <button @click="toggleStatus" :disabled="actionLoading" class="px-4 py-2 text-sm rounded-md text-white transition-colors" :class="subscription.is_active ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'">
                                <svg v-if="actionLoading" class="animate-spin h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ subscription.is_active ? 'Disable' : 'Enable' }}
                            </button>
                            <button @click="showDeleteModal = true" class="px-4 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 transition-colors">
                                <svg class="h-4 w-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Delete
                            </button>
                        </div>
                    </div>

                    <!-- Status Badges -->
                    <div class="mb-6 flex flex-wrap gap-2">
                        <span v-if="subscription.is_expired" class="px-3 py-1 text-sm font-medium rounded-full bg-red-100 text-red-800">
                            Expired
                        </span>
                        <span v-else-if="subscription.is_active" class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800">
                            Active
                        </span>
                        <span v-else class="px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800">
                            Inactive
                        </span>
                        <span v-if="subscription.auto_renew" class="px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800">
                            Auto-Renew Enabled
                        </span>
                        <span v-if="subscription.days_until_expiration !== null && subscription.days_until_expiration <= 7 && !subscription.is_expired" class="px-3 py-1 text-sm font-medium rounded-full bg-orange-100 text-orange-800">
                            Expiring Soon ({{ subscription.days_until_expiration }} days)
                        </span>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-white shadow rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Package</p>
                                    <p class="text-lg font-semibold text-gray-900">{{ subscription.package_name || subscription.package?.name || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white shadow rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Connections</p>
                                    <p class="text-lg font-semibold text-gray-900">{{ subscription.active_connections || 0 }} / {{ subscription.max_connections || 1 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white shadow rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full" :class="(subscription.days_until_expiration !== null && subscription.days_until_expiration <= 7) ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Days Remaining</p>
                                    <p class="text-lg font-semibold" :class="(subscription.days_until_expiration !== null && subscription.days_until_expiration <= 7) ? 'text-red-600' : 'text-gray-900'">
                                        {{ subscription.is_expired ? 'Expired' : (subscription.days_until_expiration !== null ? subscription.days_until_expiration : 'N/A') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white shadow rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Status</p>
                                    <p class="text-lg font-semibold" :class="subscription.is_active && !subscription.is_expired ? 'text-green-600' : 'text-red-600'">
                                        {{ subscription.is_expired ? 'Expired' : (subscription.is_active ? 'Active' : 'Disabled') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="mb-6 border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                                class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                                :class="activeTab === tab.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                                {{ tab.name }}
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div v-show="activeTab === 'details'" class="space-y-6">
                        <!-- Subscription Details -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Subscription Details</h3>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Subscription ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900">#{{ subscription.id }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Subscriber</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <router-link :to="`/subscribers/${subscription.subscriber_id}`" class="text-indigo-600 hover:text-indigo-800">
                                            {{ subscription.subscriber_name || subscription.subscriber?.username || 'N/A' }}
                                        </router-link>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Package</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ subscription.package_name || subscription.package?.name || 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Start Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDate(subscription.start_date) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Expire Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900" :class="subscription.is_expired ? 'text-red-600' : ''">
                                        {{ formatDate(subscription.expire_date) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Duration</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ calculateDuration(subscription.start_date, subscription.expire_date) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Max Connections</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ subscription.max_connections || 1 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Active Connections</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ subscription.active_connections || 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Auto-Renew</dt>
                                    <dd class="mt-1">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full" :class="subscription.auto_renew ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                                            {{ subscription.auto_renew ? 'Enabled' : 'Disabled' }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Created At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(subscription.created_at) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(subscription.updated_at) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Notes Section -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Notes</h3>
                            <div v-if="subscription.notes" class="text-sm text-gray-700 whitespace-pre-wrap">{{ subscription.notes }}</div>
                            <div v-else class="text-sm text-gray-500 italic">No notes added for this subscription.</div>
                        </div>
                    </div>

                    <div v-show="activeTab === 'device'" class="space-y-6">
                        <!-- Device Information -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Device Information</h3>
                                <button v-if="subscription.device_id || subscription.device_info" @click="showClearDeviceModal = true"
                                    class="px-3 py-1.5 text-sm bg-red-100 text-red-700 rounded-md hover:bg-red-200 transition-colors">
                                    <svg class="h-4 w-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Clear Device
                                </button>
                            </div>
                            <div v-if="subscription.device_id || subscription.device_info || subscription.last_ip || subscription.last_user_agent">
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                                    <div v-if="subscription.device_id">
                                        <dt class="text-sm font-medium text-gray-500">Device ID</dt>
                                        <dd class="mt-1 text-sm text-gray-900 font-mono break-all">{{ subscription.device_id }}</dd>
                                    </div>
                                    <div v-if="subscription.device_info">
                                        <dt class="text-sm font-medium text-gray-500">Device Info</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscription.device_info }}</dd>
                                    </div>
                                    <div v-if="subscription.last_ip">
                                        <dt class="text-sm font-medium text-gray-500">Last IP Address</dt>
                                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ subscription.last_ip }}</dd>
                                    </div>
                                    <div v-if="subscription.last_user_agent">
                                        <dt class="text-sm font-medium text-gray-500">Last User Agent</dt>
                                        <dd class="mt-1 text-sm text-gray-900 break-all">{{ subscription.last_user_agent }}</dd>
                                    </div>
                                    <div v-if="subscription.last_activity">
                                        <dt class="text-sm font-medium text-gray-500">Last Activity</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(subscription.last_activity) }}</dd>
                                    </div>
                                </dl>
                            </div>
                            <div v-else class="text-center py-8">
                                <svg class="h-12 w-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <p class="mt-4 text-sm text-gray-500">No device has been linked to this subscription yet.</p>
                                <p class="mt-1 text-xs text-gray-400">Device information will appear here when the subscriber starts streaming.</p>
                            </div>
                        </div>
                    </div>

                    <div v-show="activeTab === 'package'" class="space-y-6">
                        <!-- Package Information -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Package Details</h3>
                            <div v-if="subscription.package">
                                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Package Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscription.package.name }}</dd>
                                    </div>
                                    <div v-if="subscription.package.description">
                                        <dt class="text-sm font-medium text-gray-500">Description</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscription.package.description }}</dd>
                                    </div>
                                    <div v-if="subscription.package.price !== undefined">
                                        <dt class="text-sm font-medium text-gray-500">Price</dt>
                                        <dd class="mt-1 text-sm text-gray-900">${{ parseFloat(subscription.package.price || 0).toFixed(2) }}</dd>
                                    </div>
                                    <div v-if="subscription.package.duration_days">
                                        <dt class="text-sm font-medium text-gray-500">Duration</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscription.package.duration_days }} days</dd>
                                    </div>
                                    <div v-if="subscription.package.max_connections">
                                        <dt class="text-sm font-medium text-gray-500">Max Connections</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscription.package.max_connections }}</dd>
                                    </div>
                                    <div v-if="subscription.package.trial_days">
                                        <dt class="text-sm font-medium text-gray-500">Trial Period</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscription.package.trial_days }} days</dd>
                                    </div>
                                </dl>
                            </div>
                            <div v-else class="text-sm text-gray-500 italic">Package information not available.</div>
                        </div>

                        <!-- Bouquets -->
                        <div class="bg-white shadow rounded-lg p-6" v-if="subscription.package?.bouquets?.length">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Included Bouquets</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div v-for="bouquet in subscription.package.bouquets" :key="bouquet.id" class="border rounded-lg p-4">
                                    <h4 class="font-medium text-gray-900">{{ bouquet.name }}</h4>
                                    <p v-if="bouquet.stream_count" class="text-sm text-gray-500">{{ bouquet.stream_count }} streams</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-show="activeTab === 'subscriber'" class="space-y-6">
                        <!-- Subscriber Information -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Subscriber Information</h3>
                                <router-link :to="`/subscribers/${subscription.subscriber_id}`" class="px-3 py-1.5 text-sm bg-indigo-100 text-indigo-700 rounded-md hover:bg-indigo-200 transition-colors">
                                    View Profile
                                </router-link>
                            </div>
                            <div v-if="subscription.subscriber">
                                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Username</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscription.subscriber.username }}</dd>
                                    </div>
                                    <div v-if="subscription.subscriber.email">
                                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscription.subscriber.email }}</dd>
                                    </div>
                                    <div v-if="subscription.subscriber.phone">
                                        <dt class="text-sm font-medium text-gray-500">Phone</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscription.subscriber.phone }}</dd>
                                    </div>
                                    <div v-if="subscription.subscriber.country">
                                        <dt class="text-sm font-medium text-gray-500">Country</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscription.subscriber.country }}</dd>
                                    </div>
                                    <div v-if="subscription.subscriber.city">
                                        <dt class="text-sm font-medium text-gray-500">City</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscription.subscriber.city }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                        <dd class="mt-1">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full" :class="subscription.subscriber.enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                                {{ subscription.subscriber.enabled ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                            <div v-else class="text-sm text-gray-500 italic">Subscriber information not available.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Edit Subscription</h3>
                        <button @click="closeEditModal" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <form @submit.prevent="updateSubscription" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Subscriber</label>
                            <select v-model="editForm.subscriber_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select Subscriber</option>
                                <option v-for="sub in subscribers" :key="sub.id" :value="sub.id">{{ sub.username }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Package</label>
                            <select v-model="editForm.package_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select Package</option>
                                <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input v-model="editForm.start_date" type="datetime-local" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Expire Date</label>
                            <input v-model="editForm.expire_date" type="datetime-local" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Max Connections</label>
                            <input v-model.number="editForm.max_connections" type="number" min="1" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div class="flex items-center space-x-6 pt-6">
                            <label class="flex items-center">
                                <input v-model="editForm.is_active" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" />
                                <span class="ml-2 text-sm text-gray-700">Active</span>
                            </label>
                            <label class="flex items-center">
                                <input v-model="editForm.auto_renew" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" />
                                <span class="ml-2 text-sm text-gray-700">Auto-Renew</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea v-model="editForm.notes" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="Optional notes about this subscription..."></textarea>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" @click="closeEditModal" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" :disabled="saving" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50">
                            <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-4 w-4 inline" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ saving ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Renew Modal -->
        <div v-if="showRenewModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Renew Subscription</h3>
                        <button @click="showRenewModal = false" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <form @submit.prevent="renewSubscription" class="p-6">
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-4">
                            Current expiration: <strong>{{ formatDate(subscription.expire_date) }}</strong>
                        </p>
                        <label class="block text-sm font-medium text-gray-700">Additional Days</label>
                        <input v-model.number="renewDays" type="number" min="1" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" />
                        <p class="mt-2 text-sm text-gray-500">
                            New expiration will be: <strong>{{ calculateNewExpiration() }}</strong>
                        </p>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" @click="showRenewModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" :disabled="saving" class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700 transition-colors disabled:opacity-50">
                            <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-4 w-4 inline" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ saving ? 'Renewing...' : 'Renew Subscription' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Clear Device Modal -->
        <div v-if="showClearDeviceModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Clear Device Information</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-gray-700">
                                Are you sure you want to clear the device information for this subscription? This will allow the subscriber to register a new device.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex justify-end space-x-3">
                    <button @click="showClearDeviceModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button @click="clearDevice" :disabled="saving" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-50">
                        {{ saving ? 'Clearing...' : 'Clear Device' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Delete Subscription</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-gray-700">
                                Are you sure you want to delete this subscription? This action cannot be undone.
                            </p>
                            <p class="mt-2 text-sm text-gray-500">
                                Subscription #{{ subscription?.id }} for {{ subscription?.subscriber_name || subscription?.subscriber?.username }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex justify-end space-x-3">
                    <button @click="showDeleteModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button @click="deleteSubscription" :disabled="saving" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-50">
                        {{ saving ? 'Deleting...' : 'Delete' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '../../components/AppLayout.vue';
import { subscriptionsAPI, subscribersAPI, packagesAPI } from '../../services/api';
import { useToastStore } from '../../stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const subscriptionId = route.params.id;

// State
const subscription = ref(null);
const loading = ref(true);
const actionLoading = ref(false);
const saving = ref(false);
const error = ref(null);

// Tabs
const tabs = [
    { id: 'details', name: 'Details' },
    { id: 'device', name: 'Device' },
    { id: 'package', name: 'Package' },
    { id: 'subscriber', name: 'Subscriber' }
];
const activeTab = ref('details');

// Modals
const showEditModal = ref(false);
const showRenewModal = ref(false);
const showClearDeviceModal = ref(false);
const showDeleteModal = ref(false);

// Form data
const renewDays = ref(30);
const subscribers = ref([]);
const packages = ref([]);
const editForm = ref({
    subscriber_id: '',
    package_id: '',
    start_date: '',
    expire_date: '',
    max_connections: 1,
    is_active: true,
    auto_renew: false,
    notes: ''
});

// Fetch subscription details
const fetchSubscription = async () => {
    loading.value = true;
    error.value = null;
    try {
        const response = await subscriptionsAPI.getOne(subscriptionId);
        if (response.data.success) {
            subscription.value = response.data.data;
        } else {
            error.value = response.data.message || 'Failed to load subscription';
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load subscription details';
        console.error('Error fetching subscription:', err);
    } finally {
        loading.value = false;
    }
};

// Fetch dropdown data
const fetchDropdownData = async () => {
    try {
        const [subscribersRes, packagesRes] = await Promise.all([
            subscribersAPI.getAll({ per_page: 1000 }),
            packagesAPI.getAll()
        ]);

        if (subscribersRes.data.success) {
            subscribers.value = subscribersRes.data.data?.data || subscribersRes.data.data || [];
        }
        if (packagesRes.data.success) {
            packages.value = packagesRes.data.data || [];
        }
    } catch (err) {
        console.error('Error fetching dropdown data:', err);
    }
};

// Toggle status
const toggleStatus = async () => {
    actionLoading.value = true;
    try {
        await subscriptionsAPI.toggle(subscriptionId);
        await fetchSubscription();
        toast.success(`Subscription ${subscription.value.is_active ? 'enabled' : 'disabled'} successfully`);
    } catch (err) {
        toast.error('Failed to toggle subscription status');
        console.error('Error toggling status:', err);
    } finally {
        actionLoading.value = false;
    }
};

// Renew subscription
const renewSubscription = async () => {
    saving.value = true;
    try {
        await subscriptionsAPI.renew(subscriptionId, renewDays.value);
        showRenewModal.value = false;
        await fetchSubscription();
        toast.success(`Subscription renewed for ${renewDays.value} days`);
    } catch (err) {
        toast.error('Failed to renew subscription');
        console.error('Error renewing subscription:', err);
    } finally {
        saving.value = false;
    }
};

// Clear device
const clearDevice = async () => {
    saving.value = true;
    try {
        await subscriptionsAPI.clearDevice(subscriptionId);
        showClearDeviceModal.value = false;
        await fetchSubscription();
        toast.success('Device information cleared successfully');
    } catch (err) {
        toast.error('Failed to clear device information');
        console.error('Error clearing device:', err);
    } finally {
        saving.value = false;
    }
};

// Delete subscription
const deleteSubscription = async () => {
    saving.value = true;
    try {
        await subscriptionsAPI.delete(subscriptionId);
        toast.success('Subscription deleted successfully');
        router.push('/subscriptions');
    } catch (err) {
        toast.error('Failed to delete subscription');
        console.error('Error deleting subscription:', err);
    } finally {
        saving.value = false;
    }
};

// Update subscription
const updateSubscription = async () => {
    saving.value = true;
    try {
        await subscriptionsAPI.update(subscriptionId, editForm.value);
        showEditModal.value = false;
        await fetchSubscription();
        toast.success('Subscription updated successfully');
    } catch (err) {
        toast.error(err.response?.data?.message || 'Failed to update subscription');
        console.error('Error updating subscription:', err);
    } finally {
        saving.value = false;
    }
};

// Open edit modal
const openEditModal = () => {
    if (subscription.value) {
        editForm.value = {
            subscriber_id: subscription.value.subscriber_id,
            package_id: subscription.value.package_id,
            start_date: formatDateTimeLocal(subscription.value.start_date),
            expire_date: formatDateTimeLocal(subscription.value.expire_date),
            max_connections: subscription.value.max_connections || 1,
            is_active: subscription.value.is_active,
            auto_renew: subscription.value.auto_renew || false,
            notes: subscription.value.notes || ''
        };
        showEditModal.value = true;
        fetchDropdownData();
    }
};

// Close edit modal
const closeEditModal = () => {
    showEditModal.value = false;
};

// Format helpers
const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatDateTimeLocal = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const offset = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - offset * 60 * 1000);
    return localDate.toISOString().slice(0, 16);
};

const calculateDuration = (start, end) => {
    if (!start || !end) return 'N/A';
    const startDate = new Date(start);
    const endDate = new Date(end);
    const days = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
    return `${days} days`;
};

const calculateNewExpiration = () => {
    if (!subscription.value?.expire_date) return 'N/A';
    const currentExpire = new Date(subscription.value.expire_date);
    const newExpire = new Date(currentExpire.getTime() + renewDays.value * 24 * 60 * 60 * 1000);
    return formatDate(newExpire);
};

// Watch for edit modal open
import { watch } from 'vue';
watch(showEditModal, (newVal) => {
    if (newVal) {
        openEditModal();
    }
});

onMounted(() => {
    fetchSubscription();
});
</script>
