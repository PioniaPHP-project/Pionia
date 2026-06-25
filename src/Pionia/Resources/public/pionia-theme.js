(function () {
    var key = 'pionia-theme';

    function currentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(key, theme);
        window.__pioniaTheme = theme;
    }

    function toggleTheme() {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    }

    document.addEventListener('click', function (event) {
        var target = event.target.closest('[data-pionia-theme-toggle]');
        if (target) {
            event.preventDefault();
            toggleTheme();
        }
    });
})();
