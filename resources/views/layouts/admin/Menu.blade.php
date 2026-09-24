@php use App\Support\DemoData; @endphp

<x-admin.shell title="Menu" heading="Drink menu" subheading="What staff can make, the price, and how much of each ingredient one cup uses">

    <x-admin.panel title="Drinks" note="{{ count($menuItems) }} on the menu">
        <x-slot:tools>
            <button type="button" class="btn btn--primary btn--sm" id="addDrinkBtn">Add new drink</button>
        </x-slot:tools>

        <table class="table" id="menuTable">
            <thead>
                <tr><th>Drink</th><th class="ta-r">Price</th><th>Recipe</th><th>Action</th></tr>
            </thead>
            <tbody><!-- rendered by admin.js --></tbody>
        </table>
    </x-admin.panel>

    <x-admin.modal id="drinkForm" title="Add new drink" size="md">
        <input type="hidden" id="drinkEditingIndex" value="">

        <label class="field-group">
            <span>Drink name</span>
            <input type="text" class="field" id="drinkName" placeholder="e.g. Brown Sugar Latte">
        </label>

        <div class="field-horizontal">
            <label class="field-group field-group--narrow field-line field-vertical">
                <span>Drink type</span>
                    <select class="field" id="drinkType">
                        <option value="drinkType">Select a type</option>
                        @foreach (DemoData::drinkTypes() as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                </select>
            </label>

            <label class="field-group field-group--narrow field-line field-vertical">
                <span>Price (₱)</span>
                <input type="number" class="field" id="drinkPrice" min="0" step="1" placeholder="150">
            </label>
        </div>

        <h3 class="sub-head">Recipe — how much of each ingredient one cup uses</h3>
        <div class="recipe-fields" id="drinkRecipeFields"></div>

        <x-slot:footer>
            <button type="button" class="btn btn--ghost" data-close-modal="drinkForm">Cancel</button>
            <button type="button" class="btn btn--primary" id="drinkSaveBtn">Save drink</button>
        </x-slot:footer>
    </x-admin.modal>

    @push('scripts')
        <script>
            window.KAPE_MENU = {
                menuItems:   @json($menuItems),
                ingredients: @json($ingredients),
            };
            initMenu();
        </script>
    @endpush

</x-admin.shell>