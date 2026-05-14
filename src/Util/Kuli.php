<?php

namespace Hati\Util;

use Hati\Trunk;
use Throwable;

/**
 * This class got its name from the inspiration of those working men in train stations,
 * bus stations and sea/river ports in Bangladesh who lift very heavy weight every
 * single day to earn their bread and butter.
 *
 * Kuli handles uploaded files and moves them from temporary upload storage to a
 * configured destination folder.
 *
 * Upload rules are extension-based. Each allowed extension may define its own
 * maximum size in MB. If an extension is listed without a size, the default
 * maximum size is used.
 *
 * Example rules:
 * <code>
 * ['jpg', 'png', 'pdf' => 10, 'mp4' => 50]
 * </code>
 */
class Kuli
{
	
	private const DEFAULT_MAX_SIZE_MB = 5;
	private const MB = 1000000;
	
	private string $folder;
	
	private bool $uniqueName;
	private bool $throwError;
	
	private array $rules;
	private array $uploadedFiles = [];
	
	/**
	 * Creates a new uploader instance.
	 *
	 * The folder is the destination directory where uploaded files will be stored.
	 * The folder is created automatically during upload if it does not exist.
	 *
	 * Rules define allowed extensions and size limits in MB.
	 *
	 * Supported rule styles:
	 * ```
	 *  ['jpg', 'png']
	 *  ['jpg' => 2, 'png' => 3]
	 *  ['jpg' => 2, 'png', 'mp4' => 10]
	 * ```
	 * Extensions listed without a size use the default max size of 5 MB.
	 *
	 * @param string $folder Destination folder for uploaded files.
	 * @param array $rules Allowed extension rules.
	 * @param bool $uniqueName Whether uploaded files should be stored with generated unique names.
	 * @param bool $throwError Whether upload failures should throw Trunk.
	 */
	public function __construct(string $folder, array $rules, bool $uniqueName = false, bool $throwError = false)
	{
		$this->folder = $this->normalizeFolder($folder);
		
		$this->rules = $this->normalizeRules($rules);
		$this->uniqueName = $uniqueName;
		$this->throwError = $throwError;
	}
	
	private function normalizeFolder(string $folder): string
	{
		$folder = trim($folder);
		
		if ($folder === '') {
			throw new Trunk('Upload folder cannot be empty.');
		}
		
		$folder = rtrim($folder, '/\\');
		
		if ($folder === '') {
			throw new Trunk('Upload folder cannot be root.');
		}
		
		return $folder;
	}
	
	private function normalizeRules(array $rules): array
	{
		if ($rules === []) {
			throw new Trunk('Upload rules cannot be empty.');
		}
		
		$normalized = [];
		
		foreach ($rules as $key => $value) {
			if (is_int($key)) {
				if (!is_string($value)) {
					throw new Trunk('Upload extension must be a string.');
				}
				
				$ext = $this->normalizeExtension($value);
				$normalized[$ext] = self::DEFAULT_MAX_SIZE_MB * self::MB;
				continue;
			}
			
			if (!is_string($key)) {
				throw new Trunk('Upload extension must be a string.');
			}
			
			if (!is_int($value) && !is_float($value)) {
				throw new Trunk("Upload size for extension '$key' must be a number.");
			}
			
			if ($value <= 0) {
				throw new Trunk("Upload size for extension '$key' must be greater than zero.");
			}
			
			$ext = $this->normalizeExtension($key);
			$normalized[$ext] = (int) ceil($value * self::MB);
		}
		
		return $normalized;
	}
	
	private function normalizeExtension(string $ext): string
	{
		$ext = strtolower(trim($ext));
		$ext = ltrim($ext, '.');
		
		if ($ext === '') {
			throw new Trunk('Upload extension cannot be empty.');
		}
		
		if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $ext)) {
			throw new Trunk("Invalid upload extension '$ext'.");
		}
		
		return $ext;
	}
	
	private function normalizeUploadArray(array $file): array
	{
		if (!array_key_exists('name', $file)) {
			return [];
		}
		
		$defaults = [
			'name'     => '',
			'type'     => null,
			'tmp_name' => '',
			'error'    => UPLOAD_ERR_NO_FILE,
			'size'     => 0,
		];
		
		$data = [];
		
		foreach ($defaults as $key => $default) {
			$value = $file[$key] ?? $default;
			$data[$key] = is_array($value) ? $value : [$value];
		}
		
		$files = [];
		$count = count($data['name']);
		
		for ($i = 0; $i < $count; $i++) {
			$files[] = [
				'name'     => (string) ($data['name'][$i] ?? ''),
				'type'     => $data['type'][$i] ?? null,
				'tmp_name' => (string) ($data['tmp_name'][$i] ?? ''),
				'error'    => (int) ($data['error'][$i] ?? UPLOAD_ERR_NO_FILE),
				'size'     => (int) ($data['size'][$i] ?? 0),
			];
		}
		
		return $files;
	}
	
	/**
	 * Returns uploaded file information.
	 *
	 * The returned array contains three lists:
	 *
	 * - folder: destination folder of each uploaded file
	 * - name: stored file name of each uploaded file
	 * - path: full stored path of each uploaded file
	 *
	 * Folder paths do not contain a trailing directory separator.
	 *
	 * @return array
	 */
	public function getFileInfo(): array
	{
		$array = [
			'folder' => [],
			'name'   => [],
			'path'   => [],
		];

		foreach ($this->uploadedFiles as $file) {
			$array['folder'][] = $file['folder'];
			$array['name'][] = $file['name'];
			$array['path'][] = $file['path'];
		}

		return $array;
	}
	
	/**
	 * Returns detailed information about successfully uploaded files.
	 *
	 * Each item contains:
	 * - folder
	 * - name
	 * - original_name
	 * - path
	 * - ext
	 * - size
	 * - type
	 *
	 * @return array
	 */
	public function getFiles(): array
	{
		return $this->uploadedFiles;
	}
	
	/**
	 * Loads and stores one or more uploaded files.
	 *
	 * The file array must be passed explicitly. Kuli does not read from $_FILES
	 * directly. This keeps it usable in classic PHP, PHP-FPM, OpenSwoole, tests,
	 * and other runtimes.
	 *
	 * Expected input is a PHP-like upload array:
	 *
	 * [
	 *     'name' => 'photo.jpg',
	 *     'type' => 'image/jpeg',
	 *     'tmp_name' => '/tmp/php123',
	 *     'error' => 0,
	 *     'size' => 12345,
	 * ]
	 *
	 * Multi-file upload arrays are also supported:
	 *
	 * [
	 *     'name' => ['a.jpg', 'b.jpg'],
	 *     'type' => ['image/jpeg', 'image/jpeg'],
	 *     'tmp_name' => ['/tmp/php1', '/tmp/php2'],
	 *     'error' => [0, 0],
	 *     'size' => [123, 456],
	 * ]
	 *
	 * When $required is true, all files must upload successfully. If one file fails,
	 * previously uploaded files from the same call are deleted and the method returns 0,
	 * or throws Trunk when throwError is enabled.
	 *
	 * When $required is false, failed files are ignored and the method returns the
	 * number of successfully uploaded files.
	 *
	 * @param array $file PHP/OpenSwoole-style uploaded file array.
	 * @param bool $required Whether all submitted files must pass validation and upload.
	 * @return int Number of successfully uploaded files.
	 */
	public function load(array $file, bool $required = true): int
	{
		$this->uploadedFiles = [];
		
		if ($file === []) {
			$this->handleError('No files were selected.');
			return 0;
		}
		
		$files = $this->normalizeUploadArray($file);
		
		if ($files === []) {
			$this->handleError('No files were submitted.');
			return 0;
		}
		
		$uploadCount = 0;
		
		foreach ($files as $item) {
			try {
				if ($this->uploadOne($item)) {
					$uploadCount++;
				}
			} catch (Trunk $error) {
				if (!$required) {
					continue;
				}
				
				$this->deleteUploadedFiles();
				$this->handleError($error->getMessage());
				return 0;
			}
		}

		return $uploadCount;
	}

	private function deleteUploadedFiles(): void
	{
		foreach ($this->uploadedFiles as $file) {
			$path = $file['path'] ?? null;
			
			if (is_string($path) && is_file($path)) {
				unlink($path);
			}
		}
		
		$this->uploadedFiles = [];
	}

	private function handleError(string $message): void
	{
		if ($this->throwError) {
			throw new Trunk($message);
		}
	}
	
	private function assertUploadOk(array $file): void
	{
		$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
		
		if ($error === UPLOAD_ERR_OK) {
			return;
		}
		
		$message = match ($error) {
			UPLOAD_ERR_INI_SIZE,
			UPLOAD_ERR_FORM_SIZE => 'Uploaded file is too large.',
			UPLOAD_ERR_PARTIAL => 'Uploaded file was only partially uploaded.',
			UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
			UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory.',
			UPLOAD_ERR_CANT_WRITE => 'Failed to write uploaded file to disk.',
			UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
			default => 'Unknown upload error.',
		};
		
		throw new Trunk($message);
	}
	
	private function getAllowedSizeForExtension(string $ext): int
	{
		if (!isset($this->rules[$ext])) {
			throw new Trunk("Unsupported file extension '$ext'.");
		}
		
		return $this->rules[$ext];
	}
	
	private function assertFileSize(array $file, string $ext): void
	{
		$size = (int) ($file['size'] ?? 0);
		$maxSize = $this->getAllowedSizeForExtension($ext);
		
		if ($size <= 0) {
			throw new Trunk('Uploaded file is empty.');
		}
		
		if ($size > $maxSize) {
			throw new Trunk("File size is too big for '$ext'.");
		}
	}
	
	private function makeStoredName(string $originalName, string $ext): string
	{
		if ($this->uniqueName) {
			return $this->makeUniqueName($ext);
		}
		
		$name = basename($originalName);
		$name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name) ?? '';
		
		if ($name === '' || $name === '.' || $name === '..') {
			throw new Trunk('Invalid uploaded file name.');
		}
		
		return $name;
	}
	
	// In classic PHP, uploaded files should be moved using move_uploaded_file().
	// In OpenSwoole or tests, the temporary file may not be recognized by PHP's
	// upload mechanism, so rename() is used as a runtime-neutral fallback.
	private function moveFile(string $tmpName, string $targetPath): void
	{
		if ($tmpName === '' || !is_file($tmpName)) {
			throw new Trunk('Temporary uploaded file does not exist.');
		}
		
		if (is_uploaded_file($tmpName)) {
			$moved = move_uploaded_file($tmpName, $targetPath);
		} else {
			$moved = rename($tmpName, $targetPath);
		}
		
		if (!$moved) {
			throw new Trunk('Failed to move uploaded file.');
		}
	}
	
	private function ensureFolderExists(): void
	{
		if (is_dir($this->folder)) {
			return;
		}
		
		if (!mkdir($this->folder, 0775, true) && !is_dir($this->folder)) {
			throw new Trunk('Failed to create upload folder.');
		}
	}
	
	private function uploadOne(array $file): bool
	{
		$this->assertUploadOk($file);
		
		$originalName = basename((string) ($file['name'] ?? ''));
		$tmpName = (string) ($file['tmp_name'] ?? '');
		
		if ($originalName === '') {
			throw new Trunk('Uploaded file name is empty.');
		}
		
		$ext = $this->normalizeExtension(pathinfo($originalName, PATHINFO_EXTENSION));
		
		$this->assertFileSize($file, $ext);
		$this->ensureFolderExists();
		
		$storedName = $this->makeStoredName($originalName, $ext);
		$targetPath = $this->folder . DIRECTORY_SEPARATOR . $storedName;
		
		if (file_exists($targetPath)) {
			throw new Trunk("Target file already exists: '$storedName'.");
		}
		
		$this->moveFile($tmpName, $targetPath);
		
		$this->uploadedFiles[] = [
			'folder' => $this->folder,
			'name' => $storedName,
			'original_name' => $originalName,
			'path' => $targetPath,
			'ext' => $ext,
			'size' => (int) ($file['size'] ?? 0),
			'type' => $file['type'] ?? null,
		];
		
		return true;
	}
	
	private function makeUniqueName(string $ext): string
	{
		try {
			return bin2hex(random_bytes(16)) . '.' . $ext;
		} catch (Throwable) {
			$uid = str_replace('.', '_', Util::uniqueId());
			return $uid . '.' . $ext;
		}
	}
	
}