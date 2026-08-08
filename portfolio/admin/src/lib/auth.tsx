import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { api, setCsrfToken } from './api';

interface User {
  id: number;
  email: string;
  name: string;
}

interface AuthValue {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  // Beim Start prüfen, ob noch eine gültige Sitzung besteht.
  useEffect(() => {
    let active = true;

    api
      .me()
      .then((data) => {
        if (!active) return;
        setUser(data.user);
        if (data.csrfToken) setCsrfToken(data.csrfToken);
      })
      .catch(() => undefined)
      .finally(() => active && setLoading(false));

    return () => {
      active = false;
    };
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const data = await api.login(email, password);
    setCsrfToken(data.csrfToken);
    setUser(data.user);
  }, []);

  const logout = useCallback(async () => {
    await api.logout().catch(() => undefined);
    setCsrfToken('');
    setUser(null);
  }, []);

  const value = useMemo(() => ({ user, loading, login, logout }), [user, loading, login, logout]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth muss innerhalb von <AuthProvider> verwendet werden.');

  return context;
}
