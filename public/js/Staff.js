/* kape-nated staff — Order & Inventory only. Independent from admin.js. */

/* ---------- modals: toggles the .open class on a .backdrop, not [hidden] ---------- */
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

/* ---------- order screen: menu grid, option modal, cart, submit ---------- */
function initStaffOrder() {
    const data = window.KAPE_ORDER;
    if (!data) return;

    const MENU = data.menu;       // each: {id,name,price,type,drink_type_id,has_temperature,sizes:[{value,label,price}]}
    const ADDONS = data.addons;   // each: {id,name,price,drink_type_ids:[]}
    const $ = (id) => document.getElementById(id);
    const peso = (n) => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const short = (n) => '₱' + (Number.isInteger(+n) ? +n : (+n).toFixed(2));
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    let cart = [];       // {id, name, basePrice, size, sizeLabel, temp, addons:[{id,name,price}], qty}
    let picking = null;  // menu item currently open in the modal

    const addonsFor = (item) => ADDONS.filter((a) => !a.drink_type_ids.length || a.drink_type_ids.includes(item.drink_type_id));
    const priceText = (m) => (m.sizes && m.sizes.length ? m.sizes.map((s) => short(s.price)).join('–') : short(m.price));

    /* --- menu grid --- */
    const types = [...new Set(MENU.map((m) => m.type))];
    $('type').innerHTML = ['All', ...types].map((t) => `<option>${esc(t)}</option>`).join('');
    $('type').value = types.includes('Coffee') ? 'Coffee' : 'All';

    function renderGrid() {
        const t = $('type').value;
        const q = $('search').value.trim().toLowerCase();
        const list = MENU.filter((m) => (t === 'All' || m.type === t) && m.name.toLowerCase().includes(q));

        $('grid').innerHTML = list.length
            ? list.map((m) => `<button class="card" data-id="${m.id}"><span class="pic"><span class="price-tag">${priceText(m)}</span></span><span class="label">${esc(m.name)}</span></button>`).join('')
            : '<div class="empty">No drinks match. Try another type or search.</div>';
    }

    $('type').onchange = renderGrid;
    $('search').oninput = renderGrid;

    $('grid').onclick = (e) => {
        const card = e.target.closest('.card');
        if (!card) return;
        openModal(MENU.find((m) => m.id == card.dataset.id));
    };

    /* --- option modal --- */
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

        // Size
        $('mSizeWrapper').hidden = !sizes.length;
        $('mSizes').innerHTML = sizes.map((s, i) =>
            `<input type="radio" name="size" id="size${i}" value="${s.value}"${i === 0 ? ' checked' : ''}>` +
            `<label for="size${i}">${esc(s.label)} · ${short(s.price)}</label>`
        ).join('');

        // Temp
        $('mTempWrapper').hidden = !item.has_temperature;
        if ($('tCold'))$('tCold').checked = true;

        // Addons
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

    /* --- ticket & change calculation --- */
    const unit = (l) => l.basePrice + l.addons.reduce((s, a) => s + +a.price, 0);
    const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

    function calculateChange(total) {
        const cashArea = $('cashCalcArea');
        const tenderedInput = $('cashTendered');
        const changeLabel = $('cashChange');
        if (!cashArea || !tenderedInput || !changeLabel) return;

        const isCash = document.querySelector('input[name="pay"]:checked')?.value === 'cash';
        cashArea.style.display = isCash ? 'block' : 'none';

        if (!isCash) {
            tenderedInput.value = '';
            changeLabel.textContent = '₱0.00';
            return;
        }

        const tendered = parseFloat(tenderedInput.value) || 0;
        const change = tendered - total;

        if (tendered > 0 && change >= 0) {
            changeLabel.textContent = peso(change);
            changeLabel.style.color = '#2e7d32'; // Green
        } else if (tendered > 0 && change < 0) {
            changeLabel.textContent = 'Short cash';
            changeLabel.style.color = '#d32f2f'; // Red
        } else {
            changeLabel.textContent = '₱0.00';
            changeLabel.style.color = 'inherit';
        }
    }

    function renderCart() {
        $('lines').innerHTML = cart.length
            ? cart.map((l, i) => {
                const details = [];
                if (l.sizeLabel) details.push(esc(l.sizeLabel));
                if (l.temp) details.push(cap(l.temp));
                if (l.addons.length) details.push(l.addons.map((a) => esc(a.name)).join(', '));

                return `
                <div class="line">
                    <b>${esc(l.name)}</b><span class="amt">${peso(unit(l) * l.qty)}</span>
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
        $('total').textContent = peso(total);$('complete').disabled = !cart.length;

        calculateChange(total);
    }

    // Attach listeners for cash input and radio switches
    document.querySelectorAll('input[name="pay"]').forEach((r) => {
        r.onchange = () => {
            const total = cart.reduce((s, l) => s + unit(l) * l.qty, 0);
            calculateChange(total);
        };
    });

    if ($('cashTendered')) {$('cashTendered').oninput = () => {
            const total = cart.reduce((s, l) => s + unit(l) * l.qty, 0);
            calculateChange(total);
        };
    }

    $('lines').onclick = (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        const i = +b.dataset.i;
        if (b.dataset.a === 'inc') cart[i].qty++;
        if (b.dataset.a === 'dec') cart[i].qty > 1 ? cart[i].qty-- : cart.splice(i, 1);
        if (b.dataset.a === 'rm') cart.splice(i, 1);
        renderCart();
    };

    /* --- complete order --- */
    $('complete').onclick = async () => {
        const msg = $('msg');
        msg.className = 'msg';
        msg.textContent = '';
        $('complete').disabled = true;

        try {
            const res = await fetch(data.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({
                    payment: document.querySelector('input[name=pay]:checked').value,
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
            cart = [];
            if ($('cashTendered'))$('cashTendered').value = '';
            renderCart();
        } catch (err) {
            msg.className = 'msg err';
            msg.textContent = err.message;
            $('complete').disabled = !cart.length;
        }
    };

    renderGrid();
    renderCart();
}