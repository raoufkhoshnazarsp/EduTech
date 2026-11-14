<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Settings for theme_sorkh.
 *
 * @package    theme_sorkh
 * @copyright  2026 آموزشگاه SaaS سرخ
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettingsorkh', get_string('configtitle', 'theme_sorkh'));

    $page = new admin_settingpage('theme_sorkh_general', get_string('general', 'core'));

    $page->add(new admin_setting_configcolourpicker('theme_sorkh/brandcolor',
        get_string('brandcolor', 'theme_sorkh'),
        get_string('brandcolor_desc', 'theme_sorkh'), '#F52F53'));

    $page->add(new admin_setting_configcolourpicker('theme_sorkh/secondarycolor',
        get_string('secondarycolor', 'theme_sorkh'),
        get_string('secondarycolor_desc', 'theme_sorkh'), '#636363'));

    $page->add(new admin_setting_configcolourpicker('theme_sorkh/successcolor',
        get_string('successcolor', 'theme_sorkh'),
        get_string('successcolor_desc', 'theme_sorkh'), '#06B91F'));

    $page->add(new admin_setting_configcolourpicker('theme_sorkh/textcolor',
        get_string('textcolor', 'theme_sorkh'),
        get_string('textcolor_desc', 'theme_sorkh'), '#3d3d3d'));

    $page->add(new admin_setting_configstoredfile('theme_sorkh/backgroundimage',
        get_string('backgroundimage', 'theme_sorkh'),
        get_string('backgroundimage_desc', 'theme_sorkh'), 'backgroundimage',
        0, ['maxfiles' => 1, 'accepted_types' => ['image']]));

    $page->add(new admin_setting_configcheckbox('theme_sorkh/darkmode',
        get_string('darkmode', 'theme_sorkh'),
        get_string('darkmode_desc', 'theme_sorkh'), 1));

    $page->add(new admin_setting_configcheckbox('theme_sorkh/a11y',
        get_string('a11y', 'theme_sorkh'),
        get_string('a11y_desc', 'theme_sorkh'), 1));

    $settings->add($page);
}
