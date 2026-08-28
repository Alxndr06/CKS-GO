document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    const shop = document.querySelector(".shop_catalog_page");

    if (!shop) {
        return;
    }

    const cards = Array.from(shop.querySelectorAll("[data-shop-variant-card]"));
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    const cardStates = new WeakMap();
    const transitionDuration = 280;
    let expandedCard = null;
    let dockedActionsFrame = null;

    function getCardState(card) {
        let state = cardStates.get(card);

        if (state) {
            return state;
        }

        state = {
            toggle: card.querySelector("[data-shop-variant-toggle]"),
            panel: card.querySelector("[data-shop-variant-panel]"),
            select: card.querySelector(".shop_variant_select"),
            summary: card.querySelector("[data-shop-selected-variant-label]"),
            choices: Array.from(card.querySelectorAll("[data-shop-variant-choice]")),
            transitionToken: 0,
            layoutAnimation: null
        };
        cardStates.set(card, state);

        return state;
    }

    function captureCardPositions() {
        const positions = new Map();

        if (reducedMotion.matches) {
            return positions;
        }

        cards.forEach(function (card) {
            if (card.offsetParent !== null) {
                positions.set(card, card.getBoundingClientRect());
            }
        });

        return positions;
    }

    function animateCardReflow(previousPositions) {
        if (reducedMotion.matches || typeof Element.prototype.animate !== "function") {
            return;
        }

        cards.forEach(function (card) {
            const previous = previousPositions.get(card);

            if (!previous || card.offsetParent === null) {
                return;
            }

            const current = card.getBoundingClientRect();
            const deltaX = previous.left - current.left;
            const deltaY = previous.top - current.top;

            if (Math.abs(deltaX) < 1 && Math.abs(deltaY) < 1) {
                return;
            }

            const state = getCardState(card);
            state.layoutAnimation?.cancel();
            state.layoutAnimation = card.animate(
                [
                    { transform: `translate3d(${deltaX}px, ${deltaY}px, 0)` },
                    { transform: "translate3d(0, 0, 0)" }
                ],
                {
                    duration: 240,
                    easing: "cubic-bezier(.2, .75, .25, 1)"
                }
            );

            state.layoutAnimation.addEventListener("finish", function () {
                state.layoutAnimation = null;
            }, { once: true });
        });
    }

    function applyExpandedState(card, expanded) {
        const state = getCardState(card);
        card.classList.toggle("is_variant_expanded", expanded);

        if (!expanded) {
            card.classList.remove("has_mobile_docked_actions");
        }

        state.toggle?.setAttribute("aria-expanded", expanded ? "true" : "false");

        if (state.panel) {
            state.panel.setAttribute("aria-hidden", expanded ? "false" : "true");
            setPanelInteractivity(state.panel, expanded);
        }
    }

    function setPanelInteractivity(panel, interactive) {
        if ("inert" in panel) {
            panel.inert = !interactive;
            return;
        }

        panel.querySelectorAll("a[href], button, input, select, textarea, [tabindex]").forEach(function (element) {
            if (!interactive) {
                if (!element.hasAttribute("data-shop-previous-tabindex")) {
                    element.dataset.shopPreviousTabindex = element.getAttribute("tabindex") || "";
                }

                element.tabIndex = -1;
                return;
            }

            const previousTabIndex = element.dataset.shopPreviousTabindex;

            if (typeof previousTabIndex === "undefined") {
                return;
            }

            if (previousTabIndex === "") {
                element.removeAttribute("tabindex");
            } else {
                element.setAttribute("tabindex", previousTabIndex);
            }

            delete element.dataset.shopPreviousTabindex;
        });
    }

    function keepExpandedCardInView(card) {
        const cardRect = card.getBoundingClientRect();
        const viewportTop = 12;
        const viewportBottom = window.innerHeight - 20;

        if (cardRect.top >= viewportTop && cardRect.bottom <= viewportBottom) {
            return;
        }

        const topGap = window.innerWidth <= 720 ? 12 : 20;
        const targetTop = Math.max(0, window.scrollY + cardRect.top - topGap);

        window.scrollTo({
            top: targetTop,
            behavior: reducedMotion.matches ? "auto" : "smooth"
        });
    }

    function setExpanded(card, expanded, options) {
        const state = getCardState(card);
        const wasExpanded = card.classList.contains("is_variant_expanded");

        if (!state.panel || wasExpanded === expanded) {
            return;
        }

        const previousPositions = captureCardPositions();

        if (expanded && expandedCard && expandedCard !== card) {
            const previousState = getCardState(expandedCard);
            previousState.transitionToken += 1;
            applyExpandedState(expandedCard, false);
        }

        state.transitionToken += 1;
        const token = state.transitionToken;
        applyExpandedState(card, expanded);

        if (expanded) {
            syncCardSelection(card);
        }

        expandedCard = expanded ? card : (expandedCard === card ? null : expandedCard);

        window.requestAnimationFrame(function () {
            animateCardReflow(previousPositions);
            scheduleDockedActionsUpdate();
        });

        if (expanded) {
            window.setTimeout(function () {
                if (
                    state.transitionToken === token
                    && card.classList.contains("is_variant_expanded")
                ) {
                    keepExpandedCardInView(card);
                }
            }, reducedMotion.matches ? 0 : transitionDuration);
        } else if (options?.restoreFocus) {
            state.toggle?.focus({ preventScroll: true });
        }
    }

    function updateDockedActions() {
        dockedActionsFrame = null;

        if (!expandedCard) {
            return;
        }

        const isMobile = window.innerWidth <= 720;
        const rect = expandedCard.getBoundingClientRect();
        const shouldDock = isMobile
            && rect.bottom > 88
            && rect.top < window.innerHeight - 72;

        expandedCard.classList.toggle("has_mobile_docked_actions", shouldDock);
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
        const state = getCardState(card);

        if (!state.select) {
            return;
        }

        const selectedOption = state.select.options[state.select.selectedIndex] || null;
        const selectedValue = selectedOption ? selectedOption.value : "";
        const matchingChoice = state.choices.find(function (choice) {
            return choice.dataset.variantId === selectedValue;
        }) || null;
        const fallbackChoice = state.choices.find(function (choice) {
            return !choice.disabled;
        }) || null;

        const panelIsInteractive = card.classList.contains("is_variant_expanded");

        state.choices.forEach(function (choice) {
            const isSelected = choice === matchingChoice;
            choice.classList.toggle("is_selected", isSelected);
            choice.setAttribute("aria-selected", isSelected ? "true" : "false");
            choice.tabIndex = panelIsInteractive && choice === (matchingChoice || fallbackChoice) ? 0 : -1;
        });

        if (state.summary) {
            state.summary.textContent = matchingChoice?.dataset.variantLabel || getOptionLabel(selectedOption);
        }
    }

    function selectVariantChoice(choice) {
        const card = choice.closest("[data-shop-variant-card]");

        if (!card || choice.disabled) {
            return;
        }

        const state = getCardState(card);

        if (!state.select) {
            return;
        }

        state.select.value = choice.dataset.variantId || "";
        state.select.dispatchEvent(new Event("change", { bubbles: true }));
    }

    shop.addEventListener("click", function (event) {
        const toggle = event.target.closest("[data-shop-variant-toggle]");

        if (toggle && shop.contains(toggle)) {
            const card = toggle.closest("[data-shop-variant-card]");

            if (card) {
                setExpanded(card, !card.classList.contains("is_variant_expanded"));
            }

            return;
        }

        const choice = event.target.closest("[data-shop-variant-choice]");

        if (choice && shop.contains(choice)) {
            selectVariantChoice(choice);
        }
    });

    shop.addEventListener("change", function (event) {
        if (!event.target.matches(".shop_variant_select")) {
            return;
        }

        const card = event.target.closest("[data-shop-variant-card]");

        if (card) {
            syncCardSelection(card);
        }
    });

    shop.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && expandedCard) {
            event.preventDefault();
            setExpanded(expandedCard, false, { restoreFocus: true });
            return;
        }

        const choice = event.target.closest("[data-shop-variant-choice]");

        if (!choice || !["ArrowDown", "ArrowRight", "ArrowUp", "ArrowLeft", "Home", "End"].includes(event.key)) {
            return;
        }

        const card = choice.closest("[data-shop-variant-card]");
        const choices = card
            ? getCardState(card).choices.filter(function (item) { return !item.disabled; })
            : [];

        if (choices.length === 0) {
            return;
        }

        event.preventDefault();
        const currentIndex = Math.max(0, choices.indexOf(choice));
        let nextIndex = currentIndex;

        if (event.key === "Home") {
            nextIndex = 0;
        } else if (event.key === "End") {
            nextIndex = choices.length - 1;
        } else if (event.key === "ArrowDown" || event.key === "ArrowRight") {
            nextIndex = (currentIndex + 1) % choices.length;
        } else {
            nextIndex = (currentIndex - 1 + choices.length) % choices.length;
        }

        const nextChoice = choices[nextIndex];
        selectVariantChoice(nextChoice);
        nextChoice.focus();
    });

    cards.forEach(function (card) {
        const shouldRemainExpanded = expandedCard === null
            && card.classList.contains("is_variant_expanded");

        applyExpandedState(card, shouldRemainExpanded);

        if (shouldRemainExpanded) {
            expandedCard = card;
        }

        syncCardSelection(card);
    });

    window.addEventListener("scroll", scheduleDockedActionsUpdate, { passive: true });
    window.addEventListener("resize", scheduleDockedActionsUpdate, { passive: true });
    scheduleDockedActionsUpdate();
});
