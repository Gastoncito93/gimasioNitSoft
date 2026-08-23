<?php
/**
 * Layout Header - GYM PRO SaaS
 */
// Las variables globales ($isSuperAdmin, $isSimulating, $userRole, $simulatedRole, $gimnasioId, $gimnasioNombre, $isPlanPro, etc.) ya están normalizadas por proteger.php
$isSimulating = $isSimulating || (!empty($_SESSION['simulated_role']) && in_array($_SESSION['simulated_role'], [ROLE_DUENO, ROLE_COACH, ROLE_ALUMNO]));
$simulatedRole = $simulatedRole ?? ($_SESSION['simulated_role'] ?? null);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($gimnasioNombre) ?> - GYM PRO</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/../../assets/css/app.css') ?>">
</head>
<body>

<?php if ($isSimulating): ?>
<div class="simulation-banner">
  <div class="sim-pill">
    <span class="sim-dot"></span>
    MODO SIMULACIÓN: <b style="text-transform:uppercase;color:var(--t1)"><?= htmlspecialchars($userRole) ?></b> (Sede: <b style="color:var(--t1)"><?= htmlspecialchars($gimnasioNombre) ?></b>)
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <button type="button" class="btn btn-xs btn-primary" onclick="openSimulationModal()" style="font-weight:700">Cambiar Rol / Sede</button>
    <button type="button" class="btn btn-xs btn-danger" onclick="exitSimulationMode()" style="font-weight:700">✕ Salir de Simulación</button>
  </div>
</div>
<?php endif; ?>

<div class="app">