<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Sorkh theme configuration.
 *
 * @package    theme_sorkh
 * @copyright  2026 آموزشگاه SaaS سرخ
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$THEME->name = 'sorkh';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->editor_scss = ['editor'];
$THEME->usefallback = true;
$THEME->scss = function($theme) {
    return theme_sorkh_get_main_scss_content($theme);
};
$THEME->extrascsscallback = 'theme_sorkh_get_extra_scss';
$THEME->prescsscallback = 'theme_sorkh_get_pre_scss';
$THEME->precompiledcsscallback = 'theme_sorkh_get_precompiled_css';
$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->hidedefaultblocks = [
    'navigation' => ['course-view', 'incourse'],
];
