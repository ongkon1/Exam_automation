<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\Student\CallSessionController;
use App\Http\Controllers\Student\ResultController as StudentResultController;
use App\Http\Controllers\Student\VoiceExamController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\EvaluationController;
use App\Http\Controllers\Teacher\ResultController;
use App\Http\Controllers\Teacher\SettingsController;
use App\Http\Controllers\Teacher\StudentController;
use App\Http\Controllers\Teacher\TranscriptController;
use App\Http\Controllers\Teacher\WebhookLogController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    /** @var \App\Models\User|null $user */
    $user = Auth::user();

    return $user
        ? redirect()->route($user->homeRoute())
        : redirect()->route('login');
});

// Public marketing page for the voice assistant — no login required.
Route::view('powerinai-demo', 'powerinai-demo')->name('powerinai-demo');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::post('logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:teacher'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {
        Route::get('dashboard', TeacherDashboardController::class)->name('dashboard');
        Route::resource('students', StudentController::class);
        Route::resource('results', ResultController::class)->except('show');
        Route::post('results/{result}/evaluate', EvaluationController::class)->name('results.evaluate');
        Route::get('transcripts', [TranscriptController::class, 'index'])->name('transcripts.index');
        Route::get('transcripts/{transcript}', [TranscriptController::class, 'show'])->name('transcripts.show');
        Route::post('transcripts/{transcript}/retry', [TranscriptController::class, 'retry'])
            ->name('transcripts.retry');
        Route::get('webhooks', [WebhookLogController::class, 'index'])->name('webhooks.index');
        Route::get('webhooks/{webhook}', [WebhookLogController::class, 'show'])->name('webhooks.show');
        Route::delete('webhooks', [WebhookLogController::class, 'destroy'])->name('webhooks.destroy');
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });

Route::middleware(['auth', 'role:student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('results', [StudentResultController::class, 'index'])->name('results.index');
        Route::get('results/{result}', [StudentResultController::class, 'show'])->name('results.show');
        Route::get('voice-exam', VoiceExamController::class)->name('voice-exam');
        Route::post('voice-exam/sessions', [CallSessionController::class, 'store'])->name('voice-exam.sessions.store');
        Route::post('voice-exam/sessions/end', [CallSessionController::class, 'end'])->name('voice-exam.sessions.end');
        Route::get('profile', [ProfileController::class, 'show'])->name('profile');
        Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    });
