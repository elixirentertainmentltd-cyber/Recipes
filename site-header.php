<header class="site-header">
  <a class="brand" href="/" aria-label="Elixir Recipes home"><img src="/assets/elixir-recipes-logo.webp" alt="" width="220" height="110"></a>
  <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav" hidden><span class="menu-icon" aria-hidden="true"><span></span><span></span><span></span></span><span>Menu</span></button>
  <nav id="site-nav" class="site-nav" aria-label="Primary navigation">
    <a href="/#recipes">Recipes</a><a href="/#browse">Browse</a><a href="/#about">About</a>
    <?php if (is_admin()): ?><a href="/admin.php">Manage recipes</a><a class="nav-cta" href="/admin.php?logout=1">Sign out</a><?php else: ?><a class="nav-cta" href="/admin.php">Recipe admin <span aria-hidden="true">↗</span></a><?php endif; ?>
  </nav>
</header>
