/**
 * Projektraster mit Filtern und Video-Vorschau beim Überfahren.
 *
 * Wird von der Startseite (gekürzt) und von /work/ (vollständig, mit Filtern)
 * genutzt. Die Karten kommen zur Laufzeit aus der API, damit neue Projekte aus
 * dem Dashboard ohne neuen Build erscheinen.
 */

import { fetchProjects, track } from '../lib/api';
import type { Project } from '../lib/types';
import { refreshMotion } from './motion';

function escapeHtml(value: string): string {
  const div = document.createElement('div');
  div.textContent = value;

  return div.innerHTML;
}

/** Seitenverhältnis der Kachel – Plakate sollen als Plakat erscheinen. */
const CARD_ASPECT: Record<string, string> = {
  landscape: 'aspect-[4/3]',
  square: 'aspect-square',
  portrait: 'aspect-[3/4]',
};

function cardMarkup(project: Project, index: number, size: 'large' | 'normal'): string {
  const cover = project.cover;
  const preview = project.preview;

  // Großformatige Kacheln bleiben quer, außer das Projekt wünscht ausdrücklich
  // ein anderes Format – sonst reißt ein Hochformat die Startseite auseinander.
  //
  // Flach statt hoch: Mit 16:10 über die volle Breite füllte die erste Kachel
  // fast den ganzen Bildschirm und alles darunter verschwand. Als Band bleibt
  // sie das Highlight, ohne die Seite zu verstopfen.
  const aspect =
    size === 'large' && project.cardFormat === 'landscape'
      ? 'aspect-[4/3] sm:aspect-[16/9] lg:aspect-[21/9]'
      : (CARD_ASPECT[project.cardFormat] ?? CARD_ASPECT.landscape);

  const meta = [project.category, project.year].filter(Boolean).join(' · ');

  // `sizes` sagt dem Browser, wie breit das Bild tatsächlich dargestellt wird –
  // ohne diese Angabe lädt er immer die größte Variante.
  const sizes = size === 'large' ? '(max-width: 768px) 100vw, 66vw' : '(max-width: 768px) 100vw, 45vw';
  const source = cover
    ? `src="${escapeHtml(cover.thumbUrl ?? cover.url)}" ${cover.srcset ? `srcset="${escapeHtml(cover.srcset)}" sizes="${sizes}"` : ''}`
    : '';

  // Die Kachel ist immer formatfüllend – auch bei „vollständig zeigen".
  // Diese Einstellung entscheidet über die Projektseite, nicht über die
  // Übersicht: Dort soll das Raster ein Raster bleiben und nicht aus halb
  // gefüllten Feldern bestehen. Wie viel vom Motiv in die Kachel passt,
  // steuert stattdessen das Kachelformat (quer, quadratisch, hoch) und der
  // Bildausschnitt.
  const visual = !cover
    ? `<div class="grid h-full w-full place-items-center bg-surface">
         <span class="font-mono text-xs tracking-[0.3em] text-faint">OHNE VORSCHAUBILD</span>
       </div>`
    : `<img
         data-cover
         ${source}
         alt="${escapeHtml(cover.alt || project.title)}"
         loading="${index < 2 ? 'eager' : 'lazy'}"
         decoding="async"
         style="object-position: ${escapeHtml(project.coverFocus || '50% 50%')}; --cover-zoom: ${Number(project.coverZoom) || 1}"
         class="cover-zoom h-full w-full object-cover"
       >`;

  const video = preview
    ? `<video
         data-preview
         src="${escapeHtml(preview.url)}"
         muted loop playsinline preload="none"
         aria-hidden="true"
         class="absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-500"
       ></video>`
    : '';

  return `
    <article
      class="group relative"
      data-project-card
      data-category="${escapeHtml(project.category.toLowerCase())}"
      data-category-key="${escapeHtml(categoryKey(project.category))}"
      data-tags="${escapeHtml(project.tags.join(' ').toLowerCase())}"
      data-reveal
      data-reveal-delay="${(index % 3) * 90}"
      style="--card-accent: ${escapeHtml(project.accent)}"
    >
      <a
        href="/work/${escapeHtml(project.slug)}"
        class="block focus-visible:outline-none"
        data-cursor="view"
        data-cursor-label="ANSEHEN"
        data-project-link="${project.id}"
      >
        <div class="relative ${aspect} overflow-hidden rounded-2xl border border-line bg-elevated">
          ${visual}
          ${video}
          <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/75 via-black/10 to-transparent opacity-80 transition-opacity duration-500 group-hover:opacity-95"></div>
          <div
            class="pointer-events-none absolute inset-0 rounded-2xl opacity-0 transition-opacity duration-500 group-hover:opacity-100"
            style="box-shadow: inset 0 0 0 1px var(--card-accent), 0 24px 60px -24px var(--card-accent)"
          ></div>

          ${
            project.featured
              ? `<span class="absolute left-4 top-4 rounded-full border border-white/25 bg-black/40 px-2.5 py-1 font-mono text-[0.5625rem] uppercase tracking-[0.2em] text-white/90 backdrop-blur">Highlight</span>`
              : ''
          }

          ${/* Größer als vorher: Auf einer Kachel, die die halbe Seite einnimmt,
                wirkte ein Titel in Fließtextgröße wie eine Bildunterschrift. */ ''}
          <div class="absolute inset-x-0 bottom-0 p-6 sm:p-8">
            <h3 class="${size === 'large' ? 'text-3xl sm:text-5xl' : 'text-2xl sm:text-3xl'} font-bold tracking-tight text-white">
              ${escapeHtml(project.title)}
            </h3>
            ${project.subtitle ? `<p class="mt-2 max-w-xl text-base text-white/70 sm:text-lg">${escapeHtml(project.subtitle)}</p>` : ''}
          </div>
        </div>

        <div class="mt-4 flex items-start justify-between gap-4">
          <div class="min-w-0">
            <p class="label-mono">${escapeHtml(meta)}</p>
            <p class="mt-2 flex flex-wrap gap-1.5">
              ${project.tools
                .slice(0, 4)
                .map(
                  (tool) =>
                    `<span class="rounded border border-line px-1.5 py-0.5 font-mono text-[0.625rem] text-faint">${escapeHtml(tool)}</span>`,
                )
                .join('')}
            </p>
          </div>
          <span
            class="mt-1 grid h-9 w-9 shrink-0 place-items-center rounded-full border border-line text-ink transition-all duration-500 group-hover:border-transparent group-hover:bg-ink group-hover:text-bg"
            aria-hidden="true"
          >↗</span>
        </div>
      </a>
    </article>`;
}

/** Video-Vorschau nur laden, wenn wirklich darübergefahren wird. */
function wireHoverPreviews(container: HTMLElement): void {
  if (window.matchMedia('(hover: none)').matches) return;

  container.querySelectorAll<HTMLElement>('[data-project-card]').forEach((card) => {
    const video = card.querySelector<HTMLVideoElement>('[data-preview]');
    if (!video) return;

    card.addEventListener('pointerenter', () => {
      if (video.preload === 'none') video.preload = 'auto';
      video.style.opacity = '1';
      void video.play().catch(() => undefined);
    });

    card.addEventListener('pointerleave', () => {
      video.style.opacity = '0';
      video.pause();
    });
  });
}

function wireTracking(container: HTMLElement): void {
  container.querySelectorAll<HTMLAnchorElement>('[data-project-link]').forEach((link) => {
    link.addEventListener('click', () => {
      track('project_view', { projectId: Number(link.dataset.projectLink) });

      // Für den Seitenübergang: Nur das angeklickte Titelbild bekommt den
      // Namen, unter dem der Browser es auf der Zielseite wiederfindet.
      // Ein Name darf pro Seite nur einmal vorkommen – deshalb erst aufräumen.
      container.querySelectorAll<HTMLElement>('[data-cover]').forEach((cover) => {
        cover.style.viewTransitionName = '';
      });
      link.querySelector<HTMLElement>('[data-cover]')?.style.setProperty('view-transition-name', 'project-hero');
    });
  });
}

/** Kategorie → kurzer Schlüssel für die Adresszeile. */
function categoryKey(category: string): string {
  return category
    .toLowerCase()
    .replace(/ä/g, 'ae')
    .replace(/ö/g, 'oe')
    .replace(/ü/g, 'ue')
    .replace(/ß/g, 'ss')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

/**
 * Kategorien zum Anklicken – mehrere gleichzeitig möglich.
 *
 * Die Auswahl steht in der Adresszeile (`?kategorie=grafik-layout,motion`),
 * damit sich eine gefilterte Ansicht verschicken lässt: „hier nur meine
 * Print-Arbeiten". Beim Aufruf eines solchen Links ist die Auswahl gesetzt.
 */
function renderFilters(categories: string[], onChange: (selected: Set<string>) => void): void {
  const container = document.querySelector<HTMLElement>('[data-work-filters]');
  if (!container || categories.length === 0) return;

  const byKey = new Map(categories.map((category) => [categoryKey(category), category]));
  const selected = new Set<string>();

  // Vorauswahl aus der Adresszeile übernehmen.
  const params = new URLSearchParams(location.search);
  (params.get('kategorie') ?? '')
    .split(',')
    .map((entry) => entry.trim())
    .filter((entry) => byKey.has(entry))
    .forEach((entry) => selected.add(entry));

  const syncUrl = (): void => {
    const url = new URL(location.href);
    url.searchParams.delete('kategorie');

    // Bewusst von Hand zusammengesetzt: `searchParams` würde das Komma als
    // %2C kodieren, und der Link soll verschickbar und lesbar bleiben.
    const rest = url.searchParams.toString();
    const selection = selected.size === 0 ? '' : `kategorie=${[...selected].join(',')}`;
    url.search = [selection, rest].filter(Boolean).join('&');

    history.replaceState(null, '', url.toString());
  };

  const paint = (): void => {
    container.querySelectorAll<HTMLButtonElement>('[data-filter]').forEach((button) => {
      const key = button.dataset.filter ?? '';
      const active = key === 'all' ? selected.size === 0 : selected.has(key);

      button.setAttribute('aria-pressed', String(active));

      // Die Hover-Farbe darf nur an den nicht gewählten Knöpfen hängen.
      // Vorher stand sie fest in der Klassenliste: Der gewählte Knopf ist
      // dunkel mit heller Schrift, beim Darüberfahren zog `hover:text-ink`
      // die Schrift aber auf dieselbe Farbe wie den Grund – der Text war weg.
      button.className = active
        ? 'rounded-full border border-transparent bg-ink px-4 py-2 text-sm text-bg transition-all duration-300'
        : 'rounded-full border border-line px-4 py-2 text-sm text-muted transition-all duration-300 hover:border-line-strong hover:text-ink';
    });

    const share = container.querySelector<HTMLButtonElement>('[data-share-selection]');
    if (share) share.hidden = selected.size === 0;
  };

  container.innerHTML = `
    ${['all', ...byKey.keys()]
      .map((key) => {
        const label = key === 'all' ? 'Alle' : (byKey.get(key) ?? key);

        return `
        <button
          type="button"
          data-filter="${escapeHtml(key)}"
          data-cursor="hover"
          aria-pressed="false"
          class="rounded-full border border-line px-4 py-2 text-sm text-muted transition-all duration-300 hover:border-line-strong hover:text-ink"
        >${escapeHtml(label)}</button>`;
      })
      .join('')}
    <button
      type="button"
      data-share-selection
      data-cursor="hover"
      hidden
      class="ml-auto rounded-full border border-line px-4 py-2 text-sm text-muted transition-colors duration-300 hover:border-line-strong hover:text-ink"
    >Auswahl-Link kopieren</button>`;

  container.querySelectorAll<HTMLButtonElement>('[data-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      const key = button.dataset.filter ?? 'all';

      if (key === 'all') {
        selected.clear();
      } else if (selected.has(key)) {
        selected.delete(key);
      } else {
        selected.add(key);
      }

      paint();
      syncUrl();
      onChange(selected);
    });
  });

  container.querySelector<HTMLButtonElement>('[data-share-selection]')?.addEventListener('click', (event) => {
    const button = event.currentTarget as HTMLButtonElement;
    const original = button.textContent;

    void navigator.clipboard?.writeText(location.href).then(() => {
      button.textContent = 'Link kopiert';
      window.setTimeout(() => (button.textContent = original), 1600);
    });
  });

  paint();
  if (selected.size > 0) onChange(selected);
}

export async function initWorkGrid(): Promise<void> {
  const grid = document.querySelector<HTMLElement>('[data-work-grid]');
  if (!grid) return;

  const limit = Number(grid.dataset.limit ?? 0);
  const withFilters = grid.dataset.filters === 'true';
  const status = document.querySelector<HTMLElement>('[data-work-status]');

  let projects: Project[];
  let categories: string[];

  try {
    const data = await fetchProjects();
    projects = data.projects;
    categories = data.categories;
  } catch {
    if (status) {
      status.textContent = 'Die Projekte konnten gerade nicht geladen werden. Bitte Seite neu laden.';
      status.classList.remove('hidden');
    }
    return;
  }

  if (projects.length === 0) {
    if (status) {
      status.textContent = 'Hier entsteht gerade etwas. Die ersten Projekte sind in Kürze zu sehen.';
      status.classList.remove('hidden');
    }
    return;
  }

  const visible = limit > 0 ? projects.slice(0, limit) : projects;

  // Erstes Projekt bekommt auf großen Bildschirmen die volle Breite.
  grid.innerHTML = visible
    .map((project, index) => {
      const large = index === 0 && visible.length > 1;

      return `<div class="${large ? 'md:col-span-2' : ''}">${cardMarkup(project, index, large ? 'large' : 'normal')}</div>`;
    })
    .join('');

  status?.classList.add('hidden');
  wireHoverPreviews(grid);
  wireTracking(grid);

  if (withFilters) {
    const status = document.querySelector<HTMLElement>('[data-filter-status]');

    renderFilters(categories, (selected) => {
      let visible = 0;

      grid.querySelectorAll<HTMLElement>('[data-project-card]').forEach((card) => {
        const wrapper = card.parentElement;
        const matches = selected.size === 0 || selected.has(card.dataset.categoryKey ?? '');
        if (matches) visible++;

        card.style.transition = 'opacity 320ms var(--ease-out-expo), transform 320ms var(--ease-out-expo)';
        card.style.opacity = matches ? '1' : '0';
        card.style.transform = matches ? 'none' : 'scale(0.97)';

        if (wrapper) {
          window.setTimeout(
            () => {
              wrapper.style.display = matches ? '' : 'none';
            },
            matches ? 0 : 300,
          );
        }
      });

      if (status) {
        status.textContent =
          selected.size === 0
            ? ''
            : `${visible} von ${projects.length} Projekten · ${[...selected].length} Kategorie${selected.size === 1 ? '' : 'n'} gewählt`;
      }
    });
  }

  refreshMotion();
}
