<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       taller/seguros.php
 *  \ingroup    taller
 *  \brief      Seguros list view - TallerPro view
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

$table_seguro = MAIN_DB_PREFIX . "taller_seguro";
$table_vehiculo = MAIN_DB_PREFIX . "taller_vehiculo";

// Ensure table exists
$sql_create = "CREATE TABLE IF NOT EXISTS " . $table_seguro . " (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    fk_vehiculo     INT DEFAULT NULL,
    compania        VARCHAR(128) NOT NULL,
    poliza          VARCHAR(64) DEFAULT NULL,
    tipo            VARCHAR(64) DEFAULT NULL,
    fecha_inicio    DATE DEFAULT NULL,
    fecha_fin       DATE DEFAULT NULL,
    telefono        VARCHAR(32) DEFAULT NULL,
    notas           TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat   INT NOT NULL,
    entity          INT DEFAULT 1,
    status          SMALLINT DEFAULT 1,
    INDEX idx_taller_seguro_vehiculo (fk_vehiculo),
    INDEX idx_taller_seguro_compania (compania)
) ENGINE=innodb;";
$db->query($sql_create);

$action = GETPOST('action', 'alpha');
if (empty($action) && !empty($_POST['action'])) {
	$action = $_POST['action'];
}

// Acción: Crear Seguro
if ($action == 'create_seguro' || $action == 'add_seguro') {
	$fk_vehiculo = GETPOSTINT('fk_vehiculo');
	if (empty($fk_vehiculo) && !empty($_POST['fk_vehiculo'])) {
		$fk_vehiculo = (int) $_POST['fk_vehiculo'];
	}

	$compania = trim(GETPOST('compania', 'alphanohtml'));
	if (empty($compania) && !empty($_POST['compania'])) {
		$compania = trim($_POST['compania']);
	}

	$poliza = trim(GETPOST('poliza', 'alphanohtml'));
	if (empty($poliza) && !empty($_POST['poliza'])) {
		$poliza = trim($_POST['poliza']);
	}

	$tipo = trim(GETPOST('tipo', 'alphanohtml'));
	if (empty($tipo) && !empty($_POST['tipo'])) {
		$tipo = trim($_POST['tipo']);
	}

	$fecha_inicio = trim(GETPOST('fecha_inicio', 'alpha'));
	if (empty($fecha_inicio) && !empty($_POST['fecha_inicio'])) {
		$fecha_inicio = trim($_POST['fecha_inicio']);
	}

	$fecha_fin = trim(GETPOST('fecha_fin', 'alpha'));
	if (empty($fecha_fin) && !empty($_POST['fecha_fin'])) {
		$fecha_fin = trim($_POST['fecha_fin']);
	}

	$telefono = trim(GETPOST('telefono', 'alphanohtml'));
	if (empty($telefono) && !empty($_POST['telefono'])) {
		$telefono = trim($_POST['telefono']);
	}

	$notas = trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) {
		$notas = trim($_POST['notas']);
	}

	if (empty($compania)) {
		setEventMessages($langs->trans("ErrorFieldRequired", "Compañía"), null, 'errors');
	} else {
		$sql_insert = "INSERT INTO " . $table_seguro . " (";
		$sql_insert .= "fk_vehiculo, compania, poliza, tipo, fecha_inicio, fecha_fin, telefono, notas, date_creation, fk_user_creat, entity, status";
		$sql_insert .= ") VALUES (";
		$sql_insert .= ($fk_vehiculo > 0 ? ((int) $fk_vehiculo) : "NULL") . ", ";
		$sql_insert .= "'" . $db->escape($compania) . "', ";
		$sql_insert .= "'" . $db->escape($poliza) . "', ";
		$sql_insert .= "'" . $db->escape($tipo) . "', ";
		$sql_insert .= (!empty($fecha_inicio) ? "'" . $db->escape($fecha_inicio) . "'" : "NULL") . ", ";
		$sql_insert .= (!empty($fecha_fin) ? "'" . $db->escape($fecha_fin) . "'" : "NULL") . ", ";
		$sql_insert .= "'" . $db->escape($telefono) . "', ";
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
			setEventMessages("Error al guardar el seguro: " . $db->lasterror(), null, 'errors');
		}
	}
}

// Acción: Actualizar Seguro
if ($action == 'update_seguro' || $action == 'edit_seguro') {
	$rowid = GETPOSTINT('rowid');
	if (empty($rowid) && !empty($_POST['rowid'])) {
		$rowid = (int) $_POST['rowid'];
	}

	$fk_vehiculo = GETPOSTINT('fk_vehiculo');
	if (empty($fk_vehiculo) && !empty($_POST['fk_vehiculo'])) {
		$fk_vehiculo = (int) $_POST['fk_vehiculo'];
	}

	$compania = trim(GETPOST('compania', 'alphanohtml'));
	if (empty($compania) && !empty($_POST['compania'])) {
		$compania = trim($_POST['compania']);
	}

	$poliza = trim(GETPOST('poliza', 'alphanohtml'));
	if (empty($poliza) && !empty($_POST['poliza'])) {
		$poliza = trim($_POST['poliza']);
	}

	$tipo = trim(GETPOST('tipo', 'alphanohtml'));
	if (empty($tipo) && !empty($_POST['tipo'])) {
		$tipo = trim($_POST['tipo']);
	}

	$fecha_inicio = trim(GETPOST('fecha_inicio', 'alpha'));
	if (empty($fecha_inicio) && !empty($_POST['fecha_inicio'])) {
		$fecha_inicio = trim($_POST['fecha_inicio']);
	}

	$fecha_fin = trim(GETPOST('fecha_fin', 'alpha'));
	if (empty($fecha_fin) && !empty($_POST['fecha_fin'])) {
		$fecha_fin = trim($_POST['fecha_fin']);
	}

	$telefono = trim(GETPOST('telefono', 'alphanohtml'));
	if (empty($telefono) && !empty($_POST['telefono'])) {
		$telefono = trim($_POST['telefono']);
	}

	$notas = trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) {
		$notas = trim($_POST['notas']);
	}

	if (empty($compania)) {
		setEventMessages($langs->trans("ErrorFieldRequired", "Compañía"), null, 'errors');
	} elseif ($rowid <= 0) {
		setEventMessages("Identificador de seguro no válido", null, 'errors');
	} else {
		$sql_update = "UPDATE " . $table_seguro . " SET ";
		$sql_update .= "fk_vehiculo = " . ($fk_vehiculo > 0 ? ((int) $fk_vehiculo) : "NULL") . ", ";
		$sql_update .= "compania = '" . $db->escape($compania) . "', ";
		$sql_update .= "poliza = '" . $db->escape($poliza) . "', ";
		$sql_update .= "tipo = '" . $db->escape($tipo) . "', ";
		$sql_update .= "fecha_inicio = " . (!empty($fecha_inicio) ? "'" . $db->escape($fecha_inicio) . "'" : "NULL") . ", ";
		$sql_update .= "fecha_fin = " . (!empty($fecha_fin) ? "'" . $db->escape($fecha_fin) . "'" : "NULL") . ", ";
		$sql_update .= "telefono = '" . $db->escape($telefono) . "', ";
		$sql_update .= "notas = '" . $db->escape($notas) . "' ";
		$sql_update .= "WHERE rowid = " . ((int) $rowid) . " AND entity IN (" . getEntity('taller_seguro') . ")";

		$resql = $db->query($sql_update);
		if ($resql) {
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF']);
			exit;
		} else {
			setEventMessages("Error al actualizar el seguro: " . $db->lasterror(), null, 'errors');
		}
	}
}

// Acción: Eliminar Seguro
if ($action == 'delete_seguro' || $action == 'delete') {
	$rowid = GETPOSTINT('rowid');
	if ($rowid > 0) {
		$sql_del = "UPDATE " . $table_seguro . " SET status = 0 WHERE rowid = " . ((int) $rowid) . " AND entity IN (" . getEntity('taller_seguro') . ")";
		$resql = $db->query($sql_del);
		if ($resql) {
			setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF']);
			exit;
		} else {
			setEventMessages("Error al eliminar el seguro: " . $db->lasterror(), null, 'errors');
		}
	}
}

// Consultar vehículos para el desplegable
$vehicles_list = array();
$sql_v = "SELECT rowid, matricula, marca, modelo FROM " . $table_vehiculo;
$sql_v .= " WHERE status = 1 AND entity IN (" . getEntity('taller_vehiculo') . ") ORDER BY matricula ASC";
$res_v = $db->query($sql_v);
if ($res_v) {
	while ($v = $db->fetch_object($res_v)) {
		$vehicles_list[] = $v;
	}
}

// Consultar seguros con JOIN a vehículos
$seguros_list = array();
$sql_s = "SELECT s.rowid, s.fk_vehiculo, s.compania, s.poliza, s.tipo, s.fecha_inicio, s.fecha_fin, s.telefono, s.notas, ";
$sql_s .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo ";
$sql_s .= "FROM " . $table_seguro . " as s ";
$sql_s .= "LEFT JOIN " . $table_vehiculo . " as v ON s.fk_vehiculo = v.rowid ";
$sql_s .= "WHERE s.status = 1 AND s.entity IN (" . getEntity('taller_seguro') . ") ";
$sql_s .= "ORDER BY s.rowid DESC";
$res_s = $db->query($sql_s);
if ($res_s) {
	while ($s = $db->fetch_object($res_s)) {
		$seguros_list[] = $s;
	}
}
$total_seguros = count($seguros_list);

$css_file = __DIR__ . '/css/tallerpro.css';
$js_file = __DIR__ . '/js/tallerpro.js';
$css_version = file_exists($css_file) ? filemtime($css_file) : dol_now();
$js_version = file_exists($js_file) ? filemtime($js_file) : dol_now();

$css_url = dol_buildpath('/taller/css/tallerpro.css', 1) . '?v=' . $css_version;
$js_url = dol_buildpath('/taller/js/tallerpro.js', 1) . '?v=' . $js_version;

$morehead = '<link rel="stylesheet" type="text/css" href="'.$css_url.'">'."\n";
$morehead .= '<script src="'.$js_url.'"></script>'."\n";

// Page Header
llxHeader('', 'Seguros - TallerPro', '', '', 0, 0, array(), array(), $morehead, 'mod-taller page-tallerpro');

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
						<a href="<?php echo $materiales_url; ?>" class="tallerpro-nav-item">
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
						<a href="<?php echo $seguros_url; ?>" class="tallerpro-nav-item active">
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
						<h1 class="tallerpro-page-title"><?php echo $langs->trans("Seguros"); ?></h1>
						<p class="tallerpro-page-subtitle"><?php echo $total_seguros; ?> registro(s)</p>
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

				<?php echo dol_htmloutput_events(); ?>

				<?php if (empty($seguros_list)): ?>
					<!-- Empty State Card -->
					<div class="tallerpro-empty-card">
						Aún no hay registros. Añade el primero.
					</div>
				<?php else: ?>
					<!-- Table Card -->
					<div class="tallerpro-table-card">
						<table class="tallerpro-table">
							<thead>
								<tr>
									<th>Vehículo</th>
									<th>Compañía</th>
									<th>Nº de póliza</th>
									<th>Tipo</th>
									<th>Vigencia</th>
									<th>Teléfono</th>
									<th style="width: 80px; text-align: right;"></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($seguros_list as $seg): ?>
									<tr>
										<td style="font-weight: 600; color: #1e293b;">
											<?php if (!empty($seg->fk_vehiculo)): ?>
												<a href="<?php echo $vehiculos_url . '?id=' . ((int) $seg->fk_vehiculo); ?>" style="color: inherit; text-decoration: none;" title="Ver vehículo">
													<?php echo dol_escape_htmltag($seg->vehiculo_matricula ?: '—'); ?>
													<?php if (!empty($seg->vehiculo_marca) || !empty($seg->vehiculo_modelo)): ?>
														<span style="font-weight: normal; color: #64748b; font-size: 0.82rem; display: block;">
															<?php echo dol_escape_htmltag(trim($seg->vehiculo_marca . ' ' . $seg->vehiculo_modelo)); ?>
														</span>
													<?php endif; ?>
												</a>
											<?php else: ?>
												<span style="color: #94a3b8; font-weight: normal;">—</span>
											<?php endif; ?>
										</td>
										<td style="font-weight: 600; color: #1e293b;">
											<?php echo dol_escape_htmltag($seg->compania); ?>
										</td>
										<td style="color: #334155;">
											<?php echo !empty($seg->poliza) ? dol_escape_htmltag($seg->poliza) : '—'; ?>
										</td>
										<td>
											<?php if (!empty($seg->tipo)): ?>
												<span class="tallerpro-badge">
													<?php echo dol_escape_htmltag($seg->tipo); ?>
												</span>
											<?php else: ?>
												—
											<?php endif; ?>
										</td>
										<td style="color: #334155; font-size: 0.85rem;">
											<?php 
											$has_ini = !empty($seg->fecha_inicio);
											$has_fin = !empty($seg->fecha_fin);
											if ($has_ini && $has_fin) {
												echo dol_escape_htmltag($seg->fecha_inicio) . ' al ' . dol_escape_htmltag($seg->fecha_fin);
											} elseif ($has_ini) {
												echo 'Desde ' . dol_escape_htmltag($seg->fecha_inicio);
											} elseif ($has_fin) {
												echo 'Hasta ' . dol_escape_htmltag($seg->fecha_fin);
											} else {
												echo '—';
											}
											?>
										</td>
										<td style="color: #334155;">
											<?php if (!empty($seg->telefono)): ?>
												<a href="tel:<?php echo dol_escape_htmltag($seg->telefono); ?>" style="color: #2563eb; text-decoration: none;">
													<?php echo dol_escape_htmltag($seg->telefono); ?>
												</a>
											<?php else: ?>
												—
											<?php endif; ?>
										</td>
										<td>
											<div class="tallerpro-actions">
												<button type="button" class="tallerpro-action-btn btn-edit-seguro" title="Editar"
													data-id="<?php echo (int) $seg->rowid; ?>"
													data-vehiculo="<?php echo (int) $seg->fk_vehiculo; ?>"
													data-compania="<?php echo dol_escape_htmltag($seg->compania); ?>"
													data-poliza="<?php echo dol_escape_htmltag($seg->poliza); ?>"
													data-tipo="<?php echo dol_escape_htmltag($seg->tipo); ?>"
													data-fechainicio="<?php echo dol_escape_htmltag($seg->fecha_inicio); ?>"
													data-fechafin="<?php echo dol_escape_htmltag($seg->fecha_fin); ?>"
													data-telefono="<?php echo dol_escape_htmltag($seg->telefono); ?>"
													data-notas="<?php echo dol_escape_htmltag($seg->notas); ?>">
													<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
														<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
													</svg>
												</button>
												<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=delete_seguro&rowid=<?php echo (int) $seg->rowid; ?>&token=<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>"
													class="tallerpro-action-btn"
													title="Eliminar"
													onclick="return confirm('¿Estás seguro de que deseas eliminar este seguro?');">
													<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
														<polyline points="3 6 5 6 21 6"></polyline>
														<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
													</svg>
												</a>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</main>
		</div>
	</div>

	<!-- Modal Registro Seguros -->
	<div class="tallerpro-modal-backdrop" id="tallerSeguroModal">
		<div class="tallerpro-modal-card">
			<div class="tallerpro-modal-header">
				<h2 class="tallerpro-modal-title" id="tallerSeguroModalTitle">Nuevo registro</h2>
				<button type="button" class="tallerpro-modal-close" id="tallerCloseModalBtn" title="Cerrar">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			<form class="tallerpro-modal-form" id="tallerSeguroForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
				<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
				<input type="hidden" name="action" id="seguroFormAction" value="create_seguro">
				<input type="hidden" name="rowid" id="seguroFormRowid" value="0">

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Vehículo</label>
					<select name="fk_vehiculo" id="seg_fk_vehiculo" class="tallerpro-form-select">
						<option value="">— Seleccionar vehículo —</option>
						<?php foreach ($vehicles_list as $veh): ?>
							<option value="<?php echo (int) $veh->rowid; ?>">
								<?php echo dol_escape_htmltag($veh->matricula . (!empty($veh->marca) ? ' - ' . $veh->marca : '') . (!empty($veh->modelo) ? ' ' . $veh->modelo : '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Compañía <span class="tallerpro-required">*</span></label>
					<input type="text" name="compania" id="seg_compania" class="tallerpro-form-input" required />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Nº de póliza</label>
					<input type="text" name="poliza" id="seg_poliza" class="tallerpro-form-input" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Tipo</label>
					<input type="text" name="tipo" id="seg_tipo" class="tallerpro-form-input" placeholder="Todo riesgo, terceros..." />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Inicio</label>
					<input type="date" name="fecha_inicio" id="seg_fecha_inicio" class="tallerpro-form-input" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Fin</label>
					<input type="date" name="fecha_fin" id="seg_fecha_fin" class="tallerpro-form-input" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Teléfono</label>
					<input type="text" name="telefono" id="seg_telefono" class="tallerpro-form-input" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Notas</label>
					<textarea name="notas" id="seg_notas" class="tallerpro-form-textarea" rows="3"></textarea>
				</div>
				<div class="tallerpro-modal-footer">
					<button type="submit" class="tallerpro-btn-submit" id="tallerSaveModalBtn">Guardar</button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php
// End of page
llxFooter();
$db->close();
