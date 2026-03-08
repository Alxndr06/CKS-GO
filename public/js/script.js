/* MENU BURGER */
document.addEventListener("DOMContentLoaded", function () {
    const burger = document.getElementById("burger");
    const navbar = document.getElementById("main_navbar");

    if (burger && navbar) {
        burger.addEventListener("click", function () {
            navbar.classList.toggle("show");
            navbar.classList.toggle("hide");
            burger.classList.toggle("open");
            document.body.classList.toggle("no-scroll");
        });
    }
});

/* Liens cliquables dans la liste des utilisateurs */
document.addEventListener("click", function (e) {
    const row = e.target.closest("tr.user-row");
    if (!row) return;

    const isActionClick = e.target.closest(".col-actions");
    const isInteractive = e.target.closest("a, button, input, select, textarea, label");

    if (isActionClick || isInteractive) {
        e.stopPropagation();
        return;
    }

    const href = row.dataset.href;
    if (href) {
        window.location.href = href;
    }
});

/* FACTURATION MULTI-PRODUITS ADMIN */
document.addEventListener("DOMContentLoaded", function () {
    const wrapper = document.getElementById("charge_lines_wrapper");
    const addBtn = document.getElementById("add_charge_line_btn");

    if (!wrapper || !addBtn) {
        return;
    }

    function bindRemoveButtons() {
        const buttons = wrapper.querySelectorAll(".remove_charge_line_btn");

        buttons.forEach((button) => {
            button.onclick = function () {
                const lines = wrapper.querySelectorAll(".charge_line");

                if (lines.length <= 1) {
                    return;
                }

                const line = button.closest(".charge_line");
                if (line) {
                    line.remove();
                }
            };
        });
    }

    addBtn.addEventListener("click", function () {
        const firstLine = wrapper.querySelector(".charge_line");

        if (!firstLine) {
            return;
        }

        const clone = firstLine.cloneNode(true);

        clone.querySelectorAll("select").forEach((select) => {
            select.selectedIndex = 0;
        });

        clone.querySelectorAll("input").forEach((input) => {
            if (input.type === "number") {
                input.value = 1;
            } else {
                input.value = "";
            }
        });

        wrapper.appendChild(clone);
        bindRemoveButtons();
    });

    bindRemoveButtons();
});