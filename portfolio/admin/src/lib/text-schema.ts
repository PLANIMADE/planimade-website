/**
 * Beschreibung aller pflegbaren Website-Texte für das Dashboard.
 *
 * Die Schlüssel entsprechen den `data-text`-Attributen im Frontend. Diese
 * Liste steuert nur, wie die Felder im Dashboard heißen und gruppiert sind –
 * gespeichert wird eine schlichte Zuordnung Schlüssel → Text.
 */

export interface TextField {
  key: string;
  label: string;
  /** Mehrzeiliges Eingabefeld statt einzeiligem */
  long?: boolean;
}

export interface TextGroup {
  title: string;
  hint: string;
  fields: TextField[];
}

export const TEXT_GROUPS: TextGroup[] = [
  {
    title: 'Startseite — Kopfbereich',
    hint: 'Name, Leitsatz und Rolle stehen unter „Profil". Hier geht es um die Beschriftungen drumherum.',
    fields: [
      { key: 'home.hero.button', label: 'Knopf' },
      { key: 'home.hero.scroll', label: 'Scroll-Hinweis' },
    ],
  },
  {
    title: 'Startseite — Vorstellung',
    hint: 'Der Fließtext selbst steht unter „Profil → Vorstellungstext".',
    fields: [
      { key: 'home.intro.label', label: 'Kleine Überschrift' },
      { key: 'home.intro.headline', label: 'Überschrift', long: true },
      { key: 'home.stats.1.value', label: 'Kennzahl 1 — Wert' },
      { key: 'home.stats.1.label', label: 'Kennzahl 1 — Bezeichnung' },
      { key: 'home.stats.2.value', label: 'Kennzahl 2 — Wert' },
      { key: 'home.stats.2.label', label: 'Kennzahl 2 — Bezeichnung' },
      { key: 'home.stats.3.value', label: 'Kennzahl 3 — Wert' },
      { key: 'home.stats.3.label', label: 'Kennzahl 3 — Bezeichnung' },
    ],
  },
  {
    title: 'Startseite — Projekte',
    hint: '',
    fields: [
      { key: 'home.work.label', label: 'Kleine Überschrift' },
      { key: 'home.work.headline', label: 'Überschrift' },
      { key: 'home.work.lead', label: 'Einleitung', long: true },
      { key: 'home.work.action', label: 'Link rechts' },
    ],
  },
  {
    title: 'Startseite — Fähigkeiten, Ablauf, Stimmen',
    hint: 'Die Inhalte dieser Abschnitte pflegst du unter „Fähigkeiten & Ablauf" bzw. „Kundenstimmen".',
    fields: [
      { key: 'home.skills.label', label: 'Fähigkeiten — kleine Überschrift' },
      { key: 'home.skills.headline', label: 'Fähigkeiten — Überschrift' },
      { key: 'home.skills.lead', label: 'Fähigkeiten — Einleitung', long: true },
      { key: 'home.process.label', label: 'Ablauf — kleine Überschrift' },
      { key: 'home.process.headline', label: 'Ablauf — Überschrift' },
      { key: 'home.testimonials.label', label: 'Stimmen — kleine Überschrift' },
      { key: 'home.testimonials.headline', label: 'Stimmen — Überschrift' },
    ],
  },
  {
    title: 'Startseite — Abschluss',
    hint: 'Der große Block ganz unten, der zum Kontakt führt.',
    fields: [
      { key: 'home.cta.label', label: 'Kleine Überschrift' },
      { key: 'home.cta.headline', label: 'Überschrift' },
      { key: 'home.cta.lead', label: 'Text', long: true },
      { key: 'home.cta.button', label: 'Knopf' },
    ],
  },
  {
    title: 'Seite „Arbeiten"',
    hint: '',
    fields: [
      { key: 'work.label', label: 'Kleine Überschrift' },
      { key: 'work.headline', label: 'Überschrift' },
      { key: 'work.lead', label: 'Einleitung', long: true },
    ],
  },
  {
    title: 'Seite „Über mich"',
    hint: 'Vorstellungstext, Werdegang und Kompetenzen stehen unter „Profil" und „Lebenslauf".',
    fields: [
      { key: 'about.label', label: 'Kleine Überschrift' },
      { key: 'about.print', label: 'Druck-Knopf' },
      { key: 'about.aside.location', label: 'Seitenspalte — Standort' },
      { key: 'about.aside.status', label: 'Seitenspalte — Status' },
      { key: 'about.aside.languages', label: 'Seitenspalte — Sprachen' },
      { key: 'about.aside.contact', label: 'Seitenspalte — Erreichbar' },
      { key: 'about.aside.elsewhere', label: 'Seitenspalte — Woanders' },
      { key: 'about.timeline.label', label: 'Werdegang — kleine Überschrift' },
      { key: 'about.timeline.headline', label: 'Werdegang — Überschrift' },
      { key: 'about.timeline.lead', label: 'Werdegang — Einleitung', long: true },
      { key: 'about.expertise.label', label: 'Kompetenzen — kleine Überschrift' },
      { key: 'about.expertise.headline', label: 'Kompetenzen — Überschrift' },
      { key: 'about.expertise.lead', label: 'Kompetenzen — Einleitung', long: true },
      { key: 'about.skills.label', label: 'Disziplinen — kleine Überschrift' },
      { key: 'about.skills.headline', label: 'Disziplinen — Überschrift' },
      { key: 'about.process.label', label: 'Ablauf — kleine Überschrift' },
      { key: 'about.process.headline', label: 'Ablauf — Überschrift' },
      { key: 'about.cta.label', label: 'Abschluss — kleine Überschrift' },
      { key: 'about.cta.headline', label: 'Abschluss — Überschrift' },
      { key: 'about.cta.button', label: 'Abschluss — Knopf' },
    ],
  },
  {
    title: 'Seite „Kontakt"',
    hint: '',
    fields: [
      { key: 'contact.label', label: 'Kleine Überschrift' },
      { key: 'contact.headline', label: 'Überschrift' },
      { key: 'contact.lead', label: 'Einleitung', long: true },
      { key: 'contact.direct', label: 'Beschriftung E-Mail-Block' },
      { key: 'contact.form.button', label: 'Absende-Knopf' },
    ],
  },
  {
    title: 'Fußzeile',
    hint: 'Erscheint auf jeder Seite ganz unten.',
    fields: [
      { key: 'footer.headline', label: 'Überschrift' },
      { key: 'footer.lead', label: 'Text', long: true },
      { key: 'footer.nav', label: 'Spaltentitel Navigation' },
      { key: 'footer.elsewhere', label: 'Spaltentitel Social-Links' },
    ],
  },
  {
    title: 'Fehlerseite (404)',
    hint: 'Wird angezeigt, wenn jemand eine Adresse aufruft, die es nicht gibt.',
    fields: [
      { key: 'notFound.headline', label: 'Überschrift' },
      { key: 'notFound.lead', label: 'Text', long: true },
      { key: 'notFound.button', label: 'Knopf' },
    ],
  },
];
