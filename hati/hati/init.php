<?php


/*
 * Initialize Hati!
 *
 * First check if Hati has already been loaded. If not then only
 * load it which is will setup Hati as defined by configurations.
 * */
if (!class_exists(\hati\Hati::class)) {
	require dirname(__DIR__) . '/vendor/rootdata21/hati/hati/Hati.php';
}
