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
 * T2 + T3 etape 6 : machine d'etat pop-up de revision espacee.
 *
 * Plain JS (pas d'AMD) pour eviter la dependance grunt build, conformement a
 * la decision T2 figee en memoire. Toutes les strings UI passent par
 * M.util.get_string() (preregistrees via $PAGE->requires->strings_for_js).
 *
 * Etats :
 *   1 = Proposition       (T2)  : pop-up "veux-tu reviser ?"
 *   2 = Question          (T3)  : QCM ou LIBRE + chrono client
 *   3 = Feedback          (T3)  : verdict + explication
 *   4 = Bilan             (T3)  : score, recommandation, actions
 *
 * Defensif : tout timeout / erreur reseau revient a une fermeture propre sans
 * casser la page.
 */
(function() {
    'use strict';

    var TIMEOUT_INITIAL_MS = 2000;       // check pending (T2)
    var TIMEOUT_QUIZ_MS = 12000;          // fetch quiz + record answer (LLM-juge possible)
    var POLL_INTERVAL_MS = 2000;          // re-poll GENERATING -> READY
    var POLL_MAX_ATTEMPTS = 10;           // ~20s total avant abandon

    /** Container racine, recree a chaque ouverture. */
    var overlay = null;
    /** Card interne (re-rendue a chaque transition d'etat). */
    var card = null;

    /** Etat machine. */
    var st = {
        initialData: null,    // payload de popup_check.php (strings T2 + quizSessionId)
        questions: [],
        currentIndex: 0,
        questionStartTime: 0, // performance.now() au render question N
        answers: {},          // questionId -> verdict (pour relecture/state-3)
        pollAttempts: 0,
    };

    // ---- helpers ----
    function cfg() {
        return (window.M && window.M.cfg) ? window.M.cfg : null;
    }

    /** Lit une string inlinee depuis le payload de popup_check.php (autonome, pas de M.str). */
    function s(key) {
        var strs = (st.initialData && st.initialData.strings) ? st.initialData.strings : {};
        return strs[key] !== undefined ? strs[key] : key;
    }

    /** Format mini : remplace les {placeholders} par les valeurs de params. */
    function fmt(tpl, params) {
        if (!tpl) return '';
        return String(tpl).replace(/\{(\w+)\}/g, function(_, k) {
            return params && params[k] !== undefined ? params[k] : '';
        });
    }

    function el(tag, className, text) {
        var e = document.createElement(tag);
        if (className) { e.className = className; }
        if (text !== undefined && text !== null) { e.textContent = text; }
        return e;
    }

    function close() {
        if (overlay && overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
        }
        overlay = null;
        card = null;
    }

    function rebuildCard() {
        // Reset le contenu de la card sans recreer l'overlay (preserve l'animation CSS).
        while (card.firstChild) {
            card.removeChild(card.firstChild);
        }
    }

    function ensureOverlay() {
        if (overlay) { return; }
        overlay = el('div', 'local-astusse-popup-overlay');
        card = el('div', 'local-astusse-popup-card');
        overlay.appendChild(card);
        overlay.addEventListener('click', function(ev) {
            if (ev.target === overlay) {
                // Click-outside fermeture autorisee uniquement en etat 1 et 4 (eviter abandon involontaire).
                if (overlay.dataset.allowOutsideClose === '1') {
                    close();
                }
            }
        });
        document.body.appendChild(overlay);
    }

    function renderHeader(title, allowCross) {
        var header = el('div', 'local-astusse-popup-header');
        header.appendChild(el('span', 'local-astusse-popup-title', title));
        if (allowCross) {
            var cross = el('button', 'local-astusse-popup-cross', '✕');
            cross.setAttribute('type', 'button');
            cross.setAttribute('aria-label', st.initialData && st.initialData.btnClose ? st.initialData.btnClose : 'Close');
            cross.addEventListener('click', close);
            header.appendChild(cross);
        }
        return header;
    }

    // ========================================================================
    // Etat 1 : Proposition
    // ========================================================================
    function renderState1(data) {
        st.initialData = data;
        ensureOverlay();
        rebuildCard();
        overlay.dataset.allowOutsideClose = '1';

        card.appendChild(renderHeader(data.title, true));

        var body = el('div', 'local-astusse-popup-body');
        body.appendChild(el('p', 'local-astusse-popup-greeting', data.greeting));
        body.appendChild(el('p', null, data.consultedLine));
        body.appendChild(el('p', 'local-astusse-popup-review', data.reviewLine));
        body.appendChild(el('p', 'local-astusse-popup-pitch', data.pitch));
        card.appendChild(body);

        var footer = el('div', 'local-astusse-popup-footer');
        var launch = el('button', 'btn btn-primary', data.btnLaunch);
        var later = el('button', 'btn btn-secondary', data.btnLater);
        var closeBtn = el('button', 'btn btn-link', data.btnClose);
        [launch, later, closeBtn].forEach(function(b) { b.setAttribute('type', 'button'); });
        footer.appendChild(launch);
        footer.appendChild(later);
        footer.appendChild(closeBtn);
        card.appendChild(footer);

        // T5 cablera "Plus tard" (snooze 4h). En T3 c'est un close simple.
        later.addEventListener('click', close);
        closeBtn.addEventListener('click', close);

        launch.addEventListener('click', function() {
            if (!data.quizSessionId) {
                renderError(s('errorLoad'));
                return;
            }
            renderLoading(s('loading'));
            fetchQuizPolling(data.quizSessionId);
        });
    }

    // ========================================================================
    // Loader generique (transitions inter-etats)
    // ========================================================================
    function renderLoading(message) {
        ensureOverlay();
        rebuildCard();
        overlay.dataset.allowOutsideClose = '0';
        card.appendChild(renderHeader(st.initialData ? st.initialData.title : '...', false));
        var body = el('div', 'local-astusse-popup-body local-astusse-popup-loading');
        body.appendChild(el('div', 'local-astusse-popup-spinner'));
        body.appendChild(el('p', null, message));
        card.appendChild(body);
    }

    function renderError(message) {
        ensureOverlay();
        rebuildCard();
        overlay.dataset.allowOutsideClose = '1';
        card.appendChild(renderHeader(st.initialData ? st.initialData.title : '...', true));
        var body = el('div', 'local-astusse-popup-body');
        body.appendChild(el('p', 'local-astusse-popup-error', message));
        card.appendChild(body);
        var footer = el('div', 'local-astusse-popup-footer');
        var ok = el('button', 'btn btn-secondary',
            st.initialData && st.initialData.btnClose ? st.initialData.btnClose : 'OK');
        ok.setAttribute('type', 'button');
        ok.addEventListener('click', close);
        footer.appendChild(ok);
        card.appendChild(footer);
    }

    // ========================================================================
    // Fetch quiz (gestion polling GENERATING -> READY)
    // ========================================================================
    function fetchQuizPolling(quizSessionId) {
        st.pollAttempts = 0;
        attemptFetch(quizSessionId);
    }

    function attemptFetch(quizSessionId) {
        st.pollAttempts++;
        var c = cfg();
        if (!c) { renderError(s('errorLoad')); return; }

        var fd = new FormData();
        fd.append('sesskey', c.sesskey);
        fd.append('quizSessionId', quizSessionId);

        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var timer = controller ? window.setTimeout(function() { controller.abort(); }, TIMEOUT_QUIZ_MS) : null;

        window.fetch(c.wwwroot + '/local/astusse/quiz_generate.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: fd,
            headers: {'Accept': 'application/json'},
            signal: controller ? controller.signal : undefined
        }).then(function(resp) {
            if (timer) { window.clearTimeout(timer); }
            return resp.json().then(function(j) { return { status: resp.status, body: j }; });
        }).then(function(r) {
            if (r.status === 410) { renderError(s('errorExpired')); return; }
            if (r.status === 404 || r.status === 400) { renderError(s('errorLoad')); return; }
            if (r.status === 502) { renderError(s('errorLoad')); return; }
            if (r.status !== 200 || !r.body) { renderError(s('errorLoad')); return; }

            var status = r.body.status;
            if (status === 'READY' || status === 'IN_PROGRESS') {
                st.questions = Array.isArray(r.body.questions) ? r.body.questions : [];
                if (st.questions.length === 0) { renderError(s('errorLoad')); return; }
                st.currentIndex = 0;
                renderState2();
            } else if (status === 'GENERATING') {
                if (st.pollAttempts >= POLL_MAX_ATTEMPTS) {
                    renderError(s('errorGeneratingTimeout'));
                    return;
                }
                renderLoading(s('waitingGeneration'));
                window.setTimeout(function() { attemptFetch(quizSessionId); }, POLL_INTERVAL_MS);
            } else if (status === 'EXPIRED') {
                renderError(s('errorExpired'));
            } else {
                renderError(s('errorFailed'));
            }
        }).catch(function() {
            if (timer) { window.clearTimeout(timer); }
            renderError(s('errorLoad'));
        });
    }

    // ========================================================================
    // Etat 2 : Question (QCM ou LIBRE)
    // ========================================================================
    function renderState2() {
        ensureOverlay();
        rebuildCard();
        overlay.dataset.allowOutsideClose = '0';

        var q = st.questions[st.currentIndex];
        var total = st.questions.length;

        card.appendChild(renderHeader(st.initialData.title, true));

        var body = el('div', 'local-astusse-popup-body');
        body.appendChild(el('div', 'local-astusse-quiz-progress',
            fmt(s('questionProgressTpl'), { current: st.currentIndex + 1, total: total })));
        body.appendChild(el('p', 'local-astusse-quiz-prompt', q.prompt));

        var form = el('div', 'local-astusse-quiz-form');
        var inputs = [];
        var isQcm = q.type === 'qcm';

        if (isQcm && Array.isArray(q.choices)) {
            q.choices.forEach(function(choice, idx) {
                var label = el('label', 'local-astusse-quiz-choice');
                var input = document.createElement('input');
                input.type = 'radio';
                input.name = 'astusse-quiz-' + q.questionId;
                input.value = String(idx);
                label.appendChild(input);
                label.appendChild(el('span', null, ' ' + choice));
                form.appendChild(label);
                inputs.push(input);
            });
        } else {
            var ta = document.createElement('textarea');
            ta.className = 'local-astusse-quiz-textarea form-control';
            ta.rows = 4;
            ta.placeholder = s('librePlaceholder');
            form.appendChild(ta);
            inputs.push(ta);
        }
        body.appendChild(form);
        card.appendChild(body);

        var footer = el('div', 'local-astusse-popup-footer');
        var validate = el('button', 'btn btn-primary', s('validate'));
        validate.setAttribute('type', 'button');
        validate.disabled = true;
        footer.appendChild(validate);
        card.appendChild(footer);

        // Active le bouton dès qu'une saisie est faite.
        if (isQcm) {
            inputs.forEach(function(inp) {
                inp.addEventListener('change', function() { validate.disabled = false; });
            });
        } else {
            inputs[0].addEventListener('input', function() {
                validate.disabled = inputs[0].value.trim().length === 0;
            });
        }

        // Chrono client : performance.now() = haute precision.
        st.questionStartTime = (window.performance && window.performance.now) ? window.performance.now() : Date.now();

        validate.addEventListener('click', function() {
            var elapsed = ((window.performance && window.performance.now) ? window.performance.now() : Date.now()) - st.questionStartTime;
            var responseTimeMs = Math.max(0, Math.round(elapsed));
            var userAnswerIndex = null, userAnswerText = null;
            if (isQcm) {
                for (var i = 0; i < inputs.length; i++) {
                    if (inputs[i].checked) { userAnswerIndex = parseInt(inputs[i].value, 10); break; }
                }
                if (userAnswerIndex === null) { return; }
            } else {
                userAnswerText = inputs[0].value.trim();
                if (!userAnswerText) { return; }
            }
            validate.disabled = true;
            sendAnswer(q, userAnswerIndex, userAnswerText, responseTimeMs);
        });
    }

    function sendAnswer(question, userAnswerIndex, userAnswerText, responseTimeMs) {
        renderLoading(s('loading'));
        var c = cfg();
        if (!c) { renderError(s('errorSend')); return; }

        var fd = new FormData();
        fd.append('sesskey', c.sesskey);
        fd.append('quizSessionId', st.initialData.quizSessionId);
        fd.append('questionId', question.questionId);
        if (userAnswerIndex !== null) { fd.append('userAnswerIndex', String(userAnswerIndex)); }
        if (userAnswerText !== null) { fd.append('userAnswerText', userAnswerText); }
        fd.append('responseTimeMs', String(responseTimeMs));

        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var timer = controller ? window.setTimeout(function() { controller.abort(); }, TIMEOUT_QUIZ_MS) : null;

        window.fetch(c.wwwroot + '/local/astusse/quiz_answer.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: fd,
            headers: {'Accept': 'application/json'},
            signal: controller ? controller.signal : undefined
        }).then(function(resp) {
            if (timer) { window.clearTimeout(timer); }
            return resp.json().then(function(j) { return { status: resp.status, body: j }; });
        }).then(function(r) {
            if (r.status === 410) { renderError(s('errorExpired')); return; }
            if (r.status !== 200 || !r.body) { renderError(s('errorSend')); return; }
            st.answers[question.questionId] = r.body;
            renderState3(question, r.body);
        }).catch(function() {
            if (timer) { window.clearTimeout(timer); }
            renderError(s('errorSend'));
        });
    }

    // ========================================================================
    // Etat 3 : Feedback per-question
    // ========================================================================
    function renderState3(question, verdict) {
        ensureOverlay();
        rebuildCard();
        overlay.dataset.allowOutsideClose = '0';

        card.appendChild(renderHeader(st.initialData.title, true));

        var body = el('div', 'local-astusse-popup-body');

        var isPending = !!verdict.pending;
        var isCorrect = verdict.correct === true;
        var verdictClass = isPending
            ? 'local-astusse-quiz-verdict pending'
            : (isCorrect ? 'local-astusse-quiz-verdict correct' : 'local-astusse-quiz-verdict incorrect');
        var verdictLabel = isPending
            ? s('feedbackPending')
            : (isCorrect ? s('feedbackCorrect') : s('feedbackIncorrect'));
        body.appendChild(el('div', verdictClass, verdictLabel));

        // Affichage de la bonne reponse (revelee post-validation).
        if (!isPending) {
            if (question.type === 'qcm' && verdict.correctIndex !== null && verdict.correctIndex !== undefined) {
                var correctChoice = Array.isArray(question.choices) ? question.choices[verdict.correctIndex] : '';
                body.appendChild(el('p', 'local-astusse-quiz-correct',
                    fmt(s('correctAnswerQcmTpl'), { answer: correctChoice })));
            } else if (question.type === 'libre' && verdict.correctAnswer) {
                body.appendChild(el('p', 'local-astusse-quiz-correct',
                    fmt(s('correctAnswerLibreTpl'), { answer: verdict.correctAnswer })));
            }
        }

        if (verdict.explanation) {
            body.appendChild(el('p', 'local-astusse-quiz-explanation', verdict.explanation));
        }

        card.appendChild(body);

        var footer = el('div', 'local-astusse-popup-footer');
        var isLast = st.currentIndex >= st.questions.length - 1;
        var nextBtn = el('button', 'btn btn-primary', isLast ? s('seeResult') : s('next'));
        nextBtn.setAttribute('type', 'button');
        nextBtn.addEventListener('click', function() {
            if (isLast) {
                renderLoading(s('loading'));
                finalizeQuiz();
            } else {
                st.currentIndex++;
                renderState2();
            }
        });
        footer.appendChild(nextBtn);
        card.appendChild(footer);
    }

    // ========================================================================
    // Finalisation + Etat 4 (Bilan)
    // ========================================================================
    function finalizeQuiz() {
        var c = cfg();
        if (!c) { renderError(s('errorSend')); return; }

        var fd = new FormData();
        fd.append('sesskey', c.sesskey);
        fd.append('quizSessionId', st.initialData.quizSessionId);

        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var timer = controller ? window.setTimeout(function() { controller.abort(); }, TIMEOUT_QUIZ_MS) : null;

        window.fetch(c.wwwroot + '/local/astusse/quiz_result.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: fd,
            headers: {'Accept': 'application/json'},
            signal: controller ? controller.signal : undefined
        }).then(function(resp) {
            if (timer) { window.clearTimeout(timer); }
            return resp.json().then(function(j) { return { status: resp.status, body: j }; });
        }).then(function(r) {
            if (r.status === 410) { renderError(s('errorExpired')); return; }
            if (r.status !== 200 || !r.body) { renderError(s('errorSend')); return; }
            renderState4(r.body);
        }).catch(function() {
            if (timer) { window.clearTimeout(timer); }
            renderError(s('errorSend'));
        });
    }

    function renderState4(bilan) {
        ensureOverlay();
        rebuildCard();
        overlay.dataset.allowOutsideClose = '1';

        card.appendChild(renderHeader(s('bilanTitle'), true));

        var body = el('div', 'local-astusse-popup-body');
        body.appendChild(el('div', 'local-astusse-bilan-score',
            fmt(s('bilanScoreTpl'), { correct: bilan.correctCount, total: bilan.totalCount })));

        var reco = bilan.recommendation;
        var recoText = '';
        if (reco === 'CONSOLIDATION') {
            recoText = fmt(s('bilanConsolidationTpl'), { days: bilan.nextReviewDays });
        } else if (reco === 'PARTIAL') {
            recoText = s('bilanPartial');
        } else {
            recoText = s('bilanWeak');
        }
        body.appendChild(el('p', 'local-astusse-bilan-reco', recoText));

        if (Array.isArray(bilan.perResource) && bilan.perResource.length > 0) {
            body.appendChild(el('p', 'local-astusse-bilan-perres-label', s('bilanPerresourceLabel')));
            var ul = el('ul', 'local-astusse-bilan-list');
            bilan.perResource.forEach(function(pr) {
                // Construction DOM directe pour pouvoir inserer un <a> dans le nom du cours,
                // sans risque d'injection (textContent sur chaque morceau).
                var li = document.createElement('li');
                var resourceName = pr.resourceName || ('Resource #' + pr.resourceCmid);
                var courseName = pr.courseName || '';

                li.appendChild(document.createTextNode(resourceName + ' ('));
                if (courseName && pr.courseUrl) {
                    var courseLink = document.createElement('a');
                    courseLink.href = pr.courseUrl;
                    courseLink.textContent = courseName;
                    li.appendChild(courseLink);
                } else {
                    li.appendChild(document.createTextNode(courseName));
                }
                li.appendChild(document.createTextNode(') - ' + pr.correctCount + '/' + pr.totalCount));
                ul.appendChild(li);
            });
            body.appendChild(ul);
        }
        card.appendChild(body);

        var footer = el('div', 'local-astusse-popup-footer');
        if (reco === 'PARTIAL' && bilan.fragileViewUrl) {
            var view = document.createElement('a');
            view.className = 'btn btn-primary';
            view.href = bilan.fragileViewUrl;
            view.textContent = s('bilanSeeResource');
            footer.appendChild(view);
        } else if (reco === 'WEAK') {
            var tutor = document.createElement('a');
            tutor.className = 'btn btn-primary';
            // T3 etape 7 cablera le contexte preload sur le chat. En T3 etape 6, lien generique.
            var c2 = cfg();
            tutor.href = (c2 ? c2.wwwroot : '') + '/local/astusse/chat.php?quizSessionId='
                + encodeURIComponent(st.initialData.quizSessionId);
            tutor.textContent = s('bilanAskTutor');
            footer.appendChild(tutor);
        }
        var finish = el('button', 'btn btn-secondary', s('bilanFinish'));
        finish.setAttribute('type', 'button');
        finish.addEventListener('click', close);
        footer.appendChild(finish);
        card.appendChild(footer);
    }

    // ========================================================================
    // Entry point : appel popup_check.php au chargement
    // ========================================================================
    function buildCheckUrl() {
        var c = cfg();
        if (!c || !c.wwwroot || !c.sesskey) { return null; }
        return c.wwwroot + '/local/astusse/popup_check.php?sesskey=' + encodeURIComponent(c.sesskey);
    }

    function check() {
        var url = buildCheckUrl();
        if (!url || !window.fetch) { return; }

        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var timer = controller ? window.setTimeout(function() { controller.abort(); }, TIMEOUT_INITIAL_MS) : null;

        window.fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'},
            signal: controller ? controller.signal : undefined
        }).then(function(resp) {
            if (timer) { window.clearTimeout(timer); }
            return resp.ok ? resp.json() : null;
        }).then(function(data) {
            if (data && data.hasPending) {
                renderState1(data);
            }
        }).catch(function() {
            if (timer) { window.clearTimeout(timer); }
            // Silencieux : pas de pop-up si l'API est down (defensif).
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', check);
    } else {
        check();
    }
})();
