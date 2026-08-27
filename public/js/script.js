function getBaseUrl() {
    return document.body?.dataset?.baseUrl || "";
}

document.addEventListener("click", function (event) {
    const printTrigger = event.target.closest("[data-print-trigger]");
    if (printTrigger) {
        window.print();
        return;
    }

    const stockTrigger = event.target.closest("[data-stock-form-target]");
    if (!stockTrigger) return;

    const targetId = stockTrigger.getAttribute("data-stock-form-target");
    const form = targetId ? document.getElementById(targetId) : null;
    if (!form) return;

    const open = form.classList.toggle("is_open");
    stockTrigger.setAttribute("aria-expanded", String(open));
});

/* BURGER MENU */
document.addEventListener("DOMContentLoaded", function () {
    const burger = document.getElementById("burger");
    const navbar = document.getElementById("main_navbar");

    if (!burger || !navbar) {
        return;
    }

    function openMenu() {
        navbar.classList.add("show");
        navbar.classList.remove("hide");
        burger.classList.add("open");
        burger.setAttribute("aria-expanded", "true");
        document.body.classList.add("no-scroll");
    }

    function closeMenu() {
        navbar.classList.remove("show");
        navbar.classList.add("hide");
        burger.classList.remove("open");
        burger.setAttribute("aria-expanded", "false");
        document.body.classList.remove("no-scroll");
    }

    function toggleMenu() {
        if (navbar.classList.contains("show")) {
            closeMenu();
            return;
        }

        openMenu();
    }

    burger.addEventListener("click", function (e) {
        e.stopPropagation();

        if (window.innerWidth <= 768) {
            toggleMenu();
        }
    });

    document.addEventListener("click", function (e) {
        const clickInsideHeader = e.target.closest("header");

        if (!clickInsideHeader && navbar.classList.contains("show")) {
            closeMenu();
        }
    });

    navbar.querySelectorAll("a").forEach(function (link) {
        link.addEventListener("click", function () {
            if (window.innerWidth <= 768) {
                closeMenu();
            }
        });
    });

    window.addEventListener("resize", function () {
        if (window.innerWidth > 768) {
            closeMenu();
        }
    });
});

/* RETOUR EN HAUT — ESPACE DE GESTION */
document.addEventListener("DOMContentLoaded", function () {
    const scrollTopButton = document.querySelector("[data-staff-scroll-top]");

    if (!scrollTopButton) {
        return;
    }

    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    let scheduled = false;

    const syncVisibility = function () {
        const revealAfter = Math.max(420, window.innerHeight * 0.55);
        scrollTopButton.hidden = window.scrollY < revealAfter;
        scheduled = false;
    };

    const scheduleVisibilitySync = function () {
        if (scheduled) {
            return;
        }

        scheduled = true;
        window.requestAnimationFrame(syncVisibility);
    };

    scrollTopButton.addEventListener("click", function () {
        window.scrollTo({
            top: 0,
            behavior: reducedMotion.matches ? "auto" : "smooth",
        });
    });

    window.addEventListener("scroll", scheduleVisibilitySync, { passive: true });
    window.addEventListener("resize", scheduleVisibilitySync);
    syncVisibility();
});

/* Liens cliquables dans la liste des utilisateurs */
document.addEventListener("click", function (e) {
    const row = e.target.closest("tr.user-row");
    if (!row) {
        return;
    }

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

        buttons.forEach(function (button) {
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

        clone.querySelectorAll("select").forEach(function (select) {
            select.selectedIndex = 0;
        });

        clone.querySelectorAll("input").forEach(function (input) {
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

/* BOUTIQUE — RECHERCHE INSTANTANEE */
document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("[data-shop-live-search-form]");

    if (!form) {
        return;
    }

    const input = form.querySelector("[data-shop-live-search]");
    const cards = Array.from(document.querySelectorAll("[data-shop-product-card]"));
    const resultCount = document.querySelector("[data-shop-result-count]");
    const catalogTitle = document.querySelector("[data-shop-catalog-title]");
    const resetLink = form.querySelector("[data-shop-search-reset]");
    const emptyState = document.querySelector("[data-shop-live-empty]");
    const categoryLinks = Array.from(form.querySelectorAll(".cat_pills a"));
    const initialServerQuery = String(form.dataset.initialQuery || "").trim();
    const categoryInput = form.querySelector('input[name="cat"]');
    const hasCategoryFilter = Boolean(categoryInput && categoryInput.value);
    let serverSearchTimer = null;

    if (!input || cards.length === 0) {
        return;
    }

    categoryLinks.forEach(function (link) {
        link.dataset.baseHref = link.getAttribute("href") || "index.php?controller=shop&action=index";
    });

    function normalizeSearchValue(value) {
        return String(value || "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLocaleLowerCase("fr")
            .trim();
    }

    function updateCategoryLinks(query) {
        categoryLinks.forEach(function (link) {
            const url = new URL(link.dataset.baseHref, window.location.href);

            if (query) {
                url.searchParams.set("q", query);
            } else {
                url.searchParams.delete("q");
            }

            link.href = url.toString();
        });
    }

    function updateAddress(query) {
        const url = new URL(window.location.href);

        if (query) {
            url.searchParams.set("q", query);
        } else {
            url.searchParams.delete("q");
        }

        window.history.replaceState(null, "", url.toString());
    }

    function applyLiveFilter() {
        const rawQuery = input.value.trim();
        const normalizedQuery = normalizeSearchValue(rawQuery);
        let visibleCount = 0;

        cards.forEach(function (card) {
            const searchText = normalizeSearchValue(card.dataset.searchText || card.textContent);
            const isVisible = normalizedQuery === "" || searchText.includes(normalizedQuery);

            card.hidden = !isVisible;

            if (isVisible) {
                visibleCount += 1;
            }
        });

        const resultLabel = visibleCount + " résultat" + (visibleCount > 1 ? "s" : "");

        if (resultCount) {
            resultCount.textContent = resultLabel;
        }

        if (catalogTitle) {
            catalogTitle.textContent = rawQuery ? "Résultats pour « " + rawQuery + " »" : "Tous les produits";
        }

        if (emptyState) {
            emptyState.hidden = visibleCount > 0;
        }

        if (resetLink) {
            resetLink.hidden = rawQuery === "" && !hasCategoryFilter;
        }

        updateCategoryLinks(rawQuery);
        updateAddress(rawQuery);
    }

    input.addEventListener("input", function () {
        const currentQuery = input.value.trim();

        if (initialServerQuery && currentQuery !== initialServerQuery) {
            window.clearTimeout(serverSearchTimer);
            serverSearchTimer = window.setTimeout(function () {
                HTMLFormElement.prototype.submit.call(form);
            }, 260);
            return;
        }

        applyLiveFilter();
    });

    form.addEventListener("submit", function (event) {
        if (!initialServerQuery) {
            event.preventDefault();
            applyLiveFilter();
        }
    });

    if (resetLink) {
        resetLink.addEventListener("click", function (event) {
            if (initialServerQuery || hasCategoryFilter) {
                return;
            }

            event.preventDefault();
            input.value = "";
            applyLiveFilter();
            input.focus();
        });
    }

    applyLiveFilter();
});

/* BOUTIQUE — BARRE DE FILTRES ET PANIER STICKY */
document.addEventListener("DOMContentLoaded", function () {
    const filters = document.querySelector(".shop_filters_redesign");

    if (!filters) {
        return;
    }

    let updateFrame = null;

    function updateStickyShopLayout() {
        updateFrame = null;

        const shouldCompact = window.innerWidth > 768;
        filters.classList.toggle("is_compact", shouldCompact);

        const cartTop = shouldCompact
            ? Math.ceil(filters.getBoundingClientRect().height + 22)
            : 12;

        document.documentElement.style.setProperty("--shop-sticky-cart-top", cartTop + "px");
    }

    function requestStickyShopUpdate() {
        if (updateFrame !== null) {
            return;
        }

        updateFrame = window.requestAnimationFrame(updateStickyShopLayout);
    }

    window.addEventListener("scroll", requestStickyShopUpdate, { passive: true });
    window.addEventListener("resize", requestStickyShopUpdate);

    if ("ResizeObserver" in window) {
        const resizeObserver = new ResizeObserver(requestStickyShopUpdate);
        resizeObserver.observe(filters);
    }

    updateStickyShopLayout();
});

/* MAJ IMAGE VARIANTE */
document.addEventListener("DOMContentLoaded", function () {
    const baseUrl = getBaseUrl();
    const variantRadios = document.querySelectorAll('input[type="radio"][name="variant_id"][data-product-id]');
    const variantSelects = document.querySelectorAll(".shop_variant_select[data-product-id]");

    variantRadios.forEach(function (radio) {
        radio.addEventListener("change", function () {
            if (!this.checked) {
                return;
            }

            const productId = this.dataset.productId;
            const image = this.dataset.image;
            const img = document.getElementById("product-image-" + productId);

            if (img && image) {
                img.src = baseUrl + "/public/img/" + image;
            }
        });
    });

    variantSelects.forEach(function (select) {
        select.addEventListener("change", function () {
            const productId = this.dataset.productId;
            const selectedOption = this.options[this.selectedIndex];
            const image = selectedOption.dataset.image;
            const img = document.getElementById("product-image-" + productId);

            if (img && image) {
                img.src = baseUrl + "/public/img/" + image;
            }
        });
    });
});

/* MAJ CARD PRODUIT DEPUIS VARIANTE */
document.addEventListener("DOMContentLoaded", function () {
    const variantSelects = document.querySelectorAll(".shop_variant_select");
    const baseUrl = getBaseUrl();

    variantSelects.forEach(function (select) {
        const updateProductCardFromVariant = function () {
            const productId = select.dataset.productId;
            const fallbackImage = select.dataset.fallbackImage || "php.png";
            const selectedOption = select.options[select.selectedIndex];

            if (!selectedOption) {
                return;
            }

            const image = selectedOption.dataset.image || fallbackImage;
            const price = selectedOption.dataset.price || "";
            const stock = parseInt(selectedOption.dataset.stock || "0", 10);
            const active = selectedOption.dataset.active === "1";
            const available = active && stock > 0;

            const img = document.getElementById("shop-product-image-" + productId);
            const priceNode = document.getElementById("shop-product-price-" + productId);
            const stockBadge = document.getElementById("shop-product-stock-badge-" + productId);
            const stockHint = document.getElementById("shop-product-stock-hint-" + productId);
            const submitBtn = document.getElementById("shop-product-submit-" + productId);
            const submitLabel = submitBtn ? submitBtn.querySelector("[data-shop-submit-label]") : null;
            const quantityInput = select.closest("form")?.querySelector('input[name="quantity"]');

            if (img) {
                img.src = baseUrl + "/public/img/" + image;
            }

            if (priceNode && price) {
                priceNode.textContent = price;
            }

            if (stockBadge) {
                stockBadge.textContent = available ? "En stock" : "Rupture";
                stockBadge.classList.toggle("in_stock", available);
                stockBadge.classList.toggle("out_stock", !available);
            }

            if (stockHint) {
                const stockLabel = stock + " unité" + (stock > 1 ? "s" : "") + " disponible" + (stock > 1 ? "s" : "");
                stockHint.textContent = available ? stockLabel : "Cette variante est indisponible";
                stockHint.classList.toggle("is_available", available);
                stockHint.classList.toggle("is_unavailable", !available);
            }

            if (quantityInput) {
                quantityInput.max = String(Math.max(1, stock));
                quantityInput.disabled = !available;

                if (Number(quantityInput.value || 1) > stock) {
                    quantityInput.value = String(Math.max(1, stock));
                }

                quantityInput.dispatchEvent(new Event("input", { bubbles: true }));
            }

            if (submitBtn) {
                submitBtn.disabled = !available;
                submitBtn.dataset.defaultLabel = available ? "Ajouter au panier" : "Indisponible";

                if (submitLabel) {
                    submitLabel.textContent = submitBtn.dataset.defaultLabel;
                } else {
                    submitBtn.textContent = submitBtn.dataset.defaultLabel;
                }
            }
        };

        select.addEventListener("change", updateProductCardFromVariant);
        updateProductCardFromVariant();
    });
});

/* CONTROLES DE QUANTITE BORNES */
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-quantity-control]").forEach(function (control) {
        const input = control.querySelector('input[type="number"]');
        const minusButton = control.querySelector("[data-qty-minus]");
        const plusButton = control.querySelector("[data-qty-plus]");

        if (!input || !minusButton || !plusButton) {
            return;
        }

        function getBounds() {
            const min = Number(input.min || 1);
            const parsedMax = Number(input.max || Number.MAX_SAFE_INTEGER);

            return {
                min: Number.isFinite(min) ? min : 1,
                max: Number.isFinite(parsedMax) ? parsedMax : Number.MAX_SAFE_INTEGER
            };
        }

        function normalizeQuantity() {
            const bounds = getBounds();
            const parsedValue = Number.parseInt(input.value || String(bounds.min), 10);
            const nextValue = Math.min(bounds.max, Math.max(bounds.min, Number.isFinite(parsedValue) ? parsedValue : bounds.min));

            input.value = String(nextValue);
            minusButton.disabled = input.disabled || nextValue <= bounds.min;
            plusButton.disabled = input.disabled || nextValue >= bounds.max;
        }

        function changeQuantity(delta) {
            normalizeQuantity();
            input.value = String(Number(input.value) + delta);
            normalizeQuantity();
            input.dispatchEvent(new Event("change", { bubbles: true }));
        }

        minusButton.addEventListener("click", function () {
            changeQuantity(-1);
        });

        plusButton.addEventListener("click", function () {
            changeQuantity(1);
        });

        input.addEventListener("input", normalizeQuantity);
        input.addEventListener("change", normalizeQuantity);
        normalizeQuantity();
    });
});

/* ADMIN PAYMENT */
document.addEventListener("DOMContentLoaded", function () {
    const userSearchInput = document.getElementById("user_search");
    const resetSearchBtn = document.getElementById("paym_reset_user_search");
    const userSelect = document.getElementById("user_id");

    if (!userSearchInput || !resetSearchBtn || !userSelect) {
        return;
    }

    const initialOptions = Array.from(userSelect.options).map(function (option, index) {
        return {
            value: option.value,
            text: option.textContent,
            search: (option.dataset.userLabel || option.textContent || "").toLowerCase(),
            selected: option.selected,
            isPlaceholder: index === 0
        };
    });

    function rebuildOptions(term) {
        const normalizedTerm = (term || "").trim().toLowerCase();
        const currentValue = userSelect.value;

        userSelect.innerHTML = "";

        initialOptions.forEach(function (item) {
            if (item.isPlaceholder) {
                const placeholder = document.createElement("option");
                placeholder.value = item.value;
                placeholder.textContent = item.text;
                userSelect.appendChild(placeholder);
                return;
            }

            if (normalizedTerm !== "" && !item.search.includes(normalizedTerm)) {
                return;
            }

            const option = document.createElement("option");
            option.value = item.value;
            option.textContent = item.text;
            userSelect.appendChild(option);
        });

        const hasCurrentValue = Array.from(userSelect.options).some(function (option) {
            return option.value === currentValue;
        });

        if (hasCurrentValue) {
            userSelect.value = currentValue;
        } else {
            userSelect.selectedIndex = 0;
        }

        if (userSelect.options.length === 1) {
            const noResultOption = document.createElement("option");
            noResultOption.value = "";
            noResultOption.textContent = "Aucun utilisateur trouvé";
            noResultOption.disabled = true;
            userSelect.appendChild(noResultOption);
            userSelect.selectedIndex = 0;
        }
    }

    userSearchInput.addEventListener("input", function () {
        rebuildOptions(userSearchInput.value);
    });

    resetSearchBtn.addEventListener("click", function () {
        userSearchInput.value = "";
        rebuildOptions("");
        userSearchInput.focus();
    });
});

/* ADMIN FACTURATION UTILISATEUR CIBLÉE */
document.addEventListener("DOMContentLoaded", function () {
    const billingDirectory = document.querySelector("[data-billing-directory]");

    if (billingDirectory) {
        const searchInput = billingDirectory.querySelector("[data-billing-user-search]");
        const resetButton = billingDirectory.querySelector("[data-billing-search-reset]");
        const userCards = Array.from(billingDirectory.querySelectorAll("[data-billing-user-card]"));
        const visibleCount = billingDirectory.querySelector("[data-billing-visible-count]");
        const emptyState = billingDirectory.querySelector("[data-billing-directory-empty]");

        function normalizeBillingSearch(value) {
            return String(value || "")
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .trim();
        }

        function filterBillingUsers() {
            const term = normalizeBillingSearch(searchInput ? searchInput.value : "");
            let count = 0;

            userCards.forEach(function (card) {
                const cardSearch = normalizeBillingSearch(card.dataset.billingSearch || "");
                const matchesSearch = term === "" || cardSearch.includes(term);
                const isVisible = matchesSearch;

                card.hidden = !isVisible;
                if (isVisible) {
                    count += 1;
                }
            });

            if (visibleCount) {
                visibleCount.textContent = String(count);
            }

            if (emptyState) {
                emptyState.hidden = count !== 0;
            }

            if (resetButton) {
                resetButton.hidden = term === "";
            }
        }

        if (searchInput) {
            searchInput.addEventListener("input", filterBillingUsers);
        }

        if (resetButton && searchInput) {
            resetButton.addEventListener("click", function () {
                searchInput.value = "";
                filterBillingUsers();
                searchInput.focus();
            });
        }

        filterBillingUsers();
    }

    const billUserSearchInput = document.getElementById("user_search");
    const billUserResetBtn = document.getElementById("reset_user_search");
    const billUserSelect = document.getElementById("user_id");

    if (billUserSearchInput && billUserResetBtn && billUserSelect) {
        const initialOptions = Array.from(billUserSelect.options).map(function (option, index) {
            return {
                value: option.value,
                text: option.textContent,
                search: (option.dataset.userLabel || option.textContent || "").toLowerCase(),
                disabled: option.disabled,
                isPlaceholder: index === 0
            };
        });

        function rebuildBillUserOptions(term) {
            const normalizedTerm = (term || "").trim().toLowerCase();
            const currentValue = billUserSelect.value;

            billUserSelect.innerHTML = "";

            initialOptions.forEach(function (item) {
                if (item.isPlaceholder) {
                    const placeholder = document.createElement("option");
                    placeholder.value = "";
                    placeholder.textContent = item.text;
                    billUserSelect.appendChild(placeholder);
                    return;
                }

                if (normalizedTerm !== "" && !item.search.includes(normalizedTerm)) {
                    return;
                }

                const option = document.createElement("option");
                option.value = item.value;
                option.textContent = item.text;
                option.disabled = item.disabled;
                billUserSelect.appendChild(option);
            });

            const hasCurrentValue = Array.from(billUserSelect.options).some(function (option) {
                return option.value === currentValue;
            });

            if (hasCurrentValue) {
                billUserSelect.value = currentValue;
            } else {
                billUserSelect.selectedIndex = 0;
            }

            if (billUserSelect.options.length === 1) {
                const emptyOption = document.createElement("option");
                emptyOption.value = "";
                emptyOption.textContent = "Aucun utilisateur trouvé";
                emptyOption.disabled = true;
                billUserSelect.appendChild(emptyOption);
                billUserSelect.selectedIndex = 0;
            }
        }

        billUserSearchInput.addEventListener("input", function () {
            rebuildBillUserOptions(billUserSearchInput.value);
        });

        billUserResetBtn.addEventListener("click", function () {
            billUserSearchInput.value = "";
            rebuildBillUserOptions("");
            billUserSearchInput.focus();
        });
    }

    const chargeLinesWrapper = document.getElementById("charge_lines_wrapper");
    const addChargeLineBtn = document.getElementById("add_charge_line_btn");
    const customChargeLinesWrapper = document.getElementById("custom_charge_lines_wrapper");
    const addCustomChargeLineBtn = document.getElementById("add_custom_charge_line_btn");
    const multiChargeForm = document.getElementById("multi_charge_form");
    const billingComposer = document.querySelector("[data-billing-composer]");
    const billingModeInputs = billingComposer
        ? Array.from(billingComposer.querySelectorAll("[data-billing-mode-choice]"))
        : [];
    const billingPanels = billingComposer
        ? Array.from(billingComposer.querySelectorAll("[data-billing-panel]"))
        : [];
    const summaryMode = billingComposer
        ? billingComposer.querySelector("[data-billing-summary-mode]")
        : null;
    const summaryLines = billingComposer
        ? billingComposer.querySelector("[data-billing-summary-lines]")
        : null;
    const estimatedTotal = multiChargeForm
        ? multiChargeForm.querySelector("[data-charge-estimated-total]")
        : null;

    function parseChargeAmount(value) {
        const parsed = Number(String(value || "").replace(",", "."));
        return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    }

    function updateChargeEstimatedTotal() {
        if (!multiChargeForm || !estimatedTotal) {
            return;
        }

        let total = 0;

        let completedLines = 0;

        multiChargeForm.querySelectorAll("[data-charge-line]").forEach(function (line) {
            const select = line.querySelector('select[name="variant_id[]"]');
            const quantity = line.querySelector('input[name="quantity[]"]');
            const selectedOption = select && select.selectedIndex >= 0
                ? select.options[select.selectedIndex]
                : null;
            const price = selectedOption && select && !select.disabled
                ? parseChargeAmount(selectedOption.dataset.price)
                : 0;
            const count = quantity ? Math.max(0, parseInt(quantity.value || "0", 10) || 0) : 0;

            total += price * count;
            if (select && !select.disabled && select.value !== "" && count > 0) {
                completedLines += 1;
            }
        });

        multiChargeForm.querySelectorAll("[data-custom-charge-amount]").forEach(function (input) {
            if (!input.disabled) {
                total += parseChargeAmount(input.value);

                const line = input.closest("[data-custom-charge-line]");
                const label = line ? line.querySelector("[data-custom-charge-label]") : null;
                if (label && label.value.trim() !== "" && parseChargeAmount(input.value) > 0) {
                    completedLines += 1;
                }
            }
        });

        estimatedTotal.textContent = total.toLocaleString("fr-FR", {
            style: "currency",
            currency: "EUR"
        });

        if (summaryLines) {
            summaryLines.textContent = String(completedLines);
        }
    }

    function renumberBillingLines(wrapper, selector) {
        if (!wrapper) {
            return;
        }

        wrapper.querySelectorAll(selector).forEach(function (line, index) {
            const number = line.querySelector("[data-billing-line-number]");
            if (number) {
                number.textContent = String(index + 1).padStart(2, "0");
            }
        });
    }

    function renderBillingMode() {
        if (!billingComposer || billingModeInputs.length === 0) {
            return;
        }

        const selected = billingModeInputs.find(function (input) {
            return input.checked;
        });
        const mode = selected ? selected.value : "custom";
        const labels = {
            custom: "Montant libre",
            product: "Produits du catalogue",
            mixed: "Facturation mixte"
        };

        billingModeInputs.forEach(function (input) {
            const card = input.closest(".billing_mode_card");
            if (card) {
                card.classList.toggle("is_active", input === selected);
            }
        });

        billingPanels.forEach(function (panel) {
            const panelType = panel.dataset.billingPanel || "";
            const isVisible = mode === "mixed" || mode === panelType;

            panel.hidden = !isVisible;
            panel.querySelectorAll("input, select, textarea, button").forEach(function (field) {
                field.disabled = !isVisible;
            });
        });

        if (summaryMode) {
            summaryMode.textContent = labels[mode] || labels.custom;
        }

        updateChargeEstimatedTotal();
    }

    function updateCustomChargeRequirements(line) {
        const label = line.querySelector("[data-custom-charge-label]");
        const amount = line.querySelector("[data-custom-charge-amount]");

        if (!label || !amount) {
            return;
        }

        label.required = amount.value.trim() !== "";
        amount.required = label.value.trim() !== "";
    }

    if (chargeLinesWrapper && addChargeLineBtn) {
        function updateRemoveButtons() {
            const lines = chargeLinesWrapper.querySelectorAll("[data-charge-line]");

            lines.forEach(function (line) {
                const button = line.querySelector("[data-remove-charge-line]");
                if (button) {
                    button.disabled = lines.length === 1;
                }
            });
        }

        function bindRemoveButtons(scope) {
            const buttons = scope.querySelectorAll("[data-remove-charge-line]");

            buttons.forEach(function (button) {
                button.addEventListener("click", function () {
                    const lines = chargeLinesWrapper.querySelectorAll("[data-charge-line]");

                    if (lines.length <= 1) {
                        return;
                    }

                    const line = button.closest("[data-charge-line]");
                    if (line) {
                        line.remove();
                        updateRemoveButtons();
                        renumberBillingLines(chargeLinesWrapper, "[data-charge-line]");
                        updateChargeEstimatedTotal();
                    }
                });
            });
        }

        addChargeLineBtn.addEventListener("click", function () {
            const firstLine = chargeLinesWrapper.querySelector("[data-charge-line]");

            if (!firstLine) {
                return;
            }

            const clone = firstLine.cloneNode(true);

            clone.querySelectorAll("select").forEach(function (select) {
                select.selectedIndex = 0;
            });

            clone.querySelectorAll("input").forEach(function (input) {
                if (input.type === "number") {
                    input.value = "1";
                }
            });

            chargeLinesWrapper.appendChild(clone);
            bindRemoveButtons(clone);
            updateRemoveButtons();
            renumberBillingLines(chargeLinesWrapper, "[data-charge-line]");
            updateChargeEstimatedTotal();
        });

        bindRemoveButtons(chargeLinesWrapper);
        updateRemoveButtons();
        renumberBillingLines(chargeLinesWrapper, "[data-charge-line]");
    }

    if (customChargeLinesWrapper && addCustomChargeLineBtn) {
        function updateCustomRemoveButtons() {
            const lines = customChargeLinesWrapper.querySelectorAll("[data-custom-charge-line]");

            lines.forEach(function (line) {
                const button = line.querySelector("[data-remove-custom-charge-line]");
                if (button) {
                    button.disabled = lines.length === 1;
                }
            });
        }

        function bindCustomChargeLine(line) {
            const button = line.querySelector("[data-remove-custom-charge-line]");
            const fields = line.querySelectorAll("[data-custom-charge-label], [data-custom-charge-amount]");

            fields.forEach(function (field) {
                field.addEventListener("input", function () {
                    updateCustomChargeRequirements(line);
                    updateChargeEstimatedTotal();
                });
            });

            if (button) {
                button.addEventListener("click", function () {
                    const lines = customChargeLinesWrapper.querySelectorAll("[data-custom-charge-line]");

                    if (lines.length <= 1) {
                        return;
                    }

                    line.remove();
                    updateCustomRemoveButtons();
                    renumberBillingLines(customChargeLinesWrapper, "[data-custom-charge-line]");
                    updateChargeEstimatedTotal();
                });
            }

            updateCustomChargeRequirements(line);
        }

        addCustomChargeLineBtn.addEventListener("click", function () {
            const firstLine = customChargeLinesWrapper.querySelector("[data-custom-charge-line]");

            if (!firstLine) {
                return;
            }

            const clone = firstLine.cloneNode(true);
            clone.querySelectorAll("input").forEach(function (input) {
                input.value = "";
                input.required = false;
            });

            customChargeLinesWrapper.appendChild(clone);
            bindCustomChargeLine(clone);
            updateCustomRemoveButtons();
            renumberBillingLines(customChargeLinesWrapper, "[data-custom-charge-line]");
        });

        customChargeLinesWrapper.querySelectorAll("[data-custom-charge-line]").forEach(bindCustomChargeLine);
        updateCustomRemoveButtons();
        renumberBillingLines(customChargeLinesWrapper, "[data-custom-charge-line]");
    }

    if (multiChargeForm) {
        multiChargeForm.addEventListener("input", updateChargeEstimatedTotal);
        multiChargeForm.addEventListener("change", updateChargeEstimatedTotal);
        multiChargeForm.addEventListener("submit", function (event) {
            const selectedModeInput = billingModeInputs.find(function (input) {
                return input.checked;
            });
            const selectedMode = selectedModeInput ? selectedModeInput.value : "mixed";
            const hasProductLine = Array.from(
                multiChargeForm.querySelectorAll('select[name="variant_id[]"]')
            ).some(function (select) {
                return !select.disabled && select.value !== "";
            });
            const hasCustomLine = Array.from(
                multiChargeForm.querySelectorAll("[data-custom-charge-line]")
            ).some(function (line) {
                const label = line.querySelector("[data-custom-charge-label]");
                const amount = line.querySelector("[data-custom-charge-amount]");
                return label && amount && !amount.disabled
                    && label.value.trim() !== ""
                    && parseChargeAmount(amount.value) > 0;
            });
            let errorMessage = "";

            if (selectedMode === "product" && !hasProductLine) {
                errorMessage = "Sélectionne au moins un produit à facturer.";
            } else if (selectedMode === "custom" && !hasCustomLine) {
                errorMessage = "Renseigne au moins un libellé et un montant libre.";
            } else if (selectedMode === "mixed" && (!hasProductLine || !hasCustomLine)) {
                errorMessage = "La facturation mixte doit contenir au moins un produit et un montant libre.";
            } else if (!hasProductLine && !hasCustomLine) {
                errorMessage = "Ajoute au moins un produit ou un montant libre à facturer.";
            }

            if (errorMessage !== "") {
                event.preventDefault();
                event.stopImmediatePropagation();
                window.alert(errorMessage);
            }
        });

        billingModeInputs.forEach(function (input) {
            input.addEventListener("change", renderBillingMode);
        });

        renderBillingMode();
        updateChargeEstimatedTotal();
    }

    document.querySelectorAll("form[data-confirm-message]").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            const message = form.getAttribute("data-confirm-message");

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});

/* SHOP — AJOUT PANIER AJAX / QUICK CART */
document.addEventListener("DOMContentLoaded", function () {
    const quickCart = document.querySelector("[data-shop-quick-cart]");
    const toastStack = document.querySelector("[data-shop-toast-stack]");
    const addForms = document.querySelectorAll("[data-shop-add-form]");
    const headerCartBadges = document.querySelectorAll(".cart_count_badge");
    const quickCartItemsList = quickCart ? quickCart.querySelector("[data-shop-cart-items]") : null;
    const quickCartToggle = quickCart ? quickCart.querySelector("[data-shop-cart-toggle]") : null;
    const quickCartCount = quickCart ? quickCart.querySelector("[data-shop-cart-count]") : null;
    const quickCartSubtotal = quickCart ? quickCart.querySelector("[data-shop-cart-subtotal]") : null;
    const quickCartCheckoutBtn = quickCart ? quickCart.querySelector(".shop_quick_cart_checkout_btn") : null;
    const quickCartRemoveUrl = quickCart ? (quickCart.dataset.removeUrl || "index.php?controller=shop&action=removeCartItem") : "";
    const quickCartCsrfToken = quickCart ? (quickCart.dataset.csrfToken || "") : "";
    const quickCartStorageKey = "cksgo_quick_cart_collapsed";
    const shopBaseUrl = getBaseUrl();

    let isQuickCartCollapsed = false;

    try {
        isQuickCartCollapsed = window.localStorage.getItem(quickCartStorageKey) === "1";
    } catch (error) {
        isQuickCartCollapsed = false;
    }

    function pluralizeArticle(count) {
        return count === 1 ? "article" : "articles";
    }

    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function isDesktopViewport() {
        return window.innerWidth > 768;
    }

    function showToast(message, type) {
        if (!toastStack || !message) {
            return;
        }

        const toast = document.createElement("div");
        toast.className = "shop_toast " + (type === "error" ? "shop_toast_error" : "shop_toast_success");
        toast.textContent = message;
        toastStack.appendChild(toast);

        window.setTimeout(function () {
            toast.classList.add("is_leaving");

            window.setTimeout(function () {
                toast.remove();
            }, 220);
        }, 2200);
    }

    function bumpQuickCart() {
        if (!quickCart) {
            return;
        }

        quickCart.classList.remove("is_bumped");

        window.requestAnimationFrame(function () {
            quickCart.classList.add("is_bumped");

            window.setTimeout(function () {
                quickCart.classList.remove("is_bumped");
            }, 260);
        });
    }

    function flashAddedState(form) {
        const card = form.closest(".product_card");
        const submitBtn = form.querySelector('button[type="submit"]');
        const qtyInput = form.querySelector('input[name="quantity"]');

        if (card) {
            card.classList.add("is_recently_added");

            window.setTimeout(function () {
                card.classList.remove("is_recently_added");
            }, 1200);
        }

        if (submitBtn) {
            const defaultLabel = submitBtn.dataset.defaultLabel || submitBtn.textContent;
            const addedLabel = submitBtn.dataset.addedLabel || "Ajouté";
            const submitLabel = submitBtn.querySelector("[data-shop-submit-label]");

            if (submitLabel) {
                submitLabel.textContent = addedLabel;
            } else {
                submitBtn.textContent = addedLabel;
            }

            window.setTimeout(function () {
                if (submitLabel) {
                    submitLabel.textContent = defaultLabel;
                } else {
                    submitBtn.textContent = defaultLabel;
                }
            }, 1100);
        }

        if (qtyInput) {
            qtyInput.value = "1";
        }
    }

    function updateHeaderBadges(itemCount) {
        headerCartBadges.forEach(function (badge) {
            badge.textContent = String(itemCount);
            badge.hidden = itemCount <= 0;

            const cartLink = badge.closest("a");
            if (cartLink) {
                cartLink.setAttribute(
                    "aria-label",
                    `Panier, ${itemCount} article${itemCount > 1 ? "s" : ""}`
                );
            }
        });
    }

    function getQuickCartTotalLines() {
        if (!quickCart) {
            return 0;
        }

        return Number(quickCart.dataset.totalLines || 0);
    }

    function syncQuickCartCollapse(totalLines) {
        if (!quickCart) {
            return;
        }

        const canCollapse = totalLines > 0;
        const shouldCollapse = canCollapse && isDesktopViewport() && isQuickCartCollapsed;

        quickCart.classList.toggle("is_collapsed", shouldCollapse);

        if (quickCartToggle) {
            const shouldShowToggle = canCollapse && isDesktopViewport();

            quickCartToggle.hidden = !shouldShowToggle;
            quickCartToggle.classList.toggle("is_hidden", !shouldShowToggle);
            quickCartToggle.textContent = shouldCollapse ? "+" : "−";
            quickCartToggle.setAttribute("aria-expanded", shouldCollapse ? "false" : "true");
            quickCartToggle.setAttribute("aria-label", shouldCollapse ? "Déplier le panier rapide" : "Réduire le panier rapide");
            quickCartToggle.setAttribute("title", shouldCollapse ? "Déplier le panier rapide" : "Réduire le panier rapide");
        }
    }

    function renderQuickCartItems(items) {
        if (!quickCartItemsList) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            quickCartItemsList.innerHTML = '<li class="shop_quick_cart_item shop_quick_cart_item_empty"><span>Ton panier est encore vide.</span><small>Ajoute un produit pour commencer.</small></li>';
            return;
        }

        quickCartItemsList.innerHTML = items.map(function (item) {
            const productName = escapeHtml(item.product_name || "");
            const displayVariant = escapeHtml(item.display_variant || "Variante");
            const quantity = escapeHtml(item.quantity || 0);
            const cartItemId = escapeHtml(item.cart_item_id || 0);
            const productImage = escapeHtml(item.product_image || "php.png");
            const lineTotal = escapeHtml(item.line_total_formatted || "");
            const removeLabel = "Retirer " + (item.product_name || "ce produit") + " du panier";

            return '' +
                '<li class="shop_quick_cart_item" data-cart-item-id="' + cartItemId + '">' +
                '<div class="shop_quick_cart_thumb"><img src="' + shopBaseUrl + '/public/img/' + productImage + '" alt="" loading="lazy"></div>' +
                '<div class="shop_quick_cart_item_main">' +
                "<strong>" + productName + "</strong>" +
                "<span>" + displayVariant + " · ×" + quantity + "</span>" +
                "</div>" +
                '<div class="shop_quick_cart_item_side">' +
                '<span class="shop_quick_cart_item_qty">' + lineTotal + "</span>" +
                '<button type="button" class="shop_quick_cart_item_remove" data-shop-cart-remove="' + cartItemId + '" aria-label="' + escapeHtml(removeLabel) + '" title="Retirer ce produit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16"></path><path d="M9 7V4h6v3"></path><path d="m7 7 1 13h8l1-13"></path></svg></button>' +
                "</div>" +
                "</li>";
        }).join("");
    }

    function updateQuickCart(cart) {
        if (!quickCart || !cart) {
            return;
        }

        const itemCount = Number(cart.item_count || 0);
        const totalLines = Number(cart.total_lines || 0);

        quickCart.dataset.totalLines = String(totalLines);
        quickCart.classList.toggle("is_empty", itemCount <= 0);
        quickCart.classList.toggle("has_items", itemCount > 0);

        if (quickCartCount) {
            quickCartCount.textContent = itemCount + " " + pluralizeArticle(itemCount);
        }

        if (quickCartSubtotal) {
            quickCartSubtotal.textContent = cart.subtotal_formatted || "0,00 €";
        }

        if (quickCartCheckoutBtn) {
            quickCartCheckoutBtn.disabled = itemCount <= 0;
        }

        updateHeaderBadges(itemCount);
        renderQuickCartItems(cart.items || []);
        syncQuickCartCollapse(totalLines);
        bumpQuickCart();
    }

    function sendCartRequest(url, formData) {
        return fetch(url, {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            },
            credentials: "same-origin"
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    message: "Réponse serveur invalide."
                };
            });
        });
    }

    if (quickCartToggle) {
        quickCartToggle.addEventListener("click", function () {
            isQuickCartCollapsed = !isQuickCartCollapsed;

            try {
                window.localStorage.setItem(quickCartStorageKey, isQuickCartCollapsed ? "1" : "0");
            } catch (error) {
                // no-op
            }

            syncQuickCartCollapse(getQuickCartTotalLines());
        });
    }

    if (quickCart) {
        syncQuickCartCollapse(getQuickCartTotalLines());

        quickCart.addEventListener("click", function (event) {
            const removeButton = event.target.closest("[data-shop-cart-remove]");

            if (!removeButton) {
                return;
            }

            event.preventDefault();

            const cartItemId = Number(removeButton.getAttribute("data-shop-cart-remove") || 0);

            if (!cartItemId || removeButton.disabled) {
                return;
            }

            removeButton.disabled = true;

            const formData = new FormData();
            formData.append("cart_item_id", String(cartItemId));

            if (quickCartCsrfToken) {
                formData.append("csrf_token", quickCartCsrfToken);
            }

            sendCartRequest(quickCartRemoveUrl, formData)
                .then(function (payload) {
                    if (payload.redirect_url) {
                        window.location.href = payload.redirect_url;
                        return;
                    }

                    if (!payload.success) {
                        showToast(payload.message || "Impossible de retirer ce produit.", "error");
                        return;
                    }

                    if (payload.cart) {
                        updateQuickCart(payload.cart);
                    }

                    showToast(payload.message || "Produit retiré du panier.", "success");
                })
                .catch(function () {
                    showToast("Une erreur réseau est survenue.", "error");
                })
                .finally(function () {
                    if (document.body.contains(removeButton)) {
                        removeButton.disabled = false;
                    }
                });
        });

        window.addEventListener("resize", function () {
            syncQuickCartCollapse(getQuickCartTotalLines());
        });
    }

    addForms.forEach(function (form) {
        form.addEventListener("submit", function (event) {
            event.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');

            if (submitBtn && submitBtn.disabled) {
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
            }

            const formData = new FormData(form);

            sendCartRequest(form.action, formData)
                .then(function (payload) {
                    if (payload.redirect_url) {
                        window.location.href = payload.redirect_url;
                        return;
                    }

                    if (!payload.success) {
                        showToast(payload.message || "Impossible d’ajouter ce produit.", "error");
                        return;
                    }

                    if (payload.cart) {
                        updateQuickCart(payload.cart);
                    }

                    flashAddedState(form);
                    showToast(payload.message || "Produit ajouté au panier.", "success");
                })
                .catch(function () {
                    showToast("Une erreur réseau est survenue.", "error");
                })
                .finally(function () {
                    if (submitBtn) {
                        const selectedOption = form.querySelector('select[name="variant_id"] option:checked');
                        const variantIsActive = selectedOption ? selectedOption.dataset.active === "1" : true;
                        const variantHasStock = selectedOption ? Number(selectedOption.dataset.stock || 0) > 0 : true;
                        submitBtn.disabled = !(variantIsActive && variantHasStock);
                    }
                });
        });
    });
});

/* SHOP ALERT — MODALE GLOBALE */
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.querySelector("[data-shop-alert-modal]");
    const triggers = Array.from(document.querySelectorAll("[data-shop-alert-open]"));

    if (!modal || !triggers.length) {
        return;
    }

    const dialog = modal.querySelector("[data-shop-alert-dialog]");
    const backdrop = modal.querySelector("[data-shop-alert-backdrop]");
    const closeButtons = Array.from(modal.querySelectorAll("[data-shop-alert-close]"));
    const productIdInput = modal.querySelector("[data-shop-alert-product-id]");
    const productNameNode = modal.querySelector("[data-shop-alert-product-name]");
    const variantField = modal.querySelector("[data-shop-alert-variant-field]");
    const variantSelect = modal.querySelector("[data-shop-alert-variant-select]");
    const messageField = modal.querySelector('textarea[name="message"]');
    const typeSelect = modal.querySelector('select[name="type"]');
    const firstFocusable = modal.querySelector("[data-shop-alert-close]");
    const animationDuration = 180;

    let activeTrigger = null;
    let closeTimer = null;

    function isModalOpen() {
        return modal.classList.contains("is_open");
    }

    function setScrollLock(shouldLock) {
        document.documentElement.classList.toggle("shop_alert_locked", shouldLock);
        document.body.classList.toggle("shop_alert_locked", shouldLock);
    }

    function resetModalFields() {
        if (productIdInput) {
            productIdInput.value = "";
        }

        if (productNameNode) {
            productNameNode.textContent = "Produit";
        }

        if (variantSelect) {
            variantSelect.innerHTML = "";
        }

        if (variantField) {
            variantField.hidden = true;
        }

        if (typeSelect) {
            typeSelect.value = "missing_product";
        }

        if (messageField) {
            messageField.value = "";
        }
    }

    function hydrateVariantSelect(trigger) {
        if (!variantSelect || !variantField) {
            return;
        }

        const optionsId = trigger.getAttribute("data-alert-options-id");
        const optionsTemplate = optionsId ? document.getElementById(optionsId) : null;

        variantSelect.innerHTML = "";

        if (!optionsTemplate) {
            variantField.hidden = true;
            return;
        }

        variantSelect.innerHTML = optionsTemplate.innerHTML.trim();

        const productCard = trigger.closest(".product_card");
        const productVariantSelect = productCard ? productCard.querySelector(".shop_variant_select") : null;
        const selectedVariantValue = productVariantSelect ? productVariantSelect.value : "";
        const hasMatchingOption = selectedVariantValue
            ? Array.from(variantSelect.options).some(function (option) {
                return option.value === selectedVariantValue;
            })
            : false;

        if (hasMatchingOption) {
            variantSelect.value = selectedVariantValue;
        } else if (variantSelect.options.length > 0) {
            variantSelect.selectedIndex = 0;
        }

        variantField.hidden = variantSelect.options.length === 0;
    }

    function openModal(trigger) {
        activeTrigger = trigger;

        if (productIdInput) {
            productIdInput.value = trigger.getAttribute("data-alert-product-id") || "";
        }

        if (productNameNode) {
            productNameNode.textContent = trigger.getAttribute("data-alert-product-name") || "Produit";
        }

        hydrateVariantSelect(trigger);

        modal.hidden = false;
        window.clearTimeout(closeTimer);

        window.requestAnimationFrame(function () {
            modal.classList.add("is_open");
            setScrollLock(true);

            if (firstFocusable) {
                firstFocusable.focus();
            }
        });
    }

    function closeModal(restoreFocus) {
        if (!isModalOpen() && modal.hidden) {
            return;
        }

        modal.classList.remove("is_open");
        setScrollLock(false);

        window.clearTimeout(closeTimer);
        closeTimer = window.setTimeout(function () {
            modal.hidden = true;
            resetModalFields();

            if (restoreFocus && activeTrigger) {
                activeTrigger.focus();
            }

            activeTrigger = null;
        }, animationDuration);
    }

    triggers.forEach(function (trigger) {
        trigger.addEventListener("click", function (event) {
            event.preventDefault();
            openModal(trigger);
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener("click", function (event) {
            event.preventDefault();
            closeModal(true);
        });
    });

    if (backdrop) {
        backdrop.addEventListener("click", function () {
            closeModal(true);
        });
    }

    modal.addEventListener("click", function (event) {
        if (!dialog) {
            return;
        }

        if (dialog.contains(event.target)) {
            return;
        }

        closeModal(true);
    });

    document.addEventListener("keydown", function (event) {
        if (!isModalOpen()) {
            return;
        }

        if (event.key === "Escape") {
            event.preventDefault();
            closeModal(true);
        }
    });

    window.addEventListener("beforeunload", function () {
        setScrollLock(false);
    });
});

/* PAGINATION ADMIN ADAPTIVE */
document.addEventListener("DOMContentLoaded", function () {
    const paginations = document.querySelectorAll('.apl_pagination[data-pagination="adaptive"]');

    if (!paginations.length) {
        return;
    }

    function buildPaginationItems(currentPage, totalPages, maxVisible) {
        const safeCurrent = Math.max(1, currentPage);
        const safeTotal = Math.max(1, totalPages);
        const safeMax = Math.max(3, maxVisible);

        if (safeTotal <= safeMax) {
            return Array.from({ length: safeTotal }, function (_, index) {
                return index + 1;
            });
        }

        const items = [1];
        const innerVisible = Math.max(1, safeMax - 2);
        let start = Math.max(2, safeCurrent - Math.floor(innerVisible / 2));
        let end = start + innerVisible - 1;

        if (end > safeTotal - 1) {
            end = safeTotal - 1;
            start = Math.max(2, end - innerVisible + 1);
        }

        if (start <= 2) {
            start = 2;
            end = Math.min(safeTotal - 1, start + innerVisible - 1);
        }

        if (start > 2) {
            items.push("ellipsis");
        }

        for (let page = start; page <= end; page += 1) {
            items.push(page);
        }

        if (end < safeTotal - 1) {
            items.push("ellipsis");
        }

        items.push(safeTotal);

        return items;
    }

    function buildHref(template, page) {
        return template.replace("__PAGE__", String(page));
    }

    function createPageLink(page, currentPage, template) {
        const link = document.createElement("a");
        link.className = "apl_page_link" + (page === currentPage ? " is_active" : "");
        link.href = buildHref(template, page);
        link.textContent = String(page);

        if (page === currentPage) {
            link.setAttribute("aria-current", "page");
        }

        return link;
    }

    function createNavLink(label, className, targetPage, template) {
        const link = document.createElement("a");
        link.className = "apl_page_link apl_page_link_nav " + className;
        link.href = buildHref(template, targetPage);
        link.textContent = label;
        return link;
    }

    function createEllipsis() {
        const ellipsis = document.createElement("span");
        ellipsis.className = "apl_page_ellipsis";
        ellipsis.setAttribute("aria-hidden", "true");
        ellipsis.textContent = "…";
        return ellipsis;
    }

    function renderPagination(nav) {
        const currentPage = parseInt(nav.dataset.currentPage || "1", 10);
        const totalPages = parseInt(nav.dataset.totalPages || "1", 10);
        const maxDesktop = parseInt(nav.dataset.maxDesktop || "5", 10);
        const maxMobile = parseInt(nav.dataset.maxMobile || "3", 10);
        const template = nav.dataset.pageTemplate || "";
        const maxVisible = window.innerWidth <= 768 ? maxMobile : maxDesktop;

        if (!template || totalPages <= 1) {
            return;
        }

        const items = buildPaginationItems(currentPage, totalPages, maxVisible);
        nav.innerHTML = "";

        if (currentPage > 1) {
            nav.appendChild(createNavLink("Précédent", "apl_page_link_prev", currentPage - 1, template));
        }

        items.forEach(function (item) {
            if (item === "ellipsis") {
                nav.appendChild(createEllipsis());
                return;
            }

            nav.appendChild(createPageLink(item, currentPage, template));
        });

        if (currentPage < totalPages) {
            nav.appendChild(createNavLink("Suivant", "apl_page_link_next", currentPage + 1, template));
        }
    }

    let resizeTimer = null;

    function renderAllPaginations() {
        paginations.forEach(renderPagination);
    }

    renderAllPaginations();

    window.addEventListener("resize", function () {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(renderAllPaginations, 120);
    });
});

/* DESCRIPTION DU RÔLE SÉLECTIONNÉ */
document.addEventListener("DOMContentLoaded", function () {
    const roleSelect = document.querySelector('select[name="role"]');
    const roleHelp = document.querySelector("[data-role-help]");

    if (!roleSelect || !roleHelp) {
        return;
    }

    const title = roleHelp.querySelector("[data-role-help-title]");
    const description = roleHelp.querySelector("[data-role-help-description]");

    function updateRoleHelp() {
        const option = roleSelect.options[roleSelect.selectedIndex];

        if (!option) {
            return;
        }

        if (title) {
            title.textContent = option.dataset.roleLabel || option.textContent.trim();
        }

        if (description) {
            description.textContent = option.dataset.roleDescription || "";
        }
    }

    roleSelect.addEventListener("change", updateRoleHelp);
    updateRoleHelp();
});

/* MATRICE DE PERMISSIONS UTILISATEUR */
document.addEventListener("DOMContentLoaded", function () {
    const editor = document.querySelector("[data-permission-editor]");
    const roleSelect = document.querySelector('select[name="role"]');

    if (!editor || !roleSelect) {
        return;
    }

    let rolePermissionMap = {};

    try {
        rolePermissionMap = JSON.parse(editor.dataset.rolePermissionMap || "{}");
    } catch (error) {
        rolePermissionMap = {};
    }

    const rows = Array.from(editor.querySelectorAll("[data-permission-row]"));

    function updatePermissionRow(row) {
        const permission = row.dataset.permissionRow || "";
        const role = roleSelect.value || "user";
        const select = row.querySelector("[data-permission-select]");
        const roleState = row.querySelector("[data-role-state]");
        const effectiveState = row.querySelector("[data-effective-state]");
        const baseAllowed = Boolean(rolePermissionMap[role] && rolePermissionMap[role][permission]);

        if (roleState) {
            roleState.textContent = baseAllowed ? "Inclus dans le rôle" : "Non inclus";
            roleState.classList.toggle("is_allowed", baseAllowed);
            roleState.classList.toggle("is_denied", !baseAllowed);
        }

        if (!select) {
            return;
        }

        select.disabled = role === "admin" || select.dataset.canAdminister !== "1";

        const effect = select.value || "inherit";
        const effectiveAllowed = effect === "allow" || (effect !== "deny" && baseAllowed);

        if (effectiveState) {
            effectiveState.textContent = effectiveAllowed ? "Accès actif" : "Accès refusé";
            effectiveState.classList.toggle("is_allowed", effectiveAllowed);
            effectiveState.classList.toggle("is_denied", !effectiveAllowed);
        }
    }

    function refreshPermissionMatrix() {
        rows.forEach(updatePermissionRow);
    }

    rows.forEach(function (row) {
        const select = row.querySelector("[data-permission-select]");
        if (select) {
            select.addEventListener("change", function () {
                updatePermissionRow(row);
            });
        }
    });

    roleSelect.addEventListener("change", refreshPermissionMatrix);
    refreshPermissionMatrix();
});

/* PARCOURS D'ENCAISSEMENT UNIFIE */
document.addEventListener("DOMContentLoaded", function () {
    const workflow = document.querySelector("[data-payment-workflow]");

    if (!workflow) {
        return;
    }

    workflow.dataset.paymentEnhanced = "1";

    const modeInputs = Array.from(workflow.querySelectorAll('input[name="payment_mode"]'));
    const orderPanel = workflow.querySelector("[data-payment-orders-panel]");
    const freePanel = workflow.querySelector("[data-payment-free-panel]");
    const orderInputs = Array.from(workflow.querySelectorAll('input[name="order_ids[]"]'));
    const selectAll = workflow.querySelector("[data-payment-select-all]");
    const amountInput = workflow.querySelector('input[name="amount"]');
    const countValue = workflow.querySelector("[data-payment-count]");
    const countLabel = workflow.querySelector("[data-payment-count-label]");
    const totalValue = workflow.querySelector("[data-payment-total]");
    const balanceValue = workflow.querySelector("[data-payment-balance]");
    const hint = workflow.querySelector("[data-payment-hint]");
    const submitButton = workflow.querySelector("[data-payment-submit]");
    const dialog = document.querySelector("[data-payment-dialog]");
    const dialogDescription = dialog ? dialog.querySelector("[data-payment-dialog-description]") : null;
    const dialogBalance = dialog ? dialog.querySelector("[data-payment-dialog-balance]") : null;
    const dialogConfirm = dialog ? dialog.querySelector("[data-payment-dialog-confirm]") : null;
    const initialBalanceCents = parseInt(workflow.dataset.balanceCents || "0", 10);
    const orderDueCents = parseInt(workflow.dataset.orderDueCents || "0", 10);

    function formatMoney(cents) {
        return new Intl.NumberFormat("fr-FR", {
            style: "currency",
            currency: "EUR"
        }).format(cents / 100);
    }

    function getMode() {
        const checked = modeInputs.find(function (input) {
            return input.checked;
        });

        return checked ? checked.value : "free";
    }

    function getState() {
        const mode = getMode();
        const selectedOrders = orderInputs.filter(function (input) {
            return input.checked;
        });
        const selectedTotalCents = selectedOrders.reduce(function (total, input) {
            return total + parseInt(input.dataset.dueCents || "0", 10);
        }, 0);
        const freeValue = amountInput ? Number(String(amountInput.value || "").replace(",", ".")) : 0;
        const freeCents = Number.isFinite(freeValue) ? Math.round(freeValue * 100) : 0;
        const totalCents = mode === "orders" ? selectedTotalCents : freeCents;

        return {
            mode: mode,
            selectedOrders: selectedOrders,
            totalCents: totalCents,
            balanceAfterCents: initialBalanceCents - totalCents,
            valid: totalCents > 0 && (mode !== "orders" || selectedOrders.length > 0)
        };
    }

    function describeBalance(cents) {
        if (cents < 0) {
            return "Avoir de " + formatMoney(Math.abs(cents));
        }

        if (cents > 0) {
            return formatMoney(cents) + " à régler";
        }

        return "Compte soldé";
    }

    function updateSelectAll() {
        if (!selectAll || orderInputs.length === 0) {
            return;
        }

        const selectedCount = orderInputs.filter(function (input) {
            return input.checked;
        }).length;

        selectAll.checked = selectedCount === orderInputs.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < orderInputs.length;
    }

    function render() {
        const state = getState();
        const ordersMode = state.mode === "orders";

        if (orderPanel) {
            orderPanel.hidden = !ordersMode;
        }
        if (freePanel) {
            freePanel.hidden = state.mode !== "free";
        }
        if (amountInput) {
            amountInput.required = state.mode === "free";
        }

        if (countLabel) {
            countLabel.textContent = ordersMode ? "Commandes" : "Affectation";
        }
        if (countValue) {
            countValue.textContent = ordersMode
                ? String(state.selectedOrders.length)
                : (state.balanceAfterCents < 0
                    ? "Commandes + avoir"
                    : (state.totalCents > orderDueCents ? "Commandes + ancienne note" : "Plus anciennes d’abord"));
        }
        if (totalValue) {
            totalValue.textContent = formatMoney(state.totalCents);
        }
        if (balanceValue) {
            balanceValue.textContent = describeBalance(state.balanceAfterCents);
            balanceValue.classList.toggle("is_credit", state.balanceAfterCents < 0);
        }
        if (submitButton) {
            submitButton.disabled = !state.valid;
        }
        if (hint) {
            if (!state.valid) {
                hint.textContent = ordersMode
                    ? "Sélectionne au moins une commande."
                    : "Saisis le montant reçu.";
            } else if (state.balanceAfterCents < 0) {
                hint.textContent = "Après validation, le compte disposera d’un avoir de " + formatMoney(Math.abs(state.balanceAfterCents)) + ".";
            } else if (state.balanceAfterCents === 0) {
                hint.textContent = "Ce paiement soldera entièrement le compte.";
            } else {
                hint.textContent = "Il restera " + formatMoney(state.balanceAfterCents) + " à régler sur le compte.";
            }
        }

        updateSelectAll();
    }

    modeInputs.forEach(function (input) {
        input.addEventListener("change", render);
    });
    orderInputs.forEach(function (input) {
        input.addEventListener("change", render);
    });
    if (amountInput) {
        amountInput.addEventListener("input", render);
    }
    if (selectAll) {
        selectAll.addEventListener("change", function () {
            orderInputs.forEach(function (input) {
                input.checked = selectAll.checked;
            });
            render();
        });
    }

    workflow.addEventListener("submit", function (event) {
        if (workflow.dataset.confirmed === "1") {
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = "Encaissement en cours...";
            }
            return;
        }

        event.preventDefault();
        const state = getState();

        if (!state.valid) {
            render();
            return;
        }

        const modeDescription = state.mode === "orders"
            ? state.selectedOrders.length + " commande(s) pour " + formatMoney(state.totalCents)
            : "un montant libre de " + formatMoney(state.totalCents);

        if (dialog && typeof dialog.showModal === "function") {
            if (dialogDescription) {
                dialogDescription.textContent = "Tu vas enregistrer " + modeDescription + ".";
            }
            if (dialogBalance) {
                dialogBalance.textContent = "Nouveau solde : " + describeBalance(state.balanceAfterCents);
                dialogBalance.classList.toggle("is_credit", state.balanceAfterCents < 0);
            }
            dialog.showModal();
            return;
        }

        if (window.confirm("Confirmer " + modeDescription + " ?")) {
            workflow.dataset.confirmed = "1";
            workflow.requestSubmit();
        }
    });

    if (dialogConfirm) {
        dialogConfirm.addEventListener("click", function () {
            workflow.dataset.confirmed = "1";
            if (dialog) {
                dialog.close();
            }
            workflow.requestSubmit();
        });
    }

    render();
});

(() => {
    const automaticForms = document.querySelectorAll([
        "[data-auto-filter-form]",
        ".user_directory_filters",
        ".admin_catalog_search_form",
        ".payflow_search",
        ".admin_list_toolbar_form"
    ].join(","));

    automaticForms.forEach((form) => {
        if ((form.getAttribute("method") || "get").toLowerCase() !== "get") return;

        let timer = null;
        form.classList.add("is_enhanced");

        const rememberFocus = (field) => {
            try {
                window.sessionStorage.setItem("cksgo-auto-filter-focus", JSON.stringify({
                    path: window.location.pathname,
                    controller: form.querySelector('[name="controller"]')?.value || "",
                    action: form.querySelector('[name="action"]')?.value || "",
                    name: field.name || "",
                    start: typeof field.selectionStart === "number" ? field.selectionStart : null,
                    end: typeof field.selectionEnd === "number" ? field.selectionEnd : null
                }));
            } catch (error) {
                // Le filtrage reste fonctionnel quand le stockage navigateur est indisponible.
            }
        };

        const submitFrom = (field, delay = 0) => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => {
                rememberFocus(field);
                form.requestSubmit();
            }, delay);
        };

        form.querySelectorAll("select, input[type='checkbox'], input[type='radio']").forEach((field) => {
            field.addEventListener("change", () => submitFrom(field));
        });

        form.querySelectorAll("input[type='search'], input[name='q'], input[name='user_search']").forEach((field) => {
            field.addEventListener("input", () => {
                window.clearTimeout(timer);
                submitFrom(field, 320);
            });
        });
    });

    try {
        const rawFocus = window.sessionStorage.getItem("cksgo-auto-filter-focus");
        if (rawFocus) {
            window.sessionStorage.removeItem("cksgo-auto-filter-focus");
            const state = JSON.parse(rawFocus);
            const form = Array.from(automaticForms).find((candidate) => (
                (candidate.querySelector('[name="controller"]')?.value || "") === state.controller
                && (candidate.querySelector('[name="action"]')?.value || "") === state.action
            ));
            const field = form?.querySelector(`[name="${window.CSS?.escape ? CSS.escape(state.name) : state.name}"]`);
            if (field) {
                field.focus({preventScroll: true});
                if (state.start !== null && typeof field.setSelectionRange === "function") {
                    field.setSelectionRange(state.start, state.end ?? state.start);
                }
            }
        }
    } catch (error) {
        // Aucun impact sur le fonctionnement des formulaires.
    }

    const editor = document.querySelector("[data-news-editor]");
    if (!editor) return;

    const title = editor.querySelector("#news-title");
    const summary = editor.querySelector("#news-summary");
    const content = editor.querySelector("#news-content");
    const category = editor.querySelector("#news-category");
    const published = editor.querySelector("[data-news-published]");
    const previewTitle = editor.querySelector("[data-news-preview-title]");
    const previewSummary = editor.querySelector("[data-news-preview-summary]");
    const previewCategory = editor.querySelector("[data-news-preview-category]");
    const stateLabel = document.querySelector("[data-news-state-label]");

    const update = () => {
        if (previewTitle) previewTitle.textContent = title?.value.trim() || "Titre de votre actualité";
        if (previewSummary) {
            const value = summary?.value.trim() || content?.value.trim() || "Le résumé apparaîtra ici pendant la rédaction.";
            previewSummary.textContent = value.length > 280 ? `${value.slice(0, 277)}…` : value;
        }
        if (previewCategory && category) previewCategory.textContent = category.options[category.selectedIndex]?.text || "Information";
        if (stateLabel && published) {
            stateLabel.textContent = published.checked ? "Publiée" : "Brouillon";
            stateLabel.classList.toggle("is_success", published.checked);
            stateLabel.classList.toggle("is_neutral", !published.checked);
        }
        [title, summary, content].forEach((field) => {
            if (!field) return;
            const counter = editor.querySelector(`[data-count-for="${field.id}"]`);
            if (counter) counter.textContent = String(field.value.length);
        });
    };

    [title, summary, content].forEach((field) => field?.addEventListener("input", update));
    category?.addEventListener("change", update);
    published?.addEventListener("change", update);
    update();
})();

(() => {
    const flashRegion = document.querySelector(".app_flash_region");
    if (!flashRegion || !flashRegion.textContent.trim()) return;

    flashRegion.setAttribute("role", "status");
    flashRegion.setAttribute("aria-atomic", "true");

    const dismiss = () => {
        if (flashRegion.classList.contains("is_leaving")) return;
        flashRegion.classList.add("is_leaving");
        window.setTimeout(() => flashRegion.remove(), 220);
    };

    flashRegion.addEventListener("click", dismiss);
    window.setTimeout(dismiss, 6000);
})();

(() => {
    document.querySelectorAll("[data-password-toggle]").forEach((button) => {
        button.addEventListener("click", () => {
            const input = document.getElementById(button.dataset.passwordToggle || "");
            if (!input) return;
            const reveal = input.type === "password";
            input.type = reveal ? "text" : "password";
            button.setAttribute("aria-label", reveal ? "Masquer le mot de passe" : "Afficher le mot de passe");
            button.classList.toggle("is_revealed", reveal);
        });
    });

    const passwordForm = document.querySelector("[data-password-form]");
    if (!passwordForm) return;

    const password = passwordForm.querySelector("[data-new-password]");
    const confirmation = passwordForm.querySelector("[data-confirm-password]");
    const strength = passwordForm.querySelector("[data-password-strength]");
    const strengthLabel = strength?.querySelector("small");

    const updatePasswordState = () => {
        const value = password?.value || "";
        let score = 0;
        if (value.length >= 15) score++;
        if (value.length >= 20) score++;
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
        if (/\d/.test(value) && /[^\w\s]/.test(value)) score++;

        strength?.setAttribute("data-score", String(score));
        if (strengthLabel) {
            strengthLabel.textContent = value === ""
                ? "Saisissez un nouveau mot de passe."
                : ["Très faible", "Faible", "Correct", "Bon", "Très bon"][score];
        }

        if (confirmation) {
            confirmation.setCustomValidity(
                confirmation.value !== "" && confirmation.value !== value
                    ? "Les mots de passe ne correspondent pas."
                    : ""
            );
        }
    };

    password?.addEventListener("input", updatePasswordState);
    confirmation?.addEventListener("input", updatePasswordState);
})();

(() => {
    document.querySelectorAll("[data-product-visibility]").forEach((group) => {
        const guests = group.querySelector("[data-visible-to-guests]");
        const staffOnly = group.querySelector("[data-staff-only]");
        if (!guests || !staffOnly) return;

        const sync = () => {
            if (staffOnly.checked) guests.checked = false;
            guests.disabled = staffOnly.checked;
            group.classList.toggle("is_staff_only", staffOnly.checked);
        };

        staffOnly.addEventListener("change", sync);
        sync();
    });

    const banType = document.querySelector("[data-ban-type]");
    const banValue = document.querySelector("[data-ban-value]");
    if (banType && banValue) {
        const syncBanPlaceholder = () => {
            banValue.placeholder = banType.value === "ip" ? "192.0.2.25" : "utilisateur@exemple.fr";
            banValue.inputMode = banType.value === "ip" ? "decimal" : "email";
        };
        banType.addEventListener("change", syncBanPlaceholder);
        syncBanPlaceholder();
    }
})();

(() => {
    document.querySelectorAll("[data-inventory-adjust-form]").forEach((form) => {
        const mode = form.querySelector("[data-inventory-mode]");
        const quantity = form.querySelector("[data-inventory-quantity]");
        const preview = form.querySelector("[data-inventory-preview]");
        const currentStock = Number.parseInt(form.dataset.currentStock || "0", 10);

        const renderPreview = () => {
            const enteredQuantity = Math.max(0, Number.parseInt(quantity?.value || "0", 10) || 0);
            let nextStock = currentStock;

            if (mode?.value === "increase") nextStock += enteredQuantity;
            if (mode?.value === "decrease") nextStock -= enteredQuantity;
            if (mode?.value === "set") nextStock = enteredQuantity;

            if (preview) {
                preview.textContent = `Stock ${currentStock} → ${nextStock}`;
                preview.classList.toggle("is_invalid", nextStock < 0);
            }
        };

        mode?.addEventListener("change", renderPreview);
        quantity?.addEventListener("input", renderPreview);
        renderPreview();
    });
})();

document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-alert-item-picker]").forEach(function (picker) {
        const form = picker.closest("form");
        const selectAll = picker.querySelector("[data-alert-select-all]");
        const items = Array.from(picker.querySelectorAll("[data-alert-item]"));
        const hint = picker.querySelector("[data-alert-selection-hint]");

        if (!form || !selectAll || items.length === 0) return;

        const sync = function () {
            const selectedCount = items.filter((item) => item.checked).length;
            selectAll.checked = selectedCount === items.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < items.length;
            selectAll.setCustomValidity(selectedCount > 0 ? "" : "Sélectionne au moins un produit.");
            picker.classList.toggle("has_selection_error", selectedCount === 0);
            if (hint) {
                hint.textContent = selectedCount > 0
                    ? `${selectedCount} produit${selectedCount > 1 ? "s" : ""} sélectionné${selectedCount > 1 ? "s" : ""}.`
                    : "Choisis un, plusieurs ou tous les produits.";
            }
        };

        selectAll.addEventListener("change", function () {
            items.forEach((item) => {
                item.checked = selectAll.checked;
            });
            sync();
        });
        items.forEach((item) => item.addEventListener("change", sync));
        form.addEventListener("submit", function (event) {
            sync();
            if (!items.some((item) => item.checked)) {
                event.preventDefault();
                selectAll.reportValidity();
            }
        });
        sync();
    });

    document.querySelectorAll("[data-alert-refund-picker]").forEach(function (form) {
        const selectAll = form.querySelector("[data-alert-refund-select-all]");
        const items = Array.from(form.querySelectorAll("[data-alert-refund-item]"));
        const hint = form.querySelector("[data-alert-refund-hint]");

        if (!selectAll || items.length === 0) return;

        const sync = function () {
            const selectedItems = items.filter((item) => item.checked);
            selectAll.checked = selectedItems.length === items.length;
            selectAll.indeterminate = selectedItems.length > 0 && selectedItems.length < items.length;
            selectAll.setCustomValidity(selectedItems.length > 0 ? "" : "Sélectionne au moins un produit à rembourser.");

            items.forEach((item) => {
                const candidate = item.closest(".alert_refund_candidate");
                const quantity = candidate?.querySelector("[data-alert-refund-quantity]");
                if (quantity) {
                    quantity.disabled = !item.checked;
                    quantity.required = item.checked;
                }
                candidate?.classList.toggle("is_unselected", !item.checked);
            });

            if (hint) {
                hint.textContent = selectedItems.length > 0
                    ? `${selectedItems.length} ligne${selectedItems.length > 1 ? "s" : ""} ${selectedItems.length > 1 ? "seront" : "sera"} remboursée${selectedItems.length > 1 ? "s" : ""}.`
                    : "Aucune ligne sélectionnée.";
            }
        };

        selectAll.addEventListener("change", function () {
            items.forEach((item) => {
                item.checked = selectAll.checked;
            });
            sync();
        });
        items.forEach((item) => item.addEventListener("change", sync));
        form.addEventListener("submit", function (event) {
            sync();
            if (!items.some((item) => item.checked)) {
                event.preventDefault();
                selectAll.reportValidity();
            }
        });
        sync();
    });
});
