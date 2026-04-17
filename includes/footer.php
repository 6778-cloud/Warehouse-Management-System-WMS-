</div> <!-- End content-area -->
</div> <!-- End main-content -->
</div> <!-- End wrapper -->

<!-- Scripts -->
<script>
    // --- THEME TOGGLE (For Print Friendly Screenshots) ---
    const themeBtn = document.getElementById('theme-toggle');
    const body = document.body;

    // Load saved theme
    if (localStorage.getItem('theme') === 'light') {
        body.classList.add('light-mode');
        if (themeBtn) updateThemeButton(true);
    }

    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            body.classList.toggle('light-mode');
            const isLight = body.classList.contains('light-mode');
            localStorage.setItem('theme', isLight ? 'light' : 'dark');
            updateThemeButton(isLight);
        });
    }

    function updateThemeButton(isLight) {
        if (!themeBtn) return;
        if (isLight) {
            themeBtn.innerHTML = '<i class="fas fa-moon"></i> Dark Mode';
        } else {
            themeBtn.innerHTML = '<i class="fas fa-sun"></i> Light Mode';
        }
    }

    // --- SIDEBAR TOGGLE ---
    const toggleBtn = document.querySelector('.toggle-sidebar');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');

    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', () => {
            if (sidebar.style.display === 'none') {
                sidebar.style.display = 'block';
                mainContent.style.marginLeft = '260px';
            } else {
                sidebar.style.display = 'none';
                mainContent.style.marginLeft = '0';
            }
        });
    }
</script>
</body>

</html>