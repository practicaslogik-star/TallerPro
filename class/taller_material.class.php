<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/taller_material.class.php
 * \ingroup taller
 * \brief   Clase CommonObject para gestión de materiales en TallerPro
 */

require_once __DIR__ . '/taller_base.class.php';

/**
 * Entidad Material (tabla llx_taller_material).
 */
class TallerMaterial extends TallerBaseObject
{
	/** @var string */
	public $element = 'taller_material';

	/** @var string */
	public $table_element = 'taller_material';

	/** @var string */
	public $nombre = '';

	/** @var string|null */
	public $ref = '';

	/** @var int|null */
	public $fk_product = null;

	/** @var string|null */
	public $proveedor = '';

	/** @var float */
	public $stock = 0.0;

	/** @var float */
	public $precio = 0.0;

	/** @var string|null */
	public $notas = '';

	/** @var array<string,array<string,mixed>> */
	public $fields = array(
		'rowid'         => array('type' => 'integer',       'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0),
		'nombre'        => array('type' => 'varchar(255)',  'label' => 'Nombre',      'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 1),
		'ref'           => array('type' => 'varchar(128)',  'label' => 'Ref',         'enabled' => 1, 'position' => 20,  'notnull' => 0, 'visible' => 1),
		'fk_product'    => array('type' => 'integer',       'label' => 'Product',     'enabled' => 1, 'position' => 30,  'notnull' => -1, 'visible' => 1),
		'proveedor'     => array('type' => 'varchar(255)',  'label' => 'Proveedor',   'enabled' => 1, 'position' => 40,  'notnull' => 0, 'visible' => 1),
		'stock'         => array('type' => 'double',        'label' => 'Stock',       'enabled' => 1, 'position' => 50,  'notnull' => 0, 'default' => 0, 'visible' => 1),
		'precio'        => array('type' => 'double(24,8)',  'label' => 'Price',       'enabled' => 1, 'position' => 60,  'notnull' => 0, 'default' => 0, 'visible' => 1),
		'notas'         => array('type' => 'text',          'label' => 'Notes',       'enabled' => 1, 'position' => 70,  'notnull' => 0, 'visible' => 3),
		'date_creation' => array('type' => 'datetime',      'label' => 'DateCreation','enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer',       'label' => 'UserAuthor',  'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'entity'        => array('type' => 'integer',       'label' => 'Entity',      'enabled' => 1, 'position' => 520, 'notnull' => 0, 'default' => 1, 'visible' => 0),
		'status'        => array('type' => 'smallint',      'label' => 'Status',      'enabled' => 1, 'position' => 530, 'notnull' => 0, 'default' => 1, 'visible' => 1),
	);

	/**
	 * {@inheritdoc}
	 */
	public function create(User $user, $notrigger = 0)
	{
		$this->fk_product = ($this->fk_product > 0) ? (int) $this->fk_product : null;
		return parent::create($user, $notrigger);
	}

	/**
	 * {@inheritdoc}
	 */
	public function update(User $user, $notrigger = 0)
	{
		$this->fk_product = ($this->fk_product > 0) ? (int) $this->fk_product : null;
		return parent::update($user, $notrigger);
	}
}
