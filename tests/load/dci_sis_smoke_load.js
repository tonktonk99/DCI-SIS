/**
 * tests/load/dci_sis_smoke_load.js
 *
 * DCI-SIS k6 Load Test — Read-only authenticated flows
 *
 * All scenarios are read-only (GET requests + login POST only).
 * No writes, no grade submissions, no enrollments, no approvals.
 *
 * Required env var:
 *   BASE_URL — staging server URL, no trailing slash.
 *              Never point at production without written approval.
 *              Example: https://staging.your-domain.ac.th/dci-sis
 *
 * Optional credential env vars (role flow skips to public flow if missing):
 *   ADMIN_USER, ADMIN_PASS
 *   REGISTRAR_USER, REGISTRAR_PASS
 *   PROFESSOR_USER, PROFESSOR_PASS
 *   STUDENT_USER, STUDENT_PASS
 *   ALUMNI_USER, ALUMNI_PASS
 *
 * Profile (K6_PROFILE env var, default: smoke):
 *   smoke    — 2 min, max 5 VUs   — quick sanity check
 *   baseline — 10 min, max 20 VUs — establish response time baseline
 *   staging  — 20 min, max 50 VUs — staging load simulation
 *
 * Usage:
 *   BASE_URL="https://staging.example.ac.th/dci-sis" \
 *   STUDENT_USER="student_test" STUDENT_PASS="..." \
 *   k6 run tests/load/dci_sis_smoke_load.js
 *
 *   K6_PROFILE=baseline BASE_URL="..." [creds...] \
 *   k6 run tests/load/dci_sis_smoke_load.js
 *
 *   K6_PROFILE=staging BASE_URL="..." [creds...] \
 *   k6 run --out json=results/staging_$(date +%Y%m%d_%H%M%S).json \
 *   tests/load/dci_sis_smoke_load.js
 *
 * See docs/load-test-plan.md for full documentation, monitoring checklist,
 * result interpretation, and go/no-go criteria.
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Counter } from 'k6/metrics';

// ─────────────────────────────────────────────────────────────────────────────
// Fail fast: BASE_URL is required
// ─────────────────────────────────────────────────────────────────────────────

const BASE_URL = (() => {
  const raw = (__ENV.BASE_URL || '').replace(/\/$/, '');
  if (!raw) {
    throw new Error(
      '\n[DCI-SIS k6] ERROR: BASE_URL is required.\n\n' +
      'Set BASE_URL to your staging server URL:\n' +
      '  BASE_URL="https://staging.your-domain.ac.th/dci-sis" k6 run ...\n\n' +
      'NEVER point BASE_URL at production without explicit written approval.\n'
    );
  }
  return raw;
})();

// ─────────────────────────────────────────────────────────────────────────────
// Profile: smoke | baseline | staging
// ─────────────────────────────────────────────────────────────────────────────

const PROFILE = (() => {
  const p = (__ENV.K6_PROFILE || 'smoke').toLowerCase();
  if (!['smoke', 'baseline', 'staging'].includes(p)) {
    throw new Error(
      `[DCI-SIS k6] K6_PROFILE must be: smoke | baseline | staging. Got: "${p}"`
    );
  }
  return p;
})();

// ─────────────────────────────────────────────────────────────────────────────
// Stage configuration per profile
//
//   smoke    — 2 min total, max  5 VUs — quick sanity check only
//   baseline — 10 min total, max 20 VUs — establish normal response time
//   staging  — 20 min total, max 50 VUs — staging load simulation
//              Notify team before running staging profile.
// ─────────────────────────────────────────────────────────────────────────────

const STAGES = {
  smoke: [
    { duration: '30s', target: 2 },
    { duration: '60s', target: 5 },
    { duration: '30s', target: 0 },
  ],
  baseline: [
    { duration: '1m', target: 10 },
    { duration: '6m', target: 20 },
    { duration: '2m', target: 10 },
    { duration: '1m', target: 0  },
  ],
  staging: [
    { duration: '2m',  target: 25 },
    { duration: '12m', target: 50 },
    { duration: '3m',  target: 25 },
    { duration: '3m',  target: 0  },
  ],
};

// ─────────────────────────────────────────────────────────────────────────────
// Think time (seconds between page loads) — simulates real user reading time
// ─────────────────────────────────────────────────────────────────────────────

const THINK = {
  smoke:    { min: 0.3, max: 1.0 },
  baseline: { min: 1.0, max: 3.0 },
  staging:  { min: 1.5, max: 4.0 },
};

function thinkTime(multiplier) {
  const scale = multiplier !== undefined ? multiplier : 1;
  const { min, max } = THINK[PROFILE];
  sleep((Math.random() * (max - min) + min) * scale);
}

// ─────────────────────────────────────────────────────────────────────────────
// Credentials — from environment only, never hardcoded
// ─────────────────────────────────────────────────────────────────────────────

const CREDS = {
  admin:     { user: __ENV.ADMIN_USER     || '', pass: __ENV.ADMIN_PASS     || '' },
  registrar: { user: __ENV.REGISTRAR_USER || '', pass: __ENV.REGISTRAR_PASS || '' },
  professor: { user: __ENV.PROFESSOR_USER || '', pass: __ENV.PROFESSOR_PASS || '' },
  student:   { user: __ENV.STUDENT_USER   || '', pass: __ENV.STUDENT_PASS   || '' },
  alumni:    { user: __ENV.ALUMNI_USER    || '', pass: __ENV.ALUMNI_PASS    || '' },
};

function hasCreds(role) {
  return CREDS[role].user !== '' && CREDS[role].pass !== '';
}

// Log configured/missing roles at init time
(function validateCredentials() {
  const configured = Object.keys(CREDS).filter(hasCreds);
  const missing    = Object.keys(CREDS).filter(function(r) { return !hasCreds(r); });
  if (configured.length === 0) {
    console.warn('[DCI-SIS k6] No role credentials set — only public_flow will run.');
    console.warn('[DCI-SIS k6] Set STUDENT_USER/STUDENT_PASS etc. to test authenticated flows.');
  } else {
    console.log('[DCI-SIS k6] Configured roles: ' + configured.join(', '));
  }
  if (missing.length > 0 && configured.length > 0) {
    console.warn('[DCI-SIS k6] Missing credentials (will fall back to public_flow): ' + missing.join(', '));
  }
  console.log('[DCI-SIS k6] BASE_URL: ' + BASE_URL);
  console.log('[DCI-SIS k6] Profile:  ' + PROFILE);
})();

// ─────────────────────────────────────────────────────────────────────────────
// Custom metrics
// ─────────────────────────────────────────────────────────────────────────────

const loginSuccess = new Rate('dci_login_success');
const csrfMissing  = new Counter('dci_csrf_missing');

// ─────────────────────────────────────────────────────────────────────────────
// k6 options
// ─────────────────────────────────────────────────────────────────────────────

export var options = {
  stages: STAGES[PROFILE],

  thresholds: {
    // ── Error rate ──────────────────────────────────────────────────────────
    http_req_failed: ['rate<0.01'],  // <1% HTTP-level errors (connection fail, timeout)

    // ── Check pass rate ─────────────────────────────────────────────────────
    checks: ['rate>0.95'],           // >95% of all response checks pass

    // ── Login success ───────────────────────────────────────────────────────
    dci_login_success: ['rate>0.95'],

    // ── Response time by page type (tagged in each http.get / http.post) ────
    // Conservative staging thresholds — adjust after baseline run.
    // p(99) is tracked as a metric but has no hard threshold yet (trend analysis only).
    'http_req_duration{type:public}':   ['p(95)<1000'],  // login page, unauthenticated
    'http_req_duration{type:auth}':     ['p(95)<2000'],  // login POST action
    'http_req_duration{type:fast}':     ['p(95)<1000'],  // dashboards, simple listings
    'http_req_duration{type:standard}': ['p(95)<2000'],  // normal pages (courses, schedule, etc.)
    'http_req_duration{type:heavy}':    ['p(95)<2000'],  // enrollment, gradebook, transcript, audit-logs
  },
};

// ─────────────────────────────────────────────────────────────────────────────
// CSRF token extraction
//
// login.php renders: <input type="hidden" name="_csrf" value="64hexchars">
// Token: bin2hex(random_bytes(32)) = 64 lowercase hex chars
// ─────────────────────────────────────────────────────────────────────────────

function extractCsrf(body) {
  // Primary: name before value (matches order from includes/csrf.php csrf_field())
  var m = body.match(/name="_csrf"\s+value="([a-f0-9]{64})"/);
  if (m) return m[1];

  // Fallback: value before name (attribute order safety)
  m = body.match(/value="([a-f0-9]{64})"\s+[^>]*name="_csrf"/);
  if (m) return m[1];

  // Loose fallback: any non-empty value after name="_csrf"
  m = body.match(/name="_csrf"[^>]*value="([^"]{32,})"/);
  if (m) return m[1];

  return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// login(role)
//
// Flow:
//   1. GET /login.php
//      - If session still valid → already redirected to dashboard → skip login POST
//      - If not logged in → HTML with CSRF token in hidden input
//   2. Extract _csrf token from HTML
//   3. POST /actions/login-action.php with username + password + _csrf
//      - Server: verify_csrf() → password_verify() → session_regenerate_id()
//      - 302 redirect to role dashboard → k6 follows → 200 on dashboard
//   4. Check final response: status 200, URL not /login.php, no login form in body
//
// Returns true on success, false on failure (caller skips remaining page GETs).
// ─────────────────────────────────────────────────────────────────────────────

function login(role) {
  var creds = CREDS[role];

  // GET login.php — k6 follows redirects by default (up to 10)
  var loginPage = http.get(BASE_URL + '/login.php', {
    tags: { type: 'public', page: 'login_page', role: role },
  });

  // Already logged in: login.php redirected (302 → dashboard), so final URL ≠ /login.php
  if (loginPage.status === 200 && loginPage.url.indexOf('/login.php') === -1) {
    check(loginPage, {
      'session reuse: on dashboard (200)': function(r) { return r.status === 200; },
    });
    loginSuccess.add(1);
    return true;
  }

  // Login form should be visible — verify and extract CSRF
  var pageOk = check(loginPage, {
    'login page: status 200':             function(r) { return r.status === 200; },
    'login page: _csrf field present':    function(r) { return r.body.indexOf('name="_csrf"') !== -1; },
    'login page: username field present': function(r) { return r.body.indexOf('name="username"') !== -1; },
  });

  if (!pageOk || loginPage.status !== 200) {
    loginSuccess.add(0);
    return false;
  }

  var csrfToken = extractCsrf(loginPage.body);
  if (!csrfToken) {
    csrfMissing.add(1);
    console.error('[DCI-SIS k6] VU' + __VU + ' iter' + __ITER + ': CSRF token not found in login page. role=' + role + ' url=' + loginPage.url);
    loginSuccess.add(0);
    return false;
  }

  // Brief pause between page load and form submit (realistic user behaviour)
  sleep(0.3);

  // POST login — k6 automatically follows 302 redirect to dashboard
  // Server sets dci_sess cookie in 302 response; k6 stores it in per-VU cookie jar
  var loginRes = http.post(
    BASE_URL + '/actions/login-action.php',
    {
      username: creds.user,
      password: creds.pass,
      _csrf:    csrfToken,
    },
    { tags: { type: 'auth', page: 'login_post', role: role } }
  );

  // Final response (after redirect): should be dashboard page, not login page
  var loginOk = check(loginRes, {
    'login POST: status 200':                function(r) { return r.status === 200; },
    'login POST: landed on dashboard':       function(r) { return r.url.indexOf('/login.php') === -1; },
    'login POST: no error query param':      function(r) { return r.url.indexOf('error=') === -1; },
    'login POST: no login form in response': function(r) { return r.body.indexOf('name="password"') === -1; },
  });

  loginSuccess.add(loginOk ? 1 : 0);

  if (!loginOk) {
    console.warn('[DCI-SIS k6] VU' + __VU + ' iter' + __ITER + ': login failed. role=' + role + ' final_url=' + loginRes.url);
  }

  return loginOk;
}

// ─────────────────────────────────────────────────────────────────────────────
// getPage(path, label, type, role)
//
// GET a single page with standard checks:
//   - HTTP status 200
//   - Final URL not /login.php (session still valid)
//   - No PHP fatal/parse error in response body
// ─────────────────────────────────────────────────────────────────────────────

function getPage(path, label, type, role) {
  var res = http.get(BASE_URL + path, {
    tags: { type: type, page: label, role: role },
  });

  check(res, {
    [label + ': status 200']:        function(r) { return r.status === 200; },
    [label + ': not login redirect']: function(r) { return r.url.indexOf('/login.php') === -1; },
    [label + ': no PHP fatal error']: function(r) {
      return r.body.indexOf('Fatal error') === -1 && r.body.indexOf('Parse error') === -1;
    },
  });

  return res;
}

// ─────────────────────────────────────────────────────────────────────────────
// publicFlow — public pages (no login required)
// Tests login page availability and basic HTTP stack.
// ─────────────────────────────────────────────────────────────────────────────

function publicFlow() {
  group('public_flow', function() {
    var res = http.get(BASE_URL + '/login.php', {
      tags: { type: 'public', page: 'login_page', role: 'public' },
    });
    check(res, {
      'public: login page status 200':   function(r) { return r.status === 200; },
      'public: _csrf field in HTML':     function(r) { return r.body.indexOf('name="_csrf"') !== -1; },
      'public: security header present': function(r) {
        return (r.headers['X-Frame-Options'] || r.headers['x-frame-options'] || '') !== '';
      },
    });
    thinkTime();
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// studentFlow — hot paths: enrollment, grades, transcript
// ─────────────────────────────────────────────────────────────────────────────

function studentFlow() {
  if (!hasCreds('student')) { publicFlow(); return; }

  group('student_flow', function() {
    if (!login('student')) return;
    thinkTime();

    // Dashboard
    getPage('/student/dashboard.php',  'student_dashboard',  'fast',     'student');
    thinkTime();

    // HOT PATH: Course registration / enrollment list
    getPage('/student/enrollment.php', 'student_enrollment', 'heavy',    'student');
    thinkTime(1.5);

    // Course list
    getPage('/student/courses.php',    'student_courses',    'standard', 'student');
    thinkTime();

    // Class schedule
    getPage('/student/schedule.php',   'student_schedule',   'standard', 'student');
    thinkTime();

    // HOT PATH: Grades
    getPage('/student/grades.php',     'student_grades',     'standard', 'student');
    thinkTime();

    // HOT PATH: Transcript (joins grades, courses, enrollments)
    getPage('/student/transcript.php', 'student_transcript', 'heavy',    'student');
    thinkTime(1.5);

    // Document requests (view list — no submit)
    getPage('/student/requests.php',   'student_requests',   'standard', 'student');
    thinkTime();
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// professorFlow — hot path: gradebook (joins enrollments, grade_items, scores)
// ─────────────────────────────────────────────────────────────────────────────

function professorFlow() {
  if (!hasCreds('professor')) { publicFlow(); return; }

  group('professor_flow', function() {
    if (!login('professor')) return;
    thinkTime();

    getPage('/professor/dashboard.php', 'professor_dashboard', 'fast',     'professor');
    thinkTime();

    getPage('/professor/courses.php',   'professor_courses',   'standard', 'professor');
    thinkTime();

    getPage('/professor/students.php',  'professor_students',  'standard', 'professor');
    thinkTime();

    // HOT PATH: Gradebook (complex join across multiple tables)
    getPage('/professor/gradebook.php', 'professor_gradebook', 'heavy',    'professor');
    thinkTime(2);

    getPage('/professor/exams.php',     'professor_exams',     'standard', 'professor');
    thinkTime();
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// registrarFlow — hot paths: students list (paginated), transcripts
// ─────────────────────────────────────────────────────────────────────────────

function registrarFlow() {
  if (!hasCreds('registrar')) { publicFlow(); return; }

  group('registrar_flow', function() {
    if (!login('registrar')) return;
    thinkTime();

    getPage('/registrar/dashboard.php',         'registrar_dashboard',    'fast',     'registrar');
    thinkTime();

    // HOT PATH: Student list (paginated, 50 per page)
    getPage('/registrar/students.php',          'registrar_students',     'heavy',    'registrar');
    thinkTime(1.5);

    getPage('/registrar/sections.php',          'registrar_sections',     'standard', 'registrar');
    thinkTime();

    // HOT PATH: Transcripts (search + pagination)
    getPage('/registrar/transcripts.php',       'registrar_transcripts',  'heavy',    'registrar');
    thinkTime(1.5);

    // Document request queue (view — no approve/reject)
    getPage('/registrar/document-requests.php', 'registrar_doc_requests', 'standard', 'registrar');
    thinkTime();

    getPage('/registrar/courses.php',           'registrar_courses',      'standard', 'registrar');
    thinkTime();
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// adminFlow — hot path: audit logs (large table, pagination + filters)
// ─────────────────────────────────────────────────────────────────────────────

function adminFlow() {
  if (!hasCreds('admin')) { publicFlow(); return; }

  group('admin_flow', function() {
    if (!login('admin')) return;
    thinkTime();

    getPage('/admin/dashboard.php',  'admin_dashboard',  'fast',     'admin');
    thinkTime();

    getPage('/admin/users.php',      'admin_users',      'standard', 'admin');
    thinkTime();

    // HOT PATH: Audit logs (large table, indexed by action + user + created_at)
    getPage('/admin/audit-logs.php', 'admin_audit_logs', 'heavy',    'admin');
    thinkTime(2);

    // Roles page (read-only view)
    getPage('/admin/roles.php',      'admin_roles',      'standard', 'admin');
    thinkTime();
    // NOTE: admin/settings.php excluded — may contain write forms; test separately
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// alumniFlow — GET form pages only; transcript_request.php loaded but NOT submitted
// ─────────────────────────────────────────────────────────────────────────────

function alumniFlow() {
  if (!hasCreds('alumni')) { publicFlow(); return; }

  group('alumni_flow', function() {
    if (!login('alumni')) return;
    thinkTime();

    getPage('/alumni/dashboard.php',          'alumni_dashboard',          'fast',     'alumni');
    thinkTime();

    getPage('/alumni/profile.php',            'alumni_profile',            'standard', 'alumni');
    thinkTime();

    // Load transcript request form (GET only — do NOT POST/submit)
    getPage('/alumni/transcript_request.php', 'alumni_transcript_request', 'standard', 'alumni');
    thinkTime();
    // NOTE: alumni/certificate_request.php excluded — likely a write action; test separately
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// Default function — VU role distribution
//
// 9-slot round-robin by VU number (__VU starts at 1):
//
//   Slots 1-3 (33%): student    — largest real-world population
//   Slots 4-5 (22%): public     — unauthenticated visitors / bots / crawlers
//   Slot  6   (11%): professor
//   Slot  7   (11%): registrar
//   Slot  8   (11%): admin
//   Slot  9   (11%): alumni
//
// When credentials for a role are missing, that flow silently runs publicFlow().
// ─────────────────────────────────────────────────────────────────────────────

export default function() {
  var slot = (__VU - 1) % 9;

  if      (slot <= 2) { studentFlow();   }
  else if (slot <= 4) { publicFlow();    }
  else if (slot === 5) { professorFlow(); }
  else if (slot === 6) { registrarFlow(); }
  else if (slot === 7) { adminFlow();    }
  else                 { alumniFlow();   }
}
