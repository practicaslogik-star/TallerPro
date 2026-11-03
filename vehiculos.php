<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       taller/vehiculos.php
 *  \ingroup    taller
 *  \brief      Vehículos list view - TallerPro view
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
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

$table_vehiculo = MAIN_DB_PREFIX . "taller_vehiculo";
$table_documento = MAIN_DB_PREFIX . "taller_documento_vehiculo";

// Ensure tables exist
$sql_create = "CREATE TABLE IF NOT EXISTS " . $table_vehiculo . " (
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
$db->query($sql_create);

$sql_create_doc = "CREATE TABLE IF NOT EXISTS " . $table_documento . " (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_vehiculo INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    tipo VARCHAR(100) DEFAULT NULL,
    archivo VARCHAR(255) DEFAULT NULL,
    url VARCHAR(500) DEFAULT NULL,
    datec DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fk_vehiculo (fk_vehiculo)
) ENGINE=InnoDB;";
$db->query($sql_create_doc);

$action = GETPOST('action', 'alpha');
if (empty($action) && !empty($_POST['action'])) {
	$action = $_POST['action'];
}

// Acción: Añadir Documento
if ($action == 'add_documento') {
	$fk_vehiculo = GETPOSTINT('fk_vehiculo');
	if (empty($fk_vehiculo) && !empty($_POST['fk_vehiculo'])) {
		$fk_vehiculo = (int) $_POST['fk_vehiculo'];
	}
	$nombre = trim(GETPOST('nombre', 'alphanohtml'));
	if (empty($nombre) && !empty($_POST['nombre'])) {
		$nombre = trim($_POST['nombre']);
	}
	$tipo = trim(GETPOST('tipo', 'alphanohtml'));
	if (empty($tipo) && !empty($_POST['tipo'])) {
		$tipo = trim($_POST['tipo']);
	}
	$url = trim(GETPOST('url', 'none'));
	if (empty($url) && !empty($_POST['url'])) {
		$url = trim($_POST['url']);
	}

	$archivo_filename = '';
	$file_size = 0;

	if (empty($nombre)) {
		setEventMessages("El nombre del documento es obligatorio", null, 'errors');
	} elseif ($fk_vehiculo <= 0) {
		setEventMessages("Vehículo no válido", null, 'errors');
	} else {
		// Procesar subida de archivo si existe
		if (!empty($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
			require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
			$upload_dir = (!empty($conf->taller->dir_output) ? $conf->taller->dir_output : (DOL_DATA_ROOT . '/taller')) . '/vehiculos/' . $fk_vehiculo;
			if (!is_dir($upload_dir)) {
				dol_mkdir($upload_dir);
			}

			$orig_name = basename($_FILES['archivo']['name']);
			$clean_name = dol_sanitizeFileName($orig_name);
			$final_name = time() . '_' . $clean_name;
			$destination = $upload_dir . '/' . $final_name;

			if (dol_move_uploaded_file($_FILES['archivo']['tmp_name'], $destination, 0, 0, $_FILES['archivo']['error'])) {
				$archivo_filename = $final_name;
				$file_size = (int) $_FILES['archivo']['size'];
			} else {
				setEventMessages("Error al guardar el archivo adjunto", null, 'errors');
			}
		}

		$sql_ins = "INSERT INTO " . $table_documento . " (";
		$sql_ins .= "fk_vehiculo, nombre, tipo, archivo, url, datec";
		$sql_ins .= ") VALUES (";
		$sql_ins .= ((int) $fk_vehiculo) . ", ";
		$sql_ins .= "'" . $db->escape($nombre) . "', ";
		$sql_ins .= "'" . $db->escape($tipo) . "', ";
		$sql_ins .= "'" . $db->escape($archivo_filename) . "', ";
		$sql_ins .= "'" . $db->escape($url) . "', ";
		$sql_ins .= "'" . $db->idate(dol_now()) . "'";
		$sql_ins .= ")";

		if ($db->query($sql_ins)) {
			setEventMessages("Documento guardado correctamente", null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $fk_vehiculo . "&tab=tab-documentos");
			exit;
		} else {
			setEventMessages("Error al guardar en la base de datos: " . $db->lasterror(), null, 'errors');
			header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $fk_vehiculo . "&tab=tab-documentos");
			exit;
		}
	}
}

// Acción: Eliminar Documento
if ($action == 'delete_documento') {
	$doc_id = GETPOSTINT('rowid');
	$fk_vehiculo = GETPOSTINT('fk_vehiculo');
	if (empty($fk_vehiculo) && !empty($_GET['fk_vehiculo'])) {
		$fk_vehiculo = (int) $_GET['fk_vehiculo'];
	}

	if ($doc_id > 0) {
		$sql_get = "SELECT archivo, fk_vehiculo FROM " . $table_documento . " WHERE rowid = " . ((int) $doc_id) . " LIMIT 1";
		$res_get = $db->query($sql_get);
		if ($res_get && ($doc_obj = $db->fetch_object($res_get))) {
			$fk_vehiculo = (int) $doc_obj->fk_vehiculo;
			if (!empty($doc_obj->archivo)) {
				$base_dir = (!empty($conf->taller->dir_output) ? $conf->taller->dir_output : (DOL_DATA_ROOT . '/taller'));
				$file_path = $base_dir . '/vehiculos/' . $fk_vehiculo . '/' . $doc_obj->archivo;
				if (file_exists($file_path)) {
					require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
					dol_delete_file($file_path);
				}
			}
			$db->query("DELETE FROM " . $table_documento . " WHERE rowid = " . ((int) $doc_id));
			setEventMessages("Documento eliminado", null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $fk_vehiculo . "&tab=tab-documentos");
			exit;
		}
	}
}

// Acción: Descargar / Ver Documento
if ($action == 'download_doc') {
	$doc_id = GETPOSTINT('rowid');
	if ($doc_id > 0) {
		$sql_d = "SELECT archivo, fk_vehiculo, nombre FROM " . $table_documento . " WHERE rowid = " . ((int) $doc_id) . " LIMIT 1";
		$res_d = $db->query($sql_d);
		if ($res_d && ($doc = $db->fetch_object($res_d))) {
			$base_dir = (!empty($conf->taller->dir_output) ? $conf->taller->dir_output : (DOL_DATA_ROOT . '/taller'));
			$file_path = $base_dir . '/vehiculos/' . $doc->fk_vehiculo . '/' . $doc->archivo;
			if (file_exists($file_path)) {
				require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
				dol_download($file_path, $doc->archivo, 'application/octet-stream', 1);
				exit;
			}
		}
	}
}

// Acción: Crear Vehículo
if ($action == 'create_vehiculo' || $action == 'add_vehiculo') {
	$matricula = strtoupper(trim(GETPOST('matricula', 'alphanohtml')));
	if (empty($matricula) && !empty($_POST['matricula'])) {
		$matricula = strtoupper(trim($_POST['matricula']));
	}

	$fk_soc = GETPOSTINT('fk_soc');
	if (empty($fk_soc) && !empty($_POST['fk_soc'])) {
		$fk_soc = (int) $_POST['fk_soc'];
	}

	$marca = trim(GETPOST('marca', 'alphanohtml'));
	$modelo = trim(GETPOST('modelo', 'alphanohtml'));
	$anio = trim(GETPOST('anio', 'alphanohtml'));
	$color = trim(GETPOST('color', 'alphanohtml'));
	$vin = trim(GETPOST('vin', 'alphanohtml'));
	$km = GETPOSTINT('km');
	$notas = trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) {
		$notas = trim($_POST['notas']);
	}

	if (empty($matricula)) {
		setEventMessages($langs->trans("ErrorFieldRequired", "Matrícula"), null, 'errors');
	} elseif ($fk_soc <= 0) {
		setEventMessages($langs->trans("ErrorFieldRequired", "Propietario / Cliente"), null, 'errors');
	} else {
		$sql_insert = "INSERT INTO " . $table_vehiculo . " (";
		$sql_insert .= "fk_soc, matricula, marca, modelo, anio, color, vin, km, notas, date_creation, fk_user_creat, entity, status";
		$sql_insert .= ") VALUES (";
		$sql_insert .= ((int) $fk_soc) . ", ";
		$sql_insert .= "'" . $db->escape($matricula) . "', ";
		$sql_insert .= "'" . $db->escape($marca) . "', ";
		$sql_insert .= "'" . $db->escape($modelo) . "', ";
		$sql_insert .= "'" . $db->escape($anio) . "', ";
		$sql_insert .= "'" . $db->escape($color) . "', ";
		$sql_insert .= "'" . $db->escape($vin) . "', ";
		$sql_insert .= ((int) $km) . ", ";
		$sql_insert .= "'" . $db->escape($notas) . "', ";
		$sql_insert .= "'" . $db->idate(dol_now()) . "', ";
		$sql_insert .= ((int) $user->id) . ", ";
		$sql_insert .= ((int) $conf->entity) . ", ";
		$sql_insert .= "1";
		$sql_insert .= ")";

		$resql = $db->query($sql_insert);
		if ($resql) {
			// Si el usuario eligió incorporar el modelo/marca al catálogo JSON permanente
			$guardar_en_cat = GETPOSTINT('guardar_en_catalogo');
			if ($guardar_en_cat == 1 && !empty($marca) && !empty($modelo)) {
				$json_path = __DIR__ . '/js/catalogo_coches_new.json';
				if (file_exists($json_path) && is_writable($json_path)) {
					$json_content = file_get_contents($json_path);
					$cat_data = json_decode($json_content, true);
					if (is_array($cat_data)) {
						// Si la marca no existe, la creamos
						if (!isset($cat_data[$marca])) {
							$cat_data[$marca] = array();
						}

						$modelos_existentes_lower = array_map('mb_strtolower', $cat_data[$marca]);
						if (!in_array(mb_strtolower($modelo), $modelos_existentes_lower)) {
							$cat_data[$marca][] = $modelo;
							sort($cat_data[$marca], SORT_NATURAL | SORT_FLAG_CASE);
							ksort($cat_data, SORT_NATURAL | SORT_FLAG_CASE);
							file_put_contents($json_path, json_encode($cat_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
						}
					}
				}
			}

			header("Location: " . $_SERVER['PHP_SELF'] . "?msg=saved");
			exit;
		} else {
			dol_syslog("TallerPro - Error al guardar vehículo: " . $db->lasterror(), LOG_ERR);
			setEventMessages("Error al guardar el vehículo", null, 'errors');
		}
	}
}

// Acción: Eliminar Vehículo (Seguro vía POST con token)
if ($action == 'delete_vehiculo' || $action == 'delete') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		accessforbidden('Method not allowed');
	}
	$token = GETPOST('token', 'alpha');
	$valid_token = (!empty($_SESSION['token']) && $token === $_SESSION['token']) || (!empty($_SESSION['newtoken']) && $token === $_SESSION['newtoken']);
	if (!$valid_token) {
		accessforbidden('Invalid security token');
	}

	$rowid = GETPOSTINT('rowid');
	if (empty($rowid) && !empty($_POST['rowid'])) {
		$rowid = (int) $_POST['rowid'];
	}
	if ($rowid > 0) {
		// 1. Comprobar si este vehículo tiene alguna factura emitida
		$has_invoices = false;
		$invoice_ref = '';

		// A) Facturas oficiales de Dolibarr vinculadas por extrafield fk_vehiculo
		$sql_f_check1 = "SELECT f.ref FROM " . MAIN_DB_PREFIX . "facture_extrafields as fe ";
		$sql_f_check1 .= "JOIN " . MAIN_DB_PREFIX . "facture as f ON f.rowid = fe.fk_object ";
		$sql_f_check1 .= "WHERE fe.fk_vehiculo = " . ((int) $rowid) . " LIMIT 1";
		$res_f1 = $db->query($sql_f_check1);
		if ($res_f1 && ($obj_f1 = $db->fetch_object($res_f1))) {
			$has_invoices = true;
			$invoice_ref = $obj_f1->ref;
		}

		// B) Reparaciones de este vehículo que tengan ref_factura generada
		if (!$has_invoices) {
			$sql_f_check2 = "SELECT ref_factura FROM " . $table_reparacion . " ";
			$sql_f_check2 .= "WHERE fk_vehiculo = " . ((int) $rowid) . " AND ref_factura IS NOT NULL AND ref_factura != '' LIMIT 1";
			$res_f2 = $db->query($sql_f_check2);
			if ($res_f2 && ($obj_f2 = $db->fetch_object($res_f2))) {
				$has_invoices = true;
				$invoice_ref = $obj_f2->ref_factura;
			}
		}

		if ($has_invoices) {
			// =========================================================================
			// BLOQUEO DE SEGURIDAD (ESTILO DOLIBARR): No se permite destruir elementos facturados
			// =========================================================================
			$msg_error = "No se puede eliminar el vehículo porque tiene facturación emitida (" . $invoice_ref . "). Por motivos fiscales y contables, los registros facturados no pueden eliminarse.";
			header("Location: " . $_SERVER['PHP_SELF'] . "?msg=error&error=" . urlencode($msg_error));
			exit;

		} else {
			dol_syslog("TallerPro - Error al eliminar vehículo ID " . $rowid . ": " . $db->lasterror(), LOG_ERR);
			setEventMessages("Error al eliminar el vehículo", null, 'errors');
		}
	}
}

// Consultar clientes reales para el desplegable
$clients_list = array();
$sql_c = "SELECT s.rowid, s.nom FROM " . MAIN_DB_PREFIX . "societe as s WHERE s.client IN (1, 2, 3) AND s.status = 1 AND s.entity IN (" . getEntity('societe') . ") ORDER BY s.nom ASC";
$res_c = $db->query($sql_c);
if ($res_c) {
	while ($c = $db->fetch_object($res_c)) {
		$clients_list[] = $c;
	}
}

// Consultar vehículos con JOIN de cliente y comprobación de facturación vinculada
$sql_veh = "SELECT v.rowid, v.fk_soc, v.matricula, v.marca, v.modelo, v.anio, v.color, v.vin, v.km, v.notas, ";
$sql_veh .= "s.nom as cliente_nombre, ";
$sql_veh .= "(SELECT COUNT(fe.rowid) FROM " . MAIN_DB_PREFIX . "facture_extrafields as fe WHERE fe.fk_vehiculo = v.rowid) as nb_facturas_directas, ";
$sql_veh .= "(SELECT COUNT(r.rowid) FROM " . MAIN_DB_PREFIX . "taller_reparacion as r WHERE r.fk_vehiculo = v.rowid AND r.ref_factura IS NOT NULL AND r.ref_factura != '') as nb_reparaciones_facturadas ";
$sql_veh .= "FROM " . $table_vehiculo . " as v ";
$sql_veh .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON v.fk_soc = s.rowid ";
$sql_veh .= "WHERE v.status = 1 AND v.entity IN (" . getEntity('taller_vehiculo') . ") ";
$sql_veh .= "ORDER BY v.rowid DESC";

$res_veh = $db->query($sql_veh);
$num_veh = $res_veh ? $db->num_rows($res_veh) : 0;

$veh_id = GETPOSTINT('id');
if (empty($veh_id) && isset($_GET['id'])) {
	$veh_id = (int) $_GET['id'];
}

if (empty($veh_id)) {
	$veh_id = GETPOSTINT('rowid');
}

if (empty($veh_id) && isset($_GET['rowid'])) {
	$veh_id = (int) $_GET['rowid'];
}

$veh_matricula = trim(GETPOST('matricula', 'alphanohtml'));
if (empty($veh_matricula) && isset($_GET['matricula'])) {
	$veh_matricula = trim($_GET['matricula']);
}

$veh_detail = null;
$client_detail = null;
$veh_invoices = array();
$veh_repairs = array();
$veh_seguros = array();
$veh_documentos = array();

if ($veh_id > 0 || !empty($veh_matricula)) {
	$sql_vd = "SELECT v.rowid, v.fk_soc, v.matricula, v.marca, v.modelo, v.anio, v.color, v.vin, v.km, v.notas, ";
	$sql_vd .= "s.nom as cliente_nombre, s.phone as cliente_telefono, s.email as cliente_email, ";
	$sql_vd .= "s.siren as cliente_cif, s.address as cliente_direccion, s.zip as cliente_cp, s.town as cliente_ciudad ";
	$sql_vd .= "FROM " . $table_vehiculo . " as v ";
	$sql_vd .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON v.fk_soc = s.rowid ";
	$sql_vd .= "WHERE v.status = 1 ";
	if ($veh_id > 0) {
		$sql_vd .= "AND v.rowid = " . ((int) $veh_id) . " ";
	} else {
		$sql_vd .= "AND v.matricula = '" . $db->escape($veh_matricula) . "' ";
	}
	$sql_vd .= "AND v.entity IN (" . getEntity('taller_vehiculo') . ") LIMIT 1";

	$res_vd = $db->query($sql_vd);
	if ($res_vd && $db->num_rows($res_vd) > 0) {
		$veh_detail = $db->fetch_object($res_vd);
		if (!empty($veh_detail->fk_soc)) {
			require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
			$soc = new Societe($db);
			if ($soc->fetch($veh_detail->fk_soc) > 0) {
				$client_detail = $soc;
			}

			// Invoices of this vehicle
			$sql_inv = "SELECT f.rowid, f.ref, f.total_ttc, f.datef, f.paye, f.fk_statut ";
			$sql_inv .= "FROM " . MAIN_DB_PREFIX . "facture as f ";
			$sql_inv .= "LEFT JOIN " . MAIN_DB_PREFIX . "facture_extrafields as fe ON fe.fk_object = f.rowid ";
			$sql_inv .= "WHERE (fe.fk_vehiculo = " . ((int) $veh_detail->rowid) . " OR fe.snapshot_matricula = '" . $db->escape($veh_detail->matricula) . "') ";
			$sql_inv .= "ORDER BY f.datef DESC, f.rowid DESC";
			$res_inv = $db->query($sql_inv);
			if ($res_inv) {
				while ($inv_obj = $db->fetch_object($res_inv)) {
					$veh_invoices[] = $inv_obj;
				}
			}
		}

		// Repairs of this vehicle
		$sql_vr = "SELECT rowid, fecha, descripcion, coste_mo, notas, ref_factura ";
		$sql_vr .= "FROM " . MAIN_DB_PREFIX . "taller_reparacion ";
		$sql_vr .= "WHERE fk_vehiculo = " . ((int) $veh_detail->rowid) . " AND status = 1 ";
		$sql_vr .= "ORDER BY fecha DESC, rowid DESC";
		$res_vr = $db->query($sql_vr);
		if ($res_vr) {
			while ($vr_obj = $db->fetch_object($res_vr)) {
				$veh_repairs[] = $vr_obj;
			}
		}

		// Insurance of this vehicle
		$sql_vs = "SELECT rowid, compania, poliza, tipo, fecha_inicio, fecha_fin, telefono, notas ";
		$sql_vs .= "FROM " . MAIN_DB_PREFIX . "taller_seguro ";
		$sql_vs .= "WHERE fk_vehiculo = " . ((int) $veh_detail->rowid) . " AND status = 1 ";
		$sql_vs .= "ORDER BY rowid DESC";
		$res_vs = $db->query($sql_vs);
		if ($res_vs) {
			while ($vs_obj = $db->fetch_object($res_vs)) {
				$veh_seguros[] = $vs_obj;
			}
		}

		// Documents of this vehicle
		$sql_vdoc = "SELECT rowid, nombre, tipo, archivo, url, datec ";
		$sql_vdoc .= "FROM " . $table_documento . " ";
		$sql_vdoc .= "WHERE fk_vehiculo = " . ((int) $veh_detail->rowid) . " ";
		$sql_vdoc .= "ORDER BY rowid DESC";
		$res_vdoc = $db->query($sql_vdoc);
		if ($res_vdoc) {
			while ($vdoc_obj = $db->fetch_object($res_vdoc)) {
				$veh_documentos[] = $vdoc_obj;
			}
		}
	}
}

$css_file = __DIR__ . '/css/tallerpro.css';
$js_file = __DIR__ . '/js/tallerpro.js';
$css_version = (file_exists($css_file) ? filemtime($css_file) : dol_now());
$js_version = (file_exists($js_file) ? filemtime($js_file) : dol_now()) . '_' . dol_now();

$css_url = dol_buildpath('/taller/css/tallerpro.css', 1) . '?v=' . $css_version;
$js_url = dol_buildpath('/taller/js/tallerpro.js', 1) . '?v=' . $js_version;

$morehead = '<link rel="stylesheet" type="text/css" href="' . $css_url . '">' . "\n";
$morehead .= '<script src="' . $js_url . '"></script>' . "\n";

// Page Header
llxHeader($morehead, 'Vehículos - TallerPro', '', '', 0, 0, array(), array(), $morehead, 'mod-taller page-tallerpro');

$dolibarr_root_url = DOL_URL_ROOT . '/index.php';
$setup_url = dol_buildpath('/taller/configuracion.php', 1);
//enlace menu lateral 
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
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round">
						<path
							d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z">
						</path>
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
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
						<a href="<?php echo $vehiculos_url; ?>" class="tallerpro-nav-item active">
							<span class="tallerpro-nav-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path
										d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 3.1C2 11.4 2 11.7 2 12v4c0 .6.4 1 1 1h2">
									</path>
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
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path
										d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z">
									</path>
								</svg>
							</span>
							<span class="tallerpro-nav-text"><?php echo $langs->trans("Reparaciones"); ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo $materiales_url; ?>" class="tallerpro-nav-item">
							<span class="tallerpro-nav-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path
										d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z">
									</path>
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
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
								</svg>
							</span>
							<span class="tallerpro-nav-text"><?php echo $langs->trans("Seguros"); ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo $facturas_url; ?>" class="tallerpro-nav-item">
							<span class="tallerpro-nav-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<rect x="3" y="3" width="18" height="18" rx="2"></rect>
									<line x1="12" y1="7" x2="12" y2="17"></line>
									<path
										d="M9.5 9.5a1.5 1.5 0 0 1 1.5-1.5h2a1.5 1.5 0 0 1 1.5 1.5c0 1.5-3 1.5-3 3a1.5 1.5 0 0 0 1.5 1.5h2a1.5 1.5 0 0 0 1.5-1.5">
									</path>
								</svg>
							</span>
							<span class="tallerpro-nav-text"><?php echo $langs->trans("Facturas"); ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo $setup_url; ?>" class="tallerpro-nav-item">
							<span class="tallerpro-nav-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<circle cx="12" cy="12" r="3"></circle>
									<path
										d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z">
									</path>
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
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
					stroke-linecap="round" stroke-linejoin="round">
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
				<button type="button" class="tallerpro-topbar-sidebar-toggle" id="tallerTopbarToggle"
					title="Alternar menú" aria-label="Alternar menú">
					<svg class="tallerpro-toggle-icon-desktop" width="18" height="18" viewBox="0 0 24 24" fill="none"
						stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
						<line x1="9" y1="3" x2="9" y2="21"></line>
					</svg>
					<svg class="tallerpro-toggle-icon-mobile" width="20" height="20" viewBox="0 0 24 24" fill="none"
						stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
				<?php
				// Mostrar alertas o mensajes de Dolibarr
				echo dol_htmloutput_events();
				?>

				<?php if ($veh_detail): ?>
					<?php
					$client_name = '—';
					$client_phone = '—';
					$client_email = '—';
					$client_dni = '—';
					$client_address = '—';

					if (!empty($client_detail)) {
						if (!empty($client_detail->nom))
							$client_name = $client_detail->nom;
						if (!empty($client_detail->phone))
							$client_phone = $client_detail->phone;
						if (!empty($client_detail->email))
							$client_email = $client_detail->email;
						if (!empty($client_detail->siren)) {
							$client_dni = $client_detail->siren;
						} elseif (!empty($client_detail->idprof1)) {
							$client_dni = $client_detail->idprof1;
						}
						$addr_parts = array();
						if (!empty($client_detail->address))
							$addr_parts[] = $client_detail->address;
						$zip_town = trim(($client_detail->zip ?? '') . ' ' . ($client_detail->town ?? ''));
						if (!empty($zip_town))
							$addr_parts[] = $zip_town;
						if (!empty($addr_parts))
							$client_address = implode(' - ', $addr_parts);
					} elseif (!empty($veh_detail->cliente_nombre)) {
						$client_name = $veh_detail->cliente_nombre;
						if (!empty($veh_detail->cliente_telefono))
							$client_phone = $veh_detail->cliente_telefono;
						if (!empty($veh_detail->cliente_email))
							$client_email = $veh_detail->cliente_email;
						if (!empty($veh_detail->cliente_cif)) {
							$client_dni = $veh_detail->cliente_cif;
						} elseif (!empty($veh_detail->cliente_idprof1)) {
							$client_dni = $veh_detail->cliente_idprof1;
						}
						$addr_parts = array();
						if (!empty($veh_detail->cliente_direccion))
							$addr_parts[] = $veh_detail->cliente_direccion;
						$zip_town = trim(($veh_detail->cliente_cp ?? '') . ' ' . ($veh_detail->cliente_ciudad ?? ''));
						if (!empty($zip_town))
							$addr_parts[] = $zip_town;
						if (!empty($addr_parts))
							$client_address = implode(' - ', $addr_parts);
					}

					$sub_brand_model = implode(' · ', array_filter(array($veh_detail->marca, $veh_detail->modelo)));
					if (empty($sub_brand_model)) {
						$sub_brand_model = '—';
					}
					$veh_color = !empty($veh_detail->color) ? $veh_detail->color : '—';
					$veh_vin = !empty($veh_detail->vin) ? $veh_detail->vin : '—';
					$veh_km = (!empty($veh_detail->km) && $veh_detail->km > 0) ? number_format($veh_detail->km, 0, ',', '.') : '—';
					?>

					<div class="tallerpro-detail-container">
						<!-- Volver a vehículos -->
						<a href="<?php echo $vehiculos_url; ?>" class="tallerpro-back-to-list">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
								stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<line x1="19" y1="12" x2="5" y2="12"></line>
								<polyline points="12 19 5 12 12 5"></polyline>
							</svg>
							<span>Volver a vehículos</span>
						</a>

						<!-- Tarjeta del Vehículo -->
						<div class="tallerpro-vehicle-card">
							<div class="tallerpro-vehicle-main-info">
								<h1 class="tallerpro-vehicle-plate">
									<?php echo dol_escape_htmltag($veh_detail->matricula); ?>
								</h1>
								<p class="tallerpro-vehicle-model"><?php echo dol_escape_htmltag($sub_brand_model); ?></p>
							</div>
							<div class="tallerpro-vehicle-meta-group">
								<div class="tallerpro-vehicle-meta-item">
									<span class="tallerpro-vehicle-meta-label">COLOR</span>
									<span
										class="tallerpro-vehicle-meta-value"><?php echo dol_escape_htmltag($veh_color); ?></span>
								</div>
								<div class="tallerpro-vehicle-meta-item">
									<span class="tallerpro-vehicle-meta-label">VIN</span>
									<span
										class="tallerpro-vehicle-meta-value"><?php echo dol_escape_htmltag($veh_vin); ?></span>
								</div>
								<div class="tallerpro-vehicle-meta-item">
									<span class="tallerpro-vehicle-meta-label">KM</span>
									<span
										class="tallerpro-vehicle-meta-value"><?php echo dol_escape_htmltag($veh_km); ?></span>
								</div>
								<div class="tallerpro-vehicle-meta-item">
									<span class="tallerpro-vehicle-meta-label">PROPIETARIO</span>
									<span class="tallerpro-vehicle-meta-value">
										<?php if (!empty($veh_detail->fk_soc)): ?>
											<a href="<?php echo dol_buildpath('/taller/clientes.php', 1) . '?id=' . $veh_detail->fk_soc; ?>"
												style="color: inherit; text-decoration: none;" title="Ver ficha del cliente">
												<?php echo dol_escape_htmltag($client_name); ?>
											</a>
										<?php else: ?>
											<?php echo dol_escape_htmltag($client_name); ?>
										<?php endif; ?>
									</span>
								</div>
							</div>
						</div>

						<!-- Navegación por pestañas -->
						<div class="tallerpro-tabs-container">
							<button type="button" class="tallerpro-tab-btn active" data-tab="tab-propietario">
								<span>Propietario</span>
							</button>
							<button type="button" class="tallerpro-tab-btn" data-tab="tab-reparaciones">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path
										d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z">
									</path>
								</svg>
								<span>Reparaciones</span>
							</button>


							<button type="button" class="tallerpro-tab-btn" data-tab="tab-seguros">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<circle cx="12" cy="12" r="9"></circle>
								</svg>
								<span>Seguros</span>
							</button>

							<button type="button" class="tallerpro-tab-btn" data-tab="tab-documentos">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
									<polyline points="14 2 14 8 20 8"></polyline>
									<line x1="16" y1="13" x2="8" y2="13"></line>
									<line x1="16" y1="17" x2="8" y2="17"></line>
									<polyline points="10 9 9 9 8 9"></polyline>
								</svg>
								<span>Documentos</span>
							</button>

							<button type="button" class="tallerpro-tab-btn" data-tab="tab-facturas">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<rect x="3" y="3" width="18" height="18" rx="2"></rect>
									<line x1="12" y1="7" x2="12" y2="17"></line>
									<path
										d="M9.5 9.5a1.5 1.5 0 0 1 1.5-1.5h2a1.5 1.5 0 0 1 1.5 1.5c0 1.5-3 1.5-3 3a1.5 1.5 0 0 0 1.5 1.5h2a1.5 1.5 0 0 0 1.5-1.5">
									</path>
								</svg>

								<span>Facturas</span>
							</button>
						</div>

						<!-- Contenido Pestaña 1: Propietario -->
						<div class="tallerpro-tab-pane active" id="tab-propietario">
							<div class="tallerpro-vehicle-detail-card">
								<h2 class="tallerpro-vehicle-detail-card-title">Datos del propietario</h2>
								<div class="tallerpro-vehicle-owner-grid">
									<div class="tallerpro-vehicle-owner-field">
										<span class="tallerpro-vehicle-owner-label">NOMBRE</span>
										<span
											class="tallerpro-vehicle-owner-value"><?php echo dol_escape_htmltag($client_name); ?></span>
									</div>
									<div class="tallerpro-vehicle-owner-field">
										<span class="tallerpro-vehicle-owner-label">TELÉFONO</span>
										<span
											class="tallerpro-vehicle-owner-value"><?php echo dol_escape_htmltag($client_phone); ?></span>
									</div>
									<div class="tallerpro-vehicle-owner-field">
										<span class="tallerpro-vehicle-owner-label">EMAIL</span>
										<span
											class="tallerpro-vehicle-owner-value"><?php echo dol_escape_htmltag($client_email); ?></span>
									</div>
									<div class="tallerpro-vehicle-owner-field">
										<span class="tallerpro-vehicle-owner-label">DNI</span>
										<span
											class="tallerpro-vehicle-owner-value"><?php echo dol_escape_htmltag($client_dni); ?></span>
									</div>
									<div class="tallerpro-vehicle-owner-field">
										<span class="tallerpro-vehicle-owner-label">DIRECCIÓN</span>
										<span
											class="tallerpro-vehicle-owner-value"><?php echo dol_escape_htmltag($client_address); ?></span>
									</div>
								</div>
							</div>
						</div>

						<!-- Contenido Pestaña 2: Reparaciones -->
						<div class="tallerpro-tab-pane" id="tab-reparaciones">
							<div class="tallerpro-vehicle-detail-card">
								<h2 class="tallerpro-vehicle-detail-card-title">Reparaciones del vehículo</h2>
								<?php if (!empty($veh_repairs)): ?>
									<div class="tallerpro-detail-list">
										<?php foreach ($veh_repairs as $vr): ?>
											<div class="tallerpro-detail-item">
												<div class="tallerpro-detail-item-main">
													<span
														class="tallerpro-detail-item-title"><?php echo dol_escape_htmltag(!empty($vr->descripcion) ? $vr->descripcion : 'Mantenimiento / Reparación'); ?></span>
													<span class="tallerpro-detail-item-sub">
														Fecha: <?php echo dol_escape_htmltag($vr->fecha); ?>
														<?php if (!empty($vr->ref_factura))
															echo ' • ' . dol_escape_htmltag($vr->ref_factura); ?>
														<?php if (!empty($vr->notas))
															echo ' • ' . dol_escape_htmltag($vr->notas); ?>
													</span>
												</div>
												<div class="tallerpro-detail-item-end">
													<span
														class="tallerpro-detail-item-price"><?php echo number_format((float) $vr->coste_mo, 2, '.', '') . ' €'; ?></span>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else: ?>
									<div class="tallerpro-empty-message">No hay reparaciones registradas para este vehículo.
									</div>
								<?php endif; ?>
							</div>
						</div>



						<!-- Contenido Pestaña 4: Seguros -->
						<div class="tallerpro-tab-pane" id="tab-seguros">
							<div class="tallerpro-vehicle-detail-card">
								<h2 class="tallerpro-vehicle-detail-card-title">Seguro del vehículo</h2>
								<?php if (!empty($veh_seguros)): ?>
									<div class="tallerpro-detail-list">
										<?php foreach ($veh_seguros as $vs): ?>
											<div class="tallerpro-seguro-item">
												<div class="tallerpro-seguro-main">
													<div class="tallerpro-seguro-header">
														<span
															class="tallerpro-seguro-company"><?php echo dol_escape_htmltag($vs->compania); ?></span>
														<?php if (!empty($vs->tipo)): ?>
															<span
																class="tallerpro-badge"><?php echo dol_escape_htmltag($vs->tipo); ?></span>
														<?php endif; ?>
													</div>
													<div class="tallerpro-seguro-details">
														<?php if (!empty($vs->poliza)): ?>
															<span><strong>Póliza:</strong>
																<?php echo dol_escape_htmltag($vs->poliza); ?></span>
														<?php endif; ?>
														<?php if (!empty($vs->telefono)): ?>
															<span><strong>Tel:</strong> <a
																	href="tel:<?php echo dol_escape_htmltag($vs->telefono); ?>"
																	style="color: #2563eb; text-decoration: none;"><?php echo dol_escape_htmltag($vs->telefono); ?></a></span>
														<?php endif; ?>
														<?php
														$has_ini = !empty($vs->fecha_inicio);
								
														$has_fin = !empty($vs->fecha_fin);
														if ($has_ini || $has_fin): ?>
															<span>
																<strong>Vigencia:</strong>
																<?php echo $has_ini ? dol_escape_htmltag($vs->fecha_inicio) : '—'; ?>
																al
																<?php echo $has_fin ? dol_escape_htmltag($vs->fecha_fin) : '—'; ?>
															</span>
														<?php endif; ?>
													</div>
													<?php if (!empty($vs->notas)): ?>
														<div class="tallerpro-seguro-notes">
															<?php echo nl2br(dol_escape_htmltag($vs->notas)); ?>
														</div>
													<?php endif; ?>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else: ?>
									<div class="tallerpro-empty-message">No hay seguro asociado registrado para este vehículo.
									</div>
								<?php endif; ?>
							</div>
						</div>
					
						<!-- Contenido Pestaña Documentos del vehículo -->
						<div class="tallerpro-tab-pane" id="tab-documentos">
							<div class="tallerpro-vehicle-detail-card">
								
								<div
									style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
									<h2 class="tallerpro-vehicle-detail-card-title" style="margin-bottom: 0;">Documentos
									</h2>
									<button type="button" class="tallerpro-btn-primary" id="btnOpenDocModal" onclick="document.getElementById('tallerDocModal').classList.add('active');">
										<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
											<line x1="12" y1="5" x2="12" y2="19"></line>
											<line x1="5" y1="12" x2="19" y2="12"></line>
										</svg>
										<span>Añadir Documento</span>
									</button>
								</div>

								<?php if (!empty($veh_documentos)): ?>
									<div class="tallerpro-detail-list">
										<?php foreach ($veh_documentos as $doc): ?>
											<?php
											$date_doc = !empty($doc->datec) ? dol_print_date($db->jdate($doc->datec), 'day') : '';
											$is_link = !empty($doc->url);
											$has_file = !empty($doc->archivo);
											$file_ext = strtolower(pathinfo($doc->archivo, PATHINFO_EXTENSION));
											?>
											<div class="tallerpro-detail-item" style="display: flex !important; flex-direction: row !important; align-items: center !important; justify-content: space-between !important; padding: 14px 18px !important; border-radius: 10px; border: 1px solid #e2e8f0; background: #ffffff; transition: all 0.2s ease;">
												<!-- Parte izquierda: Icono + Título + Metadata en horizontal -->
												<div style="display: flex; align-items: center; gap: 14px; min-width: 0;">
													<!-- Icono badge -->
													<div style="width: 42px; height: 42px; border-radius: 8px; background: <?php echo $is_link ? '#eff6ff' : ($file_ext === 'pdf' ? '#fef2f2' : '#f8fafc'); ?>; border: 1px solid <?php echo $is_link ? '#dbeafe' : ($file_ext === 'pdf' ? '#fee2e2' : '#e2e8f0'); ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
														<?php if ($is_link): ?>
															<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
																<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
																<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
															</svg>
														<?php elseif ($file_ext === 'pdf'): ?>
															<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e11d48" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
																<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
																<polyline points="14 2 14 8 20 8"></polyline>
																<line x1="16" y1="13" x2="8" y2="13"></line>
																<line x1="16" y1="17" x2="8" y2="17"></line>
																<polyline points="10 9 9 9 8 9"></polyline>
															</svg>
														<?php else: ?>
															<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
																<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
																<circle cx="8.5" cy="8.5" r="1.5"></circle>
																<polyline points="21 15 16 10 5 21"></polyline>
															</svg>
														<?php endif; ?>
													</div>

													<!-- Info texto -->
													<div style="display: flex; flex-direction: column; gap: 3px;">
														<span style="font-weight: 600; font-size: 14.5px; color: #0f172a; line-height: 1.3;">
															<?php echo dol_escape_htmltag($doc->nombre); ?>
														</span>
														<div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #64748b;">
															<?php if (!empty($doc->tipo)): ?>
																<span style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; font-weight: 500; font-size: 11.5px; border: 1px solid #e2e8f0;">
																	<?php echo dol_escape_htmltag($doc->tipo); ?>
																</span>
															<?php endif; ?>
															<span>Subido el <?php echo dol_escape_htmltag($date_doc); ?></span>
														</div>
													</div>
												</div>

												<!-- Parte derecha: Acciones elegantes -->
												<div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
													<?php if ($has_file): ?>
														<a href="<?php echo $_SERVER['PHP_SELF'] . '?action=download_doc&rowid=' . ((int) $doc->rowid); ?>" target="_blank"
															style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; font-size: 13px; font-weight: 500; color: #2563eb; background: #eff6ff; border: 1px solid #dbeafe; border-radius: 6px; text-decoration: none; transition: all 0.15s ease;"
															onmouseover="this.style.background='#dbeafe';" onmouseout="this.style.background='#eff6ff';">
															<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
																<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
																<polyline points="7 10 12 15 17 10"></polyline>
																<line x1="12" y1="15" x2="12" y2="3"></line>
															</svg>
															<span>Descargar</span>
														</a>
													<?php elseif ($is_link): ?>
														<a href="<?php echo dol_escape_htmltag($doc->url); ?>" target="_blank" rel="noopener noreferrer"
															style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; font-size: 13px; font-weight: 500; color: #2563eb; background: #eff6ff; border: 1px solid #dbeafe; border-radius: 6px; text-decoration: none; transition: all 0.15s ease;"
															onmouseover="this.style.background='#dbeafe';" onmouseout="this.style.background='#eff6ff';">
															<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
																<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
																<polyline points="15 3 21 3 21 9"></polyline>
																<line x1="10" y1="14" x2="21" y2="3"></line>
															</svg>
															<span>Abrir enlace</span>
														</a>
													<?php endif; ?>

													<a href="<?php echo $_SERVER['PHP_SELF'] . '?action=delete_documento&rowid=' . ((int) $doc->rowid) . '&fk_vehiculo=' . ((int) $veh_detail->rowid) . '&token=' . (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>"
														onclick="return confirm('¿Seguro que deseas eliminar este documento?');"
														title="Eliminar documento"
														style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; border: 1px solid transparent; background: transparent; color: #94a3b8; text-decoration: none; transition: all 0.15s ease;"
														onmouseover="this.style.background='#fee2e2'; this.style.color='#ef4444'; this.style.borderColor='#fecaca';"
														onmouseout="this.style.background='transparent'; this.style.color='#94a3b8'; this.style.borderColor='transparent';">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<polyline points="3 6 5 6 21 6"></polyline>
															<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
														</svg>
													</a>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else: ?>
									<div class="tallerpro-empty-message" id="docEmptyMsg"
										style="padding: 24px 0; color: #64748b; font-size: 14px;">
										Sin documentos.
									</div>
								<?php endif; ?>
							</div>
						</div>




						<!-- Contenido Pestaña Facturas del vehiculo -->
						<div class="tallerpro-tab-pane" id="tab-facturas">
							<div class="tallerpro-vehicle-detail-card">
								<h2 class="tallerpro-vehicle-detail-card-title">Facturas del vehículo</h2>
								<?php if (!empty($veh_invoices)): ?>
									<div class="tallerpro-detail-list">
										<?php foreach ($veh_invoices as $f): ?>
											<?php
											if ($f->fk_statut == 0) {
												$status_label = 'Borrador';
												$status_class = 'borrador';
											} elseif (!empty($f->paye) || $f->fk_statut == 2) {
												$status_label = 'Pagada';
												$status_class = 'pagada';
											} else {
												$status_label = 'Pendiente';
												$status_class = 'pendiente';
											}
											$date_str = dol_print_date($db->jdate($f->datef), 'day');
											$fac_url = dol_buildpath('/taller/facturas.php', 1) . '?id=' . ((int) $f->rowid);
											?>
											<div class="tallerpro-detail-item" style="cursor: pointer;"
												onclick="window.location.href='<?php echo $fac_url; ?>';">
												<div class="tallerpro-detail-item-main">
													<a href="<?php echo $fac_url; ?>" class="tallerpro-detail-item-title"
														style="text-decoration: none; color: inherit;">
														<?php echo dol_escape_htmltag($f->ref); ?>
													</a>
													<span class="tallerpro-detail-item-sub">Fecha:
														<?php echo dol_escape_htmltag($date_str); ?></span>
												</div>
												<div class="tallerpro-detail-item-end">
													<span
														class="tallerpro-detail-item-price"><?php echo price($f->total_ttc, 0, $langs, 1, -1, -1, $conf->currency); ?></span>
													<span
														class="tallerpro-badge-status-<?php echo $status_class; ?>"><?php echo $status_label; ?></span>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else: ?>
									<div class="tallerpro-empty-message">No hay facturas asociadas a este vehículo.</div>
								<?php endif; ?>
							</div>
						</div>
					</div>

				<?php else: ?>
					<!-- Header Actions & Title -->
					<div class="tallerpro-header-actions">
						<div class="tallerpro-content-header" style="margin-bottom: 0;">
							<h1 class="tallerpro-page-title"><?php echo $langs->trans("Vehiculos"); ?></h1>
							<p class="tallerpro-page-subtitle"><?php echo $num_veh; ?> registro(s)</p>
						</div>
						<div>
							<button type="button" class="tallerpro-btn-primary" id="tallerOpenModalBtn">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
									<th>Matrícula</th>
									<th>Marca</th>
									<th>Modelo</th>
									<th>Propietario</th>
									<th style="width: 80px; text-align: right;"></th>
								</tr>
							</thead>
							<tbody>
								<?php if ($num_veh > 0 && $res_veh): ?>
									<?php while ($veh = $db->fetch_object($res_veh)): ?>
										<?php
										$client_url = dol_buildpath('/taller/clientes.php', 1) . '?id=' . $veh->fk_soc;
										$veh_url = dol_buildpath('/taller/vehiculos.php', 1) . '?id=' . $veh->rowid;
										?>
										<tr>
											<td style="font-weight: 700; color: #0f172a; letter-spacing: 0.02em;">
												<a href="<?php echo $veh_url; ?>" style="color: inherit; text-decoration: none;"
													title="Ver vehículo">
													<?php echo dol_escape_htmltag($veh->matricula); ?>
												</a>
											</td>
											<td style="color: #1e293b;">
												<?php echo !empty($veh->marca) ? dol_escape_htmltag($veh->marca) : '<span style="color: #94a3b8;">—</span>'; ?>
											</td>
											<td style="color: #1e293b;">
												<?php echo !empty($veh->modelo) ? dol_escape_htmltag($veh->modelo) : '<span style="color: #94a3b8;">—</span>'; ?>
											</td>
											<td>
												<?php if (!empty($veh->cliente_nombre)): ?>
													<a href="<?php echo $client_url; ?>"
														style="color: #1d4ed8; font-weight: 500; text-decoration: none;"
														title="Ver detalles del cliente">
														<?php echo dol_escape_htmltag($veh->cliente_nombre); ?>
													</a>
												<?php else: ?>
													<span style="color: #94a3b8;">Sin asignar</span>
												<?php endif; ?>
											</td>
											<td>
												<div class="tallerpro-actions">
													<a href="<?php echo $veh_url; ?>" class="tallerpro-action-btn"
														title="Ver vehículo">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b"
															stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6">
															</path>
															<polyline points="15 3 21 3 21 9"></polyline>
															<line x1="10" y1="14" x2="21" y2="3"></line>
														</svg>
													</a>
													<button type="button" class="tallerpro-action-btn btn-delete-action"
														title="Eliminar vehículo" data-id="<?php echo (int) $veh->rowid; ?>"
														data-action="delete_vehiculo">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ef4444"
															stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<polyline points="3 6 5 6 21 6"></polyline>
															<path
																d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
															</path>
														</svg>
													</button>
												</div>
											</td>
										</tr>
									<?php endwhile; ?>
								<?php else: ?>
									<tr>
										<td colspan="5" style="text-align: center; color: #94a3b8; padding: 40px 20px;">
											Aún no hay vehículos registrados. Pulsa en <strong>Añadir</strong> para registrar el
											primero.
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</main>
		</div>

		<!-- Modal Nuevo Registro Vehículos -->
		<div class="tallerpro-modal-backdrop" id="tallerVehiculoModal">
			<div class="tallerpro-modal-card">
				<div class="tallerpro-modal-header">
					<h2 class="tallerpro-modal-title" id="vehiculoModalTitle">Nuevo registro de vehículo</h2>
					<button type="button" class="tallerpro-modal-close" id="tallerCloseModalBtn" title="Cerrar">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
							stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<line x1="18" y1="6" x2="6" y2="18"></line>
							<line x1="6" y1="6" x2="18" y2="18"></line>
						</svg>
					</button>
				</div>
				<form class="tallerpro-modal-form" id="tallerVehiculoForm" method="POST"
					action="<?php echo $_SERVER['PHP_SELF']; ?>">
					<input type="hidden" name="token"
						value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
					<input type="hidden" name="action" id="vehiculoFormAction" value="create_vehiculo">
					<input type="hidden" name="vehiculo_id" id="vehiculoFormRowid" value="0">

					<input type="hidden" name="guardar_en_catalogo" id="veh_guardar_en_catalogo" value="0">

					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Matrícula <span class="tallerpro-required">*</span></label>
						<input type="text" name="matricula" id="veh_matricula" class="tallerpro-form-input"
							placeholder="Ej: 1234ABC" required autocomplete="off" style="text-transform: uppercase;" />
					</div>
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Propietario / Cliente <span
								class="tallerpro-required">*</span></label>
						<select name="fk_soc" id="veh_fk_soc" class="tallerpro-form-select" required>
							<option value="">— Selecciona un cliente —</option>
							<?php foreach ($clients_list as $c): ?>
								<option value="<?php echo $c->rowid; ?>"><?php echo dol_escape_htmltag($c->nom); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Marca</label>
						<div class="tallerpro-autocomplete-wrapper">
							<input type="text" name="marca" id="veh_marca" class="tallerpro-form-input"
								placeholder="Ej: BMW, Renault..." autocomplete="off" required />
							<div class="tallerpro-autocomplete-dropdown" id="dropdown_marcas"></div>
						</div>

					</div>
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Modelo</label>
						<div class="tallerpro-autocomplete-wrapper">
							<input type="text" name="modelo" id="veh_modelo" class="tallerpro-form-input" required
								placeholder="Ej: 318 D, Mégane..." autocomplete="off" />
							<div class="tallerpro-autocomplete-dropdown" id="dropdown_modelos"></div>
						</div>
					</div>
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Año</label>
						<input type="text" name="anio" id="veh_anio" class="tallerpro-form-input"
							placeholder="Ej: 2018" />
					</div>
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Color</label>
						<input type="text" name="color" id="veh_color" class="tallerpro-form-input"
							placeholder="Ej: Blanco, Negro..." />
					</div>
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Nº bastidor (VIN)</label>
						<input type="text" name="vin" id="veh_vin" class="tallerpro-form-input"
							placeholder="Ej: WBA..." />
					</div>
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Kilometraje</label>
						<input type="number" name="km" id="veh_km" class="tallerpro-form-input"
							placeholder="Ej: 120000" />
					</div>
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Notas</label>
						<textarea name="notas" id="veh_notas" class="tallerpro-form-textarea" rows="3"
							placeholder="Observaciones o notas sobre el vehículo..."></textarea>
					</div>
					<div class="tallerpro-modal-footer">
						<button type="button" class="tallerpro-btn-cancel" id="tallerCancelModalBtn">Cancelar</button>
						<button type="submit" class="tallerpro-btn-save" id="btnGuardarVehiculo">Guardar</button>
					</div>
				</form>
			</div>
		</div>

	</div>



	<!-- Modal Confirmación de Eliminación -->
	<div class="tallerpro-modal-backdrop" id="deleteConfirmModal">
		<div class="tallerpro-modal-card" style="max-width: 440px; padding: 28px 24px; text-align: center;">
			<div
				style="width: 52px; height: 52px; border-radius: 50%; background-color: #fee2e2; color: #ef4444; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
				<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round">
					<polyline points="3 6 5 6 21 6"></polyline>
					<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
					<line x1="10" y1="11" x2="10" y2="17"></line>
					<line x1="14" y1="11" x2="14" y2="17"></line>
				</svg>
			</div>
			<h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 24px 0;">¿Estás seguro de que
				deseas borrar?</h3>

			<div style="display: flex; gap: 12px; justify-content: center;">
				<button type="button" id="btnCancelDelete"
					style="flex: 1; padding: 10px 16px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px;">
					No
				</button>
				<button type="button" id="btnConfirmDelete"
					style="flex: 1; padding: 10px 16px; border: none; background: #ef4444; color: #ffffff; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);">
					Sí
				</button>
			</div>
		</div>
	</div>


	<!-- Modal Inteligente de Catálogo, nuevos modelo para uso a futuro-->
	<div class="tallerpro-modal-backdrop" id="catalogoConfirmModal" style="z-index: 1000000;">
		<div class="tallerpro-modal-card" style="max-width: 440px; padding: 26px 24px; text-align: center;">
			<div id="catalogoModalIcon"
				style="width: 50px; height: 50px; border-radius: 50%; background-color: #eff6ff; color: #2563eb; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
					stroke-linecap="round" stroke-linejoin="round">
					<circle cx="12" cy="12" r="10"></circle>
					<line x1="12" y1="16" x2="12" y2="12"></line>
					<line x1="12" y1="8" x2="12.01" y2="8"></line>
				</svg>
			</div>

			<h3 id="catalogoModalTitle" style="font-size: 17px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">
				Nuevo modelo detectado</h3>
			<p id="catalogoModalDesc" style="font-size: 13.5px; color: #64748b; margin: 0 0 22px 0; line-height: 1.5;">
			</p>

			<div style="display: flex; gap: 10px; justify-content: center;">
				<button type="button" id="btnCatalogoOpcion2"
					style="flex: 1; padding: 10px 14px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 13.5px;">
					Solo registrar
				</button>
				<button type="button" id="btnCatalogoOpcion1"
					style="flex: 1; padding: 10px 14px; border: none; background: #2563eb; color: #ffffff; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 13.5px; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.25);">
					Guardar en catálogo
				</button>
			</div>
		</div>
	</div>

	<!-- Formulario oculto para borrado seguro POST con CSRF Token -->
	<form id="tallerGlobalDeleteForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" style="display: none;">
		<input type="hidden" name="token"
			value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
		<input type="hidden" name="action" id="globalDeleteAction" value="">
		<input type="hidden" name="rowid" id="globalDeleteRowid" value="">
		<input type="hidden" name="socid" id="globalDeleteSocid" value="">
	</form>

	<!-- Modal Nuevo Documento para Vehículo -->
	<div class="tallerpro-modal-backdrop" id="tallerDocModal">
		<div class="tallerpro-modal-card" style="max-width: 480px; padding: 26px 28px;">
			<div class="tallerpro-modal-header" style="margin-bottom: 20px;">
				<h2 class="tallerpro-modal-title" style="font-size: 18px; font-weight: 700; color: #0f172a;">Nuevo
					documento</h2>
				<button type="button" class="tallerpro-modal-close" id="tallerCloseDocModalBtn" onclick="document.getElementById('tallerDocModal').classList.remove('active');" title="Cerrar">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>

			<form class="tallerpro-modal-form" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>"
				enctype="multipart/form-data" id="tallerDocForm">
				<input type="hidden" name="token"
					value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
				<input type="hidden" name="action" value="add_documento">
				<input type="hidden" name="fk_vehiculo"
					value="<?php echo !empty($veh_detail) ? (int) $veh_detail->rowid : 0; ?>">

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Nombre <span class="tallerpro-required">*</span></label>
					<input type="text" name="nombre" id="doc_nombre" class="tallerpro-form-input" required
						autocomplete="off" />
				</div>

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Tipo</label>
					<input type="text" name="tipo" id="doc_tipo" class="tallerpro-form-input"
						placeholder="Permiso, ITV, ficha técnica..." autocomplete="off" />
				</div>

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Archivo (PDF o imagen)</label>
					<input type="file" name="archivo" id="doc_archivo" class="tallerpro-form-input"
						accept=".pdf, image/jpeg, image/png, image/webp, image/gif" style="padding: 7px 12px;" />
				</div>

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">o URL / enlace</label>
					<input type="url" name="url" id="doc_url" class="tallerpro-form-input" placeholder="https://..."
						autocomplete="off" />
				</div>

				<div class="tallerpro-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; padding-top: 0; border-top: none;">
					<button type="button" class="tallerpro-btn-cancel" onclick="document.getElementById('tallerDocModal').classList.remove('active');">Cancelar</button>
					<button type="submit" class="tallerpro-btn-save" id="btnGuardarDoc">Guardar</button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php
// End of page
llxFooter();
$db->close();
