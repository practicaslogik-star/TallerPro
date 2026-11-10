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
$langs->loadLangs(array("taller@taller", "bills", "banks", "errors"));

require_once __DIR__ . '/lib/taller.lib.php';

$action = GETPOST('action', 'aZ09');

// Security check
$is_ajax_action = in_array($action, array('get_next_factura_numero', 'get_factura_lineas', 'add_factura_linea', 'delete_factura_linea', 'save_factura_totales'))
	|| (GETPOSTINT('ajax') || !empty($_SERVER['HTTP_X_REQUESTED_WITH']));
tallerpro_require_permission('facturar_or_read', $is_ajax_action);

$user_can_facturar = $user->hasRight('taller', 'facturar');
$user_can_delete_fac = $user->hasRight('taller', 'delete') && $user_can_facturar;

$facture_enabled = isModEnabled('facture');
$propal_enabled = isModEnabled('propal');
$bank_enabled = isModEnabled('bank');

$req_doc_type = trim(GETPOST('doc_type', 'aZ09'));
if (empty($req_doc_type)) {
	$req_doc_type = trim(GETPOST('doc', 'aZ09'));
}
if (empty($req_doc_type) && GETPOSTINT('propal_id') > 0) {
	$req_doc_type = 'propal';
}
$is_propal_request = ($req_doc_type === 'propal' || $req_doc_type === 'presupuesto');

if (!$is_propal_request && !$facture_enabled && !empty($action) && in_array($action, array('create_factura', 'add_factura', 'update_factura', 'update_factura_estado', 'update_estado', 'delete_factura', 'add_factura_linea', 'delete_factura_linea', 'save_factura_totales'))) {
	if ($is_ajax_action) {
		header('Content-Type: application/json');
		echo json_encode(array('success' => false, 'error' => $langs->trans("ErrModuleFactureDisabledAjax")));
		exit;
	}
	setEventMessages($langs->trans("ErrModuleFactureDisabledMsg"), null, 'errors');
	header("Location: " . $_SERVER['PHP_SELF']);
	exit;
}
if ($is_propal_request && !$propal_enabled && !empty($action) && in_array($action, array('update_factura', 'delete_factura'))) {
	if ($is_ajax_action) {
		header('Content-Type: application/json');
		echo json_encode(array('success' => false, 'error' => $langs->trans("ErrModulePropalDisabled")));
		exit;
	}
	setEventMessages($langs->trans("ErrModulePropalDisabled"), null, 'errors');
	header("Location: " . $_SERVER['PHP_SELF']);
	exit;
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
if ($propal_enabled) {
	require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
}
require_once DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once __DIR__ . '/lib/taller_stock.lib.php';
require_once __DIR__ . '/class/taller_material.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

$table_vehiculo = MAIN_DB_PREFIX . "taller_vehiculo";
$table_soc = MAIN_DB_PREFIX . "societe";

// Función para obtener la siguiente referencia prevista de factura en Dolibarr
if (!function_exists('getNextFacturaNumero')) {
	function getNextFacturaNumero($db, $year_or_date = '', $entity = 1) {
		global $mysoc;
		$fac_static = new Facture($db);
		$next = $fac_static->getNextNumRef($mysoc);
		return !empty($next) ? $next : '(PROV)';
	}
}

/**
 * Obtiene los modos de pago activos de Dolibarr en la entidad actual.
 */
if (!function_exists('tallerpro_get_payment_methods')) {
	function tallerpro_get_payment_methods($db, $langs) {
		$list = array();
		$sql = "SELECT id, code, libelle FROM " . MAIN_DB_PREFIX . "c_paiement ";
		$sql .= "WHERE active = 1 AND code != '' AND entity IN (" . getEntity('c_paiement') . ") ";
		$sql .= "ORDER BY position ASC, libelle ASC";
		$res = $db->query($sql);
		if ($res) {
			while ($obj = $db->fetch_object($res)) {
				$lbl = $obj->libelle;
				$tr_key = 'PaymentType' . $obj->code;
				$tr_val = $langs->transnoentitiesnoconv($tr_key);
				if (!empty($tr_val) && $tr_val !== $tr_key) {
					$lbl = $tr_val;
				}
				$obj->label_display = $lbl;
				$list[] = $obj;
			}
		}
		return $list;
	}
}

/**
 * Obtiene las cuentas bancarias/cajas abiertas de Dolibarr en la entidad actual.
 */
if (!function_exists('tallerpro_get_bank_accounts')) {
	function tallerpro_get_bank_accounts($db) {
		$list = array();
		if (!isModEnabled('bank')) {
			return $list;
		}
		$sql = "SELECT rowid, ref, label, courant FROM " . MAIN_DB_PREFIX . "bank_account ";
		$sql .= "WHERE clos = 0 AND entity IN (" . getEntity('bank_account') . ") ";
		$sql .= "ORDER BY label ASC, ref ASC";
		$res = $db->query($sql);
		if ($res) {
			while ($obj = $db->fetch_object($res)) {
				$list[] = $obj;
			}
		}
		return $list;
	}
}

/**
 * Convierte una fecha YYYY-MM-DD a timestamp seguro al mediodía.
 */
if (!function_exists('tallerpro_parse_payment_date')) {
	function tallerpro_parse_payment_date($date_str) {
		$date_str = trim((string) $date_str);
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date_str, $m)) {
			return dol_mktime(12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
		}
		if (!empty($date_str)) {
			$ts = dol_stringtotime($date_str);
			if ($ts > 0) {
				return $ts;
			}
		}
		return dol_now();
	}
}

/**
 * Valida modo de pago y cuenta bancaria (si el módulo Bank está activo) para la entidad actual.
 *
 * @return array{valid:bool,paiementcode:string,error:string}
 */
if (!function_exists('tallerpro_validate_payment_inputs')) {
	function tallerpro_validate_payment_inputs($db, $paiementid, $accountid) {
		global $langs;
		$paiementid = (int) $paiementid;
		$accountid = (int) $accountid;

		if ($paiementid <= 0) {
			return array('valid' => false, 'paiementcode' => '', 'error' => $langs->trans("ErrSelectValidPaymentMethod"));
		}
		$sql_pm = "SELECT id, code FROM " . MAIN_DB_PREFIX . "c_paiement WHERE id = " . $paiementid . " AND active = 1 AND entity IN (" . getEntity('c_paiement') . ") LIMIT 1";
		$res_pm = $db->query($sql_pm);
		if (!$res_pm || $db->num_rows($res_pm) <= 0) {
			return array('valid' => false, 'paiementcode' => '', 'error' => $langs->trans("ErrInvalidPaymentMethodEntity"));
		}
		$pm_obj = $db->fetch_object($res_pm);
		$paiementcode = (string) $pm_obj->code;

		if (isModEnabled('bank')) {
			if ($accountid <= 0) {
				return array('valid' => false, 'paiementcode' => '', 'error' => $langs->trans("ErrSelectOpenBankAccount"));
			}
			$sql_ba = "SELECT rowid FROM " . MAIN_DB_PREFIX . "bank_account WHERE rowid = " . $accountid . " AND clos = 0 AND entity IN (" . getEntity('bank_account') . ") LIMIT 1";
			$res_ba = $db->query($sql_ba);
			if (!$res_ba || $db->num_rows($res_ba) <= 0) {
				return array('valid' => false, 'paiementcode' => '', 'error' => $langs->trans("ErrInvalidOrClosedBankAccount"));
			}
		}

		return array('valid' => true, 'paiementcode' => $paiementcode, 'error' => '');
	}
}

/**
 * Registra un cobro contable real en Dolibarr usando Paiement y lo enlaza al banco/caja si está activo.
 * Debe ejecutarse dentro de una transacción activa en $db.
 *
 * @return array{success:bool,error:string}
 */
if (!function_exists('tallerpro_register_invoice_payment')) {
	function tallerpro_register_invoice_payment($db, Facture $fac, User $user, $paiementid, $accountid, $datepaye_str = '', $num_payment = '') {
		global $langs;
		if ($fac->id <= 0 || $fac->fetch($fac->id) <= 0 || !tallerpro_is_entity_allowed($fac->entity, 'facture')) {
			return array('success' => false, 'error' => $langs->trans("ErrLoadInvoiceInEntity"));
		}
		if ($fac->statut == Facture::STATUS_DRAFT) {
			return array('success' => false, 'error' => $langs->trans("ErrInvoiceMustBeValidated"));
		}
		if ($fac->paye == 1 || $fac->statut == Facture::STATUS_CLOSED) {
			return array('success' => true, 'error' => '');
		}

		$paid_sum = (float) $fac->getSommePaiement();
		$credit_sum = (float) $fac->getSumCreditNotesUsed();
		$deposit_sum = (float) $fac->getSumDepositsUsed();
		$resteapayer = (float) price2num($fac->total_ttc - $paid_sum - $credit_sum - $deposit_sum, 'MT');

		if ($resteapayer <= 0) {
			return array('success' => false, 'error' => $langs->trans("ErrAmountMustBePositive"));
		}

		$chk = tallerpro_validate_payment_inputs($db, $paiementid, $accountid);
		if (empty($chk['valid'])) {
			return array('success' => false, 'error' => $chk['error']);
		}

		$thirdparty = new Societe($db);
		if ($fac->socid > 0) {
			$thirdparty->fetch($fac->socid);
		}

		$paiement = new Paiement($db);
		$paiement->datepaye = tallerpro_parse_payment_date($datepaye_str);
		$paiement->amounts = array($fac->id => $resteapayer);
		if (!empty($fac->multicurrency_code)) {
			$paiement->multicurrency_code = array($fac->id => $fac->multicurrency_code);
		}
		if (!empty($fac->multicurrency_tx)) {
			$paiement->multicurrency_tx = array($fac->id => $fac->multicurrency_tx);
		}
		$paiement->paiementid = (int) $paiementid;
		$paiement->paiementcode = $chk['paiementcode'];
		$paiement->num_payment = trim((string) $num_payment);
		$paiement->note_private = 'Cobro registrado desde TallerPro';
		$paiement->fk_account = (isModEnabled('bank') && (int) $accountid > 0) ? (int) $accountid : 0;

		$paiement_id = $paiement->create($user, 1, $thirdparty);
		if ($paiement_id <= 0) {
			$err = trim($paiement->error . ' ' . implode(' ', $paiement->errors));
			dol_syslog("TallerPro - Error al crear Paiement para factura ID " . $fac->id . ": " . $err, LOG_ERR);
			return array('success' => false, 'error' => $langs->trans("ErrRegisterPaymentDolibarr") . (!empty($err) ? ': ' . $err : '.'));
		}

		if (isModEnabled('bank') && (int) $accountid > 0) {
			$label = ($fac->type == Facture::TYPE_CREDIT_NOTE) ? '(CustomerInvoicePaymentBack)' : '(CustomerInvoicePayment)';
			$bank_line_id = $paiement->addPaymentToBank($user, 'payment', $label, (int) $accountid, '', '');
			if ($bank_line_id < 0) {
				$err = trim($paiement->error . ' ' . implode(' ', $paiement->errors));
				dol_syslog("TallerPro - Error al añadir apunte bancario para Paiement ID " . $paiement_id . ": " . $err, LOG_ERR);
				return array('success' => false, 'error' => $langs->trans("ErrCreatePaymentBankLine") . (!empty($err) ? ': ' . $err : '.'));
			}
		}

		return array('success' => true, 'error' => '');
	}
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
	tallerpro_require_post_csrf();
	tallerpro_require_permission('facturar');

	$fecha = trim(GETPOST('fecha', 'alpha'));
	if (empty($fecha)) {
		$fecha = date('Y-m-d');
	}

	$total = (float) price2num(GETPOST('total', 'alphanohtml'));
	$fk_soc = GETPOSTINT('fk_soc');
	$fk_vehiculo = GETPOSTINT('fk_vehiculo');

	$estado = trim(GETPOST('estado', 'alphanohtml'));
	if (empty($estado) || ($estado != 'Pagada' && $estado != 'Pendiente' && $estado != 'Borrador')) {
		$estado = 'Borrador';
	}

	$notas = trim(GETPOST('notas', 'restricthtml'));
	$pay_datepaye = trim(GETPOST('datepaye', 'alpha'));
	$pay_paiementid = GETPOSTINT('paiementid');
	$pay_accountid = GETPOSTINT('accountid');
	$pay_num_payment = trim(GETPOST('num_payment', 'alphanohtml'));

	$pre_error = '';
	if ($fk_soc <= 0) {
		$pre_error = $langs->trans("ErrSelectClientInvoice");
	} elseif (!tallerpro_record_in_entity($db, 'societe', $fk_soc, 'societe')) {
		$pre_error = $langs->trans("ErrClientInvalidEntity");
	} elseif ($fk_vehiculo > 0 && !tallerpro_record_in_entity($db, 'taller_vehiculo', $fk_vehiculo, 'taller_vehiculo', 'status = 1')) {
		$pre_error = $langs->trans("ErrVehicleNotInEntity");
	} elseif ($estado == 'Pagada') {
		if ($total <= 0) {
			$pre_error = $langs->trans("ErrCreatePaidPositiveTotal");
		} else {
			$pay_chk = tallerpro_validate_payment_inputs($db, $pay_paiementid, $pay_accountid);
			if (empty($pay_chk['valid'])) {
				$pre_error = $pay_chk['error'];
			}
		}
	}

	if (!empty($pre_error)) {
		setEventMessages($pre_error, null, 'errors');
	} else {
		$db->begin();
		$ok = true;
		$create_error_msg = '';

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
				$res_line = $fac->addline($langs->transnoentitiesnoconv("ServiciosDeTaller"), $subprice, 1, $tva_tx, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1);
				if ($res_line <= 0) {
					$ok = false;
				}
			}
			if ($ok && $estado != 'Borrador') {
				$res_val = $fac->validate($user);
				if ($res_val >= 0) {
					if ($estado == 'Pagada') {
						$pay_res = tallerpro_register_invoice_payment($db, $fac, $user, $pay_paiementid, $pay_accountid, $pay_datepaye, $pay_num_payment);
						if (empty($pay_res['success'])) {
							$ok = false;
							$create_error_msg = $pay_res['error'];
						}
					}
				} else {
					$ok = false;
				}
			}
		} else {
			$ok = false;
		}

		if ($ok) {
			$db->commit();
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $fac_id);
			exit;
		} else {
			$db->rollback();
			dol_syslog("TallerPro - Error al crear factura: " . $fac->error . " " . implode(' ', $fac->errors), LOG_ERR);
			setEventMessages(!empty($create_error_msg) ? $create_error_msg : $langs->trans("ErrGenerateInvoice"), null, 'errors');
		}
	}
}

// Acción: Actualizar Factura o Presupuesto (Propal)
if ($action == 'update_factura' || $action == 'edit_factura') {
	tallerpro_require_post_csrf();
	tallerpro_require_permission('facturar');

	$rowid = GETPOSTINT('rowid');
	$fecha = trim(GETPOST('fecha', 'alpha'));
	$fk_soc = GETPOSTINT('fk_soc');
	$fk_vehiculo = GETPOSTINT('fk_vehiculo');
	$estado = trim(GETPOST('estado', 'alphanohtml'));
	$notas = trim(GETPOST('notas', 'restricthtml'));
	$pay_datepaye = trim(GETPOST('datepaye', 'alpha'));
	$pay_paiementid = GETPOSTINT('paiementid');
	$pay_accountid = GETPOSTINT('accountid');
	$pay_num_payment = trim(GETPOST('num_payment', 'alphanohtml'));

	if ($rowid <= 0) {
		setEventMessages($langs->trans("ErrInvalidInvoiceId"), null, 'errors');
	} elseif ($is_propal_request && $propal_enabled) {
		$propal = new Propal($db);
		if ($propal->fetch($rowid) > 0 && tallerpro_is_entity_allowed($propal->entity, 'propal')) {
			if ($propal->statut != Propal::STATUS_DRAFT) {
				setEventMessages($langs->trans("ErrOnlyDraftQuoteEditable"), null, 'errors');
				header("Location: " . $_SERVER['PHP_SELF'] . '?doc=propal&id=' . $rowid);
				exit;
			}
			$db->begin();
			if (!empty($fecha)) {
				$propal->date = dol_stringtotime($fecha);
				$propal->datep = $propal->date;
			}
			$propal->note_public = $notas;
			$res_upd = $propal->update($user);
			if ($res_upd >= 0) {
				$db->commit();
				setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
				header("Location: " . $_SERVER['PHP_SELF'] . '?doc=propal&id=' . $rowid);
				exit;
			} else {
				$db->rollback();
				dol_syslog("TallerPro - Error al actualizar presupuesto Propal ID " . $rowid . ": " . $propal->error, LOG_ERR);
				setEventMessages($langs->trans("ErrUpdatePropal"), null, 'errors');
			}
		} else {
			setEventMessages($langs->trans("ErrQuoteNotFoundModify"), null, 'errors');
		}
	} else {
		$fac = new Facture($db);
		if ($fac->fetch($rowid) > 0 && tallerpro_is_entity_allowed($fac->entity, 'facture')) {
			if ($fac->statut != Facture::STATUS_DRAFT) {
				setEventMessages($langs->trans("ErrOnlyDraftInvoiceEditable"), null, 'errors');
				header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $rowid);
				exit;
			}

			$old_ref = $fac->ref;
			$fac->fetch_optionals();
			$is_presupuesto = (!empty($fac->array_options['options_tipo_doc']) && $fac->array_options['options_tipo_doc'] === 'presupuesto');

			$pre_pay_error = '';
			if (!$is_presupuesto && $estado == 'Pagada') {
				if ((float) $fac->total_ttc <= 0) {
					$pre_pay_error = $langs->trans("ErrTotalGreaterZeroForPaid");
				} else {
					$pay_chk = tallerpro_validate_payment_inputs($db, $pay_paiementid, $pay_accountid);
					if (empty($pay_chk['valid'])) {
						$pre_pay_error = $pay_chk['error'];
					}
				}
			}

			if (!empty($pre_pay_error)) {
				setEventMessages($pre_pay_error, null, 'errors');
			} else {
				$db->begin();
				$ok = true;
				$upd_error_msg = '';

				// Actualizar Fecha y Notas (públicas) en la factura borrador
				if (!empty($fecha)) {
					$fac->date = dol_stringtotime($fecha);
				}
				$fac->note_public = $notas;
				$res_upd = $fac->update($user);
				if ($res_upd < 0) {
					$ok = false;
				}

				// Actualizar el estado si se cambia a Pendiente o Pagada
				if ($ok && !$is_presupuesto && ($estado == 'Pendiente' || $estado == 'Pagada')) {
					$res_val = $fac->validate($user);
					if ($res_val >= 0) {
						// Sincronizar referencia definitiva en reparaciones si se validó la factura
						if (!empty($old_ref) && $old_ref !== $fac->ref) {
							if (!$db->query("UPDATE " . MAIN_DB_PREFIX . "taller_reparacion SET ref_factura = '" . $db->escape($fac->ref) . "' WHERE ref_factura = '" . $db->escape($old_ref) . "' AND entity IN (" . getEntity('taller_reparacion') . ")")) {
								$ok = false;
							}
						}
						if ($ok && $estado == 'Pagada') {
							$pay_res = tallerpro_register_invoice_payment($db, $fac, $user, $pay_paiementid, $pay_accountid, $pay_datepaye, $pay_num_payment);
							if (empty($pay_res['success'])) {
								$ok = false;
								$upd_error_msg = $pay_res['error'];
							}
						}
					} else {
						$ok = false;
						dol_syslog("TallerPro - Error al validar factura borrador ID " . $rowid . ": " . $fac->error . " " . implode(' ', $fac->errors), LOG_ERR);
					}
				}

				if ($ok) {
					$db->commit();
					setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
					header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $rowid);
					exit;
				} else {
					$db->rollback();
					setEventMessages(!empty($upd_error_msg) ? $upd_error_msg : $langs->trans("ErrUpdateOrValidateDraft"), null, 'errors');
				}
			}
		} else {
			setEventMessages($langs->trans("ErrInvoiceNotFoundModify"), null, 'errors');
		}
	}
}

// Acción: Cambiar Estado de Factura directamente desde la tabla (Borrador -> Pendiente / Pagada, Pendiente <-> Pagada)
if ($action == 'update_factura_estado' || $action == 'update_estado') {
	$is_ajax_state = (bool) (GETPOSTINT('ajax') || !empty($_SERVER['HTTP_X_REQUESTED_WITH']));
	tallerpro_require_post_csrf($is_ajax_state);
	tallerpro_require_permission('facturar', $is_ajax_state);

	$rowid = GETPOSTINT('rowid');
	$new_estado = trim(GETPOST('new_estado', 'alphanohtml'));
	$pay_datepaye = trim(GETPOST('datepaye', 'alpha'));
	$pay_paiementid = GETPOSTINT('paiementid');
	$pay_accountid = GETPOSTINT('accountid');
	$pay_num_payment = trim(GETPOST('num_payment', 'alphanohtml'));

	$res_success = false;
	$error_msg = '';
	$updated_ref = '';
	$is_borrador_now = false;
	$final_estado = '';

	if ($is_propal_request) {
		$error_msg = $langs->trans("ErrQuotesAlwaysDraft");
	} elseif ($rowid > 0 && in_array($new_estado, array('Pendiente', 'Pagada'))) {
		$fac = new Facture($db);
		if ($fac->fetch($rowid) > 0 && tallerpro_is_entity_allowed($fac->entity, 'facture')) {
			$fac->fetch_optionals();
			if (!empty($fac->array_options['options_tipo_doc']) && $fac->array_options['options_tipo_doc'] === 'presupuesto') {
				$error_msg = $langs->trans("ErrQuotesAlwaysDraft");
			} else {
				$old_ref = $fac->ref;
				$is_draft = ($fac->statut == Facture::STATUS_DRAFT);
				$was_paid = ($fac->statut == Facture::STATUS_CLOSED || $fac->paye == 1);

				$db->begin();
				$ok = true;

				if ($is_draft) {
					// Validar borrador para asignar número oficial definitivo
					$res_val = $fac->validate($user);
					if ($res_val >= 0) {
						// Sincronizar referencia si cambió en órdenes de reparación
						if (!empty($old_ref) && $old_ref !== $fac->ref) {
							if (!$db->query("UPDATE " . MAIN_DB_PREFIX . "taller_reparacion SET ref_factura = '" . $db->escape($fac->ref) . "' WHERE ref_factura = '" . $db->escape($old_ref) . "' AND entity IN (" . getEntity('taller_reparacion') . ")")) {
								$ok = false;
							}
						}
						if ($ok && $new_estado === 'Pagada') {
							$pay_res = tallerpro_register_invoice_payment($db, $fac, $user, $pay_paiementid, $pay_accountid, $pay_datepaye, $pay_num_payment);
							if (empty($pay_res['success'])) {
								$ok = false;
								$error_msg = $pay_res['error'];
							}
						}
					} else {
						$ok = false;
						dol_syslog("TallerPro - Error al validar factura borrador ID " . $rowid . ": " . $fac->error . " " . implode(' ', $fac->errors), LOG_ERR);
						$error_msg = $langs->trans("ErrValidateDraftInvoice");
					}
				} else {
					// Factura ya validada: cambiar entre Pendiente y Pagada
					if ($new_estado === 'Pagada' && !$was_paid) {
						$pay_res = tallerpro_register_invoice_payment($db, $fac, $user, $pay_paiementid, $pay_accountid, $pay_datepaye, $pay_num_payment);
						if (empty($pay_res['success'])) {
							$ok = false;
							$error_msg = $pay_res['error'];
						}
					} elseif ($new_estado === 'Pendiente' && $was_paid) {
						$paid_sum = (float) $fac->getSommePaiement();
						$res_pf = $db->query("SELECT COUNT(rowid) as nb FROM " . MAIN_DB_PREFIX . "paiement_facture WHERE fk_facture = " . ((int) $fac->id));
						$nb_pf = ($res_pf && ($pf_obj = $db->fetch_object($res_pf))) ? (int) $pf_obj->nb : 0;

						if ($paid_sum > 0 || $nb_pf > 0) {
							$ok = false;
							$error_msg = $langs->trans("ErrInvoiceHasPaymentsReopen");
						} elseif ($fac->setUnpaid($user) < 0) {
							$ok = false;
							$error_msg = $langs->trans("ErrReopenInvoicePending");
						}
					}
				}

				if ($ok) {
					$db->commit();
					$res_success = true;
				} else {
					$db->rollback();
					$res_success = false;
				}
			}

			if ($res_success) {
				$fac->fetch($rowid);
				$final_estado = ($fac->statut == Facture::STATUS_DRAFT) ? 'Borrador' : (($fac->paye == 1 || $fac->statut == Facture::STATUS_CLOSED) ? 'Pagada' : 'Pendiente');
				$is_borrador_now = ($final_estado === 'Borrador');
				$updated_ref = $fac->ref;
			}
		} else {
			$error_msg = $langs->trans("ErrInvoiceNotFound");
		}
	} else {
		$error_msg = $langs->trans("ErrInvalidInvoiceOrState");
	}

	if (GETPOSTINT('ajax') || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
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
		setEventMessages($error_msg ?: $langs->trans("ErrUpdateStatus"), null, 'errors');
	}
	header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $rowid);
	exit;
}

// Acción: Eliminar Factura o Presupuesto Propal (Seguro vía POST con token)
if ($action == 'delete_factura' || $action == 'delete') {
	tallerpro_require_post_csrf();
	tallerpro_require_permission('facturar');
	tallerpro_require_permission('delete');

	$rowid = GETPOSTINT('rowid');
	if ($rowid > 0) {
		if ($is_propal_request && $propal_enabled) {
			$propal = new Propal($db);
			if ($propal->fetch($rowid) > 0 && tallerpro_is_entity_allowed($propal->entity, 'propal')) {
				if ($propal->statut != Propal::STATUS_DRAFT) {
					setEventMessages($langs->trans("ErrOnlyDraftQuoteDeletable"), null, 'errors');
					header("Location: " . $_SERVER['PHP_SELF']);
					exit;
				}
				$db->begin();
				$res = $propal->delete($user);
				if ($res > 0) {
					$db->commit();
					setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
					header("Location: " . $_SERVER['PHP_SELF']);
					exit;
				} else {
					$db->rollback();
					dol_syslog("TallerPro - Error al eliminar presupuesto Propal ID " . $rowid . ": " . $propal->error, LOG_ERR);
					setEventMessages($langs->trans("ErrDeleteQuote"), null, 'errors');
				}
			}
		} else {
			$fac = new Facture($db);
			if ($fac->fetch($rowid) > 0 && tallerpro_is_entity_allowed($fac->entity, 'facture')) {
				if ($fac->statut != Facture::STATUS_DRAFT) {
					setEventMessages($langs->trans("ErrOnlyDraftInvoiceDeletable"), null, 'errors');
					header("Location: " . $_SERVER['PHP_SELF']);
					exit;
				}
				$del_ref = $fac->ref;
				$db->begin();
				$res = $fac->delete($user);
				$del_ok = ($res > 0);
				if ($del_ok && !empty($del_ref)) {
					if (!$db->query("UPDATE " . MAIN_DB_PREFIX . "taller_reparacion SET ref_factura = NULL WHERE ref_factura = '" . $db->escape($del_ref) . "' AND entity IN (" . getEntity('taller_reparacion') . ")")) {
						$del_ok = false;
					}
				}
				if ($del_ok) {
					$db->commit();
					setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
					header("Location: " . $_SERVER['PHP_SELF']);
					exit;
				} else {
					$db->rollback();
					dol_syslog("TallerPro - Error al eliminar factura ID " . $rowid . ": " . $fac->error, LOG_ERR);
					setEventMessages($langs->trans("ErrDeleteInvoice"), null, 'errors');
				}
			}
		}
	}
}

// Generar documento PDF de la factura o presupuesto según diseño de referencia
if ($action == 'pdf' || $action == 'download_pdf') {
	$fac_id = GETPOSTINT('id');
	if (empty($fac_id)) {
		$fac_id = $is_propal_request ? GETPOSTINT('propal_id') : GETPOSTINT('fk_factura');
	}
	if ($fac_id > 0) {
		require_once __DIR__ . '/lib/pdf_factura.lib.php';
		$download = GETPOSTINT('download');
		$mode = ($download == 1) ? 'D' : 'I';
		$pdf_doc_type = $is_propal_request ? 'propal' : 'factura';
		if ($pdf_doc_type === 'propal' && !$propal_enabled) {
			accessforbidden($langs->trans("ErrModulePropalDisabled"));
		}
		if (!tallerpro_generate_factura_pdf($db, $conf, $user, $fac_id, $mode, '', $pdf_doc_type)) {
			accessforbidden($langs->trans($is_propal_request ? "ErrQuoteNotFoundOrForbidden" : "ErrInvoiceNotFoundOrForbidden"));
		}
		exit;
	}
	accessforbidden($langs->trans("ErrInvalidInvoiceId"));
}

// AJAX: Obtener líneas y datos completos de una factura
if ($action == 'get_factura_lineas') {
	$fk_factura = GETPOSTINT('fk_factura');
	$out = array('materiales' => array(), 'reparaciones' => array(), 'factura' => null);
	if ($fk_factura > 0) {
		$fac = new Facture($db);
		if ($fac->fetch($fk_factura) > 0 && tallerpro_is_entity_allowed($fac->entity, 'facture')) {
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
					'descripcion' => !empty($line->desc) ? $line->desc : (!empty($line->label) ? $line->label : $langs->transnoentitiesnoconv("SinDescripcion")),
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
	tallerpro_require_post_csrf(true);
	tallerpro_require_permission('facturar', true);

	$fk_factura = GETPOSTINT('fk_factura');

	$tipo = trim(GETPOST('tipo', 'alpha'));
	if (empty($tipo)) {
		$tipo = 'material';
	}

	$fk_material = GETPOSTINT('fk_material');
	$codigo = trim(GETPOST('codigo', 'alphanohtml'));
	$descripcion = trim(GETPOST('descripcion', 'alphanohtml'));

	$cantidad = (float) price2num(GETPOST('cantidad', 'alphanohtml'));
	if ($cantidad <= 0) $cantidad = 1.0;

	$precio = (float) price2num(GETPOST('precio', 'alphanohtml'));
	$descuento = (float) price2num(GETPOST('descuento', 'alphanohtml'));

	$total_neto = $cantidad * $precio * (1 - ($descuento / 100));
	if ($total_neto < 0) $total_neto = 0;

	$notas = trim(GETPOST('notas', 'restricthtml'));
	$guardar_catalogo = GETPOSTINT('guardar_catalogo');

	if ($fk_factura <= 0) {
		header('Content-Type: application/json');
		echo json_encode(array('status' => 'error', 'message' => $langs->trans("ErrInvalidInvoiceId")));
		exit;
	}

	if (empty($descripcion)) {
		header('Content-Type: application/json');
		echo json_encode(array('status' => 'error', 'message' => $langs->trans("ErrLineDescriptionRequired")));
		exit;
	}

	$fac = new Facture($db);
	if ($fac->fetch($fk_factura) <= 0 || !tallerpro_is_entity_allowed($fac->entity, 'facture')) {
		header('Content-Type: application/json');
		echo json_encode(array('status' => 'error', 'message' => $langs->trans("ErrInvoiceNotFoundSimple")));
		exit;
	}

	if ($fac->statut != Facture::STATUS_DRAFT) {
		header('Content-Type: application/json');
		echo json_encode(array('status' => 'error', 'message' => $langs->trans("ErrOnlyDraftAddLines")));
		exit;
	}

	// Anti-duplicate protection: avoid inserting the exact same line within 3 seconds
	$fac->fetch_lines();
	foreach ($fac->lines as $existing_line) {
		if ($existing_line->desc == $descripcion && $existing_line->qty == $cantidad && $existing_line->subprice == $precio) {
			if (abs(dol_now() - $existing_line->date_creation) <= 3) {
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

	$db->begin();
	$ok = true;
	$line_id = 0;

	$fk_product_line = 0;
	if ($tipo == 'material') {
		if ($fk_material > 0) {
			$mat_dao = new TallerMaterial($db);
			if ($mat_dao->fetch($fk_material) > 0) {
				$fk_product_line = (int) $mat_dao->fk_product;
			}
		}
		if ($fk_product_line <= 0) {
			$fk_product_line = tallerpro_get_or_create_product($codigo, $descripcion, $precio, 0, $user);
			if ($fk_product_line <= 0) {
				$ok = false;
			}
		}
	}

	if ($ok && $guardar_catalogo && $tipo == 'material') {
		$mat_new = new TallerMaterial($db);
		$mat_new->nombre     = $descripcion;
		$mat_new->ref        = $codigo;
		$mat_new->fk_product = ($fk_product_line > 0 ? (int) $fk_product_line : null);
		$mat_new->precio     = (float) $precio;
		$mat_new->stock      = 0.0;
		$mat_new->entity     = (int) $conf->entity;
		if ($mat_new->create($user) <= 0) {
			$ok = false;
		}
	}

	if ($ok) {
		$product_type = ($tipo == 'reparacion') ? 1 : 0;
		$tva_tx = 21.0;
		$line_id = $fac->addline($descripcion, $precio, $cantidad, $tva_tx, 0, 0, $fk_product_line, $descuento, '', '', 0, 0, 0, 'HT', 0, $product_type, -1, 0, '', 0, 0, null, 0, '', array(), 100, 0, null, 0, $codigo, 0);
		if ($line_id <= 0) {
			$ok = false;
		}
	}

	if ($ok) {
		$db->commit();
		$fac->fetch($fk_factura);
		header('Content-Type: application/json');
		echo json_encode(array(
			'status' => 'ok',
			'id' => (int) $line_id,
			'total_neto' => (float) $total_neto,
			'new_total' => (float) $fac->total_ttc
		));
	} else {
		$db->rollback();
		$err_msg = !empty($fac->error) ? $fac->error : (!empty($fac->errors) ? implode(' ', $fac->errors) : $langs->transnoentitiesnoconv("ErrUnknown"));
		header('Content-Type: application/json');
		echo json_encode(array('status' => 'error', 'message' => trim($langs->trans("ErrAddLineDolibarr")) . ' ' . $err_msg));
	}
	exit;
}

// AJAX: Eliminar línea
if ($action == 'delete_factura_linea') {
	tallerpro_require_post_csrf(true);
	tallerpro_require_permission('facturar', true);

	$linea_id = GETPOSTINT('linea_id');
	$fk_factura = GETPOSTINT('fk_factura');

	if ($fk_factura > 0 && $linea_id > 0) {
		$fac = new Facture($db);
		if ($fac->fetch($fk_factura) > 0 && tallerpro_is_entity_allowed($fac->entity, 'facture')) {
			if ($fac->statut != Facture::STATUS_DRAFT) {
				header('Content-Type: application/json');
				echo json_encode(array('status' => 'error', 'message' => $langs->trans("ErrOnlyDraftDeleteLines")));
				exit;
			}
			$db->begin();
			$res_del = $fac->deleteline($linea_id, $fac->id);
			if ($res_del > 0) {
				$db->commit();
				$fac->fetch($fk_factura);
				header('Content-Type: application/json');
				echo json_encode(array('status' => 'ok', 'new_total' => (float) $fac->total_ttc));
				exit;
			}
			$db->rollback();
		}
	}
	header('Content-Type: application/json');
	echo json_encode(array('status' => 'error', 'message' => $langs->trans("ErrCannotDeleteLine")));
	exit;
}

// AJAX: Guardar totales de factura
if ($action == 'save_factura_totales') {
	tallerpro_require_post_csrf(true);
	tallerpro_require_permission('facturar', true);

	$fk_factura = GETPOSTINT('fk_factura');
	$descuento_global = (float) price2num(GETPOST('descuento_global', 'alphanohtml'));

	if ($fk_factura > 0) {
		$fac = new Facture($db);
		if ($fac->fetch($fk_factura) > 0 && tallerpro_is_entity_allowed($fac->entity, 'facture')) {
			if ($fac->statut != Facture::STATUS_DRAFT) {
				header('Content-Type: application/json');
				echo json_encode(array('status' => 'error', 'message' => $langs->trans("ErrOnlyDraftModifyTotals")));
				exit;
			}
			$db->begin();
			$ok = true;
			if ($descuento_global >= 0) {
				$fac->remise_percent = $descuento_global;
				if ($fac->update($user) < 0) {
					$ok = false;
				}
			}
			if ($ok && $fac->update_price() <= 0) {
				$ok = false;
			}
			if ($ok) {
				$db->commit();
				$fac->fetch($fk_factura);
				header('Content-Type: application/json');
				echo json_encode(array('status' => 'ok', 'new_total' => (float) $fac->total_ttc));
				exit;
			}
			$db->rollback();
			header('Content-Type: application/json');
			echo json_encode(array('status' => 'error', 'message' => !empty($fac->error) ? $fac->error : $langs->trans("ErrUpdateInvoice")));
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

// Consultar métodos de pago y cuentas bancarias/cajas activas de Dolibarr
$payment_methods_list = tallerpro_get_payment_methods($db, $langs);
$bank_accounts_list = tallerpro_get_bank_accounts($db);

// Consultar catálogo de materiales con stock nativo de Dolibarr
$wh_id_fac = tallerpro_get_default_warehouse($user);
$materials_catalog = array();
$sql_mc = "SELECT tm.rowid, tm.ref, tm.nombre, tm.precio, tm.stock, tm.fk_product, ps.reel as wh_stock ";
$sql_mc .= "FROM " . MAIN_DB_PREFIX . "taller_material tm ";
$sql_mc .= "LEFT JOIN " . MAIN_DB_PREFIX . "product_stock ps ON ps.fk_product = tm.fk_product AND ps.fk_entrepot = " . ((int) $wh_id_fac) . " ";
$sql_mc .= "WHERE tm.status = 1 AND tm.entity IN (" . getEntity('taller_material') . ") ORDER BY tm.nombre ASC";
$res_mc = $db->query($sql_mc);
if ($res_mc) {
	while ($mc = $db->fetch_object($res_mc)) {
		$mc->stock = ($mc->wh_stock !== null) ? (float) $mc->wh_stock : (float) $mc->stock;
		$materials_catalog[] = $mc;
	}
}

// Consultar facturas nativas de Dolibarr y presupuestos (Propal) con JOIN a cliente y vehículo
$facturas_list = array();
$sql_f = "SELECT f.rowid, f.ref as numero_factura, f.datef as fecha, f.fk_soc, f.total_ttc as total, f.total_ht, f.total_tva, ";
$sql_f .= "f.fk_statut, f.paye, COALESCE(f.remise_percent, 0) as descuento_global, 21.0 as iva_porcentaje, f.note_public as notas, ";
$sql_f .= "(CASE WHEN f.fk_statut = 0 THEN 'Borrador' WHEN (f.paye = 1 OR f.fk_statut = 2) THEN 'Pagada' ELSE 'Pendiente' END) as estado, ";
$sql_f .= "fe.fk_vehiculo, fe.fk_reparacion, 'factura' as tipo_doc, 'factura' as doc_type, ";
$sql_f .= "s.nom as cliente_nombre, ";
$sql_f .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo ";
$sql_f .= "FROM " . MAIN_DB_PREFIX . "facture as f ";
$sql_f .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON f.fk_soc = s.rowid ";
$sql_f .= "LEFT JOIN " . MAIN_DB_PREFIX . "facture_extrafields as fe ON fe.fk_object = f.rowid ";
$sql_f .= "LEFT JOIN " . MAIN_DB_PREFIX . "taller_vehiculo as v ON fe.fk_vehiculo = v.rowid ";
$sql_f .= "WHERE f.entity IN (" . getEntity('facture') . ") AND COALESCE(fe.tipo_doc, 'factura') != 'presupuesto' ";
$sql_f .= "ORDER BY f.datef DESC, f.rowid DESC";
$res_f = $db->query($sql_f);
if ($res_f) {
	while ($f_obj = $db->fetch_object($res_f)) {
		$f_obj->fecha = !empty($f_obj->fecha) ? substr((string) $f_obj->fecha, 0, 10) : '';
		$facturas_list[] = $f_obj;
	}
}

if ($propal_enabled) {
	$sql_p = "SELECT p.rowid, p.ref as numero_factura, p.datep as fecha, p.fk_soc, p.total_ttc as total, p.total_ht, p.total_tva, ";
	$sql_p .= "p.fk_statut, 0 as paye, COALESCE(p.remise_percent, 0) as descuento_global, 21.0 as iva_porcentaje, p.note_public as notas, ";
	$sql_p .= "'Presupuesto' as estado, ";
	$sql_p .= "pe.fk_vehiculo, pe.fk_reparacion, 'presupuesto' as tipo_doc, 'propal' as doc_type, ";
	$sql_p .= "s.nom as cliente_nombre, ";
	$sql_p .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo ";
	$sql_p .= "FROM " . MAIN_DB_PREFIX . "propal as p ";
	$sql_p .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON p.fk_soc = s.rowid ";
	$sql_p .= "LEFT JOIN " . MAIN_DB_PREFIX . "propal_extrafields as pe ON pe.fk_object = p.rowid ";
	$sql_p .= "LEFT JOIN " . MAIN_DB_PREFIX . "taller_vehiculo as v ON pe.fk_vehiculo = v.rowid ";
	$sql_p .= "WHERE p.entity IN (" . getEntity('propal') . ") ";
	$sql_p .= "ORDER BY p.datep DESC, p.rowid DESC";
	$res_p = $db->query($sql_p);
	if ($res_p) {
		while ($p_obj = $db->fetch_object($res_p)) {
			$p_obj->fecha = !empty($p_obj->fecha) ? substr((string) $p_obj->fecha, 0, 10) : '';
			$facturas_list[] = $p_obj;
		}
	}
}

if (count($facturas_list) > 1) {
	usort($facturas_list, function ($a, $b) {
		$cmp = strcmp((string) $b->fecha, (string) $a->fecha);
		if ($cmp !== 0) {
			return $cmp;
		}
		return ((int) $b->rowid) - ((int) $a->rowid);
	});
}
$total_facturas = count($facturas_list);

// Siguiente número previsto
$next_factura_numero = getNextFacturaNumero($db);

// Vista individual de una factura o presupuesto (Documental)
$view_id = GETPOSTINT('id');
if (empty($view_id)) {
	$view_id = GETPOSTINT('propal_id');
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
	if ($is_propal_request && $propal_enabled) {
		$sql_single = "SELECT p.rowid, p.ref as numero_factura, p.datep as fecha, p.fk_soc, p.total_ttc as total, p.total_ht, p.total_tva, ";
		$sql_single .= "p.fk_statut, 0 as paye, COALESCE(p.remise_percent, 0) as descuento_global, 21.0 as iva_porcentaje, p.note_public as notas, ";
		$sql_single .= "'Presupuesto' as estado, ";
		$sql_single .= "pe.fk_vehiculo, pe.fk_reparacion, 'presupuesto' as tipo_doc, 'propal' as doc_type, ";
		$sql_single .= "s.nom as cliente_nombre, COALESCE(NULLIF(s.siren, ''), NULLIF(s.siret, ''), s.tva_intra) as cliente_cif, COALESCE(NULLIF(s.town, ''), s.address) as cliente_ciudad, s.phone as cliente_telefono, s.email as cliente_email, ";
		$sql_single .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo, v.anio as vehiculo_anio ";
		$sql_single .= "FROM " . MAIN_DB_PREFIX . "propal as p ";
		$sql_single .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON p.fk_soc = s.rowid ";
		$sql_single .= "LEFT JOIN " . MAIN_DB_PREFIX . "propal_extrafields as pe ON pe.fk_object = p.rowid ";
		$sql_single .= "LEFT JOIN " . MAIN_DB_PREFIX . "taller_vehiculo as v ON pe.fk_vehiculo = v.rowid ";
		$sql_single .= "WHERE p.rowid = " . ((int) $view_id) . " AND p.entity IN (" . getEntity('propal') . ")";
	} else {
		$sql_single = "SELECT f.rowid, f.ref as numero_factura, f.datef as fecha, f.fk_soc, f.total_ttc as total, f.total_ht, f.total_tva, ";
		$sql_single .= "f.fk_statut, f.paye, COALESCE(f.remise_percent, 0) as descuento_global, 21.0 as iva_porcentaje, f.note_public as notas, ";
		$sql_single .= "(CASE WHEN COALESCE(fe.tipo_doc, '') = 'presupuesto' THEN 'Presupuesto' WHEN f.fk_statut = 0 THEN 'Borrador' WHEN (f.paye = 1 OR f.fk_statut = 2) THEN 'Pagada' ELSE 'Pendiente' END) as estado, ";
		$sql_single .= "fe.fk_vehiculo, fe.fk_reparacion, COALESCE(fe.tipo_doc, 'factura') as tipo_doc, 'factura' as doc_type, ";
		$sql_single .= "s.nom as cliente_nombre, COALESCE(NULLIF(s.siren, ''), NULLIF(s.siret, ''), s.tva_intra) as cliente_cif, COALESCE(NULLIF(s.town, ''), s.address) as cliente_ciudad, s.phone as cliente_telefono, s.email as cliente_email, ";
		$sql_single .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo, v.anio as vehiculo_anio ";
		$sql_single .= "FROM " . MAIN_DB_PREFIX . "facture as f ";
		$sql_single .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON f.fk_soc = s.rowid ";
		$sql_single .= "LEFT JOIN " . MAIN_DB_PREFIX . "facture_extrafields as fe ON fe.fk_object = f.rowid ";
		$sql_single .= "LEFT JOIN " . MAIN_DB_PREFIX . "taller_vehiculo as v ON fe.fk_vehiculo = v.rowid ";
		$sql_single .= "WHERE f.rowid = " . ((int) $view_id) . " AND f.entity IN (" . getEntity('facture') . ")";
	}
	$res_single = $db->query($sql_single);
	if ($res_single && ($fs = $db->fetch_object($res_single))) {
		$fs->fecha = !empty($fs->fecha) ? substr((string) $fs->fecha, 0, 10) : '';
		$factura_single = $fs;
		$single_tot_bruto = 0.0;
		$single_tot_neto = 0.0;
		$single_tot_dto_lineas = 0.0;
		$tot_mat = 0.0;
		$tot_rep = 0.0;
		if ($is_propal_request && $propal_enabled) {
			$sql_single_lines = "SELECT pd.rowid, (CASE WHEN pd.product_type = 1 THEN 'reparacion' ELSE 'material' END) as tipo, ";
			$sql_single_lines .= "COALESCE(NULLIF(pd.label, ''), pr.ref) as codigo, ";
			$sql_single_lines .= "(CASE WHEN pd.description IS NOT NULL AND pd.description != '' THEN pd.description ELSE pd.label END) as descripcion, ";
			$sql_single_lines .= "pd.qty as cantidad, pd.subprice as precio, pd.remise_percent as descuento, pd.total_ht as total_neto ";
			$sql_single_lines .= "FROM " . MAIN_DB_PREFIX . "propaldet as pd ";
			$sql_single_lines .= "LEFT JOIN " . MAIN_DB_PREFIX . "product as pr ON pd.fk_product = pr.rowid ";
			$sql_single_lines .= "WHERE pd.fk_propal = " . ((int) $view_id) . " ORDER BY pd.rang ASC, pd.rowid ASC";
		} else {
			$sql_single_lines = "SELECT fd.rowid, (CASE WHEN fd.product_type = 1 THEN 'reparacion' ELSE 'material' END) as tipo, ";
			$sql_single_lines .= "COALESCE(NULLIF(fd.ref_ext, ''), p.ref) as codigo, ";
			$sql_single_lines .= "(CASE WHEN fd.description IS NOT NULL AND fd.description != '' THEN fd.description ELSE fd.label END) as descripcion, ";
			$sql_single_lines .= "fd.qty as cantidad, fd.subprice as precio, fd.remise_percent as descuento, fd.total_ht as total_neto ";
			$sql_single_lines .= "FROM " . MAIN_DB_PREFIX . "facturedet as fd ";
			$sql_single_lines .= "LEFT JOIN " . MAIN_DB_PREFIX . "product as p ON fd.fk_product = p.rowid ";
			$sql_single_lines .= "WHERE fd.fk_facture = " . ((int) $view_id) . " ORDER BY fd.rang ASC, fd.rowid ASC";
		}
		$res_lin = $db->query($sql_single_lines);
		if ($res_lin) {
			while ($l = $db->fetch_object($res_lin)) {
				$qty = (float) $l->cantidad;
				if ($qty <= 0) $qty = 1.0;
				$p_unit = (float) $l->precio;
				$l_bruto = $qty * $p_unit;
				$l_neto = (float) $l->total_neto;
				$l_dto = max(0, $l_bruto - $l_neto);

				$single_tot_bruto += $l_bruto;
				$single_tot_neto += $l_neto;
				$single_tot_dto_lineas += $l_dto;

				if ($l->tipo == 'material') {
					$single_materiales[] = $l;
					$tot_mat += $l_neto;
				} else {
					$single_reparaciones[] = $l;
					$tot_rep += $l_neto;
				}
			}
		}

		$single_global_desc_percent = (float) $factura_single->descuento_global;
		$single_global_desc_amt = ($single_global_desc_percent > 0) ? ($single_tot_neto * ($single_global_desc_percent / 100)) : 0.0;

		$single_subtotal = $single_tot_bruto;
		$single_desc = $single_tot_dto_lineas + $single_global_desc_amt;
		$single_base = max(0, $single_subtotal - $single_desc);
		$single_iva_pct = 21.0;
		$single_iva_amt = (float) $factura_single->total_tva > 0 ? (float) $factura_single->total_tva : round($single_base * ($single_iva_pct / 100), 2);
		$single_total = (float) $factura_single->total > 0 ? (float) $factura_single->total : ($single_base + $single_iva_amt);
	}
}

$morehead = tallerpro_build_morehead($langs);

// Page Header
llxHeader($morehead, $langs->trans("Facturas") . ' - TallerPro', '', '', 0, 0, array(), array(), '', 'mod-taller page-tallerpro');

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
					<?php if ($view_id > 0 && $factura_single): ?>
						<a href="<?php echo $facturas_url; ?>" class="tallerpro-topbar-breadcrumb-parent"><?php echo $langs->trans("Facturas"); ?></a>
						<span class="tallerpro-topbar-breadcrumb-sep">/</span>
						<span class="tallerpro-topbar-breadcrumb-current"><?php echo dol_escape_htmltag($factura_single->numero_factura); ?></span>
					<?php else: ?>
						<span class="tallerpro-topbar-breadcrumb-current"><?php echo $langs->trans("Facturas"); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<div class="tallerpro-topbar-right"></div>
		</header>

		<!-- Dashboard Content -->
		<div class="tallerpro-content-wrapper">
			<main class="tallerpro-content">
				<?php if ($view_id > 0 && $factura_single): ?>
					<?php
					$single_is_propal = ((!empty($factura_single->doc_type) && $factura_single->doc_type === 'propal') || (!empty($factura_single->tipo_doc) && $factura_single->tipo_doc === 'presupuesto'));
					$f_estado_lower = strtolower(trim($factura_single->estado));
					if ($single_is_propal || $f_estado_lower == 'presupuesto') {
						$badge_style = 'background-color: #e0f2fe !important; color: #0369a1 !important; border: 1px solid #bae6fd !important;';
						$badge_label = $langs->trans("PresupuestoSingular");
					} elseif ($f_estado_lower == 'pagada') {
						$badge_style = 'background-color: #ecfdf5 !important; color: #047857 !important; border: 1px solid #a7f3d0 !important;';
						$badge_label = $langs->trans("Pagada");
					} elseif ($f_estado_lower == 'borrador') {
						$badge_style = 'background-color: #f1f5f9 !important; color: #475569 !important; border: 1px solid #cbd5e1 !important;';
						$badge_label = $langs->trans("Borrador");
					} else {
						$badge_style = 'background-color: #fffbeb !important; color: #b45309 !important; border: 1px solid #fde68a !important;';
						$badge_label = $langs->trans("Pendiente");
					}
					$single_pdf_url = $facturas_url . '?action=pdf' . ($single_is_propal ? '&doc=propal' : '') . '&id=' . ((int) $factura_single->rowid);
					?>

					<!-- Vista Detalle Completa de Factura (según diseño de referencia) -->
					<div class="tallerpro-invoice-view-wrapper">
						<!-- Top Action Bar -->
						<div class="tallerpro-invoice-view-topbar">
							<a href="<?php echo $facturas_url; ?>" class="tallerpro-back-link">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<line x1="19" y1="12" x2="5" y2="12"></line>
									<polyline points="12 19 5 12 12 5"></polyline>
								</svg>
								<span><?php echo $langs->trans("VolverAFacturas"); ?></span>
							</a>
							<div class="tallerpro-invoice-view-top-actions">
								<span class="tallerpro-invoice-badge-status" style="<?php echo $badge_style; ?>">
									<?php echo dol_escape_htmltag($badge_label); ?>
								</span>
								<a href="<?php echo $single_pdf_url; ?>" target="_blank" class="tallerpro-btn-pdf"
									style="display: inline-flex !important; align-items: center !important; gap: 7px !important; background-color: #0f2b5c !important; color: #ffffff !important; font-size: 13px !important; font-weight: 600 !important; padding: 8px 16px !important; border-radius: 8px !important; border: none !important; cursor: pointer !important; text-decoration: none !important; box-shadow: 0 1px 2px rgba(15, 43, 92, 0.2) !important;">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
										<polyline points="7 10 12 15 17 10"></polyline>
										<line x1="12" y1="15" x2="12" y2="3"></line>
									</svg>
									<span><?php echo $langs->trans("DescargarPDF"); ?></span>
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
								<span><?php echo $single_is_propal ? $langs->trans("PresupuestoUpper") : $langs->trans("FacturaUpper"); ?></span>
							</div>
							<h1 class="tallerpro-invoice-doc-num"><?php echo dol_escape_htmltag($factura_single->numero_factura); ?></h1>
							<div class="tallerpro-invoice-doc-date"><?php echo !empty($factura_single->fecha) ? dol_escape_htmltag($factura_single->fecha) : date('Y-m-d'); ?></div>
						</div>

						<!-- 2 Column Cards: Cliente & Vehículo -->
						<div class="tallerpro-invoice-cards-grid">
							<div class="tallerpro-invoice-info-card">
								<div class="tallerpro-info-card-tag"><?php echo $langs->trans("ClienteUpper"); ?></div>
								<div class="tallerpro-info-card-title"><?php echo dol_escape_htmltag($factura_single->cliente_nombre ?: $langs->trans("SinCliente")); ?></div>
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
								<div class="tallerpro-info-card-tag"><?php echo $langs->trans("VehiculoUpper"); ?></div>
								<div class="tallerpro-info-card-title"><?php echo dol_escape_htmltag($factura_single->vehiculo_matricula ?: $langs->trans("SinVehiculo")); ?></div>
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
								<span><?php echo $langs->trans("Materiales"); ?></span>
							</div>
							<div class="tallerpro-invoice-table-card">
								<table class="tallerpro-invoice-table">
									<thead>
										<tr>
											<th style="width: 15%;"><?php echo $langs->trans("ColCodigoLabel"); ?></th>
											<th style="width: 45%;"><?php echo $langs->trans("ColDescripcionLabel"); ?></th>
											<th style="width: 10%; text-align: center;"><?php echo $langs->trans("ColCantShortLabel"); ?></th>
											<th style="width: 12%; text-align: right;"><?php echo $langs->trans("ColPrecioUnitLabel"); ?></th>
											<th style="width: 8%; text-align: center;"><?php echo $langs->trans("ColDtoShortLabel"); ?></th>
											<th style="width: 15%; text-align: right;"><?php echo $langs->trans("ColTotalLabel"); ?></th>
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
								<span><?php echo $langs->trans("ReparacionesManoDeObra"); ?></span>
							</div>
							<div class="tallerpro-invoice-table-card">
								<table class="tallerpro-invoice-table">
									<thead>
										<tr>
											<th style="width: 60%;"><?php echo $langs->trans("ColDescripcionLabel"); ?></th>
											<th style="width: 15%; text-align: right;"><?php echo $langs->trans("Precio"); ?></th>
											<th style="width: 10%; text-align: center;"><?php echo $langs->trans("ColDtoShortLabel"); ?></th>
											<th style="width: 15%; text-align: right;"><?php echo $langs->trans("ColTotalLabel"); ?></th>
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
									<span style="color: #64748b !important; font-weight: 600 !important; font-size: 13px !important; letter-spacing: 0.03em !important;"><?php echo $langs->trans("SubtotalUpper"); ?></span>
									<span style="color: #0f172a !important; font-weight: 600 !important; font-size: 13.5px !important; text-align: right !important;"><?php echo number_format((float) $single_subtotal, 2, '.', '') . ' €'; ?></span>
								</div>
								<div class="tallerpro-totals-row" style="display: flex !important; justify-content: space-between !important; align-items: center !important; font-size: 13.5px !important; color: #64748b !important; width: 100% !important;">
									<span style="color: #64748b !important; font-weight: 600 !important; font-size: 13px !important; letter-spacing: 0.03em !important;"><?php echo $langs->trans("DescuentoUpper"); ?></span>
									<span style="color: #0f172a !important; font-weight: 600 !important; font-size: 13.5px !important; text-align: right !important;"><?php echo number_format((float) $single_desc, 2, '.', '') . ' €'; ?></span>
								</div>
								<div class="tallerpro-totals-divider" style="height: 1px !important; background-color: #f1f5f9 !important; margin: 4px 0 !important; border: none !important; width: 100% !important;"></div>
								<div class="tallerpro-totals-row" style="display: flex !important; justify-content: space-between !important; align-items: center !important; font-size: 13.5px !important; color: #64748b !important; width: 100% !important;">
									<span style="color: #64748b !important; font-weight: 600 !important; font-size: 13px !important; letter-spacing: 0.03em !important;"><?php echo $langs->trans("SubtotalDtoUpper"); ?></span>
									<span style="color: #0f172a !important; font-weight: 600 !important; font-size: 13.5px !important; text-align: right !important;"><?php echo number_format((float) $single_base, 2, '.', '') . ' €'; ?></span>
								</div>
								<div class="tallerpro-totals-row" style="display: flex !important; justify-content: space-between !important; align-items: center !important; font-size: 13.5px !important; color: #64748b !important; width: 100% !important;">
									<span style="color: #64748b !important; font-weight: 600 !important; font-size: 13px !important; letter-spacing: 0.03em !important;"><?php echo $langs->trans("IVA"); ?> (<?php echo (int) $single_iva_pct; ?>%)</span>
									<span style="color: #0f172a !important; font-weight: 600 !important; font-size: 13.5px !important; text-align: right !important;"><?php echo number_format((float) $single_iva_amt, 2, '.', '') . ' €'; ?></span>
								</div>
								<div class="tallerpro-totals-divider" style="height: 1px !important; background-color: #f1f5f9 !important; margin: 4px 0 !important; border: none !important; width: 100% !important;"></div>
								<div class="tallerpro-totals-row tallerpro-totals-final" style="display: flex !important; justify-content: space-between !important; align-items: center !important; width: 100% !important; margin-top: 3px !important;">
									<span style="font-size: 15px !important; font-weight: 700 !important; color: #0f172a !important; letter-spacing: 0.02em !important;"><?php echo $langs->trans("TotalUpper"); ?></span>
									<span style="font-size: 16px !important; font-weight: 700 !important; color: #0f172a !important; text-align: right !important;"><?php echo number_format((float) $single_total, 2, '.', '') . ' €'; ?></span>
								</div>
							</div>
						</div>

						<!-- Sección 4: Notas (visibles en todo momento) -->
						<div class="tallerpro-invoice-info-card" style="background: #ffffff !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; padding: 20px 24px !important; margin-top: 16px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;">
							<div class="tallerpro-info-card-tag" style="font-size: 11px !important; font-weight: 700 !important; color: #64748b !important; letter-spacing: 0.05em !important; text-transform: uppercase !important;"><?php echo $langs->trans("NotasUpper"); ?></div>
							<div class="tallerpro-info-card-line" style="margin-top: 6px !important; font-size: 13.5px !important; color: #0f172a !important; line-height: 1.5 !important;">
								<?php
								$clean_single_notas = tallerpro_clean_notas($factura_single->notas);
								echo !empty($clean_single_notas) ? nl2br(dol_escape_htmltag($clean_single_notas)) : '<span style="color: #94a3b8;">' . dol_escape_htmltag($langs->trans("SinNotas")) . '</span>';
								?>
							</div>
						</div>

						<?php
						$mostrar_iban_view = (getDolGlobalInt('TALLER_MOSTRAR_IBAN', 1) === 1);
						$enc_iban_view = getDolGlobalString('TALLER_IBAN');
						if ($mostrar_iban_view && !empty($enc_iban_view)):
							require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
							$raw_iban_view = dolDecrypt($enc_iban_view);
							if (!empty($raw_iban_view) && strtoupper(trim($raw_iban_view)) !== 'NONE'):
						?>
						<!-- Sección 5: Datos de Pago / Transferencia (controlado por configuración) -->
						<div class="tallerpro-invoice-info-card" style="background: #ffffff !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; padding: 20px 24px !important; margin-top: 16px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;">
							<div class="tallerpro-info-card-tag" style="font-size: 11px !important; font-weight: 700 !important; color: #64748b !important; letter-spacing: 0.05em !important; text-transform: uppercase !important;"><?php echo $langs->trans("PaymentBankTransferTitleUpper"); ?></div>
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
							<p class="tallerpro-page-subtitle"><?php echo $langs->trans("RegistrosCount", $total_facturas); ?></p>
						</div>
						<?php if ($user_can_facturar): ?>
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

					<?php if (!$facture_enabled): ?>
						<div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 18px 22px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
							<div style="display: flex; align-items: flex-start; gap: 14px;">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;">
									<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
									<line x1="12" y1="9" x2="12" y2="13"></line>
									<line x1="12" y1="17" x2="12.01" y2="17"></line>
								</svg>
								<div>
									<h3 style="margin: 0 0 4px 0; font-size: 14.5px; font-weight: 600; color: #92400e;"><?php echo $langs->trans("ModuleFactureDisabledTitle"); ?></h3>
									<p style="margin: 0 0 12px 0; font-size: 13px; color: #b45309; line-height: 1.5;">
										<?php echo $langs->trans("ModuleFactureDisabledBody"); ?>
									</p>
									<a href="<?php echo DOL_URL_ROOT; ?>/admin/modules.php?mainmenu=home" class="tallerpro-btn-primary" style="display: inline-flex; text-decoration: none; padding: 7px 14px; font-size: 12.5px;">
										<?php echo $langs->trans("ModuleFactureDisabledBtn"); ?>
									</a>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<?php if (empty($facturas_list)): ?>
						<!-- Empty State Card -->
						<div class="tallerpro-empty-card">
							<?php echo $langs->trans("InvoicesEmptyState"); ?>
						</div>
					<?php else: ?>
						<!-- Table Card -->
						<div class="tallerpro-table-card">
							<table class="tallerpro-table">
								<thead>
									<tr>
										<th><?php echo $langs->trans("ColNumShort"); ?></th>
										<th><?php echo $langs->trans("Fecha"); ?></th>
										<th><?php echo $langs->trans("Cliente"); ?></th>
										<th><?php echo $langs->trans("Vehiculo"); ?></th>
										<th><?php echo $langs->trans("Total"); ?></th>
										<th><?php echo $langs->trans("Estado"); ?></th>
										<th style="width: 220px; text-align: right;"></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($facturas_list as $f): ?>
										<?php
										$is_presupuesto = ((!empty($f->doc_type) && $f->doc_type === 'propal') || (!empty($f->tipo_doc) && $f->tipo_doc === 'presupuesto'));
										$row_doc_type = $is_presupuesto ? 'propal' : 'factura';
										$row_view_url = $facturas_url . ($is_presupuesto ? '?doc=propal&id=' : '?id=') . ((int) $f->rowid);
										$row_pdf_url = $facturas_url . '?action=pdf' . ($is_presupuesto ? '&doc=propal' : '') . '&id=' . ((int) $f->rowid);
										$f_estado_lower = strtolower(trim($f->estado));
										if ($is_presupuesto) {
											$status_class = 'status-presupuesto';
											$cur_estado = 'Presupuesto';
										} elseif ($f_estado_lower == 'pagada') {
											$status_class = 'status-pagada';
											$cur_estado = 'Pagada';
										} elseif ($f_estado_lower == 'borrador' || (isset($f->fk_statut) && (int)$f->fk_statut == 0)) {
											$status_class = 'status-borrador';
											$cur_estado = 'Borrador';
										} else {
											$status_class = 'status-pendiente';
											$cur_estado = 'Pendiente';
										}
										$is_borrador = (isset($f->fk_statut) && (int)$f->fk_statut == 0);
										$can_edit = $is_borrador && $user_can_facturar;
										$can_delete = $is_borrador && $user_can_delete_fac;
										?>
										<tr data-invoice-id="<?php echo (int) $f->rowid; ?>" data-estado="<?php echo $cur_estado; ?>" data-tipo-doc="<?php echo $is_presupuesto ? 'presupuesto' : 'factura'; ?>" data-doc-type="<?php echo $row_doc_type; ?>">
											<td style="color: #1e293b; font-weight: 700;">
												<a href="<?php echo $row_view_url; ?>" class="factura-ref-link" style="color: #0f172a; text-decoration: none;" title="<?php echo dol_escape_htmltag($is_presupuesto ? $langs->trans("VerPresupuesto") : $langs->trans("VerFactura")); ?>">
													<?php echo dol_escape_htmltag($f->numero_factura); ?>
												</a>
												<?php if ($is_presupuesto): ?>
													<span style="display: block; font-size: 11px; font-weight: 600; color: #0284c7; margin-top: 2px;"><?php echo $langs->trans("PresupuestoSingular"); ?></span>
												<?php endif; ?>
											</td>
											<td style="color: #1e293b;">
												<?php echo !empty($f->fecha) ? dol_escape_htmltag($f->fecha) : '—'; ?>
											</td>
											<td style="color: #1e293b;">
												<?php if (!empty($f->fk_soc) && !empty($f->cliente_nombre)): ?>
													<a href="<?php echo $clientes_url . '?id=' . ((int) $f->fk_soc); ?>" style="color: inherit; text-decoration: none;" title="<?php echo dol_escape_htmltag($langs->trans("VerDetallesCliente")); ?>">
														<?php echo dol_escape_htmltag($f->cliente_nombre); ?>
													</a>
												<?php else: ?>
													<?php echo dol_escape_htmltag($f->cliente_nombre ?: '—'); ?>
												<?php endif; ?>
											</td>
											<td style="color: #1e293b;">
												<?php if (!empty($f->fk_vehiculo) && !empty($f->vehiculo_matricula)): ?>
													<a href="<?php echo $vehiculos_url . '?id=' . ((int) $f->fk_vehiculo); ?>" style="color: inherit; text-decoration: none;" title="<?php echo dol_escape_htmltag($langs->trans("VerVehiculo")); ?>">
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
												<?php if ($is_presupuesto): ?>
													<span class="tallerpro-badge-presupuesto" title="<?php echo dol_escape_htmltag($langs->trans("QuoteEditableBadgeTitle")); ?>"><?php echo $langs->trans("PresupuestoSingular"); ?></span>
												<?php else: ?>
													<select class="tallerpro-status-select <?php echo $status_class; ?> tallerpro-invoice-status-select"
														data-id="<?php echo (int) $f->rowid; ?>"
														data-current-estado="<?php echo $cur_estado; ?>"
														data-ref="<?php echo dol_escape_htmltag($f->numero_factura); ?>"
														data-total="<?php echo number_format((float) $f->total, 2, '.', ''); ?>"
														<?php if (!$user_can_facturar) echo 'disabled'; ?>
														title="<?php echo dol_escape_htmltag($langs->trans("CambiarEstadoFacturaTitle")); ?>">
														<?php if ($is_borrador): ?>
															<option value="Borrador" selected><?php echo $langs->trans("Borrador"); ?></option>
															<option value="Pendiente"><?php echo $langs->trans("Pendiente"); ?></option>
															<option value="Pagada"><?php echo $langs->trans("Pagada"); ?></option>
														<?php elseif ($cur_estado === 'Pendiente'): ?>
															<option value="Pendiente" selected><?php echo $langs->trans("Pendiente"); ?></option>
															<option value="Pagada"><?php echo $langs->trans("Pagada"); ?></option>
														<?php else: ?>
															<option value="Pagada" selected><?php echo $langs->trans("Pagada"); ?></option>
															<option value="Pendiente"><?php echo $langs->trans("Pendiente"); ?></option>
														<?php endif; ?>
													</select>
												<?php endif; ?>
											</td>
											<td>
												<div class="tallerpro-actions">
													<a href="<?php echo $row_view_url; ?>" class="tallerpro-btn-table-primary btn-ver-factura" title="<?php echo dol_escape_htmltag($is_presupuesto ? $langs->trans("VerPresupuesto") : $langs->trans("VerFactura")); ?>">
														<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
															<polyline points="15 3 21 3 21 9"></polyline>
															<line x1="10" y1="14" x2="21" y2="3"></line>
														</svg>
														<span><?php echo $langs->trans("Ver"); ?></span>
													</a>
													<a href="<?php echo $row_pdf_url; ?>" target="_blank" class="tallerpro-btn-table-secondary" title="<?php echo dol_escape_htmltag($langs->trans("DescargarPDF")); ?>">
														<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
															<polyline points="7 10 12 15 17 10"></polyline>
															<line x1="12" y1="15" x2="12" y2="3"></line>
														</svg>
														<span><?php echo $langs->trans("PDF"); ?></span>
													</a>
													<button type="button" class="tallerpro-action-btn btn-edit-factura <?php echo (!$can_edit ? 'is-disabled' : ''); ?>"
														title="<?php echo dol_escape_htmltag($can_edit ? ($is_presupuesto ? $langs->trans("EditarPresupuesto") : $langs->trans("Editar")) : $langs->trans("ErrOnlyDraftInvoiceEditable")); ?>"
														<?php if (!$can_edit) echo 'disabled'; ?>
														data-id="<?php echo (int) $f->rowid; ?>"
														data-numero="<?php echo dol_escape_htmltag($f->numero_factura); ?>"
														data-fecha="<?php echo dol_escape_htmltag($f->fecha); ?>"
														data-soc="<?php echo (int) $f->fk_soc; ?>"
														data-vehiculo="<?php echo (int) $f->fk_vehiculo; ?>"
														data-total="<?php echo number_format((float) $f->total, 2, '.', ''); ?>"
														data-estado="<?php echo dol_escape_htmltag($f->estado); ?>"
														data-tipo-doc="<?php echo $is_presupuesto ? 'presupuesto' : 'factura'; ?>"
														data-doc-type="<?php echo $row_doc_type; ?>"
														data-notas="<?php echo dol_escape_htmltag(tallerpro_clean_notas($f->notas)); ?>">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="<?php echo ($can_edit ? '#64748b' : '#cbd5e1'); ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
														</svg>
													</button>
													<button type="button"
														class="tallerpro-action-btn btn-delete-action <?php echo (!$can_delete ? 'is-disabled' : ''); ?>"
														title="<?php echo dol_escape_htmltag($can_delete ? ($is_presupuesto ? $langs->trans("EliminarPresupuesto") : $langs->trans("Eliminar")) : $langs->trans("ErrOnlyDraftInvoiceDeletable")); ?>"
														<?php if (!$can_delete) echo 'disabled'; ?>
														data-id="<?php echo (int) $f->rowid; ?>"
														data-doc-type="<?php echo $row_doc_type; ?>"
														data-action="delete_factura">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="<?php echo ($can_delete ? '#ef4444' : '#cbd5e1'); ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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

	if (!function_exists('tallerpro_render_payment_fields')) {
		function tallerpro_render_payment_fields($id_prefix, $payment_methods_list, $bank_accounts_list, $bank_enabled) {
			global $langs;
			$id_prefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $id_prefix);
			?>
			<div class="tallerpro-form-group">
				<label class="tallerpro-form-label" for="<?php echo $id_prefix; ?>_datepaye"><?php echo $langs->trans("FechaDeCobro"); ?> <span class="tallerpro-required">*</span></label>
				<input type="date" name="datepaye" id="<?php echo $id_prefix; ?>_datepaye" class="tallerpro-form-input" value="<?php echo date('Y-m-d'); ?>" />
			</div>
			<div class="tallerpro-form-group">
				<label class="tallerpro-form-label" for="<?php echo $id_prefix; ?>_paiementid"><?php echo $langs->trans("ModoDePago"); ?> <span class="tallerpro-required">*</span></label>
				<select name="paiementid" id="<?php echo $id_prefix; ?>_paiementid" class="tallerpro-form-select">
					<option value=""><?php echo $langs->trans("SelectPaymentMethodOption"); ?></option>
					<?php foreach ($payment_methods_list as $pm): ?>
						<option value="<?php echo (int) $pm->id; ?>">
							<?php echo dol_escape_htmltag($pm->label_display); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php if ($bank_enabled): ?>
			<div class="tallerpro-form-group">
				<label class="tallerpro-form-label" for="<?php echo $id_prefix; ?>_accountid"><?php echo $langs->trans("CuentaBancariaCaja"); ?> <span class="tallerpro-required">*</span></label>
				<select name="accountid" id="<?php echo $id_prefix; ?>_accountid" class="tallerpro-form-select">
					<option value=""><?php echo $langs->trans("SelectBankAccountOption"); ?></option>
					<?php foreach ($bank_accounts_list as $ba): ?>
						<option value="<?php echo (int) $ba->rowid; ?>">
							<?php echo dol_escape_htmltag((!empty($ba->ref) ? $ba->ref . ' - ' : '') . $ba->label); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if (empty($bank_accounts_list)): ?>
					<span class="tallerpro-form-help-text" style="color: #dc2626;"><?php echo $langs->trans("NoOpenBankAccountsWarn"); ?></span>
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<div class="tallerpro-form-group">
				<label class="tallerpro-form-label" for="<?php echo $id_prefix; ?>_num_payment"><?php echo $langs->trans("NumOperacionRef"); ?></label>
				<input type="text" name="num_payment" id="<?php echo $id_prefix; ?>_num_payment" class="tallerpro-form-input" placeholder="<?php echo dol_escape_htmltag($langs->trans("PlaceholderPaymentRef")); ?>" maxlength="50" />
			</div>
			<?php
		}
	}
	?>
	<!-- Modal Nuevo Registro Facturas -->
	<div class="tallerpro-modal-backdrop" id="tallerFacturaModal">
		<div class="tallerpro-modal-card">
			<div class="tallerpro-modal-header">
				<h2 class="tallerpro-modal-title" id="tallerFacturaModalTitle"><?php echo $langs->trans("NuevoRegistro"); ?></h2>
				<button type="button" class="tallerpro-modal-close" id="tallerCloseModalBtn" title="<?php echo dol_escape_htmltag($langs->trans("Cerrar")); ?>">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			<form class="tallerpro-modal-form" id="tallerFacturaForm" method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>">
				<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
				<input type="hidden" name="action" id="facturaFormAction" value="create_factura">
				<input type="hidden" name="rowid" id="facturaFormRowid" value="0">
				<input type="hidden" name="doc_type" id="facturaFormDocType" value="factura">

				<!-- Aviso para facturas en estado borrador -->
				<div id="facEditNotice" style="display: none; padding: 10px 14px; margin-bottom: 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #2563eb; border-radius: 8px; font-size: 13px; color: #1e40af; line-height: 1.45;">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;">
						<circle cx="12" cy="12" r="10"></circle>
						<line x1="12" y1="16" x2="12" y2="12"></line>
						<line x1="12" y1="8" x2="12.01" y2="8"></line>
					</svg>
					<strong><?php echo $langs->trans("EditInvoiceNoticeTitle"); ?></strong> <?php echo $langs->trans("EditInvoiceNoticeDesc"); ?>
				</div>

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("NumeroFacturaLabel"); ?> <span class="tallerpro-required">*</span></label>
					<input type="text" name="numero_factura" id="fac_numero" class="tallerpro-form-input tallerpro-input-readonly" value="<?php echo dol_escape_htmltag($next_factura_numero); ?>" data-next-numero="<?php echo dol_escape_htmltag($next_factura_numero); ?>" readonly required />
					<span class="tallerpro-form-help-text">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 3px;">
							<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
							<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
						</svg>
						<?php echo $langs->trans("AutoGeneratedHelp"); ?>
					</span>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("Fecha"); ?></label>
					<div class="tallerpro-input-with-icon">
						<input type="date" name="fecha" id="fac_fecha" class="tallerpro-form-input" value="<?php echo date('Y-m-d'); ?>" />
					</div>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("Cliente"); ?></label>
					<select name="fk_soc" id="fac_fk_soc" class="tallerpro-form-select">
						<option value=""><?php echo $langs->trans("SelectOption"); ?></option>
						<?php foreach ($clients_list as $cl): ?>
							<option value="<?php echo (int) $cl->rowid; ?>">
								<?php echo dol_escape_htmltag($cl->nom); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("Vehiculo"); ?></label>
					<select name="fk_vehiculo" id="fac_fk_vehiculo" class="tallerpro-form-select">
						<option value=""><?php echo $langs->trans("SelectOption"); ?></option>
						<?php foreach ($vehicles_list as $vh): ?>
							<option value="<?php echo (int) $vh->rowid; ?>" data-soc="<?php echo (int) $vh->fk_soc; ?>">
								<?php echo dol_escape_htmltag($vh->matricula . (!empty($vh->marca) ? ' - ' . $vh->marca : '') . (!empty($vh->modelo) ? ' ' . $vh->modelo : '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("TotalEur"); ?></label>
					<input type="number" step="0.01" min="0" name="total" id="fac_total" class="tallerpro-form-input" value="0.00" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("Estado"); ?></label>
					<select name="estado" id="fac_estado" class="tallerpro-form-select">
						<option value="Borrador" selected><?php echo $langs->trans("Borrador"); ?></option>
						<option value="Pendiente"><?php echo $langs->trans("Pendiente"); ?></option>
						<option value="Pagada"><?php echo $langs->trans("Pagada"); ?></option>
					</select>
				</div>
				<div id="facModalPaymentFields" style="display: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; flex-direction: column; gap: 12px;">
					<div style="font-size: 12px; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 0.04em;">
						<?php echo $langs->trans("PaymentRealDataDolibarr"); ?>
					</div>
					<?php tallerpro_render_payment_fields('fac', $payment_methods_list, $bank_accounts_list, $bank_enabled); ?>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label"><?php echo $langs->trans("Notas"); ?></label>
					<textarea name="notas" id="fac_notas" class="tallerpro-form-textarea" rows="3"></textarea>
				</div>
				<div class="tallerpro-modal-footer">
					<button type="submit" class="tallerpro-btn-submit" id="tallerSaveModalBtn"><?php echo $langs->trans("Guardar"); ?></button>
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
			<h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;"><?php echo $langs->trans("ConfirmPendingTitle"); ?></h3>
			<p style="font-size: 13.5px; color: #64748b; margin: 0 0 24px 0; line-height: 1.5;" id="pendienteModalDesc">
				<?php echo $langs->trans("ConfirmPendingBody"); ?>
			</p>

			<div style="display: flex; gap: 12px; justify-content: center;">
				<button type="button" id="btnCancelPendiente" style="flex: 1; padding: 10px 16px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px;">
					<?php echo $langs->trans("Rechazar"); ?>
				</button>
				<button type="button" id="btnConfirmPendiente" style="flex: 1; padding: 10px 16px; border: none; background: #d97706; color: #ffffff; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px; box-shadow: 0 2px 4px rgba(217, 119, 6, 0.25);">
					<?php echo $langs->trans("Confirmar"); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Modal Confirmación para poner Factura en Pagada -->
	<div class="tallerpro-modal-backdrop" id="pagadaConfirmModal" style="z-index: 1000006;">
		<div class="tallerpro-modal-card" style="max-width: 480px; padding: 26px 24px; text-align: center;">
			<div style="width: 52px; height: 52px; border-radius: 50%; background-color: #ecfdf5; color: #059669; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 14px auto;">
				<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
					<polyline points="22 4 12 14.01 9 11.01"></polyline>
				</svg>
			</div>
			<h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;"><?php echo $langs->trans("ConfirmPaidTitle"); ?></h3>
			<p style="font-size: 13.5px; color: #64748b; margin: 0 0 16px 0; line-height: 1.5;" id="pagadaModalDesc">
				<?php echo $langs->trans("ConfirmPaidBody"); ?>
			</p>

			<div id="pagadaModalError" style="display: none; text-align: left; padding: 10px 12px; margin-bottom: 14px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; font-size: 13px; color: #b91c1c;"></div>

			<div style="text-align: left; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
				<?php tallerpro_render_payment_fields('pagada', $payment_methods_list, $bank_accounts_list, $bank_enabled); ?>
			</div>

			<div style="display: flex; gap: 12px; justify-content: center;">
				<button type="button" id="btnCancelPagada" style="flex: 1; padding: 10px 16px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px;">
					<?php echo $langs->trans("Rechazar"); ?>
				</button>
				<button type="button" id="btnConfirmPagada" style="flex: 1; padding: 10px 16px; border: none; background: #059669; color: #ffffff; font-weight: 600; border-radius: 8px; cursor: pointer; font-size: 14px; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.25);">
					<?php echo $langs->trans("ConfirmarCobro"); ?>
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
		<input type="hidden" name="doc_type" id="globalDeleteDocType" value="">
		<input type="hidden" name="socid" id="globalDeleteSocid" value="">
	</form>

</div>

<?php
// End of page
llxFooter();
$db->close();
