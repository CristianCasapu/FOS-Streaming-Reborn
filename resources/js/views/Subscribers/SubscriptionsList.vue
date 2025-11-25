<template>
    <AppLayout>
        <div class="px-4 py-6 sm:px-0">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Subscriptions</h1>
                    <p class="mt-2 text-sm text-gray-600">Manage subscriber subscriptions and renewals</p>
                </div>
                <div class="flex space-x-3">
                    <button @click="exportSubscriptions" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export
                    </button>
                    <button @click="openCreateModal" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Subscription
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-indigo-100 rounded-md p-2">
                            <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
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
                        <div class="flex-shrink-0 bg-red-100 rounded-md p-2">
                            <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs font-medium text-gray-500">Expired</p>
                            <p class="text-lg font-bold text-red-600">{{ stats.expired }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 rounded-md p-2">
                            <svg class="h-5 w-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs font-medium text-gray-500">Expiring</p>
                            <p class="text-lg font-bold text-yellow-600">{{ stats.expiring_soon }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-md p-2">
                            <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs font-medium text-gray-500">Auto-Renew</p>
                            <p class="text-lg font-bold text-blue-600">{{ stats.auto_renew_enabled }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 rounded-md p-2">
                            <svg class="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs font-medium text-gray-500">Connections</p>
                            <p class="text-lg font-bold text-purple-600">{{ stats.total_connections }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white shadow rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input v-model="filters.search" @input="debouncedSearch" type="text" placeholder="Search subscriber, device, IP..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Package</label>
                        <select v-model="filters.package_id" @change="fetchSubscriptions" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All Packages</option>
                            <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select v-model="filters.status" @change="fetchSubscriptions" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                            <option value="expiring">Expiring Soon (7 days)</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Auto-Renew</label>
                        <select v-model="filters.auto_renew" @change="fetchSubscriptions" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
                        <select v-model="filters.per_page" @change="fetchSubscriptions" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
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
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <span class="text-sm text-indigo-700">
                        <strong>{{ selectedIds.length }}</strong> subscription(s) selected
                    </span>
                    <div class="flex flex-wrap gap-2">
                        <button @click="bulkEnable" class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700">Enable</button>
                        <button @click="bulkDisable" class="px-3 py-1.5 text-sm bg-gray-600 text-white rounded-md hover:bg-gray-700">Disable</button>
                        <button @click="openBulkRenewModal" class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">Renew</button>
                        <button @click="confirmBulkDelete" class="px-3 py-1.5 text-sm bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                        <button @click="clearSelection" class="px-3 py-1.5 text-sm bg-white text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Clear</button>
                    </div>
                </div>
            </div>

            <!-- Subscriptions Table -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div v-if="loading" class="p-12 text-center">
                    <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-gray-600">Loading subscriptions...</p>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">
                                    <input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subscriber</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Package</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device / IP</th>
                                <th @click="sortBy('connection_count')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                    <div class="flex items-center">
                                        Connections
                                        <span v-if="filters.sort_by === 'connection_count'" class="ml-1">{{ filters.sort_order === 'asc' ? '↑' : '↓' }}</span>
                                    </div>
                                </th>
                                <th @click="sortBy('expire_date')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                    <div class="flex items-center">
                                        Expires
                                        <span v-if="filters.sort_by === 'expire_date'" class="ml-1">{{ filters.sort_order === 'asc' ? '↑' : '↓' }}</span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th @click="sortBy('last_connected')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                    <div class="flex items-center">
                                        Last Active
                                        <span v-if="filters.sort_by === 'last_connected'" class="ml-1">{{ filters.sort_order === 'asc' ? '↑' : '↓' }}</span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="sub in subscriptions" :key="sub.id" class="hover:bg-gray-50" :class="{ 'bg-indigo-50': selectedIds.includes(sub.id) }">
                                <td class="px-4 py-4">
                                    <input type="checkbox" :checked="selectedIds.includes(sub.id)" @change="toggleSelect(sub.id)" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <router-link :to="`/subscribers/${sub.subscriber_id}`" class="group">
                                        <div class="text-sm font-medium text-gray-900 group-hover:text-indigo-600">{{ sub.subscriber_name }}</div>
                                        <div class="text-xs text-gray-500">{{ sub.subscriber_email || '—' }}</div>
                                    </router-link>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full bg-indigo-100 text-indigo-800">{{ sub.package_name }}</span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ sub.device || '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ sub.ip_address || sub.device_mac || '—' }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm">
                                    <span class="font-medium text-gray-900">{{ sub.current_connections }}</span>
                                    <span class="text-gray-500"> / {{ sub.max_concurrent_connections }}</span>
                                    <div class="text-xs text-gray-400">{{ sub.connection_count }} total</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ formatDate(sub.expire_date) }}</div>
                                    <div class="text-xs" :class="getExpiryClass(sub)">
                                        {{ sub.is_expired ? 'Expired' : `${sub.days_until_expiration} days left` }}
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex flex-col gap-1">
                                        <span v-if="sub.is_expired" class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800 inline-flex items-center w-fit">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1"></span>Expired
                                        </span>
                                        <span v-else-if="sub.is_expiring_soon" class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-800 inline-flex items-center w-fit">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-500 mr-1"></span>Expiring
                                        </span>
                                        <span v-else-if="sub.is_active" class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800 inline-flex items-center w-fit">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1"></span>Active
                                        </span>
                                        <span v-else class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800 inline-flex items-center w-fit">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400 mr-1"></span>Inactive
                                        </span>
                                        <span v-if="sub.auto_renew" class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 inline-flex items-center w-fit">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>Auto
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatTimeAgo(sub.last_connected) }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <router-link :to="`/subscribers/subscriptions/${sub.id}`" class="text-blue-600 hover:text-blue-900" title="View">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </router-link>
                                        <button @click="openRenewModal(sub)" class="text-green-600 hover:text-green-900" title="Renew">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </button>
                                        <button @click="editSubscription(sub)" class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="toggleStatus(sub)" class="text-yellow-600 hover:text-yellow-900" :title="sub.is_active ? 'Disable' : 'Enable'">
                                            <svg v-if="sub.is_active" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                            </svg>
                                            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                        <button @click="confirmDelete(sub)" class="text-red-600 hover:text-red-900" title="Delete">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="subscriptions.length === 0">
                                <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No subscriptions found</h3>
                                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new subscription.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="pagination.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing <span class="font-medium">{{ ((pagination.current_page - 1) * pagination.per_page) + 1 }}</span> to <span class="font-medium">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}</span> of <span class="font-medium">{{ pagination.total }}</span>
                        </div>
                        <div class="flex space-x-1">
                            <button @click="goToPage(1)" :disabled="pagination.current_page === 1" class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50">First</button>
                            <button @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50">Prev</button>
                            <span class="px-3 py-1 text-sm text-gray-600">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
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
                    <h3 class="text-lg font-semibold text-gray-900">{{ isEditing ? 'Edit Subscription' : 'Create Subscription' }}</h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="saveSubscription" class="px-6 py-4 space-y-6">
                    <!-- Subscriber & Package -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subscriber <span class="text-red-500">*</span></label>
                            <select v-model="form.subscriber_id" required :disabled="isEditing" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100">
                                <option value="">Select Subscriber</option>
                                <option v-for="s in subscribers" :key="s.id" :value="s.id">{{ s.username }} ({{ s.email || 'No email' }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Package <span class="text-red-500">*</span></label>
                            <select v-model="form.package_id" @change="onPackageChange" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select Package</option>
                                <option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }} - ${{ p.price }} ({{ p.duration_days }} days)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Duration & Expire -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date <span class="text-red-500">*</span></label>
                            <input v-model="form.start_date" @change="calculateExpireDate" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Duration (days) <span class="text-red-500">*</span></label>
                            <input v-model.number="form.duration_days" @input="calculateExpireDate" type="number" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expire Date</label>
                            <input v-model="form.expire_date" type="datetime-local" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 cursor-not-allowed" />
                        </div>
                    </div>

                    <!-- Connections -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Concurrent Connections</label>
                        <input v-model.number="form.max_concurrent_connections" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>

                    <!-- Device Info -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Device Information (Optional)</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Device Name</label>
                                <input v-model="form.device" type="text" placeholder="e.g., Samsung TV" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Device MAC</label>
                                <input v-model="form.device_mac" type="text" placeholder="00:11:22:33:44:55" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">IP Address</label>
                                <input v-model="form.ip_address" type="text" placeholder="192.168.1.100" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ISP</label>
                                <input v-model="form.isp" type="text" placeholder="Internet Service Provider" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Status Options -->
                    <div class="flex items-center space-x-6">
                        <label class="flex items-center">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 text-indigo-600 rounded" />
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                        <label class="flex items-center">
                            <input v-model="form.auto_renew" type="checkbox" class="h-4 w-4 text-indigo-600 rounded" />
                            <span class="ml-2 text-sm text-gray-700">Auto-Renew</span>
                        </label>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" @click="closeModal" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" :disabled="submitting" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {{ submitting ? 'Saving...' : isEditing ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Renew Modal -->
        <div v-if="showRenewModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ renewTarget.bulk ? `Renew ${renewTarget.count} Subscription(s)` : 'Renew Subscription' }}
                </h3>
                <form @submit.prevent="executeRenew">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Additional Days</label>
                        <input v-model.number="renewDays" type="number" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                        <p class="mt-1 text-xs text-gray-500">Days will be added from current expiration or today if expired.</p>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" @click="showRenewModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" :disabled="submitting" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50">
                            {{ submitting ? 'Renewing...' : 'Renew' }}
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
                        <h3 class="text-lg font-medium text-gray-900">{{ deleteTarget.bulk ? 'Delete Subscriptions' : 'Delete Subscription' }}</h3>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mb-6">
                    <template v-if="deleteTarget.bulk">
                        Are you sure you want to delete <strong>{{ deleteTarget.count }}</strong> subscription(s)? This action cannot be undone.
                    </template>
                    <template v-else>
                        Are you sure you want to delete subscription for <strong>{{ deleteTarget.subscriber }}</strong>? This action cannot be undone.
                    </template>
                </p>
                <div class="flex justify-end space-x-3">
                    <button @click="showDeleteModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button @click="executeDelete" :disabled="deleting" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50">
                        {{ deleting ? 'Deleting...' : 'Delete' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import AppLayout from '../../components/AppLayout.vue';
import { subscriptionsAPI, subscribersAPI, packagesAPI } from '../../services/api';
import { useToastStore } from '../../stores/toast';

const router = useRouter();
const toast = useToastStore();

// Data
const subscriptions = ref([]);
const subscribers = ref([]);
const packages = ref([]);
const loading = ref(false);
const submitting = ref(false);
const deleting = ref(false);

// Stats
const stats = ref({
    total: 0,
    active: 0,
    expired: 0,
    expiring_soon: 0,
    inactive: 0,
    auto_renew_enabled: 0,
    total_connections: 0
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
    package_id: '',
    status: '',
    auto_renew: '',
    sort_by: 'expire_date',
    sort_order: 'desc',
    per_page: 20,
    page: 1
});

// Selection
const selectedIds = ref([]);

// Modals
const showModal = ref(false);
const showRenewModal = ref(false);
const showDeleteModal = ref(false);
const isEditing = ref(false);

// Form
const defaultForm = {
    id: null,
    subscriber_id: '',
    package_id: '',
    start_date: '',
    duration_days: 30,
    expire_date: '',
    max_concurrent_connections: 1,
    device: '',
    device_mac: '',
    ip_address: '',
    isp: '',
    is_active: true,
    auto_renew: false,
    notes: ''
};
const form = ref({ ...defaultForm });

// Renew
const renewDays = ref(30);
const renewTarget = ref({ id: null, bulk: false, count: 0 });

// Delete
const deleteTarget = ref({ id: null, subscriber: '', bulk: false, count: 0 });

// Computed
const isAllSelected = computed(() => {
    return subscriptions.value.length > 0 && selectedIds.value.length === subscriptions.value.length;
});

// Methods
const fetchSubscriptions = async () => {
    loading.value = true;
    try {
        const params = {
            page: filters.page,
            per_page: filters.per_page,
            sort_by: filters.sort_by,
            sort_order: filters.sort_order
        };
        if (filters.search) params.search = filters.search;
        if (filters.package_id) params.package_id = filters.package_id;
        if (filters.status) params.status = filters.status;
        if (filters.auto_renew !== '') params.auto_renew = filters.auto_renew;

        const response = await subscriptionsAPI.getAll(params);
        subscriptions.value = response.data.data;
        pagination.value = response.data.pagination;
        selectedIds.value = selectedIds.value.filter(id => subscriptions.value.some(s => s.id === id));
    } catch (error) {
        toast.error('Failed to load subscriptions', { details: error.response?.data?.message || error.message });
    } finally {
        loading.value = false;
    }
};

const fetchStats = async () => {
    try {
        const response = await subscriptionsAPI.getStats();
        if (response.data.success) {
            stats.value = response.data.data;
        }
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
};

const fetchSubscribers = async () => {
    try {
        const response = await subscribersAPI.getAll({ per_page: 1000, enabled: 1 });
        subscribers.value = response.data.data || [];
    } catch (error) {
        console.error('Failed to load subscribers:', error);
    }
};

const fetchPackages = async () => {
    try {
        const response = await packagesAPI.getAll({ per_page: 100 });
        packages.value = response.data.data || [];
    } catch (error) {
        console.error('Failed to load packages:', error);
    }
};

let searchTimeout;
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        filters.page = 1;
        fetchSubscriptions();
    }, 400);
};

const sortBy = (column) => {
    if (filters.sort_by === column) {
        filters.sort_order = filters.sort_order === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort_by = column;
        filters.sort_order = 'desc';
    }
    fetchSubscriptions();
};

const goToPage = (page) => {
    filters.page = page;
    fetchSubscriptions();
};

// Selection
const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = subscriptions.value.map(s => s.id);
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

// Modal
const getTodayDate = () => {
    const today = new Date();
    return today.toISOString().split('T')[0];
};

const openCreateModal = async () => {
    isEditing.value = false;
    form.value = {
        ...defaultForm,
        start_date: getTodayDate()
    };
    calculateExpireDate();
    if (subscribers.value.length === 0) await fetchSubscribers();
    showModal.value = true;
};

const editSubscription = async (sub) => {
    isEditing.value = true;
    const startDate = sub.created_at ? new Date(sub.created_at).toISOString().split('T')[0] : getTodayDate();
    form.value = {
        id: sub.id,
        subscriber_id: sub.subscriber_id,
        package_id: sub.package_id,
        start_date: startDate,
        duration_days: sub.days_until_expiration > 0 ? sub.days_until_expiration : 30,
        expire_date: sub.expire_date ? new Date(sub.expire_date).toISOString().slice(0, 16) : '',
        max_concurrent_connections: sub.max_concurrent_connections || 1,
        device: sub.device || '',
        device_mac: sub.device_mac || '',
        ip_address: sub.ip_address || '',
        isp: sub.isp || '',
        is_active: sub.is_active === 1,
        auto_renew: sub.auto_renew === 1,
        notes: sub.notes || ''
    };
    if (subscribers.value.length === 0) await fetchSubscribers();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.value = { ...defaultForm };
};

const onPackageChange = () => {
    const pkg = packages.value.find(p => p.id == form.value.package_id);
    if (pkg) {
        form.value.duration_days = pkg.duration_days || 30;
        form.value.max_concurrent_connections = pkg.max_concurrent_devices || 1;
        calculateExpireDate();
    }
};

const calculateExpireDate = () => {
    if (form.value.start_date && form.value.duration_days) {
        const startDate = new Date(form.value.start_date);
        const expireDate = new Date(startDate);
        expireDate.setDate(expireDate.getDate() + parseInt(form.value.duration_days));
        form.value.expire_date = expireDate.toISOString().slice(0, 16);
    }
};

const saveSubscription = async () => {
    submitting.value = true;
    try {
        const expireDate = new Date(form.value.expire_date);
        const data = {
            subscriber_id: form.value.subscriber_id,
            package_id: form.value.package_id,
            expire_date: expireDate.toISOString().slice(0, 19).replace('T', ' '),
            max_concurrent_connections: form.value.max_concurrent_connections,
            device: form.value.device || null,
            device_mac: form.value.device_mac || null,
            ip_address: form.value.ip_address || null,
            isp: form.value.isp || null,
            is_active: form.value.is_active ? 1 : 0,
            auto_renew: form.value.auto_renew ? 1 : 0,
            notes: form.value.notes || null
        };

        if (isEditing.value) {
            await subscriptionsAPI.update(form.value.id, data);
            toast.success('Subscription updated successfully');
        } else {
            await subscriptionsAPI.create(data);
            toast.success('Subscription created successfully');
        }

        closeModal();
        fetchSubscriptions();
        fetchStats();
    } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to save subscription');
    } finally {
        submitting.value = false;
    }
};

// Status toggle
const toggleStatus = async (sub) => {
    try {
        await subscriptionsAPI.toggle(sub.id);
        sub.is_active = sub.is_active ? 0 : 1;
        toast.success(sub.is_active ? 'Subscription enabled' : 'Subscription disabled');
        fetchStats();
    } catch (error) {
        toast.error('Failed to toggle status');
    }
};

// Renew
const openRenewModal = (sub) => {
    renewTarget.value = { id: sub.id, subscriber: sub.subscriber_name, bulk: false, count: 0 };
    renewDays.value = 30;
    showRenewModal.value = true;
};

const openBulkRenewModal = () => {
    renewTarget.value = { id: null, subscriber: '', bulk: true, count: selectedIds.value.length };
    renewDays.value = 30;
    showRenewModal.value = true;
};

const executeRenew = async () => {
    submitting.value = true;
    try {
        if (renewTarget.value.bulk) {
            await subscriptionsAPI.bulkRenew(selectedIds.value, renewDays.value);
            toast.success(`${renewTarget.value.count} subscription(s) renewed for ${renewDays.value} days`);
            selectedIds.value = [];
        } else {
            await subscriptionsAPI.renew(renewTarget.value.id, renewDays.value);
            toast.success(`Subscription renewed for ${renewDays.value} days`);
        }
        showRenewModal.value = false;
        fetchSubscriptions();
        fetchStats();
    } catch (error) {
        toast.error('Failed to renew', { details: error.response?.data?.message || error.message });
    } finally {
        submitting.value = false;
    }
};

// Delete
const confirmDelete = (sub) => {
    deleteTarget.value = { id: sub.id, subscriber: sub.subscriber_name, bulk: false, count: 0 };
    showDeleteModal.value = true;
};

const confirmBulkDelete = () => {
    deleteTarget.value = { id: null, subscriber: '', bulk: true, count: selectedIds.value.length };
    showDeleteModal.value = true;
};

const executeDelete = async () => {
    deleting.value = true;
    try {
        if (deleteTarget.value.bulk) {
            await subscriptionsAPI.bulkDelete(selectedIds.value);
            toast.success(`${deleteTarget.value.count} subscription(s) deleted`);
            selectedIds.value = [];
        } else {
            await subscriptionsAPI.delete(deleteTarget.value.id);
            toast.success('Subscription deleted');
        }
        showDeleteModal.value = false;
        fetchSubscriptions();
        fetchStats();
    } catch (error) {
        toast.error('Failed to delete', { details: error.response?.data?.message || error.message });
    } finally {
        deleting.value = false;
    }
};

// Bulk actions
const bulkEnable = async () => {
    try {
        await subscriptionsAPI.bulkToggle(selectedIds.value, 1);
        toast.success(`${selectedIds.value.length} subscription(s) enabled`);
        fetchSubscriptions();
        fetchStats();
    } catch (error) {
        toast.error('Failed to enable subscriptions');
    }
};

const bulkDisable = async () => {
    try {
        await subscriptionsAPI.bulkToggle(selectedIds.value, 0);
        toast.success(`${selectedIds.value.length} subscription(s) disabled`);
        fetchSubscriptions();
        fetchStats();
    } catch (error) {
        toast.error('Failed to disable subscriptions');
    }
};

// Export
const exportSubscriptions = async () => {
    try {
        toast.info('Preparing export...');
        const response = await subscriptionsAPI.export('csv');
        if (response.data.success) {
            toast.success('Export completed');
        }
    } catch (error) {
        toast.error('Failed to export');
    }
};

// Utilities
const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
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

const getExpiryClass = (sub) => {
    if (sub.is_expired) return 'text-red-600';
    if (sub.days_until_expiration <= 7) return 'text-yellow-600';
    return 'text-gray-500';
};

// Init
onMounted(async () => {
    await Promise.all([fetchSubscriptions(), fetchStats(), fetchPackages()]);
});
</script>
