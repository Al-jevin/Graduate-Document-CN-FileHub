<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

$user = Session::requireLoginJson();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed.'], 405);
if (!Csrf::verify()) json_response(['error' => 'Your session expired. Reload the page and try again.'], 419);

$fail = fn (string $m, int $code = 422) => json_response(['error' => $m], $code);

// --- Student and document type must exist
$studentId = (string) ($_POST['student_id'] ?? '');
if (!is_uuid($studentId) || Students::find($studentId) === null) $fail('Select a student from the list.');
$typeId = (int) ($_POST['document_type_id'] ?? 0);
$typeNames = array_column(Students::documentTypes(), 'name', 'id');
if (!isset($typeNames[$typeId])) $fail('Select a document type from the list.');
$isExam = $typeNames[$typeId] === Students::EXAM_TYPE;

// --- Residency, semester, and (for the Comprehensive Examination) exam date and takes
$residency = (int) ($_POST['year_of_residency'] ?? 0);
if (!isset(Students::RESIDENCY_YEARS[$residency])) $fail('Choose the year of residency.');
$semester = (string) ($_POST['semester'] ?? '');
if (!in_array($semester, Students::SEMESTERS, true)) $fail('Choose the semester.');

$examDate = null; $examTakes = null;
if ($isExam) {
    $raw = (string) ($_POST['exam_date'] ?? '');
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('Asia/Manila'));
    $ok = $dt && $dt->format('Y-m-d') === $raw && (int) $dt->format('Y') >= 1950;
    if (!$ok) $fail('Enter a valid examination date (MM | DD | YYYY).');
    if ($dt > new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'))) $fail('The examination date cannot be in the future.');
    $examDate = $raw;
    $examTakes = (int) ($_POST['exam_takes'] ?? 0);
    if (!isset(Students::EXAM_TAKES[$examTakes])) $fail('Choose the number of takes.');
}

// --- File checks (never trust the browser: extension, MIME type and file signature)
$maxBytes = min(Env::int('MAX_UPLOAD_MB', 10), 10) * 1024 * 1024;
$f = $_FILES['file'] ?? null;
if (!$f || !isset($f['error'])) $fail('Choose a PDF file to upload.');
if ($f['error'] === UPLOAD_ERR_NO_FILE) $fail('Choose a PDF file to upload.');
if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) $fail('The file is larger than the server allows (max ' . ($maxBytes / 1048576) . ' MB).');
if ($f['error'] !== UPLOAD_ERR_OK) $fail('The upload failed. Try again.');
if ($f['size'] <= 0 || $f['size'] > $maxBytes) $fail('The file must be 10 MB or smaller.');

$name = basename(str_replace('\\', '/', (string) $f['name']));
$name = mb_substr((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name), 0, 200);
if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') $fail('Only PDF files are allowed.');
if ((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) !== 'application/pdf') $fail('Only PDF files are allowed.');
$bytes = file_get_contents($f['tmp_name']);
if ($bytes === false || !str_starts_with($bytes, '%PDF-')) $fail('That file is not a valid PDF.');

// --- Store privately, then record it. If the record fails, remove the file again.
$submissionId = uuid_v4();
$path = $studentId . '/' . $submissionId . '.pdf';
$up = Storage::upload($path, $bytes);
if ($up['status'] === 0 || $up['status'] >= 300) $fail('Could not store the file. Try again.', 502);

$rec = Supabase::rpc('create_submission', [
    'p_id' => $submissionId, 'p_student_id' => $studentId, 'p_document_type_id' => $typeId,
    'p_created_by' => $user['id'], 'p_storage_path' => $path, 'p_original_filename' => $name,
    'p_file_size' => strlen($bytes), 'p_sha256' => hash('sha256', $bytes),
    'p_year_of_residency' => $residency, 'p_semester' => $semester,
    'p_exam_date' => $examDate, 'p_exam_takes' => $examTakes,
]);
if ($rec['status'] < 200 || $rec['status'] >= 300) {
    Storage::delete($path);
    $fail('Could not save the submission. Try again.', 502);
}
json_response(['ok' => true, 'tracking_id' => $rec['data']['tracking_id'] ?? '']);
