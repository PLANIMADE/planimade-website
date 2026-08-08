import { useEffect, useState } from 'react';
import { api, formatDate, type Message } from '../lib/api';
import { useToast } from '../lib/toast';

const FILTERS = [
  { value: 'new', label: 'Neu' },
  { value: 'read', label: 'Gelesen' },
  { value: 'archived', label: 'Archiv' },
  { value: 'all', label: 'Alle' },
] as const;

export default function Messages() {
  const toast = useToast();
  const [messages, setMessages] = useState<Message[]>([]);
  const [filter, setFilter] = useState<string>('new');
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState<Message | null>(null);

  useEffect(() => {
    setLoading(true);
    api
      .messages(filter)
      .then((data) => setMessages(data.messages))
      .catch(() => toast('Nachrichten konnten nicht geladen werden.', 'error'))
      .finally(() => setLoading(false));
  }, [filter, toast]);

  const setStatus = async (message: Message, status: Message['status']) => {
    try {
      await api.setMessageStatus(message.id, status);
      setMessages((current) =>
        filter === 'all'
          ? current.map((entry) => (entry.id === message.id ? { ...entry, status } : entry))
          : current.filter((entry) => entry.id !== message.id),
      );
      if (open?.id === message.id) setOpen({ ...message, status });
      toast(status === 'archived' ? 'Archiviert' : 'Als gelesen markiert');
    } catch {
      toast('Status konnte nicht geändert werden.', 'error');
    }
  };

  const remove = async (message: Message) => {
    if (!window.confirm('Nachricht endgültig löschen?')) return;

    try {
      await api.deleteMessage(message.id);
      setMessages((current) => current.filter((entry) => entry.id !== message.id));
      setOpen(null);
      toast('Nachricht gelöscht');
    } catch {
      toast('Nachricht konnte nicht gelöscht werden.', 'error');
    }
  };

  // Beim Öffnen automatisch als gelesen markieren – spart einen Klick.
  const openMessage = (message: Message) => {
    setOpen(message);
    if (message.status === 'new') void setStatus(message, 'read');
  };

  return (
    <div className="animate-in space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold tracking-tight">Nachrichten</h1>
          <p className="mt-1 text-sm text-muted">Anfragen aus dem Kontaktformular.</p>
        </div>

        <div className="flex gap-1 rounded-lg border border-line p-1">
          {FILTERS.map((entry) => (
            <button
              key={entry.value}
              type="button"
              onClick={() => setFilter(entry.value)}
              className={`rounded-md px-3 py-1.5 text-xs transition-colors ${
                filter === entry.value ? 'bg-panel2 text-ink' : 'text-muted hover:text-ink'
              }`}
            >
              {entry.label}
            </button>
          ))}
        </div>
      </header>

      {loading ? (
        <p className="font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>
      ) : messages.length === 0 ? (
        <div className="panel p-12 text-center text-sm text-faint">
          {filter === 'new' ? 'Keine neuen Anfragen. Posteingang leer.' : 'Nichts vorhanden.'}
        </div>
      ) : (
        <ul className="space-y-2">
          {messages.map((message) => (
            <li key={message.id}>
              <button
                type="button"
                onClick={() => openMessage(message)}
                className="panel flex w-full items-center gap-4 p-4 text-left transition-colors hover:border-line-strong"
              >
                {message.status === 'new' && <span className="h-2 w-2 shrink-0 rounded-full bg-accent" />}
                <div className="min-w-0 flex-1">
                  <p className="flex items-baseline gap-2">
                    <span className="truncate text-sm font-medium">{message.name}</span>
                    <span className="truncate text-xs text-faint">{message.email}</span>
                  </p>
                  <p className="mt-0.5 truncate text-xs text-muted">
                    {message.subject ? `${message.subject} — ` : ''}
                    {message.body}
                  </p>
                </div>
                <span className="shrink-0 text-[0.6875rem] text-faint">{formatDate(message.createdAt)}</span>
              </button>
            </li>
          ))}
        </ul>
      )}

      {open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
          <button type="button" aria-label="Schließen" onClick={() => setOpen(null)} className="absolute inset-0 bg-black/70" />

          <div className="panel animate-in relative flex max-h-[85vh] w-full max-w-2xl flex-col">
            <header className="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
              <div className="min-w-0">
                <h2 className="truncate text-sm font-semibold">{open.subject || 'Anfrage ohne Betreff'}</h2>
                <p className="mt-1 truncate text-xs text-muted">
                  {open.name} · {open.email}
                  {open.budget ? ` · Budget: ${open.budget}` : ''}
                </p>
                <p className="mt-0.5 text-[0.6875rem] text-faint">{formatDate(open.createdAt)}</p>
              </div>
              <button type="button" onClick={() => setOpen(null)} className="btn btn-ghost shrink-0 px-2 py-1 text-xs">
                ✕
              </button>
            </header>

            <div className="flex-1 overflow-y-auto px-6 py-5">
              <p className="whitespace-pre-wrap text-sm leading-relaxed text-ink">{open.body}</p>
            </div>

            <footer className="flex flex-wrap gap-2 border-t border-line px-6 py-4">
              <a
                href={`mailto:${open.email}?subject=${encodeURIComponent(`Re: ${open.subject || 'Deine Anfrage'}`)}`}
                className="btn btn-primary"
              >
                Antworten
              </a>
              {open.status !== 'archived' && (
                <button type="button" onClick={() => void setStatus(open, 'archived')} className="btn btn-ghost">
                  Archivieren
                </button>
              )}
              <button type="button" onClick={() => void remove(open)} className="btn btn-danger ml-auto">
                Löschen
              </button>
            </footer>
          </div>
        </div>
      )}
    </div>
  );
}
