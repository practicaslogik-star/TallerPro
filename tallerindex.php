<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       taller/tallerindex.php
 *  \ingroup    taller
 *  \brief      Home page of taller module - TallerPro view
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
// Try main.inc.php using relative path
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
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array("taller@taller"));

// Security check
if (!isModEnabled('taller')) {
	accessforbidden('Module not enabled');
}

// AJAX: Búsqueda global en el Dashboard
if (GETPOST('action', 'a_z09') == 'search_all') {
	$q = trim(GETPOST('q', 'alphanohtml'));
	$results = array(
		'vehiculos' => array(),
		'clientes' => array(),
		'materiales' => array(),
		'facturas' => array(),
	);
	if (strlen($q) >= 2) {
		$q_esc = $db->escape($q);
		// Vehículos
		$sql_v = "SELECT v.rowid, v.matricula, v.marca, v.modelo, s.nom as cliente ";
		$sql_v .= "FROM " . MAIN_DB_PREFIX . "taller_vehiculo as v ";
		$sql_v .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON v.fk_soc = s.rowid ";
		$sql_v .= "WHERE v.status = 1 AND (v.matricula LIKE '%" . $q_esc . "%' OR v.marca LIKE '%" . $q_esc . "%' OR v.modelo LIKE '%" . $q_esc . "%') LIMIT 5";
		$res_v = $db->query($sql_v);
		if ($res_v) {
			while ($o = $db->fetch_object($res_v)) {
				$results['vehiculos'][] = array(
					'id' => $o->rowid,
					'title' => $o->matricula,
					'subtitle' => trim($o->marca . ' ' . $o->modelo) . ($o->cliente ? ' · ' . $o->cliente : ''),
					'url' => dol_buildpath('/taller/vehiculos.php', 1) . '?id=' . $o->rowid
				);
			}
		}
		// Clientes
		$sql_c = "SELECT rowid, nom, siren, email, phone FROM " . MAIN_DB_PREFIX . "societe WHERE client IN (1, 2, 3) AND (nom LIKE '%" . $q_esc . "%' OR siren LIKE '%" . $q_esc . "%' OR email LIKE '%" . $q_esc . "%' OR phone LIKE '%" . $q_esc . "%') LIMIT 5";
		$res_c = $db->query($sql_c);
		if ($res_c) {
			while ($o = $db->fetch_object($res_c)) {
				$results['clientes'][] = array(
					'id' => $o->rowid,
					'title' => $o->nom,
					'subtitle' => $o->siren ?: ($o->phone ?: $o->email),
					'url' => dol_buildpath('/taller/clientes.php', 1) . '?id=' . $o->rowid
				);
			}
		}
		// Materiales
		$sql_m = "SELECT rowid, nombre, ref, precio FROM " . MAIN_DB_PREFIX . "taller_material WHERE status = 1 AND (nombre LIKE '%" . $q_esc . "%' OR ref LIKE '%" . $q_esc . "%') LIMIT 5";
		$res_m = $db->query($sql_m);
		if ($res_m) {
			while ($o = $db->fetch_object($res_m)) {
				$results['materiales'][] = array(
					'id' => $o->rowid,
					'title' => $o->nombre,
					'subtitle' => ($o->ref ? 'Ref: ' . $o->ref . ' · ' : '') . number_format((float) $o->precio, 2, ',', '.') . ' €',
					'url' => dol_buildpath('/taller/materiales.php', 1)
				);
			}
		}
		// Facturas
		$sql_f = "SELECT rowid, numero_factura, total, estado FROM " . MAIN_DB_PREFIX . "taller_factura WHERE status = 1 AND numero_factura LIKE '%" . $q_esc . "%' LIMIT 5";
		$res_f = $db->query($sql_f);
		if ($res_f) {
			while ($o = $db->fetch_object($res_f)) {
				$results['facturas'][] = array(
					'id' => $o->rowid,
					'title' => 'Factura ' . $o->numero_factura,
					'subtitle' => number_format((float) $o->total, 2, ',', '.') . ' € · ' . $o->estado,
					'url' => dol_buildpath('/taller/facturas.php', 1) . '?id=' . $o->rowid
				);
			}
		}
	}
	header('Content-Type: application/json');
	echo json_encode($results);
	exit;
}

// 1. Contador real de clientes en Dolibarr (módulo Terceros)
$nb_clientes = 0;
$sql = "SELECT COUNT(rowid) as total FROM " . MAIN_DB_PREFIX . "societe WHERE client IN (1, 2, 3) AND entity IN (" . getEntity('societe') . ")";
$resql = $db->query($sql);
if ($resql && ($obj = $db->fetch_object($resql))) {
	$nb_clientes = (int) $obj->total;
}

// 2. Contador real de vehículos
$nb_vehiculos = 0;
$sql_veh = "SELECT COUNT(rowid) as total FROM " . MAIN_DB_PREFIX . "taller_vehiculo WHERE status = 1 AND entity IN (" . getEntity('taller_vehiculo') . ")";
$resql_veh = $db->query($sql_veh);
if ($resql_veh && ($obj_veh = $db->fetch_object($resql_veh))) {
	$nb_vehiculos = (int) $obj_veh->total;
}

// 3. Contador real de reparaciones
$nb_reparaciones = 0;
$sql_rep = "SELECT COUNT(rowid) as total FROM " . MAIN_DB_PREFIX . "taller_reparacion WHERE status = 1 AND entity IN (" . getEntity('taller_reparacion') . ")";
$resql_rep = $db->query($sql_rep);
if ($resql_rep && ($obj_rep = $db->fetch_object($resql_rep))) {
	$nb_reparaciones = (int) $obj_rep->total;
}

// 4. Contador real de materiales
$nb_materiales = 0;
$sql_mat = "SELECT COUNT(rowid) as total FROM " . MAIN_DB_PREFIX . "taller_material WHERE status = 1 AND entity IN (" . getEntity('taller_material') . ")";
$resql_mat = $db->query($sql_mat);
if ($resql_mat && ($obj_mat = $db->fetch_object($resql_mat))) {
	$nb_materiales = (int) $obj_mat->total;
}

// 7. Consultar últimas reparaciones con JOIN de vehículo
$ultimas_reparaciones = array();
$sql_ult_rep = "SELECT r.rowid, r.fecha, r.descripcion, r.coste_mo, v.rowid as vehiculo_id, v.matricula, v.marca, v.modelo ";
$sql_ult_rep .= "FROM " . MAIN_DB_PREFIX . "taller_reparacion as r ";
$sql_ult_rep .= "LEFT JOIN " . MAIN_DB_PREFIX . "taller_vehiculo as v ON r.fk_vehiculo = v.rowid ";
$sql_ult_rep .= "WHERE r.status = 1 AND r.entity IN (" . getEntity('taller_reparacion') . ") ";
$sql_ult_rep .= "ORDER BY r.fecha DESC, r.rowid DESC LIMIT 10";
$res_ult_rep = $db->query($sql_ult_rep);
if ($res_ult_rep) {
	while ($ur = $db->fetch_object($res_ult_rep)) {
		$ultimas_reparaciones[] = $ur;
	}
}

// 8. Consultar últimos vehículos con JOIN de cliente
$vehiculos_recientes = array();
$sql_rec_veh = "SELECT v.rowid, v.matricula, v.marca, v.modelo, v.anio, s.nom as cliente_nombre ";
$sql_rec_veh .= "FROM " . MAIN_DB_PREFIX . "taller_vehiculo as v ";
$sql_rec_veh .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON v.fk_soc = s.rowid ";
$sql_rec_veh .= "WHERE v.status = 1 AND v.entity IN (" . getEntity('taller_vehiculo') . ") ";
$sql_rec_veh .= "ORDER BY v.rowid DESC LIMIT 10";
$res_rec_veh = $db->query($sql_rec_veh);
if ($res_rec_veh) {
	while ($rv = $db->fetch_object($res_rec_veh)) {
		$vehiculos_recientes[] = $rv;
	}
}

$css_file = __DIR__ . '/css/tallerpro.css';
$js_file = __DIR__ . '/js/tallerpro.js';
$css_version = file_exists($css_file) ? filemtime($css_file) : dol_now();
$js_version = file_exists($js_file) ? filemtime($js_file) : dol_now();

$css_url = dol_buildpath('/taller/css/tallerpro.css', 1) . '?v=' . $css_version;
$js_url = dol_buildpath('/taller/js/tallerpro.js', 1) . '?v=' . $js_version;

$morehead = '<link rel="stylesheet" type="text/css" href="'.$css_url.'">'."\n";
$morehead .= '<script src="'.$js_url.'"></script>'."\n";

// Page Header
llxHeader('', 'TallerPro', '', '', 0, 0, array(), array(), $morehead, 'mod-taller page-tallerpro');

$dolibarr_root_url = DOL_URL_ROOT . '/index.php';
$setup_url = dol_buildpath('/taller/admin/setup.php', 1);
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
			<a href="<?php echo dol_buildpath('/taller/tallerindex.php', 1); ?>" class="tallerpro-brand">
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
						<a href="<?php echo $index_url; ?>" class="tallerpro-nav-item active">
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
									<rect x="2" y="4" width="20" height="16" rx="2"></rect>
									<line x1="12" y1="8" x2="12" y2="16"></line>
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
				<span class="tallerpro-topbar-sidebar-toggle" id="tallerTopbarToggle" title="Alternar menú">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
						<line x1="9" y1="3" x2="9" y2="21"></line>
					</svg>
				</span>
				<span class="tallerpro-topbar-dot">•</span>
				<span class="tallerpro-topbar-title">TallerPro</span>
			</div>
			<div class="tallerpro-topbar-right">
				<a href="<?php echo $dolibarr_root_url; ?>" class="tallerpro-back-dolibarr" title="Volver al menú principal de Dolibarr">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="19" y1="12" x2="5" y2="12"></line>
						<polyline points="12 19 5 12 12 5"></polyline>
					</svg>
					<?php echo $langs->trans("VolverADolibarr"); ?>
				</a>
			</div>
		</header>

		<!-- Dashboard Content -->
		<div class="tallerpro-content-wrapper">
			<main class="tallerpro-content">
				<!-- Page Header -->
			<div class="tallerpro-content-header">
				<h1 class="tallerpro-page-title"><?php echo $langs->trans("Inicio"); ?></h1>
				<p class="tallerpro-page-subtitle"><?php echo $langs->trans("SearchSubtitle"); ?></p>
			</div>

			<!-- Search Bar -->
			<div class="tallerpro-search-container">
				<div class="tallerpro-search-card">
					<span class="tallerpro-search-icon">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="11" cy="11" r="8"></circle>
							<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
						</svg>
					</span>
					<input type="text" id="tallerGlobalSearchInput" class="tallerpro-search-input" placeholder="<?php echo $langs->trans("SearchPlaceholder"); ?>" autocomplete="off" />
				</div>
				<div class="tallerpro-search-dropdown" id="tallerSearchDropdown"></div>
			</div>

			<!-- Metric KPI Cards (3 columns x 2 rows) -->
			<div class="tallerpro-kpi-grid">
				<!-- Card 1: Clientes -->
				<a href="<?php echo $clientes_url; ?>" class="tallerpro-kpi-card">
					<div class="tallerpro-kpi-info">
						<div class="tallerpro-kpi-value"><?php echo $nb_clientes; ?></div>
						<div class="tallerpro-kpi-label"><?php echo $langs->trans("Clientes"); ?></div>
					</div>
					<div class="tallerpro-kpi-icon tallerpro-icon-clientes">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
							<circle cx="9" cy="7" r="4"></circle>
							<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
							<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
						</svg>
					</div>
				</a>

				<!-- Card 2: Vehículos -->
				<a href="<?php echo $vehiculos_url; ?>" class="tallerpro-kpi-card">
					<div class="tallerpro-kpi-info">
						<div class="tallerpro-kpi-value"><?php echo $nb_vehiculos; ?></div>
						<div class="tallerpro-kpi-label"><?php echo $langs->trans("Vehiculos"); ?></div>
					</div>
					<div class="tallerpro-kpi-icon tallerpro-icon-vehiculos">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 3.1C2 11.4 2 11.7 2 12v4c0 .6.4 1 1 1h2"></path>
							<circle cx="7" cy="17" r="2"></circle>
							<path d="M9 17h6"></path>
							<circle cx="17" cy="17" r="2"></circle>
						</svg>
					</div>
				</a>

				<!-- Card 3: Reparaciones -->
				<a href="<?php echo $reparaciones_url; ?>" class="tallerpro-kpi-card">
					<div class="tallerpro-kpi-info">
						<div class="tallerpro-kpi-value"><?php echo $nb_reparaciones; ?></div>
						<div class="tallerpro-kpi-label"><?php echo $langs->trans("Reparaciones"); ?></div>
					</div>
					<div class="tallerpro-kpi-icon tallerpro-icon-reparaciones">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
						</svg>
					</div>
				</a>

				<!-- Card 4: Materiales -->
				<a href="<?php echo $materiales_url; ?>" class="tallerpro-kpi-card">
					<div class="tallerpro-kpi-info">
						<div class="tallerpro-kpi-value"><?php echo $nb_materiales; ?></div>
						<div class="tallerpro-kpi-label"><?php echo $langs->trans("Materiales"); ?></div>
					</div>
					<div class="tallerpro-kpi-icon tallerpro-icon-materiales">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
							<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
							<line x1="12" y1="22.08" x2="12" y2="12"></line>
						</svg>
					</div>
				</a>

				<!-- Card 5: Seguros -->
				<a href="<?php echo $seguros_url; ?>" class="tallerpro-kpi-card">
					<div class="tallerpro-kpi-info">
						<div class="tallerpro-kpi-label" style="font-size: 13.5px;"><?php echo $langs->trans("Seguros"); ?></div>
					</div>
					<div class="tallerpro-kpi-icon tallerpro-icon-seguros">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
						</svg>
					</div>
				</a>

				<!-- Card 6: Facturas -->
				<a href="<?php echo $facturas_url; ?>" class="tallerpro-kpi-card">
					<div class="tallerpro-kpi-info">
						<div class="tallerpro-kpi-label" style="font-size: 13.5px;"><?php echo $langs->trans("Facturas"); ?></div>
					</div>
					<div class="tallerpro-kpi-icon tallerpro-icon-facturas">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<rect x="3" y="3" width="18" height="18" rx="2"></rect>
							<line x1="12" y1="7" x2="12" y2="17"></line>
							<path d="M9.5 9.5a1.5 1.5 0 0 1 1.5-1.5h2a1.5 1.5 0 0 1 1.5 1.5c0 1.5-3 1.5-3 3a1.5 1.5 0 0 0 1.5 1.5h2a1.5 1.5 0 0 0 1.5-1.5"></path>
						</svg>
					</div>
				</a>
			</div>

			<!-- Bottom Activity Cards (2 columns) -->
			<div class="tallerpro-activity-grid">
				<!-- Left Activity Card: Últimas reparaciones -->
				<div class="tallerpro-activity-card">
					<div class="tallerpro-activity-header">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
						</svg>
						<span><?php echo $langs->trans("UltimasReparaciones"); ?></span>
					</div>
					<?php if (empty($ultimas_reparaciones)) { ?>
						<div class="tallerpro-empty-state">
							<?php echo $langs->trans("SinReparaciones"); ?>
						</div>
					<?php } else { ?>
						<div class="tallerpro-activity-list">
							<?php foreach ($ultimas_reparaciones as $ur) { ?>
								<a href="<?php echo $reparaciones_url; ?>" class="tallerpro-activity-item" title="Ver reparaciones">
									<div class="tallerpro-activity-item-left">
										<div class="tallerpro-activity-item-title">
											<?php if (!empty($ur->matricula)) { ?>
												<span class="tallerpro-activity-badge"><?php echo dol_escape_htmltag($ur->matricula); ?></span>
											<?php } ?>
											<span><?php echo dol_escape_htmltag($ur->descripcion ?: 'Reparación'); ?></span>
										</div>
										<div class="tallerpro-activity-item-desc">
											<?php echo !empty($ur->fecha) ? dol_print_date($db->jdate($ur->fecha), 'day') : ''; ?>
											<?php if (!empty($ur->marca) || !empty($ur->modelo)) { ?>
												· <?php echo dol_escape_htmltag(trim($ur->marca . ' ' . $ur->modelo)); ?>
											<?php } ?>
										</div>
									</div>
									<div class="tallerpro-activity-item-right">
										<span class="tallerpro-activity-cost"><?php echo number_format((float) $ur->coste_mo, 2, ',', '.') . ' €'; ?></span>
									</div>
								</a>
							<?php } ?>
						</div>
					<?php } ?>
				</div>

				<!-- Right Activity Card: Vehículos recientes -->
				<div class="tallerpro-activity-card">
					<div class="tallerpro-activity-header">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 3.1C2 11.4 2 11.7 2 12v4c0 .6.4 1 1 1h2"></path>
							<circle cx="7" cy="17" r="2"></circle>
							<path d="M9 17h6"></path>
							<circle cx="17" cy="17" r="2"></circle>
						</svg>
						<span><?php echo $langs->trans("VehiculosRecientes"); ?></span>
					</div>
					<?php if (empty($vehiculos_recientes)) { ?>
						<div class="tallerpro-empty-state">
							<?php echo $langs->trans("SinVehiculos"); ?>
						</div>
					<?php } else { ?>
						<div class="tallerpro-activity-list">
							<?php foreach ($vehiculos_recientes as $vr) { ?>
								<a href="<?php echo $vehiculos_url . '?id=' . ((int) $vr->rowid); ?>" class="tallerpro-activity-item" title="Ver detalles del vehículo">
									<div class="tallerpro-activity-item-left">
										<div class="tallerpro-activity-item-title">
											<span class="tallerpro-activity-badge"><?php echo dol_escape_htmltag($vr->matricula); ?></span>
											<span><?php echo dol_escape_htmltag(trim($vr->marca . ' ' . $vr->modelo) ?: 'Vehículo'); ?></span>
										</div>
										<div class="tallerpro-activity-item-desc">
											<?php echo !empty($vr->cliente_nombre) ? dol_escape_htmltag($vr->cliente_nombre) : 'Sin propietario'; ?>
											<?php echo !empty($vr->anio) ? ' (' . dol_escape_htmltag($vr->anio) . ')' : ''; ?>
										</div>
									</div>
									<div class="tallerpro-activity-item-right">
										<span class="tallerpro-activity-arrow">
											<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
												<polyline points="9 18 15 12 9 6"></polyline>
											</svg>
										</span>
									</div>
								</a>
							<?php } ?>
						</div>
					<?php } ?>
				</div>
			</div>
		</main>
	</div>
</div>
</div>

<?php
// End of page
llxFooter();
$db->close();
