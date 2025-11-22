<template>
    <router-view />
</template>

<script setup>
import { onMounted } from 'vue';

onMounted(() => {
    console.log('%c FOS Streaming v70 - Subscriber Portal ', 'background: #10B981; color: white; padding: 5px 10px; border-radius: 3px; font-weight: bold;');
});
</script>
