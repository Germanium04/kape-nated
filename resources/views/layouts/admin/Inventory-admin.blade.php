<x-admin.shell title="Inventory Operations" heading="Inventory Operations" subheading="Log and audit stock deliveries and inter-branch transfers.">

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
            <button type="button" class="tab-btn tab-btn--active" id="tabAllItems">
                All Transactions
            </button>
            <button type="button" class="tab-btn tab-btn--inactive" id="tabRunningLow">
                ⚠️ Running Low Alerts <span id="runningLowBadge" class="badge badge--out tab-badge">0</span>
            </button>
        </div>

        <div class="filter-grid-row" style="grid-template-columns: 2fr 1fr 1fr;">
            <label class="field-group fg-search">
                <span>Search Ledger</span>
                <input type="search" class="field" id="stockSearch" placeholder="Search Transaction ID, item, or branch...">
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

            <!-- Dynamic Third Filter: Action Type or Alert Severity -->
            <label class="field-group fg-status" id="dynamicFilterWrapper">
                <span id="dynamicFilterLabel">Transaction Type</span>
                <select class="field" id="stockStatusFilter">
                    <option value="all">All Action Types</option>
                    <option value="stock_in">Stock Deliveries (Stock-In)</option>
                    <option value="transfer">↔ Branch Transfers</option>
                </select>
            </label>
        </div>
    </x-admin.panel>

    <div class="section-spacer-sm"></div>

    <!-- Stock Operations Panel -->
    <x-admin.panel id="ledgerPanel" title="Stock Operations Ledger" note="Real-time log of manual supply deliveries and inter-branch transfers">
        <x-slot:tools>
            <button type="button" class="btn btn--primary btn--sm" data-open-modal="inventoryActionModal">New Stock Operation</button>
        </x-slot:tools>

        <table class="table" id="stockTable">
            <thead><!-- Dynamic JS headers --></thead>
            <tbody><!-- Dynamic JS content --></tbody>
        </table>

        <!-- Pagination Bar -->
        <div id="inventoryPagination" class="pagination-bar pagination-wrapper" hidden>
            <span id="inventoryPageInfo" class="muted text-sm">Showing 1–10 of 0 records</span>
            <div class="pagination-controls-group">
                <button type="button" id="inventoryPrevBtn" class="btn btn--ghost btn--sm">Previous</button>
                <div id="inventoryPageNumbers" class="pagination-numbers-group"></div>
                <button type="button" id="inventoryNextBtn" class="btn btn--ghost btn--sm">Next</button>
            </div>
        </div>
    </x-admin.panel>

    <!-- Modal 1: Operations Modal (Admin Only: Delivery & Transfer) -->
    <x-admin.modal id="inventoryActionModal" title="Stock Operation" size="md">
        <div id="invFormNotice" style="display: none; padding: 10px 14px; border-radius: 6px; font-size: 12px; margin-bottom: 12px; font-weight: 500;"></div>

        <label class="field-group form-row-spacing">
            <span class="text-ink-bold">Select Action Type</span>
            <select class="field action-type-select" id="invActionType">
                <option value="stock_in">Stock Delivery (Stock-In)</option>
                <option value="transfer">Branch Stock Transfer</option>
            </select>
        </label>

        <hr class="modal-section-divider">

        <!-- SECTION 1: Batch Stock-In -->
        <div id="secStockIn" class="action-section">
            <p class="panel-intro">Log a supply delivery containing one or multiple ingredients into a branch's stock room.</p>
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
                    <span>Supplier / Invoice Ref</span>
                    <input type="text" class="field" id="stockInRef" placeholder="e.g. Invoice #1042 or Self-Made">
                </label>
            </div>

            <p class="panel-intro modal-section-label">Delivered Items:</p>
            <div id="stockInRowsContainer" class="dynamic-rows-container"></div>
            <button type="button" class="btn btn--ghost btn--sm" id="addStockInRowBtn">+ Add Another Item</button>
        </div>

        <!-- SECTION 2: Inter-Branch Stock Transfer -->
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

    <!-- Modal 1b: Confirmation Dialogue -->
    <x-admin.modal id="confirmInvActionModal" title="Confirm Stock Operation" size="sm">
        <div style="padding: 10px 0; text-align: center;">
            <div style="font-size: 32px; margin-bottom: 8px;">📦</div>
            <p style="margin: 0 0 6px; font-weight: 600; font-size: 15px;">Are you sure you want to commit this operation?</p>
            <p style="margin: 0; font-size: 12.5px; color: var(--ink-soft);" id="confirmInvText">Branch stock balances will be updated immediately.</p>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="confirmInvActionModal">Cancel</button>
            <button type="button" class="btn btn--primary" id="btnConfirmFinalInvSave">Okay, Save Operation</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 1c: Success Modal -->
    <x-admin.modal id="successInvModal" title="Operation Complete" size="sm">
        <div style="padding: 14px 0; text-align: center;">
            <div style="font-size: 36px; margin-bottom: 8px;">✅</div>
            <p style="margin: 0 0 4px; font-weight: 700; font-size: 16px; color: var(--good, #46703f);" id="successInvTitle">Success!</p>
            <p style="margin: 0; font-size: 13px; color: var(--ink-soft);" id="successInvText">Inventory operation recorded successfully!</p>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn--primary" id="btnSuccessModalClose" data-close-modal="successInvModal">Okay</button>
        </x-slot:footer>
    </x-admin.modal>

    <!-- Modal 1d: Error Dialogue Modal -->
    <x-admin.modal id="errorInvModal" title="Operation Failed" size="sm">
        <div style="padding: 14px 0; text-align: center;">
            <div style="font-size: 36px; margin-bottom: 8px;">⚠️</div>
            <p style="margin: 0 0 4px; font-weight: 700; font-size: 16px; color: #c0392b;" id="errorInvTitle">Failed!</p>
            <p style="margin: 0; font-size: 13px; color: var(--ink-soft);" id="errorInvText">An unexpected error occurred during the transaction.</p>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn--primary" data-close-modal="errorInvModal">Understood</button>
        </x-slot:footer>
    </x-admin.modal>

    @push('scripts')
        <script src="{{ asset('js/Admin3.js') }}"></script>
        <script>
            window.KAPE = {
                ingredients:   @json($ingredients),
                recipes:       @json($recipes),
                branchWeights: @json($branchWeights),
                movements:     @json($movements ?? []),
            };
            initInventory();
        </script>
    @endpush

</x-admin.shell>