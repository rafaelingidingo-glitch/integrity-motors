<?php /* Admin shell — footer */ ?>
    </main>
  </div><!-- /content column -->
</div><!-- /flex -->

<!-- Mobile sidebar toggle (progressive enhancement, mirrors app.js nav toggle) -->
<script src="<?= url('/assets/js/app.js') ?>" defer></script>
<script defer>
document.addEventListener('click', function (e) {
  var drawer = document.getElementById('admin-mobile-nav');
  if (!drawer) return;
  if (e.target.closest('#admin-nav-toggle')) {
    drawer.classList.toggle('hidden');
  }
  if (e.target.closest('[data-admin-nav-close]')) {
    drawer.classList.add('hidden');
  }
});
</script>
</body>
</html>
