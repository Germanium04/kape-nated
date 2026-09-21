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

/* ---------- receipts: branch, payment, range, and search ---------- */
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

    function apply() {
        const term = (search.value || '').trim().toLowerCase();
        const pay = payment.value;
        const br = branch.value;
        let shown = 0;
        let total = 0;

        rows.forEach((row) => {
            const hit = (!term || row.dataset.search.includes(term)) &&
                        (pay === 'all' || row.dataset.payment === pay) &&
                        (br === 'all' || row.dataset.branch === br) &&
                        matchesRange(row.dataset.date);
            row.hidden = !hit;
            if (hit) {
                shown++;
                const amount = row.querySelector('td.ta-r.mono');
                if (amount) total += parseFloat(amount.textContent.replace(/[₱,]/g, '')) || 0;
            }
        });

        empty.hidden = shown > 0;
        summary.textContent = shown
            ? `${shown} transaction${shown === 1 ? '' : 's'} · ₱${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`
            : '';
    }

    rangeMode.addEventListener('change', () => {
        Object.values(wraps).forEach((w) => { w.hidden = true; });
        if (wraps[rangeMode.value]) wraps[rangeMode.value].hidden = false;
        apply();
    });

    [search, payment, branch, rangeDay, rangeMonth, rangeYear].forEach((el) => {
        el.addEventListener('input', apply);
        el.addEventListener('change', apply);
    });

    apply();
})();

/* ---------- shared: deterministic "random" so estimates don't jump around on every render ---------- */
function seedNum(str) {
    let h = 0;
    for (let i = 0; i < str.length; i++) { h = (h * 31 + str.charCodeAt(i)) >>> 0; }
    return (h % 1000) / 1000; // 0..1, stable for the same input
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

    /* Real days we actually have (13–19 Sep 2026); anything else is an estimate. */
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
        const mode = modeSel.value;
        const value = mode === 'day' ? dayInput.value : mode === 'month' ? monthInput.value : yearSel.value;
        const weight = weightOf(branchSel.value);
        const s = statsFor(mode, value, weight);
        const label = { day: 'that day', month: 'that month', year: 'that year' }[mode];

        document.getElementById('salesStats').innerHTML = `
            <article class="stat stat--up"><p class="stat-label">Gross sales</p><p class="stat-value">${peso(s.total)}</p><p class="stat-trend">${s.real ? 'Recorded' : 'Estimated'} — ${label}</p></article>
            <article class="stat"><p class="stat-label">Orders</p><p class="stat-value">${s.orders.toLocaleString('en-PH')}</p><p class="stat-trend">Closed transactions</p></article>
            <article class="stat"><p class="stat-label">Average ticket</p><p class="stat-value">${pesoDec(s.avg)}</p><p class="stat-trend">Per order</p></article>
            <article class="stat"><p class="stat-label">Cups served</p><p class="stat-value">${s.cups.toLocaleString('en-PH')}</p><p class="stat-trend">All drinks</p></article>`;
    }

    function renderBranchRanking() {
        const mode = modeSel.value;
        const value = mode === 'day' ? dayInput.value : mode === 'month' ? monthInput.value : yearSel.value;
        const networkTotal = statsFor(mode, value, 1).total; // weight 1 = the whole network, before any branch filter

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

/* ---------- inventory ---------- */
function initInventory() {
    const data = window.KAPE;
    if (!data) return;

    const num = (n) => Math.round(n).toLocaleString('en-PH');

    /*
     * There's one real (combined) stock count in this prototype. "Per branch" is
     * that combined count split by each branch's usual share (branchWeights), so
     * the screen behaves correctly now. A real backend stores stock per branch
     * directly and this scaling step goes away entirely.
     */
    const originalCombined = JSON.parse(JSON.stringify(data.ingredients));
    let combined = JSON.parse(JSON.stringify(data.ingredients));

    function branchWeight(branch) {
        return branch === 'all' ? 1 : (data.branchWeights[branch] || 0);
    }

    function stockForBranch(branch) {
        const w = branchWeight(branch);
        return combined.map((i) => ({ ...i, stock: i.stock * w, used_today: i.used_today * w }));
    }

    function byKeyOf(list) { return Object.fromEntries(list.map((i) => [i.key, i])); }

    /* Usage-rate multiplier + a little stable variance so Month/Year don't just repeat Today's number. */
    function usageRate(item, mode) {
        if (mode === 'day') return item.used_today;
        const seed = seedNum(mode + item.key);
        const variance = 0.85 + seed * 0.3; // 0.85–1.15
        return item.used_today * variance;
    }

    function status(item) {
        if (item.stock <= 0) return { tone: 'out', label: 'Out of stock' };
        if (item.stock <= item.reorder) return { tone: 'low', label: 'Reorder now' };
        if (item.stock <= item.reorder * 1.25) return { tone: 'info', label: 'Getting low' };
        return { tone: 'ok', label: 'Healthy' };
    }

    function daysLeft(item, rate) {
        if (!rate) return null;
        return item.stock / rate;
    }

    function currentList() {
        return stockForBranch(document.getElementById('stockBranch').value);
    }

    function currentRateMode() {
        return document.getElementById('stockRateMode').value;
    }

    /* --- stock table --- */
    function renderTable() {
        const list = currentList();
        const mode = currentRateMode();
        const body = document.querySelector('#stockTable tbody');
        const term = (document.getElementById('stockSearch').value || '').trim().toLowerCase();

        body.innerHTML = list
            .filter((i) => !term || i.name.toLowerCase().includes(term))
            .map((i) => {
                const rate = usageRate(i, mode);
                const s = status(i);
                const d = daysLeft(i, rate);
                const days = d === null ? '—' : (d < 1 ? 'Today' : d.toFixed(1) + ' d');
                return `<tr class="${s.tone === 'low' || s.tone === 'out' ? 'row--flag' : ''}">
                    <td>${i.name}</td>
                    <td class="ta-r mono">${num(i.stock)} ${i.unit}</td>
                    <td class="ta-r mono">${num(rate)} ${i.unit}</td>
                    <td class="ta-r mono">${num(i.reorder)} ${i.unit}</td>
                    <td class="ta-r mono">${days}</td>
                    <td><span class="badge badge--${s.tone}">${s.label}</span></td>
                    <td class="ta-r mono">${pesoDec(i.stock * i.cost)}</td>
                </tr>`;
            }).join('') || '<tr><td colspan="7" class="empty-note">No ingredient by that name.</td></tr>';
    }

    /* --- alert banner: the only place low stock is called out --- */
    function renderAlert() {
        const list = currentList();
        const flagged = list.filter((i) => i.stock <= i.reorder);
        const box = document.getElementById('lowStockAlert');

        if (!flagged.length) {
            box.innerHTML = `<section class="panel panel--good"><div class="panel-body">
                <h2 class="notice-head">Stock room is complete</h2>
                <p class="notice-text">Nothing here is at its reorder point.</p>
            </div></section>`;
            return;
        }

        box.innerHTML = `<section class="panel panel--warning">
            <div class="panel-head"><h2>${flagged.length} ${flagged.length === 1 ? 'item needs' : 'items need'} reordering</h2>
            <span class="panel-note">Low stock only</span></div>
            <div class="panel-body">
                <ul class="alert-list">
                    ${flagged.map((i) => `<li>
                        <span class="alert-name">${i.name}</span>
                        <span class="alert-figure">${num(i.stock)}${i.unit} left, reorder at ${num(i.reorder)}${i.unit}</span>
                        <span class="badge badge--${status(i).tone}">${status(i).label}</span>
                    </li>`).join('')}
                </ul>
                <button type="button" class="btn btn--primary" data-open-modal="stockIn">Add stock</button>
            </div>
        </section>`;
    }

    /* --- top figures --- */
    function renderStats() {
        const list = currentList();
        const value = list.reduce((sum, i) => sum + i.stock * i.cost, 0);
        const flagged = list.filter((i) => i.stock <= i.reorder).length;

        document.getElementById('inventoryStats').innerHTML = `
            <article class="stat"><p class="stat-label">Stock value</p><p class="stat-value">${pesoDec(value)}</p><p class="stat-trend">At last purchase price</p></article>
            <article class="stat ${flagged ? 'stat--alert' : 'stat--up'}"><p class="stat-label">Below reorder point</p><p class="stat-value">${flagged}</p><p class="stat-trend">${flagged ? 'Restock before closing' : 'All good'}</p></article>
            <article class="stat"><p class="stat-label">Tracked ingredients</p><p class="stat-value">${list.length}</p><p class="stat-trend">Across the current view</p></article>
            <article class="stat"><p class="stat-label">Branch view</p><p class="stat-value stat-value--sm">${document.getElementById('stockBranch').selectedOptions[0].text}</p><p class="stat-trend">Switch it above</p></article>`;
    }

    function render() {
        renderAlert();
        renderStats();
        renderTable();
    }

    function fillStockInPicker() {
        document.getElementById('stockInItem').innerHTML = combined
            .map((i) => `<option value="${i.key}">${i.name} (${i.unit})</option>`).join('');
    }

    /* --- adding stock, to whichever branch the modal has selected --- */
    document.getElementById('stockInSave').addEventListener('click', () => {
        const branch = document.getElementById('stockInBranch').value;
        const key = document.getElementById('stockInItem').value;
        const qty = Math.max(1, parseInt(document.getElementById('stockInQty').value, 10) || 0);
        const w = branchWeight(branch);
        const item = byKeyOf(combined)[key];

        item.stock += qty / w;
        render();

        document.getElementById('stockIn').hidden = true;
        document.body.classList.remove('is-locked');
    });

    document.getElementById('stockSearch').addEventListener('input', renderTable);
    document.getElementById('stockBranch').addEventListener('change', render);
    document.getElementById('stockRateMode').addEventListener('change', render);

    fillStockInPicker();
    render();
}

/* ---------- menu: add, edit, and remove drinks and their recipes ---------- */
function initMenu() {
    const data = window.KAPE_MENU;
    if (!data) return;

    let items = JSON.parse(JSON.stringify(data.menuItems));
    const ingredientInfo = Object.fromEntries(data.ingredients.map((i) => [i.key, { name: i.name, unit: i.unit }]));

    function renderTable() {
        document.querySelector('#menuTable tbody').innerHTML = items.map((item, idx) => {
            const chips = Object.entries(item.recipe || {})
                .map(([k, v]) => `<span class="chip">${ingredientInfo[k] ? ingredientInfo[k].name : k} ${v}${ingredientInfo[k] ? ingredientInfo[k].unit : ''}</span>`)
                .join('') || '<span class="muted">No recipe set</span>';

            return `<tr>
                <td>${item.name}</td>
                <td class="ta-r mono">₱${Number(item.price).toFixed(2)}</td>
                <td class="recipe-parts">${chips}</td>
                <td class="ta-r">
                    <button type="button" class="btn btn--ghost btn--sm" data-edit="${idx}">Edit</button>
                    <button type="button" class="btn btn--ghost btn--sm" data-remove="${idx}">Remove</button>
                </td>
            </tr>`;
        }).join('') || '<tr><td colspan="4" class="empty-note">No drinks on the menu yet.</td></tr>';
    }

    function renderRecipeInputs(recipe) {
        recipe = recipe || {};
        document.getElementById('drinkRecipeFields').innerHTML = Object.entries(ingredientInfo).map(([key, ing]) => {
            const amount = recipe[key] || '';
            return `<label class="field-group recipe-field">
                <span>${ing.name} <span class="muted">(${ing.unit})</span></span>
                <input type="number" min="0" step="0.1" class="field drink-amount" data-key="${key}" value="${amount}" placeholder="0">
            </label>`;
        }).join('');
    }

    function openForm(index) {
        const editing = typeof index === 'number';
        const item = editing ? items[index] : { name: '', price: '', recipe: {} };

        document.getElementById('drinkEditingIndex').value = editing ? index : '';
        document.getElementById('drinkName').value = item.name;
        document.getElementById('drinkPrice').value = item.price;
        renderRecipeInputs(item.recipe);

        const heading = document.querySelector('#drinkForm .modal-head h2');
        if (heading) heading.textContent = editing ? `Edit ${item.name}` : 'Add new drink';

        document.getElementById('drinkForm').hidden = false;
        document.body.classList.add('is-locked');
    }

    document.getElementById('addDrinkBtn').addEventListener('click', () => openForm());

    document.getElementById('menuTable').addEventListener('click', (e) => {
        const editBtn = e.target.closest('[data-edit]');
        if (editBtn) { openForm(parseInt(editBtn.dataset.edit, 10)); return; }

        const removeBtn = e.target.closest('[data-remove]');
        if (removeBtn) {
            const idx = parseInt(removeBtn.dataset.remove, 10);
            if (confirm(`Remove ${items[idx].name} from the menu?`)) {
                items.splice(idx, 1);
                renderTable();
            }
        }
    });

    document.getElementById('drinkSaveBtn').addEventListener('click', () => {
        const name = document.getElementById('drinkName').value.trim();
        const price = parseFloat(document.getElementById('drinkPrice').value) || 0;

        if (!name) {
            document.getElementById('drinkName').focus();
            return;
        }

        const recipe = {};
        document.querySelectorAll('.drink-amount').forEach((input) => {
            const v = parseFloat(input.value);
            if (v > 0) recipe[input.dataset.key] = v;
        });

        const editingIndex = document.getElementById('drinkEditingIndex').value;
        if (editingIndex !== '') {
            items[parseInt(editingIndex, 10)] = { name, price, recipe };
        } else {
            items.push({ name, price, recipe });
        }

        renderTable();
        document.getElementById('drinkForm').hidden = true;
        document.body.classList.remove('is-locked');
    });

    renderTable();
}