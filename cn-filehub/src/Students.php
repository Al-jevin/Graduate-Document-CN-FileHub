<?php
declare(strict_types=1);

/** Student records and the lists the Records pages need. */
final class Students
{
    public const EXAM_TYPE = 'Comprehensive Examination';
    public const RESIDENCY_YEARS = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year'];
    public const SEMESTERS = ['1st Semester', '2nd Semester', 'Summer'];
    public const EXAM_TAKES = [1 => '1st take', 2 => '2nd take', 3 => '3rd take', 4 => '4th take', 5 => '5th take'];

    /** Academic years, newest first, starting with the current one (school year begins in August). */
    public static function academicYears(int $count = 30): array
    {
        $start = (int) date('n') >= 8 ? (int) date('Y') : (int) date('Y') - 1;
        $years = [];
        for ($i = 0; $i < $count; $i++) {
            $years[] = ($start - $i) . '-' . ($start - $i + 1);
        }
        return $years;
    }

    public static function all(): array
    {
        $r = Supabase::rest('GET', 'students', ['query' => [
            'select' => 'id,student_number,first_name,last_name,graduate_year_level,is_former',
            'deleted_at' => 'is.null', 'order' => 'last_name.asc,first_name.asc', 'limit' => '2000',
        ]]);
        return is_array($r['data'] ?? null) ? $r['data'] : [];
    }

    public static function find(string $id): ?array
    {
        $r = Supabase::rest('GET', 'students', ['query' => ['select' => 'id', 'id' => 'eq.' . $id, 'deleted_at' => 'is.null', 'limit' => '1']]);
        return is_array($r['data'] ?? null) ? ($r['data'][0] ?? null) : null;
    }

    /** The approved document types, exactly as stored in the database. */
    public static function documentTypes(): array
    {
        $r = Supabase::rest('GET', 'document_types', ['query' => ['select' => 'id,name', 'is_active' => 'eq.true', 'order' => 'sort_order.asc']]);
        return is_array($r['data'] ?? null) ? $r['data'] : [];
    }

    /** @return array{ok:bool,errors:array<string,string>,id:?string} */
    public static function create(array $in, string $adminId): array
    {
        $clean = fn ($v) => trim((string) preg_replace('/\s+/u', ' ', trim((string) $v)));
        $d = [
            'first_name' => $clean($in['first_name'] ?? ''),
            'last_name'  => $clean($in['last_name'] ?? ''),
            'student_number' => trim((string) ($in['student_number'] ?? '')),
            'graduate_year_level' => $clean($in['graduate_year_level'] ?? ''),
            'section_cohort' => $clean($in['section_cohort'] ?? ''),
            'email' => strtolower(trim((string) ($in['email'] ?? ''))),
            'is_former' => !empty($in['is_former']),
            'academic_year' => null,
        ];
        $errors = [];
        $nameRe = '/^\p{L}[\p{L}\p{M} .\'\-]{0,59}$/u';
        if (!preg_match($nameRe, $d['first_name'])) $errors['first_name'] = 'enter a valid first name';
        if (!preg_match($nameRe, $d['last_name']))  $errors['last_name']  = 'enter a valid last name';
        if (!preg_match('/^[0-9]{4}-[0-9]{5}$/', $d['student_number'])) $errors['student_number'] = 'use the format XXXX-XXXXX';
        if ($d['graduate_year_level'] === '' || mb_strlen($d['graduate_year_level']) > 30) $errors['graduate_year_level'] = 'enter the year level';
        if ($d['section_cohort'] === '' || mb_strlen($d['section_cohort']) > 40) $errors['section_cohort'] = 'enter the section or cohort';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 254) $errors['email'] = 'enter a valid email address';

        if (!$d['is_former']) {
            $ay = (string) ($in['academic_year'] ?? '');
            if (!in_array($ay, self::academicYears(), true)) {
                $errors['academic_year'] = 'choose an academic year';
            } else {
                $d['academic_year'] = $ay;
            }
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $r = Supabase::rest('POST', 'students', ['prefer' => 'return=representation', 'body' => $d + ['created_by' => $adminId]]);
        if ($r['status'] === 409) {
            return ['ok' => false, 'errors' => ['student_number' => 'a student with this number already exists'], 'id' => null];
        }
        $id = $r['data'][0]['id'] ?? null;
        if ($r['status'] >= 300 || $r['status'] === 0 || !is_string($id)) {
            return ['ok' => false, 'errors' => ['_form' => 'Could not save the student record. Try again.'], 'id' => null];
        }
        Auth::log('student.create', $adminId, ['student_number' => $d['student_number']], 'student', $id);
        return ['ok' => true, 'errors' => [], 'id' => $id];
    }
}
