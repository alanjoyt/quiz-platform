        </main>
        
        <!-- Mobile Bottom Tab Navigation -->
        <?php if (!(isset($GLOBALS['hideNav']) && $GLOBALS['hideNav'])): ?>
        <nav class="mobile-nav">
            <a href="<?php echo BASE_URI; ?>" class="mobile-nav-item <?php echo $_SERVER['REQUEST_URI'] == BASE_URI || $_SERVER['REQUEST_URI'] == BASE_URI . 'index' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                <span>Home</span>
            </a>
            <a href="<?php echo BASE_URI; ?>quiz" class="mobile-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], BASE_URI . 'quiz') !== false ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10H7v-2h10v2zm0-4H7V7h10v2zm0 8H7v-2h10v2z"/></svg>
                <span>Quiz</span>
            </a>
            <a href="<?php echo BASE_URI; ?>leaderboard" class="mobile-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], BASE_URI . 'leaderboard') !== false ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                <span>Rank</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?php echo BASE_URI; ?>profile" class="mobile-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], BASE_URI . 'profile') !== false ? 'active' : ''; ?>">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    <span>Profile</span>
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URI; ?>login" class="mobile-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], BASE_URI . 'login') !== false ? 'active' : ''; ?>">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                    <span>Login</span>
                </a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
        
        <!-- Footer -->
        <footer class="site-footer">
            <p>© 2026 Cheranalloor Faces. All rights reserved.</p>
            <p style="margin-top: 0.25rem; font-weight: 600; opacity: 0.85;">
                Website developed by 
                <a href="https://www.instagram.com/mr.ignite?igsh=aXJ3dXpiZTg5MXVy" target="_blank" style="color: var(--secondary-accent); font-weight: 700; text-decoration: none;">mr.ignite</a> 
                ~ 
                <a href="https://www.instagram.com/official.nexyra?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank" style="color: var(--secondary-accent); font-weight: 700; text-decoration: none;">nexyra</a>
            </p>
            <p style="margin-top: 0.15rem; font-size: 0.75rem; opacity: 0.75;">
                Contact: <a href="mailto:asknexyra@gmail.com" style="color: var(--text-color); text-decoration: underline;">asknexyra@gmail.com</a>
            </p>
            <p style="margin-top: 0.5rem;">
                <a href="https://www.instagram.com/cheranalloor_faces?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 600; color: #E1306C; text-decoration: none;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="vertical-align: middle;"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.051.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>
                    <span>Follow @cheranalloor_faces</span>
                </a>
            </p>
        </footer>
    </div>
    
    <!-- Scripts -->
    <script src="<?php echo BASE_URI; ?>static/js/main.js"></script>
    <?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>
