# CN FILEHUB: local setup checklist

1. **Copy the project** to `C:\laragon\www\cn-filehub` and start Laragon (Apache + PHP 8.2 or newer).
2. **Create `.env`**: copy `.env.example` to `.env` and paste your two keys from
   Supabase > Project Settings > API Keys (anon/publishable key and service-role/secret key).
   Never commit `.env` and never share the service-role key.
3. **Turn off email confirmation**: Supabase > Authentication > Sign In / Providers > Email > turn off "Confirm email".
4. **Install the database**: Supabase > SQL Editor > New query > paste all of `database/schema.sql` > Run. Then run `02_submission_list_view.sql`, then `03_upload_changes.sql`.
5. **Add the seals** (optional until you have the files): put the two logo images in
   `public/assets/img/` as `seal-college.png` and `seal-university.png`.
6. **Verify**: open http://localhost/cn-filehub/public/setup-check.php. Every line should say Pass.

If cURL reports an SSL certificate error on Windows, download `cacert.pem` and set its full path in
`CURL_CA_BUNDLE` in `.env`.
