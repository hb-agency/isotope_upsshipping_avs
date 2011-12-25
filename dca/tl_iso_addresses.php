<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Winans Creative 2011
 * @author     Blair Winans <blair@winanscreative.com>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


/**
 * Table tl_iso_addresses
 */
 

/**
 * Palettes
 */
 $GLOBALS['TL_DCA']['tl_iso_addresses']['palettes']['default'] = str_replace('country','country,address_classification', $GLOBALS['TL_DCA']['tl_iso_addresses']['palettes']['default']);


/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_iso_addresses']['fields']['address_classification'] = array
(
	'label'					=> &$GLOBALS['TL_LANG']['tl_iso_addresses']['address_classification'],
	'exclude'				=> true,
	'filter'				=> true,
	'sorting'				=> true,
	'default'				=> 'residential',
	'inputType'				=> 'select',
	'options'				=> array('residential', 'business', 'unknown'),
	'reference'				=> &$GLOBALS['TL_LANG']['tl_iso_addresses'],
	'eval'					=> array('mandatory'=>true, 'feEditable'=>true, 'feGroup'=>'address', 'tl_class'=>'w50'),
);