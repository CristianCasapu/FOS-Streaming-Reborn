<template>
    <AppLayout>
        <div class="px-4 py-6 sm:px-0">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Subscribers</h1>
                    <p class="mt-2 text-sm text-gray-600">Manage subscriber accounts and their subscriptions</p>
                </div>
                <div class="flex space-x-3">
                    <button @click="exportSubscribers" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export
                    </button>
                    <button @click="openCreateModal" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Subscriber
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-indigo-100 rounded-md p-2">
                            <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs font-medium text-gray-500">Total</p>
                            <p class="text-lg font-bold text-gray-900">{{ stats.total }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-md p-2">
                            <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs font-medium text-gray-500">Active</p>
                            <p class="text-lg font-bold text-green-600">{{ stats.active }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-gray-100 rounded-md p-2">
                            <svg class="h-5 w-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs font-medium text-gray-500">Inactive</p>
                            <p class="text-lg font-bold text-gray-600">{{ stats.inactive }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-md p-2">
                            <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs font-medium text-gray-500">Subscribed</p>
                            <p class="text-lg font-bold text-blue-600">{{ stats.with_active_subscriptions }}</p>
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
                            <p class="text-xs font-medium text-gray-500">Trials</p>
                            <p class="text-lg font-bold text-purple-600">{{ stats.with_trials }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 rounded-md p-2">
                            <svg class="h-5 w-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs font-medium text-gray-500">New (7d)</p>
                            <p class="text-lg font-bold text-yellow-600">{{ stats.recently_created }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white shadow rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input v-model="filters.search" @input="debouncedSearch" type="text" placeholder="Search by username, email, phone..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select v-model="filters.enabled" @change="fetchSubscribers" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                        <select v-model="filters.country" @change="fetchSubscribers" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All Countries</option>
                            <option v-for="country in countries" :key="country.country" :value="country.country">
                                {{ country.country }} ({{ country.count }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subscriptions</label>
                        <select v-model="filters.has_subscriptions" @change="fetchSubscribers" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All</option>
                            <option value="1">Has Subscriptions</option>
                            <option value="0">No Subscriptions</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
                        <select v-model="filters.per_page" @change="fetchSubscribers" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            <div v-if="selectedIds.length > 0" class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-indigo-700">
                        <strong>{{ selectedIds.length }}</strong> subscriber(s) selected
                    </span>
                    <div class="flex space-x-3">
                        <button @click="bulkEnable" class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700">
                            Enable Selected
                        </button>
                        <button @click="bulkDisable" class="px-3 py-1.5 text-sm bg-gray-600 text-white rounded-md hover:bg-gray-700">
                            Disable Selected
                        </button>
                        <button @click="confirmBulkDelete" class="px-3 py-1.5 text-sm bg-red-600 text-white rounded-md hover:bg-red-700">
                            Delete Selected
                        </button>
                        <button @click="clearSelection" class="px-3 py-1.5 text-sm bg-white text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">
                            Clear Selection
                        </button>
                    </div>
                </div>
            </div>

            <!-- Subscribers Table -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div v-if="loading" class="p-12 text-center">
                    <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-gray-600">Loading subscribers...</p>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">
                                    <input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
                                </th>
                                <th @click="sortBy('username')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                    <div class="flex items-center">
                                        Username
                                        <span v-if="filters.sort_by === 'username'" class="ml-1">{{ filters.sort_order === 'asc' ? '↑' : '↓' }}</span>
                                    </div>
                                </th>
                                <th @click="sortBy('email')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                    <div class="flex items-center">
                                        Contact
                                        <span v-if="filters.sort_by === 'email'" class="ml-1">{{ filters.sort_order === 'asc' ? '↑' : '↓' }}</span>
                                    </div>
                                </th>
                                <th @click="sortBy('country')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                    <div class="flex items-center">
                                        Location
                                        <span v-if="filters.sort_by === 'country'" class="ml-1">{{ filters.sort_order === 'asc' ? '↑' : '↓' }}</span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subscriptions</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trial</th>
                                <th @click="sortBy('enabled')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                    <div class="flex items-center">
                                        Status
                                        <span v-if="filters.sort_by === 'enabled'" class="ml-1">{{ filters.sort_order === 'asc' ? '↑' : '↓' }}</span>
                                    </div>
                                </th>
                                <th @click="sortBy('created_at')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                    <div class="flex items-center">
                                        Created
                                        <span v-if="filters.sort_by === 'created_at'" class="ml-1">{{ filters.sort_order === 'asc' ? '↑' : '↓' }}</span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="subscriber in subscribers" :key="subscriber.id" class="hover:bg-gray-50" :class="{ 'bg-indigo-50': selectedIds.includes(subscriber.id) }">
                                <td class="px-4 py-4">
                                    <input type="checkbox" :checked="selectedIds.includes(subscriber.id)" @change="toggleSelect(subscriber.id)" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                            <span class="text-indigo-600 font-semibold text-sm">{{ subscriber.username.charAt(0).toUpperCase() }}</span>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">{{ subscriber.username }}</div>
                                            <div class="text-xs text-gray-500">ID: {{ subscriber.id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ subscriber.email || '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ subscriber.phone || '—' }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ subscriber.country || '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ subscriber.city || '' }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-1">
                                        <span class="px-2 py-0.5 text-xs rounded-full" :class="subscriber.active_subscriptions_count > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'">
                                            {{ subscriber.active_subscriptions_count }} active
                                        </span>
                                        <span v-if="subscriber.subscriptions_count > subscriber.active_subscriptions_count" class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">
                                            {{ subscriber.subscriptions_count - subscriber.active_subscriptions_count }} expired
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span v-if="subscriber.trial_active" class="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-800">Active</span>
                                    <span v-else-if="subscriber.has_trial" class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">Expired</span>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <button @click="toggleStatus(subscriber)" class="group">
                                        <span :class="['px-2 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full cursor-pointer transition-colors', subscriber.enabled ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-100 text-gray-800 hover:bg-gray-200']">
                                            <span class="w-2 h-2 rounded-full mr-1.5" :class="subscriber.enabled ? 'bg-green-500' : 'bg-gray-400'"></span>
                                            {{ subscriber.enabled ? 'Active' : 'Inactive' }}
                                        </span>
                                    </button>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDate(subscriber.created_at) }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <router-link :to="`/subscribers/${subscriber.id}`" class="text-blue-600 hover:text-blue-900" title="View Details">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </router-link>
                                        <button @click="editSubscriber(subscriber)" class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="confirmDelete(subscriber)" class="text-red-600 hover:text-red-900" title="Delete">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="subscribers.length === 0">
                                <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No subscribers found</h3>
                                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new subscriber.</p>
                                    <button @click="openCreateModal" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        Add Subscriber
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="pagination.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing <span class="font-medium">{{ ((pagination.current_page - 1) * pagination.per_page) + 1 }}</span> to <span class="font-medium">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}</span> of <span class="font-medium">{{ pagination.total }}</span> results
                        </div>
                        <div class="flex space-x-1">
                            <button @click="goToPage(1)" :disabled="pagination.current_page === 1" class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50">First</button>
                            <button @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50">Previous</button>
                            <span class="px-3 py-1 text-sm text-gray-600">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
                            <button @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50">Next</button>
                            <button @click="goToPage(pagination.last_page)" :disabled="pagination.current_page === pagination.last_page" class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50">Last</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 overflow-y-auto p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full my-8 max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">{{ isEditing ? 'Edit Subscriber' : 'Create Subscriber' }}</h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="submitForm" class="px-6 py-4">
                    <!-- Account Information -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="h-5 w-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Account Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
                                <input v-model="form.username" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="Enter username" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input v-model="form.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="subscriber@example.com" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Password {{ isEditing ? '(leave blank to keep current)' : '' }} <span v-if="!isEditing" class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input v-model="form.password" :type="showPassword ? 'text' : 'password'" :required="!isEditing" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 pr-10" placeholder="Enter password" />
                                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600">
                                        <svg v-if="showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                        </svg>
                                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                <input v-model="form.phone" type="tel" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="+1 234 567 8900" />
                            </div>
                        </div>
                    </div>

                    <!-- Location Information -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="h-5 w-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Location Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                <input v-model="form.country" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="United States" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                <input v-model="form.city" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="New York" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                <input v-model="form.address" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="123 Main Street, Apt 4B" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                                <input v-model="form.postal_code" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="10001" />
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="h-5 w-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Additional Information
                        </h4>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea v-model="form.notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="Any additional notes about this subscriber..."></textarea>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="h-5 w-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Account Status
                        </h4>
                        <div class="flex items-center space-x-6">
                            <label class="flex items-center">
                                <input v-model="form.enabled" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" />
                                <span class="ml-2 text-sm text-gray-700">Account Active</span>
                            </label>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" @click="closeModal" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" :disabled="submitting" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <span v-if="submitting" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Saving...
                            </span>
                            <span v-else>{{ isEditing ? 'Update Subscriber' : 'Create Subscriber' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 bg-red-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">{{ deleteTarget.bulk ? 'Delete Multiple Subscribers' : 'Delete Subscriber' }}</h3>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mb-6">
                    <template v-if="deleteTarget.bulk">
                        Are you sure you want to delete <strong>{{ deleteTarget.count }}</strong> subscriber(s)? This will also delete all their subscriptions and trials. This action cannot be undone.
                    </template>
                    <template v-else>
                        Are you sure you want to delete subscriber "<strong>{{ deleteTarget.username }}</strong>"? This will also delete all their subscriptions and trials. This action cannot be undone.
                    </template>
                </p>
                <div class="flex justify-end space-x-3">
                    <button @click="showDeleteModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button @click="executeDelete" :disabled="deleting" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50">
                        <span v-if="deleting">Deleting...</span>
                        <span v-else>Delete</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { subscribersAPI } from '../../services/api';
import { useToastStore } from '../../stores/toast';

const toast = useToastStore();

// Data
const subscribers = ref([]);
const countries = ref([]);
const loading = ref(false);
const submitting = ref(false);
const deleting = ref(false);

// Stats
const stats = ref({
    total: 0,
    active: 0,
    inactive: 0,
    with_subscriptions: 0,
    with_active_subscriptions: 0,
    with_trials: 0,
    recently_created: 0,
    recently_active: 0,
});

// Pagination
const pagination = ref({
    total: 0,
    per_page: 20,
    current_page: 1,
    last_page: 1
});

// Filters
const filters = reactive({
    search: '',
    enabled: '',
    country: '',
    has_subscriptions: '',
    has_trials: '',
    sort_by: 'created_at',
    sort_order: 'desc',
    per_page: 20,
    page: 1
});

// Selection
const selectedIds = ref([]);

// Modals
const showModal = ref(false);
const showDeleteModal = ref(false);
const isEditing = ref(false);
const showPassword = ref(false);

// Form
const defaultForm = {
    id: null,
    username: '',
    email: '',
    password: '',
    phone: '',
    country: '',
    city: '',
    address: '',
    postal_code: '',
    notes: '',
    enabled: true
};

const form = ref({ ...defaultForm });

// Delete target
const deleteTarget = ref({
    id: null,
    username: '',
    bulk: false,
    count: 0
});

// Computed
const isAllSelected = computed(() => {
    return subscribers.value.length > 0 && selectedIds.value.length === subscribers.value.length;
});

// Methods
const fetchSubscribers = async () => {
    loading.value = true;
    try {
        const params = {
            page: filters.page,
            per_page: filters.per_page,
            sort_by: filters.sort_by,
            sort_order: filters.sort_order
        };
        if (filters.search) params.search = filters.search;
        if (filters.enabled !== '') params.enabled = filters.enabled;
        if (filters.country) params.country = filters.country;
        if (filters.has_subscriptions !== '') params.has_subscriptions = filters.has_subscriptions;
        if (filters.has_trials !== '') params.has_trials = filters.has_trials;

        const response = await subscribersAPI.getAll(params);
        subscribers.value = response.data.data;
        pagination.value = response.data.pagination;
        // Clear selection when data changes
        selectedIds.value = selectedIds.value.filter(id =>
            subscribers.value.some(s => s.id === id)
        );
    } catch (error) {
        toast.error('Failed to load subscribers', {
            details: error.response?.data?.message || error.message
        });
    } finally {
        loading.value = false;
    }
};

const fetchStats = async () => {
    try {
        const response = await subscribersAPI.getStats();
        stats.value = response.data.data;
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
};

const fetchCountries = async () => {
    try {
        const response = await subscribersAPI.getCountries();
        countries.value = response.data.data;
    } catch (error) {
        console.error('Failed to load countries:', error);
    }
};

let searchTimeout;
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        filters.page = 1;
        fetchSubscribers();
    }, 400);
};

const sortBy = (column) => {
    if (filters.sort_by === column) {
        filters.sort_order = filters.sort_order === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort_by = column;
        filters.sort_order = 'desc';
    }
    fetchSubscribers();
};

const goToPage = (page) => {
    filters.page = page;
    fetchSubscribers();
};

// Selection methods
const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = subscribers.value.map(s => s.id);
    }
};

const toggleSelect = (id) => {
    const index = selectedIds.value.indexOf(id);
    if (index > -1) {
        selectedIds.value.splice(index, 1);
    } else {
        selectedIds.value.push(id);
    }
};

const clearSelection = () => {
    selectedIds.value = [];
};

// Modal methods
const openCreateModal = () => {
    isEditing.value = false;
    form.value = { ...defaultForm };
    showPassword.value = false;
    showModal.value = true;
};

const editSubscriber = (subscriber) => {
    isEditing.value = true;
    form.value = {
        id: subscriber.id,
        username: subscriber.username,
        email: subscriber.email || '',
        password: '',
        phone: subscriber.phone || '',
        country: subscriber.country || '',
        city: subscriber.city || '',
        address: subscriber.address || '',
        postal_code: subscriber.postal_code || '',
        notes: subscriber.notes || '',
        enabled: subscriber.enabled === 1
    };
    showPassword.value = false;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.value = { ...defaultForm };
};

const submitForm = async () => {
    submitting.value = true;
    try {
        const data = {
            username: form.value.username,
            email: form.value.email || null,
            phone: form.value.phone || null,
            country: form.value.country || null,
            city: form.value.city || null,
            address: form.value.address || null,
            postal_code: form.value.postal_code || null,
            notes: form.value.notes || null,
            enabled: form.value.enabled ? 1 : 0
        };

        if (form.value.password) {
            data.password = form.value.password;
        }

        if (isEditing.value) {
            await subscribersAPI.update(form.value.id, data);
            toast.success('Subscriber updated successfully', {
                title: 'Success'
            });
        } else {
            if (!form.value.password) {
                toast.error('Password is required');
                return;
            }
            data.password = form.value.password;
            await subscribersAPI.create(data);
            toast.success('Subscriber created successfully', {
                title: 'Success'
            });
        }

        closeModal();
        fetchSubscribers();
        fetchStats();
    } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to save subscriber', {
            title: 'Error'
        });
    } finally {
        submitting.value = false;
    }
};

// Status toggle
const toggleStatus = async (subscriber) => {
    try {
        await subscribersAPI.toggle(subscriber.id);
        subscriber.enabled = subscriber.enabled ? 0 : 1;
        toast.success(subscriber.enabled ? 'Subscriber enabled' : 'Subscriber disabled');
        fetchStats();
    } catch (error) {
        toast.error('Failed to toggle status', {
            details: error.response?.data?.message || error.message
        });
    }
};

// Delete methods
const confirmDelete = (subscriber) => {
    deleteTarget.value = {
        id: subscriber.id,
        username: subscriber.username,
        bulk: false,
        count: 0
    };
    showDeleteModal.value = true;
};

const confirmBulkDelete = () => {
    deleteTarget.value = {
        id: null,
        username: '',
        bulk: true,
        count: selectedIds.value.length
    };
    showDeleteModal.value = true;
};

const executeDelete = async () => {
    deleting.value = true;
    try {
        if (deleteTarget.value.bulk) {
            await subscribersAPI.bulkDelete(selectedIds.value);
            toast.success(`${deleteTarget.value.count} subscriber(s) deleted successfully`, {
                title: 'Deleted'
            });
            selectedIds.value = [];
        } else {
            await subscribersAPI.delete(deleteTarget.value.id);
            toast.success(`Subscriber "${deleteTarget.value.username}" deleted successfully`, {
                title: 'Deleted'
            });
        }
        showDeleteModal.value = false;
        fetchSubscribers();
        fetchStats();
        fetchCountries();
    } catch (error) {
        toast.error('Failed to delete', {
            details: error.response?.data?.message || error.message
        });
    } finally {
        deleting.value = false;
    }
};

// Bulk actions
const bulkEnable = async () => {
    try {
        await subscribersAPI.bulkToggle(selectedIds.value, 1);
        toast.success(`${selectedIds.value.length} subscriber(s) enabled`);
        fetchSubscribers();
        fetchStats();
    } catch (error) {
        toast.error('Failed to enable subscribers', {
            details: error.response?.data?.message || error.message
        });
    }
};

const bulkDisable = async () => {
    try {
        await subscribersAPI.bulkToggle(selectedIds.value, 0);
        toast.success(`${selectedIds.value.length} subscriber(s) disabled`);
        fetchSubscribers();
        fetchStats();
    } catch (error) {
        toast.error('Failed to disable subscribers', {
            details: error.response?.data?.message || error.message
        });
    }
};

// Export
const exportSubscribers = async () => {
    try {
        toast.info('Preparing export...');
        const response = await subscribersAPI.export('csv');
        // The API will handle CSV download directly
        if (response.data.success) {
            toast.success('Export completed');
        }
    } catch (error) {
        toast.error('Failed to export', {
            details: error.response?.data?.message || error.message
        });
    }
};

// Utilities
const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

// Init
onMounted(() => {
    fetchSubscribers();
    fetchStats();
    fetchCountries();
});
</script>
