<?php

namespace WPBench\Storage;

interface BaseStorageDriver {
	public function remember($key, $callback, $ttl);
	public function set($key, $value, $ttl);
	public function get($key);
	public function delete($key);
	public function flush();
	public function close();
	public function connect();
	public function isConnected();
	public function getStats();
}