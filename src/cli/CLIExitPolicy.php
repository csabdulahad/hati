<?php

namespace hati\cli;

enum CLIExitPolicy
{
	/**
	 * Parent should treat child code as its own
	 * */
	case PROPAGATE;
	
	/**
	 * Child failure does not stop parent; parent decides
	 * */
	case SWALLOW;
	
	/**
	 * Convert into a parent-level fatal error immediately if child fails
	 * */
	case RAISE;
}