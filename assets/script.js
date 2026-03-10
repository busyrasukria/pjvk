document.addEventListener("DOMContentLoaded", () => {
    const $ = id => document.getElementById(id);

    // --- Collapsible Main Navbar ---
    const mainToggleBtn = $("main-nav-toggle");
    const mainNavbar = $("main-navbar");
    const mainContentWrapper = $("main-nav-content-wrapper"); // Ensure ID matches HTML
    const mainIcon = mainToggleBtn ? mainToggleBtn.querySelector('i') : null;

    if (mainToggleBtn && mainNavbar && mainContentWrapper && mainIcon) { 
        
        const setMainNavState = (isCollapsed) => {
            if (isCollapsed) {
                mainNavbar.classList.add('collapsed');
                mainIcon.classList.remove('fa-chevron-left');
                mainIcon.classList.add('fa-chevron-right');
            } else {
                mainNavbar.classList.remove('collapsed');
                mainIcon.classList.remove('fa-chevron-right');
                mainIcon.classList.add('fa-chevron-left');
            }
        };

        // Check and set initial state from localStorage
        const mainNavInitialState = localStorage.getItem('mainNavbarCollapsed') === 'true';
        setMainNavState(mainNavInitialState);
        
        mainToggleBtn.addEventListener("click", () => {
            const isCollapsed = mainNavbar.classList.contains('collapsed');
            if (isCollapsed) {
                // Open it
                setMainNavState(false);
                localStorage.setItem('mainNavbarCollapsed', 'false');
            } else {
                // Collapse it
                setMainNavState(true);
                localStorage.setItem('mainNavbarCollapsed', 'true');
            }
        });
    } else {
        console.error("Collapsible main navbar elements not found. Check IDs: main-nav-toggle, main-navbar, main-nav-content-wrapper");
    }
    // --- END: Collapsible Main Navbar ---
    

    function updateClock() {
    const options = { hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: true, timeZone: 'Asia/Kuala_Lumpur' };
    document.getElementById('currentTime').textContent = new Intl.DateTimeFormat('en-GB', options).format(new Date());
}
updateClock();
setInterval(updateClock, 1000);

function updateClock() {
    const options = { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit', 
        hour12: false,       // <-- 24-hour format
        timeZone: 'Asia/Kuala_Lumpur' 
    };
    document.getElementById('currentTime').textContent = new Intl.DateTimeFormat('en-GB', options).format(new Date());
}
updateClock();
setInterval(updateClock, 1000);

  lucide.createIcons();
});