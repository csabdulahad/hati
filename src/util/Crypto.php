<?php

namespace hati\util;

use InvalidArgumentException;

/**
 * A utility class, containing helpful functions to perform cryptographic operations.
 *
 * @since 5.0.0
 * */

abstract class Crypto {

	const ALGO_BCRYPT = '2y';
	const ALGO_2Y = '2y';
	const ALGO_ARGON2I = 'argon2i';
	const ALGO_ARGONI2ID = 'argon2id';
	const ALGO_MD5 = 'md5';

	/**
	 * Hash any string value with specified algorithm. It supports hashing with the following algorithms: md5,
	 * bcypto, 2y, argon2i, argon2id. There are defined constant for each type with ALGO prefix in Crypto class.
	 *
	 * @param string $value Any string value to be hashed
	 * @param string $algo The algorithm to be used for hashing
	 * @return string Hashed value
	 * */
	public static function hash(string $value, string $algo = self::ALGO_BCRYPT): string {
		$allowed = [
			self::ALGO_BCRYPT, self::ALGO_2Y, self::ALGO_ARGON2I,
			self::ALGO_ARGONI2ID, self::ALGO_MD5
		];

		if (!in_array($algo, $allowed))
			throw new InvalidArgumentException('Invalid argument. Allowed algorithms are: md5, bcypto, 2y, argon2i, argon2id');

		if ($algo === 'md5') return md5($value);
		return password_hash($value, $algo);
	}

}