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