<?php
declare(strict_types=1);

/** Authentication: login, registration, password rules, rate limiting, audit log. */
final class Auth
{
    // ---------- Password policy (mirrored in assets/js/form-validate.js) ----------
    public static function passwordError(string $pw): ?string
    {
        if (mb_strlen($pw) < 8)               return 'must be 8 characters long';
        if (mb_strlen($pw) > 72)              return 'must be 72 characters or fewer';
        if (!preg_match('/[A-Z]/', $pw))      return 'needs an uppercase letter';
        if (!preg_match('/[a-z]/', $pw))      return 'needs a lowercase letter';
        if (!preg_match('/[0-9]/', $pw))      return 'needs a number';
        if (!preg_match('/[^A-Za-z0-9\s]/', $pw)) return 'needs a special character';
        return null;
    }

    // ---------- Rate limiting & audit ----------
    private static function failedCount(string $kind, string $column, string $value, int $minutes, int $cap): int
    {
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - $minutes * 60);
        $r = Supabase::rest('GET', 'auth_attempts', ['query' => [
            'select' => 'id', $column => 'eq.' . $value, 'kind' => 'eq.' . $kind,
            'succeeded' => 'eq.false', 'created_at' => 'gte.' . $since, 'limit' => (string) $cap,
        ]]);
        return ($r['status'] === 200 && is_array($r['data'])) ? count($r['data']) : 0;
    }

    public static function recordAttempt(string $kind, string $identifier, bool $succeeded): void
    {
        Supabase::rest('POST', 'auth_attempts', ['prefer' => 'return=minimal', 'body' => [
            'identifier' => $identifier, 'kind' => $kind, 'ip_address' => client_ip(), 'succeeded' => $succeeded,
        ]]);
    }

    private static function clearFailures(string $kind, string $identifier): void
    {
        Supabase::rest('DELETE', 'auth_attempts', ['query' => [
            'identifier' => 'eq.' . $identifier, 'kind' => 'eq.' . $kind, 'succeeded' => 'eq.false',
        ]]);
    }

    public static function isRateLimited(string $kind, string $identifier): bool
    {
        $max  = Env::int('LOGIN_MAX_ATTEMPTS', 5);
        $mins = Env::int('LOGIN_LOCKOUT_MINUTES', 15);
        return self::failedCount($kind, 'identifier', $identifier, $mins, $max) >= $max
            || self::failedCount($kind, 'ip_address', client_ip(), $mins, $max * 4) >= $max * 4;
    }

    public static function lockoutMessage(): string
    {
        return 'Too many attempts. Try again in ' . Env::int('LOGIN_LOCKOUT_MINUTES', 15) . ' minutes.';
    }

    public static function log(string $action, ?string $actorId, array $details = [], ?string $entityType = null, ?string $entityId = null): void
    {
        Supabase::rest('POST', 'activity_logs', ['prefer' => 'return=minimal', 'body' => [
            'actor_id' => $actorId, 'action' => $action, 'entity_type' => $entityType,
            'entity_id' => $entityId, 'details' => (object) $details, 'ip_address' => client_ip(),
        ]]);
    }

    // ---------- Login ----------
    /** @return array{ok:bool,error:?string} */
    public static function attemptLogin(string $username, string $password): array
    {
        $fail = fn (string $m) => ['ok' => false, 'error' => $m];
        $generic = 'Incorrect username or password.';

        if ($username === '' || $password === '') {
            return $fail('Enter your username and password.');
        }
        $identifier = strtolower(mb_substr($username, 0, 60));

        if (self::isRateLimited('login', $identifier)) {
            return $fail(self::lockoutMessage());
        }
        if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $username) || strlen($password) > 200) {
            self::recordAttempt('login', $identifier, false);
            return $fail($generic);
        }

        $lookup = Supabase::rpc('resolve_login_email', ['p_username' => $username]);
        if ($lookup['status'] === 0) {
            return $fail('Cannot reach the server. Check your connection and try again.');
        }
        $email = is_string($lookup['data']) && $lookup['data'] !== '' ? $lookup['data'] : null;
        if ($email === null) {
            self::recordAttempt('login', $identifier, false);
            return $fail($generic);
        }

        $token = Supabase::request('POST', '/auth/v1/token', [
            'key' => 'anon', 'query' => ['grant_type' => 'password'],
            'body' => ['email' => $email, 'password' => $password],
        ]);
        if ($token['status'] === 0) {
            return $fail('Cannot reach the server. Check your connection and try again.');
        }
        $userId = $token['data']['user']['id'] ?? null;
        if ($token['status'] !== 200 || !is_string($userId)) {
            self::recordAttempt('login', $identifier, false);
            return $fail($generic);
        }

        $profile = Supabase::rest('GET', 'profiles', ['query' => [
            'select' => 'id,username,first_name,last_name,email,is_active', 'id' => 'eq.' . $userId, 'limit' => '1',
        ]]);
        $row = $profile['data'][0] ?? null;
        if (!$row || !$row['is_active']) {
            self::recordAttempt('login', $identifier, false);
            return $fail($generic);
        }

        self::clearFailures('login', $identifier);
        self::recordAttempt('login', $identifier, true);
        Session::login([
            'id' => $row['id'], 'username' => $row['username'],
            'first_name' => $row['first_name'], 'last_name' => $row['last_name'], 'email' => $row['email'],
        ]);
        self::log('auth.login', $row['id']);
        return ['ok' => true, 'error' => null];
    }

    // ---------- Registration ----------
    private static function clean(mixed $v): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', trim((string) $v)));
    }

    /** @return array{ok:bool,errors:array<string,string>} */
    public static function register(array $in): array
    {
        $ip = client_ip();
        if (self::failedCount('register', 'ip_address', $ip, 15, 10) >= 10) {
            return ['ok' => false, 'errors' => ['_form' => 'Too many attempts. Try again in 15 minutes.']];
        }

        $d = [
            'first_name' => self::clean($in['first_name'] ?? ''),
            'last_name'  => self::clean($in['last_name'] ?? ''),
            'employee_number' => self::clean($in['employee_number'] ?? ''),
            'position'   => self::clean($in['position'] ?? ''),
            'email'      => strtolower(trim((string) ($in['email'] ?? ''))),
            'username'   => trim((string) ($in['username'] ?? '')),
        ];
        $password = (string) ($in['password'] ?? '');
        $confirm  = (string) ($in['password_confirm'] ?? '');
        $errors = [];

        $nameRe = '/^\p{L}[\p{L}\p{M} .\'\-]{0,59}$/u';
        if (!preg_match($nameRe, $d['first_name'])) $errors['first_name'] = 'enter a valid first name';
        if (!preg_match($nameRe, $d['last_name']))  $errors['last_name']  = 'enter a valid last name';
        if (!preg_match('/^[A-Za-z0-9\-]{3,30}$/', $d['employee_number'])) $errors['employee_number'] = 'use letters, numbers and hyphens (3 to 30)';
        if (mb_strlen($d['position']) < 2 || mb_strlen($d['position']) > 80) $errors['position'] = 'enter your position';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 254) $errors['email'] = 'enter a valid email address';
        if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $d['username'])) $errors['username'] = 'use 4 to 30 letters, numbers, _ or .';
        if (($m = self::passwordError($password)) !== null) $errors['password'] = $m;
        if ($password !== $confirm) $errors['password_confirm'] = 'password confirmation does not match';
        if (empty($in['terms'])) $errors['terms'] = 'accept the Terms and Conditions to continue';

        if (!$errors) {
            $or = sprintf('(username.eq."%s",email.eq."%s",employee_number.eq."%s")', $d['username'], $d['email'], $d['employee_number']);
            $dup = Supabase::rest('GET', 'profiles', ['query' => ['select' => 'username,email,employee_number', 'or' => $or]]);
            if ($dup['status'] !== 200) {
                return ['ok' => false, 'errors' => ['_form' => 'Could not check for existing accounts. Try again.']];
            }
            foreach ($dup['data'] as $row) {
                if (strtolower($row['username']) === strtolower($d['username'])) $errors['username'] = 'username is already taken';
                if (strtolower($row['email']) === $d['email']) $errors['email'] = 'an account with this email already exists';
                if (strtolower($row['employee_number']) === strtolower($d['employee_number'])) $errors['employee_number'] = 'this employee number already has an account';
            }
        }
        if ($errors) {
            self::recordAttempt('register', $d['email'] ?: 'unknown', false);
            return ['ok' => false, 'errors' => $errors];
        }

        $user = Supabase::request('POST', '/auth/v1/admin/users', ['body' => [
            'email' => $d['email'], 'password' => $password, 'email_confirm' => true,
        ]]);
        $uid = $user['data']['id'] ?? null;
        if ($user['status'] === 0) {
            return ['ok' => false, 'errors' => ['_form' => 'Cannot reach the server. Try again.']];
        }
        if (!is_string($uid)) {
            self::recordAttempt('register', $d['email'], false);
            $msg = (string) $user['error'];
            return ['ok' => false, 'errors' => stripos($msg, 'already') !== false
                ? ['email' => 'an account with this email already exists']
                : ['_form' => 'Could not create the account: ' . $msg]];
        }

        $ins = Supabase::rest('POST', 'profiles', ['prefer' => 'return=minimal', 'body' => $d + ['id' => $uid]]);
        if ($ins['status'] >= 400 || $ins['status'] === 0) {
            Supabase::request('DELETE', '/auth/v1/admin/users/' . $uid); // roll back the auth user
            self::recordAttempt('register', $d['email'], false);
            return ['ok' => false, 'errors' => ['_form' => $ins['status'] === 409
                ? 'This username, email or employee number is already registered.'
                : 'Could not create the account. Try again.']];
        }

        self::recordAttempt('register', $d['email'], true);
        self::log('admin.register', $uid, ['username' => $d['username']], 'profile', $uid);
        return ['ok' => true, 'errors' => []];
    }

    // ---------- Password reset (demo flow: no OTP) ----------
    public static function findActiveProfileByEmail(string $email): ?array
    {
        $r = Supabase::rest('GET', 'profiles', ['query' => [
            'select' => 'id,email', 'email' => 'eq.' . strtolower($email), 'is_active' => 'eq.true', 'limit' => '1',
        ]]);
        return $r['data'][0] ?? null;
    }

    /** @return array{ok:bool,error:?string} */
    public static function setPassword(string $userId, string $password): array
    {
        $r = Supabase::request('PUT', '/auth/v1/admin/users/' . rawurlencode($userId), ['body' => ['password' => $password]]);
        if ($r['status'] === 200) {
            self::log('auth.password_reset', $userId);
            return ['ok' => true, 'error' => null];
        }
        return ['ok' => false, 'error' => $r['status'] === 0 ? 'Cannot reach the server. Try again.' : (string) $r['error']];
    }
}
