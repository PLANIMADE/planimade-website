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

  // Größenangabe & Optimierungs-Hinweis
  function fmtKB(b) {
    if (b >= 1048576) return (b / 1048576).toFixed(1).replace(".", ",") + " MB";
    return Math.max(1, Math.round(b / 1024)) + " KB";
  }
  function optNote(j) {
    if (!j || !j.optimized) return "";
    const o = j.optimized;
    const saved = o.before > 0 ? Math.round((1 - o.after / o.before) * 100) : 0;
    return ' <span class="opt-note">· optimiert ' + fmtKB(o.before) + " → " + fmtKB(o.after) + (saved > 0 ? " (−" + saved + "%)" : "") + "</span>";
  }

  // ---- „Aus Bibliothek wählen" (Medien-Picker) ----
  let _pickTarget = null;
  document.addEventListener("click", async (e) => {
    const pick = e.target.closest("[data-media-pick]");
    if (pick) {
      _pickTarget = pick.closest("[data-media]")?.querySelector("[data-media-input]");
      openMediaPicker();
      return;
    }
    // Auswahl einer Datei im Picker
    const tile = e.target.closest("[data-pick-path]");
    if (tile && _pickTarget) {
      _pickTarget.value = tile.getAttribute("data-pick-path");
      _pickTarget.dispatchEvent(new Event("input", { bubbles: true }));
      const wrap = _pickTarget.closest("[data-media]");
      const st = wrap && wrap.querySelector(".media-status");
      if (st) st.innerHTML = '<a href="' + _pickTarget.value + '" target="_blank" class="media-prev">' + _pickTarget.value + "</a>";
      closeMediaPicker();
      return;
    }
    if (e.target.closest("[data-pick-close]") || e.target.id === "media-picker") closeMediaPicker();
  });
  function closeMediaPicker() { const m = document.getElementById("media-picker"); if (m) m.remove(); }
  async function openMediaPicker() {
    closeMediaPicker();
    const ov = document.createElement("div");
    ov.id = "media-picker";
    ov.innerHTML = `<div class="mp-box"><div class="mp-head"><span>Aus der Medien-Bibliothek wählen</span>
      <button type="button" class="kb-modal-x" data-pick-close aria-label="Schließen">✕</button></div>
      <div class="mp-grid" data-pick-grid><p class="muted" style="padding:1rem">Lädt…</p></div></div>`;
    document.body.appendChild(ov);
    try {
      const r = await fetch("index.php?action=medialist");
      const j = await r.json();
      const grid = ov.querySelector("[data-pick-grid]");
      const files = (j && j.files) || [];
      grid.innerHTML = files.length
        ? files.map((f) => `<button type="button" class="mp-tile" data-pick-path="${f.path}" title="${f.name}">
            ${f.img ? `<img src="${f.path}" alt="" loading="lazy">` : `<span class="mp-file">📄</span>`}
            <span class="mp-name">${f.name}</span></button>`).join("")
        : '<p class="muted" style="padding:1rem">Noch keine Dateien hochgeladen.</p>';
    } catch { ov.querySelector("[data-pick-grid]").innerHTML = '<p class="up-err" style="padding:1rem">Konnte Liste nicht laden.</p>'; }
  }

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
        status.innerHTML = '<a href="' + j.path + '" target="_blank" class="media-prev">' + j.path + "</a>" + optNote(j);
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

  // ---- Editor: einklappbare Objekt-Sektionen ----
  document.addEventListener("click", (e) => {
    const head = e.target.closest("[data-objtoggle]");
    if (!head) return;
    const box = head.closest("[data-objfold]");
    if (!box) return;
    const folded = box.classList.toggle("folded");
    head.setAttribute("aria-expanded", String(!folded));
  });

  // ---- Admin-Topbar: aufklappbare Gruppen (Community / System / Glocke) ----
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".tb-group-btn");
    const group = btn ? btn.closest("[data-tb-group]") : null;
    // Klick im offenen Panel (kein Button) → offen lassen
    if (!btn && e.target.closest("[data-tb-group]")) return;
    // andere offene Gruppen schließen
    document.querySelectorAll("[data-tb-group].open").forEach((g) => {
      if (g !== group) { g.classList.remove("open"); const b = g.querySelector(".tb-group-btn"); if (b) b.setAttribute("aria-expanded", "false"); }
    });
    if (group) {
      const open = group.classList.toggle("open");
      btn.setAttribute("aria-expanded", String(open));
    }
  });

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

    // Schnellantwort-Vorlage einfügen
    const tplBtn = compose.querySelector("[data-tpl-insert]");
    const tplSel = compose.querySelector("[data-tpl-select]");
    const tplData = compose.querySelector("[data-tpl-data]");
    if (tplBtn && tplSel && tplData && input) {
      let bodies = [];
      try { bodies = JSON.parse(tplData.textContent || "[]"); } catch {}
      tplBtn.addEventListener("click", () => {
        const i = tplSel.value;
        if (i === "" || !bodies[i]) return;
        const ins = bodies[i];
        const pos = input.selectionStart ?? input.value.length;
        const before = input.value.slice(0, pos);
        const sep = before === "" || before.endsWith("\n") ? "" : "\n";
        input.value = before + sep + ins + input.value.slice(pos);
        input.dispatchEvent(new Event("input", { bubbles: true }));
        input.focus();
      });
    }
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
        else { status.innerHTML = "✓ Hochgeladen" + optNote(j) + " — Seite wird aktualisiert…"; setTimeout(() => location.reload(), j.optimized ? 1100 : 200); }
      } catch { status.innerHTML = '<span class="up-err">Upload-Fehler</span>'; }
    });
  }

  // ---- Planungs-Board: Drag & Drop zwischen Spalten ----
  const board = document.querySelector("[data-board]");
  if (board) {
    let drag = null, ph = null, startX = 0, startY = 0, moved = false, origStyle = "", offX = 0, offY = 0, w = 0;
    function afterElement(container, y) {
      const els = [...container.querySelectorAll(".kb-card:not(.kb-dragging)")];
      let best = null, bestOff = -Infinity;
      for (const child of els) {
        const box = child.getBoundingClientRect();
        const off = y - box.top - box.height / 2;
        if (off < 0 && off > bestOff) { bestOff = off; best = child; }
      }
      return best;
    }
    board.addEventListener("pointerdown", (e) => {
      const grip = e.target.closest("[data-kb-grip]");
      if (!grip) return;
      const card = grip.closest(".kb-card");
      if (!card) return;
      e.preventDefault();
      drag = card; moved = false; startX = e.clientX; startY = e.clientY;
      origStyle = card.getAttribute("style") || "";
      const r = card.getBoundingClientRect();
      offX = e.clientX - r.left; offY = e.clientY - r.top; w = r.width;
      try { grip.setPointerCapture(e.pointerId); } catch {}
    });
    board.addEventListener("pointermove", (e) => {
      if (!drag) return;
      if (!moved) {
        if (Math.abs(e.clientX - startX) < 4 && Math.abs(e.clientY - startY) < 4) return;
        moved = true;
        ph = document.createElement("div");
        ph.className = "kb-ph";
        ph.style.height = drag.offsetHeight + "px";
        drag.parentNode.insertBefore(ph, drag);
        drag.classList.add("kb-dragging");
        drag.style.cssText = origStyle + ";position:fixed;width:" + w + "px;z-index:999;pointer-events:none;margin:0";
        document.body.classList.add("is-dragging");
      }
      drag.style.left = (e.clientX - offX) + "px";
      drag.style.top = (e.clientY - offY) + "px";
      drag.style.visibility = "hidden";
      const el = document.elementFromPoint(e.clientX, e.clientY);
      drag.style.visibility = "";
      const cards = el && el.closest("[data-kb-cards]");
      if (cards) {
        const after = afterElement(cards, e.clientY);
        if (after == null) cards.appendChild(ph); else cards.insertBefore(ph, after);
      }
    });
    function endDrag() {
      if (!drag) return;
      if (moved && ph) {
        ph.parentNode.insertBefore(drag, ph);
        ph.remove();
        drag.classList.remove("kb-dragging");
        if (origStyle) drag.setAttribute("style", origStyle); else drag.removeAttribute("style");
        document.body.classList.remove("is-dragging");
        boardSave();
      }
      drag = null; ph = null; moved = false;
    }
    board.addEventListener("pointerup", endDrag);
    board.addEventListener("pointercancel", endDrag);
    function boardSave() {
      const order = [...board.querySelectorAll("[data-col-id]")].map((c) => ({
        col: c.getAttribute("data-col-id"),
        cards: [...c.querySelectorAll(".kb-card")].map((k) => k.getAttribute("data-card-id")),
      }));
      const fd = new URLSearchParams();
      fd.append("csrf", csrf());
      fd.append("order", JSON.stringify(order));
      fetch("index.php?action=board_save", { method: "POST", body: fd }).catch(() => {});
    }
  }

  // ---- Board: Karten-Editor-Modal schließen (✕, Backdrop, Esc) ----
  document.addEventListener("click", (e) => {
    if (e.target.closest("[data-kb-close]")) {
      const ed = e.target.closest(".kb-cardedit");
      if (ed) ed.open = false;
      return;
    }
    // Klick auf den Backdrop (offenes details, aber außerhalb des Formulars)
    const openEd = e.target.closest(".kb-cardedit[open]");
    if (openEd && !e.target.closest(".kb-cardform")) openEd.open = false;
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") document.querySelectorAll(".kb-cardedit[open]").forEach((d) => { d.open = false; });
  });

  // ---- Board: Checklisten-Punkt umschalten (AJAX) ----
  document.addEventListener("click", async (e) => {
    const item = e.target.closest("[data-chk-card]");
    if (!item) return;
    e.preventDefault();
    const card = item.getAttribute("data-chk-card"), i = item.getAttribute("data-chk-i");
    const fd = new URLSearchParams();
    fd.append("csrf", csrf()); fd.append("card", card); fd.append("i", i);
    try {
      const r = await fetch("index.php?action=board_toggle", { method: "POST", body: fd });
      const j = await r.json();
      if (!j.ok) return;
      const on = item.classList.toggle("done");
      item.querySelector(".kb-chk-box").textContent = on ? "✓" : "";
      const cardEl = item.closest(".kb-card");
      const bar = cardEl.querySelector(".kb-chk-bar span");
      const cnt = cardEl.querySelector(".kb-chk-count");
      if (bar) bar.style.width = (j.total ? Math.round(j.done / j.total * 100) : 0) + "%";
      if (cnt) cnt.textContent = j.done + "/" + j.total;
    } catch {}
  });

  // ---- Board: Label-Filter ----
  const kbFilter = document.querySelector("[data-kb-filter]");
  if (kbFilter) {
    kbFilter.addEventListener("click", (e) => {
      const tag = e.target.closest("[data-label]");
      if (!tag) return;
      kbFilter.querySelectorAll(".kb-filter-tag").forEach((t) => t.classList.remove("on"));
      tag.classList.add("on");
      const want = tag.getAttribute("data-label");
      document.querySelectorAll(".board .kb-card").forEach((c) => {
        const labels = (c.getAttribute("data-labels") || "").split("|").filter(Boolean);
        c.style.display = (!want || labels.includes(want)) ? "" : "none";
      });
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
      const grp = document.querySelector("[data-tb-group].open");
      if (grp) { grp.classList.remove("open"); return; }
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
