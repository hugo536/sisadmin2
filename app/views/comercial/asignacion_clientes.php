<?php 
    $clientes = $clientes ?? []; 
    $listas_combo = $listas_combo ?? []; 
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-person-lines-fill me-2 text-info"></i> Asignación de Clientes
            </h1>
            <p class="text-muted small mb-0 ms-1">Vincula tus clientes a las listas de precios correspondientes.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="search" id="clienteSearch" class="form-control bg-light" placeholder="Buscar cliente por nombre o RUC...">
                </div>
                <div class="col-md-3 ms-auto text-end">
                    <small class="text-muted fst-italic">Los cambios se guardan automáticamente</small>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover" id="clientesAsignacionTable">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Cliente</th>
                            <th>Documento</th>
                            <th>Ubicación</th>
                            <th style="width: 300px;">Lista de Precios Asignada</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $c): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($c['nombre_completo']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($c['email'] ?? '-'); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($c['numero_documento']); ?></span>
                                </td>
                                <td class="small text-muted">
                                    <?php echo htmlspecialchars($c['distrito'] ?? 'Sin distrito'); ?>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm border-info-subtle js-cambiar-lista" 
                                            data-id-cliente="<?php echo $c['id']; ?>">
                                        <option value="">-- Precio Público (Base) --</option>
                                        <?php foreach ($listas_combo as $l): ?>
                                            <option value="<?php echo $l['id']; ?>" 
                                                <?php echo ($c['id_lista_precios'] == $l['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($l['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <i class="bi bi-check-circle-fill text-success" title="Activo"></i>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white py-3">
            <nav><ul class="pagination pagination-sm mb-0 justify-content-end" id="paginationAsignacion"></ul></nav>
        </div>
    </div>
</div>

<script>
    // Script rápido para manejar el cambio automático (AJAX)
    document.querySelectorAll('.js-cambiar-lista').forEach(select => {
        select.addEventListener('change', function() {
            const clienteId = this.dataset.idCliente;
            const listaId = this.value;
            
            // Aquí iría tu fetch para guardar
            /* fetch('?ruta=comercial/asignacion/guardar', { ... })
            .then(res => res.json())
            .then(data => alert('Asignación actualizada')); 
            */
           console.log(`Cliente ${clienteId} cambiado a Lista ${listaId}`);
           
           // Feedback visual temporal
           this.classList.add('bg-success-subtle');
           setTimeout(() => this.classList.remove('bg-success-subtle'), 1000);
        });
    });
</script>