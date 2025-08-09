<?php

/*
 * Plugin Name: basic_types
 * Plugin URI: https://nullstep.com
 * Description: custom post/taxonomy/roles stuff
 * Author: nullstep
 * Author URI: https://nullstep.com
 * Version: 2.2.2
*/

defined('ABSPATH') or die('⎺\_(ツ)_/⎺');

define('_PLUGIN', 'basic_types');
define('_DEVUSER', ['admin', 'nullstep']);

class BT {
	public static $message = null;
	public static $def = [];

	public static $posts;
	public static $taxes;
	public static $roles;
	
	protected static $slug;
	protected static $done = false;

	//   ▄█   ███▄▄▄▄▄     ▄█       ███      
	//  ███   ███▀▀▀▀██▄  ███   ▀█████████▄  
	//  ███▌  ███    ███  ███▌     ▀███▀▀██  
	//  ███▌  ███    ███  ███▌      ███   ▀  
	//  ███▌  ███    ███  ███▌      ███      
	//  ███   ███    ███  ███       ███      
	//  ███   ███    ███  ███       ███      
	//  █▀     ▀█    █▀   █▀       ▄████▀

	public static function init() {
		self::$def['plugin'] = _PLUGIN;
		self::$def['prefix'] = strtolower(__CLASS__);
		self::$def['url'] = plugin_dir_url(__FILE__);
		self::$def['path'] = plugin_dir_path(__FILE__);
		self::$def['nonce'] = wp_create_nonce(_PLUGIN);

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
			'bt_transfer' => [
				'type' => 'string',
				'default' => 'no'
			],
			'bt_data_api' => [
				'type' => 'string',
				'default' => 'permissions_admin'
			],
			'bt_fa' => [
				'type' => 'string',
				'default' => 'no'
			],
			'bt_files' => [
				'type' => 'string',
				'default' => 'no'
			],
			'bt_users' => [
				'type' => 'string',
				'default' => 'yes'
			],
			'bt_order' => [
				'type' => 'string',
				'default' => 'pt'
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
						'label' => 'Icon (base64 encoded svg)',
						'type' => 'input'
					],
					'bt_hide_admin' => [
						'label' => 'Hide "administrator" user accounts',
						'type' => 'check'
					],
					'bt_data_api' => [
						'label' => 'Permissions for API',
						'type' => 'select',
						'values' => [
							'permissions_admin' => 'Admin Only',
							'permissions_open' => 'Open'
						]
					],
					'bt_transfer' => [
						'label' => 'Enable Imports/Exports',
						'type' => 'check'
					],
					'bt_fa' => [
						'label' => 'Include Font Awesome in WP Admin',
						'type' => 'check'
					],
					'bt_files' => [
						'label' => 'Check for JSON files',
						'type' => 'check'
					],
					'bt_users' => [
						'label' => 'Restyle User Edit page',
						'type' => 'check'
					],
					'bt_order' => [
						'label' => 'Set Menu Order',
						'type' => 'select',
						'values' => [
							'pt' => 'Posts > Taxonomies',
							'tp' => 'Taxonomies > Posts'
						]
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
			],
			'misc' => [
				'label' => 'Misc Functions',
				'columns' => 6,
				'buttons' => true
			]
		];

		// get stored settings

		define('_BT', self::get_settings());

		// define api routes

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
			],
			[
				'methods' => 'GET',
				'callback' => 'get_records',
				'args' => [],
				'permission_callback' => _BT['bt_data_api'],
				'path' => 'data'
			]
		];

		// setup settings etc.

		if (_BT['bt_active'] == 'yes') {
			if (_BT['bt_files'] == 'yes') {

				// check for our json files
				// and use them if they exist

				foreach (['posts', 'taxes', 'roles'] as $type) {
					if (file_exists(__DIR__ . '/' . $type . '.json')) {
						self::$$type = json_decode(file_get_contents(__DIR__ . '/' . $type . '.json'), true);
					}
					else {
						self::$$type = json_decode(_BT['bt_' . $type], true);
					}
				}
			}
			else {
				self::$posts = json_decode(_BT['bt_posts'], true);
				self::$taxes = json_decode(_BT['bt_taxes'], true);
				self::$roles = json_decode(_BT['bt_roles'], true);
			}
		}

		// handle any download requests

		self::handle_download();

		// actions and filters

		add_action('admin_footer', __CLASS__ . '::dialog_and_popover');
		add_action('admin_enqueue_scripts', __CLASS__ . '::admin_scripts');
		add_action('add_meta_boxes', __CLASS__ . '::add_metaboxes');
		add_action('edit_form_top', __CLASS__ . '::add_buttons_to_post_edit');
		add_action('save_post', __CLASS__ . '::save_postdata');
		add_action('pre_get_posts', __CLASS__ . '::sort_post_column_query');
		add_action('admin_footer', __CLASS__ . '::post_list_buttons');
		add_action('admin_head', __CLASS__ . '::set_vars');
		add_action('restrict_manage_posts', __CLASS__ . '::add_post_filters');
		add_filter('parse_query', __CLASS__ . '::apply_post_filters');

		add_filter('parent_file', __CLASS__ . '::set_current_menu');

		if (is_admin()) {
			self::init_menu();
		}

		// register our post types

		self::register_posts();

		// register our taxonomies

		self::register_taxes();

		// register user meta management

		if (_BT['bt_users'] == 'yes') {
			if (self::check(self::$roles)) {
				add_filter('manage_users_columns', __CLASS__ . '::manage_users_columns', 10, 1);
				add_action('manage_users_custom_column', __CLASS__ . '::manage_users_custom_column', 10, 3);
				add_action('user_new_form', __CLASS__ . '::user_profile_fields');
				add_action('show_user_profile', __CLASS__ . '::user_profile_fields');
				add_action('edit_user_profile', __CLASS__ . '::user_profile_fields');
				add_action('personal_options_update', __CLASS__ . '::update_profile_fields');
				add_action('edit_user_profile_update', __CLASS__ . '::update_profile_fields');
			}
		}

		if (_BT['bt_hide_admin'] == 'yes' && !self::is_dev()) {
			add_action('pre_user_query', __CLASS__ . '::hide_administrators');
			add_filter('views_users', __CLASS__ . '::modify_user_count');
		}

		// item updated messages

		add_filter('post_updated_messages', __CLASS__ . '::updated_messages');

		// register our ajax handler

		add_action('wp_ajax_' . self::$def['prefix'] . '_ajax', __CLASS__ . '::ajax');

		// register our extended search

		add_action('pre_get_posts', __CLASS__ . '::posts_search');
		add_filter('terms_clauses', __CLASS__ . '::terms_search', 10, 3);

		// and we're done
	}

	public static function register_posts() {
		if (self::check(self::$posts)) {
			foreach (self::$posts as $type => $data) {
				add_action('manage_' . $type . '_posts_custom_column', __CLASS__ . '::posts_column_views', 5, 2);
				add_filter('manage_' . $type . '_posts_columns', __CLASS__ . '::posts_columns');
				add_filter('manage_edit-' . $type . '_sortable_columns', __CLASS__ . '::set_posts_sortable_columns');

				$uc_type = self::label($type, false, true);
				$p_type = self::label($type);

				$labels = [
					'name' => $p_type,
					'singular_name' => $uc_type,
					'menu_name' => $p_type,
					'name_admin_bar' => $p_type,
					'add_new' => 'Add new',
					'add_new_item' => 'Add new ' . $uc_type,
					'new_item' => 'New ' . $uc_type,
					'edit_item' => 'Edit ' . $uc_type,
					'view_item' => 'View ' . $uc_type,
					'all_items' => 'All ' . $p_type,
					'search_items' => 'Search ' . $p_type,
					'not_found' => 'No ' . $p_type . ' found',
					'item_published' => $uc_type . ' published',
					'item_updated' =>  $uc_type . ' updated'
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

			add_action('admin_footer-post-new.php', __CLASS__ . '::auto_taxonomies');
		}
	}

	public static function register_taxes() {
		if (self::check(self::$taxes)) {
			foreach (self::$taxes as $tax => $data) {
				add_action($tax . '_edit_form_fields', __CLASS__ . '::edit_taxonomy_fields', 10, 2);
				add_action($tax . '_add_form_fields', __CLASS__ . '::edit_taxonomy_fields', 10, 2);
				add_action('edited_' . $tax, __CLASS__ . '::save_taxonomy_fields', 10, 3);
				add_action('created_' . $tax, __CLASS__ . '::save_taxonomy_fields', 10, 3);
				add_action($tax . '_pre_add_form', __CLASS__ . '::taxonomy_list_buttons');
				add_filter('manage_edit-' . $tax . '_columns', __CLASS__ . '::taxonomy_columns');
				add_filter('manage_' . $tax . '_custom_column', __CLASS__ . '::taxonomy_column_views', 10, 3);

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
					'add_new_item' => 'Add new ' . $uc_tax,
					'new_item_name' => 'New ' . $uc_tax . ' Name',
					'not_found' => 'No ' . $p_tax . ' found',
					'no_terms' => 'No ' . $p_tax,
					'item_published' => $uc_tax . ' published',
					'item_updated' =>  $uc_tax . ' updated'
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

	// data fetch api

	public static function get_records(?WP_REST_Request $request = null) {
		global $wpdb;

		$type = $request->get_param('type');
		$page = (int)$request->get_param('page');
		$per = (int)$request->get_param('per');
		$taxes = (function_exists('bt_fetch_taxes')) ? bt_fetch_taxes($type) : [];
		$status = $request->get_param('status') ?? 'publish';
		$search = '%' . $wpdb->esc_like($request->get_param('search')) . '%';
		$offset = ($page - 1) * $per;

		$status = ($status == 'any') ? '%' : $status;

		$query_ids = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_status LIKE %s AND (p.post_title LIKE %s OR pm.meta_value LIKE %s) LIMIT %d OFFSET %d";

		$post_ids = $wpdb->get_col(
			$wpdb->prepare($query_ids, $type, $status, $search, $search, $per, $offset)
		);

		$query_count = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_status LIKE %s AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)";

		$total_count = $wpdb->get_var(
			$wpdb->prepare($query_count, $type, $status, $search, $search)
		);

		$results = [];

		if (!empty($post_ids)) {
			$posts = get_posts([
				'post__in' => $post_ids,
				'post_type' => $type,
				'orderby' => 'post__in',
				'posts_per_page' => -1,
			]);

			foreach ($posts as $post) {
				$term_data = [];

				if (count($taxes) > 0) {
					foreach ($taxes as $tax) {
						$terms = get_the_terms($post->ID, $tax);
						$term_data[$tax] = ($terms && !is_wp_error($terms)) ? wp_list_pluck($terms, 'name') : [];
					}
				}

				$results[] = [
					'id' => $post->ID,
					'title' => get_the_title($post),
					'terms' => $term_data,
				];
			}
		}

		$result = [
			'results' => $results,
			'search' => $search,
			'type' => $type,
			'total' => (int)$total_count,
			'page' => $page,
			'per' => $per,
			'pages' => ceil($total_count / $per)
		];

		return ($request) ? rest_ensure_response($result) : $result;
	}

	// RENAME THIS

	public static function data_api() {
		wp_register_script('bt', '');
		wp_enqueue_script('bt');

		$debounce = 'function debounce(f,w){let t;return function(...a){clearTimeout(t);t=setTimeout(()=>f.apply(this,a),w);};}';

		wp_add_inline_script('bt', $debounce . 'const ' . self::$def['plugin'] . ' = ' . json_encode(
			['api' => [
				'url' => esc_url_raw(rest_url(self::$def['plugin'] . '-api/v1/data')),
				'nonce' => wp_create_nonce('wp_rest')
			]]
		));
	}


	//     ▄████████     ▄████████      ███          ███       ▄█   ███▄▄▄▄▄       ▄██████▄      ▄████████  
	//    ███    ███    ███    ███  ▀█████████▄  ▀█████████▄  ███   ███▀▀▀▀██▄    ███    ███    ███    ███  
	//    ███    █▀     ███    █▀      ▀███▀▀██     ▀███▀▀██  ███▌  ███    ███    ███    █▀     ███    █▀   
	//    ███          ▄███▄▄▄          ███   ▀      ███   ▀  ███▌  ███    ███   ▄███           ███         
	//  ▀███████████  ▀▀███▀▀▀          ███          ███      ███▌  ███    ███  ▀▀███ ████▄   ▀███████████  
	//           ███    ███    █▄       ███          ███      ███   ███    ███    ███    ███           ███  
	//     ▄█    ███    ███    ███      ███          ███      ███   ███    ███    ███    ███     ▄█    ███  
	//   ▄████████▀     ██████████     ▄████▀       ▄████▀    █▀     ▀█    █▀     ████████▀    ▄████████▀

	public static function get_settings(?WP_REST_Request $request = null) {
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
		$icon = (_BT['bt_icon'] != '') ? 'data:image/svg+xml;base64,' . _BT['bt_icon'] : 'data:image/svg+xml;base64,' . base64_encode('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="500px" height="500px" viewbox="0 0 500 500"><path fill="#a7aaad" d="M250,9.8L42,129.9v240.2l208,120.1l208-120.1V129.9L250,9.8z M152.2,242.3c-13.1,1.1-26.5,0.7-39.6,0.3 c0.2-38.4,0-76.7,0.1-115.1c63.5,0.2,127.1,0.1,190.7,0.1c0.1,13.3,0.1,26.5,0,39.8c-50.2,0-100.5,0.1-150.7,0 C152.5,192.3,153.5,217.4,152.2,242.3z M302.6,397.8c-13.1-0.4-26.3,0-39.4-0.2c0.1-39.9,0-79.7,0-119.6c13.3,0.2,26.6,0,39.9,0.1 C302.8,318,303.9,358,302.6,397.8z M378.6,242.8c-50.2-0.7-100.5,0.1-150.7-0.4c-0.1,51.7,0,103.4-0.1,155.1 c-13.2,0.3-26.5,0.1-39.7,0.1c-0.1-65-0.1-129.9,0-194.9c50.2,0,100.4,0,150.7,0c0-25.1,0-50.1,0-75.2c13.3,0.1,26.6,0.1,39.9,0	C378.6,166,378.8,204.4,378.6,242.8z"/></svg>');

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

		// add custom posts/taxonomies menus/submenus

		if (_BT['bt_order'] == 'tp') {
			self::add_posts_menus(3.9);
			self::add_taxes_menus(3.1);
		}
		else {
			self::add_posts_menus(3.1);			
			self::add_taxes_menus(3.9);
		}		
	}

	public static function add_posts_menus($position) {
		if (self::check(self::$posts)) {
			foreach (self::$posts as $post => $data) {
				$label = self::label($post);
				$menu = $data['menu'] ?? false;
				$p_icon = $data['icon'] ?? false;
				$mp = $data['position'] ?? $position;

				if ($menu) {
					add_menu_page(
						$label,
						$label,
						'manage_options',
						'edit.php?post_type=' . $post,
						'',
						($p_icon) ? 'data:image/svg+xml;base64,' . $p_icon : $icon,
						$mp
					);
				}
				else {
					add_submenu_page(
						self::$slug,
						$label,
						$label,
						'manage_options',
						'edit.php?post_type=' . $post
					);
				}
			}
		}
	}

	public static function add_taxes_menus($position) {
		if (self::check(self::$taxes)) {
			foreach (self::$taxes as $tax => $data) {
				$label = self::label($tax);
				$menu = $data['menu'] ?? false;
				$t_icon = $data['icon'] ?? false;
				$mp = $data['position'] ?? $position;

				if ($menu) {
					add_menu_page(
						$label,
						$label,
						'manage_options',
						'edit-tags.php?taxonomy=' . $tax,
						'',
						($t_icon) ? 'data:image/svg+xml;base64,' . $t_icon : $icon,
						$mp
					);
				}
				else {
					add_submenu_page(
						self::$slug,
						$label,
						$label,
						'manage_options',
						'edit-tags.php?taxonomy=' . $tax
					);
				}
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
		$title = ucwords((_BT['bt_title'] != '') ? _BT['bt_title'] : self::$def['title']);

		$dev_tabs = [
			'posts',
			'taxes',
			'roles'
		];

		// build form

		echo '<div id="' . $name . '-wrap" class="wrap">';
			echo '<h1>' . $title . '</h1>';
			echo '<p>Configure your ' . $title . ' settings&hellip;</p>';
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
								case 'number': {
									echo '<label for="' . $fid . '">';
										echo $field['label'] . ':';
									echo '</label>';
									echo '<input id="' . $fid . '" type="number" step="' . $field['step'] . '" min="' . $field['min'] . '" max="' . $field['max'] . '" name="' . $fid . '">';
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

		if (_BT['bt_fa'] == 'yes') {
			wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css', '', '6.7.2', 'all');
		}

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

		$base = $current_screen->base;

		switch ($pagenow) {
			case 'edit-tags.php':
			case 'term.php': {
				$type = $current_screen->taxonomy;

				if (isset(self::$taxes[$type])) {
					$tax = self::$taxes[$type];
					if (isset($tax['menu']) && $tax['menu'] === true) {
						$parent_file = 'edit-tags.php?taxonomy=' . $type;
					}
					else {
						$parent_file = self::$def['plugin'] . '-menu';
					}
				}

				break;
			}
			case 'post-new.php':
			case 'post.php': {
				$type = $current_screen->post_type;

				if (isset(self::$posts[$type])) {
					$post = self::$posts[$type];
					if (isset($post['menu']) && $post['menu'] === true) {
						$parent_file = 'edit.php?post_type=' . $type;						
					}
					else {
						$parent_file = self::$def['plugin'] . '-menu';
					}
				}

				break;
			}
		}

		return $parent_file;
	}

	public static function set_vars() {
		$user = wp_get_current_user();
?>
		<script>const _bt = { hash: '<?php echo hash('sha1', 'e' . $user->ID); ?>', nonce: '<?php echo self::$def['nonce']; ?>' };</script>
<?php
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
				'personnel',
				'staff',
				'children'
			];

			$y_not = [
				'holiday'
			];

			switch (substr($string, -1)) {
				case 'y': {
					$string = (in_array(strtolower(trim($string)), $y_not) ? $string . 's' : rtrim($string, 'y') . 'ies');
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

	// make prefix

	public static function prefix($type) {
		return '_' . self::$def['prefix'] . '_' . $type . '_';
	}

	// set our updated post messages

	public static function updated_messages($messages) {
		global $pagenow;

		$type = false;

		if ($pagenow == 'post.php' || $pagenow == 'post-new.php') {
			$post_id = $_GET['post'] ?? null;

			if ($post_id) {
				$type = get_post_type($post_id);
			}
			elseif (isset($_GET['post_type'])) {
				$type = sanitize_text_field($_GET['post_type']);
			}
		}

		if ($type) {
			$name = self::label($type, false, true);

			$messages[$type] = [
				0 => '',
				1 => $name . ' updated.',
				2 => 'Field updated.',
				3 => 'Field updated.',
				4 => $name . ' updated.',
				5 => isset($_GET['revision']) ? $name . ' restored.' : false,
				6 => $name . ' saved.',
				7 => $name . ' saved.',
				8 => $name . ' saved.',
				9 => $name . ' scheduled.',
				10 => $name . ' updated.'
			];
		}

		return $messages;
	}

	// dialog box and popover setup

	public static function dialog_and_popover() {
		$nav = '';

		$tabs = [
			'search'
		];

		$tabs = apply_filters(strtolower(__CLASS__) . '_dialog_tabs', $tabs);

		if ($tabs && is_array($tabs) && count($tabs) > 0) {
			$nav .= '<nav id="bt-dlg-nav" class="nav-tab-wrapper">';

			foreach ($tabs as $tab) {
				$nav .= '<a href="#tab-' . $tab . '" class="nav-tab caps">' . $tab . '</a>';
			}

			$nav .= '</nav>';
		}

		$content = '<div class="tab-content" id="tab-search"></div>';

		$content = apply_filters(strtolower(__CLASS__) . '_dialog_content', $content);

		$html = <<<HTML
			<dialog id="bt-view">
				<div class="content">
					<div class="header">
						<h2></h2>
						<span class="close" onclick="window.BT.dlg('close', 'bt-view');">&times;</span>
					</div>
					<div class="body">
						{$nav}
						{$content}
					</div>
					<div class="footer">
						<span class="button button-primary" id="dlg-confirm" onclick="window.BT[window.BT.cf].done();"><i class="fa-solid fa-check"></i> Confirm</span>
						<span class="button button-secondary" id="dlg-cancel" onclick="window.BT.dlg('close', 'bt-view');"><i class="fa-solid fa-xmark"></i></i> Cancel</span>
					</div>
				</div>
			</dialog>
			<script>
				jQuery(function($) {
					$('#bt-view .tab-content').eq(0).show();
					$('#bt-view .nav-tab').eq(0).addClass('nav-tab-active');
					$('#bt-view nav a').on('click', function(e) {
						e.preventDefault();
						$('#bt-view .nav-tab').removeClass('nav-tab-active');
						var tab = $(this).attr('href');
						$('#bt-view .tab-content').hide();
						$(this).addClass('nav-tab-active');
						$(tab).show();
					});
				});
			</script>
			<div id="bt-popup" popover></div>
HTML;
		echo $html;
	}

	// helper - get our fields, sans-sections

	public static function get_meta_fields($sections) {
		$fields = [];

		if (!$sections) {
			return $fields;
		}

		if (count($sections)) {
			foreach ($sections as $section => $data) {
				if (count($data['fields'])) {
					foreach ($data['fields'] as $field => $keys) {
						$fields[$field] = $keys;
					}
				}
			}
		}

		return $fields;
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

				.field-title {
					position: absolute;
					display: inline-block;
					width: 18%;
				}
				.field-edit {
					display: inline-block;
					margin-left: 20%;
					margin-bottom: 10px;
					width: 80%;

					input, select, textarea {
						box-sizing: border-box;
						display: inline-block;
						padding: 3px;
						vertical-align: middle;
						margin-top: 10px;
					}

					.adjust {
						position: relative;
						top: -4px;
						left: 5px;
					}
				}
				.title {
					h3 {
						font-size: 18px;
						font-weight: 700;
						margin: 1.5rem 0 0;
					    background: rgba(250, 250, 250, 0.8);
					    padding: 6px;
						color: #000;
					}
				}
				em, label {
					display: inline-block;
					font-weight: 700;
					font-style: normal;
					padding-top: 4px;
				}
				.mcw {
					padding-top: 6px;
					margin-bottom: 8px;
				}
				.mcw .label {
					display: inline-block;
					padding: 10px 15px 0 5px;
				}
				.mcw .mc {
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
				.mcw .mc::before {
					bottom: -6px;
					content: "";
					left: -6px;
					position: absolute;
					right: -6px;
					top: -6px;
				}
				.mcw .mc, .mcw .mc::after {
					transition: all 100ms ease-out;
				}
				.mcw .mc::after {
					background-color: #fff;
					border-radius: 50%;
					content: "";
					height: 14px;
					left: 3px;
					position: absolute;
					top: 3px;
					width: 14px;
				}
				.mcw input[type=checkbox] {
					cursor: default;
				}
				.mcw .mc:hover {
					background-color: #c9cbcd;
					transition-duration: 0s;
				}
				.mcw .mc:checked {
					background-color: var(--admin-highlight, #2271b1);
				}
				.mcw .mc:checked::after {
					background-color: #fff;
					left: 13px;
				}
				.mcw :focus:not(.focus-visible) {
					outline: 0;
				}
				.mcw .mc:checked:hover {
					background-color: var(--admin-highlight, #2271b1);
				}
				input[type=radio] {
					margin-right: 20px;
					width: 20px;
					height: 20px;
					margin-top: -4px;
					appearance: none;
					background-color: #fff;
				}
				input[type=radio]:checked::before, input[type=radio]:checked {
					background-color: var(--admin-highlight, #2271b1);
					border: 2px solid var(--admin-highlight, #2271b1);
				}
				span.desc {
					display: block;
					padding-top: 0px;
					font-style: italic;
					font-size: 12px;
				}
				div.middle {
					position: relative;
					margin-bottom: 10px;
					padding-bottom: 10px;
					border-bottom: 1px dashed #ddd;
				}
				div.top {
					position: relative;
					margin-top: 10px;
					margin-bottom: 10px;
					padding-bottom: 10px;
					border-bottom: 1px dashed #ddd;
				}
				div.bottom {
					position: relative;
					margin-bottom: 0;
					padding-bottom: 0;
					border-bottom: 0;
				}
				.switch {
					position: relative;
					display: inline-block;
					width: 50px;
					height: 24px;
					margin: 3px 0;
				}
				.switch input {
					opacity: 0;
					width: 0;
					height: 0;
				}
				.slider {
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
				.slider:before {
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
				input:checked + .slider {
					background-color: var(--admin-highlight, #2271b1);
				}
				input:focus + .slider {
					box-shadow: 0 0 1px var(--admin-highlight, #2271b1);
				}
				input:checked + .slider:before {
					transform: translateX(22px);
				}
				.bt_data-view {
					display: inline-block;
					width: 73%;
					padding: 3px;
					margin-top: 10px;
				}
				.choose-colour-button {
					margin-top: 9px;
					height: 37px;
				}
				.email-button,
				.website-button {
					position: relative;
					top: 6px;
					margin-right: 1px;
					height: 36px;
					line-height: 33px;
					text-align: center;
				}
				.choose-item-button,
				.choose-file-button,
				.view-file-button {
					position: relative;
					top: 7px;
					margin-right: 1px;
					height: 36px;
				}
				.plus-button {
					font-size: 2rem;
					line-height: 25px;
				}
				.button-primary:hover {
					box-shadow: 0 0 100px 100px rgba(255,255,255,.3) inset;
				}
				.barcode {
					width: 20%;
					display: inline-block;
					margin: 0 15px;

					svg {
						position: relative;
						width: 100%;
						top: 17px;
					}
				}
				.bc-btn {
					position: relative;
					top: 5px;
				}
				.bt-gallery-images li {
					position: relative;
					display: inline-block;
					vertical-align: top;
					width: 150px;
					margin: 0 10px 10px 0;
					padding: 5px;
					border: 1px solid #8c8f94;
					border-radius: 4px;

					img {
						display: block;
						width: 100%;
						height: auto;
						margin: 0;
						padding: 0;
					}

					.del {
						position: absolute;
						top: 10px;
						right: 10px;
						width: 20px;
						height: 20px;
						text-align: center;
						font-size: 24px;
						line-height: 14px;
						cursor: pointer;
						user-select: none;
						z-index: 999;
						background: #fff;
						border: 2px solid #000;
						border-radius: 99px;
					}
				}
				.bt-list-select {
					min-width: 150px;
				}
				.bt-list-items li {
					position: relative;
					display: inline-block;
					vertical-align: top;
					width: 150px;
					min-height: 100px;
					margin: 0 10px 10px 0;
					padding: 5px 20px 5px 5px;
					border: 1px solid #8c8f94;
					border-radius: 4px;
					background: #fff;

					p {
						width: calc(100% - 36px);
						text-transform: capitalize;
						margin: 0 0 44px 4px;
						font-size: 16px;
					}

					i {
						position: absolute;
						bottom: 8px;
						left: 8px;
						right: 8px;
						height: 29px;
						font-size: 24px;
						font-style: normal;
						text-align: center;
					}

					span {
						position: absolute;
						width: 20px;
						height: 20px;
						text-align: center;
						font-size: 24px;
						line-height: 14px;
						cursor: pointer;
						user-select: none;
						z-index: 999;
					}

					span:hover {
						filter: brightness(150%);
					}

					.del {
						top: 8px;
						right: 8px;
						background: #ddd;
						border: 1px solid #bbb;
						border-radius: 99px;
					}

					.minus {
						bottom: 8px;
						left: 8px;
						background: var(--primary-brand-colour);
						border: 1px solid #555;
						color: #fff;
						border-radius: 4px;
					}

					.plus {
						bottom: 8px;
						right: 8px;
						background: var(--primary-brand-colour);
						border: 1px solid #555;
						color: #fff;
						border-radius: 4px;
					}
				}
			}
			#application-passwords-section,
			p.search-box,
			.term-name-wrap,
			.term-slug-wrap,
			.term-parent-wrap,
			.term-description-wrap,
			p.submit,
			table.form-table,
			.edit-tag-actions {
				display: none !important;
				opacity: 0;
				visibility: hidden;
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
			#postbox-container-1 {
				.bt-postbox {
					.inside {
						padding-top: 6px;
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

				h2 {
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
			.button-align {
				display: inline-block;
				padding: 5px 10px 0;
			}
			dialog#bt-view {
				border: none;
				width: 66.6vw;
				height: 90vh;
				position: fixed;
				padding: 0;
				margin: 5vh auto;
				overflow-y: auto;
				overflow-x: hidden;

				.caps {
					text-transform: capitalize;
				}

				.content {
					height: 100%;

					.header {
						h2 {
							position: absolute;
							font-size: 1.6rem;
							padding: 0;
							top: 10px;
							left: 30px;
						}

						.close {
							position: absolute;
							font-size: 40px;
							cursor: pointer;
							top: 20px;
							right: 20px;
							z-index: 9999;
						}
					}

					.footer {
						#dlg-confirm {
							position: absolute;
							bottom: 30px;
							right: 110px;
							z-index: 9999;
						}

						#dlg-cancel {
							position: absolute;
							bottom: 30px;
							right: 30px;
							z-index: 9999;
						}
					}

					.body {
						display: flex;
						flex-direction: column;
						box-sizing: border-box;
						height: 100%;
						padding: 80px 30px 30px;

						nav {
							height: 35px;

							a {
								text-decoration: none;
								background: #eee;
								user-select: none;
							}

							a:focus {
								box-shadow: none;
							}

							a.nav-tab-active {
								background: #fefefe;
								border-bottom: 1px solid #fdfdfd;
							}
						}

						.tab-content {
							flex: 1;
							overflow-x: hidden;
							overflow-y: auto;
							display: none;
						}

						#bt-results {
							margin-top: 15px;
							width: 100%;

							th {
								text-align: left;
							}

							.p-minus,
							.p-plus {
								width: 28px;
								user-select: none;
							}

							.p-prev,
							.p-next {
								color: var(--primary-brand-colour);
								font-size: 24px;
								cursor: pointer;
							}

							.off {
								color: #cfd1d4;
								font-size: 24px;
							}

							.num {
								max-width: 50px;
							}

							tr.product:hover {
								background: #eeefee;
							}

							td.ctrls {
								text-align: right;
							}
						}
					}
				}
			}
			dialog#bt-view::backdrop {
				background: rgba(0, 0, 0, 0.7);
				backdrop-filter: blur(3px);
			}
			#bt-popup {
				padding: 10px;
				background: white;
				border: 1px solid #ccc;
				border-radius: 4px;
				max-width: 300px;
				min-width: 100px;
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
				z-index: 99999;
			}
			.loader {
				width: 48px;
				height: 6px;
				display: block;
				margin: auto;
				position: relative;
				border-radius: 4px;
				color: var(--primary-brand-colour);
				box-sizing: border-box;
				animation: anim 0.6s linear infinite;
			}
			@keyframes anim {
				0% { box-shadow: -10px 20px, 10px 35px , 0px 50px; }
				25% { box-shadow: 0px 20px ,  0px 35px, 10px 50px; }
				50% { box-shadow: 10px 20px, -10px 35px, 0px 50px; }
				75% { box-shadow: 0px 20px, 0px  35px, -10px 50px; }
				100% { box-shadow: -10px 20px, 10px  35px, 0px 50px; }
			}
		</style>
<?php
	}

	public static function gen_js() {
?>
		<script>
			var $ = jQuery;

			function ft() {
				let n = $('#titlewrap input');
				let l = n.val().length * 2;
				n.focus();
				n[0].setSelectionRange(l, l);
			}

			function gallery(e) {
				this.field = e;
				this.get = function() {
					var a = $('#' + this.field).val().split(',');
					return a.filter((val) => val !== '');
				};
				this.set = function(imgs) {
					$('#' + this.field).val(imgs.join());
				};
				this.del = function(index) {
					var imgs = this.get();
					imgs.splice(index, 1);
					this.set(imgs);
					this.gen();
				};
				this.add = function(img) {
					var imgs = this.get();
					imgs.push(img);
					this.set(imgs);
					this.gen();
				};
				this.gen = function() {
					var field = this.field;
					var ul = $('#' + field + '_gallery');
					ul.empty();
					var imgs = this.get();
					$.each(imgs, function(i, v) {
						var url = (window.BT.sub == 1) ? '<?php echo content_url(); ?>/uploads' + v : '<?php echo wp_get_upload_dir()['url']; ?>/' + v;
						ul.append('<li><img src="' + url + '"><span class="del" onclick="window.BT.' + field + '.del(' + i + ')">&times;</span></li>');
					});
				};
			}

			function list(e) {
				this.field = e;
				this.get = function() {
					var a = $('#' + this.field).val().split(',');
					return a.filter((val) => val !== '');
				};
				this.set = function(items) {
					$('#' + this.field).val(items.join());
				};
				this.del = function(index) {
					var items = this.get();
					items.splice(index, 1);
					this.set(items);
					this.gen();
				};
				this.add = function(item) {
					var items = this.get();
					items.push(item);
					this.set(items);
					this.gen();
				};
				this.gen = function() {
					var field = this.field;
					var ul = $('#' + field + '_list');
					ul.empty();
					var items = this.get();
					$.each(items, function(i, v) {
						var text = $('#selector_' + field + ' option[value="' + v + '"]').text();
						ul.append('<li><p>' + text + '</p><span class="del" onclick="window.BT.' + field + '.del(' + i + ')">&times;</span></li>');
					});
				};
			}

			function multi(e, t) {
				this.field = window.BT.cf = e;
				this.type = t;
				this.get = function() {
					var a = $('#' + this.field).val();
					if (a == '') {
						a = '[]';
					}
					return JSON.parse(a);
				};
				this.set = function(items) {
					$('#' + this.field).val(JSON.stringify(items));
				};
				this.del = function(item) {
					var items = this.get();
					this.set(items.filter(obj => obj.id !== item));
					this.gen();
				};
				this.add = function(item, title, num) {
					var items = this.get();
					items.push({ 'id': item, 'title': title, 'num': num });
					this.set(items);
				};
				this.done = function() {
					var m = this;
					$('tr.product').each(function(i, v) {
						let p = $(v).find('.num');
						let t = $(v).find('.title');
						let n = Number(p.val());
						if (n > 0) {
							m.add(p.data('id'), window.BT.e64(t.text()), n);
						}
					});
					window.BT.dlg('close', 'bt-view');
 					this.gen();
 				};
				this.minus = function(item) {

				};
				this.plus = function(item) {

				};
				this.dlg = function() {
					var type = this.type;
					$('#bt-view .header h2').html('Add a <span class="caps">' + type + '</span>');
					window.BT.dlg('open', 'bt-view');
					var s = $('#bt-view #tab-search');
					s.empty();
					s.append('<br><input type="text" id="bt-search-term" value="">');
					s.append('&nbsp;<span class="button button-primary" id="bt-search-go"><i class="fa-solid fa-magnifying-glass"></i></span>');
					s.append('<table id="bt-results"></table>');
					var m = this;
					$(document).on('click', '#bt-search-go', function(e) {
						m.fetch($('#bt-view #bt-search-term').val(), 1, 15);
					});
					$(document).on('keypress', '#bt-search-term', function(e) {
						if (e.which === 13) {
							e.preventDefault();
							m.fetch($('#bt-view #bt-search-term').val(), 1, 15);
						}
					});
					$('#bt-view #bt-search-term').focus();
				};
				this.fetch = function(search, page, per) {
					var t = $('#bt-view #bt-results');
					window.BT.ref = {
						t: this,
						s: search,
						p: page,
						per: per
					};
					t.empty();
					t.append('<tr><td colspan="5"><span class="loader"></span></td></tr>');
					window.BT.get({
						type: this.type,
						search: search,
						page: page,
						per: per
					}, function(r) {
						t.empty();
						t.append('<tr><th>Product</th><th>Category</th><th>Supplier</th><th></th></tr>');
						if (r.hasOwnProperty('results')) {
							if (r.results.length > 0) {
								$.each(r.results, function(i, v) {
									t.append('<tr class="product"><td class="title">' + v.title + '</td><td>' + v.terms.product_category.join(', ') + '</td><td>' + v.terms.supplier.join(', ') + '</td><td class="ctrls"><span class="button button-primary p-minus" data-id="' + v.id + '">-</span> <input class="num" type="text" id="n-' + v.id + '" data-id="' + v.id + '" value="0"> <span class="button button-primary p-plus" data-id="' + v.id + '">+</span></td></td>');
								});
								t.append('<tr><td colspan="4"><br><strong>Found ' + r.total + ' result' + ((r.total > 1) ? 's' : '') + ' - Page ' + r.page + ' of ' + r.pages + '</strong></td></tr>');
								if (r.pages > 1) {
									t.append('<tr><td colspan="4">');
									if (r.page > 1) {
										t.append('<span class="p-prev" onclick="window.BT.ref.t.fetch(window.BT.ref.s, (window.BT.ref.p - 1), window.BT.ref.per);"><i class="fa-solid fa-circle-chevron-left"></i></span>&nbsp;');
									}
									else {
										t.append('<span class="off"><i class="fa-solid fa-circle-chevron-left"></i></span>&nbsp;');
									}
									if (r.page < r.pages) {
										t.append('<span class="p-next" onclick="window.BT.ref.t.fetch(window.BT.ref.s, (window.BT.ref.p + 1), window.BT.ref.per);"><i class="fa-solid fa-circle-chevron-right"></i></span>');
									}
									else {
										t.append('<span class="off"><i class="fa-solid fa-circle-chevron-right"></i></span>');
									}
									t.append('</td></tr>');
								}
								$(document).off('click', '.p-minus');
								$(document).off('click', '.p-plus');
								$(document).on('click', '.p-minus', function(e) {
									let id = $(this).data('id');
									let n = Number($('#n-' + id).val());
									if (n > 0) {
										$('#n-' + id).val(n - 1);
									}
								});
								$(document).on('click', '.p-plus', function(e) {
									let id = $(this).data('id');
									let n = Number($('#n-' + id).val());
									$('#n-' + id).val(n + 1);
								});
							}
							else {
								t.append('<tr><td>No results</td></td>');
							}
						}
						else {
							t.append('<tr><td>There was an error fetching data</td></td>');
						}
					});
				};
				this.gen = function() {
					var field = this.field;
					var ul = $('#' + field + '_list');
					ul.empty();
					var items = this.get();
					$.each(items, function(i, v) {
						var text = $('#selector_' + field + ' option[value="' + v + '"]').text();
						ul.append('<li><p>' + window.BT.d64(v.title).toLowerCase() + '</p><span class="del" onclick="window.BT.' + field + '.del(' + v.id + ')">&times;</span><span class="minus" onclick="window.BT.' + field + '.minus(' + v.id + ')">-</span><span class="plus" onclick="window.BT.' + field + '.plus(' + v.id + ')">+</span><i>' + v.num + '</i></li>');
					});
				};
			}

			jQuery(function($) {
				const plugin = basic_types;

				window.BT = {
					sub: <?php echo (get_option('uploads_use_yearmonth_folders')) ? 1 : 0; ?>,
					dlg: function(action, id) {
						let d = document.getElementById(id);
						if (action == 'open') {
							d.showModal();
						}
						else {
							d.close();
						}
					},
					cf: '',
					get: function(args, f) {
						$.ajax({
							method: 'GET',
							url: plugin.api.url,
							beforeSend: function(xhr) {
								xhr.setRequestHeader('X-WP-Nonce', plugin.api.nonce);
							},
							data: args
						})
						.then(function(r) {
							f(r);
						});
					}
					,
					e64: function(str) {
						return btoa(unescape(encodeURIComponent(str)));
					},
					d64: function(str) {
						return decodeURIComponent(escape(atob(str)));
 					}
				};

				var mediaUploader, bid;

				$('.choose-file-button').on('click', function(e) {
					bid = $(this).data('id');
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
						var i = (window.BT.sub) ? attachment.url.substring(attachment.url.indexOf('uploads') + 7) : attachment.url.split('/').pop();
						if ($('#' + bid).hasClass('bt-gallery')) {
							window.BT[bid].add(i);
						}
						else {
							$('#' + bid).val(i);
						}
					});
					mediaUploader.open();
				});

				$('#submitdiv h2').text('Actions');
				$('#publishing-action #publish').val('Save');
			});
		</script>
<?php
	}

	public static function gen_fields($what, $type, $field, $fval, $keys) {
		$fid = self::$def['prefix'] . '_' . $type . '_' . $field;
		$fname = self::prefix($type) . $field;

		$post_id = $_GET['post'] ?? null;

		$override = apply_filters(self::$def['prefix'] . '_add_post_fields', false, $what, $type, $field, $fval, $keys);

		if ($override) {
			$overridden = (!is_array($override)) ? [$override] : $override;

			if (count($overridden) > 0) {
				foreach ($overridden as $o) {
					echo '<div class="field-title">';
						echo '<label>' . $o['label'] . ':</label>';
						echo '<span class="desc">' . $o['description'] . '</span>';
					echo '</div>';

					echo '<div class="field-edit">';
						echo $o['value'];
					echo '</div>';
				}
			}

			return;
		}

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
					// view linked user

					$user = get_user_by('ID', $fval);

					if ($user) {
						echo '<input id="user-' . $user->ID . '" type="text" readonly value="' . $user->display_name . '" style="width:99%">';
					}
					else {
						echo '<div id="user-none">No data to view</div>';
					}
				}
				else {
					// view linked record of 'type'

					echo '<input type="text" readonly id="linked_' . $keys['linked'] . '" value="' . (($fval != '') ? $fval : 'N/A') . '" style="width:99%">';
				}

				echo '</div>';
			}
			else {
				// this is a standard linked record field
?>
				<input type="hidden" id="<?php echo $fid; ?>" name="<?php echo $fname; ?>" value="<?php echo $fval; ?>">
<?php
				switch ($keys['type']) {
					case 'select': {
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
							'posts_per_page' => -1,
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
						break;
					}
					case 'multi': {
						// this is a multi linked record field using
						// checkboxes to create a comma separated array
?>
						<div class="field-title">
							<label><?php echo $keys['label']; ?>:</label>
							<span class="desc"><?php echo $keys['description']; ?></span>
						</div>
						<div class="field-edit">
<?php
						if (!is_array($fval)) {
							$fval = ($fval !== '') ? explode(',', $fval) : [];
						}

						if ($keys['linked'] == 'user') {
							$roles = (strpos($keys['role'], ',') !== false) ? explode(',', $keys['role']) : [$keys['role']];
							$role = (isset($keys['role'])) ? ['role__in' => $roles] : null;
							$users = get_users($role);

							if (count($users)) {
								foreach ($users as $user) {
									$id = $user->ID;
									$checked = in_array($id, $fval) ? ' checked' : '';
									echo '<label><input type="checkbox" class="bt-cb" name="' . self::$def['prefix'] . '-' . $keys['linked'] . '[]" value="' . $id . '"' . $checked . '> ' . $user->display_name . '</label>';
									echo (!empty($keys['row'])) ? '' : '<br>';
								}
							}
						}
						else {
							$loop = get_posts([
								'post_type' => $keys['linked'],
								'post_status' => 'publish',
								'posts_per_page' => -1,
								'orderby' => 'title',
								'order' => 'ASC'
							]);

							if (count($loop) > 0) {
								foreach ($loop as $post) {
									$id = $post->ID;
									$checked = in_array($id, $fval) ? ' checked' : '';
									if ($post->post_title != 'Auto Draft') {
										echo '<label><input type="checkbox" class="bt-cb" name="' . self::$def['prefix'] . '-' . $keys['linked'] . '[]" value="' . $id . '"' . $checked . '> ' . $post->post_title . '</label>';
										echo (!empty($keys['row'])) ? '' : '<br>';
									}
								}
							}
						}
?>
						</div>
						<script>
							var <?php echo $field; ?>_checkboxes = $('input[name="<?php echo self::$def['prefix']; ?>-<?php echo $keys['linked']; ?>[]"]');
							var <?php echo $field; ?>_input = $('#<?php echo self::$def['prefix']; ?>_<?php echo $type; ?>_<?php echo $field; ?>');
							<?php echo $field; ?>_checkboxes.on('change', function() {
								var values = [];
								<?php echo $field; ?>_checkboxes.each(function() {
									if ($(this).is(':checked')) {
										values.push($(this).val());
									}
								});
								<?php echo $field; ?>_input.val(values.join(','));
							});
						</script>
<?php
						break;
					}
					default: {
						// unsupported or undefined linked 'type'

						echo '-_-';
					}
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
								$selected = ($fval == $value || $value == $keys['default']) ? ' selected' : '';
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
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						foreach ($keys['values'] as $value => $label) {
							$checked = ($fval == $value) ? ' checked' : '';
							echo '<div style="display:inline-block;margin:14px 0 14px 0">';
								echo '<span class="label" style="margin-right:10px">' . $label . '</span>';
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
						if ($fval == '') {
							$fval = $keys['default'] ?? $fval;
						}
						echo '<input type="text" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '" style="width:99%">';
					break;
				}
				case 'calc': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						$calc = $keys['calc'] ?? null;
						$data = ($post_id) ? self::get_all($post_id) : null;
						if ($data && $calc) {
							foreach ($data as $f => $v) {
								$var = '_' . $f;
								$$var = (float)$v;
							}
						}
						@eval('$out =' . $calc . ';');
						echo '<input type="text" readonly value="' . $out . '" style="width:99%">';
					break;
				}
				case 'barcode': {
					require_once(get_template_directory() . '/inc/barcode.php');
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<input type="text" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '" style="width:25%">';
						if ($fval != '') {
							$barcode = new Barcode();
							$obj = $barcode->getBarcodeObj($keys['version'], $fval, -2, -37, 'black', [0, 0, 0, 0])->setBackgroundColor('white');
							echo '<div class="barcode">' . $obj->getSvgCode() . '</div>';
							echo '<a href="' . get_admin_url() . 'admin.php?action=download&type=_barcode&version=' . urlencode($keys['version']) . '&format=svg&value=' . $fval . '" class="button-primary bc-btn"><i class="fa-solid fa-circle-down"></i> &nbsp;SVG</a> &nbsp; ';
							echo '<a href="' . get_admin_url() . 'admin.php?action=download&type=_barcode&version=' . urlencode($keys['version']) . '&format=png&value=' . $fval . '" class="button-primary bc-btn"><i class="fa-solid fa-circle-down"></i> &nbsp;PNG</a>';
						}
					break;
				}
				case 'id': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<input type="text" value="' . $post_id . '" readonly style="width:99%">';
					break;
				}
				case 'number': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					$step = (isset($keys['step'])) ? ' step="' . $keys['step'] . '"' : '';
					$min = (isset($keys['min'])) ? ' min="' . $keys['min'] . '"' : '';
					$max = (isset($keys['max'])) ? ' max="' . $keys['max'] . '"' : '';
					echo '<div class="field-edit">';
						echo '<input type="number" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '"' . $step . $min . $max . '>';
					break;
				}
				case 'email': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<a data-id="' . $fid . '" href="mailto:' . $fval . '" class="button-primary email-button" style="width:5%"><i class="fa-solid fa-at"></i></a>';
						echo '<input type="email" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '" style="width:93%">';
					break;
				}
				case 'website': {
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<a data-id="' . $fid . '" href="' . $fval . '" class="button-primary website-button" style="width:5%" target="_blank"><i class="fa-solid fa-globe"></i></a>';
						echo '<input type="text" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '" style="width:93%">';
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
					if ($fval == '' && $keys['default'] != '') {
						if ($keys['default'] == 'now') {
							$fval = date('Y-m-d');
						}
						else {
							$fval = $keys['default'];
						}
					}
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
					$height = (isset($keys['height'])) ? ';height:' . $keys['height'] : '';
					echo '<div class="field-edit">';
						echo '<textarea id="' . $fid . '" class="tabs" name="' . $fname . '" style="width:99%' . $height . '">' . $fval . '</textarea>';
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
					$colour = ($fval == '') ? '#000000' : $fval;
						echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<input id="colour-' . $fid . '" data-id="' . $fid . '" type="color" class="choose-colour-button" value="' . $colour . '">';
						echo '<input id="' . $fid . '" type="text" name="' . $fname . '" value="' . $colour . '" style="width:93%">';
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
								'posts_per_page' => -1,
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
				case 'gallery': {
							echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';
					echo '<div class="field-edit">';
						echo '<input class="bt-gallery" type="hidden" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '">';
						echo '<div class="button button-primary choose-file-button plus-button" data-id="' . $fid . '">+</div>';
						echo '<ul class="bt-gallery-images" id="' . $fid . '_gallery"></ul>';
						echo '<script>';
							echo 'var g = new gallery("' . $fid . '"); g.gen(); window.BT.' . $fid . ' = g;';
						echo '</script>';
					break;
				}
				case 'list': {
							echo '<label for="' . $fid . '">';
							echo $keys['label'] . ':';
						echo '</label>';
						echo '<span class="desc">' . $keys['description'] . '</span>';
					echo '</div>';

					$options = [];
					$linked = $keys['linked_type'] ?? null;

					if ($linked) {
						$posts = get_posts([
							'post_type' => $linked,
							'post_status' => 'publish',
							'posts_per_page' => -1,
							'orderby' => 'title',
							'order' => 'ASC'
						]);

						if (is_array($posts) && count($posts) > 0) {
							foreach ($posts as $p) {
								$options[] = [
									'id' => $p->ID,
									'title' => $p->post_title
								];
							}
						}
					}

					echo '<div class="field-edit">';
						echo '<input class="bt-list" type="hidden" id="' . $fid . '" name="' . $fname . '" value="' . $fval . '">';
						echo '<div class="button button-primary choose-item-button plus-button" data-id="' . $fid . '">+</div>';
						echo '<span id="select_' . $fid . '" class="hidden adjust">';
							echo '<select id="selector_' . $fid . '" class="bt-list-select">';
								echo '<option value="0">Please Select...</option>';
								foreach ($options as $option) {
									echo '<option value="' . $option['id'] . '">' . $option['title'] . '</option>';
								}
							echo '</select>';
						echo '</span>';
						echo '<ul class="bt-list-items" id="' . $fid . '_list"></ul>';
						echo '<script>';
							echo 'jQuery(function($) {';
								echo 'var l = new list("' . $fid . '"); l.gen(); window.BT.' . $fid . ' = l;';
								echo '$("#selector_' . $fid . '").on("change", function() {';
									echo 'var i = $(this).val();';
									echo 'window.BT.' . $fid . '.add(i);';
									echo 'window.BT.' . $fid . '.gen();';
									echo '$(this).val("0");';
									echo '$("#select_' . $fid . '").hide();';
								echo '});';
								echo '$(".choose-item-button").on("click", function(e) {';
									echo '$("#select_' . $fid . '").show();';
								echo '});';
							echo '});';
						echo '</script>';
					break;
				}
				case 'multi-list': {
					$html = <<<HTML
						<label for="{$fid}">
							{$keys['label']}:
						</label>
						<span class="desc">{$keys['description']}</span>
					</div>
					<div class="field-edit">
						<input class="bt-list" type="hidden" id="{$fid}" name="{$fname}" value="{$fval}">
						<div class="button button-primary choose-item-button plus-button" data-id="{$fid}">+</div>
						<ul class="bt-list-items" id="{$fid}_list"></ul>
						<script>
							jQuery(function($) {
								var m = new multi('{$fid}', '{$keys['linked_type']}'); m.gen(); window.BT.{$fid} = m;
								$('.choose-item-button').on('click', function(e) {
									window.BT.{$fid}.dlg();
								});
							});
						</script>
HTML;
					echo $html;
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
		foreach (self::$posts as $type => $fields) {
			add_meta_box(
				strtolower(__CLASS__) . '_meta_box',
				'Information',
				__CLASS__ . '::post_metabox',
				$type
			);
		}
	}

	public static function post_metabox($post) {
		global $pagenow;

		$type = $post->post_type;
		$field_values = [];

		$new_post = ($pagenow == 'post-new.php') ? true : false;

		$prefix = self::prefix($type);
		$fields = self::get_meta_fields(self::$posts[$type]['sections']);

		if (count($fields) > 0) {
			foreach ($fields as $field => $details) {
				$field_values[$field] = get_post_meta($post->ID, $prefix . $field, true);
			}		
		}

		wp_nonce_field(plugins_url(__FILE__), 'bt_nonce');
		wp_enqueue_media();

		self::data_api();

		self::gen_css();
		self::gen_js();
?>
		<script>
			jQuery(function($) {
				ft();
			});
		</script>

		<div class="inside">
<?php
		foreach (self::$posts[$type]['sections'] as $section => $data) {
			if (isset($data['label']) && $data['label'] != '') {
				echo '<div class="title"><h3>' . $data['label'] . '</h3></div>';
			}

			$count = 1;

			foreach ($data['fields'] as $field => $keys) {
				if (!isset($keys['hidden']) || !$keys['hidden']) {

					// set box class
					switch ($count) {
						case count($data['fields']): {
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
					$fval = $field_values[$field] ?? '';

					if ($field != '_settings') {
						self::gen_fields('post', $type, $field, $fval, $keys);
					}
?>
			</div>
<?php
					$count++;
				}
			}
		}
?>
		</div>
<?php
	}

	// save posts data

	public static function save_postdata($post_id) {
		$post = get_post($post_id);
		$type = $post->post_type;

		if (array_key_exists($type, self::$posts)) {
			$prefix = self::prefix($type);
			$fields = self::get_meta_fields(self::$posts[$type]['sections']);

			if (count($fields)) {
				foreach ($fields as $field => $value) {
					if (array_key_exists($prefix . $field, $_POST)) {
						update_post_meta(
							$post_id,
							$prefix . $field,
							$_POST[$prefix . $field]
						);
					}
				}
			}

			do_action(self::$def['prefix'] . '_after_save_post', $post_id, $type, $_POST);
		}
	}

	// posts views

	public static function posts_column_views($column_key, $post_id) {
		$type = get_post_type($post_id);
		$prefix = self::prefix($type);

		if (isset(self::$posts[$type])) {
			$fields = self::get_meta_fields(self::$posts[$type]['sections']);

			if (!count($fields)) {
				return;
			}

			foreach ($fields as $field => $keys) {
				if (($field == $column_key) && !empty($keys['column'])) {
					$meta_value = get_post_meta($post_id, $prefix . $field, true);

					switch ($keys['type']) {
						case 'check': {
							$yes = '<svg fill="#000000" height="18px" width="18px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 490 490"><polygon points="452.253,28.326 197.831,394.674 29.044,256.875 0,292.469 207.253,461.674 490,54.528 "></polygon></svg>';
							$no = '<svg fill="#000000"  height="16px" width="16px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M0 14.545L1.455 16 8 9.455 14.545 16 16 14.545 9.455 8 16 1.455 14.545 0 8 6.545 1.455 0 0 1.455 6.545 8z" fill-rule="evenodd"></path></svg>';
							echo ($meta_value == 'yes') ? $yes : $no;
							break;
						}
						default: {
							if (filter_var($meta_value, FILTER_VALIDATE_EMAIL)) {
								$meta_value = '<a href="mailto:' . $meta_value . '">' . $meta_value . '</a>';
							}

							echo apply_filters(strtolower(__CLASS__) . '_post_column_data', $meta_value, $column_key, $post_id);
						}
					}
				}
			}
		}
	}

	public static function posts_columns($columns) {
		$type = $_GET['post_type'];

		if (isset(self::$posts[$type])) {
			unset($columns['date']);

			$fields = self::get_meta_fields(self::$posts[$type]['sections']);

			if (!count($fields)) {
				return;
			}

			foreach ($fields as $field => $keys) {
				if (isset($keys['column']) && $keys['column']) {
					$columns[$field] = $keys['label'];
				}
			}
			
			$columns['date'] = 'Date';
		}

		$columns = apply_filters(strtolower(__CLASS__) . '_post_column_title', $columns);

		return $columns;
	}

	public static function sort_post_column_query($query) {
		if (!is_admin() || !$query->is_main_query()) {
			return;
		}

		$type = $_GET['post_type'] ?? null;

		if ($type && isset(self::$posts[$type])) {
			$orderby = $query->get('orderby');
			$prefix = self::prefix($type);
			$fields = self::get_meta_fields(self::$posts[$type]['sections']);

			if (!count($fields)) {
				return;
			}

			foreach ($fields as $field => $keys) {
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
			$fields = self::get_meta_fields(self::$posts[$type]['sections']);

			if (!count($fields)) {
				return;
			}

			foreach ($fields as $field => $keys) {
				if (isset($keys['column']) && isset($keys['sort'])) {
					$columns[$field] = $field;
				}
			}
		}
		return $columns;
	}

	// add buttons to post list page

	public static function post_list_buttons() {
		global $pagenow;

		$type = isset($_GET['post_type']) ? $_GET['post_type'] : null;

		if ($type && isset(self::$posts[$type])) {
			self::importer($type, 'post');
?>
			<script>
				jQuery(function($) {
					let e = $('.page-title-action');
					let b = ' &nbsp; &nbsp; <a href="<?php echo get_admin_url() . 'admin.php?action=download&type=' . $type; ?>" class="page-title-action">Export <?php echo self::label($type); ?> as CSV</a> <input id="p-csv-f" type="file" class="hidden"><span id="p-csv" class="page-title-action">Import <?php echo self::label($type); ?></span><div class="progress">&nbsp;</div>';
					e.addClass('primary').after(b);
					$('#p-csv').on('click', function(e) {
						$('#p-csv-f').click();
					});
					$('#p-csv-f').on('change', function(e) {
						bt_read($('#p-csv-f').prop('files')[0]);
					});
				});
			</script>
<?php
		}
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

			if ($parsed_url) {
				parse_str($parsed_url, $params);
			}

			if (isset($params['paged'])) {
				$paged = '&paged=' . $params['paged'];
			}
		}

		if (isset(self::$posts[$type])) {
			echo '<br><a class="button button-primary" href="/wp-admin/edit.php?post_type=' . $type . $paged . '">Back to ' . self::label($type) . ' List&hellip;</a><br><br>';
		}
	}

	// add filters to post list page

	public static function add_post_filters() {
		$type = $_GET['post_type'] ?? null;

		if (!$type) {
			return;
		}

		if (!isset(self::$posts[$type])) {
			// not one of ours
			return;
		}

		$taxes = get_object_taxonomies($type);

		foreach ($taxes as $tax) {
			$terms = get_terms($tax);

			if (!empty($terms)) {
				echo '<select name="' . $tax . '" id="' . $tax . '" class="postform">';
				echo '<option value="">' . 'All ' . self::label($tax) . '</option>';

				$added = [];

				foreach ($terms as $term) {
					if (!in_array($term->term_id, $added)) {
						$selected = (isset($_GET[$tax]) && ($_GET[$tax] == $term->slug)) ? ' selected="selected"' : '';
						echo '<option value="' . $term->slug . '"' . $selected . '>' . $term->name . '</option>';
						$added[] = $term->term_id;
					}
				}

				echo '</select>';
			}
		}

		// add our ajax search box

		self::data_api();
		$admin_url = get_admin_url();

		$html = <<<HTML
			<input id="bt-search" type="search" value="" autocomplete="off" placeholder="Type 3 characters or more to search..." style="margin-left:50px;width:260px">
			<script>
				jQuery(function($) {
					const plugin = basic_types;
					const s = $('#bt-search');
					const o = $('#the-list').clone(true, true);
					s.appendTo(s.parent());
					const h = debounce(function(e) {
						const t = $('#the-list');
						if (s.val().length > 2) {
							$.ajax({
								method: 'GET',
								url: plugin.api.url,
								beforeSend: function(xhr) {
									xhr.setRequestHeader('X-WP-Nonce', plugin.api.nonce);
								},
								data: {
									type: '{$type}',
									search: s.val(),
									page: 1,
									per: 50,
									status: 'any'
								}
							})
							.then(function(r) {
								let tr = $('#the-list tr:first').clone();
								tr.find('th, td').text('');
								t.empty();
								if (r.total == 0) {
									var nr = tr.clone();
									nr.attr('id', 'no-posts');
									nr.find('.title').html('<strong>Nothing found</strong>');
									t.append(nr);
								}
								else {
									r.results.forEach(function(result) {
										var nr = tr.clone();
										nr.attr('id', 'post-' + result.id);
										nr.find('.title').html('<strong><a class="row-title" href="{$admin_url}post.php?post=' + result.id + '&amp;action=edit" aria-label="“' + result.title + '” (Edit)">' + result.title + '</a></strong>');
										t.append(nr);
									});
								}
							});
						}
						else {
							t.replaceWith(o.clone(true, true));
						}
					}, 300);
					s.on('keyup', h);
				});
			</script>
HTML;
		echo $html;
	}

	// apply filters to post list page

	public static function apply_post_filters($query) {
		global $pagenow;

		if (!is_admin() || !$query->is_main_query()) {
			return;
		}

		$type = $_GET['post_type'] ?? null;

		if (!isset(self::$posts[$type])) {
			// not one of ours
			return;
		}

		if ($pagenow === 'edit.php' && $type) {
			$taxes = get_object_taxonomies($type);

			foreach ($taxes as $tax) {
				if (!empty($_GET[$tax])) {
					$query->query_vars[$tax] = $_GET[$tax];
				}
			}
		}
	}

	public static function auto_taxonomies() {
		global $typenow;

		if (!isset(self::$posts[$typenow])) {
			return;
		}

		$taxes = get_object_taxonomies($typenow, 'names');

		if (count($taxes) > 0) {
			echo '<script>jQuery(function($) {';

			foreach ($taxes as $tax) {
				$id = isset($_GET[$tax]) ? intval($_GET[$tax]) : 0;
				if ($id) {
					echo '$("#in-' . $tax . '-' . $id . '-2").prop("checked", true);';
				}
			}

			echo '});</script>';
		}
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

		self::data_api();

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
		$keys = self::$taxes[$taxonomy]['fields'];
		$label = ucwords(strtolower(str_replace('_', ' ', $taxonomy)));

		$hierarchical = self::$taxes[$taxonomy]['hierarchical'] ?? false;
		$show_description = self::$taxes[$taxonomy]['description'] ?? false;
		$show_slug = self::$taxes[$taxonomy]['slug'] ?? false;

		$taxonomies = self::$taxes[$taxonomy]['taxonomies'] ?? [];

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
								<h2 class="hndle ui-sortable-handle">Actions</h2>
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
			$meta_box = apply_filters(strtolower(__CLASS__) . '_taxonomy_meta_box', []);

			if (count($meta_box) > 0) {
				foreach ($meta_box as $mb) {
					$slug = sanitize_title($mb['title']);
?>
						<div id="btmetadiv-<?php echo $slug; ?>" class="postbox bt-postbox">
							<div class="postbox-header">
								<h2 class="hndle ui-sortable-handle"><?php echo $mb['title']; ?></h2>
								<div class="handle-actions hide-if-no-js"></div>
							</div>
							<div class="inside">
								<div class="submitbox" id="btmetacontent-<?php echo $slug; ?>">
									<?php echo $mb['content']; ?>
								</div>
							</div>
						</div>
<?php
				}
			}

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
					$terms = self::get_terms($category);
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

		wp_nonce_field(plugins_url(__FILE__), 'bt_nonce');
		wp_enqueue_media();

		if (is_string($term)) {
			// this is a new term

			$a = ($action == 'new') ? 'right' : 'left';
			$b = ($action == 'new') ? 'left' : 'right';

			echo '<style>#col-' . $a . '{display:none}#col-' . $b . '{width:100%}#titlediv #tag-name{padding:3px 8px;font-size:1.7em;line-height:100%;height:1.7em;width:100%;outline: 0;margin:0 0 3px;background-color:#fff}</style>';
		}
		else {
			// editing existing term

			$fields = self::get_meta_fields(self::$taxes[$taxonomy]['sections']);

			if (count($fields) > 0) {
				foreach ($fields as $field => $details) {
					$field_values[$field] = get_term_meta($term->term_id, $prefix . $field, true);
				}		
			}

			echo '<style>#edittag{max-width:100%}#titlediv #name{padding:3px 8px;font-size:1.7em;line-height:100%;height:1.7em;width:100%;outline: 0;margin:0 0 3px;background-color:#fff}</style>';
		}

		if ($action != 'list') {
			$name_element = ($action == 'new') ? 'tag-name' : 'name';
			$slug_element = ($action == 'new') ? 'tag-slug' : 'slug';
			$desc_element = ($action == 'new') ? 'tag-description' : 'description';

			self::gen_css();
			self::gen_js();
?>
							<script>
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

									ft();

									let w = $('.wp-own');
									if (w.length == 1) {
										w.removeClass('top middle').addClass('bottom');
									}
								});
							</script>
							<div class="inside">
<?php
			$count = 0;

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

			foreach(self::$taxes[$taxonomy]['sections'] as $section => $data) {
				if (isset($data['label']) && $data['label'] != '') {
					echo '<div class="title"><h3>' . $data['label'] . '</h3></div>';
				}

				$count = 1;

				foreach ($data['fields'] as $field => $keys) {

					// set box class
					switch ($count) {
						case count($data['fields']): {
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
					$fval = $field_values[$field] ?? '';

					self::gen_fields('taxonomy', $taxonomy, $field, $fval, $keys);
?>
								</div>
<?php
					$count++;
				}
			}
?>
							</div>
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
			$fields = self::get_meta_fields(self::$taxes[$type]['sections']);

			if (count($fields)) {
				foreach ($fields as $field => $value) {
					if (array_key_exists($prefix . $field, $_POST)) {
						update_term_meta(
							$term_id,
							$prefix . $field,
							$_POST[$prefix . $field]
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
							implode(',', $term_ids)
						);
					}
				}
			}

			wp_redirect(admin_url('term.php?taxonomy=' . $type . '&tag_ID=' . $term_id));
			exit;
		}
	}

	// taxonomy list view columns

	public static function taxonomy_columns($columns) {
		$type = $_GET['taxonomy'] ?? null;

		if ($type && isset(self::$taxes[$type])) {
			unset($columns['description']);
			unset($columns['slug']);
			unset($columns['posts']);

			$fields = self::get_meta_fields(self::$taxes[$type]['sections']);

			if (!count($fields)) {
				return;
			}

			foreach ($fields as $field => $keys) {
				if (isset($keys['column']) && $keys['column']) {
					$columns[$field] = $keys['label'];
				}
			}

			$columns = apply_filters(strtolower(__CLASS__) . '_taxonomy_column_title', $columns);
		}

		return $columns;
	}

	public static function taxonomy_column_views($output, $column_key, $term_id) {
		$type = $_GET['taxonomy'] ?? null;

		if ($type && isset(self::$taxes[$type])) {
			$prefix = self::prefix($type);

			$fields = self::get_meta_fields(self::$taxes[$type]['sections']);

			if (!count($fields)) {
				return $output;
			}

			foreach ($fields as $field => $keys) {
				if (($field == $column_key) && !empty($keys['column'])) {
					$meta_value = get_term_meta($term_id, $prefix . $field, true);

					switch ($keys['type']) {
						case 'check': {
							$yes = '<svg fill="#000000" height="18px" width="18px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 490 490"><polygon points="452.253,28.326 197.831,394.674 29.044,256.875 0,292.469 207.253,461.674 490,54.528 "></polygon></svg>';
							$no = '<svg fill="#000000"  height="16px" width="16px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M0 14.545L1.455 16 8 9.455 14.545 16 16 14.545 9.455 8 16 1.455 14.545 0 8 6.545 1.455 0 0 1.455 6.545 8z" fill-rule="evenodd"></path></svg>';
							return ($meta_value == 'yes') ? $yes : $no;
							break;
						}
						default: {
							if (filter_var($meta_value, FILTER_VALIDATE_EMAIL)) {
								$meta_value = '<a href="mailto:' . $meta_value . '">' . $meta_value . '</a>';
							}

							return apply_filters(strtolower(__CLASS__) . '_taxonomy_column_data', $meta_value, $column_key, $post_id);
						}
					}
				}
			}
		}

		return $output;
	}

	// add buttons to custom taxonomy list page

	public static function taxonomy_list_buttons($taxonomy) {
		$screen = get_current_screen();
		$action = $_GET['action'] ?? 'list';

		if ($screen->base == 'edit-tags' && $action != 'new') {
			if ($taxonomy && isset(self::$taxes[$taxonomy])) {
				self::importer($taxonomy, 'taxonomy');
?>
				<script>
					jQuery(function($) {
						let e = $('h1.wp-heading-inline');
						let b = '<a class="page-title-action primary" href="/wp-admin/edit-tags.php?taxonomy=<?php echo $taxonomy; ?>&action=new">Add New</a> &nbsp; &nbsp; <a href="<?php echo get_admin_url(); ?>admin.php?action=download&type=<?php echo $taxonomy; ?>" class="page-title-action">Export <?php echo self::label($taxonomy); ?> as CSV</a> <input id="t-csv-f" type="file" class="hidden"><span id="t-csv" class="page-title-action">Import <?php echo self::label($taxonomy); ?></span><div class="progress">&nbsp;</div>';
						e.after(b);
						$('#t-csv').on('click', function(e) {
							$('#t-csv-f').click();
						});
						$('#t-csv-f').on('change', function(e) {
							bt_read($('#t-csv-f').prop('files')[0]);
						});
					});
				</script>
<?php
			}
		}
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
			$prefix = self::prefix($role->name);

			$field_values = [];

			if (isset(self::$roles['add'][$role->name])) {
				$fields = self::get_meta_fields(self::$roles['add'][$role->name]['sections']);

				if (count($fields) > 0) {
					foreach ($fields as $field => $details) {
						$field_values[$field] = get_user_meta($user->ID, $prefix . $field, true);
					}		
				}
			}

			$taxonomies = (isset(self::$roles['add'][$role->name]['taxonomies'])) ? self::$roles['add'][$role->name]['taxonomies'] : [];
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
			if (current_user_can('manage_options') && count($taxonomies) > 0) {
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
					$terms = self::get_terms($category);
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
		wp_nonce_field(plugins_url(__FILE__), 'bt_nonce');
		wp_enqueue_media();

		self::data_api();

		self::gen_css();
		self::gen_js();
?>
								<script>
									$(function() {
										let s = $('#user_login').detach();
										s.css({'width':'99%'});
										$('#meta-user_login').append(s);

										let r = $('#role').detach();
										r.css({'width':'99%'});
<?php
	if (_BT['bt_hide_admin'] == 'yes' && !self::is_dev()) {
?>
										r.find('option[value="administrator"]').remove();
<?php
	}
?>
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
			$class = (isset(self::$roles['add'][$role->name]) && isset(self::$roles['add'][$role->name]['fields'])) ? 'middle' : 'bottom';
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

			if (count($field_values) > 0) {
				// this role has some meta fields

				foreach (self::$roles['add'][$role->name]['sections'] as $section => $data) {
					if (isset($data['label']) && $data['label'] != '') {
						echo '<div class="title"><h3>' . $data['label'] . '</h3></div>';
					}

					$count = 1;

					foreach ($data['fields'] as $field => $keys) {

						// set box class
						switch ($count) {
							case count($data['fields']): {
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
						$fval = $field_values[$field] ?? '';

						self::gen_fields('roles', $role->name, $field, $fval, $keys);
?>
									</div>
<?php
						$count++;
					}
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

			if (isset(self::$roles['add'][$role->name]) && isset(self::$roles['add'][$role->name]['sections'])) {
				$prefix = self::prefix($role->name);

				$fields = self::get_meta_fields(self::$roles['add'][$role->name]['sections']);
				$keys = self::$roles['add'][$role->name]['fields'];

				if (count($fields)) {
					foreach ($fields as $field => $value) {
						if (array_key_exists($prefix . $field, $_POST)) {
							update_user_meta(
								$user_id,
								$prefix . $field,
								$_POST[$prefix . $field]
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
							implode(',', $term_ids)
						);
					}
				}
			}
		}
	}

	// user view columns

	public static function manage_users_columns($columns) {
		unset($columns['posts']);

		$columns = apply_filters(strtolower(__CLASS__) . '_user_column_title', $columns);

		return $columns;
	}

	public static function manage_users_custom_column($output, $column_key, $user_id) {
		$output = apply_filters(strtolower(__CLASS__) . '_user_column_data', $output, $column_key, $user_id);

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

	//     ▄█    █▄        ▄████████   ▄█           ▄███████▄  
	//    ███    ███      ███    ███  ███          ███    ███  
	//    ███    ███      ███    █▀   ███          ███    ███  
	//   ▄███▄▄▄▄███▄▄   ▄███▄▄▄      ███          ███    ███  
	//  ▀▀███▀▀▀▀███▀   ▀▀███▀▀▀      ███        ▀█████████▀   
	//    ███    ███      ███    █▄   ███          ███         
	//    ███    ███      ███    ███  ███▌    ▄    ███         
	//    ███    █▀       ██████████  █████▄▄██   ▄████▀

	// get a meta value for a post or term by meta key

	public static function get($id, $field, $is_json = false) {
		$type = get_post_type($id);
		$function = 'post';

		if (!$type || $type == 'page') {
			$type = get_term($id)->taxonomy;
			$function = 'term';
		}

		$data = call_user_func_array('get_' . $function . '_meta', [$id, self::prefix($type) . $field, true]);

		return ($is_json) ? json_decode($data, true) : $data;
	}

	// get all meta data for a post or term

	public static function get_all($id, $is_json = false) {
		$type = get_post_type($id);
		$function = 'post';

		if (!$type || $type == 'page') {
			$type = get_term($id)->taxonomy;
			$function = 'term';
		}

		$data = call_user_func_array('get_' . $function . '_meta', [$id]);

		$result = [];

		foreach ($data as $key => $value) {
			$remove = self::prefix($type);
			$result[str_replace($remove, '', $key)] = $value[0];
		}

		return ($is_json) ? json_decode($result, true) : $result;
	}

	// set a meta value of a field for a post or term

	public static function set($id, $field, $value) {
		$type = get_post_type($id);
		$function = 'post';

		if (!$type) {
			$type = get_term($id)->taxonomy;
			$function = 'term';
		}

		return call_user_func_array('update_' . $function . '_meta', [$id, self::prefix($type) . $field, $value]);
	}

	// if the meta value is JSON this will set a
	// value of a key in that array, overwriting an
	// existing value or creating the key and assigning
	// the value if it doesn't exist

	public static function update($id, $field, $key, $value = false) {
		$type = get_post_type($id);
		$function = 'post';

		if (!$type) {
			$type = get_term($id)->taxonomy;
			$function = 'term';
		}

		$data = json_decode(call_user_func_array('get_' . $function . '_meta', [$id, self::prefix($type) . $field, true]), true);

		if ($value) {
			$data[$key] = $value;
		}
		else {
			unset($data[$key]);
		}

		return call_user_func_array('update_' . $function . '_meta', [$id, self::prefix($type) . $field, json_encode($data)]);
	}

	// if the meta value is JSON, this will delete
	// a key/value pair in that array using an overloaded
	// version of the 'update' function above

	public static function delete($id, $field, $key) {
		return self::update($id, $field, $key, false);
	}

	// returns an array of all meta fields for a post or term

	public static function fields($type) {
		$fields = [];
		$data_type = null;

		if (isset(self::$posts[$type])) {
			$data_type = self::$posts[$type];
		}
		else if (isset(self::$taxes[$type])) {
			$data_type = self::$taxes[$type];
		}

		if (count($data_type) > 0) {
			foreach ($data_type as $field => $keys) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	// returns an array of all keys for a meta field for a post/term

	public static function keys($type, $field) {
		if (isset(self::$posts[$type])) {
			return self::$posts[$type][$field];
		}
		else if (isset(self::$taxes[$type])) {
			return self::$taxes[$type][$field];
		}

		return false;
	}

	// creates a post object of the requested type

	public static function make($type, $title, $author_id = null) {
		return wp_insert_post([
			'post_title' => $title,
			'post_content' => '',
			'post_status' => 'publish',
			'post_date' => date('Y-m-d H:i:s'),
			'post_author' => $author_id ?? wp_get_current_user()->ID,
			'post_type' => $type,
			'post_category' => array(0)
		]);
	}

	// returns all terms of a taxonomy

	public static function get_terms($taxonomy) {
		$terms = [];

		if (isset(self::$taxes[$taxonomy])) {
			$terms = get_terms($taxonomy, [
				'hide_empty' => false
			]);
		}

		return $terms;
	}

	// returns all posts of a type

	public static function get_posts($type, $orderby = 'title', $order = 'ASC') {
		return get_posts([
			'post_type' => $type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => $orderby,
			'order' => $order
		]);
	}

	// returns all posts of a type of a specific term

	public static function get_posts_of_term($type, $tax, $term, $orderby = 'title', $order = 'ASC') {
		return get_posts([
			'post_type' => $type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => $orderby,
			'order' => $order,
			'tax_query' => [
				[
					'taxonomy' => $tax,
					'field' => 'slug',
					'terms' => $term
				]
			]
		]);
	}

	// returns all posts of a type sorted by a meta key/value

	public static function get_posts_sort_by_meta($type, $meta_key, $order = 'ASC') {
		return get_posts([
			'post_type' => $type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'meta_key' => $meta_key,
			'orderby' => 'meta_value',
			'order' => $order
		]);
	}

	// returns all posts of a type of a specific term sorted by a meta key/value

	public static function get_posts_of_term_sort_by_meta($type, $tax, $term, $meta_key, $order = 'ASC') {
		return get_posts([
			'post_type' => $type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'tax_query' => [
				[
					'taxonomy' => $tax,
					'field' => 'slug',
					'terms' => $term
				]
			],
			'meta_key' => $meta_key,
			'orderby' => 'meta_value',
			'order' => $order
		]);
	}

	// empty a taxonomy of all relationships and terms

	/*

	DELETE tr
	FROM wp_term_relationships tr
	JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
	WHERE tt.taxonomy = 'customer';

	DELETE FROM wp_term_taxonomy
	WHERE taxonomy = 'customer';

	DELETE t
	FROM wp_terms t
	LEFT JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
	WHERE tt.term_id IS NULL;

	*/

	// empty a post type of all posts, meta and relationships

	/*

	DELETE tr
	FROM wp_term_relationships tr
	JOIN wp_posts p ON tr.object_id = p.ID
	WHERE p.post_type = 'movie';

	DELETE pm
	FROM wp_postmeta pm
	JOIN wp_posts p ON pm.post_id = p.ID
	WHERE p.post_type = 'movie';

	DELETE c
	FROM wp_comments c
	JOIN wp_posts p ON c.comment_post_ID = p.ID
	WHERE p.post_type = 'movie';

	DELETE FROM wp_posts
	WHERE post_type = 'movie';

	*/

	//     ▄████████       ▄█     ▄████████  ▀████    ▐████▀  
	//    ███    ███      ███    ███    ███    ███▌   ████▀   
	//    ███    ███      ███    ███    ███     ███  ▐███     
	//    ███    ███      ███    ███    ███     ▀███▄███▀     
	//  ▀███████████      ███  ▀███████████     ████▀██▄      
	//    ███    ███      ███    ███    ███    ▐███  ▀███     
	//    ███    ███  █▄ ▄███    ███    ███   ▄███     ███▄   
	//    ███    █▀   ▀▀▀▀▀▀     ███    █▀   ████       ███▄

	public static function ajax() {
		$hash = $_POST['hash'] ?? null;
		$check = hash('sha1', 'e' . wp_get_current_user()->ID);
		$nonce = $_POST['nonce'] ?? null;

		if (!$nonce || !wp_verify_nonce($nonce, _PLUGIN)) {
			wp_send_json_error('security error (nonce failed)');
			die;
		}

		if (!$hash || $hash !== $check) {
			wp_send_json_error('security error (hash failed)');
			die;
		}

		$data = $_POST['payload'] ?? null;

		if (!$data) {
			wp_send_json_error('no data');
			die;
		}

		switch ($data['cmd']) {
			case 'import': {
				$type = $data['type'];
				$row = $data['data'];

				if (!is_array($row)) {
					wp_send_json_error('row is not an array');
					die;
				}

				$id = array_shift($row);
				$title = array_shift($row);

				switch ($data['port']) {
					case 'post': {
						if (is_int($id) && get_post($id)) {
							// we are updating a post

							wp_update_post([
								'ID' => $id,
								'post_title' => $title
							]);

							$result = 'updated post';	
						}
						else {
							// we are adding a new post

							$id = wp_insert_post([
								'post_title' => $title,
								'post_content' => '',
								'post_status' => 'publish',
								'post_date' => date('Y-m-d H:i:s'),
								'post_author' => wp_get_current_user()->ID,
								'post_type' => $type,
								'post_category' => array(0)
							]);

							$result = 'added post';
						}

						if (count($row) > 0) {
							foreach ($row as $field => $value) {
								if (self::keys($type, $field)) {
									update_post_meta($id, self::prefix($type) . $field, $value);
								}
								else {
									$term = term_exists($value, $field);

									if (!$term) {
										$term = wp_insert_term(ucwords(strtolower($value)), $field);
									}

									if (!is_wp_error($term)) {
										wp_set_post_terms($id, $term, $field, true);
									}
								}
							}
						}

						wp_send_json_success($result);
						die;
					}
					case 'taxonomy': {
						if (is_int($id) && get_term($id)) {
							// we are updating a term

							wp_update_term($id, $type, [
								'name' => $title
							]);

							$result = 'updated term';
						}
						else {
							// we are adding a new term

							$term = wp_create_term($title, $type);
							$id = $term['term_id'];

							$result = 'added term';
						}

						if (count($row) > 0) {
							foreach ($row as $field => $value) {
								update_term_meta($id, self::prefix($type) . $field, $value);
							}
						}

						wp_send_json_success($result);
						die;
					}
					default: {
						wp_send_json_error('unsupported type');
						die;
					}
				}

				break;
			}
			default: {
				wp_send_json_error('unsupported command');
				die;
			}
		}

		die;
	}

	public static function importer($type, $p_or_t) {
?>
		<style>
			.progress {
				display: none;
				position: relative;
				top: -4px;
				margin-left: 6px;
				border-radius: 3px;
				padding-top: 4px;
				border: 1px solid var(--primary-brand-colour);
				width: 120px;
				height: 24px;
				text-align: center;
			}
		</style>
		<script>
			function bt_csv(content) {
				const rows = content.split('\n');
				const headers = rows[0].split(',');

				const data = rows.slice(1).map(row => {
					const values = row.split(/,(?=(?:[^"]*"[^"]*")*[^"]*$)/);

					return headers.reduce((obj, header, index) => {
						obj[header.trim()] = values[index]?.trim();
						return obj;
					}, {});
				});

				return data;
			}
			function bt_ajax(data, i = 0) {
				const t = data.length - 1;
				if (i == t) {
					jQuery('.progress').text('Done').fadeOut(200);
					window.location.reload(true);
					return;
				}

				let v = Math.trunc((i / t) * 100);
				jQuery('.progress').css('display', 'inline-block').text(v + '% (' + i + ' of ' + t + ')');

				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'bt_ajax',
						hash: _bt.hash,
						nonce: _bt.nonce,
						payload: {
							port: '<?php echo $p_or_t; ?>',
							type: '<?php echo $type; ?>',
							cmd: 'import',
							data: data[i]
						}
					},
					success: function(response) {
						bt_ajax(data, i + 1);
					},
					error: function(xhr, status, error) {
						alert('There was an error processing the import: ' + error);
					}
				});
			}
			function bt_read(file) {
				if (file) {
					const reader = new FileReader();
					reader.onload = function(e) {
						const content = e.target.result;
						const rows = bt_csv(content);
						var p = 0, c = rows.length - 1, r = false;

						jQuery('#p-csv-f').val('');

						bt_ajax(rows, 0);
					};

					reader.readAsText(file);
				}
			}
		</script>
<?php
	}


	//     ▄████████     ▄████████     ▄████████     ▄████████   ▄████████     ▄█    █▄     
	//    ███    ███    ███    ███    ███    ███    ███    ███  ███    ███    ███    ███    
	//    ███    █▀     ███    █▀     ███    ███    ███    ███  ███    █▀     ███    ███    
	//    ███          ▄███▄▄▄        ███    ███   ▄███▄▄▄▄██▀  ███          ▄███▄▄▄▄███▄▄  
	//  ▀███████████  ▀▀███▀▀▀      ▀███████████  ▀▀███▀▀▀▀▀    ███         ▀▀███▀▀▀▀███▀   
	//           ███    ███    █▄     ███    ███  ▀███████████  ███    █▄     ███    ███    
	//     ▄█    ███    ███    ███    ███    ███    ███    ███  ███    ███    ███    ███    
	//   ▄████████▀     ██████████    ███    █▀     ███    ███  ████████▀     ███    █▀

	// extend searches in admin area for posts/taxonomies
	// to include meta data values for our custom types

	public static function posts_search($query) {
		if (!is_admin() || !$query->is_main_query() || !$query->is_search()) {
			return;
		}

		$screen = get_current_screen();
		if ($screen && $screen->base !== 'edit') {
			return;
		}

		if (!isset(self::$posts[$query->get('post_type')])) {
			return;
		}

		add_filter('posts_join', __CLASS__ . '::posts_search_join');
		add_filter('posts_where', __CLASS__ . '::posts_search_where');
		add_filter('posts_distinct', function() { return 'DISTINCT'; });
	}

	public static function posts_search_join($join) {
		global $wpdb;

		return $join . " LEFT JOIN {$wpdb->postmeta} AS pm ON pm.post_id = {$wpdb->posts}.ID ";
	}

	public static function posts_search_where($where) {
		global $wpdb;

		if (!isset($_GET['s']) || empty($_GET['s'])) {
			return $where;
		}

		$search = esc_sql($wpdb->esc_like(sanitize_text_field($_GET['s'])));
		return $where . " OR pm.meta_value LIKE '%{$search}%'";
	}

	public static function terms_search($clauses, $taxonomies, $args) {
		if (empty($args['search']) || !is_admin() || !isset($_GET['s']) || empty($taxonomies)) {
			return $clauses;
		}

		if (empty(array_intersect_key(self::$taxes, array_flip($taxonomies)))) {
			return $clauses;
		}

		global $wpdb;

		$clauses['fields'] = 'DISTINCT ' . $clauses['fields'];

		$clauses['join'] .= " LEFT JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id ";

		$search = esc_sql($wpdb->esc_like($args['search']));
		$clauses['where'] .= " OR (tm.meta_value LIKE '%$search%') ";

		$clauses['groupby'] = 't.term_id';

		return $clauses;
	}


	//  ████████▄    ▄██████▄    ▄█     █▄   ███▄▄▄▄▄    
	//  ███   ▀███  ███    ███  ███     ███  ███▀▀▀▀██▄  
	//  ███    ███  ███    ███  ███     ███  ███    ███  
	//  ███    ███  ███    ███  ███     ███  ███    ███  
	//  ███    ███  ███    ███  ███     ███  ███    ███  
	//  ███    ███  ███    ███  ███     ███  ███    ███  
	//  ███   ▄███  ███    ███  ███ ▄█▄ ███  ███    ███  
	//  ████████▀    ▀██████▀    ▀███▀███▀    ▀█    █▀

	//   ▄█         ▄██████▄      ▄████████  ████████▄   
	//  ███        ███    ███    ███    ███  ███   ▀███  
	//  ███        ███    ███    ███    ███  ███    ███  
	//  ███        ███    ███    ███    ███  ███    ███  
	//  ███        ███    ███  ▀███████████  ███    ███  
	//  ███        ███    ███    ███    ███  ███    ███  
	//  ███▌    ▄  ███    ███    ███    ███  ███   ▄███  
	//  █████▄▄██   ▀██████▀     ███    █▀   ████████▀

	public static function headers($name) {
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . $name . '"');
	}

	public static function handle_download() {
		if (isset($_GET['action']) && $_GET['action'] === 'download') {

			if (!is_user_logged_in()) {
				wp_die('You must be logged in to access this file');
			}

			$type = $_GET['type'] ?? null;

			if (!$type) {
				wp_die('No type requested');
			}

			if (substr($type, 0, 1) != '_') {
				list($name, $data) = self::fetch_data($type);

				if ($name && $data) {
					self::headers($name);
					echo $data;
					exit;
				}
			}
			else {
				switch(substr($type, 1)) {
					case 'barcode': {
						$name = 'barcode.svg';
						self::headers($name);
						break;
					}
				}
			}
		}
	}

	public static function fetch_data($type) {
		$result = apply_filters(strtolower(__CLASS__) . '_download_data', false, $type);

		if ($result) {
			$name = $result['name'];
			$data = $result['data'];
		}
		else {
			$cols = ['id', 'title'];
			$p_or_t = null;

			if (isset(self::$posts[$type])) {
				foreach (self::$posts[$type] as $field => $keys) {
					$cols[] = $field;
				}

				$p_or_t = 'post';

				$objects = get_posts([
					'post_type' => $type,
					'post_status' => 'publish',
					'posts_per_page' => -1,
					'orderby' => 'id',
					'order' => 'ASC'
				]);
			}

			if (isset(self::$taxes[$type])) {
				foreach (self::$taxes[$type]['fields'] as $field => $keys) {
					$cols[] = $field;
				}

				$p_or_t = 'taxonomy';

				// we need to register our
				// taxonomies here because
				// wordpress is retarded -_-

				self::register_taxes();

				$objects = get_terms([
					'taxonomy' => $type,
					'hide_empty' => false
				]);
			}

			$name = $type . '.csv';
			$data = implode(',', $cols) . "\n";

			if (count($objects) > 0 && $p_or_t) {
				foreach ($objects as $object) {
					$id = ($p_or_t == 'post') ? $object->ID : $object->term_id;

					$row = [];
					$row[] = $id;
					$row[] = ($p_or_t == 'post') ? $object->post_title : $object->name;

					$col_count = count($cols) - 2;

					if ($col_count > 0) {
						for ($c = 0; $c < $col_count; $c++) {
							$row[] = self::get($id, $cols[$c + 2]);
						}
					}

					$data .= implode(',', $row) . "\n";
				}
			}
		}

		return [$name, $data];
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