<x-staff.shell title="Inventory">

    <main class="wrap">
        <div class="page-head">
            <h1>Inventory</h1>
            <p class="page-sub">Manage stock levels and record replenishments for {{ $branch ?? 'your assigned branch' }}.</p>
        </div>

        @if(session('status'))
            <p class="flash">{{ session('status') }}</p>
        @endif
        @error('quantity')
            <p class="flash err">{{ $message }}</p>
        @enderror

        <!-- Stat Cards Row -->
        <div class="stat-row inventory-stats-grid" id="inventoryStats"></div>

        <!-- Filter Panel -->
        <div class="panel">
            <div class="panel-body">
                <!-- Quick Filter Tabs -->
                <div class="inventory-tab-row">
                    <button type="button" class="inventory-tab-btn" id="tabRunningLow">
                        Running low <span id="runningLowBadge" class="badge badge--out inventory-tab-badge">0</span>
                    </button>
                    <button type="button" class="inventory-tab-btn is-active" id="tabAllItems">
                        All items
                    </button>
                </div>

                <!-- Filter Controls Row -->
                <div class="inventory-filter-controls">
                    <div class="inventory-filter-col-search">
                        <label for="stockSearch" class="inventory-filter-label">Search inventory</label>
                        <input type="search" class="field inventory-filter-input" id="stockSearch" placeholder="🔍 Search by item name">
                    </div>

                    <div class="inventory-filter-col-item">
                        <label for="stockStatusFilter" class="inventory-filter-label">Stock status</label>
                        <select class="field inventory-filter-input" id="stockStatusFilter">
                            <option value="all">All statuses</option>
                            <option value="low">Running low</option>
                            <option value="healthy">Healthy</option>
                            <option value="out">Out of stock</option>
                        </select>
                    </div>

                    <div class="inventory-filter-col-item">
                        <label for="stockAvailabilityFilter" class="inventory-filter-label">Availability</label>
                        <select class="field inventory-filter-input" id="stockAvailabilityFilter">
                            <option value="all">All availabilities</option>
                            <option value="available">Available</option>
                            <option value="unavailable">Unavailable (Out of stock)</option>
                            <option value="disabled">Disabled</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ingredients Table Panel -->
        <div class="panel">
            <div class="panel-head">
                <h2>Ingredients</h2>
                <span class="panel-note">On-hand updates as orders are served</span>
                <div class="panel-tools">
                    <button type="button" class="btn btn--primary btn--sm" id="btnOpenActionModal">+ Stock In / Out</button>
                </div>
            </div>

            <div class="panel-body table-body-flush">
                <div class="table-wrap">
                    <table class="table" id="stockTable">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th class="ta-r">On hand</th>
                                <th class="ta-r">Used Today</th>
                                <th class="ta-r">Reorder Point</th>
                                <th class="ta-r">Days left</th>
                                <th>Status</th>
                                <th>Availability</th>
                                <th class="ta-r">Stock value</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div id="inventoryPagination" class="pagination-bar-wrap" hidden>
                    <span id="inventoryPageInfo" class="muted pagination-info-text">Showing 1–10 of 0 items</span>
                    <div class="pagination-controls-box">
                        <button type="button" id="inventoryPrevBtn" class="btn btn--ghost btn--sm">&laquo; Prev</button>
                        <div id="inventoryPageNumbers" class="pagination-numbers-box"></div>
                        <button type="button" id="inventoryNextBtn" class="btn btn--ghost btn--sm">Next &raquo;</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Staff Stock Action Modal -->
    <div class="backdrop" id="staffActionModal">
        <div class="modal staff-modal-box">
            <div class="staff-modal-header">
                <h2 class="staff-modal-title">Staff Stock Action</h2>
                <button type="button" class="close staff-modal-close" id="btnCloseModal">&times;</button>
            </div>
            
            <form method="POST" action="{{ route('staff.inventory.action') }}" class="staff-modal-form">
                @csrf
                <div class="form-group-block">
                    <span class="form-group-label">Action Type</span>
                    <select class="field form-field-tall" name="action_type" id="staffActionType">
                        <option value="stock_in">📦 Stock Delivery / Replenishment (Stock-In)</option>
                        <option value="stock_out">🗑️ Stock Deduction / Spoilage (Stock-Out)</option>
                    </select>
                </div>

                <div class="form-group-block">
                    <span class="form-group-label">Select Ingredient</span>
                    <select class="field form-field-standard" name="ingredient_id" id="staffIngSelect" required>
                        @foreach($ingredients as $ing)
                            <option value="{{ $ing->id }}" data-unit="{{ $ing->unit }}">
                                {{ $ing->name }} (Available: {{ rtrim(rtrim(number_format($ing->stock, 2), '0'), '.') }} {{ $ing->unit }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-row-split">
                    <div class="form-col-flex">
                        <span id="modalQtyLabel" class="form-group-label">Quantity</span>
                        <input type="number" step="any" min="0.01" class="field form-field-standard" name="quantity" placeholder="e.g. 500" required>
                    </div>

                    <div class="form-col-flex" id="unitSelectGroup">
                        <span class="form-group-label">Input Unit</span>
                        <select class="field form-field-standard" name="unit_multiplier" id="staffUnitMultiplier">
                            <!-- Dynamically populated via JS -->
                        </select>
                    </div>

                    <div id="staffReasonGroup" class="form-col-flex" hidden>
                        <span class="form-group-label">Reason for Stock-Out</span>
                        <select class="field form-field-standard" name="reason">
                            <option value="spoilage">Expired / Spoiled</option>
                            <option value="spill">Spilled / Prep Mistake</option>
                            <option value="damage">Damaged Delivery</option>
                            <option value="audit">Inventory Count Correction</option>
                        </select>
                    </div>
                </div>

                <!-- Modal Action Buttons (Clean & Aligned) -->
                <div class="staff-modal-actions">
                    <button type="button" class="btn btn--ghost" id="btnCancelModal">Cancel</button>
                    <button type="submit" class="btn" id="btnSubmitAction">Confirm Stock-In</button>
                </div>
            </form>
        </div>
    </div>

@push('scripts')
        <script src="{{ asset('js/Staff.js') }}"></script>
        <script>
            window.KAPE_STAFF_INV = {
                ingredients: {!! json_encode($ingredients->map(fn($i) => [
                    'id'                => $i->id,
                    'key'               => $i->key,
                    'name'              => $i->name,
                    'unit'              => $i->unit,
                    'purchase_unit'     => $i->purchase_unit ?? $i->unit,
                    'conversion_factor' => (float) ($i->conversion_factor ?? 1.00),
                    'stock'             => (float) $i->stock,
                    'reorder'           => (float) $i->reorder_level,
                    'used_today'        => (float) ($i->used_today ?? 0),
                    'cost'              => (float) $i->cost,
                    'is_disabled'       => isset($i->is_active) ? !$i->is_active : false
                ])) !!}
            };
            document.addEventListener('DOMContentLoaded', () => {
                initStaffInventory();
            });
        </script>
    @endpush

</x-staff.shell>