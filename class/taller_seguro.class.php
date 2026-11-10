<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/taller_seguro.class.php
 * \ingroup taller
 * \brief   Clase CommonObject para gestión de seguros en TallerPro
 */

require_once __DIR__ . '/taller_base.class.php';

/**
 * Entidad Seguro (tabla llx_taller_seguro).
 */
class TallerSeguro extends TallerBaseObject
{
	/** @var string */
	public $element = 'taller_seguro';

	/** @var string */
	public $table_element = 'taller_seguro';

	/** @var int|null */
	public $fk_vehiculo = null;

	/** @var string */
	public $compania = '';

	/** @var string|null */
	public $poliza = '';

	/** @var string|null */
	public $tipo = '';

	/** @var int|string|null */
	public $fecha_inicio = null;

	/** @var int|string|null */
	public $fecha_fin = null;

	/** @var string|null */
	public $telefono = '';

	/** @var string|null */
	public $notas = '';

	/** @var array<string,array<string,mixed>> */
	public $fields = array(
		'rowid'         => array('type' => 'integer',      'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0),
		'fk_vehiculo'   => array('type' => 'integer',      'label' => 'Vehiculo',    'enabled' => 1, 'position' => 10,  'notnull' => -1, 'visible' => 1),
		'compania'      => array('type' => 'varchar(128)', 'label' => 'Compania',    'enabled' => 1, 'position' => 20,  'notnull' => 1, 'visible' => 1),
		'poliza'        => array('type' => 'varchar(64)',  'label' => 'Poliza',      'enabled' => 1, 'position' => 30,  'notnull' => 0, 'visible' => 1),
		'tipo'          => array('type' => 'varchar(64)',  'label' => 'Tipo',        'enabled' => 1, 'position' => 40,  'notnull' => 0, 'visible' => 1),
		'fecha_inicio'  => array('type' => 'date',         'label' => 'FechaInicio', 'enabled' => 1, 'position' => 50,  'notnull' => 0, 'visible' => 1),
		'fecha_fin'     => array('type' => 'date',         'label' => 'FechaFin',    'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 1),
		'telefono'      => array('type' => 'varchar(32)',  'label' => 'Phone',       'enabled' => 1, 'position' => 70,  'notnull' => 0, 'visible' => 1),
		'notas'         => array('type' => 'text',         'label' => 'Notes',       'enabled' => 1, 'position' => 80,  'notnull' => 0, 'visible' => 3),
		'date_creation' => array('type' => 'datetime',     'label' => 'DateCreation','enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer',      'label' => 'UserAuthor',  'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'entity'        => array('type' => 'integer',      'label' => 'Entity',      'enabled' => 1, 'position' => 520, 'notnull' => 0, 'default' => 1, 'visible' => 0),
		'status'        => array('type' => 'smallint',     'label' => 'Status',      'enabled' => 1, 'position' => 530, 'notnull' => 0, 'default' => 1, 'visible' => 1),
	);

	/**
	 * {@inheritdoc}
	 */
	public function create(User $user, $notrigger = 0)
	{
		$this->fk_vehiculo = ($this->fk_vehiculo > 0) ? (int) $this->fk_vehiculo : null;
		$this->fecha_inicio = $this->normalizeDateTimestamp($this->fecha_inicio);
		$this->fecha_fin = $this->normalizeDateTimestamp($this->fecha_fin);
		return parent::create($user, $notrigger);
	}

	/**
	 * {@inheritdoc}
	 */
	public function update(User $user, $notrigger = 0)
	{
		$this->fk_vehiculo = ($this->fk_vehiculo > 0) ? (int) $this->fk_vehiculo : null;
		$this->fecha_inicio = $this->normalizeDateTimestamp($this->fecha_inicio);
		$this->fecha_fin = $this->normalizeDateTimestamp($this->fecha_fin);
		return parent::update($user, $notrigger);
	}
}
