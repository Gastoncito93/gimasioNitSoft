<?php
// Módulo API: saas

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
        $gymNombre = 'Todas las Sedes (Global SaaS)';
        $planTipo = 'pro';
        if ($gymId > 0) {
            $stG = $pdo->prepare("SELECT nombre, plan_tipo FROM gimnasios WHERE id = ? LIMIT 1");
            $stG->execute([$gymId]);
            $gData = $stG->fetch();
            if ($gData) {
                $gymNombre = $gData['nombre'];
                $planTipo = $gData['plan_tipo'] ?: 'standard';
            }
        }
        jsonOut(true, [
            'audit_gym_id'    => $_SESSION['audit_gym_id'],
            'gimnasio_nombre' => $gymNombre,
            'plan_tipo'       => $planTipo
        ], 'Modo de auditoría actualizado');
    }

    if ($action === 'saas.gimnasios.save') {
        requireRole(ROLE_ADMIN_GENERAL, true);
        $id        = (int)input('id', 0);
        $nombre    = trim(input('nombre', ''));
        $telefono  = trim(input('telefono', ''));
        $email     = trim(input('email', ''));
        $direccion = trim(input('direccion', ''));
        $planTipo  = input('plan_tipo', 'standard');
        $monto     = (float)input('suscripcion_monto', 45000);
        $venc      = input('suscripcion_vencimiento', date('Y-m-d', strtotime('+30 days')));
        $estado    = input('suscripcion_estado', 'activo');
        $code      = trim(input('invite_code', '')) ?: strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $nombre), 0, 8) . rand(100, 999));

        $duenoUser = trim(input('dueno_usuario', ''));
        $duenoPass = input('dueno_password', '');

        if ($nombre === '') jsonOut(false, [], 'El nombre del gimnasio es obligatorio');

        if ($id > 0) {
            $pdo->prepare("UPDATE gimnasios SET nombre=?, invite_code=?, telefono=?, email=?, direccion=?, plan_tipo=?, suscripcion_monto=?, suscripcion_vencimiento=?, suscripcion_estado=? WHERE id=?")
                ->execute([$nombre, $code, $telefono, $email, $direccion, $planTipo, $monto, $venc, $estado, $id]);
            
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

            $pdo->prepare("INSERT INTO gimnasios (nombre, invite_code, dueno_id, telefono, email, direccion, plan_tipo, suscripcion_monto, suscripcion_vencimiento, suscripcion_estado) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$nombre, $code, $newDuenoId, $telefono, $email, $direccion, $planTipo, $monto, $venc, $estado]);
            $id = (int)$pdo->lastInsertId();

            $pdo->prepare("UPDATE users SET gimnasio_id=? WHERE id=?")->execute([$id, $newDuenoId]);

            // Generar invitación por defecto
            $pdo->prepare("INSERT INTO invitaciones (gimnasio_id, token, rol, usos_restantes) VALUES (?, ?, 'alumno', 500)")
                ->execute([$id, $code . '_ALUMNO']);
            $pdo->prepare("INSERT INTO invitaciones (gimnasio_id, token, rol, usos_restantes) VALUES (?, ?, 'coach', 100)")
                ->execute([$id, $code . '_COACH']);
        }

        jsonOut(true, ['id' => $id], 'Gimnasio y Dueño guardados exitosamente');
    }

    if ($action === 'saas.gimnasios.toggle_suspension') {
        requireRole(ROLE_ADMIN_GENERAL, true);
        $id = (int)input('id', 0);
        $estadoActual = input('estado_actual', '');

        if ($estadoActual === 'suspendido') {
            $nuevoEstado = 'activo';
            $pdo->prepare("
                UPDATE gimnasios 
                SET suscripcion_estado = 'activo',
                    suscripcion_vencimiento = CASE 
                        WHEN suscripcion_vencimiento IS NULL OR suscripcion_vencimiento < CURDATE() 
                        THEN DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                        ELSE suscripcion_vencimiento 
                    END 
                WHERE id = ?
            ")->execute([$id]);

            $pdo->prepare("UPDATE users SET activo = 1 WHERE (gimnasio_id = ? AND rol = 'dueno') OR id = (SELECT dueno_id FROM gimnasios WHERE id = ?)")->execute([$id, $id]);
            $msg = '✅ Sede reactivada y cuenta del dueño habilitada con éxito.';
        } else {
            $nuevoEstado = 'suspendido';
            $pdo->prepare("UPDATE gimnasios SET suscripcion_estado = 'suspendido' WHERE id = ?")->execute([$id]);
            $pdo->prepare("UPDATE users SET activo = 0 WHERE (gimnasio_id = ? AND rol = 'dueno') OR id = (SELECT dueno_id FROM gimnasios WHERE id = ?)")->execute([$id, $id]);
            $msg = '🚫 Sede suspendida y cuenta del dueño bloqueada.';
        }

        jsonOut(true, ['id' => $id, 'nuevo_estado' => $nuevoEstado], $msg);
    }

    if ($action === 'saas.gimnasios.toggle_plan_pro') {
        requireRole(ROLE_ADMIN_GENERAL, true);
        $id       = (int)input('gimnasio_id', 0);
        $planTipo = input('plan_tipo', 'pro');

        if (!$id) jsonOut(false, [], 'ID de gimnasio obligatorio');
        if (!in_array($planTipo, ['standard', 'pro'], true)) $planTipo = 'pro';

        $stGym = $pdo->prepare("SELECT id, nombre, plan_tipo FROM gimnasios WHERE id = ? LIMIT 1");
        $stGym->execute([$id]);
        $gym = $stGym->fetch();
        if (!$gym) jsonOut(false, [], 'Gimnasio no encontrado');

        // Actualizar únicamente el plan_tipo en la base de datos (todos los socios, coaches y pagos se conservan intactos)
        $pdo->prepare("UPDATE gimnasios SET plan_tipo = ? WHERE id = ?")->execute([$planTipo, $id]);

        $msg = ($planTipo === 'pro')
            ? "👑 ¡Gimnasio '{$gym['nombre']}' ascendido al PLAN PRO! Módulo de nutrición habilitado."
            : "⭐ Gimnasio '{$gym['nombre']}' configurado en PLAN STANDARD.";

        jsonOut(true, ['id' => $id, 'plan_tipo' => $planTipo], $msg);
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
     * ENDPOINTS DE SIMULACIÓN MULTI-ROL (SUPERADMIN PERSPECTIVAS)
     * ------------------------------------------------------------- */
    if ($action === 'saas.simulation.options') {
        $gyms = $pdo->query("
            SELECT g.id, g.nombre, g.plan_tipo, u.nombre_usuario AS dueno_usuario, u.email AS dueno_email
            FROM gimnasios g
            LEFT JOIN users u ON u.id = g.dueno_id
            ORDER BY g.id ASC
        ")->fetchAll();
        $coaches = $pdo->query("SELECT p.id, p.nombre, p.gimnasio_id, g.nombre AS gym_nombre FROM profesores p LEFT JOIN gimnasios g ON g.id = p.gimnasio_id WHERE p.activo = 1 ORDER BY p.nombre ASC")->fetchAll();
        $alumnos = $pdo->query("SELECT a.id, a.nombre, a.gimnasio_id, a.plan, a.estado, g.nombre AS gym_nombre FROM alumnos a LEFT JOIN gimnasios g ON g.id = a.gimnasio_id WHERE a.estado != 'eliminado' ORDER BY a.nombre ASC")->fetchAll();

        jsonOut(true, [
            'is_simulating' => (bool)$isSimulating,
            'simulated_role' => $simulatedRole,
            'simulated_gym_id' => $simulatedGymId,
            'simulated_profesor_id' => $simulatedProfId,
            'simulated_alumno_id' => $simulatedAluId,
            'current_role' => $userRole,
            'gyms' => $gyms,
            'coaches' => $coaches,
            'alumnos' => $alumnos
        ]);
    }

    if ($action === 'saas.simulation.set') {
        $role = trim(input('role', '')) ?: trim(input('simulated_role', 'admin_general'));
        $gymId = (int)input('gimnasio_id', 0) ?: (int)input('gym_id', 0);
        $profId = (int)input('profesor_id', 0);
        $aluId = (int)input('alumno_id', 0);

        if ($role === 'admin_general') {
            unset($_SESSION['simulated_role'], $_SESSION['simulated_gym_id'], $_SESSION['simulated_profesor_id'], $_SESSION['simulated_alumno_id']);
            $_SESSION['audit_gym_id'] = null;
            jsonOut(true, ['role' => 'admin_general'], 'Vista restablecida al SuperAdmin Global');
        }

        if (!in_array($role, [ROLE_DUENO, ROLE_COACH, ROLE_ALUMNO])) {
            jsonOut(false, [], 'Rol de simulación no válido');
        }

        $_SESSION['simulated_role'] = $role;
        $_SESSION['simulated_gym_id'] = $gymId > 0 ? $gymId : 1;

        if ($role === ROLE_COACH) {
            if (!$profId && $gymId > 0) {
                $profId = (int)$pdo->query("SELECT id FROM profesores WHERE gimnasio_id = $gymId AND activo = 1 LIMIT 1")->fetchColumn();
            }
            $_SESSION['simulated_profesor_id'] = $profId ?: 1;
            $_SESSION['simulated_alumno_id'] = null;
        } elseif ($role === ROLE_ALUMNO) {
            if (!$aluId && $gymId > 0) {
                $aluId = (int)$pdo->query("SELECT id FROM alumnos WHERE gimnasio_id = $gymId AND estado != 'eliminado' LIMIT 1")->fetchColumn();
            }
            $_SESSION['simulated_alumno_id'] = $aluId ?: 1;
            $_SESSION['simulated_profesor_id'] = null;
        } else {
            $_SESSION['simulated_profesor_id'] = null;
            $_SESSION['simulated_alumno_id'] = null;
        }

        $_SESSION['audit_gym_id'] = $gymId > 0 ? $gymId : null;

        jsonOut(true, [
            'role' => $role,
            'gym_id' => $_SESSION['simulated_gym_id'],
            'profesor_id' => $_SESSION['simulated_profesor_id'],
            'alumno_id' => $_SESSION['simulated_alumno_id']
        ], "Modo simulación activado: viendo como " . strtoupper($role));
    }

    if ($action === 'saas.simulation.exit') {
        unset($_SESSION['simulated_role'], $_SESSION['simulated_gym_id'], $_SESSION['simulated_profesor_id'], $_SESSION['simulated_alumno_id']);
        $_SESSION['audit_gym_id'] = null;
        jsonOut(true, [], 'Modo simulación finalizado');
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE ENLACES DE INVITACIÓN DIRECTA (MULTI-TENANT)
     * ------------------------------------------------------------- */
    if ($action === 'invitaciones.get_links') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $gymId = $currentGymId ?: 1;
        $stGym = $pdo->prepare("SELECT nombre, invite_code FROM gimnasios WHERE id = ? LIMIT 1");
        $stGym->execute([$gymId]);
        $gymRow = $stGym->fetch() ?: [];
        $code = !empty($gymRow['invite_code']) ? $gymRow['invite_code'] : ('GYM_' . $gymId);
        
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $scheme = $isHttps ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/gimnasio/index.php');
        $scriptDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
        if (substr($scriptDir, -4) === '/api') {
            $scriptDir = substr($scriptDir, 0, -4);
        }
        $base = "{$scheme}://{$host}{$scriptDir}/register.php?invite=";

        $tokenAlu   = $code . '_ALUMNO';
        $tokenCoach = $code . '_COACH';

        // Asegurar que los tokens existan en la tabla invitaciones para este gimnasio
        $stEnsureAlu = $pdo->prepare("INSERT INTO invitaciones (gimnasio_id, token, rol, usos_restantes) VALUES (?, ?, 'alumno', 500) ON DUPLICATE KEY UPDATE usos_restantes = GREATEST(usos_restantes, 50)");
        $stEnsureAlu->execute([$gymId, $tokenAlu]);

        $stEnsureCoach = $pdo->prepare("INSERT INTO invitaciones (gimnasio_id, token, rol, usos_restantes) VALUES (?, ?, 'coach', 100) ON DUPLICATE KEY UPDATE usos_restantes = GREATEST(usos_restantes, 20)");
        $stEnsureCoach->execute([$gymId, $tokenCoach]);

        $linkAlumno = $base . $tokenAlu;
        $linkCoach  = $base . $tokenCoach;

        // Si quien solicita el enlace es un Coach, generar enlace directo con su ID de profesor para auto-asignación
        if ($userRole === ROLE_COACH && $profesorId) {
            $linkAlumno .= '&coach=' . (int)$profesorId;
        }

        jsonOut(true, [
            'is_coach'    => ($userRole === ROLE_COACH),
            'profesor_id' => $profesorId,
            'coach_nombre'=> $userRole === ROLE_COACH ? $userDisplayName : null,
            'gym_nombre'  => $gymRow['nombre'] ?? 'Gimnasio',
            'link_alumno' => $linkAlumno,
            'link_coach'  => $linkCoach,
            'code'        => $code
        ]);
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE CONCURRENCIA / ASISTENCIAS (AISLADO POR GYM_ID)
     * ------------------------------------------------------------- */

