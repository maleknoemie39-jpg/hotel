(() => {
    "use strict";

    const evenements = JSON.parse(
        document.querySelector("#donnees-evenements").textContent
    );
    const donnees = {
        evenements,
        jourSelectionne: dateEnTexte(new Date())
    };
    const selectionner = (element) => document.querySelector(element);

    function dateEnTexte(date) {
        const annee = date.getFullYear();
        const mois = String(date.getMonth() + 1).padStart(2, "0");
        const jour = String(date.getDate()).padStart(2, "0");
        return `${annee}-${mois}-${jour}`;
    }

    function securiserTexte(texte) {
        return String(texte ?? "").replace(/[&<>"']/g, (caractere) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[caractere]);
    }

    function joursDuPlanning() {
        const debut = new Date();
        debut.setHours(0, 0, 0, 0);

        return Array.from({ length: 7 }, (_, index) => {
            const date = new Date(debut);
            date.setDate(debut.getDate() + index);
            return date;
        });
    }

    function afficherDate(date, options) {
        return new Intl.DateTimeFormat("fr-FR", options).format(date);
    }

    function afficherJours() {
        const jours = joursDuPlanning();

        selectionner("#choix-jour").innerHTML = jours.map((date, index) => {
            const dateTexte = dateEnTexte(date);
            const nombre = donnees.evenements.filter(
                (evenement) => evenement.debut.slice(0, 10) === dateTexte
            ).length;
            const nomJour = index === 0
                ? "Aujourd’hui"
                : afficherDate(date, { weekday: "short" });

            return `
                <button
                    type="button"
                    class="bouton-jour${dateTexte === donnees.jourSelectionne ? " actif" : ""}"
                    role="tab"
                    aria-selected="${dateTexte === donnees.jourSelectionne}"
                    aria-controls="liste-evenements"
                    data-date="${dateTexte}">
                    <span class="nom-jour">${securiserTexte(nomJour)}</span>
                    <strong>${securiserTexte(
                        afficherDate(date, { day: "numeric", month: "short" })
                    )}</strong>
                    <span class="nombre-evenements">${
                        nombre
                            ? `${nombre} ${nombre > 1 ? "animations" : "animation"}`
                            : "Aucune animation"
                    }</span>
                </button>
            `;
        }).join("");
    }

    function statutEvenement(evenement) {
        const debut = new Date(`${evenement.debut}:00`);
        const fin = new Date(`${evenement.fin}:00`);
        const maintenant = new Date();

        if (maintenant >= debut && maintenant < fin) {
            return "En cours";
        }

        if (
            evenement.debut.slice(0, 10) === dateEnTexte(maintenant)
            && maintenant < debut
        ) {
            const evenementEnCours = donnees.evenements.some((autre) =>
                autre.debut.slice(0, 10) === evenement.debut.slice(0, 10)
                && autre.debut < evenement.debut
                && new Date(`${autre.fin}:00`) > maintenant
            );

            if (!evenementEnCours) {
                return "Ensuite";
            }
        }

        return "";
    }

    function afficherEvenements() {
        const evenementsDuJour = donnees.evenements
            .filter((evenement) =>
                evenement.debut.slice(0, 10) === donnees.jourSelectionne
            )
            .sort((a, b) => a.debut.localeCompare(b.debut));
        const date = new Date(`${donnees.jourSelectionne}T12:00:00`);
        const aujourdHui = dateEnTexte(new Date()) === donnees.jourSelectionne;

        selectionner("#date-selectionnee").textContent = aujourdHui
            ? `Aujourd’hui · ${afficherDate(date, {
                weekday: "long",
                day: "numeric",
                month: "long"
            })}`
            : afficherDate(date, {
                weekday: "long",
                day: "numeric",
                month: "long"
            });

        selectionner("#nombre-evenements").textContent =
            `${evenementsDuJour.length} ${
                evenementsDuJour.length > 1 ? "animations" : "animation"
            }`;

        if (evenementsDuJour.length === 0) {
            selectionner("#liste-evenements").innerHTML = `
                <div class="aucun-evenement">
                    <strong>Aucune animation prévue</strong>
                    <p>Consultez une autre journée du planning.</p>
                </div>
            `;
            return;
        }

        selectionner("#liste-evenements").innerHTML = evenementsDuJour.map((evenement) => {
            const statut = statutEvenement(evenement);
            const places = Number(evenement.places_disponibles);

            return `
                <article class="carte-evenement${statut ? " evenement-suivant" : ""}">
                    <div class="horaire-evenement">
                        <strong>${securiserTexte(evenement.debut.slice(11, 16))}</strong>
                        <span>à ${securiserTexte(evenement.fin.slice(11, 16))}</span>
                    </div>
                    <div class="details-evenement">
                        <div class="informations-evenement">
                            <span class="etiquette">${securiserTexte(evenement.type)}</span>
                            ${statut ? `
                                <span class="statut-evenement">
                                    <i></i>${securiserTexte(statut)}
                                </span>
                            ` : ""}
                        </div>
                        <h4>${securiserTexte(evenement.titre)}</h4>
                        <p>${securiserTexte(evenement.description)}</p>
                        <div class="informations-supplementaires">
                            <span>Jeu : ${securiserTexte(evenement.jeux || "À préciser")}</span>
                            <span>Salle : ${securiserTexte(evenement.salle || "À préciser")}</span>
                        </div>
                    </div>
                    <div class="places-disponibles">
                        <strong>${places}</strong>
                        <span>
                            place${places > 1 ? "s" : ""}
                            restante${places > 1 ? "s" : ""}
                        </span>
                    </div>
                </article>
            `;
        }).join("");
    }

    selectionner("#choix-jour").addEventListener("click", (evenement) => {
        const bouton = evenement.target.closest("[data-date]");
        if (!bouton) return;

        donnees.jourSelectionne = bouton.dataset.date;
        afficherJours();
        afficherEvenements();
    });

    afficherJours();
    afficherEvenements();
})();
