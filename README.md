# CN FileHub

A document submission and tracking system for the College of Nursing Graduate Program. Administrators register students, upload thesis and research PDFs on their behalf, track each submission, and review or reject it. Files are stored privately and are only opened through short-lived signed links.

**Stack:** PHP 8.2+ (no framework, no Composer packages) · Supabase (Auth, PostgreSQL, private Storage) · vanilla JS and CSS

## Features

- Admin login with a username and password, lockout after repeated failed attempts, CSRF protection, and session timeout
- Admin registration and password reset (demo mode available)
- Student records and document types
- PDF upload (max 10 MB by default) with a tracking ID per submission
- Review workflow with status history and remarks
- Dashboard, searchable submissions table, and an activity log
- Soft delete: deleted submissions are hidden but kept in the system

## Project structure

```
cn-filehub/
├── database/
│   ├── schema.sql                  # Tables, RLS policies, functions, storage bucket (run first)
│   └── 02_submission_list_view.sql # Read-only view used by the tables (run second)
├── includes/                       # Shared layout and UI components
├── public/                         # Web root: only this folder should be served
│   ├── api/                        # JSON endpoints used by the JS
│   └── assets/                     # CSS, JS, images (seals)
├── src/                            # Server code: Auth, Supabase client, Storage, Csrf, Session...
├── .env.example                    # Template for your local settings
└── SETUP.md                        # Short checklist (Laragon)
```

## Requirements

- PHP 8.2 or newer with these extensions enabled: `curl`, `fileinfo`, `openssl`, `mbstring`, `json`
- A free [Supabase](https://supabase.com) account
- Git (only if you are cloning from GitHub)

You can use Laragon, XAMPP, or just PHP's built-in server. No Apache is needed.

## Setup

### 1. Get the code

```bash
git clone <your-repo-url> cn-filehub
cd cn-filehub
```

Or unzip the zip you were given.

### 2. Create your own Supabase project

1. Go to Supabase and create a new project. Save the database password somewhere safe.
2. Open **Project Settings > API Keys** and copy:
   - the **Project URL** (for example `https://xxxx.supabase.co`)
   - the **Publishable (anon) key**, which starts with `sb_publishable_`
   - the **Secret (service-role) key**, which starts with `sb_secret_`
3. Open **Authentication > Sign In / Providers > Email** and turn **off** "Confirm email".

### 3. Install the database

In **Supabase > SQL Editor > New query**, run these two files **in order**, one at a time:

1. `database/schema.sql`
2. `database/02_submission_list_view.sql`

`schema.sql` also creates the private `student-documents` storage bucket (PDF only, 10 MB).

### 4. Configure `.env`

```bash
cp .env.example .env        # Windows: copy .env.example .env
```

Open `.env` and fill in:

| Variable | What to put |
|---|---|
| `APP_ENV` | `local` on your laptop, `production` on a real server |
| `APP_URL` | The URL you open the app at (see step 5) |
| `SUPABASE_URL` | Your project URL, with no `/rest/v1/` at the end |
| `SUPABASE_ANON_KEY` | Publishable key |
| `SUPABASE_SERVICE_ROLE_KEY` | Secret key. **Server only, never share or commit it** |
| `STORAGE_BUCKET` | `student-documents` |
| `MAX_UPLOAD_MB` | `10` |
| `SESSION_LIFETIME_MINUTES` | `60` |
| `DEMO_PASSWORD_RESET` | `true` skips the OTP on Forgot Password. Set `false` for real use |
| `LOGIN_MAX_ATTEMPTS` / `LOGIN_LOCKOUT_MINUTES` | `5` / `15` |
| `CURL_CA_BUNDLE` | Windows only: full path to `cacert.pem` if you get an SSL error |

> Windows SSL error? Download `cacert.pem` from curl.se/docs/caextract.html, save it somewhere, and set its full path in `CURL_CA_BUNDLE` (use forward slashes).

### 5. Run it

**Option A: PHP built-in server (simplest)**

```bash
php -S localhost:8000 -t public
```

Set `APP_URL=http://localhost:8000` in `.env`, then open http://localhost:8000.

**Option B: Laragon / XAMPP**

Copy the folder to `C:\laragon\www\cn-filehub` (or `htdocs`), start Apache, and set
`APP_URL=http://localhost/cn-filehub/public`.

### 6. Verify

With `APP_ENV=local`, open `/setup-check.php`. Every line should say **Pass**. This page returns 404 unless `APP_ENV=local`.

### 7. Create the first admin

Open `/register.php`, create an account, then log in.

## Security notes

- **Never commit `.env`.** It is already in `.gitignore`. Double-check with `git status` before every commit.
- The **service-role key bypasses all database security rules.** It is only used by the PHP server and must never reach the browser or a repository.
- If a secret key is ever shared or pushed by accident, rotate it in Supabase (**Project Settings > API Keys**) and update `.env`.
- Registration is open: anyone who can reach `/register.php` can create an admin account. Before putting this on a shared network or the internet, restrict or remove that page.
- Set `APP_ENV=production` and `DEMO_PASSWORD_RESET=false` outside of local testing, and serve the site over HTTPS.
- Only the `public/` folder should be web-accessible. Do not point your web server at the project root.

## Troubleshooting

| Problem | Fix |
|---|---|
| "Cannot reach Supabase Auth" / SSL certificate error | Set `CURL_CA_BUNDLE` to your `cacert.pem` path |
| "Service-role key rejected" | Re-copy the Secret key into `.env`, with no quotes or spaces |
| "Database schema not found" | Run `schema.sql` in the Supabase SQL Editor |
| Submissions table errors or is empty | Run `02_submission_list_view.sql` |
| New admin cannot log in right away | Turn off "Confirm email" in Supabase Auth settings |
| Blank page or missing extension error | Enable `curl`, `fileinfo`, `openssl`, `mbstring` in `php.ini` |
| Upload fails | Check the file is a PDF under `MAX_UPLOAD_MB`, and that `php.ini` `upload_max_filesize` and `post_max_size` are large enough |
