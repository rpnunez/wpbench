<?php

namespace src\Helpers;

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

}