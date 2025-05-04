<?php
namespace WPBench;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the WPBench Settings Page and Options management.
 */
class WPBenchSettings {

	/** The option name where all settings are stored as an array. */
	const OPTION_NAME = 'wpbench_options';

	/** The settings group name used in settings_fields(). */
	const SETTINGS_GROUP = 'wpbench_settings_group';

	/** The slug for the settings page. */
	const PAGE_SLUG = 'wpbench_settings';

	const SETTING_STATUS = 'status';
	const SETTING_DELETE_RESULTS = 'delete_on_uninstall_benchmark_results';
	const SETTING_DELETE_PROFILES = 'delete_on_uninstall_benchmark_profiles';

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_action('admin_menu', [$this, 'add_settings_page']);
		add_action('admin_init', [$this, 'register_settings_api']);
	}

	/**
	 * Get the default values for all settings.
	 * Static method to allow easy access from helper function.
	 *
	 * @return array Default settings.
	 */
	public static function get_defaults() : array {
		return [
			self::SETTING_STATUS          => 'enabled',
			self::SETTING_DELETE_RESULTS  => 'no', // Default: Do not delete results
			self::SETTING_DELETE_PROFILES => 'no', // Default: Do not delete profiles
		];
	}

	/**
	 * Retrieve all WPBench options, merged with defaults.
	 *
	 * @return array Merged options.
	 */
	public function get_options() : array {
		$saved_options = get_option(self::OPTION_NAME, []);
		$defaults = self::get_defaults();

		// Ensure saved options are an array before parsing
		if (!is_array($saved_options)) {
			$saved_options = [];
		}

		return wp_parse_args($saved_options, $defaults);
	}

	/**
	 * Add the settings page under the main WPBench menu.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'wpbench_main_menu',                       // Parent slug (main WPBench menu)
			__('WPBench Settings', 'wpbench'),         // Page title
			__('Settings', 'wpbench'),                 // Menu title
			'manage_options',                          // Capability required
			self::PAGE_SLUG,                           // Menu slug
			[$this, 'render_settings_page']            // Callback function to render the page
		);
	}

	/**
	 * Register settings, sections, and fields using the Settings API.
	 * Hooked to 'admin_init'.
	 */
	public function register_settings_api() {
		// Register the single option array where all settings are stored
		register_setting(
			self::SETTINGS_GROUP,           // Option group used in settings_fields()
			self::OPTION_NAME,              // Option name in wp_options table
			[$this, 'sanitize_settings']    // Sanitization callback function
		);

		// Add General Settings Section
		add_settings_section(
			'wpbench_general_section',              // Section ID
			__('General Settings', 'wpbench'),      // Title displayed above the section
			[$this, 'render_general_section_callback'], // Callback for section introduction text (optional)
			self::PAGE_SLUG                         // Page slug where this section appears
		);

            // Add Status Field
            add_settings_field(
                'wpbench_field_status',                 // Field ID
                __('WPBench Status', 'wpbench'),        // Label for the field
                [$this, 'render_status_field_callback'],// Callback function to render the input control
                self::PAGE_SLUG,                        // Page slug
                'wpbench_general_section',              // Section ID this field belongs to
                ['label_for' => 'wpbench_field_status_select'] // Associate label with the select input
            );

		// --- Advanced Section ---
		add_settings_section(
			'wpbench_advanced_section',                 // Section ID
			__('Advanced Settings', 'wpbench'),         // Title
			[$this, 'render_advanced_section_callback'],// Section description callback
			self::PAGE_SLUG                             // Page slug
		);

            // Note: Both fields below are part of the same logical question in the UI
            add_settings_field(
                'wpbench_field_delete_on_uninstall',        // Field group ID
                __('Data Deletion on Uninstall', 'wpbench'), // Label for the group
                [$this, 'render_delete_options_callback'],  // Callback to render BOTH radio groups
                self::PAGE_SLUG,
                'wpbench_advanced_section'
            );

		// Add future fields here, linking them to sections
		// add_settings_field( 'wpbench_field_other', __('Other Setting', 'wpbench'), ... );
	}

	/**
	 * Render introductory text for the General section (optional).
	 *
	 * @param array $args Arguments passed from add_settings_section.
	 */
	public function render_general_section_callback($args) {
		// Example: Output some text below the section title
		echo '<p>' . esc_html__('Configure the main operational status of WPBench.', 'wpbench') . '</p>';
	}

	/**
	 * Render the input field for the 'status' setting (Dropdown).
	 *
	 * @param array $args Arguments passed from add_settings_field.
	 */
	public function render_status_field_callback($args) {
		$options = $this->get_options();
		$current_value = $options['status'] ?? 'enabled'; // Default to 'enabled' if not set
		$field_id = 'wpbench_field_status_select'; // Match label_for in add_settings_field
		$field_name = self::OPTION_NAME . '[status]'; // Name attribute for saving in the option array

		?>
		<select id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>">
			<option value="enabled" <?php selected($current_value, 'enabled'); ?>>
				<?php esc_html_e('Enabled', 'wpbench'); ?>
			</option>
			<option value="disabled" <?php selected($current_value, 'disabled'); ?>>
				<?php esc_html_e('Disabled', 'wpbench'); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e('Enable or disable the core benchmarking functionality.', 'wpbench'); ?>
			<?php // TODO: Explain what "Disabled" means (e.g., hides menus, stops AJAX?). ?>
		</p>
		<?php
	}

	/** Render introductory text for the Advanced section. */
	public function render_advanced_section_callback($args) {
		echo '<p>' . esc_html__('Configure actions performed when the plugin is deleted via the WordPress admin area.', 'wpbench') . '</p>';
		echo '<p><strong>' . esc_html__('Warning:', 'wpbench') . '</strong> ' . esc_html__('Enabling data deletion is permanent and cannot be undone.', 'wpbench') . '</p>';
	}

	/**
	 * Render the radio buttons for the data deletion options.
	 * Renders both sets of radio buttons under one field registration.
	 */
	public function render_delete_options_callback($args) {
		$options = $this->get_options();
		$delete_results_val = $options[self::SETTING_DELETE_RESULTS] ?? 'no';
		$delete_profiles_val = $options[self::SETTING_DELETE_PROFILES] ?? 'no';

		// Count posts safely
		$results_count = 0;
		$results_counts_obj = wp_count_posts(AdminBenchmark::POST_TYPE);
		if ($results_counts_obj) {
			$results_count = $results_counts_obj->publish + $results_counts_obj->draft + $results_counts_obj->pending + $results_counts_obj->private + $results_counts_obj->trash;
		}

		$profiles_count = 0;
		$profiles_counts_obj = wp_count_posts(BenchmarkProfileAdmin::POST_TYPE);
		if ($profiles_counts_obj) {
			$profiles_count = $profiles_counts_obj->publish + $profiles_counts_obj->draft + $profiles_counts_obj->pending + $profiles_counts_obj->private + $profiles_counts_obj->trash;
		}

		$results_field_name = self::OPTION_NAME . '[' . self::SETTING_DELETE_RESULTS . ']';
		$profiles_field_name = self::OPTION_NAME . '[' . self::SETTING_DELETE_PROFILES . ']';

		?>
        <fieldset>
            <legend class="screen-reader-text"><?php esc_html_e('Data Deletion Options', 'wpbench'); ?></legend>
            <p><?php esc_html_e('On plugin uninstallation, should the plugin delete:', 'wpbench'); ?></p>

            <div style="margin-bottom: 15px; margin-left: 10px;">
                <label style="display: block; margin-bottom: 5px;">
					<?php printf(esc_html__('All Benchmark Results? You currently have %d Benchmark Results.', 'wpbench'), (int)$results_count); ?>
                </label>
                <label style="margin-right: 15px;">
                    <input type="radio" name="<?php echo esc_attr($results_field_name); ?>" value="yes" <?php checked($delete_results_val, 'yes'); ?>>
					<?php esc_html_e('Yes', 'wpbench'); ?>
                </label>
                <label>
                    <input type="radio" name="<?php echo esc_attr($results_field_name); ?>" value="no" <?php checked($delete_results_val, 'no'); ?>>
					<?php esc_html_e('No', 'wpbench'); ?>
                </label>
            </div>

            <div style="margin-left: 10px;">
                <label style="display: block; margin-bottom: 5px;">
					<?php printf(esc_html__('All Benchmark Profiles? You currently have %d Benchmark Profiles.', 'wpbench'), (int)$profiles_count); ?>
                </label>
                <label style="margin-right: 15px;">
                    <input type="radio" name="<?php echo esc_attr($profiles_field_name); ?>" value="yes" <?php checked($delete_profiles_val, 'yes'); ?>>
					<?php esc_html_e('Yes', 'wpbench'); ?>
                </label>
                <label>
                    <input type="radio" name="<?php echo esc_attr($profiles_field_name); ?>" value="no" <?php checked($delete_profiles_val, 'no'); ?>>
					<?php esc_html_e('No', 'wpbench'); ?>
                </label>
            </div>
        </fieldset>
		<?php
	}

	/**
	 * Sanitize the settings array before saving.
	 *
	 * @param array $input Raw input data from $_POST for the option group.
	 * @return array Sanitized array to be saved.
	 */
	public function sanitize_settings($input) : array {
		// Get existing options to merge with new input, preventing loss of unsubmitted settings
		$existing_options = $this->get_options();
		$sanitized_output = $existing_options; // Start with existing values

		if (!is_array($input)) {
			// Input should be an array, if not, return existing without changes
			add_settings_error(
				self::OPTION_NAME,
				'invalid_input',
				__('Invalid settings input data received.', 'wpbench'),
				'error'
			);

			return $sanitized_output;
		}

		// Sanitize Status
		if (isset($input[self::SETTING_STATUS])) {
			$status_value = sanitize_key($input[self::SETTING_STATUS]);
			$sanitized_output[self::SETTING_STATUS] = in_array($status_value, ['enabled', 'disabled']) ? $status_value : self::get_defaults()[self::SETTING_STATUS];
		}

		// Sanitize Delete Results Option
		if (isset($input[self::SETTING_DELETE_RESULTS])) {
			$delete_results_value = sanitize_key($input[self::SETTING_DELETE_RESULTS]);
			$sanitized_output[self::SETTING_DELETE_RESULTS] = ($delete_results_value === 'yes') ? 'yes' : 'no'; // Default to 'no' if not 'yes'
		} else {
			// Ensure default if not present in submission
			$sanitized_output[self::SETTING_DELETE_RESULTS] = $existing_options[self::SETTING_DELETE_RESULTS] ?? self::get_defaults()[self::SETTING_DELETE_RESULTS];
		}

		// Sanitize Delete Profiles Option
		if (isset($input[self::SETTING_DELETE_PROFILES])) {
			$delete_profiles_value = sanitize_key($input[self::SETTING_DELETE_PROFILES]);
			$sanitized_output[self::SETTING_DELETE_PROFILES] = ($delete_profiles_value === 'yes') ? 'yes' : 'no';
		} else {
			$sanitized_output[self::SETTING_DELETE_PROFILES] = $existing_options[self::SETTING_DELETE_PROFILES] ?? self::get_defaults()[self::SETTING_DELETE_PROFILES];
		}


		// Add success message only if no errors were added during sanitization
		$errors = get_settings_errors(self::OPTION_NAME);
		$has_errors = false;

		foreach ($errors as $error) {
			if ($error['type'] === 'error') {
				$has_errors = true;
				break;
			}
		}

		if (!$has_errors) {
			add_settings_error(self::OPTION_NAME, 'settings_saved', __('Settings saved.', 'wpbench'), 'updated');
		}

		return $sanitized_output;
	}

	/**
	 * Render the HTML for the settings page.
	 * Callback for add_submenu_page.
	 */
	public function render_settings_page() {
		// Check user capabilities
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}

		?>
		<div class="wrap wpbench-settings-wrap">
		<h1><?php echo esc_html(get_admin_page_title()); ?></h1>

		<?php // Display settings errors/update messages
		settings_errors( self::OPTION_NAME ); // Use option name slug here, or custom slug if preferred
		?>

		<form method="post" action="options.php">
			<?php
			// Output necessary hidden fields (nonce, action, option_page)
			settings_fields(self::SETTINGS_GROUP);

			// Output settings sections and their fields
			do_settings_sections(self::PAGE_SLUG);

			// Output save settings button
			submit_button(__('Save Settings', 'wpbench'));
			?>
		</form>
		</div><?php
	}

}