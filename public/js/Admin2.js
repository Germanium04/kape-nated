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

    /* =========================================================================
       0. UTILITY NOTIFICATIONS & MODAL DIALOGS
       ========================================================================= */
    function showSuccessModal(messageText, titleText = "Success!", modalId = "successSaveDrinkModal") {
        const modal = document.getElementById(modalId);
        const titleEl = modal?.querySelector('.modal-head h2') || document.getElementById('successModalTitle');
        const textEl = document.getElementById('successModalText') || document.getElementById('successTypeModalText');

        if (titleEl) titleEl.textContent = titleText;
        if (textEl) textEl.textContent = messageText;

        if (modal) {
            modal.hidden = false;
            document.body.classList.add('is-locked');
        }
    }

    function showErrorModal(messageText, titleText = "Transaction Failed!") {
        const modal = document.getElementById('errorModal');
        const titleEl = document.getElementById('errorModalTitle');
        const textEl = document.getElementById('errorModalText');

        if (titleEl) titleEl.textContent = titleText;
        if (textEl) textEl.textContent = messageText;

        if (modal) {
            modal.hidden = false;
            document.body.classList.add('is-locked');
        }
    }

    function showFormNotice(message, isError = true, noticeId = 'drinkFormNotice') {
        const notice = document.getElementById(noticeId);
        if (!notice) return;

        notice.textContent = message;
        notice.style.background = isError ? 'var(--flag-pale)' : 'var(--good-pale)';
        notice.style.color = isError ? 'var(--flag-dark)' : 'var(--good-dark)';
        notice.style.border = isError ? '1px solid var(--flag)' : '1px solid var(--good)';
        notice.style.display = 'block';
    }

    function clearFormNotice(noticeId = 'drinkFormNotice') {
        const notice = document.getElementById(noticeId);
        if (notice) notice.style.display = 'none';

        document.querySelectorAll('.field--error').forEach(el => {
            el.classList.remove('field--error');
            el.style.borderColor = '';
        });
    }

    /* =========================================================================
       1. MAIN TAB SWITCHER
       ========================================================================= */
    const tabDrinks = document.getElementById('tabMenuDrinks');
    const tabIngredients = document.getElementById('tabMenuIngredients');

    const filterRowDrinks = document.getElementById('filterRowDrinks');
    const filterRowIngredients = document.getElementById('filterRowIngredients');

    const sectionDrinksTab = document.getElementById('sectionDrinksTab');
    const sectionIngredientsTab = document.getElementById('sectionIngredientsTab');

    tabDrinks?.addEventListener('click', () => {
        tabDrinks.className = 'tab-btn tab-btn--active';
        tabIngredients.className = 'tab-btn tab-btn--inactive';

        if (filterRowDrinks) filterRowDrinks.hidden = false;
        if (filterRowIngredients) filterRowIngredients.hidden = true;

        if (sectionDrinksTab) sectionDrinksTab.hidden = false;
        if (sectionIngredientsTab) sectionIngredientsTab.hidden = true;

        renderTable();
    });

    tabIngredients?.addEventListener('click', () => {
        tabIngredients.className = 'tab-btn tab-btn--active';
        tabDrinks.className = 'tab-btn tab-btn--inactive';

        if (filterRowDrinks) filterRowDrinks.hidden = true;
        if (filterRowIngredients) filterRowIngredients.hidden = false;

        if (sectionDrinksTab) sectionDrinksTab.hidden = true;
        if (sectionIngredientsTab) sectionIngredientsTab.hidden = false;

        renderIngDirectoryTable();
    });

    /* =========================================================================
       2. MODAL OPEN / CLOSE HANDLERS
       ========================================================================= */
    document.addEventListener('click', (e) => {
        const openBtn = e.target.closest('[data-open-modal]');
        if (openBtn && openBtn.dataset.openModal) {
            const modalEl = document.getElementById(openBtn.dataset.openModal);
            if (modalEl) {
                clearFormNotice('drinkFormNotice');
                clearFormNotice('drinkTypeNotice');
                clearFormNotice('ingFormNotice');
                modalEl.hidden = false;
                document.body.classList.add('is-locked');
            }
        }

        const closeBtn = e.target.closest('[data-close-modal]');
        if (closeBtn && closeBtn.dataset.closeModal) {
            const modalEl = document.getElementById(closeBtn.dataset.closeModal);
            if (modalEl) {
                clearFormNotice('drinkFormNotice');
                clearFormNotice('drinkTypeNotice');
                clearFormNotice('ingFormNotice');
                modalEl.hidden = true;
                if (!document.querySelector('.modal:not([hidden])')) {
                    document.body.classList.remove('is-locked');
                }
            }
        }
    });

    /* =========================================================================
       3. DRINK TYPE MODAL & PRICING
       ========================================================================= */
    const typePriceMode = document.getElementById('typePriceMode');
    const typeSingleWrap = document.getElementById('typeSingleWrap');
    const typeSizesWrap = document.getElementById('typeSizesWrap');
    const typeWrapGrande = document.getElementById('typeWrapGrande');
    const typeWrapVenti = document.getElementById('typeWrapVenti');

    typePriceMode?.addEventListener('change', (e) => {
        const mode = e.target.value;
        if (typeSingleWrap) typeSingleWrap.hidden = mode !== 'single';
        if (typeSizesWrap) typeSizesWrap.hidden = mode === 'single';

        if (mode === 'grande' || mode === 'venti') {
            typeSizesWrap?.style.setProperty('grid-template-columns', '1fr');
        } else {
            typeSizesWrap?.style.setProperty('grid-template-columns', '1fr 1fr');
        }

        if (typeWrapGrande) typeWrapGrande.hidden = (mode !== 'grande' && mode !== 'sizes');
        if (typeWrapVenti) typeWrapVenti.hidden = (mode !== 'venti' && mode !== 'sizes');
    });

    const btnAddAddon = document.getElementById('btnAddCategoryAddon');
    const addonFormRow = document.getElementById('newAddonFormRow');
    const btnConfirmAddAddon = document.getElementById('btnConfirmAddAddon');

    btnAddAddon?.addEventListener('click', () => {
        if (addonFormRow) {
            const isHidden = addonFormRow.style.display === 'none' || !addonFormRow.style.display;
            addonFormRow.style.display = isHidden ? 'grid' : 'none';
            if (isHidden) {
                document.getElementById('newAddonName')?.focus();
            }
        }
    });

    btnConfirmAddAddon?.addEventListener('click', () => {
        const nameInput = document.getElementById('newAddonName');
        const priceInput = document.getElementById('newAddonPrice');
        const name = nameInput?.value.trim();
        const price = parseFloat(priceInput?.value) || 0;

        if (!name) { nameInput?.focus(); return; }

        const addonsContainer = document.getElementById('categoryAddonsList');
        if (addonsContainer) {
            const tempId = 'temp_' + Date.now();
            const label = document.createElement('label');
            label.className = 'chip';
            label.style.cssText = 'cursor: pointer; user-select: none; display: flex; align-items: center; background: var(--cream); padding: 4px 10px; border-radius: 16px; font-size: 12px;';
            label.innerHTML = `<input type="checkbox" class="type-addon-check" value="${tempId}" checked style="margin-right: 6px;"> ${name} (+₱${price.toFixed(2)})`;
            
            addonsContainer.appendChild(label);
        }

        if (nameInput) nameInput.value = '';
        if (priceInput) priceInput.value = '';
        if (addonFormRow) addonFormRow.style.display = 'none';
    });

    document.getElementById('saveDrinkTypeBtn')?.addEventListener('click', () => {
        clearFormNotice('drinkTypeNotice');

        const typeNameInput = document.getElementById('newTypeName');
        const typeName = typeNameInput?.value.trim();

        if (!typeName) {
            showFormNotice('Please enter a Category / Drink Type Name.', true, 'drinkTypeNotice');
            typeNameInput?.style.setProperty('border-color', 'var(--flag)');
            typeNameInput?.focus();
            return;
        }

        const confirmTextEl = document.getElementById('confirmTypeText');
        if (confirmTextEl) {
            confirmTextEl.textContent = `Are you sure you want to save "${typeName}" as a new drink category?`;
        }

        const confirmModal = document.getElementById('confirmSaveTypeModal');
        if (confirmModal) confirmModal.hidden = false;
    });

    document.getElementById('btnConfirmFinalTypeSave')?.addEventListener('click', async () => {
        const confirmModal = document.getElementById('confirmSaveTypeModal');
        if (confirmModal) confirmModal.hidden = true;

        const saveBtn = document.getElementById('saveDrinkTypeBtn');
        const typeNameInput = document.getElementById('newTypeName');
        const typeName = typeNameInput?.value.trim();

        const selectedAddons = [];
        document.querySelectorAll('.type-addon-check:checked').forEach(chk => {
            const val = chk.value;
            if (!val.startsWith('temp_')) {
                selectedAddons.push(val);
            }
        });

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving category...';
        }

        try {
            const response = await fetch('/admin/drink-types/store', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    name: typeName,
                    addons: selectedAddons
                })
            });

            const res = await response.json();

            if (res.success && res.drink_type) {
                typesList.push(res.drink_type);

                const filterSelect = document.getElementById('menuFilterType');
                if (filterSelect) {
                    const opt = document.createElement('option');
                    opt.value = res.drink_type.id;
                    opt.textContent = res.drink_type.name;
                    filterSelect.appendChild(opt);
                }

                const formSelect = document.getElementById('drinkType');
                if (formSelect) {
                    const opt = document.createElement('option');
                    opt.value = res.drink_type.id;
                    opt.textContent = res.drink_type.name;
                    formSelect.appendChild(opt);
                }

                if (typeNameInput) typeNameInput.value = '';

                const drinkTypeFormModal = document.getElementById('drinkTypeForm');
                if (drinkTypeFormModal) drinkTypeFormModal.hidden = true;

                const textEl = document.getElementById('successTypeModalText');
                if (textEl) textEl.textContent = `Category "${res.drink_type.name}" created successfully!`;
                
                const successModal = document.getElementById('successSaveTypeModal');
                if (successModal) {
                    successModal.hidden = false;
                    document.body.classList.add('is-locked');
                }

            } else {
                showErrorModal(res.message || 'Failed to save drink category to the database.');
            }
        } catch (err) {
            showErrorModal('Network or server error while persisting drink category.');
            console.error('Error saving drink type:', err);
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Category Template';
            }
        }
    });

    /* =========================================================================
       4. MASTER INGREDIENT DIRECTORY & MEASUREMENT TYPE FILTER
       ========================================================================= */
    const invIngUnitType = document.getElementById('invIngUnitType');
    const invIngBaseUnit = document.getElementById('invIngBaseUnit');

    const unitCategoryMap = {
        volume: ['ml', 'L', 'gal'],
        weight: ['g', 'kg', 'oz'],
        count:  ['pc', 'pack', 'box']
    };

    invIngUnitType?.addEventListener('change', (e) => {
        const type = e.target.value;
        if (!invIngBaseUnit) return;

        if (type === 'volume') {
            invIngBaseUnit.innerHTML = `
                <option value="ml">Milliliters (ml)</option>
                <option value="L">Liters (L)</option>
                <option value="gal">Gallons (gal)</option>
            `;
        } else if (type === 'weight') {
            invIngBaseUnit.innerHTML = `
                <option value="g">Grams (g)</option>
                <option value="kg">Kilograms (kg)</option>
                <option value="oz">Ounces (oz)</option>
            `;
        } else if (type === 'count') {
            invIngBaseUnit.innerHTML = `
                <option value="pc">Pieces (pc)</option>
                <option value="pack">Pack (pack)</option>
                <option value="box">Box (box)</option>
            `;
        }
    });

    function renderIngDirectoryTable() {
        const tbody = document.querySelector('#ingDirectoryTable tbody');
        if (!tbody) return;

        const searchVal = (document.getElementById('ingDirectorySearch')?.value || '').toLowerCase().trim();
        const statusVal = document.getElementById('ingFilterStatus')?.value || 'all';
        const unitTypeVal = document.getElementById('ingFilterUnitType')?.value || 'all';

        const filteredIngredients = ingredientsList.filter(ing => {
            const matchesSearch = ing.name.toLowerCase().includes(searchVal) || ing.key.toLowerCase().includes(searchVal);
            const isIngActive = ing.is_active !== false;
            const matchesStatus = statusVal === 'all' || (statusVal === 'active' && isIngActive) || (statusVal === 'inactive' && !isIngActive);
            
            let matchesUnitType = true;
            if (unitTypeVal !== 'all' && unitCategoryMap[unitTypeVal]) {
                matchesUnitType = unitCategoryMap[unitTypeVal].includes(ing.unit);
            }

            return matchesSearch && matchesStatus && matchesUnitType;
        });

        const totalRows = filteredIngredients.length;
        const totalPages = Math.ceil(totalRows / pageSize) || 1;
        if (currentIngPage > totalPages) currentIngPage = 1;

        const start = (currentIngPage - 1) * pageSize;
        const visibleIngs = filteredIngredients.slice(start, start + pageSize);

        tbody.innerHTML = visibleIngs.map((ing) => {
            const realIndex = ingredientsList.indexOf(ing);
            const statusBadge = ing.is_active !== false ? `<span class="badge badge--ok">Active</span>` : `<span class="badge badge--muted">Disabled</span>`;
            const toggleLabel = ing.is_active !== false ? 'Disable' : 'Enable';

            return `<tr style="${ing.is_active === false ? 'opacity: 0.6;' : ''}">
                <td><code>${ing.key}</code></td>
                <td><b>${ing.name}</b></td>
                <td><span class="chip">${ing.unit}</span></td>
                <td class="ta-r mono">₱${Number(ing.cost || 0).toFixed(2)}</td>
                <td class="ta-r mono">${Number(ing.reorder || 0)} ${ing.unit}</td>
                <td>${statusBadge}</td>
                <td class="ta-r">
                    <button type="button" class="btn btn--ghost btn--sm" data-toggle-ing="${realIndex}">${toggleLabel}</button>
                </td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" class="empty-note">No raw ingredients match your filter criteria.</td></tr>';

        renderIngPagination(totalRows, totalPages);
    }

    // Toggle Ingredient Action (Disable / Enable)
    document.querySelector('#ingDirectoryTable tbody')?.addEventListener('click', async (e) => {
        const toggleBtn = e.target.closest('[data-toggle-ing]');
        if (!toggleBtn) return;

        const realIndex = parseInt(toggleBtn.dataset.toggleIng, 10);
        const ing = ingredientsList[realIndex];
        if (!ing) return;

        toggleBtn.disabled = true;

        try {
            const response = await fetch(`/admin/ingredients/${ing.id}/toggle`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const res = await response.json();
            if (res.success) {
                ing.is_active = res.is_active;
                renderIngDirectoryTable();
                renderTable(); // Re-render drink table so ingredient cascade updates instantly
            } else {
                showErrorModal(res.message || 'Failed to toggle ingredient status.');
            }
        } catch (err) {
            showErrorModal('Network error while toggling ingredient status.');
            console.error('Error toggling ingredient:', err);
        } finally {
            toggleBtn.disabled = false;
        }
    });

    ['ingDirectorySearch', 'ingFilterStatus', 'ingFilterUnitType'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => { currentIngPage = 1; renderIngDirectoryTable(); });
        document.getElementById(id)?.addEventListener('change', () => { currentIngPage = 1; renderIngDirectoryTable(); });
    });

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
        if (info) info.textContent = `Showing ${start + 1}–${end} of ${totalRows} raw ingredients`;

        buildPageButtons(numbers, totalPages, currentIngPage, (p) => {
            currentIngPage = p;
            renderIngDirectoryTable();
        });

        if (prev) {
            prev.disabled = currentIngPage === 1;
            prev.onclick = () => { if (currentIngPage > 1) { currentIngPage--; renderIngDirectoryTable(); } };
        }
        if (next) {
            next.disabled = currentIngPage === totalPages;
            next.onclick = () => { if (currentIngPage < totalPages) { currentIngPage++; renderIngDirectoryTable(); } };
        }
    }

    document.getElementById('saveInvIngredientBtn')?.addEventListener('click', () => {
        clearFormNotice('ingFormNotice');

        const nameInput = document.getElementById('invIngName');
        const name = nameInput?.value.trim();

        if (!name) {
            showFormNotice('Please enter an ingredient name.', true, 'ingFormNotice');
            nameInput?.focus();
            return;
        }

        const confirmTextEl = document.getElementById('confirmIngText');
        if (confirmTextEl) {
            confirmTextEl.textContent = `Are you sure you want to register "${name}" in the inventory?`;
        }

        const confirmModal = document.getElementById('confirmSaveIngModal');
        if (confirmModal) confirmModal.hidden = false;
    });

    document.getElementById('btnConfirmFinalIngSave')?.addEventListener('click', async () => {
        const confirmModal = document.getElementById('confirmSaveIngModal');
        if (confirmModal) confirmModal.hidden = true;

        const nameInput = document.getElementById('invIngName');
        const unitInput = document.getElementById('invIngBaseUnit');
        const reorderInput = document.getElementById('invIngReorder');
        const costInput = document.getElementById('invIngCost');

        const name = nameInput?.value.trim();
        const unit = unitInput?.value;
        const reorder = parseFloat(reorderInput?.value) || 0;
        const cost = parseFloat(costInput?.value) || 0;

        const saveBtn = document.getElementById('saveInvIngredientBtn');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const response = await fetch('/admin/ingredients/store', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ name, unit, reorder_level: reorder, cost })
            });

            const res = await response.json();

            if (res.success && res.ingredient) {
                ingredientsList.unshift(res.ingredient);
                renderIngDirectoryTable();

                const modalEl = document.getElementById('addIngredientModal');
                if (modalEl) modalEl.hidden = true;
                document.body.classList.remove('is-locked');

                if (nameInput) nameInput.value = '';
                if (reorderInput) reorderInput.value = '';
                if (costInput) costInput.value = '';

                showSuccessModal(`Ingredient "${res.ingredient.name}" registered successfully!`, "Ingredient Registered");
            } else {
                showErrorModal(res.message || 'Database transaction error registering ingredient.');
            }
        } catch (err) {
            showErrorModal('Network connection failure or server error while saving ingredient.');
            console.error('Error saving ingredient:', err);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Ingredient';
        }
    });

    /* =========================================================================
       5. DRINKS TABLE & PAGINATION (WITH CASCADING INGREDIENT STATUS)
       ========================================================================= */
function renderTable() {
        const tbody = document.querySelector('#menuTable tbody');
        if (!tbody) return;

        const searchVal = (document.getElementById('menuSearch')?.value || '').toLowerCase().trim();
        const typeVal = document.getElementById('menuFilterType')?.value || 'all';
        const statusVal = document.getElementById('menuFilterStatus')?.value || 'all';

        const ingredientMap = Object.fromEntries(ingredientsList.map((i) => [i.key, i]));

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

        tbody.innerHTML = visibleItems.map((item) => {
            const realIndex = items.indexOf(item);
            let hasDisabledIngredient = false;

            const chips = Object.entries(item.recipe || {}).map(([k, v]) => {
                const ing = ingredientMap[k];
                const isDisabled = ing && ing.is_active === false;
                if (isDisabled) hasDisabledIngredient = true;

                const chipStyle = isDisabled ? 'background: var(--flag-pale); color: var(--flag-dark); border: 1px solid var(--flag);' : '';
                const labelText = ing ? ing.name : k;
                const unitText = ing ? ing.unit : '';

                return `<span class="chip" style="${chipStyle}">${labelText} ${v}${unitText}${isDisabled ? ' (Disabled)' : ''}</span>`;
            }).join('') || '<span class="muted">No recipe set</span>';

            const photo = item.image_path 
                ? `<img src="${item.image_path}" alt="${item.name}" style="width:36px; height:36px; border-radius:6px; object-fit:cover;">`
                : `<div style="width:36px; height:36px; border-radius:6px; background:var(--tan-pale); display:flex; align-items:center; justify-content:center; font-size:12px; color:var(--ink-soft);">☕</div>`;

            let statusBadge = item.is_active !== false ? `<span class="badge badge--ok">Active</span>` : `<span class="badge badge--muted">Inactive</span>`;
            
            // Clean single-line badge when an ingredient is disabled
            if (hasDisabledIngredient && item.is_active !== false) {
                statusBadge = `<span class="badge badge--out" style="white-space: nowrap; padding: 4px 10px; display: inline-block;">Unavailable</span>`;
            }

            const toggleLabel = item.is_active !== false ? 'Deactivate' : 'Activate';

            return `<tr style="${item.is_active === false || hasDisabledIngredient ? 'opacity: 0.75;' : ''}">
                <td>${photo}</td>
                <td><b>${item.name}</b></td>
                <td>${item.drink_type_name || 'General'}</td>
                <td class="ta-r mono">₱${Number(item.price).toFixed(2)}</td>
                <td class="recipe-parts">${chips}</td>
                <td style="white-space: nowrap;">${statusBadge}</td>
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

    document.querySelector('#menuTable tbody')?.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit]');
        if (editBtn) {
            const index = parseInt(editBtn.dataset.edit, 10);
            openForm(index);
            return;
        }

        const toggleBtn = e.target.closest('[data-toggle-status]');
        if (toggleBtn) {
            const index = parseInt(toggleBtn.dataset.toggleStatus, 10);
            const item = items[index];
            if (!item) return;

            toggleBtn.disabled = true;

            try {
                const response = await fetch(`/admin/drinks/${item.id}/toggle`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                const res = await response.json();
                if (res.success) {
                    item.is_active = res.is_active;
                    renderTable();
                } else {
                    showErrorModal(res.message || 'Failed to toggle drink status.');
                }
            } catch (err) {
                showErrorModal('Network error while toggling drink status.');
                console.error('Error toggling drink status:', err);
            } finally {
                toggleBtn.disabled = false;
            }
        }
    });

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
                span.style.cssText = 'padding: 0 0.25rem; align-self: center; color: var(--ink-soft); font-size: 0.85rem;';
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

    /* =========================================================================
       6. DRINK FORM RECIPES & PERSISTENCE
       ========================================================================= */
    const drinkImageInput = document.getElementById('drinkImage');
    const btnTriggerPhoto = document.getElementById('btnTriggerPhoto');
    const photoFileName = document.getElementById('photoFileName');

    btnTriggerPhoto?.addEventListener('click', () => drinkImageInput?.click());

    drinkImageInput?.addEventListener('change', (e) => {
        if (e.target.files && e.target.files[0]) {
            if (photoFileName) {
                photoFileName.textContent = e.target.files[0].name;
                photoFileName.hidden = false;
            }
        } else if (photoFileName) {
            photoFileName.hidden = true;
        }
    });

    const drinkTypeSelect = document.getElementById('drinkType');
    const typeSelectWrapper = document.getElementById('typeSelectWrapper');
    const chkCustomSoloDrink = document.getElementById('chkCustomSoloDrink');
    const customPricingContainer = document.getElementById('customPricingContainer');
    const drinkPricingMode = document.getElementById('drinkPricingMode');

    const wrapSinglePrice = document.getElementById('wrapSinglePrice');
    const wrapDualPrices = document.getElementById('wrapDualPrices');
    const wrapGrandePrice = document.getElementById('wrapGrandePrice');
    const wrapVentiPrice = document.getElementById('wrapVentiPrice');

    chkCustomSoloDrink?.addEventListener('change', (e) => {
        const isCustom = e.target.checked;
        clearFormNotice('drinkFormNotice');

        if (typeSelectWrapper) typeSelectWrapper.hidden = isCustom;
        if (drinkTypeSelect && isCustom) drinkTypeSelect.value = '';

        if (customPricingContainer) customPricingContainer.hidden = !isCustom;
    });

    drinkPricingMode?.addEventListener('change', (e) => {
        const mode = e.target.value;
        clearFormNotice('drinkFormNotice');

        if (wrapSinglePrice) wrapSinglePrice.hidden = mode !== 'single';
        if (wrapDualPrices) wrapDualPrices.hidden = mode === 'single';

        if (mode === 'grande' || mode === 'venti') {
            wrapDualPrices?.style.setProperty('grid-template-columns', '1fr');
        } else {
            wrapDualPrices?.style.setProperty('grid-template-columns', '1fr 1fr');
        }

        if (wrapGrandePrice) wrapGrandePrice.hidden = (mode !== 'grande' && mode !== 'both');
        if (wrapVentiPrice) wrapVentiPrice.hidden = (mode !== 'venti' && mode !== 'both');
    });

    function validateRecipeRows() {
        const rows = document.querySelectorAll('#recipeRowsContainer .recipe-row');
        const selectedKeys = new Set();

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const selectEl = row.querySelector('.recipe-ing-select');
            const amountEl = row.querySelector('.recipe-ing-amount');
            const key = selectEl.value;

            if (!key) {
                showFormNotice(`Please select an ingredient for recipe row #${i + 1}.`, true, 'drinkFormNotice');
                selectEl.style.borderColor = 'var(--flag)';
                selectEl.focus();
                return false;
            }

            if (selectedKeys.has(key)) {
                const ingName = selectEl.options[selectEl.selectedIndex]?.text || key;
                showFormNotice(`"${ingName}" is already added to the recipe. Duplicate ingredients are not allowed.`, true, 'drinkFormNotice');
                selectEl.style.borderColor = 'var(--flag)';
                selectEl.focus();
                return false;
            }
            selectedKeys.add(key);

            const val = parseFloat(amountEl.value);
            if (isNaN(val) || val <= 0) {
                showFormNotice(`Please input a valid positive amount for recipe row #${i + 1}.`, true, 'drinkFormNotice');
                amountEl.style.borderColor = 'var(--flag)';
                amountEl.focus();
                return false;
            }
        }
        return true;
    }

    function addRecipeRow(selectedKey = '', amount = '', unitVal = '') {
        const container = document.getElementById('recipeRowsContainer');
        if (!container) return;

        const row = document.createElement('div');
        row.className = 'recipe-row';
        row.style.cssText = 'display: flex; gap: 6px; align-items: center; margin-bottom: 6px;';

        // Filter out disabled ingredients from dropdown choices
        const activeIngredients = ingredientsList.filter(ing => ing.is_active !== false || ing.key === selectedKey);

        const optionsHtml = activeIngredients.map((ing) => `
            <option value="${ing.key}" data-unit="${ing.unit}" ${ing.key === selectedKey ? 'selected' : ''}>
                ${ing.name}${ing.is_active === false ? ' (Disabled)' : ''}
            </option>
        `).join('');

        const currentUnit = unitVal || (selectedKey ? (ingredientsList.find(i => i.key === selectedKey)?.unit || 'g') : 'g');

        row.innerHTML = `
            <select class="field recipe-ing-select" style="flex: 2.5; height: 36px; padding: 2px 6px; font-size: 12px; box-sizing: border-box; margin: 0;">
                <option value="" ${!selectedKey ? 'selected' : ''} disabled>Select ingredient</option>
                ${optionsHtml}
            </select>
            
            <input type="number" min="0" step="0.1" class="field recipe-ing-amount" value="${amount}" placeholder="Amount" style="flex: 1; height: 36px; padding: 2px 6px; font-size: 12px; box-sizing: border-box; margin: 0;">
            
            <select class="field recipe-ing-unit" style="width: 52px; height: 36px; padding: 2px 2px; font-size: 11px; box-sizing: border-box; margin: 0;">
                <option value="g" ${currentUnit === 'g' ? 'selected' : ''}>g</option>
                <option value="ml" ${currentUnit === 'ml' ? 'selected' : ''}>ml</option>
                <option value="pc" ${currentUnit === 'pc' ? 'selected' : ''}>pc</option>
            </select>

            <button type="button" class="btn btn--ghost remove-recipe-row" style="height: 36px; width: 36px; padding: 0; display: flex; align-items: center; justify-content: center; color: var(--flag);">✕</button>
        `;

        const selectEl = row.querySelector('.recipe-ing-select');
        const unitSelectEl = row.querySelector('.recipe-ing-unit');

        selectEl.addEventListener('change', (e) => {
            clearFormNotice('drinkFormNotice');
            const chosenKey = e.target.value;
            
            const matchedIng = ingredientsList.find(i => i.key === chosenKey);
            if (matchedIng && unitSelectEl) {
                unitSelectEl.value = matchedIng.unit || 'g';
            }

            const allSelected = Array.from(document.querySelectorAll('.recipe-ing-select'))
                .map(s => s.value)
                .filter(Boolean);

            const duplicates = allSelected.filter((item, index) => allSelected.indexOf(item) !== index);
            if (duplicates.includes(chosenKey)) {
                const ingName = e.target.options[e.target.selectedIndex]?.text || chosenKey;
                showFormNotice(`"${ingName}" is already selected in another row.`, true, 'drinkFormNotice');
                selectEl.style.borderColor = 'var(--flag)';
            } else {
                selectEl.style.borderColor = '';
            }
        });

        row.querySelector('.remove-recipe-row').addEventListener('click', () => {
            row.remove();
            clearFormNotice('drinkFormNotice');
        });

        container.appendChild(row);
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

    document.getElementById('drinkSaveBtn')?.addEventListener('click', () => {
        clearFormNotice('drinkFormNotice');

        const nameInput = document.getElementById('drinkName');
        const name = nameInput?.value.trim();
        if (!name) {
            showFormNotice('Please enter a drink name.', true, 'drinkFormNotice');
            nameInput?.style.setProperty('border-color', 'var(--flag)');
            nameInput?.focus();
            return;
        }

        const isCustom = chkCustomSoloDrink?.checked;
        const drinkTypeId = drinkTypeSelect?.value;

        if (!isCustom && !drinkTypeId) {
            showFormNotice('Please select a Drink Type or check "Custom / Solo Drink".', true, 'drinkFormNotice');
            drinkTypeSelect?.style.setProperty('border-color', 'var(--flag)');
            drinkTypeSelect?.focus();
            return;
        }

        const mode = drinkPricingMode?.value || 'single';

        if (isCustom) {
            if (mode === 'single') {
                const pEl = document.getElementById('drinkPrice');
                const price = parseFloat(pEl?.value);
                if (isNaN(price) || price <= 0) {
                    showFormNotice('Please enter a valid base price.', true, 'drinkFormNotice');
                    pEl?.style.setProperty('border-color', 'var(--flag)');
                    pEl?.focus();
                    return;
                }
            } else if (mode === 'grande' || mode === 'both') {
                const gEl = document.getElementById('drinkPriceGrande');
                const price = parseFloat(gEl?.value);
                if (isNaN(price) || price <= 0) {
                    showFormNotice('Please enter a valid Grande price.', true, 'drinkFormNotice');
                    gEl?.style.setProperty('border-color', 'var(--flag)');
                    gEl?.focus();
                    return;
                }
            }

            if (mode === 'venti' || mode === 'both') {
                const vEl = document.getElementById('drinkPriceVenti');
                const vPrice = parseFloat(vEl?.value);
                if (isNaN(vPrice) || vPrice <= 0) {
                    showFormNotice('Please enter a valid Venti price.', true, 'drinkFormNotice');
                    vEl?.style.setProperty('border-color', 'var(--flag)');
                    vEl?.focus();
                    return;
                }
            }
        }

        if (!validateRecipeRows()) return;

        const confirmTextEl = document.getElementById('confirmDrinkText');
        if (confirmTextEl) {
            confirmTextEl.textContent = `Are you sure you want to save "${name}" to the menu catalog?`;
        }

        const confirmModal = document.getElementById('confirmSaveDrinkModal');
        if (confirmModal) confirmModal.hidden = false;
    });

    document.getElementById('btnConfirmFinalSave')?.addEventListener('click', async () => {
        const confirmModal = document.getElementById('confirmSaveDrinkModal');
        if (confirmModal) confirmModal.hidden = true;

        const saveBtn = document.getElementById('drinkSaveBtn');
        const name = document.getElementById('drinkName')?.value.trim();
        const isCustom = chkCustomSoloDrink?.checked;
        const drinkTypeId = drinkTypeSelect?.value;
        const mode = drinkPricingMode?.value || 'single';

        let price = 0;
        let priceVenti = null;

        if (isCustom) {
            if (mode === 'single') {
                price = parseFloat(document.getElementById('drinkPrice')?.value);
            } else if (mode === 'grande' || mode === 'both') {
                price = parseFloat(document.getElementById('drinkPriceGrande')?.value);
            }

            if (mode === 'venti' || mode === 'both') {
                priceVenti = parseFloat(document.getElementById('drinkPriceVenti')?.value);
                if (mode === 'venti') price = priceVenti;
            }
        }

        const recipe = {};
        document.querySelectorAll('#recipeRowsContainer .recipe-row').forEach((row) => {
            const key = row.querySelector('.recipe-ing-select')?.value;
            const amount = parseFloat(row.querySelector('.recipe-ing-amount')?.value) || 0;
            if (key && amount > 0) recipe[key] = amount;
        });

        const editingIndex = document.getElementById('drinkEditingIndex').value;
        const itemId = editingIndex !== '' ? items[parseInt(editingIndex, 10)]?.id : null;

        const formData = new FormData();
        if (itemId) formData.append('id', itemId);
        formData.append('name', name);
        formData.append('price', price);
        if (priceVenti) formData.append('price_venti', priceVenti);
        formData.append('has_sizes', (mode === 'both') ? '1' : '0');
        if (drinkTypeId) formData.append('drink_type_id', drinkTypeId);

        Object.entries(recipe).forEach(([k, v]) => {
            formData.append(`recipe[${k}]`, v);
        });

        const fileInput = document.getElementById('drinkImage');
        if (fileInput?.files[0]) {
            formData.append('image', fileInput.files[0]);
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving drink...';

        try {
            const response = await fetch('/admin/drinks/store', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: formData
            });

            const res = await response.json();
            if (res.success && res.item) {
                const isEdit = editingIndex !== '';
                if (isEdit) {
                    items[parseInt(editingIndex, 10)] = res.item;
                } else {
                    items.unshift(res.item);
                }

                currentDrinkPage = 1;
                renderTable();

                document.getElementById('drinkForm').hidden = true;
                
                const successMsg = isEdit 
                    ? `Drink "${res.item.name}" updated successfully!` 
                    : `New drink "${res.item.name}" created successfully!`;
                showSuccessModal(successMsg, isEdit ? "Drink Updated" : "Drink Created", "successSaveDrinkModal");

            } else {
                showErrorModal(res.message || 'Error processing drink save request in database.');
            }
        } catch (err) {
            showErrorModal('Network or server connection error while saving drink.');
            console.error("Error saving drink:", err);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save drink';
        }
    });

    function openForm(index) {
        clearFormNotice('drinkFormNotice');
        const editing = typeof index === 'number';
        const item = editing ? items[index] : { name: '', price: '', price_venti: '', has_sizes: false, recipe: {}, drink_type_id: '' };

        document.getElementById('drinkEditingIndex').value = editing ? index : '';
        document.getElementById('drinkName').value = item.name;
        document.getElementById('drinkPrice').value = item.price || '';
        document.getElementById('drinkPriceGrande').value = item.price || '';
        document.getElementById('drinkPriceVenti').value = item.price_venti || '';
        document.getElementById('drinkType').value = item.drink_type_id || '';

        if (photoFileName) {
            if (item.image_path) {
                const name = item.image_path.split('/').pop();
                photoFileName.textContent = `Current: ${name}`;
                photoFileName.hidden = false;
            } else {
                photoFileName.hidden = true;
            }
        }

        if (chkCustomSoloDrink) {
            chkCustomSoloDrink.checked = !item.drink_type_id;
            chkCustomSoloDrink.dispatchEvent(new Event('change'));
        }

        if (item.has_sizes && drinkPricingMode) {
            drinkPricingMode.value = 'both';
            drinkPricingMode.dispatchEvent(new Event('change'));
        } else if (drinkPricingMode) {
            drinkPricingMode.value = 'single';
            drinkPricingMode.dispatchEvent(new Event('change'));
        }

        renderRecipeInputs(item.recipe);

        const heading = document.querySelector('#drinkForm .modal-head h2');
        if (heading) heading.textContent = editing ? `Edit ${item.name}` : 'Add new drink';

        document.getElementById('drinkForm').hidden = false;
        document.body.classList.add('is-locked');
    }

    /* =========================================================================
       7. INITIALIZATION & SEARCH FILTERS
       ========================================================================= */
    document.getElementById('addDrinkBtn')?.addEventListener('click', () => openForm());
    
    document.getElementById('addRecipeRowBtn')?.addEventListener('click', () => {
        clearFormNotice('drinkFormNotice');
        if (validateRecipeRows()) {
            addRecipeRow();
        }
    });

    ['menuSearch', 'menuFilterType', 'menuFilterStatus'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => { currentDrinkPage = 1; renderTable(); });
        document.getElementById(id)?.addEventListener('change', () => { currentDrinkPage = 1; renderTable(); });
    });

    ['ingDirectorySearch', 'ingFilterStatus', 'ingFilterUnitType'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => { currentIngPage = 1; renderIngDirectoryTable(); });
        document.getElementById(id)?.addEventListener('change', () => { currentIngPage = 1; renderIngDirectoryTable(); });
    });

    renderTable();
}