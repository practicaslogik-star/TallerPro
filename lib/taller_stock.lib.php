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
require_once dirname(__DIR__) . '/class/taller_material.class.php';
require_once dirname(__DIR__) . '/class/taller_reparacion.class.php';

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
	$ent->label = 'ALM-TALLER';
	$ent->lieu = 'Almacén Principal Taller';
	$ent->description = 'Almacén oficial de repuestos y materiales de TallerPro';
	$ent->statut = 1;
	$ent->entity = (int) $conf->entity;

	$ent_id = $ent->create($user);
	if ($ent_id > 0) {
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

	$prod = new Product($db);
	if ($prod->fetch((int) $product_id) > 0) {
		$fallback_stock = (float) $prod->stock_reel;
		$prod->load_stock('nobatch,novirtual');
		if ($warehouse_id > 0 && isset($prod->stock_warehouse[$warehouse_id])) {
			return (float) $prod->stock_warehouse[$warehouse_id]->real;
		}
		return ($prod->stock_reel > 0 || $fallback_stock == 0) ? (float) $prod->stock_reel : $fallback_stock;
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
 * Busca un material activo de taller por código o nombre dentro de la entidad actual.
 *
 * @param DoliDB $db       Manejador de base de datos
 * @param string $mat_code Referencia / código del material
 * @param string $mat_desc Nombre / descripción del material
 * @return object|null
 */
function tallerpro_find_active_material_by_ref_or_name($db, $mat_code, $mat_desc)
{
	$table_mat = MAIN_DB_PREFIX . "taller_material";
	$sql_m = "SELECT rowid, fk_product, stock FROM " . $table_mat . " WHERE status = 1 AND entity IN (" . getEntity('taller_material') . ") AND (";
	if (!empty($mat_code)) {
		$sql_m .= "ref = '" . $db->escape($mat_code) . "' OR ";
	}
	$sql_m .= "nombre = '" . $db->escape($mat_desc) . "') LIMIT 1";

	$res_m = $db->query($sql_m);
	return ($res_m && ($mat_obj = $db->fetch_object($res_m))) ? $mat_obj : null;
}

/**
 * Sincroniza todos los materiales de TallerPro con Dolibarr llx_product y asegura existencias
 *
 * @param User|null $user Usuario actual
 * @return int Total de materiales sincronizados (-1 si falla)
 */
function tallerpro_sync_all_materials($user = null)
{
	global $db, $conf;

	if (empty($user)) {
		global $user;
	}

	$table_material = MAIN_DB_PREFIX . "taller_material";
	$warehouse_id = tallerpro_get_default_warehouse($user);

	$db->begin();
	$ok = true;

	// 2. Mapear cada material existente a llx_product
	$sql = "SELECT rowid, ref, nombre, precio, stock, fk_product FROM " . $table_material . " WHERE status = 1 AND entity IN (" . getEntity('taller_material') . ")";
	$res = $db->query($sql);

	$count = 0;
	if (!$res) {
		$ok = false;
	} else {
		while ($m = $db->fetch_object($res)) {
			$pid = (int) $m->fk_product;
			$mat_dao = new TallerMaterial($db);
			$loaded = ($mat_dao->fetch((int) $m->rowid) > 0);
			$needs_update = false;

			if ($pid <= 0) {
				$pid = tallerpro_get_or_create_product($m->ref, $m->nombre, (float) $m->precio, 0, $user);
				if ($pid > 0 && $loaded) {
					$mat_dao->fk_product = $pid;
					$needs_update = true;
				}
			}

			if ($pid > 0 && $warehouse_id > 0) {
				// Sincronizar stock inicial si en el almacén es 0 y en material es > 0
				$current_doli_stock = tallerpro_get_product_stock($pid, $warehouse_id);
				$mat_stock = (float) $m->stock;

				if ($current_doli_stock == 0 && $mat_stock > 0) {
					if (!tallerpro_record_stock_movement($pid, $mat_stock, 'Sincronización inicial con Almacén Taller', 'taller_material', $m->rowid, $user)) {
						$ok = false;
						break;
					}
				} elseif ($current_doli_stock > 0 && $mat_stock != $current_doli_stock && $loaded) {
					// Actualizar tabla local con el stock real del almacén
					$mat_dao->stock = $current_doli_stock;
					$needs_update = true;
				}
			}

			if ($needs_update && $loaded && $mat_dao->update($user) <= 0) {
				$ok = false;
				break;
			}
			$count++;
		}
	}

	// 3. Si en llx_product hay productos creados por Dolibarr que no están en llx_taller_material, importarlos
	if ($ok) {
		$sql_ext = "SELECT p.rowid, p.ref, p.label, p.price, p.stock FROM " . MAIN_DB_PREFIX . "product p ";
		$sql_ext .= "LEFT JOIN " . $table_material . " tm ON (tm.fk_product = p.rowid AND tm.entity IN (" . getEntity('taller_material') . ")) ";
		$sql_ext .= "WHERE p.tosell = 1 AND p.fk_product_type = 0 AND tm.rowid IS NULL AND p.entity IN (" . getEntity('product') . ")";
		$res_ext = $db->query($sql_ext);
		if (!$res_ext) {
			$ok = false;
		} else {
			while ($ep = $db->fetch_object($res_ext)) {
				$new_mat = new TallerMaterial($db);
				$new_mat->nombre     = (string) $ep->label;
				$new_mat->ref        = (string) $ep->ref;
				$new_mat->fk_product = (int) $ep->rowid;
				$new_mat->precio     = (float) $ep->price;
				$new_mat->stock      = (float) $ep->stock;
				$new_mat->entity     = (int) $conf->entity;
				if ($new_mat->create($user) <= 0) {
					$ok = false;
					break;
				}
			}
		}
	}

	if ($ok) {
		$db->commit();
		return $count;
	}

	$db->rollback();
	dol_syslog("TallerPro - Error sincronizando materiales en tallerpro_sync_all_materials: " . $db->lasterror(), LOG_ERR);
	return -1;
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
	$table_veh = MAIN_DB_PREFIX . "taller_vehiculo";

	$db->begin();

	$rep_dao = new TallerReparacion($db);
	if ($rep_dao->fetch($reparacion_id) <= 0) {
		$db->rollback();
		return false;
	}

	if (!empty($rep_dao->stock_descontado)) {
		$db->commit();
		return true; // Ya descontado anteriormente
	}

	$notas_str = (string) $rep_dao->notas;
	if (empty($notas_str) || ($notas_str[0] !== '{' && $notas_str[0] !== '[')) {
		$rep_dao->stock_descontado = 1;
		$ok_flag = ($rep_dao->update($user) > 0);
		if ($ok_flag) {
			$db->commit();
		} else {
			$db->rollback();
		}
		return $ok_flag;
	}

	$data = json_decode($notas_str, true);
	if (empty($data['materiales']) || !is_array($data['materiales'])) {
		$rep_dao->stock_descontado = 1;
		$ok_flag = ($rep_dao->update($user) > 0);
		if ($ok_flag) {
			$db->commit();
		} else {
			$db->rollback();
		}
		return $ok_flag;
	}

	$matricula = 'Vehículo';
	$sql_v = "SELECT v.matricula FROM " . $table_veh . " v WHERE v.rowid = " . ((int) $rep_dao->fk_vehiculo) . " AND v.entity IN (" . getEntity('taller_vehiculo') . ") LIMIT 1";
	$res_v = $db->query($sql_v);
	if ($res_v && ($v_obj = $db->fetch_object($res_v)) && !empty($v_obj->matricula)) {
		$matricula = $v_obj->matricula;
	}

	$ot_num = 'OT #' . str_pad($reparacion_id, 4, '0', STR_PAD_LEFT);
	$label_mvt = "Consumo en reparación " . $ot_num . " (" . $matricula . ")";
	$ok = true;

	foreach ($data['materiales'] as $m) {
		$cant = (float) (!empty($m['cantidad']) ? $m['cantidad'] : 1);
		if ($cant <= 0) continue;

		$mat_code = !empty($m['codigo']) ? trim($m['codigo']) : '';
		$mat_desc = !empty($m['descripcion']) ? trim($m['descripcion']) : '';

		$mat_obj = tallerpro_find_active_material_by_ref_or_name($db, $mat_code, $mat_desc);
		if ($mat_obj) {
			$mat_dao = new TallerMaterial($db);
			if ($mat_dao->fetch((int) $mat_obj->rowid) <= 0) {
				$ok = false;
				break;
			}

			$pid = (int) $mat_dao->fk_product;
			$needs_upd = false;
			if ($pid <= 0) {
				$pid = tallerpro_get_or_create_product($mat_code, $mat_desc, (float) ($m['precio'] ?? 0), 0, $user);
				if ($pid <= 0) {
					$ok = false;
					break;
				}
				$mat_dao->fk_product = $pid;
				$needs_upd = true;
			}

			if ($pid > 0) {
				if (!tallerpro_record_stock_movement($pid, -$cant, $label_mvt, 'reparacion', $reparacion_id, $user)) {
					$ok = false;
					break;
				}
				$mat_dao->stock = tallerpro_get_product_stock($pid);
				$needs_upd = true;
			}

			if ($needs_upd && $mat_dao->update($user) <= 0) {
				$ok = false;
				break;
			}
		}
	}

	if ($ok) {
		$rep_dao->stock_descontado = 1;
		if ($rep_dao->update($user) <= 0) {
			$ok = false;
		}
	}

	if ($ok) {
		$db->commit();
		return true;
	}

	$db->rollback();
	dol_syslog("TallerPro - Error descontando stock en reparación ID " . $reparacion_id . ": " . $db->lasterror(), LOG_ERR);
	return false;
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

	$table_veh = MAIN_DB_PREFIX . "taller_vehiculo";

	$db->begin();

	$rep_dao = new TallerReparacion($db);
	if ($rep_dao->fetch($reparacion_id) <= 0) {
		$db->rollback();
		return false;
	}

	if (empty($rep_dao->stock_descontado)) {
		$db->commit();
		return true; // No había sido descontado
	}

	$notas_str = (string) $rep_dao->notas;
	if (empty($notas_str) || ($notas_str[0] !== '{' && $notas_str[0] !== '[')) {
		$db->commit();
		return true;
	}

	$data = json_decode($notas_str, true);
	if (empty($data['materiales']) || !is_array($data['materiales'])) {
		$db->commit();
		return true;
	}

	$matricula = 'Vehículo';
	$sql_v = "SELECT v.matricula FROM " . $table_veh . " v WHERE v.rowid = " . ((int) $rep_dao->fk_vehiculo) . " AND v.entity IN (" . getEntity('taller_vehiculo') . ") LIMIT 1";
	$res_v = $db->query($sql_v);
	if ($res_v && ($v_obj = $db->fetch_object($res_v)) && !empty($v_obj->matricula)) {
		$matricula = $v_obj->matricula;
	}

	$ot_num = 'OT #' . str_pad($reparacion_id, 4, '0', STR_PAD_LEFT);
	$label_mvt = "Devolución por anulación de reparación " . $ot_num . " (" . $matricula . ")";
	$ok = true;

	foreach ($data['materiales'] as $m) {
		$cant = (float) (!empty($m['cantidad']) ? $m['cantidad'] : 1);
		if ($cant <= 0) continue;

		$mat_code = !empty($m['codigo']) ? trim($m['codigo']) : '';
		$mat_desc = !empty($m['descripcion']) ? trim($m['descripcion']) : '';

		$mat_obj = tallerpro_find_active_material_by_ref_or_name($db, $mat_code, $mat_desc);
		if ($mat_obj) {
			$mat_dao = new TallerMaterial($db);
			if ($mat_dao->fetch((int) $mat_obj->rowid) <= 0) {
				$ok = false;
				break;
			}
			$pid = (int) $mat_dao->fk_product;
			if ($pid > 0) {
				if (!tallerpro_record_stock_movement($pid, $cant, $label_mvt, 'reparacion', $reparacion_id, $user)) {
					$ok = false;
					break;
				}
				$mat_dao->stock = tallerpro_get_product_stock($pid);
				if ($mat_dao->update($user) <= 0) {
					$ok = false;
					break;
				}
			}
		}
	}

	if ($ok) {
		$rep_dao->stock_descontado = 0;
		if ($rep_dao->update($user) <= 0) {
			$ok = false;
		}
	}

	if ($ok) {
		$db->commit();
		return true;
	}

	$db->rollback();
	dol_syslog("TallerPro - Error restaurando stock en reparación ID " . $reparacion_id . ": " . $db->lasterror(), LOG_ERR);
	return false;
}

