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

$action = GETPOST('action', 'alpha');
if (empty($action) && !empty($_POST['action'])) {
	$action = $_POST['action'];
}



// Acción: Crear Reparación
if ($action == 'create_reparacion' || $action == 'add_reparacion') {
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

	$notas = trim(GETPOST('notas', 'restricthtml'));
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
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF'] . "?msg=saved");
			exit;
		} else {
			setEventMessages("Error al guardar la reparación: " . $db->lasterror(), null, 'errors');
		}
	}
}

// Acción: Actualizar Reparación
if ($action == 'update_reparacion' || $action == 'edit_reparacion') {
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

	$notas = trim(GETPOST('notas', 'restricthtml'));
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
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF'] . "?msg=saved");
			exit;
		} else {
			setEventMessages("Error al actualizar la reparación: " . $db->lasterror(), null, 'errors');
		}
	}
}

// Acción: Cambiar Estado directamente desde la tabla
if ($action == 'toggle_estado' || $action == 'update_estado') {
	$rowid = GETPOSTINT('rowid');
	$new_estado = trim(GETPOST('new_estado', 'alpha'));
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
	if (GETPOST('ajax', 'int') || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
		header('Content-Type: application/json');
		echo json_encode(array('success' => true, 'new_estado' => $new_estado));
		exit;
	}
	header("Location: " . $_SERVER['PHP_SELF'] . "?msg=status_updated");
	exit;
}

// Acción: Facturar Reparación (Convierte la orden de reparación en Factura oficial de Dolibarr)
if ($action == 'facturar_reparacion' || $action == 'facturar') {
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
				$fac->note_public = "Factura de reparación " . $ot_num . " (" . $rep->matricula . ")" . (!empty($rep->notas) ? "\n" . $rep->notas : "");
				$fac->array_options['options_fk_vehiculo'] = (int) $rep->fk_vehiculo;

				$fac_id = $fac->create($user);
				if ($fac_id > 0) {
					// Guardar fk_vehiculo en facture_extrafields directamente para garantizar sincronización
					if (!empty($rep->fk_vehiculo)) {
						$db->query("UPDATE " . MAIN_DB_PREFIX . "facture_extrafields SET fk_vehiculo = " . ((int) $rep->fk_vehiculo) . " WHERE fk_object = " . ((int) $fac_id));
					}

					// Añadir línea de mano de obra / trabajo realizado
					$desc_mo = !empty($rep->descripcion) ? $rep->descripcion : ("Mano de obra reparación " . $ot_num);
					$coste_mo = (float) $rep->coste_mo;
					if ($coste_mo <= 0) $coste_mo = 50.0;
					$tva_tx = 21.0;
					$fac->addline($desc_mo, $coste_mo, 1, $tva_tx, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1);

					// Validar factura (asigna numeración oficial definitiva y queda en estado Pendiente)
					$fac->validate($user);

					// Actualizar la reparación con la referencia de la factura y marcarla como finalizada
					$db->query("UPDATE " . $table_reparacion . " SET ref_factura = '" . $db->escape($fac->ref) . "', estado = 'finalizada' WHERE rowid = " . ((int) $rowid));

					setEventMessages("Factura " . $fac->ref . " emitida con éxito a partir de la reparación", null, 'mesgs');
					header("Location: " . dol_buildpath('/taller/facturas.php', 1) . '?id=' . $fac_id);
					exit;
				} else {
					setEventMessages("Error al generar la factura en Dolibarr: " . $fac->error . " " . implode(' ', $fac->errors), null, 'errors');
				}
			}
		} else {
			setEventMessages("No se encontró la reparación indicada", null, 'errors');
		}
	}
}

// Acción: Guardar cambios y Facturar desde el modal
if ($action == 'save_and_facturar') {
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

	$notas = trim(GETPOST('notas', 'restricthtml'));
	if (empty($notas) && !empty($_POST['notas'])) $notas = trim($_POST['notas']);

	if ($fk_vehiculo <= 0) {
		setEventMessages("Debe seleccionar un vehículo para la reparación", null, 'errors');
	} else {
		$target_rep_id = 0;
		if ($rowid > 0) {
			$sql_upd = "UPDATE " . $table_reparacion . " SET fk_vehiculo = " . ((int)$fk_vehiculo) . ", fecha = '" . $db->escape($fecha) . "', descripcion = '" . $db->escape($descripcion) . "', coste_mo = " . ((float)$coste_mo) . ", estado = 'finalizada', notas = '" . $db->escape($notas) . "' WHERE rowid = " . ((int)$rowid);
			$db->query($sql_upd);
			$target_rep_id = $rowid;
		} else {
			$sql_ins = "INSERT INTO " . $table_reparacion . " (fk_vehiculo, fecha, descripcion, coste_mo, notas, estado, date_creation, fk_user_creat, entity, status) VALUES (";
			$sql_ins .= ((int)$fk_vehiculo) . ", '" . $db->escape($fecha) . "', '" . $db->escape($descripcion) . "', " . ((float)$coste_mo) . ", '" . $db->escape($notas) . "', 'finalizada', '" . $db->idate(dol_now()) . "', " . ((int)$user->id) . ", " . ((int)$conf->entity) . ", 1)";
			$db->query($sql_ins);
			$target_rep_id = $db->last_insert_id($table_reparacion);
		}

		header("Location: " . $_SERVER['PHP_SELF'] . "?action=facturar_reparacion&rowid=" . $target_rep_id);
		exit;
	}
}

// Acción: Eliminar Reparación
if ($action == 'delete_reparacion' || $action == 'delete') {
	$rowid = GETPOSTINT('rowid');
	if ($rowid > 0) {
		$sql_del = "UPDATE " . $table_reparacion . " SET status = 0 WHERE rowid = " . ((int) $rowid) . " AND entity IN (" . getEntity('taller_reparacion') . ")";
		$resql = $db->query($sql_del);
	}
	if (GETPOST('ajax', 'int') || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
		header('Content-Type: application/json');
		echo json_encode(array('success' => true));
		exit;
	}
	if ($resql) {
		setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		header("Location: " . $_SERVER['PHP_SELF'] . "?msg=deleted");
		exit;
	} else {
		setEventMessages("Error al eliminar la reparación: " . $db->lasterror(), null, 'errors');
	}
}

// Consultar vehículos para el desplegable
$vehicles_list = array();
$sql_v = "SELECT rowid, matricula, marca, modelo FROM " . $table_vehiculo;
$sql_v .= " WHERE status = 1 AND entity IN (" . getEntity('taller_vehiculo') . ") ORDER BY matricula ASC";
$res_v = $db->query($sql_v);
if ($res_v) {
	while ($v = $db->fetch_object($res_v)) {
		$vehicles_list[] = $v;
	}
}

// Consultar materiales para el desplegable del modal
$materials_list = array();
$sql_m = "SELECT rowid, ref, nombre, precio, stock FROM " . $table_material;
$sql_m .= " WHERE status = 1 AND entity IN (" . getEntity('taller_material') . ") ORDER BY nombre ASC";
$res_m = $db->query($sql_m);
if ($res_m) {
	while ($m = $db->fetch_object($res_m)) {
		$materials_list[] = $m;
	}
}

// Consultar reparaciones con JOIN a vehículos
$reparaciones_list = array();
$sql_r = "SELECT r.rowid, r.fk_vehiculo, r.fecha, r.ref_factura, r.descripcion, r.coste_mo, r.notas, r.estado, ";
$sql_r .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo ";
$sql_r .= "FROM " . $table_reparacion . " as r ";
$sql_r .= "LEFT JOIN " . $table_vehiculo . " as v ON r.fk_vehiculo = v.rowid ";
$sql_r .= "WHERE r.status = 1 AND r.entity IN (" . getEntity('taller_reparacion') . ") ";
$sql_r .= "ORDER BY r.fecha DESC, r.rowid DESC";
$res_r = $db->query($sql_r);
if ($res_r) {
	while ($r = $db->fetch_object($res_r)) {
		$reparaciones_list[] = $r;
	}
}
$total_reparaciones = count($reparaciones_list);

$css_file = __DIR__ . '/css/tallerpro.css';
$js_file = __DIR__ . '/js/tallerpro.js';
$css_version = file_exists($css_file) ? filemtime($css_file) : dol_now();
$js_version = file_exists($js_file) ? filemtime($js_file) : dol_now();

$css_url = dol_buildpath('/taller/css/tallerpro.css', 1) . '?v=' . $css_version;
$js_url = dol_buildpath('/taller/js/tallerpro.js', 1) . '?v=' . $js_version;

$morehead = '<link rel="stylesheet" type="text/css" href="' . $css_url . '">' . "\n";
$morehead .= '<script src="' . $js_url . '"></script>' . "\n";

// Page Header
llxHeader('', 'Reparaciones - TallerPro', '', '', 0, 0, array(), array(), $morehead, 'mod-taller page-tallerpro');

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
				<div class="tallerpro-topbar-breadcrumb">
					<a href="<?php echo $index_url; ?>" class="tallerpro-topbar-breadcrumb-parent">TallerPro</a>
					<span class="tallerpro-topbar-breadcrumb-sep">/</span>
					<span class="tallerpro-topbar-breadcrumb-current"><?php echo $langs->trans("Reparaciones"); ?></span>
				</div>
			</div>
			
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
						<button type="button" class="tallerpro-btn-primary" id="tallerOpenModalBtn" style="padding: 10px 18px; font-size: 14px; font-weight: 600; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px;">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<line x1="12" y1="5" x2="12" y2="19"></line>
								<line x1="5" y1="12" x2="19" y2="12"></line>
							</svg>
							<span>Nueva Reparación</span>
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
									$search_text = strtolower(dol_escape_htmltag(($rep->vehiculo_matricula ?: '') . ' ' . ($rep->vehiculo_marca ?: '') . ' ' . ($rep->descripcion ?: '')));
									$rep_ref = !empty($rep->ref_factura) ? dol_escape_htmltag($rep->ref_factura) : ('OT #' . str_pad($rep->rowid, 4, '0', STR_PAD_LEFT));
									?>
									<tr class="rep-row" data-id="<?php echo (int) $rep->rowid; ?>" data-estado="<?php echo $is_finalizada ? 'finalizada' : 'en_curso'; ?>" data-search="<?php echo $search_text; ?>">
										<td>
											<div style="font-weight: 600; color: #1e293b;"><?php echo dol_escape_htmltag($rep->fecha); ?></div>
											<div style="font-size: 11.5px; color: #94a3b8; margin-top: 2px;">
												<?php echo $rep_ref; ?>
											</div>
										</td>
										<td>
											<div style="font-weight: 700; color: #0f172a;"><?php echo dol_escape_htmltag($rep->vehiculo_matricula ?: '—'); ?></div>
											<div style="font-size: 12px; color: #64748b; margin-top: 2px;">
												<?php echo dol_escape_htmltag(trim(($rep->vehiculo_marca ?: '') . ' ' . ($rep->vehiculo_modelo ?: '')) ?: 'Cliente'); ?>
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
										<td style="font-weight: 600; color: #1e293b;"><?php echo number_format((float) $rep->coste_mo, 2, '.', '') . ' €'; ?></td>
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
													<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=facturar_reparacion&rowid=<?php echo (int) $rep->rowid; ?>&token=<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : (function_exists('newToken') ? newToken() : ''))); ?>"
														class="tallerpro-btn-facturar-action"
														title="Emitir Factura oficial de esta reparación"
														onclick="return confirm('¿Deseas generar la factura oficial de Dolibarr para esta reparación?');">
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

	<!-- Modal Orden de Reparación -->
	<div class="tallerpro-modal-backdrop" id="tallerReparacionModal">
		<div class="tallerpro-modal-card tallerpro-modal-order-card">
			<div class="tallerpro-modal-header" style="margin-bottom: 16px;">
				<div>
					<h2 class="tallerpro-modal-title" id="orderModalTitle">Orden de Reparación</h2>
					<div class="tallerpro-modal-subtitle">Registra el diagnóstico, mano de obra y repuestos utilizados</div>
				</div>
				<button type="button" class="tallerpro-modal-close" id="tallerCloseModalBtn" title="Cerrar">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>

			<form class="tallerpro-modal-form" id="tallerReparacionForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
				<input type="hidden" name="action" id="reparacionFormAction" value="create_reparacion">
				<input type="hidden" name="rowid" id="reparacionFormRowid" value="0">
				<input type="hidden" name="token" id="reparacionFormToken" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : (function_exists('newToken') ? newToken() : ''))); ?>">
				<input type="hidden" name="coste_mo" id="orderCosteMoHidden" value="128.55">

				<!-- Row 1: 3 columns -->
				<div class="tallerpro-order-top-row">
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Vehículo &amp; Cliente <span class="tallerpro-required">*</span></label>
						<select class="tallerpro-form-select" id="orderVehiculoSelect" name="fk_vehiculo" required>
							<?php if (!empty($vehicles_list)): ?>
								<?php foreach ($vehicles_list as $veh): ?>
									<option value="<?php echo (int) $veh->rowid; ?>">
										<?php echo dol_escape_htmltag($veh->matricula . (!empty($veh->marca) ? ' — ' . $veh->marca : '') . (!empty($veh->modelo) ? ' (' . $veh->modelo . ')' : '')); ?>
									</option>
								<?php endforeach; ?>
							<?php else: ?>
								<option value="1" selected>7422NJY — Ivan López (Audi A3)</option>
								<option value="2">7412NJY — John Miller (Ford Focus)</option>
								<option value="3">3569 JPB — Prealuma S.L. (Seat León)</option>
								<option value="4">6721 HPB — Fernández &amp; Outeiro (Renault Megane)</option>
							<?php endif; ?>
						</select>
					</div>
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Fecha de Recepción</label>
						<div class="tallerpro-input-with-icon">
							<input type="date" class="tallerpro-form-input" id="orderFecha" name="fecha" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required />						</div>
					</div>

					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Estado de la Reparación</label>
						<select class="tallerpro-form-select" id="orderEstadoSelect" name="estado">
							<option value="en_curso" selected>En curso</option>
							<option value="finalizada">Finalizada</option>
						</select>
					</div>
				</div>

				<!-- Row 2: Trabajo Solicitado / Avería Reportada -->
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Trabajo Solicitado / Avería Reportada <span class="tallerpro-required">*</span></label>
					<textarea class="tallerpro-form-textarea" maxlength="300" id="orderAveria" name="descripcion" rows="2" style="min-height: 54px;" required></textarea>
				</div>

				<!-- Section: Mano de Obra & Operaciones -->
				<div class="tallerpro-order-section-box">
					<div class="tallerpro-order-section-header">
						<div class="tallerpro-order-section-title">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
							</svg>
							<span>Mano de Obra &amp; Operaciones</span>
						</div>

						<button type="button" class="tallerpro-order-btn-add" id="btnAddTrabajoRow">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
								<line x1="12" y1="5" x2="12" y2="19"></line>
								<line x1="5" y1="12" x2="19" y2="12"></line>
							</svg>
							<span>Añadir Trabajo</span>
						</button>
					</div>
					<table class="tallerpro-order-table">
						<thead>
							<tr>
								<th>DESCRIPCIÓN DEL TRABAJO</th>
								<th style="width: 140px; text-align: right;">COSTE (€)</th>
								<th style="width: 36px; text-align: center;"></th>
							</tr>
						</thead>
						<tbody id="laborTableBody">
							<tr>
								<td>
									<input type="text" class="tallerpro-order-input labor-desc" placeholder="Descripción de la tarea..." />
								</td>
								<td>
									<input type="number" step="0.01" min="0" class="tallerpro-order-input labor-cost" style="text-align: right;" />
								</td>
								<td style="text-align: center;">
									<button type="button" class="tallerpro-order-del-btn" title="Eliminar trabajo">✕</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Section: Materiales & Repuestos Consumidos -->
				<div class="tallerpro-order-section-box">
					<div class="tallerpro-order-section-header">
						<div class="tallerpro-order-section-title">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
								<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
								<line x1="12" y1="22.08" x2="12" y2="12"></line>
							</svg>
							<span>Materiales &amp; Repuestos Consumidos</span>
						</div>
						<button type="button" class="tallerpro-order-btn-add" id="btnAddMaterialRow">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
								<line x1="12" y1="5" x2="12" y2="19"></line>
								<line x1="5" y1="12" x2="19" y2="12"></line>
							</svg>
							<span>Añadir Repuesto</span>
						</button>
					</div>
					<table class="tallerpro-order-table">
						<thead>
							<tr>
								<th>MATERIAL / REFERENCIA DEL CATÁLOGO</th>
								<th style="width: 70px; text-align: center;">CANT.</th>
								<th style="width: 110px; text-align: right;">P. UNIT (€)</th>
								<th style="width: 100px; text-align: right;">TOTAL</th>
								<th style="width: 36px; text-align: center;"></th>
							</tr>
						</thead>

						<tbody id="materialsTableBody">
							<?php
							$first_mat_price = !empty($materials_list) ? number_format((float) $materials_list[0]->precio, 2, '.', '') : '0.00';
							?>
						
							<tr>
								<td>
									<select class="tallerpro-order-select mat-select">
										<?php if (!empty($materials_list)): ?>
											<?php foreach ($materials_list as $mat): ?>
												<?php
												$mat_label = $mat->nombre . (!empty($mat->ref) ? ' (Ref. ' . $mat->ref . ')' : '');
												$mat_price = number_format((float) $mat->precio, 2, '.', '');
												?>
												<option value="<?php echo $mat_price; ?>" data-id="<?php echo (int) $mat->rowid; ?>" data-price="<?php echo $mat_price; ?>">
													<?php echo dol_escape_htmltag($mat_label); ?>
												</option>
											<?php endforeach; ?>
										<?php else: ?>
											<option value="0.00">Sin materiales registrados en catálogo</option>
										<?php endif; ?>
									</select>
								</td>
								<td>
									<input type="number" min="1" step="1" class="tallerpro-order-input mat-qty" value="1" style="text-align: center;" />
								</td>
								<td>
									<input type="number" step="0.01" min="0" class="tallerpro-order-input mat-price" value="<?php echo $first_mat_price; ?>" style="text-align: right;" />
								</td>
								<td style="text-align: right; font-weight: 600; color: #1e293b;" class="mat-row-total">
									<?php echo $first_mat_price; ?> €
								</td>
								<td style="text-align: center;">
									<button type="button" class="tallerpro-order-del-btn" title="Eliminar repuesto">✕</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Section: Totals Summary -->
				<div style="display: flex; justify-content: flex-end; margin-top: 6px;">
					<div class="tallerpro-order-summary-box">
						<div class="tallerpro-order-summary-row">
							<span>Mano de obra:</span>
							<span id="summaryLabor">100.00 €</span>
						</div>
						<div class="tallerpro-order-summary-row">
							<span>Materiales:</span>
							<span id="summaryMaterials">28.55 €</span>
						</div>
						<div class="tallerpro-order-summary-row total">
							<span>Total Estimado:</span>
							<span id="summaryTotal">128.55 €</span>
						</div>
					</div>
				</div>

				<!-- Modal Footer: Dual actions -->
				<div class="tallerpro-order-footer-dual">
					<button type="button" class="tallerpro-btn-emitir-factura" id="btnModalEmitirFactura" title="Emitir Factura oficial de esta reparación">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
							<polyline points="14 2 14 8 20 8"></polyline>
							<line x1="16" y1="13" x2="8" y2="13"></line>
							<line x1="16" y1="17" x2="8" y2="17"></line>
							<polyline points="10 9 9 9 8 9"></polyline>
						</svg>
						<span>Emitir Factura Ahora</span>
					</button>
					<div style="display: flex; align-items: center; gap: 10px;">
						<button type="button" class="tallerpro-btn-cancel-text" id="tallerCancelModalBtn">Cancelar</button>
						<button type="submit" class="tallerpro-btn-submit" id="tallerSaveOrderBtn" style="padding: 9px 20px;">Guardar Reparación</button>
					</div>
				</div>
			</form>
		</div>
	</div>

	<!-- modal eliminacion confirmacion -->

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


</div>

<?php
// End of page
llxFooter();
$db->close();
