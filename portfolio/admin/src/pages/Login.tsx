import { useState, type FormEvent } from 'react';
import { useAuth } from '../lib/auth';
import { ApiError } from '../lib/api';

export default function Login() {
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setError('');
    setBusy(true);

    try {
      await login(email, password);
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Anmeldung fehlgeschlagen.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="grid min-h-screen place-items-center px-4">
      <div className="w-full max-w-sm">
        <div className="mb-8 text-center">
          <span className="mx-auto grid h-12 w-12 place-items-center rounded-xl border border-line-strong text-base font-bold tracking-tight">
            DM
          </span>
          <h1 className="mt-5 text-lg font-semibold">Portfolio-Dashboard</h1>
          <p className="mt-1 text-sm text-faint">Bitte anmelden, um Inhalte zu pflegen.</p>
        </div>

        <form onSubmit={onSubmit} className="panel animate-in space-y-4 p-6">
          {error && (
            <p className="rounded-lg border border-bad/40 bg-bad/10 px-3 py-2.5 text-sm text-bad" role="alert">
              {error}
            </p>
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
              autoFocus
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
              autoComplete="current-password"
              required
            />
          </div>

          <button type="submit" className="btn btn-primary w-full" disabled={busy}>
            {busy ? 'Wird geprüft …' : 'Anmelden'}
          </button>
        </form>

        <p className="mt-6 text-center text-xs leading-relaxed text-faint">
          Passwort vergessen? Auf dem Server einmal
          <code className="mx-1 rounded bg-panel2 px-1.5 py-0.5">php api/scripts/setup.php --email=… --password=… --force</code>
          ausführen.
        </p>
      </div>
    </div>
  );
}
