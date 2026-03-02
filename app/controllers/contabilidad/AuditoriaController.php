<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';

class AuditoriaController extends Controlador
{
    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('auditoria.ver');

        $f = [
            'evento' => trim((string)($_GET['evento'] ?? '')),
            'fecha_desde' => trim((string)($_GET['fecha_desde'] ?? date('Y-m-01'))),
            'fecha_hasta' => trim((string)($_GET['fecha_hasta'] ?? date('Y-m-d'))),
        ];

        $where = ['1=1'];
        $params = [];
        if ($f['evento'] !== '') {
            $where[] = 'b.evento LIKE :evento';
            $params['evento'] = '%' . $f['evento'] . '%';
        }
        if ($f['fecha_desde'] !== '') {
            $where[] = 'DATE(b.created_at) >= :fecha_desde';
            $params['fecha_desde'] = $f['fecha_desde'];
        }
        if ($f['fecha_hasta'] !== '') {
            $where[] = 'DATE(b.created_at) <= :fecha_hasta';
            $params['fecha_hasta'] = $f['fecha_hasta'];
        }

        $sql = 'SELECT b.created_at, b.evento, b.descripcion, b.ip_address, u.usuario
                FROM bitacora_seguridad b
                LEFT JOIN usuarios u ON u.id = b.created_by
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY b.created_at DESC
                LIMIT 500';
        $stmt = Conexion::get()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->render('contabilidad/auditoria', [
            'ruta_actual' => 'auditoria/index',
            'filtros' => $f,
            'rows' => $rows,
        ]);
    }

    public function exportar_csv(): void
    {
        AuthMiddleware::handle();
        require_permiso('auditoria.ver');

        $evento = trim((string)($_GET['evento'] ?? ''));
        $where = ['1=1'];
        $params = [];
        if ($evento !== '') {
            $where[] = 'b.evento LIKE :evento';
            $params['evento'] = '%' . $evento . '%';
        }

        $stmt = Conexion::get()->prepare('SELECT b.created_at, b.evento, b.descripcion, b.ip_address, u.usuario
            FROM bitacora_seguridad b LEFT JOIN usuarios u ON u.id = b.created_by
            WHERE ' . implode(' AND ', $where) . ' ORDER BY b.created_at DESC LIMIT 2000');
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="auditoria.csv"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['fecha', 'evento', 'descripcion', 'ip', 'usuario']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['created_at'], $r['evento'], $r['descripcion'], $r['ip_address'], $r['usuario']]);
        }
        fclose($out);
        exit;
    }
}
