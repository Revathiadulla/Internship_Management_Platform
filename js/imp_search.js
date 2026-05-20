/**
 * IMP Universal Search Engine
 * Reusable, lightweight, real-time search for all IMP dashboards.
 * Usage: ImpSearch.init(config)
 */
const ImpSearch = (function () {

  // ── Core filter function ──────────────────────────────────────────────────
  function filterItems(items, query, getTextFn) {
    const q = query.toLowerCase().trim();
    let visible = 0;
    items.forEach(item => {
      const text = getTextFn(item).toLowerCase();
      const match = q === '' || text.includes(q);
      item.style.display = match ? '' : 'none';
      if (match) {
        item.style.opacity = '0';
        item.style.transform = 'translateY(4px)';
        requestAnimationFrame(() => {
          item.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
          item.style.opacity = '1';
          item.style.transform = 'translateY(0)';
        });
        visible++;
      }
    });
    return visible;
  }

  // ── Show/hide empty state ─────────────────────────────────────────────────
  function toggleEmpty(emptyEl, visible, total) {
    if (!emptyEl) return;
    emptyEl.style.display = (visible === 0 && total > 0) ? 'block' : 'none';
  }

  // ── Build empty state element ─────────────────────────────────────────────
  function createEmptyState(msg) {
    const div = document.createElement('div');
    div.id = 'imp-search-empty';
    div.className = 'text-center py-12 col-span-full';
    div.innerHTML = `
      <div class="w-14 h-14 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
        <span class="material-symbols-outlined text-[28px] text-slate-400">search_off</span>
      </div>
      <p class="text-slate-500 font-semibold text-sm">${msg || 'No results found.'}</p>
      <p class="text-slate-400 text-xs mt-1">Try a different keyword.</p>`;
    return div;
  }

  // ── Main init ─────────────────────────────────────────────────────────────
  /**
   * @param {Object} config
   * @param {string}   config.inputId       - ID of the search <input>
   * @param {string}   config.itemSelector  - CSS selector for searchable items
   * @param {string}   [config.textSelector]- CSS selector inside each item for text (optional)
   * @param {string}   [config.containerId] - ID of the container (for empty state injection)
   * @param {string}   [config.emptyMsg]    - Custom "no results" message
   * @param {Function} [config.getTextFn]   - Custom function(item) => string
   */
  function init(config) {
    const input = document.getElementById(config.inputId);
    if (!input) return;

    const getItems = () => Array.from(document.querySelectorAll(config.itemSelector));

    // Build or find empty state
    let emptyEl = document.getElementById('imp-search-empty');
    const container = config.containerId ? document.getElementById(config.containerId) : null;
    if (!emptyEl && container) {
      emptyEl = createEmptyState(config.emptyMsg);
      container.appendChild(emptyEl);
    }

    // Text extractor
    const getText = config.getTextFn || function (item) {
      if (config.textSelector) {
        return Array.from(item.querySelectorAll(config.textSelector))
          .map(el => el.textContent).join(' ');
      }
      return item.textContent;
    };

    // Real-time filtering
    input.addEventListener('input', function () {
      const items = getItems();
      const visible = filterItems(items, this.value, getText);
      toggleEmpty(emptyEl, visible, items.length);
    });

    // Clear on Escape
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        this.value = '';
        this.dispatchEvent(new Event('input'));
        this.blur();
      }
    });
  }

  // ── Table row search ──────────────────────────────────────────────────────
  function initTable(config) {
    const input = document.getElementById(config.inputId);
    if (!input) return;

    const getRows = () => Array.from(document.querySelectorAll(config.rowSelector || 'tbody tr'));
    let emptyRow = null;

    input.addEventListener('input', function () {
      const q = this.value.toLowerCase().trim();
      const rows = getRows();
      let visible = 0;

      rows.forEach(row => {
        if (row.id === 'imp-search-empty-row') return;
        const text = row.textContent.toLowerCase();
        const match = q === '' || text.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
      });

      // Empty row
      const tbody = rows[0]?.closest('tbody');
      if (tbody) {
        const existing = tbody.querySelector('#imp-search-empty-row');
        if (visible === 0 && rows.length > 0) {
          if (!existing) {
            const tr = document.createElement('tr');
            tr.id = 'imp-search-empty-row';
            const cols = rows[0]?.querySelectorAll('td, th').length || 4;
            tr.innerHTML = `<td colspan="${cols}" class="py-10 text-center">
              <div class="flex flex-col items-center gap-2">
                <span class="material-symbols-outlined text-[32px] text-slate-300">search_off</span>
                <p class="text-slate-500 font-semibold text-sm">${config.emptyMsg || 'No results found.'}</p>
                <p class="text-slate-400 text-xs">Try a different keyword.</p>
              </div></td>`;
            tbody.appendChild(tr);
          }
        } else if (existing) {
          existing.remove();
        }
      }
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { this.value = ''; this.dispatchEvent(new Event('input')); this.blur(); }
    });
  }

  return { init, initTable, filterItems };
})();
