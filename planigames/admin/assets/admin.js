/* PLANIGAMES Admin — Listen/Blöcke verwalten, Uploads, Summaries. */
(() => {
  "use strict";
  let uid = Date.now() % 1000000;
  const csrf = () => (document.querySelector('input[name="csrf"]') || {}).value || "";

  const itemsBox = (c) => c.querySelector(":scope > .list-items, :scope > [data-items]");

  function addListItem(list) {
    const tpl = list.querySelector(":scope > template[data-tpl]");
    if (!tpl) return;
    const token = list.dataset.token;
    const html = tpl.innerHTML.split(token).join("n" + uid++);
    itemsBox(list).insertAdjacentHTML("beforeend", html);
    const added = itemsBox(list).lastElementChild;
    focusFirst(added);
  }

  function addBlock(blocks) {
    const type = (blocks.querySelector("[data-block-select]") || {}).value;
    const tpl = blocks.querySelector(':scope > template[data-block-tpl="' + type + '"]');
    if (!tpl) return;
    const token = blocks.dataset.token;
    const html = tpl.innerHTML.split(token).join("n" + uid++);
    itemsBox(blocks).insertAdjacentHTML("beforeend", html);
    const added = itemsBox(blocks).lastElementChild;
    focusFirst(added);
  }

  function focusFirst(item) {
    if (!item) return;
    item.classList.remove("folded");
    const inp = item.querySelector("input[type=text], textarea, select");
    if (inp) inp.focus();
  }

  function updateSummary(item) {
    const sum = item.querySelector(":scope > .item-bar [data-sum]");
    if (!sum || sum.classList.contains("block-label")) return;
    const inp = item.querySelector(".item-body input[type=text], .item-body textarea");
    if (inp) sum.textContent = inp.value;
  }

  // ---- Klicks (Delegation) ----
  document.addEventListener("click", (e) => {
    const t = e.target;
    if (t.closest("[data-add]")) { addListItem(t.closest("[data-list]")); e.preventDefault(); return; }
    if (t.closest("[data-add-block]")) { addBlock(t.closest("[data-blocks]")); e.preventDefault(); return; }

    const item = t.closest("[data-item]");
    if (!item) return;
    if (t.closest("[data-del]")) {
      if (item.querySelector(".item-body input, .item-body textarea, .item-body select") &&
          !confirm("Diesen Eintrag wirklich löschen?")) return;
      item.remove(); e.preventDefault(); return;
    }
    if (t.closest("[data-up]")) { const p = item.previousElementSibling; if (p) item.parentNode.insertBefore(item, p); e.preventDefault(); return; }
    if (t.closest("[data-down]")) { const n = item.nextElementSibling; if (n) item.parentNode.insertBefore(n, item); e.preventDefault(); return; }
    if (t.closest("[data-dup]")) { duplicateItem(item); e.preventDefault(); return; }
    if (t.closest("[data-fold]")) { item.classList.toggle("folded"); e.preventDefault(); return; }
  });

  // ---- Eintrag/Block duplizieren (mit allen Werten, neuer Index) ----
  function duplicateItem(item) {
    const list = item.closest("[data-list], [data-blocks]");
    if (!list) return;
    const base = list.dataset.name;
    if (!base) return;
    // Aktuellen Namens-Index dieses Items ermitteln
    let idx = null;
    for (const el of item.querySelectorAll("[name]")) {
      const n = el.getAttribute("name");
      if (n.indexOf(base + "[") === 0) {
        const m = n.slice(base.length).match(/^\[([^\]]+)\]/);
        if (m) { idx = m[1]; break; }
      }
    }
    if (idx === null) return;
    const oldPrefix = base + "[" + idx + "]";
    const newPrefix = base + "[n" + uid++ + "]";

    const clone = item.cloneNode(true);
    // Werte 1:1 übernehmen (cloneNode kopiert getippte Werte nicht zuverlässig)
    const origFields = item.querySelectorAll("input, textarea, select");
    const cloneFields = clone.querySelectorAll("input, textarea, select");
    cloneFields.forEach((cf, i) => {
      const of = origFields[i];
      if (of) {
        if (cf.type === "checkbox" || cf.type === "radio") cf.checked = of.checked;
        else cf.value = of.value;
      }
      // Namens-Index neu setzen, damit nichts kollidiert
      const nm = cf.getAttribute("name");
      if (nm && nm.indexOf(oldPrefix) === 0) cf.setAttribute("name", newPrefix + nm.slice(oldPrefix.length));
    });
    clone.classList.remove("folded");
    item.after(clone);
    updateSummary(clone);
  }

  // ---- Sortieren per Griff (⠿) — Pointer-basiert (Maus & Touch), zuverlässig
  //      auch wenn die Items Formularfelder enthalten (HTML5-DnD versagt da oft) ----
  let drag = null;
  function itemSiblings(container, item) {
    return Array.prototype.filter.call(container.children, (c) => c !== item && c.matches && c.matches("[data-item]"));
  }
  document.addEventListener("pointerdown", (e) => {
    if (e.button != null && e.button !== 0) return;       // nur linke Maustaste
    const grip = e.target.closest("[data-grip]");
    if (!grip) return;
    const item = grip.closest("[data-item]");
    if (!item || !item.parentNode) return;
    e.preventDefault();
    drag = { item, container: item.parentNode, moved: false };
    item.classList.add("dragging");
    document.body.classList.add("is-dragging");
  });
  document.addEventListener("pointermove", (e) => {
    if (!drag) return;
    e.preventDefault();
    drag.moved = true;
    const { item, container } = drag;
    const sibs = itemSiblings(container, item);
    let placed = false;
    for (const sib of sibs) {
      const r = sib.getBoundingClientRect();
      if (e.clientY < r.top + r.height / 2) { container.insertBefore(item, sib); placed = true; break; }
    }
    if (!placed) container.appendChild(item);             // unterhalb aller -> ans Ende
  });
  function endDrag() {
    if (!drag) return;
    drag.item.classList.remove("dragging");
    document.body.classList.remove("is-dragging");
    drag = null;
  }
  document.addEventListener("pointerup", endDrag);
  document.addEventListener("pointercancel", endDrag);

  // ---- Summary live aktualisieren ----
  document.addEventListener("input", (e) => {
    const item = e.target.closest("[data-item]");
    if (item) updateSummary(item);
  });

  // ---- Datei-Upload ----
  document.addEventListener("change", async (e) => {
    const fileInput = e.target.closest("[data-media-file]");
    if (!fileInput || !fileInput.files || !fileInput.files[0]) return;
    const wrap = fileInput.closest("[data-media]");
    const textInput = wrap.querySelector("[data-media-input]");
    const status = wrap.querySelector(".media-status");
    const file = fileInput.files[0];
    status.textContent = "Lädt… (" + Math.round(file.size / 1024) + " KB)";
    const fd = new FormData();
    fd.append("csrf", csrf());
    fd.append("file", file);
    try {
      const r = await fetch("index.php?action=upload", { method: "POST", body: fd });
      const j = await r.json();
      if (j.error) { status.innerHTML = '<span class="up-err">' + j.error + "</span>"; }
      else {
        textInput.value = j.path;
        status.innerHTML = '<a href="' + j.path + '" target="_blank" class="media-prev">' + j.path + "</a>";
      }
    } catch (err) {
      status.innerHTML = '<span class="up-err">Upload-Fehler</span>';
    }
    fileInput.value = "";
  });

  // Startzustand: alle Einträge eingeklappt lassen? Nein – aufgeklappt, aber lange Listen klappen wir zu.
  document.querySelectorAll("[data-items]").forEach((box) => {
    if (box.children.length > 4) box.querySelectorAll(":scope > [data-item]").forEach((i) => i.classList.add("folded"));
  });

  // ---- Suche/Filter in langen Listen (ab 5 Einträgen) ----
  document.querySelectorAll("[data-list], [data-blocks]").forEach((list) => {
    const box = list.querySelector(":scope > [data-items]");
    const head = list.querySelector(":scope > .listhead");
    if (!box || !head || box.children.length < 5) return;
    const search = document.createElement("input");
    search.type = "search";
    search.className = "list-search";
    search.placeholder = "Suchen / filtern …";
    head.after(search);
    const apply = () => {
      const q = search.value.trim().toLowerCase();
      box.querySelectorAll(":scope > [data-item]").forEach((it) => {
        if (!q) { it.style.display = ""; return; }
        const sum = (it.querySelector(":scope > .item-bar [data-sum]")?.textContent || "");
        const vals = Array.from(it.querySelectorAll("input, textarea, select")).map((f) => f.value || "").join(" ");
        it.style.display = (sum + " " + vals).toLowerCase().includes(q) ? "" : "none";
      });
    };
    search.addEventListener("input", apply);
  });

  // ---- Admin-Topbar: Mobile-Burger ----
  const tbBurger = document.getElementById("tb-burger");
  if (tbBurger) {
    const topbar = tbBurger.closest(".topbar");
    tbBurger.addEventListener("click", () => {
      const open = topbar.classList.toggle("nav-open");
      tbBurger.setAttribute("aria-expanded", String(open));
    });
  }

  // ---- Medien-Bibliothek: Pfad kopieren ----
  document.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-copy]");
    if (!btn) return;
    const path = btn.getAttribute("data-copy");
    const done = () => {
      const t = document.getElementById("media-copied");
      if (t) { t.classList.add("show"); setTimeout(() => t.classList.remove("show"), 1400); }
    };
    if (navigator.clipboard) navigator.clipboard.writeText(path).then(done).catch(() => fallbackCopy(path, done));
    else fallbackCopy(path, done);
  });
  function fallbackCopy(text, cb) {
    const ta = document.createElement("textarea");
    ta.value = text; ta.style.position = "fixed"; ta.style.opacity = "0";
    document.body.appendChild(ta); ta.select();
    try { document.execCommand("copy"); cb(); } catch {}
    ta.remove();
  }

  // ---- Mail-Dashboard: Live-Vorschau ----
  const compose = document.querySelector("[data-mail-compose]");
  if (compose) {
    const mode = compose.getAttribute("data-mode") || "compose";
    const input = compose.querySelector("[data-mail-input]");
    const frame = compose.querySelector("[data-mail-frame]");
    let timer = null, busy = false, pending = false;
    async function render() {
      if (busy) { pending = true; return; }
      busy = true;
      const fd = new FormData();
      fd.append("csrf", csrf());
      fd.append("mode", mode);
      fd.append("message", input ? input.value : "");
      try {
        const r = await fetch("mail.php?action=preview", { method: "POST", body: fd });
        frame.srcdoc = await r.text();
      } catch {}
      busy = false;
      if (pending) { pending = false; render(); }
    }
    if (input) input.addEventListener("input", () => { clearTimeout(timer); timer = setTimeout(render, 350); });
    render();
  }

  // ---- Medien-Bibliothek: direkter Upload ----
  const libUp = document.getElementById("lib-upload");
  if (libUp) {
    libUp.addEventListener("change", async () => {
      if (!libUp.files || !libUp.files[0]) return;
      const status = document.getElementById("lib-upload-status");
      const file = libUp.files[0];
      status.textContent = "Lädt… (" + Math.round(file.size / 1024) + " KB)";
      const fd = new FormData();
      fd.append("csrf", csrf());
      fd.append("file", file);
      try {
        const r = await fetch("index.php?action=upload", { method: "POST", body: fd });
        const j = await r.json();
        if (j.error) { status.innerHTML = '<span class="up-err">' + j.error + "</span>"; }
        else { status.textContent = "✓ Hochgeladen — Seite wird aktualisiert…"; location.reload(); }
      } catch { status.innerHTML = '<span class="up-err">Upload-Fehler</span>'; }
    });
  }

  // ---- Tastatur-Shortcuts ----
  function primarySaveButton() {
    return document.querySelector('#editor button[name="save"], button[name="save"], form .btn-primary[type="submit"], form button.btn-primary');
  }
  document.addEventListener("keydown", (e) => {
    // Strg/Cmd + S = Speichern
    if ((e.ctrlKey || e.metaKey) && (e.key === "s" || e.key === "S")) {
      const btn = primarySaveButton();
      if (btn) { e.preventDefault(); btn.click(); }
      return;
    }
    // ? = Hilfe-Overlay (nur außerhalb von Eingabefeldern)
    const inField = /^(INPUT|TEXTAREA|SELECT)$/.test((e.target.tagName || ""));
    if (e.key === "?" && !inField) { e.preventDefault(); toggleShortcutHelp(); return; }
    if (e.key === "Escape") {
      const help = document.getElementById("kbd-help");
      if (help) { help.remove(); return; }
      const tb = document.querySelector(".topbar.nav-open");
      if (tb) tb.classList.remove("nav-open");
    }
  });
  function toggleShortcutHelp() {
    const ex = document.getElementById("kbd-help");
    if (ex) { ex.remove(); return; }
    const d = document.createElement("div");
    d.id = "kbd-help";
    d.innerHTML = `<div class="kbd-box">
      <h3>Tastatur-Shortcuts</h3>
      <div class="kbd-row"><span><kbd>Strg</kbd>/<kbd>⌘</kbd> + <kbd>S</kbd></span><span>Speichern</span></div>
      <div class="kbd-row"><span><kbd>?</kbd></span><span>Diese Hilfe</span></div>
      <div class="kbd-row"><span><kbd>Esc</kbd></span><span>Schließen / Menü zu</span></div>
      <button class="btn-add" data-kbd-close>Schließen</button>
    </div>`;
    d.addEventListener("click", (e) => { if (e.target === d || e.target.closest("[data-kbd-close]")) d.remove(); });
    document.body.appendChild(d);
  }
})();
