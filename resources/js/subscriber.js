/**
 * FOS Streaming v70 - Subscriber Portal
 * Vue 3 + Vue Router + Pinia + Tailwind CSS
 */

import '../css/app.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import subscriberRouter from './router/subscriber';
import SubscriberApp from './SubscriberApp.vue';

// Create Vue app
const app = createApp(SubscriberApp);

// Use Pinia for state management
const pinia = createPinia();
app.use(pinia);

// Use Vue Router
app.use(subscriberRouter);

// Mount app
app.mount('#app');

// Global error handler
app.config.errorHandler = (err, instance, info) => {
    console.error('Global error:', err);
    console.error('Error info:', info);
};
