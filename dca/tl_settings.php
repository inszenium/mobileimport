<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   mobile
 * @author    inszenium
 * @license   LGPL
 * @copyright inszenium 2017
 */


/**
 * Add to palette
 */
$GLOBALS['TL_DCA']['tl_settings']['palettes']['__selector__'][] = 'usemobile';
//$GLOBALS['TL_DCA']['tl_settings']['palettes']['__selector__'][] = 'useautoscout24';

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{mobileimport_legend},usemobile';
//$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{mobileimport_legend},usemobile,useautoscout24';

$GLOBALS['TL_DCA']['tl_settings']['subpalettes']['usemobile'] = 'mobilecustomerId,mobileUser,mobilePass';
$GLOBALS['TL_DCA']['tl_settings']['subpalettes']['autoscout24'] = 'autoscout24';

/**
 * Add fields
 */

$GLOBALS['TL_DCA']['tl_settings']['fields']['usemobile'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_settings']['usemobile'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('submitOnChange'=>true)
);


$GLOBALS['TL_DCA']['tl_settings']['fields']['mobilecustomerId'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['mobilecustomerId'],
	'inputType'               => 'text',
	'eval'                    => array('mandatory'=>true, 'nospace'=>true, 'tl_class'=>'w50')
);
$GLOBALS['TL_DCA']['tl_settings']['fields']['mobileUser'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['mobileUser'],
	'inputType'               => 'text',
	'eval'                    => array('decodeEntities'=>true, 'tl_class'=>'w50')
);
$GLOBALS['TL_DCA']['tl_settings']['fields']['mobilePass'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['mobilePass'],
	'inputType'               => 'textStore',
	'eval'                    => array('decodeEntities'=>true, 'tl_class'=>'w50'),
	'save_callback' => array
	(
		array('tl_settings_mobileimport', 'storePass')
	)
);


$GLOBALS['TL_DCA']['tl_settings']['fields']['useautoscout24'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_settings']['useautoscout24'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('submitOnChange'=>true)
);



/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_settings_mobileimport extends Backend
{


	/**
	 * Store the unfiltered password
	 *
	 * @param mixed         $varValue
	 * @param DataContainer $dc
	 *
	 * @return mixed
	 */
	public function storePass($varValue, DataContainer $dc)
	{
		if (isset($_POST[$dc->field]))
		{
			return Input::postUnsafeRaw($dc->field);
		}
		return $varValue;
	}
}
