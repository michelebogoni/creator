/**
 * @fileoverview WordPress Core API Documentation
 * @module services/wordpressCoreDocs
 *
 * @description
 * Comprehensive WordPress Core API documentation organized by topic.
 * Used as context for AI when generating WordPress code.
 *
 * Slug format: wordpress-core/{topic}
 * Version format: Major.Minor (e.g., "6.7", "6.6")
 */

/**
 * WordPress Core Documentation Entry
 */
export interface WordPressCoreDocs {
  docs_url: string;
  api_reference?: string;
  description: string;
  main_functions: string[];
  code_examples?: string[];
  best_practices?: string[];
  since_version?: string;
}

/**
 * WordPress Core Documentation by Topic
 *
 * These are pre-populated and don't require AI research.
 * Covers all major WordPress APIs.
 */
export const WORDPRESS_CORE_DOCS: Record<string, WordPressCoreDocs> = {
  /**
   * Media Library API
   * Critical for proper image/file uploads
   */
  "wordpress-core/media": {
    docs_url: "https://developer.wordpress.org/plugins/media/",
    api_reference: "https://developer.wordpress.org/reference/functions/wp_insert_attachment/",
    description: "WordPress Media Library API for uploading, managing, and retrieving media files.",
    main_functions: [
      "wp_insert_attachment( $attachment, $filename, $parent_post_id ) - Inserts an attachment into the media library database",
      "wp_generate_attachment_metadata( $attachment_id, $file ) - Generates metadata for an attachment (thumbnails, sizes)",
      "wp_update_attachment_metadata( $attachment_id, $data ) - Updates attachment metadata",
      "wp_get_attachment_url( $attachment_id ) - Gets the URL for an attachment",
      "wp_get_attachment_image( $attachment_id, $size, $icon, $attr ) - Gets an HTML img element for an attachment",
      "wp_get_attachment_image_src( $attachment_id, $size ) - Gets attachment image src array [url, width, height]",
      "get_attached_file( $attachment_id ) - Gets the path to an attached file",
      "wp_get_attachment_metadata( $attachment_id ) - Gets attachment metadata",
      "media_handle_upload( $file_id, $post_id ) - Handles file upload from $_FILES and creates attachment",
      "media_handle_sideload( $file_array, $post_id ) - Handles file sideload (from URL) and creates attachment",
      "wp_upload_dir() - Returns upload directory paths and URLs",
      "wp_check_filetype( $filename, $mimes ) - Validates file type",
      "wp_get_mime_types() - Gets allowed mime types",
      "download_url( $url, $timeout ) - Downloads a URL to a local temp file",
      "wp_delete_attachment( $attachment_id, $force_delete ) - Deletes an attachment",
    ],
    code_examples: [
      `// CORRECT: Upload image to Media Library
$upload_dir = wp_upload_dir();
$image_data = file_get_contents( $image_url );
$filename = basename( $image_url );
$file = $upload_dir['path'] . '/' . $filename;
file_put_contents( $file, $image_data );

// Check file type
$filetype = wp_check_filetype( $filename, null );

// Prepare attachment data
$attachment = array(
    'post_mime_type' => $filetype['type'],
    'post_title'     => sanitize_file_name( $filename ),
    'post_content'   => '',
    'post_status'    => 'inherit'
);

// Insert into Media Library
$attach_id = wp_insert_attachment( $attachment, $file );

// Generate metadata (thumbnails, etc.)
require_once( ABSPATH . 'wp-admin/includes/image.php' );
$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
wp_update_attachment_metadata( $attach_id, $attach_data );`,

      `// CORRECT: Sideload image from URL
require_once( ABSPATH . 'wp-admin/includes/file.php' );
require_once( ABSPATH . 'wp-admin/includes/media.php' );
require_once( ABSPATH . 'wp-admin/includes/image.php' );

$tmp = download_url( $image_url );
$file_array = array(
    'name'     => basename( $image_url ),
    'tmp_name' => $tmp,
);

$attach_id = media_handle_sideload( $file_array, $post_id );
if ( is_wp_error( $attach_id ) ) {
    @unlink( $tmp );
    return $attach_id;
}`,
    ],
    best_practices: [
      "ALWAYS use wp_insert_attachment() to add files to Media Library - never just copy to uploads folder",
      "ALWAYS call wp_generate_attachment_metadata() after wp_insert_attachment() to create thumbnails",
      "ALWAYS include required files: wp-admin/includes/image.php, file.php, media.php",
      "Use media_handle_sideload() for downloading remote images - it handles everything automatically",
      "Use wp_check_filetype() to validate file types before uploading",
      "Use sanitize_file_name() for filenames",
      "Set proper post_mime_type for the attachment",
    ],
  },

  /**
   * Posts API
   */
  "wordpress-core/posts": {
    docs_url: "https://developer.wordpress.org/plugins/post-types/",
    api_reference: "https://developer.wordpress.org/reference/functions/wp_insert_post/",
    description: "WordPress Posts API for creating, updating, and managing posts and custom post types.",
    main_functions: [
      "wp_insert_post( $postarr, $wp_error, $fire_after_hooks ) - Inserts or updates a post",
      "wp_update_post( $postarr, $wp_error, $fire_after_hooks ) - Updates a post",
      "wp_delete_post( $postid, $force_delete ) - Deletes a post",
      "wp_trash_post( $post_id ) - Moves a post to trash",
      "get_post( $post, $output, $filter ) - Retrieves post data",
      "get_posts( $args ) - Retrieves an array of posts",
      "get_post_meta( $post_id, $key, $single ) - Retrieves post meta field",
      "update_post_meta( $post_id, $meta_key, $meta_value ) - Updates post meta field",
      "add_post_meta( $post_id, $meta_key, $meta_value, $unique ) - Adds post meta field",
      "delete_post_meta( $post_id, $meta_key, $meta_value ) - Deletes post meta field",
      "register_post_type( $post_type, $args ) - Registers a custom post type",
      "get_post_type( $post ) - Gets the post type",
      "get_post_types( $args, $output, $operator ) - Gets registered post types",
      "set_post_thumbnail( $post, $thumbnail_id ) - Sets the featured image",
      "get_the_post_thumbnail( $post, $size, $attr ) - Gets the featured image HTML",
      "has_post_thumbnail( $post ) - Checks if post has featured image",
    ],
    code_examples: [
      `// Create a new post
$post_data = array(
    'post_title'    => wp_strip_all_tags( $title ),
    'post_content'  => $content,
    'post_status'   => 'publish',
    'post_type'     => 'post',
    'post_author'   => get_current_user_id(),
);
$post_id = wp_insert_post( $post_data, true );

if ( is_wp_error( $post_id ) ) {
    // Handle error
}

// Set featured image (attachment must exist in Media Library)
set_post_thumbnail( $post_id, $attachment_id );

// Add custom meta
update_post_meta( $post_id, '_custom_field', $value );`,

      `// Register custom post type
register_post_type( 'product', array(
    'labels' => array(
        'name' => 'Products',
        'singular_name' => 'Product',
    ),
    'public' => true,
    'has_archive' => true,
    'supports' => array( 'title', 'editor', 'thumbnail' ),
    'show_in_rest' => true,
));`,
    ],
    best_practices: [
      "Use wp_insert_post() with $wp_error = true to catch errors",
      "Always sanitize post title with wp_strip_all_tags()",
      "Use wp_kses_post() to sanitize post content if accepting HTML",
      "Set post_status appropriately: 'draft', 'publish', 'private', 'pending'",
      "Use get_current_user_id() for post_author",
    ],
  },

  /**
   * Users API
   */
  "wordpress-core/users": {
    docs_url: "https://developer.wordpress.org/plugins/users/",
    api_reference: "https://developer.wordpress.org/reference/functions/wp_insert_user/",
    description: "WordPress Users API for creating, managing, and authenticating users.",
    main_functions: [
      "wp_insert_user( $userdata ) - Inserts a user into the database",
      "wp_update_user( $userdata ) - Updates a user in the database",
      "wp_delete_user( $id, $reassign ) - Deletes a user",
      "wp_create_user( $username, $password, $email ) - Creates a new user (simplified)",
      "get_user_by( $field, $value ) - Gets user data by field (id, slug, email, login)",
      "get_userdata( $user_id ) - Gets user data by ID",
      "get_users( $args ) - Gets users matching criteria",
      "get_current_user_id() - Gets current logged-in user ID",
      "wp_get_current_user() - Gets current logged-in user object",
      "is_user_logged_in() - Checks if user is logged in",
      "current_user_can( $capability ) - Checks if current user has capability",
      "user_can( $user, $capability ) - Checks if specific user has capability",
      "get_user_meta( $user_id, $key, $single ) - Gets user meta field",
      "update_user_meta( $user_id, $meta_key, $meta_value ) - Updates user meta",
      "add_user_meta( $user_id, $meta_key, $meta_value, $unique ) - Adds user meta",
      "wp_set_password( $password, $user_id ) - Sets user password",
      "wp_check_password( $password, $hash, $user_id ) - Checks password against hash",
    ],
    code_examples: [
      `// Create a new user
$userdata = array(
    'user_login'    => sanitize_user( $username ),
    'user_pass'     => $password,
    'user_email'    => sanitize_email( $email ),
    'display_name'  => sanitize_text_field( $display_name ),
    'role'          => 'subscriber',
);
$user_id = wp_insert_user( $userdata );

if ( is_wp_error( $user_id ) ) {
    $error_message = $user_id->get_error_message();
}`,
    ],
    best_practices: [
      "Always sanitize user input: sanitize_user(), sanitize_email(), sanitize_text_field()",
      "Use wp_insert_user() for full control, wp_create_user() for simple cases",
      "Check is_wp_error() on return values",
      "Use get_user_by('email', $email) to check if user exists before creating",
      "Never store plain text passwords - WordPress handles hashing automatically",
    ],
  },

  /**
   * Options API
   */
  "wordpress-core/options": {
    docs_url: "https://developer.wordpress.org/plugins/settings/options-api/",
    api_reference: "https://developer.wordpress.org/reference/functions/get_option/",
    description: "WordPress Options API for storing and retrieving site settings.",
    main_functions: [
      "get_option( $option, $default ) - Gets an option value",
      "update_option( $option, $value, $autoload ) - Updates an option value",
      "add_option( $option, $value, $deprecated, $autoload ) - Adds a new option",
      "delete_option( $option ) - Deletes an option",
      "get_site_option( $option, $default ) - Gets network option (multisite)",
      "update_site_option( $option, $value ) - Updates network option",
      "register_setting( $option_group, $option_name, $args ) - Registers a setting",
      "get_transient( $transient ) - Gets a transient value",
      "set_transient( $transient, $value, $expiration ) - Sets a transient with expiration",
      "delete_transient( $transient ) - Deletes a transient",
    ],
    code_examples: [
      `// Store plugin settings
$settings = array(
    'api_key' => sanitize_text_field( $api_key ),
    'enabled' => (bool) $enabled,
);
update_option( 'my_plugin_settings', $settings );

// Retrieve with default
$settings = get_option( 'my_plugin_settings', array(
    'api_key' => '',
    'enabled' => false,
));`,

      `// Use transients for caching
$data = get_transient( 'my_cached_data' );
if ( false === $data ) {
    $data = expensive_function();
    set_transient( 'my_cached_data', $data, HOUR_IN_SECONDS );
}`,
    ],
    best_practices: [
      "Always provide a default value in get_option()",
      "Use transients for cached data that expires",
      "Prefix option names with plugin slug to avoid conflicts",
      "Use autoload = false for large data not needed on every page load",
      "Sanitize all data before storing",
    ],
  },

  /**
   * Database API
   */
  "wordpress-core/database": {
    docs_url: "https://developer.wordpress.org/reference/classes/wpdb/",
    api_reference: "https://developer.wordpress.org/reference/classes/wpdb/",
    description: "WordPress Database API ($wpdb) for direct database queries.",
    main_functions: [
      "$wpdb->get_results( $query, $output_type ) - Gets multiple rows",
      "$wpdb->get_row( $query, $output_type, $row_offset ) - Gets a single row",
      "$wpdb->get_var( $query, $column_offset, $row_offset ) - Gets a single value",
      "$wpdb->get_col( $query, $column_offset ) - Gets a column as array",
      "$wpdb->insert( $table, $data, $format ) - Inserts a row",
      "$wpdb->update( $table, $data, $where, $format, $where_format ) - Updates rows",
      "$wpdb->delete( $table, $where, $where_format ) - Deletes rows",
      "$wpdb->replace( $table, $data, $format ) - Replaces a row (insert or update)",
      "$wpdb->query( $query ) - Executes a query",
      "$wpdb->prepare( $query, ...$args ) - Prepares a query (SQL injection protection)",
      "$wpdb->prefix - Table prefix (e.g., 'wp_')",
      "$wpdb->last_error - Last error message",
      "$wpdb->insert_id - Last insert ID",
    ],
    code_examples: [
      `// ALWAYS use $wpdb->prepare() to prevent SQL injection
global $wpdb;
$table = $wpdb->prefix . 'my_table';

// Select with prepare
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d AND status = %s",
        $user_id,
        $status
    )
);

// Insert
$wpdb->insert(
    $table,
    array(
        'user_id' => $user_id,
        'value'   => $value,
        'created' => current_time( 'mysql' ),
    ),
    array( '%d', '%s', '%s' )
);
$new_id = $wpdb->insert_id;

// Update
$wpdb->update(
    $table,
    array( 'value' => $new_value ),  // data
    array( 'id' => $id ),            // where
    array( '%s' ),                   // data format
    array( '%d' )                    // where format
);

// Delete
$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );`,
    ],
    best_practices: [
      "ALWAYS use $wpdb->prepare() for queries with variables - prevents SQL injection",
      "Use %d for integers, %s for strings, %f for floats in prepare()",
      "Use $wpdb->prefix for table names",
      "Use insert(), update(), delete() methods instead of raw queries when possible",
      "Check $wpdb->last_error after queries for debugging",
      "Use current_time('mysql') for datetime values",
    ],
  },

  /**
   * Hooks API
   */
  "wordpress-core/hooks": {
    docs_url: "https://developer.wordpress.org/plugins/hooks/",
    api_reference: "https://developer.wordpress.org/reference/functions/add_action/",
    description: "WordPress Hooks API for actions and filters.",
    main_functions: [
      "add_action( $hook_name, $callback, $priority, $accepted_args ) - Adds an action hook",
      "do_action( $hook_name, ...$args ) - Executes an action hook",
      "remove_action( $hook_name, $callback, $priority ) - Removes an action",
      "has_action( $hook_name, $callback ) - Checks if action exists",
      "add_filter( $hook_name, $callback, $priority, $accepted_args ) - Adds a filter hook",
      "apply_filters( $hook_name, $value, ...$args ) - Applies filters to a value",
      "remove_filter( $hook_name, $callback, $priority ) - Removes a filter",
      "has_filter( $hook_name, $callback ) - Checks if filter exists",
      "current_filter() - Gets the current filter being executed",
      "doing_action( $action ) - Checks if a specific action is being executed",
    ],
    code_examples: [
      `// Add action
add_action( 'init', 'my_init_function' );
add_action( 'wp_enqueue_scripts', 'my_enqueue_scripts', 10, 0 );

// Action with arguments
add_action( 'save_post', 'my_save_post', 10, 3 );
function my_save_post( $post_id, $post, $update ) {
    if ( $update ) {
        // Post was updated
    }
}

// Add filter
add_filter( 'the_content', 'my_content_filter' );
function my_content_filter( $content ) {
    return $content . '<p>Added by filter</p>';
}

// Filter with multiple arguments
add_filter( 'post_thumbnail_html', 'my_thumbnail_filter', 10, 5 );
function my_thumbnail_filter( $html, $post_id, $thumbnail_id, $size, $attr ) {
    return '<div class="thumbnail-wrapper">' . $html . '</div>';
}`,
    ],
    best_practices: [
      "Use priority 10 (default) unless you need to run before/after other hooks",
      "Always specify accepted_args if your callback needs more than 1 argument",
      "Use unique function names or class methods to avoid conflicts",
      "Filters must return a value; actions don't return anything",
      "Use remove_action/remove_filter with exact same priority used in add_",
    ],
  },

  /**
   * REST API
   */
  "wordpress-core/rest-api": {
    docs_url: "https://developer.wordpress.org/rest-api/",
    api_reference: "https://developer.wordpress.org/reference/functions/register_rest_route/",
    description: "WordPress REST API for creating custom endpoints.",
    main_functions: [
      "register_rest_route( $namespace, $route, $args ) - Registers a REST route",
      "rest_url( $path ) - Gets the REST API URL",
      "wp_send_json( $response, $status_code ) - Sends JSON response and exits",
      "wp_send_json_success( $data ) - Sends success JSON response",
      "wp_send_json_error( $data ) - Sends error JSON response",
      "rest_ensure_response( $response ) - Ensures a REST response object",
      "WP_REST_Request - Request class with get_param(), get_params(), etc.",
      "WP_REST_Response - Response class",
      "wp_create_nonce( 'wp_rest' ) - Creates REST API nonce",
      "wp_verify_nonce( $nonce, 'wp_rest' ) - Verifies REST nonce",
    ],
    code_examples: [
      `// Register custom REST endpoint
add_action( 'rest_api_init', function() {
    register_rest_route( 'myplugin/v1', '/items', array(
        'methods'             => 'GET',
        'callback'            => 'get_items_callback',
        'permission_callback' => function() {
            return current_user_can( 'read' );
        },
    ));

    register_rest_route( 'myplugin/v1', '/items/(?P<id>\\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'get_item_callback',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'validate_callback' => function( $param ) {
                    return is_numeric( $param );
                }
            ),
        ),
    ));
});

function get_items_callback( WP_REST_Request $request ) {
    $page = $request->get_param( 'page' ) ?: 1;
    $items = get_my_items( $page );
    return new WP_REST_Response( $items, 200 );
}`,
    ],
    best_practices: [
      "Always provide a permission_callback - use '__return_true' for public endpoints",
      "Use namespacing like 'myplugin/v1' to avoid conflicts",
      "Validate and sanitize all input parameters",
      "Return WP_REST_Response or WP_Error objects",
      "Use register_rest_field() to extend existing endpoints",
    ],
  },

  /**
   * Taxonomies API
   */
  "wordpress-core/taxonomies": {
    docs_url: "https://developer.wordpress.org/plugins/taxonomies/",
    api_reference: "https://developer.wordpress.org/reference/functions/register_taxonomy/",
    description: "WordPress Taxonomies API for categories, tags, and custom taxonomies.",
    main_functions: [
      "register_taxonomy( $taxonomy, $object_type, $args ) - Registers a taxonomy",
      "get_taxonomies( $args, $output, $operator ) - Gets registered taxonomies",
      "get_terms( $args ) - Gets terms from a taxonomy",
      "get_term( $term, $taxonomy, $output, $filter ) - Gets a single term",
      "get_term_by( $field, $value, $taxonomy ) - Gets term by field",
      "wp_insert_term( $term, $taxonomy, $args ) - Inserts a new term",
      "wp_update_term( $term_id, $taxonomy, $args ) - Updates a term",
      "wp_delete_term( $term_id, $taxonomy, $args ) - Deletes a term",
      "wp_set_object_terms( $object_id, $terms, $taxonomy, $append ) - Sets terms for an object",
      "wp_get_object_terms( $object_ids, $taxonomies, $args ) - Gets terms for an object",
      "get_the_terms( $post, $taxonomy ) - Gets terms for a post",
      "has_term( $term, $taxonomy, $post ) - Checks if post has term",
    ],
    code_examples: [
      `// Register custom taxonomy
register_taxonomy( 'genre', 'book', array(
    'labels' => array(
        'name' => 'Genres',
        'singular_name' => 'Genre',
    ),
    'hierarchical' => true,
    'show_in_rest' => true,
    'rewrite' => array( 'slug' => 'genre' ),
));

// Assign terms to a post
wp_set_object_terms( $post_id, array( 'fiction', 'mystery' ), 'genre' );

// Get terms for a post
$terms = get_the_terms( $post_id, 'genre' );
if ( $terms && ! is_wp_error( $terms ) ) {
    foreach ( $terms as $term ) {
        echo $term->name;
    }
}`,
    ],
    best_practices: [
      "Use hierarchical = true for category-like taxonomies",
      "Use hierarchical = false for tag-like taxonomies",
      "Set show_in_rest = true for Gutenberg/REST API support",
      "Use wp_set_object_terms() with $append = true to add without removing existing",
      "Always check is_wp_error() on return values",
    ],
  },

  /**
   * Filesystem API
   */
  "wordpress-core/filesystem": {
    docs_url: "https://developer.wordpress.org/plugins/filesystem/",
    api_reference: "https://developer.wordpress.org/reference/classes/wp_filesystem_base/",
    description: "WordPress Filesystem API for secure file operations.",
    main_functions: [
      "WP_Filesystem() - Initializes the filesystem API",
      "$wp_filesystem->get_contents( $file ) - Reads file contents",
      "$wp_filesystem->put_contents( $file, $contents, $mode ) - Writes file contents",
      "$wp_filesystem->exists( $file ) - Checks if file exists",
      "$wp_filesystem->is_file( $path ) - Checks if path is a file",
      "$wp_filesystem->is_dir( $path ) - Checks if path is a directory",
      "$wp_filesystem->mkdir( $path, $chmod, $chown, $chgrp ) - Creates directory",
      "$wp_filesystem->rmdir( $path, $recursive ) - Removes directory",
      "$wp_filesystem->delete( $file, $recursive, $type ) - Deletes file or directory",
      "$wp_filesystem->copy( $source, $destination, $overwrite, $mode ) - Copies file",
      "$wp_filesystem->move( $source, $destination, $overwrite ) - Moves file",
      "wp_upload_dir() - Gets upload directory paths",
      "wp_mkdir_p( $target ) - Creates directory recursively",
    ],
    code_examples: [
      `// Initialize filesystem
global $wp_filesystem;
if ( ! function_exists( 'WP_Filesystem' ) ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}
WP_Filesystem();

// Write file
$upload_dir = wp_upload_dir();
$file_path = $upload_dir['basedir'] . '/my-plugin/data.json';

// Ensure directory exists
wp_mkdir_p( dirname( $file_path ) );

// Write content
$wp_filesystem->put_contents( $file_path, wp_json_encode( $data ), FS_CHMOD_FILE );

// Read file
if ( $wp_filesystem->exists( $file_path ) ) {
    $contents = $wp_filesystem->get_contents( $file_path );
    $data = json_decode( $contents, true );
}`,
    ],
    best_practices: [
      "Use WP_Filesystem API instead of PHP file functions for compatibility",
      "Always call WP_Filesystem() before using $wp_filesystem",
      "Use wp_upload_dir() to get the correct upload paths",
      "Use wp_mkdir_p() for recursive directory creation",
      "Use FS_CHMOD_FILE and FS_CHMOD_DIR for proper permissions",
    ],
  },

  /**
   * Scripts & Styles
   */
  "wordpress-core/scripts-styles": {
    docs_url: "https://developer.wordpress.org/plugins/javascript/enqueuing/",
    api_reference: "https://developer.wordpress.org/reference/functions/wp_enqueue_script/",
    description: "WordPress Scripts and Styles API for properly loading JS and CSS.",
    main_functions: [
      "wp_enqueue_script( $handle, $src, $deps, $ver, $in_footer ) - Enqueues a script",
      "wp_enqueue_style( $handle, $src, $deps, $ver, $media ) - Enqueues a stylesheet",
      "wp_register_script( $handle, $src, $deps, $ver, $in_footer ) - Registers script without enqueuing",
      "wp_register_style( $handle, $src, $deps, $ver, $media ) - Registers style without enqueuing",
      "wp_dequeue_script( $handle ) - Dequeues a script",
      "wp_dequeue_style( $handle ) - Dequeues a style",
      "wp_localize_script( $handle, $object_name, $data ) - Passes data to JavaScript",
      "wp_add_inline_script( $handle, $data, $position ) - Adds inline JavaScript",
      "wp_add_inline_style( $handle, $data ) - Adds inline CSS",
      "wp_script_is( $handle, $list ) - Checks if script is registered/enqueued",
      "wp_style_is( $handle, $list ) - Checks if style is registered/enqueued",
    ],
    code_examples: [
      `// Enqueue in frontend
add_action( 'wp_enqueue_scripts', 'my_enqueue_scripts' );
function my_enqueue_scripts() {
    wp_enqueue_style(
        'my-plugin-style',
        plugins_url( 'css/style.css', __FILE__ ),
        array(),
        '1.0.0'
    );

    wp_enqueue_script(
        'my-plugin-script',
        plugins_url( 'js/script.js', __FILE__ ),
        array( 'jquery' ),
        '1.0.0',
        true // Load in footer
    );

    // Pass PHP data to JavaScript
    wp_localize_script( 'my-plugin-script', 'myPluginData', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'my_nonce' ),
        'i18n'    => array(
            'loading' => __( 'Loading...', 'my-plugin' ),
        ),
    ));
}

// Enqueue in admin
add_action( 'admin_enqueue_scripts', 'my_admin_scripts' );
function my_admin_scripts( $hook ) {
    // Only load on specific admin pages
    if ( 'toplevel_page_my-plugin' !== $hook ) {
        return;
    }
    wp_enqueue_style( 'my-plugin-admin', ... );
}`,
    ],
    best_practices: [
      "Use wp_enqueue_scripts hook for frontend, admin_enqueue_scripts for admin",
      "Use plugins_url() or get_template_directory_uri() for URLs",
      "Set proper dependencies (e.g., 'jquery') to ensure load order",
      "Use version number for cache busting",
      "Load scripts in footer (true) when possible for performance",
      "Use wp_localize_script() to pass data from PHP to JavaScript",
      "Use $hook parameter in admin_enqueue_scripts to load only on needed pages",
    ],
  },

  /**
   * AJAX API
   */
  "wordpress-core/ajax": {
    docs_url: "https://developer.wordpress.org/plugins/javascript/ajax/",
    api_reference: "https://developer.wordpress.org/reference/hooks/wp_ajax__action/",
    description: "WordPress AJAX API for handling asynchronous requests.",
    main_functions: [
      "wp_ajax_{action} - Hook for logged-in user AJAX requests",
      "wp_ajax_nopriv_{action} - Hook for non-logged-in user AJAX requests",
      "admin_url( 'admin-ajax.php' ) - Gets the AJAX handler URL",
      "wp_send_json( $response, $status_code ) - Sends JSON and exits",
      "wp_send_json_success( $data ) - Sends success JSON response",
      "wp_send_json_error( $data ) - Sends error JSON response",
      "check_ajax_referer( $action, $query_arg, $die ) - Verifies AJAX nonce",
      "wp_create_nonce( $action ) - Creates a nonce for AJAX",
      "wp_verify_nonce( $nonce, $action ) - Verifies a nonce",
    ],
    code_examples: [
      `// PHP: Register AJAX handlers
add_action( 'wp_ajax_my_action', 'my_ajax_handler' );
add_action( 'wp_ajax_nopriv_my_action', 'my_ajax_handler' ); // For non-logged users

function my_ajax_handler() {
    // Verify nonce
    check_ajax_referer( 'my_nonce', 'nonce' );

    // Check permissions
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }

    // Process request
    $data = sanitize_text_field( $_POST['data'] );

    // Send response
    wp_send_json_success( array(
        'message' => 'Success!',
        'data'    => $result,
    ));
}`,

      `// JavaScript: Make AJAX request
jQuery.ajax({
    url: myPluginData.ajaxUrl,
    type: 'POST',
    data: {
        action: 'my_action',
        nonce: myPluginData.nonce,
        data: myData
    },
    success: function( response ) {
        if ( response.success ) {
            console.log( response.data.message );
        } else {
            console.error( response.data.message );
        }
    }
});`,
    ],
    best_practices: [
      "Always verify nonce with check_ajax_referer()",
      "Check user capabilities before processing",
      "Sanitize all input data",
      "Use wp_send_json_success() and wp_send_json_error() for consistent responses",
      "Register both wp_ajax_ and wp_ajax_nopriv_ if public access needed",
      "Use wp_die() at the end of handlers (wp_send_json does this automatically)",
    ],
  },

  /**
   * Shortcodes API
   */
  "wordpress-core/shortcodes": {
    docs_url: "https://developer.wordpress.org/plugins/shortcodes/",
    api_reference: "https://developer.wordpress.org/reference/functions/add_shortcode/",
    description: "WordPress Shortcodes API for creating shortcodes.",
    main_functions: [
      "add_shortcode( $tag, $callback ) - Registers a shortcode",
      "remove_shortcode( $tag ) - Removes a shortcode",
      "shortcode_exists( $tag ) - Checks if shortcode exists",
      "do_shortcode( $content, $ignore_html ) - Processes shortcodes in content",
      "shortcode_atts( $defaults, $atts, $shortcode ) - Combines user attributes with defaults",
      "strip_shortcodes( $content ) - Removes all shortcodes from content",
      "get_shortcode_regex() - Gets regex to match shortcodes",
    ],
    code_examples: [
      `// Simple shortcode
add_shortcode( 'greeting', 'greeting_shortcode' );
function greeting_shortcode( $atts, $content = null ) {
    $atts = shortcode_atts( array(
        'name' => 'World',
        'class' => '',
    ), $atts, 'greeting' );

    return sprintf(
        '<p class="%s">Hello, %s!</p>',
        esc_attr( $atts['class'] ),
        esc_html( $atts['name'] )
    );
}
// Usage: [greeting name="John" class="highlight"]

// Enclosing shortcode (with content)
add_shortcode( 'box', 'box_shortcode' );
function box_shortcode( $atts, $content = null ) {
    $atts = shortcode_atts( array(
        'color' => 'blue',
    ), $atts, 'box' );

    return sprintf(
        '<div class="box box-%s">%s</div>',
        esc_attr( $atts['color'] ),
        do_shortcode( $content ) // Process nested shortcodes
    );
}
// Usage: [box color="red"]Content here[/box]`,
    ],
    best_practices: [
      "Always use shortcode_atts() for consistent attribute handling",
      "Escape output: esc_html(), esc_attr(), esc_url()",
      "Call do_shortcode() on $content for nested shortcodes",
      "Return content, don't echo it",
      "Use the third parameter in shortcode_atts() for filter support",
    ],
  },

  /**
   * Widgets API
   */
  "wordpress-core/widgets": {
    docs_url: "https://developer.wordpress.org/plugins/widgets/",
    api_reference: "https://developer.wordpress.org/reference/classes/wp_widget/",
    description: "WordPress Widgets API for creating sidebar widgets.",
    main_functions: [
      "register_widget( $widget_class ) - Registers a widget class",
      "unregister_widget( $widget_class ) - Unregisters a widget",
      "register_sidebar( $args ) - Registers a widget area/sidebar",
      "dynamic_sidebar( $index ) - Displays a sidebar",
      "is_active_sidebar( $index ) - Checks if sidebar has widgets",
      "WP_Widget class - Base class for widgets",
      "the_widget( $widget, $instance, $args ) - Displays a widget outside sidebar",
    ],
    code_examples: [
      `// Create custom widget
class My_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'my_widget',
            'My Widget',
            array( 'description' => 'A custom widget' )
        );
    }

    // Frontend display
    public function widget( $args, $instance ) {
        echo $args['before_widget'];
        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title'];
        }
        echo '<p>' . esc_html( $instance['text'] ) . '</p>';
        echo $args['after_widget'];
    }

    // Admin form
    public function form( $instance ) {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">Title:</label>
            <input type="text"
                   id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                   value="<?php echo esc_attr( $title ); ?>" />
        </p>
        <?php
    }

    // Save settings
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = sanitize_text_field( $new_instance['title'] );
        return $instance;
    }
}

// Register widget
add_action( 'widgets_init', function() {
    register_widget( 'My_Widget' );
});`,
    ],
    best_practices: [
      "Extend WP_Widget class for custom widgets",
      "Escape all output in widget() method",
      "Sanitize all input in update() method",
      "Use get_field_id() and get_field_name() for form fields",
      "Support Block widgets by adding show_in_rest in constructor args (WP 5.8+)",
    ],
  },
};

/**
 * Get WordPress Core documentation for a specific topic
 *
 * @param topic - Topic slug (e.g., "media", "posts")
 * @returns Documentation entry or null
 */
export function getWordPressCoreDocs(topic: string): WordPressCoreDocs | null {
  const fullSlug = topic.startsWith("wordpress-core/")
    ? topic
    : `wordpress-core/${topic}`;

  return WORDPRESS_CORE_DOCS[fullSlug] || null;
}

/**
 * Get all WordPress Core documentation topics
 *
 * @returns Array of topic slugs
 */
export function getWordPressCoreTopics(): string[] {
  return Object.keys(WORDPRESS_CORE_DOCS);
}

/**
 * Check if a slug is a WordPress Core documentation request
 *
 * @param slug - Plugin/doc slug to check
 * @returns True if this is a WP Core docs request
 */
export function isWordPressCoreDocsRequest(slug: string): boolean {
  return slug.startsWith("wordpress-core/") || slug === "wordpress-core";
}
