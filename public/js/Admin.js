/* kape-nated admin prototype — front-end behaviour only.
   Nothing persists; a refresh puts the demo data back. */

/* ---------- user menu dropdown ---------- */
(function userMenu() {
    const btn = document.getElementById('userMenuBtn');
    const menu = document.getElementById('userDropdown');
    if (!btn || !menu) return;

    function close() {
        menu.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
    }

    function open() {
        menu.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
    }

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.hidden ? open() : close();
    });

    document.addEventListener('click', (e) => {
        if (!menu.hidden && !menu.contains(e.target) && e.target !== btn) close();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !menu.hidden) close();
    });

    const profileBtn = document.getElementById('profilePlaceholder');
    const logoutBtn = document.getElementById('logoutPlaceholder');
    const head = menu.querySelector('.user-dropdown-head .muted');
    const defaultNote = head ? head.textContent : '';

    function flash(text) {
        if (!head) return;
        head.textContent = text;
        setTimeout(() => { head.textContent = defaultNote; close(); }, 1100);
    }

    if (profileBtn) profileBtn.addEventListener('click', () => flash('No profile page built yet — this is a stand-in.'));
    if (logoutBtn) logoutBtn.addEventListener('click', () => flash("Signed out (prototype only — nothing real happened)."));
})();

/* ---------- modals ---------- */
document.addEventListener('click', (e) => {
    const open = e.target.closest('[data-open-modal]');
    if (open) {
        const el = document.getElementById(open.dataset.openModal);
        if (el) { el.hidden = false; document.body.classList.add('is-locked'); }
    }

    const close = e.target.closest('[data-close-modal]');
    if (close) {
        const el = document.getElementById(close.dataset.closeModal);
        if (el) { el.hidden = true; document.body.classList.remove('is-locked'); }
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal:not([hidden])').forEach((m) => { m.hidden = true; });
    document.body.classList.remove('is-locked');
});

/* ---------- receipts & money reconciliation ---------- */
(function receipts() {
    const table = document.getElementById('receiptTable');
    if (!table) return;

    const search = document.getElementById('receiptSearch');
    const payment = document.getElementById('receiptFilter');
    const branch = document.getElementById('receiptBranch');
    const rangeMode = document.getElementById('receiptRangeMode');
    const rangeDay = document.getElementById('rangeDay');
    const rangeMonth = document.getElementById('rangeMonth');
    const rangeYear = document.getElementById('rangeYear');
    const empty = document.getElementById('receiptEmpty');
    const summary = document.getElementById('receiptSummary');
    const rows = Array.from(table.tBodies[0].rows);

    // Cash Drawer Audit Elements
    const reconExpected = document.getElementById('reconExpected');
    const reconActual = document.getElementById('reconActual');
    const reconVariance = document.getElementById('reconVariance');

    // Pagination Elements
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    const pageNumbers = document.getElementById('pageNumbers');
    const pageInfo = document.getElementById('pageInfo');
    const paginationBar = document.getElementById('receiptPagination');

    const pageSize = 10;
    let currentPage = 1;

    const wraps = {
        day: document.getElementById('rangeDayWrap'),
        month: document.getElementById('rangeMonthWrap'),
        year: document.getElementById('rangeYearWrap'),
    };

    function matchesRange(dateStr) {
        const mode = rangeMode.value;
        if (mode === 'all') return true;
        if (mode === 'day') return dateStr === rangeDay.value;
        if (mode === 'month') return dateStr.startsWith(rangeMonth.value);
        if (mode === 'year') return dateStr.startsWith(rangeYear.value);
        return true;
    }

    function calculateCashVariance(expectedCash) {
        if (!reconVariance) return;

        if (!reconActual || reconActual.value === '') {
            reconVariance.value = '₱0.00';
            reconVariance.style.color = 'inherit';
            return;
        }

        const actualCash = parseFloat(reconActual.value) || 0;
        const variance = actualCash - expectedCash;
        const prefix = variance > 0 ? '+' : '';

        reconVariance.value = prefix + '₱' + variance.toLocaleString('en-PH', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });

        reconVariance.style.color = variance < 0 ? '#a63d2a' : (variance > 0 ? '#46703f' : 'inherit');
    }

    function renderPageNumbers(totalPages) {
        if (!pageNumbers) return;
        pageNumbers.innerHTML = '';

        if (totalPages <= 1) return;

        let pages = [];
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                pages.push(i);
            } else if (pages[pages.length - 1] !== '...') {
                pages.push('...');
            }
        }

        pages.forEach((p) => {
            if (p === '...') {
                const span = document.createElement('span');
                span.textContent = '…';
                span.style.cssText = 'padding: 0 0.25rem; align-self: center; color: #888; font-size: 0.85rem;';
                pageNumbers.appendChild(span);
            } else {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = p;
                btn.className = p === currentPage ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm';
                btn.style.cssText = 'min-width: 2rem; padding: 0.25rem 0.5rem;';
                
                btn.addEventListener('click', () => {
                    currentPage = p;
                    apply();
                });

                pageNumbers.appendChild(btn);
            }
        });
    }

    function apply() {
        const term = (search.value || '').trim().toLowerCase();
        const pay = payment.value;
        const br = branch.value;
        
        let matchingRows = [];
        let total = 0;
        let expectedCash = 0;

        rows.forEach((row) => {
            const rowPayment = (row.dataset.payment || '').toLowerCase();
            const rowBranch = row.dataset.branch;
            const rowDate = row.dataset.date;

            // 1. Calculate Expected Cash for Audit (Strictly Cash payments matching Branch & Date range)
            const isBranchMatch = (br === 'all' || rowBranch === br);
            const isDateMatch = matchesRange(rowDate);

            if (isBranchMatch && isDateMatch && rowPayment === 'cash') {
                const amountCell = row.querySelector('td.ta-r.mono');
                const amount = amountCell ? parseFloat(amountCell.textContent.replace(/[₱,]/g, '')) || 0 : 0;
                expectedCash += amount;
            }

            // 2. Filter Table Display Rows
            const hit = (!term || row.dataset.search.includes(term)) &&
                        (pay === 'all' || rowPayment === pay.toLowerCase()) &&
                        isBranchMatch &&
                        isDateMatch;
            
            if (hit) {
                matchingRows.push(row);
                const amountCell = row.querySelector('td.ta-r.mono');
                const amount = amountCell ? parseFloat(amountCell.textContent.replace(/[₱,]/g, '')) || 0 : 0;
                total += amount;
            } else {
                row.hidden = true;
            }
        });

        const totalMatching = matchingRows.length;
        const totalPages = Math.ceil(totalMatching / pageSize) || 1;

        if (currentPage > totalPages) currentPage = 1;

        const startIdx = (currentPage - 1) * pageSize;
        const endIdx = startIdx + pageSize;

        rows.forEach((row) => { row.hidden = true; });
        matchingRows.slice(startIdx, endIdx).forEach((row) => { row.hidden = false; });

        empty.hidden = totalMatching > 0;
        if (paginationBar) paginationBar.hidden = totalMatching === 0;

        summary.textContent = totalMatching
            ? `${totalMatching} transaction${totalMatching === 1 ? '' : 's'} · ₱${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`
            : '';

        if (pageInfo) {
            const from = totalMatching ? startIdx + 1 : 0;
            const to = Math.min(endIdx, totalMatching);
            pageInfo.textContent = `Showing ${from}–${to} of ${totalMatching} transactions`;
        }

        if (prevBtn) prevBtn.disabled = currentPage <= 1;
        if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

        renderPageNumbers(totalPages);

        // Update Expected Cash Box
        if (reconExpected) {
            reconExpected.value = '₱' + expectedCash.toLocaleString('en-PH', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            });
        }

        // Recalculate Variance
        calculateCashVariance(expectedCash);
    }

    prevBtn?.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            apply();
        }
    });

    nextBtn?.addEventListener('click', () => {
        currentPage++;
        apply();
    });

    rangeMode.addEventListener('change', () => {
        Object.values(wraps).forEach((w) => { w.hidden = true; });
        if (wraps[rangeMode.value]) wraps[rangeMode.value].hidden = false;
        currentPage = 1;
        apply();
    });

    [search, payment, branch, rangeDay, rangeMonth, rangeYear].forEach((el) => {
        el?.addEventListener('input', () => { currentPage = 1; apply(); });
        el?.addEventListener('change', () => { currentPage = 1; apply(); });
    });

    if (reconActual) {
        reconActual.addEventListener('input', apply);
    }

    apply();
})();

/* ---------- shared: deterministic "random" so estimates don't jump around on every render ---------- */
function seedNum(str) {
    let h = 0;
    for (let i = 0; i < str.length; i++) { h = (h * 31 + str.charCodeAt(i)) >>> 0; }
    return (h % 1000) / 1000;
}

const peso = (n) => '₱' + Math.round(n).toLocaleString('en-PH');
const pesoDec = (n) => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/* ---------- sales: branch + Day/Month/Year filter ---------- */
function initSales() {
    const data = window.KAPE_SALES;
    if (!data) return;

    const branchSel = document.getElementById('salesBranch');
    const modeSel = document.getElementById('salesRangeMode');
    const dayInput = document.getElementById('salesDay');
    const monthInput = document.getElementById('salesMonth');
    const yearSel = document.getElementById('salesYear');
    const wraps = {
        day: document.getElementById('salesDayWrap'),
        month: document.getElementById('salesMonthWrap'),
        year: document.getElementById('salesYearWrap'),
    };

    function weightOf(branch) {
        return branch === 'all' ? 1 : (data.branchWeights[branch] || 0);
    }

    function statsFor(mode, value, weight) {
        let matched = [];
        if (mode === 'day') matched = data.daily.filter((d) => d.date === value);
        if (mode === 'month') matched = data.daily.filter((d) => d.date.startsWith(value));
        if (mode === 'year') matched = data.daily.filter((d) => d.date.startsWith(value));

        let total, orders, cups, real;

        if (matched.length) {
            total = matched.reduce((s, d) => s + d.cash + d.gcash, 0);
            orders = Math.round(total / 193);
            cups = Math.round(orders * 1.42);
            real = true;
        } else {
            const seed = seedNum(mode + value);
            const baseDay = 8200 + seed * 4600;
            total = mode === 'day' ? baseDay : mode === 'month' ? baseDay * 27 : baseDay * 27 * 12;
            orders = Math.round(total / 195);
            cups = Math.round(orders * 1.42);
            real = false;
        }

        total *= weight;
        orders = Math.round(orders * weight);
        cups = Math.round(cups * weight);

        return { total, orders, avg: orders ? total / orders : 0, cups, real };
    }

    function renderStats() {
        const list = currentList();
        const value = list.reduce((sum, i) => sum + i.stock * i.cost, 0);
        const flagged = list.filter((i) => i.stock <= i.reorder).length;
        const statsBox = document.getElementById('inventoryStats');
        const badge = document.getElementById('runningLowBadge');

        if (badge) badge.textContent = flagged;

        if (!statsBox) return;

        statsBox.innerHTML = `
            <article class="stat" style="background:#fff; border:1px solid var(--line,#2a1a11); border-radius:10px; padding:14px 16px; display:flex; flex-direction:column; justify-content:space-between; height:100%;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <span class="stat-label" style="font-size:13px; color:var(--ink-soft,#6b5748);">Total Stock Value</span>
                    <span class="stat-value" style="font-size:24px; font-weight:700; margin:0; text-align:right;">${pesoDec(value)}</span>
                </div>
                <p class="stat-trend" style="font-size:12px; color:var(--ink-soft,#6b5748); margin-top:8px;">Total valuation for the selected branch</p>
            </article>
            
            <article class="stat ${flagged ? 'stat--alert' : ''}" style="background:#fff; border:1px solid var(--line,#2a1a11); border-radius:10px; padding:14px 16px; display:flex; flex-direction:column; justify-content:space-between; height:100%;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <span class="stat-label" style="font-size:13px; color:var(--ink-soft,#6b5748);">Items Running Low</span>
                    <span class="stat-value" style="font-size:24px; font-weight:700; margin:0; text-align:right; color:${flagged ? '#a63d2a' : 'inherit'};">${flagged} Items</span>
                </div>
                <p class="stat-trend" style="font-size:12px; color:var(--ink-soft,#6b5748); margin-top:8px;">Needs immediate replenishment</p>
            </article>

            <article class="stat" style="background:#fff; border:1px solid var(--line,#2a1a11); border-radius:10px; padding:14px 16px; display:flex; flex-direction:column; justify-content:space-between; height:100%;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <span class="stat-label" style="font-size:13px; color:var(--ink-soft,#6b5748);">Total Tracked Ingredients</span>
                    <span class="stat-value" style="font-size:24px; font-weight:700; margin:0; text-align:right;">${list.length} Items</span>
                </div>
                <p class="stat-trend" style="font-size:11px; color:var(--ink-soft,#6b5748); margin-top:8px;">Active items monitored in system</p>
            </article>`;
    }

    function renderBranchRanking() {
        const mode = modeSel.value;
        const value = mode === 'day' ? dayInput.value : mode === 'month' ? monthInput.value : yearSel.value;
        const networkTotal = statsFor(mode, value, 1).total;

        const ranked = Object.entries(data.branchWeights)
            .map(([name, w]) => ({ name, total: networkTotal * w }))
            .sort((a, b) => b.total - a.total);

        const top = ranked[0].total || 1;

        document.getElementById('branchRanking').innerHTML = ranked.map((b) => `
            <li>
                <span class="rank-name">${b.name}</span>
                <span class="rank-bar"><i style="width:${Math.round((b.total / top) * 100)}%"></i></span>
                <span class="rank-num">${peso(b.total)}</span>
            </li>`).join('');
    }

    function renderChart() {
        const weight = weightOf(branchSel.value);
        const scaled = data.daily.map((d) => ({ ...d, cash: d.cash * weight, gcash: d.gcash * weight }));
        const max = Math.max(...scaled.map((d) => d.cash + d.gcash));

        document.getElementById('salesChart').innerHTML = scaled.map((d) => {
            const sum = d.cash + d.gcash;
            const gPct = sum ? Math.round((d.gcash / sum) * 100) : 0;
            const cPct = 100 - gPct;
            return `<div class="chart-col" title="${d.label}: ${peso(sum)}">
                <span class="chart-value">${(sum / 1000).toFixed(1)}k</span>
                <div class="chart-stack" style="height:${Math.round((sum / max) * 100)}%">
                    <div class="chart-seg chart-seg--gcash" style="height:${gPct}%"></div>
                    <div class="chart-seg chart-seg--cash" style="height:${cPct}%"></div>
                </div>
                <span class="chart-label">${d.label}</span>
            </div>`;
        }).join('');
    }

    function renderSplit() {
        const weight = weightOf(branchSel.value);
        const cash = data.daily.reduce((s, d) => s + d.cash, 0) * weight;
        const gcash = data.daily.reduce((s, d) => s + d.gcash, 0) * weight;
        const total = cash + gcash || 1;
        const cashPct = Math.round((cash / total) * 100);
        const gcashPct = 100 - cashPct;

        document.getElementById('salesSplit').innerHTML = `
            <div class="split-bar">
                <div class="split-seg split-seg--cash" style="width:${cashPct}%"></div>
                <div class="split-seg split-seg--gcash" style="width:${gcashPct}%"></div>
            </div>
            <div class="pay-cards">
                <div class="pay-card pay-card--cash">
                    <span class="pay-card-label"><span class="dot dot--cash"></span>Cash</span>
                    <span class="pay-card-amount">${peso(cash)}</span>
                    <span class="pay-card-pct">${cashPct}% of takings</span>
                </div>
                <div class="pay-card pay-card--gcash">
                    <span class="pay-card-label"><span class="dot dot--gcash"></span>GCash</span>
                    <span class="pay-card-amount">${peso(gcash)}</span>
                    <span class="pay-card-pct">${gcashPct}% of takings</span>
                </div>
            </div>`;
    }

    function renderMenu() {
        const weight = weightOf(branchSel.value);
        const totalRevenue = data.topItems.reduce((s, i) => s + i.revenue, 0);
        const top = data.topItems[0].sold;

        document.getElementById('menuPerformance').innerHTML = data.topItems.map((item) => {
            const sold = Math.round(item.sold * weight);
            const revenue = item.revenue * weight;
            return `<tr>
                <td>${item.name}</td>
                <td class="ta-r mono">${sold}</td>
                <td class="ta-r mono">${peso(revenue)}</td>
                <td class="ta-r mono">${Math.round((item.revenue / totalRevenue) * 100)}%</td>
                <td><span class="rank-bar rank-bar--inline"><i style="width:${Math.round((item.sold / top) * 100)}%"></i></span></td>
            </tr>`;
        }).join('');
    }

    function renderAll() { renderStats(); renderBranchRanking(); renderChart(); renderSplit(); renderMenu(); }

    modeSel.addEventListener('change', () => {
        Object.values(wraps).forEach((w) => { w.hidden = true; });
        wraps[modeSel.value].hidden = false;
        renderStats();
        renderBranchRanking();
    });

    [branchSel].forEach((el) => el.addEventListener('change', renderAll));
    [dayInput, monthInput, yearSel].forEach((el) => el.addEventListener('change', () => { renderStats(); renderBranchRanking(); }));

    renderAll();
}

// pagination
(function dashboardOrdersPagination() {
    const table = document.getElementById('dashboardOrdersTable');
    if (!table) return;

    const paginationBar = document.getElementById('dashboardPagination');
    const pageInfo = document.getElementById('dashboardPageInfo');
    const pageNumbers = document.getElementById('dashPageNumbers');
    const prevBtn = document.getElementById('dashPrevBtn');
    const nextBtn = document.getElementById('dashNextBtn');

    const rows = Array.from(table.tBodies[0].rows);
    const pageSize = 5;
    let currentPage = 1;
    const totalRows = rows.length;
    const totalPages = Math.ceil(totalRows / pageSize);

    if (totalRows <= pageSize) {
        if (paginationBar) paginationBar.hidden = true;
        return;
    }

    function render() {
        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;

        rows.forEach((row, index) => {
            row.hidden = !(index >= start && index < end);
        });

        if (pageInfo) {
            pageInfo.textContent = `Showing ${start + 1}–${Math.min(end, totalRows)} of ${totalRows} orders`;
        }

        if (pageNumbers) {
            pageNumbers.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = i;
                btn.className = i === currentPage ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm';
                btn.style.padding = '3px 8px';
                btn.addEventListener('click', () => {
                    currentPage = i;
                    render();
                });
                pageNumbers.appendChild(btn);
            }
        }

        if (prevBtn) prevBtn.disabled = currentPage === 1;
        if (nextBtn) nextBtn.disabled = currentPage === totalPages;

        if (paginationBar) paginationBar.hidden = false;
    }

    prevBtn?.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            render();
        }
    });

    nextBtn?.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            render();
        }
    });

    render();
})();