<?php
// Módulo API: coaches

    if ($action === 'coach.desvincular_alumno') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $aluId = (int)input('alumno_id', 0);
        if (!$aluId) jsonOut(false, [], 'ID de alumno no especificado');

        if ($userRole === ROLE_COACH) {
            // El coach solo puede desvincular alumnos asignados a su propio ID de profesor
            $st = $pdo->prepare("SELECT id, nombre, gimnasio_id FROM alumnos WHERE id = ? AND profesor_id = ? LIMIT 1");
            $st->execute([$aluId, $profesorId]);
            $alu = $st->fetch();
            if (!$alu) jsonOut(false, [], 'El alumno no pertenece a tu lista de alumnos a cargo');
        } else {
            $st = $pdo->prepare("SELECT id, nombre, gimnasio_id FROM alumnos WHERE id = ? AND (gimnasio_id = ? OR ? = 1) LIMIT 1");
            $st->execute([$aluId, $currentGymId, $isSuperAdmin ? 1 : 0]);
            $alu = $st->fetch();
            if (!$alu) jsonOut(false, [], 'Alumno no encontrado');
        }

        // Desvincular al alumno del coach (el alumno permanece intacto en la sede y en el panel del dueño)
        $pdo->prepare("UPDATE alumnos SET profesor_id = NULL WHERE id = ?")->execute([$aluId]);

        // Registrar nota de trazabilidad en notas_coach
        $notaTrazabilidad = "\n[" . date('d/m/Y H:i') . "] Baja de lista a cargo del coach (" . ($userDisplayName ?: 'Coach') . "). Permanece activo en el gimnasio para el dueño.";
        $pdo->prepare("UPDATE alumnos SET notas_coach = CONCAT(COALESCE(notas_coach, ''), ?) WHERE id = ?")->execute([$notaTrazabilidad, $aluId]);

        jsonOut(true, [], 'Alumno dado de baja con éxito de tu lista. Permanece registrado en el gimnasio para el dueño.');
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE PROFESORES (AISLADO POR GYM_ID)
     * ------------------------------------------------------------- */
    if ($action === 'profesores.list') {
        $q  = trim(input('q', ''));
        $ym = ymHoy();
        $sql = "SELECT p.*, COUNT(DISTINCT a.id) AS total_alumnos, COALESCE(pm.total_mes,0) AS abonado_mes
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
            $pId = (int)$r['id'];
            $tipoRem = $r['tipo_remuneracion'] ?: 'sueldo_fijo';
            $cuotaSueldo = (float)($r['cuota_mensual'] ?? 0);
            $pctComision = (float)($r['porcentaje_comision'] ?? 0);
            $montoPorAlu = (float)($r['monto_por_alumno'] ?? 0);
            $canonMensual = (float)($r['canon_mensual'] ?? 0);
            $diaPagoCanon = (int)($r['dia_pago_canon'] ?? 10);
            $abon = (float)($r['abonado_mes'] ?? 0);

            // 1. Recaudación de los alumnos de este coach en el mes actual (atribución por fecha de asignación)
            $stRec = $pdo->prepare("
                SELECT COALESCE(SUM(pa.monto), 0) AS total_recaudado,
                       COUNT(DISTINCT pa.alumno_id) AS alumnos_pagaron
                FROM pagos pa
                JOIN alumnos al ON al.id = pa.alumno_id
                WHERE (pa.profesor_id = ? OR (al.profesor_id = ? AND (al.profesor_asignado_en IS NULL OR pa.fecha_pago >= al.profesor_asignado_en)))
                  AND pa.tipo = 'alumno'
                  AND DATE_FORMAT(pa.fecha_pago, '%Y-%m') = ?
            ");
            $stRec->execute([$pId, $pId, $ym]);
            $recRow = $stRec->fetch() ?: ['total_recaudado' => 0, 'alumnos_pagaron' => 0];
            $recaudadoAlus = (float)$recRow['total_recaudado'];
            $alumnosPagaron = (int)$recRow['alumnos_pagaron'];

            // 2. Cálculo de la ganancia según el modelo configurado
            $gananciaMes = 0.0;
            if ($tipoRem === 'porcentaje') {
                $gananciaMes = round($recaudadoAlus * ($pctComision / 100), 2);
            } elseif ($tipoRem === 'monto_alumno') {
                $gananciaMes = round($alumnosPagaron * $montoPorAlu, 2);
            } elseif ($tipoRem === 'canon_alquiler') {
                $gananciaMes = 0.0; // En este esquema el coach le paga al dueño
            } else {
                $gananciaMes = round($cuotaSueldo, 2);
            }

            // Canon pagado por el coach al dueño
            $stCanon = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE tipo='coach_a_dueno' AND profesor_id=? AND DATE_FORMAT(fecha_pago, '%Y-%m')=?");
            $stCanon->execute([$pId, $ym]);
            $canonAbonado = (float)$stCanon->fetchColumn();

            $r['tipo_remuneracion']    = $tipoRem;
            $r['cuota_mensual']        = $cuotaSueldo;
            $r['porcentaje_comision']  = $pctComision;
            $r['monto_por_alumno']     = $montoPorAlu;
            $r['canon_mensual']        = $canonMensual;
            $r['dia_pago_canon']       = $diaPagoCanon;
            $r['canon_abonado_mes']    = $canonAbonado;
            $r['canon_saldo_mes']      = max(0, $canonMensual - $canonAbonado);
            $r['recaudado_alumnos_mes']= $recaudadoAlus;
            $r['alumnos_pagaron_mes']  = $alumnosPagaron;
            $r['ganancia_calculada_mes'] = $gananciaMes;
            $r['saldo_mes']            = max(0, $gananciaMes - $abon);

            $stAlus = $pdo->prepare("
                SELECT id, nombre, telefono, plan, actividades, fecha_vencimiento, estado 
                FROM alumnos 
                WHERE profesor_id = ? 
                ORDER BY nombre ASC
            ");
            $stAlus->execute([$pId]);
            $r['alumnos_lista'] = $stAlus->fetchAll();
        }
        unset($r);
        jsonOut(true, $rows);
    }

    if ($action === 'profesores.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $id       = (int)input('id', 0);
        $nombre   = trim(input('nombre', ''));
        $tel      = trim(input('telefono', ''));
        $tipoRem  = input('tipo_remuneracion', 'sueldo_fijo');
        $cuota    = (float)input('cuota_mensual', 0);
        $pct      = (float)input('porcentaje_comision', 0);
        $mtoAlu   = (float)input('monto_por_alumno', 0);
        $canon    = (float)input('canon_mensual', 0);
        $diaCanon = (int)input('dia_pago_canon', 10);
        $fp       = input('fecha_pago', null);
        $obs      = trim(input('observaciones', ''));
        $gymDest  = $currentGymId ?: 1;

        if (!in_array($tipoRem, ['sueldo_fijo', 'porcentaje', 'monto_alumno', 'canon_alquiler'], true)) {
            $tipoRem = 'sueldo_fijo';
        }

        if ($id > 0) {
            // Edición por parte del dueño: solo actualiza esquema de remuneración, honorarios, canon y observaciones
            $pdo->prepare("UPDATE profesores SET tipo_remuneracion=?, cuota_mensual=?, porcentaje_comision=?, monto_por_alumno=?, canon_mensual=?, dia_pago_canon=?, fecha_pago=?, observaciones=? WHERE id=? AND (gimnasio_id=? OR ?=1)")
                ->execute([$tipoRem, $cuota, $pct, $mtoAlu, $canon, $diaCanon, $fp ?: null, $obs, $id, $gymDest, $isSuperAdmin ? 1 : 0]);
        } else {
            if ($nombre === '') jsonOut(false, [], 'El nombre completo del coach es obligatorio.');
            $pdo->prepare("INSERT INTO profesores (gimnasio_id, nombre, telefono, tipo_remuneracion, cuota_mensual, porcentaje_comision, monto_por_alumno, canon_mensual, dia_pago_canon, fecha_pago, observaciones) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$gymDest, $nombre, $tel, $tipoRem, $cuota, $pct, $mtoAlu, $canon, $diaCanon, $fp ?: null, $obs]);
        }
        jsonOut(true, [], 'Condiciones, canon y remuneración del coach guardadas exitosamente');
    }

    if ($action === 'profesores.assign_alumnos') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $profId = (int)input('profesor_id', 0);
        $aluIds = input('alumno_ids', []);
        $gymDest = $currentGymId ?: 1;

        if (!$profId) jsonOut(false, [], 'ID de coach obligatorio');
        if (!is_array($aluIds)) {
            $aluIds = array_filter(array_map('intval', explode(',', (string)$aluIds)));
        } else {
            $aluIds = array_map('intval', $aluIds);
        }

        // 1. Validar que ninguno de los alumnos seleccionados pertenezca actualmente a OTRO profesor
        if (!empty($aluIds)) {
            $inClause = implode(',', array_fill(0, count($aluIds), '?'));
            $stCheck = $pdo->prepare("
                SELECT a.id, a.nombre, p.nombre AS coach_nombre 
                FROM alumnos a
                JOIN profesores p ON p.id = a.profesor_id
                WHERE a.id IN ($inClause) AND a.profesor_id != ? AND (a.gimnasio_id = ? OR ?=1)
                LIMIT 1
            ");
            $paramsCheck = array_merge($aluIds, [$profId, $gymDest, $isSuperAdmin ? 1 : 0]);
            $stCheck->execute($paramsCheck);
            $conflict = $stCheck->fetch();

            if ($conflict) {
                jsonOut(false, [], "El socio '{$conflict['nombre']}' ya está asignado al coach '{$conflict['coach_nombre']}'. No se puede asignar a otro coach simultáneamente.");
            }
        }

        // 2. Quitar los alumnos que antes estaban con este profesor pero ahora se desmarcaron
        if (!empty($aluIds)) {
            $inClause = implode(',', array_fill(0, count($aluIds), '?'));
            $stUnset = $pdo->prepare("UPDATE alumnos SET profesor_id = NULL WHERE profesor_id = ? AND id NOT IN ($inClause) AND (gimnasio_id = ? OR ?=1)");
            $params = array_merge([$profId], $aluIds, [$gymDest, $isSuperAdmin ? 1 : 0]);
            $stUnset->execute($params);
        } else {
            $stUnsetAll = $pdo->prepare("UPDATE alumnos SET profesor_id = NULL WHERE profesor_id = ? AND (gimnasio_id = ? OR ?=1)");
            $stUnsetAll->execute([$profId, $gymDest, $isSuperAdmin ? 1 : 0]);
        }

        // 3. Asignar los alumnos seleccionados
        $assignedCount = 0;
        if (!empty($aluIds)) {
            $stSet = $pdo->prepare("UPDATE alumnos SET profesor_id = ? WHERE id = ? AND (profesor_id IS NULL OR profesor_id = ?) AND (gimnasio_id = ? OR ?=1)");
            foreach ($aluIds as $aId) {
                if ($aId > 0) {
                    $stSet->execute([$profId, $aId, $profId, $gymDest, $isSuperAdmin ? 1 : 0]);
                    $assignedCount++;
                }
            }
        }

        jsonOut(true, ['count' => $assignedCount], "Se asignaron {$assignedCount} socio(s) al coach correctamente");
    }

    if ($action === 'profesores.delete') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $id = (int)input('id', 0);
        $gymDest = $currentGymId ?: 1;
        if (!$id) jsonOut(false, [], 'ID de coach no válido');

        $stProf = $pdo->prepare("SELECT id, nombre FROM profesores WHERE id = ? AND (gimnasio_id = ? OR ?=1) LIMIT 1");
        $stProf->execute([$id, $gymDest, $isSuperAdmin ? 1 : 0]);
        if (!$stProf->fetch()) {
            jsonOut(false, [], 'Coach no encontrado en esta sede o sin permisos para eliminarlo.');
        }

        $pdo->prepare("UPDATE alumnos SET profesor_id=NULL WHERE profesor_id=?")->execute([$id]);
        $pdo->prepare("UPDATE users SET profesor_id=NULL WHERE profesor_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM profesores WHERE id=?")->execute([$id]);
        jsonOut(true, [], 'Coach / Profesor eliminado');
    }

    if ($action === 'profesores.toggle_suspension') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $id      = (int)input('id', 0);
        $activo  = (int)input('activo', 0); // 0: suspender, 1: reactivar
        $gymDest = $currentGymId ?: 1;

        if (!$id) jsonOut(false, [], 'ID de coach no especificado');

        $stProf = $pdo->prepare("SELECT id, nombre, activo, gimnasio_id FROM profesores WHERE id = ? AND (gimnasio_id = ? OR ?=1) LIMIT 1");
        $stProf->execute([$id, $gymDest, $isSuperAdmin ? 1 : 0]);
        $prof = $stProf->fetch();
        if (!$prof) jsonOut(false, [], 'Coach no encontrado en esta sede');

        // Actualizar activo en profesores
        $pdo->prepare("UPDATE profesores SET activo = ? WHERE id = ?")->execute([$activo, $id]);

        // Actualizar el estado activo del usuario en la tabla users
        $pdo->prepare("UPDATE users SET activo = ? WHERE profesor_id = ?")->execute([$activo, $id]);

        $msg = ($activo === 1)
            ? "✅ Cuenta del Coach '{$prof['nombre']}' reactivada y habilitada con éxito. Todos sus alumnos y datos se conservan."
            : "⏸️ Cuenta del Coach '{$prof['nombre']}' suspendida / pausada. Sus datos y alumnos quedan 100% resguardados.";

        jsonOut(true, ['id' => $id, 'activo' => $activo], $msg);
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE PAGOS (AISLADO POR GYM_ID)
     * ------------------------------------------------------------- */

    if ($action === 'coach.pagos.list') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $profId = (int)input('profesor_id', 0) ?: ($profesorId ?: 0);
        $gymDest = $currentGymId ?: 1;
        $ym = input('periodo', ymHoy());

        if (!$profId) jsonOut(false, [], 'Coach no identificado');

        $stProf = $pdo->prepare("SELECT * FROM profesores WHERE id = ?");
        $stProf->execute([$profId]);
        $prof = $stProf->fetch();
        if (!$prof) jsonOut(false, [], 'Coach no encontrado');

        // 1. Pagos que el Dueño le realizó al Coach (Liquidaciones de honorarios / comisiones)
        $stLiq = $pdo->prepare("
            SELECT pa.*, pr.nombre AS profesor, g.nombre AS gym_nombre 
            FROM pagos pa
            LEFT JOIN profesores pr ON pr.id = pa.profesor_id
            LEFT JOIN gimnasios g ON g.id = pa.gimnasio_id
            WHERE pa.tipo = 'profesor' AND pa.profesor_id = ? 
            ORDER BY pa.fecha_pago DESC, pa.id DESC
        ");
        $stLiq->execute([$profId]);
        $liquidacionesRecibidas = $stLiq->fetchAll();

        // 2. Cobros de alumnos a cargo del coach
        $stCobros = $pdo->prepare("
            SELECT pa.*, al.nombre AS alumno_nombre, al.plan AS alumno_plan, g.nombre AS gym_nombre 
            FROM pagos pa
            JOIN alumnos al ON al.id = pa.alumno_id
            LEFT JOIN gimnasios g ON g.id = pa.gimnasio_id
            WHERE (pa.profesor_id = ? OR (al.profesor_id = ? AND (al.profesor_asignado_en IS NULL OR pa.fecha_pago >= al.profesor_asignado_en)))
              AND pa.tipo = 'alumno'
            ORDER BY pa.fecha_pago DESC, pa.id DESC
            LIMIT 50
        ");
        $stCobros->execute([$profId, $profId]);
        $cobrosAlumnos = $stCobros->fetchAll();

        // 3. Estadísticas de días y actividad
        $stDias = $pdo->prepare("
            SELECT COUNT(DISTINCT fecha) AS dias_activos, COUNT(*) AS total_asistencias
            FROM asistencias 
            WHERE coach_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?
        ");
        $stDias->execute([$profId, $ym]);
        $statsDias = $stDias->fetch() ?: ['dias_activos' => 0, 'total_asistencias' => 0];

        // Total liquidado en el mes actual por el dueño
        $stTotLiq = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE tipo = 'profesor' AND profesor_id = ? AND DATE_FORMAT(fecha_pago, '%Y-%m') = ?");
        $stTotLiq->execute([$profId, $ym]);
        $totLiqMes = (float)$stTotLiq->fetchColumn();

        // Total recaudado de alumnos este mes
        $stRec = $pdo->prepare("
            SELECT COALESCE(SUM(pa.monto), 0) AS total_recaudado,
                   COUNT(DISTINCT pa.alumno_id) AS alumnos_pagaron
            FROM pagos pa
            JOIN alumnos al ON al.id = pa.alumno_id
            WHERE (pa.profesor_id = ? OR (al.profesor_id = ? AND (al.profesor_asignado_en IS NULL OR pa.fecha_pago >= al.profesor_asignado_en)))
              AND pa.tipo = 'alumno'
              AND DATE_FORMAT(pa.fecha_pago, '%Y-%m') = ?
        ");
        $stRec->execute([$profId, $profId, $ym]);
        $recRow = $stRec->fetch() ?: ['total_recaudado' => 0, 'alumnos_pagaron' => 0];
        $recaudadoMes = (float)$recRow['total_recaudado'];
        $alumnosPagaronMes = (int)$recRow['alumnos_pagaron'];

        // Cálculo de Ganancia según esquema pactado
        $tipoRem = $prof['tipo_remuneracion'] ?? 'sueldo_fijo';
        $pctComision = (float)($prof['porcentaje_comision'] ?? 0);
        $montoPorAlu = (float)($prof['monto_por_alumno'] ?? 0);
        $cuotaSueldo = (float)($prof['cuota_mensual'] ?? 0);

        $gananciaMes = 0.0;
        if ($tipoRem === 'porcentaje') {
            $gananciaMes = round($recaudadoMes * ($pctComision / 100), 2);
        } elseif ($tipoRem === 'monto_alumno') {
            $gananciaMes = round($alumnosPagaronMes * $montoPorAlu, 2);
        } else {
            $gananciaMes = round($cuotaSueldo, 2);
        }

        $saldoPendiente = max(0, $gananciaMes - $totLiqMes);

        jsonOut(true, [
            'profesor'                => $prof,
            'liquidaciones_recibidas' => $liquidacionesRecibidas,
            'cobros_alumnos'          => $cobrosAlumnos,
            'stats_dias'              => $statsDias,
            'totales_mes'             => [
                'recaudado_alumnos'   => $recaudadoMes,
                'alumnos_pagaron'     => $alumnosPagaronMes,
                'ganancia_mes'        => $gananciaMes,
                'liquidado_mes'       => $totLiqMes,
                'saldo_pendiente'     => $saldoPendiente,
                'tipo_remuneracion'   => $tipoRem,
                'porcentaje_comision' => $pctComision,
                'monto_por_alumno'    => $montoPorAlu,
                'cuota_mensual'       => $cuotaSueldo
            ]
        ]);
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE CHECK-IN Y SEGUIMIENTO DE RUTINAS DE ALUMNOS
     * ------------------------------------------------------------- */

