/* PLANIGAMES Admin — Listen/Blöcke verwalten, Uploads, Summaries. */
(() => {
  "use strict";
  let uid = Date.now() % 1000000;
  // Sicherheits-Token: steht auf jeder Admin-Seite im <head> (auch dort, wo es kein Formular gibt)
  const csrf = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || (document.querySelector('input[name="csrf"]') || {}).value || "";
  // Antwort sicher als JSON lesen – bei Server-Fehlern verständliche Meldung statt „not valid JSON“
  async function readJson(r) {
    const t = await r.text();
    try { return JSON.parse(t); } catch (e) {
      const msg = t.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim().slice(0, 160);
      return { ok: false, error: msg || ("Server-Fehler " + r.status) };
    }
  }
  window.pgReadJson = readJson;

  // Admin als installierbare App (Homescreen): Service Worker registrieren (nur über HTTPS).
  if ("serviceWorker" in navigator && location.protocol === "https:") {
    addEventListener("load", () => navigator.serviceWorker.register("sw.js").catch(() => {}));
  }

  const itemsBox = (c) => c.querySelector(":scope > .list-items, :scope > [data-items]");

  function addListItem(list) {
    const tpl = list.querySelector(":scope > template[data-tpl]");
    if (!tpl) return;
    const token = list.dataset.token;
    const html = tpl.innerHTML.split(token).join("n" + uid++);
    itemsBox(list).insertAdjacentHTML("beforeend", html);
    const added = itemsBox(list).lastElementChild;
    focusFirst(added);
    injectLogoPlacers();   // neu hinzugefügte Spiele bekommen ihren „Logo platzieren“-Button
    if (window.pgSyncShowIf) window.pgSyncShowIf();
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
    if (window.pgSyncShowIf) window.pgSyncShowIf();
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
    // Tippen auf die Kopfzeile (Zusammenfassung) klappt auch auf/zu – große Trefferfläche fürs Handy
    if (t.closest(".item-bar") && !t.closest("[data-grip], button, input, select, a, label")) {
      item.classList.toggle("folded"); e.preventDefault();
    }
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
      const j = await readJson(r);
      const grid = ov.querySelector("[data-pick-grid]");
      const files = (j && j.files) || [];
      grid.innerHTML = files.length
        ? files.map((f) => `<button type="button" class="mp-tile" data-pick-path="${f.path}" title="${f.name}">
            ${f.img ? `<img src="${f.path}" alt="" loading="lazy">` : `<span class="mp-file">📄</span>`}
            <span class="mp-name">${f.name}</span></button>`).join("")
        : '<p class="muted" style="padding:1rem">Noch keine Dateien hochgeladen.</p>';
    } catch { ov.querySelector("[data-pick-grid]").innerHTML = '<p class="up-err" style="padding:1rem">Konnte Liste nicht laden.</p>'; }
  }

  // ---- Benachrichtigungsglocke: Echtzeit-Aktualisierung (Polling) ----
  const notifBell = document.querySelector("[data-notif-bell]");
  if (notifBell) {
    const setBadge = (el, val) => { if (!el) return; if (val > 0) { el.textContent = val; el.hidden = false; } else { el.hidden = true; el.textContent = ""; } };
    let lastTotal = parseInt((document.querySelector("[data-notif-total]") || {}).textContent || "0", 10) || 0;
    async function pollNotifs() {
      try {
        const r = await fetch("index.php?action=notif_counts", { headers: { "X-Requested-With": "fetch" } });
        if (!r.ok) return;
        const j = await readJson(r);
        const c = j.counts || {}, notes = j.notes || {};
        setBadge(document.querySelector("[data-notif-total]"), c.total);
        ["mail", "contacts", "comments", "subs"].forEach((k) => {
          const row = document.querySelector(`[data-nrow="${k}"]`);
          if (!row) return;
          setBadge(row.querySelector("[data-ncount]"), k === "subs" ? 0 : c[k]);
          const note = row.querySelector("[data-nnote]");
          if (note && notes[k]) note.textContent = notes[k];
        });
        // kurzer Puls, wenn neue Meldungen dazukamen
        if (c.total > lastTotal) { notifBell.classList.add("notif-pulse"); setTimeout(() => notifBell.classList.remove("notif-pulse"), 1200); }
        lastTotal = c.total;
      } catch {}
    }
    pollNotifs();                 // sofort beim Start: zeigt neue Mails direkt an (ohne Reload)
    setInterval(pollNotifs, 20000);
    // bei Rückkehr zum Tab sofort aktualisieren
    document.addEventListener("visibilitychange", () => { if (!document.hidden) pollNotifs(); });
  }

  // ---- Board-Karten-Bild: Datei-Feedback (Highlight + Dateiname) ----
  document.addEventListener("change", (e) => {
    const inp = e.target;
    if (!(inp instanceof HTMLInputElement) || inp.type !== "file" || inp.name !== "cardimg") return;
    const lbl = inp.closest("label");
    if (!lbl) return;
    const has = inp.files && inp.files.length > 0;
    lbl.classList.toggle("has-file", has);
    if (lbl.classList.contains("kb-img-pick")) {
      let tag = lbl.parentElement.querySelector(".kb-img-name");
      if (!tag) { tag = document.createElement("span"); tag.className = "kb-img-name"; lbl.insertAdjacentElement("afterend", tag); }
      tag.textContent = has ? "📎 " + inp.files[0].name : "";
    }
  });

  // ---- Bild-Fokuspunkt (Bildausschnitt für Cover) ----
  document.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-media-focus]");
    if (!btn) return;
    const input = btn.closest("[data-media]")?.querySelector("[data-media-input]");
    if (!input || !input.value.trim()) { alert("Bitte zuerst ein Bild hochladen oder auswählen."); return; }
    openFocusPicker(input);
  });
  function parseFocus(val) {
    const m = String(val || "").match(/#focus=(\d+),(\d+)/);
    return m ? { x: +m[1], y: +m[2] } : { x: 50, y: 50 };
  }
  function openFocusPicker(input) {
    const src = input.value.split("#")[0];
    let { x, y } = parseFocus(input.value);
    const ov = document.createElement("div");
    ov.id = "media-picker";
    ov.innerHTML = `<div class="mp-box" style="max-width:560px"><div class="mp-head"><span>Fokuspunkt setzen</span>
        <button type="button" class="kb-modal-x" data-focus-close aria-label="Schließen">✕</button></div>
      <div style="padding:1rem">
        <p class="muted" style="margin:0 0 .7rem;font-size:.85rem">Klicke auf den wichtigsten Bildbereich – er bleibt beim Zuschneiden (z. B. als Cover) immer sichtbar.</p>
        <div class="focus-stage" data-focus-stage><img src="${src}" alt="" draggable="false"><span class="focus-dot" data-focus-dot></span></div>
        <div class="kb-cardform-foot" style="margin-top:1rem"><button type="button" class="btn-primary" data-focus-save>Übernehmen</button>
          <button type="button" class="btn-add" data-focus-reset>Mittig</button></div>
      </div></div>`;
    document.body.appendChild(ov);
    const stage = ov.querySelector("[data-focus-stage]");
    const dot = ov.querySelector("[data-focus-dot]");
    const place = () => { dot.style.left = x + "%"; dot.style.top = y + "%"; };
    place();
    const setFromEvent = (ev) => {
      const r = stage.getBoundingClientRect();
      x = Math.round(Math.min(100, Math.max(0, ((ev.clientX - r.left) / r.width) * 100)));
      y = Math.round(Math.min(100, Math.max(0, ((ev.clientY - r.top) / r.height) * 100)));
      place();
    };
    stage.addEventListener("click", setFromEvent);
    ov.querySelector("[data-focus-reset]").addEventListener("click", () => { x = 50; y = 50; place(); });
    ov.querySelector("[data-focus-save]").addEventListener("click", () => {
      input.value = src + (x === 50 && y === 50 ? "" : `#focus=${x},${y}`);
      input.dispatchEvent(new Event("input", { bubbles: true }));
      ov.remove();
    });
    const close = (ev) => { if (ev.target === ov || ev.target.closest("[data-focus-close]")) ov.remove(); };
    ov.addEventListener("click", close);
  }

  // ---- Maskottchen-Wackelaugen: visueller Platzierer ----
  function injectEyesPlacer() {
    const lx = document.querySelector('input[name$="[eyes][lx]"]');
    if (!lx) return;
    const body = lx.closest(".object-body");
    if (!body || body.querySelector("[data-eyes-edit]")) return;
    const wrap = document.createElement("div");
    wrap.className = "field";
    wrap.innerHTML = `<button type="button" class="btn-primary" data-eyes-edit>🪄 Augen platzieren</button>
      <p class="hint">Öffnet eine Vorschau deines Maskottchens – zieh die beiden Augen an die richtige Stelle.</p>`;
    body.insertBefore(wrap, body.firstChild);
  }
  injectEyesPlacer();
  injectLogoPlacers();

  // ---- Countdown-Datum: warnen, wenn es in der Vergangenheit liegt ----
  (function countdownDateWarn() {
    const inp = document.querySelector('input[type="datetime-local"][name$="[heroCountdown][date]"]');
    if (!inp) return;
    const note = document.createElement("p");
    note.className = "hint";
    note.style.marginTop = ".4rem";
    inp.insertAdjacentElement("afterend", note);
    const check = () => {
      const v = inp.value;
      if (!v) { note.textContent = ""; return; }
      const t = new Date(v).getTime();
      if (isNaN(t)) { note.textContent = ""; return; }
      if (t <= Date.now()) {
        note.style.color = "#ffb454";
        note.textContent = "⚠️ Dieses Datum liegt in der Vergangenheit – der Countdown zählt dann nicht herunter, sondern zeigt sofort den „nach Ablauf“-Text. Bitte ein Datum in der Zukunft wählen.";
      } else {
        note.style.color = "var(--ok, #5fd07f)";
        const days = Math.ceil((t - Date.now()) / 86400000);
        note.textContent = "✅ Countdown läuft bis " + new Date(t).toLocaleString() + " (noch " + days + " Tage).";
      }
    };
    inp.addEventListener("input", check);
    inp.addEventListener("change", check);
    check();
  })();

  document.addEventListener("click", (e) => {
    if (e.target.closest("[data-eyes-edit]")) openEyesPicker();
  });

  function openEyesPicker() {
    const g = (k) => document.querySelector(`input[name$="[eyes][${k}]"]`);
    const f = { lx: g("lx"), ly: g("ly"), rx: g("rx"), ry: g("ry"), size: g("size") };
    if (!f.lx) return;
    const imgInput = document.querySelector('input[name$="[mascot][image]"]');
    const emojiInput = document.querySelector('input[name$="[mascot][emoji]"]');
    const src = imgInput && imgInput.value.trim() ? imgInput.value.split("#")[0] : "";
    const emoji = (emojiInput && emojiInput.value.trim()) || "🧙";
    let st = { lx: +f.lx.value || 35, ly: +f.ly.value || 42, rx: +f.rx.value || 65, ry: +f.ry.value || 42, size: +f.size.value || 22 };
    const face = src ? `<img src="${src}" alt="" draggable="false">` : `<span class="ep-emoji">${emoji}</span>`;
    const ov = document.createElement("div");
    ov.id = "media-picker";
    ov.innerHTML = `<div class="mp-box" style="max-width:440px"><div class="mp-head"><span>Augen platzieren</span>
        <button type="button" class="kb-modal-x" data-ep-close aria-label="Schließen">✕</button></div>
      <div style="padding:1rem">
        <p class="muted" style="margin:0 0 .7rem;font-size:.85rem">Zieh die beiden Augen auf dein Maskottchen (oder klick auf die Stelle). Die Größe ist relativ zum Bild – die Vorschau entspricht der Live-Ansicht.</p>
        <div class="ep-stage" data-ep-stage>
          <div class="ep-face" data-ep-face>${face}
            <span class="ep-eye" data-eye="l"><span class="ep-pupil"></span></span>
            <span class="ep-eye" data-eye="r"><span class="ep-pupil"></span></span>
          </div>
        </div>
        <label class="ep-size">Augengröße <input type="range" min="8" max="40" value="${st.size}" data-ep-size> <span data-ep-sizeval>${st.size}%</span></label>
        <div class="kb-cardform-foot" style="margin-top:1rem"><button type="button" class="btn-primary" data-ep-save>Übernehmen</button>
          <button type="button" class="btn-add" data-ep-reset>Standard</button></div>
      </div></div>`;
    document.body.appendChild(ov);
    const stage = ov.querySelector("[data-ep-stage]");
    const faceEl = ov.querySelector("[data-ep-face]");
    const eyeL = ov.querySelector('[data-eye="l"]'), eyeR = ov.querySelector('[data-eye="r"]');
    const sizeIn = ov.querySelector("[data-ep-size]"), sizeVal = ov.querySelector("[data-ep-sizeval]");
    function render() {
      [eyeL, eyeR].forEach(el => { el.style.width = st.size + "%"; });
      eyeL.style.left = st.lx + "%"; eyeL.style.top = st.ly + "%";
      eyeR.style.left = st.rx + "%"; eyeR.style.top = st.ry + "%";
      sizeVal.textContent = st.size + "%";
    }
    render();
    let drag = null;
    eyeL.addEventListener("pointerdown", (e) => { e.preventDefault(); drag = "l"; });
    eyeR.addEventListener("pointerdown", (e) => { e.preventDefault(); drag = "r"; });
    // Koordinaten relativ zum BILD (nicht zur Bühne) – damit Vorschau == Live
    const toPct = (ev) => { const r = faceEl.getBoundingClientRect(); return { x: Math.round(Math.min(100, Math.max(0, ((ev.clientX - r.left) / r.width) * 100))), y: Math.round(Math.min(100, Math.max(0, ((ev.clientY - r.top) / r.height) * 100))) }; };
    stage.addEventListener("pointermove", (ev) => { if (!drag) return; const { x, y } = toPct(ev); if (drag === "l") { st.lx = x; st.ly = y; } else { st.rx = x; st.ry = y; } render(); });
    window.addEventListener("pointerup", () => { drag = null; });
    faceEl.addEventListener("click", (ev) => {
      if (ev.target.closest(".ep-eye")) return;
      const { x, y } = toPct(ev);
      if (Math.hypot(x - st.lx, y - st.ly) <= Math.hypot(x - st.rx, y - st.ry)) { st.lx = x; st.ly = y; } else { st.rx = x; st.ry = y; }
      render();
    });
    sizeIn.addEventListener("input", () => { st.size = +sizeIn.value; render(); });
    ov.querySelector("[data-ep-reset]").addEventListener("click", () => { st = { lx: 35, ly: 42, rx: 65, ry: 42, size: 22 }; sizeIn.value = 22; render(); });
    ov.querySelector("[data-ep-save]").addEventListener("click", () => {
      f.lx.value = st.lx; f.ly.value = st.ly; f.rx.value = st.rx; f.ry.value = st.ry; f.size.value = st.size;
      [f.lx, f.ly, f.rx, f.ry, f.size].forEach(i => i.dispatchEvent(new Event("input", { bubbles: true })));
      const en = document.querySelector('input[type="checkbox"][name$="[eyes][enabled]"]');
      if (en && !en.checked) en.checked = true;
      ov.remove();
    });
    ov.addEventListener("click", (ev) => { if (ev.target === ov || ev.target.closest("[data-ep-close]")) ov.remove(); });
  }

  // ---- Game-Logo im Hero frei platzieren ----
  function injectLogoPlacers() {
    if (document.querySelector("[data-open-placer]")) return;   // neue Spiele-Verwaltung hat den WYSIWYG-Platzierer
    document.querySelectorAll('input[name$="[logoPos][x]"]').forEach((xin) => {
      const body = xin.closest(".object-body");
      if (!body || body.querySelector("[data-logo-edit]")) return;
      const wrap = document.createElement("div");
      wrap.className = "field";
      wrap.innerHTML = `<button type="button" class="btn-primary" data-logo-edit>🖼️ Logo platzieren</button>
        <p class="hint">Öffnet eine Hero-Vorschau – zieh das Logo an die gewünschte Stelle. „Standard“ stellt unten links wieder her.</p>`;
      body.insertBefore(wrap, body.firstChild);
    });
  }
  document.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-logo-edit]");
    if (btn) openLogoPicker(btn.closest("[data-item]") || document);
  });
  function openLogoPicker(scope) {
    const q = (sel) => scope.querySelector(sel);
    const fx = q('input[name$="[logoPos][x]"]'), fy = q('input[name$="[logoPos][y]"]');
    const fscale = q('input[name$="[logoScale]"]');
    const logoIn = q('input[name$="[logo]"]'), coverIn = q('input[name$="[cover]"]');
    if (!fx || !fy) return;
    const src = logoIn && logoIn.value.trim() ? logoIn.value.split("#")[0] : "";
    if (!src) { alert("Bitte zuerst ein Logo hochladen oder auswählen."); return; }
    const cover = coverIn && coverIn.value.trim() ? coverIn.value.split("#")[0] : "";
    const clamp = (v) => Math.min(100, Math.max(0, Math.round(v)));
    const valid = (el, d) => (el && el.value !== "" && !isNaN(+el.value)) ? clamp(+el.value) : d;
    const st = { x: valid(fx, 12), y: valid(fy, 82), scale: (fscale && +fscale.value) || 100 };
    const ov = document.createElement("div");
    ov.id = "media-picker";
    ov.innerHTML = `<div class="mp-box" style="max-width:680px"><div class="mp-head"><span>Logo im Hero platzieren</span>
        <button type="button" class="kb-modal-x" data-lp-close aria-label="Schließen">✕</button></div>
      <div style="padding:1rem">
        <p class="muted" style="margin:0 0 .7rem;font-size:.85rem">Zieh das Logo an die gewünschte Stelle (oder klick hin). Größe per Regler. Die Vorschau entspricht ungefähr der Live-Ansicht.</p>
        <div class="logo-stage" data-lp-stage${cover ? ` style="background-image:url('${cover.replace(/"/g, "%22")}')"` : ""}>
          <img class="logo-ghost" data-lp-ghost src="${src}" alt="" draggable="false">
        </div>
        <label class="ep-size">Logo-Größe <input type="range" min="40" max="260" value="${st.scale}" data-lp-size> <span data-lp-sizeval>${st.scale}%</span></label>
        <div class="kb-cardform-foot" style="margin-top:1rem"><button type="button" class="btn-primary" data-lp-save>Übernehmen</button>
          <button type="button" class="btn-add" data-lp-reset>Standard (unten links)</button></div>
      </div></div>`;
    document.body.appendChild(ov);
    const stage = ov.querySelector("[data-lp-stage]");
    const ghost = ov.querySelector("[data-lp-ghost]");
    const sizeIn = ov.querySelector("[data-lp-size]"), sizeVal = ov.querySelector("[data-lp-sizeval]");
    const render = () => {
      ghost.style.left = st.x + "%"; ghost.style.top = st.y + "%";
      ghost.style.height = (20 * st.scale / 100) + "%";
      sizeVal.textContent = st.scale + "%";
    };
    render();
    let drag = false;
    const toPct = (ev) => {
      const r = stage.getBoundingClientRect();
      st.x = clamp(((ev.clientX - r.left) / r.width) * 100);
      st.y = clamp(((ev.clientY - r.top) / r.height) * 100);
      render();
    };
    ghost.addEventListener("pointerdown", (ev) => { ev.preventDefault(); drag = true; });
    stage.addEventListener("pointermove", (ev) => { if (drag) toPct(ev); });
    window.addEventListener("pointerup", () => { drag = false; });
    stage.addEventListener("click", (ev) => { if (ev.target !== ghost) toPct(ev); });
    sizeIn.addEventListener("input", () => { st.scale = +sizeIn.value; render(); });
    ov.querySelector("[data-lp-reset]").addEventListener("click", () => {
      fx.value = ""; fy.value = "";
      [fx, fy].forEach(i => i.dispatchEvent(new Event("input", { bubbles: true })));
      ov.remove();
    });
    ov.querySelector("[data-lp-save]").addEventListener("click", () => {
      fx.value = st.x; fy.value = st.y;
      if (fscale) fscale.value = st.scale;
      [fx, fy, fscale].forEach(i => i && i.dispatchEvent(new Event("input", { bubbles: true })));
      ov.remove();
    });
    ov.addEventListener("click", (ev) => { if (ev.target === ov || ev.target.closest("[data-lp-close]")) ov.remove(); });
  }

  // ---- Markdown-Editor: Toolbar + Live-Vorschau ----
  function enhanceMarkdown(ta) {
    if (ta.dataset.mdReady) return;
    ta.dataset.mdReady = "1";
    const wrap = document.createElement("div");
    wrap.className = "md-wrap";
    ta.parentNode.insertBefore(wrap, ta);
    const bar = document.createElement("div");
    bar.className = "md-bar";
    const tools = [
      ["B", "Fett", () => surround("**", "**")],
      ["I", "Kursiv", () => surround("*", "*")],
      ["H", "Überschrift", () => linePrefix("## ")],
      ["•", "Liste", () => linePrefix("- ")],
      ["❝", "Zitat", () => linePrefix("> ")],
      ["🔗", "Link", () => surround("[", "](https://)")],
      ["🖼️", "Bild einfügen – oder Bild direkt hierher ziehen / einfügen", () => insert("![Beschreibung](/media/…)")],
    ];
    tools.forEach(([label, title, fn]) => {
      const btn = document.createElement("button");
      btn.type = "button"; btn.className = "md-btn"; btn.textContent = label; btn.title = title;
      btn.addEventListener("click", (e) => { e.preventDefault(); fn(); ta.dispatchEvent(new Event("input", { bubbles: true })); });
      bar.appendChild(btn);
    });
    const pvBtn = document.createElement("button");
    pvBtn.type = "button"; pvBtn.className = "md-btn md-pv"; pvBtn.textContent = "👁 Vorschau"; pvBtn.title = "Vorschau umschalten";
    bar.appendChild(pvBtn);
    const prev = document.createElement("div");
    prev.className = "md-preview prose-pg";
    prev.hidden = true;
    wrap.appendChild(bar); wrap.appendChild(ta); wrap.appendChild(prev);
    const renderPv = () => { if (window.marked) prev.innerHTML = window.marked.parse(ta.value || ""); };
    pvBtn.addEventListener("click", () => {
      const show = prev.hidden;
      prev.hidden = !show; ta.style.display = show ? "none" : "";
      pvBtn.classList.toggle("on", show);
      if (show) renderPv();
    });
    ta.addEventListener("input", () => { if (!prev.hidden) renderPv(); });

    function insert(text) {
      const s = ta.selectionStart, e = ta.selectionEnd;
      ta.value = ta.value.slice(0, s) + text + ta.value.slice(e);
      ta.focus(); ta.selectionStart = ta.selectionEnd = s + text.length;
    }
    function surround(before, after) {
      const s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.slice(s, e);
      ta.value = ta.value.slice(0, s) + before + sel + after + ta.value.slice(e);
      ta.focus(); ta.selectionStart = s + before.length; ta.selectionEnd = e + before.length;
    }
    function linePrefix(pfx) {
      const s = ta.selectionStart;
      let ls = ta.value.lastIndexOf("\n", s - 1) + 1;
      ta.value = ta.value.slice(0, ls) + pfx + ta.value.slice(ls);
      ta.focus(); ta.selectionStart = ta.selectionEnd = s + pfx.length;
    }

    // ---- Bild per Drag&Drop oder Einfügen (Zwischenablage) → hochladen & als Markdown einsetzen ----
    async function dropImages(files) {
      const imgs = [...files].filter((f) => f && /^image\//.test(f.type));
      if (!imgs.length) return false;
      for (const file of imgs) {
        const token = "![⏳ lädt #" + Date.now() + Math.floor(Math.random() * 1000) + "]()";
        insert(token + "\n"); ta.dispatchEvent(new Event("input", { bubbles: true }));
        let repl;
        try {
          const fd = new FormData(); fd.append("csrf", csrf()); fd.append("file", file);
          const r = await fetch("index.php?action=upload", { method: "POST", body: fd });
          const j = await readJson(r);
          repl = j && j.path ? "![](" + j.path + ")" : "![⚠️ " + ((j && j.error) || "Upload-Fehler") + "]()";
        } catch (err) { repl = "![⚠️ Upload-Fehler]()"; }
        ta.value = ta.value.replace(token, repl);
        ta.dispatchEvent(new Event("input", { bubbles: true }));
      }
      return true;
    }
    ta.addEventListener("dragover", (e) => {
      if (e.dataTransfer && [...e.dataTransfer.items || []].some((i) => i.kind === "file")) { e.preventDefault(); wrap.classList.add("md-drop"); }
    });
    ta.addEventListener("dragleave", () => wrap.classList.remove("md-drop"));
    ta.addEventListener("drop", (e) => {
      if (e.dataTransfer && [...e.dataTransfer.files].some((f) => /^image\//.test(f.type))) {
        e.preventDefault(); wrap.classList.remove("md-drop"); dropImages(e.dataTransfer.files);
      }
    });
    ta.addEventListener("paste", (e) => {
      const files = [...(e.clipboardData && e.clipboardData.items || [])].filter((i) => i.kind === "file" && /^image\//.test(i.type)).map((i) => i.getAsFile());
      if (files.length) { e.preventDefault(); dropImages(files); }
    });
  }
  document.querySelectorAll("textarea.md").forEach(enhanceMarkdown);
  // neu hinzugefügte/duplizierte Blöcke ebenfalls aufwerten
  document.addEventListener("click", (e) => {
    if (e.target.closest("[data-add],[data-add-block],[data-dup]")) {
      setTimeout(() => document.querySelectorAll("textarea.md").forEach(enhanceMarkdown), 80);
    }
  });

  // ---- Datei-Upload ----
  document.addEventListener("change", async (e) => {
    const fileInput = e.target.closest("[data-media-file]");
    if (!fileInput || !fileInput.files || !fileInput.files[0]) return;
    const wrap = fileInput.closest("[data-media]");
    const textInput = wrap.querySelector("[data-media-input]");
    const status = wrap.querySelector(".media-status");
    let file = fileInput.files[0];
    let trimNote = "";
    // Spiel-Logo: transparenten Rand automatisch entfernen (macht Größe & Platzierung berechenbar)
    if (textInput && textInput.name === "g[logo]" && window.pgTrimLogoFile) {
      status.textContent = "Entfernt transparenten Rand …";
      try {
        const t = await window.pgTrimLogoFile(file);
        if (t) { const cut = Math.round((1 - (t.box[2] - t.box[0]) * (t.box[3] - t.box[1])) * 100); file = t.file; trimNote = ' <span class="opt-note">· transparenter Rand entfernt (−' + cut + ' % Fläche)</span>'; }
      } catch (err) {}
    }
    status.textContent = "Lädt… (" + Math.round(file.size / 1024) + " KB)";
    const fd = new FormData();
    fd.append("csrf", csrf());
    fd.append("file", file);
    try {
      const r = await fetch("index.php?action=upload", { method: "POST", body: fd });
      const j = await readJson(r);
      if (j.error) { status.innerHTML = '<span class="up-err">' + j.error + "</span>"; }
      else {
        textInput.value = j.path;
        textInput.dispatchEvent(new Event("input", { bubbles: true }));
        status.innerHTML = '<a href="' + j.path + '" target="_blank" class="media-prev">' + j.path + "</a>" + optNote(j) + trimNote;
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
      else toast("Kopiert: " + (path.length > 40 ? path.slice(0, 40) + "…" : path), "ok", 1600);
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
    const frame = compose.querySelector("[data-mail-frame]") || document.querySelector("[data-mail-frame]");
    let timer = null, busy = false, pending = false;
    async function render() {
      if (busy) { pending = true; return; }
      busy = true;
      const fd = new FormData();
      fd.append("csrf", csrf());
      fd.append("mode", mode);
      fd.append("message", input ? input.value : "");
      if (compose.hasAttribute("data-html")) fd.append("html", "1");
      const sigSel = compose.querySelector("[data-sig-select]");
      if (sigSel) fd.append("sig", sigSel.value);
      const mbIn = compose.querySelector('input[name="mb"]');
      if (mbIn) fd.append("mb", mbIn.value);   // Vorschau im richtigen Postfach (Signatur-Adresse)
      const sigOn = compose.querySelector("[data-sig-on]");
      if (sigOn) { fd.append("with_sig_sent", "1"); if (sigOn.checked) fd.append("with_sig", "1"); }
      try {
        const r = await fetch("mail.php?action=preview", { method: "POST", body: fd });
        frame.srcdoc = await r.text();
      } catch {}
      busy = false;
      if (pending) { pending = false; render(); }
    }
    if (input) input.addEventListener("input", () => { clearTimeout(timer); timer = setTimeout(render, 350); });
    const sigSelP = compose.querySelector("[data-sig-select]");
    if (sigSelP) sigSelP.addEventListener("change", render);
    const sigOnP = compose.querySelector("[data-sig-on]");
    if (sigOnP) sigOnP.addEventListener("change", render);
    window.pgMailPreview = render;
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

  // ---- Felder nur bei passender Auswahl zeigen (z. B. „Eigener Link“) – der Wert bleibt erhalten ----
  function showIfCtrl(el) {
    const ref = el.dataset.showIf;
    for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) {
      const s = a.querySelector('select[name$="[' + ref + ']"]');
      if (s) return s;
    }
    return null;
  }
  function syncShowIf() {
    document.querySelectorAll("[data-show-if]").forEach((el) => {
      const s = showIfCtrl(el);
      if (!s) return;
      const show = String(el.dataset.showIn || "").split(",").includes(s.value);
      const wrap = el.parentElement && el.parentElement.classList.contains("gfield") && el.parentElement.children.length === 1 ? el.parentElement : el;
      wrap.hidden = !show;
    });
  }
  window.pgSyncShowIf = syncShowIf;
  document.addEventListener("change", (e) => { if (e.target.matches && e.target.matches("select")) syncShowIf(); });
  syncShowIf();

  // ---- Icon-Auswahl: Icon anklicken = auswählen ----
  document.addEventListener("click", (e) => {
    const b = e.target.closest("[data-iconpick] [data-icon-key]");
    if (!b) return;
    e.preventDefault();
    const box = b.closest("[data-iconpick]");
    const inp = box.querySelector("[data-iconpick-input]");
    if (!inp) return;
    inp.value = b.dataset.iconKey;
    box.querySelectorAll("[data-icon-key]").forEach((x) => { const on = x === b; x.classList.toggle("on", on); x.setAttribute("aria-pressed", on ? "true" : "false"); });
    const note = box.nextElementSibling;
    if (note && note.classList.contains("hint") && /^Bisher:/.test(note.textContent)) note.remove();
    inp.dispatchEvent(new Event("input", { bubbles: true }));
    inp.dispatchEvent(new Event("change", { bubbles: true }));
  });

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
        const j = await readJson(r);
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
      fd.append("board", board.getAttribute("data-board-id") || "");
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
      const j = await readJson(r);
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

  // ---- Board: Label- & Zuständig-Filter ----
  const kbFilter = document.querySelector("[data-kb-filter]");
  if (kbFilter) {
    kbFilter.addEventListener("click", (e) => {
      const tag = e.target.closest(".kb-filter-tag");
      if (!tag) return;
      kbFilter.querySelectorAll(".kb-filter-tag").forEach((t) => t.classList.remove("on"));
      tag.classList.add("on");
      const wantLabel = tag.getAttribute("data-label");
      const wantMine = tag.getAttribute("data-mine");
      document.querySelectorAll(".board .kb-card").forEach((c) => {
        let show = true;
        if (wantMine) show = (c.getAttribute("data-assignee") || "") === wantMine;
        else if (wantLabel) show = (c.getAttribute("data-labels") || "").split("|").filter(Boolean).includes(wantLabel);
        c.style.display = show ? "" : "none";
      });
    });
  }

  // ---- Toast-Meldungen ----
  function toast(msg, type = "ok", ms = 2800) {
    let box = document.getElementById("pg-toasts");
    if (!box) { box = document.createElement("div"); box.id = "pg-toasts"; document.body.appendChild(box); }
    const t = document.createElement("div");
    t.className = "pg-toast " + type;
    t.innerHTML = `<span class="pgt-ico">${type === "err" ? "⚠️" : type === "info" ? "ℹ️" : "✓"}</span><span>${String(msg)}</span>`;
    box.appendChild(t);
    requestAnimationFrame(() => t.classList.add("show"));
    setTimeout(() => { t.classList.remove("show"); setTimeout(() => t.remove(), 350); }, ms);
  }
  window.pgToast = toast;
  // Flash-Meldungen (?saved=1 etc.) als Toast zeigen und die Box ausblenden
  document.querySelectorAll(".flash").forEach((f) => {
    toast(f.textContent.replace(/^[✓📣🌐]\s*/, "").trim(), f.classList.contains("err") ? "err" : "ok", 4000);
  });

  // ---- Autosave + Schutz vor ungespeicherten Änderungen ----
  (function autosave() {
    const form = document.querySelector('form#editor[data-autosave]');
    if (!form) return;
    const statusEl = form.querySelector("[data-save-status]");
    let dirty = false, saving = false, timer = null;
    let autoOn = true;
    try { autoOn = localStorage.getItem("pg_autosave") !== "0"; } catch {}

    // Autosave-Schalter in die Aktionsleiste
    const actions = form.querySelector(".editor-actions");
    if (actions) {
      const lab = document.createElement("label");
      lab.className = "autosave-toggle";
      lab.title = "Automatisch speichern, während du tippst";
      lab.innerHTML = `<input type="checkbox" ${autoOn ? "checked" : ""}> Autosave`;
      lab.querySelector("input").addEventListener("change", (e) => {
        autoOn = e.target.checked;
        try { localStorage.setItem("pg_autosave", autoOn ? "1" : "0"); } catch {}
        if (autoOn && dirty) schedule();
      });
      actions.insertBefore(lab, actions.firstChild);
    }
    const setStatus = (txt, cls) => { if (statusEl) { statusEl.textContent = txt; statusEl.className = "save-status " + (cls || ""); } };

    const markDirty = () => { dirty = true; setStatus("● Nicht gespeichert", "dirty"); if (autoOn) schedule(); };
    form.addEventListener("input", markDirty);
    form.addEventListener("change", markDirty);
    function schedule() { clearTimeout(timer); timer = setTimeout(save, 2600); }

    async function save() {
      if (saving || !dirty) return;
      saving = true; setStatus("Speichert …", "saving");
      const fd = new FormData(form);
      fd.append("save", "1"); fd.append("ajax", "1");
      try {
        const r = await fetch("index.php", { method: "POST", body: fd, headers: { "X-Requested-With": "fetch" } });
        const j = await readJson(r);
        if (j.ok) {
          dirty = false; setStatus("Gespeichert ✓", "ok"); toast("Automatisch gespeichert", "ok", 1800);
          document.dispatchEvent(new CustomEvent("pg:saved", { detail: j }));
          if (j.sent) toast("📣 Newsletter an " + j.sent + " gesendet", "info", 4000);
          if (j.tr_pending && window.pgTrKick) window.pgTrKick(j.tr_pending, 15000);
          if (j.fp) { const f = form.querySelector("[data-item-fp]"); if (f) f.value = j.fp; }
          if (j.item_key !== undefined) {
            const o = form.querySelector('input[name="item_orig"]');
            if (o && o.value !== String(j.item_key)) {
              o.value = j.item_key;
              try { const u = new URL(location.href); u.searchParams.set("item", j.item_key); history.replaceState(null, "", u); } catch {}
            }
          }
          if (j.note) toast(j.note, "info", 5000);
          reloadPreview();
        } else { setStatus("Fehler", "err"); toast(j.error || "Speichern fehlgeschlagen", "err", 6000); }
      } catch (e) { setStatus("Offline", "err"); }
      saving = false;
    }
    // Für andere Funktionen (z. B. „Adresse ändern“): erst ungespeicherte Änderungen sichern
    window.pgEditor = { isDirty: () => dirty, save: () => save() };
    // Manuelles Speichern: normal absenden, aber dirty zurücksetzen (kein Warn-Dialog)
    form.addEventListener("submit", () => { dirty = false; });
    addEventListener("beforeunload", (e) => { if (dirty) { e.preventDefault(); e.returnValue = ""; } });

    // ---- Live-Vorschau-Splitview (ein/ausschaltbar) ----
    const splitBtn = form.querySelector("[data-split-toggle]");
    // Vorschau-Ziel: bevorzugt das vom Server gesetzte data-preview; sonst aus ?collection= ableiten
    // (robust, falls eine ältere index.php das Attribut nicht setzt).
    function resolvePreviewUrl() {
      const explicit = form.getAttribute("data-preview");
      if (explicit) return explicit;
      const c = new URLSearchParams(location.search).get("collection") || "";
      return ({ studio: "../index.html", team: "../index.html#team-section",
        games: "../games.html", patchnotes: "../devlog.php", legal: "../rechtliches.html" })[c] || "../index.html";
    }
    const previewUrl = resolvePreviewUrl();
    let frame = null;
    function reloadPreview() { if (frame) frame.src = previewUrl + (previewUrl.includes("?") ? "&" : "?") + "_t=" + Date.now(); }
    let pane = null;
    function placePane() {
      if (!pane) return;
      const tb = document.querySelector(".topbar");
      const th = tb ? Math.round(tb.getBoundingClientRect().height) : 53;
      pane.style.top = th + "px";
      pane.style.height = "calc(100vh - " + th + "px)";
    }
    function openSplit() {
      document.body.classList.add("split-on");
      if (!pane) {
        pane = document.createElement("div");
        pane.className = "split-preview";
        pane.innerHTML = `<div class="sp-bar"><span>👁 Live-Vorschau</span><a href="${previewUrl}" target="_blank" rel="noopener">↗ Tab</a><button type="button" data-split-close aria-label="Vorschau schließen">✕</button></div><iframe title="Vorschau"></iframe>`;
        document.body.appendChild(pane);
        frame = pane.querySelector("iframe");
        pane.querySelector("[data-split-close]").addEventListener("click", closeSplit);
        addEventListener("resize", placePane);
        reloadPreview();
      }
      placePane();   // unter die (evtl. zweizeilige) Topbar setzen, damit der ✕ klickbar ist
      try { localStorage.setItem("pg_split", "1"); } catch {}
    }
    function closeSplit() { document.body.classList.remove("split-on"); try { localStorage.setItem("pg_split", "0"); } catch {} }
    if (splitBtn) splitBtn.addEventListener("click", () => document.body.classList.contains("split-on") ? closeSplit() : openSplit());
    try { if (localStorage.getItem("pg_split") === "1" && innerWidth > 900) openSplit(); } catch {}
  })();

  // ---- App-Shell: Seitenmenü auf dem Handy ----
  const side = document.getElementById("side"), scrim = document.getElementById("scrim");
  function openDrawer() { if (!side) return; side.classList.add("open"); if (scrim) scrim.classList.add("show"); }
  function closeDrawer() { if (!side) return; side.classList.remove("open"); if (scrim) scrim.classList.remove("show"); }
  [document.getElementById("menuBtn"), document.getElementById("tabMore")].forEach((b) => { if (b) b.addEventListener("click", openDrawer); });
  if (scrim) scrim.addEventListener("click", closeDrawer);

  // ---- Befehlspalette: Strg/⌘ + K — Seiten, Spiele, Aktionen + Volltextsuche ----
  const pal = document.getElementById("pal");
  if (pal && Array.isArray(window.PG_PAL)) {
    const palIn = document.getElementById("palInput"), palList = document.getElementById("palList");
    const escH = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
    const norm = (v) => String(v || "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");
    let palSel = 0, palShown = [];
    const renderPal = () => {
      const raw = palIn.value.trim(), q = norm(raw);
      palShown = window.PG_PAL.filter((it) => !q || norm(it.l + " " + (it.k || "")).includes(q));
      if (raw) palShown.push({ s: "Volltext", i: "search", l: "In allen Inhalten suchen: „" + raw + "“", h: "index.php?view=search&q=" + encodeURIComponent(raw), k: "" });
      palSel = Math.max(0, Math.min(palSel, palShown.length - 1));
      let sec = "", h = "";
      palShown.forEach((it, i) => {
        if (it.s !== sec) { sec = it.s; h += `<li class="pal-sec">${escH(sec)}</li>`; }
        h += `<li class="pal-item${i === palSel ? " on" : ""}" data-pi="${i}"><svg class="ic" aria-hidden="true"><use href="#i-${escH(it.i)}"/></svg>${escH(it.l)}${it.s === "Spiele" && it.k ? `<small>${escH(it.k)}</small>` : ""}</li>`;
      });
      palList.innerHTML = h || '<li class="pal-sec">Nichts gefunden</li>';
      const on = palList.querySelector(".pal-item.on"); if (on) on.scrollIntoView({ block: "nearest" });
    };
    const palGo = (it) => { if (!it) return; if (it.h === "../index.html") { window.open(it.h, "_blank"); closePal(); } else location.href = it.h; };
    const openPal = (q) => { closeDrawer(); pal.hidden = false; palIn.value = q || ""; palSel = 0; renderPal(); palIn.focus(); requestAnimationFrame(() => palIn.focus()); };
    const closePal = () => { pal.hidden = true; };
    window.pgOpenPalette = openPal;
    document.addEventListener("click", (e) => {
      if (e.target.closest("[data-open-palette]")) { e.preventDefault(); openPal(); return; }
      if (e.target === pal) { closePal(); return; }
      const li = e.target.closest(".pal-item"); if (li && pal.contains(li)) palGo(palShown[+li.dataset.pi]);
    });
    // Suchfeld in der Kopfzeile öffnet die Palette (ohne JS bleibt es eine normale Volltextsuche)
    const topIn = document.querySelector("[data-open-palette-input]");
    if (topIn) topIn.addEventListener("focus", () => { const v = topIn.value; topIn.blur(); openPal(v); });
    palIn.addEventListener("input", () => { palSel = 0; renderPal(); });
    palIn.addEventListener("keydown", (e) => {
      if (e.key === "ArrowDown") { palSel = Math.min(palSel + 1, palShown.length - 1); renderPal(); e.preventDefault(); }
      else if (e.key === "ArrowUp") { palSel = Math.max(palSel - 1, 0); renderPal(); e.preventDefault(); }
      else if (e.key === "Enter") { e.preventDefault(); palGo(palShown[palSel]); }
      else if (e.key === "Escape") { e.stopPropagation(); closePal(); }
    });
    document.addEventListener("keydown", (e) => {
      if ((e.ctrlKey || e.metaKey) && (e.key === "k" || e.key === "K")) { e.preventDefault(); pal.hidden ? openPal() : closePal(); }
    });
  }


  // =====================================================================
  //  SPIELE-VERWALTUNG (Liste + Einzel-Editor mit Reitern)
  // =====================================================================
  const gEditor = document.querySelector("[data-game-editor]");
  const gList = document.querySelector("[data-game-rows]");

  // ---- Reiter ----
  function showTab(tab, focusField) {
    const root = document.querySelector(".gd");
    if (!root || !root.querySelector(`[data-panel="${tab}"]`)) return;
    root.querySelectorAll(".gd-tabs [data-tab]").forEach((b) => b.classList.toggle("on", b.dataset.tab === tab));
    root.querySelectorAll(".gd-panel").forEach((p) => p.classList.toggle("on", p.dataset.panel === tab));
    const inp = root.querySelector("[data-tab-input]"); if (inp) inp.value = tab;
    try { const u = new URL(location.href); u.searchParams.set("tab", tab); ["saved", "created", "duplicated", "renamed", "mc", "gerr", "translated", "error"].forEach((k) => u.searchParams.delete(k)); history.replaceState(null, "", u); } catch {}
    if (focusField) {
      const box = root.querySelector(`[data-panel="${tab}"] [data-gfield="${focusField}"]`);
      if (box) {
        box.scrollIntoView({ behavior: "smooth", block: "center" });
        box.classList.add("g-flash"); setTimeout(() => box.classList.remove("g-flash"), 1600);
        const f = box.querySelector("input:not([type=hidden]), textarea, select"); if (f) setTimeout(() => f.focus({ preventScroll: true }), 350);
      }
    }
  }
  document.addEventListener("click", (e) => {
    const t = e.target.closest(".gd [data-tab]");
    if (t) { e.preventDefault(); showTab(t.dataset.tab); return; }
    const go = e.target.closest("[data-goto-tab]");
    if (go) { e.preventDefault(); showTab(go.dataset.gotoTab, go.dataset.gotoField); }
  });

  // ---- Liste: Suche & Status-Filter ----
  if (gList) {
    const search = document.querySelector("[data-game-search]");
    const filter = document.querySelector("[data-game-filter]");
    let want = "";
    const apply = () => {
      const q = (search ? search.value : "").trim().toLowerCase();
      gList.querySelectorAll(".gm-row").forEach((r) => {
        const ok = (!q || (r.dataset.q || "").includes(q)) && (!want || r.dataset.status === want);
        r.style.display = ok ? "" : "none";
      });
    };
    if (search) search.addEventListener("input", apply);
    if (filter) filter.addEventListener("click", (e) => {
      const b = e.target.closest("[data-gf]"); if (!b) return;
      want = b.dataset.gf; filter.querySelectorAll("[data-gf]").forEach((x) => x.classList.toggle("on", x === b)); apply();
    });

    // ---- Reihenfolge per Ziehen (Griff rechts) → sofort gespeichert ----
    let gd = null;
    gList.addEventListener("pointerdown", (e) => {
      const grip = e.target.closest("[data-game-grip]"); if (!grip) return;
      const row = grip.closest(".gm-row"); if (!row) return;
      e.preventDefault();
      gd = { row, before: [...gList.querySelectorAll(".gm-row")].map((r) => r.dataset.slug).join("|") };
      row.classList.add("dragging"); document.body.classList.add("is-dragging");
      try { grip.setPointerCapture(e.pointerId); } catch {}
    });
    gList.addEventListener("pointermove", (e) => {
      if (!gd) return;
      e.preventDefault();
      const rows = [...gList.querySelectorAll(".gm-row")].filter((r) => r !== gd.row);
      let placed = false;
      for (const r of rows) { const b = r.getBoundingClientRect(); if (e.clientY < b.top + b.height / 2) { gList.insertBefore(gd.row, r); placed = true; break; } }
      if (!placed) gList.appendChild(gd.row);
    });
    const endGameDrag = async () => {
      if (!gd) return;
      const { row, before } = gd; gd = null;
      row.classList.remove("dragging"); document.body.classList.remove("is-dragging");
      const order = [...gList.querySelectorAll(".gm-row")].map((r) => r.dataset.slug);
      if (order.join("|") === before) return;
      const fd = new URLSearchParams(); fd.append("csrf", csrf()); fd.append("order", JSON.stringify(order));
      try {
        const r = await fetch("index.php?action=games_reorder", { method: "POST", body: fd, headers: { "X-Requested-With": "fetch" } });
        const j = await readJson(r);
        toast(j.ok ? "Reihenfolge gespeichert – so erscheint sie auch auf der Website" : "Reihenfolge konnte nicht gespeichert werden", j.ok ? "ok" : "err");
      } catch { toast("Reihenfolge konnte nicht gespeichert werden", "err"); }
    };
    gList.addEventListener("pointerup", endGameDrag);
    gList.addEventListener("pointercancel", endGameDrag);
  }

  // ---- Seiten-Blöcke: kompakt, ein-/ausblenden, Hinzufügen über Kacheln ----
  document.querySelectorAll(".gd [data-blocks]").forEach((blocks) => {
    blocks.querySelectorAll(":scope > [data-items] > [data-item]").forEach((it) => it.classList.add("folded"));
    const sel = blocks.querySelector("[data-block-select]");
    if (!sel) return;
    const add = document.createElement("button");
    add.type = "button"; add.className = "g-addblock";
    add.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-plus"/></svg>Block hinzufügen';
    const pal = document.createElement("div");
    pal.className = "g-palette"; pal.hidden = true;
    const icons = { hero: "image", richtext: "devlog", features: "star", gallery: "image", trailer: "play", quotes: "chat", stats: "chart", roadmap: "route",
      faq: "help", countdown: "clock", cta: "mega", download: "download", requirements: "server", storewidget: "ext", suggestions: "bulb", waitlist: "list", lore: "book", spacer: "move" };
    pal.innerHTML = [...sel.options].map((o) => `<button type="button" data-add-type="${o.value}"><svg class="ic" aria-hidden="true"><use href="#i-${icons[o.value] || "cockpit"}"/></svg>${o.textContent}</button>`).join("");
    blocks.appendChild(add); blocks.appendChild(pal);
    add.addEventListener("click", () => { pal.hidden = !pal.hidden; });
    pal.addEventListener("click", (e) => {
      const b = e.target.closest("[data-add-type]"); if (!b) return;
      sel.value = b.dataset.addType; addBlock(blocks); pal.hidden = true;
      setTimeout(() => document.querySelectorAll("textarea.md").forEach(enhanceMarkdown), 60);
      const last = blocks.querySelector(":scope > [data-items] > [data-item]:last-child");
      if (last) { last.scrollIntoView({ behavior: "smooth", block: "center" }); last.dispatchEvent(new Event("input", { bubbles: true })); }
      toast("„" + b.textContent.trim() + "“ hinzugefügt – Inhalte eintragen und speichern", "info");
    });
  });
  document.addEventListener("click", (e) => {
    const sw = e.target.closest("[data-block-vis]");
    if (!sw) return;
    e.preventDefault();
    const item = sw.closest("[data-item]");
    const inp = item && item.querySelector(":scope > [data-hidden-input]");
    if (!inp) return;
    const hide = inp.value !== "1";
    inp.value = hide ? "1" : "0";
    sw.classList.toggle("on", !hide); sw.setAttribute("aria-pressed", String(!hide));
    item.classList.toggle("is-hidden", hide);
    const meta = item.querySelector("[data-block-meta]");
    if (meta) meta.textContent = hide ? "ausgeblendet · " + meta.textContent.replace(/^ausgeblendet · /, "") : meta.textContent.replace(/^ausgeblendet · /, "");
    inp.dispatchEvent(new Event("input", { bubbles: true }));
    toast(hide ? "Block ausgeblendet – bleibt gespeichert, nur nicht sichtbar" : "Block wird wieder angezeigt", "info", 2200);
  });

  // ---- Dialoge ----
  function openDialog(id) {
    const d = document.getElementById(id); if (!d) return;
    d.hidden = false;
    const f = d.querySelector("[data-autofocus]"); if (f) setTimeout(() => { f.focus(); if (f.select) f.select(); }, 40);
    d.dispatchEvent(new Event("pg:open"));
  }
  function closeDialogs() { document.querySelectorAll(".pg-modal-back:not([hidden])").forEach((d) => { d.hidden = true; }); }
  window.pgOpenDialog = openDialog;
  document.addEventListener("click", (e) => {
    const o = e.target.closest("[data-open-dialog]");
    if (o) { e.preventDefault(); openDialog(o.dataset.openDialog); return; }
    if (e.target.closest(".pg-modal [data-close]") || e.target.classList.contains("pg-modal-back")) { e.preventDefault(); closeDialogs(); }
  });
  document.addEventListener("keydown", (e) => { if (e.key === "Escape" && document.querySelector(".pg-modal-back:not([hidden])")) closeDialogs(); });
  // ?new=1 → Dialog „Neu“ direkt öffnen (z. B. „Devlog-Beitrag“ im Cockpit)
  { const auto = document.querySelector(".pg-modal-back[data-autoopen]"); if (auto) openDialog(auto.id); }

  const slugify = (v) => String(v || "").toLowerCase().replace(/ä/g, "ae").replace(/ö/g, "oe").replace(/ü/g, "ue").replace(/ß/g, "ss").replace(/&/g, "-und-").replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
  // Neues Spiel: Adresse live anzeigen
  const ngTitle = document.querySelector("[data-slug-source]"), ngPrev = document.querySelector("[data-slug-preview]");
  if (ngTitle && ngPrev) ngTitle.addEventListener("input", () => { ngPrev.textContent = slugify(ngTitle.value) || "…"; });

  // Web-Adresse ändern: prüfen + vorher ungespeicherte Änderungen sichern
  const slugForm = document.querySelector("[data-slug-form]");
  if (slugForm) {
    const inp = slugForm.querySelector("[data-slug-input]"), err = slugForm.querySelector("[data-slug-err]"), btn = slugForm.querySelector("[data-slug-save]");
    let taken = []; try { taken = JSON.parse(slugForm.dataset.taken || "[]"); } catch {}
    const cur = slugForm.dataset.current || "";
    const validate = () => {
      const v = inp.value;
      let m = "";
      if (!v) m = "Bitte eine Adresse eingeben.";
      else if (!/^[a-z0-9]+(-[a-z0-9]+)*$/.test(v)) m = "Nur Kleinbuchstaben, Zahlen und einzelne Bindestriche (z. B. frontliners).";
      else if (taken.includes(v)) m = "Diese Adresse benutzt schon ein anderes Spiel.";
      else if (v === cur) m = "Das ist bereits die aktuelle Adresse.";
      err.textContent = m; btn.disabled = !!m;
      return !m;
    };
    inp.addEventListener("input", () => { const p = inp.selectionStart; inp.value = inp.value.toLowerCase().replace(/\s+/g, "-").replace(/[^a-z0-9-]/g, ""); try { inp.setSelectionRange(p, p); } catch {} validate(); });
    slugForm.closest(".pg-modal-back").addEventListener("pg:open", validate);
    slugForm.addEventListener("submit", async (e) => {
      if (!validate()) { e.preventDefault(); return; }
      if (window.pgEditor && window.pgEditor.isDirty()) {
        e.preventDefault(); btn.disabled = true; btn.textContent = "Speichert Änderungen …";
        await window.pgEditor.save();
        slugForm.submit();
      } else { btn.disabled = true; btn.textContent = "Zieht um …"; }
    });
  }
  // Formulare mit Sicherheitsabfrage (Löschen, Übersetzen)
  document.querySelectorAll("form[data-confirm]").forEach((f) => f.addEventListener("submit", (e) => {
    if (!confirm(f.dataset.confirm)) e.preventDefault();
  }));

  // ---- Live: Google-Vorschau & Logo-Status ----
  if (gEditor) {
    const tIn = gEditor.querySelector('[name="g[title]"]'), dIn = gEditor.querySelector('[name="g[tagline]"]');
    const st = gEditor.querySelector("[data-serp-title]"), sd = gEditor.querySelector("[data-serp-desc]");
    const upd = () => {
      if (st && tIn) st.textContent = (tIn.value || "") + " — PLANIGAMES";
      if (sd && dIn) { const v = dIn.value.trim(); sd.textContent = v.length > 158 ? v.slice(0, 157) + " …" : v; }
    };
    [tIn, dIn].forEach((x) => x && x.addEventListener("input", upd));
    // Button-Vorschau mit den Spielfarben (gleiche Logik wie auf der Website)
    const bp = gEditor.querySelector("[data-btn-prev]");
    const a1 = gEditor.querySelector('[name="g[accent]"]'), a2 = gEditor.querySelector('[name="g[accent2]"]');
    const bright = (h) => { const m = String(h || "").trim().replace("#", "").match(/^([0-9a-f]{3}|[0-9a-f]{6})$/i); if (!m) return 0; let x = m[1]; if (x.length === 3) x = x.split("").map((c) => c + c).join(""); return (parseInt(x.substr(0, 2), 16) * 299 + parseInt(x.substr(2, 2), 16) * 587 + parseInt(x.substr(4, 2), 16) * 114) / 1000; };
    const updBtn = () => {
      if (!bp) return;
      const c1 = (a1 && /^#[0-9a-f]{6}$/i.test(a1.value) ? a1.value : "#ff7d1a"), c2 = (a2 && /^#[0-9a-f]{6}$/i.test(a2.value) ? a2.value : c1);
      bp.style.background = `linear-gradient(110deg, ${c1}, ${c2})`;
      bp.style.color = (bright(c1) + bright(c2)) / 2 > 176 ? "#1a0d00" : "#ffffff";
    };
    [a1, a2].forEach((x) => { if (!x) return; x.addEventListener("input", updBtn); const sw = x.previousElementSibling; if (sw) sw.addEventListener("input", () => setTimeout(updBtn, 0)); });
    updBtn();
    const logoIn = gEditor.querySelector('[name="g[logo]"]'), prev = gEditor.querySelector("[data-logo-prev]");
    if (logoIn && prev) logoIn.addEventListener("input", () => {
      const v = logoIn.value.split("#")[0].trim();
      prev.innerHTML = v ? `<img src="${v.replace(/"/g, "%22")}" alt="">` : "<span>kein Logo</span>";
    });
  }


  // =====================================================================
  //  LOGO PLATZIEREN – die ECHTE Spielseite in echter Bildschirmgröße
  //  (Rahmen mit ?_placer=1), gemessen werden nur die sichtbaren Logo-Pixel.
  // =====================================================================
  const VPS = { laptop: { w: 1366, h: 768, l: "Laptop" }, desktop: { w: 1920, h: 1080, l: "Full HD" }, wide: { w: 2560, h: 1440, l: "1440p" }, mobile: { w: 390, h: 844, l: "Handy" } };
  const nextFrames = (w) => new Promise((r) => { const win = w || window; win.requestAnimationFrame(() => win.requestAnimationFrame(() => r())); });
  const loadImg = (src) => new Promise((res, rej) => { const im = new Image(); im.onload = () => res(im); im.onerror = () => rej(new Error("Bild nicht ladbar")); im.src = src; });
  const isSvg = (n) => /\.svg($|[?#])/i.test(String(n || "")) || /svg/i.test(String(n && n.type || ""));

  // Seitenverhältnis + Ausmaß des SVG (viewBox bzw. width/height)
  function svgGeom(text) {
    const doc = new DOMParser().parseFromString(text, "image/svg+xml");
    const svg = doc.documentElement;
    if (!svg || svg.nodeName.toLowerCase() !== "svg") return null;
    // nur absolute Angaben (Zahl oder px) zählen – „100%“ o. Ä. bestimmt keine Größe
    const num = (v) => { const m = String(v || "").trim().match(/^([\d.]+)(px)?$/i); const n = m ? parseFloat(m[1]) : 0; return isFinite(n) && n > 0 ? n : 0; };
    let vb = (svg.getAttribute("viewBox") || "").trim().split(/[\s,]+/).map(Number);
    const w = num(svg.getAttribute("width")), h = num(svg.getAttribute("height"));
    if (vb.length !== 4 || vb.some((x) => !isFinite(x)) || vb[2] <= 0 || vb[3] <= 0) vb = w && h ? [0, 0, w, h] : null;
    if (!vb) return null;
    // Weichen feste Breite/Höhe vom viewBox-Verhältnis ab, rendert der Browser mit Rand → dann nicht zuschneiden
    const skew = w && h ? Math.abs((w / h) / (vb[2] / vb[3]) - 1) > 0.01 : false;
    return { doc, svg, vb, w, h, ar: w && h ? w / h : vb[2] / vb[3], skew };
  }
  // Sichtbarer Bereich (Alpha > 10) als Anteile 0..1 – für SVG und Rasterbilder
  async function measureAlpha(src, ar, maxSide) {
    const im = await loadImg(src);
    let w = im.naturalWidth, h = im.naturalHeight;
    if (ar) { const side = maxSide || 1600; if (ar >= 1) { w = side; h = Math.round(side / ar); } else { h = side; w = Math.round(side * ar); } }
    if (!w || !h) { w = 1000; h = 500; }
    const sc = Math.min(1, (maxSide || 1600) / Math.max(w, h));
    const cw = Math.max(1, Math.round(w * sc)), ch = Math.max(1, Math.round(h * sc));
    const c = document.createElement("canvas"); c.width = cw; c.height = ch;
    const ctx = c.getContext("2d", { willReadFrequently: true });
    ctx.drawImage(im, 0, 0, cw, ch);
    let d; try { d = ctx.getImageData(0, 0, cw, ch).data; } catch (e) { return { box: [0, 0, 1, 1], ar: w / h, im }; }
    let x0 = cw, y0 = ch, x1 = -1, y1 = -1;
    for (let y = 0; y < ch; y++) { const row = y * cw * 4; for (let x = 0; x < cw; x++) if (d[row + x * 4 + 3] > 10) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; } }
    return { box: x1 < 0 ? [0, 0, 1, 1] : [x0 / cw, y0 / ch, (x1 + 1) / cw, (y1 + 1) / ch], ar: w / h, im, cw, ch };
  }
  const _logoInfo = {};
  async function logoInfo(url) {
    if (_logoInfo[url]) return _logoInfo[url];
    let ar = 0, blob = null;
    if (isSvg(url)) {
      try {
        const g = svgGeom(await (await fetch(url)).text());
        if (g) {
          ar = g.ar;   // feste Pixelgröße setzen – ohne zeichnen manche Browser SVGs nicht auf Canvas
          g.svg.setAttribute("width", ar >= 1 ? 2000 : Math.round(2000 * ar)); g.svg.setAttribute("height", ar >= 1 ? Math.round(2000 / ar) : 2000);
          blob = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(g.doc)], { type: "image/svg+xml" }));
        }
      } catch (e) {}
    }
    try { return (_logoInfo[url] = await measureAlpha(blob || url, ar, 2000)); }
    catch (e) { return { box: [0, 0, 1, 1], ar: ar || 2 }; }
    finally { if (blob) URL.revokeObjectURL(blob); }
  }
  // Prüfsumme wie pg_tr_hash() / trHash() (FNV-1a, 32 Bit, UTF-8)
  const fnvHex = (s) => { let h = 0x811c9dc5; for (const x of new TextEncoder().encode(String(s).trim())) { h ^= x; h = Math.imul(h, 0x01000193) >>> 0; } return h.toString(16).padStart(8, "0"); };
  // Sichtbarer Teil des Logos als gespeicherter Wert „x0,y0,x1,y1,Seitenverhältnis,Prüfsumme“
  async function logoBoxValue(src) {
    const info = await logoInfo(src);
    if (!info || !info.ar || !info.box) return "";
    return info.box.map((n) => (+n).toFixed(4)).concat((+info.ar).toFixed(4), fnvHex(src)).join(",");
  }
  const logoBoxValid = (val, src) => { const v = String(val || "").split(","); return v.length === 6 && v[5] === fnvHex(src); };
  async function saveLogoBox(slug, src) {
    const box = await logoBoxValue(src);
    if (!box) return null;
    try {
      const fd = new URLSearchParams(); fd.append("csrf", csrf()); fd.append("slug", slug); fd.append("logo", src); fd.append("box", box);
      const j = await (await fetch("index.php?action=game_logobox", { method: "POST", body: fd, headers: { "X-Requested-With": "fetch" } })).json();
      return j && j.ok ? j.box : null;
    } catch (e) { return null; }
  }
  // Cockpit: Logos ohne gespeicherten Ausschnitt einmal messen, speichern und sofort ohne Rand zeigen
  document.querySelectorAll("[data-logo-sync]").forEach(async (el) => {
    const box = await saveLogoBox(el.dataset.logoSync, el.dataset.logoSrc);
    const v = String(box || "").split(",").map(Number);
    if (v.length < 5 || !(v[2] > v[0]) || !(v[3] > v[1])) return;
    const fw = v[2] - v[0], fh = v[3] - v[1], img = el.querySelector("img");
    el.classList.remove("is-plain"); el.classList.add("pg-crop"); el.removeAttribute("data-logo-sync");
    el.style.setProperty("--va", (v[4] * fw / fh).toFixed(4));
    el.setAttribute("role", "img"); el.setAttribute("aria-label", img ? img.alt : "");
    if (img) { img.alt = ""; Object.assign(img.style, { width: (100 / fw).toFixed(4) + "%", left: (-100 * v[0] / fw).toFixed(4) + "%", top: (-100 * v[1] / fh).toFixed(4) + "%" }); }
  });
  // Spiel-Editor: Ausschnitt fürs aktuelle Logo sicherstellen (beim Öffnen und nach jedem Speichern)
  {
    const row = document.querySelector("[data-logo-sync-editor]");
    const li = row && document.querySelector('[name="g[logo]"]');
    if (row && li) {
      let busy = false;
      const sync = async () => {
        const src = li.value.split("#")[0].trim();
        if (!src || busy || logoBoxValid(row.dataset.logoBox, src)) return;
        busy = true;
        const box = await saveLogoBox(row.dataset.slug, src);   // klappt erst, wenn das neue Logo gespeichert ist
        if (box) row.dataset.logoBox = box;
        busy = false;
      };
      setTimeout(sync, 1200);
      document.addEventListener("pg:saved", () => setTimeout(sync, 200));
    }
  }

  // Transparenten Rand eines Logos abschneiden → neue Datei (oder null, wenn nichts zu tun ist)
  async function trimLogoFile(file) {
    const base = String(file.name || "logo").replace(/\.[^.]+$/, "").replace(/-trim$/, "") + "-trim";
    if (isSvg(file.name) || isSvg(file)) {
      const text = await file.text();
      const g = svgGeom(text); if (!g || g.skew) return null;
      const url = URL.createObjectURL(new Blob([text], { type: "image/svg+xml" }));
      let m; try { m = await measureAlpha(url, g.ar, 2000); } finally { URL.revokeObjectURL(url); }
      const [a, b, c, d] = m.box, pad = 0.004;
      if ((c - a) * (d - b) > 0.94) return null;
      const fx0 = Math.max(0, a - pad), fy0 = Math.max(0, b - pad), fx1 = Math.min(1, c + pad), fy1 = Math.min(1, d + pad);
      const [vx, vy, vw, vh] = g.vb;
      const nvb = [vx + fx0 * vw, vy + fy0 * vh, (fx1 - fx0) * vw, (fy1 - fy0) * vh].map((v) => +v.toFixed(3));
      g.svg.setAttribute("viewBox", nvb.join(" "));
      if (g.w && g.h) { g.svg.setAttribute("width", +(g.w * (fx1 - fx0)).toFixed(2)); g.svg.setAttribute("height", +(g.h * (fy1 - fy0)).toFixed(2)); }
      g.svg.setAttribute("preserveAspectRatio", "xMidYMid meet");
      const out = new XMLSerializer().serializeToString(g.doc);
      return { file: new File([out], base + ".svg", { type: "image/svg+xml" }), box: [fx0, fy0, fx1, fy1] };
    }
    if (!/^image\/(png|webp|gif)$/i.test(file.type || "") && !/\.(png|webp|gif)$/i.test(file.name || "")) return null;
    const url = URL.createObjectURL(file);
    try {
      const im = await loadImg(url);
      const W = im.naturalWidth, H = im.naturalHeight;
      if (!W || !H || W * H > 40e6) return null;
      const c = document.createElement("canvas"); c.width = W; c.height = H;
      const ctx = c.getContext("2d", { willReadFrequently: true }); ctx.drawImage(im, 0, 0);
      const d = ctx.getImageData(0, 0, W, H).data;
      let x0 = W, y0 = H, x1 = -1, y1 = -1;
      for (let y = 0; y < H; y++) { const row = y * W * 4; for (let x = 0; x < W; x++) if (d[row + x * 4 + 3] > 10) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; } }
      if (x1 < 0) return null;
      const p = Math.round(Math.max(W, H) * 0.004);
      x0 = Math.max(0, x0 - p); y0 = Math.max(0, y0 - p); x1 = Math.min(W - 1, x1 + p); y1 = Math.min(H - 1, y1 + p);
      const cw = x1 - x0 + 1, ch = y1 - y0 + 1;
      if (cw * ch > 0.94 * W * H) return null;
      const o = document.createElement("canvas"); o.width = cw; o.height = ch;
      o.getContext("2d").drawImage(im, x0, y0, cw, ch, 0, 0, cw, ch);
      const blob = await new Promise((r) => o.toBlob(r, "image/png"));
      return blob ? { file: new File([blob], base + ".png", { type: "image/png" }), box: [x0 / W, y0 / H, (x1 + 1) / W, (y1 + 1) / H] } : null;
    } finally { URL.revokeObjectURL(url); }
  }
  window.pgTrimLogoFile = trimLogoFile;


  const PL = { vp: "desktop", layout: "left", scale: 100, gap: 20, box: [0, 0, 1, 1], ar: 2, fit: {}, m: null, api: null, papi: null, s: 1, logo: "" };
  const LAYOUTS = [
    ["left", "Links", "Logo über dem Text, linksbündig",
     '<rect x="6" y="8" width="20" height="6" rx="1.5" class="lg"/><rect x="6" y="17" width="26" height="2.5" rx="1"/><rect x="6" y="22" width="18" height="2.5" rx="1"/><rect x="6" y="28" width="10" height="4" rx="2" class="bt"/>'],
    ["center", "Mittig", "Alles zentriert",
     '<rect x="14" y="8" width="20" height="6" rx="1.5" class="lg"/><rect x="11" y="17" width="26" height="2.5" rx="1"/><rect x="15" y="22" width="18" height="2.5" rx="1"/><rect x="19" y="28" width="10" height="4" rx="2" class="bt"/>'],
    ["split", "Logo rechts", "Text links, Logo groß daneben",
     '<rect x="27" y="11" width="16" height="12" rx="2" class="lg"/><rect x="5" y="12" width="18" height="2.5" rx="1"/><rect x="5" y="17" width="14" height="2.5" rx="1"/><rect x="5" y="23" width="10" height="4" rx="2" class="bt"/>'],
  ];
  let plDlg = null;
  function plEl(sel) { return plDlg.querySelector(sel); }
  function buildPlacer() {
    if (plDlg) return;
    plDlg = document.createElement("div");
    plDlg.className = "pg-modal-back"; plDlg.id = "dlg-placer"; plDlg.hidden = true;
    plDlg.innerHTML = `<div class="pg-modal xl" role="dialog" aria-modal="true" aria-labelledby="pl-t">
      <div class="pg-modal-h"><div><h3 id="pl-t">Kopfbereich gestalten</h3><p>Das ist die echte Spielseite in echter Bildschirmgröße. Das Logo steht immer im Textblock – auf jedem Bildschirm gleich zum Text, ohne etwas zu überdecken.</p></div>
        <button type="button" class="icon-btn" data-close aria-label="Schließen"><svg class="ic"><use href="#i-x"/></svg></button></div>
      <div class="placer-bar"><div class="g-seg" data-vp-seg></div><div class="placer-warn" data-pl-warn></div></div>
      <div class="placer-tip" data-pl-tip hidden></div>
      <div class="placer-wrap" data-pl-wrap><div class="placer-stage" data-pl-stage>
        <iframe data-pl-frame title="Vorschau der Spielseite" tabindex="-1"></iframe>
        <div class="placer-ov" data-pl-ov><div class="placer-sel" data-pl-sel hidden></div></div>
        <div class="placer-load" data-pl-load>Lädt die Spielseite …</div></div></div>
      <div class="placer-foot">
        <div class="pl-layouts" data-pl-layouts role="radiogroup" aria-label="Anordnung">${LAYOUTS.map(([k, l, d, svg]) => `<button type="button" class="pl-lay" data-lay="${k}" role="radio" title="${d}"><svg viewBox="0 0 48 36" aria-hidden="true">${svg}</svg><span><b>${l}</b><small>${d}</small></span></button>`).join("")}</div>
        <div class="pl-sliders">
          <label class="placer-range"><span>Logo-Größe</span><input type="range" min="30" max="300" step="1" data-pl-size><b data-pl-sizeval>100 %</b></label>
          <label class="placer-range" data-pl-gapwrap title="Abstand zwischen Status-Badge, Logo und Text darunter"><span>Abstand</span><input type="range" min="0" max="120" step="2" data-pl-gap><b data-pl-gapval>20 px</b></label>
        </div>
        <span class="sp"></span>
        <button type="button" class="btn btn-primary" data-pl-save><svg class="ic"><use href="#i-check"/></svg>Übernehmen</button>
      </div></div>
      <iframe data-pl-probe title="" aria-hidden="true" tabindex="-1" class="placer-probe"></iframe>`;
    document.body.appendChild(plDlg);
    plEl("[data-vp-seg]").addEventListener("click", (e) => { const b = e.target.closest("[data-vp]"); if (b) { PL.vp = b.dataset.vp; plRender(true); } });
    plEl("[data-pl-layouts]").addEventListener("click", (e) => { const b = e.target.closest("[data-lay]"); if (!b) return; PL.layout = b.dataset.lay; plApply({ logoLayout: PL.layout }).then(plCheckAll); });
    const size = plEl("[data-pl-size]");
    size.addEventListener("input", () => { PL.scale = +size.value; plApply({ logoScale: PL.scale }); });
    size.addEventListener("change", plCheckAll);
    const gap = plEl("[data-pl-gap]");
    gap.addEventListener("input", () => { PL.gap = +gap.value; plApply({ logoGap: PL.gap }); });
    gap.addEventListener("change", plCheckAll);
    plEl("[data-pl-save]").addEventListener("click", plSave);
    addEventListener("resize", () => { if (plDlg && !plDlg.hidden) plRender(true); });
    new MutationObserver(() => { if (plDlg.hidden) { plEl("[data-pl-frame]").src = "about:blank"; plEl("[data-pl-probe]").src = "about:blank"; PL.api = PL.papi = null; } })
      .observe(plDlg, { attributes: true, attributeFilter: ["hidden"] });
  }
  function plFormPatch() {
    const v = (n) => { const el = gEditor.querySelector(`[name="g[${n}]"]`); return el ? el.value : undefined; };
    const p = { title: v("title"), tagline: v("tagline"), status: v("status"), accent: v("accent"), accent2: v("accent2"), wishlistUrl: v("wishlistUrl"), cover: v("cover"), logo: PL.logo };
    Object.keys(p).forEach((k) => p[k] === undefined && delete p[k]);
    const hero = [...gEditor.querySelectorAll('[data-item][data-block-type="hero"]')].find((it) => (it.querySelector(":scope > [data-hidden-input]") || {}).value !== "1");
    if (hero) { const h = {}; ["tagline", "background", "backgroundMobile", "video", "poster", "ctaLabel", "trailerUrl"].forEach((k) => { const el = hero.querySelector(`[name$="[${k}]"]`); if (el) h[k] = el.value; }); p.hero = h; }
    p.logoScale = PL.scale; p.logoLayout = PL.layout; p.logoGap = PL.gap;
    return p;
  }
  async function plWaitApi(frame) {
    for (let i = 0; i < 200; i++) {
      try { const w = frame.contentWindow; if (w && w.PLANIGAMES && w.PLANIGAMES.placer) return w.PLANIGAMES.placer; } catch (e) { return null; }
      await new Promise((r) => setTimeout(r, 100));
    }
    return null;
  }
  async function plWaitImg(frame) {
    try { const img = frame.contentDocument.querySelector('[data-hero-logo="inline"]'); if (img && img.decode) await img.decode().catch(() => {}); } catch (e) {}
  }
  // sichtbare Logo-Pixel innerhalb des Bild-Elements (object-fit: contain + transparenter Rand)
  function plVis(r, cropped) {
    if (!r) return null;
    if (cropped) return { x: r.x, y: r.y, w: r.w, h: r.h };
    const ar = PL.ar || (r.w / r.h);
    let iw = r.w, ih = r.h;
    if (r.w / r.h > ar) iw = r.h * ar; else ih = r.w / ar;
    const ix = r.x + (r.w - iw) / 2 * (PL.align === "left" ? 0 : (PL.align === "right" ? 2 : 1)), iy = r.y + (r.h - ih) / 2, b = PL.box;
    return { x: ix + b[0] * iw, y: iy + b[1] * ih, w: (b[2] - b[0]) * iw, h: (b[3] - b[1]) * ih };
  }
  // Bewertung je Bildschirmgröße: passt der Kopfbereich auf den ersten Bildschirm? Ist das Logo gut erkennbar?
  function plJudge(m, mobile) {
    const out = [];
    if (!m || !m.section) return out;
    if (m.section.h > m.vh + 4) out.push("höher als der Bildschirm");
    const v = plVis(m.logo, m.cropped);
    if (v && v.h < (mobile ? 26 : 38)) out.push("Logo wirkt sehr klein");
    if (v && m.inner && v.x + v.w > m.inner.x + m.inner.w + 2) out.push("Logo ragt aus dem Text-Bereich");
    return out;
  }
  function plFit() {
    const vp = VPS[PL.vp], wrap = plEl("[data-pl-wrap]"), frame = plEl("[data-pl-frame]"), stage = plEl("[data-pl-stage]");
    const availW = Math.max(200, wrap.clientWidth - 32), availH = Math.max(220, Math.min(innerHeight * 0.56, 640));
    PL.s = Math.min(availW / vp.w, availH / vp.h);
    frame.style.width = vp.w + "px"; frame.style.height = vp.h + "px"; frame.style.transform = `scale(${PL.s})`;
    stage.style.width = Math.round(vp.w * PL.s) + "px"; stage.style.height = Math.round(vp.h * PL.s) + "px";
  }
  function plDraw() {
    const m = PL.m, s = PL.s, sel = plEl("[data-pl-sel]");
    const mobile = VPS[PL.vp].w < 768;
    PL.align = mobile || PL.layout === "center" ? "center" : (PL.layout === "split" ? "right" : "left");
    const r = m && m.logo ? plVis(m.logo, m.cropped) : null;
    sel.hidden = !r;
    if (r) Object.assign(sel.style, { left: r.x * s + "px", top: r.y * s + "px", width: r.w * s + "px", height: r.h * s + "px" });
    const issues = plJudge(m, mobile);
    const w = plEl("[data-pl-warn]");
    w.className = "placer-warn " + (issues.length ? "bad" : "ok");
    w.innerHTML = issues.length ? '<svg class="ic"><use href="#i-alert"/></svg>' + escHtml(issues.join(" · "))
                                : '<svg class="ic"><use href="#i-check"/></svg>' + (mobile ? "Handy: passt, alles mittig" : "Passt auf den ersten Bildschirm");
    plEl("[data-pl-size]").value = PL.scale; plEl("[data-pl-sizeval]").textContent = PL.scale + " %";
    const gapIn = plEl("[data-pl-gap]"), gapOff = PL.layout === "split";
    gapIn.value = PL.gap; gapIn.disabled = gapOff; plEl("[data-pl-gapval]").textContent = PL.gap + " px";
    plEl("[data-pl-gapwrap]").classList.toggle("off", gapOff);
    plEl("[data-pl-gapwrap]").title = gapOff ? "Nur bei „Links“ und „Mittig“ – bei „Logo rechts“ steht das Logo neben dem Text" : "Abstand zwischen Status-Badge, Logo und Text darunter";
    plEl("[data-pl-layouts]").querySelectorAll("[data-lay]").forEach((b) => { const on = b.dataset.lay === PL.layout; b.classList.toggle("on", on); b.setAttribute("aria-checked", String(on)); });
  }
  function plSeg() {
    plEl("[data-vp-seg]").innerHTML = Object.entries(VPS).map(([k, v]) => {
      const w = PL.fit[k];
      const dot = `<span class="vdot${w && w.length ? " warn" : ""}" title="${w && w.length ? escHtml(w.join(", ")) : "passt"}"></span>`;
      return `<button type="button" class="${PL.vp === k ? "on" : ""}" data-vp="${k}">${dot}${v.l}<span class="n">${v.w}</span></button>`;
    }).join("");
  }
  async function plRender(resize) {
    if (!PL.api) return;
    const frame = plEl("[data-pl-frame]");
    if (resize) { plFit(); await nextFrames(frame.contentWindow); }
    PL.m = PL.api.measure();
    plSeg(); plDraw();
  }
  async function plApply(patch) {
    if (!PL.api) return;
    await PL.api.update(patch);
    await plWaitImg(plEl("[data-pl-frame]"));
    await nextFrames(plEl("[data-pl-frame]").contentWindow);
    PL.m = PL.api.measure();
    plDraw();
  }
  let plChecking = 0;
  async function plCheckAll() {
    if (!PL.papi) return;
    const run = ++plChecking, probe = plEl("[data-pl-probe]");
    await PL.papi.update(plFormPatch());
    await plWaitImg(probe);
    for (const k of ["laptop", "desktop", "wide", "mobile"]) {
      if (run !== plChecking || !PL.papi) return;
      probe.style.width = VPS[k].w + "px"; probe.style.height = VPS[k].h + "px";
      await nextFrames(probe.contentWindow);
      const keep = PL.align;
      PL.align = VPS[k].w < 768 || PL.layout === "center" ? "center" : (PL.layout === "split" ? "right" : "left");
      PL.fit[k] = plJudge(PL.papi.measure(), VPS[k].w < 768);
      PL.align = keep;
    }
    plSeg();
  }
  function plTip() {
    // Durchsichtiger Rand stört nicht mehr: Website & Vorschau zeigen nur den sichtbaren Teil des Logos.
    const tip = plEl("[data-pl-tip]"); tip.hidden = true; tip.innerHTML = "";
  }
  function plSave() {
    const q = (n) => gEditor.querySelector(`[name="g[${n}]"]`);
    const fl = q("logoLayout"), fs = q("logoScale"), fg = q("logoGap");
    if (fl) fl.value = PL.layout; if (fs) fs.value = PL.scale; if (fg) fg.value = PL.gap;
    [fl, fs, fg].forEach((i) => i && i.dispatchEvent(new Event("input", { bubbles: true })));
    const gpTxt = gEditor.querySelector("[data-logo-gapv]"); if (gpTxt) gpTxt.textContent = PL.gap;
    const lbl = { left: "Links über dem Text", center: "Mittig – alles zentriert", split: "Logo rechts neben dem Text" };
    const stTxt = gEditor.querySelector("[data-logo-state]"), scTxt = gEditor.querySelector("[data-logo-scale]");
    if (stTxt) stTxt.textContent = lbl[PL.layout]; if (scTxt) scTxt.textContent = PL.scale;
    const bad = Object.entries(PL.fit).filter(([, v]) => v && v.length).map(([k, v]) => VPS[k].l + ": " + v.join(", "));
    plDlg.hidden = true;
    toast(bad.length ? "Übernommen · Hinweis – " + bad.join(" · ") : "Übernommen · passt auf allen Bildschirmgrößen", bad.length ? "info" : "ok", 5000);
  }
  async function openPlacer() {
    if (!gEditor) return;
    const li = gEditor.querySelector('[name="g[logo]"]');
    PL.logo = li ? li.value.split("#")[0].trim() : "";
    if (window.pgEditor && window.pgEditor.isDirty()) await window.pgEditor.save();   // Vorschau = aktueller Stand
    buildPlacer();
    const v = (n) => (gEditor.querySelector(`[name="g[${n}]"]`) || {}).value;
    PL.layout = ["left", "center", "split"].includes(v("logoLayout")) ? v("logoLayout") : "left";
    PL.scale = Math.round(Math.min(300, Math.max(30, +v("logoScale") || 100)));
    PL.gap = String(v("logoGap") ?? "").trim() === "" || !isFinite(+v("logoGap")) ? 20 : Math.round(Math.min(120, Math.max(0, +v("logoGap"))));
    PL.vp = innerWidth < 860 ? "mobile" : "laptop"; PL.fit = {}; PL.api = PL.papi = null; PL.m = null;
    PL.box = [0, 0, 1, 1]; PL.ar = 0;
    plDlg.hidden = false;
    plEl("[data-pl-load]").hidden = false; plEl("[data-pl-load]").textContent = "Lädt die Spielseite …";
    plFit(); plSeg();
    const slug = (gEditor.querySelector('[name="game_orig"]') || {}).value || "";
    const src = "../game.php?slug=" + encodeURIComponent(slug) + "&_placer=1&_t=" + Date.now();
    const frame = plEl("[data-pl-frame]"), probe = plEl("[data-pl-probe]");
    frame.src = src; probe.src = src;
    if (PL.logo) { const info = await logoInfo(PL.logo); PL.box = info.box; PL.ar = info.ar; }
    plTip();
    PL.api = await plWaitApi(frame);
    if (!PL.api) { plEl("[data-pl-load]").textContent = "Die Spielseite konnte nicht geladen werden – bitte Seite neu laden."; return; }
    await plApply(plFormPatch());
    plEl("[data-pl-load]").hidden = true;
    await plRender(true);
    PL.papi = await plWaitApi(probe);
    plCheckAll();
  }
  document.addEventListener("click", (e) => { if (e.target.closest("[data-open-placer]")) { e.preventDefault(); openPlacer(); } });
  // =====================================================================
  //  POSTFACH: Editor mit Formatierung, Anhänge, Entwürfe, Signaturen
  // =====================================================================
  const escHtml = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  // Eingefügtes HTML (z. B. aus Word/Webseiten) auf einfache Auszeichnungen reduzieren
  function cleanPasted(html) {
    const doc = new DOMParser().parseFromString(html, "text/html");
    const ok = { B: "b", STRONG: "b", I: "i", EM: "i", U: "u", P: "p", DIV: "p", BR: "br", UL: "ul", OL: "ol", LI: "li", H1: "h3", H2: "h3", H3: "h3", H4: "h3", BLOCKQUOTE: "blockquote", A: "a" };
    const walk = (n) => {
      if (n.nodeType === 3) return escHtml(n.nodeValue);
      if (n.nodeType !== 1 || /^(SCRIPT|STYLE|META|LINK|TITLE|IMG|IFRAME|OBJECT|SVG)$/.test(n.nodeName)) return "";
      const inner = [...n.childNodes].map(walk).join("");
      const t = ok[n.nodeName];
      if (!t) return inner;
      if (t === "br") return "<br>";
      if (t === "a") { const h = n.getAttribute("href") || ""; return /^(https?:|mailto:)/i.test(h) ? `<a href="${escHtml(h)}">${inner}</a>` : inner; }
      return `<${t}>${inner}</${t}>`;
    };
    return [...doc.body.childNodes].map(walk).join("");
  }
  document.querySelectorAll("[data-rte]").forEach((rte) => {
    const area = rte.querySelector("[data-rte-area]"), input = rte.querySelector("[data-rte-input]");
    // ---- Spiel-Karten: im Editor sichtbar, im Formular als Platzhalter [[spiel:slug]] ----
    let GAMES = [];
    try { GAMES = JSON.parse((rte.querySelector("[data-games-data]") || {}).textContent || "[]"); } catch (e) {}
    const gameBlock = (slug) => {
      const g = GAMES.find((x) => x.slug === slug); if (!g) return null;
      const el = document.createElement("div");
      el.className = "mc-game"; el.contentEditable = "false"; el.dataset.game = slug; el.style.setProperty("--acc", g.accent);
      el.innerHTML = (g.cover ? '<img alt="">' : "") + '<div class="mc-game-b"><b></b><span></span><em></em></div><button type="button" class="mc-game-x" title="Spiel-Karte entfernen" aria-label="Spiel-Karte entfernen">×</button>';
      if (g.cover) el.querySelector("img").src = g.cover;
      el.querySelector("b").textContent = g.title;
      el.querySelector("span").textContent = g.tagline;
      el.querySelector("em").textContent = (/steampowered\.com/i.test(g.wish) ? "Jetzt auf Steam wunschlisten" : (g.wish ? "Jetzt auf die Wunschliste" : "Zum Spiel")) + " →";
      return el;
    };
    const hydrate = () => {                 // Platzhalter (z. B. aus einem Entwurf) wieder als Karte zeigen
      area.querySelectorAll("p").forEach((p) => {
        const m = p.textContent.trim().match(/^\[\[spiel:([a-z0-9-]+)\]\]$/i);
        if (m) { const b = gameBlock(m[1]); if (b) p.replaceWith(b); }
      });
    };
    const serialize = () => {
      const c = area.cloneNode(true);
      c.querySelectorAll(".mc-game").forEach((g) => { const p = document.createElement("p"); p.textContent = "[[spiel:" + g.dataset.game + "]]"; g.replaceWith(p); });
      return c.innerHTML;
    };
    const sync = () => {
      const empty = !area.textContent.trim() && !area.querySelector("li, blockquote, .mc-game");
      area.classList.toggle("is-empty", empty);
      input.value = empty ? "" : serialize();
      input.dispatchEvent(new Event("input", { bubbles: true }));
    };
    const gBtn = rte.querySelector("[data-game-menu]"), gList = rte.querySelector("[data-game-list]");
    if (gBtn && gList) {
      gList.innerHTML = GAMES.map((g, i) => `<button type="button" data-gi="${i}">${g.cover ? `<img src="${escHtml(g.cover)}" alt="">` : "<i></i>"}<span>${escHtml(g.title)}</span></button>`).join("");
      let saved = null;
      gBtn.addEventListener("mousedown", (e) => { e.preventDefault(); const sel = getSelection(); saved = sel.rangeCount && area.contains(sel.anchorNode) ? sel.getRangeAt(0).cloneRange() : null; });
      gBtn.addEventListener("click", () => { gList.hidden = !gList.hidden; });
      document.addEventListener("click", (e) => { if (!e.target.closest(".rte-gamewrap")) gList.hidden = true; });
      gList.addEventListener("mousedown", (e) => e.preventDefault());
      gList.addEventListener("click", (e) => {
        const b = e.target.closest("[data-gi]"); if (!b) return;
        const block = gameBlock(GAMES[+b.dataset.gi].slug);
        let blk = null;
        if (saved) { let n = saved.startContainer; while (n && n.parentNode !== area) n = n.parentNode; blk = n; }
        if (blk && blk.parentNode === area) blk.after(block); else area.appendChild(block);
        const after = document.createElement("p"); after.innerHTML = "<br>"; block.after(after);
        // Schreibmarke hinter die Karte setzen, damit weitergeschrieben werden kann
        area.focus();
        const r = document.createRange(); r.setStart(after, 0); r.collapse(true);
        const sel = getSelection(); sel.removeAllRanges(); sel.addRange(r);
        gList.hidden = true; sync();
        toast("Spiel-Karte eingefügt – in der Mail mit Cover und Wishlist-Button", "ok", 2600);
      });
    }
    area.addEventListener("click", (e) => { const x = e.target.closest(".mc-game-x"); if (x) { x.closest(".mc-game").remove(); sync(); } });
    hydrate();
    const blockOf = () => {
      const sel = getSelection(); if (!sel.rangeCount) return "";
      let n = sel.anchorNode; while (n && n !== area) { if (n.nodeType === 1 && /^(H3|BLOCKQUOTE|P|LI)$/.test(n.nodeName)) return n.nodeName; n = n.parentNode; }
      return "";
    };
    const exec = (cmd) => {
      area.focus();
      try { document.execCommand("defaultParagraphSeparator", false, "p"); } catch (e) {}
      if (cmd === "h3") document.execCommand("formatBlock", false, blockOf() === "H3" ? "p" : "h3");
      else if (cmd === "quote") document.execCommand("formatBlock", false, blockOf() === "BLOCKQUOTE" ? "p" : "blockquote");
      else if (cmd === "link") {
        const cur = getSelection().toString();
        let url = prompt("Link-Adresse (https://… oder mailto:…)", /^https?:\/\//.test(cur) ? cur : "https://");
        if (url === null) return;
        url = url.trim();
        if (/^www\./i.test(url)) url = "https://" + url;
        if (!/^(https?:\/\/|mailto:)/i.test(url) || url === "https://") { toast("Bitte eine vollständige Adresse mit https:// oder mailto: eingeben.", "info"); return; }
        if (getSelection().isCollapsed) document.execCommand("insertHTML", false, `<a href="${escHtml(url)}">${escHtml(url)}</a>`);
        else document.execCommand("createLink", false, url);
      } else if (cmd === "clear") { document.execCommand("removeFormat"); document.execCommand("unlink"); document.execCommand("formatBlock", false, "p"); }
      else document.execCommand(cmd);
      sync(); updState();
    };
    const updState = () => {
      rte.querySelectorAll("[data-cmd]").forEach((b) => {
        const c = b.dataset.cmd; let on = false;
        try { if (["bold", "italic", "underline", "insertUnorderedList", "insertOrderedList"].includes(c)) on = document.queryCommandState(c); } catch (e) {}
        if (c === "h3") on = blockOf() === "H3"; if (c === "quote") on = blockOf() === "BLOCKQUOTE";
        b.classList.toggle("on", !!on);
      });
    };
    rte.querySelector(".rte-bar").addEventListener("mousedown", (e) => { if (e.target.closest("[data-cmd]")) e.preventDefault(); });   // Auswahl im Text behalten
    rte.addEventListener("click", (e) => { const b = e.target.closest("[data-cmd]"); if (b) exec(b.dataset.cmd); });
    area.addEventListener("input", sync);
    area.addEventListener("keyup", updState); area.addEventListener("mouseup", updState);
    area.addEventListener("focus", () => { try { document.execCommand("defaultParagraphSeparator", false, "p"); } catch (e) {} });
    area.addEventListener("paste", (e) => {
      const cd = e.clipboardData; if (!cd) return;
      const html = cd.getData("text/html"), text = cd.getData("text/plain");
      e.preventDefault();
      if (html) document.execCommand("insertHTML", false, cleanPasted(html));
      else document.execCommand("insertHTML", false, escHtml(text).replace(/\r?\n/g, "<br>"));
      sync();
    });
    // Schnellantwort-Vorlage an der Schreibmarke einfügen
    const tplSel = rte.querySelector("[data-tpl-select]"), tplData = rte.querySelector("[data-tpl-data]");
    if (tplSel && tplData) {
      let bodies = []; try { bodies = JSON.parse(tplData.textContent || "[]"); } catch (e) {}
      tplSel.addEventListener("change", () => {
        const t = bodies[tplSel.value]; tplSel.value = "";
        if (!t) return;
        area.focus();
        document.execCommand("insertHTML", false, t.split(/\n{2,}/).map((p) => "<p>" + escHtml(p).replace(/\n/g, "<br>") + "</p>").join(""));
        sync();
      });
    }
    window.pgRteSet = (html) => { area.innerHTML = html; hydrate(); sync(); };
    sync();
  });

  // ---- Anhänge: auswählen/ziehen, Liste, Größe prüfen ----
  const composer = document.querySelector("[data-composer]");
  if (composer) {
    const attBox = composer.querySelector("[data-atts]");
    const BLOCK = /\.(exe|bat|cmd|com|scr|msi|jse?|vbe?|ps1|jar|lnk|reg|hta|cpl|pif|wsf|php|phtml)$/i;
    const max = +composer.dataset.max || 10485760;
    let files = [];
    const fmt = (b) => (b >= 1048576 ? (b / 1048576).toFixed(1).replace(".", ",") + " MB" : Math.max(1, Math.round(b / 1024)) + " KB");
    const fwdSize = () => [...composer.querySelectorAll("[data-fwd-size]")].filter((c) => c.checked).reduce((a, c) => a + (+c.dataset.fwdSize || 0), 0);
    const renderAtts = () => {
      if (!attBox) return;
      const list = attBox.querySelector("[data-att-list]");
      const total = files.reduce((a, f) => a + f.size, 0) + fwdSize();
      list.innerHTML = files.map((f, i) => `<li><span class="mx-att-ic">${escHtml((f.name.split(".").pop() || "").slice(0, 4).toUpperCase())}</span><span class="cmp-att-n">${escHtml(f.name)}</span><small>${fmt(f.size)}</small><button type="button" class="icon-btn sm" data-att-del="${i}" aria-label="Entfernen"><svg class="ic"><use href="#i-x"/></svg></button></li>`).join("")
        + (files.length || fwdSize() ? `<li class="cmp-att-sum${total > max ? " over" : ""}">${fmt(total)} von ${fmt(max)}${total > max ? " – zu groß" : ""}</li>` : "");
      const dt = new DataTransfer(); files.forEach((f) => dt.items.add(f));
      attBox.querySelector("[data-att-input]").files = dt.files;
    };
    const addFiles = (list) => {
      for (const f of list) {
        if (BLOCK.test(f.name)) { toast("„" + f.name + "“ – dieser Dateityp wird aus Sicherheitsgründen nicht verschickt.", "err", 4500); continue; }
        if (files.some((x) => x.name === f.name && x.size === f.size)) continue;
        if (files.reduce((a, x) => a + x.size, 0) + fwdSize() + f.size > max) { toast("„" + f.name + "“ passt nicht mehr – Anhänge zusammen höchstens " + fmt(max) + ".", "err", 4500); continue; }
        files.push(f);
      }
      renderAtts();
    };
    if (attBox) {
      const inp = attBox.querySelector("[data-att-input]"), drop = attBox.querySelector("[data-drop]");
      inp.addEventListener("change", () => { const l = [...inp.files]; addFiles(l); });
      attBox.addEventListener("click", (e) => { const d = e.target.closest("[data-att-del]"); if (d) { e.preventDefault(); files.splice(+d.dataset.attDel, 1); renderAtts(); } });
      ["dragenter", "dragover"].forEach((t) => composer.addEventListener(t, (e) => { if ([...(e.dataTransfer?.items || [])].some((i) => i.kind === "file")) { e.preventDefault(); drop.classList.add("over"); } }));
      ["dragleave", "drop"].forEach((t) => composer.addEventListener(t, () => drop.classList.remove("over")));
      composer.addEventListener("drop", (e) => { if (e.dataTransfer && e.dataTransfer.files.length) { e.preventDefault(); addFiles([...e.dataTransfer.files]); } });
      composer.querySelectorAll("[data-fwd-size]").forEach((c) => c.addEventListener("change", renderAtts));
      renderAtts();
    }
    // ---- Entwurf automatisch im Browser merken (pro Modus) ----
    const mode = composer.dataset.mode || "compose";
    const mbDraft = (composer.querySelector('input[name="mb"]') || {}).value || "main";
    const key = "pg_mail_draft_" + mode + (mode === "compose" && mbDraft !== "main" ? "_" + mbDraft : "");   // Entwurf je Postfach
    const st = composer.querySelector("[data-draft-status]");
    const fTo = composer.querySelector('[name="to"]'), fSub = composer.querySelector('[name="subject"]'), fSig = composer.querySelector("[data-sig-select]");
    const fHtml = composer.querySelector("[data-rte-input]");
    const hasContent = () => (fTo && fTo.value.trim()) || (fSub && fSub.value.trim()) || (fHtml && fHtml.value.trim());
    let dt = null;
    const saveDraft = () => {
      clearTimeout(dt);
      dt = setTimeout(() => {
        try {
          if (!hasContent()) { localStorage.removeItem(key); if (st) st.textContent = ""; return; }
          localStorage.setItem(key, JSON.stringify({ to: fTo ? fTo.value : "", subject: fSub ? fSub.value : "", html: fHtml ? fHtml.value : "", sig: fSig ? fSig.value : null, t: Date.now() }));
          if (st) st.textContent = "Entwurf gemerkt · " + new Date().toLocaleTimeString("de-DE", { hour: "2-digit", minute: "2-digit" });
        } catch (e) {}
      }, 600);
    };
    composer.addEventListener("input", saveDraft);
    composer.addEventListener("change", saveDraft);
    let saved = null; try { saved = JSON.parse(localStorage.getItem(key) || "null"); } catch (e) {}
    if (saved && !hasContent() && (saved.html || saved.subject || saved.to)) {
      const bar = document.createElement("div");
      bar.className = "cmp-restore";
      bar.innerHTML = `<svg class="ic"><use href="#i-clock"/></svg><span>Ungesendeter Entwurf vom ${new Date(saved.t).toLocaleString("de-DE", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" })}${saved.subject ? " – „" + escHtml(saved.subject) + "“" : ""}</span><button type="button" class="btn btn-sm" data-r="1">Wiederherstellen</button><button type="button" class="btn btn-sm btn-ghost" data-r="0">Verwerfen</button>`;
      composer.querySelector(".cmp-h").after(bar);
      bar.addEventListener("click", (e) => {
        const b = e.target.closest("[data-r]"); if (!b) return;
        if (b.dataset.r === "1") {
          if (fTo && saved.to) fTo.value = saved.to; if (fSub && saved.subject) fSub.value = saved.subject;
          if (fSig && saved.sig !== null && [...fSig.options].some((o) => o.value === saved.sig)) fSig.value = saved.sig;
          if (window.pgRteSet && saved.html) window.pgRteSet(saved.html);
          if (window.pgMailPreview) window.pgMailPreview();
        } else { try { localStorage.removeItem(key); } catch (err) {} }
        bar.remove();
      });
    }
    composer.addEventListener("submit", (e) => {
      if (fHtml && !fHtml.value.trim()) { e.preventDefault(); toast("Bitte eine Nachricht schreiben.", "info"); composer.querySelector("[data-rte-area]")?.focus(); return; }
      if (files.reduce((a, f) => a + f.size, 0) + fwdSize() > max) { e.preventDefault(); toast("Anhänge sind zusammen zu groß.", "err"); return; }
      try { localStorage.removeItem(key); } catch (err) {}
      const btn = composer.querySelector('button[type="submit"]');
      if (btn) { setTimeout(() => { btn.disabled = true; btn.textContent = "Wird gesendet …"; }, 0); }
    });
    // Vorschau ein-/ausblenden (Einstellung merken)
    const mx = document.querySelector("[data-mx]");
    const pv = (on) => { if (mx) mx.classList.toggle("pv-off", !on); try { localStorage.setItem("pg_mail_pv", on ? "1" : "0"); } catch (e) {} };
    let pvOn = innerWidth >= 1280; try { const v = localStorage.getItem("pg_mail_pv"); if (v !== null) pvOn = v === "1"; } catch (e) {}
    pv(pvOn);
    composer.querySelectorAll("[data-preview-toggle]").forEach((b) => b.addEventListener("click", () => pv(mx && mx.classList.contains("pv-off"))));
  }

  // ---- Liste filtern ----
  const mxSearch = document.querySelector("[data-mx-search]");
  if (mxSearch) mxSearch.addEventListener("input", () => {
    const q = mxSearch.value.trim().toLowerCase();
    document.querySelectorAll("[data-mx-rows] .mx-row").forEach((r) => { r.parentElement.style.display = !q || (r.dataset.q || "").includes(q) ? "" : "none"; });
  });

  // ---- Signaturen verwalten ----
  const sigDlg = document.getElementById("dlg-sigs"), sigData = document.getElementById("pg-sigs-data");
  if (sigDlg && sigData) {
    let SD = { sigs: [], mine: "", team: [] }; try { SD = JSON.parse(sigData.textContent); } catch (e) {}
    const listEl = sigDlg.querySelector("[data-sig-list]");
    let rows = [], mineIdx = -1;
    const fields = [["label", "Bezeichnung", "z. B. Dominik"], ["name", "Name", "Dominic Majewski"], ["role", "Rolle / Titel", "Founder · PLANIGAMES"], ["closing", "Grußzeile", "Beste Grüße"], ["email", "Absender-Adresse", "z. B. dominik@planigames.com · leer = Studio-Adresse"], ["phone", "Telefon (optional)", "+49 …"]];
    const st = SD.studio || { name: "PLANIGAMES", email: "" };
    const fromLine = (r) => {
      const studio = r.id === "studio";
      const name = !studio && r.name ? r.name + " | " + st.name : st.name;
      const mail = !studio && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(r.email || "") ? r.email : st.email;
      return "Erscheint beim Empfänger als <b>" + escHtml(name) + "</b> &lt;" + escHtml(mail) + "&gt; · Antworten gehen an " + escHtml(mail);
    };
    const draw = () => {
      listEl.innerHTML = rows.length ? rows.map((r, i) => `<div class="sig-card" data-i="${i}">
          <div class="sig-card-h"><b>${escHtml(r.label || r.name || "Neue Signatur")}</b>
            <label class="sig-mine"><input type="radio" name="sig-mine" value="${i}" ${mineIdx === i ? "checked" : ""}> Mein Standard</label>
            <button type="button" class="icon-btn sm" data-sig-del="${i}" title="Löschen" aria-label="Löschen"><svg class="ic"><use href="#i-trash"/></svg></button></div>
          <div class="sig-from" data-sig-from>${fromLine(r)}</div>
          <div class="sig-grid">${fields.map(([k, l, ph]) => `<label class="field"><span class="flabel">${l}</span><input type="text" data-k="${k}" value="${escHtml(r[k] || "")}" placeholder="${escHtml(ph)}"></label>`).join("")}</div></div>`).join("")
        + `<label class="sig-none"><input type="radio" name="sig-mine" value="-1" ${mineIdx === -1 ? "checked" : ""}> Standardmäßig ohne Signatur schreiben</label>`
        : '<p class="hint">Noch keine Signatur – lege eine an oder übernimm sie aus den Team-Profilen.</p>';
    };
    const open = () => {
      rows = SD.sigs.map((x) => ({ ...x }));
      mineIdx = rows.findIndex((x) => x.id === SD.mine);
      draw(); sigDlg.hidden = false;
    };
    document.addEventListener("click", (e) => { if (e.target.closest("[data-open-sigs]")) { e.preventDefault(); open(); } });
    if (new URLSearchParams(location.search).get("sigs") === "1") open();     // aus „Kontaktformular“/„Schnellantworten“ kommend
    listEl.addEventListener("input", (e) => {
      const inp = e.target.closest("[data-k]"); if (!inp) return;
      const card = inp.closest(".sig-card"), i = +card.dataset.i;
      rows[i][inp.dataset.k] = inp.value;
      if (inp.dataset.k === "label" || inp.dataset.k === "name") card.querySelector(".sig-card-h b").textContent = rows[i].label || rows[i].name || "Neue Signatur";
      card.querySelector("[data-sig-from]").innerHTML = fromLine(rows[i]);
    });
    listEl.addEventListener("change", (e) => { if (e.target.name === "sig-mine") mineIdx = +e.target.value; });
    listEl.addEventListener("click", (e) => {
      const d = e.target.closest("[data-sig-del]"); if (!d) return;
      const i = +d.dataset.sigDel;
      if (!confirm("Signatur „" + (rows[i].label || rows[i].name || "") + "“ löschen?")) return;
      rows.splice(i, 1); if (mineIdx === i) mineIdx = -1; else if (mineIdx > i) mineIdx--;
      draw();
    });
    sigDlg.querySelector("[data-sig-add]").addEventListener("click", () => { rows.push({ id: "", label: "", name: "", role: "", closing: "Beste Grüße", email: "", phone: "" }); draw(); listEl.querySelector(".sig-card:last-child input")?.focus(); });
    const teamBtn = sigDlg.querySelector("[data-sig-team]");
    if (teamBtn) teamBtn.addEventListener("click", () => {
      let n = 0;
      (SD.team || []).forEach((m) => {
        // persönliche Signatur schon vorhanden? (die Studio-Signatur „PLANIGAMES“ zählt nicht)
        if (rows.some((r) => r.id !== "studio" && (r.label || "").toLowerCase() !== "planigames" && (r.name || "").trim().toLowerCase() === m.name.trim().toLowerCase())) return;
        rows.push({ id: "", label: m.name.split(" ")[0], name: m.name, role: m.role ? m.role + " · PLANIGAMES" : "PLANIGAMES", closing: "Beste Grüße", email: "", phone: "" }); n++;
      });
      draw();
      toast(n ? n + " Signatur" + (n === 1 ? "" : "en") + " aus den Team-Profilen übernommen – prüfen und speichern." : "Alle Team-Mitglieder haben schon eine Signatur.", "info");
    });
    sigDlg.querySelector("[data-sig-save]").addEventListener("click", async (e) => {
      const btn = e.currentTarget; btn.disabled = true;
      const fd = new URLSearchParams(); fd.append("csrf", csrf()); fd.append("sigs", JSON.stringify(rows)); fd.append("mine", String(mineIdx));
      try {
        const r = await fetch("mail.php?action=sigs_save", { method: "POST", body: fd, headers: { "X-Requested-With": "fetch" } });
        const j = await readJson(r);
        if (!j.ok) throw new Error(j.error || "Fehler");
        SD.sigs = j.sigs; SD.mine = j.default;
        document.querySelectorAll("[data-sig-select]").forEach((sel) => {
          const cur = sel.value;
          sel.innerHTML = (j.options || []).map((o) => `<option value="${escHtml(o.id)}">${escHtml(o.text)}</option>`).join("");
          sel.value = j.sigs.some((x) => x.id === cur) ? cur : (j.default || "");
          sel.dispatchEvent(new Event("change", { bubbles: true }));
        });
        sigDlg.hidden = true;
        toast("Signaturen gespeichert", "ok");
      } catch (err) { toast("Speichern fehlgeschlagen: " + err.message, "err"); }
      btn.disabled = false;
    });
  }

  /* =========================================================
     Übersetzung – Deutsch ist die Vorlage
     ========================================================= */
  async function trCall(action, data) {
    const fd = new FormData();
    fd.append("csrf", csrf());
    Object.entries(data).forEach(([k, v]) => (Array.isArray(v) ? v.forEach((x) => fd.append(k + "[]", x)) : fd.append(k, v)));
    try {
      const r = await fetch("index.php?action=" + action, { method: "POST", body: fd, headers: { "X-Requested-With": "fetch" } });
      return await readJson(r);
    } catch (e) { return { ok: false, error: "Keine Verbindung zum Server." }; }
  }
  const trSleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const trCfg = (() => { try { return JSON.parse((document.getElementById("pg-tr-cfg") || {}).textContent || "{}"); } catch (e) { return {}; } })();

  // ---- Hintergrund: veraltete/fehlende englische Texte automatisch nachziehen ----
  let trBgRunning = false, trBgTimer = null;
  function trPill(text) {
    let p = document.getElementById("tr-pill");
    if (text === null) { if (p) { p.classList.remove("show"); setTimeout(() => p.remove(), 350); } return; }
    if (!p) { p = document.createElement("div"); p.id = "tr-pill"; p.className = "tr-pill"; p.setAttribute("role", "status"); document.body.appendChild(p); }
    p.innerHTML = '<span class="tr-pill-spin"></span><span></span>';
    p.lastChild.textContent = text;
    requestAnimationFrame(() => p.classList.add("show"));
  }
  async function trBackground(pending) {
    const keys = Object.keys(pending || {}).filter((k) => pending[k] > 0);
    if (trBgRunning || !keys.length) return;
    trBgRunning = true;
    const total = keys.reduce((a, k) => a + Number(pending[k] || 0), 0);
    let done = 0;
    trPill(`Englisch wird nachgezogen … 0 / ${total}`);
    try {
      outer: for (const key of keys) {
        for (let round = 0; round < 60; round++) {
          const j = await trCall("tr_sync", { collection: key, scope: "", mode: "auto" });
          if (j.busy) break outer;                                   // läuft bereits (z. B. anderer Tab)
          if (!j.ok) { toast(j.error || "Englisch konnte nicht nachgezogen werden.", "err", 6000); break outer; }
          done += j.done || 0;
          trPill(`Englisch wird nachgezogen … ${Math.min(done, total)} / ${total}`);
          if (j.unavailable) { toast("Übersetzungsdienst gerade nicht erreichbar – die Website zeigt solange den deutschen Text. Es geht später automatisch weiter.", "info", 7000); break outer; }
          if (!j.pending || !j.done) break;
        }
      }
    } finally {
      trPill(null);
      trBgRunning = false;
    }
    if (done) toast(`Englisch aktualisiert: ${done} Text${done === 1 ? "" : "e"} neu übersetzt`, "ok", 3500);
  }
  window.pgTrKick = (pending, delay) => {
    if (!trCfg.auto || document.querySelector("form[data-tr]")) return;
    clearTimeout(trBgTimer);
    trBgTimer = setTimeout(() => trBackground(pending), delay || 0);
  };
  if (trCfg.auto && trCfg.pending && Object.keys(trCfg.pending).length) window.pgTrKick(trCfg.pending, 1500);

  // ---- Übersetzungs-Ansicht ----
  (function trView() {
    const form = document.querySelector("form[data-tr]");
    if (!form) return;
    const key = form.dataset.key, scope = form.dataset.scope || "";
    const rows = [...form.querySelectorAll("[data-tr-row]")];
    const bar = form.querySelector("[data-tr-savebar]"), dirtyEl = form.querySelector("[data-tr-dirty]");
    const dirty = new Set();
    let filter = ((form.querySelector("[data-trf].on") || {}).dataset || {}).trf || "all";

    const grow = (ta) => { if (ta.tagName !== "TEXTAREA") return; ta.style.height = "auto"; ta.style.height = (ta.scrollHeight + 2) + "px"; };
    rows.forEach((row) => {
      const inp = row.querySelector("[data-tr-input]");
      if (inp) { inp.dataset.orig = inp.value; grow(inp); }
      const chip = row.querySelector("[data-tr-chip]");
      if (chip) { chip.dataset.cls = chip.className; chip.dataset.txt = chip.textContent; }
    });
    addEventListener("resize", () => rows.forEach((r) => { const t = r.querySelector("textarea"); if (t) grow(t); }));

    function setChip(row, cls, txt) {
      const chip = row.querySelector("[data-tr-chip]");
      if (!chip) return;
      if (!cls) { chip.className = chip.dataset.cls; chip.innerHTML = "<i></i>" + chip.dataset.txt.trim(); return; }
      chip.className = "tr-chip " + cls; chip.innerHTML = "<i></i>" + txt;
    }
    function refreshBar() {
      const n = dirty.size;
      if (dirtyEl) dirtyEl.textContent = n ? `${n} ungespeicherte Änderung${n === 1 ? "" : "en"}` : "Keine ungespeicherten Änderungen";
      if (bar) bar.classList.toggle("is-dirty", n > 0);
    }
    function markDirty(row, on) {
      if (on) dirty.add(row); else dirty.delete(row);
      row.classList.toggle("is-dirty", on);
      refreshBar();
    }
    function applyFilter() {
      let any = 0;
      form.querySelectorAll("[data-tr-item]").forEach((it) => {
        let itemAny = false;
        it.querySelectorAll("[data-tr-group]").forEach((g) => {
          let gAny = false;
          g.querySelectorAll("[data-tr-row]").forEach((row) => {
            const show = filter === "all" || row.dataset.status !== "ok" || dirty.has(row);
            row.hidden = !show; if (show) gAny = true;
          });
          g.hidden = !gAny; if (gAny) itemAny = true;
        });
        it.hidden = !itemAny; if (itemAny) any++;
      });
      const none = form.querySelector("[data-tr-none]");
      if (none) none.hidden = !(filter === "todo" && !any);
      rows.forEach((r) => { if (!r.hidden) { const t = r.querySelector("textarea"); if (t) grow(t); } });
    }
    form.querySelectorAll("[data-trf]").forEach((b) => b.addEventListener("click", () => {
      filter = b.dataset.trf;
      form.querySelectorAll("[data-trf]").forEach((x) => x.classList.toggle("on", x === b));
      applyFilter();
    }));
    applyFilter();

    form.addEventListener("input", (e) => {
      const row = e.target.closest("[data-tr-row]");
      if (!row || !e.target.matches("[data-tr-input]")) return;
      grow(e.target);
      const changed = e.target.value !== e.target.dataset.orig;
      const okd = !!row.querySelector('input[name^="trok["]');
      if (changed) setChip(row, "st-edited", "geändert");
      else if (okd) setChip(row, "st-confirmed", "bestätigt");
      else setChip(row, null);
      markDirty(row, changed || okd);
    });
    form.addEventListener("click", async (e) => {
      const one = e.target.closest("[data-tr-one]"), okb = e.target.closest("[data-tr-ok]");
      if (okb) {
        const row = okb.closest("[data-tr-row]");
        if (!row.querySelector('input[name^="trok["]')) {
          const h = document.createElement("input");
          h.type = "hidden"; h.name = "trok[" + row.dataset.ip + "]"; h.value = "1";
          row.appendChild(h);
        }
        setChip(row, "st-confirmed", "bestätigt");
        markDirty(row, true);
        okb.remove();
        return;
      }
      if (one) {
        const row = one.closest("[data-tr-row]");
        one.disabled = true; one.classList.add("busy");
        const j = await trCall("tr_one", { collection: key, scope, ip: row.dataset.ip });
        one.disabled = false; one.classList.remove("busy");
        if (!j.ok) { toast(j.error || "Übersetzen fehlgeschlagen", "err", 6000); return; }
        const inp = row.querySelector("[data-tr-input]");
        inp.value = j.en; grow(inp);
        const hh = row.querySelector("[data-tr-hash]"); if (hh && j.h) hh.value = j.h;
        setChip(row, "st-fresh", "neu übersetzt");
        markDirty(row, true);
      }
    });

    // Nur geänderte Zeilen absenden (kleine Anfrage, nichts wird versehentlich überschrieben)
    function strip(fd) {
      rows.forEach((row) => {
        if (dirty.has(row)) return;
        fd.delete("tr[" + row.dataset.ip + "]"); fd.delete("trh[" + row.dataset.ip + "]");
      });
      return fd;
    }
    let submitting = false;
    form.addEventListener("submit", (e) => {
      submitting = true;
      rows.forEach((row) => {
        if (dirty.has(row)) return;
        row.querySelectorAll("[data-tr-input],[data-tr-hash]").forEach((x) => { x.disabled = true; });
      });
    });
    addEventListener("beforeunload", (e) => { if (dirty.size && !submitting) { e.preventDefault(); e.returnValue = ""; } });

    async function saveFirst() {
      if (!dirty.size) return true;
      const fd = strip(new FormData(form)); fd.append("ajax", "1");
      try {
        const r = await fetch(form.action, { method: "POST", body: fd, headers: { "X-Requested-With": "fetch" } });
        const j = await readJson(r);
        if (!j.ok) { toast(j.error || "Speichern fehlgeschlagen", "err", 6000); return false; }
      } catch (err) { toast("Keine Verbindung zum Server.", "err", 6000); return false; }
      dirty.clear(); rows.forEach((r) => r.classList.remove("is-dirty")); refreshBar();
      return true;
    }
    function progress(mode) {
      const back = document.createElement("div");
      back.className = "pg-modal-back tr-prog";
      back.innerHTML = `<div class="pg-modal" role="dialog" aria-modal="true" aria-live="polite"><h3>${mode === "confirm" ? "Wird als geprüft markiert …" : "Englisch wird aus dem Deutschen übersetzt …"}</h3>`
        + `<div class="tr-prog-bar"><i></i></div><p class="tr-prog-n">Einen Moment …</p>`
        + `<div class="pg-modal-f"><button type="button" class="btn btn-sm" data-c>Abbrechen</button></div></div>`;
      document.body.appendChild(back);
      const api = {
        stop: false,
        update(n, t) {
          back.querySelector(".tr-prog-n").textContent = t ? `${n} von ${t} Texten erledigt` : `${n} Texte erledigt`;
          back.querySelector(".tr-prog-bar i").style.width = (t ? Math.min(100, (n / t) * 100) : 100) + "%";
        },
        close() { back.remove(); },
      };
      back.querySelector("[data-c]").addEventListener("click", (ev) => { api.stop = true; ev.target.textContent = "Wird beendet …"; ev.target.disabled = true; });
      return api;
    }
    async function run(mode) {
      if (mode === "force" && !confirm("Alle englischen Texte hier neu aus dem Deutschen übersetzen?\n\nEigene Anpassungen am Englischen werden dabei ersetzt. Vorher wird automatisch eine Sicherheitskopie angelegt (Backups).")) return;
      if (mode === "confirm" && !confirm("Alle ungeprüften und veralteten englischen Texte als „passt“ markieren?\n\nSie gelten dann als aktuell und erscheinen so auf der Website.")) return;
      if (!(await saveFirst())) return;
      const ui = progress(mode);
      let queue = mode === "force" ? rows.map((r) => r.dataset.ip) : [];
      const total = mode === "force" ? queue.length : Number(form.dataset.run || 0);
      let done = 0, first = true, busy = 0, failed = "";
      ui.update(0, total);
      while (!ui.stop) {
        const payload = { collection: key, scope, mode };
        if (mode === "force") { if (!queue.length) break; payload.ips = queue.slice(0, 30); if (first) payload.first = "1"; }
        const j = await trCall("tr_sync", payload);
        if (j.busy) { if (++busy > 6) { failed = "busy"; break; } await trSleep(2500); continue; }
        first = false;
        if (!j.ok) { failed = "err"; break; }
        done += j.done || 0;
        if (mode === "force") { const got = new Set(Object.keys(j.texts || {})); queue = queue.filter((ip) => !got.has(ip)); }
        ui.update(done, total || done + (j.pending || 0));
        if (j.unavailable) { failed = "unavail"; break; }
        if (mode === "confirm") break;
        if (mode === "auto" && (!j.pending || !j.done)) break;
        if (mode === "force" && !j.done) break;
      }
      ui.close();
      const u = new URL(location.href);
      u.searchParams.delete("saved"); u.searchParams.delete("trfail");
      u.searchParams.set("trdone", String(done));
      if (failed) u.searchParams.set("trfail", failed);
      location.href = u.toString();
    }
    form.querySelectorAll("[data-tr-run]").forEach((b) => b.addEventListener("click", () => run(b.dataset.trRun)));

    const auto = form.querySelector("[data-tr-auto]");
    if (auto) auto.addEventListener("change", async () => {
      const j = await trCall("tr_settings", { auto: auto.checked ? "1" : "" });
      if (!j.ok) { auto.checked = !auto.checked; toast(j.error || "Konnte die Einstellung nicht speichern.", "err", 5000); return; }
      toast(auto.checked ? "Englisch wird ab jetzt automatisch nachgezogen." : "Automatisches Nachziehen aus – veraltete Texte zeigen auf der Website solange Deutsch.", "ok", 4000);
    });
  })();

  /* ---- Cockpit: Nachrichten im Hintergrund aktualisieren, weitere Aufgaben aufklappen ---- */
  (function cockpit() {
    const box = document.querySelector(".ck-inbox[data-ck-refresh]");
    if (box) {
      const sync = box.querySelector("[data-ck-sync]");
      fetch("index.php?action=ck_inbox", { headers: { "X-Requested-With": "fetch" } }).then(readJson).then((j) => {
        if (sync) sync.hidden = true;
        if (!j || !j.ok) return;
        const list = box.querySelector("[data-ck-msgs]");
        if (list && typeof j.html === "string") list.innerHTML = j.html;
        const c = box.querySelector("[data-ck-unread]");
        if (c) { c.textContent = j.unread; c.classList.toggle("zero", !j.unread); }
      }).catch(() => { if (sync) sync.hidden = true; });
    }
    document.querySelectorAll("[data-ck-showmore]").forEach((b) => b.addEventListener("click", () => {
      b.closest(".ck-tasks").querySelectorAll(".ck-more-item").forEach((li) => { li.hidden = false; });
      b.remove();
    }));
  })();

  /* ---- Devlog & Team: Liste durchsuchen, filtern, (Team) per Ziehen sortieren ---- */
  (function itemList() {
    const rows = document.querySelector(".it-rows");
    if (!rows) return;
    const search = document.querySelector("[data-it-search]"), filter = document.querySelector("[data-it-filter]");
    let want = "";
    const apply = () => {
      const q = (search ? search.value : "").trim().toLowerCase();
      rows.querySelectorAll(".gm-row").forEach((r) => {
        r.style.display = (!q || (r.dataset.q || "").includes(q)) && (!want || r.dataset.state === want) ? "" : "none";
      });
    };
    if (search) search.addEventListener("input", apply);
    if (filter) filter.addEventListener("click", (e) => {
      const b = e.target.closest("[data-if]"); if (!b) return;
      want = b.dataset.if; filter.querySelectorAll("[data-if]").forEach((x) => x.classList.toggle("on", x === b)); apply();
    });
    if (!rows.hasAttribute("data-it-reorder")) return;
    let dg = null;
    rows.addEventListener("pointerdown", (e) => {
      const grip = e.target.closest("[data-it-grip]"); if (!grip) return;
      const row = grip.closest(".gm-row"); e.preventDefault();
      dg = { row, before: [...rows.querySelectorAll(".gm-row")].map((r) => r.dataset.key).join("|") };
      row.classList.add("dragging"); document.body.classList.add("is-dragging");
      try { grip.setPointerCapture(e.pointerId); } catch {}
    });
    rows.addEventListener("pointermove", (e) => {
      if (!dg) return; e.preventDefault();
      const others = [...rows.querySelectorAll(".gm-row")].filter((r) => r !== dg.row);
      let placed = false;
      for (const r of others) { const b = r.getBoundingClientRect(); if (e.clientY < b.top + b.height / 2) { rows.insertBefore(dg.row, r); placed = true; break; } }
      if (!placed) rows.appendChild(dg.row);
    });
    const end = async () => {
      if (!dg) return;
      const { row, before } = dg; dg = null;
      row.classList.remove("dragging"); document.body.classList.remove("is-dragging");
      const order = [...rows.querySelectorAll(".gm-row")].map((r) => Number(r.dataset.key));
      if (order.join("|") === before) return;
      const fd = new URLSearchParams(); fd.append("csrf", csrf()); fd.append("collection", "team"); fd.append("order", JSON.stringify(order));
      const j = await fetch("index.php?action=items_reorder", { method: "POST", body: fd, headers: { "X-Requested-With": "fetch" } }).then(readJson).catch(() => ({ ok: false }));
      if (!j.ok) { toast("Reihenfolge konnte nicht gespeichert werden", "err"); return; }
      toast("Reihenfolge gespeichert – so erscheint sie auch auf der Website", "ok");
      // Positionen haben sich geändert → Links & Auswahl neu laden
      setTimeout(() => { const on = order.indexOf(Number((rows.querySelector(".gm-row.on") || {}).dataset?.key)); const u = new URL(location.href); if (on >= 0) u.searchParams.set("item", on); location.href = u.toString(); }, 600);
    };
    rows.addEventListener("pointerup", end);
    rows.addEventListener("pointercancel", end);
  })();

  /* ---- Bildfelder: Vorschau aktuell halten ---- */
  document.addEventListener("input", (e) => {
    const inp = e.target.closest("[data-media-input]"); if (!inp) return;
    const th = inp.closest("[data-media]")?.querySelector("[data-media-thumb]"); if (!th) return;
    const v = inp.value.split("#")[0].trim();
    th.innerHTML = v ? `<img src="${/^(https?:)?\/\/|^data:/.test(v) ? v : "../" + v.replace(/^\/+/, "")}" alt="">` : '<svg class="ic" aria-hidden="true"><use href="#i-image"/></svg>';
  });

  /* ---- Wortlisten (Tags) als Chips ---- */
  document.querySelectorAll("[data-tags]").forEach((box) => {
    const inp = box.querySelector("[data-tag-in]"), name = box.dataset.name;
    const add = (raw) => {
      raw.split(",").map((x) => x.trim()).filter(Boolean).forEach((v) => {
        const exists = [...box.querySelectorAll('input[type="hidden"]')].some((h) => h.value.toLowerCase() === v.toLowerCase());
        if (exists) return;
        const chip = document.createElement("span");
        chip.className = "tag-chip";
        chip.innerHTML = '<span></span><input type="hidden"><button type="button" data-tag-del><svg class="ic" aria-hidden="true"><use href="#i-x"/></svg></button>';
        chip.firstChild.textContent = v;
        const h = chip.querySelector("input"); h.name = name + "[n" + (uid++) + "]"; h.value = v;
        chip.querySelector("button").setAttribute("aria-label", v + " entfernen");
        inp.before(chip);
      });
      inp.value = "";
      box.dispatchEvent(new Event("input", { bubbles: true }));
    };
    inp.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === ",") { e.preventDefault(); add(inp.value); }
      else if (e.key === "Backspace" && inp.value === "") { const last = inp.previousElementSibling; if (last && last.classList.contains("tag-chip")) { last.remove(); box.dispatchEvent(new Event("input", { bubbles: true })); } }
    });
    inp.addEventListener("blur", () => { if (inp.value.trim()) add(inp.value); });
    box.addEventListener("click", (e) => {
      const del = e.target.closest("[data-tag-del]");
      if (del) { del.closest(".tag-chip").remove(); box.dispatchEvent(new Event("input", { bubbles: true })); return; }
      if (e.target.closest(".tags-box") && e.target.tagName !== "INPUT") inp.focus();
    });
  });

  /* ---- Bereiche mit Reitern (Studio & Startseite, Rechtliches) ---- */
  (function sections() {
    const root = document.querySelector("[data-sections]");
    if (!root) return;
    const show = (id) => {
      root.querySelectorAll("[data-sx]").forEach((b) => b.classList.toggle("on", b.dataset.sx === id));
      root.querySelectorAll("[data-sx-panel]").forEach((p) => p.classList.toggle("on", p.dataset.sxPanel === id));
      const inp = root.querySelector("[data-sx-input]"); if (inp) inp.value = id;
      try { const u = new URL(location.href); u.searchParams.set("tab", id); ["saved", "error", "sent"].forEach((k) => u.searchParams.delete(k)); history.replaceState(null, "", u); } catch {}
      root.querySelectorAll(`[data-sx-panel="${id}"] textarea`).forEach((t) => t.dispatchEvent(new Event("pg:shown")));
    };
    root.addEventListener("click", (e) => {
      const b = e.target.closest("[data-sx]");
      if (b) { e.preventDefault(); show(b.dataset.sx); if (innerWidth < 860) root.querySelector(".sx-body").scrollIntoView({ behavior: "smooth", block: "start" }); }
    });
  })();

  /* ---- Board: Aufklapp-Menüs (Board, Spalte, Neues Board) schließen beim Klick daneben ---- */
  document.addEventListener("click", (e) => {
    document.querySelectorAll("details.kb-pop[open]").forEach((d) => { if (!d.contains(e.target)) d.open = false; });
  });
  // Karte aus dem Kalender (#card-…) direkt öffnen
  if (location.hash.startsWith("#card-")) {
    const card = document.getElementById(location.hash.slice(1));
    const ed = card && card.querySelector(".kb-cardedit");
    if (ed) { card.scrollIntoView({ block: "center" }); card.classList.add("kb-flash"); setTimeout(() => card.classList.remove("kb-flash"), 1800); }
  }

  /* ---- Statistik: Verlauf als Linie + Fläche, Fadenkreuz-Tooltip (Maus & Tastatur) ---- */
  document.querySelectorAll("[data-st-chart]").forEach((el) => {
    let data = [];
    try { data = JSON.parse(el.querySelector("script").textContent || "[]"); } catch (e) { return; }
    if (!data.length) return;
    const COLOR = "#f06f15";                          // geprüft: Helligkeit, Sättigung, Kontrast auf dunklem Grund
    const fmt = (n) => Number(n).toLocaleString("de-DE");
    const fmtD = (d, long) => { const [y, m, dd] = d.split("-").map(Number); const dt = new Date(y, m - 1, dd);
      return long ? dt.toLocaleDateString("de-DE", { weekday: "short", day: "numeric", month: "long" }) : dd + "." + m + "."; };
    const nice = (max) => { if (max <= 4) return 4; const p = Math.pow(10, Math.floor(Math.log10(max))); const n = max / p; return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p; };
    const tip = document.createElement("div"); tip.className = "st-tip"; tip.hidden = true;
    let sel = -1, geo = null;
    function draw() {
      const W = Math.max(280, el.clientWidth), H = W < 560 ? 210 : 270;
      const pad = { l: 44, r: 18, t: 14, b: 28 };
      const pw = W - pad.l - pad.r, ph = H - pad.t - pad.b;
      const yMax = nice(Math.max(...data.map((x) => x.v)) * 1.08);
      const X = (i) => pad.l + (data.length === 1 ? pw / 2 : (i / (data.length - 1)) * pw);
      const Y = (v) => pad.t + ph - (v / yMax) * ph;
      geo = { X, Y, pad, pw, ph, W, H };
      let svg = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" aria-hidden="true">`;
      for (let k = 0; k <= 4; k++) {
        const v = (yMax / 4) * k, y = Y(v);
        svg += `<line x1="${pad.l}" x2="${W - pad.r}" y1="${y}" y2="${y}" class="st-grid"/><text x="${pad.l - 8}" y="${y + 4}" class="st-ax" text-anchor="end">${fmt(Math.round(v))}</text>`;
      }
      const step = Math.max(1, Math.ceil(data.length / (W < 560 ? 4 : 7)));
      data.forEach((x, i) => { if (i % step === 0 || i === data.length - 1) svg += `<text x="${X(i)}" y="${H - 8}" class="st-ax" text-anchor="${i === 0 ? "start" : i === data.length - 1 ? "end" : "middle"}">${fmtD(x.d)}</text>`; });
      const pts = data.map((x, i) => `${X(i).toFixed(1)},${Y(x.v).toFixed(1)}`);
      svg += `<path d="M${pts[0]} L${pts.join(" L")} L${X(data.length - 1)},${Y(0)} L${X(0)},${Y(0)} Z" fill="${COLOR}" fill-opacity=".1"/>`;
      svg += `<path d="M${pts.join(" L")}" fill="none" stroke="${COLOR}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>`;
      const li = data.length - 1;
      svg += `<circle cx="${X(li)}" cy="${Y(data[li].v)}" r="4" fill="${COLOR}" stroke="#121216" stroke-width="2"/>`;
      svg += `<text x="${X(li) - 8}" y="${Y(data[li].v) - 10}" class="st-endlbl" text-anchor="end">${fmt(data[li].v)}</text>`;
      svg += `<g class="st-cross" style="display:none"><line class="st-crossl" y1="${pad.t}" y2="${pad.t + ph}"/><circle r="5" fill="${COLOR}" stroke="#121216" stroke-width="2"/></g></svg>`;
      el.innerHTML = svg; el.appendChild(tip);
      if (sel >= 0) show(sel);
    }
    function show(i) {
      sel = Math.max(0, Math.min(data.length - 1, i));
      const g = el.querySelector(".st-cross"); if (!g || !geo) return;
      const x = geo.X(sel), y = geo.Y(data[sel].v);
      g.style.display = ""; g.querySelector("line").setAttribute("x1", x); g.querySelector("line").setAttribute("x2", x);
      g.querySelector("circle").setAttribute("cx", x); g.querySelector("circle").setAttribute("cy", y);
      tip.hidden = false;
      tip.replaceChildren();
      const v = document.createElement("b"); v.textContent = fmt(data[sel].v) + (data[sel].v === 1 ? " Aufruf" : " Aufrufe");
      const d = document.createElement("span"); d.textContent = fmtD(data[sel].d, true);
      tip.append(v, d);
      const tw = tip.offsetWidth || 140;
      tip.style.left = Math.min(Math.max(x - tw / 2, 4), geo.W - tw - 4) + "px";
      tip.style.top = Math.max(0, y - 58) + "px";
    }
    function hide() { sel = -1; tip.hidden = true; const g = el.querySelector(".st-cross"); if (g) g.style.display = "none"; }
    el.addEventListener("pointermove", (e) => {
      if (!geo) return;
      const r = el.getBoundingClientRect(), x = e.clientX - r.left;
      const i = Math.round(((x - geo.pad.l) / geo.pw) * (data.length - 1));
      show(i);
    });
    el.addEventListener("pointerleave", hide);
    el.addEventListener("focus", () => show(data.length - 1));
    el.addEventListener("blur", hide);
    el.addEventListener("keydown", (e) => {
      if (e.key === "ArrowLeft") { e.preventDefault(); show((sel < 0 ? data.length : sel) - 1); }
      if (e.key === "ArrowRight") { e.preventDefault(); show((sel < 0 ? -1 : sel) + 1); }
      if (e.key === "Home") { e.preventDefault(); show(0); }
      if (e.key === "End") { e.preventDefault(); show(data.length - 1); }
    });
    draw();
    let rt = null; addEventListener("resize", () => { clearTimeout(rt); rt = setTimeout(draw, 120); });
  });

  /* ---- Schnellantworten: Vorlagen hinzufügen/entfernen ---- */
  (function templates() {
    const grid = document.querySelector("[data-tpl-grid]"); if (!grid) return;
    const tpl = document.querySelector("template[data-tpl-new]"), empty = document.querySelector("[data-tpl-empty]");
    const refresh = () => { if (empty) empty.hidden = !!grid.querySelector("[data-tpl-card]"); };
    document.querySelector("[data-tpl-add]").addEventListener("click", () => {
      grid.insertAdjacentHTML("beforeend", tpl.innerHTML.split("__N__").join("n" + uid++));
      const c = grid.lastElementChild; c.querySelector("input").focus(); refresh();
      grid.closest("form").dispatchEvent(new Event("input", { bubbles: true }));
    });
    grid.addEventListener("click", (e) => {
      const d = e.target.closest("[data-tpl-del]"); if (!d) return;
      const c = d.closest("[data-tpl-card]");
      const filled = [...c.querySelectorAll("input,textarea")].some((x) => x.value.trim());
      if (filled && !confirm("Diese Vorlage entfernen? (Wirksam nach „Speichern“)")) return;
      c.remove(); refresh();
      grid.closest("form").dispatchEvent(new Event("input", { bubbles: true }));
    });
  })();

  /* ---- Tabellen durchsuchen (Warteliste, Abos, …) ---- */
  document.querySelectorAll("[data-dt-search]").forEach((inp) => {
    const table = inp.closest("section, .ck-card, body").querySelector("[data-dt]"); if (!table) return;
    inp.addEventListener("input", () => {
      const q = inp.value.trim().toLowerCase();
      table.querySelectorAll("tbody tr").forEach((tr) => { tr.hidden = !!q && !(tr.dataset.q || tr.textContent.toLowerCase()).includes(q); });
    });
  });

  /* ---- Warteliste anschreiben ---- */
  (function wlMail() {
    const form = document.querySelector("[data-wl-form]"); if (!form) return;
    const frame = form.querySelector("[data-wl-frame]"), sum = form.querySelector("[data-wl-sum]");
    let D = { all: 0, new: 0, keys: {}, title: "" };
    try { D = JSON.parse(document.getElementById("wl-data").textContent); } catch (e) {}
    const post = (a, extra) => { const f = new FormData(form); Object.entries(extra || {}).forEach(([k, v]) => f.set(k, v)); return fetch("index.php?action=" + a, { method: "POST", body: f, headers: { "X-Requested-With": "fetch" } }); };
    let pt = null;
    const preview = () => { clearTimeout(pt); pt = setTimeout(async () => { try { const r = await post("wl_preview"); frame.srcdoc = await r.text(); } catch (e) {} }, 400); };
    const count = () => {
      const m = (form.querySelector("[name=mode]:checked") || {}).value;
      let n = m === "new" ? D.new : m === "top" ? Math.min(+form.querySelector("[name=n]").value || 0, D.all) : D.all;
      const k = form.querySelector("[name=keys]");
      if (k && k.checked) {
        const free = D.keys[(form.querySelector("[name=platform]") || {}).value] || 0;
        if (free < n) { sum.textContent = `Nur ${free} freie Keys – es gehen ${free} von ${n} Mails raus`; sum.classList.add("warn"); return free; }
      }
      sum.classList.remove("warn");
      sum.textContent = n + " Empfänger";
      return n;
    };
    form.addEventListener("input", () => { preview(); count(); });
    form.addEventListener("change", () => { preview(); count(); });
    form.addEventListener("submit", (e) => e.preventDefault());
    preview(); count();
    const ready = () => {
      if (!form.querySelector("[name=subject]").value.trim()) { toast("Bitte einen Betreff eingeben.", "info"); form.querySelector("[name=subject]").focus(); return false; }
      if (!form.querySelector("[data-rte-input]").value.trim()) { toast("Bitte eine Nachricht schreiben.", "info"); form.querySelector("[data-rte-area]").focus(); return false; }
      return true;
    };
    form.querySelector("[data-wl-test]").addEventListener("click", async (e) => {
      if (!ready()) return;
      e.target.disabled = true;
      const j = await post("wl_test").then(readJson).catch(() => ({ ok: false, error: "Keine Verbindung." }));
      e.target.disabled = false;
      toast(j.ok ? "Testmail an " + j.to + " gesendet" : (j.error || "Fehler"), j.ok ? "ok" : "err", 5000);
    });
    async function run(id, total) {
      const back = document.createElement("div");
      back.className = "pg-modal-back tr-prog";
      back.innerHTML = `<div class="pg-modal" role="dialog" aria-modal="true" aria-live="polite"><h3>Mails werden gesendet …</h3><div class="tr-prog-bar"><i></i></div><p class="tr-prog-n">Einen Moment …</p><div class="pg-modal-f"><button type="button" class="btn btn-sm" data-c>Pause</button></div></div>`;
      document.body.appendChild(back);
      let stop = false, busy = 0, last = null;
      back.querySelector("[data-c]").addEventListener("click", (ev) => { stop = true; ev.target.textContent = "Wird angehalten …"; ev.target.disabled = true; });
      while (!stop) {
        const j = await fetch("index.php?action=wl_step", { method: "POST", body: new URLSearchParams({ csrf: csrf(), id }), headers: { "X-Requested-With": "fetch" } }).then(readJson).catch(() => ({ ok: false, error: "Keine Verbindung." }));
        if (j.busy) { if (++busy > 8) break; await trSleep(2000); continue; }
        if (!j.ok) { toast(j.error || "Fehler beim Senden", "err", 7000); break; }
        last = j;
        const done = j.sent + j.failed + j.skipped;
        back.querySelector(".tr-prog-n").textContent = `${j.sent} gesendet${j.failed ? " · " + j.failed + " fehlgeschlagen" : ""}${j.skipped ? " · " + j.skipped + " übersprungen" : ""} – ${done} von ${j.total}`;
        back.querySelector(".tr-prog-bar i").style.width = Math.round((done / Math.max(1, j.total)) * 100) + "%";
        if (!j.left) break;
      }
      back.remove();
      if (last) {
        const u = new URL(location.href);
        ["wlsent", "wlfail", "wlskip", "wlpause"].forEach((k) => u.searchParams.delete(k));
        u.searchParams.set("wlsent", last.sent); if (last.failed) u.searchParams.set("wlfail", last.failed); if (last.skipped) u.searchParams.set("wlskip", last.skipped); if (last.left) u.searchParams.set("wlpause", "1");
        location.href = u.toString();
      }
    }
    form.querySelector("[data-wl-send]").addEventListener("click", async (e) => {
      if (!ready()) return;
      const n = count();
      if (!n) { toast("Keine Empfänger ausgewählt.", "info"); return; }
      const keys = form.querySelector("[name=keys]")?.checked;
      if (!confirm(`Jetzt ${n} Mail${n === 1 ? "" : "s"} an die Warteliste von ${D.title} senden${keys ? " – jede mit einem eigenen Key" : ""}?\n\nJede Mail geht einzeln raus und enthält einen Abmelde-Link.`)) return;
      e.target.disabled = true;
      const j = await post("wl_start").then(readJson).catch(() => ({ ok: false, error: "Keine Verbindung." }));
      if (!j.ok) { e.target.disabled = false; toast(j.error || "Fehler", "err", 6000); return; }
      try { localStorage.removeItem("pg_mail_draft_compose"); } catch (err) {}
      run(j.id, j.total);
    });
    const res = document.querySelector("[data-wl-resume]");
    if (res) res.addEventListener("click", () => run(res.dataset.wlResume, 0));
  })();

  // ---- Tastatur-Shortcuts ----
  function primarySaveButton() {
    return document.querySelector('[data-tr-savebar] button[type="submit"]')
      || document.querySelector('#editor button[name="save"], button[name="save"], form .btn-primary[type="submit"]');
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
      if (side && side.classList.contains("open")) closeDrawer();
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
      <div class="kbd-row"><span><kbd>Strg</kbd>/<kbd>⌘</kbd> + <kbd>K</kbd></span><span>Suchen &amp; springen</span></div>
      <div class="kbd-row"><span><kbd>?</kbd></span><span>Diese Hilfe</span></div>
      <div class="kbd-row"><span><kbd>Esc</kbd></span><span>Schließen / Menü zu</span></div>
      <button class="btn-add" data-kbd-close>Schließen</button>
    </div>`;
    d.addEventListener("click", (e) => { if (e.target === d || e.target.closest("[data-kbd-close]")) d.remove(); });
    document.body.appendChild(d);
  }

  /* ---- Postfach-Einstellungen: Verbindung testen (Hauptpostfach & persönliche Postfächer) ---- */
  const mbTest = (id, btn) => {
    const box = document.querySelector(`[data-mb-status="${CSS.escape(id)}"]`);
    if (!box) return;
    const esc = (t) => String(t || "").replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
    const row = (cls, ic, label, msg) => `<div class="mbx-t ${cls}"><i>${ic}</i><span><b>${label}:</b> ${esc(msg)}</span></div>`;
    box.hidden = false;
    box.innerHTML = row("wait", "…", "Verbindung", "wird geprüft – das kann ein paar Sekunden dauern …");
    if (btn) btn.disabled = true;
    const fd = new URLSearchParams(); fd.append("csrf", csrf()); fd.append("id", id);
    fetch("mail.php?action=mb_test", { method: "POST", body: fd, credentials: "same-origin", headers: { "X-Requested-With": "fetch" } })
      .then((r) => r.json())
      .then((j) => {
        if (!j || !j.ok) { box.innerHTML = row("bad", "✕", "Test", (j && j.error) || "Fehlgeschlagen."); return; }
        const one = (r, label) => row(r.ok === null ? "na" : r.ok ? "ok" : "bad", r.ok === null ? "!" : r.ok ? "✓" : "✕", label, r.msg);
        box.innerHTML = one(j.imap, "Empfang (IMAP)") + one(j.smtp, "Versand (SMTP)");
      })
      .catch(() => { box.innerHTML = row("bad", "✕", "Test", "Keine Antwort vom Server – bitte erneut versuchen."); })
      .finally(() => { if (btn) btn.disabled = false; });
  };
  document.addEventListener("click", (e) => {
    const b = e.target.closest("[data-mb-test]");
    if (b) { e.preventDefault(); mbTest(b.dataset.mbTest, b); }
  });
  { const auto = document.querySelector("[data-mb-autotest]"); if (auto) mbTest(auto.dataset.mbAutotest, auto.querySelector("[data-mb-test]")); }
})();
