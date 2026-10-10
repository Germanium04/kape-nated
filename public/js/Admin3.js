/* kape-nated admin — inventory module */
function initInventory() {
    const data = window.KAPE;
    if (!data) return;

    const num = (n) => Math.round(n).toLocaleString('en-PH');
    const pesoDec = (n) => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let combined = JSON.parse(JSON.stringify(data.ingredients));

    let currentPage = 1;
    const pageSize = 10;

    function branchWeight(branch) {
        return branch === 'all' ? 1 : (data.branchWeights[branch] || 0);
    }

    function stockForBranch(branch) {
        const w = branchWeight(branch);
        return combined.map((i) => ({ ...i, stock: i.stock * w, used_today: i.used_today * w }));
    }

    function byKeyOf(list) { return Object.fromEntries(list.map((i) => [i.key, i])); }

    function usageRate(item, mode) {
        if (item.used_today && item.used_today > 0) {
            return mode === 'day' ? item.used_today : item.used_today * 7;
        }
        
        // Dynamic fallback estimate if DB stock movements for today are 0
        const seed = Math.abs(Math.sin(item.key.length)) || 0.5;
        const baseDaily = Math.round((item.reorder || 1000) * (0.08 + seed * 0.05));
        return mode === 'day' ? baseDaily : baseDaily * 7;
    }

    function status(item) {
        if (!item.is_active) return { tone: 'out', label: 'Disabled' };
        if (item.stock <= 0) return { tone: 'out', label: 'Out of stock' };
        if (item.stock <= item.reorder) return { tone: 'low', label: 'Reorder now' };
        if (item.stock <= item.reorder * 1.25) return { tone: 'info', label: 'Getting low' };
        return { tone: 'ok', label: 'Healthy' };
    }

    function daysLeft(item, rate) {
        if (!rate || rate <= 0) return null;
        const days = item.stock / rate;
        return days > 365 ? 365 : days;
    }

    function currentList() {
        const branchEl = document.getElementById('stockBranchFilter') || document.getElementById('stockBranch');
        const selectedBranch = branchEl ? branchEl.value : 'all';
        return stockForBranch(selectedBranch);
    }

    function currentRateMode() {
        const modeEl = document.getElementById('stockRateMode');
        return modeEl ? modeEl.value : 'day';
    }

    // --- CLICKABLE STAT CARDS ---
    function renderStats() {
        const list = currentList();
        const value = list.reduce((sum, i) => sum + i.stock * i.cost, 0);
        const flagged = list.filter((i) => i.is_active && i.stock <= i.reorder).length;
        const statsBox = document.getElementById('inventoryStats');
        const badge = document.getElementById('runningLowBadge');

        if (badge) badge.textContent = flagged;
        if (!statsBox) return;

        statsBox.innerHTML = `
            <article class="stat stat--up" id="cardTotalValue" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px;">
                <p class="stat-label">Total Stock Value</p>
                <p class="stat-value">${pesoDec(value)}</p>
                <p class="stat-trend">Total valuation for the selected branch</p>
            </article>
            
            <article class="stat ${flagged ? 'stat--alert' : 'stat--muted-alert'}" id="cardRunningLow" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; cursor: pointer; border-left: 5px solid var(--flag, #a63d2a);" title="Click to view running low items">
                <p class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                    ${flagged ? '<span style="color: var(--flag, #a63d2a); font-size: 14px;">⚠️</span>' : ''}
                    <span>Items Running Low</span>
                </p>
                <p class="stat-value" style="color: ${flagged ? 'var(--flag, #a63d2a)' : 'inherit'};">${flagged} Items</p>
                <p class="stat-trend">${flagged ? 'Needs immediate replenishment' : 'Stock room healthy'}</p>
            </article>

            <article class="stat stat--up" id="cardTotalItems" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; cursor: pointer;" title="Click to view all items">
                <p class="stat-label">Total Tracked Ingredients</p>
                <p class="stat-value">${list.length} Items</p>
                <p class="stat-trend">Active items monitored in system</p>
            </article>`;

        document.getElementById('cardRunningLow')?.addEventListener('click', filterToLowStock);
        document.getElementById('cardTotalItems')?.addEventListener('click', filterToAllItems);
    }

    function filterToLowStock() {
        const statusFilter = document.getElementById('stockStatusFilter');
        if (statusFilter) statusFilter.value = 'low';
        
        const tabLow = document.getElementById('tabRunningLow');
        const tabAll = document.getElementById('tabAllItems');
        if (tabLow) {
            tabLow.style.color = 'var(--primary, #a63d2a)';
            tabLow.style.borderBottom = '2px solid var(--primary, #a63d2a)';
        }
        if (tabAll) {
            tabAll.style.color = 'var(--muted, #666)';
            tabAll.style.borderBottom = 'none';
        }

        currentPage = 1;
        renderTable();
    }

    function filterToAllItems() {
        const statusFilter = document.getElementById('stockStatusFilter');
        if (statusFilter) statusFilter.value = 'all';
        
        const tabAll = document.getElementById('tabAllItems');
        const tabLow = document.getElementById('tabRunningLow');
        if (tabAll) {
            tabAll.style.color = 'var(--primary, #a63d2a)';
            tabAll.style.borderBottom = '2px solid var(--primary, #a63d2a)';
        }
        if (tabLow) {
            tabLow.style.color = 'var(--muted, #666)';
            tabLow.style.borderBottom = 'none';
        }

        currentPage = 1;
        renderTable();
    }

    // Dynamic Packaging Dropdown Listener based on Measurement Type
    document.getElementById('invIngUnitType')?.addEventListener('change', (e) => {
        const type = e.target.value;
        const purchaseSelect = document.getElementById('invIngPurchaseUnit');
        if (!purchaseSelect) return;

        if (type === 'volume') {
            purchaseSelect.innerHTML = `
                <option value="1L Bottle" data-factor="1000">1 Liter Bottle (1,000 ml)</option>
                <option value="Gallon" data-factor="3785.41">Gallon (3,785.41 ml)</option>
                <option value="500ml Pack" data-factor="500">500ml Pack</option>
                <option value="Individual" data-factor="1">Custom ml (1:1)</option>
            `;
        } else if (type === 'weight') {
            purchaseSelect.innerHTML = `
                <option value="1Kg Bag" data-factor="1000">1 Kilogram Bag (1,000 g)</option>
                <option value="500g Bag" data-factor="500">500 Gram Bag</option>
                <option value="250g Pack" data-factor="250">250 Gram Pack</option>
                <option value="Individual" data-factor="1">Custom Grams (1:1)</option>
            `;
        } else if (type === 'count') {
            purchaseSelect.innerHTML = `
                <option value="Box (100 pcs)" data-factor="100">Box (100 pcs)</option>
                <option value="Pack (50 pcs)" data-factor="50">Pack (50 pcs)</option>
                <option value="Individual" data-factor="1">Single Unit (1:1)</option>
            `;
        }
    });

    // --- TABLE PAGINATION & RENDER ---
    function renderTable() {
        const list = currentList();
        const mode = currentRateMode();
        const body = document.querySelector('#stockTable tbody');
        
        const term = (document.getElementById('stockSearch')?.value || '').trim().toLowerCase();
        const statusVal = document.getElementById('stockStatusFilter')?.value || 'all';
        const unitVal = document.getElementById('stockUnitFilter')?.value || 'all';

        if (!body) return;

        const filtered = list.filter((i) => {
            const matchesSearch = !term || i.name.toLowerCase().includes(term);
            const matchesUnit = unitVal === 'all' || i.unit === unitVal;
            
            const isLow = i.stock <= i.reorder;
            const isOut = i.stock <= 0 || !i.is_active;
            const matchesStatus = statusVal === 'all' ||
                (statusVal === 'low' && isLow && !isOut) ||
                (statusVal === 'healthy' && !isLow && i.is_active) ||
                (statusVal === 'out' && isOut);

            return matchesSearch && matchesUnit && matchesStatus;
        });

        const totalRows = filtered.length;
        const totalPages = Math.ceil(totalRows / pageSize) || 1;

        if (currentPage > totalPages) currentPage = 1;

        const startIdx = (currentPage - 1) * pageSize;
        const endIdx = startIdx + pageSize;
        const visibleItems = filtered.slice(startIdx, endIdx);

        body.innerHTML = visibleItems.map((i) => {
            const rate = usageRate(i, mode);
            const s = status(i);
            const d = daysLeft(i, rate);
            const days = d === null ? '—' : (d < 1 ? 'Today' : d.toFixed(1) + ' d');
            const isActive = i.is_active !== false;

            return `<tr class="${!isActive || s.tone === 'low' || s.tone === 'out' ? 'row--flag' : ''}" style="${!isActive ? 'opacity: 0.6; background-color: rgba(0,0,0,0.02);' : ''}">
                <td>
                    <strong>${i.name}</strong>
                </td>
                <td class="ta-r mono">${num(i.stock)} ${i.unit}</td>
                <td class="ta-r mono">${num(rate)} ${i.unit}</td>
                <td class="ta-r mono">${num(i.reorder)} ${i.unit}</td>
                <td class="ta-r mono">${days}</td>
                <td><span class="badge badge--${s.tone}">${s.label}</span></td>
                <td class="ta-r mono">${pesoDec(i.stock * i.cost)}</td>
                <td class="ta-r">
                    <button type="button" class="btn btn--ghost btn--sm toggle-active-btn" data-key="${i.key}" data-id="${i.id || ''}" style="font-size: 11px; padding: 2px 8px;">
                        ${isActive ? 'Disable' : 'Enable'}
                    </button>
                </td>
            </tr>`;
        }).join('') || '<tr><td colspan="8" class="empty-note">No ingredient matches your filters.</td></tr>';

        bindToggleListeners();
        renderPaginationControls(totalRows, totalPages, startIdx, endIdx);
    }

    // --- TOGGLE INGREDIENT IS_ACTIVE DB LISTENER ---
    function bindToggleListeners() {
        document.querySelectorAll('.toggle-active-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const key = e.target.getAttribute('data-key');
                const id = e.target.getAttribute('data-id');
                const item = combined.find(i => i.key === key);

                if (!item) return;

                // Optimistic UI update
                item.is_active = !item.is_active;
                render();

                // DB Sync via Laravel Endpoint
                if (id) {
                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        await fetch(`/admin/ingredients/${id}/toggle`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken || ''
                            }
                        });
                    } catch (err) {
                        console.error('Failed to update ingredient status in database:', err);
                    }
                }
            });
        });
    }

    function renderPaginationControls(totalRows, totalPages, startIdx, endIdx) {
        const paginationBar = document.getElementById('inventoryPagination');
        const pageInfo = document.getElementById('inventoryPageInfo');
        const pageNumbers = document.getElementById('inventoryPageNumbers');
        const prevBtn = document.getElementById('inventoryPrevBtn');
        const nextBtn = document.getElementById('inventoryNextBtn');

        if (!paginationBar) return;

        if (totalRows <= pageSize) {
            paginationBar.hidden = true;
            return;
        }

        paginationBar.hidden = false;

        if (pageInfo) {
            const from = totalRows ? startIdx + 1 : 0;
            const to = Math.min(endIdx, totalRows);
            pageInfo.textContent = `Showing ${from}–${to} of ${totalRows} items`;
        }

        if (prevBtn) prevBtn.disabled = currentPage <= 1;
        if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

        if (pageNumbers) {
            pageNumbers.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = i;
                btn.className = i === currentPage ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm';
                btn.style.cssText = 'padding: 2px 8px; font-size: 12px;';
                btn.addEventListener('click', () => {
                    currentPage = i;
                    renderTable();
                });
                pageNumbers.appendChild(btn);
            }
        }
    }

    // --- 1-TO-MANY BATCH STOCK-IN ROW BUILDER ---
    function addStockInRow(selectedKey = '', qty = 1) {
        const container = document.getElementById('stockInRowsContainer');
        if (!container) return;

        const row = document.createElement('div');
        row.className = 'stock-in-row';
        row.style.cssText = 'display: flex; gap: 8px; align-items: center; margin-bottom: 6px;';

        const optionsHtml = combined.map((ing) => `
            <option value="${ing.key}" data-unit="${ing.unit}" ${ing.key === selectedKey ? 'selected' : ''}>
                ${ing.name}
            </option>
        `).join('');

        const defaultUnit = selectedKey 
            ? (combined.find(i => i.key === selectedKey)?.unit || 'g')
            : (combined[0]?.unit || 'g');

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

        row.querySelector('.remove-stock-in-row').addEventListener('click', () => { row.remove(); });
        container.appendChild(row);
    }

    // --- 1-TO-MANY BATCH TRANSFER ROW BUILDER ---
    function addTransferRow(selectedKey = '', qty = 1) {
        const container = document.getElementById('transferRowsContainer');
        if (!container) return;

        const row = document.createElement('div');
        row.className = 'transfer-row';
        row.style.cssText = 'display: flex; gap: 8px; align-items: center; margin-bottom: 6px;';

        const optionsHtml = combined.map((ing) => `
            <option value="${ing.key}" data-unit="${ing.unit}" ${ing.key === selectedKey ? 'selected' : ''}>
                ${ing.name}
            </option>
        `).join('');

        const defaultUnit = selectedKey 
            ? (combined.find(i => i.key === selectedKey)?.unit || 'g')
            : (combined[0]?.unit || 'g');

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

        row.querySelector('.remove-transfer-row').addEventListener('click', () => { row.remove(); });
        container.appendChild(row);
    }

    // --- DYNAMIC UNIFIED MODAL SWITCHER LOGIC ---
    const actionTypeSelect = document.getElementById('invActionType');
    const submitBtn = document.getElementById('submitInvActionBtn');

    const sections = {
        stock_in: document.getElementById('secStockIn'),
        stock_out: document.getElementById('secStockOut'),
        transfer: document.getElementById('secTransfer')
    };

    const submitLabels = {
        stock_in: 'Save Stock Delivery',
        stock_out: 'Confirm Deduction',
        transfer: 'Complete Stock Transfer'
    };

    actionTypeSelect?.addEventListener('change', (e) => {
        const selected = e.target.value;

        Object.keys(sections).forEach(key => {
            if (sections[key]) sections[key].hidden = (key !== selected);
        });

        if (submitBtn) submitBtn.textContent = submitLabels[selected] || 'Save';

        if (selected === 'stock_out') populateStockOutSelect();
        if (selected === 'stock_in' && document.getElementById('stockInRowsContainer')?.children.length === 0) addStockInRow();
        if (selected === 'transfer' && document.getElementById('transferRowsContainer')?.children.length === 0) addTransferRow();
    });

    function populateStockOutSelect() {
        const select = document.getElementById('stockOutIngredientSelect');
        if (!select) return;
        select.innerHTML = combined.map(i => `<option value="${i.key}">${i.name} (${i.unit})</option>`).join('');
    }

    // Master Directory: Save New Raw Ingredient Handler
    document.getElementById('saveInvIngredientBtn')?.addEventListener('click', () => {
        const name = document.getElementById('invIngName')?.value.trim();
        const baseUnit = document.getElementById('invIngBaseUnit')?.value;
        const reorder = parseFloat(document.getElementById('invIngReorder')?.value) || 0;
        const cost = parseFloat(document.getElementById('invIngCost')?.value) || 0;

        if (name) {
            combined.push({
                key: name.toLowerCase().replace(/\s+/g, '_'),
                name: name,
                unit: baseUnit,
                stock: 0,
                reorder: reorder,
                used_today: 0,
                cost: cost,
                is_active: true
            });

            render();

            const modal = document.getElementById('addIngredientModal');
            if (modal) modal.hidden = true;
            document.body.classList.remove('is-locked');
        }
    });

    submitBtn?.addEventListener('click', () => {
        const action = actionTypeSelect.value;

        if (action === 'stock_in') {
            const rows = document.querySelectorAll('#stockInRowsContainer .stock-in-row');
            rows.forEach(row => {
                const key = row.querySelector('.stock-in-item-select')?.value;
                const qty = parseFloat(row.querySelector('.stock-in-qty-input')?.value) || 0;
                if (key && qty > 0) {
                    const item = byKeyOf(combined)[key];
                    if (item) item.stock += qty;
                }
            });
        } 
        else if (action === 'stock_out') {
            const key = document.getElementById('stockOutIngredientSelect')?.value;
            const qty = parseFloat(document.getElementById('stockOutQtyInput')?.value) || 0;
            if (key && qty > 0) {
                const item = byKeyOf(combined)[key];
                if (item) item.stock = Math.max(0, item.stock - qty);
            }
        }
        else if (action === 'transfer') {
            const from = document.getElementById('transferFromBranch')?.value;
            const to = document.getElementById('transferToBranch')?.value;
            if (from === to) {
                alert('Source and destination branches must be different.');
                return;
            }
        }

        render();

        const modal = document.getElementById('inventoryActionModal');
        if (modal) modal.hidden = true;
        document.body.classList.remove('is-locked');
    });

    // Row Builder Button Bindings
    document.getElementById('addStockInRowBtn')?.addEventListener('click', () => addStockInRow());
    document.getElementById('addTransferRowBtn')?.addEventListener('click', () => addTransferRow());

    // Reset rows on modal open
    document.querySelector('[data-open-modal="inventoryActionModal"]')?.addEventListener('click', () => {
        if (document.getElementById('stockInRowsContainer')?.children.length === 0) addStockInRow();
    });

    function render() {
        renderStats();
        renderTable();
    }

    // Pagination Button Listeners
    document.getElementById('inventoryPrevBtn')?.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    });

    document.getElementById('inventoryNextBtn')?.addEventListener('click', () => {
        currentPage++;
        renderTable();
    });

    // Tab & Filter Listeners
    document.getElementById('tabRunningLow')?.addEventListener('click', filterToLowStock);
    document.getElementById('tabAllItems')?.addEventListener('click', filterToAllItems);

    ['stockSearch', 'stockBranchFilter', 'stockBranch', 'stockStatusFilter', 'stockUnitFilter'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => { currentPage = 1; render(); });
        document.getElementById(id)?.addEventListener('change', () => { currentPage = 1; render(); });
    });

    render();
}