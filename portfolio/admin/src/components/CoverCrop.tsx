import { useRef, useState } from 'react';
import type { MediaItem } from '../lib/api';

/**
 * Bildausschnitt des Titelbilds bestimmen.
 *
 * Die Kachel im Raster ist formatfüllend, das Motiv also beschnitten – und
 * beschnitten wurde bisher immer zur Mitte hin. Bei einem Gesicht am Bildrand
 * oder einem Produkt im oberen Drittel war damit genau das Falsche zu sehen.
 *
 * Hier wird gezeigt, was die Kachel später zeigt: dieselbe Fläche, dasselbe
 * Format, derselbe Beschnitt. Ziehen verschiebt, der Regler vergrößert.
 */

type Props = {
  cover: MediaItem | null;
  format: 'landscape' | 'square' | 'portrait';
  focus: string;
  zoom: number;
  onChange: (focus: string, zoom: number) => void;
};

const ASPECT: Record<Props['format'], string> = {
  landscape: '4 / 3',
  square: '1 / 1',
  portrait: '3 / 4',
};

function parseFocus(value: string): [number, number] {
  const treffer = value.match(/^(\d{1,3}(?:\.\d+)?)% (\d{1,3}(?:\.\d+)?)%$/);
  if (!treffer) return [50, 50];

  return [Number(treffer[1]), Number(treffer[2])];
}

const klemme = (wert: number): number => Math.min(100, Math.max(0, wert));

export default function CoverCrop({ cover, format, focus, zoom, onChange }: Props) {
  const box = useRef<HTMLDivElement | null>(null);
  const [zieht, setZieht] = useState(false);
  const [x, y] = parseFocus(focus);

  if (!cover || cover.kind !== 'image') return null;

  const verschiebe = (dx: number, dy: number): void => {
    const rahmen = box.current?.getBoundingClientRect();
    if (!rahmen) return;

    // Nach rechts ziehen heißt: das Bild nach rechts schieben, also weiter
    // links hineinschauen. Deshalb das Minus.
    onChange(
      `${klemme(x - (dx / rahmen.width) * 100).toFixed(1)}% ${klemme(y - (dy / rahmen.height) * 100).toFixed(1)}%`,
      zoom,
    );
  };

  const onPointerDown = (event: React.PointerEvent<HTMLDivElement>): void => {
    event.currentTarget.setPointerCapture(event.pointerId);
    setZieht(true);
  };

  const onPointerMove = (event: React.PointerEvent<HTMLDivElement>): void => {
    if (!zieht) return;
    verschiebe(event.movementX, event.movementY);
  };

  const onPointerUp = (event: React.PointerEvent<HTMLDivElement>): void => {
    event.currentTarget.releasePointerCapture(event.pointerId);
    setZieht(false);
  };

  // Mit der Tastatur in Schritten von einem Prozent – ohne das wäre der
  // Ausschnitt nur mit der Maus erreichbar.
  const onKeyDown = (event: React.KeyboardEvent<HTMLDivElement>): void => {
    const schritte: Record<string, [number, number]> = {
      ArrowLeft: [1, 0],
      ArrowRight: [-1, 0],
      ArrowUp: [0, 1],
      ArrowDown: [0, -1],
    };
    const schritt = schritte[event.key];
    if (!schritt) return;

    event.preventDefault();
    onChange(`${klemme(x + schritt[0]).toFixed(1)}% ${klemme(y + schritt[1]).toFixed(1)}%`, zoom);
  };

  const unveraendert = x === 50 && y === 50 && zoom === 1;

  return (
    <div className="space-y-3">
      <div>
        <span className="label">Bildausschnitt der Kachel</span>
        <p className="text-[0.6875rem] leading-relaxed text-faint">
          Ziehen verschiebt den Ausschnitt, der Regler vergrößert ihn. Genau so erscheint das
          Bild später in der Übersicht.
        </p>
      </div>

      <div
        ref={box}
        role="application"
        tabIndex={0}
        aria-label="Bildausschnitt verschieben – mit den Pfeiltasten oder durch Ziehen"
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onKeyDown={onKeyDown}
        className={`relative overflow-hidden rounded-lg border border-line bg-elevated outline-none focus-visible:border-accent ${
          zieht ? 'cursor-grabbing' : 'cursor-grab'
        }`}
        style={{ aspectRatio: ASPECT[format] }}
      >
        <img
          src={cover.thumbUrl ?? cover.url}
          alt=""
          draggable={false}
          className="h-full w-full select-none object-cover"
          style={{ objectPosition: focus, scale: String(zoom) }}
        />

        {/* Drittelraster – hilft beim Ausrichten und zeigt, dass hier etwas
            zu bewegen ist. Nur während des Ziehens sichtbar. */}
        <div
          className={`pointer-events-none absolute inset-0 transition-opacity ${zieht ? 'opacity-100' : 'opacity-0'}`}
          style={{
            backgroundImage:
              'linear-gradient(to right, rgb(255 255 255 / 35%) 1px, transparent 1px), linear-gradient(to bottom, rgb(255 255 255 / 35%) 1px, transparent 1px)',
            backgroundSize: '33.333% 33.333%',
          }}
        />
      </div>

      <div className="flex items-center gap-3">
        <input
          type="range"
          min={1}
          max={2.5}
          step={0.05}
          value={zoom}
          onChange={(event) => onChange(focus, Number(event.target.value))}
          className="h-1 flex-1 accent-[var(--accent)]"
          aria-label="Ausschnitt vergrößern"
        />
        <span className="w-12 text-right font-mono text-[0.625rem] text-faint">
          {Math.round(zoom * 100)}%
        </span>
        <button
          type="button"
          onClick={() => onChange('50% 50%', 1)}
          disabled={unveraendert}
          className="rounded border border-line px-2 py-1 text-[0.625rem] text-muted transition-colors hover:border-line-strong hover:text-ink disabled:opacity-40"
        >
          Zurücksetzen
        </button>
      </div>
    </div>
  );
}
