<?php

namespace hati\util;

use Exception;
use FilesystemIterator;
use hati\cli\CLI;
use hati\data\Mimes;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * A helper class consisting functions to deal with files of file system
 * easily in PHP!
 *
 * @since 5.0.0
 * */

abstract class File {

	/**
	 * is_writable() returns TRUE on Windows servers when you really can't write to
	 * the file, based on the read-only attribute. is_writable() is also unreliable
	 * on Unix servers if safe_mode is on.
	 *
	 * @see https://bugs.php.net/bug.php?id=54709
	 *
	 * @throws Exception
	 * */
	public static function isWriteable(string $file): bool {
		// If we're on a Unix server we call is_writable
		if (! CLI::isWindows()) {
			return is_writable($file);
		}

		/* For Windows servers and safe_mode "on" installations we'll actually
		 * write a file then read it. Bah...
		 */
		if (is_dir($file)) {
			$file = rtrim($file, '/') . '/' . bin2hex(random_bytes(16));
			if (($fp = @fopen($file, 'ab')) === false) {
				return false;
			}

			fclose($fp);
			@chmod($file, 0777);
			@unlink($file);

			return true;
		}

		if (! is_file($file) || ($fp = @fopen($file, 'ab')) === false) {
			return false;
		}

		fclose($fp);

		return true;
	}

	/**
	 * Creates a Directory Map
	 *
	 * Reads the specified directory and builds an array
	 * representation of it. Sub-folders contained with the
	 * directory will be mapped as well.
	 *
	 * @param string $sourceDir      Path to source
	 * @param int    $dirDepth Depth of directories to traverse
	 *                               (0 = fully recursive, 1 = current dir, etc)
	 * @param bool   $hidden         Whether to show hidden files
	 */
	public static function mapDir(string $sourceDir, int $dirDepth = 0, bool $hidden = false): array {
		try {
			$fp = opendir($sourceDir);

			$fileData  = [];
			$newDepth  = $dirDepth - 1;
			$sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			while (false !== ($file = readdir($fp))) {
				// Remove '.', '..', and hidden files [optional]
				if ($file === '.' || $file === '..' || ($hidden === false && $file[0] === '.')) {
					continue;
				}

				if (is_dir($sourceDir . $file)) {
					$file .= DIRECTORY_SEPARATOR;
				}

				if (($dirDepth < 1 || $newDepth > 0) && is_dir($sourceDir . $file)) {
					$fileData[$file] = self::mapDir($sourceDir . $file, $newDepth, $hidden);
				} else {
					$fileData[] = $file;
				}
			}

			closedir($fp);

			return $fileData;
		} catch (Throwable) {
			return [];
		}
	}

	/**
	 * Recursively copies the files and directories of the origin directory
	 * into the target directory, i.e. "mirror" its contents.
	 *
	 * @param bool $overwrite Whether individual files overwrite on collision
	 *
	 * @throws InvalidArgumentException
	 */
	public static function copyDir(string $originDir, string $targetDir, bool $overwrite = true): void {
		if (! is_dir($originDir = rtrim($originDir, '\\/'))) {
			throw new InvalidArgumentException(sprintf('The origin directory "%s" was not found.', $originDir));
		}

		if (! is_dir($targetDir = rtrim($targetDir, '\\/'))) {
			@mkdir($targetDir, 0755, true);
		}

		$dirLen = strlen($originDir);

		/**
		 * @var SplFileInfo $file
		 */
		foreach (new RecursiveIteratorIterator(
					 new RecursiveDirectoryIterator($originDir, FilesystemIterator::SKIP_DOTS),
					 RecursiveIteratorIterator::SELF_FIRST
				 ) as $file) {
			$origin = $file->getPathname();
			$target = $targetDir . substr($origin, $dirLen);

			if ($file->isDir()) {
				if (! is_dir($target)) {
					mkdir($target, 0755);
				}
			} elseif (! is_file($target) || ($overwrite && is_file($target))) {
				copy($origin, $target);
			}
		}
	}
	
	/**
	 * Copy a file from source to destination.
	 *
	 * - If the destination file exists and $override is false, the file will NOT be overwritten
	 *   and the function will return false.
	 * - If the destination file exists and $override is true, the file will be overwritten.
	 * - If the destination parent directory does not exist, it will be created automatically.
	 *
	 * @param string $sourceFile       Absolute or relative path to the source file.
	 * @param string $destinationFile  Absolute or relative path to the destination file.
	 * @param bool   $override     Whether to overwrite the destination if it exists.
	 *
	 * @return bool Returns true on successful copy, false otherwise.
	 */
	public static function copy(string $sourceFile, string $destinationFile, bool $override): bool
	{
		if (!file_exists($sourceFile) || !is_readable($sourceFile)) {
			return false;
		}
		
		if (file_exists($destinationFile) && !$override) {
			return false;
		}
		
		$parentDir = dirname($destinationFile);
		
		if (!is_dir($parentDir)) {
			if (!mkdir($parentDir, 0755, true) && !is_dir($parentDir)) {
				return false;
			}
		}
		
		return copy($sourceFile, $destinationFile);
	}

	/**
	 * Delete Files
	 *
	 * Deletes all files contained in the supplied directory path.
	 * Files must be writable or owned by the system in order to be deleted.
	 * If the second parameter is set to true, any directories contained
	 * within the supplied base directory will be nuked as well.
	 *
	 * @param string $path   File path
	 * @param bool   $delDir Whether to delete any directories found in the path
	 * @param bool   $hidden Whether to include hidden files (files beginning with a period)
	 */
	public static function delete(string $path, bool $delDir = false, bool $hidden = false): bool {
		$path = realpath($path) ?: $path;
		$path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		try {
			foreach (new RecursiveIteratorIterator(
						 new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
						 RecursiveIteratorIterator::CHILD_FIRST
					 ) as $object) {

				// Let's not delete the hidden file if it was said so!
				$filename = $object -> getFilename();
				if (!$hidden && $filename[0] === '.') {
					continue;
				}

				// Delete the folder
				$isDir = $object -> isDir();
				if ($isDir && $delDir) {
					rmdir($object->getPathname());
					continue;
				}

				// Delete the file
				if (!$isDir) {
					unlink($object->getPathname());
				}
			}

			return true;
		} catch (Throwable) {
			return false;
		}
	}
	
	/**
	 * Recursively delete a directory and all of its contents.
	 *
	 * - Deletes all files and subdirectories within the given directory.
	 * - Finally removes the directory itself.
	 * - If the directory does not exist, returns false.
	 *
	 * @param string $dir Absolute or relative path to the directory to delete.
	 *
	 * @return bool Returns true on successful deletion, false otherwise.
	 */
	public static function deleteDir(string $dir): bool
	{
		if (!is_dir($dir)) {
			return false;
		}
		
		$items = scandir($dir);
		if ($items === false) {
			return false;
		}
		
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			
			if (is_dir($path) && !is_link($path)) {
				if (!self::deleteDir($path)) {
					return false;
				}
			} else {
				if (!unlink($path)) {
					return false;
				}
			}
		}
		
		return rmdir($dir);
	}

	/**
	 * Get Directory File Information
	 *
	 * Reads the specified directory and builds an array containing the filenames,
	 * filesize, dates, and permissions
	 *
	 * Any sub-folders contained within the specified path are read as well.
	 *
	 * @param string $sourceDir    Path to source
	 * @param bool   $topLevelOnly Look only at the top level directory specified?
	 * @param bool   $recursion    Internal variable to determine recursion status - do not use in calls
	 */
	public static function getDirFileInfo(string $sourceDir, bool $topLevelOnly = true, bool $recursion = false): array {
		static $fileData = [];
		$relativePath    = $sourceDir;

		try {
			$fp = opendir($sourceDir);

			// reset the array and make sure $source_dir has a trailing slash on the initial call
			if ($recursion === false) {
				$fileData  = [];
				$sourceDir = rtrim(realpath($sourceDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
			}

			// Used to be foreach (scandir($source_dir, 1) as $file), but scandir() is simply not as fast
			while (false !== ($file = readdir($fp))) {
				if (is_dir($sourceDir . $file) && $file[0] !== '.' && $topLevelOnly === false) {
					self::getDirFileInfo($sourceDir . $file . DIRECTORY_SEPARATOR, $topLevelOnly, true);
				} elseif ($file[0] !== '.') {
					$fileData[$file]                  = self::getFileInfo($sourceDir . $file);
					$fileData[$file]['relative_path'] = $relativePath;
				}
			}

			closedir($fp);

			return $fileData;
		} catch (Throwable) {
			return [];
		}
	}

	/**
	 * Get File Info
	 *
	 * Given a file and path, returns the name, path, size, date modified
	 * Second parameter allows you to explicitly declare what information you want returned
	 * Options are: name, server_path, size, date, readable, writable, executable, fileperms
	 * Returns false if the file cannot be found.
	 *
	 * @param string $file Path to file
	 * @param array|string $returnedValues Array or comma separated string of information returned
	 *
	 * @return ?array
	 * @throws Exception
	 */
	public static function getFileInfo(string $file, array|string $returnedValues = ['name', 'server_path', 'size', 'date']): ?array {
		if (! is_file($file)) {
			return null;
		}

		$fileInfo = [];

		if (is_string($returnedValues)) {
			$returnedValues = explode(',', $returnedValues);
		}

		foreach ($returnedValues as $key) {
			switch ($key) {
				case 'name':
					$fileInfo['name'] = basename($file);
					break;

				case 'server_path':
					$fileInfo['server_path'] = $file;
					break;

				case 'size':
					$fileInfo['size'] = filesize($file);
					break;

				case 'date':
					$fileInfo['date'] = filemtime($file);
					break;

				case 'readable':
					$fileInfo['readable'] = is_readable($file);
					break;

				case 'writable':
					$fileInfo['writable'] = self::isWriteable($file);
					break;

				case 'executable':
					$fileInfo['executable'] = is_executable($file);
					break;

				case 'fileperms':
					$fileInfo['fileperms'] = fileperms($file);
					break;
			}
		}

		return $fileInfo;
	}

	/**
	 * Reads the specified directory and builds an array containing the filenames.
	 * Any sub-folders contained within the specified path are read as well.
	 *
	 * @param string    $sourceDir   Path to source
	 * @param bool|null $includePath Whether to include the path as part of the filename; false for no path, null for a relative path, true for full path
	 * @param bool      $hidden      Whether to include hidden files (files beginning with a period)
	 * @param bool      $includeDir  Whether to include directories
	 */
	public static function getFiles(string $sourceDir, ?bool $includePath = false, bool $hidden = false, bool $includeDir = true): array {
		$files = [];

		$sourceDir = realpath($sourceDir) ?: $sourceDir;
		$sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		try {
			foreach (new RecursiveIteratorIterator(
						 new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
						 RecursiveIteratorIterator::SELF_FIRST
					 ) as $name => $object) {
				$basename = pathinfo($name, PATHINFO_BASENAME);
				if (! $hidden && $basename[0] === '.') {
					continue;
				}

				if ($includeDir || ! $object->isDir()) {
					if ($includePath === false) {
						$files[] = $basename;
					} elseif ($includePath === null) {
						$files[] = str_replace($sourceDir, '', $name);
					} else {
						$files[] = $name;
					}
				}
			}
		} catch (Throwable) {
			return [];
		}

		sort($files);

		return $files;
	}

	/**
	 * Symbolic Permissions
	 *
	 * Takes a numeric value representing a file's permissions and returns
	 * standard symbolic notation representing that value
	 *
	 * @param int $perms Permissions
	 */
	public static function symbolicPermission(int $perms): string {
		if (($perms & 0xC000) === 0xC000) {
			$symbolic = 's'; // Socket
		} elseif (($perms & 0xA000) === 0xA000) {
			$symbolic = 'l'; // Symbolic Link
		} elseif (($perms & 0x8000) === 0x8000) {
			$symbolic = '-'; // Regular
		} elseif (($perms & 0x6000) === 0x6000) {
			$symbolic = 'b'; // Block special
		} elseif (($perms & 0x4000) === 0x4000) {
			$symbolic = 'd'; // Directory
		} elseif (($perms & 0x2000) === 0x2000) {
			$symbolic = 'c'; // Character special
		} elseif (($perms & 0x1000) === 0x1000) {
			$symbolic = 'p'; // FIFO pipe
		} else {
			$symbolic = 'u'; // Unknown
		}

		// Owner
		$symbolic .= (($perms & 0x0100) ? 'r' : '-')
			. (($perms & 0x0080) ? 'w' : '-')
			. (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));

		// Group
		$symbolic .= (($perms & 0x0020) ? 'r' : '-')
			. (($perms & 0x0010) ? 'w' : '-')
			. (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));

		// World
		$symbolic .= (($perms & 0x0004) ? 'r' : '-')
			. (($perms & 0x0002) ? 'w' : '-')
			. (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));

		return $symbolic;
	}

	/**
	 * Octal Permissions
	 *
	 * Takes a numeric value representing a file's permissions and returns
	 * a three character string representing the file's octal permissions
	 *
	 * @param int $perms Permissions
	 */
	public static function octalPermissions(int $perms): string {
		return substr(sprintf('%o', $perms), -3);
	}

	/**
	 * Force Download
	 *
	 * Generates headers that force a download to happen
	 *
	 * @param string $filename filename
	 * @param mixed $data the data to be downloaded
	 * @param bool $setMime whether to try and send the actual file MIME type
	 */
	public static function foreDownload(string $filename = '', mixed $data = '', bool $setMime = false): void {
		if ($filename === '' OR $data === '')
		{
			return;
		}
		elseif ($data === null)
		{
			if ( ! @is_file($filename) OR ($filesize = @filesize($filename)) === false)
			{
				return;
			}

			$filepath = $filename;
			$filename = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $filename));
			$filename = end($filename);
		}
		else
		{
			$filesize = strlen($data);
		}

		// Set the default MIME type to send
		$mime = 'application/octet-stream';

		$x = explode('.', $filename);
		$extension = end($x);

		if ($setMime === TRUE)
		{
			if (count($x) === 1 OR $extension === '')
			{
				/* If we're going to detect the MIME type,
				 * we'll need a file extension.
				 */
				return;
			}

			// Load the mime types
			$mimes = Mimes::MIME_TYPES;

			// Only change the default MIME if we can find one
			if (isset($mimes[$extension]))
			{
				$mime = is_array($mimes[$extension]) ? $mimes[$extension][0] : $mimes[$extension];
			}
		}

		/* It was reported that browsers on Android 2.1 (and possibly older as well)
		 * need to have the filename extension upper-cased in order to be able to
		 * download it.
		 *
		 * Reference: http://digiblog.de/2011/04/19/android-and-the-download-file-headers/
		 */
		if (count($x) !== 1 && isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Android\s(1|2\.[01])/', $_SERVER['HTTP_USER_AGENT']))
		{
			$x[count($x) - 1] = strtoupper($extension);
			$filename = implode('.', $x);
		}

		if ($data === null && ($fp = @fopen($filepath, 'rb')) === false)
		{
			return;
		}

		// Clean output buffer
		if (ob_get_level() !== 0 && @ob_end_clean() === false)
		{
			@ob_clean();
		}

		// Generate the server headers
		header('Content-Type: '.$mime);
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Expires: 0');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: '.$filesize);
		header('Cache-Control: private, no-transform, no-store, must-revalidate');

		// If we have raw data - just dump it
		if ($data !== null)
		{
			exit($data);
		}

		// Flush 1MB chunks of data
		while ( ! feof($fp) && ($data = fread($fp, 1048576)) !== false)
		{
			echo $data;
		}

		fclose($fp);
		exit;
	}

}