<?php
declare(strict_types=1);

/**
 * Parse a URL and return [canonicalUrl, provider].
 *
 * The canonical form strips tracking params and fragments so the same video
 * opened from different contexts (`&feature=share&utm_source=...`) maps to a
 * single review row.
 *
 * Logic ported from the Socrates TypeScript reference:
 *   - YouTube: keep only `v` (and `list` if present). `youtu.be/ID` → canonical.
 *   - Sybill / Drive: keep path, drop query string.
 *   - Other: protocol + host + pathname.
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
    if (str_ends_with($host, 'sybill.ai')) {
        return ["$scheme://{$parts['host']}$path", 'sybill'];
    }
    if ($host === 'drive.google.com' || str_ends_with($host, '.drive.google.com')) {
        return ["$scheme://{$parts['host']}$path", 'gdrive'];
    }
    return ["$scheme://{$parts['host']}$path", 'other'];
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
    if ($provider === 'youtube') {
        $sep = str_contains($canonicalUrl, '?') ? '&' : '?';
        return "$canonicalUrl{$sep}t={$t}s";
    }
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
