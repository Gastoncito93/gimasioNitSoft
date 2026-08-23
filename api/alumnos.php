<?php
// Módulo API: alumnos

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
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
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
        $prof        = (int)input('profesor_id', 0) ?: null;
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
            $prevProf = $pdo->query("SELECT profesor_id FROM alumnos WHERE id = $id")->fetchColumn();
            $profAsigSql = ($prevProf != $prof) ? ", profesor_asignado_en = CURDATE()" : "";
            $pdo->prepare("UPDATE alumnos SET nombre=?, dni=?, telefono=?, email=?, plan=?, actividades=?, fecha_inicio=?, fecha_vencimiento=?, estado=?, profesor_id=?, es_del_gym=? $profAsigSql WHERE id=?")
                ->execute([$nombre, $dni, $telefono, $email, $plan, $actividades, $ini, $venc, $est, $prof, $esgym, $id]);
        } else {
            $pdo->prepare("INSERT INTO alumnos (gimnasio_id, nombre, dni, telefono, email, plan, actividades, fecha_inicio, fecha_vencimiento, estado, profesor_id, profesor_asignado_en, es_del_gym) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$gymDest, $nombre, $dni, $telefono, $email, $plan, $actividades, $ini, $venc, $est, $prof, $prof ? hoy() : null, $esgym]);
            $id = (int)$pdo->lastInsertId();
        }

        $p_monto = (float)input('pago_monto', 0);
        if ($p_monto > 0) {
            $pdo->prepare("INSERT INTO pagos (gimnasio_id, tipo, alumno_id, profesor_id, monto, fecha_pago, plan, medio_pago, observaciones) VALUES (?, 'alumno', ?, ?, ?, ?, ?, ?, 'Pago inicial registrado')")
                ->execute([$gymDest, $id, $prof, $p_monto, input('pago_fecha', hoy()), $plan, input('pago_medio', 'efectivo')]);
        }

        jsonOut(true, ['id' => $id], 'Alumno guardado correctamente');
    }

    if ($action === 'alumnos.delete') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $id = (int)input('id', 0);
        $gymDest = $currentGymId ?: 1;
        if (!$id) jsonOut(false, [], 'ID de alumno no válido');

        // Validar pertenencia a la sede
        $stCheck = $pdo->prepare("SELECT id, nombre FROM alumnos WHERE id = ? AND (gimnasio_id = ? OR ?=1) LIMIT 1");
        $stCheck->execute([$id, $gymDest, $isSuperAdmin ? 1 : 0]);
        if (!$stCheck->fetch()) {
            jsonOut(false, [], 'Alumno no encontrado en esta sede o sin permisos para eliminarlo.');
        }

        // Eliminar registros vinculados al alumno
        $pdo->prepare("DELETE FROM pagos WHERE alumno_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM asistencias WHERE alumno_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM rutinas_checkins WHERE alumno_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM rutinas WHERE alumno_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM planes_nutricionales WHERE alumno_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE alumno_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM alumnos WHERE id = ?")->execute([$id]);

        jsonOut(true, ['id' => $id], 'Alumno y registros asociados eliminados con éxito');
    }

    if ($action === 'alumnos.toggle_suspension') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $id      = (int)input('id', 0);
        $estado  = input('estado', 'pausado'); // 'pausado' (suspender) | 'activo' (reactivar)
        $gymDest = $currentGymId ?: 1;

        if (!$id) jsonOut(false, [], 'ID de alumno no especificado');
        if (!in_array($estado, ['activo', 'pausado', 'vencido'], true)) $estado = 'pausado';

        $stAlu = $pdo->prepare("SELECT id, nombre, id_users, estado, gimnasio_id FROM alumnos WHERE id = ? AND (gimnasio_id = ? OR ?=1) LIMIT 1");
        $stAlu->execute([$id, $gymDest, $isSuperAdmin ? 1 : 0]);
        $alu = $stAlu->fetch();
        if (!$alu) jsonOut(false, [], 'Alumno no encontrado en esta sede');

        // Actualizar estado del alumno en la tabla alumnos
        $pdo->prepare("UPDATE alumnos SET estado = ? WHERE id = ?")->execute([$estado, $id]);

        // Actualizar el estado activo del usuario en la tabla users
        $userActivo = ($estado === 'activo') ? 1 : 0;
        $pdo->prepare("UPDATE users SET activo = ? WHERE alumno_id = ? OR id = ?")->execute([$userActivo, $id, (int)$alu['id_users']]);

        $msg = ($estado === 'activo')
            ? "✅ Cuenta del alumno '{$alu['nombre']}' reactivada y habilitada con éxito. Todos sus datos están intactos."
            : "⏸️ Cuenta del alumno '{$alu['nombre']}' suspendida / pausada. Sus datos e historial quedan 100% resguardados.";

        jsonOut(true, ['id' => $id, 'estado' => $estado, 'user_activo' => $userActivo], $msg);
    }

    if ($action === 'alumnos.ficha') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $aluId       = (int)input('id', 0) ?: (int)input('alumno_id', 0);
        $gymDest     = (int)($currentGymId ?: 1);
        $userGymId   = (int)($currentGymId ?: ($_SESSION['gimnasio_id'] ?? 0));
        $ym          = ymHoy();
        $profIdSafe  = (int)($profesorId ?: 0);
        $isSuper     = $isSuperAdmin ? 1 : 0;
        $isCoachUser = hasRole(ROLE_COACH) ? 1 : 0;

        if (!$aluId) {
            jsonOut(false, [], 'ID de alumno requerido.');
        }

        $stAlu = $pdo->prepare("
            SELECT a.*, g.nombre AS gimnasio_nombre, p.nombre AS coach_nombre, p.telefono AS coach_telefono,
                   DATEDIFF(a.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM alumnos a
            LEFT JOIN gimnasios g ON g.id = a.gimnasio_id
            LEFT JOIN profesores p ON p.id = a.profesor_id
            WHERE a.id = ? AND (? = 1 OR a.gimnasio_id = ? OR a.gimnasio_id = ? OR (? = 1 AND a.profesor_id = ?) OR a.gimnasio_id IS NULL OR a.gimnasio_id = 0)
            LIMIT 1
        ");
        $stAlu->execute([$aluId, $isSuper, $gymDest, $userGymId, $isCoachUser, $profIdSafe]);
        $alumno = $stAlu->fetch();

        if (!$alumno) {
            jsonOut(false, [], 'Alumno no encontrado.');
        }

        // Cuota y pagos del mes
        $cuota = planPrice($pdo, $alumno['plan'] ?? '3x');
        $stAb = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE tipo = 'alumno' AND alumno_id = ? AND DATE_FORMAT(fecha_pago, '%Y-%m') = ?");
        $stAb->execute([$aluId, $ym]);
        $abonadoMes = (float)$stAb->fetchColumn();
        $saldoMes = max(0, $cuota - $abonadoMes);

        $alumno['cuota_mes']   = $cuota;
        $alumno['abonado_mes'] = $abonadoMes;
        $alumno['saldo_mes']   = $saldoMes;

        // Rutina activa asignada
        $stRut = $pdo->prepare("
            SELECT rp.*,
                   (SELECT COUNT(*) FROM rutinas_dias WHERE programa_id = rp.id) AS total_dias,
                   (SELECT COUNT(*) FROM rutinas_ejercicios WHERE dia_id IN (SELECT id FROM rutinas_dias WHERE programa_id = rp.id)) AS total_ejercicios
            FROM rutinas_programas rp
            WHERE rp.alumno_id = ? AND rp.es_plantilla = 0
            ORDER BY rp.id DESC LIMIT 1
        ");
        $stRut->execute([$aluId]);
        $rutina = $stRut->fetch() ?: null;
        $rutinaDias = [];
        if ($rutina) {
            $stDias = $pdo->prepare("
                SELECT rd.*, 
                       (SELECT COUNT(*) FROM rutinas_ejercicios WHERE dia_id = rd.id) AS ejercicios_count
                FROM rutinas_dias rd 
                WHERE rd.programa_id = ? 
                ORDER BY rd.numero_dia ASC
            ");
            $stDias->execute([$rutina['id']]);
            $rutinaDias = $stDias->fetchAll();
        }

        // Asistencias
        $dSem = inicioSemana();
        $hSem = finSemana();
        $stAsisMes = $pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE alumno_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?");
        $stAsisMes->execute([$aluId, $ym]);
        $asisMes = (int)$stAsisMes->fetchColumn();

        $stAsisSem = $pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE alumno_id = ? AND fecha BETWEEN ? AND ?");
        $stAsisSem->execute([$aluId, $dSem, $hSem]);
        $asisSem = (int)$stAsisSem->fetchColumn();

        $stAsisHist = $pdo->prepare("SELECT id, fecha, hora, observaciones FROM asistencias WHERE alumno_id = ? ORDER BY fecha DESC, hora DESC LIMIT 15");
        $stAsisHist->execute([$aluId]);
        $asisHist = $stAsisHist->fetchAll();

        // Pagos históricos
        $stPagos = $pdo->prepare("SELECT id, monto, fecha_pago, plan, medio_pago, observaciones FROM pagos WHERE tipo = 'alumno' AND alumno_id = ? ORDER BY fecha_pago DESC, id DESC LIMIT 15");
        $stPagos->execute([$aluId]);
        $pagosHist = $stPagos->fetchAll();

        // Historial de Rutinas Realizadas (Check-ins de entrenamientos)
        $stCheckins = $pdo->prepare("
            SELECT rc.*, p.nombre AS coach_nombre
            FROM rutinas_checkins rc
            LEFT JOIN alumnos a ON a.id = rc.alumno_id
            LEFT JOIN profesores p ON p.id = a.profesor_id
            WHERE rc.alumno_id = ?
            ORDER BY rc.fecha DESC, rc.hora DESC
            LIMIT 30
        ");
        $stCheckins->execute([$aluId]);
        $checkinsHist = $stCheckins->fetchAll();

        $stTotCh = $pdo->prepare("SELECT COUNT(*) FROM rutinas_checkins WHERE alumno_id = ?");
        $stTotCh->execute([$aluId]);
        $totalCheckins = (int)$stTotCh->fetchColumn();

        $stChMes = $pdo->prepare("SELECT COUNT(*) FROM rutinas_checkins WHERE alumno_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?");
        $stChMes->execute([$aluId, $ym]);
        $checkinsMes = (int)$stChMes->fetchColumn();

        jsonOut(true, [
            'alumno'           => $alumno,
            'rutina'           => $rutina,
            'rutina_dias'      => $rutinaDias,
            'rutinas_checkins' => $checkinsHist,
            'total_checkins'   => $totalCheckins,
            'checkins_mes'     => $checkinsMes,
            'asistencias'      => [
                'mes'       => $asisMes,
                'semana'    => $asisSem,
                'historial' => $asisHist,
                'ultima'    => !empty($asisHist) ? ($asisHist[0]['fecha'] . ' ' . ($asisHist[0]['hora'] ?: '')) : null
            ],
            'pagos'            => $pagosHist
        ]);
    }

    if ($action === 'alumnos.save_notes') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $aluId       = (int)input('id', 0) ?: (int)input('alumno_id', 0);
        $notasAlu    = trim(input('notas_alumno', ''));
        $notasCoach  = trim(input('notas_coach', ''));
        $gymDest     = $currentGymId ?: 1;

        if (!$aluId) {
            jsonOut(false, [], 'ID de alumno requerido.');
        }

        $pdo->prepare("UPDATE alumnos SET notas_alumno = ?, notas_coach = ? WHERE id = ? AND (gimnasio_id = ? OR ? = 1)")
            ->execute([$notasAlu, $notasCoach, $aluId, $gymDest, $isSuperAdmin ? 1 : 0]);

        jsonOut(true, [], 'Notas guardadas exitosamente');
    }


