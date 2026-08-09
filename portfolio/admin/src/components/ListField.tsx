import { useEffect, useState } from 'react';

/**
 * Eingabefeld für eine Liste, die als Text mit Kommas geschrieben wird.
 *
 * Der Grund für diese eigene Komponente: Ein Feld, das bei jedem Tastendruck
 * zerlegt und wieder zusammengesetzt wird, lässt sich nicht bedienen. Tippt
 * man ein Komma, entsteht kurz ein leerer Eintrag – der fällt beim Aufräumen
 * weg, und das Komma verschwindet noch während des Tippens. Dasselbe gilt für
 * das Leerzeichen danach.
 *
 * Deshalb behält das Feld den rohen Text, solange es den Fokus hat, und
 * ordnet ihn erst beim Verlassen wieder. Nach außen geht trotzdem bei jedem
 * Tastendruck die fertige Liste – so bleibt „Ungespeichert" ehrlich, und
 * Zwischenstände gehen beim Speichern nicht verloren.
 */
function parse(text: string): string[] {
  return text
    .split(',')
    .map((eintrag) => eintrag.trim())
    .filter(Boolean);
}

export default function ListField({
  value,
  onChange,
  placeholder,
  className = 'field text-xs',
  id,
}: {
  value: string[];
  onChange: (next: string[]) => void;
  placeholder?: string;
  className?: string;
  id?: string;
}) {
  const [text, setText] = useState(() => value.join(', '));
  const [tippt, setTippt] = useState(false);

  // Änderungen von außen übernehmen – aber nie, während jemand schreibt.
  const außen = value.join(', ');
  useEffect(() => {
    if (!tippt) {
      setText((bisher) => (bisher === außen ? bisher : außen));
    }
  }, [außen, tippt]);

  return (
    <input
      id={id}
      className={className}
      value={text}
      placeholder={placeholder}
      onFocus={() => setTippt(true)}
      onChange={(event) => {
        setText(event.target.value);
        onChange(parse(event.target.value));
      }}
      onBlur={() => {
        setTippt(false);
        // Beim Verlassen einmal sauber setzen: aus „a,,b ,“ wird „a, b“.
        setText(parse(text).join(', '));
      }}
    />
  );
}
