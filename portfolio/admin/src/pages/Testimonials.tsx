import { useEffect, useState } from 'react';
import { api, type Testimonial } from '../lib/api';
import { useToast } from '../lib/toast';

const BLANK: Partial<Testimonial> = {
  author: '',
  role: '',
  company: '',
  quote: '',
  status: 'published',
  position: 0,
};

export default function Testimonials() {
  const toast = useToast();
  const [items, setItems] = useState<Testimonial[]>([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState<Partial<Testimonial> | null>(null);

  const load = () => {
    api
      .testimonials()
      .then((data) => setItems(data.testimonials))
      .catch(() => toast('Kundenstimmen konnten nicht geladen werden.', 'error'))
      .finally(() => setLoading(false));
  };

  useEffect(load, []); // eslint-disable-line react-hooks/exhaustive-deps

  const save = async () => {
    if (!editing) return;

    try {
      await api.saveTestimonial(editing.id ?? null, editing);
      setEditing(null);
      load();
      toast('Gespeichert');
    } catch (error) {
      toast(error instanceof Error ? error.message : 'Speichern fehlgeschlagen', 'error');
    }
  };

  const remove = async (item: Testimonial) => {
    if (!window.confirm(`Zitat von „${item.author}" löschen?`)) return;

    try {
      await api.deleteTestimonial(item.id);
      setItems((current) => current.filter((entry) => entry.id !== item.id));
      toast('Gelöscht');
    } catch {
      toast('Konnte nicht gelöscht werden.', 'error');
    }
  };

  return (
    <div className="animate-in space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold tracking-tight">Kundenstimmen</h1>
          <p className="mt-1 text-sm text-muted">
            Erscheinen auf der Startseite. Ohne Einträge wird der ganze Abschnitt ausgeblendet.
          </p>
        </div>
        <button type="button" onClick={() => setEditing({ ...BLANK })} className="btn btn-primary">
          + Neue Stimme
        </button>
      </header>

      {loading ? (
        <p className="font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>
      ) : items.length === 0 ? (
        <div className="panel p-12 text-center text-sm text-faint">Noch keine Kundenstimmen hinterlegt.</div>
      ) : (
        <ul className="grid gap-3 md:grid-cols-2">
          {items.map((item) => (
            <li key={item.id} className="panel flex flex-col p-5">
              <p className="flex-1 text-sm leading-relaxed text-ink">„{item.quote}"</p>
              <div className="mt-4 flex items-end justify-between gap-3">
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium">{item.author}</p>
                  <p className="truncate text-xs text-faint">
                    {[item.role, item.company].filter(Boolean).join(' · ') || '—'}
                  </p>
                </div>
                <div className="flex shrink-0 gap-1.5">
                  {item.status === 'hidden' && (
                    <span className="rounded-full border border-line px-2 py-0.5 text-[0.625rem] text-faint">
                      versteckt
                    </span>
                  )}
                  <button type="button" onClick={() => setEditing(item)} className="btn btn-ghost px-2.5 py-1 text-xs">
                    Bearbeiten
                  </button>
                  <button type="button" onClick={() => void remove(item)} className="btn btn-danger px-2 py-1 text-xs">
                    ✕
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}

      {editing && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
          <button type="button" aria-label="Schließen" onClick={() => setEditing(null)} className="absolute inset-0 bg-black/70" />

          <div className="panel animate-in relative w-full max-w-lg space-y-4 p-6">
            <h2 className="text-sm font-semibold">{editing.id ? 'Stimme bearbeiten' : 'Neue Kundenstimme'}</h2>

            <div>
              <label className="label" htmlFor="quote">
                Zitat *
              </label>
              <textarea
                id="quote"
                className="field min-h-28 resize-y"
                value={editing.quote ?? ''}
                onChange={(event) => setEditing({ ...editing, quote: event.target.value })}
              />
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
              <div>
                <label className="label" htmlFor="author">
                  Name *
                </label>
                <input
                  id="author"
                  className="field"
                  value={editing.author ?? ''}
                  onChange={(event) => setEditing({ ...editing, author: event.target.value })}
                />
              </div>
              <div>
                <label className="label" htmlFor="trole">
                  Position
                </label>
                <input
                  id="trole"
                  className="field"
                  value={editing.role ?? ''}
                  onChange={(event) => setEditing({ ...editing, role: event.target.value })}
                />
              </div>
              <div>
                <label className="label" htmlFor="company">
                  Firma
                </label>
                <input
                  id="company"
                  className="field"
                  value={editing.company ?? ''}
                  onChange={(event) => setEditing({ ...editing, company: event.target.value })}
                />
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div>
                <label className="label" htmlFor="status">
                  Sichtbarkeit
                </label>
                <select
                  id="status"
                  className="field"
                  value={editing.status ?? 'published'}
                  onChange={(event) =>
                    setEditing({ ...editing, status: event.target.value as Testimonial['status'] })
                  }
                >
                  <option value="published">Sichtbar</option>
                  <option value="hidden">Versteckt</option>
                </select>
              </div>
              <div>
                <label className="label" htmlFor="position">
                  Reihenfolge
                </label>
                <input
                  id="position"
                  type="number"
                  className="field"
                  value={editing.position ?? 0}
                  onChange={(event) => setEditing({ ...editing, position: Number(event.target.value) })}
                />
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button type="button" onClick={() => setEditing(null)} className="btn btn-ghost">
                Abbrechen
              </button>
              <button type="button" onClick={() => void save()} className="btn btn-primary">
                Speichern
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
