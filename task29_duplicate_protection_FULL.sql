-- TASK 29: Duplicate Protection / Unique Indexes - FULL VERSION
-- Database: dci_sis
-- วิธีใช้แบบปลอดภัย:
-- 1) รันชุด PRE-CHECK ก่อน
-- 2) ถ้า query ใดมีผลลัพธ์ แปลว่ามีข้อมูลซ้ำ ต้องแก้ข้อมูลก่อน
-- 3) ถ้าทุก query ไม่คืนค่า rows ค่อยรันชุด APPLY UNIQUE INDEXES

-- =========================================================
-- PART A: PRE-CHECK DUPLICATES
-- =========================================================

-- 1) users.username
SELECT username, COUNT(*) AS total
FROM users
GROUP BY username
HAVING COUNT(*) > 1;

-- 2) students.student_code
SELECT student_code, COUNT(*) AS total
FROM students
GROUP BY student_code
HAVING COUNT(*) > 1;

-- 3) staff.staff_code
SELECT staff_code, COUNT(*) AS total
FROM staff
GROUP BY staff_code
HAVING COUNT(*) > 1;

-- 4) programs.program_code
SELECT program_code, COUNT(*) AS total
FROM programs
GROUP BY program_code
HAVING COUNT(*) > 1;

-- 5) courses.course_code
SELECT course_code, COUNT(*) AS total
FROM courses
GROUP BY course_code
HAVING COUNT(*) > 1;

-- 6) semesters.semester_name
SELECT semester_name, COUNT(*) AS total
FROM semesters
GROUP BY semester_name
HAVING COUNT(*) > 1;

-- 7) sections: semester_id + course_id + section_number
SELECT semester_id, course_id, section_number, COUNT(*) AS total
FROM sections
GROUP BY semester_id, course_id, section_number
HAVING COUNT(*) > 1;

-- 8) enrollments: student_id + section_id
SELECT student_id, section_id, COUNT(*) AS total
FROM enrollments
GROUP BY student_id, section_id
HAVING COUNT(*) > 1;

-- 9) exam_scores: exam_id + student_id
SELECT exam_id, student_id, COUNT(*) AS total
FROM exam_scores
GROUP BY exam_id, student_id
HAVING COUNT(*) > 1;

-- 10) grade_scores: grade_item_id + student_id
SELECT grade_item_id, student_id, COUNT(*) AS total
FROM grade_scores
GROUP BY grade_item_id, student_id
HAVING COUNT(*) > 1;

-- 11) final_grades: enrollment_id
SELECT enrollment_id, COUNT(*) AS total
FROM final_grades
GROUP BY enrollment_id
HAVING COUNT(*) > 1;


-- =========================================================
-- PART B: APPLY UNIQUE INDEXES
-- รันส่วนนี้เฉพาะเมื่อ PART A ไม่พบข้อมูลซ้ำ
-- ถ้า error "Duplicate key name" แปลว่า index นั้นถูกสร้างไว้แล้ว
-- =========================================================

ALTER TABLE users
ADD UNIQUE KEY uq_users_username (username);

ALTER TABLE students
ADD UNIQUE KEY uq_students_student_code (student_code);

ALTER TABLE staff
ADD UNIQUE KEY uq_staff_staff_code (staff_code);

ALTER TABLE programs
ADD UNIQUE KEY uq_programs_program_code (program_code);

ALTER TABLE courses
ADD UNIQUE KEY uq_courses_course_code (course_code);

ALTER TABLE semesters
ADD UNIQUE KEY uq_semesters_semester_name (semester_name);

ALTER TABLE sections
ADD UNIQUE KEY uq_sections_semester_course_section (semester_id, course_id, section_number);

ALTER TABLE enrollments
ADD UNIQUE KEY uq_enrollments_student_section (student_id, section_id);

ALTER TABLE exam_scores
ADD UNIQUE KEY uq_exam_scores_exam_student (exam_id, student_id);

ALTER TABLE grade_scores
ADD UNIQUE KEY uq_grade_scores_item_student (grade_item_id, student_id);

ALTER TABLE final_grades
ADD UNIQUE KEY uq_final_grades_enrollment (enrollment_id);
