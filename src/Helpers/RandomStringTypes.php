<?php

namespace WPBench\Helpers;

/**
 * Enum RandomStringTypes
 *
 * Represents different types of random string generation options.
 * The available options are:
 * - Alphanumeric: Includes both letters and numbers.
 * - Numeric: Includes only numbers.
 * - Alpha: Includes only letters.
 */
enum RandomStringTypes: string {
	case Alphanumeric = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	case Numeric = '0123456789';
	case Alpha = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
}