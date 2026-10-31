<?php
require_once __DIR__ . '/../src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/login.php'); }   // POST-only so a link/image can't log you out
csrf_verify();
$portal = ($_SESSION['user']['role'] ?? 'admin') === 'student' ? 'student' : 'admin';
Auth::logout();
session_start();
flash('success', 'You have been logged out.');
redirect('/login.php?portal=' . $portal);
