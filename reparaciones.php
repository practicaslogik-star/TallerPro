<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       taller/reparaciones.php
 *  \ingroup    taller
 *  \brief      Reparaciones list view - TallerPro view
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
$langs->loadLangs(array("taller@taller"));

// Security check
if (!isModEnabled('taller')) {
	accessforbidden('Module not enabled');
}

require_once __DIR__ . '/lib/taller_stock.lib.php';

$table_reparacion = MAIN_DB_PREFIX . "taller_reparacion";
$table_vehiculo = MAIN_DB_PREFIX . "taller_vehiculo";
$table_material = MAIN_DB_PREFIX . "taller_material";

// Ensure table exists
$sql_create = "CREATE TABLE IF NOT EXISTS " . $table_reparacion . " (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    fk_vehiculo     INT NOT NULL,
    fecha           DATE NOT NULL,
    ref_factura     VARCHAR(64) DEFAULT NULL,
    descripcion     TEXT DEFAULT NULL,
    coste_mo        DOUBLE(24,8) DEFAULT 0,
    notas           TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat   INT NOT NULL,
    entity          INT DEFAULT 1,
    status          SMALLINT DEFAULT 1,
    estado          VARCHAR(32) DEFAULT 'en_curso',
    INDEX idx_taller_rep_fk_vehiculo (fk_vehiculo),
    INDEX idx_taller_rep_fecha (fecha)
) ENGINE=innodb;";
$db->query($sql_create);
@$db->query("ALTER TABLE " . $table_reparacion . " ADD COLUMN estado VARCHAR(32) DEFAULT 'en_curso'");

// Table: Historial y Movimientos de Stock
$table_stock_mvt = MAIN_DB_PREFIX . "taller_stock_mvt";
$sql_create_mvt = "CREATE TABLE IF NOT EXISTS " . $table_stock_mvt . " (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    fk_material     INT NOT NULL,
    cantidad        DOUBLE NOT NULL,
    tipo            VARCHAR(32) NOT NULL,
    fk_reparacion   INT DEFAULT NULL,
    stock_anterior  DOUBLE DEFAULT 0,
    stock_posterior DOUBLE DEFAULT 0,
    fecha           DATETIME NOT NULL,
    fk_user         INT NOT NULL,
    descripcion     VARCHAR(255) DEFAULT NULL,
    entity          INT DEFAULT 1,
    INDEX idx_taller_mvt_mat (fk_material),
    INDEX idx_taller_mvt_rep (fk_reparacion)
) ENGINE=innodb;";
$db->query($sql_create_mvt);

/**
 * Obtener materiales de una orden de reparación decodificando sus notas JSON
 */
if (!function_exists('getMaterialesFromReparacionNotas')) {
	function getMaterialesFromReparacionNotas($notas_str) {
		if (empty($notas_str) || ($notas_str[0] !== '{' && $notas_str[0] !== '[')) {
			return array();
		}
		$data = json_decode($notas_str, true);
		if (!empty($data) && !empty($data['materiales']) && is_array($data['materiales'])) {
			return $data['materiales'];
		}
		return array();
	}
}

/**
 * Aplicar consumo o ajuste de stock para los materiales de una reparación
 */
if (!function_exists('aplicarStockReparacion')) {
	function aplicarStockReparacion($db, $rep_id, $nuevas_lineas_json, $prev_notas_json = null, $user_id = 1, $entity = 1) {
		$table_material = MAIN_DB_PREFIX . "taller_material";
		$table_mvt = MAIN_DB_PREFIX . "taller_stock_mvt";

		// 1. Revertir stock de materiales previos si se está editando
		if (!empty($prev_notas_json)) {
			$prev_mats = getMaterialesFromReparacionNotas($prev_notas_json);
			foreach ($prev_mats as $pm) {
				$fk_mat = !empty($pm['fk_material']) ? (int) $pm['fk_material'] : 0;
				$cant = (float) (!empty($pm['cantidad']) ? $pm['cantidad'] : 0);
				if ($cant > 0) {
					$fk_prod = 0;
					if ($fk_mat > 0) {
						$res_cur = $db->query("SELECT stock, fk_product FROM " . $table_material . " WHERE rowid = " . $fk_mat);
						if ($res_cur && ($obj_cur = $db->fetch_object($res_cur))) {
							$fk_prod = (int) $obj_cur->fk_product;
						}
					}
					if ($fk_prod <= 0 && !empty($pm['codigo'])) {
						$res_p = $db->query("SELECT fk_product, rowid FROM " . $table_material . " WHERE ref = '" . $db->escape(trim($pm['codigo'])) . "' LIMIT 1");
						if ($res_p && ($p_obj = $db->fetch_object($res_p))) {
							$fk_prod = (int) $p_obj->fk_product;
							if ($fk_mat <= 0) $fk_mat = (int) $p_obj->rowid;
						}
					}

					// Reintegrar en Almacén nativo Dolibarr
					if ($fk_prod > 0) {
						tallerpro_record_stock_movement($fk_prod, $cant, 'Ajuste previo por edición en OT #' . str_pad($rep_id, 4, '0', STR_PAD_LEFT), 'reparacion', $rep_id, null);
					}

					if ($fk_mat > 0) {
						$res_cur = $db->query("SELECT stock FROM " . $table_material . " WHERE rowid = " . $fk_mat);
						$cur_stock = 0.0;
						if ($res_cur && ($obj_cur = $db->fetch_object($res_cur))) {
							$cur_stock = (float) $obj_cur->stock;
						}
						$new_stock = $cur_stock + $cant;
						$db->query("UPDATE " . $table_material . " SET stock = " . $new_stock . " WHERE rowid = " . $fk_mat);

						$sql_mvt = "INSERT INTO " . $table_mvt . " (fk_material, cantidad, tipo, fk_reparacion, stock_anterior, stock_posterior, fecha, fk_user, descripcion, entity) VALUES (";
						$sql_mvt .= $fk_mat . ", " . $cant . ", 'reparacion_ajuste_dev', " . ((int) $rep_id) . ", " . $cur_stock . ", " . $new_stock . ", '" . $db->idate(dol_now()) . "', " . ((int) $user_id) . ", 'Ajuste previo por edición en OT #" . str_pad($rep_id, 4, '0', STR_PAD_LEFT) . "', " . ((int) $entity) . ")";
						$db->query($sql_mvt);
					}
				}
			}
		}

		// 2. Aplicar descuento de stock de los nuevos materiales
		$new_mats = getMaterialesFromReparacionNotas($nuevas_lineas_json);
		foreach ($new_mats as $nm) {
			$fk_mat = !empty($nm['fk_material']) ? (int) $nm['fk_material'] : 0;
			$cant = (float) (!empty($nm['cantidad']) ? $nm['cantidad'] : 0);
			$desc_clean = trim(!empty($nm['descripcion']) ? $nm['descripcion'] : '');
			$code_clean = !empty($nm['codigo']) ? trim($nm['codigo']) : '';

			if ($fk_mat <= 0 && (!empty($desc_clean) || !empty($code_clean))) {
				$res_search = $db->query("SELECT rowid, stock, fk_product FROM " . $table_material . " WHERE (ref = '" . $db->escape($code_clean) . "' AND ref != '') OR nombre = '" . $db->escape($desc_clean) . "' LIMIT 1");
				if ($res_search && ($s_obj = $db->fetch_object($res_search))) {
					$fk_mat = (int) $s_obj->rowid;
					$fk_prod = (int) $s_obj->fk_product;
				} elseif (!empty($nm['guardar_catalogo'])) {
					$fk_prod = tallerpro_get_or_create_product($code_clean, $desc_clean, (float) ($nm['precio'] ?? 0), 0, null);
					$sql_new_m = "INSERT INTO " . $table_material . " (nombre, ref, fk_product, stock, precio, date_creation, fk_user_creat, entity, status) VALUES (";
					$sql_new_m .= "'" . $db->escape($desc_clean) . "', '" . $db->escape($code_clean) . "', " . ($fk_prod > 0 ? (int)$fk_prod : "NULL") . ", 0, " . ((float) ($nm['precio'] ?? 0)) . ", '" . $db->idate(dol_now()) . "', " . ((int) $user_id) . ", " . ((int) $entity) . ", 1)";
					if ($db->query($sql_new_m)) {
						$fk_mat = (int) $db->last_insert_id($table_material);
					}
				}
			}

			if ($fk_mat > 0 && $cant > 0) {
				$res_cur = $db->query("SELECT stock, fk_product FROM " . $table_material . " WHERE rowid = " . $fk_mat);
				$cur_stock = 0.0;
				$fk_prod = 0;
				if ($res_cur && ($obj_cur = $db->fetch_object($res_cur))) {
					$cur_stock = (float) $obj_cur->stock;
					$fk_prod = (int) $obj_cur->fk_product;
				}
				if ($fk_prod <= 0) {
					$fk_prod = tallerpro_get_or_create_product($code_clean, $desc_clean, (float) ($nm['precio'] ?? 0), 0, null);
					if ($fk_prod > 0) {
						$db->query("UPDATE " . $table_material . " SET fk_product = " . $fk_prod . " WHERE rowid = " . $fk_mat);
					}
				}

				// Descontar en Almacén nativo Dolibarr
				if ($fk_prod > 0) {
					tallerpro_record_stock_movement($fk_prod, -$cant, 'Consumo en OT #' . str_pad($rep_id, 4, '0', STR_PAD_LEFT), 'reparacion', $rep_id, null);
				}

				$new_stock = $cur_stock - $cant;
				$db->query("UPDATE " . $table_material . " SET stock = " . $new_stock . " WHERE rowid = " . $fk_mat);

				$sql_mvt = "INSERT INTO " . $table_mvt . " (fk_material, cantidad, tipo, fk_reparacion, stock_anterior, stock_posterior, fecha, fk_user, descripcion, entity) VALUES (";
				$sql_mvt .= $fk_mat . ", -" . $cant . ", 'reparacion_consumo', " . ((int) $rep_id) . ", " . $cur_stock . ", " . $new_stock . ", '" . $db->idate(dol_now()) . "', " . ((int) $user_id) . ", 'Consumo en OT #" . str_pad($rep_id, 4, '0', STR_PAD_LEFT) . "', " . ((int) $entity) . ")";
				$db->query($sql_mvt);
			}
		}
	}
}

/**
 * Revertir totalmente el stock al eliminar una reparación
 */
if (!function_exists('revertirStockReparacion')) {
	function revertirStockReparacion($db, $rep_id, $user_id = 1, $entity = 1) {
		$table_material = MAIN_DB_PREFIX . "taller_material";
		$table_reparacion = MAIN_DB_PREFIX . "taller_reparacion";
		$table_mvt = MAIN_DB_PREFIX . "taller_stock_mvt";

		$res_rep = $db->query("SELECT notas FROM " . $table_reparacion . " WHERE rowid = " . ((int) $rep_id));
		if ($res_rep && ($rep_obj = $db->fetch_object($res_rep))) {
			$mats = getMaterialesFromReparacionNotas($rep_obj->notas);
			foreach ($mats as $m) {
				$fk_mat = !empty($m['fk_material']) ? (int) $m['fk_material'] : 0;
				$cant = (float) (!empty($m['cantidad']) ? $m['cantidad'] : 0);
				if ($cant > 0) {
					$fk_prod = 0;
					if ($fk_mat > 0) {
						$res_cur = $db->query("SELECT stock, fk_product FROM " . $table_material . " WHERE rowid = " . $fk_mat);
						if ($res_cur && ($obj_cur = $db->fetch_object($res_cur))) {
							$fk_prod = (int) $obj_cur->fk_product;
						}
					}
					if ($fk_prod <= 0 && !empty($m['codigo'])) {
						$res_p = $db->query("SELECT fk_product, rowid FROM " . $table_material . " WHERE ref = '" . $db->escape(trim($m['codigo'])) . "' LIMIT 1");
						if ($res_p && ($p_obj = $db->fetch_object($res_p))) {
							$fk_prod = (int) $p_obj->fk_product;
							if ($fk_mat <= 0) $fk_mat = (int) $p_obj->rowid;
						}
					}

					// Reintegrar en Almacén nativo Dolibarr
					if ($fk_prod > 0) {
						tallerpro_record_stock_movement($fk_prod, $cant, 'Devolución por eliminación de OT #' . str_pad($rep_id, 4, '0', STR_PAD_LEFT), 'reparacion', $rep_id, null);
					}

					if ($fk_mat > 0) {
						$res_cur = $db->query("SELECT stock FROM " . $table_material . " WHERE rowid = " . $fk_mat);
						$cur_stock = 0.0;
						if ($res_cur && ($obj_cur = $db->fetch_object($res_cur))) {
							$cur_stock = (float) $obj_cur->stock;
						}
						$new_stock = $cur_stock + $cant;
						$db->query("UPDATE " . $table_material . " SET stock = " . $new_stock . " WHERE rowid = " . $fk_mat);

						$sql_mvt = "INSERT INTO " . $table_mvt . " (fk_material, cantidad, tipo, fk_reparacion, stock_anterior, stock_posterior, fecha, fk_user, descripcion, entity) VALUES (";
						$sql_mvt .= $fk_mat . ", " . $cant . ", 'reparacion_devolucion', " . ((int) $rep_id) . ", " . $cur_stock . ", " . $new_stock . ", '" . $db->idate(dol_now()) . "', " . ((int) $user_id) . ", 'Devolución por eliminación de OT #" . str_pad($rep_id, 4, '0', STR_PAD_LEFT) . "', " . ((int) $entity) . ")";
						$db->query($sql_mvt);
					}
				}
			}
		}
	}
}

$action = GETPOST('action', 'alpha');
if (empty($action) && !empty($_POST['action'])) {
	$action = $_POST['action'];
}



// Acción: Crear Reparación
if ($action == 'create_reparacion' || $action == 'add_reparacion') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		accessforbidden('Method not allowed');
	}
	$token = GETPOST('token', 'alpha');
	$valid_token = (!empty($_SESSION['token']) && $token === $_SESSION['token']) || (!empty($_SESSION['newtoken']) && $token === $_SESSION['newtoken']);
	if (!$valid_token) {
		accessforbidden('Invalid security token');
	}

	$fk_vehiculo = GETPOSTINT('fk_vehiculo');
	if (empty($fk_vehiculo) && !empty($_POST['fk_vehiculo'])) {
		$fk_vehiculo = (int) $_POST['fk_vehiculo'];
	}

	$fecha = trim(GETPOST('fecha', 'alpha'));
	if (empty($fecha) && !empty($_POST['fecha'])) {
		$fecha = trim($_POST['fecha']);
	}
	if (empty($fecha)) {
		$fecha = date('Y-m-d');
	}

	$descripcion = trim(GETPOST('descripcion', 'restricthtml'));
	if (empty($descripcion) && !empty($_POST['descripcion'])) {
		$descripcion = trim($_POST['descripcion']);
	}

	$coste_mo = GETPOSTFLOAT('coste_mo');
	if ($coste_mo === null || $coste_mo === '') {
		$coste_mo = isset($_POST['coste_mo']) ? (float) $_POST['coste_mo'] : 0;
	}

	$lineas_json = !empty($_POST['lineas_json']) ? trim($_POST['lineas_json']) : '';
	$notas = !empty($lineas_json) ? $lineas_json : trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) {
		$notas = trim($_POST['notas']);
	}

	$estado = trim(GETPOST('estado', 'alpha'));
	if (empty($estado) && !empty($_POST['estado'])) {
		$estado = trim($_POST['estado']);
	}
	if (empty($estado)) {
		$estado = 'en_curso';
	}

	if ($fk_vehiculo <= 0) {
		setEventMessages($langs->trans("ErrorFieldRequired", "Vehículo"), null, 'errors');
	} else {
		$sql_insert = "INSERT INTO " . $table_reparacion . " (";
		$sql_insert .= "fk_vehiculo, fecha, descripcion, coste_mo, notas, estado, date_creation, fk_user_creat, entity, status";
		$sql_insert .= ") VALUES (";
		$sql_insert .= ((int) $fk_vehiculo) . ", ";
		$sql_insert .= "'" . $db->escape($fecha) . "', ";
		$sql_insert .= "'" . $db->escape($descripcion) . "', ";
		$sql_insert .= ((float) $coste_mo) . ", ";
		$sql_insert .= "'" . $db->escape($notas) . "', ";
		$sql_insert .= "'" . $db->escape($estado) . "', ";
		$sql_insert .= "'" . $db->idate(dol_now()) . "', ";
		$sql_insert .= ((int) $user->id) . ", ";
		$sql_insert .= ((int) $conf->entity) . ", ";
		$sql_insert .= "1";
		$sql_insert .= ")";

		$resql = $db->query($sql_insert);
		if ($resql) {
			$new_rep_id = (int) $db->last_insert_id($table_reparacion);
			if ($new_rep_id > 0 && !empty($notas)) {
				aplicarStockReparacion($db, $new_rep_id, $notas, null, $user->id, $conf->entity);
			}
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF'] . "?msg=saved");
			exit;
		} else {
			dol_syslog("TallerPro - Error al guardar reparación: " . $db->lasterror(), LOG_ERR);
			setEventMessages("Error al guardar la reparación", null, 'errors');
		}
	}
}

// Acción: Actualizar Reparación
if ($action == 'update_reparacion' || $action == 'edit_reparacion') {
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

	$fk_vehiculo = GETPOSTINT('fk_vehiculo');
	if (empty($fk_vehiculo) && !empty($_POST['fk_vehiculo'])) {
		$fk_vehiculo = (int) $_POST['fk_vehiculo'];
	}

	$fecha = trim(GETPOST('fecha', 'alpha'));
	if (empty($fecha) && !empty($_POST['fecha'])) {
		$fecha = trim($_POST['fecha']);
	}
	if (empty($fecha)) {
		$fecha = date('Y-m-d');
	}

	$descripcion = trim(GETPOST('descripcion', 'restricthtml'));
	if (empty($descripcion) && !empty($_POST['descripcion'])) {
		$descripcion = trim($_POST['descripcion']);
	}

	$coste_mo = GETPOSTFLOAT('coste_mo');
	if ($coste_mo === null || $coste_mo === '') {
		$coste_mo = isset($_POST['coste_mo']) ? (float) $_POST['coste_mo'] : 0;
	}

	$lineas_json = !empty($_POST['lineas_json']) ? trim($_POST['lineas_json']) : '';
	$notas = !empty($lineas_json) ? $lineas_json : trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) {
		$notas = trim($_POST['notas']);
	}

	$estado = trim(GETPOST('estado', 'alpha'));
	if (empty($estado) && !empty($_POST['estado'])) {
		$estado = trim($_POST['estado']);
	}
	if (empty($estado)) {
		$estado = 'en_curso';
	}

	if ($fk_vehiculo <= 0) {
		setEventMessages($langs->trans("ErrorFieldRequired", "Vehículo"), null, 'errors');
	} elseif ($rowid <= 0) {
		setEventMessages("Identificador de reparación no válido", null, 'errors');
	} else {
		$prev_notas = null;
		$res_prev = $db->query("SELECT notas FROM " . $table_reparacion . " WHERE rowid = " . ((int) $rowid));
		if ($res_prev && ($p_obj = $db->fetch_object($res_prev))) {
			$prev_notas = $p_obj->notas;
		}

		$sql_update = "UPDATE " . $table_reparacion . " SET ";
		$sql_update .= "fk_vehiculo = " . ((int) $fk_vehiculo) . ", ";
		$sql_update .= "fecha = '" . $db->escape($fecha) . "', ";
		$sql_update .= "descripcion = '" . $db->escape($descripcion) . "', ";
		$sql_update .= "coste_mo = " . ((float) $coste_mo) . ", ";
		$sql_update .= "estado = '" . $db->escape($estado) . "', ";
		$sql_update .= "notas = '" . $db->escape($notas) . "' ";
		$sql_update .= "WHERE rowid = " . ((int) $rowid) . " AND entity IN (" . getEntity('taller_reparacion') . ")";

		$resql = $db->query($sql_update);
		if ($resql) {
			aplicarStockReparacion($db, $rowid, $notas, $prev_notas, $user->id, $conf->entity);
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF'] . "?msg=saved");
			exit;
		} else {
			dol_syslog("TallerPro - Error al actualizar reparación ID " . $rowid . ": " . $db->lasterror(), LOG_ERR);
			setEventMessages("Error al actualizar la reparación", null, 'errors');
		}
	}
}

// Acción: Cambiar Estado directamente desde la tabla
if ($action == 'toggle_estado' || $action == 'update_estado') {
	$token = GETPOST('token', 'alpha');
	$valid_token = (!empty($_SESSION['token']) && $token === $_SESSION['token']) || (!empty($_SESSION['newtoken']) && $token === $_SESSION['newtoken']);
	if (!$valid_token) {
		if (GETPOST('ajax', 'int') || !empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
			header('Content-Type: application/json');
			echo json_encode(array('success' => false, 'error' => 'Token de seguridad inválido'));
			exit;
		}
		accessforbidden('Invalid security token');
	}

	$rowid = GETPOSTINT('rowid');
	if (empty($rowid) && !empty($_POST['rowid'])) {
		$rowid = (int) $_POST['rowid'];
	}
	$new_estado = trim(GETPOST('new_estado', 'alpha'));
	if (empty($new_estado) && !empty($_POST['new_estado'])) {
		$new_estado = trim($_POST['new_estado']);
	}
	if ($rowid > 0) {
		if (empty($new_estado)) {
			$res_chk = $db->query("SELECT estado FROM " . $table_reparacion . " WHERE rowid = " . ((int) $rowid));
			if ($res_chk && ($chk_obj = $db->fetch_object($res_chk))) {
				$new_estado = ($chk_obj->estado === 'finalizada' || $chk_obj->estado === 'finalizado') ? 'en_curso' : 'finalizada';
			} else {
				$new_estado = 'finalizada';
			}
		}
		$db->query("UPDATE " . $table_reparacion . " SET estado = '" . $db->escape($new_estado) . "' WHERE rowid = " . ((int) $rowid) . " AND entity IN (" . getEntity('taller_reparacion') . ")");
	}
	if (GETPOST('ajax', 'int') || !empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
		header('Content-Type: application/json');
		echo json_encode(array('success' => true, 'new_estado' => $new_estado));
		exit;
	}
	header("Location: " . $_SERVER['PHP_SELF'] . "?msg=status_updated");
	exit;
}

// Acción: Facturar Reparación (Convierte la orden de reparación en Factura oficial de Dolibarr)
if ($action == 'facturar_reparacion' || $action == 'facturar') {
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
		require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

		// 1. Obtener la reparación con su vehículo y cliente
		$sql_r = "SELECT r.*, v.fk_soc, v.matricula, v.marca, v.modelo ";
		$sql_r .= "FROM " . $table_reparacion . " as r ";
		$sql_r .= "LEFT JOIN " . $table_vehiculo . " as v ON r.fk_vehiculo = v.rowid ";
		$sql_r .= "WHERE r.rowid = " . ((int) $rowid);
		$res_r = $db->query($sql_r);

		if ($res_r && ($rep = $db->fetch_object($res_r))) {
			// Si ya tiene factura asociada, redirigir a la vista de la factura
			if (!empty($rep->ref_factura)) {
				$sql_f_check = "SELECT rowid FROM " . MAIN_DB_PREFIX . "facture WHERE ref = '" . $db->escape($rep->ref_factura) . "' AND entity IN (" . getEntity('facture') . ")";
				$res_f_check = $db->query($sql_f_check);
				if ($res_f_check && ($f_exist = $db->fetch_object($res_f_check))) {
					setEventMessages("Esta reparación ya tiene emitida la Factura " . $rep->ref_factura, null, 'mesgs');
					header("Location: " . dol_buildpath('/taller/facturas.php', 1) . '?id=' . ((int) $f_exist->rowid));
					exit;
				}
			}

			if (empty($rep->fk_soc)) {
				setEventMessages("El vehículo asociado a esta reparación no tiene ningún cliente asignado", null, 'errors');
			} else {
				$fac = new Facture($db);
				$fac->socid = (int) $rep->fk_soc;
				$fac->date = dol_now();
				$fac->type = Facture::TYPE_STANDARD;
				$ot_num = 'OT #' . str_pad($rep->rowid, 4, '0', STR_PAD_LEFT);

				// Extraer exclusivamente las notas del usuario (sin código ni JSON filtrado)
				$user_nota = '';
				if (!empty($rep->notas)) {
					if ($rep->notas[0] === '{' || $rep->notas[0] === '[') {
						$data_notas = json_decode($rep->notas, true);
						if (!empty($data_notas['notas_usuario'])) {
							$user_nota = trim($data_notas['notas_usuario']);
						} elseif (!empty($data_notas['notas']) && is_string($data_notas['notas'])) {
							$user_nota = trim($data_notas['notas']);
						}
					} else {
						$user_nota = trim($rep->notas);
					}
				}
				$fac->note_public = !empty($user_nota) ? $user_nota : '';
				$fac->array_options['options_fk_vehiculo'] = (int) $rep->fk_vehiculo;

				$fac_id = $fac->create($user);
				if ($fac_id > 0) {
					$lineas = !empty($rep->notas) ? json_decode($rep->notas, true) : null;
					if (!empty($lineas) && is_array($lineas) && (!empty($lineas['materiales']) || !empty($lineas['reparaciones']))) {
						if (!empty($lineas['materiales'])) {
							foreach ($lineas['materiales'] as $m) {
								$p_cant = (float) (!empty($m['cantidad']) ? $m['cantidad'] : 1);
								$p_precio = (float) (!empty($m['precio']) ? $m['precio'] : 0);
								$p_desc = (float) (!empty($m['descuento']) ? $m['descuento'] : 0);
								$p_desc_txt = !empty($m['descripcion']) ? $m['descripcion'] : 'Material';
								$p_code = !empty($m['codigo']) ? trim($m['codigo']) : '';

								$p_fk_product = 0;
								if (!empty($m['fk_material'])) {
									$res_pm = $db->query("SELECT fk_product FROM " . MAIN_DB_PREFIX . "taller_material WHERE rowid = " . ((int) $m['fk_material']));
									if ($res_pm && ($pm_obj = $db->fetch_object($res_pm))) {
										$p_fk_product = (int) $pm_obj->fk_product;
									}
								}
								if ($p_fk_product <= 0) {
									$p_fk_product = tallerpro_get_or_create_product($p_code, $p_desc_txt, $p_precio, 0, $user);
								}

								$fac->addline($p_desc_txt, $p_precio, $p_cant, 21.0, 0, 0, $p_fk_product, $p_desc, '', '', 0, 0, 0, 'HT', 0, 0, -1, 0, '', 0, 0, null, 0, '', array(), 100, 0, null, 0, $p_code, 0);
							}
						}
						if (!empty($lineas['reparaciones'])) {
							foreach ($lineas['reparaciones'] as $r) {
								$r_precio = (float) (!empty($r['precio']) ? $r['precio'] : 0);
								$r_desc = (float) (!empty($r['descuento']) ? $r['descuento'] : 0);
								$r_desc_txt = !empty($r['descripcion']) ? $r['descripcion'] : 'Mano de obra';
								$fac->addline($r_desc_txt, $r_precio, 1, 21.0, 0, 0, 0, $r_desc, '', '', 0, 0, 0, 'HT', 0, 1);
							}
						}
					} else {
						// Añadir línea de mano de obra / trabajo realizado
						$desc_mo = !empty($rep->descripcion) ? $rep->descripcion : ("Mano de obra reparación " . $ot_num);
						$coste_mo = (float) $rep->coste_mo;
						if ($coste_mo <= 0) $coste_mo = 50.0;
						$tva_tx = 21.0;
						$fac->addline($desc_mo, $coste_mo, 1, $tva_tx, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1);
					}

					// La factura permanece en estado Borrador (permite editarla y borrarla)
					// Actualizar la reparación con la referencia borrador de la factura y marcarla como finalizada
					$db->query("UPDATE " . $table_reparacion . " SET ref_factura = '" . $db->escape($fac->ref) . "', estado = 'finalizada' WHERE rowid = " . ((int) $rowid));

					setEventMessages("Factura borrador " . $fac->ref . " creada con éxito a partir de la reparación", null, 'mesgs');
					header("Location: " . dol_buildpath('/taller/facturas.php', 1) . '?id=' . $fac_id);
					exit;
				} else {
					dol_syslog("TallerPro - Error al generar factura desde reparación: " . $fac->error . " " . implode(' ', $fac->errors), LOG_ERR);
					setEventMessages("Error al generar la factura en Dolibarr", null, 'errors');
				}
			}
		} else {
			setEventMessages("No se encontró la reparación indicada", null, 'errors');
		}
	}
}

// Acción: Guardar cambios y Facturar desde el modal
if ($action == 'save_and_facturar') {
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
	$fk_vehiculo = GETPOSTINT('fk_vehiculo');
	if (empty($fk_vehiculo) && !empty($_POST['fk_vehiculo'])) {
		$fk_vehiculo = (int) $_POST['fk_vehiculo'];
	}
	$fecha = trim(GETPOST('fecha', 'alpha'));
	if (empty($fecha) && !empty($_POST['fecha'])) $fecha = trim($_POST['fecha']);
	if (empty($fecha)) $fecha = date('Y-m-d');

	$descripcion = trim(GETPOST('descripcion', 'restricthtml'));
	if (empty($descripcion) && !empty($_POST['descripcion'])) $descripcion = trim($_POST['descripcion']);

	$coste_mo = GETPOSTFLOAT('coste_mo');
	if ($coste_mo === null || $coste_mo === '') $coste_mo = isset($_POST['coste_mo']) ? (float) $_POST['coste_mo'] : 0;

	$lineas_json = !empty($_POST['lineas_json']) ? trim($_POST['lineas_json']) : '';
	$notas = !empty($lineas_json) ? $lineas_json : trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) $notas = trim($_POST['notas']);

	if ($fk_vehiculo <= 0) {
		setEventMessages("Debe seleccionar un vehículo para la reparación", null, 'errors');
	} else {
		$target_rep_id = 0;
		if ($rowid > 0) {
			$prev_notas = null;
			$res_prev = $db->query("SELECT notas FROM " . $table_reparacion . " WHERE rowid = " . ((int) $rowid));
			if ($res_prev && ($p_obj = $db->fetch_object($res_prev))) {
				$prev_notas = $p_obj->notas;
			}
			$sql_upd = "UPDATE " . $table_reparacion . " SET fk_vehiculo = " . ((int)$fk_vehiculo) . ", fecha = '" . $db->escape($fecha) . "', descripcion = '" . $db->escape($descripcion) . "', coste_mo = " . ((float)$coste_mo) . ", estado = 'finalizada', notas = '" . $db->escape($notas) . "' WHERE rowid = " . ((int)$rowid);
			$db->query($sql_upd);
			$target_rep_id = $rowid;
			aplicarStockReparacion($db, $target_rep_id, $notas, $prev_notas, $user->id, $conf->entity);
		} else {
			$sql_ins = "INSERT INTO " . $table_reparacion . " (fk_vehiculo, fecha, descripcion, coste_mo, notas, estado, date_creation, fk_user_creat, entity, status) VALUES (";
			$sql_ins .= ((int)$fk_vehiculo) . ", '" . $db->escape($fecha) . "', '" . $db->escape($descripcion) . "', " . ((float)$coste_mo) . ", '" . $db->escape($notas) . "', 'finalizada', '" . $db->idate(dol_now()) . "', " . ((int)$user->id) . ", " . ((int)$conf->entity) . ", 1)";
			$db->query($sql_ins);
			$target_rep_id = (int) $db->last_insert_id($table_reparacion);
			if ($target_rep_id > 0 && !empty($notas)) {
				aplicarStockReparacion($db, $target_rep_id, $notas, null, $user->id, $conf->entity);
			}
		}

		$token_val = (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : ''));
		header("Location: " . $_SERVER['PHP_SELF'] . "?action=facturar_reparacion&rowid=" . $target_rep_id . "&token=" . $token_val);
		exit;
	}
}

// Acción: Eliminar Reparación (Seguro vía POST con token)
if ($action == 'delete_reparacion' || $action == 'delete') {
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
		revertirStockReparacion($db, $rowid, $user->id, $conf->entity);
		$sql_del = "UPDATE " . $table_reparacion . " SET status = 0 WHERE rowid = " . ((int) $rowid) . " AND entity IN (" . getEntity('taller_reparacion') . ")";
		$resql = $db->query($sql_del);
	}
	if (GETPOST('ajax', 'int') || !empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
		header('Content-Type: application/json');
		echo json_encode(array('success' => true));
		exit;
	}
	if ($resql) {
		setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		header("Location: " . $_SERVER['PHP_SELF'] . "?msg=deleted");
		exit;
	} else {
		dol_syslog("TallerPro - Error al eliminar reparación ID " . $rowid . ": " . $db->lasterror(), LOG_ERR);
		setEventMessages("Error al eliminar la reparación", null, 'errors');
	}
}

// Consultar vehículos con su cliente para el desplegable
$vehicles_list = array();
$sql_v = "SELECT v.rowid, v.matricula, v.marca, v.modelo, s.nom as cliente_nombre ";
$sql_v .= "FROM " . $table_vehiculo . " as v ";
$sql_v .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON v.fk_soc = s.rowid ";
$sql_v .= "WHERE v.status = 1 AND v.entity IN (" . getEntity('taller_vehiculo') . ") ORDER BY v.matricula ASC";
$res_v = $db->query($sql_v);
if ($res_v) {
	while ($v = $db->fetch_object($res_v)) {
		$vehicles_list[] = $v;
	}
}

// Consultar materiales para el desplegable del modal con stock nativo de Dolibarr
$wh_id_rep = tallerpro_get_default_warehouse($user);
$materials_list = array();
$sql_m = "SELECT tm.rowid, tm.ref, tm.nombre, tm.precio, tm.stock, tm.fk_product, ps.reel as wh_stock ";
$sql_m .= "FROM " . $table_material . " tm ";
$sql_m .= "LEFT JOIN " . MAIN_DB_PREFIX . "product_stock ps ON ps.fk_product = tm.fk_product AND ps.fk_entrepot = " . ((int) $wh_id_rep) . " ";
$sql_m .= "WHERE tm.status = 1 AND tm.entity IN (" . getEntity('taller_material') . ") ORDER BY tm.nombre ASC";
$res_m = $db->query($sql_m);
if ($res_m) {
	while ($m = $db->fetch_object($res_m)) {
		$m->stock = ($m->wh_stock !== null) ? (float) $m->wh_stock : (float) $m->stock;
		$materials_list[] = $m;
	}
}

// Consultar reparaciones con JOIN a vehículos y cliente
$reparaciones_list = array();
$sql_r = "SELECT r.rowid, r.fk_vehiculo, r.fecha, r.ref_factura, r.descripcion, r.coste_mo, r.notas, r.estado, ";
$sql_r .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo, ";
$sql_r .= "s.nom as cliente_nombre ";
$sql_r .= "FROM " . $table_reparacion . " as r ";
$sql_r .= "LEFT JOIN " . $table_vehiculo . " as v ON r.fk_vehiculo = v.rowid ";
$sql_r .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON v.fk_soc = s.rowid ";
$sql_r .= "WHERE r.status = 1 AND r.entity IN (" . getEntity('taller_reparacion') . ") ";
$sql_r .= "ORDER BY r.fecha DESC, r.rowid DESC";
$res_r = $db->query($sql_r);
if ($res_r) {
	while ($r = $db->fetch_object($res_r)) {
		$reparaciones_list[] = $r;
	}
}
$total_reparaciones = count($reparaciones_list);
$preselected_vehiculo = GETPOSTINT('new_vehiculo');

$css_file = __DIR__ . '/css/tallerpro.css';
$js_file = __DIR__ . '/js/tallerpro.js';
$css_version = file_exists($css_file) ? filemtime($css_file) : dol_now();
$js_version = file_exists($js_file) ? filemtime($js_file) : dol_now();

$css_url = dol_buildpath('/taller/css/tallerpro.css', 1) . '?v=' . $css_version;
$js_url = dol_buildpath('/taller/js/tallerpro.js', 1) . '?v=' . $js_version;

$morehead = '<link rel="stylesheet" type="text/css" href="' . $css_url . '">' . "\n";
$morehead .= '<script src="' . $js_url . '"></script>' . "\n";

// Page Header
llxHeader($morehead, 'Reparaciones - TallerPro', '', '', 0, 0, array(), array(), '', 'mod-taller page-tallerpro');

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
						<a href="<?php echo $reparaciones_url; ?>" class="tallerpro-nav-item active">
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
					<span class="tallerpro-topbar-breadcrumb-current"><?php echo $langs->trans("Reparaciones"); ?></span>
				</div>
			</div>
			<div class="tallerpro-topbar-right"></div>
		</header>

		<!-- Dashboard Content -->
		<div class="tallerpro-content-wrapper">
			<main class="tallerpro-content">
				<!-- Header Actions & Title -->
				<div class="tallerpro-header-actions" style="margin-bottom: 20px;">
					<div class="tallerpro-content-header" style="margin-bottom: 0;">
						<h1 class="tallerpro-page-title" style="font-size: 26px; font-weight: 700; color: #0f172a; margin: 0 0 4px 0;"><?php echo $langs->trans("Reparaciones"); ?></h1>
						<p class="tallerpro-page-subtitle" id="reparacionesCountSubtitle" style="color: #64748b; font-size: 13.5px; margin: 0;"><?php echo !empty($reparaciones_list) ? count($reparaciones_list) : 4; ?> órdenes registradas</p>
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

				<!-- Filter Bar Card -->
				<div class="tallerpro-reparaciones-filter-card">
					<div class="tallerpro-filter-search-box">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="11" cy="11" r="8"></circle>
							<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
						</svg>
						<input type="text" id="tallerSearchReparaciones" class="tallerpro-filter-search-input" placeholder="Buscar por matrícula o cliente...">
					</div>
					<div class="tallerpro-filter-state-group">
						<span class="tallerpro-filter-state-label">Filtrar estado:</span>
						<select id="tallerFilterEstado" class="tallerpro-filter-state-select">
							<option value="all">Todos los estados</option>
							<option value="en_curso">En curso</option>
							<option value="finalizada">Finalizada</option>
						</select>
					</div>
				</div>

				<!-- Table Card -->
				<div class="tallerpro-table-card">
					<table class="tallerpro-table" id="tallerReparacionesTable">
						<thead>
							<tr>
								<th style="width: 14%;">FECHA / REF</th>
								<th style="width: 20%;">VEHÍCULO &amp; CLIENTE</th>
								<th style="width: 15%;">INTERVENCIÓN PRINCIPAL</th>
								<th style="width: 12%;">ESTADO</th>
								<th style="width: 10%;">COSTE EST.</th>
								<th style="width: 19%; text-align: right;">ACCIONES</th>
							</tr>
						</thead>

						<tbody id="reparacionesTableBody">
							<?php if (!empty($reparaciones_list)): ?>
								<?php foreach ($reparaciones_list as $rep): ?>
									<?php
									$rep_estado = !empty($rep->estado) ? $rep->estado : 'en_curso';
									$is_finalizada = ($rep_estado === 'finalizada' || $rep_estado === 'finalizado');
									$search_text = strtolower(dol_escape_htmltag(($rep->vehiculo_matricula ?: '') . ' ' . ($rep->vehiculo_marca ?: '') . ' ' . ($rep->cliente_nombre ?: '') . ' ' . ($rep->descripcion ?: '')));
									$rep_ref = !empty($rep->ref_factura) ? dol_escape_htmltag($rep->ref_factura) : ('OT #' . str_pad($rep->rowid, 4, '0', STR_PAD_LEFT));

									$rep_total_display = (float) $rep->coste_mo;
									if (!empty($rep->notas) && ($rep->notas[0] === '{' || $rep->notas[0] === '[')) {
										$p_notas = json_decode($rep->notas, true);
										if (!empty($p_notas)) {
											if (isset($p_notas['total'])) {
												$rep_total_display = (float) $p_notas['total'];
											} else {
												$m_tot = 0;
												if (!empty($p_notas['materiales'])) {
													foreach ($p_notas['materiales'] as $m) $m_tot += (float) ($m['total_neto'] ?? 0);
												}
												$r_tot = 0;
												if (!empty($p_notas['reparaciones'])) {
													foreach ($p_notas['reparaciones'] as $r) $r_tot += (float) ($r['total_neto'] ?? 0);
												}
												$desc = (float) ($p_notas['descuento_global'] ?? 0);
												$iva_p = (float) ($p_notas['iva_porcentaje'] ?? 21);
												$base = max(0, ($m_tot + $r_tot) - $desc);
												$rep_total_display = $base * (1 + ($iva_p / 100));
											}
										}
									}
									$token_val = (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : (function_exists('newToken') ? newToken() : '')));
									$facturar_url = $_SERVER['PHP_SELF'] . '?action=facturar_reparacion&rowid=' . ((int) $rep->rowid) . '&token=' . $token_val;
									?>
									<tr class="rep-row" data-id="<?php echo (int) $rep->rowid; ?>" data-estado="<?php echo $is_finalizada ? 'finalizada' : 'en_curso'; ?>" data-has-factura="<?php echo !empty($rep->ref_factura) ? '1' : '0'; ?>" data-facturar-url="<?php echo $facturar_url; ?>" data-search="<?php echo $search_text; ?>">
										<td>
											<div style="font-weight: 600; color: #1e293b;"><?php echo dol_escape_htmltag($rep->fecha); ?></div>
											<div style="font-size: 11.5px; color: #94a3b8; margin-top: 2px;">
												<?php echo $rep_ref; ?>
											</div>
										</td>
										<td>
											<div style="font-weight: 700; color: #0f172a;"><?php echo dol_escape_htmltag($rep->vehiculo_matricula ?: '—'); ?></div>
											<div style="font-size: 12px; color: #64748b; margin-top: 2px;">
												<?php echo dol_escape_htmltag(trim(($rep->vehiculo_marca ?: '') . ' ' . ($rep->vehiculo_modelo ?: ''))); ?>
												<?php if (!empty($rep->cliente_nombre)): ?>
													<span style="color: #cbd5e1;">·</span> <span style="color: #0f3ba8; font-weight: 500;"><?php echo dol_escape_htmltag($rep->cliente_nombre); ?></span>
												<?php endif; ?>
											</div>
										</td>
										
										<td style="color: #334155; font-size: 13.5px;" title="<?php echo dol_escape_htmltag($rep->descripcion ?: ''); ?>">
											<div class="tallerpro-truncate">
												<?php echo dol_escape_htmltag($rep->descripcion ?: '—'); ?>
											</div>
										</td>

										<td>
											<select class="tallerpro-status-select status-<?php echo $is_finalizada ? 'finalizada' : 'en_curso'; ?>" data-id="<?php echo (int) $rep->rowid; ?>" title="Cambiar estado de reparación">
												<option value="en_curso" <?php echo !$is_finalizada ? 'selected' : ''; ?>>En curso</option>
												<option value="finalizada" <?php echo $is_finalizada ? 'selected' : ''; ?>>Finalizada</option>
											</select>
										</td>
										<td style="font-weight: 600; color: #1e293b;"><?php echo number_format($rep_total_display, 2, '.', '') . ' €'; ?></td>
										<td>


											<div class="tallerpro-actions">
												<?php if (!empty($rep->ref_factura)): ?>
													<a href="<?php echo dol_buildpath('/taller/facturas.php', 1) . '?search_q=' . urlencode($rep->ref_factura); ?>" class="tallerpro-btn-facturada-link" title="Ver factura <?php echo dol_escape_htmltag($rep->ref_factura); ?>">
														<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
															<polyline points="14 2 14 8 20 8"></polyline>
														</svg>
														<span><?php echo dol_escape_htmltag($rep->ref_factura); ?></span>
													</a>
												<?php elseif ($is_finalizada): ?>
													<a href="<?php echo $facturar_url; ?>"
														class="tallerpro-btn-facturar-action"
														data-id="<?php echo (int) $rep->rowid; ?>"
														data-facturar-url="<?php echo $facturar_url; ?>"
														title="Emitir Factura oficial de esta reparación">
														<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
															<polyline points="14 2 14 8 20 8"></polyline>
															<line x1="16" y1="13" x2="8" y2="13"></line>
															<line x1="16" y1="17" x2="8" y2="17"></line>
															<polyline points="10 9 9 9 8 9"></polyline>
														</svg>
														<span>Facturar</span>
													</a>
												<?php endif; ?>
												<button type="button" class="tallerpro-action-btn btn-edit-reparacion" title="Editar reparación"
													data-id="<?php echo (int) $rep->rowid; ?>"
													data-vehiculo="<?php echo (int) $rep->fk_vehiculo; ?>"
													data-fecha="<?php echo dol_escape_htmltag($rep->fecha); ?>"
													data-estado="<?php echo dol_escape_htmltag($rep_estado); ?>"
													data-descripcion="<?php echo dol_escape_htmltag($rep->descripcion); ?>"
													data-coste="<?php echo number_format((float) $rep->coste_mo, 2, '.', ''); ?>"
													data-notas="<?php echo dol_escape_htmltag($rep->notas); ?>"
													data-factura="<?php echo dol_escape_htmltag($rep->ref_factura ?: ''); ?>"
													data-ref="<?php echo $rep_ref; ?>">
													<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
														<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
													</svg>
												</button>
												<button type="button" class="tallerpro-action-btn btn-delete-reparacion" data-id="<?php echo (int) $rep->rowid; ?>" title="Eliminar reparación">
													<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
														<polyline points="3 6 5 6 21 6"></polyline>
														<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
													</svg>
												</button>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else: ?>
								<!-- Row 1: Finalizada con botón Facturar -->
								<tr class="rep-row" data-id="mock-1" data-estado="finalizada" data-search="7422njy ivan lopez cambio de embrague y valvulina de caja">
									<td>
										<div style="font-weight: 600; color: #1e293b;">2026-10-27</div>
										<div style="font-size: 11.5px; color: #94a3b8; margin-top: 2px;">OT #0123</div>
									</td>
									<td>
										<div style="font-weight: 700; color: #0f172a;">7422NJY</div>
										<div style="font-size: 12px; color: #64748b; margin-top: 2px;">Ivan López</div>
									</td>
									<td style="color: #334155; font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Cambio de embrague y valvulina de caja</td>
									<td>
										<select class="tallerpro-status-select status-finalizada" data-id="mock-1" title="Cambiar estado de reparación">
											<option value="en_curso">En curso</option>
											<option value="finalizada" selected>Finalizada</option>
										</select>
									</td>
									<td style="font-weight: 600; color: #1e293b;">128.55 €</td>
									<td>
										<div class="tallerpro-actions">
											<button type="button" class="tallerpro-btn-facturar-action" title="Emitir Factura (sin funcionalidad)">
												<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
													<polyline points="14 2 14 8 20 8"></polyline>
													<line x1="16" y1="13" x2="8" y2="13"></line>
													<line x1="16" y1="17" x2="8" y2="17"></line>
													<polyline points="10 9 9 9 8 9"></polyline>
												</svg>
												<span>Facturar</span>
											</button>
											<button type="button" class="tallerpro-action-btn btn-edit-reparacion" title="Editar reparación"
												data-id="mock-1"
												data-vehiculo="1"
												data-fecha="2026-10-27"
												data-estado="finalizada"
												data-descripcion="Cambio de embrague y valvulina de caja"
												data-coste="128.55"
												data-ref="OT #0123">
												<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
												</svg>
											</button>
											<button type="button" class="tallerpro-action-btn btn-delete-reparacion" data-id="mock-1" title="Eliminar reparación">
												<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<polyline points="3 6 5 6 21 6"></polyline>
													<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
												</svg>
											</button>
										</div>
									</td>
								</tr>

								<!-- Row 2: En curso sin botón Facturar -->
								<tr class="rep-row" data-id="mock-2" data-estado="en_curso" data-search="7412njy john miller revision de caja de cambios que rasca al reducir">
									<td>
										<div style="font-weight: 600; color: #1e293b;">2026-10-28</div>
										<div style="font-size: 11.5px; color: #94a3b8; margin-top: 2px;">OT #0124</div>
									</td>
									<td>
										<div style="font-weight: 700; color: #0f172a;">7412NJY</div>
										<div style="font-size: 12px; color: #64748b; margin-top: 2px;">John Miller</div>
									</td>
									<td style="color: #334155; font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Revisión de caja de cambios que rasca al reducir</td>
									<td>
										<select class="tallerpro-status-select status-en_curso" data-id="mock-2" title="Cambiar estado de reparación">
											<option value="en_curso" selected>En curso</option>
											<option value="finalizada">Finalizada</option>
										</select>
									</td>
									<td style="font-weight: 600; color: #1e293b;">200.00 €</td>
									<td>
										<div class="tallerpro-actions">
											<button type="button" class="tallerpro-action-btn btn-edit-reparacion" title="Editar reparación"
												data-id="mock-2"
												data-vehiculo="2"
												data-fecha="2026-10-28"
												data-estado="en_curso"
												data-descripcion="Revisión de caja de cambios que rasca al reducir"
												data-coste="200.00"
												data-ref="OT #0124">
												<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
												</svg>
											</button>
											<button type="button" class="tallerpro-action-btn btn-delete-reparacion" data-id="mock-2" title="Eliminar reparación">
												<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<polyline points="3 6 5 6 21 6"></polyline>
													<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
												</svg>
											</button>
										</div>
									</td>
								</tr>

								<!-- Row 3: Finalizada con botón Facturar -->
								<tr class="rep-row" data-id="mock-3" data-estado="finalizada" data-search="3569jpb prealuma s.l. pulir carroceria lateral y sustitucion de moldura">
									<td>
										<div style="font-weight: 600; color: #1e293b;">2026-10-24</div>
										<div style="font-size: 11.5px; color: #94a3b8; margin-top: 2px;">Fact. 0177/25</div>
									</td>
									<td>
										<div style="font-weight: 700; color: #0f172a;">3569 JPB</div>
										<div style="font-size: 12px; color: #64748b; margin-top: 2px;">Prealuma S.L.</div>
									</td>
									<td style="color: #334155; font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Pulir carrocería lateral y sustitución de moldura</td>
									<td>
										<select class="tallerpro-status-select status-finalizada" data-id="mock-3" title="Cambiar estado de reparación">
											<option value="en_curso">En curso</option>
											<option value="finalizada" selected>Finalizada</option>
										</select>
									</td>
									<td style="font-weight: 600; color: #1e293b;">773.44 €</td>
									<td>
										<div class="tallerpro-actions">
											<button type="button" class="tallerpro-btn-facturar-action" title="Emitir Factura (sin funcionalidad)">
												<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
													<polyline points="14 2 14 8 20 8"></polyline>
													<line x1="16" y1="13" x2="8" y2="13"></line>
													<line x1="16" y1="17" x2="8" y2="17"></line>
													<polyline points="10 9 9 9 8 9"></polyline>
												</svg>
												<span>Facturar</span>
											</button>
											<button type="button" class="tallerpro-action-btn btn-edit-reparacion" title="Editar reparación"
												data-id="mock-3"
												data-vehiculo="3"
												data-fecha="2026-10-24"
												data-estado="finalizada"
												data-descripcion="Pulir carrocería lateral y sustitución de moldura"
												data-coste="773.44"
												data-ref="Fact. 0177/25">
												<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
												</svg>
											</button>
											<button type="button" class="tallerpro-action-btn btn-delete-reparacion" data-id="mock-3" title="Eliminar reparación">
												<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<polyline points="3 6 5 6 21 6"></polyline>
													<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
												</svg>
											</button>
										</div>
									</td>
								</tr>

								<!-- Row 4: En curso sin botón Facturar -->
								<tr class="rep-row" data-id="mock-4" data-estado="en_curso" data-search="6721hpb fernandez & outeiro diagnostico luz de averia motor en cuadro">
									<td>
										<div style="font-weight: 600; color: #1e293b;">2026-10-29</div>
										<div style="font-size: 11.5px; color: #94a3b8; margin-top: 2px;">OT #0125</div>
									</td>
									<td>
										<div style="font-weight: 700; color: #0f172a;">6721 HPB</div>
										<div style="font-size: 12px; color: #64748b; margin-top: 2px;">Fernández &amp; Outeiro</div>
									</td>
									<td style="color: #334155; font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Diagnóstico luz de avería motor en cuadro</td>
									<td>
										<select class="tallerpro-status-select status-en_curso" data-id="mock-4" title="Cambiar estado de reparación">
											<option value="en_curso" selected>En curso</option>
											<option value="finalizada">Finalizada</option>
										</select>
									</td>
									<td style="font-weight: 600; color: #1e293b;">45.00 €</td>
									<td>
										<div class="tallerpro-actions">
											<button type="button" class="tallerpro-action-btn btn-edit-reparacion" title="Editar reparación"
												data-id="mock-4"
												data-vehiculo="4"
												data-fecha="2026-10-29"
												data-estado="en_curso"
												data-descripcion="Diagnóstico luz de avería motor en cuadro"
												data-coste="45.00"
												data-ref="OT #0125">
												<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
												</svg>
											</button>
											<button type="button" class="tallerpro-action-btn btn-delete-reparacion" data-id="mock-4" title="Eliminar reparación">
												<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<polyline points="3 6 5 6 21 6"></polyline>
													<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
												</svg>
											</button>
										</div>
									</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</main>
		</div>
	</div>

	<!-- Modal Orden de Reparación (Diseño idéntico a Detalle de Factura) -->
	<div class="tallerpro-modal-backdrop" id="tallerReparacionModal">
		<form class="tallerpro-modal-card tallerpro-modal-card-lg" id="tallerReparacionForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
			<div class="tallerpro-modal-header">
				<h2 class="tallerpro-modal-title" id="orderModalTitle">Detalle de la reparación</h2>
				<button type="button" class="tallerpro-modal-close" id="tallerCloseModalBtn" title="Cerrar">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>

			<input type="hidden" name="action" id="reparacionFormAction" value="create_reparacion">
			<input type="hidden" name="rowid" id="reparacionFormRowid" value="0">
			<input type="hidden" name="token" id="reparacionFormToken" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : (function_exists('newToken') ? newToken() : ''))); ?>">
			<input type="hidden" name="coste_mo" id="orderCosteMoHidden" value="0.00">
			<input type="hidden" name="lineas_json" id="orderLineasJsonHidden" value="">

			<div class="tallerpro-modal-body">
					<!-- Sección 0: Datos del Vehículo y Recepción -->
					<div class="tallerpro-invoice-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px;">
						<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
							<h3 class="tallerpro-invoice-section-title" style="margin: 0;">
								<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
									<rect x="1" y="3" width="15" height="13"></rect>
									<polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
									<circle cx="5.5" cy="18.5" r="2.5"></circle>
									<circle cx="18.5" cy="18.5" r="2.5"></circle>
								</svg>
								<span>Datos del Vehículo y Recepción</span>
							</h3>
							<div id="repFacturaBadgeContainer" style="display: none;"></div>
						</div>

						<div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 14px;">
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Vehículo &amp; Cliente <span class="tallerpro-required" style="color: #ef4444;">*</span></label>
								<select class="tallerpro-form-select" id="orderVehiculoSelect" name="fk_vehiculo" required style="font-weight: 500;" <?php if ($preselected_vehiculo > 0) echo 'data-preselected="' . $preselected_vehiculo . '"'; ?>>
									<option value="">— Seleccionar vehículo —</option>
									<?php foreach ($vehicles_list as $veh): ?>
										<option value="<?php echo (int) $veh->rowid; ?>">
											<?php 
											$vlabel = dol_escape_htmltag($veh->matricula . ' — ' . trim($veh->marca . ' ' . $veh->modelo));
											if (!empty($veh->cliente_nombre)) {
												$vlabel .= ' (' . dol_escape_htmltag($veh->cliente_nombre) . ')';
											}
											echo $vlabel;
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Fecha de Entrada <span class="tallerpro-required" style="color: #ef4444;">*</span></label>
								<input type="date" class="tallerpro-form-input" id="orderFecha" name="fecha" value="<?php echo date('Y-m-d'); ?>" required />
							</div>

							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Estado</label>
								<select class="tallerpro-form-select" id="orderEstadoSelect" name="estado">
									<option value="en_curso" selected>En curso</option>
									<option value="finalizada">Finalizada</option>
								</select>
							</div>
						</div>

						<div class="tallerpro-form-group" style="margin-top: 10px;">
							<label class="tallerpro-form-label">Avería reportada / Trabajo solicitado <span class="tallerpro-required" style="color: #ef4444;">*</span></label>
							<input type="text" class="tallerpro-form-input" id="orderAveria" name="descripcion" placeholder="Ej: Revisión 60.000 km, ruido en frenos al frenar, cambio de aceite y filtros..." required />
						</div>

						<div class="tallerpro-form-group" style="margin-top: 10px;">
							<label class="tallerpro-form-label">Notas</label>
							<textarea class="tallerpro-form-textarea" id="orderNotas" name="notas_texto" rows="2" placeholder="Notas u observaciones..."></textarea>
						</div>
					</div>
					<!-- Sección 1: Materiales (Diseño Idéntico a Factura) -->
					<div class="tallerpro-invoice-section">
						<h3 class="tallerpro-invoice-section-title">
							<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
								<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
								<line x1="12" y1="22.08" x2="12" y2="12"></line>
							</svg>
							<span>Materiales</span>
						</h3>
						<div class="tallerpro-grid-mat-row1" style="margin-top: 12px;">
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Material del catálogo</label>
								<select id="rep_mat_catalogo" class="tallerpro-form-select">
									<option value="">— Nuevo / escribir manualmente —</option>
									<?php foreach ($materials_list as $mcat): 
										$stk = (float) $mcat->stock;
										$stk_label = $stk <= 0 ? 'Sin stock (0 uds)' : ('Stock: ' . (int)$stk . ' uds');
									?>
										<option value="<?php echo (int) $mcat->rowid; ?>"
											data-codigo="<?php echo dol_escape_htmltag($mcat->ref); ?>"
											data-descripcion="<?php echo dol_escape_htmltag($mcat->nombre); ?>"
											data-precio="<?php echo number_format((float) $mcat->precio, 2, '.', ''); ?>"
											data-stock="<?php echo $stk; ?>">
											<?php echo dol_escape_htmltag($mcat->nombre . ' (' . number_format((float) $mcat->precio, 2, '.', '') . ' €) — [' . $stk_label . ']'); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<div id="rep_mat_stock_pill" style="display: none; margin-top: 6px;"></div>
							</div>
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Código</label>
								<input type="text" id="rep_mat_codigo" class="tallerpro-form-input" />
							</div>
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Descripción</label>
								<input type="text" id="rep_mat_descripcion" class="tallerpro-form-input" />
							</div>
						</div>

						<div class="tallerpro-grid-mat-row2" style="margin-top: 10px;">
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Cantidad</label>
								<input type="number" step="1" min="1" id="rep_mat_cantidad" class="tallerpro-form-input" value="1" />
							</div>
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Precio unit. (€)</label>
								<input type="number" step="0.01" min="0" id="rep_mat_precio" class="tallerpro-form-input" value="0" />
							</div>
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Descuento (%)</label>
								<input type="number" step="1" min="0" max="100" id="rep_mat_descuento" class="tallerpro-form-input" value="0" />
							</div>
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Total neto</label>
								<input type="text" id="rep_mat_total_neto" class="tallerpro-form-input" value="0.00 €" readonly />
							</div>
							<div class="tallerpro-form-group" style="justify-content: flex-end;">
								<button type="button" class="tallerpro-btn-add-line" id="btnAddRepMaterialLine">
									<span>+ Añadir</span>
								</button>
							</div>
						</div>

						<!-- Listado de Materiales o estado vacío -->
						<div id="rep_mat_empty" class="tallerpro-invoice-empty-box" style="margin-top: 12px;">
							Sin materiales todavía.
						</div>
						<div id="rep_mat_list" class="tallerpro-invoice-lines-list" style="display: none; margin-top: 12px;"></div>
					</div>

					<!-- Sección 2: Reparaciones / Mano de obra (Diseño Idéntico a Factura) -->
					<div class="tallerpro-invoice-section">
						<h3 class="tallerpro-invoice-section-title">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
							</svg>
							<span>Reparaciones / Mano de obra</span>
						</h3>
						<div class="tallerpro-grid-rep-row1" style="margin-top: 12px;">
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Descripción de la reparación</label>
								<input type="text" id="rep_mo_descripcion" class="tallerpro-form-input" placeholder="Ej: Cambio de embrague" />
							</div>
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Precio (€)</label>
								<input type="number" step="0.01" min="0" id="rep_mo_precio" class="tallerpro-form-input" value="0" />
							</div>
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Descuento (%)</label>
								<input type="number" step="1" min="0" max="100" id="rep_mo_descuento" class="tallerpro-form-input" value="0" />
							</div>
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Total neto</label>
								<input type="text" id="rep_mo_total_neto" class="tallerpro-form-input" value="0.00 €" readonly />
							</div>
						</div>

						<div class="tallerpro-grid-rep-row2" style="margin-top: 10px;">
							<div class="tallerpro-form-group">
								<label class="tallerpro-form-label">Notas</label>
								<textarea id="rep_mo_notas" class="tallerpro-form-textarea" rows="2"></textarea>
							</div>
							<div class="tallerpro-form-group" style="justify-content: flex-end;">
								<button type="button" class="tallerpro-btn-add-line" id="btnAddRepMoLine">
									<span>+ Añadir</span>
								</button>
							</div>
						</div>

						<!-- Listado de Reparaciones o estado vacío -->
						<div id="rep_mo_empty" class="tallerpro-invoice-empty-box" style="margin-top: 12px;">
							Sin reparaciones todavía.
						</div>
						<div id="rep_mo_list" class="tallerpro-invoice-lines-list" style="display: none; margin-top: 12px;"></div>
					</div>

					<!-- Sección 3: Totales y Acciones (Diseño Idéntico a Factura) -->
					<div class="tallerpro-invoice-totals-card">
						<div class="tallerpro-invoice-totals-grid">
							<!-- Lado izquierdo: Descuento global e IVA -->
							<div class="tallerpro-totals-inputs">
								<div class="tallerpro-form-group">
									<label class="tallerpro-form-label">Descuento global (€)</label>
									<input type="number" step="0.01" min="0" id="rep_tot_descuento_global" class="tallerpro-form-input" value="0" />
								</div>
								<div class="tallerpro-form-group">
									<label class="tallerpro-form-label">IVA (%)</label>
									<input type="number" step="0.01" min="0" id="rep_tot_iva" class="tallerpro-form-input" value="21" />
								</div>
							</div>

							<!-- Lado derecho: Desglose dinámico -->
							<div class="tallerpro-totals-summary">
								<div class="tallerpro-totals-row">
									<span>Materiales</span>
									<span id="rep_sum_materiales">0.00 €</span>
								</div>
								<div class="tallerpro-totals-row">
									<span>Reparaciones</span>
									<span id="rep_sum_reparaciones">0.00 €</span>
								</div>
								<div class="tallerpro-totals-divider"></div>
								<div class="tallerpro-totals-row">
									<span>Subtotal</span>
									<span id="rep_sum_subtotal">0.00 €</span>
								</div>
								<div class="tallerpro-totals-row">
									<span>Descuento</span>
									<span id="rep_sum_descuento">- 0.00 €</span>
								</div>
								<div class="tallerpro-totals-divider"></div>
								<div class="tallerpro-totals-row">
									<span>Base imponible</span>
									<span id="rep_sum_base_imponible">0.00 €</span>
								</div>
								<div class="tallerpro-totals-row">
									<span id="rep_lbl_iva">IVA (21%)</span>
									<span id="rep_sum_iva">0.00 €</span>
								</div>
								<div class="tallerpro-totals-divider"></div>
								<div class="tallerpro-totals-row tallerpro-totals-final">
									<span>TOTAL</span>
									<span id="rep_sum_total">0.00 €</span>
								</div>

								<button type="submit" class="tallerpro-btn-save-totals" id="tallerSaveOrderBtn" title="Guardar orden de reparación">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
										<polyline points="17 21 17 13 7 13 7 21"></polyline>
										<polyline points="7 3 7 8 15 8"></polyline>
									</svg>
									<span>Guardar reparación</span>
								</button>

								<button type="button" class="tallerpro-btn-emitir-factura" id="btnModalEmitirFactura" style="display: none;" title="Guardar y emitir factura oficial de Dolibarr">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
										<polyline points="14 2 14 8 20 8"></polyline>
										<line x1="16" y1="13" x2="8" y2="13"></line>
										<line x1="16" y1="17" x2="8" y2="17"></line>
										<polyline points="10 9 9 9 8 9"></polyline>
									</svg>
									<span>Guardar y Emitir Factura</span>
								</button>
							</div>
						</div>
					</div>
				</div>
		</form>
	</div>

	<!-- modal eliminacion confirmacion -->

	<!-- Modal Confirmación de Eliminación -->
	<div class="tallerpro-modal-backdrop" id="deleteConfirmModal" style="z-index: 1000005;">
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
			<p style="font-size: 13.5px; color: #64748b; margin: 0 0 24px 0; line-height: 1.5;">Esta reparación dejará de mostrarse en la lista activa, pero sus datos se conservarán en el sistema.</p>

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

	<!-- Modal Confirmación de Facturación -->
	<div class="tallerpro-modal-backdrop" id="facturarConfirmModal" style="z-index: 1000005;">
		<div class="tallerpro-modal-card" style="max-width: 440px; padding: 28px 24px; text-align: center;">
			<div style="width: 52px; height: 52px; border-radius: 50%; background-color: #ecfdf5; color: #059669; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
				<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
					<polyline points="14 2 14 8 20 8"></polyline>
					<line x1="16" y1="13" x2="8" y2="13"></line>
					<line x1="16" y1="17" x2="8" y2="17"></line>
					<polyline points="10 9 9 9 8 9"></polyline>
				</svg>
			</div>
			<h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">¿Deseas facturar esta reparación?</h3>
			<p style="font-size: 13.5px; color: #64748b; margin: 0 0 24px 0; line-height: 1.5;">Se generará la factura en estado borrador en Dolibarr con los materiales y la mano de obra registrados.</p>

			<div style="display: flex; gap: 12px; justify-content: center;">
				<button type="button" id="btnCancelFacturar" style="flex: 1; padding: 10px 16px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px;">
					Rechazar
				</button>
				<button type="button" id="btnConfirmFacturar" style="flex: 1; padding: 10px 16px; border: none; background: #059669; color: #ffffff; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.25);">
					Confirmar
				</button>
			</div>
		</div>
	</div>

	<!-- Formulario oculto para borrado seguro POST con CSRF Token -->
	<form id="tallerGlobalDeleteForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" style="display: none;">
		<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : (function_exists('newToken') ? newToken() : ''))); ?>">
		<input type="hidden" name="action" id="globalDeleteAction" value="delete_reparacion">
		<input type="hidden" name="rowid" id="globalDeleteRowid" value="">
		<input type="hidden" name="socid" id="globalDeleteSocid" value="">
	</form>


</div>

<?php
// End of page
llxFooter();
$db->close();
