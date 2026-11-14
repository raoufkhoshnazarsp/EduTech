<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Sorkh theme callbacks.
 *
 * @package    theme_sorkh
 * @copyright  2026 آموزشگاه SaaS سرخ
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Inject the dark-mode and accessibility controls into every page.
 *
 * @return string HTML to add before </body>.
 */
function theme_sorkh_before_footer() {
    $html = '';

    $html .= html_writer::start_div('sorkh-a11y', ['role' => 'toolbar', 'aria-label' => 'دسترسی‌پذیری']);
    $html .= html_writer::tag('button', 'A+', [
        'type' => 'button', 'data-sorkh' => 'fs-up', 'title' => 'بزرگ‌تر کردن فونت', 'aria-label' => 'بزرگ‌تر کردن فونت',
    ]);
    $html .= html_writer::tag('button', 'A-', [
        'type' => 'button', 'data-sorkh' => 'fs-down', 'title' => 'کوچک‌تر کردن فونت', 'aria-label' => 'کوچک‌تر کردن فونت',
    ]);
    $html .= html_writer::tag('button', '◐', [
        'type' => 'button', 'data-sorkh' => 'contrast', 'title' => 'کنتراست بالا', 'aria-label' => 'کنتراست بالا',
    ]);
    $html .= html_writer::tag('button', '↺', [
        'type' => 'button', 'data-sorkh' => 'reset', 'title' => 'بازنشانی', 'aria-label' => 'بازنشانی تنظیمات نمایش',
    ]);
    $html .= html_writer::end_div();

    $html .= html_writer::start_tag('script');
    $html .= <<<'JS'
(function () {
    var root = document.documentElement;
    var KEY = 'sorkh-prefs';

    function load() {
        try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; }
    }
    function save(p) {
        try { localStorage.setItem(KEY, JSON.stringify(p)); } catch (e) {}
    }
    function apply(p) {
        root.classList.remove('sorkh-dark', 'sorkh-contrast', 'sorkh-fs-1', 'sorkh-fs-2', 'sorkh-fs-3');
        if (p.dark) { root.classList.add('sorkh-dark'); }
        if (p.contrast) { root.classList.add('sorkh-contrast'); }
        if (p.fs) { root.classList.add('sorkh-fs-' + p.fs); }
        var btn = document.querySelector('[data-sorkh="dark"]');
        if (btn) { btn.textContent = p.dark ? '☀' : '☾'; }
    }

    var prefs = load();
    apply(prefs);

    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-sorkh]');
        if (!t) { return; }
        var action = t.getAttribute('data-sorkh');
        if (action === 'dark') {
            prefs.dark = !prefs.dark;
        } else if (action === 'contrast') {
            prefs.contrast = !prefs.contrast;
        } else if (action === 'fs-up') {
            prefs.fs = Math.min(3, (prefs.fs || 0) + 1);
        } else if (action === 'fs-down') {
            prefs.fs = Math.max(0, (prefs.fs || 0) - 1);
        } else if (action === 'reset') {
            prefs = {};
        }
        save(prefs);
        apply(prefs);
    });
})();
JS;
    $html .= html_writer::end_tag('script');

    return $html;
}
