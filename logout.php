<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
use App\Core\Auth;
Auth::logout();
redirect('/login.php');
