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

	// global $db;
	// $extrafields = new ExtraFields($db);
	// $extrafields->fetch_name_optionals_label('myobject');

	$langs->load("taller@taller");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/taller/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	/*
	$head[$h][0] = dol_buildpath("/taller/admin/myobject_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$nbExtrafields = (isset($extrafields->attributes['myobject']['label']) && is_countable($extrafields->attributes['myobject']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafields';
	$h++;

	$head[$h][0] = dol_buildpath("/taller/admin/myobjectline_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFieldsLines");
	$nbExtrafields = (isset($extrafields->attributes['myobjectline']['label']) && is_countable($extrafields->attributes['myobjectline']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafieldsline';
	$h++;
	*/

	$head[$h][0] = dol_buildpath("/taller/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@taller:/taller/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@taller:/taller/mypage.php?id=__ID__'
	//); // to remove a tab
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
