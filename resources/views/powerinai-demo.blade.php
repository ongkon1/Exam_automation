<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PowerinAI Voice Assistant &middot; Hire AI Employee</title>
    <meta name="description" content="PowerinAI Voice Assistant — tap to start speaking and hire your AI employee.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">
    <link href="{{ asset('asset/css/powerinai-demo.css') }}" rel="stylesheet">
</head>
<body>

{{-- Faint topographic contours behind everything --}}
<div class="pa-contours" aria-hidden="true">
    <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" fill="none">
        <g stroke="#dfe3f2" stroke-width="1" opacity=".55">
            @for ($i = 0; $i < 9; $i++)
                <path d="M-120 {{ 470 + $i * 34 }} C 180 {{ 330 + $i * 30 }}, 330 {{ 690 + $i * 26 }}, 640 {{ 900 + $i * 20 }}"/>
                <path d="M1560 {{ 190 + $i * 36 }} C 1280 {{ 300 + $i * 30 }}, 1200 {{ 560 + $i * 26 }}, 1330 {{ 900 + $i * 22 }}"/>
            @endfor
        </g>
    </svg>
</div>

<main class="pa-page">

    {{-- Identity lockup --}}
    <header class="pa-logo">
        <img class="pa-logo-img" src="{{ asset('asset/img/powerinai-logo.jpg') }}"
             width="1280" height="318" alt="PowerinAI.com — Hire AI Employee">
    </header>

    {{-- Assistant --}}
    <section class="pa-card" id="pa-card" aria-label="PowerinAI Voice Assistant">
        <header class="pa-card-head">
            <span class="pa-avatar">
                <img src="{{ asset('asset/img/powerinai-mark-light.svg') }}" alt="">
            </span>
            <div>
                <div class="pa-card-title">PowerinAI Voice Assistant</div>
                <div class="pa-status">Online</div>
            </div>
            <button type="button" class="pa-menu" aria-label="More options">&#8942;</button>
        </header>

        <div class="pa-console">
            {{-- Waveform, mirrored either side of the button --}}
            @php($bars = [14, 22, 34, 52, 74, 96, 66, 44, 80, 58, 30, 20, 12])
            <div class="pa-wave pa-wave--left" aria-hidden="true">
                @foreach (array_reverse($bars) as $i => $height)
                    <span style="--h: {{ $height }}%; animation-delay: {{ $i * 60 }}ms"></span>
                @endforeach
            </div>

            <div class="pa-mic-shell">
                <span class="pa-ring-dotted" aria-hidden="true"></span>
                <span class="pa-ring-glow" aria-hidden="true"></span>
                <span class="pa-pulse" aria-hidden="true"></span>
                <span class="pa-pulse" aria-hidden="true"></span>

                <button type="button" class="pa-mic" id="pa-mic"
                        aria-pressed="false" aria-label="Tap to start speaking">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="pa-mic-g" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#818cf8"/>
                                <stop offset="100%" stop-color="#6c63ff"/>
                            </linearGradient>
                        </defs>
                        <rect x="9" y="2" width="6" height="12" rx="3" fill="url(#pa-mic-g)"/>
                        <path d="M5 11a7 7 0 0 0 14 0" stroke="url(#pa-mic-g)" stroke-width="2"
                              stroke-linecap="round"/>
                        <path d="M12 18v4M8.5 22h7" stroke="url(#pa-mic-g)" stroke-width="2"
                              stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="pa-wave pa-wave--right" aria-hidden="true">
                @foreach ($bars as $i => $height)
                    <span style="--h: {{ $height }}%; animation-delay: {{ $i * 60 }}ms"></span>
                @endforeach
            </div>
        </div>

        <div class="pa-prompt">
            <p class="pa-prompt-title" id="pa-title">Tap to start speaking</p>
            <p class="pa-prompt-sub" id="pa-sub">Listening for your request</p>
        </div>

        <button type="button" class="pa-input" id="pa-input" aria-label="Tap to speak">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <rect x="9" y="2" width="6" height="12" rx="3" fill="#6c63ff"/>
                <path d="M5 11a7 7 0 0 0 14 0" stroke="#6c63ff" stroke-width="2" stroke-linecap="round"/>
                <path d="M12 18v4" stroke="#6c63ff" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span class="pa-input-label" id="pa-input-label">Tap to speak</span>
            <svg width="22" height="18" viewBox="0 0 22 18" fill="none" aria-hidden="true">
                <g stroke="#6c63ff" stroke-width="2" stroke-linecap="round">
                    <path d="M2 7v4M6 4v10M10 1v16M14 4v10M18 7v4"/>
                </g>
            </svg>
        </button>
    </section>
</main>

<script>
    (function () {
        'use strict';

        var card = document.getElementById('pa-card');
        var mic = document.getElementById('pa-mic');
        var title = document.getElementById('pa-title');
        var sub = document.getElementById('pa-sub');
        var inputLabel = document.getElementById('pa-input-label');

        var copy = {
            idle: ['Tap to start speaking', 'Listening for your request', 'Tap to speak'],
            live: ['Listening…', 'Speak now — tap again to stop', 'Tap to stop']
        };

        function setListening(on) {
            var text = on ? copy.live : copy.idle;

            card.classList.toggle('is-listening', on);
            mic.setAttribute('aria-pressed', String(on));
            mic.setAttribute('aria-label', text[0]);
            title.textContent = text[0];
            sub.textContent = text[1];
            inputLabel.textContent = text[2];
        }

        function toggle() {
            setListening(!card.classList.contains('is-listening'));
        }

        mic.addEventListener('click', toggle);
        document.getElementById('pa-input').addEventListener('click', toggle);
    })();
</script>
</body>
</html>
