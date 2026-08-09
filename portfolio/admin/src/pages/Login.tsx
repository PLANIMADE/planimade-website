import { useEffect, useState, type FormEvent } from 'react';
import { useAuth } from '../lib/auth';
import { ApiError, api } from '../lib/api';

/**
 * Eingang zum Dashboard.
 *
 * Beim allerersten Aufruf gibt es noch keinen Zugang – dann steht hier ein
 * Formular zum Anlegen statt eines Logins. So gibt es genau eine Adresse,
 * die man sich merken muss: `/admin/`.
 */
export default function Login() {
  const { login, setup } = useAuth();
  const [modus, setModus] = useState<'laedt' | 'login' | 'einrichten'>('laedt');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [name, setName] = useState('');
  const [demo, setDemo] = useState(true);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let aktiv = true;

    api
      .setupRequired()
      .then((data) => {
        if (aktiv) setModus(data.required ? 'einrichten' : 'login');
      })
      // Antwortet die Frage nicht, ist der Login der sichere Standard:
      // Ein fälschlich angezeigtes Einrichtungsformular wäre schlimmer.
      .catch(() => {
        if (aktiv) setModus('login');
      });

    return () => {
      aktiv = false;
    };
  }, []);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setError('');
    setBusy(true);

    try {
      if (modus === 'einrichten') {
        await setup(email, password, name, demo);
      } else {
        await login(email, password);
      }
    } catch (caught) {
      // 409 heißt: In der Zwischenzeit wurde bereits ein Zugang angelegt.
      // Dann einfach auf den Login umschalten, statt eine Sackgasse zu zeigen.
      if (caught instanceof ApiError && caught.status === 409) {
        setModus('login');
        setError('Es gibt bereits einen Zugang. Bitte melde dich an.');
      } else {
        setError(
          caught instanceof ApiError
            ? caught.message
            : modus === 'einrichten'
              ? 'Einrichtung fehlgeschlagen.'
              : 'Anmeldung fehlgeschlagen.',
        );
      }
    } finally {
      setBusy(false);
    }
  };

  if (modus === 'laedt') {
    return (
      <div className="grid min-h-screen place-items-center px-4">
        <p className="font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>
      </div>
    );
  }

  const einrichten = modus === 'einrichten';

  return (
    <div className="grid min-h-screen place-items-center px-4">
      <div className="w-full max-w-sm">
        <div className="mb-8 text-center">
          <span className="mx-auto grid h-12 w-12 place-items-center rounded-xl border border-line-strong text-base font-bold tracking-tight">
            DM
          </span>
          <h1 className="mt-5 text-lg font-semibold">
            {einrichten ? 'Portfolio einrichten' : 'Portfolio-Dashboard'}
          </h1>
          <p className="mt-1 text-sm leading-relaxed text-faint">
            {einrichten
              ? 'Noch kein Zugang vorhanden. Lege jetzt deinen an – damit meldest du dich künftig hier an.'
              : 'Bitte anmelden, um Inhalte zu pflegen.'}
          </p>
        </div>

        <form onSubmit={onSubmit} className="panel animate-in space-y-4 p-6">
          {error && (
            <p className="rounded-lg border border-bad/40 bg-bad/10 px-3 py-2.5 text-sm text-bad" role="alert">
              {error}
            </p>
          )}

          {einrichten && (
            <div>
              <label className="label" htmlFor="name">
                Dein Name
              </label>
              <input
                id="name"
                type="text"
                className="field"
                value={name}
                onChange={(event) => setName(event.target.value)}
                placeholder="Vorname Nachname"
                autoComplete="name"
                autoFocus
              />
            </div>
          )}

          <div>
            <label className="label" htmlFor="email">
              E-Mail
            </label>
            <input
              id="email"
              type="email"
              className="field"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              autoComplete="username"
              required
              autoFocus={!einrichten}
            />
          </div>

          <div>
            <label className="label" htmlFor="password">
              Passwort
            </label>
            <input
              id="password"
              type="password"
              className="field"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              autoComplete={einrichten ? 'new-password' : 'current-password'}
              minLength={einrichten ? 10 : undefined}
              required
            />
            {einrichten && (
              <p className="mt-1 text-[0.6875rem] text-faint">
                Mindestens 10 Zeichen. Merk es dir gut – zurücksetzen geht nur auf dem Server.
              </p>
            )}
          </div>

          {einrichten && (
            <label className="flex cursor-pointer items-start gap-2.5 text-sm text-muted">
              <input
                type="checkbox"
                checked={demo}
                onChange={(event) => setDemo(event.target.checked)}
                className="mt-0.5 accent-[var(--accent)]"
              />
              Beispielprojekte anlegen, damit die Seite nicht leer startet (später löschbar)
            </label>
          )}

          <button type="submit" className="btn btn-primary w-full" disabled={busy}>
            {busy
              ? einrichten
                ? 'Wird angelegt …'
                : 'Wird geprüft …'
              : einrichten
                ? 'Zugang anlegen und starten'
                : 'Anmelden'}
          </button>
        </form>

        {!einrichten && (
          <p className="mt-6 text-center text-xs leading-relaxed text-faint">
            Passwort vergessen? Auf dem Server einmal
            <code className="mx-1 rounded bg-panel2 px-1.5 py-0.5">
              php api/scripts/setup.php --email=… --password=… --force
            </code>
            ausführen.
          </p>
        )}
      </div>
    </div>
  );
}
