/**
 * Embeds the Speaklar voice-call widget inside the "Start an Exam" card.
 *
 * The vendor script (webcall-bd 1.js) mounts a floating button and a fixed-position
 * popup on document.body. This moves that popup into the card, fills the name and
 * phone from the student's profile, and hides the parts the exam flow does not use.
 *
 * Note: the vendor refuses to start a call unless its email and website inputs hold
 * valid values, so those are filled from the profile and hidden rather than removed.
 */
(function () {
    'use strict';

    var PREFIX = 'spcl';
    var POLL_INTERVAL = 100;
    var MAX_ATTEMPTS = 150; // ~15 seconds
    var CALL_LABEL_FROM = 'Start Call';
    var CALL_LABEL_TO = 'Start Exam';

    var container = document.getElementById('webcall-widget');

    if (!container) {
        return;
    }

    var profile = {
        name: container.dataset.name || '',
        phone: container.dataset.phone || '',
        email: container.dataset.email || '',
        website: container.dataset.website || ''
    };

    // Resolved lazily: a call can start long after this script ran.
    function sessionUrl() {
        return container.dataset.sessionUrl || '';
    }

    function sessionEndUrl() {
        return container.dataset.sessionEndUrl || '';
    }


    function el(id) {
        return document.getElementById(PREFIX + '-' + id);
    }

    function hide(node) {
        if (node) {
            node.style.setProperty('display', 'none', 'important');
        }
    }

    function setValue(id, value) {
        var node = el(id);

        if (!node) {
            return null;
        }

        node.value = value;
        node.dispatchEvent(new Event('input', { bubbles: true }));
        node.dispatchEvent(new Event('change', { bubbles: true }));

        return node;
    }

    function message(text) {
        var placeholder = document.getElementById('webcall-placeholder');

        if (placeholder) {
            placeholder.textContent = text;
        }
    }

    /**
     * The vendor parses the number with libphonenumber against ISO "BD", so it needs
     * the national form: no country code, leading zero.
     */
    function toNationalBd(raw) {
        var digits = String(raw || '').replace(/\D+/g, '');

        if (digits.indexOf('880') === 0) {
            digits = digits.slice(3);
        }

        if (digits && digits.charAt(0) !== '0') {
            digits = '0' + digits;
        }

        return digits;
    }

    /* ---------------------------------------------------------------------
     * Call id capture
     *
     * Speaklar places the call with SIP.js: the vendor calls UA.invite(), which
     * returns a session carrying the SIP Call-ID. That id is registered against
     * this student and the chosen subject, so when the provider posts the
     * transcript back with the same call id we know who sat which exam.
     * ------------------------------------------------------------------- */

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    function post(url, body) {
        if (!url) {
            return Promise.resolve();
        }

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        }).catch(function (error) {
            console.error('[voice-exam] request failed', error);
        });
    }

    /**
     * SIP.js has moved this around between versions, so try the known locations.
     */
    function readCallId(session) {
        if (!session) {
            return '';
        }

        var request = session.request || {};

        var candidates = [
            request.callId,
            typeof request.getHeader === 'function' ? request.getHeader('Call-ID') : null,
            session.dialog && session.dialog.callId,
            session.callId,
            session.id
        ];

        for (var i = 0; i < candidates.length; i++) {
            if (typeof candidates[i] === 'string' && candidates[i]) {
                return candidates[i];
            }
        }

        return '';
    }

    function trackSession(session) {
        var registered = false;
        var callId = '';

        /**
         * Records that this student started a voice exam. Speaklar does not hand the
         * browser its call id, so the transcript callback is reconciled later using the
         * number Speaklar reports for the call.
         *
         * If a SIP Call-ID happens to be readable it is sent along, but nothing depends
         * on it.
         */
        function register() {
            if (registered) {
                return;
            }

            registered = true;
            callId = readCallId(session);

            if (callId) {
                container.dataset.callId = callId;
            }

            console.info('[voice-exam] exam started, call id', callId || '(not provided)');

            post(sessionUrl(), callId ? { call_id: callId } : {});

            // Swap the student's details for a live camera preview.
            startCamera();
        }

        function finish() {
            stopCamera();

            if (registered) {
                post(sessionEndUrl(), callId ? { call_id: callId } : {});
            }
        }

        register();

        if (typeof session.on === 'function') {
            session.on('terminated', finish);
            session.on('failed', finish);
        }
    }

    function hookSip() {
        var proto = window.SIP && window.SIP.UA && window.SIP.UA.prototype;

        if (!proto || proto.voiceExamHooked || typeof proto.invite !== 'function') {
            return Boolean(proto && proto.voiceExamHooked);
        }

        var original = proto.invite;

        proto.invite = function () {
            var session = original.apply(this, arguments);

            try {
                trackSession(session);
            } catch (error) {
                console.error('[voice-exam] could not track call', error);
            }

            return session;
        };

        proto.voiceExamHooked = true;

        return true;
    }

    /* ---------------------------------------------------------------------
     * Avatar and camera
     *
     * The avatar is centred at the top of the panel on its own — the name and number
     * fields below it are filled from the profile and hidden. When a call starts the
     * avatar moves onto the live camera preview, inset in its corner.
     * ------------------------------------------------------------------- */

    var identityRow = null;
    var avatarEl = null;
    var cameraWrap = null;
    var cameraVideo = null;
    var cameraStream = null;

    function buildIdentityRow(popup) {
        var avatar = popup.querySelector('#avatar-container');

        if (!avatar) {
            return;
        }

        avatarEl = avatar;
        identityRow = document.createElement('div');
        identityRow.className = 'webcall-identity';

        avatar.parentNode.insertBefore(identityRow, avatar);
        identityRow.appendChild(avatar);

        // Camera preview lives alongside, hidden until a call starts.
        cameraWrap = document.createElement('div');
        cameraWrap.className = 'webcall-camera';
        cameraWrap.style.display = 'none';

        cameraVideo = document.createElement('video');
        cameraVideo.setAttribute('autoplay', '');
        cameraVideo.setAttribute('playsinline', '');
        cameraVideo.muted = true;

        var label = document.createElement('span');
        label.className = 'webcall-camera-label';
        label.textContent = 'Recording';

        cameraWrap.appendChild(cameraVideo);
        cameraWrap.appendChild(label);
        identityRow.parentNode.insertBefore(cameraWrap, identityRow.nextSibling);
    }

    function startCamera() {
        if (!cameraWrap || cameraStream) {
            return;
        }

        // Not available over plain HTTP, and absent in some embedded browsers.
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            console.warn('[voice-exam] camera unavailable — a secure context (HTTPS) is required.');

            return;
        }

        // Audio is left alone: the call already owns the microphone.
        navigator.mediaDevices.getUserMedia({ video: true, audio: false })
            .then(function (stream) {
                cameraStream = stream;
                cameraVideo.srcObject = stream;

                if (identityRow) {
                    identityRow.style.display = 'none';
                }

                // The avatar rides along, inset over the bottom-right of the video, so the
                // assistant stays on screen while the student is being filmed.
                if (avatarEl) {
                    cameraWrap.appendChild(avatarEl);
                }

                cameraWrap.style.display = '';
            })
            .catch(function (error) {
                // Permission refused or no device — the student info simply stays put.
                console.warn('[voice-exam] camera not started', error);
            });
    }

    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) { track.stop(); });
            cameraStream = null;
        }

        if (cameraVideo) {
            cameraVideo.srcObject = null;
        }

        if (cameraWrap) {
            cameraWrap.style.display = 'none';
        }

        // Put the avatar back at the head of the identity row.
        if (identityRow) {
            if (avatarEl) {
                identityRow.insertBefore(avatarEl, identityRow.firstChild);
            }

            identityRow.style.display = '';
        }
    }

    /**
     * The identity fields are hidden, so nobody would notice if the vendor cleared or
     * reformatted them. They are re-asserted before every call rather than set once.
     */
    function fillIdentity() {
        setValue('name', profile.name);
        setValue('targetId', toNationalBd(profile.phone));
        setValue('email', profile.email);
        setValue('website', profile.website);
    }

    /**
     * The vendor rewrites the call button's markup every time a call ends, so the
     * label is re-applied whenever it reappears rather than set once.
     */
    function watchCallButtonLabel() {
        var button = el('call-button');

        if (!button) {
            return;
        }

        function relabel() {
            var walker = document.createTreeWalker(button, NodeFilter.SHOW_TEXT);
            var node;

            while ((node = walker.nextNode())) {
                if (node.nodeValue.indexOf(CALL_LABEL_FROM) !== -1) {
                    node.nodeValue = node.nodeValue.replace(CALL_LABEL_FROM, CALL_LABEL_TO);
                }
            }
        }

        relabel();

        // Capture phase: the values are back in place before the vendor's own click
        // handler reads them.
        button.addEventListener('click', fillIdentity, true);

        // Only rewrites nodes still holding the old label, so this cannot loop.
        new MutationObserver(relabel).observe(button, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    function mount(popup) {
        var placeholder = document.getElementById('webcall-placeholder');

        if (placeholder) {
            placeholder.remove();
        }

        container.appendChild(popup);
        popup.classList.add('webcall-embedded');

        // The floating launcher and the close button belong to the popup's old life.
        hide(document.getElementById(PREFIX + '-toggle'));
        hide(el('close-call'));

        // Speaklar handles the call, so the language stays pinned to Bengali and the
        // language picker is not offered. The English branch would use a different provider.
        setValue('selected-language', 'bn');
        hide(el('language-flag-box'));

        // Every exam call dials Bangladesh, so the country picker is fixed at BD.
        // The button itself stays in the DOM — its data-iso drives the number parsing.
        hide(el('country-code'));
        hide(document.querySelector('.' + PREFIX + '-country-box'));

        // The vendor will not place a call without these, but the student has nothing to
        // choose here — the values come from their profile — so they are filled and hidden.
        fillIdentity();

        ['name', 'targetId', 'email', 'website'].forEach(function (id) {
            var node = el(id);

            if (node) {
                node.readOnly = true;
                node.setAttribute('aria-readonly', 'true');
                hide(node.parentElement || node);
            }
        });

        buildIdentityRow(popup);
        watchCallButtonLabel();

        container.classList.add('webcall-ready');
    }

    // A student with no phone number on file cannot be matched to their transcript,
    // so the widget is not mounted at all.
    if (!profile.phone) {
        return;
    }

    var attempts = 0;
    var mounted = false;
    var hooked = false;

    var timer = setInterval(function () {
        if (!mounted) {
            var popup = document.getElementById(PREFIX + '-popup');

            if (popup) {
                mount(popup);
                mounted = true;
            }
        }

        // SIP.js arrives on its own schedule, and must be hooked before the first call.
        if (!hooked) {
            hooked = hookSip();
        }

        if (mounted && hooked) {
            clearInterval(timer);

            return;
        }

        if (++attempts > MAX_ATTEMPTS) {
            clearInterval(timer);

            if (!mounted) {
                message('The voice call widget could not be loaded. Check your connection and reload the page.');
            } else if (!hooked) {
                console.error('[voice-exam] SIP.js was not found — call ids cannot be recorded.');
            }
        }
    }, POLL_INTERVAL);
})();
