import { useEffect, useRef, useState } from 'react';
import type { MediaItem } from '../lib/api';

/**
 * Vorschaubild einer Datei in der Mediathek.
 *
 * Bilder haben ein fertiges Vorschaubild vom Server. Videos hatten bisher
 * gar keins – in der Übersicht stand nur „▶ VIDEO", man musste also raten,
 * welches Video man gerade auswählt. Ein Vorschaubild auf dem Server zu
 * erzeugen bräuchte einen Videoschnitt, den ein geteilter Webspace nicht
 * hergibt. Also lädt der Browser die ersten Sekunden, spult ein Stück vor
 * und zeigt das Bild, das dort steht.
 */

/** Anteil der Laufzeit, an dem das Standbild genommen wird. */
const STELLE = 0.3;

export default function MediaThumb({ item, className = '' }: { item: MediaItem; className?: string }) {
  const video = useRef<HTMLVideoElement | null>(null);
  const [bereit, setBereit] = useState(false);

  useEffect(() => {
    const element = video.current;
    if (!element) return;

    const spule = (): void => {
      if (!Number.isFinite(element.duration) || element.duration <= 0) return;
      element.currentTime = Math.min(element.duration * STELLE, Math.max(0, element.duration - 0.1));
    };

    element.addEventListener('loadedmetadata', spule);
    element.addEventListener('seeked', () => setBereit(true), { once: true });
    if (element.readyState >= 1) spule();

    return () => element.removeEventListener('loadedmetadata', spule);
  }, [item.id]);

  if (item.kind === 'image') {
    return <img src={item.thumbUrl ?? item.url} alt={item.alt} loading="lazy" className={`h-full w-full object-cover ${className}`} />;
  }

  if (item.kind === 'video') {
    return (
      <span className="relative block h-full w-full bg-black">
        <video
          ref={video}
          src={item.url}
          muted
          playsInline
          preload="metadata"
          className={`h-full w-full object-cover transition-opacity ${bereit ? 'opacity-100' : 'opacity-0'} ${className}`}
        />
        <span className="pointer-events-none absolute inset-0 grid place-items-center">
          <span className="grid h-9 w-9 place-items-center rounded-full border border-white/60 bg-black/45 text-[0.625rem] text-white backdrop-blur">
            ▶
          </span>
        </span>
      </span>
    );
  }

  return (
    <span className="grid h-full w-full place-items-center font-mono text-[0.625rem] text-faint">
      {item.kind === 'document' ? '▤ PDF' : '◈ 3D'}
    </span>
  );
}
