import { useEffect, useState } from 'react';
import { api, formatDate, type Project } from '../lib/api';
import { useToast } from '../lib/toast';

type TrashedProject = Project & { deletedAt: string; purgeAt: string };

export default function Trash() {
  const toast = useToast();
  const [projects, setProjects] = useState<TrashedProject[]>([]);
  const [loading, setLoading] = useState(true);

  const load = () => {
    api
      .trash()
      .then((data) => setProjects(data.projects))
      .catch(() => toast('Papierkorb konnte nicht geladen werden.', 'error'))
      .finally(() => setLoading(false));
  };

  useEffect(load, []); // eslint-disable-line react-hooks/exhaustive-deps

  const restore = async (project: TrashedProject) => {
    try {
      await api.restoreProject(project.id);
      setProjects((current) => current.filter((entry) => entry.id !== project.id));
      toast(`„${project.title}" wiederhergestellt – als Entwurf sichtbar.`);
    } catch {
      toast('Wiederherstellen fehlgeschlagen.', 'error');
    }
  };

  const purge = async (project: TrashedProject) => {
    if (!window.confirm(`„${project.title}" endgültig löschen? Das lässt sich nicht rückgängig machen.`)) return;

    try {
      await api.purgeProject(project.id);
      setProjects((current) => current.filter((entry) => entry.id !== project.id));
      toast('Endgültig gelöscht');
    } catch {
      toast('Löschen fehlgeschlagen.', 'error');
    }
  };

  const daysLeft = (purgeAt: string): number =>
    Math.max(0, Math.ceil((new Date(purgeAt).getTime() - Date.now()) / 86_400_000));

  return (
    <div className="animate-in space-y-6">
      <header>
        <h1 className="text-xl font-semibold tracking-tight">Papierkorb</h1>
        <p className="mt-1 text-sm text-muted">
          Gelöschte Projekte bleiben 30 Tage erhalten und verschwinden danach automatisch.
        </p>
      </header>

      {loading ? (
        <p className="font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>
      ) : projects.length === 0 ? (
        <div className="panel p-12 text-center text-sm text-faint">Der Papierkorb ist leer.</div>
      ) : (
        <ul className="space-y-2">
          {projects.map((project) => (
            <li key={project.id} className="panel flex items-center gap-4 p-3">
              <div className="h-12 w-16 shrink-0 overflow-hidden rounded-md border border-line bg-panel2">
                {project.cover ? (
                  <img
                    src={project.cover.thumbUrl ?? project.cover.url}
                    alt=""
                    className="h-full w-full object-cover opacity-60"
                    loading="lazy"
                  />
                ) : (
                  <span className="grid h-full w-full place-items-center text-[0.5rem] text-faint">KEIN BILD</span>
                )}
              </div>

              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{project.title}</p>
                <p className="truncate text-xs text-faint">
                  Gelöscht am {formatDate(project.deletedAt)} · noch {daysLeft(project.purgeAt)} Tage
                </p>
              </div>

              <div className="flex shrink-0 gap-1.5">
                <button type="button" onClick={() => void restore(project)} className="btn btn-ghost px-3 py-1.5 text-xs">
                  Wiederherstellen
                </button>
                <button type="button" onClick={() => void purge(project)} className="btn btn-danger px-3 py-1.5 text-xs">
                  Endgültig löschen
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
