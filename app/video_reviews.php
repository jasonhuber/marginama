<?php
declare(strict_types=1);

/**
 * Parse a URL and return [canonicalUrl, provider].
 *
 * The canonical form strips tracking params and fragments so the same video
 * opened from different contexts (`&feature=share&utm_source=...`) maps to a
 * single review row.
 *
 * Supported providers (see provider_label() for display names):
 *   youtube · sybill · gdrive · vimeo · loom · wistia · gong · salesloft
 *   zoom · chorus · panopto · riverside · descript · twitch · msstream · other
 */
function canonicalize_video_url(string $input): array {
    $parts = parse_url($input);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        throw new InvalidArgumentException('Invalid URL');
    }
    $scheme = strtolower($parts['scheme']);
    $host   = strtolower(preg_replace('/^www\./', '', $parts['host']));
    $path   = $parts['path'] ?? '/';
    parse_str($parts['query'] ?? '', $qs);

    // ── YouTube ──────────────────────────────────────────────────────────────
    if ($host === 'youtube.com' || $host === 'm.youtube.com') {
        $v    = $qs['v']    ?? null;
        $list = $qs['list'] ?? null;
        if ($v) {
            $qp = ['v' => $v];
            if ($list) $qp['list'] = $list;
            return ['https://www.youtube.com/watch?' . http_build_query($qp), 'youtube'];
        }
        return ["https://www.youtube.com$path", 'youtube'];
    }
    if ($host === 'youtu.be') {
        $id = ltrim($path, '/');
        if ($id !== '') {
            return ['https://www.youtube.com/watch?v=' . rawurlencode($id), 'youtube'];
        }
    }

    // ── Sybill ───────────────────────────────────────────────────────────────
    if (str_ends_with($host, 'sybill.ai')) {
        return ["$scheme://{$parts['host']}$path", 'sybill'];
    }

    // ── Google Drive ─────────────────────────────────────────────────────────
    if ($host === 'drive.google.com' || str_ends_with($host, '.drive.google.com')) {
        return ["$scheme://{$parts['host']}$path", 'gdrive'];
    }

    // ── Vimeo ────────────────────────────────────────────────────────────────
    // Forms: vimeo.com/VIDEO_ID, vimeo.com/VIDEO_ID/HASH, player.vimeo.com/video/VIDEO_ID
    // Normalize player embeds to the canonical watch URL so one review per video.
    if ($host === 'vimeo.com') {
        return ["https://vimeo.com$path", 'vimeo'];
    }
    if ($host === 'player.vimeo.com') {
        if (preg_match('#^/video/(\d+)#', $path, $m)) {
            return ["https://vimeo.com/{$m[1]}", 'vimeo'];
        }
        return ["https://player.vimeo.com$path", 'vimeo'];
    }

    // ── Loom ─────────────────────────────────────────────────────────────────
    // loom.com/share/UUID · loom.com/embed/UUID — both → /share/UUID.
    if ($host === 'loom.com' || str_ends_with($host, '.loom.com')) {
        if (preg_match('#^/(?:share|embed)/([a-f0-9-]{10,})#', $path, $m)) {
            return ["https://www.loom.com/share/{$m[1]}", 'loom'];
        }
        return ["https://www.loom.com$path", 'loom'];
    }

    // ── Wistia ───────────────────────────────────────────────────────────────
    // wistia.com/medias/ID · fast.wistia.net/embed/iframe/ID — normalize to /medias/ID.
    if (str_ends_with($host, 'wistia.com') || str_ends_with($host, 'wistia.net')) {
        if (preg_match('#/(?:medias|embed/iframe|embed/medias)/([A-Za-z0-9]+)#', $path, $m)) {
            return ["https://{$parts['host']}/medias/{$m[1]}", 'wistia'];
        }
        return ["$scheme://{$parts['host']}$path", 'wistia'];
    }

    // ── Gong ─────────────────────────────────────────────────────────────────
    // app.gong.io/call?id=XXX — keep the id param only; Gong is regional (us-*, eu-*).
    if (str_ends_with($host, 'gong.io')) {
        if (!empty($qs['id'])) {
            return ["$scheme://{$parts['host']}$path?id={$qs['id']}", 'gong'];
        }
        return ["$scheme://{$parts['host']}$path", 'gong'];
    }

    // ── Salesloft Conversations ──────────────────────────────────────────────
    // app.salesloft.com/... — recording id lives in the pathname; drop query
    // and fragment so share-link tracking doesn't fragment the review.
    if ($host === 'app.salesloft.com' || str_ends_with($host, '.salesloft.com')) {
        return ["$scheme://{$parts['host']}$path", 'salesloft'];
    }

    // ── Zoom Cloud Recordings ────────────────────────────────────────────────
    // zoom.us/rec/share/HASH?pwd=... — drop the password + tracking.
    if (str_ends_with($host, 'zoom.us') || str_ends_with($host, 'zoom.com')) {
        return ["$scheme://{$parts['host']}$path", 'zoom'];
    }

    // ── Chorus (ZoomInfo) ────────────────────────────────────────────────────
    if (str_ends_with($host, 'chorus.ai')) {
        return ["$scheme://{$parts['host']}$path", 'chorus'];
    }

    // ── Panopto ──────────────────────────────────────────────────────────────
    // *.panopto.com/Panopto/Pages/Viewer.aspx?id=UUID — keep only id.
    if (str_ends_with($host, 'panopto.com') || str_ends_with($host, 'panopto.eu')) {
        if (!empty($qs['id'])) {
            return ["$scheme://{$parts['host']}$path?id={$qs['id']}", 'panopto'];
        }
        return ["$scheme://{$parts['host']}$path", 'panopto'];
    }

    // ── Riverside ────────────────────────────────────────────────────────────
    if (str_ends_with($host, 'riverside.fm')) {
        return ["$scheme://{$parts['host']}$path", 'riverside'];
    }

    // ── Descript ─────────────────────────────────────────────────────────────
    if (str_ends_with($host, 'descript.com')) {
        return ["$scheme://{$parts['host']}$path", 'descript'];
    }

    // ── Twitch VODs ──────────────────────────────────────────────────────────
    // twitch.tv/videos/VIDEO_ID — only VOD URLs get canonicalized as twitch.
    if (str_ends_with($host, 'twitch.tv')) {
        if (preg_match('#^/videos/\d+#', $path)) {
            return ["https://www.twitch.tv$path", 'twitch'];
        }
        return ["$scheme://{$parts['host']}$path", 'twitch'];
    }

    // ── Microsoft Stream / SharePoint video ──────────────────────────────────
    if ($host === 'web.microsoftstream.com' || str_ends_with($host, '.microsoftstream.com')) {
        return ["$scheme://{$parts['host']}$path", 'msstream'];
    }

    // ── Fallback: keep protocol + host + pathname ────────────────────────────
    return ["$scheme://{$parts['host']}$path", 'other'];
}

/**
 * Human-readable label for a provider code. Centralized so new platforms are
 * one-liners — see reviews_list/detail/share for usage.
 */
function provider_label(?string $provider): string {
    static $labels = [
        'youtube'   => 'YouTube',
        'sybill'    => 'Sybill',
        'gdrive'    => 'Google Drive',
        'vimeo'     => 'Vimeo',
        'loom'      => 'Loom',
        'wistia'    => 'Wistia',
        'gong'      => 'Gong',
        'salesloft' => 'Salesloft',
        'zoom'      => 'Zoom',
        'chorus'    => 'Chorus',
        'panopto'   => 'Panopto',
        'riverside' => 'Riverside',
        'descript'  => 'Descript',
        'twitch'    => 'Twitch',
        'msstream'  => 'Microsoft Stream',
        'other'     => 'Video',
    ];
    return $labels[$provider] ?? 'Video';
}

function format_timestamp(int $seconds): string {
    $s = max(0, $seconds);
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    $sec = $s % 60;
    return $h > 0
        ? sprintf('%d:%02d:%02d', $h, $m, $sec)
        : sprintf('%d:%02d', $m, $sec);
}

function deep_link_at_time(string $canonicalUrl, string $provider, int $timestampSec): string {
    $t = max(0, $timestampSec);
    $sep = str_contains($canonicalUrl, '?') ? '&' : '?';
    switch ($provider) {
        case 'youtube':
            return "$canonicalUrl{$sep}t={$t}s";
        case 'vimeo':
            // Vimeo supports both ?t=Xs and #t=Xs; the hash form avoids a reload.
            return "$canonicalUrl#t={$t}s";
        case 'loom':
            return "$canonicalUrl{$sep}t={$t}";
        case 'wistia':
            return "$canonicalUrl{$sep}wtime={$t}s";
        case 'panopto':
            return "$canonicalUrl{$sep}start={$t}";
        case 'twitch':
            // Twitch expects 1h2m3s format.
            $h = intdiv($t, 3600);
            $m = intdiv($t % 3600, 60);
            $s = $t % 60;
            $tSpec = ($h ? "{$h}h" : '') . "{$m}m{$s}s";
            return "$canonicalUrl{$sep}t={$tSpec}";
        case 'msstream':
            return "$canonicalUrl{$sep}st={$t}";
    }
    // Providers that don't support deep-link seeking (Sybill, Drive, Gong,
    // Salesloft, Zoom, Chorus, Riverside, Descript) just return the canonical URL.
    return $canonicalUrl;
}

function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Inline an SVG asset from app/views/icons/ so it inherits currentColor
 * from CSS. Returns raw SVG (caller should NOT escape).
 *
 * $name is the part between "icon-" and ".svg" (e.g. "capture"), or the
 * literal basename for special assets like "hero-illustration".
 */
function icon(string $name, string $class = ''): string {
    $dir = __DIR__ . '/views/icons/';
    $path = is_file($dir . $name . '.svg')
        ? $dir . $name . '.svg'
        : $dir . 'icon-' . $name . '.svg';
    if (!is_file($path)) return '';
    $svg = file_get_contents($path) ?: '';
    if ($class !== '') {
        $svg = preg_replace('/<svg\b/', '<svg class="' . e($class) . '"', $svg, 1);
    }
    return $svg;
}
