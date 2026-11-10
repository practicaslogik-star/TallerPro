<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       taller/configuracion.php
 *  \ingroup    taller
 *  \brief      Vista de configuración y preferencias del taller - TallerPro
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

require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 * @var Societe $mysoc
 */

// Load translations
$langs->loadLangs(array("admin", "taller@taller"));

require_once __DIR__ . '/lib/taller.lib.php';

// Security check
tallerpro_require_permission('admin');

require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';

$action = GETPOST('action', 'aZ09');

// Guardar datos editados del taller
if ($action == 'update_settings') {
	tallerpro_require_post_csrf();
	tallerpro_require_permission('admin');
	$nombre = trim(GETPOST('taller_nombre', 'alphanohtml'));
	$cif = trim(GETPOST('taller_cif', 'alphanohtml'));
	$email = trim(GETPOST('taller_email', 'alphanohtml'));
	$telefono = trim(GETPOST('taller_telefono', 'alphanohtml'));
	$direccion = trim(GETPOST('taller_direccion', 'alphanohtml'));
	$poblacion = trim(GETPOST('taller_poblacion', 'alphanohtml'));
	$iban = trim(GETPOST('taller_iban', 'alphanohtml'));
	$mostrar_iban = (GETPOST('taller_mostrar_iban', 'alpha') === '1' || GETPOST('taller_mostrar_iban', 'alpha') === 'on' || GETPOSTINT('taller_mostrar_iban') === 1) ? 1 : 0;

	dolibarr_set_const($db, 'TALLER_NOMBRE_COMERCIAL', $nombre, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'TALLER_CIF', $cif, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'TALLER_EMAIL', $email, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'TALLER_TELEFONO', $telefono, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'TALLER_DIRECCION', $direccion, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'TALLER_POBLACION', $poblacion, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'TALLER_MOSTRAR_IBAN', $mostrar_iban, 'yesno', 0, '', $conf->entity);

	// Cifrado simétrico seguro para el IBAN bancario (OpenSSL con clave de instancia)
	if (!empty($iban) && strtoupper($iban) !== 'NONE') {
		$iban_clean = strtoupper(str_replace(' ', '', $iban));
		$iban_cifrado = dolEncrypt($iban_clean);
		dolibarr_set_const($db, 'TALLER_IBAN', $iban_cifrado, 'chaine', 0, '', $conf->entity);
	} else {
		dolibarr_del_const($db, 'TALLER_IBAN', $conf->entity);
	}

	setEventMessages("Datos del taller actualizados correctamente", null, 'mesgs');
	header("Location: " . $_SERVER['PHP_SELF']);
	exit;
}

// Cargar datos: primero de las constantes de TallerPro, o fallback a los datos generales de Dolibarr ($mysoc)
$taller_nombre = !empty($conf->global->TALLER_NOMBRE_COMERCIAL) ? $conf->global->TALLER_NOMBRE_COMERCIAL : (!empty($mysoc->name) ? $mysoc->name : 'TallerPro');
$taller_cif = !empty($conf->global->TALLER_CIF) ? $conf->global->TALLER_CIF : (!empty($mysoc->idprof1) ? $mysoc->idprof1 : (!empty($mysoc->tva_intra) ? $mysoc->tva_intra : ''));
$taller_email = !empty($conf->global->TALLER_EMAIL) ? $conf->global->TALLER_EMAIL : (!empty($mysoc->email) ? $mysoc->email : '');
$taller_telefono = !empty($conf->global->TALLER_TELEFONO) ? $conf->global->TALLER_TELEFONO : (!empty($mysoc->phone) ? $mysoc->phone : '');
$taller_direccion = !empty($conf->global->TALLER_DIRECCION) ? $conf->global->TALLER_DIRECCION : (!empty($mysoc->address) ? $mysoc->address : '');
$taller_poblacion = !empty($conf->global->TALLER_POBLACION) ? $conf->global->TALLER_POBLACION : ((!empty($mysoc->zip) ? $mysoc->zip . ' ' : '') . (!empty($mysoc->town) ? $mysoc->town : ''));
$taller_mostrar_iban = isset($conf->global->TALLER_MOSTRAR_IBAN) ? (int) $conf->global->TALLER_MOSTRAR_IBAN : 1;

// Descifrar IBAN para visualización en el formulario
$taller_iban = '';
if (!empty($conf->global->TALLER_IBAN)) {
	$raw_iban = dolDecrypt($conf->global->TALLER_IBAN);
	if (!empty($raw_iban) && strtoupper(trim($raw_iban)) !== 'NONE') {
		$taller_iban = trim(chunk_split($raw_iban, 4, ' '));
	}
}

// Rutas
$css_file = __DIR__ . '/css/tallerpro.css';
$js_file = __DIR__ . '/js/tallerpro.js';
$css_version = file_exists($css_file) ? filemtime($css_file) : dol_now();
$js_version = file_exists($js_file) ? filemtime($js_file) : dol_now();

$css_url = dol_buildpath('/taller/css/tallerpro.css', 1) . '?v=' . $css_version;
$js_url = dol_buildpath('/taller/js/tallerpro.js', 1) . '?v=' . $js_version;

$morehead = '<link rel="stylesheet" type="text/css" href="'.$css_url.'">'."\n";
$morehead .= '<script src="'.$js_url.'"></script>'."\n";

llxHeader($morehead, 'Configuración - TallerPro', '', '', 0, 0, array(), array(), '', 'mod-taller page-tallerpro');

$dolibarr_root_url = DOL_URL_ROOT . '/index.php';
$index_url = dol_buildpath('/taller/tallerindex.php', 1);
$clientes_url = dol_buildpath('/taller/clientes.php', 1);
$vehiculos_url = dol_buildpath('/taller/vehiculos.php', 1);
$reparaciones_url = dol_buildpath('/taller/reparaciones.php', 1);
$materiales_url = dol_buildpath('/taller/materiales.php', 1);
$seguros_url = dol_buildpath('/taller/seguros.php', 1);
$facturas_url = dol_buildpath('/taller/facturas.php', 1);
$configuracion_url = dol_buildpath('/taller/configuracion.php', 1);
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
						<a href="<?php echo $configuracion_url; ?>" class="tallerpro-nav-item active">
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
					<span class="tallerpro-topbar-breadcrumb-current"><?php echo $langs->trans("Configuracion"); ?></span>
				</div>
			</div>
			<div class="tallerpro-topbar-right">
				<a href="<?php echo $dolibarr_root_url; ?>" class="tallerpro-back-dolibarr" title="Volver al menú principal de Dolibarr">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="19" y1="12" x2="5" y2="12"></line>
						<polyline points="12 19 5 12 12 5"></polyline>
					</svg>
					<span><?php echo $langs->trans("VolverADolibarr"); ?></span>
				</a>
			</div>
		</header>

		<!-- Content -->
		<div class="tallerpro-content-wrapper">
			<main class="tallerpro-content" style="max-width: 860px; margin: 0 auto; width: 100%;">
				
				<!-- Header -->
				<div class="tallerpro-content-header" style="margin-bottom: 24px;">
					<h1 class="tallerpro-page-title" style="font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">Configuración</h1>
					<p class="tallerpro-page-subtitle" style="font-size: 14px; color: #64748b; margin: 0;">Preferencias del taller</p>
				</div>

				<!-- Formulario editable -->
				<form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
					<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
					<input type="hidden" name="action" value="update_settings">

					<!-- Tarjeta 1: Datos del taller -->
					<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px 28px; margin-bottom: 24px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
							<h2 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">Datos del taller</h2>
							<span style="font-size: 12px; color: #64748b; background: #f8fafc; padding: 4px 10px; border-radius: 6px; border: 1px solid #e2e8f0;">Datos visibles en órdenes y facturas</span>
						</div>

						<div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; gap: 20px;">
							<label style="font-size: 14px; color: #64748b; min-width: 140px;">Nombre comercial</label>
							<input type="text" name="taller_nombre" class="tallerpro-form-input" style="max-width: 450px;" value="<?php echo dol_escape_htmltag($taller_nombre); ?>" placeholder="Nombre comercial de tu taller" required />
						</div>

						<div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; gap: 20px;">
							<label style="font-size: 14px; color: #64748b; min-width: 140px;">CIF / NIF</label>
							<input type="text" name="taller_cif" class="tallerpro-form-input" style="max-width: 450px;" value="<?php echo dol_escape_htmltag($taller_cif); ?>" placeholder="Ej: B-12345678" />
						</div>

						<div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; gap: 20px;">
							<label style="font-size: 14px; color: #64748b; min-width: 140px;">Email</label>
							<input type="email" name="taller_email" class="tallerpro-form-input" style="max-width: 450px;" value="<?php echo dol_escape_htmltag($taller_email); ?>" placeholder="taller@ejemplo.com" />
						</div>

						<div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; gap: 20px;">
							<label style="font-size: 14px; color: #64748b; min-width: 140px;">Teléfono</label>
							<input type="text" name="taller_telefono" class="tallerpro-form-input" style="max-width: 450px;" value="<?php echo dol_escape_htmltag($taller_telefono); ?>" placeholder="Teléfono del taller" />
						</div>

						<div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; gap: 20px;">
							<label style="font-size: 14px; color: #64748b; min-width: 140px;">Dirección</label>
							<input type="text" name="taller_direccion" class="tallerpro-form-input" style="max-width: 450px;" value="<?php echo dol_escape_htmltag($taller_direccion); ?>" placeholder="Calle, número, polígono..." />
						</div>

						<div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; gap: 20px;">
							<label style="font-size: 14px; color: #64748b; min-width: 140px;">CP y Ciudad</label>
							<input type="text" name="taller_poblacion" class="tallerpro-form-input" style="max-width: 450px;" value="<?php echo dol_escape_htmltag($taller_poblacion); ?>" placeholder="Ej: 28001 Madrid" />
						</div>

						<div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid #f1f5f9; gap: 20px;">
							<div>
								<label style="font-size: 14px; color: #64748b; display: block; margin-bottom: 4px;">IBAN Bancario (Opcional)</label>
								<span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; color: #059669; background: #ecfdf5; padding: 2px 8px; border-radius: 4px; border: 1px solid #a7f3d0;">
									<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
										<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
										<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
									</svg>
									Cifrado seguro en BD
								</span>
							</div>
							<div style="width: 100%; max-width: 450px;">
								<input type="text" name="taller_iban" class="tallerpro-form-input" style="font-family: monospace; letter-spacing: 0.05em;" value="<?php echo dol_escape_htmltag($taller_iban); ?>" placeholder="ES00 0000 0000 0000 0000 0000" />
								<span style="font-size: 11.5px; color: #94a3b8; margin-top: 4px; display: block;">Se imprimirá en las facturas pendientes como cuenta de destino para transferencias.</span>
							</div>
						</div>

						<div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #f1f5f9; gap: 20px;">
							<div>
								<label for="taller_mostrar_iban" style="font-size: 14px; color: #475569; font-weight: 500; display: block; margin-bottom: 4px; cursor: pointer;">Mostrar IBAN en facturas</label>
								<span style="font-size: 11.5px; color: #94a3b8; display: block;">¿Desea mostrar el IBAN al emitir factura?</span>
							</div>
							<div style="width: 100%; max-width: 450px;">
								<div class="tallerpro-switch-wrapper">
									<label class="tallerpro-switch" for="taller_mostrar_iban">
										<input type="hidden" name="taller_mostrar_iban" value="0">
										<input type="checkbox" name="taller_mostrar_iban" id="taller_mostrar_iban" value="1" <?php echo !empty($taller_mostrar_iban) ? 'checked' : ''; ?>>
										<span class="tallerpro-switch-slider"></span>
									</label>
									<label for="taller_mostrar_iban" id="taller_mostrar_iban_status" class="tallerpro-switch-label <?php echo !empty($taller_mostrar_iban) ? 'is-active' : 'is-inactive'; ?>">
										<?php echo !empty($taller_mostrar_iban) ? 'Activado (se mostrará en facturas emitidas)' : 'Desactivado (oculto en facturas emitidas)'; ?>
									</label>
								</div>
								<span style="font-size: 11.5px; color: #94a3b8; margin-top: 6px; display: block;">Si está desactivado, el bloque de transferencia bancaria y el número de cuenta se omitirán por completo en las facturas.</span>
							</div>
						</div>

						<div style="margin-top: 20px; display: flex; justify-content: flex-end;">
							<button type="submit" class="tallerpro-btn-save" style="padding: 9px 20px; font-size: 13.5px; font-weight: 600; border-radius: 8px;">
								Guardar cambios
							</button>
						</div>
					</div>
				</form>

				<!-- Tarjeta 2: Sobre esta aplicación -->
				<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px 28px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
					<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px;">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="12" cy="12" r="10"></circle>
							<line x1="12" y1="16" x2="12" y2="12"></line>
							<line x1="12" y1="8" x2="12.01" y2="8"></line>
						</svg>
						<h2 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">Sobre esta aplicación</h2>
					</div>

					<p style="font-size: 13.5px; color: #64748b; line-height: 1.6; margin: 0 0 10px 0;">
						Aplicación de gestión profesional para talleres mecánicos integrada con Dolibarr ERP: clientes, vehículos, reparaciones, materiales, seguros y facturas.
					</p>

					<p style="font-size: 13px; color: #94a3b8; line-height: 1.5; margin: 0;">
						Los datos están asegurados y sincronizados con tu base de datos de Dolibarr. El acceso al módulo se gestiona desde el panel de usuarios y permisos de Dolibarr.
					</p>
				</div>

			</main>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var sw = document.getElementById('taller_mostrar_iban');
	var lbl = document.getElementById('taller_mostrar_iban_status');
	if (sw && lbl) {
		sw.addEventListener('change', function() {
			if (this.checked) {
				lbl.textContent = 'Activado (se mostrará en facturas emitidas)';
				lbl.className = 'tallerpro-switch-label is-active';
			} else {
				lbl.textContent = 'Desactivado (oculto en facturas emitidas)';
				lbl.className = 'tallerpro-switch-label is-inactive';
			}
		});
	}
});
</script>

<?php
llxFooter();
$db->close();