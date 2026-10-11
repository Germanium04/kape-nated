/* =========================================================================
   kape-nated admin — complete JS module (public/js/Admin.js)
   ========================================================================= */

/* -------------------------------------------------------------------------
   1. USER MENU DROPDOWN & MODALS
   ------------------------------------------------------------------------- */
(function userMenu() {
    const btn = document.getElementById('userMenuBtn');
    const menu = document.getElementById('userDropdown');
    if (!btn || !menu) return;

    function close() { menu.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
    function open() { menu.hidden = false; btn.setAttribute('aria-expanded', 'true'); }

    btn.addEventListener('click', (e) => { e.stopPropagation(); menu.hidden ? open() : close(); });
    document.addEventListener('click', (e) => { if (!menu.hidden && !menu.contains(e.target) && e.target !== btn) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !menu.hidden) close(); });
})();

document.addEventListener('click', (e) => {
    const openBtn = e.target.closest('[data-open-modal]');
    if (openBtn && openBtn.dataset.openModal) {
        const el = document.getElementById(openBtn.dataset.openModal);
        if (el) { el.hidden = false; document.body.classList.add('is-locked'); }
    }

    const closeBtn = e.target.closest('[data-close-modal]');
    if (closeBtn && closeBtn.dataset.closeModal) {
        const el = document.getElementById(closeBtn.dataset.closeModal);
        if (el) { el.hidden = true; document.body.classList.remove('is-locked'); }
    }
});

/* -------------------------------------------------------------------------
   2. FORMATTING HELPERS
   ------------------------------------------------------------------------- */
const peso = (n) => '₱' + Math.round(n || 0).toLocaleString('en-PH');
const pesoDec = (n) => '₱' + (n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/* -------------------------------------------------------------------------
   3. RECEIPTS & TRANSACTIONS MODULE
   ------------------------------------------------------------------------- */
function initReceipts() {
    const table = document.getElementById('receiptTable');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const PAGE_SIZE = 10;
    let currentPage = 1;

    const branchSelect = document.getElementById('receiptBranch');
    const paymentSelect = document.getElementById('receiptFilter');
    const rangeModeSelect = document.getElementById('receiptRangeMode');
    const searchInput = document.getElementById('receiptSearch');

    const dayWrap = document.getElementById('rangeDayWrap');
    const monthWrap = document.getElementById('rangeMonthWrap');
    const yearWrap = document.getElementById('rangeYearWrap');

    const dayInput = document.getElementById('rangeDay');
    const monthInput = document.getElementById('rangeMonth');
    const yearInput = document.getElementById('rangeYear');

    const emptyNote = document.getElementById('receiptEmpty');
    const pageInfo = document.getElementById('pageInfo');
    const pageNumbers = document.getElementById('pageNumbers');
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');

    const reconExpected = document.getElementById('reconExpected');
    const reconActual = document.getElementById('reconActual');
    const reconVariance = document.getElementById('reconVariance');

    function toggleRangeInputs() {
        const mode = rangeModeSelect?.value || 'all';
        if (dayWrap) dayWrap.hidden = mode !== 'day';
        if (monthWrap) monthWrap.hidden = mode !== 'month';
        if (yearWrap) yearWrap.hidden = mode !== 'year';
    }

    function getFilteredRows() {
        const branchVal = (branchSelect?.value || 'all').trim().toLowerCase();
        const paymentVal = (paymentSelect?.value || 'all').trim().toLowerCase();
        const rangeMode = rangeModeSelect?.value || 'all';
        const searchVal = (searchInput?.value || '').toLowerCase().trim();

        const selectedDay = dayInput?.value || '';
        const selectedMonth = monthInput?.value || '';
        const selectedYear = yearInput?.value || '';

        return rows.filter(row => {
            const rowBranch = (row.dataset.branch || '').trim().toLowerCase();
            const rowPayment = (row.dataset.payment || '').trim().toLowerCase();
            const rowSearch = (row.dataset.search || '').toLowerCase();
            const rowDate = (row.dataset.date || '').trim();

            if (branchVal !== 'all' && rowBranch !== branchVal) return false;
            if (paymentVal !== 'all' && rowPayment !== paymentVal) return false;
            if (searchVal && !rowSearch.includes(searchVal)) return false;

            if (rangeMode === 'day' && selectedDay && rowDate !== selectedDay) return false;
            if (rangeMode === 'month' && selectedMonth && !rowDate.startsWith(selectedMonth)) return false;
            if (rangeMode === 'year' && selectedYear && !rowDate.startsWith(selectedYear)) return false;

            return true;
        });
    }

    function renderReceiptsTable() {
        toggleRangeInputs();
        const filtered = getFilteredRows();
        const totalItems = filtered.length;
        const totalPages = Math.ceil(totalItems / PAGE_SIZE) || 1;

        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        rows.forEach(r => r.style.display = 'none');

        if (totalItems === 0) {
            if (emptyNote) emptyNote.hidden = false;
            if (pageInfo) pageInfo.textContent = 'Showing 0 transactions';
            if (prevBtn) prevBtn.disabled = true;
            if (nextBtn) nextBtn.disabled = true;
            if (pageNumbers) pageNumbers.innerHTML = '';
            updateCashDrawerAudit([]);
            return;
        }

        if (emptyNote) emptyNote.hidden = true;

        const startIdx = (currentPage - 1) * PAGE_SIZE;
        const endIdx = Math.min(startIdx + PAGE_SIZE, totalItems);
        const visibleRows = filtered.slice(startIdx, endIdx);

        visibleRows.forEach(r => r.style.display = '');

        if (pageInfo) pageInfo.textContent = `Showing ${startIdx + 1}-${endIdx} of ${totalItems} transactions`;

        if (prevBtn) {
            prevBtn.disabled = currentPage <= 1;
            prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; renderReceiptsTable(); } };
        }

        if (nextBtn) {
            nextBtn.disabled = currentPage >= totalPages;
            nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; renderReceiptsTable(); } };
        }

        buildPageButtons(totalPages);
        updateCashDrawerAudit(filtered);
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
                btn.onclick = () => { currentPage = i; renderReceiptsTable(); };
                pageNumbers.appendChild(btn);
            } else if (pageNumbers.lastElementChild?.textContent !== '…') {
                const span = document.createElement('span');
                span.textContent = '…';
                span.style.padding = '0 4px';
                pageNumbers.appendChild(span);
            }
        }
    }

    function updateCashDrawerAudit(filteredRows) {
        if (!reconExpected) return;

        let cashSum = 0;
        filteredRows.forEach(row => {
            const isCash = (row.dataset.payment || '').toLowerCase() === 'cash';
            const isCompleted = row.innerHTML.includes('Completed');
            if (isCash && isCompleted) {
                const totalCell = row.querySelector('.mono.ta-r')?.textContent || '0';
                const num = parseFloat(totalCell.replace(/[^0-9.]/g, '')) || 0;
                cashSum += num;
            }
        });

        reconExpected.value = pesoDec(cashSum);

        const actualVal = parseFloat(reconActual?.value) || 0;
        if (reconActual?.value !== '' && reconVariance) {
            const diff = actualVal - cashSum;
            reconVariance.value = (diff >= 0 ? '+' : '') + pesoDec(diff);
            reconVariance.style.color = diff < 0 ? 'var(--flag, #a63d2a)' : 'var(--good, #46703f)';
        } else if (reconVariance) {
            reconVariance.value = '₱0.00';
            reconVariance.style.color = '';
        }
    }

    [branchSelect, paymentSelect, rangeModeSelect, dayInput, monthInput, yearInput].forEach(el => {
        el?.addEventListener('change', () => { currentPage = 1; renderReceiptsTable(); });
    });

    searchInput?.addEventListener('input', () => { currentPage = 1; renderReceiptsTable(); });
    reconActual?.addEventListener('input', () => { updateCashDrawerAudit(getFilteredRows()); });

    renderReceiptsTable();
}

/* -------------------------------------------------------------------------
   4. REPORTS MODULE
   ------------------------------------------------------------------------- */
function initReports() {
    const masterData = window.KAPE_REPORTS;
    if (!masterData) return;

    let reconcilePage = 1;
    let movementsPage = 1;
    let attentionPage = 1;
    let menuPage = 1;
    const PAGE_SIZE = 5;

    // View Switchers
    const btnSales = document.getElementById('btnReportSales');
    const btnInv = document.getElementById('btnReportInventory');
    const secSales = document.getElementById('sectionSalesReport');
    const secInv = document.getElementById('sectionInventoryReport');
    const invSubGroup = document.getElementById('invSubTabGroup');

    // Export & Filter Controls
    const btnExportReport = document.getElementById('btnExportReport');
    const btnConfirmExport = document.getElementById('btnConfirmExport');
    const exportModal = document.getElementById('exportReportModal');
    const reportBranch = document.getElementById('reportBranch');
    const reportViewBy = document.getElementById('reportViewBy');
    const reportDate = document.getElementById('reportDate');
    const reportMonth = document.getElementById('reportMonth');
    const reportYear = document.getElementById('reportYear');
    const reportDayWrap = document.getElementById('reportDayWrap');
    const reportMonthWrap = document.getElementById('reportMonthWrap');
    const reportYearWrap = document.getElementById('reportYearWrap');
    const selectInvCategory = document.getElementById('selectInvCategory');

    function toggleReportDateInputs() {
        const mode = reportViewBy?.value || 'day';
        if (reportDayWrap) reportDayWrap.hidden = mode !== 'day';
        if (reportMonthWrap) reportMonthWrap.hidden = mode !== 'month';
        if (reportYearWrap) reportYearWrap.hidden = mode !== 'year';
    }

    function getReportDateValue() {
        const mode = reportViewBy?.value || 'day';
        if (mode === 'month') return reportMonth?.value || '';
        if (mode === 'year') return reportYear?.value || '';
        return reportDate?.value || '';
    }

    btnSales?.addEventListener('click', () => {
        btnSales.className = 'tab-btn tab-btn--active';
        btnInv.className = 'tab-btn tab-btn--inactive';
        if (secSales) secSales.removeAttribute('hidden');
        if (secInv) secInv.setAttribute('hidden', 'true');
        if (invSubGroup) invSubGroup.hidden = true;
    });

    btnInv?.addEventListener('click', () => {
        btnInv.className = 'tab-btn tab-btn--active';
        btnSales.className = 'tab-btn tab-btn--inactive';
        if (secSales) secSales.setAttribute('hidden', 'true');
        if (secInv) secInv.removeAttribute('hidden');
        if (invSubGroup) invSubGroup.hidden = false;
        renderInventoryReport();
    });

    btnExportReport?.addEventListener('click', () => {
        if (exportModal) {
            exportModal.hidden = false;
            document.body.classList.add('is-locked');
        }
    });

    btnConfirmExport?.addEventListener('click', () => {
        const scope = document.querySelector('input[name="exportScope"]:checked')?.value || 'both';
        const format = document.querySelector('input[name="exportFormat"]:checked')?.value || 'csv';
        const branch = reportBranch?.value || 'all';
        const viewBy = reportViewBy?.value || 'day';
        // Normalize to a full date the backend's `date` validation accepts.
        let date = getReportDateValue() || '';
        if (viewBy === 'month' && date) date = date + '-01';
        if (viewBy === 'year' && date) date = date + '-01-01';

        const params = new URLSearchParams({ scope, branch, view_by: viewBy, date });
        const base = format === 'pdf' ? '/admin/reports/export/pdf' : '/admin/reports/export/csv';
        window.location.href = `${base}?${params.toString()}`;

        if (exportModal) {
            exportModal.hidden = true;
            document.body.classList.remove('is-locked');
        }
    });

    function isDateInSelectedRange(recordDateStr) {
        if (!recordDateStr) return false;
        const selectedDateVal = getReportDateValue();
        if (!selectedDateVal) return true;

        const viewBy = reportViewBy?.value || 'day';
        const rawRecDate = recordDateStr.split(' ')[0];

        if (viewBy === 'day') {
            return rawRecDate === selectedDateVal;
        } else if (viewBy === 'month') {
            return rawRecDate.substring(0, 7) === selectedDateVal.substring(0, 7);
        } else if (viewBy === 'year') {
            return rawRecDate.substring(0, 4) === selectedDateVal.substring(0, 4);
        }
        return true;
    }

    function getBranchFilteredOrders() {
        const selectedBranch = (reportBranch?.value || 'all').toLowerCase();
        return (masterData.orders || []).filter(order => {
            if (selectedBranch !== 'all' && (order.branch || '').toLowerCase() !== selectedBranch) {
                return false;
            }
            return true;
        });
    }

    function getFilteredOrders() {
        return getBranchFilteredOrders().filter(order => isDateInSelectedRange(order.date));
    }

    function updatePrintRangeText() {
        const viewBy = reportViewBy?.value || 'day';
        const dateInput = getReportDateValue();
        const branch = reportBranch?.value || 'all';
        const printTextEl = document.getElementById('printReportRangeText');

        if (!printTextEl) return;
        let dateFormatted = 'Selected Period';
        if (viewBy === 'month' && dateInput) {
            dateFormatted = new Date(dateInput + '-01').toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
        } else if (viewBy === 'year' && dateInput) {
            dateFormatted = dateInput;
        } else if (dateInput) {
            dateFormatted = new Date(dateInput).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        }
        const branchFormatted = branch === 'all' ? 'All Branches' : branch;

        printTextEl.textContent = `${dateFormatted} (${viewBy.toUpperCase()}) · ${branchFormatted}`;
    }

    /* ---------------------------------------------------------------------
       4.1 SALES REPORT RENDERER
       --------------------------------------------------------------------- */
    function renderSalesReport() {
        const orders = getFilteredOrders().filter(o => o.status === 'Completed');

        let totalGross = 0;
        let totalCash = 0;
        let totalGcash = 0;
        let totalCups = 0;
        const itemMap = {};
        const branchMap = {};

        orders.forEach(o => {
            totalGross += o.total || 0;
            if (o.payment === 'Cash') totalCash += o.total || 0;
            if (o.payment === 'Gcash' || o.payment === 'GCash') totalGcash += o.total || 0;

            branchMap[o.branch] = (branchMap[o.branch] || 0) + (o.total || 0);

            (o.items || []).forEach(item => {
                totalCups += item.qty || 0;
                if (!itemMap[item.name]) {
                    itemMap[item.name] = { name: item.name, sold: 0, revenue: 0 };
                }
                itemMap[item.name].sold += item.qty || 0;
                itemMap[item.name].revenue += (item.qty * item.price) || 0;
            });
        });

        const totalOrders = orders.length;
        const avgTicket = totalOrders ? totalGross / totalOrders : 0;

        const elGross = document.getElementById('valGrossSales');
        const elOrders = document.getElementById('valTotalOrders');
        const elAvg = document.getElementById('valAvgTicket');
        const elCups = document.getElementById('valCupsServed');

        if (elGross) elGross.textContent = peso(totalGross);
        if (elOrders) elOrders.textContent = totalOrders;
        if (elAvg) elAvg.textContent = pesoDec(avgTicket);
        if (elCups) elCups.textContent = totalCups;

        const branchOrders = getBranchFilteredOrders().filter(o => o.status === 'Completed');
        const baseDate = getReportDateValue() ? new Date(getReportDateValue() + (reportViewBy?.value === 'month' ? '-01' : (reportViewBy?.value === 'year' ? '-01-01' : ''))) : new Date();
        const dailyTakingsMap = {};

        const last7Days = [];
        for (let i = 6; i >= 0; i--) {
            const d = new Date(baseDate);
            d.setDate(d.getDate() - i);
            const iso = d.toISOString().split('T')[0];
            const label = d.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric' });
            last7Days.push({ iso, label });
            dailyTakingsMap[iso] = 0;
        }

        branchOrders.forEach(o => {
            const orderDateIso = (o.date || '').split(' ')[0];
            if (dailyTakingsMap[orderDateIso] !== undefined) {
                dailyTakingsMap[orderDateIso] += (o.total || 0);
            }
        });

        const chartContainer = document.getElementById('chartSalesOverTime');
        if (chartContainer) {
            const dayValues = last7Days.map(d => dailyTakingsMap[d.iso] || 0);
            const maxVal = Math.max(...dayValues) || 1;
            const minVal = Math.min(...dayValues.filter(v => v > 0)) || 0;
            const range = maxVal - minVal || 1;

            chartContainer.innerHTML = last7Days.map(d => {
                const sum = dailyTakingsMap[d.iso] || 0;
                let heightPct = 15;
                if (sum > 0) {
                    heightPct = Math.round(15 + ((sum - minVal) / range) * 85);
                }
                const formattedValue = sum >= 1000 ? (sum / 1000).toFixed(1) + 'k' : sum;
                
                return `<div class="chart-col" title="${d.label}: ${peso(sum)}">
                    <span class="chart-value">${formattedValue}</span>
                    <div class="chart-stack" style="height: ${heightPct}%;">
                        <div class="chart-seg chart-seg--cash" style="height: 100%; background: var(--tan, #cf9a6a); border-radius: 4px;"></div>
                    </div>
                    <span class="chart-label">${d.label}</span>
                </div>`;
            }).join('');
        }

        const overall = totalCash + totalGcash || 1;
        const cashPct = Math.round((totalCash / overall) * 100);
        const gcashPct = 100 - cashPct;

        const boxSplit = document.getElementById('boxPaymentSplit');
        if (boxSplit) {
            boxSplit.innerHTML = `
                <div class="split-bar" style="height: 14px; border-radius: 7px; overflow: hidden; display: flex; background: #e5e7eb; margin-bottom: 16px;">
                    <div class="split-seg split-seg--cash" style="width:${cashPct}%; background: var(--tan, #cf9a6a);"></div>
                    <div class="split-seg split-seg--gcash" style="width:${gcashPct}%; background: #e0a96d; opacity: 0.6;"></div>
                </div>
                <div class="pay-cards" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; height: 100%;">
                    <div class="pay-card pay-card--cash" style="padding: 16px; border: 1px solid var(--border, #e5e7eb); border-radius: 8px; display: flex; flex-direction: column; justify-content: space-between;">
                        <span class="pay-card-label" style="display: flex; align-items: center; gap: 6px; font-weight: 600;"><span class="dot dot--cash" style="width: 10px; height: 10px; border-radius: 50%; background: var(--tan, #cf9a6a);"></span>Cash</span>
                        <span class="pay-card-amount" style="font-size: 22px; font-weight: 700; margin: 8px 0;">${peso(totalCash)}</span>
                        <span class="pay-card-pct" style="color: #6b7280; font-size: 13px;">${cashPct}%</span>
                    </div>
                    <div class="pay-card pay-card--gcash" style="padding: 16px; border: 1px solid var(--border, #e5e7eb); border-radius: 8px; display: flex; flex-direction: column; justify-content: space-between;">
                        <span class="pay-card-label" style="display: flex; align-items: center; gap: 6px; font-weight: 600;"><span class="dot dot--gcash" style="width: 10px; height: 10px; border-radius: 50%; background: #e0a96d;"></span>GCash</span>
                        <span class="pay-card-amount" style="font-size: 22px; font-weight: 700; margin: 8px 0;">${peso(totalGcash)}</span>
                        <span class="pay-card-pct" style="color: #6b7280; font-size: 13px;">${gcashPct}%</span>
                    </div>
                </div>`;
        }

        const branchList = document.getElementById('listSalesByBranch');
        const branchPanel = branchList?.closest('.panel');
        const selectedBranchVal = (reportBranch?.value || 'all').toLowerCase();

        if (branchPanel) {
            if (selectedBranchVal !== 'all') {
                branchPanel.style.display = 'none';
            } else {
                branchPanel.style.display = '';

                const sortedBranches = Object.entries(branchMap).sort((a, b) => b[1] - a[1]);
                const maxBranchVal = sortedBranches[0]?.[1] || 1;

                if (sortedBranches.length === 0) {
                    branchList.innerHTML = '<li class="empty-note">No sales recorded for this selection.</li>';
                } else {
                    branchList.innerHTML = sortedBranches.map(([bName, val]) => `
                        <li>
                            <span class="rank-name" style="width: 140px;">${bName}</span>
                            <span class="rank-bar"><i style="width: ${Math.round((val / maxBranchVal) * 100)}%"></i></span>
                            <span class="rank-num">${peso(val)}</span>
                        </li>
                    `).join('');
                }
            }
        }

        const sortedItems = Object.values(itemMap).sort((a, b) => b.revenue - a.revenue);
        const barsContainer = document.getElementById('barsMenuRevenue');

        if (barsContainer) {
            const maxRev = sortedItems[0]?.revenue || 1;
            barsContainer.innerHTML = sortedItems.slice(0, 5).map(item => `
                <div class="hbar-row">
                    <span class="hbar-label">${item.name}</span>
                    <div class="hbar-track">
                        <div class="hbar-fill" style="width: ${Math.round((item.revenue / maxRev) * 100)}%"></div>
                    </div>
                    <span class="hbar-value">${peso(item.revenue)}</span>
                </div>
            `).join('') || '<p class="empty-note">No drink sales available for this range.</p>';
        }

        renderPaginatedMenuBreakdown(sortedItems, totalGross);
    }

    function renderPaginatedMenuBreakdown(topItems, totalRev) {
        const tableBody = document.getElementById('tableMenuBreakdown');
        if (!tableBody) return;

        const totalItems = topItems.length;
        const totalPages = Math.ceil(totalItems / PAGE_SIZE) || 1;

        if (menuPage > totalPages) menuPage = totalPages;
        if (menuPage < 1) menuPage = 1;

        const startIdx = (menuPage - 1) * PAGE_SIZE;
        const pageItems = topItems.slice(startIdx, startIdx + PAGE_SIZE);

        tableBody.innerHTML = pageItems.map(item => `
            <tr>
                <td><b>${item.name}</b></td>
                <td class="ta-r mono">${item.sold}</td>
                <td class="ta-r mono">${peso(item.revenue)}</td>
                <td class="ta-r mono">${totalRev ? Math.round((item.revenue / totalRev) * 100) : 0}%</td>
            </tr>
        `).join('') || '<tr><td colspan="4" class="empty-note ta-c">No item sales recorded.</td></tr>';

        const prevBtn = document.getElementById('btnMenuPrev');
        const nextBtn = document.getElementById('btnMenuNext');
        const pageInfo = document.getElementById('menuPageInfo');

        if (pageInfo) {
            const endIdx = Math.min(startIdx + PAGE_SIZE, totalItems);
            pageInfo.textContent = `Showing ${totalItems ? startIdx + 1 : 0} to ${endIdx} of ${totalItems} drinks`;
        }

        if (prevBtn) {
            prevBtn.disabled = menuPage <= 1;
            prevBtn.onclick = () => { if (menuPage > 1) { menuPage--; renderPaginatedMenuBreakdown(topItems, totalRev); } };
        }

        if (nextBtn) {
            nextBtn.disabled = menuPage >= totalPages;
            nextBtn.onclick = () => { if (menuPage < totalPages) { menuPage++; renderPaginatedMenuBreakdown(topItems, totalRev); } };
        }
    }

    /* ---------------------------------------------------------------------
       4.2 INVENTORY REPORT RENDERER
       --------------------------------------------------------------------- */
    function renderInventoryReport() {
        const selectedBranch = (reportBranch?.value || 'all').toLowerCase();
        const categoryVal = selectInvCategory?.value || 'overview';

        let rawIngredients = masterData.ingredients || [];
        
        // Dynamic branch filtering for inventory stock items
        let ingredients = rawIngredients;
        if (selectedBranch !== 'all') {
            ingredients = rawIngredients.filter(i => (i.branch_name || '').toLowerCase() === selectedBranch);
        }

        const uniqueIngredientsMap = {};
        ingredients.forEach(i => {
            const key = selectedBranch === 'all' ? i.id : `${i.id}_${i.branch_id || i.branch_name}`;
            if (!uniqueIngredientsMap[key]) {
                uniqueIngredientsMap[key] = { ...i };
            } else {
                uniqueIngredientsMap[key].stock += i.stock;
                uniqueIngredientsMap[key].used_today += i.used_today;
            }
        });
        const reconciledList = Object.values(uniqueIngredientsMap);

        const lowStockList = reconciledList.filter(i => (i.stock <= i.reorder));
        const elReorder = document.getElementById('invCountReorder');
        const elTrackedNote = document.getElementById('invTrackedNote');
        const panelAttention = document.getElementById('panelItemsNeedingAttention');

        if (elReorder) elReorder.textContent = lowStockList.length;
        if (elTrackedNote) elTrackedNote.textContent = `Of ${reconciledList.length} tracked ingredients`;

        if (panelAttention) {
            if (lowStockList.length === 0 || categoryVal === 'movements' || categoryVal === 'value') {
                panelAttention.hidden = true;
            } else {
                panelAttention.hidden = false;
                renderPaginatedAttentionItems(lowStockList);
            }
        }

        let movements = masterData.movements || [];
        movements = movements.filter(m => {
            const movementBranch = (m.branch || '').toLowerCase();
            if (selectedBranch !== 'all' && movementBranch !== 'all' && movementBranch !== selectedBranch) {
                return false;
            }
            return isDateInSelectedRange(m.date);
        });

        let stockReceivedVal = 0;
        let wasteCostVal = 0;
        let closingVal = 0;

        reconciledList.forEach(i => {
            closingVal += (i.stock * (i.cost || 0));
        });

        movements.forEach(m => {
            const ing = rawIngredients.find(i => i.name === m.ingredient);
            const cost = ing?.cost || 0;
            const reason = (m.reason || '').toLowerCase();
            if (m.change > 0 && !reason.includes('transfer')) {
                stockReceivedVal += (m.change * cost);
            } else if (m.change < 0 && (reason.includes('spill') || reason.includes('spoil') || reason.includes('waste'))) {
                wasteCostVal += (Math.abs(m.change) * cost);
            }
        });

        const elReceived = document.getElementById('valStockReceived');
        const elWaste = document.getElementById('valWasteCost');
        const elClosing = document.getElementById('valClosingStockValue');

        if (elReceived) elReceived.textContent = pesoDec(stockReceivedVal);
        if (elWaste) elWaste.textContent = pesoDec(wasteCostVal);
        if (elClosing) elClosing.textContent = pesoDec(closingVal);

        const boxTrend = document.getElementById('boxIngredientUsageTrend');
        if (boxTrend) {
            const groupedMap = {};
            
            // Calculate usage trend from movements matching date and branch range
            movements.forEach(m => {
                if (m.change < 0) {
                    const consumed = Math.abs(m.change);
                    const ingName = m.ingredient;
                    const matchIng = rawIngredients.find(i => i.name === ingName);
                    const unit = matchIng ? matchIng.unit : '';

                    if (!groupedMap[ingName]) {
                        groupedMap[ingName] = { name: ingName, unit: unit, used: 0 };
                    }
                    groupedMap[ingName].used += consumed;
                }
            });

            const uniqueUsedItems = Object.values(groupedMap)
                .sort((a, b) => b.used - a.used)
                .slice(0, 8);

            if (uniqueUsedItems.length === 0) {
                boxTrend.innerHTML = '<div class="empty-note ta-c" style="padding:16px;">No usage recorded for this range.</div>';
            } else {
                boxTrend.innerHTML = uniqueUsedItems.map(i => `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border, #eee); font-size:13px;">
                        <span><b>${i.name}</b></span>
                        <strong class="mono" style="font-size:14px;">${i.used.toLocaleString()} ${i.unit}</strong>
                    </div>
                `).join('');
            }
        }

        const boxMovements = document.getElementById('boxRecentMovements')?.closest('.panel');
        const boxReconcile = document.getElementById('tableStockReconciliation')?.closest('.panel');
        const boxUsageTrend = document.getElementById('boxIngredientUsageTrend')?.closest('.panel');

        if (categoryVal === 'movements') {
            if (boxMovements) boxMovements.style.display = '';
            if (boxReconcile) boxReconcile.style.display = 'none';
            if (boxUsageTrend) boxUsageTrend.style.display = 'none';
        } else if (categoryVal === 'usage') {
            if (boxMovements) boxMovements.style.display = 'none';
            if (boxReconcile) boxReconcile.style.display = '';
            if (boxUsageTrend) boxUsageTrend.style.display = '';
        } else if (categoryVal === 'value') {
            if (boxMovements) boxMovements.style.display = 'none';
            if (boxReconcile) boxReconcile.style.display = '';
            if (boxUsageTrend) boxUsageTrend.style.display = 'none';
        } else {
            if (boxMovements) boxMovements.style.display = '';
            if (boxReconcile) boxReconcile.style.display = '';
            if (boxUsageTrend) boxUsageTrend.style.display = '';
        }

        renderPaginatedMovements(movements);
        renderPaginatedStockReconciliation(reconciledList);
    }

    function renderPaginatedAttentionItems(lowStockList) {
        const tbodyAttention = document.getElementById('tableItemsNeedingAttention');
        if (!tbodyAttention) return;

        const totalItems = lowStockList.length;
        const totalPages = Math.ceil(totalItems / PAGE_SIZE) || 1;

        if (attentionPage > totalPages) attentionPage = totalPages;
        if (attentionPage < 1) attentionPage = 1;

        const startIdx = (attentionPage - 1) * PAGE_SIZE;
        const pageItems = lowStockList.slice(startIdx, startIdx + PAGE_SIZE);

        tbodyAttention.innerHTML = pageItems.map(i => `
            <tr>
                <td><b>${i.name}</b></td>
                <td class="ta-r mono">${i.stock} ${i.unit}</td>
                <td class="ta-r mono">${i.reorder} ${i.unit}</td>
                <td class="ta-r muted">—</td>
                <td class="ta-r"><span class="badge badge--low">Low</span></td>
            </tr>
        `).join('') || '<tr><td colspan="5" class="empty-note ta-c">No items currently below reorder level.</td></tr>';

        const prevBtn = document.getElementById('btnAttentionPrev');
        const nextBtn = document.getElementById('btnAttentionNext');
        const pageInfo = document.getElementById('attentionPageInfo');

        if (pageInfo) {
            const endIdx = Math.min(startIdx + PAGE_SIZE, totalItems);
            pageInfo.textContent = `Showing ${totalItems ? startIdx + 1 : 0} to ${endIdx} of ${totalItems} low stock items`;
        }

        if (prevBtn) {
            prevBtn.disabled = attentionPage <= 1;
            prevBtn.onclick = () => { if (attentionPage > 1) { attentionPage--; renderPaginatedAttentionItems(lowStockList); } };
        }

        if (nextBtn) {
            nextBtn.disabled = attentionPage >= totalPages;
            nextBtn.onclick = () => { if (attentionPage < totalPages) { attentionPage++; renderPaginatedAttentionItems(lowStockList); } };
        }
    }

    function renderPaginatedMovements(movements) {
        const tbodyMovements = document.getElementById('tableRecentMovements');
        if (!tbodyMovements) return;

        const totalItems = movements.length;
        const totalPages = Math.ceil(totalItems / PAGE_SIZE) || 1;

        if (movementsPage > totalPages) movementsPage = totalPages;
        if (movementsPage < 1) movementsPage = 1;

        const startIdx = (movementsPage - 1) * PAGE_SIZE;
        const pageItems = movements.slice(startIdx, startIdx + PAGE_SIZE);

        tbodyMovements.innerHTML = pageItems.map(m => `
            <tr>
                <td class="muted">${m.date}</td>
                <td><b>${m.ingredient}</b></td>
                <td><span class="badge badge--neutral">${m.reason}</span></td>
                <td class="ta-r mono ${m.change >= 0 ? 'text-green' : 'text-red'}">${m.change >= 0 ? '+' : ''}${m.change}</td>
            </tr>
        `).join('') || '<tr><td colspan="4" class="empty-note ta-c">No recent movements recorded for this range.</td></tr>';

        const prevBtn = document.getElementById('btnMovementsPrev');
        const nextBtn = document.getElementById('btnMovementsNext');
        const pageInfo = document.getElementById('movementsPageInfo');

        if (pageInfo) {
            const endIdx = Math.min(startIdx + PAGE_SIZE, totalItems);
            pageInfo.textContent = `Showing ${totalItems ? startIdx + 1 : 0} to ${endIdx} of ${totalItems} movements`;
        }

        if (prevBtn) {
            prevBtn.disabled = movementsPage <= 1;
            prevBtn.onclick = () => { if (movementsPage > 1) { movementsPage--; renderPaginatedMovements(movements); } };
        }

        if (nextBtn) {
            nextBtn.disabled = movementsPage >= totalPages;
            nextBtn.onclick = () => { if (movementsPage < totalPages) { movementsPage++; renderPaginatedMovements(movements); } };
        }
    }

    function renderPaginatedStockReconciliation(ingredients) {
        const tbodyReconcile = document.getElementById('tableStockReconciliation');
        if (!tbodyReconcile) return;

        const selectedBranch = (document.getElementById('reportBranch')?.value || 'all').toLowerCase();
        const movements = masterData.movements || [];

        const totalItems = ingredients.length;
        const totalPages = Math.ceil(totalItems / PAGE_SIZE) || 1;

        if (reconcilePage > totalPages) reconcilePage = totalPages;
        if (reconcilePage < 1) reconcilePage = 1;

        const startIdx = (reconcilePage - 1) * PAGE_SIZE;
        const pageItems = ingredients.slice(startIdx, startIdx + PAGE_SIZE);

        tbodyReconcile.innerHTML = pageItems.map(i => {
            const ingMovements = movements.filter(m => {
                const matchesIng = m.ingredient === i.name;
                const mBranch = (m.branch || '').toLowerCase();
                const matchesBranch = selectedBranch === 'all' || mBranch === selectedBranch;
                return matchesIng && matchesBranch && isDateInSelectedRange(m.date);
            });

            let receivedQty = 0;
            let wasteQty = 0;
            let consumedQty = 0;

            ingMovements.forEach(m => {
                const r = (m.reason || '').toLowerCase();
                if (m.change > 0 && !r.includes('transfer')) {
                    receivedQty += m.change;
                } else if (m.change < 0 && (r.includes('spill') || r.includes('spoil') || r.includes('waste'))) {
                    wasteQty += Math.abs(m.change);
                } else if (m.change < 0) {
                    consumedQty += Math.abs(m.change);
                }
            });

            const closingStock = i.stock;
            const openingStock = closingStock + consumedQty + wasteQty - receivedQty;

            return `<tr>
                <td><b>${i.name}</b></td>
                <td class="ta-r mono">${openingStock.toLocaleString()} ${i.unit}</td>
                <td class="ta-r mono ${receivedQty > 0 ? 'text-green' : 'muted'}">${receivedQty > 0 ? '+' + receivedQty.toLocaleString() : '0'} ${i.unit}</td>
                <td class="ta-r mono ${consumedQty > 0 ? 'text-red' : 'muted'}">${consumedQty > 0 ? '-' + consumedQty.toLocaleString() : '0'} ${i.unit}</td>
                <td class="ta-r mono ${wasteQty > 0 ? 'text-red' : 'muted'}">${wasteQty > 0 ? '-' + wasteQty.toLocaleString() : '0'} ${i.unit}</td>
                <td class="ta-r muted">0 ${i.unit}</td>
                <td class="ta-r mono"><b>${closingStock.toLocaleString()} ${i.unit}</b></td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" class="empty-note ta-c">No ingredients found.</td></tr>';

        const prevBtn = document.getElementById('btnReconcilePrev');
        const nextBtn = document.getElementById('btnReconcileNext');
        const pageInfo = document.getElementById('reconcilePageInfo');

        if (pageInfo) {
            const endIdx = Math.min(startIdx + PAGE_SIZE, totalItems);
            pageInfo.textContent = `Showing ${totalItems ? startIdx + 1 : 0} to ${endIdx} of ${totalItems} ingredients`;
        }

        if (prevBtn) {
            prevBtn.disabled = reconcilePage <= 1;
            prevBtn.onclick = () => { if (reconcilePage > 1) { reconcilePage--; renderPaginatedStockReconciliation(ingredients); } };
        }

        if (nextBtn) {
            nextBtn.disabled = reconcilePage >= totalPages;
            nextBtn.onclick = () => { if (reconcilePage < totalPages) { reconcilePage++; renderPaginatedStockReconciliation(ingredients); } };
        }
    }

    [reportBranch, reportViewBy, reportDate, reportMonth, reportYear, selectInvCategory].forEach(el => {
        el?.addEventListener('change', () => {
            attentionPage = 1;
            movementsPage = 1;
            reconcilePage = 1;
            toggleReportDateInputs();
            renderSalesReport();
            renderInventoryReport();
            updatePrintRangeText();
        });
    });

    toggleReportDateInputs();
    renderSalesReport();
    renderInventoryReport();
    updatePrintRangeText();
}

document.addEventListener('DOMContentLoaded', () => {
    initReceipts();
    initReports();
});