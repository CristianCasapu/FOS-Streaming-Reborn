/**
 * Toast Store
 * Global toast notification management
 */

import { defineStore } from 'pinia';

export const useToastStore = defineStore('toast', {
    state: () => ({
        toasts: [],
        maxToasts: 5,
        counter: 0,
    }),

    actions: {
        /**
         * Add a toast notification
         * @param {Object} options - Toast options
         * @param {string} options.message - Toast message
         * @param {string} options.type - Toast type: 'success', 'error', 'warning', 'info'
         * @param {number} options.duration - Duration in ms (default: 5000, 0 = persistent)
         * @param {string} options.title - Optional title
         * @param {string} options.details - Optional detailed message (shown below main message)
         */
        show(options) {
            const id = ++this.counter;
            const toast = {
                id,
                message: options.message || '',
                type: options.type || 'info',
                duration: options.duration !== undefined ? options.duration : 5000,
                title: options.title || null,
                details: options.details || null,
                timestamp: Date.now(),
            };

            // Limit number of toasts
            if (this.toasts.length >= this.maxToasts) {
                this.toasts.shift();
            }

            this.toasts.push(toast);

            // Auto-dismiss if duration > 0
            if (toast.duration > 0) {
                setTimeout(() => {
                    this.dismiss(id);
                }, toast.duration);
            }

            return id;
        },

        /**
         * Show success toast
         */
        success(message, options = {}) {
            return this.show({ ...options, message, type: 'success' });
        },

        /**
         * Show error toast
         */
        error(message, options = {}) {
            // Errors persist longer by default
            const duration = options.duration !== undefined ? options.duration : 8000;
            return this.show({ ...options, message, type: 'error', duration });
        },

        /**
         * Show warning toast
         */
        warning(message, options = {}) {
            return this.show({ ...options, message, type: 'warning' });
        },

        /**
         * Show info toast
         */
        info(message, options = {}) {
            return this.show({ ...options, message, type: 'info' });
        },

        /**
         * Dismiss a specific toast
         */
        dismiss(id) {
            const index = this.toasts.findIndex(t => t.id === id);
            if (index > -1) {
                this.toasts.splice(index, 1);
            }
        },

        /**
         * Dismiss all toasts
         */
        dismissAll() {
            this.toasts = [];
        },
    },
});
