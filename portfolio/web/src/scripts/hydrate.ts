/**
 * Füllt die Stellen der Seite, die aus dem Dashboard kommen: Name, Intro,
 * Verfügbarkeits-Badge, Social-Links, Skills, Prozess, Impressum.
 *
 * Der Rohbau steht statisch im HTML (gut für SEO und Ladezeit), die
 * gepflegten Inhalte werden hier eingesetzt.
 */

import { fetchSettings, fetchTestimonials } from '../lib/api';
import type { Settings } from '../lib/types';

/** Statusfarben kommen aus CSS-Variablen – damit stimmt der Kontrast in beiden Themes. */
const statusColors: Record<string, string> = {
  open: 'var(--state-ok)',
  limited: 'var(--state-warn)',
  closed: 'var(--state-bad)',
};

function setText(selector: string, value: string): void {
  document.querySelectorAll<HTMLElement>(selector).forEach((element) => {
    element.textContent = value;
  });
}

function renderParagraphs(container: HTMLElement, text: string): void {
  container.innerHTML = '';
  text
    .split(/\n{2,}/)
    .map((part) => part.trim())
    .filter(Boolean)
    .forEach((part) => {
      const paragraph = document.createElement('p');
      paragraph.textContent = part;
      container.append(paragraph);
    });
}

/**
 * Ersetzt alle Texte, die im Dashboard gepflegt wurden.
 *
 * Im HTML steht jeweils der Standardtext – ist im Dashboard nichts (oder
 * etwas Leeres) hinterlegt, bleibt er stehen. Dadurch kann eine versehentlich
 * geleerte Eingabe die Seite nie „ausräumen".
 */
function fillTexts(settings: Settings): void {
  const texts = settings.texts ?? {};

  document.querySelectorAll<HTMLElement>('[data-text]').forEach((element) => {
    const key = element.dataset.text;
    if (!key) return;

    const value = texts[key];
    if (typeof value === 'string' && value.trim() !== '') {
      element.textContent = value;
    }
  });
}

/** Laufband auf der Startseite aus dem Dashboard befüllen. */
function fillMarquee(settings: Settings): void {
  const track = document.querySelector<HTMLElement>('[data-marquee-track]');
  if (!track) return;

  const items = (settings.marquee ?? []).map((item) => item.trim()).filter(Boolean);
  if (items.length === 0) {
    track.closest('.marquee')?.remove();
    return;
  }

  // Zweimal ausgeben: Die Animation läuft bis -50 % und springt unsichtbar zurück.
  track.innerHTML = [...items, ...items]
    .map(
      (item) => `
      <span class="flex shrink-0 items-center gap-8 px-8">
        <span class="whitespace-nowrap text-sm font-medium tracking-tight text-muted">${escapeHtml(item)}</span>
        <span class="text-accent/60">✦</span>
      </span>`,
    )
    .join('');
}

/**
 * Ersetzt das Monogramm durch das hochgeladene Logo.
 *
 * Betrifft alle Stellen mit `data-mark`: die Marke in der Navigation, den
 * Fuß des Lebenslaufs und den Sperrbildschirm. Ohne Logo bleibt ein
 * Monogramm stehen – dann aber aus den Initialen des eingetragenen Namens
 * und nicht als fest verdrahtetes „DM".
 */
function fillMark(settings: Settings): void {
  const marks = document.querySelectorAll<HTMLElement>('[data-mark]');
  if (marks.length === 0) return;

  const logo = settings.logo?.image ?? null;

  const initialen = settings.name
    .split(/\s+/)
    .filter((teil) => teil !== '')
    .slice(0, 2)
    .map((teil) => teil[0]?.toUpperCase() ?? '')
    .join('');

  marks.forEach((mark) => {
    if (!logo) {
      if (initialen !== '') mark.textContent = initialen;
      return;
    }

    const bild = document.createElement('img');
    bild.src = logo.thumbUrl ?? logo.url;
    bild.alt = settings.logo.alt || settings.name;
    // Vollständig zeigen statt beschneiden: Ein Logo ist selten quadratisch.
    bild.className = 'h-full w-full object-contain';
    mark.replaceChildren(bild);
    mark.classList.add('is-logo');
  });
}

function fillPortrait(settings: Settings): void {
  const figure = document.querySelector<HTMLElement>('[data-portrait]');
  if (!figure) return;

  const portrait = settings.portrait?.image;
  if (!portrait) {
    figure.remove();
    return;
  }

  const image = figure.querySelector<HTMLImageElement>('[data-portrait-image]');
  const caption = figure.querySelector<HTMLElement>('[data-portrait-caption]');

  if (image) {
    image.src = portrait.thumbUrl ?? portrait.url;
    if (portrait.srcset) {
      image.srcset = portrait.srcset;
      image.sizes = '(max-width: 1024px) 100vw, 24rem';
    }
    image.alt = portrait.alt || settings.name;
  }

  if (caption) {
    const text = settings.portrait.caption.trim();
    caption.textContent = text;
    caption.hidden = text === '';
  }

  figure.classList.remove('hidden');
}

function fillAvailability(settings: Settings): void {
  // Abgeschaltet: Badge samt Beschriftung restlos entfernen, damit kein
  // leerer Rahmen stehen bleibt.
  if (settings.availability.visible === false) {
    document.querySelectorAll('[data-availability], [data-availability-detail]').forEach((element) => {
      element.closest('[data-availability-block]')?.remove();
      element.remove();
    });

    return;
  }

  const color = statusColors[settings.availability.status] ?? statusColors.open!;
  const pulse = settings.availability.status === 'open';

  document.querySelectorAll<HTMLElement>('[data-availability]').forEach((badge) => {
    const dot = badge.querySelector<HTMLElement>('[data-availability-dot]');
    const label = badge.querySelector<HTMLElement>('[data-availability-label]');

    if (dot) {
      dot.className = `h-1.5 w-1.5 rounded-full ${pulse ? 'animate-pulse' : ''}`;
      dot.style.background = color;
    }
    if (label) {
      label.textContent = settings.availability.label;
      label.className = 'text-xs font-medium';
      label.style.color = color;
    }

    badge.classList.remove('opacity-0');
  });

  // Der Detailtext steht je nach Seite innerhalb oder außerhalb des Badges.
  setText('[data-availability-detail]', settings.availability.detail);
}

function fillSocials(settings: Settings): void {
  const active = settings.socials.filter((social) => social.url.trim() !== '');

  document.querySelectorAll<HTMLElement>('[data-socials]').forEach((container) => {
    container.innerHTML = '';

    if (active.length === 0) {
      const item = document.createElement('li');
      item.className = 'text-sm text-faint';
      item.textContent = 'Links folgen';
      container.append(item);
      return;
    }

    active.forEach((social) => {
      const item = document.createElement('li');
      const link = document.createElement('a');
      link.href = social.url;
      link.target = '_blank';
      link.rel = 'noopener noreferrer me';
      link.className = 'text-muted transition-colors hover:text-ink';
      link.dataset.cursor = 'hover';
      link.textContent = social.label;
      item.append(link);
      container.append(item);
    });
  });
}

function fillMailLinks(settings: Settings): void {
  document.querySelectorAll<HTMLAnchorElement>('[data-mail-link]').forEach((link) => {
    link.href = `mailto:${settings.email}`;
    if (link.textContent?.trim() === '…' || link.textContent?.trim() === '') {
      link.textContent = settings.email;
    }
  });
}

function fillSkills(settings: Settings): void {
  const container = document.querySelector<HTMLElement>('[data-skills]');
  if (!container) return;

  container.innerHTML = settings.skills
    .map(
      (group, index) => `
      <article class="surface-card group relative overflow-hidden p-7 transition-colors duration-500 hover:border-line-strong" data-reveal data-reveal-delay="${index * 80}">
        <span class="label-mono">0${index + 1}</span>
        <h3 class="mt-4 text-xl font-bold tracking-tight text-ink">${escapeHtml(group.title)}</h3>
        <p class="mt-3 text-sm leading-relaxed text-muted">${escapeHtml(group.description)}</p>
        <ul class="mt-6 flex flex-wrap gap-2">
          ${group.items
            .map(
              (item) =>
                `<li class="rounded-full border border-line px-2.5 py-1 font-mono text-[0.625rem] uppercase tracking-wider text-faint">${escapeHtml(item)}</li>`,
            )
            .join('')}
        </ul>
        <div class="pointer-events-none absolute -right-16 -top-16 h-40 w-40 rounded-full bg-accent/10 opacity-0 blur-2xl transition-opacity duration-700 group-hover:opacity-100"></div>
      </article>`,
    )
    .join('');
}

function fillProcess(settings: Settings): void {
  const container = document.querySelector<HTMLElement>('[data-process]');
  if (!container) return;

  container.innerHTML = settings.process
    .map(
      (step, index) => `
      <li class="relative border-t border-line py-8 md:grid md:grid-cols-[6rem_1fr_2fr] md:items-baseline md:gap-8" data-reveal data-reveal-delay="${index * 70}">
        <span class="label-mono">Schritt ${index + 1}</span>
        <h3 class="mt-2 text-lg font-semibold tracking-tight text-ink md:mt-0">${escapeHtml(step.title)}</h3>
        <p class="mt-2 text-sm leading-relaxed text-muted md:mt-0">${escapeHtml(step.description)}</p>
      </li>`,
    )
    .join('');
}

function fillLegal(settings: Settings): void {
  const legal = settings.legal;
  setText('[data-legal-company]', legal.company || settings.name);
  setText('[data-legal-street]', legal.street || '— bitte im Dashboard ergänzen —');
  setText('[data-legal-city]', legal.city || '');
  setText('[data-legal-email]', legal.email || settings.email);
  setText('[data-legal-phone]', legal.phone || '—');
  setText('[data-legal-vat]', legal.vatId || '—');
}

async function fillTestimonials(): Promise<void> {
  const container = document.querySelector<HTMLElement>('[data-testimonials]');
  if (!container) return;

  const testimonials = await fetchTestimonials().catch(() => []);
  const section = container.closest('section');

  if (testimonials.length === 0) {
    section?.remove();
    return;
  }

  container.innerHTML = testimonials
    .map(
      (item, index) => `
      <figure class="surface-card flex h-full flex-col justify-between p-8" data-reveal data-reveal-delay="${index * 80}">
        <blockquote class="text-base leading-relaxed text-ink">„${escapeHtml(item.quote)}"</blockquote>
        <figcaption class="mt-8 flex items-center gap-3">
          ${
            item.avatarUrl
              ? `<img src="${escapeHtml(item.avatarUrl)}" alt="" width="40" height="40" loading="lazy" class="h-10 w-10 rounded-full object-cover">`
              : `<span class="grid h-10 w-10 place-items-center rounded-full border border-line font-mono text-xs text-faint">${escapeHtml(item.author.slice(0, 2).toUpperCase())}</span>`
          }
          <span>
            <span class="block text-sm font-medium text-ink">${escapeHtml(item.author)}</span>
            <span class="block text-xs text-faint">${escapeHtml([item.role, item.company].filter(Boolean).join(' · '))}</span>
          </span>
        </figcaption>
      </figure>`,
    )
    .join('');
}

export async function initHydration(): Promise<void> {
  let settings: Settings;
  try {
    settings = await fetchSettings();
  } catch {
    // Ohne API bleibt der statisch gerenderte Rohbau stehen – kein Blocker.
    document.querySelectorAll<HTMLElement>('[data-availability]').forEach((badge) => badge.remove());
    return;
  }

  // Zuerst die festen Texte – danach überschreiben die spezielleren
  // Bausteine (Name, Rolle …) gezielt einzelne Stellen.
  fillTexts(settings);
  fillMarquee(settings);
  fillPortrait(settings);
  fillMark(settings);

  setText('[data-name]', settings.name);

  // Der Hero setzt Vor- und Nachname in zwei getrennte Zeilen.
  const [firstName, ...restName] = settings.name.split(' ');
  setText('[data-name-first]', firstName ?? settings.name);
  if (restName.length > 0) setText('[data-name-last]', restName.join(' '));

  setText('[data-role]', settings.role);
  setText('[data-location]', settings.location);
  setText('[data-tagline]', settings.tagline);

  document.querySelectorAll<HTMLElement>('[data-intro]').forEach((element) => {
    renderParagraphs(element, settings.intro);
  });

  fillAvailability(settings);
  fillSocials(settings);
  fillMailLinks(settings);
  fillSkills(settings);
  fillProcess(settings);
  fillLegal(settings);
  await fillTestimonials();

  document.dispatchEvent(new CustomEvent('dm:hydrated', { detail: settings }));
}

function escapeHtml(value: string): string {
  const div = document.createElement('div');
  div.textContent = value;

  return div.innerHTML;
}
