<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { resellersAPI } from '../../services/api';
import { useRouter } from 'vue-router';

const router = useRouter();
const resellers = ref([]);
const loading = ref(true);
const stats = ref(null);
const showCreateModal = ref(false);
const message = ref(null);

const newReseller = ref({
    username: '',
    email: '',
    password: '',
    company_name: '',
    commission_rate: 10.00,
    max_subscribers: 100,
    max_packages: 10,
    status: 'active'
});

const fetchResellers = async () => {
    loading.value = true;
    try {
        const [resellersRes, statsRes] = await Promise.all([
            resellersAPI.list(),
            resellersAPI.stats()
        ]);
        resellers.value = resellersRes.data.data;
        stats.value = statsRes.data.data;
    } catch (error) {
        console.error('Failed to fetch resellers:', error);
        showMessage('Failed to load resellers: ' + error.message, 'error');
    } finally {
        loading.value = false;
    }
};

const createReseller = async () => {
    try {
        await resellersAPI.create(newReseller.value);
        showMessage('Reseller created successfully', 'success');
        showCreateModal.value = false;
        resetForm();
        fetchResellers();
    } catch (error) {
        showMessage('Failed to create reseller: ' + (error.response?.data?.error || error.message), 'error');
    }
};

const resetForm = () => {
    newReseller.value = {
        username: '',
        email: '',
        password: '',
        company_name: '',
        commission_rate: 10.00,
        max_subscribers: 100,
        max_packages: 10,
        status: 'active'
    };
};

const viewReseller = (id) => {
    router.push(`/resellers/${id}`);
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount || 0);
};

const showMessage = (text, type) => {
    message.value = { text, type };
    setTimeout(() => message.value = null, 5000);
};

onMounted(() => {
    fetchResellers();
});
</script>

<template>
    <AppLayout>
        <div class="px-4 py-6 sm:px-0">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Resellers Management</h1>
                    <p class="mt-2 text-sm text-gray-600">Manage reseller accounts and commissions</p>
                </div>
                <button @click="showCreateModal = true" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Reseller
                </button>
            </div>

            <div v-if="message" class="mb-6">
                <div :class="['rounded-md p-4', message.type === 'success' ? 'bg-green-50' : 'bg-red-50']">
                    <p :class="['text-sm', message.type === 'success' ? 'text-green-800' : 'text-red-800']">{{ message.text }}</p>
                </div>
            </div>

            <!-- Stats -->
            <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm font-medium text-gray-500">Total Resellers</div>
                    <div class="mt-2 text-3xl font-semibold">{{ stats.total_resellers }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm font-medium text-gray-500">Active</div>
                    <div class="mt-2 text-3xl font-semibold text-green-600">{{ stats.active_resellers }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm font-medium text-gray-500">Total Commission Paid</div>
                    <div class="mt-2 text-3xl font-semibold">{{ formatCurrency(stats.total_commission_paid) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm font-medium text-gray-500">Pending</div>
                    <div class="mt-2 text-3xl font-semibold text-yellow-600">{{ formatCurrency(stats.pending_commissions) }}</div>
                </div>
            </div>

            <!-- Resellers Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subscribers</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commission Rate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Balance</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-if="loading">
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">Loading...</td>
                        </tr>
                        <tr v-else-if="resellers.length === 0">
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                <p class="text-gray-600">No resellers found</p>
                                <button @click="showCreateModal = true" class="mt-2 text-indigo-600 hover:text-indigo-900">
                                    Create your first reseller
                                </button>
                            </td>
                        </tr>
                        <tr v-else v-for="reseller in resellers" :key="reseller.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ reseller.company_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ reseller.username }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ reseller.subscriber_count || 0 }} / {{ reseller.max_subscribers }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ reseller.commission_rate }}%</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                {{ formatCurrency(reseller.credit_balance) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="`px-2 py-1 text-xs rounded-full ${reseller.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`">
                                    {{ reseller.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button @click="viewReseller(reseller.id)" class="text-blue-600 hover:text-blue-900">
                                    View Details
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create Reseller Modal -->
        <div v-if="showCreateModal" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showCreateModal = false"></div>

                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Create New Reseller</h3>
                        <form @submit.prevent="createReseller" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Username *</label>
                                <input v-model="newReseller.username" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email *</label>
                                <input v-model="newReseller.email" type="email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Password *</label>
                                <input v-model="newReseller.password" type="password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Company Name *</label>
                                <input v-model="newReseller.company_name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Commission Rate (%)</label>
                                    <input v-model.number="newReseller.commission_rate" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Max Subscribers</label>
                                    <input v-model.number="newReseller.max_subscribers" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Max Packages</label>
                                    <input v-model.number="newReseller.max_packages" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status</label>
                                    <select v-model="newReseller.status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="active">Active</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                                <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:col-start-2 sm:text-sm">
                                    Create Reseller
                                </button>
                                <button type="button" @click="showCreateModal = false; resetForm();" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
