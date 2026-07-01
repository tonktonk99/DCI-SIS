<?php
/**
 * IdentityRepository — read-only resolver for the identity model.
 *
 * Provides a single entry point for pages that need to display
 * student identity data (name, program, student code).
 *
 * Resolution order for every method:
 *   1. Identity model: users.person_id → persons → student_programs → programs
 *      (joins back to students via identity_links for legacy columns like GPA)
 *   2. Legacy fallback: students JOIN programs WHERE students.user_id = ?
 *      (used when users.person_id is NULL — not yet migrated)
 *
 * The returned array shape is identical to the legacy JOIN query so existing
 * pages need only a one-line change to their $student assignment.
 */

declare(strict_types=1);

class IdentityRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Resolve student display data for any student page.
     *
     * Returns an array compatible with:
     *   SELECT students.*, programs.program_code, programs.program_name_th,
     *          programs.program_name_en
     *   FROM students LEFT JOIN programs ON programs.id = students.program_id
     *   WHERE students.user_id = ?
     *
     * Keys guaranteed: id, user_id, student_code, program_id, first_name,
     *   last_name, admission_year, study_status, cumulative_gpa,
     *   total_credits_earned, year_level, program_code, program_name_th,
     *   program_name_en
     *
     * Returns null when neither the identity model nor legacy table
     * has a record for this user.
     */
    public function resolveStudentForUser(int $userId): ?array
    {
        $row = $this->fromIdentityModel($userId);
        if ($row !== null) {
            return $row;
        }
        return $this->fromLegacyStudents($userId);
    }

    /**
     * Return students.id for a user — fastest path for pages that only
     * need $studentId and do not display identity fields.
     */
    public function getStudentId(int $userId): int
    {
        $student = $this->resolveStudentForUser($userId);
        return (int)($student['id'] ?? 0);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Identity model path.
     *
     * Joins: users.person_id → persons → student_programs (is_primary=1)
     *        → programs, identity_links → students (for GPA / legacy cols)
     *
     * COALESCE prefers the legacy students column when a linked row exists,
     * falling back to the student_programs equivalent.
     */
    private function fromIdentityModel(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                s.id,
                s.user_id,
                COALESCE(s.student_code,          sp.student_no)          AS student_code,
                sp.program_id,
                p.first_name,
                p.last_name,
                COALESCE(s.admission_year,        sp.admit_year)          AS admission_year,
                COALESCE(s.study_status,          sp.academic_status)     AS study_status,
                COALESCE(s.cumulative_gpa,        0.00)                   AS cumulative_gpa,
                COALESCE(s.total_credits_earned,  0)                      AS total_credits_earned,
                s.year_level,
                s.created_at,
                prog.program_code,
                prog.program_name_th,
                prog.program_name_en
            FROM users u
            JOIN  persons          p    ON  p.id  = u.person_id
            JOIN  student_programs sp   ON  sp.person_id = p.id
                                       AND sp.is_primary  = 1
            LEFT JOIN identity_links il ON  il.person_id   = p.id
                                       AND il.source_table = 'students'
            LEFT JOIN students     s    ON  s.id = il.source_id
            LEFT JOIN programs     prog ON  prog.id = sp.program_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Legacy fallback — mirrors the original query used in student pages
     * before the identity model was introduced.
     */
    private function fromLegacyStudents(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                students.*,
                programs.program_code,
                programs.program_name_th,
                programs.program_name_en
            FROM students
            LEFT JOIN programs ON programs.id = students.program_id
            WHERE students.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
