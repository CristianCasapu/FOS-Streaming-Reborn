<template>
    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="loading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                </div>

                <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <h3 class="text-lg text-red-900">Error: {{ error }}</h3>
                    <button @click="router.back()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-md">Go Back</button>
                </div>

                <div v-else-if="subscription">
                    <!-- Header -->
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <button @click="router.back()" class="p-2 hover:bg-gray-100 rounded-lg">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                            </button>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900">Subscription #{{ subscription.id }}</h1>
                                <p class="text-sm text-gray-600 mt-1">{{ subscription.subscriber_name }}</p>
                            </div>
                        </div>
                        <div class="flex space-x-3">
                            <button @click="showRenewModal = true" class="px-4 py-2 bg-green-600 text-white text-sm rounded-md">Renew</button>
                            <button @click="toggleStatus" class="px-4 py-2 text-sm rounded-md text-white" :class="subscription.is_active ? 'bg-yellow-600' : 'bg-green-600'">
                                {{ subscription.is_active ? 'Disable' : 'Enable' }}
                            </button>
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div class="mb-6">
                        <span v-if="subscription.is_expired" class="px-3 py-1 text-sm font-medium rounded-full bg-red-100 text-red-800">Expired</span>
                        <span v-else-if="subscription.is_active" class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800">Active</span>
                        <span v-else class="px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800">Inactive</span>
                    </div>

                    <!-- Info Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-sm text-gray-600">Package</h3>
                            <p class="text-2xl font-bold text-gray-900">{{ subscription.package_name }}</p>
                        </div>
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-sm text-gray-600">Connections</h3>
                            <p class="text-2xl font-bold text-gray-900">{{ subscription.active_connections || 0 }} / {{ subscription.max_connections }}</p>
                        </div>
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-sm text-gray-600">Days Remaining</h3>
                            <p class="text-2xl font-bold" :class="subscription.days_until_expiration <= 7 ? 'text-red-600' : 'text-gray-900'">
                                {{ subscription.days_until_expiration }}
                            </p>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium mb-4">Subscription Details</h3>
                        <dl class="grid grid-cols-2 gap-4">
                            <div><dt class="text-sm text-gray-500">Subscriber</dt><dd class="text-sm text-gray-900">{{ subscription.subscriber_name }}</dd></div>
                            <div><dt class="text-sm text-gray-500">Package</dt><dd class="text-sm text-gray-900">{{ subscription.package_name }}</dd></div>
                            <div><dt class="text-sm text-gray-500">Start Date</dt><dd class="text-sm text-gray-900">{{ formatDate(subscription.start_date) }}</dd></div>
                            <div><dt class="text-sm text-gray-500">Expire Date</dt><dd class="text-sm text-gray-900">{{ formatDate(subscription.expire_date) }}</dd></div>
                            <div><dt class="text-sm text-gray-500">Max Connections</dt><dd class="text-sm text-gray-900">{{ subscription.max_connections }}</dd></div>
                            <div><dt class="text-sm text-gray-500">Active Connections</dt><dd class="text-sm text-gray-900">{{ subscription.active_connections || 0 }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Renew Modal -->
        <div v-if="showRenewModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium mb-4">Renew Subscription</h3>
                <form @submit.prevent="renewSubscription">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Additional Days</label>
                        <input v-model.number="renewDays" type="number" min="1" required class="mt-1 w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" @click="showRenewModal = false" class="px-4 py-2 border rounded-md">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md">Renew</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '../../components/AppLayout.vue';
import { subscriptionsAPI } from '../../services/api';

const route = useRoute();
const router = useRouter();
const subscriptionId = route.params.id;

const subscription = ref(null);
const loading = ref(true);
const error = ref(null);
const showRenewModal = ref(false);
const renewDays = ref(30);

const fetchSubscription = async () => {
    loading.value = true;
    try {
        const response = await subscriptionsAPI.getOne(subscriptionId);
        if (response.data.success) subscription.value = response.data.data;
        else error.value = response.data.message || 'Failed to load';
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load';
    } finally {
        loading.value = false;
    }
};

const toggleStatus = async () => {
    try {
        await subscriptionsAPI.toggle(subscriptionId);
        await fetchSubscription();
    } catch (err) {
        alert('Failed to toggle status');
    }
};

const renewSubscription = async () => {
    try {
        await subscriptionsAPI.renew(subscriptionId, renewDays.value);
        showRenewModal.value = false;
        await fetchSubscription();
        alert('Subscription renewed');
    } catch (err) {
        alert('Failed to renew');
    }
};

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';

onMounted(() => fetchSubscription());
</script>
