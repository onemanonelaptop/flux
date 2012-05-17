<?php
/**
 * Plugin Name: Flux
 * Plugin URI: http://github.com/onemanonelaptop/flux
 * Description: Useful tools for creating plugins
 * Version: 0.0.1
 * Author: Rob Holmes
 * Author URI: http://github.com/onemanonelaptop
 */
 
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
 
add_action( 'plugins_loaded', 'flux' );
/**
 * Runs on plugins_loaded action and defines the required classes
 * after the classes are defined the flux action is fired, this is where child
 * plugins define their functionality 
 */
if (!function_exists('flux')) {
    function flux() {
        include_once('plugin.php'); // Plugin
        include_once('form.php');   // Forms
        include_once('type.php');   // Custom Post Types
        include_once('tax.php');    // Custom Taxonomies
        do_action('flux');
    } // function
} // if
