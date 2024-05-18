<?php
	
	namespace hati\cli;
	
	/**
	 * Set of allowed filters to be used when adding flag options
	 * to HatiCLI to save developers from typos
	 *
	 * @since 6.0.0
	 * */
		
	enum CLIOptionFilter: string {
		case STRING = 'str';
		case INT = 'int';
		case FLOAT = 'float';
	}