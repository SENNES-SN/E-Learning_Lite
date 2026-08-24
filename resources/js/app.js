import "./bootstrap";
import { createIcons, icons } from "lucide";

document.addEventListener("DOMContentLoaded", () => {
    createIcons({
        icons,
        attrs: {
            "stroke-width": 2,
            "aria-hidden": "true",
        },
    });

    const passwordToggle = document.querySelector("[data-password-toggle]");
    const passwordInput = document.querySelector("#password");

    if (passwordToggle && passwordInput) {
        passwordToggle.addEventListener("click", () => {
            const passwordIsVisible = passwordInput.type === "text";
            const hiddenIcon = passwordToggle.querySelector(
                ".password-hidden-icon",
            );
            const visibleIcon = passwordToggle.querySelector(
                ".password-visible-icon",
            );

            passwordInput.type = passwordIsVisible ? "password" : "text";
            passwordToggle.setAttribute(
                "aria-label",
                passwordIsVisible
                    ? "Tampilkan password"
                    : "Sembunyikan password",
            );
            hiddenIcon?.toggleAttribute("hidden", !passwordIsVisible);
            visibleIcon?.toggleAttribute("hidden", passwordIsVisible);
        });
    }

    const loginFeedback = document.querySelector("[data-login-feedback]");

    if (loginFeedback) {
        window.setTimeout(() => {
            loginFeedback.classList.add("is-leaving");
            document
                .querySelector(".login-card")
                ?.classList.remove("has-feedback");
            window.setTimeout(() => loginFeedback.remove(), 220);
        }, 4000);
    }
});
