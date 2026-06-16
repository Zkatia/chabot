/**
 * Ingestion jobs tracking page: poll JSON status endpoint and refresh rows
 * until no non-final job remains, then stop.
 *
 * @module     local_astusse/jobs_page
 * @copyright  2026 Ingenium Digital Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var pollTimer = null;

    /**
     * Whether a job status is terminal (no further polling needed).
     *
     * @param {String} status Job status value.
     * @return {Boolean} True when the status is final.
     */
    function isFinal(status) {
        return status === 'succeeded' || status === 'failed';
    }

    /**
     * Escape a value for safe insertion into HTML.
     *
     * @param {*} value Raw value to escape.
     * @return {String} HTML-escaped string.
     */
    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * Build the HTML for the details cell of a job row.
     *
     * @param {Object} job Job data object.
     * @return {String} HTML markup for the details cell.
     */
    function buildDetails(job) {
        var parts = [];
        if (job.backendtraceid) {
            parts.push('<div class="small text-muted">Trace ID : ' + escapeHtml(job.backendtraceid) + '</div>');
        }
        if (job.backendjobid) {
            parts.push('<div class="small text-muted">Backend job ID : ' + escapeHtml(job.backendjobid) + '</div>');
        }
        if (job.status === 'failed' && job.errormessage) {
            parts.push('<div class="small text-danger">' + escapeHtml(job.errormessage) + '</div>');
        }
        if (job.status === 'failed' && job.httpstatus) {
            parts.push('<div class="small text-muted">HTTP ' + escapeHtml(job.httpstatus) + '</div>');
        }
        return parts.length ? parts.join('') : '&mdash;';
    }

    /**
     * Update a single job table row in place from fresh job data.
     *
     * @param {HTMLElement} row Table row element to update.
     * @param {Object} job Job data object.
     * @return {void}
     */
    function updateRow(row, job) {
        var previousStatus = row.getAttribute('data-job-status');
        row.setAttribute('data-job-status', job.status);

        var statusCell = row.querySelector('[data-cell="status"]');
        if (statusCell) {
            statusCell.className = job.statusclass;
            statusCell.textContent = job.statuslabel;
        }

        var attemptsCell = row.querySelector('[data-cell="attempts"]');
        if (attemptsCell) {
            attemptsCell.textContent = String(job.attempts);
        }

        var detailsCell = row.querySelector('[data-cell="details"]');
        if (detailsCell) {
            detailsCell.innerHTML = buildDetails(job);
        }

        // If a job transitions into a failed state, the retry button row must be
        // rendered — a full reload is the simplest way to get the server-signed form.
        if (previousStatus !== 'failed' && job.status === 'failed') {
            window.location.reload();
        }
    }

    /**
     * Collect the ids of all jobs that are not yet in a final state.
     *
     * @return {Array.<String>} List of pending job ids.
     */
    function pendingIds() {
        var ids = [];
        var rows = document.querySelectorAll('tr[data-job-id]');
        for (var i = 0; i < rows.length; i++) {
            if (!isFinal(rows[i].getAttribute('data-job-status'))) {
                ids.push(rows[i].getAttribute('data-job-id'));
            }
        }
        return ids;
    }

    /**
     * Whether any job on the page is still pending.
     *
     * @return {Boolean} True when at least one job is not final.
     */
    function hasPendingJobs() {
        return pendingIds().length > 0;
    }

    /**
     * Stop the recurring poll timer if it is running.
     *
     * @return {void}
     */
    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    /**
     * Append the pending job ids as query parameters to the status endpoint.
     *
     * @param {String} endpoint Base status endpoint URL.
     * @return {String} Endpoint URL with the pending ids appended.
     */
    function buildPollUrl(endpoint) {
        var ids = pendingIds();
        if (ids.length === 0) {
            return endpoint;
        }
        var sep = endpoint.indexOf('?') === -1 ? '?' : '&';
        var query = ids.map(function(id) {
            return 'ids[]=' + encodeURIComponent(id);
        }).join('&');
        return endpoint + sep + query;
    }

    /**
     * Fetch fresh job statuses and refresh the matching rows, stopping when done.
     *
     * @param {String} endpoint Base status endpoint URL.
     * @return {void}
     */
    function poll(endpoint) {
        var url = buildPollUrl(endpoint);
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'}
        }).then(function(resp) {
            if (!resp.ok) {
                throw new Error('status endpoint returned HTTP ' + resp.status);
            }
            return resp.json();
        }).then(function(data) {
            var jobs = (data && data.jobs) || [];
            for (var i = 0; i < jobs.length; i++) {
                var job = jobs[i];
                var row = document.querySelector('tr[data-job-id="' + job.id + '"]');
                if (row) {
                    updateRow(row, job);
                }
            }
            if (!hasPendingJobs()) {
                stopPolling();
            }
            return;
        }).catch(function(err) {
            // Stop polling on repeated failures to avoid hammering.
            // eslint-disable-next-line no-console
            console.error('local_astusse jobs poll failed:', err);
            stopPolling();
        });
    }

    /**
     * Auto-submit the jobs filter form when a filter control changes.
     *
     * @return {void}
     */
    function initFilterAutoSubmit() {
        var form = document.getElementById('local-astusse-jobs-filters-form');
        if (!form) {
            return;
        }
        var autoSubmitControls = form.querySelectorAll('[data-autosubmit="1"]');
        autoSubmitControls.forEach(function(control) {
            control.addEventListener('change', function() {
                form.submit();
            });
        });
    }

    return {
        init: function(args) {
            args = args || {};
            initFilterAutoSubmit();
            var endpoint = args.statusEndpoint;
            var intervalMs = parseInt(args.pollIntervalMs, 10) || 3000;
            if (!endpoint) {
                return;
            }
            if (!hasPendingJobs()) {
                return;
            }
            // First poll shortly after load to catch quick transitions, then regular interval.
            setTimeout(function() {
                poll(endpoint);
            }, 1000);
            pollTimer = setInterval(function() {
                poll(endpoint);
            }, intervalMs);
        }
    };
});
