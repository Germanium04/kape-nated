/* kape-nated admin — inventory module */
function initInventory() {
    const data = window.KAPE;
    if (!data) return;

    let items = JSON.parse(JSON.stringify(data.ingredients || []));
    let currentPage = 1;
    const pageSize = 10;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const num = (n) => Math.round(n).toLocaleString('en-PH');
    const pesoDec = (n) => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function getMasterIngredients() {
        const map = new Map();
        items.forEach(i => {
            if (!map.has(i.key)) {
                map.set(i.key, { key: i.key, name: i.name, unit: i.unit });
            }
        });
        return Array.from(map.values());
    }

    function currentBranch() {
        const branchEl = document.getElementById('stockBranchFilter') || document.getElementById('stockBranch');
        return branchEl ? branchEl.value : 'all';
    }

    function renderStats() {
        const selBranch = currentBranch();
        const statsBox = document.getElementById('inventoryStats');
        const badge = document.getElementById('runningLowBadge');

        const filteredList = items.filter(i => selBranch === 'all' || i.branch_name === selBranch);

        const totalValue = filteredList.reduce((sum, i) => sum + (i.stock * (i.cost || 0)), 0);
        const lowCount = filteredList.filter(i => i.is_active && i.stock <= i.reorder).length;

        if (badge) badge.textContent = lowCount;
        if (!statsBox) return;

        statsBox.innerHTML = `
            <article class="stat stat--up" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px;">
                <p class="stat-label">Total Stock Value</p>
                <p class="stat-value">${pesoDec(totalValue)}</p>
                <p class="stat-trend">Valuation for ${selBranch === 'all' ? 'All Branches' : selBranch}</p>
            </article>
            
            <article class="stat ${lowCount ? 'stat--alert' : 'stat--muted-alert'}" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; border-left: 5px solid var(--flag, #a63d2a);">
                <p class="stat-label">${lowCount ? '⚠️ ' : ''}Items Running Low</p>
                <p class="stat-value" style="color: ${lowCount ? 'var(--flag, #a63d2a)' : 'inherit'};">${lowCount} Items</p>
                <p class="stat-trend">${lowCount ? 'Needs immediate replenishment' : 'Stock levels healthy'}</p>
            </article>

            <article class="stat stat--up" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px;">
                <p class="stat-label">Total Branch Records</p>
                <p class="stat-value">${filteredList.length} Items</p>
                <p class="stat-trend">Monitored stock items</p>
            </article>`;
    }

    function renderTable() {
        const body = document.querySelector('#stockTable tbody');
        if (!body) return;

        const selBranch = currentBranch();
        const term = (document.getElementById('stockSearch')?.value || '').trim().toLowerCase();
        const statusVal = document.getElementById('stockStatusFilter')?.value || 'all';

        const filtered = items.filter((i) => {
            const matchesBranch = selBranch === 'all' || i.branch_name === selBranch;
            const matchesSearch = !term || i.name.toLowerCase().includes(term) || (i.branch_name && i.branch_name.toLowerCase().includes(term));
            
            const isLow = i.stock <= i.reorder;
            const isOut = i.stock <= 0 || !i.is_active;
            const matchesStatus = statusVal === 'all' ||
                (statusVal === 'low' && isLow && !isOut) ||
                (statusVal === 'healthy' && !isLow && i.is_active) ||
                (statusVal === 'out' && isOut);

            return matchesBranch && matchesSearch && matchesStatus;
        });

        const totalRows = filtered.length;
        const totalPages = Math.ceil(totalRows / pageSize) || 1;
        if (currentPage > totalPages) currentPage = 1;

        const startIdx = (currentPage - 1) * pageSize;
        const visibleItems = filtered.slice(startIdx, startIdx + pageSize);

        body.innerHTML = visibleItems.map((i) => {
            const isLow = i.stock <= i.reorder;
            const isOut = i.stock <= 0 || !i.is_active;
            const tone = !i.is_active || isOut ? 'out' : (isLow ? 'low' : 'ok');
            const label = !i.is_active ? 'Disabled' : (isOut ? 'Out of stock' : (isLow ? 'Reorder now' : 'Healthy'));

            return `<tr>
                <td><span class="badge badge--neutral" style="font-weight: 600;">${i.branch_name || 'All'}</span></td>
                <td><strong>${i.name}</strong></td>
                <td class="ta-r mono"><b>${num(i.stock)} ${i.unit}</b></td>
                <td class="ta-r mono">${num(i.used_today || 0)} ${i.unit}</td>
                <td class="ta-r mono">${num(i.reorder || 0)} ${i.unit}</td>
                <td><span class="badge badge--${tone}">${label}</span></td>
                <td class="ta-r mono">${pesoDec(i.stock * (i.cost || 0))}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" class="empty-note">No branch stock records match your criteria.</td></tr>';

        renderPaginationControls(totalRows, totalPages, startIdx);
    }

    function renderPaginationControls(totalRows, totalPages, startIdx) {
        const bar = document.getElementById('inventoryPagination');
        const info = document.getElementById('inventoryPageInfo');
        const numbers = document.getElementById('inventoryPageNumbers');
        const prev = document.getElementById('inventoryPrevBtn');
        const next = document.getElementById('inventoryNextBtn');

        if (!bar) return;
        if (totalRows <= pageSize) { bar.hidden = true; return; }

        bar.hidden = false;
        if (info) {
            const end = Math.min(startIdx + pageSize, totalRows);
            info.textContent = `Showing ${totalRows ? startIdx + 1 : 0}–${end} of ${totalRows} items`;
        }

        if (numbers) {
            numbers.innerHTML = '';
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
                    numbers.appendChild(span);
                } else {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = p;
                    btn.className = p === currentPage ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm';
                    btn.style.cssText = 'min-width: 2rem; padding: 0.25rem 0.5rem;';
                    btn.addEventListener('click', () => { currentPage = p; renderTable(); });
                    numbers.appendChild(btn);
                }
            });
        }

        if (prev) { prev.disabled = currentPage <= 1; prev.onclick = () => { if (currentPage > 1) { currentPage--; renderTable(); } }; }
        if (next) { next.disabled = currentPage >= totalPages; next.onclick = () => { if (currentPage < totalPages) { currentPage++; renderTable(); } }; }
    }

    // Modal Builder Helpers
    function addStockInRow(selectedKey = '', qty = 1) {
        const container = document.getElementById('stockInRowsContainer');
        if (!container) return;

        const masterList = getMasterIngredients();
        const row = document.createElement('div');
        row.className = 'stock-in-row';
        row.style.cssText = 'display: flex; gap: 8px; align-items: center; margin-bottom: 6px;';

        const optionsHtml = masterList.map((ing) => `
            <option value="${ing.key}" data-unit="${ing.unit}" ${ing.key === selectedKey ? 'selected' : ''}>
                ${ing.name}
            </option>
        `).join('');

        const defaultUnit = selectedKey 
            ? (masterList.find(i => i.key === selectedKey)?.unit || 'g')
            : (masterList[0]?.unit || 'g');

        row.innerHTML = `
            <select class="field stock-in-item-select" style="flex: 2; height: 38px; box-sizing: border-box; margin: 0;">
                <option value="" ${!selectedKey ? 'selected' : ''} disabled>Select ingredient</option>
                ${optionsHtml}
            </select>
            
            <div style="flex: 1; display: flex; align-items: center; gap: 6px; height: 38px;">
                <input type="number" step="any" inputmode="decimal" class="field stock-in-qty-input" value="${qty}" placeholder="Qty" style="width: 100%; height: 38px; box-sizing: border-box; margin: 0;">
                <span class="muted stock-in-unit-badge" style="font-size: 12px; min-width: 28px;">${defaultUnit}</span>
            </div>

            <button type="button" class="btn btn--ghost remove-stock-in-row" style="height: 38px; width: 38px; padding: 0; display: flex; align-items: center; justify-content: center; color: var(--red, #c0392b);">✕</button>
        `;

        row.querySelector('.stock-in-item-select').addEventListener('change', (e) => {
            const selectedOpt = e.target.options[e.target.selectedIndex];
            const unit = selectedOpt.getAttribute('data-unit') || '';
            row.querySelector('.stock-in-unit-badge').textContent = unit;
        });

        row.querySelector('.remove-stock-in-row').addEventListener('click', () => row.remove());
        container.appendChild(row);
    }

    function addTransferRow(selectedKey = '', qty = 1) {
        const container = document.getElementById('transferRowsContainer');
        if (!container) return;

        const masterList = getMasterIngredients();
        const row = document.createElement('div');
        row.className = 'transfer-row';
        row.style.cssText = 'display: flex; gap: 8px; align-items: center; margin-bottom: 6px;';

        const optionsHtml = masterList.map((ing) => `
            <option value="${ing.key}" data-unit="${ing.unit}" ${ing.key === selectedKey ? 'selected' : ''}>
                ${ing.name}
            </option>
        `).join('');

        const defaultUnit = selectedKey 
            ? (masterList.find(i => i.key === selectedKey)?.unit || 'g')
            : (masterList[0]?.unit || 'g');

        row.innerHTML = `
            <select class="field transfer-item-select" style="flex: 2; height: 38px; box-sizing: border-box; margin: 0;">
                <option value="" ${!selectedKey ? 'selected' : ''} disabled>Select ingredient</option>
                ${optionsHtml}
            </select>
            
            <div style="flex: 1; display: flex; align-items: center; gap: 6px; height: 38px;">
                <input type="number" step="any" inputmode="decimal" class="field transfer-qty-input" value="${qty}" placeholder="Qty" style="width: 100%; height: 38px; box-sizing: border-box; margin: 0;">
                <span class="muted transfer-unit-badge" style="font-size: 12px; min-width: 28px;">${defaultUnit}</span>
            </div>

            <button type="button" class="btn btn--ghost remove-transfer-row" style="height: 38px; width: 38px; padding: 0; display: flex; align-items: center; justify-content: center; color: var(--red, #c0392b);">✕</button>
        `;

        row.querySelector('.transfer-item-select').addEventListener('change', (e) => {
            const selectedOpt = e.target.options[e.target.selectedIndex];
            const unit = selectedOpt.getAttribute('data-unit') || '';
            row.querySelector('.transfer-unit-badge').textContent = unit;
        });

        row.querySelector('.remove-transfer-row').addEventListener('click', () => row.remove());
        container.appendChild(row);
    }

    function populateStockOutSelect() {
        const select = document.getElementById('stockOutIngredientSelect');
        if (!select) return;
        select.innerHTML = getMasterIngredients().map(i => `<option value="${i.key}">${i.name} (${i.unit})</option>`).join('');
    }

    const actionTypeSelect = document.getElementById('invActionType');
    const submitBtn = document.getElementById('submitInvActionBtn');

    const sections = {
        stock_in: document.getElementById('secStockIn'),
        stock_out: document.getElementById('secStockOut'),
        transfer: document.getElementById('secTransfer')
    };

    actionTypeSelect?.addEventListener('change', (e) => {
        const selected = e.target.value;
        Object.keys(sections).forEach(key => {
            if (sections[key]) sections[key].hidden = (key !== selected);
        });

        if (selected === 'stock_out') populateStockOutSelect();
        if (selected === 'stock_in' && document.getElementById('stockInRowsContainer')?.children.length === 0) addStockInRow();
        if (selected === 'transfer' && document.getElementById('transferRowsContainer')?.children.length === 0) addTransferRow();
    });

    // SUBMIT OPERATION TO LARAVEL BACKEND
    submitBtn?.addEventListener('click', async () => {
        const action = actionTypeSelect.value;
        let payload = { type: action, items: [] };

        if (action === 'stock_in') {
            payload.branch = document.getElementById('stockInBranch')?.value;
            document.querySelectorAll('#stockInRowsContainer .stock-in-row').forEach(row => {
                const key = row.querySelector('.stock-in-item-select')?.value;
                const qty = parseFloat(row.querySelector('.stock-in-qty-input')?.value) || 0;
                if (key && qty > 0) {
                    payload.items.push({ key, qty });
                    const item = items.find(i => i.key === key && i.branch_name === payload.branch);
                    if (item) item.stock += qty;
                }
            });
        } 
        else if (action === 'stock_out') {
            const key = document.getElementById('stockOutIngredientSelect')?.value;
            const qty = parseFloat(document.getElementById('stockOutQtyInput')?.value) || 0;
            payload.branch = currentBranch() === 'all' ? items[0]?.branch_name : currentBranch();
            payload.reason = document.getElementById('stockOutReasonSelect')?.value || 'spoilage';

            if (key && qty > 0) {
                payload.items.push({ key, qty });
                const item = items.find(i => i.key === key && (payload.branch === 'all' || i.branch_name === payload.branch));
                if (item) item.stock = Math.max(0, item.stock - qty);
            }
        }
        else if (action === 'transfer') {
            payload.from_branch = document.getElementById('transferFromBranch')?.value;
            payload.to_branch = document.getElementById('transferToBranch')?.value;

            if (payload.from_branch === payload.to_branch) {
                alert('Source and destination branches must be different.');
                return;
            }

            document.querySelectorAll('#transferRowsContainer .transfer-row').forEach(row => {
                const key = row.querySelector('.transfer-item-select')?.value;
                const qty = parseFloat(row.querySelector('.transfer-qty-input')?.value) || 0;

                if (key && qty > 0) {
                    payload.items.push({ key, qty });
                    const sourceItem = items.find(i => i.key === key && i.branch_name === payload.from_branch);
                    const destItem = items.find(i => i.key === key && i.branch_name === payload.to_branch);

                    if (sourceItem) sourceItem.stock = Math.max(0, sourceItem.stock - qty);
                    if (destItem) destItem.stock += qty;
                }
            });
        }

        if (payload.items.length === 0) return;

        try {
            const response = await fetch('/admin/inventory/operation', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload)
            });

            const res = await response.json();
            if (res.success) {
                render();
                document.getElementById('inventoryActionModal').hidden = true;
                document.body.classList.remove('is-locked');
            }
        } catch (err) {
            console.error('Failed to commit stock movement to DB:', err);
        }
    });

    document.getElementById('addStockInRowBtn')?.addEventListener('click', () => addStockInRow());
    document.getElementById('addTransferRowBtn')?.addEventListener('click', () => addTransferRow());

    document.querySelector('[data-open-modal="inventoryActionModal"]')?.addEventListener('click', () => {
        if (document.getElementById('stockInRowsContainer')?.children.length === 0) addStockInRow();
    });

    function render() {
        renderStats();
        renderTable();
    }

    ['stockBranch', 'stockBranchFilter'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', (e) => {
            const val = e.target.value;
            const otherId = id === 'stockBranch' ? 'stockBranchFilter' : 'stockBranch';
            const other = document.getElementById(otherId);
            if (other) other.value = val;
            currentPage = 1;
            render();
        });
    });

    ['stockSearch', 'stockStatusFilter', 'stockUnitFilter'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => { currentPage = 1; render(); });
        document.getElementById(id)?.addEventListener('change', () => { currentPage = 1; render(); });
    });

    document.getElementById('tabRunningLow')?.addEventListener('click', () => {
        const sf = document.getElementById('stockStatusFilter');
        if (sf) sf.value = 'low';
        currentPage = 1;
        render();
    });

    document.getElementById('tabAllItems')?.addEventListener('click', () => {
        const sf = document.getElementById('stockStatusFilter');
        if (sf) sf.value = 'all';
        currentPage = 1;
        render();
    });

    render();
}