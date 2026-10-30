<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       taller/facturas.php
 *  \ingroup    taller
 *  \brief      Facturas list view - TallerPro view
 */

if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}

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

$facture_enabled = isModEnabled('facture');
if (!$facture_enabled && !empty($action) && in_array($action, array('create_factura', 'add_factura', 'update_factura', 'delete_factura', 'add_factura_linea', 'delete_factura_linea', 'save_factura_totales'))) {
	setEventMessages("El módulo de Facturación nativo de Dolibarr está desactivado. Actívelo en Configuración -> Módulos para gestionar facturas.", null, 'errors');
	header("Location: " . $_SERVER['PHP_SELF']);
	exit;
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

$table_vehiculo = MAIN_DB_PREFIX . "taller_vehiculo";
$table_soc = MAIN_DB_PREFIX . "societe";

// Ensure extrafield fk_vehiculo exists on facture_extrafields
$sql_ef_check = "SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "facture_extrafields LIKE 'fk_vehiculo'";
$res_ef_check = $db->query($sql_ef_check);
if ($res_ef_check && $db->num_rows($res_ef_check) == 0) {
	$db->query("ALTER TABLE " . MAIN_DB_PREFIX . "facture_extrafields ADD COLUMN fk_vehiculo INT DEFAULT NULL");
}

// Función para obtener la siguiente referencia prevista de factura en Dolibarr
if (!function_exists('getNextFacturaNumero')) {
	function getNextFacturaNumero($db, $year_or_date = '', $entity = 1) {
		global $mysoc;
		$fac_static = new Facture($db);
		$next = $fac_static->getNextNumRef($mysoc);
		return !empty($next) ? $next : '(PROV)';
	}
}

$action = GETPOST('action', 'alpha');
if (empty($action) && !empty($_POST['action'])) {
	$action = $_POST['action'];
}

// AJAX: Obtener siguiente número de factura automático
if ($action == 'get_next_factura_numero') {
	$next_num = getNextFacturaNumero($db);
	header('Content-Type: application/json');
	echo json_encode(array('status' => 'ok', 'numero' => $next_num));
	exit;
}

// Acción: Crear Factura Nativa en Dolibarr
if ($action == 'create_factura' || $action == 'add_factura') {
	$fecha = trim(GETPOST('fecha', 'alpha'));
	if (empty($fecha) && !empty($_POST['fecha'])) {
		$fecha = trim($_POST['fecha']);
	}
	if (empty($fecha)) {
		$fecha = date('Y-m-d');
	}

	$total_raw = GETPOST('total', 'alpha');
	if ($total_raw === '' && isset($_POST['total'])) {
		$total_raw = $_POST['total'];
	}
	$total = (float) str_replace(',', '.', $total_raw);

	$fk_soc = GETPOSTINT('fk_soc');
	if (empty($fk_soc) && !empty($_POST['fk_soc'])) {
		$fk_soc = (int) $_POST['fk_soc'];
	}

	$fk_vehiculo = GETPOSTINT('fk_vehiculo');
	if (empty($fk_vehiculo) && !empty($_POST['fk_vehiculo'])) {
		$fk_vehiculo = (int) $_POST['fk_vehiculo'];
	}

	$estado = trim(GETPOST('estado', 'alphanohtml'));
	if (empty($estado) && !empty($_POST['estado'])) {
		$estado = trim($_POST['estado']);
	}
	if (empty($estado) || ($estado != 'Pagada' && $estado != 'Pendiente')) {
		$estado = 'Pendiente';
	}

	$notas = trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) {
		$notas = trim($_POST['notas']);
	}

	if ($fk_soc <= 0) {
		setEventMessages("Debe seleccionar un cliente para emitir la factura", null, 'errors');
	} else {
		$fac = new Facture($db);
		$fac->socid = (int) $fk_soc;
		$fac->date = !empty($fecha) ? dol_stringtotime($fecha) : dol_now();
		$fac->type = Facture::TYPE_STANDARD;
		$fac->note_public = $notas;
		$fac->array_options['options_fk_vehiculo'] = ($fk_vehiculo > 0 ? (int) $fk_vehiculo : null);

		$fac_id = $fac->create($user);
		if ($fac_id > 0) {
			if ($total > 0) {
				$tva_tx = 21.0;
				$subprice = round($total / (1 + ($tva_tx / 100)), 2);
				$fac->addline("Servicios de taller", $subprice, 1, $tva_tx, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1);
			}
			// En TallerPro toda factura se valida automáticamente (asignando número oficial)
			$fac->validate($user);
			if ($estado == 'Pagada') {
				$fac->setPaid($user);
			}
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $fac_id);
			exit;
		} else {
			setEventMessages("Error al crear la factura: " . $fac->error . " " . implode(' ', $fac->errors), null, 'errors');
		}
	}
}

// Acción: Actualizar Factura
if ($action == 'update_factura' || $action == 'edit_factura') {
	$rowid = GETPOSTINT('rowid');
	if (empty($rowid) && !empty($_POST['rowid'])) {
		$rowid = (int) $_POST['rowid'];
	}

	$fecha = trim(GETPOST('fecha', 'alpha'));
	if (empty($fecha) && !empty($_POST['fecha'])) {
		$fecha = trim($_POST['fecha']);
	}

	$fk_soc = GETPOSTINT('fk_soc');
	if (empty($fk_soc) && !empty($_POST['fk_soc'])) {
		$fk_soc = (int) $_POST['fk_soc'];
	}

	$fk_vehiculo = GETPOSTINT('fk_vehiculo');
	if (empty($fk_vehiculo) && !empty($_POST['fk_vehiculo'])) {
		$fk_vehiculo = (int) $_POST['fk_vehiculo'];
	}

	$estado = trim(GETPOST('estado', 'alphanohtml'));
	if (empty($estado) && !empty($_POST['estado'])) {
		$estado = trim($_POST['estado']);
	}

	$notas = trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) {
		$notas = trim($_POST['notas']);
	}

	if ($rowid <= 0) {
		setEventMessages("Identificador de factura no válido", null, 'errors');
	} else {
		$fac = new Facture($db);
		if ($fac->fetch($rowid) > 0) {
			if (!empty($fk_soc)) $fac->socid = $fk_soc;
			if (!empty($fecha)) $fac->date = dol_stringtotime($fecha);
			$fac->note_public = $notas;
			$fac->array_options['options_fk_vehiculo'] = ($fk_vehiculo > 0 ? (int) $fk_vehiculo : null);

			$res = $fac->update($user);
			if ($res >= 0) {
				if ($fac->statut == Facture::STATUS_DRAFT) {
					$fac->validate($user);
				}
				if ($estado == 'Pagada' && $fac->paye == 0) {
					$fac->setPaid($user);
				} elseif ($estado == 'Pendiente' && $fac->paye == 1) {
					$fac->setUnpaid($user);
				}
				setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
				header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $rowid);
				exit;
			} else {
				setEventMessages("Error al actualizar la factura: " . $fac->error, null, 'errors');
			}
		} else {
			setEventMessages("No se encontró la factura a modificar", null, 'errors');
		}
	}
}

// Acción: Eliminar Factura
if ($action == 'delete_factura' || $action == 'delete') {
	$rowid = GETPOSTINT('rowid');
	if ($rowid > 0) {
		$fac = new Facture($db);
		if ($fac->fetch($rowid) > 0) {
			$res = $fac->delete($user);
			if ($res > 0) {
				setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
				header("Location: " . $_SERVER['PHP_SELF']);
				exit;
			} else {
				setEventMessages("Error al eliminar la factura: " . $fac->error, null, 'errors');
			}
		}
	}
}

// Generar documento PDF de la factura según diseño de referencia
if ($action == 'pdf' || $action == 'download_pdf') {
	$fac_id = GETPOSTINT('id');
	if (empty($fac_id) && !empty($_GET['id'])) {
		$fac_id = (int) $_GET['id'];
	}
	if (empty($fac_id)) {
		$fac_id = GETPOSTINT('fk_factura');
	}
	if ($fac_id > 0) {
		require_once __DIR__ . '/lib/pdf_factura.lib.php';
		$download = GETPOSTINT('download');
		$mode = ($download == 1) ? 'D' : 'I';
		tallerpro_generate_factura_pdf($db, $conf, $user, $fac_id, $mode);
		exit;
	}
}

// AJAX: Obtener líneas y datos completos de una factura
if ($action == 'get_factura_lineas') {
	$fk_factura = GETPOSTINT('fk_factura');
	$out = array('materiales' => array(), 'reparaciones' => array(), 'factura' => null);
	if ($fk_factura > 0) {
		$fac = new Facture($db);
		if ($fac->fetch($fk_factura) > 0) {
			$fac->fetch_lines();
			$estado_label = ($fac->paye == 1 || $fac->statut == Facture::STATUS_CLOSED) ? 'Pagada' : 'Pendiente';
			$out['factura'] = (object) array(
				'rowid' => (int) $fac->id,
				'numero_factura' => $fac->ref,
				'total' => (float) $fac->total_ttc,
				'total_ht' => (float) $fac->total_ht,
				'total_tva' => (float) $fac->total_tva,
				'descuento_global' => (float) $fac->remise_percent,
				'iva_porcentaje' => 21.0,
				'estado' => $estado_label
			);

			foreach ($fac->lines as $line) {
				$item = array(
					'rowid' => (int) $line->id,
					'tipo' => ($line->product_type == 1 ? 'reparacion' : 'material'),
					'fk_material' => (int) $line->fk_product,
					'codigo' => !empty($line->ref_ext) ? $line->ref_ext : (!empty($line->product_ref) ? $line->product_ref : (!empty($line->ref) ? $line->ref : '')),
					'descripcion' => !empty($line->desc) ? $line->desc : (!empty($line->label) ? $line->label : 'Sin descripción'),
					'cantidad' => (float) $line->qty,
					'precio' => (float) $line->subprice,
					'descuento' => (float) $line->remise_percent,
					'total_neto' => (float) $line->total_ht,
					'notas' => ''
				);
				if ($line->product_type == 1) {
					$out['reparaciones'][] = $item;
				} else {
					$out['materiales'][] = $item;
				}
			}
		}
	}
	header('Content-Type: application/json');
	echo json_encode($out);
	exit;
}

// AJAX: Añadir línea (Material o Reparación)
if ($action == 'add_factura_linea') {
	$fk_factura = GETPOSTINT('fk_factura');
	if (empty($fk_factura) && !empty($_POST['fk_factura'])) {
		$fk_factura = (int) $_POST['fk_factura'];
	}

	$tipo = trim(GETPOST('tipo', 'alpha'));
	if (empty($tipo) && !empty($_POST['tipo'])) {
		$tipo = trim($_POST['tipo']);
	}
	if (empty($tipo)) {
		$tipo = 'material';
	}

	$fk_material = GETPOSTINT('fk_material');
	if (empty($fk_material) && !empty($_POST['fk_material'])) {
		$fk_material = (int) $_POST['fk_material'];
	}

	$codigo = trim(GETPOST('codigo', 'alphanohtml'));
	if (empty($codigo) && !empty($_POST['codigo'])) {
		$codigo = trim($_POST['codigo']);
	}

	$descripcion = trim(GETPOST('descripcion', 'alphanohtml'));
	if (empty($descripcion) && !empty($_POST['descripcion'])) {
		$descripcion = trim($_POST['descripcion']);
	}

	$cantidad_raw = GETPOST('cantidad', 'alpha');
	if ($cantidad_raw === '' && isset($_POST['cantidad'])) {
		$cantidad_raw = $_POST['cantidad'];
	}
	$cantidad = (float) str_replace(',', '.', $cantidad_raw);
	if ($cantidad <= 0) $cantidad = 1.0;

	$precio_raw = GETPOST('precio', 'alpha');
	if ($precio_raw === '' && isset($_POST['precio'])) {
		$precio_raw = $_POST['precio'];
	}
	$precio = (float) str_replace(',', '.', $precio_raw);

	$descuento_raw = GETPOST('descuento', 'alpha');
	if ($descuento_raw === '' && isset($_POST['descuento'])) {
		$descuento_raw = $_POST['descuento'];
	}
	$descuento = (float) str_replace(',', '.', $descuento_raw);

	$total_neto = $cantidad * $precio * (1 - ($descuento / 100));
	if ($total_neto < 0) $total_neto = 0;

	$notas = trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) {
		$notas = trim($_POST['notas']);
	}

	$guardar_catalogo = GETPOSTINT('guardar_catalogo');

	if ($fk_factura <= 0) {
		header('Content-Type: application/json');
		echo json_encode(array('status' => 'error', 'message' => 'Identificador de factura no válido'));
		exit;
	}

	if (empty($descripcion)) {
		header('Content-Type: application/json');
		echo json_encode(array('status' => 'error', 'message' => 'La descripción es obligatoria'));
		exit;
	}

	$fac = new Facture($db);
	if ($fac->fetch($fk_factura) <= 0) {
		header('Content-Type: application/json');
		echo json_encode(array('status' => 'error', 'message' => 'Factura no encontrada'));
		exit;
	}

	$saved_ref = $fac->ref;
	$was_validated = ($fac->status == Facture::STATUS_VALIDATED || $fac->status == Facture::STATUS_CLOSED);
	$was_paid = ($fac->status == Facture::STATUS_CLOSED || $fac->paye == 1);
	if ($was_validated) {
		$fac->setDraft($user);
	}

	// Anti-duplicate protection: avoid inserting the exact same line within 3 seconds
	$fac->fetch_lines();
	foreach ($fac->lines as $existing_line) {
		if ($existing_line->desc == $descripcion && $existing_line->qty == $cantidad && $existing_line->subprice == $precio) {
			if (abs(dol_now() - $existing_line->date_creation) <= 3) {
				if ($was_validated) {
					$fac->validate($user, $saved_ref);
					if ($was_paid) $fac->setPaid($user);
				}
				header('Content-Type: application/json');
				echo json_encode(array(
					'status' => 'ok',
					'id' => (int) $existing_line->id,
					'total_neto' => (float) $existing_line->total_ht,
					'new_total' => (float) $fac->total_ttc,
					'duplicate_prevented' => true
				));
				exit;
			}
		}
	}

	if ($guardar_catalogo && $tipo == 'material') {
		$sql_ins_mat = "INSERT INTO " . MAIN_DB_PREFIX . "taller_material (nombre, ref, precio, date_creation, fk_user_creat, entity, status) VALUES (";
		$sql_ins_mat .= "'" . $db->escape($descripcion) . "', ";
		$sql_ins_mat .= "'" . $db->escape($codigo) . "', ";
		$sql_ins_mat .= $precio . ", ";
		$sql_ins_mat .= "'" . $db->idate(dol_now()) . "', ";
		$sql_ins_mat .= ((int) $user->id) . ", ";
		$sql_ins_mat .= ((int) $conf->entity) . ", 1)";
		$db->query($sql_ins_mat);
	}

	$product_type = ($tipo == 'reparacion') ? 1 : 0;
	$tva_tx = 21.0;
	$line_id = $fac->addline($descripcion, $precio, $cantidad, $tva_tx, 0, 0, 0, $descuento, '', '', 0, 0, 0, 'HT', 0, $product_type, -1, 0, '', 0, 0, null, 0, '', array(), 100, 0, null, 0, $codigo, 0);

	if ($was_validated) {
		$fac->validate($user, $saved_ref);
		if ($was_paid) {
			$fac->setPaid($user);
		}
	}

	if ($line_id > 0) {
		$fac->fetch($fk_factura);
		header('Content-Type: application/json');
		echo json_encode(array(
			'status' => 'ok',
			'id' => (int) $line_id,
			'total_neto' => (float) $total_neto,
			'new_total' => (float) $fac->total_ttc
		));
	} else {
		$err_msg = !empty($fac->error) ? $fac->error : (!empty($fac->errors) ? implode(' ', $fac->errors) : 'Error desconocido');
		header('Content-Type: application/json');
		echo json_encode(array('status' => 'error', 'message' => 'Error al añadir línea en Dolibarr: ' . $err_msg));
	}
	exit;
}

// AJAX: Eliminar línea
if ($action == 'delete_factura_linea') {
	$linea_id = GETPOSTINT('linea_id');
	if (empty($linea_id) && !empty($_POST['linea_id'])) {
		$linea_id = (int) $_POST['linea_id'];
	}
	$fk_factura = GETPOSTINT('fk_factura');
	if (empty($fk_factura) && !empty($_POST['fk_factura'])) {
		$fk_factura = (int) $_POST['fk_factura'];
	}

	if ($fk_factura > 0 && $linea_id > 0) {
		$fac = new Facture($db);
		if ($fac->fetch($fk_factura) > 0) {
			$saved_ref = $fac->ref;
			$was_validated = ($fac->status == Facture::STATUS_VALIDATED || $fac->status == Facture::STATUS_CLOSED);
			$was_paid = ($fac->status == Facture::STATUS_CLOSED || $fac->paye == 1);
			if ($was_validated) {
				$fac->setDraft($user);
			}
			$fac->deleteline($linea_id, $fac->id);
			if ($was_validated) {
				$fac->validate($user, $saved_ref);
				if ($was_paid) {
					$fac->setPaid($user);
				}
			}
			$fac->fetch($fk_factura);
			header('Content-Type: application/json');
			echo json_encode(array('status' => 'ok', 'new_total' => (float) $fac->total_ttc));
			exit;
		}
	}
	header('Content-Type: application/json');
	echo json_encode(array('status' => 'ok'));
	exit;
}

// AJAX: Guardar totales de factura
if ($action == 'save_factura_totales') {
	$fk_factura = GETPOSTINT('fk_factura');
	if (empty($fk_factura) && !empty($_POST['fk_factura'])) {
		$fk_factura = (int) $_POST['fk_factura'];
	}

	$descuento_global_raw = GETPOST('descuento_global', 'alpha');
	if ($descuento_global_raw === '' && isset($_POST['descuento_global'])) {
		$descuento_global_raw = $_POST['descuento_global'];
	}
	$descuento_global = (float) str_replace(',', '.', $descuento_global_raw);

	if ($fk_factura > 0) {
		$fac = new Facture($db);
		if ($fac->fetch($fk_factura) > 0) {
			$saved_ref = $fac->ref;
			$was_validated = ($fac->status == Facture::STATUS_VALIDATED || $fac->status == Facture::STATUS_CLOSED);
			$was_paid = ($fac->status == Facture::STATUS_CLOSED || $fac->paye == 1);
			if ($was_validated) {
				$fac->setDraft($user);
			}
			if ($descuento_global >= 0) {
				$fac->remise_percent = $descuento_global;
				$fac->update($user);
			}
			$fac->update_price();
			if ($was_validated) {
				$fac->validate($user, $saved_ref);
				if ($was_paid) {
					$fac->setPaid($user);
				}
			}
			$fac->fetch($fk_factura);
			header('Content-Type: application/json');
			echo json_encode(array('status' => 'ok', 'new_total' => (float) $fac->total_ttc));
			exit;
		}
	}
	header('Content-Type: application/json');
	echo json_encode(array('status' => 'ok'));
	exit;
}

// Consultar clientes para el desplegable
$clients_list = array();
$sql_c = "SELECT rowid, nom FROM " . $table_soc . " WHERE status = 1 AND entity IN (" . getEntity('societe') . ") ORDER BY nom ASC";
$res_c = $db->query($sql_c);
if ($res_c) {
	while ($c = $db->fetch_object($res_c)) {
		$clients_list[] = $c;
	}
}

// Consultar vehículos para el desplegable
$vehicles_list = array();
$sql_v = "SELECT rowid, matricula, marca, modelo FROM " . $table_vehiculo . " WHERE status = 1 AND entity IN (" . getEntity('taller_vehiculo') . ") ORDER BY matricula ASC";
$res_v = $db->query($sql_v);
if ($res_v) {
	while ($v = $db->fetch_object($res_v)) {
		$vehicles_list[] = $v;
	}
}

// Consultar catálogo de materiales
$materials_catalog = array();
$sql_mc = "SELECT rowid, ref, nombre, precio FROM " . MAIN_DB_PREFIX . "taller_material WHERE status = 1 ORDER BY nombre ASC";
$res_mc = $db->query($sql_mc);
if ($res_mc) {
	while ($mc = $db->fetch_object($res_mc)) {
		$materials_catalog[] = $mc;
	}
}

// Consultar facturas nativas de Dolibarr con JOIN a cliente y vehículo
$facturas_list = array();
$sql_f = "SELECT f.rowid, f.ref as numero_factura, f.datef as fecha, f.fk_soc, f.total_ttc as total, f.total_ht, f.total_tva, ";
$sql_f .= "f.fk_statut, f.paye, COALESCE(f.remise_percent, 0) as descuento_global, 21.0 as iva_porcentaje, f.note_public as notas, ";
$sql_f .= "(CASE WHEN (f.paye = 1 OR f.fk_statut = 2) THEN 'Pagada' ELSE 'Pendiente' END) as estado, ";
$sql_f .= "fe.fk_vehiculo, ";
$sql_f .= "s.nom as cliente_nombre, ";
$sql_f .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo ";
$sql_f .= "FROM " . MAIN_DB_PREFIX . "facture as f ";
$sql_f .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON f.fk_soc = s.rowid ";
$sql_f .= "LEFT JOIN " . MAIN_DB_PREFIX . "facture_extrafields as fe ON fe.fk_object = f.rowid ";
$sql_f .= "LEFT JOIN " . MAIN_DB_PREFIX . "taller_vehiculo as v ON fe.fk_vehiculo = v.rowid ";
$sql_f .= "WHERE f.entity IN (" . getEntity('facture') . ") ";
$sql_f .= "ORDER BY f.datef DESC, f.rowid DESC";
$res_f = $db->query($sql_f);
if ($res_f) {
	while ($f_obj = $db->fetch_object($res_f)) {
		$facturas_list[] = $f_obj;
	}
}
$total_facturas = count($facturas_list);

// Siguiente número previsto
$next_factura_numero = getNextFacturaNumero($db);

// Vista individual de una factura (Documental)
$view_id = GETPOSTINT('id');
if (empty($view_id) && !empty($_GET['id'])) {
	$view_id = (int) $_GET['id'];
}
$factura_single = null;
$single_materiales = array();
$single_reparaciones = array();
$single_subtotal = 0.0;
$single_desc = 0.0;
$single_base = 0.0;
$single_iva_pct = 21.0;
$single_iva_amt = 0.0;
$single_total = 0.0;

if ($view_id > 0) {
	$sql_single = "SELECT f.rowid, f.ref as numero_factura, f.datef as fecha, f.fk_soc, f.total_ttc as total, f.total_ht, f.total_tva, ";
	$sql_single .= "f.fk_statut, f.paye, COALESCE(f.remise_percent, 0) as descuento_global, 21.0 as iva_porcentaje, f.note_public as notas, ";
	$sql_single .= "(CASE WHEN (f.paye = 1 OR f.fk_statut = 2) THEN 'Pagada' ELSE 'Pendiente' END) as estado, ";
	$sql_single .= "fe.fk_vehiculo, ";
	$sql_single .= "s.nom as cliente_nombre, COALESCE(NULLIF(s.siren, ''), NULLIF(s.siret, ''), s.tva_intra) as cliente_cif, COALESCE(NULLIF(s.town, ''), s.address) as cliente_ciudad, s.phone as cliente_telefono, s.email as cliente_email, ";
	$sql_single .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo, v.anio as vehiculo_anio ";
	$sql_single .= "FROM " . MAIN_DB_PREFIX . "facture as f ";
	$sql_single .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON f.fk_soc = s.rowid ";
	$sql_single .= "LEFT JOIN " . MAIN_DB_PREFIX . "facture_extrafields as fe ON fe.fk_object = f.rowid ";
	$sql_single .= "LEFT JOIN " . MAIN_DB_PREFIX . "taller_vehiculo as v ON fe.fk_vehiculo = v.rowid ";
	$sql_single .= "WHERE f.rowid = " . ((int) $view_id);
	$res_single = $db->query($sql_single);
	if ($res_single && ($fs = $db->fetch_object($res_single))) {
		$factura_single = $fs;
		$tot_mat = 0.0;
		$tot_rep = 0.0;
		$sql_single_lines = "SELECT fd.rowid, (CASE WHEN fd.product_type = 1 THEN 'reparacion' ELSE 'material' END) as tipo, ";
		$sql_single_lines .= "COALESCE(NULLIF(fd.ref_ext, ''), p.ref) as codigo, ";
		$sql_single_lines .= "(CASE WHEN fd.description IS NOT NULL AND fd.description != '' THEN fd.description ELSE fd.label END) as descripcion, ";
		$sql_single_lines .= "fd.qty as cantidad, fd.subprice as precio, fd.remise_percent as descuento, fd.total_ht as total_neto ";
		$sql_single_lines .= "FROM " . MAIN_DB_PREFIX . "facturedet as fd ";
		$sql_single_lines .= "LEFT JOIN " . MAIN_DB_PREFIX . "product as p ON fd.fk_product = p.rowid ";
		$sql_single_lines .= "WHERE fd.fk_facture = " . ((int) $view_id) . " ORDER BY fd.rang ASC, fd.rowid ASC";
		$res_lin = $db->query($sql_single_lines);
		if ($res_lin) {
			while ($l = $db->fetch_object($res_lin)) {
				if ($l->tipo == 'material') {
					$single_materiales[] = $l;
					$tot_mat += (float) $l->total_neto;
				} else {
					$single_reparaciones[] = $l;
					$tot_rep += (float) $l->total_neto;
				}
			}
		}
		$single_subtotal = $tot_mat + $tot_rep;
		$single_desc = (float) $factura_single->descuento_global;
		$single_base = max(0, $single_subtotal - $single_desc);
		$single_iva_pct = 21.0;
		$single_iva_amt = (float) $factura_single->total_tva;
		$single_total = (float) $factura_single->total;
		if ($single_total <= 0 && ($single_base > 0 || $single_desc > 0)) {
			$single_total = $single_base + $single_iva_amt;
		}
	}
}

$css_file = __DIR__ . '/css/tallerpro.css';
$js_file = __DIR__ . '/js/tallerpro.js';
$css_version = (file_exists($css_file) ? filemtime($css_file) : dol_now()) . '_' . dol_now();
$js_version = (file_exists($js_file) ? filemtime($js_file) : dol_now()) . '_' . dol_now();

$css_url = dol_buildpath('/taller/css/tallerpro.css', 1) . '?v=' . $css_version;
$js_url = dol_buildpath('/taller/js/tallerpro.js', 1) . '?v=' . $js_version;

$morehead = '<link rel="stylesheet" type="text/css" href="'.$css_url.'">'."\n";
$morehead .= '<script src="'.$js_url.'"></script>'."\n";

// Page Header
llxHeader('', 'Facturas - TallerPro', '', '', 0, 0, array(), array(), $morehead, 'mod-taller page-tallerpro');

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
						<a href="<?php echo $facturas_url; ?>" class="tallerpro-nav-item active">
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
				<?php if ($view_id > 0 && $factura_single): ?>
					<style>
						.tallerpro-invoice-view-wrapper {
							max-width: 1080px;
							margin: 0 auto;
							display: flex;
							flex-direction: column;
							gap: 20px;
						}
						.tallerpro-invoice-view-topbar {
							display: flex;
							align-items: center;
							justify-content: space-between;
							gap: 16px;
						}
						.tallerpro-back-link {
							display: inline-flex;
							align-items: center;
							gap: 8px;
							color: #475569;
							font-size: 13.5px;
							font-weight: 500;
							text-decoration: none;
							transition: color 0.15s ease;
						}
						.tallerpro-back-link:hover {
							color: #1d4ed8;
						}
						.tallerpro-invoice-view-top-actions {
							display: flex;
							align-items: center;
							gap: 12px;
						}
						.tallerpro-btn-edit-lines {
							display: inline-flex;
							align-items: center;
							gap: 6px;
							background: #ffffff;
							color: #334155;
							border: 1px solid #e2e8f0;
							font-size: 13px;
							font-weight: 500;
							padding: 8px 16px;
							border-radius: 8px;
							cursor: pointer;
							box-shadow: 0 1px 2px rgba(0,0,0,0.03);
							transition: background-color 0.15s ease;
						}
						.tallerpro-btn-edit-lines:hover {
							background-color: #f8fafc;
							border-color: #cbd5e1;
						}
						.tallerpro-invoice-badge-status {
							display: inline-flex !important;
							align-items: center !important;
							justify-content: center !important;
							padding: 4px 14px !important;
							border-radius: 9999px !important;
							font-size: 12px !important;
							font-weight: 500 !important;
							line-height: 1.3 !important;
						}
						.tallerpro-invoice-doc-header {
							display: flex;
							flex-direction: column;
							gap: 4px;
						}
						.tallerpro-invoice-doc-label {
							display: inline-flex;
							align-items: center;
							gap: 5px;
							font-size: 11px;
							font-weight: 700;
							color: #64748b;
							letter-spacing: 0.05em;
							text-transform: uppercase;
						}
						.tallerpro-invoice-doc-num {
							font-size: 28px;
							font-weight: 700;
							color: #0f172a;
							margin: 2px 0 0 0;
							letter-spacing: -0.02em;
							line-height: 1.1;
						}
						.tallerpro-invoice-doc-date {
							font-size: 13.5px;
							color: #64748b;
						}
						.tallerpro-invoice-cards-grid {
							display: grid;
							grid-template-columns: 1fr 1fr;
							gap: 16px;
						}
						.tallerpro-invoice-info-card {
							background: #ffffff !important;
							border: 1px solid #e2e8f0 !important;
							border-radius: 12px !important;
							padding: 20px 24px !important;
							box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;
							display: flex !important;
							flex-direction: column !important;
							gap: 4px !important;
						}
						.tallerpro-info-card-tag {
							font-size: 11px !important;
							font-weight: 700 !important;
							color: #64748b !important;
							letter-spacing: 0.05em !important;
							text-transform: uppercase !important;
						}
						.tallerpro-info-card-title {
							font-size: 15px !important;
							font-weight: 700 !important;
							color: #0f172a !important;
							margin-top: 2px !important;
						}
						.tallerpro-info-card-line {
							font-size: 13px !important;
							color: #64748b !important;
						}
						.tallerpro-info-card-sub {
							font-size: 12.5px !important;
							color: #64748b !important;
						}
						.tallerpro-invoice-totals-card-view {
							background: #ffffff !important;
							border: 1px solid #e2e8f0 !important;
							border-radius: 12px !important;
							padding: 22px 28px !important;
							box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;
							display: flex !important;
							justify-content: flex-end !important;
							width: 100% !important;
							box-sizing: border-box !important;
							margin-top: 16px !important;
						}
						.tallerpro-totals-summary-box {
							width: 320px !important;
							max-width: 100% !important;
							margin-left: auto !important;
							margin-right: 0 !important;
							display: flex !important;
							flex-direction: column !important;
							gap: 8px !important;
						}
						.tallerpro-totals-row {
							display: flex !important;
							justify-content: space-between !important;
							align-items: center !important;
							width: 100% !important;
							font-size: 13.5px !important;
							color: #64748b !important;
						}
						.tallerpro-totals-row span:first-child {
							color: #64748b !important;
							font-size: 13.5px !important;
							font-weight: 400 !important;
							text-align: left !important;
						}
						.tallerpro-totals-row span:last-child {
							color: #0f172a !important;
							font-size: 13.5px !important;
							font-weight: 500 !important;
							text-align: right !important;
						}
						.tallerpro-totals-divider {
							height: 1px !important;
							background-color: #f1f5f9 !important;
							margin: 4px 0 !important;
							border: none !important;
							width: 100% !important;
						}
						.tallerpro-totals-final {
							margin-top: 3px !important;
							display: flex !important;
							justify-content: space-between !important;
							align-items: center !important;
							width: 100% !important;
						}
						.tallerpro-totals-final span:first-child {
							font-size: 15px !important;
							font-weight: 700 !important;
							color: #0f172a !important;
							letter-spacing: 0.02em !important;
						}
						.tallerpro-totals-final span:last-child {
							font-size: 16px !important;
							font-weight: 700 !important;
							color: #0f172a !important;
							text-align: right !important;
						}
						.tallerpro-invoice-section-block {
							display: flex;
							flex-direction: column;
							gap: 8px;
							width: 100%;
							margin-top: 4px;
						}
						.tallerpro-invoice-section-heading {
							display: flex;
							align-items: center;
							gap: 8px;
							font-size: 15px;
							font-weight: 600;
							color: #0f172a;
						}
						.tallerpro-invoice-table-card {
							background: #ffffff;
							border: 1px solid #e2e8f0;
							border-radius: 12px;
							overflow: hidden;
							box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
						}
						.tallerpro-invoice-table {
							width: 100%;
							border-collapse: collapse;
							text-align: left;
						}
						.tallerpro-invoice-table th {
							background-color: #ffffff;
							padding: 12px 18px;
							font-size: 12.5px;
							font-weight: 500;
							color: #64748b;
							border-bottom: 1px solid #e2e8f0;
							white-space: nowrap;
						}
						.tallerpro-invoice-table td {
							padding: 14px 18px;
							font-size: 13.5px;
							color: #334155;
							border-bottom: 1px solid #f1f5f9;
							vertical-align: middle;
						}
						.tallerpro-invoice-table tr:last-child td {
							border-bottom: none;
						}
						.tallerpro-invoice-table tr:hover td {
							background-color: #fafbfd;
						}
					</style>

					<!-- Vista Detalle Completa de Factura (según diseño de referencia) -->
					<div class="tallerpro-invoice-view-wrapper">
						<!-- Top Action Bar -->
						<div class="tallerpro-invoice-view-topbar">
							<a href="<?php echo $facturas_url; ?>" class="tallerpro-back-link">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<line x1="19" y1="12" x2="5" y2="12"></line>
									<polyline points="12 19 5 12 12 5"></polyline>
								</svg>
								<span>Volver a facturas</span>
							</a>
							<div class="tallerpro-invoice-view-top-actions">
								<button type="button" class="tallerpro-btn-edit-lines btn-detalle-factura"
									data-id="<?php echo (int) $factura_single->rowid; ?>"
									data-numero="<?php echo dol_escape_htmltag($factura_single->numero_factura); ?>"
									style="display: inline-flex !important; align-items: center !important; gap: 6px !important; background: #ffffff !important; color: #334155 !important; border: 1px solid #e2e8f0 !important; font-size: 13px !important; font-weight: 500 !important; padding: 8px 16px !important; border-radius: 8px !important; cursor: pointer !important;">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
									</svg>
									<span>Editar líneas</span>
								</button>
								<?php
								$is_pagada = (strtolower(trim($factura_single->estado)) == 'pagada');
								$badge_style = $is_pagada
									? 'background-color: #ecfdf5 !important; color: #047857 !important; border: 1px solid #a7f3d0 !important;'
									: 'background-color: #fffbeb !important; color: #b45309 !important; border: 1px solid #fde68a !important;';
								?>
								<span class="tallerpro-invoice-badge-status" style="<?php echo $badge_style; ?>">
									<?php echo dol_escape_htmltag($factura_single->estado ?: 'Pendiente'); ?>
								</span>
								<a href="<?php echo $facturas_url . '?action=pdf&id=' . ((int) $factura_single->rowid); ?>" target="_blank" class="tallerpro-btn-pdf"
									style="display: inline-flex !important; align-items: center !important; gap: 7px !important; background-color: #0f2b5c !important; color: #ffffff !important; font-size: 13px !important; font-weight: 600 !important; padding: 8px 16px !important; border-radius: 8px !important; border: none !important; cursor: pointer !important; text-decoration: none !important; box-shadow: 0 1px 2px rgba(15, 43, 92, 0.2) !important;">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
										<polyline points="7 10 12 15 17 10"></polyline>
										<line x1="12" y1="15" x2="12" y2="3"></line>
									</svg>
									<span>Descargar PDF</span>
								</a>
							</div>
						</div>

						<!-- Document Heading -->
						<div class="tallerpro-invoice-doc-header">
							<div class="tallerpro-invoice-doc-label">
								<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
									<polyline points="14 2 14 8 20 8"></polyline>
									<line x1="16" y1="13" x2="8" y2="13"></line>
									<line x1="16" y1="17" x2="8" y2="17"></line>
									<polyline points="10 9 9 9 8 9"></polyline>
								</svg>
								<span>FACTURA</span>
							</div>
							<h1 class="tallerpro-invoice-doc-num"><?php echo dol_escape_htmltag($factura_single->numero_factura); ?></h1>
							<div class="tallerpro-invoice-doc-date"><?php echo !empty($factura_single->fecha) ? dol_escape_htmltag($factura_single->fecha) : date('Y-m-d'); ?></div>
						</div>

						<!-- 2 Column Cards: Cliente & Vehículo -->
						<div class="tallerpro-invoice-cards-grid">
							<div class="tallerpro-invoice-info-card">
								<div class="tallerpro-info-card-tag">CLIENTE</div>
								<div class="tallerpro-info-card-title"><?php echo dol_escape_htmltag($factura_single->cliente_nombre ?: 'Sin cliente'); ?></div>
								<?php if (!empty($factura_single->cliente_cif)): ?>
									<div class="tallerpro-info-card-line">DNI: <?php echo dol_escape_htmltag($factura_single->cliente_cif); ?></div>
								<?php endif; ?>
								<?php if (!empty($factura_single->cliente_ciudad)): ?>
									<div class="tallerpro-info-card-line"><?php echo dol_escape_htmltag($factura_single->cliente_ciudad); ?></div>
								<?php endif; ?>
								<?php
								$contact_parts = array_filter(array($factura_single->cliente_telefono, $factura_single->cliente_email));
								if (!empty($contact_parts)): ?>
									<div class="tallerpro-info-card-sub"><?php echo dol_escape_htmltag(implode(' · ', $contact_parts)); ?></div>
								<?php endif; ?>
							</div>

							<div class="tallerpro-invoice-info-card">
								<div class="tallerpro-info-card-tag">VEHÍCULO</div>
								<div class="tallerpro-info-card-title"><?php echo dol_escape_htmltag($factura_single->vehiculo_matricula ?: 'Sin vehículo'); ?></div>
								<?php
								$veh_parts = array_filter(array($factura_single->vehiculo_marca, $factura_single->vehiculo_modelo, $factura_single->vehiculo_anio));
								if (!empty($veh_parts)): ?>
									<div class="tallerpro-info-card-line"><?php echo dol_escape_htmltag(implode(' · ', $veh_parts)); ?></div>
								<?php endif; ?>
							</div>
						</div>

						<?php if (!empty($single_materiales)): ?>
						<!-- Sección 1: Materiales (solo si contiene materiales) -->
						<div class="tallerpro-invoice-section-block">
							<div class="tallerpro-invoice-section-heading">
								<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
									<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
									<line x1="12" y1="22.08" x2="12" y2="12"></line>
								</svg>
								<span>Materiales</span>
							</div>
							<div class="tallerpro-invoice-table-card">
								<table class="tallerpro-invoice-table">
									<thead>
										<tr>
											<th style="width: 15%;">Código</th>
											<th style="width: 45%;">Descripción</th>
											<th style="width: 10%; text-align: center;">Cant.</th>
											<th style="width: 12%; text-align: right;">P. unit.</th>
											<th style="width: 8%; text-align: center;">Dto.</th>
											<th style="width: 15%; text-align: right;">Total</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($single_materiales as $mat): ?>
											<tr>
												<td style="color: #64748b;"><?php echo !empty($mat->codigo) ? dol_escape_htmltag($mat->codigo) : '—'; ?></td>
												<td style="font-weight: 500; color: #0f172a;"><?php echo dol_escape_htmltag($mat->descripcion); ?></td>
												<td style="text-align: center; color: #334155;"><?php echo number_format((float) $mat->cantidad, 0); ?></td>
												<td style="text-align: right; color: #334155;"><?php echo number_format((float) $mat->precio, 2, '.', '') . ' €'; ?></td>
												<td style="text-align: center; color: #334155;"><?php echo number_format((float) $mat->descuento, 0) . '%'; ?></td>
												<td style="text-align: right; font-weight: 700; color: #0f172a;"><?php echo number_format((float) $mat->total_neto, 2, '.', '') . ' €'; ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
						<?php endif; ?>

						<?php if (!empty($single_reparaciones)): ?>
						<!-- Sección 2: Reparaciones / Mano de obra (solo si contiene reparaciones) -->
						<div class="tallerpro-invoice-section-block">
							<div class="tallerpro-invoice-section-heading">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
								</svg>
								<span>Reparaciones / Mano de obra</span>
							</div>
							<div class="tallerpro-invoice-table-card">
								<table class="tallerpro-invoice-table">
									<thead>
										<tr>
											<th style="width: 60%;">Descripción</th>
											<th style="width: 15%; text-align: right;">Precio</th>
											<th style="width: 10%; text-align: center;">Dto.</th>
											<th style="width: 15%; text-align: right;">Total</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($single_reparaciones as $rep): ?>
											<tr>
												<td>
													<span style="font-weight: 500; color: #0f172a;"><?php echo dol_escape_htmltag($rep->descripcion); ?></span>
													<?php if (!empty($rep->notas)): ?>
														<div style="font-size: 11.5px; color: #64748b; margin-top: 2px;"><?php echo dol_escape_htmltag($rep->notas); ?></div>
													<?php endif; ?>
												</td>
												<td style="text-align: right; color: #334155;"><?php echo number_format((float) $rep->precio, 2, '.', '') . ' €'; ?></td>
												<td style="text-align: center; color: #334155;"><?php echo number_format((float) $rep->descuento, 0) . '%'; ?></td>
												<td style="text-align: right; font-weight: 700; color: #0f172a;"><?php echo number_format((float) $rep->total_neto, 2, '.', '') . ' €'; ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
						<?php endif; ?>

						<!-- Sección 3: Totales Card -->
						<div class="tallerpro-invoice-totals-card-view" style="background: #ffffff !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; padding: 22px 28px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important; display: flex !important; justify-content: flex-end !important; width: 100% !important; box-sizing: border-box !important; margin-top: 16px !important; margin-bottom: 16px !important;">
							<div class="tallerpro-totals-summary-box" style="width: 320px !important; max-width: 100% !important; margin-left: auto !important; margin-right: 0 !important; display: flex !important; flex-direction: column !important; gap: 8px !important;">
								<div class="tallerpro-totals-row" style="display: flex !important; justify-content: space-between !important; align-items: center !important; font-size: 13.5px !important; color: #64748b !important; width: 100% !important;">
									<span style="color: #64748b !important; font-weight: 400 !important; font-size: 13.5px !important;">Subtotal</span>
									<span style="color: #0f172a !important; font-weight: 500 !important; font-size: 13.5px !important; text-align: right !important;"><?php echo number_format((float) $single_subtotal, 2, '.', '') . ' €'; ?></span>
								</div>
								<div class="tallerpro-totals-row" style="display: flex !important; justify-content: space-between !important; align-items: center !important; font-size: 13.5px !important; color: #64748b !important; width: 100% !important;">
									<span style="color: #64748b !important; font-weight: 400 !important; font-size: 13.5px !important;">Descuento</span>
									<span style="color: #0f172a !important; font-weight: 500 !important; font-size: 13.5px !important; text-align: right !important;">- <?php echo number_format((float) $single_desc, 2, '.', '') . ' €'; ?></span>
								</div>
								<div class="tallerpro-totals-divider" style="height: 1px !important; background-color: #f1f5f9 !important; margin: 4px 0 !important; border: none !important; width: 100% !important;"></div>
								<div class="tallerpro-totals-row" style="display: flex !important; justify-content: space-between !important; align-items: center !important; font-size: 13.5px !important; color: #64748b !important; width: 100% !important;">
									<span style="color: #64748b !important; font-weight: 400 !important; font-size: 13.5px !important;">Base imponible</span>
									<span style="color: #0f172a !important; font-weight: 500 !important; font-size: 13.5px !important; text-align: right !important;"><?php echo number_format((float) $single_base, 2, '.', '') . ' €'; ?></span>
								</div>
								<div class="tallerpro-totals-row" style="display: flex !important; justify-content: space-between !important; align-items: center !important; font-size: 13.5px !important; color: #64748b !important; width: 100% !important;">
									<span style="color: #64748b !important; font-weight: 400 !important; font-size: 13.5px !important;">IVA (<?php echo (int) $single_iva_pct; ?>%)</span>
									<span style="color: #0f172a !important; font-weight: 500 !important; font-size: 13.5px !important; text-align: right !important;"><?php echo number_format((float) $single_iva_amt, 2, '.', '') . ' €'; ?></span>
								</div>
								<div class="tallerpro-totals-divider" style="height: 1px !important; background-color: #f1f5f9 !important; margin: 4px 0 !important; border: none !important; width: 100% !important;"></div>
								<div class="tallerpro-totals-row tallerpro-totals-final" style="display: flex !important; justify-content: space-between !important; align-items: center !important; width: 100% !important; margin-top: 3px !important;">
									<span style="font-size: 15px !important; font-weight: 700 !important; color: #0f172a !important; letter-spacing: 0.02em !important;">TOTAL</span>
									<span style="font-size: 16px !important; font-weight: 700 !important; color: #0f172a !important; text-align: right !important;"><?php echo number_format((float) $single_total, 2, '.', '') . ' €'; ?></span>
								</div>
							</div>
						</div>

						<!-- Sección 4: Notas (visibles en todo momento) -->
						<div class="tallerpro-invoice-info-card" style="background: #ffffff !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; padding: 20px 24px !important; margin-top: 16px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;">
							<div class="tallerpro-info-card-tag" style="font-size: 11px !important; font-weight: 700 !important; color: #64748b !important; letter-spacing: 0.05em !important; text-transform: uppercase !important;">NOTAS</div>
							<div class="tallerpro-info-card-line" style="margin-top: 6px !important; font-size: 13.5px !important; color: #0f172a !important; line-height: 1.5 !important;">
								<?php echo !empty(trim((string) $factura_single->notas)) ? nl2br(dol_escape_htmltag($factura_single->notas)) : '<span style="color: #94a3b8;">Sin notas</span>'; ?>
							</div>
						</div>
					</div>
				<?php else: ?>
					<!-- Header Actions & Title -->
					<div class="tallerpro-header-actions">
						<div class="tallerpro-content-header" style="margin-bottom: 0;">
							<h1 class="tallerpro-page-title"><?php echo $langs->trans("Facturas"); ?></h1>
							<p class="tallerpro-page-subtitle"><?php echo $total_facturas; ?> registro(s)</p>
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

					<?php if (!$facture_enabled): ?>
						<div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 18px 22px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
							<div style="display: flex; align-items: flex-start; gap: 14px;">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;">
									<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
									<line x1="12" y1="9" x2="12" y2="13"></line>
									<line x1="12" y1="17" x2="12.01" y2="17"></line>
								</svg>
								<div>
									<h3 style="margin: 0 0 4px 0; font-size: 14.5px; font-weight: 600; color: #92400e;">Módulo nativo de Facturación de Dolibarr desactivado</h3>
									<p style="margin: 0 0 12px 0; font-size: 13px; color: #b45309; line-height: 1.5;">
										TallerPro requiere el módulo oficial <strong>Facturación y Pagos</strong> para emitir y consultar facturas oficiales.
									</p>
									<a href="<?php echo DOL_URL_ROOT; ?>/admin/modules.php?mainmenu=home" class="tallerpro-btn-primary" style="display: inline-flex; text-decoration: none; padding: 7px 14px; font-size: 12.5px;">
										Activar módulo Facturación en Dolibarr
									</a>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<?php if (empty($facturas_list)): ?>
						<!-- Empty State Card -->
						<div class="tallerpro-empty-card">
							Aún no hay facturas registradas. Añade la primera.
						</div>
					<?php else: ?>
						<!-- Table Card -->
						<div class="tallerpro-table-card">
							<table class="tallerpro-table">
								<thead>
									<tr>
										<th>Nº</th>
										<th>Fecha</th>
										<th>Cliente</th>
										<th>Vehículo</th>
										<th>Total</th>
										<th>Estado</th>
										<th style="width: 220px; text-align: right;"></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($facturas_list as $f): ?>
										<tr data-invoice-id="<?php echo (int) $f->rowid; ?>">
											<td style="color: #1e293b; font-weight: 700;">
												<a href="<?php echo $facturas_url . '?id=' . ((int) $f->rowid); ?>" style="color: #0f172a; text-decoration: none;" title="Ver factura">
													<?php echo dol_escape_htmltag($f->numero_factura); ?>
												</a>
											</td>
											<td style="color: #1e293b;">
												<?php echo !empty($f->fecha) ? dol_escape_htmltag($f->fecha) : '—'; ?>
											</td>
											<td style="color: #1e293b;">
												<?php if (!empty($f->fk_soc) && !empty($f->cliente_nombre)): ?>
													<a href="<?php echo $clientes_url . '?id=' . ((int) $f->fk_soc); ?>" style="color: inherit; text-decoration: none;" title="Ver cliente">
														<?php echo dol_escape_htmltag($f->cliente_nombre); ?>
													</a>
												<?php else: ?>
													<?php echo dol_escape_htmltag($f->cliente_nombre ?: '—'); ?>
												<?php endif; ?>
											</td>
											<td style="color: #1e293b;">
												<?php if (!empty($f->fk_vehiculo) && !empty($f->vehiculo_matricula)): ?>
													<a href="<?php echo $vehiculos_url . '?id=' . ((int) $f->fk_vehiculo); ?>" style="color: inherit; text-decoration: none;" title="Ver vehículo">
														<?php echo dol_escape_htmltag($f->vehiculo_matricula); ?>
													</a>
												<?php else: ?>
													<?php echo dol_escape_htmltag($f->vehiculo_matricula ?: '—'); ?>
												<?php endif; ?>
											</td>
											<td class="col-factura-total" style="color: #0f172a; font-weight: 700;">
												<?php echo number_format((float) $f->total, 2, '.', '') . ' €'; ?>
											</td>
											<td>
												<?php
												$badge_class = (strtolower(trim($f->estado)) == 'pagada') ? 'tallerpro-badge-status-pagada' : 'tallerpro-badge-status-pendiente';
												?>
												<span class="<?php echo $badge_class; ?>">
													<?php echo dol_escape_htmltag($f->estado ?: 'Pendiente'); ?>
												</span>
											</td>
											<td>
												<div class="tallerpro-actions">
													<a href="<?php echo $facturas_url . '?id=' . ((int) $f->rowid); ?>" class="tallerpro-btn-table-primary btn-ver-factura" title="Ver factura">
														<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
															<polyline points="15 3 21 3 21 9"></polyline>
															<line x1="10" y1="14" x2="21" y2="3"></line>
														</svg>
														<span>Ver</span>
													</a>
													<a href="<?php echo $facturas_url . '?action=pdf&id=' . ((int) $f->rowid); ?>" target="_blank" class="tallerpro-btn-table-secondary" title="Descargar PDF">
														<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
															<polyline points="7 10 12 15 17 10"></polyline>
															<line x1="12" y1="15" x2="12" y2="3"></line>
														</svg>
														<span>PDF</span>
													</a>
													<button type="button" class="tallerpro-btn-table-secondary btn-detalle-factura" title="Ver detalle"
														data-id="<?php echo (int) $f->rowid; ?>"
														data-numero="<?php echo dol_escape_htmltag($f->numero_factura); ?>"
														data-fecha="<?php echo dol_escape_htmltag($f->fecha); ?>"
														data-soc="<?php echo (int) $f->fk_soc; ?>"
														data-vehiculo="<?php echo (int) $f->fk_vehiculo; ?>"
														data-total="<?php echo number_format((float) $f->total, 2, '.', ''); ?>"
														data-estado="<?php echo dol_escape_htmltag($f->estado); ?>"
														data-notas="<?php echo dol_escape_htmltag($f->notas); ?>">
														<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
															<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
															<line x1="12" y1="22.08" x2="12" y2="12"></line>
														</svg>
														<span>Detalle</span>
													</button>
													<button type="button" class="tallerpro-action-btn btn-edit-factura" title="Editar"
														data-id="<?php echo (int) $f->rowid; ?>"
														data-numero="<?php echo dol_escape_htmltag($f->numero_factura); ?>"
														data-fecha="<?php echo dol_escape_htmltag($f->fecha); ?>"
														data-soc="<?php echo (int) $f->fk_soc; ?>"
														data-vehiculo="<?php echo (int) $f->fk_vehiculo; ?>"
														data-total="<?php echo number_format((float) $f->total, 2, '.', ''); ?>"
														data-estado="<?php echo dol_escape_htmltag($f->estado); ?>"
														data-notas="<?php echo dol_escape_htmltag($f->notas); ?>">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
														</svg>
													</button>
													<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=delete_factura&rowid=<?php echo (int) $f->rowid; ?>&token=<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>"
														class="tallerpro-action-btn"
														title="Eliminar"
														onclick="return confirm('¿Estás seguro de que deseas eliminar esta factura?');">
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
				<?php endif; ?>
			</main>
		</div>
	</div>

	<?php
	$next_factura_numero = getNextFacturaNumero($db, date('Y-m-d'), $conf->entity);
	?>
	<!-- Modal Nuevo Registro Facturas -->
	<div class="tallerpro-modal-backdrop" id="tallerFacturaModal">
		<div class="tallerpro-modal-card">
			<div class="tallerpro-modal-header">
				<h2 class="tallerpro-modal-title" id="tallerFacturaModalTitle">Nuevo registro</h2>
				<button type="button" class="tallerpro-modal-close" id="tallerCloseModalBtn" title="Cerrar">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			<form class="tallerpro-modal-form" id="tallerFacturaForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
				<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
				<input type="hidden" name="action" id="facturaFormAction" value="create_factura">
				<input type="hidden" name="rowid" id="facturaFormRowid" value="0">

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Nº factura <span class="tallerpro-required">*</span></label>
					<input type="text" name="numero_factura" id="fac_numero" class="tallerpro-form-input tallerpro-input-readonly" value="<?php echo dol_escape_htmltag($next_factura_numero); ?>" data-next-numero="<?php echo dol_escape_htmltag($next_factura_numero); ?>" readonly required />
					<span class="tallerpro-form-help-text">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 3px;">
							<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
							<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
						</svg>
						Generado automáticamente por el sistema
					</span>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Fecha</label>
					<div class="tallerpro-input-with-icon">
						<input type="date" name="fecha" id="fac_fecha" class="tallerpro-form-input" value="<?php echo date('Y-m-d'); ?>" />
					</div>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Cliente</label>
					<select name="fk_soc" id="fac_fk_soc" class="tallerpro-form-select">
						<option value="">— Selecciona —</option>
						<?php foreach ($clients_list as $cl): ?>
							<option value="<?php echo (int) $cl->rowid; ?>">
								<?php echo dol_escape_htmltag($cl->nom); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Vehículo</label>
					<select name="fk_vehiculo" id="fac_fk_vehiculo" class="tallerpro-form-select">
						<option value="">— Selecciona —</option>
						<?php foreach ($vehicles_list as $vh): ?>
							<option value="<?php echo (int) $vh->rowid; ?>">
								<?php echo dol_escape_htmltag($vh->matricula . (!empty($vh->marca) ? ' - ' . $vh->marca : '') . (!empty($vh->modelo) ? ' ' . $vh->modelo : '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Total (€)</label>
					<input type="number" step="0.01" min="0" name="total" id="fac_total" class="tallerpro-form-input" value="0.00" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Estado</label>
					<select name="estado" id="fac_estado" class="tallerpro-form-select">
						<option value="Pendiente" selected>Pendiente</option>
						<option value="Pagada">Pagada</option>
					</select>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Notas</label>
					<textarea name="notas" id="fac_notas" class="tallerpro-form-textarea" rows="3"></textarea>
				</div>
				<div class="tallerpro-modal-footer">
					<button type="submit" class="tallerpro-btn-submit" id="tallerSaveModalBtn">Guardar</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Modal Detalle de la Factura -->
	<div class="tallerpro-modal-backdrop" id="tallerFacturaDetalleModal">
		<div class="tallerpro-modal-card tallerpro-modal-card-lg">
			<div class="tallerpro-modal-header">
				<h2 class="tallerpro-modal-title" id="tallerDetalleModalTitle">Detalle de la factura</h2>
				<button type="button" class="tallerpro-modal-close" id="tallerCloseDetalleModalBtn" title="Cerrar">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>

			<div class="tallerpro-modal-body">
				<input type="hidden" id="detalle_fk_factura" value="0">

			<!-- Sección 1: Materiales -->
			<div class="tallerpro-invoice-section">
				<h3 class="tallerpro-invoice-section-title">
					<span>📦</span> Materiales
				</h3>
				<form id="tallerMatForm" onsubmit="event.preventDefault();">
					<div class="tallerpro-grid-mat-row1">
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Material del catálogo</label>
							<select id="det_mat_catalogo" class="tallerpro-form-select">
								<option value="">— Nuevo / escribir manualmente —</option>
								<?php foreach ($materials_catalog as $mcat): ?>
									<option value="<?php echo (int) $mcat->rowid; ?>"
										data-codigo="<?php echo dol_escape_htmltag($mcat->ref); ?>"
										data-descripcion="<?php echo dol_escape_htmltag($mcat->nombre); ?>"
										data-precio="<?php echo number_format((float) $mcat->precio, 2, '.', ''); ?>">
										<?php echo dol_escape_htmltag($mcat->nombre . ' (' . number_format((float) $mcat->precio, 2, '.', '') . ' €)'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Código</label>
							<input type="text" id="det_mat_codigo" class="tallerpro-form-input" />
						</div>
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Descripción</label>
							<input type="text" id="det_mat_descripcion" class="tallerpro-form-input" />
						</div>
					</div>

					<div class="tallerpro-grid-mat-row2" style="margin-top: 10px;">
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Cantidad</label>
							<input type="number" step="1" min="1" id="det_mat_cantidad" class="tallerpro-form-input" value="1" />
						</div>
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Precio unit. (€)</label>
							<input type="number" step="0.01" min="0" id="det_mat_precio" class="tallerpro-form-input" value="0" />
						</div>
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Descuento (%)</label>
							<input type="number" step="1" min="0" max="100" id="det_mat_descuento" class="tallerpro-form-input" value="0" />
						</div>
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Total neto</label>
							<input type="text" id="det_mat_total_neto" class="tallerpro-form-input" value="0.00 €" readonly />
						</div>
						<div class="tallerpro-form-group" style="justify-content: flex-end;">
							<label class="tallerpro-checkbox-label">
								<input type="checkbox" id="det_mat_guardar_catalogo" checked />
								<span>Guardar en catálogo</span>
							</label>
						</div>
						<div class="tallerpro-form-group" style="justify-content: flex-end;">
							<button type="button" class="tallerpro-btn-add-line" id="btnAddMaterialLine">
								<span>+ Añadir</span>
							</button>
						</div>
					</div>
				</form>

				<!-- Listado de Materiales o estado vacío -->
				<div id="det_mat_empty" class="tallerpro-invoice-empty-box">
					Sin materiales todavía.
				</div>
				<div id="det_mat_list" class="tallerpro-invoice-lines-list" style="display: none;"></div>
			</div>

			<!-- Sección 2: Reparaciones / Mano de obra -->
			<div class="tallerpro-invoice-section">
				<h3 class="tallerpro-invoice-section-title">
					<span>🔧</span> Reparaciones / Mano de obra
				</h3>
				<form id="tallerRepForm" onsubmit="event.preventDefault();">
					<div class="tallerpro-grid-rep-row1">
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Descripción de la reparación</label>
							<input type="text" id="det_rep_descripcion" class="tallerpro-form-input" placeholder="Ej: Cambio de embrague" />
						</div>
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Precio (€)</label>
							<input type="number" step="0.01" min="0" id="det_rep_precio" class="tallerpro-form-input" value="0" />
						</div>
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Descuento (%)</label>
							<input type="number" step="1" min="0" max="100" id="det_rep_descuento" class="tallerpro-form-input" value="0" />
						</div>
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Total neto</label>
							<input type="text" id="det_rep_total_neto" class="tallerpro-form-input" value="0.00 €" readonly />
						</div>
					</div>

					<div class="tallerpro-grid-rep-row2" style="margin-top: 10px;">
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Notas</label>
							<textarea id="det_rep_notas" class="tallerpro-form-textarea" rows="2"></textarea>
						</div>
						<div class="tallerpro-form-group" style="justify-content: flex-end;">
							<button type="button" class="tallerpro-btn-add-line" id="btnAddReparacionLine">
								<span>+ Añadir</span>
							</button>
						</div>
					</div>
				</form>

				<!-- Listado de Reparaciones o estado vacío -->
				<div id="det_rep_empty" class="tallerpro-invoice-empty-box">
					Sin reparaciones todavía.
				</div>
				<div id="det_rep_list" class="tallerpro-invoice-lines-list" style="display: none;"></div>
			</div>

			<!-- Sección 3: Totales y desglose impositivo -->
			<div class="tallerpro-invoice-totals-card">
				<div class="tallerpro-invoice-totals-grid">
					<!-- Lado izquierdo: Descuento global e IVA -->
					<div class="tallerpro-totals-inputs">
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">Descuento global (€)</label>
							<input type="number" step="0.01" min="0" id="det_tot_descuento_global" class="tallerpro-form-input" value="0" />
						</div>
						<div class="tallerpro-form-group">
							<label class="tallerpro-form-label">IVA (%)</label>
							<input type="number" step="0.01" min="0" id="det_tot_iva" class="tallerpro-form-input" value="21" />
						</div>
					</div>

					<!-- Lado derecho: Desglose dinámico -->
					<div class="tallerpro-totals-summary">
						<div class="tallerpro-totals-row">
							<span>Materiales</span>
							<span id="sum_materiales">0.00 €</span>
						</div>
						<div class="tallerpro-totals-row">
							<span>Reparaciones</span>
							<span id="sum_reparaciones">0.00 €</span>
						</div>
						<div class="tallerpro-totals-divider"></div>
						<div class="tallerpro-totals-row">
							<span>Subtotal</span>
							<span id="sum_subtotal">0.00 €</span>
						</div>
						<div class="tallerpro-totals-row">
							<span>Descuento</span>
							<span id="sum_descuento">- 0.00 €</span>
						</div>
						<div class="tallerpro-totals-divider"></div>
						<div class="tallerpro-totals-row">
							<span>Base imponible</span>
							<span id="sum_base_imponible">0.00 €</span>
						</div>
						<div class="tallerpro-totals-row">
							<span id="lbl_iva">IVA (21%)</span>
							<span id="sum_iva">0.00 €</span>
						</div>
						<div class="tallerpro-totals-divider"></div>
						<div class="tallerpro-totals-row tallerpro-totals-final">
							<span>TOTAL</span>
							<span id="sum_total">0.00 €</span>
						</div>
					</div>
				</div>

				<div class="tallerpro-invoice-modal-footer-actions">
					<button type="button" class="tallerpro-btn-save-totals" id="btnSaveInvoiceTotals" title="Guarda los totales en la base de datos">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
							<polyline points="17 21 17 13 7 13 7 21"></polyline>
							<polyline points="7 3 7 8 15 8"></polyline>
						</svg>
						<span>Guardar totales</span>
					</button>
					<button type="button" class="tallerpro-btn-modal-listo" id="btnModalListo">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
							<polyline points="20 6 9 17 4 12"></polyline>
						</svg>
						<span>Listo</span>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
// End of page
llxFooter();
$db->close();
