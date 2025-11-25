<template>
    <Teleport to="body">
        <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 pointer-events-none max-w-md w-full">
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto rounded-lg shadow-lg overflow-hidden"
                    :class="toastClasses(toast.type)"
                >
                    <div class="p-4">
                        <div class="flex items-start">
                            <!-- Icon -->
                            <div class="flex-shrink-0">
                                <!-- Success Icon -->
                                <svg v-if="toast.type === 'success'" class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <!-- Error Icon -->
                                <svg v-else-if="toast.type === 'error'" class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                                <!-- Warning Icon -->
                                <svg v-else-if="toast.type === 'warning'" class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <!-- Info Icon -->
                                <svg v-else class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <!-- Content -->
                            <div class="ml-3 flex-1">
                                <p v-if="toast.title" class="text-sm font-semibold" :class="titleClasses(toast.type)">
                                    {{ toast.title }}
                                </p>
                                <p class="text-sm" :class="messageClasses(toast.type)">
                                    {{ toast.message }}
                                </p>
                                <p v-if="toast.details" class="mt-1 text-xs opacity-80" :class="messageClasses(toast.type)">
                                    {{ toast.details }}
                                </p>
                            </div>
                            <!-- Close Button -->
                            <div class="ml-4 flex-shrink-0 flex">
                                <button
                                    @click="dismiss(toast.id)"
                                    class="inline-flex rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2"
                                    :class="closeButtonClasses(toast.type)"
                                >
                                    <span class="sr-only">Close</span>
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Progress bar for auto-dismiss -->
                    <div
                        v-if="toast.duration > 0"
                        class="h-1 origin-left"
                        :class="progressClasses(toast.type)"
                        :style="{ animation: `shrink ${toast.duration}ms linear forwards` }"
                    ></div>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue';
import { useToastStore } from '../stores/toast';

const toastStore = useToastStore();
const toasts = computed(() => toastStore.toasts);

const dismiss = (id) => toastStore.dismiss(id);

const toastClasses = (type) => {
    const base = 'border';
    switch (type) {
        case 'success': return `${base} bg-green-50 border-green-200`;
        case 'error': return `${base} bg-red-50 border-red-200`;
        case 'warning': return `${base} bg-yellow-50 border-yellow-200`;
        default: return `${base} bg-blue-50 border-blue-200`;
    }
};

const titleClasses = (type) => {
    switch (type) {
        case 'success': return 'text-green-800';
        case 'error': return 'text-red-800';
        case 'warning': return 'text-yellow-800';
        default: return 'text-blue-800';
    }
};

const messageClasses = (type) => {
    switch (type) {
        case 'success': return 'text-green-700';
        case 'error': return 'text-red-700';
        case 'warning': return 'text-yellow-700';
        default: return 'text-blue-700';
    }
};

const closeButtonClasses = (type) => {
    switch (type) {
        case 'success': return 'text-green-400 hover:text-green-500 focus:ring-green-500';
        case 'error': return 'text-red-400 hover:text-red-500 focus:ring-red-500';
        case 'warning': return 'text-yellow-400 hover:text-yellow-500 focus:ring-yellow-500';
        default: return 'text-blue-400 hover:text-blue-500 focus:ring-blue-500';
    }
};

const progressClasses = (type) => {
    switch (type) {
        case 'success': return 'bg-green-400';
        case 'error': return 'bg-red-400';
        case 'warning': return 'bg-yellow-400';
        default: return 'bg-blue-400';
    }
};
</script>

<style scoped>
/* Toast enter/leave animations */
.toast-enter-active {
    animation: slideIn 0.3s ease-out;
}

.toast-leave-active {
    animation: slideOut 0.3s ease-in;
}

.toast-move {
    transition: transform 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideOut {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100%);
    }
}

/* Progress bar shrink animation */
@keyframes shrink {
    from {
        transform: scaleX(1);
    }
    to {
        transform: scaleX(0);
    }
}
</style>
