<?php
declare(strict_types=1);

// GET /extension.zip
// Builds a zip of the extension/ directory on the fly and streams it.
// No auth — the extension code is public on GitHub anyway.

$extDir = realpath(__DIR__ . '/../../extension');
if (!$extDir || !is_dir($extDir)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Extension source directory not found.\n";
    exit;
}

if (!class_exists(ZipArchive::class)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ZipArchive is not available on this server.\n";
    exit;
}

$tmpFile = tempnam(sys_get_temp_dir(), 'marginama-ext-');
if ($tmpFile === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Could not allocate temp file.\n";
    exit;
}

$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmpFile);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Could not create zip.\n";
    exit;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($extDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $absolute = $file->getPathname();
    $relative = ltrim(substr($absolute, strlen($extDir)), DIRECTORY_SEPARATOR);
    // Skip dotfiles inside the extension (e.g. .gitignore) — not needed at runtime.
    if (str_starts_with(basename($relative), '.')) continue;
    $zip->addFile($absolute, 'marginama-extension/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative));
}
$zip->close();

$size = filesize($tmpFile);
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="marginama-extension.zip"');
if ($size !== false) {
    header('Content-Length: ' . $size);
}
header('Cache-Control: private, no-store');
readfile($tmpFile);
@unlink($tmpFile);
