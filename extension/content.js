// Content script — injects the floating sidebar onto supported video pages.
// Uses a Shadow DOM root so page styles can't bleed in and we can't leak out.

(function () {
  if (window.__SOCRATES_VIDEO_REVIEW_INJECTED__) return;
  window.__SOCRATES_VIDEO_REVIEW_INJECTED__ = true;

  // ── Utilities ──────────────────────────────────────────────────────────────

  /**
   * Find the page's primary <video>, recursing into same-origin iframes.
   *
   * Google Drive's video player loads its <video> element inside a nested
   * drive.google.com iframe (e.g. `drive.google.com/drive-viewer/…`). Because
   * it's same-origin, we can read `iframe.contentDocument` and search in
   * there. Cross-origin iframes silently throw on access and we skip them.
   *
   * Returns null if the video hasn't loaded yet. The sidebar polls every
   * 500ms, so the "Now" badge starts working as soon as the video appears.
   */
  function findVideo(root) {
    root = root || document;
    let vids;
    try {
      vids = Array.from(root.querySelectorAll("video"));
    } catch {
      return null;
    }
    // Prefer a video that's currently playing
    const playing = vids.find((v) => !v.paused && v.currentTime > 0);
    if (playing) return playing;
    // Else the largest video in this frame
    const best = vids.reduce((b, v) => {
      if (!b) return v;
      return v.clientWidth * v.clientHeight > b.clientWidth * b.clientHeight
        ? v
        : b;
    }, null);
    if (best) return best;

    // Recurse into same-origin iframes (Drive, some embeds)
    let iframes;
    try {
      iframes = Array.from(root.querySelectorAll("iframe"));
    } catch {
      return null;
    }
    for (const iframe of iframes) {
      let doc = null;
      try {
        doc = iframe.contentDocument;
      } catch {
        // Cross-origin frame — can't access, skip
        continue;
      }
      if (doc) {
        const inner = findVideo(doc);
        if (inner) return inner;
      }
    }
    return null;
  }

  function currentTimestamp() {
    const v = findVideo();
    return v ? v.currentTime : 0;
  }

  function seekTo(seconds) {
    const v = findVideo();
    if (v) {
      v.currentTime = seconds;
      if (v.paused) v.play().catch(() => {});
    }
  }

  function getPageTitle() {
    // Try og:title, then <title>, then pathname
    const og = document.querySelector('meta[property="og:title"]');
    if (og?.content) return og.content;
    if (document.title) return document.title;
    return location.pathname;
  }

  function formatTimestamp(seconds) {
    const s = Math.max(0, Math.floor(seconds));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = (n) => String(n).padStart(2, "0");
    return h > 0 ? `${h}:${pad(m)}:${pad(sec)}` : `${m}:${pad(sec)}`;
  }

  // ── Shadow-DOM sidebar ─────────────────────────────────────────────────────

  const host = document.createElement("div");
  host.id = "socrates-video-review-host";
  host.style.cssText = "all: initial; position: fixed; top: 80px; right: 16px; z-index: 2147483646;";
  document.documentElement.appendChild(host);

  const shadow = host.attachShadow({ mode: "open" });

  shadow.innerHTML = `
    <style>
      :host, * { box-sizing: border-box; }
      .panel {
        font: 13px/1.45 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        letter-spacing: -0.005em;
        width: 340px;
        background: #111114;
        color: #ededed;
        border: 1px solid #1f1f23;
        border-radius: 10px;
        box-shadow:
          0 1px 0 rgba(255,255,255,0.03) inset,
          0 20px 50px -10px rgba(0,0,0,0.6),
          0 10px 25px rgba(0,0,0,0.4);
        overflow: hidden;
      }
      .panel.collapsed .body { display: none; }
      .header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        background: #0c0c0f;
        border-bottom: 1px solid #1f1f23;
        cursor: grab;
        user-select: none;
      }
      .mark {
        width: 18px; height: 18px;
        display: inline-flex; align-items: center; justify-content: center;
        background: #111114; color: #ededed;
        border: 1px solid #1f1f23; border-radius: 4px;
        font-family: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;
        font-size: 10px; font-weight: 500;
      }
      .title {
        flex: 1;
        font-weight: 600;
        color: #fafafa;
        font-size: 12px;
        letter-spacing: -0.01em;
      }
      .dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: #52525b;
      }
      .dot.ok {
        background: #06b6d4;
        box-shadow: 0 0 0 3px rgba(6,182,212,0.18);
      }
      .dot.warn { background: #fbbf24; }
      .dot.err { background: #f87171; }
      .icon-btn {
        background: transparent;
        border: 0;
        padding: 2px 6px;
        cursor: pointer;
        color: #71717a;
        font-size: 14px;
        line-height: 1;
        border-radius: 4px;
        transition: background-color 120ms, color 120ms;
      }
      .icon-btn:hover { background: #1f1f23; color: #ededed; }
      .body { padding: 10px 12px; }
      .ts-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
      }
      .ts-badge {
        background: #0c0c0f;
        color: #06b6d4;
        padding: 3px 8px;
        border: 1px solid #1f1f23;
        border-radius: 6px;
        font-variant-numeric: tabular-nums;
        font-family: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;
        font-size: 12px;
        letter-spacing: 0.01em;
      }
      .ts-row button {
        font: inherit;
        font-size: 11px;
        padding: 3px 8px;
        background: #111114;
        border: 1px solid #1f1f23;
        color: #a1a1aa;
        border-radius: 6px;
        cursor: pointer;
        transition: border-color 120ms, color 120ms;
      }
      .ts-row button:hover { border-color: #3f3f46; color: #ededed; }
      textarea {
        width: 100%;
        min-height: 70px;
        resize: vertical;
        padding: 8px 10px;
        background: #0c0c0f;
        border: 1px solid #1f1f23;
        border-radius: 6px;
        font: inherit;
        color: #ededed;
        transition: border-color 120ms, box-shadow 120ms;
      }
      textarea::placeholder { color: #52525b; }
      textarea:focus {
        outline: none;
        border-color: #06b6d4;
        box-shadow: 0 0 0 3px rgba(6,182,212,0.22);
      }
      .actions {
        display: flex;
        gap: 6px;
        margin-top: 8px;
      }
      .primary, .secondary {
        flex: 1;
        padding: 7px 10px;
        font-size: 12px;
        font-weight: 500;
        border-radius: 6px;
        cursor: pointer;
        border: 1px solid transparent;
        font: inherit;
        font-weight: 500;
        transition: background-color 120ms, border-color 120ms, color 120ms;
      }
      .primary {
        background: #06b6d4;
        color: #04181d;
        font-weight: 600;
      }
      .primary:hover { background: #22c8dc; }
      .primary:disabled { background: #27272a; color: #52525b; cursor: not-allowed; }
      .secondary {
        background: #111114;
        color: #a1a1aa;
        border-color: #1f1f23;
      }
      .secondary:hover { border-color: #3f3f46; color: #ededed; }
      .status {
        margin-top: 6px;
        font-size: 11px;
        color: #71717a;
        min-height: 14px;
      }
      .status.err { color: #f87171; }
      .status.ok { color: #06b6d4; }
      .list {
        margin-top: 12px;
        max-height: 280px;
        overflow-y: auto;
        border-top: 1px solid #1f1f23;
        padding-top: 8px;
      }
      .list::-webkit-scrollbar { width: 8px; }
      .list::-webkit-scrollbar-track { background: transparent; }
      .list::-webkit-scrollbar-thumb { background: #27272a; border-radius: 4px; }
      .list-empty {
        text-align: center;
        color: #52525b;
        font-size: 11px;
        padding: 12px 0;
        font-family: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;
      }
      .note {
        padding: 7px 4px;
        border-bottom: 1px dashed #1f1f23;
        display: flex;
        gap: 8px;
        align-items: flex-start;
      }
      .note:last-child { border-bottom: none; }
      .note .jump {
        background: #0c0c0f;
        color: #06b6d4;
        border: 1px solid #1f1f23;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;
        font-size: 11px;
        cursor: pointer;
        transition: border-color 120ms;
      }
      .note .jump:hover { border-color: rgba(6,182,212,0.4); }
      .note .text {
        flex: 1;
        font-size: 12px;
        color: #ededed;
        white-space: pre-wrap;
        word-break: break-word;
        line-height: 1.5;
      }
      .note .del {
        background: transparent;
        border: 0;
        color: #52525b;
        cursor: pointer;
        font-size: 11px;
        padding: 0 2px;
        transition: color 120ms;
      }
      .note .del:hover { color: #f87171; }
      .config-hint {
        background: rgba(6,182,212,0.08);
        border: 1px solid rgba(6,182,212,0.32);
        padding: 8px 10px;
        border-radius: 6px;
        font-size: 11px;
        color: #67e8f9;
        margin-bottom: 8px;
      }
      .config-hint a { color: #06b6d4; text-decoration: underline; }
      .overlay-toggle {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 10px;
        padding: 6px 8px;
        background: #0c0c0f;
        border: 1px solid #1f1f23;
        border-radius: 6px;
        font-size: 11px;
        color: #a1a1aa;
        cursor: pointer;
        transition: border-color 120ms;
      }
      .overlay-toggle:hover { border-color: #3f3f46; }
      .overlay-toggle input { margin: 0; cursor: pointer; accent-color: #06b6d4; }
      .reviewer-picker {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 10px;
        font-size: 11px;
        color: #a1a1aa;
      }
      .reviewer-picker label {
        color: #71717a;
        white-space: nowrap;
        font-family: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;
        font-size: 10px;
        letter-spacing: 0.06em;
        text-transform: uppercase;
      }
      .reviewer-picker select {
        flex: 1;
        padding: 5px 8px;
        border: 1px solid #1f1f23;
        border-radius: 6px;
        background: #0c0c0f;
        font: inherit;
        color: #ededed;
        cursor: pointer;
        transition: border-color 120ms;
      }
      .reviewer-picker select:focus {
        outline: none;
        border-color: #06b6d4;
      }
      .readonly-banner {
        padding: 6px 10px;
        background: rgba(6,182,212,0.08);
        border: 1px solid rgba(6,182,212,0.32);
        border-radius: 6px;
        font-size: 11px;
        color: #67e8f9;
        margin-bottom: 10px;
      }
      .composer[hidden] { display: none !important; }
    </style>
    <div class="panel" id="panel">
      <div class="header" id="drag">
        <span class="mark" aria-hidden="true">§</span>
        <span class="title">Marginama</span>
        <span class="dot" id="statusDot"></span>
        <button class="icon-btn" id="refreshBtn" title="Refresh">↻</button>
        <button class="icon-btn" id="toggleBtn" title="Collapse">–</button>
      </div>
      <div class="body">
        <div id="configHint" class="config-hint" style="display:none;">
          No API token configured.
          <a href="#" id="openOptions">Open settings</a>
        </div>
        <div class="reviewer-picker" id="reviewerPicker" style="display:none;">
          <label for="reviewerSelect">Viewing</label>
          <select id="reviewerSelect"></select>
        </div>
        <div class="readonly-banner" id="readonlyBanner" style="display:none;">
          Read-only — you're viewing <span id="readonlyName">someone else</span>'s notes.
        </div>
        <label class="overlay-toggle">
          <input type="checkbox" id="overlayToggle" />
          <span>Show notes as overlay on video</span>
        </label>
        <div class="composer" id="composer">
          <div class="ts-row">
            <span class="ts-badge" id="tsBadge">0:00</span>
            <button id="captureBtn" title="Set to current video time">⟳ Now</button>
            <button id="seekBtn" title="Seek to captured time">▸ Seek</button>
          </div>
          <textarea id="noteInput" placeholder="Critique this moment…"></textarea>
          <div class="actions">
            <button class="secondary" id="clearBtn">Clear</button>
            <button class="primary" id="saveBtn">Add note</button>
          </div>
          <div class="status" id="status"></div>
        </div>
        <div class="list" id="list">
          <div class="list-empty">No notes yet for this video.</div>
        </div>
      </div>
    </div>
  `;

  const $ = (sel) => shadow.querySelector(sel);
  const panel = $("#panel");
  const tsBadge = $("#tsBadge");
  const noteInput = $("#noteInput");
  const saveBtn = $("#saveBtn");
  const statusEl = $("#status");
  const statusDot = $("#statusDot");
  const listEl = $("#list");
  const configHint = $("#configHint");
  const reviewerPicker = $("#reviewerPicker");
  const reviewerSelect = $("#reviewerSelect");
  const readonlyBanner = $("#readonlyBanner");
  const readonlyName = $("#readonlyName");
  const composer = $("#composer");

  let capturedTs = 0;

  // All reviews for the current video that the user can see: their own +
  // any that teammates have workspace-shared. Populated by refreshNotes().
  // The "mine" pseudo-review is synthetic when the user hasn't written
  // anything for this video yet — it has id=null and empty critiques.
  let allReviews = []; // [{ id, isMine, reviewer: {name}, critiques: [...] }, ...]
  let activeReviewId = "mine"; // "mine" or a real review id owned by someone else

  function setStatus(msg, kind = "") {
    statusEl.textContent = msg || "";
    statusEl.classList.remove("ok", "err");
    if (kind) statusEl.classList.add(kind);
  }

  function setStatusDot(kind) {
    statusDot.classList.remove("ok", "warn", "err");
    if (kind) statusDot.classList.add(kind);
  }

  function captureNow() {
    capturedTs = currentTimestamp();
    tsBadge.textContent = formatTimestamp(capturedTs);
  }

  function getActiveReview() {
    return allReviews.find((r) => (r.isMine ? "mine" : r.id) === activeReviewId) ?? null;
  }

  function isActiveMine() {
    const r = getActiveReview();
    return Boolean(r?.isMine);
  }

  /**
   * Render the reviewer dropdown. Always includes a "Your notes" option
   * (so the user can write a fresh critique even if shared reviews from
   * others exist first). Hides itself when there are zero shared reviews.
   */
  function renderReviewerPicker() {
    const others = allReviews.filter((r) => !r.isMine);
    if (others.length === 0) {
      reviewerPicker.style.display = "none";
    } else {
      reviewerPicker.style.display = "";
    }

    // Build options
    reviewerSelect.innerHTML = "";
    const mineOpt = document.createElement("option");
    mineOpt.value = "mine";
    const mine = allReviews.find((r) => r.isMine);
    const myCount = mine?.critiques.length ?? 0;
    mineOpt.textContent = `Your notes (${myCount})`;
    reviewerSelect.appendChild(mineOpt);

    for (const r of others) {
      const opt = document.createElement("option");
      opt.value = r.id;
      opt.textContent = `${r.reviewer.name} (${r.critiques.length})`;
      reviewerSelect.appendChild(opt);
    }

    reviewerSelect.value = activeReviewId;
  }

  /**
   * Render the notes list for the currently active review. Delete buttons
   * are hidden unless the active review is mine.
   */
  function renderList() {
    const review = getActiveReview();
    const critiques = review?.critiques ?? [];
    const editable = Boolean(review?.isMine);

    if (critiques.length === 0) {
      const msg = editable
        ? "No notes yet for this video."
        : `${review?.reviewer?.name ?? "This reviewer"} hasn't taken notes on this video yet.`;
      listEl.innerHTML = `<div class="list-empty">${msg}</div>`;
      return;
    }
    listEl.innerHTML = "";
    for (const c of critiques) {
      const row = document.createElement("div");
      row.className = "note";
      const delBtn = editable
        ? `<button class="del" data-id="${c.id}" title="Delete">✕</button>`
        : "";
      row.innerHTML = `
        <button class="jump" data-ts="${c.timestampSec}" title="Seek to ${formatTimestamp(c.timestampSec)}">${formatTimestamp(c.timestampSec)}</button>
        <div class="text"></div>
        ${delBtn}
      `;
      row.querySelector(".text").textContent = c.note;
      listEl.appendChild(row);
    }
    listEl.querySelectorAll(".jump").forEach((b) => {
      b.addEventListener("click", () => seekTo(Number(b.dataset.ts)));
    });
    listEl.querySelectorAll(".del").forEach((b) => {
      b.addEventListener("click", async () => {
        const id = b.dataset.id;
        if (!confirm("Delete this note?")) return;
        const resp = await chrome.runtime.sendMessage({
          type: "deleteCritique",
          id,
        });
        if (resp?.ok) {
          const mine = allReviews.find((r) => r.isMine);
          if (mine) {
            mine.critiques = mine.critiques.filter((c) => c.id !== id);
          }
          renderReviewerPicker();
          renderList();
          setStatus("Deleted.", "ok");
        } else {
          setStatus(resp?.error || "Delete failed.", "err");
        }
      });
    });
  }

  /**
   * Show/hide the composer + read-only banner based on the active review.
   */
  function applyReadonlyState() {
    const review = getActiveReview();
    const editable = Boolean(review?.isMine);
    composer.hidden = !editable;
    if (editable) {
      readonlyBanner.style.display = "none";
    } else {
      readonlyName.textContent = review?.reviewer?.name ?? "someone";
      readonlyBanner.style.display = "";
    }
  }

  /**
   * Fetch all reviews (mine + workspace-shared) for the current page URL.
   * Always seeds a synthetic "mine" review if the server didn't return one
   * — that way the user can start writing without waiting for a round-trip.
   */
  async function refreshNotes() {
    try {
      const resp = await chrome.runtime.sendMessage({
        type: "getReviewByUrl",
        videoUrl: location.href,
      });
      if (!resp?.ok) {
        setStatusDot("warn");
        setStatus(resp?.error || "Could not load notes.", "err");
        return;
      }
      setStatusDot("ok");
      const serverReviews = Array.isArray(resp.reviews) ? resp.reviews : [];

      // Seed a synthetic "mine" entry if the server didn't return one — this
      // way the composer has something to attach to even before the first save.
      const hasMine = serverReviews.some((r) => r.isMine);
      if (!hasMine) {
        serverReviews.unshift({
          id: null,
          isMine: true,
          reviewer: { name: "You" },
          critiques: [],
        });
      }
      allReviews = serverReviews;

      // Preserve the active selection across refreshes if it still exists;
      // otherwise fall back to "mine".
      const stillThere =
        activeReviewId === "mine"
          ? true
          : allReviews.some((r) => r.id === activeReviewId);
      if (!stillThere) activeReviewId = "mine";

      renderReviewerPicker();
      renderList();
      applyReadonlyState();
    } catch (e) {
      setStatusDot("err");
      setStatus(e.message || String(e), "err");
    }
  }

  async function checkConfig() {
    const resp = await chrome.runtime.sendMessage({ type: "ping" });
    if (!resp?.configured) {
      configHint.style.display = "";
      setStatusDot("warn");
      return false;
    }
    configHint.style.display = "none";
    return true;
  }

  async function save() {
    // Saves always target MY review — if I'm currently viewing someone
    // else's notes, writing a new one still creates / appends to mine.
    const note = noteInput.value.trim();
    if (!note) {
      setStatus("Write a note first.", "err");
      noteInput.focus();
      return;
    }
    saveBtn.disabled = true;
    setStatus("Saving…");
    try {
      const resp = await chrome.runtime.sendMessage({
        type: "saveCritique",
        videoUrl: location.href,
        videoTitle: getPageTitle(),
        timestampSec: capturedTs,
        note,
      });
      if (!resp?.ok) throw new Error(resp?.error || "Save failed");
      setStatus("Saved.", "ok");
      noteInput.value = "";

      // Optimistic insert into my own review
      let mine = allReviews.find((r) => r.isMine);
      if (!mine) {
        mine = {
          id: resp.review.id,
          isMine: true,
          reviewer: { name: "You" },
          critiques: [],
        };
        allReviews.unshift(mine);
      } else if (!mine.id && resp.review?.id) {
        mine.id = resp.review.id;
      }
      mine.critiques = [...mine.critiques, resp.critique].sort(
        (a, b) => a.timestampSec - b.timestampSec
      );
      // If the user was reading someone else's notes, snap back to mine
      // so they see their new save land.
      activeReviewId = "mine";
      renderReviewerPicker();
      renderList();
      applyReadonlyState();
    } catch (e) {
      setStatus(e.message || String(e), "err");
    } finally {
      saveBtn.disabled = false;
    }
  }

  // ── Overlay captions ───────────────────────────────────────────────────────
  //
  // When enabled, shows a caption over the video from t-0.5s to t+5s of each
  // critique's timestamp. Lets you scrub through a rewatch and see the notes
  // pop up like a director's commentary track.
  //
  // Fullscreen handling: when the page enters fullscreen, we reparent the
  // overlay host into the fullscreen element so it stays visible over the
  // black curtain.

  const overlayHost = document.createElement("div");
  overlayHost.id = "socrates-video-review-overlay";
  overlayHost.style.cssText =
    "all: initial; position: fixed; z-index: 2147483645; pointer-events: none;";
  document.documentElement.appendChild(overlayHost);

  const overlayShadow = overlayHost.attachShadow({ mode: "open" });
  overlayShadow.innerHTML = `
    <style>
      :host, * { box-sizing: border-box; }
      .wrap {
        position: absolute;
        inset: 0;
        pointer-events: none;
      }
      .caption {
        position: absolute;
        left: 50%;
        bottom: 14%;
        transform: translateX(-50%);
        max-width: 80%;
        padding: 10px 14px;
        background: rgba(0,0,0,0.78);
        color: #ffffff;
        border-radius: 8px;
        font: 500 15px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        text-align: center;
        opacity: 0;
        transition: opacity 180ms ease-out;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
      }
      .caption.visible { opacity: 1; }
      .ts {
        display: block;
        font-family: ui-monospace, "SF Mono", Menlo, monospace;
        font-size: 10px;
        opacity: 0.7;
        margin-bottom: 4px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
      }
      .text {
        white-space: pre-wrap;
        word-break: break-word;
      }
    </style>
    <div class="wrap">
      <div class="caption" id="caption">
        <span class="ts" id="captionTs"></span>
        <span class="text" id="captionText"></span>
      </div>
    </div>
  `;

  const captionEl = overlayShadow.getElementById("caption");
  const captionTsEl = overlayShadow.getElementById("captionTs");
  const captionTextEl = overlayShadow.getElementById("captionText");

  let overlayEnabled = false;
  let overlayRaf = null;
  let overlayCurrentId = null; // id of the critique currently displayed

  function positionOverlay(video) {
    const r = video.getBoundingClientRect();
    overlayHost.style.top = r.top + "px";
    overlayHost.style.left = r.left + "px";
    overlayHost.style.width = r.width + "px";
    overlayHost.style.height = r.height + "px";
  }

  function showCaption(critique) {
    if (overlayCurrentId === critique.id) return;
    overlayCurrentId = critique.id;
    captionTsEl.textContent = formatTimestamp(critique.timestampSec);
    captionTextEl.textContent = critique.note;
    captionEl.classList.add("visible");
  }

  function hideCaption() {
    if (!overlayCurrentId) return;
    overlayCurrentId = null;
    captionEl.classList.remove("visible");
  }

  function overlayTick() {
    if (!overlayEnabled) {
      hideCaption();
      overlayRaf = null;
      return;
    }
    const video = findVideo();
    // Overlay captions reflect whichever review is currently selected
    // in the dropdown — if you're reading Jane's commentary, Jane's notes
    // appear over the video; flip to your own notes and yours do.
    const critiques = getActiveReview()?.critiques ?? [];
    if (!video || critiques.length === 0) {
      hideCaption();
      overlayRaf = requestAnimationFrame(overlayTick);
      return;
    }
    positionOverlay(video);

    const t = video.currentTime;
    // Window: from 0.5s before the critique timestamp to 5s after.
    // Pick the latest match (most relevant if two critiques are within 5s of each other).
    let active = null;
    for (const c of critiques) {
      if (t >= c.timestampSec - 0.5 && t <= c.timestampSec + 5) {
        active = c;
      }
    }
    if (active) showCaption(active);
    else hideCaption();

    overlayRaf = requestAnimationFrame(overlayTick);
  }

  function setOverlayEnabled(enabled) {
    overlayEnabled = enabled;
    chrome.storage.local.set({ overlayEnabled: enabled }).catch(() => {});
    if (enabled) {
      if (!overlayRaf) overlayRaf = requestAnimationFrame(overlayTick);
    } else {
      if (overlayRaf) {
        cancelAnimationFrame(overlayRaf);
        overlayRaf = null;
      }
      hideCaption();
    }
  }

  // Reparent the overlay into / out of the fullscreen element so it stays
  // visible when the video goes fullscreen.
  document.addEventListener("fullscreenchange", () => {
    const target = document.fullscreenElement ?? document.documentElement;
    if (overlayHost.parentNode !== target) {
      target.appendChild(overlayHost);
    }
  });

  // ── Wire up UI ─────────────────────────────────────────────────────────────

  reviewerSelect.addEventListener("change", () => {
    activeReviewId = reviewerSelect.value;
    renderList();
    applyReadonlyState();
  });

  $("#captureBtn").addEventListener("click", () => {
    captureNow();
    noteInput.focus();
  });
  $("#seekBtn").addEventListener("click", () => seekTo(capturedTs));
  $("#saveBtn").addEventListener("click", save);
  $("#clearBtn").addEventListener("click", () => {
    noteInput.value = "";
    setStatus("");
  });
  $("#refreshBtn").addEventListener("click", () => {
    refreshNotes();
    captureNow();
  });
  $("#toggleBtn").addEventListener("click", () => {
    panel.classList.toggle("collapsed");
    $("#toggleBtn").textContent = panel.classList.contains("collapsed") ? "+" : "–";
  });
  $("#openOptions").addEventListener("click", (e) => {
    e.preventDefault();
    chrome.runtime.sendMessage({ type: "openOptions" }).catch(() => {});
    // Fallback: chrome.runtime.openOptionsPage isn't available to content scripts,
    // so the user may need to right-click the extension icon → Options.
    alert(
      "Click the Marginama Video Review extension icon in the toolbar, then choose Options."
    );
  });

  const overlayToggle = $("#overlayToggle");
  overlayToggle.addEventListener("change", () => {
    setOverlayEnabled(overlayToggle.checked);
  });
  // Load persisted preference
  chrome.storage.local.get(["overlayEnabled"]).then(({ overlayEnabled: saved }) => {
    if (saved) {
      overlayToggle.checked = true;
      setOverlayEnabled(true);
    }
  });

  // Cmd/Ctrl+Enter to save from within the note field
  noteInput.addEventListener("keydown", (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === "Enter") {
      e.preventDefault();
      save();
    }
  });

  // Keyboard shortcut: Ctrl/Cmd+Shift+N fires a message from background
  chrome.runtime.onMessage.addListener((msg) => {
    if (msg?.type === "captureNow") {
      captureNow();
      noteInput.focus();
    }
  });

  // Draggable header
  (function makeDraggable() {
    const drag = $("#drag");
    let startX, startY, origRight, origTop;
    drag.addEventListener("mousedown", (e) => {
      if (e.target.closest("button")) return; // don't start drag on button clicks
      startX = e.clientX;
      startY = e.clientY;
      const rect = host.getBoundingClientRect();
      origRight = window.innerWidth - rect.right;
      origTop = rect.top;
      const onMove = (ev) => {
        const dx = ev.clientX - startX;
        const dy = ev.clientY - startY;
        host.style.right = Math.max(0, origRight - dx) + "px";
        host.style.top = Math.max(0, origTop + dy) + "px";
      };
      const onUp = () => {
        window.removeEventListener("mousemove", onMove);
        window.removeEventListener("mouseup", onUp);
      };
      window.addEventListener("mousemove", onMove);
      window.addEventListener("mouseup", onUp);
    });
  })();

  // Poll the video's currentTime while it's playing, so the "Now" badge
  // doesn't update on its own but the capture-click is always accurate.
  // We update the badge lazily on click — no polling needed.

  // Init
  captureNow();
  (async () => {
    const configured = await checkConfig();
    if (configured) await refreshNotes();
  })();

  // YouTube and other SPAs re-use the page across video navigations. Watch
  // for URL changes and re-fetch notes when the video id changes.
  let lastHref = location.href;
  setInterval(() => {
    if (location.href !== lastHref) {
      lastHref = location.href;
      allReviews = [];
      activeReviewId = "mine";
      renderReviewerPicker();
      renderList();
      applyReadonlyState();
      captureNow();
      refreshNotes();
    }
  }, 1500);

  // Keep the "Now" badge live-updating so the user always sees the current
  // video time without having to click. Twice per second is plenty smooth
  // and cheap. Also surfaces "no video found yet" state so the user knows
  // why the badge isn't moving (especially on Drive where the player loads
  // progressively inside a nested iframe).
  let sawVideoAt = 0;
  setInterval(() => {
    const v = findVideo();
    if (!v) {
      // No video anywhere yet — make it visible in the UI
      if (!noteInput.value.trim()) {
        tsBadge.textContent = "—:—";
        tsBadge.title = "No video detected on this page yet";
      }
      return;
    }
    tsBadge.title = "Current video time";
    if (!sawVideoAt) {
      // First time we've seen the video — log for debugging visibility
      sawVideoAt = Date.now();
      // Ensure the "Now" badge snaps to the real time immediately
      capturedTs = v.currentTime;
      tsBadge.textContent = formatTimestamp(capturedTs);
    }
    // Only update if the user hasn't explicitly captured a different time
    // (i.e. the note field is empty — they're just looking, not writing).
    if (!noteInput.value.trim()) {
      capturedTs = v.currentTime;
      tsBadge.textContent = formatTimestamp(capturedTs);
    }
  }, 500);

  // MutationObserver to catch the video appearing in the DOM — fires
  // before the 500ms interval would notice, so the sidebar reacts
  // immediately when Drive finishes injecting its player iframe.
  const mo = new MutationObserver(() => {
    if (!sawVideoAt && findVideo()) {
      // Force an immediate tick by "faking" the interval's work
      const v = findVideo();
      if (v) {
        sawVideoAt = Date.now();
        capturedTs = v.currentTime;
        tsBadge.textContent = formatTimestamp(capturedTs);
        tsBadge.title = "Current video time";
      }
    }
  });
  mo.observe(document.documentElement, {
    childList: true,
    subtree: true,
  });
})();
