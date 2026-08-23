<?php
if (!function_exists('jsonOut')) {
    function jsonOut(bool $ok = true, $data = [], string $msg = ''): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'data' => $data, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('input')) {
    function input(string $k, $d = null) {
        return $_POST[$k] ?? $_GET[$k] ?? $d;
    }
}

if (!function_exists('hoy')) {
    function hoy(): string {
        return (new DateTime())->format('Y-m-d');
    }
}

if (!function_exists('ymHoy')) {
    function ymHoy(): string {
        return (new DateTime())->format('Y-m');
    }
}

if (!function_exists('inicioSemana')) {
    function inicioSemana(): string {
        $d = new DateTime('monday this week');
        return $d->format('Y-m-d');
    }
}

if (!function_exists('finSemana')) {
    function finSemana(): string {
        $d = new DateTime('sunday this week');
        return $d->format('Y-m-d');
    }
}

if (!function_exists('calcVencimiento')) {
    function calcVencimiento(string $base, string $plan): string {
        $d = new DateTime($base);
        $d->modify('+30 days');
        return $d->format('Y-m-d');
    }
}

if (!function_exists('estadoAlumno')) {
    function estadoAlumno(string $venc): string {
        $v = new DateTime($venc);
        $h = new DateTime('today');
        return ($v >= $h) ? 'activo' : 'vencido';
    }
}

if (!function_exists('fmtFecha')) {
    function fmtFecha(?string $f): string {
        if (!$f) return '-';
        $p = explode('-', explode(' ', trim($f))[0]);
        return (count($p) === 3 && strlen($p[0]) === 4) ? "{$p[2]}/{$p[1]}/{$p[0]}" : $f;
    }
}

if (!function_exists('getPlanPrecios')) {
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
}

if (!function_exists('planPrice')) {
    function planPrice(PDO $pdo, string $plan): float {
        $pp = getPlanPrecios($pdo);
        return isset($pp[$plan]) ? (float)$pp[$plan] : 0.0;
    }
}

if (!function_exists('maintainAutoStates')) {
    function maintainAutoStates(PDO $pdo): void {
        $al = $pdo->query("SELECT id, fecha_vencimiento, estado FROM alumnos WHERE estado != 'pausado'")->fetchAll();
        $upd = $pdo->prepare("UPDATE alumnos SET estado=? WHERE id=?");
        $hoy0 = new DateTime('today');

        foreach ($al as $a) {
            if (empty($a['fecha_vencimiento'])) continue;
            $id = (int)$a['id'];
            $est = (new DateTime($a['fecha_vencimiento']) >= $hoy0) ? 'activo' : 'vencido';
            if ($est !== $a['estado']) {
                $upd->execute([$est, $id]);
            }
        }

        // Actualizar estados de suscripción de los gimnasios y sincronizar cuenta del dueño
        $gyms = $pdo->query("SELECT id, dueno_id, suscripcion_vencimiento, suscripcion_estado FROM gimnasios")->fetchAll();
        $updGym = $pdo->prepare("UPDATE gimnasios SET suscripcion_estado=? WHERE id=?");
        $updUser = $pdo->prepare("UPDATE users SET activo=? WHERE id=? OR (gimnasio_id=? AND rol='dueno')");

        foreach ($gyms as $g) {
            $gId = (int)$g['id'];
            $dId = (int)($g['dueno_id'] ?? 0);

            if ($g['suscripcion_estado'] === 'suspendido') {
                $updUser->execute([0, $dId, $gId]);
                continue;
            }

            if (empty($g['suscripcion_vencimiento'])) continue;

            $vencTs = strtotime($g['suscripcion_vencimiento']);
            $diffDias = (int)ceil(($vencTs - time()) / 86400);
            $nuevoEst = 'activo';

            if ($diffDias < 0) {
                $nuevoEst = 'vencido';
                // Si la suscripción venció, se desactiva automáticamente la cuenta del dueño
                $updUser->execute([0, $dId, $gId]);
            } elseif ($diffDias <= 5) {
                $nuevoEst = 'proximo';
                $updUser->execute([1, $dId, $gId]);
            } else {
                $updUser->execute([1, $dId, $gId]);
            }

            if ($nuevoEst !== $g['suscripcion_estado']) {
                $updGym->execute([$nuevoEst, $gId]);
            }
        }
    }
}