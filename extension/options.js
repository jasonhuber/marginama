const DEFAULT_API_BASE = "https://marginama.com";

const apiBaseEl = document.getElementById("apiBase");
const apiTokenEl = document.getElementById("apiToken");
const saveBtn = document.getElementById("saveBtn");
const testBtn = document.getElementById("testBtn");
const statusEl = document.getElementById("status");
const tokenLink = document.getElementById("tokenLink");

function setStatus(msg, kind = "") {
  statusEl.textContent = msg || "";
  statusEl.classList.remove("ok", "err");
  if (kind) statusEl.classList.add(kind);
}

function normalizeBase(raw) {
  const v = (raw || DEFAULT_API_BASE).trim().replace(/\/$/, "");
  return v || DEFAULT_API_BASE;
}

async function load() {
  const { apiBase, apiToken } = await chrome.storage.sync.get([
    "apiBase",
    "apiToken",
  ]);
  apiBaseEl.value = apiBase || DEFAULT_API_BASE;
  apiTokenEl.value = apiToken || "";
  updateTokenLink();
}

function updateTokenLink() {
  const base = normalizeBase(apiBaseEl.value);
  tokenLink.href = `${base}/settings/api-tokens`;
}

async function save() {
  const apiBase = normalizeBase(apiBaseEl.value);
  const apiToken = apiTokenEl.value.trim();
  await chrome.storage.sync.set({ apiBase, apiToken });
  setStatus("Saved.", "ok");
}

async function test() {
  setStatus("Testing…");
  const apiBase = normalizeBase(apiBaseEl.value);
  const apiToken = apiTokenEl.value.trim();
  if (!apiToken) {
    setStatus("Paste a token first.", "err");
    return;
  }
  try {
    const res = await fetch(`${apiBase}/api/v1/video-reviews`, {
      headers: { Authorization: `Bearer ${apiToken}` },
    });
    if (res.status === 401) {
      setStatus("Token rejected (401). Double-check you copied it in full.", "err");
      return;
    }
    if (!res.ok) {
      setStatus(`Request failed (${res.status}).`, "err");
      return;
    }
    const data = await res.json();
    setStatus(
      `Connected. ${data.count ?? 0} existing review${
        (data.count ?? 0) === 1 ? "" : "s"
      }.`,
      "ok"
    );
  } catch (e) {
    setStatus(`Network error: ${e.message || e}`, "err");
  }
}

apiBaseEl.addEventListener("input", updateTokenLink);
saveBtn.addEventListener("click", save);
testBtn.addEventListener("click", test);
load();
