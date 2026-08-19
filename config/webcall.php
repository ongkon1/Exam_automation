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

    // Subjects a student may choose from before starting a call.
    'subjects' => [
        'Mathematics',
        'English',
        'Physics',
        'Chemistry',
        'Biology',
        'General Knowledge',
    ],

];
