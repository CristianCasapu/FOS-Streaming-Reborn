/**
 * FOS Streaming v70 - Main Application JavaScript
 * Vue 3 + Vue Router + Pinia + Tailwind CSS
 */

import '../css/app.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';

// Create Vue app
const app = createApp(App);

// Use Pinia for state management
const pinia = createPinia();
app.use(pinia);

// Use Vue Router
app.use(router);

// Mount app
app.mount('#app');

// Global error handler
app.config.errorHandler = (err, instance, info) => {
    console.error('Global error:', err);
    console.error('Error info:', info);
};
