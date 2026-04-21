<?php
declare(strict_types=1);

require_csrf();
sign_out();
header('Location: /');
