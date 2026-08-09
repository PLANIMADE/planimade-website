/** Datenformen, die die PHP-API liefert. */

export interface MediaItem {
  id: number;
  url: string;
  thumbUrl: string | null;
  /** Fertiges srcset über alle erzeugten Bildgrößen, null bei Videos/Modellen. */
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

export interface ProjectLink {
  label: string;
  url: string;
}

export interface Metric {
  label: string;
  value: string;
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
  links: ProjectLink[];
  metrics: Metric[];
  /** Farbfelder für Branding-Arbeiten */
  palette: Array<{ name: string; hex: string }>;
  accent: string;
  /** 'cover' = formatfüllend beschnitten, 'contain' = vollständig gezeigt */
  display: 'cover' | 'contain';
  cardFormat: 'landscape' | 'square' | 'portrait';
  status: 'draft' | 'published';
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

export interface SkillGroup {
  title: string;
  description: string;
  items: string[];
}

export interface ProcessStep {
  title: string;
  description: string;
}

export interface ExpertiseItem {
  name: string;
  /** Selbsteinschätzung 0–100 */
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

export interface HeroSettings {
  mode: 'type' | 'showreel';
  mediaId: number | null;
  posterId: number | null;
  overlay: number;
  showTitle: boolean;
  video: MediaItem | null;
  poster: MediaItem | null;
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
  logo: { mediaId: number | null; alt: string; image: MediaItem | null };
  /** Schlüssel → Text. Die Schlüssel stehen im HTML als `data-text`. */
  texts: Record<string, string>;
  /** Wortliste des Laufbands auf der Startseite */
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
  availability: {
    /** Aus = das Status-Feld erscheint nirgends auf der Seite. */
    visible: boolean;
    status: 'open' | 'limited' | 'closed';
    label: string;
    detail: string;
  };
  email: string;
  phone: string;
  socials: ProjectLink[];
  skills: SkillGroup[];
  process: ProcessStep[];
  /** Adresse und Mailversand – früher in api/.env.php, jetzt im Dashboard. */
  site: { url: string; mailTo: string; mailFrom: string; mailEnabled: boolean };
  seo: { title: string; description: string; keywords: string };
  legal: {
    company: string;
    street: string;
    city: string;
    email: string;
    phone: string;
    vatId: string;
  };
  features: { sound: boolean; cursor: boolean; analytics: boolean; easterEgg: boolean };
}

export interface Testimonial {
  id: number;
  author: string;
  role: string;
  company: string;
  quote: string;
  avatarUrl: string | null;
}
