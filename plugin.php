<?php
/* Copyright 2011 Rob Holmes ( email: rob@onemanonelaptop.com )

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if (!class_exists('Plugin')) {
    /**
    * The Plugin Class
    */ 
    class Plugin {

        /**
        * @var array version
        */
        protected $version = '0.0.9';

        /**
        * @var string The slug of the plugin
        */
        protected $slug = "";

        /**
        * @var string The name of the file e.g. my-plugin/my-plugin.php
        */
        protected $filename = '';

        /**
        * @var  string    The server path and filename of the child plugin
        */
        protected $plugin_file = '';

        /**
        * @var  string    The server path to the child plugin directory
        */
        protected $plugin_path = '';

        /**
        * @var  string    The url for the child plugin directory
        */
        protected $plugin_url = '';

        /**
        * @var  string    The server path and filename of the diy plugin
        */
        protected $diy_file = '';

        /**
        * @var  string    The server path to the diy plugin directory
        */
        protected $diy_path = '';

        /**
        * @var  string    The url for the diy plugin directory
        */
        protected $diy_url = '';

        /**
        * @var  string    The plugin page
        */
        protected $pages = array();

        var $debug_messages = array();
        
        /**
        * Constructor
        * @since 0.0.1
        * @param string $file The filename of the plugin extending this class
        */
        function __construct($file = __FILE__) {
            // Save the filename of the child plugin
            $this->filename = plugin_basename($file);
        } // function

        /**
        * This starts the process of defining the plugin
        */
        public function start() {

            // If we havent got a user defined slug then exit
            if ($this->slug == '') {
                    return;
            }

            // full file and path to the plugin file
            $this->plugin_file =  WP_PLUGIN_DIR .'/'.$this->filename ;

            // store the path to the child plugin
            $this->plugin_path = WP_PLUGIN_DIR.'/'.str_replace(basename( $this->filename),"",plugin_basename($this->filename));

            // store the url to the child plugin
            $this->plugin_url = plugin_dir_url( $this->plugin_file );

            // paths to the diy plugin
            $this->diy_file = __FILE__;
            $this->diy_path = str_replace(basename( $this->diy_file),"",$this->diy_file);
            $this->diy_url = str_replace(ABSPATH,trailingslashit(get_option( 'siteurl' )),$this->diy_path);


            // Save some effort if its an ajax request
            if (!defined('DOING_AJAX') || !DOING_AJAX) {

                // Add the plugins options page
                // @todo skip the need for the intermediary function
                add_action( 'admin_menu', array($this,'diy_register_pages') );

                // Register the child plugins metaboxes
                add_action('admin_init', array($this,'diy_metaboxes'));

                // Save the custom post fields with the post data
                add_action('save_post', array(&$this,'diy_save_post')); 

                // Register the scripts and styles needed for metaboxes and fields
                add_action('admin_init', array(&$this,'diy_scripts_and_styles') );

                // Force the plugin options page to have two columns
                add_filter('screen_layout_columns', array(&$this, 'diy_settings_page_columns'), 10, 2);

            } // end if

            // Setup the ajax callback for autocomplete widget
            add_action('wp_ajax_suggest_action', array(&$this,'diy_suggest_posts_callback'));	
            add_action('wp_ajax_attachments_action', array(&$this,'diy_suggest_attachments'));	
            add_action('wp_ajax_save_attachment_action', array(&$this,'diy_save_attachment'));	

            // add_action('wp_ajax_suggest_action', array(&$this,'diy_suggest_users_callback'));
            add_filter( 'posts_where', array(&$this,'diy_modify_posts_where'), 10, 2 );
            add_filter( 'posts_where', array(&$this,'diy_modify_posts_where_url'), 10, 2 );

        } // end function

        
        /**
        * Validate callback when saving a plugins options
        * 
        * @param    array   $data   The form post data
        * @return   array   The validated data
        */
        function diy_validate_settings($data) {

            return $data;
        } // function

        /*
        * Null Section Callback - Never actually gets called
        */
        function diy_section_callback() { }

        /**
        * Loop throught the defined metaboxes and create them as necessary
        *
        */
        function diy_metaboxes() {
            // Go through the first level of the form array
            foreach ($this->forms as $key => $metabox) {

                // if the type is set to metabox
                if ($metabox['#type'] == 'metabox' ) {

                    // If its has been defined for post types
                    if (isset($metabox['#post_types']) && is_array($metabox['#post_types'])) {
                        foreach ($metabox['#post_types'] as $post_type) {
                            // Add the metabox    
                            add_meta_box( 
                                $key, 
                                $metabox['#title'],
                                array(&$this,'render_metabox_fields'),
                                $post_type, 
                                $metabox['#context'],
                                'core', 
                                $key
                            );
                        }  
                    } // end if

                    // otherwise add this metabox to an options page
                    if (isset($metabox['#pages']) && is_array($metabox['#pages'])) {
                        foreach ($metabox['#pages'] as $page) {

                            add_settings_section(
                                $key,
                                'asc',
                                array(&$this, 'diy_section_callback'),
                                $page
                            );

                                add_meta_box(
                                $key,
                                $metabox['#title'],
                                array(&$this, 'render_metabox_fields'),
                                $page,
                                $metabox['#context'],
                                'core',
                                $key
                            );

                            // if there is a parent then
                            add_settings_field(
                                $key,  // option identifier
                                $metabox['#title'], // field title
                                array(&$this, 'settings_field_callback'), // field widget callback
                                $page , // page hook
                                $key, // assigned metabox
                                $metabox // arguments to pass in
                            );

                            register_setting( $page , $key , array(&$this,'diy_validate_settings'));

                        } // if
                    } // foreach
                } // if
            } // foreach
        } // function

        /**
         *  Render a group of metabox fields
         */
        function render_metabox_fields($post,$args) {
            
            // sotre the metabox id (array key)
            $key = $args['args'];
            // get the saved values depeding on the context
            if (empty($post)) {
                $values = get_option($key,array());
            } else {
                $values = get_post_meta($post->ID,$args['args'],true);
            }
            // if the array is empty load the defaults and save them
            if (empty($values)) {
                $values = $this->forms[$key];
                $this->apply_defaults($values);
            }
           
            // print any visibility options form the metabox definition
            print $this->metabox_visibility($key);
            // print any positioning options form the metabox definition
            print $this->metabox_positioning($key);
 
            // retrieve the form
            $newform = new Form($this->forms[$key]);
            // print the rendered form
            print $newform->values($values)->render();
        } // function

        /**
         * Called when a settings filed does not yet exist for a page's metabox
         * Use the form definition to biuld an array of values and save the
         * setting 
         */
        function apply_defaults(&$values) {
            // go through each element recurisvely    
            foreach($values as $key => $value) {
              
                // special treatement for multi groups
                if ($key == '#type') {
                    if ($value == 'multigroup') {
                        // save the children
                        $save = $values;
                        // wipe the entry
                        $values = array();
                        // add the children back in against index 0
                        foreach ($save as $k => $v) {
                            if ($k === '' || $k[0] !== '#') {
                                $values[0][$k] = $v;
                            } // if
                        } // foreach
                     
                    } // if
                } // if
                //
                // recursively register any nested pages
                if ($key === '' || $key[0] !== '#') {

                    // recursive call with the child page
                     self::apply_defaults($values[$key]);
                } else if ($key == '#default_value' ) {
                    $save = $values['#default_value'];
                    unset($values['#default_value']);
                    $values = $save;
                    
                  
                } else {
                   
                    unset($values[$key]);
                }
                
            }
            return $values;
        } // function
        
        /**
         * Allow metaboxes to be fixed in certain positions
         * @param string $id Array key of the metabox
         * @return string
         */
        function metabox_positioning($id) {
            if (isset($this->forms[$id]['#settings']['lock_top'])) {
                return '<div class="lock-top"></div>';
            } else if (isset($this->forms[$id]['#settings']['lock_bottom'])) {
                return '<div class="lock-bottom"></div>';
            } else if (isset($this->forms[$id]['#settings']['lock_before_post_title'])) {
                return '<div class="lock-before-post-title"></div>';
            } else if (isset($this->forms[$id]['#settings']['lock_after_post_title'])) {
                return '<div class="lock-after-post-title"></div>';
            } else {
                return '';
            }
        } // function

        /**
         * Insert a div into a metabox to indiate its visibility settings 
         * Used by jquery to disable/enable visiblity settings
         * @param string $id Array key of the metabox
         * @return string 
         */
        function metabox_visibility($id) {
            if (isset($this->forms[$id]['#settings']['always_open'])) {
                return '<div class="always-open"></div>';
            } else if (isset($this->forms[$id]['#settings']['start_closed'])) {
                return '<div class="start-closed"></div>';
            } else if (isset($this->forms[$id]['#settings']['start_open'])) {
                return '<div class="start-open"></div>';
            } else {
                return '';
            }
        } // function

        /**
         * Recursively create options pages from the pages array
         *
         * @since	0.0.9
         * @access	public
         */
        public function diy_register_pages() {
            // Start off the recursion
            $this->diy_register_page($this->pages);
        }


        /**
         * Register a page with wordpress
         */
        public function diy_register_page(&$elements) {
            global $admin_page_hooks;
            foreach ($elements as $key => $page) {
                if ($page['#type'] == 'menu') {
                    $elements[$key]['#hook'] =  add_menu_page(  __($page['#title']), __($page['#link_text']), 'manage_options', $key, array(&$this,'diy_render_options_page' ));
               
                    
                    
                    }
                
                // Add theme pages
                if ($page['#type'] == 'theme') {
                    // Register the page and its callback and save the hook for later
                    $elements[$key]['#hook'] =  add_theme_page( __($page['#title']), __($page['#link_text']), 'edit_theme_options', $key, array(&$this,'diy_render_options_page' ));
                    // add a callback to load the required css and javascript on each of the pages
                    add_action('load-'.$elements[$key]['#hook'],  array(&$this, 'diy_enqueue_settings_page_scripts'));
                    add_action('admin_print_scripts-' . $elements[$key]['#hook'], array(&$this, 'diy_admin_scripts'));
                    add_action('admin_print_styles-' . $elements[$key]['#hook'], array(&$this,  'diy_admin_styles'));
                }

                // Add options pages
                if ($page['#type'] == 'options') {
                    // Set the callback
                    if (empty($elements[$key]['#callback'])) { 
                        $elements[$key]['#callback'] =  'diy_render_options_page';
                    }
                    if ($elements['#parent']) { 
                        // allow a link destination, custom callback
                        if (isset($elements[$key]['#destination'])) { 

                            $elements[$key]['#hook'] = add_submenu_page( $elements['#parent'], __($page['#title']), __($page['#link_text']), 'manage_options',    $elements[$key]['#destination'] ,'' );
                        } else {
                            $elements[$key]['#hook'] = add_submenu_page( $elements['#parent'], __($page['#title']), __($page['#link_text']), 'manage_options',  $key,  array($this, 'diy_render_options_page')  );
                    
                            
                            }
                    } else {
                        $elements[$key]['#hook'] = add_options_page(__($page['#title']), __($page['#link_text']), 'manage_options', $key, array($this, 'diy_render_options_page'));
                    }
                    // add a callback to load the required css and javascript on each of the pages
                    add_action('load-'.$elements[$key]['#hook'],  array(&$this, 'diy_enqueue_settings_page_scripts'));

                    // Add custom scripts and styles to the plugin/theme page only
                    add_action('admin_print_scripts-' . $elements[$key]['#hook'], array(&$this, 'diy_admin_scripts'));
                    add_action('admin_print_styles-' . $elements[$key]['#hook'], array(&$this,  'diy_admin_styles'));

                }

                // recursively register any nested pages
                if ($key === '' || $key[0] !== '#') {
                    // Save the parent
                    $elements[$key]['#parent'] = $key;
                    // recursive call with the child page
                    $this->diy_register_page($elements[$key]);
                }

            } // end foreach
        } // function


        /**
         * Runs only on an options page load hook and enables the scripts needed for metaboxes
         *
         * @since	0.0.1
         * @access	public
         */
        function diy_enqueue_settings_page_scripts() {
            wp_enqueue_script('common');
            wp_enqueue_script('wp-lists');
            wp_enqueue_script('postbox');
        } // function

        /**
         * Add a settings link to the plugin list page
         *
         * @since	0.0.1
         * @param	string  $file       the filename of the plugin currently being rendered on the installed plugins page
         * @param	array   $links      an array of the current registered links in html format
         * @return	array
         * @access	public
         */
        function diy_add_settings_link($links, $file) {
            // if the current row being rendered matches our plugin then add a settings link
            if ( $file == $this->filename  ){
                // Build the html for the link
                $settings_link = '<a href="options-general.php?page=' .$this->slug . '">' . __('Settings', $this->slug) . '</a>';
                // Prepend our link to the beginning of the links array
                array_unshift( $links, $settings_link );
            }
            return $links;
        } // function

        /**
         * On the plugin page make sure there are two columns
         *
         * @since	0.0.1
         * @access	public
         * @param   int $columns
         * @param   string  $screen
         * @return  int number of columns
         */
        function diy_settings_page_columns($columns, $screen) {
            global $current_screen;
        
            if ($screen == $current_screen->id) {
                $columns[$screen] = 2;
                 update_user_option(true, "screen_layout_" .$screen, "2" );
            }
            return $columns;
        } // function

        /**
        * Create the options page form
        *
        * @since	0.0.1
        * @access	public
        */
        public function diy_render_options_page() {
            global $current_screen;
            // retrieve the current id from the screen object
            $current_page = $_GET['page'];
            if (isset($this->pages[$current_screen->parent_file]) && is_array($this->pages[$current_screen->parent_file][$current_page])) {
                $page = $this->pages[$current_screen->parent_file][$current_page];
              
            } else {
                $page = $this->pages[$current_page];
             
            }
           
            
            // @todo escape

            global $screen_layout_columns;

            ?>
            <div class="wrap">
                <?php 
                // Output a custom settings page icon
                if (!empty($page['#icon'])) {
                    print '<div class="icon32" style="background:url(' . $this->plugin_url . $page['#icon'] . ');"></div>';
                } else {
                    screen_icon('options-general');
                }
                ?>
                <h2><?php print  $page['#title']; ?></h2>
                <?php do_action($current_page . '_settings_page_top'); ?>
                <form id="settings" data-hook="<?php print $page['#hook']; ?>" action="options.php" method="post" enctype="multipart/form-data">

                    <?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
                    <?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>
                    <?php settings_fields($current_screen->id); ?>
                    <div id="poststuff" class="metabox-holder<?php echo 2 == $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">
                        <div id="side-info-column" class="inner-sidebar">
                                <?php    
                              
                                do_meta_boxes( $page['#hook'], 'side', $data = array());  ?>
                        </div>
                        <div id="post-body" class="has-sidebar">
                            <div id="post-body-content" class="has-sidebar-content">
                                <?php 
                                do_meta_boxes( $page['#hook'], 'normal', $data = array()); ?>
                                <br/>
                                <p>
                                    <input type="submit" value="Save Changes" class="button-primary" name="Submit"/>	
                                </p>
                            </div>
                        </div>
                        <br class="clear"/>				
                    </div>	
                </form>
            </div>

            <?php
        } // function


        /**
        * Register the admin scripts
        *
        * @since	0.0.1
        * @access	public
        */
        function diy_scripts_and_styles() {
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'jquery-ui-core' );
            wp_enqueue_script( 'jquery-ui-datepicker' );

            wp_enqueue_script('jquery-ui-sortable');

            // if admin.js exists in the child plugin include it
            if (file_exists($this->diy_path . 'admin.js')) {
           
                wp_register_script('diy' ,$this->diy_url . 'admin.js',  array('jquery','jquery-ui-sortable','jquery-ui-sortable','media-upload','thickbox','editor'));
            }

            // if admin.css exists in the child plugin include it
            if (file_exists($this->diy_path . 'admin.css')) {
                 
                wp_register_style('diy' ,$this->diy_url . 'admin.css');
            }

            // If wea re using a child plugin
            if ($this->plugin_path != $this->diy_path) {
                // if admin.js exists in the child plugin include it
                if (file_exists($this->plugin_path . 'admin.js')) {
                    wp_register_script($this->slug . '-admin' ,$this->plugin_url . 'js/admin.js');
                }

                // if admin.css exists in the child plugin include it
                if (file_exists($this->plugin_path . 'admin.css')) {
                    wp_register_style($this->slug . '-admin' ,$this->plugin_url . 'css/admin.css');
                }
            }

            // only load the google map if we have used one
            wp_register_script('gmap','http://maps.google.com/maps/api/js?sensor=false');
            
            
            // Add custom scripts and styles to the plugin/theme page only
            add_action('admin_print_scripts-widgets.php', array(&$this, 'diy_admin_scripts'));
            add_action('admin_print_styles-widgets.php', array(&$this,  'diy_admin_styles'));

        
            // Add custom scripts and styles to the post editor pages
            add_action('admin_print_scripts-post.php', array(&$this, 'diy_admin_scripts'));
            add_action('admin_print_scripts-post-new.php',array(&$this,  'diy_admin_scripts'));
            add_action('admin_print_styles-post.php', array(&$this, 'diy_admin_styles'));
            add_action('admin_print_styles-post-new.php',array(&$this,  'diy_admin_styles'));	

        } // function

        /**
        * Add custom styles to this plugins options page only
        *
        * @since	0.0.1
        * @access	public
        */
        function diy_admin_styles() {

            // used by media upload
            wp_enqueue_style('thickbox');
            // Enqueue our diy specific css
            wp_enqueue_style('diy');
            // color picker
            wp_enqueue_style( 'farbtastic' );
            // Allow usage of the google map api
            wp_enqueue_script('gmap');
            // Allow cropping
            wp_enqueue_script('jcrop');
                    
        } // function

        /**
        * Add scripts globally to all post.php and post-new.php admin screens
        *
        * @since	0.0.1
        * @access	public
        */
        function diy_admin_scripts() {
            // Enqueue our diy specific javascript
            wp_enqueue_script('diy');
            // Color picker
            wp_enqueue_script('farbtastic');  
            // Allow Jquery Chosen
            wp_enqueue_script('suggest');
            // Allow usage of the google map api
            wp_enqueue_script('gmap');
            // Allow cropping
            wp_enqueue_script('jcrop');
        } // function

     
        /**
        * Ajax callback function to return list of attachments
        *
        * @since    0.0.8
        * @access   public
        */ 
        function diy_save_attachment() {
            // If the upload field has a file in it
            if(isset($_FILES['upload']) && ($_FILES['upload']['size'] > 0)) {

                // Get the type of the uploaded file. This is returned as "type/extension"
                $arr_file_type = wp_check_filetype(basename($_FILES['upload']['name']));
                $uploaded_file_type = $arr_file_type['type'];

                // Set an array containing a list of acceptable formats
                $allowed_file_types = array('image/jpg','image/jpeg','image/gif','image/png','application/pdf');

                // If the uploaded file is the right format
                if(in_array($uploaded_file_type, $allowed_file_types)) {

                    // Options array for the wp_handle_upload function. 'test_upload' => false
                    $upload_overrides = array( 'test_form' => false ); 

                    // Handle the upload using WP's wp_handle_upload function. Takes the posted file and an options array
                    $uploaded_file = wp_handle_upload($_FILES['upload'], $upload_overrides);

                    // If the wp_handle_upload call returned a local path for the image
                    if(isset($uploaded_file['file'])) {

                        // The wp_insert_attachment function needs the literal system path, which was passed back from wp_handle_upload
                        $file_name_and_location = $uploaded_file['file'];

                        // Generate a title for the image that'll be used in the media library
                        $file_title_for_media_library = 'your title here';
                        $wp_upload_dir = wp_upload_dir();
                        // Set up options array to add this file as an attachment
                        $attachment = array(
                            'post_mime_type' => $uploaded_file_type,
                            'post_title' => 'Uploaded image ' . addslashes($file_title_for_media_library),
                            'post_content' => '',
                            'post_status' => 'inherit',
                            'guid' => trailingslashit($wp_upload_dir['baseurl']) . _wp_relative_upload_path( $file_name_and_location )

                        );

                        // Run the wp_insert_attachment function. This adds the file to the media library and generates the thumbnails. If you wanted to attch this image to a post, you could pass the post id as a third param and it'd magically happen.
                        $attach_id = wp_insert_attachment( $attachment, $file_name_and_location );
                        require_once(ABSPATH . "wp-admin" . '/includes/image.php');
                        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_name_and_location );

                        wp_update_attachment_metadata($attach_id,  $attach_data);



                        // Set the feedback flag to false, since the upload was successful
                        $upload_feedback = false;


                    } else { // wp_handle_upload returned some kind of error. the return does contain error details, so you can use it here if you want.

                        $upload_feedback = 'There was a problem with your upload.';

                    }

                } else { // wrong file type

                    $upload_feedback = 'Please upload only image files (jpg, gif or png).';

                }

            } else { // No file was passed

                $upload_feedback = false;

            }

            // Update the post meta with any feedback
            //update_post_meta($post_id,'_xxxx_attached_image_upload_feedback',$upload_feedback);

            print wp_get_attachment_url($attach_id );

        die();
        } // function


        /**
        * Ajax callback function to return list of post types
        *
        * @since    0.0.1
        * @access   public
        * @todo    split this out into many functions for each type of suggestion i.e. users by role, attachments by extension
        */ 
        function diy_suggest_posts_callback() {
            global $wpdb;

            $group =  $wpdb->escape($_GET['group']);
            $field =  $wpdb->escape($_GET['field']);    
            $in =  $wpdb->escape($_GET['q']);


            $explode = explode('[',str_replace(']','',$group));
            // die(var_export($explode,true));


            $parts = preg_split('/(\[|\])/', $group, null, PREG_SPLIT_NO_EMPTY);

            // lookup any custom arguments in the form definition
            // @todo for this to work in multigroups the index will have to be
            // squashed as the form wont be expanded at this point

            $custom_args = $this->forms;
            foreach ($parts as $part) {
                $custom_args = $custom_args[$part];
            }
            $custom_args = $custom_args['#wp_query'];


            // if we are searching for posts
            if (isset($custom_args['post_type'])) {
                $defaults = array(
                    'post_title_like' => $in,
                    'post_type' => 'post',
                );

                $args = wp_parse_args($custom_args, $defaults);

                $the_query = new WP_Query($args);
                // The Loop
                while ( $the_query->have_posts() ) : $the_query->the_post();
                        echo  get_the_title(). " [#" . get_the_ID() . "]" . "\n";

                endwhile;
            } else {
                $defaults = array(
                    'search'=>'*' . $in . '*',
                );

                $args = wp_parse_args($custom_args, $defaults);

                // we are searching for users
                $wp_user_search = new WP_User_Query(  $args );
                $users = $wp_user_search->get_results();

                foreach ($users as $user) {
                print  $user->user_nicename . " [*" .$user->ID . "]" .  "\n";
                }
            }

            die(); // this is required to return a proper result
        } // function

        /**
        * Modify the query WHERE clause when performing a suggest ajax request
        *
        * @since	0.0.2
        * @access	public
        */ 
        function diy_modify_posts_where( $where, &$wp_query ) {
            global $wpdb;
            // only modify the query when  post_title_like has been passed in
            if ( $post_title_like = $wp_query->get( 'post_title_like' ) ) {
                $where .= ' AND ' . $wpdb->posts . '.post_title LIKE \'%' . esc_sql( like_escape( $post_title_like ) ) . '%\'';
            }
            return $where;
        } // function

        /**
         * Modify the query WHERE clause when performing a suggest ajax request for attachments
         *
         * @since    0.0.8
         * @access   public
         */ 
        function diy_modify_posts_where_url( $where, &$wp_query ) {
            global $wpdb;
            // only modify the query when  post_title_like has been passed in
            if ( $post_url_like = $wp_query->get( 'post_url_like' ) ) {
                $where .= ' AND ' . $wpdb->posts . '.guid LIKE \'%' . esc_sql( like_escape( $post_url_like ) ) . '%\'';
            }
            return $where;
        } // function


    

        /**
         *  Save the post meta box field data
         *
         * @since	0.0.1
         * @access	public
         * @param    string  $post_id    The post id we are saving
         */ 
        function diy_save_post( $post_id ) {
            global $post, $new_meta_boxes;

            // Stop WP from clearing custom fields on autosave
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
                return;

            // Prevent quick edit from clearing custom fields
            if (defined('DOING_AJAX') && DOING_AJAX)
                return;

            // Check some permissions
            if ( isset($_POST['post_type']) && 'page' == $_POST['post_type'] ) {
                if ( !current_user_can( 'edit_page', $post_id ))
                return $post_id;
            } else {
                if ( !current_user_can( 'edit_post', $post_id ))
                return $post_id;
            }


            // print var_export($_POST,true);

            // go through each metabox definition to see if one of them applies to this page
            $meta_to_save = array();
            foreach ($this->forms as $key => $form) {
                if ($form['#type'] == 'metabox' && isset($form['#post_types']) && is_array($form['#post_types'])) {
                    foreach ($form['#post_types'] as $post_type) {
                        if (is_object($post) && $post_type == $post->post_type) {
                            $meta_to_save[] = $key;
                        }
                    }
                }
            }
            // print var_export( $meta_to_save,true);


            // Go through each group in the metabox
            foreach( $meta_to_save as $meta) {

                // Get the post data for this field group
                if (isset($_POST[$meta])) {
                    $data = $_POST[$meta];
                } else {
                    $data = "";
                }



                self::suggest_fix($data);
                if(get_post_meta($post_id, $meta) == "") {
                    add_post_meta($post_id, $meta, $data, true);
                } elseif ($data != get_post_meta($post_id, $meta, true)) {
                    update_post_meta($post_id, $meta, $data);
                } elseif($data == "") {
                    delete_post_meta($post_id, $meta, get_post_meta($post_id, $meta, true));
                }

                self::save_expanded($meta, $data,$this->forms,$post_id);

            } // end foreach

        } // end function


        function save_expanded($k,$data,&$elements,$post_id) {
            // for each element
            foreach ($elements as $key => $form) {
                // if the expanded property is set
                if (isset($elements[$key]['#expanded']) && $elements[$key]['#expanded'] === true) {

                    $meta_field_name = $k . '_' . $key;
                    if(get_post_meta($post_id,  $meta_field_name) == "") {
                        add_post_meta($post_id,  $meta_field_name,  $data[$key], true);
                    } elseif ($data[$key][$field_name] != get_post_meta($post_id, $meta_field_name, true)) {
                        update_post_meta($post_id,  $meta_field_name,  $data[$key]);
                    } elseif($data[$key][$field_name] == "") {
                        delete_post_meta($post_id,  $meta_field_name, get_post_meta($post_id,  $meta_field_name, true));
                    }

                } // endif
                // recursively register any nested pages
                if ($key === '' || $key[0] !== '#') {
                    // recursive call with the child page
                    self::save_expanded($k,$data,$elements[$key],$post_id);
                } // if
            } // foreach
        } // function

        /**
        * Never actually gets called as render_metabox_fields handles it
        */ 
        function settings_field_callback($args) { } // end function



        function suggest_fix(&$values) {
            global $wpdb;
            // go through each element recurisvely    
            foreach($values as $key => $value) {
                // if it is an array then carry on recursivley
                if (is_array($values[$key])) { 
                    self::suggest_fix($values[$key]); 
                } else {  
                    // if the [# string is found in the data
                    if (strlen(strstr($values[$key],'[#'))>0) {
                        // extract it [# ] 
                        preg_match('/.*\[#(.*)\]/', $values[$key], $matches);
                        $values[$key] =  $matches[1];
                        // Retrieve matching data from the posts table
                        $result = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts AS wposts  WHERE wposts.ID = '" . $values[$key] . "'");
                        if ($result == 0) {
                                $values[$key]='';
                        }
                    } // if

                    // if the [* string is found in the data
                    if (strlen(strstr($values[$key],'[*'))>0) {
                        // extract it [* ] 
                        preg_match('/.*\[\*(.*)\]/', $values[$key], $matches);
                        $values[$key] =  $matches[1];
                        // Retrieve matching data from the posts table
                        $result = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users AS wpusers  WHERE wpusers.ID = '" . $values[$key] . "'");
                        if ($result == 0) {
                            $values[$key]='';
                        }
                    } // if
                } // if
            } // foreach
        } // function

        /**
        * Save the forms array
        * @param array $forms 
        */
        function forms($forms) {
            $this->forms = Form::process($forms);
            return $this;
        } // function

        /**
        * Save the pages array
        * @param array $pages 
        */
        function pages($pages) {
            $this->pages = $pages;
            return $this;
        } // function

        /**
         * Save the slug
         * @param boolean $slug
         */
        function slug($slug) {
            $this->slug = $slug;
            return $this;
        } // function

    } // end class definition

} // end if class exists
?>