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

  // ---- Benachrichtigungsglocke: Echtzeit-Aktualisierung (Polling) ----
  const notifBell = document.querySelector("[data-notif-bell]");
  if (notifBell) {
    const setBadge = (el, val) => { if (!el) return; if (val > 0) { el.textContent = val; el.hidden = false; } else { el.hidden = true; el.textContent = ""; } };
    let lastTotal = parseInt((document.querySelector("[data-notif-total]") || {}).textContent || "0", 10) || 0;
    async function pollNotifs() {
      try {
        const r = await fetch("index.php?action=notif_counts", { headers: { "X-Requested-With": "fetch" } });
        if (!r.ok) return;
        const j = await r.json();
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
    setInterval(pollNotifs, 30000);
    // bei Rückkehr zum Tab sofort aktualisieren
    document.addEventListener("visibilitychange", () => { if (!document.hidden) pollNotifs(); });
  }

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
          const j = await r.json();
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
        const j = await r.json();
        if (j.ok) {
          dirty = false; setStatus("Gespeichert ✓", "ok"); toast("Automatisch gespeichert", "ok", 1800);
          if (j.sent) toast("📣 Newsletter an " + j.sent + " gesendet", "info", 4000);
          reloadPreview();
        } else { setStatus("Fehler", "err"); toast("Speichern fehlgeschlagen", "err"); }
      } catch (e) { setStatus("Offline", "err"); }
      saving = false;
    }
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

  // ---- Mobiles Admin: untere Tab-Leiste ----
  (function mobileNav() {
    if (document.getElementById("pg-mobnav")) return;
    if (!document.querySelector(".topbar")) return;   // nur im eingeloggten Bereich
    const items = [
      ["index.php", "🏠", "Start"],
      ["index.php?collection=studio", "★", "Studio"],
      ["index.php?view=board", "🗂️", "Board"],
      ["mail.php", "📮", "Postfach"],
      ["index.php?view=search", "🔎", "Suche"],
    ];
    const path = location.pathname.split("/").pop() || "index.php";
    const here = location.search;
    const nav = document.createElement("nav");
    nav.id = "pg-mobnav";
    nav.innerHTML = items.map(([href, ico, lbl]) => {
      const hp = href.split("?")[0], hq = href.split("?")[1] || "";
      let on = false;
      if (hp === "mail.php") on = path === "mail.php";
      else if (path === "index.php") on = hq === "" ? here === "" : here.includes(hq);
      return `<a href="${href}" class="${on ? "on" : ""}"><span>${ico}</span>${lbl}</a>`;
    }).join("");
    document.body.appendChild(nav);
    document.body.classList.add("has-mobnav");
  })();

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
