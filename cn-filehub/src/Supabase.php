<?php
declare(strict_types=1);

/**
 * Thin server-side Supabase client (cURL).
 * Uses the service-role key by default. Never call this from browser code.
 */
final class Supabase
{
    /**
     * @param array{query?:array,body?:mixed,key?:string,token?:string,prefer?:string,headers?:array,raw?:string,content_type?:string} $opts
     * @return array{status:int,data:mixed,error:?string}
     */
    public static function request(string $method, string $path, array $opts = []): array
    {
        // Accept the URL even if it was pasted with /rest/v1/ on the end.
        $base = preg_replace('#/(rest|auth|storage)/v1/?$#', '', rtrim((string) Env::get('SUPABASE_URL'), '/'));
        $key  = ($opts['key'] ?? 'service') === 'anon'
            ? (string) Env::get('SUPABASE_ANON_KEY')
            : (string) Env::get('SUPABASE_SERVICE_ROLE_KEY');

        if ($base === '' || $key === '') {
            return ['status' => 0, 'data' => null, 'error' => 'Supabase is not configured. Check your .env file.'];
        }

        $url = $base . $path;
        if (!empty($opts['query'])) {
            $url .= '?' . http_build_query($opts['query']);
        }

        $headers = [
            'apikey: ' . $key,
            'Authorization: Bearer ' . ($opts['token'] ?? $key),
            'Accept: application/json',
        ];
        if (!empty($opts['prefer'])) {
            $headers[] = 'Prefer: ' . $opts['prefer'];
        }
        foreach ($opts['headers'] ?? [] as $h) {
            $headers[] = $h;
        }

        $payload = null;
        if (isset($opts['raw'])) {
            $payload   = $opts['raw'];
            $headers[] = 'Content-Type: ' . ($opts['content_type'] ?? 'application/octet-stream');
        } elseif (array_key_exists('body', $opts)) {
            $payload   = json_encode($opts['body'], JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        if (Env::has('CURL_CA_BUNDLE')) {
            curl_setopt($ch, CURLOPT_CAINFO, (string) Env::get('CURL_CA_BUNDLE'));
        }

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['status' => 0, 'data' => null, 'error' => 'Network error: ' . $curlErr];
        }

        $data  = $response === '' ? null : json_decode($response, true);
        $error = null;
        if ($status >= 400) {
            $error = is_array($data)
                ? (string) ($data['message'] ?? $data['msg'] ?? $data['error_description'] ?? $data['error'] ?? 'Request failed')
                : 'Request failed';
        }
        return ['status' => $status, 'data' => $data, 'error' => $error];
    }

    /** PostgREST table call, e.g. Supabase::rest('GET', 'students', ['query' => ['select' => '*']]) */
    public static function rest(string $method, string $table, array $opts = []): array
    {
        return self::request($method, '/rest/v1/' . ltrim($table, '/'), $opts);
    }

    /** Call a database function (RPC). */
    public static function rpc(string $function, array $args = []): array
    {
        return self::request('POST', '/rest/v1/rpc/' . $function, ['body' => (object) $args]);
    }
}
