(function () {
    "use strict";

    const bloc = document.querySelector("#donnees-evenements");
    const donnees = JSON.parse(bloc.textContent);
    const evenements = donnees.evenements;
    const choixJour = document.querySelector("#choix-jour");
    const titreDate = document.querySelector("#date-selectionnee");
    const compteur = document.querySelector("#compteur-evenements");
    const liste = document.querySelector("#liste-evenements");
    const aujourdHui = new Date();
    aujourdHui.setHours(12, 0, 0, 0);
    const debutSemaine = new Date(donnees.dateDebut + "T12:00:00");

    const jourCourt = new Intl.DateTimeFormat("fr-FR", {weekday: "short"});
    const dateCourte = new Intl.DateTimeFormat("fr-FR", {day: "numeric", month: "short"});
    const dateLongue = new Intl.DateTimeFormat("fr-FR", {weekday: "long", day: "numeric", month: "long"});
    const jours = Array.from({length: 7}, (_, index) => {
        const date = new Date(debutSemaine);
        date.setDate(debutSemaine.getDate() + index);
        return date;
    });
    let jourActif = 0;

    function cle(date) {
        const mois = String(date.getMonth() + 1).padStart(2, "0");
        const jour = String(date.getDate()).padStart(2, "0");
        return `${date.getFullYear()}-${mois}-${jour}`;
    }

    function securise(texte) {
        const remplacements = {"&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"};
        return String(texte ?? "").replace(/[&<>"']/g, caractere => remplacements[caractere]);
    }

    function animations(nombre) {
        return nombre + (nombre > 1 ? " animations" : " animation");
    }

    function evDuJour(date) {
        return evenements.filter(evenement => evenement.debut.slice(0, 10) === cle(date));
    }

    function afficherJours() {
        choixJour.innerHTML = jours.map((date, index) => {
            const nombre = evDuJour(date).length;
            const estAujourdhui = cle(date) === cle(aujourdHui);
            return `
                <button class="bouton-jour${index === 0 ? " actif" : ""}" type="button"
                    id="jour-${cle(date)}" role="tab" aria-controls="liste-evenements"
                    aria-selected="${index === 0}" tabindex="${index === 0 ? 0 : -1}"
                    data-date="${cle(date)}" data-index="${index}"
                    aria-label="${dateLongue.format(date)}, ${animations(nombre)}">
                    <span class="nom-jour">${estAujourdhui ? "Aujourd’hui" : jourCourt.format(date)}</span>
                    <strong>${dateCourte.format(date)}</strong>
                    <span class="nombre-evenements">${nombre === 0 ? "Aucune animation" : animations(nombre)}</span>
                </button>`;
        }).join("");

    }

    function selectionnerJour(index) {
        jourActif = index;
        const boutonActif = choixJour.querySelector(`[data-index="${index}"]`);
        choixJour.querySelectorAll(".bouton-jour").forEach((bouton, boutonIndex) => {
            const actif = boutonIndex === index;
            bouton.classList.toggle("actif", actif);
            bouton.setAttribute("aria-selected", String(actif));
            bouton.tabIndex = actif ? 0 : -1;
        });

        titreDate.textContent = dateLongue.format(jours[index]);
        compteur.textContent = animations(evDuJour(jours[index]).length);
        liste.setAttribute("aria-labelledby", boutonActif.id);
        liste.querySelectorAll(".colonne-jour").forEach((colonne, colonneIndex) => {
            colonne.classList.toggle("jour-actif", colonneIndex === index);
        });
    }

    function minutes(heure) {
        const [heures, minutes] = heure.split(":").map(Number);
        return (heures - 8) * 60 + minutes;
    }

    function blocEvenement(evenement) {
        const debutHeure = evenement.debut.length > 10;
        const finHeure = evenement.fin.length > 10;
        const heureInconnue = debutHeure && finHeure &&
            evenement.debut.slice(11, 16) === "00:00" &&
            evenement.fin.slice(11, 16) === "00:00";
        const heureDebut = heureInconnue || !debutHeure ? "08:00" : evenement.debut.slice(11, 16);
        const heureFin = heureInconnue || !finHeure ? "09:00" : evenement.fin.slice(11, 16);
        const haut = Math.max(0, minutes(heureDebut));
        const hauteur = Math.max(46, minutes(heureFin) - minutes(heureDebut));

        return `
            <article class="bloc-evenement" style="top:${haut}px;height:${hauteur}px"
                title="${securise(evenement.titre)} — ${heureDebut} à ${heureFin} · ${securise(evenement.description || "")}">
                <div class="bloc-heures">${heureDebut} — ${heureFin}</div>
                <h4>${securise(evenement.titre)}</h4>
                <p>${securise(evenement.type || "Animation")} · ${securise(evenement.jeux || "Jeu à préciser")}</p>
                <p>Salle : ${securise(evenement.salle || "À préciser")}</p>
            </article>`;
    }

    function afficherGrille() {
        const heures = Array.from({length: 15}, (_, index) => {
            const heure = index + 8;
            return `<div class="ligne-heure" style="top:${index * 60}px">
                <span>${String(heure).padStart(2, "0")}:00</span>
            </div>`;
        }).join("");

        const colonnes = jours.map((date, index) =>
            `<div class="colonne-jour${cle(date) === cle(aujourdHui) ? " jour-aujourdhui" : ""}" data-date="${cle(date)}">
                ${evDuJour(date).map(blocEvenement).join("")}
            </div>`
        ).join("");

        liste.innerHTML = `<div class="grille-semaine">
            <div class="axe-heures">${heures}</div>
            <div class="colonnes-semaine">${colonnes}</div>
        </div>`;
    }

    choixJour.addEventListener("click", event => {
        const bouton = event.target.closest(".bouton-jour");
        if (bouton) selectionnerJour(Number(bouton.dataset.index));
    });

    choixJour.addEventListener("keydown", event => {
        if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") return;
        event.preventDefault();
        const direction = event.key === "ArrowRight" ? 1 : -1;
        const index = (jourActif + direction + jours.length) % jours.length;
        choixJour.querySelector(`[data-index="${index}"]`).focus();
        selectionnerJour(index);
    });

    afficherJours();
    afficherGrille();
    selectionnerJour(0);
})();
