<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  $cartMgr = new CartManager();
  $cartId = $cartMgr->getCurrentCartId();
  $totCartItems = $cartMgr->getCartTotal($cartId)[0]['num_products'];
  $totCartItems = !$totCartItems ? 0 : $totCartItems;
?>
  <footer class="navbar-fixed bottom">
    <hr>
    <div class="container mt-5 mb-5">
      <p><?php echo 'Copyright &copy; ' . date('Y').' - '. SITE_NAME; ?></p>
      <p class="small text-muted">
        <a href="<?php echo ROOT_URL; ?>public?page=privacy">Informativa Privacy</a> |
        <a href="<?php echo ROOT_URL; ?>public?page=cookie-policy">Cookie Policy</a>
      </p>
    </div>
  </footer>

  <!-- Cookie Consent Banner -->
  <?php if (!isset($_COOKIE['cookie_consent'])): ?>
  <div id="cookieBanner" class="cookie-banner">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-md-8">
          <p class="mb-md-0">
            Questo sito utilizza cookie tecnici necessari al funzionamento.
            Continuando la navigazione accetti l'utilizzo dei cookie.
            <a href="<?php echo ROOT_URL; ?>public?page=cookie-policy">Maggiori informazioni</a>
          </p>
        </div>
        <div class="col-md-4 text-md-right">
          <button type="button" class="btn btn-primary btn-sm" onclick="acceptCookies()">Accetta</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="acceptCookies()">Chiudi</button>
        </div>
      </div>
    </div>
  </div>
  <style>
  .cookie-banner {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #343a40;
    color: #fff;
    padding: 15px 0;
    z-index: 9999;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
  }
  .cookie-banner a {
    color: #17a2b8;
  }
  .cookie-banner p {
    margin: 0;
    font-size: 0.9rem;
  }
  </style>
  <script>
  function acceptCookies() {
    var expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1);
    document.cookie = 'cookie_consent=accepted; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
    document.getElementById('cookieBanner').style.display = 'none';
  }
  </script>
  <?php endif; ?>

  <script>
  $(document).ready(function(){
    var totCartItems = '<?php echo $totCartItems; ?>';
    $('.js-totCartItems').html(totCartItems);
  });
  </script>
</body>

</html>