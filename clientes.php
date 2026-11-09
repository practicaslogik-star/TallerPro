<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       taller/clientes.php
 *  \ingroup    taller
 *  \brief      Clientes list view - TallerPro view
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
$langs->loadLangs(array("taller@taller", "companies"));

// Security check
if (!isModEnabled('taller')) {
	accessforbidden('Module not enabled');
}

$societe_enabled = isModEnabled('societe');
if (!$societe_enabled && !empty($action) && in_array($action, array('create_client', 'add_client', 'update_client', 'edit_client', 'delete_client', 'delete'))) {
	setEventMessages("El módulo de Terceros de Dolibarr está desactivado. Actívelo en Configuración -> Módulos para gestionar clientes.", null, 'errors');
	header("Location: " . $_SERVER['PHP_SELF']);
	exit;
}

$clientes_url = dol_buildpath('/taller/clientes.php', 1);

require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

$action = GETPOST('action', 'aZ09');

// Acción: Crear Cliente en módulo Terceros de Dolibarr
if ($action == 'create_client' || $action == 'add_client') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		accessforbidden('Method not allowed');
	}
	$token = GETPOST('token', 'alpha');
	$valid_token = (!empty($_SESSION['token']) && $token === $_SESSION['token']) || (!empty($_SESSION['newtoken']) && $token === $_SESSION['newtoken']);
	if (!$valid_token) {
		accessforbidden('Invalid security token');
	}

	$name    = trim(GETPOST('name', 'alphanohtml'));
	$phone   = trim(GETPOST('phone', 'alphanohtml'));
	$email   = trim(GETPOST('email', 'custom', 0, FILTER_SANITIZE_EMAIL));
	$cif     = trim(GETPOST('cif', 'alphanohtml'));
	$address = trim(GETPOST('address', 'alphanohtml'));
	$notes   = trim(GETPOST('notes', 'restricthtml'));

	if (empty($name)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Name")), null, 'errors');
	} else {
		global $mysoc;
		$soc = new Societe($db);
		$soc->name         = $name;
		$soc->nom          = $name;
		$soc->client       = 1; // 1 = Cliente
		$soc->fournisseur  = 0;
		$soc->phone        = $phone;
		$soc->email        = $email;
		$soc->idprof1      = $cif;
		$soc->address      = $address;
		$soc->note_private = $notes;

		if (!empty($mysoc->country_id)) {
			$soc->country_id = $mysoc->country_id;
		} elseif (getDolGlobalInt('MAIN_INFO_SOCIETE_COUNTRY')) {
			$soc->country_id = getDolGlobalInt('MAIN_INFO_SOCIETE_COUNTRY');
		}

		$result = $soc->create($user);

		if ($result > 0) {
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF']);
			exit;
		} else {
			$errmsg = !empty($soc->error) ? $soc->error : (!empty($soc->errors) ? implode(', ', $soc->errors) : 'Error al crear el cliente');
			setEventMessages($errmsg, $soc->errors, 'errors');
		}
	}
}

// Acción: Actualizar Cliente en módulo Terceros de Dolibarr
if ($action == 'update_client' || $action == 'edit_client') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		accessforbidden('Method not allowed');
	}
	$token = GETPOST('token', 'alpha');
	$valid_token = (!empty($_SESSION['token']) && $token === $_SESSION['token']) || (!empty($_SESSION['newtoken']) && $token === $_SESSION['newtoken']);
	if (!$valid_token) {
		accessforbidden('Invalid security token');
	}

	$socid = GETPOSTINT('socid');
	if (empty($socid)) {
		$socid = GETPOSTINT('rowid');
	}

	$name    = trim(GETPOST('name', 'alphanohtml'));
	$phone   = trim(GETPOST('phone', 'alphanohtml'));
	$email   = trim(GETPOST('email', 'custom', 0, FILTER_SANITIZE_EMAIL));
	$cif     = trim(GETPOST('cif', 'alphanohtml'));
	$address = trim(GETPOST('address', 'alphanohtml'));
	$notes   = trim(GETPOST('notes', 'restricthtml'));

	if ($socid <= 0) {
		setEventMessages("ID de cliente inválido", null, 'errors');
	} elseif (empty($name)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Name")), null, 'errors');
	} else {
		$soc = new Societe($db);
		$res = $soc->fetch($socid);
		if ($res > 0) {
			$soc->name         = $name;
			$soc->nom          = $name;
			$soc->phone        = $phone;
			$soc->email        = $email;
			$soc->idprof1      = $cif;
			$soc->siren        = $cif;
			$soc->address      = $address;
			$soc->note_private = $notes;

			$result = $soc->update($socid, $user);
			if ($result >= 0) {
				setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
				$stay_detail = GETPOSTINT('stay_detail');
				header("Location: " . $_SERVER['PHP_SELF'] . ($stay_detail ? '?id=' . $socid : ''));
				exit;
			} else {
				$errmsg = !empty($soc->error) ? $soc->error : (!empty($soc->errors) ? implode(', ', $soc->errors) : 'Error al actualizar el cliente');
				setEventMessages($errmsg, $soc->errors, 'errors');
			}
		} else {
			setEventMessages("Cliente no encontrado", null, 'errors');
		}
	}
}

// Acción: Eliminar Cliente (Seguro vía POST con token)
if ($action == 'delete_client' || $action == 'delete') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		accessforbidden('Method not allowed');
	}
	$token = GETPOST('token', 'alpha');
	$valid_token = (!empty($_SESSION['token']) && $token === $_SESSION['token']) || (!empty($_SESSION['newtoken']) && $token === $_SESSION['newtoken']);
	if (!$valid_token) {
		accessforbidden('Invalid security token');
	}

	$socid = GETPOSTINT('socid');
	if (empty($socid)) {
		$socid = GETPOSTINT('rowid');
	}
	if ($socid > 0) {
		$soc = new Societe($db);
		$res = $soc->fetch($socid);
		if ($res > 0) {
			$result = $soc->delete($socid, $user);
			if ($result > 0) {
				setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
				header("Location: " . $_SERVER['PHP_SELF']);
				exit;
			} else {
				dol_syslog("TallerPro - Error al eliminar cliente ID " . $socid . ": " . $soc->error, LOG_ERR);
				setEventMessages("No se pudo eliminar el cliente seleccionado", null, 'errors');
			}
		}
	}
}



$socid = GETPOSTINT('socid');
if (empty($socid)) {
	$socid = GETPOSTINT('id');
}

$client_detail = null;
$client_vehicles = array();
$client_invoices = array();

if ($socid > 0) {
	$soc = new Societe($db);
	$res = $soc->fetch($socid);
	if ($res > 0) {
		$client_detail = $soc;

		// Conteo y lista de vehículos del cliente
		$sql_v = "SELECT v.rowid, v.matricula, v.marca, v.modelo, v.anio, v.color, v.km ";
		$sql_v .= "FROM " . MAIN_DB_PREFIX . "taller_vehiculo as v ";
		$sql_v .= "WHERE v.fk_soc = " . ((int) $socid) . " AND v.status = 1 ";
		$sql_v .= "ORDER BY v.rowid DESC";
		$res_v = $db->query($sql_v);
		if ($res_v) {
			while ($v_obj = $db->fetch_object($res_v)) {
				$client_vehicles[] = $v_obj;
			}
		}

		// Conteo y lista de facturas del cliente
		$sql_f = "SELECT f.rowid, f.ref, f.total_ttc, f.datef, f.paye, f.fk_statut ";
		$sql_f .= "FROM " . MAIN_DB_PREFIX . "facture as f ";
		$sql_f .= "WHERE f.fk_soc = " . ((int) $socid) . " ";
		$sql_f .= "ORDER BY f.datef DESC";
		$res_f = $db->query($sql_f);
		if ($res_f) {
			while ($f_obj = $db->fetch_object($res_f)) {
				$client_invoices[] = $f_obj;
			}
		}
	}
}

// Consultar clientes reales de la base de datos (llx_societe) con conteo de vehículos
$sql = "SELECT s.rowid, s.nom as name, s.phone, s.email, s.address, s.siren as cif, s.note_private as notes, ";
$sql .= "COUNT(v.rowid) as nb_vehiculos ";
$sql .= " FROM " . MAIN_DB_PREFIX . "societe as s";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "taller_vehiculo as v ON (v.fk_soc = s.rowid AND v.status = 1)";
$sql .= " WHERE s.client IN (1, 2, 3)";
$sql .= " AND s.entity IN (" . getEntity('societe') . ")";
$sql .= " GROUP BY s.rowid, s.nom, s.phone, s.email, s.address, s.siren, s.note_private";
$sql .= " ORDER BY s.nom ASC";

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num_rows = $resql ? $db->num_rows($resql) : 0;

$css_file = __DIR__ . '/css/tallerpro.css';
$js_file = __DIR__ . '/js/tallerpro.js';
$css_version = (file_exists($css_file) ? filemtime($css_file) : dol_now()) . '_' . dol_now();
$js_version = (file_exists($js_file) ? filemtime($js_file) : dol_now()) . '_' . dol_now();

$css_url = dol_buildpath('/taller/css/tallerpro.css', 1) . '?v=' . $css_version;
$js_url = dol_buildpath('/taller/js/tallerpro.js', 1) . '?v=' . $js_version;

$morehead = '<link rel="stylesheet" type="text/css" href="'.$css_url.'">'."\n";
$morehead .= '<script src="'.$js_url.'"></script>'."\n";

// Page Header
llxHeader($morehead, 'Clientes - TallerPro', '', '', 0, 0, array(), array(), '', 'mod-taller page-tallerpro');


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
						<a href="<?php echo $clientes_url; ?>" class="tallerpro-nav-item active">
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
			<div class="tallerpro-topbar-brand">
				<span class="tallerpro-topbar-dot">•</span>
				<div class="tallerpro-topbar-breadcrumb">
					<a href="<?php echo $index_url; ?>" class="tallerpro-topbar-breadcrumb-parent">TallerPro</a>
					<span class="tallerpro-topbar-breadcrumb-sep">/</span>
					<?php if ($client_detail): ?>
						<a href="<?php echo $clientes_url; ?>" class="tallerpro-topbar-breadcrumb-parent"><?php echo $langs->trans("Clientes"); ?></a>
						<span class="tallerpro-topbar-breadcrumb-sep">/</span>
						<span class="tallerpro-topbar-breadcrumb-current"><?php echo dol_escape_htmltag($client_detail->nom); ?></span>
					<?php else: ?>
						<span class="tallerpro-topbar-breadcrumb-current"><?php echo $langs->trans("Clientes"); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<div class="tallerpro-topbar-right"></div>
		</header>

		<!-- Dashboard Content -->
		<div class="tallerpro-content-wrapper">
			<main class="tallerpro-content">
				<?php
				// Mostrar alertas o mensajes de Dolibarr
				echo dol_htmloutput_events();
				?>

				<?php if (!$societe_enabled): ?>
					<div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 18px 22px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
						<div style="display: flex; align-items: flex-start; gap: 14px;">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;">
								<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
								<line x1="12" y1="9" x2="12" y2="13"></line>
								<line x1="12" y1="17" x2="12.01" y2="17"></line>
							</svg>
							<div>
								<h3 style="margin: 0 0 4px 0; font-size: 14.5px; font-weight: 600; color: #92400e;">Módulo nativo de Terceros de Dolibarr desactivado</h3>
								<p style="margin: 0 0 12px 0; font-size: 13px; color: #b45309; line-height: 1.5;">
									TallerPro requiere el módulo oficial <strong>Terceros</strong> para gestionar fichas de clientes.
								</p>
								<a href="<?php echo DOL_URL_ROOT; ?>/admin/modules.php?mainmenu=home" class="tallerpro-btn-primary" style="display: inline-flex; text-decoration: none; padding: 7px 14px; font-size: 12.5px;">
									Activar módulo Terceros en Dolibarr
								</a>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ($client_detail): ?>
					<?php
					$cif = !empty($client_detail->siren) ? $client_detail->siren : (!empty($client_detail->idprof1) ? $client_detail->idprof1 : '—');
					
					$addr_parts = array();
					if (!empty($client_detail->address)) $addr_parts[] = $client_detail->address;
					$zip_town = trim($client_detail->zip . ' ' . $client_detail->town);
					if (!empty($zip_town)) $addr_parts[] = $zip_town;
					$address = !empty($addr_parts) ? implode(' - ', $addr_parts) : '—';

					$contact_parts = array();
					if (!empty($client_detail->phone)) $contact_parts[] = $client_detail->phone;
					if (!empty($client_detail->email)) $contact_parts[] = $client_detail->email;
					$contact_str = !empty($contact_parts) ? implode(' · ', $contact_parts) : 'Sin contacto';
					?>


					<div class="tallerpro-detail-container">
						<!-- Breadcrumb / Volver a clientes -->
						<a href="<?php echo $clientes_url; ?>" class="tallerpro-back-to-list">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<line x1="19" y1="12" x2="5" y2="12"></line>
								<polyline points="12 19 5 12 12 5"></polyline>
							</svg>
							<span>Volver a clientes</span>
						</a>

						<!-- Tarjeta 1: Información del Cliente -->
						<div class="tallerpro-client-card">
							<div class="tallerpro-client-main-info" style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
								<div>
									<h1 class="tallerpro-client-name"><?php echo dol_escape_htmltag($client_detail->nom); ?></h1>
									<p class="tallerpro-client-contact"><?php echo dol_escape_htmltag($contact_str); ?></p>
								</div>
								<button type="button" class="tallerpro-action-btn btn-edit-client"
									title="Editar cliente"
									data-id="<?php echo (int) $client_detail->id; ?>"
									data-name="<?php echo dol_escape_htmltag($client_detail->nom); ?>"
									data-phone="<?php echo dol_escape_htmltag($client_detail->phone ?? ''); ?>"
									data-email="<?php echo dol_escape_htmltag($client_detail->email ?? ''); ?>"
									data-cif="<?php echo dol_escape_htmltag(!empty($client_detail->idprof1) ? $client_detail->idprof1 : $client_detail->siren); ?>"
									data-address="<?php echo dol_escape_htmltag($client_detail->address ?? ''); ?>"
									data-notes="<?php echo dol_escape_htmltag($client_detail->note_private ?? ''); ?>">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
										<path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
									</svg>
								</button>
							</div>
							<div class="tallerpro-client-meta-group">
								<div class="tallerpro-client-meta-item">
									<span class="tallerpro-client-meta-label">DNI / CIF</span>
									<span class="tallerpro-client-meta-value"><?php echo dol_escape_htmltag($cif); ?></span>
								</div>
								<div class="tallerpro-client-meta-item">
									<span class="tallerpro-client-meta-label">DIRECCIÓN</span>
									<span class="tallerpro-client-meta-value"><?php echo dol_escape_htmltag($address); ?></span>
								</div>
							</div>
						</div>

						<!-- Tarjeta 2: Vehículos del cliente -->
						<div class="tallerpro-detail-section-card">
							<div class="tallerpro-detail-section-header">
								<div class="tallerpro-detail-section-title">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 3.1C2 11.4 2 11.7 2 12v4c0 .6.4 1 1 1h2"></path>
										<circle cx="7" cy="17" r="2"></circle>
										<path d="M9 17h6"></path>
										<circle cx="17" cy="17" r="2"></circle>
									</svg>
									<span>Vehículos del cliente</span>
								</div>
								<span class="tallerpro-badge-pill"><?php echo count($client_vehicles); ?></span>
							</div>
							<div class="tallerpro-detail-list">
								<?php if (!empty($client_vehicles)): ?>
									<?php foreach ($client_vehicles as $v): ?>
										<?php
										$sub_desc = array_filter(array($v->marca, $v->modelo));
										$sub_str = !empty($sub_desc) ? implode(' · ', $sub_desc) : 'Sin marca / modelo';
										?>
										<div class="tallerpro-detail-item">
											<div class="tallerpro-detail-item-main">
												<span class="tallerpro-detail-item-title"><?php echo dol_escape_htmltag($v->matricula); ?></span>
												<span class="tallerpro-detail-item-sub"><?php echo dol_escape_htmltag($sub_str); ?></span>
											</div>
											<a href="<?php echo $vehiculos_url . '?id=' . $v->rowid; ?>" class="tallerpro-detail-item-link" title="Ver vehículo">
												<span>Ver vehículo</span>
												<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
													<polyline points="15 3 21 3 21 9"></polyline>
													<line x1="10" y1="14" x2="21" y2="3"></line>
												</svg>
											</a>
										</div>
									<?php endforeach; ?>
								<?php else: ?>
									<div class="tallerpro-empty-message">Este cliente aún no tiene vehículos registrados.</div>
								<?php endif; ?>
							</div>
						</div>

						<!-- Tarjeta 3: Facturas del cliente -->
						<div class="tallerpro-detail-section-card">
							<div class="tallerpro-detail-section-header">
								<div class="tallerpro-detail-section-title">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
										<polyline points="14 2 14 8 20 8"></polyline>
										<line x1="16" y1="13" x2="8" y2="13"></line>
										<line x1="16" y1="17" x2="8" y2="17"></line>
										<line x1="10" y1="9" x2="8" y2="9"></line>
									</svg>
									<span>Facturas del cliente</span>
								</div>
								<span class="tallerpro-badge-pill"><?php echo count($client_invoices); ?></span>
							</div>
							<div class="tallerpro-detail-list">
								<?php if (!empty($client_invoices)): ?>
									<?php foreach ($client_invoices as $f): ?>
										<?php
										$is_paid = !empty($f->paye);
										$status_label = $is_paid ? 'Pagada' : 'Pendiente';
										$status_class = $is_paid ? 'pagada' : 'pendiente';
										$invoice_title = (stripos($f->ref, 'factura') === false) ? ('Factura ' . $f->ref) : $f->ref;
										?>
										<div class="tallerpro-detail-item">
											<div class="tallerpro-detail-item-main">
												<span class="tallerpro-detail-item-title"><?php echo dol_escape_htmltag($invoice_title); ?></span>
												<span class="tallerpro-detail-item-sub"><?php echo dol_print_date($db->jdate($f->datef), 'day'); ?></span>
											</div>
											<div class="tallerpro-detail-item-end">
												<span class="tallerpro-detail-item-price"><?php echo price($f->total_ttc, 0, $langs, 1, -1, -1, $conf->currency); ?></span>
												<span class="tallerpro-badge-status-<?php echo $status_class; ?>"><?php echo $status_label; ?></span>
											</div>
										</div>
									<?php endforeach; ?>
								<?php else: ?>
									<div class="tallerpro-empty-message">Este cliente aún no tiene facturas registradas.</div>
								<?php endif; ?>
							</div>
						</div>
					</div>

				<?php else: ?>

					<!-- Header Actions & Title -->
					<div class="tallerpro-header-actions">
						<div class="tallerpro-content-header" style="margin-bottom: 0;">
							<h1 class="tallerpro-page-title"><?php echo $langs->trans("Clientes"); ?></h1>
							<p class="tallerpro-page-subtitle"><?php echo $num_rows; ?> registro(s)</p>
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
									<th>Teléfono</th>
									<th>Email</th>
									<th>Vehículos</th>
									<th style="width: 100px; text-align: right;"></th>
								</tr>
							</thead>
							<tbody>
								<?php if ($num_rows > 0 && $resql): ?>
									<?php while ($obj = $db->fetch_object($resql)): ?>
										<?php
										$view_url = $_SERVER['PHP_SELF'] . '?id=' . $obj->rowid;
										$edit_url = DOL_URL_ROOT . '/societe/card.php?action=edit&socid=' . $obj->rowid;
										?>
										<tr>
											<td style="font-weight: 500; color: #0f172a;">
												<a href="<?php echo $view_url; ?>" style="font-weight: 500; color: #0f172a; text-decoration: none;" title="Ver detalles del cliente"><?php echo dol_escape_htmltag($obj->name); ?></a>
											</td>
											<td><?php echo !empty($obj->phone) ? dol_escape_htmltag($obj->phone) : '<span style="color: #94a3b8;">—</span>'; ?></td>
											<td><?php echo !empty($obj->email) ? dol_escape_htmltag($obj->email) : '<span style="color: #94a3b8;">—</span>'; ?></td>
											<td><span class="tallerpro-badge-pill"><?php echo (int) $obj->nb_vehiculos; ?></span></td>
											<td>
												<div class="tallerpro-actions">
													<a href="<?php echo $view_url; ?>" class="tallerpro-action-btn" title="Ver detalles del cliente">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
															<polyline points="15 3 21 3 21 9"></polyline>
															<line x1="10" y1="14" x2="21" y2="3"></line>
														</svg>
													</a>
													<button type="button" class="tallerpro-action-btn btn-edit-client"
														title="Editar cliente"
														data-id="<?php echo (int) $obj->rowid; ?>"
														data-name="<?php echo dol_escape_htmltag($obj->name); ?>"
														data-phone="<?php echo dol_escape_htmltag($obj->phone ?? ''); ?>"
														data-email="<?php echo dol_escape_htmltag($obj->email ?? ''); ?>"
														data-cif="<?php echo dol_escape_htmltag($obj->cif ?? ''); ?>"
														data-address="<?php echo dol_escape_htmltag($obj->address ?? ''); ?>"
														data-notes="<?php echo dol_escape_htmltag($obj->notes ?? ''); ?>">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
															<path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
														</svg>
													</button>
													<button type="button"
														class="tallerpro-action-btn btn-delete-action"
														title="Eliminar"
														data-id="<?php echo (int) $obj->rowid; ?>"
														data-action="delete_client">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<polyline points="3 6 5 6 21 6"></polyline>
															<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
														</svg>
													</button>
												</div>
											</td>
										</tr>
									<?php endwhile; ?>
								<?php else: ?>
									<tr>
										<td colspan="5" style="text-align: center; color: #94a3b8; padding: 40px 20px;">
											Aún no hay clientes registrados. Pulsa en <strong>Añadir</strong> para crear el primero.
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>

				<?php endif; ?>
			</main>
		</div>
	</div>

	<!-- Modal Nuevo Registro / Edición Clientes -->
	<div class="tallerpro-modal-backdrop" id="tallerClientModal">
		<div class="tallerpro-modal-card" style="max-width: 520px;">
			<div class="tallerpro-modal-header">
				<h2 class="tallerpro-modal-title" id="tallerClientModalTitle">Nuevo registro de cliente</h2>
				<button type="button" class="tallerpro-modal-close" id="tallerCloseModalBtn" title="Cerrar">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			<form class="tallerpro-modal-form" id="tallerClientForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
				<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
				<input type="hidden" name="action" id="clientFormAction" value="create_client">
				<input type="hidden" name="socid" id="clientFormSocid" value="0">
				<input type="hidden" name="stay_detail" id="clientFormStayDetail" value="<?php echo $client_detail ? '1' : '0'; ?>">

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Nombre / Razón Social <span class="tallerpro-required">*</span></label>
					<input type="text" name="name" id="client_name" class="tallerpro-form-input" placeholder="Ej: Talleres Mecánicos SL o Juan Pérez" required autocomplete="off" />
				</div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Teléfono</label>
						<input type="text" name="phone" id="client_phone" class="tallerpro-form-input" placeholder="Ej: 600123456" />
					</div>
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">DNI / CIF</label>
						<input type="text" name="cif" id="client_cif" class="tallerpro-form-input" placeholder="Ej: B12345678" />
					</div>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Email</label>
					<input type="email" name="email" id="client_email" class="tallerpro-form-input" placeholder="ejemplo@correo.com" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Dirección</label>
					<input type="text" name="address" id="client_address" class="tallerpro-form-input" placeholder="Calle, número, ciudad..." />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Notas</label>
					<textarea name="notes" id="client_notes" class="tallerpro-form-textarea" rows="3" placeholder="Información adicional del cliente..."></textarea>
				</div>
				<div class="tallerpro-modal-footer">
					<button type="button" class="tallerpro-btn-cancel" id="tallerCancelModalBtn">Cancelar</button>
					<button type="submit" class="tallerpro-btn-save" id="btnGuardarClienteNuevo">Guardar</button>
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
			<p style="font-size: 13.5px; color: #64748b; margin: 0 0 24px 0; line-height: 1.5;">Este cliente dejará de mostrarse en la lista activa, pero sus datos se conservarán en el sistema.</p>

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

	<!-- Formulario oculto para borrado seguro POST con CSRF Token -->
	<form id="tallerGlobalDeleteForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" style="display: none;">
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
