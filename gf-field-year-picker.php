<?php
 /**
 * Plugin Name: Year Picker Field for Gravity Forms
 * Plugin URI: https://github.com/agileware/gf-field-year-picker
 * Description: Adds a custom Year Picker field type to Gravity Forms. Useful when you just want the user to select a year value from a range of values instead of selecting a date value.
 * Author: Agileware
 * Author URI: https://agileware.com.au
 * Version: 1.3
 * Text Domain: gf-field-year-picker
 * Requires Plugins: gravityforms
 * 
 * Copyright (c) Agileware Pty Ltd (email : support@agileware.com.au)
 *
 * Year Picker Field for Gravity Forms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Year Picker Field for Gravity Forms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */
 
if (!defined('ABSPATH')) exit;

class GF_Field_YearPicker extends GF_Field {

    public $type = 'yearpicker';

    public function get_form_editor_field_title() {
        return esc_attr__('Year Picker', 'gravityforms');
    }

    public function get_form_editor_button() {
        return [
            'group' => 'advanced_fields',
            'text'  => $this->get_form_editor_field_title(),
        ];
    }

    public function get_form_editor_field_settings() {
        return [
            'label_setting',
            'default_value_setting',
            'visibility_setting',
            'css_class_setting',
            'admin_label_setting',
            'placeholder_setting',
            'conditional_logic_field_setting',
            'min_year_offset_setting',
            'max_year_offset_setting',
            'rules_setting'
        ];
    }

    /**
     * Required Field & Range Validation
     */
    public function validate($value, $form) {
        $current_year = date('Y');
        $min_offset = !empty($this->minYearOffset) ? intval($this->minYearOffset) : 0;
        $max_offset = !empty($this->maxYearOffset) ? intval($this->maxYearOffset) : 0;

        // Maximum of 100 years
        $min_offset = min(max($min_offset, 0), 100);
        $max_offset = min(max($max_offset, 0), 100);

        $min_year = $current_year - $min_offset;
        $max_year = $current_year + $max_offset;

        if ($this->isRequired && empty($value)) {
            $this->failed_validation = true;
            $this->validation_message = __('This field is required. Please select a year.', 'gravityforms');
        }

        if (!empty($value) && ($value < $min_year || $value > $max_year)) {
            $this->failed_validation = true;
            $this->validation_message = sprintf(__('Please select a year between %d and %d.', 'gravityforms'), $min_year, $max_year);
        }
    }

    public function get_field_input($form, $value = '', $entry = null) {

        // Return an empty select field in the Gravity Form editor.
        if ($this->is_form_editor()) {
            return "<select name='input_{$this->id}' id='input_{$form['id']}_{$this->id}' class='gfield_select'></select>";
        }

        $form_id = $form['id'];
        $field_id = $this->id;
        $placeholder = !empty($this->placeholder) ? esc_html($this->placeholder) : __('Select a Year', 'gravityforms');
        
        // Calculate the Default Value logic
        $default_value = !empty($this->defaultValue) ? esc_html($this->defaultValue) : '';        
        $default_value = GFCommon::replace_variables($default_value, $form, $entry);

        // VALIDATION
        // Create a list of valid choice values first
        $valid_options = [];
        if (!empty($this->choices)) {
            foreach ($this->choices as $choice) {
                $valid_options[] = $choice['value'];
            }
        }

        // Ensure the Default Value is only used if it actually exists in the choices.
        // If the admin set a default that no longer exists, we reset it to empty.
        if (!in_array($default_value, $valid_options)) {
            $default_value = ''; 
        }

        // Determine the actual selected value.
        // If $value is not empty (meaning a submission occurred or we are editing an entry), use it.
        // Otherwise, fall back to the calculated $default_value.
        $selected_value = (string) $value !== '' ? $value : $default_value;

        // Start the dropdown
        $dropdown = "<select name='input_{$field_id}' id='input_{$form_id}_{$field_id}' class='gfield_select'>";
        
        // Placeholder is selected only if our resolved $selected_value is empty
        $dropdown .= "<option value='' " . ((string) $selected_value === '' ? 'selected' : '') . ">{$placeholder}</option>";
    
        // Loop through choices and apply selection
        if (!empty($this->choices)) {
            foreach ($this->choices as $choice) {
                // Compare the choice against $selected_value, NOT $default_value
                $is_selected = (string) $selected_value === (string) $choice['value'];
                $selected = $is_selected ? 'selected' : '';
                
                $dropdown .= "<option value='{$choice['value']}' {$selected}>{$choice['text']}</option>";
            }
        }
    
        $dropdown .= "</select>";
    
        return $dropdown;
    }
    
}

// Register the field with Gravity Forms.
GF_Fields::register(new GF_Field_YearPicker());

/**
 * Dynamically populate Year Picker dropdown before form renders.
 */
add_filter('gform_pre_render', function ($form) {
    foreach ($form['fields'] as &$field) {
        if ($field->type === 'yearpicker') {
            $min_offset = !empty($field->minYearOffset) ? intval($field->minYearOffset) : 0;
            $max_offset = !empty($field->maxYearOffset) ? intval($field->maxYearOffset) : 0;

            // Maximum of 100 years
            $min_offset = min(max($min_offset, 0), 100);
            $max_offset = min(max($max_offset, 0), 100);

            $current_year = date('Y');
            $min_year = $current_year - $min_offset;
            $max_year = $current_year + $max_offset;

            // Build the dropdown options dynamically
            $choices = [];

            for ($year = $max_year; $year >= $min_year; $year--) {
                $choices[] = ['text' => $year, 'value' => $year];
            }

            $field->choices = $choices;
        }
    }
    return $form;
});

/**
 * Add Year Picker settings to the Gravity Forms UI (General Section)
 */
add_action('gform_field_standard_settings', function ($position, $form_id) {
    if ($position === 25) {
        ?>
        <li class="min_year_offset_setting field_setting" style="display:none;">
            <label for="field_min_year_offset">Min Year Offset (0-100)</label>
            <input type="number" id="field_min_year_offset" class="fieldwidth-3"
                   min="0" max="100" value="0"
                   oninput="SetFieldProperty('minYearOffset', this.value);" />
        </li>
        <li class="max_year_offset_setting field_setting" style="display:none;">
            <label for="field_max_year_offset">Max Year Offset (0-100)</label>
            <input type="number" id="field_max_year_offset" class="fieldwidth-3"
                   min="0" max="100" value="0"
                   oninput="SetFieldProperty('maxYearOffset', this.value);" />
        </li>
        <?php
    }
}, 10, 2);
