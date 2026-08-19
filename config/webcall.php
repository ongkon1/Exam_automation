<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Call Exam
    |--------------------------------------------------------------------------
    |
    | Settings for the student voice-exam widget and the transcript callback the
    | voice provider posts back when a call ends.
    |
    */

    // Shared secret the provider must send in the X-Webhook-Secret header.
    'webhook_secret' => env('WEBCALL_WEBHOOK_SECRET'),

    // Marks a voice exam is scored out of. The AI is asked for a score in this range.
    'full_marks' => (int) env('WEBCALL_FULL_MARKS', 100),

    // The exam_name written onto results generated from a voice exam.
    'exam_name' => env('WEBCALL_EXAM_NAME', 'Voice Exam'),

    // How far back to look for the call session a transcript belongs to. The provider
    // gives us the student's number but not the subject, so the subject comes from the
    // session the student opened when they pressed Start Exam.
    'session_window_minutes' => (int) env('WEBCALL_SESSION_WINDOW_MINUTES', 360),

    // Voice exams are recorded per student, not per subject: the student picks nothing
    // before calling, and the examiner covers whatever subjects come up. This label is
    // written to the subject column so results stay readable.
    'subject' => env('WEBCALL_SUBJECT', 'General'),

];
