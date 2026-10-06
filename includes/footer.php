</main>
<footer class="site-footer">
    <div class="wrap footer-bar">
        <?php if (empty($isAdmin)) { render_ad_slot('footer'); } ?>
        <div class="footer-brand">
            <img src="<?= e(href('assets/icon.png')) ?>" alt="" width="42" height="42">
            <div>
                <strong>WhatsGrolink.com</strong>
                <p>A directory of WhatsApp group invite links.</p>
            </div>
        </div>
        <nav class="footer-links" aria-label="Information">
            <a href="<?= e(href('about.php')) ?>">About</a>
            <a href="<?= e(href('contact.php')) ?>">Contact</a>
            <a href="<?= e(href('disclaimer.php')) ?>">Disclaimer</a>
            <a href="<?= e(href('privacy.php')) ?>">Privacy</a>
            <a href="<?= e(href('terms.php')) ?>">Terms</a>
        </nav>
    </div>
</footer>
</body>
</html>
