<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

Session::requireLoginJson();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['error' => 'Method not allowed.'], 405);

$part = (string) ($_GET['part'] ?? 'snapshot');

switch ($part) {
    case 'snapshot':
        json_response(Dashboard::snapshot());

    case 'overview':
        $type = (string) ($_GET['type'] ?? '');
        if (!in_array($type, ['documents', 'students', 'month'], true)) json_response(['error' => 'Unknown list.'], 422);
        $out = Dashboard::overview($type);
        break;

    case 'month':
        $y = (int) ($_GET['year'] ?? 0);
        $m = (int) ($_GET['month'] ?? 0);
        if ($y < 2000 || $y > 2100 || $m < 1 || $m > 12) json_response(['error' => 'Invalid month.'], 422);
        $out = ['days' => (object) Dashboard::monthActivity($y, $m)];
        break;

    case 'day':
        $date = (string) ($_GET['date'] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $p) || !checkdate((int) $p[2], (int) $p[3], (int) $p[1])) {
            json_response(['error' => 'Invalid date.'], 422);
        }
        $out = ['items' => Dashboard::dayActivity($date)];
        break;

    default:
        json_response(['error' => 'Unknown request.'], 400);
}

if (Dashboard::error() !== null) json_response(['error' => Dashboard::error()], 502);
json_response($out);
