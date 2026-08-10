import { useState } from 'react';
import MediaPicker from './MediaPicker';
import type { MediaItem } from '../lib/api';
import MediaThumb from './MediaThumb';

interface Props {
  label: string;
  hint?: string;
  value: MediaItem | null;
  kind?: 'image' | 'video' | 'model' | 'document';
  onChange: (item: MediaItem | null) => void;
}

/** Ein Formularfeld, hinter dem die Medienauswahl steckt. */
export default function MediaField({ label, hint, value, kind = 'image', onChange }: Props) {
  const [picking, setPicking] = useState(false);

  return (
    <div>
      <label className="label">{label}</label>

      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={() => setPicking(true)}
          className="h-16 w-24 shrink-0 overflow-hidden rounded-lg border border-line bg-panel2 transition-colors hover:border-accent"
        >
          {value ? (
            <MediaThumb item={value} />
          ) : (
            <span className="grid h-full w-full place-items-center text-lg text-faint">+</span>
          )}
        </button>

        <div className="min-w-0 flex-1">
          <p className="truncate text-xs text-muted">{value ? value.filename : 'Nichts ausgewählt'}</p>
          {hint && <p className="mt-0.5 text-[0.6875rem] leading-relaxed text-faint">{hint}</p>}
          {value && (
            <button
              type="button"
              onClick={() => onChange(null)}
              className="mt-1.5 text-[0.6875rem] text-bad transition-opacity hover:opacity-80"
            >
              Entfernen
            </button>
          )}
        </div>
      </div>

      {picking && (
        <MediaPicker
          kind={kind}
          title={label}
          onClose={() => setPicking(false)}
          onSelect={(item) => {
            onChange(item);
            setPicking(false);
          }}
        />
      )}
    </div>
  );
}
