<template>
    <AppLayout>
        <div class="px-4 py-6 sm:px-0">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Staff Members</h1>
                    <p class="mt-2 text-sm text-gray-600">Manage staff accounts and permissions</p>
                </div>
                <button @click="showCreateModal = true" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Staff Member
                </button>
            </div>

            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Note:</strong> The main staff account (ID 1) cannot be deleted for security reasons.
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input v-model="searchQuery" @input="debouncedSearch" type="text" placeholder="Search usernames..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
                        <select v-model="perPage" @change="fetchStaff" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
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
                    <p class="mt-4 text-gray-600">Loading staff members...</p>
                </div>
                <table v-else class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Member</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="staff in staffs" :key="staff.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div :class="['flex-shrink-0 h-10 w-10 rounded-lg flex items-center justify-center', staff.is_main ? 'bg-yellow-100' : 'bg-indigo-100']">
                                        <svg class="h-6 w-6" :class="staff.is_main ? 'text-yellow-600' : 'text-indigo-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <div class="flex items-center">
                                            <div class="text-sm font-medium text-gray-900">{{ staff.username }}</div>
                                            <span v-if="staff.is_main" class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">Main</span>
                                        </div>
                                        <div class="text-sm text-gray-500">{{ staff.full_name || '—' }}</div>
                                        <div class="text-xs text-gray-400">{{ staff.email || '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="['px-2 py-1 text-xs font-medium rounded-full',
                                    staff.role === 'admin' ? 'bg-purple-100 text-purple-800' :
                                    staff.role === 'supervisor' ? 'bg-blue-100 text-blue-800' :
                                    'bg-gray-100 text-gray-800']">
                                    {{ staff.role ? staff.role.charAt(0).toUpperCase() + staff.role.slice(1) : '—' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="['px-2 py-1 text-xs font-medium rounded-full',
                                    staff.status === 'active' ? 'bg-green-100 text-green-800' :
                                    staff.status === 'inactive' ? 'bg-gray-100 text-gray-800' :
                                    'bg-red-100 text-red-800']">
                                    {{ staff.status ? staff.status.charAt(0).toUpperCase() + staff.status.slice(1) : '—' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div>{{ formatDate(staff.last_login) }}</div>
                                <div class="text-xs text-gray-400">{{ staff.login_count || 0 }} logins</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <button @click="editAdmin(staff)" class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                <button
                                    v-if="staff.id !== 1"
                                    @click="confirmDelete(staff)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="staffs.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">No staff members found</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="pagination.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }} results
                        </div>
                        <div class="flex space-x-2">
                            <button @click="currentPage--; fetchStaff()" :disabled="currentPage === 1" class="px-3 py-1 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed">Previous</button>
                            <button @click="currentPage++; fetchStaff()" :disabled="currentPage === pagination.last_page" class="px-3 py-1 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showCreateModal || showEditModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 overflow-y-auto">
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full my-8">
                <h3 class="text-lg font-medium mb-4">{{ showEditModal ? 'Edit Staff Member' : 'Create Staff Member' }}</h3>
                <form @submit.prevent="showEditModal ? updateAdmin() : createAdmin()">
                    <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
                        <!-- Account Information -->
                        <div class="border-b pb-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Account Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Username *</label>
                                    <input v-model="form.username" type="text" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="Staff username" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <input v-model="form.email" type="email" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="email@example.com" />
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700">
                                    Password {{ showEditModal ? '(leave blank to keep current)' : '*' }}
                                </label>
                                <input
                                    v-model="form.password"
                                    type="password"
                                    :required="!showEditModal"
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="Strong password"
                                />
                                <p class="mt-1 text-xs text-gray-500">Use a strong password with letters, numbers, and symbols</p>
                            </div>
                        </div>

                        <!-- Role & Status -->
                        <div class="border-b pb-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Role & Access</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Role *</label>
                                    <select v-model="form.role" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">Select Role</option>
                                        <option value="admin">Administrator (Full Access)</option>
                                        <option value="supervisor">Supervisor (Most Access)</option>
                                        <option value="support">Support (Read Only)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status *</label>
                                    <select v-model="form.status" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">Select Status</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Information -->
                        <div class="border-b pb-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Personal Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Full Name</label>
                                    <input v-model="form.full_name" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="John Doe" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                                    <input v-model="form.phone" type="tel" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="+1234567890" />
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea v-model="form.notes" rows="3" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="Internal notes about this staff member..."></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
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
                <p class="text-sm text-gray-500 mb-6">
                    Are you sure you want to delete the staff member "{{ staffToDelete?.username }}"? This action cannot be undone.
                </p>
                <div class="flex justify-end space-x-3">
                    <button @click="showDeleteModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button @click="deleteAdmin" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { staffAPI } from '../../services/api';

const staffs = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const perPage = ref(20);
const currentPage = ref(1);
const pagination = ref({ total: 0, per_page: 20, current_page: 1, last_page: 1 });
const message = ref(null);

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const staffToDelete = ref(null);
const form = ref({
    id: null,
    username: '',
    email: '',
    password: '',
    role: 'support',
    status: 'active',
    full_name: '',
    phone: '',
    notes: ''
});

const fetchStaff = async () => {
    loading.value = true;
    try {
        const params = { page: currentPage.value, per_page: perPage.value };
        if (searchQuery.value) params.search = searchQuery.value;
        const response = await staffAPI.getAll(params);
        staffs.value = response.data.data;
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            per_page: response.data.per_page,
            total: response.data.total,
            from: response.data.from,
            to: response.data.to
        };
    } catch (error) {
        showMessage('Error loading staff members: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        loading.value = false;
    }
};

let searchTimeout;
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        fetchStaff();
    }, 500);
};

const createAdmin = async () => {
    try {
        await staffAPI.create({
            username: form.value.username,
            email: form.value.email,
            password: form.value.password,
            role: form.value.role,
            status: form.value.status,
            full_name: form.value.full_name,
            phone: form.value.phone,
            notes: form.value.notes
        });
        showMessage('Staff account created successfully');
        closeModal();
        fetchStaff();
    } catch (error) {
        showMessage('Error creating staff member: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const editAdmin = (staff) => {
    form.value = {
        id: staff.id,
        username: staff.username,
        email: staff.email || '',
        password: '',
        role: staff.role || 'support',
        status: staff.status || 'active',
        full_name: staff.full_name || '',
        phone: staff.phone || '',
        notes: staff.notes || ''
    };
    showEditModal.value = true;
};

const updateAdmin = async () => {
    try {
        const data = {
            username: form.value.username,
            email: form.value.email,
            role: form.value.role,
            status: form.value.status,
            full_name: form.value.full_name,
            phone: form.value.phone,
            notes: form.value.notes
        };
        // Only include password if it's been changed
        if (form.value.password) {
            data.password = form.value.password;
        }
        await staffAPI.update(form.value.id, data);
        showMessage('Staff account updated successfully');
        closeModal();
        fetchStaff();
    } catch (error) {
        showMessage('Error updating staff member: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const confirmDelete = (staff) => {
    // Never allow deleting the main admin account (ID 1)
    if (staff.id === 1 || staff.is_main) {
        showMessage('Cannot delete the main staff account (ID 1)', 'error');
        return;
    }
    staffToDelete.value = staff;
    showDeleteModal.value = true;
};

const deleteAdmin = async () => {
    try {
        await staffAPI.delete(staffToDelete.value.id);
        showMessage('Staff account deleted successfully');
        showDeleteModal.value = false;
        staffToDelete.value = null;
        fetchStaff();
    } catch (error) {
        showMessage('Error deleting staff member: ' + (error.response?.data?.message || error.message), 'error');
    }
};

const closeModal = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    form.value = {
        id: null,
        username: '',
        email: '',
        password: '',
        role: 'support',
        status: 'active',
        full_name: '',
        phone: '',
        notes: ''
    };
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const showMessage = (text, type = 'success') => {
    message.value = { text, type };
    setTimeout(() => message.value = null, 5000);
};

onMounted(() => fetchStaff());
</script>
