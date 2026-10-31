<?php
declare(strict_types=1);

/** Minimal Supabase HTTP client (Auth + PostgREST) over cURL. */
final class Supabase
{
    private static function base(): string
    {
        $url = env('SUPABASE_URL');
        if (!$url) { throw new RuntimeException('SUPABASE_URL is not configured.'); }
        return rtrim($url, '/');
    }

    private static function key(bool $service): string
    {
        $k = env($service ? 'SUPABASE_SERVICE_ROLE_KEY' : 'SUPABASE_ANON_KEY');
        if (!$k) { throw new RuntimeException('Supabase API key is not configured.'); }
        return $k;
    }

    /** Supabase Auth (GoTrue): path like "/token?grant_type=password". Uses the anon key. */
    public static function auth(string $method, string $path, ?array $body = null, ?string $bearer = null): array
    {
        $key = self::key(false);
        return self::send($method, self::base() . '/auth/v1' . $path, $body, [
            'apikey: ' . $key,
            'Authorization: Bearer ' . ($bearer ?? $key),
        ]);
    }

    /** PostgREST: path like "/profiles?id=eq.123". $bearer = act as that user (RLS applies); $service = admin access. */
    public static function rest(string $method, string $path, ?array $body = null, ?string $bearer = null, bool $service = false, array $extra = []): array
    {
        $key = self::key($service);
        return self::send($method, self::base() . '/rest/v1' . $path, $body, array_merge([
            'apikey: ' . $key,
            'Authorization: Bearer ' . ($bearer ?? $key),
        ], $extra));
    }

    private static function send(string $method, string $url, ?array $body, array $headers): array
    {
        $headers[] = 'Accept: application/json';
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;

        $ch = curl_init($url);
        curl_setopt_array($ch, $opts);
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) { error_log('Supabase cURL error: ' . curl_error($ch)); }
        curl_close($ch);

        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        return ['status' => $status, 'data' => is_array($data) ? $data : null];
    }
}
