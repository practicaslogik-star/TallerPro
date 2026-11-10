<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/taller_stock.lib.php
 * \ingroup taller
 * \brief   Biblioteca de integración de Stock y Productos nativos de Dolibarr para TallerPro
 */

require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';

/**
 * Asegura y devuelve el Almacén principal de TallerPro en Dolibarr
 *
 * @param User|null $user Usuario actual
 * @return int ID del almacén
 */
function tallerpro_get_default_warehouse($user = null)
{
	global $db, $conf;

	if (empty($user)) {
		global $user;
	}

	$table_entrepot = MAIN_DB_PREFIX . "entrepot";

	// 1. Buscar almacén con ref 'ALM-TALLER' o 'TALLER' o activo
	$sql = "SELECT rowid FROM " . $table_entrepot . " WHERE statut = 1 AND entity IN (" . getEntity('stock') . ")";
	$sql .= " AND (ref = 'ALM-TALLER' OR ref LIKE '%TALLER%') LIMIT 1";
	$res = $db->query($sql);
	if ($res && ($obj = $db->fetch_object($res))) {
		return (int) $obj->rowid;
	}

	// 2. Si no hay con nombre taller, buscar cualquier almacén activo
	$sql_any = "SELECT rowid FROM " . $table_entrepot . " WHERE statut = 1 AND entity IN (" . getEntity('stock') . ") ORDER BY rowid ASC LIMIT 1";
	$res_any = $db->query($sql_any);
	if ($res_any && ($obj = $db->fetch_object($res_any))) {
		return (int) $obj->rowid;
	}

	// 3. Si no existe ninguno, crear el Almacén Principal de Taller automáticamente
	$ent = new Entrepot($db);
	$ent->label = 'Almacén Principal Taller';
	$ent->lieu = 'Taller';
	$ent->description = 'Almacén oficial de repuestos y materiales de TallerPro';
	$ent->statut = 1;
	$ent->entity = (int) $conf->entity;

	$ent_id = $ent->create($user);
	if ($ent_id > 0) {
		// Asegurar que ref sea ALM-TALLER
		$db->query("UPDATE " . $table_entrepot . " SET ref = 'ALM-TALLER' WHERE rowid = " . ((int) $ent_id));
		return (int) $ent_id;
	}

	return 0;
}

/**
 * Obtiene o crea un producto nativo de Dolibarr (llx_product) a partir de los datos del material
 *
 * @param string $ref        Referencia del material
 * @param string $nombre     Nombre / descripción del material
 * @param float  $precio     Precio de venta
 * @param float  $coste      Precio de coste (opcional)
 * @param User|null $user    Usuario actual
 * @return int ID de llx_product
 */
function tallerpro_get_or_create_product($ref, $nombre, $precio = 0, $coste = 0, $user = null)
{
	global $db, $conf;

	if (empty($user)) {
		global $user;
	}

	$table_product = MAIN_DB_PREFIX . "product";

	$clean_ref = trim($ref);
	$clean_nom = trim($nombre);

	if (empty($clean_ref)) {
		// Generar referencia automática si no tiene
		$clean_ref = 'MAT-' . strtoupper(substr(md5($clean_nom . microtime(true)), 0, 6));
	}

	// 1. Buscar producto por referencia
	$sql = "SELECT rowid FROM " . $table_product . " WHERE ref = '" . $db->escape($clean_ref) . "' AND entity IN (" . getEntity('product') . ") LIMIT 1";
	$res = $db->query($sql);
	if ($res && ($obj = $db->fetch_object($res))) {
		return (int) $obj->rowid;
	}

	// 2. Buscar por nombre exacto
	$sql_nom = "SELECT rowid FROM " . $table_product . " WHERE label = '" . $db->escape($clean_nom) . "' AND entity IN (" . getEntity('product') . ") LIMIT 1";
	$res_nom = $db->query($sql_nom);
	if ($res_nom && ($obj = $db->fetch_object($res_nom))) {
		return (int) $obj->rowid;
	}

	// 3. Crear nuevo producto físico en Dolibarr
	$prod = new Product($db);
	$prod->ref = $clean_ref;
	$prod->label = $clean_nom;
	$prod->description = $clean_nom;
	$prod->type = Product::TYPE_PRODUCT; // 0 = Producto físico con stock
	$prod->status = 1;                   // En venta
	$prod->status_buy = 1;               // En compra
	$prod->price = (float) $precio;
	$prod->price_base_type = 'HT';
	$prod->cost_price = (float) $coste;
	$prod->tva_tx = 21.0;
	$prod->entity = (int) $conf->entity;

	$pid = $prod->create($user);
	if ($pid > 0) {
		return (int) $pid;
	}

	return 0;
}

/**
 * Obtiene el stock real de un producto en el almacén de taller
 *
 * @param int $product_id ID de llx_product
 * @param int $warehouse_id ID de almacén (opcional, si es 0 busca el default)
 * @return float Stock disponible
 */
function tallerpro_get_product_stock($product_id, $warehouse_id = 0)
{
	global $db, $conf;

	if ($product_id <= 0) return 0.0;

	if ($warehouse_id <= 0) {
		$warehouse_id = tallerpro_get_default_warehouse();
	}

	$table_pstock = MAIN_DB_PREFIX . "product_stock";
	$sql = "SELECT reel FROM " . $table_pstock . " WHERE fk_product = " . ((int) $product_id) . " AND fk_entrepot = " . ((int) $warehouse_id) . " LIMIT 1";
	$res = $db->query($sql);
	if ($res && ($obj = $db->fetch_object($res))) {
		return (float) $obj->reel;
	}

	// Si no hay registro específico de almacén, consultar stock total de llx_product
	$sql_p = "SELECT stock FROM " . MAIN_DB_PREFIX . "product WHERE rowid = " . ((int) $product_id);
	$res_p = $db->query($sql_p);
	if ($res_p && ($p_obj = $db->fetch_object($res_p))) {
		return (float) $p_obj->stock;
	}

	return 0.0;
}

/**
 * Registra un movimiento de stock nativo en Dolibarr
 *
 * @param int    $product_id     ID de llx_product
 * @param float  $qty_diff       Diferencia de cantidad (>0 entrada, <0 salida)
 * @param string $label          Motivo / etiqueta del movimiento
 * @param string $origin_element Elemento de origen ('reparacion', 'ajuste', etc.)
 * @param int    $origin_id      ID del elemento de origen
 * @param User|null $user        Usuario actual
 * @return bool True si tuvo éxito
 */
function tallerpro_record_stock_movement($product_id, $qty_diff, $label = '', $origin_element = '', $origin_id = null, $user = null)
{
	global $db, $conf;

	if (abs((float) $qty_diff) < 0.0001 || $product_id <= 0) {
		return true;
	}

	if (empty($user)) {
		global $user;
	}

	$warehouse_id = tallerpro_get_default_warehouse($user);
	if ($warehouse_id <= 0) {
		return false;
	}

	$prod = new Product($db);
	if ($prod->fetch($product_id) > 0) {
		$movement_type = ($qty_diff > 0) ? 0 : 1;
		$qty_abs = abs((float) $qty_diff);

		$res = $prod->correct_stock(
			$user,
			$warehouse_id,
			$qty_abs,
			$movement_type,
			$label ?: 'Movimiento de taller',
			0,
			'',
			$origin_element,
			$origin_id
		);

		return ($res >= 0);
	}

	return false;
}

/**
 * Sincroniza todos los materiales de TallerPro con Dolibarr llx_product y asegura existencias
 *
 * @param User|null $user Usuario actual
 * @return int Total de materiales sincronizados
 */
function tallerpro_sync_all_materials($user = null)
{
	global $db, $conf;

	if (empty($user)) {
		global $user;
	}

	$table_material = MAIN_DB_PREFIX . "taller_material";
	$warehouse_id = tallerpro_get_default_warehouse($user);

	// 2. Mapear cada material existente a llx_product
	$sql = "SELECT rowid, ref, nombre, precio, stock, fk_product FROM " . $table_material . " WHERE status = 1 AND entity IN (" . getEntity('taller_material') . ")";
	$res = $db->query($sql);

	$count = 0;
	if ($res) {
		while ($m = $db->fetch_object($res)) {
			$pid = (int) $m->fk_product;
			if ($pid <= 0) {
				$pid = tallerpro_get_or_create_product($m->ref, $m->nombre, (float) $m->precio, 0, $user);
				if ($pid > 0) {
					$db->query("UPDATE " . $table_material . " SET fk_product = " . $pid . " WHERE rowid = " . ((int) $m->rowid));
				}
			}

			if ($pid > 0 && $warehouse_id > 0) {
				// Sincronizar stock inicial si en el almacén es 0 y en material es > 0
				$current_doli_stock = tallerpro_get_product_stock($pid, $warehouse_id);
				$mat_stock = (float) $m->stock;

				if ($current_doli_stock == 0 && $mat_stock > 0) {
					tallerpro_record_stock_movement($pid, $mat_stock, 'Sincronización inicial con Almacén Taller', 'taller_material', $m->rowid, $user);
				} elseif ($current_doli_stock > 0 && $mat_stock != $current_doli_stock) {
					// Actualizar tabla local con el stock real del almacén
					$db->query("UPDATE " . $table_material . " SET stock = " . $current_doli_stock . " WHERE rowid = " . ((int) $m->rowid));
				}
			}
			$count++;
		}
	}

	// 3. Si en llx_product hay productos creados por Dolibarr que no están en llx_taller_material, importarlos
	$sql_ext = "SELECT p.rowid, p.ref, p.label, p.price, p.stock FROM " . MAIN_DB_PREFIX . "product p ";
	$sql_ext .= "LEFT JOIN " . $table_material . " tm ON (tm.fk_product = p.rowid AND tm.entity IN (" . getEntity('taller_material') . ")) ";
	$sql_ext .= "WHERE p.tosell = 1 AND p.fk_product_type = 0 AND tm.rowid IS NULL AND p.entity IN (" . getEntity('product') . ")";
	$res_ext = $db->query($sql_ext);
	if ($res_ext) {
		while ($ep = $db->fetch_object($res_ext)) {
			$sql_in = "INSERT INTO " . $table_material . " (nombre, ref, fk_product, precio, stock, date_creation, fk_user_creat, entity, status) VALUES (";
			$sql_in .= "'" . $db->escape($ep->label) . "', ";
			$sql_in .= "'" . $db->escape($ep->ref) . "', ";
			$sql_in .= (int) $ep->rowid . ", ";
			$sql_in .= (float) $ep->price . ", ";
			$sql_in .= (float) $ep->stock . ", ";
			$sql_in .= "'" . $db->idate(dol_now()) . "', ";
			$sql_in .= (int) ($user ? $user->id : 1) . ", ";
			$sql_in .= (int) $conf->entity . ", 1)";
			$db->query($sql_in);
		}
	}

	return $count;
}

/**
 * Descuenta el stock de los materiales consumidos en una orden de reparación
 *
 * @param int $reparacion_id ID de la reparación
 * @param User|null $user Usuario actual
 * @return bool
 */
function tallerpro_consume_repair_materials($reparacion_id, $user = null)
{
	global $db;

	$reparacion_id = (int) $reparacion_id;
	if ($reparacion_id <= 0) return false;

	if (empty($user)) {
		global $user;
	}

	$table_rep = MAIN_DB_PREFIX . "taller_reparacion";
	$table_mat = MAIN_DB_PREFIX . "taller_material";
	$table_veh = MAIN_DB_PREFIX . "taller_vehiculo";

	// Verificar si ya fue descontado
	$sql = "SELECT r.rowid, r.notas, r.stock_descontado, v.matricula ";
	$sql .= "FROM " . $table_rep . " r ";
	$sql .= "LEFT JOIN " . $table_veh . " v ON r.fk_vehiculo = v.rowid ";
	$sql .= "WHERE r.rowid = " . $reparacion_id . " AND r.entity IN (" . getEntity('taller_reparacion') . ")";

	$res = $db->query($sql);
	if (!$res || !($rep = $db->fetch_object($res))) {
		return false;
	}

	if (!empty($rep->stock_descontado)) {
		return true; // Ya descontado anteriormente
	}

	if (empty($rep->notas) || ($rep->notas[0] !== '{' && $rep->notas[0] !== '[')) {
		// No tiene lista estructurada de materiales
		$db->query("UPDATE " . $table_rep . " SET stock_descontado = 1 WHERE rowid = " . $reparacion_id);
		return true;
	}

	$data = json_decode($rep->notas, true);
	if (empty($data['materiales']) || !is_array($data['materiales'])) {
		$db->query("UPDATE " . $table_rep . " SET stock_descontado = 1 WHERE rowid = " . $reparacion_id);
		return true;
	}

	$matricula = !empty($rep->matricula) ? $rep->matricula : 'Vehículo';
	$ot_num = 'OT #' . str_pad($reparacion_id, 4, '0', STR_PAD_LEFT);
	$label_mvt = "Consumo en reparación " . $ot_num . " (" . $matricula . ")";

	foreach ($data['materiales'] as $m) {
		$cant = (float) (!empty($m['cantidad']) ? $m['cantidad'] : 1);
		if ($cant <= 0) continue;

		$mat_code = !empty($m['codigo']) ? trim($m['codigo']) : '';
		$mat_desc = !empty($m['descripcion']) ? trim($m['descripcion']) : '';

		// Buscar material por código o nombre
		$sql_m = "SELECT rowid, fk_product, stock FROM " . $table_mat . " WHERE status = 1 AND entity IN (" . getEntity('taller_material') . ") AND (";
		if (!empty($mat_code)) {
			$sql_m .= "ref = '" . $db->escape($mat_code) . "' OR ";
		}
		$sql_m .= "nombre = '" . $db->escape($mat_desc) . "') LIMIT 1";

		$res_m = $db->query($sql_m);
		if ($res_m && ($mat_obj = $db->fetch_object($res_m))) {
			$pid = (int) $mat_obj->fk_product;
			if ($pid <= 0) {
				$pid = tallerpro_get_or_create_product($mat_code, $mat_desc, (float)($m['precio'] ?? 0), 0, $user);
				$db->query("UPDATE " . $table_mat . " SET fk_product = " . $pid . " WHERE rowid = " . ((int) $mat_obj->rowid));
			}

			if ($pid > 0) {
				tallerpro_record_stock_movement($pid, -$cant, $label_mvt, 'reparacion', $reparacion_id, $user);
				$new_local_stock = tallerpro_get_product_stock($pid);
				$db->query("UPDATE " . $table_mat . " SET stock = " . $new_local_stock . " WHERE rowid = " . ((int) $mat_obj->rowid));
			}
		}
	}

	$db->query("UPDATE " . $table_rep . " SET stock_descontado = 1 WHERE rowid = " . $reparacion_id);
	return true;
}

/**
 * Reintegra el stock de los materiales consumidos si se elimina o cancela la reparación
 *
 * @param int $reparacion_id ID de la reparación
 * @param User|null $user Usuario actual
 * @return bool
 */
function tallerpro_restore_repair_stock($reparacion_id, $user = null)
{
	global $db;

	$reparacion_id = (int) $reparacion_id;
	if ($reparacion_id <= 0) return false;

	if (empty($user)) {
		global $user;
	}

	$table_rep = MAIN_DB_PREFIX . "taller_reparacion";
	$table_mat = MAIN_DB_PREFIX . "taller_material";
	$table_veh = MAIN_DB_PREFIX . "taller_vehiculo";

	$sql = "SELECT r.rowid, r.notas, r.stock_descontado, v.matricula ";
	$sql .= "FROM " . $table_rep . " r ";
	$sql .= "LEFT JOIN " . $table_veh . " v ON r.fk_vehiculo = v.rowid ";
	$sql .= "WHERE r.rowid = " . $reparacion_id . " AND r.entity IN (" . getEntity('taller_reparacion') . ")";

	$res = $db->query($sql);
	if (!$res || !($rep = $db->fetch_object($res))) {
		return false;
	}

	if (empty($rep->stock_descontado)) {
		return true; // No había sido descontado
	}

	if (empty($rep->notas) || ($rep->notas[0] !== '{' && $rep->notas[0] !== '[')) {
		return true;
	}

	$data = json_decode($rep->notas, true);
	if (empty($data['materiales']) || !is_array($data['materiales'])) {
		return true;
	}

	$matricula = !empty($rep->matricula) ? $rep->matricula : 'Vehículo';
	$ot_num = 'OT #' . str_pad($reparacion_id, 4, '0', STR_PAD_LEFT);
	$label_mvt = "Devolución por anulación de reparación " . $ot_num . " (" . $matricula . ")";

	foreach ($data['materiales'] as $m) {
		$cant = (float) (!empty($m['cantidad']) ? $m['cantidad'] : 1);
		if ($cant <= 0) continue;

		$mat_code = !empty($m['codigo']) ? trim($m['codigo']) : '';
		$mat_desc = !empty($m['descripcion']) ? trim($m['descripcion']) : '';

		$sql_m = "SELECT rowid, fk_product FROM " . $table_mat . " WHERE status = 1 AND entity IN (" . getEntity('taller_material') . ") AND (";
		if (!empty($mat_code)) {
			$sql_m .= "ref = '" . $db->escape($mat_code) . "' OR ";
		}
		$sql_m .= "nombre = '" . $db->escape($mat_desc) . "') LIMIT 1";

		$res_m = $db->query($sql_m);
		if ($res_m && ($mat_obj = $db->fetch_object($res_m))) {
			$pid = (int) $mat_obj->fk_product;
			if ($pid > 0) {
				tallerpro_record_stock_movement($pid, $cant, $label_mvt, 'reparacion', $reparacion_id, $user);
				$new_local_stock = tallerpro_get_product_stock($pid);
				$db->query("UPDATE " . $table_mat . " SET stock = " . $new_local_stock . " WHERE rowid = " . ((int) $mat_obj->rowid));
			}
		}
	}

	$db->query("UPDATE " . $table_rep . " SET stock_descontado = 0 WHERE rowid = " . $reparacion_id);
	return true;
}

