<?php

/*
 * By default, composer prepares 'src' as root folder for projects.
 * However, Hati can also work without 'src' folder. Adjust as per
 * your project requirement.
 * */
const HATI_USE_SRC_AS_ROOT = true;


/*
 * Default folder name where Hati tries to find various configuration
 * file.
 * */
const HATI_CONFIG_FOLDER_NAME = 'hati';


/*
 * Initialize Hati!
 *
 * First check if Hati has already been loaded. If not, then only
 * load it which will set up Hati as defined by configurations.
 * */
if (!class_exists(\hati\Hati::class)) {
	$level = HATI_USE_SRC_AS_ROOT ? 2 : 1;
	require dirname(__DIR__, $level) . '/vendor/rootdata21/hati/src/Hati.php';
}
