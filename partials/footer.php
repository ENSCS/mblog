</div>
<footer class="site-footer">
  <div class="container">&copy; <?= date('Y') ?> <?= htmlspecialchars(siteSetting('site_name')) ?> — <?= htmlspecialchars(siteSetting('footer_tagline')) ?></div>
</footer>
<?= $footerScripts ?? '' ?>
</body>
</html>
