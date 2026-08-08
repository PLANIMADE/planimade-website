import { useCallback, useEffect, useRef, useState } from 'react';
import { api, formatBytes, type MediaItem } from '../lib/api';
import { useToast } from '../lib/toast';

interface Props {
  kind?: 'image' | 'video' | 'model' | 'document';
  onSelect: (item: MediaItem) => void;
  onClose: () => void;
  title?: string;
}

/** Modal zum Auswählen oder Hochladen einer Datei. */
export default function MediaPicker({ kind, onSelect, onClose, title = 'Medium auswählen' }: Props) {
  const toast = useToast();
  const [items, setItems] = useState<MediaItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

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
                    onClick={() => onSelect(item)}
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
                        <span className="grid h-full w-full place-items-center font-mono text-[0.625rem] text-faint">
                          {item.kind === 'video' ? '▶ VIDEO' : item.kind === 'document' ? '▤ PDF' : '◈ 3D'}
                        </span>
                      )}
                    </span>
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
    </div>
  );
}
