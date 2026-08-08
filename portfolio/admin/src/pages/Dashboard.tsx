import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, type Stats } from '../lib/api';

const RANGES = [7, 30, 90];

export default function Dashboard() {
  const [stats, setStats] = useState<Stats | null>(null);
  const [days, setDays] = useState(30);
  const [error, setError] = useState('');

  useEffect(() => {
    setStats(null);
    api
      .stats(days)
      .then(setStats)
      .catch(() => setError('Statistik konnte nicht geladen werden.'));
  }, [days]);

  if (error) return <p className="text-sm text-bad">{error}</p>;

  if (!stats) {
    return <p className="font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>;
  }

  const { counts, analytics } = stats;
  const maxViews = Math.max(1, ...analytics.perDay.map((day) => day.views));

  return (
    <div className="animate-in space-y-8">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold tracking-tight">Übersicht</h1>
          <p className="mt-1 text-sm text-muted">Was auf der Website passiert – ohne Cookies erhoben.</p>
        </div>

        <div className="flex gap-1 rounded-lg border border-line p-1">
          {RANGES.map((range) => (
            <button
              key={range}
              type="button"
              onClick={() => setDays(range)}
              className={`rounded-md px-3 py-1.5 text-xs transition-colors ${
                days === range ? 'bg-panel2 text-ink' : 'text-muted hover:text-ink'
              }`}
            >
              {range} Tage
            </button>
          ))}
        </div>
      </header>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Aufrufe" value={analytics.totals.views} hint={`in ${days} Tagen`} />
        <StatCard label="Besucher" value={analytics.totals.visitors} hint="ungefähr, ohne Cookies" />
        <StatCard label="Projektansichten" value={analytics.totals.projectViews} hint="Case-Studies geöffnet" />
        <StatCard
          label="Neue Nachrichten"
          value={counts.unreadMessages}
          hint={counts.unreadMessages > 0 ? 'warten auf Antwort' : 'alles beantwortet'}
          highlight={counts.unreadMessages > 0}
          to="/nachrichten"
        />
      </div>

      <section className="panel p-6">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-semibold">Aufrufe pro Tag</h2>
          <p className="font-mono text-[0.625rem] tracking-widest text-faint">MAX {maxViews}</p>
        </div>

        <div className="mt-6 flex h-40 items-end gap-[3px]">
          {analytics.perDay.map((day) => (
            <div
              key={day.day}
              className="group relative flex-1 rounded-t bg-accent/25 transition-colors hover:bg-accent"
              style={{ height: `${Math.max(2, (day.views / maxViews) * 100)}%` }}
            >
              <span className="pointer-events-none absolute -top-9 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded border border-line bg-panel2 px-2 py-1 text-[0.625rem] group-hover:block">
                {new Date(day.day).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })} · {day.views}
              </span>
            </div>
          ))}
        </div>
      </section>

      <div className="grid gap-4 lg:grid-cols-2">
        <ListCard
          title="Meistgesehene Projekte"
          empty="Noch keine Projektaufrufe."
          items={stats.mostViewed.map((project) => ({
            key: String(project.id),
            label: project.title,
            value: `${project.views}`,
          }))}
        />
        <ListCard
          title="Beliebteste Seiten"
          empty="Noch keine Aufrufe."
          items={analytics.topPages.map((page) => ({ key: page.path, label: page.path, value: `${page.views}` }))}
        />
        <ListCard
          title="Woher kommen Besucher?"
          empty="Bislang nur Direktaufrufe."
          items={analytics.referrers.map((item) => ({
            key: item.referrer,
            label: item.referrer,
            value: `${item.views}`,
          }))}
        />
        <ListCard
          title="Geräte"
          empty="Noch keine Daten."
          items={analytics.devices.map((item) => ({
            key: item.device,
            label: { mobile: 'Smartphone', tablet: 'Tablet', desktop: 'Desktop' }[item.device] ?? item.device,
            value: `${item.views}`,
          }))}
        />
      </div>

      <section className="panel flex flex-wrap items-center justify-between gap-4 p-6">
        <div className="text-sm text-muted">
          <span className="text-ink">{counts.published}</span> veröffentlicht ·{' '}
          <span className="text-ink">{counts.drafts}</span> Entwürfe ·{' '}
          <span className="text-ink">{counts.media}</span> Mediendateien
        </div>
        <div className="flex gap-2">
          <a href={api.exportUrl} download="portfolio-backup.json" className="btn btn-ghost">
            Backup herunterladen
          </a>
          <Link to="/projekte/neu" className="btn btn-primary">
            Neues Projekt
          </Link>
        </div>
      </section>
    </div>
  );
}

function StatCard({
  label,
  value,
  hint,
  highlight,
  to,
}: {
  label: string;
  value: number;
  hint: string;
  highlight?: boolean;
  to?: string;
}) {
  const content = (
    <div className={`panel h-full p-5 transition-colors ${to ? 'hover:border-line-strong' : ''}`}>
      <p className="text-xs text-muted">{label}</p>
      <p className={`mt-2 text-3xl font-semibold tracking-tight ${highlight ? 'text-accent' : ''}`}>{value}</p>
      <p className="mt-1 text-xs text-faint">{hint}</p>
    </div>
  );

  return to ? <Link to={to}>{content}</Link> : content;
}

function ListCard({
  title,
  items,
  empty,
}: {
  title: string;
  items: Array<{ key: string; label: string; value: string }>;
  empty: string;
}) {
  return (
    <section className="panel p-6">
      <h2 className="text-sm font-semibold">{title}</h2>
      {items.length === 0 ? (
        <p className="mt-4 text-sm text-faint">{empty}</p>
      ) : (
        <ul className="mt-4 space-y-2.5">
          {items.map((item) => (
            <li key={item.key} className="flex items-center justify-between gap-4 text-sm">
              <span className="min-w-0 truncate text-muted">{item.label}</span>
              <span className="shrink-0 font-mono text-xs text-ink">{item.value}</span>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
