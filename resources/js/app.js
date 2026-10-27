// The hero's scan plays once, when the phone is in view: on phones it sits below the fold.
const phone = document.querySelector('.phone');
if (phone) {
    const observer = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        phone.classList.add('play');
        observer.disconnect();
    }, { threshold: .4 });
    observer.observe(phone);
}

// A visitor whose browser prefers another of the site's languages is offered this page in it, once: closing the
// offer keeps it closed for the rest of the visit. The page never moves anyone by itself.
const page = document.documentElement.lang;
const preferred = navigator.languages
    .map((language) => language.slice(0, 2).toLowerCase())
    .find((language) => language === page || document.querySelector(`[data-lang-suggest="${language}"]`));
const suggestion = preferred && preferred !== page && document.querySelector(`[data-lang-suggest="${preferred}"]`);
if (suggestion && !remembered('lang-suggest-closed')) {
    suggestion.hidden = false;
    suggestion.querySelector('[data-lang-suggest-close]').addEventListener('click', () => {
        suggestion.hidden = true;
        remember('lang-suggest-closed');
    });
}

function remembered(key) {
    try {
        return sessionStorage.getItem(key) !== null;
    } catch {
        return false;
    }
}

function remember(key) {
    try {
        sessionStorage.setItem(key, '1');
    } catch {
        // Private browsing without storage: the offer comes back on the next page, which is fine.
    }
}

// The language menu is a <details>: it closes on a click outside it, or on Escape.
document.querySelectorAll('[data-lang-menu]').forEach((menu) => {
    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) menu.open = false;
    });
    menu.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !menu.open) return;
        menu.open = false;
        menu.querySelector('summary').focus();
    });
});

// The waitlist form works without JavaScript; with it, it sends in the background and the visitor stays put.

document.querySelectorAll('[data-waitlist]').forEach((block) => {
    const form = block.querySelector('form');
    form.addEventListener('submit', (event) => join(event, block));
});

async function join(event, block) {
    event.preventDefault();
    const form = event.currentTarget;
    const input = form.querySelector('input[type=email]');
    const error = form.querySelector('[role=alert]');
    const button = form.querySelector('button[type=submit]');

    if (!input.value.trim() || !input.checkValidity()) {
        showError(input, error);
        return;
    }

    button.disabled = true;
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            showError(input, error, data.message);
            return;
        }
        form.hidden = true;
        const done = block.querySelector('[data-waitlist-done]');
        done.hidden = false;
        done.focus({ preventScroll: true });
    } catch {
        // Offline or blocked: let the browser send the form the plain way.
        form.submit();
    } finally {
        button.disabled = false;
    }
}

function showError(input, error, message) {
    if (message) error.textContent = message;
    error.hidden = false;
    input.setAttribute('aria-invalid', 'true');
    input.focus();
    input.addEventListener('input', () => {
        error.hidden = true;
        input.removeAttribute('aria-invalid');
    }, { once: true });
}
