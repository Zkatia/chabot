/**
 * ASTUSSE chat application.
 * Config is injected by chat_ui.php via window.AstusseChatConfig.
 */
(function () {
    const config = window.AstusseChatConfig || {};

    const mdRenderer = new marked.Renderer();
    mdRenderer.link = (href, title, text) =>
        `<a href="${href}" target="_blank" rel="noopener noreferrer"${title ? ` title="${title}"` : ''}>${text}</a>`;
    marked.setOptions({ renderer: mdRenderer, gfm: true, breaks: true });

    function renderMarkdown(text) {
        try {
            return marked.parse(String(text || ''));
        } catch (e) {
            return escapeHtml(text).replace(/\n/g, '<br>');
        }
    }

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
    const syncingCourses = new Map();
    const syncingSessions = new Map();
    const deletingThreads = new Set();

    // ── Sidebar toggle ──
    const sidebar = document.getElementById('local-astusse-chatapp-sidebar');
    const sidebarOpen = document.getElementById('local-astusse-chatapp-sidebar-open');
    const sidebarClose = document.getElementById('local-astusse-chatapp-sidebar-close');
    function updateSidebarToggleVisibility() {
        if (sidebarOpen) {
            sidebarOpen.style.display = sidebar && sidebar.classList.contains('is-collapsed') ? 'flex' : 'none';
        }
    }
    if (sidebar && sidebarOpen) {
        sidebarOpen.addEventListener('click', function () {
            sidebar.classList.remove('is-collapsed');
            updateSidebarToggleVisibility();
        });
    }
    if (sidebar && sidebarClose) {
        sidebarClose.addEventListener('click', function () {
            sidebar.classList.add('is-collapsed');
            updateSidebarToggleVisibility();
        });
    }
    updateSidebarToggleVisibility();

    // ── Auto-resize textarea ──
    if (messageInput) {
        messageInput.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 160) + 'px';
        });
    }

    // T3 etape 7 : pre-remplit le textarea si on arrive depuis "Demander au tuteur"
    // (chat.php a injecte draftMessage dans config.defaults).
    if (messageInput && config.defaults && config.defaults.draftMessage) {
        messageInput.value = config.defaults.draftMessage;
        // Trigger auto-resize sur le contenu pre-rempli.
        messageInput.dispatchEvent(new Event('input', { bubbles: true }));
        // Focus en fin de saisie pour que l'apprenant puisse editer / completer.
        try {
            messageInput.focus();
            messageInput.setSelectionRange(messageInput.value.length, messageInput.value.length);
        } catch (e) {
            // setSelectionRange peut throw sur certains navigateurs anciens : non bloquant.
        }
    }

    function createSessionId() {
        return 'astusse-' + Date.now() + '-' + Math.random().toString(16).slice(2, 10);
    }

    function createId(prefix) {
        return prefix + '-' + Date.now() + '-' + Math.random().toString(16).slice(2, 8);
    }

    function normalizeAgentType(value) {
        return value === 'socratique' ? 'socratique' : 'explicatif';
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function defaultState() {
        return {
            selectedCourseId: config.lockedCourse ? String(config.selectedCourseId || '') : '',
            activeThreadIdByCourse: {},
            threadsByCourse: {}
        };
    }

    function normalizeMessage(message, defaults) {
        defaults = defaults || {};
        return {
            id: String(message.id || createId('msg')),
            role: message.role === 'assistant' ? 'assistant' : 'user',
            text: String(message.text || ''),
            pending: !!message.pending,
            error: !!message.error,
            traceId: String(message.traceId || defaults.traceId || ''),
            sessionId: String(message.sessionId || defaults.sessionId || ''),
            agentUsed: String(message.agentUsed || defaults.agentUsed || '')
        };
    }

    function normalizeThread(thread, courseId) {
        const normalizedCourseId = String(thread.courseId || courseId || '');
        const sessionId = String(thread.sessionId || createSessionId());
        const agentType = normalizeAgentType(thread.agentType || thread.agentUsed);

        return {
            id: String(thread.id || createId('conv')),
            courseId: normalizedCourseId,
            sessionId: sessionId,
            title: String(thread.title || config.strings.untitledConversation),
            agentType: agentType,
            updatedAt: Number(thread.updatedAt || Date.now()),
            backendBacked: !!thread.backendBacked,
            historyLoadedAt: Number(thread.historyLoadedAt || 0),
            messages: Array.isArray(thread.messages) ? thread.messages.map((message) => normalizeMessage(message, {
                sessionId: sessionId,
                agentUsed: agentType
            })) : []
        };
    }

    function clearLegacyLocalCache() {
        const legacyKey = String(config.storageKey || 'local_astusse_chat_threads_v4');
        try {
            window.localStorage.removeItem(legacyKey);
        } catch (e) {
        }
    }

    let state = defaultState();
    clearLegacyLocalCache();

    function getSelectedCourseId() {
        return config.lockedCourse ? String(config.selectedCourseId || '') : String(state.selectedCourseId || '');
    }

    function setSelectedCourseId(courseId) {
        if (!config.lockedCourse) {
            state.selectedCourseId = String(courseId || '');
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
        return getThreads(courseId).slice().sort((left, right) => right.updatedAt - left.updatedAt);
    }

    function getThreadById(courseId, threadId) {
        return getThreads(courseId).find((thread) => thread.id === String(threadId)) || null;
    }

    function getThreadBySessionId(courseId, sessionId) {
        return getThreads(courseId).find((thread) => thread.sessionId === String(sessionId)) || null;
    }

    function getActiveThread(courseId) {
        const key = String(courseId || '');
        const activeId = state.activeThreadIdByCourse[key] || '';
        const active = activeId ? getThreadById(key, activeId) : null;
        if (active) {
            return active;
        }
        const sorted = getSortedThreads(key);
        if (!sorted.length) {
            return null;
        }
        state.activeThreadIdByCourse[key] = sorted[0].id;
        return sorted[0];
    }

    function setActiveThread(courseId, threadId) {
        state.activeThreadIdByCourse[String(courseId)] = String(threadId);
    }

    function updateThread(courseId, thread) {
        const threads = getThreads(courseId);
        const index = threads.findIndex((candidate) => candidate.id === thread.id);
        if (index === -1) {
            threads.push(thread);
        } else {
            threads[index] = thread;
        }
    }

    function deleteThreadLocal(courseId, threadId) {
        const key = String(courseId || '');
        const threads = getThreads(key);
        const index = threads.findIndex((thread) => thread.id === String(threadId));
        if (index === -1) {
            return false;
        }

        threads.splice(index, 1);
        if (state.activeThreadIdByCourse[key] === String(threadId)) {
            const nextThread = getSortedThreads(key)[0] || null;
            if (nextThread) {
                state.activeThreadIdByCourse[key] = nextThread.id;
            } else {
                delete state.activeThreadIdByCourse[key];
            }
        }
        return true;
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
        const sessionId = createSessionId();
        const thread = normalizeThread({
            id: createId('conv'),
            courseId: key,
            sessionId: sessionId,
            title: config.strings.untitledConversation,
            agentType: agentSelect.value || (config.defaults && config.defaults.agentType) || 'explicatif',
            updatedAt: Date.now(),
            backendBacked: false,
            historyLoadedAt: 0,
            messages: []
        }, key);
        getThreads(key).push(thread);
        state.activeThreadIdByCourse[key] = thread.id;
        return thread;
    }

    function mergeBackendSessions(courseId, sessions) {
        const key = String(courseId || '');
        const threads = getThreads(key);
        const seenSessionIds = new Set();

        (sessions || []).forEach((session) => {
            const sessionId = String(session.sessionId || '');
            if (!sessionId) {
                return;
            }
            seenSessionIds.add(sessionId);
            const existing = getThreadBySessionId(key, sessionId);
            const merged = normalizeThread({
                id: existing ? existing.id : createId('conv'),
                courseId: key,
                sessionId: sessionId,
                title: session.title || (existing && existing.title) || config.strings.untitledConversation,
                agentType: session.agentUsed || (existing && existing.agentType) || 'explicatif',
                updatedAt: Number(session.updatedAt || (existing && existing.updatedAt) || Date.now()),
                backendBacked: true,
                historyLoadedAt: existing ? existing.historyLoadedAt : 0,
                messages: existing ? existing.messages : []
            }, key);
            updateThread(key, merged);
        });

        state.threadsByCourse[key] = threads.filter((thread) => {
            if (!thread.backendBacked) {
                return true;
            }
            return seenSessionIds.has(thread.sessionId);
        });

        const activeThread = getActiveThread(key);
        if (!activeThread && state.threadsByCourse[key].length) {
            state.activeThreadIdByCourse[key] = getSortedThreads(key)[0].id;
        }
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
            const item = document.createElement('div');
            item.className = 'local-astusse-chatapp-thread' + (activeThread && activeThread.id === thread.id ? ' is-active' : '');

            const openButton = document.createElement('button');
            openButton.type = 'button';
            openButton.className = 'local-astusse-chatapp-thread-main';
            openButton.innerHTML =
                '<strong>' + escapeHtml(thread.title || config.strings.untitledConversation) + '</strong>' +
                '<span>' + escapeHtml((config.labels.agents && config.labels.agents[thread.agentType]) || thread.agentType) + '</span>';
            openButton.addEventListener('click', async () => {
                setActiveThread(courseId, thread.id);
                render();
                await syncThreadHistory(courseId, thread, { silent: true });
            });

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'local-astusse-chatapp-thread-delete';
            deleteButton.textContent = config.strings.deleteConversationLabel;
            deleteButton.disabled = deletingThreads.has(thread.id);
            deleteButton.setAttribute('aria-label', config.strings.deleteConversationLabel + ': ' +
                String(thread.title || config.strings.untitledConversation));
            deleteButton.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (!window.confirm(config.strings.deleteConversationConfirm)) {
                    return;
                }
                await deleteThread(thread);
            });

            item.appendChild(openButton);
            item.appendChild(deleteButton);
            threadList.appendChild(item);
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
        article.dataset.messageId = message.id;

        const role = document.createElement('span');
        role.className = 'local-astusse-chatapp-message-role';
        role.textContent = message.role === 'assistant' ? config.strings.assistantLabel : config.strings.studentLabel;
        article.appendChild(role);

        const bubble = document.createElement('div');
        bubble.className = 'local-astusse-chatapp-bubble' + (message.role === 'assistant' ? ' is-markdown' : '');
        bubble.innerHTML = message.role === 'assistant'
            ? renderMarkdown(message.text)
            : escapeHtml(message.text).replace(/\n/g, '<br>');
        article.appendChild(bubble);

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
        thread.messages.forEach((message) => messagesNode.appendChild(renderMessage(message)));
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
        agentSelect.value = thread
            ? normalizeAgentType(thread.agentType)
            : normalizeAgentType((config.defaults && config.defaults.agentType) || 'explicatif');
    }

    function render() {
        syncCourseOptions();
        renderCourseContext();
        renderThreadList();
        renderMessages();
        syncAgentSelect();
        syncAvailability();
    }

    function updatePendingBubble(messageId, text) {
        const article = messagesNode.querySelector('[data-message-id="' + messageId + '"]');
        if (!article) {
            return;
        }
        const bubble = article.querySelector('.local-astusse-chatapp-bubble');
        if (bubble) {
            bubble.innerHTML = renderMarkdown(text);
        }
        messagesNode.scrollTop = messagesNode.scrollHeight;
    }

    async function requestJson(url, options) {
        const response = await fetch(url, Object.assign({
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }, options || {}));
        const raw = await response.text();
        let body;
        try {
            body = raw ? JSON.parse(raw) : {};
        } catch (e) {
            throw new Error(config.strings.invalidJson || config.strings.genericError);
        }
        if (!response.ok || !body.ok) {
            throw new Error(String((body && body.message) || config.strings.genericError));
        }
        return body;
    }

    function buildHistoryUrl(courseId, sessionId) {
        const params = new URLSearchParams();
        params.set('courseid', String(courseId || ''));
        if (sessionId) {
            params.set('sessionid', String(sessionId));
        }
        return (config.historyEndpoint || '') + '?' + params.toString();
    }

    async function syncSessionsForCourse(courseId, options) {
        options = options || {};
        const key = String(courseId || '');
        if (!key || !courseMap.has(key) || !config.historyEndpoint) {
            return;
        }
        if (syncingCourses.has(key)) {
            return syncingCourses.get(key);
        }

        const promise = (async () => {
            if (!options.silent) {
                setStatus(config.strings.loadingHistory, 'busy');
            }
            try {
                const body = await requestJson(buildHistoryUrl(key), { method: 'GET' });
                mergeBackendSessions(key, Array.isArray(body.sessions) ? body.sessions : []);
                render();
                if (!options.silent) {
                    setStatus(config.strings.ready, '');
                }
            } catch (error) {
                if (!options.silent) {
                    setStatus(String((error && error.message) || config.strings.historySyncFailed), 'error');
                }
            } finally {
                syncingCourses.delete(key);
            }
        })();

        syncingCourses.set(key, promise);
        return promise;
    }

    async function syncThreadHistory(courseId, thread, options) {
        options = options || {};
        const key = String(courseId || '');
        if (!thread || !thread.sessionId || !config.historyEndpoint) {
            return thread;
        }
        if (thread.historyLoadedAt && !options.force) {
            return thread;
        }
        if (syncingSessions.has(thread.sessionId)) {
            return syncingSessions.get(thread.sessionId);
        }

        const promise = (async () => {
            if (!options.silent) {
                setStatus(config.strings.loadingHistory, 'busy');
            }
            try {
                const body = await requestJson(buildHistoryUrl(key, thread.sessionId), { method: 'GET' });
                const liveThread = getThreadById(key, thread.id) || thread;
                liveThread.backendBacked = true;
                liveThread.title = String(body.title || liveThread.title || config.strings.untitledConversation);
                liveThread.agentType = normalizeAgentType(body.agentUsed || liveThread.agentType);
                liveThread.updatedAt = Number(body.updatedAt || liveThread.updatedAt || Date.now());
                liveThread.historyLoadedAt = Date.now();
                liveThread.messages = Array.isArray(body.messages) ? body.messages.map((message) => normalizeMessage({
                    role: message.role,
                    text: message.text,
                    pending: false,
                    error: false,
                    traceId: String(body.traceId || ''),
                    sessionId: String(body.sessionId || liveThread.sessionId),
                    agentUsed: String(body.agentUsed || liveThread.agentType)
                }, {
                    sessionId: String(body.sessionId || liveThread.sessionId),
                    agentUsed: String(body.agentUsed || liveThread.agentType),
                    traceId: String(body.traceId || '')
                })) : [];
                updateThread(key, liveThread);
                render();
                if (!options.silent) {
                    setStatus(config.strings.ready, '');
                }
                return liveThread;
            } catch (error) {
                if (error && typeof error.message === 'string' && error.message.indexOf('HTTP 404') !== -1) {
                    const liveThread = getThreadById(key, thread.id) || thread;
                    liveThread.backendBacked = false;
                    liveThread.historyLoadedAt = 0;
                    liveThread.messages = [];
                    updateThread(key, liveThread);
                    if (!options.silent) {
                        setStatus(config.strings.historyDeletedRemote, 'error');
                    }
                    render();
                    return liveThread;
                }
                if (!options.silent) {
                    setStatus(String((error && error.message) || config.strings.historySyncFailed), 'error');
                }
                return thread;
            } finally {
                syncingSessions.delete(thread.sessionId);
            }
        })();

        syncingSessions.set(thread.sessionId, promise);
        return promise;
    }

    async function ensureCourseReady(courseId) {
        if (!courseId || !courseMap.has(String(courseId))) {
            return;
        }
        await syncSessionsForCourse(courseId, { silent: true });
        const activeThread = getActiveThread(courseId);
        if (activeThread) {
            await syncThreadHistory(courseId, activeThread, { silent: true });
        }
        render();
    }

    async function deleteThread(thread) {
        const courseId = String(thread.courseId || getSelectedCourseId() || '');
        if (!courseId) {
            return;
        }

        deletingThreads.add(thread.id);
        renderThreadList();
        setStatus(config.strings.loadingHistory, 'busy');

        try {
            if (thread.backendBacked && thread.sessionId && config.historyEndpoint) {
                const body = new URLSearchParams();
                body.set('sesskey', config.sesskey);
                body.set('courseid', courseId);
                body.set('action', 'delete');
                body.set('sessionid', thread.sessionId);
                await requestJson(config.historyEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString()
                });
            }

            deleteThreadLocal(courseId, thread.id);
            setStatus(config.strings.deleteConversationStatus, '');
            render();
            messageInput.focus();
        } catch (error) {
            setStatus(String((error && error.message) || config.strings.genericError), 'error');
        } finally {
            deletingThreads.delete(thread.id);
            renderThreadList();
        }
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

        const userMessage = normalizeMessage({
            id: createId('msg'),
            role: 'user',
            text: message,
            pending: false,
            error: false,
            traceId: '',
            sessionId: thread.sessionId,
            agentUsed: ''
        });
        const pendingMessage = normalizeMessage({
            id: createId('msg'),
            role: 'assistant',
            text: config.strings.pending,
            pending: true,
            error: false,
            traceId: '',
            sessionId: thread.sessionId,
            agentUsed: thread.agentType
        });

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
            const response = await fetch(config.streamEndpoint || config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData.toString()
            });

            if (!response.ok) {
                const raw = await response.text();
                let errorMessage = config.strings.genericError;
                try {
                    errorMessage = JSON.parse(raw).message || errorMessage;
                } catch (e) {}
                throw new Error(errorMessage);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let accumulatedText = '';

            while (true) {
                const chunk = await reader.read();
                if (chunk.done) {
                    break;
                }
                buffer += decoder.decode(chunk.value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                for (const line of lines) {
                    if (!line.startsWith('data:')) {
                        continue;
                    }
                    const data = line.slice(5).trim();
                    if (!data) {
                        continue;
                    }

                    let event;
                    try {
                        event = JSON.parse(data);
                    } catch (e) {
                        continue;
                    }

                    if (event.type === 'token') {
                        accumulatedText += String(event.text || '');
                        const liveThread = getThreadById(courseId, thread.id) || thread;
                        const liveTarget = liveThread.messages.find((candidate) => candidate.id === pendingMessage.id);
                        if (liveTarget) {
                            liveTarget.text = accumulatedText;
                        }
                        updatePendingBubble(pendingMessage.id, accumulatedText);
                    } else if (event.type === 'done') {
                        const liveThread = getThreadById(courseId, thread.id) || thread;
                        const target = liveThread.messages.find((candidate) => candidate.id === pendingMessage.id);
                        if (target) {
                            if (accumulatedText) {
                                target.text = accumulatedText;
                            }
                            target.pending = false;
                            target.traceId = String(event.traceId || '');
                            target.sessionId = String(event.sessionId || liveThread.sessionId || '');
                            target.agentUsed = String(event.agentUsed || liveThread.agentType || '');
                        }
                        liveThread.sessionId = String(event.sessionId || liveThread.sessionId || thread.sessionId);
                        liveThread.agentType = normalizeAgentType(event.agentUsed || liveThread.agentType || thread.agentType);
                        liveThread.updatedAt = Date.now();
                        liveThread.backendBacked = true;
                        liveThread.historyLoadedAt = Date.now();
                        updateThread(courseId, liveThread);
                        setStatus(config.strings.ready, '');
                    } else if (event.type === 'error') {
                        throw new Error(String(event.message || config.strings.genericError));
                    }
                }
            }

            await syncSessionsForCourse(courseId, { silent: true });
        } catch (error) {
            const liveThread = getThreadById(courseId, thread.id) || thread;
            const target = liveThread.messages.find((candidate) => candidate.id === pendingMessage.id);
            if (target) {
                target.text = String((error && error.message) || config.strings.genericError);
                target.pending = false;
                target.error = true;
            }
            liveThread.updatedAt = Date.now();
            updateThread(courseId, liveThread);
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

    courseSelect.addEventListener('change', async () => {
        setSelectedCourseId(courseSelect.value);
        setStatus(config.strings.ready, '');
        render();
        await ensureCourseReady(getSelectedCourseId());
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
    ensureCourseReady(getSelectedCourseId());
})();
