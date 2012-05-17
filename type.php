<?php
/** 
   Copyright 2011 Rob Holmes ( email: rob@onemanonelaptop.com )

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

/**
 * Wrapper class for a custom post type 
 */
if (!class_exists('Type')) {
    class Type {
        
        /**
         * @var string The slug of the new post type
         */
        var $post_type = 'example';
        
        
        var $post_type_archive_title = '';
        /**
         * @var string The label to use
         */
        var $label = '';
        
        /**
         * @var boolean Is the post type public
         */
        var $public = true;
        
        /**
         * @var boolean Is the post type public queryable
         */
        var $publicly_queryable = true;
        
        /**
         * @var string define some placeholder text for the title field
         */
        var $placeholder = '';
        
        /**
         * @var string The template to use for post titles
         */
        var $post_title_template = '';
        
        /**
         * @var string The slug of the new post type
         */
        var $data = array();
        
        /**
         * @var string The slug of the new post type
         */
        var $labels = array();
        
        /**
         * @var string The slug of the new post type
         */
        var $show_in_menu = true;
        
        /**
         * @var string The slug of the new post type
         */
        var $disable_revisions = false;
        
        /**
         * @var string The slug of the new post type
         */
        var $disable_autosave = false;
        
        /**
         * @var string The slug of the new post type
         */
        var $rewrite;
        
        var $exclude_from_search = false;
        var $supports = array();
        var $disable_features = false;
        var $simple_submit = false;
        
        var $remove_columns = array();
        var $featured_image_title = 'Featured Image';
        var $move_featured_image_metabox = false;
        
        var $restrict_manage_posts = array();
        
        /**
         * Constructor
         * @param string $type  The slug of the post type
         */
        function __construct($type) {
            // save the slug
            $this->post_type = $type;
            
            // Regsitre the post type
            add_action( 'init', array($this,'register_post_type') );

            // Add a custom placeholder
            add_filter( 'enter_title_here', array($this,'enter_title_here'), 10, 2 );  

            // add some custom rows to the downloads post type
            add_filter( 'manage_edit-' . $this->post_type . '_columns', array(&$this, 'type_column_heading' ));
            add_filter( 'manage_' . $this->post_type . '_posts_custom_column', array(&$this,  'type_column_callback'), 10, 2 );
            
            // Change the length of the exceprt
           // add_filter('excerpt_length', array($this,'type_excerpt_length'));
            
            // remove some columns for this post type
            add_filter('manage_' . $this->post_type . '_columns', array($this,'type_remove_column'));
            
            
            // auto generate post titles
            add_filter('wp_insert_post_data', array(&$this, 'auto_generate_title'), 99, 2);
            
            add_action("admin_menu",  array(&$this,'disable_features'));
            
            
            add_action( 'admin_menu', array(&$this,'replace_submit_meta_box') );
            
            add_action('wp_print_scripts',  array(&$this,'type_disable_autosave'));
            
            // add_filter( 'edit_posts_per_page', array(&$this,'type_edit_post_per_page' ));
            
            // Add the theme options link to the black menu bar
            add_action( 'wp_before_admin_bar_render', array(&$this,'admin_bar_edit_link' ));
            
//            add_filter( 'posts_where' , array(&$this,'posts_where' ));
//            
//            add_filter('posts_orderby', 'posts_orderby' );
//            
//             add_filter('query_vars', 'query_vars' );
            
             add_filter('post_type_archive_title',array(&$this,'type_archive_title') );
                     
             // Move the featured image upload box to the LHS
            add_action( 'do_meta_boxes', array(&$this,'move_featured_image_metabox') );
            
            // allow filtering of the manage posts page by any registered taxonomies
            add_action( 'restrict_manage_posts', array(&$this,'type_restrict_manage_posts' ) );
            
           add_filter( 'post_updated_messages',  array(&$this,'type_post_updated_messages' ) );
                     
        } // function
        
        
          
            function type_restrict_manage_posts() {
                global $typenow;
                if( $typenow == $this->post_type ){
                    foreach (get_object_taxonomies( $this->post_type ) as $tax_slug) {
                            $tax_obj = get_taxonomy($tax_slug);
                            $tax_name = $tax_obj->labels->name;
                            $terms = get_terms($tax_slug);
                            echo "<select name='$tax_slug' id='$tax_slug' class='postform'>";
                            echo "<option value=''>All $tax_name</option>";
                            foreach ($terms as $term) { echo '<option value='. $term->slug, (isset($_GET[$tax_slug]) && $_GET[$tax_slug] == $term->slug )? ' selected="selected"' : '','>' . $term->name .' (' . $term->count .')</option>'; }
                            echo "</select>";
                    }

                }   
            } // end function
       
        
            
            
            
            
        function move_featured_image_metabox() {
            if ($this->move_featured_image_metabox) {
                $title = __(  $this->featured_image_title, $this->post_type );
                remove_meta_box( 'postimagediv', $this->post_type, 'side' );
                add_meta_box( 'postimagediv',  $title, 'post_thumbnail_meta_box', $this->post_type, 'normal', 'high' );
            }
        }
        
        function type_archive_title($name) {
            if ($this->archive_title != '') {
                return $this->archive_title;
            }
            return $name;
        }
        
         function archive_title($title) {
           $this->archive_title = $title ;
           return $this;
        }
        
        
	/**
	* 
	*
	* @since	0.1
	* @access	public
	*/ 
	function manage_posts_by_taxonomy() {
            global $typenow;
            // if we are on the slides post type list page
            if ( $typenow == $this->post_type ) {
            $filters = get_object_taxonomies($typenow);
                foreach ($filters as $tax_slug) {
                    $tax_obj = get_taxonomy($tax_slug);
                    wp_dropdown_categories(array(
                        'show_option_all' => __('Show All '.$tax_obj->label ),
                        'taxonomy' => $tax_slug,
                        'name' => $tax_obj->name,
                        'orderby' => '',
                        'selected' => $_GET[$tax_obj->query_var],
                        'hierarchical' => $tax_obj->hierarchical,
                        'show_count' => false,
                        'hide_empty' => true
                    ));
                }
            }
	} // function 
        
        
         /**
         * Can the post type auto set its featured image
         * @param type $autoset
         * @return \Type 
         */
        function autoset($autoset = true) {
            $this->autoset = $autoset;
            if ($this->autoset) {
                add_action('the_post', array($this,'autoset_featured'));
                add_action('save_post', array($this,'autoset_featured'));
                add_action('draft_to_publish', array($this,'autoset_featured'));
                add_action('new_to_publish', array($this,'autoset_featured'));
                add_action('pending_to_publish', array($this,'autoset_featured'));
                add_action('future_to_publish', array($this,'autoset_featured'));
            }
            return $this;
        } // function
        
        /**
         * Auto set the featured image from the first attachment 
         */
        function autoset_featured() {
            global $post;
            $already_has_thumb = has_post_thumbnail($post->ID);
            if (!$already_has_thumb)  {
            $attached_image = get_children( "post_parent=$post->ID&post_type=attachment&post_mime_type=image&numberposts=1" );
                if ($attached_image) {
                    foreach ($attached_image as $attachment_id => $attachment) {
                        set_post_thumbnail($post->ID, $attachment_id);
                    }
                }
            }
        } // function
        

//        function query_vars( $qvars )
//        {
//        $qvars[] = 'geostate';
//        return $qvars;
//        }
//
//        function posts_orderby( $orderby ) {
//            global $gloss_category;
//            if( is_post_type_archive($this->post_type) ) {
//                if( $_GET['orderby'] == 'ASC' ) {
//                    // alphabetical order by post title
//                    return "post_title ASC";
//                }
//            }
//            // not in glossary category, return default order by
//            return $orderby;
//        }
//
//        function posts_where( $where ) {
//            $letters = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','X','Y','Z');
//            if( is_post_type_archive($this->post_type) ) {
//                    if (in_array($_GET['letter'],$letters)) {
//
//                            $where .= 'AND wp_posts.post_title LIKE "' .$_GET['letter'] . '%" ';
//                    }
//                    }
//
//            return $where;
//        }
//        
        
        function disable_features() {
            if ($this->disable_features) { 
                remove_action('pre_post_update', 'wp_save_post_revision');
            }
        } // function
        
        /**
         * Disable autosave for this post type if the flag has been set 
         */
        function type_disable_autosave() {
            if ($this->disable_autosave) { 
                wp_deregister_script('autosave'); 
            }
        } // function
        
        /**
         * Replace the submit box with a custom version 
         */
        function replace_submit_meta_box() {
            if ($this->simple_submit) { 
                remove_meta_box('submitdiv',  $this->post_type, 'core');
                add_meta_box('submitdiv', __('Publish'),  array(&$this,'custom_post_submit_meta_box'), $this->post_type, 'side', 'core');
            }
        } // function
        
        function custom_post_submit_meta_box() { // a modified version of post_submit_meta_box() (wp-admin/includes/meta-boxes.php, line 12)
          
        } // function
        
        /**
         * Add a new column to the manage page
         * @param array $columns the existing columns
         * @return array 
         */
        function type_column_heading($columns) {
            if ($this->columns) {
            foreach ($this->columns as $key => $column) {
                $columns[$key] = __($column['#title']);
            }
            }
            return $columns;
        } // function
		
	/**
         * Populate the new column with data from a custom callback
         * @param string $column_id
         * @param int $post_id 
         */	
        function type_column_callback($column_id, $post_id){
          
            if (is_callable($this->columns[$column_id]['#callback'])) {
                call_user_func($this->columns[$column_id]['#callback'],$column_id, $post_id);
            } 
            
            
            $parts = preg_split('/(\[|\])/', $this->columns[$column_id]['#callback'], null, PREG_SPLIT_NO_EMPTY);
            
            $custom_args = get_post_meta($post_id,$parts[0],true);
           
            array_shift($parts);
            foreach ($parts as $part) {
                $custom_args = $custom_args[$part];
            }
            print $custom_args;
         
        } // function
        
        
        /**
         * Define some labels quickly and dirty
         */
        function default_labels() {
            
            // define the labels array
            $labels = array(
                'name' => _x('Testimonials', 'post type general name'),
                'singular_name' => _x('Testimonials', 'post type singular name'),
                'add_new' => _x('Add Testimonial', 'book'),
                'add_new_item' => __('Add New Testimonial'),
                'edit_item' => __('Edit Testimonial'),
                'new_item' => __('New Testimonial'),
                'all_items' => __('All Testimonials'),
                'view_item' => __('View Testimonial'),
                'search_items' => __('Search Testimonials'),
                'not_found' =>  __('No testimonials found'),
                'not_found_in_trash' => __('No testimonials found in Trash'), 
                'parent_item_colon' => '',
                'menu_name' => 'Testimonials'
            );
        
            // save
            $this->labels = $labels;
        } // functions
        
        
        // Change the placeholder text
        function enter_title_here( $text, $post ) {  
            return ($post->post_type == $this->post_type && !empty($this->placeholder)) ? $this->placeholder : $text ;
        }  // end f

        function register_post_type() {
            
            // post type arguments
            $args = array(
                'exclude_from_search' => $this->exclude_from_search,
                'labels' => $this->labels,
                'public' => $this->public,
                'publicly_queryable' => $this->publicly_queryable,
                'show_ui' => true, 
                'show_in_menu' => $this->show_in_menu, 
                'query_var' => true,
                'rewrite' => true,
                'capability_type' => 'post',
                'has_archive' => true, 
                'hierarchical' => false,
                'menu_position' => null,
                'supports' => $this->supports,
                'rewrite' => $this->rewrite
            ); 
            
            // regsiter the psot type
            register_post_type($this->post_type, $args);
        } // function

        
        function type_post_updated_messages($messages) {
            global $post_ID, $post;
            $messages[$this->post_type] = array(
                0 => '', // Unused. Messages start at index 1.
                1 => sprintf( __('Paage updated. <a href="%s">View page</a>'), esc_url( get_permalink($post_ID) ) ),
                2 => __('Custom field updated.'),
                3 => __('Custom field deleted.'),
                4 => __('Page updated.'),
                5 => isset($_GET['revision']) ? sprintf( __('Page restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
                6 => sprintf( __('Page published. <a href="%s">View page</a>'), esc_url( get_permalink($post_ID) ) ),
                7 => __('Page saved.'),
                8 => sprintf( __('Page submitted. <a target="_blank" href="%s">Preview page</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
                9 => sprintf( __('Page scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview page</a>'), date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
            10 => sprintf( __('Pagase draft updated. <a target="_blank" href="%s">Preview page</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
            );
            return $messages;
        }
        
        function type_restrict_manage_posts_custom() {
            if (!empty($this->restrict_manage_posts)) {
                foreach ($this->restrict_manage_posts as $key => $values) {
                     echo "<select name='" . $key . "' class='postform'>";
                     echo "<option value=''>" . $values['#default'] . "</option>";
                     foreach ($values['#options'] as $option_key => $option_value)
                    
                        echo "<option " . selected( $_GET[$key], $option_key ) . " value='permanent'>" . $option_value . "</option>";
                    echo "</select>";
                }
            }
        }
        
        /**
         * Set the name of the post type
         * @param string $name
         * @return \Type 
         */
        function name($name) {
            $this->labels['name'] = _x($name, 'post type general name');
            return $this;
        } // function
        
        /**
         * Set the singluar name
         * @param string $singluar_name
         * @return \Type 
         */
        function singular_name($singluar_name) {
            $this->labels['singluar_name'] = _x($singluar_name, 'post type singular name');
            return $this; 
        } // function
        
        /**
         * Set the add new text
         * @param string $add_new
         * @return \Type 
         */
        function add_new($add_new) {
            $this->labels['add_new'] = _x($add_new, $this->post_type);
            return $this;
        } // function
        
        /**
         * Set the add new item text
         * @param string $add_new_item
         * @return \Type 
         */
        function add_new_item($add_new_item) {
            $this->labels['add_new_item'] = __($add_new_item);
            return $this;
        } // function
        
        /**
         * Set the edit item text
         * @param string $edit_item
         * @return \Type 
         */
        function edit_item($edit_item) {
             $this->labels['edit_item'] =  __($edit_item);
             return $this;
        } // function
        
        /**
         * Set the new item text
         * @param string $new_item
         * @return \Type 
         */
        function new_item($new_item) {
             $this->labels['new_item'] =  __($new_item);
             return $this;
        } // function
        
        /**
         * Set the view item text
         * @param string $view_item
         * @return \Type 
         */
        function view_item($view_item) {
             $this->labels['view_item'] =  __($view_item);
             return $this;
        } // function
        
        /**
         * Set the all items text
         * @param string $all_items
         * @return \Type 
         */
        function all_items($all_items) {
             $this->labels['all_items'] =  __($all_items);
             return $this;
        } // function
        
        /**
         * Set the search items text
         * @param string $search_items
         * @return \Type 
         */
        function search_items($search_items) {
             $this->labels['search_items'] =  __($search_items);
             return $this;
        } // function
        
        /**
         * Set the not found text
         * @param string $not_found
         * @return \Type 
         */
        function not_found($not_found) {
             $this->labels['not_found'] =  __($not_found);
             return $this;
        } // function
        
        /**
         * Set the not found in trash text
         * @param string $not_found_in_trash
         * @return \Type 
         */
        function not_found_in_trash($not_found_in_trash) {
             $this->labels['not_found_in_trash'] =  __($not_found_in_trash);
             return $this;
        } // function
        
        /**
         * Parent item coln
         * @param string $parent_item_colon
         * @return \Type 
         */
        function parent_item_colon($parent_item_colon) {
             $this->labels['parent_item_colon'] =  $parent_item_colon;
             return $this;
        } // function
        
        /**
         * Set the menu name text
         * @param string $menu_name
         * @return \Type 
         */
        function menu_name($menu_name) {
             $this->labels['menu_name'] =  $menu_name;
             return $this;
        } // function
        
        /**
         * Add a new column to the post type management page
         * @param int $id
         * @param string $title
         * @param array $callback
         * @return \Type 
         */
        function column($id,$title,$callback) {
            $this->columns[$id] = array(
                '#title' => $title,
                '#callback' => $callback,
             
            );
            return $this;
        } // function
        
        function  filter($filters) {
            $this->filter = array_merge($filters,$this->filter);
            return $this;
        }
        /**
         * Is the post type public
         * @param boolean $public
         * @return \Type 
         */
        function publicly_queryable($publicly_queryable = true) {
            $this->publicly_queryable = $publicly_queryable;
            return $this;
        } // function

         /**
         * Is the post type public
         * @param boolean $public
         * @return \Type 
         */
        function is_public($public) {
            $this->public = $public;
            return $this;
        } // function
        
        /**
         * Should the post type have its menu links in the sidebar
         * @param boolean $show_in_menu
         * @return \Type 
         */
        function show_in_menu($show_in_menu = true) {
            $this->show_in_menu = $show_in_menu;
            return $this;
        } // function
        
           /**
         * has_archive
         * @param boolean $has_archive
         * @return \Type 
         */
        function has_archive($has_archive = true) {
            $this->has_archive = $has_archive;
            return $this;
        } // function
        
          /**
         * Should the post type have its menu links in the sidebar
         * @param boolean $show_in_menu
         * @return \Type 
         */
        function show_in_nav_menus($show_in_nav_menus = true) {
            $this->show_in_nav_menus = $show_in_nav_menus;
            return $this;
        } // function
        
        /**
         * post_title_template
         * @param string $post_title_template
         * @return \Type 
         */
        function post_title_template($post_title_template = '') {
            $this->post_title_template = $post_title_template;
            return $this;
        } // function
        
        /**
         * Disable revision for thsi post type
         * @param boolean $disable_revisions
         * @return \Type 
         */
        function disable_revisions($disable_revisions = true) {
            $this->disable_revisions = $disable_revisions;
            return $this;
        } // function
        
        
        function restrict_manage_posts($restrict) {
            $this->restrict_manage_posts = $restrict;
            return $this;
        }
        /**
         * Autosave
         * @param string $parent_item_colon
         * @return \Type 
         */
        function disable_autosave($disable_autosave = true) {
             $this->disable_autosave =  $disable_autosave;
             return $this;
        } // function
        
         /**
         * Autosave
         * @param string $parent_item_colon
         * @return \Type 
         */
        function rewrite($rewrite = array()) {
             $this->rewrite =  $rewrite;
             return $this;
        } // function
        
        
        /**
         * Should the post type have its menu links in the sidebar
         * @param boolean $show_in_menu
         * @return \Type 
         */
        function show_ui($show_ui = true) {
            $this->show_ui = $show_ui;
            return $this;
        } // function
        
        /**
         * Taxonomies
         * @param array $taxonomies
         * @return \Type 
         */
        function taxonomies($taxonomies = array()) {
            $this->taxonomies = $taxonomies;
            return $this;
        } // function
        
           
        /**
         * Supports
         * @param array $supports
         * @return \Type 
         */
        function supports($supports = array()) {
            $this->supports = $supports;
            return $this;
        } // function
        
        
        /**
         * Set the placeholder text for the title field
         * @param type $placeholder
         * @return \Type 
         */
        function placeholder($placeholder) {
            $this->placeholder = $placeholder;
            return $this;
        } // function

        function exclude_from_search() {
            $this->exclude_from_search = $exclude_from_search;
            return $this;
        } // function
        
        /**
         * Remove any specifed columns from the manage post type page
         * @param array $defaults Columns
         * @return array 
         */
        function type_remove_column($defaults) {
            foreach ($this->remove_columns as $column) {
                unset($defaults[$column]);
            }
            return $defaults;
        } // function
       
        function remove_column($column) {
            $this->remove_columns[] = $column;
            return $this;
            
        } // function
        
        /**
         * Change the length of the excerpt for this post type 
         */
        function excerpt_length($excerpt_length) {
            $this->excerpt_length = $excerpt_length;
            return $this;
        } // function
        
        
                
        /**
         * Modify the qty of posts to return on the edit page
         * @param int $per_page
         * @param string $post_type
         * @return int 
         */
        function type_edit_post_per_page( $per_page, $post_type ) {

            $edit_per_page = 'edit_' . $post_type . '_per_page';
            $per_page = (int) get_user_option( $edit_per_page );
            if ( empty( $per_page ) || $per_page < 1 )
                $per_page = 1;

            return $per_page;
        }
        
        /**
         * Automatically generate  the post titles for custom post types with hidden titles
         */
	function auto_generate_title($data, $postarr) {
               
            if (!is_array($this->post_title_template)) {
                return $data;
            }
            
		// If it is our form has not been submitted, so we dont want to do anything
		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

		if ($data['post_type'] == $this->post_type) {
                  
                        $title = '';
                        foreach ($this->post_title_template as $template_part) {
                              $keys = preg_split('/(\[|\])/', $template_part, null, PREG_SPLIT_NO_EMPTY);
                               // print '<pre>' . var_export($keys,true) . '</pre>'; 
                              
                                // if there is only one entry then j ust append it as its not a field
                                if (count($keys) == 1) {
                                    $title .= $keys[0];
                                } else {
                                
                                    $custom_args = $postarr;
                                    foreach ($keys as $key) {
                                
                                            $custom_args = $custom_args[$key];
                                    }
                                    $title .= $custom_args;
                                }
                        }
                    
			// Generate the post title
        		$data['post_title'] = $title;
                        
			// Generate the post slug
			$data['post_name'] = sanitize_title($data['post_title'] );
	
		} // function
		
		// always return $data
		return $data;	
        } // function

        /**
        * Add the theme options link to the appearance menu
        */
        function admin_bar_edit_link() {
            global $wp_admin_bar, $diyoptions;
            $wp_admin_bar->add_menu( array(
                'parent' => 'new-content', // use 'false' for a root menu, or pass the ID of the parent menu
                'id' => 'menu_' . $this->post_type . '_add_link', // link ID, defaults to a sanitized title value
                'title' => ucwords($this->post_type), // link title
                'href' => admin_url('post-new.php?post_type=' . $this->post_type), // name of file
                'meta' => false // array of any of the following options: array( 'html' => '', 'class' => '', 'onclick' => '', target => '', title => '' );
            ));
        } // end function

    } // end class
} // end class exists