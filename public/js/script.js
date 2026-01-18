/* MENU BURGER */
document.addEventListener("DOMContentLoaded", function () {
    const burger = document.getElementById("burger");
    const navbar = document.getElementById("main_navbar");

    burger.addEventListener("click", function () {
        navbar.classList.toggle("show");
        navbar.classList.toggle("hide");

        // Ajout ou suppression de l’état “ouvert” du burger
        burger.classList.toggle("open");

        // Désactiver le scroll du body si menu ouvert
        document.body.classList.toggle("no-scroll");
    });
});

/* Liens cliquables dans la liste des utilisateurs */
document.addEventListener('click', (e) => {
    const row = e.target.closest('tr.user-row');
    if (!row) return;

    const isActionClick = e.target.closest('.col-actions');
    const isInteractive = e.target.closest('a, button, input, select, textarea, label');

    if (isActionClick || isInteractive) {
        e.stopPropagation();
        return;
    }

    const href = row.dataset.href;
    if (href) window.location.href = href;
});
