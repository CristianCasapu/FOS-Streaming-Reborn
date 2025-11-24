<template>
    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Loading State -->
                <div v-if="loading" class="flex items-center justify-center min-h-screen">
                    <div class="text-center">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                        <p class="mt-4 text-gray-600">Loading bouquet details...</p>
                    </div>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <div class="flex items-center">
                        <svg class="h-6 w-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <h3 class="text-lg font-medium text-red-900">Error Loading Bouquet</h3>
                            <p class="text-sm text-red-700 mt-1">{{ error }}</p>
                        </div>
                    </div>
                    <button @click="router.back()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Go Back
                    </button>
                </div>

                <!-- Main Content -->
                <div v-else-if="bouquet">
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
                                    <h1 class="text-3xl font-bold text-gray-900">{{ bouquet.name }}</h1>
                                    <p class="text-sm text-gray-600 mt-1">{{ bouquet.description || 'No description' }}</p>
                                </div>
                            </div>
                            <div class="flex space-x-3">
                                <button @click="showEditModal = true" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Edit
                                </button>
                                <button @click="toggleStatus" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white" :class="bouquet.is_active ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'">
                                    {{ bouquet.is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div class="mb-6">
                        <span :class="['inline-flex items-center px-3 py-1 rounded-full text-sm font-medium', bouquet.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
                            <span class="w-2 h-2 rounded-full mr-2" :class="bouquet.is_active ? 'bg-green-600' : 'bg-gray-600'"></span>
                            {{ bouquet.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Total Channels</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ bouquet.channels?.length || 0 }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-purple-100 rounded-md p-3">
                                    <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Packages Using</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ bouquet.package_count || 0 }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-indigo-100 rounded-md p-3">
                                    <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Sort Order</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ bouquet.sort_order || 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="mb-6">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <button @click="activeTab = 'channels'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'channels' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                    Channels ({{ bouquet.channels?.length || 0 }})
                                </button>
                                <button @click="activeTab = 'packages'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'packages' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                    Packages ({{ bouquet.packages?.length || 0 }})
                                </button>
                            </nav>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div>
                        <!-- Channels Tab -->
                        <div v-show="activeTab === 'channels'">
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="p-6 border-b border-gray-200">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-lg font-medium text-gray-900">Assigned Channels</h3>
                                        <button @click="showAddChannelsModal = true" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                            Add Channels
                                        </button>
                                    </div>
                                </div>

                                <div v-if="bouquet.channels && bouquet.channels.length > 0">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Channel Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stream</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="channel in bouquet.channels" :key="channel.id" class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">{{ channel.name }}</div>
                                                    <div class="text-sm text-gray-500">{{ channel.description || 'No description' }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ channel.stream_name || 'No stream' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span :class="['px-2 py-1 text-xs rounded-full', channel.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
                                                        {{ channel.is_active ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                                    <button @click="removeChannel(channel.id)" class="text-red-600 hover:text-red-900">Remove</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="text-center py-12">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No channels</h3>
                                    <p class="mt-1 text-sm text-gray-500">Get started by adding channels to this bouquet.</p>
                                    <button @click="showAddChannelsModal = true" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                        Add Channels
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Packages Tab -->
                        <div v-show="activeTab === 'packages'">
                            <div class="bg-white shadow rounded-lg overflow-hidden">
                                <div class="p-6 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Packages Using This Bouquet</h3>
                                </div>

                                <div v-if="bouquet.packages && bouquet.packages.length > 0">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Max Connections</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="pkg in bouquet.packages" :key="pkg.id" class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">{{ pkg.name }}</div>
                                                    <div class="text-sm text-gray-500">{{ pkg.description || 'No description' }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ${{ pkg.price || 'N/A' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ pkg.max_connections }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span :class="['px-2 py-1 text-xs rounded-full', pkg.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
                                                        {{ pkg.is_active ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                                    <router-link :to="`/streams/packages/${pkg.id}`" class="text-indigo-600 hover:text-indigo-900">View</router-link>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="text-center py-12">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No packages</h3>
                                    <p class="mt-1 text-sm text-gray-500">This bouquet is not included in any packages yet.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Bouquet Modal -->
        <div v-if="showEditModal && bouquet" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full">
                <h3 class="text-lg font-medium mb-4">Edit Bouquet</h3>
                <form @submit.prevent="updateBouquet">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Bouquet Name</label>
                            <input v-model="editForm.name" type="text" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea v-model="editForm.description" rows="3" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sort Order</label>
                            <input v-model.number="editForm.sort_order" type="number" min="0" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" />
                        </div>
                        <div class="flex items-center">
                            <input v-model="editForm.is_active" type="checkbox" class="h-4 w-4 text-indigo-600 rounded" />
                            <label class="ml-2 text-sm text-gray-900">Active</label>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Update</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Channels Modal -->
        <div v-if="showAddChannelsModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 overflow-y-auto">
            <div class="bg-white rounded-lg p-6 max-w-4xl w-full my-8">
                <h3 class="text-lg font-medium mb-4">Add Channels to Bouquet</h3>
                <div class="mb-4">
                    <input v-model="channelSearchQuery" type="text" placeholder="Search channels..." class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <div v-if="loadingChannels" class="text-center py-12">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    </div>
                    <div v-else-if="availableChannels.length === 0" class="text-center py-12">
                        <p class="text-sm text-gray-500">No available channels to add</p>
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="channel in filteredAvailableChannels" :key="channel.id" class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ channel.name }}</p>
                                <p class="text-xs text-gray-500">{{ channel.description || 'No description' }}</p>
                            </div>
                            <button @click="addChannel(channel.id)" class="px-3 py-1 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">
                                Add
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end mt-6">
                    <button @click="showAddChannelsModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Close</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '../../components/AppLayout.vue';
import { bouquetsAPI, channelsAPI } from '../../services/api';

const route = useRoute();
const router = useRouter();
const bouquetId = route.params.id;

const bouquet = ref(null);
const availableChannels = ref([]);
const loading = ref(true);
const loadingChannels = ref(false);
const error = ref(null);

const activeTab = ref('channels');
const showEditModal = ref(false);
const showAddChannelsModal = ref(false);
const channelSearchQuery = ref('');

const editForm = ref({
    name: '',
    description: '',
    sort_order: 0,
    is_active: true
});

const filteredAvailableChannels = computed(() => {
    if (!channelSearchQuery.value) return availableChannels.value;
    const query = channelSearchQuery.value.toLowerCase();
    return availableChannels.value.filter(ch =>
        ch.name.toLowerCase().includes(query) ||
        (ch.description && ch.description.toLowerCase().includes(query))
    );
});

const fetchBouquet = async () => {
    loading.value = true;
    error.value = null;
    try {
        const response = await bouquetsAPI.getOne(bouquetId);
        if (response.data.success) {
            bouquet.value = response.data.data;
            editForm.value = {
                name: bouquet.value.name,
                description: bouquet.value.description || '',
                sort_order: bouquet.value.sort_order || 0,
                is_active: bouquet.value.is_active
            };
        } else {
            error.value = response.data.message || 'Failed to load bouquet';
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load bouquet details';
        console.error('Error fetching bouquet:', err);
    } finally {
        loading.value = false;
    }
};

const fetchAvailableChannels = async () => {
    loadingChannels.value = true;
    try {
        const response = await channelsAPI.getAll({ active: '1', per_page: 1000 });
        if (response.data.success) {
            const allChannels = response.data.data;
            const assignedChannelIds = new Set(bouquet.value.channels?.map(ch => ch.id) || []);
            availableChannels.value = allChannels.filter(ch => !assignedChannelIds.has(ch.id));
        }
    } catch (err) {
        console.error('Error fetching channels:', err);
    } finally {
        loadingChannels.value = false;
    }
};

const updateBouquet = async () => {
    try {
        const response = await bouquetsAPI.update(bouquetId, editForm.value);
        if (response.data.success) {
            showEditModal.value = false;
            await fetchBouquet();
            alert('Bouquet updated successfully');
        }
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to update bouquet');
        console.error('Error updating bouquet:', err);
    }
};

const toggleStatus = async () => {
    try {
        const response = await bouquetsAPI.toggle(bouquetId);
        if (response.data.success) {
            await fetchBouquet();
        }
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to toggle status');
        console.error('Error toggling status:', err);
    }
};

const addChannel = async (channelId) => {
    try {
        const response = await bouquetsAPI.assignChannels(bouquetId, [channelId]);
        if (response.data.success) {
            await fetchBouquet();
            await fetchAvailableChannels();
            alert('Channel added successfully');
        }
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to add channel');
        console.error('Error adding channel:', err);
    }
};

const removeChannel = async (channelId) => {
    if (!confirm('Are you sure you want to remove this channel from the bouquet?')) return;

    try {
        const response = await bouquetsAPI.removeChannel(bouquetId, channelId);
        if (response.data.success) {
            await fetchBouquet();
            alert('Channel removed successfully');
        }
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to remove channel');
        console.error('Error removing channel:', err);
    }
};

onMounted(async () => {
    await fetchBouquet();
    await fetchAvailableChannels();
});
</script>
