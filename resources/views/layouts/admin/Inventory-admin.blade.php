<x-admin.shell title="Inventory" heading="Inventory" subheading="Manage stock levels and record replenishments across branches.">

    <x-slot:headerActions>
        <div class="header-actions-wrapper">
            <span class="muted text-sm">Branch</span>
            <select class="field branch-select-field" id="stockBranch">
                <option value="all">All Branches (combined)</option>
                @foreach(array_keys($branchWeights) as $branch)
                    <option value="{{ $branch }}">{{ $branch }}</option>
                @endforeach
            </select>
        </div>
    </x-slot:headerActions>

    <!-- Stat Cards Row -->
    <div class="stat-row inventory-stats-grid" id="inventoryStats"></div>

    <!-- Filter Panel -->
    <x-admin.panel title="" note="">
        <div class="tab-nav-row">
            <button type="button" class="tab-btn tab-btn--active" id="tabRunningLow">
                Running low <span id="runningLowBadge" class="badge badge--out tab-badge">0</span>
            </button>
            <button type="button" class="tab-btn tab-btn--inactive" id="tabAllItems">
                All items
            </button>
        </div>

        <div class="filter-grid-row">
            <label class="field-group fg-search">
                <span>Search inventory</span>
                <input type="search" class="field" id="stockSearch" placeholder="🔍 Search by item name">
            </label>

            <label class="field-group fg-branch">
                <span>Branch Filter</span>
                <select class="field" id="stockBranchFilter">
                    <option value="all">All Branches (combined)</option>
                    @foreach(array_keys($branchWeights) as $branch)
                        <option value="{{ $branch }}">{{ $branch }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field-group fg-status">
                <span>Stock status</span>
                <select class="field" id="stockStatusFilter">
                    <option value="all">All statuses</option>
                    <option value="low">Running low</option>
                    <option value="healthy">Healthy</option>
                    <option value="out">Out of stock / Disabled</option>
                </select>
            </label>

            <label class="field-group fg-unit">
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

    <div class="section-spacer-sm"></div>

    <!-- Ingredients Live Stock Room Panel -->
    <x-admin.panel title="Ingredients Stock Room" note="On-hand stock levels update dynamically as POS orders are fulfilled">
        <x-slot:tools>
            <button type="button" class="btn btn--primary btn--sm" data-open-modal="inventoryActionModal">📦 Manage Inventory</button>
        </x-slot:tools>

        <table class="table" id="stockTable">
            <thead>
                <tr>
                    <th>Branch</th>
                    <th>Ingredient</th>
                    <th class="ta-r">On hand</th>
                    <th class="ta-r">Used today</th>
                    <th class="ta-r">Reorder at</th>
                    <th>Status</th>
                    <th class="ta-r">Stock value</th>
                </tr>
            </thead>
            <tbody><!-- rendered by JS --></tbody>
        </table>

        <!-- Receipts-style Pagination Bar -->
        <div id="inventoryPagination" class="pagination-bar pagination-wrapper" hidden>
            <span id="inventoryPageInfo" class="muted text-sm">Showing 1–10 of 0 items</span>
            <div class="pagination-controls-group">
                <button type="button" id="inventoryPrevBtn" class="btn btn--ghost btn--sm">Previous</button>
                <div id="inventoryPageNumbers" class="pagination-numbers-group"></div>
                <button type="button" id="inventoryNextBtn" class="btn btn--ghost btn--sm">Next</button>
            </div>
        </div>
    </x-admin.panel>

    <!-- Modal 1: Register New Raw Ingredient -->
    <x-admin.modal id="addIngredientModal" title="Register New Raw Ingredient" size="md">
        <p class="panel-intro">Register a brand new raw ingredient into the system's global master list.</p>

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

            <label class="field-group fg-branch">
                <span>Purchase Packaging</span>
                <select class="field" id="invIngPurchaseUnit">
                    <option value="1L Bottle" data-factor="1000">1 Liter Bottle (1,000 ml)</option>
                    <option value="Gallon" data-factor="3785.41">Gallon (3,785.41 ml)</option>
                    <option value="500ml Pack" data-factor="500">500ml Pack</option>
                </select>
            </label>
        </div>

        <div class="field-horizontal">
            <label class="field-group fg-branch">
                <span>Reorder Point (Base Unit)</span>
                <input type="number" step="any" inputmode="decimal" class="field" id="invIngReorder" placeholder="e.g. 1000">
            </label>
            <label class="field-group fg-branch">
                <span>Estimated Cost per Base Unit (₱)</span>
                <input type="number" step="any" inputmode="decimal" class="field" id="invIngCost" placeholder="e.g. 0.15">
            </label>
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="addIngredientModal">Cancel</button>
            <button type="button" class="btn btn--primary" id="saveInvIngredientBtn">Save Ingredient</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 2: Dynamic Operations Modal -->
    <x-admin.modal id="inventoryActionModal" title="Stock Operation" size="md">
        <label class="field-group form-row-spacing">
            <span class="text-ink-bold">Select Action Type</span>
            <select class="field action-type-select" id="invActionType">
                <option value="stock_in">📦 Stock Delivery (Stock-In)</option>
                <option value="stock_out">🗑️ Stock Deduction / Spoilage (Stock-Out)</option>
                <option value="transfer">↔ Branch Stock Transfer</option>
            </select>
        </label>

        <hr class="modal-section-divider">

        <!-- SECTION 1: Batch Stock-In -->
        <div id="secStockIn" class="action-section">
            <p class="panel-intro">Log a delivery containing one or multiple ingredients into a branch's stock room.</p>
            <div class="field-horizontal form-row-spacing">
                <label class="field-group fg-branch">
                    <span>Receiving Branch</span>
                    <select class="field" id="stockInBranch">
                        @foreach(array_keys($branchWeights) as $branch)
                            <option value="{{ $branch }}">{{ $branch }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group fg-branch">
                    <span>Supplier / Invoice No.</span>
                    <input type="text" class="field" id="stockInRef" placeholder="e.g. Invoice #1042">
                </label>
            </div>

            <p class="panel-intro modal-section-label">Delivered Items:</p>
            <div id="stockInRowsContainer" class="dynamic-rows-container"></div>
            <button type="button" class="btn btn--ghost btn--sm" id="addStockInRowBtn">+ Add Another Item</button>
        </div>

        <!-- SECTION 2: Stock-Out / Spoilage -->
        <div id="secStockOut" class="action-section" hidden>
            <p class="panel-intro">Log manual inventory deductions for spoilage, damage, spills, or audit adjustments.</p>
            <div class="field-horizontal form-row-spacing">
                <label class="field-group fg-search">
                    <span>Ingredient</span>
                    <select class="field" id="stockOutIngredientSelect"></select>
                </label>

                <label class="field-group fg-branch">
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
            <div class="field-horizontal form-row-spacing">
                <label class="field-group fg-branch">
                    <span>From (Source Branch)</span>
                    <select class="field" id="transferFromBranch">
                        @foreach(array_keys($branchWeights) as $branch)
                            <option value="{{ $branch }}">{{ $branch }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group fg-branch">
                    <span>To (Destination Branch)</span>
                    <select class="field" id="transferToBranch">
                        @foreach(array_keys($branchWeights) as $branch)
                            <option value="{{ $branch }}">{{ $branch }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <p class="panel-intro modal-section-label">Transfer Items:</p>
            <div id="transferRowsContainer" class="dynamic-rows-container"></div>
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