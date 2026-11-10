<?php
/* Copyright (C) 2026		SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    taller/lib/taller.lib.php
 * \ingroup taller
 * \brief   Library files with common functions for Taller
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function tallerAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("taller@taller");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/taller/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/taller/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'taller@taller');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'taller@taller', 'remove');

	return $head;
}

/**
 * Comprueba que el módulo esté activo, que el usuario sea interno (no externo)
 * y que disponga del permiso requerido del módulo Taller.
 *
 * @param string $perm      Permiso requerido ('read', 'write', 'delete', 'facturar', 'facturar_or_read', 'admin')
 * @param bool   $is_ajax   Si es true devuelve respuesta JSON en caso de denegación
 * @return void
 */
function tallerpro_require_permission($perm = 'read', $is_ajax = false)
{
	global $user;

	if (!isModEnabled('taller')) {
		if ($is_ajax) {
			header('Content-Type: application/json');
			http_response_code(403);
			echo json_encode(array('success' => false, 'status' => 'error', 'error' => 'Module not enabled', 'message' => 'Module not enabled'));
			exit;
		}
		accessforbidden('Module not enabled');
	}

	// Bloquear siempre acceso a usuarios externos (portal/terceros)
	if (!empty($user->socid)) {
		if ($is_ajax) {
			header('Content-Type: application/json');
			http_response_code(403);
			echo json_encode(array('success' => false, 'status' => 'error', 'error' => 'External users are not allowed', 'message' => 'External users are not allowed'));
			exit;
		}
		accessforbidden('External users are not allowed');
	}

	$allowed = false;
	if ($perm === 'admin') {
		$allowed = !empty($user->admin);
	} elseif ($perm === 'facturar_or_read') {
		$allowed = $user->hasRight('taller', 'read') || $user->hasRight('taller', 'facturar');
	} else {
		$allowed = (bool) $user->hasRight('taller', $perm);
	}

	if (!$allowed) {
		if ($is_ajax) {
			header('Content-Type: application/json');
			http_response_code(403);
			echo json_encode(array('success' => false, 'status' => 'error', 'error' => 'NotEnoughPermissions', 'message' => 'Permisos insuficientes para realizar esta acción'));
			exit;
		}
		accessforbidden();
	}
}

/**
 * Valida que la petición mutadora utilice método POST y un token CSRF válido de Dolibarr.
 *
 * @param bool $is_ajax Si es true devuelve respuesta JSON en caso de error
 * @return void
 */
function tallerpro_require_post_csrf($is_ajax = false)
{
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		if ($is_ajax) {
			header('Content-Type: application/json');
			http_response_code(405);
			echo json_encode(array('success' => false, 'status' => 'error', 'error' => 'Method not allowed', 'message' => 'Método HTTP no permitido (se requiere POST)'));
			exit;
		}
		accessforbidden('Method not allowed');
	}

	$token = GETPOST('token', 'alpha');
	$valid_token = !empty($token) && (
		(!empty($_SESSION['token']) && hash_equals((string) $_SESSION['token'], (string) $token))
		|| (!empty($_SESSION['newtoken']) && hash_equals((string) $_SESSION['newtoken'], (string) $token))
	);

	if (!$valid_token) {
		if ($is_ajax) {
			header('Content-Type: application/json');
			http_response_code(403);
			echo json_encode(array('success' => false, 'status' => 'error', 'error' => 'Invalid security token', 'message' => 'Token de seguridad inválido o expirado'));
			exit;
		}
		accessforbidden('Invalid security token');
	}
}

/**
 * Comprueba si un entity ID pertenece a las entidades permitidas para el elemento dado.
 *
 * @param int|string $obj_entity Entidad del registro
 * @param string     $element    Nombre del elemento en getEntity()
 * @return bool
 */
function tallerpro_is_entity_allowed($obj_entity, $element)
{
	$allowed_raw = explode(',', (string) getEntity($element));
	$allowed_ids = array();
	foreach ($allowed_raw as $e_id) {
		$e_int = (int) trim($e_id);
		if ($e_int > 0) {
			$allowed_ids[] = $e_int;
		}
	}
	return in_array((int) $obj_entity, $allowed_ids, true);
}

/**
 * Comprueba que un registro exista en base de datos dentro de la entidad activa.
 *
 * @param DoliDB $db         Manejador de base de datos
 * @param string $table      Nombre de tabla sin prefijo (ej. 'societe', 'taller_vehiculo')
 * @param int    $rowid      Identificador del registro
 * @param string $element    Nombre del elemento en getEntity()
 * @param string $extra_sql  Condición SQL adicional estática (ej. 'status = 1')
 * @return bool
 */
function tallerpro_record_in_entity($db, $table, $rowid, $element, $extra_sql = '')
{
	$rowid = (int) $rowid;
	if ($rowid <= 0 || empty($table) || empty($element)) {
		return false;
	}
	$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . $table . " WHERE rowid = " . $rowid . " AND entity IN (" . getEntity($element) . ")";
	if (!empty($extra_sql)) {
		$sql .= " AND (" . $extra_sql . ")";
	}
	$sql .= " LIMIT 1";
	$res = $db->query($sql);
	return ($res && $db->num_rows($res) > 0);
}

/**
 * Devuelve el mapa de extensiones permitidas con sus tipos MIME reales aceptados.
 *
 * @return array<string, string[]>
 */
function tallerpro_get_allowed_mimes_map()
{
	return array(
		'pdf'  => array('application/pdf', 'application/x-pdf'),
		'jpg'  => array('image/jpeg'),
		'jpeg' => array('image/jpeg'),
		'png'  => array('image/png'),
		'webp' => array('image/webp'),
		'doc'  => array('application/msword', 'application/cdfv2', 'application/x-ole-storage'),
		'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'),
		'odt'  => array('application/vnd.oasis.opendocument.text', 'application/zip'),
		'txt'  => array('text/plain', 'inode/x-empty', 'application/x-empty'),
	);
}

/**
 * Detecta el MIME real del contenido binario de un fichero usando finfo / mime_content_type.
 *
 * @param string $filepath Ruta del archivo en disco
 * @return string MIME normalizado en minúsculas o cadena vacía
 */
function tallerpro_detect_real_mime($filepath)
{
	if (empty($filepath) || !file_exists($filepath) || !is_readable($filepath)) {
		return '';
	}

	$mime = '';
	if (function_exists('finfo_open')) {
		$finfo = @finfo_open(FILEINFO_MIME_TYPE);
		if ($finfo) {
			$detected = @finfo_file($finfo, $filepath);
			if (is_string($detected)) {
				$parts = explode(';', $detected);
				$mime = strtolower(trim($parts[0]));
			}
			@finfo_close($finfo);
		}
	}

	if (empty($mime) && function_exists('mime_content_type')) {
		$detected = @mime_content_type($filepath);
		if (is_string($detected)) {
			$parts = explode(';', $detected);
			$mime = strtolower(trim($parts[0]));
		}
	}

	return $mime;
}

/**
 * Valida la extensión y el contenido binario (MIME real y cabecera de imagen) de un archivo subido.
 *
 * @param string $tmp_path Ruta temporal del fichero subido
 * @param string $ext      Extensión del fichero en minúsculas
 * @return array{valid:bool,mime:string,error:string}
 */
function tallerpro_validate_uploaded_file($tmp_path, $ext)
{
	$ext = strtolower(trim((string) $ext));
	$allowed_map = tallerpro_get_allowed_mimes_map();

	if (!isset($allowed_map[$ext])) {
		return array(
			'valid' => false,
			'mime' => '',
			'error' => "Formato no permitido (.{$ext}). Se aceptan PDF, imágenes (JPG, PNG, WEBP) y documentos comunes."
		);
	}

	if (empty($tmp_path) || !is_file($tmp_path)) {
		return array(
			'valid' => false,
			'mime' => '',
			'error' => "El archivo temporal recibido no es válido."
		);
	}

	$real_mime = tallerpro_detect_real_mime($tmp_path);
	if (empty($real_mime) || !in_array($real_mime, $allowed_map[$ext], true)) {
		$mime_lbl = !empty($real_mime) ? $real_mime : 'desconocido';
		return array(
			'valid' => false,
			'mime' => $real_mime,
			'error' => "El contenido real del archivo ({$mime_lbl}) no coincide con la extensión .{$ext}."
		);
	}

	// Verificación adicional de cabecera para imágenes
	if (in_array($ext, array('jpg', 'jpeg', 'png', 'webp'), true)) {
		$img_info = @getimagesize($tmp_path);
		$expected_types = array(
			'jpg'  => IMAGETYPE_JPEG,
			'jpeg' => IMAGETYPE_JPEG,
			'png'  => IMAGETYPE_PNG,
			'webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18,
		);
		if (!is_array($img_info) || empty($img_info[2]) || (int) $img_info[2] !== (int) $expected_types[$ext]) {
			return array(
				'valid' => false,
				'mime' => $real_mime,
				'error' => "La imagen cargada está dañada o no corresponde al formato .{$ext}."
			);
		}
	}

	return array(
		'valid' => true,
		'mime' => $real_mime,
		'error' => ''
	);
}

/**
 * Limpia metadatos técnicos JSON o prefijos heredados del campo de notas de documentos.
 *
 * @param string $notas_str Texto bruto de notas
 * @return string
 */
function tallerpro_clean_notas($notas_str)
{
	$notas_raw = trim((string) $notas_str);
	if (empty($notas_raw)) {
		return '';
	}
	if (strpos($notas_raw, '{') !== false && strpos($notas_raw, '}') !== false) {
		if ($notas_raw[0] === '{') {
			$dec = json_decode($notas_raw, true);
			return !empty($dec['notas_usuario']) ? trim($dec['notas_usuario']) : (!empty($dec['notas']) && is_string($dec['notas']) ? trim($dec['notas']) : '');
		}
		$clean = preg_replace('/\{.*?\}$/s', '', $notas_raw);
		if (preg_match('/^Factura de reparaci[oó]n\s+OT\s*#\d+/i', trim($clean))) {
			$clean = preg_replace('/^Factura de reparaci[oó]n\s+OT\s*#\d+[^\n]*\n?/i', '', trim($clean));
		}
		return trim($clean);
	} elseif (preg_match('/^Factura de reparaci[oó]n\s+OT\s*#\d+/i', $notas_raw)) {
		$clean = preg_replace('/^Factura de reparaci[oó]n\s+OT\s*#\d+[^\n]*\n?/i', '', $notas_raw);
		return trim($clean);
	}
	return $notas_raw;
}

/**
 * Crea o actualiza de forma idempotente un extrafield del módulo si falta o difiere su configuración.
 *
 * @param ExtraFields         $extrafields Instancia de ExtraFields
 * @param string              $attrname    Código del campo extra
 * @param string              $label       Etiqueta visible
 * @param string              $type        Tipo de campo Dolibarr ('link', 'int', 'varchar', etc.)
 * @param int                 $pos         Posición
 * @param string              $size        Tamaño/longitud
 * @param string              $elementtype Objeto destino ('facture' o 'propal')
 * @param array|string        $param       Parámetros (con claves asociativas válidas para Dolibarr)
 * @return void
 */
function tallerpro_sync_extrafield($extrafields, $attrname, $label, $type, $pos, $size, $elementtype, $param = '')
{
	$extrafields->fetch_name_optionals_label($elementtype);

	if (empty($extrafields->attributes[$elementtype]['label'][$attrname])) {
		$extrafields->addExtraField($attrname, $label, $type, $pos, $size, $elementtype, 0, 0, '', $param, 1, '', -1, 0, '', '', 'taller@taller', 'isModEnabled("taller")');
		return;
	}

	$cur_type = $extrafields->attributes[$elementtype]['type'][$attrname] ?? '';
	$cur_param = $extrafields->attributes[$elementtype]['param'][$attrname] ?? array();
	$expected_keys = (is_array($param) && !empty($param['options']) && is_array($param['options'])) ? array_keys($param['options']) : array();
	$cur_keys = (is_array($cur_param) && !empty($cur_param['options']) && is_array($cur_param['options'])) ? array_keys($cur_param['options']) : array();

	if ($cur_type !== $type || ($expected_keys !== $cur_keys)) {
		$extrafields->updateExtraField($attrname, $label, $type, $pos, $size, $elementtype, 0, 0, '', $param, 1, '', -1, 0, '', '', 'taller@taller', 'isModEnabled("taller")');
	}
}

/**
 * Registra o repara de forma idempotente los extrafields del módulo Taller en facturas y presupuestos (Propal).
 * Debe invocarse desde modTaller::init().
 *
 * @param DoliDB $db Manejador de base de datos
 * @return void
 */
function tallerpro_ensure_module_extrafields($db)
{
	include_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
	$extrafields = new ExtraFields($db);
	$vehiculo_link_param = array('options' => array('TallerVehiculo:/taller/class/taller_vehiculo.class.php:0:(t.status:=:1)' => null));

	// Facturas (facture)
	tallerpro_sync_extrafield($extrafields, 'fk_vehiculo', "Vehículo Taller", 'link', 1, '', 'facture', $vehiculo_link_param);
	tallerpro_sync_extrafield($extrafields, 'fk_reparacion', "Reparación Taller", 'int', 2, '10', 'facture', '');
	tallerpro_sync_extrafield($extrafields, 'tipo_doc', "Tipo de Documento", 'varchar', 3, '32', 'facture', '');

	// Presupuestos (propal)
	tallerpro_sync_extrafield($extrafields, 'fk_vehiculo', "Vehículo Taller", 'link', 1, '', 'propal', $vehiculo_link_param);
	tallerpro_sync_extrafield($extrafields, 'fk_reparacion', "Reparación Taller", 'int', 2, '10', 'propal', '');
}

/**
 * Registra o repara de forma idempotente los extrafields del módulo Taller en presupuestos nativos (Propal).
 *
 * @param DoliDB $db Manejador de base de datos
 * @return void
 */
function tallerpro_ensure_propal_extrafields($db)
{
	tallerpro_ensure_module_extrafields($db);
}

/**
 * Migra de forma idempotente los antiguos presupuestos guardados como Facture borrador
 * (extrafields.tipo_doc = 'presupuesto') hacia el objeto nativo Propal de Dolibarr.
 *
 * @param DoliDB    $db    Manejador de base de datos
 * @param User|null $user  Usuario que ejecuta la operación (fallback a admin si no disponible)
 * @param bool      $force Si es true fuerza la comprobación aunque TALLER_PROPAL_MIGRATED esté en 1
 * @return int Número de presupuestos migrados o -1 en caso de error
 */
function tallerpro_migrate_legacy_presupuestos_to_propal($db, $user = null, $force = false)
{
	global $conf;

	if (!$force && getDolGlobalInt('TALLER_PROPAL_MIGRATED') === 1) {
		return 0;
	}

	tallerpro_ensure_propal_extrafields($db);

	require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
	require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
	require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
	require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

	$mig_user = $user;
	if (!($mig_user instanceof User) || $mig_user->id <= 0) {
		$mig_user = new User($db);
		if ($mig_user->fetch(1) <= 0) {
			return -1;
		}
	}

	$sql = "SELECT f.rowid, f.ref, f.entity ";
	$sql .= "FROM " . MAIN_DB_PREFIX . "facture as f ";
	$sql .= "INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields as fe ON fe.fk_object = f.rowid ";
	$sql .= "WHERE fe.tipo_doc = 'presupuesto' AND f.fk_statut = 0 AND f.entity IN (" . getEntity('facture') . ") ";
	$sql .= "ORDER BY f.rowid ASC";

	$res = $db->query($sql);
	if (!$res) {
		dol_syslog("TallerPro - Error consultando presupuestos legacy para migrar a Propal: " . $db->lasterror(), LOG_ERR);
		return -1;
	}

	$migrated_count = 0;
	$had_error = false;

	while ($obj = $db->fetch_object($res)) {
		$fac = new Facture($db);
		if ($fac->fetch((int) $obj->rowid) <= 0) {
			continue;
		}
		$fac->fetch_lines();
		$fac->fetch_optionals();

		$old_ref = trim((string) $fac->ref);
		$fk_vehiculo = !empty($fac->array_options['options_fk_vehiculo']) ? (int) $fac->array_options['options_fk_vehiculo'] : 0;
		$fk_reparacion = !empty($fac->array_options['options_fk_reparacion']) ? (int) $fac->array_options['options_fk_reparacion'] : 0;

		// Evitar duplicados si ya se migró anteriormente este registro
		$sql_dup = "SELECT p.rowid FROM " . MAIN_DB_PREFIX . "propal as p ";
		$sql_dup .= "LEFT JOIN " . MAIN_DB_PREFIX . "propal_extrafields as pe ON pe.fk_object = p.rowid ";
		$sql_dup .= "WHERE p.entity = " . ((int) $fac->entity) . " AND (p.ref_ext = 'LEGACY_FAC_" . ((int) $fac->id) . "'";
		if (!empty($old_ref)) {
			$sql_dup .= " OR p.ref = '" . $db->escape($old_ref) . "'";
		}
		if ($fk_reparacion > 0) {
			$sql_dup .= " OR pe.fk_reparacion = " . $fk_reparacion;
		}
		$sql_dup .= ") LIMIT 1";

		$res_dup = $db->query($sql_dup);
		$existing_propal_id = ($res_dup && ($dup_obj = $db->fetch_object($res_dup))) ? (int) $dup_obj->rowid : 0;

		$db->begin();
		$ok = true;

		if ($existing_propal_id <= 0) {
			$propal = new Propal($db);
			$propal->socid = (int) $fac->socid;
			$propal->date = !empty($fac->date) ? $fac->date : dol_now();
			$propal->datep = $propal->date;
			$propal->duree_validite = 30;
			$propal->note_public = (string) $fac->note_public;
			$propal->note_private = (string) $fac->note_private;
			$propal->ref_ext = 'LEGACY_FAC_' . (int) $fac->id;
			$propal->entity = (int) $fac->entity;
			$propal->array_options['options_fk_vehiculo'] = ($fk_vehiculo > 0 ? $fk_vehiculo : null);
			$propal->array_options['options_fk_reparacion'] = ($fk_reparacion > 0 ? $fk_reparacion : null);

			$propal_id = $propal->create($mig_user);
			if ($propal_id <= 0) {
				$ok = false;
				dol_syslog("TallerPro - Error creando Propal desde factura legacy ID " . $fac->id . ": " . $propal->error, LOG_ERR);
			} else {
				$propal->insertExtraFields();
				$target_ref = !empty($old_ref) ? $old_ref : ('PRE' . date('ym', (int) $propal->date) . '-' . str_pad($propal_id, 4, '0', STR_PAD_LEFT));
				if (Propal::isExistingObject('propal', 0, $target_ref) > 0) {
					$target_ref = 'PRE' . date('ym', (int) $propal->date) . '-' . str_pad($propal_id, 4, '0', STR_PAD_LEFT);
				}
				if ($propal->setValueFrom('ref', $target_ref, '', '', 'text', '', $mig_user) < 0) {
					$ok = false;
				}

				if ($ok && !empty($fac->lines)) {
					foreach ($fac->lines as $l) {
						$l_fk_prod = (int) $l->fk_product;
						if ($l_fk_prod > 0) {
							$res_pchk = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "product WHERE rowid = " . $l_fk_prod . " LIMIT 1");
							if (!$res_pchk || $db->num_rows($res_pchk) <= 0) {
								$l_fk_prod = 0;
							}
						}
						$l_desc = !empty($l->desc) ? $l->desc : (!empty($l->description) ? $l->description : (!empty($l->label) ? $l->label : 'Concepto'));
						$l_label = !empty($l->ref_ext) ? $l->ref_ext : (!empty($l->label) ? $l->label : '');
						$res_l = $propal->addline(
							$l_desc,
							(float) $l->subprice,
							(float) $l->qty,
							(float) $l->tva_tx,
							(float) $l->localtax1_tx,
							(float) $l->localtax2_tx,
							$l_fk_prod,
							(float) $l->remise_percent,
							'HT',
							0,
							(int) $l->info_bits,
							(int) $l->product_type,
							(int) $l->rang,
							(int) $l->special_code,
							0,
							0,
							(float) $l->pa_ht,
							$l_label
						);
						if ($res_l < 0) {
							$ok = false;
							dol_syslog("TallerPro - Error copiando línea a Propal ID " . $propal_id . ": " . $propal->error, LOG_ERR);
							break;
						}
					}
				}
			}
		}

		if ($ok) {
			// Restaurar referencia provisional (PROV...) si el borrador legacy tenía prefijo PRE...
			// para que CommonInvoice::is_erasable() autorice el borrado estándar sin devolver -2.
			if ((int) $fac->statut === Facture::STATUS_DRAFT && substr((string) $fac->ref, 1, 4) !== 'PROV') {
				$prov_ref = '(PROV' . ((int) $fac->id) . ')';
				if ($fac->setValueFrom('ref', $prov_ref, '', '', 'text', '', $mig_user) < 0) {
					$ok = false;
					dol_syslog("TallerPro - Error restaurando referencia provisional en factura legacy ID " . $fac->id . ": " . $fac->error, LOG_ERR);
				} else {
					$fac->ref = $prov_ref;
				}
			}
		}

		if ($ok) {
			$res_del = $fac->delete($mig_user, 1);
			if ($res_del <= 0) {
				$ok = false;
				dol_syslog("TallerPro - Error eliminando factura borrador legacy ID " . $fac->id . " tras migrar a Propal: " . $fac->error, LOG_ERR);
			}
		}

		if ($ok) {
			$db->commit();
			$migrated_count++;
		} else {
			$db->rollback();
			$had_error = true;
		}
	}

	if (!$had_error) {
		dolibarr_set_const($db, 'TALLER_PROPAL_MIGRATED', 1, 'chaine', 0, '', $conf->entity);
	}

	return $had_error ? -1 : $migrated_count;
}

/**
 * Devuelve el diccionario de traducciones consumido por js/tallerpro.js.
 *
 * @param Translate $langs Objeto de traducciones activo
 * @return array<string,string>
 */
function tallerpro_get_js_i18n($langs)
{
	$keys = array(
		'CloseMenu',
		'Eliminar',
		'Editar',
		'Ver',
		'PDF',
		'NuevoRegistro',
		'EditarRegistro',
		'NuevoRegistroCliente',
		'EditarCliente',
		'SelectOption',
		'ErrTotalPositiveForPayment',
		'ErrSelectPaymentMethod',
		'ErrSelectBankAccount',
		'EditarPresupuesto',
		'EditarFactura',
		'EditQuoteNoticeTitle',
		'EditQuoteNoticeDesc',
		'EditInvoiceNoticeTitle',
		'EditInvoiceNoticeDesc',
		'ToastRepairSaved',
		'ToastRepairDeleted',
		'ToastRepairStatusUpdated',
		'OrdenDeReparacion',
		'NuevaOrdenReparacion',
		'EditarOrdenReparacion',
		'ErrNoDeleteRepairPerm',
		'ConfirmInvoiceRepair',
		'OrdersRegisteredSuffix',
		'StateUpdatedTo',
		'Finalizada',
		'EnCurso',
		'ErrNoUpdateStatusPerm',
		'ErrPaidToDraftForbidden',
		'ErrInvoiceTotalPositive',
		'ValidateDraftDescPrefix',
		'ValidateDraftDescSuffix',
		'MarkPendingDescPrefix',
		'MarkPendingDescSuffix',
		'MarkPaidDraftDescPrefix',
		'MarkPaidDraftDescMid',
		'MarkPaidDescPrefix',
		'MarkPaidDescMid',
		'MarkPaidDescSuffix',
		'ErrSpecifyPaymentDate',
		'OnlyDraftCanEdit',
		'OnlyDraftCanDelete',
		'InvoiceStatusUpdatedTo',
		'ErrUpdateInvoiceStatus',
		'ErrConnectionUpdateStatus',
		'StockOutWarehouse',
		'StockRequestedExceeds',
		'StockLowInWarehouse',
		'StockAvailableInWarehouse',
		'ColCodigo',
		'ColDescripcion',
		'ColCantShort',
		'ColPrecioUnit',
		'ColDtoShort',
		'ColTotal',
		'DeleteMaterialTitle',
		'ColDescIntervencion',
		'ColPrecio',
		'DeleteLaborTitle',
		'IVA',
		'ErrIndicateMaterialDesc',
		'ConfirmOutOfStockMaterial',
		'ConfirmOverStockMaterial',
		'ErrIndicateLaborDesc',
		'CrearAlbaran',
		'VerAlbaran',
		'ViewLinkedQuoteTitle',
		'CreateLinkedQuoteTitle',
		'InvoicedPrefix',
		'DefaultLaborDesc',
		'ErrSelectVehicleForRepair',
		'ErrIndicateFaultOrWork',
		'SearchNoMatches',
		'Vehiculos',
		'Clientes',
		'Materiales',
		'Facturas',
		'IbanStatusEnabled',
		'IbanStatusDisabled',
		'EditLabelPrefix',
		'TypeOtro',
		'EmitirFacturaTitle',
		'Facturar',
		'Borrador',
		'Pendiente',
		'Pagada',
	);

	$dict = array();
	foreach ($keys as $k) {
		$dict[$k] = $langs->transnoentitiesnoconv($k);
	}
	return $dict;
}

/**
 * Genera el bloque HTML <head> estándar de las vistas TallerPro (CSS + JS + diccionario i18n).
 *
 * @param Translate $langs        Objeto de traducciones activo
 * @param bool      $include_i18n Si es true inserta window.TALLERPRO_I18N antes del JS
 * @return string
 */
function tallerpro_build_morehead($langs, $include_i18n = true)
{
	$root_dir = dirname(__DIR__);
	$css_file = $root_dir . '/css/tallerpro.css';
	$js_file = $root_dir . '/js/tallerpro.js';

	$css_version = file_exists($css_file) ? filemtime($css_file) : dol_now();
	$js_version = file_exists($js_file) ? filemtime($js_file) : dol_now();

	$css_url = dol_buildpath('/taller/css/tallerpro.css', 1) . '?v=' . $css_version;
	$js_url = dol_buildpath('/taller/js/tallerpro.js', 1) . '?v=' . $js_version;

	$morehead = '<link rel="stylesheet" type="text/css" href="' . $css_url . '">' . "\n";
	if ($include_i18n && $langs) {
		$i18n_json = json_encode(tallerpro_get_js_i18n($langs), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$morehead .= '<script>window.TALLERPRO_I18N = ' . $i18n_json . ';</script>' . "\n";
	}
	$morehead .= '<script src="' . $js_url . '"></script>' . "\n";

	return $morehead;
}

