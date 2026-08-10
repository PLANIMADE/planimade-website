/**
 * Case-Study-Seite.
 *
 * Im Live-Betrieb liefert PHP die Projektdaten bereits im HTML mit
 * (`<script id="project-data">`) – die Seite ist damit sofort vollständig.
 * Lokal im Dev-Server wird stattdessen über den Slug nachgeladen.
 */

import { fetchProject, fetchProjects, track } from '../lib/api';
import type { Project } from '../lib/types';
import { refreshMotion } from './motion';
import { initLightbox } from './lightbox';
import { initVideos, videoMarkup } from './video';
import { initPdfViewer } from './pdf-viewer';

/**
 * Was oben auf der Seite steht.
 *
 * Vorher war das immer das Titelbild. Das Titelbild ist aber für die Kacheln
 * der Übersicht gemacht – wer eine Projektseite öffnet, will die Arbeit
 * sehen und nicht noch einmal dasselbe Vorschaubild.
 *
 * Die Reihenfolge: Video, dann Dokument, dann Bild – und nur wenn es nichts
 * davon gibt, das Titelbild. Das Dokument steht vor dem Bild, weil bei einer
 * Print-Arbeit das PDF die Arbeit ist und die Bilder daneben nur Beiwerk.
 *
 * `ausGalerie` merkt sich, welcher Eintrag nach oben gewandert ist – der
 * fällt in der Galerie darunter weg, sonst stünde er zweimal auf der Seite.
 */
function heroAuswahl(project: Project): { media: Project['cover']; ausGalerie: number | null } {
  const ausGalerie = (art: string): number => project.gallery.findIndex((item) => item.media.kind === art);

  for (const art of ['video', 'document', 'image']) {
    const stelle = ausGalerie(art);
    if (stelle !== -1) {
      return { media: project.gallery[stelle]!.media, ausGalerie: stelle };
    }

    // Ein Vorschauvideo ist gleichwertig zu einem aus der Galerie.
    if (art === 'video' && project.preview?.kind === 'video') {
      return { media: project.preview, ausGalerie: null };
    }
  }

  return { media: project.cover, ausGalerie: null };
}

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

function galleryMarkup(project: Project, ohneIndex: number | null): string {
  const eintraege = project.gallery.filter((_, index) => index !== ohneIndex);
  if (eintraege.length === 0) return '';

  return `
    <section class="container-page mt-24">
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        ${eintraege
          .map((item, index) => {
            const span = item.layout === 'half' ? '' : 'md:col-span-2';
            const sizes = item.layout === 'half' ? '(max-width: 768px) 100vw, 45vw' : '(max-width: 768px) 100vw, 90vw';
            const alt = escapeHtml(item.media.alt || item.caption || project.title);

            // PDFs werden gezeigt, nicht zum Herunterladen angeboten. Über
            // die volle Breite, weil eine Seite in einer halben Spalte nicht
            // mehr lesbar wäre.
            if (item.media.kind === 'document') {
              return `<div class="md:col-span-2" data-reveal>${documentMarkup(item.media, item.caption)}</div>`;
            }

            // Videos bekommen den eigenen Rahmen mit Abspieltaste – und
            // ausdrücklich kein `data-lightbox-item` weiter unten: Ein Klick
            // soll abspielen, nicht die Großansicht aufziehen.
            if (item.media.kind === 'video') {
              return `
                <figure class="${span}" data-reveal data-reveal-delay="${(index % 2) * 80}">
                  <div class="overflow-hidden rounded-2xl border border-line">
                    ${videoMarkup(escapeHtml(item.media.url), escapeHtml(item.media.mime), 'aspect-video')}
                  </div>
                  ${item.caption ? `<figcaption class="mt-3 font-mono text-xs text-faint">${escapeHtml(item.caption)}</figcaption>` : ''}
                </figure>`;
            }

            const media = `<img
                     src="${escapeHtml(item.media.thumbUrl ?? item.media.url)}"
                     ${item.media.srcset ? `srcset="${escapeHtml(item.media.srcset)}" sizes="${sizes}"` : ''}
                     alt="${alt}"
                     loading="lazy" decoding="async"
                     class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.02]"
                   >`;

            return `
              <figure
                class="${span} group"
                data-reveal data-reveal-delay="${(index % 2) * 80}"
                ${item.media.kind === 'model' ? '' : 'data-lightbox-item'}
                data-full="${escapeHtml(item.media.url)}"
                data-alt="${alt}"
                data-caption="${escapeHtml(item.caption)}"
                data-kind="${item.media.kind}"
              >
                <div class="overflow-hidden rounded-2xl border border-line bg-elevated">${media}</div>
                ${item.caption ? `<figcaption class="mt-3 font-mono text-xs text-faint">${escapeHtml(item.caption)}</figcaption>` : ''}
              </figure>`;
          })
          .join('')}
      </div>
    </section>`;
}

/**
 * Farbfelder für Branding-Arbeiten.
 * Ein Klick kopiert den Hex-Wert – praktisch, wenn jemand die Palette
 * übernehmen oder prüfen will.
 */
function paletteMarkup(project: Project): string {
  const colors = project.palette.filter((entry) => /^#?[0-9a-f]{3,8}$/i.test(entry.hex.trim()));
  if (colors.length === 0) return '';

  return `
    <section class="container-page mt-24" data-reveal>
      <p class="label-mono mb-5">Farbpalette — zum Kopieren anklicken</p>
      <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        ${colors
          .map((color) => {
            const hex = color.hex.trim().startsWith('#') ? color.hex.trim() : `#${color.hex.trim()}`;

            return `
            <li>
              <button
                type="button"
                data-copy-color="${escapeHtml(hex)}"
                data-cursor="hover"
                class="group w-full overflow-hidden rounded-xl border border-line text-left transition-colors hover:border-line-strong"
              >
                <span class="block h-24 w-full" style="background: ${escapeHtml(hex)}"></span>
                <span class="block px-3 py-2.5">
                  <span class="block truncate text-sm text-ink">${escapeHtml(color.name || hex)}</span>
                  <span class="block font-mono text-[0.625rem] uppercase text-faint">${escapeHtml(hex)}</span>
                </span>
              </button>
            </li>`;
          })
          .join('')}
      </ul>
    </section>`;
}

/**
 * Ein Dokument auf der Projektseite.
 *
 * Die Seiten zeichnet das Skript selbst (siehe `pdf-viewer.ts`) – der
 * eingebaute Betrachter des Browsers schafft nur ein Dokument pro Seite,
 * zeigt auf dem iPhone bloß die erste Seite und bringt eine graue
 * Werkzeugleiste mit, die mitten in der Arbeit steht.
 *
 * Bis die Seiten da sind, steht die erste Seite als Bild im Rahmen – die
 * erzeugt der Server beim Hochladen. So ist die Fläche nie leer.
 */
function documentMarkup(media: Project['gallery'][number]['media'], caption: string, hoehe = 'h-[70vh] max-h-[52rem] min-h-[26rem]'): string {
  const url = escapeHtml(media.url);
  const titel = escapeHtml(caption || media.filename);

  return `
    <figure class="overflow-hidden rounded-2xl border border-line bg-elevated">
      <div class="pdf-ansicht ${hoehe}" data-pdf="${url}" aria-label="${titel}">
        ${
          media.thumbUrl
            ? `<figure class="pdf-blatt"><img src="${escapeHtml(media.thumbUrl)}" alt="${escapeHtml(media.alt || caption)}" class="block h-auto w-full rounded"></figure>`
            : `<p class="p-10 text-center font-mono text-xs tracking-[0.2em] text-faint">PDF WIRD GELADEN …</p>`
        }
      </div>

      <figcaption class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3.5">
        <span class="min-w-0">
          <span class="block truncate text-sm text-ink">${titel}</span>
          <span class="block font-mono text-[0.625rem] text-faint">PDF · ${dateiGroesse(media.size)}</span>
        </span>
        <span class="flex shrink-0 items-center gap-4">
          <a href="${url}" target="_blank" rel="noopener" data-cursor="hover" class="link-underline text-xs text-ink">Ganze Seite</a>
          <a href="${url}" download data-cursor="hover" class="link-underline text-xs text-muted">Herunterladen</a>
        </span>
      </figcaption>
    </figure>`;
}

/** Unter einem Megabyte in Kilobyte – „0 MB" hilft niemandem. */
function dateiGroesse(bytes: number): string {
  return bytes >= 1024 * 1024
    ? `${Math.round((bytes / 1024 / 1024) * 10) / 10} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * „Nächstes Projekt" am Ende der Case-Study.
 *
 * Ohne diesen Block endet jede Projektseite in einer Sackgasse – wer sich
 * durchklicken will, müsste zurück navigieren.
 */
async function nextProjectMarkup(project: Project): Promise<{ html: string; slug: string | null }> {
  const { projects } = await fetchProjects().catch(() => ({ projects: [] as Project[] }));
  if (projects.length < 2) return { html: '', slug: null };

  const position = projects.findIndex((entry) => entry.id === project.id);
  const next = projects[(position + 1) % projects.length];
  if (!next || next.id === project.id) return { html: '', slug: null };

  const cover = next.cover;

  return {
    slug: next.slug,
    html: `
      <section class="mt-32 border-t border-line pt-16">
        <div class="container-page">
          <div class="flex items-center justify-between gap-4">
            <p class="label-mono">Nächstes Projekt</p>
            <p class="hidden font-mono text-[0.625rem] tracking-widest text-faint sm:block">
              MIT → ZUR NÄCHSTEN ARBEIT
            </p>
          </div>

          <a
            href="/work/${escapeHtml(next.slug)}"
            data-cursor="view"
            data-cursor-label="ANSEHEN"
            data-next-project
            class="group mt-6 block overflow-hidden rounded-3xl border border-line bg-elevated"
          >
            <div class="relative aspect-[21/9]">
              ${
                cover
                  ? `<img
                       data-cover
                       src="${escapeHtml(cover.thumbUrl ?? cover.url)}"
                       ${cover.srcset ? `srcset="${escapeHtml(cover.srcset)}" sizes="90vw"` : ''}
                       alt="${escapeHtml(cover.alt || next.title)}"
                       loading="lazy" decoding="async"
                       class="h-full w-full object-cover transition-transform duration-[1.2s] ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-[1.03]"
                     >`
                  : '<div class="h-full w-full bg-surface"></div>'
              }
              <div class="absolute inset-0 bg-gradient-to-t from-black/75 to-transparent"></div>
              <div class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-6 p-6 sm:p-10">
                <div>
                  <p class="font-mono text-[0.625rem] uppercase tracking-[0.2em] text-white/60">${escapeHtml(next.category)}</p>
                  <p class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-4xl">${escapeHtml(next.title)}</p>
                </div>
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full border border-white/30 text-white transition-all duration-500 group-hover:border-transparent group-hover:bg-white group-hover:text-black">→</span>
              </div>
            </div>
          </a>
        </div>
      </section>`,
  };
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

/**
 * Misst, wie weit Besucher eine Case-Study lesen.
 *
 * Gemeldet werden nur vier Schwellen (25/50/75/100 %) und jede nur einmal –
 * das reicht für die Frage „hält das Projekt die Aufmerksamkeit?" und
 * erzeugt keine Datenspur, die über den Besuch hinausgeht.
 */
function trackReadingDepth(projectId: number): void {
  const thresholds = [25, 50, 75, 100];
  const reported = new Set<number>();

  const check = (): void => {
    const scrollable = document.documentElement.scrollHeight - window.innerHeight;
    if (scrollable <= 0) return;

    const percent = Math.min(100, Math.round((window.scrollY / scrollable) * 100));

    thresholds
      .filter((threshold) => percent >= threshold && !reported.has(threshold))
      .forEach((threshold) => {
        reported.add(threshold);
        track('scroll_depth', { projectId, value: threshold });
      });

    if (reported.size === thresholds.length) {
      window.removeEventListener('scroll', onScroll);
    }
  };

  // Gedrosselt: Beim Scrollen feuert das Ereignis sonst hundertfach.
  let waiting = false;
  const onScroll = (): void => {
    if (waiting) return;
    waiting = true;
    window.setTimeout(() => {
      waiting = false;
      check();
    }, 400);
  };

  window.addEventListener('scroll', onScroll, { passive: true });
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

  const { media: heroQuelle, ausGalerie } = heroAuswahl(project);
  const contain = project.display === 'contain';
  const istVideo = heroQuelle?.kind === 'video';
  const istDokument = heroQuelle?.kind === 'document';

  // Hochformate dürfen nicht auf 16:9 beschnitten werden – bei „vollständig
  // zeigen" bekommt das Bild deshalb ein höheres Feld und liegt darin wie ein
  // Blatt auf einer Fläche. Videos laufen immer im Kinoformat.
  const heroAspect = istVideo ? 'aspect-video' : contain ? 'aspect-[4/3] sm:aspect-[16/10]' : 'aspect-[16/9]';

  // `view-transition-name` verbindet dieses Bild mit der angeklickten Kachel
  // auf der Übersicht – der Browser lässt es beim Seitenwechsel hineinwachsen.
  const heroImage = heroQuelle
    ? `<img
         src="${escapeHtml(heroQuelle.url)}"
         ${heroQuelle.srcset ? `srcset="${escapeHtml(heroQuelle.srcset)}" sizes="90vw"` : ''}
         alt="${escapeHtml(heroQuelle.alt || project.title)}"
         class="${contain ? 'paper-sheet' : 'h-full w-full object-cover'}"
         style="view-transition-name: project-hero${
           // Der eingestellte Ausschnitt gilt nur, wenn hier wirklich das
           // Titelbild steht und formatfüllend beschnitten wird.
           !contain && heroQuelle === project.cover
             ? `; object-position: ${escapeHtml(project.coverFocus || '50% 50%')}`
             : ''
         }"
         fetchpriority="high" decoding="async"
       >`
    : '<div class="h-full w-full bg-surface"></div>';

  const heroMedia = istVideo
    ? videoMarkup(escapeHtml(heroQuelle!.url), escapeHtml(heroQuelle!.mime), 'h-full w-full')
    : contain && heroQuelle
      ? `<div class="paper-stage h-full w-full">${heroImage}</div>`
      : heroImage;

  /*
   * Ein Dokument bringt seinen eigenen Rahmen mit – Seitenzahlen, Zoom und
   * Blättern gehören zum Betrachter und nicht in einen Kasten mit festem
   * Seitenverhältnis. Deshalb hier keine `heroAspect`-Hülle, sondern die
   * Ansicht selbst, etwas höher als in der Galerie.
   */
  const heroBlock = istDokument
    ? documentMarkup(heroQuelle!, project.title, 'h-[80vh] max-h-[58rem] min-h-[28rem]')
    : `<div class="${heroAspect} overflow-hidden rounded-3xl border border-line bg-elevated">${heroMedia}</div>`;

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
          ${heroBlock}
        </div>
      </header>

      <div class="container-page mt-20 grid gap-16 lg:grid-cols-[1fr_18rem] lg:gap-24">
        <div>
          ${/* `whitespace-pre-line`: Ein Enter in der Kurzbeschreibung soll auch
                auf der Seite eine neue Zeile sein. Ohne die Angabe faltet HTML
                jeden Umbruch zu einem Leerzeichen zusammen. */ ''}
          ${project.summary ? `<p class="whitespace-pre-line text-xl leading-relaxed text-ink sm:text-2xl" data-reveal>${escapeHtml(project.summary)}</p>` : ''}
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

      ${paletteMarkup(project)}
      ${galleryMarkup(project, ausGalerie)}

      <div data-next-slot></div>

    </article>`;

  const bodyContainer = root.querySelector<HTMLElement>('[data-project-body]');
  if (bodyContainer) await renderBody(project, bodyContainer);

  // Nächstes Projekt anhängen und per Pfeiltaste erreichbar machen.
  const { html: nextHtml, slug: nextSlug } = await nextProjectMarkup(project);
  const nextSlot = root.querySelector<HTMLElement>('[data-next-slot]');
  if (nextSlot && nextHtml !== '') {
    nextSlot.outerHTML = nextHtml;

    if (nextSlug !== null) {
      // Der Name darf pro Seite nur einmal vergeben sein: erst dem
      // Titelbild wegnehmen, dann dem Vorschaubild des nächsten Projekts geben.
      const handOverName = (): void => {
        root.querySelectorAll<HTMLElement>('[style*="view-transition-name"]').forEach((element) => {
          element.style.removeProperty('view-transition-name');
        });
        document
          .querySelector<HTMLElement>('[data-next-project] [data-cover]')
          ?.style.setProperty('view-transition-name', 'project-hero');
      };

      document.querySelector('[data-next-project]')?.addEventListener('click', handOverName);

      document.addEventListener('keydown', (event) => {
        const target = event.target as HTMLElement | null;
        if (target?.matches('input, textarea, select')) return;
        if (event.key === 'ArrowRight' && !event.metaKey && !event.ctrlKey && !event.altKey) {
          handOverName();
          location.href = `/work/${nextSlug}`;
        }
      });
    }
  }

  // Farbwert in die Zwischenablage – mit kurzer Rückmeldung im Knopf selbst.
  root.querySelectorAll<HTMLButtonElement>('[data-copy-color]').forEach((button) => {
    button.addEventListener('click', () => {
      const hex = button.dataset.copyColor ?? '';
      const label = button.querySelector<HTMLElement>('span > span:last-child');
      if (!label) return;

      const original = label.textContent;
      void navigator.clipboard?.writeText(hex).then(
        () => {
          label.textContent = 'Kopiert';
          window.setTimeout(() => (label.textContent = original), 1400);
        },
        () => undefined,
      );
    });
  });

  trackReadingDepth(project.id);
  initLightbox(root);
  initVideos(root);
  // Nur wenn wirklich ein Dokument auf der Seite steht – die Bibliothek zum
  // Zeichnen der Seiten wiegt einiges und wird erst dann geladen.
  if (root.querySelector('[data-pdf]')) initPdfViewer(root);

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
