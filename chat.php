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
 * Learner chat page for local_astusse.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('AJAX_SCRIPT') && isset($_REQUEST['action']) && $_REQUEST['action'] === 'send') {
    define('AJAX_SCRIPT', true);
}

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

/**
 * Return JSON encoding flags used by the chat AJAX endpoint.
 *
 * The LLM can return non-ASCII text and occasionally malformed byte sequences.
 * We substitute invalid UTF-8 bytes instead of returning an empty response body.
 *
 * @return int
 */
function local_astusse_chat_json_flags(): int {
    return JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
}

/**
 * Send a JSON response for the chat AJAX endpoint and terminate execution.
 *
 * @param array $payload
 * @param int $statuscode
 * @return void
 */
function local_astusse_chat_send_json(array $payload, int $statuscode = 200): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statuscode);
    }

    $json = json_encode($payload, local_astusse_chat_json_flags());
    if ($json === false) {
        error_log('local_astusse chat JSON encoding failed: ' . json_last_error_msg());
        $json = '{"ok":false,"message":"Unexpected JSON encoding error."}';
    }

    echo $json;
    exit;
}

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$coursecontext = context_course::instance($course->id);

require_login($course);
require_capability('local/astusse:usechat', $coursecontext);

$referencestatus = \local_astusse\reference_trainer_service::get_status($courseid);
$referencetrainerid = $referencestatus['state'] === 'valid' ? (string)$referencestatus['trainerid'] : null;
$showreferencecontext = has_capability('local/astusse:managereferencetrainer', $coursecontext);

$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'send') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        local_astusse_chat_send_json([
            'ok' => false,
            'message' => get_string('chat:error_invalid_request', 'local_astusse'),
        ], 405);
    }

    $sesskey = optional_param('sesskey', '', PARAM_RAW);
    if ($sesskey === '' || !confirm_sesskey($sesskey)) {
        local_astusse_chat_send_json([
            'ok' => false,
            'message' => get_string('chat:error_invalid_sesskey', 'local_astusse'),
        ], 400);
    }

    $message = trim((string)optional_param('message', '', PARAM_RAW));
    $agenttype = trim((string)optional_param('agenttype', '', PARAM_ALPHA));
    $sessionid = trim((string)optional_param('sessionid', '', PARAM_ALPHANUMEXT));

    if ($message === '') {
        local_astusse_chat_send_json([
            'ok' => false,
            'message' => get_string('chat:error_message_required', 'local_astusse'),
        ], 400);
    }

    if (!in_array($agenttype, ['explicatif', 'socratique'], true)) {
        local_astusse_chat_send_json([
            'ok' => false,
            'message' => get_string('chat:error_agent_invalid', 'local_astusse'),
        ], 400);
    }

    if ($sessionid === '') {
        local_astusse_chat_send_json([
            'ok' => false,
            'message' => get_string('chat:error_session_required', 'local_astusse'),
        ], 400);
    }

    try {
        global $USER;

        $client = new \local_astusse\api_client();
        $response = $client->send_message_for_user(
            $USER,
            $message,
            $agenttype,
            $sessionid,
            (string)$courseid,
            $referencetrainerid
        );

        $bodyjson = $response['body_json'] ?? null;
        $assistantmessage = '';
        if (is_array($bodyjson) && !empty($bodyjson['echo'])) {
            $assistantmessage = (string)$bodyjson['echo'];
        } else if (is_array($bodyjson) && !empty($bodyjson['response'])) {
            $assistantmessage = (string)$bodyjson['response'];
        }

        if ($assistantmessage === '') {
            throw new moodle_exception('chat:error_backend', 'local_astusse');
        }

        local_astusse_chat_send_json([
            'ok' => true,
            'status' => is_array($bodyjson) ? (string)($bodyjson['status'] ?? 'OK') : 'OK',
            'assistantMessage' => $assistantmessage,
            'sessionId' => is_array($bodyjson) ? (string)($bodyjson['sessionId'] ?? $sessionid) : $sessionid,
            'traceId' => is_array($bodyjson) ? (string)($bodyjson['traceId'] ?? '') : '',
            'agentUsed' => is_array($bodyjson) ? (string)($bodyjson['agentUsed'] ?? $agenttype) : $agenttype,
        ]);
    } catch (\Throwable $e) {
        error_log('local_astusse chat send failed: ' . get_class($e) . ' - ' . $e->getMessage());

        $errormessage = trim((string)$e->getMessage());
        if ($e instanceof moodle_exception && $errormessage !== '') {
            $errormessage = $e->getMessage();
        }

        if ($errormessage === '') {
            $errormessage = get_string('chat:error_generic', 'local_astusse');
        }

        local_astusse_chat_send_json([
            'ok' => false,
            'message' => $errormessage,
        ], 502);
    }
}

$PAGE->set_context($coursecontext);
$PAGE->set_url(new moodle_url('/local/astusse/chat.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('chat:title', 'local_astusse'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/astusse/styles.css'));

$referencestateclass = 'is-' . preg_replace('/[^a-z]/', '', (string)$referencestatus['state']);

$chatconfig = [
    'endpoint' => (new moodle_url('/local/astusse/chat.php', [
        'courseid' => $courseid,
        'action' => 'send',
    ]))->out(false),
    'sesskey' => sesskey(),
    'courseId' => (string)$courseid,
    'trainerId' => $referencetrainerid,
    'storageKey' => 'local_astusse_chat_state_' . $courseid,
    'sessionKey' => 'local_astusse_chat_session_' . $courseid,
    'defaults' => [
        'agentType' => 'explicatif',
    ],
    'labels' => [
        'agents' => [
            'explicatif' => get_string('chat:agent_explicatif', 'local_astusse'),
            'socratique' => get_string('chat:agent_socratique', 'local_astusse'),
        ],
    ],
    'strings' => [
        'referenceTrainerContext' => $referencestatus['state'] === 'valid'
            ? get_string('chat:reference_trainer_context', 'local_astusse', fullname($referencestatus['user']))
            : ($referencestatus['state'] === 'invalid'
                ? get_string('chat:reference_trainer_invalid', 'local_astusse')
                : get_string('chat:reference_trainer_missing', 'local_astusse')),
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
        'summaryNone' => get_string('chat:summary_none', 'local_astusse'),
        'pending' => get_string('chat:pending_label', 'local_astusse'),
        'invalidJson' => get_string('chat:error_invalid_json', 'local_astusse'),
    ],
];

echo $OUTPUT->header();
?>
<div class="local-astusse-chat-layout">
    <section class="local-astusse-chat-panel">
        <div class="local-astusse-chat-header">
            <div class="local-astusse-chat-hero">
                <div class="local-astusse-chat-hero-copy">
                    <span class="local-astusse-chat-kicker">ASTUSSE</span>
                    <h2><?php echo s(get_string('chat:heading', 'local_astusse')); ?></h2>
                    <p class="local-astusse-chat-intro"><?php echo s(get_string('chat:intro', 'local_astusse')); ?></p>
                </div>
                <div class="local-astusse-chat-hero-actions">
                    <span id="local-astusse-chat-status" class="local-astusse-chat-status">
                        <?php echo s(get_string('chat:status_ready', 'local_astusse')); ?>
                    </span>
                    <button type="button" id="local-astusse-chat-reset" class="btn btn-secondary local-astusse-chat-reset">
                        <?php echo s(get_string('chat:new_session_button', 'local_astusse')); ?>
                    </button>
                </div>
            </div>

            <div class="local-astusse-chat-context-grid<?php echo $showreferencecontext ? '' : ' is-single'; ?>">
                <div class="local-astusse-chat-context-card">
                    <div class="local-astusse-chat-context-value">
                        <?php echo s(get_string('chat:course_context', 'local_astusse', format_string($course->fullname))); ?>
                    </div>
                </div>
                <?php if ($showreferencecontext) { ?>
                    <div class="local-astusse-chat-context-card <?php echo s($referencestateclass); ?>">
                        <div class="local-astusse-chat-context-value">
                            <?php
                            if ($referencestatus['state'] === 'valid') {
                                echo s(get_string('chat:reference_trainer_context', 'local_astusse', fullname($referencestatus['user'])));
                            } else if ($referencestatus['state'] === 'invalid') {
                                echo s(get_string('chat:reference_trainer_invalid', 'local_astusse'));
                            } else {
                                echo s(get_string('chat:reference_trainer_missing', 'local_astusse'));
                            }
                            ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="local-astusse-chat-summary" aria-label="<?php echo s(get_string('chat:summary_aria', 'local_astusse')); ?>">
                <div class="local-astusse-chat-summary-card">
                    <span class="local-astusse-chat-summary-label"><?php echo s(get_string('chat:summary_mode', 'local_astusse')); ?></span>
                    <strong id="local-astusse-chat-summary-agent"><?php echo s(get_string('chat:agent_explicatif', 'local_astusse')); ?></strong>
                </div>
                <div class="local-astusse-chat-summary-card">
                    <span class="local-astusse-chat-summary-label"><?php echo s(get_string('chat:summary_messages', 'local_astusse')); ?></span>
                    <strong id="local-astusse-chat-summary-count">0</strong>
                </div>
                <div class="local-astusse-chat-summary-card">
                    <span class="local-astusse-chat-summary-label"><?php echo s(get_string('chat:summary_session', 'local_astusse')); ?></span>
                    <strong id="local-astusse-chat-summary-session">...</strong>
                </div>
            </div>
        </div>

        <div id="local-astusse-chat-messages" class="local-astusse-chat-messages" aria-live="polite">
            <div class="local-astusse-chat-empty" id="local-astusse-chat-empty">
                <strong class="local-astusse-chat-empty-title"><?php echo s(get_string('chat:empty_state', 'local_astusse')); ?></strong>
                <p class="local-astusse-chat-empty-detail"><?php echo s(get_string('chat:empty_state_detail', 'local_astusse')); ?></p>
            </div>
        </div>
    </section>

    <aside class="local-astusse-chat-sidebar">
        <form id="local-astusse-chat-form" class="local-astusse-chat-form">
            <div class="local-astusse-chat-sidebar-header">
                <span class="local-astusse-chat-sidebar-kicker"><?php echo s(get_string('chat:agent_label', 'local_astusse')); ?></span>
                <p class="local-astusse-chat-sidebar-text"><?php echo s(get_string('chat:history_notice', 'local_astusse')); ?></p>
            </div>

            <fieldset class="local-astusse-chat-fieldset">
                <legend class="local-astusse-chat-legend"><?php echo s(get_string('chat:agent_label', 'local_astusse')); ?></legend>
                <div class="local-astusse-chat-agent-options">
                    <label class="local-astusse-chat-option">
                        <input type="radio" name="agenttype" value="explicatif" checked>
                        <span><?php echo s(get_string('chat:agent_explicatif', 'local_astusse')); ?></span>
                    </label>
                    <label class="local-astusse-chat-option">
                        <input type="radio" name="agenttype" value="socratique">
                        <span><?php echo s(get_string('chat:agent_socratique', 'local_astusse')); ?></span>
                    </label>
                </div>
            </fieldset>

            <div>
                <label class="local-astusse-chat-label" for="local-astusse-chat-message">
                    <?php echo s(get_string('chat:message_label', 'local_astusse')); ?>
                </label>
                <textarea
                    id="local-astusse-chat-message"
                    class="local-astusse-chat-message-input"
                    name="message"
                    placeholder="<?php echo s(get_string('chat:message_placeholder', 'local_astusse')); ?>"
                    maxlength="10000"
                    required
                ></textarea>
                <div class="local-astusse-chat-hint">
                    <?php echo s(get_string('chat:input_hint', 'local_astusse')); ?>
                </div>
            </div>

            <div class="local-astusse-chat-actions">
                <button type="submit" class="btn btn-primary local-astusse-chat-send">
                    <?php echo s(get_string('chat:send_button', 'local_astusse')); ?>
                </button>
            </div>
        </form>
    </aside>
</div>

<script>
(function() {
    const config = <?php echo json_encode($chatconfig, JSON_UNESCAPED_SLASHES); ?>;
    const form = document.getElementById('local-astusse-chat-form');
    const messageInput = document.getElementById('local-astusse-chat-message');
    const messagesNode = document.getElementById('local-astusse-chat-messages');
    const emptyNode = document.getElementById('local-astusse-chat-empty');
    const statusNode = document.getElementById('local-astusse-chat-status');
    const resetButton = document.getElementById('local-astusse-chat-reset');
    const submitButton = form.querySelector('button[type="submit"]');
    const summaryAgentNode = document.getElementById('local-astusse-chat-summary-agent');
    const summaryCountNode = document.getElementById('local-astusse-chat-summary-count');
    const summarySessionNode = document.getElementById('local-astusse-chat-summary-session');

    function defaultState() {
        return {
            messages: [],
            agentType: config.defaults.agentType
        };
    }

    function generateSessionId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'astusse-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    }

    function normalizeState(value) {
        const fallback = defaultState();
        return {
            messages: Array.isArray(value && value.messages) ? value.messages : [],
            agentType: value && value.agentType ? value.agentType : fallback.agentType
        };
    }

    function readState() {
        try {
            const raw = window.sessionStorage.getItem(config.storageKey);
            if (!raw) {
                return defaultState();
            }
            return normalizeState(JSON.parse(raw));
        } catch (error) {
            return defaultState();
        }
    }

    function saveState(state) {
        window.sessionStorage.setItem(config.storageKey, JSON.stringify(state));
    }

    function getSessionId() {
        let sessionId = window.sessionStorage.getItem(config.sessionKey);
        if (!sessionId) {
            sessionId = generateSessionId();
            window.sessionStorage.setItem(config.sessionKey, sessionId);
        }
        return sessionId;
    }

    function shortenSessionId(sessionId) {
        if (!sessionId) {
            return config.strings.summaryNone;
        }
        if (sessionId.length <= 18) {
            return sessionId;
        }
        return sessionId.slice(0, 8) + '...' + sessionId.slice(-6);
    }

    function getSelectedAgentType() {
        const selected = form.querySelector('input[name="agenttype"]:checked');
        return selected ? selected.value : config.defaults.agentType;
    }

    function formatAgentLabel(agent) {
        return config.labels.agents[agent] || agent || config.strings.summaryNone;
    }

    function refreshSummary(state) {
        summaryAgentNode.textContent = formatAgentLabel(state.agentType);
        summaryCountNode.textContent = String(state.messages.length);
        summarySessionNode.textContent = shortenSessionId(getSessionId());
    }

    function setStatus(text, isLoading) {
        statusNode.textContent = text;
        statusNode.classList.toggle('is-loading', !!isLoading);
    }

    function renderMessage(message) {
        const wrapper = document.createElement('div');
        wrapper.className = 'local-astusse-message';
        wrapper.dataset.role = message.role;
        if (message.pending) {
            wrapper.classList.add('is-pending');
        }
        if (message.error) {
            wrapper.classList.add('is-error');
        }

        const label = document.createElement('div');
        label.className = 'local-astusse-message-label';
        label.textContent = message.role === 'assistant'
            ? config.strings.assistantLabel
            : config.strings.studentLabel;

        const body = document.createElement('div');
        body.className = 'local-astusse-message-body';
        body.textContent = message.text;

        wrapper.appendChild(label);
        wrapper.appendChild(body);

        if (message.meta && message.meta.agentUsed) {
            const pills = document.createElement('div');
            pills.className = 'local-astusse-message-pills';

            if (message.meta.agentUsed) {
                const agentPill = document.createElement('span');
                agentPill.className = 'local-astusse-message-pill';
                agentPill.textContent = config.strings.agentUsedLabel + ': ' + formatAgentLabel(message.meta.agentUsed);
                pills.appendChild(agentPill);
            }

            if (pills.childNodes.length) {
                wrapper.appendChild(pills);
            }
        }

        if (message.role === 'assistant' && message.meta) {
            const parts = [];
            if (message.meta.traceId) {
                parts.push(config.strings.traceIdLabel + ': ' + message.meta.traceId);
            }
            if (message.meta.sessionId) {
                parts.push(config.strings.sessionIdLabel + ': ' + message.meta.sessionId);
            }
            if (parts.length) {
                const details = document.createElement('details');
                details.className = 'local-astusse-message-details';

                const summary = document.createElement('summary');
                summary.className = 'local-astusse-message-details-summary';
                summary.textContent = config.strings.technicalDetailsLabel;

                const meta = document.createElement('div');
                meta.className = 'local-astusse-chat-meta local-astusse-chat-meta-technical';
                meta.textContent = parts.join(' | ');

                details.appendChild(summary);
                details.appendChild(meta);
                wrapper.appendChild(details);
            }
        }

        messagesNode.appendChild(wrapper);
        return wrapper;
    }

    function renderMessages(messages) {
        messagesNode.querySelectorAll('.local-astusse-message').forEach(node => node.remove());
        if (!messages.length) {
            emptyNode.hidden = false;
            refreshSummary(readState());
            return;
        }
        emptyNode.hidden = true;
        messages.forEach(renderMessage);
        messagesNode.scrollTop = messagesNode.scrollHeight;
        refreshSummary(readState());
    }

    function applyState(state) {
        form.querySelectorAll('input[name="agenttype"]').forEach(input => {
            input.checked = input.value === state.agentType;
        });
        renderMessages(state.messages);
    }

    function persistSettings() {
        const state = readState();
        state.agentType = getSelectedAgentType();
        saveState(state);
        refreshSummary(state);
        return state;
    }

    function pushMessage(message) {
        const state = readState();
        state.agentType = getSelectedAgentType();
        state.messages.push(message);
        saveState(state);
        renderMessages(state.messages);
    }

    function renderPendingAssistant() {
        emptyNode.hidden = true;
        const pendingNode = renderMessage({
            role: 'assistant',
            text: config.strings.pending,
            pending: true
        });
        messagesNode.scrollTop = messagesNode.scrollHeight;
        return pendingNode;
    }

    function replacePendingAssistant(pendingNode, message) {
        if (pendingNode && pendingNode.parentNode) {
            pendingNode.parentNode.removeChild(pendingNode);
        }
        pushMessage(message);
    }

    function resetSession() {
        const newSessionId = generateSessionId();
        window.sessionStorage.setItem(config.sessionKey, newSessionId);
        window.sessionStorage.removeItem(config.storageKey);
        messageInput.value = '';
        applyState(readState());
        setStatus(config.strings.ready, false);
        messageInput.focus();
    }

    async function sendMessage(event) {
        event.preventDefault();

        const message = messageInput.value.trim();
        if (!message) {
            setStatus(config.strings.genericError, false);
            messageInput.focus();
            return;
        }

        persistSettings();
        const sessionId = getSessionId();
        const selectedAgent = getSelectedAgentType();

        pushMessage({role: 'student', text: message});
        messageInput.value = '';

        const formData = new URLSearchParams();
        formData.set('sesskey', config.sesskey);
        formData.set('message', message);
        formData.set('agenttype', selectedAgent);
        formData.set('sessionid', sessionId);

        submitButton.disabled = true;
        resetButton.disabled = true;
        setStatus(config.strings.loading, true);
        const pendingNode = renderPendingAssistant();

        try {
            const response = await fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: formData.toString()
            });

            const rawResponse = await response.text();
            let payload = null;
            try {
                payload = JSON.parse(rawResponse);
            } catch (parseError) {
                console.error('local_astusse invalid JSON response', rawResponse);
                const preview = rawResponse
                    ? rawResponse.replace(/\s+/g, ' ').trim().slice(0, 220)
                    : '';
                throw new Error(
                    preview
                        ? config.strings.invalidJson + ' ' + preview
                        : config.strings.invalidJson
                );
            }

            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || config.strings.genericError);
            }

            if (payload.sessionId) {
                window.sessionStorage.setItem(config.sessionKey, payload.sessionId);
            }

            replacePendingAssistant(pendingNode, {
                role: 'assistant',
                text: payload.assistantMessage,
                meta: {
                    traceId: payload.traceId || '',
                    sessionId: payload.sessionId || sessionId,
                    agentUsed: payload.agentUsed || selectedAgent
                }
            });
            setStatus(config.strings.ready, false);
            messageInput.focus();
        } catch (error) {
            replacePendingAssistant(pendingNode, {
                role: 'assistant',
                text: error && error.message ? error.message : config.strings.genericError,
                error: true,
                meta: {
                    sessionId: getSessionId()
                }
            });
            setStatus(error && error.message ? error.message : config.strings.genericError, false);
            messageInput.focus();
        } finally {
            submitButton.disabled = false;
            resetButton.disabled = false;
        }
    }

    form.querySelectorAll('input[name="agenttype"]').forEach(input => {
        input.addEventListener('change', persistSettings);
    });

    messageInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }
            form.dispatchEvent(new Event('submit', {cancelable: true}));
        }
    });

    resetButton.addEventListener('click', resetSession);
    form.addEventListener('submit', sendMessage);
    getSessionId();
    applyState(readState());
    persistSettings();
})();
</script>
<?php
echo $OUTPUT->footer();
