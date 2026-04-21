const DEFAULT_API_BASE = "https://marginama.com";

document.getElementById("openReviews").addEventListener("click", async (e) => {
  e.preventDefault();
  const { apiBase } = await chrome.storage.sync.get(["apiBase"]);
  const base = (apiBase || DEFAULT_API_BASE).replace(/\/$/, "");
  chrome.tabs.create({ url: `${base}/video-reviews` });
});

document.getElementById("openOptions").addEventListener("click", (e) => {
  e.preventDefault();
  chrome.runtime.openOptionsPage();
});

(async () => {
  const { apiToken } = await chrome.storage.sync.get(["apiToken"]);
  const status = document.getElementById("status");
  status.textContent = apiToken
    ? "Connected to Marginama."
    : "No API token yet — open Extension settings to paste one.";
})();
