/**
 * Case-Study-Seite.
 *
 * Im Live-Betrieb liefert PHP die Projektdaten bereits im HTML mit
 * (`<script id="project-data">`) – die Seite ist damit sofort vollständig.
 * Lokal im Dev-Server wird stattdessen über den Slug nachgeladen.
 */

import { fetchProject, track } from '../lib/api';
import type { Project } from '../lib/types';
import { refreshMotion } from './motion';

function escapeHtml(value: string): string {
  const div = document.createElement('div');
  div.textContent = value;

  return div.innerHTML;
}

function slugFromPath(): string {
  const parts = location.pathname.replace(/\/+$/, '').split('/');

  return decodeURIComponent(parts[parts.length - 1] ?? '');
}

function embeddedProject(): Project | null {
  const node = document.querySelector<HTMLScriptElement>('#project-data');
  if (!node?.textContent) return null;

  try {
    return (JSON.parse(node.textContent) as { project: Project }).project;
  } catch {
    return null;
  }
}

async function renderBody(project: Project, container: HTMLElement): Promise<void> {
  if (project.body.trim() === '') {
    container.remove();
    return;
  }

  // Markdown-Parser erst laden, wenn es wirklich Text gibt.
  const { marked } = await import('marked');
  container.innerHTML = await marked.parse(project.body, { async: true, breaks: true });
}

function galleryMarkup(project: Project): string {
  if (project.gallery.length === 0) return '';

  return `
    <section class="container-page mt-24">
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        ${project.gallery
          .map((item, index) => {
            const span = item.layout === 'half' ? '' : 'md:col-span-2';
            const media =
              item.media.kind === 'video'
                ? `<video src="${escapeHtml(item.media.url)}" controls playsinline preload="metadata" class="h-full w-full object-cover"></video>`
                : `<img src="${escapeHtml(item.media.url)}" alt="${escapeHtml(item.media.alt || item.caption || project.title)}" loading="lazy" decoding="async" class="h-full w-full object-cover">`;

            return `
              <figure class="${span}" data-reveal data-reveal-delay="${(index % 2) * 80}">
                <div class="overflow-hidden rounded-2xl border border-line bg-elevated">${media}</div>
                ${item.caption ? `<figcaption class="mt-3 font-mono text-xs text-faint">${escapeHtml(item.caption)}</figcaption>` : ''}
              </figure>`;
          })
          .join('')}
      </div>
    </section>`;
}

function metricsMarkup(project: Project): string {
  if (project.metrics.length === 0) return '';

  return `
    <dl
      class="mt-12 grid gap-px overflow-hidden rounded-2xl border border-line bg-line"
      style="grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr))"
    >
      ${project.metrics
        .map(
          (metric) => `
        <div class="bg-bg p-6">
          <dt class="label-mono">${escapeHtml(metric.label)}</dt>
          <dd class="mt-2 text-2xl font-bold tracking-tight text-ink">${escapeHtml(metric.value)}</dd>
        </div>`,
        )
        .join('')}
    </dl>`;
}

function factsMarkup(project: Project): string {
  const facts: Array<[string, string]> = [
    ['Kunde', project.client],
    ['Rolle', project.role],
    ['Jahr', project.year ? String(project.year) : ''],
    ['Kategorie', project.category],
  ].filter((entry): entry is [string, string] => entry[1] !== '');

  return `
    <dl class="space-y-6">
      ${facts
        .map(
          ([label, value]) => `
        <div>
          <dt class="label-mono">${escapeHtml(label)}</dt>
          <dd class="mt-1.5 text-sm text-ink">${escapeHtml(value)}</dd>
        </div>`,
        )
        .join('')}
      ${
        project.tools.length > 0
          ? `<div>
               <dt class="label-mono">Werkzeuge</dt>
               <dd class="mt-2 flex flex-wrap gap-1.5">
                 ${project.tools.map((tool) => `<span class="rounded border border-line px-2 py-0.5 font-mono text-[0.625rem] text-faint">${escapeHtml(tool)}</span>`).join('')}
               </dd>
             </div>`
          : ''
      }
      ${
        project.links.length > 0
          ? `<div>
               <dt class="label-mono">Links</dt>
               <dd class="mt-2 space-y-1.5">
                 ${project.links
                   .map(
                     (link) =>
                       `<a href="${escapeHtml(link.url)}" target="_blank" rel="noopener noreferrer" class="link-underline block text-sm text-accent">${escapeHtml(link.label)} ↗</a>`,
                   )
                   .join('')}
               </dd>
             </div>`
          : ''
      }
    </dl>`;
}

export async function initProjectDetail(): Promise<void> {
  const root = document.querySelector<HTMLElement>('[data-project-root]');
  if (!root) return;

  let project = embeddedProject();

  if (!project) {
    try {
      project = (await fetchProject(slugFromPath())).project;
    } catch {
      root.innerHTML = `
        <div class="container-page py-40 text-center">
          <p class="label-mono">Fehler 404</p>
          <h1 class="display-lg mt-6 text-ink">Projekt nicht gefunden</h1>
          <a href="/work/" class="link-underline mt-8 inline-block text-accent">Zurück zu allen Arbeiten</a>
        </div>`;
      return;
    }
    track('project_view', { projectId: project.id });
  }

  document.title = `${project.title} – ${project.category || 'Case Study'} | Dominic Majewski`;
  root.style.setProperty('--card-accent', project.accent);

  const cover = project.cover;
  const heroMedia = cover
    ? `<img src="${escapeHtml(cover.url)}" alt="${escapeHtml(cover.alt || project.title)}" class="h-full w-full object-cover" fetchpriority="high" decoding="async">`
    : '<div class="h-full w-full bg-surface"></div>';

  root.innerHTML = `
    <article>
      <header class="relative overflow-hidden pt-[calc(var(--nav-height)+3rem)]">
        <div class="glow -top-32 left-1/3 h-[28rem] w-[28rem]" style="background: ${escapeHtml(project.accent)}"></div>

        <div class="container-page relative">
          <a href="/work/" class="label-mono transition-colors hover:text-ink" data-cursor="hover">← Alle Arbeiten</a>

          <h1 class="display-lg mt-8 max-w-5xl text-ink">${escapeHtml(project.title)}</h1>
          ${project.subtitle ? `<p class="mt-6 max-w-2xl text-lg text-muted">${escapeHtml(project.subtitle)}</p>` : ''}
        </div>

        <div class="container-page relative mt-14">
          <div class="aspect-[16/9] overflow-hidden rounded-3xl border border-line bg-elevated">${heroMedia}</div>
        </div>
      </header>

      <div class="container-page mt-20 grid gap-16 lg:grid-cols-[1fr_18rem] lg:gap-24">
        <div>
          ${project.summary ? `<p class="text-xl leading-relaxed text-ink sm:text-2xl" data-reveal>${escapeHtml(project.summary)}</p>` : ''}
          <div class="prose-case mt-10" data-project-body data-reveal></div>
          ${metricsMarkup(project)}
        </div>
        <aside class="lg:sticky lg:top-28 lg:self-start" data-reveal>${factsMarkup(project)}</aside>
      </div>

      ${
        project.before && project.after
          ? `<section class="container-page mt-24" data-reveal>
               <p class="label-mono mb-5">Vorher / Nachher</p>
               <div
                 data-before-after
                 data-before="${escapeHtml(project.before.url)}"
                 data-after="${escapeHtml(project.after.url)}"
                 class="relative aspect-[16/9] cursor-ew-resize select-none overflow-hidden rounded-2xl border border-line bg-elevated"
               ></div>
             </section>`
          : ''
      }

      ${
        project.model
          ? `<section class="container-page mt-24" data-reveal>
               <p class="label-mono mb-5">3D-Modell — zum Drehen ziehen</p>
               <div
                 data-model-viewer
                 data-src="${escapeHtml(project.model.url)}"
                 class="relative aspect-[16/9] overflow-hidden rounded-2xl border border-line bg-elevated"
               ></div>
             </section>`
          : ''
      }

      ${galleryMarkup(project)}

      <section class="container-page mt-32">
        <div class="surface-card flex flex-col items-start justify-between gap-8 p-10 sm:flex-row sm:items-center">
          <div>
            <p class="label-mono">Weiter geht's</p>
            <p class="display-md mt-3 text-ink">Ähnliches Projekt im Kopf?</p>
          </div>
          <a
            href="/contact/"
            data-magnetic="0.3"
            data-cursor="hover"
            class="shrink-0 rounded-full bg-ink px-8 py-4 text-sm font-semibold text-bg transition-transform"
          >
            <span data-magnetic-inner class="block">Projekt anfragen</span>
          </a>
        </div>
      </section>
    </article>`;

  const bodyContainer = root.querySelector<HTMLElement>('[data-project-body]');
  if (bodyContainer) await renderBody(project, bodyContainer);

  // Schwergewichte erst laden, wenn das Projekt sie wirklich braucht.
  if (root.querySelector('[data-before-after]')) {
    const { initBeforeAfter } = await import('./before-after');
    initBeforeAfter();
  }
  if (root.querySelector('[data-model-viewer]')) {
    const { initModelViewer } = await import('./model-viewer');
    void initModelViewer();
  }

  refreshMotion();
}
