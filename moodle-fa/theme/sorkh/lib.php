<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Sorkh theme library.
 *
 * @package    theme_sorkh
 * @copyright  2026 آموزشگاه SaaS سرخ
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_sorkh_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_sorkh', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    }

    $scss .= file_get_contents($CFG->dirroot . '/theme/sorkh/scss/post.scss');

    return $scss;
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_sorkh_get_pre_scss($theme) {
    $scss = '';
    $configurable = [
        'brandcolor' => ['brand-primary'],
        'secondarycolor' => ['brand-secondary'],
        'textcolor' => ['body-color'],
        'successcolor' => ['success'],
    ];

    foreach ($configurable as $configkey => $targets) {
        $value = isset($theme->settings->{$configkey}) ? $theme->settings->{$configkey} : null;
        if (empty($value)) {
            continue;
        }
        array_map(function($target) use (&$scss, $value) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }, (array) $targets);
    }

    $scss .= file_get_contents(__DIR__ . '/scss/pre.scss');
    return $scss;
}

/**
 * Get extra SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_sorkh_get_extra_scss($theme) {
    $content = '';
    $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');
    if (!empty($imageurl)) {
        $content .= 'body { background-image: url("' . $imageurl . '"); background-size: cover; }';
    }
    $content .= file_get_contents(__DIR__ . '/scss/extra.scss');
    return $content;
}

/**
 * Get precompiled CSS.
 *
 * @return string
 */
function theme_sorkh_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/boost/style/moodle.css');
}
