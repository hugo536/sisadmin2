<?php
declare(strict_types=1);

class TercerosEmpleadosModel extends Modelo
{
    public function guardar(int $idTercero, array $data, int $userId): void
    {
        $sql = "INSERT INTO terceros_empleados (id_tercero, cargo, area, fecha_ingreso, fecha_cese, estado_laboral, 
                                            tipo_contrato, sueldo_basico, moneda, asignacion_familiar,
                                            tipo_pago, pago_diario, regimen_pensionario, tipo_comision_afp, cuspp, essalud, updated_by)
                VALUES (:id_tercero, :cargo, :area, :fecha_ingreso, :fecha_cese, :estado_laboral, 
                        :tipo_contrato, :sueldo_basico, :moneda, :asignacion_familiar,
                        :tipo_pago, :pago_diario, :regimen_pensionario, :tipo_comision_afp, :cuspp, :essalud, :updated_by)
                ON DUPLICATE KEY UPDATE
                    cargo = VALUES(cargo), area = VALUES(area), 
                    fecha_ingreso = VALUES(fecha_ingreso), fecha_cese = VALUES(fecha_cese),
                    estado_laboral = VALUES(estado_laboral), tipo_contrato = VALUES(tipo_contrato),
                    sueldo_basico = VALUES(sueldo_basico), moneda = VALUES(moneda), asignacion_familiar = VALUES(asignacion_familiar),
                    tipo_pago = VALUES(tipo_pago), pago_diario = VALUES(pago_diario),
                    regimen_pensionario = VALUES(regimen_pensionario), tipo_comision_afp = VALUES(tipo_comision_afp),
                    cuspp = VALUES(cuspp), essalud = VALUES(essalud),
                    updated_by = VALUES(updated_by), updated_at = NOW()";

        $this->db()->prepare($sql)->execute([
            'id_tercero'      => $idTercero,
            'cargo'           => $data['cargo'] ?? null,
            'area'            => $data['area'] ?? null,
            'fecha_ingreso'   => $data['fecha_ingreso'] ?? null,
            'fecha_cese'      => $data['fecha_cese'] ?? null,
            'estado_laboral'  => $data['estado_laboral'] ?? 'activo',
            'tipo_contrato'   => $data['tipo_contrato'] ?? null,
            'sueldo_basico'   => (float)($data['sueldo_basico'] ?? 0),
            'moneda'          => $data['moneda'] ?? 'PEN',
            'asignacion_familiar' => !empty($data['asignacion_familiar']) ? 1 : 0,
            'tipo_pago'       => $data['tipo_pago'] ?? null,
            'pago_diario'     => (float)($data['pago_diario'] ?? 0),
            'regimen_pensionario' => $data['regimen_pensionario'] ?? null,
            'tipo_comision_afp'   => $data['tipo_comision_afp'] ?? null,
            'cuspp'           => $data['cuspp'] ?? null,
            'essalud'         => !empty($data['essalud']) ? 1 : 0,
            'updated_by'      => $userId
        ]);
    }
}
