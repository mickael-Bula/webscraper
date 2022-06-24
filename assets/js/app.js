const app = {
    init: function() {
        console.log("app.init");
        const cards = document.querySelectorAll(".my-card");
        cards.forEach(element => element.addEventListener("click", app.handleClick));
    },

    handleClick: function(e) {
        const card = e.currentTarget;
        const color = (card.id === "orange") ? "orange" : (card.id === "red") ? "red" : "green";

        if (card.classList.contains("active")) {
            document.querySelector(`.my-card--${color} > .h5`).classList.remove("active");
            document.querySelector(`.my-card--${color} > .my-card-positions`).classList.remove("active");
            card.classList.remove("active");
            // j'ajoute un timer pour faire disparaître la bordure une fois l'élément refermé
            setTimeout(() => card.classList.remove(`my-card--${color}`), 200);
        } else {
            card.className=`my-card my-card--${color} active`;
            document.querySelector(`.my-card--${color} > .h5`).className="h5 active";
            document.querySelector(`.my-card--${color} > .my-card-positions`).className="my-card-positions active";
        }
    }
}

document.addEventListener("DOMContentLoaded", app.init);