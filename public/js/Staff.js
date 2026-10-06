/* kape-nated staff — Order & Inventory only. Independent from admin.js. */

/* ---------- modals: toggles the .open class on a .backdrop, not [hidden] —
   staff.css uses a different convention from admin.css on purpose. ---------- */
document.addEventListener('click', (e) => {
    const close = e.target.closest('[data-close-modal]');
    if (close) {
        document.getElementById(close.dataset.closeModal)?.classList.remove('open');
        return;
    }

    // Clicking the dark backdrop itself (not the modal card inside it) closes it too.
    if (e.target.classList.contains('backdrop')) {
        e.target.classList.remove('open');
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.backdrop.open').forEach((b) => b.classList.remove('open'));
});

/* ---------- order screen: menu grid, add-on modal, cart, submit ---------- */
function initStaffOrder() {
    const data = window.KAPE_ORDER;
    if (!data) return;

    const MENU = data.menu;
    const ADDONS = data.addons;
    const peso = (n) => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const $ = (id) => document.getElementById(id);

    let cart = [];       // {id, name, price, temp, addons:[{id,name,price}], qty}
    let picking = null;  // menu item currently open in the modal

    /* --- menu grid --- */
    const types = [...new Set(MENU.map((m) => m.type))];
    $('type').innerHTML = ['All', ...types].map((t) => `<option>${t}</option>`).join('');
    $('type').value = types.includes('Coffee') ? 'Coffee' : 'All';

    function renderGrid() {
        const t = $('type').value;
        const q = $('search').value.trim().toLowerCase();
        const list = MENU.filter((m) => (t === 'All' || m.type === t) && m.name.toLowerCase().includes(q));

        $('grid').innerHTML = list.length
            ? list.map((m) => `<button class="card" data-id="${m.id}"><span class="pic"></span><span class="label">${m.name}</span></button>`).join('')
            : '<div class="empty">No drinks match. Try another type or search.</div>';
    }

    $('type').onchange = renderGrid;
    $('search').oninput = renderGrid;

    $('grid').onclick = (e) => {
        const card = e.target.closest('.card');
        if (!card) return;
        openModal(MENU.find((m) => m.id == card.dataset.id));
    };

    /* --- add-on modal --- */
    function openModal(item) {
        picking = item;
        $('mTitle').textContent = item.name;
        $('mAddons').innerHTML = ADDONS.map((a) =>
            `<label class="opt"><input type="checkbox" value="${a.id}"> ${a.name}<span>+${peso(a.price)}</span></label>`
        ).join('');
        $('tCold').checked = true;
        $('addonModal').classList.add('open');
        $('mAdd').focus();
    }

    $('mAdd').onclick = () => {
        const temp = document.querySelector('input[name=temp]:checked').value;
        const addons = [...$('mAddons').querySelectorAll('input:checked')].map((c) => ADDONS.find((a) => a.id == c.value));
        const key = (l) => l.id + l.temp + l.addons.map((a) => a.id).sort().join(',');
        const line = { id: picking.id, name: picking.name, price: +picking.price, temp, addons, qty: 1 };
        const same = cart.find((l) => key(l) === key(line));
        same ? same.qty++ : cart.push(line);

        $('addonModal').classList.remove('open');
        renderCart();
    };

    /* --- ticket --- */
    const unit = (l) => l.price + l.addons.reduce((s, a) => s + +a.price, 0);

    function renderCart() {
        $('lines').innerHTML = cart.length
            ? cart.map((l, i) => `
                <div class="line">
                    <b>${l.name}</b><span class="amt">${peso(unit(l) * l.qty)}</span>
                    <small>${l.temp}${l.addons.length ? ' · ' + l.addons.map((a) => a.name).join(', ') : ''}</small>
                    <div class="qty">
                        <button data-a="dec" data-i="${i}" aria-label="Fewer">−</button><span>${l.qty}</span>
                        <button data-a="inc" data-i="${i}" aria-label="More">+</button>
                        <button class="rm" data-a="rm" data-i="${i}">Remove</button>
                    </div>
                </div>`).join('')
            : '<div class="hint">Tap menu item to add here</div>';

        const total = cart.reduce((s, l) => s + unit(l) * l.qty, 0);
        $('total').textContent = peso(total);
        $('complete').disabled = !cart.length;
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
                    items: cart.map((l) => ({ id: l.id, qty: l.qty, temp: l.temp, addons: l.addons.map((a) => a.id) })),
                }),
            });

            const result = await res.json();
            if (!res.ok) throw new Error(result.errors?.items?.[0] || result.message || 'Could not save the order.');

            msg.className = 'msg ok';
            msg.textContent = `Order ${result.order_no} saved · ${peso(result.total)}`;
            $('orderNo').textContent = result.next_no;
            cart = [];
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