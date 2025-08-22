/**
 * Service Worker for PrettiOps
 * Provides offline support, caching strategies, and performance optimization
 * 
 * @version 1.0.0
 * @author PrettiOps Development Team
 */

// Service Worker Configuration
const CACHE_NAME = 'prettiops-v1.0.0';
const OFFLINE_PAGE = '/offline';
const API_CACHE_NAME = 'prettiops-api-v1.0.0';
const STATIC_CACHE_NAME = 'prettiops-static-v1.0.0';
const DYNAMIC_CACHE_NAME = 'prettiops-dynamic-v1.0.0';

// Cache Duration Settings (in milliseconds)
const CACHE_DURATIONS = {
  static: 7 * 24 * 60 * 60 * 1000,    // 7 days
  api: 5 * 60 * 1000,                  // 5 minutes
  dynamic: 24 * 60 * 60 * 1000,        // 1 day
  images: 30 * 24 * 60 * 60 * 1000,    // 30 days
};

// Resources to cache on install
const STATIC_ASSETS = [
  '/',
  '/login',
  '/register',
  '/dashboard',
  '/editor',
  '/build/app.css',
  '/build/app.js',
  '/build/runtime.js',
  '/favicon.ico',
  '/manifest.json',
  OFFLINE_PAGE,
];

// API endpoints to cache
const API_PATTERNS = [
  /\/api\/snippets$/,
  /\/api\/dashboard\/stats$/,
  /\/api\/user\/profile$/,
];

// Resources that should always be fetched from network
const NETWORK_FIRST = [
  /\/api\/auth\//,
  /\/api\/.*\/(create|update|delete)$/,
  /\/logout$/,
];

// Resources that can be served from cache
const CACHE_FIRST = [
  /\.(css|js|woff|woff2|ttf|eot)$/,
  /\/build\//,
  /\/images\//,
  /\/icons\//,
];

/**
 * Install Event Handler
 * Pre-caches essential resources when service worker is installed
 */
self.addEventListener('install', (event) => {
  console.log('[ServiceWorker] Install event');
  
  event.waitUntil(
    (async () => {
      try {
        // Cache static assets
        const staticCache = await caches.open(STATIC_CACHE_NAME);
        await staticCache.addAll(STATIC_ASSETS);
        
        console.log('[ServiceWorker] Static assets cached');
        
        // Skip waiting to activate immediately
        self.skipWaiting();
      } catch (error) {
        console.error('[ServiceWorker] Failed to cache static assets:', error);
      }
    })()
  );
});

/**
 * Activate Event Handler
 * Cleans up old caches and claims all clients
 */
self.addEventListener('activate', (event) => {
  console.log('[ServiceWorker] Activate event');
  
  event.waitUntil(
    (async () => {
      try {
        // Clean up old caches
        const cacheNames = await caches.keys();
        const oldCaches = cacheNames.filter(name => 
          name.startsWith('prettiops-') && 
          !name.includes('v1.0.0')
        );
        
        await Promise.all(
          oldCaches.map(cacheName => {
            console.log('[ServiceWorker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          })
        );
        
        // Claim all clients
        await self.clients.claim();
        
        console.log('[ServiceWorker] Service worker activated');
      } catch (error) {
        console.error('[ServiceWorker] Activation failed:', error);
      }
    })()
  );
});

/**
 * Fetch Event Handler
 * Implements caching strategies based on request type
 */
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }
  
  // Skip chrome-extension and other non-http requests
  if (!url.protocol.startsWith('http')) {
    return;
  }
  
  event.respondWith(handleFetch(request));
});

/**
 * Main fetch handler that routes requests to appropriate caching strategies
 * 
 * @param {Request} request - The fetch request
 * @returns {Promise<Response>} Response from cache or network
 */
async function handleFetch(request) {
  const url = new URL(request.url);
  
  try {
    // API requests
    if (url.pathname.startsWith('/api/')) {
      return await handleApiRequest(request);
    }
    
    // Static assets (CSS, JS, fonts, images)
    if (isStaticAsset(request)) {
      return await cacheFirst(request, STATIC_CACHE_NAME);
    }
    
    // Network-first resources
    if (isNetworkFirst(request)) {
      return await networkFirst(request, DYNAMIC_CACHE_NAME);
    }
    
    // Cache-first resources
    if (isCacheFirst(request)) {
      return await cacheFirst(request, STATIC_CACHE_NAME);
    }
    
    // Default: Network first with cache fallback for navigation
    if (request.mode === 'navigate') {
      return await networkFirstWithOfflineFallback(request);
    }
    
    // Everything else: stale-while-revalidate
    return await staleWhileRevalidate(request, DYNAMIC_CACHE_NAME);
    
  } catch (error) {
    console.error('[ServiceWorker] Fetch failed:', error);
    return await handleFetchError(request, error);
  }
}

/**
 * Handle API requests with specific caching strategies
 * 
 * @param {Request} request - API request
 * @returns {Promise<Response>} API response
 */
async function handleApiRequest(request) {
  const url = new URL(request.url);
  
  // Network-first for auth and write operations
  if (isNetworkFirst(request)) {
    return await networkFirst(request);
  }
  
  // Cache-first for read operations with short TTL
  if (isApiCacheable(request)) {
    const response = await staleWhileRevalidate(request, API_CACHE_NAME, CACHE_DURATIONS.api);
    
    // Add cache metadata
    if (response && response.ok) {
      const clonedResponse = response.clone();
      clonedResponse.headers.set('X-Cache-Source', 'service-worker');
    }
    
    return response;
  }
  
  // Default: network only
  return fetch(request);
}

/**
 * Network First Strategy
 * Try network first, fallback to cache if network fails
 * 
 * @param {Request} request - The request
 * @param {string} [cacheName] - Cache name to use
 * @returns {Promise<Response>} Response from network or cache
 */
async function networkFirst(request, cacheName = DYNAMIC_CACHE_NAME) {
  try {
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok && cacheName) {
      const cache = await caches.open(cacheName);
      await cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.log('[ServiceWorker] Network failed, trying cache:', request.url);
    
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    throw error;
  }
}

/**
 * Cache First Strategy
 * Try cache first, fallback to network if not cached
 * 
 * @param {Request} request - The request
 * @param {string} cacheName - Cache name to use
 * @returns {Promise<Response>} Response from cache or network
 */
async function cacheFirst(request, cacheName) {
  const cachedResponse = await caches.match(request);
  
  if (cachedResponse) {
    // Check if cache is expired (for dynamic content)
    if (await isCacheExpired(cachedResponse, cacheName)) {
      console.log('[ServiceWorker] Cache expired, fetching fresh:', request.url);
      return await networkFirst(request, cacheName);
    }
    
    return cachedResponse;
  }
  
  // Not in cache, fetch from network
  const networkResponse = await fetch(request);
  
  if (networkResponse.ok) {
    const cache = await caches.open(cacheName);
    await cache.put(request, networkResponse.clone());
  }
  
  return networkResponse;
}

/**
 * Stale While Revalidate Strategy
 * Return cache immediately, update cache in background
 * 
 * @param {Request} request - The request
 * @param {string} cacheName - Cache name to use
 * @param {number} [maxAge] - Maximum age in milliseconds
 * @returns {Promise<Response>} Response from cache
 */
async function staleWhileRevalidate(request, cacheName, maxAge) {
  const cachedResponse = await caches.match(request);
  
  // Background update
  const fetchPromise = (async () => {
    try {
      const networkResponse = await fetch(request);
      
      if (networkResponse.ok) {
        const cache = await caches.open(cacheName);
        await cache.put(request, networkResponse.clone());
      }
      
      return networkResponse;
    } catch (error) {
      console.warn('[ServiceWorker] Background update failed:', error);
      return null;
    }
  })();
  
  // Return cached response immediately if available and not expired
  if (cachedResponse) {
    if (!maxAge || !(await isCacheExpired(cachedResponse, cacheName, maxAge))) {
      return cachedResponse;
    }
  }
  
  // If no cache or expired, wait for network
  return await fetchPromise || cachedResponse;
}

/**
 * Network first with offline page fallback for navigation
 * 
 * @param {Request} request - Navigation request
 * @returns {Promise<Response>} Response from network or offline page
 */
async function networkFirstWithOfflineFallback(request) {
  try {
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cache successful navigations
      const cache = await caches.open(DYNAMIC_CACHE_NAME);
      await cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.log('[ServiceWorker] Navigation failed, checking cache:', request.url);
    
    // Try cache first
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Show offline page
    const offlineResponse = await caches.match(OFFLINE_PAGE);
    if (offlineResponse) {
      return offlineResponse;
    }
    
    // Last resort: create minimal offline response
    return new Response(
      `
      <!DOCTYPE html>
      <html>
        <head>
          <title>PrettiOps - Offline</title>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
                   text-align: center; padding: 50px; background: #f8fafc; }
            .container { max-width: 400px; margin: 0 auto; }
            .icon { font-size: 64px; margin-bottom: 20px; }
            h1 { color: #334155; margin-bottom: 16px; }
            p { color: #64748b; line-height: 1.6; }
          </style>
        </head>
        <body>
          <div class="container">
            <div class="icon">📱</div>
            <h1>You're Offline</h1>
            <p>PrettiOps isn't available right now. Please check your connection and try again.</p>
            <button onclick="location.reload()" style="margin-top: 20px; padding: 10px 20px; 
                    background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer;">
              Try Again
            </button>
          </div>
        </body>
      </html>
      `,
      {
        headers: {
          'Content-Type': 'text/html',
          'Cache-Control': 'no-cache'
        }
      }
    );
  }
}

/**
 * Handle fetch errors
 * 
 * @param {Request} request - The failed request
 * @param {Error} error - The error that occurred
 * @returns {Promise<Response>} Error response
 */
async function handleFetchError(request, error) {
  console.error('[ServiceWorker] Fetch error:', error);
  
  // Try to return cached version
  const cachedResponse = await caches.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }
  
  // Return appropriate error response
  if (request.mode === 'navigate') {
    return await caches.match(OFFLINE_PAGE) || 
           new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
  }
  
  return new Response('Network Error', { status: 503, statusText: 'Service Unavailable' });
}

/**
 * Check if cache entry is expired
 * 
 * @param {Response} response - Cached response
 * @param {string} cacheName - Cache name
 * @param {number} [maxAge] - Maximum age in milliseconds
 * @returns {Promise<boolean>} True if expired
 */
async function isCacheExpired(response, cacheName, maxAge) {
  if (!maxAge) {
    maxAge = CACHE_DURATIONS[cacheName.includes('static') ? 'static' : 
                             cacheName.includes('api') ? 'api' : 'dynamic'];
  }
  
  const dateHeader = response.headers.get('date');
  if (!dateHeader) return false;
  
  const cacheDate = new Date(dateHeader);
  const now = new Date();
  
  return (now.getTime() - cacheDate.getTime()) > maxAge;
}

/**
 * Utility functions to classify requests
 */

function isStaticAsset(request) {
  return CACHE_FIRST.some(pattern => pattern.test(request.url));
}

function isNetworkFirst(request) {
  return NETWORK_FIRST.some(pattern => pattern.test(request.url));
}

function isCacheFirst(request) {
  return CACHE_FIRST.some(pattern => pattern.test(request.url));
}

function isApiCacheable(request) {
  return API_PATTERNS.some(pattern => pattern.test(request.url));
}

/**
 * Background Sync Event Handler
 * Handles background sync events for offline actions
 */
self.addEventListener('sync', (event) => {
  console.log('[ServiceWorker] Background sync:', event.tag);
  
  if (event.tag === 'snippet-save') {
    event.waitUntil(syncSnippets());
  }
  
  if (event.tag === 'analytics-sync') {
    event.waitUntil(syncAnalytics());
  }
});

/**
 * Sync offline snippet saves
 */
async function syncSnippets() {
  try {
    // Get pending snippet saves from IndexedDB or localStorage
    const pendingSaves = await getPendingSnippetSaves();
    
    for (const save of pendingSaves) {
      try {
        const response = await fetch('/api/snippets', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(save.data),
        });
        
        if (response.ok) {
          await removePendingSnippetSave(save.id);
          console.log('[ServiceWorker] Synced snippet save:', save.id);
        }
      } catch (error) {
        console.error('[ServiceWorker] Failed to sync snippet save:', error);
      }
    }
  } catch (error) {
    console.error('[ServiceWorker] Background sync failed:', error);
  }
}

/**
 * Sync offline analytics data
 */
async function syncAnalytics() {
  try {
    // Implementation would sync offline analytics data
    console.log('[ServiceWorker] Analytics sync completed');
  } catch (error) {
    console.error('[ServiceWorker] Analytics sync failed:', error);
  }
}

/**
 * Push Notification Event Handler
 * Handles push notifications from the server
 */
self.addEventListener('push', (event) => {
  console.log('[ServiceWorker] Push received:', event);
  
  if (!event.data) return;
  
  const data = event.data.json();
  const options = {
    body: data.body || 'You have a new notification',
    icon: '/icons/icon-192x192.png',
    badge: '/icons/badge-96x96.png',
    tag: data.tag || 'general',
    data: data.data || {},
    actions: data.actions || [],
    requireInteraction: data.requireInteraction || false,
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title || 'PrettiOps', options)
  );
});

/**
 * Notification Click Event Handler
 */
self.addEventListener('notificationclick', (event) => {
  console.log('[ServiceWorker] Notification click:', event);
  
  event.notification.close();
  
  const data = event.notification.data || {};
  let url = data.url || '/dashboard';
  
  if (event.action) {
    url = data.actions?.[event.action]?.url || url;
  }
  
  event.waitUntil(
    clients.openWindow(url)
  );
});

/**
 * Message Event Handler
 * Handles messages from the main thread
 */
self.addEventListener('message', (event) => {
  console.log('[ServiceWorker] Message received:', event.data);
  
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'CLEAR_CACHE') {
    event.waitUntil(clearAllCaches());
  }
  
  if (event.data && event.data.type === 'CACHE_SNIPPET') {
    event.waitUntil(cacheSnippet(event.data.snippet));
  }
});

/**
 * Clear all caches
 */
async function clearAllCaches() {
  const cacheNames = await caches.keys();
  await Promise.all(
    cacheNames
      .filter(name => name.startsWith('prettiops-'))
      .map(name => caches.delete(name))
  );
  console.log('[ServiceWorker] All caches cleared');
}

/**
 * Cache a specific snippet for offline access
 * 
 * @param {Object} snippet - Snippet data to cache
 */
async function cacheSnippet(snippet) {
  try {
    const cache = await caches.open(API_CACHE_NAME);
    const response = new Response(JSON.stringify(snippet), {
      headers: {
        'Content-Type': 'application/json',
        'Date': new Date().toISOString(),
      },
    });
    
    await cache.put(`/api/snippets/${snippet.id}`, response);
    console.log('[ServiceWorker] Snippet cached for offline access:', snippet.id);
  } catch (error) {
    console.error('[ServiceWorker] Failed to cache snippet:', error);
  }
}

/**
 * IndexedDB helper functions for offline data
 * These would be implemented based on your specific offline data needs
 */

async function getPendingSnippetSaves() {
  // Implementation would get pending saves from IndexedDB
  return [];
}

async function removePendingSnippetSave(id) {
  // Implementation would remove completed save from IndexedDB
  console.log('Removed pending save:', id);
}

console.log('[ServiceWorker] Service worker script loaded');