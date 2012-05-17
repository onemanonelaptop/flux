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
 * Wrapper class for a custom taxonomy
 */
if (!class_exists('Tax')) {
    class Tax {
        
        /**
         * @var string The slug of the new post type
         */
        var $slug = 'example';
        var $post_type = 'post';
        var $rewrite_slug = '';
        var $hierarchical = false;
        var $show_ui = true;
        var $query_var = true;
        
        /**
         * Constructor
         * @param string $type  The slug of the post type
         */
        function __construct($slug) {
            // save the slug
            $this->slug = $slug;
            
            // Register the new taxonomy
            add_action( 'init', array($this,'register_tax') );
        }
        
        function register_tax() {
            foreach ($this->post_types as $post_type) {
                register_taxonomy( $this->slug, $post_type, array(
                    'hierarchical' => $this->hierarchical,
                    'labels' => $this->labels, /* NOTICE: the $labels variable here */
                    'show_ui' => $this->show_ui,
                    'query_var' => $this->query_var,
                    'rewrite' => array( 'slug' => $this->rewrite_slug ),
                )); // end taxonomy
            }
        }
        
        /**
         * Set the name of the post type
         * @param string $name
         * @return \Type 
         */
        function name($name) {
            $this->labels['name'] = _x($name, 'taxonomy general name');
            return $this;
        } // function
        
        function post_types($post_types) {
            $this->post_types = $post_types;
            return $this;
        } // function
        function singular_name($name) {
            $this->labels['singular_name'] = _x($name, 'taxonomy singular name');
            return $this;
        } // function
        
        function search_items($search_items) {
            $this->labels['search_items'] = __($search_items);
            return $this;
        } // function
        
         function popular_items($popular_items) {
            $this->labels['popular_items'] = __($popular_items);
            return $this;
        } // function
        
        function all_items($all_items) {
            $this->labels['all_items'] = __($all_items);
            return $this;
        } // function
        
        
        function parent_item($parent_item) {
            $this->labels['parent_item'] = __($parent_item);
            return $this;
        } // function
        
         function parent_item_colon($parent_item_colon) {
            $this->labels['parent_item_colon'] = __($parent_item_colon);
            return $this;
        } // function
        
        function edit_item($edit_item) {
            $this->labels['edit_item'] = __($edit_item);
            return $this;
        } // function
        
         function update_item($update_item) {
            $this->labels['update_item'] = __($update_item);
            return $this;
        } // function
        
       function add_new_item($add_new_item) {
            $this->labels['add_new_item'] = __($add_new_item);
            return $this;
        } // function
        
         function new_item_name($new_item_name) {
            $this->labels['new_item_name'] = __($new_item_name);
            return $this;
        } // function
        
        function separate_items_with_commas($separate_items_with_commas) {
            $this->labels['separate_items_with_commas'] = __($separate_items_with_commas);
            return $this;
        } // function
        
         function add_or_remove_items($add_or_remove_items) {
            $this->labels['add_or_remove_items'] = __($add_or_remove_items);
            return $this;
        } // function
        
         function choose_from_most_used($choose_from_most_used) {
            $this->labels['choose_from_most_used'] = __($choose_from_most_used);
            return $this;
        } // function
       
    } // end class
} // end class exists 