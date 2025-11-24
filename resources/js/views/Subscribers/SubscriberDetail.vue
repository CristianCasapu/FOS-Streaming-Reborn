<template>
    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Loading State -->
                <div v-if="loading" class="flex items-center justify-center min-h-screen">
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
                                <button @click="router.back()" class="p-2 hover:bg-gray-100 rounded-lg">
                                    <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                </button>
                                <div>
                                    <h1 class="text-3xl font-bold text-gray-900">{{ subscriber.username }}</h1>
                                    <p class="text-sm text-gray-600 mt-1">Subscriber ID: {{ subscriber.id }}</p>
                                </div>
                            </div>
                            <div class="flex space-x-3">
                                <button @click="showEditModal = true" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Edit
                                </button>
                                <button @click="showCreateSubscriptionModal = true" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    New Subscription
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div class="mb-6">
                        <span :class="['inline-flex items-center px-3 py-1 rounded-full text-sm font-medium', subscriber.enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                            <span class="w-2 h-2 rounded-full mr-2" :class="subscriber.enabled ? 'bg-green-600' : 'bg-red-600'"></span>
                            {{ subscriber.enabled ? 'Active' : 'Inactive' }}
                        </span>
                        <span v-if="subscriber.is_reseller" class="ml-2 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                            Reseller
                        </span>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-indigo-100 rounded-md p-3">
                                    <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Active Subscriptions</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ subscriptions.filter(s => s.is_active && !s.is_expired).length }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Active Trials</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ trials.filter(t => t.is_active && !t.is_expired).length }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Max Connections</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ subscriber.max_connections || 'Unlimited' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Member Since</p>
                                    <p class="text-lg font-bold text-gray-900">{{ formatDate(subscriber.created_at) }}</p>
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
                                <button @click="activeTab = 'subscriptions'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'subscriptions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                    Subscriptions ({{ subscriptions.length }})
                                </button>
                                <button @click="activeTab = 'trials'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'trials' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                    Trials ({{ trials.length }})
                                </button>
                                <button @click="activeTab = 'activity'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'activity' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
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
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h3>
                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Username</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscriber.username }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscriber.email || 'Not provided' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Phone</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscriber.phone || 'Not provided' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Country</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscriber.country || 'Not provided' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">City</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscriber.city || 'Not provided' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">ISP</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscriber.isp || 'Not provided' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Account Settings -->
                            <div class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Account Settings</h3>
                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Package/Plan</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscriber.package || 'No package assigned' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Max Connections</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscriber.max_connections || 'Unlimited' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Expiration Date</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ subscriber.expiration_date ? formatDate(subscriber.expiration_date) : 'No expiration' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Account Status</dt>
                                        <dd class="mt-1">
                                            <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', subscriber.enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                                {{ subscriber.enabled ? 'Active' : 'Inactive' }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Notes -->
                            <div v-if="subscriber.notes" class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Notes</h3>
                                <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ subscriber.notes }}</p>
                            </div>
                        </div>

                        <!-- Subscriptions Tab -->
                        <div v-show="activeTab === 'subscriptions'">
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div v-if="loadingSubscriptions" class="text-center py-12">
                                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                                </div>
                                <div v-else-if="subscriptions.length === 0" class="text-center py-12">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No subscriptions</h3>
                                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new subscription.</p>
                                    <button @click="showCreateSubscriptionModal = true" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                        Create Subscription
                                    </button>
                                </div>
                                <table v-else class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expire Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="sub in subscriptions" :key="sub.id" class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ sub.package_name || 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ formatDate(sub.start_date) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ formatDate(sub.expire_date) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span v-if="sub.is_expired" class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Expired</span>
                                                <span v-else-if="sub.is_active" class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Active</span>
                                                <span v-else class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                                <router-link :to="`/subscribers/subscriptions/${sub.id}`" class="text-indigo-600 hover:text-indigo-900">View</router-link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Trials Tab -->
                        <div v-show="activeTab === 'trials'">
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div v-if="loadingTrials" class="text-center py-12">
                                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                                </div>
                                <div v-else-if="trials.length === 0" class="text-center py-12">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No trials</h3>
                                    <p class="mt-1 text-sm text-gray-500">This subscriber has no trial periods.</p>
                                </div>
                                <table v-else class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">End Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="trial in trials" :key="trial.id" class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ trial.package_name || 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ formatDate(trial.start_date) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ formatDate(trial.end_date) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span v-if="trial.is_expired" class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Expired</span>
                                                <span v-else-if="trial.is_active" class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Active</span>
                                                <span v-else class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                                <router-link :to="`/subscribers/trials/${trial.id}`" class="text-indigo-600 hover:text-indigo-900">View</router-link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
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
                                                            <p class="mt-0.5 text-sm text-gray-500">{{ activity.description }}</p>
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
        <div v-if="showEditModal && subscriber" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 overflow-y-auto">
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full my-8">
                <h3 class="text-lg font-medium mb-4">Edit Subscriber</h3>
                <form @submit.prevent="updateSubscriber">
                    <div class="space-y-4 max-h-[calc(100vh-200px)] overflow-y-auto pr-2">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Username</label>
                                <input v-model="editForm.username" type="text" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <input v-model="editForm.email" type="email" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Phone</label>
                                <input v-model="editForm.phone" type="tel" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Max Connections</label>
                                <input v-model.number="editForm.max_connections" type="number" min="1" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea v-model="editForm.notes" rows="3" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center">
                                <input v-model="editForm.enabled" type="checkbox" class="h-4 w-4 text-indigo-600 rounded" />
                                <label class="ml-2 text-sm text-gray-900">Active</label>
                            </div>
                            <div class="flex items-center">
                                <input v-model="editForm.is_reseller" type="checkbox" class="h-4 w-4 text-indigo-600 rounded" />
                                <label class="ml-2 text-sm text-gray-900">Is Reseller</label>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Update</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Create Subscription Modal -->
        <div v-if="showCreateSubscriptionModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium mb-4">Create New Subscription</h3>
                <form @submit.prevent="createSubscription">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Package</label>
                            <select v-model="subscriptionForm.package_id" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="">Select a package</option>
                                <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input v-model="subscriptionForm.start_date" type="date" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Duration (days)</label>
                            <input v-model.number="subscriptionForm.duration_days" type="number" min="1" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" />
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="showCreateSubscriptionModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '../../components/AppLayout.vue';
import { subscribersAPI, subscriptionsAPI, trialsAPI, activitiesAPI, packagesAPI } from '../../services/api';

const route = useRoute();
const router = useRouter();
const subscriberId = route.params.id;

const subscriber = ref(null);
const subscriptions = ref([]);
const trials = ref([]);
const activities = ref([]);
const packages = ref([]);

const loading = ref(true);
const loadingSubscriptions = ref(false);
const loadingTrials = ref(false);
const loadingActivity = ref(false);
const error = ref(null);

const activeTab = ref('overview');
const showEditModal = ref(false);
const showCreateSubscriptionModal = ref(false);

const editForm = ref({
    username: '',
    email: '',
    phone: '',
    max_connections: 5,
    notes: '',
    enabled: true,
    is_reseller: false
});

const subscriptionForm = ref({
    package_id: '',
    start_date: new Date().toISOString().split('T')[0],
    duration_days: 30
});

const fetchSubscriber = async () => {
    loading.value = true;
    error.value = null;
    try {
        const response = await subscribersAPI.getOne(subscriberId);
        if (response.data.success) {
            subscriber.value = response.data.data;
            editForm.value = {
                username: subscriber.value.username,
                email: subscriber.value.email || '',
                phone: subscriber.value.phone || '',
                max_connections: subscriber.value.max_connections || 5,
                notes: subscriber.value.notes || '',
                enabled: subscriber.value.enabled === 1,
                is_reseller: subscriber.value.is_reseller === 1
            };
        } else {
            error.value = response.data.message || 'Failed to load subscriber';
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load subscriber details';
        console.error('Error fetching subscriber:', err);
    } finally {
        loading.value = false;
    }
};

const fetchSubscriptions = async () => {
    loadingSubscriptions.value = true;
    try {
        const response = await subscriptionsAPI.bySubscriber(subscriberId);
        if (response.data.success) {
            subscriptions.value = response.data.data || [];
        }
    } catch (err) {
        console.error('Error fetching subscriptions:', err);
    } finally {
        loadingSubscriptions.value = false;
    }
};

const fetchTrials = async () => {
    loadingTrials.value = true;
    try {
        const response = await trialsAPI.bySubscriber(subscriberId);
        if (response.data.success) {
            trials.value = response.data.data || [];
        }
    } catch (err) {
        console.error('Error fetching trials:', err);
    } finally {
        loadingTrials.value = false;
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
        console.error('Error fetching activity:', err);
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
        console.error('Error fetching packages:', err);
    }
};

const updateSubscriber = async () => {
    try {
        const data = {
            ...editForm.value,
            enabled: editForm.value.enabled ? 1 : 0,
            is_reseller: editForm.value.is_reseller ? 1 : 0
        };
        const response = await subscribersAPI.update(subscriberId, data);
        if (response.data.success) {
            showEditModal.value = false;
            await fetchSubscriber();
            alert('Subscriber updated successfully');
        }
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to update subscriber');
        console.error('Error updating subscriber:', err);
    }
};

const createSubscription = async () => {
    try {
        // Calculate expire_date from start_date + duration_days
        const startDate = new Date(subscriptionForm.value.start_date);
        const expireDate = new Date(startDate);
        expireDate.setDate(expireDate.getDate() + parseInt(subscriptionForm.value.duration_days));

        // Format as MySQL datetime: YYYY-MM-DD HH:mm:ss
        const formattedExpireDate = expireDate.toISOString().slice(0, 19).replace('T', ' ');

        const data = {
            subscriber_id: subscriberId,
            package_id: subscriptionForm.value.package_id,
            expire_date: formattedExpireDate,
            max_concurrent_connections: 1, // Default value
            is_active: 1,
            auto_renew: 0
        };

        const response = await subscriptionsAPI.create(data);
        if (response.data.success) {
            showCreateSubscriptionModal.value = false;
            await fetchSubscriptions();
            alert('Subscription created successfully');
        }
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to create subscription');
        console.error('Error creating subscription:', err);
    }
};

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

onMounted(async () => {
    await fetchSubscriber();
    await Promise.all([
        fetchSubscriptions(),
        fetchTrials(),
        fetchActivity(),
        fetchPackages()
    ]);
});
</script>
