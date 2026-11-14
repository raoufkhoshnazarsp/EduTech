<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Sorkh theme renderers.
 *
 * @package    theme_sorkh
 * @copyright  2026 آموزشگاه SaaS سرخ
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/theme/sorkh/lib_callbacks.php');

/**
 * Core renderer for theme_sorkh.
 */
class theme_sorkh_core_renderer extends theme_boost\output\core_renderer {

    /**
     * Add the dark-mode toggle to the navbar and the accessibility toolbar to the footer.
     *
     * @return string
     */
    public function standard_end_of_body_html() {
        $html = parent::standard_end_of_body_html();
        $html .= theme_sorkh_before_footer();
        return $html;
    }

    /**
     * Insert the dark-mode button into the user menu area.
     *
     * @return string
     */
    public function navbar_plugin_output() {
        $html = parent::navbar_plugin_output();
        $html .= html_writer::tag('button', '☾', [
            'type' => 'button',
            'class' => 'sorkh-dark-toggle',
            'data-sorkh' => 'dark',
            'title' => 'حالت شب',
            'aria-label' => 'تغییر حالت شب',
        ]);
        return $html;
    }
}
