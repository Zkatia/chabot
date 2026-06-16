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
    const appRoot = document.getElementById('local-astusse-chatapp');
    const courseButton = document.getElementById('local-astusse-chatapp-course');
    const courseLabelNode = document.getElementById('local-astusse-chatapp-course-label');
    const courseMenu = document.getElementById('local-astusse-chatapp-course-menu');
    const courseItemsNode = document.getElementById('local-astusse-chatapp-course-items');
    const courseWrap = document.getElementById('local-astusse-chatapp-course-wrap');
    const agentControl = document.getElementById('local-astusse-chatapp-agent');
    const agentButtons = agentControl ? Array.prototype.slice.call(agentControl.querySelectorAll('.la-seg')) : [];
    const messageInput = document.getElementById('local-astusse-chatapp-input');
    const sendButton = document.getElementById('local-astusse-chatapp-send');
    const newButton = document.getElementById('local-astusse-chatapp-new');
    const composerNode = document.getElementById('local-astusse-chatapp-composer');
    const statusNode = document.getElementById('local-astusse-chatapp-status');
    const statusTextNode = document.getElementById('local-astusse-chatapp-status-text');
    const themeButton = document.getElementById('local-astusse-chatapp-theme');
    const threadList = document.getElementById('local-astusse-chatapp-threadlist');
    const searchInput = document.getElementById('local-astusse-chatapp-search');
    const messagesNode = document.getElementById('local-astusse-chatapp-messages');

    // ── Charte « autoporteur » : icones inline (Lucide-style) + couleurs d'agents ──
    const SVG_OPEN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" ' +
        'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    const AGENT_ICONS = {
        explicatif: SVG_OPEN + '<path d="M12 2 4 6v6c0 5 3.4 7.7 8 10 4.6-2.3 8-5 8-10V6l-8-4z"/><path d="m9 12 2 2 4-4"/></svg>',
        socratique: SVG_OPEN + '<path d="M9.5 9a2.5 2.5 0 1 1 3 2.45c-.8.2-1.5.9-1.5 1.8V14"/><path d="M11 17.5h.01"/><circle cx="12" cy="12" r="9"/></svg>'
    };
    const AGENT_COLORS = {
        explicatif: 'var(--agent-prescriptif)',
        socratique: 'var(--agent-socratic)'
    };
    const ICON_OCTO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" ' +
        'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M6.7 13.4C6.2 8.9 8.7 6 12 6s5.8 2.9 5.3 7.4"/>' +
        '<path d="M6.9 12.9c-2.1 1.2-3.2 3.4-2.4 5 .45.9 1.6.85 1.95-.25"/>' +
        '<path d="M9.4 13.7c-1.05 1.7-1.45 4-.5 5.4.55.8 1.6.6 1.7-.55"/>' +
        '<path d="M12 14c0 2.2-.05 3.95 0 5.5"/>' +
        '<path d="M14.6 13.7c1.05 1.7 1.45 4 .5 5.4-.55.8-1.6.6-1.7-.55"/>' +
        '<path d="M17.1 12.9c2.1 1.2 3.2 3.4 2.4 5-.45.9-1.6.85-1.95-.25"/></svg>';
    const ICON_SPARK = SVG_OPEN + '<path d="M12 3l1.9 5.6a3 3 0 0 0 1.9 1.9L21.4 12l-5.6 1.9a3 3 0 0 0-1.9 1.9' +
        'L12 21.4l-1.9-5.6a3 3 0 0 0-1.9-1.9L2.6 12l5.6-1.9a3 3 0 0 0 1.9-1.9L12 3z"/></svg>';
    const ICON_COPY = SVG_OPEN + '<rect x="9" y="9" width="12" height="12" rx="2"/>' +
        '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
    const ICON_TRASH = SVG_OPEN + '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6v14a2 2 0 0 1-2 2H7' +
        'a2 2 0 0 1-2-2V6"/></svg>';
    const ICON_SUN = SVG_OPEN + '<circle cx="12" cy="12" r="4"/>' +
        '<path d="M12 2v2M12 20v2M5 5l1.4 1.4M17.6 17.6 19 19M2 12h2M20 12h2M5 19l1.4-1.4M17.6 6.4 19 5"/></svg>';
    const ICON_MOON = SVG_OPEN + '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';
    const ICON_BOOK = SVG_OPEN + '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>' +
        '<path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>';
    const ICON_CHECK = SVG_OPEN + '<path d="M20 6 9 17l-5-5"/></svg>';

    // ── Modale de confirmation charte (remplace window.confirm) ──
    // Renvoie une Promise<boolean> : true = confirmé, false = annulé.
    function astusseConfirm(opts) {
        opts = opts || {};
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'la-modal-overlay';

            const card = document.createElement('div');
            card.className = 'la-modal-card';
            card.setAttribute('role', 'alertdialog');
            card.setAttribute('aria-modal', 'true');

            const titleEl = document.createElement('h2');
            titleEl.className = 'la-modal-title';
            titleEl.textContent = opts.title || '';

            const msgEl = document.createElement('p');
            msgEl.className = 'la-modal-message';
            msgEl.textContent = opts.message || '';

            const actions = document.createElement('div');
            actions.className = 'la-modal-actions';

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'la-modal-btn la-modal-cancel';
            cancelBtn.textContent = opts.cancelLabel || 'Annuler';

            const confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.className = 'la-modal-btn la-modal-confirm' + (opts.danger ? ' is-danger' : '');
            confirmBtn.textContent = opts.confirmLabel || 'OK';

            let closed = false;
            const close = (result) => {
                if (closed) {
                    return;
                }
                closed = true;
                document.removeEventListener('keydown', onKey, true);
                overlay.remove();
                resolve(result);
            };
            const onKey = (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    close(false);
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    close(true);
                }
            };

            cancelBtn.addEventListener('click', () => close(false));
            confirmBtn.addEventListener('click', () => close(true));
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    close(false);
                }
            });
            document.addEventListener('keydown', onKey, true);

            actions.appendChild(cancelBtn);
            actions.appendChild(confirmBtn);
            card.appendChild(titleEl);
            card.appendChild(msgEl);
            card.appendChild(actions);
            overlay.appendChild(card);
            (appRoot || document.body).appendChild(overlay);
            confirmBtn.focus();
        });
    }

    function agentLabel(agentId) {
        return (config.labels && config.labels.agents && config.labels.agents[agentId]) || agentId;
    }

    function agentRole(agentId) {
        return agentId === 'socratique' ? config.strings.agentSocratiqueRole : config.strings.agentExplicatifRole;
    }

    function agentDesc(agentId) {
        return agentId === 'socratique' ? config.strings.agentSocratiqueDesc : config.strings.agentExplicatifDesc;
    }

    // ── Contrôle segmenté du mode d'agent ──
    let currentAgent = 'explicatif';

    function getAgentValue() {
        return currentAgent;
    }

    function setAgentValue(value) {
        currentAgent = normalizeAgentType(value);
        agentButtons.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.agent === currentAgent);
        });
    }

    // ── Thème clair/sombre persistant ──
    const THEME_STORAGE_KEY = 'local_astusse_chat_theme';

    function applyTheme(theme) {
        const next = theme === 'dark' ? 'dark' : 'light';
        if (appRoot) {
            appRoot.setAttribute('data-theme', next);
        }
        if (themeButton) {
            themeButton.innerHTML = next === 'dark' ? ICON_SUN : ICON_MOON;
        }
    }

    let storedTheme = 'light';
    try {
        storedTheme = window.localStorage.getItem(THEME_STORAGE_KEY) || 'light';
    } catch (e) {
    }
    applyTheme(storedTheme);
    if (themeButton) {
        themeButton.addEventListener('click', () => {
            const next = appRoot && appRoot.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            try {
                window.localStorage.setItem(THEME_STORAGE_KEY, next);
            } catch (e) {
            }
        });
    }

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
            agentType: getAgentValue() || (config.defaults && config.defaults.agentType) || 'explicatif',
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
        statusTextNode.textContent = text;
        statusNode.classList.remove('is-error', 'is-busy');
        if (kind === 'error') {
            statusNode.classList.add('is-error');
        } else if (kind === 'busy') {
            statusNode.classList.add('is-busy');
        }
    }

    // ── Menu déroulant custom des cours (charte autoporteur) ──
    let courseMenuOpen = false;

    function closeCourseMenu() {
        courseMenuOpen = false;
        courseMenu.hidden = true;
        courseButton.setAttribute('aria-expanded', 'false');
    }

    function openCourseMenu() {
        if (config.lockedCourse) {
            return;
        }
        courseMenuOpen = true;
        courseMenu.hidden = false;
        courseButton.setAttribute('aria-expanded', 'true');
    }

    async function pickCourse(courseId) {
        closeCourseMenu();
        if (String(courseId) === getSelectedCourseId()) {
            return;
        }
        setSelectedCourseId(String(courseId));
        setStatus(config.strings.ready, '');
        render();
        await ensureCourseReady(getSelectedCourseId());
    }

    function syncCourseOptions() {
        const selectedCourseId = getSelectedCourseId();
        const selected = selectedCourseId && courseMap.has(selectedCourseId)
            ? courseMap.get(selectedCourseId)
            : null;

        courseLabelNode.textContent = selected ? selected.fullname : config.strings.coursePlaceholder;
        courseButton.classList.toggle('is-unset', !selected);
        courseButton.disabled = !!config.lockedCourse;

        courseItemsNode.innerHTML = '';
        (config.courses || []).forEach((course) => {
            const isSelected = String(course.id) === selectedCourseId;
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'la-menu-item';
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            item.innerHTML =
                '<span class="la-mi-ico">' + ICON_BOOK + '</span>' +
                '<span class="la-mi-body"><b>' + escapeHtml(course.fullname) + '</b>' +
                '<span>' + escapeHtml(course.shortname || '') + '</span></span>' +
                (isSelected ? '<span class="la-mi-check">' + ICON_CHECK + '</span>' : '');
            item.addEventListener('click', () => {
                pickCourse(course.id);
            });
            courseItemsNode.appendChild(item);
        });
    }

    function renderThreadList() {
        const courseId = getSelectedCourseId();
        threadList.innerHTML = '';
        if (!courseId) {
            const empty = document.createElement('div');
            empty.className = 'la-sb-empty';
            empty.textContent = config.strings.noCourseDetail;
            threadList.appendChild(empty);
            return;
        }

        const activeThread = getActiveThread(courseId);
        let threads = getSortedThreads(courseId);
        if (!threads.length) {
            const empty = document.createElement('div');
            empty.className = 'la-sb-empty';
            empty.innerHTML = '<strong>' + escapeHtml(config.strings.conversationsEmpty) + '</strong><span>' +
                escapeHtml(config.strings.conversationsEmptyDetail) + '</span>';
            threadList.appendChild(empty);
            return;
        }

        // Filtrage par la recherche de la sidebar (correspondance sur le titre).
        const query = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
        if (query) {
            threads = threads.filter((thread) =>
                String(thread.title || '').toLowerCase().indexOf(query) !== -1);
            if (!threads.length) {
                const empty = document.createElement('div');
                empty.className = 'la-sb-empty';
                empty.textContent = config.strings.searchNoResults;
                threadList.appendChild(empty);
                return;
            }
        }

        threads.forEach((thread) => {
            const agentId = normalizeAgentType(thread.agentType);
            const item = document.createElement('div');
            item.className = 'la-convo is-' + agentId +
                (activeThread && activeThread.id === thread.id ? ' is-active' : '');

            const dot = document.createElement('span');
            dot.className = 'la-dot';
            dot.setAttribute('aria-hidden', 'true');

            const openButton = document.createElement('button');
            openButton.type = 'button';
            openButton.className = 'la-convo-main';
            openButton.innerHTML =
                '<span class="la-convo-title">' + escapeHtml(thread.title || config.strings.untitledConversation) + '</span>' +
                '<span class="la-convo-meta">' + escapeHtml(agentLabel(agentId)) + '</span>';
            openButton.addEventListener('click', async () => {
                setActiveThread(courseId, thread.id);
                render();
                await syncThreadHistory(courseId, thread, { silent: true });
            });

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'la-icon-btn la-convo-delete';
            deleteButton.innerHTML = ICON_TRASH;
            deleteButton.disabled = deletingThreads.has(thread.id);
            deleteButton.title = config.strings.deleteConversationLabel;
            deleteButton.setAttribute('aria-label', config.strings.deleteConversationLabel + ': ' +
                String(thread.title || config.strings.untitledConversation));
            deleteButton.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();
                const confirmed = await astusseConfirm({
                    title: config.strings.deleteConversationTitle,
                    message: config.strings.deleteConversationConfirm,
                    confirmLabel: config.strings.deleteConversationLabel,
                    cancelLabel: config.strings.cancelLabel,
                    danger: true
                });
                if (!confirmed) {
                    return;
                }
                await deleteThread(thread);
            });

            item.appendChild(dot);
            item.appendChild(openButton);
            item.appendChild(deleteButton);
            threadList.appendChild(item);
        });
    }

    // Écran d'accueil charte « autoporteur » : logo, salutation, cartes d'agents, suggestions.
    function renderWelcome() {
        const courseId = getSelectedCourseId();
        const course = courseId && courseMap.has(courseId) ? courseMap.get(courseId) : null;

        messagesNode.innerHTML = '';
        const empty = document.createElement('div');
        empty.className = 'la-empty';
        const inner = document.createElement('div');
        inner.className = 'la-empty-inner';

        const logo = document.createElement('div');
        logo.className = 'la-empty-logo';
        logo.innerHTML = ICON_OCTO;
        inner.appendChild(logo);

        const heading = document.createElement('h1');
        heading.textContent = config.strings.emptyGreeting;
        inner.appendChild(heading);

        const sub = document.createElement('p');
        sub.className = 'la-sub';
        if (course) {
            const parts = String(config.strings.emptyCourseLoaded).split('%COURSE%');
            sub.appendChild(document.createTextNode(parts[0] || ''));
            const courseName = document.createElement('b');
            courseName.textContent = course.fullname;
            sub.appendChild(courseName);
            sub.appendChild(document.createTextNode(parts[1] || ''));
        } else {
            sub.textContent = config.strings.emptyCourseNeeded;
        }
        inner.appendChild(sub);

        const grid = document.createElement('div');
        grid.className = 'la-agent-grid';
        ['socratique', 'explicatif'].forEach((agentId) => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'la-agent-card' + (getAgentValue() === agentId ? ' is-selected' : '');
            card.style.setProperty('--ag', AGENT_COLORS[agentId]);
            card.innerHTML =
                '<div class="la-ag-icon">' + AGENT_ICONS[agentId] + '</div>' +
                '<div class="la-ag-name">' + escapeHtml(agentLabel(agentId)) + '</div>' +
                '<div class="la-ag-role">' + escapeHtml(agentRole(agentId)) + '</div>' +
                '<div class="la-ag-desc">' + escapeHtml(agentDesc(agentId)) + '</div>';
            card.addEventListener('click', () => {
                applyAgentChange(agentId);
                renderWelcome();
            });
            grid.appendChild(card);
        });
        inner.appendChild(grid);

        const starters = Array.isArray(config.strings.starters) ? config.strings.starters : [];
        if (starters.length) {
            const suggLabel = document.createElement('div');
            suggLabel.className = 'la-sugg-label';
            suggLabel.textContent = config.strings.starterLabel;
            inner.appendChild(suggLabel);

            const row = document.createElement('div');
            row.className = 'la-sugg-row';
            starters.forEach((starter) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'la-sugg';
                button.disabled = !course;
                button.innerHTML = ICON_SPARK + escapeHtml(starter);
                button.addEventListener('click', () => {
                    messageInput.value = starter;
                    messageInput.dispatchEvent(new Event('input', { bubbles: true }));
                    messageInput.focus();
                });
                row.appendChild(button);
            });
            inner.appendChild(row);
        }

        empty.appendChild(inner);
        messagesNode.appendChild(empty);
    }

    function renderMessage(message) {
        const agentId = normalizeAgentType(message.agentUsed || getAgentValue());
        const isAssistant = message.role === 'assistant';

        const article = document.createElement('article');
        article.className = 'la-msg ' + (isAssistant ? 'la-ai' : 'la-user') +
            (message.pending ? ' is-pending' : '') +
            (message.error ? ' is-error' : '');
        article.dataset.messageId = message.id;
        if (isAssistant) {
            article.style.setProperty('--ag', AGENT_COLORS[agentId]);
        }

        const meta = document.createElement('div');
        meta.className = 'la-msg-meta';
        if (isAssistant) {
            const badge = document.createElement('span');
            badge.className = 'la-msg-badge';
            badge.innerHTML = AGENT_ICONS[agentId] + escapeHtml(agentLabel(agentId)) +
                ' · ' + escapeHtml(agentRole(agentId));
            meta.appendChild(badge);
        } else {
            const name = document.createElement('span');
            name.className = 'la-name';
            name.textContent = config.strings.studentLabel;
            meta.appendChild(name);
        }
        article.appendChild(meta);

        const bubble = document.createElement('div');
        bubble.className = 'la-bubble' + (isAssistant ? ' is-markdown' : '');
        if (message.pending && message.text === config.strings.pending) {
            // Avant le premier token : points de frappe animés.
            bubble.innerHTML = '<span class="la-typing"><span></span><span></span><span></span></span>';
        } else {
            bubble.innerHTML = isAssistant
                ? renderMarkdown(message.text)
                : escapeHtml(message.text).replace(/\n/g, '<br>');
        }
        article.appendChild(bubble);

        if (isAssistant && !message.pending && !message.error && navigator.clipboard) {
            const actions = document.createElement('div');
            actions.className = 'la-msg-actions';
            const copyButton = document.createElement('button');
            copyButton.type = 'button';
            copyButton.className = 'la-msg-act';
            copyButton.innerHTML = ICON_COPY + escapeHtml(config.strings.copyLabel);
            copyButton.addEventListener('click', () => {
                navigator.clipboard.writeText(message.text).then(() => {
                    copyButton.innerHTML = ICON_COPY + escapeHtml(config.strings.copiedLabel);
                    setTimeout(() => {
                        copyButton.innerHTML = ICON_COPY + escapeHtml(config.strings.copyLabel);
                    }, 1600);
                }).catch(() => {
                });
            });
            actions.appendChild(copyButton);
            article.appendChild(actions);
        }

        return article;
    }

    function renderMessages() {
        const courseId = getSelectedCourseId();
        if (!courseId) {
            renderWelcome();
            return;
        }
        const thread = getActiveThread(courseId);
        if (!thread || !thread.messages.length) {
            renderWelcome();
            return;
        }
        messagesNode.innerHTML = '';
        const innerWrap = document.createElement('div');
        innerWrap.className = 'la-chat-inner';
        thread.messages.forEach((message) => innerWrap.appendChild(renderMessage(message)));
        messagesNode.appendChild(innerWrap);
        messagesNode.scrollTop = messagesNode.scrollHeight;
    }

    function syncAvailability() {
        const hasCourse = !!getSelectedCourseId() && courseMap.has(getSelectedCourseId());
        const disabled = !hasCourse || isSending;
        sendButton.disabled = disabled;
        newButton.disabled = disabled;
        if (composerNode) {
            composerNode.classList.toggle('is-disabled', !hasCourse);
        }
    }

    function syncAgentSelect() {
        const courseId = getSelectedCourseId();
        const thread = courseId ? getActiveThread(courseId) : null;
        setAgentValue(thread
            ? normalizeAgentType(thread.agentType)
            : normalizeAgentType((config.defaults && config.defaults.agentType) || 'explicatif'));
    }

    function render() {
        syncCourseOptions();
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
        const bubble = article.querySelector('.la-bubble');
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
            courseButton.focus();
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

        thread.agentType = normalizeAgentType(getAgentValue() || thread.agentType || 'explicatif');
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
            courseButton.focus();
            return;
        }
        createThread(courseId);
        setStatus(config.strings.ready, '');
        render();
        messageInput.focus();
    });

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            renderThreadList();
        });
    }

    courseButton.addEventListener('click', () => {
        if (courseMenuOpen) {
            closeCourseMenu();
        } else {
            openCourseMenu();
        }
    });

    document.addEventListener('click', (event) => {
        if (courseMenuOpen && courseWrap && !courseWrap.contains(event.target)) {
            closeCourseMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && courseMenuOpen) {
            closeCourseMenu();
            courseButton.focus();
        }
    });

    function applyAgentChange(agentId) {
        setAgentValue(agentId);
        const courseId = getSelectedCourseId();
        const thread = courseId ? getActiveThread(courseId) : null;
        if (thread) {
            thread.agentType = normalizeAgentType(agentId || thread.agentType);
            thread.updatedAt = Date.now();
            updateThread(courseId, thread);
            renderThreadList();
        }
    }

    agentButtons.forEach((button) => {
        button.addEventListener('click', () => {
            applyAgentChange(button.dataset.agent);
            // Si l'écran d'accueil est affiché, refléter la carte sélectionnée.
            if (messagesNode.querySelector('.la-empty')) {
                renderWelcome();
            }
        });
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
