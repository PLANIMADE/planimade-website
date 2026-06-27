"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from "react";
import {
  loadProgress,
  replaceProgress,
  subscribeProgress,
} from "@/features/progression/local";
import { mergeProgress } from "@/features/progression/merge";
import { getSupabase, isSupabaseConfigured } from "./supabase";
import { loadRemote, saveRemote } from "./sync";

export interface AuthUser {
  id: string;
  email?: string;
}

interface AuthContextValue {
  configured: boolean;
  user: AuthUser | null;
  syncing: boolean;
  signInWithEmail: (email: string) => Promise<{ error?: string }>;
  signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue>({
  configured: false,
  user: null,
  syncing: false,
  signInWithEmail: async () => ({ error: "Cloud-Sync ist nicht konfiguriert." }),
  signOut: async () => {},
});

export function useAuth(): AuthContextValue {
  return useContext(AuthContext);
}

/**
 * Hält die Auth-Session und synchronisiert den lokalen Store mit der Cloud:
 * beim Login wird gemerged (verlustfrei), danach werden lokale Änderungen
 * (debounced) hochgeschrieben. Ohne Supabase-Konfiguration ein No-Op.
 */
export function AuthSyncProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [syncing, setSyncing] = useState(false);
  const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const unsubStore = useRef<(() => void) | null>(null);

  useEffect(() => {
    const supabase = getSupabase();
    if (!supabase) return;

    const scheduleSave = (uid: string) => {
      if (saveTimer.current) clearTimeout(saveTimer.current);
      saveTimer.current = setTimeout(() => {
        saveRemote(supabase, uid, loadProgress()).catch(() => {});
      }, 1200);
    };

    const onSession = async (uid: string | null, email?: string) => {
      // alte Store-Subscription lösen
      unsubStore.current?.();
      unsubStore.current = null;

      setUser(uid ? { id: uid, email } : null);
      if (!uid) return;

      setSyncing(true);
      try {
        const remote = await loadRemote(supabase, uid);
        const local = loadProgress();
        const merged = remote ? mergeProgress(local, remote) : local;
        replaceProgress(merged);
        await saveRemote(supabase, uid, merged);
      } catch {
        /* offline o.ä. – lokal weiterarbeiten */
      } finally {
        setSyncing(false);
      }

      // ab jetzt lokale Änderungen hochschreiben
      unsubStore.current = subscribeProgress(() => scheduleSave(uid));
    };

    supabase.auth
      .getSession()
      .then(({ data }) =>
        onSession(data.session?.user?.id ?? null, data.session?.user?.email),
      );

    const { data: sub } = supabase.auth.onAuthStateChange((_event, session) =>
      onSession(session?.user?.id ?? null, session?.user?.email),
    );

    return () => {
      sub.subscription.unsubscribe();
      unsubStore.current?.();
      if (saveTimer.current) clearTimeout(saveTimer.current);
    };
  }, []);

  const signInWithEmail = useCallback(async (email: string) => {
    const supabase = getSupabase();
    if (!supabase) return { error: "Cloud-Sync ist nicht konfiguriert." };
    const { error } = await supabase.auth.signInWithOtp({
      email,
      options: {
        emailRedirectTo:
          typeof window !== "undefined"
            ? `${window.location.origin}/profile`
            : undefined,
      },
    });
    return { error: error?.message };
  }, []);

  const signOut = useCallback(async () => {
    const supabase = getSupabase();
    await supabase?.auth.signOut();
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider
      value={{
        configured: isSupabaseConfigured(),
        user,
        syncing,
        signInWithEmail,
        signOut,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}
