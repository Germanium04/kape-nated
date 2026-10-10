<x-admin.shell title="Menu" heading="Drink menu" subheading="What staff can make, the price, and how much of each ingredient one cup uses">
    <x-admin.panel title="Filter Menu" note="Quickly find drinks by name, type, or availability status">
        <div class="field-horizontal" style="gap: 12px; flex-wrap: wrap;">
            <!-- Search Input -->
            <label class="field-group" style="flex: 2; min-width: 200px;">
                <span>Search Drink</span>
                <input type="search" class="field" id="menuSearch" placeholder="Type a drink name...">
            </label>

            <!-- Category Dropdown -->
            <label class="field-group" style="flex: 1; min-width: 150px;">
                <span>Category / Type</span>
                <select class="field" id="menuFilterType">
                    <option value="all">All Categories</option>
                    @foreach ($drinkTypes as $type)
                        <option value="{{ is_array($type) ? $type['id'] : $type }}">{{ is_array($type) ? $type['name'] : $type }}</option>
                    @endforeach
                </select>
            </label>

            <!-- Status Dropdown -->
            <label class="field-group" style="flex: 1; min-width: 150px;">
                <span>Status</span>
                <select class="field" id="menuFilterStatus">
                    <option value="all">All Statuses</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>
            </label>
        </div>
    </x-admin.panel>

    <x-admin.panel title="Drinks" note="{{ count($menuItems) }} on the menu">
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

        <!-- Table Pagination Bar -->
        <div id="menuPagination" class="pagination-bar" hidden style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px;">
            <span id="menuPageInfo" class="muted" style="font-size: 13px;"></span>
            <div class="pagination-controls" style="display: flex; gap: 6px; align-items: center;">
                <button type="button" id="menuPrevBtn" class="btn btn--ghost btn--sm">&laquo; Prev</button>
                <div id="menuPageNumbers" style="display: flex; gap: 4px;"></div>
                <button type="button" id="menuNextBtn" class="btn btn--ghost btn--sm">Next &raquo;</button>
            </div>
        </div>
    </x-admin.panel>

    <!-- Modal: Add / Edit Drink -->
    <x-admin.modal id="drinkForm" title="Add new drink" size="md">
        <form id="drinkFormElement" enctype="multipart/form-data">
            <input type="hidden" id="drinkEditingIndex" value="">

            <div class="field-horizontal" style="gap: 12px; align-items: flex-start; margin-bottom: 12px;">
                <!-- Drink Name Field -->
                <label class="field-group" style="flex: 1; margin-bottom: 0;">
                    <span>Drink name</span>
                    <input type="text" class="field" id="drinkName" placeholder="e.g. Brown Sugar Latte">
                </label>

                <!-- Custom Styled Upload Button Side-by-Side -->
                <label class="field-group" style="width: auto; margin-bottom: 0;">
                    <span>Photo</span>
                    <div style="position: relative; display: inline-block;">
                        <input type="file" id="drinkImage" accept="image/*" style="display: none;" onchange="document.getElementById('fileNameText').textContent = this.files[0]?.name || 'No file chosen'">
                        <button type="button" class="btn btn--ghost" onclick="document.getElementById('drinkImage').click()" style="display: flex; align-items: center; gap: 6px; white-space: nowrap;">
                            📷 Choose photo
                        </button>
                    </div>
                    <span id="fileNameText" class="muted" style="font-size: 11px; margin-top: 2px; display: block; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">No file chosen</span>
                </label>
            </div>

            <div class="field-horizontal" style="gap: 12px;">
                <label class="field-group field-group--grow">
                    <span>Drink type</span>
                    <select class="field" id="drinkType">
                        <option value="">Select a type</option>
                        @foreach ($drinkTypes as $type)
                            <option value="{{ is_array($type) ? $type['id'] : $type }}">{{ is_array($type) ? $type['name'] : $type }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group field-group--narrow">
                    <span>Price (₱)</span>
                    <input type="decimal" class="field" id="drinkPrice" min="0" step="1" placeholder="150">
                </label>
            </div>

            <h3 class="sub-head" style="margin-top: 16px;">Recipe — ingredient usage per serving</h3>

            <!-- Dynamic Recipe Rows Container -->
            <div id="recipeRowsContainer" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px;"></div>

            <!-- Add Row Button -->
            <button type="button" class="btn btn--ghost btn--sm" id="addRecipeRowBtn">
                + Add Ingredient to Recipe
            </button>
        </form>

        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="drinkForm">Cancel</button>
            <button type="button" class="btn btn--primary" id="drinkSaveBtn">Save drink</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal: Configure Drink Type Category Template -->
    <x-admin.modal id="drinkTypeForm" title="Configure Drink Type Category" size="md">
        <div class="field-group">
            <span>Category / Type Name</span>
            <input type="text" class="field" id="newTypeName" placeholder="e.g. Iced Coffee, Flavored Soda">
        </div>

        <!-- Default Single Price Input -->
        <div id="singlePriceWrapper" class="field-group">
            <span>Price (₱)</span>
            <input type="decimal" class="field" id="typeSinglePrice" placeholder="e.g. 50">
        </div>

        <!-- Dual Size Price Inputs (Shown ONLY when Supports Sizes is checked) -->
        <div id="sizedPriceWrapper" class="field-horizontal" style="gap: 12px; margin-bottom: 12px;" hidden>
            <label class="field-group" style="flex: 1; margin-bottom: 0;">
                <span>Grande Price (₱)</span>
                <input type="decimal" class="field" id="typeGrandePrice" placeholder="65">
            </label>
            
            <label class="field-group" style="flex: 1; margin-bottom: 0;">
                <span>Venti Price (₱)</span>
                <input type="decimal" class="field" id="typeVentiPrice" placeholder="85">
            </label>
        </div>

        <fieldset class="checks">
            <legend>Options & Variation Rules</legend>
            <label class="check">
                <input type="checkbox" id="typeHasSizes" value="1">
                <span>Supports Sizes (Grande / Venti)</span>
            </label>
            <label class="check">
                <input type="checkbox" id="typeHasTemp" value="1">
                <span>Supports Hot & Cold Options</span>
            </label>
        </fieldset>

        <fieldset class="checks">
            <legend>Available POS Add-ons for this Category</legend>
            
            <div id="typeAddonCheckboxes" style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                @if(!empty($addons))
                    @foreach($addons as $addon)
                        <label class="check">
                            <input type="checkbox" name="typeAddons" value="{{ $addon['id'] ?? $addon['name'] }}">
                            <span>{{ $addon['name'] }} (₱{{ number_format($addon['price']) }})</span>
                        </label>
                    @endforeach
                @else
                    <span class="muted" style="font-size: 12px;">No premade add-ons found.</span>
                @endif
            </div>

            <div id="newAddonRow" hidden style="background: #fdf6ee; padding: 10px; border-radius: 6px; border: 1px dashed var(--line-soft); margin-top: 8px;">
                <div class="form-row" style="margin-bottom: 0;">
                    <label class="field-group" style="margin-bottom: 0;">
                        <span>Add-on Name</span>
                        <input type="text" class="field" id="customAddonName" placeholder="e.g. Oat Milk">
                    </label>
                    <label class="field-group" style="margin-bottom: 0; max-width: 100px;">
                        <span>Price (₱)</span>
                        <input type="decimal" class="field" id="customAddonPrice" placeholder="20">
                    </label>
                    <button type="button" class="btn btn--primary btn--sm" id="confirmAddonBtn" style="align-self: flex-end;">Add</button>
                </div>
            </div>

            <button type="button" class="btn btn--ghost btn--sm" id="showAddonRowBtn" style="margin-top: 6px;">
                + Create New Add-on
            </button>
        </fieldset>

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