<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "redis_lock_strategy".
 *
 * Auto generated 25-04-2016 18:05
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'Tourstrean Redis Lock Strategy',
	'description' => 'Set a Lock Strategy for Redis with priority 100',
	'category' => 'fe',
	'version' => '1.0.0',
	'state' => 'stable',
	'uploadfolder' => false,
	'createDirs' => NULL,
	'clearcacheonload' => false,
	'author' => 'Alexander Miehe',
	'author_email' => 'alexander.miehe@tourstream.eu',
	'author_company' => NULL,
	'constraints' => 
	array (
		'depends' => 
		array (
			'typo3' => '7.6.0-7.99.99',
		),
		'conflicts' => 
		array (
		),
		'suggests' => 
		array (
		),
	),
);

