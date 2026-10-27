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
