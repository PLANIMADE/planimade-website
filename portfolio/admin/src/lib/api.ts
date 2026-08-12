/**
 * API-Client des Dashboards.
 *
 * Der CSRF-Token wird beim Login bzw. beim Sitzungscheck geholt und danach
 * automatisch an jede schreibende Anfrage gehängt.
 */

export interface MediaItem {
  id: number;
  url: string;
  thumbUrl: string | null;
  srcset: string | null;
  filename: string;
  mime: string;
  kind: 'image' | 'video' | 'model' | 'document';
  size: number;
  width: number | null;
  height: number | null;
  alt: string;
  createdAt: string | null;
}

export interface GalleryItem {
  caption: string;
  layout: 'full' | 'half' | 'wide';
  media: MediaItem;
}

export interface Project {
  id: number;
  slug: string;
  title: string;
  subtitle: string;
  summary: string;
  body: string;
  category: string;
  client: string;
  role: string;
  year: number | null;
  tools: string[];
  tags: string[];
  links: Array<{ label: string; url: string }>;
  metrics: Array<{ label: string; value: string }>;
  palette: Array<{ name: string; hex: string }>;
  accent: string;
  display: 'cover' | 'contain';
  cardFormat: 'landscape' | 'square' | 'portrait';
  /** Blickpunkt des Kachelausschnitts als CSS-Wert, z. B. '50% 30%'. */
  coverFocus: string;
  /** Vergrößerung des Kachelausschnitts, 1 = unverändert. */
  coverZoom: number;
  status: 'draft' | 'published';
  publishAt: string | null;
  featured: boolean;
  position: number;
  views: number;
  cover: MediaItem | null;
  preview: MediaItem | null;
  model: MediaItem | null;
  before: MediaItem | null;
  after: MediaItem | null;
  gallery: GalleryItem[];
  createdAt: string;
  updatedAt: string;
  publishedAt: string | null;
}

export interface Message {
  id: number;
  name: string;
  email: string;
  subject: string;
  budget: string;
  body: string;
  status: 'new' | 'read' | 'archived';
  /** 1 = übergeben, -1 = abgelehnt, -2 = nicht versucht, 0 = älter als diese Zählung. */
  notified: number;
  createdAt: string;
}

export interface Testimonial {
  id: number;
  author: string;
  role: string;
  company: string;
  quote: string;
  status: 'published' | 'hidden';
  position: number;
  avatarId: number | null;
  avatarUrl: string | null;
}

export interface HeroSettings {
  mode: 'type' | 'showreel';
  mediaId: number | null;
  posterId: number | null;
  overlay: number;
  showTitle: boolean;
  video: MediaItem | null;
  poster: MediaItem | null;
}

export interface ExpertiseItem {
  name: string;
  level: number;
  note: string;
  group: string;
}

export interface TimelineEntry {
  period: string;
  title: string;
  org: string;
  location: string;
  description: string;
  type: 'work' | 'education' | 'project';
  tags: string[];
}

export interface Resume {
  headline: string;
  summary: string;
  timeline: TimelineEntry[];
  languages: Array<{ name: string; level: string }>;
  facts: Array<{ label: string; value: string }>;
}

export interface SystemCheckItem {
  label: string;
  status: 'ok' | 'warn' | 'error';
  value: string;
  hint: string | null;
}

export interface SystemReport {
  checks: SystemCheckItem[];
  info: {
    databaseSize: string;
    uploadsSize: string;
    mediaCount: number;
    imagesWithoutVariants: number;
    imagesWithoutAlt: number;
    scheduledProjects: number;
    trashCount: number;
    serverTime: string;
    memoryLimit: string;
    maxExecutionTime: string;
  };
}

export interface Settings {
  name: string;
  role: string;
  location: string;
  tagline: string;
  intro: string;
  appearance: { defaultTheme: 'light' | 'dark' | 'system' };
  hero: HeroSettings;
  portrait: { mediaId: number | null; caption: string; image: MediaItem | null };
  /** Eigenes Logo statt des Monogramms – Navigation und Lebenslauf. */
  logo: {
    mediaId: number | null;
    alt: string;
    /** 'light' kehrt das Logo im hellen Design um, 'dark' im dunklen. */
    adapt: 'none' | 'light' | 'dark';
    image: MediaItem | null;
  };
  texts: Record<string, string>;
  marquee: string[];
  cv: {
    profile: string;
    /** Akzentfarbe des Dokuments als Hex-Wert. Leer = Farbe der Website. */
    accent: string;
    /** Papierfarbe des Dokuments – unabhängig vom Farbschema der Website. */
    theme: 'light' | 'dark';
    includePhoto: boolean;
    includeProjects: boolean;
    includeExpertise: boolean;
    details: Array<{ label: string; value: string }>;
    footer: string;
  };
  expertise: ExpertiseItem[];
  resume: Resume;
  availability: { visible: boolean; status: 'open' | 'limited' | 'closed'; label: string; detail: string };
  email: string;
  phone: string;
  socials: Array<{ label: string; url: string }>;
  skills: Array<{ title: string; description: string; items: string[] }>;
  process: Array<{ title: string; description: string }>;
  /** Adresse und Mailversand – früher in api/.env.php, jetzt im Dashboard. */
  site: {
    url: string;
    mailTo: string;
    mailFrom: string;
    mailEnabled: boolean;
    /** Eigenes Postfach für den Versand. Leer = über mail() des Servers. */
    smtpHost: string;
    smtpPort: number;
    smtpUser: string;
    smtpPass: string;
    smtpSecurity: 'auto' | 'tls' | 'ssl' | 'none';
  };
  seo: { title: string; description: string; keywords: string };
  legal: { company: string; street: string; city: string; email: string; phone: string; vatId: string };
  features: { sound: boolean; cursor: boolean; analytics: boolean; easterEgg: boolean };
}

/* ------------------------------------------------------------ Bewerbungs-Radar */

/** Ein Eintrag der Liste. Die Kürzel stammen aus der ursprünglichen Datei. */
export interface BewerbungEintrag {
  id: string;
  quelle: 'datei' | 'eigen';
  status: string;
  notiz: string;
  kontaktAm: string;
  gesendetAm: string | null;
  // Agentur
  n?: string;
  c?: string;
  r?: string;
  d?: number;
  u?: string;
  e?: string;
  p?: string;
  f?: string[];
  flag?: string;
  // Stelle
  role?: string;
  co?: string;
  loc?: string;
  tags?: string[];
  url?: string | null;
  note?: string;
  fit?: boolean;
}

export interface BewerbungVersand {
  absender: string;
  absenderName: string;
  host: string;
  port: number;
  benutzer: string;
  passwort: string;
  sicherheit: 'auto' | 'tls' | 'ssl' | 'none';
  imapHost: string;
  imapPort: number;
  imapBenutzer: string;
  imapPasswort: string;
  imapOrdner: string;
  hatPasswort: boolean;
}

export interface BewerbungDatei {
  name: string;
  groesse: number;
  url: string;
  kurz: string;
}

export interface BewerbungDaten {
  regionen: Array<{ id: string; km: string; city: string }>;
  links: Array<{ url: string; quelle: string; titel: string; hinweis: string }>;
  agenturen: BewerbungEintrag[];
  stellen: BewerbungEintrag[];
  statiAgentur: string[];
  statiStelle: string[];
  vorlage: { subj: string; body: string; att: string };
  versand: BewerbungVersand;
  dateien: BewerbungDatei[];
}

export interface Stats {
  analytics: {
    range: { days: number; since: string };
    totals: { views: number; visitors: number; projectViews: number };
    perDay: Array<{ day: string; views: number; visitors: number }>;
    topPages: Array<{ path: string; views: number }>;
    topProjects: Array<{ id: number; title: string; slug: string; views: number }>;
    readDepth: Array<{ id: number; title: string; slug: string; opened: number; finished: number; share: number }>;
    referrers: Array<{ referrer: string; views: number }>;
    devices: Array<{ device: string; views: number }>;
  };
  counts: { projects: number; published: number; drafts: number; media: number; unreadMessages: number };
  mostViewed: Array<{ id: number; title: string; slug: string; views: number }>;
}

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public fields?: Record<string, string>,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

let csrfToken = '';

/**
 * Normalerweise laufen Anfragen über die kurze Adresse `/api/<route>`. Fehlt
 * auf dem Server die Umschreibung aus `api/.htaccess`, greift der direkte Weg
 * über index.php – dann steht die Route in `_route`.
 */
let direkterWeg = false;

function apiUrl(path: string): string {
  if (!direkterWeg) {
    return `/api/${path}`;
  }

  // Eine bereits angehängte Abfrage (etwa `projects?drafts=1`) muss erhalten
  // bleiben – mitkodiert käme sie auf dem Server nie an.
  const [route = '', query] = path.split('?');

  return `/api/index.php?_route=${encodeURIComponent(route)}${query ? `&${query}` : ''}`;
}

export function setCsrfToken(token: string): void {
  csrfToken = token;
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const method = (options.method ?? 'GET').toUpperCase();
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');

  if (!(options.body instanceof FormData) && options.body !== undefined) {
    headers.set('Content-Type', 'application/json');
  }
  if (method !== 'GET' && csrfToken !== '') {
    headers.set('X-CSRF-Token', csrfToken);
  }

  const response = await fetch(apiUrl(path), { ...options, headers, credentials: 'same-origin' });

  // Antwortet der Server auf die kurze Adresse mit einer HTML-Fehlerseite,
  // fehlt auf ihm `api/.htaccess`. Dann einmal auf den direkten Weg über
  // index.php umschalten – danach läuft alles wie gewohnt weiter.
  if (!direkterWeg && response.status === 404 && !(response.headers.get('content-type') ?? '').includes('json')) {
    direkterWeg = true;

    return request<T>(path, options);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const data = (await response.json().catch(() => ({}))) as Record<string, unknown>;

  if (!response.ok) {
    throw new ApiError(
      typeof data.error === 'string' ? data.error : `Fehler ${response.status}`,
      response.status,
      data.fields as Record<string, string> | undefined,
    );
  }

  return data as T;
}

export const api = {
  // Sitzung
  me: () => request<{ user: { id: number; email: string; name: string } | null; csrfToken?: string }>('auth/me'),
  login: (email: string, password: string) =>
    request<{ user: { id: number; email: string; name: string }; csrfToken: string }>('auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),
  logout: () => request<{ ok: boolean }>('auth/logout', { method: 'POST' }),

  // Erste Einrichtung – nur relevant, solange es keinen Zugang gibt.
  setupRequired: () => request<{ required: boolean }>('auth/setup'),
  setup: (email: string, password: string, name: string, demo: boolean) =>
    request<{ user: { id: number; email: string; name: string }; csrfToken: string }>('auth/setup', {
      method: 'POST',
      body: JSON.stringify({ email, password, name, demo }),
    }),
  changePassword: (current: string, next: string) =>
    request<{ ok: boolean }>('auth/password', { method: 'POST', body: JSON.stringify({ current, next }) }),

  // Projekte
  projects: () => request<{ projects: Project[]; categories: string[] }>('projects?drafts=1'),
  // Beim Speichern werden Medien als IDs und die Galerie in Kurzform geschickt –
  // deshalb bewusst nicht `Partial<Project>`.
  createProject: (data: Record<string, unknown>) =>
    request<{ project: Project }>('projects', { method: 'POST', body: JSON.stringify(data) }),
  updateProject: (id: number, data: Record<string, unknown>) =>
    request<{ project: Project }>(`projects/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  deleteProject: (id: number) => request<{ ok: boolean }>(`projects/${id}`, { method: 'DELETE' }),
  reorderProjects: (ids: number[]) =>
    request<{ ok: boolean }>('projects-reorder', { method: 'POST', body: JSON.stringify({ ids }) }),

  // Medien
  media: (kind?: string) => request<{ media: MediaItem[] }>(`media${kind ? `?kind=${kind}` : ''}`),
  mediaWithoutAlt: () => request<{ media: MediaItem[] }>('media?missingAlt=1&limit=100'),
  uploadMedia: (file: File, alt = '') => {
    const form = new FormData();
    form.append('file', file);
    form.append('alt', alt);

    return request<{ media: MediaItem }>('media', { method: 'POST', body: form });
  },
  updateMedia: (id: number, alt: string) =>
    request<{ media: MediaItem }>(`media/${id}`, { method: 'PATCH', body: JSON.stringify({ alt }) }),
  deleteMedia: (id: number) => request<{ ok: boolean }>(`media/${id}`, { method: 'DELETE' }),

  // Nachrichten
  messages: (status?: string) =>
    request<{ messages: Message[]; unread: number }>(`messages${status ? `?status=${status}` : ''}`),
  setMessageStatus: (id: number, status: Message['status']) =>
    request<{ ok: boolean }>(`messages/${id}`, { method: 'PATCH', body: JSON.stringify({ status }) }),
  deleteMessage: (id: number) => request<{ ok: boolean }>(`messages/${id}`, { method: 'DELETE' }),

  // Kundenstimmen
  testimonials: () => request<{ testimonials: Testimonial[] }>('testimonials'),
  saveTestimonial: (id: number | null, data: Partial<Testimonial>) =>
    request<{ testimonial: Testimonial }>(id === null ? 'testimonials' : `testimonials/${id}`, {
      method: id === null ? 'POST' : 'PUT',
      body: JSON.stringify(data),
    }),
  deleteTestimonial: (id: number) => request<{ ok: boolean }>(`testimonials/${id}`, { method: 'DELETE' }),

  // Papierkorb
  trash: () => request<{ projects: Array<Project & { deletedAt: string; purgeAt: string }> }>('trash'),
  restoreProject: (id: number) => request<{ project: Project }>(`trash/${id}/restore`, { method: 'POST' }),
  purgeProject: (id: number) => request<{ ok: boolean }>(`trash/${id}`, { method: 'DELETE' }),

  // Systemcheck
  system: () => request<SystemReport>('system'),
  mailTest: () => request<{ ok: boolean; message: string }>('system/mail-test', { method: 'POST' }),
  optimizeImages: (limit = 20) =>
    request<{ optimized: number; remaining: number }>('system/optimize', {
      method: 'POST',
      body: JSON.stringify({ limit }),
    }),
  rebuildSocialCards: () => request<{ generated: number }>('system/social-cards', { method: 'POST' }),

  // Einstellungen & Auswertung
  settings: () => request<{ settings: Settings }>('settings'),
  saveSettings: (data: Partial<Settings>) =>
    request<{ settings: Settings }>('settings', { method: 'PUT', body: JSON.stringify(data) }),
  stats: (days = 30) => request<Stats>(`stats?days=${days}`),
  // Bewerbungs-Radar
  bewerbung: () => request<BewerbungDaten>('bewerbung'),
  bewerbungMerken: (id: string, data: Partial<Pick<BewerbungEintrag, 'status' | 'notiz' | 'kontaktAm'>>) =>
    request<{ eintrag: BewerbungEintrag }>(`bewerbung/eintrag/${encodeURIComponent(id)}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    }),
  bewerbungAnlegenViele: (zeilen: Array<Record<string, unknown>>) =>
    request<{ neu: number; uebersprungen: number }>('bewerbung/eintraege', {
      method: 'POST',
      body: JSON.stringify({ zeilen }),
    }),
  bewerbungAnlegen: (data: Record<string, unknown>) =>
    request<{ eintrag: BewerbungEintrag }>('bewerbung/eintrag', { method: 'POST', body: JSON.stringify(data) }),
  bewerbungBearbeiten: (id: string, data: Record<string, unknown>) =>
    request<{ eintrag: BewerbungEintrag }>(`bewerbung/eintrag/${encodeURIComponent(id)}/daten`, {
      method: 'PUT',
      body: JSON.stringify(data),
    }),
  bewerbungLoeschen: (id: string) =>
    request<void>(`bewerbung/eintrag/${encodeURIComponent(id)}`, { method: 'DELETE' }),
  bewerbungLoeschenViele: (ids: string[]) =>
    request<{ geloescht: number }>('bewerbung/loeschen', { method: 'POST', body: JSON.stringify({ ids }) }),
  bewerbungDubletten: () => request<{ ids: string[] }>('bewerbung/dubletten'),
  bewerbungNachschub: () => request<{ neu: number; ergaenzt: number }>('bewerbung/nachschub', { method: 'POST' }),
  bewerbungImport: (sicherung: unknown) =>
    request<{ 'übernommen': number; unbekannt: number }>('bewerbung/import', {
      method: 'POST',
      body: JSON.stringify(sicherung),
    }),
  bewerbungVorlage: (data: { subj: string; body: string; att: string }) =>
    request<{ vorlage: BewerbungDaten['vorlage'] }>('bewerbung/vorlage', { method: 'PUT', body: JSON.stringify(data) }),
  bewerbungVersand: (data: Partial<BewerbungVersand>) =>
    request<{ versand: BewerbungVersand }>('bewerbung/versand', { method: 'PUT', body: JSON.stringify(data) }),
  bewerbungVersandTest: () => request<{ ok: boolean; message: string }>('bewerbung/versand/test', { method: 'POST' }),
  bewerbungSenden: (ids: string[]) =>
    request<{ ergebnisse: Array<{ id: string; name: string; ok: boolean; meldung: string }> }>('bewerbung/senden', {
      method: 'POST',
      body: JSON.stringify({ ids }),
    }),
  bewerbungDateiHochladen: (datei: File) => {
    const body = new FormData();
    body.append('file', datei);

    return request<{ dateien: BewerbungDatei[] }>('bewerbung/dateien', { method: 'POST', body });
  },
  bewerbungDateiLoeschen: (name: string) =>
    request<{ dateien: BewerbungDatei[] }>(`bewerbung/dateien/${encodeURIComponent(name)}`, { method: 'DELETE' }),

  exportUrl: '/api/export',
};

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;

  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function formatDate(value: string | null): string {
  if (!value) return '—';

  return new Date(value).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
