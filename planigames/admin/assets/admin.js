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
    if (t.closest("[data-fold]")) { item.classList.toggle("folded"); e.preventDefault(); return; }
  });

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
})();
