/**
 * Service Worker - N.A Fresh Fruits & Coconuts
 * Offline Caching, Cache-First Static Assets, Safe Dynamic Routing
 */

const CACHE_NAME = 'na-coconuts-v1';

const PRECACHE_ASSETS = [
  '/',
  '/offline.html',
  '/manifest.json',
  '/favicon.ico',
  '/apple-touch-icon.png',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
  '/assets/icons/icon-maskable-512.png',
  '/assets/images/hero-coconut.png',
  '/assets/images/product-main.png',
  '/assets/images/cart-coconut.png'
];

// Install Event - Precaching core static resources
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(PRECACHE_ASSETS).catch((err) => {
        console.warn('PWA Precache warning:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// Activate Event - Clean up stale old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((name) => {
          if (name !== CACHE_NAME) {
            return caches.delete(name);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event - Safe routing with network-first for pages and offline fallback
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // 1. Only handle GET requests
  if (request.method !== 'GET') {
    return;
  }

  // 2. NEVER cache sensitive, dynamic, payment, or administrative routes
  const isPrivateOrDynamic = 
    url.pathname.startsWith('/admin') ||
    url.pathname.startsWith('/checkout') ||
    url.pathname.startsWith('/cart') ||
    url.pathname.startsWith('/payment') ||
    url.pathname.startsWith('/webhooks') ||
    url.pathname.startsWith('/account') ||
    url.pathname.startsWith('/login') ||
    url.pathname.startsWith('/register') ||
    url.pathname.startsWith('/install') ||
    url.pathname.startsWith('/api');

  if (isPrivateOrDynamic) {
    // Let request pass straight to network without any service worker caching
    return;
  }

  // 3. Static Assets (Images, Icons, Fonts, CSS) - Cache First with Network Fallback
  if (
    url.pathname.startsWith('/assets/') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.jpg') ||
    url.pathname.endsWith('.jpeg') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.ico') ||
    url.hostname.includes('cdn.tailwindcss.com') ||
    url.hostname.includes('cdn.jsdelivr.net')
  ) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        return fetch(request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const responseClone = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, responseClone);
            });
          }
          return networkResponse;
        }).catch(() => {
          return cachedResponse;
        });
      })
    );
    return;
  }

  // 4. Storefront Navigation Requests (HTML pages: '/', '/shop', etc.)
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((networkResponse) => {
          return networkResponse;
        })
        .catch(() => {
          // If network failed and offline, serve the offline fallback page
          return caches.match('/offline.html');
        })
    );
  }
});
