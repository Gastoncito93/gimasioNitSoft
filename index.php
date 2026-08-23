<?php
/**
 * GYM PRO SaaS - Punto de Entrada Principal (Arquitectura Modular)
 */
require_once __DIR__ . '/proteger.php';
require_once __DIR__ . '/api/helpers.php';

// Si es una petición AJAX / API, delegar al router de la API
if (isset($_GET['ajax']) || isset($_GET['action'])) {
    require __DIR__ . '/api/index.php';
    exit;
}

// Renderizado de la Aplicación Web
require __DIR__ . '/views/layout/header.php';
require __DIR__ . '/views/layout/sidebar.php';
?>
  <main class="main">
    <?php require __DIR__ . '/views/layout/navbar.php'; ?>
    <?php require __DIR__ . '/views/pages/all_pages.php'; ?>
  </main>
<?php
require __DIR__ . '/views/layout/footer.php';