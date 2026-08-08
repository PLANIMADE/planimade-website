/**
 * Bewerbungs-Lebenslauf.
 *
 * Holt die Daten von `/api/cv` – die Route verlangt einen Login. Kommt 401
 * zurück, erscheint statt des Dokuments ein Hinweis: Der Lebenslauf enthält
 * Anschrift und Geburtsjahr und geht niemanden sonst etwas an.
 *
 * Aufbau: farbiger Kopf mit Name und Foto, ein Schlagwortband, darunter eine
 * schmale Spalte mit Kontakt, Sprachen und Eckdaten neben dem Werdegang. Die
 * Abschnittsnummern zählt das CSS selbst, damit abgeschaltete Blöcke keine
 * Lücke in der Reihenfolge hinterlassen.
 *
 * Die Schnellschalter oben wirken nur auf den aktuellen Ausdruck – die
 * dauerhaften Vorgaben stehen im Dashboard.
 */

import type { ExpertiseItem, Settings, TimelineEntry } from '../lib/types';

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
            ? `<p class="cv-entry-tags">${entry.tags
                .map((tag) => `<span class="cv-tag">${escapeHtml(tag)}</span>`)
                .join('')}</p>`
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

/**
 * Kenntnisse als Chipwand: gefüllt = Kernwerkzeug, farbige Kontur = sicher,
 * neutral = vorhanden. Drei Stufen statt Prozentbalken – die behaupten eine
 * Genauigkeit, die eine Selbsteinschätzung nicht hat.
 */
function chipClass(level: number): string {
  if (level >= 88) return 'cv-chip is-key';
  if (level >= 65) return 'cv-chip is-strong';

  return 'cv-chip';
}

function fillExpertise(settings: Settings): void {
  const block = document.querySelector<HTMLElement>('[data-cv-block="expertise"]');
  const container = document.querySelector<HTMLElement>('[data-cv-expertise]');
  if (!block || !container) return;

  const items = settings.expertise.filter((item) => item.name.trim() !== '');
  if (!settings.cv.includeExpertise || items.length === 0) {
    block.remove();
    return;
  }

  const groups = new Map<string, ExpertiseItem[]>();
  items.forEach((item) => {
    const key = item.group || 'Weitere';
    groups.set(key, [...(groups.get(key) ?? []), item]);
  });

  container.innerHTML = [...groups.entries()]
    .map(
      ([group, entries]) => `
      <div class="cv-chip-group">
        <p class="cv-chip-group-title">${escapeHtml(group)}</p>
        <ul class="cv-chips">
          ${entries
            .map((item) => `<li class="${chipClass(item.level)}">${escapeHtml(item.name)}</li>`)
            .join('')}
        </ul>
      </div>`,
    )
    .join('');

  block.hidden = false;
}

/**
 * Erlaubt einen Zeilenumbruch hinter „@" und „/", indem dort ein unsichtbares
 * Trennzeichen steht. Ohne das bricht der Browser eine lange E-Mail mitten im
 * Wort um – „…@gma / il.com" sieht auf Papier falsch aus. Punkte bleiben
 * bewusst außen vor, sonst landet „com" allein in der nächsten Zeile.
 */
function softBreaks(value: string): string {
  return value.replace(/([@/])/g, '$1​');
}

/** Zeilen für die schmale Spalte – Beschriftung oben, Wert darunter. */
function rowsMarkup(rows: Array<[string, string]>, rowClass: string): string {
  return rows
    .filter(([, value]) => value.trim() !== '')
    .map(
      ([label, value]) => `
      <div class="${rowClass}">
        <dt>${escapeHtml(label)}</dt>
        <dd>${escapeHtml(softBreaks(value))}</dd>
      </div>`,
    )
    .join('');
}

function fillLanguages(settings: Settings): void {
  const block = document.querySelector<HTMLElement>('[data-cv-block="languages"]');
  const container = document.querySelector<HTMLElement>('[data-cv-languages]');
  if (!block || !container) return;

  const rows = settings.resume.languages
    .filter((language) => language.name.trim() !== '')
    .map((language): [string, string] => [language.name, language.level]);

  if (rows.length === 0) {
    block.remove();
    return;
  }

  container.innerHTML = rowsMarkup(rows, 'cv-contact-row');
  block.hidden = false;
}

function fillFacts(settings: Settings): void {
  const block = document.querySelector<HTMLElement>('[data-cv-block="facts"]');
  const container = document.querySelector<HTMLElement>('[data-cv-facts]');
  if (!block || !container) return;

  const rows = settings.resume.facts
    .filter((fact) => fact.label.trim() !== '' && fact.value.trim() !== '')
    .map((fact): [string, string] => [fact.label, fact.value]);

  if (rows.length === 0) {
    block.remove();
    return;
  }

  container.innerHTML = rowsMarkup(rows, 'cv-facts-row');
  block.hidden = false;
}

/**
 * Die E-Mail ist der längste Einzelwert und passt selten in die schmale
 * Spalte. Statt sie umbrechen zu lassen, wird sie so weit verkleinert, bis
 * sie in eine Zeile passt – bis auf drei Viertel der Ausgangsgröße. Erst
 * wenn selbst das nicht reicht, greift der Umbruch hinter dem @.
 *
 * Gesetzt wird nur ein Faktor; die Ausgangsgröße rechnet das Stylesheet je
 * Medium selbst. Eine feste Pixelgröße von hier aus würde die kleinere
 * Druckgröße überschreiben.
 */
function fitEmail(dd: HTMLElement): void {
  dd.style.removeProperty('--fit');
  dd.style.whiteSpace = 'nowrap';

  for (const faktor of [1, 0.94, 0.88, 0.82, 0.76]) {
    dd.style.setProperty('--fit', String(faktor));
    if (dd.scrollWidth <= dd.clientWidth) return;
  }

  // Passt selbst verkleinert nicht – dann lieber umbrechen als überlaufen.
  dd.style.removeProperty('--fit');
  dd.style.whiteSpace = '';
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

  container.innerHTML = rowsMarkup(rows, 'cv-contact-row');

  const email = container.querySelector<HTMLElement>('.cv-contact-row:first-child dd');
  if (email) {
    fitEmail(email);
    // Der Druck ist schmaler als das Fenster – also neu rechnen.
    window.addEventListener('beforeprint', () => fitEmail(email));
    window.addEventListener('afterprint', () => fitEmail(email));
    window.addEventListener('resize', () => fitEmail(email));
  }
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

/**
 * Das Schlagwortband unter dem Kopf zeigt dieselben Wörter wie das Laufband
 * der Startseite – so trägt der Lebenslauf dieselbe Handschrift.
 *
 * Wie viele Begriffe hineinpassen, hängt von der Breite ab: am Bildschirm
 * mehr als auf A4. Statt eine Zahl zu raten, werden Wörter entfernt, bis die
 * Zeile passt – sonst stünde dort ein abgeschnittenes „ECHTZEIT-RENDE…".
 */
function fitStrip(strip: HTMLElement, words: string[]): void {
  for (let count = words.length; count > 0; count -= 1) {
    strip.textContent = words.slice(0, count).join('  /  ');
    if (strip.scrollWidth <= strip.clientWidth) return;
  }
}

function fillStrip(settings: Settings): void {
  const strip = document.querySelector<HTMLElement>('[data-cv-strip]');
  if (!strip) return;

  const words = settings.marquee.map((word) => word.trim()).filter((word) => word !== '');
  if (words.length === 0) {
    strip.remove();
    return;
  }

  strip.hidden = false;
  fitStrip(strip, words);

  // Der Druck ist schmaler als das Fenster – also vor dem Druck neu rechnen
  // und danach wieder auf die Bildschirmbreite zurück.
  window.addEventListener('beforeprint', () => fitStrip(strip, words));
  window.addEventListener('afterprint', () => fitStrip(strip, words));
  window.addEventListener('resize', () => fitStrip(strip, words));
}

function fillName(settings: Settings): void {
  // „Dominic Majewski" wird zu zwei Zeilen: erste Zeile gefüllt, zweite als
  // Kontur. Bei einteiligen Namen bleibt die zweite Zeile leer.
  const parts = settings.name.trim().split(/\s+/);
  const last = parts.length > 1 ? parts.pop() ?? '' : '';
  const first = parts.join(' ');

  const firstEl = document.querySelector<HTMLElement>('[data-name-first]');
  const lastEl = document.querySelector<HTMLElement>('[data-name-last]');
  if (firstEl) firstEl.textContent = first;
  if (lastEl) {
    lastEl.textContent = last;
    if (last === '') lastEl.remove();
  }
}

function fillFooter(settings: Settings): void {
  const text = document.querySelector<HTMLElement>('[data-cv-footer]');
  if (text) {
    const value = settings.cv.footer.trim();
    if (value === '') {
      text.remove();
    } else {
      text.textContent = value;
      text.hidden = false;
    }
  }

  // Die eigene Adresse gehört auf ein Bewerbungsblatt – sie kommt aus dem
  // Browser, damit sie nach einem Domainwechsel nicht veraltet.
  const site = document.querySelector<HTMLElement>('[data-cv-site]');
  if (site) site.textContent = window.location.host.replace(/^www\./, '');

  const year = document.querySelector<HTMLElement>('[data-cv-year]');
  if (year) year.textContent = String(new Date().getFullYear());
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
      // „Foto farbig" ergibt nur in der Schwarzweiß-Fassung einen Sinn.
      document
        .querySelectorAll<HTMLElement>('[data-cv-only-mono]')
        .forEach((element) => (element.hidden = on));
    }
    if (name === 'photocolor') {
      sheet.classList.toggle('cv-photo-color', on);
    }
    if (name === 'dark') {
      sheet.classList.toggle('cv-dark', on);
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
    if (name === 'dark') {
      input.checked = sheet.classList.contains('cv-dark');
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

  fillName(settings);
  document.querySelectorAll<HTMLElement>('[data-role]').forEach((el) => (el.textContent = settings.role));

  fillPhoto(settings);
  fillStrip(settings);
  fillDetails(settings);
  fillLanguages(settings);
  fillFacts(settings);

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
  fillFooter(settings);

  const sheet = document.querySelector<HTMLElement>('[data-cv-sheet]');
  if (sheet) {
    // Papierfarbe zuerst setzen, damit die Schalter oben den richtigen
    // Startzustand ablesen können.
    sheet.classList.toggle('cv-dark', settings.cv.theme === 'dark');
    wireToggles(sheet);
  }

  document.querySelectorAll<HTMLButtonElement>('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
  });
}
