<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/taller_vehiculo.class.php
 * \ingroup taller
 * \brief   Clase CommonObject para gestión de vehículos en TallerPro
 */

require_once __DIR__ . '/taller_base.class.php';

/**
 * Entidad Vehículo (tabla llx_taller_vehiculo).
 */
class TallerVehiculo extends TallerBaseObject
{
	/** @var string */
	public $element = 'taller_vehiculo';

	/** @var string */
	public $table_element = 'taller_vehiculo';

	/** @var int */
	public $fk_soc = 0;

	/** @var string */
	public $matricula = '';

	/** @var string|null */
	public $marca = '';

	/** @var string|null */
	public $modelo = '';

	/** @var string|null */
	public $anio = '';

	/** @var string|null */
	public $color = '';

	/** @var string|null */
	public $vin = '';

	/** @var int */
	public $km = 0;

	/** @var string|null */
	public $notas = '';

	/** @var array<string,array<string,mixed>> */
	public $fields = array(
		'rowid'         => array('type' => 'integer',  'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0),
		'fk_soc'        => array('type' => 'integer',  'label' => 'ThirdParty',  'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 1),
		'matricula'     => array('type' => 'varchar(32)',  'label' => 'Matricula', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
		'marca'         => array('type' => 'varchar(64)',  'label' => 'Marca',     'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'modelo'        => array('type' => 'varchar(64)',  'label' => 'Modelo',    'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'anio'          => array('type' => 'varchar(10)',  'label' => 'Anio',      'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'color'         => array('type' => 'varchar(32)',  'label' => 'Color',     'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'vin'           => array('type' => 'varchar(64)',  'label' => 'VIN',       'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1),
		'km'            => array('type' => 'integer',  'label' => 'KM',          'enabled' => 1, 'position' => 80,  'notnull' => 0, 'default' => 0, 'visible' => 1),
		'notas'         => array('type' => 'text',     'label' => 'Notes',       'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => 3),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation','enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer',  'label' => 'UserAuthor',  'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'entity'        => array('type' => 'integer',  'label' => 'Entity',      'enabled' => 1, 'position' => 520, 'notnull' => 0, 'default' => 1, 'visible' => 0),
		'status'        => array('type' => 'smallint', 'label' => 'Status',      'enabled' => 1, 'position' => 530, 'notnull' => 0, 'default' => 1, 'visible' => 1),
	);
}
