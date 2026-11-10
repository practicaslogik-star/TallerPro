<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/taller_base.class.php
 * \ingroup taller
 * \brief   Clase base CommonObject compartida por las entidades propias de TallerPro
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

/**
 * Base DAO común para entidades de TallerPro con aislamiento multiempresa y borrado lógico.
 */
abstract class TallerBaseObject extends CommonObject
{
	/** @var int Indica que la entidad soporta aislamiento MultiCompany */
	public $ismultientitymanaged = 1;

	/** @var int Las tablas propias del módulo no utilizan tablas *_extrafields */
	public $isextrafieldmanaged = 0;

	/** @var int */
	public $rowid;

	/** @var int|string|null */
	public $date_creation;

	/** @var int|null */
	public $fk_user_creat;

	/** @var int|null */
	public $entity;

	/** @var int */
	public $status = 1;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Manejador de base de datos
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Carga el registro desde BD comprobando la entidad activa.
	 *
	 * @param int         $id  ID del registro
	 * @param string|null $ref Referencia opcional
	 * @return int             >0 si OK, 0 si no existe o fuera de entidad, <0 si error
	 */
	public function fetch($id, $ref = null)
	{
		$morewhere = ' AND t.entity IN (' . getEntity($this->element) . ')';
		return $this->fetchCommon((int) $id, $ref, $morewhere, 1);
	}

	/**
	 * Crea el registro usando CommonObject::createCommon y disparando triggers estándar.
	 *
	 * @param User $user      Usuario creador
	 * @param int  $notrigger 0=lanzar triggers, 1=deshabilitar triggers
	 * @return int            ID creado (>0) si OK, <=0 si error
	 */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		if (empty($this->entity)) {
			$this->entity = (int) $conf->entity;
		}
		if (empty($this->date_creation)) {
			$this->date_creation = dol_now();
		}
		if (empty($this->fk_user_creat) && !empty($user->id)) {
			$this->fk_user_creat = (int) $user->id;
		}
		if (!isset($this->status)) {
			$this->status = 1;
		}

		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Actualiza el registro en BD mediante CommonObject::updateCommon.
	 *
	 * @param User $user      Usuario que modifica
	 * @param int  $notrigger 0=lanzar triggers, 1=deshabilitar triggers
	 * @return int            >0 si OK, <0 si error
	 */
	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Borrado lógico estándar (status = 0) respetando triggers y transacción de CommonObject.
	 *
	 * @param User $user      Usuario que elimina
	 * @param int  $notrigger 0=lanzar triggers, 1=deshabilitar triggers
	 * @return int            >0 si OK, <0 si error
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if ((int) $this->id <= 0) {
			return -1;
		}
		$this->status = 0;
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Normaliza fechas tipo 'YYYY-MM-DD' o timestamp a timestamp UNIX seguro (mediodía).
	 *
	 * @param int|string|null $value Fecha de entrada
	 * @return int|null
	 */
	protected function normalizeDateTimestamp($value)
	{
		if (is_null($value) || $value === '') {
			return null;
		}
		if (is_numeric($value)) {
			$ts = (int) $value;
			return ($ts > 0) ? $ts : null;
		}
		$str = trim((string) $value);
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $str, $m)) {
			return dol_mktime(12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
		}
		$ts = dol_stringtotime($str);
		return ($ts > 0) ? $ts : null;
	}
}
