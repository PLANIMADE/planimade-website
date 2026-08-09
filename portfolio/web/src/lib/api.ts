import type { Project, Settings, Testimonial } from './types';

/**
 * Zugriff auf die PHP-API.
 *
 * Alles läuft über relative Pfade – im Dev-Betrieb leitet Vite `/api` an den
 * lokalen PHP-Server weiter, in Produktion liegt beides unter derselben Domain.
 */

const cache = new Map<string, Promise<unknown>>();

/**
 * Normalerweise laufen Anfragen über die kurze Adresse `/api/<route>`. Fehlt
 * auf dem Server die Umschreibung aus `api/.htaccess` – FTP-Programme lassen
 * Dateien mit führendem Punkt gerne aus –, greift der direkte Weg über
 * index.php. Ohne diesen Notweg bliebe die Seite bei ihren eingebauten
 * Standardtexten stehen, ohne dass man den Grund sähe.
 */
let direkterWeg = false;

function apiUrl(path: string): string {
  if (!direkterWeg) {
    return `/api/${path}`;
  }

  const [route = '', query] = path.split('?');

  return `/api/index.php?_route=${encodeURIComponent(route)}${query ? `&${query}` : ''}`;
}

async function get<T>(path: string): Promise<T> {
  let response = await fetch(apiUrl(path), {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });

  // Eine HTML-Fehlerseite statt JSON heißt: Der Server kennt die kurze
  // Adresse nicht. Einmal umschalten, danach bleibt es dabei.
  if (!direkterWeg && response.status === 404 && !(response.headers.get('content-type') ?? '').includes('json')) {
    direkterWeg = true;
    response = await fetch(apiUrl(path), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
  }

  if (!response.ok) {
    throw new Error(`API-Fehler ${response.status} bei /api/${path}`);
  }

  return (await response.json()) as T;
}

/** Gleiche Anfrage nur einmal pro Seitenaufruf stellen. */
function cached<T>(key: string, loader: () => Promise<T>): Promise<T> {
  if (!cache.has(key)) {
    cache.set(
      key,
      loader().catch((error: unknown) => {
        cache.delete(key);
        throw error;
      }),
    );
  }

  return cache.get(key) as Promise<T>;
}

export function fetchProjects(): Promise<{ projects: Project[]; categories: string[] }> {
  return cached('projects', () => get<{ projects: Project[]; categories: string[] }>('projects'));
}

export function fetchProject(slug: string): Promise<{ project: Project }> {
  return cached(`project:${slug}`, () => get<{ project: Project }>(`projects/${encodeURIComponent(slug)}`));
}

export function fetchSettings(): Promise<Settings> {
  return cached('settings', async () => (await get<{ settings: Settings }>('settings')).settings);
}

export function fetchTestimonials(): Promise<Testimonial[]> {
  return cached('testimonials', async () => (await get<{ testimonials: Testimonial[] }>('testimonials')).testimonials);
}

export interface ContactPayload {
  name: string;
  email: string;
  subject?: string;
  budget?: string;
  message: string;
  website?: string;
}

export async function sendContact(
  payload: ContactPayload,
): Promise<{ ok: boolean; error?: string; fields?: Record<string, string> }> {
  const response = await fetch(apiUrl('contact'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });

  const data = (await response.json().catch(() => ({}))) as {
    ok?: boolean;
    error?: string;
    fields?: Record<string, string>;
  };

  return { ok: response.ok && data.ok === true, error: data.error, fields: data.fields };
}

/** Cookiefreies Zählpixel-Ersatzsignal – bewusst „fire and forget". */
export function track(type: string, extra: Record<string, unknown> = {}): void {
  const body = JSON.stringify({
    type,
    path: location.pathname,
    referrer: document.referrer,
    device: window.matchMedia('(max-width: 640px)').matches
      ? 'mobile'
      : window.matchMedia('(max-width: 1024px)').matches
        ? 'tablet'
        : 'desktop',
    ...extra,
  });

  if (navigator.sendBeacon) {
    navigator.sendBeacon('/api/events', new Blob([body], { type: 'application/json' }));
    return;
  }

  void fetch('/api/events', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body,
    keepalive: true,
  }).catch(() => undefined);
}
