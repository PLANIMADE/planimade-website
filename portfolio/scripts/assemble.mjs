#!/usr/bin/env node

/**
 * Baut aus den fertigen Builds den Ordner `deploy/` zusammen –
 * exakt so, wie er später auf dem Webspace liegt.
 *
 * Aufruf: npm run build   (baut vorher Frontend und Dashboard)
 *         node scripts/assemble.mjs   (nur zusammenstellen)
 */

import { cp, mkdir, rm, writeFile, readdir, stat } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const deploy = path.join(root, 'deploy');

const green = (text) => `[32m${text}[0m`;
const dim = (text) => `[2m${text}[0m`;
const red = (text) => `[31m${text}[0m`;

async function requireDir(dir, hint) {
  if (!existsSync(dir)) {
    console.error(red(`\nFehlt: ${path.relative(root, dir)}`));
    console.error(`   ${hint}\n`);
    process.exit(1);
  }
}

async function directorySize(dir) {
  let total = 0;
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    total += entry.isDirectory() ? await directorySize(full) : (await stat(full)).size;
  }

  return total;
}

const webDist = path.join(root, 'web', 'dist');
const adminDist = path.join(root, 'admin', 'dist');

await requireDir(webDist, 'Bitte zuerst `npm run build:web` ausführen.');
await requireDir(adminDist, 'Bitte zuerst `npm run build:admin` ausführen.');

await rm(deploy, { recursive: true, force: true });
await mkdir(deploy, { recursive: true });

// 1. Öffentliche Website
await cp(webDist, deploy, { recursive: true });

// 2. Dashboard
await cp(adminDist, path.join(deploy, 'admin'), { recursive: true });

// 3. API – ohne lokale Daten, Zugangsdaten und Entwicklungshilfen
await cp(path.join(root, 'api'), path.join(deploy, 'api'), {
  recursive: true,
  filter: (source) => {
    const name = path.basename(source);
    const relative = path.relative(path.join(root, 'api'), source);

    if (name === '.env.php') return false;
    if (name === 'router.dev.php') return false;
    if (name === 'error.log' || name === 'salt.key') return false;
    if (name.endsWith('.sqlite') || name.endsWith('.sqlite-wal') || name.endsWith('.sqlite-shm')) return false;
    // storage/ wird angelegt, aber ohne Inhalt (Live-Daten bleiben unangetastet)
    if (relative.startsWith('storage') && name !== 'storage' && name !== '.htaccess') return false;

    return true;
  },
});

// 4. Server-Konfiguration
await cp(path.join(root, 'server', 'root.htaccess'), path.join(deploy, '.htaccess'));
await mkdir(path.join(deploy, 'uploads'), { recursive: true });
await cp(path.join(root, 'server', 'uploads.htaccess'), path.join(deploy, 'uploads', '.htaccess'));

// 5. Kurze Erinnerung direkt im Ordner
await writeFile(
  path.join(deploy, 'api', 'storage', 'HINWEIS.txt'),
  [
    'Dieser Ordner enthält die Datenbank (portfolio.sqlite) und den Sicherheitsschlüssel.',
    '',
    'Beim Hochladen per FTP diesen Ordner NIEMALS überschreiben oder leeren –',
    'sonst sind alle Projekte, Nachrichten und Statistiken weg.',
    '',
    'Für ein Backup genügt es, portfolio.sqlite herunterzuladen.',
  ].join('\n'),
  'utf8',
);

const size = await directorySize(deploy);

console.log(`
${green('✓ deploy/ ist fertig')}

  ${dim('Struktur')}
  deploy/
    index.html, work/, about/ …   ${dim('die Website')}
    _astro/                       ${dim('Styles, Skripte, Schriften')}
    admin/                        ${dim('Dashboard')}
    api/                          ${dim('PHP-Backend')}
    uploads/                      ${dim('Medien (auf dem Server belassen!)')}
    .htaccess                     ${dim('Weiterleitungen, Cache, Sicherheit')}

  ${dim('Gesamtgröße')}  ${(size / 1024 / 1024).toFixed(1)} MB

  ${dim('Nächster Schritt')}
  Inhalt von deploy/ per FTP in das Domain-Verzeichnis laden.
  ${red('Beim Hochladen ausnehmen:')} uploads/  und  api/storage/
  ${dim('Details: DEPLOY-ALL-INKL.md')}
`);
