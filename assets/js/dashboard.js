const navLinks = document.querySelectorAll('.nav-link');
const contentFrame = document.getElementById('contentFrame');

function setActiveLinkByHref(href) {
    navLinks.forEach(item => item.classList.remove('active'));

    navLinks.forEach(link => {
        const linkHref = link.getAttribute('href');
        if (href && linkHref && href.includes(linkHref)) {
            link.classList.add('active');
        }
    });
}

navLinks.forEach(link => {
    link.addEventListener('click', () => {
        navLinks.forEach(item => item.classList.remove('active'));
        link.classList.add('active');
        localStorage.setItem('firstaid_last_page', link.getAttribute('href'));
    });
});

if (contentFrame) {
    const savedPage = localStorage.getItem('firstaid_last_page');
    if (savedPage) {
        contentFrame.src = savedPage;
        setActiveLinkByHref(savedPage);
    }

    contentFrame.addEventListener('load', () => {
        try {
            const framePath = contentFrame.contentWindow.location.pathname.split('/').pop();
            if (framePath && framePath !== 'dashboard.php') {
                localStorage.setItem('firstaid_last_page', framePath);
                document.cookie = `firstaid_last_page=${encodeURIComponent(framePath)}; path=/; max-age=604800`;
                setActiveLinkByHref(framePath);
            }
        } catch (e) {
            // Ignore cross-origin frame errors.
        }
    });
}