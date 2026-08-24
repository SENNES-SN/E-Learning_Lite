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

    const setButtonLoading = (control) => {
        if (!control || control.dataset.loadingActive === "true") return;

        const label = control.getAttribute("aria-label") || control.textContent.trim();
        const content = Array.from(control.children).find((child) =>
            child.classList.contains("loading-button-content"),
        );

        if (!content) {
            const contentWrapper = document.createElement("span");
            contentWrapper.className = "loading-button-content";
            while (control.firstChild) {
                contentWrapper.appendChild(control.firstChild);
            }
            control.appendChild(contentWrapper);
        }

        control.dataset.loadingActive = "true";
        control.dataset.loadingOriginalLabel = control.getAttribute("aria-label") || "";
        control.classList.add("is-loading");
        control.setAttribute("aria-busy", "true");
        control.setAttribute("aria-label", label ? `${label} sedang diproses` : "Sedang diproses");

        if (control instanceof HTMLButtonElement) {
            control.disabled = true;
        } else {
            control.dataset.loadingOriginalTabindex = control.getAttribute("tabindex") || "";
            control.setAttribute("aria-disabled", "true");
            control.setAttribute("tabindex", "-1");
        }
    };

    const resetButtonLoading = (control) => {
        if (!control || control.dataset.loadingActive !== "true") return;

        control.classList.remove("is-loading");
        control.removeAttribute("aria-busy");

        if (control.dataset.loadingOriginalLabel) {
            control.setAttribute("aria-label", control.dataset.loadingOriginalLabel);
        } else {
            control.removeAttribute("aria-label");
        }

        if (control instanceof HTMLButtonElement) {
            control.disabled = false;
        } else {
            control.removeAttribute("aria-disabled");
            if (control.dataset.loadingOriginalTabindex) {
                control.setAttribute("tabindex", control.dataset.loadingOriginalTabindex);
            } else {
                control.removeAttribute("tabindex");
            }
        }

        delete control.dataset.loadingActive;
        delete control.dataset.loadingOriginalLabel;
        delete control.dataset.loadingOriginalTabindex;
    };

    window.setButtonLoading = setButtonLoading;

    document.addEventListener("click", (event) => {
        const control = event.target.closest("a[data-loading-button]");
        if (
            !control ||
            event.defaultPrevented ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey ||
            control.hasAttribute("download") ||
            control.target === "_blank"
        ) {
            return;
        }

        setButtonLoading(control);
    });

    document.addEventListener("submit", (event) => {
        if (event.defaultPrevented) return;

        const submitter = event.submitter
            || event.target.querySelector('[type="submit"][data-loading-button]');
        if (submitter?.matches("[data-loading-button]")) {
            setButtonLoading(submitter);
        }
    });

    window.addEventListener("pageshow", () => {
        document.querySelectorAll('[data-loading-button][data-loading-active="true"]')
            .forEach(resetButtonLoading);
    });
});
