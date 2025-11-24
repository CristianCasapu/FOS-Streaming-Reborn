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

                <div v-else-if="trial">
                    <!-- Header -->
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <button @click="router.back()" class="p-2 hover:bg-gray-100 rounded-lg">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                            </button>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900">Trial #{{ trial.id }}</h1>
                                <p class="text-sm text-gray-600 mt-1">{{ trial.subscriber_name }}</p>
                            </div>
                        </div>
                        <div class="flex space-x-3">
                            <button v-if="!trial.is_expired && trial.is_active" @click="showConvertModal = true" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md">Convert to Subscription</button>
                            <button @click="showExtendModal = true" class="px-4 py-2 bg-green-600 text-white text-sm rounded-md">Extend</button>
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div class="mb-6">
                        <span v-if="trial.is_expired" class="px-3 py-1 text-sm font-medium rounded-full bg-red-100 text-red-800">Expired</span>
                        <span v-else-if="trial.is_active" class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800">Active</span>
                        <span v-else class="px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800">Inactive</span>
                    </div>

                    <!-- Info Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-sm text-gray-600">Package</h3>
                            <p class="text-2xl font-bold text-gray-900">{{ trial.package_name }}</p>
                        </div>
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-sm text-gray-600">Duration</h3>
                            <p class="text-2xl font-bold text-gray-900">{{ trial.duration_hours }} hours</p>
                        </div>
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-sm text-gray-600">Time Remaining</h3>
                            <p class="text-2xl font-bold" :class="trial.hours_remaining <= 2 ? 'text-red-600' : 'text-gray-900'">
                                {{ trial.hours_remaining }} hours
                            </p>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium mb-4">Trial Details</h3>
                        <dl class="grid grid-cols-2 gap-4">
                            <div><dt class="text-sm text-gray-500">Subscriber</dt><dd class="text-sm text-gray-900">{{ trial.subscriber_name }}</dd></div>
                            <div><dt class="text-sm text-gray-500">Package</dt><dd class="text-sm text-gray-900">{{ trial.package_name }}</dd></div>
                            <div><dt class="text-sm text-gray-500">Start Date</dt><dd class="text-sm text-gray-900">{{ formatDateTime(trial.start_date) }}</dd></div>
                            <div><dt class="text-sm text-gray-500">End Date</dt><dd class="text-sm text-gray-900">{{ formatDateTime(trial.end_date) }}</dd></div>
                            <div><dt class="text-sm text-gray-500">Duration</dt><dd class="text-sm text-gray-900">{{ trial.duration_hours }} hours</dd></div>
                            <div><dt class="text-sm text-gray-500">Max Connections</dt><dd class="text-sm text-gray-900">{{ trial.max_connections }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Extend Modal -->
        <div v-if="showExtendModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium mb-4">Extend Trial</h3>
                <form @submit.prevent="extendTrial">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Additional Hours</label>
                        <input v-model.number="extendHours" type="number" min="1" required class="mt-1 w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" @click="showExtendModal = false" class="px-4 py-2 border rounded-md">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md">Extend</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Convert Modal -->
        <div v-if="showConvertModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium mb-4">Convert to Subscription</h3>
                <form @submit.prevent="convertToSubscription">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Duration (days)</label>
                        <input v-model.number="convertDays" type="number" min="1" required class="mt-1 w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" @click="showConvertModal = false" class="px-4 py-2 border rounded-md">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Convert</button>
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
import { trialsAPI } from '../../services/api';

const route = useRoute();
const router = useRouter();
const trialId = route.params.id;

const trial = ref(null);
const loading = ref(true);
const error = ref(null);
const showExtendModal = ref(false);
const showConvertModal = ref(false);
const extendHours = ref(24);
const convertDays = ref(30);

const fetchTrial = async () => {
    loading.value = true;
    try {
        const response = await trialsAPI.getOne(trialId);
        if (response.data.success) trial.value = response.data.data;
        else error.value = response.data.message || 'Failed to load';
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load';
    } finally {
        loading.value = false;
    }
};

const extendTrial = async () => {
    try {
        await trialsAPI.extend(trialId, extendHours.value);
        showExtendModal.value = false;
        await fetchTrial();
        alert('Trial extended');
    } catch (err) {
        alert('Failed to extend');
    }
};

const convertToSubscription = async () => {
    try {
        await trialsAPI.convert(trialId, { duration_days: convertDays.value });
        showConvertModal.value = false;
        alert('Converted to subscription');
        router.push('/subscribers/subscriptions');
    } catch (err) {
        alert('Failed to convert');
    }
};

const formatDateTime = (d) => d ? new Date(d).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A';

onMounted(() => fetchTrial());
</script>
