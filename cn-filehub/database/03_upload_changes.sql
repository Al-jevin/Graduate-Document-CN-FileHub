-- Run once in Supabase > SQL Editor (after schema.sql and 02_submission_list_view.sql).
-- Safe to re-run. Adds: new document types, residency/semester, comprehensive exam fields.

-- 1) Document types (new order matches the approved dropdown list)
insert into public.document_types (name, sort_order) values
  ('Birth Certificate', 1),
  ('Transcript of Records', 2),
  ('Recommendation Letter', 3),
  ('PRC PNLE Certification of Board Rating', 4),
  ('Marriage Contract', 5),
  ('1.5 x 1.5 ID Picture (white background with name tag)', 6),
  ('Curriculum Vitae', 7),
  ('Student Enrollment Record', 8),
  ('Official Receipt', 9),
  ('Summary of Related Learning Experience', 10),
  ('Other Submission', 11),
  ('Comprehensive Examination', 12)
on conflict (name) do update set sort_order = excluded.sort_order, is_active = true;

-- 2) New columns on submissions
alter table public.submissions
  add column if not exists year_of_residency smallint,
  add column if not exists semester text,
  add column if not exists comprehensive_exam_date date,
  add column if not exists comprehensive_exam_takes smallint;

alter table public.submissions drop constraint if exists submissions_residency_chk;
alter table public.submissions add constraint submissions_residency_chk
  check (year_of_residency is null or year_of_residency between 1 and 5);
alter table public.submissions drop constraint if exists submissions_semester_chk;
alter table public.submissions add constraint submissions_semester_chk
  check (semester is null or semester in ('1st Semester','2nd Semester','Summer'));
alter table public.submissions drop constraint if exists submissions_takes_chk;
alter table public.submissions add constraint submissions_takes_chk
  check (comprehensive_exam_takes is null or comprehensive_exam_takes between 1 and 5);

-- 3) Replace create_submission with a version that stores the new fields
drop function if exists public.create_submission(uuid,uuid,smallint,uuid,text,text,integer,text);

create or replace function public.create_submission(
  p_id uuid, p_student_id uuid, p_document_type_id smallint, p_created_by uuid,
  p_storage_path text, p_original_filename text, p_file_size integer, p_sha256 text,
  p_year_of_residency smallint default null, p_semester text default null,
  p_exam_date date default null, p_exam_takes smallint default null
) returns public.submissions
language plpgsql security definer set search_path = public as $$
declare
  v_year integer := extract(year from (now() at time zone 'Asia/Manila'))::integer;
  v_seq  integer;
  v_type text;
  v_row  public.submissions;
begin
  select name into v_type from public.document_types where id = p_document_type_id and is_active;
  if v_type is null then raise exception 'Unknown document type' using errcode = '22023'; end if;

  if not exists (select 1 from public.students where id = p_student_id and deleted_at is null) then
    raise exception 'Student not found' using errcode = 'P0002';
  end if;

  if v_type = 'Comprehensive Examination' and (p_exam_date is null or p_exam_takes is null) then
    raise exception 'Exam date and number of takes are required' using errcode = '22023';
  end if;

  insert into public.tracking_counters (year, last_value) values (v_year, 1)
  on conflict (year) do update set last_value = public.tracking_counters.last_value + 1
  returning last_value into v_seq;

  insert into public.submissions (id, tracking_id, student_id, document_type_id, created_by,
                                  year_of_residency, semester, comprehensive_exam_date, comprehensive_exam_takes)
  values (p_id, format('CN-%s-%s-%s', v_type, v_year, lpad(v_seq::text, 4, '0')),
          p_student_id, p_document_type_id, p_created_by,
          p_year_of_residency, p_semester,
          case when v_type = 'Comprehensive Examination' then p_exam_date end,
          case when v_type = 'Comprehensive Examination' then p_exam_takes end)
  returning * into v_row;

  insert into public.submission_files (submission_id, storage_path, original_filename, file_size, sha256)
  values (v_row.id, p_storage_path, p_original_filename, p_file_size, p_sha256);

  insert into public.activity_logs (actor_id, action, entity_type, entity_id, details)
  values (p_created_by, 'submission.create', 'submission', v_row.id::text,
          jsonb_build_object('tracking_id', v_row.tracking_id));

  return v_row;
end $$;

revoke all on function public.create_submission(uuid,uuid,smallint,uuid,text,text,integer,text,smallint,text,date,smallint) from public, anon, authenticated;
grant execute on function public.create_submission(uuid,uuid,smallint,uuid,text,text,integer,text,smallint,text,date,smallint) to service_role;

-- 4) Show the new fields on the submission list / review page
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
       f.storage_path,
       s.year_of_residency,
       s.semester,
       s.comprehensive_exam_date,
       s.comprehensive_exam_takes
from public.submissions s
join public.students        st on st.id = s.student_id
join public.document_types  dt on dt.id = s.document_type_id
left join public.submission_files f on f.submission_id = s.id
where s.deleted_at is null;

revoke all on public.submission_list from anon, authenticated;
grant select on public.submission_list to service_role;
