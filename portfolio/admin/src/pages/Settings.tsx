import { useEffect, useState } from 'react';
import { api, type Settings, type TimelineEntry } from '../lib/api';
import { useToast } from '../lib/toast';
import MediaField from '../components/MediaField';
import { TEXT_GROUPS } from '../lib/text-schema';

const TABS = [
  { id: 'profil', label: 'Profil' },
  { id: 'startseite', label: 'Startseite' },
  { id: 'lebenslauf', label: 'Lebenslauf' },
  { id: 'inhalte', label: 'Fähigkeiten & Ablauf' },
  { id: 'texte', label: 'Texte' },
  { id: 'seo', label: 'SEO' },
  { id: 'rechtliches', label: 'Rechtliches' },
  { id: 'konto', label: 'Konto' },
] as const;

const TIMELINE_TYPES = [
  { value: 'work', label: 'Beruflich' },
  { value: 'education', label: 'Ausbildung' },
  { value: 'project', label: 'Projekt' },
] as const;

export default function SettingsPage() {
  const toast = useToast();
  const [settings, setSettings] = useState<Settings | null>(null);
  const [tab, setTab] = useState<string>('profil');
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);

  useEffect(() => {
    api
      .settings()
      .then((data) => setSettings(data.settings))
      .catch(() => toast('Einstellungen konnten nicht geladen werden.', 'error'));
  }, [toast]);

  if (!settings) return <p className="font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>;

  const set = <K extends keyof Settings>(key: K, value: Settings[K]): void => {
    setSettings({ ...settings, [key]: value });
    setDirty(true);
  };

  const save = async () => {
    setSaving(true);
    try {
      const data = await api.saveSettings(settings);
      setSettings(data.settings);
      setDirty(false);
      toast('Einstellungen gespeichert');
    } catch (error) {
      toast(error instanceof Error ? error.message : 'Speichern fehlgeschlagen', 'error');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="animate-in space-y-6 pb-20">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold tracking-tight">Einstellungen</h1>
          <p className="mt-1 text-sm text-muted">Alle Texte, die nicht zu einem einzelnen Projekt gehören.</p>
        </div>
        <div className="flex items-center gap-3">
          {dirty && <span className="text-xs text-warn">Ungespeichert</span>}
          <button type="button" onClick={() => void save()} className="btn btn-primary" disabled={saving}>
            {saving ? 'Speichert …' : 'Speichern'}
          </button>
        </div>
      </header>

      <div className="flex flex-wrap gap-1 border-b border-line">
        {TABS.map((entry) => (
          <button
            key={entry.id}
            type="button"
            onClick={() => setTab(entry.id)}
            className={`-mb-px border-b-2 px-4 py-2.5 text-sm transition-colors ${
              tab === entry.id ? 'border-accent text-ink' : 'border-transparent text-muted hover:text-ink'
            }`}
          >
            {entry.label}
          </button>
        ))}
      </div>

      {tab === 'profil' && (
        <div className="space-y-6">
          <section className="panel grid gap-4 p-6 sm:grid-cols-2">
            <Field label="Name" value={settings.name} onChange={(value) => set('name', value)} />
            <Field label="Rolle / Untertitel" value={settings.role} onChange={(value) => set('role', value)} />
            <Field label="Standort" value={settings.location} onChange={(value) => set('location', value)} />
            <Field label="E-Mail" value={settings.email} onChange={(value) => set('email', value)} type="email" />
            <div className="sm:col-span-2">
              <Field
                label="Leitsatz (Hero)"
                value={settings.tagline}
                onChange={(value) => set('tagline', value)}
                hint="Der Satz direkt unter deinem Namen auf der Startseite."
              />
            </div>
            <div className="sm:col-span-2">
              <label className="label">Vorstellungstext</label>
              <textarea
                className="field min-h-40 resize-y leading-relaxed"
                value={settings.intro}
                onChange={(event) => set('intro', event.target.value)}
              />
              <p className="mt-1 text-[0.6875rem] text-faint">
                Leerzeile = neuer Absatz. Erscheint auf Startseite und „Über mich".
              </p>
            </div>
          </section>

          <section className="panel space-y-5 p-6">
            <h2 className="text-sm font-semibold">Porträtbild</h2>
            <p className="text-[0.6875rem] leading-relaxed text-faint">
              Erscheint auf der Seite „Über mich" neben deinem Lebenslauf und im Ausdruck. Hochformat
              wirkt am besten (etwa 4:5).
            </p>

            <div className="grid gap-5 sm:grid-cols-2">
              <MediaField
                label="Bild"
                hint="Ohne Bild bleibt die Stelle einfach leer."
                value={settings.portrait.image}
                onChange={(item) => set('portrait', { ...settings.portrait, image: item, mediaId: item?.id ?? null })}
              />
              <div>
                <label className="label" htmlFor="portrait-caption">
                  Bildunterschrift (optional)
                </label>
                <input
                  id="portrait-caption"
                  className="field"
                  value={settings.portrait.caption}
                  placeholder="z. B. Foto: Vorname Nachname"
                  onChange={(event) => set('portrait', { ...settings.portrait, caption: event.target.value })}
                />
              </div>
            </div>
          </section>

          <section className="panel space-y-5 p-6">
            <h2 className="text-sm font-semibold">Logo</h2>
            <p className="text-[0.6875rem] leading-relaxed text-faint">
              Ersetzt das Monogramm in der Navigation und im Lebenslauf. Ein PNG oder SVG mit
              transparentem Hintergrund passt sich beiden Farbschemata an; das Logo wird
              vollständig gezeigt, nicht beschnitten. Ohne Logo stehen dort die Initialen aus
              deinem Namen.
            </p>

            <div className="grid gap-5 sm:grid-cols-2">
              <MediaField
                label="Logo"
                hint="Am besten quer oder quadratisch, mit etwas Luft am Rand."
                value={settings.logo.image}
                onChange={(item) => set('logo', { ...settings.logo, image: item, mediaId: item?.id ?? null })}
              />
              <div>
                <label className="label" htmlFor="logo-alt">
                  Alternativtext
                </label>
                <input
                  id="logo-alt"
                  className="field"
                  value={settings.logo.alt}
                  placeholder={settings.name}
                  onChange={(event) => set('logo', { ...settings.logo, alt: event.target.value })}
                />
                <p className="mt-1 text-[0.6875rem] text-faint">
                  Was Screenreader vorlesen. Leer lassen: dann wird dein Name verwendet.
                </p>
              </div>
            </div>
          </section>

          <section className="panel space-y-4 p-6">
            <h2 className="text-sm font-semibold">Darstellung</h2>
            <p className="text-[0.6875rem] leading-relaxed text-faint">
              Gilt für Besucher, die zum ersten Mal kommen. Wer selbst umschaltet, behält seine Wahl.
            </p>

            <div className="grid gap-2 sm:grid-cols-3">
              {(
                [
                  ['light', 'Hell', 'Immer im hellen Design starten'],
                  ['dark', 'Dunkel', 'Immer im dunklen Design starten'],
                  ['system', 'Wie das Gerät', 'Folgt der Systemeinstellung'],
                ] as const
              ).map(([value, label, hint]) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => set('appearance', { ...settings.appearance, defaultTheme: value })}
                  className={`rounded-lg border p-3 text-left transition-colors ${
                    settings.appearance.defaultTheme === value
                      ? 'border-accent bg-accent/10'
                      : 'border-line hover:border-line-strong'
                  }`}
                >
                  <span className="block text-sm">{label}</span>
                  <span className="mt-0.5 block text-[0.6875rem] leading-relaxed text-faint">{hint}</span>
                </button>
              ))}
            </div>
          </section>

          <section className="panel space-y-4 p-6">
            <h2 className="text-sm font-semibold">Status-Feld</h2>

            <label className="flex cursor-pointer items-start gap-3 text-sm">
              <input
                type="checkbox"
                checked={settings.availability.visible}
                onChange={(event) =>
                  set('availability', { ...settings.availability, visible: event.target.checked })
                }
                className="mt-1 accent-[var(--accent)]"
              />
              <span>
                Status auf der Website anzeigen
                <span className="mt-0.5 block text-[0.6875rem] leading-relaxed text-faint">
                  Das kleine Feld mit dem farbigen Punkt über deinem Namen. Aus = erscheint nirgends.
                </span>
              </span>
            </label>

            {settings.availability.visible && (
              <div className="grid gap-4 sm:grid-cols-3">
                <div>
                  <label className="label">Farbe</label>
                  <select
                    className="field"
                    value={settings.availability.status}
                    onChange={(event) =>
                      set('availability', {
                        ...settings.availability,
                        status: event.target.value as Settings['availability']['status'],
                      })
                    }
                  >
                    <option value="open">Grün</option>
                    <option value="limited">Gelb</option>
                    <option value="closed">Rot</option>
                  </select>
                </div>
                <Field
                  label="Beschriftung"
                  value={settings.availability.label}
                  onChange={(value) => set('availability', { ...settings.availability, label: value })}
                />
                <Field
                  label="Zusatz (optional)"
                  value={settings.availability.detail}
                  onChange={(value) => set('availability', { ...settings.availability, detail: value })}
                />
              </div>
            )}
          </section>

          <section className="panel space-y-4 p-6">
            <h2 className="text-sm font-semibold">Social-Links</h2>
            <p className="text-[0.6875rem] text-faint">Leere Adressen werden auf der Website ausgeblendet.</p>

            {settings.socials.map((social, index) => (
              <div key={index} className="flex gap-2">
                <input
                  className="field w-40 shrink-0"
                  value={social.label}
                  placeholder="Instagram"
                  onChange={(event) => {
                    const next = [...settings.socials];
                    next[index] = { ...social, label: event.target.value };
                    set('socials', next);
                  }}
                />
                <input
                  className="field flex-1"
                  value={social.url}
                  placeholder="https://…"
                  onChange={(event) => {
                    const next = [...settings.socials];
                    next[index] = { ...social, url: event.target.value };
                    set('socials', next);
                  }}
                />
                <button
                  type="button"
                  onClick={() => set('socials', settings.socials.filter((_, position) => position !== index))}
                  className="btn btn-danger px-2.5 text-xs"
                  aria-label="Entfernen"
                >
                  ✕
                </button>
              </div>
            ))}

            <button
              type="button"
              onClick={() => set('socials', [...settings.socials, { label: '', url: '' }])}
              className="btn btn-ghost text-xs"
            >
              + Link
            </button>
          </section>

          <section className="panel space-y-4 p-6">
            <h2 className="text-sm font-semibold">Funktionen</h2>
            {(
              [
                ['analytics', 'Besuchsstatistik erfassen', 'Cookiefrei und anonymisiert – Basis für die Übersicht.'],
                ['sound', 'Interface-Sounds anbieten', 'Standardmäßig aus; Besucher schalten sie selbst ein.'],
                ['cursor', 'Eigener Mauszeiger', 'Nur auf Geräten mit Maus.'],
                ['easterEgg', 'Wireframe-Osterei', 'Konami-Code oder das Wort „render" tippen.'],
              ] as const
            ).map(([key, label, hint]) => (
              <label key={key} className="flex cursor-pointer items-start gap-3 text-sm">
                <input
                  type="checkbox"
                  checked={settings.features[key]}
                  onChange={(event) => set('features', { ...settings.features, [key]: event.target.checked })}
                  className="mt-1 accent-[var(--accent)]"
                />
                <span>
                  {label}
                  <span className="mt-0.5 block text-[0.6875rem] text-faint">{hint}</span>
                </span>
              </label>
            ))}
          </section>
        </div>
      )}

      {tab === 'startseite' && (
        <div className="space-y-6">
          <section className="panel space-y-4 p-6">
            <h2 className="text-sm font-semibold">Kopfbereich</h2>
            <p className="text-[0.6875rem] leading-relaxed text-faint">
              Das Erste, was Besucher sehen. Typografie wirkt ruhiger, ein Showreel zeigt in drei
              Sekunden, was du kannst.
            </p>

            <div className="grid gap-2 sm:grid-cols-2">
              {(
                [
                  ['type', 'Große Typografie', 'Dein Name über einem bewegten Farbverlauf'],
                  ['showreel', 'Showreel-Video', 'Video im Vollbild, Ton auf Wunsch zuschaltbar'],
                ] as const
              ).map(([value, label, hint]) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => set('hero', { ...settings.hero, mode: value })}
                  className={`rounded-lg border p-4 text-left transition-colors ${
                    settings.hero.mode === value ? 'border-accent bg-accent/10' : 'border-line hover:border-line-strong'
                  }`}
                >
                  <span className="block text-sm">{label}</span>
                  <span className="mt-1 block text-[0.6875rem] leading-relaxed text-faint">{hint}</span>
                </button>
              ))}
            </div>
          </section>

          <section className="panel space-y-4 p-6">
            <h2 className="text-sm font-semibold">Laufband</h2>
            <p className="text-[0.6875rem] leading-relaxed text-faint">
              Die Wortliste, die unter dem Kopfbereich durchläuft. Ein Begriff pro Zeile.
            </p>

            <textarea
              className="field min-h-40 resize-y font-mono text-xs leading-relaxed"
              value={settings.marquee.join('\n')}
              onChange={(event) =>
                set(
                  'marquee',
                  event.target.value
                    .split('\n')
                    .map((entry) => entry.trim())
                    .filter(Boolean),
                )
              }
            />
            <p className="text-[0.6875rem] text-faint">
              {settings.marquee.length} Begriffe · leer lassen blendet das Laufband aus
            </p>
          </section>

          {settings.hero.mode === 'showreel' && (
            <section className="panel space-y-5 p-6">
              <h2 className="text-sm font-semibold">Showreel</h2>

              <div className="grid gap-5 sm:grid-cols-2">
                <MediaField
                  label="Video"
                  hint="Am besten web-optimiertes MP4 (H.264). Läuft stumm in Dauerschleife."
                  kind="video"
                  value={settings.hero.video}
                  onChange={(item) => set('hero', { ...settings.hero, video: item, mediaId: item?.id ?? null })}
                />
                <MediaField
                  label="Standbild"
                  hint="Wird angezeigt, solange das Video lädt. Bester erster Frame."
                  value={settings.hero.poster}
                  onChange={(item) => set('hero', { ...settings.hero, poster: item, posterId: item?.id ?? null })}
                />
              </div>

              <div>
                <label className="label" htmlFor="overlay">
                  Abdunklung: {settings.hero.overlay} %
                </label>
                <input
                  id="overlay"
                  type="range"
                  min={0}
                  max={90}
                  step={5}
                  value={settings.hero.overlay}
                  onChange={(event) => set('hero', { ...settings.hero, overlay: Number(event.target.value) })}
                  className="w-full accent-[var(--accent)]"
                />
                <p className="mt-1 text-[0.6875rem] text-faint">
                  Je heller das Video, desto mehr Abdunklung braucht die Schrift darüber.
                </p>
              </div>

              {!settings.hero.video && (
                <p className="rounded-lg border border-warn/40 bg-warn/10 px-3 py-2.5 text-xs text-warn">
                  Ohne Video bleibt die Startseite bei der Typografie-Variante.
                </p>
              )}
            </section>
          )}
        </div>
      )}

      {tab === 'lebenslauf' && (
        <div className="space-y-6">
          <section className="panel space-y-5 p-6">
            <div>
              <h2 className="text-sm font-semibold">Bewerbungs-Lebenslauf</h2>
              <p className="mt-1 text-[0.6875rem] leading-relaxed text-muted">
                Unter{' '}
                <a href="/lebenslauf/" target="_blank" rel="noopener" className="text-accent underline">
                  /lebenslauf/
                </a>{' '}
                entsteht daraus ein klassischer tabellarischer Lebenslauf zum Ausdrucken oder als
                PDF – gespeist aus Werdegang, Kompetenzen und Sprachen weiter unten. Die Seite ist
                für Suchmaschinen gesperrt.
              </p>
            </div>

            <div>
              <label className="label" htmlFor="cv-profile">
                Kurzprofil
              </label>
              <textarea
                id="cv-profile"
                className="field min-h-28 resize-y"
                value={settings.cv.profile}
                placeholder="Zwei, drei Sätze für Bewerbungen – nüchterner als der Text auf der Website."
                onChange={(event) => set('cv', { ...settings.cv, profile: event.target.value })}
              />
              <p className="mt-1 text-[0.6875rem] text-faint">
                Leer lassen: dann wird der erste Absatz deines Vorstellungstexts verwendet.
              </p>
            </div>

            <div className="space-y-3">
              <p className="label">Kopfdaten</p>
              {settings.cv.details.map((detail, index) => (
                <div key={index} className="flex gap-2">
                  <input
                    className="field w-44 shrink-0"
                    value={detail.label}
                    placeholder="Anschrift"
                    onChange={(event) => {
                      const details = [...settings.cv.details];
                      details[index] = { ...detail, label: event.target.value };
                      set('cv', { ...settings.cv, details });
                    }}
                  />
                  <input
                    className="field flex-1"
                    value={detail.value}
                    placeholder="Musterstraße 1, 12345 Musterstadt"
                    onChange={(event) => {
                      const details = [...settings.cv.details];
                      details[index] = { ...detail, value: event.target.value };
                      set('cv', { ...settings.cv, details });
                    }}
                  />
                  <button
                    type="button"
                    onClick={() =>
                      set('cv', {
                        ...settings.cv,
                        details: settings.cv.details.filter((_, position) => position !== index),
                      })
                    }
                    className="btn btn-danger px-2.5 text-xs"
                    aria-label="Entfernen"
                  >
                    ✕
                  </button>
                </div>
              ))}
              <button
                type="button"
                onClick={() => set('cv', { ...settings.cv, details: [...settings.cv.details, { label: '', value: '' }] })}
                className="btn btn-ghost text-xs"
              >
                + Angabe
              </button>
              <p className="text-[0.6875rem] leading-relaxed text-faint">
                Leere Angaben erscheinen nicht. E-Mail und Standort kommen automatisch aus dem Profil.
              </p>
            </div>

            <div>
              <p className="label">Papier</p>
              <div className="mt-2 flex gap-2">
                {(
                  [
                    ['light', 'Hell'],
                    ['dark', 'Dunkel'],
                  ] as const
                ).map(([value, label]) => (
                  <button
                    key={value}
                    type="button"
                    onClick={() => set('cv', { ...settings.cv, theme: value })}
                    className={`btn text-xs ${settings.cv.theme === value ? 'btn-primary' : 'btn-ghost'}`}
                  >
                    {label}
                  </button>
                ))}
              </div>
              <p className="mt-1 text-[0.6875rem] leading-relaxed text-faint">
                Gilt nur für das Lebenslauf-Dokument, nicht für die Website. Auf der Seite selbst
                kannst du für einen einzelnen Export jederzeit umschalten.
              </p>
            </div>

            <div className="space-y-2">
              {(
                [
                  ['includePhoto', 'Porträtbild zeigen'],
                  ['includeProjects', 'Abschnitt „Projekte" zeigen'],
                  ['includeExpertise', 'Abschnitt „Kenntnisse" zeigen'],
                ] as const
              ).map(([key, label]) => (
                <label key={key} className="flex cursor-pointer items-center gap-3 text-sm">
                  <input
                    type="checkbox"
                    checked={settings.cv[key]}
                    onChange={(event) => set('cv', { ...settings.cv, [key]: event.target.checked })}
                    className="accent-[var(--accent)]"
                  />
                  {label}
                </label>
              ))}
            </div>

            <div>
              <label className="label" htmlFor="cv-footer">
                Fußzeile (optional)
              </label>
              <input
                id="cv-footer"
                className="field"
                value={settings.cv.footer}
                placeholder="z. B. Musterstadt, Januar 2026"
                onChange={(event) => set('cv', { ...settings.cv, footer: event.target.value })}
              />
            </div>
          </section>

          <section className="panel space-y-4 p-6">
            <h2 className="text-sm font-semibold">Eckdaten</h2>
            <p className="text-[0.6875rem] text-faint">Die Zahlenreihe oben auf der Lebenslauf-Seite.</p>

            {settings.resume.facts.map((fact, index) => (
              <div key={index} className="flex gap-2">
                <input
                  className="field w-48 shrink-0"
                  value={fact.label}
                  placeholder="Erfahrung"
                  onChange={(event) => {
                    const facts = [...settings.resume.facts];
                    facts[index] = { ...fact, label: event.target.value };
                    set('resume', { ...settings.resume, facts });
                  }}
                />
                <input
                  className="field flex-1"
                  value={fact.value}
                  placeholder="8+ Jahre"
                  onChange={(event) => {
                    const facts = [...settings.resume.facts];
                    facts[index] = { ...fact, value: event.target.value };
                    set('resume', { ...settings.resume, facts });
                  }}
                />
                <button
                  type="button"
                  onClick={() =>
                    set('resume', {
                      ...settings.resume,
                      facts: settings.resume.facts.filter((_, position) => position !== index),
                    })
                  }
                  className="btn btn-danger px-2.5 text-xs"
                  aria-label="Entfernen"
                >
                  ✕
                </button>
              </div>
            ))}

            <button
              type="button"
              onClick={() =>
                set('resume', { ...settings.resume, facts: [...settings.resume.facts, { label: '', value: '' }] })
              }
              className="btn btn-ghost text-xs"
            >
              + Eckdatum
            </button>
          </section>

          <section className="panel space-y-4 p-6">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold">Werdegang</h2>
              <button
                type="button"
                onClick={() =>
                  set('resume', {
                    ...settings.resume,
                    timeline: [
                      {
                        period: '',
                        title: '',
                        org: '',
                        location: '',
                        description: '',
                        type: 'work',
                        tags: [],
                      },
                      ...settings.resume.timeline,
                    ],
                  })
                }
                className="btn btn-ghost px-3 py-1.5 text-xs"
              >
                + Station
              </button>
            </div>
            <p className="text-[0.6875rem] leading-relaxed text-faint">
              Neueste Station zuerst – die Reihenfolge hier ist auch die Reihenfolge auf der Website.
            </p>

            {settings.resume.timeline.map((entry, index) => (
              <div key={index} className="space-y-3 rounded-lg border border-line p-4">
                <div className="flex flex-wrap gap-2">
                  <input
                    className="field w-40 shrink-0"
                    value={entry.period}
                    placeholder="2022 — heute"
                    onChange={(event) => {
                      const timeline = [...settings.resume.timeline];
                      timeline[index] = { ...entry, period: event.target.value };
                      set('resume', { ...settings.resume, timeline });
                    }}
                  />
                  <input
                    className="field min-w-40 flex-1"
                    value={entry.title}
                    placeholder="Position oder Titel"
                    onChange={(event) => {
                      const timeline = [...settings.resume.timeline];
                      timeline[index] = { ...entry, title: event.target.value };
                      set('resume', { ...settings.resume, timeline });
                    }}
                  />
                  <select
                    className="field w-36 shrink-0"
                    value={entry.type}
                    onChange={(event) => {
                      const timeline = [...settings.resume.timeline];
                      timeline[index] = { ...entry, type: event.target.value as TimelineEntry['type'] };
                      set('resume', { ...settings.resume, timeline });
                    }}
                  >
                    {TIMELINE_TYPES.map((type) => (
                      <option key={type.value} value={type.value}>
                        {type.label}
                      </option>
                    ))}
                  </select>
                  <div className="flex shrink-0 gap-1">
                    <button
                      type="button"
                      onClick={() => {
                        if (index === 0) return;
                        const timeline = [...settings.resume.timeline];
                        [timeline[index - 1], timeline[index]] = [timeline[index]!, timeline[index - 1]!];
                        set('resume', { ...settings.resume, timeline });
                      }}
                      className="btn btn-ghost px-2 py-1 text-xs"
                      aria-label="Nach oben"
                    >
                      ↑
                    </button>
                    <button
                      type="button"
                      onClick={() =>
                        set('resume', {
                          ...settings.resume,
                          timeline: settings.resume.timeline.filter((_, position) => position !== index),
                        })
                      }
                      className="btn btn-danger px-2 py-1 text-xs"
                      aria-label="Station entfernen"
                    >
                      ✕
                    </button>
                  </div>
                </div>

                <div className="flex flex-wrap gap-2">
                  <input
                    className="field min-w-40 flex-1"
                    value={entry.org}
                    placeholder="Firma, Agentur oder Hochschule"
                    onChange={(event) => {
                      const timeline = [...settings.resume.timeline];
                      timeline[index] = { ...entry, org: event.target.value };
                      set('resume', { ...settings.resume, timeline });
                    }}
                  />
                  <input
                    className="field w-40 shrink-0"
                    value={entry.location}
                    placeholder="Ort (optional)"
                    onChange={(event) => {
                      const timeline = [...settings.resume.timeline];
                      timeline[index] = { ...entry, location: event.target.value };
                      set('resume', { ...settings.resume, timeline });
                    }}
                  />
                </div>

                <textarea
                  className="field min-h-16 resize-y text-xs"
                  value={entry.description}
                  placeholder="Was hast du dort gemacht? Zwei Sätze reichen."
                  onChange={(event) => {
                    const timeline = [...settings.resume.timeline];
                    timeline[index] = { ...entry, description: event.target.value };
                    set('resume', { ...settings.resume, timeline });
                  }}
                />

                <input
                  className="field text-xs"
                  value={entry.tags.join(', ')}
                  placeholder="Werkzeuge oder Schwerpunkte, mit Komma getrennt"
                  onChange={(event) => {
                    const timeline = [...settings.resume.timeline];
                    timeline[index] = {
                      ...entry,
                      tags: event.target.value
                        .split(',')
                        .map((tag) => tag.trim())
                        .filter(Boolean),
                    };
                    set('resume', { ...settings.resume, timeline });
                  }}
                />
              </div>
            ))}
          </section>

          <section className="panel space-y-4 p-6">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold">Software-Kompetenzen</h2>
              <button
                type="button"
                onClick={() =>
                  set('expertise', [...settings.expertise, { name: '', level: 70, note: '', group: '' }])
                }
                className="btn btn-ghost px-3 py-1.5 text-xs"
              >
                + Programm
              </button>
            </div>
            <p className="text-[0.6875rem] leading-relaxed text-faint">
              Selbsteinschätzung von 0 bis 100. Gleiche Bereichsnamen werden auf der Website
              zusammengefasst.
            </p>

            {settings.expertise.map((item, index) => (
              <div key={index} className="flex flex-wrap items-center gap-2">
                <input
                  className="field w-40 shrink-0"
                  value={item.name}
                  placeholder="Blender"
                  onChange={(event) => {
                    const expertise = [...settings.expertise];
                    expertise[index] = { ...item, name: event.target.value };
                    set('expertise', expertise);
                  }}
                />
                <input
                  className="field w-44 shrink-0"
                  value={item.group}
                  placeholder="Bereich"
                  list="expertise-groups"
                  onChange={(event) => {
                    const expertise = [...settings.expertise];
                    expertise[index] = { ...item, group: event.target.value };
                    set('expertise', expertise);
                  }}
                />
                <div className="flex min-w-44 flex-1 items-center gap-2">
                  <input
                    type="range"
                    min={0}
                    max={100}
                    step={5}
                    value={item.level}
                    onChange={(event) => {
                      const expertise = [...settings.expertise];
                      expertise[index] = { ...item, level: Number(event.target.value) };
                      set('expertise', expertise);
                    }}
                    className="flex-1 accent-[var(--accent)]"
                    aria-label={`Niveau ${item.name}`}
                  />
                  <span className="w-9 shrink-0 text-right font-mono text-xs text-faint">{item.level}</span>
                </div>
                <input
                  className="field w-32 shrink-0 text-xs"
                  value={item.note}
                  placeholder="Notiz"
                  onChange={(event) => {
                    const expertise = [...settings.expertise];
                    expertise[index] = { ...item, note: event.target.value };
                    set('expertise', expertise);
                  }}
                />
                <button
                  type="button"
                  onClick={() => set('expertise', settings.expertise.filter((_, position) => position !== index))}
                  className="btn btn-danger px-2.5 text-xs"
                  aria-label="Entfernen"
                >
                  ✕
                </button>
              </div>
            ))}

            <datalist id="expertise-groups">
              {[...new Set(settings.expertise.map((item) => item.group).filter(Boolean))].map((group) => (
                <option key={group} value={group} />
              ))}
            </datalist>
          </section>

          <section className="panel space-y-4 p-6">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold">Sprachen</h2>
              <button
                type="button"
                onClick={() =>
                  set('resume', {
                    ...settings.resume,
                    languages: [...settings.resume.languages, { name: '', level: '' }],
                  })
                }
                className="btn btn-ghost px-3 py-1.5 text-xs"
              >
                + Sprache
              </button>
            </div>

            {settings.resume.languages.map((language, index) => (
              <div key={index} className="flex gap-2">
                <input
                  className="field w-48 shrink-0"
                  value={language.name}
                  placeholder="Deutsch"
                  onChange={(event) => {
                    const languages = [...settings.resume.languages];
                    languages[index] = { ...language, name: event.target.value };
                    set('resume', { ...settings.resume, languages });
                  }}
                />
                <input
                  className="field flex-1"
                  value={language.level}
                  placeholder="Muttersprache"
                  onChange={(event) => {
                    const languages = [...settings.resume.languages];
                    languages[index] = { ...language, level: event.target.value };
                    set('resume', { ...settings.resume, languages });
                  }}
                />
                <button
                  type="button"
                  onClick={() =>
                    set('resume', {
                      ...settings.resume,
                      languages: settings.resume.languages.filter((_, position) => position !== index),
                    })
                  }
                  className="btn btn-danger px-2.5 text-xs"
                  aria-label="Entfernen"
                >
                  ✕
                </button>
              </div>
            ))}
          </section>
        </div>
      )}

      {tab === 'inhalte' && (
        <div className="space-y-6">
          <section className="panel space-y-5 p-6">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold">Fähigkeiten</h2>
              <button
                type="button"
                onClick={() => set('skills', [...settings.skills, { title: '', description: '', items: [] }])}
                className="btn btn-ghost px-3 py-1.5 text-xs"
              >
                + Bereich
              </button>
            </div>

            {settings.skills.map((skill, index) => (
              <div key={index} className="space-y-3 rounded-lg border border-line p-4">
                <div className="flex gap-2">
                  <input
                    className="field flex-1"
                    value={skill.title}
                    placeholder="Bereich, z. B. 3D & Rendering"
                    onChange={(event) => {
                      const next = [...settings.skills];
                      next[index] = { ...skill, title: event.target.value };
                      set('skills', next);
                    }}
                  />
                  <button
                    type="button"
                    onClick={() => set('skills', settings.skills.filter((_, position) => position !== index))}
                    className="btn btn-danger px-2.5 text-xs"
                    aria-label="Bereich entfernen"
                  >
                    ✕
                  </button>
                </div>
                <textarea
                  className="field min-h-16 resize-y text-xs"
                  value={skill.description}
                  placeholder="Ein bis zwei Sätze zur Erklärung"
                  onChange={(event) => {
                    const next = [...settings.skills];
                    next[index] = { ...skill, description: event.target.value };
                    set('skills', next);
                  }}
                />
                <input
                  className="field text-xs"
                  value={skill.items.join(', ')}
                  placeholder="Werkzeuge, mit Komma getrennt"
                  onChange={(event) => {
                    const next = [...settings.skills];
                    next[index] = {
                      ...skill,
                      items: event.target.value
                        .split(',')
                        .map((entry) => entry.trim())
                        .filter(Boolean),
                    };
                    set('skills', next);
                  }}
                />
              </div>
            ))}
          </section>

          <section className="panel space-y-4 p-6">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold">Ablauf</h2>
              <button
                type="button"
                onClick={() => set('process', [...settings.process, { title: '', description: '' }])}
                className="btn btn-ghost px-3 py-1.5 text-xs"
              >
                + Schritt
              </button>
            </div>

            {settings.process.map((step, index) => (
              <div key={index} className="flex gap-2">
                <input
                  className="field w-40 shrink-0"
                  value={step.title}
                  placeholder="Briefing"
                  onChange={(event) => {
                    const next = [...settings.process];
                    next[index] = { ...step, title: event.target.value };
                    set('process', next);
                  }}
                />
                <input
                  className="field flex-1"
                  value={step.description}
                  placeholder="Was passiert in diesem Schritt?"
                  onChange={(event) => {
                    const next = [...settings.process];
                    next[index] = { ...step, description: event.target.value };
                    set('process', next);
                  }}
                />
                <button
                  type="button"
                  onClick={() => set('process', settings.process.filter((_, position) => position !== index))}
                  className="btn btn-danger px-2.5 text-xs"
                  aria-label="Schritt entfernen"
                >
                  ✕
                </button>
              </div>
            ))}
          </section>
        </div>
      )}

      {tab === 'texte' && (
        <div className="space-y-6">
          <p className="rounded-lg border border-line bg-panel p-4 text-xs leading-relaxed text-muted">
            Hier stehen alle festen Beschriftungen der Website – Überschriften, Einleitungen,
            Knöpfe. Ein leeres Feld bedeutet: Es bleibt beim ursprünglichen Text. Du kannst also
            nichts kaputt machen.
          </p>

          {TEXT_GROUPS.map((group) => (
            <section key={group.title} className="panel space-y-4 p-6">
              <div>
                <h2 className="text-sm font-semibold">{group.title}</h2>
                {group.hint && <p className="mt-1 text-[0.6875rem] leading-relaxed text-faint">{group.hint}</p>}
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                {group.fields.map((field) => (
                  <div key={field.key} className={field.long ? 'sm:col-span-2' : ''}>
                    <label className="label" htmlFor={field.key}>
                      {field.label}
                    </label>
                    {field.long ? (
                      <textarea
                        id={field.key}
                        className="field min-h-20 resize-y"
                        value={settings.texts[field.key] ?? ''}
                        onChange={(event) =>
                          set('texts', { ...settings.texts, [field.key]: event.target.value })
                        }
                      />
                    ) : (
                      <input
                        id={field.key}
                        className="field"
                        value={settings.texts[field.key] ?? ''}
                        onChange={(event) =>
                          set('texts', { ...settings.texts, [field.key]: event.target.value })
                        }
                      />
                    )}
                  </div>
                ))}
              </div>
            </section>
          ))}
        </div>
      )}

      {tab === 'seo' && (
        <section className="panel grid gap-4 p-6">
          <Field
            label="Seitentitel"
            value={settings.seo.title}
            onChange={(value) => set('seo', { ...settings.seo, title: value })}
            hint="Steht im Browser-Tab und als Überschrift bei Google. Rund 60 Zeichen."
          />
          <div>
            <label className="label">Beschreibung</label>
            <textarea
              className="field min-h-24 resize-y"
              value={settings.seo.description}
              onChange={(event) => set('seo', { ...settings.seo, description: event.target.value })}
            />
            <p className="mt-1 text-[0.6875rem] text-faint">
              Der Text unter dem Suchergebnis. Etwa 150–160 Zeichen ({settings.seo.description.length} aktuell).
            </p>
          </div>
          <Field
            label="Schlagwörter"
            value={settings.seo.keywords}
            onChange={(value) => set('seo', { ...settings.seo, keywords: value })}
          />
        </section>
      )}

      {tab === 'rechtliches' && (
        <section className="panel grid gap-4 p-6 sm:grid-cols-2">
          <p className="text-[0.6875rem] leading-relaxed text-warn sm:col-span-2">
            Diese Angaben landen direkt in Impressum und Datenschutzerklärung. In Deutschland sind sie
            Pflicht – bitte vollständig ausfüllen, bevor die Seite online geht.
          </p>
          <Field
            label="Name / Firma"
            value={settings.legal.company}
            onChange={(value) => set('legal', { ...settings.legal, company: value })}
          />
          <Field
            label="Straße und Hausnummer"
            value={settings.legal.street}
            onChange={(value) => set('legal', { ...settings.legal, street: value })}
          />
          <Field
            label="PLZ und Ort"
            value={settings.legal.city}
            onChange={(value) => set('legal', { ...settings.legal, city: value })}
          />
          <Field
            label="E-Mail (Impressum)"
            value={settings.legal.email}
            onChange={(value) => set('legal', { ...settings.legal, email: value })}
          />
          <Field
            label="Telefon"
            value={settings.legal.phone}
            onChange={(value) => set('legal', { ...settings.legal, phone: value })}
          />
          <Field
            label="USt-IdNr. (falls vorhanden)"
            value={settings.legal.vatId}
            onChange={(value) => set('legal', { ...settings.legal, vatId: value })}
          />
        </section>
      )}

      {tab === 'konto' && <AccountPanel />}
    </div>
  );
}

function Field({
  label,
  value,
  onChange,
  hint,
  type = 'text',
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  hint?: string;
  type?: string;
}) {
  return (
    <div>
      <label className="label">{label}</label>
      <input type={type} className="field" value={value} onChange={(event) => onChange(event.target.value)} />
      {hint && <p className="mt-1 text-[0.6875rem] leading-relaxed text-faint">{hint}</p>}
    </div>
  );
}

function AccountPanel() {
  const toast = useToast();
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [repeat, setRepeat] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (next !== repeat) {
      toast('Die beiden neuen Passwörter stimmen nicht überein.', 'error');
      return;
    }

    setBusy(true);
    try {
      await api.changePassword(current, next);
      setCurrent('');
      setNext('');
      setRepeat('');
      toast('Passwort geändert – andere Geräte wurden abgemeldet.');
    } catch (error) {
      toast(error instanceof Error ? error.message : 'Änderung fehlgeschlagen', 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="panel max-w-md space-y-4 p-6">
      <h2 className="text-sm font-semibold">Passwort ändern</h2>

      <div>
        <label className="label" htmlFor="current">
          Aktuelles Passwort
        </label>
        <input
          id="current"
          type="password"
          className="field"
          value={current}
          onChange={(event) => setCurrent(event.target.value)}
          autoComplete="current-password"
        />
      </div>
      <div>
        <label className="label" htmlFor="next">
          Neues Passwort
        </label>
        <input
          id="next"
          type="password"
          className="field"
          value={next}
          onChange={(event) => setNext(event.target.value)}
          autoComplete="new-password"
        />
        <p className="mt-1 text-[0.6875rem] text-faint">Mindestens 10 Zeichen.</p>
      </div>
      <div>
        <label className="label" htmlFor="repeat">
          Neues Passwort wiederholen
        </label>
        <input
          id="repeat"
          type="password"
          className="field"
          value={repeat}
          onChange={(event) => setRepeat(event.target.value)}
          autoComplete="new-password"
        />
      </div>

      <button
        type="button"
        onClick={() => void submit()}
        className="btn btn-primary w-full"
        disabled={busy || current === '' || next.length < 10}
      >
        {busy ? 'Wird geändert …' : 'Passwort ändern'}
      </button>
    </section>
  );
}
