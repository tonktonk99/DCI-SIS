# TASK 33: Final Flow Test Checklist

เอกสารนี้ใช้ตรวจระบบ DCI SIS แบบ End-to-End หลังจากทำ Core Modules, Audit, Password Upgrade, Duplicate Protection และ Print Documents แล้ว

## 0. เตรียมก่อนทดสอบ

- [ ] ใช้ database ที่ต้องการทดสอบ เช่น `dci_sis`
- [ ] วางไฟล์ล่าสุดครบทุก path
- [ ] Login ได้อย่างน้อย 4 role:
  - [ ] `admin / 1234`
  - [ ] `registrar1 / 1234`
  - [ ] `prof1 / 1234`
  - [ ] `student1 / 1234`
- [ ] มีตารางหลักครบ:
  - [ ] users
  - [ ] programs
  - [ ] courses
  - [ ] semesters
  - [ ] sections
  - [ ] section_instructors
  - [ ] section_schedules
  - [ ] students
  - [ ] staff
  - [ ] enrollments
  - [ ] exams
  - [ ] exam_scores
  - [ ] grade_items
  - [ ] grade_scores
  - [ ] final_grades
  - [ ] document_requests
  - [ ] audit_logs

## 1. Admin Flow

### 1.1 Login Admin

- [ ] เปิด `/dci-sis/login.php`
- [ ] Login ด้วย `admin / 1234`
- [ ] ระบบ redirect ไป `/dci-sis/admin/dashboard.php`
- [ ] Sidebar แสดงเมนู Admin:
  - [ ] Dashboard
  - [ ] Users
  - [ ] Roles
  - [ ] Settings
  - [ ] Audit Logs

### 1.2 Admin Users

- [ ] เปิด `/dci-sis/admin/users.php`
- [ ] สร้าง user ใหม่ได้
- [ ] สร้าง username ซ้ำไม่ได้
- [ ] เปลี่ยน role ได้
- [ ] reset password ได้
- [ ] Password Type ของ user ใหม่เป็น `hash`
- [ ] Audit logs มี:
  - [ ] `USER.CREATE`
  - [ ] `USER.ROLE_CHANGE`
  - [ ] `USER.PASSWORD_RESET`

### 1.3 Audit Logs

- [ ] เปิด `/dci-sis/admin/audit-logs.php`
- [ ] เห็น `AUTH.LOGIN_SUCCESS`
- [ ] Filter action ได้
- [ ] Filter user ได้
- [ ] Top Actions แสดงข้อมูล

## 2. Registrar Academic Setup Flow

### 2.1 Academic Years / Semesters

- [ ] เปิด `/dci-sis/registrar/academic-years.php`
- [ ] สร้างปีการศึกษาได้
- [ ] เปิด `/dci-sis/registrar/semesters.php`
- [ ] สร้างภาคเรียนได้
- [ ] ตั้งภาคเรียนปัจจุบันได้ หรือมี column `is_current`

### 2.2 Programs

- [ ] เปิด `/dci-sis/registrar/programs.php`
- [ ] เพิ่มหลักสูตรได้
- [ ] เพิ่ม `program_code` ซ้ำแล้วขึ้นข้อความเตือน ไม่ใช่ SQL error
- [ ] Toggle active/inactive ได้

### 2.3 Courses

- [ ] เปิด `/dci-sis/registrar/courses.php`
- [ ] เพิ่มรายวิชาได้
- [ ] เพิ่ม `course_code` ซ้ำแล้วขึ้นข้อความเตือน ไม่ใช่ SQL error
- [ ] Toggle active/inactive ได้

### 2.4 Professors

- [ ] เปิด `/dci-sis/registrar/professors.php`
- [ ] เพิ่มอาจารย์ได้
- [ ] ผูก user role professor ได้
- [ ] เพิ่ม `staff_code` ซ้ำแล้วขึ้นข้อความเตือน ไม่ใช่ SQL error

### 2.5 Students

- [ ] เปิด `/dci-sis/registrar/students.php`
- [ ] เพิ่มนักศึกษาได้
- [ ] ผูก user role student ได้
- [ ] เพิ่ม `student_code` ซ้ำแล้วขึ้นข้อความเตือน ไม่ใช่ SQL error

### 2.6 Sections

- [ ] เปิด `/dci-sis/registrar/sections.php`
- [ ] เลือก semester ได้
- [ ] เลือก course ได้
- [ ] เพิ่ม section ได้
- [ ] ผูก professor ได้
- [ ] เพิ่ม schedule ได้
- [ ] เพิ่ม section ซ้ำใน semester/course เดิมแล้วขึ้นข้อความเตือน

## 3. Student Enrollment Flow

### 3.1 Login Student

- [ ] Login ด้วย `student1 / 1234`
- [ ] ระบบ redirect ไป `/dci-sis/student/dashboard.php`
- [ ] Dashboard แสดงข้อมูลจริง:
  - [ ] รายวิชาที่ลงทะเบียน
  - [ ] หน่วยกิต
  - [ ] GPA
  - [ ] ตารางเรียนวันนี้
  - [ ] สอบที่กำลังจะมาถึง

### 3.2 Enrollment

- [ ] เปิด `/dci-sis/student/enrollment.php`
- [ ] เห็น section ที่เปิดอยู่
- [ ] กด Enroll ได้
- [ ] Enroll ซ้ำไม่ได้
- [ ] จำนวน enrolled_count เพิ่มถูกต้อง

### 3.3 My Courses / Schedule

- [ ] เปิด `/dci-sis/student/courses.php`
- [ ] เห็นรายวิชาที่ลงทะเบียน
- [ ] เปิด `/dci-sis/student/schedule.php`
- [ ] เห็นตารางเรียนตาม section_schedules

## 4. Exam Flow

### 4.1 Registrar Create Exam

- [ ] Login registrar
- [ ] เปิด `/dci-sis/registrar/exams.php`
- [ ] สร้าง exam ได้
- [ ] ตั้ง status เป็น `published`

### 4.2 Student See Exam

- [ ] Login student
- [ ] เปิด `/dci-sis/student/exams.php`
- [ ] เห็น exam ที่ published ของ section ที่ตัวเอง enroll

### 4.3 Professor Enter Exam Scores

- [ ] Login professor
- [ ] เปิด `/dci-sis/professor/exams.php`
- [ ] เห็น exam ของ section ที่ตัวเองสอน
- [ ] กรอกคะแนนสอบได้
- [ ] Student กลับไปดู `/student/exams.php` แล้วเห็นคะแนน

## 5. Gradebook / Final Grade Flow

### 5.1 Professor Gradebook

- [ ] เปิด `/dci-sis/professor/gradebook.php`
- [ ] เลือก section ได้
- [ ] เพิ่ม grade items ได้ เช่น Attendance, Midterm, Final
- [ ] กรอกคะแนนได้
- [ ] คะแนนรวมคำนวณถูกต้องตาม weight
- [ ] Letter grade แสดงถูกต้อง
- [ ] Submit Final Grades ได้

### 5.2 Registrar Grade Review

- [ ] Login registrar
- [ ] เปิด `/dci-sis/registrar/grades.php`
- [ ] เห็นเกรด status `submitted`
- [ ] กด Release ได้
- [ ] Audit logs มี `GRADE.RELEASE`
- [ ] กด Return ได้ถ้าต้องส่งกลับ
- [ ] กด Lock ได้หลัง released

### 5.3 Student Grades / Transcript

- [ ] Login student
- [ ] เปิด `/dci-sis/student/grades.php`
- [ ] เห็นเฉพาะเกรด released/locked
- [ ] GPA คำนวณจากรายการที่ประกาศ
- [ ] เปิด `/dci-sis/student/transcript.php`
- [ ] Transcript แยกตามภาคเรียน
- [ ] เปิด `/dci-sis/student/transcript-print.php`
- [ ] Print หรือ Save as PDF ได้

## 6. Document Request Flow

### 6.1 Student Request

- [ ] Login student
- [ ] เปิด `/dci-sis/student/requests.php`
- [ ] ส่งคำขอ Transcript ได้
- [ ] ส่งคำขอ Certificate ได้
- [ ] เห็นสถานะคำขอของตัวเอง

### 6.2 Registrar Process Request

- [ ] Login registrar
- [ ] เปิด `/dci-sis/registrar/document-requests.php`
- [ ] เห็นคำขอเอกสาร
- [ ] กด Process ได้
- [ ] กด Complete ได้
- [ ] กด Reject ได้
- [ ] Audit logs มี:
  - [ ] `DOCUMENT.PROCESS`
  - [ ] `DOCUMENT.COMPLETE`
  - [ ] `DOCUMENT.REJECT`

### 6.3 Registrar Print Certificate

- [ ] เปิด `/dci-sis/registrar/transcripts.php`
- [ ] กด Certificate ได้
- [ ] เปิด `/dci-sis/registrar/certificate-print.php?student_id=...`
- [ ] Print หรือ Save as PDF ได้

## 7. Alumni Flow

- [ ] สร้าง user `alumni1` role `alumni`
- [ ] Login alumni ได้
- [ ] เปิด `/dci-sis/alumni/dashboard.php`
- [ ] เปิด `/dci-sis/alumni/profile.php`
- [ ] ส่ง transcript request ได้
- [ ] ส่ง certificate request ได้
- [ ] Registrar เห็น request ใน `/registrar/document-requests.php`

## 8. Security Smoke Test

- [ ] Student เปิด `/registrar/dashboard.php` แล้วถูกปฏิเสธ
- [ ] Professor เปิด `/admin/users.php` แล้วถูกปฏิเสธ
- [ ] Registrar เปิด `/admin/audit-logs.php` แล้วถูกปฏิเสธ
- [ ] Logout แล้วเปิด dashboard เดิมไม่ได้
- [ ] Login สำเร็จมี audit log
- [ ] Login ผิดมี audit log

## 9. Common Errors To Watch

### HTML Entity Problem

ถ้าในไฟล์มีแบบนี้ ถือว่าผิด:

```text
&lt;?php
-&gt;
&amp;&amp;
```

ต้องเป็น:

```php
<?php
->
&&
```

### Missing Column

ถ้า error `Unknown column is_current` ให้รัน:

```sql
ALTER TABLE semesters
ADD COLUMN is_current TINYINT(1) DEFAULT 0;
```

### Unique Index Error

ถ้าเจอ `Duplicate key name` แปลว่า index นั้นมีอยู่แล้ว ข้ามได้

ถ้าเจอ `Duplicate entry` แปลว่ามีข้อมูลซ้ำ ต้องแก้ข้อมูลก่อน

## 10. Pass Criteria

ระบบถือว่า MVP ผ่านเมื่อ flow นี้ผ่านครบ:

- [ ] Admin สร้าง user ได้
- [ ] Registrar สร้างหลักสูตร/วิชา/section/student/professor ได้
- [ ] Student enroll ได้
- [ ] Registrar สร้าง exam ได้
- [ ] Professor กรอกคะแนนสอบได้
- [ ] Professor submit final grades ได้
- [ ] Registrar release grade ได้
- [ ] Student เห็น grades/transcript ได้
- [ ] Student ขอเอกสารได้
- [ ] Registrar process document request ได้
- [ ] Admin ตรวจ audit logs ได้
