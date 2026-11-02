<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

redirect(Session::check() ? 'home.php' : 'login.php');
