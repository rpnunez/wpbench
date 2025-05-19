<?php
namespace WPBench\BenchmarkTest;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class File
 *
 * Implements a benchmark test for file I/O operations. This benchmark performs repeated file write and read
 * operations to measure performance metrics such as time taken, bytes written, bytes read, and the number
 * of completed operations.
 */
class File implements BaseBenchmarkTest {

	/**
	 * Retrieves the singleton instance of the File class.
	 *
	 * @return File|null The singleton instance of the File class, or null if an instance is not created.
	 */
	public function getInstance(): ?File {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get descriptive information about the File I/O test.
	 *
	 * @return array Test details.
	 */
	public function getInfo() : array {
		return [
			'id'            => 'file_io',
			'name'          => __('File I/O Test', 'wpbench'),
			'description'   => __('Performs repeated file write and read operations.', 'wpbench'),
			'config_label'  => __('File I/O Operations', 'wpbench'),
			'config_unit'   => __('write/read cycles', 'wpbench'),
			'default_value' => 100,
			'min_value'     => 10,
			'max_value'     => 5000,
			'instance'      => $this->getInstance()
		];
	}

    /**
     * Run the File I/O benchmark test.
     *
     * @param int $value Number of write/read operations.
     * @return array Results including 'time', 'operations', 'bytes_written', 'bytes_read', 'error'.
     */
	public function run(int $value): array {
		$iterations = absint($value);

		if ($iterations <= 0) {
			return $this->buildResult(0, 0, 0, 0, 'Invalid iteration count.');
		}

		$startTime = microtime(true);
		$errorMessages = [];
		$bytesWritten = 0;
		$bytesRead = 0;
		$operations = 0;

		// Validate and get temporary file path
		$tempFile = $this->validateUploadDirectory($errorMessages);
		if ($tempFile === null) {
			return $this->buildResult(0, 0, 0, 0, implode('; ', $errorMessages));
		}

		$dummyData = str_repeat("0123456789abcdef", 64); // 1KB data chunk
		$dataLen = strlen($dummyData);

		try {
			for ($i = 0; $i < $iterations; $i++) {
				// Write data to temporary file
				$written = $this->writeToFile($tempFile, $dummyData, $dataLen, $errorMessages);
				if ($written !== null) {
					$bytesWritten += $written;
				}
				$operations++; // Count write operation

				// Read data from temporary file
				$readData = $this->readFromFile($tempFile, $dataLen, $errorMessages);
				if ($readData !== null) {
					$bytesRead += strlen($readData);
					if ($readData !== $dummyData) {
						$errorMessages[] = "Read data mismatch on iteration $i. Possible corruption or race condition.";
					}
				}
				$operations++; // Count read operation
			}
		} catch (\Exception $exception) {
			$errorMessages[] = $exception->getMessage();
		} finally {
			// Ensure the temporary file is deleted even if errors occur
			$this->deleteTempFile($tempFile, $errorMessages);
		}

		return $this->buildResult(
			round(microtime(true) - $startTime, 4),
			$operations,
			$bytesWritten,
			$bytesRead,
			empty($errorMessages) ? null : implode('; ', $errorMessages)
		);
	}

	private function validateUploadDirectory(array &$errorMessages): ?string {
		$uploadDir = wp_upload_dir();

		if (!$uploadDir || !empty($uploadDir['error']) || !wp_is_writable($uploadDir['basedir'])) {
			$errorMessages[] = 'Upload directory is not writable or accessible. ' . ($uploadDir['error'] ?? 'Check permissions.');
			return null;
		}

		return trailingslashit($uploadDir['basedir']) . 'wpbench_temp_file_' . uniqid() . '.txt';
	}

	private function writeToFile(string $filePath, string $data, int $dataLen, array &$errorMessages): ?int {
		$fh = @fopen($filePath, 'w');
		if (!$fh) {
			$lastError = error_get_last();
			$errorMessages[] = 'fopen (write) failed: ' . ($lastError['message'] ?? 'Unknown reason.');
			return null;
		}

		$written = fwrite($fh, $data);
		fclose($fh);

		if ($written === false) {
			$errorMessages[] = 'fwrite failed.';
			return null;
		}

		if ($written !== $dataLen) {
			$errorMessages[] = "fwrite wrote partial data ($written / $dataLen bytes). Disk full?";
			return null;
		}

		return $written;
	}

	private function readFromFile(string $filePath, int $dataLen, array &$errorMessages): ?string {
		$fh = @fopen($filePath, 'r');
		if (!$fh) {
			$lastError = error_get_last();
			$errorMessages[] = 'fopen (read) failed: ' . ($lastError['message'] ?? 'Unknown reason.');
			return null;
		}

		$data = fread($fh, $dataLen + 10); // Read slightly more to test EOF
		fclose($fh);

		if ($data === false) {
			$errorMessages[] = 'fread failed.';
			return null;
		}

		return $data;
	}

	private function deleteTempFile(string $filePath, array &$errorMessages): void {
		if (file_exists($filePath) && !@unlink($filePath)) {
			$errorMessages[] = 'Failed to delete temporary file: ' . $filePath;
		}
	}

	public function buildResult(float $time, int $operations, int $bytesWritten, int $bytesRead, ?string $error): array {
		return [
			'time' => $time,
			'operations' => $operations,
			'bytes_written' => $bytesWritten,
			'bytes_read' => $bytesRead,
			'error' => $error,
		];
	}

    public function run_old( $value ) : array {
        $iterations = absint($value);
         if ($iterations <= 0) {
            return ['time' => 0, 'operations' => 0, 'bytes_written' => 0, 'bytes_read' => 0, 'error' => 'Invalid iteration count.'];
        }

         $start = microtime(true);
         $upload_dir = wp_upload_dir();
         $error = null;
         $bytes_written = 0;
         $bytes_read = 0;
         $operations = 0;
         $temp_file = null; // Initialize

         // Check if basedir is writable before proceeding
         if ( ! $upload_dir || ! empty( $upload_dir['error'] ) || ! wp_is_writable( $upload_dir['basedir'] ) ) {
			$error = 'Upload directory is not writable or accessible. Error: ' . ($upload_dir['error'] ?? 'Check permissions.');

			// Bail early if directory isn't usable
			return [
				'time' => round(microtime(true) - $start, 4),
				'operations' => 0, 'bytes_written' => 0, 'bytes_read' => 0,
				'error' => $error
			];
         }

         $temp_file = trailingslashit($upload_dir['basedir']) . 'wpbench_temp_file_' . uniqid() . '.txt';
         $dummy_data = str_repeat("0123456789abcdef", 64); // 1KB data chunk
         $data_len = strlen($dummy_data);

        try {
            for ($i = 0; $i < $iterations; $i++) {
                // --- Write ---
                $fh_write = @fopen($temp_file, 'w');

                if (!$fh_write) {
                    $last_error = error_get_last();

                    throw new \Exception("fopen (write) failed: " . ($last_error['message'] ?? 'Unknown reason.'));
                }

                $written = fwrite($fh_write, $dummy_data);
                fclose($fh_write);
                $operations++; // Count write operation attempt

                if ($written === false) {
                     throw new \Exception("fwrite failed.");
                } elseif ($written !== $data_len) {
                     throw new \Exception("fwrite wrote partial data ($written / $data_len bytes). Disk full?");
                } else {
                    $bytes_written += $written;
                }

                // --- Read ---
                $fh_read = @fopen($temp_file, 'r');

                 if (!$fh_read) {
                     $last_error = error_get_last();

                     throw new \Exception("fopen (read) failed: " . ($last_error['message'] ?? 'Unknown reason.'));
                 }

                // Read exactly the amount written or up to a reasonable max
                $read_data = fread($fh_read, $data_len + 10); // Read slightly more to test EOF
                fclose($fh_read);
                $operations++; // Count read operation attempt

                 if ($read_data === false) {
                     throw new \Exception("fread failed.");
                 } else {
                     $bytes_read += strlen($read_data);
                     // Optional: Verify content matches $dummy_data? Could slow down test.
                     if ($read_data !== $dummy_data) {
						 throw new \Exception("Read data mismatch. Possible file corruption or race condition?");
					 }
                 }
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        } finally {
             // Ensure temp file is deleted even if errors occurred
             if ($temp_file !== null && file_exists($temp_file)) {
                @unlink($temp_file);
             }
        }

        return [
            'time' => round(microtime(true) - $start, 4),
            'operations' => $operations, // Total actual operations attempted/completed
            'bytes_written' => $bytes_written,
            'bytes_read' => $bytes_read,
            'error' => $error
        ];
    }
}