<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Sorkh theme — child of Boost with Vazirmatn font and brand palette.
 *
 * @package    theme_sorkh
 * @copyright  2026 آموزشگاه SaaS سرخ
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'theme_sorkh';
$plugin->version   = 2026111400;
$plugin->release   = '1.0.0';
$plugin->requires  = 2026100500;
$plugin->maturity  = MATURITY_STABLE;
$plugin->dependencies = [
    'theme_boost' => 2026100500,
];
