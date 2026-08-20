<?php
require_once __DIR__ . '/proteger.php';

/**************************************************************
 * GYM PRO SaaS - PANEL MULTI-TENANT (NITSOF PATTERN)
 * 1. SuperAdmin (Plataforma, SaaS, Auditoría global de Sedes)
 * 2. Dueños (Inquilinos / Sedes Aisladas)
 * 3. Coaches & Alumnos (Operación aislada por gimnasio_id)
 **************************************************************/

$currentGymId = getEffectiveGymId();

/* ===== Helpers Backend ===== */
function jsonOut(bool $ok = true, $data = [], string $msg = ''): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'data' => $data, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function input(string $k, $d = null) {
    return $_POST[$k] ?? $_GET[$k] ?? $d;
}

function hoy(): string {
    return (new DateTime())->format('Y-m-d');
}

function ymHoy(): string {
    return (new DateTime())->format('Y-m');
}

function inicioSemana(): string {
    $d = new DateTime('monday this week');
    return $d->format('Y-m-d');
}

function finSemana(): string {
    $d = new DateTime('sunday this week');
    return $d->format('Y-m-d');
}

function calcVencimiento(string $base, string $plan): string {
    $d = new DateTime($base);
    $d->modify('+30 days');
    return $d->format('Y-m-d');
}

function estadoAlumno(string $venc): string {
    $v = new DateTime($venc);
    $h = new DateTime('today');
    return ($v >= $h) ? 'activo' : 'vencido';
}

function fmtFecha(?string $f): string {
    if (!$f) return '-';
    $p = explode('-', explode(' ', trim($f))[0]);
    return (count($p) === 3 && strlen($p[0]) === 4) ? "{$p[2]}/{$p[1]}/{$p[0]}" : $f;
}

function getPlanPrecios(PDO $pdo): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $rows = $pdo->query("SELECT plan, precio FROM plan_precios")->fetchAll();
    $cache = [];
    foreach ($rows as $r) {
        $cache[$r['plan']] = (float)$r['precio'];
    }
    return $cache;
}

function planPrice(PDO $pdo, string $plan): float {
    $pp = getPlanPrecios($pdo);
    return isset($pp[$plan]) ? (float)$pp[$plan] : 0.0;
}

function maintainAutoStates(PDO $pdo): void {
    $last = $pdo->query("SELECT alumno_id, MAX(fecha_pago) AS last_pago
                         FROM pagos
                         WHERE tipo='alumno' AND alumno_id IS NOT NULL
                         GROUP BY alumno_id")->fetchAll();
    $map = [];
    foreach ($last as $r) {
        $map[(int)$r['alumno_id']] = $r['last_pago'];
    }

    $al = $pdo->query("SELECT id, plan, fecha_inicio, fecha_vencimiento, estado FROM alumnos")->fetchAll();
    $upd = $pdo->prepare("UPDATE alumnos SET fecha_vencimiento=?, estado=? WHERE id=?");
    $hoy0 = new DateTime('today');

    foreach ($al as $a) {
        if ($a['estado'] === 'pausado') continue;
        $id = (int)$a['id'];
        $plan = $a['plan'] ?: '3x';
        $base = $map[$id] ?? $a['fecha_inicio'];
        $venc = calcVencimiento($base, $plan);
        $est  = (new DateTime($venc) >= $hoy0) ? 'activo' : 'vencido';
        if ($venc !== $a['fecha_vencimiento'] || $est !== $a['estado']) {
            $upd->execute([$venc, $est, $id]);
        }
    }

    // Actualizar estados de suscripción de los gimnasios
    $gyms = $pdo->query("SELECT id, suscripcion_vencimiento, suscripcion_estado FROM gimnasios")->fetchAll();
    $updGym = $pdo->prepare("UPDATE gimnasios SET suscripcion_estado=? WHERE id=?");
    foreach ($gyms as $g) {
        if ($g['suscripcion_estado'] === 'suspendido') continue;
        if (empty($g['suscripcion_vencimiento'])) continue;
        $vencTs = strtotime($g['suscripcion_vencimiento']);
        $diffDias = (int)ceil(($vencTs - time()) / 86400);
        $nuevoEst = 'activo';
        if ($diffDias < 0) $nuevoEst = 'vencido';
        elseif ($diffDias <= 5) $nuevoEst = 'proximo';

        if ($nuevoEst !== $g['suscripcion_estado']) {
            $updGym->execute([$nuevoEst, $g['id']]);
        }
    }
}

maintainAutoStates($pdo);

/* ============================================================
 * ROUTER AJAX MULTI-TENANT (SUPERADMIN, DUEÑO, COACH, ALUMNO)
 * ============================================================ */
if (isset($_GET['ajax'])) {
    maintainAutoStates($pdo);
    $action = $_GET['ajax'];

    /* -------------------------------------------------------------
     * ENDPOINTS SAAS / SUPERADMIN & AUDITORÍA
     * ------------------------------------------------------------- */
    if ($action === 'saas.gimnasios.list') {
        requireRole(ROLE_ADMIN_GENERAL, true);
        $st = $pdo->query("
            SELECT g.*, u.nombre_usuario AS dueno_usuario, u.email AS dueno_email, u.telefono AS dueno_tel,
                   u.activo AS dueno_activo,
                   (SELECT COUNT(*) FROM alumnos WHERE gimnasio_id = g.id) AS total_alumnos_gym,
                   (SELECT COUNT(*) FROM profesores WHERE gimnasio_id = g.id) AS total_profes_gym,
                   DATEDIFF(g.suscripcion_vencimiento, CURDATE()) AS dias_para_vencer
            FROM gimnasios g
            LEFT JOIN users u ON u.id = g.dueno_id
            ORDER BY g.id DESC
        ");
        $rows = $st->fetchAll();
        jsonOut(true, $rows);
    }

    if ($action === 'saas.switch_audit') {
        requireRole(ROLE_ADMIN_GENERAL, true);
        $gymId = (int)input('gimnasio_id', 0);
        $_SESSION['audit_gym_id'] = $gymId > 0 ? $gymId : null;
        jsonOut(true, ['audit_gym_id' => $_SESSION['audit_gym_id']], 'Modo de auditoría actualizado');
    }

    if ($action === 'saas.gimnasios.save') {
        requireRole(ROLE_ADMIN_GENERAL, true);
        $id        = (int)input('id', 0);
        $nombre    = trim(input('nombre', ''));
        $telefono  = trim(input('telefono', ''));
        $email     = trim(input('email', ''));
        $direccion = trim(input('direccion', ''));
        $monto     = (float)input('suscripcion_monto', 45000);
        $venc      = input('suscripcion_vencimiento', date('Y-m-d', strtotime('+30 days')));
        $estado    = input('suscripcion_estado', 'activo');
        $code      = trim(input('invite_code', '')) ?: strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $nombre), 0, 8) . rand(100, 999));

        $duenoUser = trim(input('dueno_usuario', ''));
        $duenoPass = input('dueno_password', '');

        if ($nombre === '') jsonOut(false, [], 'El nombre del gimnasio es obligatorio');

        if ($id > 0) {
            $pdo->prepare("UPDATE gimnasios SET nombre=?, invite_code=?, telefono=?, email=?, direccion=?, suscripcion_monto=?, suscripcion_vencimiento=?, suscripcion_estado=? WHERE id=?")
                ->execute([$nombre, $code, $telefono, $email, $direccion, $monto, $venc, $estado, $id]);
            
            $duenoId = (int)$pdo->query("SELECT dueno_id FROM gimnasios WHERE id=$id")->fetchColumn();
            if ($duenoId > 0 && $duenoPass !== '') {
                $hash = hashPassword($duenoPass);
                $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $duenoId]);
            }
        } else {
            if ($duenoUser === '' || strlen($duenoPass) < 6) {
                jsonOut(false, [], 'Debes indicar un usuario y contraseña de al menos 6 caracteres para el Dueño.');
            }
            $hash = hashPassword($duenoPass);
            $stDueno = $pdo->prepare("INSERT INTO users (nombre_usuario, email, password_hash, rol, activo, telefono) VALUES (?, ?, ?, 'dueno', 1, ?)");
            $stDueno->execute([$duenoUser, $email ?: ($duenoUser . '@gym.com'), $hash, $telefono]);
            $newDuenoId = (int)$pdo->lastInsertId();

            $pdo->prepare("INSERT INTO gimnasios (nombre, invite_code, dueno_id, telefono, email, direccion, suscripcion_monto, suscripcion_vencimiento, suscripcion_estado) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$nombre, $code, $newDuenoId, $telefono, $email, $direccion, $monto, $venc, $estado]);
            $id = (int)$pdo->lastInsertId();

            $pdo->prepare("UPDATE users SET gimnasio_id=? WHERE id=?")->execute([$id, $newDuenoId]);

            // Generar invitación por defecto
            $pdo->prepare("INSERT INTO invitaciones (gimnasio_id, token, rol, usos_restantes) VALUES (?, ?, 'alumno', 500)")
                ->execute([$id, $code . '_ALUMNO']);
        }

        jsonOut(true, ['id' => $id], 'Gimnasio y Dueño guardados exitosamente');
    }

    if ($action === 'saas.gimnasios.toggle_suspension') {
        requireRole(ROLE_ADMIN_GENERAL, true);
        $id = (int)input('id', 0);
        $estadoActual = input('estado_actual', '');
        $nuevoEstado = ($estadoActual === 'suspendido') ? 'activo' : 'suspendido';

        $pdo->prepare("UPDATE gimnasios SET suscripcion_estado=? WHERE id=?")->execute([$nuevoEstado, $id]);
        $duenoId = (int)$pdo->query("SELECT dueno_id FROM gimnasios WHERE id=$id")->fetchColumn();
        if ($duenoId > 0) {
            $pdo->prepare("UPDATE users SET activo=? WHERE id=?")->execute([($nuevoEstado === 'suspendido' ? 0 : 1), $duenoId]);
        }
        jsonOut(true, ['nuevo_estado' => $nuevoEstado], 'Estado de suscripción actualizado');
    }

    if ($action === 'saas.pagos.save') {
        requireRole(ROLE_ADMIN_GENERAL, true);
        $gymId  = (int)input('gimnasio_id', 0);
        $monto  = (float)input('monto', 0);
        $fecha  = input('fecha_pago', hoy());
        $periodo = input('periodo_mes', ymHoy());
        $medio  = input('medio_pago', 'transferencia');
        $comp   = trim(input('comprobante', ''));
        $obs    = trim(input('observaciones', ''));

        if (!$gymId || $monto <= 0) jsonOut(false, [], 'Gimnasio y monto válido requeridos');

        $duenoId = (int)$pdo->query("SELECT dueno_id FROM gimnasios WHERE id=$gymId")->fetchColumn();

        $pdo->prepare("INSERT INTO pagos_plataforma (gimnasio_id, dueno_id, monto, fecha_pago, periodo_mes, medio_pago, comprobante, observaciones) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$gymId, $duenoId, $monto, $fecha, $periodo, $medio, $comp, $obs]);

        $nuevoVenc = date('Y-m-d', strtotime('+30 days', strtotime($fecha)));
        $pdo->prepare("UPDATE gimnasios SET suscripcion_vencimiento=?, suscripcion_estado='activo' WHERE id=?")
            ->execute([$nuevoVenc, $gymId]);

        if ($duenoId > 0) {
            $pdo->prepare("UPDATE users SET activo=1 WHERE id=?")->execute([$duenoId]);
        }

        jsonOut(true, [], 'Pago de suscripción asentado y servicio renovado');
    }

    if ($action === 'saas.pagos.list') {
        requireRole(ROLE_ADMIN_GENERAL, true);
        $st = $pdo->query("
            SELECT pp.*, g.nombre AS gimnasio_nombre, u.nombre_usuario AS dueno_nombre
            FROM pagos_plataforma pp
            LEFT JOIN gimnasios g ON g.id = pp.gimnasio_id
            LEFT JOIN users u ON u.id = pp.dueno_id
            ORDER BY pp.fecha_pago DESC, pp.id DESC
        ");
        jsonOut(true, $st->fetchAll());
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE ENLACES DE INVITACIÓN DIRECTA (MULTI-TENANT)
     * ------------------------------------------------------------- */
    if ($action === 'invitaciones.get_links') {
        $gymId = $currentGymId ?: 1;
        $gymRow = $pdo->query("SELECT nombre, invite_code FROM gimnasios WHERE id=$gymId")->fetch() ?: [];
        $code = $gymRow['invite_code'] ?: ('GYM_' . $gymId);
        
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = "http://{$host}/gimnasio/register.php?invite=";

        jsonOut(true, [
            'gym_nombre' => $gymRow['nombre'] ?? 'Gimnasio',
            'link_alumno' => $base . $code,
            'link_coach'  => $base . $code . '_COACH',
            'code'        => $code
        ]);
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE CONCURRENCIA / ASISTENCIAS (AISLADO POR GYM_ID)
     * ------------------------------------------------------------- */
    if ($action === 'asistencias.list') {
        $aluId = (int)input('alumno_id', 0);
        $sql = "SELECT asis.*, al.nombre AS alumno_nombre, al.plan, al.estado AS alumno_estado, pr.nombre AS coach_nombre
                FROM asistencias asis
                LEFT JOIN alumnos al ON al.id = asis.alumno_id
                LEFT JOIN profesores pr ON pr.id = asis.coach_id
                WHERE 1=1";
        $p = [];

        if ($currentGymId) {
            $sql .= " AND asis.gimnasio_id = ?";
            $p[] = $currentGymId;
        }

        if (hasRole(ROLE_ALUMNO)) {
            $sql .= " AND asis.alumno_id = ?";
            $p[] = $alumnoId ?: 0;
        } elseif (hasRole(ROLE_COACH)) {
            $sql .= " AND (asis.coach_id = ? OR al.profesor_id = ?)";
            $p[] = $profesorId ?: 0;
            $p[] = $profesorId ?: 0;
        }

        if ($aluId > 0) {
            $sql .= " AND asis.alumno_id = ?";
            $p[] = $aluId;
        }

        $sql .= " ORDER BY asis.fecha DESC, asis.hora DESC LIMIT 100";
        $st = $pdo->prepare($sql);
        $st->execute($p);
        jsonOut(true, $st->fetchAll());
    }

    if ($action === 'asistencias.checkin') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $aluId = (int)input('alumno_id', 0);
        $obs   = trim(input('observaciones', 'Ingreso al gimnasio'));
        if (!$aluId) jsonOut(false, [], 'Seleccioná un alumno para registrar asistencia');

        $stAlu = $pdo->prepare("SELECT gimnasio_id, profesor_id, estado, fecha_vencimiento FROM alumnos WHERE id=?");
        $stAlu->execute([$aluId]);
        $aluData = $stAlu->fetch();
        if (!$aluData) jsonOut(false, [], 'Alumno no encontrado');

        $gymDest = $aluData['gimnasio_id'] ?: ($currentGymId ?: 1);
        $hora = (new DateTime())->format('H:i:s');
        $fecha = hoy();

        $pdo->prepare("INSERT INTO asistencias (alumno_id, gimnasio_id, coach_id, fecha, hora, observaciones) VALUES (?,?,?,?,?,?)")
            ->execute([$aluId, $gymDest, $aluData['profesor_id'] ?: $profesorId, $fecha, $hora, $obs]);

        jsonOut(true, [
            'alumno_estado' => $aluData['estado'],
            'vencimiento'   => $aluData['fecha_vencimiento']
        ], '¡Ingreso registrado correctamente!');
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE RUTINAS (AISLADO POR GYM_ID)
     * ------------------------------------------------------------- */
    if ($action === 'rutinas.list') {
        $aluId = (int)input('alumno_id', 0);
        $sql = "SELECT r.*, al.nombre AS alumno_nombre, al.telefono AS alumno_tel, p.nombre AS coach_nombre
                FROM rutinas r
                LEFT JOIN alumnos al ON al.id = r.alumno_id
                LEFT JOIN profesores p ON p.id = r.coach_id
                WHERE 1=1";
        $p = [];

        if ($currentGymId) {
            $sql .= " AND r.gimnasio_id = ?";
            $p[] = $currentGymId;
        }

        if (hasRole(ROLE_ALUMNO)) {
            $sql .= " AND r.alumno_id = ?";
            $p[] = $alumnoId ?: 0;
        } elseif (hasRole(ROLE_COACH)) {
            $sql .= " AND (r.coach_id = ? OR al.profesor_id = ?)";
            $p[] = $profesorId ?: 0;
            $p[] = $profesorId ?: 0;
        }

        if ($aluId > 0) {
            $sql .= " AND r.alumno_id = ?";
            $p[] = $aluId;
        }

        $sql .= " ORDER BY r.id DESC";
        $st = $pdo->prepare($sql);
        $st->execute($p);
        jsonOut(true, $st->fetchAll());
    }

    if ($action === 'rutinas.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $id      = (int)input('id', 0);
        $aluId   = (int)input('alumno_id', 0);
        $coachId = hasRole(ROLE_COACH) ? $profesorId : ((int)input('coach_id', 0) ?: null);
        $titulo  = trim(input('titulo', ''));
        $obj     = trim(input('objetivo', 'Fuerza / Hipertrofia'));
        $dias    = trim(input('dias_semana', 'Lunes a Viernes'));
        $det     = trim(input('detalles', ''));
        $estado  = input('estado', 'activa');
        $gymDest = $currentGymId ?: 1;

        if (!$aluId || $titulo === '' || $det === '') {
            jsonOut(false, [], 'Alumno, título y detalle de ejercicios obligatorios');
        }

        if ($id > 0) {
            $pdo->prepare("UPDATE rutinas SET alumno_id=?, coach_id=?, titulo=?, objetivo=?, dias_semana=?, detalles=?, estado=? WHERE id=?")
                ->execute([$aluId, $coachId, $titulo, $obj, $dias, $det, $estado, $id]);
        } else {
            $pdo->prepare("INSERT INTO rutinas (gimnasio_id, alumno_id, coach_id, titulo, objetivo, dias_semana, detalles, fecha_asignacion, estado) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$gymDest, $aluId, $coachId, $titulo, $obj, $dias, $det, hoy(), $estado]);
        }
        jsonOut(true, [], 'Rutina guardada exitosamente');
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE NUTRICIÓN (AISLADO POR GYM_ID)
     * ------------------------------------------------------------- */
    if ($action === 'nutricion.list') {
        $aluId = (int)input('alumno_id', 0);
        $sql = "SELECT pn.*, al.nombre AS alumno_nombre, p.nombre AS coach_nombre
                FROM planes_nutricionales pn
                LEFT JOIN alumnos al ON al.id = pn.alumno_id
                LEFT JOIN profesores p ON p.id = pn.coach_id
                WHERE 1=1";
        $p = [];

        if ($currentGymId) {
            $sql .= " AND pn.gimnasio_id = ?";
            $p[] = $currentGymId;
        }

        if (hasRole(ROLE_ALUMNO)) {
            $sql .= " AND pn.alumno_id = ?";
            $p[] = $alumnoId ?: 0;
        } elseif (hasRole(ROLE_COACH)) {
            $sql .= " AND (pn.coach_id = ? OR al.profesor_id = ?)";
            $p[] = $profesorId ?: 0;
            $p[] = $profesorId ?: 0;
        }

        if ($aluId > 0) {
            $sql .= " AND pn.alumno_id = ?";
            $p[] = $aluId;
        }

        $sql .= " ORDER BY pn.id DESC";
        $st = $pdo->prepare($sql);
        $st->execute($p);
        jsonOut(true, $st->fetchAll());
    }

    if ($action === 'nutricion.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $id      = (int)input('id', 0);
        $aluId   = (int)input('alumno_id', 0);
        $coachId = hasRole(ROLE_COACH) ? $profesorId : ((int)input('coach_id', 0) ?: null);
        $titulo  = trim(input('titulo', ''));
        $cal     = (int)input('calorias_aprox', 2200);
        $det     = trim(input('detalles', ''));
        $estado  = input('estado', 'activo');
        $gymDest = $currentGymId ?: 1;

        if (!$aluId || $titulo === '' || $det === '') {
            jsonOut(false, [], 'Alumno, título y menú nutricional obligatorios');
        }

        if ($id > 0) {
            $pdo->prepare("UPDATE planes_nutricionales SET alumno_id=?, coach_id=?, titulo=?, calorias_aprox=?, detalles=?, estado=? WHERE id=?")
                ->execute([$aluId, $coachId, $titulo, $cal, $det, $estado, $id]);
        } else {
            $pdo->prepare("INSERT INTO planes_nutricionales (gimnasio_id, alumno_id, coach_id, titulo, calorias_aprox, detalles, fecha_asignacion, estado) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$gymDest, $aluId, $coachId, $titulo, $cal, $det, hoy(), $estado]);
        }
        jsonOut(true, [], 'Plan nutricional guardado exitosamente');
    }

    /* -------------------------------------------------------------
     * DASHBOARD KPIs MULTI-TENANT (SUPERADMIN, DUEÑO, COACH, ALUMNO)
     * ------------------------------------------------------------- */
    if ($action === 'dashboard.kpis') {
        $ym = ymHoy();

        if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO])) {
            $isSuper = hasRole(ROLE_ADMIN_GENERAL);
            $gymFilter = $currentGymId ? " WHERE gimnasio_id = $currentGymId" : "";
            $gymFilterAnd = $currentGymId ? " AND gimnasio_id = $currentGymId" : "";

            // Métricas del Gimnasio
            $totalAlu  = (int)$pdo->query("SELECT COUNT(*) FROM alumnos $gymFilter")->fetchColumn();

            // Cálculo exacto de alumnos al día (pagaron su cuota del mes) vs alumnos con saldo pendiente / deuda
            $sqlAlus = "
                SELECT a.id, a.nombre, a.dni, a.telefono, a.plan, a.estado, a.fecha_vencimiento, COALESCE(pa.total_mes, 0) AS abonado_mes
                FROM alumnos a
                LEFT JOIN (
                    SELECT alumno_id, SUM(monto) AS total_mes 
                    FROM pagos 
                    WHERE tipo='alumno' AND DATE_FORMAT(fecha_pago,'%Y-%m')='$ym'
                    GROUP BY alumno_id
                ) pa ON pa.alumno_id = a.id
                $gymFilter
                ORDER BY a.nombre ASC
            ";
            $allAlus = $pdo->query($sqlAlus)->fetchAll();
            $planPrecios = getPlanPrecios($pdo);

            $pagaronAlu   = 0;
            $deudaAlu     = 0;
            $activosCount = 0;
            $vencCount    = 0;
            $pausCount    = 0;
            $alusAlDia    = [];
            $alusConDeuda = [];

            foreach ($allAlus as $al) {
                $cuota  = $planPrecios[$al['plan']] ?? 30000;
                $ab     = (float)$al['abonado_mes'];
                $saldo  = max(0, $cuota - $ab);
                $isPaid = ($ab >= $cuota && $al['estado'] !== 'vencido');

                $item = [
                    'id'          => (int)$al['id'],
                    'nombre'      => $al['nombre'],
                    'dni'         => $al['dni'] ?? '',
                    'plan'        => $al['plan'],
                    'cuota'       => $cuota,
                    'abonado'     => $ab,
                    'saldo'       => $saldo,
                    'estado'      => $al['estado'],
                    'vencimiento' => $al['fecha_vencimiento']
                ];

                if ($isPaid) {
                    $pagaronAlu++;
                    $alusAlDia[] = $item;
                } else {
                    $deudaAlu++;
                    $alusConDeuda[] = $item;
                }

                if ($al['estado'] === 'activo') $activosCount++;
                elseif ($al['estado'] === 'vencido') $vencCount++;
                elseif ($al['estado'] === 'pausado') $pausCount++;
            }

            // Coaches del Gimnasio
            $sqlProf = "
                SELECT p.id, p.nombre, p.telefono, p.cuota_mensual, COALESCE(pp.total_mes, 0) AS pagado_mes
                FROM profesores p
                LEFT JOIN (
                    SELECT profesor_id, SUM(monto) AS total_mes
                    FROM pagos
                    WHERE tipo='profesor' AND DATE_FORMAT(fecha_pago, '%Y-%m')='$ym'
                    GROUP BY profesor_id
                ) pp ON pp.profesor_id = p.id
                $gymFilter
                ORDER BY p.nombre ASC
            ";
            $allProfes = $pdo->query($sqlProf)->fetchAll();
            $profesAlDia = [];
            $profesConDeuda = [];

            foreach ($allProfes as $pr) {
                $cuota = (float)$pr['cuota_mensual'];
                $pagado = (float)$pr['pagado_mes'];
                $saldo = max(0, $cuota - $pagado);
                $isPaid = ($pagado >= $cuota && $cuota > 0);

                $pItem = [
                    'id'      => (int)$pr['id'],
                    'nombre'  => $pr['nombre'],
                    'cuota'   => $cuota,
                    'pagado'  => $pagado,
                    'saldo'   => $saldo
                ];

                if ($isPaid || ($cuota == 0 && $pagado > 0)) {
                    $profesAlDia[] = $pItem;
                } else {
                    $profesConDeuda[] = $pItem;
                }
            }

            $totalProf    = count($allProfes);
            $pagadosProf  = count($profesAlDia);
            $deudaProf    = count($profesConDeuda);

            // Recaudación
            $h    = hoy();
            $dSem = inicioSemana();
            $hSem = finSemana();
            $diaTot = (float)$pdo->query("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE fecha_pago='$h' $gymFilterAnd")->fetchColumn();
            $semTot = (float)$pdo->query("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE fecha_pago BETWEEN '$dSem' AND '$hSem' $gymFilterAnd")->fetchColumn();
            $ingMes = (float)$pdo->query("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE DATE_FORMAT(fecha_pago,'%Y-%m')='$ym' $gymFilterAnd")->fetchColumn();

            // Concurrencia de hoy
            $asistHoy = (int)$pdo->query("SELECT COUNT(*) FROM asistencias WHERE fecha='$h' $gymFilterAnd")->fetchColumn();

            // Próximos vencimientos
            $sqlProx = "SELECT id, nombre, telefono, plan, fecha_vencimiento, estado 
                        FROM alumnos 
                        WHERE estado='activo' AND DATEDIFF(fecha_vencimiento, CURDATE()) BETWEEN 0 AND ?" . $gymFilterAnd . "
                        ORDER BY fecha_vencimiento ASC";
            $proxAlu = $pdo->prepare($sqlProx);
            $proxAlu->execute([ALERTA_DIAS_ALUMNO]);
            $prox = $proxAlu->fetchAll();

            // Datos SaaS si es SuperAdmin
            $saasData = [];
            $allGymsList = [];
            if ($isSuper) {
                $totalGyms = (int)$pdo->query("SELECT COUNT(*) FROM gimnasios")->fetchColumn();
                $gymsActivos = (int)$pdo->query("SELECT COUNT(*) FROM gimnasios WHERE suscripcion_estado='activo'")->fetchColumn();
                $gymsProx = (int)$pdo->query("SELECT COUNT(*) FROM gimnasios WHERE suscripcion_estado='proximo'")->fetchColumn();
                $gymsVenc = (int)$pdo->query("SELECT COUNT(*) FROM gimnasios WHERE suscripcion_estado IN ('vencido', 'suspendido')")->fetchColumn();
                $saasIngMes = (float)$pdo->query("SELECT COALESCE(SUM(monto),0) FROM pagos_plataforma WHERE DATE_FORMAT(fecha_pago,'%Y-%m')='$ym'")->fetchColumn();
                $saasIngHoy = (float)$pdo->query("SELECT COALESCE(SUM(monto),0) FROM pagos_plataforma WHERE fecha_pago = CURDATE()")->fetchColumn();
                $saasIngAnio = (float)$pdo->query("SELECT COALESCE(SUM(monto),0) FROM pagos_plataforma WHERE YEAR(fecha_pago) = YEAR(CURDATE())")->fetchColumn();
                $saasPotencial = (float)$pdo->query("SELECT COALESCE(SUM(suscripcion_monto),0) FROM gimnasios")->fetchColumn();
                $saasCobranzaPct = $saasPotencial > 0 ? round(($saasIngMes / $saasPotencial) * 100, 1) : 100;

                // Cobros a dueños pendientes o próximos a vencer
                $stProxDueños = $pdo->query("
                    SELECT g.id, g.nombre, g.telefono, g.suscripcion_monto, g.suscripcion_vencimiento, g.suscripcion_estado,
                           u.nombre_usuario AS dueno_usuario, u.email AS dueno_email,
                           DATEDIFF(g.suscripcion_vencimiento, CURDATE()) AS dias_restantes
                    FROM gimnasios g
                    LEFT JOIN users u ON u.id = g.dueno_id
                    WHERE g.suscripcion_estado IN ('proximo', 'vencido', 'suspendido')
                       OR (g.suscripcion_vencimiento IS NOT NULL AND DATEDIFF(g.suscripcion_vencimiento, CURDATE()) <= 15)
                    ORDER BY g.suscripcion_vencimiento ASC
                ");
                $proxDueños = $stProxDueños->fetchAll();

                $saasData = [
                    'total_gyms'        => $totalGyms,
                    'gyms_activos'      => $gymsActivos,
                    'gyms_proximos'     => $gymsProx,
                    'gyms_vencidos'     => $gymsVenc,
                    'ingresos_mes'      => $saasIngMes,
                    'ingresos_hoy'      => $saasIngHoy,
                    'ingresos_anio'     => $saasIngAnio,
                    'potencial_mes'     => $saasPotencial,
                    'cobranza_pct'      => $saasCobranzaPct,
                    'prox_vencimientos' => $proxDueños
                ];

                $allGymsList = $pdo->query("
                    SELECT g.id, g.nombre, g.invite_code, g.suscripcion_estado, g.suscripcion_monto, g.suscripcion_vencimiento,
                           u.nombre_usuario AS dueno_usuario, u.email AS dueno_email,
                           (SELECT COUNT(*) FROM alumnos WHERE gimnasio_id = g.id) AS total_alumnos,
                           (SELECT COUNT(*) FROM profesores WHERE gimnasio_id = g.id) AS total_profes
                    FROM gimnasios g
                    LEFT JOIN users u ON u.id = g.dueno_id
                    ORDER BY g.id ASC
                ")->fetchAll();
            }

            // Datos de Suscripción del Gimnasio para el Dueño
            $gymInfo = $pdo->query("SELECT * FROM gimnasios WHERE id=" . ($currentGymId ?: 1) . " LIMIT 1")->fetch() ?: [];

            jsonOut(true, [
                'role'             => $userRole,
                'is_super'         => $isSuper,
                'effective_gym_id' => $currentGymId,
                'all_gyms'         => $allGymsList,
                'totales'     => [
                    'alumnos'             => $totalAlu,
                    'alumnos_activos'     => $activosCount,
                    'alumnos_vencidos'    => $vencCount,
                    'alumnos_pausados'    => $pausCount,
                    'alumnos_pagaron'     => $pagaronAlu,
                    'alumnos_deudores'    => $deudaAlu,
                    'profesores'          => $totalProf,
                    'profesores_pagados'  => $pagadosProf,
                    'ingresos_mes'        => $ingMes,
                    'asistencias_hoy'     => $asistHoy,
                    'cumplidos'           => $pagaronAlu + $pagadosProf,
                    'vencidos'            => $deudaAlu + $deudaProf
                ],
                'desglose' => [
                    'alumnos_total'      => count($allAlus),
                    'alumnos_pagaron'    => $pagaronAlu,
                    'alumnos_deudores'   => $deudaAlu,
                    'alumnos_al_dia'     => $alusAlDia,
                    'alumnos_con_deuda'  => $alusConDeuda,
                    'profesores_total'   => $totalProf,
                    'profesores_pagaron' => $pagadosProf,
                    'profesores_deuda'   => $deudaProf,
                    'profes_al_dia'      => $profesAlDia,
                    'profes_con_deuda'   => $profesConDeuda
                ],
                'recaudacion' => ['dia' => $diaTot, 'semana' => $semTot, 'mes' => $ingMes],
                'prox_vencimientos' => $prox,
                'saas'        => $saasData,
                'gym_info'    => $gymInfo
            ]);

        } elseif (hasRole(ROLE_COACH)) {
            $pId = $profesorId ?: 0;

            $stTot = $pdo->prepare("SELECT COUNT(*) FROM alumnos WHERE profesor_id = ?");
            $stTot->execute([$pId]); $totalAlu = (int)$stTot->fetchColumn();

            $stAct = $pdo->prepare("SELECT COUNT(*) FROM alumnos WHERE profesor_id = ? AND estado='activo'");
            $stAct->execute([$pId]); $activos = (int)$stAct->fetchColumn();

            $stVenc = $pdo->prepare("SELECT COUNT(*) FROM alumnos WHERE profesor_id = ? AND estado='vencido'");
            $stVenc->execute([$pId]); $vencAlu = (int)$stVenc->fetchColumn();

            // Total recaudado de sus alumnos en el mes
            $stGan = $pdo->prepare("
                SELECT COALESCE(SUM(pa.monto), 0)
                FROM pagos pa
                JOIN alumnos al ON al.id = pa.alumno_id
                WHERE al.profesor_id = ? AND DATE_FORMAT(pa.fecha_pago, '%Y-%m') = ?
            ");
            $stGan->execute([$pId, $ym]);
            $gananciaMes = (float)$stGan->fetchColumn();

            $ymPrev = (new DateTime('first day of last month'))->format('Y-m');
            $stGan->execute([$pId, $ymPrev]);
            $gananciaMesPrev = (float)$stGan->fetchColumn();
            $varGanancia = $gananciaMesPrev > 0 ? round(($gananciaMes - $gananciaMesPrev) / $gananciaMesPrev * 100, 1) : null;

            $dSem = inicioSemana(); $hSem = finSemana();
            $stAsis = $pdo->prepare("
                SELECT COUNT(*)
                FROM asistencias asis
                JOIN alumnos al ON al.id = asis.alumno_id
                WHERE al.profesor_id = ? AND asis.fecha BETWEEN ? AND ?
            ");
            $stAsis->execute([$pId, $dSem, $hSem]);
            $asistSemana = (int)$stAsis->fetchColumn();

            $stProx = $pdo->prepare("SELECT id, nombre, telefono, plan, fecha_vencimiento, estado 
                                     FROM alumnos 
                                     WHERE profesor_id = ? AND estado='activo' AND DATEDIFF(fecha_vencimiento, CURDATE()) BETWEEN 0 AND ?
                                     ORDER BY fecha_vencimiento ASC");
            $stProx->execute([$pId, ALERTA_DIAS_ALUMNO]);
            $prox = $stProx->fetchAll();

            $stProf = $pdo->prepare("SELECT * FROM profesores WHERE id=?");
            $stProf->execute([$pId]); $profData = $stProf->fetch() ?: [];

            jsonOut(true, [
                'role' => ROLE_COACH,
                'totales' => [
                    'alumnos'             => $totalAlu,
                    'alumnos_activos'     => $activos,
                    'alumnos_vencidos'    => $vencAlu,
                    'ganancia_mes'        => $gananciaMes,
                    'ganancia_mes_prev'   => $gananciaMesPrev,
                    'variacion_pct'       => $varGanancia,
                    'asistencias_semana'  => $asistSemana,
                    'cuota_mensual'       => (float)($profData['cuota_mensual'] ?? 0)
                ],
                'prox_vencimientos' => $prox
            ]);

        } else { // ROLE_ALUMNO
            $aId = $alumnoId ?: 0;
            $stAlu = $pdo->prepare("
                SELECT a.*, p.nombre AS coach_nombre, p.telefono AS coach_tel,
                       DATEDIFF(a.fecha_vencimiento, CURDATE()) AS dias_restantes
                FROM alumnos a
                LEFT JOIN profesores p ON p.id = a.profesor_id
                WHERE a.id = ?
            ");
            $stAlu->execute([$aId]);
            $aluData = $stAlu->fetch() ?: [];

            $cuota = planPrice($pdo, $aluData['plan'] ?? '3x');

            $stAbon = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE tipo='alumno' AND alumno_id=? AND DATE_FORMAT(fecha_pago, '%Y-%m')=?");
            $stAbon->execute([$aId, $ym]);
            $abonadoMes = (float)$stAbon->fetchColumn();
            $saldoDeuda = max(0, $cuota - $abonadoMes);
            $estaVencido = ($aluData['estado'] ?? '') === 'vencido' || ($saldoDeuda > 0 && (int)($aluData['dias_restantes'] ?? 0) < 0);

            $stAsisMes = $pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE alumno_id=? AND DATE_FORMAT(fecha, '%Y-%m')=?");
            $stAsisMes->execute([$aId, $ym]);
            $totalAsistenciasMes = (int)$stAsisMes->fetchColumn();

            $stAsisList = $pdo->prepare("SELECT fecha, hora, observaciones FROM asistencias WHERE alumno_id=? ORDER BY fecha DESC, hora DESC LIMIT 10");
            $stAsisList->execute([$aId]);
            $historialAsistencias = $stAsisList->fetchAll();

            $stRut = $pdo->prepare("SELECT * FROM rutinas WHERE alumno_id=? AND estado='activa' ORDER BY id DESC LIMIT 1");
            $stRut->execute([$aId]);
            $rutinaActiva = $stRut->fetch();

            $stNut = $pdo->prepare("SELECT * FROM planes_nutricionales WHERE alumno_id=? AND estado='activo' ORDER BY id DESC LIMIT 1");
            $stNut->execute([$aId]);
            $planNutri = $stNut->fetch();

            $stPagos = $pdo->prepare("SELECT * FROM pagos WHERE tipo='alumno' AND alumno_id=? ORDER BY fecha_pago DESC LIMIT 10");
            $stPagos->execute([$aId]);
            $misPagos = $stPagos->fetchAll();

            jsonOut(true, [
                'role'                 => ROLE_ALUMNO,
                'alumno'               => $aluData,
                'cuota'                => $cuota,
                'abonado_mes'          => $abonadoMes,
                'saldo_deuda'          => $saldoDeuda,
                'esta_vencido'         => $estaVencido,
                'asistencias_mes'      => $totalAsistenciasMes,
                'historial_asistencias'=> $historialAsistencias,
                'rutina'               => $rutinaActiva,
                'nutricion'            => $planNutri,
                'mis_pagos'            => $misPagos
            ]);
        }
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE ALUMNOS (AISLADO POR GYM_ID)
     * ------------------------------------------------------------- */
    if ($action === 'alumnos.list') {
        $q      = trim(input('q', ''));
        $plan   = input('plan', '');
        $estado = input('estado', '');
        $prof   = (int)input('profesor_id', 0);
        $ym     = ymHoy();

        $sql = "SELECT a.*, p.nombre AS profesor, COALESCE(pa.total_mes,0) AS abonado_mes,
                       DATEDIFF(a.fecha_vencimiento, CURDATE()) AS dias_restantes
                FROM alumnos a
                LEFT JOIN profesores p ON p.id = a.profesor_id
                LEFT JOIN (
                    SELECT alumno_id, SUM(monto) AS total_mes 
                    FROM pagos 
                    WHERE tipo='alumno' AND DATE_FORMAT(fecha_pago,'%Y-%m')=? 
                    GROUP BY alumno_id
                ) pa ON pa.alumno_id = a.id
                WHERE 1=1";
        $params = [$ym];

        if ($currentGymId) {
            $sql .= " AND a.gimnasio_id = ?";
            $params[] = $currentGymId;
        }

        if (hasRole(ROLE_COACH)) {
            $sql .= " AND a.profesor_id = ?";
            $params[] = $profesorId ?: 0;
        } elseif (hasRole(ROLE_ALUMNO)) {
            $sql .= " AND a.id = ?";
            $params[] = $alumnoId ?: 0;
        } else {
            if ($prof > 0) {
                $sql .= " AND a.profesor_id = ?";
                $params[] = $prof;
            }
        }

        if ($q !== '') {
            $sql .= " AND (a.nombre LIKE ? OR a.dni LIKE ? OR a.telefono LIKE ? OR a.actividades LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if (in_array($plan, ['3x', 'full', 'clase'], true)) {
            $sql .= " AND a.plan=?";
            $params[] = $plan;
        }
        if (in_array($estado, ['activo', 'vencido', 'pausado'], true)) {
            $sql .= " AND a.estado=?";
            $params[] = $estado;
        }

        $sql .= " ORDER BY a.id DESC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        foreach ($rows as &$r) {
            $cuota = planPrice($pdo, $r['plan'] ?? '3x');
            $ab    = (float)($r['abonado_mes'] ?? 0);
            $r['cuota_mes']  = $cuota;
            $r['saldo_mes']  = max(0, $cuota - $ab);
            $r['alerta']     = ($r['estado'] === 'activo' && $r['dias_restantes'] !== null && (int)$r['dias_restantes'] >= 0 && (int)$r['dias_restantes'] <= ALERTA_DIAS_ALUMNO) ? 'proximo' : 'none';
        }
        unset($r);
        jsonOut(true, $rows);
    }

    if ($action === 'alumnos.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $id          = (int)input('id', 0);
        $nombre      = trim(input('nombre', ''));
        $dni         = trim(input('dni', ''));
        $telefono    = trim(input('telefono', ''));
        $email       = trim(input('email', ''));
        $plan        = input('plan', '3x');
        $actividades = trim(input('actividades', 'Musculación'));
        $ini         = input('fecha_inicio', hoy());
        $venc        = input('fecha_vencimiento', '') ?: calcVencimiento($ini, $plan);
        $est         = input('estado', '') ?: estadoAlumno($venc);
        $prof        = hasRole(ROLE_COACH) ? $profesorId : ((int)input('profesor_id', 0) ?: null);
        $esgym       = (int)input('es_del_gym', 1);
        $gymDest     = $currentGymId ?: 1;

        if ($nombre === '' || mb_strlen($nombre) < 3) {
            jsonOut(false, [], 'El nombre completo es obligatorio y debe tener al menos 3 caracteres.');
        }
        if (preg_match('/\d/', $nombre)) {
            jsonOut(false, [], 'El nombre no debe contener caracteres numéricos.');
        }

        // Validación y control de DNI
        if ($dni === '') {
            jsonOut(false, [], 'El DNI es obligatorio.');
        }
        if (preg_match('/[a-zA-Z]/', $dni)) {
            jsonOut(false, [], 'El DNI no puede contener letras.');
        }
        $cleanDni = preg_replace('/\D/', '', $dni);
        if (strlen($cleanDni) < 7 || strlen($cleanDni) > 9) {
            jsonOut(false, [], 'El DNI debe contener entre 7 y 9 dígitos numéricos.');
        }
        $dni = $cleanDni;

        // Control de duplicados por DNI en el mismo gimnasio
        $stDni = $pdo->prepare("SELECT id, nombre FROM alumnos WHERE gimnasio_id = ? AND dni = ? AND id != ? LIMIT 1");
        $stDni->execute([$gymDest, $dni, $id]);
        $dupDni = $stDni->fetch();
        if ($dupDni) {
            jsonOut(false, [], "Ya existe un alumno registrado con el DNI {$dni} (pertenece a '{$dupDni['nombre']}').");
        }

        // Control de duplicados por Nombre en el mismo gimnasio
        $stNom = $pdo->prepare("SELECT id, dni FROM alumnos WHERE gimnasio_id = ? AND LOWER(TRIM(nombre)) = LOWER(?) AND id != ? LIMIT 1");
        $stNom->execute([$gymDest, $nombre, $id]);
        $dupNom = $stNom->fetch();
        if ($dupNom) {
            jsonOut(false, [], "Ya existe un alumno registrado con el nombre '{$nombre}' en este gimnasio (DNI: " . ($dupNom['dni'] ?: 'Sin DNI') . ").");
        }

        if ($telefono !== '') {
            if (preg_match('/[a-zA-Z]/', $telefono)) {
                jsonOut(false, [], 'El teléfono no puede contener letras. Solo números y caracteres válidos (+, -).');
            }
            $cleanDigits = preg_replace('/\D/', '', $telefono);
            if (strlen($cleanDigits) < 7 || strlen($cleanDigits) > 15) {
                jsonOut(false, [], 'El teléfono debe contener entre 7 y 15 dígitos numéricos.');
            }
        }
        if ($ini !== '' && $venc !== '' && $venc < $ini) {
            jsonOut(false, [], 'La fecha de vencimiento no puede ser anterior a la fecha de inicio.');
        }

        if ($id > 0) {
            $pdo->prepare("UPDATE alumnos SET nombre=?, dni=?, telefono=?, email=?, plan=?, actividades=?, fecha_inicio=?, fecha_vencimiento=?, estado=?, profesor_id=?, es_del_gym=? WHERE id=?")
                ->execute([$nombre, $dni, $telefono, $email, $plan, $actividades, $ini, $venc, $est, $prof, $esgym, $id]);
        } else {
            $pdo->prepare("INSERT INTO alumnos (gimnasio_id, nombre, dni, telefono, email, plan, actividades, fecha_inicio, fecha_vencimiento, estado, profesor_id, es_del_gym) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$gymDest, $nombre, $dni, $telefono, $email, $plan, $actividades, $ini, $venc, $est, $prof, $esgym]);
            $id = (int)$pdo->lastInsertId();
        }

        $p_monto = (float)input('pago_monto', 0);
        if ($p_monto > 0) {
            $pdo->prepare("INSERT INTO pagos (gimnasio_id, tipo, alumno_id, monto, fecha_pago, plan, medio_pago, observaciones) VALUES (?, 'alumno', ?, ?, ?, ?, ?, 'Pago inicial registrado')")
                ->execute([$gymDest, $id, $p_monto, input('pago_fecha', hoy()), $plan, input('pago_medio', 'efectivo')]);
        }

        jsonOut(true, ['id' => $id], 'Alumno guardado correctamente');
    }

    if ($action === 'alumnos.delete') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $id = (int)input('id', 0);
        $pdo->prepare("DELETE FROM alumnos WHERE id=?")->execute([$id]);
        jsonOut(true, [], 'Alumno eliminado');
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE PROFESORES (AISLADO POR GYM_ID)
     * ------------------------------------------------------------- */
    if ($action === 'profesores.list') {
        $q  = trim(input('q', ''));
        $ym = ymHoy();
        $sql = "SELECT p.*, COUNT(a.id) AS total_alumnos, COALESCE(pm.total_mes,0) AS abonado_mes
                FROM profesores p
                LEFT JOIN alumnos a ON a.profesor_id = p.id
                LEFT JOIN (
                    SELECT profesor_id, SUM(monto) AS total_mes 
                    FROM pagos 
                    WHERE tipo='profesor' AND DATE_FORMAT(fecha_pago,'%Y-%m')=? 
                    GROUP BY profesor_id
                ) pm ON pm.profesor_id = p.id
                WHERE 1=1";
        $params = [$ym];

        if ($currentGymId) {
            $sql .= " AND p.gimnasio_id = ?";
            $params[] = $currentGymId;
        }

        if (hasRole(ROLE_COACH) && $profesorId) {
            $sql .= " AND p.id = ?";
            $params[] = $profesorId;
        }

        if ($q !== '') {
            $sql .= " AND (p.nombre LIKE ? OR p.telefono LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $sql .= " GROUP BY p.id ORDER BY p.id DESC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $cuota = (float)($r['cuota_mensual'] ?? 0);
            $abon  = (float)($r['abonado_mes'] ?? 0);
            $r['saldo_mes'] = max(0, $cuota - $abon);

            $stAlus = $pdo->prepare("
                SELECT id, nombre, telefono, plan, actividades, fecha_vencimiento, estado 
                FROM alumnos 
                WHERE profesor_id = ? 
                ORDER BY nombre ASC
            ");
            $stAlus->execute([$r['id']]);
            $r['alumnos_lista'] = $stAlus->fetchAll();
        }
        unset($r);
        jsonOut(true, $rows);
    }

    if ($action === 'profesores.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $id     = (int)input('id', 0);
        $nombre = trim(input('nombre', ''));
        $tel    = trim(input('telefono', ''));
        $cuota  = (float)input('cuota_mensual', 0);
        $fp     = input('fecha_pago', null);
        $obs    = trim(input('observaciones', ''));
        $gymDest = $currentGymId ?: 1;

        if ($nombre === '') jsonOut(false, [], 'Nombre obligatorio');

        if ($id > 0) {
            $pdo->prepare("UPDATE profesores SET nombre=?, telefono=?, cuota_mensual=?, fecha_pago=?, observaciones=? WHERE id=?")
                ->execute([$nombre, $tel, $cuota, $fp ?: null, $obs, $id]);
        } else {
            $pdo->prepare("INSERT INTO profesores (gimnasio_id, nombre, telefono, cuota_mensual, fecha_pago, observaciones) VALUES (?,?,?,?,?,?)")
                ->execute([$gymDest, $nombre, $tel, $cuota, $fp ?: null, $obs]);
        }
        jsonOut(true, [], 'Profesor guardado');
    }

    if ($action === 'profesores.delete') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $id = (int)input('id', 0);
        $pdo->prepare("UPDATE alumnos SET profesor_id=NULL WHERE profesor_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM profesores WHERE id=?")->execute([$id]);
        jsonOut(true, [], 'Coach / Profesor eliminado');
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE PAGOS (AISLADO POR GYM_ID)
     * ------------------------------------------------------------- */
    if ($action === 'pagos.list') {
        $q     = trim(input('q', ''));
        $tipo  = input('tipo', '');
        $medio = input('medio', '');
        $mes   = input('mes', '');

        $sql = "SELECT pa.*, al.nombre AS alumno, al.plan AS alumno_plan, pr.nombre AS profesor
                FROM pagos pa
                LEFT JOIN alumnos al ON al.id = pa.alumno_id
                LEFT JOIN profesores pr ON pr.id = pa.profesor_id
                WHERE 1=1";
        $p = [];

        if ($currentGymId) {
            $sql .= " AND pa.gimnasio_id = ?";
            $p[] = $currentGymId;
        }

        if (hasRole(ROLE_ALUMNO)) {
            $sql .= " AND pa.alumno_id = ?";
            $p[] = $alumnoId ?: 0;
        } elseif (hasRole(ROLE_COACH)) {
            $sql .= " AND (pa.profesor_id = ? OR al.profesor_id = ?)";
            $p[] = $profesorId ?: 0;
            $p[] = $profesorId ?: 0;
        }

        if ($tipo === 'alumno' || $tipo === 'profesor') {
            $sql .= " AND pa.tipo = ?";
            $p[] = $tipo;
        }

        if ($medio !== '') {
            $sql .= " AND pa.medio_pago = ?";
            $p[] = $medio;
        }

        if ($mes !== '') {
            $sql .= " AND DATE_FORMAT(pa.fecha_pago, '%Y-%m') = ?";
            $p[] = $mes;
        }

        if ($q !== '') {
            $sql .= " AND (al.nombre LIKE ? OR pr.nombre LIKE ? OR pa.observaciones LIKE ? OR pa.plan LIKE ? OR pa.medio_pago LIKE ?)";
            $p[] = '%' . $q . '%';
            $p[] = '%' . $q . '%';
            $p[] = '%' . $q . '%';
            $p[] = '%' . $q . '%';
            $p[] = '%' . $q . '%';
        }

        $sql .= " ORDER BY pa.fecha_pago DESC, pa.id DESC LIMIT 250";
        $st = $pdo->prepare($sql);
        $st->execute($p);
        jsonOut(true, $st->fetchAll());
    }

    if ($action === 'pagos.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $tipo  = input('tipo', 'alumno');
        $alu   = (int)input('alumno_id', 0) ?: null;
        $pro   = (int)input('profesor_id', 0) ?: null;
        $monto = (float)input('monto', 0);
        $fecha = input('fecha_pago', hoy());
        $plan  = input('plan', null);
        $medio = input('medio_pago', 'efectivo');
        $obs   = trim(input('observaciones', ''));
        $gymDest = $currentGymId ?: 1;
        $ym = ymHoy();

        if ($monto <= 0) {
            jsonOut(false, [], 'El monto a cobrar debe ser mayor a $ 0.');
        }

        if ($tipo === 'alumno') {
            if (!$alu) {
                jsonOut(false, [], 'Debés seleccionar un alumno.');
            }
            $stAl = $pdo->prepare("SELECT id, nombre, plan, estado FROM alumnos WHERE id = ? AND gimnasio_id = ? LIMIT 1");
            $stAl->execute([$alu, $gymDest]);
            $al = $stAl->fetch();
            if (!$al) {
                jsonOut(false, [], 'Alumno no encontrado en esta sede.');
            }

            $pl = $plan ?: ($al['plan'] ?? '3x');
            $cuotaPactada = planPrice($pdo, $pl);

            // Calcular cuánto lleva abonado en el mes
            $stAbonado = $pdo->prepare("
                SELECT COALESCE(SUM(monto), 0) 
                FROM pagos 
                WHERE tipo = 'alumno' AND alumno_id = ? AND DATE_FORMAT(fecha_pago, '%Y-%m') = ?
            ");
            $stAbonado->execute([$alu, $ym]);
            $abonadoMes = (float)$stAbonado->fetchColumn();
            $saldoPendiente = max(0, $cuotaPactada - $abonadoMes);

            // 1. Si ya pagó el 100% de la cuota pactada y está al día
            if ($abonadoMes >= $cuotaPactada && $al['estado'] !== 'vencido') {
                jsonOut(false, [], "El alumno '{$al['nombre']}' ya se encuentra AL DÍA para este mes (Abonó $ " . number_format($abonadoMes, 0, ',', '.') . "). No se puede cobrar de más.");
            }

            // 2. Control estricto: Ni más ni menos de lo pactado
            if (abs($monto - $saldoPendiente) > 0.01) {
                if ($monto > $saldoPendiente) {
                    jsonOut(false, [], "El monto ingresado ($ " . number_format($monto, 0, ',', '.') . ") supera la cuota pactada restante ($ " . number_format($saldoPendiente, 0, ',', '.') . "). Solo se permite cobrar exactamente lo pactado.");
                } else {
                    jsonOut(false, [], "El monto ingresado ($ " . number_format($monto, 0, ',', '.') . ") es menor al saldo pactado ($ " . number_format($saldoPendiente, 0, ',', '.') . "). Debe cobrarse el importe pactado exacto.");
                }
            }

            $plan = $pl;
        } elseif ($tipo === 'profesor') {
            if (!$pro) {
                jsonOut(false, [], 'Debés seleccionar un coach/profesor.');
            }
            $stPr = $pdo->prepare("SELECT id, nombre, cuota_mensual FROM profesores WHERE id = ? AND gimnasio_id = ? LIMIT 1");
            $stPr->execute([$pro, $gymDest]);
            $pr = $stPr->fetch();
            if (!$pr) {
                jsonOut(false, [], 'Coach no encontrado.');
            }

            $cuotaProf = (float)$pr['cuota_mensual'];
            if ($cuotaProf > 0) {
                $stPagProf = $pdo->prepare("
                    SELECT COALESCE(SUM(monto), 0) 
                    FROM pagos 
                    WHERE tipo = 'profesor' AND profesor_id = ? AND DATE_FORMAT(fecha_pago, '%Y-%m') = ?
                ");
                $stPagProf->execute([$pro, $ym]);
                $pagadoMes = (float)$stPagProf->fetchColumn();
                $saldoProf = max(0, $cuotaProf - $pagadoMes);

                if ($pagadoMes >= $cuotaProf) {
                    jsonOut(false, [], "El coach '{$pr['nombre']}' ya tiene sus honorarios mensuales pactados de $ " . number_format($cuotaProf, 0, ',', '.') . " totalmente liquidados.");
                }

                if (abs($monto - $saldoProf) > 0.01) {
                    jsonOut(false, [], "El pago al coach debe ser exactamente el honorario mensual pactado de $ " . number_format($saldoProf, 0, ',', '.') . " (ni más ni menos).");
                }
            }
        }

        $pdo->prepare("INSERT INTO pagos (gimnasio_id, tipo, alumno_id, profesor_id, monto, fecha_pago, plan, medio_pago, observaciones) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$gymDest, $tipo, $alu, $pro, $monto, $fecha, $plan, $medio, $obs]);

        if ($tipo === 'alumno' && $alu) {
            $nv = calcVencimiento($fecha, $plan);
            $pdo->prepare("UPDATE alumnos SET fecha_vencimiento=?, estado='activo' WHERE id=?")->execute([$nv, $alu]);
        }

        jsonOut(true, [], 'Pago registrado exitosamente');
    }

    /* --- Precios de Planes y Configuración --- */
    if ($action === 'config.get') {
        $rows = $pdo->query("SELECT plan, precio FROM plan_precios")->fetchAll();
        jsonOut(true, $rows);
    }

    if ($action === 'config.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $p3x    = (float)input('p3x', 0);
        $pfull  = (float)input('pfull', 0);
        $pclase = (float)input('pclase', 0);
        $pdo->prepare("REPLACE INTO plan_precios (plan, precio) VALUES ('3x', ?), ('full', ?), ('clase', ?)")
            ->execute([$p3x, $pfull, $pclase]);
        jsonOut(true, [], 'Precios actualizados');
    }

    if ($action === 'gym.get') {
        $targetId = $currentGymId ?: 1;
        $row = $pdo->query("SELECT * FROM gimnasios WHERE id=$targetId LIMIT 1")->fetch() ?: [];
        jsonOut(true, $row);
    }

    if ($action === 'gym.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $targetId = $currentGymId ?: 1;
        $nombre = trim(input('nombre', 'Gimnasio'));
        $tel    = trim(input('telefono', ''));
        $dir    = trim(input('direccion', ''));
        $code   = trim(input('invite_code', ''));
        $pdo->prepare("UPDATE gimnasios SET nombre=?, telefono=?, direccion=?, invite_code=? WHERE id=?")
            ->execute([$nombre, $tel, $dir, $code ?: null, $targetId]);
        jsonOut(true, [], 'Datos de sede guardados');
    }

    /* --- Gestión de Usuarios y Roles --- */
    if ($action === 'usuarios.list') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $sql = "SELECT u.id, u.nombre_usuario, u.email, u.telefono, u.rol, u.is_superadmin, u.gimnasio_id, u.profesor_id, u.alumno_id, u.activo, u.creado_en,
                       p.nombre AS profesor_nombre, a.nombre AS alumno_nombre, g.nombre AS gimnasio_nombre
                FROM users u
                LEFT JOIN profesores p ON p.id = u.profesor_id
                LEFT JOIN alumnos a ON a.id = u.alumno_id
                LEFT JOIN gimnasios g ON g.id = u.gimnasio_id
                WHERE 1=1";
        $p = [];
        if ($currentGymId) {
            $sql .= " AND u.gimnasio_id = ?";
            $p[] = $currentGymId;
        }
        $sql .= " ORDER BY u.id DESC";
        $st = $pdo->prepare($sql);
        $st->execute($p);
        jsonOut(true, $st->fetchAll());
    }

    if ($action === 'usuarios.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $id     = (int)input('id', 0);
        $user   = trim(input('nombre_usuario', ''));
        $email  = trim(input('email', ''));
        $tel    = trim(input('telefono', ''));
        $rol    = trim(input('rol', ROLE_ALUMNO));
        $pass   = input('password', '');
        $profId = (int)input('profesor_id', 0) ?: null;
        $aluId  = (int)input('alumno_id', 0) ?: null;
        $activo = (int)input('activo', 1);
        $gymDest = $currentGymId ?: ((int)input('gimnasio_id', 1) ?: 1);

        if ($user === '') jsonOut(false, [], 'Usuario obligatorio');

        if ($id > 0) {
            if ($pass !== '') {
                $hash = hashPassword($pass);
                $pdo->prepare("UPDATE users SET nombre_usuario=?, email=?, telefono=?, password_hash=?, rol=?, profesor_id=?, alumno_id=?, activo=? WHERE id=?")
                    ->execute([$user, $email, $tel, $hash, $rol, $profId, $aluId, $activo, $id]);
            } else {
                $pdo->prepare("UPDATE users SET nombre_usuario=?, email=?, telefono=?, rol=?, profesor_id=?, alumno_id=?, activo=? WHERE id=?")
                    ->execute([$user, $email, $tel, $rol, $profId, $aluId, $activo, $id]);
            }
        } else {
            if (strlen($pass) < 6) jsonOut(false, [], 'Contraseña mínima de 6 caracteres');
            $hash = hashPassword($pass);
            $pdo->prepare("INSERT INTO users (nombre_usuario, email, telefono, password_hash, rol, gimnasio_id, profesor_id, alumno_id, activo) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$user, $email, $tel, $hash, $rol, $gymDest, $profId, $aluId, $activo]);
        }
        jsonOut(true, [], 'Usuario guardado');
    }

    if ($action === 'usuarios.blanquear') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $userId = (int)input('user_id', 0);
        $aluId  = (int)input('alumno_id', 0);

        if ($aluId > 0 && !$userId) {
            $st = $pdo->prepare("SELECT id FROM users WHERE alumno_id = ? LIMIT 1");
            $st->execute([$aluId]);
            $userId = (int)$st->fetchColumn();
        }

        if (!$userId) {
            jsonOut(false, [], 'El socio no posee una cuenta de usuario web creada.');
        }

        $pdo->prepare("UPDATE users SET password_hash = NULL WHERE id = ?")->execute([$userId]);
        jsonOut(true, [], 'Contraseña blanqueada con éxito. El socio definirá su nueva contraseña al ingresar.');
    }

    /* --- Reportes Avanzados: Semanal (Barras), Mensual (Líneas) y Anual (Torta) --- */
    if ($action === 'reportes.avanzado') {
        $gymFilterAnd = $currentGymId ? " AND gimnasio_id = $currentGymId" : "";

        // 1. SEMANAL (Gráfica de Barras)
        $mon = inicioSemana(); $sun = finSemana();
        $q = $pdo->prepare("SELECT fecha_pago d, SUM(monto) t FROM pagos WHERE fecha_pago BETWEEN ? AND ?" . $gymFilterAnd . " GROUP BY d");
        $q->execute([$mon, $sun]);
        $mapSem = []; foreach ($q->fetchAll() as $r) { $mapSem[$r['d']] = (float)$r['t']; }

        $diasNombres = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $diasSemana = [];
        $totalSemana = 0;
        $d = new DateTime($mon);
        for ($i = 0; $i < 7; $i++) {
            $dx = $d->format('Y-m-d');
            $montoDia = $mapSem[$dx] ?? 0;
            $totalSemana += $montoDia;
            $diasSemana[] = [
                'fecha' => $dx,
                'label' => $diasNombres[$i],
                'sublabel' => $d->format('d/m'),
                'monto' => $montoDia
            ];
            $d->modify('+1 day');
        }

        // 2. MENSUAL (Gráfica de Líneas - Progreso 6 Meses)
        $mMes = $pdo->query("
            SELECT DATE_FORMAT(fecha_pago, '%Y-%m') ym, SUM(monto) t
            FROM pagos
            WHERE fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)" . $gymFilterAnd . "
            GROUP BY ym
            ORDER BY ym ASC
        ")->fetchAll();

        $mesesNombres = [
            '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
            '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'
        ];
        $serieMes = [];
        $totalMesUltimo = 0;
        foreach ($mMes as $r) {
            $partes = explode('-', $r['ym']);
            $lblMes = ($mesesNombres[$partes[1]] ?? $partes[1]) . ' ' . substr($partes[0], 2);
            $montoMes = (float)$r['t'];
            $serieMes[] = [
                'ym'    => $r['ym'],
                'label' => $lblMes,
                'monto' => $montoMes
            ];
            $totalMesUltimo = $montoMes;
        }

        // 3. ANUAL (Gráfica de Torta - Distribución por Concepto)
        $curYear = date('Y');
        $stAnual = $pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN tipo = 'alumno' AND plan = '3x' THEN monto ELSE 0 END), 0) AS plan_3x,
                COALESCE(SUM(CASE WHEN tipo = 'alumno' AND plan = 'full' THEN monto ELSE 0 END), 0) AS plan_full,
                COALESCE(SUM(CASE WHEN tipo = 'alumno' AND plan = 'clase' THEN monto ELSE 0 END), 0) AS plan_clase,
                COALESCE(SUM(CASE WHEN tipo = 'profesor' THEN monto ELSE 0 END), 0) AS pago_profes,
                COALESCE(SUM(monto), 0) AS total_anual
            FROM pagos
            WHERE YEAR(fecha_pago) = ?" . $gymFilterAnd . "
        ");
        $stAnual->execute([$curYear]);
        $anualRow = $stAnual->fetch();

        $distribucionAnual = [
            ['label' => 'Plan 3x por Sem.', 'valor' => (float)$anualRow['plan_3x'], 'color' => '#3b82f6'],
            ['label' => 'Plan Full / Libre', 'valor' => (float)$anualRow['plan_full'], 'color' => '#10b981'],
            ['label' => 'Pases por Clase', 'valor' => (float)$anualRow['plan_clase'], 'color' => '#f59e0b'],
            ['label' => 'Pago Profesores', 'valor' => (float)$anualRow['pago_profes'], 'color' => '#8b5cf6']
        ];

        jsonOut(true, [
            'semana' => [
                'serie' => $diasSemana,
                'total' => $totalSemana
            ],
            'mensual' => [
                'serie' => $serieMes,
                'total_ultimo' => $totalMesUltimo
            ],
            'anual' => [
                'year' => $curYear,
                'total' => (float)$anualRow['total_anual'],
                'distribucion' => $distribucionAnual
            ]
        ]);
    }

    jsonOut(false, [], 'Acción desconocida');
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GYM PRO SaaS - Arquitectura Multi-Tenant</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg-base: #090d16;
    --bg-card: #131b2e;
    --bg-card-hover: #18233c;
    --bg-inp: #1b2640;
    --border: #243452;
    --border-light: rgba(255, 255, 255, 0.08);
    --pri: #3b82f6;
    --sec: #8b5cf6;
    --ok: #10b981;
    --warn: #f59e0b;
    --err: #ef4444;
    --t1: #f8fafc;
    --t2: #cbd5e1;
    --t-mut: #64748b;
    --r: 12px;
    --r-lg: 16px;
    --shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    --tr: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  }
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
  body {
    background: radial-gradient(circle at 10% 0%, #1e1b4b 0%, var(--bg-base) 60%);
    color: var(--t1);
    min-height: 100vh;
    overflow-x: hidden;
  }
  .app { display: flex; min-height: 100vh; }
  
  /* Sidebar */
  .sidebar {
    width: 260px;
    background: rgba(10, 15, 29, 0.95);
    border-right: 1px solid var(--border);
    backdrop-filter: blur(12px);
    display: flex;
    flex-direction: column;
    position: fixed;
    height: 100vh;
    z-index: 100;
  }
  .logo {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 22px 18px;
    border-bottom: 1px solid var(--border);
  }
  .logo-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--pri), var(--sec));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 800;
    box-shadow: 0 8px 16px rgba(59, 130, 246, 0.35);
  }
  .logo-text { display: flex; flex-direction: column; }
  .logo-text span:first-child { font-size: 17px; font-weight: 800; letter-spacing: -0.5px; }
  .logo-text span:last-child { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--pri); }

  .user-badge {
    padding: 12px 16px;
    margin: 10px 12px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border);
    border-radius: var(--r);
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #1e293b;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: var(--pri);
  }
  .user-info { display: flex; flex-direction: column; overflow: hidden; }
  .user-name { font-size: 13px; font-weight: 700; color: var(--t1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .user-role-tag { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }

  .nav { display: flex; flex-direction: column; gap: 4px; padding: 10px 12px; flex: 1; overflow-y: auto; }
  .nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 14px;
    border-radius: var(--r);
    color: var(--t2);
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 600;
    transition: var(--tr);
  }
  .nav a:hover { background: rgba(255, 255, 255, 0.05); color: #fff; transform: translateX(3px); }
  .nav a.active {
    background: linear-gradient(135deg, var(--pri), var(--sec));
    color: #fff;
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3);
  }
  .nav-icon { font-size: 16px; }

  .sidebar-footer {
    padding: 14px;
    border-top: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  /* Main Area */
  .main {
    flex: 1;
    margin-left: 260px;
    padding: 24px 32px;
    min-width: 0;
  }
  .topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    background: rgba(19, 27, 46, 0.6);
    border: 1px solid var(--border);
    padding: 12px 20px;
    border-radius: var(--r);
    backdrop-filter: blur(8px);
    flex-wrap: wrap;
    gap: 12px;
  }
  .topbar-title { font-size: 14px; font-weight: 700; color: var(--t2); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .topbar-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  
  /* Audit Selector */
  .audit-select {
    background: #1e1b4b;
    border: 1px solid #3b82f6;
    color: #93c5fd;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    outline: none;
    cursor: pointer;
  }

  /* Cards & Layout */
  .card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: var(--shadow);
    transition: var(--tr);
  }
  .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    flex-wrap: wrap;
    gap: 10px;
  }
  .card-title {
    font-size: 17px;
    font-weight: 800;
    color: var(--t1);
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .title-page { font-size: 24px; font-weight: 800; margin-bottom: 20px; letter-spacing: -0.5px; }

  .grid { display: grid; gap: 18px; margin-bottom: 20px; }
  .g4 { grid-template-columns: repeat(4, 1fr); }
  .g3 { grid-template-columns: repeat(3, 1fr); }
  .g2 { grid-template-columns: repeat(2, 1fr); }

  /* Stat Card */
  .stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-shadow: var(--shadow);
  }
  .stat-label { font-size: 12px; font-weight: 700; color: var(--t-mut); text-transform: uppercase; letter-spacing: 0.5px; }
  .stat-value { font-size: 26px; font-weight: 800; color: var(--t1); letter-spacing: -0.5px; }
  .stat-sub { font-size: 12px; color: var(--t2); display: flex; align-items: center; gap: 6px; }

  /* Alert Card (Deuda) */
  .debt-banner {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.4));
    border: 2px solid var(--err);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }

  /* Membership Digital Card */
  .membership-card {
    background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
    border: 2px solid var(--pri);
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 20px 40px rgba(59, 130, 246, 0.25);
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
  }

  /* Buttons */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: var(--r);
    border: 1px solid transparent;
    cursor: pointer;
    font-weight: 700;
    font-size: 13px;
    transition: var(--tr);
    text-decoration: none;
  }
  .btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
  .btn-primary { background: linear-gradient(135deg, var(--pri), var(--sec)); color: #fff; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
  .btn-secondary { background: var(--bg-inp); color: var(--t1); border-color: var(--border); }
  .btn-success { background: var(--ok); color: #fff; }
  .btn-danger { background: var(--err); color: #fff; }
  .btn-warn { background: var(--warn); color: #000; }
  .btn-sm { padding: 6px 10px; font-size: 12px; border-radius: 8px; }
  .btn-xs { padding: 5px 9px; font-size: 11.5px; border-radius: 6px; gap: 4px; font-weight: 600; }

  /* Tables */
  .tbl-wrap {
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: var(--r);
    background: rgba(10, 15, 29, 0.4);
  }
  .tbl { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  .tbl th {
    background: #111827;
    text-align: left;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    color: var(--t2);
    font-weight: 700;
    white-space: nowrap;
    letter-spacing: 0.2px;
  }
  .tbl td {
    padding: 15px 16px;
    border-bottom: 1px solid var(--border-light);
    color: var(--t1);
    vertical-align: middle;
  }
  .tbl tbody tr {
    transition: background 0.15s ease;
  }
  .tbl tbody tr:hover { background: rgba(255, 255, 255, 0.035); }
  
  /* Inputs */
  .inp, select.inp, textarea.inp {
    width: 100%;
    padding: 10px 14px;
    background: var(--bg-inp);
    border: 1px solid var(--border);
    border-radius: var(--r);
    color: #fff;
    font-size: 13px;
    outline: none;
    transition: var(--tr);
  }
  .inp:focus { border-color: var(--pri); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25); background: #202d4b; }
  .inp.inp-error, select.inp.inp-error, textarea.inp.inp-error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.25) !important;
    background: rgba(239, 68, 68, 0.05) !important;
  }
  .field-error {
    color: #f87171;
    font-size: 11.5px;
    margin-top: 5px;
    display: none;
    font-weight: 600;
    line-height: 1.35;
  }
  .form-group { margin-bottom: 14px; }
  .form-label { display: block; font-size: 12px; font-weight: 700; color: var(--t2); margin-bottom: 6px; }
  .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }

  /* Badges */
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid transparent;
  }
  .b-ok { background: rgba(16, 185, 129, 0.15); color: #34d399; border-color: rgba(16, 185, 129, 0.35); }
  .b-warn { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border-color: rgba(245, 158, 11, 0.35); }
  .b-bad { background: rgba(239, 68, 68, 0.15); color: #f87171; border-color: rgba(239, 68, 68, 0.35); }
  .b-info { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.35); }
  .b-purple { background: rgba(139, 92, 246, 0.15); color: #c084fc; border-color: rgba(139, 92, 246, 0.35); }
  
  .pulse { animation: pulseAnim 1.5s infinite; }
  @keyframes pulseAnim {
    0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); }
    70% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
    100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
  }

  /* Modals */
  .modal-backdrop {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(6px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
  }
  .modal-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.8);
  }
  .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid var(--border); }
  .modal-body { padding: 24px; }
  .modal-footer { display: flex; align-items: center; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid var(--border); background: rgba(10, 15, 29, 0.4); }
  .btn-close { background: transparent; border: none; color: var(--t2); font-size: 20px; cursor: pointer; }

  /* Charts */
  .chart { width: 100%; height: 260px; border-radius: var(--r); }

  /* Toast */
  #toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #1e293b;
    border: 1px solid var(--border);
    color: #fff;
    padding: 14px 20px;
    border-radius: var(--r);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6);
    display: none;
    align-items: center;
    gap: 12px;
    z-index: 2000;
    font-size: 14px;
    font-weight: 600;
  }
  #toast.toast-ok { border-color: var(--ok); background: #064e3b; color: #a7f3d0; }
  #toast.toast-err { border-color: var(--err); background: #7f1d1d; color: #fecaca; }

  @media(max-width: 1024px) {
    .sidebar { width: 80px; }
    .sidebar .logo-text, .sidebar .user-info, .sidebar .nav span, .sidebar .sidebar-footer { display: none; }
    .main { margin-left: 80px; padding: 20px; }
    .g4 { grid-template-columns: repeat(2, 1fr); }
    .g3 { grid-template-columns: 1fr; }
    .g2 { grid-template-columns: 1fr; }
  }
  @media(max-width: 640px) {
    #dash-charts-container { grid-template-columns: 1fr !important; }
  }
</style>
</head>
<body>

<div class="app">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="logo">
      <div class="logo-icon">🏋️</div>
      <div class="logo-text">
        <span id="brand-gym-name">NITSOFT</span>
        <span>GIMNASIO</span>
      </div>
    </div>

    <div class="user-badge">
      <div class="user-avatar"><?= strtoupper(substr($userDisplayName, 0, 1)) ?></div>
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($userDisplayName) ?></span>
        <span class="user-role-tag" style="color:<?php
          if ($userRole === ROLE_ADMIN_GENERAL) echo '#c084fc';
          elseif ($userRole === ROLE_DUENO) echo '#60a5fa';
          elseif ($userRole === ROLE_COACH) echo '#34d399';
          else echo '#facc15';
        ?>">
          ● <span id="user-role-text"><?php
            if ($userRole === ROLE_ADMIN_GENERAL) echo 'ADMIN GENERAL';
            elseif ($userRole === ROLE_DUENO) echo htmlspecialchars($gimnasioNombre ?: 'Olympus Gym Pro');
            elseif ($userRole === ROLE_COACH) echo 'COACH / PROFE';
            else echo 'ALUMNO / SOCIO';
          ?></span>
        </span>
      </div>
    </div>

    <nav class="nav">
      <!-- NAVEGACIÓN DINÁMICA SEGÚN LOS 4 ROLES -->
      <a href="#" class="active" data-page="dashboard"><span class="nav-icon">📊</span><span>Dashboard</span></a>

      <!-- 1. MÓDULOS DEL ADMINISTRADOR GENERAL (SAAS) -->
      <?php if (hasRole(ROLE_ADMIN_GENERAL)): ?>
        <a href="#" data-page="saas-gimnasios"><span class="nav-icon">🏢</span><span>Gimnasios & Dueños</span></a>
        <a href="#" data-page="saas-pagos"><span class="nav-icon">💵</span><span>Cobros de Plataforma</span></a>
      <?php endif; ?>

      <!-- 2. MÓDULOS DE ADMINISTRADOR GENERAL Y DUEÑO -->
      <?php if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO])): ?>
        <a href="#" data-page="alumnos"><span class="nav-icon">👥</span><span>Alumnos (Socios)</span></a>
        <a href="#" data-page="profesores"><span class="nav-icon">🏋️‍♂️</span><span>Coaches & Profes</span></a>
        <a href="#" data-page="rutinas"><span class="nav-icon">📋</span><span>Rutinas de Entrenamiento</span></a>
        <a href="#" data-page="nutricion"><span class="nav-icon">🥗</span><span>Planes de Comida</span></a>
        <a href="#" data-page="pagos"><span class="nav-icon">💳</span><span>Pagos y Caja</span></a>
        <a href="#" data-page="reportes"><span class="nav-icon">📈</span><span>Estadísticas</span></a>
        <a href="#" data-page="config"><span class="nav-icon">⚙️</span><span>Configuración Sede</span></a>
        <a href="#" data-page="usuarios"><span class="nav-icon">🛡️</span><span>Usuarios & Roles</span></a>
      <?php endif; ?>

      <!-- 3. MÓDULOS DEL COACH -->
      <?php if (hasRole(ROLE_COACH)): ?>
        <a href="#" data-page="coach-alumnos"><span class="nav-icon">👥</span><span>Mis Alumnos a Cargo</span></a>
        <a href="#" data-page="rutinas"><span class="nav-icon">📋</span><span>Asignar Rutinas</span></a>
        <a href="#" data-page="nutricion"><span class="nav-icon">🥗</span><span>Asignar Plan Comida</span></a>
        <a href="#" data-page="coach-ingresos"><span class="nav-icon">💰</span><span>Mis Ganancias & Cobros</span></a>
      <?php endif; ?>

      <!-- 4. MÓDULOS DEL ALUMNO -->
      <?php if (hasRole(ROLE_ALUMNO)): ?>
        <a href="#" data-page="mi-membresia"><span class="nav-icon">🪪</span><span>Mi Carnet Digital</span></a>
        <a href="#" data-page="mi-rutina"><span class="nav-icon">💪</span><span>Mi Rutina</span></a>
        <a href="#" data-page="mi-nutricion"><span class="nav-icon">🥑</span><span>Mi Plan Nutricional</span></a>
        <a href="#" data-page="mis-pagos"><span class="nav-icon">📜</span><span>Mis Pagos</span></a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
      <?php if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH])): ?>
        <button class="btn btn-secondary btn-sm" onclick="openInviteModal()">🔗 Link de Registro</button>
      <?php endif; ?>
      <a class="btn btn-secondary btn-sm" href="change_password.php">🔑 Cambiar Clave</a>
      <a class="btn btn-danger btn-sm" href="logout.php">🚪 Cerrar Sesión</a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="topbar">
      <div class="topbar-title">
        <span>📍 Sistema de Gimnasio</span>
        <span style="color:var(--t-mut)">•</span>
        <span style="color:var(--pri);font-weight:700">
          <?php
            if ($userRole === ROLE_ADMIN_GENERAL) echo '👑 Administrador General';
            elseif ($userRole === ROLE_DUENO) echo '🏢 Dueño de Gimnasio';
            elseif ($userRole === ROLE_COACH) echo '🏋️ Coach / Entrenador';
            else echo '👤 Alumno / Socio';
          ?>
        </span>

        <?php if (hasRole(ROLE_ADMIN_GENERAL)): ?>
          <span style="color:var(--t-mut)">•</span>
          <label style="font-size:12px;color:var(--t2);font-weight:700">Auditar Sede:</label>
          <select id="superadmin-gym-switcher" class="audit-select" onchange="switchAuditGym(this.value)">
            <option value="0">🌐 Todas las Sedes (Global SaaS)</option>
          </select>
        <?php endif; ?>

        <span style="color:var(--t-mut)">•</span>
        <span id="current-date-txt" style="color:var(--t-mut)"></span>
      </div>
      <div class="topbar-actions">
        <?php if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH])): ?>
          <button class="btn btn-secondary btn-sm" onclick="openInviteModal()">🔗 Enlace Invitación</button>
          <button class="btn btn-primary btn-sm" onclick="openPagoModal()">+ Pago</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- ==================== VIEW: DASHBOARD (4 ROLES) ==================== -->
    <section id="page-dashboard">
      <div class="title-page">Dashboard de Control</div>

      <!-- 1. DASHBOARD DEL ADMIN GENERAL (SAAS) Y DUEÑO -->
      <?php if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO])): ?>
        
        <?php if (hasRole(ROLE_ADMIN_GENERAL)): ?>
        <!-- HUB DE ESCRITORIOS Y CREACIÓN DE DUEÑOS (SUPERADMIN) -->
        <div class="card" style="background:linear-gradient(135deg, #10172a, #0b1120);border:1px solid #334155;margin-bottom:24px">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;border-bottom:1px solid var(--border);padding-bottom:14px">
            <div>
              <div style="display:flex;align-items:center;gap:10px">
                <span class="badge b-purple">👑 PLATAFORMA SAAS</span>
                <span id="saas-active-desk-badge" class="badge b-info">🌐 Vista Global (Todas las Sedes)</span>
              </div>
              <h2 style="font-size:18px;font-weight:800;margin-top:6px">Escritorios de Gimnasios & Gestión de Dueños</h2>
              <p style="color:var(--t2);font-size:13px">Seleccioná un gimnasio para ver y operar exactamente como su dueño, o creá una nueva sede con su dueño único.</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
              <button class="btn btn-primary btn-sm" onclick="openGymModal()">➕ Crear Gimnasio & Dueño</button>
              <button class="btn btn-success btn-sm" onclick="openSaasPagoModal()">💵 Registrar Pago SaaS</button>
              <button class="btn btn-secondary btn-sm" onclick="setPage('saas-gimnasios')">⚙️ Configurar Sedes</button>
            </div>
          </div>

          <!-- GRID DE ESCRITORIOS DE GIMNASIOS -->
          <div id="superadmin-gyms-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(290px, 1fr));gap:16px;align-items:stretch">
            <!-- Renderizado dinámico vía JS -->
          </div>
        </div>
        <?php endif; ?>

        <div class="grid g4">
          <div class="stat-card">
            <div class="stat-label" id="lbl-kpi-1">Total Alumnos</div>
            <div class="stat-value" id="kpi-alumnos">-</div>
            <div class="stat-sub" id="sub-kpi-1"><span id="kpi-activos" class="badge b-ok">- al día</span> <span id="kpi-vencidos" class="badge b-bad">- con deuda</span></div>
          </div>
          <div class="stat-card">
            <div class="stat-label" id="lbl-kpi-2">Coaches & Profes</div>
            <div class="stat-value" style="color:#8b5cf6" id="kpi-profesores">-</div>
            <div class="stat-sub" id="sub-kpi-2">Equipo del gimnasio</div>
          </div>
          <div class="stat-card">
            <div class="stat-label" id="lbl-kpi-3">Recaudación de Hoy</div>
            <div class="stat-value" style="color:var(--ok)">$ <span id="rec-hoy">-</span></div>
            <div class="stat-sub" id="sub-kpi-3">Semana: $ <b id="rec-semana">-</b></div>
          </div>
          <div class="stat-card">
            <div class="stat-label" id="lbl-kpi-4">Ingresos del Mes</div>
            <div class="stat-value" style="color:#60a5fa">$ <span id="kpi-mes">-</span></div>
            <div class="stat-sub" id="sub-kpi-4">Mes corriente</div>
          </div>
        </div>

        <div class="grid g2">
          <!-- CARD DE ESTADO DE COBRANZAS Y EQUIPO (2 GRÁFICOS DIFERENCIADOS) -->
          <div class="card" style="display:flex;flex-direction:column;gap:14px">
            <div class="card-header" style="justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:10px">
              <div>
                <div class="card-title" id="dash-chart-title">🎯 Estado de Cobranzas & Equipo</div>
                <div style="font-size:12px;color:var(--t2);margin-top:2px" id="dash-chart-subtitle">Resumen visual de socios y coaches</div>
              </div>
            </div>

            <!-- CONTENEDOR DE 2 GRÁFICOS INDEPENDIENTES (ALUMNOS vs COACHES) -->
            <div id="dash-charts-container" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:stretch">
              
              <!-- 1. GRÁFICO ALUMNOS -->
              <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:12px;padding:14px;display:flex;flex-direction:column;justify-content:space-between;gap:12px">
                <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,0.06);padding-bottom:6px">
                  <span style="font-weight:800;font-size:13px;color:#fff">👥 Socios / Alumnos</span>
                  <span id="dash-alu-tot-badge" class="badge b-info">0 Alumnos</span>
                </div>
                
                <div style="display:flex;align-items:center;justify-content:center;gap:14px">
                  <canvas id="chart-alumnos" class="chart" style="width:120px;height:120px;max-width:120px;max-height:120px"></canvas>
                  <div id="dash-alu-summary" style="display:flex;flex-direction:column;gap:6px;font-size:12px">
                    <div style="display:flex;align-items:center;gap:6px">
                      <span style="width:9px;height:9px;border-radius:50%;background:#10b981;display:inline-block"></span>
                      <span>Pagaron: <b id="dash-alu-pagaron" style="color:#10b981;font-size:13px">0</b></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px">
                      <span style="width:9px;height:9px;border-radius:50%;background:#ef4444;display:inline-block"></span>
                      <span>Deben: <b id="dash-alu-deuda" style="color:#ef4444;font-size:13px">0</b></span>
                    </div>
                  </div>
                </div>

                <button class="btn btn-xs btn-success" style="width:100%;padding:7px;font-size:11.5px;font-weight:700" onclick="openPagoModal('alumno')">💵 Cobrar Cuota</button>
              </div>

              <!-- 2. GRÁFICO COACHES -->
              <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:12px;padding:14px;display:flex;flex-direction:column;justify-content:space-between;gap:12px">
                <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,0.06);padding-bottom:6px">
                  <span style="font-weight:800;font-size:13px;color:#fff">🏋️ Coaches / Profes</span>
                  <span id="dash-prof-tot-badge" class="badge b-purple">0 Coaches</span>
                </div>
                
                <div style="display:flex;align-items:center;justify-content:center;gap:14px">
                  <canvas id="chart-coaches" class="chart" style="width:120px;height:120px;max-width:120px;max-height:120px"></canvas>
                  <div id="dash-prof-summary" style="display:flex;flex-direction:column;gap:6px;font-size:12px">
                    <div style="display:flex;align-items:center;gap:6px">
                      <span style="width:9px;height:9px;border-radius:50%;background:#8b5cf6;display:inline-block"></span>
                      <span>Pagados: <b id="dash-prof-pagados" style="color:#8b5cf6;font-size:13px">0</b></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px">
                      <span style="width:9px;height:9px;border-radius:50%;background:#f97316;display:inline-block"></span>
                      <span>Por pagar: <b id="dash-prof-deuda" style="color:#f97316;font-size:13px">0</b></span>
                    </div>
                  </div>
                </div>

                <button class="btn btn-xs btn-primary" style="width:100%;padding:7px;font-size:11.5px;font-weight:700" onclick="openPagoModal('profesor')">💵 Liquidar Coach</button>
              </div>

            </div>

            <!-- CONTENEDOR PARA VISTA GLOBAL SAAS SUPERADMIN -->
            <div id="dash-saas-chart-box" style="display:none;align-items:center;gap:16px">
              <div style="display:flex;justify-content:center;align-items:center">
                <canvas id="chart-saas" class="chart" style="width:145px;height:145px;max-width:145px;max-height:145px"></canvas>
              </div>
              <div id="dash-saas-chart-legend" style="display:flex;flex-direction:column;gap:8px;flex:1"></div>
            </div>

          </div>

          <div class="card">
            <div class="card-header">
              <div class="card-title" id="dash-table-title">⚠️ Próximos Vencimientos de Cuotas (5 días)</div>
            </div>
            <div class="tbl-wrap" style="max-height:260px; overflow-y:auto;">
              <table class="tbl" id="tbl-prox">
                <thead id="tbl-prox-thead">
                  <tr><th>Alumno</th><th>Teléfono</th><th>Vence</th><th>Estado</th><th style="text-align:right">Acción</th></tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>

      <!-- 2. DASHBOARD DEL COACH -->
      <?php elseif (hasRole(ROLE_COACH)): ?>
        <div class="grid g3">
          <div class="stat-card">
            <div class="stat-label">Mis Alumnos a Cargo</div>
            <div class="stat-value" id="coach-kpi-alumnos">-</div>
            <div class="stat-sub"><span id="coach-kpi-activos" class="badge b-ok">- activos</span> <span id="coach-kpi-vencidos" class="badge b-bad">- vencidos</span></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Mis Ganancias del Mes</div>
            <div class="stat-value" style="color:var(--ok)">$ <span id="coach-kpi-ganancia">-</span></div>
            <div class="stat-sub">Comparativa vs mes previo: <span id="coach-kpi-variacion" class="badge b-info">-</span></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Alumnos al Día</div>
            <div class="stat-value" style="color:#10b981" id="coach-kpi-asist">-</div>
            <div class="stat-sub">Cuota abonada este mes</div>
          </div>
        </div>

        <div class="grid g2">
          <div class="card">
            <div class="card-header">
              <div class="card-title">⚡ Acciones Rápidas del Coach</div>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px">
              <button class="btn btn-primary" onclick="openAluModal()">+ Registrar Nuevo Alumno a mi Clase</button>
              <button class="btn btn-success" onclick="openRutinaModal()">📋 Cargar Nueva Rutina</button>
              <button class="btn btn-warn" onclick="openNutriModal()">🥑 Cargar Plan Nutricional</button>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><div class="card-title">⚠️ Vencimientos Próximos de Mis Alumnos</div></div>
            <div class="tbl-wrap" style="max-height:240px; overflow-y:auto;">
              <table class="tbl" id="tbl-prox">
                <thead><tr><th>Alumno</th><th>Teléfono</th><th>Vence</th><th>Estado</th><th style="text-align:right">Acción</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>

      <!-- 3. DASHBOARD DEL ALUMNO -->
      <?php elseif (hasRole(ROLE_ALUMNO)): ?>
        <div id="alumno-dashboard-container"></div>
      <?php endif; ?>
    </section>

    <!-- ==================== VIEW: SAAS GIMNASIOS & DUEÑOS (SUPERADMIN ONLY) ==================== -->
    <?php if (hasRole(ROLE_ADMIN_GENERAL)): ?>
    <section id="page-saas-gimnasios" style="display:none">
      <div class="card-header">
        <div class="title-page" style="margin-bottom:0">Gestión de Gimnasios & Dueños (SaaS)</div>
        <button class="btn btn-primary" onclick="openGymModal()">+ Habilitar / Cargar Nuevo Gimnasio</button>
      </div>

      <div class="card">
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-saas-gyms">
            <thead>
              <tr>
                <th>Gimnasio / Código</th>
                <th>Dueño Asignado</th>
                <th>Teléfono / WhatsApp</th>
                <th>Cuota SaaS</th>
                <th>Vencimiento</th>
                <th>Estado Suscripción</th>
                <th>Alumnos</th>
                <th style="text-align:right">Acciones de Control</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>

    <section id="page-saas-pagos" style="display:none">
      <div class="card-header">
        <div class="title-page" style="margin-bottom:0">Historial de Cobros de Plataforma a Dueños</div>
        <button class="btn btn-success" onclick="openSaasPagoModal()">+ Asentar Pago de Suscripción</button>
      </div>

      <div class="card">
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-saas-pagos">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Gimnasio</th>
                <th>Dueño</th>
                <th>Período</th>
                <th>Medio</th>
                <th style="text-align:right">Monto</th>
                <th>Comprobante / Obs</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- ==================== VIEW: ALUMNOS (ADMIN GENERAL & DUEÑO) ==================== -->
    <?php if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO])): ?>
    <section id="page-alumnos" style="display:none">
      <div class="card-header">
        <div class="title-page" style="margin-bottom:0">Gestión de Alumnos (Socios)</div>
        <button class="btn btn-primary" onclick="openAluModal()">+ Registrar Nuevo Alumno</button>
      </div>

      <div class="card">
        <div class="form-row">
          <div><label class="form-label">Buscar</label><input id="alu-q" class="inp" placeholder="Nombre, DNI, teléfono o actividad..." onkeyup="debounceLoadAlumnos()"></div>
          <div>
            <label class="form-label">Plan</label>
            <select id="alu-plan" class="inp" onchange="loadAlumnos()">
              <option value="">Todos los planes</option>
              <option value="3x">3 veces por semana</option>
              <option value="full">Full (Pase Libre)</option>
              <option value="clase">Por Clase</option>
            </select>
          </div>
          <div>
            <label class="form-label">Estado</label>
            <select id="alu-estado" class="inp" onchange="loadAlumnos()">
              <option value="">Todos los estados</option>
              <option value="activo">Activo</option>
              <option value="vencido">Vencido</option>
              <option value="pausado">Pausado</option>
            </select>
          </div>
          <div>
            <label class="form-label">Coach</label>
            <select id="alu-prof" class="inp" onchange="loadAlumnos()"><option value="">Todos los coaches</option></select>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-alu">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Teléfono / WhatsApp</th>
                <th>Plan</th>
                <th>Actividades</th>
                <th>Cuota</th>
                <th>Abonado</th>
                <th>Saldo</th>
                <th>Vencimiento</th>
                <th>Estado</th>
                <th>Coach</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ==================== VIEW: PROFESORES (ADMIN GENERAL & DUEÑO) ==================== -->
    <section id="page-profesores" style="display:none">
      <div class="card-header">
        <div>
          <div class="title-page" style="margin-bottom:0">Gestión de Coaches y Profesores</div>
          <p style="color:var(--t2);font-size:13px;margin-top:2px">Listado y administración exclusiva de entrenadores, cuotas mensuales y alumnos a su cargo.</p>
        </div>
        <button class="btn btn-primary" onclick="openProfModal()">+ Registrar Coach / Profe</button>
      </div>

      <!-- FILTROS Y BUSCADOR -->
      <div class="card">
        <div class="form-row">
          <div style="flex:2">
            <label class="form-label">🔍 Buscar Coach</label>
            <input id="prof-filter-q" class="inp" placeholder="Buscar por nombre o teléfono del coach..." onkeyup="debounceLoadProfesores()">
          </div>
          <div style="flex:1">
            <label class="form-label">💵 Estado de Cuota / Pago</label>
            <select id="prof-filter-estado" class="inp" onchange="loadProfesores()">
              <option value="">Todos los estados</option>
              <option value="al_dia">Al Día (Sin deuda)</option>
              <option value="deuda">Con Saldo Pendiente</option>
            </select>
          </div>
        </div>
      </div>

      <!-- TABLA PRINCIPAL DE COACHES -->
      <div class="card">
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-prof">
            <thead id="tbl-prof-thead">
              <tr>
                <th>Coach / Profesor</th>
                <th>Teléfono / WhatsApp</th>
                <th>Honorario Mensual Acordado</th>
                <th>Liquidado este Mes</th>
                <th>Estado de Liquidación</th>
                <th>Socios a Cargo</th>
                <th>Observaciones</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- ==================== VIEW: COACH MIS ALUMNOS ==================== -->
    <?php if (hasRole(ROLE_COACH)): ?>
    <section id="page-coach-alumnos" style="display:none">
      <div class="card-header">
        <div class="title-page" style="margin-bottom:0">Mis Alumnos Asignados</div>
        <button class="btn btn-primary" onclick="openAluModal()">+ Inscribir Alumno a mi Clase</button>
      </div>

      <div class="card">
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-coach-alumnos">
            <thead>
              <tr>
                <th>Alumno</th>
                <th>Teléfono / WhatsApp</th>
                <th>Plan</th>
                <th>Actividades</th>
                <th>Vencimiento</th>
                <th>Estado</th>
                <th style="text-align:right">Acciones de Entrenamiento</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>

    <section id="page-coach-ingresos" style="display:none">
      <div class="title-page">Mis Ganancias y Cobros Recibidos</div>
      <div class="grid g2">
        <div class="stat-card">
          <div class="stat-label">Total Recaudado de Alumnos en el Mes</div>
          <div class="stat-value" style="color:var(--ok)" id="coach-rec-mes">$ 0.00</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Mi Pago / Cuota Mensual Acordada</div>
          <div class="stat-value" style="color:#60a5fa" id="coach-cuota-mensual">$ 0.00</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Historial de Pagos de Mis Alumnos</div></div>
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-coach-pagos">
            <thead><tr><th>Fecha</th><th>Alumno</th><th>Plan</th><th>Medio</th><th style="text-align:right">Monto</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- ==================== VIEW: RUTINAS ==================== -->
    <?php if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH])): ?>
    <section id="page-rutinas" style="display:none">
      <div class="card-header">
        <div class="title-page" style="margin-bottom:0">Rutinas de Entrenamiento Personalizadas</div>
        <button class="btn btn-primary" onclick="openRutinaModal()">+ Asignar / Crear Rutina</button>
      </div>

      <div class="card">
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-rutinas">
            <thead>
              <tr>
                <th>Alumno</th>
                <th>Título de Rutina</th>
                <th>Objetivo</th>
                <th>Días</th>
                <th>Fecha Asignación</th>
                <th>Estado</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ==================== VIEW: NUTRICIÓN ==================== -->
    <section id="page-nutricion" style="display:none">
      <div class="card-header">
        <div class="title-page" style="margin-bottom:0">Planes de Alimentación & Nutrición</div>
        <button class="btn btn-primary" onclick="openNutriModal()">+ Asignar Plan Nutricional</button>
      </div>

      <div class="card">
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-nutricion">
            <thead>
              <tr>
                <th>Alumno</th>
                <th>Título del Plan</th>
                <th>Calorías Aprox.</th>
                <th>Coach</th>
                <th>Fecha Asignación</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ==================== VIEW: PAGOS Y CAJA ==================== -->
    <section id="page-pagos" style="display:none">
      <div class="card-header" style="flex-wrap:wrap;gap:12px">
        <div>
          <div class="title-page" style="margin-bottom:0">Control de Pagos y Caja</div>
          <p style="color:var(--t2);font-size:13px;margin-top:4px">Historial y registro de cobros de cuotas de alumnos y cánones de coaches.</p>
        </div>
        <button class="btn btn-success" onclick="openPagoModal()">+ Registrar Pago</button>
      </div>

      <!-- PANEL DE BÚSQUEDA Y FILTROS -->
      <div class="card" style="margin-bottom:16px;padding:16px">
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;align-items:flex-end">
          
          <div class="form-group" style="margin:0">
            <label class="form-label">🔍 Buscar por Nombre / Titular</label>
            <input id="pagos-filter-q" class="inp" placeholder="Buscar alumno, coach o detalle..." oninput="debounceLoadPagos()">
          </div>

          <div class="form-group" style="margin:0">
            <label class="form-label">👥 Filtrar por Tipo</label>
            <select id="pagos-filter-tipo" class="inp" onchange="loadPagos()">
              <option value="">👥 Ver Todos (Alumnos y Coaches)</option>
              <option value="alumno">👤 Solo Alumnos</option>
              <option value="profesor">🏋️ Solo Coaches / Profesores</option>
            </select>
          </div>

          <div class="form-group" style="margin:0">
            <label class="form-label">💳 Medio de Pago</label>
            <select id="pagos-filter-medio" class="inp" onchange="loadPagos()">
              <option value="">(Todos los Medios)</option>
              <option value="efectivo">💵 Efectivo</option>
              <option value="transferencia">🏦 Transferencia / MercadoPago</option>
              <option value="debito">💳 Débito</option>
              <option value="credito">💳 Crédito</option>
            </select>
          </div>

          <div class="form-group" style="margin:0">
            <label class="form-label">📅 Mes de Registro</label>
            <input type="month" id="pagos-filter-mes" class="inp" onchange="loadPagos()">
          </div>

          <div>
            <button class="btn btn-secondary" style="width:100%" onclick="resetPagosFiltros()">🔄 Limpiar Filtros</button>
          </div>

        </div>

        <!-- RESUMEN EN VIVO DE LO FILTRADO EN CAJA -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06);flex-wrap:wrap;gap:10px">
          <div style="font-size:13px;color:var(--t2)">
            Mostrando <b id="pagos-count-txt" style="color:#fff">0</b> registros
          </div>
          <div style="font-size:15px;font-weight:800;color:var(--ok)">
            Total Recaudado (Filtro Actual): $ <span id="pagos-total-txt">0.00</span>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-pagos">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Titular</th>
                <th>Plan / Concepto</th>
                <th>Medio de Pago</th>
                <th style="text-align:right">Monto</th>
                <th>Observaciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ==================== VIEW: REPORTES (ADMIN GENERAL & DUEÑO) ==================== -->
    <section id="page-reportes" style="display:none">
      <div class="title-page">Estadísticas y Evolución de Ingresos</div>

      <!-- KPI SUMMARY BANNER -->
      <div class="grid g3">
        <div class="stat-card">
          <div class="stat-label">📅 Recaudación Esta Semana</div>
          <div class="stat-value" style="color:var(--ok)">$ <span id="rep-total-semana">0.00</span></div>
          <div class="stat-sub">Lunes a Domingo</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">📈 Ingresos del Último Mes</div>
          <div class="stat-value" style="color:#38bdf8">$ <span id="rep-total-mes">0.00</span></div>
          <div class="stat-sub">Mes corriente</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">🥧 Total Anual Acumulado</div>
          <div class="stat-value" style="color:#c084fc">$ <span id="rep-total-anual">0.00</span></div>
          <div class="stat-sub">Año <span id="rep-year-lbl">2026</span></div>
        </div>
      </div>

      <!-- 1. RECAUDACIÓN SEMANAL (BARRAS) & 2. PROGRESO MENSUAL (LÍNEAS) -->
      <div class="grid g2">
        <div class="card">
          <div class="card-header">
            <div class="card-title">📊 1. Recaudación Semanal (Gráfica de Barras)</div>
            <span class="badge b-info">Día por día</span>
          </div>
          <canvas id="chart-semanal-barras" class="chart" style="height:280px"></canvas>
        </div>

        <div class="card">
          <div class="card-header">
            <div class="card-title">📈 2. Progreso y Evolución Mensual (Gráfica de Líneas)</div>
            <span class="badge b-ok">Últimos 6 meses</span>
          </div>
          <canvas id="chart-mensual-lineas" class="chart" style="height:280px"></canvas>
        </div>
      </div>

      <!-- 3. DISTRIBUCIÓN ANUAL (TORTA) -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">🥧 3. Distribución Anual por Concepto (Gráfica de Torta)</div>
          <span class="badge b-purple">Participación %</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1.2fr;gap:24px;align-items:center" class="g-pie-wrap">
          <div>
            <canvas id="chart-anual-torta" class="chart" style="height:280px;max-width:320px;margin:0 auto;display:block"></canvas>
          </div>
          <div id="legend-anual-torta" style="display:flex;flex-direction:column;gap:10px"></div>
        </div>
      </div>
    </section>

    <!-- ==================== VIEW: CONFIGURACIÓN ==================== -->
    <section id="page-config" style="display:none">
      <div class="title-page">Configuración de Sede y Tarifas</div>
      <div class="grid g2">
        <div class="card">
          <div class="card-title" style="margin-bottom:16px">🏷️ Precios de Planes y Cuotas</div>
          <form onsubmit="return saveConfig(event)">
            <div class="form-group"><label class="form-label">Plan 3 Veces por Semana ($)</label><input id="cfg-3x" type="number" step="0.01" class="inp" required></div>
            <div class="form-group"><label class="form-label">Plan Full / Pase Libre ($)</label><input id="cfg-full" type="number" step="0.01" class="inp" required></div>
            <div class="form-group"><label class="form-label">Pase por Clase Individual ($)</label><input id="cfg-clase" type="number" step="0.01" class="inp" required></div>
            <button class="btn btn-primary" style="width:100%">Guardar Precios</button>
          </form>
        </div>

        <div class="card">
          <div class="card-title" style="margin-bottom:16px">🏢 Datos del Gimnasio</div>
          <form onsubmit="return saveGymData(event)">
            <div class="form-group"><label class="form-label">Nombre del Gimnasio</label><input id="cfg-gym-nombre" class="inp" required></div>
            <div class="form-group"><label class="form-label">Código de Invitación / Registro</label><input id="cfg-gym-code" class="inp" placeholder="NITSOFT-PRO"></div>
            <div class="form-group"><label class="form-label">Teléfono / WhatsApp</label><input id="cfg-gym-tel" class="inp"></div>
            <div class="form-group"><label class="form-label">Dirección</label><input id="cfg-gym-dir" class="inp"></div>
            <button class="btn btn-primary" style="width:100%">Guardar Datos de Sede</button>
          </form>
        </div>
      </div>
    </section>

    <!-- ==================== VIEW: USUARIOS & ROLES (ADMIN GENERAL & DUEÑO) ==================== -->
    <section id="page-usuarios" style="display:none">
      <div class="card-header">
        <div class="title-page" style="margin-bottom:0">Gestión de Usuarios & Roles (4 Roles)</div>
        <button class="btn btn-primary" onclick="openUserModal()">+ Crear Nuevo Usuario</button>
      </div>

      <div class="card">
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-usuarios">
            <thead>
              <tr>
                <th>Usuario</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Perfil Vinculado</th>
                <th>Gimnasio</th>
                <th>Estado</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- ==================== VISTAS EXCLUSIVAS DEL ALUMNO ==================== -->
    <?php if (hasRole(ROLE_ALUMNO)): ?>
    <section id="page-mi-membresia" style="display:none">
      <div class="title-page">Mi Membresía Digital</div>
      <div id="alumno-portal-carnet"></div>
    </section>

    <section id="page-mi-rutina" style="display:none">
      <div class="title-page">Mi Rutina de Entrenamiento</div>
      <div id="alumno-portal-rutina"></div>
    </section>

    <section id="page-mi-nutricion" style="display:none">
      <div class="title-page">Mi Plan Nutricional & Comidas</div>
      <div id="alumno-portal-nutri"></div>
    </section>

    <section id="page-mis-pagos" style="display:none">
      <div class="title-page">Historial de Mis Pagos</div>
      <div class="card">
        <div class="tbl-wrap">
          <table class="tbl" id="tbl-mis-pagos">
            <thead><tr><th>Fecha</th><th>Plan</th><th>Medio</th><th style="text-align:right">Monto</th><th>Observaciones</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>
    <?php endif; ?>

  </main>
</div>

<!-- ==================== MODALES DEL SISTEMA ==================== -->

<!-- MODAL: LINK DE INVITACIÓN DIRECTA (MULTI-TENANT) -->
<div id="modal-invite" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <h3 style="font-size:18px;font-weight:800">🔗 Enlaces de Registro Directo</h3>
      <button class="btn-close" onclick="closeModal('modal-invite')">&times;</button>
    </div>
    <div class="modal-body">
      <p style="color:var(--t2);font-size:13px;margin-bottom:16px">
        Compartí estos enlaces con tus socios o profesores para que se registren directamente en tu gimnasio sin necesidad de seleccionar la sede manualmente:
      </p>

      <div class="form-group">
        <label class="form-label">👤 Enlace para Registro de Alumnos / Socios</label>
        <div style="display:flex;gap:8px">
          <input id="inv-link-alumno" class="inp" readonly>
          <button class="btn btn-primary" onclick="copyLink('inv-link-alumno')">📋 Copiar</button>
        </div>
      </div>

      <div class="form-group" style="margin-top:14px">
        <label class="form-label">🏋️ Enlace para Registro de Coaches / Profes</label>
        <div style="display:flex;gap:8px">
          <input id="inv-link-coach" class="inp" readonly>
          <button class="btn btn-primary" onclick="copyLink('inv-link-coach')">📋 Copiar</button>
        </div>
      </div>

      <div style="margin-top:16px;text-align:center">
        <button class="btn btn-success" style="width:100%" onclick="shareWhatsAppInvite()">💬 Compartir por WhatsApp</button>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-invite')">Cerrar</button>
    </div>
  </div>
</div>

<!-- MODAL: RUTINA -->
<div id="modal-rutina" class="modal-backdrop">
  <div class="modal-box" style="max-width:680px">
    <div class="modal-header">
      <h3 id="rutina-modal-title" style="font-size:18px;font-weight:800">Cargar Rutina de Entrenamiento</h3>
      <button class="btn-close" onclick="closeModal('modal-rutina')">&times;</button>
    </div>
    <form onsubmit="return saveRutina(event)">
      <div class="modal-body">
        <input type="hidden" id="rutina-id">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Alumno *</label>
            <select id="rutina-alumno" class="inp" required><option value="">(Seleccionar Alumno)</option></select>
          </div>
          <div class="form-group">
            <label class="form-label">Título de la Rutina *</label>
            <input id="rutina-titulo" class="inp" required placeholder="Ej: Hipertrofia 4 Días Push/Pull">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Objetivo</label>
            <input id="rutina-obj" class="inp" placeholder="Ej: Ganancia muscular, Pérdida de grasa">
          </div>
          <div class="form-group">
            <label class="form-label">Días de la Semana</label>
            <input id="rutina-dias" class="inp" placeholder="Ej: Lunes, Martes, Jueves, Viernes">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Ejercicios, Series y Repeticiones *</label>
          <textarea id="rutina-det" class="inp" rows="6" required placeholder="DÍA 1 (Piernas):&#10;- Sentadillas: 4x10&#10;- Prensa: 4x12&#10;..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-rutina')">Cancelar</button>
        <button class="btn btn-primary">Guardar Rutina</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: PLAN NUTRICIONAL -->
<div id="modal-nutri" class="modal-backdrop">
  <div class="modal-box" style="max-width:680px">
    <div class="modal-header">
      <h3 id="nutri-modal-title" style="font-size:18px;font-weight:800">Cargar Plan Nutricional / Comida</h3>
      <button class="btn-close" onclick="closeModal('modal-nutri')">&times;</button>
    </div>
    <form onsubmit="return saveNutri(event)">
      <div class="modal-body">
        <input type="hidden" id="nutri-id">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Alumno *</label>
            <select id="nutri-alumno" class="inp" required><option value="">(Seleccionar Alumno)</option></select>
          </div>
          <div class="form-group">
            <label class="form-label">Título del Plan *</label>
            <input id="nutri-titulo" class="inp" required placeholder="Ej: Plan de Definición y Rendimiento">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Calorías Aprox. Diarias</label>
          <input id="nutri-cal" type="number" class="inp" value="2200">
        </div>
        <div class="form-group">
          <label class="form-label">Detalle de Comidas (Desayuno, Almuerzo, Merienda, Cena) *</label>
          <textarea id="nutri-det" class="inp" rows="6" required placeholder="🍳 Desayuno: ...&#10;🥗 Almuerzo: ...&#10;🍎 Merienda: ...&#10;🍗 Cena: ..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-nutri')">Cancelar</button>
        <button class="btn btn-primary">Guardar Plan Nutricional</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: ALUMNO -->
<div id="modal-alu" class="modal-backdrop">
  <div class="modal-box" style="max-width:780px">
    <div class="modal-header" style="padding:22px 28px">
      <h3 id="alu-modal-title" style="font-size:20px;font-weight:800">Registrar Alumno</h3>
      <button class="btn-close" onclick="closeModal('modal-alu')">&times;</button>
    </div>
    <form onsubmit="return saveAlumno(event)" novalidate>
      <div class="modal-body" style="padding:28px;display:flex;flex-direction:column;gap:18px">
        <input type="hidden" id="alu-id">
        <div class="form-row" style="gap:18px">
          <div class="form-group">
            <label class="form-label" style="font-size:13.5px;font-weight:700">Nombre Completo *</label>
            <input id="alu-nombre" class="inp" placeholder="Ej: Florencia Carreño" style="padding:12px 14px;font-size:14px">
            <div id="err-alu-nombre" class="field-error"></div>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size:13.5px;font-weight:700">DNI / Documento *</label>
            <input id="alu-dni" class="inp" placeholder="Ej: 38456789" style="padding:12px 14px;font-size:14px">
            <div id="err-alu-dni" class="field-error"></div>
          </div>
        </div>
        <div class="form-row" style="gap:18px">
          <div class="form-group">
            <label class="form-label" style="font-size:13.5px;font-weight:700">Teléfono / WhatsApp</label>
            <input id="alu-telefono" class="inp" placeholder="Ej: 2657506957 o +54 9 2657..." style="padding:12px 14px;font-size:14px">
            <div id="err-alu-telefono" class="field-error"></div>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size:13.5px;font-weight:700">Plan *</label>
            <select id="alu-plan-inp" class="inp" style="padding:12px 14px;font-size:14px">
              <option value="3x">3 veces por semana</option>
              <option value="full">Full (Pase Libre)</option>
              <option value="clase">Por Clase</option>
            </select>
            <div id="err-alu-plan" class="field-error"></div>
          </div>
        </div>
        <div class="form-row" style="gap:18px">
          <div class="form-group">
            <label class="form-label" style="font-size:13.5px;font-weight:700">Actividades</label>
            <input id="alu-actividades" class="inp" placeholder="Musculación, Funcional, Boxeo..." style="padding:12px 14px;font-size:14px">
            <div id="err-alu-actividades" class="field-error"></div>
          </div>
          <?php if (!hasRole(ROLE_COACH)): ?>
          <div class="form-group">
            <label class="form-label" style="font-size:13.5px;font-weight:700">Coach Asignado</label>
            <select id="alu-prof-inp" class="inp" style="padding:12px 14px;font-size:14px"><option value="">(Ninguno / General)</option></select>
            <div id="err-alu-prof" class="field-error"></div>
          </div>
          <?php endif; ?>
        </div>
        <div class="form-row" style="gap:18px">
          <div class="form-group">
            <label class="form-label" style="font-size:13.5px;font-weight:700">Fecha de Inicio *</label>
            <input id="alu-inicio" type="date" class="inp" value="<?= hoy() ?>" style="padding:12px 14px;font-size:14px">
            <div id="err-alu-inicio" class="field-error"></div>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size:13.5px;font-weight:700">Fecha Vencimiento *</label>
            <input id="alu-venc" type="date" class="inp" style="padding:12px 14px;font-size:14px">
            <div id="err-alu-venc" class="field-error"></div>
          </div>
        </div>
        <div class="form-row" style="gap:18px">
          <div class="form-group">
            <label class="form-label" style="font-size:13.5px;font-weight:700">Estado</label>
            <select id="alu-estado-inp" class="inp" style="padding:12px 14px;font-size:14px">
              <option value="activo">Activo</option>
              <option value="vencido">Vencido</option>
              <option value="pausado">Pausado</option>
            </select>
            <div id="err-alu-estado" class="field-error"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="padding:18px 28px">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-alu')" style="padding:11px 18px;font-size:13.5px">Cancelar</button>
        <button class="btn btn-primary" style="padding:11px 22px;font-size:13.5px;font-weight:700">Guardar Alumno</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: PROFESOR -->
<div id="modal-prof" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="prof-modal-title" style="font-size:18px;font-weight:800">Registrar Coach / Profesor</h3>
      <button class="btn-close" onclick="closeModal('modal-prof')">&times;</button>
    </div>
    <form onsubmit="return saveProfesor(event)">
      <div class="modal-body">
        <input type="hidden" id="prof-id">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Nombre del Coach *</label><input id="prof-nombre" class="inp" required placeholder="Gastón Sosa"></div>
          <div class="form-group"><label class="form-label">Teléfono / WhatsApp</label><input id="prof-telefono" class="inp" placeholder="+54 266 ..."></div>
        </div>
        <div class="form-group"><label class="form-label">Honorario / Sueldo Mensual Acordado ($) *</label><input id="prof-cuota" type="number" step="0.01" class="inp" value="0.00" required></div>
        <div class="form-group"><label class="form-label">Observaciones</label><textarea id="prof-obs" class="inp" rows="2" placeholder="Especialidades, horarios, notas internas"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-prof')">Cancelar</button>
        <button class="btn btn-primary">Guardar Coach</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: PAGO -->
<div id="modal-pago" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modal-pago-title" style="font-size:18px;font-weight:800">Registrar Cobro / Pago</h3>
      <button class="btn-close" onclick="closeModal('modal-pago')">&times;</button>
    </div>
    <form onsubmit="return savePago(event)">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tipo de Transacción</label>
            <select id="pago-tipo" class="inp" onchange="onPagoTipoChange()">
              <option value="alumno">👤 Cobro a Alumno (Ingreso de Cuota)</option>
              <?php if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO])): ?><option value="profesor">🏋️ Liquidación a Coach (Pago de Honorarios)</option><?php endif; ?>
            </select>
          </div>
          <div class="form-group" id="group-pago-alumno">
            <label class="form-label">Alumno / Socio *</label>
            <select id="pago-alumno" class="inp" onchange="onPagoAlumnoSelect()"><option value="">(Seleccionar Alumno)</option></select>
          </div>
          <div class="form-group" id="group-pago-profesor" style="display:none">
            <label class="form-label">Coach / Profesor a Liquidar *</label>
            <select id="pago-profesor" class="inp" onchange="onPagoProfesorSelect()"><option value="">(Seleccionar Coach)</option></select>
          </div>
        </div>

        <!-- RESUMEN DE CUOTA PACTADA Y SALDO -->
        <div id="pago-summary-box" style="display:none;background:rgba(59, 130, 246, 0.08);border:1px solid rgba(59, 130, 246, 0.25);border-radius:10px;padding:12px;margin-bottom:14px">
          <div style="display:flex;justify-content:space-between;align-items:center;font-size:12.5px">
            <span id="pago-summary-plan" style="font-weight:700;color:#fff">Plan / Titular: -</span>
            <span id="pago-summary-badge" class="badge b-info">-</span>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1.2fr;gap:8px;margin-top:8px;font-size:12px;border-top:1px dashed rgba(255,255,255,0.1);padding-top:8px">
            <div><span id="lbl-pago-summary-cuota" style="color:var(--t2)">Cuota Pactada:</span><br><b id="pago-summary-cuota" style="color:#fff">$ 0</b></div>
            <div><span id="lbl-pago-summary-abonado" style="color:var(--t2)">Abonado este Mes:</span><br><b id="pago-summary-abonado" style="color:var(--ok)">$ 0</b></div>
            <div><span id="lbl-pago-summary-saldo" style="color:var(--t2)">Saldo Exacto a Cobrar:</span><br><b id="pago-summary-saldo" style="color:#38bdf8;font-size:13px">$ 0</b></div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
              <span id="lbl-pago-monto">Monto Exacto ($) *</span>
              <span id="pago-lock-badge" class="badge b-info" style="font-size:10px;padding:2px 6px">🔒 PACTADO</span>
            </label>
            <input id="pago-monto" type="number" step="0.01" class="inp" required placeholder="0.00" readonly style="font-weight:800;color:#fff;background:#151f33">
            <div id="pago-monto-hint" style="font-size:11px;color:var(--t2);margin-top:4px">
              Solo se permite registrar el importe pactado exacto (ni más ni menos).
            </div>
          </div>
          <div class="form-group"><label class="form-label">Fecha</label><input id="pago-fecha" type="date" class="inp" value="<?= hoy() ?>" required></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Medio de Pago</label>
            <select id="pago-medio" class="inp"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="tarjeta">Tarjeta</option><option value="otro">Otro</option></select>
          </div>
          <div class="form-group"><label class="form-label">Comprobante / Obs</label><input id="pago-obs" class="inp" placeholder="Opcional"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-pago')">Cancelar</button>
        <button id="btn-pago-submit" class="btn btn-success">Confirmar Operación</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: GIMNASIO & DUEÑO (SUPERADMIN) -->
<?php if (hasRole(ROLE_ADMIN_GENERAL)): ?>
<div id="modal-gym" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="gym-modal-title" style="font-size:18px;font-weight:800">Habilitar / Crear Gimnasio & Dueño</h3>
      <button class="btn-close" onclick="closeModal('modal-gym')">&times;</button>
    </div>
    <form onsubmit="return saveGymSaaS(event)">
      <div class="modal-body">
        <input type="hidden" id="saas-gym-id">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Nombre del Gimnasio *</label><input id="saas-gym-nombre" class="inp" required placeholder="Ej: Titan Fitness"></div>
          <div class="form-group"><label class="form-label">Código Único de Sede (Invite Code)</label><input id="saas-gym-code" class="inp" placeholder="TITAN-FIT"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Teléfono / WhatsApp</label><input id="saas-gym-tel" class="inp" placeholder="+54 266 ..."></div>
          <div class="form-group"><label class="form-label">Email de Contacto</label><input id="saas-gym-email" type="email" class="inp" placeholder="contacto@gym.com"></div>
        </div>
        <div class="form-group"><label class="form-label">Dirección / Sede</label><input id="saas-gym-dir" class="inp" placeholder="Av. Principal 123"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Pago Mensual SaaS ($)</label><input id="saas-gym-monto" type="number" step="0.01" class="inp" value="45000.00" required></div>
          <div class="form-group"><label class="form-label">Fecha Vencimiento Suscripción</label><input id="saas-gym-venc" type="date" class="inp" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required></div>
        </div>
        <div id="saas-dueno-creds" style="margin-top:10px;padding-top:10px;border-top:1px dashed var(--border)">
          <div style="font-size:13px;font-weight:700;color:var(--pri);margin-bottom:8px">👤 Credenciales Únicas del Dueño:</div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Usuario Dueño *</label><input id="saas-dueno-user" class="inp" placeholder="dueno_marcos"></div>
            <div class="form-group"><label class="form-label">Contraseña Inicial *</label><input id="saas-dueno-pass" type="password" class="inp" placeholder="••••••••"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-gym')">Cancelar</button>
        <button class="btn btn-primary">Guardar y Habilitar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: ASENTAR PAGO SAAS -->
<div id="modal-saas-pago" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <h3 style="font-size:18px;font-weight:800">💵 Asentar Pago de Suscripción SaaS</h3>
      <button class="btn-close" onclick="closeModal('modal-saas-pago')">&times;</button>
    </div>
    <form onsubmit="return saveSaasPago(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Gimnasio *</label>
          <select id="saas-pago-gym" class="inp" required><option value="">(Seleccionar Gimnasio)</option></select>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Monto ($) *</label><input id="saas-pago-monto" type="number" step="0.01" class="inp" required value="45000.00"></div>
          <div class="form-group"><label class="form-label">Fecha de Pago</label><input id="saas-pago-fecha" type="date" class="inp" value="<?= hoy() ?>" required></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Medio de Pago</label>
            <select id="saas-pago-medio" class="inp"><option value="transferencia">Transferencia</option><option value="mercadopago">MercadoPago</option><option value="efectivo">Efectivo</option></select>
          </div>
          <div class="form-group"><label class="form-label">Nro. Comprobante</label><input id="saas-pago-comp" class="inp" placeholder="TRF-123456"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-saas-pago')">Cancelar</button>
        <button class="btn btn-success">✅ Asentar Pago y Renovar Servicio</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- MODAL: USUARIO & ROL -->
<div id="modal-usuario" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="user-modal-title" style="font-size:18px;font-weight:800">Gestionar Usuario & Rol</h3>
      <button class="btn-close" onclick="closeModal('modal-usuario')">&times;</button>
    </div>
    <form onsubmit="return saveUsuario(event)">
      <div class="modal-body">
        <input type="hidden" id="user-id">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Nombre de Usuario *</label><input id="user-nombre" class="inp" required></div>
          <div class="form-group"><label class="form-label">Email *</label><input id="user-email" type="email" class="inp" required></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Rol (RBAC) *</label>
            <select id="user-rol" class="inp">
              <?php if (hasRole(ROLE_ADMIN_GENERAL)): ?><option value="admin_general">👑 Admin General (SaaS Total)</option><?php endif; ?>
              <option value="dueno">🏢 Dueño de Gimnasio</option>
              <option value="coach">🏋️ Coach / Entrenador</option>
              <option value="alumno">👤 Alumno / Socio</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Estado</label><select id="user-activo" class="inp"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
        </div>
        <div class="form-group"><label class="form-label">Contraseña (BCrypt)</label><input id="user-password" type="password" class="inp" placeholder="Dejar en blanco para no modificar"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-usuario')">Cancelar</button>
        <button class="btn btn-primary">Guardar Usuario</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: CONFIRMACIÓN DEL SISTEMA -->
<div id="modal-confirm" class="modal-backdrop" style="z-index:9999">
  <div class="modal-box" style="max-width:440px;border:1px solid #334155;box-shadow:0 25px 50px -12px rgba(0, 0, 0, 0.8);border-radius:16px;background:#0f172a;overflow:hidden">
    <div style="padding:28px 24px 20px 24px;text-align:center">
      <div id="confirm-modal-icon" style="width:54px;height:54px;border-radius:50%;background:rgba(239, 68, 68, 0.15);color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 16px auto;border:1px solid rgba(239, 68, 68, 0.3)">
        🗑️
      </div>
      <h3 id="confirm-modal-title" style="font-size:18px;font-weight:800;color:#fff;margin-bottom:8px">¿Eliminar Alumno?</h3>
      <div id="confirm-modal-msg" style="font-size:13.5px;color:var(--t2);line-height:1.5;margin:0">Esta acción no se puede deshacer.</div>
    </div>
    <div class="modal-footer" style="padding:16px 24px;display:flex;gap:12px;justify-content:center;background:rgba(15, 23, 42, 0.6);border-top:1px solid #1e293b">
      <button type="button" class="btn btn-secondary" id="confirm-modal-cancel" style="flex:1;padding:10px 16px;font-size:13.5px;border-radius:10px">Cancelar</button>
      <button type="button" class="btn btn-danger" id="confirm-modal-btn" style="flex:1;padding:10px 16px;font-size:13.5px;font-weight:700;border-radius:10px">Sí, Eliminar</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast"></div>

<!-- ==================== FRONTEND CONTROLLER (JS) ==================== -->
<script>
const CURRENT_USER = {
  id: <?= $userId ?>,
  name: <?= json_encode($userName) ?>,
  email: <?= json_encode($userEmail) ?>,
  role: <?= json_encode($userRole) ?>,
  is_superadmin: <?= json_encode($isSuperAdmin) ?>,
  gimnasio_id: <?= json_encode($gimnasioId) ?>,
  audit_gym_id: <?= json_encode($auditGymId) ?>,
  profesor_id: <?= json_encode($profesorId) ?>,
  alumno_id: <?= json_encode($alumnoId) ?>
};

const $ = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
const fmtMoney = n => (Number(n || 0)).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtDate = dStr => {
  if (!dStr) return '-';
  const raw = String(dStr).trim().split(' ')[0];
  const parts = raw.split('-');
  if (parts.length === 3 && parts[0].length === 4) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }
  return dStr;
};

let _toastTimer = null;
function showToast(msg, isError = false) {
  const t = $('#toast');
  if (!t) return;
  if (_toastTimer) clearTimeout(_toastTimer);
  t.textContent = (isError ? '⚠️ ' : '✅ ') + msg;
  t.className = isError ? 'toast-err' : 'toast-ok';
  t.style.display = 'flex';
  _toastTimer = setTimeout(() => { t.style.display = 'none'; }, 3500);
}

async function api(action, data = {}, method = 'POST') {
  try {
    let r;
    if (method === 'GET') {
      r = await fetch(`?ajax=${action}&` + new URLSearchParams(data));
    } else {
      r = await fetch(`?ajax=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: new URLSearchParams(data)
      });
    }
    if (r.status === 401) { window.location.href = 'login.php'; return { ok: false }; }
    return await r.json();
  } catch (err) {
    showToast('Error de comunicación con el servidor', true);
    return { ok: false, msg: err.message };
  }
}

function openModal(id) { $('#' + id).style.display = 'flex'; }
function closeModal(id) { $('#' + id).style.display = 'none'; }

function systemConfirm({ title = '¿Confirmar acción?', message = 'Esta acción no se puede deshacer.', confirmText = 'Sí, Continuar', cancelText = 'Cancelar', icon = '🗑️', isDanger = true } = {}) {
  return new Promise((resolve) => {
    const modal = $('#modal-confirm');
    if (!modal) {
      resolve(window.confirm(message.replace(/<[^>]*>?/gm, '')));
      return;
    }
    $('#confirm-modal-title').textContent = title;
    $('#confirm-modal-msg').innerHTML = message;
    $('#confirm-modal-icon').textContent = icon;

    const iconBox = $('#confirm-modal-icon');
    if (isDanger) {
      iconBox.style.background = 'rgba(239, 68, 68, 0.15)';
      iconBox.style.borderColor = 'rgba(239, 68, 68, 0.3)';
      iconBox.style.color = '#ef4444';
    } else {
      iconBox.style.background = 'rgba(59, 130, 246, 0.15)';
      iconBox.style.borderColor = 'rgba(59, 130, 246, 0.3)';
      iconBox.style.color = '#3b82f6';
    }

    const btnConfirm = $('#confirm-modal-btn');
    const btnCancel = $('#confirm-modal-cancel');
    btnConfirm.textContent = confirmText;
    btnConfirm.className = isDanger ? 'btn btn-danger' : 'btn btn-primary';
    btnCancel.textContent = cancelText;

    const cleanup = (result) => {
      closeModal('modal-confirm');
      btnConfirm.onclick = null;
      btnCancel.onclick = null;
      resolve(result);
    };

    btnConfirm.onclick = () => cleanup(true);
    btnCancel.onclick = () => cleanup(false);

    openModal('modal-confirm');
  });
}

function setPage(pageId) {
  $$('.nav a').forEach(a => a.classList.toggle('active', a.dataset.page === pageId));
  $$('main > section').forEach(s => s.style.display = 'none');
  const target = $('#page-' + pageId);
  if (target) target.style.display = 'block';

  if (pageId === 'dashboard') loadDashboard();
  if (pageId === 'saas-gimnasios') loadSaasGimnasios();
  if (pageId === 'saas-pagos') loadSaasPagos();
  if (pageId === 'alumnos' || pageId === 'coach-alumnos') { loadAlumnos(); loadProfesOptions(); }
  if (pageId === 'profesores') loadProfesores();
  if (pageId === 'rutinas' || pageId === 'mi-rutina') { loadRutinas(); loadAlumnosOptions(); }
  if (pageId === 'nutricion' || pageId === 'mi-nutricion') { loadNutricion(); loadAlumnosOptions(); }
  if (pageId === 'pagos' || pageId === 'mis-pagos' || pageId === 'coach-ingresos') { loadPagos(); loadAlumnosOptions(); loadProfesOptions(); }
  if (pageId === 'reportes') loadReportes();
  if (pageId === 'config') { loadConfig(); loadGymData(); }
  if (pageId === 'usuarios') { loadUsuarios(); loadProfesOptions(); loadAlumnosOptions(); }
  if (pageId === 'mi-membresia') loadAlumnoPortal();
}

$$('.nav a').forEach(a => a.addEventListener('click', e => {
  e.preventDefault();
  setPage(a.dataset.page);
}));

/* ===== AUDIT SWITCHER (SUPERADMIN) ===== */
async function switchAuditGym(gymId) {
  const r = await api('saas.switch_audit', { gimnasio_id: gymId });
  if (r.ok) {
    showToast(gymId == 0 ? 'Modo Global SaaS activado' : `Auditando Sede ID ${gymId}`);
    loadDashboard();
    if (CURRENT_USER.role !== 'alumno') {
      loadAlumnos();
      loadProfesores();
    }
  }
}

/* ===== INVITACIONES MULTI-TENANT ===== */
async function openInviteModal() {
  const { ok, data } = await api('invitaciones.get_links', {}, 'GET');
  if (!ok) return;
  $('#inv-link-alumno').value = data.link_alumno;
  $('#inv-link-coach').value = data.link_coach;
  openModal('modal-invite');
}

function copyLink(inputId) {
  const input = $('#' + inputId);
  input.select();
  navigator.clipboard.writeText(input.value);
  showToast('¡Enlace copiado al portapapeles!');
}

function shareWhatsAppInvite() {
  const link = $('#inv-link-alumno').value;
  const text = `¡Hola! Podés registrarte en nuestro gimnasio ingresando en este enlace directo: ${link}`;
  window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
}

/* ===== DASHBOARD ===== */
async function loadDashboard() {
  const { ok, data } = await api('dashboard.kpis', {}, 'GET');
  if (!ok) return;

  if (data.role === 'admin_general' || data.role === 'dueno') {
    const curAudit = data.effective_gym_id || 0;
    _saasGymsCache = data.all_gyms || [];

    // Poblar Switcher de SuperAdmin y Hub de Escritorios
    const sw = $('#superadmin-gym-switcher');
    if (sw && data.all_gyms) {
      sw.innerHTML = '<option value="0">🌐 Todas las Sedes (Global SaaS)</option>';
      data.all_gyms.forEach(g => {
        const opt = document.createElement('option');
        opt.value = g.id;
        opt.textContent = `🏢 ${g.nombre} (${g.suscripcion_estado.toUpperCase()})`;
        if (g.id == curAudit) opt.selected = true;
        sw.appendChild(opt);
      });
    }

    const deskBadge = $('#saas-active-desk-badge');
    if (deskBadge) {
      if (curAudit == 0) {
        deskBadge.className = 'badge b-info';
        deskBadge.textContent = '🌐 Vista Global (Todas las Sedes)';
      } else {
        const activeGym = (data.all_gyms || []).find(g => g.id == curAudit);
        const gName = activeGym ? activeGym.nombre : `Sede #${curAudit}`;
        const dUser = activeGym && activeGym.dueno_usuario ? activeGym.dueno_usuario : 'Dueño';
        deskBadge.className = 'badge b-ok pulse';
        deskBadge.innerHTML = `🏢 Escritorio Activo: <b>${gName}</b> (Dueño: ${dUser})`;
      }
    }

    // SI ES SUPERADMIN EN VISTA GLOBAL SAAS: MOSTRAR ESTADÍSTICA DE COBROS A DUEÑOS
    if (data.is_super && curAudit == 0) {
      if ($('#lbl-kpi-1')) $('#lbl-kpi-1').textContent = '💵 Mi Facturación SaaS (Mes)';
      if ($('#kpi-alumnos')) $('#kpi-alumnos').textContent = '$ ' + fmtMoney(data.saas?.ingresos_mes || 0);
      if ($('#sub-kpi-1')) $('#sub-kpi-1').innerHTML = `Acumulado Anual: $ <b>${fmtMoney(data.saas?.ingresos_anio || 0)}</b>`;

      if ($('#lbl-kpi-2')) $('#lbl-kpi-2').textContent = '🏢 Sedes & Dueños Habilitados';
      if ($('#kpi-profesores')) $('#kpi-profesores').textContent = `${data.saas?.gyms_activos || 0} Activas`;
      if ($('#sub-kpi-2')) $('#sub-kpi-2').innerHTML = `<span class="badge b-warn">${data.saas?.gyms_proximos || 0} por vencer</span> <span class="badge b-bad">${data.saas?.gyms_vencidos || 0} suspendidas</span>`;

      if ($('#lbl-kpi-3')) $('#lbl-kpi-3').textContent = '💰 Recaudación SaaS Hoy';
      if ($('#rec-hoy')) $('#rec-hoy').textContent = fmtMoney(data.saas?.ingresos_hoy || 0);
      if ($('#sub-kpi-3')) $('#sub-kpi-3').innerHTML = `Potencial Mensual: $ <b>${fmtMoney(data.saas?.potencial_mes || 0)}</b>`;

      if ($('#lbl-kpi-4')) $('#lbl-kpi-4').textContent = '📊 Tasa de Cobranza a Dueños';
      if ($('#kpi-mes')) $('#kpi-mes').textContent = `${data.saas?.cobranza_pct || 100}%`;
      if ($('#sub-kpi-4')) $('#sub-kpi-4').innerHTML = `${data.saas?.gyms_activos || 0} de ${data.saas?.total_gyms || 0} sedes al día`;

      if ($('#dash-chart-title')) $('#dash-chart-title').textContent = '🎯 Cumplimiento de Suscripciones SaaS (Dueños)';
      if ($('#dash-chart-subtitle')) $('#dash-chart-subtitle').textContent = `Estado de las ${data.saas?.total_gyms || 0} sedes registradas`;
      
      if ($('#dash-charts-container')) $('#dash-charts-container').style.display = 'none';
      if ($('#dash-saas-chart-box')) $('#dash-saas-chart-box').style.display = 'flex';

      requestAnimationFrame(() => {
        const cSaas = $('#chart-saas');
        if (cSaas) {
          drawDonut(cSaas, [
            { label: 'Dueños al Día', value: data.saas?.gyms_activos || 0, color: '#10b981' },
            { label: 'Por Vencer', value: data.saas?.gyms_proximos || 0, color: '#f59e0b' },
            { label: 'Vencidas / Suspendidas', value: data.saas?.gyms_vencidos || 0, color: '#ef4444' }
          ], `${data.saas?.total_gyms || 0}`, `${data.saas?.gyms_activos || 0} AL DÍA`);
        }
      });

      const sLeg = $('#dash-saas-chart-legend');
      if (sLeg) {
        sLeg.innerHTML = `
          <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:8px">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <span style="font-weight:800;font-size:13px;color:#fff">🏢 Sedes Totales: ${data.saas?.total_gyms || 0}</span>
              <span class="badge b-info">${data.saas?.cobranza_pct || 100}% Cobrado</span>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <span class="badge b-ok">🟢 ${data.saas?.gyms_activos || 0} al día</span>
              <span class="badge b-warn">🟡 ${data.saas?.gyms_proximos || 0} por vencer</span>
              <span class="badge b-bad">🔴 ${data.saas?.gyms_vencidos || 0} mora</span>
            </div>
            <p style="font-size:11.5px;color:var(--t2);margin:0">Facturación SaaS recaudada: <b style="color:var(--ok)">$ ${fmtMoney(data.saas?.ingresos_mes || 0)}</b></p>
          </div>
        `;
      }

      if ($('#dash-table-title')) $('#dash-table-title').textContent = '⚠️ Suscripciones de Dueños por Cobrar / Próximas a Vencer';
      renderSaasDueñosTabla(data.saas?.prox_vencimientos || []);

    } else {
      // SI ESTÁ AUDITANDO UN GIMNASIO ESPECÍFICO O ES UN DUEÑO
      if ($('#lbl-kpi-1')) $('#lbl-kpi-1').textContent = 'Total Alumnos';
      if ($('#kpi-alumnos')) $('#kpi-alumnos').textContent = data.totales?.alumnos || 0;
      if ($('#sub-kpi-1')) $('#sub-kpi-1').innerHTML = `<span class="badge b-ok">${data.totales?.alumnos_pagaron || 0} al día</span> <span class="badge b-bad">${data.totales?.alumnos_deudores || 0} con deuda</span>`;

      if ($('#lbl-kpi-2')) $('#lbl-kpi-2').textContent = 'Coaches & Profes';
      if ($('#kpi-profesores')) $('#kpi-profesores').textContent = data.totales?.profesores || 0;
      if ($('#sub-kpi-2')) {
        const profPagados = data.totales?.profesores_pagados || 0;
        const profTotal = data.totales?.profesores || 0;
        const profDeuda = Math.max(0, profTotal - profPagados);
        $('#sub-kpi-2').innerHTML = `<span class="badge b-purple">${profPagados} al día</span> <span class="badge ${profDeuda > 0 ? 'b-warn' : 'b-ok'}">${profDeuda} con deuda</span>`;
      }

      if ($('#lbl-kpi-3')) $('#lbl-kpi-3').textContent = 'Recaudación de Hoy';
      if ($('#rec-hoy')) $('#rec-hoy').textContent = fmtMoney(data.recaudacion?.dia || 0);
      if ($('#sub-kpi-3')) $('#sub-kpi-3').innerHTML = `Semana: $ <b>${fmtMoney(data.recaudacion?.semana || 0)}</b>`;

      if ($('#lbl-kpi-4')) $('#lbl-kpi-4').textContent = 'Ingresos del Mes';
      if ($('#kpi-mes')) $('#kpi-mes').textContent = fmtMoney(data.totales?.ingresos_mes || 0);
      if ($('#sub-kpi-4')) $('#sub-kpi-4').textContent = 'Mes corriente';

      if ($('#dash-chart-title')) $('#dash-chart-title').textContent = '🎯 Estado de Cobranzas & Equipo';
      if ($('#dash-chart-subtitle')) {
        const aluTot = data.desglose?.alumnos_total || 0;
        const profTot = data.desglose?.profesores_total || 0;
        $('#dash-chart-subtitle').textContent = `Resumen de ${aluTot} ${aluTot === 1 ? 'Alumno' : 'Alumnos'} y ${profTot} ${profTot === 1 ? 'Coach' : 'Coaches'}`;
      }

      if ($('#dash-saas-chart-box')) $('#dash-saas-chart-box').style.display = 'none';
      if ($('#dash-charts-container')) $('#dash-charts-container').style.display = 'grid';

      const aluTot = data.desglose?.alumnos_total || 0;
      const aluPag = data.desglose?.alumnos_pagaron || 0;
      const aluDeud = data.desglose?.alumnos_deudores || 0;

      const profTot = data.desglose?.profesores_total || 0;
      const profPag = data.desglose?.profesores_pagaron || 0;
      const profDeud = data.desglose?.profesores_deuda || 0;

      // Actualizar Badges y Contadores Numéricos
      if ($('#dash-alu-tot-badge')) $('#dash-alu-tot-badge').textContent = `${aluTot} ${aluTot === 1 ? 'Alumno' : 'Alumnos'}`;
      if ($('#dash-alu-pagaron')) $('#dash-alu-pagaron').textContent = aluPag;
      if ($('#dash-alu-deuda')) $('#dash-alu-deuda').textContent = aluDeud;

      if ($('#dash-prof-tot-badge')) $('#dash-prof-tot-badge').textContent = `${profTot} ${profTot === 1 ? 'Coach' : 'Coaches'}`;
      if ($('#dash-prof-pagados')) $('#dash-prof-pagados').textContent = profPag;
      if ($('#dash-prof-deuda')) $('#dash-prof-deuda').textContent = profDeud;

      requestAnimationFrame(() => {
        // 1. Gráfico de Alumnos
        const cAlu = $('#chart-alumnos');
        if (cAlu) {
          drawDonut(cAlu, [
            { label: 'Alumnos al Día', value: aluPag, color: '#10b981' },
            { label: 'Alumnos con Deuda', value: aluDeud, color: '#ef4444' }
          ], `${aluTot}`, `${aluPag} AL DÍA`);
        }

        // 2. Gráfico de Coaches
        const cProf = $('#chart-coaches');
        if (cProf) {
          drawDonut(cProf, [
            { label: 'Coaches Pagados', value: profPag, color: '#8b5cf6' },
            { label: 'Coaches con Deuda', value: profDeud, color: '#f97316' }
          ], `${profTot}`, `${profPag} PAGADOS`);
        }
      });

      if ($('#dash-table-title')) $('#dash-table-title').textContent = '⚠️ Próximos Vencimientos de Cuotas (5 días)';
      renderProximosTabla(data.prox_vencimientos || []);
    }

    const gymGrid = $('#superadmin-gyms-grid');
    if (gymGrid && data.all_gyms) {
      gymGrid.innerHTML = '';

      // 1. Tarjeta de Vista Global
      const isGlobal = curAudit == 0;
      const globalCard = document.createElement('div');
      globalCard.style.background = isGlobal ? 'linear-gradient(135deg, rgba(59, 130, 246, 0.28), rgba(30, 58, 138, 0.5))' : 'rgba(255, 255, 255, 0.03)';
      globalCard.style.border = isGlobal ? '2px solid #3b82f6' : '1px solid var(--border)';
      globalCard.style.borderRadius = 'var(--r)';
      globalCard.style.padding = '20px';
      globalCard.style.display = 'flex';
      globalCard.style.flexDirection = 'column';
      globalCard.style.justifyContent = 'space-between';
      globalCard.style.gap = '16px';
      globalCard.innerHTML = `
        <div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:30px">🌐</span>
            ${isGlobal ? '<span class="badge b-ok" style="font-size:11.5px;padding:4px 10px">ACTIVO AHORA</span>' : '<span class="badge b-info" style="font-size:11.5px;padding:4px 10px">Consolidado</span>'}
          </div>
          <h3 style="font-size:17px;font-weight:800;margin-top:10px;color:#fff;line-height:1.3">Vista Global SaaS (Tus Ganancias)</h3>
          <p style="font-size:13px;color:var(--t2);margin-top:5px;line-height:1.4">Facturación de plataforma cobrada a los dueños.</p>
        </div>
        <div>
          <button class="btn ${isGlobal ? 'btn-secondary' : 'btn-primary'}" style="width:100%;padding:10px 14px;font-size:13.5px;font-weight:700" onclick="switchAuditGym(0)">
            ${isGlobal ? '✅ Viendo Tus Ganancias' : '👁️ Ver Vista Global SaaS'}
          </button>
        </div>
      `;
      gymGrid.appendChild(globalCard);

      // 2. Tarjetas de cada Gimnasio
      data.all_gyms.forEach(g => {
        const isSel = g.id == curAudit;
        let bClass = 'b-ok';
        let bText = 'Al Día';
        if (g.suscripcion_estado === 'proximo') { bClass = 'b-warn'; bText = 'Próximo'; }
        if (g.suscripcion_estado === 'vencido' || g.suscripcion_estado === 'suspendido') { bClass = 'b-bad'; bText = 'Suspendido'; }

        const card = document.createElement('div');
        card.style.background = isSel ? 'linear-gradient(135deg, rgba(16, 185, 129, 0.22), rgba(6, 78, 59, 0.45))' : 'rgba(255, 255, 255, 0.03)';
        card.style.border = isSel ? '2px solid #10b981' : '1px solid var(--border)';
        card.style.borderRadius = 'var(--r)';
        card.style.padding = '20px';
        card.style.display = 'flex';
        card.style.flexDirection = 'column';
        card.style.justifyContent = 'space-between';
        card.style.gap = '14px';

        card.innerHTML = `
          <div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
              <span style="font-size:28px">🏢</span>
              <span class="badge ${bClass}" style="font-size:11.5px;padding:4px 10px">${bText}</span>
            </div>
            <h3 style="font-size:16px;font-weight:800;margin-top:10px;color:#fff">${g.nombre}</h3>
            <div style="font-size:13px;color:var(--t2);margin-top:8px;line-height:1.6">
              <div>👤 <b>Dueño:</b> <span style="color:#60a5fa">${g.dueno_usuario || 'Sin asignar'}</span></div>
              <div>👥 <b>Socios:</b> ${g.total_alumnos || 0} | 🏋️ <b>Coaches:</b> ${g.total_profes || 0}</div>
              <div>💵 <b>Pago a Cobrar:</b> $ ${fmtMoney(g.suscripcion_monto || 45000)}/mes</div>
            </div>
          </div>
          <div style="display:flex;gap:8px">
            <button class="btn ${isSel ? 'btn-success' : 'btn-primary'} btn-sm" style="flex:1;padding:8px 12px;font-size:13px;font-weight:700" onclick="switchAuditGym(${g.id})">
              ${isSel ? '✅ Escritorio Activo' : '👁️ Auditar este Gimnasio'}
            </button>
            <button class="btn btn-secondary btn-sm" style="padding:8px 12px" title="Editar Sede" onclick="editGymById(${g.id})">✏️</button>
          </div>
        `;
        gymGrid.appendChild(card);
      });

      // 3. Tarjeta de Nuevo Gimnasio (Más compacta y discreta)
      const newCard = document.createElement('div');
      newCard.style.border = '1.5px dashed #475569';
      newCard.style.borderRadius = 'var(--r)';
      newCard.style.padding = '12px 14px';
      newCard.style.display = 'flex';
      newCard.style.flexDirection = 'column';
      newCard.style.alignItems = 'center';
      newCard.style.justifyContent = 'center';
      newCard.style.cursor = 'pointer';
      newCard.style.minHeight = '100px';
      newCard.style.textAlign = 'center';
      newCard.style.gap = '4px';
      newCard.style.background = 'rgba(255, 255, 255, 0.015)';
      newCard.style.transition = 'var(--tr)';
      newCard.onmouseenter = () => { newCard.style.borderColor = '#94a3b8'; newCard.style.background = 'rgba(255, 255, 255, 0.04)'; };
      newCard.onmouseleave = () => { newCard.style.borderColor = '#475569'; newCard.style.background = 'rgba(255, 255, 255, 0.015)'; };
      newCard.onclick = () => openGymModal();
      newCard.innerHTML = `
        <div style="font-size:20px;line-height:1">➕</div>
        <strong style="font-size:12px;color:#cbd5e1;font-weight:600">Crear Nuevo Gimnasio & Dueño</strong>
        <p style="font-size:10.5px;color:var(--t-mut);margin:0">Dar de alta sede</p>
      `;
      gymGrid.appendChild(newCard);
    }
  } else if (data.role === 'coach') {
    $('#coach-kpi-alumnos').textContent = data.totales.alumnos;
    $('#coach-kpi-activos').textContent = `${data.totales.alumnos_activos} activos`;
    $('#coach-kpi-vencidos').textContent = `${data.totales.alumnos_vencidos} vencidos`;
    $('#coach-kpi-ganancia').textContent = fmtMoney(data.totales.ganancia_mes);
    $('#coach-kpi-asist').textContent = `${data.totales.alumnos_activos || 0} activos`;
    $('#coach-kpi-variacion').textContent = data.totales.variacion_pct === null ? '—' : (data.totales.variacion_pct >= 0 ? `▲ +${data.totales.variacion_pct}%` : `▼ ${data.totales.variacion_pct}%`);

    renderProximosTabla(data.prox_vencimientos);
  } else if (data.role === 'alumno') {
    renderAlumnoPortal(data);
  }
}

// 1. Tabla de Cobranzas a Dueños de Gimnasios (Para SuperAdmin)
function renderSaasDueñosTabla(items) {
  const thead = $('#tbl-prox-thead');
  const tb = $('#tbl-prox tbody');
  if (!tb) return;
  if (thead) {
    thead.innerHTML = `<tr><th>Gimnasio & Dueño</th><th>Teléfono / WhatsApp</th><th>Pago Mensual</th><th>Vencimiento</th><th>Estado</th><th style="text-align:right">Acción</th></tr>`;
  }
  tb.innerHTML = '';
  if (!items || !items.length) {
    tb.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--t-mut);padding:18px">¡Excelente! Todos los dueños de gimnasios están al día 🎉</td></tr>';
    return;
  }
  items.forEach(g => {
    const isSusp = g.suscripcion_estado === 'suspendido';
    const isProx = g.suscripcion_estado === 'proximo';
    const badgeCls = isSusp ? 'b-bad' : (isProx ? 'b-warn pulse' : (g.suscripcion_estado === 'vencido' ? 'b-bad' : 'b-ok'));
    const badgeTxt = isSusp ? '⛔ SUSPENDIDO' : (isProx ? '⚠️ PRÓXIMO' : (g.suscripcion_estado === 'vencido' ? 'VENCIDO' : 'AL DÍA'));

    const telClean = (g.telefono || '').replace(/\D/g, '');
    const waBtn = telClean ? `<a href="https://wa.me/${telClean}?text=Hola%20${encodeURIComponent(g.dueno_usuario || g.nombre)},%20te%20escribimos%20de%20GYM%20PRO%20SaaS%20respecto%20a%20la%20suscripci%C3%B3n%20mensual%20de%20tu%20gimnasio%20(${encodeURIComponent(g.nombre)})." target="_blank" class="btn btn-sm btn-secondary" title="Cobrar por WhatsApp">💬</a>` : '';

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><b>${g.nombre}</b><br><small style="color:#60a5fa">Dueño: ${g.dueno_usuario || 'Sin asignar'}</small></td>
      <td>${g.telefono || '-'} ${waBtn}</td>
      <td style="font-weight:700;color:#60a5fa">$ ${fmtMoney(g.suscripcion_monto || 45000)}</td>
      <td><b>${fmtDate(g.suscripcion_vencimiento)}</b><br><small style="color:var(--t-mut)">${g.dias_restantes !== null ? (g.dias_restantes >= 0 ? `Quedan ${g.dias_restantes} días` : `Venció hace ${Math.abs(g.dias_restantes)} días`) : ''}</small></td>
      <td><span class="badge ${badgeCls}">${badgeTxt}</span></td>
      <td style="text-align:right;white-space:nowrap">
        <button class="btn btn-sm btn-success" onclick="openSaasPagoModal(${g.id})">💵 Cobrar SaaS</button>
      </td>
    `;
    tb.appendChild(tr);
  });
}

// 2. Tabla de Próximos Vencimientos de Alumnos (Para Dueños y Auditoría de Sede)
function renderProximosTabla(items) {
  const thead = $('#tbl-prox-thead');
  const tb = $('#tbl-prox tbody');
  if (!tb) return;
  if (thead) {
    thead.innerHTML = `<tr><th>Alumno</th><th>Teléfono</th><th>Vence</th><th>Estado</th><th style="text-align:right">Acción</th></tr>`;
  }
  tb.innerHTML = '';
  if (!items || !items.length) {
    tb.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--t-mut);padding:18px">No hay vencimientos de alumnos en los próximos 5 días 🎉</td></tr>';
    return;
  }
  items.forEach(r => {
    const tr = document.createElement('tr');
    const telClean = (r.telefono || '').replace(/\D/g, '');
    const waBtn = telClean ? `<a href="https://wa.me/${telClean}?text=Hola%20${encodeURIComponent(r.nombre)},%20te%20recordamos%20que%20tu%20cuota%20vence%20el%20${fmtDate(r.fecha_vencimiento)}." target="_blank" class="btn btn-sm btn-secondary" title="Avisar por WhatsApp">💬</a>` : '';
    tr.innerHTML = `
      <td><b>${r.nombre}</b></td>
      <td>${r.telefono || '-'} ${waBtn}</td>
      <td style="font-weight:700">${fmtDate(r.fecha_vencimiento)}</td>
      <td><span class="badge b-warn pulse">Próximo</span></td>
      <td style="text-align:right"><button class="btn btn-sm btn-primary" onclick="openPagoModal('alumno', ${r.id})">Cobrar</button></td>
    `;
    tb.appendChild(tr);
  });
}

/* ===== GENERADOR DE CÓDIGO QR VISUAL PARA CARNET ===== */
function generateQrSvg(dataString, size = 130) {
  let hash = 0;
  for (let i = 0; i < dataString.length; i++) hash = ((hash << 5) - hash) + dataString.charCodeAt(i);
  
  const modules = 21;
  const grid = Array.from({ length: modules }, () => Array(modules).fill(0));

  function drawFinder(r0, c0) {
    for (let r = 0; r < 7; r++) {
      for (let c = 0; c < 7; c++) {
        if (r === 0 || r === 6 || c === 0 || c === 6 || (r >= 2 && r <= 4 && c >= 2 && c <= 4)) {
          grid[r0 + r][c0 + c] = 1;
        }
      }
    }
  }
  drawFinder(0, 0);
  drawFinder(0, 14);
  drawFinder(14, 0);

  for (let i = 8; i < 13; i++) {
    grid[6][i] = (i % 2 === 0) ? 1 : 0;
    grid[i][6] = (i % 2 === 0) ? 1 : 0;
  }

  let seed = Math.abs(hash) || 1234567;
  for (let r = 0; r < modules; r++) {
    for (let c = 0; c < modules; c++) {
      const inFinder1 = r < 8 && c < 8;
      const inFinder2 = r < 8 && c >= 13;
      const inFinder3 = r >= 13 && c < 8;
      const inTiming = (r === 6 && c >= 8 && c <= 12) || (c === 6 && r >= 8 && r <= 12);
      if (!inFinder1 && !inFinder2 && !inFinder3 && !inTiming) {
        seed = (seed * 9301 + 49297) % 233280;
        grid[r][c] = (seed / 233280 > 0.45) ? 1 : 0;
      }
    }
  }

  const cellSize = (size / modules).toFixed(2);
  let rects = '';
  for (let r = 0; r < modules; r++) {
    for (let c = 0; c < modules; c++) {
      if (grid[r][c] === 1) {
        rects += `<rect x="${(c * cellSize).toFixed(2)}" y="${(r * cellSize).toFixed(2)}" width="${cellSize}" height="${cellSize}" fill="#0f172a" />`;
      }
    }
  }

  return `
    <svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" style="background:#ffffff;border-radius:10px;padding:6px;box-shadow:0 8px 20px rgba(0,0,0,0.35);display:block;margin:0 auto">
      ${rects}
    </svg>
  `;
}

/* ===== PORTAL DEL ALUMNO (DASHBOARD VS CARNET DIGITAL) ===== */
function renderAlumnoPortal(data) {
  const a = data.alumno || {};
  const isVencido = data.esta_vencido;
  const saldo = data.saldo_deuda;
  const diasRest = a.dias_restantes !== undefined ? a.dias_restantes : null;
  const diasTxt = diasRest !== null ? (diasRest >= 0 ? `Quedan ${diasRest} días` : `Venció hace ${Math.abs(diasRest)} días`) : 'Sin vencimiento';

  let debtBanner = '';
  if (saldo > 0 || isVencido) {
    debtBanner = `
      <div class="debt-banner">
        <div>
          <h3 style="color:#fca5a5;font-size:16px;font-weight:800;display:flex;align-items:center;gap:8px">
            <span>🚨 AVISO DE PAGO PENDIENTE / CUOTA VENCIDA</span>
          </h3>
          <p style="color:#fecaca;font-size:13px;margin-top:4px">
            Tu membresía se encuentra en estado <b>${isVencido ? 'VENCIDA' : 'CON SALDO PENDIENTE'}</b>. Monto adeudado: <b>$ ${fmtMoney(saldo)}</b>.
          </p>
        </div>
        <a href="https://wa.me/${(a.coach_tel || '5492664000000').replace(/\D/g,'')}?text=Hola,%20quisiera%20regularizar%20mi%20pago%20de%20cuota." target="_blank" class="btn btn-warn" style="font-weight:800">💬 Regularizar por WhatsApp</a>
      </div>
    `;
  }

  /* -------------------------------------------------------------
   * 1. DASHBOARD DEL ALUMNO (CENTRO DE ENTRENAMIENTO & PROGRESO)
   * ------------------------------------------------------------- */
  const dashHtml = `
    ${debtBanner}

    <!-- HERO BANNER MOTIVACIONAL -->
    <div style="background:linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(139, 92, 246, 0.25));border:1px solid rgba(59, 130, 246, 0.35);border-radius:var(--r-lg);padding:24px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
      <div>
        <span class="badge b-purple" style="font-size:12px;margin-bottom:8px">🔥 MI CENTRO DE ENTRENAMIENTO</span>
        <h2 style="font-size:24px;font-weight:800;color:#fff;margin-top:4px">¡Hola, ${a.nombre || CURRENT_USER.name}! 💪</h2>
        <p style="color:var(--t2);font-size:13px;margin-top:4px">
          Sede: <b style="color:#fff">${a.gimnasio_nombre || 'NITSOFT'}</b> • Coach Asignado: <b style="color:#60a5fa">${a.coach_nombre || 'Gimnasio General'}</b>
        </p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-primary" onclick="setPage('mi-membresia')" style="font-weight:800;box-shadow:0 8px 20px rgba(59, 130, 246, 0.4)">
          🪪 Ver Mi Carnet Digital & QR
        </button>
        <a href="https://wa.me/${(a.coach_tel || '5492664000000').replace(/\D/g,'')}?text=Hola%20${encodeURIComponent(a.coach_nombre || 'Coach')},%20te%20contacto%20desde%20la%20app." target="_blank" class="btn btn-secondary">
          💬 WhatsApp Coach
        </a>
      </div>
    </div>

    <!-- TARJETAS DE ESTADÍSTICAS DEL ALUMNO -->
    <div class="grid g4" style="margin-bottom:20px">
      <div class="stat-card">
        <div class="stat-label">📅 Próximo Vencimiento</div>
        <div class="stat-value" style="font-size:20px;color:${isVencido ? '#ef4444' : '#60a5fa'}">${a.fecha_vencimiento || '-'}</div>
        <div class="stat-sub">
          <span class="badge ${isVencido ? 'b-bad' : (diasRest !== null && diasRest <= 5 ? 'b-warn pulse' : 'b-ok')}">
            ${isVencido ? '⛔ Vencido' : diasTxt}
          </span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-label">💳 Estado de Cuota</div>
        <div class="stat-value" style="font-size:20px;color:${isVencido ? '#ef4444' : '#10b981'}">${isVencido ? 'Pago Pendiente' : 'Al Día'}</div>
        <div class="stat-sub">
          <span class="badge ${isVencido ? 'b-bad' : 'b-ok'}">${isVencido ? 'Mora' : 'Habilitado'}</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-label">🏷️ Plan Contratado</div>
        <div class="stat-value" style="font-size:20px;color:#a78bfa">Plan ${(a.plan || '3x').toUpperCase()}</div>
        <div class="stat-sub">
          <span>$ ${fmtMoney(data.cuota)} / mes</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-label">📋 Rutina Activa</div>
        <div class="stat-value" style="font-size:18px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--ok)">
          ${data.rutina ? data.rutina.titulo : 'Sin Asignar'}
        </div>
        <div class="stat-sub">
          <span>${data.rutina ? (data.rutina.objetivo || 'Entrenamiento') : 'Pedísela a tu coach'}</span>
        </div>
      </div>
    </div>

    <!-- ACCESO RÁPIDO A RUTINA Y NUTRICIÓN -->
    <div class="grid g2" style="margin-bottom:20px">
      <div class="card">
        <div class="card-header" style="justify-content:space-between">
          <div class="card-title" style="display:flex;align-items:center;gap:8px">
            <span>💪 Tu Rutina de Entrenamiento</span>
            <span class="badge ${data.rutina ? 'b-ok' : 'b-warn'}">${data.rutina ? 'Activa' : 'Pendiente'}</span>
          </div>
          <button class="btn btn-sm btn-primary" onclick="setPage('mi-rutina')">Ver Rutina Completa →</button>
        </div>
        <div>
          ${data.rutina ? `
            <div style="margin-bottom:10px">
              <strong style="color:#fff;font-size:16px">${data.rutina.titulo}</strong>
              <div style="font-size:12px;color:var(--t2);margin-top:2px">Días: <b>${data.rutina.dias_semana || 'Lunes a Viernes'}</b> • Objetivo: <b>${data.rutina.objetivo || 'Ganancia'}</b></div>
            </div>
            <div style="background:var(--bg-inp);padding:14px;border-radius:10px;font-size:13px;max-height:140px;overflow-y:auto;line-height:1.6;white-space:pre-wrap;border:1px solid var(--border)">
              ${data.rutina.detalles}
            </div>
          ` : `
            <div style="text-align:center;padding:30px;color:var(--t-mut)">
              <div style="font-size:28px;margin-bottom:6px">📋</div>
              <p>Tu coach todavía no cargó una rutina personalizada.</p>
              <a href="https://wa.me/${(a.coach_tel || '5492664000000').replace(/\D/g,'')}?text=Hola,%20quisiera%20solicitar%20mi%20rutina%20de%20entrenamiento." target="_blank" class="btn btn-sm btn-secondary" style="margin-top:10px">💬 Solicitar Rutina por WhatsApp</a>
            </div>
          `}
        </div>
      </div>

      <div class="card">
        <div class="card-header" style="justify-content:space-between">
          <div class="card-title" style="display:flex;align-items:center;gap:8px">
            <span>🥑 Tu Plan Nutricional</span>
            <span class="badge ${data.nutricion ? 'b-purple' : 'b-warn'}">${data.nutricion ? 'Asignado' : 'Sin plan'}</span>
          </div>
          <button class="btn btn-sm btn-secondary" onclick="setPage('mi-nutricion')">Ver Plan Completo →</button>
        </div>
        <div>
          ${data.nutricion ? `
            <div style="margin-bottom:10px">
              <strong style="color:#fff;font-size:16px">${data.nutricion.titulo}</strong>
              <div style="font-size:12px;color:var(--t2);margin-top:2px">Meta Energética: <b style="color:#38bdf8">${data.nutricion.calorias_aprox} kcal / día</b></div>
            </div>
            <div style="background:var(--bg-inp);padding:14px;border-radius:10px;font-size:13px;max-height:140px;overflow-y:auto;line-height:1.6;white-space:pre-wrap;border:1px solid var(--border)">
              ${data.nutricion.detalles}
            </div>
          ` : `
            <div style="text-align:center;padding:30px;color:var(--t-mut)">
              <div style="font-size:28px;margin-bottom:6px">🥗</div>
              <p>No tenés un plan de comidas activo actualmente.</p>
              <a href="https://wa.me/${(a.coach_tel || '5492664000000').replace(/\D/g,'')}?text=Hola,%20quisiera%20consultar%20por%20un%20plan%20nutricional." target="_blank" class="btn btn-sm btn-secondary" style="margin-top:10px">💬 Consultar Plan a Coach</a>
            </div>
          `}
        </div>
      </div>
    </div>
  `;

  if ($('#alumno-dashboard-container')) $('#alumno-dashboard-container').innerHTML = dashHtml;

  /* -------------------------------------------------------------
   * 2. MI CARNET DIGITAL (CREDENCIAL VIP CON CÓDIGO QR DE ACCESO)
   * ------------------------------------------------------------- */
  const qrSvg = generateQrSvg(`GYM_SOCIO_${a.id || 1}_${a.nombre || 'SOCIO'}_${a.fecha_vencimiento || '2026'}`);

  const carnetHtml = `
    ${debtBanner}

    <div style="max-width:580px;margin:0 auto">
      <!-- CREDENCIAL DIGITAL VIP PASS -->
      <div style="background:linear-gradient(145deg, #111827 0%, #1e1b4b 50%, #090d16 100%);border:2px solid ${isVencido ? '#ef4444' : '#3b82f6'};border-radius:24px;padding:28px;box-shadow:0 25px 60px rgba(0,0,0,0.8);position:relative;overflow:hidden">
        
        <!-- DECORACIÓN HOLOGRÁFICA SUPERIOR -->
        <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,0.1);padding-bottom:16px;margin-bottom:20px">
          <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:28px">🏋️</span>
            <div>
              <div style="font-size:16px;font-weight:800;color:#fff;letter-spacing:0.5px">${a.gimnasio_nombre || 'NITSOFT'}</div>
              <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px">Membresía Oficial de Socio</div>
            </div>
          </div>
          <span class="badge b-purple" style="font-size:11px;padding:5px 12px;font-weight:800">SOCIO VIP</span>
        </div>

        <!-- CUERPO PRINCIPAL DEL CARNET: DATOS + QR -->
        <div style="display:grid;grid-template-columns:1fr 140px;gap:20px;align-items:center">
          <div>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
              <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg, var(--pri), var(--sec));display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:#fff;border:2px solid rgba(255,255,255,0.3)">
                ${(a.nombre || CURRENT_USER.name).substring(0,1).toUpperCase()}
              </div>
              <div>
                <h2 style="font-size:20px;font-weight:800;color:#fff;margin:0;line-height:1.2">${a.nombre || CURRENT_USER.name}</h2>
                <div style="font-size:12px;color:#38bdf8;font-weight:700">Socio N°: #SOC-${String(a.id || 1).padStart(5, '0')}</div>
              </div>
            </div>

            <div style="font-size:12px;color:var(--t2);line-height:1.6;margin-bottom:14px">
              <div>🏷️ <b>Plan:</b> <span style="color:#fff;font-weight:700">Plan ${(a.plan || '3x').toUpperCase()}</span> ($ ${fmtMoney(data.cuota)})</div>
              <div>🎯 <b>Actividades:</b> <span style="color:#fff">${a.actividades || 'Musculación, Funcional'}</span></div>
              <div>🏋️ <b>Coach a Cargo:</b> <span style="color:#c084fc;font-weight:700">${a.coach_nombre || 'Gimnasio General'}</span></div>
            </div>

            <!-- INDICADOR LUMINOSO DE ESTADO -->
            <div>
              <span class="badge ${isVencido ? 'b-bad' : 'b-ok pulse'}" style="font-size:12px;padding:6px 14px;font-weight:800">
                ${isVencido ? '⛔ ACCESO DENEGADO / CUOTA VENCIDA' : '🟢 ACCESO HABILITADO / AL DÍA'}
              </span>
            </div>
          </div>

          <!-- CÓDIGO QR PARA ESCANEAR EN LA ENTRADA -->
          <div style="text-align:center">
            ${qrSvg}
            <div style="font-size:10px;color:var(--t-mut);margin-top:6px;font-weight:700">ESCANEAR EN ACCESO</div>
          </div>
        </div>

        <!-- FOOTER DEL CARNET -->
        <div style="border-top:1px solid rgba(255,255,255,0.1);padding-top:16px;margin-top:20px;display:flex;justify-content:space-between;align-items:center;font-size:12px">
          <div>
            <span style="color:var(--t-mut)">Vigencia de Cuota:</span>
            <div style="font-size:15px;font-weight:800;color:${isVencido ? '#ef4444' : '#60a5fa'}">${fmtDate(a.fecha_vencimiento)}</div>
          </div>
          <div style="text-align:right">
            <span style="color:var(--t-mut)">Estado de Membresía:</span>
            <div style="font-size:15px;font-weight:800;color:${isVencido ? '#ef4444' : '#10b981'}">${isVencido ? '⛔ VENCIDO' : '✅ ACTIVO'}</div>
          </div>
        </div>

      </div>

      <!-- INSTRUCCIÓN DE USO EN RECEPCIÓN -->
      <div style="text-align:center;margin-top:16px;font-size:13px;color:var(--t2)">
        💡 Presentá este código QR en la recepción o molinete de <b>${a.gimnasio_nombre || 'tu gimnasio'}</b> para registrar tu ingreso automático.
      </div>
    </div>
  `;

  if ($('#alumno-portal-carnet')) $('#alumno-portal-carnet').innerHTML = carnetHtml;

  // Render Rutina del Alumno
  if ($('#alumno-portal-rutina')) {
    if (data.rutina) {
      $('#alumno-portal-rutina').innerHTML = `
        <div class="card">
          <div class="card-header">
            <div>
              <span class="badge b-ok">Rutina Activa</span>
              <h2 style="font-size:20px;font-weight:800;margin-top:6px">${data.rutina.titulo}</h2>
              <p style="color:var(--t2);font-size:13px">Objetivo: <b>${data.rutina.objetivo}</b> | Días: <b>${data.rutina.dias_semana}</b> | Asignada: <b>${fmtDate(data.rutina.fecha_asignacion)}</b></p>
            </div>
          </div>
          <div style="background:var(--bg-inp);padding:18px;border-radius:12px;white-space:pre-wrap;font-size:14px;line-height:1.6">${data.rutina.detalles}</div>
        </div>`;
    } else {
      $('#alumno-portal-rutina').innerHTML = `<div class="card" style="text-align:center;padding:40px;color:var(--t-mut)">Tu coach aún no cargó una rutina personalizada. ¡Solicitásela en tu próxima visita!</div>`;
    }
  }

  // Render Nutrición del Alumno
  if ($('#alumno-portal-nutri')) {
    if (data.nutricion) {
      $('#alumno-portal-nutri').innerHTML = `
        <div class="card">
          <div class="card-header">
            <div>
              <span class="badge b-purple">Plan Nutricional</span>
              <h2 style="font-size:20px;font-weight:800;margin-top:6px">${data.nutricion.titulo}</h2>
              <p style="color:var(--t2);font-size:13px">Calorías Objetivo: <b>${data.nutricion.calorias_aprox} kcal / día</b> | Asignado: <b>${fmtDate(data.nutricion.fecha_asignacion)}</b></p>
            </div>
          </div>
          <div style="background:var(--bg-inp);padding:18px;border-radius:12px;white-space:pre-wrap;font-size:14px;line-height:1.6">${data.nutricion.detalles}</div>
        </div>`;
    } else {
      $('#alumno-portal-nutri').innerHTML = `<div class="card" style="text-align:center;padding:40px;color:var(--t-mut)">No tenés un plan de comidas activo. Tu coach puede asignarte uno en cualquier momento.</div>`;
    }
  }

  // Render Mis Pagos
  if ($('#tbl-mis-pagos tbody') && data.mis_pagos) {
    const tb = $('#tbl-mis-pagos tbody');
    tb.innerHTML = '';
    data.mis_pagos.forEach(p => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td><b>${fmtDate(p.fecha_pago)}</b></td><td>Plan ${p.plan || '-'}</td><td><span class="badge b-ok">${p.medio_pago}</span></td><td style="text-align:right;font-weight:800;color:var(--ok)">$ ${fmtMoney(p.monto)}</td><td>${p.observaciones || '-'}</td>`;
      tb.appendChild(tr);
    });
  }
}

async function loadAlumnoPortal() {
  const { ok, data } = await api('dashboard.kpis', {}, 'GET');
  if (ok && data.role === 'alumno') renderAlumnoPortal(data);
}

/* ===== SAAS GIMNASIOS & DUEÑOS (SUPERADMIN) ===== */
let _saasGymsCache = [];
async function loadSaasGimnasios() {
  const { ok, data } = await api('saas.gimnasios.list', {}, 'GET');
  if (!ok) return;
  _saasGymsCache = data;

  const tb = $('#tbl-saas-gyms tbody');
  if (!tb) return;
  tb.innerHTML = '';
  data.forEach(g => {
    const isSusp = g.suscripcion_estado === 'suspendido';
    const isProx = g.suscripcion_estado === 'proximo';
    const badgeCls = isSusp ? 'b-bad' : (isProx ? 'b-warn pulse' : (g.suscripcion_estado === 'vencido' ? 'b-bad' : 'b-ok'));
    const badgeTxt = isSusp ? '⛔ SUSPENDIDO' : (isProx ? '⚠️ PRONTO A VENCER' : (g.suscripcion_estado === 'vencido' ? 'VENCIDO' : 'AL DÍA (ACTIVO)'));

    const telClean = (g.telefono || '').replace(/\D/g, '');
    const waLink = telClean ? `<a href="https://wa.me/${telClean}" target="_blank" style="color:var(--ok);text-decoration:none;margin-left:4px">💬</a>` : '';

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><b>${g.nombre}</b><br><small style="color:#60a5fa">Code: ${g.invite_code || '-'}</small><br><small style="color:var(--t-mut)">${g.direccion || '-'}</small></td>
      <td><b>${g.dueno_usuario || '-'}</b><br><small style="color:var(--t2)">${g.dueno_email || ''}</small></td>
      <td>${g.telefono || '-'} ${waLink}</td>
      <td style="font-weight:700;color:#60a5fa">$ ${fmtMoney(g.suscripcion_monto)}</td>
      <td><b>${fmtDate(g.suscripcion_vencimiento)}</b><br><small style="color:var(--t-mut)">${g.dias_para_vencer !== null ? (g.dias_para_vencer >= 0 ? `Quedan ${g.dias_para_vencer} días` : `Venció hace ${Math.abs(g.dias_para_vencer)} días`) : ''}</small></td>
      <td><span class="badge ${badgeCls}">${badgeTxt}</span></td>
      <td><span class="badge b-purple">${g.total_alumnos_gym} socios</span></td>
      <td style="text-align:right;white-space:nowrap">
        <button class="btn btn-sm btn-success" onclick="openSaasPagoModal(${g.id})">💵 Cobrar SaaS</button>
        <button class="btn btn-sm ${isSusp ? 'btn-warn' : 'btn-danger'}" onclick="toggleSuspensionGym(${g.id}, '${g.suscripcion_estado}')">${isSusp ? '✅ Reactivar' : '🚫 Suspender'}</button>
      </td>
    `;
    tb.appendChild(tr);
  });
}

function openGymModal() {
  $('#saas-gym-id').value = '';
  $('#saas-gym-nombre').value = '';
  $('#saas-gym-code').value = '';
  $('#saas-gym-tel').value = '';
  $('#saas-gym-email').value = '';
  $('#saas-gym-dir').value = '';
  $('#saas-gym-monto').value = '45000.00';
  const in30 = new Date();
  in30.setDate(in30.getDate() + 30);
  $('#saas-gym-venc').value = in30.toISOString().split('T')[0];
  $('#saas-dueno-user').value = '';
  $('#saas-dueno-pass').value = '';
  if ($('#gym-modal-title')) $('#gym-modal-title').textContent = '➕ Habilitar / Crear Gimnasio & Dueño';
  openModal('modal-gym');
}

function editGymById(id) {
  const g = (_saasGymsCache || []).find(x => x.id == id);
  if (!g) {
    setPage('saas-gimnasios');
    return;
  }
  $('#saas-gym-id').value = g.id;
  $('#saas-gym-nombre').value = g.nombre || '';
  $('#saas-gym-code').value = g.invite_code || '';
  $('#saas-gym-tel').value = g.telefono || '';
  $('#saas-gym-email').value = g.email || '';
  $('#saas-gym-dir').value = g.direccion || '';
  $('#saas-gym-monto').value = g.suscripcion_monto || 45000;
  $('#saas-gym-venc').value = g.suscripcion_vencimiento || '';
  $('#saas-dueno-user').value = g.dueno_usuario || '';
  $('#saas-dueno-pass').value = '';
  if ($('#gym-modal-title')) $('#gym-modal-title').textContent = `✏️ Editar Gimnasio (${g.nombre})`;
  openModal('modal-gym');
}

async function saveGymSaaS(e) {
  e.preventDefault();
  const data = {
    id: $('#saas-gym-id').value,
    nombre: $('#saas-gym-nombre').value,
    invite_code: $('#saas-gym-code').value,
    telefono: $('#saas-gym-tel').value,
    email: $('#saas-gym-email').value,
    direccion: $('#saas-gym-dir').value,
    suscripcion_monto: $('#saas-gym-monto').value,
    suscripcion_vencimiento: $('#saas-gym-venc').value,
    dueno_usuario: $('#saas-dueno-user').value,
    dueno_password: $('#saas-dueno-pass').value
  };

  const r = await api('saas.gimnasios.save', data);
  if (r.ok) {
    showToast('Gimnasio y Dueño guardados exitosamente');
    closeModal('modal-gym');
    await loadSaasGimnasios();
    await loadDashboard();
  } else {
    showToast(r.msg || 'Error al guardar gimnasio', true);
  }
}

async function toggleSuspensionGym(id, estadoActual) {
  const isSusp = estadoActual === 'suspendido';
  const r = await api('saas.gimnasios.toggle_suspension', { id, estado_actual: estadoActual });
  if (r.ok) {
    showToast(isSusp ? 'Gimnasio y dueño reactivados con éxito' : 'Gimnasio y dueño suspendidos');
    await loadSaasGimnasios();
    await loadDashboard();
  } else {
    showToast(r.msg || 'Error al cambiar estado', true);
  }
}

async function loadSaasPagos() {
  const { ok, data } = await api('saas.pagos.list', {}, 'GET');
  if (!ok) return;
  const tb = $('#tbl-saas-pagos tbody');
  if (!tb) return;
  tb.innerHTML = '';
  data.forEach(p => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><b>${fmtDate(p.fecha_pago)}</b></td>
      <td><b>${p.gimnasio_nombre}</b></td>
      <td>${p.dueno_nombre}</td>
      <td><span class="badge b-info">${p.periodo_mes}</span></td>
      <td><span class="badge b-ok">${p.medio_pago}</span></td>
      <td style="text-align:right;font-weight:800;color:var(--ok)">$ ${fmtMoney(p.monto)}</td>
      <td style="color:var(--t-mut)">${p.comprobante || '-'} ${p.observaciones ? `(${p.observaciones})` : ''}</td>
    `;
    tb.appendChild(tr);
  });
}

async function openSaasPagoModal(gymId = null) {
  if (!_saasGymsCache || !_saasGymsCache.length) {
    const { ok, data } = await api('saas.gimnasios.list', {}, 'GET');
    if (ok && data) _saasGymsCache = data;
  }
  const sel = $('#saas-pago-gym');
  if (sel) {
    sel.innerHTML = '<option value="">(Seleccionar Gimnasio)</option>';
    _saasGymsCache.forEach(g => {
      const opt = document.createElement('option');
      opt.value = g.id;
      opt.textContent = `${g.nombre} (Dueño: ${g.dueno_usuario || 'Sin asignar'}) - $ ${fmtMoney(g.suscripcion_monto || 45000)}`;
      sel.appendChild(opt);
    });
    if (gymId) sel.value = gymId;
  }
  openModal('modal-saas-pago');
}

async function saveSaasPago(e) {
  e.preventDefault();
  const data = {
    gimnasio_id: $('#saas-pago-gym').value,
    monto: $('#saas-pago-monto').value,
    fecha_pago: $('#saas-pago-fecha').value,
    medio_pago: $('#saas-pago-medio').value,
    comprobante: $('#saas-pago-comp').value
  };

  const r = await api('saas.pagos.save', data);
  if (r.ok) {
    showToast('Pago de suscripción registrado y servicio renovado');
    closeModal('modal-saas-pago');
    await loadSaasGimnasios();
    await loadSaasPagos();
    await loadDashboard();
  } else {
    showToast(r.msg || 'Error al asentar pago', true);
  }
}



/* ===== RUTINAS ===== */
let _rutinasCache = [];
async function loadRutinas() {
  const { ok, data } = await api('rutinas.list', {}, 'GET');
  if (!ok) return;
  _rutinasCache = data;
  const tb = $('#tbl-rutinas tbody');
  if (!tb) return;
  tb.innerHTML = '';
  data.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><b>${r.alumno_nombre}</b></td>
      <td><b>${r.titulo}</b></td>
      <td>${r.objetivo || '-'}</td>
      <td>${r.dias_semana || '-'}</td>
      <td>${fmtDate(r.fecha_asignacion)}</td>
      <td><span class="badge b-ok">${r.estado}</span></td>
      <td style="text-align:right">
        <button class="btn btn-sm btn-secondary" onclick='openRutinaModal(${JSON.stringify(r)})'>✏️ Editar</button>
      </td>
    `;
    tb.appendChild(tr);
  });
}

function openRutinaModal(row = null) {
  $('#rutina-modal-title').textContent = row ? 'Editar Rutina' : 'Cargar Rutina de Entrenamiento';
  $('#rutina-id').value = row?.id || '';
  $('#rutina-alumno').value = row?.alumno_id || '';
  $('#rutina-titulo').value = row?.titulo || '';
  $('#rutina-obj').value = row?.objetivo || '';
  $('#rutina-dias').value = row?.dias_semana || 'Lunes a Viernes';
  $('#rutina-det').value = row?.detalles || '';
  openModal('modal-rutina');
}

async function saveRutina(e) {
  e.preventDefault();
  const data = {
    id: $('#rutina-id').value,
    alumno_id: $('#rutina-alumno').value,
    titulo: $('#rutina-titulo').value,
    objetivo: $('#rutina-obj').value,
    dias_semana: $('#rutina-dias').value,
    detalles: $('#rutina-det').value
  };
  const r = await api('rutinas.save', data);
  if (r.ok) {
    showToast('Rutina guardada correctamente');
    closeModal('modal-rutina');
    loadRutinas();
  } else {
    showToast(r.msg || 'Error', true);
  }
}

/* ===== NUTRICIÓN ===== */
async function loadNutricion() {
  const { ok, data } = await api('nutricion.list', {}, 'GET');
  if (!ok) return;
  const tb = $('#tbl-nutricion tbody');
  if (!tb) return;
  tb.innerHTML = '';
  data.forEach(n => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><b>${n.alumno_nombre}</b></td>
      <td><b>${n.titulo}</b></td>
      <td><span class="badge b-purple">${n.calorias_aprox} kcal</span></td>
      <td>${n.coach_nombre || 'General'}</td>
      <td>${fmtDate(n.fecha_asignacion)}</td>
      <td style="text-align:right">
        <button class="btn btn-sm btn-secondary" onclick='openNutriModal(${JSON.stringify(n)})'>✏️ Editar</button>
      </td>
    `;
    tb.appendChild(tr);
  });
}

function openNutriModal(row = null) {
  $('#nutri-modal-title').textContent = row ? 'Editar Plan Nutricional' : 'Cargar Plan Nutricional / Comida';
  $('#nutri-id').value = row?.id || '';
  $('#nutri-alumno').value = row?.alumno_id || '';
  $('#nutri-titulo').value = row?.titulo || '';
  $('#nutri-cal').value = row?.calorias_aprox || 2200;
  $('#nutri-det').value = row?.detalles || '';
  openModal('modal-nutri');
}

async function saveNutri(e) {
  e.preventDefault();
  const data = {
    id: $('#nutri-id').value,
    alumno_id: $('#nutri-alumno').value,
    titulo: $('#nutri-titulo').value,
    calorias_aprox: $('#nutri-cal').value,
    detalles: $('#nutri-det').value
  };
  const r = await api('nutricion.save', data);
  if (r.ok) {
    showToast('Plan nutricional guardado');
    closeModal('modal-nutri');
    loadNutricion();
  } else {
    showToast(r.msg || 'Error', true);
  }
}

/* ===== ALUMNOS ===== */
let _alumnosCache = [];
let _debounceAluTimer;
function debounceLoadAlumnos() {
  clearTimeout(_debounceAluTimer);
  _debounceAluTimer = setTimeout(loadAlumnos, 250);
}

async function loadAlumnos() {
  const q = $('#alu-q')?.value?.trim() || '';
  const plan = $('#alu-plan')?.value || '';
  const estado = $('#alu-estado')?.value || '';
  const prof = $('#alu-prof')?.value || '';

  const { ok, data } = await api('alumnos.list', { q, plan, estado, profesor_id: prof }, 'GET');
  if (!ok) return;
  _alumnosCache = data;

  const tb = $('#tbl-alu tbody') || $('#tbl-coach-alumnos tbody');
  if (!tb) return;
  tb.innerHTML = '';
  if (!data.length) {
    tb.innerHTML = '<tr><td colspan="11" style="text-align:center;color:var(--t-mut);padding:24px">No se encontraron alumnos.</td></tr>';
    return;
  }

  data.forEach(r => {
    const isProximo = r.alerta === 'proximo';
    const badgeCls = r.estado === 'vencido' ? 'b-bad' : (isProximo ? 'b-warn pulse' : (r.estado === 'pausado' ? 'b-warn' : 'b-ok'));
    const badgeTxt = r.estado === 'vencido' ? 'Vencido' : (isProximo ? 'Próximo' : (r.estado === 'pausado' ? 'Pausado' : 'Activo'));
    const saldoBadge = r.saldo_mes > 0 ? `<span class="badge b-warn">Debe $ ${fmtMoney(r.saldo_mes)}</span>` : `<span class="badge b-ok">Al Día</span>`;

    const telClean = (r.telefono || '').replace(/\D/g, '');
    const waLink = telClean ? `<a href="https://wa.me/${telClean}?text=Hola%20${encodeURIComponent(r.nombre)}" target="_blank" style="color:var(--ok);text-decoration:none;margin-left:4px" title="WhatsApp">💬</a>` : '';

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <b style="font-size:14px;color:#fff">${r.nombre}</b>
        ${r.dni ? `<br><small style="color:var(--t2);font-weight:600">DNI: ${r.dni}</small>` : ''}
      </td>
      <td style="white-space:nowrap">${r.telefono || '-'} ${waLink}</td>
      <td><span class="badge b-info" style="font-size:11.5px;padding:4px 8px">${r.plan}</span></td>
      <td><span style="color:var(--t2);font-size:12.5px">${r.actividades || 'Musculación'}</span></td>
      <td style="font-weight:700;white-space:nowrap;color:#60a5fa">$ ${fmtMoney(r.cuota_mes)}</td>
      <td style="color:var(--ok);font-weight:700;white-space:nowrap">$ ${fmtMoney(r.abonado_mes)}</td>
      <td style="white-space:nowrap">${saldoBadge}</td>
      <td style="white-space:nowrap;font-size:13px"><b>${fmtDate(r.fecha_vencimiento)}</b></td>
      <td style="white-space:nowrap"><span class="badge ${badgeCls}">${badgeTxt}</span></td>
      <td>${r.profesor ? `<span class="badge b-purple" style="font-size:11.5px">🏋️ ${r.profesor}</span>` : `<span style="color:var(--t-mut);font-size:12px">General</span>`}</td>
      <td style="text-align:right;white-space:nowrap">
        <div style="display:inline-flex;flex-direction:column;gap:4px;align-items:stretch;min-width:145px">
          <div style="display:flex;gap:4px">
            <button class="btn btn-xs btn-success" style="flex:1" title="Cobrar Cuota" onclick="openPagoModal('alumno', ${r.id})">💵 Cobrar</button>
            <button class="btn btn-xs btn-primary" style="flex:1" title="Rutina" onclick="openRutinaModal({alumno_id:${r.id}, titulo:'Rutina personalizada'})">📋 Rutina</button>
          </div>
          <div style="display:flex;gap:4px">
            <button class="btn btn-xs btn-secondary" style="flex:1" title="Editar Alumno" onclick='openAluModal(${JSON.stringify(r)})'>✏️ Editar</button>
            ${CURRENT_USER.role === 'admin_general' || CURRENT_USER.role === 'dueno' ? `<button class="btn btn-xs btn-danger" style="flex:1" title="Eliminar Alumno" onclick="delAlumno(${r.id}, '${r.nombre}')">🗑️ Borrar</button>` : ''}
          </div>
        </div>
      </td>`;
    tb.appendChild(tr);
  });
}

function setFieldError(fieldId, errId, msg) {
  const inp = $('#' + fieldId);
  const errEl = $('#' + errId);
  if (inp) inp.classList.add('inp-error');
  if (errEl) {
    errEl.innerHTML = `⚠️ ${msg}`;
    errEl.style.display = 'block';
  }
}

function clearFieldError(fieldId, errId) {
  const inp = $('#' + fieldId);
  const errEl = $('#' + errId);
  if (inp) inp.classList.remove('inp-error');
  if (errEl) {
    errEl.innerHTML = '';
    errEl.style.display = 'none';
  }
}

function clearAluErrors() {
  clearFieldError('alu-nombre', 'err-alu-nombre');
  clearFieldError('alu-dni', 'err-alu-dni');
  clearFieldError('alu-telefono', 'err-alu-telefono');
  clearFieldError('alu-plan-inp', 'err-alu-plan');
  clearFieldError('alu-actividades', 'err-alu-actividades');
  clearFieldError('alu-inicio', 'err-alu-inicio');
  clearFieldError('alu-venc', 'err-alu-venc');
  clearFieldError('alu-estado-inp', 'err-alu-estado');
  if ($('#err-alu-prof')) clearFieldError('alu-prof-inp', 'err-alu-prof');
}

function setupAlumnoRealtimeValidation() {
  const nombreInp = $('#alu-nombre');
  if (nombreInp && !nombreInp.dataset.bound) {
    nombreInp.dataset.bound = 'true';
    nombreInp.addEventListener('input', () => {
      const val = nombreInp.value.trim();
      if (!val) {
        setFieldError('alu-nombre', 'err-alu-nombre', 'El nombre y apellido son obligatorios.');
      } else if (/\d/.test(val)) {
        setFieldError('alu-nombre', 'err-alu-nombre', 'El nombre no debe contener caracteres numéricos.');
      } else if (val.length < 3) {
        setFieldError('alu-nombre', 'err-alu-nombre', 'El nombre debe tener al menos 3 caracteres.');
      } else {
        clearFieldError('alu-nombre', 'err-alu-nombre');
      }
    });
  }

  const dniInp = $('#alu-dni');
  if (dniInp && !dniInp.dataset.bound) {
    dniInp.dataset.bound = 'true';
    dniInp.addEventListener('input', () => {
      const val = dniInp.value.trim();
      if (!val) {
        setFieldError('alu-dni', 'err-alu-dni', 'El DNI es obligatorio para evitar registros duplicados.');
      } else if (/[a-zA-Z]/.test(val)) {
        setFieldError('alu-dni', 'err-alu-dni', 'El DNI solo puede contener números, sin letras ni puntos.');
      } else {
        const digits = val.replace(/\D/g, '');
        if (digits.length < 7 || digits.length > 9) {
          setFieldError('alu-dni', 'err-alu-dni', 'El DNI debe contener entre 7 y 9 dígitos numéricos.');
        } else {
          clearFieldError('alu-dni', 'err-alu-dni');
        }
      }
    });
  }

  const telInp = $('#alu-telefono');
  if (telInp && !telInp.dataset.bound) {
    telInp.dataset.bound = 'true';
    telInp.addEventListener('input', () => {
      const val = telInp.value.trim();
      if (val && /[a-zA-Z]/.test(val)) {
        setFieldError('alu-telefono', 'err-alu-telefono', 'El teléfono no puede contener letras. Solo números (ej: 2657506957 o +54 9 2657...).');
      } else if (val) {
        const digits = val.replace(/\D/g, '');
        if (digits.length < 7) {
          setFieldError('alu-telefono', 'err-alu-telefono', 'El teléfono debe contener al menos 7 dígitos numéricos.');
        } else if (digits.length > 15) {
          setFieldError('alu-telefono', 'err-alu-telefono', 'El teléfono no puede superar los 15 dígitos numéricos.');
        } else {
          clearFieldError('alu-telefono', 'err-alu-telefono');
        }
      } else {
        clearFieldError('alu-telefono', 'err-alu-telefono');
      }
    });
  }

  const planInp = $('#alu-plan-inp');
  const inicioInp = $('#alu-inicio');
  const vencInp = $('#alu-venc');

  if (planInp && !planInp.dataset.bound) {
    planInp.dataset.bound = 'true';
    planInp.addEventListener('change', () => {
      clearFieldError('alu-plan-inp', 'err-alu-plan');
      if (inicioInp && inicioInp.value && vencInp) {
        vencInp.value = calcVenc(inicioInp.value, planInp.value);
        clearFieldError('alu-venc', 'err-alu-venc');
      }
    });
  }

  if (inicioInp && !inicioInp.dataset.bound) {
    inicioInp.dataset.bound = 'true';
    inicioInp.addEventListener('change', () => {
      if (!inicioInp.value) {
        setFieldError('alu-inicio', 'err-alu-inicio', 'La fecha de inicio es obligatoria.');
      } else {
        clearFieldError('alu-inicio', 'err-alu-inicio');
        if (planInp && vencInp) {
          vencInp.value = calcVenc(inicioInp.value, planInp.value || '3x');
          clearFieldError('alu-venc', 'err-alu-venc');
        }
      }
    });
  }

  if (vencInp && !vencInp.dataset.bound) {
    vencInp.dataset.bound = 'true';
    vencInp.addEventListener('change', () => {
      if (!vencInp.value) {
        setFieldError('alu-venc', 'err-alu-venc', 'La fecha de vencimiento es obligatoria.');
      } else if (inicioInp && inicioInp.value && vencInp.value < inicioInp.value) {
        setFieldError('alu-venc', 'err-alu-venc', 'La fecha de vencimiento no puede ser anterior a la fecha de inicio.');
      } else {
        clearFieldError('alu-venc', 'err-alu-venc');
      }
    });
  }
}

function openAluModal(row = null) {
  clearAluErrors();
  setupAlumnoRealtimeValidation();
  $('#alu-modal-title').textContent = row ? 'Editar Alumno' : 'Registrar Nuevo Alumno';
  $('#alu-id').value = row?.id || '';
  $('#alu-nombre').value = row?.nombre || '';
  $('#alu-dni').value = row?.dni || '';
  $('#alu-telefono').value = row?.telefono || '';
  $('#alu-plan-inp').value = row?.plan || '3x';
  $('#alu-actividades').value = row?.actividades || 'Musculación, Funcional';
  $('#alu-inicio').value = row?.fecha_inicio || currentDate();
  $('#alu-venc').value = row?.fecha_vencimiento || calcVenc(row?.fecha_inicio || currentDate(), row?.plan || '3x');
  $('#alu-estado-inp').value = row?.estado || 'activo';
  if ($('#alu-prof-inp')) $('#alu-prof-inp').value = row?.profesor_id || '';
  openModal('modal-alu');
}

async function saveAlumno(e) {
  e.preventDefault();
  clearAluErrors();

  const nombreVal = ($('#alu-nombre').value || '').trim();
  const dniVal = ($('#alu-dni').value || '').trim();
  const telVal = ($('#alu-telefono').value || '').trim();
  const planVal = $('#alu-plan-inp').value;
  const actVal = ($('#alu-actividades').value || '').trim();
  const iniVal = $('#alu-inicio').value;
  const vencVal = $('#alu-venc').value;
  const estVal = $('#alu-estado-inp').value;
  const profVal = $('#alu-prof-inp')?.value || '';

  let hasError = false;
  let firstErrEl = null;

  // 1. Validar Nombre
  if (!nombreVal) {
    setFieldError('alu-nombre', 'err-alu-nombre', 'El nombre y apellido son obligatorios.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-nombre');
  } else if (nombreVal.length < 3) {
    setFieldError('alu-nombre', 'err-alu-nombre', 'El nombre debe tener al menos 3 caracteres.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-nombre');
  } else if (/\d/.test(nombreVal)) {
    setFieldError('alu-nombre', 'err-alu-nombre', 'El nombre no debe contener caracteres numéricos.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-nombre');
  }

  // 2. Validar DNI
  if (!dniVal) {
    setFieldError('alu-dni', 'err-alu-dni', 'El DNI es obligatorio para evitar registros duplicados.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-dni');
  } else if (/[a-zA-Z]/.test(dniVal)) {
    setFieldError('alu-dni', 'err-alu-dni', 'El DNI solo puede contener números, sin letras ni puntos.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-dni');
  } else {
    const cleanDni = dniVal.replace(/\D/g, '');
    if (cleanDni.length < 7 || cleanDni.length > 9) {
      setFieldError('alu-dni', 'err-alu-dni', 'El DNI debe contener entre 7 y 9 dígitos numéricos.');
      hasError = true;
      if (!firstErrEl) firstErrEl = $('#alu-dni');
    }
  }

  // 3. Validar Teléfono
  if (telVal) {
    if (/[a-zA-Z]/.test(telVal)) {
      setFieldError('alu-telefono', 'err-alu-telefono', 'El teléfono no puede contener letras. Solo números (ej: 2657506957 o +54 9 2657...).');
      hasError = true;
      if (!firstErrEl) firstErrEl = $('#alu-telefono');
    } else {
      const digits = telVal.replace(/\D/g, '');
      if (digits.length < 7) {
        setFieldError('alu-telefono', 'err-alu-telefono', 'El teléfono debe contener al menos 7 dígitos numéricos.');
        hasError = true;
        if (!firstErrEl) firstErrEl = $('#alu-telefono');
      } else if (digits.length > 15) {
        setFieldError('alu-telefono', 'err-alu-telefono', 'El teléfono no puede superar los 15 dígitos numéricos.');
        hasError = true;
        if (!firstErrEl) firstErrEl = $('#alu-telefono');
      }
    }
  }

  // 4. Validar Plan
  if (!planVal) {
    setFieldError('alu-plan-inp', 'err-alu-plan', 'Seleccioná un plan válido.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-plan-inp');
  }

  // 5. Validar Fechas
  if (!iniVal) {
    setFieldError('alu-inicio', 'err-alu-inicio', 'La fecha de inicio es requerida.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-inicio');
  }
  if (!vencVal) {
    setFieldError('alu-venc', 'err-alu-venc', 'La fecha de vencimiento es requerida.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-venc');
  } else if (iniVal && vencVal < iniVal) {
    setFieldError('alu-venc', 'err-alu-venc', 'La fecha de vencimiento no puede ser anterior a la fecha de inicio.');
    hasError = true;
    if (!firstErrEl) firstErrEl = $('#alu-venc');
  }

  if (hasError) {
    if (firstErrEl) firstErrEl.focus();
    return;
  }

  const data = {
    id: $('#alu-id').value,
    nombre: nombreVal,
    dni: dniVal.replace(/\D/g, ''),
    telefono: telVal,
    plan: planVal,
    actividades: actVal,
    fecha_inicio: iniVal,
    fecha_vencimiento: vencVal,
    estado: estVal,
    profesor_id: profVal
  };

  const r = await api('alumnos.save', data);
  if (r.ok) {
    showToast('Alumno guardado exitosamente');
    closeModal('modal-alu');
    await loadAlumnos();
    loadAlumnosOptions();
    await loadDashboard();
  } else {
    const errorMsg = r.msg || 'Error al guardar alumno';
    showToast(errorMsg, true);
    if (errorMsg.includes('DNI')) {
      setFieldError('alu-dni', 'err-alu-dni', errorMsg);
      $('#alu-dni').focus();
    } else if (errorMsg.includes('nombre')) {
      setFieldError('alu-nombre', 'err-alu-nombre', errorMsg);
      $('#alu-nombre').focus();
    }
  }
}

async function delAlumno(id, nombre) {
  const ok = await systemConfirm({
    title: '¿Eliminar Alumno?',
    message: `¿Estás seguro de que deseas eliminar permanentemente al alumno <b>${nombre}</b>? Se cancelarán sus registros y cuotas asociadas.`,
    confirmText: 'Sí, Eliminar',
    cancelText: 'Cancelar',
    icon: '🗑️',
    isDanger: true
  });
  if (!ok) return;
  const r = await api('alumnos.delete', { id });
  if (r.ok) { 
    showToast('Alumno eliminado con éxito'); 
    loadAlumnos(); 
    loadDashboard(); 
  } else {
    showToast(r.msg || 'Error al eliminar alumno', true);
  }
}

/* ===== PROFESORES ===== */
let _profesCache = [];
let _debounceProfTimer;
function debounceLoadProfesores() {
  clearTimeout(_debounceProfTimer);
  _debounceProfTimer = setTimeout(loadProfesores, 250);
}

async function loadProfesores() {
  const q = $('#prof-filter-q')?.value?.trim() || '';
  const res = await api('profesores.list', { q }, 'GET');
  if (res.ok) {
    _profesCache = res.data || [];
    renderProfesoresTable();
  }
}

function renderProfesoresTable() {
  const q = ($('#prof-filter-q')?.value || '').toLowerCase().trim();
  const estadoFiltro = $('#prof-filter-estado')?.value || '';

  const tb = $('#tbl-prof tbody');
  if (!tb) return;
  tb.innerHTML = '';

  let profList = _profesCache || [];

  if (estadoFiltro === 'al_dia') profList = profList.filter(p => p.saldo_mes <= 0);
  if (estadoFiltro === 'deuda') profList = profList.filter(p => p.saldo_mes > 0);
  if (q) profList = profList.filter(p => p.nombre.toLowerCase().includes(q) || (p.telefono && p.telefono.includes(q)));

  if (!profList.length) {
    tb.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--t-mut);padding:28px">No se encontraron profesores o coaches registrados.</td></tr>';
    return;
  }

  profList.forEach(p => {
    const telClean = (p.telefono || '').replace(/\D/g, '');
    const waLink = telClean ? `<a href="https://wa.me/${telClean}?text=Hola%20${encodeURIComponent(p.nombre)}" target="_blank" style="color:var(--ok);text-decoration:none;margin-left:4px" title="WhatsApp">💬</a>` : '';

    const abonadoMes = parseFloat(p.abonado_mes || p.pagado_mes || 0);
    const cuotaMensual = parseFloat(p.cuota_mensual || 0);
    const saldoPendiente = parseFloat(p.saldo_mes ?? Math.max(0, cuotaMensual - abonadoMes));
    const isAlDia = (abonadoMes >= cuotaMensual && cuotaMensual > 0);

    const badgeEstado = isAlDia 
      ? '<span class="badge b-ok">🟢 Liquidado (Al Día)</span>'
      : `<span class="badge b-warn">🟠 Pendiente ($ ${fmtMoney(saldoPendiente)})</span>`;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><b style="font-size:14px;color:#fff">${p.nombre}</b></td>
      <td style="white-space:nowrap">${p.telefono || '-'} ${waLink}</td>
      <td style="font-weight:700;color:#60a5fa;white-space:nowrap">$ ${fmtMoney(cuotaMensual)} <small style="color:var(--t2)">/mes</small></td>
      <td style="color:var(--ok);font-weight:700;white-space:nowrap">$ ${fmtMoney(abonadoMes)}</td>
      <td style="white-space:nowrap">${badgeEstado}</td>
      <td style="white-space:nowrap"><span class="badge b-purple" style="font-size:11.5px;padding:4px 8px">👥 ${p.total_alumnos || 0} socios</span></td>
      <td><span style="color:var(--t2);font-size:12.5px">${p.observaciones || '-'}</span></td>
      <td style="text-align:right;white-space:nowrap">
        <div style="display:inline-flex;flex-direction:column;gap:4px;align-items:stretch;min-width:140px">
          <button class="btn btn-xs ${isAlDia ? 'btn-secondary' : 'btn-primary'}" style="width:100%;font-weight:700" title="Liquidar Honorarios al Coach" onclick="openPagoModal('profesor', ${p.id})">💵 Liquidar / Pagar</button>
          <div style="display:flex;gap:4px">
            <button class="btn btn-xs btn-secondary" style="flex:1" title="Editar Coach" onclick='openProfModal(${JSON.stringify(p)})'>✏️ Editar</button>
            <button class="btn btn-xs btn-danger" style="flex:1" title="Eliminar Coach" onclick="delProfesor(${p.id}, '${p.nombre}')">🗑️ Borrar</button>
          </div>
        </div>
      </td>
    `;
    tb.appendChild(tr);
  });
}

function openProfModal(row = null) {
  $('#prof-modal-title').textContent = row ? 'Editar Coach' : 'Registrar Coach / Profesor';
  $('#prof-id').value = row?.id || '';
  $('#prof-nombre').value = row?.nombre || '';
  $('#prof-telefono').value = row?.telefono || '';
  $('#prof-cuota').value = row?.cuota_mensual || 0;
  $('#prof-obs').value = row?.observaciones || '';
  openModal('modal-prof');
}

async function saveProfesor(e) {
  e.preventDefault();
  const data = {
    id: $('#prof-id').value,
    nombre: $('#prof-nombre').value,
    telefono: $('#prof-telefono').value,
    cuota_mensual: $('#prof-cuota').value,
    observaciones: $('#prof-obs').value
  };
  const r = await api('profesores.save', data);
  if (r.ok) {
    showToast('Coach guardado exitosamente');
    closeModal('modal-prof');
    await loadProfesores();
    loadProfesOptions();
    await loadDashboard();
  } else {
    showToast(r.msg || 'Error al guardar coach', true);
  }
}

async function delProfesor(id, nombre) {
  const ok = await systemConfirm({
    title: '¿Eliminar Coach?',
    message: `¿Estás seguro de que deseas eliminar al coach <b>${nombre}</b>? Sus alumnos asignados permanecerán en el sistema.`,
    confirmText: 'Sí, Eliminar',
    cancelText: 'Cancelar',
    icon: '🗑️',
    isDanger: true
  });
  if (!ok) return;

  const r = await api('profesores.delete', { id });
  if (r.ok) {
    showToast('Coach eliminado correctamente');
    await loadProfesores();
    loadProfesOptions();
    await loadDashboard();
  } else {
    showToast(r.msg || 'Error al eliminar coach', true);
  }
}

/* ===== PAGOS Y CAJA ===== */
let _debouncePagosTimer;
function debounceLoadPagos() {
  clearTimeout(_debouncePagosTimer);
  _debouncePagosTimer = setTimeout(loadPagos, 250);
}

function resetPagosFiltros() {
  if ($('#pagos-filter-q')) $('#pagos-filter-q').value = '';
  if ($('#pagos-filter-tipo')) $('#pagos-filter-tipo').value = '';
  if ($('#pagos-filter-medio')) $('#pagos-filter-medio').value = '';
  if ($('#pagos-filter-mes')) $('#pagos-filter-mes').value = '';
  loadPagos();
}

async function loadPagos() {
  const q = $('#pagos-filter-q')?.value?.trim() || '';
  const tipo = $('#pagos-filter-tipo')?.value || '';
  const medio = $('#pagos-filter-medio')?.value || '';
  const mes = $('#pagos-filter-mes')?.value || '';

  const { ok, data } = await api('pagos.list', { q, tipo, medio, mes }, 'GET');
  if (!ok) return;
  const tb = $('#tbl-pagos tbody') || $('#tbl-coach-pagos tbody');
  if (!tb) return;
  tb.innerHTML = '';

  let totalFiltrado = 0;

  if (!data || !data.length) {
    tb.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--t-mut);padding:24px">No se encontraron pagos con los filtros aplicados.</td></tr>';
  } else {
    data.forEach(p => {
      totalFiltrado += parseFloat(p.monto || 0);
      const isAlu = p.tipo === 'alumno';
      const titular = isAlu ? (p.alumno || 'Alumno') : (p.profesor || 'Coach');
      const badgeTipo = isAlu ? '<span class="badge b-info" style="font-weight:800">👤 ALUMNO</span>' : '<span class="badge b-purple" style="font-weight:800">🏋️ COACH</span>';

      let badgeMedio = 'b-ok';
      if (p.medio_pago === 'transferencia') badgeMedio = 'b-info';
      else if (p.medio_pago === 'debito' || p.medio_pago === 'credito') badgeMedio = 'b-warn';

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><b>${fmtDate(p.fecha_pago)}</b></td>
        <td>${badgeTipo}</td>
        <td><strong style="color:#fff">${titular}</strong></td>
        <td><span class="badge ${isAlu ? 'b-info' : 'b-purple'}">${p.plan || (isAlu ? 'Cuota' : 'Pago Mensual')}</span></td>
        <td><span class="badge ${badgeMedio}">${p.medio_pago ? p.medio_pago.toUpperCase() : 'EFECTIVO'}</span></td>
        <td style="text-align:right;font-weight:800;color:var(--ok);font-size:14px">$ ${fmtMoney(p.monto)}</td>
        <td style="color:var(--t-mut);font-size:12px">${p.observaciones || '-'}</td>
      `;
      tb.appendChild(tr);
    });
  }

  if ($('#pagos-count-txt')) $('#pagos-count-txt').textContent = data ? data.length : 0;
  if ($('#pagos-total-txt')) $('#pagos-total-txt').textContent = fmtMoney(totalFiltrado);
}

async function openPagoModal(tipo = 'alumno', id = null) {
  if (tipo === 'alumno' && (!_alumnosCache || !_alumnosCache.length)) {
    await loadAlumnosOptions();
  } else if (tipo === 'profesor' && (!_profesCache || !_profesCache.length)) {
    await loadProfesOptions();
  }

  $('#pago-tipo').value = tipo;
  onPagoTipoChange();
  const btnSubmit = $('#modal-pago button.btn-success');
  if (btnSubmit) btnSubmit.disabled = false;

  if (tipo === 'alumno') {
    $('#pago-alumno').value = id || '';
    onPagoAlumnoSelect();
  } else if (tipo === 'profesor' && $('#pago-profesor')) {
    $('#pago-profesor').value = id || '';
    onPagoProfesorSelect();
  }
  openModal('modal-pago');
}

function onPagoTipoChange() {
  const isAlu = $('#pago-tipo').value === 'alumno';
  $('#group-pago-alumno').style.display = isAlu ? 'block' : 'none';
  if ($('#group-pago-profesor')) $('#group-pago-profesor').style.display = isAlu ? 'none' : 'block';
  
  if ($('#modal-pago-title')) {
    $('#modal-pago-title').textContent = isAlu ? '💵 Cobrar Cuota a Alumno / Socio' : '💵 Liquidar / Registrar Pago a Coach';
  }
  if ($('#lbl-pago-monto')) {
    $('#lbl-pago-monto').textContent = isAlu ? 'Monto Exacto a Cobrar ($) *' : 'Monto de Honorario a Liquidar ($) *';
  }
  if ($('#lbl-pago-summary-cuota')) {
    $('#lbl-pago-summary-cuota').textContent = isAlu ? 'Cuota Pactada:' : 'Honorario Acordado:';
  }
  if ($('#lbl-pago-summary-saldo')) {
    $('#lbl-pago-summary-saldo').textContent = isAlu ? 'Saldo Exacto a Cobrar:' : 'Saldo a Liquidar:';
  }

  const btnSubmit = $('#btn-pago-submit') || $('#modal-pago button.btn-success');
  if (btnSubmit) {
    btnSubmit.textContent = isAlu ? 'Confirmar Cobro de Cuota' : 'Confirmar Pago al Coach';
    btnSubmit.disabled = false;
  }
  
  if (isAlu) {
    onPagoAlumnoSelect();
  } else {
    onPagoProfesorSelect();
  }
}

function onPagoAlumnoSelect() {
  const id = $('#pago-alumno')?.value;
  const sBox = $('#pago-summary-box');
  const btnSubmit = $('#btn-pago-submit') || $('#modal-pago button.btn-success');

  if (!id) {
    if (sBox) sBox.style.display = 'none';
    $('#pago-monto').value = '';
    if ($('#pago-monto-hint')) $('#pago-monto-hint').textContent = 'Solo se permite cobrar el importe pactado exacto (ni más ni menos).';
    if (btnSubmit) btnSubmit.disabled = false;
    return;
  }

  const alu = (_alumnosCache || []).find(a => String(a.id) === String(id));
  if (alu) {
    const cuota = parseFloat(alu.cuota_mes || 0);
    const abonado = parseFloat(alu.abonado_mes || 0);
    const saldo = Math.max(0, cuota - abonado);
    const isAlDia = (abonado >= cuota && alu.estado !== 'vencido');

    if (sBox) {
      sBox.style.display = 'block';
      $('#pago-summary-plan').textContent = `👤 Socio: ${alu.nombre} • Plan ${(alu.plan || '3x').toUpperCase()}`;
      $('#pago-summary-cuota').textContent = `$ ${fmtMoney(cuota)}`;
      $('#pago-summary-abonado').textContent = `$ ${fmtMoney(abonado)}`;
      $('#pago-summary-saldo').textContent = `$ ${fmtMoney(saldo)}`;

      const badge = $('#pago-summary-badge');
      if (badge) {
        if (isAlDia) {
          badge.className = 'badge b-ok';
          badge.textContent = '🟢 AL DÍA (TOTALMENTE PAGADO)';
        } else if (saldo < cuota && abonado > 0) {
          badge.className = 'badge b-warn';
          badge.textContent = `⚠️ PAGO PARCIAL (DEBE $ ${fmtMoney(saldo)})`;
        } else {
          badge.className = 'badge b-bad';
          badge.textContent = '🔴 CUOTA PENDIENTE';
        }
      }
    }

    if (isAlDia) {
      $('#pago-monto').value = '0.00';
      if ($('#pago-monto-hint')) {
        $('#pago-monto-hint').innerHTML = '<span style="color:#10b981;font-weight:700">✅ Este alumno ya abonó el 100% de su cuota de este mes. No se permite cobrar de más.</span>';
      }
      if (btnSubmit) btnSubmit.disabled = true;
    } else {
      $('#pago-monto').value = saldo.toFixed(2);
      if ($('#pago-monto-hint')) {
        $('#pago-monto-hint').innerHTML = `<span style="color:#38bdf8;font-weight:700">🔒 Importe fijado en $ ${fmtMoney(saldo)} (Monto pactado exacto para quedar al día).</span>';
      }
      if (btnSubmit) btnSubmit.disabled = false;
    }
  }
}

function onPagoProfesorSelect() {
  const id = $('#pago-profesor')?.value;
  const sBox = $('#pago-summary-box');
  const btnSubmit = $('#btn-pago-submit') || $('#modal-pago button.btn-success');

  if (!id) {
    if (sBox) sBox.style.display = 'none';
    $('#pago-monto').value = '';
    if ($('#pago-monto-hint')) $('#pago-monto-hint').textContent = 'Solo se permite liquidar el honorario mensual pactado exacto.';
    if (btnSubmit) btnSubmit.disabled = false;
    return;
  }

  const prof = (_profesCache || []).find(p => String(p.id) === String(id));
  if (prof) {
    const cuota = parseFloat(prof.cuota_mensual || 0);
    const pagado = parseFloat(prof.abonado_mes || prof.pagado_mes || 0);
    const saldo = parseFloat(prof.saldo_mes ?? Math.max(0, cuota - pagado));
    const isAlDia = (pagado >= cuota && cuota > 0);

    if (sBox) {
      sBox.style.display = 'block';
      $('#pago-summary-plan').textContent = `🏋️ Coach: ${prof.nombre}`;
      $('#pago-summary-cuota').textContent = `$ ${fmtMoney(cuota)}`;
      $('#pago-summary-abonado').textContent = `$ ${fmtMoney(pagado)}`;
      $('#pago-summary-saldo').textContent = `$ ${fmtMoney(saldo)}`;

      const badge = $('#pago-summary-badge');
      if (badge) {
        if (isAlDia) {
          badge.className = 'badge b-ok';
          badge.textContent = '🟢 HONORARIOS LIQUIDADOS (AL DÍA)';
        } else {
          badge.className = 'badge b-warn';
          badge.textContent = `🟠 PAGO PENDIENTE ($ ${fmtMoney(saldo)})`;
        }
      }
    }

    if (isAlDia) {
      $('#pago-monto').value = '0.00';
      if ($('#pago-monto-hint')) {
        $('#pago-monto-hint').innerHTML = '<span style="color:#10b981;font-weight:700">✅ Este coach ya cobró el total de sus honorarios de este mes.</span>';
      }
      if (btnSubmit) btnSubmit.disabled = true;
    } else {
      const montoFijado = saldo > 0 ? saldo : cuota;
      $('#pago-monto').value = montoFijado.toFixed(2);
      if ($('#pago-monto-hint')) {
        $('#pago-monto-hint').innerHTML = `<span style="color:#a855f7;font-weight:700">🔒 Importe fijado en $ ${fmtMoney(montoFijado)} (Honorario mensual pactado).</span>`;
      }
      if (btnSubmit) btnSubmit.disabled = false;
    }
  }
}

async function savePago(e) {
  e.preventDefault();
  const data = {
    tipo: $('#pago-tipo').value,
    alumno_id: $('#pago-alumno').value,
    profesor_id: $('#pago-profesor')?.value || '',
    monto: $('#pago-monto').value,
    fecha_pago: $('#pago-fecha').value,
    medio_pago: $('#pago-medio').value,
    observaciones: $('#pago-obs').value
  };
  const r = await api('pagos.save', data);
  if (r.ok) {
    showToast('Pago registrado correctamente');
    closeModal('modal-pago');
    loadPagos();
    loadDashboard();
    if (CURRENT_USER.role !== 'alumno') loadAlumnos();
    loadAlumnosOptions();
    if (typeof loadProfesores === 'function') loadProfesores();
    if (typeof loadProfesOptions === 'function') loadProfesOptions();
  } else {
    showToast(r.msg || 'Error', true);
  }
}

/* ===== USUARIOS & ROLES ===== */
async function loadUsuarios() {
  const { ok, data } = await api('usuarios.list', {}, 'GET');
  if (!ok) return;
  const tb = $('#tbl-usuarios tbody');
  if (!tb) return;
  tb.innerHTML = '';
  data.forEach(u => {
    const rolBadge = u.rol === 'admin_general' ? 'b-purple' : (u.rol === 'dueno' ? 'b-info' : (u.rol === 'coach' ? 'b-ok' : 'b-warn'));
    const vinculo = u.rol === 'dueno' ? `🏢 ${u.gimnasio_nombre || 'Sede'}` : (u.rol === 'coach' ? `🏋️ ${u.profesor_nombre || 'Coach'}` : (u.rol === 'alumno' ? `👤 ${u.alumno_nombre || 'Socio'}` : 'SuperAdmin'));
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><b>${u.nombre_usuario}</b></td>
      <td>${u.email}</td>
      <td><span class="badge ${rolBadge}">${u.rol.toUpperCase()}</span></td>
      <td>${vinculo}</td>
      <td>${u.gimnasio_nombre || '-'}</td>
      <td><span class="badge ${u.activo == 1 ? 'b-ok' : 'b-bad'}">${u.activo == 1 ? 'Activo' : 'Inactivo'}</span></td>
      <td style="text-align:right"><button class="btn btn-sm btn-secondary" onclick='openUserModal(${JSON.stringify(u)})'>✏️</button></td>
    `;
    tb.appendChild(tr);
  });
}

function openUserModal(row = null) {
  $('#user-id').value = row?.id || '';
  $('#user-nombre').value = row?.nombre_usuario || '';
  $('#user-email').value = row?.email || '';
  $('#user-rol').value = row?.rol || 'alumno';
  $('#user-activo').value = row?.activo ?? 1;
  $('#user-password').value = '';
  openModal('modal-usuario');
}

async function saveUsuario(e) {
  e.preventDefault();
  const data = {
    id: $('#user-id').value,
    nombre_usuario: $('#user-nombre').value,
    email: $('#user-email').value,
    rol: $('#user-rol').value,
    activo: $('#user-activo').value,
    password: $('#user-password').value
  };
  const r = await api('usuarios.save', data);
  if (r.ok) {
    showToast('Usuario guardado');
    closeModal('modal-usuario');
    loadUsuarios();
  } else {
    showToast(r.msg || 'Error', true);
  }
}

/* ===== CONFIGURACIÓN ===== */
async function loadConfig() {
  const { ok, data } = await api('config.get', {}, 'GET');
  if (!ok) return;
  const map = {}; data.forEach(x => { map[x.plan] = x.precio; });
  $('#cfg-3x').value = map['3x'] ?? 0;
  $('#cfg-full').value = map['full'] ?? 0;
  $('#cfg-clase').value = map['clase'] ?? 0;
}

async function saveConfig(e) {
  e.preventDefault();
  const data = { p3x: $('#cfg-3x').value, pfull: $('#cfg-full').value, pclase: $('#cfg-clase').value };
  const r = await api('config.save', data);
  if (r.ok) { showToast('Precios guardados'); loadAlumnos(); }
  else showToast(r.msg || 'Error', true);
  return false;
}

async function loadGymData() {
  const { ok, data } = await api('gym.get', {}, 'GET');
  if (!ok || !data) return;
  if ($('#cfg-gym-nombre')) $('#cfg-gym-nombre').value = data.nombre || 'Gimnasio';
  if ($('#cfg-gym-code')) $('#cfg-gym-code').value = data.invite_code || '';
  if ($('#cfg-gym-tel')) $('#cfg-gym-tel').value = data.telefono || '';
  if ($('#cfg-gym-dir')) $('#cfg-gym-dir').value = data.direccion || '';
  if (CURRENT_USER.role === 'dueno' && data.nombre && $('#user-role-text')) {
    $('#user-role-text').textContent = data.nombre;
  }
}

async function saveGymData(e) {
  e.preventDefault();
  const data = {
    nombre: $('#cfg-gym-nombre')?.value || '',
    invite_code: $('#cfg-gym-code')?.value || '',
    telefono: $('#cfg-gym-tel')?.value || '',
    direccion: $('#cfg-gym-dir')?.value || ''
  };
  const r = await api('gym.save', data);
  if (r.ok) {
    showToast('Datos de sede guardados');
    if (CURRENT_USER.role === 'dueno' && data.nombre && $('#user-role-text')) {
      $('#user-role-text').textContent = data.nombre;
    }
  } else {
    showToast(r.msg || 'Error', true);
  }
}

/* ===== OPTIONS POPULATORS ===== */
async function loadAlumnosOptions() {
  const { ok, data } = await api('alumnos.list', { q: '' }, 'GET');
  if (!ok) return;
  _alumnosCache = data;
  ['#rutina-alumno', '#nutri-alumno', '#pago-alumno'].forEach(selStr => {
    const s = $(selStr);
    if (!s) return;
    s.innerHTML = '<option value="">(Seleccionar Alumno)</option>';
    data.forEach(a => {
      const opt = document.createElement('option');
      opt.value = a.id;
      opt.textContent = `${a.nombre}${a.dni ? ' (DNI: ' + a.dni + ')' : ''} - ${a.plan} ($ ${fmtMoney(a.cuota_mes)})`;
      s.appendChild(opt);
    });
  });
}

async function loadProfesOptions() {
  const { ok, data } = await api('profesores.list', { q: '' }, 'GET');
  if (!ok) return;
  _profesCache = data;

  // 1. Selector de Coach en Formulario y Filtro de Alumnos: SOLO MOSTRAR EL NOMBRE (Sin precio ni sueldo privado)
  ['#alu-prof', '#alu-prof-inp'].forEach(selStr => {
    const s = $(selStr);
    if (!s) return;
    s.innerHTML = '<option value="">(Sin coach asignado / General)</option>';
    data.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `${p.nombre}`;
      s.appendChild(opt);
    });
  });

  // 2. Selector de Pago a Coach (Liquidación de honorarios)
  const selPago = $('#pago-profesor');
  if (selPago) {
    selPago.innerHTML = '<option value="">(Seleccionar Coach)</option>';
    data.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `${p.nombre}`;
      selPago.appendChild(opt);
    });
  }
}

/* ===== CANVAS CHARTS (SEMANAL BARRAS, MENSUAL LÍNEAS, ANUAL TORTA) ===== */

// 1. Gráfica de Dona para Dashboard (Cumplimiento)
function drawDonut(canvas, items, centerTitle = '', centerSub = '') {
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width = (canvas.clientWidth || 175);
  const h = canvas.height = (canvas.clientHeight || 175);
  ctx.clearRect(0, 0, w, h);

  const rawTot = items.reduce((a, b) => a + (Number(b.value) || 0), 0);
  const tot = rawTot || 1;
  const cx = w / 2, cy = h / 2, r = Math.min(w, h) / 2 - 8, ir = r * 0.68;
  let start = -Math.PI / 2;

  if (rawTot === 0) {
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, 2 * Math.PI); ctx.fillStyle = 'rgba(255, 255, 255, 0.06)'; ctx.fill();
    ctx.globalCompositeOperation = 'destination-out';
    ctx.beginPath(); ctx.arc(cx, cy, ir, 0, 2 * Math.PI); ctx.fill();
    ctx.globalCompositeOperation = 'source-over';
    ctx.fillStyle = '#64748b'; ctx.font = '700 13px system-ui'; ctx.textAlign = 'center';
    ctx.fillText('0', cx, cy + 4);
    return;
  }

  items.forEach(it => {
    const val = Number(it.value) || 0;
    if (val <= 0) return;
    const slice = (val / tot) * 2 * Math.PI;
    const end = start + slice;
    ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, r, start, end); ctx.closePath();
    ctx.fillStyle = it.color || '#3b82f6'; ctx.fill();
    start = end;
  });

  ctx.globalCompositeOperation = 'destination-out';
  ctx.beginPath(); ctx.arc(cx, cy, ir, 0, 2 * Math.PI); ctx.fill();
  ctx.globalCompositeOperation = 'source-over';

  const mainTxt = centerTitle || rawTot.toLocaleString('es-AR');
  ctx.fillStyle = '#ffffff'; 
  ctx.font = '800 22px system-ui'; 
  ctx.textAlign = 'center';
  ctx.fillText(mainTxt, cx, cy + (centerSub ? -2 : 7));

  if (centerSub) {
    ctx.fillStyle = '#94a3b8';
    ctx.font = '700 9.5px system-ui';
    ctx.fillText(centerSub.toUpperCase(), cx, cy + 14);
  }
}

// 2. Gráfica Semanal de BARRAS (Lun a Dom)
function drawWeeklyBarChart(canvas, data) {
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width = canvas.clientWidth || 400, h = canvas.height = canvas.clientHeight || 280;
  ctx.clearRect(0, 0, w, h);
  const pts = data?.serie || [];
  if (!pts.length) return;

  const maxVal = Math.max(...pts.map(p => Number(p.monto) || 0), 100);
  const padBottom = 40, padTop = 35, padX = 30;
  const chartHeight = h - padBottom - padTop;
  const chartWidth = w - 2 * padX;
  const colStep = chartWidth / pts.length;
  const barWidth = Math.max(colStep * 0.52, 14);

  // Líneas guía horizontales
  ctx.strokeStyle = 'rgba(255, 255, 255, 0.05)';
  ctx.lineWidth = 1;
  for (let i = 0; i <= 4; i++) {
    const yLine = padTop + (chartHeight / 4) * i;
    ctx.beginPath(); ctx.moveTo(padX, yLine); ctx.lineTo(w - padX, yLine); ctx.stroke();
  }

  pts.forEach((p, i) => {
    const val = Number(p.monto) || 0;
    const bh = (val / maxVal) * chartHeight;
    const x = padX + i * colStep + (colStep - barWidth) / 2;
    const y = h - padBottom - bh;

    // Barra con Gradiente Azul/Violeta
    const grad = ctx.createLinearGradient(0, y, 0, h - padBottom);
    if (val > 0) {
      grad.addColorStop(0, '#3b82f6');
      grad.addColorStop(1, '#1e3a8a');
    } else {
      grad.addColorStop(0, 'rgba(255, 255, 255, 0.08)');
      grad.addColorStop(1, 'rgba(255, 255, 255, 0.02)');
    }

    ctx.fillStyle = grad;
    ctx.beginPath();
    ctx.roundRect(x, Math.min(y, h - padBottom - 4), barWidth, Math.max(bh, 4), [6, 6, 0, 0]);
    ctx.fill();

    // Monto arriba de la barra
    if (val > 0) {
      ctx.fillStyle = '#60a5fa';
      ctx.font = '700 11px system-ui';
      ctx.textAlign = 'center';
      ctx.fillText(`$${(val >= 1000 ? (val/1000).toFixed(0) + 'k' : val)}`, x + barWidth / 2, y - 8);
    }

    // Día y Fecha abajo
    ctx.fillStyle = '#f8fafc';
    ctx.font = '700 12px system-ui';
    ctx.textAlign = 'center';
    ctx.fillText(p.label, x + barWidth / 2, h - padBottom + 16);

    ctx.fillStyle = '#64748b';
    ctx.font = '10px system-ui';
    ctx.fillText(p.sublabel, x + barWidth / 2, h - padBottom + 28);
  });
}

// 3. Gráfica Mensual de LÍNEAS (Progreso y Evolución)
function drawMonthlyLineChart(canvas, data) {
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width = canvas.clientWidth || 400, h = canvas.height = canvas.clientHeight || 280;
  ctx.clearRect(0, 0, w, h);
  const pts = data?.serie || [];
  if (!pts.length) return;

  const maxVal = Math.max(...pts.map(p => Number(p.monto) || 0), 100);
  const padBottom = 35, padTop = 35, padX = 40;
  const chartHeight = h - padBottom - padTop;
  const chartWidth = w - 2 * padX;
  const colStep = chartWidth / Math.max(1, pts.length - 1);

  // Líneas guía horizontales
  ctx.strokeStyle = 'rgba(255, 255, 255, 0.05)';
  ctx.lineWidth = 1;
  for (let i = 0; i <= 4; i++) {
    const yLine = padTop + (chartHeight / 4) * i;
    ctx.beginPath(); ctx.moveTo(padX, yLine); ctx.lineTo(w - padX, yLine); ctx.stroke();
  }

  // Coordenadas calculadas
  const coords = pts.map((p, i) => {
    const val = Number(p.monto) || 0;
    const x = padX + i * colStep;
    const y = h - padBottom - (val / maxVal) * chartHeight;
    return { x, y, val, label: p.label };
  });

  // Área sombreada bajo la curva
  const areaGrad = ctx.createLinearGradient(0, padTop, 0, h - padBottom);
  areaGrad.addColorStop(0, 'rgba(6, 182, 212, 0.35)');
  areaGrad.addColorStop(1, 'rgba(6, 182, 212, 0.0)');

  ctx.beginPath();
  ctx.moveTo(coords[0].x, h - padBottom);
  coords.forEach((c, i) => {
    if (i === 0) ctx.lineTo(c.x, c.y);
    else {
      const prev = coords[i - 1];
      const cx = (prev.x + c.x) / 2;
      ctx.bezierCurveTo(cx, prev.y, cx, c.y, c.x, c.y);
    }
  });
  ctx.lineTo(coords[coords.length - 1].x, h - padBottom);
  ctx.closePath();
  ctx.fillStyle = areaGrad;
  ctx.fill();

  // Línea continua de progreso
  ctx.beginPath();
  coords.forEach((c, i) => {
    if (i === 0) ctx.moveTo(c.x, c.y);
    else {
      const prev = coords[i - 1];
      const cx = (prev.x + c.x) / 2;
      ctx.bezierCurveTo(cx, prev.y, cx, c.y, c.x, c.y);
    }
  });
  ctx.strokeStyle = '#06b6d4';
  ctx.lineWidth = 3.5;
  ctx.lineCap = 'round';
  ctx.stroke();

  // Puntos interactivos y valores
  coords.forEach(c => {
    // Círculo exterior
    ctx.beginPath();
    ctx.arc(c.x, c.y, 6, 0, 2 * Math.PI);
    ctx.fillStyle = '#090d16';
    ctx.fill();
    ctx.strokeStyle = '#06b6d4';
    ctx.lineWidth = 3;
    ctx.stroke();

    // Monto
    if (c.val > 0) {
      ctx.fillStyle = '#38bdf8';
      ctx.font = '700 11px system-ui';
      ctx.textAlign = 'center';
      ctx.fillText(`$${(c.val >= 1000 ? (c.val/1000).toFixed(0) + 'k' : c.val)}`, c.x, c.y - 12);
    }

    // Etiqueta del mes
    ctx.fillStyle = '#cbd5e1';
    ctx.font = '700 12px system-ui';
    ctx.textAlign = 'center';
    ctx.fillText(c.label, c.x, h - padBottom + 20);
  });
}

// 4. Gráfica Anual de TORTA / DONUT (Distribución por Concepto)
function drawAnnualPieChart(canvas, items, legendId, totalAnual) {
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width = canvas.clientWidth || 300, h = canvas.height = canvas.clientHeight || 280;
  ctx.clearRect(0, 0, w, h);

  const tot = totalAnual || items.reduce((a, b) => a + (Number(b.valor) || 0), 0) || 1;
  const cx = w / 2, cy = h / 2, r = Math.min(w, h) / 2 - 18, ir = r * 0.60;
  let start = -Math.PI / 2;

  items.forEach(it => {
    const val = Number(it.valor) || 0;
    const slice = (val / tot) * 2 * Math.PI;
    const end = start + slice;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, r, start, end);
    ctx.closePath();
    ctx.fillStyle = it.color || '#3b82f6';
    ctx.fill();
    start = end;
  });

  // Centro hueco (Donut)
  ctx.globalCompositeOperation = 'destination-out';
  ctx.beginPath();
  ctx.arc(cx, cy, ir, 0, 2 * Math.PI);
  ctx.fill();
  ctx.globalCompositeOperation = 'source-over';

  // Texto central
  ctx.fillStyle = '#f8fafc';
  ctx.font = '800 18px system-ui';
  ctx.textAlign = 'center';
  ctx.fillText(`$${fmtMoney(tot)}`, cx, cy + 2);
  ctx.fillStyle = '#94a3b8';
  ctx.font = '700 10px system-ui';
  ctx.fillText('TOTAL ANUAL', cx, cy + 18);

  // Renderizar Leyenda con barras de porcentaje
  const leg = document.getElementById(legendId);
  if (leg) {
    leg.innerHTML = '';
    items.forEach(it => {
      const val = Number(it.valor) || 0;
      const pct = tot > 0 ? ((val / tot) * 100).toFixed(1) : '0.0';
      const row = document.createElement('div');
      row.style.background = 'rgba(255, 255, 255, 0.03)';
      row.style.border = '1px solid var(--border)';
      row.style.borderRadius = '10px';
      row.style.padding = '10px 14px';
      row.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
          <div style="display:flex;align-items:center;gap:8px">
            <span style="display:inline-block;width:12px;height:12px;border-radius:4px;background:${it.color}"></span>
            <strong style="font-size:13px;color:#fff">${it.label}</strong>
          </div>
          <div style="text-align:right">
            <span style="font-weight:800;color:${it.color};font-size:13px">$ ${fmtMoney(val)}</span>
            <small style="color:var(--t-mut);margin-left:6px;font-weight:700">(${pct}%)</small>
          </div>
        </div>
        <div style="width:100%;height:4px;background:rgba(255,255,255,0.06);border-radius:2px;overflow:hidden">
          <div style="width:${pct}%;height:100%;background:${it.color};border-radius:2px"></div>
        </div>
      `;
      leg.appendChild(row);
    });
  }
}

// Carga y Ejecución de Reportes
async function loadReportes() {
  const { ok, data } = await api('reportes.avanzado', {}, 'GET');
  if (!ok) return;

  // Actualizar KPI resumen
  if ($('#rep-total-semana')) $('#rep-total-semana').textContent = fmtMoney(data.semana?.total || 0);
  if ($('#rep-total-mes')) $('#rep-total-mes').textContent = fmtMoney(data.mensual?.total_ultimo || 0);
  if ($('#rep-total-anual')) $('#rep-total-anual').textContent = fmtMoney(data.anual?.total || 0);
  if ($('#rep-year-lbl')) $('#rep-year-lbl').textContent = data.anual?.year || '2026';

  requestAnimationFrame(() => {
    // 1. Semanal de Barras
    drawWeeklyBarChart($('#chart-semanal-barras'), data.semana);
    // 2. Mensual de Líneas
    drawMonthlyLineChart($('#chart-mensual-lineas'), data.mensual);
    // 3. Anual de Torta con Leyenda
    drawAnnualPieChart($('#chart-anual-torta'), data.anual?.distribucion || [], 'legend-anual-torta', data.anual?.total || 0);
  });
}

function currentDate() {
  const d = new Date();
  return `${d.getFullYear()}-${('0' + (d.getMonth() + 1)).slice(-2)}-${('0' + d.getDate()).slice(-2)}`;
}

function calcVenc(baseStr, plan) {
  const d = new Date(baseStr || Date.now());
  d.setDate(d.getDate() + 30);
  return `${d.getFullYear()}-${('0' + (d.getMonth() + 1)).slice(-2)}-${('0' + d.getDate()).slice(-2)}`;
}

/* ===== INIT ===== */
window.addEventListener('DOMContentLoaded', () => {
  const d = new Date();
  if ($('#current-date-txt')) {
    $('#current-date-txt').textContent = d.toLocaleDateString('es-AR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  loadGymData();
  setPage('dashboard');
  if (CURRENT_USER.role !== 'alumno') {
    loadProfesOptions();
    loadAlumnosOptions();
  }
});
</script>
</body>
</html>
