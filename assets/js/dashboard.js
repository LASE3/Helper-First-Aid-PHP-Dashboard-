const navLinks = document.querySelectorAll('.nav-link');
const contentFrame = document.getElementById('contentFrame');

function normalizeFramePage(value) {
    if (!value) return 'incidents.php';

    value = String(value).trim();

    try {
        const url = new URL(value, window.location.href);
        value = url.pathname.split('/').pop() + url.search;
    } catch (e) {
        // keep original value
    }

    // Do not restore details pages without their required id.
    if (value === 'incident_view.php' || value === 'user_view.php') {
        return value === 'user_view.php' ? 'users.php' : 'incidents.php';
    }

    // Basic safety: only allow local PHP pages, not external URLs.
    if (!/^[a-zA-Z0-9_\-]+\.php(\?.*)?$/.test(value)) {
        return 'incidents.php';
    }

    return value;
}

function setLastPage(page) {
    const safePage = normalizeFramePage(page);
    localStorage.setItem('firstaid_last_page', safePage);
    document.cookie = `firstaid_last_page=${encodeURIComponent(safePage)}; path=/; max-age=604800; SameSite=Lax`;
    return safePage;
}

function setActiveLinkByHref(href) {
    navLinks.forEach(item => item.classList.remove('active'));

    const page = normalizeFramePage(href).split('?')[0];

    navLinks.forEach(link => {
        const linkHref = link.getAttribute('href');
        if (linkHref && (page === linkHref || page.includes(linkHref))) {
            link.classList.add('active');
        }
    });

    // Details pages belong to their parent menu.
    if (page === 'incident_view.php' || page === 'export_incident_csv.php') {
        document.querySelector('.nav-link[href="incidents.php"]')?.classList.add('active');
    }

    if (page === 'user_view.php') {
        document.querySelector('.nav-link[href="users.php"]')?.classList.add('active');
    }
}

navLinks.forEach(link => {
    link.addEventListener('click', () => {
        const page = setLastPage(link.getAttribute('href'));
        setActiveLinkByHref(page);
    });
});

if (contentFrame) {
    const urlParams = new URLSearchParams(window.location.search);
    const requestedPage = urlParams.get('page');
    const frameSrc = contentFrame.getAttribute('src');
    const savedPage = normalizeFramePage(requestedPage || frameSrc || localStorage.getItem('firstaid_last_page'));
    contentFrame.src = savedPage;
    setLastPage(savedPage);
    setActiveLinkByHref(savedPage);

    contentFrame.addEventListener('load', () => {
        try {
            const url = new URL(contentFrame.contentWindow.location.href);
            const framePage = url.pathname.split('/').pop() + url.search;

            if (framePage && framePage !== 'dashboard.php') {
                const safePage = setLastPage(framePage);
                setActiveLinkByHref(safePage);
            }
        } catch (e) {
            // Ignore cross-origin frame errors.
        }
    });
}

function openGlobalModal(html) {
    document.getElementById("globalModalContent").innerHTML = html;
    document.getElementById("globalModalOverlay").classList.add("active");
    document.body.style.overflow = "hidden";
}

function closeGlobalModal() {
    document.getElementById("globalModalOverlay").classList.remove("active");
    document.getElementById("globalModalContent").innerHTML = "";
    document.body.style.overflow = "";
}

const globalModalOverlay = document.getElementById("globalModalOverlay");
if (globalModalOverlay) {
    globalModalOverlay.addEventListener("click", function (event) {
        if (event.target === globalModalOverlay) {
            closeGlobalModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && globalModalOverlay.classList.contains("active")) {
            closeGlobalModal();
        }
    });
}
