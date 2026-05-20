const INACTIVITY_LIMIT_MS = 10 * 60 * 1000;
const LOGOUT_GRACE_MS = 60 * 1000;

let inactivityTimer = null;
let logoutTimer = null;

const sessionModal = document.getElementById('sessionModal');
const stayLoggedInBtn = document.getElementById('stayLoggedInBtn');

function showSessionWarning() {
    if (!sessionModal) return;

    sessionModal.classList.add('show');
    sessionModal.setAttribute('aria-hidden', 'false');

    logoutTimer = window.setTimeout(() => {
        window.top.location.href = 'logout.php';
    }, LOGOUT_GRACE_MS);
}

function resetInactivityTimer() {
    window.clearTimeout(inactivityTimer);
    inactivityTimer = window.setTimeout(showSessionWarning, INACTIVITY_LIMIT_MS);
}

function hideSessionWarning() {
    if (!sessionModal) return;

    sessionModal.classList.remove('show');
    sessionModal.setAttribute('aria-hidden', 'true');
}

['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(eventName => {
    document.addEventListener(eventName, () => {
        if (!sessionModal || !sessionModal.classList.contains('show')) {
            resetInactivityTimer();
        }
    }, { passive: true });
});

if (stayLoggedInBtn) {
    stayLoggedInBtn.addEventListener('click', async () => {
        window.clearTimeout(logoutTimer);

        try {
            const response = await fetch('keep_alive.php', {
                method: 'POST',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                window.top.location.href = 'logout.php';
                return;
            }

            hideSessionWarning();
            resetInactivityTimer();
        } catch (e) {
            window.top.location.href = 'logout.php';
        }
    });
}

resetInactivityTimer();
