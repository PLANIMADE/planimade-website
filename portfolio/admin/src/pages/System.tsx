import { useEffect, useState } from 'react';
import { api, type MediaItem, type SystemReport } from '../lib/api';
import { useToast } from '../lib/toast';

const STATUS_STYLE: Record<string, { color: string; label: string }> = {
  ok: { color: 'var(--ok)', label: 'In Ordnung' },
  warn: { color: 'var(--warn)', label: 'Hinweis' },
  error: { color: 'var(--bad)', label: 'Problem' },
};

export default function System() {
  const toast = useToast();
  const [report, setReport] = useState<SystemReport | null>(null);
  const [busy, setBusy] = useState('');

  const load = () => {
    api
      .system()
      .then(setReport)
      .catch(() => toast('Systemcheck konnte nicht geladen werden.', 'error'));
  };

  useEffect(load, []); // eslint-disable-line react-hooks/exhaustive-deps

  const mailTest = async () => {
    setBusy('mail');
    try {
      const result = await api.mailTest();
      toast(result.message, result.ok ? 'success' : 'error');
    } catch (error) {
      toast(error instanceof Error ? error.message : 'Testmail fehlgeschlagen', 'error');
    } finally {
      setBusy('');
    }
  };

  // Läuft in Schritten – so kann auch eine große Mediathek nicht in ein
  // Zeitlimit des Servers laufen.
  const optimize = async () => {
    setBusy('optimize');
    try {
      let total = 0;
      for (let round = 0; round < 40; round++) {
        const result = await api.optimizeImages(15);
        total += result.optimized;
        if (result.remaining === 0 || result.optimized === 0) break;
      }
      toast(total > 0 ? `${total} Bilder optimiert` : 'Alle Bilder sind bereits optimiert');
      load();
    } catch (error) {
      toast(error instanceof Error ? error.message : 'Optimierung fehlgeschlagen', 'error');
    } finally {
      setBusy('');
    }
  };

  const rebuildCards = async () => {
    setBusy('cards');
    try {
      const result = await api.rebuildSocialCards();
      toast(`${result.generated} Vorschaubilder erzeugt`);
    } catch (error) {
      toast(error instanceof Error ? error.message : 'Erzeugen fehlgeschlagen', 'error');
    } finally {
      setBusy('');
    }
  };

  if (!report) return <p className="font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>;

  const problems = report.checks.filter((check) => check.status !== 'ok').length;

  return (
    <div className="animate-in space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold tracking-tight">Systemcheck</h1>
          <p className="mt-1 text-sm text-muted">
            {problems === 0
              ? 'Alles in Ordnung – der Server erfüllt alle Voraussetzungen.'
              : `${problems} Punkt${problems === 1 ? '' : 'e'} solltest du dir ansehen.`}
          </p>
        </div>
        <button type="button" onClick={load} className="btn btn-ghost">
          Erneut prüfen
        </button>
      </header>

      <section className="panel divide-y divide-line">
        {report.checks.map((check) => {
          const style = STATUS_STYLE[check.status] ?? STATUS_STYLE.ok!;

          return (
            <div key={check.label} className="flex flex-wrap items-start gap-3 p-4">
              <span
                className="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                style={{ background: style.color }}
                title={style.label}
              />
              <div className="min-w-0 flex-1">
                <p className="text-sm">{check.label}</p>
                {check.hint && <p className="mt-1 text-xs leading-relaxed text-muted">{check.hint}</p>}
              </div>
              <span className="shrink-0 font-mono text-xs text-faint">{check.value}</span>
            </div>
          );
        })}
      </section>

      <section className="panel p-6">
        <h2 className="text-sm font-semibold">Zahlen</h2>
        <dl className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
          <Info label="Datenbank" value={report.info.databaseSize} />
          <Info label="Medien gesamt" value={report.info.uploadsSize} />
          <Info label="Dateien" value={String(report.info.mediaCount)} />
          <Info label="Im Papierkorb" value={String(report.info.trashCount)} />
          <Info label="Arbeitsspeicher" value={report.info.memoryLimit} />
          <Info label="Zeitlimit" value={`${report.info.maxExecutionTime} s`} />
          <Info label="Ohne kleine Größen" value={String(report.info.imagesWithoutVariants)} />
          <Info label="Ohne Beschreibung" value={String(report.info.imagesWithoutAlt)} />
          <Info label="Geplante Projekte" value={String(report.info.scheduledProjects)} />
        </dl>
      </section>

      {report.info.imagesWithoutAlt > 0 && <MissingAltPanel count={report.info.imagesWithoutAlt} />}

      <section className="panel space-y-4 p-6">
        <h2 className="text-sm font-semibold">Wartung</h2>

        <Action
          title="Mailversand testen"
          description="Schickt eine Testnachricht an die hinterlegte Adresse. Kommt sie nicht an, liegt es fast immer am Absender."
          button="Testmail senden"
          busy={busy === 'mail'}
          onClick={mailTest}
        />

        <Action
          title="Bilder optimieren"
          description={
            report.info.imagesWithoutVariants > 0
              ? `${report.info.imagesWithoutVariants} Bilder haben noch keine verkleinerten Fassungen. Das kostet Besucher unnötig Ladezeit.`
              : 'Alle Bilder liegen in mehreren Größen vor.'
          }
          button="Jetzt nachholen"
          busy={busy === 'optimize'}
          disabled={report.info.imagesWithoutVariants === 0}
          onClick={optimize}
        />

        <Action
          title="Vorschaubilder neu erzeugen"
          description="Die Bilder, die beim Teilen eines Projektlinks erscheinen. Nach Änderungen an Titeln oder Titelbildern sinnvoll."
          button="Neu erzeugen"
          busy={busy === 'cards'}
          onClick={rebuildCards}
        />
      </section>
    </div>
  );
}

/**
 * Bilder ohne Beschreibung – direkt hier ergänzbar.
 *
 * Alt-Texte sind das, was Screenreader vorlesen und was in der Bildersuche
 * landet. Sie fehlen fast immer, weil sie beim Hochladen niemand einträgt.
 */
function MissingAltPanel({ count }: { count: number }) {
  const toast = useToast();
  const [items, setItems] = useState<MediaItem[]>([]);
  const [drafts, setDrafts] = useState<Record<number, string>>({});
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (!open) return;

    api
      .mediaWithoutAlt()
      .then((data) => setItems(data.media))
      .catch(() => toast('Liste konnte nicht geladen werden.', 'error'));
  }, [open, toast]);

  const save = async (item: MediaItem) => {
    const alt = (drafts[item.id] ?? '').trim();
    if (alt === '') return;

    try {
      await api.updateMedia(item.id, alt);
      setItems((current) => current.filter((entry) => entry.id !== item.id));
      toast('Beschreibung gespeichert');
    } catch {
      toast('Konnte nicht gespeichert werden.', 'error');
    }
  };

  return (
    <section className="panel space-y-4 p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold">Bilder ohne Beschreibung</h2>
          <p className="mt-1 text-[0.6875rem] leading-relaxed text-muted">
            {count} {count === 1 ? 'Bild hat' : 'Bilder haben'} keinen Alt-Text. Screenreader lesen
            dann nur den Dateinamen vor, und die Bildersuche findet nichts.
          </p>
        </div>
        <button type="button" onClick={() => setOpen(!open)} className="btn btn-ghost shrink-0 text-xs">
          {open ? 'Einklappen' : 'Jetzt ergänzen'}
        </button>
      </div>

      {open && (
        <ul className="space-y-2">
          {items.length === 0 ? (
            <li className="text-sm text-faint">Alles beschrieben.</li>
          ) : (
            items.map((item) => (
              <li key={item.id} className="flex items-center gap-3 rounded-lg border border-line p-2">
                <img
                  src={item.thumbUrl ?? item.url}
                  alt=""
                  className="h-12 w-16 shrink-0 rounded border border-line object-cover"
                  loading="lazy"
                />
                <input
                  className="field flex-1 py-1.5 text-xs"
                  placeholder="Was ist auf dem Bild zu sehen?"
                  value={drafts[item.id] ?? ''}
                  onChange={(event) => setDrafts({ ...drafts, [item.id]: event.target.value })}
                  onKeyDown={(event) => event.key === 'Enter' && void save(item)}
                />
                <button
                  type="button"
                  onClick={() => void save(item)}
                  disabled={(drafts[item.id] ?? '').trim() === ''}
                  className="btn btn-ghost shrink-0 px-3 py-1.5 text-xs"
                >
                  Speichern
                </button>
              </li>
            ))
          )}
        </ul>
      )}
    </section>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-xs text-faint">{label}</dt>
      <dd className="mt-1 text-sm font-medium">{value}</dd>
    </div>
  );
}

function Action({
  title,
  description,
  button,
  busy,
  disabled,
  onClick,
}: {
  title: string;
  description: string;
  button: string;
  busy: boolean;
  disabled?: boolean;
  onClick: () => void | Promise<void>;
}) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-line p-4">
      <div className="min-w-0 flex-1">
        <p className="text-sm">{title}</p>
        <p className="mt-1 text-xs leading-relaxed text-muted">{description}</p>
      </div>
      <button
        type="button"
        onClick={() => void onClick()}
        disabled={busy || disabled}
        className="btn btn-ghost shrink-0 text-xs"
      >
        {busy ? 'Läuft …' : button}
      </button>
    </div>
  );
}
