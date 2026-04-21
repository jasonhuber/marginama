// Background service worker.
// Handles all API calls so auth token stays in extension storage and
// requests originate from the extension (no CORS from content script origin).

const DEFAULT_API_BASE = "https://marginama.com";

async function getConfig() {
  const { apiBase, apiToken } = await chrome.storage.sync.get([
    "apiBase",
    "apiToken",
  ]);
  return {
    apiBase: (apiBase || DEFAULT_API_BASE).replace(/\/$/, ""),
    apiToken: apiToken || "",
  };
}

async function apiFetch(path, init = {}) {
  const { apiBase, apiToken } = await getConfig();
  if (!apiToken) {
    throw new Error(
      "No API token configured. Open the extension options and paste your Marginama token."
    );
  }
  const res = await fetch(`${apiBase}${path}`, {
    ...init,
    headers: {
      ...(init.headers || {}),
      Authorization: `Bearer ${apiToken}`,
      "Content-Type": "application/json",
    },
  });
  const text = await res.text();
  let body = null;
  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = { raw: text };
  }
  if (!res.ok) {
    const message =
      body?.error?.message ||
      body?.raw ||
      `Request failed (${res.status} ${res.statusText})`;
    throw new Error(message);
  }
  return body;
}

// Message dispatcher. Content script sends {type, ...} and expects the
// handler to resolve — we return true to keep the channel open for async.
chrome.runtime.onMessage.addListener((msg, _sender, sendResponse) => {
  (async () => {
    try {
      switch (msg.type) {
        case "ping": {
          const { apiBase, apiToken } = await getConfig();
          sendResponse({
            ok: true,
            configured: Boolean(apiToken),
            apiBase,
          });
          return;
        }
        case "saveCritique": {
          const body = await apiFetch("/api/v1/video-reviews", {
            method: "POST",
            body: JSON.stringify({
              videoUrl: msg.videoUrl,
              videoTitle: msg.videoTitle,
              timestampSec: msg.timestampSec,
              note: msg.note,
            }),
          });
          sendResponse({ ok: true, ...body });
          return;
        }
        case "getReviewByUrl": {
          const qp = new URLSearchParams({ url: msg.videoUrl });
          const body = await apiFetch(`/api/v1/video-reviews/by-url?${qp}`);
          sendResponse({ ok: true, ...body });
          return;
        }
        case "deleteCritique": {
          await apiFetch(`/api/v1/video-reviews/critiques/${msg.id}`, {
            method: "DELETE",
          });
          sendResponse({ ok: true });
          return;
        }
        default:
          sendResponse({ ok: false, error: `Unknown message type: ${msg.type}` });
      }
    } catch (e) {
      sendResponse({ ok: false, error: e.message || String(e) });
    }
  })();
  return true;
});

// Keyboard shortcut — tell the active tab's content script to handle it.
chrome.commands.onCommand.addListener(async (command) => {
  if (command !== "add-critique") return;
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (tab?.id) {
    chrome.tabs.sendMessage(tab.id, { type: "captureNow" }).catch(() => {
      /* no content script on this tab */
    });
  }
});
