<?php
/* Copyright (C) 2004-2018	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019	Nicolas ZABOURI				<info@inovea-conseil.com>
 * Copyright (C) 2019-2024	Frédéric France				<frederic.france@free.fr>
 * Copyright (C) 2026		Logi-K Soluciones Digitales	<https://logi-k.es/>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   taller     Module Taller
 *  \brief      Taller module descriptor.
 *
 *  \file       htdocs/taller/core/modules/modTaller.class.php
 *  \ingroup    taller
 *  \brief      Description and activation file for module Taller
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module Taller
 */
class modTaller extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 500010; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'taller';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = "other";

		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';

		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
		// Module label (no space allowed), used if translation string 'ModuleTallerName' not found (Taller is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// DESCRIPTION_FLAG
		// Module description, used if translation string 'ModuleTallerDesc' not found (Taller is name of module).
		$this->description = "Gestión integral de talleres mecánicos y automoción para Dolibarr ERP/CRM";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "Módulo completo de gestión de taller: recepción de vehículos, clientes, órdenes de trabajo, control de recambios, aseguradoras y facturación oficial.";

		// Author
		$this->editor_name = 'Logi-K Soluciones Digitales';
		$this->editor_url = 'https://logi-k.es/';		// Must be an external online web site
		$this->editor_squarred_logo = 'tallerpro_logo.png@taller';					// Must be image filename into the module/img directory followed with @modulename.

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated', 'experimental_deprecated' or a version string like 'x.y.z'
		$this->version = '1.0.0';
		// Url to the file with your last numberversion of this module
		//$this->url_last_version = 'http://www.example.com/versionmodule.txt';

		// Key used in llx_const table to save module status enabled/disabled (where TALLER is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		// To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = 'taller@taller';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 0,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 0,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			'models' => 0,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// CSS y JS propios cargados exclusivamente en las pantallas TallerPro (evita contaminación global del ERP)
			'css' => array(),
			'js' => array(),
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
			/* BEGIN MODULEBUILDER HOOKSCONTEXTS */
			'hooks' => array(
				//   'data' => array(
				//       'hookcontext1',
				//       'hookcontext2',
				//   ),
				//   'entity' => '0',
			),
			/* END MODULEBUILDER HOOKSCONTEXTS */
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
			// Set this to 1 if the module provides a website template into doctemplates/websites/website_template-mytemplate
			'websitetemplates' => 0,
			// Set this to 1 if the module provides a captcha driver
			'captcha' => 0
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/taller/temp","/taller/subdir");
		$this->dirs = array("/taller/temp", "/taller/vehiculos");

		// Config pages. Put here list of php page, stored into taller/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@taller");

		// Dependencies
		// A condition to hide module
		$this->hidden = getDolGlobalInt('MODULE_TALLER_DISABLED'); // A condition to disable module;
		// List of module class names that must be enabled if this module is enabled.
		$this->depends = array('modFacture', 'modPropale', 'modSociete', 'modProduct', 'modStock');
		// List of module class names to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->requiredby = array();
		// List of module class names this module is in conflict with. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array();

		// The language file dedicated to your module
		$this->langfiles = array("taller@taller");

		// Prerequisites
		$this->phpmin = array(7, 4); // Minimum version of PHP required by module
		// $this->phpmax = array(8, 0); // Maximum version of PHP required by module
		$this->need_dolibarr_version = array(16, 0); // Minimum version of Dolibarr required by module
		// $this->max_dolibarr_version = array(19, -3); // Maximum version of Dolibarr required by module
		$this->need_javascript_ajax = 0;

		// Messages at activation
		$this->warnings_activation = array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		//$this->automatic_activation = array('FR'=>'TallerWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(1 => array('TALLER_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
		//                             2 => array('TALLER_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
		// );
		$this->const = array();

		// Some keys to add into the overwriting translation tables
		/*$this->overwrite_translation = array(
			'en_US:ParentCompany'=>'Parent company or reseller',
			'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
		)*/

		if (!isModEnabled("taller")) {
			$conf->taller = new stdClass();
			$conf->taller->enabled = 0;
		}

		// Array to add new pages in new tabs
		/* BEGIN MODULEBUILDER TABS */
		$this->tabs = array();
		/* END MODULEBUILDER TABS */
		// Example:
		// To add a new tab identified by code tabname1
		// $this->tabs[] = array('data' => 'objecttype:+tabname1:Title1:mylangfile@taller:$user->hasRight(\'taller\', \'read\'):/taller/mynewtab1.php?id=__ID__');
		// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
		// $this->tabs[] = array('data' => 'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@taller:$user->hasRight(\'othermodule\', \'read\'):/taller/mynewtab2.php?id=__ID__',
		// To remove an existing tab identified by code tabname
		// $this->tabs[] = array('data' => 'objecttype:-tabname:NU:conditiontoremove');
		//
		// Where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'delivery'         to add a tab in delivery view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'supplier_invoice' to add a tab in supplier invoice view
		// 'member'           to add a tab in foundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in sale order view
		// 'supplier_order'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'supplier_payment' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view


		// Dictionaries
		/* Example:
		 $this->dictionaries=array(
		 'langs' => 'taller@taller',
		 // List of tables we want to see into dictionary editor
		 'tabname' => array("table1", "table2", "table3"),
		 // Label of tables
		 'tablib' => array("Table1", "Table2", "Table3"),
		 // Request to select fields
		 'tabsql' => array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table1 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table2 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table3 as f'),
		 // Sort order
		 'tabsqlsort' => array("label ASC", "label ASC", "label ASC"),
		 // List of fields (result of select to show dictionary)
		 'tabfield' => array("code,label", "code,label", "code,label"),
		 // List of fields (list of fields to edit a record)
		 'tabfieldvalue' => array("code,label", "code,label", "code,label"),
		 // List of fields (list of fields for insert)
		 'tabfieldinsert' => array("code,label", "code,label", "code,label"),
		 // Name of columns with primary key (try to always name it 'rowid')
		 'tabrowid' => array("rowid", "rowid", "rowid"),
		 // Condition to show each dictionary
		 'tabcond' => array(isModEnabled('taller'), isModEnabled('taller'), isModEnabled('taller')),
		 // Tooltip for every fields of dictionaries: DO NOT PUT AN EMPTY ARRAY
		 'tabhelp' => array(array('code' => $langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), array('code' => $langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), ...),
		 );
		 */
		/* BEGIN MODULEBUILDER DICTIONARIES */
		$this->dictionaries = array();
		/* END MODULEBUILDER DICTIONARIES */

		// Boxes/Widgets
		// Add here list of php file(s) stored in taller/core/boxes that contains a class to show a widget.
		/* BEGIN MODULEBUILDER WIDGETS */
		$this->boxes = array(
			//  0 => array(
			//      'file' => 'tallerwidget1.php@taller',
			//      'note' => 'Widget provided by Taller',
			//      'enabledbydefaulton' => 'Home',
			//  ),
			//  ...
		);
		/* END MODULEBUILDER WIDGETS */

		// Cronjobs (List of cron jobs entries to add when module is enabled)
		// unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		/* BEGIN MODULEBUILDER CRON */
		$this->cronjobs = array(
			//  0 => array(
			//      'label' => 'MyJob label',
			//      'jobtype' => 'method',
			//      'class' => '/taller/class/myobject.class.php',
			//      'objectname' => 'MyObject',
			//      'method' => 'doScheduledJob',
			//      'parameters' => '',
			//      'comment' => 'Comment',
			//      'frequency' => 2,
			//      'unitfrequency' => 3600,
			//      'status' => 0,
			//      'test' => 'isModEnabled("taller")',
			//      'priority' => 50,
			//  ),
		);
		/* END MODULEBUILDER CRON */
		// Example: $this->cronjobs=array(
		//    0=>array('label'=>'My label', 'jobtype'=>'method', 'class'=>'/dir/class/file.class.php', 'objectname'=>'MyClass', 'method'=>'myMethod', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>2, 'unitfrequency'=>3600, 'status'=>0, 'test'=>'isModEnabled("taller")', 'priority'=>50),
		//    1=>array('label'=>'My label', 'jobtype'=>'command', 'command'=>'', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>1, 'unitfrequency'=>3600*24, 'status'=>0, 'test'=>'isModEnabled("taller")', 'priority'=>50)
		// );

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;
		// Add here entries to declare new permissions
		/* BEGIN MODULEBUILDER PERMISSIONS */
		$this->rights[$r][0] = $this->numero . sprintf("%02d", 1); // Permission id
		$this->rights[$r][1] = 'PermissionTallerRead'; // Permission label
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'read'; // In php code, permission will be checked by test if ($user->hasRight('taller', 'read'))
		$r++;

		$this->rights[$r][0] = $this->numero . sprintf("%02d", 2);
		$this->rights[$r][1] = 'PermissionTallerWrite';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'write'; // $user->hasRight('taller', 'write')
		$r++;

		$this->rights[$r][0] = $this->numero . sprintf("%02d", 3);
		$this->rights[$r][1] = 'PermissionTallerDelete';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'delete'; // $user->hasRight('taller', 'delete')
		$r++;

		$this->rights[$r][0] = $this->numero . sprintf("%02d", 4);
		$this->rights[$r][1] = 'PermissionTallerFacturar';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'facturar'; // $user->hasRight('taller', 'facturar')
		$r++;
		/* END MODULEBUILDER PERMISSIONS */


		// Main menu entries to add
		$this->menu = array();
		$r = 0;
		// Add here entries to declare new menus
		/* BEGIN MODULEBUILDER TOPMENU */
		$this->menu[$r++] = array(
			'fk_menu' => '', // Will be stored into mainmenu + leftmenu. Use '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'top', // This is a Top menu entry
			'titre' => 'ModuleTallerName',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'taller',
			'leftmenu' => '',
			'url' => '/taller/tallerindex.php',
			'langs' => 'taller@taller', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("taller")', // Define condition to show or hide menu entry. Use 'isModEnabled("taller")' if entry must be visible if module is enabled.
			'perms' => '$user->hasRight("taller", "read")', // Solo usuarios internos con permiso de lectura
			'target' => '',
			'user' => 0, // 0=Menu for internal users only, 1=external users, 2=both
		);
		/* END MODULEBUILDER TOPMENU */

		/* BEGIN MODULEBUILDER LEFTMENU MYOBJECT */
		$menu_items = array(
			array('code' => 'inicio', 'titre' => 'Inicio', 'url' => '/taller/tallerindex.php', 'icon' => 'fa-home', 'perms' => '$user->hasRight("taller", "read")'),
			array('code' => 'clientes', 'titre' => 'Clientes', 'url' => '/taller/clientes.php', 'icon' => 'fa-users', 'perms' => '$user->hasRight("taller", "read")'),
			array('code' => 'vehiculos', 'titre' => 'Vehículos', 'url' => '/taller/vehiculos.php', 'icon' => 'fa-car', 'perms' => '$user->hasRight("taller", "read")'),
			array('code' => 'reparaciones', 'titre' => 'Reparaciones', 'url' => '/taller/reparaciones.php', 'icon' => 'fa-wrench', 'perms' => '$user->hasRight("taller", "read")'),
			array('code' => 'materiales', 'titre' => 'Materiales', 'url' => '/taller/materiales.php', 'icon' => 'fa-box', 'perms' => '$user->hasRight("taller", "read")'),
			array('code' => 'seguros', 'titre' => 'Seguros', 'url' => '/taller/seguros.php', 'icon' => 'fa-shield-alt', 'perms' => '$user->hasRight("taller", "read")'),
			array('code' => 'facturas', 'titre' => 'Facturas', 'url' => '/taller/facturas.php', 'icon' => 'fa-file-invoice-dollar', 'perms' => '$user->hasRight("taller", "read") || $user->hasRight("taller", "facturar")'),
			array('code' => 'configuracion', 'titre' => 'Configuración', 'url' => '/taller/admin/setup.php', 'icon' => 'fa-cog', 'perms' => '$user->admin'),
		);

		foreach ($menu_items as $mitem) {
			$this->menu[$r++] = array(
				'fk_menu' => 'fk_mainmenu=taller',
				'type' => 'left',
				'titre' => $mitem['titre'],
				'prefix' => img_picto('', $mitem['icon'], 'class="pictofixedwidth valignmiddle paddingright"'),
				'mainmenu' => 'taller',
				'leftmenu' => 'taller_' . $mitem['code'],
				'url' => $mitem['url'],
				'langs' => 'taller@taller',
				'position' => 1000 + $r,
				'enabled' => 'isModEnabled("taller")',
				'perms' => $mitem['perms'],
				'target' => '',
				'user' => 0,
			);
		}
		/* END MODULEBUILDER LEFTMENU MYOBJECT */


		// Exports profiles provided by this module
		$r = 0;

		// Imports profiles provided by this module
		$r = 0;
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int<-1,1>          	1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs, $user;

		// Create tables of module at module activation
		$result = $this->_load_tables('/taller/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries
		}

		// Create or repair extrafields during init (facture y propal)
		require_once dirname(dirname(__DIR__)).'/lib/taller.lib.php';
		tallerpro_ensure_module_extrafields($this->db);

		// Permissions
		$this->remove($options);

		$sql = array();

		$res_init = $this->_init($sql, $options);
		if ($res_init > 0) {
			// Migrar presupuestos antiguos guardados como Facture borrador a Propal nativo
			tallerpro_migrate_legacy_presupuestos_to_propal($this->db, $user);
		}
		return $res_init;
	}

	/**
	 *	Function called when module is disabled.
	 *	Remove from database constants, boxes and permissions from Dolibarr database.
	 *	Data directories are not deleted
	 *
	 *	@param	string		$options	Options when enabling module ('', 'noboxes')
	 *	@return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
