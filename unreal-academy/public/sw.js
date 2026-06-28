/* Unreal Academy – Service Worker (Offline-Fähigkeit).
   Strategie: Network-first mit Cache-Fallback. Online wird immer die frische
   Version geladen (kein veraltetes PWA-Problem); offline kommt der Cache. */

const CACHE = "ua-cache-v1";

self.addEventListener("install", () => {
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)));
      await self.clients.claim();
    })(),
  );
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return; // nur eigene Assets

  event.respondWith(
    (async () => {
      const cache = await caches.open(CACHE);
      try {
        const fresh = await fetch(req);
        if (fresh && fresh.status === 200) cache.put(req, fresh.clone());
        return fresh;
      } catch {
        const cached = await cache.match(req);
        if (cached) return cached;
        // Offline-Navigation: App-Shell (Scope-Wurzel) zurückgeben.
        if (req.mode === "navigate") {
          const shell = await cache.match(self.registration.scope);
          if (shell) return shell;
        }
        throw new Error("offline und nicht im Cache");
      }
    })(),
  );
});
