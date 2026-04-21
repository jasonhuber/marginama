<?php
declare(strict_types=1);

require_csrf();
$__u = current_user();
track_event('auth.signout', null, [], $__u['id'] ?? null);
sign_out();
header('Location: /');
