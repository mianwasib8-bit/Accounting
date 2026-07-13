<?php
/**
 * Application Bootstrap — include once from every entry point
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once APP_PATH . '/Core/Autoloader.php';

use App\Core\Auth;

Auth::start();
