<?php
declare(strict_types=1);

class CajasBancosModel extends Modelo
{
    /**
     * Obtiene todas las cajas, bancos y billeteras activos para usarlos en selectores dinámicos
     */
    public function listarActivos(): array
    {
        $sql = 'SELECT id, codigo, nombre, tipo, entidad, tipo_cuenta, moneda 
                FROM configuracion_cajas_bancos 
                WHERE estado = 1 AND deleted_at IS NULL 
                ORDER BY tipo ASC, nombre ASC';
                
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarConFiltros(array $filtros): array
    {
        $where = ['1=1'];
        $params = [];

        $busqueda = trim((string) ($filtros['q'] ?? ''));
        if ($busqueda !== '') {
            // Asignamos un nombre único a cada marcador
            $where[] = '(codigo LIKE :q1 OR nombre LIKE :q2 OR entidad LIKE :q3 OR titular LIKE :q4)';
            
            // Pasamos el valor para cada uno de los marcadores
            $valorBusqueda = '%' . $busqueda . '%';
            $params['q1'] = $valorBusqueda;
            $params['q2'] = $valorBusqueda;
            $params['q3'] = $valorBusqueda;
            $params['q4'] = $valorBusqueda;
        }

        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        if ($tipo !== '') {
            $where[] = 'tipo = :tipo';
            $params['tipo'] = $tipo;
        }

        $estadoFiltro = (string) ($filtros['estado_filtro'] ?? 'activos');
        if ($estadoFiltro === 'eliminados') {
            $where[] = 'deleted_at IS NOT NULL';
        } elseif ($estadoFiltro === 'todos') {
            // sin filtro adicional
        } else {
            $where[] = 'deleted_at IS NULL';
            $where[] = $estadoFiltro === 'inactivos' ? 'estado = 0' : 'estado = 1';
        }

        $orderBy = 'nombre ASC';
        $sql = 'SELECT * FROM configuracion_cajas_bancos WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy;
        
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params); // ¡Aquí ya no te dará error!

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function existeCodigo(string $codigo, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM configuracion_cajas_bancos WHERE codigo = :codigo';
        $params = ['codigo' => $codigo];

        if ($ignorarId !== null && $ignorarId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignorarId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function crear(array $data): bool
    {
        $sql = 'INSERT INTO configuracion_cajas_bancos
                (codigo, nombre, tipo, entidad, tipo_cuenta, moneda, titular, numero_cuenta, permite_cobros, permite_pagos, estado, observaciones, created_by, updated_by)
                VALUES
                (:codigo, :nombre, :tipo, :entidad, :tipo_cuenta, :moneda, :titular, :numero_cuenta, :permite_cobros, :permite_pagos, :estado, :observaciones, :created_by, :updated_by)';

        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'entidad' => $data['entidad'],
            'tipo_cuenta' => $data['tipo_cuenta'],
            'moneda' => $data['moneda'],
            'titular' => $data['titular'],
            'numero_cuenta' => $data['numero_cuenta'],
            'permite_cobros' => $data['permite_cobros'],
            'permite_pagos' => $data['permite_pagos'],
            'estado' => $data['estado'],
            'observaciones' => $data['observaciones'],
            'created_by' => $data['user_id'],
            'updated_by' => $data['user_id'],
        ]);
    }

    public function actualizar(int $id, array $data): bool
    {
        $sql = 'UPDATE configuracion_cajas_bancos
                SET codigo = :codigo,
                    nombre = :nombre,
                    tipo = :tipo,
                    entidad = :entidad,
                    tipo_cuenta = :tipo_cuenta,
                    moneda = :moneda,
                    titular = :titular,
                    numero_cuenta = :numero_cuenta,
                    permite_cobros = :permite_cobros,
                    permite_pagos = :permite_pagos,
                    estado = :estado,
                    observaciones = :observaciones,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id';

        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'entidad' => $data['entidad'],
            'tipo_cuenta' => $data['tipo_cuenta'],
            'moneda' => $data['moneda'],
            'titular' => $data['titular'],
            'numero_cuenta' => $data['numero_cuenta'],
            'permite_cobros' => $data['permite_cobros'],
            'permite_pagos' => $data['permite_pagos'],
            'estado' => $data['estado'],
            'observaciones' => $data['observaciones'],
            'updated_by' => $data['user_id'],
        ]);
    }

    public function eliminarLogico(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare('UPDATE configuracion_cajas_bancos
            SET deleted_at = NOW(), deleted_by = :deleted_by, estado = 0, updated_by = :updated_by, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');

        return $stmt->execute([
            'id' => $id,
            'deleted_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function restaurar(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare('UPDATE configuracion_cajas_bancos
            SET deleted_at = NULL, deleted_by = NULL, updated_by = :updated_by, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NOT NULL');

        return $stmt->execute([
            'id' => $id,
            'updated_by' => $userId,
        ]);
    }

    public function actualizarEstado(int $id, int $estado, int $userId): bool
    {
        $stmt = $this->db()->prepare('UPDATE configuracion_cajas_bancos
            SET estado = :estado, updated_by = :updated_by, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');

        return $stmt->execute([
            'id' => $id,
            'estado' => $estado,
            'updated_by' => $userId,
        ]);
    }
}