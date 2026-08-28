document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    const form = document.querySelector("[data-orders-batch]");

    if (!form) {
        return;
    }

    const selectableOrders = Array.from(form.querySelectorAll("[data-orders-selectable]"));
    const selectAll = form.querySelector("[data-orders-select-all]");
    const submitButton = form.querySelector("[data-orders-batch-submit]");
    const selectionCount = form.querySelector("[data-orders-selection-count]");

    function refreshSelection() {
        const selectedCount = selectableOrders.filter(function (input) {
            return input.checked;
        }).length;

        if (selectionCount) {
            selectionCount.textContent = selectedCount === 0
                ? "Aucune sélection"
                : selectedCount + " sélectionnée" + (selectedCount > 1 ? "s" : "");
        }

        if (submitButton) {
            submitButton.disabled = selectedCount === 0;
            submitButton.textContent = selectedCount === 0
                ? "Générer les factures"
                : "Générer " + selectedCount + " facture" + (selectedCount > 1 ? "s" : "");
        }

        if (selectAll) {
            selectAll.checked = selectableOrders.length > 0 && selectedCount === selectableOrders.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < selectableOrders.length;
        }
    }

    selectAll?.addEventListener("change", function () {
        selectableOrders.forEach(function (input) {
            input.checked = selectAll.checked;
        });
        refreshSelection();
    });

    form.addEventListener("change", function (event) {
        if (event.target.matches("[data-orders-selectable]")) {
            refreshSelection();
        }
    });

    form.addEventListener("submit", function (event) {
        if (!selectableOrders.some(function (input) { return input.checked; })) {
            event.preventDefault();
        }
    });

    refreshSelection();
});
