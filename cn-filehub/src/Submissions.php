<?php
declare(strict_types=1);

/** Reads and changes submissions (all writes go through the database functions). */
final class Submissions
{
    public const PAGE_SIZE = 15;
    /** Statuses an admin can choose, in the order the design shows them. "Submitted" is system-only. */
    public const REVIEW_STATUSES = ['Rejected', 'Completed', 'Accepted'];

    /** Keep search text safe for a PostgREST filter (no commas, brackets, quotes or wildcards). */
    public static function sanitizeQuery(string $q): string
    {
        $q = (string) preg_replace('/[^\p{L}\p{N} \-_.@#\/]/u', '', $q);
        return mb_substr(trim($q), 0, 60);
    }

    /** @return array{rows:array,total:int,error:?string} */
    public static function search(string $scope, string $q, int $page): array
    {
        $page = max(1, $page);
        $query = [
            'select' => 'id,tracking_id,student_name,student_number,document_name,status,created_at',
            'order'  => 'created_at.desc,id.desc',
            'limit'  => (string) self::PAGE_SIZE,
            'offset' => (string) (($page - 1) * self::PAGE_SIZE),
        ];
        if ($scope === 'tasks') {
            $query['status'] = 'eq.Submitted';
        }
        if ($q !== '') {
            $query['or'] = sprintf(
                '(tracking_id.ilike.*%1$s*,student_name.ilike.*%1$s*,student_number.ilike.*%1$s*,document_name.ilike.*%1$s*,status.ilike.*%1$s*)',
                $q
            );
        }
        $r = Supabase::rest('GET', 'submission_list', ['query' => $query, 'prefer' => 'count=exact']);
        if ($r['status'] !== 200 && $r['status'] !== 206 || !is_array($r['data'])) {
            return ['rows' => [], 'total' => 0, 'error' => $r['status'] === 404
                ? 'The submission list is not set up yet. Run database/02_submission_list_view.sql.'
                : 'Could not load submissions. Try again.'];
        }
        $rows = [];
        foreach ($r['data'] as $i => $row) {
            $rows[] = [
                'n'              => ($page - 1) * self::PAGE_SIZE + $i + 1,
                'id'             => $row['id'],
                'tracking_id'    => $row['tracking_id'],
                'student_name'   => $row['student_name'],
                'student_number' => $row['student_number'],
                'document_name'  => $row['document_name'],
                'date'           => format_manila($row['created_at']),
                'status'         => $row['status'],
            ];
        }
        return ['rows' => $rows, 'total' => $r['total'] ?? count($rows), 'error' => null];
    }

    public static function find(string $id): ?array
    {
        $r = Supabase::rest('GET', 'submission_list', ['query' => ['select' => '*', 'id' => 'eq.' . $id, 'limit' => '1']]);
        return is_array($r['data'] ?? null) ? ($r['data'][0] ?? null) : null;
    }

    public static function history(string $id): array
    {
        $r = Supabase::rest('GET', 'submission_status_history', ['query' => [
            'select' => 'old_status,new_status,remarks,changed_at,profiles(first_name,last_name)',
            'submission_id' => 'eq.' . $id, 'order' => 'changed_at.desc',
        ]]);
        return is_array($r['data'] ?? null) ? $r['data'] : [];
    }

    /** @return array{ok:bool,error:?string} */
    public static function review(string $id, string $status, string $remarks, string $adminId): array
    {
        return self::result(Supabase::rpc('review_submission', [
            'p_submission_id' => $id, 'p_status' => $status, 'p_remarks' => $remarks, 'p_admin_id' => $adminId,
        ]));
    }

    /** Soft delete. @return array{ok:bool,error:?string} */
    public static function delete(string $id, string $adminId): array
    {
        return self::result(Supabase::rpc('soft_delete_submission', ['p_submission_id' => $id, 'p_admin_id' => $adminId]));
    }

    private static function result(array $r): array
    {
        if ($r['status'] >= 200 && $r['status'] < 300) {
            return ['ok' => true, 'error' => null];
        }
        return ['ok' => false, 'error' => $r['status'] === 0 ? 'Cannot reach the server. Try again.' : (string) $r['error']];
    }
}
