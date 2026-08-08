import { useEffect, useState } from 'react';
import { api, type Settings } from '../lib/api';
import { useToast } from '../lib/toast';

const TABS = [
  { id: 'profil', label: 'Profil' },
  { id: 'inhalte', label: 'Fähigkeiten & Ablauf' },
  { id: 'seo', label: 'SEO' },
  { id: 'rechtliches', label: 'Rechtliches' },
  { id: 'konto', label: 'Konto' },
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
