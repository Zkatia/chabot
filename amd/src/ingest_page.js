/**
 * Ingest page interactions: resource filters, select-all.
 *
 * @module     local_astusse/ingest_page
 * @copyright  2026 Ingenium Digital Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        /**
         * Wire up the resource filter buttons and the select-all checkbox.
         *
         * @return {void}
         */
        init: function() {
            initResourceFilters();
            initSelectAll();
        }
    };

    /**
     * Bind the modname filter buttons so they show/hide resource rows.
     *
     * @return {void}
     */
    function initResourceFilters() {
        var filterBtns = document.querySelectorAll('.local-astusse-filter-btn');
        filterBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                filterBtns.forEach(function(b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                var filter = btn.getAttribute('data-filter');
                document.querySelectorAll('.local-astusse-resource-row').forEach(function(row) {
                    if (filter === 'all' || row.getAttribute('data-modname') === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                var selectAll = document.getElementById('local-astusse-select-all');
                if (selectAll) {
                    selectAll.checked = false;
                }
            });
        });
    }

    /**
     * Bind the select-all checkbox so it toggles every visible resource row.
     *
     * @return {void}
     */
    function initSelectAll() {
        var selectAll = document.getElementById('local-astusse-select-all');
        if (!selectAll) {
            return;
        }
        selectAll.addEventListener('change', function() {
            var checked = selectAll.checked;
            document.querySelectorAll('.local-astusse-resource-check').forEach(function(cb) {
                var row = cb.closest('tr');
                if (row && row.style.display !== 'none') {
                    cb.checked = checked;
                }
            });
        });
    }
});
