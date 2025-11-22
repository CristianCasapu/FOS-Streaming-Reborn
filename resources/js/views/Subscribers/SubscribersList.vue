<template>
    <AppLayout>
        <div class="px-4 py-6 sm:px-0">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Subscribers</h1>
                    <p class="mt-2 text-sm text-gray-600">Manage subscriber accounts</p>
                </div>
                <button @click="showCreateModal = true" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Subscriber
                </button>
            </div>

            <div class="bg-white shadow rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input v-model="searchQuery" @input="debouncedSearch" type="text" placeholder="Search by username or email..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select v-model="filterEnabled" @change="fetchSubscribers" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All Subscribers</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
                        <select v-model="perPage" @change="fetchSubscribers" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>

            <div v-if="message" class="mb-6">
                <div :class="['rounded-md p-4', message.type === 'success' ? 'bg-green-50' : 'bg-red-50']">
                    <p :class="['text-sm', message.type === 'success' ? 'text-green-800' : 'text-red-800']">{{ message.text }}</p>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div v-if="loading" class="p-12 text-center">
                    <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-gray-600">Loading subscribers...</p>
                </div>
                <table v-else class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="subscriber in subscribers" :key="subscriber.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ subscriber.username }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600">{{ subscriber.email || '—' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="['px-2 inline-flex text-xs leading-5 font-semibold rounded-full', subscriber.enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
                                    {{ subscriber.enabled ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ formatDate(subscriber.created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <button @click="editSubscriber(subscriber)" class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                <button @click="confirmDelete(subscriber)" class="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="subscribers.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">No subscribers found</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="pagination.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }} results
                        </div>
                        <div class="flex space-x-2">
                            <button @click="currentPage--; fetchSubscribers()" :disabled="currentPage === 1" class="px-3 py-1 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed">Previous</button>
                            <button @click="currentPage++; fetchSubscribers()" :disabled="currentPage === pagination.last_page" class="px-3 py-1 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showCreateModal || showEditModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium mb-4">{{ showEditModal ? 'Edit Subscriber' : 'Create Subscriber' }}</h3>
                <form @submit.prevent="showEditModal ? updateSubscriber() : createSubscriber()">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Username</label>
                            <input v-model="form.username" type="text" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input v-model="form.email" type="email" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password {{ showEditModal ? '(leave blank to keep current)' : '' }}</label>
                            <input v-model="form.password" type="password" :required="!showEditModal" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div class="flex items-center">
                            <input v-model="form.enabled" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" />
                            <label class="ml-2 block text-sm text-gray-900">Active</label>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="closeModal" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">{{ showEditModal ? 'Update' : 'Create' }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium mb-4">Confirm Delete</h3>
                <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete subscriber "{{ subscriberToDelete?.username }}"? This action cannot be undone.</p>
                <div class="flex justify-end space-x-3">
                    <button @click="showDeleteModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button @click="deleteSubscriber" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { subscribersAPI } from '../../services/api';

const subscribers = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const filterEnabled = ref('');
const perPage = ref(20);
const currentPage = ref(1);
const pagination = ref({ total: 0, per_page: 20, current_page: 1, last_page: 1 });
const message = ref(null);

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const subscriberToDelete = ref(null);
const form = ref({
    id: null,
    username: '',
    email: '',
    password: '',
    enabled: true
});

const fetchSubscribers = async () => {
    loading.value = true;
    try {
        const params = { page: currentPage.value, per_page: perPage.value };
        if (filterEnabled.value !== '') params.enabled = filterEnabled.value;
        if (searchQuery.value) params.search = searchQuery.value;
        const response = await subscribersAPI.getAll(params);
        subscribers.value = response.data.data;
        pagination.value = response.data.pagination;
    } catch (error) {
        showMessage('Error loading subscribers: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        loading.value = false;
    }
};

let searchTimeout;
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        fetchSubscribers();
    }, 500);
};

const createSubscriber = async () => {
    try {
        await subscribersAPI.create({
            username: form.value.username,
            email: form.value.email,
            password: form.value.password,
            enabled: form.value.enabled ? 1 : 0
        });
        showMessage('Subscriber created successfully');
        closeModal();
        fetchSubscribers();
    } catch (error) {
        showMessage('Error creating subscriber: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const editSubscriber = (subscriber) => {
    form.value = {
        id: subscriber.id,
        username: subscriber.username,
        email: subscriber.email || '',
        password: '',
        enabled: subscriber.enabled === 1
    };
    showEditModal.value = true;
};

const updateSubscriber = async () => {
    try {
        const data = {
            username: form.value.username,
            email: form.value.email,
            enabled: form.value.enabled ? 1 : 0
        };
        if (form.value.password) {
            data.password = form.value.password;
        }
        await subscribersAPI.update(form.value.id, data);
        showMessage('Subscriber updated successfully');
        closeModal();
        fetchSubscribers();
    } catch (error) {
        showMessage('Error updating subscriber: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const confirmDelete = (subscriber) => {
    subscriberToDelete.value = subscriber;
    showDeleteModal.value = true;
};

const deleteSubscriber = async () => {
    try {
        await subscribersAPI.delete(subscriberToDelete.value.id);
        showMessage('Subscriber deleted successfully');
        showDeleteModal.value = false;
        subscriberToDelete.value = null;
        fetchSubscribers();
    } catch (error) {
        showMessage('Error deleting subscriber: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const closeModal = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    form.value = { id: null, username: '', email: '', password: '', enabled: true };
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const showMessage = (text, type = 'success') => {
    message.value = { text, type };
    setTimeout(() => message.value = null, 5000);
};

onMounted(() => fetchSubscribers());
</script>
