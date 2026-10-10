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
   3. REPORTS MODULE
   ------------------------------------------------------------------------- */
function initReports() {
    const data = window.KAPE_REPORTS || window.KAPE_SALES;
    if (!data) return;

    // Pagination State (5 items per page)
    let reconcilePage = 1;
    let movementsPage = 1;
    let menuPage = 1;
    const PAGE_SIZE = 5;

    const btnSales = document.getElementById('btnReportSales');
    const btnInv = document.getElementById('btnReportInventory');
    const secSales = document.getElementById('sectionSalesReport');
    const secInv = document.getElementById('sectionInventoryReport');
    const invSubGroup = document.getElementById('invSubTabGroup');

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

    function updatePrintRangeText() {
        const dateInput = document.getElementById('reportDate')?.value;
        const viewBy = document.getElementById('reportViewBy')?.value || 'day';
        const branch = document.getElementById('reportBranch')?.value || 'all';
        const printTextEl = document.getElementById('printReportRangeText');

        if (!printTextEl) return;
        let dateFormatted = dateInput ? new Date(dateInput).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Selected Period';
        let branchFormatted = branch === 'all' ? 'All Branches' : branch;

        printTextEl.textContent = `${dateFormatted} (${viewBy.toUpperCase()}) · ${branchFormatted}`;
    }

    /* ---------------------------------------------------------------------
       3.1 SALES REPORT RENDERER
       --------------------------------------------------------------------- */
    function renderSalesReport() {
        const dailyData = data.sales || data.daily || [];

        const totalSales = dailyData.reduce((sum, d) => sum + (d.cash || 0) + (d.gcash || 0), 0);
        const estimatedOrders = Math.round(totalSales / 195) || 0;
        const estimatedCups = Math.round(estimatedOrders * 1.42) || 0;
        const avgTicket = estimatedOrders ? totalSales / estimatedOrders : 0;

        const elGross = document.getElementById('valGrossSales');
        const elOrders = document.getElementById('valTotalOrders');
        const elAvg = document.getElementById('valAvgTicket');
        const elCups = document.getElementById('valCupsServed');

        if (elGross) elGross.textContent = peso(totalSales);
        if (elOrders) elOrders.textContent = estimatedOrders;
        if (elAvg) elAvg.textContent = pesoDec(avgTicket);
        if (elCups) elCups.textContent = estimatedCups;

        // Payment Split - Stretched to fit container height
        const totalCash = dailyData.reduce((sum, d) => sum + (d.cash || 0), 0);
        const totalGcash = dailyData.reduce((sum, d) => sum + (d.gcash || 0), 0);
        const overall = totalCash + totalGcash || 1;
        const cashPct = Math.round((totalCash / overall) * 100);
        const gcashPct = 100 - cashPct;

        const boxSplit = document.getElementById('boxPaymentSplit');
        if (boxSplit) {
            boxSplit.style.display = 'flex';
            boxSplit.style.flexDirection = 'column';
            boxSplit.style.justifyContent = 'space-between';
            boxSplit.style.minHeight = '180px';

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

        // Sales Chart
        const chartContainer = document.getElementById('chartSalesOverTime');
        if (chartContainer && dailyData.length > 0) {
            const maxVal = Math.max(...dailyData.map(d => (d.cash || 0) + (d.gcash || 0))) || 1;
            
            chartContainer.innerHTML = dailyData.map(d => {
                const sum = (d.cash || 0) + (d.gcash || 0);
                const heightPct = Math.min(100, Math.max(10, Math.round((sum / maxVal) * 100)));
                
                return `<div class="chart-col" title="${d.label}: ${peso(sum)}">
                    <span class="chart-value">${(sum / 1000).toFixed(1)}k</span>
                    <div class="chart-stack" style="height: ${heightPct}%;">
                        <div class="chart-seg chart-seg--cash" style="height: 100%; background: var(--tan, #cf9a6a);"></div>
                    </div>
                    <span class="chart-label">${d.label}</span>
                </div>`;
            }).join('');
        }

        // Branch Ranking
        const branchList = document.getElementById('listSalesByBranch');
        if (branchList) {
            const topVal = totalSales * 0.6;
            const subVal = totalSales * 0.4;

            branchList.innerHTML = `
                <li>
                    <span class="rank-name" style="width: 140px;">Poblacion Main</span>
                    <span class="rank-bar"><i style="width: 100%"></i></span>
                    <span class="rank-num">${peso(topVal)}</span>
                </li>
                <li>
                    <span class="rank-name" style="width: 140px;">Mabini Branch</span>
                    <span class="rank-bar"><i style="width: 68%"></i></span>
                    <span class="rank-num">${peso(subVal)}</span>
                </li>`;
        }

        // Top Drink Revenue & Paginated Breakdown Table
        const topItems = data.topItems || [];
        const barsContainer = document.getElementById('barsMenuRevenue');
        
        if (topItems.length > 0) {
            const maxRev = topItems[0].revenue || 1;

            if (barsContainer) {
                barsContainer.innerHTML = topItems.slice(0, 5).map(item => `
                    <div class="hbar-row">
                        <span class="hbar-label">${item.name}</span>
                        <div class="hbar-track">
                            <div class="hbar-fill" style="width: ${Math.round((item.revenue / maxRev) * 100)}%"></div>
                        </div>
                        <span class="hbar-value">${peso(item.revenue)}</span>
                    </div>
                `).join('');
            }

            renderPaginatedMenuBreakdown(topItems);
        }
    }

    function renderPaginatedMenuBreakdown(topItems) {
        const tableBody = document.getElementById('tableMenuBreakdown');
        if (!tableBody) return;

        const totalItems = topItems.length;
        const totalPages = Math.ceil(totalItems / PAGE_SIZE) || 1;

        if (menuPage > totalPages) menuPage = totalPages;
        if (menuPage < 1) menuPage = 1;

        const startIdx = (menuPage - 1) * PAGE_SIZE;
        const pageItems = topItems.slice(startIdx, startIdx + PAGE_SIZE);
        const totalRev = topItems.reduce((s, i) => s + (i.revenue || 0), 0) || 1;

        tableBody.innerHTML = pageItems.map(item => `
            <tr>
                <td><b>${item.name}</b></td>
                <td class="ta-r mono">${item.sold}</td>
                <td class="ta-r mono">${peso(item.revenue)}</td>
                <td class="ta-r mono">${Math.round((item.revenue / totalRev) * 100)}%</td>
            </tr>
        `).join('') || '<tr><td colspan="4" class="empty-note ta-c">No item sales recorded.</td></tr>';

        const prevBtn = document.getElementById('btnMenuPrev');
        const nextBtn = document.getElementById('btnMenuNext');
        const pageInfo = document.getElementById('menuPageInfo');

        if (pageInfo) {
            const endIdx = Math.min(startIdx + PAGE_SIZE, totalItems);
            pageInfo.textContent = `Showing ${startIdx + 1} to ${endIdx} of ${totalItems} drinks`;
        }

        if (prevBtn) {
            prevBtn.disabled = menuPage <= 1;
            prevBtn.onclick = () => {
                if (menuPage > 1) {
                    menuPage--;
                    renderPaginatedMenuBreakdown(topItems);
                }
            };
        }

        if (nextBtn) {
            nextBtn.disabled = menuPage >= totalPages;
            nextBtn.onclick = () => {
                if (menuPage < totalPages) {
                    menuPage++;
                    renderPaginatedMenuBreakdown(topItems);
                }
            };
        }
    }

    /* ---------------------------------------------------------------------
       3.2 INVENTORY REPORT RENDERER
       --------------------------------------------------------------------- */
    function renderInventoryReport() {
        const ingredients = data.ingredients || [];
        const lowStockList = ingredients.filter(i => (i.stock <= i.reorder));

        const elReorder = document.getElementById('invCountReorder');
        const elTrackedNote = document.getElementById('invTrackedNote');
        const panelAttention = document.getElementById('panelItemsNeedingAttention');

        if (elReorder) elReorder.textContent = lowStockList.length;
        if (elTrackedNote) elTrackedNote.textContent = `Of ${ingredients.length} tracked ingredients`;

        // 1. Dynamic Low Stock Panel (Hides when all stock levels are healthy)
        if (panelAttention) {
            if (lowStockList.length === 0) {
                panelAttention.hidden = true;
            } else {
                panelAttention.hidden = false;
                const tbodyAttention = document.getElementById('tableItemsNeedingAttention');
                if (tbodyAttention) {
                    tbodyAttention.innerHTML = lowStockList.map(i => `
                        <tr>
                            <td><b>${i.name}</b></td>
                            <td class="ta-r mono">${i.stock} ${i.unit}</td>
                            <td class="ta-r mono">${i.reorder} ${i.unit}</td>
                            <td class="ta-r muted">—</td>
                            <td class="ta-r"><span class="badge badge--low">Low</span></td>
                        </tr>
                    `).join('');
                }
            }
        }

        // 2. Top 8 Ingredient Usage Trend (Aggregates duplicate ingredient names into 1 consolidated total)
        const boxTrend = document.getElementById('boxIngredientUsageTrend');
        if (boxTrend) {
            const groupedMap = {};
            ingredients.forEach(i => {
                const used = Number(i.used_today) || 0;
                if (used > 0) {
                    if (!groupedMap[i.name]) {
                        groupedMap[i.name] = { name: i.name, unit: i.unit, used_today: 0 };
                    }
                    groupedMap[i.name].used_today += used;
                }
            });

            const uniqueUsedItems = Object.values(groupedMap)
                .sort((a, b) => b.used_today - a.used_today)
                .slice(0, 8);

            if (uniqueUsedItems.length === 0) {
                boxTrend.innerHTML = '<div class="empty-note ta-c" style="padding:16px;">No usage recorded today.</div>';
            } else {
                boxTrend.innerHTML = uniqueUsedItems.map(i => `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border, #eee); font-size:13px;">
                        <span><b>${i.name}</b></span>
                        <strong class="mono" style="font-size:14px;">${i.used_today.toLocaleString()} ${i.unit}</strong>
                    </div>
                `).join('');
            }
        }

        // 3. Paginated Movements Table
        renderPaginatedMovements(data.movements || []);

        // 4. Paginated Reconciliation Table
        renderPaginatedStockReconciliation(ingredients);
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
        `).join('') || '<tr><td colspan="4" class="empty-note ta-c">No recent movements recorded.</td></tr>';

        const prevBtn = document.getElementById('btnMovementsPrev');
        const nextBtn = document.getElementById('btnMovementsNext');
        const pageInfo = document.getElementById('movementsPageInfo');

        if (pageInfo) {
            const endIdx = Math.min(startIdx + PAGE_SIZE, totalItems);
            pageInfo.textContent = `Showing ${totalItems ? startIdx + 1 : 0} to ${endIdx} of ${totalItems} movements`;
        }

        if (prevBtn) {
            prevBtn.disabled = movementsPage <= 1;
            prevBtn.onclick = () => {
                if (movementsPage > 1) {
                    movementsPage--;
                    renderPaginatedMovements(movements);
                }
            };
        }

        if (nextBtn) {
            nextBtn.disabled = movementsPage >= totalPages;
            nextBtn.onclick = () => {
                if (movementsPage < totalPages) {
                    movementsPage++;
                    renderPaginatedMovements(movements);
                }
            };
        }
    }

    function renderPaginatedStockReconciliation(ingredients) {
        const tbodyReconcile = document.getElementById('tableStockReconciliation');
        if (!tbodyReconcile) return;

        const totalItems = ingredients.length;
        const totalPages = Math.ceil(totalItems / PAGE_SIZE) || 1;

        if (reconcilePage > totalPages) reconcilePage = totalPages;
        if (reconcilePage < 1) reconcilePage = 1;

        const startIdx = (reconcilePage - 1) * PAGE_SIZE;
        const pageItems = ingredients.slice(startIdx, startIdx + PAGE_SIZE);

        tbodyReconcile.innerHTML = pageItems.map(i => `
            <tr>
                <td><b>${i.name}</b></td>
                <td class="ta-r muted">${i.stock + (i.used_today || 0)} ${i.unit}</td>
                <td class="ta-r muted">—</td>
                <td class="ta-r mono">${i.used_today > 0 ? '-' + i.used_today : '—'}</td>
                <td class="ta-r muted">—</td>
                <td class="ta-r muted">—</td>
                <td class="ta-r mono"><b>${i.stock} ${i.unit}</b></td>
            </tr>
        `).join('') || '<tr><td colspan="7" class="empty-note ta-c">No ingredients found.</td></tr>';

        const prevBtn = document.getElementById('btnReconcilePrev');
        const nextBtn = document.getElementById('btnReconcileNext');
        const pageInfo = document.getElementById('reconcilePageInfo');

        if (pageInfo) {
            const endIdx = Math.min(startIdx + PAGE_SIZE, totalItems);
            pageInfo.textContent = `Showing ${totalItems ? startIdx + 1 : 0} to ${endIdx} of ${totalItems} ingredients`;
        }

        if (prevBtn) {
            prevBtn.disabled = reconcilePage <= 1;
            prevBtn.onclick = () => {
                if (reconcilePage > 1) {
                    reconcilePage--;
                    renderPaginatedStockReconciliation(ingredients);
                }
            };
        }

        if (nextBtn) {
            nextBtn.disabled = reconcilePage >= totalPages;
            nextBtn.onclick = () => {
                if (reconcilePage < totalPages) {
                    reconcilePage++;
                    renderPaginatedStockReconciliation(ingredients);
                }
            };
        }
    }

    ['reportBranch', 'reportViewBy', 'reportDate'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => {
            renderSalesReport();
            renderInventoryReport();
            updatePrintRangeText();
        });
    });

    renderSalesReport();
    updatePrintRangeText();
}

document.addEventListener('DOMContentLoaded', initReports);