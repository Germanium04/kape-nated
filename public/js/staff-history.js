// Staff transaction history: payment + date-range + search filters with pagination.
(() => {
    const table = document.getElementById('historyTable');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const PAGE_SIZE = 10;
    let currentPage = 1;

    const paymentSelect = document.getElementById('historyPayment');
    const rangeModeSelect = document.getElementById('historyRangeMode');
    const searchInput = document.getElementById('historySearch');
    const dayWrap = document.getElementById('historyDayWrap');
    const monthWrap = document.getElementById('historyMonthWrap');
    const yearWrap = document.getElementById('historyYearWrap');
    const dayInput = document.getElementById('historyDay');
    const monthInput = document.getElementById('historyMonth');
    const yearInput = document.getElementById('historyYear');
    const emptyNote = document.getElementById('historyEmpty');
    const pageInfo = document.getElementById('historyPageInfo');
    const pageNumbers = document.getElementById('historyPageNumbers');
    const prevBtn = document.getElementById('historyPrevBtn');
    const nextBtn = document.getElementById('historyNextBtn');
    const summary = document.getElementById('historySummary');

    function toggleRangeInputs() {
        const mode = rangeModeSelect?.value || 'all';
        if (dayWrap) dayWrap.hidden = mode !== 'day';
        if (monthWrap) monthWrap.hidden = mode !== 'month';
        if (yearWrap) yearWrap.hidden = mode !== 'year';
    }

    function getFilteredRows() {
        const paymentVal = (paymentSelect?.value || 'all').trim().toLowerCase();
        const rangeMode = rangeModeSelect?.value || 'all';
        const searchVal = (searchInput?.value || '').toLowerCase().trim();
        const selectedDay = dayInput?.value || '';
        const selectedMonth = monthInput?.value || '';
        const selectedYear = yearInput?.value || '';

        return rows.filter(row => {
            const rowPayment = (row.dataset.payment || '').trim().toLowerCase();
            const rowSearch = (row.dataset.search || '').toLowerCase();
            const rowDate = (row.dataset.date || '').trim();

            if (paymentVal !== 'all' && rowPayment !== paymentVal) return false;
            if (searchVal && !rowSearch.includes(searchVal)) return false;
            if (rangeMode === 'day' && selectedDay && rowDate !== selectedDay) return false;
            if (rangeMode === 'month' && selectedMonth && !rowDate.startsWith(selectedMonth)) return false;
            if (rangeMode === 'year' && selectedYear && !rowDate.startsWith(selectedYear)) return false;
            return true;
        });
    }

    function buildPageButtons(totalPages) {
        if (!pageNumbers) return;
        pageNumbers.innerHTML = '';
        if (totalPages <= 1) return;
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = i;
                btn.className = i === currentPage ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm';
                btn.onclick = () => { currentPage = i; renderTable(); };
                pageNumbers.appendChild(btn);
            } else if (pageNumbers.lastElementChild?.textContent !== '…') {
                const span = document.createElement('span');
                span.textContent = '…';
                span.style.padding = '0 4px';
                pageNumbers.appendChild(span);
            }
        }
    }

    function renderTable() {
        toggleRangeInputs();
        const filtered = getFilteredRows();
        const totalItems = filtered.length;
        const totalPages = Math.ceil(totalItems / PAGE_SIZE) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        rows.forEach(r => r.style.display = 'none');

        if (summary) summary.textContent = totalItems + ' transaction' + (totalItems === 1 ? '' : 's');

        if (totalItems === 0) {
            if (emptyNote) emptyNote.hidden = false;
            if (pageInfo) pageInfo.textContent = 'Showing 0 transactions';
            if (prevBtn) prevBtn.disabled = true;
            if (nextBtn) nextBtn.disabled = true;
            if (pageNumbers) pageNumbers.innerHTML = '';
            return;
        }

        if (emptyNote) emptyNote.hidden = true;
        const startIdx = (currentPage - 1) * PAGE_SIZE;
        const endIdx = Math.min(startIdx + PAGE_SIZE, totalItems);
        filtered.slice(startIdx, endIdx).forEach(r => r.style.display = '');
        if (pageInfo) pageInfo.textContent = 'Showing ' + (startIdx + 1) + '-' + endIdx + ' of ' + totalItems + ' transactions';
        if (prevBtn) {
            prevBtn.disabled = currentPage <= 1;
            prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; renderTable(); } };
        }
        if (nextBtn) {
            nextBtn.disabled = currentPage >= totalPages;
            nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; renderTable(); } };
        }
        buildPageButtons(totalPages);
    }

    [paymentSelect, rangeModeSelect, dayInput, monthInput, yearInput].forEach(el => {
        el?.addEventListener('change', () => { currentPage = 1; renderTable(); });
    });
    searchInput?.addEventListener('input', () => { currentPage = 1; renderTable(); });

    // Opens a receipt modal by id; Staff.js already handles closing.
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-open-modal]');
        if (btn) document.getElementById(btn.dataset.openModal)?.classList.add('open');
    });

    renderTable();
})();
