<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */


/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
	'inszenium',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Classes
	'inszenium\ImportMobile'   => 'system/modules/mobileimport/classes/ImportMobile.php',
	'inszenium\ImportAutoscout24' => 'system/modules/mobileimport/classes/ImportAutoscout24.php',
));
