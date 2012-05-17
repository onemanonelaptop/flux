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

// If the class has already been defined then dont define it again
if (!class_exists('Form')) {
  
    /**
    * Define the Form class 
    */
    class Form {

        /**
         * @var type Store the form definition
         */
        var $form = array();
        
        /**
         * @var array Store the values to be applied to the form
         */
        var $values = array();
        
        /**
         * Contruct
         * @param array $form An array of metabox form definitions keyed by metabox id
         */
        function __construct($form = array()) {
            // save the incoming form array
            $this->form($form);
        } // function
              
        /**
         * Save the form definition and add the metabox array keys as parents
         * @param array $form 
         */
        function form($form) {
            $this->form = $form;
            // add the array key as the parent of all level 1 children
            foreach ($form as $key => $value) {
                if (isset($value['#type']) && $value['#type'] == 'metabox') {
                    $form['#form_id'] = $key; // save the key as the form id
                    $form[$key]['#parents'][] = $key; // store the parent field key
                } 
            } 
        } // function
         
        /**
         * Store the values
         * @param array $values 
         * @return \Form 
         */
        function values($values) {
            $this->values = $values;
            return $this;
        } // function
      
        /**
         * Render the form and return the rendered html.
         * @return string 
         */
        function render() {
            // save a copy of the form definition
            $form = $this->form;
            // save a copy of the values
            $values = $this->values;
            // prepare the form wwith defaults and expand multigroups
            self::prepare_form( $form);
            // populate the form with the values
            self::populate_form( $form);
            // return the rendered html for printing
            return self::render_form($form); 
        } // function

        /**
         * Load all the defaults for each form element and expand any multigroups
         * @param type $element
         * @return boolean 
         */
        function prepare_form( &$element) {

            // Initialize as unprocessed.
            $element['#processed'] = FALSE;

            // Use element defaults.
            if (isset($element['#type']) && empty($element['#defaults_loaded']) && ($info = self::element_info($element['#type']))) {
                // Overlay $info onto $element, retaining preexisting keys in $element.
                $element += $info;
                $element['#defaults_loaded'] = TRUE;
            }

            // Assign basic defaults common for all form elements.
            $element += array(
                '#required' => FALSE,
                '#attributes' => array(),
                '#title_display' => 'before',
            );

            // Allow for elements to expand to multiple elements, e.g., radios,
            // checkboxes and files.
            if (isset($element['#process']) && !$element['#processed']) {
                foreach ($element['#process'] as $process) {
                  
                      if (method_exists('Form',$process)) { 
                        $element = call_user_func(array('self',$process), $element);
                    } // exists
                }
                $element['#processed'] = TRUE;
            }

            // Recurse through all child elements.
            $count = 0;
            foreach (self::element_children($element) as $key) {
                // Prior to checking properties of child elements, their default properties
                // need to be loaded.
                if (isset($element[$key]['#type']) && empty($element[$key]['#defaults_loaded']) && ($info = self::element_info($element[$key]['#type']))) {
                    $element[$key] += $info;
                    $element[$key]['#defaults_loaded'] = TRUE;
                }


                // Deny access to child elements if parent is denied.
                if (isset($element['#access']) && !$element['#access']) {
                    $element[$key]['#access'] = FALSE;
                }

                // Make child elements inherit their parent's #disabled and #allow_focus
                // values unless they specify their own.
                foreach (array('#disabled', '#allow_focus') as $property) {
                    if (isset($element[$property]) && !isset($element[$key][$property])) {
                        $element[$key][$property] = $element[$property];
                    }
                }

                // Don't squash existing parents value.
                if (!isset($element[$key]['#parents'])) {
                    // Check to see if a tree of child elements is present. If so,
                    // continue down the tree if required.
                    $element[$key]['#parents'] =  array_merge($element['#parents'], array($key));
                }

                $element[$key] = self::prepare_form( $element[$key]);
                $count++;
            }

            // Special handling if we're inside a multigroup
            if (isset($element['#type']) && $element['#type'] == 'multigroup') {

                // remote the first parent
                // get the values for the remaing parent keys
                $find_value = $this->values;
                $parents = $element['#parents'];
                array_shift($parents);
                foreach (array_values($parents) as $part) {
                    $find_value = $find_value[$part];
                }
                
                // @todo change test to a sensible name
               // $test = $this->values[end(array_values($element['#parents']))];
          
                $test = $find_value; 

                if (!is_array($test)) {
                    $test = array(0 => '');
                }
                $tempform = array();

                foreach ($element as $key => $form) {
                    if ($key === '' || $key[0] !== '#') {
                        foreach ($test as $index => $value) {


                            // save the form against the multigroup integer keyed array
                            $tempform[$index][$key] = $form;

                            $temp_parent = array_pop($tempform[$index][$key]['#parents']);
                            $tempform[$index][$key]['#parents'][] = $index;
                            $tempform[$index][$key]['#parents'][] = $temp_parent;
                            $tempform[$index]['#type'] = 'group';

                            // delete the original field keys
                            unset($element[$key]);
                        } // foreach
                    } // if
                } // foreach
                //
                // merge the forms
                $element += $tempform;

                $element['#multigroup_processed'] = TRUE;
            }

            return $element;
        } // function
        
        
        /**
         * Populate the form with the passed in values
         * @param type $form 
         */
        function populate_form(&$form) {
            $values = $this->values;
            // Start off the recursion
            $this->form_values($form,$values);
        } // function
        
        /**
         * Change the structure of the values array so that it matches the form
         * array so it can be merged
         */
        function form_values(&$form,&$values) {
            // go through each element recurisvely    
            foreach($values as $key => $value) {
                // if it is an array then carry on recursivley
                if (is_array($values[$key])) { 
                    $this->form_values($form[$key],$values[$key]); 
                } else {  // if its not an array then we should have an value
                    // save it 
                    $val = $values[$key];
                    // add it as the default value
                    $form[$key]['#value'] = $val;
                }
            } // foreach
        } // function

        /**
         * Render a form array
         * @param array $elements
         * @return string 
         */
        public static  function render_form(&$elements) {
            
            // Leave if there is nothing to render
            if (empty($elements)) {
                return;
            }

            // Do not print elements twice.
            if (!empty($elements['#printed'])) {
                return;
            }

            // If #markup is set, ensure #type is set. This allows to specify just #markup
            // on an element without setting #type.
            if (isset($elements['#markup']) && !isset($elements['#type'])) {
                $elements['#type'] = 'markup';
            }

            // If the defaults for this element type have not been loaded yet, populate
            // them.
            if (isset($elements['#type']) && empty($elements['#defaults_loaded'])) {
            //   print "Loading defaults...<br/>";
                $elements += self::element_info($elements['#type']);
            }

            // Make any final changes to the element before it is rendered. This means
            // that the $element or the children can be altered or corrected before the
            // element is rendered into the final text.
            if (isset($elements['#pre_render'])) {
                foreach ($elements['#pre_render'] as $function) {

                    if (method_exists('Form',$function)) { 
                        $elements = call_user_func(array('self',$function), $elements);
                    } // exists
                    
                } // foreach
            } // isset

            // Allow #pre_render to abort rendering.
            if (!empty($elements['#printed'])) {
                return;
            }

            // Get the children of the element, sorted by weight.
            $children = self::element_children($elements, TRUE );
      
            // Initialize this element's #children, unless a #pre_render callback already
            // preset #children.
            if (!isset($elements['#children'])) {
                $elements['#children'] = '';
            }

            // Call the element's #theme function if it is set. Then any children of the
            // element have to be rendered there.
            if (isset($elements['#theme'])) {
                $elements['#children'] = self::theme($elements['#theme'], $elements);
            }

            // If  the element has children, render them now.
            if ($elements['#children'] == '') {
                foreach ($children as $key) {
                    $elements['#children'] .= self::render_form($elements[$key]);
                }
            }

            // Let the theme functions in #theme_wrappers add markup around the rendered
            // children.
            if (isset($elements['#theme_wrappers'])) {
                foreach ($elements['#theme_wrappers'] as $theme_wrapper) {
                    $elements['#children'] = self::theme($theme_wrapper, $elements);
                }
            }

            // add the prefix and/or the suffix to the final output
            $prefix = isset($elements['#prefix']) ? $elements['#prefix'] : '';
            $suffix = isset($elements['#suffix']) ? $elements['#suffix'] : '';
            $output = $prefix . $elements['#children'] . $suffix;

            // set the printed value
            $elements['#printed'] = TRUE;
            return $output;
        } // function
 
        /**
         * Return a sorted list of the element's children by their array keys
         * @param array $elements
         * @return array 
         */
        function element_children(&$elements) {

            // Filter out properties from the element, leaving only children.
            $children = array();
         
            foreach ($elements as $key => $value) {
                if ($key === '' || $key[0] !== '#') {
                    $children[$key] = $value;
                }
            }
            return array_keys($children);
        } // end function

        /**
         * Pre render markup
         * @param array $elements
         * @return type 
         */
        function pre_render_markup($elements) {
            $elements['#children'] = $elements['#markup']; 
            return $elements;
        } // function

        /**
         * Returns the html needed to print a label
         * @param array $args An array of field arguments
         * @param string $field_html The current fields html output
         * @return string 
         */
        public static function label($args,$field_html) {

            // build the html
            $label_html = '<label class="field-label' . ('#title_display' == 'hidden' ? " element-invisible" :  "") . '" for="' . $args['name'] . '" > ' . $args['title'] . '</label>';

            // Does the label come before or after the field
            if ($args['#title_display'] == 'before' || $args['#title_display'] == 'invisible') {
                return $label_html . $field_html;
            } else if ($args['#title_display'] == 'after') {
                return $field_html . $label_html;
            } // endif
            
            // in any other case just return what we passed in
            return $field_html;
            
        } // function

        /**
         * Set the attributes
         * @param array $element
         * @param array $map 
         */
        function element_set_attributes(array &$element, array $map) {
            foreach ($map as $property => $attribute) {
                // If the key is numeric, the attribute name needs to be taken over.
                if (is_int($property)) {
                $property = '#' . $attribute;
                }
                // Do not overwrite already existing attributes.
                if (isset($element[$property]) && !isset($element['#attributes'][$attribute])) {
                $element['#attributes'][$attribute] = $element[$property];
                }
            } // foreach
        } // function

        /**
         * Use the #parents array to generate the name attribute for the field
         * @todo change to name
         * @param array $element 
         */
        function element_set_name(array &$element) {
            // get the first element of the array as it wont be needing brackets
            $stem = array_shift($element['#parents']);
            // add brackets around the rest of the keys
            $element['#attributes']['name'] =  $stem  . implode('',array_map(array('self','bracket_me_up'),$element['#parents']));
        } // function

        /**
         *
         * @param array $keys
         * @return type 
         */
        function element_make_name(array $keys) {
            $keys = array_reverse($keys);
            $stem = array_shift($keys);
            return  $stem  . implode('',array_map(array('self','bracket_me_up'),$keys));
        } // function
        
        /**
         * Callback function to add square brackets to a string, used in the 
         * generation of the name attribute 
         * @param type $item
         * @return type 
         */
        function bracket_me_up($item) {
            return "[$item]";
        } // function

        /**
         * Wrapper to call an elements theming function
         * 
         * @param string $type The '#type' of field to render
         * @param array $args Arguments to pass to the function
         * @return string 
         */
        function theme($type,$args) {
            $function = 'theme_' . $type;
            return self::$function($args);
        } // function

        /**
        * Render a color picker field widget
        * @param    array   $element
        * @access   public
        */ 
        public static function theme_color($element) {

            $element['#attributes']['type'] = 'text'; 

            self::element_set_attributes($element, array('id', 'name', 'value', 'size', 'maxlength'));
            self::element_set_class($element, array('form-color'));
            self::element_set_name($element);
            
            return "<span style='position:relative; display:inline-block;'><span style='background:" . (!empty($element['#value']) ? $element['#value'] : " url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAIAAAAC64paAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyJpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYxIDY0LjE0MDk0OSwgMjAxMC8xMi8wNy0xMDo1NzowMSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNS4xIFdpbmRvd3MiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6QzIwM0UzNzZEODc2MTFFMDgyM0RFQUJEOEU1NEI2NjkiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6QzIwM0UzNzdEODc2MTFFMDgyM0RFQUJEOEU1NEI2NjkiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDpDMjAzRTM3NEQ4NzYxMUUwODIzREVBQkQ4RTU0QjY2OSIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDpDMjAzRTM3NUQ4NzYxMUUwODIzREVBQkQ4RTU0QjY2OSIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/Ps3q5KgAAAKOSURBVHjaXJRZTypBEIWZYVPgKsgeSAgQCUvgBeP//wGQyBaBRCFACKIgO7L7zdS94439MFTXqa5zqroapVqtXi6XdDpts9leXl4+Pz8jkUg4HN7tds/Pz4qiZLNZq9Xa6/XG47HX643H4wJZWIfDwWQyEcT3dDqxPZ/PJn0dj0dFX9g4f0FQKsvlEtf7+/t+vw8EAna7Hc9sNsPw+/3EQcixu7u76+vrr6+vj48PgUiqulyum5ubxWIxmUyurq7Y4sVerVZ/9DWfz9miEZtjBqRFkhgB0KIZTFVVDLZms5kuwGxAJCWSggVia+l2u0QUi0WONZtN9CcSiVgshtRyuUzE4+Mj306nMxgMQqHQ/f29QFrD0Ew+lJCP9G63m9D1ek1Lbm9vsYHISyQQhAZEvKYE5kqlgrdQKFDJaDR6fX2lqnw+D/T09ESfUqkUPaP+RqNhQBbqodskhvakL7zYeLBJjQEhMRJpQNoF1+t1IqhTJoHcwWCQO6Mx1ElEMpkEGg6H0+kU5dFoVCBkW7bbrVCxoRObzYYt0WTEplrujy+c1IVgA4Jf4dJlA8wY0CEkyX2wJZFApMADRP0CaUPCuPp8PlKgmcQIxouNSJ++uLx+vy9T5XA4DIiDP8xcgNPpRCEGtaCKrUAQQgWhiBdIGxJuhYiHhweO8VbgoUP0jxSlUun/IYGf18aQCPQzJOQjMYVxmVInzQOSITHry+Px0C0D+jskiOHqkZrJZCibIaEwhOVyOdBarUaTkEORvLZ2uy0QHKo8Zklh+rewZfIEEvsXpKGtVosfBgMZNA9VTAKqKOzt7Q2IOmkH/zC8czjhFwiniloO4GWq8RIBGzbt3ehLIAiBaLsBcfBbgAEArCsu6B0YK4AAAAAASUVORK5CYII=);")  ."' class='swatch'></span><input " . self::attributes($element['#attributes'])  . "  />" 			
            . "<div   class='picker' style=''></div></span>";

        } // function
        
        /**
        * Build a textarea field widget
        *
        * @since	0.0.1
        * @param	array 	$args 	The width, name, value, placeholder and description of the textarea field
        */
        public static function theme_textarea($element) {
            self::element_set_attributes($element, array('id', 'name', 'cols', 'rows'));
            self::element_set_class($element, array('form-textarea','form-field'));
            self::element_set_name($element);
            $wrapper_attributes = array(
                'class' => array('form-textarea-wrapper'),
            ); 
            $output = '<div' . self::attributes($wrapper_attributes) . '>';
            $output .= '<textarea' . self::attributes($element['#attributes']) . '>' . (isset($element['#value']) ? esc_textarea($element['#value']) : '') . '</textarea>';
            $output .= '</div>';
            return $output;
        } // function
        
        /**
        * Build an editor field widget
        *
        * @since	0.0.1
        * @param	array 	$args 	The width, name, value, placeholder and description of the textarea field
        */
        public static function theme_editor($element) {
            self::element_set_attributes($element, array('id', 'name', 'cols', 'rows'));
            self::element_set_class($element, array('form-editor','form-field'));
            self::element_set_name($element);
            $wrapper_attributes = array(
                'class' => array('form-editor-wrapper'),
            ); 
            $output = '<div' . self::attributes($wrapper_attributes) . '>';
           
            $settings=array('textarea_name' => $element['#attributes']['name']);
            ob_start();
            wp_editor(  $element['#value'], $element['#attributes']['name'], $settings );
            $output .= ob_get_clean();
     
            $output .= '</div>';
            return $output;
        } // function
        
        
        /**
         * Checkbox theme function
         * @param array $element
         * @return string 
         */
        function theme_checkbox($element) {
            $element['#attributes']['type'] = 'checkbox';
            self::element_set_attributes($element, array('id', 'name','#return_value' => 'value'));

            // Unchecked checkbox has #value of integer 0.
            if (!empty($element['#value'])) {
                $element['#attributes']['checked'] = 'checked';
            }
            self::element_set_class($element, array('form-checkbox'));
            self::element_set_name($element);
            return '<input' . self::attributes($element['#attributes']) . ' />';
        } // function

        /**
         * File theme function
         * @param type $element
         * @return string 
         */
        function theme_file($element) {
            $element['#attributes']['type'] = 'file';
            self::element_set_attributes($element, array('id', 'name', 'size'));
            self::element_set_class($element, array('form-file'));
            self::element_set_name($element);
            return '<input' . self::attributes($element['#attributes']) . ' />';
        } // function

        /**
         * Set the field attributes
         * @param array $attributes
         * @return type 
         */
        function attributes(array $attributes = array()) {
            foreach ($attributes as $attribute => &$data) {
                $data = implode(' ', (array) $data);
                $data = $attribute . '="' . esc_attr($data) . '"';
            }
            return $attributes ? ' ' . implode(' ', $attributes) : '';
        } // function

        /**
         * Set CSS classes for an element
         * @param type $element
         * @param type $class 
         */
        function element_set_class(&$element, $class = array()) {
           
            
            if (!empty($class)) {
                if (!isset($element['#attributes']['class'])) {
                $element['#attributes']['class'] = array();
                }
                $element['#attributes']['class'] = array_merge($element['#attributes']['class'], $class);
            }
            // This function is invoked from form element theme functions, but the
            // rendered form element may not necessarily have been processed by
            // prepare().
            // @todo this can probably go
            if (!empty($element['#required'])) {
                $element['#attributes']['class'][] = 'required';
            }
        } // function
        
        /**
         * Radio Buttons
         * @param type $variables
         * @return type 
         */
        function theme_radios($variables) {
            $element = $variables;
            $attributes = array();
            if (isset($element['#id'])) {
                $attributes['id'] = $element['#id'];
            }
            $attributes['class'] = 'form-radios';
            if (!empty($element['#attributes']['class'])) {
                $attributes['class'] .= ' ' . implode (' ', $element['#attributes']['class']);
            }
            return '<div' . self::attributes($attributes) . '>' . (!empty($element['#children']) ? $element['#children'] : '') . '</div>';
        } // function
        

        function theme_radio($element) {

            $element['#attributes']['type'] = 'radio';
            self::element_set_attributes($element, array('id', 'name', '#return_value' => 'value'));
            self::element_set_name($element);
//            if (isset($element['#return_value']) && $element['#value'] !== FALSE && $element['#value'] == $element['#return_value']) {
//                $element['#attributes']['checked'] = 'checked';
//            }
            self::element_set_class($element, array('form-radio'));

            return '<input' . self::attributes($element['#attributes']) . ' />';
        }

        /**
         * Password theme function
         * @param array $element
         * @return string 
         */
        function theme_password($element) {
            $element['#attributes']['type'] = 'password';
            self::element_set_attributes($element, array('id', 'name', 'size', 'maxlength'));
            self::element_set_class($element, array('form-text'));
            self::element_set_name($element);
            return '<input' . self::attributes($element['#attributes']) . ' />'; 
        } // function

        /**
         *
         * @param type $element
         * @return real 
         */
        function form_process_radios($element) {
            if (count($element['#options']) > 0) {
                $weight = 0;
                foreach ($element['#options'] as $key => $choice) {
                
                $element += array($key => array());
                // Generate the parents as the autogenerator does, so we will have a
                // unique id for each radio button.
                $parents_for_id = array_merge($element['#parents'], array($key));
                $element[$key] += array(
                    '#type' => 'radio', 
                    '#title' => $choice,
                    // The key is sanitized in drupal_attributes() during output from the
                    // theme function. 
                    '#return_value' => $key,
                    // Use default or FALSE. A value of FALSE means that the radio button is
                    // not 'checked'. 
                    '#default_value' => isset($element['#default_value']) ? $element['#default_value'] : FALSE, 
                    '#attributes' => $element['#attributes'], 
                    '#parents' => $element['#parents'], 
                    '#id' => ''
                );
                }
            }
            return $element;
        } // function

        /**
         * Return the defaults for an element type
         * @param type $type
         * @return type 
         * @todo merge with default element info
         */
        public static  function element_info($type) {
            // load up the defaults
            $defaults = self::default_element_info();
            // return the defaults for the requested #type
            return $defaults[$type];
        } // function

        /**
         * Define the defaults for all field widget types
         * @return array 
         */
        function default_element_info() {

            $types['textfield'] = array(
                '#input' => TRUE, 
                '#size' => 60, 
                '#maxlength' => 128, 
                '#theme' => 'textfield', 
                '#theme_wrappers' => array('form_element'),
            );

            // textarea
            $types['textarea'] = array(
                '#input' => TRUE, 
                '#cols' => 60, 
                '#rows' => 5, 
                '#resizable' => TRUE, 
                '#theme' => 'textarea', 
                '#theme_wrappers' => array('form_element'),
            );

            // wp_editor
            $types['editor'] = array(
                '#input' => TRUE, 
                '#cols' => 60, 
                '#rows' => 5, 
                '#resizable' => TRUE, 
                '#theme' => 'editor', 
                '#theme_wrappers' => array('form_element'),
            );

            // suggest/autocomplete
            $types['suggest'] = array(
                '#input' => TRUE, 
                '#cols' => 60, 
                '#rows' => 5, 
                '#resizable' => TRUE, 
                '#theme' => 'suggest', 
                '#theme_wrappers' => array('form_element'),
            );

            // radios
            $types['radios'] = array(
                '#input' => TRUE, 
                '#process' => array('form_process_radios'), 
                '#pre_render' => array('form_pre_render_conditional_form_element'),
                '#theme_wrappers' => array('form_element'),
            );

            // radio
            $types['radio'] = array(
                '#input' => TRUE, 
                '#default_value' => NULL, 
                '#theme' => 'radio', 
                '#theme_wrappers' => array('form_element'), 
                '#title_display' => 'after',
            );

            // checkboxes
            $types['checkboxes'] = array(
                '#input' => TRUE, 
                '#theme_wrappers' => array('checkboxes'), 
                '#pre_render' => array('form_pre_render_conditional_form_element'),
            );

            // checkbox
            $types['checkbox'] = array(
                '#input' => TRUE, 
                '#return_value' => 1, 
                '#theme' => 'checkbox',  
                '#theme_wrappers' => array('form_element'), 
                '#title_display' => 'after',
            );

            // select
            $types['select'] = array(
                '#input' => TRUE, 
                '#multiple' => FALSE,  
                '#theme' => 'select', 
                '#theme_wrappers' => array('form_element'),
            );

            // multiselect
                $types['multiselect'] = array(
                '#input' => TRUE, 
                '#multiple' => FALSE, 
                '#theme' => 'multiselect', 
                '#theme_wrappers' => array('form_element'),
            );

            // date picker
            $types['date'] = array(
                '#input' => TRUE, 
                '#element_validate' => array('date_validate'), 
                '#theme' => 'date', 
                '#theme_wrappers' => array('form_element'),
            );

            // date picker
            $types['map'] = array( 
                '#theme_wrappers' => array('map'),
            );

            // file
            $types['file'] = array(
                '#input' => TRUE, 
                '#size' => 60, 
                '#theme' => 'file', 
                '#theme_wrappers' => array('form_element'),
            );

            // markup
            $types['item'] = array(
                '#markup' => '', 
                '#pre_render' => array('pre_render_markup'), 
                '#theme_wrappers' => array('form_element'),
                '#printed' => FALSE
            ); 

            // hidden
            $types['hidden'] = array(
                '#input' => TRUE, 
                '#process' => array('ajax_process_form'), 
                '#theme' => 'hidden',
            );


            // markup
            $types['markup'] = array(
                '#markup' => '', 
                '#pre_render' => array('pre_render_markup'),
                '#printed' => FALSE
            );

            // link
            $types['link'] = array(
                '#pre_render' => array('drupal_pre_render_link', 'pre_render_markup'),
            );

            // fieldset
            $types['fieldset'] = array(
                '#collapsible' => FALSE, 
                '#collapsed' => FALSE, 
                '#value' => NULL, 
                '#pre_render' => array('form_pre_render_fieldset'), 
                '#theme_wrappers' => array('fieldset'),
            );

            $types['multigroup'] = array(
                '#collapsible' => FALSE, 
                '#collapsed' => FALSE, 
                '#value' => NULL, 
            //     '#process' => array('form_process_fieldset', 'ajax_process_form'), 
                '#pre_render' => array('form_pre_render_multigroup'), 
                '#theme_wrappers' => array('multigroup'),
            );

            // Generic container
            $types['container'] = array(
                '#theme_wrappers' => array('container'), 
            );

            // Wordpress metabox
            $types['metabox'] = array( );

            // field multigroup
            $types['group'] = array(
                '#theme_wrappers' => array('form_group'),
            );

            // color picker
            $types['color'] = array(
                '#input' => TRUE, 
                '#theme' => 'color', 
                '#theme_wrappers' => array('form_element'),
            );

            // File attachment
            $types['attachment'] = array(
                '#input' => TRUE, 
                '#theme' => 'attachment', 
                '#theme_wrappers' => array('form_element'),
            );

            // Location lat/long
            $types['location'] = array(
                '#input' => TRUE, 
                '#theme' => 'location', 
                '#theme_wrappers' => array('form_element'),
            );
            
            // return the array
            return $types;
        } // function

     
        function theme_form_element_label( $element) {

            // If title and required marker are both empty, output no label.
            if ((!isset($element['#title']) || $element['#title'] === '') && empty($element['#required'])) {
                return '';
            }

            // If the element is required, a required marker is appended to the label.
            $required = !empty($element['#required']) ? self::theme('form_required_marker', array('element' => $element)) : '';

            $title = $element['#title'];

            $attributes = array();
            
            
            // Style the label as class option to display inline with the element.
            if ($element['#title_display'] == 'after') {
                $attributes['class'] = 'option';
            }
            // Show label only to screen readers to avoid disruption in visual flows.
            elseif ($element['#title_display'] == 'invisible') {
                $attributes['class'] = 'element-invisible';
            }

            if (!empty($element['#id'])) {
                $attributes['for'] = $element['#id'];
            }

            // The leading whitespace helps visually separate fields from inline labels.
            return ' <label' . self::attributes($attributes) . '>' . $title . "</label>\n";
        } // function   

        /**
         * 
         * @param type $variables
         * @return string 
         */
        function theme_fieldset($element ) {
          
            self::element_set_attributes($element, array('id'));
            self::element_set_class($element, array('form-wrapper'));
            if ($element['#collapsible']) {
                self::element_set_class($element, array('collapsible'));
            }
            if ($element['#collapsed']) {
                self::element_set_class($element, array('collapsed'));
            }
            $output = '<fieldset' . self::attributes($element['#attributes']) . '>';
            if (!empty($element['#title'])) {
                // Always wrap fieldset legends in a SPAN for CSS positioning.
                $output .= '<legend><span class="fieldset-legend">' . $element['#title'] . '</span></legend>';
            }
            $output .= '<div class="fieldset-wrapper">';
            if (!empty($element['#description'])) {
                $output .= '<div class="fieldset-description">' . $element['#description'] . '</div>';
            }
            $output .= $element['#children'];
            if (isset($element['#value'])) {
                $output .= $element['#value'];
            }
            $output .= '</div>';
            $output .= "</fieldset>\n";
            return $output;
        } // function

        /**
         * Multigroup theme function
         * @param array $variables
         * @return string 
         */
        function theme_multigroup($element) {
            self::element_set_attributes($element, array('id'));
            self::element_set_class($element, array('multigroup-wrapper'));

            $output = '<div ' . self::attributes($element['#attributes']) . '>';

            $output .= '<div class="multigroup-controller multigroup-sortable" data-stem="' . self::element_make_name($element['#parents']) . '" data-max="' . $element['#cardinality'] . '">';
            if (!empty($element['#description'])) {
                $output .= '<div class="multigroup-description">' . $element['#description'] . '</div>';
            }
           
       
            $output .= $element['#children'];
            $output .= '</div>';
            $output .= '<a href="#" class="form-type-another button-secondary" >Add Another</a>';
            $output .= "</div>\n";
            return $output;
        } // function

        /**
         * Add a wrapper around the group to give a nice jquery selector for duplicating the group
         * @param type $element
         * @return type 
         */
        function theme_form_group($element) {
            return "<div class='multigroup'>" . $element['#children'] . "</div>";
        } // function

        /**
         * Form element wrapper
         * @param array $element
         * @return string
         */
        function theme_form_element($element) {
            // This function is invoked as theme wrapper, but the rendered form element
            // may not necessarily have been processed by prepare().
            $element += array(
                '#title_display' => 'before',
            );

            // Add element #id for #type 'item'.
            if (isset($element['#markup']) && !empty($element['#id'])) {
                $attributes['id'] = $element['#id'];
            }
            // Add element's #type and #name as class to aid with JS/CSS selectors.
            $attributes['class'] = array('form-item');
            
             if (isset($element['#inline'])) {
                $attributes['class'][] = 'form-inline';
            }
            
            if (!empty($element['#type'])) {
                $attributes['class'][] = 'form-type-' . strtr($element['#type'], '_', '-');
            }
            if (!empty($element['#name'])) {
                $attributes['class'][] = 'form-item-' . strtr($element['#name'], array(' ' => '-', '_' => '-', '[' => '-', ']' => ''));
            }
            // Add a class for disabled elements to facilitate cross-browser styling.
            if (!empty($element['#attributes']['disabled'])) {
                $attributes['class'][] = 'form-disabled';
            }
            $output = '<div' . self::attributes($attributes) . '>' . "\n";
               
            // If #title is not set, we don't display any label or required marker.
            if (!isset($element['#title'])) {
                $element['#title_display'] = 'none';
            }
            $prefix = isset($element['#field_prefix']) ? '<span class="field-prefix">' . $element['#field_prefix'] . '</span> ' : '';
            $suffix = isset($element['#field_suffix']) ? ' <span class="field-suffix">' . $element['#field_suffix'] . '</span>' : '';

            switch ($element['#title_display']) {
                case 'before':
                case 'invisible':
                $output .= ' ' . self::theme('form_element_label', $element);
                $output .= ' ' . $prefix . $element['#children'] . $suffix . "\n";
                break;

                case 'after':
                $output .= ' ' . $prefix . $element['#children'] . $suffix;
                $output .= ' ' . self::theme('form_element_label', $element) . "\n";
                break;

                case 'none':
                case 'attribute':
                // Output no label and no required marker, only the children.
                $output .= ' ' . $prefix . $element['#children'] . $suffix . "\n";
                break;
            }

            if (!empty($element['#description'])) {
                $output .= '<div class="description">' . $element['#description'] . "</div>\n";
            }

            $output .= "</div>\n";

            return $output;
        } // function

        /**
         * Select box theme 
         */
        function theme_select($variables) {
            $element = $variables;
            self::element_set_attributes($element, array('id', 'name', 'size'));
            self::element_set_class($element, array('form-select','form-field'));
            self::element_set_name($element); 
            
            return '<select' . self::attributes($element['#attributes']) . '>' . self::form_select_options($element) . '</select>';
        } // function     

        /**
         * Select box options renderer
         */
        function form_select_options($element, $choices = NULL) {
            if (!isset($choices)) {
                $choices = $element['#options'];
            }
           
            // array_key_exists() accommodates the rare event where $element['#value'] is NULL.
            // isset() fails in this situation.
            $value_valid = isset($element['#value']) || array_key_exists('#value', $element);
            $value_is_array = $value_valid && is_array($element['#value']);
            $options = '';
            foreach ($choices as $key => $choice) {
                if (is_array($choice)) {
                $options .= '<optgroup label="' . $key . '">';
                $options .= self::form_select_options($element, $choice);
                $options .= '</optgroup>';
                }
                elseif (is_object($choice)) {
                $options .= self::form_select_options($element, $choice->option);
                }
                else {
                $key = (string) $key;
                if ($value_valid && (!$value_is_array && (string) $element['#value'] === $key || ($value_is_array && in_array($key, $element['#value'])))) {
                    $selected = ' selected="selected"';
                }
                else {
                    $selected = '';
                }
                // @todo add check_plain equivalent back in for key and choice
                $options .= '<option value="' . $key . '"' . $selected . '>' . $choice . '</option>';
                }
            }
            return $options;
        } // function

        /**
        * Returns HTML for a select form element.
        *
        * It is possible to group options together; to do this, change the format of
        * $options to an associative array in which the keys are group labels, and the
        * values are associative arrays in the normal $options format.
        *
        * @param $variables
        *   An associative array containing:
        *   - element: An associative array containing the properties of the element.
        *     Properties used: #title, #value, #options, #description, #extra,
        *     #multiple, #required, #name, #attributes, #size.
        *
        * @ingroup themeable
        */
        function theme_multiselect($element) {
           
            self::element_set_attributes($element, array('id', 'name', 'size', 'multiple', 'default_value', 'required'));
            self::element_set_class($element, array('form-multiselect'));
            $options = $element['#options']; // All available options as defined by the element
            $items = $element['#default_value']; // All selected options are referred to as "items".
            $element['#field_name'] = $element['#name']; // CCK calls the #name "#field_name", so let's duplicate that..
            $required = $element['#required'];

            $widget = _multiselect_build_widget_code($options, $items, $element, $required);

            // Add a couple of things into the attributes.
            $element['#attributes']['class'][] = $widget['selfield'];
            $element['#attributes']['class'][] = "multiselect_sel";
            $element['#attributes']['id'] = $element['#field_name'];

            return $widget['prefix_pre'] . $widget['prefix_options'] . $widget['prefix_post'] . '<div class="form-item form-type-select"><select' . drupal_attributes($element['#attributes']) . '>' . _multiselect_html_for_box_options($widget['selected_options']) . '</select></div>' . "\n</div>\n";
        } // function

        /**
         * Textfield theme function
         * @param array $variables
         * @return string 
         */
        function theme_textfield($element) {
            $element;
            //$element['#attributes']['value'] = '';
            $element['#attributes']['type'] = 'text';
            self::element_set_attributes($element, array('id', 'name', 'value', 'size', 'maxlength'));
            self::element_set_class($element, array('form-text','form-field'));
            self::element_set_name($element);
            $output = '<input' . self::attributes($element['#attributes']) . ' />';
            return $output;
        } // function

        /**
         * Suggest theme function
         */
        function theme_suggest($element ) {
            $element['#attributes']['type'] = 'text';
            $element['#attributes']['data-group'] = 'text';
            $element['#attributes']['data-field'] = 'text';
            // turn the saved post id into something readable
            $element['#value'] = self::suggest_get_title($element['#value']);
            self::element_set_attributes($element, array('id', 'name', 'value', 'size', 'maxlength'));
            
            self::element_set_class($element, array('form-text','form-suggest', 'form-field'));
            self::element_set_name($element);

            $output = '<input' . self::attributes($element['#attributes']) . ' />';
            return $output . $extra; 
        } // function
        
        /**
         * Given an id show it along with the title in the autocmoplete textbox
         * @param string  $id Post ID
         * @param string $mode
         * @return string
         */ 
        function suggest_get_title($id, $mode='posts') {
            if ($mode == 'posts') {
                if (empty($id)) { return ""; }
                return get_the_title($id) . " [#". $id ."]";
            } else {
                if (empty($id)) { return ""; }
                return get_the_author_meta('user_nicename',$id) . " [*" . $id . "]"; 
            }
        } // function
        
        /**
         * Render a google map
         *
         * @since 0.0.1
         * @access public
         * @param array $args
         */ 
        function theme_map($element) {
            // build the html map element
            
            $output = '<div id="map-" class="gmap field" data-zoom="5" data-lat="" data-long="" data-latfield="' .  $element['#settings']['latfield'] . '" data-longfield="' .  $element['#settings']['longfield'] . '" style="height:200px;" ></div>';
            $output .= '<div class="mapgroup">' . $element['#children'] . '</div>';
            return $output;
        } // function

        /**
         * Attachment theme function
         * @param array $element The element to render
         * @return string
         */
        function theme_attachment($element) {

            $element['#attributes']['type'] = 'text';
            self::element_set_attributes($element, array('id', 'name', 'value', 'size', 'maxlength'));
            self::element_set_class($element, array('form-text','form-attachment','form-field'));
            self::element_set_name($element);

            $output = '<input' . self::attributes($element['#attributes']) . ' />';
            $output .= "<input class='attachment-upload button-secondary'  type='button' value='Upload'/>";

            return $output;
        } // end function
       
        /**
         * Date theme function
         * @param array $element The element to render
         * @return string 
         */
        function theme_date($element) {
            // Show other months    
            if (!isset($element['#settings']['showothermonths'])) { 
                $element['#attributes']['data-showothermonths'] = 'false';
            } else {
                 $element['#attributes']['data-showothermonths'] = $element['#settings']['showothermonths'];
            }
            // Date formats
            if (!isset($element['#settings']['dateformat'])) {
                $element['#attributes']['data-dateformat'] = 'mm/dd/yy';
            } else {
                $element['#attributes']['data-dateformat'] = $element['#settings']['dateformat'];
            }
            // How many months to show
            if (!isset($element['#settings']['numberofmonths'])) {
                $element['#attributes']['data-numberofmonths'] = '2';
            } else {
                $element['#attributes']['data-numberofmonths'] = $element['#settings']['numberofmonths'];
            }

            $element['#attributes']['type'] = 'text';
            self::element_set_attributes($element, array('id', 'name', 'value'));
            self::element_set_class($element, array('form-text','form-date','form-field'));
            self::element_set_name($element);

            $output = '<input' . self::attributes($element['#attributes']) . ' />';

            return $output ;
        } // function
                  
        /**
         * Runs as soon as a form is defined & sets the parents of all children
         * @param type $form
         * @return type 
         */
        function process($form) {
            foreach ($form as $key => $value) {
                // only do it for metaboxes
                if ($value['#type'] == 'metabox') {
                    // set the form ID
                    $form['#form_id'] = $key;
                    // set the child's parent
                    $form[$key]['#parents'][] = $key;
                }
            }
            return $form;
        } // function

    } // end class
 
} // end class exists
?>
