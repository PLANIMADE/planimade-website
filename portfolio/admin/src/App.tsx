import { Navigate, Route, Routes } from 'react-router-dom';
import { useAuth } from './lib/auth';
import Layout from './components/Layout';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Projects from './pages/Projects';
import ProjectEditor from './pages/ProjectEditor';
import MediaLibrary from './pages/MediaLibrary';
import Messages from './pages/Messages';
import Testimonials from './pages/Testimonials';
import SettingsPage from './pages/Settings';

export default function App() {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="grid min-h-screen place-items-center">
        <p className="font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>
      </div>
    );
  }

  if (!user) {
    return <Login />;
  }

  return (
    <Layout>
      <Routes>
        <Route path="/" element={<Dashboard />} />
        <Route path="/projekte" element={<Projects />} />
        <Route path="/projekte/neu" element={<ProjectEditor />} />
        <Route path="/projekte/:id" element={<ProjectEditor />} />
        <Route path="/medien" element={<MediaLibrary />} />
        <Route path="/nachrichten" element={<Messages />} />
        <Route path="/stimmen" element={<Testimonials />} />
        <Route path="/einstellungen" element={<SettingsPage />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Layout>
  );
}
