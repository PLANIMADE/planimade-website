import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { api, type GalleryItem, type MediaItem, type Project } from '../lib/api';
import { useToast } from '../lib/toast';
import MediaField from '../components/MediaField';
import MediaPicker from '../components/MediaPicker';

type Draft = {
  title: string;
  slug: string;
  subtitle: string;
  summary: string;
  body: string;
  category: string;
  client: string;
  role: string;
  year: string;
  accent: string;
  status: 'draft' | 'published';
  featured: boolean;
  tools: string;
  tags: string;
  links: Array<{ label: string; url: string }>;
  metrics: Array<{ label: string; value: string }>;
  palette: Array<{ name: string; hex: string }>;
  display: 'cover' | 'contain';
  cardFormat: 'landscape' | 'square' | 'portrait';
  cover: MediaItem | null;
  preview: MediaItem | null;
  model: MediaItem | null;
  before: MediaItem | null;
  after: MediaItem | null;
  gallery: GalleryItem[];
};

const EMPTY: Draft = {
  title: '',
  slug: '',
  subtitle: '',
  summary: '',
  body: '',
  category: '',
  client: '',
  role: '',
  year: String(new Date().getFullYear()),
  accent: '#a855f7',
  status: 'draft',
  featured: false,
  tools: '',
  tags: '',
  links: [],
  metrics: [],
  palette: [],
  display: 'cover',
  cardFormat: 'landscape',
  cover: null,
  preview: null,
  model: null,
  before: null,
  after: null,
  gallery: [],
};

const ACCENTS = ['#a855f7', '#3b82f6', '#22d3ee', '#34d399', '#f97316', '#f43f5e', '#eab308'];

function toDraft(project: Project): Draft {
  return {
    title: project.title,
    slug: project.slug,
    subtitle: project.subtitle,
    summary: project.summary,
    body: project.body,
    category: project.category,
    client: project.client,
    role: project.role,
    year: project.year === null ? '' : String(project.year),
    accent: project.accent,
    status: project.status,
    featured: project.featured,
    tools: project.tools.join(', '),
    tags: project.tags.join(', '),
    links: project.links,
    metrics: project.metrics,
    palette: project.palette,
    display: project.display,
    cardFormat: project.cardFormat,
    cover: project.cover,
    preview: project.preview,
    model: project.model,
    before: project.before,
    after: project.after,
    gallery: project.gallery,
  };
}

const splitList = (value: string): string[] =>
  value
    .split(',')
    .map((entry) => entry.trim())
    .filter(Boolean);

export default function ProjectEditor() {
  const { id } = useParams();
  const navigate = useNavigate();
  const toast = useToast();

  const isNew = id === undefined;
  const [draft, setDraft] = useState<Draft>(EMPTY);
  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [addingGallery, setAddingGallery] = useState(false);

  useEffect(() => {
    if (isNew) return;

    api
      .projects()
      .then((data) => {
        const project = data.projects.find((entry) => entry.id === Number(id));
        if (!project) {
          toast('Projekt nicht gefunden.', 'error');
          navigate('/projekte');
          return;
        }
        setDraft(toDraft(project));
      })
      .catch(() => toast('Projekt konnte nicht geladen werden.', 'error'))
      .finally(() => setLoading(false));
  }, [id, isNew, navigate, toast]);

  // Vor versehentlichem Verlassen mit ungespeicherten Änderungen warnen.
  useEffect(() => {
    if (!dirty) return;

    const handler = (event: BeforeUnloadEvent) => event.preventDefault();
    window.addEventListener('beforeunload', handler);

    return () => window.removeEventListener('beforeunload', handler);
  }, [dirty]);

  const set = <K extends keyof Draft>(key: K, value: Draft[K]): void => {
    setDraft((current) => ({ ...current, [key]: value }));
    setDirty(true);
  };

  const save = async (status?: 'draft' | 'published') => {
    if (draft.title.trim() === '') {
      toast('Bitte einen Titel eintragen.', 'error');
      return;
    }

    setSaving(true);
    const payload = {
      title: draft.title,
      slug: draft.slug,
      subtitle: draft.subtitle,
      summary: draft.summary,
      body: draft.body,
      category: draft.category,
      client: draft.client,
      role: draft.role,
      year: draft.year === '' ? null : Number(draft.year),
      accent: draft.accent,
      status: status ?? draft.status,
      featured: draft.featured,
      tools: splitList(draft.tools),
      tags: splitList(draft.tags),
      links: draft.links.filter((link) => link.label !== '' && link.url !== ''),
      metrics: draft.metrics.filter((metric) => metric.label !== ''),
      palette: draft.palette.filter((color) => color.hex.trim() !== ''),
      display: draft.display,
      cardFormat: draft.cardFormat,
      coverId: draft.cover?.id ?? null,
      previewId: draft.preview?.id ?? null,
      modelId: draft.model?.id ?? null,
      beforeId: draft.before?.id ?? null,
      afterId: draft.after?.id ?? null,
      gallery: draft.gallery.map((item) => ({
        mediaId: item.media.id,
        caption: item.caption,
        layout: item.layout,
      })),
    };

    try {
      const result = isNew ? await api.createProject(payload) : await api.updateProject(Number(id), payload);
      setDraft(toDraft(result.project));
      setDirty(false);
      toast(status === 'published' ? 'Projekt ist live' : 'Gespeichert');
      if (isNew) navigate(`/projekte/${result.project.id}`, { replace: true });
    } catch (error) {
      toast(error instanceof Error ? error.message : 'Speichern fehlgeschlagen', 'error');
    } finally {
      setSaving(false);
    }
  };

  const moveGallery = (index: number, direction: -1 | 1): void => {
    const next = [...draft.gallery];
    const target = index + direction;
    if (target < 0 || target >= next.length) return;

    [next[index], next[target]] = [next[target]!, next[index]!];
    set('gallery', next);
  };

  if (loading) return <p className="font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>;

  return (
    <div className="animate-in space-y-6 pb-24">
      <header className="flex flex-wrap items-center justify-between gap-4">
        <div className="min-w-0">
          <button type="button" onClick={() => navigate('/projekte')} className="text-xs text-faint hover:text-ink">
            ← Projekte
          </button>
          <h1 className="mt-1 truncate text-xl font-semibold tracking-tight">
            {isNew ? 'Neues Projekt' : draft.title || 'Ohne Titel'}
          </h1>
        </div>

        {!isNew && draft.status === 'published' && (
          <a
            href={`/work/${draft.slug}`}
            target="_blank"
            rel="noopener"
            className="btn btn-ghost px-3 py-1.5 text-xs"
          >
            Auf der Website ansehen ↗
          </a>
        )}
      </header>

      <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
        {/* ------------------------------------------------------ Hauptspalte */}
        <div className="space-y-6">
          <section className="panel space-y-4 p-6">
            <h2 className="text-sm font-semibold">Grunddaten</h2>

            <div>
              <label className="label" htmlFor="title">
                Titel *
              </label>
              <input
                id="title"
                className="field"
                value={draft.title}
                onChange={(event) => set('title', event.target.value)}
                placeholder="z. B. Orbit — Produktfilm"
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="label" htmlFor="slug">
                  URL-Kürzel
                </label>
                <input
                  id="slug"
                  className="field"
                  value={draft.slug}
                  onChange={(event) => set('slug', event.target.value)}
                  placeholder="wird automatisch erzeugt"
                />
                <p className="mt-1 text-[0.6875rem] text-faint">/work/{draft.slug || '…'}</p>
              </div>
              <div>
                <label className="label" htmlFor="category">
                  Kategorie
                </label>
                <input
                  id="category"
                  className="field"
                  value={draft.category}
                  onChange={(event) => set('category', event.target.value)}
                  placeholder="z. B. 3D & Rendering"
                  list="categories"
                />
                <datalist id="categories">
                  <option value="3D & Rendering" />
                  <option value="Realtime & Unreal" />
                  <option value="Motion & Schnitt" />
                  <option value="Web & Code" />
                </datalist>
              </div>
            </div>

            <div>
              <label className="label" htmlFor="subtitle">
                Untertitel
              </label>
              <input
                id="subtitle"
                className="field"
                value={draft.subtitle}
                onChange={(event) => set('subtitle', event.target.value)}
                placeholder="Ein Satz, der das Projekt einordnet"
              />
            </div>

            <div>
              <label className="label" htmlFor="summary">
                Kurzbeschreibung
              </label>
              <textarea
                id="summary"
                className="field min-h-24 resize-y"
                value={draft.summary}
                onChange={(event) => set('summary', event.target.value)}
                placeholder="Steht groß über der Case-Study und dient als Vorschautext bei geteilten Links."
              />
            </div>
          </section>

          <section className="panel space-y-4 p-6">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold">Projektbeschreibung</h2>
              <span className="font-mono text-[0.625rem] text-faint">MARKDOWN</span>
            </div>
            <textarea
              className="field min-h-72 resize-y font-mono text-[0.8125rem] leading-relaxed"
              value={draft.body}
              onChange={(event) => set('body', event.target.value)}
              placeholder={'## Ausgangslage\nWorum ging es?\n\n## Umsetzung\nWas hast du gemacht?\n\n## Ergebnis\nWas kam dabei heraus?'}
            />
            <p className="text-[0.6875rem] leading-relaxed text-faint">
              <code>## Überschrift</code> · <code>**fett**</code> · <code>- Aufzählung</code> ·{' '}
              <code>[Text](https://…)</code>
            </p>
          </section>

          <section className="panel space-y-5 p-6">
            <h2 className="text-sm font-semibold">Medien</h2>

            <div className="grid gap-5 sm:grid-cols-2">
              <MediaField
                label="Titelbild"
                hint="Wird im Raster und ganz oben auf der Projektseite gezeigt."
                value={draft.cover}
                onChange={(item) => set('cover', item)}
              />
              <MediaField
                label="Hover-Video"
                hint="Kurzer, stummer Clip, der im Raster beim Überfahren läuft."
                kind="video"
                value={draft.preview}
                onChange={(item) => set('preview', item)}
              />
              <MediaField
                label="3D-Modell (GLB)"
                hint="Optional: direkt im Browser drehbar."
                kind="model"
                value={draft.model}
                onChange={(item) => set('model', item)}
              />
              <div className="grid grid-cols-2 gap-3">
                <MediaField label="Vorher" value={draft.before} onChange={(item) => set('before', item)} />
                <MediaField label="Nachher" value={draft.after} onChange={(item) => set('after', item)} />
              </div>
            </div>
          </section>

          <section className="panel space-y-4 p-6">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold">Bildstrecke</h2>
              <button type="button" onClick={() => setAddingGallery(true)} className="btn btn-ghost px-3 py-1.5 text-xs">
                + Hinzufügen
              </button>
            </div>

            {draft.gallery.length === 0 ? (
              <p className="text-sm text-faint">Noch keine Bilder oder Videos in der Strecke.</p>
            ) : (
              <ul className="space-y-2">
                {draft.gallery.map((item, index) => (
                  <li key={`${item.media.id}-${index}`} className="flex items-center gap-3 rounded-lg border border-line p-2">
                    <div className="h-12 w-16 shrink-0 overflow-hidden rounded border border-line bg-panel2">
                      {item.media.kind === 'image' ? (
                        <img
                          src={item.media.thumbUrl ?? item.media.url}
                          alt=""
                          className="h-full w-full object-cover"
                        />
                      ) : (
                        <span className="grid h-full w-full place-items-center text-[0.5rem] text-faint">VIDEO</span>
                      )}
                    </div>

                    <input
                      className="field flex-1 py-1.5 text-xs"
                      value={item.caption}
                      placeholder="Bildunterschrift (optional)"
                      onChange={(event) => {
                        const next = [...draft.gallery];
                        next[index] = { ...item, caption: event.target.value };
                        set('gallery', next);
                      }}
                    />

                    <select
                      className="field w-24 shrink-0 py-1.5 text-xs"
                      value={item.layout}
                      onChange={(event) => {
                        const next = [...draft.gallery];
                        next[index] = { ...item, layout: event.target.value as GalleryItem['layout'] };
                        set('gallery', next);
                      }}
                    >
                      <option value="full">Breit</option>
                      <option value="half">Halb</option>
                    </select>

                    <div className="flex shrink-0 gap-1">
                      <button
                        type="button"
                        onClick={() => moveGallery(index, -1)}
                        className="btn btn-ghost px-2 py-1 text-xs"
                        aria-label="Nach oben"
                      >
                        ↑
                      </button>
                      <button
                        type="button"
                        onClick={() => moveGallery(index, 1)}
                        className="btn btn-ghost px-2 py-1 text-xs"
                        aria-label="Nach unten"
                      >
                        ↓
                      </button>
                      <button
                        type="button"
                        onClick={() => set('gallery', draft.gallery.filter((_, position) => position !== index))}
                        className="btn btn-danger px-2 py-1 text-xs"
                        aria-label="Entfernen"
                      >
                        ✕
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <RepeatableSection
            title="Kennzahlen"
            hint="Kurze Fakten, die auf der Projektseite hervorgehoben werden – etwa Renderzeit · 38 h."
            rows={draft.metrics}
            fields={['label', 'value'] as const}
            placeholders={['Bezeichnung', 'Wert'] as const}
            onChange={(rows) => set('metrics', rows as Draft['metrics'])}
          />

          <RepeatableSection
            title="Farbpalette"
            hint="Für Branding- und Grafikarbeiten. Erscheint als anklickbare Farbfelder auf der Projektseite – etwa „Signalrot“ und „#E15028“."
            rows={draft.palette}
            fields={['name', 'hex'] as const}
            placeholders={['Bezeichnung', '#E15028'] as const}
            onChange={(rows) => set('palette', rows as Draft['palette'])}
          />

          <RepeatableSection
            title="Links"
            hint="Weiterführende Links, z. B. zur Live-Seite oder zum ArtStation-Beitrag."
            rows={draft.links}
            fields={['label', 'url'] as const}
            placeholders={['Beschriftung', 'https://…'] as const}
            onChange={(rows) => set('links', rows as Draft['links'])}
          />
        </div>

        {/* ------------------------------------------------------ Seitenleiste */}
        <aside className="space-y-6 lg:sticky lg:top-8 lg:self-start">
          <section className="panel space-y-4 p-5">
            <h2 className="text-sm font-semibold">Veröffentlichung</h2>

            <div className="flex gap-2">
              {(['draft', 'published'] as const).map((status) => (
                <button
                  key={status}
                  type="button"
                  onClick={() => set('status', status)}
                  className={`flex-1 rounded-lg border px-3 py-2 text-xs transition-colors ${
                    draft.status === status ? 'border-accent bg-accent/10 text-ink' : 'border-line text-muted'
                  }`}
                >
                  {status === 'draft' ? 'Entwurf' : 'Live'}
                </button>
              ))}
            </div>

            <label className="flex cursor-pointer items-start gap-2.5 text-xs text-muted">
              <input
                type="checkbox"
                checked={draft.featured}
                onChange={(event) => set('featured', event.target.checked)}
                className="mt-0.5 accent-[var(--accent)]"
              />
              <span>
                Als Highlight markieren
                <span className="mt-0.5 block text-[0.6875rem] text-faint">Bekommt ein Abzeichen im Raster.</span>
              </span>
            </label>

            <div className="flex gap-2 pt-1">
              <button type="button" onClick={() => void save()} className="btn btn-ghost flex-1" disabled={saving}>
                {saving ? 'Speichert …' : 'Speichern'}
              </button>
              <button
                type="button"
                onClick={() => void save('published')}
                className="btn btn-primary flex-1"
                disabled={saving}
              >
                Live schalten
              </button>
            </div>

            {dirty && <p className="text-[0.6875rem] text-warn">Ungespeicherte Änderungen</p>}
          </section>

          <section className="panel space-y-4 p-5">
            <h2 className="text-sm font-semibold">Details</h2>

            <div>
              <label className="label" htmlFor="client">
                Kunde
              </label>
              <input
                id="client"
                className="field"
                value={draft.client}
                onChange={(event) => set('client', event.target.value)}
              />
            </div>

            <div>
              <label className="label" htmlFor="role">
                Deine Rolle
              </label>
              <input
                id="role"
                className="field"
                value={draft.role}
                onChange={(event) => set('role', event.target.value)}
                placeholder="Konzept, Modelling, Schnitt …"
              />
            </div>

            <div>
              <label className="label" htmlFor="year">
                Jahr
              </label>
              <input
                id="year"
                type="number"
                className="field"
                value={draft.year}
                onChange={(event) => set('year', event.target.value)}
              />
            </div>

            <div>
              <label className="label" htmlFor="tools">
                Werkzeuge
              </label>
              <input
                id="tools"
                className="field"
                value={draft.tools}
                onChange={(event) => set('tools', event.target.value)}
                placeholder="Blender, Cycles, Resolve"
              />
              <p className="mt-1 text-[0.6875rem] text-faint">Mit Komma trennen.</p>
            </div>

            <div>
              <label className="label" htmlFor="tags">
                Schlagwörter
              </label>
              <input
                id="tags"
                className="field"
                value={draft.tags}
                onChange={(event) => set('tags', event.target.value)}
                placeholder="Produktfilm, 3D"
              />
            </div>
          </section>

          <section className="panel space-y-4 p-5">
            <h2 className="text-sm font-semibold">Darstellung</h2>
            <p className="text-[0.6875rem] leading-relaxed text-faint">
              Wichtig für Print- und Grafikarbeiten: Ein Plakat im Hochformat soll nicht auf
              Breitbild beschnitten werden.
            </p>

            <div className="space-y-2">
              {(
                [
                  ['cover', 'Formatfüllend', 'Bild füllt die Fläche, Ränder werden beschnitten'],
                  ['contain', 'Vollständig zeigen', 'Ganzes Motiv auf ruhiger Fläche, wie ein Blatt'],
                ] as const
              ).map(([value, label, hint]) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => set('display', value)}
                  className={`w-full rounded-lg border p-3 text-left transition-colors ${
                    draft.display === value ? 'border-accent bg-accent/10' : 'border-line hover:border-line-strong'
                  }`}
                >
                  <span className="block text-xs">{label}</span>
                  <span className="mt-0.5 block text-[0.625rem] leading-relaxed text-faint">{hint}</span>
                </button>
              ))}
            </div>

            <div>
              <label className="label">Kachelformat im Raster</label>
              <div className="grid grid-cols-3 gap-2">
                {(
                  [
                    ['landscape', 'Quer'],
                    ['square', 'Quadrat'],
                    ['portrait', 'Hoch'],
                  ] as const
                ).map(([value, label]) => (
                  <button
                    key={value}
                    type="button"
                    onClick={() => set('cardFormat', value)}
                    className={`rounded-lg border px-2 py-2 text-xs transition-colors ${
                      draft.cardFormat === value ? 'border-accent bg-accent/10 text-ink' : 'border-line text-muted'
                    }`}
                  >
                    {label}
                  </button>
                ))}
              </div>
            </div>
          </section>

          <section className="panel space-y-3 p-5">
            <h2 className="text-sm font-semibold">Akzentfarbe</h2>
            <p className="text-[0.6875rem] leading-relaxed text-faint">
              Färbt Leuchteffekte und Rahmen dieses Projekts.
            </p>

            <div className="flex flex-wrap gap-2">
              {ACCENTS.map((color) => (
                <button
                  key={color}
                  type="button"
                  onClick={() => set('accent', color)}
                  aria-label={color}
                  className={`h-7 w-7 rounded-full border-2 transition-transform hover:scale-110 ${
                    draft.accent === color ? 'border-ink' : 'border-transparent'
                  }`}
                  style={{ background: color }}
                />
              ))}
              <input
                type="color"
                value={draft.accent}
                onChange={(event) => set('accent', event.target.value)}
                className="h-7 w-7 cursor-pointer rounded-full border border-line bg-transparent"
                aria-label="Eigene Farbe"
              />
            </div>
          </section>
        </aside>
      </div>

      {addingGallery && (
        <MediaPicker
          title="Zur Bildstrecke hinzufügen"
          onClose={() => setAddingGallery(false)}
          onSelect={(item) => {
            set('gallery', [...draft.gallery, { media: item, caption: '', layout: 'full' }]);
            setAddingGallery(false);
          }}
        />
      )}
    </div>
  );
}

/** Wiederholbare Zeilen mit zwei Feldern – für Kennzahlen und Links. */
function RepeatableSection({
  title,
  hint,
  rows,
  fields,
  placeholders,
  onChange,
}: {
  title: string;
  hint: string;
  rows: Array<Record<string, string>>;
  fields: [string, string];
  placeholders: [string, string];
  onChange: (rows: Array<Record<string, string>>) => void;
}) {
  return (
    <section className="panel space-y-4 p-6">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold">{title}</h2>
        <button
          type="button"
          onClick={() => onChange([...rows, { [fields[0]]: '', [fields[1]]: '' }])}
          className="btn btn-ghost px-3 py-1.5 text-xs"
        >
          + Zeile
        </button>
      </div>

      <p className="text-[0.6875rem] leading-relaxed text-faint">{hint}</p>

      {rows.map((row, index) => (
        <div key={index} className="flex gap-2">
          {fields.map((field, fieldIndex) => (
            <input
              key={field}
              className="field flex-1 py-2 text-xs"
              placeholder={placeholders[fieldIndex]}
              value={row[field] ?? ''}
              onChange={(event) => {
                const next = [...rows];
                next[index] = { ...row, [field]: event.target.value };
                onChange(next);
              }}
            />
          ))}
          <button
            type="button"
            onClick={() => onChange(rows.filter((_, position) => position !== index))}
            className="btn btn-danger px-2.5 py-1.5 text-xs"
            aria-label="Zeile entfernen"
          >
            ✕
          </button>
        </div>
      ))}
    </section>
  );
}
