/**
 * FOS Streaming v70 - Frontend Configuration
 */

// Admin base path - reads from Vite env or falls back to detecting from current URL
// Set VITE_ADMIN_PATH in .env to override (e.g., VITE_ADMIN_PATH=/adminx)
function getAdminPath() {
    // First, check Vite environment variable
    if (import.meta.env.VITE_ADMIN_PATH) {
        return import.meta.env.VITE_ADMIN_PATH;
    }

    // Fall back to detecting from current URL path
    // If we're at /adminx/something, extract /adminx
    const path = window.location.pathname;
    const match = path.match(/^(\/[^/]+)/);
    if (match && match[1] !== '/build' && match[1] !== '/assets') {
        return match[1];
    }

    // Default fallback
    return '/admin';
}

export const ADMIN_PATH = getAdminPath();

// API base URL
export const API_BASE_URL = '';

// Development mode check
export const isDev = import.meta.env.DEV;
