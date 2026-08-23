<?php
// Módulo API: usuarios

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
        if ($userRole === ROLE_DUENO) {
            $sql .= " AND u.gimnasio_id = ? AND u.is_superadmin = 0 AND u.rol IN ('coach', 'alumno', 'dueno')";
            $p[] = $currentGymId;
        } elseif ($currentGymId) {
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

        if ($user === '') jsonOut(false, [], 'Nombre de usuario obligatorio');

        // Dueño solo puede crear/editar coach y alumno de su gimnasio
        if ($userRole === ROLE_DUENO && !in_array($rol, [ROLE_COACH, ROLE_ALUMNO], true)) {
            $rol = ROLE_ALUMNO;
        }

        if ($id > 0) {
            $stCheck = $pdo->prepare("SELECT id, rol, is_superadmin, gimnasio_id FROM users WHERE id = ? LIMIT 1");
            $stCheck->execute([$id]);
            $target = $stCheck->fetch();
            if (!$target) jsonOut(false, [], 'Usuario no encontrado');

            if ($userRole === ROLE_DUENO) {
                if ((int)$target['gimnasio_id'] !== (int)$currentGymId || $target['is_superadmin'] == 1 || $target['rol'] === ROLE_ADMIN_GENERAL) {
                    jsonOut(false, [], 'No tenés permisos para editar este usuario.');
                }
            }

            if ($pass !== '') {
                $passVal = validatePasswordStrength($pass);
                if (!$passVal['ok']) {
                    jsonOut(false, [], $passVal['mensaje']);
                }
                $hash = hashPassword($pass);
                $pdo->prepare("UPDATE users SET nombre_usuario=?, email=?, telefono=?, password_hash=?, rol=?, profesor_id=?, alumno_id=?, activo=? WHERE id=?")
                    ->execute([$user, $email, $tel, $hash, $rol, $profId, $aluId, $activo, $id]);
            } else {
                $pdo->prepare("UPDATE users SET nombre_usuario=?, email=?, telefono=?, rol=?, profesor_id=?, alumno_id=?, activo=? WHERE id=?")
                    ->execute([$user, $email, $tel, $rol, $profId, $aluId, $activo, $id]);
            }
        } else {
            $passVal = validatePasswordStrength($pass);
            if (!$passVal['ok']) {
                jsonOut(false, [], $passVal['mensaje']);
            }
            $hash = hashPassword($pass);
            $pdo->prepare("INSERT INTO users (nombre_usuario, email, telefono, password_hash, rol, gimnasio_id, profesor_id, alumno_id, activo) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$user, $email, $tel, $hash, $rol, $gymDest, $profId, $aluId, $activo]);
        }
        jsonOut(true, [], 'Usuario guardado exitosamente');
    }

    if ($action === 'usuarios.toggle_status') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $targetUserId = (int)input('user_id', 0);
        $nuevoEstado  = (int)input('activo', 1); // 1 = habilitado, 0 = bloqueado

        if (!$targetUserId) jsonOut(false, [], 'ID de usuario no especificado.');

        $stUser = $pdo->prepare("SELECT id, nombre_usuario, rol, is_superadmin, gimnasio_id FROM users WHERE id = ? LIMIT 1");
        $stUser->execute([$targetUserId]);
        $target = $stUser->fetch();
        if (!$target) jsonOut(false, [], 'El usuario no existe.');

        // Nadie puede bloquear su propia cuenta actual de sesión
        if ($targetUserId === (int)$_SESSION['user_id']) {
            jsonOut(false, [], 'No podés bloquear tu propia cuenta de sesión actual.');
        }

        // Si es Dueño:
        if ($userRole === ROLE_DUENO) {
            if ((int)$target['gimnasio_id'] !== (int)$currentGymId) {
                jsonOut(false, [], 'No tenés permisos para gestionar usuarios de otra sede.');
            }
            if ($target['is_superadmin'] == 1 || in_array($target['rol'], [ROLE_ADMIN_GENERAL, ROLE_DUENO], true)) {
                jsonOut(false, [], 'Como dueño no tenés permisos para bloquear cuentas de administradores o dueños.');
            }
            if (!in_array($target['rol'], [ROLE_COACH, ROLE_ALUMNO], true)) {
                jsonOut(false, [], 'Solo podés habilitar o bloquear cuentas de coaches y alumnos.');
            }
        }

        // Si es SuperAdmin: tiene control total sobre todos los usuarios y sedes

        $pdo->prepare("UPDATE users SET activo = ? WHERE id = ?")->execute([$nuevoEstado, $targetUserId]);

        $estadoTxt = $nuevoEstado === 1 ? 'habilitada y activada' : 'bloqueada y suspendida';
        jsonOut(true, ['user_id' => $targetUserId, 'activo' => $nuevoEstado], "La cuenta de '{$target['nombre_usuario']}' ha sido {$estadoTxt} con éxito.");
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

        $stUser = $pdo->prepare("SELECT id, nombre_usuario, rol, is_superadmin, gimnasio_id FROM users WHERE id = ? LIMIT 1");
        $stUser->execute([$userId]);
        $target = $stUser->fetch();
        if (!$target) jsonOut(false, [], 'Usuario no encontrado');

        if ($userRole === ROLE_DUENO) {
            if ((int)$target['gimnasio_id'] !== (int)$currentGymId) {
                jsonOut(false, [], 'No tenés permisos para gestionar usuarios de otra sede.');
            }
            if ($target['is_superadmin'] == 1 || $target['rol'] === ROLE_ADMIN_GENERAL) {
                jsonOut(false, [], 'No tenés permisos sobre cuentas de Administrador General.');
            }
        }

        $pdo->prepare("UPDATE users SET password_hash = NULL WHERE id = ?")->execute([$userId]);
        jsonOut(true, [], "Contraseña de '{$target['nombre_usuario']}' blanqueada con éxito. Podrá definir su nueva clave al ingresar.");
    }

    if ($action === 'usuarios.generar_temp_pass') {
        requireRole([ROLE_ADMIN_GENERAL, ROLE_DUENO], true);
        $targetUserId = (int)input('user_id', 0);
        $aluId        = (int)input('alumno_id', 0);
        $profId       = (int)input('profesor_id', 0);
        $gymId        = (int)input('gimnasio_id', 0);

        if ($gymId > 0 && !$targetUserId) {
            $st = $pdo->prepare("SELECT dueno_id FROM gimnasios WHERE id = ? LIMIT 1");
            $st->execute([$gymId]);
            $targetUserId = (int)$st->fetchColumn();
        } elseif ($aluId > 0 && !$targetUserId) {
            $st = $pdo->prepare("SELECT id FROM users WHERE alumno_id = ? LIMIT 1");
            $st->execute([$aluId]);
            $targetUserId = (int)$st->fetchColumn();
        } elseif ($profId > 0 && !$targetUserId) {
            $st = $pdo->prepare("SELECT id FROM users WHERE profesor_id = ? LIMIT 1");
            $st->execute([$profId]);
            $targetUserId = (int)$st->fetchColumn();
        }

        if (!$targetUserId) {
            jsonOut(false, [], 'Esta sede o usuario no posee una cuenta de acceso creada.');
        }

        $stUser = $pdo->prepare("
            SELECT u.id, u.nombre_usuario, u.email, u.telefono, u.rol, u.is_superadmin, u.gimnasio_id,
                   g.nombre AS gimnasio_nombre,
                   COALESCE(a.nombre, p.nombre, u.nombre_usuario) AS persona_nombre
            FROM users u
            LEFT JOIN gimnasios g ON g.id = u.gimnasio_id
            LEFT JOIN alumnos a ON a.id = u.alumno_id
            LEFT JOIN profesores p ON p.id = u.profesor_id
            WHERE u.id = ? LIMIT 1
        ");
        $stUser->execute([$targetUserId]);
        $target = $stUser->fetch();
        if (!$target) jsonOut(false, [], 'Usuario no encontrado.');

        // Validación de permisos:
        if ($userRole === ROLE_DUENO) {
            if ((int)$target['gimnasio_id'] !== (int)$currentGymId) {
                jsonOut(false, [], 'No tenés permisos para generar claves en otra sede.');
            }
            if ($target['is_superadmin'] == 1 || in_array($target['rol'], [ROLE_ADMIN_GENERAL, ROLE_DUENO], true)) {
                jsonOut(false, [], 'Como dueño solo podés recuperar contraseñas de tus coaches y alumnos.');
            }
        }

        // Generar contraseña temporal segura: Formato TempXXX!YY (ej: Temp742!XYZ)
        $numPart = str_pad((string)random_int(100, 999), 3, '0', STR_PAD_LEFT);
        $letPart = strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
        $tempPass = 'Temp' . $numPart . '!' . $letPart;

        $tempHash = hashPassword($tempPass);

        // Actualizar usuario: asignar hash temporal, exigir cambio obligatorio (debe_cambiar_password = 1) y asegurar cuenta activa
        $pdo->prepare("UPDATE users SET password_hash = ?, debe_cambiar_password = 1, activo = 1 WHERE id = ?")
            ->execute([$tempHash, $targetUserId]);

        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if (substr($dir, -4) === '/api') {
            $dir = substr($dir, 0, -4);
        }
        $loginUrl = "{$scheme}://{$host}{$dir}/login.php";

        $gymNom = $target['gimnasio_nombre'] ?: 'NITSOFT GIMNASIO';
        $waMsg = "¡Hola {$target['persona_nombre']}! Se ha generado tu acceso para {$gymNom}:\n\n" .
                 "👤 *Usuario:* {$target['nombre_usuario']}\n" .
                 "🔑 *Contraseña Temporal:* `{$tempPass}`\n\n" .
                 "👉 *Ingresá en:* {$loginUrl}\n\n" .
                 "ℹ️ *Importante:* Al ingresar por primera vez con esta clave temporal, el sistema te solicitará definir tu nueva contraseña definitiva personal.";

        $cleanTel = preg_replace('/\D/', '', $target['telefono'] ?? '');
        $waLink = $cleanTel ? "https://wa.me/{$cleanTel}?text=" . urlencode($waMsg) : "";

        jsonOut(true, [
            'user_id'        => $targetUserId,
            'nombre_usuario' => $target['nombre_usuario'],
            'persona_nombre' => $target['persona_nombre'],
            'telefono'       => $target['telefono'] ?? '',
            'rol'            => $target['rol'],
            'temp_password'  => $tempPass,
            'whatsapp_msg'   => $waMsg,
            'whatsapp_link'  => $waLink
        ], "Contraseña temporal generada con éxito.");
    }

    /* --- Reportes Avanzados: Semanal (Barras), Mensual (Líneas) y Anual (Torta) --- */

