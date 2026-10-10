function initMenu() {
    const data = window.KAPE_MENU;
    if (!data) return;

    let items = JSON.parse(JSON.stringify(data.menuItems));
    let ingredientsList = JSON.parse(JSON.stringify(data.ingredients));
    let typesList = JSON.parse(JSON.stringify(data.drinkTypes));

    // Pagination State
    const pageSize = 8;
    let currentPage = 1;

    function renderTable() {
        const tbody = document.querySelector('#menuTable tbody');
        if (!tbody) return;

        const totalRows = items.length;
        const totalPages = Math.ceil(totalRows / pageSize) || 1;

        if (currentPage > totalPages) currentPage = 1;

        const start = (currentPage - 1) * pageSize;
        const visibleItems = items.slice(start, start + pageSize);

        const ingredientMap = Object.fromEntries(ingredientsList.map((i) => [i.key, i]));

        tbody.innerHTML = visibleItems.map((item) => {
            const realIndex = items.indexOf(item);
            const chips = Object.entries(item.recipe || {})
                .map(([k, v]) => `<span class="chip">${ingredientMap[k] ? ingredientMap[k].name : k} ${v}${ingredientMap[k] ? ingredientMap[k].unit : ''}</span>`)
                .join('') || '<span class="muted">No recipe set</span>';

            const photo = item.image_path 
                ? `<img src="${item.image_path}" alt="${item.name}" style="width:36px; height:36px; border-radius:6px; object-fit:cover;">`
                : `<div style="width:36px; height:36px; border-radius:6px; background:var(--tan-pale); display:flex; align-items:center; justify-content:center; font-size:12px; color:var(--ink-soft);">☕</div>`;

            const statusBadge = item.is_active !== false 
                ? `<span class="badge badge--ok">Active</span>`
                : `<span class="badge badge--muted">Inactive</span>`;

            const toggleLabel = item.is_active !== false ? 'Deactivate' : 'Activate';

            return `<tr style="${item.is_active === false ? 'opacity: 0.6;' : ''}">
                <td>${photo}</td>
                <td><b>${item.name}</b></td>
                <td>${item.drink_type_name || 'Standard'}</td>
                <td class="ta-r mono">₱${Number(item.price).toFixed(2)}</td>
                <td class="recipe-parts">${chips}</td>
                <td>${statusBadge}</td>
                <td class="ta-r">
                    <button type="button" class="btn btn--ghost btn--sm" data-edit="${realIndex}">Edit</button>
                    <button type="button" class="btn btn--ghost btn--sm" data-toggle-status="${realIndex}">${toggleLabel}</button>
                </td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" class="empty-note">No drinks on the menu yet.</td></tr>';

        renderPagination(totalRows, totalPages);
    }

    function renderPagination(totalRows, totalPages) {
        const bar = document.getElementById('menuPagination');
        const info = document.getElementById('menuPageInfo');
        const numbers = document.getElementById('menuPageNumbers');
        const prev = document.getElementById('menuPrevBtn');
        const next = document.getElementById('menuNextBtn');

        if (!bar) return;

        if (totalRows <= pageSize) {
            bar.hidden = true;
            return;
        }

        bar.hidden = false;
        const start = (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, totalRows);
        if (info) info.textContent = `Showing ${start + 1}–${end} of ${totalRows} drinks`;

        if (numbers) {
            numbers.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = i;
                btn.className = i === currentPage ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm';
                btn.style.padding = '3px 8px';
                btn.addEventListener('click', () => { currentPage = i; renderTable(); });
                numbers.appendChild(btn);
            }
        }

        if (prev) { prev.disabled = currentPage === 1; prev.onclick = () => { if (currentPage > 1) { currentPage--; renderTable(); } }; }
        if (next) { next.disabled = currentPage === totalPages; next.onclick = () => { if (currentPage < totalPages) { currentPage++; renderTable(); } }; }
    }

    function renderRecipeInputs(recipe) {
        const container = document.getElementById('recipeRowsContainer');
        if (!container) return;

        container.innerHTML = '';
        const entries = Object.entries(recipe || {});

        if (entries.length > 0) {
            entries.forEach(([key, amount]) => addRecipeRow(key, amount));
        } else {
            addRecipeRow();
        }
    }

    function addRecipeRow(selectedKey = '', amount = '') {
    const container = document.getElementById('recipeRowsContainer');
    if (!container) return;

    const row = document.createElement('div');
    row.className = 'recipe-row';
    // Ensure all children stretch to the exact same vertical height
    row.style.cssText = 'display: flex; gap: 8px; align-items: center; margin-bottom: 6px;';

    const optionsHtml = ingredientsList.map((ing) => `
        <option value="${ing.key}" data-unit="${ing.unit}" ${ing.key === selectedKey ? 'selected' : ''}>
            ${ing.name}
        </option>
    `).join('');

    const defaultUnit = selectedKey 
        ? (ingredientsList.find(i => i.key === selectedKey)?.unit || 'g')
        : (ingredientsList[0]?.unit || 'g');

    row.innerHTML = `
        <!-- Ingredient Dropdown -->
        <select class="field recipe-ing-select" style="flex: 2; height: 38px; box-sizing: border-box; margin: 0;">
            <option value="" ${!selectedKey ? 'selected' : ''} disabled>Select ingredient</option>
            ${optionsHtml}
        </select>
        
        <!-- Amount Input + Unit Badge Wrapper -->
        <div style="flex: 1; display: flex; align-items: center; gap: 6px; height: 38px;">
            <input type="number" min="0" step="0.1" class="field recipe-ing-amount" value="${amount}" placeholder="Amount" style="width: 100%; height: 38px; box-sizing: border-box; margin: 0;">
            <span class="muted recipe-ing-unit" style="font-size: 12px; min-width: 28px; line-height: 38px; display: inline-block;">${defaultUnit}</span>
        </div>

        <!-- Remove Button -->
        <button type="button" class="btn btn--ghost remove-recipe-row" style="height: 38px; width: 38px; padding: 0; display: flex; align-items: center; justify-content: center; color: var(--red, #c0392b); border-color: transparent; flex-shrink: 0;">
            ✕
        </button>
    `;

    row.querySelector('.recipe-ing-select').addEventListener('change', (e) => {
        const selectedOpt = e.target.options[e.target.selectedIndex];
        const unit = selectedOpt.getAttribute('data-unit') || '';
        row.querySelector('.recipe-ing-unit').textContent = unit;
    });

    row.querySelector('.remove-recipe-row').addEventListener('click', () => {
        row.remove();
    });

    container.appendChild(row);
}

    // Single listener for adding dynamic recipe rows
    document.getElementById('addRecipeRowBtn')?.addEventListener('click', () => {
        addRecipeRow();
    });

    // Dynamic Pricing Swap for Drink Type Category Modal
    document.getElementById('typeHasSizes')?.addEventListener('change', (e) => {
        const singleWrap = document.getElementById('singlePriceWrapper');
        const sizedWrap = document.getElementById('sizedPriceWrapper');
        if (!singleWrap || !sizedWrap) return;

        if (e.target.checked) {
            singleWrap.hidden = true;
            sizedWrap.hidden = false;
        } else {
            singleWrap.hidden = false;
            sizedWrap.hidden = true;
        }
    });

    // Save Drink Type Template Action
    document.getElementById('saveDrinkTypeBtn')?.addEventListener('click', () => {
        const nameInput = document.getElementById('newTypeName');
        const name = nameInput.value.trim();
        if (!name) return;

        const hasSizes = document.getElementById('typeHasSizes')?.checked || false;
        const defaultPrice = hasSizes 
            ? (parseFloat(document.getElementById('typeGrandePrice')?.value) || 0)
            : (parseFloat(document.getElementById('typeSinglePrice')?.value) || 0);
        const ventiPrice = hasSizes 
            ? (parseFloat(document.getElementById('typeVentiPrice')?.value) || 0)
            : null;

        const id = name.toLowerCase().replace(/\s+/g, '_');
        const newType = { 
            id: id, 
            name: name, 
            default_price: defaultPrice, 
            venti_price: ventiPrice,
            has_sizes: hasSizes 
        };

        typesList.push(newType);

        const typeSelect = document.getElementById('drinkType');
        const opt = document.createElement('option');
        opt.value = newType.id;
        opt.textContent = newType.name;
        opt.selected = true;
        typeSelect.appendChild(opt);

        typeSelect.dispatchEvent(new Event('change'));

        nameInput.value = '';
        document.getElementById('drinkTypeForm').hidden = true;
        document.body.classList.remove('is-locked');
    });

    // Auto-fill prices when selecting a drink type
    document.getElementById('drinkType')?.addEventListener('change', (e) => {
        const selectedTypeId = e.target.value;
        const typeData = typesList.find(t => t.id == selectedTypeId || t.name == selectedTypeId);

        if (typeData && typeData.default_price) {
            const priceField = document.getElementById('drinkPrice');
            if (priceField) priceField.value = typeData.default_price;
        }
    });

    // Show inline custom add-on row
    document.getElementById('showAddonRowBtn')?.addEventListener('click', function() {
        document.getElementById('newAddonRow').hidden = false;
        this.hidden = true;
    });

    // Register custom add-on checkbox dynamically
    document.getElementById('confirmAddonBtn')?.addEventListener('click', function() {
        const nameInput = document.getElementById('customAddonName');
        const priceInput = document.getElementById('customAddonPrice');
        
        const name = nameInput.value.trim();
        const price = parseFloat(priceInput.value) || 0;

        if (!name) {
            nameInput.focus();
            return;
        }

        const container = document.getElementById('typeAddonCheckboxes');
        const newLabel = document.createElement('label');
        newLabel.className = 'check';
        newLabel.innerHTML = `
            <input type="checkbox" name="typeAddons" value="${name}" checked>
            <span>${name} (₱${price})</span>
        `;
        container.appendChild(newLabel);

        nameInput.value = '';
        priceInput.value = '';
        document.getElementById('newAddonRow').hidden = true;
        document.getElementById('showAddonRowBtn').hidden = false;
    });

    // Table Actions & Toggle Active State
    document.getElementById('menuTable')?.addEventListener('click', (e) => {
        const editBtn = e.target.closest('[data-edit]');
        if (editBtn) {
            openForm(parseInt(editBtn.dataset.edit, 10));
            return;
        }

        const toggleBtn = e.target.closest('[data-toggle-status]');
        if (toggleBtn) {
            const idx = parseInt(toggleBtn.dataset.toggleStatus, 10);
            items[idx].is_active = items[idx].is_active === false ? true : false;
            renderTable();
        }
    });

    function openForm(index) {
        const editing = typeof index === 'number';
        const item = editing ? items[index] : { name: '', price: '', recipe: {}, drink_type_id: '' };

        document.getElementById('drinkEditingIndex').value = editing ? index : '';
        document.getElementById('drinkName').value = item.name;
        document.getElementById('drinkPrice').value = item.price;
        document.getElementById('drinkType').value = item.drink_type_id || '';
        
        const fileText = document.getElementById('fileNameText');
        if (fileText) fileText.textContent = 'No file chosen';

        renderRecipeInputs(item.recipe);

        const heading = document.querySelector('#drinkForm .modal-head h2');
        if (heading) heading.textContent = editing ? `Edit ${item.name}` : 'Add new drink';

        document.getElementById('drinkForm').hidden = false;
        document.body.classList.add('is-locked');
    }

    document.getElementById('addDrinkBtn')?.addEventListener('click', () => openForm());

    document.getElementById('drinkSaveBtn')?.addEventListener('click', () => {
        const name = document.getElementById('drinkName').value.trim();
        const price = parseFloat(document.getElementById('drinkPrice').value) || 0;
        const drinkTypeId = document.getElementById('drinkType').value;
        const typeSelect = document.getElementById('drinkType');
        const typeName = typeSelect.options[typeSelect.selectedIndex]?.text || '';

        if (!name) {
            document.getElementById('drinkName').focus();
            return;
        }

        const recipe = {};
        document.querySelectorAll('#recipeRowsContainer .recipe-row').forEach((row) => {
            const key = row.querySelector('.recipe-ing-select')?.value;
            const amount = parseFloat(row.querySelector('.recipe-ing-amount')?.value) || 0;

            if (key && amount > 0) {
                recipe[key] = amount;
            }
        });

        const editingIndex = document.getElementById('drinkEditingIndex').value;
        if (editingIndex !== '') {
            items[parseInt(editingIndex, 10)] = { 
                ...items[parseInt(editingIndex, 10)], 
                name, price, drink_type_id: drinkTypeId, drink_type_name: typeName, recipe 
            };
        } else {
            items.push({ name, price, drink_type_id: drinkTypeId, drink_type_name: typeName, is_active: true, recipe });
        }

        renderTable();
        document.getElementById('drinkForm').hidden = true;
        document.body.classList.remove('is-locked');
    });

    function renderTable() {
    const tbody = document.querySelector('#menuTable tbody');
    if (!tbody) return;

    // Get filter input values
    const searchVal = (document.getElementById('menuSearch')?.value || '').toLowerCase().trim();
    const typeVal = document.getElementById('menuFilterType')?.value || 'all';
    const statusVal = document.getElementById('menuFilterStatus')?.value || 'all';

    // Apply active filters
    const filteredItems = items.filter(item => {
        // 1. Search Filter
        const matchesSearch = item.name.toLowerCase().includes(searchVal);

        // 2. Type Filter
        const matchesType = typeVal === 'all' || 
            item.drink_type_id == typeVal || 
            item.drink_type_name == typeVal;

        // 3. Status Filter
        const isItemActive = item.is_active !== false;
        const matchesStatus = statusVal === 'all' || 
            (statusVal === 'active' && isItemActive) || 
            (statusVal === 'inactive' && !isItemActive);

        return matchesSearch && matchesType && matchesStatus;
    });

    const totalRows = filteredItems.length;
    const totalPages = Math.ceil(totalRows / pageSize) || 1;

    if (currentPage > totalPages) currentPage = 1;

    const start = (currentPage - 1) * pageSize;
    const visibleItems = filteredItems.slice(start, start + pageSize);

    const ingredientMap = Object.fromEntries(ingredientsList.map((i) => [i.key, i]));

    tbody.innerHTML = visibleItems.map((item) => {
        const realIndex = items.indexOf(item);
        const chips = Object.entries(item.recipe || {})
            .map(([k, v]) => `<span class="chip">${ingredientMap[k] ? ingredientMap[k].name : k} ${v}${ingredientMap[k] ? ingredientMap[k].unit : ''}</span>`)
            .join('') || '<span class="muted">No recipe set</span>';

        const photo = item.image_path 
            ? `<img src="${item.image_path}" alt="${item.name}" style="width:36px; height:36px; border-radius:6px; object-fit:cover;">`
            : `<div style="width:36px; height:36px; border-radius:6px; background:var(--tan-pale); display:flex; align-items:center; justify-content:center; font-size:12px; color:var(--ink-soft);">☕</div>`;

        const statusBadge = item.is_active !== false 
            ? `<span class="badge badge--ok">Active</span>`
            : `<span class="badge badge--muted">Inactive</span>`;

        const toggleLabel = item.is_active !== false ? 'Deactivate' : 'Activate';

        return `<tr style="${item.is_active === false ? 'opacity: 0.6;' : ''}">
            <td>${photo}</td>
            <td><b>${item.name}</b></td>
            <td>${item.drink_type_name || 'Standard'}</td>
            <td class="ta-r mono">₱${Number(item.price).toFixed(2)}</td>
            <td class="recipe-parts">${chips}</td>
            <td>${statusBadge}</td>
            <td class="ta-r">
                <button type="button" class="btn btn--ghost btn--sm" data-edit="${realIndex}">Edit</button>
                <button type="button" class="btn btn--ghost btn--sm" data-toggle-status="${realIndex}">${toggleLabel}</button>
            </td>
        </tr>`;
    }).join('') || '<tr><td colspan="7" class="empty-note">No drinks match your filter criteria.</td></tr>';

    renderPagination(totalRows, totalPages);
}

// Bind live filter event listeners
['menuSearch', 'menuFilterType', 'menuFilterStatus'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', () => {
        currentPage = 1; // Reset to page 1 when filtering
        renderTable();
    });
    document.getElementById(id)?.addEventListener('change', () => {
        currentPage = 1;
        renderTable();
    });
});

    renderTable();
}