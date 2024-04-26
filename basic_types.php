<?php

/*
 * Plugin Name: basic_types
 * Plugin URI: https://nullstep.com/wp-plugins
 * Description: custom post/taxonomy/roles stuff
 * Author: nullstep
 * Author URI: https://nullstep.com
 * Version: 1.2.2
*/

defined('ABSPATH') or die('⎺\_(ツ)_/⎺');

define('_DEVUSER', 'admin');

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

			if (get_option('auth_key') !== '') {
				$updater = new WPU(__FILE__);
				$updater->set_versions('6.4', '6.4.3');
				$updater->set_username('nullstep');
				$updater->set_repository('basic_types');
				$updater->authorize(get_option('auth_key'));
				$updater->initialize();
			}
		}

		// register post types

		if (self::check(self::$posts)) {
			foreach (self::$posts as $type => $data) {
				add_action('manage_' . $type . '_posts_custom_column', __CLASS__ . '::posts_custom_column_views', 5, 2);
				add_filter('manage_' . $type . '_posts_columns', __CLASS__ . '::posts_column_views');
				add_filter('manage_edit-' . $type . '_sortable_columns', __CLASS__ . '::set_posts_sortable_columns');

				$uc_type = ucwords(str_replace('_', ' ', $type));
				$p_type = self::plural($uc_type);

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

		if (self::check(self::$taxes)) {
			foreach (self::$taxes as $tax => $type) {
				$uc_tax = ucwords(str_replace('_', ' ', $tax));
				$p_tax = self::plural($uc_tax);

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

				register_taxonomy_for_object_type($tax, $type);
			}
		}

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
		update_option(self::$def['plugin'] . '-settings', $settings);

		return rest_ensure_response(self::get_settings());
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
				$plural = self::plural(str_replace('_', ' ', $tax));
				add_submenu_page(
					self::$slug,
					ucwords($plural),
					ucwords($plural),
					'manage_options',
					'/edit-tags.php?taxonomy=' . $tax
				);
			}			
		}

		// add custom posts submenus

		if (self::check(self::$posts)) {
			foreach (self::$posts as $type => $data) {
				$plural = self::plural(str_replace('_', ' ', $type));

				add_submenu_page(
					self::$slug,
					ucwords($plural),
					ucwords($plural),
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

		return ($user->user_login == _DEVUSER) ? true : false;
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

	// pluralise a string (possibly some missed exceptions)

	public static function plural($string) {
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

	// add back to... button on type edit page

	public static function add_buttons_to_post_edit() {
		global $post;

		$type = $post->post_type;

		if (isset(BT::$posts[$type])) {
			echo '<br><a class="button button-primary" href="/wp-admin/edit.php?post_type=' . $type . '">Back to ' . ucwords(self::plural(str_replace('_', ' ', $type))) . ' List&hellip;</a><br><br>';
		}
	}

	public static function prefix($type) {
		return '_' . self::$def['prefix'] . '_' . $type . '_';
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

		$idp = strtolower(self::$def['prefix']);
?>
		<style>
			#<?php echo $idp; ?>_meta_box .field-title {
				position: absolute;
				display: inline-block;
				width: 18%;
			}
			#<?php echo $idp; ?>_meta_box .field-edit {
				display: inline-block;
				margin-left: 20%;
				margin-bottom: 10px;
				width: 80%;
			}
			#<?php echo $idp; ?>_meta_box em,
			#<?php echo $idp; ?>_meta_box label {
				display: inline-block;
				font-weight: 700;
				font-style: normal;
				padding-top: 4px;
			}
			#<?php echo $idp; ?>_meta_box input,
			#<?php echo $idp; ?>_meta_box select,
			#<?php echo $idp; ?>_meta_box textarea {
				box-sizing: border-box;
				display: inline-block;
				padding: 3px;
				width: 100%;
				vertical-align: middle;
				margin-top: 10px;
			}
			#<?php echo $idp; ?>_meta_box .mcw {
				padding-top: 6px;
				margin-bottom: 8px;
			}
			#<?php echo $idp; ?>_meta_box .mcw .label {
				display: inline-block;
				padding: 10px 15px 0 5px;
			}
			#<?php echo $idp; ?>_meta_box .mcw .mc {
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
			#<?php echo $idp; ?>_meta_box .mcw .mc::before {
				bottom: -6px;
				content: "";
				left: -6px;
				position: absolute;
				right: -6px;
				top: -6px;
			}
			#<?php echo $idp; ?>_meta_box .mcw .mc,
			#<?php echo $idp; ?>_meta_box .mcw .mc::after {
				transition: all 100ms ease-out;
			}
			#<?php echo $idp; ?>_meta_box .mcw .mc::after {
				background-color: #fff;
				border-radius: 50%;
				content: "";
				height: 14px;
				left: 3px;
				position: absolute;
				top: 3px;
				width: 14px;
			}
			#<?php echo $idp; ?>_meta_box .mcw input[type=checkbox] {
				cursor: default;
			}
			#<?php echo $idp; ?>_meta_box .mcw .mc:hover {
				background-color: #c9cbcd;
				transition-duration: 0s;
			}
			#<?php echo $idp; ?>_meta_box .mcw .mc:checked {
				background-color: var(--admin-highlight, #2271b1);
			}
			#<?php echo $idp; ?>_meta_box .mcw .mc:checked::after {
				background-color: #fff;
				left: 13px;
			}
			#<?php echo $idp; ?>_meta_box .mcw :focus:not(.focus-visible) {
				outline: 0;
			}
			#<?php echo $idp; ?>_meta_box .mcw .mc:checked:hover {
				background-color: var(--admin-highlight, #2271b1);
			}
			#<?php echo $idp; ?>_meta_box input[type=radio] {
				margin-right: 20px;
				width: 20px;
				height: 20px;
				margin-top: -4px;
				appearance: none;
				background-color: #fff;
			}
			#<?php echo $idp; ?>_meta_box input[type=radio]:checked::before,
			#<?php echo $idp; ?>_meta_box input[type=radio]:checked {
				background-color: var(--admin-highlight, #2271b1);
				border: 2px solid var(--admin-highlight, #2271b1);
			}
			#<?php echo $idp; ?>_meta_box span.desc {
				display: block;
				padding-top: 0px;
				font-style: italic;
				font-size: 12px;
			}
			#<?php echo $idp; ?>_meta_box div.middle {
				position: relative;
				margin-bottom: 10px;
				padding-bottom: 10px;
				border-bottom: 1px dashed #ddd;
			}
			#<?php echo $idp; ?>_meta_box div.top {
				position: relative;
				margin-top: 10px;
				margin-bottom: 10px;
				padding-bottom: 10px;
				border-bottom: 1px dashed #ddd;
			}
			#<?php echo $idp; ?>_meta_box div.bottom {
				position: relative;
				margin-bottom: 0;
				padding-bottom: 0;
				border-bottom: 0;
			}
			#<?php echo $idp; ?>_meta_box .switch {
				position: relative;
				display: inline-block;
				width: 50px;
				height: 24px;
				margin: 3px 0;
			}
			#<?php echo $idp; ?>_meta_box .switch input {
				opacity: 0;
				width: 0;
				height: 0;
			}
			#<?php echo $idp; ?>_meta_box .slider {
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
			#<?php echo $idp; ?>_meta_box .slider:before {
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
			#<?php echo $idp; ?>_meta_box input:checked + .slider {
				background-color: var(--admin-highlight, #2271b1);
			}
			#<?php echo $idp; ?>_meta_box input:focus + .slider {
				box-shadow: 0 0 1px var(--admin-highlight, #2271b1);
			}
			#<?php echo $idp; ?>_meta_box input:checked + .slider:before {
				transform: translateX(22px);
			}
			#<?php echo $idp; ?>_meta_box .bt_data-view {
				display: inline-block;
				width: 73%;
				padding: 3px;
				margin-top: 10px;
			}
		</style>
		<script>
			var $ = jQuery;
		</script>
		<div class="inside">
<?php
		$count = 1;

		foreach (BT::$posts[$type] as $field => $keys) {

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
			$fid = self::$def['prefix'] . '_' . $type . '_' . $field;
			$fname = '_' . self::$def['prefix'] . '_' . $type . '_' . $field;
			$fval = $field_values[$field];

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

						<select id="<?php echo self::$def['prefix']; ?>-<?php echo $keys['linked']; ?>">
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
							echo '<select id="' . $fid . '" name="' . $fname . '">';
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
							echo '<input type="text" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '">';
						break;
					}
					case 'display': {
							echo '<label for="' . $fid . '">';
								echo $keys['label'] . ':';
							echo '</label>';
							echo '<span class="desc">' . $keys['description'] . '</span>';
						echo '</div>';
						echo '<div class="field-edit">';
							echo '<input type="text" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '" readonly>';
						break;
					}
					case 'date': {
							echo '<label for="' . $fid . '">';
								echo $keys['label'] . ':';
							echo '</label>';
							echo '<span class="desc">' . $keys['description'] . '</span>';
						echo '</div>';
						echo '<div class="field-edit">';
							echo '<input type="date" id="' . $fid . '" class="date" name="' . $fname . '" value="' . $fval . '">';
						break;
					}
					case 'text': {
							echo '<label for="' . $fid . '">';
								echo $keys['label'] . ':';
							echo '</label>';
							echo '<span class="desc">' . $keys['description'] . '</span>';
						echo '</div>';
						$height = ($keys['height']) ? ' style="height:' . $keys['height'] . '"' : '';
						echo '<div class="field-edit">';
							echo '<textarea id="' . $fid . '" class="tabs" name="' . $fname . '"' . $height . '>' . $fval . '</textarea>';
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
									'media_buttons' => $keys['media'],
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
							echo '<input id="' . $fid . '" type="text" name="' . $fname . '" value="' . $fval . '" style="width:90%">';
							echo '<input data-id="' . $fid . '" type="button" class="button-primary choose-file-button" value="..." style="width:5%">';
						break;
					}
					case 'colour': {
							echo '<label for="' . $fid . '">';
								echo $keys['label'] . ':';
							echo '</label>';
							echo '<span class="desc">' . $keys['description'] . '</span>';
						echo '</div>';
						echo '<div class="field-edit">';
							echo '<input id="' . $fid . '" type="text" name="' . $fname . '">';
							echo '<input id="colour-' . $fid . '" data-id="' . $fid . '" type="color" class="choose-colour-button" value="' . $fval . '">';
							echo '<script>';
								echo '$("#' . $fid . '").on("change", function() {';
									echo '$("#colour-' . $fid . '").val($("#' . $fid . '").val());';
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
				}

				echo '</div>';
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

	//     ▄████████     ▄████████   ▄█    █▄      ▄████████  
	//    ███    ███    ███    ███  ███    ███    ███    ███  
	//    ███    █▀     ███    ███  ███    ███    ███    █▀   
	//    ███           ███    ███  ███    ███   ▄███▄▄▄      
	//  ▀███████████  ▀███████████  ███    ███  ▀▀███▀▀▀      
	//           ███    ███    ███  ▀██    ███    ███    █▄   
	//     ▄█    ███    ███    ███   ▀██  ██▀     ███    ███  
	//   ▄████████▀     ███    █▀     ▀████▀      ██████████

	function save_postdata($post_id) {
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
		}
	}

	//   ▄█    █▄    ▄█      ▄████████   ▄█     █▄      ▄████████  
	//  ███    ███  ███     ███    ███  ███     ███    ███    ███  
	//  ███    ███  ███▌    ███    █▀   ███     ███    ███    █▀   
	//  ███    ███  ███▌   ▄███▄▄▄      ███     ███    ███         
	//  ███    ███  ███▌  ▀▀███▀▀▀      ███     ███  ▀███████████  
	//  ▀██    ███  ███     ███    █▄   ███     ███           ███  
	//   ▀██  ██▀   ███     ███    ███  ███ ▄█▄ ███     ▄█    ███  
	//    ▀████▀    █▀      ██████████   ▀███▀███▀    ▄████████▀

	function posts_custom_column_views($column_name, $id) {
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

	function posts_column_views($columns) {
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

	function sort_custom_column_query($query) {
		if (!is_admin() || !$query->is_main_query()) {
			return;
		}

		$type = $_GET['post_type'];

		if (isset(self::$posts[$type])) {
			$orderby = $query->get('orderby');
			$prefix = self::prefix($type);

			foreach (self::$posts[$type] as $field => $keys) {
				if (isset($keys['column']) && isset($keys['sort'])) {
					if ($orderby == $field) {
						$query->set('meta_key', $prefix . $field);
						$query->set('orderby', 'meta_value');
					}
				}
			}
		}
	}

	function set_posts_sortable_columns($columns) {
		$type = $_GET['post_type'];

		if (isset(self::$posts[$type])) {
			foreach (self::$posts[$type] as $field => $keys) {
				if (isset($keys['column']) && isset($keys['sort'])) {
					$columns[$field] = $field;
				}
			}
		}
		return $columns;
	}
}

//  ███    █▄      ▄███████▄  ████████▄      ▄████████      ███         ▄████████  
//  ███    ███    ███    ███  ███   ▀███    ███    ███  ▀█████████▄    ███    ███  
//  ███    ███    ███    ███  ███    ███    ███    ███     ▀███▀▀██    ███    █▀   
//  ███    ███    ███    ███  ███    ███    ███    ███      ███   ▀   ▄███▄▄▄      
//  ███    ███  ▀█████████▀   ███    ███  ▀███████████      ███      ▀▀███▀▀▀      
//  ███    ███    ███         ███    ███    ███    ███      ███        ███    █▄   
//  ███    ███    ███         ███   ▄███    ███    ███      ███        ███    ███  
//  ████████▀    ▄████▀       ████████▀     ███    █▀      ▄████▀      ██████████

if (!class_exists('WPU')) {
	class WPU {
		private $file;
		private $plugin;
		private $basename;
		private $active;
		private $username;
		private $repository;
		private $authorize_token;
		private $github_response;

		private $requires;
		private $tested;

		public function __construct($file) {
			$this->file = $file;
			add_action('admin_init', [$this, 'set_plugin_properties']);

			return $this;
		}

		public function set_plugin_properties() {
			$this->plugin = get_plugin_data($this->file);
			$this->basename = plugin_basename($this->file);
			$this->active = is_plugin_active($this->basename);
		}

		public function set_versions($requires, $tested) {
			$this->requires = $requires;
			$this->tested = $tested;
		}

		public function set_username($username) {
			$this->username = $username;
		}

		public function set_repository($repository) {
			$this->repository = $repository;
		}

		public function authorize($token) {
			$this->authorize_token = $token;
		}

		private function get_repository_info() {
			if (is_null($this->github_response)) {
				$request_uri = sprintf('https://api.github.com/repos/%s/%s/releases', $this->username, $this->repository);

				$curl = curl_init();

				curl_setopt_array($curl, [
					CURLOPT_URL => $request_uri,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => '',
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 0,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => 'GET',
					CURLOPT_HTTPHEADER => [
						'Authorization: token ' . $this->authorize_token,
						'User-Agent: WPUpdater/1.0.0'
					]
				]);

				$response = curl_exec($curl);

				curl_close($curl);

				$response = json_decode($response, true);

				if (is_array($response)) {
					$response = current($response);
				}

				$this->github_response = $response;
			}
		}

		public function initialize() {
			add_filter('pre_set_site_transient_update_plugins', [$this, 'modify_transient'], 10, 1);
			add_filter('plugins_api', [$this, 'plugin_popup'], 10, 3);
			add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
		}

		public function modify_transient($transient) {
			if (property_exists($transient, 'checked')) {
				if ($checked = $transient->checked) {
					$this->get_repository_info();

					if (isset($this->github_response['tag_name'])) {
						$out_of_date = version_compare($this->github_response['tag_name'], $checked[$this->basename], 'gt');
					}
					else {
						$out_of_date = false;
					}

					if ($out_of_date) {
						$new_files = $this->github_response['zipball_url'];
						$slug = current(explode('/', $this->basename));

						$plugin = [
							'url' => $this->plugin['PluginURI'],
							'slug' => $slug,
							'package' => $new_files,
							'new_version' => $this->github_response['tag_name']
						];

						$transient->response[$this->basename] = (object) $plugin;
					}
				}
			}

			return $transient;
		}

		public function plugin_popup($result, $action, $args) {
			if ($action !== 'plugin_information') {
				return false;
			}

			if (!empty($args->slug)) {
				if ($args->slug == current(explode('/' , $this->basename))) {
					$this->get_repository_info();

					$plugin = [
						'name' => $this->plugin['Name'],
						'slug' => $this->basename,
						'requires' => $this->$requires ?? '6.3',
						'tested' => $this->$tested ?? '6.4.3',
						'version' => $this->github_response['tag_name'],
						'author' => $this->plugin['AuthorName'],
						'author_profile' => $this->plugin['AuthorURI'],
						'last_updated' => $this->github_response['published_at'],
						'homepage' => $this->plugin['PluginURI'],
						'short_description' => $this->plugin['Description'],
						'sections' => [
							'Description' => $this->plugin['Description'],
							'Updates' => $this->github_response['body'],
						],
						'download_link' => $this->github_response['zipball_url']
					];

					return (object) $plugin;
				}
			}


			return $result;
		}

		public function after_install($response, $hook_extra, $result) {
			global $wp_filesystem;

			$install_directory = plugin_dir_path($this->file);
			$wp_filesystem->move($result['destination'], $install_directory);
			$result['destination'] = $install_directory;

			if ($this->active) {
				activate_plugin($this->basename);
			}

			return $result;
		}
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