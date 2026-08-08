/** Datenformen, die die PHP-API liefert. */

export interface MediaItem {
  id: number;
  url: string;
  thumbUrl: string | null;
  filename: string;
  mime: string;
  kind: 'image' | 'video' | 'model';
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
  accent: string;
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

export interface Settings {
  name: string;
  role: string;
  location: string;
  tagline: string;
  intro: string;
  availability: { status: 'open' | 'limited' | 'closed'; label: string; detail: string };
  email: string;
  phone: string;
  socials: ProjectLink[];
  skills: SkillGroup[];
  process: ProcessStep[];
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
