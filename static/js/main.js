// Theme management
document.addEventListener('DOMContentLoaded', () => {
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    const sunIcon = document.getElementById('sun-icon');
    const moonIcon = document.getElementById('moon-icon');
    
    // Safe localStorage wrappers to prevent crashes on Brave or Private/Incognito windows
    const getStoredItem = (key) => {
        try {
            return localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    };

    const setStoredItem = (key, value) => {
        try {
            localStorage.setItem(key, value);
        } catch (e) {}
    };

    // Check stored preference or system preference
    const savedTheme = getStoredItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    let currentTheme = savedTheme || (systemPrefersDark ? 'dark' : 'light');
    
    // Set theme
    setTheme(currentTheme);
    
    // Toggle theme on button click
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            currentTheme = currentTheme === 'light' ? 'dark' : 'light';
            setTheme(currentTheme);
            setStoredItem('theme', currentTheme);
        });
    }
    
    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        
        // Toggle icons
        if (theme === 'dark') {
            if (sunIcon) sunIcon.style.display = 'block';
            if (moonIcon) moonIcon.style.display = 'none';
        } else {
            if (sunIcon) sunIcon.style.display = 'none';
            if (moonIcon) moonIcon.style.display = 'block';
        }
    }
    
    // Auto-dismiss alert notifications after 4 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease-out, max-height 0.5s ease-out';
            alert.style.opacity = '0';
            alert.style.maxHeight = '0';
            alert.style.padding = '0';
            alert.style.margin = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });
});
