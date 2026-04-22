# Marginama Video Review — Chrome Extension

A Manifest V3 extension that lets you capture time-stamped critiques from
YouTube, Sybill, and Google Drive videos and send them to Marginama. Works in
Brave / Chrome / any Chromium browser.

## Install (unpacked)

1. Open `brave://extensions` (or `chrome://extensions`).
2. Toggle **Developer mode** on (top right).
3. Click **Load unpacked** and pick this folder.
4. Open the extension's **Options** page.
5. Paste your Marginama API token (see below) and click **Save** then
   **Test connection**.

## Getting an API token

1. Sign in at <https://marginama.com>.
2. Go to **Settings → API Tokens** (in the header nav).
3. Click **Create token**, give it a name (e.g. "Chrome extension — laptop"),
   and copy the plaintext shown. The token is only displayed once.

## Using it

Open any supported video page. The floating Marginama panel appears in the
top-right. Supported platforms:

| Platform           | URL                                                         |
|--------------------|-------------------------------------------------------------|
| YouTube            | `youtube.com/watch?v=…`, `youtu.be/ID`, `m.youtube.com`     |
| Vimeo              | `vimeo.com/ID`, `player.vimeo.com/video/ID`                 |
| Loom               | `loom.com/share/…`                                          |
| Wistia             | `wistia.com/medias/ID`, `fast.wistia.net/embed/iframe/ID`   |
| Sybill             | `*.sybill.ai/*`                                             |
| Gong               | `*.app.gong.io/*`                                           |
| Salesloft          | `app.salesloft.com/*` (Conversations recordings)            |
| Zoom               | `*.zoom.us/rec/*` (cloud recordings)                        |
| Chorus             | `chorus.ai/*`                                               |
| Panopto            | `*.panopto.com/*`, `*.panopto.eu/*`                         |
| Google Drive       | `drive.google.com/*` (any file page with an embedded video) |
| Riverside          | `riverside.fm/*`                                            |
| Descript           | `web.descript.com/*`                                        |
| Twitch VODs        | `twitch.tv/videos/ID`                                       |
| Microsoft Stream   | `web.microsoftstream.com/*`                                 |

To add a note:

1. Click **⟳ Now** to capture the current video timestamp (or press
   **Cmd+Shift+N** / **Ctrl+Shift+N**).
2. Type the critique.
3. Click **Add note**.

Notes appear in the list below, sorted by timestamp. Click any timestamp to
seek the video there. Click ✕ to delete.

## Manual timer (for players we can't reach)

Some video players (Gong, Zoom cloud recordings, enterprise DRM videos) keep
the underlying `<video>` element behind cross-origin iframes or sandboxing. The
extension can't read or seek them. For those, flip the **Manual timer** toggle
in the sidebar:

1. Click **▶ Start** the instant you hit play on the video.
2. The badge ticks up as your stopwatch.
3. Press **Cmd+Shift+N** (or click **⟳ Now**) to capture the current elapsed
   time with your note. Save as normal.

Tradeoff: the dashboard's click-to-seek won't work for these notes — clicking
a timestamp opens the source URL but can't jump to that second (because the
extension never had control of the player). You seek manually.

If the extension hasn't found a video three seconds after page load, the
sidebar shows a small hint prompting you to try manual mode.

## Files

| File             | Purpose                                                       |
|------------------|---------------------------------------------------------------|
| `manifest.json`  | MV3 extension manifest                                        |
| `background.js`  | Service worker — all API calls live here                      |
| `content.js`     | Injects the shadow-DOM sidebar on matched pages               |
| `sidebar.css`    | Placeholder for global styles (all real styles are inlined)   |
| `options.html/js`| Settings page: Marginama URL + API token                      |
| `popup.html/js`  | Toolbar-icon popup with quick links                           |
| `icons/`         | Add `icon16.png`, `icon48.png`, `icon128.png` for a real icon |

## Supported hosts

Defined in `manifest.json` under `host_permissions`:

- `marginama.com` — production Marginama (required for API calls)
- `www.marginama.com` — alias
- `localhost:8000` — local Marginama dev server (harmless if unused)

If you run Marginama somewhere else, add that origin to `host_permissions`
and reload the extension at `chrome://extensions`.
