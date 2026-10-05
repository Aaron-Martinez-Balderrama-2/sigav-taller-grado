
        </div><!-- .page-content -->
    </div><!-- .main-content -->
</div><!-- .app-layout -->

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('show');
    }

    function toggleTheme() {
        var html = document.documentElement;
        var current = html.getAttribute('data-theme');
        var next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('sigav-theme', next);
        updateThemeIcon(next);
    }

    function updateThemeIcon(theme) {
        var icon = document.getElementById('themeIcon');
        if (icon) icon.textContent = theme === 'dark' ? '🌙' : '☀️';
    }

    // Inicializar ícono correcto al cargar
    updateThemeIcon(document.documentElement.getAttribute('data-theme') || 'light');
</script>
</body>
</html>
