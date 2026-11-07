
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("search");
    const orderCards = Array.from(
        document.querySelectorAll(".admin-order-card")
    );

    if (!searchInput || orderCards.length === 0) {
        return;
    }

    // Add a live result count above the order cards.
    const resultCount = document.createElement("p");
    resultCount.id = "liveOrderCount";
    resultCount.setAttribute("aria-live", "polite");
    searchInput.closest("form").insertAdjacentElement(
        "afterend",
        resultCount
    );

    function normalise(value) {
        return String(value ?? "").trim().toLowerCase();
    }

    function filterOrders() {
        const query = normalise(searchInput.value);
        let visibleCount = 0;

        orderCards.forEach(function (card) {
            const searchableText = [
                card.dataset.orderId,
                card.dataset.customer,
                card.dataset.email,
                card.dataset.status,
                card.dataset.paymentStatus,
                card.textContent
            ].map(normalise).join(" ");

            const matches = searchableText.includes(query);

            card.hidden = !matches;

            if (matches) {
                visibleCount++;
            }
        });

        resultCount.textContent =
            "Showing " + visibleCount + " of " +
            orderCards.length + " orders on this page.";
    }

    searchInput.addEventListener("input", filterOrders);
    filterOrders();

    // Confirm fulfilment status changes before submitting.
    
        
        document.querySelectorAll(
            'form[action="orders.php"]'
        ).forEach(function (form) {
            // Only attach confirmation to forms that update an order.
            if (!form.querySelector('input[name="order_id"]')) {
                return;
            }

            const statusSelect = form.querySelector(
                'select[name="status"]'
            );

            if (!statusSelect) {
                return;
            }



        form.addEventListener("submit", function (event) {
            const selectedStatus = statusSelect.value;

            const confirmed = window.confirm(
                "Change this order's fulfilment status to '" +
                selectedStatus + "'?"
            );

            if (!confirmed) {
                event.preventDefault();
            }
        });
    });
});
