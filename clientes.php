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

$clientes_url = dol_buildpath('/taller/clientes.php', 1);

require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

$action = GETPOST('action', 'alpha');
if (empty($action) && !empty($_POST['action'])) {
	$action = $_POST['action'];
}

// Acción: Crear Cliente en módulo Terceros de Dolibarr
if ($action == 'create_client' || $action == 'add_client') {
	$name    = trim(GETPOST('name', 'alphanohtml'));
	if (empty($name) && !empty($_POST['name'])) {
		$name = trim($_POST['name']);
	}
	$phone   = trim(GETPOST('phone', 'alpha'));
	if (empty($phone) && !empty($_POST['phone'])) {
		$phone = trim($_POST['phone']);
	}
	$email   = trim(GETPOST('email', 'custom', 0, FILTER_SANITIZE_EMAIL));
	if (empty($email) && !empty($_POST['email'])) {
		$email = trim($_POST['email']);
	}
	$cif     = trim(GETPOST('cif', 'alphanohtml'));
	if (empty($cif) && !empty($_POST['cif'])) {
		$cif = trim($_POST['cif']);
	}
	$address = trim(GETPOST('address', 'alphanohtml'));
	if (empty($address) && !empty($_POST['address'])) {
		$address = trim($_POST['address']);
	}
	$notes   = trim(GETPOST('notes', 'restricthtml'));
	if (empty($notes) && !empty($_POST['notes'])) {
		$notes = trim($_POST['notes']);
	}

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

// Acción: Eliminar Cliente
if ($action == 'delete_client' || $action == 'delete') {
	$socid = GETPOST('socid', 'int');
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
				$errmsg = !empty($soc->error) ? $soc->error : (!empty($soc->errors) ? implode(', ', $soc->errors) : 'Error al eliminar el cliente');
				setEventMessages($errmsg, $soc->errors, 'errors');
			}
		}
	}
}

// Ensure vehicle table exists
$sql_veh_create = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "taller_vehiculo (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    fk_soc          INT NOT NULL,
    matricula       VARCHAR(32) NOT NULL,
    marca           VARCHAR(64) DEFAULT NULL,
    modelo          VARCHAR(64) DEFAULT NULL,
    anio            VARCHAR(10) DEFAULT NULL,
    color           VARCHAR(32) DEFAULT NULL,
    vin             VARCHAR(64) DEFAULT NULL,
    km              INT DEFAULT 0,
    notas           TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat   INT NOT NULL,
    entity          INT DEFAULT 1,
    status          SMALLINT DEFAULT 1,
    INDEX idx_taller_vehiculo_fk_soc (fk_soc),
    INDEX idx_taller_vehiculo_matricula (matricula)
) ENGINE=innodb;";
$db->query($sql_veh_create);

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
$sql = "SELECT s.rowid, s.nom as name, s.phone, s.email, s.address, s.siren as cif, ";
$sql .= "COUNT(v.rowid) as nb_vehiculos ";
$sql .= " FROM " . MAIN_DB_PREFIX . "societe as s";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "taller_vehiculo as v ON (v.fk_soc = s.rowid AND v.status = 1)";
$sql .= " WHERE s.client IN (1, 2, 3)";
$sql .= " AND s.entity IN (" . getEntity('societe') . ")";
$sql .= " GROUP BY s.rowid, s.nom, s.phone, s.email, s.address, s.siren";
$sql .= " ORDER BY s.nom ASC";

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num_rows = $resql ? $db->num_rows($resql) : 0;

$css_file = __DIR__ . '/css/tallerpro.css';
$js_file = __DIR__ . '/js/tallerpro.js';
$css_version = (file_exists($css_file) ? filemtime($css_file) : dol_now());
$js_version = (file_exists($js_file) ? filemtime($js_file) : dol_now()) . '_' . dol_now();

$css_url = dol_buildpath('/taller/css/tallerpro.css', 1) . '?v=' . $css_version;
$js_url = dol_buildpath('/taller/js/tallerpro.js', 1) . '?v=' . $js_version;

$morehead = '<link rel="stylesheet" type="text/css" href="'.$css_url.'">'."\n";
$morehead .= '<script src="'.$js_url.'"></script>'."\n";

// Page Header
llxHeader('', 'Clientes - TallerPro', '', '', 0, 0, array(), array(), $morehead, 'mod-taller page-tallerpro');

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
			<div class="tallerpro-topbar-right"></div>
		</header>

		<!-- Dashboard Content -->
		<div class="tallerpro-content-wrapper">
			<main class="tallerpro-content">
				<?php
				// Mostrar alertas o mensajes de Dolibarr
				echo dol_htmloutput_events();
				?>

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
						<div class="tallerpro-client-main-info">
							<h1 class="tallerpro-client-name"><?php echo dol_escape_htmltag($client_detail->nom); ?></h1>
							<p class="tallerpro-client-contact"><?php echo dol_escape_htmltag($contact_str); ?></p>
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
										<a href="<?php echo $vehiculos_url; ?>" class="tallerpro-detail-item-link" title="Ir a vehículos">
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
									<rect x="3" y="3" width="18" height="18" rx="2"></rect>
									<line x1="12" y1="7" x2="12" y2="17"></line>
									<path d="M9.5 9.5a1.5 1.5 0 0 1 1.5-1.5h2a1.5 1.5 0 0 1 1.5 1.5c0 1.5-3 1.5-3 3a1.5 1.5 0 0 0 1.5 1.5h2a1.5 1.5 0 0 0 1.5-1.5"></path>
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
									?>
									<div class="tallerpro-detail-item">
										<div class="tallerpro-detail-item-main">
											<span class="tallerpro-detail-item-title"><?php echo dol_escape_htmltag($f->ref); ?></span>
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
										$del_url  = $_SERVER['PHP_SELF'] . '?action=delete_client&socid=' . $obj->rowid . '&token=' . (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken()));
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
													<a href="<?php echo $edit_url; ?>" class="tallerpro-action-btn" title="Editar en Dolibarr">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
															<path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
														</svg>
													</a>
													<a href="<?php echo $del_url; ?>" class="tallerpro-action-btn" title="Eliminar" onclick="return confirm('¿Estás seguro de que deseas eliminar este cliente?');">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<polyline points="3 6 5 6 21 6"></polyline>
															<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
														</svg>
													</a>
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

	<!-- Modal Nuevo Registro Clientes -->
	<div class="tallerpro-modal-backdrop" id="tallerClientModal">
		<div class="tallerpro-modal-card">
			<div class="tallerpro-modal-header">
				<h2 class="tallerpro-modal-title">Nuevo registro de cliente</h2>
				<button type="button" class="tallerpro-modal-close" id="tallerCloseModalBtn" title="Cerrar">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			<form class="tallerpro-modal-form" id="tallerClientForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
				<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
				<input type="hidden" name="action" value="create_client">

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Nombre / Razón Social <span class="tallerpro-required">*</span></label>
					<input type="text" name="name" id="client_name" class="tallerpro-form-input" placeholder="Ej: Talleres Mecánicos SL o Juan Pérez" required autocomplete="off" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Teléfono</label>
					<input type="text" name="phone" id="client_phone" class="tallerpro-form-input" placeholder="Ej: 600123456" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Email</label>
					<input type="email" name="email" id="client_email" class="tallerpro-form-input" placeholder="ejemplo@correo.com" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">DNI / CIF</label>
					<input type="text" name="cif" id="client_cif" class="tallerpro-form-input" placeholder="Ej: B12345678" />
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
</div>

<?php
// End of page
llxFooter();
$db->close();
