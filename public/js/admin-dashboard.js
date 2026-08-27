document.addEventListener("DOMContentLoaded", function () {
    const dashboard = document.querySelector("[data-admin-dashboard]");
    const dialog = document.getElementById("dashboard_preferences");
    const customizeButton = dashboard?.querySelector("[data-dashboard-customize]");

    if (!dashboard || !dialog || !customizeButton) {
        return;
    }

    const storageKey = "cksgo.admin.dashboard.preferences.v1";
    const defaults = {
        finance: true,
        "quick-actions": true,
        compact: false,
    };

    function readPreferences() {
        try {
            const stored = JSON.parse(window.localStorage.getItem(storageKey) || "{}");

            return Object.keys(defaults).reduce(function (preferences, key) {
                preferences[key] = typeof stored[key] === "boolean" ? stored[key] : defaults[key];
                return preferences;
            }, {});
        } catch (error) {
            return { ...defaults };
        }
    }

    function writePreferences(preferences) {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(preferences));
        } catch (error) {
            // La personnalisation reste fonctionnelle pour la session courante.
        }
    }

    function applyPreferences(preferences) {
        dashboard.classList.toggle("is_compact", preferences.compact);

        dashboard.querySelectorAll("[data-dashboard-panel]").forEach(function (panel) {
            const panelName = panel.getAttribute("data-dashboard-panel");
            panel.hidden = preferences[panelName] === false;
        });

        dialog.querySelectorAll("[data-dashboard-preference]").forEach(function (input) {
            const preferenceName = input.getAttribute("data-dashboard-preference");
            input.checked = preferences[preferenceName] !== false;
        });

        const secondaryGrid = dashboard.querySelector(".dashboard_secondary_grid");
        if (secondaryGrid) {
            const panels = Array.from(secondaryGrid.querySelectorAll("[data-dashboard-panel]"));
            secondaryGrid.hidden = panels.length > 0 && panels.every(function (panel) {
                return panel.hidden;
            });
        }
    }

    let preferences = readPreferences();
    applyPreferences(preferences);
    dashboard.classList.add("is_customizable");

    customizeButton.addEventListener("click", function () {
        if (typeof dialog.showModal === "function") {
            dialog.showModal();
            return;
        }

        dialog.setAttribute("open", "");
    });

    dialog.querySelectorAll("[data-dashboard-preference]").forEach(function (input) {
        input.addEventListener("change", function () {
            const preferenceName = input.getAttribute("data-dashboard-preference");
            preferences[preferenceName] = input.checked;
            writePreferences(preferences);
            applyPreferences(preferences);
        });
    });

    dialog.querySelector("[data-dashboard-reset]")?.addEventListener("click", function () {
        preferences = { ...defaults };
        writePreferences(preferences);
        applyPreferences(preferences);
    });

    dialog.addEventListener("click", function (event) {
        const bounds = dialog.getBoundingClientRect();
        const outside = event.clientX < bounds.left
            || event.clientX > bounds.right
            || event.clientY < bounds.top
            || event.clientY > bounds.bottom;

        if (outside && typeof dialog.close === "function") {
            dialog.close();
        }
    });

    dialog.querySelector("form")?.addEventListener("submit", function () {
        if (typeof dialog.close !== "function") {
            dialog.removeAttribute("open");
        }
    });
});
