const darkMode = {
    themeButton: document.getElementById("switchMode"),
    body : document.querySelector('body'),
    init: function() {
        darkMode.themeButton.addEventListener("click", darkMode.toggle);
        if (darkMode.body.dataset.theme === "dark") {
            document.querySelector(".theme-toggle").classList.add("theme-toggle--toggled");
        }
    },
    toggle: function() {
        if (darkMode.body.dataset.theme === "light") {
            document.querySelector(".theme-toggle").classList.add("theme-toggle--toggled");
            darkMode.body.dataset.theme = "dark";
            darkMode.updateSession("dark");
        } else {
            darkMode.body.dataset.theme = "light";
            document.querySelector(".theme-toggle").classList.remove("theme-toggle--toggled");
            darkMode.updateSession("light");
        }
    },
    updateSession: function(value) {
        // Envoyer la valeur au serveur (via une requête AJAX)
        fetch('/theme', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ theme: value }),
        }).then(response => console.log(response.status));
    }
}
document.addEventListener("DOMContentLoaded", darkMode.init);