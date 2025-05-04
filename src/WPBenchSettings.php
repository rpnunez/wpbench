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
			'status' => 'enabled', // Default status for the plugin functionality
			// Add other future settings here with their defaults
			// 'some_other_setting' => 'default_value',
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

		// --- Sanitize Status ---
		if (isset($input['status'])) {
			$status_value = sanitize_key($input['status']); // Use sanitize_key for simple slugs

			if (in_array($status_value, ['enabled', 'disabled'])) {
                $sanitized_output['status'] = $status_value;
			} else {
                // Invalid value submitted, revert to default or existing? Revert to default.
                $sanitized_output['status'] = self::get_defaults()['status'];
                add_settings_error(
                    self::OPTION_NAME,
                    'invalid_status',
                    __('Invalid value selected for WPBench Status. Reverted to default.', 'wpbench'),
                    'warning' // Use warning as we corrected it
                );
			}
		} else {
			// If 'status' wasn't submitted (e.g., field disabled/removed), keep the existing value
			// $sanitized_output['status'] = $existing_options['status']; // Already handled by starting with existing
		}

		// --- Sanitize Other Future Settings ---
		// if (isset($input['some_other_setting'])) {
		//    $sanitized_output['some_other_setting'] = sanitize_text_field($input['some_other_setting']);
		// }

		// Add success message
		add_settings_error(
			self::OPTION_NAME,
			'settings_saved', // Slug-like ID
			__('Settings saved successfully.', 'wpbench'),
			'updated' // 'updated' for success, 'error' for errors, 'warning'
		);

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