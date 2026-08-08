/**
 * Füllt den Bewerbungs-Lebenslauf mit den Daten aus dem Dashboard.
 *
 * Aufbau bewusst klassisch-tabellarisch: links der Zeitraum, rechts die
 * Station. Das ist die Form, die Personalabteilungen in Sekunden erfassen –
 * und die sich sauber auf A4 drucken lässt.
 */

import { fetchSettings } from '../lib/api';
import type { ExpertiseItem, Settings, TimelineEntry } from '../lib/types';

/** Kenntnisstufe in Worte fassen – Balken haben in einer Bewerbung nichts verloren. */
function levelLabel(level: number): string {
  if (level >= 90) return 'Sehr gut';
  if (level >= 75) return 'Gut';
  if (level >= 55) return 'Fortgeschritten';

  return 'Grundkenntnisse';
}

function escapeHtml(value: string): string {
  const div = document.createElement('div');
  div.textContent = value;

  return div.innerHTML;
}

function entryMarkup(entry: TimelineEntry): string {
  const meta = [entry.org, entry.location].filter(Boolean).join(', ');

  return `
    <div class="cv-entry">
      <div class="cv-entry-period">${escapeHtml(entry.period)}</div>
      <div class="cv-entry-body">
        <p class="cv-entry-title">${escapeHtml(entry.title)}</p>
        ${meta ? `<p class="cv-entry-meta">${escapeHtml(meta)}</p>` : ''}
        ${entry.description ? `<p class="cv-entry-text">${escapeHtml(entry.description)}</p>` : ''}
        ${
          entry.tags.length > 0
            ? `<p class="cv-entry-tags">${entry.tags.map((tag) => escapeHtml(tag)).join(' · ')}</p>`
            : ''
        }
      </div>
    </div>`;
}

function fillTimeline(settings: Settings, type: TimelineEntry['type']): void {
  const block = document.querySelector<HTMLElement>(`[data-cv-block="${type}"]`);
  const container = document.querySelector<HTMLElement>(`[data-cv-entries="${type}"]`);
  if (!block || !container) return;

  // Projekte lassen sich im Dashboard abschalten – eine Bewerbung als
  // Angestellter braucht sie oft nicht.
  if (type === 'project' && !settings.cv.includeProjects) {
    block.remove();
    return;
  }

  const entries = settings.resume.timeline.filter((entry) => entry.type === type);
  if (entries.length === 0) {
    block.remove();
    return;
  }

  container.innerHTML = entries.map(entryMarkup).join('');
  block.hidden = false;
}

function fillExpertise(settings: Settings): void {
  const block = document.querySelector<HTMLElement>('[data-cv-block="expertise"]');
  const container = document.querySelector<HTMLElement>('[data-cv-expertise]');
  if (!block || !container) return;

  if (!settings.cv.includeExpertise || settings.expertise.length === 0) {
    block.remove();
    return;
  }

  // Nach Bereich gruppieren – eine lange Aufzählung liest niemand.
  const groups = new Map<string, ExpertiseItem[]>();
  settings.expertise
    .filter((item) => item.name.trim() !== '')
    .forEach((item) => {
      const key = item.group || 'Weitere';
      groups.set(key, [...(groups.get(key) ?? []), item]);
    });

  container.innerHTML = [...groups.entries()]
    .map(
      ([group, items]) => `
      <div class="cv-entry">
        <div class="cv-entry-period">${escapeHtml(group)}</div>
        <div class="cv-entry-body">
          <p class="cv-entry-text">
            ${items.map((item) => `${escapeHtml(item.name)} <span class="cv-level">(${levelLabel(item.level)})</span>`).join(' · ')}
          </p>
        </div>
      </div>`,
    )
    .join('');

  block.hidden = false;
}

function fillLanguages(settings: Settings): void {
  const block = document.querySelector<HTMLElement>('[data-cv-block="languages"]');
  const container = document.querySelector<HTMLElement>('[data-cv-languages]');
  if (!block || !container) return;

  const languages = settings.resume.languages.filter((language) => language.name.trim() !== '');
  if (languages.length === 0) {
    block.remove();
    return;
  }

  container.innerHTML = languages
    .map(
      (language) => `
      <div class="cv-entry">
        <div class="cv-entry-period">${escapeHtml(language.name)}</div>
        <div class="cv-entry-body"><p class="cv-entry-text">${escapeHtml(language.level)}</p></div>
      </div>`,
    )
    .join('');

  block.hidden = false;
}

function fillDetails(settings: Settings): void {
  const container = document.querySelector<HTMLElement>('[data-cv-details]');
  if (!container) return;

  // Kontaktdaten stehen immer drin, dazu die frei gepflegten Angaben.
  const rows: Array<[string, string]> = [
    ['E-Mail', settings.email],
    ...settings.cv.details
      .filter((detail) => detail.value.trim() !== '')
      .map((detail): [string, string] => [detail.label, detail.value]),
  ];

  if (settings.location.trim() !== '' && !rows.some(([label]) => label.toLowerCase() === 'anschrift')) {
    rows.push(['Standort', settings.location]);
  }

  container.innerHTML = rows
    .filter(([, value]) => value.trim() !== '')
    .map(
      ([label, value]) => `
      <div class="cv-detail">
        <dt>${escapeHtml(label)}</dt>
        <dd>${escapeHtml(value)}</dd>
      </div>`,
    )
    .join('');
}

function fillPhoto(settings: Settings): void {
  const figure = document.querySelector<HTMLElement>('[data-cv-photo]');
  const image = document.querySelector<HTMLImageElement>('[data-cv-photo-image]');
  if (!figure || !image) return;

  const portrait = settings.portrait?.image;
  if (!settings.cv.includePhoto || !portrait) {
    figure.remove();
    return;
  }

  image.src = portrait.thumbUrl ?? portrait.url;
  image.alt = `Porträt von ${settings.name}`;
  figure.hidden = false;
}

export async function initCv(): Promise<void> {
  if (!document.querySelector('[data-cv]')) return;

  const settings = await fetchSettings().catch(() => null);
  if (!settings) return;

  document.querySelectorAll<HTMLElement>('[data-name]').forEach((el) => (el.textContent = settings.name));
  document.querySelectorAll<HTMLElement>('[data-role]').forEach((el) => (el.textContent = settings.role));

  fillPhoto(settings);
  fillDetails(settings);

  const profileBlock = document.querySelector<HTMLElement>('[data-cv-profile-block]');
  const profile = document.querySelector<HTMLElement>('[data-cv-profile]');
  const profileText = settings.cv.profile.trim() || settings.intro.split(/\n{2,}/)[0]?.trim() || '';
  if (profileBlock && profile && profileText !== '') {
    profile.textContent = profileText;
    profileBlock.hidden = false;
  } else {
    profileBlock?.remove();
  }

  fillTimeline(settings, 'work');
  fillTimeline(settings, 'education');
  fillTimeline(settings, 'project');
  fillExpertise(settings);
  fillLanguages(settings);

  const footer = document.querySelector<HTMLElement>('[data-cv-footer]');
  if (footer) {
    const text = settings.cv.footer.trim();
    if (text === '') {
      footer.remove();
    } else {
      footer.textContent = text;
      footer.hidden = false;
    }
  }

  document.querySelectorAll<HTMLButtonElement>('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
  });
}
