<?php
// Módulo API: dashboard

    if ($action === 'dashboard.kpis') {
        $ym = ymHoy();

        if (hasRole([ROLE_ADMIN_GENERAL, ROLE_DUENO])) {
            $isSuper = hasRole(ROLE_ADMIN_GENERAL);
            $gymFilterAlu = $currentGymId ? " WHERE a.gimnasio_id = $currentGymId" : "";
            $gymFilterProf = $currentGymId ? " WHERE p.gimnasio_id = $currentGymId" : "";
            $gymFilterPagoAlu = $currentGymId ? " AND pa.gimnasio_id = $currentGymId" : "";
            $gymFilterPagoProf = $currentGymId ? " AND pa.gimnasio_id = $currentGymId" : "";
            $gymFilterSimple = $currentGymId ? " WHERE gimnasio_id = $currentGymId" : "";
            $gymFilterSimpleAnd = $currentGymId ? " AND gimnasio_id = $currentGymId" : "";

            // Métricas del Gimnasio
            $totalAlu  = (int)$pdo->query("SELECT COUNT(*) FROM alumnos $gymFilterSimple")->fetchColumn();

            // Cálculo exacto de alumnos al día (pagaron su cuota del mes) vs alumnos con saldo pendiente / deuda
            $sqlAlus = "
                SELECT a.id, a.nombre, a.dni, a.telefono, a.plan, a.estado, a.fecha_vencimiento, COALESCE(pa.total_mes, 0) AS abonado_mes
                FROM alumnos a
                LEFT JOIN (
                    SELECT alumno_id, SUM(monto) AS total_mes 
                    FROM pagos 
                    WHERE tipo='alumno' AND DATE_FORMAT(fecha_pago,'%Y-%m')='$ym'
                    " . ($currentGymId ? " AND gimnasio_id = $currentGymId" : "") . "
                    GROUP BY alumno_id
                ) pa ON pa.alumno_id = a.id
                $gymFilterAlu
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
                SELECT p.id, p.nombre, p.telefono, p.tipo_remuneracion, p.cuota_mensual, p.porcentaje_comision, p.monto_por_alumno,
                       COALESCE(pp.total_mes, 0) AS pagado_mes
                FROM profesores p
                LEFT JOIN (
                    SELECT profesor_id, SUM(monto) AS total_mes
                    FROM pagos
                    WHERE tipo='profesor' AND DATE_FORMAT(fecha_pago, '%Y-%m')='$ym'
                    " . ($currentGymId ? " AND gimnasio_id = $currentGymId" : "") . "
                    GROUP BY profesor_id
                ) pp ON pp.profesor_id = p.id
                $gymFilterProf
                ORDER BY p.nombre ASC
            ";
            $allProfes = $pdo->query($sqlProf)->fetchAll();
            $profesAlDia = [];
            $profesConDeuda = [];

            foreach ($allProfes as $pr) {
                $pId = (int)$pr['id'];
                $tipoRem = $pr['tipo_remuneracion'] ?: 'sueldo_fijo';
                $cuotaSueldo = (float)$pr['cuota_mensual'];
                $pctComision = (float)$pr['porcentaje_comision'];
                $montoPorAlu = (float)$pr['monto_por_alumno'];
                $pagado = (float)$pr['pagado_mes'];

                // Calcular ganancia esperada del coach en el mes (atribución por fecha de asignación)
                $gananciaMes = 0.0;
                if ($tipoRem === 'porcentaje') {
                    $stRec = $pdo->prepare("
                        SELECT COALESCE(SUM(pa.monto), 0)
                        FROM pagos pa
                        JOIN alumnos al ON al.id = pa.alumno_id
                        WHERE (pa.profesor_id = ? OR (al.profesor_id = ? AND (al.profesor_asignado_en IS NULL OR pa.fecha_pago >= al.profesor_asignado_en)))
                          AND pa.tipo = 'alumno'
                          AND DATE_FORMAT(pa.fecha_pago, '%Y-%m') = ?
                    ");
                    $stRec->execute([$pId, $pId, $ym]);
                    $recAlus = (float)$stRec->fetchColumn();
                    $gananciaMes = round($recAlus * ($pctComision / 100), 2);
                } elseif ($tipoRem === 'monto_alumno') {
                    $stCnt = $pdo->prepare("
                        SELECT COUNT(DISTINCT pa.alumno_id)
                        FROM pagos pa
                        JOIN alumnos al ON al.id = pa.alumno_id
                        WHERE (pa.profesor_id = ? OR (al.profesor_id = ? AND (al.profesor_asignado_en IS NULL OR pa.fecha_pago >= al.profesor_asignado_en)))
                          AND pa.tipo = 'alumno'
                          AND DATE_FORMAT(pa.fecha_pago, '%Y-%m') = ?
                    ");
                    $stCnt->execute([$pId, $pId, $ym]);
                    $cntAlus = (int)$stCnt->fetchColumn();
                    $gananciaMes = round($cntAlus * $montoPorAlu, 2);
                } else {
                    $gananciaMes = round($cuotaSueldo, 2);
                }

                $saldo = max(0, $gananciaMes - $pagado);
                $isPaid = ($pagado >= $gananciaMes && $gananciaMes > 0);

                $pItem = [
                    'id'                => $pId,
                    'nombre'            => $pr['nombre'],
                    'tipo_remuneracion' => $tipoRem,
                    'cuota'             => $gananciaMes,
                    'pagado'            => $pagado,
                    'saldo'             => $saldo
                ];

                if ($isPaid || ($gananciaMes == 0 && $pagado >= 0)) {
                    $profesAlDia[] = $pItem;
                } else {
                    $profesConDeuda[] = $pItem;
                }
            }

            $totalProf    = count($allProfes);
            $pagadosProf  = count($profesAlDia);
            $deudaProf    = count($profesConDeuda);

            // Recaudación real de alumnos (cobranza de cuotas del gimnasio)
            $h    = hoy();
            $dSem = inicioSemana();
            $hSem = finSemana();
            $diaTot = (float)$pdo->query("SELECT COALESCE(SUM(pa.monto), 0) FROM pagos pa JOIN alumnos al ON al.id = pa.alumno_id WHERE pa.tipo = 'alumno' AND pa.fecha_pago = '$h' $gymFilterPagoAlu")->fetchColumn();
            $semTot = (float)$pdo->query("SELECT COALESCE(SUM(pa.monto), 0) FROM pagos pa JOIN alumnos al ON al.id = pa.alumno_id WHERE pa.tipo = 'alumno' AND pa.fecha_pago BETWEEN '$dSem' AND '$hSem' $gymFilterPagoAlu")->fetchColumn();
            $ingMes = (float)$pdo->query("SELECT COALESCE(SUM(pa.monto), 0) FROM pagos pa JOIN alumnos al ON al.id = pa.alumno_id WHERE pa.tipo = 'alumno' AND DATE_FORMAT(pa.fecha_pago, '%Y-%m') = '$ym' $gymFilterPagoAlu")->fetchColumn();

            // Total liquidado a coaches en el mes (egresos)
            $liqMes = (float)$pdo->query("SELECT COALESCE(SUM(pa.monto), 0) FROM pagos pa JOIN profesores pr ON pr.id = pa.profesor_id WHERE pa.tipo = 'profesor' AND DATE_FORMAT(pa.fecha_pago, '%Y-%m') = '$ym' $gymFilterPagoProf")->fetchColumn();

            // Honorarios calculados / esperados para coaches este mes según sus esquemas
            $honorariosEsperadosCoaches = (float)array_sum(array_column($profesAlDia, 'cuota')) + (float)array_sum(array_column($profesConDeuda, 'cuota'));

            // Concurrencia de hoy
            $asistHoy = (int)$pdo->query("SELECT COUNT(*) FROM asistencias WHERE fecha='$h' $gymFilterSimpleAnd")->fetchColumn();

            // Próximos vencimientos
            $sqlProx = "SELECT id, nombre, telefono, plan, fecha_vencimiento, estado 
                        FROM alumnos 
                        WHERE estado='activo' AND DATEDIFF(fecha_vencimiento, CURDATE()) BETWEEN 0 AND ? $gymFilterSimpleAnd
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
                    'liquidado_coaches'   => $liqMes,
                    'honorarios_coaches'  => $honorariosEsperadosCoaches,
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
                'recaudacion' => ['dia' => $diaTot, 'semana' => $semTot, 'mes' => $ingMes, 'liquidado_coaches' => $liqMes],
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

            // Datos del Coach y su Modelo de Remuneración
            $stProf = $pdo->prepare("SELECT * FROM profesores WHERE id=?");
            $stProf->execute([$pId]); $profData = $stProf->fetch() ?: [];

            $tipoRem = $profData['tipo_remuneracion'] ?? 'sueldo_fijo';
            $pctComision = (float)($profData['porcentaje_comision'] ?? 0);
            $montoPorAlu = (float)($profData['monto_por_alumno'] ?? 0);
            $cuotaSueldo = (float)($profData['cuota_mensual'] ?? 0);

            // Total recaudado de sus alumnos en el mes actual (atribución por fecha de asignación)
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
            $recaudadoMes = (float)$recRow['total_recaudado'];
            $alumnosPagaronMes = (int)$recRow['alumnos_pagaron'];

            // Total recaudado mes previo para cálculo de variación
            $ymPrev = (new DateTime('first day of last month'))->format('Y-m');
            $stRec->execute([$pId, $pId, $ymPrev]);
            $recRowPrev = $stRec->fetch() ?: ['total_recaudado' => 0, 'alumnos_pagaron' => 0];
            $recaudadoMesPrev = (float)$recRowPrev['total_recaudado'];
            $alumnosPagaronMesPrev = (int)$recRowPrev['alumnos_pagaron'];

            // Ganancia calculada según el modelo pactado
            $gananciaMes = 0.0;
            $gananciaMesPrev = 0.0;
            if ($tipoRem === 'porcentaje') {
                $gananciaMes = round($recaudadoMes * ($pctComision / 100), 2);
                $gananciaMesPrev = round($recaudadoMesPrev * ($pctComision / 100), 2);
            } elseif ($tipoRem === 'monto_alumno') {
                $gananciaMes = round($alumnosPagaronMes * $montoPorAlu, 2);
                $gananciaMesPrev = round($alumnosPagaronMesPrev * $montoPorAlu, 2);
            } else {
                $gananciaMes = round($cuotaSueldo, 2);
                $gananciaMesPrev = round($cuotaSueldo, 2);
            }
            $varGanancia = $gananciaMesPrev > 0 ? round(($gananciaMes - $gananciaMesPrev) / $gananciaMesPrev * 100, 1) : null;

            // Liquidado este mes al coach por el gimnasio
            $stLiq = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE tipo='profesor' AND profesor_id=? AND DATE_FORMAT(fecha_pago, '%Y-%m')=?");
            $stLiq->execute([$pId, $ym]);
            $liquidadoMes = (float)$stLiq->fetchColumn();
            $saldoPendiente = max(0, $gananciaMes - $liquidadoMes);

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

            // Pagos de Canon al Dueño & Estadísticas de Días del Coach
            $stProfRow = $pdo->prepare("SELECT canon_mensual, dia_pago_canon FROM profesores WHERE id = ?");
            $stProfRow->execute([$pId]);
            $profRow = $stProfRow->fetch() ?: [];
            $canonFijo = (float)($profRow['canon_mensual'] ?? 0);
            $diaPagoCanon = (int)($profRow['dia_pago_canon'] ?? 10);
            
            $canonPactado = 0.0;
            if ($tipoRem === 'canon_alquiler') {
                $canonPactado = $canonFijo;
            } elseif ($tipoRem === 'porcentaje') {
                if ($canonFijo > 0) {
                    $canonPactado = $canonFijo;
                } elseif ($recaudadoMes > 0 && $pctComision > 0 && $pctComision < 100) {
                    $canonPactado = round($recaudadoMes * ((100 - $pctComision) / 100), 2);
                } else {
                    $canonPactado = $canonFijo;
                }
            } else {
                $canonPactado = $canonFijo;
            }

            $stCanonPag = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE tipo = 'coach_a_dueno' AND profesor_id = ? AND DATE_FORMAT(fecha_pago, '%Y-%m') = ?");
            $stCanonPag->execute([$pId, $ym]);
            $canonPagadoMes = (float)$stCanonPag->fetchColumn();
            $saldoCanon = max(0, $canonPactado - $canonPagadoMes);

            $stDiasAct = $pdo->prepare("SELECT COUNT(DISTINCT fecha) FROM asistencias WHERE coach_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?");
            $stDiasAct->execute([$pId, $ym]);
            $diasActivosCoach = (int)$stDiasAct->fetchColumn();

            $stTotClases = $pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE coach_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?");
            $stTotClases->execute([$pId, $ym]);
            $totalClasesCoach = (int)$stTotClases->fetchColumn();

            $stPagosDueno = $pdo->prepare("SELECT * FROM pagos WHERE tipo = 'coach_a_dueno' AND profesor_id = ? ORDER BY fecha_pago DESC, id DESC LIMIT 15");
            $stPagosDueno->execute([$pId]);
            $pagosAlDueno = $stPagosDueno->fetchAll();

            $stLiqDueno = $pdo->prepare("SELECT * FROM pagos WHERE tipo = 'profesor' AND profesor_id = ? ORDER BY fecha_pago DESC, id DESC LIMIT 15");
            $stLiqDueno->execute([$pId]);
            $liquidacionesDueno = $stLiqDueno->fetchAll();

            $stCobrosAlu = $pdo->prepare("
                SELECT pa.*, al.nombre AS alumno_nombre, al.plan AS alumno_plan 
                FROM pagos pa
                JOIN alumnos al ON al.id = pa.alumno_id
                WHERE (pa.profesor_id = ? OR (al.profesor_id = ? AND (al.profesor_asignado_en IS NULL OR pa.fecha_pago >= al.profesor_asignado_en)))
                  AND pa.tipo = 'alumno'
                ORDER BY pa.fecha_pago DESC, pa.id DESC
                LIMIT 20
            ");
            $stCobrosAlu->execute([$pId, $pId]);
            $cobrosAlumnos = $stCobrosAlu->fetchAll();

            jsonOut(true, [
                'role' => ROLE_COACH,
                'totales' => [
                    'alumnos'             => $totalAlu,
                    'alumnos_activos'     => $activos,
                    'alumnos_vencidos'    => $vencAlu,
                    'recaudado_alumnos'   => $recaudadoMes,
                    'alumnos_pagaron'     => $alumnosPagaronMes,
                    'ganancia_mes'        => $gananciaMes,
                    'ganancia_mes_prev'   => $gananciaMesPrev,
                    'variacion_pct'       => $varGanancia,
                    'liquidado_mes'       => $liquidadoMes,
                    'saldo_pendiente'     => $saldoPendiente,
                    'asistencias_semana'  => $asistSemana,
                    'tipo_remuneracion'   => $tipoRem,
                    'cuota_mensual'       => $cuotaSueldo,
                    'porcentaje_comision' => $pctComision,
                    'monto_por_alumno'    => $montoPorAlu,
                    'canon_mensual'       => $canonPactado,
                    'dia_pago_canon'      => $diaPagoCanon,
                    'canon_pagado_mes'    => $canonPagadoMes,
                    'saldo_canon'         => $saldoCanon,
                    'dias_activos_mes'    => $diasActivosCoach,
                    'total_clases_mes'    => $totalClasesCoach
                ],
                'pagos_al_dueno'       => $pagosAlDueno,
                'liquidaciones_dueno'  => $liquidacionesDueno,
                'cobros_alumnos'       => $cobrosAlumnos,
                'prox_vencimientos'    => $prox
            ]);

        } else { // ROLE_ALUMNO
            $aId = $alumnoId ?: 0;
            $stAlu = $pdo->prepare("
                SELECT a.*, p.nombre AS coach_nombre, p.telefono AS coach_tel,
                       g.nombre AS gimnasio_nombre, g.telefono AS gym_tel, g.plan_tipo AS gimnasio_plan_tipo,
                       DATEDIFF(a.fecha_vencimiento, CURDATE()) AS dias_restantes
                FROM alumnos a
                LEFT JOIN profesores p ON p.id = a.profesor_id
                LEFT JOIN gimnasios g ON g.id = a.gimnasio_id
                WHERE a.id = ?
            ");
            $stAlu->execute([$aId]);
            $aluData = $stAlu->fetch() ?: [];

            $cuota = planPrice($pdo, $aluData['plan'] ?? '3x');

            $stAbon = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE tipo='alumno' AND alumno_id=? AND DATE_FORMAT(fecha_pago, '%Y-%m')=?");
            $stAbon->execute([$aId, $ym]);
            $abonadoMes = (float)$stAbon->fetchColumn();
            $saldoDeuda = max(0, $cuota - $abonadoMes);
            $diasRestantes = (int)($aluData['dias_restantes'] ?? 0);
            $estaVencido = ($aluData['estado'] ?? '') === 'vencido' || ($saldoDeuda > 0 && $diasRestantes < 0);

            $stAsisMes = $pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE alumno_id=? AND DATE_FORMAT(fecha, '%Y-%m')=?");
            $stAsisMes->execute([$aId, $ym]);
            $totalAsistenciasMes = (int)$stAsisMes->fetchColumn();

            $stAsisList = $pdo->prepare("SELECT fecha, hora, observaciones FROM asistencias WHERE alumno_id=? ORDER BY fecha DESC, hora DESC LIMIT 10");
            $stAsisList->execute([$aId]);
            $historialAsistencias = $stAsisList->fetchAll();

            // Buscar checkins de rutinas realizadas por el alumno
            $stCheckins = $pdo->prepare("
                SELECT rc.*, p.nombre AS coach_nombre
                FROM rutinas_checkins rc
                LEFT JOIN alumnos a ON a.id = rc.alumno_id
                LEFT JOIN profesores p ON p.id = a.profesor_id
                WHERE rc.alumno_id = ?
                ORDER BY rc.fecha DESC, rc.hora DESC
                LIMIT 25
            ");
            $stCheckins->execute([$aId]);
            $checkinsRutina = $stCheckins->fetchAll();

            $stTotChM = $pdo->prepare("SELECT COUNT(*) FROM rutinas_checkins WHERE alumno_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?");
            $stTotChM->execute([$aId, $ym]);
            $totalCheckinsMes = (int)$stTotChM->fetchColumn();

            $stDiasEnt = $pdo->prepare("SELECT DISTINCT fecha FROM rutinas_checkins WHERE alumno_id = ? ORDER BY fecha DESC LIMIT 30");
            $stDiasEnt->execute([$aId]);
            $diasEntrenadosList = $stDiasEnt->fetchAll(PDO::FETCH_COLUMN);

            // Calcular Racha de Días Consecutivos
            $racha = 0;
            $checkDate = new DateTime();
            $fechasSet = array_flip($diasEntrenadosList);
            // Check if trained today or yesterday
            $todayStr = $checkDate->format('Y-m-d');
            $yesterdayStr = (clone $checkDate)->modify('-1 day')->format('Y-m-d');
            if (isset($fechasSet[$todayStr]) || isset($fechasSet[$yesterdayStr])) {
                if (!isset($fechasSet[$todayStr])) {
                    $checkDate->modify('-1 day');
                }
                while (isset($fechasSet[$checkDate->format('Y-m-d')])) {
                    $racha++;
                    $checkDate->modify('-1 day');
                }
            }

            // Buscar programa estructurado activo del alumno
            $stProgAlu = $pdo->prepare("
                SELECT p.*, pr.nombre AS coach_nombre, pr.telefono AS coach_tel 
                FROM rutinas_programas p 
                LEFT JOIN profesores pr ON pr.id = p.coach_id 
                WHERE p.alumno_id = ? AND p.estado = 'activa' 
                ORDER BY p.id DESC LIMIT 1
            ");
            $stProgAlu->execute([$aId]);
            $progActivo = $stProgAlu->fetch();

            if ($progActivo) {
                $stDias = $pdo->prepare("SELECT * FROM rutinas_dias WHERE programa_id = ? ORDER BY numero_dia ASC, orden ASC");
                $stDias->execute([$progActivo['id']]);
                $diasProg = $stDias->fetchAll();
                foreach ($diasProg as &$d) {
                    $stEj = $pdo->prepare("
                        SELECT re.*, ec.nombre AS ejercicio_nombre, ec.grupo_muscular, ec.descripcion AS ejercicio_desc
                        FROM rutinas_ejercicios re
                        JOIN ejercicios_catalogo ec ON ec.id = re.ejercicio_id
                        WHERE re.dia_id = ?
                        ORDER BY FIELD(re.bloque, 'calentamiento', 'principal', 'cardio', 'vuelta_calma'), re.orden ASC, re.id ASC
                    ");
                    $stEj->execute([$d['id']]);
                    $d['ejercicios'] = $stEj->fetchAll();
                }
                unset($d);
                $progActivo['dias'] = $diasProg;
            }

            $stRut = $pdo->prepare("SELECT * FROM rutinas WHERE alumno_id=? AND estado='activa' ORDER BY id DESC LIMIT 1");
            $stRut->execute([$aId]);
            $rutinaLegacy = $stRut->fetch();

            $stNut = $pdo->prepare("SELECT * FROM planes_nutricionales WHERE alumno_id=? AND estado='activo' ORDER BY id DESC LIMIT 1");
            $stNut->execute([$aId]);
            $planNutri = $stNut->fetch();

            $stPagos = $pdo->prepare("SELECT * FROM pagos WHERE tipo='alumno' AND alumno_id=? ORDER BY fecha_pago DESC, id DESC LIMIT 20");
            $stPagos->execute([$aId]);
            $misPagos = $stPagos->fetchAll();

            $gymPlanTipo = $aluData['gimnasio_plan_tipo'] ?? 'standard';
            $isGymPlanPro = ($gymPlanTipo === 'pro');

            jsonOut(true, [
                'role'                 => ROLE_ALUMNO,
                'alumno'               => $aluData,
                'is_plan_pro'          => $isGymPlanPro,
                'cuota'                => $cuota,
                'abonado_mes'          => $abonadoMes,
                'saldo_deuda'          => $saldoDeuda,
                'esta_vencido'         => $estaVencido,
                'dias_restantes'       => $diasRestantes,
                'asistencias_mes'      => $totalAsistenciasMes,
                'historial_asistencias'=> $historialAsistencias,
                'rutina_checkins'      => $checkinsRutina,
                'total_checkins_mes'   => $totalCheckinsMes,
                'racha_dias'           => $racha,
                'dias_entrenados'      => $diasEntrenadosList,
                'rutina'               => $rutinaLegacy,
                'programa_activo'      => $progActivo,
                'nutricion'            => $isGymPlanPro ? $planNutri : null,
                'mis_pagos'            => $misPagos
            ]);
        }
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE ALUMNOS (AISLADO POR GYM_ID)
     * ------------------------------------------------------------- */

