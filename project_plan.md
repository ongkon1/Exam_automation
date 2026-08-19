# Government Exam Monitoring — Project Plan

A role-based exam result management system built with Laravel and MySQL.

**Teacher** gets full CRUD over students and results, plus a Settings page holding two AI prompt fields used to generate evaluations via the OpenAI API.
**Student** sees their own results (read-only), maintains their own profile details, and can sit a spoken **voice exam** whose transcript the AI scores automatically.

---

## 0. Voice exam (web call) feature

A student opens **Voice Exam**, confirms their phone number, picks a subject, and starts a call. The voice
widget itself is supplied later; everything around it is built.

When the call ends the provider POSTs the transcript back:

```
POST /api/webhooks/webcall/transcript
X-Webhook-Secret: <WEBCALL_WEBHOOK_SECRET>
Content-Type: application/json

{
  "order_id": null,
  "call_id": "85a764409b8911f1be2d7e0a46f1ba4d",
  "port": "770111",
  "carrier": "0",
  "result": "confirmed",
  "summary": "### Call Summary\n\n...",
  "transcript": "assistant: ...\nuser: ..."
}
```

**The payload carries no phone number and no subject**, so the `call_id` registered when the student
started the exam is what ties the transcript back to them. `summary` and `result` are stored on the
transcript (`summary`, `call_result`) and the summary is passed to the AI alongside the transcript.

Only `transcript` is always required. `phone` and `subject` are still accepted — and become required —
when a callback arrives with no `call_id`. `phone_number`, `transcript_text` and `id` work as aliases for
`phone`, `transcript` and `call_id`.

**Matching a transcript to a student.** The browser never learns Speaklar's call id, so matching happens
in two steps:

Voice exams are recorded **per student, not per subject** — the student picks nothing before calling and the
examiner asks in-call which subjects they are sitting. Results are written with the `WEBCALL_SUBJECT` label
(default `General`).

1. **At call start** — `public/asset/js/voice-exam-embed.js` hooks `SIP.UA.prototype.invite` (Speaklar dials
   over SIP.js, exposed as `window.SIP`) and POSTs to `student/voice-exam/sessions`. The server opens a
   `call_sessions` row recording the student, their phone number and the time. This is an audit trail of
   attempts; matching does not depend on it.
2. **On the callback** — the payload carries only a call id, so the job calls Speaklar's status endpoint:

   ```
   GET https://app.speaklar.com/api/ai-bulk-calls/status?call_id=<id>
   Authorization: Bearer <SPEAKLAR_API_TOKEN>
   ```

   `calls[0].cdr.dst` is the number the student called from — **not** `src` / `port` / `extension`, which are
   all the internal channel (e.g. `770115`). That number alone identifies the student. Their most recent
   unclaimed session inside `WEBCALL_SESSION_WINDOW_MINUTES` (default 6 hours) is stamped `matched_at` so one
   call is not counted against two sessions.

If a call id *is* ever known up front it still short-circuits step 2, and can only be claimed once — a second
student posting the same id is rejected with 409.

Pipeline:

1. **Authenticate** — the route lives in `routes/api.php`, so it is stateless: no session, no CSRF token.
   `VerifyWebCallSecret` compares the header against `config('webcall.webhook_secret')` with `hash_equals`.
   401 on mismatch, 503 when no secret is configured.
2. **Deduplicate** — a repeated `call_id` returns `{"status":"duplicate"}` without creating a second row.
3. **Match the student** — see the two-step reconciliation above: the Speaklar status lookup gives the
   number, `App\Support\PhoneNumber::findStudent()` turns it into a student (digits-only comparison, exact
   match first, then the last 9 digits, refusing ambiguous matches), and the open `call_sessions` row gives
   the subject. An unresolvable student *or* subject stores the transcript with status `unmatched` and logs
   a warning rather than dropping it.
4. **Store** — one `exam_transcripts` row, keyed by student and subject.
5. **Evaluate** — `EvaluateExamTranscript::dispatchAfterResponse()` runs once the provider has its 202, so
   the callback never waits on OpenAI. The model is asked for JSON `{marks_obtained, feedback}` using the
   teacher's two saved prompts; the score is clamped to the marks range.
6. **Publish** — a `results` row is created (`exam_name` = "Voice Exam", grade derived by `Result::gradeFor`),
   so the voice exam appears alongside written exams for both roles. The transcript is linked to it and
   marked `evaluated`.

Failures (no API key, malformed AI response, no teacher prompts saved) mark the transcript `failed` with the
reason recorded; no partial result is written. Teachers review everything under **Voice Transcripts**.

> `dispatchAfterResponse` runs the job in the same PHP process, so no queue worker is needed. Swap it for
> `dispatch()` in `WebCallTranscriptController` to move it onto a real queue.

---

## 1. Confirmed decisions

These are settled — do not re-litigate them during implementation.

| Decision | Choice |
|---|---|
| Frontend | Blade views + Bootstrap 5 (CDN). No Vite/npm needed for CSS. |
| Auth | Hand-rolled `LoginController` using Laravel's `Auth` facade. **No public registration** — teachers create student accounts. |
| Result model | Flat `results` table. No separate `exams` table. |
| Prompt settings | Per-teacher. Each teacher owns their own `system_prompt` and `evaluation_prompt`. |
| AI evaluation | Wired up now, using the **OpenAI Chat Completions API**. |

---

## 2. Tech stack & environment

- **Laravel 13** — `composer create-project laravel/laravel .` (13.25 was current at install time)
- **PHP 8.3.14** (WAMP, already installed — Laravel 12 needs ≥ 8.2 ✓)
- **Composer 2.8.11** ✓
- **MySQL 8** (WAMP) — database name: `government_exam_monitoring`. WAMP creates new tables as MyISAM, whose 1000-byte key limit breaks utf8mb4 unique indexes, so `config/database.php` pins `'engine' => 'InnoDB'`.
- **Bootstrap 5.3** via CDN in the main layout (`<html data-bs-theme="light">`)
- **Theme** — `public/asset/css/theme.css`, adapted from powerinai.com: their pink `#ff3c7e` → violet
  `#6c63ff` accent gradient, `#18192b` text and 18px radius, on light surfaces (`#f5f6fa` page,
  `#ffffff` cards, `#e5e7eb` borders). It overrides the `--bs-*` variables plus the `bg-white` /
  `bg-light` / `table-light` utilities the views already use, so the palette is defined in one place
- **OpenAI API** called through Laravel's built-in `Http` client (no extra package)
- Serve via `php artisan serve` or WAMP at `http://localhost/government_exam_monitoring/public`

`.env` values to set:

```env
APP_NAME="Government Exam Monitoring"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=government_exam_monitoring
DB_USERNAME=root
DB_PASSWORD=

OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
```

Document `OPENAI_API_KEY` and `OPENAI_MODEL` in `.env.example` too (keys blank there).

---

## 3. Database schema

### `users` — extend the default Laravel migration

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `email` | string unique | login identifier |
| `password` | string | hashed |
| `role` | enum('teacher','student') | default `student`, **indexed** |
| `roll_number` | string nullable unique | students only |
| `class_name` | string nullable | students only |
| `phone` | string nullable | |
| `date_of_birth` | date nullable | |
| `address` | text nullable | |
| `created_by` | bigint nullable FK → users.id | which teacher created this student |
| `remember_token`, `created_at`, `updated_at` | | |

### `teacher_settings`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | bigint FK → users.id | **unique**, cascade on delete |
| `system_prompt` | text nullable | |
| `evaluation_prompt` | text nullable | |
| `created_at`, `updated_at` | | |

### `results` — flat, no exams table

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `student_id` | bigint FK → users.id | cascade on delete, **indexed** |
| `exam_name` | string | e.g. "Midterm 2026" |
| `subject` | string | |
| `exam_date` | date nullable | |
| `full_marks` | unsigned int | default 100 |
| `marks_obtained` | decimal(5,2) | |
| `grade` | string nullable | auto-derived on save |
| `remarks` | text nullable | teacher's own note |
| `ai_feedback` | text nullable | filled by the OpenAI evaluation |
| `evaluated_at` | timestamp nullable | |
| `created_by` | bigint nullable FK → users.id | teacher who entered it |
| `created_at`, `updated_at` | | |

---

## 4. Models & relationships

**`App\Models\User`**
- `$fillable`: name, email, password, role, roll_number, class_name, phone, date_of_birth, address, created_by
- `$hidden`: password, remember_token
- `$casts`: `password => 'hashed'`, `date_of_birth => 'date'`
- Relations: `results()` hasMany(Result, 'student_id'), `teacherSetting()` hasOne(TeacherSetting)
- Scopes: `scopeStudents($q)`, `scopeTeachers($q)`
- Helpers: `isTeacher(): bool`, `isStudent(): bool`

**`App\Models\TeacherSetting`**
- `$fillable`: user_id, system_prompt, evaluation_prompt
- `user()` belongsTo(User)

**`App\Models\Result`**
- `$fillable`: student_id, exam_name, subject, exam_date, full_marks, marks_obtained, grade, remarks, ai_feedback, evaluated_at, created_by
- `$casts`: `exam_date => 'date'`, `evaluated_at => 'datetime'`, `marks_obtained => 'decimal:2'`
- Relations: `student()` belongsTo(User, 'student_id'), `creator()` belongsTo(User, 'created_by')
- Accessor `getPercentageAttribute()` → `full_marks > 0 ? round($marks_obtained / $full_marks * 100, 2) : 0`
- `public static function gradeFor(float $percentage): string` — A+ ≥ 80, A ≥ 70, A- ≥ 60, B ≥ 50, C ≥ 40, otherwise F. Call this in the controller (or a saving model event) so `grade` stays consistent.

---

## 5. Auth & authorization

**`App\Http\Controllers\Auth\LoginController`**
- `showLoginForm()` → `auth.login` view
- `login(Request $r)` → validate `email|required|email`, `password|required`; `Auth::attempt($credentials, $r->boolean('remember'))`; on success `$r->session()->regenerate()` then redirect by role; on failure `back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email')`
- `logout(Request $r)` → `Auth::logout()`, invalidate session, regenerate CSRF token, redirect to `/login`

**Role redirect helper** — one place that maps role → route name:
`teacher` → `teacher.students.index`, `student` → `student.dashboard`. Used after login and by the `/` route.

**`App\Http\Middleware\EnsureRole`**
```php
public function handle(Request $request, Closure $next, string ...$roles)
{
    abort_unless($request->user() && in_array($request->user()->role, $roles, true), 403);
    return $next($request);
}
```
Register the alias in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class]);
})
```

**Ownership checks** — role middleware is not enough. Every student-facing controller action that loads a record must verify ownership:
```php
abort_unless($result->student_id === auth()->id(), 403);
```
Teacher actions on students should likewise verify the target user is actually a student (`abort_unless($student->isStudent(), 404)`) so a teacher can't edit another teacher through the students routes.

---

## 6. Routes — `routes/web.php`

```php
Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')->name('logout');

Route::get('/', fn () => redirect()->route(
    auth()->check() && auth()->user()->isTeacher()
        ? 'teacher.students.index'
        : (auth()->check() ? 'student.dashboard' : 'login')
));

Route::middleware(['auth', 'role:teacher'])
    ->prefix('teacher')->name('teacher.')->group(function () {
        Route::resource('students', StudentController::class);
        Route::resource('results',  ResultController::class);
        Route::post('results/{result}/evaluate', EvaluationController::class)
            ->name('results.evaluate');
        Route::get('settings',  [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings',  [SettingsController::class, 'update'])->name('settings.update');
    });

Route::middleware(['auth', 'role:student'])
    ->prefix('student')->name('student.')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('results',            [StudentResultController::class, 'index'])->name('results.index');
        Route::get('results/{result}',   [StudentResultController::class, 'show'])->name('results.show');
        Route::get('profile',      [ProfileController::class, 'show'])->name('profile');
        Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile',      [ProfileController::class, 'update'])->name('profile.update');
    });
```

---

## 7. Controllers & form requests

### Controllers

| Class | Responsibility |
|---|---|
| `Auth\LoginController` | login form, login, logout |
| `Teacher\StudentController` | full resource CRUD over `users` where role = student. `index` supports a `?search=` filter on name/email/roll_number and paginates 15. `show` displays the student's profile plus their results. |
| `Teacher\ResultController` | full resource CRUD over `results`. `index` supports `?student_id=` and `?subject=` filters, eager-loads `student`, paginates 15. Sets `grade` from `Result::gradeFor()` and `created_by = auth()->id()` on store/update. |
| `Teacher\SettingsController` | `edit` / `update` — teacher's own name, email, optional new password, and the two prompt fields. Uses `firstOrCreate` on `teacher_settings`. |
| `Teacher\EvaluationController` | single `__invoke(Result $result, OpenAiEvaluator $evaluator)` — runs the AI evaluation |
| `Student\DashboardController` | single-action: summary cards (total results, average percentage, best/worst subject) + 5 most recent results |
| `Student\ResultController` | `index` (own results, paginated) and `show` (own result detail, with ownership check) |
| `Student\ProfileController` | `show` / `edit` / `update` — a student maintains their own name, email, phone, date of birth, address and password. Roll number and class are excluded from `UpdateStudentProfileRequest`, so they stay teacher-controlled. |

### Form requests

| Class | Key rules |
|---|---|
| `StoreStudentRequest` | `name` required; `email` required/email/unique:users; `password` required/min:8/confirmed; `roll_number` nullable/unique:users; `date_of_birth` nullable/date; others nullable strings |
| `UpdateStudentRequest` | same, but `email` and `roll_number` use `Rule::unique('users')->ignore($this->route('student'))`; `password` is `nullable|min:8|confirmed` and only applied when filled |
| `StoreResultRequest` | `student_id` required/exists:users,id; `exam_name`, `subject` required; `full_marks` required/integer/min:1; `marks_obtained` required/numeric/min:0/`lte:full_marks`; `exam_date` nullable/date; `remarks` nullable |
| `UpdateResultRequest` | same as store |
| `UpdateTeacherSettingsRequest` | `name` required; `email` required/email/unique ignoring self; `password` nullable/min:8/confirmed; `system_prompt` nullable/string/max:5000; `evaluation_prompt` nullable/string/max:5000 |

---

## 8. Views — `resources/views`

```
layouts/app.blade.php          Bootstrap 5 CDN, role-aware sidebar + navbar, @yield('content')
partials/_flash.blade.php      success / error flash alerts
partials/_errors.blade.php     validation error list

auth/login.blade.php

teacher/students/index.blade.php    table + search box + Add/Edit/Delete buttons
teacher/students/create.blade.php
teacher/students/edit.blade.php
teacher/students/show.blade.php     profile card + that student's results table

teacher/results/index.blade.php     table + student/subject filters + "AI Evaluate" button per row
teacher/results/create.blade.php    student dropdown, exam/subject/marks fields
teacher/results/edit.blade.php

teacher/settings/edit.blade.php     profile fields + System prompt + Evaluation prompt textareas

student/dashboard.blade.php         summary cards + recent results
student/results/index.blade.php
student/results/show.blade.php      full detail incl. ai_feedback panel
student/profile.blade.php           own info card + Edit Profile button
student/profile-edit.blade.php      self-service edit form (roll number / class shown disabled)
```

Sidebar links by role:
- Teacher → Students, Results, Settings
- Student → Dashboard, My Results, My Profile

Delete buttons must be `<form method="POST">` with `@method('DELETE')` + `@csrf` and a JS confirm.

---

## 9. OpenAI evaluation feature

### Config — `config/services.php`

```php
'openai' => [
    'key'   => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
],
```

### Service — `app/Services/OpenAiEvaluator.php`

```php
public function evaluate(Result $result, TeacherSetting $settings): string
```

- Endpoint: `POST https://api.openai.com/v1/chat/completions`
- `Http::withToken(config('services.openai.key'))->timeout(60)->post(...)`
- Payload:
  - `model` → `config('services.openai.model')`
  - `messages`:
    - `{"role": "system", "content": $settings->system_prompt}`
    - `{"role": "user", "content": $settings->evaluation_prompt . "\n\n" . $this->renderResult($result)}`
  - `temperature` → 0.7
- `renderResult()` builds a plain-text block: student name, roll number, class, exam name, subject, exam date, marks obtained / full marks, percentage, grade, teacher remarks.
- Throw a `RuntimeException` with the API's error message when the response is non-2xx or the expected `choices.0.message.content` key is missing; otherwise return that content string.

### Controller — `Teacher\EvaluationController`

```php
public function __invoke(Result $result, OpenAiEvaluator $evaluator)
{
    $settings = auth()->user()->teacherSetting;

    if (! $settings || ! $settings->system_prompt || ! $settings->evaluation_prompt) {
        return back()->with('error', 'Set your System prompt and Evaluation prompt in Settings first.');
    }

    try {
        $result->update([
            'ai_feedback' => $evaluator->evaluate($result->load('student'), $settings),
            'evaluated_at' => now(),
        ]);
        return back()->with('success', 'AI evaluation generated.');
    } catch (\Throwable $e) {
        report($e);
        return back()->with('error', 'Evaluation failed: ' . $e->getMessage());
    }
}
```

### UI behaviour

- On `teacher/results/index`, each row shows an **AI Evaluate** button. If the logged-in teacher has not saved both prompts, render it `disabled` with a hint linking to Settings.
- Rows already evaluated show the `evaluated_at` timestamp and a **Re-evaluate** label.
- The generated text is stored in `results.ai_feedback` and is visible to the student on `student/results/show`.

---

## 10. Seeders — `database/seeders/DatabaseSeeder.php`

- One teacher: `teacher@example.com` / `password`, role `teacher`, with an empty `teacher_settings` row.
- 5–10 students (`student1@example.com` … / `password`) with roll numbers and class names.
- A handful of results per student across 3–4 subjects, with grades derived through `Result::gradeFor()`, so both dashboards have real data on first run.

---

## 11. Build order

1. `composer create-project laravel/laravel .` — configure `.env`, create the `government_exam_monitoring` database in phpMyAdmin.
2. Migrations (users alter, `teacher_settings`, `results`) + the three models with relationships.
3. Auth: `LoginController`, login view, `EnsureRole` middleware + alias, role-based redirect.
4. `layouts/app.blade.php` shell with Bootstrap 5 and the role-aware sidebar.
5. Teacher student CRUD (controller + form requests + 4 views).
6. Teacher result CRUD (controller + form requests + 3 views), including grade derivation.
7. Teacher settings page with the two prompt fields.
8. Student dashboard, results index/show, profile — each with ownership checks.
9. OpenAI service, evaluation controller, and the Evaluate button UI.
10. Seeders, then run the verification below.

---

## 12. Verification

### Setup
```bash
php artisan migrate:fresh --seed
php artisan serve
```
Or browse `http://localhost/government_exam_monitoring/public` under WAMP.

### Manual walkthrough
1. Log in as `teacher@example.com` / `password` → lands on the students list.
2. Create a student, edit them, confirm the change persists, delete a throwaway one.
3. Add a result for a student → confirm the grade is derived correctly from the percentage.
4. Open **Settings**, save a System prompt and an Evaluation prompt → reload and confirm both persisted.
5. Back on Results, click **AI Evaluate** → feedback text is saved and `evaluated_at` is set.
6. Log out, log in as that student → dashboard shows their own summary, results list shows only their rows, and the result detail shows the AI feedback.

### Negative checks
- Student hitting `/teacher/students` → **403**.
- Student requesting another student's result id at `/student/results/{id}` → **403**.
- Login with a wrong password → returns to the form with an error, no session created.
- Submitting `marks_obtained` greater than `full_marks` → validation error.
- Clicking Evaluate with empty prompts → friendly "set your prompts first" message, no API call.

### Automated tests (Pest — `php artisan test`)
| Test | Covers |
|---|---|
| `TeacherStudentCrudTest` | teacher can create/update/delete a student; validation rejects a duplicate email |
| `TeacherResultCrudTest` | result create/update; `marks_obtained > full_marks` is rejected; grade derivation |
| `StudentAccessTest` | student is 403'd from teacher routes and from another student's result |
| `TeacherSettingsTest` | both prompts persist to `teacher_settings` |
| `EvaluationTest` | `Http::fake()` the OpenAI endpoint → asserts `ai_feedback` and `evaluated_at` are written; a faked 500 leaves the result unchanged and flashes an error |
