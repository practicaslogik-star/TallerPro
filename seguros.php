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

require_once __DIR__ . '/lib/taller.lib.php';
require_once __DIR__ . '/class/taller_seguro.class.php';

// Security check
tallerpro_require_permission('read');

$can_write = $user->hasRight('taller', 'write');
$can_delete = $user->hasRight('taller', 'delete');

$table_seguro = MAIN_DB_PREFIX . "taller_seguro";
$table_vehiculo = MAIN_DB_PREFIX . "taller_vehiculo";

$action = GETPOST('action', 'aZ09');

// Acción: Crear Seguro
if ($action == 'create_seguro' || $action == 'add_seguro') {
	tallerpro_require_post_csrf();
	tallerpro_require_permission('write');

	$fk_vehiculo  = GETPOSTINT('fk_vehiculo');
	$compania     = trim(GETPOST('compania', 'alphanohtml'));
	$poliza       = trim(GETPOST('poliza', 'alphanohtml'));
	$tipo         = trim(GETPOST('tipo', 'alphanohtml'));
	$fecha_inicio = trim(GETPOST('fecha_inicio', 'alpha'));
	$fecha_fin    = trim(GETPOST('fecha_fin', 'alpha'));
	$telefono     = trim(GETPOST('telefono', 'alphanohtml'));
	$notas        = trim(GETPOST('notas', 'restricthtml'));

	$vehiculo_valido = ($fk_vehiculo <= 0 || tallerpro_record_in_entity($db, 'taller_vehiculo', $fk_vehiculo, 'taller_vehiculo', 'status = 1'));

	if (empty($compania)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("FieldCompania")), null, 'errors');
	} elseif (!$vehiculo_valido) {
		setEventMessages($langs->trans("ErrVehicleNotInEntity"), null, 'errors');
	} else {
		$seg_obj = new TallerSeguro($db);
		$seg_obj->fk_vehiculo  = ($fk_vehiculo > 0 ? $fk_vehiculo : null);
		$seg_obj->compania     = $compania;
		$seg_obj->poliza       = $poliza;
		$seg_obj->tipo         = $tipo;
		$seg_obj->fecha_inicio = $fecha_inicio;
		$seg_obj->fecha_fin    = $fecha_fin;
		$seg_obj->telefono     = $telefono;
		$seg_obj->notas        = $notas;

		$res_create = $seg_obj->create($user);
		if ($res_create > 0) {
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF']);
			exit;
		} else {
			dol_syslog("TallerPro - Error al guardar seguro: " . $seg_obj->error . " " . implode(' ', $seg_obj->errors), LOG_ERR);
			setEventMessages($langs->trans("ErrSaveInsurance"), null, 'errors');
		}
	}
}

// Acción: Actualizar Seguro
if ($action == 'update_seguro' || $action == 'edit_seguro') {
	tallerpro_require_post_csrf();
	tallerpro_require_permission('write');

	$rowid        = GETPOSTINT('rowid');
	$fk_vehiculo  = GETPOSTINT('fk_vehiculo');
	$compania     = trim(GETPOST('compania', 'alphanohtml'));
	$poliza       = trim(GETPOST('poliza', 'alphanohtml'));
	$tipo         = trim(GETPOST('tipo', 'alphanohtml'));
	$fecha_inicio = trim(GETPOST('fecha_inicio', 'alpha'));
	$fecha_fin    = trim(GETPOST('fecha_fin', 'alpha'));
	$telefono     = trim(GETPOST('telefono', 'alphanohtml'));
	$notas        = trim(GETPOST('notas', 'restricthtml'));

	$vehiculo_valido = ($fk_vehiculo <= 0 || tallerpro_record_in_entity($db, 'taller_vehiculo', $fk_vehiculo, 'taller_vehiculo', 'status = 1'));

	if (empty($compania)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("FieldCompania")), null, 'errors');
	} elseif ($rowid <= 0) {
		setEventMessages($langs->trans("ErrInvalidInsuranceId"), null, 'errors');
	} elseif (!$vehiculo_valido) {
		setEventMessages($langs->trans("ErrVehicleNotInEntity"), null, 'errors');
	} else {
		$seg_obj = new TallerSeguro($db);
		if ($seg_obj->fetch($rowid) > 0) {
			$seg_obj->fk_vehiculo  = ($fk_vehiculo > 0 ? $fk_vehiculo : null);
			$seg_obj->compania     = $compania;
			$seg_obj->poliza       = $poliza;
			$seg_obj->tipo         = $tipo;
			$seg_obj->fecha_inicio = $fecha_inicio;
			$seg_obj->fecha_fin    = $fecha_fin;
			$seg_obj->telefono     = $telefono;
			$seg_obj->notas        = $notas;

			if ($seg_obj->update($user) > 0) {
				setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
				header("Location: " . $_SERVER['PHP_SELF']);
				exit;
			} else {
				dol_syslog("TallerPro - Error al actualizar seguro ID " . $rowid . ": " . $seg_obj->error . " " . implode(' ', $seg_obj->errors), LOG_ERR);
				setEventMessages($langs->trans("ErrUpdateInsurance"), null, 'errors');
			}
		} else {
			setEventMessages($langs->trans("ErrInsuranceNotFound"), null, 'errors');
		}
	}
}

// Acción: Eliminar Seguro (Seguro vía POST con token)
if ($action == 'delete_seguro' || $action == 'delete') {
	tallerpro_require_post_csrf();
	tallerpro_require_permission('delete');

	$rowid = GETPOSTINT('rowid');
	if ($rowid > 0) {
		$seg_obj = new TallerSeguro($db);
		if ($seg_obj->fetch($rowid) > 0 && $seg_obj->delete($user) > 0) {
			setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF']);
			exit;
		} else {
			dol_syslog("TallerPro - Error al eliminar seguro ID " . $rowid . ": " . $seg_obj->error, LOG_ERR);
			setEventMessages($langs->trans("ErrDeleteInsurance"), null, 'errors');
		}
	}
}

// Consultar vehículos para el desplegable
$preselected_veh = GETPOSTINT('new_vehiculo');

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

$morehead = tallerpro_build_morehead($langs);

// Page Header
llxHeader($morehead, $langs->trans("Seguros") . ' - ' . $langs->trans("TallerPro"), '', '', 0, 0, array(), array(), '', 'mod-taller page-tallerpro');

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
					<svg width="22" height="22" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path fill-rule="evenodd" clip-rule="evenodd" d="M 90.75 45.5 L 97.71 44.73 L 97.71 55.27 L 90.75 54.5 L 89.37 61.44 L 96.09 63.39 L 92.06 73.12 L 85.93 69.75 L 82 75.63 L 87.46 80.01 L 80.01 87.46 L 75.63 82 L 69.75 85.93 L 73.12 92.06 L 63.39 96.09 L 61.44 89.37 L 54.5 90.75 L 55.27 97.71 L 44.73 97.71 L 45.5 90.75 L 38.56 89.37 L 36.61 96.09 L 26.88 92.06 L 30.25 85.93 L 24.37 82 L 19.99 87.46 L 12.54 80.01 L 18 75.63 L 14.07 69.75 L 7.94 73.12 L 3.91 63.39 L 10.63 61.44 L 9.25 54.5 L 2.29 55.27 L 2.29 44.73 L 9.25 45.5 L 10.63 38.56 L 3.91 36.61 L 7.94 26.88 L 14.07 30.25 L 18 24.37 L 12.54 19.99 L 19.99 12.54 L 24.37 18 L 30.25 14.07 L 26.88 7.94 L 36.61 3.91 L 38.56 10.63 L 45.5 9.25 L 44.73 2.29 L 55.27 2.29 L 54.5 9.25 L 61.44 10.63 L 63.39 3.91 L 73.12 7.94 L 69.75 14.07 L 75.63 18 L 80.01 12.54 L 87.46 19.99 L 82 24.37 L 85.93 30.25 L 92.06 26.88 L 96.09 36.61 L 89.37 38.56 Z M 50,17 A 33 33 0 1 0 50,83 A 33 33 0 1 0 50,17 Z" fill="#ffffff"/>
						<g transform="translate(50,50)">
							<g transform="rotate(-45)">
								<rect x="-3" y="-22" width="6" height="44" rx="1.5" fill="#ffffff"/>
								<path d="M -8 -20 C -8 -27 -4 -31 0 -31 C 4 -31 8 -27 8 -20 C 8 -18 6 -17 3.5 -17 L 3.5 -23.5 C 3.5 -25 1.5 -26.5 0 -26.5 C -1.5 -26.5 -3.5 -25 -3.5 -23.5 L -3.5 -17 C -6 -17 -8 -18 -8 -20 Z" fill="#ffffff"/>
								<path d="M -8 -20 C -8 -27 -4 -31 0 -31 C 4 -31 8 -27 8 -20 C 8 -18 6 -17 3.5 -17 L 3.5 -23.5 C 3.5 -25 1.5 -26.5 0 -26.5 C -1.5 -26.5 -3.5 -25 -3.5 -23.5 L -3.5 -17 C -6 -17 -8 -18 -8 -20 Z" transform="rotate(180)" fill="#ffffff"/>
							</g>
							<g transform="rotate(45)">
								<rect x="-4.5" y="-29" width="9" height="57" rx="3" fill="#dc2626"/>
								<rect x="-2" y="-24" width="4" height="27" fill="#ffffff"/>
								<polygon points="-2.5,-24 2.5,-24 1.8,-28 -1.8,-28" fill="#ffffff"/>
								<path d="M -5 3 C -5 1 -3 0 0 0 C 3 0 5 1 5 3 L 6 18 C 6 23.5 3 26 0 26 C -3 26 -6 23.5 -6 18 Z" fill="#ffffff"/>
								<line x1="-1.8" y1="7" x2="-1.8" y2="19" stroke="#dc2626" stroke-width="1.2" stroke-linecap="round"/>
								<line x1="1.8" y1="7" x2="1.8" y2="19" stroke="#dc2626" stroke-width="1.2" stroke-linecap="round"/>
							</g>
						</g>
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
					<?php if (!empty($user->admin)): ?>
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
					<?php endif; ?>
				</ul>
			</div>
		</div>

		<!-- Collapse Button -->
		<div class="tallerpro-sidebar-bottom">
			<button type="button" class="tallerpro-collapse-btn" id="tallerCollapseBtn" title="<?php echo dol_escape_htmltag($langs->trans("ColapsarMenu")); ?>">
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
			<button type="button" class="tallerpro-topbar-sidebar-toggle" id="tallerTopbarToggle" title="<?php echo dol_escape_htmltag($langs->trans("AlternarMenu")); ?>" aria-label="<?php echo dol_escape_htmltag($langs->trans("AlternarMenu")); ?>">
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
			<div class="tallerpro-topbar-brand">
				<span class="tallerpro-topbar-dot">•</span>
				<div class="tallerpro-topbar-breadcrumb">
					<a href="<?php echo $index_url; ?>" class="tallerpro-topbar-breadcrumb-parent">TallerPro</a>
					<span class="tallerpro-topbar-breadcrumb-sep">/</span>
					<span class="tallerpro-topbar-breadcrumb-current"><?php echo $langs->trans("Seguros"); ?></span>
				</div>
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
						<p class="tallerpro-page-subtitle"><?php echo $langs->trans("RegistrosCount", $total_seguros); ?></p>
					</div>
					<?php if ($can_write): ?>
					<div>
						<button type="button" class="tallerpro-btn-primary" id="tallerOpenModalBtn">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
								<line x1="12" y1="5" x2="12" y2="19"></line>
								<line x1="5" y1="12" x2="19" y2="12"></line>
							</svg>
							<span><?php echo $langs->trans("Anadir"); ?></span>
						</button>
					</div>
					<?php endif; ?>
				</div>

				<?php echo dol_htmloutput_events(); ?>

				<?php if (empty($seguros_list)): ?>
					<!-- Empty State Card -->
					<div class="tallerpro-empty-card">
						<?php echo $langs->trans("SinRegistrosPrimer"); ?>
					</div>
				<?php else: ?>
					<!-- Table Card -->
					<div class="tallerpro-table-card">
						<table class="tallerpro-table">
							<thead>
								<tr>
									<th><?php echo $langs->trans("Vehiculo"); ?></th>
									<th><?php echo $langs->trans("Compania"); ?></th>
									<th><?php echo $langs->trans("NumeroPoliza"); ?></th>
									<th><?php echo $langs->trans("Tipo"); ?></th>
									<th><?php echo $langs->trans("Vigencia"); ?></th>
									<th><?php echo $langs->trans("Telefono"); ?></th>
									<th style="width: 80px; text-align: right;"></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($seguros_list as $seg): ?>
									<tr>
										<td style="font-weight: 600; color: #1e293b;">
											<?php if (!empty($seg->fk_vehiculo)): ?>
												<a href="<?php echo $vehiculos_url . '?id=' . ((int) $seg->fk_vehiculo); ?>" style="color: inherit; text-decoration: none;" title="<?php echo dol_escape_htmltag($langs->trans("VerVehiculo")); ?>">
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
												echo dol_escape_htmltag($seg->fecha_inicio) . ' ' . dol_escape_htmltag($langs->trans("ConectorAl")) . ' ' . dol_escape_htmltag($seg->fecha_fin);
											} elseif ($has_ini) {
												echo dol_escape_htmltag($langs->trans("VigenciaDesde")) . ' ' . dol_escape_htmltag($seg->fecha_inicio);
											} elseif ($has_fin) {
												echo dol_escape_htmltag($langs->trans("VigenciaHasta")) . ' ' . dol_escape_htmltag($seg->fecha_fin);
											} else {
												echo '—';
											}
											?>
										</td>
										<td style="color: #334155;">
											<?php if (!empty($seg->telefono)): ?>
												<a href="tel:<?php echo dol_escape_htmltag($seg->telefono); ?>" style="color: #dc2626; font-weight: 500; text-decoration: none;">
													<?php echo dol_escape_htmltag($seg->telefono); ?>
												</a>
											<?php else: ?>
												—
											<?php endif; ?>
										</td>
										<td>
											<div class="tallerpro-actions">
												<?php if ($can_write): ?>
												<button type="button" class="tallerpro-action-btn btn-edit-seguro" title="<?php echo dol_escape_htmltag($langs->trans("Editar")); ?>"
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
												<?php endif; ?>
												<?php if ($can_delete): ?>
												<button type="button"
													class="tallerpro-action-btn btn-delete-action"
													title="<?php echo dol_escape_htmltag($langs->trans("Eliminar")); ?>"
													data-id="<?php echo (int) $seg->rowid; ?>"
													data-action="delete_seguro">
													<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
														<polyline points="3 6 5 6 21 6"></polyline>
														<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
													</svg>
												</button>
												<?php endif; ?>
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
		<div class="tallerpro-modal-card" style="max-width: 540px;">
			<div class="tallerpro-modal-header">
				<h2 class="tallerpro-modal-title" id="tallerSeguroModalTitle"><?php echo $langs->trans("NuevoRegistro"); ?></h2>
				<button type="button" class="tallerpro-modal-close" id="tallerCloseModalBtn" title="<?php echo dol_escape_htmltag($langs->trans("Cerrar")); ?>">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			<form class="tallerpro-modal-form" id="tallerSeguroForm" method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>">
				<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
				<input type="hidden" name="action" id="seguroFormAction" value="create_seguro">
				<input type="hidden" name="rowid" id="seguroFormRowid" value="0">

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("Vehiculo"); ?></label>
					<select name="fk_vehiculo" id="seg_fk_vehiculo" class="tallerpro-form-select"<?php if ($preselected_veh > 0) echo ' data-preselected="' . $preselected_veh . '"'; ?>>
						<option value=""><?php echo $langs->trans("SelectVehicleOption"); ?></option>
						<?php foreach ($vehicles_list as $veh): ?>
							<option value="<?php echo (int) $veh->rowid; ?>"<?php if ($preselected_veh == (int) $veh->rowid) echo ' selected'; ?>>
								<?php echo dol_escape_htmltag($veh->matricula . (!empty($veh->marca) ? ' - ' . $veh->marca : '') . (!empty($veh->modelo) ? ' ' . $veh->modelo : '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("Compania"); ?> <span class="tallerpro-required">*</span></label>
					<input type="text" name="compania" id="seg_compania" class="tallerpro-form-input" required />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("NumeroPoliza"); ?></label>
					<input type="text" name="poliza" id="seg_poliza" class="tallerpro-form-input" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("TipoSeguroLabel"); ?></label>
					<select name="tipo" id="seg_tipo" class="tallerpro-form-select">
						<option value="Responsabilidad civil obligatoria"><?php echo $langs->trans("InsTypeRC"); ?></option>
						<option value="Terceros básico"><?php echo $langs->trans("InsTypeTercerosBasico"); ?></option>
						<option value="Terceros ampliado"><?php echo $langs->trans("InsTypeTercerosAmpliado"); ?></option>
						<option value="Todo riesgo con franquicia"><?php echo $langs->trans("InsTypeTodoRiesgoFranq"); ?></option>
						<option value="Todo riesgo sin franquicia"><?php echo $langs->trans("InsTypeTodoRiesgoSinFranq"); ?></option>
						<option value="Otro / No especificado" selected><?php echo $langs->trans("TypeOtro"); ?></option>
					</select>
				</div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label"><?php echo $langs->trans("FechaInicioLabel"); ?></label>
						<input type="date" name="fecha_inicio" id="seg_fecha_inicio" class="tallerpro-form-input" />
					</div>
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label"><?php echo $langs->trans("FechaFinLabel"); ?></label>
						<input type="date" name="fecha_fin" id="seg_fecha_fin" class="tallerpro-form-input" />
					</div>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("Telefono"); ?></label>
					<input type="text" name="telefono" id="seg_telefono" class="tallerpro-form-input" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("Notas"); ?></label>
					<textarea name="notas" id="seg_notas" class="tallerpro-form-textarea" rows="3"></textarea>
				</div>
				<div class="tallerpro-modal-footer">
					<button type="submit" class="tallerpro-btn-submit" id="tallerSaveModalBtn"><?php echo $langs->trans("Guardar"); ?></button>
				</div>
			</form>
		</div>
	</div>

	<!-- Modal Confirmación de Eliminación Global -->
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
			<h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;"><?php echo $langs->trans("ConfirmDeleteTitle"); ?></h3>
			<p style="font-size: 13.5px; color: #64748b; margin: 0 0 24px 0; line-height: 1.5;"><?php echo $langs->trans("ConfirmDeleteBodyGeneric"); ?></p>

			<div style="display: flex; gap: 12px; justify-content: center;">
				<button type="button" id="btnCancelDelete" style="flex: 1; padding: 10px 16px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px;">
					<?php echo $langs->trans("Rechazar"); ?>
				</button>
				<button type="button" id="btnConfirmDelete" style="flex: 1; padding: 10px 16px; border: none; background: #ef4444; color: #ffffff; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);">
					<?php echo $langs->trans("Confirmar"); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Formulario oculto para borrado seguro POST con CSRF Token -->
	<form id="tallerGlobalDeleteForm" method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" style="display: none;">
		<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
		<input type="hidden" name="action" id="globalDeleteAction" value="">
		<input type="hidden" name="rowid" id="globalDeleteRowid" value="">
		<input type="hidden" name="socid" id="globalDeleteSocid" value="">
	</form>
</div>

<?php
// End of page
llxFooter();
$db->close();
