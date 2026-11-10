<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/taller_reparacion.class.php
 * \ingroup taller
 * \brief   Clase CommonObject para gestión de órdenes de reparación en TallerPro
 */

require_once __DIR__ . '/taller_base.class.php';

/**
 * Entidad Reparación (tabla llx_taller_reparacion).
 */
class TallerReparacion extends TallerBaseObject
{
	/** @var string */
	public $element = 'taller_reparacion';

	/** @var string */
	public $table_element = 'taller_reparacion';

	/** @var int */
	public $fk_vehiculo = 0;

	/** @var int|string|null */
	public $fecha = null;

	/** @var string|null */
	public $ref_factura = null;

	/** @var string|null */
	public $descripcion = '';

	/** @var float */
	public $coste_mo = 0.0;

	/** @var string|null */
	public $notas = '';

	/** @var string */
	public $estado = 'en_curso';

	/** @var int */
	public $stock_descontado = 0;

	/** @var array<string,array<string,mixed>> */
	public $fields = array(
		'rowid'            => array('type' => 'integer',      'label' => 'TechnicalID',    'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0),
		'fk_vehiculo'      => array('type' => 'integer',      'label' => 'Vehiculo',       'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 1),
		'fecha'            => array('type' => 'date',         'label' => 'Fecha',          'enabled' => 1, 'position' => 20,  'notnull' => 1, 'visible' => 1),
		'ref_factura'      => array('type' => 'varchar(64)',  'label' => 'RefFactura',     'enabled' => 1, 'position' => 30,  'notnull' => 0, 'visible' => 1),
		'descripcion'      => array('type' => 'text',         'label' => 'Description',    'enabled' => 1, 'position' => 40,  'notnull' => 0, 'visible' => 1),
		'coste_mo'         => array('type' => 'double(24,8)', 'label' => 'CosteMO',        'enabled' => 1, 'position' => 50,  'notnull' => 0, 'default' => 0, 'visible' => 1),
		'notas'            => array('type' => 'text',         'label' => 'Notes',          'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 3),
		'estado'           => array('type' => 'varchar(32)',  'label' => 'Estado',         'enabled' => 1, 'position' => 70,  'notnull' => 0, 'default' => 'en_curso', 'visible' => 1),
		'stock_descontado' => array('type' => 'smallint',     'label' => 'StockDescontado','enabled' => 1, 'position' => 80,  'notnull' => 0, 'default' => 0, 'visible' => 0),
		'date_creation'    => array('type' => 'datetime',     'label' => 'DateCreation',   'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'fk_user_creat'    => array('type' => 'integer',      'label' => 'UserAuthor',     'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'entity'           => array('type' => 'integer',      'label' => 'Entity',         'enabled' => 1, 'position' => 520, 'notnull' => 0, 'default' => 1, 'visible' => 0),
		'status'           => array('type' => 'smallint',     'label' => 'Status',         'enabled' => 1, 'position' => 530, 'notnull' => 0, 'default' => 1, 'visible' => 1),
	);

	/**
	 * {@inheritdoc}
	 */
	public function create(User $user, $notrigger = 0)
	{
		$ts = $this->normalizeDateTimestamp($this->fecha);
		$this->fecha = ($ts !== null) ? $ts : dol_now();
		if (empty($this->estado)) {
			$this->estado = 'en_curso';
		}
		return parent::create($user, $notrigger);
	}

	/**
	 * {@inheritdoc}
	 */
	public function update(User $user, $notrigger = 0)
	{
		$ts = $this->normalizeDateTimestamp($this->fecha);
		if ($ts !== null) {
			$this->fecha = $ts;
		}
		return parent::update($user, $notrigger);
	}
}

