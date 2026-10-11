/* ==========================================================================
   kape-nated — STAFF JavaScript Asset (POS, Inventory & Receipts)
   ========================================================================== */

document.addEventListener('click', (e) => {
    const close = e.target.closest('[data-close-modal]');
    if (close) {
        const modalEl = document.getElementById(close.dataset.closeModal);
        if (modalEl) {
            modalEl.classList.remove('open');
            if (!document.querySelector('.backdrop.open, .modal.open')) {
                document.body.classList.remove('is-locked');
            }
        }
        return;
    }

    if (e.target.classList.contains('backdrop')) {
        e.target.classList.remove('open');
        if (!document.querySelector('.backdrop.open, .modal.open')) {
            document.body.classList.remove('is-locked');
        }
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.backdrop.open, .modal.open').forEach((b) => {
        b.classList.remove('open');
    });
    document.body.classList.remove('is-locked');
});

function showErrorModal(message, title = 'Transaction Error', modalId = 'orderErrorModal') {
    const errorModal = document.getElementById(modalId);
    const titleEl = modalId === 'staffErrorModal' ? document.getElementById('staffErrorTitle') : document.getElementById('orderErrorTitle');
    const textEl = modalId === 'staffErrorModal' ? document.getElementById('staffErrorText') : document.getElementById('orderErrorText');

    if (titleEl) titleEl.textContent = title;
    if (textEl) textEl.textContent = message;

    if (errorModal) {
        errorModal.classList.add('open');
        document.body.classList.add('is-locked');
    }
}

function showModalNotice(msg, isErr = true) {
    const notice = document.getElementById('staffModalNotice');
    if (!notice) return;
    notice.textContent = msg;
    notice.style.background = isErr ? 'var(--flag-pale)' : 'var(--good-pale)';
    notice.style.color = isErr ? 'var(--flag-dark)' : 'var(--good-dark)';
    notice.style.border = isErr ? '1px solid var(--flag)' : '1px solid var(--good)';
    notice.style.display = 'block';
}

function clearModalNotice() {
    const notice = document.getElementById('staffModalNotice');
    if (notice) notice.style.display = 'none';
}

/**
 * Initialize Staff Inventory Module
 */
function initStaffInventory() {
    const data = window.KAPE_STAFF_INV;
    if (!data) return;

    const num = (n) => Math.round(n).toLocaleString('en-PH');
    const pesoDec = (n) => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const ingredients = data.ingredients;
    let currentPage = 1;
    const pageSize = 10;

    function status(item) {
        if (item.stock <= 0) return { tone: 'out', label: 'Out of stock' };
        if (item.stock <= item.reorder) return { tone: 'low', label: 'Reorder now' };
        if (item.stock <= item.reorder * 1.25) return { tone: 'info', label: 'Getting low' };
        return { tone: 'ok', label: 'Healthy' };
    }

    const ingSelect = document.getElementById('staffIngSelect');
    const actionTypeSelect = document.getElementById('staffActionType');
    const unitMultiplierSelect = document.getElementById('staffUnitMultiplier');
    const unitSelectGroup = document.getElementById('unitSelectGroup');
    const reasonGroup = document.getElementById('staffReasonGroup');
    const submitBtn = document.getElementById('btnSubmitAction');
    const qtyInput = document.getElementById('staffQtyInput');

    function updateModalUnits() {
        if (!ingSelect) return;
        clearModalNotice();
        const selectedOption = ingSelect.options[ingSelect.selectedIndex];
        const baseUnit = selectedOption?.dataset.unit || 'ml';
        const isOut = actionTypeSelect?.value === 'stock_out';

        if (isOut) {
            if (unitSelectGroup) unitSelectGroup.style.display = 'none';
            if (reasonGroup) reasonGroup.hidden = false;
            if (submitBtn) submitBtn.textContent = 'Confirm Stock-Out';
        } else {
            if (unitSelectGroup) unitSelectGroup.style.display = 'flex';
            if (reasonGroup) reasonGroup.hidden = true;
            if (submitBtn) submitBtn.textContent = 'Confirm Stock-In';

            if (unitMultiplierSelect) {
                let options = [];

                if (baseUnit === 'ml') {
                    options = [
                        { val: 1, label: 'ml (Base Unit)' },
                        { val: 1000, label: 'Liter (1,000 ml)' },
                        { val: 3785, label: 'Gallon (3,785 ml)' }
                    ];
                } else if (baseUnit === 'g') {
                    options = [
                        { val: 1, label: 'g (Base Unit)' },
                        { val: 1000, label: 'Kilogram / Kg (1,000 g)' },
                        { val: 500, label: 'Pack (500 g)' }
                    ];
                } else {
                    options = [
                        { val: 1, label: 'pc (Base Unit)' },
                        { val: 50, label: 'Pack (50 pcs)' },
                        { val: 100, label: 'Box (100 pcs)' }
                    ];
                }

                unitMultiplierSelect.innerHTML = options.map(o => `<option value="${o.val}">${o.label}</option>`).join('');
            }
        }
    }

    ingSelect?.addEventListener('change', updateModalUnits);
    actionTypeSelect?.addEventListener('change', updateModalUnits);

    submitBtn?.addEventListener('click', () => {
        clearModalNotice();

        const isOut = actionTypeSelect?.value === 'stock_out';
        const selectedOpt = ingSelect?.options[ingSelect.selectedIndex];
        if (!selectedOpt) {
            showModalNotice('Please select a valid ingredient.');
            return;
        }

        const ingName = selectedOpt.text.split(' (Available:')[0].trim();
        const availableStock = parseFloat(selectedOpt.dataset.stock || '0');
        const baseUnit = selectedOpt.dataset.unit || '';
        const isDisabled = selectedOpt.dataset.disabled === '1';
        const inputQty = parseFloat(qtyInput?.value || '0');

        if (isDisabled) {
            showModalNotice(`Cannot perform stock operation: '${ingName}' has been disabled by Admin.`);
            return;
        }

        if (isNaN(inputQty) || inputQty <= 0) {
            showModalNotice('Please enter a valid positive quantity.');
            qtyInput?.focus();
            return;
        }

        if (isOut) {
            if (availableStock <= 0) {
                showModalNotice(`Cannot perform stock-out: '${ingName}' is currently out of stock (0 ${baseUnit}).`);
                return;
            }

            if (inputQty > availableStock) {
                const formattedAvail = availableStock.toLocaleString('en-PH');
                showModalNotice(`Cannot deduct ${inputQty} ${baseUnit}. Only ${formattedAvail} ${baseUnit} available.`);
                qtyInput?.focus();
                return;
            }
        }

        const actionText = isOut ? 'Stock Deduction (Stock-Out)' : 'Stock Replenishment (Stock-In)';
        const unitMultiplier = isOut ? 1 : (parseFloat(unitMultiplierSelect?.value) || 1);
        const totalBaseQty = inputQty * unitMultiplier;
        const totalDisplay = `${totalBaseQty.toLocaleString('en-PH')} ${baseUnit}`;

        const summaryEl = document.getElementById('confirmActionSummary');
        const detailEl = document.getElementById('confirmActionDetail');

        if (summaryEl) summaryEl.textContent = `${actionText}: ${ingName}`;
        if (detailEl) detailEl.textContent = `Quantity: ${inputQty} input unit(s) → Net Change: ${isOut ? '-' : '+'}${totalDisplay}`;

        const confirmModal = document.getElementById('confirmActionModal');
        if (confirmModal) {
            confirmModal.classList.add('open');
            document.body.classList.add('is-locked');
        }
    });

    document.getElementById('btnProceedActionConfirm')?.addEventListener('click', async () => {
        const confirmModal = document.getElementById('confirmActionModal');
        const actionModal = document.getElementById('staffActionModal');
        const form = document.getElementById('staffActionForm');

        if (confirmModal) confirmModal.classList.remove('open');
        if (!form) return;

        const formData = new FormData(form);

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: formData
            });

            const result = await res.json().catch(() => ({}));

            if (!res.ok) {
                const message = result.message || (result.errors ? Object.values(result.errors)[0][0] : 'Failed to perform stock action.');
                showErrorModal(message, 'Action Failed', 'staffErrorModal');
                return;
            }

            if (actionModal) actionModal.classList.remove('open');

            const successModal = document.getElementById('staffSuccessModal');
            const successText = document.getElementById('staffSuccessText');
            if (successText) successText.textContent = result.message || 'Inventory changes updated successfully.';

            if (successModal) {
                successModal.classList.add('open');
                document.body.classList.add('is-locked');
            }

        } catch (err) {
            showErrorModal('Network error or server connection failure.', 'Action Failed', 'staffErrorModal');
        }
    });

    document.getElementById('btnCancelActionConfirm')?.addEventListener('click', () => {
        const confirmModal = document.getElementById('confirmActionModal');
        if (confirmModal) confirmModal.classList.remove('open');
    });

    document.getElementById('btnStaffSuccessDone')?.addEventListener('click', () => {
        window.location.reload();
    });

    function renderStats() {
        const value = ingredients.reduce((sum, i) => sum + Math.max(0, i.stock) * i.cost, 0);
        // STOCK ALERT: Counts both low stock (stock <= reorder) AND out of stock (stock <= 0)
        const flagged = ingredients.filter((i) => i.stock <= i.reorder).length;
        const statsBox = document.getElementById('inventoryStats');
        const badge = document.getElementById('runningLowBadge');

        if (badge) badge.textContent = flagged;
        if (!statsBox) return;

        statsBox.innerHTML = `
            <article class="stat stat--up" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px;">
                <p class="stat-label">Total Stock Value</p>
                <p class="stat-value" style="text-align: right;">${pesoDec(value)}</p>
                <p class="stat-trend">Total valuation for assigned branch</p>
            </article>
            
            <article class="stat ${flagged ? 'stat--alert' : 'stat--muted-alert'}" id="cardRunningLow" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; cursor: pointer; border-left: 5px solid var(--flag, #a63d2a);">
                <p class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                    ${flagged ? '<span style="color: var(--flag, #a63d2a);">⚠️</span>' : ''}
                    <span>Stock Alert</span>
                </p>
                <p class="stat-value" style="text-align: right; color: ${flagged ? 'var(--flag, #a63d2a)' : 'inherit'};">${flagged} Items</p>
                <p class="stat-trend">${flagged ? 'Items low or out of stock' : 'Stock room healthy'}</p>
            </article>

            <article class="stat stat--up" id="cardTotalItems" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; cursor: pointer;">
                <p class="stat-label">Total Tracked Ingredients</p>
                <p class="stat-value" style="text-align: right;">${ingredients.length} Items</p>
                <p class="stat-trend">Active ingredients monitored</p>
            </article>`;

        document.getElementById('cardRunningLow')?.addEventListener('click', () => {
            const statusFilter = document.getElementById('stockStatusFilter');
            if (statusFilter) statusFilter.value = 'alert';
            currentPage = 1;
            renderTable();
        });
        document.getElementById('cardTotalItems')?.addEventListener('click', () => {
            const statusFilter = document.getElementById('stockStatusFilter');
            if (statusFilter) statusFilter.value = 'all';
            currentPage = 1;
            renderTable();
        });
    }

    function renderTable() {
        const body = document.querySelector('#stockTable tbody');
        const term = (document.getElementById('stockSearch')?.value || '').trim().toLowerCase();
        const statusVal = document.getElementById('stockStatusFilter')?.value || 'all';
        const availVal = document.getElementById('stockAvailabilityFilter')?.value || 'all';
        const unitVal = document.getElementById('stockUnitFilter')?.value || 'all';
        if (!body) return;

        const filtered = ingredients.filter((i) => {
            const matchesSearch = !term || i.name.toLowerCase().includes(term);
            const matchesUnit = unitVal === 'all' || i.unit === unitVal;
            
            const isLow = i.stock > 0 && i.stock <= i.reorder;
            const isOut = i.stock <= 0;
            const isAlert = i.stock <= i.reorder;
            const isDisabled = Boolean(i.is_disabled);
            
            const matchesStatus = statusVal === 'all' ||
                (statusVal === 'alert' && isAlert) ||
                (statusVal === 'low' && isLow) ||
                (statusVal === 'healthy' && !isAlert) ||
                (statusVal === 'out' && isOut);

            let matchesAvail = true;
            if (availVal === 'available') {
                matchesAvail = !isDisabled && i.stock > 0;
            } else if (availVal === 'unavailable' || availVal === 'out') {
                matchesAvail = !isDisabled && i.stock <= 0;
            } else if (availVal === 'disabled') {
                matchesAvail = isDisabled;
            }

            return matchesSearch && matchesUnit && matchesStatus && matchesAvail;
        });

        const totalRows = filtered.length;
        const totalPages = Math.ceil(totalRows / pageSize) || 1;
        if (currentPage > totalPages) currentPage = 1;

        const startIdx = (currentPage - 1) * pageSize;
        const visibleItems = filtered.slice(startIdx, startIdx + pageSize);

        body.innerHTML = visibleItems.map((i) => {
            const s = status(i);
            const safeStock = Math.max(0, i.stock);
            const days = i.used_today > 0 && safeStock > 0 ? (safeStock / i.used_today).toFixed(1) + ' d' : '—';
            
            let availBadge = '<span class="badge badge--ok">Available</span>';
            if (i.is_disabled) {
                availBadge = '<span class="badge badge--muted">Disabled</span>';
            } else if (i.stock <= 0) {
                availBadge = '<span class="badge badge--out">Unavailable</span>';
            }

            return `<tr class="${s.tone === 'low' || s.tone === 'out' ? 'row--flag' : ''}">
                <td>${i.name}</td>
                <td class="ta-r mono">${num(safeStock)} ${i.unit}</td>
                <td class="ta-r mono">${num(i.used_today)} ${i.unit}</td>
                <td class="ta-r mono">${num(i.reorder)} ${i.unit}</td>
                <td class="ta-r mono">${days}</td>
                <td><span class="badge badge--${s.tone}">${s.label}</span></td>
                <td>${availBadge}</td>
                <td class="ta-r mono">${pesoDec(safeStock * i.cost)}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="8" class="empty-note">No ingredient matches your filters.</td></tr>';

        renderPagination(totalRows, totalPages, startIdx);
    }

    function renderPagination(totalRows, totalPages, startIdx) {
        const bar = document.getElementById('inventoryPagination');
        const info = document.getElementById('inventoryPageInfo');
        const nums = document.getElementById('inventoryPageNumbers');
        const prev = document.getElementById('inventoryPrevBtn');
        const next = document.getElementById('inventoryNextBtn');

        if (!bar) return;
        if (totalRows <= pageSize) {
            bar.hidden = true;
            return;
        }

        bar.hidden = false;
        if (info) info.textContent = `Showing ${totalRows ? startIdx + 1 : 0}–${Math.min(startIdx + pageSize, totalRows)} of ${totalRows} items`;
        if (prev) prev.disabled = currentPage <= 1;
        if (next) next.disabled = currentPage >= totalPages;

        if (nums) {
            nums.innerHTML = '';
            for (let p = 1; p <= totalPages; p++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = p;
                btn.className = p === currentPage ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm';
                btn.style.cssText = 'padding: 2px 8px; font-size: 12px; margin: 0 2px;';
                btn.onclick = () => { currentPage = p; renderTable(); };
                nums.appendChild(btn);
            }
        }
    }

    const modal = document.getElementById('staffActionModal');
    document.getElementById('btnOpenActionModal')?.addEventListener('click', () => {
        if (modal) {
            modal.classList.add('open');
            document.body.classList.add('is-locked');
        }
        updateModalUnits();
    });
    
    const closeModalFunc = () => {
        if (modal) {
            modal.classList.remove('open');
            document.body.classList.remove('is-locked');
        }
    };
    document.getElementById('btnCloseModal')?.addEventListener('click', closeModalFunc);
    document.getElementById('btnCancelModal')?.addEventListener('click', closeModalFunc);

    const tabRunningLow = document.getElementById('tabRunningLow');
    const tabAllItems = document.getElementById('tabAllItems');
    const statusFilter = document.getElementById('stockStatusFilter');

    tabRunningLow?.addEventListener('click', () => {
        tabRunningLow.style.fontWeight = '600';
        tabRunningLow.style.borderBottom = '2px solid var(--primary)';
        tabRunningLow.style.color = 'var(--primary)';
        
        tabAllItems.style.fontWeight = '500';
        tabAllItems.style.borderBottom = '2px solid transparent';
        tabAllItems.style.color = 'var(--muted)';

        if (statusFilter) statusFilter.value = 'alert';
        currentPage = 1;
        renderTable();
    });

    tabAllItems?.addEventListener('click', () => {
        tabAllItems.style.fontWeight = '600';
        tabAllItems.style.borderBottom = '2px solid var(--primary)';
        tabAllItems.style.color = 'var(--primary)';
        
        tabRunningLow.style.fontWeight = '500';
        tabRunningLow.style.borderBottom = '2px solid transparent';
        tabRunningLow.style.color = 'var(--muted)';

        if (statusFilter) statusFilter.value = 'all';
        currentPage = 1;
        renderTable();
    });

    ['stockSearch', 'stockStatusFilter', 'stockAvailabilityFilter', 'stockUnitFilter'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => { currentPage = 1; renderTable(); });
        document.getElementById(id)?.addEventListener('change', () => { currentPage = 1; renderTable(); });
    });

    document.getElementById('inventoryPrevBtn')?.addEventListener('click', () => {
        if (currentPage > 1) { currentPage--; renderTable(); }
    });
    document.getElementById('inventoryNextBtn')?.addEventListener('click', () => {
        currentPage++; renderTable();
    });

    renderStats();
    renderTable();
}

/**
 * Initialize Staff POS Ordering Module
 */
function initStaffOrder() {
    const data = window.KAPE_ORDER;
    if (!data) return;

    const MENU = data.menu;       
    const ADDONS = data.addons;   
    const $ = (id) => document.getElementById(id);
    const peso = (n) => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const short = (n) => '₱' + (Number.isInteger(+n) ? +n : (+n).toFixed(2));
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const subTextFormat = (text) => text.charAt(0).toUpperCase() + text.slice(1);

    let cart = [];       
    let picking = null;  

    const addonsFor = (item) => ADDONS.filter((a) => !a.drink_type_ids.length || a.drink_type_ids.includes(item.drink_type_id));
    const priceText = (m) => (m.sizes && m.sizes.length ? m.sizes.map((s) => short(s.price)).join('–') : short(m.price));

    const types = [...new Set(MENU.map((m) => m.type))];
    if ($('type')) {$('type').innerHTML = ['All', ...types].map((t) => `<option>${esc(t)}</option>`).join('');
        $('type').value = types.includes('Coffee') ? 'Coffee' : 'All';
    }

    function renderGrid() {
        if (!$('grid')) return;
        const t = $('type')?.value || 'All';
        const avail = $('availabilityFilter')?.value || 'available';
        const q = ($('search')?.value || '').trim().toLowerCase();

        const list = MENU.filter((m) => {
            const matchesType = (t === 'All' || m.type === t);
            const matchesSearch = m.name.toLowerCase().includes(q);
            
            let matchesAvail = true;
            if (avail === 'available') {
                matchesAvail = !m.is_out_of_stock;
            } else if (avail === 'unavailable') {
                matchesAvail = m.is_out_of_stock;
            }

            return matchesType && matchesSearch && matchesAvail;
        });

        $('grid').innerHTML = list.length
            ? list.map((m) => {
                const unavailableClass = m.is_out_of_stock ? 'is-unavailable' : '';
                const overlayHtml = m.is_out_of_stock 
                    ? `<div class="card-unavailable-overlay"><span class="card-unavailable-badge">Unavailable</span></div>` 
                    : '';

                return `
                    <button class="card ${unavailableClass}" data-id="${m.id}" ${m.is_out_of_stock ? 'disabled' : ''}>
                        <span class="pic">
                            <span class="price-tag">${priceText(m)}</span>
                            ${overlayHtml}
                        </span>
                        <span class="label">${esc(m.name)}</span>
                    </button>
                `;
            }).join('')
            : '<div class="empty">No drinks match your filter.</div>';
    }

    if ($('type'))$('type').onchange = renderGrid;
    if ($('search'))$('search').oninput = renderGrid;
    $('availabilityFilter')?.addEventListener('change', renderGrid);

    if ($('grid')) {$('grid').onclick = (e) => {
            const card = e.target.closest('.card');
            if (!card || card.classList.contains('is-unavailable')) return;
            openModal(MENU.find((m) => m.id == card.dataset.id));
        };
    }

    function openModal(item) {
        picking = item;
        const addons = addonsFor(item);
        const sizes = item.sizes || [];

        if (!sizes.length && !item.has_temperature && !addons.length) {
            addLine(item, null, null, []);
            return;
        }

        if ($('mTitle'))$('mTitle').textContent = item.name;
        if ($('mType'))$('mType').textContent = sizes.length ? item.type : `${item.type} · ${short(item.price)}`;

        if ($('mSizeWrapper'))$('mSizeWrapper').hidden = !sizes.length;
        if ($('mSizes')) {$('mSizes').innerHTML = sizes.map((s, i) =>
                `<input type="radio" name="size" id="size${i}" value="${s.value}"${i === 0 ? ' checked' : ''}>` +
                `<label for="size${i}">${esc(s.label)} · ${short(s.price)}</label>`
            ).join('');
        }

        if ($('mTempWrapper'))$('mTempWrapper').hidden = !item.has_temperature;
        if ($('tCold'))$('tCold').checked = true;

        if ($('mAddonsWrapper'))$('mAddonsWrapper').hidden = !addons.length;
        if ($('mAddonsHead'))$('mAddonsHead').textContent = /coffee/i.test(item.type) ? 'Coffee add-ons' : (/soda/i.test(item.type) ? 'Soda add-ons' : 'Add-ons');
        
        if ($('mAddons')) {$('mAddons').innerHTML = addons.map((a) => {
                const isDisabled = a.is_disabled;
                const disAttr = isDisabled ? 'disabled' : '';
                const disLabel = isDisabled ? ' <small style="color: var(--flag, #a63d2a);">(Unavailable)</small>' : '';
                const style = isDisabled ? 'style="opacity: 0.55; cursor: not-allowed;"' : '';

                return `<label class="opt" ${style}>
                    <input type="checkbox" value="${a.id}" ${disAttr}> ${esc(a.name)}${disLabel}
                    <span>+${peso(a.price)}</span>
                </label>`;
            }).join('');
        }

        refreshPrice();
        const modal = $('addonModal');
        if (modal) {
            modal.classList.add('open');
            document.body.classList.add('is-locked');
        }
        $('mAdd')?.focus();
    }

    function selection() {
        const sizeInput = document.querySelector('#addonModal input[name=size]:checked');
        const sizes = picking.sizes || [];
        const size = sizes.length ? (sizes.find((s) => s.value === sizeInput?.value) || sizes[0]) : null;
        const temp = picking.has_temperature ? (document.querySelector('#addonModal input[name=temp]:checked')?.value || 'cold') : null;
        const addons = [...($('mAddons')?.querySelectorAll('input:checked') || [])].map((c) => ADDONS.find((a) => a.id == c.value));

        return { size, temp, addons, base: size ? size.price : picking.price };
    }

    function refreshPrice() {
        const s = selection();
        if ($('mAdd'))$('mAdd').textContent = `Add to Order · ${peso(s.base + s.addons.reduce((t, a) => t + +a.price, 0))}`;
    }
    $('addonModal')?.addEventListener('change', refreshPrice);

    if ($('mAdd')) {$('mAdd').onclick = () => {
            const s = selection();
            addLine(picking, s.size, s.temp, s.addons);
            const modal = $('addonModal');
            if (modal) {
                modal.classList.remove('open');
                document.body.classList.remove('is-locked');
            }
        };
    }

    function addLine(item, size, temp, addons) {
        const line = {
            id: item.id,
            name: item.name,
            basePrice: size ? +size.price : +item.price,
            size: size ? size.value : null,
            sizeLabel: size ? size.label : null,
            temp,
            addons,
            qty: 1,
        };

        const key = (l) => `${l.id}_${l.size || ''}_${l.temp || ''}_${l.addons.map((a) => a.id).sort().join(',')}`;
        const same = cart.find((l) => key(l) === key(line));
        same ? same.qty++ : cart.push(line);

        renderCart();
    }

    const unit = (l) => l.basePrice + l.addons.reduce((s, a) => s + +a.price, 0);
    const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

    function updatePaymentAreas(total) {
        const cashArea = $('cashCalcArea');
        const gcashArea = $('gcashCalcArea');
        const tenderedInput = $('cashTendered');
        const changeLabel = $('cashChange');
        const gcashRefInput = $('gcashRef');
        const completeBtn = $('complete');

        const payMethod = document.querySelector('input[name="pay"]:checked')?.value || 'cash';

        if (cashArea) cashArea.style.display = payMethod === 'cash' ? 'block' : 'none';
        if (gcashArea) {
            gcashArea.style.display = payMethod === 'gcash' ? 'block' : 'none';
            gcashArea.removeAttribute('hidden');
        }

        if (payMethod === 'gcash') {
            if (tenderedInput) tenderedInput.value = '';
            if (changeLabel) changeLabel.textContent = '₱0.00';
            if (completeBtn) completeBtn.disabled = !cart.length;
            return;
        }

        if (payMethod === 'cash') {
            if (gcashRefInput) gcashRefInput.value = '';
        }

        if (!tenderedInput || !changeLabel) return;

        const tenderedVal = tenderedInput.value.trim();
        const tendered = parseFloat(tenderedVal) || 0;

        if (tenderedVal === '' || tendered === 0) {
            changeLabel.textContent = '₱0.00 (Exact)';
            changeLabel.style.color = 'inherit';
            if (completeBtn && cart.length > 0) completeBtn.disabled = false;
            return;
        }

        const change = tendered - total;

        if (change >= 0) {
            changeLabel.textContent = peso(change);
            changeLabel.style.color = 'var(--good)'; 
            if (completeBtn && cart.length > 0) completeBtn.disabled = false;
        } else {
            changeLabel.textContent = `Short by ${peso(Math.abs(change))}`;
            changeLabel.style.color = 'var(--flag)';
            if (completeBtn) completeBtn.disabled = true;
        }
    }

    function renderCart() {
        if (!$('lines')) return;
        $('lines').innerHTML = cart.length
            ? cart.map((l, i) => {
                const details = [];
                const unitPrice = unit(l);
                details.push(`${l.qty} × ${peso(unitPrice)}`);

                if (l.sizeLabel) details.push(esc(l.sizeLabel));
                if (l.temp) details.push(cap(l.temp));
                if (l.addons.length) details.push(l.addons.map((a) => esc(a.name)).join(', '));

                return `
                <div class="line">
                    <b>${esc(l.name)}</b><span class="amt">${peso(unitPrice * l.qty)}</span>
                    <small>${details.join(' · ')}</small>
                    <div class="qty">
                        <button data-a="dec" data-i="${i}" aria-label="Fewer">−</button><span>${l.qty}</span>
                        <button data-a="inc" data-i="${i}" aria-label="More">+</button>
                        <button class="rm" data-a="rm" data-i="${i}">Remove</button>
                    </div>
                </div>`;
            }).join('')
            : '<div class="hint">Tap menu item to add here</div>';

        const total = cart.reduce((s, l) => s + unit(l) * l.qty, 0);
        if ($('total'))$('total').textContent = peso(total);

        updatePaymentAreas(total);
    }

    document.querySelectorAll('input[name="pay"]').forEach((r) => {
        r.onchange = () => {
            const total = cart.reduce((s, l) => s + unit(l) * l.qty, 0);
            updatePaymentAreas(total);
        };
    });

    if ($('cashTendered')) {$('cashTendered').oninput = () => {
            const total = cart.reduce((s, l) => s + unit(l) * l.qty, 0);
            updatePaymentAreas(total);
        };
    }

    if ($('lines')) {$('lines').onclick = (e) => {
            const b = e.target.closest('button');
            if (!b) return;
            const i = +b.dataset.i;
            if (b.dataset.a === 'inc') cart[i].qty++;
            if (b.dataset.a === 'dec') {
                if (cart[i].qty > 1) {
                    cart[i].qty--;
                } else {
                    cart.splice(i, 1);
                }
            }
            if (b.dataset.a === 'rm') {
                cart.splice(i, 1);
            }
            renderCart();
        };
    }

    const confirmModal = $('confirmOrderModal');
    const btnCancelConfirm = $('btnCancelOrderConfirm');
    const btnSubmitConfirm = $('btnSubmitOrderConfirm');

    if (btnCancelConfirm) {
        btnCancelConfirm.onclick = () => {
            if (confirmModal) {
                confirmModal.classList.remove('open');
                document.body.classList.remove('is-locked');
            }
        };
    }

    if ($('complete')) {$('complete').onclick = () => {
            const paymentMethod = document.querySelector('input[name=pay]:checked')?.value || 'cash';
            const gcashRef = $('gcashRef')?.value.trim();

            if (paymentMethod === 'gcash' && !gcashRef) {
                showErrorModal('Please enter the GCash reference number.', 'Missing Information');
                return;
            }

            const total = cart.reduce((s, l) => s + unit(l) * l.qty, 0);
            if ($('confirmModalTotal'))$('confirmModalTotal').textContent = peso(total);
            if ($('confirmModalPayType'))$('confirmModalPayType').textContent = `Payment via ${paymentMethod}`;

            if (confirmModal) {
                confirmModal.classList.add('open');
                document.body.classList.add('is-locked');
            }
        };
    }

    if (btnSubmitConfirm) {
        btnSubmitConfirm.onclick = async () => {
            if (confirmModal) {
                confirmModal.classList.remove('open');
                document.body.classList.remove('is-locked');
            }
            
            if ($('complete'))$('complete').disabled = true;

            const paymentMethod = document.querySelector('input[name=pay]:checked')?.value || 'cash';
            const gcashRef = $('gcashRef')?.value.trim();

            try {
                const res = await fetch(data.storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        payment: paymentMethod,
                        gcash_ref: paymentMethod === 'gcash' ? gcashRef : null,
                        items: cart.map((l) => ({
                            id: l.id,
                            qty: l.qty,
                            size: l.size,
                            temp: l.temp,
                            addons: l.addons.map((a) => a.id),
                        })),
                    }),
                });

                const result = await res.json();
                
                if (!res.ok) {
                    const errText = result.errors?.items?.[0] || result.message || 'Could not complete the order.';
                    throw new Error(errText);
                }

                if ($('orderNo'))$('orderNo').textContent = result.next_no;

                if ($('rcptOrderNo'))$('rcptOrderNo').textContent = result.order_no;
                if ($('rcptPayMethod'))$('rcptPayMethod').textContent = paymentMethod.toUpperCase();
                if ($('rcptDate'))$('rcptDate').textContent = new Date().toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
                if ($('rcptMeta'))$('rcptMeta').textContent = `Transaction Completed Successfully`;
                if ($('rcptTotal'))$('rcptTotal').textContent = peso(result.total);

                if ($('rcptItems')) {$('rcptItems').innerHTML = cart.map(l => {
                        const addText = l.addons.length ? `<br><small style="color:var(--ink-soft); padding-left:8px;">+ ${l.addons.map(a => a.name).join(', ')}</small>` : '';
                        const sizeTemp = [l.sizeLabel, l.temp].filter(Boolean).join(' · ');
                        const subText = sizeTemp ? `<br><small style="color:var(--ink-soft); padding-left:8px;">${subTextFormat(sizeTemp)}</small>` : '';
                        
                        return `
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span>${l.qty}x ${esc(l.name)}${subText}${addText}</span>
                                <span>${peso(unit(l) * l.qty)}</span>
                            </div>
                        `;
                    }).join('');
                }

                const tendered = parseFloat($('cashTendered')?.value) || 0;
                if ($('rcptCashRow')) {
                    if (paymentMethod === 'cash' && tendered > 0) {
                        $('rcptCashRow').hidden = false;
                        if ($('rcptChange'))$('rcptChange').textContent = `${peso(tendered)} / ${peso(tendered - result.total)}`;
                    } else {
                        $('rcptCashRow').hidden = true;
                    }
                }

                const receiptModal = $('receiptModal');
                if (receiptModal) {
                    receiptModal.classList.add('open');
                    document.body.classList.add('is-locked');
                }

                cart = [];
                if ($('cashTendered'))$('cashTendered').value = '';
                if ($('gcashRef'))$('gcashRef').value = '';
                renderCart();

            } catch (err) {
                showErrorModal(err.message, 'Order Failed');
                if ($('complete'))$('complete').disabled = !cart.length;
            }
        };
    }

    const printBtn = $('btnPrintReceipt');
    if (printBtn) {
        printBtn.onclick = () => {
            window.print();
        };
    }

    renderGrid();
    renderCart();
}

/**
 * Initialize Staff Receipts / Transaction History Module
 */
function initStaffReceipts() {
    const table = document.getElementById('receiptTable');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const PAGE_SIZE = 10;
    let currentPage = 1;

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
        const mode = rangeModeSelect?.value || 'day';
        if (dayWrap) dayWrap.hidden = mode !== 'day';
        if (monthWrap) monthWrap.hidden = mode !== 'month';
        if (yearWrap) yearWrap.hidden = mode !== 'year';
    }

    function getFilteredRows() {
        const paymentVal = (paymentSelect?.value || 'all').trim().toLowerCase();
        const rangeMode = rangeModeSelect?.value || 'day';
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

    function renderTable() {
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
            prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; renderTable(); } };
        }

        if (nextBtn) {
            nextBtn.disabled = currentPage >= totalPages;
            nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; renderTable(); } };
        }

        buildPageButtons(totalPages);
        updateCashDrawerAudit(filtered);
    }

    function buildPageButtons(totalPages) {
        if (!pageNumbers) return;
        pageNumbers.innerHTML = '';
        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = i;
            btn.className = i === currentPage ? 'btn btn--sm btn--primary' : 'btn btn--sm btn--ghost';
            btn.style.cssText = 'padding: 2px 8px; margin: 0 2px;';
            btn.onclick = () => { currentPage = i; renderTable(); };
            pageNumbers.appendChild(btn);
        }
    }

    function updateCashDrawerAudit(filteredRows) {
        if (!reconExpected) return;

        let cashSum = 0;
        filteredRows.forEach(row => {
            const isCash = (row.dataset.payment || '').toLowerCase() === 'cash';
            if (isCash) {
                const totalCell = row.querySelector('.mono.ta-r')?.textContent || '0';
                const num = parseFloat(totalCell.replace(/[^0-9.]/g, '')) || 0;
                cashSum += num;
            }
        });

        reconExpected.value = '₱' + cashSum.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const actualVal = parseFloat(reconActual?.value) || 0;
        if (reconActual?.value !== '' && reconVariance) {
            const diff = actualVal - cashSum;
            reconVariance.value = (diff >= 0 ? '+' : '') + '₱' + diff.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            reconVariance.style.color = diff < 0 ? 'var(--flag)' : 'var(--good)';
        } else if (reconVariance) {
            reconVariance.value = '₱0.00';
            reconVariance.style.color = 'inherit';
        }
    }

    [paymentSelect, rangeModeSelect, dayInput, monthInput, yearInput].forEach(el => {
        el?.addEventListener('change', () => { currentPage = 1; renderTable(); });
    });

    searchInput?.addEventListener('input', () => { currentPage = 1; renderTable(); });
    reconActual?.addEventListener('input', () => { updateCashDrawerAudit(getFilteredRows()); });

    document.addEventListener('click', (e) => {
        const openBtn = e.target.closest('[data-open-modal]');
        if (openBtn) {
            const modal = document.getElementById(openBtn.dataset.openModal);
            if (modal) {
                modal.classList.add('open');
                document.body.classList.add('is-locked');
            }
        }
    });

    renderTable();
}

function printReceipt(containerId) {
    const printElement = document.getElementById(containerId);
    if (!printElement) return;

    const printContents = printElement.innerHTML;
    const originalContents = document.body.innerHTML;

    document.body.innerHTML = `<div style="padding: 20px; font-family: monospace;">${printContents}</div>`;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
}