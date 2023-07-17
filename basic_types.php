<?php

/*
 * Plugin Name: basic_types
 * Plugin URI: https://seventy9.co.uk/wp-plugins
 * Description: custom post/taxonomy stuff
 * Author: Scott A. Dixon
 * Author URI: https://seventy9.co.uk
 * Version: 1.0.2
*/

defined('ABSPATH') or die('⎺\_(ツ)_/⎺');

// defines

define('_PLUGIN_BASIC_TYPES', 'basic_types');
define('_PREFIX_BASIC_TYPES', 'bt');

define('_URL_BASIC_TYPES', plugin_dir_url(__FILE__));
define('_PATH_BASIC_TYPES', plugin_dir_path(__FILE__));

//   ▄████████   ▄██████▄   ███▄▄▄▄▄       ▄████████  
//  ███    ███  ███    ███  ███▀▀▀▀██▄    ███    ███  
//  ███    █▀   ███    ███  ███    ███    ███    █▀   
//  ███         ███    ███  ███    ███   ▄███▄▄▄      
//  ███         ███    ███  ███    ███  ▀▀███▀▀▀      
//  ███    █▄   ███    ███  ███    ███    ███         
//  ███    ███  ███    ███  ███    ███    ███         
//  ████████▀    ▀██████▀    ▀█    █▀     ███         

// basic_types args

define('_ARGS_BASIC_TYPES', [
	'bt_active' => [
		'type' => 'string',
		'default' => 'yes'
	],
	'bt_title' => [
		'type' => 'string',
		'default' => ''
	],
	'bt_posts' => [
		'type' => 'string',
		'default' => '{}'
	],
	'bt_taxes' => [
		'type' => 'string',
		'default' => '{}'
	],
	'bt_roles' => [
		'type' => 'string',
		'default' => '{}'
	]
]);

// basic_types admin 

define('_ADMIN_BASIC_TYPES', [
	'general' => [
		'label' => 'General',
		'columns' => 4,
		'fields' => [
			'bt_active' => [
				'label' => 'Active',
				'type' => 'check'
			],
			'bt_title' => [
				'label' => 'Title',
				'type' => 'input'
			]
		]
	],
	'posts' => [
		'label' => 'Posts Config',
		'columns' => 1,
		'fields' => [
			'bt_posts' => [
				'label' => 'Posts JSON',
				'type' => 'code'
			]
		]
	],
	'taxes' => [
		'label' => 'Taxonomies Config',
		'columns' => 1,
		'fields' => [
			'bt_taxes' => [
				'label' => 'Taxonomies JSON',
				'type' => 'code'
			]
		]
	],
	'roles' => [
		'label' => 'Roles Config',
		'columns' => 1,
		'fields' => [
			'bt_roles' => [
				'label' => 'Roles JSON',
				'type' => 'code'
			]
		]
	]
]);

// basic_types api routes

define('_APIPATH_BASIC_TYPES',
	'settings'
);

define('_API_BASIC_TYPES', [
	[
		'methods' => 'POST',
		'callback' => 'update_settings',
		'args' => _btSettings::args(),
		'permission_callback' => 'permissions'
	],
	[
		'methods' => 'GET',
		'callback' => 'get_settings',
		'args' => [],
		'permission_callback' => 'permissions'
	]
]);

//   ▄████████   ▄█           ▄████████     ▄████████     ▄████████  
//  ███    ███  ███          ███    ███    ███    ███    ███    ███  
//  ███    █▀   ███          ███    ███    ███    █▀     ███    █▀   
//  ███         ███          ███    ███    ███           ███         
//  ███         ███        ▀███████████  ▀███████████  ▀███████████  
//  ███    █▄   ███          ███    ███           ███           ███  
//  ███    ███  ███▌    ▄    ███    ███     ▄█    ███     ▄█    ███  
//  ████████▀   █████▄▄██    ███    █▀    ▄████████▀    ▄████████▀ 

class bt {
	public static $message = null;
}

class _btAPI {
	public function add_routes() {
		if (count(_API_BASIC_TYPES)) {

			foreach(_API_BASIC_TYPES as $route) {
				register_rest_route(_PLUGIN_BASIC_TYPES . '-api/v1', '/' . _APIPATH_BASIC_TYPES, [
					'methods' => $route['methods'],
					'callback' => [$this, $route['callback']],
					'args' => $route['args'],
					'permission_callback' => [$this, $route['permission_callback']]
				]);
			}
		}
	}

	public function permissions() {
		return current_user_can('manage_options');
	}

	public function update_settings(WP_REST_Request $request) {
		$settings = [];

		foreach (_btSettings::args() as $key => $val) {
			$settings[$key] = $request->get_param($key);
		}
		_btSettings::save_settings($settings);
		return rest_ensure_response(_btSettings::get_settings());
	}

	public function get_settings(WP_REST_Request $request) {
		return rest_ensure_response(_btSettings::get_settings());
	}
}

class _btSettings {
	protected static $option_key = _PLUGIN_BASIC_TYPES . '-settings';

	public static function args() {
		$args = _ARGS_BASIC_TYPES;

		foreach (_ARGS_BASIC_TYPES as $key => $val) {
			$val['required'] = true;

			switch ($val['type']) {
				case 'integer': {
					$cb = 'absint';
					break;
				}
				default: {
					$cb = 'sanitize_text_field';
				}
				$val['sanitize_callback'] = $cb;
			}
		}
		return $args;
	}

	public static function get_settings() {
		$defaults = [];

		foreach (_ARGS_BASIC_TYPES as $key => $val) {
			$defaults[$key] = $val['default'];
		}
		$saved = get_option(self::$option_key, []);

		if (!is_array($saved) || empty($saved)) {
			return $defaults;
		}
		return wp_parse_args($saved, $defaults);
	}

	public static function save_settings(array $settings) {
		$defaults = [];

		foreach (_ARGS_BASIC_TYPES as $key => $val) {
			$defaults[$key] = $val['default'];
		}

		foreach ($settings as $i => $setting) {
			if (!array_key_exists($i, $defaults)) {
				unset($settings[$i]);
			}

			if (in_array($i, ['bt_posts', 'bt_taxes', 'bt_roles'])) {
				$settings[$i] = json_encode(
					json_decode($setting),
					JSON_PRETTY_PRINT
				);
			}
		}
		update_option(self::$option_key, $settings);
	}
}

class _btMenu {
	protected $slug = _PLUGIN_BASIC_TYPES . '-menu';
	protected $assets_url;

	public function __construct($assets_url) {
		$this->assets_url = $assets_url;
		add_action('admin_menu', [$this, 'add_page']);
		add_action('admin_enqueue_scripts', [$this, 'register_assets']);
	}

	public function add_page() {
		$title = (_BT['bt_title'] != '') ? _BT['bt_title'] : _PLUGIN_BASIC_TYPES;
		add_menu_page(
			$title,
			$title,
			'manage_options',
			$this->slug,
			[$this, 'render_admin'],
			'data:image/svg+xml;base64,' . base64_encode(
				'<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="500px" height="500px" viewbox="0 0 500 500"><path fill="#a7aaad" d="M250,9.8L42,129.9v240.2l208,120.1l208-120.1V129.9L250,9.8z M152.2,242.3c-13.1,1.1-26.5,0.7-39.6,0.3 c0.2-38.4,0-76.7,0.1-115.1c63.5,0.2,127.1,0.1,190.7,0.1c0.1,13.3,0.1,26.5,0,39.8c-50.2,0-100.5,0.1-150.7,0 C152.5,192.3,153.5,217.4,152.2,242.3z M302.6,397.8c-13.1-0.4-26.3,0-39.4-0.2c0.1-39.9,0-79.7,0-119.6c13.3,0.2,26.6,0,39.9,0.1 C302.8,318,303.9,358,302.6,397.8z M378.6,242.8c-50.2-0.7-100.5,0.1-150.7-0.4c-0.1,51.7,0,103.4-0.1,155.1 c-13.2,0.3-26.5,0.1-39.7,0.1c-0.1-65-0.1-129.9,0-194.9c50.2,0,100.4,0,150.7,0c0-25.1,0-50.1,0-75.2c13.3,0.1,26.6,0.1,39.9,0	C378.6,166,378.8,204.4,378.6,242.8z"/></svg>'
			),
			3
		);

		// add config submenu

		add_submenu_page(
			$this->slug,
			'Configuration',
			'Configuration',
			'manage_options',
			$this->slug
		);

		// add custom taxonomies submenus

		if (bt_check_var(_TAXES_BASIC_TYPES)) {
			foreach (_TAXES_BASIC_TYPES as $tax => $type) {
				$plural = bt_plural(str_replace('_', ' ', $tax));
				add_submenu_page(
					$this->slug,
					ucwords($plural),
					ucwords($plural),
					'manage_options',
					'/edit-tags.php?taxonomy=' . $tax
				);
			}			
		}

		// add custom posts submenus

		if (bt_check_var(_POSTS_BASIC_TYPES)) {
			foreach (_POSTS_BASIC_TYPES as $type => $data) {
				$plural = bt_plural(str_replace('_', ' ', $type));

				add_submenu_page(
					$this->slug,
					ucwords($plural),
					ucwords($plural),
					'manage_options',
					'/edit.php?post_type=' . $type
				);
			}
		}
	}

	public function register_assets() {
		$boo = microtime(false);
		wp_register_script($this->slug, $this->assets_url . '/' . _PLUGIN_BASIC_TYPES . '.js?' . $boo, ['jquery']);
		wp_register_style($this->slug, $this->assets_url . '/' . _PLUGIN_BASIC_TYPES . '.css?' . $boo);
		wp_localize_script($this->slug, _PLUGIN_BASIC_TYPES, [
			'strings' => [
				'saved' => 'Settings Saved',
				'error' => 'Error'
			],
			'api' => [
				'url' => esc_url_raw(rest_url(_PLUGIN_BASIC_TYPES . '-api/v1/' . _APIPATH_BASIC_TYPES)),
				'nonce' => wp_create_nonce('wp_rest')
			]
		]);
	}

	public function enqueue_assets() {
		if (!wp_script_is($this->slug, 'registered')) {
			$this->register_assets();
		}
		wp_enqueue_script($this->slug);
		wp_enqueue_style($this->slug);
	}

	public function render_admin() {
		wp_enqueue_media();
		$this->enqueue_assets();

		$name = _PLUGIN_BASIC_TYPES;
		$form = _ADMIN_BASIC_TYPES;

		// build form

		echo '<div id="' . $name . '-wrap" class="wrap">';
			echo '<h1>' . $name . '</h1>';
			echo '<p>Configure your ' . $name . ' settings...</p>';
			echo '<form id="' . $name . '-form" method="post">';
				echo '<nav id="' . $name . '-nav" class="nav-tab-wrapper">';

				foreach ($form as $tid => $tab) {
					echo '<a href="#' . $name . '-' . $tid . '" class="nav-tab">' . $tab['label'] . '</a>';
				}
				echo '</nav>';
				echo '<div class="tab-content">';

				foreach ($form as $tid => $tab) {
					echo '<div id="' . $name . '-' . $tid . '" class="' . $name . '-tab">';

					foreach ($tab['fields'] as $fid => $field) {
						echo '<div class="form-block col-' . $tab['columns'] . '">';
						
						switch ($field['type']) {
							case 'input': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<input id="' . $fid . '" type="text" name="' . $fid . '">';
								break;
							}
							case 'select': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<select id="' . $fid . '" name="' . $fid . '">';
									foreach ($field['values'] as $value => $label) {
										echo '<option value="' . $value . '">' . $label . '</option>';
									}
								echo '</select>';
								break;
							}
							case 'text': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<textarea id="' . $fid . '" class="tabs" name="' . $fid . '"></textarea>';
								break;
							}
							case 'file': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<input id="' . $fid . '" type="text" name="' . $fid . '">';
								echo '<input data-id="' . $fid . '" type="button" class="button-primary choose-file-button" value="...">';
								break;
							}
							case 'colour': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<input id="' . $fid . '" type="text" name="' . $fid . '">';
								echo '<input data-id="' . $fid . '" type="color" class="choose-colour-button" value="#000000">';
								break;
							}
							case 'code': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<textarea id="' . $fid . '" class="code" name="' . $fid . '"></textarea>';
								break;
							}
							case 'check': {
								echo '<em>' . $field['label'] . ':</em>';
								echo '<label class="switch">';
									echo '<input type="checkbox" id="' . $fid . '" name="' . $fid . '" value="yes">';
									echo '<span class="slider"></span>';
								echo '</label>';
								break;
							}
						}
						echo '</div>';
					}
					echo '</div>';
				}
				echo '</div>';
				echo '<div>';
					submit_button();
				echo '</div>';
				echo '<div id="' . $name . '-feedback"></div>';
			echo '</form>';
		echo '</div>';
	}
}

//   ▄████████   ▄██████▄   ████████▄      ▄████████  
//  ███    ███  ███    ███  ███   ▀███    ███    ███  
//  ███    █▀   ███    ███  ███    ███    ███    █▀   
//  ███         ███    ███  ███    ███   ▄███▄▄▄      
//  ███         ███    ███  ███    ███  ▀▀███▀▀▀      
//  ███    █▄   ███    ███  ███    ███    ███    █▄   
//  ███    ███  ███    ███  ███   ▄███    ███    ███  
//  ████████▀    ▀██████▀   ████████▀     ██████████

// check vars

function bt_check_var($var) {
	$result = false;

	if (isset($var)) {
		if (is_array($var)) {
			if (count($var) > 0) {
				$result = true;
			}
		}
	}

	return $result;
}

// set screen/sub scripts

function bt_admin_scripts() {
	$screen = get_current_screen();

	if (null === $screen) {
		return;
	}
	if ($screen->base !== 'toplevel_page_' . _PLUGIN_BASIC_TYPES . '-menu') {
		return;
	}

	wp_enqueue_code_editor(['type' => 'application/x-httpd-php']);
}

// init api

function bt_api_init() {
	_btSettings::args();
	$api = new _btAPI();
	$api->add_routes();
}

// init plugin

function bt_init($dir) {
	// set up admin menu

	if (is_admin()) {
		new _btMenu(_URL_BASIC_TYPES);
	}

	// register roles

	if (bt_check_var(_ROLES_BASIC_TYPES)) {
		if (isset(_ROLES_BASIC_TYPES['remove'])) {
			foreach (_ROLES_BASIC_TYPES['remove'] as $role) {
				if (get_role($role)) {
					remove_role($role);
				}
			}			
		}

		if (isset(_ROLES_BASIC_TYPES['add'])) {
			foreach (_ROLES_BASIC_TYPES['add'] as $role => $details) {
				$capabilities = [];
				foreach ($details['capabilities'] as $cap) {
					$capabilities[$cap] = TRUE;
				}
				if (get_role($role)) {
					remove_role($role);
				}
				add_role($role, $details['label'], $capabilities);
			}			
		}
	}

	// register post types

	if (bt_check_var(_POSTS_BASIC_TYPES)) {
		foreach (_POSTS_BASIC_TYPES as $type => $data) {
			$uc_type = ucwords(str_replace('_', ' ', $type));
			$p_type = bt_plural($uc_type);

			$labels = [
				'name' => $p_type,
				'singular_name' => $uc_type,
				'menu_name' => $p_type,
				'name_admin_bar' => $p_type,
				'add_new' => 'Add New',
				'add_new_item' => 'Add New ' . $uc_type,
				'new_item' => 'New ' . $uc_type,
				'edit_item' => 'Edit ' . $uc_type,
				'view_item' => 'View ' . $uc_type,
				'all_items' => $p_type,
				'search_items' => 'Search ' . $p_type,
				'not_found' => 'No ' . $p_type . ' Found'
			];

			register_post_type($type, [
				'supports' => [
					'title'
				],
				'hierarchical' => true,
				'labels' => $labels,
				'show_ui' => true,
				'show_in_menu' => false,
				'query_var' => true,
				'has_archive' => false,
				'rewrite' => ['slug' => $type]
			]);
		}
	}

	// register taxonomies

	if (bt_check_var(_TAXES_BASIC_TYPES)) {
		foreach (_TAXES_BASIC_TYPES as $tax => $type) {
			$uc_tax = ucwords(str_replace('_', ' ', $tax));
			$p_tax = bt_plural($uc_tax);

			$labels = [
				'name' => $p_tax,
				'singular_name' => $uc_tax,
				'menu_name' => $p_tax,
				'search_items' => 'Search ' . $p_tax,
				'all_items' => 'All ' . $p_tax,
				'parent_item' => 'Parent ' . $uc_tax,
				'parent_item_colon' => 'Parent ' . $uc_tax . ':',
				'edit_item' => 'Edit ' . $uc_tax, 
				'update_item' => 'Update ' . $uc_tax,
				'add_new_item' => 'Add New ' . $uc_tax,
				'new_item_name' => 'New ' . $uc_tax . ' Name',
				'not_found' => 'No ' . $p_tax . ' found',
				'no_terms' => 'No ' . $p_tax
			];

			register_taxonomy($tax, $type, [
				'hierarchical' => true,
				'labels' => $labels,
				'show_ui' => true,
				'show_in_menu' => false,
				'show_in_rest' => true,
				'show_admin_column' => true,
				'query_var' => true,
				'rewrite' => ['slug' => $tax],
			]);
		}
	}
}

//     ▄███████▄   ▄██████▄      ▄████████      ███      
//    ███    ███  ███    ███    ███    ███  ▀█████████▄  
//    ███    ███  ███    ███    ███    █▀      ▀███▀▀██  
//    ███    ███  ███    ███    ███             ███   ▀  
//  ▀█████████▀   ███    ███  ▀███████████      ███      
//    ███         ███    ███           ███      ███      
//    ███         ███    ███     ▄█    ███      ███      
//   ▄████▀        ▀██████▀    ▄████████▀      ▄████▀

function bt_add_metaboxes() {
	foreach (_POSTS_BASIC_TYPES as $type => $fields) {
		add_meta_box(
			_PREFIX_BASIC_TYPES . '_meta_box',
			'Information',
			_PREFIX_BASIC_TYPES . '_post_metabox',
			$type
		);
	}
}

function bt_post_metabox($post) {
	$type = $post->post_type;

	$prefix = '_' . _PREFIX_BASIC_TYPES . '_' . $type . '_';
	$keys = _POSTS_BASIC_TYPES[$type];

	if (count($keys) > 0) {
		foreach ($keys as $key => $details) {
			$$key = get_post_meta($post->ID, $prefix . $key, true);
		}		
	}

	wp_nonce_field(plugins_url(__FILE__), 'wr_plugin_noncename');
	wp_enqueue_media();
?>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.min.css" rel="stylesheet">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.full.min.js"></script>
	<style>
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box em,
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box label {
			display: inline-block;
			width: 20%;
			font-weight: 700;
			font-style: normal;
			padding-top: 4px;
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box input,
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box select,
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box textarea {
			box-sizing: border-box;
			display: inline-block;
			width: 73%;
			padding: 3px;
			vertical-align: middle;
			margin-top: 10px;
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box span.desc {
			display: block;
			width: 18%;
			padding-top: 0px;
			clear: both;
			font-style: italic;
			font-size: 12px;
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box div.middle {
			margin-bottom: 10px;
			padding-bottom: 10px;
			border-bottom: 1px dashed #ddd;
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box div.top {
			margin-top: 10px;
			margin-bottom: 10px;
			padding-bottom: 10px;
			border-bottom: 1px dashed #ddd;
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box div.bottom {
			margin-bottom: 0;
			padding-bottom: 0;
			border-bottom: 0;
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box .switch {
			position: relative;
			display: inline-block;
			width: 50px;
			height: 24px;
			margin: 3px 0;
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box .switch input {
			opacity: 0;
			width: 0;
			height: 0;
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box .slider {
			position: absolute;
			cursor: pointer;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background-color: #ccc;
			transition: .4s;
			border-radius: 24px;
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box .slider:before {
			position: absolute;
			content: "";
			height: 20px;
			width: 20px;
			left: 4px;
			bottom: 4px;
			background-color: white;
			transition: .4s;
			border-radius: 50%;
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box input:checked + .slider {
			background-color: #2271b1;
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box input:focus + .slider {
			box-shadow: 0 0 1px #2271b1;
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box input:checked + .slider:before {
			transform: translateX(22px);
		}
		#<?php echo _PREFIX_BASIC_TYPES; ?>_meta_box .bt_data-view {
			display: inline-block;
			width: 73%;
			padding: 3px;
			margin-top: 10px;
		}

		.xdsoft_datetimepicker .xdsoft_label {
			font-weight: normal;
		}
	</style>
	<script>
		var $ = jQuery;
	</script>
	<div class="inside">
<?php
	$count = 1;

	foreach (_POSTS_BASIC_TYPES[$type] as $field => $keys) {

		// set box class
		switch ($count) {
			case count(_POSTS_BASIC_TYPES[$type]): {
				$class = 'bottom';
				break;
			}
			case 1: {
				$class = 'top';
				break;
			}
			default: {
				$class = 'middle';
			}
		}
?>
		<div class="<?php echo $class; ?>">
<?php
		$fid = _PREFIX_BASIC_TYPES . '_' . $type . '_' . $field;
		$fname = '_' . _PREFIX_BASIC_TYPES . '_' . $type . '_' . $field;
		$fval = $$field;

		if ($keys['linked']) {
			// this is a linked id field

			if ($keys['type'] == 'view') {
				// this is a view linked records field

				echo '<label>' . $keys['label'] . ':</label>';

				if ($keys['linked'] == 'user') {
					// view all linked users

					$roles = (strpos($keys['role'], ',') === true) ? explode(',', $keys['role']) : [$keys['role']];
					$role = (isset($keys['role'])) ? ['role__in' => $roles] : null;
					$users = get_users($role);

					if (count($users)) {
						foreach ($users as $user) {
							echo '<div id="user-' . $user->ID . '" class="bt_data-view">' . $user->display_name . '</div>';
						}
					}
					else {
						echo '<div id="user-none" class="bt_data-view">No data to view</div>';
					}
				}
				else {
					// view all linked records of 'type'

					$loop = get_posts([
						'post_type' => $keys['linked'],
						'post_status' => 'public',
						'posts_per_page' => '-1',
						'orderby' => 'title',
						'order' => 'ASC'
					]);

					if (count($loop) > 0) {
						foreach ($loop as $post) {
							if ($post->post_title != 'Auto Draft') {
								echo '<div value="' . $keys['linked'] . '-' . $post->ID . '">' . $post->post_title . '</div>';
							}
						}
					}
				}

				echo '<span class="desc">' . $keys['description'] . '</span>';
			}
			else {
				// this is a standard linked record field
?>
				<input type="hidden" id="<?php echo $fid; ?>" name="<?php echo $fname; ?>" value="<?php echo $fval; ?>">
<?php
				if ($keys['type'] == 'select') {
					// this field needs a select box
?>
				<label><?php echo $keys['label']; ?>:</label>
				<select id="<?php echo _PREFIX_BASIC_TYPES; ?>-<?php echo $keys['linked']; ?>">
					<option value="0">Select <?php echo ucwords(str_replace('_', ' ', $keys['linked'])); ?>&hellip;</option>
<?php
					if ($keys['linked'] == 'user') {
						$roles = (strpos($keys['role'], ',') === true) ? explode(',', $keys['role']) : [$keys['role']];
						$role = (isset($keys['role'])) ? ['role__in' => $roles] : null;
						$users = get_users($role);

						if (count($users)) {
							foreach ($users as $user) {
								$id = $user->ID;
								$selected = ($id == $fval) ? ' selected' : '';
								echo '<option value="' . $id . '"' . $selected . '>' . $user->display_name . '</option>';
							}
						}
					}
					else {
						$loop = get_posts([
							'post_type' => $keys['linked'],
							'post_status' => 'public',
							'posts_per_page' => '-1',
							'orderby' => 'title',
							'order' => 'ASC'
						]);

						if (count($loop) > 0) {
							foreach ($loop as $post) {
								$id = $post->ID;
								$selected = ($id == $fval) ? ' selected' : '';
								if ($post->post_title != 'Auto Draft') {
									echo '<option value="' . $id . '"' . $selected . '>' . $post->post_title . '</option>';
								}
							}
						}
					}
?>
				</select>
				<span class="desc"><?php echo $keys['description']; ?></span>
				<script>
					var <?php echo $field; ?>_select = $('#<?php echo _PREFIX_BASIC_TYPES; ?>-<?php echo $keys['linked']; ?>');
					var <?php echo $field; ?>_input = $('#<?php echo _PREFIX_BASIC_TYPES; ?>_<?php echo $type; ?>_<?php echo $field; ?>');
					<?php echo $field; ?>_select.on('change', function() {
						<?php echo $field; ?>_input.val($(this).val());
					});
				</script>
<?php
				}
			}
		}
		else {
			// this is a data field

			switch ($keys['type']) {
				case 'select': {
					echo '<label for="' . $fid . '">';
						echo $keys['label'] . ':';
					echo '</label>';
					echo '<select id="' . $fid . '" name="' . $fname . '">';
						echo '<option value="0">Select ' . $keys['label'] . '&hellip;</option>';
						foreach ($keys['values'] as $value => $label) {
							$selected = ($fval == $value) ? ' selected' : '';
							echo '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
						}
					echo '</select>';
					break;
				}
				case 'input': {
					echo '<label for="' . $fid . '">';
						echo $keys['label'] . ':';
					echo '</label>';
					echo '<input type="text" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '">';
					break;
				}
				case 'display': {
					echo '<label for="' . $fid . '">';
						echo $keys['label'] . ':';
					echo '</label>';
					echo '<input type="text" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '" readonly>';
					break;
				}
				case 'date': {
					echo '<label for="' . $fid . '">';
						echo $keys['label'] . ':';
					echo '</label>';
					echo '<input type="text" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '">';
					echo '<script>';
						echo '$("#' . $fid . '").datetimepicker({timepicker:false,format:"d-m-Y"});';
					echo '</script>';
					break;
				}
				case 'text': {
					echo '<label for="' . $fid . '">';
						echo $keys['label'] . ':';
					echo '</label>';
					echo '<textarea id="' . $fid . '" class="tabs" name="' . $fname . '">' . $fval . '</textarea>';
					break;
				}
				case 'file': {
					echo '<label for="' . $fid . '">';
						echo $keys['label'] . ':';
					echo '</label>';
					echo '<input id="' . $fid . '" type="text" name="' . $fname . '" value="' . $fval . '" style="width:68%">';
					echo '<input data-id="' . $fid . '" type="button" class="button-primary choose-file-button" value="..." style="width:5%">';
					break;
				}
				case 'colour': {
					echo '<label for="' . $fid . '">';
						echo $keys['label'] . ':';
					echo '</label>';
					echo '<input id="' . $fid . '" type="text" name="' . $fname . '">';
					echo '<input data-id="' . $fid . '" type="color" class="choose-colour-button" value="' . $fval . '">';
					break;
				}
				case 'check': {
					echo '<em>' . $keys['label'] . ':</em>';
					echo '<label class="switch">';
						echo '<input type="hidden" name="' . $fname . '" value="no">';
						echo '<input type="checkbox" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '">';
						echo '<span class="slider"></span>';
					echo '</label>';
					echo '<script>';
						echo '$("#' . $fid . '").on("change", function() {';
							echo '$("#' . $fid . '").val(($("#' . $fid . '").prop("checked")) ? "yes" : "no");';
						echo '});';
						echo 'if ("yes" == "' . $fval . '") {';
							echo '$("#' . $fid . '").prop("checked", true);';
						echo '}';
					echo '</script>';
					break;
				}
			}
			echo '<span class="desc">' . $keys['description'] . '</span>';
		}
?>
		</div>
<?php
		$count++;
	}
?>
	</div>
	<script>
		$(function(){
			var mediaUploader, bid;
			$('.choose-file-button').on('click', function(e) {
				bid = '#' + $(this).data('id');
				e.preventDefault();
				if (mediaUploader) {
					mediaUploader.open();
					return;
				}
				mediaUploader = wp.media.frames.file_frame = wp.media({
					title: 'Choose File',
					button: {
						text: 'Choose File'
					}, multiple: false
				});
				wp.media.frame.on('open', function() {
					if (wp.media.frame.content.get() !== null) {          
						wp.media.frame.content.get().collection._requery(true);
						wp.media.frame.content.get().options.selection.reset();
					}
				}, this);
				mediaUploader.on('select', function() {
					var attachment = mediaUploader.state().get('selection').first().toJSON();
					$(bid).val(attachment.url.split('/').pop());
				});
				mediaUploader.open();
			});
		});
	</script>
<?php
}

function bt_save_postdata($post_id) {
	$post = get_post($post_id);
	$type = $post->post_type;

	if (array_key_exists($type, _POSTS_BASIC_TYPES)) {
		$prefix = '_' . _PREFIX_BASIC_TYPES . '_' . $type . '_';
		$keys = _POSTS_BASIC_TYPES[$type];

		if ($keys) {
			foreach ($keys as $key => $details) {
				$$key = get_post_meta($post->ID, $prefix . $key, true);
			}
		}

		if ($keys) {
			foreach ($keys as $key => $details) {
				if (array_key_exists($prefix . $key, $_POST)) {
					update_post_meta(
						$post_id,
						$prefix . $key,
						$_POST[$prefix . $key]
					);
				}
			}		
		}
	}
}

// admin submenus

function bt_set_current_menu($parent_file) {
	global $submenu_file, $current_screen, $pagenow;

	if (bt_check_var(_TAXES_BASIC_TYPES)) {
		foreach (_TAXES_BASIC_TYPES as $tax => $type) {

			if ($current_screen->id == 'edit-' . $tax) {
				if ($pagenow == 'post.php') {
					$submenu_file = 'edit.php?post_type=' . $current_screen->post_type;
				}
				if ($pagenow == 'edit-tags.php') {
					$submenu_file = 'edit-tags.php?taxonomy=' . $tax . '&post_type=' . $current_screen->post_type;
				}
				$parent_file = _PLUGIN_BASIC_TYPES . '-menu';
			}
		}
	}
	return $parent_file;
}

// pluralise string

function bt_plural($string) {
	switch (substr($string, -1)) {
		case 'y': {
			$plural = rtrim($string, 'y') . 'ies';
			break;
		}
		case 'h': {
			$plural = $string . 'es';
			break;
		}
		default: {
			$plural = $string . 's';
		}
	}

	return $plural;
}

//   ▄█    █▄    ▄█      ▄████████   ▄█     █▄      ▄████████  
//  ███    ███  ███     ███    ███  ███     ███    ███    ███  
//  ███    ███  ███▌    ███    █▀   ███     ███    ███    █▀   
//  ███    ███  ███▌   ▄███▄▄▄      ███     ███    ███         
//  ███    ███  ███▌  ▀▀███▀▀▀      ███     ███  ▀███████████  
//  ▀██    ███  ███     ███    █▄   ███     ███           ███  
//   ▀██  ██▀   ███     ███    ███  ███ ▄█▄ ███     ▄█    ███  
//    ▀████▀    █▀      ██████████   ▀███▀███▀    ▄████████▀ 

function bt_posts_custom_column_views($column_name, $id) {
	$type = get_post_type(get_the_ID());
	$prefix = '_' . _PREFIX_BASIC_TYPES . '_' . $type . '_';

	if (isset(_POSTS_BASIC_TYPES[$type])) {

		foreach (_POSTS_BASIC_TYPES[$type] as $field => $keys) {
			if (($field == $column_name) && $keys['column']) {
				echo get_post_meta($id, $prefix . $field, true);
			}
		}
	}
}

function bt_posts_column_views($columns) {
	$type = $_GET['post_type'];

	if (isset(_POSTS_BASIC_TYPES[$type])) {
		unset($columns['date']);

		foreach (_POSTS_BASIC_TYPES[$type] as $field => $keys) {
			if ($keys['column']) {
				$columns[$field] = $keys['label'];
			}
		}
		
		$columns['date'] = 'Date';
	}

	return $columns;
}

/*

NOT COMPLETE

function bt_sort_custom_column_query($query) {
	$orderby = $query->get('orderby');
	if (in_array($orderby, ['post_views', 'page_views'])) {
		$meta_query = [
			'relation' => 'OR',
			[
				'key' => $orderby . '_count',
				'compare' => 'NOT EXISTS'
			],
			[
				'key' => $orderby . '_count'
			]
		];
		$query->set('meta_query', $meta_query);
		$query->set('orderby', 'meta_value');
	}
}

function bt_set_posts_sortable_columns($columns) {
	$type = get_post_type(get_the_ID());

	if (isset(_POSTS_BASIC_TYPES[$type])) {
		foreach (_POSTS_BASIC_TYPES[$type] as $field => $keys) {
			if ($keys['column']) {
				$columns[$field] = $field;
			}
		}
	}
	return $columns;
}

*/

//     ▄████████   ▄█    ▄█            ███         ▄████████     ▄████████  
//    ███    ███  ███   ███        ▀█████████▄    ███    ███    ███    ███  
//    ███    █▀   ███▌  ███           ▀███▀▀██    ███    █▀     ███    ███  
//   ▄███▄▄▄      ███▌  ███            ███   ▀   ▄███▄▄▄       ▄███▄▄▄▄██▀  
//  ▀▀███▀▀▀      ███▌  ███            ███      ▀▀███▀▀▀      ▀▀███▀▀▀▀▀    
//    ███         ███   ███            ███        ███    █▄   ▀███████████  
//    ███         ███   ███▌    ▄      ███        ███    ███    ███    ███  
//    ███         █▀    █████▄▄██     ▄████▀      ██████████    ███    ███

/*

NOT COMPLETE

function bt_add_filter_to_slides_list() {
	$type = (isset($_GET['post_type'])) ? $_GET['post_type'] : 'post';

	if ($type == 'slide') {
		$banners = get_terms([
			'taxonomy' => 'banner',
			'hide_empty' => FALSE,
		]);

		echo '<select name="banner_name">';
		echo '<option value="">Filter By...</option>';

		$current = isset($_GET['banner_name']) ? $_GET['banner_name'] : '';

		foreach ($banners as $banner) {
			printf(
				'<option value="%s"%s>%s</option>',
				$banner->name,
				$banner->name == $current ? ' selected="selected"' : '',
				$banner->name
			);
		}

		echo '</select>';
		echo '<style>.ui-sortable-handle{cursor:move}</style>';
	}
}

function bt_slides_filter($query) {
	global $pagenow;
	$type = (isset($_GET['post_type'])) ? $_GET['post_type'] : 'post';

	if (is_admin() && $type == 'slide' && $pagenow == 'edit.php') {
		if (isset($_GET['banner_name']) && $_GET['banner_name'] != '') {
			$query->query_vars['banner'] = $_GET['banner_name'];
		}
	}
}

*/

function bt_admin_output() {
	if (BT::$message) {
		bt_print_output(BT::$message);
	}
}

function bt_print_output($message) {
	echo '<div class="notice notice-info"><pre>';
		echo var_export($message, true);
	echo '</pre></div>';
}

//   ▄█   ███▄▄▄▄▄     ▄█       ███      
//  ███   ███▀▀▀▀██▄  ███   ▀█████████▄  
//  ███▌  ███    ███  ███▌     ▀███▀▀██  
//  ███▌  ███    ███  ███▌      ███   ▀  
//  ███▌  ███    ███  ███▌      ███      
//  ███   ███    ███  ███       ███      
//  ███   ███    ███  ███       ███      
//  █▀     ▀█    █▀   █▀       ▄████▀

define('_BT', _btSettings::get_settings());

if (_BT['bt_active'] == 'yes') {
	define('_POSTS_BASIC_TYPES', json_decode(_BT['bt_posts'], TRUE));
	define('_TAXES_BASIC_TYPES', json_decode(_BT['bt_taxes'], TRUE));
	define('_ROLES_BASIC_TYPES', json_decode(_BT['bt_roles'], TRUE));
}
else {
	define('_POSTS_BASIC_TYPES', []);
	define('_TAXES_BASIC_TYPES', []);
	define('_ROLES_BASIC_TYPES', []);
}

add_action('admin_notices', 'bt_admin_output');
add_action('admin_enqueue_scripts', 'bt_admin_scripts');

add_action('add_meta_boxes', 'bt_add_metaboxes');
add_action('save_post', 'bt_save_postdata');

if (bt_check_var(_POSTS_BASIC_TYPES)) {
	foreach (_POSTS_BASIC_TYPES as $field => $keys) {
		add_action('manage_' . $field . '_posts_custom_column', 'bt_posts_custom_column_views', 5, 2);
		add_filter('manage_' . $field . '_posts_columns', 'bt_posts_column_views');
	}
}

// NOT COMPLETE

//add_action('pre_get_posts', 'bt_sort_custom_column_query');
//add_filter('manage_edit-post_sortable_columns', 'bt_set_posts_sortable_columns');

//add_action('restrict_manage_posts', 'bt_add_filter_to_slides_list');
//add_filter('parse_query', 'bt_slides_filter');

add_filter('parent_file', 'bt_set_current_menu');

// boot plugin

add_action('init', 'bt_init');
add_action('rest_api_init', 'bt_api_init');

// eof