<?php
// Módulo API: reportes

    if ($action === 'reportes.avanzado') {
        $gymFilterPago = $currentGymId ? " AND pa.gimnasio_id = $currentGymId" : "";
        $gymFilterSimpleAnd = $currentGymId ? " AND gimnasio_id = $currentGymId" : "";

        // 1. SEMANAL (Gráfica de Barras) - Ingresos de Alumnos
        $mon = inicioSemana(); $sun = finSemana();
        $q = $pdo->prepare("SELECT pa.fecha_pago d, SUM(pa.monto) t FROM pagos pa JOIN alumnos al ON al.id = pa.alumno_id WHERE pa.tipo = 'alumno' AND pa.fecha_pago BETWEEN ? AND ?" . $gymFilterPago . " GROUP BY d");
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

        // 2. MENSUAL (Gráfica de Líneas - Progreso 6 Meses) - Ingresos de Alumnos
        $mMes = $pdo->query("
            SELECT DATE_FORMAT(pa.fecha_pago, '%Y-%m') ym, SUM(pa.monto) t
            FROM pagos pa
            JOIN alumnos al ON al.id = pa.alumno_id
            WHERE pa.tipo = 'alumno' AND pa.fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)" . $gymFilterPago . "
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
            WHERE YEAR(fecha_pago) = ?" . $gymFilterSimpleAnd . "
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

