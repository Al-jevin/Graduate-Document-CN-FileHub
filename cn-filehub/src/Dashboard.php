<?php
declare(strict_types=1);

/** Everything the Home dashboard shows. All data comes from the shared database. */
final class Dashboard
{
    public const LIST_LIMIT = 200;
    private const COLS = 'id,tracking_id,student_name,student_number,document_name,status,created_at';
    private static ?string $error = null;

    public static function error(): ?string
    {
        return self::$error;
    }

    private static function tz(string $name = 'Asia/Manila'): DateTimeZone
    {
        return new DateTimeZone($name);
    }

    /** "2026-11-01 00:00:00" in Manila time -> UTC timestamp for filtering. */
    private static function utc(string $manilaDateTime): string
    {
        return (new DateTimeImmutable($manilaDateTime, self::tz()))->setTimezone(self::tz('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /** @return array{rows:array,total:int} */
    private static function get(string $table, array $query, bool $count = false): array
    {
        $opts = ['query' => $query];
        if ($count) {
            $opts['prefer'] = 'count=exact';
        }
        $r = Supabase::rest('GET', $table, $opts);
        if (($r['status'] !== 200 && $r['status'] !== 206) || !is_array($r['data'])) {
            self::$error = $r['status'] === 404
                ? 'The submission list is not set up. Run database/02_submission_list_view.sql.'
                : 'Some dashboard data could not be loaded.';
            return ['rows' => [], 'total' => 0];
        }
        return ['rows' => $r['data'], 'total' => $r['total'] ?? count($r['data'])];
    }

    private static function row(array $r): array
    {
        return [
            'id' => $r['id'], 'tracking_id' => $r['tracking_id'], 'student_name' => $r['student_name'],
            'student_number' => $r['student_number'] ?? '', 'document_name' => $r['document_name'],
            'status' => $r['status'], 'date' => format_manila($r['created_at']),
        ];
    }

    public static function counts(): array
    {
        $monthStart = self::utc(date('Y-m-01 00:00:00'));
        return [
            'documents' => self::get('submission_list', ['select' => 'id', 'limit' => '1'], true)['total'],
            'students'  => self::get('students', ['select' => 'id', 'deleted_at' => 'is.null', 'limit' => '1'], true)['total'],
            'month'     => self::get('submission_list', ['select' => 'id', 'created_at' => 'gte.' . $monthStart, 'limit' => '1'], true)['total'],
        ];
    }

    public static function recent(int $limit = 10): array
    {
        $r = self::get('submission_list', ['select' => self::COLS, 'order' => 'created_at.desc,id.desc', 'limit' => (string) $limit]);
        return array_map([self::class, 'row'], $r['rows']);
    }

    /** Submissions nobody has reviewed yet. */
    public static function tasks(int $limit = 12): array
    {
        $r = self::get('submission_list', ['select' => self::COLS, 'status' => 'eq.Submitted', 'order' => 'created_at.desc,id.desc', 'limit' => (string) $limit]);
        return array_map([self::class, 'row'], $r['rows']);
    }

    public static function snapshot(): array
    {
        self::$error = null;
        $out = ['counts' => self::counts(), 'recent' => self::recent(10), 'tasks' => self::tasks(12)];
        $out['error'] = self::$error;
        return $out;
    }

    /** Rows behind a Submission Overview card. */
    public static function overview(string $type): array
    {
        if ($type === 'students') {
            $r = self::get('students', [
                'select' => 'id,student_number,first_name,last_name,graduate_year_level,section_cohort,is_former,academic_year',
                'deleted_at' => 'is.null', 'order' => 'last_name.asc,first_name.asc', 'limit' => (string) self::LIST_LIMIT,
            ], true);
            $rows = array_map(fn ($s) => [
                'student_number' => $s['student_number'], 'name' => $s['last_name'] . ', ' . $s['first_name'],
                'level' => $s['graduate_year_level'], 'section' => $s['section_cohort'],
                'former' => (bool) $s['is_former'], 'academic_year' => $s['academic_year'] ?? '',
            ], $r['rows']);
            return ['type' => $type, 'rows' => $rows, 'total' => $r['total'], 'limit' => self::LIST_LIMIT];
        }
        $query = ['select' => self::COLS, 'order' => 'created_at.desc,id.desc', 'limit' => (string) self::LIST_LIMIT];
        if ($type === 'month') {
            $query['created_at'] = 'gte.' . self::utc(date('Y-m-01 00:00:00'));
        }
        $r = self::get('submission_list', $query, true);
        return ['type' => $type, 'rows' => array_map([self::class, 'row'], $r['rows']), 'total' => $r['total'], 'limit' => self::LIST_LIMIT];
    }

    /** Per-day counts of uploads and reviews for one calendar month (Manila time). */
    public static function monthActivity(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $end = (new DateTimeImmutable($start, self::tz()))->modify('+1 month')->format('Y-m-d H:i:s');
        $s = self::utc($start);
        $e = self::utc($end);
        $days = [];
        foreach (['created_at' => 'uploaded', 'reviewed_at' => 'reviewed'] as $col => $label) {
            $r = self::get('submission_list', ['select' => $col, 'and' => "($col.gte.$s,$col.lt.$e)", 'limit' => '5000']);
            foreach ($r['rows'] as $row) {
                $d = substr(format_manila($row[$col]), 0, 10);
                $days[$d][$label] = ($days[$d][$label] ?? 0) + 1;
            }
        }
        foreach ($days as &$d) {
            $d += ['uploaded' => 0, 'reviewed' => 0];
        }
        unset($d);
        return $days;
    }

    /** Documents uploaded or reviewed on one date. */
    public static function dayActivity(string $date): array
    {
        $s = self::utc($date . ' 00:00:00');
        $e = self::utc((new DateTimeImmutable($date, self::tz()))->modify('+1 day')->format('Y-m-d') . ' 00:00:00');
        $items = [];
        foreach (['created_at' => 'Uploaded', 'reviewed_at' => 'Reviewed'] as $col => $action) {
            $r = self::get('submission_list', [
                'select' => 'id,tracking_id,student_name,document_name,status,created_at,reviewed_at',
                'and' => "($col.gte.$s,$col.lt.$e)", 'order' => $col . '.desc', 'limit' => '200',
            ]);
            foreach ($r['rows'] as $row) {
                $items[] = [
                    'id' => $row['id'], 'tracking_id' => $row['tracking_id'], 'student_name' => $row['student_name'],
                    'document_name' => $row['document_name'], 'status' => $row['status'], 'action' => $action,
                    'time' => substr(format_manila($row[$col]), 11, 5), 'sort' => $row[$col],
                ];
            }
        }
        usort($items, fn ($a, $b) => strcmp($b['sort'], $a['sort']));
        return array_map(function ($i) { unset($i['sort']); return $i; }, $items);
    }
}
