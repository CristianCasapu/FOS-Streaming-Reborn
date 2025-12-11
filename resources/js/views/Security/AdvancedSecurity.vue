<template>
    <AppLayout>
        <div class="px-4 py-6 sm:px-0">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Advanced Security</h1>
                <p class="mt-2 text-sm text-gray-600">Manage firewall (UFW) and intrusion prevention (fail2ban)</p>
            </div>

            <div v-if="message" class="mb-6">
                <div :class="['rounded-md p-4', message.type === 'success' ? 'bg-green-50' : 'bg-red-50']">
                    <p :class="['text-sm', message.type === 'success' ? 'text-green-800' : 'text-red-800']">{{ message.text }}</p>
                </div>
            </div>

            <!-- Warning Banner -->
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            <strong>Warning:</strong> These security features require sudo privileges. Misconfiguration may lock you out of your server.
                        </p>
                    </div>
                </div>
            </div>

            <!-- UFW Default Rules Section -->
            <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="h-6 w-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <h2 class="text-xl font-semibold text-gray-900">UFW Default Rules (Lockout Prevention)</h2>
                    </div>
                    <button @click="loadDefaultRules" class="text-indigo-600 hover:text-indigo-800">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>

                <div v-if="loadingRules" class="p-12 text-center">
                    <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <div v-else class="p-6">
                    <div class="mb-4 flex justify-between items-center">
                        <p class="text-sm text-gray-600">
                            These rules were automatically detected from your system configuration to prevent lockouts.
                            <span class="inline-flex items-center px-2 py-1 ml-2 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                🔒 Protected rules cannot be deleted
                            </span>
                        </p>
                        <button @click="applyAllRules" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 flex items-center">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Apply All Rules to UFW
                        </button>
                    </div>

                    <div v-if="defaultRules.length > 0" class="border rounded-md overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Port</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Protocol</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applied</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="rule in defaultRules" :key="rule.id" :class="rule.enabled ? '' : 'bg-gray-50 opacity-60'">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span v-if="rule.is_protected" class="text-xl" title="Protected - Cannot be deleted">🔒</span>
                                        <button @click="toggleRule(rule)" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" :class="rule.enabled ? 'bg-indigo-600' : 'bg-gray-200'">
                                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" :class="rule.enabled ? 'translate-x-6' : 'translate-x-1'"></span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="font-mono text-sm font-medium">{{ rule.port }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">{{ rule.protocol.toUpperCase() }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="{
                                            'bg-green-100 text-green-800': rule.action === 'allow',
                                            'bg-red-100 text-red-800': rule.action === 'deny',
                                            'bg-yellow-100 text-yellow-800': rule.action === 'limit'
                                        }">{{ rule.action.toUpperCase() }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ rule.description }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span v-if="rule.applied" class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">✓ Applied</span>
                                        <button v-else-if="rule.enabled" @click="applySingleRule(rule)" :disabled="applyingRule === rule.id" class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800 hover:bg-yellow-200 cursor-pointer flex items-center">
                                            <svg v-if="applyingRule === rule.id" class="animate-spin h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            {{ applyingRule === rule.id ? 'Applying...' : 'Apply Now' }}
                                        </button>
                                        <span v-else class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500">Disabled</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                        <button v-if="!rule.is_protected" @click="deleteRule(rule)" class="text-red-600 hover:text-red-800 ml-3">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                        <span v-else class="text-gray-400 text-xs">Protected</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500">
                        <p>No default rules found.</p>
                        <button @click="reseedDefaults" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Seed Default Rules
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- UFW Firewall Card -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="h-6 w-6 text-orange-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <h2 class="text-xl font-semibold text-gray-900">UFW Firewall</h2>
                        </div>
                        <span v-if="!loading" :class="['px-3 py-1 rounded-full text-xs font-semibold', ufwStatus.active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                            {{ ufwStatus.active ? 'ACTIVE' : 'INACTIVE' }}
                        </span>
                    </div>

                    <div v-if="loading" class="p-12 text-center">
                        <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <div v-else class="p-6">
                        <div v-if="!ufwStatus.installed" class="text-center py-8">
                            <svg class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="text-gray-600">UFW is not installed on this system</p>
                            <button @click="installUFW" class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Install UFW
                            </button>
                        </div>

                        <div v-else>
                            <!-- UFW Stats -->
                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div class="bg-gray-50 rounded-lg p-3 text-center">
                                    <div class="text-2xl font-bold text-gray-900">{{ ufwStatus.rules_count || 0 }}</div>
                                    <div class="text-xs text-gray-500">Total Rules</div>
                                </div>
                                <div class="bg-green-50 rounded-lg p-3 text-center">
                                    <div class="text-2xl font-bold text-green-600">{{ appliedRulesCount }}</div>
                                    <div class="text-xs text-gray-500">Applied</div>
                                </div>
                                <div class="bg-yellow-50 rounded-lg p-3 text-center">
                                    <div class="text-2xl font-bold text-yellow-600">{{ pendingRulesCount }}</div>
                                    <div class="text-xs text-gray-500">Pending</div>
                                </div>
                            </div>

                            <!-- UFW Controls -->
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">Service Control</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <button @click="toggleUFW(true)" :disabled="ufwStatus.active || ufwActionLoading" class="px-3 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50 flex items-center justify-center">
                                        <svg v-if="ufwActionLoading === 'enable'" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <svg v-else class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Enable
                                    </button>
                                    <button @click="toggleUFW(false)" :disabled="!ufwStatus.active || ufwActionLoading" class="px-3 py-2 text-sm bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50 flex items-center justify-center">
                                        <svg v-if="ufwActionLoading === 'disable'" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <svg v-else class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Disable
                                    </button>
                                    <button @click="reloadUFW" :disabled="!ufwStatus.active || ufwActionLoading" class="px-3 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 flex items-center justify-center">
                                        <svg v-if="ufwActionLoading === 'reload'" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <svg v-else class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Reload
                                    </button>
                                    <button @click="resetUFW" :disabled="ufwActionLoading" class="px-3 py-2 text-sm bg-gray-600 text-white rounded hover:bg-gray-700 disabled:opacity-50 flex items-center justify-center">
                                        <svg v-if="ufwActionLoading === 'reset'" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <svg v-else class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Reset
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <button @click="showAddRuleModal = true" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                    <svg class="h-5 w-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Custom Firewall Rule
                                </button>
                            </div>

                            <div v-if="customRules.length > 0" class="border rounded-md divide-y">
                                <div v-for="rule in customRules" :key="rule.id" class="p-3 flex items-center justify-between hover:bg-gray-50">
                                    <div class="flex-1">
                                        <span class="font-mono text-sm">{{ rule.readable_rule }}</span>
                                        <p class="text-xs text-gray-500 mt-1">{{ rule.description }}</p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button @click="toggleRule(rule)" class="text-indigo-600 hover:text-indigo-800">
                                            <svg v-if="rule.enabled" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            <svg v-else class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <button @click="deleteRule(rule)" class="text-red-600 hover:text-red-800">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-4 text-gray-500 text-sm">
                                No custom firewall rules configured
                            </div>
                        </div>
                    </div>
                </div>

                <!-- fail2ban Card -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="h-6 w-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                            <h2 class="text-xl font-semibold text-gray-900">fail2ban</h2>
                        </div>
                        <span v-if="!loading" :class="['px-3 py-1 rounded-full text-xs font-semibold', fail2banStatus.running ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                            {{ fail2banStatus.running ? 'RUNNING' : 'STOPPED' }}
                        </span>
                    </div>

                    <div v-if="loading" class="p-12 text-center">
                        <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <div v-else class="p-6">
                        <div v-if="!fail2banStatus.installed" class="text-center py-8">
                            <svg class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="text-gray-600">fail2ban is not installed on this system</p>
                            <button @click="installFail2ban" class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Install fail2ban
                            </button>
                        </div>

                        <div v-else>
                            <!-- fail2ban Stats -->
                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div class="bg-gray-50 rounded-lg p-3 text-center">
                                    <div class="text-2xl font-bold text-gray-900">{{ fail2banStatus.jails_count || 0 }}</div>
                                    <div class="text-xs text-gray-500">Active Jails</div>
                                </div>
                                <div class="bg-red-50 rounded-lg p-3 text-center">
                                    <div class="text-2xl font-bold text-red-600">{{ fail2banStatus.banned_count || 0 }}</div>
                                    <div class="text-xs text-gray-500">Banned IPs</div>
                                </div>
                                <div class="bg-blue-50 rounded-lg p-3 text-center">
                                    <div class="text-2xl font-bold text-blue-600">{{ availableJails.filter(j => j.can_enable).length }}</div>
                                    <div class="text-xs text-gray-500">Available</div>
                                </div>
                            </div>

                            <!-- fail2ban Controls -->
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">Service Control</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <button v-if="!fail2banStatus.running" @click="enableFail2ban" :disabled="fail2banActionLoading" class="px-3 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50 flex items-center justify-center">
                                        <svg v-if="fail2banActionLoading === 'enable'" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <svg v-else class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Start
                                    </button>
                                    <button v-if="fail2banStatus.running" @click="stopFail2ban" :disabled="fail2banActionLoading" class="px-3 py-2 text-sm bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50 flex items-center justify-center">
                                        <svg v-if="fail2banActionLoading === 'stop'" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <svg v-else class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                        </svg>
                                        Stop
                                    </button>
                                    <button @click="reloadFail2ban" :disabled="!fail2banStatus.running || fail2banActionLoading" class="px-3 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 flex items-center justify-center">
                                        <svg v-if="fail2banActionLoading === 'reload'" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <svg v-else class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Reload
                                    </button>
                                    <button @click="restartFail2ban" :disabled="!fail2banStatus.running || fail2banActionLoading" class="px-3 py-2 text-sm bg-orange-600 text-white rounded hover:bg-orange-700 disabled:opacity-50 flex items-center justify-center">
                                        <svg v-if="fail2banActionLoading === 'restart'" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <svg v-else class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Restart
                                    </button>
                                </div>
                            </div>

                            <h3 class="text-sm font-medium text-gray-700 mb-2">Active Jails</h3>
                            <div v-if="jails.length > 0" class="border rounded-md divide-y">
                                <div v-for="jail in jails" :key="jail" class="p-3">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">{{ jail }}</span>
                                        <div class="flex space-x-2">
                                            <button @click="loadJailStatus(jail)" class="text-indigo-600 hover:text-indigo-800 text-sm">
                                                Status
                                            </button>
                                            <button @click="showJailDetails(jail)" class="text-blue-600 hover:text-blue-800 text-sm">
                                                Details
                                            </button>
                                        </div>
                                    </div>
                                    <div v-if="jailDetails[jail]" class="mt-2 text-sm text-gray-600 bg-gray-50 p-2 rounded">
                                        <div class="grid grid-cols-3 gap-2">
                                            <div>
                                                <span class="font-medium">Banned:</span> {{ jailDetails[jail].currently_banned }}
                                            </div>
                                            <div>
                                                <span class="font-medium">Total:</span> {{ jailDetails[jail].total_banned }}
                                            </div>
                                            <div>
                                                <span class="font-medium">Failed:</span> {{ jailDetails[jail].total_failed }}
                                            </div>
                                        </div>
                                        <div v-if="jailDetails[jail].banned_ips && jailDetails[jail].banned_ips.length > 0" class="mt-2">
                                            <span class="font-medium">Banned IPs:</span>
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                <span v-for="ip in jailDetails[jail].banned_ips" :key="ip" class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono flex items-center">
                                                    {{ ip }}
                                                    <button @click="unbanIP(jail, ip)" class="ml-1 text-red-600 hover:text-red-800" title="Unban this IP">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-4 text-gray-500 text-sm">
                                No active jails
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- fail2ban Available Jails Section -->
            <div v-if="fail2banStatus.installed" class="mt-6 bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="h-6 w-6 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <h2 class="text-xl font-semibold text-gray-900">Manage Jails</h2>
                    </div>
                    <button @click="loadAvailableJails" class="text-indigo-600 hover:text-indigo-800">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-600 mb-4">
                        Enable or disable jails based on your running services. Jails for non-running services are automatically disabled to prevent errors.
                    </p>
                    <div v-if="loadingAvailableJails" class="text-center py-8">
                        <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div v-else-if="availableJails.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="jail in availableJails" :key="jail.id" class="border rounded-lg p-4 hover:shadow-md transition-shadow" :class="jail.can_enable ? '' : 'bg-gray-50 opacity-75'">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-semibold text-gray-900">{{ jail.name }}</h4>
                                <button
                                    @click="toggleJailEnabled(jail)"
                                    :disabled="!jail.can_enable || togglingJail === jail.id"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="jail.is_enabled ? 'bg-indigo-600' : 'bg-gray-200'"
                                    :title="!jail.can_enable ? 'Service not running' : (jail.is_enabled ? 'Click to disable' : 'Click to enable')"
                                >
                                    <span v-if="togglingJail === jail.id" class="absolute inset-0 flex items-center justify-center">
                                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </span>
                                    <span v-else class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" :class="jail.is_enabled ? 'translate-x-6' : 'translate-x-1'"></span>
                                </button>
                            </div>
                            <p class="text-sm text-gray-600 mb-3">{{ jail.description }}</p>
                            <div class="space-y-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Service:</span>
                                    <span :class="jail.service_running ? 'text-green-600' : 'text-red-600'" class="font-medium flex items-center">
                                        <span v-if="jail.service_running" class="mr-1">&#10003;</span>
                                        <span v-else class="mr-1">&#10007;</span>
                                        {{ jail.service_running ? 'Running' : 'Not Running' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Log File:</span>
                                    <span :class="jail.log_exists ? 'text-green-600' : 'text-yellow-600'" class="font-medium flex items-center" :title="!jail.log_exists && jail.service_running ? 'Will be created automatically when enabled' : ''">
                                        <span v-if="jail.log_exists" class="mr-1">&#10003;</span>
                                        <span v-else class="mr-1">&#9888;</span>
                                        {{ jail.log_exists ? 'Found' : (jail.service_running ? 'Auto-create' : 'Missing') }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Status:</span>
                                    <span :class="jail.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="px-2 py-0.5 rounded-full">
                                        {{ jail.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <div class="pt-2 border-t border-gray-100">
                                    <span class="text-gray-400 font-mono text-xs truncate block" :title="jail.log_path">{{ jail.log_path }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500">
                        <p>No jail configurations available.</p>
                        <button @click="loadAvailableJails" class="mt-2 text-indigo-600 hover:text-indigo-800 text-sm">
                            Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- fail2ban Jail Configuration Section -->
            <div v-if="fail2banStatus.installed" class="mt-6 bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="h-6 w-6 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <h2 class="text-xl font-semibold text-gray-900">fail2ban Jail Configuration</h2>
                    </div>
                    <button @click="loadFail2banConfig" class="text-indigo-600 hover:text-indigo-800">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    <div v-if="loadingConfig" class="text-center py-8">
                        <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div v-else-if="fail2banConfig.jails && Object.keys(fail2banConfig.jails).length > 0">
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">
                                Configuration file: <code class="bg-gray-100 px-2 py-1 rounded text-xs">{{ fail2banConfig.config_file }}</code>
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div v-for="(config, jailName) in fail2banConfig.jails" :key="jailName" class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-semibold text-gray-900">{{ jailName }}</h4>
                                    <span :class="['px-2 py-1 rounded-full text-xs', config.enabled === 'true' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
                                        {{ config.enabled === 'true' ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </div>
                                <div class="space-y-2 text-sm">
                                    <div v-if="config.port" class="flex justify-between">
                                        <span class="text-gray-500">Port:</span>
                                        <span class="font-mono">{{ config.port }}</span>
                                    </div>
                                    <div v-if="config.maxretry" class="flex justify-between">
                                        <span class="text-gray-500">Max Retry:</span>
                                        <span>{{ config.maxretry }}</span>
                                    </div>
                                    <div v-if="config.findtime" class="flex justify-between">
                                        <span class="text-gray-500">Find Time:</span>
                                        <span>{{ formatTime(config.findtime) }}</span>
                                    </div>
                                    <div v-if="config.bantime" class="flex justify-between">
                                        <span class="text-gray-500">Ban Time:</span>
                                        <span>{{ formatTime(config.bantime) }}</span>
                                    </div>
                                    <div v-if="config.filter" class="flex justify-between">
                                        <span class="text-gray-500">Filter:</span>
                                        <span class="font-mono text-xs">{{ config.filter }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500">
                        <p>No jail configuration found.</p>
                        <p class="text-sm mt-2">Copy the configuration files from <code class="bg-gray-100 px-2 py-1 rounded">security/fail2ban/</code> to <code class="bg-gray-100 px-2 py-1 rounded">/etc/fail2ban/</code></p>
                    </div>
                </div>
            </div>

            <!-- fail2ban Logs Section -->
            <div v-if="fail2banStatus.installed && fail2banStatus.running" class="mt-6 bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="h-6 w-6 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h2 class="text-xl font-semibold text-gray-900">fail2ban Logs</h2>
                    </div>
                    <div class="flex items-center space-x-2">
                        <select v-model="selectedJailFilter" @change="loadFail2banLogs" class="text-sm border-gray-300 rounded-md">
                            <option value="">All Jails</option>
                            <option v-for="jail in jails" :key="jail" :value="jail">{{ jail }}</option>
                        </select>
                        <button @click="loadFail2banLogs" class="text-indigo-600 hover:text-indigo-800">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div v-if="loadingLogs" class="text-center py-8">
                        <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div v-else-if="fail2banLogs.length > 0" class="bg-gray-900 text-green-400 p-4 rounded font-mono text-xs max-h-96 overflow-y-auto">
                        <div v-for="(log, index) in fail2banLogs" :key="index" class="mb-1 hover:bg-gray-800 px-1 rounded">
                            <span class="text-gray-500">{{ log.timestamp }}</span>
                            <span :class="getLogLevelClass(log.level)">{{ log.level }}</span>
                            <span v-if="log.jail" class="text-blue-400">[{{ log.jail }}]</span>
                            <span class="text-gray-300">{{ log.message }}</span>
                        </div>
                    </div>
                    <div v-else class="text-center py-4 text-gray-500 text-sm">
                        No fail2ban logs available
                    </div>
                </div>
            </div>

            <!-- Security Logs -->
            <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900">Recent Security Events</h2>
                </div>
                <div class="p-6">
                    <div class="bg-gray-900 text-green-400 p-4 rounded font-mono text-sm max-h-96 overflow-y-auto">
                        <div v-if="securityLogs.length > 0">
                            <div v-for="(log, index) in securityLogs" :key="index" class="mb-1">
                                {{ log }}
                            </div>
                        </div>
                        <div v-else class="text-gray-500">
                            No recent security events
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Status Footer -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gradient-to-r from-orange-50 to-orange-100 border border-orange-200 rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <svg class="h-6 w-6 text-orange-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                UFW Service
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Status:
                                <span v-if="!ufwStatus.installed" class="font-semibold text-red-600">Not Installed</span>
                                <span v-else-if="!ufwStatus.active" class="font-semibold text-yellow-600">Installed but Inactive</span>
                                <span v-else class="font-semibold text-green-600">Active</span>
                            </p>
                        </div>
                        <div>
                            <button v-if="!ufwStatus.installed" @click="installUFW" class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700">
                                Install UFW
                            </button>
                            <button v-else-if="!ufwStatus.active" @click="toggleUFW(true)" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                Enable UFW
                            </button>
                            <span v-else class="px-4 py-2 bg-green-100 text-green-800 rounded-md font-semibold">
                                ✓ Running
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-red-50 to-red-100 border border-red-200 rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <svg class="h-6 w-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                fail2ban Service
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Status:
                                <span v-if="!fail2banStatus.installed" class="font-semibold text-red-600">Not Installed</span>
                                <span v-else-if="!fail2banStatus.running" class="font-semibold text-yellow-600">Installed but Stopped</span>
                                <span v-else class="font-semibold text-green-600">Running</span>
                            </p>
                        </div>
                        <div>
                            <button v-if="!fail2banStatus.installed" @click="installFail2ban" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                Install fail2ban
                            </button>
                            <button v-else-if="!fail2banStatus.running" @click="enableFail2ban" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                Enable fail2ban
                            </button>
                            <span v-else class="px-4 py-2 bg-green-100 text-green-800 rounded-md font-semibold">
                                ✓ Running
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Custom UFW Rule Modal -->
        <div v-if="showAddRuleModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium mb-4">Add Custom Firewall Rule</h3>
                <form @submit.prevent="addCustomRule">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Port Number *</label>
                            <input v-model="newRule.port" type="number" min="1" max="65535" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="8080" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Protocol</label>
                            <select v-model="newRule.protocol" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="tcp">TCP</option>
                                <option value="udp">UDP</option>
                                <option value="both">Both</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Action</label>
                            <select v-model="newRule.action" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="allow">Allow</option>
                                <option value="deny">Deny</option>
                                <option value="reject">Reject</option>
                                <option value="limit">Limit (Rate Limited)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description *</label>
                            <input v-model="newRule.description" type="text" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Custom application port" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">From IP (Optional)</label>
                            <input v-model="newRule.from_ip" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="192.168.1.0/24 or leave empty for any" />
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="showAddRuleModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Add Rule</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- UFW Safety Check Modal -->
        <div v-if="showSafetyModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-lg w-full">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-12 w-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="text-lg font-medium text-gray-900">⚠️ Lockout Warning</h3>
                        <div class="mt-2 text-sm text-gray-600">
                            <p class="font-semibold text-red-600 mb-2">The following critical services are NOT enabled:</p>
                            <ul class="list-disc list-inside space-y-1 bg-red-50 p-3 rounded">
                                <li v-for="service in missingServices" :key="service" class="text-red-800">
                                    {{ service }}
                                </li>
                            </ul>
                            <p class="mt-3 font-semibold">If you enable UFW now, you will be LOCKED OUT of these services!</p>
                            <p class="mt-2">Please enable rules for these services first, or confirm that you understand the risk.</p>
                        </div>
                        <div class="mt-6 flex justify-end space-x-3">
                            <button @click="showSafetyModal = false" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                                Cancel
                            </button>
                            <button @click="confirmUFWEnable" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                I Understand - Enable Anyway
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { securityAPI, ufwRulesAPI, systemCommandsAPI } from '../../services/api';

const loading = ref(false);
const loadingRules = ref(false);
const loadingConfig = ref(false);
const loadingLogs = ref(false);
const loadingAvailableJails = ref(false);
const togglingJail = ref(null);
const applyingRule = ref(null);
const ufwActionLoading = ref(null);
const fail2banActionLoading = ref(null);
const message = ref(null);

const ufwStatus = ref({ installed: false, active: false, rules_count: 0 });
const fail2banStatus = ref({ installed: false, running: false, jails_count: 0, banned_count: 0 });
const defaultRules = ref([]);
const customRules = ref([]);
const jails = ref([]);
const jailDetails = ref({});
const securityLogs = ref([]);
const fail2banConfig = ref({});
const fail2banLogs = ref([]);
const selectedJailFilter = ref('');
const availableJails = ref([]);

const showAddRuleModal = ref(false);
const showSafetyModal = ref(false);
const missingServices = ref([]);
const pendingUFWAction = ref(null);

// Computed properties for rule counts
const appliedRulesCount = computed(() => {
    return [...defaultRules.value, ...customRules.value].filter(r => r.applied && r.enabled).length;
});

const pendingRulesCount = computed(() => {
    return [...defaultRules.value, ...customRules.value].filter(r => !r.applied && r.enabled).length;
});

const newRule = ref({
    port: '',
    protocol: 'tcp',
    action: 'allow',
    description: '',
    from_ip: ''
});

const loadStatus = async () => {
    loading.value = true;
    try {
        const response = await securityAPI.getStatus();
        const data = response.data.data;

        ufwStatus.value = data.ufw;
        fail2banStatus.value = data.fail2ban;

        if (data.ufw.installed) {
            await loadDefaultRules();
            await loadCustomRules();
        }

        if (data.fail2ban.installed) {
            await loadJails();
            await loadFail2banConfig();
            await loadAvailableJails();
            if (data.fail2ban.running) {
                await loadFail2banLogs();
            }
        }

        await loadSecurityLogs();
    } catch (error) {
        showMessage('Error loading security status: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        loading.value = false;
    }
};

const loadDefaultRules = async () => {
    loadingRules.value = true;
    try {
        const response = await ufwRulesAPI.getDefaultRules();
        defaultRules.value = response.data.data;
    } catch (error) {
        console.error('Error loading default rules:', error);
        showMessage('Error loading default rules: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        loadingRules.value = false;
    }
};

const loadCustomRules = async () => {
    try {
        const response = await ufwRulesAPI.getCustomRules();
        customRules.value = response.data.data;
    } catch (error) {
        console.error('Error loading custom rules:', error);
    }
};

const loadJails = async () => {
    try {
        const response = await securityAPI.getFail2banJails();
        jails.value = response.data.data;
    } catch (error) {
        console.error('Error loading jails:', error);
    }
};

const loadJailStatus = async (jail) => {
    try {
        const response = await securityAPI.getJailStatus(jail);
        jailDetails.value[jail] = response.data.data;
    } catch (error) {
        showMessage('Error loading jail status: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const loadSecurityLogs = async () => {
    try {
        const response = await securityAPI.getSecurityLogs();
        securityLogs.value = response.data.data;
    } catch (error) {
        console.error('Error loading security logs:', error);
    }
};

const checkCriticalServices = () => {
    const missing = [];
    const criticalPorts = [
        { port: 22, name: 'SSH (Port 22) - You will lose terminal access!' },
        { port: 80, name: 'HTTP (Port 80) - Web access will be blocked' },
        { port: 443, name: 'HTTPS (Port 443) - Secure web access will be blocked' }
    ];

    criticalPorts.forEach(({ port, name }) => {
        const enabled = defaultRules.value.find(r => r.port === port && r.enabled);
        if (!enabled) {
            missing.push(name);
        }
    });

    return missing;
};

const toggleUFW = async (enable) => {
    if (enable) {
        // Check for critical services before enabling
        const missing = checkCriticalServices();
        if (missing.length > 0) {
            missingServices.value = missing;
            pendingUFWAction.value = enable;
            showSafetyModal.value = true;
            return;
        }
    }

    const confirmed = confirm(`Are you sure you want to ${enable ? 'ENABLE' : 'DISABLE'} the firewall?${!enable ? '\n\nWARNING: This may leave your server unprotected!' : ''}`);
    if (!confirmed) return;

    ufwActionLoading.value = enable ? 'enable' : 'disable';
    try {
        await securityAPI.toggleUFW(enable);
        showMessage(`Firewall ${enable ? 'enabled' : 'disabled'} successfully`);
        await loadStatus();
    } catch (error) {
        showMessage('Error toggling firewall: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        ufwActionLoading.value = null;
    }
};

const confirmUFWEnable = async () => {
    showSafetyModal.value = false;
    const enable = pendingUFWAction.value;

    try {
        await securityAPI.toggleUFW(enable);
        showMessage(`Firewall ${enable ? 'enabled' : 'disabled'} successfully`, 'success');
        await loadStatus();
    } catch (error) {
        showMessage('Error toggling firewall: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const toggleRule = async (rule) => {
    try {
        await ufwRulesAPI.toggle(rule.id);
        showMessage(`Rule ${rule.enabled ? 'disabled' : 'enabled'} successfully`);
        await loadDefaultRules();
        await loadCustomRules();
    } catch (error) {
        showMessage('Error toggling rule: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const addCustomRule = async () => {
    try {
        await ufwRulesAPI.create(newRule.value);
        showMessage('Custom firewall rule added successfully');
        showAddRuleModal.value = false;
        newRule.value = { port: '', protocol: 'tcp', action: 'allow', description: '', from_ip: '' };
        await loadCustomRules();
    } catch (error) {
        showMessage('Error adding rule: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const deleteRule = async (rule) => {
    if (rule.is_protected) {
        showMessage('Cannot delete protected rule. This rule is critical for server access.', 'error');
        return;
    }

    if (!confirm(`Delete firewall rule for port ${rule.port}?`)) return;

    try {
        await ufwRulesAPI.delete(rule.id);
        showMessage('Firewall rule deleted successfully');
        await loadDefaultRules();
        await loadCustomRules();
    } catch (error) {
        showMessage('Error deleting rule: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const applyAllRules = async () => {
    if (!confirm('Apply all enabled rules to UFW? This will reset UFW and apply all enabled rules.\n\nThis may temporarily interrupt connections.')) return;

    try {
        const response = await ufwRulesAPI.applyAll();
        showMessage(response.data.message);
        await loadDefaultRules();
        await loadCustomRules();
        await loadStatus();
    } catch (error) {
        showMessage('Error applying rules: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const applySingleRule = async (rule) => {
    applyingRule.value = rule.id;
    try {
        const response = await ufwRulesAPI.applySingle(rule.id);
        showMessage(response.data.message);
        await loadDefaultRules();
        await loadCustomRules();
    } catch (error) {
        showMessage('Error applying rule: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        applyingRule.value = null;
    }
};

const reloadUFW = async () => {
    ufwActionLoading.value = 'reload';
    try {
        await securityAPI.reloadUFW();
        showMessage('UFW reloaded successfully');
        await loadStatus();
    } catch (error) {
        showMessage('Error reloading UFW: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        ufwActionLoading.value = null;
    }
};

const resetUFW = async () => {
    if (!confirm('Reset UFW to defaults?\n\nWARNING: This will remove ALL firewall rules and disable UFW!\n\nYou will need to re-apply rules after reset.')) return;

    ufwActionLoading.value = 'reset';
    try {
        await securityAPI.resetUFW();
        showMessage('UFW reset successfully. All rules have been removed.');
        // Mark all rules as unapplied in the database
        await loadDefaultRules();
        await loadCustomRules();
        await loadStatus();
    } catch (error) {
        showMessage('Error resetting UFW: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        ufwActionLoading.value = null;
    }
};

const reseedDefaults = async () => {
    if (!confirm('Reseed default UFW rules? This will run the intelligent port detection again.')) return;

    try {
        const response = await ufwRulesAPI.reseedDefaults();
        showMessage('Default rules reseeded successfully');
        await loadDefaultRules();
    } catch (error) {
        showMessage('Error reseeding defaults: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const installUFW = async () => {
    if (!confirm('Install UFW firewall? This requires sudo privileges.')) return;

    loading.value = true;
    showMessage('Installing UFW firewall... Please wait, this may take a moment.');

    try {
        const response = await systemCommandsAPI.installPackage('ufw');

        if (response.data.success) {
            showMessage('UFW installed successfully!', 'success');
        } else {
            showMessage('UFW installation failed: ' + (response.data.data?.output || response.data.message || 'Unknown error'), 'error');
        }

        // Refresh status to reflect new installation state
        await loadStatus();
    } catch (error) {
        showMessage('Error installing UFW: ' + (error.response?.data?.message || error.message), 'error');
        loading.value = false;
    }
};

const installFail2ban = async () => {
    if (!confirm('Install fail2ban? This requires sudo privileges.')) return;

    loading.value = true;
    showMessage('Installing fail2ban... Please wait, this may take a moment.');

    try {
        const response = await systemCommandsAPI.installPackage('fail2ban');

        if (response.data.success) {
            showMessage('fail2ban installed successfully!', 'success');
        } else {
            showMessage('fail2ban installation failed: ' + (response.data.data?.output || response.data.message || 'Unknown error'), 'error');
        }

        // Refresh status to reflect new installation state
        await loadStatus();
    } catch (error) {
        showMessage('Error installing fail2ban: ' + (error.response?.data?.message || error.message), 'error');
        loading.value = false;
    }
};

const enableFail2ban = async () => {
    if (!confirm('Enable and start fail2ban service?')) return;

    fail2banActionLoading.value = 'enable';
    try {
        const response = await securityAPI.enableFail2ban();
        showMessage(response.data.message || 'fail2ban service enabled and started successfully');
        await loadStatus();
        await loadAvailableJails();
    } catch (error) {
        showMessage('Error starting fail2ban: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        fail2banActionLoading.value = null;
    }
};

const stopFail2ban = async () => {
    if (!confirm('Stop fail2ban service? This will disable intrusion prevention.')) return;

    fail2banActionLoading.value = 'stop';
    try {
        await securityAPI.stopFail2ban();
        showMessage('fail2ban service stopped successfully');
        await loadStatus();
    } catch (error) {
        showMessage('Error stopping fail2ban: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        fail2banActionLoading.value = null;
    }
};

const reloadFail2ban = async () => {
    fail2banActionLoading.value = 'reload';
    try {
        await securityAPI.reloadFail2ban();
        showMessage('fail2ban reloaded successfully');
        await loadJails();
        await loadAvailableJails();
    } catch (error) {
        showMessage('Error reloading fail2ban: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        fail2banActionLoading.value = null;
    }
};

const restartFail2ban = async () => {
    if (!confirm('Restart fail2ban service? This will briefly interrupt intrusion prevention.')) return;

    fail2banActionLoading.value = 'restart';
    try {
        await securityAPI.restartFail2ban();
        showMessage('fail2ban restarted successfully');
        await loadStatus();
        await loadJails();
        await loadAvailableJails();
    } catch (error) {
        showMessage('Error restarting fail2ban: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        fail2banActionLoading.value = null;
    }
};

const loadFail2banConfig = async () => {
    loadingConfig.value = true;
    try {
        const response = await securityAPI.getFail2banConfig();
        fail2banConfig.value = response.data.data;
    } catch (error) {
        console.error('Error loading fail2ban config:', error);
    } finally {
        loadingConfig.value = false;
    }
};

const loadAvailableJails = async () => {
    loadingAvailableJails.value = true;
    try {
        const response = await securityAPI.getAvailableJails();
        availableJails.value = response.data.data;
    } catch (error) {
        console.error('Error loading available jails:', error);
        showMessage('Error loading available jails: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        loadingAvailableJails.value = false;
    }
};

const toggleJailEnabled = async (jail) => {
    if (!jail.can_enable && !jail.is_enabled) {
        showMessage('Cannot enable this jail: service is not running', 'error');
        return;
    }

    const newState = !jail.is_enabled;
    const action = newState ? 'enable' : 'disable';

    if (!confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} jail "${jail.name}"?`)) return;

    togglingJail.value = jail.id;
    try {
        const response = await securityAPI.toggleJail(jail.id, newState);
        showMessage(response.data.message);
        await loadAvailableJails();
        await loadJails();
    } catch (error) {
        showMessage('Error toggling jail: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        togglingJail.value = null;
    }
};

const loadFail2banLogs = async () => {
    loadingLogs.value = true;
    try {
        const response = await securityAPI.getFail2banLogs(selectedJailFilter.value);
        fail2banLogs.value = response.data.data;
    } catch (error) {
        console.error('Error loading fail2ban logs:', error);
    } finally {
        loadingLogs.value = false;
    }
};

const showJailDetails = async (jail) => {
    try {
        const response = await securityAPI.getJailDetails(jail);
        jailDetails.value[jail] = response.data.data.status;
        // Could open a modal here with more details
        console.log('Jail details:', response.data.data);
    } catch (error) {
        showMessage('Error loading jail details: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const unbanIP = async (jail, ip) => {
    if (!confirm(`Unban IP ${ip} from jail ${jail}?`)) return;

    try {
        await securityAPI.unbanIP({ jail, ip });
        showMessage(`IP ${ip} unbanned from ${jail}`);
        await loadJailStatus(jail);
    } catch (error) {
        showMessage('Error unbanning IP: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const formatTime = (seconds) => {
    const num = parseInt(seconds);
    if (isNaN(num)) return seconds;
    if (num >= 86400) return `${Math.floor(num / 86400)}d`;
    if (num >= 3600) return `${Math.floor(num / 3600)}h`;
    if (num >= 60) return `${Math.floor(num / 60)}m`;
    return `${num}s`;
};

const getLogLevelClass = (level) => {
    switch (level?.toUpperCase()) {
        case 'WARNING':
        case 'WARN':
            return 'text-yellow-400';
        case 'ERROR':
        case 'CRITICAL':
            return 'text-red-400';
        case 'INFO':
            return 'text-blue-400';
        case 'NOTICE':
            return 'text-purple-400';
        default:
            return 'text-gray-400';
    }
};

const showMessage = (text, type = 'success') => {
    message.value = { text, type };
    setTimeout(() => message.value = null, 5000);
};

onMounted(() => {
    loadStatus();
});
</script>
