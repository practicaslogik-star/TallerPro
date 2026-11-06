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

        // Auto-activate tab from URL query param or hash (e.g., ?tab=seguros or #tab-materiales)
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const queryTab = urlParams.get('tab');
            const hashTab = window.location.hash ? window.location.hash.replace('#', '') : '';
            const activeTabName = hashTab || queryTab;
            if (activeTabName) {
                const targetBtn = document.querySelector(`.tallerpro-tab-btn[data-tab="${activeTabName}"]`) ||
                                  document.querySelector(`.tallerpro-tab-btn[data-tab="tab-${activeTabName}"]`);
                if (targetBtn) {
                    targetBtn.click();
                }
            }
        } catch (err) {}

        // Toggle de subida de documentos en ficha de vehículo
        const btnToggleUploadDoc = document.getElementById('btnToggleUploadDoc');
        const uploadDocBox = document.getElementById('tallerproUploadDocBox');
        const btnCancelUploadDoc = document.getElementById('btnCancelUploadDoc');
        if (btnToggleUploadDoc && uploadDocBox) {
            btnToggleUploadDoc.addEventListener('click', function () {
                if (uploadDocBox.style.display === 'none' || !uploadDocBox.style.display) {
                    uploadDocBox.style.display = 'block';
                } else {
                    uploadDocBox.style.display = 'none';
                }
            });
        }
        if (btnCancelUploadDoc && uploadDocBox) {
            btnCancelUploadDoc.addEventListener('click', function () {
                uploadDocBox.style.display = 'none';
            });
        }

        // Clientes Modal handling (Create & Edit)
        const clientModal = document.getElementById('tallerClientModal');
        const clientForm = document.getElementById('tallerClientForm');
        const clientModalTitle = document.getElementById('tallerClientModalTitle');
        const clientFormAction = document.getElementById('clientFormAction');
        const clientFormSocid = document.getElementById('clientFormSocid');
        const addClientBtn = document.getElementById('tallerOpenModalBtn');

        if (addClientBtn && clientForm && clientModal) {
            addClientBtn.addEventListener('click', function () {
                clientForm.reset();
                if (clientModalTitle) clientModalTitle.textContent = 'Nuevo registro de cliente';
                if (clientFormAction) clientFormAction.value = 'create_client';
                if (clientFormSocid) clientFormSocid.value = '0';
            });
        }

        const editClientBtns = document.querySelectorAll('.btn-edit-client');
        editClientBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (!clientModal || !clientForm) return;

                if (clientModalTitle) clientModalTitle.textContent = 'Editar cliente';
                if (clientFormAction) clientFormAction.value = 'update_client';
                if (clientFormSocid) clientFormSocid.value = btn.dataset.id || '0';

                const clientName = document.getElementById('client_name');
                const clientPhone = document.getElementById('client_phone');
                const clientEmail = document.getElementById('client_email');
                const clientCif = document.getElementById('client_cif');
                const clientAddress = document.getElementById('client_address');
                const clientNotes = document.getElementById('client_notes');

                if (clientName) clientName.value = btn.dataset.name || '';
                if (clientPhone) clientPhone.value = btn.dataset.phone || '';
                if (clientEmail) clientEmail.value = btn.dataset.email || '';
                if (clientCif) clientCif.value = btn.dataset.cif || '';
                if (clientAddress) clientAddress.value = btn.dataset.address || '';
                if (clientNotes) clientNotes.value = btn.dataset.notes || '';

                clientModal.classList.add('active');
                if (clientName) clientName.focus();
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
                const segTipo = document.getElementById('seg_tipo');
                if (segTipo) segTipo.value = 'Otro / No especificado';
            });
        }

        const segVehiculo = document.getElementById('seg_fk_vehiculo');
        if (segVehiculo && segVehiculo.dataset.preselected && segModal && segForm) {
            segForm.reset();
            segVehiculo.value = segVehiculo.dataset.preselected;
            if (segModalTitle) segModalTitle.textContent = 'Nuevo registro';
            if (segFormAction) segFormAction.value = 'create_seguro';
            if (segFormRowid) segFormRowid.value = '0';
            const segTipo = document.getElementById('seg_tipo');
            if (segTipo) segTipo.value = 'Otro / No especificado';
            segModal.classList.add('active');
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
                if (segTipo) {
                    const tipoVal = (btn.dataset.tipo || '').trim();
                    let found = false;
                    for (let i = 0; i < segTipo.options.length; i++) {
                        if (segTipo.options[i].value === tipoVal) {
                            segTipo.selectedIndex = i;
                            found = true;
                            break;
                        }
                    }
                    if (!found) {
                        segTipo.value = 'Otro / No especificado';
                    }
                }
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

        const facSoc = document.getElementById('fac_fk_soc');
        const facVehiculo = document.getElementById('fac_fk_vehiculo');

        // Caché de todos los vehículos con su cliente asociado (data-soc)
        let allVehiclesCache = [];
        if (facVehiculo) {
            allVehiclesCache = Array.from(facVehiculo.options).map(function (opt) {
                return {
                    value: opt.value,
                    text: opt.textContent,
                    soc: opt.getAttribute('data-soc') || ''
                };
            });
        }

        function filterFacturaVehiclesByClient(selectedSocId, keepVehiculoId) {
            if (!facVehiculo || allVehiclesCache.length === 0) return;

            const socStr = selectedSocId ? String(selectedSocId).trim() : '';
            const targetVehStr = (keepVehiculoId !== undefined && keepVehiculoId !== null) ? String(keepVehiculoId).trim() : String(facVehiculo.value || '').trim();

            facVehiculo.innerHTML = '';

            // Opción por defecto
            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = '— Selecciona —';
            facVehiculo.appendChild(defaultOpt);

            let matchFound = false;

            allVehiclesCache.forEach(function (item) {
                if (!item.value) return;

                const belongsToClient = (socStr !== '' && item.soc === socStr);
                const isTargetVeh = (targetVehStr !== '' && item.value === targetVehStr);

                // Si no hay cliente seleccionado, muestra todos los vehículos.
                // Si hay cliente seleccionado, solo muestra los vehículos de ese cliente (o el actualmente asignado).
                if (!socStr || belongsToClient || isTargetVeh) {
                    const opt = document.createElement('option');
                    opt.value = item.value;
                    opt.textContent = item.text;
                    opt.setAttribute('data-soc', item.soc);
                    if (targetVehStr !== '' && item.value === targetVehStr) {
                        opt.selected = true;
                        matchFound = true;
                    }
                    facVehiculo.appendChild(opt);
                }
            });

            if (!matchFound) {
                facVehiculo.value = '';
            }
        }

        if (facSoc) {
            facSoc.addEventListener('change', function () {
                filterFacturaVehiclesByClient(facSoc.value);
            });
        }

        if (facVehiculo) {
            facVehiculo.addEventListener('change', function () {
                // Si el usuario elige un vehículo y aún no ha seleccionado cliente, auto-seleccionar el cliente asociado
                if (facSoc && !facSoc.value && facVehiculo.value) {
                    const selectedOpt = facVehiculo.selectedOptions[0];
                    const vSoc = selectedOpt ? selectedOpt.getAttribute('data-soc') : '';
                    if (vSoc) {
                        facSoc.value = vSoc;
                        filterFacturaVehiclesByClient(vSoc, facVehiculo.value);
                    }
                }
            });
        }

        function resetFacturaFormStyles() {
            const facEditNotice = document.getElementById('facEditNotice');
            if (facEditNotice) facEditNotice.style.display = 'none';

            if (facFecha) {
                facFecha.readOnly = false;
                facFecha.disabled = false;
                facFecha.style.pointerEvents = '';
                facFecha.style.backgroundColor = '';
                facFecha.style.color = '';
            }
            if (facSoc) {
                facSoc.disabled = false;
                facSoc.style.pointerEvents = '';
                facSoc.style.backgroundColor = '';
                facSoc.style.color = '';
            }
            if (facVehiculo) {
                facVehiculo.disabled = false;
                facVehiculo.style.pointerEvents = '';
                facVehiculo.style.backgroundColor = '';
                facVehiculo.style.color = '';
            }
            const facTotal = document.getElementById('fac_total');
            if (facTotal) {
                facTotal.readOnly = false;
                facTotal.disabled = false;
                facTotal.style.pointerEvents = '';
                facTotal.style.backgroundColor = '';
                facTotal.style.color = '';
            }
            const facNotas = document.getElementById('fac_notas');
            if (facNotas) {
                facNotas.readOnly = false;
                facNotas.disabled = false;
                facNotas.style.pointerEvents = '';
                facNotas.style.backgroundColor = '';
                facNotas.style.color = '';
            }
            const facEstado = document.getElementById('fac_estado');
            if (facEstado) {
                facEstado.disabled = false;
                facEstado.style.borderColor = '';
                facEstado.style.boxShadow = '';
            }
        }

        if (addFacBtn && facForm) {
            addFacBtn.addEventListener('click', function () {
                facForm.reset();
                resetFacturaFormStyles();
                if (facModalTitle) facModalTitle.textContent = 'Nuevo registro';
                if (facFormAction) facFormAction.value = 'create_factura';
                if (facFormRowid) facFormRowid.value = '0';
                filterFacturaVehiclesByClient('');
                const todayStr = new Date().toISOString().split('T')[0];
                if (facFecha) {
                    facFecha.value = todayStr;
                }
                const facEstado = document.getElementById('fac_estado');
                if (facEstado) {
                    facEstado.value = 'Borrador';
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

                const isPresupuesto = (btn.dataset.tipoDoc === 'presupuesto');

                if (facModalTitle) facModalTitle.textContent = isPresupuesto ? 'Editar presupuesto' : 'Editar factura';
                if (facFormAction) facFormAction.value = 'update_factura';
                if (facFormRowid) facFormRowid.value = btn.dataset.id || '0';

                const facTotal = document.getElementById('fac_total');
                const facEstado = document.getElementById('fac_estado');
                const facNotas = document.getElementById('fac_notas');
                const facEditNotice = document.getElementById('facEditNotice');

                if (facNumero) {
                    facNumero.value = btn.dataset.numero || '';
                    facNumero.readOnly = true;
                }

                // Fecha: editable
                if (facFecha) {
                    facFecha.value = btn.dataset.fecha || '';
                    facFecha.readOnly = false;
                    facFecha.disabled = false;
                    facFecha.style.pointerEvents = '';
                    facFecha.style.backgroundColor = '#ffffff';
                    facFecha.style.color = '#0f172a';
                }

                const clientSocId = btn.dataset.soc || '';
                const vehicleId = btn.dataset.vehiculo || '';

                // Cliente y Vehículo permanecen bloqueados (solo lectura)
                if (facSoc) {
                    facSoc.value = clientSocId;
                    facSoc.disabled = true;
                    facSoc.style.pointerEvents = 'none';
                    facSoc.style.backgroundColor = '#f8fafc';
                    facSoc.style.color = '#64748b';
                }
                filterFacturaVehiclesByClient(clientSocId, vehicleId);
                if (facVehiculo) {
                    facVehiculo.disabled = true;
                    facVehiculo.style.pointerEvents = 'none';
                    facVehiculo.style.backgroundColor = '#f8fafc';
                    facVehiculo.style.color = '#64748b';
                }

                // Total: bloqueado (calculado por las líneas de factura)
                if (facTotal) {
                    facTotal.value = btn.dataset.total || '0.00';
                    facTotal.readOnly = true;
                    facTotal.style.pointerEvents = 'none';
                    facTotal.style.backgroundColor = '#f8fafc';
                    facTotal.style.color = '#64748b';
                }

                // Notas: editable
                if (facNotas) {
                    facNotas.value = btn.dataset.notas || '';
                    facNotas.readOnly = false;
                    facNotas.disabled = false;
                    facNotas.style.pointerEvents = '';
                    facNotas.style.backgroundColor = '#ffffff';
                    facNotas.style.color = '#0f172a';
                }

                // Estado: editable para facturas normales, oculto/sin estado para presupuestos
                if (facEstado) {
                    const estadoGroup = facEstado.closest('.tallerpro-form-group');
                    if (isPresupuesto) {
                        if (estadoGroup) estadoGroup.style.display = 'none';
                        facEstado.value = 'Borrador';
                    } else {
                        if (estadoGroup) estadoGroup.style.display = '';
                        facEstado.value = btn.dataset.estado || 'Borrador';
                        facEstado.disabled = false;
                        facEstado.style.pointerEvents = '';
                        facEstado.style.backgroundColor = '#ffffff';
                        facEstado.style.color = '#0f172a';
                        facEstado.style.borderColor = '#059669';
                        facEstado.style.boxShadow = '0 0 0 2px rgba(5, 150, 105, 0.15)';
                    }
                }

                if (facEditNotice) {
                    facEditNotice.style.display = 'block';
                    if (isPresupuesto) {
                        facEditNotice.innerHTML = `
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            <strong>Edición de presupuesto:</strong> Documento enlazado a la reparación, editable en todo momento sin estado rígido.
                        `;
                    } else {
                        facEditNotice.innerHTML = `
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            <strong>Edición de factura:</strong> Es posible modificar la fecha, las notas y el estado.
                        `;
                    }
                }

                facModal.classList.add('active');
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

        // (El modal de detalle de facturas ha sido eliminado; la gestión de líneas y totales se realiza centralizadamente en reparaciones)

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

        // Auto-display flash toast from URL query params (e.g. ?msg=saved)
        const repUrlParams = new URLSearchParams(window.location.search);
        if (repUrlParams.has('msg')) {
            const msgCode = repUrlParams.get('msg');
            let flashMsg = '';
            if (msgCode === 'saved') {
                flashMsg = 'Reparación guardada';
            } else if (msgCode === 'deleted') {
                flashMsg = 'Reparación eliminada correctamente';
            } else if (msgCode === 'status_updated') {
                flashMsg = 'Estado de la reparación actualizado correctamente';
            }
            if (flashMsg) {
                window.showTallerToast(flashMsg);
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

        // Modal Confirmación de Eliminación Global (Reparaciones, Clientes, Vehículos, Materiales, Seguros, Facturas)
        let pendingDeleteAction = null;
        let pendingDeleteUrl = null;
        let pendingDeleteRow = null;
        let pendingDeleteRowId = null;

        document.addEventListener('click', function (e) {
            // Caso 1: Reparaciones AJAX delete
            const delRepBtn = e.target.closest('.btn-delete-reparacion');
            if (delRepBtn) {
                e.preventDefault();
                pendingDeleteAction = 'delete_reparacion';
                pendingDeleteRow = delRepBtn.closest('.rep-row');
                pendingDeleteRowId = delRepBtn.getAttribute('data-id');
                pendingDeleteUrl = null;
                const modal = document.getElementById('deleteConfirmModal');
                if (modal) modal.classList.add('active');
                return;
            }

            // Caso 2: Enlace/botón de eliminación en Clientes, Vehículos, Materiales, Seguros, Facturas
            const delActionBtn = e.target.closest('.btn-delete-action, a[href*="delete_client"], a[href*="delete_vehiculo"], a[href*="delete_material"], a[href*="delete_seguro"], a[href*="delete_factura"]');
            if (delActionBtn) {
                e.preventDefault();
                pendingDeleteAction = delActionBtn.getAttribute('data-action') || null;
                pendingDeleteRowId = delActionBtn.getAttribute('data-id') || null;
                pendingDeleteUrl = delActionBtn.getAttribute('data-delete-url') || (delActionBtn.tagName === 'A' ? delActionBtn.getAttribute('href') : null);
                pendingDeleteRow = delActionBtn.closest('tr');
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
                pendingDeleteAction = null;
                pendingDeleteUrl = null;
                pendingDeleteRow = null;
                pendingDeleteRowId = null;
            } else if (modal && e.target === modal) {
                modal.classList.remove('active');
                pendingDeleteAction = null;
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

            // Caso A: Si existe el formulario oculto global (Clientes, Vehículos, Materiales, Seguros, Facturas) y no es borrado AJAX de reparaciones
            const globalDeleteForm = document.getElementById('tallerGlobalDeleteForm');
            if (globalDeleteForm && pendingDeleteAction && pendingDeleteAction !== 'delete_reparacion') {
                const actionInput = document.getElementById('globalDeleteAction');
                const rowidInput = document.getElementById('globalDeleteRowid');
                const socidInput = document.getElementById('globalDeleteSocid');
                if (actionInput) actionInput.value = pendingDeleteAction;
                if (rowidInput) rowidInput.value = pendingDeleteRowId || '';
                if (socidInput) socidInput.value = pendingDeleteRowId || '';
                globalDeleteForm.submit();
                return;
            }

            // Fallback para URL legacy si no hay formulario
            if (pendingDeleteUrl && !globalDeleteForm) {
                window.location.href = pendingDeleteUrl;
                return;
            }

            // Caso B: Eliminación por AJAX (Reparaciones)
            const row = pendingDeleteRow;
            const rowid = pendingDeleteRowId;
            const tokenInput = document.querySelector('#tallerGlobalDeleteForm input[name="token"]') || document.getElementById('reparacionFormToken');
            const token = tokenInput ? tokenInput.value : '';

            if (rowid && !isNaN(rowid) && parseInt(rowid, 10) > 0) {
                const formData = new FormData();
                formData.append('action', 'delete_reparacion');
                formData.append('ajax', '1');
                formData.append('rowid', rowid);
                if (token) formData.append('token', token);

                fetch('reparaciones.php', {
                    method: 'POST',
                    body: formData
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
            pendingDeleteAction = null;
            pendingDeleteUrl = null;
            pendingDeleteRow = null;
            pendingDeleteRowId = null;
        });

        // Modal Confirmación de Facturación
        let pendingFacturarUrl = null;

        window.openFacturarModal = function (btn) {
            if (!btn) return;
            const url = btn.getAttribute('data-facturar-url') || btn.getAttribute('href');
            window.__tallerPendingFacturarUrl = url;
            pendingFacturarUrl = url;
            const modal = document.getElementById('facturarConfirmModal');
            if (modal) {
                modal.classList.add('active');
            } else if (url && confirm('¿Deseas facturar esta reparación? Se generará la factura en estado borrador en Dolibarr.')) {
                window.location.href = url;
            }
        };

        document.addEventListener('click', function (e) {
            const facturarBtn = e.target.closest('.tallerpro-btn-facturar-action');
            if (facturarBtn) {
                e.preventDefault();
                window.openFacturarModal(facturarBtn);
            }
        });

        document.addEventListener('click', function (e) {
            const cancelBtn = e.target.closest('#btnCancelFacturar');
            const modal = document.getElementById('facturarConfirmModal');
            if (cancelBtn && modal) {
                modal.classList.remove('active');
                window.__tallerPendingFacturarUrl = null;
                pendingFacturarUrl = null;
            } else if (modal && e.target === modal) {
                modal.classList.remove('active');
                window.__tallerPendingFacturarUrl = null;
                pendingFacturarUrl = null;
            }
        });

        document.addEventListener('click', function (e) {
            const confirmBtn = e.target.closest('#btnConfirmFacturar');
            const modal = document.getElementById('facturarConfirmModal');
            if (!confirmBtn || !modal) return;
            e.preventDefault();

            modal.classList.remove('active');

            const targetUrl = window.__tallerPendingFacturarUrl || pendingFacturarUrl;
            if (targetUrl) {
                window.__tallerPendingFacturarUrl = null;
                pendingFacturarUrl = null;
                window.location.href = targetUrl;
            }
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

        // Cambio de estado de reparación directamente desde el selector de la tabla
        document.addEventListener('change', function (e) {
            const statusSelect = e.target.closest('.tallerpro-status-select');
            if (!statusSelect) return;
            if (statusSelect.classList.contains('tallerpro-invoice-status-select')) return; // Manejado por el flujo de facturas

            const newEstado = statusSelect.value;
            const rowid = statusSelect.getAttribute('data-id');
            const tr = statusSelect.closest('tr');
            if (!tr || !rowid) return;

            // Actualizar apariencia visual del selector
            if (newEstado === 'finalizada') {
                statusSelect.classList.remove('status-en_curso');
                statusSelect.classList.add('status-finalizada');
            } else {
                statusSelect.classList.remove('status-finalizada');
                statusSelect.classList.add('status-en_curso');
            }

            // Actualizar atributos de la fila para filtros y edición
            tr.setAttribute('data-estado', newEstado);
            const editBtn = tr.querySelector('.btn-edit-reparacion');
            if (editBtn) {
                editBtn.setAttribute('data-estado', newEstado);
            }

            // Actualizar botones de acción (Facturar)
            const actionsDiv = tr.querySelector('.tallerpro-actions');
            const existingFacturarBtn = tr.querySelector('.tallerpro-btn-facturar-action');
            const hasFactura = tr.getAttribute('data-has-factura') === '1';

            if (newEstado === 'finalizada' && !hasFactura) {
                if (!existingFacturarBtn && actionsDiv) {
                    const facturarBtn = document.createElement('button');
                    facturarBtn.type = 'button';
                    const targetUrl = tr.getAttribute('data-facturar-url') || (window.location.pathname + '?action=facturar_reparacion&rowid=' + rowid);
                    facturarBtn.setAttribute('data-facturar-url', targetUrl);
                    facturarBtn.className = 'tallerpro-btn-facturar-action';
                    facturarBtn.title = 'Emitir Factura oficial de esta reparación';
                    facturarBtn.onclick = function () { window.openFacturarModal(this); };
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
            } else if (newEstado === 'en_curso') {
                if (existingFacturarBtn) {
                    existingFacturarBtn.remove();
                }
            }

            // Si hay un filtro de estado activo, re-filtrar
            if (stateFilter && stateFilter.value !== 'all') {
                filterReparaciones();
            }

            // Persistir en backend vía POST
            const tokenInput = document.getElementById('reparacionFormToken') || document.querySelector('#tallerGlobalDeleteForm input[name="token"]');
            const token = tokenInput ? tokenInput.value : '';
            const formData = new FormData();
            formData.append('action', 'update_estado');
            formData.append('rowid', rowid);
            formData.append('new_estado', newEstado);
            formData.append('ajax', '1');
            if (token) formData.append('token', token);

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            }).then(function (res) {
                return res.json();
            }).then(function () {
                if (typeof window.showTallerToast === 'function') {
                    window.showTallerToast('Estado actualizado a ' + (newEstado === 'finalizada' ? 'Finalizada' : 'En curso'));
                }
            }).catch(function (err) {
                console.error('Error al actualizar estado:', err);
            });
        });

        // =========================================================================
        // Gestión de Cambio de Estado en Facturas con Pop-ups de Confirmación
        // =========================================================================
        let pendingInvoiceStateChange = null;

        document.addEventListener('change', function (e) {
            const invSelect = e.target.closest('.tallerpro-invoice-status-select');
            if (!invSelect) return;

            const rowid = invSelect.getAttribute('data-id');
            const oldEstado = invSelect.getAttribute('data-current-estado') || 'Borrador';
            const newEstado = invSelect.value;
            const ref = invSelect.getAttribute('data-ref') || '';
            const tr = invSelect.closest('tr');

            if (newEstado === oldEstado) return;

            // Regla de facturas: no se puede revertir una factura emitida a Borrador
            if (newEstado === 'Borrador') {
                invSelect.value = oldEstado;
                if (typeof window.showTallerToast === 'function') {
                    window.showTallerToast('Una factura emitida no puede volver a estado Borrador', 'error');
                }
                return;
            }

            pendingInvoiceStateChange = {
                select: invSelect,
                rowid: rowid,
                oldEstado: oldEstado,
                newEstado: newEstado,
                ref: ref,
                tr: tr
            };

            if (newEstado === 'Pendiente') {
                const modal = document.getElementById('pendienteConfirmModal');
                const desc = document.getElementById('pendienteModalDesc');
                if (desc) {
                    if (oldEstado === 'Borrador') {
                        desc.textContent = 'Se validará la factura borrador ' + (ref ? '(' + ref + ') ' : '') + 'y se le asignará su número fiscal oficial en Dolibarr.';
                    } else {
                        desc.textContent = 'La factura ' + (ref ? '(' + ref + ') ' : '') + 'se marcará como pendiente de cobro en el sistema.';
                    }
                }
                if (modal) modal.classList.add('active');
            } else if (newEstado === 'Pagada') {
                const modal = document.getElementById('pagadaConfirmModal');
                const desc = document.getElementById('pagadaModalDesc');
                if (desc) {
                    if (oldEstado === 'Borrador') {
                        desc.textContent = 'Se validará la factura oficial ' + (ref ? '(' + ref + ') ' : '') + 'y se registrará el cobro total en el sistema.';
                    } else {
                        desc.textContent = 'Se registrará el cobro completo de la factura ' + (ref ? '(' + ref + ') ' : '') + 'en el sistema.';
                    }
                }
                if (modal) modal.classList.add('active');
            }
        });

        // Cancelar / Rechazar cambio de estado en factura
        document.addEventListener('click', function (e) {
            const cancelPendiente = e.target.closest('#btnCancelPendiente');
            const modalPendiente = document.getElementById('pendienteConfirmModal');
            if (cancelPendiente || (modalPendiente && e.target === modalPendiente)) {
                if (modalPendiente) modalPendiente.classList.remove('active');
                if (pendingInvoiceStateChange && pendingInvoiceStateChange.select) {
                    pendingInvoiceStateChange.select.value = pendingInvoiceStateChange.oldEstado;
                }
                pendingInvoiceStateChange = null;
            }

            const cancelPagada = e.target.closest('#btnCancelPagada');
            const modalPagada = document.getElementById('pagadaConfirmModal');
            if (cancelPagada || (modalPagada && e.target === modalPagada)) {
                if (modalPagada) modalPagada.classList.remove('active');
                if (pendingInvoiceStateChange && pendingInvoiceStateChange.select) {
                    pendingInvoiceStateChange.select.value = pendingInvoiceStateChange.oldEstado;
                }
                pendingInvoiceStateChange = null;
            }
        });

        // Confirmar cambio de estado en factura (Pendiente o Pagada)
        document.addEventListener('click', function (e) {
            const confirmPendiente = e.target.closest('#btnConfirmPendiente');
            const confirmPagada = e.target.closest('#btnConfirmPagada');
            if (!confirmPendiente && !confirmPagada) return;

            if (!pendingInvoiceStateChange) return;

            const { select, rowid, oldEstado, newEstado, tr } = pendingInvoiceStateChange;
            const modalToClose = confirmPendiente ? document.getElementById('pendienteConfirmModal') : document.getElementById('pagadaConfirmModal');
            const clickedBtn = confirmPendiente || confirmPagada;
            clickedBtn.disabled = true;

            const tokenInput = document.querySelector('#tallerGlobalDeleteForm input[name="token"]');
            const token = tokenInput ? tokenInput.value : '';

            const formData = new FormData();
            formData.append('action', 'update_factura_estado');
            formData.append('rowid', rowid);
            formData.append('new_estado', newEstado);
            formData.append('ajax', '1');
            if (token) formData.append('token', token);

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                clickedBtn.disabled = false;
                if (modalToClose) modalToClose.classList.remove('active');

                if (data && data.success) {
                    const finalEstado = data.new_estado || newEstado;
                    select.setAttribute('data-current-estado', finalEstado);

                    // Actualizar estilos del selector
                    select.classList.remove('status-borrador', 'status-pendiente', 'status-pagada');
                    if (finalEstado === 'Pagada') {
                        select.classList.add('status-pagada');
                    } else if (finalEstado === 'Pendiente') {
                        select.classList.add('status-pendiente');
                    } else {
                        select.classList.add('status-borrador');
                    }

                    // Si dejó de ser borrador: actualizar opciones, número y deshabilitar edición/eliminación
                    if (!data.is_borrador) {
                        const borradorOpt = select.querySelector('option[value="Borrador"]');
                        if (borradorOpt) borradorOpt.remove();

                        if (data.ref && tr) {
                            const refLink = tr.querySelector('.factura-ref-link') || tr.querySelector('td:first-child a');
                            if (refLink) {
                                refLink.textContent = data.ref;
                            }
                            select.setAttribute('data-ref', data.ref);
                        }

                        if (tr) {
                            const editBtn = tr.querySelector('.btn-edit-factura');
                            if (editBtn) {
                                editBtn.disabled = true;
                                editBtn.classList.add('is-disabled');
                                editBtn.setAttribute('title', 'Solo se pueden editar facturas en estado Borrador');
                                const editSvg = editBtn.querySelector('svg');
                                if (editSvg) editSvg.setAttribute('stroke', '#cbd5e1');
                            }
                            const delBtn = tr.querySelector('.btn-delete-action');
                            if (delBtn) {
                                delBtn.disabled = true;
                                delBtn.classList.add('is-disabled');
                                delBtn.setAttribute('title', 'Solo se pueden eliminar facturas en estado Borrador');
                            }
                        }
                    }

                    if (tr) {
                        tr.setAttribute('data-estado', finalEstado);
                    }

                    if (typeof window.showTallerToast === 'function') {
                        window.showTallerToast('Estado de factura actualizado a ' + finalEstado);
                    }
                } else {
                    select.value = oldEstado;
                    if (typeof window.showTallerToast === 'function') {
                        window.showTallerToast((data && data.error) || 'Error al actualizar el estado de la factura', 'error');
                    }
                }
                pendingInvoiceStateChange = null;
            })
            .catch(function (err) {
                clickedBtn.disabled = false;
                if (modalToClose) modalToClose.classList.remove('active');
                select.value = oldEstado;
                console.error('Error al actualizar estado de factura:', err);
                if (typeof window.showTallerToast === 'function') {
                    window.showTallerToast('Error de conexión al actualizar estado', 'error');
                }
                pendingInvoiceStateChange = null;
            });
        });

        // =========================================================================
        // Gestión de la Orden de Reparación (Materiales, Mano de obra, Desglose y Totales)
        // =========================================================================
        let currentRepMaterials = [];
        let currentRepLabor = [];

        // Elementos de Materiales
        const repMatCatalogo = document.getElementById('rep_mat_catalogo');
        const repMatCodigo = document.getElementById('rep_mat_codigo');
        const repMatDescripcion = document.getElementById('rep_mat_descripcion');
        const repMatCantidad = document.getElementById('rep_mat_cantidad');
        const repMatPrecio = document.getElementById('rep_mat_precio');
        const repMatDescuento = document.getElementById('rep_mat_descuento');
        const repMatTotalNeto = document.getElementById('rep_mat_total_neto');
        const repMatGuardarCatalogo = document.getElementById('rep_mat_guardar_catalogo');
        const btnAddRepMaterialLine = document.getElementById('btnAddRepMaterialLine');
        const repMatEmpty = document.getElementById('rep_mat_empty');
        const repMatList = document.getElementById('rep_mat_list');

        // Elementos de Mano de Obra
        const repMoDescripcion = document.getElementById('rep_mo_descripcion');
        const repMoPrecio = document.getElementById('rep_mo_precio');
        const repMoDescuento = document.getElementById('rep_mo_descuento');
        const repMoTotalNeto = document.getElementById('rep_mo_total_neto');
        const repMoNotas = document.getElementById('rep_mo_notas');
        const btnAddRepMoLine = document.getElementById('btnAddRepMoLine');
        const repMoEmpty = document.getElementById('rep_mo_empty');
        const repMoList = document.getElementById('rep_mo_list');

        // Elementos de Desglose y Totales
        const repTotDescuentoGlobal = document.getElementById('rep_tot_descuento_global');
        const repTotIva = document.getElementById('rep_tot_iva');
        const repSumMateriales = document.getElementById('rep_sum_materiales');
        const repSumReparaciones = document.getElementById('rep_sum_reparaciones');
        const repSumSubtotal = document.getElementById('rep_sum_subtotal');
        const repSumDescuento = document.getElementById('rep_sum_descuento');
        const repSumBaseImponible = document.getElementById('rep_sum_base_imponible');
        const repLblIva = document.getElementById('rep_lbl_iva');
        const repSumIva = document.getElementById('rep_sum_iva');
        const repSumTotal = document.getElementById('rep_sum_total');

        const orderCosteMoHidden = document.getElementById('orderCosteMoHidden');
        const orderLineasJsonHidden = document.getElementById('orderLineasJsonHidden');
        const repForm = document.getElementById('tallerReparacionForm');
        const repFacturaBadgeContainer = document.getElementById('repFacturaBadgeContainer');

        function escapeRepHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
        }

        // Cálculo dinámico para la fila de Material
        function calcRepMaterialNet() {
            if (!repMatTotalNeto) return;
            const cant = parseFloat(repMatCantidad ? repMatCantidad.value : 1) || 0;
            const precio = parseFloat(repMatPrecio ? repMatPrecio.value : 0) || 0;
            const dto = parseFloat(repMatDescuento ? repMatDescuento.value : 0) || 0;
            const net = Math.max(0, cant * precio * (1 - (dto / 100)));
            repMatTotalNeto.value = net.toFixed(2) + ' €';
        }

        const repMatStockPill = document.getElementById('rep_mat_stock_pill');

        function updateStockFeedback() {
            if (!repMatStockPill) return;
            const selectedOpt = repMatCatalogo && repMatCatalogo.selectedOptions ? repMatCatalogo.selectedOptions[0] : null;
            if (!selectedOpt || !selectedOpt.value) {
                repMatStockPill.style.display = 'none';
                repMatStockPill.innerHTML = '';
                return;
            }

            const stock = parseFloat(selectedOpt.dataset.stock !== undefined ? selectedOpt.dataset.stock : '0') || 0;
            const cant = parseFloat(repMatCantidad ? repMatCantidad.value : 1) || 0;

            repMatStockPill.style.display = 'block';

            if (stock <= 0) {
                repMatStockPill.innerHTML = '<span class="tallerpro-stock-badge out-stock">' +
                    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>' +
                    'Sin stock disponible (0 uds en almacén)</span>';
            } else if (cant > stock) {
                repMatStockPill.innerHTML = '<span class="tallerpro-stock-badge out-stock">' +
                    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>' +
                    'Cantidad solicitada (' + cant + ') supera el stock disponible (' + stock + ' uds)</span>';
            } else if (stock <= 3) {
                repMatStockPill.innerHTML = '<span class="tallerpro-stock-badge low-stock">' +
                    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>' +
                    'Stock bajo: ' + stock + ' uds en almacén</span>';
            } else {
                repMatStockPill.innerHTML = '<span class="tallerpro-stock-badge in-stock">' +
                    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>' +
                    'Stock disponible: ' + stock + ' uds</span>';
            }
        }

        if (repMatCantidad) {
            repMatCantidad.addEventListener('input', function () {
                calcRepMaterialNet();
                updateStockFeedback();
            });
        }
        if (repMatPrecio) repMatPrecio.addEventListener('input', calcRepMaterialNet);
        if (repMatDescuento) repMatDescuento.addEventListener('input', calcRepMaterialNet);

        // Selección de material del catálogo
        if (repMatCatalogo) {
            repMatCatalogo.addEventListener('change', function () {
                const selectedOpt = repMatCatalogo.selectedOptions ? repMatCatalogo.selectedOptions[0] : null;
                if (selectedOpt && selectedOpt.value) {
                    if (repMatCodigo) repMatCodigo.value = selectedOpt.dataset.codigo || '';
                    if (repMatDescripcion) repMatDescripcion.value = selectedOpt.dataset.descripcion || '';
                    if (repMatPrecio) repMatPrecio.value = selectedOpt.dataset.precio || '0';
                }
                calcRepMaterialNet();
                updateStockFeedback();
            });
        }

        // Cálculo dinámico para la fila de Mano de Obra
        function calcRepMoNet() {
            if (!repMoTotalNeto) return;
            const precio = parseFloat(repMoPrecio ? repMoPrecio.value : 0) || 0;
            const dto = parseFloat(repMoDescuento ? repMoDescuento.value : 0) || 0;
            const net = Math.max(0, precio * (1 - (dto / 100)));
            repMoTotalNeto.value = net.toFixed(2) + ' €';
        }

        if (repMoPrecio) repMoPrecio.addEventListener('input', calcRepMoNet);
        if (repMoDescuento) repMoDescuento.addEventListener('input', calcRepMoNet);

        // Renderizar tabla de Materiales
        function renderRepMaterialsTable() {
            if (!repMatList || !repMatEmpty) return;
            if (currentRepMaterials.length === 0) {
                repMatEmpty.style.display = 'block';
                repMatList.style.display = 'none';
                repMatList.innerHTML = '';
                return;
            }

            repMatEmpty.style.display = 'none';
            repMatList.style.display = 'block';

            let html = '<table class="tallerpro-invoice-table">' +
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

            currentRepMaterials.forEach(function (mat, idx) {
                const net = parseFloat(mat.total_neto) || 0;
                const codeDisplay = (mat.codigo && mat.codigo.trim()) ? escapeRepHtml(mat.codigo) : '—';
                const qtyDisplay = (parseFloat(mat.cantidad) || 1).toFixed(0);
                const priceDisplay = (parseFloat(mat.precio) || 0).toFixed(2) + ' €';
                const dtoDisplay = (parseFloat(mat.descuento) || 0).toFixed(0) + '%';
                const totalDisplay = net.toFixed(2) + ' €';

                html += '<tr>' +
                    '<td style="color: #64748b; font-family: monospace; font-size: 12.5px;">' + codeDisplay + '</td>' +
                    '<td style="font-weight: 500; color: #0f172a;">' + escapeRepHtml(mat.descripcion) + '</td>' +
                    '<td style="text-align: center;">' + qtyDisplay + '</td>' +
                    '<td style="text-align: right;">' + priceDisplay + '</td>' +
                    '<td style="text-align: center; color: #64748b;">' + dtoDisplay + '</td>' +
                    '<td style="text-align: right; font-weight: 600; color: #0f172a;">' + totalDisplay + '</td>' +
                    '<td style="text-align: center;">' +
                    '<button type="button" class="tallerpro-btn-line-delete btn-delete-rep-mat" data-index="' + idx + '" title="Eliminar material">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                    '<polyline points="3 6 5 6 21 6"></polyline>' +
                    '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>' +
                    '</svg>' +
                    '</button>' +
                    '</td>' +
                    '</tr>';
            });

            html += '</tbody></table>';
            repMatList.innerHTML = html;
        }

        // Renderizar tabla de Mano de Obra
        function renderRepLaborTable() {
            if (!repMoList || !repMoEmpty) return;
            if (currentRepLabor.length === 0) {
                repMoEmpty.style.display = 'block';
                repMoList.style.display = 'none';
                repMoList.innerHTML = '';
                return;
            }

            repMoEmpty.style.display = 'none';
            repMoList.style.display = 'block';

            let html = '<table class="tallerpro-invoice-table">' +
                '<thead>' +
                '<tr>' +
                '<th style="width: 59%;">Descripción / Intervención</th>' +
                '<th style="width: 15%; text-align: right;">Precio</th>' +
                '<th style="width: 10%; text-align: center;">Dto.</th>' +
                '<th style="width: 16%; text-align: right;">Total</th>' +
                '<th style="width: 32px; text-align: center;"></th>' +
                '</tr>' +
                '</thead>' +
                '<tbody>';

            currentRepLabor.forEach(function (lab, idx) {
                const net = parseFloat(lab.total_neto) || 0;
                let descHtml = '<span>' + escapeRepHtml(lab.descripcion) + '</span>';
                if (lab.notas && lab.notas.trim()) {
                    descHtml += '<div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">' + escapeRepHtml(lab.notas.trim()) + '</div>';
                }
                const priceDisplay = (parseFloat(lab.precio) || 0).toFixed(2) + ' €';
                const dtoDisplay = (parseFloat(lab.descuento) || 0).toFixed(0) + '%';
                const totalDisplay = net.toFixed(2) + ' €';

                html += '<tr>' +
                    '<td style="font-weight: 500; color: #0f172a;">' + descHtml + '</td>' +
                    '<td style="text-align: right;">' + priceDisplay + '</td>' +
                    '<td style="text-align: center; color: #64748b;">' + dtoDisplay + '</td>' +
                    '<td style="text-align: right; font-weight: 600; color: #0f172a;">' + totalDisplay + '</td>' +
                    '<td style="text-align: center;">' +
                    '<button type="button" class="tallerpro-btn-line-delete btn-delete-rep-mo" data-index="' + idx + '" title="Eliminar trabajo">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                    '<polyline points="3 6 5 6 21 6"></polyline>' +
                    '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>' +
                    '</svg>' +
                    '</button>' +
                    '</td>' +
                    '</tr>';
            });

            html += '</tbody></table>';
            repMoList.innerHTML = html;
        }

        // Recalcular totales y sincronizar inputs ocultos
        function renderRepTotalsSummary() {
            let matSum = 0;
            currentRepMaterials.forEach(function (m) { matSum += (parseFloat(m.total_neto) || 0); });
            let laborSum = 0;
            currentRepLabor.forEach(function (l) { laborSum += (parseFloat(l.total_neto) || 0); });

            const subtotal = matSum + laborSum;
            const descuento = parseFloat(repTotDescuentoGlobal ? repTotDescuentoGlobal.value : 0) || 0;
            const ivaPorcentaje = parseFloat(repTotIva ? repTotIva.value : 21) || 0;
            const baseImponible = Math.max(0, subtotal - descuento);
            const ivaImporte = baseImponible * (ivaPorcentaje / 100);
            const grandTotal = baseImponible + ivaImporte;

            if (repSumMateriales) repSumMateriales.textContent = matSum.toFixed(2) + ' €';
            if (repSumReparaciones) repSumReparaciones.textContent = laborSum.toFixed(2) + ' €';
            if (repSumSubtotal) repSumSubtotal.textContent = subtotal.toFixed(2) + ' €';
            if (repSumDescuento) repSumDescuento.textContent = '- ' + descuento.toFixed(2) + ' €';
            if (repSumBaseImponible) repSumBaseImponible.textContent = baseImponible.toFixed(2) + ' €';
            if (repLblIva) repLblIva.textContent = 'IVA (' + (Number.isInteger(ivaPorcentaje) ? ivaPorcentaje : ivaPorcentaje.toFixed(1)) + '%)';
            if (repSumIva) repSumIva.textContent = ivaImporte.toFixed(2) + ' €';
            if (repSumTotal) repSumTotal.textContent = grandTotal.toFixed(2) + ' €';

            if (orderCosteMoHidden) orderCosteMoHidden.value = laborSum.toFixed(2);
            if (orderLineasJsonHidden) {
                const orderNotasInput = document.getElementById('orderNotas');
                const userNotes = orderNotasInput ? orderNotasInput.value.trim() : '';

                orderLineasJsonHidden.value = JSON.stringify({
                    materiales: currentRepMaterials,
                    reparaciones: currentRepLabor,
                    descuento_global: descuento,
                    iva_porcentaje: ivaPorcentaje,
                    total: grandTotal.toFixed(2),
                    notas_usuario: userNotes
                });
            }
        }

        if (repTotDescuentoGlobal) repTotDescuentoGlobal.addEventListener('input', renderRepTotalsSummary);
        if (repTotIva) repTotIva.addEventListener('input', renderRepTotalsSummary);
        const orderNotasField = document.getElementById('orderNotas');
        if (orderNotasField) orderNotasField.addEventListener('input', renderRepTotalsSummary);

        // Añadir línea de Material
        if (btnAddRepMaterialLine) {
            btnAddRepMaterialLine.addEventListener('click', function (e) {
                if (e) e.preventDefault();
                const desc = repMatDescripcion ? repMatDescripcion.value.trim() : '';
                if (!desc) {
                    alert('Por favor, indica una descripción para el material.');
                    if (repMatDescripcion) {
                        repMatDescripcion.focus();
                        repMatDescripcion.style.borderColor = '#ef4444';
                        setTimeout(function () { repMatDescripcion.style.borderColor = ''; }, 2000);
                    }
                    return;
                }

                const cant = parseFloat(repMatCantidad ? repMatCantidad.value : 1) || 0;
                const precio = parseFloat(repMatPrecio ? repMatPrecio.value : 0) || 0;
                const dto = parseFloat(repMatDescuento ? repMatDescuento.value : 0) || 0;
                const net = Math.max(0, cant * precio * (1 - (dto / 100)));

                const selectedOpt = repMatCatalogo && repMatCatalogo.selectedOptions ? repMatCatalogo.selectedOptions[0] : null;
                if (selectedOpt && selectedOpt.value) {
                    const availableStock = parseFloat(selectedOpt.dataset.stock !== undefined ? selectedOpt.dataset.stock : '0') || 0;
                    if (availableStock <= 0) {
                        if (!confirm("Atención: El material '" + desc + "' no tiene stock disponible en almacén (0 uds). ¿Deseas incluirlo de todos modos en la orden?")) {
                            return;
                        }
                    } else if (cant > availableStock) {
                        if (!confirm("Atención: La cantidad indicada (" + cant + ") supera el stock disponible (" + availableStock + " uds). ¿Deseas continuar?")) {
                            return;
                        }
                    }
                }

                currentRepMaterials.push({
                    fk_material: repMatCatalogo ? repMatCatalogo.value : '',
                    codigo: repMatCodigo ? repMatCodigo.value.trim() : '',
                    descripcion: desc,
                    cantidad: cant,
                    precio: precio,
                    descuento: dto,
                    total_neto: net,
                    guardar_catalogo: (repMatGuardarCatalogo && repMatGuardarCatalogo.checked) ? 1 : 0
                });

                // Limpiar inputs
                if (repMatCatalogo) repMatCatalogo.value = '';
                if (repMatCodigo) repMatCodigo.value = '';
                if (repMatDescripcion) repMatDescripcion.value = '';
                if (repMatCantidad) repMatCantidad.value = '1';
                if (repMatPrecio) repMatPrecio.value = '0';
                if (repMatDescuento) repMatDescuento.value = '0';
                if (repMatTotalNeto) repMatTotalNeto.value = '0.00 €';
                if (repMatGuardarCatalogo) repMatGuardarCatalogo.checked = false;
            if (repMatStockPill) {
                repMatStockPill.style.display = 'none';
                repMatStockPill.innerHTML = '';
            }
                if (repMatStockPill) {
                    repMatStockPill.style.display = 'none';
                    repMatStockPill.innerHTML = '';
                }

                renderRepMaterialsTable();
                renderRepTotalsSummary();
            });
        }

        // Eliminar fila de material
        if (repMatList) {
            repMatList.addEventListener('click', function (e) {
                const delBtn = e.target.closest('.btn-delete-rep-mat');
                if (!delBtn) return;
                const idx = parseInt(delBtn.dataset.index, 10);
                if (!isNaN(idx) && idx >= 0 && idx < currentRepMaterials.length) {
                    currentRepMaterials.splice(idx, 1);
                    renderRepMaterialsTable();
                    renderRepTotalsSummary();
                }
            });
        }

        // Añadir línea de Mano de Obra
        if (btnAddRepMoLine) {
            btnAddRepMoLine.addEventListener('click', function (e) {
                if (e) e.preventDefault();
                const desc = repMoDescripcion ? repMoDescripcion.value.trim() : '';
                if (!desc) {
                    alert('Por favor, indica la descripción de la reparación o tarea realizada.');
                    if (repMoDescripcion) {
                        repMoDescripcion.focus();
                        repMoDescripcion.style.borderColor = '#ef4444';
                        setTimeout(function () { repMoDescripcion.style.borderColor = ''; }, 2000);
                    }
                    return;
                }

                const precio = parseFloat(repMoPrecio ? repMoPrecio.value : 0) || 0;
                const dto = parseFloat(repMoDescuento ? repMoDescuento.value : 0) || 0;
                const net = Math.max(0, precio * (1 - (dto / 100)));
                const notas = repMoNotas ? repMoNotas.value.trim() : '';

                currentRepLabor.push({
                    descripcion: desc,
                    precio: precio,
                    descuento: dto,
                    total_neto: net,
                    notas: notas
                });

                // Limpiar inputs
                if (repMoDescripcion) repMoDescripcion.value = '';
                if (repMoPrecio) repMoPrecio.value = '0';
                if (repMoDescuento) repMoDescuento.value = '0';
                if (repMoTotalNeto) repMoTotalNeto.value = '0.00 €';
                if (repMoNotas) repMoNotas.value = '';

                renderRepLaborTable();
                renderRepTotalsSummary();
            });
        }

        // Eliminar fila de mano de obra
        if (repMoList) {
            repMoList.addEventListener('click', function (e) {
                const delBtn = e.target.closest('.btn-delete-rep-mo');
                if (!delBtn) return;
                const idx = parseInt(delBtn.dataset.index, 10);
                if (!isNaN(idx) && idx >= 0 && idx < currentRepLabor.length) {
                    currentRepLabor.splice(idx, 1);
                    renderRepLaborTable();
                    renderRepTotalsSummary();
                }
            });
        }

        // Resetear modal a estado inicial vacío
        function resetReparacionModal() {
            const actionInput = document.getElementById('reparacionFormAction');
            const rowidInput = document.getElementById('reparacionFormRowid');
            const titleEl = document.getElementById('orderModalTitle');
            const vehSelect = document.getElementById('orderVehiculoSelect');
            const fechaInput = document.getElementById('orderFecha');
            const estadoSelect = document.getElementById('orderEstadoSelect');
            const averiaInput = document.getElementById('orderAveria');

            if (actionInput) actionInput.value = 'create_reparacion';
            if (rowidInput) rowidInput.value = '0';
            if (titleEl) titleEl.textContent = 'Nueva Orden de Reparación';

            if (vehSelect) vehSelect.value = '';
            if (fechaInput) {
                const today = new Date().toISOString().split('T')[0];
                fechaInput.value = today;
            }
            if (estadoSelect) estadoSelect.value = 'en_curso';
            if (averiaInput) averiaInput.value = '';
            const orderNotasInput = document.getElementById('orderNotas');
            if (orderNotasInput) orderNotasInput.value = '';

            if (repMatCatalogo) repMatCatalogo.value = '';
            if (repMatCodigo) repMatCodigo.value = '';
            if (repMatDescripcion) repMatDescripcion.value = '';
            if (repMatCantidad) repMatCantidad.value = '1';
            if (repMatPrecio) repMatPrecio.value = '0';
            if (repMatDescuento) repMatDescuento.value = '0';
            if (repMatTotalNeto) repMatTotalNeto.value = '0.00 €';
            if (repMatGuardarCatalogo) repMatGuardarCatalogo.checked = false;
            if (repMatStockPill) {
                repMatStockPill.style.display = 'none';
                repMatStockPill.innerHTML = '';
            }

            if (repMoDescripcion) repMoDescripcion.value = '';
            if (repMoPrecio) repMoPrecio.value = '0';
            if (repMoDescuento) repMoDescuento.value = '0';
            if (repMoTotalNeto) repMoTotalNeto.value = '0.00 €';
            if (repMoNotas) repMoNotas.value = '';

            if (repTotDescuentoGlobal) repTotDescuentoGlobal.value = '0';
            if (repTotIva) repTotIva.value = '21';

            if (repFacturaBadgeContainer) {
                repFacturaBadgeContainer.style.display = 'none';
                repFacturaBadgeContainer.innerHTML = '';
            }

            const btnCrearAlbaran = document.getElementById('btnModalCrearAlbaran');
            const btnCrearAlbaranText = document.getElementById('btnModalCrearAlbaranText');
            if (btnCrearAlbaran) {
                btnCrearAlbaran.style.display = 'none';
                btnCrearAlbaran.removeAttribute('data-target-albaran-url');
                if (btnCrearAlbaranText) btnCrearAlbaranText.textContent = 'Crear albarán';
            }

            currentRepMaterials = [];
            currentRepLabor = [];

            renderRepMaterialsTable();
            renderRepLaborTable();
            renderRepTotalsSummary();
        }

        if (openRepModalBtn && repModal) {
            openRepModalBtn.addEventListener('click', function (e) {
                e.preventDefault();
                resetReparacionModal();
                repModal.classList.add('active');
            });
        }

        // Editar reparación desde la tabla
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
            const factura = editBtn.getAttribute('data-factura') || '';
            const albaranId = editBtn.getAttribute('data-albaran-id') || '';
            const albaranRef = editBtn.getAttribute('data-albaran-ref') || '';
            const notas = editBtn.getAttribute('data-notas') || '';

            const actionInput = document.getElementById('reparacionFormAction');
            const rowidInput = document.getElementById('reparacionFormRowid');
            const titleEl = document.getElementById('orderModalTitle');

            if (actionInput) actionInput.value = 'update_reparacion';
            if (rowidInput) rowidInput.value = id;
            if (titleEl) titleEl.textContent = ref ? ('Editar ' + ref) : 'Editar Orden de Reparación';

            // Configurar botón Crear albarán / Ver albarán en modal
            const btnCrearAlbaran = document.getElementById('btnModalCrearAlbaran');
            const btnCrearAlbaranText = document.getElementById('btnModalCrearAlbaranText');
            if (btnCrearAlbaran) {
                btnCrearAlbaran.style.display = 'inline-flex';
                if (albaranId && parseInt(albaranId) > 0) {
                    btnCrearAlbaran.setAttribute('data-target-albaran-url', 'facturas.php?id=' + albaranId);
                    if (btnCrearAlbaranText) {
                        btnCrearAlbaranText.textContent = 'Ver albarán' + (albaranRef ? ' (' + albaranRef + ')' : '');
                    }
                    btnCrearAlbaran.title = 'Ver el albarán / presupuesto enlazado';
                } else {
                    btnCrearAlbaran.removeAttribute('data-target-albaran-url');
                    if (btnCrearAlbaranText) {
                        btnCrearAlbaranText.textContent = 'Crear albarán';
                    }
                    btnCrearAlbaran.title = 'Crear un nuevo albarán / presupuesto enlazado a esta reparación';
                }
            }

            const vehSelect = document.getElementById('orderVehiculoSelect');
            if (vehSelect && vehiculo) vehSelect.value = vehiculo;

            const fechaInput = document.getElementById('orderFecha');
            if (fechaInput && fecha) fechaInput.value = fecha;

            const estadoSelect = document.getElementById('orderEstadoSelect');
            if (estadoSelect && estado) estadoSelect.value = estado;

            const averiaInput = document.getElementById('orderAveria');
            if (averiaInput) averiaInput.value = descripcion;

            // Mostrar u ocultar badge de factura
            if (factura && factura.trim()) {
                if (repFacturaBadgeContainer) {
                    repFacturaBadgeContainer.style.display = 'block';
                    repFacturaBadgeContainer.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; border-radius: 9999px; font-size: 12px; font-weight: 600;">Facturada: ' + escapeRepHtml(factura) + '</span>';
                }
            } else {
                if (repFacturaBadgeContainer) {
                    repFacturaBadgeContainer.style.display = 'none';
                    repFacturaBadgeContainer.innerHTML = '';
                }
            }

            // Parsear notas (JSON o texto plano)
            let parsed = null;
            if (notas && (notas.startsWith('{') || notas.startsWith('['))) {
                try {
                    parsed = JSON.parse(notas);
                } catch (err) {
                    parsed = null;
                }
            }

            if (parsed && typeof parsed === 'object') {
                currentRepMaterials = Array.isArray(parsed.materiales) ? parsed.materiales : [];
                currentRepLabor = Array.isArray(parsed.reparaciones) ? parsed.reparaciones : [];
                if (repTotDescuentoGlobal) repTotDescuentoGlobal.value = parsed.descuento_global !== undefined ? parsed.descuento_global : '0';
                if (repTotIva) repTotIva.value = parsed.iva_porcentaje !== undefined ? parsed.iva_porcentaje : '21';
            } else {
                currentRepMaterials = [];
                const costVal = parseFloat(coste) || 0;
                if (costVal > 0) {
                    currentRepLabor = [{
                        descripcion: descripcion || 'Mano de obra reparación',
                        precio: costVal,
                        descuento: 0,
                        total_neto: costVal,
                        notas: ''
                    }];
                } else {
                    currentRepLabor = [];
                }
                if (repTotDescuentoGlobal) repTotDescuentoGlobal.value = '0';
                if (repTotIva) repTotIva.value = '21';
            }

            const orderNotasInput = document.getElementById('orderNotas');
            if (orderNotasInput) {
                if (parsed && parsed.notas_usuario !== undefined) {
                    orderNotasInput.value = parsed.notas_usuario;
                } else if (parsed && parsed.notas && typeof parsed.notas === 'string') {
                    orderNotasInput.value = parsed.notas;
                } else if (notas && !notas.startsWith('{') && !notas.startsWith('[')) {
                    orderNotasInput.value = notas;
                } else {
                    orderNotasInput.value = '';
                }
            }

            renderRepMaterialsTable();
            renderRepLaborTable();
            renderRepTotalsSummary();

            if (repModal) repModal.classList.add('active');
        });

        // Submit form normal (Guardar)
        if (repForm) {
            repForm.addEventListener('submit', function (e) {
                const vehSelect = document.getElementById('orderVehiculoSelect');
                if (!vehSelect || !vehSelect.value || parseInt(vehSelect.value) <= 0) {
                    e.preventDefault();
                    alert('Por favor, selecciona el vehículo al que se le realiza la reparación.');
                    if (vehSelect) vehSelect.focus();
                    return false;
                }
                const averiaInput = document.getElementById('orderAveria');
                if (!averiaInput || !averiaInput.value.trim()) {
                    e.preventDefault();
                    alert('Por favor, indica la avería o trabajo solicitado.');
                    if (averiaInput) averiaInput.focus();
                    return false;
                }

                renderRepTotalsSummary();
            });
        }

        const tallerSaveOrderBtn = document.getElementById('tallerSaveOrderBtn');
        if (tallerSaveOrderBtn) {
            tallerSaveOrderBtn.addEventListener('click', function () {
                const actionInput = document.getElementById('reparacionFormAction');
                const rowidInput = document.getElementById('reparacionFormRowid');
                if (actionInput) {
                    const rowid = rowidInput ? parseInt(rowidInput.value) : 0;
                    actionInput.value = (rowid > 0) ? 'update_reparacion' : 'create_reparacion';
                }
            });
        }

        const btnModalCrearAlbaran = document.getElementById('btnModalCrearAlbaran');
        if (btnModalCrearAlbaran) {
            btnModalCrearAlbaran.addEventListener('click', function (e) {
                e.preventDefault();
                const targetUrl = btnModalCrearAlbaran.getAttribute('data-target-albaran-url');
                if (targetUrl) {
                    window.location.href = targetUrl;
                    return;
                }

                const vehSelect = document.getElementById('orderVehiculoSelect');
                if (!vehSelect || !vehSelect.value || parseInt(vehSelect.value) <= 0) {
                    alert('Por favor, selecciona el vehículo al que se le realiza la reparación.');
                    if (vehSelect) vehSelect.focus();
                    return false;
                }
                const averiaInput = document.getElementById('orderAveria');
                if (!averiaInput || !averiaInput.value.trim()) {
                    alert('Por favor, indica la avería o trabajo solicitado.');
                    if (averiaInput) averiaInput.focus();
                    return false;
                }

                const actionInput = document.getElementById('reparacionFormAction');
                if (actionInput) {
                    actionInput.value = 'save_and_albaran';
                }

                renderRepTotalsSummary();

                if (repForm) {
                    repForm.submit();
                }
            });
        }

        // Auto-abrir modal si viene un vehículo preseleccionado en la URL
        const preselectedVeh = document.getElementById('orderVehiculoSelect')?.getAttribute('data-preselected');
        if (preselectedVeh && parseInt(preselectedVeh) > 0) {
            setTimeout(function () {
                if (openRepModalBtn) openRepModalBtn.click();
                const vehSelect = document.getElementById('orderVehiculoSelect');
                if (vehSelect) vehSelect.value = preselectedVeh;
            }, 100);
        }

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
                clearTimeout(debounceTimer);
                const query = globalSearchInput.value.trim();

                if (query.length === 0) {
                    resetDashboardView();
                    return;
                }

                debounceTimer = setTimeout(function () {
                    const currentQuery = globalSearchInput.value.trim();
                    if (currentQuery.length === 0) {
                        resetDashboardView();
                        return;
                    }

                    // Switch view to search mode after the user pauses typing
                    defaultViewContainer.style.display = 'none';
                    searchResultsContainer.style.display = 'flex';

                    fetch('tallerindex.php?action=search_all&q=' + encodeURIComponent(currentQuery))
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (globalSearchInput.value.trim() !== currentQuery) {
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
                }, 250);
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

        // ==========================================
        // Configuracion: Toggle Switch Mostrar IBAN
        // ==========================================
        var ibanSwitch = document.getElementById('taller_mostrar_iban');
        var ibanStatus = document.getElementById('taller_mostrar_iban_status');
        if (ibanSwitch && ibanStatus) {
            ibanSwitch.addEventListener('change', function () {
                if (this.checked) {
                    ibanStatus.textContent = 'Activado (se mostrará en facturas emitidas)';
                    ibanStatus.className = 'tallerpro-switch-label is-active';
                } else {
                    ibanStatus.textContent = 'Desactivado (oculto en facturas emitidas)';
                    ibanStatus.className = 'tallerpro-switch-label is-inactive';
                }
            });
        }

    });
}



