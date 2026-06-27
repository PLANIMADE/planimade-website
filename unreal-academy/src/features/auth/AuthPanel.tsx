"use client";

import { useState } from "react";
import { useAuth } from "./AuthSync";

/** Konto-/Cloud-Sync-Panel auf dem Profil. */
export function AuthPanel() {
  const { configured, user, syncing, signInWithEmail, signOut } = useAuth();
  const [email, setEmail] = useState("");
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  // Ohne Konfiguration: dezenter Hinweis (App bleibt voll nutzbar, lokal).
  if (!configured) {
    return (
      <div className="rounded-2xl border border-border bg-surface/40 p-4 text-sm text-muted">
        <div className="font-semibold text-text">Nur auf diesem Gerät</div>
        <p className="mt-1 text-xs leading-relaxed">
          Dein Fortschritt wird lokal gespeichert. Cloud-Konto &amp; Sync über
          mehrere Geräte lassen sich per Supabase aktivieren (siehe README).
        </p>
      </div>
    );
  }

  if (user) {
    return (
      <div className="flex items-center justify-between rounded-2xl border border-success/40 bg-success/5 p-4">
        <div className="min-w-0">
          <div className="text-sm font-semibold text-text">
            {syncing ? "Synchronisiere …" : "Cloud-Sync aktiv"}
          </div>
          <div className="truncate text-xs text-muted">{user.email}</div>
        </div>
        <button
          onClick={() => signOut()}
          className="shrink-0 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs text-muted transition hover:text-text"
        >
          Abmelden
        </button>
      </div>
    );
  }

  const submit = async () => {
    setError(null);
    setBusy(true);
    const { error } = await signInWithEmail(email.trim());
    setBusy(false);
    if (error) setError(error);
    else setSent(true);
  };

  return (
    <div className="rounded-2xl border border-border bg-surface/40 p-4">
      <div className="text-sm font-semibold text-text">
        Fortschritt in der Cloud sichern
      </div>
      {sent ? (
        <p className="mt-2 text-xs leading-relaxed text-success">
          Magic Link verschickt — öffne den Link in deiner E-Mail, um dich
          anzumelden. Dein lokaler Fortschritt wird dann übernommen.
        </p>
      ) : (
        <>
          <p className="mt-1 text-xs leading-relaxed text-muted">
            Per E-Mail anmelden (Magic Link). Geräteübergreifend, dein bisheriger
            Gast-Fortschritt wird verschmolzen.
          </p>
          <div className="mt-3 flex gap-2">
            <input
              type="email"
              inputMode="email"
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="du@beispiel.de"
              className="min-w-0 flex-1 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-accent"
            />
            <button
              onClick={submit}
              disabled={busy || !email.includes("@")}
              className="shrink-0 rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-bg transition hover:brightness-110 disabled:opacity-50"
            >
              {busy ? "…" : "Link senden"}
            </button>
          </div>
          {error && <p className="mt-2 text-xs text-danger">{error}</p>}
        </>
      )}
    </div>
  );
}
