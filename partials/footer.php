</div>
<footer class="site-footer">
  <div class="container container-wide">&copy; <?= date('Y') ?> <?= htmlspecialchars(siteSetting('site_name')) ?> — <?= htmlspecialchars(siteSetting('footer_tagline')) ?></div>
</footer>
<?= $footerScripts ?? '' ?>
<?= siteSetting('custom_body_code', '') ?>
</body>
</html>
