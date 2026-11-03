<?php
declare(strict_types=1);

/** Private Supabase Storage access (server only). Files are never public. */
final class Storage
{
    private static function path(string $objectPath): string
    {
        $bucket = rawurlencode((string) Env::get('STORAGE_BUCKET', 'student-documents'));
        return $bucket . '/' . implode('/', array_map('rawurlencode', explode('/', $objectPath)));
    }

    public static function upload(string $objectPath, string $bytes): array
    {
        return Supabase::request('POST', '/storage/v1/object/' . self::path($objectPath), [
            'raw' => $bytes, 'content_type' => 'application/pdf', 'headers' => ['x-upsert: false'],
        ]);
    }

    public static function delete(string $objectPath): array
    {
        return Supabase::request('DELETE', '/storage/v1/object/' . self::path($objectPath));
    }

    /** Short-lived link for opening one PDF. */
    public static function signedUrl(string $objectPath, int $seconds = 120): ?string
    {
        $r = Supabase::request('POST', '/storage/v1/object/sign/' . self::path($objectPath), ['body' => ['expiresIn' => $seconds]]);
        $rel = $r['data']['signedURL'] ?? null;
        if (!is_string($rel) || $rel === '') {
            return null;
        }
        $base = preg_replace('#/(rest|auth|storage)/v1/?$#', '', rtrim((string) Env::get('SUPABASE_URL'), '/'));
        return $base . (str_starts_with($rel, '/storage/v1') ? '' : '/storage/v1') . $rel;
    }
}
