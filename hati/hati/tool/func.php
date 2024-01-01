<?php

	function loadHati(): ?string {
		$hati = '';
		$cwd = getcwd();
		$counter = 0;

		while ($cwd !== false) {
			if ($counter >= 20) break;

			$hatiJson = $cwd . DIRECTORY_SEPARATOR . 'hati' . DIRECTORY_SEPARATOR . 'hati.json';
			if (file_exists($hatiJson)) {
				$hati = $cwd . DIRECTORY_SEPARATOR;
				break;
			}

			// go one directory up
			$cwd = realpath($cwd . '/..');

			$counter ++;
		}

		if (empty($hati)) return null;

		require $hati . 'hati/init.php';
		return $hati;
	}