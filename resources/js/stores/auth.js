/**
 * Auth Store
 * Manages authentication state
 */

import { defineStore } from 'pinia';
import { authAPI } from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        isAuthenticated: false,
        loading: false,
        error: null,
    }),

    getters: {
        currentUser: (state) => state.user,
        isLoggedIn: (state) => state.isAuthenticated,
    },

    actions: {
        async login(credentials) {
            this.loading = true;
            this.error = null;

            try {
                const response = await authAPI.login(credentials);

                if (response.data.success) {
                    this.user = response.data.user;
                    this.isAuthenticated = true;
                    return { success: true };
                } else {
                    this.error = response.data.message;
                    return { success: false, message: response.data.message };
                }
            } catch (error) {
                const message = error.response?.data?.message || 'Login failed';
                this.error = message;
                return { success: false, message };
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            try {
                await authAPI.logout();
                this.user = null;
                this.isAuthenticated = false;
                return { success: true };
            } catch (error) {
                console.error('Logout error:', error);
                // Clear state anyway
                this.user = null;
                this.isAuthenticated = false;
                return { success: true };
            }
        },

        async checkAuth() {
            try {
                const response = await authAPI.check();
                this.isAuthenticated = response.data.authenticated;

                if (this.isAuthenticated) {
                    await this.fetchUser();
                }

                return this.isAuthenticated;
            } catch (error) {
                console.error('Auth check error:', error);
                this.isAuthenticated = false;
                return false;
            }
        },

        async fetchUser() {
            try {
                const response = await authAPI.getUser();
                if (response.data.success) {
                    this.user = response.data.user;
                }
            } catch (error) {
                console.error('Fetch user error:', error);
            }
        },
    },
});
