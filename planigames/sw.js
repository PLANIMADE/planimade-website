/* PLANIGAMES Service Worker — network-first.
   Lädt online IMMER frisch (keine veralteten Dateien!), nutzt den Cache nur,
   wenn man offline ist. Admin & Nicht-GET werden nie gecacht. */
const CACHE = "pg-cache-v1";

self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (e) => e.waitUntil(self.clients.claim()));

self.addEventListener("fetch", (e) => {
  const req = e.request;
  if (req.method !== "GET") return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;          // CDNs/extern normal lassen
  if (url.pathname.startsWith("/admin")) return;        // Admin nie cachen

  e.respondWith(
    fetch(req)
      .then((res) => {
        if (res && res.status === 200 && res.type === "basic") {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
        }
        return res;
      })
      .catch(() =>
        caches.match(req).then(
          (m) => m || (req.mode === "navigate" ? caches.match("/index.html") : Response.error())
        )
      )
  );
});
