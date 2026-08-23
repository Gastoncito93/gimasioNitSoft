</div> <!-- /.app -->

<?php
// Cargar todos los modales del sistema
require __DIR__ . '/../modals/all_modals.php';
?>

<script>
const CURRENT_USER = {
  id: <?= (int)$userId ?>,
  name: <?= json_encode($userName) ?>,
  email: <?= json_encode($userEmail) ?>,
  role: <?= json_encode($userRole) ?>,
  is_superadmin: <?= json_encode((bool)$isSuperAdmin) ?>,
  is_simulating: <?= json_encode((bool)$isSimulating) ?>,
  simulated_role: <?= json_encode($simulatedRole) ?>,
  gimnasio_id: <?= json_encode($gimnasioId) ?>,
  gimnasio_nombre: <?= json_encode($gimnasioNombre) ?>,
  audit_gym_id: <?= json_encode($auditGymId) ?>,
  profesor_id: <?= json_encode($profesorId) ?>,
  alumno_id: <?= json_encode($alumnoId) ?>,
  plan_tipo: <?= json_encode($gimnasioPlanTipo) ?>,
  is_plan_pro: <?= json_encode((bool)$isPlanPro) ?>
};
</script>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../../assets/js/app.js') ?>"></script>
</body>
</html>