<?php

namespace hati\uploader;

/*
 * This data object class is used by the Kuli class in storing various file
 * information in the memory.
 * */

class FileInfo {

	// supported file types
	public const TYPE_UNKNOWN = -1;
	public const TYPE_DOC = 1;
	public const TYPE_IMG = 2;
	public const TYPE_VIDEO = 3;
	public const TYPE_AUDIO = 4;

	// file related information
	public string $name;
	public string $tempName;
	public string $ext;
	public int $size;
	public int $type = self::TYPE_UNKNOWN;
	public string $folder;

}