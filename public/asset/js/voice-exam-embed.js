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
        }

        function finish() {
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

        var nameInput = setValue('name', profile.name);
        var phoneInput = setValue('targetId', toNationalBd(profile.phone));

        [nameInput, phoneInput].forEach(function (node) {
            if (node) {
                node.readOnly = true;
                node.setAttribute('aria-readonly', 'true');
            }
        });

        // Required by the vendor's validation, but not part of the exam flow.
        var emailInput = setValue('email', profile.email);
        var websiteInput = setValue('website', profile.website);

        [emailInput, websiteInput].forEach(function (node) {
            if (node) {
                hide(node.parentElement || node);
            }
        });

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
