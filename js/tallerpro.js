/**
 * TallerPro UI JavaScript
 */
if (window.__tallerProLoaded) {
    // Prevent duplicate binding if loaded multiple times
    // (e.g. Dolibarr module auto-load + manual head tag)
} else {
window.__tallerProLoaded = true;

document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('tallerSidebar');
    const collapseBtn = document.getElementById('tallerCollapseBtn');
    const topbarToggle = document.getElementById('tallerTopbarToggle');
    const navItems = document.querySelectorAll('.tallerpro-nav-item');

    // Restore sidebar state from localStorage
    if (localStorage.getItem('tallerpro_sidebar_collapsed') === 'true') {
        sidebar?.classList.add('collapsed');
    }

    // Toggle sidebar collapse
    function toggleSidebar() {
        if (!sidebar) return;
        sidebar.classList.toggle('collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('tallerpro_sidebar_collapsed', isCollapsed ? 'true' : 'false');
    }

    collapseBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        toggleSidebar();
    });

    topbarToggle?.addEventListener('click', function (e) {
        e.preventDefault();
        toggleSidebar();
    });

    // Only handle active state for placeholder items with hash links (#...)
    navItems.forEach(function (item) {
        item.addEventListener('click', function (e) {
            const href = item.getAttribute('href');
            if (!href || href.startsWith('#')) {
                e.preventDefault();
                navItems.forEach(function (el) { el.classList.remove('active'); });
                item.classList.add('active');
            }
        });
    });

    // Generic Modal logic (works for any view with .tallerpro-modal-backdrop)
    const openModalBtns = document.querySelectorAll('#tallerOpenModalBtn, .tallerpro-btn-primary');
    const modals = document.querySelectorAll('.tallerpro-modal-backdrop');

    openModalBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const modal = document.querySelector('.tallerpro-modal-backdrop');
            if (modal) {
                e.preventDefault();
                modal.classList.add('active');
                const firstInput = modal.querySelector('input, select, textarea');
                if (firstInput) firstInput.focus();
            }
        });
    });

    modals.forEach(function (modal) {
        const closeBtns = modal.querySelectorAll('.tallerpro-modal-close, #tallerCloseModalBtn, .tallerpro-btn-cancel, #tallerCancelModalBtn');
        closeBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                modal.classList.remove('active');
            });
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.tallerpro-modal-backdrop.active').forEach(function (m) {
                m.classList.remove('active');
            });
        }
    });

    // Tab switching logic (works for .tallerpro-tab-btn and .tallerpro-tab-pane)
    const tabBtns = document.querySelectorAll('.tallerpro-tab-btn');
    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = btn.getAttribute('data-tab');
            if (!targetId) return;

            // Remove active class from all buttons in same container
            const container = btn.closest('.tallerpro-tabs-container') || document;
            container.querySelectorAll('.tallerpro-tab-btn').forEach(function (b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');

            // Toggle panes
            const panes = document.querySelectorAll('.tallerpro-tab-pane');
            panes.forEach(function (pane) {
                pane.classList.remove('active');
            });
            const targetPane = document.getElementById(targetId);
            if (targetPane) {
                targetPane.classList.add('active');
            }
        });
    });

    // Materiales Modal handling (Create & Edit)
    const matModal = document.getElementById('tallerMaterialModal');
    const matForm = document.getElementById('tallerMaterialForm');
    const matModalTitle = document.getElementById('tallerMaterialModalTitle');
    const matFormAction = document.getElementById('materialFormAction');
    const matFormRowid = document.getElementById('materialFormRowid');

    const addMatBtn = document.getElementById('tallerOpenModalBtn');
    if (addMatBtn && matForm) {
        addMatBtn.addEventListener('click', function () {
            matForm.reset();
            if (matModalTitle) matModalTitle.textContent = 'Nuevo registro';
            if (matFormAction) matFormAction.value = 'create_material';
            if (matFormRowid) matFormRowid.value = '0';
        });
    }

    const editMatBtns = document.querySelectorAll('.btn-edit-material');
    editMatBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!matModal || !matForm) return;

            if (matModalTitle) matModalTitle.textContent = 'Editar registro';
            if (matFormAction) matFormAction.value = 'update_material';
            if (matFormRowid) matFormRowid.value = btn.dataset.id || '0';

            const matNombre = document.getElementById('mat_nombre');
            const matRef = document.getElementById('mat_ref');
            const matProveedor = document.getElementById('mat_proveedor');
            const matStock = document.getElementById('mat_stock');
            const matPrecio = document.getElementById('mat_precio');
            const matNotas = document.getElementById('mat_notas');

            if (matNombre) matNombre.value = btn.dataset.nombre || '';
            if (matRef) matRef.value = btn.dataset.ref || '';
            if (matProveedor) matProveedor.value = btn.dataset.proveedor || '';
            if (matStock) matStock.value = btn.dataset.stock || '0';
            if (matPrecio) matPrecio.value = btn.dataset.precio || '0.00';
            if (matNotas) matNotas.value = btn.dataset.notas || '';

            matModal.classList.add('active');
            if (matNombre) matNombre.focus();
        });
    });

    // Reparaciones Modal handling (Create & Edit)
    const repModal = document.getElementById('tallerReparacionModal');
    const repForm = document.getElementById('tallerReparacionForm');
    const repModalTitle = document.getElementById('tallerReparacionModalTitle');
    const repFormAction = document.getElementById('reparacionFormAction');
    const repFormRowid = document.getElementById('reparacionFormRowid');
    const addRepBtn = document.getElementById('tallerOpenModalBtn');

    if (addRepBtn && repForm) {
        addRepBtn.addEventListener('click', function () {
            repForm.reset();
            if (repModalTitle) repModalTitle.textContent = 'Nuevo registro';
            if (repFormAction) repFormAction.value = 'create_reparacion';
            if (repFormRowid) repFormRowid.value = '0';
            const repFecha = document.getElementById('rep_fecha');
            if (repFecha) {
                repFecha.value = new Date().toISOString().split('T')[0];
            }
        });
    }

    const editRepBtns = document.querySelectorAll('.btn-edit-reparacion');
    editRepBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!repModal || !repForm) return;

            if (repModalTitle) repModalTitle.textContent = 'Editar registro';
            if (repFormAction) repFormAction.value = 'update_reparacion';
            if (repFormRowid) repFormRowid.value = btn.dataset.id || '0';

            const repVehiculo = document.getElementById('rep_fk_vehiculo');
            const repFecha = document.getElementById('rep_fecha');
            const repDescripcion = document.getElementById('rep_descripcion');
            const repCoste = document.getElementById('rep_coste_mo');
            const repNotas = document.getElementById('rep_notas');

            if (repVehiculo) repVehiculo.value = btn.dataset.vehiculo || '';
            if (repFecha) repFecha.value = btn.dataset.fecha || '';
            if (repDescripcion) repDescripcion.value = btn.dataset.descripcion || '';
            if (repCoste) repCoste.value = btn.dataset.coste || '0.00';
            if (repNotas) repNotas.value = btn.dataset.notas || '';

            repModal.classList.add('active');
            if (repVehiculo) repVehiculo.focus();
        });
    });

    // Seguros Modal handling (Create & Edit)
    const segModal = document.getElementById('tallerSeguroModal');
    const segForm = document.getElementById('tallerSeguroForm');
    const segModalTitle = document.getElementById('tallerSeguroModalTitle');
    const segFormAction = document.getElementById('seguroFormAction');
    const segFormRowid = document.getElementById('seguroFormRowid');
    const addSegBtn = document.getElementById('tallerOpenModalBtn');

    if (addSegBtn && segForm) {
        addSegBtn.addEventListener('click', function () {
            segForm.reset();
            if (segModalTitle) segModalTitle.textContent = 'Nuevo registro';
            if (segFormAction) segFormAction.value = 'create_seguro';
            if (segFormRowid) segFormRowid.value = '0';
        });
    }

    const editSegBtns = document.querySelectorAll('.btn-edit-seguro');
    editSegBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!segModal || !segForm) return;

            if (segModalTitle) segModalTitle.textContent = 'Editar registro';
            if (segFormAction) segFormAction.value = 'update_seguro';
            if (segFormRowid) segFormRowid.value = btn.dataset.id || '0';

            const segVehiculo = document.getElementById('seg_fk_vehiculo');
            const segCompania = document.getElementById('seg_compania');
            const segPoliza = document.getElementById('seg_poliza');
            const segTipo = document.getElementById('seg_tipo');
            const segFechaInicio = document.getElementById('seg_fecha_inicio');
            const segFechaFin = document.getElementById('seg_fecha_fin');
            const segTelefono = document.getElementById('seg_telefono');
            const segNotas = document.getElementById('seg_notas');

            if (segVehiculo) segVehiculo.value = btn.dataset.vehiculo || '';
            if (segCompania) segCompania.value = btn.dataset.compania || '';
            if (segPoliza) segPoliza.value = btn.dataset.poliza || '';
            if (segTipo) segTipo.value = btn.dataset.tipo || '';
            if (segFechaInicio) segFechaInicio.value = btn.dataset.fechainicio || '';
            if (segFechaFin) segFechaFin.value = btn.dataset.fechafin || '';
            if (segTelefono) segTelefono.value = btn.dataset.telefono || '';
            if (segNotas) segNotas.value = btn.dataset.notas || '';

            segModal.classList.add('active');
            if (segCompania) segCompania.focus();
        });
    });

    // Facturas Modal handling (Create & Edit)
    const facModal = document.getElementById('tallerFacturaModal');
    const facForm = document.getElementById('tallerFacturaForm');
    const facModalTitle = document.getElementById('tallerFacturaModalTitle');
    const facFormAction = document.getElementById('facturaFormAction');
    const facFormRowid = document.getElementById('facturaFormRowid');
    const addFacBtn = document.getElementById('tallerOpenModalBtn');
    const facNumero = document.getElementById('fac_numero');
    const facFecha = document.getElementById('fac_fecha');

    function updateNextInvoiceNumber(fechaStr) {
        if (!facNumero) return;
        if (facNumero.dataset && facNumero.dataset.nextNumero && (!fechaStr || fechaStr.substring(0, 4) === String(new Date().getFullYear()))) {
            facNumero.value = facNumero.dataset.nextNumero;
        }

        const url = (window.location.pathname || 'facturas.php') + '?action=get_next_factura_numero&fecha=' + encodeURIComponent(fechaStr || '');
        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.status === 'ok' && data.numero) {
                    facNumero.value = data.numero;
                }
            })
            .catch(function (err) {
                console.error('Error al obtener siguiente número de factura:', err);
            });
    }

    if (facFecha) {
        facFecha.addEventListener('change', function () {
            if (facFormAction && facFormAction.value === 'create_factura') {
                updateNextInvoiceNumber(facFecha.value);
            }
        });
    }

    if (addFacBtn && facForm) {
        addFacBtn.addEventListener('click', function () {
            facForm.reset();
            if (facModalTitle) facModalTitle.textContent = 'Nuevo registro';
            if (facFormAction) facFormAction.value = 'create_factura';
            if (facFormRowid) facFormRowid.value = '0';
            const todayStr = new Date().toISOString().split('T')[0];
            if (facFecha) {
                facFecha.value = todayStr;
            }
            const facEstado = document.getElementById('fac_estado');
            if (facEstado) {
                facEstado.value = 'Pendiente';
            }
            if (facNumero) {
                facNumero.readOnly = true;
                updateNextInvoiceNumber(todayStr);
            }
        });
    }

    const editFacBtns = document.querySelectorAll('.btn-edit-factura');
    editFacBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!facModal || !facForm) return;

            if (facModalTitle) facModalTitle.textContent = 'Editar registro';
            if (facFormAction) facFormAction.value = 'update_factura';
            if (facFormRowid) facFormRowid.value = btn.dataset.id || '0';

            const facSoc = document.getElementById('fac_fk_soc');
            const facVehiculo = document.getElementById('fac_fk_vehiculo');
            const facTotal = document.getElementById('fac_total');
            const facEstado = document.getElementById('fac_estado');
            const facNotas = document.getElementById('fac_notas');

            if (facNumero) {
                facNumero.value = btn.dataset.numero || '';
                facNumero.readOnly = true;
            }
            if (facFecha) facFecha.value = btn.dataset.fecha || '';
            if (facSoc) facSoc.value = btn.dataset.soc || '';
            if (facVehiculo) facVehiculo.value = btn.dataset.vehiculo || '';
            if (facTotal) facTotal.value = btn.dataset.total || '0.00';
            if (facEstado) facEstado.value = btn.dataset.estado || 'Pendiente';
            if (facNotas) facNotas.value = btn.dataset.notas || '';

            facModal.classList.add('active');
            if (facFecha) facFecha.focus();
        });
    });

    // Botón "Ver" en la tabla de facturas -> Navega a la vista documental de la factura
    const verFacBtns = document.querySelectorAll('.btn-ver-factura');
    verFacBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const href = btn.getAttribute('href');
            if (href && href !== '#' && href !== 'javascript:void(0);') {
                window.location.href = href;
            }
        });
    });

    // =========================================================================
    // Modal Detalle de Factura (Materiales, Mano de obra, Desglose y Totales)
    // =========================================================================
    const detalleModal = document.getElementById('tallerFacturaDetalleModal');
    const detalleModalTitle = document.getElementById('tallerDetalleModalTitle');
    const closeDetalleModalBtn = document.getElementById('tallerCloseDetalleModalBtn');
    const detalleFkFactura = document.getElementById('detalle_fk_factura');

    // Elementos de Materiales
    const detMatCatalogo = document.getElementById('det_mat_catalogo');
    const detMatCodigo = document.getElementById('det_mat_codigo');
    const detMatDescripcion = document.getElementById('det_mat_descripcion');
    const detMatCantidad = document.getElementById('det_mat_cantidad');
    const detMatPrecio = document.getElementById('det_mat_precio');
    const detMatDescuento = document.getElementById('det_mat_descuento');
    const detMatTotalNeto = document.getElementById('det_mat_total_neto');
    const detMatGuardarCatalogo = document.getElementById('det_mat_guardar_catalogo');
    const btnAddMaterialLine = document.getElementById('btnAddMaterialLine');
    const detMatEmpty = document.getElementById('det_mat_empty');
    const detMatList = document.getElementById('det_mat_list');

    // Elementos de Reparaciones
    const detRepDescripcion = document.getElementById('det_rep_descripcion');
    const detRepPrecio = document.getElementById('det_rep_precio');
    const detRepDescuento = document.getElementById('det_rep_descuento');
    const detRepTotalNeto = document.getElementById('det_rep_total_neto');
    const detRepNotas = document.getElementById('det_rep_notas');
    const btnAddReparacionLine = document.getElementById('btnAddReparacionLine');
    const detRepEmpty = document.getElementById('det_rep_empty');
    const detRepList = document.getElementById('det_rep_list');

    // Elementos de Totales y Desglose
    const detTotDescuentoGlobal = document.getElementById('det_tot_descuento_global');
    const detTotIva = document.getElementById('det_tot_iva');
    const sumMateriales = document.getElementById('sum_materiales');
    const sumReparaciones = document.getElementById('sum_reparaciones');
    const sumSubtotal = document.getElementById('sum_subtotal');
    const sumDescuento = document.getElementById('sum_descuento');
    const sumBaseImponible = document.getElementById('sum_base_imponible');
    const lblIva = document.getElementById('lbl_iva');
    const sumIva = document.getElementById('sum_iva');
    const sumTotal = document.getElementById('sum_total');
    const btnSaveInvoiceTotals = document.getElementById('btnSaveInvoiceTotals');

    let currentMatSum = 0;
    let currentRepSum = 0;
    let currentComputedTotal = 0;

    // Cálculo dinámico para la fila de Material
    function calcMaterialNet() {
        if (!detMatTotalNeto) return;
        const cant = parseFloat(detMatCantidad ? detMatCantidad.value : 1) || 0;
        const precio = parseFloat(detMatPrecio ? detMatPrecio.value : 0) || 0;
        const dto = parseFloat(detMatDescuento ? detMatDescuento.value : 0) || 0;
        const net = Math.max(0, cant * precio * (1 - (dto / 100)));
        detMatTotalNeto.value = net.toFixed(2) + ' €';
    }

    if (detMatCantidad) detMatCantidad.addEventListener('input', calcMaterialNet);
    if (detMatPrecio) detMatPrecio.addEventListener('input', calcMaterialNet);
    if (detMatDescuento) detMatDescuento.addEventListener('input', calcMaterialNet);

    // Selección de material del catálogo
    if (detMatCatalogo) {
        detMatCatalogo.addEventListener('change', function () {
            const selectedOpt = detMatCatalogo.selectedOptions ? detMatCatalogo.selectedOptions[0] : null;
            if (selectedOpt && selectedOpt.value) {
                if (detMatCodigo) detMatCodigo.value = selectedOpt.dataset.codigo || '';
                if (detMatDescripcion) detMatDescripcion.value = selectedOpt.dataset.descripcion || '';
                if (detMatPrecio) detMatPrecio.value = selectedOpt.dataset.precio || '0';
            }
            calcMaterialNet();
        });
    }

    // Cálculo dinámico para la fila de Reparación
    function calcReparacionNet() {
        if (!detRepTotalNeto) return;
        const precio = parseFloat(detRepPrecio ? detRepPrecio.value : 0) || 0;
        const dto = parseFloat(detRepDescuento ? detRepDescuento.value : 0) || 0;
        const net = Math.max(0, precio * (1 - (dto / 100)));
        detRepTotalNeto.value = net.toFixed(2) + ' €';
    }

    if (detRepPrecio) detRepPrecio.addEventListener('input', calcReparacionNet);
    if (detRepDescuento) detRepDescuento.addEventListener('input', calcReparacionNet);

    // Recalcular y pintar desglose de totales
    function renderTotalsSummary() {
        const subtotal = currentMatSum + currentRepSum;
        const descuento = parseFloat(detTotDescuentoGlobal ? detTotDescuentoGlobal.value : 0) || 0;
        const ivaPorcentaje = parseFloat(detTotIva ? detTotIva.value : 21) || 0;
        const baseImponible = Math.max(0, subtotal - descuento);
        const ivaImporte = baseImponible * (ivaPorcentaje / 100);
        currentComputedTotal = baseImponible + ivaImporte;

        if (sumMateriales) sumMateriales.textContent = currentMatSum.toFixed(2) + ' €';
        if (sumReparaciones) sumReparaciones.textContent = currentRepSum.toFixed(2) + ' €';
        if (sumSubtotal) sumSubtotal.textContent = subtotal.toFixed(2) + ' €';
        if (sumDescuento) sumDescuento.textContent = '- ' + descuento.toFixed(2) + ' €';
        if (sumBaseImponible) sumBaseImponible.textContent = baseImponible.toFixed(2) + ' €';
        if (lblIva) lblIva.textContent = 'IVA (' + (Number.isInteger(ivaPorcentaje) ? ivaPorcentaje : ivaPorcentaje.toFixed(1)) + '%)';
        if (sumIva) sumIva.textContent = ivaImporte.toFixed(2) + ' €';
        if (sumTotal) sumTotal.textContent = currentComputedTotal.toFixed(2) + ' €';
    }

    if (detTotDescuentoGlobal) detTotDescuentoGlobal.addEventListener('input', renderTotalsSummary);
    if (detTotIva) detTotIva.addEventListener('input', renderTotalsSummary);

    function getCsrfToken() {
        const inp = document.querySelector('input[name="token"]');
        if (inp && inp.value) return inp.value;
        const metaNew = document.querySelector('meta[name="anti-csrf-newtoken"]');
        if (metaNew && metaNew.getAttribute('content')) return metaNew.getAttribute('content');
        const metaCur = document.querySelector('meta[name="anti-csrf-currenttoken"]');
        if (metaCur && metaCur.getAttribute('content')) return metaCur.getAttribute('content');
        return '';
    }

    const ajaxUrl = window.location.pathname;

    // Cargar líneas y datos de la factura por AJAX
    function loadFacturaDetalle(facId) {
        if (!facId) return;
        fetch(ajaxUrl + '?action=get_factura_lineas&fk_factura=' + encodeURIComponent(facId))
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                // Actualizar inputs de descuento global e IVA
                if (data.factura) {
                    if (detTotDescuentoGlobal) detTotDescuentoGlobal.value = parseFloat(data.factura.descuento_global || 0).toFixed(2);
                    if (detTotIva) detTotIva.value = parseFloat(data.factura.iva_porcentaje !== null && data.factura.iva_porcentaje !== undefined ? data.factura.iva_porcentaje : 21).toFixed(0);
                }

                // Renderizar Materiales (Formato tabla idéntico a referencia)
                currentMatSum = 0;
                if (detMatList && detMatEmpty) {
                    const materiales = data.materiales || [];
                    if (materiales.length === 0) {
                        detMatEmpty.style.display = 'block';
                        detMatList.style.display = 'none';
                        detMatList.innerHTML = '';
                    } else {
                        detMatEmpty.style.display = 'none';
                        detMatList.style.display = 'block';

                        let tableHtml = '<table class="tallerpro-invoice-table">' +
                            '<thead>' +
                                '<tr>' +
                                    '<th style="width: 15%;">Código</th>' +
                                    '<th style="width: 44%;">Descripción</th>' +
                                    '<th style="width: 8%; text-align: center;">Cant.</th>' +
                                    '<th style="width: 12%; text-align: right;">P. unit.</th>' +
                                    '<th style="width: 8%; text-align: center;">Dto.</th>' +
                                    '<th style="width: 13%; text-align: right;">Total</th>' +
                                    '<th style="width: 32px; text-align: center;"></th>' +
                                '</tr>' +
                            '</thead>' +
                            '<tbody>';

                        materiales.forEach(function (mat) {
                            const net = parseFloat(mat.total_neto) || 0;
                            currentMatSum += net;
                            const codeDisplay = (mat.codigo && mat.codigo.trim()) ? escapeHtml(mat.codigo) : '—';
                            const qtyDisplay = parseFloat(mat.cantidad).toFixed(0);
                            const priceDisplay = parseFloat(mat.precio).toFixed(2) + ' €';
                            const dtoDisplay = parseFloat(mat.descuento).toFixed(0) + '%';
                            const totalDisplay = net.toFixed(2) + ' €';

                            tableHtml += '<tr>' +
                                '<td>' + codeDisplay + '</td>' +
                                '<td>' + escapeHtml(mat.descripcion) + '</td>' +
                                '<td style="text-align: center;">' + qtyDisplay + '</td>' +
                                '<td style="text-align: right;">' + priceDisplay + '</td>' +
                                '<td style="text-align: center;">' + dtoDisplay + '</td>' +
                                '<td style="text-align: right; font-weight: 700; color: #0f172a;">' + totalDisplay + '</td>' +
                                '<td style="text-align: center;">' +
                                    '<button type="button" class="tallerpro-action-btn btn-delete-line" data-line-id="' + mat.rowid + '" title="Eliminar">' +
                                        '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                                            '<polyline points="3 6 5 6 21 6"></polyline>' +
                                            '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>' +
                                        '</svg>' +
                                    '</button>' +
                                '</td>' +
                            '</tr>';
                        });

                        tableHtml += '</tbody></table>';
                        detMatList.innerHTML = tableHtml;
                    }
                }

                // Renderizar Reparaciones (Formato tabla idéntico a referencia)
                currentRepSum = 0;
                if (detRepList && detRepEmpty) {
                    const reparaciones = data.reparaciones || [];
                    if (reparaciones.length === 0) {
                        detRepEmpty.style.display = 'block';
                        detRepList.style.display = 'none';
                        detRepList.innerHTML = '';
                    } else {
                        detRepEmpty.style.display = 'none';
                        detRepList.style.display = 'block';

                        let tableHtml = '<table class="tallerpro-invoice-table">' +
                            '<thead>' +
                                '<tr>' +
                                    '<th style="width: 58%;">Descripción</th>' +
                                    '<th style="width: 14%; text-align: right;">Precio</th>' +
                                    '<th style="width: 10%; text-align: center;">Dto.</th>' +
                                    '<th style="width: 14%; text-align: right;">Total</th>' +
                                    '<th style="width: 32px; text-align: center;"></th>' +
                                '</tr>' +
                            '</thead>' +
                            '<tbody>';

                        reparaciones.forEach(function (rep) {
                            const net = parseFloat(rep.total_neto) || 0;
                            currentRepSum += net;
                            const priceDisplay = parseFloat(rep.precio).toFixed(2) + ' €';
                            const dtoDisplay = parseFloat(rep.descuento).toFixed(0) + '%';
                            const totalDisplay = net.toFixed(2) + ' €';
                            let descHtml = '<span>' + escapeHtml(rep.descripcion) + '</span>';
                            if (rep.notas && rep.notas.trim()) {
                                descHtml += '<div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">' + escapeHtml(rep.notas.trim()) + '</div>';
                            }

                            tableHtml += '<tr>' +
                                '<td>' + descHtml + '</td>' +
                                '<td style="text-align: right;">' + priceDisplay + '</td>' +
                                '<td style="text-align: center;">' + dtoDisplay + '</td>' +
                                '<td style="text-align: right; font-weight: 700; color: #0f172a;">' + totalDisplay + '</td>' +
                                '<td style="text-align: center;">' +
                                    '<button type="button" class="tallerpro-action-btn btn-delete-line" data-line-id="' + rep.rowid + '" title="Eliminar">' +
                                        '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                                            '<polyline points="3 6 5 6 21 6"></polyline>' +
                                            '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>' +
                                        '</svg>' +
                                    '</button>' +
                                '</td>' +
                            '</tr>';
                        });

                        tableHtml += '</tbody></table>';
                        detRepList.innerHTML = tableHtml;
                    }
                }

                // Pintar desglose
                renderTotalsSummary();

                // Actualizar total en la tabla principal en vivo
                updateTableRowTotal(facId, currentComputedTotal);
            })
            .catch(function (err) {
                console.error('Error al cargar detalle de factura:', err);
            });
    }

    function updateTableRowTotal(facId, total) {
        const tr = document.querySelector('tr[data-invoice-id="' + facId + '"]');
        if (tr) {
            const totalTd = tr.querySelector('.col-factura-total');
            if (totalTd) {
                totalTd.textContent = total.toFixed(2) + ' €';
            }
            const editBtn = tr.querySelector('.btn-edit-factura');
            if (editBtn) {
                editBtn.dataset.total = total.toFixed(2);
            }
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    // Botones de acción "Detalle" en la tabla de facturas
    const detalleFacBtns = document.querySelectorAll('.btn-detalle-factura');
    detalleFacBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const facId = btn.dataset.id || '';
            const facNumero = btn.dataset.numero || '';

            if (detalleModalTitle) {
                detalleModalTitle.textContent = 'Detalle de la factura ' + facNumero;
            }
            if (detalleFkFactura) {
                detalleFkFactura.value = facId;
            }

            // Resetear formularios de entrada
            if (detMatCatalogo) detMatCatalogo.value = '';
            if (detMatCodigo) detMatCodigo.value = '';
            if (detMatDescripcion) detMatDescripcion.value = '';
            if (detMatCantidad) detMatCantidad.value = '1';
            if (detMatPrecio) detMatPrecio.value = '0';
            if (detMatDescuento) detMatDescuento.value = '0';
            if (detMatTotalNeto) detMatTotalNeto.value = '0.00 €';
            if (detMatGuardarCatalogo) detMatGuardarCatalogo.checked = true;

            if (detRepDescripcion) detRepDescripcion.value = '';
            if (detRepPrecio) detRepPrecio.value = '0';
            if (detRepDescuento) detRepDescuento.value = '0';
            if (detRepTotalNeto) detRepTotalNeto.value = '0.00 €';
            if (detRepNotas) detRepNotas.value = '';

            // Mostrar modal
            if (detalleModal) {
                detalleModal.classList.add('active');
            }

            // Cargar datos
            loadFacturaDetalle(facId);
        });
    });

    // Añadir línea de Material (con protección anti-duplicados y reanudación en finally)
    let isSubmittingMaterial = false;
    if (btnAddMaterialLine) {
        btnAddMaterialLine.addEventListener('click', function (e) {
            if (e) e.preventDefault();
            if (isSubmittingMaterial) return;

            const facId = detalleFkFactura ? detalleFkFactura.value : '';
            const desc = detMatDescripcion ? detMatDescripcion.value.trim() : '';
            if (!facId) {
                alert('No se ha detectado el identificador de la factura.');
                return;
            }
            if (!desc) {
                alert('Por favor, indica una descripción para el material.');
                if (detMatDescripcion) {
                    detMatDescripcion.focus();
                    detMatDescripcion.style.borderColor = '#ef4444';
                    setTimeout(function () { detMatDescripcion.style.borderColor = ''; }, 2000);
                }
                return;
            }

            isSubmittingMaterial = true;
            const origText = btnAddMaterialLine.innerHTML;
            btnAddMaterialLine.disabled = true;
            btnAddMaterialLine.innerHTML = '<span>Añadiendo...</span>';

            const fd = new FormData();
            fd.append('action', 'add_factura_linea');
            fd.append('fk_factura', facId);
            fd.append('tipo', 'material');
            fd.append('fk_material', detMatCatalogo ? detMatCatalogo.value : '');
            fd.append('codigo', detMatCodigo ? detMatCodigo.value : '');
            fd.append('descripcion', desc);
            fd.append('cantidad', detMatCantidad ? detMatCantidad.value : '1');
            fd.append('precio', detMatPrecio ? detMatPrecio.value : '0');
            fd.append('descuento', detMatDescuento ? detMatDescuento.value : '0');
            fd.append('guardar_catalogo', (detMatGuardarCatalogo && detMatGuardarCatalogo.checked) ? '1' : '0');
            const token = getCsrfToken();
            if (token) fd.append('token', token);

            fetch(ajaxUrl + '?action=add_factura_linea', {
                method: 'POST',
                body: fd
            })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (data && data.status === 'error') {
                    alert('Error: ' + (data.message || 'No se pudo añadir el material'));
                    return;
                }
                // Limpiar campos
                if (detMatCatalogo) detMatCatalogo.value = '';
                if (detMatCodigo) detMatCodigo.value = '';
                if (detMatDescripcion) detMatDescripcion.value = '';
                if (detMatCantidad) detMatCantidad.value = '1';
                if (detMatPrecio) detMatPrecio.value = '0';
                if (detMatDescuento) detMatDescuento.value = '0';
                if (detMatTotalNeto) detMatTotalNeto.value = '0.00 €';
                // Recargar listado
                loadFacturaDetalle(facId);
            })
            .catch(function (err) {
                console.error('Error al añadir material:', err);
                alert('Ocurrió un error al añadir el material: ' + err.message);
            })
            .finally(function () {
                isSubmittingMaterial = false;
                btnAddMaterialLine.disabled = false;
                btnAddMaterialLine.innerHTML = origText;
            });
        });
    }

    // Añadir línea de Reparación (con protección anti-duplicados y reanudación en finally)
    let isSubmittingReparacion = false;
    if (btnAddReparacionLine) {
        btnAddReparacionLine.addEventListener('click', function (e) {
            if (e) e.preventDefault();
            if (isSubmittingReparacion) return;

            const facId = detalleFkFactura ? detalleFkFactura.value : '';
            const desc = detRepDescripcion ? detRepDescripcion.value.trim() : '';
            if (!facId) {
                alert('No se ha detectado el identificador de la factura.');
                return;
            }
            if (!desc) {
                alert('Por favor, indica una descripción para la reparación.');
                if (detRepDescripcion) {
                    detRepDescripcion.focus();
                    detRepDescripcion.style.borderColor = '#ef4444';
                    setTimeout(function () { detRepDescripcion.style.borderColor = ''; }, 2000);
                }
                return;
            }

            isSubmittingReparacion = true;
            const origText = btnAddReparacionLine.innerHTML;
            btnAddReparacionLine.disabled = true;
            btnAddReparacionLine.innerHTML = '<span>Añadiendo...</span>';

            const fd = new FormData();
            fd.append('action', 'add_factura_linea');
            fd.append('fk_factura', facId);
            fd.append('tipo', 'reparacion');
            fd.append('descripcion', desc);
            fd.append('precio', detRepPrecio ? detRepPrecio.value : '0');
            fd.append('descuento', detRepDescuento ? detRepDescuento.value : '0');
            fd.append('notas', detRepNotas ? detRepNotas.value : '');
            const token = getCsrfToken();
            if (token) fd.append('token', token);

            fetch(ajaxUrl + '?action=add_factura_linea', {
                method: 'POST',
                body: fd
            })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (data && data.status === 'error') {
                    alert('Error: ' + (data.message || 'No se pudo añadir la reparación'));
                    return;
                }
                // Limpiar campos
                if (detRepDescripcion) detRepDescripcion.value = '';
                if (detRepPrecio) detRepPrecio.value = '0';
                if (detRepDescuento) detRepDescuento.value = '0';
                if (detRepTotalNeto) detRepTotalNeto.value = '0.00 €';
                if (detRepNotas) detRepNotas.value = '';
                // Recargar listado
                loadFacturaDetalle(facId);
            })
            .catch(function (err) {
                console.error('Error al añadir reparación:', err);
                alert('Ocurrió un error al añadir la reparación: ' + err.message);
            })
            .finally(function () {
                isSubmittingReparacion = false;
                btnAddReparacionLine.disabled = false;
                btnAddReparacionLine.innerHTML = origText;
            });
        });
    }

    // Eliminar línea (Delegación sobre listas)
    function handleLineDelete(e) {
        const delBtn = e.target.closest('.btn-delete-line');
        if (!delBtn) return;
        const lineId = delBtn.dataset.lineId;
        const facId = detalleFkFactura ? detalleFkFactura.value : '';
        if (!lineId || !facId) return;

        if (confirm('¿Deseas eliminar esta línea?')) {
            const fd = new FormData();
            fd.append('action', 'delete_factura_linea');
            fd.append('linea_id', lineId);
            fd.append('fk_factura', facId);
            const token = getCsrfToken();
            if (token) fd.append('token', token);

            fetch(ajaxUrl + '?action=delete_factura_linea', {
                method: 'POST',
                body: fd
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.status === 'error') {
                    alert('Error al eliminar: ' + (data.message || ''));
                    return;
                }
                loadFacturaDetalle(facId);
            })
            .catch(function (err) {
                console.error('Error al eliminar línea:', err);
            });
        }
    }

    if (detMatList) detMatList.addEventListener('click', handleLineDelete);
    if (detRepList) detRepList.addEventListener('click', handleLineDelete);

    // Guardar totales en la factura
    if (btnSaveInvoiceTotals) {
        btnSaveInvoiceTotals.addEventListener('click', function () {
            const facId = detalleFkFactura ? detalleFkFactura.value : '';
            if (!facId) return;

            const fd = new FormData();
            fd.append('action', 'save_factura_totales');
            fd.append('fk_factura', facId);
            fd.append('descuento_global', detTotDescuentoGlobal ? detTotDescuentoGlobal.value : '0');
            fd.append('iva_porcentaje', detTotIva ? detTotIva.value : '21');
            fd.append('total', currentComputedTotal.toFixed(2));
            const token = getCsrfToken();
            if (token) fd.append('token', token);

            fetch(ajaxUrl + '?action=save_factura_totales', {
                method: 'POST',
                body: fd
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                updateTableRowTotal(facId, currentComputedTotal);
                const originalHtml = btnSaveInvoiceTotals.innerHTML;
                btnSaveInvoiceTotals.innerHTML = '<span>✓ ¡Totales guardados!</span>';
                btnSaveInvoiceTotals.style.backgroundColor = '#16a34a';
                setTimeout(function () {
                    btnSaveInvoiceTotals.innerHTML = originalHtml;
                    btnSaveInvoiceTotals.style.backgroundColor = '';
                }, 1800);
            })
            .catch(function (err) {
                console.error('Error al guardar totales:', err);
                alert('Error al guardar totales: ' + err.message);
            });
        });
    }

    // Botón "Listo" en el modal de líneas de factura -> Abre la vista documental
    const btnModalListo = document.getElementById('btnModalListo');
    if (btnModalListo) {
        btnModalListo.addEventListener('click', function () {
            const facId = detalleFkFactura ? detalleFkFactura.value : '';
            if (!facId) {
                if (detalleModal) detalleModal.classList.remove('active');
                return;
            }

            btnModalListo.disabled = true;
            btnModalListo.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="tallerpro-spin"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg> <span>Guardando...</span>';

            const fd = new FormData();
            fd.append('action', 'save_factura_totales');
            fd.append('fk_factura', facId);
            fd.append('descuento_global', detTotDescuentoGlobal ? detTotDescuentoGlobal.value : '0');
            fd.append('iva_porcentaje', detTotIva ? detTotIva.value : '21');
            fd.append('total', currentComputedTotal.toFixed(2));
            const token = getCsrfToken();
            if (token) fd.append('token', token);

            fetch(ajaxUrl + '?action=save_factura_totales', {
                method: 'POST',
                body: fd
            })
            .then(function () {
                window.location.href = 'facturas.php?id=' + encodeURIComponent(facId);
            })
            .catch(function (err) {
                console.warn('Error al guardar antes de redirigir:', err);
                window.location.href = 'facturas.php?id=' + encodeURIComponent(facId);
            });
        });
    }

    // Cerrar modal Detalle
    if (closeDetalleModalBtn) {
        closeDetalleModalBtn.addEventListener('click', function () {
            if (detalleModal) detalleModal.classList.remove('active');
        });
    }

    if (detalleModal) {
        detalleModal.addEventListener('click', function (e) {
            if (e.target === detalleModal) {
                detalleModal.classList.remove('active');
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && detalleModal && detalleModal.classList.contains('active')) {
            detalleModal.classList.remove('active');
        }
    });
});
}



