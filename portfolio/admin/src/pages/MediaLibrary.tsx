import { useEffect, useRef, useState } from 'react';
import { api, formatBytes, formatDate, type MediaItem } from '../lib/api';
import { useToast } from '../lib/toast';

const FILTERS = [
  { value: 'all', label: 'Alle' },
  { value: 'image', label: 'Bilder' },
  { value: 'video', label: 'Videos' },
  { value: 'model', label: '3D-Modelle' },
] as const;

export default function MediaLibrary() {
  const toast = useToast();
  const [items, setItems] = useState<MediaItem[]>([]);
  const [filter, setFilter] = useState<string>('all');
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(0);
  const [selected, setSelected] = useState<MediaItem | null>(null);
  const [dragging, setDragging] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    setLoading(true);
    api
      .media(filter === 'all' ? undefined : filter)
      .then((data) => setItems(data.media))
      .catch(() => toast('Medien konnten nicht geladen werden.', 'error'))
      .finally(() => setLoading(false));
  }, [filter, toast]);

  const upload = async (files: FileList | null) => {
    if (!files || files.length === 0) return;

    setUploading(files.length);
    for (const file of Array.from(files)) {
      try {
        const { media } = await api.uploadMedia(file);
        setItems((current) => [media, ...current]);
      } catch (error) {
        toast(error instanceof Error ? error.message : `${file.name} fehlgeschlagen`, 'error');
      }
      setUploading((count) => count - 1);
    }
    toast('Upload abgeschlossen');
  };

  const remove = async (item: MediaItem) => {
    if (!window.confirm(`„${item.filename}" wirklich löschen? Die Datei verschwindet auch aus allen Projekten.`)) {
      return;
    }

    try {
      await api.deleteMedia(item.id);
      setItems((current) => current.filter((entry) => entry.id !== item.id));
      setSelected(null);
      toast('Datei gelöscht');
    } catch {
      toast('Datei konnte nicht gelöscht werden.', 'error');
    }
  };

  const saveAlt = async (item: MediaItem, alt: string) => {
    try {
      const { media } = await api.updateMedia(item.id, alt);
      setItems((current) => current.map((entry) => (entry.id === media.id ? media : entry)));
      setSelected(media);
      toast('Bildbeschreibung gespeichert');
    } catch {
      toast('Konnte nicht gespeichert werden.', 'error');
    }
  };

  return (
    <div className="animate-in space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold tracking-tight">Medien</h1>
          <p className="mt-1 text-sm text-muted">
            Bilder, Videos und 3D-Modelle. Von Bildern wird automatisch eine kleine Version erzeugt.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <div className="flex gap-1 rounded-lg border border-line p-1">
            {FILTERS.map((entry) => (
              <button
                key={entry.value}
                type="button"
                onClick={() => setFilter(entry.value)}
                className={`rounded-md px-3 py-1.5 text-xs transition-colors ${
                  filter === entry.value ? 'bg-panel2 text-ink' : 'text-muted hover:text-ink'
                }`}
              >
                {entry.label}
              </button>
            ))}
          </div>

          <button type="button" onClick={() => inputRef.current?.click()} className="btn btn-primary">
            + Hochladen
          </button>
        </div>
      </header>

      <input
        ref={inputRef}
        type="file"
        multiple
        hidden
        accept="image/*,video/*,.glb,.gltf"
        onChange={(event) => void upload(event.target.files)}
      />

      <div
        onDragOver={(event) => {
          event.preventDefault();
          setDragging(true);
        }}
        onDragLeave={() => setDragging(false)}
        onDrop={(event) => {
          event.preventDefault();
          setDragging(false);
          void upload(event.dataTransfer.files);
        }}
        className={`rounded-xl border border-dashed p-4 transition-colors ${
          dragging ? 'border-accent bg-accent/5' : 'border-line'
        }`}
      >
        {uploading > 0 && (
          <p className="mb-4 text-sm text-accent">Noch {uploading} Datei(en) werden hochgeladen …</p>
        )}

        {loading ? (
          <p className="py-16 text-center font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>
        ) : items.length === 0 ? (
          <p className="py-16 text-center text-sm text-faint">
            Noch nichts hochgeladen. Dateien einfach hierher ziehen.
          </p>
        ) : (
          <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            {items.map((item) => (
              <li key={item.id}>
                <button
                  type="button"
                  onClick={() => setSelected(item)}
                  className="group w-full overflow-hidden rounded-lg border border-line text-left transition-colors hover:border-accent"
                >
                  <span className="block aspect-[4/3] bg-panel2">
                    {item.kind === 'image' ? (
                      <img
                        src={item.thumbUrl ?? item.url}
                        alt={item.alt}
                        loading="lazy"
                        className="h-full w-full object-cover"
                      />
                    ) : (
                      <span className="grid h-full w-full place-items-center font-mono text-xs text-faint">
                        {item.kind === 'video' ? '▶ VIDEO' : '◈ 3D'}
                      </span>
                    )}
                  </span>
                  <span className="block px-2.5 py-2">
                    <span className="block truncate text-xs text-muted">{item.filename}</span>
                    <span className="block text-[0.625rem] text-faint">{formatBytes(item.size)}</span>
                  </span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      {selected && <DetailPanel item={selected} onClose={() => setSelected(null)} onDelete={remove} onSaveAlt={saveAlt} />}
    </div>
  );
}

function DetailPanel({
  item,
  onClose,
  onDelete,
  onSaveAlt,
}: {
  item: MediaItem;
  onClose: () => void;
  onDelete: (item: MediaItem) => Promise<void>;
  onSaveAlt: (item: MediaItem, alt: string) => Promise<void>;
}) {
  const toast = useToast();
  const [alt, setAlt] = useState(item.alt);

  useEffect(() => setAlt(item.alt), [item]);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
      <button type="button" aria-label="Schließen" onClick={onClose} className="absolute inset-0 bg-black/70" />

      <div className="panel animate-in relative grid max-h-[85vh] w-full max-w-3xl gap-0 overflow-hidden md:grid-cols-[1.3fr_1fr]">
        <div className="grid place-items-center bg-panel2 p-4">
          {item.kind === 'image' ? (
            <img src={item.url} alt={item.alt} className="max-h-[60vh] w-full object-contain" />
          ) : item.kind === 'video' ? (
            <video src={item.url} controls className="max-h-[60vh] w-full" />
          ) : (
            <p className="font-mono text-xs text-faint">3D-MODELL · {item.filename}</p>
          )}
        </div>

        <div className="flex flex-col gap-4 overflow-y-auto p-5">
          <div className="flex items-start justify-between gap-3">
            <h2 className="min-w-0 break-all text-sm font-semibold">{item.filename}</h2>
            <button type="button" onClick={onClose} className="btn btn-ghost shrink-0 px-2 py-1 text-xs">
              ✕
            </button>
          </div>

          <dl className="space-y-2 text-xs">
            <Row label="Typ" value={item.mime} />
            <Row label="Größe" value={formatBytes(item.size)} />
            {item.width && <Row label="Maße" value={`${item.width} × ${item.height} px`} />}
            <Row label="Hochgeladen" value={formatDate(item.createdAt)} />
          </dl>

          <div>
            <label className="label" htmlFor="alt">
              Bildbeschreibung
            </label>
            <textarea
              id="alt"
              className="field min-h-20 resize-y"
              value={alt}
              onChange={(event) => setAlt(event.target.value)}
              placeholder="Was ist zu sehen? Wichtig für Screenreader und Suchmaschinen."
            />
            <button
              type="button"
              onClick={() => void onSaveAlt(item, alt)}
              className="btn btn-ghost mt-2 w-full py-1.5 text-xs"
              disabled={alt === item.alt}
            >
              Beschreibung speichern
            </button>
          </div>

          <div className="mt-auto space-y-2 pt-2">
            <button
              type="button"
              onClick={() => {
                void navigator.clipboard?.writeText(location.origin + item.url);
                toast('Link kopiert');
              }}
              className="btn btn-ghost w-full py-2 text-xs"
            >
              Link kopieren
            </button>
            <button type="button" onClick={() => void onDelete(item)} className="btn btn-danger w-full py-2 text-xs">
              Datei löschen
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-3">
      <dt className="text-faint">{label}</dt>
      <dd className="min-w-0 truncate text-muted">{value}</dd>
    </div>
  );
}
