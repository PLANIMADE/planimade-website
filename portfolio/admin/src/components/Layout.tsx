import { useEffect, useState, type ReactNode } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

const navigation = [
  { to: '/', label: 'Übersicht', icon: '◈', end: true },
  { to: '/projekte', label: 'Projekte', icon: '▤' },
  { to: '/medien', label: 'Medien', icon: '▦' },
  { to: '/nachrichten', label: 'Nachrichten', icon: '✉', badge: true },
  { to: '/stimmen', label: 'Kundenstimmen', icon: '❝' },
  { to: '/papierkorb', label: 'Papierkorb', icon: '⌫' },
  { to: '/einstellungen', label: 'Einstellungen', icon: '⚙' },
  { to: '/system', label: 'Systemcheck', icon: '✓' },
];

export default function Layout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();
  const location = useLocation();
  const [unread, setUnread] = useState(0);
  const [menuOpen, setMenuOpen] = useState(false);

  // Ungelesene Nachrichten regelmäßig nachzählen – das ist die einzige Zahl,
  // die man im Blick haben will, ohne die Seite zu wechseln.
  useEffect(() => {
    const load = () => {
      api
        .messages('new')
        .then((data) => setUnread(data.unread))
        .catch(() => undefined);
    };

    load();
    const timer = window.setInterval(load, 60_000);

    return () => window.clearInterval(timer);
  }, [location.pathname]);

  useEffect(() => setMenuOpen(false), [location.pathname]);

  return (
    <div className="flex min-h-screen">
      {/* Seitenleiste */}
      <aside
        className={`fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-line bg-panel transition-transform duration-300 lg:translate-x-0 ${
          menuOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex items-center gap-3 border-b border-line px-5 py-5">
          <span className="grid h-9 w-9 place-items-center rounded-lg border border-line-strong text-sm font-bold tracking-tight">
            DM
          </span>
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold">Portfolio</p>
            <p className="truncate text-xs text-faint">Dashboard</p>
          </div>
        </div>

        <nav className="flex-1 space-y-1 p-3">
          {navigation.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors ${
                  isActive ? 'bg-panel2 text-ink' : 'text-muted hover:bg-panel2/60 hover:text-ink'
                }`
              }
            >
              <span aria-hidden className="w-4 text-center text-xs text-faint">
                {item.icon}
              </span>
              <span className="flex-1">{item.label}</span>
              {item.badge && unread > 0 && (
                <span className="rounded-full bg-accent px-1.5 py-0.5 text-[0.625rem] font-semibold text-white">
                  {unread}
                </span>
              )}
            </NavLink>
          ))}
        </nav>

        <div className="space-y-3 border-t border-line p-4">
          <a
            href="/"
            target="_blank"
            rel="noopener"
            className="flex items-center gap-2 text-xs text-muted transition-colors hover:text-ink"
          >
            Website ansehen ↗
          </a>
          <div className="flex items-center justify-between gap-2">
            <p className="min-w-0 truncate text-xs text-faint" title={user?.email}>
              {user?.email}
            </p>
            <button type="button" onClick={() => void logout()} className="btn btn-ghost px-2.5 py-1 text-xs">
              Abmelden
            </button>
          </div>
        </div>
      </aside>

      {menuOpen && (
        <button
          type="button"
          aria-label="Menü schließen"
          onClick={() => setMenuOpen(false)}
          className="fixed inset-0 z-30 bg-black/60 lg:hidden"
        />
      )}

      {/* Inhalt */}
      <div className="flex min-h-screen flex-1 flex-col lg:pl-64">
        <header className="sticky top-0 z-20 flex items-center gap-3 border-b border-line bg-bg/85 px-5 py-3 backdrop-blur lg:hidden">
          <button type="button" onClick={() => setMenuOpen(true)} className="btn btn-ghost px-3 py-1.5">
            ☰
          </button>
          <span className="text-sm font-medium">Portfolio-Dashboard</span>
        </header>

        <main className="flex-1 px-5 py-8 sm:px-8 lg:px-10">
          <div className="mx-auto w-full max-w-6xl">{children}</div>
        </main>
      </div>
    </div>
  );
}
