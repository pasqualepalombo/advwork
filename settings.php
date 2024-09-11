<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The advwork module configuration variables
 *
 * The values defined here are often used as defaults for all module instances.
 *
 * @package    mod_advwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/advwork/locallib.php');

    $grades = advwork::available_maxgrades_list();

    $settings->add(new admin_setting_configselect('advwork/grade', get_string('submissiongrade', 'advwork'),
                        get_string('configgrade', 'advwork'), 80, $grades));

    $settings->add(new admin_setting_configselect('advwork/gradinggrade', get_string('gradinggrade', 'advwork'),
                        get_string('configgradinggrade', 'advwork'), 20, $grades));

    $options = array();
    for ($i = 5; $i >= 0; $i--) {
        $options[$i] = $i;
    }
    $settings->add(new admin_setting_configselect('advwork/gradedecimals', get_string('gradedecimals', 'advwork'),
                        get_string('configgradedecimals', 'advwork'), 0, $options));

    if (isset($CFG->maxbytes)) {
        $maxbytes = get_config('advwork', 'maxbytes');
        $options = get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes);
        $settings->add(new admin_setting_configselect('advwork/maxbytes', get_string('maxbytes', 'advwork'),
                            get_string('configmaxbytes', 'advwork'), 0, $options));
    }

    $settings->add(new admin_setting_configselect('advwork/strategy', get_string('strategy', 'advwork'),
                        get_string('configstrategy', 'advwork'), 'accumulative', advwork::available_strategies_list()));

    $options = advwork::available_example_modes_list();
    $settings->add(new admin_setting_configselect('advwork/examplesmode', get_string('examplesmode', 'advwork'),
                        get_string('configexamplesmode', 'advwork'), advwork::EXAMPLES_VOLUNTARY, $options));

    // include the settings of allocation subplugins
    $allocators = core_component::get_plugin_list('advworkallocation');
    foreach ($allocators as $allocator => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('advworkallocationsetting'.$allocator,
                    get_string('allocation', 'advwork') . ' - ' . get_string('pluginname', 'advworkallocation_' . $allocator), ''));
            include($settingsfile);
        }
    }

    // include the settings of grading strategy subplugins
    $strategies = core_component::get_plugin_list('advworkform');
    foreach ($strategies as $strategy => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('advworkformsetting'.$strategy,
                    get_string('strategy', 'advwork') . ' - ' . get_string('pluginname', 'advworkform_' . $strategy), ''));
            include($settingsfile);
        }
    }

    // include the settings of grading evaluation subplugins
    $evaluations = core_component::get_plugin_list('advworkeval');
    foreach ($evaluations as $evaluation => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('advworkevalsetting'.$evaluation,
                    get_string('evaluation', 'advwork') . ' - ' . get_string('pluginname', 'advworkeval_' . $evaluation), ''));
            include($settingsfile);
        }
    }

}
