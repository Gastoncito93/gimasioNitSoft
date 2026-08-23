<?php
// Módulo API: pagos

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
            if (strlen($mes) === 4) {
                $sql .= " AND DATE_FORMAT(pa.fecha_pago, '%Y') = ?";
                $p[] = $mes;
            } else {
                $sql .= " AND DATE_FORMAT(pa.fecha_pago, '%Y-%m') = ?";
                $p[] = $mes;
            }
        }

        if ($q !== '') {
            $sql .= " AND (al.nombre LIKE ? OR pr.nombre LIKE ? OR pa.observaciones LIKE ? OR pa.plan LIKE ? OR pa.medio_pago LIKE ?)";
            $p[] = '%' . $q . '%';
            $p[] = '%' . $q . '%';
            $p[] = '%' . $q . '%';
            $p[] = '%' . $q . '%';
            $p[] = '%' . $q . '%';
        }

        $sql .= " ORDER BY pa.fecha_pago DESC, pa.id DESC LIMIT 2000";
        $st = $pdo->prepare($sql);
        $st->execute($p);
        jsonOut(true, $st->fetchAll());
    }

    if ($action === 'pagos.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $tipo  = input('tipo', 'alumno');
        $alu   = (int)input('alumno_id', 0) ?: null;
        $pro   = (int)input('profesor_id', 0) ?: null;
        $monto = round((float)input('monto', 0), 2);
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
            $stAl = $pdo->prepare("SELECT id, nombre, plan, profesor_id, fecha_vencimiento, estado FROM alumnos WHERE id = ? AND (gimnasio_id = ? OR ?=1) LIMIT 1");
            $stAl->execute([$alu, $gymDest, $isSuperAdmin ? 1 : 0]);
            $al = $stAl->fetch();
            if (!$al) {
                jsonOut(false, [], 'Alumno no encontrado en esta sede.');
            }

            $pl = $plan ?: ($al['plan'] ?? '3x');
            $plan = $pl;

            // Validación estricta: el monto no puede superar el valor de la cuota / saldo del plan
            $cuotaPlan = planPrice($pdo, $plan);
            $stAb = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE tipo = 'alumno' AND alumno_id = ? AND DATE_FORMAT(fecha_pago, '%Y-%m') = ?");
            $stAb->execute([$alu, $ym]);
            $abonadoMes = (float)$stAb->fetchColumn();
            $saldoMes = max(0, $cuotaPlan - $abonadoMes);
            $maxCobro = ($saldoMes > 0) ? $saldoMes : $cuotaPlan;

            if ($maxCobro > 0 && $monto > ($maxCobro + 0.01)) {
                jsonOut(false, [], "El monto ingresado ($ " . number_format($monto, 2, ',', '.') . ") supera el valor máximo a cobrar de $ " . number_format($maxCobro, 2, ',', '.') . " correspondiente a la cuota del Plan " . strtoupper($plan) . ". Podés registrar hasta $ " . number_format($maxCobro, 2, ',', '.') . ".");
            }
        } elseif ($tipo === 'profesor') {
            if (!$pro) {
                jsonOut(false, [], 'Debés seleccionar un coach/profesor.');
            }
            $stPr = $pdo->prepare("SELECT id, nombre, tipo_remuneracion, cuota_mensual, porcentaje_comision, monto_por_alumno FROM profesores WHERE id = ? AND (gimnasio_id = ? OR ?=1) LIMIT 1");
            $stPr->execute([$pro, $gymDest, $isSuperAdmin ? 1 : 0]);
            $pr = $stPr->fetch();
            if (!$pr) {
                jsonOut(false, [], 'Coach no encontrado.');
            }

            $tipoRem = $pr['tipo_remuneracion'] ?: 'sueldo_fijo';
            $cuotaProf = 0.0;

            if ($tipoRem === 'porcentaje') {
                $stRec = $pdo->prepare("
                    SELECT COALESCE(SUM(pa.monto), 0)
                    FROM pagos pa
                    JOIN alumnos al ON al.id = pa.alumno_id
                    WHERE (pa.profesor_id = ? OR (al.profesor_id = ? AND (al.profesor_asignado_en IS NULL OR pa.fecha_pago >= al.profesor_asignado_en)))
                      AND pa.tipo = 'alumno'
                      AND DATE_FORMAT(pa.fecha_pago, '%Y-%m') = ?
                ");
                $stRec->execute([$pro, $pro, $ym]);
                $recAlus = (float)$stRec->fetchColumn();
                $cuotaProf = round($recAlus * ((float)$pr['porcentaje_comision'] / 100), 2);
            } elseif ($tipoRem === 'monto_alumno') {
                $stCnt = $pdo->prepare("
                    SELECT COUNT(DISTINCT pa.alumno_id)
                    FROM pagos pa
                    JOIN alumnos al ON al.id = pa.alumno_id
                    WHERE (pa.profesor_id = ? OR (al.profesor_id = ? AND (al.profesor_asignado_en IS NULL OR pa.fecha_pago >= al.profesor_asignado_en)))
                      AND pa.tipo = 'alumno'
                      AND DATE_FORMAT(pa.fecha_pago, '%Y-%m') = ?
                ");
                $stCnt->execute([$pro, $pro, $ym]);
                $cntAlus = (int)$stCnt->fetchColumn();
                $cuotaProf = round($cntAlus * (float)$pr['monto_por_alumno'], 2);
            } else {
                $cuotaProf = (float)$pr['cuota_mensual'];
            }

            $stPagProf = $pdo->prepare("
                SELECT COALESCE(SUM(monto), 0) 
                FROM pagos 
                WHERE tipo = 'profesor' AND profesor_id = ? AND DATE_FORMAT(fecha_pago, '%Y-%m') = ?
            ");
            $stPagProf->execute([$pro, $ym]);
            $pagadoMes = (float)$stPagProf->fetchColumn();
            $saldoProf = round(max(0, $cuotaProf - $pagadoMes), 2);

            if ($cuotaProf <= 0) {
                jsonOut(false, [], "El coach '{$pr['nombre']}' tiene $ 0 de honorarios o comisiones acumuladas este mes (sus alumnos asignados aún no registran pagos en este período).");
            }

            if ($pagadoMes >= $cuotaProf || $saldoProf <= 0) {
                jsonOut(false, [], "El coach '{$pr['nombre']}' ya tiene sus honorarios mensuales calculados de $ " . number_format($cuotaProf, 2, ',', '.') . " totalmente liquidados.");
            }

            if ($monto > $saldoProf) {
                jsonOut(false, [], "El monto ingresado ($ " . number_format($monto, 2, ',', '.') . ") supera el saldo restante a liquidar ($ " . number_format($saldoProf, 2, ',', '.') . "). Podés liquidar el total o un monto parcial menor.");
            }
        }

        $profAtPayment = ($tipo === 'alumno' && $alu) ? ($al['profesor_id'] ?: null) : $pro;
        $pdo->prepare("INSERT INTO pagos (gimnasio_id, tipo, alumno_id, profesor_id, monto, fecha_pago, plan, medio_pago, observaciones) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$gymDest, $tipo, $alu, $profAtPayment, $monto, $fecha, $plan, $medio, $obs]);

        if ($tipo === 'alumno' && $alu) {
            $currVenc = $al['fecha_vencimiento'] ?? null;
            $baseDate = ($currVenc && new DateTime($currVenc) >= new DateTime($fecha)) ? $currVenc : $fecha;
            $nv = calcVencimiento($baseDate, $plan);
            $pdo->prepare("UPDATE alumnos SET fecha_vencimiento=?, estado='activo' WHERE id=?")->execute([$nv, $alu]);
        }

        jsonOut(true, [], 'Pago registrado exitosamente');
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE PAGOS Y CANON DEL COACH (DUEÑO <-> COACH)
     * ------------------------------------------------------------- */

