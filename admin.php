<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$adminUser = auth_require_admin();
require __DIR__ . '/admin-view.php';