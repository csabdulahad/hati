<?php

namespace hati\util;

use Exception;
use ZipArchive;

/**
 * A helper class for creating ZIP file using concise API.
 *
 * @since 5.0.0
 * */

class Zip {

	/** The physical path to the zip file created */
	private string $zipPath;

	/** The ZipArchive object */
	private ZipArchive $zip;

	/** Indicates whether the zip archive has been closed or not */
	private bool $closed = false;

	/**
	 * Constructs a zip archive file at the provided location.
	 * If the file exists, it will be overwritten.
	 *
	 * @param string $zipPath The path to the zip file to be created
	 * @throws Exception When it couldn't create the zip file
	 */
	private function __construct(string $zipPath) {
		$this -> zip = new ZipArchive();

		if ($this -> zip -> open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			throw new Exception("Failed to create ZIP file");
		}

		$this -> zipPath = $zipPath;
	}

	/**
	 * Creates a zip file at specified path.
	 *
	 * @param string $zipPath The path to the zip file to be created
	 * @throws Exception When it couldn't create the zip file
	 */
	public static function create(string $zipPath): Zip {
		return new Zip($zipPath);
	}

	/**
	 * Add a file to the zip archive.
	 *
	 * @param string $path The path of the file to be added. Path can be relative.
	 * @param bool $keepFilePath Indicates whether th copy the same file structure for the file
	 * @return bool true if the file addition to the archive successful; false otherwise
	 * */
	public function addFile(string $path, bool $keepFilePath = true): bool {

		if (!file_exists($path)) {
			return false;
		}

		if (!$keepFilePath) {
			return $this -> zip -> addFile($path, basename($path));
		}

		$localPath = realpath($path);
		$localPath = str_replace(':' . DIRECTORY_SEPARATOR , DIRECTORY_SEPARATOR, $localPath);

		return $this -> zip -> addFile($path, $localPath);
	}

	/**
	 * Recursively adds the directory to the zip archive.
	 *
	 * @param string $path The path of the directory to be added. Path can be relative.
	 * @param bool $keepFilePath Indicates whether th copy the same file structure for the file
	 * @return bool true if the file addition to the archive successful; false otherwise
	 * */
	public function addDir(string $path, bool $keepFilePath = true): bool {
		$path = rtrim($path, '/\\').DIRECTORY_SEPARATOR;

		if ( ! $fp = @opendir($path)) {
			return FALSE;
		}

		while (FALSE !== ($file = readdir($fp))) {
			if ($file[0] === '.') {
				continue;
			}

			if (is_dir($path.$file)) {
				$this -> addDir($path.$file.DIRECTORY_SEPARATOR, $keepFilePath);
			} else {
				if ($keepFilePath) {
					$filename = realpath($path.$file);
				} else {
					$filename = $file;
				}

				$filename = str_replace(':' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $filename);

				$this -> zip -> addFile($path.$file, $filename);
			}
		}

		closedir($fp);
		return TRUE;
	}

	/**
	 * Closes the zip archive.
	 * */
	public function close(): void {
		$this -> zip -> close();
		$this -> closed = true;
	}

	/**
	 * Forces the created zip file downloading using proper http headers. It closes the
	 * zip archive if it wasn't. The zip file can be deleted when downloading is done.
	 *
	 * @param string $filename set the name to the downloaded file
	 * @param bool $deleteArchive indicates whether to delete the zip file after downloading
	 * @return bool returns true if the downloading was successful; false otherwise.
	 * */
	public function download(string $filename, bool $deleteArchive = false): bool {

		try {
			if (!$this -> closed) {
				$this -> close();
			}
		} catch (Exception) {
			return false;
		}

		if (!file_exists($this -> zipPath)) {
			return false;
		}

		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		readfile($this -> zipPath);

		if ($deleteArchive) {
			unlink($this -> zipPath);
		}

		return true;
	}

}