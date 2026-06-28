"use client";

import { useEffect } from "react";

const base = process.env.NEXT_PUBLIC_BASE_PATH || "";

/**
 * Registriert den Service Worker (nur im Browser/Produktion). Macht die App
 * installierbar und offline nutzbar. basePath-bewusst für GitHub-Pages-Unterpfad.
 */
export function ServiceWorkerRegister() {
  useEffect(() => {
    if (process.env.NODE_ENV !== "production") return;
    if (typeof navigator === "undefined" || !("serviceWorker" in navigator)) return;
    const onLoad = () => {
      navigator.serviceWorker
        .register(`${base}/sw.js`, { scope: `${base}/` })
        .catch(() => {
          /* Registrierung fehlgeschlagen – App läuft trotzdem (nur ohne Offline). */
        });
    };
    window.addEventListener("load", onLoad);
    return () => window.removeEventListener("load", onLoad);
  }, []);

  return null;
}
