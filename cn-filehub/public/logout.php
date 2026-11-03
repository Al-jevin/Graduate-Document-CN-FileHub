<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
Csrf::require();
if ($user = Session::user()) Auth::log('auth.logout', $user['id']);
Session::destroy();
redirect('login.php');
