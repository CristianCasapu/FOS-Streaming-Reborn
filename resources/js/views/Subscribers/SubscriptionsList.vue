<template>
    <AppLayout>
        <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold text-gray-900">Subscriptions</h2>
                    <p class="mt-1 text-sm text-gray-500">Manage subscriber subscriptions and renewals</p>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button @click="openCreateModal" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        New Subscription
                    </button>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Search</label>
                        <input v-model="filters.search" @input="debouncedFetch" type="text" placeholder="Search subscribers..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Package</label>
                        <select v-model="filters.package" @change="fetchSubscriptions" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">All Packages</option>
                            <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select v-model="filters.status" @change="fetchSubscriptions" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                            <option value="expiring_soon">Expiring Soon (7 days)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Per Page</label>
                        <select v-model="filters.perPage" @change="fetchSubscriptions" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div v-if="loading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                </div>

                <table v-else-if="subscriptions.length > 0" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subscriber</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Connections</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="sub in subscriptions" :key="sub.id">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ sub.subscriber_name }}</div>
                                <div class="text-sm text-gray-500">{{ sub.subscriber_email }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ sub.package_name }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ sub.active_connections }} / {{ sub.max_connections }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ formatDate(sub.expire_date) }}</div>
                                <div class="text-sm" :class="sub.days_until_expiration <= 7 ? 'text-red-600' : 'text-gray-500'">
                                    {{ sub.days_until_expiration }} days
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="sub.is_expired" class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Expired</span>
                                <span v-else-if="sub.is_active" class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Active</span>
                                <span v-else class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">Inactive</span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <button @click="viewSubscription(sub.id)" class="text-indigo-600 hover:text-indigo-900 mr-2">View</button>
                                <button @click="renewSubscription(sub)" class="text-green-600 hover:text-green-900 mr-2">Renew</button>
                                <button @click="toggleStatus(sub)" class="text-yellow-600 hover:text-yellow-900 mr-2">{{ sub.is_active ? 'Disable' : 'Enable' }}</button>
                                <button @click="deleteSubscription(sub)" class="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="text-center py-12">
                    <p class="text-gray-500">No subscriptions found</p>
                </div>
            </div>
        </div>
        </div>

        <!-- Create/Edit Subscription Modal -->
        <div v-if="showModal" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>

                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6">
                    <div class="absolute top-0 right-0 pt-4 pr-4">
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                {{ editingSubscription ? 'Edit Subscription' : 'Create New Subscription' }}
                            </h3>

                            <form @submit.prevent="saveSubscription" class="space-y-4">
                                <!-- Subscriber Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Subscriber *</label>
                                    <select v-model="formData.subscriber_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Select Subscriber</option>
                                        <option v-for="subscriber in subscribers" :key="subscriber.id" :value="subscriber.id">
                                            {{ subscriber.username }} ({{ subscriber.email }})
                                        </option>
                                    </select>
                                </div>

                                <!-- Package Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Package *</label>
                                    <select v-model="formData.package_id" @change="onPackageChange" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Select Package</option>
                                        <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">
                                            {{ pkg.name }} - {{ pkg.duration_days }} days (${{ pkg.price }})
                                        </option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Start Date -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Start Date *</label>
                                        <input v-model="formData.start_date" @change="calculateExpireDate" type="date" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>

                                    <!-- Duration (auto-populated from package) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Duration (days) *</label>
                                        <input v-model.number="formData.duration_days" @input="calculateExpireDate" type="number" min="1" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>

                                <!-- Expiration Date (calculated) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Expiration Date (Auto-calculated)</label>
                                    <input v-model="formData.expire_date" type="datetime-local" readonly class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm sm:text-sm cursor-not-allowed">
                                    <p class="mt-1 text-xs text-gray-500">Automatically calculated: Start Date + Duration</p>
                                </div>

                                <!-- Max Concurrent Connections -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Max Concurrent Connections</label>
                                    <input v-model.number="formData.max_concurrent_connections" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <p class="mt-1 text-xs text-gray-500">From selected package</p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Device Info (Optional) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Device Name</label>
                                        <input v-model="formData.device" type="text" placeholder="e.g., Samsung TV" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Device MAC Address</label>
                                        <input v-model="formData.device_mac" type="text" placeholder="e.g., 00:11:22:33:44:55" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>

                                <!-- Status Options -->
                                <div class="flex items-center space-x-4">
                                    <label class="flex items-center">
                                        <input v-model="formData.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-700">Active</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input v-model="formData.auto_renew" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-700">Auto-Renew</span>
                                    </label>
                                </div>

                                <!-- Notes -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                                    <textarea v-model="formData.notes" rows="2" placeholder="Optional notes..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                </div>

                                <!-- Actions -->
                                <div class="flex justify-end space-x-3 pt-4 border-t">
                                    <button type="button" @click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button type="submit" :disabled="saving" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                        {{ saving ? 'Saving...' : 'Save Subscription' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import AppLayout from '../../components/AppLayout.vue';
import { subscriptionsAPI, subscribersAPI, packagesAPI } from '../../services/api';

const router = useRouter();

const subscriptions = ref([]);
const subscribers = ref([]);
const packages = ref([]);
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const editingSubscription = ref(null);
const filters = ref({ search: '', package: '', status: '', perPage: 20, page: 1 });

const formData = ref({
    subscriber_id: '',
    package_id: '',
    start_date: '',
    duration_days: 30,
    expire_date: '',
    max_concurrent_connections: 1,
    device: '',
    device_mac: '',
    is_active: true,
    auto_renew: false,
    notes: ''
});

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        filters.value.page = 1;
        fetchSubscriptions();
    }, 300);
};

const fetchSubscriptions = async () => {
    loading.value = true;
    try {
        const response = await subscriptionsAPI.getAll(filters.value);
        if (response.data.success) {
            subscriptions.value = response.data.data;
        }
    } catch (error) {
        console.error('Error:', error);
    } finally {
        loading.value = false;
    }
};

const fetchSubscribers = async () => {
    try {
        const response = await subscribersAPI.getAll({ per_page: 1000 });
        if (response.data.success) {
            subscribers.value = response.data.data;
        }
    } catch (error) {
        console.error('Error fetching subscribers:', error);
    }
};

const fetchPackages = async () => {
    try {
        const response = await packagesAPI.getAll({ per_page: 1000 });
        if (response.data.success) {
            packages.value = response.data.data;
        }
    } catch (error) {
        console.error('Error fetching packages:', error);
    }
};

const getTodayDate = () => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const onPackageChange = () => {
    const selectedPackage = packages.value.find(p => p.id == formData.value.package_id);
    if (selectedPackage) {
        // Auto-populate duration from package
        formData.value.duration_days = selectedPackage.duration_days || 30;
        // Auto-populate max connections from package
        formData.value.max_concurrent_connections = selectedPackage.max_concurrent_devices || 1;
        // Recalculate expire date
        calculateExpireDate();
    }
};

const calculateExpireDate = () => {
    console.log('calculateExpireDate called', {
        start_date: formData.value.start_date,
        duration_days: formData.value.duration_days
    });

    if (formData.value.start_date && formData.value.duration_days) {
        const startDate = new Date(formData.value.start_date);
        const expireDate = new Date(startDate);
        expireDate.setDate(expireDate.getDate() + parseInt(formData.value.duration_days));

        // Format as YYYY-MM-DDTHH:mm for datetime-local input
        const year = expireDate.getFullYear();
        const month = String(expireDate.getMonth() + 1).padStart(2, '0');
        const day = String(expireDate.getDate()).padStart(2, '0');
        const hours = String(expireDate.getHours()).padStart(2, '0');
        const minutes = String(expireDate.getMinutes()).padStart(2, '0');

        formData.value.expire_date = `${year}-${month}-${day}T${hours}:${minutes}`;

        console.log('Calculated expire_date:', formData.value.expire_date);
    } else {
        console.warn('Cannot calculate expire_date - missing start_date or duration_days');
    }
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString();
};

const openCreateModal = async () => {
    editingSubscription.value = null;

    // Reset form with defaults
    formData.value = {
        subscriber_id: '',
        package_id: '',
        start_date: getTodayDate(),
        duration_days: 30,
        expire_date: '',
        max_concurrent_connections: 1,
        device: '',
        device_mac: '',
        is_active: true,
        auto_renew: false,
        notes: ''
    };

    // Calculate initial expire date
    calculateExpireDate();

    // Load subscribers and packages if not already loaded
    if (subscribers.value.length === 0) {
        await fetchSubscribers();
    }
    if (packages.value.length === 0) {
        await fetchPackages();
    }

    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingSubscription.value = null;
};

const saveSubscription = async () => {
    saving.value = true;
    try {
        // Convert datetime-local format to database format (YYYY-MM-DD HH:mm:ss)
        const expireDate = new Date(formData.value.expire_date);
        const formattedExpireDate = expireDate.toISOString().slice(0, 19).replace('T', ' ');

        const data = {
            subscriber_id: formData.value.subscriber_id,
            package_id: formData.value.package_id,
            expire_date: formattedExpireDate,
            max_concurrent_connections: formData.value.max_concurrent_connections,
            device: formData.value.device || null,
            device_mac: formData.value.device_mac || null,
            is_active: formData.value.is_active ? 1 : 0,
            auto_renew: formData.value.auto_renew ? 1 : 0,
            notes: formData.value.notes || null
        };

        const response = editingSubscription.value
            ? await subscriptionsAPI.update(editingSubscription.value.id, data)
            : await subscriptionsAPI.create(data);

        if (response.data.success) {
            closeModal();
            await fetchSubscriptions();
            alert(response.data.message || 'Subscription saved successfully');
        }
    } catch (error) {
        console.error('Error saving subscription:', error);
        alert(error.response?.data?.message || 'Failed to save subscription');
    } finally {
        saving.value = false;
    }
};

const viewSubscription = (id) => {
    router.push(`/subscribers/subscriptions/${id}`);
};

const renewSubscription = async (sub) => {
    const days = prompt('Enter number of days to add:', '30');
    if (!days) return;

    try {
        const response = await subscriptionsAPI.renew(sub.id, parseInt(days));
        if (response.data.success) {
            alert(response.data.message);
            fetchSubscriptions();
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Failed to renew');
    }
};

const toggleStatus = async (sub) => {
    try {
        const response = await subscriptionsAPI.toggle(sub.id);
        if (response.data.success) {
            fetchSubscriptions();
        }
    } catch (error) {
        alert('Failed to toggle status');
    }
};

const deleteSubscription = async (sub) => {
    if (!confirm(`Delete subscription for ${sub.subscriber_name}?`)) return;

    try {
        const response = await subscriptionsAPI.delete(sub.id);
        if (response.data.success) {
            fetchSubscriptions();
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Failed to delete');
    }
};

onMounted(() => {
    fetchSubscriptions();
    fetchPackages(); // Load packages for filter dropdown
});
</script>
