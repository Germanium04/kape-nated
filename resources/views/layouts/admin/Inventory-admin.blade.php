<x-admin.shell title="Inventory" heading="Inventory" subheading="What's on hand, what it's costing you, and what needs restocking">

    <x-admin.panel title="Filter" note="Pick a branch, and a rate to project days left">
        <div class="filter-row">
            <label class="field-group">
                <span>Branch</span>
                <select class="field" id="stockBranch">
                    <option value="all">All branches (combined)</option>
                    @foreach(array_keys(\App\Support\DemoData::branchWeights()) as $branch)
                        <option value="{{ $branch }}">{{ $branch }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field-group">
                <span>Usage rate</span>
                <select class="field" id="stockRateMode">
                    <option value="day">Today's pace</option>
                    <option value="month">This month's pace</option>
                    <option value="year">This year's pace</option>
                </select>
            </label>
        </div>
        <p class="panel-intro" style="margin-top:10px">
            Stock on hand is a live count for the branch you pick. The usage rate only changes how "days left" is
            projected — a monthly or yearly average smooths out one unusually busy or slow day.
        </p>
    </x-admin.panel>

    <div id="lowStockAlert"></div>

    <div class="stat-row" id="inventoryStats"></div>

    <x-admin.panel title="Stock room" note="On-hand updates as orders are served">
        <x-slot:tools>
            <input type="search" class="field" id="stockSearch" placeholder="Search an ingredient">
            <button type="button" class="btn btn--primary btn--sm" data-open-modal="stockIn">Add stock</button>
        </x-slot:tools>

        <table class="table" id="stockTable">
            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th class="ta-r">On hand</th>
                    <th class="ta-r">Used, this rate</th>
                    <th class="ta-r">Reorder at</th>
                    <th class="ta-r">Days left</th>
                    <th>Status</th>
                    <th class="ta-r">Stock value</th>
                </tr>
            </thead>
            <tbody><!-- rendered by admin.js --></tbody>
        </table>

        <p class="muted" style="margin:12px 0 0;font-size:13px">
            <strong>Planned for later:</strong> once the staff-side terminal is built, a cashier will be able to flag
            spoiled or wasted stock on the spot from their own screen, instead of it being logged here.
        </p>
    </x-admin.panel>

    <x-admin.modal id="stockIn" title="Add stock" size="sm">
        <p class="panel-intro">Log what you're putting into this branch's stock room. On-hand goes up right away.</p>

        <label class="field-group">
            <span>Branch</span>
            <select class="field" id="stockInBranch">
                @foreach(array_keys(\App\Support\DemoData::branchWeights()) as $branch)
                    <option value="{{ $branch }}">{{ $branch }}</option>
                @endforeach
            </select>
        </label>

        <label class="field-group">
            <span>Ingredient</span>
            <select class="field" id="stockInItem"></select>
        </label>

        <label class="field-group">
            <span>Quantity added</span>
            <input type="number" class="field" id="stockInQty" value="1000" min="1">
        </label>

        <label class="field-group">
            <span>Note (optional)</span>
            <input type="text" class="field" id="stockInNote" placeholder="e.g. bought 20 sacks, 20 Sept">
        </label>

        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="stockIn">Cancel</button>
            <button type="button" class="btn btn--primary" id="stockInSave">Add to stock</button>
        </x-slot:footer>
    </x-admin.modal>

    @php
        $ingredients = \App\Support\DemoData::ingredients();
        $recipes = \App\Support\DemoData::recipes();
        $branchWeights = \App\Support\DemoData::branchWeights();
    @endphp

    @push('scripts')
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