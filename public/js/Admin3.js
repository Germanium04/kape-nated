/* kape-nated admin — inventory module */
function initInventory() {
    const data = window.KAPE;
    if (!data) return;

    let items = JSON.parse(JSON.stringify(data.ingredients || []));
    let movements = JSON.parse(JSON.stringify(data.movements || []));
    let currentPage = 1;
    let activeViewMode = 'transactions'; // 'transactions' or 'alerts'
    const pageSize = 10;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const num = (n) => Math.round(n).toLocaleString('en-PH');

    const unitCategoryMap = {
        volume: ['ml', 'L', 'gal'],
        weight: ['g', 'kg', 'oz'],
        count:  ['pc', 'pack', 'box']
    };

    function showSuccessModal(msg) {
        const modal = document.getElementById('successInvModal');
        const actionModal = document.getElementById('inventoryActionModal');
        const text = document.getElementById('successInvText');

        if (actionModal) actionModal.hidden = true;
        if (text) text.textContent = msg;

        if (modal) {
            modal.hidden = false;
            document.body.classList.add('is-locked');
        }
    }

    document.getElementById('btnSuccessModalClose')?.addEventListener('click', () => {
        window.location.reload();
    });

    function showErrorModal(msg) {
        const modal = document.getElementById('errorInvModal');
        const text = document.getElementById('errorInvText');
        if (text) text.textContent = msg;
        if (modal) {
            modal.hidden = false;
            document.body.classList.add('is-locked');
        }
    }

    function showNotice(msg) {
        const notice = document.getElementById('invFormNotice');
        if (notice) {
            notice.textContent = msg;
            notice.style.background = '#f8d7da';
            notice.style.color = '#721c24';
            notice.style.border = '1px solid #f5c6cb';
            notice.style.display = 'block';
        }
    }

    function clearNotice() {
        const notice = document.getElementById('invFormNotice');
        if (notice) notice.style.display = 'none';
    }

    // Filter master ingredients to active ones only for stock operations
    function getMasterIngredients() {
        const map = new Map();
        items.forEach(i => {
            if (i.is_active !== false && !map.has(i.key)) {
                map.set(i.key, { key: i.key, name: i.name, unit: i.unit });
            }
        });
        return Array.from(map.values());
    }

    function getOptionsForUnit(baseUnit) {
        if (unitCategoryMap.volume.includes(baseUnit)) {
            return `<option value="ml" ${baseUnit === 'ml' ? 'selected' : ''}>ml</option>
                    <option value="L" ${baseUnit === 'L' ? 'selected' : ''}>L</option>
                    <option value="gal" ${baseUnit === 'gal' ? 'selected' : ''}>gal</option>`;
        } else if (unitCategoryMap.weight.includes(baseUnit)) {
            return `<option value="g" ${baseUnit === 'g' ? 'selected' : ''}>g</option>
                    <option value="kg" ${baseUnit === 'kg' ? 'selected' : ''}>kg</option>
                    <option value="oz" ${baseUnit === 'oz' ? 'selected' : ''}>oz</option>`;
        } else {
            return `<option value="pc" ${baseUnit === 'pc' ? 'selected' : ''}>pc</option>
                    <option value="pack" ${baseUnit === 'pack' ? 'selected' : ''}>pack</option>
                    <option value="box" ${baseUnit === 'box' ? 'selected' : ''}>box</option>`;
        }
    }

    function currentBranch() {
        const branchEl = document.getElementById('stockBranchFilter') || document.getElementById('stockBranch');
        return branchEl ? branchEl.value : 'all';
    }

    function renderStats() {
        const selBranch = currentBranch();
        const statsBox = document.getElementById('inventoryStats');
        const badge = document.getElementById('runningLowBadge');

        const filteredList = items.filter(i => (selBranch === 'all' || i.branch_name === selBranch) && i.is_active !== false);
        const lowCount = filteredList.filter(i => i.stock <= i.reorder).length;

        const manualMovements = movements.filter(m => m.reason.toLowerCase() !== 'order');
        const totalOpsCount = manualMovements.filter(m => selBranch === 'all' || m.branch === selBranch).length;

        if (badge) badge.textContent = lowCount;
        if (!statsBox) return;

        statsBox.innerHTML = `
            <article class="stat stat--up" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px;">
                <p class="stat-label">Total Stock Operations</p>
                <p class="stat-value">${num(totalOpsCount)}</p>
                <p class="stat-trend">Deliveries & transfers for ${selBranch === 'all' ? 'All Branches' : selBranch}</p>
            </article>
            
            <article class="stat ${lowCount ? 'stat--alert' : 'stat--muted-alert'}" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; border-left: 5px solid var(--flag, #a63d2a);">
                <p class="stat-label">${lowCount ? '⚠️ ' : ''}Items Running Low</p>
                <p class="stat-value" style="color: ${lowCount ? 'var(--flag, #a63d2a)' : 'inherit'};">${lowCount} Items</p>
                <p class="stat-trend">${lowCount ? 'Needs immediate replenishment' : 'Stock levels healthy'}</p>
            </article>

            <article class="stat stat--up" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px;">
                <p class="stat-label">Total Monitored Items</p>
                <p class="stat-value">${filteredList.length} Items</p>
                <p class="stat-trend">Active stock items across branches</p>
            </article>`;
    }

    function renderTable() {
        const thead = document.querySelector('#stockTable thead');
        const tbody = document.querySelector('#stockTable tbody');
        if (!tbody || !thead) return;

        const selBranch = currentBranch();
        const term = (document.getElementById('stockSearch')?.value || '').trim().toLowerCase();

        if (activeViewMode === 'alerts') {
            thead.innerHTML = `
                <tr>
                    <th>Branch Name</th>
                    <th>Ingredient Name</th>
                    <th class="ta-r">Current On Hand</th>
                    <th class="ta-r">Reorder Threshold</th>
                    <th>Alert Severity</th>
                    <th class="ta-r">Action</th>
                </tr>`;

            const severityVal = document.getElementById('stockStatusFilter')?.value || 'all';

            const lowStockItems = items.filter(i => {
                const matchesBranch = selBranch === 'all' || i.branch_name === selBranch;
                const matchesSearch = !term || i.name.toLowerCase().includes(term) || i.branch_name.toLowerCase().includes(term);
                const isLow = i.is_active !== false && i.stock <= i.reorder;
                const isOut = i.stock <= 0;

                let matchesSeverity = true;
                if (severityVal === 'out') matchesSeverity = isOut;
                if (severityVal === 'critical') matchesSeverity = !isOut && isLow;

                return matchesBranch && matchesSearch && isLow && matchesSeverity;
            });

            const totalRows = lowStockItems.length;
            const totalPages = Math.ceil(totalRows / pageSize) || 1;
            if (currentPage > totalPages) currentPage = 1;

            const startIdx = (currentPage - 1) * pageSize;
            const visibleItems = lowStockItems.slice(startIdx, startIdx + pageSize);

            tbody.innerHTML = visibleItems.map(i => {
                const isOut = i.stock <= 0;
                const badge = isOut ? '<span class="badge badge--out">Out of Stock</span>' : '<span class="badge badge--low">Critical Low</span>';
                const stockCss = isOut ? 'color: var(--flag, #a63d2a); font-weight: 700;' : 'font-weight: 700;';

                return `<tr>
                    <td style="font-weight: 600; color: var(--ink-soft);">${i.branch_name}</td>
                    <td><strong>${i.name}</strong></td>
                    <td class="ta-r mono" style="${stockCss}">${num(i.stock)} ${i.unit}</td>
                    <td class="ta-r mono">${num(i.reorder)} ${i.unit}</td>
                    <td>${badge}</td>
                    <td class="ta-r">
                        <button type="button" class="btn btn--primary btn--sm quick-restock-btn" data-branch="${i.branch_name}" data-key="${i.key}">📦 Deliver Supplies</button>
                    </td>
                </tr>`;
            }).join('') || '<tr><td colspan="6" class="empty-note">🎉 Excellent! No items are currently running low in this branch.</td></tr>';

            tbody.querySelectorAll('.quick-restock-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const branch = e.target.getAttribute('data-branch');
                    const key = e.target.getAttribute('data-key');

                    const modal = document.getElementById('inventoryActionModal');
                    const branchSel = document.getElementById('stockInBranch');
                    const container = document.getElementById('stockInRowsContainer');

                    if (branchSel) branchSel.value = branch;
                    if (container) {
                        container.innerHTML = '';
                        addStockInRow(key, 1);
                    }

                    if (modal) {
                        modal.hidden = false;
                        document.body.classList.add('is-locked');
                    }
                });
            });

            renderPaginationControls(totalRows, totalPages, startIdx);

        } else {
            thead.innerHTML = `
                <tr>
                    <th>Trx ID</th>
                    <th>Date & Time</th>
                    <th>Branch</th>
                    <th>Action Type</th>
                    <th>Items Included</th>
                    <th>Performed By</th>
                    <th>Supplier / Ref Notes</th>
                </tr>`;

            const actionTypeVal = document.getElementById('stockStatusFilter')?.value || 'all';
            const manualMovements = movements.filter(m => m.reason.toLowerCase() !== 'order');

            const filtered = manualMovements.filter((m) => {
                const matchesBranch = selBranch === 'all' || m.branch === selBranch;
                const matchesSearch = !term || 
                    m.ingredient.toLowerCase().includes(term) || 
                    m.branch.toLowerCase().includes(term) ||
                    (m.performed_by && m.performed_by.toLowerCase().includes(term)) ||
                    (`trx-${m.id}`).includes(term);
                
                let matchesAction = true;
                const r = m.reason.toLowerCase();
                if (actionTypeVal === 'stock_in') matchesAction = (m.change > 0 && !r.includes('transfer'));
                if (actionTypeVal === 'transfer') matchesAction = r.includes('transfer') || r.includes('correct');
                if (actionTypeVal === 'stock_out') matchesAction = (m.change < 0 || r.includes('spill') || r.includes('spoil') || r.includes('waste'));

                return matchesBranch && matchesSearch && matchesAction;
            });

            const totalRows = filtered.length;
            const totalPages = Math.ceil(totalRows / pageSize) || 1;
            if (currentPage > totalPages) currentPage = 1;

            const startIdx = (currentPage - 1) * pageSize;
            const visibleItems = filtered.slice(startIdx, startIdx + pageSize);

            tbody.innerHTML = visibleItems.map((m) => {
                const trxId = `#TRX-${1000 + m.id}`;
                const r = m.reason.toLowerCase();

                let typeBadge = '<span class="badge badge--ok">📦 Stock Delivery</span>';

                if (r.includes('transfer') || r.includes('correct')) {
                    if (m.change < 0) {
                        typeBadge = '<span class="badge badge--out">↔ Transfer Out</span>';
                    } else {
                        typeBadge = '<span class="badge badge--ok">↔ Transfer In</span>';
                    }
                } else if (m.change < 0 || r.includes('spill') || r.includes('spoil') || r.includes('waste')) {
                    typeBadge = '<span class="badge badge--out">🗑️ Stock Deduction</span>';
                }

                const changeSign = m.change > 0 ? '+' : '';
                const qtyFormatted = `${changeSign}${num(m.change)}`;

                return `<tr>
                    <td style="font-weight: 600; font-size: 12px; color: var(--ink-soft);">${trxId}</td>
                    <td style="font-size: 12.5px; color: var(--ink-soft);">${m.date}</td>
                    <td style="font-weight: 600; color: var(--brand, #5a3e2b);">${m.branch}</td>
                    <td>${typeBadge}</td>
                    <td><strong>${m.ingredient}</strong> <span class="mono" style="margin-left: 4px; font-weight: 600;">(${qtyFormatted})</span></td>
                    <td style="font-weight: 600; color: var(--ink-bold);">${m.performed_by || 'Admin User'}</td>
                    <td style="font-size: 12.5px; color: var(--ink-soft);">${m.reason || 'Restock'}</td>
                </tr>`;
            }).join('') || '<tr><td colspan="7" class="empty-note">No manual stock operations logged for this criteria.</td></tr>';

            renderPaginationControls(totalRows, totalPages, startIdx);
        }
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
            info.textContent = `Showing ${totalRows ? startIdx + 1 : 0}–${end} of ${totalRows} records`;
        }

        if (numbers) {
            numbers.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = i;
                    btn.className = i === currentPage ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm';
                    btn.style.cssText = 'min-width: 2rem; padding: 0.25rem 0.5rem;';
                    btn.addEventListener('click', () => { currentPage = i; renderTable(); });
                    numbers.appendChild(btn);
                }
            }
        }

        if (prev) { prev.disabled = currentPage <= 1; prev.onclick = () => { if (currentPage > 1) { currentPage--; renderTable(); } }; }
        if (next) { next.disabled = currentPage >= totalPages; next.onclick = () => { if (currentPage < totalPages) { currentPage++; renderTable(); } }; }
    }

    function updateModalDropdowns(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const selects = Array.from(container.querySelectorAll('select.stock-in-item-select, select.transfer-item-select'));
        const selectedKeys = selects.map(s => s.value).filter(val => val !== '');

        selects.forEach(currentSelect => {
            const currentVal = currentSelect.value;
            Array.from(currentSelect.options).forEach(opt => {
                if (opt.value === '') return;
                if (selectedKeys.includes(opt.value) && opt.value !== currentVal) {
                    opt.disabled = true;
                } else {
                    opt.disabled = false;
                }
            });
        });
    }

    function addStockInRow(selectedKey = '', qty = 1) {
        const container = document.getElementById('stockInRowsContainer');
        if (!container) return;

        const masterList = getMasterIngredients();

        if (masterList.length === 0) {
            showNotice('No active ingredients available to restock.');
            return;
        }

        if (!selectedKey) {
            const currentSelects = container.querySelectorAll('.stock-in-item-select');
            const usedKeys = Array.from(currentSelects).map(s => s.value);
            const available = masterList.find(i => !usedKeys.includes(i.key));
            if (available) selectedKey = available.key;
        }

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
            <select class="field stock-in-item-select" style="flex: 1; height: 38px; box-sizing: border-box; margin: 0;">
                <option value="" ${!selectedKey ? 'selected' : ''} disabled>Select ingredient</option>
                ${optionsHtml}
            </select>
            
            <div style="display: flex; align-items: center; gap: 4px; height: 38px;">
                <input type="number" step="any" inputmode="decimal" class="field stock-in-qty-input" value="${qty}" placeholder="Qty" style="width: 80px; height: 38px; box-sizing: border-box; margin: 0;">
                <select class="field stock-in-unit-select" style="width: 72px; min-width: 72px; height: 38px; box-sizing: border-box; margin: 0; padding: 2px 4px; font-size: 12px; text-align: center;">
                    ${getOptionsForUnit(defaultUnit)}
                </select>
            </div>

            <button type="button" class="btn btn--ghost remove-stock-in-row" style="height: 38px; width: 38px; padding: 0; display: flex; align-items: center; justify-content: center; color: var(--red, #c0392b);">✕</button>
        `;

        row.querySelector('.stock-in-item-select').addEventListener('change', (e) => {
            const selectedOpt = e.target.options[e.target.selectedIndex];
            const baseUnit = selectedOpt.getAttribute('data-unit') || 'g';
            row.querySelector('.stock-in-unit-select').innerHTML = getOptionsForUnit(baseUnit);
            updateModalDropdowns('stockInRowsContainer');
        });

        row.querySelector('.remove-stock-in-row').addEventListener('click', () => {
            row.remove();
            updateModalDropdowns('stockInRowsContainer');
        });

        container.appendChild(row);
        updateModalDropdowns('stockInRowsContainer');
    }

    function addTransferRow(selectedKey = '', qty = 1) {
        const container = document.getElementById('transferRowsContainer');
        if (!container) return;

        const masterList = getMasterIngredients();

        if (masterList.length === 0) {
            showNotice('No active ingredients available to transfer.');
            return;
        }

        if (!selectedKey) {
            const currentSelects = container.querySelectorAll('.transfer-item-select');
            const usedKeys = Array.from(currentSelects).map(s => s.value);
            const available = masterList.find(i => !usedKeys.includes(i.key));
            if (available) selectedKey = available.key;
        }

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
            <select class="field transfer-item-select" style="flex: 1; height: 38px; box-sizing: border-box; margin: 0;">
                <option value="" ${!selectedKey ? 'selected' : ''} disabled>Select ingredient</option>
                ${optionsHtml}
            </select>
            
            <div style="display: flex; align-items: center; gap: 4px; height: 38px;">
                <input type="number" step="any" inputmode="decimal" class="field transfer-qty-input" value="${qty}" placeholder="Qty" style="width: 80px; height: 38px; box-sizing: border-box; margin: 0;">
                <select class="field transfer-unit-select" style="width: 72px; min-width: 72px; height: 38px; box-sizing: border-box; margin: 0; padding: 2px 4px; font-size: 12px; text-align: center;">
                    ${getOptionsForUnit(defaultUnit)}
                </select>
            </div>

            <button type="button" class="btn btn--ghost remove-transfer-row" style="height: 38px; width: 38px; padding: 0; display: flex; align-items: center; justify-content: center; color: var(--red, #c0392b);">✕</button>
        `;

        row.querySelector('.transfer-item-select').addEventListener('change', (e) => {
            const selectedOpt = e.target.options[e.target.selectedIndex];
            const baseUnit = selectedOpt.getAttribute('data-unit') || 'g';
            row.querySelector('.transfer-unit-select').innerHTML = getOptionsForUnit(baseUnit);
            updateModalDropdowns('transferRowsContainer');
        });

        row.querySelector('.remove-transfer-row').addEventListener('click', () => {
            row.remove();
            updateModalDropdowns('transferRowsContainer');
        });

        container.appendChild(row);
        updateModalDropdowns('transferRowsContainer');
    }

    const actionTypeSelect = document.getElementById('invActionType');
    const submitBtn = document.getElementById('submitInvActionBtn');

    const sections = {
        stock_in: document.getElementById('secStockIn'),
        transfer: document.getElementById('secTransfer')
    };

    actionTypeSelect?.addEventListener('change', (e) => {
        clearNotice();
        const selected = e.target.value;
        Object.keys(sections).forEach(key => {
            if (sections[key]) sections[key].hidden = (key !== selected);
        });

        if (selected === 'stock_in' && document.getElementById('stockInRowsContainer')?.children.length === 0) addStockInRow();
        if (selected === 'transfer' && document.getElementById('transferRowsContainer')?.children.length === 0) addTransferRow();

        if (submitBtn) {
            submitBtn.textContent = selected === 'stock_in' ? 'Save Stock Delivery' : 'Save Inter-Branch Transfer';
        }
    });

    submitBtn?.addEventListener('click', () => {
        clearNotice();
        const action = actionTypeSelect.value;
        let valid = false;

        if (action === 'stock_in') {
            const rows = document.querySelectorAll('#stockInRowsContainer .stock-in-row');
            if (rows.length === 0) { showNotice('Please add at least one item to deliver.'); return; }
            for (let r of rows) {
                const item = r.querySelector('.stock-in-item-select')?.value;
                const qty = parseFloat(r.querySelector('.stock-in-qty-input')?.value);
                if (!item) { showNotice('Please select an ingredient for all rows.'); return; }
                if (isNaN(qty) || qty <= 0) { showNotice('Please enter a valid quantity.'); return; }
            }
            valid = true;
        } else if (action === 'transfer') {
            const from = document.getElementById('transferFromBranch')?.value;
            const to = document.getElementById('transferToBranch')?.value;
            if (from === to) { showNotice('Source and destination branches must be different.'); return; }

            const rows = document.querySelectorAll('#transferRowsContainer .transfer-row');
            if (rows.length === 0) { showNotice('Please add at least one item to transfer.'); return; }

            for (let r of rows) {
                const key = r.querySelector('.transfer-item-select')?.value;
                const qty = parseFloat(r.querySelector('.transfer-qty-input')?.value);
                if (!key) { showNotice('Please select an ingredient for all transfer rows.'); return; }
                if (isNaN(qty) || qty <= 0) { showNotice('Please enter a valid transfer quantity.'); return; }

                const sourceItem = items.find(i => i.key === key && i.branch_name === from);
                const availableStock = sourceItem ? sourceItem.stock : 0;

                if (availableStock <= 0) {
                    showNotice(`Cannot transfer ${sourceItem ? sourceItem.name : key}. ${from} is currently Out of Stock.`);
                    return;
                }

                if (qty > availableStock) {
                    showNotice(`Cannot transfer ${qty} for ${sourceItem.name}. ${from} only has ${availableStock} ${sourceItem.unit} in stock.`);
                    return;
                }
            }
            valid = true;
        }

        if (valid) {
            const confirmModal = document.getElementById('confirmInvActionModal');
            if (confirmModal) confirmModal.hidden = false;
        }
    });

    document.getElementById('btnConfirmFinalInvSave')?.addEventListener('click', async () => {
        const confirmModal = document.getElementById('confirmInvActionModal');
        if (confirmModal) confirmModal.hidden = true;

        const action = actionTypeSelect.value;
        let payload = { type: action, items: [] };

        if (action === 'stock_in') {
            payload.branch = document.getElementById('stockInBranch')?.value;
            payload.reason = document.getElementById('stockInRef')?.value || 'Delivery Restock';
            document.querySelectorAll('#stockInRowsContainer .stock-in-row').forEach(row => {
                const key = row.querySelector('.stock-in-item-select')?.value;
                const qty = parseFloat(row.querySelector('.stock-in-qty-input')?.value) || 0;
                const unit = row.querySelector('.stock-in-unit-select')?.value;
                if (key && qty > 0) payload.items.push({ key, qty, unit });
            });
        } else if (action === 'transfer') {
            payload.from_branch = document.getElementById('transferFromBranch')?.value;
            payload.to_branch = document.getElementById('transferToBranch')?.value;
            document.querySelectorAll('#transferRowsContainer .transfer-row').forEach(row => {
                const key = row.querySelector('.transfer-item-select')?.value;
                const qty = parseFloat(row.querySelector('.transfer-qty-input')?.value) || 0;
                const unit = row.querySelector('.transfer-unit-select')?.value;
                if (key && qty > 0) payload.items.push({ key, qty, unit });
            });
        }

        try {
            const response = await fetch('/admin/inventory/operation', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload)
            });

            const res = await response.json();
            if (res.success) {
                showSuccessModal('Stock operation committed to database successfully!');
            } else {
                showErrorModal(res.message || 'Error executing inventory transaction.');
            }
        } catch (err) {
            showErrorModal('Network error or server connection failure while executing stock movement.');
            console.error('Failed to commit stock movement to DB:', err);
        }
    });

    document.getElementById('addStockInRowBtn')?.addEventListener('click', () => addStockInRow());
    document.getElementById('addTransferRowBtn')?.addEventListener('click', () => addTransferRow());

    document.querySelector('[data-open-modal="inventoryActionModal"]')?.addEventListener('click', () => {
        clearNotice();
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

    ['stockSearch', 'stockStatusFilter'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => { currentPage = 1; render(); });
        document.getElementById(id)?.addEventListener('change', () => { currentPage = 1; render(); });
    });

    const tabAll = document.getElementById('tabAllItems');
    const tabLow = document.getElementById('tabRunningLow');
    const filterLabel = document.getElementById('dynamicFilterLabel');
    const filterSelect = document.getElementById('stockStatusFilter');

    tabLow?.addEventListener('click', () => {
        tabLow.className = 'tab-btn tab-btn--active';
        tabAll.className = 'tab-btn tab-btn--inactive';
        activeViewMode = 'alerts';

        if (filterLabel) filterLabel.textContent = 'Alert Severity';
        if (filterSelect) {
            filterSelect.innerHTML = `
                <option value="all">All Severities</option>
                <option value="out">Out of Stock Only</option>
                <option value="critical">Critical Low Only</option>`;
        }

        currentPage = 1;
        render();
    });

    tabAll?.addEventListener('click', () => {
        tabAll.className = 'tab-btn tab-btn--active';
        tabLow.className = 'tab-btn tab-btn--inactive';
        activeViewMode = 'transactions';

        if (filterLabel) filterLabel.textContent = 'Transaction Type';
        if (filterSelect) {
            filterSelect.innerHTML = `
                <option value="all">All Action Types</option>
                <option value="stock_in">📦 Stock Deliveries (Stock-In)</option>
                <option value="transfer">↔ Branch Transfers</option>
                <option value="stock_out">🗑️ Stock Deductions (Stock-Out)</option>`;
        }

        currentPage = 1;
        render();
    });

    render();
}