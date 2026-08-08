import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { HashRouter } from 'react-router-dom';
import App from './App';
import { AuthProvider } from './lib/auth';
import { ToastProvider } from './lib/toast';
import './styles.css';

/**
 * HashRouter statt BrowserRouter: So funktioniert das Dashboard auf jedem
 * Webspace, auch wenn eine .htaccess-Weiterleitung fehlt oder überschrieben wird.
 */
createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <HashRouter>
      <ToastProvider>
        <AuthProvider>
          <App />
        </AuthProvider>
      </ToastProvider>
    </HashRouter>
  </StrictMode>,
);
