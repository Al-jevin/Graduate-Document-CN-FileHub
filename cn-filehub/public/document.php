<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

// Only signed-in admins get a link, and the link expires after 2 minutes.
$user = Session::requireLogin();
$id = (string) ($_GET['id'] ?? '');
$sub = is_uuid($id) ? Submissions::find($id) : null;
if ($sub === null || empty($sub['storage_path'])) abort(404, 'Document not found.');

$url = Storage::signedUrl($sub['storage_path'], 120);
if ($url === null) abort(502, 'Could not open the document. Close this tab and try again.');

Auth::log('submission.open', $user['id'], ['tracking_id' => $sub['tracking_id']], 'submission', $id);
redirect($url);
