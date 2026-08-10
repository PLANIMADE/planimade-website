import { useCallback, useEffect, useRef, useState } from 'react';
import { api, formatBytes, type MediaItem } from '../lib/api';
import { useToast } from '../lib/toast';
import MediaThumb from './MediaThumb';

interface Props {
  kind?: 'image' | 'video' | 'model' | 'document';
  onSelect: (item: MediaItem) => void;
  onClose: () => void;
  title?: string;
  /**
   * Mehrere Dateien auf einmal übernehmen – etwa für eine Bildstrecke.
   * Ohne diese Option schließt der Dialog beim ersten Klick.
   */
  multiple?: boolean;
}

/** Modal zum Auswählen oder Hochladen einer Datei. */
export default function MediaPicker({ kind, onSelect, onClose, title = 'Medium auswählen', multiple = false }: Props) {
  const toast = useToast();
  const [items, setItems] = useState<MediaItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [picked, setPicked] = useState<MediaItem[]>([]);
  // Video, das gerade angesehen wird – liegt über dem Dialog.
  const [vorschau, setVorschau] = useState<MediaItem | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Bei Mehrfachauswahl sammelt der Dialog erst und übergibt am Ende alles
  // auf einmal – in der Reihenfolge, in der angeklickt wurde.
  const choose = (item: MediaItem): void => {
    if (!multiple) {
      onSelect(item);
      return;
    }

    setPicked((current) =>
      current.some((entry) => entry.id === item.id)
        ? current.filter((entry) => entry.id !== item.id)
        : [...current, item],
    );
  };

  const confirm = (): void => {
    picked.forEach(onSelect);
    onClose();
  };

  const load = useCallback(() => {
    setLoading(true);
    api
      .media(kind)
      .then((data) => setItems(data.media))
      .catch(() => toast('Medien konnten nicht geladen werden.', 'error'))
      .finally(() => setLoading(false));
  }, [kind, toast]);

  useEffect(load, [load]);

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);

    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  const upload = async (files: FileList | null) => {
    if (!files || files.length === 0) return;

    setUploading(true);
    for (const file of Array.from(files)) {
      try {
        const { media } = await api.uploadMedia(file);
        setItems((current) => [media, ...current]);
        toast(`${file.name} hochgeladen`);
      } catch (error) {
        toast(error instanceof Error ? error.message : `${file.name} fehlgeschlagen`, 'error');
      }
    }
    setUploading(false);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
      <button type="button" aria-label="Schließen" onClick={onClose} className="absolute inset-0 bg-black/70" />

      <div className="panel animate-in relative flex max-h-[85vh] w-full max-w-4xl flex-col">
        <header className="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
          <h2 className="text-sm font-semibold">{title}</h2>
          <div className="flex items-center gap-2">
            <button
              type="button"
              className="btn btn-primary px-3 py-1.5 text-xs"
              onClick={() => inputRef.current?.click()}
              disabled={uploading}
            >
              {uploading ? 'Lädt hoch …' : '+ Hochladen'}
            </button>
            {multiple && (
              <button
                type="button"
                onClick={confirm}
                disabled={picked.length === 0}
                className="btn btn-primary px-3 py-1.5 text-xs"
              >
                {picked.length === 0 ? 'Nichts gewählt' : `${picked.length} übernehmen`}
              </button>
            )}
            <button type="button" onClick={onClose} className="btn btn-ghost px-2.5 py-1.5 text-xs">
              ✕
            </button>
          </div>
        </header>

        <input
          ref={inputRef}
          type="file"
          multiple
          hidden
          accept={
            kind === 'image'
              ? 'image/*'
              : kind === 'video'
                ? 'video/*'
                : kind === 'model'
                  ? '.glb,.gltf'
                  : kind === 'document'
                    ? '.pdf'
                    : 'image/*,video/*,.glb,.gltf,.pdf'
          }
          onChange={(event) => void upload(event.target.files)}
        />

        <div
          className="flex-1 overflow-y-auto p-5"
          onDragOver={(event) => event.preventDefault()}
          onDrop={(event) => {
            event.preventDefault();
            void upload(event.dataTransfer.files);
          }}
        >
          {loading ? (
            <p className="py-16 text-center font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>
          ) : items.length === 0 ? (
            <p className="py-16 text-center text-sm text-faint">
              Noch keine Dateien. Ziehe Dateien hierher oder nutze „Hochladen".
            </p>
          ) : (
            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
              {items.map((item) => (
                <li key={item.id}>
                  <button
                    type="button"
                    onClick={() => choose(item)}
                    aria-pressed={picked.some((entry) => entry.id === item.id)}
                    className={`group relative w-full overflow-hidden rounded-lg border text-left transition-colors ${
                      picked.some((entry) => entry.id === item.id)
                        ? 'border-accent ring-1 ring-accent'
                        : 'border-line hover:border-accent'
                    }`}
                  >
                    {multiple && picked.some((entry) => entry.id === item.id) && (
                      <span className="absolute right-2 top-2 z-10 grid h-6 w-6 place-items-center rounded-full bg-accent text-[0.625rem] font-semibold text-white">
                        {picked.findIndex((entry) => entry.id === item.id) + 1}
                      </span>
                    )}
                    <span className="block aspect-[4/3] bg-panel2">
                      <MediaThumb item={item} />
                    </span>

                    {/* Ansehen, bevor man auswählt. Der Knopf sitzt im Knopf –
                        deshalb hält er das Klickereignis hier auf, sonst wäre
                        die Datei mit dem Blick auch gleich ausgewählt. */}
                    {item.kind === 'video' && (
                      <span
                        role="button"
                        tabIndex={0}
                        onClick={(event) => {
                          event.stopPropagation();
                          setVorschau(item);
                        }}
                        onKeyDown={(event) => {
                          if (event.key !== 'Enter' && event.key !== ' ') return;
                          event.preventDefault();
                          event.stopPropagation();
                          setVorschau(item);
                        }}
                        className="absolute bottom-11 right-2 rounded border border-line bg-panel/90 px-1.5 py-0.5 text-[0.5625rem] text-muted backdrop-blur transition-colors hover:border-accent hover:text-ink"
                      >
                        Ansehen
                      </span>
                    )}
                    <span className="block px-2 py-1.5">
                      <span className="block truncate text-[0.6875rem] text-muted">{item.filename}</span>
                      <span className="block text-[0.625rem] text-faint">{formatBytes(item.size)}</span>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      {vorschau && (
        <div className="absolute inset-0 z-10 grid place-items-center p-6" role="dialog" aria-modal="true">
          <button
            type="button"
            aria-label="Vorschau schließen"
            onClick={() => setVorschau(null)}
            className="absolute inset-0 bg-black/80"
          />
          <div className="panel relative w-full max-w-3xl overflow-hidden">
            {/* eslint-disable-next-line jsx-a11y/media-has-caption */}
            <video src={vorschau.url} controls autoPlay className="max-h-[70vh] w-full bg-black" />
            <div className="flex items-center justify-between gap-3 p-4">
              <span className="min-w-0 truncate text-xs text-muted">{vorschau.filename}</span>
              <span className="flex shrink-0 gap-2">
                <button
                  type="button"
                  onClick={() => {
                    choose(vorschau);
                    setVorschau(null);
                  }}
                  className="btn btn-primary px-3 py-1.5 text-xs"
                >
                  Dieses nehmen
                </button>
                <button type="button" onClick={() => setVorschau(null)} className="btn btn-ghost px-3 py-1.5 text-xs">
                  Schließen
                </button>
              </span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
