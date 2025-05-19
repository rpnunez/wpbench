<?php
namespace WPBench\Storage;

use BaseStorageDriver;
use WPBench\Helpers\RandomStringTypes;
use WPBench\Helpers\Utility;

/**
 * A cache storage implementation using an array to temporarily store key-value pairs
 * with optional time-to-live (TTL) and associated metadata.
 */
class ArrayCacheStorage implements BaseStorageDriver {

	/**
	 *
	 */
	private array $cache = [];

	private string $instanceKey;

	/**
	 * Constructor method that initializes the instance with a unique key.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->instanceKey = Utility::get_random_string(RandomStringTypes::Alphanumeric);
	}

	/**
	 * Normalizes the provided key by appending the instance-specific key.
	 *
	 * @param string $key The key to be normalized.
	 *
	 * @return string Returns the normalized key with the instance-specific suffix.
	 */
	public function normalizeKey($key) {
		return $key . '_' . $this->instanceKey;
	}

	/**
	 * Retrieves an item from the cache if it exists, otherwise stores it with a specified time-to-live and returns the value.
	 *
	 * @param string $key The unique identifier for the cache item.
	 * @param mixed $value The value to be stored in the cache if the key does not exist.
	 * @param int $ttl The time-to-live for the cache item, in seconds.
	 *
	 * @return mixed The cached value or the newly stored value.
	 */
	public function remember( string $key, mixed $value, int $ttl) {
		$key = $this->normalizeKey($key);

		return $this->get($key) ?? $this->set($key, $value, $ttl);
	}

	/**
	 * Stores a value in the cache with a specified key and time-to-live (TTL).
	 *
	 * @param string $key The unique identifier for the cache entry.
	 * @param mixed $value The data to be stored in the cache.
	 * @param int $ttl The time-to-live for the cache entry, in seconds.
	 *
	 * @return mixed Cache data.
	 */
	public function set(string $key, mixed $value, int $ttl): mixed {
		$key = $this->normalizeKey($key);

		$this->cache[$key] = [
			'data' => $value,
			'ttl' => $ttl,
			'created' => time(),
			'expires' => time() + $ttl,
			'tags' => ['default' ]
		];

		return $this->cache[$key]['data'];
	}

	/**
	 * Retrieves an item from the cache based on the provided key.
	 *
	 * @param string $key The key identifying the cached item.
	 *
	 * @return mixed|null The cached item if it exists and has not expired, or null if it does not exist or has expired.
	 */
	public function get( string $key): mixed {
		$key = $this->normalizeKey($key);

		if (isset($this->cache[$key]) && $item = $this->cache[$key]) {
			if (time() > $item['expires']) {
				$this->delete($key);
			} else {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Deletes an item from the cache based on the provided key.
	 *
	 * @param string $key The key identifying the cached item to be deleted.
	 *
	 * @return bool True if the key was successfully deleted.
	 */
	public function delete(string $key) {
		$key = $this->normalizeKey($key);

		unset($this->cache[$key]);

		return true;
	}

	/**
	 * Clears the cache and resets it to an empty state.
	 *
	 * @return bool Returns true upon successful cache flush.
	 */
	public function flush() {
		$this->cache = [];

		return true;
	}

	/**
	 * Closes the current instance by flushing its contents.
	 *
	 * @return void Does not return any value.
	 */
	public function close() {
		$this->flush();
	}

	/**
	 * Establishes a connection.
	 *
	 * @return bool Returns true if the connection is successfully established, false otherwise.
	 */
	public function connect() {

	}

	public function isConnected() {

	}

	public function getStats() {

	}
}