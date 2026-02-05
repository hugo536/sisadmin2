<?php
declare(strict_types=1);

class DashboardModel extends Modelo
{
    public function obtener_totales(): array
    {
        return [
            'items' => $this->contar_activos('items'),
            'terceros' => $this->contar_activos('terceros'),
            'usuarios' => $this->contar_activos('usuarios'),
        ];
    }

    public function obtener_ultimos_eventos(int $limite = 10): array
    {
        $sql = 'SELECT b.created_at, b.evento, COALESCE(u.usuario, "Sistema") AS usuario
                FROM bitacora_seguridad b
                LEFT JOIN usuarios u ON u.id = b.created_by
                ORDER BY b.created_at DESC
                LIMIT :limite';
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private function contar_activos(string $tabla): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $tabla . ' WHERE estado = 1';
        return (int) $this->db()->query($sql)->fetchColumn();
    }
}
