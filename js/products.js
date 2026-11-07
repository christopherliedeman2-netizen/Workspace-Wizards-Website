document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("productFilterForm");
    const searchInput = document.getElementById("productSearch");
    const categoryFilter = document.getElementById("categoryFilter");
    const minPriceInput = document.getElementById("minPrice");
    const maxPriceInput = document.getElementById("maxPrice");
    const resetButton = document.getElementById("resetProductFilters");
    const productGrid = document.getElementById("productGrid");
    const resultsCount = document.getElementById("productResultsCount");
    const noProductsMessage = document.getElementById("noProductsMessage");
    const filterError = document.getElementById("filterError");

    if (
        !form ||
        !searchInput ||
        !categoryFilter ||
        !minPriceInput ||
        !maxPriceInput ||
        !productGrid ||
        !resultsCount ||
        !noProductsMessage ||
        !filterError
    ) {
        return;
    }

    const productCards = Array.from(
        productGrid.querySelectorAll(".product-card")
    );

    function normalise(value) {
        return String(value ?? "")
            .toLocaleLowerCase()
            .trim();
    }

    function showError(message) {
        filterError.textContent = message;
        filterError.hidden = !message;
    }

    function applyFilters() {
        const searchTerm = normalise(searchInput.value);
        const selectedCategory = normalise(categoryFilter.value);
        const minValue = minPriceInput.value.trim();
        const maxValue = maxPriceInput.value.trim();

        const minPrice = minValue === "" ? null : Number(minValue);
        const maxPrice = maxValue === "" ? null : Number(maxValue);

        if (
            (minValue !== "" &&
                (!Number.isFinite(minPrice) || minPrice < 0)) ||
            (maxValue !== "" &&
                (!Number.isFinite(maxPrice) || maxPrice < 0))
        ) {
            showError("Enter valid prices of R0 or more.");
            return;
        }

        if (
            minPrice !== null &&
            maxPrice !== null &&
            minPrice > maxPrice
        ) {
            showError(
                "The minimum price cannot be higher than the maximum price."
            );
            return;
        }

        showError("");

        let visibleCount = 0;

        productCards.forEach(function (card) {
            const name = normalise(card.dataset.name);
            const description = normalise(card.dataset.description);
            const category = normalise(card.dataset.category);
            const price = Number(card.dataset.price);

            const matchesSearch =
                searchTerm === "" ||
                name.includes(searchTerm) ||
                description.includes(searchTerm) ||
                category.includes(searchTerm);

            const matchesCategory =
                selectedCategory === "" ||
                category === selectedCategory;

            const matchesMinPrice =
                minPrice === null || price >= minPrice;

            const matchesMaxPrice =
                maxPrice === null || price <= maxPrice;

            const matches =
                matchesSearch &&
                matchesCategory &&
                matchesMinPrice &&
                matchesMaxPrice;

            card.hidden = !matches;

            if (matches) {
                visibleCount++;
            }
        });

        resultsCount.textContent =
            "Showing " +
            visibleCount +
            " of " +
            productCards.length +
            " products.";

        noProductsMessage.hidden = visibleCount !== 0;
    }

    // Filter as the customer types or changes a selection.
    searchInput.addEventListener("input", applyFilters);
    categoryFilter.addEventListener("change", applyFilters);
    minPriceInput.addEventListener("input", applyFilters);
    maxPriceInput.addEventListener("input", applyFilters);

    // Prevent the form from reloading the page.
    form.addEventListener("submit", function (event) {
        event.preventDefault();
        applyFilters();
    });

    // Clear every filter and show all active products again.
    form.addEventListener("reset", function () {
        window.setTimeout(function () {
            showError("");
            applyFilters();
        }, 0);
    });

    // Ensure a search supplied in the URL is applied on page load.
    applyFilters();
});
