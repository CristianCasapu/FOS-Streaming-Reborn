<template>
    <router-view />
    <ToastNotifications />
</template>

<script setup>
import { onMounted } from 'vue';
import ToastNotifications from './components/ToastNotifications.vue';

onMounted(() => {
    console.log('%c FOS Streaming v70 ', 'background: #4F46E5; color: white; padding: 5px 10px; border-radius: 3px; font-weight: bold;');
    console.log('%c Vue 3 Application Loaded ', 'background: #42b883; color: white; padding: 5px 10px; border-radius: 3px;');
});
</script>
