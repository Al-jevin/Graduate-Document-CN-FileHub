<?php
declare(strict_types=1);

final class Auth
{
    private const MAX_FAILS_EMAIL = 5;     // per email, per window
    private const MAX_FAILS_IP    = 20;    // per IP, per window
    private const WINDOW          = 900;   // 15 minutes
    private const IDLE_TIMEOUT    = 1800;  // 30 min of inactivity
    private const ABS_TIMEOUT     = 28800; // 8 hours max session

    /* ------------------------------------------------------------ registration */

    /** @return array{ok:bool,errors:string[]} */
    public static function register(array $in): array
    {
        $errors = [];
        $first   = trim((string) ($in['first_name'] ?? ''));
        $last    = trim((string) ($in['last_name'] ?? ''));
        $number  = trim((string) ($in['student_number'] ?? ''));
        $email   = strtolower(trim((string) ($in['email'] ?? '')));
        $pass    = (string) ($in['password'] ?? '');
        $confirm = (string) ($in['confirm_password'] ?? '');

        $nameRx = '/^[\p{L}\p{M}][\p{L}\p{M}\s.\'\-]{0,99}$/u';
        if (!preg_match($nameRx, $first)) { $errors[] = 'Enter a valid first name.'; }
        if (!preg_match($nameRx, $last))  { $errors[] = 'Enter a valid last name.'; }
        if (!preg_match('/^[0-9\-]{5,20}$/', $number)) { $errors[] = 'Enter a valid student number.'; }

        $domain = strtolower(env('ALLOWED_EMAIL_DOMAIN', 'plm.edu.ph'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
            || !str_ends_with($email, '@' . $domain)) {
            $errors[] = "Use your PLM email address (@$domain).";
        }

        if (strlen($pass) < 8)  { $errors[] = 'Password must be at least 8 characters long.'; }
        if (strlen($pass) > 72) { $errors[] = 'Password must be 72 characters or fewer.'; }
        if (!preg_match('/\p{Lu}/u', $pass) || !preg_match('/\p{Ll}/u', $pass) || !preg_match('/\d/', $pass)) {
            $errors[] = 'Password needs an uppercase letter, a lowercase letter, and a number.';
        }
        if (!hash_equals($pass, $confirm)) { $errors[] = 'Passwords do not match.'; }
        if (empty($in['terms']))           { $errors[] = 'You must accept the Terms and Conditions and Data Privacy Notice.'; }

        if ($errors) { return ['ok' => false, 'errors' => $errors]; }

        $redirect = rtrim((string) env('APP_URL', ''), '/') . '/login.php?portal=student';
        $res = Supabase::auth('POST', '/signup?redirect_to=' . rawurlencode($redirect), [
            'email'    => $email,
            'password' => $pass,
            'data'     => ['first_name' => $first, 'last_name' => $last, 'student_number' => $number],
        ]);

        if ($res['status'] === 200) { return ['ok' => true, 'errors' => []]; }

        $code = (string) ($res['data']['error_code'] ?? $res['data']['code'] ?? '');
        error_log("Signup failed: status={$res['status']} code=$code");
        if ($code === 'weak_password')  { return ['ok' => false, 'errors' => ['That password is too weak or has appeared in a data breach. Choose another.']]; }
        if ($res['status'] === 429)     { return ['ok' => false, 'errors' => ['Too many attempts. Please try again later.']]; }
        // Deliberately vague: don't reveal whether an email / student number is already registered.
        return ['ok' => false, 'errors' => ['We could not create your account. Check your details or contact the Graduate School.']];
    }

    /* ------------------------------------------------------------ login */

    /** @return array{ok:bool,error:?string} */
    public static function login(string $email, string $password, string $portal): array
    {
        $email   = strtolower(trim($email));
        $ip      = client_ip();
        $generic = 'Invalid email or password.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $password === '' || strlen($password) > 128) {
            return ['ok' => false, 'error' => $generic];
        }

        if (self::failures('email', $email, self::MAX_FAILS_EMAIL) >= self::MAX_FAILS_EMAIL
            || self::failures('ip', $ip, self::MAX_FAILS_IP) >= self::MAX_FAILS_IP) {
            self::audit(null, $email, $portal, false, 'locked');
            return ['ok' => false, 'error' => 'Too many failed attempts. Please try again in 15 minutes.'];
        }

        $res = Supabase::auth('POST', '/token?grant_type=password', ['email' => $email, 'password' => $password]);

        if ($res['status'] === 0) {
            return ['ok' => false, 'error' => 'The service is temporarily unavailable. Please try again shortly.'];
        }
        if ($res['status'] !== 200 || empty($res['data']['access_token'])) {
            $code = (string) ($res['data']['error_code'] ?? '');
            if ($code === 'email_not_confirmed') {   // Supabase only returns this after the password was correct
                self::audit(null, $email, $portal, false, 'unverified');
                return ['ok' => false, 'error' => 'Please verify your email first. Check your PLM inbox for the confirmation link.'];
            }
            self::audit(null, $email, $portal, false, 'bad_credentials');
            return ['ok' => false, 'error' => $generic];
        }

        $access  = (string) $res['data']['access_token'];
        $refresh = (string) ($res['data']['refresh_token'] ?? '');
        $uid     = (string) ($res['data']['user']['id'] ?? '');

        // Read the profile AS the user (RLS enforced) to get role / active flag.
        $p = Supabase::rest('GET', '/profiles?select=role,first_name,last_name,student_number,is_active&id=eq.' . rawurlencode($uid) . '&limit=1', null, $access);
        $profile = $p['status'] === 200 && !empty($p['data'][0]) ? $p['data'][0] : null;

        if (!$profile || empty($profile['is_active']) || ($profile['role'] ?? '') !== $portal) {
            Supabase::auth('POST', '/logout?scope=local', null, $access);   // revoke the token we just got
            self::audit($uid, $email, $portal, false, 'not_authorized');
            return ['ok' => false, 'error' => $generic];
        }

        session_regenerate_id(true);                 // prevent session fixation
        unset($_SESSION['csrf']);                    // fresh CSRF token for the new session
        $_SESSION['user'] = [
            'id'             => $uid,
            'email'          => $email,
            'role'           => $profile['role'],
            'name'           => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
            'student_number' => $profile['student_number'] ?? null,
        ];
        $_SESSION['tokens']    = ['access' => $access, 'refresh' => $refresh];   // server-side only, never sent to browser
        $_SESSION['login_at']  = time();
        $_SESSION['last_seen'] = time();

        self::audit($uid, $email, $portal, true, 'ok');
        return ['ok' => true, 'error' => null];
    }

    /* ------------------------------------------------------------ session guard */

    public static function user(): ?array
    {
        if (empty($_SESSION['user'])) { return null; }
        $now = time();
        if ($now - ($_SESSION['last_seen'] ?? 0) > self::IDLE_TIMEOUT || $now - ($_SESSION['login_at'] ?? 0) > self::ABS_TIMEOUT) {
            self::logout();
            session_start();
            flash('error', 'Your session has expired. Please log in again.');
            return null;
        }
        $_SESSION['last_seen'] = $now;
        return $_SESSION['user'];
    }

    /** Call at the top of every protected page. */
    public static function guard(?string $role = null): array
    {
        $u = self::user();
        if (!$u) {
            flash('error', flash('error') ?? 'Please log in to continue.');
            redirect('/login.php');
        }
        if ($role !== null && $u['role'] !== $role) {
            http_response_code(403);
            exit('Forbidden');
        }
        return $u;
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['tokens']['access'])) {
            Supabase::auth('POST', '/logout?scope=local', null, $_SESSION['tokens']['access']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $c = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600, 'path' => $c['path'], 'secure' => $c['secure'],
                'httponly' => true, 'samesite' => 'Strict',
            ]);
        }
        session_destroy();
    }

    /* ------------------------------------------------------------ audit + throttling */

    private static function failures(string $col, string $val, int $limit): int
    {
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - self::WINDOW);
        $q = '/login_audit?select=id&success=eq.false&reason=in.(bad_credentials,not_authorized)'
           . '&' . $col . '=eq.' . rawurlencode($val) . '&created_at=gte.' . $since . '&limit=' . $limit;
        $r = Supabase::rest('GET', $q, null, null, true);
        return ($r['status'] === 200 && is_array($r['data'])) ? count($r['data']) : 0;
    }

    private static function audit(?string $uid, string $email, string $portal, bool $ok, string $reason): void
    {
        $r = Supabase::rest('POST', '/login_audit', [
            'user_id'    => $uid,
            'email'      => $email,
            'ip'         => client_ip(),
            'portal'     => $portal,
            'success'    => $ok,
            'reason'     => $reason,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ], null, true, ['Prefer: return=minimal']);
        if ($r['status'] >= 300) { error_log('login_audit insert failed: ' . $r['status']); }
    }
}
