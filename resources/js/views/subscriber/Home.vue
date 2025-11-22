<template>
    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
        <!-- Header / Navigation -->
        <nav class="bg-gray-900/50 backdrop-blur-lg border-b border-gray-700/50 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center space-x-3">
                        <div class="h-10 w-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center shadow-lg">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white">FOS Streaming</h1>
                            <p class="text-xs text-gray-400">Live Channels & Content</p>
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="hidden md:flex flex-1 max-w-lg mx-8">
                        <div class="relative w-full">
                            <input
                                type="text"
                                v-model="searchQuery"
                                @input="handleSearch"
                                placeholder="Search channels..."
                                class="w-full bg-gray-800/50 border border-gray-700 text-white rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                            <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="hidden lg:flex items-center space-x-6 text-sm">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-indigo-400">{{ stats.totalStreams }}</div>
                            <div class="text-xs text-gray-400">Channels</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-400">{{ stats.activeStreams }}</div>
                            <div class="text-xs text-gray-400">Live Now</div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Hero Section -->
            <div class="mb-12 text-center">
                <h2 class="text-4xl font-bold text-white mb-4">
                    Discover Live Streaming Content
                </h2>
                <p class="text-xl text-gray-300 max-w-2xl mx-auto">
                    Browse thousands of channels across multiple categories
                </p>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex justify-center items-center py-20">
                <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-indigo-500"></div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="bg-red-900/20 border border-red-500 rounded-lg p-6 text-center">
                <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p class="mt-4 text-red-300">{{ error }}</p>
            </div>

            <!-- Categories Grid -->
            <div v-else>
                <!-- Categories -->
                <div v-if="categories.length > 0" class="mb-12">
                    <h3 class="text-2xl font-bold text-white mb-6 flex items-center">
                        <svg class="h-6 w-6 mr-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        Browse by Category
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                        <div
                            v-for="category in categories"
                            :key="category.id"
                            @click="selectCategory(category)"
                            class="group cursor-pointer bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl p-6 border border-gray-700 hover:border-indigo-500 transition-all duration-300 hover:scale-105 hover:shadow-xl hover:shadow-indigo-500/20"
                        >
                            <div class="text-center">
                                <div class="mb-3 text-4xl">{{ getCategoryIcon(category.name) }}</div>
                                <h4 class="text-white font-semibold mb-1 group-hover:text-indigo-400 transition-colors">
                                    {{ category.name }}
                                </h4>
                                <p class="text-sm text-gray-400">
                                    {{ category.stream_count || 0 }} channels
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Featured / Recent Streams -->
                <div v-if="filteredStreams.length > 0">
                    <h3 class="text-2xl font-bold text-white mb-6 flex items-center">
                        <svg class="h-6 w-6 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z" />
                        </svg>
                        {{ selectedCategory ? selectedCategory.name : 'All Channels' }}
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <div
                            v-for="stream in filteredStreams"
                            :key="stream.id"
                            class="group bg-gray-800/50 rounded-xl overflow-hidden border border-gray-700 hover:border-indigo-500 transition-all duration-300 hover:scale-105 hover:shadow-xl hover:shadow-indigo-500/20 cursor-pointer"
                        >
                            <!-- Thumbnail -->
                            <div class="relative h-48 bg-gradient-to-br from-gray-700 to-gray-900 overflow-hidden">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <svg class="h-16 w-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <!-- Live Badge -->
                                <div v-if="stream.status == 1" class="absolute top-3 left-3 bg-red-600 text-white px-3 py-1 rounded-full text-xs font-bold flex items-center">
                                    <span class="w-2 h-2 bg-white rounded-full mr-2 animate-pulse"></span>
                                    LIVE
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-4">
                                <h4 class="text-white font-semibold mb-2 group-hover:text-indigo-400 transition-colors line-clamp-1">
                                    {{ stream.name }}
                                </h4>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-400">
                                        <span v-if="stream.category">{{ stream.category.name }}</span>
                                    </span>
                                    <span :class="getStatusClass(stream.status)">
                                        {{ getStatusText(stream.status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-20">
                    <svg class="mx-auto h-16 w-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="mt-4 text-xl text-gray-400">No channels found</p>
                    <p class="text-gray-500">Try adjusting your search or category filter</p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-900/50 border-t border-gray-700/50 mt-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="text-center text-gray-400 text-sm">
                    <p>&copy; 2025 FOS Streaming v70. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const loading = ref(true);
const error = ref(null);
const categories = ref([]);
const streams = ref([]);
const selectedCategory = ref(null);
const searchQuery = ref('');

const stats = ref({
    totalStreams: 0,
    activeStreams: 0,
});

// Mock data for now - will be replaced with API calls
const mockCategories = [
    { id: 1, name: 'Sports', stream_count: 24 },
    { id: 2, name: 'Movies', stream_count: 156 },
    { id: 3, name: 'News', stream_count: 42 },
    { id: 4, name: 'Entertainment', stream_count: 89 },
    { id: 5, name: 'Music', stream_count: 67 },
];

const mockStreams = [
    { id: 1, name: 'ESPN Sports Network', category: { name: 'Sports' }, status: 1, cat_id: 1 },
    { id: 2, name: 'HBO Movies', category: { name: 'Movies' }, status: 1, cat_id: 2 },
    { id: 3, name: 'CNN News', category: { name: 'News' }, status: 1, cat_id: 3 },
    { id: 4, name: 'Comedy Central', category: { name: 'Entertainment' }, status: 1, cat_id: 4 },
    { id: 5, name: 'MTV Music', category: { name: 'Music' }, status: 0, cat_id: 5 },
    { id: 6, name: 'Fox Sports', category: { name: 'Sports' }, status: 1, cat_id: 1 },
    { id: 7, name: 'Netflix Originals', category: { name: 'Movies' }, status: 1, cat_id: 2 },
    { id: 8, name: 'BBC News', category: { name: 'News' }, status: 0, cat_id: 3 },
];

const filteredStreams = computed(() => {
    let result = streams.value;

    // Filter by category
    if (selectedCategory.value) {
        result = result.filter(s => s.cat_id === selectedCategory.value.id);
    }

    // Filter by search
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(s =>
            s.name.toLowerCase().includes(query) ||
            (s.category && s.category.name.toLowerCase().includes(query))
        );
    }

    return result;
});

const getCategoryIcon = (categoryName) => {
    const icons = {
        'Sports': '⚽',
        'Movies': '🎬',
        'News': '📰',
        'Entertainment': '🎭',
        'Music': '🎵',
        'Kids': '🧸',
        'Documentary': '📺',
        'Gaming': '🎮',
    };
    return icons[categoryName] || '📺';
};

const getStatusClass = (status) => {
    return status == 1 ? 'text-green-400 font-semibold' : 'text-gray-500';
};

const getStatusText = (status) => {
    return status == 1 ? 'Online' : 'Offline';
};

const selectCategory = (category) => {
    selectedCategory.value = selectedCategory.value?.id === category.id ? null : category;
};

const handleSearch = () => {
    // Search is reactive via computed property
};

onMounted(async () => {
    try {
        // TODO: Replace with actual API calls
        // const response = await fetch('/api/streams');
        // const data = await response.json();

        // Mock data for now
        await new Promise(resolve => setTimeout(resolve, 800));
        categories.value = mockCategories;
        streams.value = mockStreams;

        stats.value.totalStreams = mockStreams.length;
        stats.value.activeStreams = mockStreams.filter(s => s.status == 1).length;

        loading.value = false;
    } catch (err) {
        error.value = 'Failed to load content. Please try again later.';
        loading.value = false;
    }
});
</script>

<style scoped>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
