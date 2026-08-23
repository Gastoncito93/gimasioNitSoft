<?php
// Módulo API: rutinas

    // 1. Catálogo de Ejercicios
    if ($action === 'catalogo_ejercicios.list' || $action === 'rutinas.catalogo.list') {
        $q     = trim(input('q', ''));
        $grupo = trim(input('grupo', ''));
        $gymDest = $currentGymId ?: 1;

        $sql = "SELECT * FROM ejercicios_catalogo WHERE (gimnasio_id IS NULL OR gimnasio_id = ?)";
        $params = [$gymDest];

        if ($grupo !== '' && $grupo !== 'todos') {
            $sql .= " AND grupo_muscular = ?";
            $params[] = $grupo;
        }

        if ($q !== '') {
            $sql .= " AND (nombre LIKE ? OR descripcion LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        $sql .= " ORDER BY grupo_muscular ASC, nombre ASC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        jsonOut(true, $st->fetchAll());
    }

    if ($action === 'catalogo_ejercicios.save' || $action === 'rutinas.catalogo.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $id          = (int)input('id', 0);
        $nombre      = trim(input('nombre', ''));
        $grupo       = trim(input('grupo_muscular', 'pecho'));
        $descripcion = trim(input('descripcion', ''));
        $gymDest     = $currentGymId ?: 1;

        if ($nombre === '' || strlen($nombre) < 3) {
            jsonOut(false, [], 'El nombre del ejercicio debe tener al menos 3 caracteres.');
        }

        $gruposValidos = ['pecho', 'espalda', 'piernas', 'hombros', 'biceps', 'triceps', 'core', 'cardio', 'cuerpo_completo'];
        if (!in_array($grupo, $gruposValidos, true)) {
            $grupo = 'pecho';
        }

        $normalizar = function($str) {
            $str = mb_strtolower(trim($str), 'UTF-8');
            $str = str_replace(
                ['á','é','í','ó','ú','ä','ë','ï','ö','ü','à','è','ì','ò','ù'],
                ['a','e','i','o','u','a','e','i','o','u','a','e','i','o','u'],
                $str
            );
            return preg_replace('/\s+/', ' ', $str);
        };

        $nombreNorm = $normalizar($nombre);

        $stCheck = $pdo->prepare("SELECT id, nombre, grupo_muscular, gimnasio_id FROM ejercicios_catalogo WHERE (gimnasio_id IS NULL OR gimnasio_id = ?)");
        $stCheck->execute([$gymDest]);
        $existentes = $stCheck->fetchAll();

        foreach ($existentes as $e) {
            if ($id > 0 && (int)$e['id'] === $id) continue;
            if ($normalizar($e['nombre']) === $nombreNorm) {
                $origen = $e['gimnasio_id'] ? 'Personalizado de tu gimnasio' : 'Oficial Gym Pro';
                $grupoUpper = strtoupper($e['grupo_muscular']);
                jsonOut(false, ['duplicado_id' => $e['id']], "El ejercicio '{$e['nombre']}' ya existe en el catálogo ({$origen} • {$grupoUpper}). No se permiten ejercicios duplicados.");
            }
        }

        if ($id > 0) {
            $pdo->prepare("UPDATE ejercicios_catalogo SET nombre=?, grupo_muscular=?, descripcion=? WHERE id=? AND (gimnasio_id=? OR ?=1)")
                ->execute([$nombre, $grupo, $descripcion, $id, $gymDest, $isSuperAdmin ? 1 : 0]);
        } else {
            $pdo->prepare("INSERT INTO ejercicios_catalogo (gimnasio_id, nombre, grupo_muscular, descripcion) VALUES (?, ?, ?, ?)")
                ->execute([$gymDest, $nombre, $grupo, $descripcion]);
            $id = (int)$pdo->lastInsertId();
        }
        jsonOut(true, ['id' => $id], 'Ejercicio guardado en el catálogo exitosamente');
    }

    if ($action === 'catalogo_ejercicios.delete' || $action === 'rutinas.catalogo.delete') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $id = (int)input('id', 0);
        $gymDest = $currentGymId ?: 1;

        $st = $pdo->prepare("DELETE FROM ejercicios_catalogo WHERE id = ? AND (gimnasio_id = ? OR ?=1)");
        $st->execute([$id, $gymDest, $isSuperAdmin ? 1 : 0]);
        jsonOut(true, [], 'Ejercicio eliminado del catálogo');
    }

    // 2. Programas / Plantillas (Listado)
    if ($action === 'rutinas.programas.list' || $action === 'rutinas.list') {
        $tipo    = input('tipo', 'todas'); // 'plantillas', 'asignadas', 'todas'
        $aluId   = (int)input('alumno_id', 0);
        $gymDest = $currentGymId ?: 1;

        $sql = "
            SELECT p.*,
                   al.nombre AS alumno_nombre, al.dni AS alumno_dni, al.telefono AS alumno_tel,
                   al.profesor_id AS alumno_profesor_id,
                   pr.nombre AS coach_nombre,
                   (SELECT COUNT(*) FROM rutinas_dias WHERE programa_id = p.id) AS dias_reales,
                   (SELECT COUNT(*) FROM rutinas_ejercicios re JOIN rutinas_dias rd ON rd.id = re.dia_id WHERE rd.programa_id = p.id) AS total_ejercicios,
                   (SELECT COUNT(*) FROM rutinas_programas cl WHERE cl.plantilla_origen_id = p.id) AS total_clonada,
                   (SELECT COUNT(DISTINCT cl.alumno_id) FROM rutinas_programas cl WHERE cl.plantilla_origen_id = p.id AND cl.estado = 'activa') AS alumnos_asignados_count
            FROM rutinas_programas p
            LEFT JOIN alumnos al ON al.id = p.alumno_id
            LEFT JOIN profesores pr ON pr.id = p.coach_id
            WHERE 1=1
        ";
        $params = [];

        if ($currentGymId) {
            $sql .= " AND (p.gimnasio_id = ? OR (p.es_plantilla = 1 AND p.gimnasio_id IS NULL))";
            $params[] = $currentGymId;
        }

        if (hasRole(ROLE_ALUMNO)) {
            $sql .= " AND p.alumno_id = ?";
            $params[] = $alumnoId ?: 0;
        } elseif (hasRole(ROLE_COACH)) {
            $profIdSafe = (int)($profesorId ?: 0);
            if ($aluId > 0) {
                $sql .= " AND p.alumno_id = ? AND (p.coach_id = ? OR al.profesor_id = ?)";
                $params[] = $aluId;
                $params[] = $profIdSafe;
                $params[] = $profIdSafe;
            } else {
                if ($tipo === 'plantillas') {
                    // Coach solo ve las plantillas maestras del gym (coach_id IS NULL o 0) O sus propias plantillas
                    $sql .= " AND p.es_plantilla = 1 AND (p.coach_id IS NULL OR p.coach_id = 0 OR p.coach_id = ?)";
                    $params[] = $profIdSafe;
                } elseif ($tipo === 'asignadas') {
                    // Coach solo ve rutinas asignadas a sus alumnos
                    $sql .= " AND p.es_plantilla = 0 AND (p.coach_id = ? OR al.profesor_id = ?)";
                    $params[] = $profIdSafe;
                    $params[] = $profIdSafe;
                } else {
                    $sql .= " AND (
                        (p.es_plantilla = 1 AND (p.coach_id IS NULL OR p.coach_id = 0 OR p.coach_id = ?))
                        OR 
                        (p.es_plantilla = 0 AND (p.coach_id = ? OR al.profesor_id = ?))
                    )";
                    $params[] = $profIdSafe;
                    $params[] = $profIdSafe;
                    $params[] = $profIdSafe;
                }
            }
        } else {
            if ($tipo === 'plantillas') {
                $sql .= " AND p.es_plantilla = 1";
            } elseif ($tipo === 'asignadas') {
                $sql .= " AND p.es_plantilla = 0 AND p.alumno_id IS NOT NULL";
            }
            if ($aluId > 0) {
                $sql .= " AND p.alumno_id = ?";
                $params[] = $aluId;
            }
        }

        $sql .= " ORDER BY p.es_plantilla DESC, p.id DESC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        jsonOut(true, $st->fetchAll());
    }

    if ($action === 'rutinas.programas.get') {
        $progId = (int)input('id', 0);
        if (!$progId) jsonOut(false, [], 'ID de programa inválido');

        // Programa Header
        $stProg = $pdo->prepare("
            SELECT p.*, al.nombre AS alumno_nombre, al.dni AS alumno_dni, al.telefono AS alumno_tel, pr.nombre AS coach_nombre
            FROM rutinas_programas p
            LEFT JOIN alumnos al ON al.id = p.alumno_id
            LEFT JOIN profesores pr ON pr.id = p.coach_id
            WHERE p.id = ? LIMIT 1
        ");
        $stProg->execute([$progId]);
        $prog = $stProg->fetch();
        if (!$prog) jsonOut(false, [], 'Programa no encontrado');

        // Días de Sesión
        $stDias = $pdo->prepare("SELECT * FROM rutinas_dias WHERE programa_id = ? ORDER BY numero_dia ASC, orden ASC");
        $stDias->execute([$progId]);
        $dias = $stDias->fetchAll();

        // Ejercicios por Día
        foreach ($dias as &$dia) {
            $stEj = $pdo->prepare("
                SELECT re.*, ec.nombre AS ejercicio_nombre, ec.grupo_muscular, ec.descripcion AS ejercicio_desc
                FROM rutinas_ejercicios re
                JOIN ejercicios_catalogo ec ON ec.id = re.ejercicio_id
                WHERE re.dia_id = ?
                ORDER BY FIELD(re.bloque, 'calentamiento', 'principal', 'cardio', 'vuelta_calma'), re.orden ASC, re.id ASC
            ");
            $stEj->execute([$dia['id']]);
            $dia['ejercicios'] = $stEj->fetchAll();
        }
        unset($dia);

        $prog['dias'] = $dias;
        jsonOut(true, $prog);
    }

    // 4. Guardar / Crear Programa
    if ($action === 'rutinas.programas.save' || $action === 'rutinas.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $id          = (int)input('id', 0);
        $titulo      = trim(input('titulo', ''));
        $objetivo    = trim(input('objetivo', 'Hipertrofia'));
        $nivel       = input('nivel', 'intermedio');
        $diasCount   = max(1, min(7, (int)input('dias_count', 3)));
        $descripcion = trim(input('descripcion', ''));
        $esPlantilla = (int)input('es_plantilla', 1);
        $aluId       = !empty(input('alumno_id')) ? (int)input('alumno_id') : null;
        $coachId     = hasRole(ROLE_COACH) ? $profesorId : ((int)input('coach_id', 0) ?: null);
        $gymDest     = $currentGymId ?: 1;

        if ($titulo === '') {
            jsonOut(false, [], 'El título del programa es obligatorio.');
        }

        if ($id > 0) {
            $stCheck = $pdo->prepare("SELECT * FROM rutinas_programas WHERE id = ? LIMIT 1");
            $stCheck->execute([$id]);
            $progExistente = $stCheck->fetch();
            if (!$progExistente) jsonOut(false, [], 'Programa no encontrado.');

            if (hasRole(ROLE_COACH)) {
                $profIdSafe = (int)($profesorId ?: 0);
                // Si es plantilla maestra del dueño, el coach NO puede editarla directamente
                if ($progExistente['es_plantilla'] == 1 && ($progExistente['coach_id'] === null || (int)$progExistente['coach_id'] === 0 || (int)$progExistente['coach_id'] !== $profIdSafe)) {
                    jsonOut(false, [], 'No podés modificar las plantillas maestras del dueño. Podés crear tu propia plantilla o asignarla a un socio.');
                }
            }

            $pdo->prepare("
                UPDATE rutinas_programas 
                SET titulo=?, objetivo=?, nivel=?, dias_count=?, descripcion=?, es_plantilla=?, alumno_id=?, coach_id=? 
                WHERE id=?
            ")->execute([$titulo, $objetivo, $nivel, $diasCount, $descripcion, $esPlantilla, $aluId, $coachId, $id]);

            // Sincronizar conteo de días si crecieron
            $stDiasAct = $pdo->prepare("SELECT COUNT(*) FROM rutinas_dias WHERE programa_id = ?");
            $stDiasAct->execute([$id]);
            $diasActuales = (int)$stDiasAct->fetchColumn();
            if ($diasCount > $diasActuales) {
                $insDia = $pdo->prepare("INSERT INTO rutinas_dias (programa_id, numero_dia, nombre_dia, enfoque, orden) VALUES (?, ?, ?, ?, ?)");
                for ($i = $diasActuales + 1; $i <= $diasCount; $i++) {
                    $insDia->execute([$id, $i, "Día {$i}", "Sesión {$i}", $i]);
                }
            }
        } else {
            $pdo->prepare("
                INSERT INTO rutinas_programas (gimnasio_id, titulo, objetivo, nivel, dias_count, descripcion, es_plantilla, alumno_id, coach_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$gymDest, $titulo, $objetivo, $nivel, $diasCount, $descripcion, $esPlantilla, $aluId, $coachId]);
            $id = (int)$pdo->lastInsertId();

            // Generar automáticamente las pestañas de días (Día 1, Día 2...)
            $insDia = $pdo->prepare("INSERT INTO rutinas_dias (programa_id, numero_dia, nombre_dia, enfoque, orden) VALUES (?, ?, ?, ?, ?)");
            for ($i = 1; $i <= $diasCount; $i++) {
                $insDia->execute([$id, $i, "Día {$i}", "Sesión {$i}", $i]);
            }
        }

        // Sincronizar con tabla legacy rutinas si es alumno
        if ($aluId) {
            $pdo->prepare("INSERT INTO rutinas (gimnasio_id, alumno_id, coach_id, titulo, objetivo, dias_semana, detalles, fecha_asignacion, estado) VALUES (?,?,?,?,?,?,?,CURDATE(),'activa')")
                ->execute([$gymDest, $aluId, $coachId, $titulo, $objetivo, "{$diasCount} Días", $descripcion ?: 'Rutina personalizada']);
        }

        jsonOut(true, ['id' => $id], 'Programa guardado exitosamente');
    }

    // 5. Eliminar Programa
    if ($action === 'rutinas.programas.delete') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $id = (int)input('id', 0);
        $gymDest = $currentGymId ?: 1;

        $stCheck = $pdo->prepare("SELECT * FROM rutinas_programas WHERE id = ? LIMIT 1");
        $stCheck->execute([$id]);
        $prog = $stCheck->fetch();
        if (!$prog) jsonOut(false, [], 'Programa no encontrado.');

        if (hasRole(ROLE_COACH)) {
            $profIdSafe = (int)($profesorId ?: 0);
            // El coach NO puede eliminar plantillas maestras del dueño
            if ($prog['es_plantilla'] == 1 && ($prog['coach_id'] === null || (int)$prog['coach_id'] === 0 || (int)$prog['coach_id'] !== $profIdSafe)) {
                jsonOut(false, [], 'No tenés permisos para eliminar las plantillas maestras del gimnasio.');
            }
        }

        $pdo->prepare("
            DELETE re FROM rutinas_ejercicios re 
            JOIN rutinas_dias rd ON rd.id = re.dia_id 
            WHERE rd.programa_id = ?
        ")->execute([$id]);
        $pdo->prepare("DELETE FROM rutinas_dias WHERE programa_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM rutinas_programas WHERE id = ? AND (gimnasio_id = ? OR ?=1)")
            ->execute([$id, $gymDest, $isSuperAdmin ? 1 : 0]);
        jsonOut(true, [], 'Programa eliminado correctamente');
    }

    // 6. Guardar Día de Sesión (Enfoque / Nombre)
    if ($action === 'rutinas.dias.save') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $diaId   = (int)input('id', 0);
        $nombre  = trim(input('nombre_dia', ''));
        $enfoque = trim(input('enfoque', ''));

        if (!$diaId) jsonOut(false, [], 'ID de día inválido');

        $pdo->prepare("UPDATE rutinas_dias SET nombre_dia=?, enfoque=? WHERE id=?")
            ->execute([$nombre ?: 'Día', $enfoque, $diaId]);
        jsonOut(true, [], 'Día de entrenamiento actualizado');
    }

    // 7. Añadir Ejercicio Individual a un Día
    if ($action === 'rutinas.ejercicios.add') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $diaId       = (int)input('dia_id', 0);
        $ejercicioId = (int)input('ejercicio_id', 0);
        $bloque      = input('bloque', 'principal'); // 'calentamiento', 'principal', 'cardio', 'vuelta_calma'
        $series      = max(1, (int)input('series', 4));
        $reps        = trim(input('repeticiones', '10-12'));
        $descanso    = !empty(input('descanso_seg')) ? (int)input('descanso_seg') : 60;
        $carga       = trim(input('carga_sugerida', ''));
        $notas       = trim(input('notas', ''));

        if (!$diaId || !$ejercicioId) {
            jsonOut(false, [], 'Día y ejercicio del catálogo son obligatorios.');
        }

        $bloquesValidos = ['calentamiento', 'principal', 'cardio', 'vuelta_calma'];
        if (!in_array($bloque, $bloquesValidos, true)) $bloque = 'principal';

        $stMax = $pdo->prepare("SELECT COALESCE(MAX(orden), 0) FROM rutinas_ejercicios WHERE dia_id = ? AND bloque = ?");
        $stMax->execute([$diaId, $bloque]);
        $maxOrden = (int)$stMax->fetchColumn();

        $ins = $pdo->prepare("
            INSERT INTO rutinas_ejercicios (dia_id, ejercicio_id, bloque, series, repeticiones, descanso_seg, carga_sugerida, notas, orden) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$diaId, $ejercicioId, $bloque, $series, $reps, $descanso, $carga, $notas, $maxOrden + 1]);
        $newId = (int)$pdo->lastInsertId();

        jsonOut(true, ['id' => $newId], 'Ejercicio añadido al día correctamente');
    }

    // 7b. Añadir Múltiples Ejercicios a un Día en Lote (Multi-Select Batch)
    if ($action === 'rutinas.ejercicios.add_batch') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $diaId     = (int)input('dia_id', 0);
        $bloque    = input('bloque', 'principal');
        $ejIds     = input('ejercicios', []);

        if (!$diaId || empty($ejIds)) {
            jsonOut(false, [], 'Día y lista de ejercicios obligatorios.');
        }

        if (!is_array($ejIds)) {
            $ejIds = array_filter(array_map('trim', explode(',', (string)$ejIds)));
        }

        $bloquesValidos = ['calentamiento', 'principal', 'cardio', 'vuelta_calma'];
        if (!in_array($bloque, $bloquesValidos, true)) $bloque = 'principal';

        // Valores por defecto inteligentes según el bloque
        $defaults = [
            'calentamiento' => ['series' => 1, 'reps' => '10 min', 'descanso' => 0,  'carga' => 'Activación'],
            'principal'     => ['series' => 4, 'reps' => '10-12',  'descanso' => 60, 'carga' => 'Moderado'],
            'cardio'        => ['series' => 3, 'reps' => '1 min',  'descanso' => 45, 'carga' => 'HIIT / Ritmo alto'],
            'vuelta_calma'  => ['series' => 3, 'reps' => '40 seg', 'descanso' => 30, 'carga' => 'Estiramiento / Core']
        ];

        $def = $defaults[$bloque] ?? $defaults['principal'];

        $stMax = $pdo->prepare("SELECT COALESCE(MAX(orden), 0) FROM rutinas_ejercicios WHERE dia_id = ? AND bloque = ?");
        $stMax->execute([$diaId, $bloque]);
        $maxOrden = (int)$stMax->fetchColumn();

        $ins = $pdo->prepare("
            INSERT INTO rutinas_ejercicios (dia_id, ejercicio_id, bloque, series, repeticiones, descanso_seg, carga_sugerida, notas, orden) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $addedCount = 0;
        foreach ($ejIds as $rawId) {
            $eId = (int)$rawId;
            if ($eId > 0) {
                $maxOrden++;
                $ins->execute([$diaId, $eId, $bloque, $def['series'], $def['reps'], $def['descanso'], $def['carga'], '', $maxOrden]);
                $addedCount++;
            }
        }

        jsonOut(true, ['count' => $addedCount], "¡Se agregaron {$addedCount} ejercicio(s) al bloque correctamente!");
    }

    // 8. Actualizar Ejercicio de un Día (soporta edición inline en vivo)
    if ($action === 'rutinas.ejercicios.update') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $id = (int)input('id', 0);
        if (!$id) jsonOut(false, [], 'ID de ejercicio inválido');

        // Obtener datos actuales
        $stCur = $pdo->prepare("SELECT * FROM rutinas_ejercicios WHERE id = ?");
        $stCur->execute([$id]);
        $cur = $stCur->fetch();
        if (!$cur) jsonOut(false, [], 'Ejercicio no encontrado');

        $bloque   = input('bloque') !== null ? input('bloque') : $cur['bloque'];
        $series   = input('series') !== null ? max(1, (int)input('series')) : $cur['series'];
        $reps     = input('repeticiones') !== null ? trim(input('repeticiones')) : $cur['repeticiones'];
        $descanso = input('descanso_seg') !== null ? (int)input('descanso_seg') : $cur['descanso_seg'];
        $carga    = input('carga_sugerida') !== null ? trim(input('carga_sugerida')) : $cur['carga_sugerida'];
        $notas    = input('notas') !== null ? trim(input('notas')) : $cur['notas'];

        $pdo->prepare("
            UPDATE rutinas_ejercicios 
            SET bloque=?, series=?, repeticiones=?, descanso_seg=?, carga_sugerida=?, notas=? 
            WHERE id=?
        ")->execute([$bloque, $series, $reps, $descanso, $carga, $notas, $id]);

        jsonOut(true, [], 'Ejercicio actualizado');
    }

    // 9. Quitar Ejercicio de un Día
    if ($action === 'rutinas.ejercicios.delete') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $id = (int)input('id', 0);
        $pdo->prepare("DELETE FROM rutinas_ejercicios WHERE id=?")->execute([$id]);
        jsonOut(true, [], 'Ejercicio quitado del día');
    }

    // 10. Asignar Plantilla a un Socio (Clonación Inteligente Personalizable)
    if ($action === 'rutinas.programas.assign') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $aluId      = (int)input('alumno_id', 0);
        $plantillaId = (int)input('plantilla_id', 0);
        $tituloCust = trim(input('titulo', ''));
        $gymDest    = $currentGymId ?: 1;
        $coachId    = hasRole(ROLE_COACH) ? $profesorId : ((int)input('coach_id', 0) ?: null);

        if (!$aluId || !$plantillaId) {
            jsonOut(false, [], 'Debes seleccionar el alumno y la plantilla base.');
        }

        // Obtener plantilla base
        $stPl = $pdo->prepare("SELECT * FROM rutinas_programas WHERE id = ? LIMIT 1");
        $stPl->execute([$plantillaId]);
        $plantilla = $stPl->fetch();
        if (!$plantilla) jsonOut(false, [], 'Plantilla no encontrada');

        // Desactivar programas anteriores del alumno si los hubiera
        $pdo->prepare("UPDATE rutinas_programas SET estado = 'archivada' WHERE alumno_id = ?")->execute([$aluId]);

        // Crear clon para el alumno
        $titFinal = $tituloCust ?: $plantilla['titulo'];
        $insClon = $pdo->prepare("
            INSERT INTO rutinas_programas (gimnasio_id, plantilla_origen_id, titulo, objetivo, nivel, dias_count, descripcion, es_plantilla, alumno_id, coach_id, estado) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'activa')
        ");
        $insClon->execute([
            $gymDest,
            $plantillaId,
            $titFinal,
            $plantilla['objetivo'],
            $plantilla['nivel'],
            $plantilla['dias_count'],
            $plantilla['descripcion'],
            $aluId,
            $coachId
        ]);
        $newProgId = (int)$pdo->lastInsertId();

        // Clonar Días y Ejercicios
        $stDiasOrig = $pdo->prepare("SELECT * FROM rutinas_dias WHERE programa_id = ? ORDER BY numero_dia ASC");
        $stDiasOrig->execute([$plantillaId]);
        $diasOrig = $stDiasOrig->fetchAll();

        $insDiaClon = $pdo->prepare("INSERT INTO rutinas_dias (programa_id, numero_dia, nombre_dia, enfoque, orden) VALUES (?, ?, ?, ?, ?)");
        $insEjClon  = $pdo->prepare("INSERT INTO rutinas_ejercicios (dia_id, ejercicio_id, bloque, series, repeticiones, descanso_seg, carga_sugerida, notas, orden) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($diasOrig as $do) {
            $insDiaClon->execute([$newProgId, $do['numero_dia'], $do['nombre_dia'], $do['enfoque'], $do['orden']]);
            $newDiaId = (int)$pdo->lastInsertId();

            $stEjOrig = $pdo->prepare("SELECT * FROM rutinas_ejercicios WHERE dia_id = ? ORDER BY orden ASC");
            $stEjOrig->execute([$do['id']]);
            $ejsOrig = $stEjOrig->fetchAll();

            foreach ($ejsOrig as $eo) {
                $insEjClon->execute([
                    $newDiaId,
                    $eo['ejercicio_id'],
                    $eo['bloque'],
                    $eo['series'],
                    $eo['repeticiones'],
                    $eo['descanso_seg'],
                    $eo['carga_sugerida'],
                    $eo['notas'],
                    $eo['orden']
                ]);
            }
        }

        // Sincronizar en tabla legacy
        $pdo->prepare("INSERT INTO rutinas (gimnasio_id, alumno_id, coach_id, titulo, objetivo, dias_semana, detalles, fecha_asignacion, estado) VALUES (?,?,?,?,?,?,?,CURDATE(),'activa')")
            ->execute([$gymDest, $aluId, $coachId, $titFinal, $plantilla['objetivo'], "{$plantilla['dias_count']} Días", "Programa clonado de {$plantilla['titulo']}"]);

        jsonOut(true, ['id' => $newProgId], "¡Programa asignado con éxito a socio! Ya podés personalizarlo.");
    }

    /* -------------------------------------------------------------
     * ENDPOINTS DE NUTRICIÓN (AISLADO POR GYM_ID)
     * ------------------------------------------------------------- */

    if ($action === 'alumnos.checkin_rutina') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH, ROLE_ALUMNO], true);
        $aluId = (int)input('alumno_id', 0) ?: ($alumnoId ?: 0);
        $progId = (int)input('programa_id', 0) ?: null;
        $diaId = (int)input('dia_id', 0) ?: null;
        $rutinaNombre = trim(input('rutina_nombre', 'Rutina de Entrenamiento'));
        $ejerciciosCount = (int)input('ejercicios_completados', 0);
        $duracion = (int)input('duracion_min', 60);
        $esfuerzo = (int)input('nivel_esfuerzo', 3);
        $obs = trim(input('observaciones', ''));
        $gymDest = $currentGymId ?: 1;
        $fecha = input('fecha', hoy());
        $hora = date('H:i:s');

        if (!$aluId) jsonOut(false, [], 'Alumno no identificado');

        $stCoach = $pdo->prepare("SELECT profesor_id FROM alumnos WHERE id = ?");
        $stCoach->execute([$aluId]);
        $coachId = (int)$stCoach->fetchColumn() ?: null;

        $st = $pdo->prepare("
            INSERT INTO rutinas_checkins (gimnasio_id, alumno_id, programa_id, dia_id, rutina_nombre, fecha, hora, ejercicios_completados, duracion_min, nivel_esfuerzo, observaciones)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $st->execute([$gymDest, $aluId, $progId, $diaId, $rutinaNombre, $fecha, $hora, $ejerciciosCount, $duracion, $esfuerzo, $obs]);

        // Registrar asistencia del día si no existe
        $stAsisH = $pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE alumno_id = ? AND fecha = ?");
        $stAsisH->execute([$aluId, $fecha]);
        $asisHoy = (int)$stAsisH->fetchColumn();
        if ($asisHoy == 0) {
            $pdo->prepare("INSERT INTO asistencias (alumno_id, gimnasio_id, coach_id, fecha, hora, observaciones) VALUES (?,?,?,?,?,?)")
                ->execute([$aluId, $gymDest, $coachId, $fecha, $hora, "Check-in: $rutinaNombre"]);
        }

        jsonOut(true, [], '¡Entrenamiento completado y registrado! Tu coach y el gimnasio podrán ver tu constancia.');
    }

    if ($action === 'alumnos.dar_feedback_rutina') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH], true);
        $checkinId = (int)input('checkin_id', 0);
        $feedback = trim(input('coach_feedback', ''));
        if (!$checkinId || $feedback === '') jsonOut(false, [], 'Check-in y mensaje de feedback requeridos');

        $pdo->prepare("UPDATE rutinas_checkins SET coach_feedback = ? WHERE id = ?")->execute([$feedback, $checkinId]);
        jsonOut(true, [], 'Devolución guardada con éxito.');
    }

    if ($action === 'alumnos.historial_rutinas') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO, ROLE_COACH, ROLE_ALUMNO], true);
        $aluId = (int)input('alumno_id', 0) ?: ($alumnoId ?: 0);
        $ym = input('periodo', ymHoy());

        if (!$aluId) jsonOut(false, [], 'Alumno no identificado');

        $st = $pdo->prepare("
            SELECT rc.*, p.nombre AS coach_nombre
            FROM rutinas_checkins rc
            LEFT JOIN alumnos a ON a.id = rc.alumno_id
            LEFT JOIN profesores p ON p.id = a.profesor_id
            WHERE rc.alumno_id = ?
            ORDER BY rc.fecha DESC, rc.hora DESC
            LIMIT 50
        ");
        $st->execute([$aluId]);
        $rows = $st->fetchAll();

        $stTotCh = $pdo->prepare("SELECT COUNT(*) FROM rutinas_checkins WHERE alumno_id = ?");
        $stTotCh->execute([$aluId]);
        $totalCheckins = (int)$stTotCh->fetchColumn();

        $stChMes = $pdo->prepare("SELECT COUNT(*) FROM rutinas_checkins WHERE alumno_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?");
        $stChMes->execute([$aluId, $ym]);
        $checkinsMes = (int)$stChMes->fetchColumn();

        jsonOut(true, [
            'historial'       => $rows,
            'total_checkins'  => $totalCheckins,
            'checkins_mes'    => $checkinsMes
        ]);
    }

    /* --- Precios de Planes y Configuración --- */

