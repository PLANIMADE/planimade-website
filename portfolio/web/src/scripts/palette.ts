/**
 * Command-Palette (⌘K / Strg+K).
 *
 * Enthält Seiten, alle veröffentlichten Projekte und Aktionen. Die Projekte
 * werden erst beim ersten Öffnen geladen – das kostet auf der Startseite nichts.
 */

import { fetchProjects, fetchSettings } from '../lib/api';
import { toggleTheme } from './theme';
import { sound } from './sound';

interface Command {
  id: string;
  label: string;
  hint?: string;
  group: string;
  keywords?: string;
  run: () => void;
}

export function initPalette(): void {
  const root = document.querySelector<HTMLElement>('[data-command-root]');
  const panel = root?.querySelector<HTMLElement>('[data-command-panel]');
  const input = root?.querySelector<HTMLInputElement>('[data-command-input]');
  const list = root?.querySelector<HTMLUListElement>('[data-command-list]');
  const backdrop = root?.querySelector<HTMLElement>('[data-command-backdrop]');
  if (!root || !panel || !input || !list || !backdrop) return;

  let open = false;
  let commands: Command[] = [];
  let filtered: Command[] = [];
  let active = 0;
  let projectsLoaded = false;

  const baseCommands: Command[] = [
    { id: 'home', label: 'Startseite', group: 'Seiten', run: () => (location.href = '/') },
    { id: 'work', label: 'Alle Arbeiten', group: 'Seiten', run: () => (location.href = '/work/') },
    { id: 'about', label: 'Über mich', group: 'Seiten', run: () => (location.href = '/about/') },
    { id: 'contact', label: 'Kontakt aufnehmen', group: 'Seiten', run: () => (location.href = '/contact/') },
    {
      id: 'theme',
      label: 'Hell / Dunkel umschalten',
      hint: 'Design',
      group: 'Aktionen',
      keywords: 'dark light theme farbe',
      run: () => toggleTheme(),
    },
    {
      id: 'mail',
      label: 'E-Mail-Adresse kopieren',
      group: 'Aktionen',
      keywords: 'mail kontakt adresse',
      run: () => {
        void fetchSettings().then((settings) => {
          void navigator.clipboard?.writeText(settings.email).then(
            () => notify('E-Mail-Adresse kopiert'),
            () => notify(settings.email),
          );
        });
      },
    },
    {
      id: 'top',
      label: 'Nach oben',
      group: 'Aktionen',
      keywords: 'scroll oben anfang',
      run: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    },
  ];

  commands = [...baseCommands];

  const notify = (message: string): void => {
    const toast = document.createElement('div');
    toast.className =
      'fixed bottom-6 left-1/2 z-[90] -translate-x-1/2 rounded-full border border-line-strong bg-elevated px-5 py-2.5 text-sm text-ink shadow-xl transition-opacity duration-300';
    toast.textContent = message;
    document.body.append(toast);
    window.setTimeout(() => {
      toast.style.opacity = '0';
      window.setTimeout(() => toast.remove(), 320);
    }, 1800);
  };

  const render = (): void => {
    const query = input.value.trim().toLowerCase();
    filtered = query
      ? commands.filter((command) =>
          `${command.label} ${command.group} ${command.keywords ?? ''} ${command.hint ?? ''}`
            .toLowerCase()
            .includes(query),
        )
      : commands;

    active = 0;
    list.innerHTML = '';

    if (filtered.length === 0) {
      list.innerHTML =
        '<li class="px-4 py-8 text-center text-sm text-faint">Nichts gefunden. Andere Schreibweise probieren?</li>';
      return;
    }

    let lastGroup = '';
    filtered.forEach((command, index) => {
      if (command.group !== lastGroup) {
        lastGroup = command.group;
        const heading = document.createElement('li');
        heading.className = 'px-3 pt-3 pb-1 font-mono text-[0.625rem] uppercase tracking-[0.2em] text-faint';
        heading.textContent = command.group;
        list.append(heading);
      }

      const item = document.createElement('li');
      item.role = 'option';
      item.dataset.index = String(index);
      item.className =
        'flex cursor-pointer items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm text-muted transition-colors';
      item.innerHTML = `<span class="truncate">${escapeHtml(command.label)}</span>${
        command.hint ? `<span class="shrink-0 font-mono text-[0.625rem] text-faint">${escapeHtml(command.hint)}</span>` : ''
      }`;

      item.addEventListener('mouseenter', () => {
        active = index;
        highlight();
      });
      item.addEventListener('click', () => {
        close();
        command.run();
      });

      list.append(item);
    });

    highlight();
  };

  const highlight = (): void => {
    list.querySelectorAll<HTMLLIElement>('li[data-index]').forEach((item) => {
      const isActive = Number(item.dataset.index) === active;
      item.classList.toggle('bg-surface-hover', isActive);
      item.classList.toggle('text-ink', isActive);
      item.setAttribute('aria-selected', String(isActive));
      if (isActive) item.scrollIntoView({ block: 'nearest' });
    });
  };

  const loadProjects = async (): Promise<void> => {
    if (projectsLoaded) return;
    projectsLoaded = true;
    try {
      const { projects } = await fetchProjects();
      commands = [
        ...baseCommands,
        ...projects.map((project) => ({
          id: `project-${project.id}`,
          label: project.title,
          hint: project.category,
          group: 'Projekte',
          keywords: [...project.tags, ...project.tools, project.client].join(' '),
          run: () => (location.href = `/work/${project.slug}`),
        })),
      ];
      render();
    } catch {
      /* Ohne API bleiben wenigstens die Seiten-Befehle nutzbar. */
    }
  };

  const openPalette = (): void => {
    if (open) return;
    open = true;
    root.hidden = false;
    requestAnimationFrame(() => {
      root.classList.replace('opacity-0', 'opacity-100');
      root.classList.remove('pointer-events-none');
      panel.classList.remove('translate-y-2', 'scale-[0.98]');
    });
    input.value = '';
    render();
    void loadProjects();
    input.focus();
    document.body.style.overflow = 'hidden';
    sound.open();
  };

  const close = (): void => {
    if (!open) return;
    open = false;
    root.classList.replace('opacity-100', 'opacity-0');
    root.classList.add('pointer-events-none');
    panel.classList.add('translate-y-2', 'scale-[0.98]');
    document.body.style.overflow = '';
    window.setTimeout(() => {
      if (!open) root.hidden = true;
    }, 300);
    sound.close();
  };

  document.querySelectorAll('[data-command-open]').forEach((button) => button.addEventListener('click', openPalette));
  backdrop.addEventListener('click', close);
  input.addEventListener('input', render);

  document.addEventListener('keydown', (event) => {
    const isShortcut = (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k';
    if (isShortcut) {
      event.preventDefault();
      open ? close() : openPalette();
      return;
    }
    if (!open) return;

    if (event.key === 'Escape') {
      event.preventDefault();
      close();
    } else if (event.key === 'ArrowDown') {
      event.preventDefault();
      active = (active + 1) % Math.max(filtered.length, 1);
      highlight();
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      active = (active - 1 + filtered.length) % Math.max(filtered.length, 1);
      highlight();
    } else if (event.key === 'Enter') {
      event.preventDefault();
      const command = filtered[active];
      if (command) {
        close();
        command.run();
      }
    }
  });
}

function escapeHtml(value: string): string {
  const div = document.createElement('div');
  div.textContent = value;

  return div.innerHTML;
}
