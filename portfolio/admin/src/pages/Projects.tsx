import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { api, formatDate, type Project } from '../lib/api';
import { useToast } from '../lib/toast';

export default function Projects() {
  const toast = useToast();
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);

  const sensors = useSensors(
    // Kleiner Schwellwert, damit ein Klick auf „Bearbeiten" kein Ziehen auslöst.
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  useEffect(() => {
    api
      .projects()
      .then((data) => setProjects(data.projects))
      .catch(() => toast('Projekte konnten nicht geladen werden.', 'error'))
      .finally(() => setLoading(false));
  }, [toast]);

  const onDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;

    const oldIndex = projects.findIndex((project) => project.id === active.id);
    const newIndex = projects.findIndex((project) => project.id === over.id);
    const next = arrayMove(projects, oldIndex, newIndex);

    setProjects(next);
    api
      .reorderProjects(next.map((project) => project.id))
      .then(() => toast('Reihenfolge gespeichert'))
      .catch(() => toast('Reihenfolge konnte nicht gespeichert werden.', 'error'));
  };

  const toggleStatus = async (project: Project) => {
    const status = project.status === 'published' ? 'draft' : 'published';
    try {
      const { project: updated } = await api.updateProject(project.id, { status });
      setProjects((current) => current.map((item) => (item.id === project.id ? updated : item)));
      toast(status === 'published' ? 'Projekt ist live' : 'Projekt ist jetzt ein Entwurf');
    } catch {
      toast('Status konnte nicht geändert werden.', 'error');
    }
  };

  const remove = async (project: Project) => {
    if (!window.confirm(`„${project.title}" wirklich löschen? Das lässt sich nicht rückgängig machen.`)) return;

    try {
      await api.deleteProject(project.id);
      setProjects((current) => current.filter((item) => item.id !== project.id));
      toast('Projekt gelöscht');
    } catch {
      toast('Projekt konnte nicht gelöscht werden.', 'error');
    }
  };

  return (
    <div className="animate-in space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold tracking-tight">Projekte</h1>
          <p className="mt-1 text-sm text-muted">
            Reihenfolge per Ziehen ändern – die Startseite zeigt das erste Projekt groß.
          </p>
        </div>
        <Link to="/projekte/neu" className="btn btn-primary">
          + Neues Projekt
        </Link>
      </header>

      {loading ? (
        <p className="font-mono text-xs tracking-[0.2em] text-faint">WIRD GELADEN …</p>
      ) : projects.length === 0 ? (
        <div className="panel p-12 text-center">
          <p className="text-sm text-muted">Noch keine Projekte angelegt.</p>
          <Link to="/projekte/neu" className="btn btn-primary mt-5">
            Erstes Projekt anlegen
          </Link>
        </div>
      ) : (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
          <SortableContext items={projects.map((project) => project.id)} strategy={verticalListSortingStrategy}>
            <ul className="space-y-2">
              {projects.map((project) => (
                <SortableRow
                  key={project.id}
                  project={project}
                  onToggle={() => void toggleStatus(project)}
                  onDelete={() => void remove(project)}
                />
              ))}
            </ul>
          </SortableContext>
        </DndContext>
      )}
    </div>
  );
}

function SortableRow({
  project,
  onToggle,
  onDelete,
}: {
  project: Project;
  onToggle: () => void;
  onDelete: () => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: project.id });

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition, opacity: isDragging ? 0.6 : 1 }}
      className="panel flex items-center gap-4 p-3"
    >
      <button
        type="button"
        {...attributes}
        {...listeners}
        aria-label={`${project.title} verschieben`}
        className="cursor-grab px-1.5 text-faint transition-colors hover:text-ink active:cursor-grabbing"
      >
        ⠿
      </button>

      <div className="h-12 w-16 shrink-0 overflow-hidden rounded-md border border-line bg-panel2">
        {project.cover ? (
          <img
            src={project.cover.thumbUrl ?? project.cover.url}
            alt=""
            className="h-full w-full object-cover"
            loading="lazy"
          />
        ) : (
          <span className="grid h-full w-full place-items-center text-[0.5rem] text-faint">KEIN BILD</span>
        )}
      </div>

      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium">{project.title}</p>
        <p className="truncate text-xs text-faint">
          {[project.category, project.year, `${project.views} Aufrufe`].filter(Boolean).join(' · ')}
        </p>
      </div>

      <button
        type="button"
        onClick={onToggle}
        className={`hidden shrink-0 rounded-full border px-2.5 py-1 text-[0.625rem] font-medium transition-colors sm:block ${
          project.status === 'published'
            ? 'border-ok/40 text-ok hover:bg-ok/10'
            : project.publishAt
              ? 'border-warn/40 text-warn hover:bg-warn/10'
              : 'border-line text-faint hover:text-ink'
        }`}
        title={project.publishAt ? `Geplant für ${formatDate(project.publishAt)}` : 'Status umschalten'}
      >
        {project.status === 'published' ? 'LIVE' : project.publishAt ? 'GEPLANT' : 'ENTWURF'}
      </button>

      <div className="flex shrink-0 gap-1.5">
        <Link to={`/projekte/${project.id}`} className="btn btn-ghost px-3 py-1.5 text-xs">
          Bearbeiten
        </Link>
        <button type="button" onClick={onDelete} className="btn btn-danger px-2.5 py-1.5 text-xs" title="Löschen">
          ✕
        </button>
      </div>
    </li>
  );
}
