/**
 * Bewerbungs-Lebenslauf.
 *
 * Holt die Daten von `/api/cv` – die Route verlangt einen Login. Kommt 401
 * zurück, erscheint statt des Dokuments ein Hinweis: Der Lebenslauf enthält
 * Anschrift und Geburtsjahr und geht niemanden sonst etwas an.
 *
 * Aufbau zweispaltig: links Kontakt, Sprachen und Kenntnisse, rechts Profil
 * und Werdegang. Die Schnellschalter oben wirken nur auf den aktuellen
 * Ausdruck – die dauerhaften Vorgaben stehen im Dashboard.
 */

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
  const meta = [entry.org, entry.location].filter(Boolean).join(' · ');

  return `
    <div class="cv-entry">
      <p class="cv-entry-period">${escapeHtml(entry.period)}</p>
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
      <div class="cv-skill-group">
        <p class="cv-skill-group-title">${escapeHtml(group)}</p>
        <ul class="cv-skills">
          ${items
            .map(
              (item) => `
            <li class="cv-skill">
              <span>${escapeHtml(item.name)}</span>
              <span class="cv-skill-level">${levelLabel(item.level)}</span>
            </li>`,
            )
            .join('')}
        </ul>
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
      <div class="cv-contact-row">
        <dt>${escapeHtml(language.name)}</dt>
        <dd>${escapeHtml(language.level)}</dd>
      </div>`,
    )
    .join('');

  block.hidden = false;
}

function fillDetails(settings: Settings): void {
  const container = document.querySelector<HTMLElement>('[data-cv-details]');
  if (!container) return;

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
      <div class="cv-contact-row">
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
  if (!portrait) {
    figure.remove();
    return;
  }

  image.src = portrait.url;
  image.alt = `Porträt von ${settings.name}`;
  figure.hidden = !settings.cv.includePhoto;
}

/** Schnellschalter: wirken nur auf diesen einen Export. */
function wireToggles(sheet: HTMLElement): void {
  const apply = (name: string, on: boolean): void => {
    if (name === 'photo') {
      const photo = document.querySelector<HTMLElement>('[data-cv-photo]');
      if (photo) photo.hidden = !on;
    }
    if (name === 'projects') {
      const block = document.querySelector<HTMLElement>('[data-cv-block="project"]');
      if (block) block.hidden = !on;
    }
    if (name === 'color') {
      sheet.classList.toggle('cv-mono', !on);
    }
  };

  document.querySelectorAll<HTMLInputElement>('[data-cv-toggle]').forEach((input) => {
    const name = input.dataset.cvToggle ?? '';

    // Startzustand an das anpassen, was tatsächlich sichtbar ist.
    if (name === 'photo') {
      input.checked = document.querySelector<HTMLElement>('[data-cv-photo]')?.hidden === false;
    }
    if (name === 'projects') {
      input.checked = document.querySelector<HTMLElement>('[data-cv-block="project"]')?.hidden === false;
    }

    input.addEventListener('change', () => apply(name, input.checked));
  });
}

export async function initCv(): Promise<void> {
  const root = document.querySelector<HTMLElement>('[data-cv]');
  if (!root) return;

  const locked = root.querySelector<HTMLElement>('[data-cv-locked]');
  const content = root.querySelector<HTMLElement>('[data-cv-content]');
  if (!locked || !content) return;

  const response = await fetch('/api/cv', {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  }).catch(() => null);

  if (!response || !response.ok) {
    locked.hidden = false;
    return;
  }

  const settings = ((await response.json()) as { settings: Settings }).settings;
  content.hidden = false;

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

  const sheet = document.querySelector<HTMLElement>('[data-cv-sheet]');
  if (sheet) {
    // Akzentfarbe des Dokuments folgt der Website.
    wireToggles(sheet);
  }

  document.querySelectorAll<HTMLButtonElement>('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
  });
}
