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
 * Bereitet einen Wert für die schmale Spalte auf.
 *
 * Bei E-Mail-Adressen ist die einzige sinnvolle Umbruchstelle vor dem „@":
 * Passt die Adresse nicht in eine Zeile, steht die Domain dann vollständig
 * in der zweiten. Damit der Browser diese Stelle auch nimmt, wird die Domain
 * unteilbar gesetzt – sonst bricht er lieber an einem Bindestrich darin um,
 * weil das die erste Zeile besser füllt („…design@sehr-" / „lange.de").
 *
 * Der lokale Teil bleibt als Notausgang teilbar: Ist er allein schon breiter
 * als die Spalte, ist ein Umbruch darin besser als ein Überlauf.
 */
function valueMarkup(value: string): string {
  const at = value.lastIndexOf('@');
  if (at === -1) {
    return escapeHtml(value).replace(/\//g, '/​');
  }

  const lokal = escapeHtml(value.slice(0, at));
  const domain = escapeHtml(value.slice(at));

  return `${lokal}​<span class="cv-mail-domain">${domain}</span>`;
}

/** Zeilen für die schmale Spalte – Beschriftung oben, Wert darunter. */
function rowsMarkup(rows: Array<[string, string]>, rowClass: string): string {
  return rows
    .filter(([, value]) => value.trim() !== '')
    .map(
      ([label, value]) => `
      <div class="${rowClass}">
        <dt>${escapeHtml(label)}</dt>
        <dd>${valueMarkup(value)}</dd>
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

  // Wie die Eckdaten kurze Wertepaare – Beschriftung links, Stufe rechts.
  container.innerHTML = rowsMarkup(rows, 'cv-facts-row');
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
 * Feste Obergrenze statt Messen: Vorher wurde die Liste auf die Fensterbreite
 * gekürzt, und das Blatt ist im Druck schmaler als jedes übliche Fenster –
 * die Vorschau zeigte also mehr Wörter als das PDF. Fünf passen in beiden
 * Medien in eine Zeile; sind die Begriffe lang, bricht das Band um, statt
 * abgeschnitten zu werden.
 */
const STRIP_MAX = 5;

function fillStrip(settings: Settings): void {
  const strip = document.querySelector<HTMLElement>('[data-cv-strip]');
  if (!strip) return;

  const words = settings.marquee
    .map((word) => word.trim())
    .filter((word) => word !== '')
    .slice(0, STRIP_MAX);

  if (words.length === 0) {
    strip.remove();
    return;
  }

  strip.textContent = words.join('  /  ');
  strip.hidden = false;
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

/**
 * Eigene Akzentfarbe für das Dokument.
 *
 * Gesetzt wird nicht `--accent` selbst, sondern eine vorgelagerte Variable:
 * Ein Inline-Stil schlägt jede Regel im Stylesheet, und „Farbe aus" könnte
 * die Farbe dann nicht mehr auf Schwarz ziehen.
 *
 * Für dunkles Papier wird die Farbe aufgehellt – ein für Weiß gewählter Ton
 * verschwände sonst im Untergrund.
 */
function applyAccent(sheet: HTMLElement, accent: string): void {
  const wert = accent.trim();
  if (!/^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(wert)) {
    sheet.style.removeProperty('--cv-accent');
    sheet.style.removeProperty('--cv-accent-dark');
    return;
  }

  sheet.style.setProperty('--cv-accent', wert);
  sheet.style.setProperty('--cv-accent-dark', `color-mix(in srgb, ${wert} 78%, white)`);
}

/* --------------------------------------------------------- Seitenanpassung */

/**
 * Sammelt die Regeln, die beim Drucken gelten – aufgelöst, nicht verpackt.
 *
 * Der Messrahmen weiter unten ist ein normales Fenster; dort greift `@media
 * print` nicht. Also werden die Druckregeln ausgepackt und als gewöhnliche
 * Regeln übernommen, während reine Bildschirmregeln wegfallen. Alles andere
 * bleibt, wie es ist.
 */
function druckRegeln(rules: CSSRuleList, out: string[]): void {
  for (const rule of Array.from(rules)) {
    if (rule instanceof CSSMediaRule) {
      const bedingung = rule.conditionText;
      if (/\bprint\b/.test(bedingung)) {
        druckRegeln(rule.cssRules, out);
      } else if (!/\bscreen\b/.test(bedingung)) {
        out.push(`@media ${bedingung}{`);
        druckRegeln(rule.cssRules, out);
        out.push('}');
      }
      continue;
    }

    out.push(rule.cssText);
  }
}

function druckStylesheet(): string {
  const out: string[] = [];
  for (const sheet of Array.from(document.styleSheets)) {
    try {
      druckRegeln(sheet.cssRules, out);
    } catch {
      // Fremde Herkunft – kommt hier nicht vor, kostet aber auch nichts.
    }
  }

  return out.join('\n');
}

let messrahmen: HTMLIFrameElement | null = null;

/**
 * Misst, wie hoch das Blatt im Druck wirklich wird.
 *
 * Direkt messen geht nicht: Am Bildschirm ist das Blatt 62 rem breit und in
 * Viewport-Einheiten gepolstert, im Druck 210 mm breit und in Millimetern.
 * Und `beforeprint` liefert noch die Maße des Fensters, nicht die der Seite.
 * Deshalb bekommt eine Kopie des Blatts einen eigenen Rahmen in A4-Breite,
 * in dem die Druckregeln als normale Regeln gelten.
 */
async function messeBlatt(sheet: HTMLElement): Promise<{ hoehe: number; seite: number } | null> {
  if (!messrahmen) {
    messrahmen = document.createElement('iframe');
    messrahmen.setAttribute('aria-hidden', 'true');
    messrahmen.tabIndex = -1;
    // Die Breite entscheidet mit: Sie ist zugleich die Fensterbreite des
    // Rahmens, an der sich alle Breiten-Abfragen ausrichten – im Druck ist
    // das die Seitenbreite, also 210 mm bei 96 dpi.
    messrahmen.style.cssText =
      'position:fixed;top:0;left:-10000px;width:794px;height:4000px;border:0;visibility:hidden;pointer-events:none';
    document.body.append(messrahmen);
  }

  const doc = messrahmen.contentDocument;
  if (!doc) return null;

  doc.open();
  doc.write('<!doctype html><html><head><meta charset="utf-8"></head><body></body></html>');
  doc.close();

  doc.documentElement.setAttribute('data-theme', document.documentElement.dataset.theme ?? 'dark');
  const style = doc.createElement('style');
  style.textContent = druckStylesheet();
  doc.head.append(style);

  const kopie = sheet.cloneNode(true) as HTMLElement;
  // Eine bereits gesetzte Anpassung würde sich sonst mit der neuen
  // multiplizieren und das Blatt Messung für Messung kleiner rechnen.
  kopie.style.removeProperty('--cv-fit');
  kopie.style.removeProperty('--cv-seiten');

  const buehne = doc.createElement('div');
  buehne.className = 'cv-stage';
  buehne.append(kopie);
  doc.body.style.cssText = 'margin:0;padding:0';
  doc.body.append(buehne);

  // Ein Blatt aus fremden Schriften misst sich falsch, solange die Schriften
  // noch laden – dann steht überall die Ersatzschrift.
  await doc.fonts?.ready?.catch?.(() => undefined);
  await new Promise((resolve) => requestAnimationFrame(resolve));

  const lineal = doc.createElement('div');
  lineal.style.cssText = 'height:297mm;width:0';
  doc.body.append(lineal);
  const seite = lineal.getBoundingClientRect().height;
  lineal.remove();

  const hoehe = kopie.getBoundingClientRect().height;
  if (seite <= 0 || hoehe <= 0) return null;

  return { hoehe, seite };
}

/** Unter diesem Maßstab wird die Schrift zu klein – dann lieber umbrechen. */
const FIT_MIN = 0.9;

/**
 * Verhindert die halbleere Extraseite.
 *
 * Läuft das Dokument nur ein Stück über die Seite, hängt hinten ein Rest von
 * zwei Zeilen und der Rest des Blatts bleibt leer – das sieht nach Unfall aus.
 * In dem Fall wird das ganze Blatt so weit verkleinert, dass es aufgeht.
 * Reicht das nicht ohne die Schrift zu ruinieren, bleibt es beim Umbruch.
 */
async function passeSeitenAn(sheet: HTMLElement): Promise<void> {
  const mass = await messeBlatt(sheet).catch(() => null);
  if (!mass) return;

  // Ein Hauch Luft: Rundungen im Druck sollen keine Seite auslösen.
  const seiten = mass.hoehe / mass.seite - 0.004;

  if (seiten <= 1) {
    sheet.style.removeProperty('--cv-fit');
    sheet.style.setProperty('--cv-seiten', '1');
    return;
  }

  // Eine Seite weniger als der Überhang verlangt – genau die halbleere
  // Extraseite soll ja verschwinden.
  const ziel = Math.floor(seiten);
  const fit = (ziel * mass.seite) / mass.hoehe;

  if (fit < FIT_MIN) {
    sheet.style.removeProperty('--cv-fit');
    sheet.style.setProperty('--cv-seiten', String(Math.ceil(seiten)));
    return;
  }

  sheet.style.setProperty('--cv-fit', fit.toFixed(4));
  sheet.style.setProperty('--cv-seiten', String(ziel));
}

/** Schnellschalter: wirken nur auf diesen einen Export. */
function wireToggles(sheet: HTMLElement, neuMessen: () => void): void {
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

    input.addEventListener('change', () => {
      apply(name, input.checked);
      neuMessen();
    });
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

  // Die Messung läuft asynchron. Wer sofort auf „Als PDF speichern" drückt,
  // soll trotzdem das angepasste Blatt bekommen – deshalb wird immer der
  // letzte Lauf festgehalten und vor dem Drucken abgewartet.
  let laufendeMessung: Promise<void> = Promise.resolve();
  const neuMessen = (): void => {
    if (!sheet) return;
    laufendeMessung = passeSeitenAn(sheet);
  };

  if (sheet) {
    // Papierfarbe zuerst setzen, damit die Schalter oben den richtigen
    // Startzustand ablesen können.
    sheet.classList.toggle('cv-dark', settings.cv.theme === 'dark');
    applyAccent(sheet, settings.cv.accent);
    wireToggles(sheet, neuMessen);
    neuMessen();
  }

  document.querySelectorAll<HTMLButtonElement>('[data-print]').forEach((button) => {
    button.addEventListener('click', async () => {
      await laufendeMessung;
      window.print();
    });
  });
}
