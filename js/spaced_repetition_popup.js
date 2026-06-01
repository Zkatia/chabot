// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under
// the terms of the GNU General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option)
// any later version.
//
// Moodle is distributed in the hope that it will be useful, but WITHOUT ANY
// WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
// FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License along
// with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * T2 spaced-repetition pop-up loader (plain JS, no AMD build required).
 *
 * Loaded in the footer on the first page after login. Calls popup_check.php
 * (which proxies the AI API with a short timeout) and, if a review is pending,
 * renders the "Etat 1 - Proposition" pop-up. In T2 the buttons are stubs : they
 * just close the pop-up (real actions arrive in T3/T5).
 *
 * Defensive : any timeout / network / parse error results in no pop-up.
 */
(function() {
    'use strict';

    var TIMEOUT_MS = 2000;

    function cfg() {
        return (window.M && window.M.cfg) ? window.M.cfg : null;
    }

    function buildUrl() {
        var c = cfg();
        if (!c || !c.wwwroot || !c.sesskey) {
            return null;
        }
        return c.wwwroot + '/local/astusse/popup_check.php?sesskey=' + encodeURIComponent(c.sesskey);
    }

    function el(tag, className, text) {
        var e = document.createElement(tag);
        if (className) {
            e.className = className;
        }
        if (text !== undefined && text !== null) {
            e.textContent = text;
        }
        return e;
    }

    function closePopup(overlay) {
        if (overlay && overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
        }
    }

    function render(data) {
        if (!data || !data.hasPending) {
            return;
        }

        var overlay = el('div', 'local-astusse-popup-overlay');
        var card = el('div', 'local-astusse-popup-card');
        overlay.appendChild(card);

        // Header : title + close cross.
        var header = el('div', 'local-astusse-popup-header');
        header.appendChild(el('span', 'local-astusse-popup-title', data.title));
        var cross = el('button', 'local-astusse-popup-cross', '✕');
        cross.setAttribute('type', 'button');
        cross.setAttribute('aria-label', data.btnClose || 'Close');
        header.appendChild(cross);
        card.appendChild(header);

        // Body.
        var body = el('div', 'local-astusse-popup-body');
        body.appendChild(el('p', 'local-astusse-popup-greeting', data.greeting));
        body.appendChild(el('p', null, data.consultedLine));
        body.appendChild(el('p', 'local-astusse-popup-review', data.reviewLine));
        body.appendChild(el('p', 'local-astusse-popup-pitch', data.pitch));
        card.appendChild(body);

        // Footer : 3 buttons (T2 stubs).
        var footer = el('div', 'local-astusse-popup-footer');
        var launch = el('button', 'btn btn-primary', data.btnLaunch);
        var later = el('button', 'btn btn-secondary', data.btnLater);
        var close = el('button', 'btn btn-link', data.btnClose);
        [launch, later, close].forEach(function(b) {
            b.setAttribute('type', 'button');
        });
        footer.appendChild(launch);
        footer.appendChild(later);
        footer.appendChild(close);
        card.appendChild(footer);

        // T2 : every control just closes the pop-up. T3/T5 will wire real actions.
        [cross, launch, later, close].forEach(function(b) {
            b.addEventListener('click', function() {
                closePopup(overlay);
            });
        });
        overlay.addEventListener('click', function(ev) {
            if (ev.target === overlay) {
                closePopup(overlay);
            }
        });

        document.body.appendChild(overlay);
    }

    function check() {
        var url = buildUrl();
        if (!url || !window.fetch) {
            return;
        }

        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var timer = controller ? window.setTimeout(function() {
            controller.abort();
        }, TIMEOUT_MS) : null;

        window.fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'},
            signal: controller ? controller.signal : undefined
        }).then(function(resp) {
            if (timer) {
                window.clearTimeout(timer);
            }
            return resp.ok ? resp.json() : null;
        }).then(function(data) {
            render(data);
        }).catch(function() {
            // Timeout / network / parse error -> no pop-up (defensive).
            if (timer) {
                window.clearTimeout(timer);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', check);
    } else {
        check();
    }
})();
