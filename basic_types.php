<?php

/*
 * Plugin Name: basic_types
 * Plugin URI: https://nullstep.com/wp-plugins
 * Description: custom post/taxonomy/roles stuff
 * Author: nullstep
 * Author URI: https://nullstep.com
 * Version: 1.4.0
*/

defined('ABSPATH') or die('⎺\_(ツ)_/⎺');

define('_DEVUSER', ['admin', 'scott']);

class BT {
	public static $message = null;
	public static $def = [];

	public static $posts;
	public static $taxes;
	public static $roles;
	
	protected static $slug; 

	//   ▄█   ███▄▄▄▄▄     ▄█       ███      
	//  ███   ███▀▀▀▀██▄  ███   ▀█████████▄  
	//  ███▌  ███    ███  ███▌     ▀███▀▀██  
	//  ███▌  ███    ███  ███▌      ███   ▀  
	//  ███▌  ███    ███  ███▌      ███      
	//  ███   ███    ███  ███       ███      
	//  ███   ███    ███  ███       ███      
	//  █▀     ▀█    █▀   █▀       ▄████▀

	public static function init() {
		self::$def['plugin'] = 'basic_types';
		self::$def['prefix'] = 'bt';
		self::$def['url'] = plugin_dir_url(__FILE__);
		self::$def['path'] = plugin_dir_path(__FILE__);

		self::$def['args'] = [
			'bt_active' => [
				'type' => 'string',
				'default' => 'yes'
			],
			'bt_title' => [
				'type' => 'string',
				'default' => ''
			],
			'bt_icon' => [
				'type' => 'string',
				'default' => ''
			],
			'bt_hide_admin' => [
				'type' => 'string',
				'default' => 'yes'
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
		];

		self::$def['admin'] = [
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
					],
					'bt_icon' => [
						'label' => 'Icon',
						'type' => 'file'
					],
					'bt_hide_admin' => [
						'label' => 'Hide "administrator" user accounts',
						'type' => 'check'
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
		];

		self::$def['api'] = [
			[
				'methods' => 'POST',
				'callback' => 'update_settings',
				'args' => self::args(),
				'permission_callback' => 'permissions_admin',
				'path' => 'settings'
			],
			[
				'methods' => 'GET',
				'callback' => 'get_settings',
				'args' => [],
				'permission_callback' => 'permissions_admin',
				'path' => 'settings'
			]
		];

		// setup settings etc.

		define('_BT', BT::get_settings());

		if (_BT['bt_active'] == 'yes') {
			BT::$posts = json_decode(_BT['bt_posts'], true);
			BT::$taxes = json_decode(_BT['bt_taxes'], true);
			BT::$roles = json_decode(_BT['bt_roles'], true);
		}

		// actions and filters

		add_action('admin_enqueue_scripts', __CLASS__ . '::admin_scripts');
		add_action('add_meta_boxes', __CLASS__ . '::add_metaboxes');
		add_action('edit_form_top', __CLASS__ . '::add_buttons_to_post_edit');
		add_action('save_post', __CLASS__ . '::save_postdata');
		add_action('pre_get_posts', __CLASS__ . '::sort_custom_column_query');

		add_filter('parent_file', __CLASS__ . '::set_current_menu');

		if (is_admin()) {
			self::init_menu();
		}

		// register post types

		if (self::check(self::$posts)) {
			foreach (self::$posts as $type => $data) {
				add_action('manage_' . $type . '_posts_custom_column', __CLASS__ . '::posts_custom_column_views', 5, 2);
				add_filter('manage_' . $type . '_posts_columns', __CLASS__ . '::posts_column_views');
				add_filter('manage_edit-' . $type . '_sortable_columns', __CLASS__ . '::set_posts_sortable_columns');

				$uc_type = self::label($type, false, true);
				$p_type = self::label($type);

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

				$supports = ['title'];

				if (isset($data['_settings'])) {
					if (isset($data['_settings']['custom_fields'])) {
						if ($data['_settings']['custom_fields']) {
							$supports[] = 'custom-fields';
						}
					}
				}

				register_post_type($type, [
					'supports' => $supports,
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

		if (self::check(self::$taxes)) {
			foreach (self::$taxes as $tax => $data) {
				add_action($tax . '_edit_form_fields', __CLASS__ . '::edit_taxonomy_fields', 10, 2);
				add_action($tax . '_add_form_fields', __CLASS__ . '::edit_taxonomy_fields', 10, 2);
				add_action('edited_' . $tax, __CLASS__ . '::save_taxonomy_fields', 10, 3);
				add_action('created_' . $tax, __CLASS__ . '::save_taxonomy_fields', 10, 3);
				add_action($tax . '_pre_add_form', __CLASS__ . '::pre_form_fields');
				add_filter('manage_edit-' . $tax . '_columns', __CLASS__ . '::taxonomy_custom_columns');
				add_action('manage_' . $tax . '_custom_column', __CLASS__ . '::taxonomy_custom_column_views', 10, 3);

				$uc_tax = self::label($tax, false, true);
				$p_tax = self::label($tax);

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

				$hierarchical = (isset($data['hierarchical'])) ? $data['hierarchical'] : true;

				register_taxonomy($tax, $data['types'], [
					'hierarchical' => $hierarchical,
					'labels' => $labels,
					'show_ui' => true,
					'show_in_menu' => false,
					'show_in_rest' => true,
					'show_admin_column' => true,
					'query_var' => true,
					'rewrite' => ['slug' => $tax],
				]);

				foreach ($data['types'] as $type) {
					register_taxonomy_for_object_type($tax, $type);
				}
			}
		}

		// register user meta

		if (self::check(self::$roles)) {
			add_filter('manage_users_columns', __CLASS__ . '::manage_users_columns', 10, 1);
			add_action('manage_users_custom_column', __CLASS__ . '::manage_users_custom_column', 10, 3);
			add_action('user_new_form', __CLASS__ . '::user_profile_fields');
			add_action('show_user_profile', __CLASS__ . '::user_profile_fields');
			add_action('edit_user_profile', __CLASS__ . '::user_profile_fields');
			add_action('personal_options_update', __CLASS__ . '::update_profile_fields');
			add_action('edit_user_profile_update', __CLASS__ . '::update_profile_fields');

			// foreach (self::$roles as $role => $data) {}
		}

		if (_BT['bt_hide_admin'] == 'yes') {
			add_action('pre_user_query', __CLASS__ . '::hide_administrators');
			add_filter('views_users', __CLASS__ . '::modify_user_count');
		}

		// item updated messages

		add_filter('post_updated_messages', __CLASS__ . '::updated_messages');

		// and we're done
	}

	//     ▄████████     ▄███████▄   ▄█   
	//    ███    ███    ███    ███  ███   
	//    ███    ███    ███    ███  ███▌  
	//    ███    ███    ███    ███  ███▌  
	//  ▀███████████  ▀█████████▀   ███▌  
	//    ███    ███    ███         ███   
	//    ███    ███    ███         ███   
	//    ███    █▀    ▄████▀       █▀

	public static function api_init() {
		if (count(self::$def['api'])) {

			foreach(self::$def['api'] as $route) {
				register_rest_route(self::$def['plugin'] . '-api/v1', '/' . $route['path'], [
					'methods' => $route['methods'],
					'callback' => __CLASS__ . '::' . $route['callback'],
					'args' => $route['args'],
					'permission_callback' => __CLASS__ . '::' . $route['permission_callback']
				]);
			}
		}
	}

	public static function permissions_admin() {
		return current_user_can('manage_options');
	}

	public static function permissions_open() {
		return true;
	}

	public static function args() {
		$args = self::$def['args'];

		foreach ($args as $key => $val) {
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

	//     ▄████████     ▄████████      ███          ███       ▄█   ███▄▄▄▄▄       ▄██████▄      ▄████████  
	//    ███    ███    ███    ███  ▀█████████▄  ▀█████████▄  ███   ███▀▀▀▀██▄    ███    ███    ███    ███  
	//    ███    █▀     ███    █▀      ▀███▀▀██     ▀███▀▀██  ███▌  ███    ███    ███    █▀     ███    █▀   
	//    ███          ▄███▄▄▄          ███   ▀      ███   ▀  ███▌  ███    ███   ▄███           ███         
	//  ▀███████████  ▀▀███▀▀▀          ███          ███      ███▌  ███    ███  ▀▀███ ████▄   ▀███████████  
	//           ███    ███    █▄       ███          ███      ███   ███    ███    ███    ███           ███  
	//     ▄█    ███    ███    ███      ███          ███      ███   ███    ███    ███    ███     ▄█    ███  
	//   ▄████████▀     ██████████     ▄████▀       ▄████▀    █▀     ▀█    █▀     ████████▀    ▄████████▀

	public static function get_settings(WP_REST_Request $request = null) {
		$defaults = [];

		foreach (self::$def['args'] as $key => $val) {
			$defaults[$key] = $val['default'];
		}

		$saved = get_option(self::$def['plugin'] . '-settings', []);

		if (!is_array($saved) || empty($saved)) {
			return $defaults;
		}

		$settings = wp_parse_args($saved, $defaults);

		return ($request) ? rest_ensure_response($settings) : $settings;
	}

	public static function update_settings(WP_REST_Request $request) {
		$settings = [];

		foreach (self::args() as $key => $val) {
			$settings[$key] = $request->get_param($key);
		}

		$defaults = [];

		foreach (self::$def['args'] as $key => $val) {
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

			if ($i == 'bt_roles') {
				// update roles

				$roles = json_decode($setting, true);

				if (self::check($roles)) {
					if (isset($roles['remove'])) {

						foreach ($roles['remove'] as $role) {
							if (get_role($role)) {
								remove_role($role);
							}
						}			
					}

					if (isset($roles['add'])) {

						foreach ($roles['add'] as $role => $details) {
							$capabilities = [];

							foreach ($details['capabilities'] as $cap) {
								$capabilities[$cap] = true;
							}

							if (get_role($role)) {
								remove_role($role);
							}
							add_role($role, $details['label'], $capabilities);
						}			
					}
				}
				else {
					// roles definition issue
				}
			}
		}

		$ok = true;

		foreach (['bt_posts', 'bt_taxes', 'bt_roles'] as $json) {
			if ($settings[$json] == 'null') {
				$ok = false;
			}
		}

		if ($ok) {
			update_option(self::$def['plugin'] . '-settings', $settings);

			return rest_ensure_response(self::get_settings());
		}
		else {
			return rest_ensure_response(['error' => 'JSON formatting error, not saved.']);
		}
	}

	//    ▄▄▄▄███▄▄▄▄       ▄████████  ███▄▄▄▄▄    ███    █▄   
	//  ▄██▀▀▀███▀▀▀██▄    ███    ███  ███▀▀▀▀██▄  ███    ███  
	//  ███   ███   ███    ███    █▀   ███    ███  ███    ███  
	//  ███   ███   ███   ▄███▄▄▄      ███    ███  ███    ███  
	//  ███   ███   ███  ▀▀███▀▀▀      ███    ███  ███    ███  
	//  ███   ███   ███    ███    █▄   ███    ███  ███    ███  
	//  ███   ███   ███    ███    ███  ███    ███  ███    ███  
	//   ▀█   ███   █▀     ██████████   ▀█    █▀   ████████▀

	public static function init_menu() {
		self::$slug = self::$def['plugin'] . '-menu';

		add_action('admin_menu', __CLASS__ . '::add_page');
		add_action('admin_enqueue_scripts', __CLASS__ . '::register_assets');
	}

	public static function add_page() {
		$title = (_BT['bt_title'] != '') ? _BT['bt_title'] : self::$def['plugin'];
		$icon =  (_BT['bt_icon'] != '') ? wp_get_upload_dir()['url'] . '/' . _BT['bt_icon'] : 'data:image/svg+xml;base64,' . base64_encode('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="500px" height="500px" viewbox="0 0 500 500"><path fill="#a7aaad" d="M250,9.8L42,129.9v240.2l208,120.1l208-120.1V129.9L250,9.8z M152.2,242.3c-13.1,1.1-26.5,0.7-39.6,0.3 c0.2-38.4,0-76.7,0.1-115.1c63.5,0.2,127.1,0.1,190.7,0.1c0.1,13.3,0.1,26.5,0,39.8c-50.2,0-100.5,0.1-150.7,0 C152.5,192.3,153.5,217.4,152.2,242.3z M302.6,397.8c-13.1-0.4-26.3,0-39.4-0.2c0.1-39.9,0-79.7,0-119.6c13.3,0.2,26.6,0,39.9,0.1 C302.8,318,303.9,358,302.6,397.8z M378.6,242.8c-50.2-0.7-100.5,0.1-150.7-0.4c-0.1,51.7,0,103.4-0.1,155.1 c-13.2,0.3-26.5,0.1-39.7,0.1c-0.1-65-0.1-129.9,0-194.9c50.2,0,100.4,0,150.7,0c0-25.1,0-50.1,0-75.2c13.3,0.1,26.6,0.1,39.9,0	C378.6,166,378.8,204.4,378.6,242.8z"/></svg>');

		add_menu_page(
			$title,
			$title,
			'manage_options',
			self::$slug,
			__CLASS__ . '::render_admin',
			$icon,
			3
		);

		// add config submenu

		add_submenu_page(
			self::$slug,
			'Configuration',
			'Configuration',
			'manage_options',
			self::$slug
		);

		// add custom taxonomies submenus

		if (self::check(self::$taxes)) {
			foreach (self::$taxes as $tax => $type) {
				$label = self::label($tax);

				add_submenu_page(
					self::$slug,
					$label,
					$label,
					'manage_options',
					'/edit-tags.php?taxonomy=' . $tax
				);
			}			
		}

		// add custom posts submenus

		if (self::check(self::$posts)) {
			foreach (self::$posts as $type => $data) {
				$label = self::label($type);

				add_submenu_page(
					self::$slug,
					$label,
					$label,
					'manage_options',
					'/edit.php?post_type=' . $type
				);
			}
		}
	}

	public static function register_assets() {
		$boo = microtime(false);

		wp_register_script(self::$slug, self::$def['url'] . '/' . self::$def['plugin'] . '.js?' . $boo, ['jquery']);
		wp_register_style(self::$slug, self::$def['url'] . '/' . self::$def['plugin'] . '.css?' . $boo);
		wp_localize_script(self::$slug, self::$def['plugin'], [
			'strings' => [
				'saved' => 'Settings Saved',
				'error' => 'Error'
			],
			'api' => [
				'url' => esc_url_raw(rest_url(self::$def['plugin'] . '-api/v1/settings')),
				'nonce' => wp_create_nonce('wp_rest')
			]
		]);
	}

	public static function enqueue_assets() {
		if (!wp_script_is(self::$slug, 'registered')) {
			self::register_assets();
		}
		wp_enqueue_script(self::$slug);
		wp_enqueue_style(self::$slug);
	}

	public static function render_admin() {
		wp_enqueue_media();
		self::enqueue_assets();

		$name = self::$def['plugin'];
		$form = self::$def['admin'];

		$dev_tabs = [
			'posts',
			'taxes',
			'roles'
		];

		// build form

		echo '<div id="' . $name . '-wrap" class="wrap">';
			echo '<h1>' . $name . '</h1>';
			echo '<p>Configure your ' . $name . ' settings...</p>';
			echo '<form id="' . $name . '-form" method="post">';
				echo '<nav id="' . $name . '-nav" class="nav-tab-wrapper">';

				foreach ($form as $tid => $tab) {
					if ((!in_array($tid, $dev_tabs)) || self::is_dev()) {
						echo '<a href="#' . $name . '-' . $tid . '" class="nav-tab">' . $tab['label'] . '</a>';
					}
				}
				echo '</nav>';
				echo '<div class="tab-content">';

				foreach ($form as $tid => $tab) {
					if ((!in_array($tid, $dev_tabs)) || self::is_dev()) {
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
				}
				echo '</div>';
				echo '<div>';
					submit_button();
				echo '</div>';
				echo '<div id="' . $name . '-feedback"></div>';
			echo '</form>';
		echo '</div>';
	}

	public static function admin_scripts() {
		wp_enqueue_style('fa', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css');

		$screen = get_current_screen();

		if (null === $screen) {
			return;
		}
		if ($screen->base !== 'toplevel_page_' . self::$def['plugin'] . '-menu') {
			return;
		}

		wp_enqueue_code_editor(['type' => 'application/x-httpd-php']);
	}

	public static function set_current_menu($parent_file) {
		global $submenu_file, $current_screen, $pagenow;

		if (self::check(self::$taxes)) {
			foreach (self::$taxes as $tax => $type) {

				if ($current_screen->id == 'edit-' . $tax) {
					if ($pagenow == 'post.php') {
						$submenu_file = 'edit.php?post_type=' . $current_screen->post_type;
					}
					if ($pagenow == 'edit-tags.php') {
						$submenu_file = 'edit-tags.php?taxonomy=' . $tax . '&post_type=' . $current_screen->post_type;
					}
					$parent_file = self::$def['plugin'] . '-menu';
				}
			}
		}

		return $parent_file;
	}

	//   ▄████████   ▄██████▄   ████████▄      ▄████████  
	//  ███    ███  ███    ███  ███   ▀███    ███    ███  
	//  ███    █▀   ███    ███  ███    ███    ███    █▀   
	//  ███         ███    ███  ███    ███   ▄███▄▄▄      
	//  ███         ███    ███  ███    ███  ▀▀███▀▀▀      
	//  ███    █▄   ███    ███  ███    ███    ███    █▄   
	//  ███    ███  ███    ███  ███   ▄███    ███    ███  
	//  ████████▀    ▀██████▀   ████████▀     ██████████

	public static function is_dev() {
		$user = wp_get_current_user();

		return (in_array($user->user_login, _DEVUSER)) ? true : false;
	}

	// check our variable meets certain conditions

	public static function check($var) {
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

	// prettify and pluralise a string (possibly some missed exceptions)

	public static function label($string, $plural = true, $caps = true) {
		if ($plural) {
			$do_not = [
				'personnel'
			];

			switch (substr($string, -1)) {
				case 'y': {
					$string = rtrim($string, 'y') . 'ies';
					break;
				}
				case 'h':
				case 's': {
					$string = $string . 'es';
					break;
				}
				default: {
					$string = (in_array(strtolower(trim($string)), $do_not) ? $string : $string . 's');
				}
			}
		}

		$string = str_replace('_', ' ', $string);

		if ($caps) {
			$string = ucwords(strtolower($string));
		}

		return $string;
	}

	// add back to... button on type edit page

	public static function add_buttons_to_post_edit() {
		global $post;

		$type = $post->post_type;
		$referrer = $_SERVER['HTTP_REFERER'] ?? null;

		$paged = '';

		if ($referrer) {
			$parsed_url = parse_url($referrer, PHP_URL_QUERY);
			$params = [];
			parse_str($parsed_url, $params);

			if (isset($params['paged'])) {
				$paged = '&paged=' . $params['paged'];
			}
		}

		if (isset(BT::$posts[$type])) {
			echo '<br><a class="button button-primary" href="/wp-admin/edit.php?post_type=' . $type . $paged . '">Back to ' . self::label($type) . ' List&hellip;</a><br><br>';
		}
	}

	// make prefix

	public static function prefix($type) {
		return '_' . self::$def['prefix'] . '_' . $type . '_';
	}

	// set our updated post messages

	public static function updated_messages($messages) {
		$type = get_post_type($_GET['post']);
		$name = self::label($type, false, true);

		$messages[$type] = [
			0 => '',
			1 => $name . ' updated.',
			2 => 'Field updated.',
			3 => 'Field updated.',
			4 => $name . ' updated.',
			5 => isset($_GET['revision']) ? $name . ' retored.' : false,
			6 => $name . ' saved.',
			7 => $name . ' saved.',
			8 => $name . ' saved.',
			9 => $name . ' scheduled.',
			10 => $name . ' updated.'
		];

		return $messages;
	}

	//    ▄▄▄▄███▄▄▄▄       ▄████████      ███         ▄████████  
	//  ▄██▀▀▀███▀▀▀██▄    ███    ███  ▀█████████▄    ███    ███  
	//  ███   ███   ███    ███    █▀      ▀███▀▀██    ███    ███  
	//  ███   ███   ███   ▄███▄▄▄          ███   ▀    ███    ███  
	//  ███   ███   ███  ▀▀███▀▀▀          ███      ▀███████████  
	//  ███   ███   ███    ███    █▄       ███        ███    ███  
	//  ███   ███   ███    ███    ███      ███        ███    ███  
	//   ▀█   ███   █▀     ██████████     ▄████▀      ███    █▀

	public static function gen_css() {
		$idp = strtolower(self::$def['prefix']);
?>
		<style>
			#<?php echo $idp; ?>_meta_box {
				position: relative;

				& .field-title {
					position: absolute;
					display: inline-block;
					width: 18%;
				}
				& .field-edit {
					display: inline-block;
					margin-left: 20%;
					margin-bottom: 10px;
					width: 80%;
				}
				& em, label {
					display: inline-block;
					font-weight: 700;
					font-style: normal;
					padding-top: 4px;
				}
				& input, select, textarea {
					box-sizing: border-box;
					display: inline-block;
					padding: 3px;
					vertical-align: middle;
					margin-top: 10px;
				}
				& .mcw {
					padding-top: 6px;
					margin-bottom: 8px;
				}
				& .mcw .label {
					display: inline-block;
					padding: 10px 15px 0 5px;
				}
				& .mcw .mc {
					appearance: none;
					background-color: #dfe1e4;
					border-radius: 72px;
					border-style: none;
					flex-shrink: 0;
					height: 20px;
					margin: -2px 0 0 0;
					position: relative;
					width: 30px;
				}
				& .mcw .mc::before {
					bottom: -6px;
					content: "";
					left: -6px;
					position: absolute;
					right: -6px;
					top: -6px;
				}
				& .mcw .mc, .mcw .mc::after {
					transition: all 100ms ease-out;
				}
				& .mcw .mc::after {
					background-color: #fff;
					border-radius: 50%;
					content: "";
					height: 14px;
					left: 3px;
					position: absolute;
					top: 3px;
					width: 14px;
				}
				& .mcw input[type=checkbox] {
					cursor: default;
				}
				& .mcw .mc:hover {
					background-color: #c9cbcd;
					transition-duration: 0s;
				}
				& .mcw .mc:checked {
					background-color: var(--admin-highlight, #2271b1);
				}
				& .mcw .mc:checked::after {
					background-color: #fff;
					left: 13px;
				}
				& .mcw :focus:not(.focus-visible) {
					outline: 0;
				}
				& .mcw .mc:checked:hover {
					background-color: var(--admin-highlight, #2271b1);
				}
				& input[type=radio] {
					margin-right: 20px;
					width: 20px;
					height: 20px;
					margin-top: -4px;
					appearance: none;
					background-color: #fff;
				}
				& input[type=radio]:checked::before, input[type=radio]:checked {
					background-color: var(--admin-highlight, #2271b1);
					border: 2px solid var(--admin-highlight, #2271b1);
				}
				& span.desc {
					display: block;
					padding-top: 0px;
					font-style: italic;
					font-size: 12px;
				}
				& div.middle {
					position: relative;
					margin-bottom: 10px;
					padding-bottom: 10px;
					border-bottom: 1px dashed #ddd;
				}
				& div.top {
					position: relative;
					margin-top: 10px;
					margin-bottom: 10px;
					padding-bottom: 10px;
					border-bottom: 1px dashed #ddd;
				}
				& div.bottom {
					position: relative;
					margin-bottom: 0;
					padding-bottom: 0;
					border-bottom: 0;
				}
				& .switch {
					position: relative;
					display: inline-block;
					width: 50px;
					height: 24px;
					margin: 3px 0;
				}
				& .switch input {
					opacity: 0;
					width: 0;
					height: 0;
				}
				& .slider {
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
				& .slider:before {
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
				& input:checked + .slider {
					background-color: var(--admin-highlight, #2271b1);
				}
				& input:focus + .slider {
					box-shadow: 0 0 1px var(--admin-highlight, #2271b1);
				}
				& input:checked + .slider:before {
					transform: translateX(22px);
				}
				& .bt_data-view {
					display: inline-block;
					width: 73%;
					padding: 3px;
					margin-top: 10px;
				}
				& .choose-colour-button {
					margin-top: 9px;
					height: 37px;
				}
				& .choose-file-button {
					position: relative;
					top: 6px;
					margin-right: 1px;
					height: 36px;
				}
				& .view-file-button {
					position: relative;
					top: 6px;
					margin-left: 1px;
					height: 36px;
				}
				& .button-primary:hover {
					box-shadow: 0 0 100px 100px rgba(255,255,255,.3) inset;
				}
			}
			p.search-box,
			.term-name-wrap,
			.term-slug-wrap,
			.term-parent-wrap,
			.term-description-wrap,
			p.submit,
			table.form-table,
			.edit-tag-actions {
				display: none;
			}
			#post-body #side-sortables {
				min-height: unset;

				.category-tabs {
					margin: 14px 0 4px;

					li.tabs {
						border: 1px solid #dcdcde;
						border-bottom-color: #fff;
						background-color: #fff;
					}
				}
			}
			#major-publishing-actions {
				border-top: none;
			}
			#your-profile > h2 {
				display: none;
			}
			#application-passwords-section {
				display: none;

				& h2 {
					padding: 0;
					font-size: 1.3em;
				}
			}
			.mt-1 {
				margin-top: 10px !important;
			}
			.ml-1 {
				margin-left: 8px !important;
			}
			#message p a {
				display: none;
			}
		</style>
<?php
	}

	public static function gen_js() {
?>
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

	public static function gen_fields($what, $type, $field, $fval, $keys) {
		$fid = self::$def['prefix'] . '_' . $type . '_' . $field;
		$fname = self::prefix($type) . $field;

		if (!empty($keys['linked'])) {
			// this is a linked id field

			if ($keys['type'] == 'view') {
				// this is a view linked records field

				echo '<div class="field-title">';
					echo '<label>' . $keys['label'] . ':</label>';
					echo '<span class="desc">' . $keys['description'] . '</span>';
				echo '</div>';

				echo '<div class="field-edit">';

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
						'post_status' => 'publish',
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

				echo '</div>';
			}
			else {
				// this is a standard linked record field
?>
				<input type="hidden" id="<?php echo $fid; ?>" name="<?php echo $fname; ?>" value="<?php echo $fval; ?>">
<?php
				if ($keys['type'] == 'select') {
					// this field needs a select box
?>
				<div class="field-title">
					<label><?php echo $keys['label']; ?>:</label>
					<span class="desc"><?php echo $keys['description']; ?></span>
				</div>

				<div class="field-edit">

					<select id="<?php echo self::$def['prefix']; ?>-<?php echo $keys['linked']; ?>" style="width:99%">
						<option value="">Select <?php echo ucwords(str_replace('_', ' ', $keys['linked'])); ?>&hellip;</option>
<?php
					if ($keys['linked'] == 'user') {
						// list all linked users

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
						// list all linked records of 'type'

						$loop = get_posts([
							'post_type' => $keys['linked'],
							'post_status' => 'publish',
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
				</div>

				<script>
					var <?php echo $field; ?>_select = $('#<?php echo self::$def['prefix']; ?>-<?php echo $keys['linked']; ?>');
					var <?php echo $field; ?>_input = $('#<?php echo self::$def['prefix']; ?>_<?php echo $type; ?>_<?php echo $field; ?>');
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

			echo '<div class="field-title">';

			switch ($keys['type']) {
				case 'select': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<select id="' . $fid . '" name="' . $fname . '" style="width:99%">';
							echo '<option value="">Select ' . $keys['label'] . '&hellip;</option>';
							foreach ($keys['values'] as $value => $label) {
								$selected = ($fval == $value) ? ' selected' : '';
								echo '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
							}
						echo '</select>';
					break;
				}
				case 'multi': {
						echo '<em>' . $keys['label'] . ':</em>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<input type="hidden" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '">';
						echo '<div class="mcw">';
						$farray = explode(',', $fval);
						foreach ($keys['values'] as $value => $label) {
							$checked = (in_array($value, $farray)) ? ' checked' : '';
							echo '<input type="checkbox" class="mc check-' . $fid . '" data-val="' . $value . '"' . $checked . '>';
							echo '<span class="label">' . $label . '</span>';
							echo (!empty($keys['row'])) ? '' : '<br>';
						}
						echo '</div>';
						echo '<script>';
							echo '$(".check-' . $fid . '").on("change", function() {';
								echo 'var v' . $fid . ' = [];';
								echo '$(".check-' . $fid . ':checked").each(function(i, v){';
									echo 'v' . $fid . '.push($(v).data("val"));';
								echo '});';
								echo '$("#' . $fid . '").val(v' . $fid . '.join());';
							echo '});';
						echo '</script>';
					break;
				}
				case 'radio': {
					echo '<em>';
						echo $keys['label'] . ':';
					echo '</em>';
					foreach ($keys['values'] as $value => $label) {
						$checked = ($fval == $value) ? ' checked' : '';
						echo '<div style="display:inline-block;margin:14px 0 14px 0">';
							echo '<span class="label">' . $label . '</span>';
							echo '<input type="radio" name="' . $fname . '" value="' . $value . '"' . $checked . '>';
						echo '</div>';
					}
					break;
				}
				case 'input': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<input type="text" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '" style="width:99%">';
					break;
				}
				case 'email': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<input type="email" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '" style="width:99%">';
					break;
				}
				case 'website': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<input type="text" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '" style="width:99%">';
					break;
				}
				case 'display': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<input type="text" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '" readonly style="width:99%">';
					break;
				}
				case 'date': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<input type="date" id="' . $fid . '" class="date" name="' . $fname . '" value="' . $fval . '" style="width:99%">';
					break;
				}
				case 'text': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					$height = (isset($keys['height'])) ? ';height:' . $keys['height'] . '"' : '';
					echo '<div class="field-edit">';
						echo '<textarea id="' . $fid . '" class="tabs" name="' . $fname . '" style="width:99%' . $height . '"">' . $fval . '</textarea>';
					break;
				}
				case 'code': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<textarea id="' . $fid . '" class="code" name="' . $fname . '"></textarea>';
					break;
				}
				case 'content': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<div style="width:100%;display:inline-block">';
							wp_editor($fval, $fid, [
								'media_buttons' => ($keys['media'] ?? false),
								'textarea_name' => $fname
							]);
						echo '</div>';
					break;
				}
				case 'file': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<button data-id="' . $fid . '" class="button-primary choose-file-button" style="width:5%"><i class="fa-solid fa-file" title="Select"></i></button>';
						echo '<input id="' . $fid . '" type="text" name="' . $fname . '" value="' . $fval . '" style="width:89%">';
						echo '<button id="preview-' . $fid . '" data-id="' . $fid . '" class="button-primary view-file-button" style="width:5%" title="Preview"><i class="fa-solid fa-eye"></i></button>';
						echo '<script>';
							echo '$("#preview-' . $fid . '").on("click", function(e) {';
								echo 'window.open("' . wp_get_upload_dir()['url'] . '/' . $fval . '", "_blank").focus();';
								echo 'e.preventDefault();';
								echo 'return false;';
							echo '});';
						echo '</script>';
					break;
				}
				case 'colour': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<input id="colour-' . $fid . '" data-id="' . $fid . '" type="color" class="choose-colour-button" value="' . $fval . '">';
						echo '<input id="' . $fid . '" type="text" name="' . $fname . '" value="' . $fval . '" style="width:93%">';
						echo '<script>';
							echo '$("#' . $fid . '").on("change", function() {';
								echo '$("#colour-' . $fid . '").val($("#' . $fid . '").val());';
							echo '});';
							echo '$("#colour-' . $fid . '").on("change", function() {';
								echo '$("#' . $fid . '").val($("#colour-' . $fid . '").val());';
							echo '});';
						echo '</script>';
					break;
				}
				case 'check': {
						echo '<em>' . $keys['label'] . ':</em>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
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
				case 'page': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<select id="' . $fid . '" name="' . $fname . '" style="width:99%">';
							echo '<option value="">Select ' . $keys['label'] . '&hellip;</option>';

							$loop = get_posts([
								'post_type' => 'page',
								'post_status' => 'publish',
								'posts_per_page' => '-1',
								'orderby' => 'title',
								'order' => 'ASC'
							]);

							if (count($loop) > 0) {
								foreach ($loop as $page) {
									$selected = ($fval == $page->ID) ? ' selected' : '';
									$label = $page->post_title . ' (' . $page->post_name . ')';
									echo '<option value="' . $page->ID . '"' . $selected . '>' . $label . '</option>';
								}
							}
						echo '</select>';
					break;
				}
				case 'button': {
						echo '<em>' . $keys['label'] . ':</em>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<a class="button button-primary" style="margin-top:10px" href="' . get_admin_url() . 'admin.php?page=' . $keys['action'] . '">';
							echo $keys['label'];
						echo '</a>';
					break;
				}

			}

			echo '</div>';
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

	public static function add_metaboxes() {
		foreach (BT::$posts as $type => $fields) {
			add_meta_box(
				strtolower(__CLASS__) . '_meta_box',
				'Information',
				__CLASS__ . '::post_metabox',
				$type
			);
		}
	}

	public static function post_metabox($post) {
		$type = $post->post_type;

		$field_values = [];

		$admin_url = parse_url(admin_url(sprintf(basename($_SERVER['REQUEST_URI']))));
		$admin_path = str_replace('/wp-admin/', '', $admin_url['path']);
		$new_post = ($admin_path == 'post-new.php') ? true : false;

		$prefix = self::prefix($type);
		$keys = BT::$posts[$type];

		if (count($keys) > 0) {
			foreach ($keys as $key => $details) {
				$field_values[$key] = get_post_meta($post->ID, $prefix . $key, true);
			}		
		}

		wp_nonce_field(plugins_url(__FILE__), 'wr_plugin_noncename');
		wp_enqueue_media();

		self::gen_css();
?>
		<script>
			var $ = jQuery;
		</script>
		<div class="inside">
<?php
		$count = 1;

		foreach (BT::$posts[$type] as $field => $keys) {
			if (!isset($keys['hidden']) || !$keys['hidden']) {

				// set box class
				switch ($count) {
					case count(BT::$posts[$type]): {
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
				$fval = $field_values[$field];

				if ($field != '_settings') {
					self::gen_fields('post', $type, $field, $fval, $keys);
				}
?>
			</div>
<?php
				$count++;
			}
		}
?>
		</div>
<?php

		self::gen_js();
	}

	// save posts data

	public static function save_postdata($post_id) {
		$post = get_post($post_id);
		$type = $post->post_type;

		if (array_key_exists($type, self::$posts)) {
			$prefix = self::prefix($type);
			$keys = self::$posts[$type];

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

			do_action(self::$def['prefix'] . '_after_save_post', $post_id, $type, $_POST);
		}
	}

	// posts views

	public static function posts_custom_column_views($column_name, $id) {
		$type = get_post_type($id);
		$prefix = self::prefix($type);

		if (isset(self::$posts[$type])) {

			foreach (self::$posts[$type] as $field => $keys) {
				if (($field == $column_name) && !empty($keys['column'])) {
					switch ($keys['type']) {
						case 'check': {
							$yes = '<svg fill="#000000" height="18px" width="18px" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 490 490"><polygon points="452.253,28.326 197.831,394.674 29.044,256.875 0,292.469 207.253,461.674 490,54.528 "></polygon></svg>';
							$no = '<svg fill="#000000"  height="16px" width="16px" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M0 14.545L1.455 16 8 9.455 14.545 16 16 14.545 9.455 8 16 1.455 14.545 0 8 6.545 1.455 0 0 1.455 6.545 8z" fill-rule="evenodd"></path></svg>';
							echo (get_post_meta($id, $prefix . $field, true) == 'yes') ? $yes : $no;
							break;
						}
						default: {
							echo apply_filters(strtolower(__CLASS__) . '_column_data', get_post_meta($id, $prefix . $field, true), $id, $column_name);
						}
					}
				}
			}
		}
	}

	public static function posts_column_views($columns) {
		$type = $_GET['post_type'];

		if (isset(self::$posts[$type])) {
			unset($columns['date']);

			foreach (self::$posts[$type] as $field => $keys) {
				if ($keys['column']) {
					$columns[$field] = $keys['label'];
				}
			}
			
			$columns['date'] = 'Date';
		}

		return $columns;
	}

	public static function sort_custom_column_query($query) {
		if (!is_admin() || !$query->is_main_query()) {
			return;
		}

		$type = $_GET['post_type'] ?? null;

		if ($type && isset(self::$posts[$type])) {
			$orderby = $query->get('orderby');
			$prefix = self::prefix($type);

			foreach (self::$posts[$type] as $field => $keys) {
				if (isset($keys['column']) && isset($keys['sort'])) {
					if ($orderby == $field) {
						$sort_by = ($keys['sort'] == 'int') ? 'meta_value_num' : 'meta_value';
						$query->set('meta_key', $prefix . $field);
						$query->set('orderby', $sort_by);
					}
				}
			}
		}
	}

	public static function set_posts_sortable_columns($columns) {
		$type = $_GET['post_type'] ?? null;

		if ($type && isset(self::$posts[$type])) {
			foreach (self::$posts[$type] as $field => $keys) {
				if (isset($keys['column']) && isset($keys['sort'])) {
					$columns[$field] = $field;
				}
			}
		}
		return $columns;
	}



	//      ███         ▄████████  ▀████    ▐████▀  
	//  ▀█████████▄    ███    ███    ███▌   ████▀   
	//     ▀███▀▀██    ███    ███     ███  ▐███     
	//      ███   ▀    ███    ███     ▀███▄███▀     
	//      ███      ▀███████████     ████▀██▄      
	//      ███        ███    ███    ▐███  ▀███     
	//      ███        ███    ███   ▄███     ███▄   
	//     ▄████▀      ███    █▀   ████       ███▄

	public static function edit_taxonomy_fields($term) {
		global $pagenow;

		switch ($pagenow) {
			case 'edit-tags.php': {
				$action = $_GET['action'] ?? 'list';
				break;
			}
			case 'term.php': {
				$action = 'edit';
				break;
			}
			default: {
				$action = 'list';
			}
		}

		$taxonomy = ($term->taxonomy) ?? $term;

		$field_values = [];

		$prefix = self::prefix($taxonomy);
		$keys = BT::$taxes[$taxonomy]['fields'];
		$label = ucwords(strtolower(str_replace('_', ' ', $taxonomy)));

		$hierarchical = BT::$taxes[$taxonomy]['hierarchical'];
		$show_description = BT::$taxes[$taxonomy]['description'];
		$show_slug = BT::$taxes[$taxonomy]['slug'];

		$taxonomies = BT::$taxes[$taxonomy]['taxonomies'] ?? [];

		if ($action != 'list') {
?>
		<br>
		<a class="button button-primary" href="/wp-admin/edit-tags.php?taxonomy=<?php echo $taxonomy; ?>">Back to <?php echo self::label($label, false, true); ?> List…</a>
		<br>
		<br>
		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="post-body-content">
					<div id="titlediv">
						<div id="titlewrap"></div>
					</div>
				</div>

				<div id="postbox-container-1" class="postbox-container">
					<div id="side-sortables" class="meta-box-sortables ui-sortable">
						<div id="submitdiv" class="postbox">
							<div class="postbox-header">
								<h2 class="hndle ui-sortable-handle">Publish</h2>
								<div class="handle-actions hide-if-no-js"></div>
							</div>
							<div class="inside">
								<div class="submitbox" id="submitpost">
									<div id="major-publishing-actions">
										<div id="publishing-action">
											<span class="spinner"></span>
											<input type="submit" name="save" id="publish" class="button button-primary button-large" value="Save">
										</div>
										<div class="clear"></div>
									</div>
								</div>
							</div>
						</div>
<?php
			if (count($taxonomies) > 0) {
				foreach ($taxonomies as $category) {
					$label = self::label($category);
?>
						<div id="<?php echo $category; ?>div" class="postbox">
							<div class="postbox-header">
								<h2 class="hndle ui-sortable-handle"><?php echo $label; ?></h2>
								<div class="handle-actions hide-if-no-js"></div>
							</div>
							<div class="inside">
								<div id="taxonomy-<?php echo $category; ?>" class="categorydiv">
									<ul id="<?php echo $category; ?>-tabs" class="category-tabs">
										<li class="tabs"><a href="#<?php echo $category; ?>-all">All <?php echo $label; ?></a></li>
									</ul>
									<div id="<?php echo $category; ?>-all" class="tabs-panel">
										<input type="hidden" name="tax_input[<?php echo $category; ?>][]" value="0">
										<ul id="<?php echo $category; ?>checklist" data-wp-lists="list:<?php echo $category; ?>" class="categorychecklist form-no-clear">
<?php
					$terms = self::get_terms_of_taxonomy($category);
					$term_ids = explode(',', get_term_meta($term->term_id, $prefix . $category, true));

					if (count($terms) > 0) {
						foreach ($terms as $t) {
							$checked = (in_array($t->term_id, $term_ids)) ? ' checked="checked"' : '';
?>
											<li id="<?php echo $category; ?>-<?php echo $t->term_id; ?>">
												<label class="selectit"><input value="<?php echo $t->term_id; ?>" type="checkbox" name="tax_input[<?php echo $category; ?>][]" id="in-<?php echo $category; ?>-<?php echo $t->term_id; ?>"<?php echo $checked; ?>> <?php echo $t->name; ?></label>
											</li>
<?php
						}
					}
?>
										</ul>
									</div>
								</div>
							</div>
						</div>
<?php
				}
			}
?>
					</div>
				</div>

				<div id="postbox-container-2" class="postbox-container">
					<div id="normal-sortables" class="meta-box-sortables ui-sortable empty-container"></div>
						<div id="advanced-sortables" class="meta-box-sortables ui-sortable">
							<div id="bt_meta_box" class="postbox">
								<div class="postbox-header"><h2 class="hndle ui-sortable-handle">Information</h2>
									<div class="handle-actions hide-if-no-js"></div>
								</div>
<?php
		}

		wp_nonce_field(plugins_url(__FILE__), 'wr_plugin_noncename');
		wp_enqueue_media();

		if (is_string($term)) {
			// this is a new term

			$a = ($action == 'new') ? 'right' : 'left';
			$b = ($action == 'new') ? 'left' : 'right';

			echo '<style>#col-' . $a . '{display:none}#col-' . $b . '{width:100%}#titlediv #tag-name{padding:3px 8px;font-size:1.7em;line-height:100%;height:1.7em;width:100%;outline: 0;margin:0 0 3px;background-color:#fff}</style>';
		}
		else {
			// editing existing term

			if (count($keys) > 0) {
				foreach ($keys as $key => $details) {
					$field_values[$key] = get_term_meta($term->term_id, $prefix . $key, true);
				}		
			}

			echo '<style>#edittag{max-width:100%}#titlediv #name{padding:3px 8px;font-size:1.7em;line-height:100%;height:1.7em;width:100%;outline: 0;margin:0 0 3px;background-color:#fff}</style>';
		}

		if ($action != 'list') {
			$name_element = ($action == 'new') ? 'tag-name' : 'name';
			$slug_element = ($action == 'new') ? 'tag-slug' : 'slug';
			$desc_element = ($action == 'new') ? 'tag-description' : 'description';

			self::gen_css();
?>
								<script>
									var $ = jQuery;
									$(function() {
										let t = $('#<?php echo $name_element; ?>').detach();
										$('#titlewrap').append(t);

										let s = $('#<?php echo $slug_element; ?>').detach();
										s.css({'width':'99%'});
										$('#meta-slug').append(s);

										let p = $('#parent').detach();
										p.css({'width':'99%'});
										$('#meta-parent').append(p);

										let d = $('#<?php echo $desc_element; ?>').detach();
										d.addClass('large-text');
										$('#meta-description').append(d);

										$('#tag-name').focus();
									});
								</script>
								<div class="inside">
<?php
			$count = 1;

			if ($show_slug) {
				$count++;
?>
									<div class="top">
										<div class="field-title">
											<label>Slug:</label>
											<span class="desc">URL-friendly version of the title</span>
										</div>
										<div class="field-edit" id="meta-slug"></div>
									</div>
<?php
			}

			if ($hierarchical) {
				$count++;
				$class = ($count == 1) ? 'top' : 'middle';
?>
									<div class="<?php echo $class; ?>">
										<div class="field-title">
											<label>Parent <?php echo $label; ?>:</label>
											<span class="desc">Assign a parent to create a hierarchy</span>
										</div>
										<div class="field-edit" id="meta-parent"></div>
									</div>
<?php
			}

			if ($show_description) {
				$count++;
				$class = ($count == 1) ? 'top' : 'middle';
?>
									<div class="<?php echo $class; ?>">
										<div class="field-title">
											<label>Description:</label>
											<span class="desc">A description of this category</span>
										</div>
										<div class="field-edit" id="meta-description"></div>
									</div>
<?php
			}

			foreach (BT::$taxes[$taxonomy]['fields'] as $field => $keys) {

				// set box class
				switch ($count) {
					case count(BT::$taxes[$taxonomy]['fields']): {
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
				$fval = $field_values[$field];

				self::gen_fields('taxonomy', $taxonomy, $field, $fval, $keys);
?>
								</div>
<?php
				$count++;
			}
?>
							</div>
<?php
		
			self::gen_js();
?>
						</div>
					</div>
				</div>
			</div>
		</div>
<?php
		}
	}

	public static function save_taxonomy_fields($term_id, $tt_id, $taxonomy) {
		$type = $taxonomy['taxonomy'];

		if (array_key_exists($type, self::$taxes)) {
			$prefix = self::prefix($type);
			$keys = self::$taxes[$type]['fields'];

			if ($keys) {
				foreach ($keys as $key => $details) {
					if (array_key_exists($prefix . $key, $_POST)) {
						update_term_meta(
							$term_id,
							$prefix . $key,
							sanitize_text_field($_POST[$prefix . $key])
						);
					}
				}		
			}

			if (isset($_POST['tax_input']) && is_array($_POST['tax_input'])) {
				if (count($_POST['tax_input']) > 0) {
					foreach ($_POST['tax_input'] as $tax => $term_ids) {
						$term_ids = array_diff($term_ids, [0]);

						update_term_meta(
							$term_id,
							$prefix . $tax,
							sanitize_text_field(implode(',', $term_ids))
						);
					}
				}
			}

			wp_redirect(admin_url('term.php?taxonomy=' . $type . '&tag_ID=' . $term_id));
			exit;
		}
	}

	// add 'new' button to custom taxonomy list page

	public static function pre_form_fields($taxonomy) {
		$screen = get_current_screen();
		$action = $_GET['action'] ?? 'list';

		if ($screen->base == 'edit-tags' && $action != 'new') {
			if ($taxonomy && isset(BT::$taxes[$taxonomy])) {
				$button = " <a class='page-title-action' href='/wp-admin/edit-tags.php?taxonomy=" . $taxonomy . "&action=new'>Add New</a>";
				echo '<script>jQuery(function($){$("h1.wp-heading-inline").after("' . $button . '");});</script>';
			}
		}
	}

	// taxonomy list view columns

	public static function taxonomy_custom_columns($columns) {
		unset($columns['description']);
		unset($columns['slug']);
		unset($columns['posts']);

		$columns = apply_filters('bt_taxonomy_columns', $columns);

		return $columns;
	}

	public static function taxonomy_custom_column_views($output, $column_key, $term_id) {
		$output = apply_filters('bt_taxonomy_column', $output, $column_key, $term_id);

		return $output;
	}

	// get terms of a taxonomy

	public static function get_terms_of_taxonomy($taxonomy) {
		$terms = [];

		if (isset(BT::$taxes[$taxonomy])) {
			$terms = get_terms($taxonomy, [
				'hide_empty' => false
			]);
		}

		return $terms;
	}

	//  ███    █▄      ▄████████     ▄████████     ▄████████  
	//  ███    ███    ███    ███    ███    ███    ███    ███  
	//  ███    ███    ███    █▀     ███    █▀     ███    ███  
	//  ███    ███    ███          ▄███▄▄▄       ▄███▄▄▄▄██▀  
	//  ███    ███  ▀███████████  ▀▀███▀▀▀      ▀▀███▀▀▀▀▀    
	//  ███    ███           ███    ███    █▄   ▀███████████  
	//  ███    ███     ▄█    ███    ███    ███    ███    ███  
	//  ████████▀    ▄████████▀     ██████████    ███    ███

	// edit user page

	public static function user_profile_fields($user) {
		global $pagenow;

		switch ($pagenow) {
			case 'user-new.php': {
				$action = 'new';
				break;
			}
			case 'user-edit.php': {
				$action = 'edit';
				break;
			}
			case 'profile.php': {
				$action = 'profile';
			}
		}

		if ($action !== 'new') {
			$role = get_role($user->roles[0]);
			$keys = (isset(BT::$roles['add'][$role->name]) && (isset(BT::$roles['add'][$role->name]['fields']))) ? BT::$roles['add'][$role->name]['fields'] : [];
			$prefix = self::prefix($role->name);
			$taxonomies = (isset(BT::$roles['add'][$role->name]['taxonomies'])) ? BT::$roles['add'][$role->name]['taxonomies'] : [];
		}
		else {
			$taxonomies = [];
		}
?>
		<br>
		<a class="button button-primary" href="/wp-admin/users.php">Back to Users List…</a>
		<br>
		<br>
		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">


				<div id="postbox-container-1" class="postbox-container">
					<div id="side-sortables" class="meta-box-sortables ui-sortable">
						<div id="submitdiv" class="postbox">
							<div class="postbox-header">
								<h2 class="hndle ui-sortable-handle">Actions</h2>
								<div class="handle-actions hide-if-no-js"></div>
							</div>
							<div class="inside">
								<div class="submitbox" id="submitpost">
									<div id="major-publishing-actions">
										<div id="publishing-action">
											<span class="spinner"></span>
											<!-- submit button moved here -->
										</div>
										<div class="clear"></div>
									</div>
								</div>
							</div>
						</div>
<?php
			if (count($taxonomies) > 0) {
				foreach ($taxonomies as $category) {
					$label = self::label($category);
?>
						<div id="<?php echo $category; ?>div" class="postbox">
							<div class="postbox-header">
								<h2 class="hndle ui-sortable-handle"><?php echo $label; ?></h2>
								<div class="handle-actions hide-if-no-js"></div>
							</div>
							<div class="inside">
								<div id="taxonomy-<?php echo $category; ?>" class="categorydiv">
									<ul id="<?php echo $category; ?>-tabs" class="category-tabs">
										<li class="tabs"><a href="#<?php echo $category; ?>-all">All <?php echo $label; ?></a></li>
									</ul>
									<div id="<?php echo $category; ?>-all" class="tabs-panel">
										<input type="hidden" name="tax_input[<?php echo $category; ?>][]" value="0">
										<ul id="<?php echo $category; ?>checklist" data-wp-lists="list:<?php echo $category; ?>" class="categorychecklist form-no-clear">
<?php
					$terms = self::get_terms_of_taxonomy($category);
					$term_ids = explode(',', get_user_meta($user->ID, $prefix . $category, true));

					if (count($terms) > 0) {
						foreach ($terms as $term) {
							$checked = (in_array($term->term_id, $term_ids)) ? ' checked="checked"' : '';
?>
											<li id="<?php echo $category; ?>-<?php echo $term->term_id; ?>">
												<label class="selectit"><input value="<?php echo $term->term_id; ?>" type="checkbox" name="tax_input[<?php echo $category; ?>][]" id="in-<?php echo $category; ?>-<?php echo $term->term_id; ?>"<?php echo $checked; ?>> <?php echo $term->name; ?></label>
											</li>
<?php
						}
					}
?>
										</ul>
									</div>
								</div>
							</div>
						</div>
<?php
				}
			}
?>
					</div>
				</div>

				<div id="postbox-container-2" class="postbox-container">
					<div id="normal-sortables" class="meta-box-sortables ui-sortable empty-container"></div>
						<div id="advanced-sortables" class="meta-box-sortables ui-sortable">
							<div id="bt_meta_box" class="postbox">
								<div class="postbox-header"><h2 class="hndle ui-sortable-handle">User Information</h2>
									<div class="handle-actions hide-if-no-js"></div>
								</div>
<?php
		wp_nonce_field(plugins_url(__FILE__), 'wr_plugin_noncename');
		wp_enqueue_media();

		self::gen_css();
?>
								<script>
									var $ = jQuery;
									$(function() {
										let s = $('#user_login').detach();
										s.css({'width':'99%'});
										$('#meta-user_login').append(s);

										let r = $('#role').detach();
										r.css({'width':'99%'});
										r.find('option[value="administrator"]').remove();
										$('#meta-role').append(r);

										let e = $('#email').detach();
										e.css({'width':'99%'});
										$('#meta-email').append(e);

										let fn = $('#first_name').detach();
										fn.css({'width':'99%'});
										$('#meta-first_name').append(fn);

										let ln = $('#last_name').detach();
										ln.css({'width':'99%'});
										$('#meta-last_name').append(ln);
<?php
		if ($action != 'new') {
?>
										let dn = $('#display_name').detach();
										dn.css({'width':'99%'});
										$('#meta-display_name').append(dn);

										let u = $('#submit').detach();
										u.addClass('button-large');
										$('#publishing-action').append(u);

										$('#publishing-action').append('<br>');

										let rl = $('#generate-reset-link').detach();
										rl.addClass('mt-1');
										$('#publishing-action').append(rl);

										let ds = $('#destroy-sessions').detach();
										ds.addClass('button-secondary mt-1 ml-1');
										$('#publishing-action').append(ds);

										let ap = $('#application-passwords-section').detach();
										$('#postbox-container-2').append(ap);
										ap.show();
<?php
		}
		else {
?>
										let p1 = $('#pass1').detach();
										p1.css({'width':'99%'});
										$('#meta-pass1').append(p1);

										let c = $('#createusersub').detach();
										c.addClass('button-large');
										$('#publishing-action').append(c);
<?php
		}
?>
										
									});
								</script>
								<div class="inside">
									<div class="top">
										<div class="field-title">
											<label>Username:</label>
											<span class="desc">Once set, usernames cannot be changed (required)</span>
										</div>
										<div class="field-edit" id="meta-user_login"></div>
									</div>
<?php
		if ($action != 'profile') {
?>
									<div class="middle">
										<div class="field-title">
											<label>User Role:</label>
											<span class="desc">Role assigned to this user</span>
										</div>
										<div class="field-edit" id="meta-role"></div>
									</div>
<?php
		}
?>
									<div class="middle">
										<div class="field-title">
											<label>Email Address:</label>
											<span class="desc">The user's email address (required)</span>
										</div>
										<div class="field-edit" id="meta-email"></div>
									</div>
									<div class="middle">
										<div class="field-title">
											<label>First Name:</label>
											<span class="desc">The user's first name</span>
										</div>
										<div class="field-edit" id="meta-first_name"></div>
									</div>
									<div class="middle">
										<div class="field-title">
											<label>Last Name:</label>
											<span class="desc">The user's last name</span>
										</div>
										<div class="field-edit" id="meta-last_name"></div>
									</div>
<?php
		if ($action != 'new') {
			$class = (isset(BT::$roles['add'][$role->name]) && isset(BT::$roles['add'][$role->name]['fields'])) ? 'middle' : 'bottom';
?>
									<div class="<?php echo $class; ?>">
										<div class="field-title">
											<label>Display Name:</label>
											<span class="desc">A preferred display name for this user</span>
										</div>
										<div class="field-edit" id="meta-display_name"></div>
									</div>
<?php
		}
		else {
?>
									<div class="middle">
										<div class="field-title">
											<label>Password:</label>
											<span class="desc">Set a password for this user</span>
										</div>
										<div class="field-edit" id="meta-pass1"></div>
									</div>
<?php
		}

		if ($action != 'new') {
			// we are editing a user, so a role is defined
			// therefore we can show the related meta fields

			if (isset(BT::$roles['add'][$role->name]) && isset(BT::$roles['add'][$role->name]['fields'])) {
				// this role has some meta fields

				if (count($keys) > 0) {
					foreach ($keys as $key => $details) {
						$field_values[$key] = get_user_meta($user->ID, $prefix . $key, true);
					}		
				}

				$count = 1;

				foreach (BT::$roles['add'][$role->name]['fields'] as $field => $keys) {

					// set box class
					switch ($count) {
						case count(BT::$roles['add'][$role->name]['fields']): {
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
					$fval = $field_values[$field];

					self::gen_fields('roles', $role->name, $field, $fval, $keys);
?>
								</div>
<?php
					$count++;
				}
			}
?>
							</div>
<?php
		}
		else {
?>
									<div class="bottom">
										<div class="field-title">
											<label>Additional Data:</label>
											<span class="desc">Role-based data for users</span>
										</div>
										<div class="field-edit" id="meta-info"><p>These extra data fields are based on the assigned user role, and will be available once this new user has been created by clicking the "Add New User" button in the "Actions" box.</p></div>
									</div>
<?php			
		}

		self::gen_js();
?>
						</div>
					</div>
				</div>
			</div>
		</div>
<?php
	}

	// save user data

	public static function update_profile_fields($user_id) {
		if (current_user_can('edit_user', $user_id)) {
			$user = get_user_by('id', $user_id);
			$role = get_role($user->roles[0]);

			if (isset(BT::$roles['add'][$role->name]) && isset(BT::$roles['add'][$role->name]['fields'])) {
				$prefix = self::prefix($role->name);
				$keys = BT::$roles['add'][$role->name]['fields'];

				if ($keys) {
					foreach ($keys as $key => $details) {
						if (array_key_exists($prefix . $key, $_POST)) {
							update_user_meta(
								$user_id,
								$prefix . $key,
								$_POST[$prefix . $key]
							);
						}
					}		
				}
			}

			if (isset($_POST['tax_input']) && is_array($_POST['tax_input'])) {
				if (count($_POST['tax_input']) > 0) {
					foreach ($_POST['tax_input'] as $tax => $term_ids) {
						$term_ids = array_diff($term_ids, [0]);

						update_user_meta(
							$user_id,
							$prefix . $tax,
							sanitize_text_field(implode(',', $term_ids))
						);
					}
				}
			}
		}
	}

	// user view columns

	public static function manage_users_columns($columns) {
		unset($columns['posts']);

		$columns = apply_filters('bt_user_columns', $columns);

		return $columns;
	}

	public static function manage_users_custom_column($output, $column_key, $user_id) {
		$output = apply_filters('bt_user_column', $output, $column_key, $user_id);

		return $output;
	}

	public static function hide_administrators($query) {
		global $wpdb;

		if (strpos($_SERVER['REQUEST_URI'], 'users.php') !== false && !current_user_can('administrator')) {
			$roles_to_hide = ['administrator'];

			$query->query_where = str_replace(
				'WHERE 1=1',
				"WHERE 1=1 AND NOT EXISTS (
					SELECT 1 FROM $wpdb->usermeta
					WHERE $wpdb->users.ID = $wpdb->usermeta.user_id
					AND meta_key = 'wp_capabilities'
					AND meta_value LIKE '%" . implode('|', $roles_to_hide) . "%'
				)",
				$query->query_where
			);
		}
	}

	public static function modify_user_count($views) {
		if (!current_user_can('administrator')) {
			$role_counts = count_users();
			$total_users = $role_counts['total_users'];
			$admin_count = $role_counts['avail_roles']['administrator'];
			
			$non_admin_count = $total_users - $admin_count;

			if (isset($views['all'])) {
				$views['all'] = str_replace(
					sprintf('(%d)', $total_users),
					sprintf('(%d)', $non_admin_count),
					$views['all']
				);
			}

			if (isset($views['administrator'])) {
				unset($views['administrator']);
			}
		}
		return $views;
	}
}


//  ▀█████████▄    ▄██████▄    ▄██████▄       ███      
//    ███    ███  ███    ███  ███    ███  ▀█████████▄  
//    ███    ███  ███    ███  ███    ███     ▀███▀▀██  
//   ▄███▄▄▄██▀   ███    ███  ███    ███      ███   ▀  
//  ▀▀███▀▀▀██▄   ███    ███  ███    ███      ███      
//    ███    ██▄  ███    ███  ███    ███      ███      
//    ███    ███  ███    ███  ███    ███      ███      
//  ▄█████████▀    ▀██████▀    ▀██████▀      ▄████▀

add_action('init', 'BT::init');
add_action('rest_api_init', 'BT::api_init');

// eof