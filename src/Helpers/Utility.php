<?php

namespace WPBench\Helpers;

class Utility {
	/**
	 * Retrieves and processes the post IDs from the request.
	 *
	 * @return array An array of unique, valid post IDs.
	 */
	public static function get_cleaned_post_ids(array $ids): array {
		$raw_ids = isset($ids) ? sanitize_text_field(wp_unslash($ids)) : '';

		if (empty($raw_ids)) {
			return [];
		}

		$ids_array = array_map('absint', explode(',', $raw_ids));
		$filtered_ids = array_filter($ids_array); // Remove invalid IDs (e.g., 0).

		return array_unique($filtered_ids);
	}

	public static function get_random_string($length, RandomStringTypes $type = RandomStringTypes::Alphanumeric) {
		// Ensure $type is a valid item in the RandomStringTypeEnum
		$characters = str_shuffle($type->value);
		$numCharacters = strlen($characters);

		return substr( str_shuffle( str_repeat( $characters, $length ) ), 0, $length );

	}

}