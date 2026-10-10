function initMenu() {
    const data = window.KAPE_MENU;
    if (!data) return;

    let items = JSON.parse(JSON.stringify(data.menuItems || []));
    let ingredientsList = JSON.parse(JSON.stringify(data.ingredients || []));
    let typesList = JSON.parse(JSON.stringify(data.drinkTypes || []));

    const pageSize = 8;
    let currentDrinkPage = 1;
    let currentIngPage = 1;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function buildPageButtons(container, totalPages, currentPage, onPageClick) {
        if (!container) return;
        container.innerHTML = '';
        if (totalPages <= 1) return;

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
                container.appendChild(span);
            } else {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = p;
                btn.className = p === currentPage ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm';
                btn.style.cssText = 'min-width: 2rem; padding: 0.25rem 0.5rem;';
                btn.addEventListener('click', () => onPageClick(p));
                container.appendChild(btn);
            }
        });
    }

    // Tab Switchers
    const tabDrinks = document.getElementById('tabMenuDrinks');
    const tabIngredients = document.getElementById('tabMenuIngredients');
    const filterDrinks = document.getElementById('filterRowDrinks');
    const filterIngredients = document.getElementById('filterRowIngredients');
    const secDrinks = document.getElementById('sectionDrinksTab');
    const secIngredients = document.getElementById('sectionIngredientsTab');

    tabDrinks?.addEventListener('click', () => {
        tabDrinks.style.color = 'var(--primary, #a63d2a)';
        tabDrinks.style.borderBottom = '2px solid var(--primary, #a63d2a)';
        tabDrinks.style.fontWeight = '600';
        tabIngredients.style.color = 'var(--muted, #666)';
        tabIngredients.style.borderBottom = 'none';
        tabIngredients.style.fontWeight = '500';

        if (filterDrinks) filterDrinks.hidden = false;
        if (filterIngredients) filterIngredients.hidden = true;
        secDrinks.hidden = false;
        secIngredients.hidden = true;
    });

    tabIngredients?.addEventListener('click', () => {
        tabIngredients.style.color = 'var(--primary, #a63d2a)';
        tabIngredients.style.borderBottom = '2px solid var(--primary, #a63d2a)';
        tabIngredients.style.fontWeight = '600';
        tabDrinks.style.color = 'var(--muted, #666)';
        tabDrinks.style.borderBottom = 'none';
        tabDrinks.style.fontWeight = '500';

        if (filterDrinks) filterDrinks.hidden = true;
        if (filterIngredients) filterIngredients.hidden = false;
        secDrinks.hidden = true;
        secIngredients.hidden = false;
        renderIngredientDirectory();
    });

    // Master Ingredients Table Rendering
    function renderIngredientDirectory() {
        const tbody = document.querySelector('#ingDirectoryTable tbody');
        if (!tbody) return;

        const term = (document.getElementById('ingDirectorySearch')?.value || '').toLowerCase().trim();
        const statusVal = document.getElementById('ingFilterStatus')?.value || 'all';
        const unitVal = document.getElementById('ingFilterUnit')?.value || 'all';

        const filtered = ingredientsList.filter(i => {
            const matchesSearch = !term || i.name.toLowerCase().includes(term) || (i.key && i.key.toLowerCase().includes(term));
            const isItemActive = i.is_active !== false;
            const matchesStatus = statusVal === 'all' || (statusVal === 'active' && isItemActive) || (statusVal === 'inactive' && !isItemActive);
            const matchesUnit = unitVal === 'all' || i.unit === unitVal;
            return matchesSearch && matchesStatus && matchesUnit;
        });

        const totalRows = filtered.length;
        const totalPages = Math.ceil(totalRows / pageSize) || 1;
        if (currentIngPage > totalPages) currentIngPage = 1;

        const start = (currentIngPage - 1) * pageSize;
        const visibleItems = filtered.slice(start, start + pageSize);

        tbody.innerHTML = visibleItems.map((ing) => {
            const isActive = ing.is_active !== false;
            const badge = isActive ? '<span class="badge badge--ok">Active</span>' : '<span class="badge badge--muted">Disabled</span>';
            const toggleText = isActive ? 'Disable' : 'Enable';

            return `<tr style="${!isActive ? 'opacity: 0.6;' : ''}">
                <td><code style="font-size: 12px; background: rgba(0,0,0,0.04); padding: 2px 6px; border-radius: 4px;">${ing.key}</code></td>
                <td><b>${ing.name}</b></td>
                <td>${ing.unit}</td>
                <td class="ta-r mono">₱${Number(ing.cost || 0).toFixed(2)}</td>
                <td class="ta-r mono">${ing.reorder || 0} ${ing.unit}</td>
                <td>${badge}</td>
                <td class="ta-r">
                    <button type="button" class="btn btn--ghost btn--sm toggle-ing-btn" data-id="${ing.id}" data-key="${ing.key}" style="min-width: 85px;">
                        ${toggleText}
                    </button>
                </td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" class="empty-note">No raw ingredients match your filter criteria.</td></tr>';

        bindToggleIngListeners();
        renderIngPagination(totalRows, totalPages);
    }

    function bindToggleIngListeners() {
        document.querySelectorAll('.toggle-ing-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const id = e.target.getAttribute('data-id');
                const key = e.target.getAttribute('data-key');
                const targetIng = ingredientsList.find(i => String(i.id) === String(id) || i.key === key);

                if (targetIng) {
                    targetIng.is_active = !targetIng.is_active;
                    renderIngredientDirectory();

                    if (id) {
                        await fetch(`/admin/ingredients/${id}/toggle`, {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                        });
                    }
                }
            });
        });
    }

    function renderIngPagination(totalRows, totalPages) {
        const bar = document.getElementById('ingPagination');
        const info = document.getElementById('ingPageInfo');
        const numbers = document.getElementById('ingPageNumbers');
        const prev = document.getElementById('ingPrevBtn');
        const next = document.getElementById('ingNextBtn');

        if (!bar) return;
        if (totalRows <= pageSize) { bar.hidden = true; return; }

        bar.hidden = false;
        const start = (currentIngPage - 1) * pageSize;
        const end = Math.min(start + pageSize, totalRows);
        if (info) info.textContent = `Showing ${start + 1}–${end} of ${totalRows} ingredients`;

        buildPageButtons(numbers, totalPages, currentIngPage, (p) => {
            currentIngPage = p;
            renderIngredientDirectory();
        });

        if (prev) {
            prev.disabled = currentIngPage === 1;
            prev.onclick = () => { if (currentIngPage > 1) { currentIngPage--; renderIngredientDirectory(); } };
        }
        if (next) {
            next.disabled = currentIngPage === totalPages;
            next.onclick = () => { if (currentIngPage < totalPages) { currentIngPage++; renderIngredientDirectory(); } };
        }
    }

    // Save New Ingredient to DB
    document.getElementById('saveInvIngredientBtn')?.addEventListener('click', async () => {
        const nameInput = document.getElementById('invIngName');
        const name = nameInput?.value.trim();
        const baseUnit = document.getElementById('invIngBaseUnit')?.value || 'g';
        const reorder = parseFloat(document.getElementById('invIngReorder')?.value) || 0;
        const cost = parseFloat(document.getElementById('invIngCost')?.value) || 0;

        if (!name) { if (nameInput) nameInput.focus(); return; }

        try {
            const response = await fetch('/admin/ingredients/store', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ name, unit: baseUnit, reorder_level: reorder, cost })
            });

            const res = await response.json();
            if (res.success && res.ingredient) {
                ingredientsList.push(res.ingredient);
                if (nameInput) nameInput.value = '';
                document.getElementById('addIngredientModal').hidden = true;
                document.body.classList.remove('is-locked');
                renderIngredientDirectory();
                renderTable();
            }
        } catch (e) {
            console.error("Failed to save ingredient to DB", e);
        }
    });

    // Drink Table Rendering
    function renderTable() {
        const tbody = document.querySelector('#menuTable tbody');
        if (!tbody) return;

        const searchVal = (document.getElementById('menuSearch')?.value || '').toLowerCase().trim();
        const typeVal = document.getElementById('menuFilterType')?.value || 'all';
        const statusVal = document.getElementById('menuFilterStatus')?.value || 'all';

        const filteredItems = items.filter(item => {
            const matchesSearch = item.name.toLowerCase().includes(searchVal);
            const matchesType = typeVal === 'all' || item.drink_type_id == typeVal || item.drink_type_name == typeVal;
            const isItemActive = item.is_active !== false;
            const matchesStatus = statusVal === 'all' || (statusVal === 'active' && isItemActive) || (statusVal === 'inactive' && !isItemActive);
            return matchesSearch && matchesType && matchesStatus;
        });

        const totalRows = filteredItems.length;
        const totalPages = Math.ceil(totalRows / pageSize) || 1;
        if (currentDrinkPage > totalPages) currentDrinkPage = 1;

        const start = (currentDrinkPage - 1) * pageSize;
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

            const statusBadge = item.is_active !== false ? `<span class="badge badge--ok">Active</span>` : `<span class="badge badge--muted">Inactive</span>`;
            const toggleLabel = item.is_active !== false ? 'Deactivate' : 'Activate';

            return `<tr style="${item.is_active === false ? 'opacity: 0.6;' : ''}">
                <td>${photo}</td>
                <td><b>${item.name}</b></td>
                <td>${item.drink_type_name || 'Standard'}</td>
                <td class="ta-r mono">₱${Number(item.price).toFixed(2)}</td>
                <td class="recipe-parts">${chips}</td>
                <td>${statusBadge}</td>
                <td class="ta-r">
                    <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 6px; white-space: nowrap;">
                        <button type="button" class="btn btn--ghost btn--sm" data-edit="${realIndex}" style="min-width: 85px;">Edit</button>
                        <button type="button" class="btn btn--ghost btn--sm" data-toggle-status="${realIndex}" style="min-width: 85px;">${toggleLabel}</button>
                    </div>
                </td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" class="empty-note">No drinks match your filter criteria.</td></tr>';

        renderPagination(totalRows, totalPages);
    }

    function renderPagination(totalRows, totalPages) {
        const bar = document.getElementById('menuPagination');
        const info = document.getElementById('menuPageInfo');
        const numbers = document.getElementById('menuPageNumbers');
        const prev = document.getElementById('menuPrevBtn');
        const next = document.getElementById('menuNextBtn');

        if (!bar) return;
        if (totalRows <= pageSize) { bar.hidden = true; return; }

        bar.hidden = false;
        const start = (currentDrinkPage - 1) * pageSize;
        const end = Math.min(start + pageSize, totalRows);
        if (info) info.textContent = `Showing ${start + 1}–${end} of ${totalRows} drinks`;

        buildPageButtons(numbers, totalPages, currentDrinkPage, (p) => {
            currentDrinkPage = p;
            renderTable();
        });

        if (prev) {
            prev.disabled = currentDrinkPage === 1;
            prev.onclick = () => { if (currentDrinkPage > 1) { currentDrinkPage--; renderTable(); } };
        }
        if (next) {
            next.disabled = currentDrinkPage === totalPages;
            next.onclick = () => { if (currentDrinkPage < totalPages) { currentDrinkPage++; renderTable(); } };
        }
    }

    // Toggle Drink active status
    document.getElementById('menuTable')?.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit]');
        if (editBtn) { openForm(parseInt(editBtn.dataset.edit, 10)); return; }

        const toggleBtn = e.target.closest('[data-toggle-status]');
        if (toggleBtn) {
            const idx = parseInt(toggleBtn.dataset.toggleStatus, 10);
            const item = items[idx];
            item.is_active = !item.is_active;
            renderTable();

            if (item.id) {
                await fetch(`/admin/drinks/${item.id}/toggle`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });
            }
        }
    });

    // Save / Update Drink Form
    document.getElementById('drinkSaveBtn')?.addEventListener('click', async () => {
        const name = document.getElementById('drinkName').value.trim();
        const price = parseFloat(document.getElementById('drinkPrice').value) || 0;
        const drinkTypeId = document.getElementById('drinkType').value;
        const editingIndex = document.getElementById('drinkEditingIndex').value;
        const itemId = editingIndex !== '' ? items[parseInt(editingIndex, 10)]?.id : null;

        if (!name) { document.getElementById('drinkName').focus(); return; }

        const recipe = {};
        document.querySelectorAll('#recipeRowsContainer .recipe-row').forEach((row) => {
            const key = row.querySelector('.recipe-ing-select')?.value;
            const amount = parseFloat(row.querySelector('.recipe-ing-amount')?.value) || 0;
            if (key && amount > 0) recipe[key] = amount;
        });

        const formData = new FormData();
        if (itemId) formData.append('id', itemId);
        formData.append('name', name);
        formData.append('price', price);
        if (drinkTypeId) formData.append('drink_type_id', drinkTypeId);

        Object.entries(recipe).forEach(([k, v]) => {
            formData.append(`recipe[${k}]`, v);
        });

        const fileInput = document.getElementById('drinkImage');
        if (fileInput?.files[0]) {
            formData.append('image', fileInput.files[0]);
        }

        try {
            const response = await fetch('/admin/drinks/store', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: formData
            });

            const res = await response.json();
            if (res.success && res.item) {
                if (editingIndex !== '') {
                    items[parseInt(editingIndex, 10)] = res.item;
                } else {
                    items.push(res.item);
                }

                renderTable();
                document.getElementById('drinkForm').hidden = true;
                document.body.classList.remove('is-locked');
            }
        } catch (err) {
            console.error("Error saving drink to database:", err);
        }
    });

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
            <select class="field recipe-ing-select" style="flex: 2; height: 38px; box-sizing: border-box; margin: 0;">
                <option value="" ${!selectedKey ? 'selected' : ''} disabled>Select ingredient</option>
                ${optionsHtml}
            </select>
            
            <div style="flex: 1; display: flex; align-items: center; gap: 6px; height: 38px;">
                <input type="number" min="0" step="0.1" class="field recipe-ing-amount" value="${amount}" placeholder="Amount" style="width: 100%; height: 38px; box-sizing: border-box; margin: 0;">
                <span class="muted recipe-ing-unit" style="font-size: 12px; min-width: 28px; line-height: 38px;">${defaultUnit}</span>
            </div>

            <button type="button" class="btn btn--ghost remove-recipe-row" style="height: 38px; width: 38px; padding: 0; display: flex; align-items: center; justify-content: center; color: var(--red, #c0392b);">✕</button>
        `;

        row.querySelector('.recipe-ing-select').addEventListener('change', (e) => {
            const selectedOpt = e.target.options[e.target.selectedIndex];
            const unit = selectedOpt.getAttribute('data-unit') || '';
            row.querySelector('.recipe-ing-unit').textContent = unit;
        });

        row.querySelector('.remove-recipe-row').addEventListener('click', () => row.remove());
        container.appendChild(row);
    }

    function openForm(index) {
        const editing = typeof index === 'number';
        const item = editing ? items[index] : { name: '', price: '', recipe: {}, drink_type_id: '' };

        document.getElementById('drinkEditingIndex').value = editing ? index : '';
        document.getElementById('drinkName').value = item.name;
        document.getElementById('drinkPrice').value = item.price;
        document.getElementById('drinkType').value = item.drink_type_id || '';

        renderRecipeInputs(item.recipe);

        const heading = document.querySelector('#drinkForm .modal-head h2');
        if (heading) heading.textContent = editing ? `Edit ${item.name}` : 'Add new drink';

        document.getElementById('drinkForm').hidden = false;
        document.body.classList.add('is-locked');
    }

    document.getElementById('addDrinkBtn')?.addEventListener('click', () => openForm());
    document.getElementById('addRecipeRowBtn')?.addEventListener('click', () => addRecipeRow());

    ['menuSearch', 'menuFilterType', 'menuFilterStatus'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => { currentDrinkPage = 1; renderTable(); });
        document.getElementById(id)?.addEventListener('change', () => { currentDrinkPage = 1; renderTable(); });
    });

    renderTable();
}