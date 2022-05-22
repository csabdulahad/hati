<?php

namespace hati\uploader;

/**
 * This class got its name from the inspiration of those working men in train stations,
 * bus stations and sea/river ports in Bangladesh who lift very heavy weight every
 * single day to earn their bread and butter.
 *
 * This class simplifies file uploading operation with various configuration settings as
 * defined in the HatiConfig file. It is able to deal with single and multi file uploading.
 *
 * Various settings such as size limit, storing folder can be set dynamically.
 *
 * */

use hati\Hati;
use hati\trunk\TrunkErr;
use hati\Util;
use Throwable;

class Kuli {

    private string $rootFolder;
    private bool $uniqueName;
    private bool $triggerError;

    // This holds FileInfo object for storing various file relate information
    // such as tempName, size, ext etc. On upload, Kuli tries to acquire these
    // information.
    private ?array $fileInfo = [];

    // It holds name of the file with folder where it has been moved to after
    // the upload. This array is used to delete uploads on required setting
    // where any of the files encounters any error. This can also be uses to
    // get the file name where unique file name is used.
    private array $movedFileName = [];

    // various file type configurations; loaded upon construction.
    private array $docConfig;
    private array $imgConfig;
    private array $videoConfig;
    private array $audioConfig;

    // if this is set then this path will be used in storing the files.
    private ?string $folder = null;

    // It is used when it is set by setter method.
    private float $maxSize = -1;

    /**
     * This method can be dynamically be set. When it is set the Kuli
     * uses the specified directory or folder for uploads. It can be
     * recursive folder structure.
     *
     * @param string $folder the folder name.
     * */
    public function setFolder(string $folder) {
        $this -> folder = $folder;
    }

    /**
     * When this method sets the file size limit in Mega Bytes, Kuli ignores
     * other file configuration for calculating the max size limit.
     *
     * @param float $mb The limit for file max size.
     * */
    public function setMaxSize(float $mb) {
        $this -> maxSize = $mb * 1000000;
    }

    public function __construct(bool $uniqueName = false, bool $throwErr = false) {
        $this -> uniqueName = $uniqueName;
        $this -> triggerError = $throwErr;
        $this -> rootFolder = Hati::docRoot();
        $this -> loadConfig();
    }

    /**
     * After successful file upload, uploaded files information can be obtained by this method.
     * It returns information such as file name, stored folder name in array.
     *
     * Folder name has no trailing directory separators.
     *
     * @return array Array containing two associative arrays namely name and folder for the
     * uploaded files.
     * */
    public function getFileInfo(): array {
        $array = [
            'folder' => [],
            'name'   => [],
            'path'   => [],
        ];

        foreach ($this -> movedFileName as $item) {
            $index = strripos( $item, DIRECTORY_SEPARATOR);
            if (!$index) continue;

            $folder = substr($item, 0, $index);
            $name = substr($item, $index + 1);

            array_push($array['folder'], $folder);
            array_push($array['name'], $name);
            array_push($array['path'], $folder . DIRECTORY_SEPARATOR . $name);
        }

        return $array;
    }

    /**
     * When this method is invoked, it performs various checks on the file form value.
     * First it makes sure that no empty files or unselected files were submitted.
     * Then it calculates the various file information and store in structure @link FileInfo
     *
     * It can work with single or multi file uploads. Like many others library methods of
     * Hati, it can be configured to throw error during object construction. If required
     * argument is set, then for multi file uploads it make sure all the files pass the
     * check and are uploaded successfully. If not set, then it tries to process uploading
     * all the files.
     *
     * On encountering any error for multi files where the required setting being on, it
     * deletes all the files before encountering the error.
     *
     * If single file is submitted, then it returns 1 on successful file upload; otherwise
     * returns 0 indicating false result.
     *
     * For multiple file uploads it returns number of file it was able to upload; on false
     * it returns 0.
     *
     * @param string $nameKey The form file name value.
     * @param bool $required When set it makes sure all the files are passing the check before
     * upload. Otherwise failure files were ignored & number of successfully uploaded is returned.
     * */
    public function load(string $nameKey, bool $required = true) : int {

        // reset the moved fileName stack for unlinking later if any exception
        // occurs on required upload
        $this -> movedFileName = [];

        // check whether any file was selected or an empty file value was submitted
        if (!isset($_FILES[$nameKey]) || empty($_FILES[$nameKey]['name'])) {
            $this -> throwError('No files were selected.');
            return 0;
        }

        $file = $_FILES[$nameKey];
        $singleFile = !is_array($file['name']);

        // get the files meta/info
        if ($singleFile) $this -> prepareFileInfo($file);
        else for ($i = 0; $i < count($file['name']); $i++) $this -> prepareFileInfo($file, $i);

        // check whether zero file was submitted
        if (count($this -> fileInfo) == 0) {
            $this -> throwError('No files were submitted.');
            return 0;
        }

        // try to upload and increment the counter. On failure reacts as per configuration
        $uploadCount = 0;
        foreach ($this -> fileInfo as $file) {
            try {
                $result = $this -> upload($file);
                
                if ($result) $uploadCount ++;
                if ($singleFile) return $uploadCount;

                if (!$result && $required) {
                    $this -> deleteUpload();
                    return 0;
                }
            } catch (Throwable $error) {
                if (!$required) continue;
                $this -> deleteUpload();
                $this -> throwError($error -> getMessage());
                return 0;
            }
        }

        return $uploadCount;
    }

    // This method is internally used to upload file. It performs various checks such as
    // file extension, file size. It returns true when the file is successfully uploaded
    // and renamed with uniques based on the configuration; false otherwise.
    // It keeps track of successfully uploaded file with folder name, file name with extension.
    private function upload(FileInfo $file): bool {
        // make sure we have supported file type
        if ($file -> type == FileInfo::TYPE_UNKNOWN) {
            $this -> throwError('Unsupported file was submitted');
            return 0;
        }

        // check if the file size is under limit
        if (!$this -> scanFileSize($file)) {
            $this -> throwError('File size is too big.');
            return 0;
        }

        // construct the folder where the file is to be moved to
        // create the dir if it doesn't exist
        $folder = $this -> rootFolder . $file -> folder;
        if (!file_exists($folder)) {
            if (!mkdir($folder, recursive: true)) {
                $this -> throwError('Failed to create folder for the file.');
                return 0;
            }
        }

        // upload the file
        $lastName = $folder . DIRECTORY_SEPARATOR . $file -> name;
        if (!move_uploaded_file($file -> tempName, $lastName)) {
            $this -> throwError('Failed to moved file to directory');
            return 0;
        }

        // add the file to the array to say we have uploaded the file
        array_push($this -> movedFileName, $file -> folder . DIRECTORY_SEPARATOR . $file -> name);

        // check for unique name and store the uploaded file path with folder and file name
        if ($this -> uniqueName) {
            $newName = $file -> folder . DIRECTORY_SEPARATOR . Util::uniqueId() . '.' .  $file -> ext;
            if (!rename($lastName, $newName)) {
                $this -> throwError('Failed to rename the file.');
                return 0;
            }

            // store the moved file name either unique or original name
            $this -> updateLastFileName($newName);
        }

        return true;
    }

    // This method updates the last item of the uploaded file information containing
    // folder name followed by directory separator and the file name with extension.
    // It becomes very useful to delete uploaded files where required setting is on
    // and one of the files was failed to upload.
    private function updateLastFileName(string $newName) {
        $lastIndex = count($this -> movedFileName) - 1;
        if ($lastIndex < 0) return;
        $this -> movedFileName[$lastIndex] = $newName;
    }

    private function deleteUpload() {
        foreach ($this -> movedFileName as $file) {
            $file = Hati::neutralizeSeparator(Hati::docRoot() . $file);
            unlink($file);
        }
    }

    private function scanFileSize(FileInfo $fileInfo): bool {
        if ($this -> maxSize != -1)
            return $fileInfo -> size <= $this -> maxSize;

        if ($fileInfo -> type == FileInfo::TYPE_DOC)
            return $fileInfo -> size <= $this -> docConfig['size'] * 1000000;

        if ($fileInfo -> type == FileInfo::TYPE_IMG)
            return $fileInfo -> size <= $this -> imgConfig['size'] * 1000000;

        if ($fileInfo -> type == FileInfo::TYPE_VIDEO)
            return $fileInfo -> size <= $this -> videoConfig['size'] * 1000000;

        if ($fileInfo -> type == FileInfo::TYPE_AUDIO)
            return $fileInfo -> size <= $this -> audioConfig['size'] * 1000000;

        return false;
    }

    // It calculates the destination folder for the file and extension.
    private function getFolderAndType(FileInfo $fileInfo) {
        if (in_array($fileInfo -> ext, $this -> docConfig['ext'])) {
            if ($this -> folder == null) $fileInfo -> folder = $this -> docConfig['folder'];
            $fileInfo -> type = FileInfo::TYPE_DOC;
        } elseif (in_array($fileInfo -> ext, $this -> imgConfig['ext'])) {
            if ($this -> folder == null) $fileInfo -> folder = $this -> imgConfig['folder'];
            $fileInfo -> type = FileInfo::TYPE_IMG;
        } elseif (in_array($fileInfo -> ext, $this -> videoConfig['ext'])) {
            if ($this -> folder == null) $fileInfo -> folder = $this -> videoConfig['folder'];
            $fileInfo -> type = FileInfo::TYPE_VIDEO;
        } elseif (in_array($fileInfo -> ext, $this -> audioConfig['ext'])) {
            if ($this -> folder == null) $fileInfo -> folder = $this -> audioConfig['folder'];
            $fileInfo -> type = FileInfo::TYPE_AUDIO;
        }

        if ($this -> folder != null) $fileInfo -> folder = (string) $this -> folder;
    }

    // This method runs over all the files from the form data and calculates
    // various information such as name, temp name, size etc. and keeps the
    // info into file info array.
    private function prepareFileInfo($file, $index = -1) {
        $singleFile = $index == -1;

        $name = $singleFile ? basename($file['name']) : basename($file['name'][$index]);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $tempName = $singleFile ? $file['tmp_name'] : $file['tmp_name'][$index];
        $size = filesize($tempName);

        $fileInfo = new FileInfo();
        $fileInfo -> name = $name;
        $fileInfo -> tempName = $tempName;
        $fileInfo -> ext = $ext;
        $fileInfo -> size = $size;
        $this -> getFolderAndType($fileInfo);
        array_push($this -> fileInfo, $fileInfo);
    }

    private function loadConfig() {
        $this -> docConfig = Hati::docConfig();
        $this -> imgConfig = Hati::imgConfig();
        $this -> videoConfig = Hati::videoConfig();
        $this -> audioConfig = Hati::audioConfig();

        // trim any whitespace from the extension list
        $this -> trimExt($this -> docConfig);
        $this -> trimExt($this -> imgConfig);
        $this -> trimExt($this -> videoConfig);
        $this -> trimExt($this -> audioConfig);

        // neutralize the directory separator in folder path
        $this -> docConfig['folder'] = Hati::neutralizeSeparator($this -> docConfig['folder']);
        $this -> imgConfig['folder'] = Hati::neutralizeSeparator($this -> imgConfig['folder']);
        $this -> videoConfig['folder'] = Hati::neutralizeSeparator($this -> videoConfig['folder']);
        $this -> audioConfig['folder'] = Hati::neutralizeSeparator($this -> audioConfig['folder']);
    }

    // This method is used internally to trim any extra whitespaces from the
    // comma separated extension string.
    private function trimExt(array &$configArray): void {
        $extArray = &$configArray['ext'];
        for ($i = 0; $i < count($extArray); $i++){
            $extArray[$i] = trim($extArray[$i]);
        }
    }

    // this throws error if the trigger is turned on.
    private function throwError(string $throwMsg): void {
        if ($this -> triggerError) throw new TrunkErr($throwMsg);
    }

}