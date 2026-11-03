<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

$user = Session::requireLoginJson();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed.'], 405);
if (!Csrf::verify()) json_response(['error' => 'Your session expired. Reload the page and try again.'], 419);

$id = (string) ($_POST['id'] ?? '');
if (!is_uuid($id)) json_response(['error' => 'Invalid submission.'], 422);

$result = Submissions::delete($id, $user['id']);
if (!$result['ok']) json_response(['error' => 'Could not delete the submission. It may already be deleted.'], 404);
json_response(['ok' => true]);
