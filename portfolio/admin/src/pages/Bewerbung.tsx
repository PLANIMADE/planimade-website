import { useEffect, useMemo, useRef, useState } from 'react';
import {
  api,
  formatBytes,
  type BewerbungDaten,
  type BewerbungEintrag,
  type BewerbungVersand,
} from '../lib/api';
import { useToast } from '../lib/toast';

/**
 * Bewerbungs-Radar.
 *
 * War vorher eine einzelne HTML-Datei mit eigenem Aussehen. Hier läuft es in
 * der Optik des Dashboards: Es sitzt hinter derselben Navigation, im selben
 * Rahmen und hinter demselben Login – ein zweites Farbschema mittendrin
 * sähe aus wie ein Versehen. Struktur, Reiter und Regionen-Leiste sind
 * geblieben, ebenso die Kürzel der Datenfelder.
 *
 * Alles wird auf dem Server gespeichert. Auf dem Handy steht damit derselbe
 * Stand wie auf dem Rechner – vorher lag er im jeweiligen Browser.
 */

/**
 * So viele Mails gehen in einer Anfrage raus, dann eine Pause.
 *
 * Klein genug, dass keine Anfrage in ein Zeitlimit läuft, und langsam
 * genug, dass es nicht nach Massenversand aussieht.
 */
const PORTION = 5;
const PAUSE = 4000;

const REITER = [
  { id: 'agenturen', label: 'Agenturen' },
  { id: 'stellen', label: 'Stellen & Suchlinks' },
  { id: 'anschreiben', label: 'Anschreiben' },
] as const;

/** Farbe je Status – dieselbe Ampel wie im Original. */
const STATUS_FARBE: Record<string, string> = {
  Offen: 'text-muted',
  Kontaktiert: 'text-warn',
  Beworben: 'text-warn',
  'Antwort erhalten': 'text-accent',
  Gespräch: 'text-accent',
  Zusage: 'text-ok',
  Absage: 'text-bad',
  Abgelaufen: 'text-faint',
  'Passt nicht': 'text-faint',
};

export default function Bewerbung() {
  const toast = useToast();
  const [daten, setDaten] = useState<BewerbungDaten | null>(null);
  const [reiter, setReiter] = useState<string>('agenturen');
  const [fehler, setFehler] = useState('');

  useEffect(() => {
    api
      .bewerbung()
      .then(setDaten)
      .catch((e) => setFehler(e instanceof Error ? e.message : 'Konnte nicht geladen werden.'));
  }, []);

  if (fehler !== '') return <p className="panel p-6 text-sm text-bad">{fehler}</p>;
  if (!daten) return <p className="font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>;

  /** Ein geänderter Eintrag ersetzt sich selbst – ohne alles neu zu laden. */
  const ersetze = (eintrag: BewerbungEintrag): void => {
    setDaten((alt) =>
      alt === null
        ? alt
        : {
            ...alt,
            agenturen: alt.agenturen.map((e) => (e.id === eintrag.id ? eintrag : e)),
            stellen: alt.stellen.map((e) => (e.id === eintrag.id ? eintrag : e)),
          },
    );
  };

  return (
    <div className="animate-in space-y-6 pb-20">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold tracking-tight">Bewerbungs-Radar</h1>
          <p className="mt-1 text-sm text-muted">
            {daten.agenturen.length} Agenturen · {daten.stellen.length} Stellen · nur für dich sichtbar.
          </p>
        </div>
        <Werkzeuge daten={daten} setDaten={setDaten} />
      </header>

      <div className="flex flex-wrap gap-1 border-b border-line">
        {REITER.map((eintrag) => (
          <button
            key={eintrag.id}
            type="button"
            onClick={() => setReiter(eintrag.id)}
            className={`-mb-px border-b-2 px-4 py-2.5 text-sm transition-colors ${
              reiter === eintrag.id ? 'border-accent text-ink' : 'border-transparent text-muted hover:text-ink'
            }`}
          >
            {eintrag.label}
          </button>
        ))}
      </div>

      {reiter === 'agenturen' && <Agenturen daten={daten} setDaten={setDaten} ersetze={ersetze} />}
      {reiter === 'stellen' && <Stellen daten={daten} ersetze={ersetze} />}
      {reiter === 'anschreiben' && <Anschreiben daten={daten} setDaten={setDaten} />}
    </div>
  );

  function Werkzeuge({
    daten,
    setDaten,
  }: {
    daten: BewerbungDaten;
    setDaten: (d: BewerbungDaten) => void;
  }) {
    const dateiWahl = useRef<HTMLInputElement>(null);

    const sicherungLaden = async (datei: File): Promise<void> => {
      try {
        const inhalt = JSON.parse(await datei.text());
        const ergebnis = await api.bewerbungImport(inhalt);
        setDaten(await api.bewerbung());
        toast(
          `${ergebnis['übernommen']} übernommen` +
            (ergebnis.unbekannt > 0 ? `, ${ergebnis.unbekannt} ohne passenden Eintrag` : ''),
        );
      } catch {
        toast('Das war keine gültige Sicherung.', 'error');
      }
    };

    return (
      <div className="flex flex-wrap items-center gap-2">
        <input
          ref={dateiWahl}
          type="file"
          accept="application/json"
          className="hidden"
          onChange={(event) => {
            const datei = event.target.files?.[0];
            if (datei) void sicherungLaden(datei);
            event.target.value = '';
          }}
        />
        <button type="button" className="btn btn-ghost text-xs" onClick={() => dateiWahl.current?.click()}>
          Sicherung einlesen
        </button>
        <button type="button" className="btn btn-ghost text-xs" onClick={() => sicherungSpeichern(daten)}>
          Sicherung speichern
        </button>
        <button
          type="button"
          className="btn btn-ghost text-xs"
          onClick={async () => {
            const { neu } = await api.bewerbungNachschub();
            setDaten(await api.bewerbung());
            toast(neu > 0 ? `${neu} neue Einträge aus der Datei` : 'Nichts Neues in der Datei');
          }}
        >
          Nachschub aus Datei
        </button>
      </div>
    );
  }
}

/* ------------------------------------------------------------------ Agenturen */

function Agenturen({
  daten,
  setDaten,
  ersetze,
}: {
  daten: BewerbungDaten;
  setDaten: (d: BewerbungDaten) => void;
  ersetze: (e: BewerbungEintrag) => void;
}) {
  const toast = useToast();
  const [region, setRegion] = useState<string | null>(null);
  const [suche, setSuche] = useState('');
  const [status, setStatus] = useState('alle');
  const [adresse, setAdresse] = useState<'alle' | 'mit' | 'ohne'>('alle');
  const [neu, setNeu] = useState(false);

  const sichtbar = useMemo(() => {
    const begriff = suche.trim().toLowerCase();

    return daten.agenturen.filter((a) => {
      if (region !== null && a.r !== region) return false;
      if (status !== 'alle' && a.status !== status) return false;
      if (adresse === 'mit' && !a.e) return false;
      if (adresse === 'ohne' && a.e) return false;
      if (begriff === '') return true;

      return [a.n, a.c, a.p, a.e, a.flag, (a.f ?? []).join(' '), a.notiz]
        .join(' ')
        .toLowerCase()
        .includes(begriff);
    });
  }, [daten.agenturen, region, status, suche, adresse]);

  const zahl = (wert: string): number => daten.agenturen.filter((a) => a.status === wert).length;
  const ohneAdresse = daten.agenturen.filter((a) => !a.e).length;

  return (
    <div className="space-y-4">
      <section className="panel space-y-4 p-4 sm:p-5">
        {/* Regionen – im Original die Ringe um Wuppertal. */}
        <div className="flex flex-wrap gap-2">
          <Filterknopf an={region === null} onClick={() => setRegion(null)}>
            Alle · {daten.agenturen.length}
          </Filterknopf>
          {daten.regionen.map((r) => (
            <Filterknopf key={r.id} an={region === r.id} onClick={() => setRegion(r.id)}>
              {r.city} <span className="opacity-60">· {r.km}</span>
            </Filterknopf>
          ))}
        </div>

        <div className="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
          <input
            className="field"
            placeholder="Suchen: Name, Ort, Schwerpunkt, Notiz …"
            value={suche}
            onChange={(event) => setSuche(event.target.value)}
          />
          <select className="field sm:w-52" value={status} onChange={(event) => setStatus(event.target.value)}>
            <option value="alle">Alle Status</option>
            {daten.statiAgentur.map((s) => (
              <option key={s} value={s}>
                {s} ({zahl(s)})
              </option>
            ))}
          </select>
          {/* Der wichtigste Filter, deshalb gleichberechtigt neben dem Status:
              Ohne Adresse lässt sich niemand anschreiben – das ist die Liste,
              die man abarbeiten will. */}
          <select
            className="field sm:w-44"
            value={adresse}
            onChange={(event) => setAdresse(event.target.value as typeof adresse)}
          >
            <option value="alle">Mit und ohne Adresse</option>
            <option value="mit">Nur mit Adresse ({daten.agenturen.filter((a) => a.e).length})</option>
            <option value="ohne">Nur ohne Adresse ({daten.agenturen.filter((a) => !a.e).length})</option>
          </select>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="text-xs text-faint">
            {sichtbar.length} von {daten.agenturen.length} · {sichtbar.filter((a) => a.e).length} mit E-Mail
          </p>
          <div className="flex flex-wrap gap-2">
            <button type="button" className="btn btn-ghost text-xs" onClick={() => csvExport(sichtbar)}>
              CSV für Excel
            </button>
            <CsvImport setDaten={setDaten} />
            <button type="button" className="btn btn-primary text-xs" onClick={() => setNeu(true)}>
              + Agentur
            </button>
          </div>
        </div>

        {ohneAdresse > 0 && adresse !== 'ohne' && (
          <button
            type="button"
            onClick={() => setAdresse('ohne')}
            className="w-full rounded-lg border border-warn/30 bg-warn/5 p-3 text-left text-xs text-warn transition-colors hover:border-warn/60"
          >
            <b>{ohneAdresse} Agenturen haben keine E-Mail-Adresse</b> – die kannst du nicht anschreiben. Hier
            klicken, um sie aufzulisten und die Adressen nachzutragen.
          </button>
        )}
      </section>

      {neu && (
        <Formular
          typ="agentur"
          onAbbruch={() => setNeu(false)}
          onFertig={async () => {
            setNeu(false);
            setDaten(await api.bewerbung());
            toast('Angelegt');
          }}
        />
      )}

      {sichtbar.length === 0 ? (
        <p className="panel p-10 text-center text-sm text-faint">Keine Treffer.</p>
      ) : (
        <ul className="space-y-2">
          {sichtbar.map((a) => (
            <Zeile key={a.id} eintrag={a} stati={daten.statiAgentur} ersetze={ersetze} setDaten={setDaten} />
          ))}
        </ul>
      )}
    </div>
  );
}

/** Eine Agentur: alles Wichtige sichtbar, der Rest beim Aufklappen. */
function Zeile({
  eintrag,
  stati,
  ersetze,
  setDaten,
}: {
  eintrag: BewerbungEintrag;
  stati: string[];
  ersetze: (e: BewerbungEintrag) => void;
  setDaten: (d: BewerbungDaten) => void;
}) {
  const toast = useToast();
  const [offen, setOffen] = useState(false);
  const [notiz, setNotiz] = useState(eintrag.notiz);
  const [bearbeiten, setBearbeiten] = useState(false);
  const zeitgeber = useRef<number>(0);

  useEffect(() => setNotiz(eintrag.notiz), [eintrag.notiz]);

  // Beim Tippen nicht bei jedem Zeichen speichern – aber verlässlich, wenn
  // eine kurze Pause entsteht. Dasselbe Verhalten wie vorher.
  const notizMerken = (wert: string): void => {
    setNotiz(wert);
    window.clearTimeout(zeitgeber.current);
    zeitgeber.current = window.setTimeout(() => {
      void api.bewerbungMerken(eintrag.id, { notiz: wert }).then((r) => ersetze(r.eintrag));
    }, 500);
  };

  return (
    <li className="panel overflow-hidden">
      <div className="flex flex-wrap items-center gap-3 p-4">
        <button type="button" onClick={() => setOffen(!offen)} className="min-w-0 flex-1 text-left">
          <span className="flex flex-wrap items-baseline gap-2">
            <span className="truncate text-sm font-medium">{eintrag.n}</span>
            {eintrag.flag && (
              <span className="rounded border border-accent/40 px-1.5 py-0.5 text-[0.5625rem] uppercase tracking-wider text-accent">
                Hinweis
              </span>
            )}
            {eintrag.quelle === 'eigen' && (
              <span className="rounded border border-line px-1.5 py-0.5 text-[0.5625rem] uppercase tracking-wider text-faint">
                selbst angelegt
              </span>
            )}
          </span>
          <span className="mt-0.5 block truncate text-xs text-muted">
            {eintrag.c}
            {typeof eintrag.d === 'number' ? ` · ${eintrag.d} km` : ''}
            {eintrag.e ? ` · ${eintrag.e}` : ''}
          </span>
        </button>

        {/* Fehlt die Adresse, steht das Feld gleich hier – ohne Aufklappen,
            ohne Formular. Bei über sechzig Nachträgen zählt jeder Klick, den
            man nicht machen muss. */}
        {!eintrag.e && <Adressfeld eintrag={eintrag} ersetze={ersetze} />}

        {eintrag.kontaktAm && <span className="shrink-0 text-[0.6875rem] text-faint">{eintrag.kontaktAm}</span>}

        <select
          className={`field w-auto shrink-0 py-1.5 text-xs ${STATUS_FARBE[eintrag.status] ?? ''}`}
          value={eintrag.status}
          onChange={async (event) => {
            const r = await api.bewerbungMerken(eintrag.id, { status: event.target.value });
            ersetze(r.eintrag);
          }}
        >
          {stati.map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </select>
      </div>

      {offen && (
        <div className="space-y-4 border-t border-line px-4 pb-4 pt-4">
          {eintrag.flag && <p className="text-xs text-accent">{eintrag.flag}</p>}

          <dl className="grid gap-3 text-xs sm:grid-cols-2">
            {eintrag.p && (
              <div>
                <dt className="text-faint">Ansprechpartner</dt>
                <dd className="mt-0.5 text-ink">{eintrag.p}</dd>
              </div>
            )}
            {(eintrag.f ?? []).length > 0 && (
              <div>
                <dt className="text-faint">Schwerpunkte</dt>
                <dd className="mt-0.5 flex flex-wrap gap-1">
                  {(eintrag.f ?? []).map((f) => (
                    <span key={f} className="rounded border border-line px-1.5 py-0.5 text-[0.625rem] text-muted">
                      {f}
                    </span>
                  ))}
                </dd>
              </div>
            )}
          </dl>

          <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
            <div>
              <label className="label">Notiz</label>
              <textarea
                className="field min-h-24 resize-y leading-relaxed"
                value={notiz}
                placeholder="Was besprochen, wann nachfassen …"
                onChange={(event) => notizMerken(event.target.value)}
              />
            </div>
            <div>
              <label className="label">Kontakt am</label>
              <input
                type="date"
                className="field"
                value={eintrag.kontaktAm}
                onChange={async (event) => {
                  const r = await api.bewerbungMerken(eintrag.id, { kontaktAm: event.target.value });
                  ersetze(r.eintrag);
                }}
              />
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-4 text-xs">
            {eintrag.u && (
              <a href={eintrag.u} target="_blank" rel="noopener" className="text-accent hover:underline">
                Website ↗
              </a>
            )}
            {eintrag.e && (
              <a href={`mailto:${eintrag.e}`} className="text-muted hover:text-ink">
                Im Mailprogramm öffnen
              </a>
            )}
            <button type="button" className="text-muted hover:text-ink" onClick={() => setBearbeiten(true)}>
              Daten bearbeiten
            </button>
            {eintrag.quelle === 'eigen' && (
              <button
                type="button"
                className="ml-auto text-bad hover:underline"
                onClick={async () => {
                  if (!window.confirm(`„${eintrag.n}" löschen?`)) return;
                  await api.bewerbungLoeschen(eintrag.id);
                  setDaten(await api.bewerbung());
                  toast('Gelöscht');
                }}
              >
                Löschen
              </button>
            )}
          </div>

          {bearbeiten && (
            <Formular
              typ="agentur"
              eintrag={eintrag}
              onAbbruch={() => setBearbeiten(false)}
              onFertig={async () => {
                setBearbeiten(false);
                setDaten(await api.bewerbung());
                toast('Gespeichert');
              }}
            />
          )}
        </div>
      )}
    </li>
  );
}

/**
 * E-Mail-Adresse nachtragen, direkt in der Zeile.
 *
 * Gespeichert wird beim Verlassen des Feldes oder mit Enter – nicht bei
 * jedem Zeichen, sonst schriebe eine halbe Adresse in die Datenbank.
 */
function Adressfeld({
  eintrag,
  ersetze,
}: {
  eintrag: BewerbungEintrag;
  ersetze: (e: BewerbungEintrag) => void;
}) {
  const toast = useToast();
  const [wert, setWert] = useState('');

  const sichern = async (): Promise<void> => {
    const adresse = wert.trim();
    if (adresse === '') return;

    if (!/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(adresse)) {
      toast('Das sieht nicht nach einer E-Mail-Adresse aus.', 'error');

      return;
    }

    const r = await api.bewerbungBearbeiten(eintrag.id, { e: adresse });
    ersetze(r.eintrag);
    toast('Adresse gespeichert');
  };

  return (
    <input
      className="field w-full shrink-0 py-1.5 text-xs sm:w-56"
      placeholder="E-Mail nachtragen …"
      value={wert}
      onChange={(event) => setWert(event.target.value)}
      onBlur={() => void sichern()}
      onKeyDown={(event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          void sichern();
        }
      }}
    />
  );
}

/**
 * Agenturen aus einer Tabelle einlesen.
 *
 * Der Weg über die JSON-Datei verlangt FTP und geht bei der nächsten
 * Aktualisierung der Website verloren. Aus einer Tabelle geht es ohne
 * beides: Spaltenüberschriften wie beim CSV-Export, alles andere wird
 * ignoriert. Doppelte überspringt der Server.
 */
function CsvImport({ setDaten }: { setDaten: (d: BewerbungDaten) => void }) {
  const toast = useToast();
  const wahl = useRef<HTMLInputElement>(null);

  const einlesen = async (datei: File): Promise<void> => {
    try {
      const zeilen = csvLesen(await datei.text());
      if (zeilen.length === 0) {
        toast('Keine brauchbaren Zeilen gefunden.', 'error');

        return;
      }

      const { neu, uebersprungen } = await api.bewerbungAnlegenViele(zeilen);
      setDaten(await api.bewerbung());
      toast(
        `${neu} angelegt` + (uebersprungen > 0 ? `, ${uebersprungen} übersprungen (schon vorhanden)` : ''),
        neu > 0 ? 'success' : 'error',
      );
    } catch (e) {
      toast(e instanceof Error ? e.message : 'Die Datei ließ sich nicht lesen.', 'error');
    }
  };

  return (
    <>
      <input
        ref={wahl}
        type="file"
        accept=".csv,text/csv"
        className="hidden"
        onChange={(event) => {
          const datei = event.target.files?.[0];
          if (datei) void einlesen(datei);
          event.target.value = '';
        }}
      />
      <button
        type="button"
        className="btn btn-ghost text-xs"
        title="Spalten: Name; Ort; km; Website; Schwerpunkte; Ansprechpartner; E-Mail"
        onClick={() => wahl.current?.click()}
      >
        CSV einlesen
      </button>
    </>
  );
}

/* -------------------------------------------------------------------- Stellen */

function Stellen({ daten, ersetze }: { daten: BewerbungDaten; ersetze: (e: BewerbungEintrag) => void }) {
  return (
    <div className="space-y-6">
      <section className="space-y-3">
        <div>
          <h2 className="text-sm font-semibold">Dauerhafte Suchlinks</h2>
          <p className="mt-1 text-xs text-muted">
            Einzelne Anzeigen laufen aus, diese Suchen nicht. Zweimal die Woche durchklicken.
          </p>
        </div>
        <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          {daten.links.map((l) => (
            <li key={l.url}>
              <a
                href={l.url}
                target="_blank"
                rel="noopener"
                className="panel block p-3 transition-colors hover:border-line-strong"
              >
                <span className="block font-mono text-[0.625rem] uppercase tracking-wider text-faint">{l.quelle}</span>
                <span className="mt-1 block text-sm text-ink">{l.titel}</span>
                <span className="mt-0.5 block text-xs text-muted">{l.hinweis}</span>
              </a>
            </li>
          ))}
        </ul>
      </section>

      <section className="space-y-3">
        <div>
          <h2 className="text-sm font-semibold">Konkrete Treffer</h2>
          <p className="mt-1 text-xs text-muted">
            Auch wenn eine Anzeige weg ist: Diese Firmen beschäftigen Gestalter im Haus – guter Grund für eine
            Initiativbewerbung.
          </p>
        </div>
        <ul className="space-y-2">
          {daten.stellen.map((s) => (
            <li key={s.id} className="panel flex flex-wrap items-center gap-3 p-4">
              <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-baseline gap-2">
                  <span className="text-sm font-medium">{s.role}</span>
                  {s.fit && (
                    <span className="rounded border border-ok/40 px-1.5 py-0.5 text-[0.5625rem] uppercase tracking-wider text-ok">
                      passt gut
                    </span>
                  )}
                </p>
                <p className="mt-0.5 text-xs text-muted">
                  {s.co} · {s.loc}
                  {typeof s.d === 'number' ? ` · ${s.d} km` : ''}
                </p>
                {s.note && <p className="mt-1 text-xs text-faint">{s.note}</p>}
                <p className="mt-1.5 flex flex-wrap gap-1">
                  {(s.tags ?? []).map((t) => (
                    <span key={t} className="rounded border border-line px-1.5 py-0.5 text-[0.625rem] text-muted">
                      {t}
                    </span>
                  ))}
                </p>
              </div>

              {s.url && (
                <a href={s.url} target="_blank" rel="noopener" className="shrink-0 text-xs text-accent hover:underline">
                  Anzeige ↗
                </a>
              )}

              <select
                className={`field w-auto shrink-0 py-1.5 text-xs ${STATUS_FARBE[s.status] ?? ''}`}
                value={s.status}
                onChange={async (event) => {
                  const r = await api.bewerbungMerken(s.id, { status: event.target.value });
                  ersetze(r.eintrag);
                }}
              >
                {daten.statiStelle.map((wert) => (
                  <option key={wert} value={wert}>
                    {wert}
                  </option>
                ))}
              </select>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}

/* ---------------------------------------------------------------- Anschreiben */

function Anschreiben({ daten, setDaten }: { daten: BewerbungDaten; setDaten: (d: BewerbungDaten) => void }) {
  const toast = useToast();
  const [vorlage, setVorlage] = useState(daten.vorlage);
  const [vorschauId, setVorschauId] = useState(daten.agenturen.find((a) => a.e)?.id ?? '');
  const [auswahl, setAuswahl] = useState<string[]>([]);
  const [ergebnisse, setErgebnisse] = useState<Array<{ id: string; name: string; ok: boolean; meldung: string }>>([]);
  const [laeuft, setLaeuft] = useState(false);
  const [fortschritt, setFortschritt] = useState<{ fertig: number; gesamt: number } | null>(null);
  const zeitgeber = useRef<number>(0);

  const mitMail = daten.agenturen.filter((a) => a.e);
  const vorschau = daten.agenturen.find((a) => a.id === vorschauId);

  const merken = (neu: typeof vorlage): void => {
    setVorlage(neu);
    window.clearTimeout(zeitgeber.current);
    zeitgeber.current = window.setTimeout(() => {
      void api.bewerbungVorlage(neu).then(() => toast('Vorlage gespeichert'));
    }, 600);
  };

  /**
   * Verschickt in Portionen statt in einem Rutsch.
   *
   * Zwei Gründe: Sechzig Mails in einer einzigen Anfrage laufen auf einem
   * geteilten Webspace ins Zeitlimit, und sechzig Mails in einer Minute
   * sehen für jeden Spamfilter aus wie ein Werbeversand. Zwischen den
   * Portionen liegt deshalb eine Pause, und der Fortschritt ist sichtbar –
   * bricht etwas ab, weiß man, wo.
   */
  const senden = async (ids: string[]): Promise<void> => {
    if (ids.length === 0) return;
    if (!window.confirm(`Das Anschreiben an ${ids.length} Empfänger verschicken?`)) return;

    setLaeuft(true);
    setErgebnisse([]);

    const gesammelt: Array<{ id: string; name: string; ok: boolean; meldung: string }> = [];

    try {
      for (let i = 0; i < ids.length; i += PORTION) {
        const teil = ids.slice(i, i + PORTION);
        setFortschritt({ fertig: i, gesamt: ids.length });

        const { ergebnisse } = await api.bewerbungSenden(teil);
        gesammelt.push(...ergebnisse);
        setErgebnisse([...gesammelt]);

        if (i + PORTION < ids.length) {
          await new Promise((weiter) => window.setTimeout(weiter, PAUSE));
        }
      }

      setDaten(await api.bewerbung());
      const gut = gesammelt.filter((e) => e.ok).length;
      toast(`${gut} von ${gesammelt.length} verschickt`, gut === gesammelt.length ? 'success' : 'error');
    } catch (e) {
      toast(e instanceof Error ? e.message : 'Versand fehlgeschlagen', 'error');
    } finally {
      setFortschritt(null);
      setLaeuft(false);
    }
  };

  return (
    <div className="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
      <div className="space-y-6">
        <section className="panel space-y-4 p-5">
          <div>
            <h2 className="text-sm font-semibold">Dein Anschreiben</h2>
            <p className="mt-1 text-xs text-muted">
              Wird exakt so verschickt, wie es hier steht – nichts wird ersetzt oder ergänzt.
            </p>
          </div>

          <div>
            <label className="label" htmlFor="tpl-subj">
              Betreff
            </label>
            <input
              id="tpl-subj"
              className="field"
              value={vorlage.subj}
              placeholder="Initiativbewerbung als Mediengestalter"
              onChange={(event) => merken({ ...vorlage, subj: event.target.value })}
            />
          </div>

          <div>
            <label className="label" htmlFor="tpl-body">
              Text
            </label>
            <textarea
              id="tpl-body"
              className="field min-h-72 resize-y leading-relaxed"
              value={vorlage.body}
              placeholder={'Guten Tag,\n\n…'}
              onChange={(event) => merken({ ...vorlage, body: event.target.value })}
            />
            <div className="mt-2 space-y-1 text-[0.6875rem] leading-relaxed text-faint">
              <p>Links auf deine Unterlagen kannst du unten kopieren und hier einfügen.</p>
              <p>
                Optionale Platzhalter – wer keine benutzt, verschickt seinen Text unverändert:{' '}
                {['{{anrede}}', '{{agentur}}', '{{ort}}', '{{ansprechpartner}}', '{{schwerpunkte}}'].map((platz) => (
                  <button
                    key={platz}
                    type="button"
                    className="mr-1 rounded border border-line px-1 font-mono text-[0.625rem] text-muted transition-colors hover:border-accent hover:text-ink"
                    onClick={() => merken({ ...vorlage, body: `${vorlage.body}${platz}` })}
                  >
                    {platz}
                  </button>
                ))}
              </p>
              <p>
                <b>{'{{anrede}}'}</b> wird zu „Sehr geehrte:r …", wenn ein Name hinterlegt ist – sonst zu „Sehr
                geehrte Damen und Herren". Kein „Sehr geehrte:r Geschäftsführung".
              </p>
            </div>
          </div>
        </section>

        <Versandfeld versand={daten.versand} setDaten={setDaten} />
        <Dateien daten={daten} setDaten={setDaten} />
      </div>

      <div className="space-y-6">
        <section className="panel space-y-3 p-5">
          <h2 className="text-sm font-semibold">Vorschau</h2>
          <select className="field" value={vorschauId} onChange={(event) => setVorschauId(event.target.value)}>
            {mitMail.map((a) => (
              <option key={a.id} value={a.id}>
                {a.n}
              </option>
            ))}
          </select>
          <div className="rounded-lg border border-line bg-panel2 p-4">
            <p className="font-mono text-[0.625rem] text-faint">An: {vorschau?.e ?? '—'}</p>
            <p className="mt-2 text-sm font-medium text-ink">{vorlage.subj || '(kein Betreff)'}</p>
            <p className="mt-2 whitespace-pre-wrap text-xs leading-relaxed text-muted">
              {vorlage.body || '(noch kein Text)'}
            </p>
          </div>
          {vorschau?.e && (
            <a
              href={`mailto:${vorschau.e}?subject=${encodeURIComponent(vorlage.subj)}&body=${encodeURIComponent(vorlage.body)}`}
              className="btn btn-ghost w-full text-xs"
            >
              Stattdessen im Mailprogramm öffnen
            </a>
          )}
        </section>

        <section className="panel space-y-3 p-5">
          <div className="flex items-center justify-between gap-3">
            <h2 className="text-sm font-semibold">Verschicken</h2>
            <span className="text-xs text-faint">{auswahl.length} ausgewählt</span>
          </div>

          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              className="btn btn-ghost text-xs"
              onClick={() => setAuswahl(mitMail.filter((a) => a.status === 'Offen').map((a) => a.id))}
            >
              Alle offenen ({mitMail.filter((a) => a.status === 'Offen').length})
            </button>
            <button type="button" className="btn btn-ghost text-xs" onClick={() => setAuswahl([])}>
              Auswahl leeren
            </button>
          </div>

          <div className="max-h-72 space-y-1 overflow-y-auto rounded-lg border border-line p-2">
            {mitMail.map((a) => (
              <label key={a.id} className="flex cursor-pointer items-center gap-2 rounded p-1 text-xs hover:bg-panel2">
                <input
                  type="checkbox"
                  className="accent-[var(--accent)]"
                  checked={auswahl.includes(a.id)}
                  onChange={(event) =>
                    setAuswahl((alt) => (event.target.checked ? [...alt, a.id] : alt.filter((id) => id !== a.id)))
                  }
                />
                <span className="min-w-0 flex-1 truncate">{a.n}</span>
                <span className={`shrink-0 ${STATUS_FARBE[a.status] ?? 'text-faint'}`}>{a.status}</span>
              </label>
            ))}
          </div>

          <button
            type="button"
            className="btn btn-primary w-full text-sm"
            disabled={laeuft || auswahl.length === 0}
            onClick={() => void senden(auswahl)}
          >
            {laeuft
              ? `Verschickt … ${fortschritt ? `${fortschritt.fertig} von ${fortschritt.gesamt}` : ''}`
              : `An ${auswahl.length} verschicken`}
          </button>

          {ergebnisse.length > 0 && (
            <ul className="max-h-52 space-y-1 overflow-y-auto text-xs">
              {ergebnisse.map((e) => (
                <li key={e.id} className={e.ok ? 'text-ok' : 'text-bad'}>
                  {e.ok ? '✓' : '✕'} {e.name}
                  {!e.ok && <span className="text-muted"> — {e.meldung}</span>}
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  );
}

/** Zugangsdaten des Postfachs, über das verschickt wird. */
function Versandfeld({
  versand,
  setDaten,
}: {
  versand: BewerbungVersand;
  setDaten: (d: BewerbungDaten) => void;
}) {
  const toast = useToast();
  const [wert, setWert] = useState(versand);
  const [offen, setOffen] = useState(versand.host === '');

  const speichern = async (): Promise<void> => {
    await api.bewerbungVersand(wert);
    setDaten(await api.bewerbung());
    toast('Zugang gespeichert');
  };

  return (
    <section className="panel p-5">
      <button
        type="button"
        onClick={() => setOffen(!offen)}
        className="flex w-full items-center justify-between gap-3 text-left"
      >
        <span>
          <span className="block text-sm font-semibold">Versand über dein Postfach</span>
          <span className="mt-0.5 block text-xs text-muted">
            {versand.host === '' ? 'Noch nicht eingerichtet' : `${versand.absender} über ${versand.host}`}
          </span>
        </span>
        <span className="shrink-0 text-xs text-faint">{offen ? '▲' : '▼'}</span>
      </button>

      {offen && (
        <div className="mt-4 space-y-4">
          <p className="text-[0.6875rem] leading-relaxed text-faint">
            Damit geht die Bewerbung wirklich von deiner Adresse raus, ohne Umweg über ein anderes Programm. Bei
            Gmail: <b>smtp.gmail.com</b>, Port 587, und als Passwort ein <b>App-Passwort</b> aus deinem
            Google-Konto (dein normales Passwort nimmt Google dort nicht an; Zwei-Faktor-Anmeldung muss aktiv sein).
            Google legt so verschickte Mails von selbst in „Gesendet" ab.
          </p>

          <div className="grid gap-3 sm:grid-cols-2">
            <Feld label="Absenderadresse" wert={wert.absender} setzen={(v) => setWert({ ...wert, absender: v })} />
            <Feld label="Absendername" wert={wert.absenderName} setzen={(v) => setWert({ ...wert, absenderName: v })} />
            <Feld label="Mailserver" wert={wert.host} setzen={(v) => setWert({ ...wert, host: v })} platzhalter="smtp.gmail.com" />
            <div className="grid grid-cols-[1fr_1fr] gap-3">
              <Feld label="Port" wert={String(wert.port)} setzen={(v) => setWert({ ...wert, port: Number(v) || 587 })} />
              <div>
                <label className="label">Verschlüsselung</label>
                <select
                  className="field"
                  value={wert.sicherheit}
                  onChange={(event) => setWert({ ...wert, sicherheit: event.target.value as BewerbungVersand['sicherheit'] })}
                >
                  <option value="auto">Automatisch</option>
                  <option value="tls">STARTTLS (587)</option>
                  <option value="ssl">SSL (465)</option>
                  <option value="none">Ohne</option>
                </select>
              </div>
            </div>
            <Feld label="Benutzername" wert={wert.benutzer} setzen={(v) => setWert({ ...wert, benutzer: v })} />
            <div>
              <label className="label">Passwort</label>
              <input
                className="field"
                type="password"
                autoComplete="new-password"
                placeholder={versand.hatPasswort ? 'gespeichert – leer lassen behält es' : ''}
                value={wert.passwort}
                onChange={(event) => setWert({ ...wert, passwort: event.target.value })}
              />
            </div>
          </div>

          <details className="rounded-lg border border-line p-3">
            <summary className="cursor-pointer text-xs text-muted">Kopie im Gesendet-Ordner (IMAP, optional)</summary>
            <p className="mt-2 text-[0.6875rem] leading-relaxed text-faint">
              IMAP verschickt nicht – es legt ab. Bei Gmail nicht nötig. Bei all-inkl und den meisten anderen
              Anbietern landet eine per SMTP verschickte Mail sonst nirgends in deinem Mailprogramm.
            </p>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
              <Feld label="IMAP-Server" wert={wert.imapHost} setzen={(v) => setWert({ ...wert, imapHost: v })} />
              <Feld label="Port" wert={String(wert.imapPort)} setzen={(v) => setWert({ ...wert, imapPort: Number(v) || 993 })} />
              <Feld label="Benutzername" wert={wert.imapBenutzer} setzen={(v) => setWert({ ...wert, imapBenutzer: v })} platzhalter="wie oben" />
              <div>
                <label className="label">Passwort</label>
                <input
                  className="field"
                  type="password"
                  autoComplete="new-password"
                  placeholder="wie oben"
                  value={wert.imapPasswort}
                  onChange={(event) => setWert({ ...wert, imapPasswort: event.target.value })}
                />
              </div>
              <Feld label="Ordner" wert={wert.imapOrdner} setzen={(v) => setWert({ ...wert, imapOrdner: v })} platzhalter="Sent" />
            </div>
          </details>

          <div className="flex flex-wrap gap-2">
            <button type="button" className="btn btn-primary text-xs" onClick={() => void speichern()}>
              Zugang speichern
            </button>
            <button
              type="button"
              className="btn btn-ghost text-xs"
              onClick={async () => {
                const r = await api.bewerbungVersandTest();
                toast(r.message, r.ok ? 'success' : 'error');
              }}
            >
              Testmail an mich
            </button>
          </div>
        </div>
      )}
    </section>
  );
}

/** Lebenslauf, Mappe, Zeugnisse – öffentlich abrufbar, zum Verlinken. */
function Dateien({ daten, setDaten }: { daten: BewerbungDaten; setDaten: (d: BewerbungDaten) => void }) {
  const toast = useToast();
  const wahl = useRef<HTMLInputElement>(null);

  return (
    <section className="panel space-y-4 p-5">
      <div>
        <h2 className="text-sm font-semibold">Unterlagen</h2>
        <p className="mt-1 text-xs text-muted">
          Liegen öffentlich abrufbar auf dem Server. Adresse kopieren und ins Anschreiben setzen, statt anzuhängen –
          das kommt durch jeden Spamfilter.
        </p>
      </div>

      <input
        ref={wahl}
        type="file"
        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
        multiple
        className="hidden"
        onChange={async (event) => {
          const dateien = Array.from(event.target.files ?? []);
          event.target.value = '';
          for (const datei of dateien) {
            try {
              await api.bewerbungDateiHochladen(datei);
            } catch (e) {
              toast(e instanceof Error ? e.message : 'Upload fehlgeschlagen', 'error');
            }
          }
          setDaten(await api.bewerbung());
          toast('Hochgeladen');
        }}
      />
      <button type="button" className="btn btn-ghost w-full text-xs" onClick={() => wahl.current?.click()}>
        Dateien hochladen
      </button>

      {daten.dateien.length > 0 && (
        <ul className="space-y-2">
          {daten.dateien.map((d) => (
            <li key={d.name} className="rounded-lg border border-line p-3">
              <div className="flex items-center justify-between gap-3">
                <span className="min-w-0">
                  <span className="block truncate text-xs text-ink">{d.name}</span>
                  <span className="block font-mono text-[0.625rem] text-faint">{formatBytes(d.groesse)}</span>
                </span>
                <span className="flex shrink-0 gap-3 text-[0.6875rem]">
                  <button
                    type="button"
                    className="text-accent hover:underline"
                    onClick={() => {
                      void navigator.clipboard?.writeText(d.kurz);
                      toast('Adresse kopiert');
                    }}
                  >
                    Adresse kopieren
                  </button>
                  <a href={d.url} target="_blank" rel="noopener" className="text-muted hover:text-ink">
                    Ansehen
                  </a>
                  <button
                    type="button"
                    className="text-bad hover:underline"
                    onClick={async () => {
                      if (!window.confirm(`„${d.name}" löschen?`)) return;
                      await api.bewerbungDateiLoeschen(d.name);
                      setDaten(await api.bewerbung());
                    }}
                  >
                    Löschen
                  </button>
                </span>
              </div>
              <p className="mt-1.5 truncate font-mono text-[0.625rem] text-faint">{d.kurz}</p>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

/* ------------------------------------------------------------------ Bausteine */

function Filterknopf({ an, onClick, children }: { an: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-full border px-3 py-1.5 text-xs transition-colors ${
        an ? 'border-transparent bg-accent text-white' : 'border-line text-muted hover:border-line-strong hover:text-ink'
      }`}
    >
      {children}
    </button>
  );
}

function Feld({
  label,
  wert,
  setzen,
  platzhalter,
}: {
  label: string;
  wert: string;
  setzen: (v: string) => void;
  platzhalter?: string;
}) {
  return (
    <div>
      <label className="label">{label}</label>
      <input className="field" value={wert} placeholder={platzhalter} onChange={(event) => setzen(event.target.value)} />
    </div>
  );
}

/** Anlegen und Bearbeiten – dieselben Felder wie in der Datei. */
function Formular({
  typ,
  eintrag,
  onAbbruch,
  onFertig,
}: {
  typ: 'agentur' | 'stelle';
  eintrag?: BewerbungEintrag;
  onAbbruch: () => void;
  onFertig: () => void;
}) {
  const toast = useToast();
  const [wert, setWert] = useState({
    n: eintrag?.n ?? '',
    c: eintrag?.c ?? '',
    r: eintrag?.r ?? '',
    d: String(eintrag?.d ?? 0),
    u: eintrag?.u ?? '',
    e: eintrag?.e ?? '',
    p: eintrag?.p ?? '',
    f: (eintrag?.f ?? []).join(', '),
    flag: eintrag?.flag ?? '',
  });

  const sichern = async (): Promise<void> => {
    if (wert.n.trim() === '') {
      toast('Ein Name muss sein.', 'error');

      return;
    }

    try {
      if (eintrag) {
        await api.bewerbungBearbeiten(eintrag.id, { ...wert, d: Number(wert.d) || 0 });
      } else {
        await api.bewerbungAnlegen({ typ, ...wert, d: Number(wert.d) || 0 });
      }
      onFertig();
    } catch (e) {
      toast(e instanceof Error ? e.message : 'Speichern fehlgeschlagen', 'error');
    }
  };

  return (
    <div className="panel space-y-3 p-4">
      <div className="grid gap-3 sm:grid-cols-2">
        <Feld label="Name" wert={wert.n} setzen={(v) => setWert({ ...wert, n: v })} />
        <Feld label="Ort" wert={wert.c} setzen={(v) => setWert({ ...wert, c: v })} platzhalter="Wuppertal (Straße)" />
        <Feld label="Region-Kürzel" wert={wert.r} setzen={(v) => setWert({ ...wert, r: v })} platzhalter="wt, berg, dd …" />
        <Feld label="Entfernung (km)" wert={wert.d} setzen={(v) => setWert({ ...wert, d: v })} />
        <Feld label="Website" wert={wert.u} setzen={(v) => setWert({ ...wert, u: v })} />
        <Feld label="E-Mail" wert={wert.e} setzen={(v) => setWert({ ...wert, e: v })} />
        <Feld label="Ansprechpartner" wert={wert.p} setzen={(v) => setWert({ ...wert, p: v })} />
        <Feld
          label="Schwerpunkte"
          wert={wert.f}
          setzen={(v) => setWert({ ...wert, f: v })}
          platzhalter="Print, Web, Motion"
        />
      </div>
      <Feld label="Hinweis" wert={wert.flag} setzen={(v) => setWert({ ...wert, flag: v })} />

      <div className="flex gap-2">
        <button type="button" className="btn btn-primary text-xs" onClick={() => void sichern()}>
          Speichern
        </button>
        <button type="button" className="btn btn-ghost text-xs" onClick={onAbbruch}>
          Abbrechen
        </button>
      </div>
    </div>
  );
}

/* -------------------------------------------------------------------- Export */

/**
 * CSV für deutsches Excel: Semikolon als Trenner und ein BOM voran, sonst
 * landen Umlaute als Buchstabensalat in der Tabelle.
 */
function csvExport(eintraege: BewerbungEintrag[]): void {
  const kopf = ['Name', 'Ort', 'km', 'Website', 'Schwerpunkte', 'Ansprechpartner', 'E-Mail', 'Status', 'Kontakt am', 'Notiz'];
  const zelle = (wert: unknown): string => `"${String(wert ?? '').replace(/"/g, '""').replace(/\r?\n/g, ' ')}"`;

  const zeilen = [
    kopf.join(';'),
    ...eintraege.map((a) =>
      [a.n, a.c, a.d, a.u, (a.f ?? []).join(', '), a.p, a.e, a.status, a.kontaktAm, a.notiz].map(zelle).join(';'),
    ),
  ];

  lade(new Blob(['﻿' + zeilen.join('\r\n')], { type: 'text/csv;charset=utf-8;' }), 'bewerbungs-radar.csv');
}

/**
 * Liest eine Tabelle ein – dieselben Spalten, die der Export ausgibt.
 *
 * Gerechnet wird mit deutschem Excel: Semikolon als Trenner, ein BOM
 * vorneweg, Anführungszeichen um Felder mit Sonderzeichen. Kommas als
 * Trenner werden trotzdem erkannt, falls die Datei woanders herkommt.
 */
function csvLesen(text: string): Array<Record<string, unknown>> {
  const roh = text.replace(/^\ufeff/, '').trim();
  if (roh === '') return [];

  const trenner = (roh.split('\n')[0]?.split(';').length ?? 1) > 1 ? ';' : ',';

  // Zeilenweise zerlegen, aber Anführungszeichen respektieren: In einem
  // Feld darf ein Trenner stehen, ohne die Spalte zu sprengen.
  const zerlege = (zeile: string): string[] => {
    const felder: string[] = [];
    let feld = '';
    let inAnfuehrung = false;

    for (let i = 0; i < zeile.length; i++) {
      const zeichen = zeile[i];

      if (zeichen === '"') {
        if (inAnfuehrung && zeile[i + 1] === '"') {
          feld += '"';
          i++;
        } else {
          inAnfuehrung = !inAnfuehrung;
        }
        continue;
      }

      if (zeichen === trenner && !inAnfuehrung) {
        felder.push(feld);
        feld = '';
        continue;
      }

      feld += zeichen;
    }

    felder.push(feld);

    return felder.map((f) => f.trim());
  };

  const zeilen = roh.split(/\r?\n/).filter((z) => z.trim() !== '');
  const kopf = zerlege(zeilen[0] ?? '').map((k) => k.toLowerCase().replace(/[^a-zäöüß]/g, ''));

  // Überschrift → Feldkürzel. Was nicht zugeordnet werden kann, fällt weg.
  const spalten: Record<string, string> = {
    name: 'n', agentur: 'n', firma: 'n',
    ort: 'c', stadt: 'c', adresse: 'c',
    km: 'd', entfernung: 'd',
    website: 'u', url: 'u', web: 'u',
    schwerpunkte: 'f', leistungen: 'f',
    ansprechpartner: 'p', kontakt: 'p',
    email: 'e', mail: 'e', epost: 'e',
    region: 'r',
    hinweis: 'flag', notiz: 'flag',
  };

  return zeilen.slice(1).flatMap((zeile) => {
    const werte = zerlege(zeile);
    const eintrag: Record<string, unknown> = {};

    kopf.forEach((ueberschrift, i) => {
      const feld = spalten[ueberschrift];
      if (feld === undefined || werte[i] === undefined || werte[i] === '') return;
      eintrag[feld] = feld === 'd' ? Number(werte[i].replace(/[^0-9]/g, '')) || 0 : werte[i];
    });

    return typeof eintrag.n === 'string' && eintrag.n !== '' ? [eintrag] : [];
  });
}

function sicherungSpeichern(daten: BewerbungDaten): void {
  const stand = (liste: BewerbungEintrag[]): Record<string, unknown> =>
    Object.fromEntries(
      liste.map((e) => [e.id, { status: e.status, note: e.notiz, date: e.kontaktAm }]),
    );

  const inhalt = JSON.stringify(
    { v: 1, exported: new Date().toISOString(), agenturen: stand(daten.agenturen), stellen: stand(daten.stellen), vorlage: daten.vorlage },
    null,
    1,
  );

  lade(new Blob([inhalt], { type: 'application/json' }), 'bewerbungs-radar-sicherung.json');
}

function lade(blob: Blob, name: string): void {
  const url = URL.createObjectURL(blob);
  const el = document.createElement('a');
  el.href = url;
  el.download = name;
  document.body.append(el);
  el.click();
  el.remove();
  URL.revokeObjectURL(url);
}
