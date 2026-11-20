(() => {
    "use strict";

    const donnees = JSON.parse(
        document.querySelector("#donnees-evenements").textContent
    );
    const evenements = donnees.evenements;
    const choixJour = document.querySelector("#choix-jour");
    const dateTitre = document.querySelector("#date-selectionnee");
    const compteurEvenements = document.querySelector("#compteur-evenements");
    const liste = document.querySelector("#liste-evenements");
    const jours = [];
    const aujourdHui = new Date();
    aujourdHui.setHours(12, 0, 0, 0);

    for (let i = 0; i < 7; i++) {
        const date = new Date(aujourdHui);
        date.setDate(aujourdHui.getDate() + i);
        jours.push(date);
    }

    let jourChoisi = cleDate(jours[0]);

    function cleDate(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
    }

    function echapper(texte) {
        return String(texte ?? "").replace(/[&<>"']/g, (caractere) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[caractere]);
    }

    function afficher() {
        choixJour.innerHTML = jours.map((date, index) => {
            const cle = cleDate(date);
            const nombre = evenements.filter(
                (evenement) => evenement.debut.slice(0, 10) === cle
            ).length;
            const nom = index === 0
                ? "Aujourd’hui"
                : new Intl.DateTimeFormat("fr-FR", {
                    weekday: "short"
                }).format(date);
            const dateCourte = new Intl.DateTimeFormat("fr-FR", {
                day: "numeric",
                month: "short"
            }).format(date);
            const libelleJour = new Intl.DateTimeFormat("fr-FR", {
                weekday: "long",
                day: "numeric",
                month: "long"
            }).format(date);

            return `
                <button class="bouton-jour${cle === jourChoisi ? " actif" : ""}"
                    id="jour-${cle}"
                    type="button" role="tab"
                    aria-selected="${cle === jourChoisi}"
                    aria-label="${cle === cleDate(aujourdHui) ? "Aujourd’hui, " : ""}${libelleJour}, ${nombre} animation${nombre > 1 ? "s" : ""}"
                    aria-controls="liste-evenements"
                    tabindex="${cle === jourChoisi ? "0" : "-1"}"
                    data-date="${cle}">
                    <span class="nom-jour">${nom}</span>
                    <strong>${dateCourte}</strong>
                    <span class="nombre-evenements">
                        ${nombre ? `${nombre} animation${nombre > 1 ? "s" : ""}` : "Aucune animation"}
                    </span>
                </button>`;
        }).join("");
        liste.setAttribute("aria-labelledby", `jour-${jourChoisi}`);

        const date = new Date(`${jourChoisi}T12:00:00`);
        const dateLongue = new Intl.DateTimeFormat("fr-FR", {
            weekday: "long",
            day: "numeric",
            month: "long"
        }).format(date);
        dateTitre.textContent = jourChoisi === cleDate(new Date())
            ? `Aujourd’hui · ${dateLongue}`
            : dateLongue;

        const duJour = evenements
            .filter((evenement) => evenement.debut.slice(0, 10) === jourChoisi)
            .sort((a, b) => a.debut.localeCompare(b.debut));
        compteurEvenements.textContent = `${duJour.length} animation${duJour.length > 1 ? "s" : ""}`;

        if (duJour.length === 0) {
            liste.innerHTML = `
                <div class="aucun-evenement">
                    <strong>Aucune animation prévue</strong>
                    <p>Consultez une autre journée du planning.</p>
                </div>`;
            return;
        }

        liste.innerHTML = duJour.map((evenement) => {
            const horaireInconnu = evenement.debut.length > 10
                && evenement.fin.length > 10
                && evenement.debut.slice(11, 16) === "00:00"
                && evenement.fin.slice(11, 16) === "00:00";
            const debut = evenement.debut.length > 10 && !horaireInconnu
                ? evenement.debut.slice(11, 16)
                : "—";
            const fin = evenement.fin.length > 10 && !horaireInconnu
                ? `à ${evenement.fin.slice(11, 16)}`
                : "Horaire à préciser";
            const places = Number(evenement.places_disponibles);
            const capacite = Number(evenement.capacite);
            let reservation = "";

            if (donnees.role === "CLIENT") {
                reservation = evenement.inscrit
                    ? '<a href="mes-inscriptions.php">Place réservée</a>'
                    : places > 0
                        ? `<form method="post" action="inscrire-evenement.php">
                            <input type="hidden" name="jeton_csrf" value="${echapper(donnees.jeton)}">
                            <input type="hidden" name="id_evenement" value="${Number(evenement.id)}">
                            <button class="bouton bouton-reserver" type="submit">Réserver</button>
                        </form>`
                        : '<span>Complet</span>';
            } else if (!donnees.role) {
                reservation = '<a href="login.php">Se connecter pour réserver</a>';
            }

            return `
                <article class="carte-evenement">
                    <div class="horaire-evenement">
                        <strong>${debut}</strong>
                        <span>${fin}</span>
                    </div>
                    <div class="details-evenement">
                        <span class="etiquette">${echapper(evenement.type || "Animation")}</span>
                        <h4>${echapper(evenement.titre)}</h4>
                        <p>${echapper(evenement.description)}</p>
                        <div class="informations-supplementaires">
                            <span>Jeu : ${echapper(evenement.jeux || "À préciser")}</span>
                            <span>Salle : ${echapper(evenement.salle || "À préciser")}</span>
                        </div>
                    </div>
                    <div class="places-disponibles">
                        <strong>${Number.isFinite(places) ? places : 0}</strong>
                        <span>places disponibles sur ${Number.isFinite(capacite) ? capacite : 0}</span>
                        ${reservation}
                    </div>
                </article>`;
        }).join("");
    }

    choixJour.addEventListener("click", (event) => {
        const bouton = event.target.closest("[data-date]");
        if (bouton) {
            jourChoisi = bouton.dataset.date;
            afficher();
        }
    });

    choixJour.addEventListener("keydown", (event) => {
        const bouton = event.target.closest("[data-date]");
        if (!bouton) {
            return;
        }

        const indexActuel = jours.findIndex(
            (date) => cleDate(date) === bouton.dataset.date
        );
        let indexSuivant = indexActuel;

        if (event.key === "ArrowRight" || event.key === "ArrowDown") {
            indexSuivant = (indexActuel + 1) % jours.length;
        } else if (event.key === "ArrowLeft" || event.key === "ArrowUp") {
            indexSuivant = (indexActuel - 1 + jours.length) % jours.length;
        } else if (event.key === "Home") {
            indexSuivant = 0;
        } else if (event.key === "End") {
            indexSuivant = jours.length - 1;
        } else {
            return;
        }

        event.preventDefault();
        jourChoisi = cleDate(jours[indexSuivant]);
        afficher();
        choixJour.querySelector(`[data-date="${jourChoisi}"]`).focus();
    });

    afficher();
})();
