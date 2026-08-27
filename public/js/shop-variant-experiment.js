document.addEventListener("DOMContentLoaded", function () {
    const shop = document.querySelector(".shop_variant_experiment");

    if (!shop) {
        return;
    }

    const cards = Array.from(shop.querySelectorAll("[data-shop-variant-card]"));
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

    function applyExpandedState(card, expanded) {
        const toggle = card.querySelector("[data-shop-variant-toggle]");
        const panel = card.querySelector("[data-shop-variant-panel]");

        card.classList.toggle("is_variant_expanded", expanded);

        if (!expanded) {
            card.classList.remove("has_mobile_docked_actions");
        }

        if (toggle) {
            toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
        }

        if (panel) {
            panel.setAttribute("aria-hidden", expanded ? "false" : "true");
        }

        scheduleDockedActionsUpdate();
    }

    function setExpanded(card, expanded) {
        const panel = card.querySelector("[data-shop-variant-panel]");

        if (!panel || reducedMotion.matches) {
            panel?.shopVariantCancel?.();
            card.classList.remove("is_variant_animating");
            applyExpandedState(card, expanded);
            return Promise.resolve(true);
        }

        panel.shopVariantCancel?.();

        return new Promise(function (resolve) {
            const duration = expanded ? 260 : 190;
            let settled = false;
            let timeoutId = null;

            function finish(completed) {
                if (settled) {
                    return;
                }

                settled = true;
                window.clearTimeout(timeoutId);
                panel.removeEventListener("transitionend", onTransitionEnd);
                panel.shopVariantCancel = null;
                panel.style.removeProperty("height");
                panel.style.removeProperty("opacity");
                panel.style.removeProperty("overflow");
                panel.style.removeProperty("transform");
                panel.style.removeProperty("transition");
                panel.style.removeProperty("--shop-variant-duration");
                card.classList.remove("is_variant_animating");

                if (completed) {
                    applyExpandedState(card, expanded);
                }

                resolve(completed);
            }

            function onTransitionEnd(event) {
                if (event.target === panel && event.propertyName === "height") {
                    finish(true);
                }
            }

            panel.shopVariantCancel = function () {
                finish(false);
            };

            card.classList.add("is_variant_animating");
            panel.style.setProperty("--shop-variant-duration", duration + "ms");
            panel.style.overflow = "hidden";
            panel.style.transition = "none";

            if (expanded) {
                applyExpandedState(card, true);
                panel.style.height = "0px";
                panel.style.opacity = "0";
                panel.style.transform = "translateY(-6px)";
            } else {
                panel.style.height = panel.getBoundingClientRect().height + "px";
                panel.style.opacity = "1";
                panel.style.transform = "translateY(0)";
                applyExpandedState(card, false);
            }

            panel.getBoundingClientRect();
            panel.style.removeProperty("transition");
            panel.addEventListener("transitionend", onTransitionEnd);

            window.requestAnimationFrame(function () {
                if (settled) {
                    return;
                }

                panel.style.height = expanded ? panel.scrollHeight + "px" : "0px";
                panel.style.opacity = expanded ? "1" : "0";
                panel.style.transform = expanded ? "translateY(0)" : "translateY(-4px)";
            });

            timeoutId = window.setTimeout(function () {
                finish(true);
            }, duration + 90);
        });
    }

    function keepExpandedCardInView(card) {
        const cardRect = card.getBoundingClientRect();
        const viewportBottom = window.innerHeight - 20;

        if (cardRect.bottom <= viewportBottom && cardRect.top >= 12) {
            return;
        }

        const topGap = window.innerWidth <= 720 ? 12 : 20;
        const targetTop = Math.max(0, window.scrollY + cardRect.top - topGap);

        window.scrollTo({
            top: targetTop,
            behavior: reducedMotion.matches ? "auto" : "smooth"
        });
    }

    let dockedActionsFrame = null;

    function updateDockedActions() {
        dockedActionsFrame = null;
        const isMobile = window.innerWidth <= 720;

        cards.forEach(function (card) {
            const rect = card.getBoundingClientRect();
            const shouldDock = isMobile
                && card.classList.contains("is_variant_expanded")
                && rect.bottom > 88
                && rect.top < window.innerHeight - 72;

            card.classList.toggle("has_mobile_docked_actions", shouldDock);
        });
    }

    function scheduleDockedActionsUpdate() {
        if (dockedActionsFrame !== null) {
            return;
        }

        dockedActionsFrame = window.requestAnimationFrame(updateDockedActions);
    }

    function getOptionLabel(option) {
        if (!option) {
            return "Variante";
        }

        return String(option.textContent || "Variante")
            .split("—")[0]
            .trim() || "Variante";
    }

    function syncCardSelection(card) {
        const select = card.querySelector(".shop_variant_select");
        const summary = card.querySelector("[data-shop-selected-variant-label]");
        const choices = Array.from(card.querySelectorAll("[data-shop-variant-choice]"));

        if (!select) {
            return;
        }

        const selectedOption = select.options[select.selectedIndex] || null;
        const selectedValue = selectedOption ? selectedOption.value : "";
        const matchingChoice = choices.find(function (choice) {
            return choice.dataset.variantId === selectedValue;
        }) || null;

        choices.forEach(function (choice) {
            const isSelected = choice === matchingChoice;
            choice.classList.toggle("is_selected", isSelected);
            choice.setAttribute("aria-selected", isSelected ? "true" : "false");
        });

        if (summary) {
            summary.textContent = matchingChoice?.dataset.variantLabel || getOptionLabel(selectedOption);
        }
    }

    cards.forEach(function (card) {
        const toggle = card.querySelector("[data-shop-variant-toggle]");
        const select = card.querySelector(".shop_variant_select");
        const choices = Array.from(card.querySelectorAll("[data-shop-variant-choice]"));

        if (toggle) {
            toggle.addEventListener("click", function () {
                const shouldExpand = !card.classList.contains("is_variant_expanded");

                if (shouldExpand) {
                    cards.forEach(function (otherCard) {
                        if (otherCard !== card) {
                            setExpanded(otherCard, false);
                        }
                    });
                }

                setExpanded(card, shouldExpand).then(function (completed) {
                    if (completed && shouldExpand) {
                        keepExpandedCardInView(card);
                    }
                });
            });
        }

        choices.forEach(function (choice) {
            choice.addEventListener("click", function () {
                if (!select || choice.disabled) {
                    return;
                }

                select.value = choice.dataset.variantId || "";
                select.dispatchEvent(new Event("change", { bubbles: true }));
                syncCardSelection(card);
            });
        });

        if (select) {
            select.addEventListener("change", function () {
                syncCardSelection(card);
            });
        }

        syncCardSelection(card);
    });

    window.addEventListener("scroll", scheduleDockedActionsUpdate, { passive: true });
    window.addEventListener("resize", scheduleDockedActionsUpdate);
    scheduleDockedActionsUpdate();
});
