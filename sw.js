self.addEventListener('install', e => {
  e.waitUntil(
    caches.open('reversi-pwa-v2').then(cache => {
      // Removing './' since it might throw a 404/redirect error on some setups
      return cache.addAll([
        './index.html',
        './style.css',
        './app.js',
        './icon.png'
      ]);
    })
  );
});

self.addEventListener('fetch', e => {
  e.respondWith(
    caches.match(e.request).then(response => {
      return response || fetch(e.request);
    })
  );
});
