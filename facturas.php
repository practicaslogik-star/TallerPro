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
require_once __DIR__ . '/lib/taller_stock.lib.php';
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

if (!function_exists('tallerpro_clean_notas')) {
	function tallerpro_clean_notas($notas_str) {
		$notas_raw = trim((string) $notas_str);
		if (empty($notas_raw)) return '';
		if (strpos($notas_raw, '{') !== false && strpos($notas_raw, '}') !== false) {
			if ($notas_raw[0] === '{') {
				$dec = json_decode($notas_raw, true);
				return !empty($dec['notas_usuario']) ? trim($dec['notas_usuario']) : (!empty($dec['notas']) && is_string($dec['notas']) ? trim($dec['notas']) : '');
			} else {
				$clean = preg_replace('/\{.*?\}$/s', '', $notas_raw);
				if (preg_match('/^Factura de reparaci[oó]n\s+OT\s*#\d+/i', trim($clean))) {
					$clean = preg_replace('/^Factura de reparaci[oó]n\s+OT\s*#\d+[^\n]*\n?/i', '', trim($clean));
				}
				return trim($clean);
			}
		} elseif (preg_match('/^Factura de reparaci[oó]n\s+OT\s*#\d+/i', $notas_raw)) {
			$clean = preg_replace('/^Factura de reparaci[oó]n\s+OT\s*#\d+[^\n]*\n?/i', '', $notas_raw);
			return trim($clean);
		}
		return $notas_raw;
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
	if (empty($estado) || ($estado != 'Pagada' && $estado != 'Pendiente' && $estado != 'Borrador')) {
		$estado = 'Borrador';
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
			if ($estado == 'Borrador') {
				// Permanece en borrador (PROV...), permitiendo edición y borrado
			} else {
				$fac->validate($user);
				if ($estado == 'Pagada') {
					$fac->setPaid($user);
				}
			}
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $fac_id);
			exit;
		} else {
			dol_syslog("TallerPro - Error al crear factura: " . $fac->error . " " . implode(' ', $fac->errors), LOG_ERR);
			setEventMessages("Error al crear la factura", null, 'errors');
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
			if ($fac->statut != Facture::STATUS_DRAFT) {
				setEventMessages("Solo se pueden editar facturas en estado Borrador", null, 'errors');
				header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $rowid);
				exit;
			}

			$old_ref = $fac->ref;

			// Regla: mientras la factura esté en estado borrador solo se le puede editar el estado
			if ($estado == 'Pendiente' || $estado == 'Pagada') {
				$res_val = $fac->validate($user);
				if ($res_val >= 0) {
					if ($estado == 'Pagada') {
						$fac->setPaid($user);
					}
					// Sincronizar referencia definitiva en reparaciones si se validó la factura
					if (!empty($old_ref) && $old_ref !== $fac->ref) {
						$db->query("UPDATE " . MAIN_DB_PREFIX . "taller_reparacion SET ref_factura = '" . $db->escape($fac->ref) . "' WHERE ref_factura = '" . $db->escape($old_ref) . "'");
					}
					setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
					header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $rowid);
					exit;
				} else {
					dol_syslog("TallerPro - Error al validar factura borrador ID " . $rowid . ": " . $fac->error . " " . implode(' ', $fac->errors), LOG_ERR);
					setEventMessages("Error al validar la factura borrador", null, 'errors');
				}
			} else {
				// Permanece en borrador, no se alteran otros campos
				setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
				header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $rowid);
				exit;
			}
		} else {
			setEventMessages("No se encontró la factura a modificar", null, 'errors');
		}
	}
}

// Acción: Cambiar Estado de Factura directamente desde la tabla (Borrador -> Pendiente / Pagada, Pendiente <-> Pagada)
if ($action == 'update_factura_estado' || $action == 'update_estado') {
	$rowid = GETPOSTINT('rowid');
	if (empty($rowid) && !empty($_POST['rowid'])) {
		$rowid = (int) $_POST['rowid'];
	}
	$new_estado = trim(GETPOST('new_estado', 'alphanohtml'));
	if (empty($new_estado) && !empty($_POST['new_estado'])) {
		$new_estado = trim($_POST['new_estado']);
	}

	$res_success = false;
	$error_msg = '';
	$updated_ref = '';
	$is_borrador_now = false;
	$final_estado = '';

	if ($rowid > 0 && in_array($new_estado, array('Pendiente', 'Pagada'))) {
		$fac = new Facture($db);
		if ($fac->fetch($rowid) > 0) {
			$old_ref = $fac->ref;
			$is_draft = ($fac->statut == Facture::STATUS_DRAFT);
			$was_paid = ($fac->statut == Facture::STATUS_CLOSED || $fac->paye == 1);

			if ($is_draft) {
				// Validar borrador para asignar número oficial definitivo
				$res_val = $fac->validate($user);
				if ($res_val >= 0) {
					if ($new_estado === 'Pagada') {
						$fac->setPaid($user);
					}
					// Sincronizar referencia si cambió en órdenes de reparación
					if (!empty($old_ref) && $old_ref !== $fac->ref) {
						$db->query("UPDATE " . MAIN_DB_PREFIX . "taller_reparacion SET ref_factura = '" . $db->escape($fac->ref) . "' WHERE ref_factura = '" . $db->escape($old_ref) . "'");
					}
					$res_success = true;
				} else {
					dol_syslog("TallerPro - Error al validar factura borrador ID " . $rowid . ": " . $fac->error . " " . implode(' ', $fac->errors), LOG_ERR);
					$error_msg = "Error al validar la factura borrador";
				}
			} else {
				// Factura ya validada: cambiar entre Pendiente y Pagada
				if ($new_estado === 'Pagada' && !$was_paid) {
					$fac->setPaid($user);
					$res_success = true;
				} elseif ($new_estado === 'Pendiente' && $was_paid) {
					$fac->setUnpaid($user);
					$res_success = true;
				} else {
					$res_success = true;
				}
			}

			if ($res_success) {
				$fac->fetch($rowid);
				$final_estado = ($fac->statut == Facture::STATUS_DRAFT) ? 'Borrador' : (($fac->paye == 1 || $fac->statut == Facture::STATUS_CLOSED) ? 'Pagada' : 'Pendiente');
				$is_borrador_now = ($final_estado === 'Borrador');
				$updated_ref = $fac->ref;
			}
		} else {
			$error_msg = "No se encontró la factura indicada";
		}
	} else {
		$error_msg = "Datos de estado o factura no válidos";
	}

	if (GETPOST('ajax', 'int') || !empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
		header('Content-Type: application/json');
		if ($res_success) {
			echo json_encode(array(
				'success' => true,
				'new_estado' => $final_estado,
				'ref' => $updated_ref,
				'is_borrador' => $is_borrador_now
			));
		} else {
			echo json_encode(array(
				'success' => false,
				'error' => $error_msg
			));
		}
		exit;
	}

	if ($res_success) {
		setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
	} else {
		setEventMessages($error_msg ?: "Error al actualizar estado", null, 'errors');
	}
	header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $rowid);
	exit;
}

// Acción: Eliminar Factura (Seguro vía POST con token)
if ($action == 'delete_factura' || $action == 'delete') {
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
		$fac = new Facture($db);
		if ($fac->fetch($rowid) > 0) {
			if ($fac->statut != Facture::STATUS_DRAFT) {
				setEventMessages("Solo se pueden eliminar facturas en estado Borrador", null, 'errors');
				header("Location: " . $_SERVER['PHP_SELF']);
				exit;
			}
			$del_ref = $fac->ref;
			$res = $fac->delete($user);
			if ($res > 0) {
				if (!empty($del_ref)) {
					$db->query("UPDATE " . MAIN_DB_PREFIX . "taller_reparacion SET ref_factura = NULL WHERE ref_factura = '" . $db->escape($del_ref) . "'");
				}
				setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
				header("Location: " . $_SERVER['PHP_SELF']);
				exit;
			} else {
				dol_syslog("TallerPro - Error al eliminar factura ID " . $rowid . ": " . $fac->error, LOG_ERR);
				setEventMessages("No se pudo eliminar la factura solicitada", null, 'errors');
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
			$estado_label = ($fac->statut == Facture::STATUS_DRAFT) ? 'Borrador' : (($fac->paye == 1 || $fac->statut == Facture::STATUS_CLOSED) ? 'Pagada' : 'Pendiente');
			$out['factura'] = (object) array(
				'rowid' => (int) $fac->id,
				'numero_factura' => $fac->ref,
				'total' => (float) $fac->total_ttc,
				'total_ht' => (float) $fac->total_ht,
				'total_tva' => (float) $fac->total_tva,
				'descuento_global' => (float) $fac->remise_percent,
				'iva_porcentaje' => 21.0,
				'estado' => $estado_label,
				'notas' => tallerpro_clean_notas($fac->note_public)
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

	$fk_product_line = 0;
	if ($tipo == 'material') {
		if ($fk_material > 0) {
			$res_m = $db->query("SELECT fk_product FROM " . MAIN_DB_PREFIX . "taller_material WHERE rowid = " . ((int) $fk_material));
			if ($res_m && ($m_obj = $db->fetch_object($res_m))) {
				$fk_product_line = (int) $m_obj->fk_product;
			}
		}
		if ($fk_product_line <= 0) {
			$fk_product_line = tallerpro_get_or_create_product($codigo, $descripcion, $precio, 0, $user);
		}
	}

	if ($guardar_catalogo && $tipo == 'material') {
		$sql_ins_mat = "INSERT INTO " . MAIN_DB_PREFIX . "taller_material (nombre, ref, fk_product, precio, date_creation, fk_user_creat, entity, status) VALUES (";
		$sql_ins_mat .= "'" . $db->escape($descripcion) . "', ";
		$sql_ins_mat .= "'" . $db->escape($codigo) . "', ";
		$sql_ins_mat .= ($fk_product_line > 0 ? (int) $fk_product_line : "NULL") . ", ";
		$sql_ins_mat .= $precio . ", ";
		$sql_ins_mat .= "'" . $db->idate(dol_now()) . "', ";
		$sql_ins_mat .= ((int) $user->id) . ", ";
		$sql_ins_mat .= ((int) $conf->entity) . ", 1)";
		$db->query($sql_ins_mat);
	}

	$product_type = ($tipo == 'reparacion') ? 1 : 0;
	$tva_tx = 21.0;
	$line_id = $fac->addline($descripcion, $precio, $cantidad, $tva_tx, 0, 0, $fk_product_line, $descuento, '', '', 0, 0, 0, 'HT', 0, $product_type, -1, 0, '', 0, 0, null, 0, '', array(), 100, 0, null, 0, $codigo, 0);

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
$sql_v = "SELECT rowid, fk_soc, matricula, marca, modelo FROM " . $table_vehiculo . " WHERE status = 1 AND entity IN (" . getEntity('taller_vehiculo') . ") ORDER BY matricula ASC";
$res_v = $db->query($sql_v);
if ($res_v) {
	while ($v = $db->fetch_object($res_v)) {
		$vehicles_list[] = $v;
	}
}

// Consultar catálogo de materiales con stock nativo de Dolibarr
$wh_id_fac = tallerpro_get_default_warehouse($user);
$materials_catalog = array();
$sql_mc = "SELECT tm.rowid, tm.ref, tm.nombre, tm.precio, tm.stock, tm.fk_product, ps.reel as wh_stock ";
$sql_mc .= "FROM " . MAIN_DB_PREFIX . "taller_material tm ";
$sql_mc .= "LEFT JOIN " . MAIN_DB_PREFIX . "product_stock ps ON ps.fk_product = tm.fk_product AND ps.fk_entrepot = " . ((int) $wh_id_fac) . " ";
$sql_mc .= "WHERE tm.status = 1 ORDER BY tm.nombre ASC";
$res_mc = $db->query($sql_mc);
if ($res_mc) {
	while ($mc = $db->fetch_object($res_mc)) {
		$mc->stock = ($mc->wh_stock !== null) ? (float) $mc->wh_stock : (float) $mc->stock;
		$materials_catalog[] = $mc;
	}
}

// Consultar facturas nativas de Dolibarr con JOIN a cliente y vehículo
$facturas_list = array();
$sql_f = "SELECT f.rowid, f.ref as numero_factura, f.datef as fecha, f.fk_soc, f.total_ttc as total, f.total_ht, f.total_tva, ";
$sql_f .= "f.fk_statut, f.paye, COALESCE(f.remise_percent, 0) as descuento_global, 21.0 as iva_porcentaje, f.note_public as notas, ";
$sql_f .= "(CASE WHEN f.fk_statut = 0 THEN 'Borrador' WHEN (f.paye = 1 OR f.fk_statut = 2) THEN 'Pagada' ELSE 'Pendiente' END) as estado, ";
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
	$sql_single .= "(CASE WHEN f.fk_statut = 0 THEN 'Borrador' WHEN (f.paye = 1 OR f.fk_statut = 2) THEN 'Pagada' ELSE 'Pendiente' END) as estado, ";
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
llxHeader($morehead, 'Facturas - TallerPro', '', '', 0, 0, array(), array(), '', 'mod-taller page-tallerpro');

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
								<?php
								$f_estado_lower = strtolower(trim($factura_single->estado));
								if ($f_estado_lower == 'pagada') {
									$badge_style = 'background-color: #ecfdf5 !important; color: #047857 !important; border: 1px solid #a7f3d0 !important;';
								} elseif ($f_estado_lower == 'borrador') {
									$badge_style = 'background-color: #f1f5f9 !important; color: #475569 !important; border: 1px solid #cbd5e1 !important;';
								} else {
									$badge_style = 'background-color: #fffbeb !important; color: #b45309 !important; border: 1px solid #fde68a !important;';
								}
								?>
								<span class="tallerpro-invoice-badge-status" style="<?php echo $badge_style; ?>">
									<?php echo dol_escape_htmltag($factura_single->estado ?: 'Borrador'); ?>
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
								<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
								<?php
								$clean_single_notas = tallerpro_clean_notas($factura_single->notas);
								echo !empty($clean_single_notas) ? nl2br(dol_escape_htmltag($clean_single_notas)) : '<span style="color: #94a3b8;">Sin notas</span>';
								?>
							</div>
						</div>

						<?php
						$mostrar_iban_view = isset($conf->global->TALLER_MOSTRAR_IBAN) ? ((int) $conf->global->TALLER_MOSTRAR_IBAN === 1) : true;
						if ($mostrar_iban_view && !empty($conf->global->TALLER_IBAN)):
							require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
							$raw_iban_view = dolDecrypt($conf->global->TALLER_IBAN);
							if (!empty($raw_iban_view) && strtoupper(trim($raw_iban_view)) !== 'NONE'):
						?>
						<!-- Sección 5: Datos de Pago / Transferencia (controlado por configuración) -->
						<div class="tallerpro-invoice-info-card" style="background: #ffffff !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; padding: 20px 24px !important; margin-top: 16px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;">
							<div class="tallerpro-info-card-tag" style="font-size: 11px !important; font-weight: 700 !important; color: #64748b !important; letter-spacing: 0.05em !important; text-transform: uppercase !important;">DATOS DE PAGO / TRANSFERENCIA</div>
							<div class="tallerpro-info-card-line" style="margin-top: 6px !important; font-size: 13.5px !important; color: #0f172a !important; font-family: monospace; font-weight: 600;">
								IBAN: <?php echo dol_escape_htmltag(trim(chunk_split($raw_iban_view, 4, ' '))); ?>
							</div>
						</div>
						<?php endif; endif; ?>
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
										<?php
										$f_estado_lower = strtolower(trim($f->estado));
										if ($f_estado_lower == 'pagada') {
											$status_class = 'status-pagada';
											$cur_estado = 'Pagada';
										} elseif ($f_estado_lower == 'borrador' || (isset($f->fk_statut) && (int)$f->fk_statut == 0)) {
											$status_class = 'status-borrador';
											$cur_estado = 'Borrador';
										} else {
											$status_class = 'status-pendiente';
											$cur_estado = 'Pendiente';
										}
										$is_borrador = ($cur_estado === 'Borrador');
										?>
										<tr data-invoice-id="<?php echo (int) $f->rowid; ?>" data-estado="<?php echo $cur_estado; ?>">
											<td style="color: #1e293b; font-weight: 700;">
												<a href="<?php echo $facturas_url . '?id=' . ((int) $f->rowid); ?>" class="factura-ref-link" style="color: #0f172a; text-decoration: none;" title="Ver factura">
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
												<select class="tallerpro-status-select <?php echo $status_class; ?> tallerpro-invoice-status-select"
													data-id="<?php echo (int) $f->rowid; ?>"
													data-current-estado="<?php echo $cur_estado; ?>"
													data-ref="<?php echo dol_escape_htmltag($f->numero_factura); ?>"
													title="Cambiar estado de factura">
													<?php if ($is_borrador): ?>
														<option value="Borrador" selected>Borrador</option>
														<option value="Pendiente">Pendiente</option>
														<option value="Pagada">Pagada</option>
													<?php elseif ($cur_estado === 'Pendiente'): ?>
														<option value="Pendiente" selected>Pendiente</option>
														<option value="Pagada">Pagada</option>
													<?php else: ?>
														<option value="Pagada" selected>Pagada</option>
														<option value="Pendiente">Pendiente</option>
													<?php endif; ?>
												</select>
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
													<button type="button" class="tallerpro-action-btn btn-edit-factura <?php echo (!$is_borrador ? 'is-disabled' : ''); ?>"
														title="<?php echo ($is_borrador ? 'Editar' : 'Solo se pueden editar facturas en estado Borrador'); ?>"
														<?php if (!$is_borrador) echo 'disabled'; ?>
														data-id="<?php echo (int) $f->rowid; ?>"
														data-numero="<?php echo dol_escape_htmltag($f->numero_factura); ?>"
														data-fecha="<?php echo dol_escape_htmltag($f->fecha); ?>"
														data-soc="<?php echo (int) $f->fk_soc; ?>"
														data-vehiculo="<?php echo (int) $f->fk_vehiculo; ?>"
														data-total="<?php echo number_format((float) $f->total, 2, '.', ''); ?>"
														data-estado="<?php echo dol_escape_htmltag($f->estado); ?>"
														data-notas="<?php echo dol_escape_htmltag(tallerpro_clean_notas($f->notas)); ?>">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="<?php echo ($is_borrador ? '#64748b' : '#cbd5e1'); ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
														</svg>
													</button>
													<button type="button"
														class="tallerpro-action-btn btn-delete-action <?php echo (!$is_borrador ? 'is-disabled' : ''); ?>"
														title="<?php echo ($is_borrador ? 'Eliminar' : 'Solo se pueden eliminar facturas en estado Borrador'); ?>"
														<?php if (!$is_borrador) echo 'disabled'; ?>
														data-id="<?php echo (int) $f->rowid; ?>"
														data-action="delete_factura">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="<?php echo ($is_borrador ? '#ef4444' : '#cbd5e1'); ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<polyline points="3 6 5 6 21 6"></polyline>
															<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
														</svg>
													</button>
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

				<!-- Aviso para facturas en estado borrador -->
				<div id="facEditNotice" style="display: none; padding: 10px 14px; margin-bottom: 16px; background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #dc2626; border-radius: 8px; font-size: 13px; color: #991b1b; line-height: 1.45;">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;">
						<circle cx="12" cy="12" r="10"></circle>
						<line x1="12" y1="16" x2="12" y2="12"></line>
						<line x1="12" y1="8" x2="12.01" y2="8"></line>
					</svg>
					<strong>Factura en borrador:</strong> Solo se permite modificar el estado para validarla (Pendiente o Pagada).
				</div>

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
							<option value="<?php echo (int) $vh->rowid; ?>" data-soc="<?php echo (int) $vh->fk_soc; ?>">
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
						<option value="Borrador" selected>Borrador</option>
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

	<!-- Modal Confirmación para poner Factura en Pendiente -->
	<div class="tallerpro-modal-backdrop" id="pendienteConfirmModal" style="z-index: 1000006;">
		<div class="tallerpro-modal-card" style="max-width: 440px; padding: 28px 24px; text-align: center;">
			<div style="width: 52px; height: 52px; border-radius: 50%; background-color: #fef3c7; color: #d97706; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
				<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="12" cy="12" r="10"></circle>
					<polyline points="12 6 12 12 16 14"></polyline>
				</svg>
			</div>
			<h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">¿Deseas poner la factura en Pendiente?</h3>
			<p style="font-size: 13.5px; color: #64748b; margin: 0 0 24px 0; line-height: 1.5;" id="pendienteModalDesc">
				La factura se registrará como emitida y quedará en estado pendiente de cobro.
			</p>

			<div style="display: flex; gap: 12px; justify-content: center;">
				<button type="button" id="btnCancelPendiente" style="flex: 1; padding: 10px 16px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px;">
					Rechazar
				</button>
				<button type="button" id="btnConfirmPendiente" style="flex: 1; padding: 10px 16px; border: none; background: #d97706; color: #ffffff; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px; box-shadow: 0 2px 4px rgba(217, 119, 6, 0.25);">
					Confirmar
				</button>
			</div>
		</div>
	</div>

	<!-- Modal Confirmación para poner Factura en Pagada -->
	<div class="tallerpro-modal-backdrop" id="pagadaConfirmModal" style="z-index: 1000006;">
		<div class="tallerpro-modal-card" style="max-width: 440px; padding: 28px 24px; text-align: center;">
			<div style="width: 52px; height: 52px; border-radius: 50%; background-color: #ecfdf5; color: #059669; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
				<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
					<polyline points="22 4 12 14.01 9 11.01"></polyline>
				</svg>
			</div>
			<h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">¿Deseas marcar la factura como Pagada?</h3>
			<p style="font-size: 13.5px; color: #64748b; margin: 0 0 24px 0; line-height: 1.5;" id="pagadaModalDesc">
				Se registrará el cobro completo de la factura en el sistema.
			</p>

			<div style="display: flex; gap: 12px; justify-content: center;">
				<button type="button" id="btnCancelPagada" style="flex: 1; padding: 10px 16px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px;">
					Rechazar
				</button>
				<button type="button" id="btnConfirmPagada" style="flex: 1; padding: 10px 16px; border: none; background: #059669; color: #ffffff; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.25);">
					Confirmar
				</button>
			</div>
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
			<h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">¿Estás seguro de que deseas borrar?</h3>
			<p style="font-size: 13.5px; color: #64748b; margin: 0 0 24px 0; line-height: 1.5;">Esta acción eliminará el registro de forma definitiva en el sistema.</p>

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
