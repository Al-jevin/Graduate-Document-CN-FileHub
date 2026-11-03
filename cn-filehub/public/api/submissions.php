<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

Session::requireLoginJson();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['error' => 'Method not allowed.'], 405);

$scope = ($_GET['scope'] ?? 'all') === 'tasks' ? 'tasks' : 'all';
$q     = Submissions::sanitizeQuery((string) ($_GET['q'] ?? ''));
$page  = max(1, (int) ($_GET['page'] ?? 1));

$result = Submissions::search($scope, $q, $page);
if ($result['error'] !== null) json_response(['error' => $result['error']], 502);

json_response([
    'rows'  => $result['rows'],
    'total' => $result['total'],
    'page'  => $page,
    'pages' => max(1, (int) ceil($result['total'] / Submissions::PAGE_SIZE)),
    'size'  => Submissions::PAGE_SIZE,
    'q'     => $q,
]);
