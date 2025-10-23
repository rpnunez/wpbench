# WPBench

**Contributors:** Your Name Here
**Tags:** benchmark, performance, speed, test, stress test, optimization
**Requires at least:** 5.2
**Tested up to:** 6.0
**Requires PHP:** 7.4
**Stable tag:** 1.0.2
**License:** GPL v2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Plugin that benchmarks and stress-tests your current WordPress site, allowing you to see what's slowing down your website.

## Description

WPBench is a powerful benchmarking tool for WordPress. It allows you to run a series of tests on your site to measure its performance. You can select which tests to run, and even which plugins to have active or inactive during the benchmark. This helps you identify performance bottlenecks and understand the impact of different plugins on your site's speed.

## Features

*   **Comprehensive Benchmarking:** Run a variety of tests to measure CPU performance, database queries, file operations, and more.
*   **Selective Plugin Testing:** Activate or deactivate specific plugins during a benchmark to measure their individual impact on performance.
*   **Benchmark Profiles:** Save your benchmark configurations (tests, plugin states) as profiles to easily run them again later.
*   **Result Comparison:** (Coming Soon) Compare the results of different benchmark runs to track performance changes over time.
*   **Detailed Results:** View detailed results for each test, including execution time and other relevant metrics.

## Installation

1.  Upload the `wpbench` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to the "WPBench" -> "Run New Benchmark" page to start your first benchmark.

## How to Use

1.  **Navigate to WPBench:** After activating the plugin, you'll find a "WPBench" menu in your WordPress admin dashboard.
2.  **Run a New Benchmark:**
    *   Go to "WPBench" -> "Run New Benchmark".
    *   **Benchmark Name:** Give your benchmark a descriptive name.
    *   **Desired Plugin State:** Select which plugins should be active or inactive during the test.
    *   **Select Tests to Run:** Choose the specific tests you want to include in the benchmark.
    *   **Test Configuration:** Configure the parameters for each selected test (e.g., number of queries for a database test).
    *   Click "Start Benchmark".
3.  **View Results:** Once the benchmark is complete, the results will be displayed on the page. You can also view past results from the "WPBench" -> "Benchmark Results" page.
4.  **Use Profiles (Optional):**
    *   To save a configuration for later use, go to "WPBench" -> "Profiles" and create a new profile.
    *   You can then load this profile on the "Run New Benchmark" page to quickly set up a test with your saved settings.

## Available Benchmark Tests

WPBench comes with a variety of tests to measure different aspects of your site's performance:

*   **CPU:** Measures the speed of basic CPU operations.
*   **DB Heavy Load:** Simulates a heavy load on the database.
*   **DB Read:** Measures the speed of reading from the database.
*   **DB Write:** Measures the speed of writing to the database.
*   **File:** Measures the speed of file system operations.
*   **Memory:** Measures memory allocation and usage.
*   **WP Actions/Filters/Hooks:** Measures the performance of WordPress hooks.
*   **WP HTTP:** Measures the speed of outgoing HTTP requests.
*   **WP Options:** Measures the performance of reading and writing to the `wp_options` table.
*   **WP Query:** Measures the performance of `WP_Query`.
*   **WP Shortcode Rendering:** Measures the time it takes to render shortcodes.
*   **WP Transients:** Measures the performance of the Transients API.

## Screenshots

*(Coming Soon)*

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

GPL v2 or later
