import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';

type ToastKind = 'success' | 'error' | 'info';

interface Toast {
  id: number;
  message: string;
  kind: ToastKind;
}

const ToastContext = createContext<((message: string, kind?: ToastKind) => void) | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);

  const push = useCallback((message: string, kind: ToastKind = 'success') => {
    const id = Date.now() + Math.random();
    setToasts((current) => [...current, { id, message, kind }]);
    window.setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== id)), 4000);
  }, []);

  const value = useMemo(() => push, [push]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div className="pointer-events-none fixed bottom-6 right-6 z-50 flex flex-col gap-2">
        {toasts.map((toast) => (
          <div
            key={toast.id}
            role="status"
            className="animate-in pointer-events-auto flex items-center gap-3 rounded-lg border border-line bg-panel2 px-4 py-3 text-sm shadow-xl"
          >
            <span
              className="h-2 w-2 shrink-0 rounded-full"
              style={{
                background:
                  toast.kind === 'success' ? 'var(--ok)' : toast.kind === 'error' ? 'var(--bad)' : 'var(--accent)',
              }}
            />
            <span className={toast.kind === 'error' ? 'text-bad' : 'text-ink'}>{toast.message}</span>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast(): (message: string, kind?: ToastKind) => void {
  const context = useContext(ToastContext);
  if (!context) throw new Error('useToast muss innerhalb von <ToastProvider> verwendet werden.');

  return context;
}
