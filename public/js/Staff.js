/* ==========================================================================
   kape-nated — STAFF JavaScript Asset (POS & Inventory)
   ========================================================================== */

document.addEventListener('click', (e) => {
    const close = e.target.closest('[data-close-modal]');
    if (close) {
        document.getElementById(close.dataset.closeModal)?.classList.remove('open');
        return;
    }

    if (e.target.classList.contains('backdrop')) {
        e.target.classList.remove('open');
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.backdrop.open').forEach((b) => b.classList.remove('open'));
});

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

    function updateModalUnits() {
        if (!ingSelect) return;
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

    function renderStats() {
        const value = ingredients.reduce((sum, i) => sum + Math.max(0, i.stock) * i.cost, 0);
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
                    <span>Items Running Low</span>
                </p>
                <p class="stat-value" style="text-align: right; color: ${flagged ? 'var(--flag, #a63d2a)' : 'inherit'};">${flagged} Items</p>
                <p class="stat-trend">${flagged ? 'Needs immediate replenishment' : 'Stock room healthy'}</p>
            </article>

            <article class="stat stat--up" id="cardTotalItems" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; cursor: pointer;">
                <p class="stat-label">Total Tracked Ingredients</p>
                <p class="stat-value" style="text-align: right;">${ingredients.length} Items</p>
                <p class="stat-trend">Active ingredients monitored</p>
            </article>`;

        document.getElementById('cardRunningLow')?.addEventListener('click', () => {
            const statusFilter = document.getElementById('stockStatusFilter');
            if (statusFilter) statusFilter.value = 'low';
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
            const isLow = i.stock <= i.reorder;
            const isOut = i.stock <= 0;
            
            const matchesStatus = statusVal === 'all' ||
                (statusVal === 'low' && isLow && !isOut) ||
                (statusVal === 'healthy' && !isLow) ||
                (statusVal === 'out' && isOut);

            let matchesAvail = true;
            if (availVal === 'available') {
                matchesAvail = !i.is_disabled && i.stock > 0;
            } else if (availVal === 'unavailable') {
                matchesAvail = !i.is_disabled && i.stock <= 0;
            } else if (availVal === 'disabled') {
                matchesAvail = i.is_disabled;
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
        modal?.classList.add('open');
        updateModalUnits();
    });
    document.getElementById('btnCloseModal')?.addEventListener('click', () => modal?.classList.remove('open'));
    document.getElementById('btnCancelModal')?.addEventListener('click', () => modal?.classList.remove('open'));

    const tabRunningLow = document.getElementById('tabRunningLow');
    const tabAllItems = document.getElementById('tabAllItems');
    const statusFilter = document.getElementById('stockStatusFilter');

    tabRunningLow?.addEventListener('click', () => {
        tabRunningLow.style.fontWeight = '600';
        tabRunningLow.style.borderBottom = '2px solid var(--primary, #a63d2a)';
        tabRunningLow.style.color = 'var(--primary, #a63d2a)';
        
        tabAllItems.style.fontWeight = '500';
        tabAllItems.style.borderBottom = '2px solid transparent';
        tabAllItems.style.color = 'var(--muted, #666)';

        if (statusFilter) statusFilter.value = 'low';
        currentPage = 1;
        renderTable();
    });

    tabAllItems?.addEventListener('click', () => {
        tabAllItems.style.fontWeight = '600';
        tabAllItems.style.borderBottom = '2px solid var(--primary, #a63d2a)';
        tabAllItems.style.color = 'var(--primary, #a63d2a)';
        
        tabRunningLow.style.fontWeight = '500';
        tabRunningLow.style.borderBottom = '2px solid transparent';
        tabRunningLow.style.color = 'var(--muted, #666)';

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
    $('type').innerHTML = ['All', ...types].map((t) => `<option>${esc(t)}</option>`).join('');
    $('type').value = types.includes('Coffee') ? 'Coffee' : 'All';

    function renderGrid() {
        const t = $('type').value;
        const avail = $('availabilityFilter')?.value || 'available';
        const q = $('search').value.trim().toLowerCase();

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

    $('type').onchange = renderGrid;
    $('search').oninput = renderGrid;
    $('availabilityFilter')?.addEventListener('change', renderGrid);

    $('grid').onclick = (e) => {
        const card = e.target.closest('.card');
        if (!card || card.classList.contains('is-unavailable')) return;
        openModal(MENU.find((m) => m.id == card.dataset.id));
    };

    function openModal(item) {
        picking = item;
        const addons = addonsFor(item);
        const sizes = item.sizes || [];

        if (!sizes.length && !item.has_temperature && !addons.length) {
            addLine(item, null, null, []);
            return;
        }

        $('mTitle').textContent = item.name;
        $('mType').textContent = sizes.length ? item.type : `${item.type} · ${short(item.price)}`;

        $('mSizeWrapper').hidden = !sizes.length;
        $('mSizes').innerHTML = sizes.map((s, i) =>
            `<input type="radio" name="size" id="size${i}" value="${s.value}"${i === 0 ? ' checked' : ''}>` +
            `<label for="size${i}">${esc(s.label)} · ${short(s.price)}</label>`
        ).join('');

        $('mTempWrapper').hidden = !item.has_temperature;
        if ($('tCold'))$('tCold').checked = true;

        $('mAddonsWrapper').hidden = !addons.length;
        $('mAddonsHead').textContent = /coffee/i.test(item.type) ? 'Coffee add-ons' : (/soda/i.test(item.type) ? 'Soda add-ons' : 'Add-ons');$('mAddons').innerHTML = addons.map((a) =>
            `<label class="opt"><input type="checkbox" value="${a.id}"> ${esc(a.name)}<span>+${peso(a.price)}</span></label>`
        ).join('');

        refreshPrice();
        $('addonModal').classList.add('open');$('mAdd').focus();
    }

    function selection() {
        const sizeInput = document.querySelector('#addonModal input[name=size]:checked');
        const sizes = picking.sizes || [];
        const size = sizes.length ? (sizes.find((s) => s.value === sizeInput?.value) || sizes[0]) : null;
        const temp = picking.has_temperature ? (document.querySelector('#addonModal input[name=temp]:checked')?.value || 'cold') : null;
        const addons = [...$('mAddons').querySelectorAll('input:checked')].map((c) => ADDONS.find((a) => a.id == c.value));

        return { size, temp, addons, base: size ? size.price : picking.price };
    }

    function refreshPrice() {
        const s = selection();
        $('mAdd').textContent = `Add to Order · ${peso(s.base + s.addons.reduce((t, a) => t + +a.price, 0))}`;
    }
    $('addonModal').addEventListener('change', refreshPrice);

    $('mAdd').onclick = () => {
        const s = selection();
        addLine(picking, s.size, s.temp, s.addons);
        $('addonModal').classList.remove('open');
    };

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
        const msg = $('msg');

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
            if (msg && msg.classList.contains('err')) {
                msg.textContent = '';
                msg.className = 'msg';
            }
            return;
        }

        const change = tendered - total;

        if (change >= 0) {
            changeLabel.textContent = peso(change);
            changeLabel.style.color = '#2e7d32'; 
            if (completeBtn && cart.length > 0) completeBtn.disabled = false;
            if (msg && msg.classList.contains('err')) {
                msg.textContent = '';
                msg.className = 'msg';
            }
        } else {
            changeLabel.textContent = `Short by ${peso(Math.abs(change))}`;
            changeLabel.style.color = '#d32f2f';
            if (completeBtn) completeBtn.disabled = true;
            if (msg) {
                msg.className = 'msg err';
                msg.textContent = 'Cash tendered is less than total amount.';
            }
        }
    }

    function renderCart() {
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
        $('total').textContent = peso(total);

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

    $('lines').onclick = (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        const i = +b.dataset.i;
        if (b.dataset.a === 'inc') cart[i].qty++;
        if (b.dataset.a === 'dec') cart[i].qty > 1 ? cart[i].qty-- : cart[i].splice(i, 1);
        if (b.dataset.a === 'rm') cart.splice(i, 1);
        renderCart();
    };

    $('complete').onclick = async () => {
        const msg = $('msg');
        msg.className = 'msg';
        msg.textContent = '';
        $('complete').disabled = true;

        const paymentMethod = document.querySelector('input[name=pay]:checked').value;
        const gcashRef = $('gcashRef')?.value.trim();

        if (paymentMethod === 'gcash' && !gcashRef) {
            msg.className = 'msg err';
            msg.textContent = 'Please enter the GCash reference number.';
            $('complete').disabled = false;
            return;
        }

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
            if (!res.ok) throw new Error(result.errors?.items?.[0] || result.message || 'Could not save the order.');

            msg.className = 'msg ok';
            msg.textContent = `Order ${result.order_no} saved · ${peso(result.total)}`;
            $('orderNo').textContent = result.next_no;

            // --- POPULATE & SHOW STYLED RECEIPT MODAL ---
            if ($('rcptOrderNo'))$('rcptOrderNo').textContent = result.order_no;
            if ($('rcptPayMethod'))$('rcptPayMethod').textContent = paymentMethod.toUpperCase();
            if ($('rcptDate'))$('rcptDate').textContent = new Date().toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
            if ($('rcptMeta'))$('rcptMeta').textContent = `Transaction Completed Successfully`;
            if ($('rcptTotal'))$('rcptTotal').textContent = peso(result.total);

            if ($('rcptItems')) {$('rcptItems').innerHTML = cart.map(l => {
                    const addText = l.addons.length ? `<br><small style="color:#666; padding-left:8px;">+ ${l.addons.map(a => a.name).join(', ')}</small>` : '';
                    const sizeTemp = [l.sizeLabel, l.temp].filter(Boolean).join(' · ');
                    const subText = sizeTemp ? `<br><small style="color:#666; padding-left:8px;">${subTextFormat(sizeTemp)}</small>` : '';
                    
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

            // Open Receipt Modal
            const receiptModal = $('receiptModal');
            if (receiptModal) receiptModal.classList.add('open');

            cart = [];
            if ($('cashTendered'))$('cashTendered').value = '';
            if ($('gcashRef'))$('gcashRef').value = '';
            renderCart();

        } catch (err) {
            msg.className = 'msg err';
            msg.textContent = err.message;
            $('complete').disabled = !cart.length;
        }
    };

    // Bind Print Button Click
    const printBtn = $('btnPrintReceipt');
    if (printBtn) {
        printBtn.onclick = () => {
            window.print();
        };
    }

    renderGrid();
    renderCart();
}