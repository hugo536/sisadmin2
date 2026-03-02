<?php
/**
 * =========================================================
 * check.php - ESCÁNER DE DEPURACIÓN PARA BOOTSTRAP 5
 * =========================================================
 * Este script escanea el DOM en busca de errores estructurales
 * que causan "Illegal invocation" o fallos en "index.js".
 */
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    console.log('%c🕵️‍♂️ SISADMIN Debugger Iniciado: Escaneando DOM...', 'background: #0d6efd; color: white; padding: 5px; font-weight: bold; border-radius: 5px;');

    let erroresEncontrados = 0;

    // 1. Buscar Tooltips mal formados (Causa principal de fallos silenciosos)
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(el => {
        if (!el.getAttribute('title') && !el.getAttribute('data-bs-original-title')) {
            console.error('❌ ERROR CRÍTICO: Tooltip sin atributo "title" encontrado. Esto rompe Bootstrap.', el);
            erroresEncontrados++;
        }
    });

    // 2. Buscar Modales incompletos (Falta de .modal-dialog rompe index.js)
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        const dialog = modal.querySelector('.modal-dialog');
        if (!dialog) {
            console.error('❌ ERROR CRÍTICO: Modal sin contenedor ".modal-dialog" en su interior.', modal);
            erroresEncontrados++;
        }
    });

    // 3. Buscar Botones con selectores inválidos (Causa "Illegal invocation" en selector-engine.js)
    const toggles = document.querySelectorAll('[data-bs-toggle="modal"], [data-bs-toggle="collapse"]');
    toggles.forEach(btn => {
        let targetSelector = btn.getAttribute('data-bs-target') || btn.getAttribute('href');
        
        // Target vacío o es solo un "#"
        if (!targetSelector || targetSelector === '#' || targetSelector.trim() === '') {
            console.error('❌ ERROR CRÍTICO: Botón con target vacío o inválido ("#").', btn);
            erroresEncontrados++;
            return;
        }

        // Target con espacios o caracteres ilegales
        try {
            const targetEl = document.querySelector(targetSelector);
            if (!targetEl && targetSelector !== '#') {
                console.warn(`⚠️ ADVERTENCIA: El botón apunta al ID "${targetSelector}", pero ese elemento NO existe en el HTML actual.`, btn);
            }
        } catch (e) {
            console.error(`❌ ERROR CRÍTICO: El botón tiene un selector mal escrito ("${targetSelector}").`, btn);
            erroresEncontrados++;
        }
    });

    if (erroresEncontrados === 0) {
        console.log('%c✅ Escaneo completado: No se encontraron errores de estructura en Bootstrap.', 'color: #198754; font-weight: bold;');
    } else {
        console.log(`%c🛑 Escaneo completado: Se encontraron ${erroresEncontrados} error(es). Revisa los mensajes de arriba.`, 'color: #dc3545; font-weight: bold;');
    }
});
</script>