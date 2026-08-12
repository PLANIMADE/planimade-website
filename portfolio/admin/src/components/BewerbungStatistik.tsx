import { useMemo, useState } from 'react';
import type { BewerbungDaten, BewerbungEintrag } from '../lib/api';

/**
 * Wie die Bewerbungen laufen – auf einen Blick.
 *
 * Absicht: Nicht möglichst viele Zahlen zeigen, sondern die eine Frage
 * beantworten, die man sich beim Öffnen stellt – bewegt sich etwas? Deshalb
 * steht oben der Weg von der Liste bis zur Zusage, und erst darunter die
 * Aufschlüsselung.
 *
 * Gerechnet wird hier, nicht auf dem Server: Die Liste liegt ohnehin schon
 * vollständig im Browser, und eine zweite Anfrage für Zahlen, die man aus
 * vorhandenen Daten abzählen kann, wäre Aufwand ohne Gegenwert.
 */

/**
 * Reihenfolge im Balken – nicht beliebig.
 *
 * Sie läuft von „vorbei“ über „läuft“ bis „geschafft“. Dass Absage ganz vorn
 * und Zusage ganz hinten steht, hat außerdem einen praktischen Grund: Rot und
 * Grün sind das Paar, das bei einer Farbfehlsichtigkeit am ehesten
 * verschwimmt. So liegen sie nie nebeneinander.
 */
const STATI_AGENTUR = [
  { id: 'Absage', farbe: 'var(--st-absage)' },
  { id: 'Offen', farbe: 'var(--st-offen)' },
  { id: 'Kontaktiert', farbe: 'var(--st-kontaktiert)' },
  { id: 'Antwort erhalten', farbe: 'var(--st-antwort)' },
  { id: 'Gespräch', farbe: 'var(--st-gespraech)' },
  { id: 'Zusage', farbe: 'var(--st-zusage)' },
] as const;

const STATI_STELLE = [
  { id: 'Absage', farbe: 'var(--st-absage)' },
  { id: 'Abgelaufen', farbe: 'var(--st-offen)' },
  { id: 'Passt nicht', farbe: 'var(--st-offen)' },
  { id: 'Offen', farbe: 'var(--st-kontaktiert)' },
  { id: 'Beworben', farbe: 'var(--st-antwort)' },
  { id: 'Gespräch', farbe: 'var(--st-gespraech)' },
] as const;

/** Angeschrieben ist alles, was den Status „Offen“ hinter sich hat. */
const ANGESCHRIEBEN = ['Kontaktiert', 'Antwort erhalten', 'Gespräch', 'Absage', 'Zusage'];
/** Eine Antwort ist jede Reaktion – auch eine Absage ist eine. */
const GEANTWORTET = ['Antwort erhalten', 'Gespräch', 'Absage', 'Zusage'];

export default function BewerbungStatistik({ daten }: { daten: BewerbungDaten }) {
  const z = useMemo(() => rechne(daten.agenturen), [daten.agenturen]);

  if (daten.agenturen.length === 0) {
    return <p className="panel p-10 text-center text-sm text-faint">Noch keine Agenturen in der Liste.</p>;
  }

  return (
    <div className="space-y-6">
      <Weg z={z} />

      {/* Zwei Spalten: links die Aufschlüsselungen, rechts die Umkreisliste,
          die deutlich länger ist. `items-start` verhindert, dass das kürzere
          Panel auf die Höhe des längeren gezogen wird. */}
      <div className="grid items-start gap-6 lg:grid-cols-[3fr_2fr]">
        <div className="space-y-6">
          <Verteilung
            titel="Wie es um die Agenturen steht"
            eintraege={daten.agenturen}
            stati={STATI_AGENTUR}
          />
          {daten.stellen.length > 0 && (
            <Verteilung
              titel="Ausgeschriebene Stellen"
              eintraege={daten.stellen}
              stati={STATI_STELLE}
            />
          )}
        </div>
        <Regionen daten={daten} />
      </div>

      <Verlauf eintraege={daten.agenturen} />
    </div>
  );
}

// ---------------------------------------------------------------- Der Weg

/**
 * Die vier Stufen von der Liste bis zur Zusage.
 *
 * Bewusst kein Trichterbild mit schrägen Kanten: Die Breite eines Trapezes
 * liest sich schlechter als die Länge eines Balkens, und schräg ist hier
 * nichts – jede Stufe ist eine Teilmenge der vorigen.
 */
function Weg({ z }: { z: Zahlen }) {
  const stufen = [
    { label: 'In der Liste', wert: z.gesamt, farbe: 'var(--st-offen)', hinweis: 'Agenturen insgesamt' },
    { label: 'Angeschrieben', wert: z.angeschrieben, farbe: 'var(--st-kontaktiert)', hinweis: 'Mail ist raus' },
    { label: 'Geantwortet', wert: z.geantwortet, farbe: 'var(--st-antwort)', hinweis: 'Reaktion gekommen' },
    { label: 'Im Gespräch', wert: z.gespraech, farbe: 'var(--st-gespraech)', hinweis: 'Termin oder Austausch' },
    { label: 'Zusage', wert: z.zusage, farbe: 'var(--st-zusage)', hinweis: 'geschafft' },
  ];
  const groesste = Math.max(...stufen.map((s) => s.wert), 1);

  return (
    <section className="panel p-6">
      <div className="flex flex-wrap items-baseline justify-between gap-4">
        <h2 className="text-sm font-semibold">Der Weg</h2>
        <p className="text-xs text-muted">
          {z.angeschrieben > 0 ? (
            <>
              <b className="text-ink">{prozent(z.geantwortet, z.angeschrieben)}</b> der angeschriebenen
              Agenturen haben reagiert
            </>
          ) : (
            'Noch nichts verschickt.'
          )}
        </p>
      </div>

      <ol className="mt-5 space-y-3">
        {stufen.map((s) => (
          <li key={s.label} className="grid grid-cols-[7.5rem_1fr] items-center gap-3 sm:grid-cols-[9rem_1fr]">
            <div className="min-w-0">
              <p className="truncate text-xs text-ink">{s.label}</p>
              <p className="truncate text-[0.6875rem] text-faint">{s.hinweis}</p>
            </div>
            <div className="flex items-center gap-3">
              <div className="h-4 flex-1 overflow-hidden rounded-sm bg-panel2">
                <div
                  className="h-full rounded-r-[4px] transition-[width] duration-500"
                  style={{ width: `${(s.wert / groesste) * 100}%`, background: s.farbe }}
                />
              </div>
              <span className="w-10 shrink-0 text-right font-mono text-sm tabular-nums text-ink">{s.wert}</span>
            </div>
          </li>
        ))}
      </ol>
    </section>
  );
}

// -------------------------------------------------------- Statusverteilung

/**
 * Ein Balken, in Abschnitte geteilt – wie sich die Liste gerade aufteilt.
 *
 * Die 2px-Lücken zwischen den Abschnitten sind Absicht: Zwei Farben, die sich
 * berühren, verschwimmen an der Kante. Ein Spalt in der Hintergrundfarbe
 * trennt sauberer als jede Umrandung, die zusätzliche Striche einführt.
 */
function Verteilung({
  titel,
  eintraege,
  stati,
}: {
  titel: string;
  eintraege: BewerbungEintrag[];
  stati: ReadonlyArray<{ id: string; farbe: string }>;
}) {
  const [hover, setHover] = useState('');

  const gesamt = eintraege.length;
  // Nur belegte Stati: Eine Legendenzeile mit „0“ erklärt nichts und drängt
  // die Zeilen auseinander, auf die es ankommt.
  const teile = stati
    .map((s) => ({ ...s, anzahl: eintraege.filter((e) => e.status === s.id).length }))
    .filter((s) => s.anzahl > 0);

  return (
    <section className="panel p-6">
      <h2 className="text-sm font-semibold">{titel}</h2>

      <div
        className="mt-5 flex h-9 gap-[2px] overflow-hidden rounded-md"
        onMouseLeave={() => setHover('')}
        role="img"
        aria-label={teile.map((t) => `${t.id}: ${t.anzahl} von ${gesamt}`).join(', ')}
      >
        {teile.map((t) => (
          <div
            key={t.id}
            className="h-full transition-opacity first:rounded-l-md last:rounded-r-md"
            style={{
              width: `${(t.anzahl / gesamt) * 100}%`,
              // Ein einzelner Treffer unter zweihundert wäre sonst ein Strich,
              // dem man die Farbe nicht mehr ansieht.
              minWidth: '4px',
              background: t.farbe,
              opacity: hover === '' || hover === t.id ? 1 : 0.35,
            }}
            onMouseEnter={() => setHover(t.id)}
            title={`${t.id}: ${t.anzahl} von ${gesamt}`}
          />
        ))}
      </div>

      {/* Die Legende ist nicht Zierde: Ohne sie hinge die Bedeutung allein an
          der Farbe, und das trägt nicht für jeden. Zwei Spalten werden
          spaltenweise gefüllt, damit die Reihenfolge der des Balkens folgt. */}
      <ul
        className="mt-5 grid gap-x-6 gap-y-2 sm:grid-flow-col sm:grid-cols-2"
        style={{ gridTemplateRows: `repeat(${Math.ceil(teile.length / 2)}, auto)` }}
      >
        {teile.map((s) => (
          <li
            key={s.id}
            className="flex items-baseline gap-2.5 text-xs transition-opacity"
            style={{ opacity: hover === '' || hover === s.id ? 1 : 0.45 }}
            onMouseEnter={() => setHover(s.id)}
            onMouseLeave={() => setHover('')}
          >
            <span
              aria-hidden
              className="size-2.5 shrink-0 translate-y-[1px] rounded-full"
              style={{ background: s.farbe }}
            />
            <span className="flex-1 truncate text-muted">{s.id}</span>
            <span className="font-mono tabular-nums text-ink">{s.anzahl}</span>
            <span className="w-11 text-right font-mono tabular-nums text-faint">
              {prozent(s.anzahl, gesamt)}
            </span>
          </li>
        ))}
      </ul>
    </section>
  );
}

// ---------------------------------------------------------------- Regionen

/** Wie weit die Liste je Umkreis abgearbeitet ist. */
function Regionen({ daten }: { daten: BewerbungDaten }) {
  const zeilen = daten.regionen
    .map((r) => {
      const drin = daten.agenturen.filter((a) => a.r === r.id);
      const ab = drin.filter((a) => ANGESCHRIEBEN.includes(a.status)).length;

      return { ...r, gesamt: drin.length, ab };
    })
    .filter((r) => r.gesamt > 0)
    .sort((a, b) => b.gesamt - a.gesamt);

  return (
    <section className="panel p-6">
      <h2 className="text-sm font-semibold">Nach Umkreis</h2>
      <p className="mt-1 text-[0.6875rem] text-faint">Angeschrieben von insgesamt</p>

      <ul className="mt-5 space-y-3">
        {zeilen.map((r) => (
          <li key={r.id}>
            <div className="flex items-baseline justify-between gap-3 text-xs">
              <span className="truncate text-muted">{r.city}</span>
              <span className="shrink-0 font-mono tabular-nums text-ink">
                {r.ab}
                <span className="text-faint">/{r.gesamt}</span>
              </span>
            </div>
            <div className="mt-1.5 h-2 overflow-hidden rounded-sm bg-panel2">
              <div
                className="h-full rounded-r-[4px] transition-[width] duration-500"
                style={{
                  width: `${(r.ab / r.gesamt) * 100}%`,
                  background: 'var(--st-kontaktiert)',
                }}
              />
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}

// ----------------------------------------------------------------- Verlauf

/**
 * Wann die Mails rausgingen.
 *
 * Nur echte Versandtage: Ein Zeitstrahl mit lauter Nullen dazwischen
 * behauptet einen Rhythmus, den es nicht gibt. Bei einem einzigen Tag lohnt
 * das Bild nicht – dann steht da ein Satz.
 */
function Verlauf({ eintraege }: { eintraege: BewerbungEintrag[] }) {
  const tage = useMemo(() => {
    const zaehler = new Map<string, number>();
    for (const e of eintraege) {
      if (e.gesendetAm === null || e.gesendetAm === '') continue;
      const tag = e.gesendetAm.slice(0, 10);
      zaehler.set(tag, (zaehler.get(tag) ?? 0) + 1);
    }

    return [...zaehler.entries()].sort((a, b) => a[0].localeCompare(b[0])).map(([tag, anzahl]) => ({ tag, anzahl }));
  }, [eintraege]);

  if (tage.length === 0) return null;

  const gesamt = tage.reduce((s, t) => s + t.anzahl, 0);

  if (tage.length === 1) {
    return (
      <section className="panel p-6">
        <h2 className="text-sm font-semibold">Versand</h2>
        <p className="mt-3 text-sm text-muted">
          Alle <b className="text-ink">{gesamt}</b> Mails gingen am{' '}
          <b className="text-ink">{datum(tage[0]!.tag)}</b> raus.
        </p>
      </section>
    );
  }

  const hoechste = Math.max(...tage.map((t) => t.anzahl));
  // Bei wenigen Tagen steht die Zahl über der Säule; bei vielen würde daraus
  // eine Zahlenwand, dann trägt sie der Hinweis beim Überfahren.
  const zahlenDran = tage.length <= 12;

  return (
    <section className="panel p-6">
      <div className="flex flex-wrap items-baseline justify-between gap-4">
        <h2 className="text-sm font-semibold">Versand je Tag</h2>
        <p className="text-xs text-muted">
          {gesamt} Mails an {tage.length} Tagen
        </p>
      </div>

      {/* Säulen bleiben schmal. Eine Fläche, die sich über die halbe Breite
          zieht, sagt nicht mehr aus als ein schmaler Balken – sie drängt sich
          nur auf. Der Rest der Zeile ist Luft. */}
      <div className="mt-6 flex items-end gap-3 overflow-x-auto border-b border-line pb-0" style={{ height: '8rem' }}>
        {tage.map((t) => (
          <div
            key={t.tag}
            className="flex h-full w-7 shrink-0 flex-col justify-end"
            title={`${datum(t.tag)}: ${t.anzahl} Mails`}
          >
            {zahlenDran && (
              <span className="mb-1 text-center font-mono text-[0.6875rem] tabular-nums text-muted">
                {t.anzahl}
              </span>
            )}
            <div
              className="w-full rounded-t-[4px] transition-[height] duration-500"
              style={{ height: `${(t.anzahl / hoechste) * 88}%`, background: 'var(--st-kontaktiert)' }}
            />
          </div>
        ))}
      </div>

      <div className="mt-2 flex gap-3 overflow-hidden text-[0.6875rem] text-faint">
        {tage.map((t) => (
          <span key={t.tag} className="w-7 shrink-0 text-center">
            {t.tag.slice(8, 10)}.{t.tag.slice(5, 7)}.
          </span>
        ))}
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ Rechnen

interface Zahlen {
  gesamt: number;
  angeschrieben: number;
  geantwortet: number;
  gespraech: number;
  zusage: number;
}

function rechne(agenturen: BewerbungEintrag[]): Zahlen {
  const mit = (stati: string[]): number => agenturen.filter((a) => stati.includes(a.status)).length;

  return {
    gesamt: agenturen.length,
    angeschrieben: mit(ANGESCHRIEBEN),
    geantwortet: mit(GEANTWORTET),
    gespraech: mit(['Gespräch', 'Zusage']),
    zusage: mit(['Zusage']),
  };
}

/**
 * Ohne Nachkommastelle – bei diesen Größenordnungen tut sie nur so genau.
 *
 * Eine Ausnahme: Was es gibt, darf nicht als „0 %“ dastehen. Die erste Zusage
 * unter zweihundert Agenturen ist der wichtigste Eintrag der ganzen Liste.
 */
function prozent(teil: number, ganzes: number): string {
  if (ganzes === 0) return '–';
  const wert = (teil / ganzes) * 100;
  if (teil > 0 && wert < 0.5) return '<1 %';

  return `${Math.round(wert)} %`;
}

function datum(iso: string): string {
  const d = new Date(iso + 'T12:00:00');

  return Number.isNaN(d.getTime())
    ? iso
    : d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' });
}
