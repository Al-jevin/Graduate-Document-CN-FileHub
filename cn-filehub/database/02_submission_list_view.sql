-- Run once in Supabase > SQL Editor (after schema.sql).
-- A flat, read-only list used for the Submissions/Tasks tables, search and Review page.
-- Only the PHP server (service role) can read it; deleted submissions are excluded.

create or replace view public.submission_list
with (security_invoker = true) as
select s.id,
       s.tracking_id,
       s.status,
       s.remarks,
       s.created_at,
       s.reviewed_at,
       st.first_name || ' ' || st.last_name as student_name,
       st.student_number,
       st.email::text                        as student_email,
       dt.name                               as document_name,
       f.original_filename,
       f.storage_path
from public.submissions s
join public.students        st on st.id = s.student_id
join public.document_types  dt on dt.id = s.document_type_id
left join public.submission_files f on f.submission_id = s.id
where s.deleted_at is null;

revoke all on public.submission_list from anon, authenticated;
grant select on public.submission_list to service_role;
