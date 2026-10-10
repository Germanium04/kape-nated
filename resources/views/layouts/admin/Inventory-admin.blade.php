<x-admin.shell title="Inventory" heading="Inventory" subheading="Manage stock levels and record replenishments across branches.">

    <x-slot:headerActions>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="muted" style="font-size: 13px;">Branch</span>
            <select class="field" id="stockBranch" style="width: 220px;">
                <option value="all">All Branches (combined)</option>
                @foreach(array_keys($branchWeights) as $branch)
                    <option value="{{ $branch }}">{{ $branch }}</option>
                @endforeach
            </select>
        </div>
    </x-slot:headerActions>

    <!-- Stat Cards Row (Aligned 3 Equal Columns) -->
    <div class="stat-row" id="inventoryStats" style="grid-template-columns: repeat(3, 1fr) !important; margin-bottom: 16px;"></div>

    <!-- Filter Panel -->
    <x-admin.panel title="" note="">
        <!-- Quick Filter Tabs -->
        <div class="tab-row" style="display: flex; gap: 16px; border-bottom: 1px solid var(--line-soft, #e5e5e5); padding-bottom: 10px; margin-bottom: 16px;">
            <button type="button" class="tab-btn is-active" id="tabRunningLow" style="background: none; border: none; font-weight: 600; cursor: pointer; border-bottom: 2px solid var(--primary, #a63d2a); padding-bottom: 6px; color: var(--primary, #a63d2a);">
                Running low <span id="runningLowBadge" class="badge badge--out" style="margin-left: 4px; font-size: 11px;">0</span>
            </button>
            <button type="button" class="tab-btn" id="tabAllItems" style="background: none; border: none; font-weight: 500; cursor: pointer; padding-bottom: 6px; color: var(--muted, #666);">
                All items
            </button>
        </div>

        <!-- Filter Controls Row -->
        <div class="filter-row" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <label class="field-group" style="flex: 2; min-width: 200px;">
                <span>Search inventory</span>
                <input type="search" class="field" id="stockSearch" placeholder="🔍 Search by item name">
            </label>

            <label class="field-group" style="flex: 1; min-width: 160px;">
                <span>Branch Filter</span>
                <select class="field" id="stockBranchFilter">
                    <option value="all">All Branches (combined)</option>
                    @foreach(array_keys($branchWeights) as $branch)
                        <option value="{{ $branch }}">{{ $branch }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field-group" style="flex: 1; min-width: 130px;">
                <span>Stock status</span>
                <select class="field" id="stockStatusFilter">
                    <option value="all">All statuses</option>
                    <option value="low">Running low</option>
                    <option value="healthy">Healthy</option>
                    <option value="out">Out of stock / Disabled</option>
                </select>
            </label>

            <label class="field-group" style="flex: 1; min-width: 110px;">
                <span>Unit</span>
                <select class="field" id="stockUnitFilter">
                    <option value="all">All units</option>
                    <option value="ml">ml</option>
                    <option value="g">g</option>
                    <option value="pc">pc</option>
                </select>
            </label>
        </div>
    </x-admin.panel>

    <!-- Ingredients Live Stock Room Panel -->
    <x-admin.panel title="Ingredients Stock Room" note="On-hand stock levels update dynamically as POS orders are fulfilled">
        <x-slot:tools>
            <button type="button" class="btn btn--ghost btn--sm" data-open-modal="addIngredientModal">+ Master Directory</button>
            <button type="button" class="btn btn--primary btn--sm" data-open-modal="inventoryActionModal">📦 Manage Inventory</button>
        </x-slot:tools>

        <table class="table" id="stockTable">
            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th class="ta-r">On hand</th>
                    <th class="ta-r">Used, this rate</th>
                    <th class="ta-r">Reorder at</th>
                    <th class="ta-r">Est. Runout</th>
                    <th>Status</th>
                    <th class="ta-r">Stock value</th>
                    <th class="ta-r">Action</th>
                </tr>
            </thead>
            <tbody><!-- rendered by JS --></tbody>
        </table>

        <!-- Pagination Bar -->
        <div id="inventoryPagination" class="pagination-bar" hidden style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--line-soft, #e5e5e5);">
            <span id="inventoryPageInfo" class="muted" style="font-size: 13px;">Showing 1–10 of 0 items</span>
            <div class="pagination-controls" style="display: flex; gap: 6px; align-items: center;">
                <button type="button" id="inventoryPrevBtn" class="btn btn--ghost btn--sm">&laquo; Prev</button>
                <div id="inventoryPageNumbers" style="display: flex; gap: 4px;"></div>
                <button type="button" id="inventoryNextBtn" class="btn btn--ghost btn--sm">Next &raquo;</button>
            </div>
        </div>
    </x-admin.panel>

    <!-- Modal 1: Master Directory - Register New Raw Ingredient -->
    <x-admin.modal id="addIngredientModal" title="Register New Raw Ingredient" size="md">
        <p class="panel-intro">Register a brand new raw ingredient into the system's global master list.</p>

        <div class="field-horizontal" style="gap: 12px; margin-bottom: 12px;">
            <label class="field-group" style="flex: 2;">
                <span>Ingredient Name</span>
                <input type="text" class="field" id="invIngName" placeholder="e.g. Oat Milk">
            </label>
            
            <label class="field-group" style="flex: 1;">
                <span>Category (Optional)</span>
                <input type="text" class="field" id="invIngCategory" placeholder="e.g. Dairy, Syrup">
            </label>
        </div>

        <div class="field-horizontal" style="gap: 12px; margin-bottom: 12px;">
        <!-- Step 1: Select Measurement Type -->
        <label class="field-group" style="flex: 1;">
            <span>Measurement Type</span>
            <select class="field" id="invIngUnitType">
                <option value="volume">Liquid / Volume (ml)</option>
                <option value="weight">Solid / Weight (g)</option>
                <option value="count">Item Count / Pieces (pc)</option>
            </select>
        </label>

        <!-- Step 2: Packaging Unit (Auto-filtered by JS) -->
        <label class="field-group" style="flex: 1;">
            <span>Purchase Packaging</span>
            <select class="field" id="invIngPurchaseUnit">
                <option value="1L Bottle" data-factor="1000">1 Liter Bottle (1,000 ml)</option>
                <option value="Gallon" data-factor="3785.41">Gallon (3,785.41 ml)</option>
                <option value="500ml Pack" data-factor="500">500ml Pack</option>
            </select>
        </label>
    </div>

        <div class="field-horizontal" style="gap: 12px;">
            <label class="field-group" style="flex: 1;">
                <span>Reorder Point (Base Unit)</span>
                <input type="number" step="any" inputmode="decimal" class="field" id="invIngReorder" placeholder="e.g. 1000">
            </label>
            
            <label class="field-group" style="flex: 1;">
                <span>Estimated Cost per Base Unit (₱)</span>
                <input type="number" step="any" inputmode="decimal" class="field" id="invIngCost" placeholder="e.g. 0.15">
            </label>
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="addIngredientModal">Cancel</button>
            <button type="button" class="btn btn--primary" id="saveInvIngredientBtn">Save Ingredient</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 2: Dynamic Inventory Action Modal (Stock Movement Transactions) -->
    <x-admin.modal id="inventoryActionModal" title="Stock Operation" size="md">
        <label class="field-group" style="margin-bottom: 16px;">
            <span style="font-weight: 600; color: var(--ink, #26180f);">Select Action Type</span>
            <select class="field" id="invActionType" style="font-weight: 600; height: 42px;">
                <option value="stock_in">📦 Stock Delivery (Stock-In)</option>
                <option value="stock_out">🗑️ Stock Deduction / Spoilage (Stock-Out)</option>
                <option value="transfer">↔ Branch Stock Transfer</option>
            </select>
        </label>

        <hr style="border: 0; border-top: 1px dashed var(--line-soft, #d9c9b8); margin-bottom: 16px;">

        <!-- SECTION 1: Batch Stock-In -->
        <div id="secStockIn" class="action-section">
            <p class="panel-intro">Log a delivery containing one or multiple ingredients into a branch's stock room.</p>
            <div class="field-horizontal" style="gap: 12px; margin-bottom: 12px;">
                <label class="field-group" style="flex: 1;">
                    <span>Receiving Branch</span>
                    <select class="field" id="stockInBranch">
                        @foreach(array_keys($branchWeights) as $branch)
                            <option value="{{ $branch }}">{{ $branch }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group" style="flex: 1;">
                    <span>Supplier / Invoice No.</span>
                    <input type="text" class="field" id="stockInRef" placeholder="e.g. Invoice #1042">
                </label>
            </div>

            <p class="panel-intro" style="margin-bottom: 8px; font-weight: 600;">Delivered Items:</p>
            <div id="stockInRowsContainer" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px;"></div>
            <button type="button" class="btn btn--ghost btn--sm" id="addStockInRowBtn">+ Add Another Item</button>
        </div>

        <!-- SECTION 2: Stock-Out / Spoilage -->
        <div id="secStockOut" class="action-section" hidden>
            <p class="panel-intro">Log manual inventory deductions for spoilage, damage, spills, or audit adjustments.</p>
            <div class="field-horizontal" style="gap: 12px; margin-bottom: 12px;">
                <label class="field-group" style="flex: 2;">
                    <span>Ingredient</span>
                    <select class="field" id="stockOutIngredientSelect"></select>
                </label>

                <label class="field-group" style="flex: 1;">
                    <span>Deduction Qty</span>
                    <input type="number" step="any" inputmode="decimal" class="field" id="stockOutQtyInput" placeholder="e.g. 500">
                </label>
            </div>

            <label class="field-group">
                <span>Reason for Stock-Out</span>
                <select class="field" id="stockOutReasonSelect">
                    <option value="spoilage">Expired / Spoiled</option>
                    <option value="spill">Spilled / Preparation Mistake</option>
                    <option value="damage">Damaged Delivery Packaging</option>
                    <option value="audit">Inventory Count Correction</option>
                </select>
            </label>
        </div>

        <!-- SECTION 3: Inter-Branch Stock Transfer -->
        <div id="secTransfer" class="action-section" hidden>
            <p class="panel-intro">Transfer stock items from a source branch to a destination branch.</p>
            <div class="field-horizontal" style="gap: 12px; margin-bottom: 12px;">
                <label class="field-group" style="flex: 1;">
                    <span>From (Source Branch)</span>
                    <select class="field" id="transferFromBranch">
                        @foreach(array_keys($branchWeights) as $branch)
                            <option value="{{ $branch }}">{{ $branch }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group" style="flex: 1;">
                    <span>To (Destination Branch)</span>
                    <select class="field" id="transferToBranch">
                        @foreach(array_keys($branchWeights) as $branch)
                            <option value="{{ $branch }}">{{ $branch }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <p class="panel-intro" style="margin-bottom: 8px; font-weight: 600;">Transfer Items:</p>
            <div id="transferRowsContainer" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px;"></div>
            <button type="button" class="btn btn--ghost btn--sm" id="addTransferRowBtn">+ Add Item to Transfer</button>
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="inventoryActionModal">Cancel</button>
            <button type="button" class="btn btn--primary" id="submitInvActionBtn">Save Stock Delivery</button>
        </x-slot:footer>
    </x-admin.modal>

    @push('scripts')
        <script src="{{ asset('js/Admin3.js') }}"></script>
        <script>
            window.KAPE = {
                ingredients:   @json($ingredients),
                recipes:       @json($recipes),
                branchWeights: @json($branchWeights),
            };
            initInventory();
        </script>
    @endpush

</x-admin.shell>