<x-admin.shell title="Menu" heading="Catalog & Recipes" subheading="Manage drink offerings, drink categories, and raw ingredient catalog">

    <!-- Unified Dynamic Filter Panel -->
    <x-admin.panel title="" note="">
        <div class="tab-nav-row">
            <button type="button" class="tab-btn tab-btn--active" id="tabMenuDrinks">
                Drink Menu
            </button>
            <button type="button" class="tab-btn tab-btn--inactive" id="tabMenuIngredients">
                Master Ingredient Directory
            </button>
        </div>

        <!-- Filter Controls: Drink Menu -->
        <div id="filterRowDrinks" class="filter-grid-row">
            <label class="field-group fg-search">
                <span>Search Drink</span>
                <input type="search" class="field" id="menuSearch" placeholder="Type a drink name...">
            </label>

            <label class="field-group fg-branch">
                <span>Category / Type</span>
                <select class="field" id="menuFilterType">
                    <option value="all">All Categories</option>
                    @foreach ($drinkTypes as $type)
                        <option value="{{ $type['id'] }}">{{ $type['name'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field-group fg-status-sm">
                <span>Status</span>
                <select class="field" id="menuFilterStatus">
                    <option value="all">All Statuses</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>
            </label>
        </div>

        <!-- Filter Controls: Master Ingredients -->
        <div id="filterRowIngredients" class="filter-grid-row" hidden>
            <label class="field-group fg-search">
                <span>Search Raw Ingredients</span>
                <input type="search" class="field" id="ingDirectorySearch" placeholder="Search by ingredient name or key...">
            </label>

            <label class="field-group fg-status-sm">
                <span>Status</span>
                <select class="field" id="ingFilterStatus">
                    <option value="all">All Statuses</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Disabled Only</option>
                </select>
            </label>

            <label class="field-group fg-unit-type-sm">
                <span>Measurement Type</span>
                <select class="field" id="ingFilterUnitType">
                    <option value="all">All Measurement Types</option>
                    <option value="volume">Liquid / Volume (ml, L, gal)</option>
                    <option value="weight">Solid / Weight (g, kg, oz)</option>
                    <option value="count">Item Count / Pieces (pc, pack, box)</option>
                </select>
            </label>
        </div>
    </x-admin.panel>

    <div class="section-spacer-sm"></div>

    <!-- PANEL 1: DRINKS TABLE -->
    <div id="sectionDrinksTab">
        <x-admin.panel title="Drinks" note="{{ count($menuItems) }} items on the menu">
            <x-slot:tools>
                <button type="button" class="btn btn--ghost btn--sm" data-open-modal="drinkTypeForm">+ Drink Type</button>
                <button type="button" class="btn btn--primary btn--sm" id="addDrinkBtn">Add new drink</button>
            </x-slot:tools>

            <table class="table" id="menuTable">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Drink</th>
                        <th>Type</th>
                        <th class="ta-r">Price</th>
                        <th>Recipe</th>
                        <th>Status</th>
                        <th class="ta-r">Action</th>
                    </tr>
                </thead>
                <tbody><!-- Rendered by JS --></tbody>
            </table>

            <div id="menuPagination" class="pagination-bar pagination-wrapper" hidden>
                <span id="menuPageInfo" class="muted text-sm"></span>
                <div class="pagination-controls-group">
                    <button type="button" id="menuPrevBtn" class="btn btn--ghost btn--sm">Previous</button>
                    <div id="menuPageNumbers" class="pagination-numbers-group"></div>
                    <button type="button" id="menuNextBtn" class="btn btn--ghost btn--sm">Next</button>
                </div>
            </div>
        </x-admin.panel>
    </div>

    <!-- PANEL 2: MASTER INGREDIENT DIRECTORY TABLE -->
    <div id="sectionIngredientsTab" hidden>
        <x-admin.panel title="Master Ingredient Directory" note="Global raw catalog items monitored across branch stock rooms">
            <x-slot:tools>
                <button type="button" class="btn btn--primary btn--sm" data-open-modal="addIngredientModal">+ Register New Ingredient</button>
            </x-slot:tools>

            <table class="table" id="ingDirectoryTable">
                <thead>
                    <tr>
                        <th>Ingredient Key</th>
                        <th>Name</th>
                        <th>Base Unit</th>
                        <th class="ta-r">Default Cost (₱)</th>
                        <th class="ta-r">Reorder Threshold</th>
                        <th>Status</th>
                        <th class="ta-r">Action</th>
                    </tr>
                </thead>
                <tbody><!-- Rendered by JS --></tbody>
            </table>

            <div id="ingPagination" class="pagination-bar pagination-wrapper" hidden>
                <span id="ingPageInfo" class="muted text-sm"></span>
                <div class="pagination-controls-group">
                    <button type="button" id="ingPrevBtn" class="btn btn--ghost btn--sm">Previous</button>
                    <div id="ingPageNumbers" class="pagination-numbers-group"></div>
                    <button type="button" id="ingNextBtn" class="btn btn--ghost btn--sm">Next</button>
                </div>
            </div>
        </x-admin.panel>
    </div>

    <!-- Modal 1: Add / Edit Drink -->
    <x-admin.modal id="drinkForm" title="Add new drink" size="md">
        <div id="drinkFormNotice" style="display: none; padding: 10px 14px; border-radius: 6px; font-size: 12px; margin-bottom: 12px; font-weight: 500;"></div>

        <form id="drinkFormElement" enctype="multipart/form-data">
            <input type="hidden" id="drinkEditingIndex" value="">

            <div style="display: grid; grid-template-columns: 1fr 140px; gap: 12px; margin-bottom: 16px; align-items: stretch;">
                <label class="field-group" style="margin: 0; display: flex; flex-direction: column;">
                    <span>Drink name</span>
                    <input type="text" class="field" id="drinkName" placeholder="e.g. Brown Sugar Latte" style="margin: 0; height: 38px; box-sizing: border-box;">
                </label>

                <div class="field-group" style="margin: 0; display: flex; flex-direction: column;">
                    <span>Photo</span>
                    <input type="file" id="drinkImage" accept="image/*" hidden>
                    <button type="button" class="btn btn--ghost" id="btnTriggerPhoto" style="width: 100%; height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 12px; font-weight: 600; box-sizing: border-box; margin: 0; padding: 0 8px;">
                        📷 Add Photo
                    </button>
                    <div id="photoFileName" style="font-size: 10px; color: #777; text-align: center; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" hidden></div>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="font-size: 13px; font-weight: 600;">Drink Type</span>
                    <label style="font-size: 12px; display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none;">
                        <input type="checkbox" id="chkCustomSoloDrink"> Custom / Solo Drink
                    </label>
                </div>
                
                <div id="typeSelectWrapper">
                    <select class="field" id="drinkType">
                        <option value="">Select a type</option>
                        @foreach ($drinkTypes as $type)
                            <option value="{{ $type['id'] }}">{{ $type['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div id="customPricingContainer" hidden style="margin-bottom: 16px; background: rgba(0,0,0,0.02); padding: 12px; border-radius: 8px; border: 1px solid var(--border, #eee);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span style="font-size: 13px; font-weight: 600;">Pricing & Sizing Setup</span>
                    <select class="field" id="drinkPricingMode" style="width: 150px; height: 32px; font-size: 12px; padding: 2px 8px;">
                        <option value="single">Single Price</option>
                        <option value="grande">Grande Only</option>
                        <option value="venti">Venti Only</option>
                        <option value="both">Grande & Venti</option>
                    </select>
                </div>

                <div id="wrapSinglePrice">
                    <label class="field-group">
                        <span>Base Price (₱)</span>
                        <input type="number" class="field" id="drinkPrice" min="0" step="0.01" placeholder="e.g. 150">
                    </label>
                </div>

                <div id="wrapDualPrices" hidden style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div id="wrapGrandePrice" hidden>
                        <label class="field-group" style="margin: 0; width: 100%;">
                            <span>Grande Price (₱)</span>
                            <input type="number" class="field" id="drinkPriceGrande" min="0" step="0.01" placeholder="e.g. 140">
                        </label>
                    </div>

                    <div id="wrapVentiPrice" hidden>
                        <label class="field-group" style="margin: 0; width: 100%;">
                            <span>Venti Price (₱)</span>
                            <input type="number" class="field" id="drinkPriceVenti" min="0" step="0.01" placeholder="e.g. 160">
                        </label>
                    </div>
                </div>
            </div>

            <h3 class="sub-head subhead-spaced">Recipe — ingredient usage per serving</h3>

            <div id="recipeRowsContainer" class="dynamic-rows-container"></div>

            <button type="button" class="btn btn--ghost btn--sm" id="addRecipeRowBtn" style="margin-top: 8px;">
                + Add Ingredient to Recipe
            </button>
        </form>

        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="drinkForm">Cancel</button>
            <button type="button" class="btn btn--primary" id="drinkSaveBtn">Save drink</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 1b: Pre-Save Drink Confirmation Dialogue -->
    <x-admin.modal id="confirmSaveDrinkModal" title="Confirm Drink Save" size="sm">
        <div style="padding: 10px 0; text-align: center;">
            <div style="font-size: 32px; margin-bottom: 8px;">☕</div>
            <p style="margin: 0 0 6px; font-weight: 600; font-size: 15px;">Are you sure you want to save this drink?</p>
            <p style="margin: 0; font-size: 12.5px; color: var(--ink-soft);" id="confirmDrinkText">The menu item and its recipe settings will be updated immediately.</p>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="confirmSaveDrinkModal">Cancel</button>
            <button type="button" class="btn btn--primary" id="btnConfirmFinalSave">Okay, Save Drink</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 1c: Drink Success Dialogue Modal -->
    <x-admin.modal id="successSaveDrinkModal" title="Drink Saved" size="sm">
        <div style="padding: 14px 0; text-align: center;">
            <div style="font-size: 36px; margin-bottom: 8px;">✅</div>
            <p style="margin: 0 0 4px; font-weight: 700; font-size: 16px; color: var(--good, #46703f);" id="successModalTitle">Success!</p>
            <p style="margin: 0; font-size: 13px; color: var(--ink-soft);" id="successModalText">New drink created successfully!</p>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn--primary" data-close-modal="successSaveDrinkModal">Okay</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 2: Register Raw Ingredient -->
    <x-admin.modal id="addIngredientModal" title="Register New Raw Ingredient" size="md">
        <div id="ingFormNotice" style="display: none; padding: 10px 14px; border-radius: 6px; font-size: 12px; margin-bottom: 12px; font-weight: 500;"></div>

        <!-- Row 1: Stretched Ingredient Name & Measurement Type -->
        <div style="display: grid; grid-template-columns: 1.8fr 1fr; gap: 12px; margin-bottom: 12px;">
            <label class="field-group" style="margin: 0;">
                <span>Ingredient Name</span>
                <input type="text" class="field" id="invIngName" placeholder="e.g. Oat Milk">
            </label>
            <label class="field-group" style="margin: 0;">
                <span>Measurement Type</span>
                <select class="field" id="invIngUnitType">
                    <option value="volume">Liquid / Volume</option>
                    <option value="weight">Solid / Weight</option>
                    <option value="count">Item Count / Pieces</option>
                </select>
            </label>
        </div>

        <!-- Row 2: SWAPPED — Stretched Reorder Threshold FIRST, Compact Base Unit SECOND -->
        <div style="display: grid; grid-template-columns: 1.8fr 1fr; gap: 12px; margin-bottom: 12px;">
            <label class="field-group" style="margin: 0;">
                <span>Reorder Threshold</span>
                <input type="number" step="any" class="field" id="invIngReorder" placeholder="e.g. 1000">
            </label>

            <label class="field-group" style="margin: 0;">
                <span>Base Unit</span>
                <select class="field" id="invIngBaseUnit">
                    <option value="ml">Milliliters (ml)</option>
                    <option value="L">Liters (L)</option>
                    <option value="gal">Gallons (gal)</option>
                </select>
            </label>
        </div>

        <!-- Row 3: Full Width Cost per Base Unit -->
        <div class="field-group">
            <span>Cost per Base Unit (₱)</span>
            <input type="number" step="any" class="field" id="invIngCost" placeholder="e.g. 0.15">
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="addIngredientModal">Cancel</button>
            <button type="button" class="btn btn--primary" id="saveInvIngredientBtn">Save Ingredient</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 2b: Pre-Save Raw Ingredient Confirmation Dialogue -->
    <x-admin.modal id="confirmSaveIngModal" title="Confirm Ingredient Registration" size="sm">
        <div style="padding: 10px 0; text-align: center;">
            <div style="font-size: 32px; margin-bottom: 8px;">📦</div>
            <p style="margin: 0 0 6px; font-weight: 600; font-size: 15px;">Are you sure you want to register this ingredient?</p>
            <p style="margin: 0; font-size: 12.5px; color: var(--ink-soft);" id="confirmIngText">It will be registered across all branch stock rooms automatically.</p>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="confirmSaveIngModal">Cancel</button>
            <button type="button" class="btn btn--primary" id="btnConfirmFinalIngSave">Okay, Register Ingredient</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 3: Configure Drink Type Category Template -->
    <x-admin.modal id="drinkTypeForm" title="Configure Drink Type Category" size="md">
        <div id="drinkTypeNotice" style="display: none; padding: 10px 14px; border-radius: 6px; font-size: 12px; margin-bottom: 12px; font-weight: 500;"></div>

        <div class="field-group" style="margin-bottom: 12px;">
            <span>Category / Type Name</span>
            <input type="text" class="field" id="newTypeName" placeholder="e.g. Iced Coffee, Flavored Soda">
        </div>

        <div style="margin-bottom: 12px; background: rgba(0,0,0,0.02); padding: 12px; border-radius: 8px; border: 1px solid var(--border, #eee);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 13px; font-weight: 600;">Pricing Structure</span>
                <select class="field" id="typePriceMode" style="width: auto; height: 32px; font-size: 12px; padding: 2px 8px;">
                    <option value="single">Single Price</option>
                    <option value="grande">Grande Only</option>
                    <option value="venti">Venti Only</option>
                    <option value="sizes">Grande / Venti Sizes</option>
                </select>
            </div>

            <div id="typeSingleWrap">
                <label class="field-group">
                    <span>Default Price (₱)</span>
                    <input type="number" step="0.01" class="field" id="typeSinglePrice" placeholder="e.g. 120">
                </label>
            </div>

            <div id="typeSizesWrap" hidden style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div id="typeWrapGrande" hidden>
                    <label class="field-group" style="margin: 0; width: 100%;">
                        <span>Grande Price (₱)</span>
                        <input type="number" step="0.01" class="field" id="typeGrandePrice" placeholder="e.g. 140">
                    </label>
                </div>
                <div id="typeWrapVenti" hidden>
                    <label class="field-group" style="margin: 0; width: 100%;">
                        <span>Venti Price (₱)</span>
                        <input type="number" step="0.01" class="field" id="typeVentiPrice" placeholder="e.g. 160">
                    </label>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 12px; background: rgba(0,0,0,0.02); padding: 12px; border-radius: 8px; border: 1px solid var(--border, #eee);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 13px; font-weight: 600;">Allowed Category Add-ons</span>
                <button type="button" class="btn btn--ghost btn--sm" id="btnAddCategoryAddon">+ Create Add-on</button>
            </div>

            <div id="newAddonFormRow" style="display: none; grid-template-columns: 2fr 1fr auto; gap: 8px; margin-bottom: 10px; background: #fff; padding: 8px; border-radius: 6px; border: 1px solid var(--border, #ddd);">
                <input type="text" id="newAddonName" class="field" placeholder="Add-on Name (e.g. Espresso Shot)" style="height: 32px; font-size: 12px;">
                <input type="number" id="newAddonPrice" class="field" placeholder="Price (₱)" step="0.01" style="height: 32px; font-size: 12px;">
                <button type="button" class="btn btn--primary btn--sm" id="btnConfirmAddAddon" style="height: 32px; padding: 0 12px; font-size: 12px;">Add</button>
            </div>

            <div id="categoryAddonsList" style="display: flex; flex-wrap: wrap; gap: 6px;">
                @foreach ($addons as $addon)
                    <label class="chip" style="cursor: pointer; user-select: none; display: flex; align-items: center; background: #f0ece1; padding: 4px 10px; border-radius: 16px; font-size: 12px;">
                        <input type="checkbox" class="type-addon-check" value="{{ $addon['id'] }}" style="margin-right: 6px;"> {{ $addon['name'] }} (+₱{{ number_format($addon['price'], 2) }})
                    </label>
                @endforeach
            </div>
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="drinkTypeForm">Cancel</button>
            <button type="button" class="btn btn--primary" id="saveDrinkTypeBtn">Save Category Template</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 3b: Pre-Save Drink Type Confirmation Dialogue -->
    <x-admin.modal id="confirmSaveTypeModal" title="Confirm Category Save" size="sm">
        <div style="padding: 10px 0; text-align: center;">
            <div style="font-size: 32px; margin-bottom: 8px;">🏷️</div>
            <p style="margin: 0 0 6px; font-weight: 600; font-size: 15px;">Are you sure you want to save this category?</p>
            <p style="margin: 0; font-size: 12.5px; color: var(--ink-soft);" id="confirmTypeText">The new drink category will be added to the system catalog immediately.</p>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="confirmSaveTypeModal">Cancel</button>
            <button type="button" class="btn btn--primary" id="btnConfirmFinalTypeSave">Okay, Save Category</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 3c: Drink Type Success Dialogue Modal -->
    <x-admin.modal id="successSaveTypeModal" title="Category Saved" size="sm">
        <div style="padding: 14px 0; text-align: center;">
            <div style="font-size: 36px; margin-bottom: 8px;">🎉</div>
            <p style="margin: 0 0 4px; font-weight: 700; font-size: 16px; color: var(--good, #46703f);">Category Created!</p>
            <p style="margin: 0; font-size: 13px; color: var(--ink-soft);" id="successTypeModalText">New drink category saved successfully!</p>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn--primary" data-close-modal="successSaveTypeModal">Okay</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Error Dialogue Modal -->
    <x-admin.modal id="errorModal" title="Transaction Error" size="sm">
        <div style="padding: 14px 0; text-align: center;">
            <div style="font-size: 36px; margin-bottom: 8px;">⚠️</div>
            <p style="margin: 0 0 4px; font-weight: 700; font-size: 16px; color: #c0392b;" id="errorModalTitle">Failed!</p>
            <p style="margin: 0; font-size: 13px; color: var(--ink-soft);" id="errorModalText">An unexpected error occurred during the transaction.</p>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn--primary" data-close-modal="errorModal">Understood</button>
        </x-slot:footer>
    </x-admin.modal>

    @push('scripts')
        <script src="{{ asset('js/Admin2.js') }}"></script>
        <script>
            window.KAPE_MENU = {
                menuItems:   @json($menuItems),
                ingredients: @json($ingredients),
                drinkTypes:  @json($drinkTypes),
                addons:      @json($addons)
            };
            initMenu();
        </script>
    @endpush

</x-admin.shell>