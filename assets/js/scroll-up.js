const app = {
    init: function() {
        console.log("scroll-up.init()");
        app.reveal();
    },

    reveal: function() {
        const reveal_btn = document.querySelector(".reveal-btn");
        const reveal = document.querySelector(".reveal");
        const windowHeight = window.innerHeight;
        const elementTop = reveal_btn.getBoundingClientRect().top;
        const elementVisible = 100;

        if (elementTop < windowHeight - elementVisible) {
            reveal.classList.add("active");
        } else {
            reveal.classList.remove("active");
        }
    }
}

window.addEventListener("scroll", app.init);