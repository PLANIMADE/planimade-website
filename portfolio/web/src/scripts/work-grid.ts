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
  const contain = project.display === 'contain';

  // Großformatige Kacheln bleiben quer, außer das Projekt wünscht ausdrücklich
  // ein anderes Format – sonst reißt ein Hochformat die Startseite auseinander.
  const aspect =
    size === 'large' && project.cardFormat === 'landscape'
      ? 'aspect-[16/10]'
      : (CARD_ASPECT[project.cardFormat] ?? CARD_ASPECT.landscape);

  const meta = [project.category, project.year].filter(Boolean).join(' · ');

  // `sizes` sagt dem Browser, wie breit das Bild tatsächlich dargestellt wird –
  // ohne diese Angabe lädt er immer die größte Variante.
  const sizes = size === 'large' ? '(max-width: 768px) 100vw, 66vw' : '(max-width: 768px) 100vw, 45vw';
  const source = cover
    ? `src="${escapeHtml(cover.thumbUrl ?? cover.url)}" ${cover.srcset ? `srcset="${escapeHtml(cover.srcset)}" sizes="${sizes}"` : ''}`
    : '';

  const visual = !cover
    ? `<div class="grid h-full w-full place-items-center bg-surface">
         <span class="font-mono text-xs tracking-[0.3em] text-faint">OHNE VORSCHAUBILD</span>
       </div>`
    : contain
      ? `<div class="paper-stage h-full w-full">
           <img
             data-cover
             ${source}
             alt="${escapeHtml(cover.alt || project.title)}"
             loading="${index < 2 ? 'eager' : 'lazy'}"
             decoding="async"
             class="paper-sheet"
           >
         </div>`
      : `<img
           data-cover
           ${source}
           alt="${escapeHtml(cover.alt || project.title)}"
           loading="${index < 2 ? 'eager' : 'lazy'}"
           decoding="async"
           class="h-full w-full object-cover transition-transform duration-[1.2s] ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-[1.04]"
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
          ${
            // Bei „vollständig zeigen" würde eine dunkle Überblendung das
            // Papier grau waschen – dort steht der Titel darunter.
            contain
              ? ''
              : `<div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/70 via-black/5 to-transparent opacity-70 transition-opacity duration-500 group-hover:opacity-90"></div>`
          }
          <div
            class="pointer-events-none absolute inset-0 rounded-2xl opacity-0 transition-opacity duration-500 group-hover:opacity-100"
            style="box-shadow: inset 0 0 0 1px var(--card-accent), 0 24px 60px -24px var(--card-accent)"
          ></div>

          ${
            project.featured
              ? `<span class="absolute left-4 top-4 rounded-full px-2.5 py-1 font-mono text-[0.5625rem] uppercase tracking-[0.2em] backdrop-blur ${
                  contain
                    ? 'border border-line-strong bg-bg/70 text-ink'
                    : 'border border-white/25 bg-black/40 text-white/90'
                }">Highlight</span>`
              : ''
          }

          ${
            contain
              ? ''
              : `<div class="absolute inset-x-0 bottom-0 p-5 sm:p-6">
                   <h3 class="${size === 'large' ? 'text-2xl sm:text-4xl' : 'text-xl sm:text-2xl'} font-bold tracking-tight text-white">
                     ${escapeHtml(project.title)}
                   </h3>
                   ${project.subtitle ? `<p class="mt-1.5 max-w-lg text-sm text-white/65">${escapeHtml(project.subtitle)}</p>` : ''}
                 </div>`
          }
        </div>

        <div class="mt-4 flex items-start justify-between gap-4">
          <div class="min-w-0">
            ${
              contain
                ? `<h3 class="${size === 'large' ? 'text-2xl sm:text-3xl' : 'text-xl'} font-bold tracking-tight text-ink">
                     ${escapeHtml(project.title)}
                   </h3>
                   ${project.subtitle ? `<p class="mt-1 text-sm text-muted">${escapeHtml(project.subtitle)}</p>` : ''}
                   <p class="label-mono mt-3">${escapeHtml(meta)}</p>`
                : `<p class="label-mono">${escapeHtml(meta)}</p>`
            }
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

function renderFilters(categories: string[], onChange: (category: string) => void): void {
  const container = document.querySelector<HTMLElement>('[data-work-filters]');
  if (!container || categories.length === 0) return;

  const entries = ['Alle', ...categories];
  container.innerHTML = entries
    .map(
      (category, index) => `
      <button
        type="button"
        data-filter="${escapeHtml(index === 0 ? 'all' : category.toLowerCase())}"
        data-cursor="hover"
        class="rounded-full border px-4 py-2 text-sm transition-all duration-300 ${
          index === 0 ? 'border-transparent bg-ink text-bg' : 'border-line text-muted hover:border-line-strong hover:text-ink'
        }"
      >${escapeHtml(category)}</button>`,
    )
    .join('');

  container.querySelectorAll<HTMLButtonElement>('[data-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      container.querySelectorAll<HTMLButtonElement>('[data-filter]').forEach((other) => {
        const isActive = other === button;
        other.classList.toggle('bg-ink', isActive);
        other.classList.toggle('text-bg', isActive);
        other.classList.toggle('border-transparent', isActive);
        other.classList.toggle('border-line', !isActive);
        other.classList.toggle('text-muted', !isActive);
      });
      onChange(button.dataset.filter ?? 'all');
    });
  });
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
    renderFilters(categories, (category) => {
      grid.querySelectorAll<HTMLElement>('[data-project-card]').forEach((card) => {
        const wrapper = card.parentElement;
        const matches = category === 'all' || card.dataset.category === category;

        card.style.transition = 'opacity 320ms var(--ease-out-expo), transform 320ms var(--ease-out-expo)';
        card.style.opacity = matches ? '1' : '0';
        card.style.transform = matches ? 'none' : 'scale(0.97)';

        if (wrapper) {
          window.setTimeout(() => {
            wrapper.style.display = matches ? '' : 'none';
          }, matches ? 0 : 300);
        }
      });
    });
  }

  refreshMotion();
}
