(function () {
    "use strict";

    const blocDonnees = document.querySelector("#donnees-evenements");
    const donneesPlanning = JSON.parse(blocDonnees.textContent);
    const evenements = donneesPlanning.evenements;
    const choixJour = document.querySelector("#choix-jour");
    const titreDate = document.querySelector("#date-selectionnee");
    const compteurEvenements = document.querySelector("#compteur-evenements");
    const listeEvenements = document.querySelector("#liste-evenements");

    const aujourdHui = new Date();
    aujourdHui.setHours(12, 0, 0, 0);

    const formatJour = new Intl.DateTimeFormat("fr-FR", { weekday: "short" });
    const formatDateCourte = new Intl.DateTimeFormat("fr-FR", {
        day: "numeric",
        month: "short"
    });
    const formatDateLongue = new Intl.DateTimeFormat("fr-FR", {
        weekday: "long",
        day: "numeric",
        month: "long"
    });

    const jours = [];
    for (let numeroJour = 0; numeroJour < 7; numeroJour++) {
        const date = new Date(aujourdHui);
        date.setDate(aujourdHui.getDate() + numeroJour);
        jours.push(date);
    }

    let dateChoisie = dateEnCle(jours[0]);

    function dateEnCle(date) {
        const mois = String(date.getMonth() + 1).padStart(2, "0");
        const jour = String(date.getDate()).padStart(2, "0");
        return date.getFullYear() + "-" + mois + "-" + jour;
    }

    function texteSecurise(texte) {
        if (texte === null || texte === undefined) {
            texte = "";
        }

        const remplacements = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        };

        return String(texte).replace(/[&<>"']/g, function (caractere) {
            return remplacements[caractere];
        });
    }

    function nombreAnimations(nombre) {
        return nombre + (nombre > 1 ? " animations" : " animation");
    }

    function afficherJours() {
        let boutons = "";

        for (let index = 0; index < jours.length; index++) {
            const date = jours[index];
            const dateCle = dateEnCle(date);
            let nombre = 0;

            for (const evenement of evenements) {
                if (evenement.debut.slice(0, 10) === dateCle) {
                    nombre++;
                }
            }

            let nomJour = formatJour.format(date);
            if (index === 0) {
                nomJour = "Aujourd’hui";
            }

            const dateCourte = formatDateCourte.format(date);
            const dateLongue = formatDateLongue.format(date);
            let texteAccessible = dateLongue;

            if (dateCle === dateEnCle(aujourdHui)) {
                texteAccessible = "Aujourd’hui, " + texteAccessible;
            }

            const nombreTexte = nombreAnimations(nombre);
            const jourActif = dateCle === dateChoisie;

            boutons += `
                <button class="bouton-jour${jourActif ? " actif" : ""}"
                    id="jour-${dateCle}"
                    type="button"
                    role="tab"
                    aria-selected="${jourActif}"
                    aria-label="${texteAccessible}, ${nombreTexte}"
                    aria-controls="liste-evenements"
                    tabindex="${jourActif ? "0" : "-1"}"
                    data-index="${index}"
                    data-date="${dateCle}">
                    <span class="nom-jour">${nomJour}</span>
                    <strong>${dateCourte}</strong>
                    <span class="nombre-evenements">
                        ${nombre === 0 ? "Aucune animation" : nombreTexte}
                    </span>
                </button>`;
        }

        choixJour.innerHTML = boutons;
        listeEvenements.setAttribute("aria-labelledby", "jour-" + dateChoisie);
    }

    function creerCarteEvenement(evenement) {
        const placesDisponibles = evenement.places_disponibles;
        const capacite = evenement.capacite;
        const debutContientHeure = evenement.debut.length > 10;
        const finContientHeure = evenement.fin.length > 10;
        const heureInconnue = debutContientHeure
            && finContientHeure
            && evenement.debut.slice(11, 16) === "00:00"
            && evenement.fin.slice(11, 16) === "00:00";

        let heureDebut = "—";
        let heureFin = "Horaire à préciser";

        if (debutContientHeure && !heureInconnue) {
            heureDebut = evenement.debut.slice(11, 16);
        }
        if (finContientHeure && !heureInconnue) {
            heureFin = "à " + evenement.fin.slice(11, 16);
        }

        let reservation = "";
        if (donneesPlanning.role === "CLIENT") {
            if (evenement.inscrit) {
                reservation = '<a href="mes-inscriptions.php">Place réservée</a>';
            } else if (placesDisponibles > 0) {
                reservation = `
                    <form method="post" action="inscrire-evenement.php">
                        <input type="hidden" name="jeton_csrf" value="${texteSecurise(donneesPlanning.jeton)}">
                        <input type="hidden" name="id_evenement" value="${evenement.id}">
                        <button class="bouton bouton-reserver" type="submit">Réserver</button>
                    </form>`;
            } else {
                reservation = "<span>Complet</span>";
            }
        } else if (donneesPlanning.role === "") {
            reservation = '<a href="connexion.php">Se connecter pour réserver</a>';
        }

        return `
            <article class="carte-evenement">
                <div class="horaire-evenement">
                    <strong>${heureDebut}</strong>
                    <span>${heureFin}</span>
                </div>
                <div class="details-evenement">
                    <span class="etiquette">${texteSecurise(evenement.type || "Animation")}</span>
                    <h4>${texteSecurise(evenement.titre)}</h4>
                    <p>${texteSecurise(evenement.description)}</p>
                    <div class="informations-supplementaires">
                        <span>Jeu : ${texteSecurise(evenement.jeux || "À préciser")}</span>
                        <span>Salle : ${texteSecurise(evenement.salle || "À préciser")}</span>
                    </div>
                </div>
                <div class="places-disponibles">
                    <strong>${placesDisponibles}</strong>
                    <span>places disponibles sur ${capacite}</span>
                    ${reservation}
                </div>
            </article>`;
    }

    function afficherEvenements() {
        const date = new Date(dateChoisie + "T12:00:00");
        const dateLongue = formatDateLongue.format(date);

        if (dateChoisie === dateEnCle(new Date())) {
            titreDate.textContent = "Aujourd’hui · " + dateLongue;
        } else {
            titreDate.textContent = dateLongue;
        }

        const evenementsDuJour = [];
        for (const evenement of evenements) {
            if (evenement.debut.slice(0, 10) === dateChoisie) {
                evenementsDuJour.push(evenement);
            }
        }

        compteurEvenements.textContent = nombreAnimations(evenementsDuJour.length);

        if (evenementsDuJour.length === 0) {
            listeEvenements.innerHTML = `
                <div class="aucun-evenement">
                    <strong>Aucune animation prévue</strong>
                    <p>Consultez une autre journée du planning.</p>
                </div>`;
            return;
        }

        let cartes = "";
        for (const evenement of evenementsDuJour) {
            cartes += creerCarteEvenement(evenement);
        }
        listeEvenements.innerHTML = cartes;
    }

    function afficherPlanning() {
        afficherJours();
        afficherEvenements();
    }

    function choisirJour(event) {
        const bouton = event.target.closest("[data-date]");
        if (bouton === null) {
            return;
        }

        dateChoisie = bouton.dataset.date;
        afficherPlanning();
    }

    function changerJourAvecClavier(event) {
        const bouton = event.target.closest("[data-date]");
        if (bouton === null) {
            return;
        }

        const indexActuel = Number(bouton.dataset.index);
        let indexSuivant = indexActuel;

        if (event.key === "Home") {
            indexSuivant = 0;
        } else if (event.key === "End") {
            indexSuivant = jours.length - 1;
        } else if (event.key === "ArrowRight" || event.key === "ArrowDown") {
            indexSuivant = (indexActuel + 1) % jours.length;
        } else if (event.key === "ArrowLeft" || event.key === "ArrowUp") {
            indexSuivant = (indexActuel - 1 + jours.length) % jours.length;
        } else {
            return;
        }

        event.preventDefault();
        dateChoisie = dateEnCle(jours[indexSuivant]);
        afficherPlanning();
        choixJour.querySelector('[data-date="' + dateChoisie + '"]').focus();
    }

    choixJour.addEventListener("click", choisirJour);
    choixJour.addEventListener("keydown", changerJourAvecClavier);
    afficherPlanning();
})();
