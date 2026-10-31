<?php
require_once __DIR__ . '/../src/bootstrap.php';
redirect(Auth::user() ? '/dashboard.php' : '/login.php');
