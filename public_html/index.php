<?php
declare(strict_types=1);

// Front controller. Every request Apache can't serve as a file hits here.

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/video_reviews.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path   = rtrim($path, '/') ?: '/';

// CORS preflight for API endpoints.
if ($method === 'OPTIONS' && str_starts_with($path, '/api/')) {
    cors_headers();
    http_response_code(204);
    exit;
}

// Route table: each entry is [method, regex, handler file].
// The first capture group becomes $params[0], etc.
$routes = [
    ['GET',    '#^/$#',                                 'home.php'],
    ['GET',    '#^/extension$#',                        'extension_install.php'],
    ['GET',    '#^/extension\.zip$#',                   'extension_download.php'],
    ['GET',    '#^/signup$#',                           'signup.php'],
    ['POST',   '#^/signup$#',                           'signup.php'],
    ['GET',    '#^/signin$#',                           'signin.php'],
    ['POST',   '#^/signin$#',                           'signin.php'],
    ['POST',   '#^/signout$#',                          'signout.php'],

    ['GET',    '#^/video-reviews$#',                    'reviews_list.php'],
    ['GET',    '#^/video-reviews/([A-Z0-9]{26})$#',     'reviews_detail.php'],
    ['POST',   '#^/video-reviews/([A-Z0-9]{26})/delete$#', 'reviews_delete.php'],
    ['POST',   '#^/video-reviews/([A-Z0-9]{26})/share$#',  'reviews_share.php'],
    ['GET',    '#^/video-reviews/([A-Z0-9]{26})/export$#', 'reviews_export.php'],
    ['POST',   '#^/video-reviews/critiques/([A-Z0-9]{26})/delete$#', 'critiques_delete.php'],
    ['POST',   '#^/video-reviews/critiques/([A-Z0-9]{26})/edit$#',   'critiques_edit.php'],

    ['GET',    '#^/share/video-review/([a-f0-9]{40})$#','share.php'],

    ['GET',    '#^/settings/api-tokens$#',              'api_tokens.php'],
    ['POST',   '#^/settings/api-tokens$#',              'api_tokens.php'],
    ['POST',   '#^/settings/api-tokens/([A-Z0-9]{26})/delete$#', 'api_tokens.php'],

    ['GET',    '#^/feedback$#',                          'feedback.php'],
    ['POST',   '#^/feedback$#',                          'feedback.php'],

    ['GET',    '#^/admin/suggestions$#',                 'admin_suggestions.php'],
    ['POST',   '#^/admin/suggestions/([A-Z0-9]{26})/status$#', 'admin_suggestions.php'],

    // Extension API (Bearer authed, returns JSON, CORS).
    ['GET',    '#^/api/v1/video-reviews$#',                               'api_v1_reviews.php'],
    ['POST',   '#^/api/v1/video-reviews$#',                               'api_v1_reviews.php'],
    ['GET',    '#^/api/v1/video-reviews/by-url$#',                        'api_v1_by_url.php'],
    ['GET',    '#^/api/v1/video-reviews/([A-Z0-9]{26})$#',                'api_v1_review_item.php'],
    ['PATCH',  '#^/api/v1/video-reviews/([A-Z0-9]{26})$#',                'api_v1_review_item.php'],
    ['DELETE', '#^/api/v1/video-reviews/([A-Z0-9]{26})$#',                'api_v1_review_item.php'],
    ['PATCH',  '#^/api/v1/video-reviews/critiques/([A-Z0-9]{26})$#',      'api_v1_critique_item.php'],
    ['DELETE', '#^/api/v1/video-reviews/critiques/([A-Z0-9]{26})$#',      'api_v1_critique_item.php'],
];

foreach ($routes as [$m, $re, $file]) {
    if ($m !== $method) continue;
    if (preg_match($re, $path, $match)) {
        $params = array_slice($match, 1);
        require __DIR__ . '/../app/handlers/' . $file;
        exit;
    }
}

// Method-not-allowed vs not-found distinction. Only a hint for humans.
foreach ($routes as [$m, $re, ]) {
    if (preg_match($re, $path)) {
        http_response_code(405);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Method not allowed.\n";
        exit;
    }
}

http_response_code(404);
if (str_starts_with($path, '/api/')) {
    json_response(404, ['error' => ['message' => 'Not found']]);
}
header('Content-Type: text/plain; charset=utf-8');
echo "Not found.\n";
