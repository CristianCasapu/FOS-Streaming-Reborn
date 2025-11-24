<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { resellersAPI } from '../../services/api';
import { useRoute } from 'vue-router';

const route = useRoute();
const reseller = ref(null);
const loading = ref(true);

const fetchReseller = async () => {
    try {
        const response = await resellersAPI.get(route.params.id);
        reseller.value = response.data.data;
    } catch (error) {
        console.error('Failed to fetch reseller:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchReseller();
});
</script>

<template>
    <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold mb-8">Reseller Dashboard</h1>
        <div v-if="loading" class="text-center py-12">Loading...</div>
        <div v-else-if="reseller" class="space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">{{ reseller.company_name }}</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <div class="text-sm text-gray-500">Username</div>
                        <div class="font-medium">{{ reseller.username }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Email</div>
                        <div class="font-medium">{{ reseller.email }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Subscribers</div>
                        <div class="font-medium">{{ reseller.stats?.total_subscribers || 0 }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Balance</div>
                        <div class="font-medium text-green-600">${{ reseller.credit_balance }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </AppLayout>
</template>
