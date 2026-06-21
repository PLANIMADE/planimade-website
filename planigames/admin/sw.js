/* PLANIGAMES Admin Service Worker — network-first, Scope /admin/.
   Lädt online IMMER frisch; cacht nur statische Assets (CSS/JS/Bilder/Fonts)
   als Offline-/Tempo-Reserve. Admin-Seiten (HTML/PHP) werden NIE gecacht
   (keine sensiblen Inhalte auf dem Gerät). */
const CACHE = "pg-admin-v1";

self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (e) => e.waitUntil(self.clients.claim()));

self.addEventListener("fetch", (e) => {
  const req = e.request;
  if (req.method !== "GET") return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  const isStatic = /\.(css|js|png|jpg|jpeg|webp|svg|gif|woff2?|ico)$/i.test(url.pathname);
  e.respondWith(
    fetch(req)
      .then((res) => {
        if (isStatic && res && res.status === 200 && res.type === "basic") {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
        }
        return res;
      })
      .catch(() => caches.match(req))
  );
});
