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
 * Aggregates the grades for submission and grades for assessments
 *
 * @package    mod_advwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');



$cmid       = required_param('cmid', PARAM_INT);            // course module
$confirm    = optional_param('confirm', false, PARAM_BOOL); // confirmation

// the params to be re-passed to view.php
$page       = optional_param('page', 0, PARAM_INT);
$sortby     = optional_param('sortby', 'lastname', PARAM_ALPHA);
$sorthow    = optional_param('sorthow', 'ASC', PARAM_ALPHA);

$cm         = get_coursemodule_from_id('advwork', $cmid, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$advwork   = $DB->get_record('advwork', array('id' => $cm->instance), '*', MUST_EXIST);
$advwork   = new advwork($advwork, $cm, $course);

$PAGE->set_url($advwork->aggregate_url(), compact('confirm', 'page', 'sortby', 'sorthow'));

require_login($course, false, $cm);
require_capability('mod/advwork:overridegrades', $PAGE->context);

// load and init the grading evaluator
$evaluator = $advwork->grading_evaluation_instance();
$settingsform = $evaluator->get_settings_form($PAGE->url);

if ($settingsdata = $settingsform->get_data()) {
    $advwork->aggregate_submission_grades();           // updates 'grade' in {advwork_submissions}
    $evaluator->update_grading_grades($settingsdata);   // updates 'gradinggrade' in {advwork_assessments}
    $advwork->aggregate_grading_grades();              // updates 'gradinggrade' in {advwork_aggregations}
    $advwork->aggregate_overall_grades();              // updates overall grades in {advwork_overall_grades}

    // send the session data to the BNS
    $courseid = $advwork->course->id;
    $courseteachersid = $advwork->get_course_teachers($courseid);
    $iscourseteacher = in_array($USER->id, $courseteachersid);

    if (isloggedin() && $iscourseteacher) {
        $advwork->send_session_data($courseid, $advwork, $courseteachersid, $cm);
        # Start : Giuseppe Bruno 
        $_SESSION['calculate_grades_button']=true; 
        # End : Giuseppe Bruno 
    }
}
redirect(new moodle_url($advwork->view_url(), compact('page', 'sortby', 'sorthow')));