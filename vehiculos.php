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

$table_vehiculo = MAIN_DB_PREFIX . "taller_vehiculo";

// Ensure table exists
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

$action = GETPOST('action', 'aZ09');

// Acción: Ver o Descargar Documento de Vehículo
if ($action == 'view_doc' || $action == 'download_doc') {
	$veh_id = GETPOSTINT('id');
	if (empty($veh_id)) {
		$veh_id = GETPOSTINT('rowid');
	}
	$raw_file = GETPOST('file', 'alphanohtml');
	$filename = basename(trim($raw_file));
	$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);

	if ($veh_id > 0 && !empty($filename) && $filename !== 'metadata.json') {
		$dir_veh_docs = DOL_DATA_ROOT . '/taller/vehiculos/' . ((int) $veh_id);
		$filepath = $dir_veh_docs . '/' . $filename;
		if (file_exists($filepath) && is_file($filepath)) {
			$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
			$mimes = array(
				'pdf'  => 'application/pdf',
				'jpg'  => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png'  => 'image/png',
				'webp' => 'image/webp',
				'gif'  => 'image/gif',
				'doc'  => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'odt'  => 'application/vnd.oasis.opendocument.text',
				'txt'  => 'text/plain',
			);
			$mime = isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';

			$download_name = $filename;
			$meta_file = $dir_veh_docs . '/metadata.json';
			if (file_exists($meta_file)) {
				$metadata = json_decode(file_get_contents($meta_file), true);
				if (!empty($metadata[$filename]['original_name'])) {
					$download_name = $metadata[$filename]['original_name'];
				}
			}

			if (ob_get_level()) {
				ob_end_clean();
			}
			header('Content-Description: File Transfer');
			header('Content-Type: ' . $mime);
			header('Content-Disposition: ' . ($action == 'view_doc' ? 'inline' : 'attachment') . '; filename="' . $download_name . '"');
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: ' . filesize($filepath));
			readfile($filepath);
			exit;
		}
	}
	accessforbidden('Documento no encontrado o no disponible');
}

// Acción: Subir Documento de Vehículo
if ($action == 'upload_doc') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		accessforbidden('Method not allowed');
	}
	$token = GETPOST('token', 'alpha');
	$valid_token = (!empty($_SESSION['token']) && $token === $_SESSION['token']) || (!empty($_SESSION['newtoken']) && $token === $_SESSION['newtoken']);
	if (!$valid_token) {
		accessforbidden('Invalid security token');
	}

	$veh_id = GETPOSTINT('id');
	if ($veh_id <= 0) {
		setEventMessages("ID de vehículo no válido", null, 'errors');
		header("Location: " . $_SERVER['PHP_SELF']);
		exit;
	}

	if (empty($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
		setEventMessages("Error al subir el archivo o no se seleccionó ninguno.", null, 'errors');
		header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $veh_id . "&tab=documentos");
		exit;
	}

	$orig_name = basename($_FILES['doc_file']['name']);
	$ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
	$allowed_exts = array('pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'odt', 'txt');
	if (!in_array($ext, $allowed_exts)) {
		setEventMessages("Formato no permitido (.{$ext}). Se aceptan PDF, imágenes (JPG, PNG) y documentos comunes.", null, 'errors');
		header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $veh_id . "&tab=documentos");
		exit;
	}

	if ($_FILES['doc_file']['size'] > 15 * 1024 * 1024) {
		setEventMessages("El archivo supera el límite de 15 MB.", null, 'errors');
		header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $veh_id . "&tab=documentos");
		exit;
	}

	$dir_veh_docs = DOL_DATA_ROOT . '/taller/vehiculos/' . ((int) $veh_id);
	if (!is_dir($dir_veh_docs)) {
		dol_mkdir($dir_veh_docs);
	}

	$safe_base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($orig_name, PATHINFO_FILENAME));
	$safe_filename = time() . '_' . substr($safe_base, 0, 40) . '.' . $ext;
	$dest_path = $dir_veh_docs . '/' . $safe_filename;

	if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $dest_path)) {
		$meta_file = $dir_veh_docs . '/metadata.json';
		$metadata = array();
		if (file_exists($meta_file)) {
			$meta_raw = file_get_contents($meta_file);
			$metadata = json_decode($meta_raw, true) ?: array();
		}

		$category = trim(GETPOST('doc_category', 'alphanohtml'));
		if (empty($category)) {
			$category = 'Documento general';
		}
		$title = trim(GETPOST('doc_title', 'alphanohtml'));
		if (empty($title)) {
			$title = pathinfo($orig_name, PATHINFO_FILENAME);
		}
		$notes = trim(GETPOST('doc_notes', 'restricthtml'));

		$metadata[$safe_filename] = array(
			'original_name' => $orig_name,
			'title'         => $title,
			'category'      => $category,
			'notes'         => $notes,
			'date'          => dol_now(),
			'size'          => filesize($dest_path),
			'user'          => $user->login
		);
		file_put_contents($meta_file, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		setEventMessages("Documento adjuntado correctamente.", null, 'mesgs');
	} else {
		setEventMessages("No se pudo guardar el archivo en el servidor.", null, 'errors');
	}

	header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $veh_id . "&tab=documentos");
	exit;
}

// Acción: Eliminar Documento de Vehículo
if ($action == 'delete_doc') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		accessforbidden('Method not allowed');
	}
	$token = GETPOST('token', 'alpha');
	$valid_token = (!empty($_SESSION['token']) && $token === $_SESSION['token']) || (!empty($_SESSION['newtoken']) && $token === $_SESSION['newtoken']);
	if (!$valid_token) {
		accessforbidden('Invalid security token');
	}

	$veh_id = GETPOSTINT('id');
	if (empty($veh_id)) {
		$veh_id = GETPOSTINT('rowid');
	}
	$raw_file = GETPOST('file', 'alphanohtml');
	$filename = basename(trim($raw_file));
	$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);

	if ($veh_id > 0 && !empty($filename) && $filename !== 'metadata.json') {
		$dir_veh_docs = DOL_DATA_ROOT . '/taller/vehiculos/' . ((int) $veh_id);
		$filepath = $dir_veh_docs . '/' . $filename;
		if (file_exists($filepath) && is_file($filepath)) {
			unlink($filepath);
			$meta_file = $dir_veh_docs . '/metadata.json';
			if (file_exists($meta_file)) {
				$metadata = json_decode(file_get_contents($meta_file), true) ?: array();
				if (isset($metadata[$filename])) {
					unset($metadata[$filename]);
					file_put_contents($meta_file, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
				}
			}
			setEventMessages("Documento eliminado correctamente.", null, 'mesgs');
		} else {
			setEventMessages("El documento no existe.", null, 'errors');
		}
	}
	header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $veh_id . "&tab=documentos");
	exit;
}

// Acción: Crear Vehículo
if ($action == 'create_vehiculo' || $action == 'add_vehiculo') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		accessforbidden('Method not allowed');
	}
	$token = GETPOST('token', 'alpha');
	$valid_token = (!empty($_SESSION['token']) && $token === $_SESSION['token']) || (!empty($_SESSION['newtoken']) && $token === $_SESSION['newtoken']);
	if (!$valid_token) {
		accessforbidden('Invalid security token');
	}

	$matricula = strtoupper(trim(GETPOST('matricula', 'alphanohtml')));
	$fk_soc    = GETPOSTINT('fk_soc');
	$marca     = trim(GETPOST('marca', 'alphanohtml'));
	$modelo    = trim(GETPOST('modelo', 'alphanohtml'));
	$anio      = trim(GETPOST('anio', 'alphanohtml'));
	$color     = trim(GETPOST('color', 'alphanohtml'));
	$vin       = trim(GETPOST('vin', 'alphanohtml'));
	$km        = GETPOSTINT('km');
	$notas     = trim(GETPOST('notas', 'restricthtml'));

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
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF']);
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
	if ($rowid > 0) {
		$sql_del = "UPDATE " . $table_vehiculo . " SET status = 0 WHERE rowid = " . ((int) $rowid) . " AND entity IN (" . getEntity('taller_vehiculo') . ")";
		$resql = $db->query($sql_del);
		if ($resql) {
			setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF']);
			exit;
		} else {
			dol_syslog("TallerPro - Error al eliminar vehículo ID " . $rowid . ": " . $db->lasterror(), LOG_ERR);
			setEventMessages("Error al eliminar el vehículo", null, 'errors');
		}
	}
}

// Consultar clientes reales para el desplegable
$clients_list = array();
$sql_c = "SELECT s.rowid, s.nom FROM " . MAIN_DB_PREFIX . "societe as s WHERE s.client IN (1, 2, 3) AND s.entity IN (" . getEntity('societe') . ") ORDER BY s.nom ASC";
$res_c = $db->query($sql_c);
if ($res_c) {
	while ($c = $db->fetch_object($res_c)) {
		$clients_list[] = $c;
	}
}

// Consultar vehículos con JOIN de cliente
$sql_veh = "SELECT v.rowid, v.fk_soc, v.matricula, v.marca, v.modelo, v.anio, v.color, v.vin, v.km, v.notas, ";
$sql_veh .= "s.nom as cliente_nombre ";
$sql_veh .= "FROM " . $table_vehiculo . " as v ";
$sql_veh .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON v.fk_soc = s.rowid ";
$sql_veh .= "WHERE v.status = 1 AND v.entity IN (" . getEntity('taller_vehiculo') . ") ";
$sql_veh .= "ORDER BY v.rowid DESC";

$res_veh = $db->query($sql_veh);
$num_veh = $res_veh ? $db->num_rows($res_veh) : 0;

$veh_id = GETPOSTINT('id');
if (empty($veh_id)) {
	$veh_id = GETPOSTINT('rowid');
}
$veh_matricula = trim(GETPOST('matricula', 'alphanohtml'));

$veh_detail = null;
$client_detail = null;
$veh_invoices = array();
$veh_repairs = array();
$veh_seguros = array();
$veh_materiales = array();

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

		// Invoices of this vehicle (via extrafield fk_vehiculo OR via repair ref_factura)
		$sql_inv = "SELECT DISTINCT f.rowid, f.ref, f.total_ttc, f.datef, f.paye, f.fk_statut ";
		$sql_inv .= "FROM " . MAIN_DB_PREFIX . "facture as f ";
		$sql_inv .= "LEFT JOIN " . MAIN_DB_PREFIX . "facture_extrafields as fe ON fe.fk_object = f.rowid ";
		$sql_inv .= "LEFT JOIN " . MAIN_DB_PREFIX . "taller_reparacion as tr ON tr.ref_factura = f.ref ";
		$sql_inv .= "WHERE (fe.fk_vehiculo = " . ((int) $veh_detail->rowid) . " OR tr.fk_vehiculo = " . ((int) $veh_detail->rowid);
		if (!empty($veh_detail->fk_soc)) {
			$sql_inv .= " OR (fe.fk_vehiculo IS NULL AND f.fk_soc = " . ((int) $veh_detail->fk_soc) . ")";
		}
		$sql_inv .= ") ORDER BY f.datef DESC, f.rowid DESC";
		$res_inv = $db->query($sql_inv);
		if ($res_inv) {
			while ($inv_obj = $db->fetch_object($res_inv)) {
				$veh_invoices[] = $inv_obj;
			}
		}

		// Materials of this vehicle (from Dolibarr invoice lines and uninvoiced repairs)
		if (!empty($veh_invoices)) {
			$fac_ids = array();
			foreach ($veh_invoices as $vi) {
				$fac_ids[] = (int) $vi->rowid;
			}
			if (!empty($fac_ids)) {
				$sql_lines = "SELECT fd.rowid, fd.fk_facture, fd.description, fd.label, fd.subprice, fd.qty, fd.total_ht, fd.product_type, ";
				$sql_lines .= "f.ref as fac_ref, f.datef, p.ref as prod_ref, p.label as prod_label ";
				$sql_lines .= "FROM " . MAIN_DB_PREFIX . "facturedet as fd ";
				$sql_lines .= "LEFT JOIN " . MAIN_DB_PREFIX . "facture as f ON f.rowid = fd.fk_facture ";
				$sql_lines .= "LEFT JOIN " . MAIN_DB_PREFIX . "product as p ON p.rowid = fd.fk_product ";
				$sql_lines .= "WHERE fd.fk_facture IN (" . implode(',', $fac_ids) . ") ";
				$sql_lines .= "AND (fd.product_type = 0 OR fd.product_type IS NULL) ";
				$sql_lines .= "ORDER BY f.datef DESC, fd.rowid ASC";
				$res_lines = $db->query($sql_lines);
				if ($res_lines) {
					while ($line = $db->fetch_object($res_lines)) {
						$desc = trim(!empty($line->description) ? $line->description : (!empty($line->prod_label) ? $line->prod_label : (!empty($line->label) ? $line->label : '')));
						if (empty($desc) || stripos($desc, 'mano de obra') !== false || stripos($desc, 'servicios de taller') !== false) {
							continue;
						}
						$code = !empty($line->prod_ref) ? trim($line->prod_ref) : '';
						$sub_label = ($code ? ($code . ' · ') : '') . ('Fact. ' . $line->fac_ref);
						$veh_materiales[] = (object) array(
							'nombre' => $desc,
							'sub' => $sub_label,
							'cantidad' => (float) $line->qty,
							'precio' => (float) $line->subprice,
							'total' => (float) $line->total_ht,
							'date' => $db->jdate($line->datef)
						);
					}
				}
			}
		}

		if (!empty($veh_repairs)) {
			foreach ($veh_repairs as $rep) {
				// Only include repair materials if the repair is not yet invoiced
				if (empty($rep->ref_factura) && !empty($rep->notas) && ($rep->notas[0] === '{' || $rep->notas[0] === '[')) {
					$p_rep = json_decode($rep->notas, true);
					if (!empty($p_rep['materiales']) && is_array($p_rep['materiales'])) {
						foreach ($p_rep['materiales'] as $m) {
							$m_code = !empty($m['codigo']) ? trim($m['codigo']) : '';
							$m_ot = 'OT #' . str_pad($rep->rowid, 4, '0', STR_PAD_LEFT);
							$m_sub = ($m_code ? ($m_code . ' · ') : '') . $m_ot;
							$veh_materiales[] = (object) array(
								'nombre' => !empty($m['descripcion']) ? $m['descripcion'] : 'Material',
								'sub' => $m_sub,
								'cantidad' => !empty($m['cantidad']) ? (float)$m['cantidad'] : 1,
								'precio' => !empty($m['precio']) ? (float)$m['precio'] : 0,
								'total' => !empty($m['total_neto']) ? (float)$m['total_neto'] : ((float)$m['precio'] * (float)($m['cantidad'] ?? 1)),
								'date' => $rep->fecha
							);
						}
					}
				}
			}
		}

		// Insurance of this vehicle
		$sql_vs = "SELECT rowid, compania, poliza, tipo, fecha_inicio, fecha_fin, telefono, notas ";
		$sql_vs .= "FROM " . MAIN_DB_PREFIX . "taller_seguro ";
		$sql_vs .= "WHERE fk_vehiculo = " . ((int) $veh_detail->rowid) . " AND (status = 1 OR status IS NULL) ";
		$sql_vs .= "ORDER BY rowid DESC";
		$res_vs = $db->query($sql_vs);
		if ($res_vs) {
			while ($vs_obj = $db->fetch_object($res_vs)) {
				$veh_seguros[] = $vs_obj;
			}
		}

		// Documentos adjuntos de este vehículo
		$veh_documentos = array();
		$dir_veh_docs = DOL_DATA_ROOT . '/taller/vehiculos/' . ((int) $veh_detail->rowid);
		if (is_dir($dir_veh_docs)) {
			$meta_file = $dir_veh_docs . '/metadata.json';
			$metadata = array();
			if (file_exists($meta_file)) {
				$meta_raw = file_get_contents($meta_file);
				$metadata = json_decode($meta_raw, true) ?: array();
			}
			$scanned_files = scandir($dir_veh_docs);
			if ($scanned_files) {
				foreach ($scanned_files as $f) {
					if ($f === '.' || $f === '..' || $f === 'metadata.json' || is_dir($dir_veh_docs . '/' . $f)) {
						continue;
					}
					$f_path = $dir_veh_docs . '/' . $f;
					$meta = isset($metadata[$f]) ? $metadata[$f] : array();
					$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
					$veh_documentos[] = (object) array(
						'filename'      => $f,
						'original_name' => !empty($meta['original_name']) ? $meta['original_name'] : $f,
						'title'         => !empty($meta['title']) ? $meta['title'] : (!empty($meta['original_name']) ? $meta['original_name'] : $f),
						'category'      => !empty($meta['category']) ? $meta['category'] : 'Documento general',
						'notes'         => !empty($meta['notes']) ? $meta['notes'] : '',
						'size'          => filesize($f_path),
						'date'          => !empty($meta['date']) ? $meta['date'] : filemtime($f_path),
						'ext'           => $ext,
						'is_pdf'        => ($ext === 'pdf'),
						'is_img'        => in_array($ext, array('jpg', 'jpeg', 'png', 'webp', 'gif')),
						'user'          => !empty($meta['user']) ? $meta['user'] : ''
					);
				}
				usort($veh_documentos, function($a, $b) {
					return $b->date - $a->date;
				});
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

$morehead = '<link rel="stylesheet" type="text/css" href="'.$css_url.'">'."\n";
$morehead .= '<script src="'.$js_url.'"></script>'."\n";

// Page Header
llxHeader($morehead, 'Vehículos - TallerPro', '', '', 0, 0, array(), array(), '', 'mod-taller page-tallerpro');

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
						<a href="<?php echo $vehiculos_url; ?>" class="tallerpro-nav-item active">
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
					<?php if ($veh_detail): ?>
						<a href="<?php echo $vehiculos_url; ?>" class="tallerpro-topbar-breadcrumb-parent"><?php echo $langs->trans("Vehiculos"); ?></a>
						<span class="tallerpro-topbar-breadcrumb-sep">/</span>
						<span class="tallerpro-topbar-breadcrumb-current"><?php echo dol_escape_htmltag($veh_detail->matricula); ?></span>
					<?php else: ?>
						<span class="tallerpro-topbar-breadcrumb-current"><?php echo $langs->trans("Vehiculos"); ?></span>
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

				<?php if ($veh_detail): ?>
					<?php
					$client_name = '—';
					$client_phone = '—';
					$client_email = '—';
					$client_dni = '—';
					$client_address = '—';

					if (!empty($client_detail)) {
						if (!empty($client_detail->nom)) $client_name = $client_detail->nom;
						if (!empty($client_detail->phone)) $client_phone = $client_detail->phone;
						if (!empty($client_detail->email)) $client_email = $client_detail->email;
						if (!empty($client_detail->siren)) {
							$client_dni = $client_detail->siren;
						} elseif (!empty($client_detail->idprof1)) {
							$client_dni = $client_detail->idprof1;
						}
						$addr_parts = array();
						if (!empty($client_detail->address)) $addr_parts[] = $client_detail->address;
						$zip_town = trim(($client_detail->zip ?? '') . ' ' . ($client_detail->town ?? ''));
						if (!empty($zip_town)) $addr_parts[] = $zip_town;
						if (!empty($addr_parts)) $client_address = implode(' - ', $addr_parts);
					} elseif (!empty($veh_detail->cliente_nombre)) {
						$client_name = $veh_detail->cliente_nombre;
						if (!empty($veh_detail->cliente_telefono)) $client_phone = $veh_detail->cliente_telefono;
						if (!empty($veh_detail->cliente_email)) $client_email = $veh_detail->cliente_email;
						if (!empty($veh_detail->cliente_cif)) {
							$client_dni = $veh_detail->cliente_cif;
						} elseif (!empty($veh_detail->cliente_idprof1)) {
							$client_dni = $veh_detail->cliente_idprof1;
						}
						$addr_parts = array();
						if (!empty($veh_detail->cliente_direccion)) $addr_parts[] = $veh_detail->cliente_direccion;
						$zip_town = trim(($veh_detail->cliente_cp ?? '') . ' ' . ($veh_detail->cliente_ciudad ?? ''));
						if (!empty($zip_town)) $addr_parts[] = $zip_town;
						if (!empty($addr_parts)) $client_address = implode(' - ', $addr_parts);
					}

					$sub_brand_model = implode(' · ', array_filter(array($veh_detail->marca, $veh_detail->modelo)));
					if (empty($sub_brand_model)) {
						$sub_brand_model = '—';
					}
					$veh_color = !empty($veh_detail->color) ? $veh_detail->color : '—';
					$veh_vin   = !empty($veh_detail->vin) ? $veh_detail->vin : '—';
					$veh_km    = (!empty($veh_detail->km) && $veh_detail->km > 0) ? number_format($veh_detail->km, 0, ',', '.') : '—';
					?>

					<div class="tallerpro-detail-container">
						<!-- Volver a vehículos -->
						<a href="<?php echo $vehiculos_url; ?>" class="tallerpro-back-to-list">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<line x1="19" y1="12" x2="5" y2="12"></line>
								<polyline points="12 19 5 12 12 5"></polyline>
							</svg>
							<span>Volver a vehículos</span>
						</a>

						<!-- Tarjeta del Vehículo -->
						<div class="tallerpro-vehicle-card">
							<div class="tallerpro-vehicle-main-info">
								<h1 class="tallerpro-vehicle-plate"><?php echo dol_escape_htmltag($veh_detail->matricula); ?></h1>
								<p class="tallerpro-vehicle-model"><?php echo dol_escape_htmltag($sub_brand_model); ?></p>
							</div>
							<div class="tallerpro-vehicle-meta-group">
								<div class="tallerpro-vehicle-meta-item">
									<span class="tallerpro-vehicle-meta-label">COLOR</span>
									<span class="tallerpro-vehicle-meta-value"><?php echo dol_escape_htmltag($veh_color); ?></span>
								</div>
								<div class="tallerpro-vehicle-meta-item">
									<span class="tallerpro-vehicle-meta-label">VIN</span>
									<span class="tallerpro-vehicle-meta-value"><?php echo dol_escape_htmltag($veh_vin); ?></span>
								</div>
								<div class="tallerpro-vehicle-meta-item">
									<span class="tallerpro-vehicle-meta-label">KM</span>
									<span class="tallerpro-vehicle-meta-value"><?php echo dol_escape_htmltag($veh_km); ?></span>
								</div>
								<div class="tallerpro-vehicle-meta-item">
									<span class="tallerpro-vehicle-meta-label">PROPIETARIO</span>
									<span class="tallerpro-vehicle-meta-value">
										<?php if (!empty($veh_detail->fk_soc)): ?>
											<a href="<?php echo dol_buildpath('/taller/clientes.php', 1) . '?id=' . $veh_detail->fk_soc; ?>" style="color: inherit; text-decoration: none;" title="Ver ficha del cliente">
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
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
								</svg>
								<span>Reparaciones</span>
							</button>
							<button type="button" class="tallerpro-tab-btn" data-tab="tab-materiales">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
									<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
									<line x1="12" y1="22.08" x2="12" y2="12"></line>
								</svg>
								<span>Materiales</span>
							</button>
							<button type="button" class="tallerpro-tab-btn" data-tab="tab-seguros">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<circle cx="12" cy="12" r="9"></circle>
								</svg>
								<span>Seguros</span>
							</button>
							<button type="button" class="tallerpro-tab-btn" data-tab="tab-documentos">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
									<polyline points="14 2 14 8 20 8"></polyline>
									<line x1="16" y1="13" x2="8" y2="13"></line>
									<line x1="16" y1="17" x2="8" y2="17"></line>
								</svg>
								<span>Documentos<?php echo !empty($veh_documentos) ? ' (' . count($veh_documentos) . ')' : ''; ?></span>
							</button>
							<button type="button" class="tallerpro-tab-btn" data-tab="tab-facturas">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<rect x="3" y="3" width="18" height="18" rx="2"></rect>
									<line x1="12" y1="7" x2="12" y2="17"></line>
									<path d="M9.5 9.5a1.5 1.5 0 0 1 1.5-1.5h2a1.5 1.5 0 0 1 1.5 1.5c0 1.5-3 1.5-3 3a1.5 1.5 0 0 0 1.5 1.5h2a1.5 1.5 0 0 0 1.5-1.5"></path>
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
										<span class="tallerpro-vehicle-owner-value"><?php echo dol_escape_htmltag($client_name); ?></span>
									</div>
									<div class="tallerpro-vehicle-owner-field">
										<span class="tallerpro-vehicle-owner-label">TELÉFONO</span>
										<span class="tallerpro-vehicle-owner-value"><?php echo dol_escape_htmltag($client_phone); ?></span>
									</div>
									<div class="tallerpro-vehicle-owner-field">
										<span class="tallerpro-vehicle-owner-label">EMAIL</span>
										<span class="tallerpro-vehicle-owner-value"><?php echo dol_escape_htmltag($client_email); ?></span>
									</div>
									<div class="tallerpro-vehicle-owner-field">
										<span class="tallerpro-vehicle-owner-label">DNI</span>
										<span class="tallerpro-vehicle-owner-value"><?php echo dol_escape_htmltag($client_dni); ?></span>
									</div>
									<div class="tallerpro-vehicle-owner-field">
										<span class="tallerpro-vehicle-owner-label">DIRECCIÓN</span>
										<span class="tallerpro-vehicle-owner-value"><?php echo dol_escape_htmltag($client_address); ?></span>
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
											<?php
											$vr_total = (float) $vr->coste_mo;
											$vr_summary_text = '';
											if (!empty($vr->notas) && ($vr->notas[0] === '{' || $vr->notas[0] === '[')) {
												$p_n = json_decode($vr->notas, true);
												if (!empty($p_n)) {
													if (isset($p_n['total'])) $vr_total = (float) $p_n['total'];
													$n_mat = count($p_n['materiales'] ?? []);
													$n_rep = count($p_n['reparaciones'] ?? []);
													$vr_summary_text = $n_mat . ' material(es) · ' . $n_rep . ' intervención(es)';
												}
											} elseif (!empty($vr->notas)) {
												$vr_summary_text = $vr->notas;
											}
											?>
											<div class="tallerpro-detail-item">
												<div class="tallerpro-detail-item-main">
													<span class="tallerpro-detail-item-title"><?php echo dol_escape_htmltag(!empty($vr->descripcion) ? $vr->descripcion : 'Mantenimiento / Reparación'); ?></span>
													<span class="tallerpro-detail-item-sub">
														Fecha: <?php echo dol_escape_htmltag($vr->fecha); ?>
														<?php if (!empty($vr->ref_factura)) echo ' • <span style="color: #047857; font-weight: 500;">' . dol_escape_htmltag($vr->ref_factura) . '</span>'; ?>
														<?php if (!empty($vr_summary_text)) echo ' • ' . dol_escape_htmltag($vr_summary_text); ?>
													</span>
												</div>
												<div class="tallerpro-detail-item-end">
													<span class="tallerpro-detail-item-price"><?php echo number_format($vr_total, 2, '.', '') . ' €'; ?></span>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else: ?>
									<div class="tallerpro-empty-message">No hay reparaciones registradas para este vehículo.</div>
								<?php endif; ?>
							</div>
						</div>

						<!-- Contenido Pestaña 3: Materiales -->
						<div class="tallerpro-tab-pane" id="tab-materiales">
							<div class="tallerpro-vehicle-detail-card">
								<h2 class="tallerpro-vehicle-detail-card-title">Materiales utilizados</h2>
								<?php if (!empty($veh_materiales)): ?>
									<div class="tallerpro-detail-list">
										<?php foreach ($veh_materiales as $mat): ?>
											<div class="tallerpro-detail-item">
												<div class="tallerpro-detail-item-main">
													<span class="tallerpro-detail-item-title"><?php echo dol_escape_htmltag($mat->nombre); ?></span>
													<span class="tallerpro-detail-item-sub"><?php echo dol_escape_htmltag($mat->sub); ?></span>
												</div>
												<div class="tallerpro-detail-item-end" style="text-align: right;">
													<span style="font-size: 13px; color: #64748b;">x<?php echo (int) $mat->cantidad == $mat->cantidad ? (int) $mat->cantidad : number_format($mat->cantidad, 2, '.', ''); ?></span>
													<span class="tallerpro-detail-item-price"><?php echo number_format($mat->precio, 2, '.', '') . ' €/ud'; ?></span>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else: ?>
									<div class="tallerpro-empty-message">No hay materiales registrados para este vehículo.</div>
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
											<div class="tallerpro-seguro-item" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin-bottom: 12px;">
												<div class="tallerpro-seguro-main">
													<div class="tallerpro-seguro-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
														<div style="display: flex; align-items: center; gap: 10px;">
															<span class="tallerpro-seguro-company" style="font-size: 15px; font-weight: 700; color: #0f172a;"><?php echo dol_escape_htmltag($vs->compania); ?></span>
															<?php if (!empty($vs->tipo)): ?>
																<span class="tallerpro-badge"><?php echo dol_escape_htmltag($vs->tipo); ?></span>
															<?php endif; ?>
														</div>
														<a href="<?php echo $seguros_url; ?>" class="tallerpro-action-btn" title="Gestionar seguros">
															<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
																<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
															</svg>
														</a>
													</div>
													<div class="tallerpro-seguro-details" style="display: flex; flex-wrap: wrap; gap: 16px; font-size: 13px; color: #475569; margin-bottom: 6px;">
														<?php if (!empty($vs->poliza)): ?>
															<span><strong>Póliza:</strong> <?php echo dol_escape_htmltag($vs->poliza); ?></span>
														<?php endif; ?>
														<?php if (!empty($vs->telefono)): ?>
															<span><strong>Tel:</strong> <a href="tel:<?php echo dol_escape_htmltag($vs->telefono); ?>" style="color: #dc2626; font-weight: 500; text-decoration: none;"><?php echo dol_escape_htmltag($vs->telefono); ?></a></span>
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
														<div class="tallerpro-seguro-notes" style="font-size: 13px; color: #64748b; background: #f8fafc; padding: 8px 12px; border-radius: 6px; margin-top: 8px;">
															<?php echo nl2br(dol_escape_htmltag($vs->notas)); ?>
														</div>
													<?php endif; ?>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else: ?>
									<div class="tallerpro-empty-message">No hay seguro asociado registrado para este vehículo.</div>
								<?php endif; ?>
							</div>
						</div>

						<!-- Contenido Pestaña 5: Documentos -->
						<div class="tallerpro-tab-pane" id="tab-documentos">
							<div class="tallerpro-vehicle-detail-card">
								<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
									<div>
										<h2 class="tallerpro-vehicle-detail-card-title" style="margin-bottom: 4px;">Documentos del vehículo</h2>
										<div style="font-size: 13px; color: var(--tp-text-muted);">
											Fichas técnicas, permisos de circulación, informes de ITV, pólizas y fotografías adjuntas.
										</div>
									</div>
									<button type="button" id="btnToggleUploadDoc" class="tallerpro-btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: var(--tp-radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid var(--tp-border); background: var(--tp-bg-card); color: var(--tp-text-dark);">
										<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
											<line x1="12" y1="5" x2="12" y2="19"></line>
											<line x1="5" y1="12" x2="19" y2="12"></line>
										</svg>
										<span>Subir documento</span>
									</button>
								</div>

								<!-- Formulario desplegable para subir documento -->
								<div id="tallerproUploadDocBox" class="tallerpro-upload-doc-box" style="display: none; background: var(--tp-bg); border: 1px dashed var(--tp-border-strong); border-radius: var(--tp-radius); padding: 20px; margin-bottom: 24px;">
									<form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" enctype="multipart/form-data">
										<input type="hidden" name="action" value="upload_doc">
										<input type="hidden" name="id" value="<?php echo (int) $veh_detail->rowid; ?>">
										<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
										
										<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 16px;">
											<div>
												<label class="tallerpro-form-label" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--tp-text-dark);">
													Seleccionar archivo <span class="tallerpro-required">*</span>
												</label>
												<input type="file" name="doc_file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.odt,.txt" class="tallerpro-form-input" style="padding: 6px; width: 100%; box-sizing: border-box; background: var(--tp-bg-card); cursor: pointer;" />
												<div style="font-size: 11.5px; color: var(--tp-text-muted); margin-top: 4px;">
													Formatos aceptados: <strong>PDF, JPG, PNG, WEBP, DOC, DOCX, ODT, TXT</strong> (Máx. 15 MB)
												</div>
											</div>

											<div>
												<label class="tallerpro-form-label" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--tp-text-dark);">
													Tipo / Categoría de documento <span class="tallerpro-required">*</span>
												</label>
												<select name="doc_category" class="tallerpro-form-select" style="width: 100%; box-sizing: border-box; padding: 9px 12px; border-radius: var(--tp-radius-sm); border: 1px solid var(--tp-border); background: var(--tp-bg-card); color: var(--tp-text-dark);">
													<option value="Ficha técnica / Homologación">Ficha técnica / Homologación</option>
													<option value="Permiso de circulación">Permiso de circulación</option>
													<option value="Informe / Tarjeta ITV">Informe / Tarjeta ITV</option>
													<option value="Póliza / Seguro">Póliza / Seguro</option>
													<option value="Fotos de recepción / Daños">Fotos de recepción / Daños</option>
													<option value="Factura / Albarán de recambio">Factura / Albarán de recambio</option>
													<option value="Documento general" selected>Otro documento / General</option>
												</select>
											</div>
										</div>

										<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 16px;">
											<div>
												<label class="tallerpro-form-label" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--tp-text-dark);">
													Título o nombre identificativo (opcional)
												</label>
												<input type="text" name="doc_title" placeholder="Ej: Ficha técnica 2026, Daño aleta derecha..." class="tallerpro-form-input" style="width: 100%; box-sizing: border-box; padding: 9px 12px; border-radius: var(--tp-radius-sm); border: 1px solid var(--tp-border); background: var(--tp-bg-card); color: var(--tp-text-dark);" />
											</div>
											<div>
												<label class="tallerpro-form-label" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--tp-text-dark);">
													Notas u observaciones adicionales (opcional)
												</label>
												<input type="text" name="doc_notes" placeholder="Ej: Vigente hasta 11/2027, entregado por cliente..." class="tallerpro-form-input" style="width: 100%; box-sizing: border-box; padding: 9px 12px; border-radius: var(--tp-radius-sm); border: 1px solid var(--tp-border); background: var(--tp-bg-card); color: var(--tp-text-dark);" />
											</div>
										</div>

										<div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px;">
											<button type="button" id="btnCancelUploadDoc" style="padding: 8px 16px; border-radius: var(--tp-radius-sm); border: 1px solid var(--tp-border); background: transparent; color: var(--tp-text-muted); cursor: pointer; font-size: 13px;">
												Cancelar
											</button>
											<button type="submit" class="tallerpro-btn-submit" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: var(--tp-radius-sm); background: #0f172a; color: #ffffff; border: none; font-size: 13px; font-weight: 600; cursor: pointer;">
												<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
													<polyline points="17 8 12 3 7 8"></polyline>
													<line x1="12" y1="3" x2="12" y2="15"></line>
												</svg>
												<span>Subir y adjuntar archivo</span>
											</button>
										</div>
									</form>
								</div>

								<!-- Listado de documentos -->
								<?php if (!empty($veh_documentos)): ?>
									<div class="tallerpro-doc-list" style="display: flex; flex-direction: column; gap: 10px;">
										<?php foreach ($veh_documentos as $doc): ?>
											<div class="tallerpro-doc-item" style="display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; background: var(--tp-bg); border: 1px solid var(--tp-border); border-radius: 10px; flex-wrap: wrap; gap: 14px;">
												<div style="display: flex; align-items: center; gap: 14px; min-width: 260px; flex: 1;">
													<div style="width: 42px; height: 42px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 11px; flex-shrink: 0; <?php echo $doc->is_pdf ? 'background: #fee2e2; color: #dc2626;' : ($doc->is_img ? 'background: #e0f2fe; color: #0284c7;' : 'background: #f1f5f9; color: #475569;'); ?>">
														<?php if ($doc->is_pdf): ?>
															PDF
														<?php elseif ($doc->is_img): ?>
															IMG
														<?php else: ?>
															DOC
														<?php endif; ?>
													</div>
													<div style="display: flex; flex-direction: column; gap: 4px;">
														<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
															<span style="font-weight: 600; font-size: 14px; color: var(--tp-text-dark);">
																<?php echo dol_escape_htmltag($doc->title); ?>
															</span>
															<span style="font-size: 11px; padding: 2px 8px; border-radius: 12px; background: #f1f5f9; color: #475569; font-weight: 500; border: 1px solid #e2e8f0;">
																<?php echo dol_escape_htmltag($doc->category); ?>
															</span>
														</div>
														<div style="font-size: 12px; color: var(--tp-text-muted); display: flex; gap: 12px; flex-wrap: wrap;">
															<span>Archivo: <?php echo dol_escape_htmltag($doc->original_name); ?></span>
															<span>·</span>
															<span><?php echo round($doc->size / 1024, 1); ?> KB</span>
															<span>·</span>
															<span><?php echo date('d/m/Y H:i', $doc->date); ?></span>
														</div>
														<?php if (!empty($doc->notes)): ?>
															<div style="font-size: 12px; color: #475569; background: var(--tp-bg-card); padding: 4px 8px; border-radius: 4px; border: 1px solid var(--tp-border); margin-top: 2px;">
																<?php echo dol_escape_htmltag($doc->notes); ?>
															</div>
														<?php endif; ?>
													</div>
												</div>

												<div style="display: flex; align-items: center; gap: 8px;">
													<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=view_doc&id=<?php echo (int) $veh_detail->rowid; ?>&file=<?php echo urlencode($doc->filename); ?>" target="_blank" class="tallerpro-btn-action" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12.5px; font-weight: 500; color: #0284c7; background: #e0f2fe; border-radius: 6px; text-decoration: none;" title="Abrir / Ver en pestaña nueva">
														<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
															<circle cx="12" cy="12" r="3"></circle>
														</svg>
														<span>Ver</span>
													</a>
													<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=download_doc&id=<?php echo (int) $veh_detail->rowid; ?>&file=<?php echo urlencode($doc->filename); ?>" class="tallerpro-btn-action" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12.5px; font-weight: 500; color: #334155; background: #f1f5f9; border-radius: 6px; text-decoration: none;" title="Descargar archivo">
														<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
															<polyline points="7 10 12 15 17 10"></polyline>
															<line x1="12" y1="15" x2="12" y2="3"></line>
														</svg>
														<span>Descargar</span>
													</a>
													<form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" style="display: inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este documento? Esta acción no se puede deshacer.');">
														<input type="hidden" name="action" value="delete_doc">
														<input type="hidden" name="id" value="<?php echo (int) $veh_detail->rowid; ?>">
														<input type="hidden" name="file" value="<?php echo dol_escape_htmltag($doc->filename); ?>">
														<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
														<button type="submit" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: none; background: transparent; color: #ef4444; border-radius: 6px; cursor: pointer;" title="Eliminar documento">
															<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
																<polyline points="3 6 5 6 21 6"></polyline>
																<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
															</svg>
														</button>
													</form>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else: ?>
									<div class="tallerpro-empty-message">No hay documentos adjuntos para este vehículo. Pulsa en <strong>Subir documento</strong> para adjuntar fichas técnicas, permisos, fotos o informes de ITV.</div>
								<?php endif; ?>
							</div>
						</div>

						<!-- Contenido Pestaña 6: Facturas -->
						<div class="tallerpro-tab-pane" id="tab-facturas">
							<div class="tallerpro-vehicle-detail-card">
								<h2 class="tallerpro-vehicle-detail-card-title">Facturas del vehículo</h2>
								<?php if (!empty($veh_invoices)): ?>
									<div class="tallerpro-detail-list">
										<?php foreach ($veh_invoices as $f): ?>
											<?php
											$f_date = !empty($f->datef) ? date('Y-m-d', $db->jdate($f->datef)) : '';
											$f_total = number_format((float) $f->total_ttc, 2, '.', '') . ' €';
											?>
											<div class="tallerpro-detail-item">
												<div class="tallerpro-detail-item-main">
													<span class="tallerpro-detail-item-title">Factura <?php echo dol_escape_htmltag($f->ref); ?></span>
													<span class="tallerpro-detail-item-sub"><?php echo dol_escape_htmltag($f_date . ' · ' . $f_total); ?></span>
												</div>
												<div class="tallerpro-detail-item-end">
													<a href="<?php echo $facturas_url . '?id=' . ((int) $f->rowid); ?>" class="tallerpro-btn-ver-factura" title="Ver factura">
														<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
															<polyline points="14 2 14 8 20 8"></polyline>
															<line x1="16" y1="13" x2="8" y2="13"></line>
															<line x1="16" y1="17" x2="8" y2="17"></line>
															<line x1="10" y1="9" x2="8" y2="9"></line>
														</svg>
														<span>Ver factura</span>
													</a>
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
										$veh_url    = dol_buildpath('/taller/vehiculos.php', 1) . '?id=' . $veh->rowid;
										?>
										<tr>
											<td style="font-weight: 700; color: #0f172a; letter-spacing: 0.02em;">
												<a href="<?php echo $veh_url; ?>" style="color: inherit; text-decoration: none;" title="Ver vehículo">
													<?php echo dol_escape_htmltag($veh->matricula); ?>
												</a>
											</td>
											<td style="color: #1e293b;"><?php echo !empty($veh->marca) ? dol_escape_htmltag($veh->marca) : '<span style="color: #94a3b8;">—</span>'; ?></td>
											<td style="color: #1e293b;"><?php echo !empty($veh->modelo) ? dol_escape_htmltag($veh->modelo) : '<span style="color: #94a3b8;">—</span>'; ?></td>
											<td>
												<?php if (!empty($veh->cliente_nombre)): ?>
													<a href="<?php echo $client_url; ?>" style="color: #0f172a; font-weight: 600; text-decoration: none;" title="Ver detalles del cliente">
														<?php echo dol_escape_htmltag($veh->cliente_nombre); ?>
													</a>
												<?php else: ?>
													<span style="color: #94a3b8;">Sin asignar</span>
												<?php endif; ?>
											</td>
											<td>
												<div class="tallerpro-actions">
													<a href="<?php echo $veh_url; ?>" class="tallerpro-action-btn" title="Ver vehículo">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
															<polyline points="15 3 21 3 21 9"></polyline>
															<line x1="10" y1="14" x2="21" y2="3"></line>
														</svg>
													</a>
													<button type="button"
														class="tallerpro-action-btn btn-delete-action"
														title="Eliminar vehículo"
														data-id="<?php echo (int) $veh->rowid; ?>"
														data-action="delete_vehiculo">
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
											Aún no hay vehículos registrados. Pulsa en <strong>Añadir</strong> para registrar el primero.
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

	<!-- Modal Nuevo Registro Vehículos -->
	<div class="tallerpro-modal-backdrop" id="tallerVehiculoModal">
		<div class="tallerpro-modal-card" style="max-width: 540px;">
			<div class="tallerpro-modal-header">
				<h2 class="tallerpro-modal-title">Nuevo registro de vehículo</h2>
				<button type="button" class="tallerpro-modal-close" id="tallerCloseModalBtn" title="Cerrar">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			<form class="tallerpro-modal-form" id="tallerVehiculoForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
				<input type="hidden" name="token" value="<?php echo (!empty($_SESSION['newtoken']) ? $_SESSION['newtoken'] : (!empty($_SESSION['token']) ? $_SESSION['token'] : newToken())); ?>">
				<input type="hidden" name="action" value="create_vehiculo">

				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Matrícula <span class="tallerpro-required">*</span></label>
					<input type="text" name="matricula" id="veh_matricula" class="tallerpro-form-input" placeholder="Ej: 1234ABC" required autocomplete="off" style="text-transform: uppercase;" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Propietario / Cliente <span class="tallerpro-required">*</span></label>
					<select name="fk_soc" id="veh_fk_soc" class="tallerpro-form-select" required>
						<option value="">— Selecciona un cliente —</option>
						<?php foreach ($clients_list as $c): ?>
							<option value="<?php echo $c->rowid; ?>"><?php echo dol_escape_htmltag($c->nom); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Marca</label>
						<input type="text" name="marca" id="veh_marca" class="tallerpro-form-input" placeholder="Ej: BMW, Renault..." />
					</div>
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Modelo</label>
						<input type="text" name="modelo" id="veh_modelo" class="tallerpro-form-input" placeholder="Ej: 318 D, Mégane..." />
					</div>
				</div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Año</label>
						<input type="text" name="anio" id="veh_anio" class="tallerpro-form-input" placeholder="Ej: 2018" />
					</div>
					<div class="tallerpro-form-group">
						<label class="tallerpro-form-label">Color</label>
						<input type="text" name="color" id="veh_color" class="tallerpro-form-input" placeholder="Ej: Blanco, Negro..." />
					</div>
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Nº bastidor (VIN)</label>
					<input type="text" name="vin" id="veh_vin" class="tallerpro-form-input" placeholder="Ej: WBA..." />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Kilometraje</label>
					<input type="number" name="km" id="veh_km" class="tallerpro-form-input" placeholder="Ej: 120000" />
				</div>
				<div class="tallerpro-form-group">
					<label class="tallerpro-form-label">Notas</label>
					<textarea name="notas" id="veh_notas" class="tallerpro-form-textarea" rows="3" placeholder="Observaciones o notas sobre el vehículo..."></textarea>
				</div>
				<div class="tallerpro-modal-footer">
					<button type="button" class="tallerpro-btn-cancel" id="tallerCancelModalBtn">Cancelar</button>
					<button type="submit" class="tallerpro-btn-save" id="btnGuardarVehiculo">Guardar</button>
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
			<p style="font-size: 13.5px; color: #64748b; margin: 0 0 24px 0; line-height: 1.5;">Este vehículo dejará de mostrarse en la lista activa, pero sus datos se conservarán en el sistema.</p>

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
