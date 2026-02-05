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

    public function obtener_movimientos_recientes(int $limite = 10): array
    {
        if (!$this->tabla_existe('inventario_movimientos')) {
            return [];
        }

        $campos = [];
        if ($this->columna_existe('inventario_movimientos', 'fecha')) {
            $campos[] = 'fecha';
        }
        if ($this->columna_existe('inventario_movimientos', 'tipo')) {
            $campos[] = 'tipo';
        }
        if ($this->columna_existe('inventario_movimientos', 'created_at')) {
            $campos[] = 'created_at';
        }

        if ($campos === []) {
            return [];
        }

        $orderby = in_array('fecha', $campos, true) ? 'fecha DESC' : 'created_at DESC';
        $sql = 'SELECT ' . implode(', ', $campos) . ' FROM inventario_movimientos ORDER BY ' . $orderby . ' LIMIT :limite';
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private function contar_activos(string $tabla): int
    {
        if (!$this->tabla_existe($tabla) || !$this->columna_existe($tabla, 'estado') || !$this->columna_existe($tabla, 'deleted_at')) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) FROM ' . $tabla . ' WHERE estado = 1 AND deleted_at IS NULL';
        $stmt = $this->db()->query($sql);
        return (int) $stmt->fetchColumn();
    }

    private function tabla_existe(string $tabla): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = :tabla';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['tabla' => $tabla]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function columna_existe(string $tabla, string $columna): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = :tabla
                  AND column_name = :columna';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'tabla' => $tabla,
            'columna' => $columna,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
