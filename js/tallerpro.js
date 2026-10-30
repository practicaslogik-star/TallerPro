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

        // Restore sidebar state from localStorage only on desktop (> 1024px)
        if (window.innerWidth > 1024 && localStorage.getItem('tallerpro_sidebar_collapsed') === 'true') {
            sidebar?.classList.add('collapsed');
        }

        // Mobile/Tablet Sidebar Control
        function closeMobileSidebar() {
            if (!sidebar) return;
            sidebar.classList.remove('mobile-open');
            document.getElementById('tallerSidebarOverlay')?.classList.remove('active');
            if (window.innerWidth > 1024 && localStorage.getItem('tallerpro_sidebar_collapsed') === 'true') {
                sidebar.classList.add('collapsed');
            }
        }

        function openMobileSidebar() {
            if (!sidebar) return;
            sidebar.classList.remove('collapsed'); // Always show full texts in drawer
            sidebar.classList.add('mobile-open');
            const layout = document.querySelector('.tallerpro-layout') || document.body;
            let overlay = document.getElementById('tallerSidebarOverlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'tallerSidebarOverlay';
                overlay.className = 'tallerpro-sidebar-overlay';
                layout.appendChild(overlay);
                overlay.addEventListener('click', closeMobileSidebar);
            } else if (overlay.parentElement !== layout) {
                layout.appendChild(overlay);
            }
            overlay.classList.add('active');
        }

        // Toggle sidebar collapse (Desktop: collapse; Mobile/Tablet: slide drawer)
        function toggleSidebar() {
            if (!sidebar) return;
            if (window.innerWidth <= 1024) {
                if (sidebar.classList.contains('mobile-open')) {
                    closeMobileSidebar();
                } else {
                    openMobileSidebar();
                }
            } else {
                sidebar.classList.toggle('collapsed');
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('tallerpro_sidebar_collapsed', isCollapsed ? 'true' : 'false');
            }
        }

        collapseBtn?.addEventListener('click', function (e) {
            e.preventDefault();
            toggleSidebar();
        });

        topbarToggle?.addEventListener('click', function (e) {
            e.preventDefault();
            toggleSidebar();
        });

        // Ensure hamburger icon exists for mobile/tablet in topbar toggle
        if (topbarToggle && !topbarToggle.querySelector('.tallerpro-toggle-icon-mobile')) {
            const firstSvg = topbarToggle.querySelector('svg');
            if (firstSvg) {
                firstSvg.classList.add('tallerpro-toggle-icon-desktop');
            }
            const mobileBurgerSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            mobileBurgerSvg.setAttribute('class', 'tallerpro-toggle-icon-mobile');
            mobileBurgerSvg.setAttribute('width', '20');
            mobileBurgerSvg.setAttribute('height', '20');
            mobileBurgerSvg.setAttribute('viewBox', '0 0 24 24');
            mobileBurgerSvg.setAttribute('fill', 'none');
            mobileBurgerSvg.setAttribute('stroke', 'currentColor');
            mobileBurgerSvg.setAttribute('stroke-width', '2.2');
            mobileBurgerSvg.setAttribute('stroke-linecap', 'round');
            mobileBurgerSvg.setAttribute('stroke-linejoin', 'round');
            mobileBurgerSvg.innerHTML = `
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            `;
            topbarToggle.appendChild(mobileBurgerSvg);
        }

        // Ensure close button exists in sidebar for mobile/tablet
        if (sidebar && !sidebar.querySelector('.tallerpro-sidebar-close')) {
            const brand = sidebar.querySelector('.tallerpro-brand');
            if (brand) {
                const closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'tallerpro-sidebar-close';
                closeBtn.title = 'Cerrar menú';
                closeBtn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                `;
                closeBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeMobileSidebar();
                });
                brand.appendChild(closeBtn);
            }
        }

        // Close mobile drawer on navigation click
        navItems.forEach(function (item) {
            item.addEventListener('click', function (e) {
                const href = item.getAttribute('href');
                if (window.innerWidth <= 1024) {
                    closeMobileSidebar();
                }
                if (!href || href.startsWith('#')) {
                    e.preventDefault();
                    navItems.forEach(function (el) { el.classList.remove('active'); });
                    item.classList.add('active');
                }
            });
        });

        // =========================================================================
        // Transformación Responsiva: Tablas -> Tarjetas (Mobile / Tablet)
        // Lee los encabezados <th> y los asigna automáticamente como data-label
        // =========================================================================
        function initResponsiveTables() {
            const tables = document.querySelectorAll('.tallerpro-table');
            tables.forEach(function (table) {
                // Obtener los títulos de las columnas de la cabecera
                const thElements = Array.from(table.querySelectorAll('thead th'));
                const headers = thElements.map(function (th) {
                    return th.textContent.trim();
                });

                // Recorrer las filas del cuerpo
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(function (row) {
                    // Si es una fila de mensaje vacío (colspan), marcarla
                    if (row.querySelector('td[colspan]')) {
                        row.classList.add('tallerpro-empty-row');
                        return;
                    }

                    const cells = Array.from(row.children).filter(function (child) {
                        return child.tagName.toLowerCase() === 'td';
                    });

                    cells.forEach(function (td, idx) {
                        const headerText = headers[idx] || '';
                        const hasActions = td.querySelector('.tallerpro-actions') ||
                            (!headerText && idx === cells.length - 1);

                        if (idx === 0) {
                            td.classList.add('tallerpro-card-header-cell');
                            if (headerText && !td.getAttribute('data-label')) {
                                td.setAttribute('data-label', headerText);
                            }
                        } else if (hasActions) {
                            td.classList.add('tallerpro-actions-cell');
                            // Add clear text labels for action buttons on mobile cards
                            td.querySelectorAll('.tallerpro-action-btn').forEach(function (btn) {
                                if (!btn.querySelector('.tallerpro-action-text, span')) {
                                    const title = (btn.getAttribute('title') || '').toLowerCase();
                                    let label = '';
                                    if (title.includes('eliminar') || title.includes('borrar')) {
                                        label = 'Eliminar';
                                        btn.classList.add('tallerpro-btn-delete');
                                    } else if (title.includes('editar') || title.includes('edit')) {
                                        label = 'Editar';
                                        btn.classList.add('tallerpro-btn-edit');
                                    } else if (title.includes('ver') || title.includes('detalle') || title.includes('view') || title.includes('descargar') || title.includes('pdf')) {
                                        label = title.includes('pdf') ? 'PDF' : 'Ver';
                                        btn.classList.add('tallerpro-btn-view');
                                    }

                                    if (label) {
                                        const span = document.createElement('span');
                                        span.className = 'tallerpro-action-text';
                                        span.textContent = label;
                                        btn.appendChild(span);
                                    }
                                }
                            });
                        } else {
                            td.classList.add('tallerpro-card-field-cell');
                            if (headerText && !td.getAttribute('data-label')) {
                                td.setAttribute('data-label', headerText);
                            }
                        }
                    });
                });
            });
        }

        // Ejecutar transformación en carga inicial
        initResponsiveTables();
        window.tallerInitResponsiveTables = initResponsiveTables;

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

        // Reparaciones Modal handling is handled in the REPARACIONES VIEW section below

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

        /* REPARACIONES VIEW (TallerPro)  */

        // Global Reusable Toast Notification
        window.showTallerToast = function (message, type = 'success', duration = 3800) {
            let container = document.getElementById('tallerToastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'tallerToastContainer';
                container.className = 'tallerpro-toast-container';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'tallerpro-toast';

            const iconSvg = (type === 'error') ? `
            <span class="tallerpro-toast-icon error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
            </span>
        ` : `
            <span class="tallerpro-toast-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </span>
        `;

            toast.innerHTML = iconSvg + '<span class="tallerpro-toast-text">' + message + '</span>';
            container.appendChild(toast);

            requestAnimationFrame(function () {
                toast.classList.add('active');
            });

            setTimeout(function () {
                toast.classList.remove('active');
                setTimeout(function () {
                    toast.remove();
                }, 260);
            }, duration);
        };

        // ==========================================================================
        // Toast Notifier Modular Universal para todas las Vistas de TallerPro
        // ==========================================================================
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg')) {
            const msgCode = urlParams.get('msg');
            const currentPath = window.location.pathname.toLowerCase();

            // 1. Detectar automáticamente la entidad según la vista
            let entidad = 'Registro';
            let esFemenino = false;

            if (currentPath.includes('cliente')) {
                entidad = 'Cliente';
                esFemenino = false;
            } else if (currentPath.includes('vehiculo')) {
                entidad = 'Vehículo';
                esFemenino = false;
            } else if (currentPath.includes('reparacion')) {
                entidad = 'Reparación';
                esFemenino = true;
            } else if (currentPath.includes('material')) {
                entidad = 'Material';
                esFemenino = false;
            } else if (currentPath.includes('seguro')) {
                entidad = 'Seguro';
                esFemenino = false;
            } else if (currentPath.includes('factura')) {
                entidad = 'Factura';
                esFemenino = true;
            }

            // 2. mensaje personalizado dependiendo de la accion
            let flashMsg = '';
            let flashType = 'success';

            if (msgCode === 'saved' || msgCode === 'created') {
                flashMsg = entidad + (esFemenino ? ' guardada' : ' guardado') + ' correctamente';
            } else if (msgCode === 'deleted') {
                flashMsg = entidad + (esFemenino ? ' eliminada' : ' eliminado') + ' correctamente';
            } else if (msgCode === 'updated') {
                flashMsg = entidad + (esFemenino ? ' actualizada' : ' actualizado') + ' correctamente';
            } else if (msgCode === 'status_updated') {
                flashMsg = 'Estado de ' + (esFemenino ? 'la ' : 'el ') + entidad.toLowerCase() + ' actualizado correctamente';
            } else if (msgCode === 'error') {
                flashType = 'error';
                flashMsg = urlParams.get('error') || ('Error al procesar ' + (esFemenino ? 'la ' : 'el ') + entidad.toLowerCase());
            }

            // 3. Mostrar el Toast y limpiar la URL discretamente
            if (flashMsg) {
                window.showTallerToast(flashMsg, flashType);
                if (window.history.replaceState) {
                    const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
                }
            }
        }

        // Modal Orden de Reparación
        const repModal = document.getElementById('tallerReparacionModal');
        const openRepModalBtn = document.getElementById('tallerOpenModalBtn');
        const closeRepModalBtn = document.getElementById('tallerCloseModalBtn');
        const cancelRepModalBtn = document.getElementById('tallerCancelModalBtn');

        if (openRepModalBtn && repModal) {
            openRepModalBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const actionInput = document.getElementById('reparacionFormAction');
                const rowidInput = document.getElementById('reparacionFormRowid');
                const titleEl = document.getElementById('orderModalTitle');

                if (actionInput) actionInput.value = 'create_reparacion';
                if (rowidInput) rowidInput.value = '0';
                if (titleEl) titleEl.textContent = 'Orden de Reparación';

                const fechaInput = document.getElementById('orderFecha');
                if (fechaInput) {
                    const today = new Date().toISOString().split('T')[0];
                    fechaInput.value = today;
                    fechaInput.min = today; // Bloquea fechas anteriores a hoy
                }

                repModal.classList.add('active');
            });
        }

        [closeRepModalBtn, cancelRepModalBtn].forEach(function (btn) {
            if (btn && repModal) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    repModal.classList.remove('active');
                });
            }
        });

        if (repModal) {
            repModal.addEventListener('click', function (e) {
                if (e.target === repModal) {
                    repModal.classList.remove('active');
                }
            });
        }

        // Modal Confirmación de Eliminación Global (Reparaciones, Clientes, Vehículos, Materiales)
        let pendingDeleteUrl = null;
        let pendingDeleteRow = null;
        let pendingDeleteRowId = null;

        document.addEventListener('click', function (e) {
            // Caso 1: Reparaciones AJAX delete
            const delRepBtn = e.target.closest('.btn-delete-reparacion');
            if (delRepBtn) {
                e.preventDefault();
                pendingDeleteRow = delRepBtn.closest('.rep-row');
                pendingDeleteRowId = delRepBtn.getAttribute('data-id');
                pendingDeleteUrl = null;
                const modal = document.getElementById('deleteConfirmModal');
                if (modal) modal.classList.add('active');
                return;
            }

            // Caso 2: Enlace/botón de eliminación en Clientes, Vehículos, Materiales
            const delActionBtn = e.target.closest('.btn-delete-action, a[href*="delete_client"], a[href*="delete_vehiculo"], a[href*="delete_material"]');
            if (delActionBtn) {
                e.preventDefault();
                pendingDeleteUrl = delActionBtn.getAttribute('data-delete-url') || delActionBtn.getAttribute('href');
                pendingDeleteRow = delActionBtn.closest('tr');
                pendingDeleteRowId = null;
                const modal = document.getElementById('deleteConfirmModal');
                if (modal) {
                    modal.classList.add('active');
                }
            }
        });

        document.addEventListener('click', function (e) {
            const cancelBtn = e.target.closest('#btnCancelDelete');
            const modal = document.getElementById('deleteConfirmModal');
            if (cancelBtn && modal) {
                modal.classList.remove('active');
                pendingDeleteUrl = null;
                pendingDeleteRow = null;
                pendingDeleteRowId = null;
            } else if (modal && e.target === modal) {
                modal.classList.remove('active');
                pendingDeleteUrl = null;
                pendingDeleteRow = null;
                pendingDeleteRowId = null;
            }
        });

        document.addEventListener('click', function (e) {
            const confirmBtn = e.target.closest('#btnConfirmDelete');
            const modal = document.getElementById('deleteConfirmModal');
            if (!confirmBtn || !modal) return;
            e.preventDefault();

            modal.classList.remove('active');

            // Si es eliminación por URL (Clientes, Vehículos, Materiales)
            if (pendingDeleteUrl) {
                window.location.href = pendingDeleteUrl;
                return;
            }

            // Si es eliminación por AJAX (Reparaciones)
            const row = pendingDeleteRow;
            const rowid = pendingDeleteRowId;
            const token = document.getElementById('reparacionFormToken')?.value || '';

            if (rowid && !isNaN(rowid) && parseInt(rowid) > 0) {
                fetch('reparaciones.php?action=delete_reparacion&ajax=1&rowid=' + rowid + '&token=' + encodeURIComponent(token), {
                    method: 'POST'
                }).catch(console.error);
            }

            if (row) {
                row.style.transition = 'all 0.25s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                setTimeout(function () {
                    row.remove();
                    if (typeof filterReparaciones === 'function') {
                        filterReparaciones();
                    }
                }, 250);
            }

            if (typeof window.showTallerToast === 'function') {
                window.showTallerToast('Reparación eliminada correctamente');
            }
            pendingDeleteRow = null;
            pendingDeleteRowId = null;
        });

        // Filter & Search table
        const searchInput = document.getElementById('tallerSearchReparaciones');
        const stateFilter = document.getElementById('tallerFilterEstado');
        const countSubtitle = document.getElementById('reparacionesCountSubtitle');

        function filterReparaciones() {
            const searchVal = (searchInput ? searchInput.value : '').toLowerCase().trim();
            const stateVal = stateFilter ? stateFilter.value : 'all';
            let visibleCount = 0;
            const currentRows = document.querySelectorAll('.rep-row');

            currentRows.forEach(function (row) {
                const rowSearch = (row.getAttribute('data-search') || '').toLowerCase();
                const rowEstado = row.getAttribute('data-estado') || '';

                const matchesSearch = !searchVal || rowSearch.includes(searchVal);
                const matchesState = (stateVal === 'all') || (rowEstado === stateVal);

                if (matchesSearch && matchesState) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (countSubtitle) {
                countSubtitle.textContent = visibleCount + ' órdenes registradas';
            }
        }

        if (searchInput) searchInput.addEventListener('input', filterReparaciones);
        if (stateFilter) stateFilter.addEventListener('change', filterReparaciones);

        // Modal Order Calculations (Labor + Materials)
        const laborBody = document.getElementById('laborTableBody');
        const materialsBody = document.getElementById('materialsTableBody');
        const summaryLabor = document.getElementById('summaryLabor');
        const summaryMaterials = document.getElementById('summaryMaterials');
        const summaryTotal = document.getElementById('summaryTotal');

        function recalculateTotals() {
            let totalLabor = 0;
            if (laborBody) {
                const costInputs = laborBody.querySelectorAll('.labor-cost');
                costInputs.forEach(function (input) {
                    const val = parseFloat(input.value) || 0;
                    totalLabor += val;
                });
            }

            let totalMaterials = 0;
            if (materialsBody) {
                const matRows = materialsBody.querySelectorAll('tr');
                matRows.forEach(function (tr) {
                    const qtyInput = tr.querySelector('.mat-qty');
                    const priceInput = tr.querySelector('.mat-price');
                    const rowTotalCell = tr.querySelector('.mat-row-total');

                    const qty = parseFloat(qtyInput ? qtyInput.value : 1) || 0;
                    const price = parseFloat(priceInput ? priceInput.value : 0) || 0;
                    const rowTot = qty * price;
                    totalMaterials += rowTot;

                    if (rowTotalCell) {
                        rowTotalCell.textContent = rowTot.toFixed(2) + ' €';
                    }
                });
            }

            const grandTotal = totalLabor + totalMaterials;
            if (summaryLabor) summaryLabor.textContent = totalLabor.toFixed(2) + ' €';
            if (summaryMaterials) summaryMaterials.textContent = totalMaterials.toFixed(2) + ' €';
            if (summaryTotal) summaryTotal.textContent = grandTotal.toFixed(2) + ' €';

            const costeMoHidden = document.getElementById('orderCosteMoHidden');
            if (costeMoHidden) {
                costeMoHidden.value = grandTotal.toFixed(2);
            }
        }

        if (laborBody) {
            laborBody.addEventListener('input', function (e) {
                if (e.target.classList.contains('labor-cost')) {
                    recalculateTotals();
                }
            });
            laborBody.addEventListener('click', function (e) {
                if (e.target.classList.contains('tallerpro-order-del-btn')) {
                    const tr = e.target.closest('tr');
                    if (laborBody.querySelectorAll('tr').length > 1) {
                        tr.remove();
                    } else {
                        const desc = tr.querySelector('.labor-desc');
                        const cost = tr.querySelector('.labor-cost');
                        if (desc) desc.value = '';
                        if (cost) cost.value = '0.00';
                    }
                    recalculateTotals();
                }
            });
        }

        if (materialsBody) {
            materialsBody.addEventListener('input', function (e) {
                if (e.target.classList.contains('mat-qty') || e.target.classList.contains('mat-price')) {
                    recalculateTotals();
                }
            });
            materialsBody.addEventListener('change', function (e) {
                if (e.target.classList.contains('mat-select')) {
                    const price = parseFloat(e.target.value) || 0;
                    const tr = e.target.closest('tr');
                    const priceInput = tr.querySelector('.mat-price');
                    if (priceInput) priceInput.value = price.toFixed(2);
                    recalculateTotals();
                }
            });
            materialsBody.addEventListener('click', function (e) {
                if (e.target.classList.contains('tallerpro-order-del-btn')) {
                    const tr = e.target.closest('tr');
                    if (materialsBody.querySelectorAll('tr').length > 1) {
                        tr.remove();
                    } else {
                        const qty = tr.querySelector('.mat-qty');
                        const price = tr.querySelector('.mat-price');
                        if (qty) qty.value = '1';
                        if (price) price.value = '0.00';
                    }
                    recalculateTotals();
                }
            });
        }

        // Add Trabajo row
        const btnAddTrabajo = document.getElementById('btnAddTrabajoRow');
        if (btnAddTrabajo && laborBody) {
            btnAddTrabajo.addEventListener('click', function () {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td><input type="text" class="tallerpro-order-input labor-desc" placeholder="Descripción de la tarea..." /></td>
                <td><input type="number" step="0.01" min="0" class="tallerpro-order-input labor-cost" value="0.00" style="text-align: right;" /></td>
                <td style="text-align: center;"><button type="button" class="tallerpro-order-del-btn" title="Eliminar trabajo">✕</button></td>
            `;
                laborBody.appendChild(tr);
                recalculateTotals();
            });
        }

        // Add Material row
        const btnAddMaterial = document.getElementById('btnAddMaterialRow');
        if (btnAddMaterial && materialsBody) {
            btnAddMaterial.addEventListener('click', function () {
                const firstSelect = document.querySelector('.mat-select');
                const optionsHtml = firstSelect ? firstSelect.innerHTML : '<option value="0.00">Sin materiales</option>';
                const firstOpt = firstSelect ? firstSelect.options[firstSelect.selectedIndex >= 0 ? firstSelect.selectedIndex : 0] : null;
                const defaultPrice = firstOpt ? (parseFloat(firstOpt.value) || 0).toFixed(2) : '0.00';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <select class="tallerpro-order-select mat-select">
                            ${optionsHtml}
                        </select>
                    </td>
                    <td><input type="number" min="1" step="1" class="tallerpro-order-input mat-qty" value="1" style="text-align: center;" /></td>
                    <td><input type="number" step="0.01" min="0" class="tallerpro-order-input mat-price" value="${defaultPrice}" style="text-align: right;" /></td>
                    <td style="text-align: right; font-weight: 600; color: #1e293b;" class="mat-row-total">${defaultPrice} €</td>
                    <td style="text-align: center;"><button type="button" class="tallerpro-order-del-btn" title="Eliminar repuesto">✕</button></td>
                `;
                materialsBody.appendChild(tr);
                recalculateTotals();
            });
        }

        const repForm = document.getElementById('tallerReparacionForm');
        if (repForm) {
            repForm.addEventListener('submit', function () {
                recalculateTotals();
            });
        }

        // Edit repair button
        document.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.btn-edit-reparacion');
            if (!editBtn) return;
            e.preventDefault();

            const id = editBtn.getAttribute('data-id') || '0';
            const vehiculo = editBtn.getAttribute('data-vehiculo') || '';
            const fecha = editBtn.getAttribute('data-fecha') || '';
            const estado = editBtn.getAttribute('data-estado') || 'en_curso';
            const descripcion = editBtn.getAttribute('data-descripcion') || '';
            const coste = editBtn.getAttribute('data-coste') || '0.00';
            const ref = editBtn.getAttribute('data-ref') || '';

            const actionInput = document.getElementById('reparacionFormAction');
            const rowidInput = document.getElementById('reparacionFormRowid');
            const titleEl = document.getElementById('orderModalTitle');

            if (actionInput) actionInput.value = 'update_reparacion';
            if (rowidInput) rowidInput.value = id;
            if (titleEl) titleEl.textContent = ref ? ('Editar ' + ref) : 'Editar Orden de Reparación';

            const vehSelect = document.getElementById('orderVehiculoSelect');
            if (vehSelect && vehiculo) vehSelect.value = vehiculo;

            const fechaInput = document.getElementById('orderFecha');
            if (fechaInput) {
                fechaInput.removeAttribute('min'); // Permite editar reparaciones que se abrieron días atrás
                if (fecha) fechaInput.value = fecha;
            }

            const estadoSelect = document.getElementById('orderEstadoSelect');
            if (estadoSelect && estado) estadoSelect.value = estado;

            const descInput = document.getElementById('orderAveria');
            if (descInput) descInput.value = descripcion;

            const laborCostInput = document.querySelector('.labor-cost');
            if (laborCostInput && coste) laborCostInput.value = coste;

            recalculateTotals();

            if (repModal) repModal.classList.add('active');
        });

        // Change state directly from Table select (silent, no toast notification)
        document.addEventListener('change', function (e) {
            const select = e.target.closest('.tallerpro-status-select');
            if (!select) return;

            const row = select.closest('.rep-row');
            const rowid = select.getAttribute('data-id');
            const newEstado = select.value;

            // Update select visual class
            select.className = 'tallerpro-status-select status-' + newEstado;

            // Update row and edit button data-estado attributes
            if (row) {
                row.setAttribute('data-estado', newEstado);
                const editBtn = row.querySelector('.btn-edit-reparacion');
                if (editBtn) editBtn.setAttribute('data-estado', newEstado);
            }

            // Update Facturar button in actions
            const actionsDiv = row ? row.querySelector('.tallerpro-actions') : null;
            if (actionsDiv) {
                let facturarBtn = actionsDiv.querySelector('.tallerpro-btn-facturar-action');
                const facturadaLink = actionsDiv.querySelector('.tallerpro-btn-facturada-link');
                if (newEstado === 'finalizada' && !facturadaLink) {
                    if (!facturarBtn) {
                        const token = document.getElementById('reparacionFormToken')?.value || '';
                        facturarBtn = document.createElement('a');
                        facturarBtn.href = 'reparaciones.php?action=facturar_reparacion&rowid=' + encodeURIComponent(rowid) + '&token=' + encodeURIComponent(token);
                        facturarBtn.setAttribute('onclick', "return confirm('¿Deseas generar la factura oficial de Dolibarr para esta reparación?');");

                        facturarBtn.className = 'tallerpro-btn-facturar-action';
                        facturarBtn.title = 'Emitir Factura oficial de esta reparación';
                        facturarBtn.innerHTML = `
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <span>Facturar</span>
                    `;
                        actionsDiv.insertBefore(facturarBtn, actionsDiv.firstChild);
                    }
                } else {
                    if (facturarBtn) {
                        facturarBtn.remove();
                    }
                }
            }

            // Persist to database silently if numeric rowid
            const token = document.getElementById('reparacionFormToken')?.value || '';
            if (rowid && !isNaN(rowid) && parseInt(rowid) > 0) {
                fetch('reparaciones.php?action=toggle_estado&ajax=1&rowid=' + rowid + '&new_estado=' + newEstado + '&token=' + encodeURIComponent(token), {
                    method: 'POST'
                }).catch(console.error);
            }
        });

        //catalogo de modelos y marcasd e vehiculos

        let catalogo_vehiculos = {};
        let catalogoVehiculos = catalogo_vehiculos;
        // cargar los datos del vehiculo del json 
        fetch('js/catalogo_coches_new.json')
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Error HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                catalogo_vehiculos = data;
                catalogoVehiculos = data;
            })
            .catch(function (error) {
                console.error('No se pudo cargar catalogo_coches_new.json:', error);
            });

        const inputMarca = document.getElementById('veh_marca');
        const inputModelo = document.getElementById('veh_modelo');
        const dropdownMarcas = document.getElementById('dropdown_marcas');
        const dropdownModelos = document.getElementById('dropdown_modelos');

        if (inputMarca && dropdownMarcas && inputModelo && dropdownModelos) {
            // Helper genérico para desplegable de autocompletado sobrio y moderno
            function setupCustomAutocomplete(input, dropdown, getItemsFn, onSelectFn) {
                let currentFocus = -1;

                function render(items, query) {
                    dropdown.innerHTML = '';
                    currentFocus = -1;

                    if (!items || items.length === 0) {
                        dropdown.classList.remove('active');
                        return;
                    }

                    items.forEach(function (item) {
                        const div = document.createElement('div');
                        div.className = 'tallerpro-autocomplete-item';
                        div.setAttribute('data-value', item);

                        const q = (query || '').trim();
                        if (q) {
                            const idx = item.toLowerCase().indexOf(q.toLowerCase());
                            if (idx !== -1) {
                                div.innerHTML = item.substring(0, idx) +
                                    '<strong>' + item.substring(idx, idx + q.length) + '</strong>' +
                                    item.substring(idx + q.length);
                            } else {
                                div.textContent = item;
                            }
                        } else {
                            div.textContent = item;
                        }

                        div.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            selectVal(item);
                        });

                        div.addEventListener('click', function (e) {
                            e.preventDefault();
                            selectVal(item);
                        });

                        dropdown.appendChild(div);
                    });

                    dropdown.classList.add('active');
                }

                function selectVal(val) {
                    input.value = val;
                    dropdown.classList.remove('active');
                    if (typeof onSelectFn === 'function') {
                        onSelectFn(val);
                    }
                }

                function filterAndShow() {
                    const q = input.value || '';
                    const items = getItemsFn(q);
                    render(items, q);
                }

                input.addEventListener('input', filterAndShow);
                input.addEventListener('focus', filterAndShow);

                input.addEventListener('keydown', function (e) {
                    const items = dropdown.querySelectorAll('.tallerpro-autocomplete-item');
                    if (!dropdown.classList.contains('active') || items.length === 0) return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        currentFocus++;
                        if (currentFocus >= items.length) currentFocus = 0;
                        highlight(items);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        currentFocus--;
                        if (currentFocus < 0) currentFocus = items.length - 1;
                        highlight(items);
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        e.stopPropagation();
                        // Selecciona el elemento enfocado con flechas, o el primero de la lista si no se usaron flechas
                        const targetIdx = currentFocus > -1 ? currentFocus : 0;
                        if (items[targetIdx]) {
                            const val = items[targetIdx].getAttribute('data-value');
                            selectVal(val);
                        }
                    } else if (e.key === 'Escape') {
                        dropdown.classList.remove('active');
                    }
                });

                function highlight(items) {
                    items.forEach(it => it.classList.remove('active-item'));
                    if (currentFocus > -1 && items[currentFocus]) {
                        items[currentFocus].classList.add('active-item');
                        items[currentFocus].scrollIntoView({ block: 'nearest' });
                    }
                }

                document.addEventListener('click', function (e) {
                    if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                        dropdown.classList.remove('active');
                    }
                });
            }

            // 1. Configurar Autocompletado de Marcas
            setupCustomAutocomplete(
                inputMarca,
                dropdownMarcas,
                function (query) {
                    const all = Object.keys(catalogo_vehiculos).sort();
                    if (!query || !query.trim()) return all;
                    const q = query.trim().toLowerCase();
                    return all.filter(m => m.toLowerCase().includes(q));
                },
                function (marcaElegida) {
                    const marcaMatch = Object.keys(catalogo_vehiculos).find(
                        m => m.toLowerCase() === marcaElegida.toLowerCase()
                    );
                    const modelos = (marcaMatch && catalogo_vehiculos[marcaMatch]) || [];
                    if (inputModelo.value && !modelos.includes(inputModelo.value)) {
                        inputModelo.value = '';
                    }
                    setTimeout(function () {
                        inputModelo.focus();
                    }, 50);
                }
            );

            // 2. Configurar Autocompletado de Modelos
            setupCustomAutocomplete(
                inputModelo,
                dropdownModelos,
                function (query) {
                    const marcaActual = (inputMarca.value || '').trim();
                    const marcaMatch = Object.keys(catalogo_vehiculos).find(
                        m => m.toLowerCase() === marcaActual.toLowerCase()
                    );
                    const modelosDisponibles = (marcaMatch && catalogo_vehiculos[marcaMatch]) ? catalogo_vehiculos[marcaMatch].slice().sort() : [];

                    if (!query || !query.trim()) return modelosDisponibles;
                    const q = query.trim().toLowerCase();
                    return modelosDisponibles.filter(mod => mod.toLowerCase().includes(q));
                },
                function (modeloElegido) {
                    if (!inputMarca.value.trim()) {
                        for (const [m, mods] of Object.entries(catalogo_vehiculos)) {
                            if (mods.includes(modeloElegido)) {
                                inputMarca.value = m;
                                break;
                            }
                        }
                    }
                }
            );

            // Limpiar modelo si se cambia manualmente la marca y deja de ser compatible
            inputMarca.addEventListener('change', function () {
                const marcaActual = (inputMarca.value || '').trim();
                const marcaMatch = Object.keys(catalogo_vehiculos).find(
                    m => m.toLowerCase() === marcaActual.toLowerCase()
                );
                const modelos = (marcaMatch && catalogo_vehiculos[marcaMatch]) || [];
                if (inputModelo.value && !modelos.includes(inputModelo.value)) {
                    inputModelo.value = '';
                }
            });
        }


        // ==========================================================================
        // Algoritmo de Similitud (Levenshtein) y Confirmación Inteligente de Catálogo
        // ==========================================================================

        // Normaliza texto eliminando acentos/tildes y convirtiendo a minúsculas
        function normalizarTexto(str) {
            return (str || '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
        }

        // 1. Función que calcula cuántas letras de diferencia hay entre dos palabras
        function distanciaLevenshtein(a, b) {
            const s1 = normalizarTexto(a);
            const s2 = normalizarTexto(b);
            const costs = [];
            for (let i = 0; i <= s1.length; i++) {
                let lastValue = i;
                for (let j = 0; j <= s2.length; j++) {
                    if (i === 0) {
                        costs[j] = j;
                    } else if (j > 0) {
                        let newValue = costs[j - 1];
                        if (s1.charAt(i - 1) !== s2.charAt(j - 1)) {
                            newValue = Math.min(Math.min(newValue, lastValue), costs[j]) + 1;
                        }
                        costs[j - 1] = lastValue;
                        lastValue = newValue;
                    }
                }
                if (i > 0) costs[s2.length] = lastValue;
            }
            return costs[s2.length];
        }

        // 2. Interceptar el formulario de creación de vehículo
        const vehForm = document.getElementById('tallerVehiculoForm');
        const modalCat = document.getElementById('catalogoConfirmModal');
        const modalTitle = document.getElementById('catalogoModalTitle');
        const modalDesc = document.getElementById('catalogoModalDesc');
        const btnOpcion1 = document.getElementById('btnCatalogoOpcion1');
        const btnOpcion2 = document.getElementById('btnCatalogoOpcion2');
        const hiddenGuardarCat = document.getElementById('veh_guardar_en_catalogo');

        if (vehForm && modalCat) {
            vehForm.addEventListener('submit', function (e) {
                // Si ya fue validado y confirmado, dejamos que se envíe normalmente
                if (vehForm.dataset.validado === 'true') {
                    return true;
                }

                const marcaEscrita = (inputMarca.value || '').trim();
                const modeloEscrito = (inputModelo.value || '').trim();
                const catActual = catalogo_vehiculos || catalogoVehiculos || {};

                // Si no hay catálogo cargado o campos vacíos, dejamos que el formulario valide por defecto
                if (!catActual || Object.keys(catActual).length === 0 || !marcaEscrita || !modeloEscrito) {
                    return true;
                }

                const marcasDisponibles = Object.keys(catActual);
                const marcaCoincidente = marcasDisponibles.find(
                    m => normalizarTexto(m) === normalizarTexto(marcaEscrita)
                );

                // --- CASO 1: POSIBLE ERRATA EN LA MARCA (ej: "renaul" -> "Renault") ---
                if (!marcaCoincidente) {
                    let marcaSugerida = null;
                    for (const b of marcasDisponibles) {
                        const dist = distanciaLevenshtein(marcaEscrita, b);
                        // Si la diferencia es de 1 o 2 letras, es casi seguro una errata
                        if (dist > 0 && dist <= (marcaEscrita.length > 5 ? 2 : 1)) {
                            marcaSugerida = b;
                            break;
                        }
                    }

                    if (marcaSugerida) {
                        e.preventDefault();
                        modalTitle.textContent = '¿Quisiste decir ' + marcaSugerida + '?';
                        modalDesc.innerHTML = 'Has escrito <strong>"' + marcaEscrita + '"</strong>, pero parece una errata de la marca <strong>"' + marcaSugerida + '"</strong>.<br><br>¿Deseas corregirla antes de continuar?';
                        btnOpcion1.textContent = 'Corregir a ' + marcaSugerida;
                        btnOpcion2.textContent = 'No, es otra marca';

                        btnOpcion1.onclick = function () {
                            inputMarca.value = marcaSugerida;
                            modalCat.classList.remove('active');
                            // Volvemos a lanzar la validación con la marca ya corregida
                            vehForm.dispatchEvent(new Event('submit', { cancelable: true }));
                        };

                        btnOpcion2.onclick = function () {
                            modalCat.classList.remove('active');
                            // El usuario insiste en que es una marca nueva: enviar directamente
                            vehForm.dataset.validado = 'true';
                            vehForm.submit();
                        };

                        modalCat.classList.add('active');
                        return false;
                    }
                }

                // --- CASO 2: MODELO NUEVO (La marca existe, pero el modelo no está en su lista) ---
                const marcaFinal = marcaCoincidente || marcaEscrita;
                const modelosDeMarca = catActual[marcaFinal] || [];

                // Usamos .find() para capturar exactamente cómo está escrito en el catálogo (ej: "kj" o "Mégane")
                const modeloCoincidente = modelosDeMarca.find(
                    mod => normalizarTexto(mod) === normalizarTexto(modeloEscrito)
                );

                if (!modeloCoincidente) {
                    // Si NO existe en el catálogo, mostramos el modal para preguntar si guardarlo
                    e.preventDefault();
                    modalTitle.textContent = 'Nuevo modelo detectado';
                    modalDesc.innerHTML = 'El modelo <strong>"' + modeloEscrito + '"</strong> no está en el catálogo de <strong>"' + marcaFinal + '"</strong>.<br><br>¿Quieres guardarlo en el catálogo para que aparezca en futuras altas de vehículos?';
                    btnOpcion1.textContent = 'Guardar en catálogo';
                    btnOpcion2.textContent = 'Solo este vehículo';

                    btnOpcion1.onclick = function () {
                        if (hiddenGuardarCat) hiddenGuardarCat.value = '1';
                        modalCat.classList.remove('active');
                        vehForm.dataset.validado = 'true';
                        vehForm.submit();
                    };

                    btnOpcion2.onclick = function () {
                        if (hiddenGuardarCat) hiddenGuardarCat.value = '0';
                        modalCat.classList.remove('active');
                        vehForm.dataset.validado = 'true';
                        vehForm.submit();
                    };

                    modalCat.classList.add('active');
                    return false;
                }

                // --- CASO 3: TODO EXISTE EN EL CATÁLOGO ---
                // Auto-corregimos los campos con las mayúsculas/minúsculas oficiales del catálogo antes de guardar
                if (marcaCoincidente) {
                    inputMarca.value = marcaCoincidente;
                }
                if (modeloCoincidente) {
                    inputModelo.value = modeloCoincidente; // <-- Si pusiste "KJ", aquí se cambia automáticamente a "kj"
                }
                return true;
            });
        }

        // ==========================================================================
        // VISTA CLIENTES: Control del Modal de Alta y Edición
        // ==========================================================================
        const clientModal = document.getElementById('tallerClientModal');
        const clientForm = document.getElementById('tallerClientForm');
        const clientTitle = document.getElementById('clientModalTitle');
        const clientAction = document.getElementById('clientFormAction');
        const clientSocid = document.getElementById('client_socid');
        const btnAddClient = document.getElementById('tallerOpenModalBtn');

        // Al pulsar "+ Añadir": preparar modal limpio para crear
        if (btnAddClient && clientForm) {
            btnAddClient.addEventListener('click', function () {
                clientForm.reset();
                if (clientTitle) clientTitle.textContent = 'Nuevo registro de cliente';
                if (clientAction) clientAction.value = 'create_client';
                if (clientSocid) clientSocid.value = '0';
            });
        }

        // Al pulsar el botón "Editar" en la tabla/tarjetas: rellenar datos y abrir modal
        document.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.btn-edit-client');
            if (!editBtn) return;
            e.preventDefault();

            const modal = document.getElementById('tallerClientModal');
            const title = document.getElementById('clientModalTitle');
            const action = document.getElementById('clientFormAction');
            const socid = document.getElementById('client_socid');

            const id = editBtn.getAttribute('data-id') || '0';
            const name = editBtn.getAttribute('data-name') || '';
            const phone = editBtn.getAttribute('data-phone') || '';
            const email = editBtn.getAttribute('data-email') || '';
            const cif = editBtn.getAttribute('data-cif') || '';
            const address = editBtn.getAttribute('data-address') || '';
            const notes = editBtn.getAttribute('data-notes') || '';

            if (title) title.textContent = 'Editar cliente';
            if (action) action.value = 'update_client';
            if (socid) socid.value = id;

            const inputName = document.getElementById('client_name');
            const inputPhone = document.getElementById('client_phone');
            const inputEmail = document.getElementById('client_email');
            const inputCif = document.getElementById('client_cif');
            const inputAddress = document.getElementById('client_address');
            const inputNotes = document.getElementById('client_notes');

            if (inputName) inputName.value = name;
            if (inputPhone) inputPhone.value = phone;
            if (inputEmail) inputEmail.value = email;
            if (inputCif) inputCif.value = cif;
            if (inputAddress) inputAddress.value = address;
            if (inputNotes) inputNotes.value = notes;

            if (modal) modal.classList.add('active');
        });

        // =========================================================================
        // Modal "Emitir Factura Ahora": guarda los datos y genera la factura oficial en Dolibarr
        // =========================================================================
        const btnModalEmitirFactura = document.getElementById('btnModalEmitirFactura');
        if (btnModalEmitirFactura && repForm) {
            btnModalEmitirFactura.addEventListener('click', function (e) {
                e.preventDefault();
                const vehSelect = document.getElementById('orderVehiculoSelect');
                if (!vehSelect || !vehSelect.value || parseInt(vehSelect.value) <= 0) {
                    alert('Debe seleccionar un vehículo válido para poder emitir la factura.');
                    return;
                }
                if (!confirm('¿Deseas guardar los cambios y emitir la factura oficial de Dolibarr para esta reparación?')) {
                    return;
                }
                const actionInput = document.getElementById('reparacionFormAction');
                if (actionInput) actionInput.value = 'save_and_facturar';

                const estadoSelect = document.getElementById('orderEstadoSelect');
                if (estadoSelect) estadoSelect.value = 'finalizada';

                recalculateTotals();
                repForm.submit();
            });
        }

        // =========================================================================
        // Dashboard Global Search (In-Page integrated results)
        // =========================================================================
        const globalSearchInput = document.getElementById('tallerGlobalSearchInput');
        const searchResultsContainer = document.getElementById('tallerDashboardSearchResults');
        const defaultViewContainer = document.getElementById('tallerDashboardDefaultView');

        if (globalSearchInput && searchResultsContainer && defaultViewContainer) {
            let debounceTimer = null;

            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            const iconVehiculo = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 3.1C2 11.4 2 11.7 2 12v4c0 .6.4 1 1 1h2"></path><circle cx="7" cy="17" r="2"></circle><path d="M9 17h6"></path><circle cx="17" cy="17" r="2"></circle></svg>';
            const iconCliente = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>';
            const iconMaterial = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>';
            const iconFactura = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>';

            function renderSection(title, iconSvg, items, defaultUrl) {
                const count = Array.isArray(items) ? items.length : 0;
                let itemsHtml = '';

                if (count === 0) {
                    itemsHtml = '<div class="tallerpro-search-empty-text">Sin coincidencias</div>';
                } else {
                    itemsHtml = '<div class="tallerpro-search-items-list">' +
                        items.map(function (item) {
                            const url = item.url || defaultUrl || '#';
                            return '<a href="' + url + '" class="tallerpro-search-item-card">' +
                                '<div class="tallerpro-search-item-title">' + escapeHtml(item.title) + '</div>' +
                                (item.subtitle ? '<div class="tallerpro-search-item-sub">' + escapeHtml(item.subtitle) + '</div>' : '') +
                                '</a>';
                        }).join('') +
                        '</div>';
                }

                return '<div class="tallerpro-search-section-card">' +
                    '<div class="tallerpro-search-section-header">' +
                    '<div class="tallerpro-search-section-title">' +
                    '<span class="tallerpro-search-section-icon">' + iconSvg + '</span>' +
                    '<span>' + escapeHtml(title) + '</span>' +
                    '</div>' +
                    '<span class="tallerpro-search-count-badge">' + count + '</span>' +
                    '</div>' +
                    itemsHtml +
                    '</div>';
            }

            function resetDashboardView() {
                searchResultsContainer.style.display = 'none';
                searchResultsContainer.innerHTML = '';
                defaultViewContainer.style.display = 'block';
            }

            globalSearchInput.addEventListener('input', function () {
                const query = globalSearchInput.value.trim();
                clearTimeout(debounceTimer);

                if (query.length === 0) {
                    resetDashboardView();
                    return;
                }

                // Switch view immediately to search mode
                defaultViewContainer.style.display = 'none';
                searchResultsContainer.style.display = 'flex';

                debounceTimer = setTimeout(function () {
                    fetch('tallerindex.php?action=search_all&q=' + encodeURIComponent(query))
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (globalSearchInput.value.trim() !== query) {
                                return; // Input changed while request was in-flight
                            }

                            let html = '';
                            // 1. Vehículos
                            html += renderSection('Vehículos', iconVehiculo, data.vehiculos || []);
                            // 2. Clientes
                            html += renderSection('Clientes', iconCliente, data.clientes || []);
                            // 3. Materiales
                            html += renderSection('Materiales', iconMaterial, data.materiales || []);
                            // 4. Facturas (if matching invoices exist)
                            if (data.facturas && data.facturas.length > 0) {
                                html += renderSection('Facturas', iconFactura, data.facturas);
                            }

                            searchResultsContainer.innerHTML = html;
                        })
                        .catch(function (err) {
                            console.error('Error fetching search results:', err);
                        });
                }, 150);
            });

            // ESC key resets search
            globalSearchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    globalSearchInput.value = '';
                    resetDashboardView();
                    globalSearchInput.blur();
                }
            });
        }

    }); // Cierre DOMContentLoaded

} // Cierre if window.__tallerProLoaded






