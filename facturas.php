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

$table_factura = MAIN_DB_PREFIX . "taller_factura";
$table_vehiculo = MAIN_DB_PREFIX . "taller_vehiculo";
$table_soc = MAIN_DB_PREFIX . "societe";
$table_linea = MAIN_DB_PREFIX . "taller_factura_linea";

// Ensure tables exist
$sql_create = "CREATE TABLE IF NOT EXISTS " . $table_factura . " (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    numero_factura  VARCHAR(64) NOT NULL,
    fecha           DATE DEFAULT NULL,
    fk_soc          INT DEFAULT NULL,
    fk_vehiculo     INT DEFAULT NULL,
    total           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    descuento_global DECIMAL(10,2) DEFAULT 0.00,
    iva_porcentaje  DECIMAL(5,2) DEFAULT 21.00,
    estado          VARCHAR(32) DEFAULT 'Pendiente',
    notas           TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat   INT NOT NULL,
    entity          INT DEFAULT 1,
    status          SMALLINT DEFAULT 1,
    INDEX idx_taller_factura_soc (fk_soc),
    INDEX idx_taller_factura_vehiculo (fk_vehiculo),
    INDEX idx_taller_factura_num (numero_factura)
) ENGINE=innodb;";
$db->query($sql_create);

$sql_create_linea = "CREATE TABLE IF NOT EXISTS " . $table_linea . " (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    fk_factura      INT NOT NULL,
    tipo            VARCHAR(32) NOT NULL,
    fk_material     INT DEFAULT NULL,
    codigo          VARCHAR(64) DEFAULT NULL,
    descripcion     VARCHAR(255) NOT NULL,
    cantidad        DECIMAL(10,2) DEFAULT 1.00,
    precio          DECIMAL(10,2) DEFAULT 0.00,
    descuento       DECIMAL(5,2) DEFAULT 0.00,
    total_neto      DECIMAL(10,2) DEFAULT 0.00,
    notas           TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    entity          INT DEFAULT 1,
    INDEX idx_taller_fac_linea_fac (fk_factura),
    INDEX idx_taller_fac_linea_tipo (tipo)
) ENGINE=innodb;";
$db->query($sql_create_linea);

// Seed sample invoices if empty
$sql_check_empty = "SELECT COUNT(*) as nb FROM " . $table_factura;
$res_ce = $db->query($sql_check_empty);
if ($res_ce) {
	$obj_ce = $db->fetch_object($res_ce);
	if ($obj_ce && $obj_ce->nb == 0) {
		$first_soc_id = 0;
		$res_fs = $db->query("SELECT rowid FROM " . $table_soc . " LIMIT 1");
		if ($res_fs && ($fs = $db->fetch_object($res_fs))) {
			$first_soc_id = (int)$fs->rowid;
		}
		$first_veh_id = 0;
		$res_fv = $db->query("SELECT rowid FROM " . $table_vehiculo . " LIMIT 1");
		if ($res_fv && ($fv = $db->fetch_object($res_fv))) {
			$first_veh_id = (int)$fv->rowid;
		}

		$seed_data = array(
			array('numero' => '127', 'fecha' => '2026-10-28', 'total' => 0.00, 'estado' => 'Pagada'),
			array('numero' => '123', 'fecha' => '2026-10-27', 'total' => 0.00, 'estado' => 'Pendiente'),
			array('numero' => '0177/25', 'fecha' => '2025-01-28', 'total' => 773.44, 'estado' => 'Pagada'),
			array('numero' => '0162/24', 'fecha' => '2024-05-09', 'total' => 844.58, 'estado' => 'Pagada'),
			array('numero' => '0161/24', 'fecha' => '2024-05-08', 'total' => 98.01, 'estado' => 'Pagada'),
			array('numero' => '0159/24', 'fecha' => '2024-04-25', 'total' => 318.23, 'estado' => 'Pagada'),
			array('numero' => '0157/24', 'fecha' => '2024-04-23', 'total' => 199.88, 'estado' => 'Pagada'),
			array('numero' => '0160/24', 'fecha' => '2024-04-29', 'total' => 578.38, 'estado' => 'Pagada')
		);
		foreach ($seed_data as $sd) {
			$sql_seed = "INSERT INTO " . $table_factura . " (numero_factura, fecha, fk_soc, fk_vehiculo, total, estado, date_creation, fk_user_creat, entity, status) VALUES (";
			$sql_seed .= "'" . $db->escape($sd['numero']) . "', ";
			$sql_seed .= "'" . $db->escape($sd['fecha']) . "', ";
			$sql_seed .= ($first_soc_id > 0 ? $first_soc_id : "NULL") . ", ";
			$sql_seed .= ($first_veh_id > 0 ? $first_veh_id : "NULL") . ", ";
			$sql_seed .= ((float) $sd['total']) . ", ";
			$sql_seed .= "'" . $db->escape($sd['estado']) . "', ";
			$sql_seed .= "'" . $db->idate(dol_now()) . "', ";
			$sql_seed .= ((int) $user->id) . ", ";
			$sql_seed .= ((int) $conf->entity) . ", 1)";
			$db->query($sql_seed);
		}
	}
}

$action = GETPOST('action', 'alpha');
if (empty($action) && !empty($_POST['action'])) {
	$action = $_POST['action'];
}

// Acción: Crear Factura
if ($action == 'create_factura' || $action == 'add_factura') {
	$numero_factura = trim(GETPOST('numero_factura', 'alphanohtml'));
	if (empty($numero_factura) && !empty($_POST['numero_factura'])) {
		$numero_factura = trim($_POST['numero_factura']);
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

	$total_raw = trim(GETPOST('total', 'alpha'));
	if (empty($total_raw) && isset($_POST['total'])) {
		$total_raw = trim($_POST['total']);
	}
	$total = (float) str_replace(',', '.', $total_raw);

	$estado = trim(GETPOST('estado', 'alphanohtml'));
	if (empty($estado) && !empty($_POST['estado'])) {
		$estado = trim($_POST['estado']);
	}
	if (empty($estado)) {
		$estado = 'Pendiente';
	}

	$notas = trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) {
		$notas = trim($_POST['notas']);
	}

	if (empty($numero_factura)) {
		setEventMessages($langs->trans("ErrorFieldRequired", "Nº factura"), null, 'errors');
	} else {
		$sql_insert = "INSERT INTO " . $table_factura . " (";
		$sql_insert .= "numero_factura, fecha, fk_soc, fk_vehiculo, total, estado, notas, date_creation, fk_user_creat, entity, status";
		$sql_insert .= ") VALUES (";
		$sql_insert .= "'" . $db->escape($numero_factura) . "', ";
		$sql_insert .= (!empty($fecha) ? "'" . $db->escape($fecha) . "'" : "NULL") . ", ";
		$sql_insert .= ($fk_soc > 0 ? ((int) $fk_soc) : "NULL") . ", ";
		$sql_insert .= ($fk_vehiculo > 0 ? ((int) $fk_vehiculo) : "NULL") . ", ";
		$sql_insert .= ((float) $total) . ", ";
		$sql_insert .= "'" . $db->escape($estado) . "', ";
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
			setEventMessages("Error al guardar la factura: " . $db->lasterror(), null, 'errors');
		}
	}
}

// Acción: Actualizar Factura
if ($action == 'update_factura' || $action == 'edit_factura') {
	$rowid = GETPOSTINT('rowid');
	if (empty($rowid) && !empty($_POST['rowid'])) {
		$rowid = (int) $_POST['rowid'];
	}

	$numero_factura = trim(GETPOST('numero_factura', 'alphanohtml'));
	if (empty($numero_factura) && !empty($_POST['numero_factura'])) {
		$numero_factura = trim($_POST['numero_factura']);
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

	$total_raw = trim(GETPOST('total', 'alpha'));
	if (empty($total_raw) && isset($_POST['total'])) {
		$total_raw = trim($_POST['total']);
	}
	$total = (float) str_replace(',', '.', $total_raw);

	$estado = trim(GETPOST('estado', 'alphanohtml'));
	if (empty($estado) && !empty($_POST['estado'])) {
		$estado = trim($_POST['estado']);
	}
	if (empty($estado)) {
		$estado = 'Pendiente';
	}

	$notas = trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) {
		$notas = trim($_POST['notas']);
	}

	if (empty($numero_factura)) {
		setEventMessages($langs->trans("ErrorFieldRequired", "Nº factura"), null, 'errors');
	} elseif ($rowid <= 0) {
		setEventMessages("Identificador de factura no válido", null, 'errors');
	} else {
		$sql_update = "UPDATE " . $table_factura . " SET ";
		$sql_update .= "numero_factura = '" . $db->escape($numero_factura) . "', ";
		$sql_update .= "fecha = " . (!empty($fecha) ? "'" . $db->escape($fecha) . "'" : "NULL") . ", ";
		$sql_update .= "fk_soc = " . ($fk_soc > 0 ? ((int) $fk_soc) : "NULL") . ", ";
		$sql_update .= "fk_vehiculo = " . ($fk_vehiculo > 0 ? ((int) $fk_vehiculo) : "NULL") . ", ";
		$sql_update .= "total = " . ((float) $total) . ", ";
		$sql_update .= "estado = '" . $db->escape($estado) . "', ";
		$sql_update .= "notas = '" . $db->escape($notas) . "' ";
		$sql_update .= "WHERE rowid = " . ((int) $rowid) . " AND entity IN (" . getEntity('taller_factura') . ")";

		$resql = $db->query($sql_update);
		if ($resql) {
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF']);
			exit;
		} else {
			setEventMessages("Error al actualizar la factura: " . $db->lasterror(), null, 'errors');
		}
	}
}

// Acción: Eliminar Factura (borrado lógico papelera interna)
if ($action == 'delete_factura' || $action == 'delete') {
	$rowid = GETPOSTINT('rowid');
	if ($rowid > 0) {
		$sql_del = "UPDATE " . $table_factura . " SET status = 0 WHERE rowid = " . ((int) $rowid) . " AND entity IN (" . getEntity('taller_factura') . ")";
		$resql = $db->query($sql_del);
		if ($resql) {
			setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF']);
			exit;
		} else {
			setEventMessages("Error al eliminar la factura: " . $db->lasterror(), null, 'errors');
		}
	}
}

// Función para recalcular totales de la factura a partir de sus líneas
if (!function_exists('recalcFacturaTotal')) {
	function recalcFacturaTotal($db, $fk_factura) {
		$table_linea = MAIN_DB_PREFIX . "taller_factura_linea";
		$table_factura = MAIN_DB_PREFIX . "taller_factura";

		$tot_mat = 0.0;
		$tot_rep = 0.0;
		$res_lines = $db->query("SELECT tipo, total_neto FROM " . $table_linea . " WHERE fk_factura = " . ((int) $fk_factura));
		if ($res_lines) {
			while ($l = $db->fetch_object($res_lines)) {
				if ($l->tipo == 'material') {
					$tot_mat += (float) $l->total_neto;
				} else {
					$tot_rep += (float) $l->total_neto;
				}
			}
		}

		$res_fac = $db->query("SELECT descuento_global, iva_porcentaje FROM " . $table_factura . " WHERE rowid = " . ((int) $fk_factura));
		$desc = 0.0;
		$iva = 21.0;
		if ($res_fac && ($fac_obj = $db->fetch_object($res_fac))) {
			$desc = (float) $fac_obj->descuento_global;
			$iva = (float) $fac_obj->iva_porcentaje;
		}

		$subtotal = $tot_mat + $tot_rep;
		$base = max(0, $subtotal - $desc);
		$total_iva = $base * ($iva / 100);
		$total_final = $base + $total_iva;

		$db->query("UPDATE " . $table_factura . " SET total = " . ((float) $total_final) . " WHERE rowid = " . ((int) $fk_factura));
		return $total_final;
	}
}

// AJAX: Obtener líneas y datos completos de una factura
if ($action == 'get_factura_lineas') {
	$fk_factura = GETPOSTINT('fk_factura');
	$out = array('materiales' => array(), 'reparaciones' => array(), 'factura' => null);
	if ($fk_factura > 0) {
		$res_fac = $db->query("SELECT rowid, numero_factura, total, descuento_global, iva_porcentaje, estado FROM " . $table_factura . " WHERE rowid = " . ((int) $fk_factura));
		if ($res_fac && ($fac_obj = $db->fetch_object($res_fac))) {
			$out['factura'] = $fac_obj;
		}

		$sql_lines = "SELECT rowid, tipo, fk_material, codigo, descripcion, cantidad, precio, descuento, total_neto, notas FROM " . $table_linea . " WHERE fk_factura = " . ((int) $fk_factura) . " ORDER BY rowid ASC";
		$res_lines = $db->query($sql_lines);
		if ($res_lines) {
			while ($l = $db->fetch_object($res_lines)) {
				if ($l->tipo == 'material') {
					$out['materiales'][] = $l;
				} else {
					$out['reparaciones'][] = $l;
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
	if (empty($fk_factura) && !empty($_GET['fk_factura'])) {
		$fk_factura = (int) $_GET['fk_factura'];
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
	if (empty($guardar_catalogo) && !empty($_POST['guardar_catalogo'])) {
		$guardar_catalogo = (int) $_POST['guardar_catalogo'];
	}

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
	// Anti-duplicate protection: avoid inserting the exact same line within 3 seconds
	$sql_chk = "SELECT rowid, total_neto FROM " . $table_linea . " ";
	$sql_chk .= "WHERE fk_factura = " . ((int) $fk_factura) . " ";
	$sql_chk .= "AND tipo = '" . $db->escape($tipo) . "' ";
	$sql_chk .= "AND descripcion = '" . $db->escape($descripcion) . "' ";
	$sql_chk .= "AND cantidad = " . $cantidad . " ";
	$sql_chk .= "AND precio = " . $precio . " ";
	$sql_chk .= "AND date_creation >= '" . $db->idate(dol_now() - 3) . "' ";
	$sql_chk .= "ORDER BY rowid DESC LIMIT 1";
	$res_chk = $db->query($sql_chk);
	if ($res_chk && ($chk_obj = $db->fetch_object($res_chk))) {
		$new_total = recalcFacturaTotal($db, $fk_factura);
		header('Content-Type: application/json');
		echo json_encode(array(
			'status' => 'ok',
			'id' => (int) $chk_obj->rowid,
			'total_neto' => (float) $chk_obj->total_neto,
			'new_total' => $new_total,
			'duplicate_prevented' => true
		));
		exit;
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

	$sql_ins_linea = "INSERT INTO " . $table_linea . " (fk_factura, tipo, fk_material, codigo, descripcion, cantidad, precio, descuento, total_neto, notas, date_creation, entity) VALUES (";
	$sql_ins_linea .= ((int) $fk_factura) . ", ";
	$sql_ins_linea .= "'" . $db->escape($tipo) . "', ";
	$sql_ins_linea .= ($fk_material > 0 ? ((int) $fk_material) : "NULL") . ", ";
	$sql_ins_linea .= "'" . $db->escape($codigo) . "', ";
	$sql_ins_linea .= "'" . $db->escape($descripcion) . "', ";
	$sql_ins_linea .= $cantidad . ", ";
	$sql_ins_linea .= $precio . ", ";
	$sql_ins_linea .= $descuento . ", ";
	$sql_ins_linea .= $total_neto . ", ";
	$sql_ins_linea .= "'" . $db->escape($notas) . "', ";
	$sql_ins_linea .= "'" . $db->idate(dol_now()) . "', ";
	$sql_ins_linea .= ((int) $conf->entity) . ")";

	$resql = $db->query($sql_ins_linea);
	if ($resql) {
		$inserted_id = $db->last_insert_id($table_linea);
		$new_total = recalcFacturaTotal($db, $fk_factura);
		header('Content-Type: application/json');
		echo json_encode(array('status' => 'ok', 'id' => $inserted_id, 'total_neto' => $total_neto, 'new_total' => $new_total));
	} else {
		header('Content-Type: application/json');
		echo json_encode(array('status' => 'error', 'message' => 'Error al insertar línea: ' . $db->lasterror()));
	}
	exit;
}

// AJAX: Eliminar línea
if ($action == 'delete_factura_linea') {
	$linea_id = GETPOSTINT('linea_id');
	if (empty($linea_id) && !empty($_POST['linea_id'])) {
		$linea_id = (int) $_POST['linea_id'];
	}
	if (empty($linea_id) && !empty($_GET['linea_id'])) {
		$linea_id = (int) $_GET['linea_id'];
	}
	$fk_factura = GETPOSTINT('fk_factura');
	if (empty($fk_factura) && !empty($_POST['fk_factura'])) {
		$fk_factura = (int) $_POST['fk_factura'];
	}
	if (empty($fk_factura) && !empty($_GET['fk_factura'])) {
		$fk_factura = (int) $_GET['fk_factura'];
	}
	if ($linea_id > 0) {
		$db->query("DELETE FROM " . $table_linea . " WHERE rowid = " . ((int) $linea_id));
		if ($fk_factura > 0) {
			recalcFacturaTotal($db, $fk_factura);
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
	if (empty($fk_factura) && !empty($_GET['fk_factura'])) {
		$fk_factura = (int) $_GET['fk_factura'];
	}

	$descuento_global_raw = GETPOST('descuento_global', 'alpha');
	if ($descuento_global_raw === '' && isset($_POST['descuento_global'])) {
		$descuento_global_raw = $_POST['descuento_global'];
	}
	$descuento_global = (float) str_replace(',', '.', $descuento_global_raw);

	$iva_porcentaje_raw = GETPOST('iva_porcentaje', 'alpha');
	if ($iva_porcentaje_raw === '' && isset($_POST['iva_porcentaje'])) {
		$iva_porcentaje_raw = $_POST['iva_porcentaje'];
	}
	$iva_porcentaje = (float) str_replace(',', '.', $iva_porcentaje_raw);

	$total_raw = GETPOST('total', 'alpha');
	if ($total_raw === '' && isset($_POST['total'])) {
		$total_raw = $_POST['total'];
	}
	$total = (float) str_replace(',', '.', $total_raw);

	if ($fk_factura > 0) {
		$sql_upd = "UPDATE " . $table_factura . " SET ";
		$sql_upd .= "descuento_global = " . $descuento_global . ", ";
		$sql_upd .= "iva_porcentaje = " . $iva_porcentaje . ", ";
		$sql_upd .= "total = " . $total . " ";
		$sql_upd .= "WHERE rowid = " . ((int) $fk_factura);
		$db->query($sql_upd);
	}
	header('Content-Type: application/json');
	echo json_encode(array('status' => 'ok', 'total' => $total));
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

// Consultar facturas con JOIN a cliente y vehículo
$facturas_list = array();
$sql_f = "SELECT f.rowid, f.numero_factura, f.fecha, f.fk_soc, f.fk_vehiculo, f.total, f.descuento_global, f.iva_porcentaje, f.estado, f.notas, ";
$sql_f .= "s.nom as cliente_nombre, ";
$sql_f .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo ";
$sql_f .= "FROM " . $table_factura . " as f ";
$sql_f .= "LEFT JOIN " . $table_soc . " as s ON f.fk_soc = s.rowid ";
$sql_f .= "LEFT JOIN " . $table_vehiculo . " as v ON f.fk_vehiculo = v.rowid ";
$sql_f .= "WHERE f.status = 1 AND f.entity IN (" . getEntity('taller_factura') . ") ";
$sql_f .= "ORDER BY f.rowid DESC";
$res_f = $db->query($sql_f);
if ($res_f) {
	while ($f_obj = $db->fetch_object($res_f)) {
		$facturas_list[] = $f_obj;
	}
}
$total_facturas = count($facturas_list);

// Vista individual de una factura (Documental)
$view_id = GETPOSTINT('id');
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
	$sql_single = "SELECT f.*, ";
	$sql_single .= "s.nom as cliente_nombre, COALESCE(NULLIF(s.siren, ''), s.idprof1) as cliente_cif, COALESCE(NULLIF(s.town, ''), s.address) as cliente_ciudad, s.phone as cliente_telefono, s.email as cliente_email, ";
	$sql_single .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo, v.anio as vehiculo_anio ";
	$sql_single .= "FROM " . $table_factura . " as f ";
	$sql_single .= "LEFT JOIN " . $table_soc . " as s ON f.fk_soc = s.rowid ";
	$sql_single .= "LEFT JOIN " . $table_vehiculo . " as v ON f.fk_vehiculo = v.rowid ";
	$sql_single .= "WHERE f.rowid = " . ((int) $view_id);
	$res_single = $db->query($sql_single);
	if ($res_single && ($fs = $db->fetch_object($res_single))) {
		$factura_single = $fs;
		$tot_mat = 0.0;
		$tot_rep = 0.0;
		$res_lin = $db->query("SELECT * FROM " . $table_linea . " WHERE fk_factura = " . ((int) $view_id) . " ORDER BY rowid ASC");
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
		$single_iva_pct = (float) ($factura_single->iva_porcentaje !== null ? $factura_single->iva_porcentaje : 21);
		$single_iva_amt = $single_base * ($single_iva_pct / 100);
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
									data-numero="<?php echo dol_escape_htmltag($factura_single->numero_factura); ?>">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
									</svg>
									<span>Editar líneas</span>
								</button>
								<?php
								$badge_class = (strtolower(trim($factura_single->estado)) == 'pagada') ? 'tallerpro-badge-status-pagada' : 'tallerpro-badge-status-pendiente';
								?>
								<span class="<?php echo $badge_class; ?>">
									<?php echo dol_escape_htmltag($factura_single->estado ?: 'Pendiente'); ?>
								</span>
								<button type="button" class="tallerpro-btn-pdf" onclick="window.print();">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
										<polyline points="7 10 12 15 17 10"></polyline>
										<line x1="12" y1="15" x2="12" y2="3"></line>
									</svg>
									<span>Descargar PDF</span>
								</button>
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

						<!-- Sección 1: Materiales -->
						<div class="tallerpro-invoice-view-section">
							<h3 class="tallerpro-invoice-section-title"><span>📦</span> Materiales</h3>
							<?php if (empty($single_materiales)): ?>
								<div class="tallerpro-invoice-empty-box">Sin materiales todavía.</div>
							<?php else: ?>
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
												<td><?php echo !empty($mat->codigo) ? dol_escape_htmltag($mat->codigo) : '—'; ?></td>
												<td><?php echo dol_escape_htmltag($mat->descripcion); ?></td>
												<td style="text-align: center;"><?php echo number_format((float) $mat->cantidad, 0); ?></td>
												<td style="text-align: right;"><?php echo number_format((float) $mat->precio, 2, '.', '') . ' €'; ?></td>
												<td style="text-align: center;"><?php echo number_format((float) $mat->descuento, 0) . '%'; ?></td>
												<td style="text-align: right; font-weight: 700; color: #0f172a;"><?php echo number_format((float) $mat->total_neto, 2, '.', '') . ' €'; ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>

						<!-- Sección 2: Reparaciones / Mano de obra -->
						<div class="tallerpro-invoice-view-section">
							<h3 class="tallerpro-invoice-section-title"><span>🔧</span> Reparaciones / Mano de obra</h3>
							<?php if (empty($single_reparaciones)): ?>
								<div class="tallerpro-invoice-empty-box">Sin reparaciones todavía.</div>
							<?php else: ?>
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
													<span><?php echo dol_escape_htmltag($rep->descripcion); ?></span>
													<?php if (!empty($rep->notas)): ?>
														<div style="font-size: 11.5px; color: #64748b; margin-top: 2px;"><?php echo dol_escape_htmltag($rep->notas); ?></div>
													<?php endif; ?>
												</td>
												<td style="text-align: right;"><?php echo number_format((float) $rep->precio, 2, '.', '') . ' €'; ?></td>
												<td style="text-align: center;"><?php echo number_format((float) $rep->descuento, 0) . '%'; ?></td>
												<td style="text-align: right; font-weight: 700; color: #0f172a;"><?php echo number_format((float) $rep->total_neto, 2, '.', '') . ' €'; ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>

						<!-- Sección 3: Totales Card -->
						<div class="tallerpro-invoice-view-section tallerpro-invoice-summary-card">
							<div class="tallerpro-totals-summary" style="max-width: 380px; margin-left: auto;">
								<div class="tallerpro-totals-row">
									<span>Subtotal</span>
									<span><?php echo number_format((float) $single_subtotal, 2, '.', '') . ' €'; ?></span>
								</div>
								<div class="tallerpro-totals-row">
									<span>Descuento</span>
									<span>- <?php echo number_format((float) $single_desc, 2, '.', '') . ' €'; ?></span>
								</div>
								<div class="tallerpro-totals-divider"></div>
								<div class="tallerpro-totals-row">
									<span>Base imponible</span>
									<span><?php echo number_format((float) $single_base, 2, '.', '') . ' €'; ?></span>
								</div>
								<div class="tallerpro-totals-row">
									<span>IVA (<?php echo (int) $single_iva_pct; ?>%)</span>
									<span><?php echo number_format((float) $single_iva_amt, 2, '.', '') . ' €'; ?></span>
								</div>
								<div class="tallerpro-totals-divider"></div>
								<div class="tallerpro-totals-row tallerpro-totals-final">
									<span>TOTAL</span>
									<span><?php echo number_format((float) $single_total, 2, '.', '') . ' €'; ?></span>
								</div>
							</div>
						</div>

						<!-- Sección 4: Notas -->
						<div class="tallerpro-invoice-info-card" style="margin-top: 4px;">
							<div class="tallerpro-info-card-tag">NOTAS</div>
							<div class="tallerpro-info-card-line" style="margin-top: 4px;"><?php echo !empty($factura_single->notas) ? nl2br(dol_escape_htmltag($factura_single->notas)) : 'Sin notas'; ?></div>
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
					<input type="text" name="numero_factura" id="fac_numero" class="tallerpro-form-input" required />
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
					<label class="tallerpro-form-label">Total (€) <span class="tallerpro-required">*</span></label>
					<input type="number" step="0.01" name="total" id="fac_total" class="tallerpro-form-input" required />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Estado</label>
					<select name="estado" id="fac_estado" class="tallerpro-form-select">
						<option value="">— Selecciona —</option>
						<option value="Pendiente">Pendiente</option>
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
