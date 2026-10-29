<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       taller/materiales.php
 *  \ingroup    taller
 *  \brief      Materiales list view - TallerPro view
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array("taller@taller"));

// Security check
if (!isModEnabled('taller')) {
	accessforbidden('Module not enabled');
}

$table_material = MAIN_DB_PREFIX . "taller_material";

// Ensure table exists
$sql_create = "CREATE TABLE IF NOT EXISTS " . $table_material . " (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(255) NOT NULL,
    ref             VARCHAR(128) DEFAULT NULL,
    proveedor       VARCHAR(255) DEFAULT NULL,
    stock           DOUBLE DEFAULT 0,
    precio          DOUBLE(24,8) DEFAULT 0,
    notas           TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat   INT NOT NULL,
    entity          INT DEFAULT 1,
    status          SMALLINT DEFAULT 1,
    INDEX idx_taller_material_ref (ref),
    INDEX idx_taller_material_nombre (nombre)
) ENGINE=innodb;";
$db->query($sql_create);

$action = GETPOST('action', 'alpha');
if (empty($action) && !empty($_POST['action'])) {
	$action = $_POST['action'];
}

// Acción: Crear Material
if ($action == 'create_material' || $action == 'add_material') {
	$nombre = trim(GETPOST('nombre', 'alphanohtml'));
	if (empty($nombre) && !empty($_POST['nombre'])) {
		$nombre = trim($_POST['nombre']);
	}

	$ref = trim(GETPOST('ref', 'alphanohtml'));
	if (empty($ref) && !empty($_POST['ref'])) {
		$ref = trim($_POST['ref']);
	}

	$proveedor = trim(GETPOST('proveedor', 'alphanohtml'));
	if (empty($proveedor) && !empty($_POST['proveedor'])) {
		$proveedor = trim($_POST['proveedor']);
	}

	$stock = GETPOSTFLOAT('stock');
	if ($stock === null || $stock === '') {
		$stock = isset($_POST['stock']) ? (float) $_POST['stock'] : 0;
	}

	$precio = GETPOSTFLOAT('precio');
	if ($precio === null || $precio === '') {
		$precio = isset($_POST['precio']) ? (float) $_POST['precio'] : 0;
	}

	$notas = trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) {
		$notas = trim($_POST['notas']);
	}

	if (empty($nombre)) {
		setEventMessages($langs->trans("ErrorFieldRequired", "Nombre"), null, 'errors');
	} else {
		$sql_insert = "INSERT INTO " . $table_material . " (";
		$sql_insert .= "nombre, ref, proveedor, stock, precio, notas, date_creation, fk_user_creat, entity, status";
		$sql_insert .= ") VALUES (";
		$sql_insert .= "'" . $db->escape($nombre) . "', ";
		$sql_insert .= "'" . $db->escape($ref) . "', ";
		$sql_insert .= "'" . $db->escape($proveedor) . "', ";
		$sql_insert .= ((float) $stock) . ", ";
		$sql_insert .= ((float) $precio) . ", ";
		$sql_insert .= "'" . $db->escape($notas) . "', ";
		$sql_insert .= "'" . $db->idate(dol_now()) . "', ";
		$sql_insert .= ((int) $user->id) . ", ";
		$sql_insert .= ((int) $conf->entity) . ", ";
		$sql_insert .= "1";
		$sql_insert .= ")";

		$resql = $db->query($sql_insert);
		if ($resql) {
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF']);
			exit;
		} else {
			setEventMessages("Error al guardar el material: " . $db->lasterror(), null, 'errors');
		}
	}
}

// Acción: Actualizar Material
if ($action == 'update_material' || $action == 'edit_material') {
	$rowid = GETPOSTINT('rowid');
	if (empty($rowid) && !empty($_POST['rowid'])) {
		$rowid = (int) $_POST['rowid'];
	}

	$nombre = trim(GETPOST('nombre', 'alphanohtml'));
	if (empty($nombre) && !empty($_POST['nombre'])) {
		$nombre = trim($_POST['nombre']);
	}

	$ref = trim(GETPOST('ref', 'alphanohtml'));
	if (empty($ref) && !empty($_POST['ref'])) {
		$ref = trim($_POST['ref']);
	}

	$proveedor = trim(GETPOST('proveedor', 'alphanohtml'));
	if (empty($proveedor) && !empty($_POST['proveedor'])) {
		$proveedor = trim($_POST['proveedor']);
	}

	$stock = GETPOSTFLOAT('stock');
	if ($stock === null || $stock === '') {
		$stock = isset($_POST['stock']) ? (float) $_POST['stock'] : 0;
	}

	$precio = GETPOSTFLOAT('precio');
	if ($precio === null || $precio === '') {
		$precio = isset($_POST['precio']) ? (float) $_POST['precio'] : 0;
	}

	$notas = trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) {
		$notas = trim($_POST['notas']);
	}

	if (empty($nombre)) {
		setEventMessages($langs->trans("ErrorFieldRequired", "Nombre"), null, 'errors');
	} elseif ($rowid <= 0) {
		setEventMessages("Identificador de material no válido", null, 'errors');
	} else {
		$sql_update = "UPDATE " . $table_material . " SET ";
		$sql_update .= "nombre = '" . $db->escape($nombre) . "', ";
		$sql_update .= "ref = '" . $db->escape($ref) . "', ";
		$sql_update .= "proveedor = '" . $db->escape($proveedor) . "', ";
		$sql_update .= "stock = " . ((float) $stock) . ", ";
		$sql_update .= "precio = " . ((float) $precio) . ", ";
		$sql_update .= "notas = '" . $db->escape($notas) . "' ";
		$sql_update .= "WHERE rowid = " . ((int) $rowid) . " AND entity IN (" . getEntity('taller_material') . ")";

		$resql = $db->query($sql_update);
		if ($resql) {
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF']);
			exit;
		} else {
			setEventMessages("Error al actualizar el material: " . $db->lasterror(), null, 'errors');
		}
	}
}

// Acción: Eliminar Material
if ($action == 'delete_material' || $action == 'delete') {
	$rowid = GETPOSTINT('rowid');
	if ($rowid > 0) {
		$sql_del = "UPDATE " . $table_material . " SET status = 0 WHERE rowid = " . ((int) $rowid) . " AND entity IN (" . getEntity('taller_material') . ")";
		$resql = $db->query($sql_del);
		if ($resql) {
			setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF']);
			exit;
		} else {
			setEventMessages("Error al eliminar el material: " . $db->lasterror(), null, 'errors');
		}
	}
}

// Consultar proveedores de Dolibarr para sugerencias en el formulario
$suppliers_list = array();
$sql_s = "SELECT DISTINCT nom FROM " . MAIN_DB_PREFIX . "societe WHERE fournisseur = 1 AND entity IN (" . getEntity('societe') . ") ORDER BY nom ASC";
$res_s = $db->query($sql_s);
if ($res_s) {
	while ($s = $db->fetch_object($res_s)) {
		if (!empty($s->nom)) {
			$suppliers_list[] = $s->nom;
		}
	}
}

// Consultar materiales
$materials_list = array();
$sql_m = "SELECT rowid, nombre, ref, proveedor, stock, precio, notas FROM " . $table_material;
$sql_m .= " WHERE status = 1 AND entity IN (" . getEntity('taller_material') . ") ORDER BY rowid DESC";
$res_m = $db->query($sql_m);
if ($res_m) {
	while ($m = $db->fetch_object($res_m)) {
		$materials_list[] = $m;
	}
}
$total_materials = count($materials_list);

$css_file = __DIR__ . '/css/tallerpro.css';
$js_file = __DIR__ . '/js/tallerpro.js';
$css_version = file_exists($css_file) ? filemtime($css_file) : dol_now();
$js_version = file_exists($js_file) ? filemtime($js_file) : dol_now();

$css_url = dol_buildpath('/taller/css/tallerpro.css', 1) . '?v=' . $css_version;
$js_url = dol_buildpath('/taller/js/tallerpro.js', 1) . '?v=' . $js_version;

$morehead = '<link rel="stylesheet" type="text/css" href="'.$css_url.'">'."\n";
$morehead .= '<script src="'.$js_url.'"></script>'."\n";

// Page Header
llxHeader('', 'Materiales - TallerPro', '', '', 0, 0, array(), array(), $morehead, 'mod-taller page-tallerpro');

$dolibarr_root_url = DOL_URL_ROOT . '/index.php';
$setup_url = dol_buildpath('/taller/configuracion.php', 1);
$index_url = dol_buildpath('/taller/tallerindex.php', 1);
$clientes_url = dol_buildpath('/taller/clientes.php', 1);
$vehiculos_url = dol_buildpath('/taller/vehiculos.php', 1);
$reparaciones_url = dol_buildpath('/taller/reparaciones.php', 1);
$materiales_url = dol_buildpath('/taller/materiales.php', 1);
$seguros_url = dol_buildpath('/taller/seguros.php', 1);
$facturas_url = dol_buildpath('/taller/facturas.php', 1);
?>

<div class="tallerpro-layout">
	<!-- Lateral Menu / Sidebar -->
	<aside class="tallerpro-sidebar" id="tallerSidebar">
		<div class="tallerpro-sidebar-top">
			<!-- Brand Header -->
			<a href="<?php echo $index_url; ?>" class="tallerpro-brand">
				<div class="tallerpro-brand-logo">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
					</svg>
				</div>
				<div class="tallerpro-brand-info">
					<span class="tallerpro-brand-title">TallerPro</span>
					<span class="tallerpro-brand-subtitle"><?php echo $langs->trans("GestionDeTaller"); ?></span>
				</div>
			</a>

			<!-- Menu Section -->
			<div class="tallerpro-nav-section">
				<div class="tallerpro-section-title"><?php echo $langs->trans("Menu"); ?></div>
				<ul class="tallerpro-nav-list">
					<li>
						<a href="<?php echo $index_url; ?>" class="tallerpro-nav-item">
							<span class="tallerpro-nav-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
									<polyline points="9 22 9 12 15 12 15 22"></polyline>
								</svg>
							</span>
							<span class="tallerpro-nav-text"><?php echo $langs->trans("Inicio"); ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo $clientes_url; ?>" class="tallerpro-nav-item">
							<span class="tallerpro-nav-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
									<circle cx="9" cy="7" r="4"></circle>
									<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
									<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
								</svg>
							</span>
							<span class="tallerpro-nav-text"><?php echo $langs->trans("Clientes"); ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo $vehiculos_url; ?>" class="tallerpro-nav-item">
							<span class="tallerpro-nav-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 3.1C2 11.4 2 11.7 2 12v4c0 .6.4 1 1 1h2"></path>
									<circle cx="7" cy="17" r="2"></circle>
									<path d="M9 17h6"></path>
									<circle cx="17" cy="17" r="2"></circle>
								</svg>
							</span>
							<span class="tallerpro-nav-text"><?php echo $langs->trans("Vehiculos"); ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo $reparaciones_url; ?>" class="tallerpro-nav-item">
							<span class="tallerpro-nav-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
								</svg>
							</span>
							<span class="tallerpro-nav-text"><?php echo $langs->trans("Reparaciones"); ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo $materiales_url; ?>" class="tallerpro-nav-item active">
							<span class="tallerpro-nav-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
									<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
									<line x1="12" y1="22.08" x2="12" y2="12"></line>
								</svg>
							</span>
							<span class="tallerpro-nav-text"><?php echo $langs->trans("Materiales"); ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo $seguros_url; ?>" class="tallerpro-nav-item">
							<span class="tallerpro-nav-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
								</svg>
							</span>
							<span class="tallerpro-nav-text"><?php echo $langs->trans("Seguros"); ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo $facturas_url; ?>" class="tallerpro-nav-item">
							<span class="tallerpro-nav-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<rect x="3" y="3" width="18" height="18" rx="2"></rect>
									<line x1="12" y1="7" x2="12" y2="17"></line>
									<path d="M9.5 9.5a1.5 1.5 0 0 1 1.5-1.5h2a1.5 1.5 0 0 1 1.5 1.5c0 1.5-3 1.5-3 3a1.5 1.5 0 0 0 1.5 1.5h2a1.5 1.5 0 0 0 1.5-1.5"></path>
								</svg>
							</span>
							<span class="tallerpro-nav-text"><?php echo $langs->trans("Facturas"); ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo $setup_url; ?>" class="tallerpro-nav-item">
							<span class="tallerpro-nav-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<circle cx="12" cy="12" r="3"></circle>
									<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
								</svg>
							</span>
							<span class="tallerpro-nav-text"><?php echo $langs->trans("Configuracion"); ?></span>
						</a>
					</li>
				</ul>
			</div>
		</div>

		<!-- Collapse Button -->
		<div class="tallerpro-sidebar-bottom">
			<button type="button" class="tallerpro-collapse-btn" id="tallerCollapseBtn" title="Colapsar menú">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
					<polyline points="15 18 9 12 15 6"></polyline>
				</svg>
			</button>
		</div>
	</aside>

	<!-- Main Workspace -->
	<div class="tallerpro-main">
		<!-- Top Bar -->
		<header class="tallerpro-topbar">
			<div class="tallerpro-topbar-left">
				<button type="button" class="tallerpro-topbar-sidebar-toggle" id="tallerTopbarToggle" title="Alternar menú" aria-label="Alternar menú">
					<svg class="tallerpro-toggle-icon-desktop" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
						<line x1="9" y1="3" x2="9" y2="21"></line>
					</svg>
					<svg class="tallerpro-toggle-icon-mobile" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="3" y1="6" x2="21" y2="6"></line>
						<line x1="3" y1="12" x2="21" y2="12"></line>
						<line x1="3" y1="18" x2="21" y2="18"></line>
					</svg>
				</button>
				<span class="tallerpro-topbar-dot">•</span>
				<span class="tallerpro-topbar-title">TallerPro</span>
			</div>
			<div class="tallerpro-topbar-right"></div>
		</header>

		<!-- Dashboard Content -->
		<div class="tallerpro-content-wrapper">
			<main class="tallerpro-content">
				<!-- Header Actions & Title -->
				<div class="tallerpro-header-actions">
					<div class="tallerpro-content-header" style="margin-bottom: 0;">
						<h1 class="tallerpro-page-title"><?php echo $langs->trans("Materiales"); ?></h1>
						<p class="tallerpro-page-subtitle"><?php echo $total_materials; ?> registro(s)</p>
					</div>
					<div>
						<button type="button" class="tallerpro-btn-primary" id="tallerOpenModalBtn">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
								<line x1="12" y1="5" x2="12" y2="19"></line>
								<line x1="5" y1="12" x2="19" y2="12"></line>
							</svg>
							<span>Añadir</span>
						</button>
					</div>
				</div>

				<!-- Table Card -->
				<div class="tallerpro-table-card">
					<table class="tallerpro-table">
						<thead>
							<tr>
								<th>Nombre</th>
								<th>Referencia</th>
								<th>Proveedor</th>
								<th>Stock</th>
								<th>Precio/ud.</th>
								<th style="width: 80px; text-align: right;"></th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($materials_list)): ?>
								<tr>
									<td colspan="6" style="text-align: center; color: #64748b; padding: 32px 16px;">
										No hay materiales registrados aún. Haz clic en <strong>«Añadir»</strong> para registrar uno nuevo.
									</td>
								</tr>
							<?php else: ?>
								<?php foreach ($materials_list as $mat): ?>
									<tr>
										<td style="color: #1e293b; font-weight: 500;"><?php echo dol_escape_htmltag($mat->nombre); ?></td>
										<td style="color: #64748b;"><?php echo !empty($mat->ref) ? dol_escape_htmltag($mat->ref) : '—'; ?></td>
										<td style="color: #64748b;"><?php echo !empty($mat->proveedor) ? dol_escape_htmltag($mat->proveedor) : '—'; ?></td>
										<td style="color: #1e293b;"><?php echo (float) $mat->stock; ?></td>
										<td style="color: #1e293b; font-weight: 500;"><?php echo number_format((float) $mat->precio, 2, '.', ''); ?></td>
										<td>
											<div class="tallerpro-actions">
												<button type="button" class="tallerpro-action-btn btn-edit-material" title="Editar"
													data-id="<?php echo (int) $mat->rowid; ?>"
													data-nombre="<?php echo dol_escape_htmltag($mat->nombre); ?>"
													data-ref="<?php echo dol_escape_htmltag($mat->ref); ?>"
													data-proveedor="<?php echo dol_escape_htmltag($mat->proveedor); ?>"
													data-stock="<?php echo (float) $mat->stock; ?>"
													data-precio="<?php echo number_format((float) $mat->precio, 2, '.', ''); ?>"
													data-notas="<?php echo dol_escape_htmltag($mat->notas); ?>">
													<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
														<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
													</svg>
												</button>
												<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=delete_material&rowid=<?php echo (int) $mat->rowid; ?>&token=<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>"
													class="tallerpro-action-btn btn-delete-action"
													title="Eliminar">
													<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
														<polyline points="3 6 5 6 21 6"></polyline>
														<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
													</svg>
												</a>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</main>
		</div>
	</div>

	<!-- Modal Nuevo Registro Materiales -->
	<div class="tallerpro-modal-backdrop" id="tallerMaterialModal">
		<div class="tallerpro-modal-card">
			<div class="tallerpro-modal-header">
				<h2 class="tallerpro-modal-title" id="tallerMaterialModalTitle">Nuevo registro</h2>
				<button type="button" class="tallerpro-modal-close" id="tallerCloseModalBtn" title="Cerrar">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			

			<form class="tallerpro-modal-form" id="tallerMaterialForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
				<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
				<input type="hidden" name="action" id="materialFormAction" value="create_material">
				<input type="hidden" name="rowid" id="materialFormRowid" value="0">

								<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Nombre <span class="tallerpro-required">*</span></label>
					<input type="text" name="nombre" id="mat_nombre" class="tallerpro-form-input" placeholder="Ej: Filtro de aceite 5W30, Pastillas de freno..." required autocomplete="off" />
				</div>

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Referencia</label>
					<input type="text" name="ref" id="mat_ref" class="tallerpro-form-input" placeholder="Ej: BOSCH-0986, VAL-82634, FL-2026..." autocomplete="off" />
				</div>

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Proveedor</label>
					<input type="text" name="proveedor" id="mat_proveedor" list="proveedores_datalist" class="tallerpro-form-input" placeholder="Ej: Valeo, Bosch, Recambios García..." autocomplete="off" />
					<?php if (!empty($suppliers_list)): ?>
						<datalist id="proveedores_datalist">
							<?php foreach ($suppliers_list as $sup_name): ?>
								<option value="<?php echo dol_escape_htmltag($sup_name); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					<?php endif; ?>
				</div>

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Stock</label>
					<input type="number" step="any" name="stock" id="mat_stock" class="tallerpro-form-input" placeholder="Ej: 10" value="0" />
				</div>

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Precio (€)</label>
					<input type="number" step="0.01" name="precio" id="mat_precio" class="tallerpro-form-input" placeholder="Ej: 28.50" value="0.00" />
				</div>

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Notas</label>
					<textarea name="notas" id="mat_notas" class="tallerpro-form-textarea" rows="3" placeholder="Ej: Válido para motores 1.9 TDI y 2.0 HDI, ubicación estante B-3..."></textarea>
				</div>
			</form>
		</div>
	</div>

	<!-- Modal Confirmación de Eliminación -->
	<div class="tallerpro-modal-backdrop" id="deleteConfirmModal">
		<div class="tallerpro-modal-card" style="max-width: 440px; padding: 28px 24px; text-align: center;">
			<div style="width: 52px; height: 52px; border-radius: 50%; background-color: #fee2e2; color: #ef4444; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
				<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<polyline points="3 6 5 6 21 6"></polyline>
					<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
					<line x1="10" y1="11" x2="10" y2="17"></line>
					<line x1="14" y1="11" x2="14" y2="17"></line>
				</svg>
			</div>
			<h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">¿Estás seguro de que deseas borrar?</h3>
			<p style="font-size: 13.5px; color: #64748b; margin: 0 0 24px 0; line-height: 1.5;">Este material dejará de mostrarse en la lista activa, pero sus datos se conservarán en el sistema.</p>

			<div style="display: flex; gap: 12px; justify-content: center;">
				<button type="button" id="btnCancelDelete" style="flex: 1; padding: 10px 16px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px;">
					Rechazar
				</button>
				<button type="button" id="btnConfirmDelete" style="flex: 1; padding: 10px 16px; border: none; background: #ef4444; color: #ffffff; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);">
					Confirmar
				</button>
			</div>
		</div>
	</div>
</div>

<?php
// End of page
llxFooter();
$db->close();
