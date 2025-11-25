<template>
    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Loading State -->
                <div v-if="loading" class="flex items-center justify-center min-h-[60vh]">
                    <div class="text-center">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                        <p class="mt-4 text-gray-600">Loading subscriber details...</p>
                    </div>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <div class="flex items-center">
                        <svg class="h-6 w-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <h3 class="text-lg font-medium text-red-900">Error Loading Subscriber</h3>
                            <p class="text-sm text-red-700 mt-1">{{ error }}</p>
                        </div>
                    </div>
                    <button @click="router.back()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Go Back
                    </button>
                </div>

                <!-- Main Content -->
                <div v-else-if="subscriber">
                    <!-- Header -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <button @click="router.back()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                                    <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                </button>
                                <div class="flex items-center">
                                    <div class="h-14 w-14 bg-indigo-100 rounded-full flex items-center justify-center mr-4">
                                        <span class="text-indigo-600 font-bold text-xl">{{ subscriber.username.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <div>
                                        <h1 class="text-3xl font-bold text-gray-900">{{ subscriber.username }}</h1>
                                        <p class="text-sm text-gray-500 mt-1">
                                            ID: {{ subscriber.id }} | Member for {{ subscriber.stats?.member_days || 0 }} days
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <button @click="toggleStatus" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <span class="w-2 h-2 rounded-full mr-2" :class="subscriber.enabled ? 'bg-green-500' : 'bg-gray-400'"></span>
                                    {{ subscriber.enabled ? 'Disable' : 'Enable' }}
                                </button>
                                <button @click="showResetPasswordModal = true" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                    </svg>
                                    Reset Password
                                </button>
                                <button @click="openEditModal" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Edit
                                </button>
                                <div class="relative" ref="actionMenuRef">
                                    <button @click="showActionMenu = !showActionMenu" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                        Actions
                                        <svg class="h-4 w-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div v-if="showActionMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200">
                                        <button @click="openCreateSubscriptionModal" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Add Subscription
                                        </button>
                                        <button v-if="!subscriber.stats?.has_trial" @click="openCreateTrialModal" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Create Trial
                                        </button>
                                        <hr class="my-1">
                                        <button @click="confirmDelete" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            Delete Subscriber
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div class="mb-6 flex items-center space-x-2">
                        <span :class="['inline-flex items-center px-3 py-1 rounded-full text-sm font-medium', subscriber.enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                            <span class="w-2 h-2 rounded-full mr-2" :class="subscriber.enabled ? 'bg-green-600' : 'bg-red-600'"></span>
                            {{ subscriber.enabled ? 'Active' : 'Inactive' }}
                        </span>
                        <span v-if="subscriber.stats?.trial_active" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Trial Active
                        </span>
                        <span v-if="subscriber.stats?.active_subscriptions > 0" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ subscriber.stats.active_subscriptions }} Active Subscription(s)
                        </span>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
                        <div class="bg-white rounded-lg shadow p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-indigo-100 rounded-md p-2">
                                    <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-xs font-medium text-gray-500">Active Subs</p>
                                    <p class="text-lg font-bold text-indigo-600">{{ subscriber.stats?.active_subscriptions || 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-gray-100 rounded-md p-2">
                                    <svg class="h-5 w-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-xs font-medium text-gray-500">Total Subs</p>
                                    <p class="text-lg font-bold text-gray-600">{{ subscriber.stats?.total_subscriptions || 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-purple-100 rounded-md p-2">
                                    <svg class="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-xs font-medium text-gray-500">Trial</p>
                                    <p class="text-lg font-bold" :class="subscriber.stats?.trial_active ? 'text-purple-600' : 'text-gray-400'">
                                        {{ subscriber.stats?.trial_active ? 'Active' : subscriber.stats?.has_trial ? 'Expired' : 'None' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-100 rounded-md p-2">
                                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-xs font-medium text-gray-500">Connections</p>
                                    <p class="text-lg font-bold text-blue-600">{{ subscriber.stats?.total_connections || 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-100 rounded-md p-2">
                                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-xs font-medium text-gray-500">Member Days</p>
                                    <p class="text-lg font-bold text-green-600">{{ subscriber.stats?.member_days || 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-yellow-100 rounded-md p-2">
                                    <svg class="h-5 w-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-xs font-medium text-gray-500">Last Active</p>
                                    <p class="text-sm font-bold text-yellow-600">{{ formatTimeAgo(subscriber.stats?.last_connection) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="mb-6">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <button @click="activeTab = 'overview'" :class="['py-4 px-1 border-b-2 font-medium text-sm transition-colors', activeTab === 'overview' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                    Overview
                                </button>
                                <button @click="activeTab = 'subscriptions'" :class="['py-4 px-1 border-b-2 font-medium text-sm transition-colors', activeTab === 'subscriptions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                    Subscriptions ({{ subscriber.subscriptions?.length || 0 }})
                                </button>
                                <button @click="activeTab = 'trial'" :class="['py-4 px-1 border-b-2 font-medium text-sm transition-colors', activeTab === 'trial' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                    Trial {{ subscriber.trial ? '(1)' : '' }}
                                </button>
                                <button @click="activeTab = 'activity'" :class="['py-4 px-1 border-b-2 font-medium text-sm transition-colors', activeTab === 'activity' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                    Activity
                                </button>
                            </nav>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div>
                        <!-- Overview Tab -->
                        <div v-show="activeTab === 'overview'" class="space-y-6">
                            <!-- Personal Information -->
                            <div class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                    <svg class="h-5 w-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    Personal Information
                                </h3>
                                <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <dt class="text-xs font-medium text-gray-500 uppercase">Username</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ subscriber.username }}</dd>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <dt class="text-xs font-medium text-gray-500 uppercase">Email</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ subscriber.email || 'Not provided' }}</dd>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <dt class="text-xs font-medium text-gray-500 uppercase">Phone</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ subscriber.phone || 'Not provided' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Location Information -->
                            <div class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                    <svg class="h-5 w-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Location Information
                                </h3>
                                <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <dt class="text-xs font-medium text-gray-500 uppercase">Country</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ subscriber.country || 'Not provided' }}</dd>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <dt class="text-xs font-medium text-gray-500 uppercase">City</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ subscriber.city || 'Not provided' }}</dd>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <dt class="text-xs font-medium text-gray-500 uppercase">Address</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ subscriber.address || 'Not provided' }}</dd>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <dt class="text-xs font-medium text-gray-500 uppercase">Postal Code</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ subscriber.postal_code || 'Not provided' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Account Details -->
                            <div class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                    <svg class="h-5 w-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Account Details
                                </h3>
                                <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <dt class="text-xs font-medium text-gray-500 uppercase">Account Status</dt>
                                        <dd class="mt-1">
                                            <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', subscriber.enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                                {{ subscriber.enabled ? 'Active' : 'Inactive' }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <dt class="text-xs font-medium text-gray-500 uppercase">Created At</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ formatDateTime(subscriber.created_at) }}</dd>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <dt class="text-xs font-medium text-gray-500 uppercase">Last Updated</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ formatDateTime(subscriber.updated_at) }}</dd>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <dt class="text-xs font-medium text-gray-500 uppercase">Last Connection</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ subscriber.stats?.last_connection ? formatDateTime(subscriber.stats.last_connection) : 'Never' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Notes -->
                            <div v-if="subscriber.notes" class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                    <svg class="h-5 w-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Notes
                                </h3>
                                <p class="text-sm text-gray-700 whitespace-pre-wrap bg-gray-50 rounded-lg p-4">{{ subscriber.notes }}</p>
                            </div>
                        </div>

                        <!-- Subscriptions Tab -->
                        <div v-show="activeTab === 'subscriptions'">
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                    <h3 class="text-lg font-medium text-gray-900">Subscriptions</h3>
                                    <button @click="openCreateSubscriptionModal" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add Subscription
                                    </button>
                                </div>
                                <div v-if="!subscriber.subscriptions?.length" class="text-center py-12">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No subscriptions</h3>
                                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new subscription.</p>
                                </div>
                                <div v-else class="divide-y divide-gray-200">
                                    <div v-for="sub in subscriber.subscriptions" :key="sub.id" class="p-6 hover:bg-gray-50 transition-colors">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-4">
                                                <div class="flex-shrink-0">
                                                    <div :class="['h-10 w-10 rounded-full flex items-center justify-center', sub.is_expired ? 'bg-red-100' : sub.is_active ? 'bg-green-100' : 'bg-gray-100']">
                                                        <svg :class="['h-5 w-5', sub.is_expired ? 'text-red-600' : sub.is_active ? 'text-green-600' : 'text-gray-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">{{ sub.package_name }}</p>
                                                    <p class="text-xs text-gray-500">Expires: {{ formatDate(sub.expire_date) }}</p>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-4">
                                                <div class="text-right">
                                                    <span v-if="sub.is_expired" class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Expired</span>
                                                    <span v-else-if="sub.is_expiring_soon" class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Expiring Soon</span>
                                                    <span v-else-if="sub.is_active" class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Active</span>
                                                    <span v-else class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                                </div>
                                                <router-link :to="`/subscribers/subscriptions/${sub.id}`" class="text-indigo-600 hover:text-indigo-900">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </router-link>
                                            </div>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div>
                                                <span class="text-gray-500">Connections:</span>
                                                <span class="ml-1 text-gray-900">{{ sub.connection_count }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Max Concurrent:</span>
                                                <span class="ml-1 text-gray-900">{{ sub.max_concurrent_connections }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Auto Renew:</span>
                                                <span class="ml-1 text-gray-900">{{ sub.auto_renew ? 'Yes' : 'No' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Last Connected:</span>
                                                <span class="ml-1 text-gray-900">{{ sub.last_connected ? formatTimeAgo(sub.last_connected) : 'Never' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Trial Tab -->
                        <div v-show="activeTab === 'trial'">
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                    <h3 class="text-lg font-medium text-gray-900">Trial</h3>
                                    <button v-if="!subscriber.trial" @click="openCreateTrialModal" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Create Trial
                                    </button>
                                </div>
                                <div v-if="!subscriber.trial" class="text-center py-12">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No trial</h3>
                                    <p class="mt-1 text-sm text-gray-500">This subscriber has no trial period.</p>
                                </div>
                                <div v-else class="p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center space-x-3">
                                            <div :class="['h-12 w-12 rounded-full flex items-center justify-center', subscriber.trial.is_expired ? 'bg-red-100' : 'bg-purple-100']">
                                                <svg :class="['h-6 w-6', subscriber.trial.is_expired ? 'text-red-600' : 'text-purple-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-lg font-medium text-gray-900">{{ subscriber.trial.package_name }}</p>
                                                <p class="text-sm text-gray-500">{{ subscriber.trial.duration_hours }} hour trial</p>
                                            </div>
                                        </div>
                                        <span v-if="subscriber.trial.is_expired" class="px-3 py-1 text-sm rounded-full bg-red-100 text-red-800">Expired</span>
                                        <span v-else-if="subscriber.trial.is_active" class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-800">Active - {{ subscriber.trial.remaining_hours }}h remaining</span>
                                        <span v-else class="px-3 py-1 text-sm rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm bg-gray-50 rounded-lg p-4">
                                        <div>
                                            <span class="text-gray-500 block">Started</span>
                                            <span class="text-gray-900 font-medium">{{ formatDateTime(subscriber.trial.started_at) }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 block">Duration</span>
                                            <span class="text-gray-900 font-medium">{{ subscriber.trial.duration_hours }} hours</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 block">Connections</span>
                                            <span class="text-gray-900 font-medium">{{ subscriber.trial.connection_count }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 block">Converted</span>
                                            <span class="text-gray-900 font-medium">{{ subscriber.trial.converted_to_subscription ? 'Yes' : 'No' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Tab -->
                        <div v-show="activeTab === 'activity'">
                            <div class="bg-white shadow rounded-lg p-6">
                                <div v-if="loadingActivity" class="text-center py-12">
                                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                                </div>
                                <div v-else-if="activities.length === 0" class="text-center py-12">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No activity</h3>
                                    <p class="mt-1 text-sm text-gray-500">No recent activity for this subscriber.</p>
                                </div>
                                <div v-else class="flow-root">
                                    <ul class="-mb-8">
                                        <li v-for="(activity, idx) in activities" :key="activity.id">
                                            <div class="relative pb-8">
                                                <span v-if="idx !== activities.length - 1" class="absolute top-5 left-5 -ml-px h-full w-0.5 bg-gray-200"></span>
                                                <div class="relative flex items-start space-x-3">
                                                    <div>
                                                        <div class="relative px-1">
                                                            <div class="h-8 w-8 bg-indigo-100 rounded-full ring-8 ring-white flex items-center justify-center">
                                                                <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                                </svg>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <div>
                                                            <div class="text-sm">
                                                                <span class="font-medium text-gray-900">{{ activity.action }}</span>
                                                            </div>
                                                            <p v-if="activity.description" class="mt-0.5 text-sm text-gray-500">{{ activity.description }}</p>
                                                            <p class="mt-0.5 text-xs text-gray-400">{{ formatDateTime(activity.created_at) }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Subscriber Modal -->
        <div v-if="showEditModal && subscriber" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 overflow-y-auto p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full my-8 max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Edit Subscriber</h3>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="updateSubscriber" class="px-6 py-4">
                    <!-- Account Information -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-gray-800 mb-4">Account Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
                                <input v-model="editForm.username" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input v-model="editForm.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input v-model="editForm.phone" type="tel" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                        </div>
                    </div>
                    <!-- Location Information -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-gray-800 mb-4">Location Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                <input v-model="editForm.country" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                <input v-model="editForm.city" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                <input v-model="editForm.address" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                                <input v-model="editForm.postal_code" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                        </div>
                    </div>
                    <!-- Additional Information -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-gray-800 mb-4">Additional Information</h4>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea v-model="editForm.notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                    </div>
                    <!-- Status -->
                    <div class="mb-6">
                        <label class="flex items-center">
                            <input v-model="editForm.enabled" type="checkbox" class="h-4 w-4 text-indigo-600 rounded" />
                            <span class="ml-2 text-sm text-gray-700">Account Active</span>
                        </label>
                    </div>
                    <!-- Actions -->
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" :disabled="submitting" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {{ submitting ? 'Saving...' : 'Update Subscriber' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Create Subscription Modal -->
        <div v-if="showCreateSubscriptionModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
                <div class="border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Create Subscription</h3>
                    <button @click="showCreateSubscriptionModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="createSubscription" class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Package <span class="text-red-500">*</span></label>
                        <select v-model="subscriptionForm.package_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select a package</option>
                            <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }} - ${{ pkg.price }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input v-model="subscriptionForm.start_date" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Duration (days)</label>
                            <input v-model.number="subscriptionForm.duration_days" type="number" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Concurrent Connections</label>
                        <input v-model.number="subscriptionForm.max_concurrent_connections" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div class="flex items-center space-x-4">
                        <label class="flex items-center">
                            <input v-model="subscriptionForm.is_active" type="checkbox" class="h-4 w-4 text-indigo-600 rounded" />
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                        <label class="flex items-center">
                            <input v-model="subscriptionForm.auto_renew" type="checkbox" class="h-4 w-4 text-indigo-600 rounded" />
                            <span class="ml-2 text-sm text-gray-700">Auto Renew</span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea v-model="subscriptionForm.notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" @click="showCreateSubscriptionModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" :disabled="submitting" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {{ submitting ? 'Creating...' : 'Create Subscription' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Create Trial Modal -->
        <div v-if="showCreateTrialModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
                <div class="border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Create Trial</h3>
                    <button @click="showCreateTrialModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="createTrial" class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Package <span class="text-red-500">*</span></label>
                        <select v-model="trialForm.package_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select a package</option>
                            <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration (hours)</label>
                        <input v-model.number="trialForm.trial_duration_hours" type="number" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea v-model="trialForm.notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" @click="showCreateTrialModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" :disabled="submitting" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {{ submitting ? 'Creating...' : 'Create Trial' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reset Password Modal -->
        <div v-if="showResetPasswordModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Reset Password</h3>
                    <button @click="showResetPasswordModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="resetPassword" class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password <span class="text-red-500">*</span></label>
                        <input v-model="passwordForm.password" type="password" required minlength="6" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                        <p class="mt-1 text-xs text-gray-500">Minimum 6 characters</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
                        <input v-model="passwordForm.confirm" type="password" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" @click="showResetPasswordModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" :disabled="submitting" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {{ submitting ? 'Resetting...' : 'Reset Password' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 bg-red-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Delete Subscriber</h3>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mb-6">
                    Are you sure you want to delete <strong>{{ subscriber?.username }}</strong>? This will also delete all subscriptions and trials. This action cannot be undone.
                </p>
                <div class="flex justify-end space-x-3">
                    <button @click="showDeleteModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button @click="deleteSubscriber" :disabled="deleting" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50">
                        {{ deleting ? 'Deleting...' : 'Delete' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '../../components/AppLayout.vue';
import { subscribersAPI, subscriptionsAPI, trialsAPI, activitiesAPI, packagesAPI } from '../../services/api';
import { useToastStore } from '../../stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const subscriberId = route.params.id;

// Data
const subscriber = ref(null);
const activities = ref([]);
const packages = ref([]);

// State
const loading = ref(true);
const loadingActivity = ref(false);
const submitting = ref(false);
const deleting = ref(false);
const error = ref(null);

// UI
const activeTab = ref('overview');
const showActionMenu = ref(false);
const actionMenuRef = ref(null);

// Modals
const showEditModal = ref(false);
const showCreateSubscriptionModal = ref(false);
const showCreateTrialModal = ref(false);
const showResetPasswordModal = ref(false);
const showDeleteModal = ref(false);

// Forms
const editForm = ref({
    username: '',
    email: '',
    phone: '',
    country: '',
    city: '',
    address: '',
    postal_code: '',
    notes: '',
    enabled: true
});

const subscriptionForm = ref({
    package_id: '',
    start_date: new Date().toISOString().split('T')[0],
    duration_days: 30,
    max_concurrent_connections: 1,
    is_active: true,
    auto_renew: false,
    notes: ''
});

const trialForm = ref({
    package_id: '',
    trial_duration_hours: 24,
    notes: ''
});

const passwordForm = ref({
    password: '',
    confirm: ''
});

// Fetch subscriber data
const fetchSubscriber = async () => {
    loading.value = true;
    error.value = null;
    try {
        const response = await subscribersAPI.getOne(subscriberId);
        if (response.data.success) {
            subscriber.value = response.data.data;
            // Update edit form
            editForm.value = {
                username: subscriber.value.username,
                email: subscriber.value.email || '',
                phone: subscriber.value.phone || '',
                country: subscriber.value.country || '',
                city: subscriber.value.city || '',
                address: subscriber.value.address || '',
                postal_code: subscriber.value.postal_code || '',
                notes: subscriber.value.notes || '',
                enabled: subscriber.value.enabled === 1
            };
        } else {
            error.value = response.data.message || 'Failed to load subscriber';
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load subscriber details';
        toast.error('Failed to load subscriber', { details: error.value });
    } finally {
        loading.value = false;
    }
};

const fetchActivity = async () => {
    loadingActivity.value = true;
    try {
        const response = await activitiesAPI.getAll({ subscriber_id: subscriberId, per_page: 20 });
        if (response.data.success) {
            activities.value = response.data.data || [];
        }
    } catch (err) {
        console.error('Failed to load activity:', err);
    } finally {
        loadingActivity.value = false;
    }
};

const fetchPackages = async () => {
    try {
        const response = await packagesAPI.getAll({ active: '1' });
        if (response.data.success) {
            packages.value = response.data.data || [];
        }
    } catch (err) {
        console.error('Failed to load packages:', err);
    }
};

// Actions
const toggleStatus = async () => {
    try {
        await subscribersAPI.toggle(subscriberId);
        subscriber.value.enabled = subscriber.value.enabled ? 0 : 1;
        toast.success(subscriber.value.enabled ? 'Subscriber enabled' : 'Subscriber disabled');
    } catch (err) {
        toast.error('Failed to toggle status', { details: err.response?.data?.message || err.message });
    }
};

const openEditModal = () => {
    editForm.value = {
        username: subscriber.value.username,
        email: subscriber.value.email || '',
        phone: subscriber.value.phone || '',
        country: subscriber.value.country || '',
        city: subscriber.value.city || '',
        address: subscriber.value.address || '',
        postal_code: subscriber.value.postal_code || '',
        notes: subscriber.value.notes || '',
        enabled: subscriber.value.enabled === 1
    };
    showEditModal.value = true;
    showActionMenu.value = false;
};

const updateSubscriber = async () => {
    submitting.value = true;
    try {
        const data = {
            ...editForm.value,
            enabled: editForm.value.enabled ? 1 : 0
        };
        await subscribersAPI.update(subscriberId, data);
        toast.success('Subscriber updated successfully');
        showEditModal.value = false;
        await fetchSubscriber();
    } catch (err) {
        toast.error('Failed to update subscriber', { details: err.response?.data?.message || err.message });
    } finally {
        submitting.value = false;
    }
};

const openCreateSubscriptionModal = () => {
    subscriptionForm.value = {
        package_id: '',
        start_date: new Date().toISOString().split('T')[0],
        duration_days: 30,
        max_concurrent_connections: 1,
        is_active: true,
        auto_renew: false,
        notes: ''
    };
    showCreateSubscriptionModal.value = true;
    showActionMenu.value = false;
};

const createSubscription = async () => {
    submitting.value = true;
    try {
        const startDate = new Date(subscriptionForm.value.start_date);
        const expireDate = new Date(startDate);
        expireDate.setDate(expireDate.getDate() + subscriptionForm.value.duration_days);

        const data = {
            subscriber_id: subscriberId,
            package_id: subscriptionForm.value.package_id,
            expire_date: expireDate.toISOString().slice(0, 19).replace('T', ' '),
            max_concurrent_connections: subscriptionForm.value.max_concurrent_connections,
            is_active: subscriptionForm.value.is_active ? 1 : 0,
            auto_renew: subscriptionForm.value.auto_renew ? 1 : 0,
            notes: subscriptionForm.value.notes || null
        };

        await subscriptionsAPI.create(data);
        toast.success('Subscription created successfully');
        showCreateSubscriptionModal.value = false;
        await fetchSubscriber();
    } catch (err) {
        toast.error('Failed to create subscription', { details: err.response?.data?.message || err.message });
    } finally {
        submitting.value = false;
    }
};

const openCreateTrialModal = () => {
    trialForm.value = {
        package_id: '',
        trial_duration_hours: 24,
        notes: ''
    };
    showCreateTrialModal.value = true;
    showActionMenu.value = false;
};

const createTrial = async () => {
    submitting.value = true;
    try {
        const data = {
            subscriber_id: subscriberId,
            package_id: trialForm.value.package_id,
            trial_duration_hours: trialForm.value.trial_duration_hours,
            notes: trialForm.value.notes || null
        };

        await trialsAPI.create(data);
        toast.success('Trial created successfully');
        showCreateTrialModal.value = false;
        await fetchSubscriber();
    } catch (err) {
        toast.error('Failed to create trial', { details: err.response?.data?.message || err.message });
    } finally {
        submitting.value = false;
    }
};

const resetPassword = async () => {
    if (passwordForm.value.password !== passwordForm.value.confirm) {
        toast.error('Passwords do not match');
        return;
    }
    if (passwordForm.value.password.length < 6) {
        toast.error('Password must be at least 6 characters');
        return;
    }

    submitting.value = true;
    try {
        await subscribersAPI.resetPassword(subscriberId, passwordForm.value.password);
        toast.success('Password reset successfully');
        showResetPasswordModal.value = false;
        passwordForm.value = { password: '', confirm: '' };
    } catch (err) {
        toast.error('Failed to reset password', { details: err.response?.data?.message || err.message });
    } finally {
        submitting.value = false;
    }
};

const confirmDelete = () => {
    showDeleteModal.value = true;
    showActionMenu.value = false;
};

const deleteSubscriber = async () => {
    deleting.value = true;
    try {
        await subscribersAPI.delete(subscriberId);
        toast.success('Subscriber deleted successfully');
        router.push('/subscribers');
    } catch (err) {
        toast.error('Failed to delete subscriber', { details: err.response?.data?.message || err.message });
    } finally {
        deleting.value = false;
    }
};

// Utilities
const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
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

const formatTimeAgo = (dateStr) => {
    if (!dateStr) return 'Never';
    const date = new Date(dateStr);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);

    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
    return formatDate(dateStr);
};

// Close action menu on click outside
const handleClickOutside = (event) => {
    if (actionMenuRef.value && !actionMenuRef.value.contains(event.target)) {
        showActionMenu.value = false;
    }
};

// Watch for tab changes to load activity
watch(activeTab, (newTab) => {
    if (newTab === 'activity' && activities.value.length === 0) {
        fetchActivity();
    }
});

// Lifecycle
onMounted(async () => {
    document.addEventListener('click', handleClickOutside);
    await Promise.all([fetchSubscriber(), fetchPackages()]);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>
