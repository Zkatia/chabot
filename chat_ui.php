<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Shared chat UI helpers for local_astusse.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Build shared course metadata consumed by the chat UI.
 *
 * @param stdClass $course
 * @param array $referencestatus
 * @param bool $showreferencecontext
 * @param bool $locked
 * @return array
 */
function local_astusse_build_chat_course_meta(
    stdClass $course,
    array $referencestatus,
    bool $showreferencecontext,
    bool $locked
): array {
    $referencetext = '';
    if ($showreferencecontext) {
        if ($referencestatus['state'] === 'valid') {
            $referencetext = get_string('chat:reference_trainer_context', 'local_astusse', fullname($referencestatus['user']));
        } else if ($referencestatus['state'] === 'invalid') {
            $referencetext = get_string('chat:reference_trainer_invalid', 'local_astusse');
        } else {
            $referencetext = get_string('chat:reference_trainer_missing', 'local_astusse');
        }
    }

    return [
        'id' => (string)$course->id,
        'fullname' => format_string($course->fullname),
        'shortname' => format_string($course->shortname),
        'locked' => $locked,
        'referenceVisible' => $showreferencecontext,
        'referenceState' => (string)$referencestatus['state'],
        'referenceText' => $referencetext,
        'referenceTrainerId' => $referencestatus['state'] === 'valid' ? (string)$referencestatus['trainerid'] : '',
        'courseChatUrl' => (new moodle_url('/local/astusse/chat.php', ['courseid' => $course->id]))->out(false),
    ];
}

/**
 * Return common UI strings for local chat pages.
 *
 * @return array
 */
function local_astusse_get_chat_ui_strings(): array {
    return [
        'heading' => get_string('chat:heading', 'local_astusse'),
        'globalHeading' => get_string('chat:global_heading', 'local_astusse'),
        'intro' => get_string('chat:intro', 'local_astusse'),
        'globalIntro' => get_string('chat:global_intro', 'local_astusse'),
        'ready' => get_string('chat:status_ready', 'local_astusse'),
        'loading' => get_string('chat:status_loading', 'local_astusse'),
        'empty' => get_string('chat:empty_state', 'local_astusse'),
        'emptyDetail' => get_string('chat:empty_state_detail', 'local_astusse'),
        'studentLabel' => get_string('chat:student_label', 'local_astusse'),
        'assistantLabel' => get_string('chat:assistant_label', 'local_astusse'),
        'genericError' => get_string('chat:error_generic', 'local_astusse'),
        'traceIdLabel' => get_string('chat:traceid_label', 'local_astusse'),
        'sessionIdLabel' => get_string('chat:sessionid_label', 'local_astusse'),
        'agentUsedLabel' => get_string('chat:agent_used_label', 'local_astusse'),
        'technicalDetailsLabel' => get_string('chat:technical_details_label', 'local_astusse'),
        'pending' => get_string('chat:pending_label', 'local_astusse'),
        'invalidJson' => get_string('chat:error_invalid_json', 'local_astusse'),
        'messageLabel' => get_string('chat:message_label', 'local_astusse'),
        'messagePlaceholder' => get_string('chat:message_placeholder', 'local_astusse'),
        'sendButton' => get_string('chat:send_button', 'local_astusse'),
        'newSessionButton' => get_string('chat:new_session_button', 'local_astusse'),
        'historyNotice' => get_string('chat:history_notice', 'local_astusse'),
        'inputHint' => get_string('chat:input_hint', 'local_astusse'),
        'courseLabel' => get_string('chat:course_selector_label', 'local_astusse'),
        'coursePlaceholder' => get_string('chat:course_selector_placeholder', 'local_astusse'),
        'courseRequired' => get_string('chat:error_course_required', 'local_astusse'),
        'courseLocked' => get_string('chat:course_locked_notice', 'local_astusse'),
        'conversationsLabel' => get_string('chat:conversations_label', 'local_astusse'),
        'conversationsEmpty' => get_string('chat:conversations_empty', 'local_astusse'),
        'conversationsEmptyDetail' => get_string('chat:conversations_empty_detail', 'local_astusse'),
        'noCourseTitle' => get_string('chat:no_course_title', 'local_astusse'),
        'noCourseDetail' => get_string('chat:no_course_detail', 'local_astusse'),
        'untitledConversation' => get_string('chat:untitled_conversation', 'local_astusse'),
        'courseContextLabel' => get_string('chat:course_context_label', 'local_astusse'),
        'referenceTrainerTitle' => get_string('chat:reference_trainer_title', 'local_astusse'),
    ];
}

/**
 * Render the shared chat UI.
 *
 * @param array $config
 * @return void
 */
function local_astusse_render_chat_ui(array $config): void {
    $jsonflags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $jsonconfig = json_encode($config, $jsonflags);
    ?>
<div class="local-astusse-chatapp" data-page-mode="<?php echo s((string)$config['pageMode']); ?>">
    <aside class="local-astusse-chatapp-sidebar">
        <div class="local-astusse-chatapp-brand">
            <span class="local-astusse-chatapp-brand-kicker">ASTUSSE</span>
            <h2><?php echo s(get_string('chat:brand_title', 'local_astusse')); ?></h2>
            <p><?php echo s($config['pageMode'] === 'global' ? $config['strings']['globalIntro'] : $config['strings']['historyNotice']); ?></p>
        </div>
        <button type="button" id="local-astusse-chatapp-new" class="btn btn-primary local-astusse-chatapp-new">
            <?php echo s($config['strings']['newSessionButton']); ?>
        </button>
        <div class="local-astusse-chatapp-sidebar-section">
            <div class="local-astusse-chatapp-sidebar-heading"><?php echo s($config['strings']['conversationsLabel']); ?></div>
            <div id="local-astusse-chatapp-threadlist" class="local-astusse-chatapp-threadlist"></div>
        </div>
    </aside>

    <section class="local-astusse-chatapp-main">
        <header class="local-astusse-chatapp-topbar">
            <div class="local-astusse-chatapp-topbar-copy">
                <span class="local-astusse-chatapp-kicker">ASTUSSE</span>
                <h1><?php echo s($config['pageMode'] === 'global' ? $config['strings']['globalHeading'] : $config['strings']['heading']); ?></h1>
                <p><?php echo s($config['pageMode'] === 'global' ? $config['strings']['globalIntro'] : $config['strings']['intro']); ?></p>
            </div>
            <div class="local-astusse-chatapp-topbar-side">
                <span id="local-astusse-chatapp-status" class="local-astusse-chatapp-status">
                    <?php echo s($config['strings']['ready']); ?>
                </span>
            </div>
        </header>

        <section class="local-astusse-chatapp-context" aria-live="polite">
            <div class="local-astusse-chatapp-context-card">
                <span class="local-astusse-chatapp-context-label"><?php echo s($config['strings']['courseContextLabel']); ?></span>
                <strong id="local-astusse-chatapp-course-context"></strong>
            </div>
            <div id="local-astusse-chatapp-reference-card" class="local-astusse-chatapp-context-card is-reference">
                <span class="local-astusse-chatapp-context-label"><?php echo s($config['strings']['referenceTrainerTitle']); ?></span>
                <strong id="local-astusse-chatapp-reference-context"></strong>
            </div>
        </section>

        <div id="local-astusse-chatapp-messages" class="local-astusse-chatapp-messages" aria-live="polite"></div>

        <section class="local-astusse-chatapp-composer">
            <div class="local-astusse-chatapp-controls">
                <div class="local-astusse-chatapp-field">
                    <label for="local-astusse-chatapp-course"><?php echo s($config['strings']['courseLabel']); ?></label>
                    <select id="local-astusse-chatapp-course" class="custom-select"></select>
                    <p id="local-astusse-chatapp-course-note" class="local-astusse-chatapp-field-note"></p>
                </div>
                <div class="local-astusse-chatapp-field">
                    <label for="local-astusse-chatapp-agent"><?php echo s(get_string('chat:agent_label', 'local_astusse')); ?></label>
                    <select id="local-astusse-chatapp-agent" class="custom-select">
                        <option value="explicatif"><?php echo s($config['labels']['agents']['explicatif']); ?></option>
                        <option value="socratique"><?php echo s($config['labels']['agents']['socratique']); ?></option>
                    </select>
                </div>
            </div>
            <div class="local-astusse-chatapp-field">
                <label for="local-astusse-chatapp-input"><?php echo s($config['strings']['messageLabel']); ?></label>
                <textarea id="local-astusse-chatapp-input" rows="4" placeholder="<?php echo s($config['strings']['messagePlaceholder']); ?>"></textarea>
            </div>
            <div class="local-astusse-chatapp-actions">
                <p class="local-astusse-chatapp-hint"><?php echo s($config['strings']['inputHint']); ?></p>
                <button type="button" id="local-astusse-chatapp-send" class="btn btn-primary local-astusse-chatapp-send">
                    <?php echo s($config['strings']['sendButton']); ?>
                </button>
            </div>
        </section>
    </section>
</div>

<script>
(function() {
    const config = <?php echo $jsonconfig ?: '{}'; ?>;
    const storageKey = config.storageKey || 'local_astusse_chat_threads_v4';
    const courseMap = new Map((config.courses || []).map((course) => [String(course.id), course]));
    const courseSelect = document.getElementById('local-astusse-chatapp-course');
    const agentSelect = document.getElementById('local-astusse-chatapp-agent');
    const messageInput = document.getElementById('local-astusse-chatapp-input');
    const sendButton = document.getElementById('local-astusse-chatapp-send');
    const newButton = document.getElementById('local-astusse-chatapp-new');
    const statusNode = document.getElementById('local-astusse-chatapp-status');
    const threadList = document.getElementById('local-astusse-chatapp-threadlist');
    const messagesNode = document.getElementById('local-astusse-chatapp-messages');
    const courseContextNode = document.getElementById('local-astusse-chatapp-course-context');
    const referenceCardNode = document.getElementById('local-astusse-chatapp-reference-card');
    const referenceContextNode = document.getElementById('local-astusse-chatapp-reference-context');
    const courseNoteNode = document.getElementById('local-astusse-chatapp-course-note');
    let isSending = false;

    function createSessionId() {
        return 'astusse-' + Date.now() + '-' + Math.random().toString(16).slice(2, 10);
    }

    function createId(prefix) {
        return prefix + '-' + Date.now() + '-' + Math.random().toString(16).slice(2, 8);
    }

    function normalizeAgentType(value) {
        return value === 'socratique' ? 'socratique' : 'explicatif';
    }

    function defaultState() {
        return {
            selectedCourseId: config.lockedCourse ? String(config.selectedCourseId || '') : '',
            activeThreadIdByCourse: {},
            threadsByCourse: {}
        };
    }

    function normalizeThread(thread, courseId) {
        return {
            id: String(thread.id || createId('conv')),
            courseId: String(thread.courseId || courseId),
            sessionId: String(thread.sessionId || createSessionId()),
            title: String(thread.title || config.strings.untitledConversation),
            agentType: normalizeAgentType(thread.agentType),
            updatedAt: Number(thread.updatedAt || Date.now()),
            messages: Array.isArray(thread.messages) ? thread.messages.map((message) => ({
                id: String(message.id || createId('msg')),
                role: message.role === 'assistant' ? 'assistant' : 'user',
                text: String(message.text || ''),
                pending: !!message.pending,
                error: !!message.error,
                traceId: String(message.traceId || ''),
                sessionId: String(message.sessionId || ''),
                agentUsed: String(message.agentUsed || '')
            })) : []
        };
    }

    function loadState() {
        try {
            const parsed = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
            if (!parsed || typeof parsed !== 'object') {
                return defaultState();
            }

            const state = defaultState();
            if (!config.lockedCourse && parsed.selectedCourseId && courseMap.has(String(parsed.selectedCourseId))) {
                state.selectedCourseId = String(parsed.selectedCourseId);
            }

            if (parsed.activeThreadIdByCourse && typeof parsed.activeThreadIdByCourse === 'object') {
                Object.keys(parsed.activeThreadIdByCourse).forEach((courseId) => {
                    state.activeThreadIdByCourse[String(courseId)] = String(parsed.activeThreadIdByCourse[courseId]);
                });
            }

            if (parsed.threadsByCourse && typeof parsed.threadsByCourse === 'object') {
                Object.keys(parsed.threadsByCourse).forEach((courseId) => {
                    if (!courseMap.has(String(courseId))) {
                        return;
                    }
                    const threads = Array.isArray(parsed.threadsByCourse[courseId]) ? parsed.threadsByCourse[courseId] : [];
                    state.threadsByCourse[String(courseId)] = threads.map((thread) => normalizeThread(thread, String(courseId)));
                });
            }

            if (config.lockedCourse) {
                state.selectedCourseId = String(config.selectedCourseId || '');
            }
            return state;
        } catch (error) {
            return defaultState();
        }
    }

    let state = loadState();

    function saveState() {
        window.localStorage.setItem(storageKey, JSON.stringify(state));
    }

    function getSelectedCourseId() {
        return config.lockedCourse ? String(config.selectedCourseId || '') : String(state.selectedCourseId || '');
    }

    function setSelectedCourseId(courseId) {
        if (!config.lockedCourse) {
            state.selectedCourseId = String(courseId || '');
            saveState();
        }
    }

    function getThreads(courseId) {
        const key = String(courseId || '');
        if (!state.threadsByCourse[key]) {
            state.threadsByCourse[key] = [];
        }
        return state.threadsByCourse[key];
    }

    function getSortedThreads(courseId) {
        return getThreads(courseId).slice().sort((a, b) => b.updatedAt - a.updatedAt);
    }

    function getThreadById(courseId, threadId) {
        return getThreads(courseId).find((thread) => thread.id === threadId) || null;
    }

    function getActiveThread(courseId) {
        const key = String(courseId || '');
        const activeId = state.activeThreadIdByCourse[key] || '';
        const activeThread = activeId ? getThreadById(key, activeId) : null;
        if (activeThread) {
            return activeThread;
        }
        const sortedThreads = getSortedThreads(key);
        if (!sortedThreads.length) {
            return null;
        }
        state.activeThreadIdByCourse[key] = sortedThreads[0].id;
        saveState();
        return sortedThreads[0];
    }

    function setActiveThread(courseId, threadId) {
        state.activeThreadIdByCourse[String(courseId)] = String(threadId);
        saveState();
    }

    function deriveTitle(text) {
        const cleaned = String(text || '').replace(/\s+/g, ' ').trim();
        if (!cleaned) {
            return config.strings.untitledConversation;
        }
        return cleaned.length > 42 ? cleaned.slice(0, 39) + '...' : cleaned;
    }

    function createThread(courseId) {
        const key = String(courseId);
        const thread = normalizeThread({
            id: createId('conv'),
            courseId: key,
            sessionId: createSessionId(),
            title: config.strings.untitledConversation,
            agentType: agentSelect.value || (config.defaults && config.defaults.agentType) || 'explicatif',
            updatedAt: Date.now(),
            messages: []
        }, key);
        getThreads(key).push(thread);
        state.activeThreadIdByCourse[key] = thread.id;
        saveState();
        return thread;
    }

    function setStatus(text, kind) {
        statusNode.textContent = text;
        statusNode.classList.remove('is-error', 'is-busy');
        if (kind === 'error') {
            statusNode.classList.add('is-error');
        } else if (kind === 'busy') {
            statusNode.classList.add('is-busy');
        }
    }

    function syncCourseOptions() {
        const selectedCourseId = getSelectedCourseId();
        courseSelect.innerHTML = '';
        if (!config.lockedCourse) {
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = config.strings.coursePlaceholder;
            courseSelect.appendChild(placeholder);
        }
        (config.courses || []).forEach((course) => {
            const option = document.createElement('option');
            option.value = String(course.id);
            option.textContent = course.fullname;
            if (String(course.id) === selectedCourseId) {
                option.selected = true;
            }
            courseSelect.appendChild(option);
        });
        courseSelect.disabled = !!config.lockedCourse;
        courseNoteNode.textContent = config.lockedCourse ? config.strings.courseLocked : config.strings.historyNotice;
    }

    function renderCourseContext() {
        const courseId = getSelectedCourseId();
        if (!courseId || !courseMap.has(courseId)) {
            courseContextNode.textContent = config.strings.coursePlaceholder;
            referenceCardNode.hidden = true;
            return;
        }

        const course = courseMap.get(courseId);
        courseContextNode.textContent = course.fullname;
        if (course.referenceVisible) {
            referenceCardNode.hidden = false;
            referenceCardNode.classList.remove('is-valid', 'is-invalid', 'is-missing');
            referenceCardNode.classList.add('is-' + String(course.referenceState || 'missing'));
            referenceContextNode.textContent = course.referenceText || '';
        } else {
            referenceCardNode.hidden = true;
        }
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderThreadList() {
        const courseId = getSelectedCourseId();
        threadList.innerHTML = '';
        if (!courseId) {
            const empty = document.createElement('div');
            empty.className = 'local-astusse-chatapp-sidebar-empty';
            empty.textContent = config.strings.noCourseDetail;
            threadList.appendChild(empty);
            return;
        }

        const activeThread = getActiveThread(courseId);
        const threads = getSortedThreads(courseId);
        if (!threads.length) {
            const empty = document.createElement('div');
            empty.className = 'local-astusse-chatapp-sidebar-empty';
            empty.innerHTML = '<strong>' + escapeHtml(config.strings.conversationsEmpty) + '</strong><span>' +
                escapeHtml(config.strings.conversationsEmptyDetail) + '</span>';
            threadList.appendChild(empty);
            return;
        }

        threads.forEach((thread) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'local-astusse-chatapp-thread' + (activeThread && activeThread.id === thread.id ? ' is-active' : '');
            button.innerHTML =
                '<strong>' + escapeHtml(thread.title || config.strings.untitledConversation) + '</strong>' +
                '<span>' + escapeHtml((config.labels.agents && config.labels.agents[thread.agentType]) || thread.agentType) + '</span>';
            button.addEventListener('click', () => {
                setActiveThread(courseId, thread.id);
                render();
            });
            threadList.appendChild(button);
        });
    }

    function renderEmptyState(title, detail) {
        messagesNode.innerHTML = '';
        const empty = document.createElement('div');
        empty.className = 'local-astusse-chatapp-empty';
        empty.innerHTML = '<strong>' + escapeHtml(title) + '</strong><p>' + escapeHtml(detail) + '</p>';
        messagesNode.appendChild(empty);
    }

    function renderMessage(message) {
        const article = document.createElement('article');
        article.className = 'local-astusse-chatapp-message is-' + message.role +
            (message.pending ? ' is-pending' : '') +
            (message.error ? ' is-error' : '');

        const role = document.createElement('span');
        role.className = 'local-astusse-chatapp-message-role';
        role.textContent = message.role === 'assistant' ? config.strings.assistantLabel : config.strings.studentLabel;
        article.appendChild(role);

        const bubble = document.createElement('div');
        bubble.className = 'local-astusse-chatapp-bubble';
        bubble.innerHTML = escapeHtml(message.text).replace(/\n/g, '<br>');
        article.appendChild(bubble);

        const hasMetadata = !!(message.traceId || message.sessionId || message.agentUsed);
        if (hasMetadata && message.role === 'assistant') {
            const details = document.createElement('details');
            details.className = 'local-astusse-chatapp-details';
            const summary = document.createElement('summary');
            summary.textContent = config.strings.technicalDetailsLabel;
            details.appendChild(summary);

            const lines = [];
            if (message.traceId) {
                lines.push(config.strings.traceIdLabel + ': ' + message.traceId);
            }
            if (message.sessionId) {
                lines.push(config.strings.sessionIdLabel + ': ' + message.sessionId);
            }
            if (message.agentUsed) {
                lines.push(config.strings.agentUsedLabel + ': ' + message.agentUsed);
            }

            const body = document.createElement('div');
            body.className = 'local-astusse-chatapp-details-body';
            body.textContent = lines.join(' | ');
            details.appendChild(body);
            article.appendChild(details);
        }

        return article;
    }

    function renderMessages() {
        const courseId = getSelectedCourseId();
        if (!courseId) {
            renderEmptyState(config.strings.noCourseTitle, config.strings.noCourseDetail);
            return;
        }
        const thread = getActiveThread(courseId);
        if (!thread || !thread.messages.length) {
            renderEmptyState(config.strings.empty, config.strings.emptyDetail);
            return;
        }

        messagesNode.innerHTML = '';
        thread.messages.forEach((message) => {
            messagesNode.appendChild(renderMessage(message));
        });
        messagesNode.scrollTop = messagesNode.scrollHeight;
    }

    function syncAvailability() {
        const hasCourse = !!getSelectedCourseId() && courseMap.has(getSelectedCourseId());
        const disabled = !hasCourse || isSending;
        sendButton.disabled = disabled;
        newButton.disabled = disabled;
    }

    function syncAgentSelect() {
        const courseId = getSelectedCourseId();
        const thread = courseId ? getActiveThread(courseId) : null;
            agentSelect.value = thread ? normalizeAgentType(thread.agentType) : normalizeAgentType((config.defaults && config.defaults.agentType) || 'explicatif');
    }

    function render() {
        syncCourseOptions();
        renderCourseContext();
        renderThreadList();
        renderMessages();
        syncAgentSelect();
        syncAvailability();
    }

    function updateThread(courseId, thread) {
        const threads = getThreads(courseId);
        const index = threads.findIndex((candidate) => candidate.id === thread.id);
        if (index === -1) {
            threads.push(thread);
        } else {
            threads[index] = thread;
        }
        saveState();
    }

    async function sendMessage() {
        if (isSending) {
            return;
        }
        const courseId = getSelectedCourseId();
        if (!courseId || !courseMap.has(courseId)) {
            setStatus(config.strings.courseRequired, 'error');
            courseSelect.focus();
            return;
        }

        const message = String(messageInput.value || '').trim();
        if (!message) {
            messageInput.focus();
            return;
        }

        let thread = getActiveThread(courseId);
        if (!thread) {
            thread = createThread(courseId);
        }

        thread.agentType = normalizeAgentType(agentSelect.value || thread.agentType || 'explicatif');
        thread.updatedAt = Date.now();

        const userMessage = {
            id: createId('msg'),
            role: 'user',
            text: message,
            pending: false,
            error: false,
            traceId: '',
            sessionId: '',
            agentUsed: ''
        };
        const pendingMessage = {
            id: createId('msg'),
            role: 'assistant',
            text: config.strings.pending,
            pending: true,
            error: false,
            traceId: '',
            sessionId: '',
            agentUsed: ''
        };

        thread.messages.push(userMessage, pendingMessage);
        if (!thread.title || thread.title === config.strings.untitledConversation) {
            thread.title = deriveTitle(message);
        }
        updateThread(courseId, thread);
        setActiveThread(courseId, thread.id);
        messageInput.value = '';
        isSending = true;
        setStatus(config.strings.loading, 'busy');
        render();

        const formData = new URLSearchParams();
        formData.set('sesskey', config.sesskey);
        formData.set('courseid', courseId);
        formData.set('message', message);
        formData.set('agenttype', thread.agentType);
        formData.set('sessionid', thread.sessionId);

        try {
            const response = await fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData.toString()
            });

            const raw = await response.text();
            let payload = null;
            try {
                payload = JSON.parse(raw);
            } catch (error) {
                throw new Error(config.strings.invalidJson + ' ' + raw.slice(0, 200));
            }

            if (!response.ok || !payload.ok) {
                throw new Error((payload && payload.message) || config.strings.genericError);
            }

            const activeThread = getThreadById(courseId, thread.id) || thread;
            const target = activeThread.messages.find((item) => item.id === pendingMessage.id);
            if (target) {
                target.text = String(payload.assistantMessage || '');
                target.pending = false;
                target.traceId = String(payload.traceId || '');
                target.sessionId = String(payload.sessionId || activeThread.sessionId || '');
                target.agentUsed = String(payload.agentUsed || activeThread.agentType || '');
            }
            activeThread.sessionId = String(payload.sessionId || activeThread.sessionId || thread.sessionId);
            activeThread.agentType = normalizeAgentType(payload.agentUsed || activeThread.agentType || thread.agentType);
            activeThread.updatedAt = Date.now();
            updateThread(courseId, activeThread);
            setStatus(config.strings.ready, '');
        } catch (error) {
            const activeThread = getThreadById(courseId, thread.id) || thread;
            const target = activeThread.messages.find((item) => item.id === pendingMessage.id);
            if (target) {
                target.text = String((error && error.message) || config.strings.genericError);
                target.pending = false;
                target.error = true;
            }
            activeThread.updatedAt = Date.now();
            updateThread(courseId, activeThread);
            setStatus(String((error && error.message) || config.strings.genericError), 'error');
        } finally {
            isSending = false;
            render();
            messageInput.focus();
        }
    }

    newButton.addEventListener('click', () => {
        const courseId = getSelectedCourseId();
        if (!courseId || !courseMap.has(courseId)) {
            setStatus(config.strings.courseRequired, 'error');
            courseSelect.focus();
            return;
        }
        createThread(courseId);
        setStatus(config.strings.ready, '');
        render();
        messageInput.focus();
    });

    courseSelect.addEventListener('change', () => {
        setSelectedCourseId(courseSelect.value);
        setStatus(config.strings.ready, '');
        render();
    });

    agentSelect.addEventListener('change', () => {
        const courseId = getSelectedCourseId();
        const thread = courseId ? getActiveThread(courseId) : null;
        if (thread) {
            thread.agentType = normalizeAgentType(agentSelect.value || thread.agentType);
            thread.updatedAt = Date.now();
            updateThread(courseId, thread);
            renderThreadList();
        }
    });

    sendButton.addEventListener('click', sendMessage);
    messageInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });

    render();
})();
</script>
<?php
}
