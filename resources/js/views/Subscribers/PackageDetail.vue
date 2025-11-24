<template>
    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Loading/Error States -->
                <div v-if="loading" class="flex items-center justify-center min-h-screen">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                </div>

                <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <h3 class="text-lg font-medium text-red-900">Error: {{ error }}</h3>
                    <button @click="router.back()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-md">Go Back</button>
                </div>

                <!-- Main Content -->
                <div v-else-if="pkg">
                    <!-- Header -->
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <button @click="router.back()" class="p-2 hover:bg-gray-100 rounded-lg">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                            </button>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900">{{ pkg.name }}</h1>
                                <p class="text-sm text-gray-600 mt-1">{{ pkg.description || 'No description' }}</p>
                            </div>
                        </div>
                        <div class="flex space-x-3">
                            <button @click="showEditModal = true" class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 bg-white hover:bg-gray-50">Edit</button>
                            <button @click="toggleStatus" class="px-4 py-2 rounded-md text-sm text-white" :class="pkg.is_active ? 'bg-yellow-600' : 'bg-green-600'">
                                {{ pkg.is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <p class="text-sm text-gray-600">Active Subscriptions</p>
                            <p class="text-2xl font-bold text-gray-900">{{ stats?.active_subscriptions || 0 }}</p>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <p class="text-sm text-gray-600">Active Trials</p>
                            <p class="text-2xl font-bold text-gray-900">{{ stats?.active_trials || 0 }}</p>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <p class="text-sm text-gray-600">Total Bouquets</p>
                            <p class="text-2xl font-bold text-gray-900">{{ pkg.bouquets?.length || 0 }}</p>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <p class="text-sm text-gray-600">Total Channels</p>
                            <p class="text-2xl font-bold text-gray-900">{{ stats?.total_channels || 0 }}</p>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="mb-6">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <button @click="activeTab = 'overview'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'overview' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500']">Overview</button>
                                <button @click="activeTab = 'bouquets'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'bouquets' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500']">Bouquets ({{ pkg.bouquets?.length || 0 }})</button>
                                <button @click="activeTab = 'subscriptions'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'subscriptions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500']">Subscriptions</button>
                            </nav>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div>
                        <!-- Overview Tab -->
                        <div v-show="activeTab === 'overview'" class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-medium mb-4">Package Information</h3>
                            <dl class="grid grid-cols-2 gap-4">
                                <div><dt class="text-sm text-gray-500">Price</dt><dd class="text-sm text-gray-900">${{ pkg.price || 'N/A' }}</dd></div>
                                <div><dt class="text-sm text-gray-500">Duration</dt><dd class="text-sm text-gray-900">{{ pkg.duration_days }} days</dd></div>
                                <div><dt class="text-sm text-gray-500">Max Connections</dt><dd class="text-sm text-gray-900">{{ pkg.max_connections }}</dd></div>
                                <div><dt class="text-sm text-gray-500">Status</dt><dd><span :class="['px-2 py-1 text-xs rounded-full', pkg.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">{{ pkg.is_active ? 'Active' : 'Inactive' }}</span></dd></div>
                            </dl>
                        </div>

                        <!-- Bouquets Tab -->
                        <div v-show="activeTab === 'bouquets'" class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="p-6 border-b">
                                <div class="flex justify-between items-center">
                                    <h3 class="text-lg font-medium">Assigned Bouquets</h3>
                                    <button @click="showAddBouquetsModal = true" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md">Add Bouquets</button>
                                </div>
                            </div>
                            <div v-if="pkg.bouquets && pkg.bouquets.length > 0">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Channels</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="bouquet in pkg.bouquets" :key="bouquet.id">
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ bouquet.name }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-500">{{ bouquet.channel_count }} channels</td>
                                            <td class="px-6 py-4"><span :class="['px-2 py-1 text-xs rounded-full', bouquet.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">{{ bouquet.is_active ? 'Active' : 'Inactive' }}</span></td>
                                            <td class="px-6 py-4 text-right text-sm space-x-2">
                                                <router-link :to="`/streams/bouquets/${bouquet.id}`" class="text-indigo-600 hover:text-indigo-900">View</router-link>
                                                <button @click="removeBouquet(bouquet.id)" class="text-red-600 hover:text-red-900">Remove</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-center py-12">
                                <h3 class="text-sm font-medium text-gray-900">No bouquets</h3>
                                <button @click="showAddBouquetsModal = true" class="mt-4 px-4 py-2 bg-indigo-600 text-white text-sm rounded-md">Add Bouquets</button>
                            </div>
                        </div>

                        <!-- Subscriptions Tab -->
                        <div v-show="activeTab === 'subscriptions'" class="bg-white shadow rounded-lg overflow-hidden">
                            <div v-if="loadingSubscriptions" class="text-center py-12">
                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                            </div>
                            <div v-else-if="subscriptions.length > 0">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subscriber</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expire Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="sub in subscriptions" :key="sub.id">
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ sub.subscriber_name }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-500">{{ formatDate(sub.start_date) }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-500">{{ formatDate(sub.expire_date) }}</td>
                                            <td class="px-6 py-4"><span :class="['px-2 py-1 text-xs rounded-full', sub.is_expired ? 'bg-red-100 text-red-800' : sub.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">{{ sub.is_expired ? 'Expired' : sub.is_active ? 'Active' : 'Inactive' }}</span></td>
                                            <td class="px-6 py-4 text-right text-sm">
                                                <router-link :to="`/subscribers/subscriptions/${sub.id}`" class="text-indigo-600 hover:text-indigo-900">View</router-link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-center py-12">
                                <h3 class="text-sm font-medium text-gray-900">No subscriptions</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal && pkg" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full">
                <h3 class="text-lg font-medium mb-4">Edit Package</h3>
                <form @submit.prevent="updatePackage">
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700">Name</label><input v-model="editForm.name" type="text" required class="mt-1 w-full px-3 py-2 border rounded-md" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Description</label><textarea v-model="editForm.description" rows="3" class="mt-1 w-full px-3 py-2 border rounded-md"></textarea></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-gray-700">Price ($)</label><input v-model.number="editForm.price" type="number" step="0.01" class="mt-1 w-full px-3 py-2 border rounded-md" /></div>
                            <div><label class="block text-sm font-medium text-gray-700">Duration (days)</label><input v-model.number="editForm.duration_days" type="number" class="mt-1 w-full px-3 py-2 border rounded-md" /></div>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700">Max Connections</label><input v-model.number="editForm.max_connections" type="number" min="1" class="mt-1 w-full px-3 py-2 border rounded-md" /></div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 border rounded-md">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Update</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Bouquets Modal -->
        <div v-if="showAddBouquetsModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-4xl w-full">
                <h3 class="text-lg font-medium mb-4">Add Bouquets</h3>
                <div class="max-h-96 overflow-y-auto space-y-2">
                    <div v-for="bouquet in availableBouquets" :key="bouquet.id" class="flex justify-between items-center p-3 border rounded-lg">
                        <div>
                            <p class="text-sm font-medium">{{ bouquet.name }}</p>
                            <p class="text-xs text-gray-500">{{ bouquet.channel_count }} channels</p>
                        </div>
                        <button @click="addBouquet(bouquet.id)" class="px-3 py-1 bg-indigo-600 text-white text-sm rounded">Add</button>
                    </div>
                </div>
                <div class="flex justify-end mt-6">
                    <button @click="showAddBouquetsModal = false" class="px-4 py-2 border rounded-md">Close</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '../../components/AppLayout.vue';
import { packagesAPI, bouquetsAPI, subscriptionsAPI } from '../../services/api';

const route = useRoute();
const router = useRouter();
const packageId = route.params.id;

const pkg = ref(null);
const stats = ref(null);
const subscriptions = ref([]);
const availableBouquets = ref([]);
const loading = ref(true);
const loadingSubscriptions = ref(false);
const error = ref(null);

const activeTab = ref('overview');
const showEditModal = ref(false);
const showAddBouquetsModal = ref(false);

const editForm = ref({ name: '', description: '', price: null, duration_days: 30, max_connections: 1 });

const fetchPackage = async () => {
    loading.value = true;
    try {
        const [pkgRes, statsRes] = await Promise.all([
            packagesAPI.getOne(packageId),
            packagesAPI.getStats(packageId)
        ]);
        if (pkgRes.data.success) {
            pkg.value = pkgRes.data.data;
            editForm.value = { name: pkg.value.name, description: pkg.value.description || '', price: pkg.value.price, duration_days: pkg.value.duration_days, max_connections: pkg.value.max_connections };
        }
        if (statsRes.data.success) stats.value = statsRes.data.data;
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load package';
    } finally {
        loading.value = false;
    }
};

const fetchSubscriptions = async () => {
    loadingSubscriptions.value = true;
    try {
        const response = await subscriptionsAPI.getAll({ package_id: packageId });
        if (response.data.success) subscriptions.value = response.data.data || [];
    } catch (err) {
        console.error(err);
    } finally {
        loadingSubscriptions.value = false;
    }
};

const fetchAvailableBouquets = async () => {
    try {
        const response = await bouquetsAPI.getAll({ active: '1', per_page: 1000 });
        if (response.data.success) {
            const assigned = new Set(pkg.value.bouquets?.map(b => b.id) || []);
            availableBouquets.value = response.data.data.filter(b => !assigned.has(b.id));
        }
    } catch (err) {
        console.error(err);
    }
};

const updatePackage = async () => {
    try {
        const response = await packagesAPI.update(packageId, editForm.value);
        if (response.data.success) {
            showEditModal.value = false;
            await fetchPackage();
            alert('Package updated');
        }
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to update');
    }
};

const toggleStatus = async () => {
    try {
        await packagesAPI.toggle(packageId);
        await fetchPackage();
    } catch (err) {
        alert('Failed to toggle status');
    }
};

const addBouquet = async (bouquetId) => {
    try {
        await packagesAPI.assignBouquets(packageId, [bouquetId]);
        await fetchPackage();
        await fetchAvailableBouquets();
        alert('Bouquet added');
    } catch (err) {
        alert('Failed to add bouquet');
    }
};

const removeBouquet = async (bouquetId) => {
    if (!confirm('Remove this bouquet?')) return;
    try {
        await packagesAPI.removeBouquet(packageId, bouquetId);
        await fetchPackage();
        alert('Bouquet removed');
    } catch (err) {
        alert('Failed to remove');
    }
};

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';

onMounted(async () => {
    await fetchPackage();
    await Promise.all([fetchSubscriptions(), fetchAvailableBouquets()]);
});
</script>
