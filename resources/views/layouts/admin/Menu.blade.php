<x-admin.shell title="Menu" heading="Catalog & Recipes" subheading="Manage drink offerings, drink categories, and raw ingredient catalog">

    <!-- Unified Dynamic Filter Panel -->
    <x-admin.panel title="" note="">
        <div class="tab-nav-row">
            <button type="button" class="tab-btn tab-btn--active" id="tabMenuDrinks">
                ☕ Drink Menu
            </button>
            <button type="button" class="tab-btn tab-btn--inactive" id="tabMenuIngredients">
                📦 Master Ingredient Directory
            </button>
        </div>

        <!-- Filter Controls: Drink Menu -->
        <div id="filterRowDrinks" class="filter-grid-row">
            <label class="field-group fg-search">
                <span>Search Drink</span>
                <input type="search" class="field" id="menuSearch" placeholder="🔍 Type a drink name...">
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
                <input type="search" class="field" id="ingDirectorySearch" placeholder="🔍 Search by ingredient name or key...">
            </label>

            <label class="field-group fg-status-sm">
                <span>Status</span>
                <select class="field" id="ingFilterStatus">
                    <option value="all">All Statuses</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Disabled Only</option>
                </select>
            </label>

            <label class="field-group fg-unit-sm">
                <span>Base Unit</span>
                <select class="field" id="ingFilterUnit">
                    <option value="all">All Units</option>
                    <option value="ml">ml</option>
                    <option value="g">g</option>
                    <option value="pc">pc</option>
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
        <form id="drinkFormElement" enctype="multipart/form-data">
            <input type="hidden" id="drinkEditingIndex" value="">

            <div class="field-horizontal form-row-spacing alignment-start">
                <label class="field-group fg-branch">
                    <span>Drink name</span>
                    <input type="text" class="field" id="drinkName" placeholder="e.g. Brown Sugar Latte">
                </label>

                <label class="field-group fg-auto">
                    <span>Photo</span>
                    <div class="photo-upload-wrapper">
                        <input type="file" id="drinkImage" accept="image/*" class="is-hidden" onchange="document.getElementById('fileNameText').textContent = this.files[0]?.name || 'No file chosen'">
                        <button type="button" class="btn btn--ghost photo-upload-btn" onclick="document.getElementById('drinkImage').click()">
                            📷 Choose photo
                        </button>
                    </div>
                    <span id="fileNameText" class="muted file-name-truncated">No file chosen</span>
                </label>
            </div>

            <div class="field-horizontal">
                <label class="field-group field-group--grow">
                    <span>Drink type</span>
                    <select class="field" id="drinkType">
                        <option value="">Select a type</option>
                        @foreach ($drinkTypes as $type)
                            <option value="{{ $type['id'] }}">{{ $type['name'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group field-group--narrow">
                    <span>Price (₱)</span>
                    <input type="number" class="field" id="drinkPrice" min="0" step="0.01" placeholder="150">
                </label>
            </div>

            <h3 class="sub-head subhead-spaced">Recipe — ingredient usage per serving</h3>

            <div id="recipeRowsContainer" class="dynamic-rows-container"></div>

            <button type="button" class="btn btn--ghost btn--sm" id="addRecipeRowBtn">
                + Add Ingredient to Recipe
            </button>
        </form>

        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="drinkForm">Cancel</button>
            <button type="button" class="btn btn--primary" id="drinkSaveBtn">Save drink</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 2: Register Raw Ingredient -->
    <x-admin.modal id="addIngredientModal" title="Register New Raw Ingredient" size="md">
        <div class="field-horizontal form-row-spacing">
            <label class="field-group fg-search">
                <span>Ingredient Name</span>
                <input type="text" class="field" id="invIngName" placeholder="e.g. Oat Milk">
            </label>
            <label class="field-group fg-branch">
                <span>Category (Optional)</span>
                <input type="text" class="field" id="invIngCategory" placeholder="e.g. Dairy, Syrup">
            </label>
        </div>

        <div class="field-horizontal form-row-spacing">
            <label class="field-group fg-branch">
                <span>Measurement Type</span>
                <select class="field" id="invIngUnitType">
                    <option value="volume">Liquid / Volume (ml)</option>
                    <option value="weight">Solid / Weight (g)</option>
                    <option value="count">Item Count / Pieces (pc)</option>
                </select>
            </label>

            <label class="field-group fg-branch">
                <span>Base Unit</span>
                <select class="field" id="invIngBaseUnit">
                    <option value="ml">Milliliters (ml)</option>
                    <option value="g">Grams (g)</option>
                    <option value="pc">Pieces (pc)</option>
                </select>
            </label>
        </div>

        <div class="field-horizontal">
            <label class="field-group fg-branch">
                <span>Reorder Threshold (Base Unit)</span>
                <input type="number" step="any" class="field" id="invIngReorder" placeholder="e.g. 1000">
            </label>
            
            <label class="field-group fg-branch">
                <span>Cost per Base Unit (₱)</span>
                <input type="number" step="any" class="field" id="invIngCost" placeholder="e.g. 0.15">
            </label>
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="addIngredientModal">Cancel</button>
            <button type="button" class="btn btn--primary" id="saveInvIngredientBtn">Save Ingredient</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 3: Configure Drink Type Category Template -->
    <x-admin.modal id="drinkTypeForm" title="Configure Drink Type Category" size="md">
        <div class="field-group">
            <span>Category / Type Name</span>
            <input type="text" class="field" id="newTypeName" placeholder="e.g. Iced Coffee, Flavored Soda">
        </div>

        <div id="singlePriceWrapper" class="field-group">
            <span>Price (₱)</span>
            <input type="number" step="0.01" class="field" id="typeSinglePrice" placeholder="e.g. 50">
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="drinkTypeForm">Cancel</button>
            <button type="button" class="btn btn--primary" id="saveDrinkTypeBtn">Save Category Template</button>
        </x-slot:footer>
    </x-admin.modal>

    @push('scripts')
        <script src="{{ asset('js/Admin2.js') }}"></script>
        <script>
            window.KAPE_MENU = {
                menuItems:   @json($menuItems),
                ingredients: @json($ingredients),
                drinkTypes:  @json($drinkTypes),
            };
            initMenu();
        </script>
    @endpush

</x-admin.shell>