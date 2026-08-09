/**
 * Interaktiver Lebenslauf auf der Seite „Über mich".
 *
 * Zeitstrahl mit Filtern, Kompetenzen mit Niveau-Anzeige, Sprachen und
 * Eckdaten – alles aus dem Dashboard gepflegt. Über die Druckfunktion des
 * Browsers entsteht daraus ein sauberes PDF (siehe Druck-Stylesheet).
 */

import { fetchSettings } from '../lib/api';
import type { ExpertiseItem, Settings, TimelineEntry } from '../lib/types';
import { refreshMotion } from './motion';

const TYPE_LABELS: Record<TimelineEntry['type'], string> = {
  work: 'Beruflich',
  education: 'Ausbildung',
  project: 'Projekt',
};

function escapeHtml(value: string): string {
  const div = document.createElement('div');
  div.textContent = value;

  return div.innerHTML;
}

function renderFacts(settings: Settings): void {
  const container = document.querySelector<HTMLElement>('[data-resume-facts]');
  if (!container) return;

  const facts = settings.resume.facts.filter((fact) => fact.label !== '' || fact.value !== '');
  if (facts.length === 0) {
    container.remove();
    return;
  }

  container.innerHTML = facts
    .map(
      (fact) => `
      <div>
        <dt class="label-mono">${escapeHtml(fact.label)}</dt>
        <dd class="mt-1.5 text-lg font-semibold tracking-tight text-ink">${escapeHtml(fact.value)}</dd>
      </div>`,
    )
    .join('');
}

function renderTimeline(settings: Settings): void {
  const container = document.querySelector<HTMLElement>('[data-timeline]');
  const filterBar = document.querySelector<HTMLElement>('[data-timeline-filters]');
  if (!container) return;

  const entries = settings.resume.timeline;
  if (entries.length === 0) {
    container.innerHTML = '<p class="py-10 text-sm text-faint">Noch keine Stationen hinterlegt.</p>';
    filterBar?.remove();
    return;
  }

  container.innerHTML = entries
    .map(
      (entry, index) => `
      <li
        class="group relative border-l border-line pb-10 pl-8 last:pb-0"
        data-timeline-entry
        data-type="${entry.type}"
        data-reveal
        data-reveal-delay="${Math.min(index, 5) * 60}"
      >
        <span class="absolute -left-[5px] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-bg bg-accent transition-transform duration-300 group-hover:scale-125"></span>

        <p class="label-mono">${escapeHtml(entry.period)}</p>

        <h3 class="mt-2 text-lg font-semibold tracking-tight text-ink">${escapeHtml(entry.title)}</h3>

        <p class="mt-1 text-sm text-muted">
          ${escapeHtml([entry.org, entry.location].filter(Boolean).join(' · '))}
          <span class="ml-1 rounded-full border border-line px-2 py-0.5 font-mono text-[0.5625rem] uppercase tracking-widest text-faint">
            ${escapeHtml(TYPE_LABELS[entry.type] ?? entry.type)}
          </span>
        </p>

        ${entry.description ? `<p class="mt-3 max-w-2xl whitespace-pre-line text-sm leading-relaxed text-muted">${escapeHtml(entry.description)}</p>` : ''}

        ${
          entry.tags.length > 0
            ? `<p class="mt-3 flex flex-wrap gap-1.5">
                 ${entry.tags
                   .map(
                     (tag) =>
                       `<span class="rounded border border-line px-1.5 py-0.5 font-mono text-[0.625rem] text-faint">${escapeHtml(tag)}</span>`,
                   )
                   .join('')}
               </p>`
            : ''
        }
      </li>`,
    )
    .join('');

  // Filter nur anbieten, wenn es überhaupt mehrere Arten gibt.
  const types = [...new Set(entries.map((entry) => entry.type))];
  if (!filterBar || types.length < 2) {
    filterBar?.remove();
    return;
  }

  const options: Array<[string, string]> = [
    ['all', 'Alle'],
    ...types.map((type): [string, string] => [type, TYPE_LABELS[type] ?? type]),
  ];

  filterBar.innerHTML = options
    .map(
      ([value, label], index) => `
      <button
        type="button"
        data-timeline-filter="${value}"
        data-cursor="hover"
        class="rounded-full border px-4 py-2 text-sm transition-all duration-300 ${
          index === 0 ? 'border-transparent bg-ink text-bg' : 'border-line text-muted hover:border-line-strong hover:text-ink'
        }"
      >${escapeHtml(label)}</button>`,
    )
    .join('');

  filterBar.querySelectorAll<HTMLButtonElement>('[data-timeline-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      const selected = button.dataset.timelineFilter ?? 'all';

      filterBar.querySelectorAll<HTMLButtonElement>('[data-timeline-filter]').forEach((other) => {
        const active = other === button;
        other.classList.toggle('bg-ink', active);
        other.classList.toggle('text-bg', active);
        other.classList.toggle('border-transparent', active);
        other.classList.toggle('border-line', !active);
        other.classList.toggle('text-muted', !active);
      });

      container.querySelectorAll<HTMLElement>('[data-timeline-entry]').forEach((entry) => {
        const visible = selected === 'all' || entry.dataset.type === selected;
        entry.style.display = visible ? '' : 'none';
      });
    });
  });
}

function renderExpertise(settings: Settings): void {
  const container = document.querySelector<HTMLElement>('[data-expertise]');
  if (!container) return;

  const items = settings.expertise.filter((item) => item.name !== '');
  if (items.length === 0) {
    container.closest('section')?.remove();
    return;
  }

  // Nach Bereich gruppieren, Reihenfolge wie im Dashboard.
  const groups = new Map<string, ExpertiseItem[]>();
  items.forEach((item) => {
    const key = item.group || 'Weitere';
    groups.set(key, [...(groups.get(key) ?? []), item]);
  });

  container.innerHTML = [...groups.entries()]
    .map(
      ([group, entries], index) => `
      <div data-reveal data-reveal-delay="${index * 70}">
        <p class="label-mono mb-5">${escapeHtml(group)}</p>
        <ul class="space-y-4">
          ${entries
            .map(
              (item) => `
            <li>
              <div class="flex items-baseline justify-between gap-3">
                <span class="text-sm font-medium text-ink">${escapeHtml(item.name)}</span>
                <span class="font-mono text-[0.625rem] text-faint">${escapeHtml(item.note)}</span>
              </div>
              <div class="mt-2 h-1 overflow-hidden rounded-full bg-line" role="img" aria-label="${escapeHtml(item.name)}: ${item.level} von 100">
                <div
                  class="h-full rounded-full bg-accent transition-[width] duration-1000 ease-[cubic-bezier(0.16,1,0.3,1)]"
                  style="width: 0"
                  data-level="${Math.max(0, Math.min(100, item.level))}"
                ></div>
              </div>
            </li>`,
            )
            .join('')}
        </ul>
      </div>`,
    )
    .join('');

  // Balken erst beim Hereinscrollen füllen – sonst sieht es niemand.
  const bars = container.querySelectorAll<HTMLElement>('[data-level]');
  const fill = (): void => bars.forEach((bar) => (bar.style.width = `${bar.dataset.level}%`));

  // Beim Drucken zählt kein Scrollen: Wer den Lebenslauf als PDF speichert,
  // bekäme sonst leere Balken aufs Papier.
  window.addEventListener('beforeprint', fill);

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
    fill();
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        fill();
        observer.disconnect();
      }
    },
    { threshold: 0.2 },
  );
  observer.observe(container);
}

function renderLanguages(settings: Settings): void {
  const container = document.querySelector<HTMLElement>('[data-languages]');
  if (!container) return;

  const languages = settings.resume.languages.filter((language) => language.name !== '');
  if (languages.length === 0) {
    container.closest('[data-languages-block]')?.remove();
    return;
  }

  container.innerHTML = languages
    .map(
      (language) => `
      <li class="flex items-baseline justify-between gap-3 border-b border-line pb-2 text-sm last:border-0">
        <span class="text-ink">${escapeHtml(language.name)}</span>
        <span class="text-xs text-faint">${escapeHtml(language.level)}</span>
      </li>`,
    )
    .join('');
}

function wirePrint(): void {
  document.querySelectorAll<HTMLButtonElement>('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
  });
}

export async function initResume(): Promise<void> {
  if (!document.querySelector('[data-resume]')) return;

  const settings = await fetchSettings().catch(() => null);
  if (!settings) return;

  renderFacts(settings);
  renderTimeline(settings);
  renderExpertise(settings);
  renderLanguages(settings);
  wirePrint();

  // Kopfzeile des Ausdrucks mit den aktuellen Kontaktdaten füllen.
  document.querySelectorAll<HTMLElement>('[data-print-contact]').forEach((element) => {
    element.textContent = [settings.email, settings.location].filter(Boolean).join(' · ');
  });

  refreshMotion();
}
