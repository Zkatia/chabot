/**
 * Ingest page interactions: resource filters, select-all.
 *
 * @module     local_astusse/ingest_page
 * @package    local_astusse
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        init: function() {
            initResourceFilters();
            initSelectAll();
        }
    };

    function initResourceFilters() {
        var filterBtns = document.querySelectorAll('.local-astusse-filter-btn');
        filterBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                filterBtns.forEach(function(b) { b.classList.remove('active'); });
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
