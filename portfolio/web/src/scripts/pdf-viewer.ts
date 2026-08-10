/**
 * PDFs im Seitendesign anzeigen – ohne den eingebauten Betrachter.
 *
 * Der erste Versuch war, das Dokument dem Browser zu überlassen
 * (`<object>`/`<iframe>`). Das funktioniert für genau ein Dokument pro Seite
 * zuverlässig; jedes weitere blieb eine schwarze Fläche, unabhängig davon,
 * ob es dieselbe Datei war, ob das erste vorher ausgehängt wurde oder ob der
 * Rahmen neu erzeugt wurde. Dazu kommt: Auf dem iPhone zeigt der eingebettete
 * Betrachter nur die erste Seite, und optisch ist es eine graue Werkzeugleiste
 * aus einem anderen Programm mitten in der Arbeit.
 *
 * Also werden die Seiten hier selbst gezeichnet. Das kostet einmalig eine
 * Bibliothek, die nur geladen wird, wenn auf der Seite wirklich ein PDF
 * steht – dafür sieht es überall gleich aus, es lassen sich beliebig viele
 * Dokumente zeigen, und die Seiten liegen als weiße Blätter auf der Fläche
 * wie die Print-Arbeiten sonst auch.
 *
 * Gezeichnet wird erst, wenn eine Seite in die Nähe des Sichtfelds kommt –
 * ein zwanzigseitiges Dokument würde sonst beim Aufbau der Seite jeden
 * Rechner ausbremsen.
 */

/** Breite, mit der eine Seite gerastert wird. Darüber wird nichts mehr besser. */
const MAX_BREITE = 1600;

type PdfSeite = {
  getViewport: (optionen: { scale: number }) => { width: number; height: number };
  render: (optionen: { canvasContext: CanvasRenderingContext2D; viewport: unknown }) => { promise: Promise<void> };
};

type PdfDokument = { numPages: number; getPage: (nummer: number) => Promise<PdfSeite> };

let bibliothek: Promise<{ getDocument: (o: unknown) => { promise: Promise<PdfDokument> } }> | null = null;

/**
 * Die Bibliothek wird einmal geladen und dann geteilt.
 *
 * Der Arbeiter (Worker) rendert in einem eigenen Strang, damit die Seite
 * beim Blättern nicht stockt. Astro packt ihn beim Bauen mit ein; die
 * `?url`-Angabe liefert die fertige Adresse dazu.
 */
async function ladeBibliothek() {
  bibliothek ??= (async () => {
    const [pdfjs, workerUrl] = await Promise.all([
      import('pdfjs-dist'),
      import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
    ]);

    pdfjs.GlobalWorkerOptions.workerSrc = workerUrl.default;

    return pdfjs as unknown as { getDocument: (o: unknown) => { promise: Promise<PdfDokument> } };
  })();

  return bibliothek;
}

function hinweis(rahmen: HTMLElement, text: string, url: string): void {
  rahmen.innerHTML = `
    <p class="p-10 text-center text-sm text-muted">
      ${text}
      <a href="${url}" target="_blank" rel="noopener" class="link-underline text-accent">In neuem Tab öffnen</a>
    </p>`;
}

async function zeige(rahmen: HTMLElement): Promise<void> {
  const url = rahmen.dataset.pdf ?? '';
  if (url === '') return;

  let doc: PdfDokument;
  try {
    const pdfjs = await ladeBibliothek();
    doc = await pdfjs.getDocument({ url }).promise;
  } catch {
    hinweis(rahmen, 'Das Dokument ließ sich nicht laden.', url);

    return;
  }

  rahmen.innerHTML = '';
  rahmen.dataset.seiten = String(doc.numPages);

  // Zuerst alle Plätze anlegen – mit dem richtigen Seitenverhältnis, damit
  // beim Nachzeichnen nichts springt.
  const beobachter = new IntersectionObserver(
    (eintraege) => {
      eintraege
        .filter((eintrag) => eintrag.isIntersecting)
        .forEach((eintrag) => {
          beobachter.unobserve(eintrag.target);
          void zeichne(eintrag.target as HTMLCanvasElement, doc);
        });
    },
    // Der Rahmen scrollt selbst – ohne `root` würde gegen das Fenster
    // gemessen, und die letzten Seiten blieben leer.
    { root: rahmen, rootMargin: '800px 0px' },
  );

  for (let nummer = 1; nummer <= doc.numPages; nummer++) {
    const seite = await doc.getPage(nummer);
    const mass = seite.getViewport({ scale: 1 });

    const blatt = document.createElement('figure');
    blatt.className = 'pdf-blatt';

    const canvas = document.createElement('canvas');
    canvas.dataset.seite = String(nummer);
    canvas.style.aspectRatio = `${mass.width} / ${mass.height}`;
    canvas.className = 'block h-auto w-full';

    const nummerierung = document.createElement('figcaption');
    nummerierung.className = 'pdf-nummer';
    nummerierung.textContent = `${nummer} / ${doc.numPages}`;

    blatt.append(canvas, nummerierung);
    rahmen.append(blatt);
    beobachter.observe(canvas);
  }
}

async function zeichne(canvas: HTMLCanvasElement, doc: PdfDokument): Promise<void> {
  const nummer = Number(canvas.dataset.seite ?? 1);
  const seite = await doc.getPage(nummer);

  // Auf die tatsächliche Darstellungsbreite rastern, mindestens aber so
  // scharf, dass Text auf einem hochauflösenden Bildschirm nicht ausfranst.
  const breite = Math.min(MAX_BREITE, Math.max(700, canvas.clientWidth * Math.min(2, window.devicePixelRatio || 1)));
  const eins = seite.getViewport({ scale: 1 });
  const viewport = seite.getViewport({ scale: breite / eins.width });

  canvas.width = Math.round(viewport.width);
  canvas.height = Math.round(viewport.height);

  const kontext = canvas.getContext('2d');
  if (!kontext) return;

  await seite.render({ canvasContext: kontext, viewport }).promise;
  canvas.dataset.fertig = '';
}

export function initPdfViewer(root: ParentNode = document): void {
  root.querySelectorAll<HTMLElement>('[data-pdf]').forEach((rahmen) => {
    if (rahmen.dataset.pdfBereit === '') return;
    rahmen.dataset.pdfBereit = '';
    void zeige(rahmen);
  });
}
