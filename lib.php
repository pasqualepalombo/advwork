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
 * Library of advwork module functions needed by Moodle core and other subsystems
 *
 * All the functions neeeded by Moodle core, gradebook, file subsystem etc
 * are placed here.
 *
 * @package    mod_advwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

define('advwork_EVENT_TYPE_SUBMISSION_OPEN',   'opensubmission');
define('advwork_EVENT_TYPE_SUBMISSION_CLOSE',  'closesubmission');
define('advwork_EVENT_TYPE_ASSESSMENT_OPEN',   'openassessment');
define('advwork_EVENT_TYPE_ASSESSMENT_CLOSE',  'closeassessment');

////////////////////////////////////////////////////////////////////////////////
// Moodle core API                                                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the information if the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function advwork_supports($feature) {
    switch($feature) {
        case FEATURE_GRADE_HAS_GRADE:   return true;
        case FEATURE_GROUPS:            return true;
        case FEATURE_GROUPINGS:         return true;
        case FEATURE_MOD_INTRO:         return true;
        case FEATURE_BACKUP_MOODLE2:    return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_SHOW_DESCRIPTION:  return true;
        case FEATURE_PLAGIARISM:        return true;
        default:                        return null;
    }
}

/**
 * Saves a new instance of the advwork into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will save a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $advwork An object from the form in mod_form.php
 * @return int The id of the newly inserted advwork record
 */
function advwork_add_instance(stdclass $advwork) {
    global $CFG, $DB;
    
    require_once(__DIR__ . '/locallib.php');

    $advwork->phase                 = advwork::PHASE_SETUP;
    $advwork->timecreated           = time();
    $advwork->timemodified          = $advwork->timecreated;
    $advwork->useexamples           = (int)!empty($advwork->useexamples);
    $advwork->usepeerassessment     = 1;
    $advwork->useselfassessment     = (int)!empty($advwork->useselfassessment);
    $advwork->latesubmissions       = (int)!empty($advwork->latesubmissions);
    $advwork->phaseswitchassessment = (int)!empty($advwork->phaseswitchassessment);
    $advwork->evaluation            = 'best';

    if (isset($advwork->gradinggradepass)) {
        $advwork->gradinggradepass = (float)unformat_float($advwork->gradinggradepass);
    }

    if (isset($advwork->submissiongradepass)) {
        $advwork->submissiongradepass = (float)unformat_float($advwork->submissiongradepass);
    }

    if (isset($advwork->submissionfiletypes)) {
        $filetypesutil = new \core_form\filetypes_util();
        $submissionfiletypes = $filetypesutil->normalize_file_types($advwork->submissionfiletypes);
        $advwork->submissionfiletypes = implode(' ', $submissionfiletypes);
    }

    if (isset($advwork->overallfeedbackfiletypes)) {
        $filetypesutil = new \core_form\filetypes_util();
        $overallfeedbackfiletypes = $filetypesutil->normalize_file_types($advwork->overallfeedbackfiletypes);
        $advwork->overallfeedbackfiletypes = implode(' ', $overallfeedbackfiletypes);
    }

    // insert the new record so we get the id
    $advwork->id = $DB->insert_record('advwork', $advwork);

    // we need to use context now, so we need to make sure all needed info is already in db
    $cmid = $advwork->coursemodule;
    $DB->set_field('course_modules', 'instance', $advwork->id, array('id' => $cmid));
    $context = context_module::instance($cmid);

    // process the custom wysiwyg editors
    if ($draftitemid = $advwork->instructauthorseditor['itemid']) {
        $advwork->instructauthors = file_save_draft_area_files($draftitemid, $context->id, 'mod_advwork', 'instructauthors',
                0, advwork::instruction_editors_options($context), $advwork->instructauthorseditor['text']);
        $advwork->instructauthorsformat = $advwork->instructauthorseditor['format'];
    }

    if ($draftitemid = $advwork->instructreviewerseditor['itemid']) {
        $advwork->instructreviewers = file_save_draft_area_files($draftitemid, $context->id, 'mod_advwork', 'instructreviewers',
                0, advwork::instruction_editors_options($context), $advwork->instructreviewerseditor['text']);
        $advwork->instructreviewersformat = $advwork->instructreviewerseditor['format'];
    }

    if ($draftitemid = $advwork->conclusioneditor['itemid']) {
        $advwork->conclusion = file_save_draft_area_files($draftitemid, $context->id, 'mod_advwork', 'conclusion',
                0, advwork::instruction_editors_options($context), $advwork->conclusioneditor['text']);
        $advwork->conclusionformat = $advwork->conclusioneditor['format'];
    }

    // re-save the record with the replaced URLs in editor fields
    $DB->update_record('advwork', $advwork);

    // create gradebook items
    advwork_grade_item_update($advwork);
    advwork_grade_item_category_update($advwork);

    // create calendar events
    advwork_calendar_update($advwork, $advwork->coursemodule);
    if (!empty($advwork->completionexpected)) {
        \core_completion\api::update_completion_date_event($cmid, 'advwork', $advwork->id, $advwork->completionexpected);
    }

    return $advwork->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $advwork An object from the form in mod_form.php
 * @return bool success
 */
function advwork_update_instance(stdclass $advwork) {
    global $CFG, $DB;
    require_once(__DIR__ . '/locallib.php');

    $advwork->timemodified          = time();
    $advwork->id                    = $advwork->instance;
    $advwork->useexamples           = (int)!empty($advwork->useexamples);
    $advwork->usepeerassessment     = 1;
    $advwork->useselfassessment     = (int)!empty($advwork->useselfassessment);
    $advwork->latesubmissions       = (int)!empty($advwork->latesubmissions);
    $advwork->phaseswitchassessment = (int)!empty($advwork->phaseswitchassessment);

    if (isset($advwork->gradinggradepass)) {
        $advwork->gradinggradepass = (float)unformat_float($advwork->gradinggradepass);
    }

    if (isset($advwork->submissiongradepass)) {
        $advwork->submissiongradepass = (float)unformat_float($advwork->submissiongradepass);
    }

    if (isset($advwork->submissionfiletypes)) {
        $filetypesutil = new \core_form\filetypes_util();
        $submissionfiletypes = $filetypesutil->normalize_file_types($advwork->submissionfiletypes);
        $advwork->submissionfiletypes = implode(' ', $submissionfiletypes);
    }

    if (isset($advwork->overallfeedbackfiletypes)) {
        $filetypesutil = new \core_form\filetypes_util();
        $overallfeedbackfiletypes = $filetypesutil->normalize_file_types($advwork->overallfeedbackfiletypes);
        $advwork->overallfeedbackfiletypes = implode(' ', $overallfeedbackfiletypes);
    }

    // todo - if the grading strategy is being changed, we may want to replace all aggregated peer grades with nulls

    $DB->update_record('advwork', $advwork);
    $context = context_module::instance($advwork->coursemodule);

    // process the custom wysiwyg editors
    if ($draftitemid = $advwork->instructauthorseditor['itemid']) {
        $advwork->instructauthors = file_save_draft_area_files($draftitemid, $context->id, 'mod_advwork', 'instructauthors',
                0, advwork::instruction_editors_options($context), $advwork->instructauthorseditor['text']);
        $advwork->instructauthorsformat = $advwork->instructauthorseditor['format'];
    }

    if ($draftitemid = $advwork->instructreviewerseditor['itemid']) {
        $advwork->instructreviewers = file_save_draft_area_files($draftitemid, $context->id, 'mod_advwork', 'instructreviewers',
                0, advwork::instruction_editors_options($context), $advwork->instructreviewerseditor['text']);
        $advwork->instructreviewersformat = $advwork->instructreviewerseditor['format'];
    }

    if ($draftitemid = $advwork->conclusioneditor['itemid']) {
        $advwork->conclusion = file_save_draft_area_files($draftitemid, $context->id, 'mod_advwork', 'conclusion',
                0, advwork::instruction_editors_options($context), $advwork->conclusioneditor['text']);
        $advwork->conclusionformat = $advwork->conclusioneditor['format'];
    }

    // re-save the record with the replaced URLs in editor fields
    $DB->update_record('advwork', $advwork);

    // update gradebook items
    advwork_grade_item_update($advwork);
    advwork_grade_item_category_update($advwork);

    // update calendar events
    advwork_calendar_update($advwork, $advwork->coursemodule);
    $completionexpected = (!empty($advwork->completionexpected)) ? $advwork->completionexpected : null;
    \core_completion\api::update_completion_date_event($advwork->coursemodule, 'advwork', $advwork->id, $completionexpected);

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function advwork_delete_instance($id) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if (! $advwork = $DB->get_record('advwork', array('id' => $id))) {
        return false;
    }

    // delete all associated aggregations
    $DB->delete_records('advwork_aggregations', array('advworkid' => $advwork->id));

    // get the list of ids of all submissions
    $submissions = $DB->get_records('advwork_submissions', array('advworkid' => $advwork->id), '', 'id');

    // get the list of all allocated assessments
    $assessments = $DB->get_records_list('advwork_assessments', 'submissionid', array_keys($submissions), '', 'id');

    // delete the associated records from the advwork core tables
    $DB->delete_records_list('advwork_grades', 'assessmentid', array_keys($assessments));
    $DB->delete_records_list('advwork_assessments', 'id', array_keys($assessments));
    $DB->delete_records_list('advwork_submissions', 'id', array_keys($submissions));

    // call the static clean-up methods of all available subplugins
    $strategies = core_component::get_plugin_list('advworkform');
    foreach ($strategies as $strategy => $path) {
        require_once($path.'/lib.php');
        $classname = 'advwork_'.$strategy.'_strategy';
        call_user_func($classname.'::delete_instance', $advwork->id);
    }

    $allocators = core_component::get_plugin_list('advworkallocation');
    foreach ($allocators as $allocator => $path) {
        require_once($path.'/lib.php');
        $classname = 'advwork_'.$allocator.'_allocator';
        call_user_func($classname.'::delete_instance', $advwork->id);
    }

    $evaluators = core_component::get_plugin_list('advworkeval');
    foreach ($evaluators as $evaluator => $path) {
        require_once($path.'/lib.php');
        $classname = 'advwork_'.$evaluator.'_evaluation';
        call_user_func($classname.'::delete_instance', $advwork->id);
    }

    // delete the calendar events
    $events = $DB->get_records('event', array('modulename' => 'advwork', 'instance' => $advwork->id));
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    // finally remove the advwork record itself
    $DB->delete_records('advwork', array('id' => $advwork->id));

    // gradebook cleanup
    grade_update('mod/advwork', $advwork->course, 'mod', 'advwork', $advwork->id, 0, null, array('deleted' => true));
    grade_update('mod/advwork', $advwork->course, 'mod', 'advwork', $advwork->id, 1, null, array('deleted' => true));

    return true;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every advwork event in the site is checked, else
 * only advwork events belonging to the course specified are checked.
 *
 * @param  integer $courseid The Course ID.
 * @param int|stdClass $instance advwork module instance or ID.
 * @param int|stdClass $cm Course module object or ID.
 * @return bool Returns true if the calendar events were successfully updated.
 */
function advwork_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB;

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('advwork', array('id' => $instance), '*', MUST_EXIST);
        }
        if (isset($cm)) {
            if (!is_object($cm)) {
                $cm = (object)array('id' => $cm);
            }
        } else {
            $cm = get_coursemodule_from_instance('advwork', $instance->id);
        }
        advwork_calendar_update($instance, $cm->id);
        return true;
    }

    if ($courseid) {
        // Make sure that the course id is numeric.
        if (!is_numeric($courseid)) {
            return false;
        }
        if (!$advworks = $DB->get_records('advwork', array('course' => $courseid))) {
            return false;
        }
    } else {
        if (!$advworks = $DB->get_records('advwork')) {
            return false;
        }
    }
    foreach ($advworks as $advwork) {
        if (!$cm = get_coursemodule_from_instance('advwork', $advwork->id, $courseid, false)) {
            continue;
        }
        advwork_calendar_update($advwork, $cm->id);
    }
    return true;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function advwork_get_view_actions() {
    return array('view', 'view all', 'view submission', 'view example');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function advwork_get_post_actions() {
    return array('add', 'add assessment', 'add example', 'add submission',
                 'update', 'update assessment', 'update example', 'update submission');
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record.
 * @param stdClass $user The user record.
 * @param cm_info|stdClass $mod The course module info object or record.
 * @param stdClass $advwork The advwork instance record.
 * @return stdclass|null
 */
function advwork_user_outline($course, $user, $mod, $advwork) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    $grades = grade_get_grades($course->id, 'mod', 'advwork', $advwork->id, $user->id);

    $submissiongrade = null;
    $assessmentgrade = null;

    $info = '';
    $time = 0;

    if (!empty($grades->items[0]->grades)) {
        $submissiongrade = reset($grades->items[0]->grades);
        $info .= get_string('submissiongrade', 'advwork') . ': ' . $submissiongrade->str_long_grade . html_writer::empty_tag('br');
        $time = max($time, $submissiongrade->dategraded);
    }
    if (!empty($grades->items[1]->grades)) {
        $assessmentgrade = reset($grades->items[1]->grades);
        $info .= get_string('gradinggrade', 'advwork') . ': ' . $assessmentgrade->str_long_grade;
        $time = max($time, $assessmentgrade->dategraded);
    }

    if (!empty($info) and !empty($time)) {
        $return = new stdclass();
        $return->time = $time;
        $return->info = $info;
        return $return;
    }

    return null;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course The course record.
 * @param stdClass $user The user record.
 * @param cm_info|stdClass $mod The course module info object or record.
 * @param stdClass $advwork The advwork instance record.
 * @return string HTML
 */
function advwork_user_complete($course, $user, $mod, $advwork) {
    global $CFG, $DB, $OUTPUT;
    require_once(__DIR__.'/locallib.php');
    require_once($CFG->libdir.'/gradelib.php');

    $advwork   = new advwork($advwork, $mod, $course);
    $grades     = grade_get_grades($course->id, 'mod', 'advwork', $advwork->id, $user->id);

    if (!empty($grades->items[0]->grades)) {
        $submissiongrade = reset($grades->items[0]->grades);
        $info = get_string('submissiongrade', 'advwork') . ': ' . $submissiongrade->str_long_grade;
        echo html_writer::tag('li', $info, array('class'=>'submissiongrade'));
    }
    if (!empty($grades->items[1]->grades)) {
        $assessmentgrade = reset($grades->items[1]->grades);
        $info = get_string('gradinggrade', 'advwork') . ': ' . $assessmentgrade->str_long_grade;
        echo html_writer::tag('li', $info, array('class'=>'gradinggrade'));
    }

    if (has_capability('mod/advwork:viewallsubmissions', $advwork->context)) {
        $canviewsubmission = true;
        if (groups_get_activity_groupmode($advwork->cm) == SEPARATEGROUPS) {
            // user must have accessallgroups or share at least one group with the submission author
            if (!has_capability('moodle/site:accessallgroups', $advwork->context)) {
                $usersgroups = groups_get_activity_allowed_groups($advwork->cm);
                $authorsgroups = groups_get_all_groups($advwork->course->id, $user->id, $advwork->cm->groupingid, 'g.id');
                $sharedgroups = array_intersect_key($usersgroups, $authorsgroups);
                if (empty($sharedgroups)) {
                    $canviewsubmission = false;
                }
            }
        }
        if ($canviewsubmission and $submission = $advwork->get_submission_by_author($user->id)) {
            $title      = format_string($submission->title);
            $url        = $advwork->submission_url($submission->id);
            $link       = html_writer::link($url, $title);
            $info       = get_string('submission', 'advwork').': '.$link;
            echo html_writer::tag('li', $info, array('class'=>'submission'));
        }
    }

    if (has_capability('mod/advwork:viewallassessments', $advwork->context)) {
        if ($assessments = $advwork->get_assessments_by_reviewer($user->id)) {
            foreach ($assessments as $assessment) {
                $a = new stdclass();
                $a->submissionurl = $advwork->submission_url($assessment->submissionid)->out();
                $a->assessmenturl = $advwork->assess_url($assessment->id)->out();
                $a->submissiontitle = s($assessment->submissiontitle);
                echo html_writer::tag('li', get_string('assessmentofsubmission', 'advwork', $a));
            }
        }
    }
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in advwork activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @param stdClass $course
 * @param bool $viewfullnames
 * @param int $timestart
 * @return boolean
 */
function advwork_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    $authoramefields = get_all_user_name_fields(true, 'author', null, 'author');
    $reviewerfields = get_all_user_name_fields(true, 'reviewer', null, 'reviewer');

    $sql = "SELECT s.id AS submissionid, s.title AS submissiontitle, s.timemodified AS submissionmodified,
                   author.id AS authorid, $authoramefields, a.id AS assessmentid, a.timemodified AS assessmentmodified,
                   reviewer.id AS reviewerid, $reviewerfields, cm.id AS cmid
              FROM {advwork} w
        INNER JOIN {course_modules} cm ON cm.instance = w.id
        INNER JOIN {modules} md ON md.id = cm.module
        INNER JOIN {advwork_submissions} s ON s.advworkid = w.id
        INNER JOIN {user} author ON s.authorid = author.id
         LEFT JOIN {advwork_assessments} a ON a.submissionid = s.id
         LEFT JOIN {user} reviewer ON a.reviewerid = reviewer.id
             WHERE cm.course = ?
                   AND md.name = 'advwork'
                   AND s.example = 0
                   AND (s.timemodified > ? OR a.timemodified > ?)
          ORDER BY s.timemodified";

    $rs = $DB->get_recordset_sql($sql, array($course->id, $timestart, $timestart));

    $modinfo = get_fast_modinfo($course); // reference needed because we might load the groups

    $submissions = array(); // recent submissions indexed by submission id
    $assessments = array(); // recent assessments indexed by assessment id
    $users       = array();

    foreach ($rs as $activity) {
        if (!array_key_exists($activity->cmid, $modinfo->cms)) {
            // this should not happen but just in case
            continue;
        }

        $cm = $modinfo->cms[$activity->cmid];
        if (!$cm->uservisible) {
            continue;
        }

        // remember all user names we can use later
        if (empty($users[$activity->authorid])) {
            $u = new stdclass();
            $users[$activity->authorid] = username_load_fields_from_object($u, $activity, 'author');
        }
        if ($activity->reviewerid and empty($users[$activity->reviewerid])) {
            $u = new stdclass();
            $users[$activity->reviewerid] = username_load_fields_from_object($u, $activity, 'reviewer');
        }

        $context = context_module::instance($cm->id);
        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($activity->submissionmodified > $timestart and empty($submissions[$activity->submissionid])) {
            $s = new stdclass();
            $s->title = $activity->submissiontitle;
            $s->authorid = $activity->authorid;
            $s->timemodified = $activity->submissionmodified;
            $s->cmid = $activity->cmid;
            if ($activity->authorid == $USER->id || has_capability('mod/advwork:viewauthornames', $context)) {
                $s->authornamevisible = true;
            } else {
                $s->authornamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($s->authorid === $USER->id) {
                    // own submissions always visible
                    $submissions[$activity->submissionid] = $s;
                    break;
                }

                if (has_capability('mod/advwork:viewallsubmissions', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $authorsgroups = groups_get_all_groups($course->id, $s->authorid, $cm->groupingid);
                        if (is_array($authorsgroups)) {
                            $authorsgroups = array_keys($authorsgroups);
                            $intersect = array_intersect($authorsgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all submissions and shares a group with the author
                                $submissions[$activity->submissionid] = $s;
                                break;
                            }
                        }

                    } else {
                        // can see all submissions from all groups
                        $submissions[$activity->submissionid] = $s;
                    }
                }
            } while (0);
        }

        if ($activity->assessmentmodified > $timestart and empty($assessments[$activity->assessmentid])) {
            $a = new stdclass();
            $a->submissionid = $activity->submissionid;
            $a->submissiontitle = $activity->submissiontitle;
            $a->reviewerid = $activity->reviewerid;
            $a->timemodified = $activity->assessmentmodified;
            $a->cmid = $activity->cmid;
            if ($activity->reviewerid == $USER->id || has_capability('mod/advwork:viewreviewernames', $context)) {
                $a->reviewernamevisible = true;
            } else {
                $a->reviewernamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($a->reviewerid === $USER->id) {
                    // own assessments always visible
                    $assessments[$activity->assessmentid] = $a;
                    break;
                }

                if (has_capability('mod/advwork:viewallassessments', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $reviewersgroups = groups_get_all_groups($course->id, $a->reviewerid, $cm->groupingid);
                        if (is_array($reviewersgroups)) {
                            $reviewersgroups = array_keys($reviewersgroups);
                            $intersect = array_intersect($reviewersgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all assessments and shares a group with the reviewer
                                $assessments[$activity->assessmentid] = $a;
                                break;
                            }
                        }

                    } else {
                        // can see all assessments from all groups
                        $assessments[$activity->assessmentid] = $a;
                    }
                }
            } while (0);
        }
    }
    $rs->close();

    $shown = false;

    if (!empty($submissions)) {
        $shown = true;
        echo $OUTPUT->heading(get_string('recentsubmissions', 'advwork'), 3);
        foreach ($submissions as $id => $submission) {
            $link = new moodle_url('/mod/advwork/submission.php', array('id'=>$id, 'cmid'=>$submission->cmid));
            if ($submission->authornamevisible) {
                $author = $users[$submission->authorid];
            } else {
                $author = null;
            }
            print_recent_activity_note($submission->timemodified, $author, $submission->title, $link->out(), false, $viewfullnames);
        }
    }

    if (!empty($assessments)) {
        $shown = true;
        echo $OUTPUT->heading(get_string('recentassessments', 'advwork'), 3);
        core_collator::asort_objects_by_property($assessments, 'timemodified');
        foreach ($assessments as $id => $assessment) {
            $link = new moodle_url('/mod/advwork/assessment.php', array('asid' => $id));
            if ($assessment->reviewernamevisible) {
                $reviewer = $users[$assessment->reviewerid];
            } else {
                $reviewer = null;
            }
            print_recent_activity_note($assessment->timemodified, $reviewer, $assessment->submissiontitle, $link->out(), false, $viewfullnames);
        }
    }

    if ($shown) {
        return true;
    }

    return false;
}

/**
 * Returns all activity in course advworks since a given time
 *
 * @param array $activities sequentially indexed array of objects
 * @param int $index
 * @param int $timestart
 * @param int $courseid
 * @param int $cmid
 * @param int $userid defaults to 0
 * @param int $groupid defaults to 0
 * @return void adds items into $activities and increases $index
 */
function advwork_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id'=>$courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];

    $params = array();
    if ($userid) {
        $userselect = "AND (author.id = :authorid OR reviewer.id = :reviewerid)";
        $params['authorid'] = $userid;
        $params['reviewerid'] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND (authorgroupmembership.groupid = :authorgroupid OR reviewergroupmembership.groupid = :reviewergroupid)";
        $groupjoin   = "LEFT JOIN {groups_members} authorgroupmembership ON authorgroupmembership.userid = author.id
                        LEFT JOIN {groups_members} reviewergroupmembership ON reviewergroupmembership.userid = reviewer.id";
        $params['authorgroupid'] = $groupid;
        $params['reviewergroupid'] = $groupid;
    } else {
        $groupselect = "";
        $groupjoin   = "";
    }

    $params['cminstance'] = $cm->instance;
    $params['submissionmodified'] = $timestart;
    $params['assessmentmodified'] = $timestart;

    $authornamefields = get_all_user_name_fields(true, 'author', null, 'author');
    $reviewerfields = get_all_user_name_fields(true, 'reviewer', null, 'reviewer');

    $sql = "SELECT s.id AS submissionid, s.title AS submissiontitle, s.timemodified AS submissionmodified,
                   author.id AS authorid, $authornamefields, author.picture AS authorpicture, author.imagealt AS authorimagealt,
                   author.email AS authoremail, a.id AS assessmentid, a.timemodified AS assessmentmodified,
                   reviewer.id AS reviewerid, $reviewerfields, reviewer.picture AS reviewerpicture,
                   reviewer.imagealt AS reviewerimagealt, reviewer.email AS revieweremail
              FROM {advwork_submissions} s
        INNER JOIN {advwork} w ON s.advworkid = w.id
        INNER JOIN {user} author ON s.authorid = author.id
         LEFT JOIN {advwork_assessments} a ON a.submissionid = s.id
         LEFT JOIN {user} reviewer ON a.reviewerid = reviewer.id
        $groupjoin
             WHERE w.id = :cminstance
                   AND s.example = 0
                   $userselect $groupselect
                   AND (s.timemodified > :submissionmodified OR a.timemodified > :assessmentmodified)
          ORDER BY s.timemodified ASC, a.timemodified ASC";

    $rs = $DB->get_recordset_sql($sql, $params);

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $context         = context_module::instance($cm->id);
    $grader          = has_capability('moodle/grade:viewall', $context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
    $viewauthors     = has_capability('mod/advwork:viewauthornames', $context);
    $viewreviewers   = has_capability('mod/advwork:viewreviewernames', $context);

    $submissions = array(); // recent submissions indexed by submission id
    $assessments = array(); // recent assessments indexed by assessment id
    $users       = array();

    foreach ($rs as $activity) {

        // remember all user names we can use later
        if (empty($users[$activity->authorid])) {
            $u = new stdclass();
            $additionalfields = explode(',', user_picture::fields());
            $u = username_load_fields_from_object($u, $activity, 'author', $additionalfields);
            $users[$activity->authorid] = $u;
        }
        if ($activity->reviewerid and empty($users[$activity->reviewerid])) {
            $u = new stdclass();
            $additionalfields = explode(',', user_picture::fields());
            $u = username_load_fields_from_object($u, $activity, 'reviewer', $additionalfields);
            $users[$activity->reviewerid] = $u;
        }

        if ($activity->submissionmodified > $timestart and empty($submissions[$activity->submissionid])) {
            $s = new stdclass();
            $s->id = $activity->submissionid;
            $s->title = $activity->submissiontitle;
            $s->authorid = $activity->authorid;
            $s->timemodified = $activity->submissionmodified;
            if ($activity->authorid == $USER->id || has_capability('mod/advwork:viewauthornames', $context)) {
                $s->authornamevisible = true;
            } else {
                $s->authornamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($s->authorid === $USER->id) {
                    // own submissions always visible
                    $submissions[$activity->submissionid] = $s;
                    break;
                }

                if (has_capability('mod/advwork:viewallsubmissions', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $authorsgroups = groups_get_all_groups($course->id, $s->authorid, $cm->groupingid);
                        if (is_array($authorsgroups)) {
                            $authorsgroups = array_keys($authorsgroups);
                            $intersect = array_intersect($authorsgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all submissions and shares a group with the author
                                $submissions[$activity->submissionid] = $s;
                                break;
                            }
                        }

                    } else {
                        // can see all submissions from all groups
                        $submissions[$activity->submissionid] = $s;
                    }
                }
            } while (0);
        }

        if ($activity->assessmentmodified > $timestart and empty($assessments[$activity->assessmentid])) {
            $a = new stdclass();
            $a->id = $activity->assessmentid;
            $a->submissionid = $activity->submissionid;
            $a->submissiontitle = $activity->submissiontitle;
            $a->reviewerid = $activity->reviewerid;
            $a->timemodified = $activity->assessmentmodified;
            if ($activity->reviewerid == $USER->id || has_capability('mod/advwork:viewreviewernames', $context)) {
                $a->reviewernamevisible = true;
            } else {
                $a->reviewernamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($a->reviewerid === $USER->id) {
                    // own assessments always visible
                    $assessments[$activity->assessmentid] = $a;
                    break;
                }

                if (has_capability('mod/advwork:viewallassessments', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $reviewersgroups = groups_get_all_groups($course->id, $a->reviewerid, $cm->groupingid);
                        if (is_array($reviewersgroups)) {
                            $reviewersgroups = array_keys($reviewersgroups);
                            $intersect = array_intersect($reviewersgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all assessments and shares a group with the reviewer
                                $assessments[$activity->assessmentid] = $a;
                                break;
                            }
                        }

                    } else {
                        // can see all assessments from all groups
                        $assessments[$activity->assessmentid] = $a;
                    }
                }
            } while (0);
        }
    }
    $rs->close();

    $advworkname = format_string($cm->name, true);

    if ($grader) {
        require_once($CFG->libdir.'/gradelib.php');
        $grades = grade_get_grades($courseid, 'mod', 'advwork', $cm->instance, array_keys($users));
    }

    foreach ($submissions as $submission) {
        $tmpactivity                = new stdclass();
        $tmpactivity->type          = 'advwork';
        $tmpactivity->cmid          = $cm->id;
        $tmpactivity->name          = $advworkname;
        $tmpactivity->sectionnum    = $cm->sectionnum;
        $tmpactivity->timestamp     = $submission->timemodified;
        $tmpactivity->subtype       = 'submission';
        $tmpactivity->content       = $submission;
        if ($grader) {
            $tmpactivity->grade     = $grades->items[0]->grades[$submission->authorid]->str_long_grade;
        }
        if ($submission->authornamevisible and !empty($users[$submission->authorid])) {
            $tmpactivity->user      = $users[$submission->authorid];
        }
        $activities[$index++]       = $tmpactivity;
    }

    foreach ($assessments as $assessment) {
        $tmpactivity                = new stdclass();
        $tmpactivity->type          = 'advwork';
        $tmpactivity->cmid          = $cm->id;
        $tmpactivity->name          = $advworkname;
        $tmpactivity->sectionnum    = $cm->sectionnum;
        $tmpactivity->timestamp     = $assessment->timemodified;
        $tmpactivity->subtype       = 'assessment';
        $tmpactivity->content       = $assessment;
        if ($grader) {
            $tmpactivity->grade     = $grades->items[1]->grades[$assessment->reviewerid]->str_long_grade;
        }
        if ($assessment->reviewernamevisible and !empty($users[$assessment->reviewerid])) {
            $tmpactivity->user      = $users[$assessment->reviewerid];
        }
        $activities[$index++]       = $tmpactivity;
    }
}

/**
 * Print single activity item prepared by {@see advwork_get_recent_mod_activity()}
 */
function advwork_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;

    if (!empty($activity->user)) {
        echo html_writer::tag('div', $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid)),
                array('style' => 'float: left; padding: 7px;'));
    }

    if ($activity->subtype == 'submission') {
        echo html_writer::start_tag('div', array('class'=>'submission', 'style'=>'padding: 7px; float:left;'));

        if ($detail) {
            echo html_writer::start_tag('h4', array('class'=>'advwork'));
            $url = new moodle_url('/mod/advwork/view.php', array('id'=>$activity->cmid));
            $name = s($activity->name);
            echo $OUTPUT->image_icon('icon', $name, $activity->type);
            echo ' ' . $modnames[$activity->type];
            echo html_writer::link($url, $name, array('class'=>'name', 'style'=>'margin-left: 5px'));
            echo html_writer::end_tag('h4');
        }

        echo html_writer::start_tag('div', array('class'=>'title'));
        $url = new moodle_url('/mod/advwork/submission.php', array('cmid'=>$activity->cmid, 'id'=>$activity->content->id));
        $name = s($activity->content->title);
        echo html_writer::tag('strong', html_writer::link($url, $name));
        echo html_writer::end_tag('div');

        if (!empty($activity->user)) {
            echo html_writer::start_tag('div', array('class'=>'user'));
            $url = new moodle_url('/user/view.php', array('id'=>$activity->user->id, 'course'=>$courseid));
            $name = fullname($activity->user);
            $link = html_writer::link($url, $name);
            echo get_string('submissionby', 'advwork', $link);
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        } else {
            echo html_writer::start_tag('div', array('class'=>'anonymous'));
            echo get_string('submission', 'advwork');
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        }

        echo html_writer::end_tag('div');
    }

    if ($activity->subtype == 'assessment') {
        echo html_writer::start_tag('div', array('class'=>'assessment', 'style'=>'padding: 7px; float:left;'));

        if ($detail) {
            echo html_writer::start_tag('h4', array('class'=>'advwork'));
            $url = new moodle_url('/mod/advwork/view.php', array('id'=>$activity->cmid));
            $name = s($activity->name);
            echo $OUTPUT->image_icon('icon', $name, $activity->type);
            echo ' ' . $modnames[$activity->type];
            echo html_writer::link($url, $name, array('class'=>'name', 'style'=>'margin-left: 5px'));
            echo html_writer::end_tag('h4');
        }

        echo html_writer::start_tag('div', array('class'=>'title'));
        $url = new moodle_url('/mod/advwork/assessment.php', array('asid'=>$activity->content->id));
        $name = s($activity->content->submissiontitle);
        echo html_writer::tag('em', html_writer::link($url, $name));
        echo html_writer::end_tag('div');

        if (!empty($activity->user)) {
            echo html_writer::start_tag('div', array('class'=>'user'));
            $url = new moodle_url('/user/view.php', array('id'=>$activity->user->id, 'course'=>$courseid));
            $name = fullname($activity->user);
            $link = html_writer::link($url, $name);
            echo get_string('assessmentbyfullname', 'advwork', $link);
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        } else {
            echo html_writer::start_tag('div', array('class'=>'anonymous'));
            echo get_string('assessment', 'advwork');
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        }

        echo html_writer::end_tag('div');
    }

    echo html_writer::empty_tag('br', array('style'=>'clear:both'));
}

/**
 * Regular jobs to execute via cron
 *
 * @return boolean true on success, false otherwise
 */
function advwork_cron() {
    global $CFG, $DB;

    $now = time();

    mtrace(' processing advwork subplugins ...');
    cron_execute_plugin_type('advworkallocation', 'advwork allocation methods');

    // now when the scheduled allocator had a chance to do its job, check if there
    // are some advworks to switch into the assessment phase
    $advworks = $DB->get_records_select("advwork",
        "phase = 20 AND phaseswitchassessment = 1 AND submissionend > 0 AND submissionend < ?", array($now));

    if (!empty($advworks)) {
        mtrace('Processing automatic assessment phase switch in '.count($advworks).' advwork(s) ... ', '');
        require_once($CFG->dirroot.'/mod/advwork/locallib.php');
        foreach ($advworks as $advwork) {
            $cm = get_coursemodule_from_instance('advwork', $advwork->id, $advwork->course, false, MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $advwork = new advwork($advwork, $cm, $course);
            $advwork->switch_phase(advwork::PHASE_ASSESSMENT);

            $params = array(
                'objectid' => $advwork->id,
                'context' => $advwork->context,
                'courseid' => $advwork->course->id,
                'other' => array(
                    'advworkphase' => $advwork->phase
                )
            );
            $event = \mod_advwork\event\phase_switched::create($params);
            $event->trigger();

            // disable the automatic switching now so that it is not executed again by accident
            // if the teacher changes the phase back to the submission one
            $DB->set_field('advwork', 'phaseswitchassessment', 0, array('id' => $advwork->id));

            // todo inform the teachers
        }
        mtrace('done');
    }

    return true;
}

/**
 * Is a given scale used by the instance of advwork?
 *
 * The function asks all installed grading strategy subplugins. The advwork
 * core itself does not use scales. Both grade for submission and grade for
 * assessments do not use scales.
 *
 * @param int $advworkid id of advwork instance
 * @param int $scaleid id of the scale to check
 * @return bool
 */
function advwork_scale_used($advworkid, $scaleid) {
    global $CFG; // other files included from here

    $strategies = core_component::get_plugin_list('advworkform');
    foreach ($strategies as $strategy => $strategypath) {
        $strategylib = $strategypath . '/lib.php';
        if (is_readable($strategylib)) {
            require_once($strategylib);
        } else {
            throw new coding_exception('the grading forms subplugin must contain library ' . $strategylib);
        }
        $classname = 'advwork_' . $strategy . '_strategy';
        if (method_exists($classname, 'scale_used')) {
            if (call_user_func_array(array($classname, 'scale_used'), array($scaleid, $advworkid))) {
                // no need to include any other files - scale is used
                return true;
            }
        }
    }

    return false;
}

/**
 * Is a given scale used by any instance of advwork?
 *
 * The function asks all installed grading strategy subplugins. The advwork
 * core itself does not use scales. Both grade for submission and grade for
 * assessments do not use scales.
 *
 * @param int $scaleid id of the scale to check
 * @return bool
 */
function advwork_scale_used_anywhere($scaleid) {
    global $CFG; // other files included from here

    $strategies = core_component::get_plugin_list('advworkform');
    foreach ($strategies as $strategy => $strategypath) {
        $strategylib = $strategypath . '/lib.php';
        if (is_readable($strategylib)) {
            require_once($strategylib);
        } else {
            throw new coding_exception('the grading forms subplugin must contain library ' . $strategylib);
        }
        $classname = 'advwork_' . $strategy . '_strategy';
        if (method_exists($classname, 'scale_used')) {
            if (call_user_func(array($classname, 'scale_used'), $scaleid)) {
                // no need to include any other files - scale is used
                return true;
            }
        }
    }

    return false;
}

/**
 * Returns all other caps used in the module
 *
 * @return array
 */
function advwork_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

////////////////////////////////////////////////////////////////////////////////
// Gradebook API                                                              //
////////////////////////////////////////////////////////////////////////////////

/**
 * Creates or updates grade items for the give advwork instance
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php. Also used by
 * {@link advwork_update_grades()}.
 *
 * @param stdClass $advwork instance object with extra cmidnumber property
 * @param stdClass $submissiongrades data for the first grade item
 * @param stdClass $assessmentgrades data for the second grade item
 * @return void
 */
function advwork_grade_item_update(stdclass $advwork, $submissiongrades=null, $assessmentgrades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $a = new stdclass();
    $a->advworkname = clean_param($advwork->name, PARAM_NOTAGS);

    $item = array();
    $item['itemname'] = get_string('gradeitemsubmission', 'advwork', $a);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = $advwork->grade;
    $item['grademin']  = 0;
    grade_update('mod/advwork', $advwork->course, 'mod', 'advwork', $advwork->id, 0, $submissiongrades , $item);

    $item = array();
    $item['itemname'] = get_string('gradeitemassessment', 'advwork', $a);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = $advwork->gradinggrade;
    $item['grademin']  = 0;
    grade_update('mod/advwork', $advwork->course, 'mod', 'advwork', $advwork->id, 1, $assessmentgrades, $item);
}

/**
 * Update advwork grades in the gradebook
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @category grade
 * @param stdClass $advwork instance object with extra cmidnumber and modname property
 * @param int $userid        update grade of specific user only, 0 means all participants
 * @return void
 */
function advwork_update_grades(stdclass $advwork, $userid=0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    $whereuser = $userid ? ' AND authorid = :userid' : '';
    $params = array('advworkid' => $advwork->id, 'userid' => $userid);
    $sql = 'SELECT authorid, grade, gradeover, gradeoverby, feedbackauthor, feedbackauthorformat, timemodified, timegraded
              FROM {advwork_submissions}
             WHERE advworkid = :advworkid AND example=0' . $whereuser;
    $records = $DB->get_records_sql($sql, $params);
    $submissiongrades = array();
    foreach ($records as $record) {
        $grade = new stdclass();
        $grade->userid = $record->authorid;
        if (!is_null($record->gradeover)) {
            $grade->rawgrade = grade_floatval($advwork->grade * $record->gradeover / 100);
            $grade->usermodified = $record->gradeoverby;
        } else {
            $grade->rawgrade = grade_floatval($advwork->grade * $record->grade / 100);
        }
        $grade->feedback = $record->feedbackauthor;
        $grade->feedbackformat = $record->feedbackauthorformat;
        $grade->datesubmitted = $record->timemodified;
        $grade->dategraded = $record->timegraded;
        $submissiongrades[$record->authorid] = $grade;
    }

    $whereuser = $userid ? ' AND userid = :userid' : '';
    $params = array('advworkid' => $advwork->id, 'userid' => $userid);
    $sql = 'SELECT userid, gradinggrade, timegraded
              FROM {advwork_aggregations}
             WHERE advworkid = :advworkid' . $whereuser;
    $records = $DB->get_records_sql($sql, $params);
    $assessmentgrades = array();
    foreach ($records as $record) {
        $grade = new stdclass();
        $grade->userid = $record->userid;
        $grade->rawgrade = grade_floatval($advwork->gradinggrade * $record->gradinggrade / 100);
        $grade->dategraded = $record->timegraded;
        $assessmentgrades[$record->userid] = $grade;
    }

    advwork_grade_item_update($advwork, $submissiongrades, $assessmentgrades);
}

/**
 * Update the grade items categories if they are changed via mod_form.php
 *
 * We must do it manually here in the advwork module because modedit supports only
 * single grade item while we use two.
 *
 * @param stdClass $advwork An object from the form in mod_form.php
 */
function advwork_grade_item_category_update($advwork) {

    $gradeitems = grade_item::fetch_all(array(
        'itemtype'      => 'mod',
        'itemmodule'    => 'advwork',
        'iteminstance'  => $advwork->id,
        'courseid'      => $advwork->course));

    if (!empty($gradeitems)) {
        foreach ($gradeitems as $gradeitem) {
            if ($gradeitem->itemnumber == 0) {
                if (isset($advwork->submissiongradepass) &&
                        $gradeitem->gradepass != $advwork->submissiongradepass) {
                    $gradeitem->gradepass = $advwork->submissiongradepass;
                    $gradeitem->update();
                }
                if ($gradeitem->categoryid != $advwork->gradecategory) {
                    $gradeitem->set_parent($advwork->gradecategory);
                }
            } else if ($gradeitem->itemnumber == 1) {
                if (isset($advwork->gradinggradepass) &&
                        $gradeitem->gradepass != $advwork->gradinggradepass) {
                    $gradeitem->gradepass = $advwork->gradinggradepass;
                    $gradeitem->update();
                }
                if ($gradeitem->categoryid != $advwork->gradinggradecategory) {
                    $gradeitem->set_parent($advwork->gradinggradecategory);
                }
            }
        }
    }
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area advwork_intro for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @package  mod_advwork
 * @category files
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function advwork_get_file_areas($course, $cm, $context) {
    $areas = array();
    $areas['instructauthors']          = get_string('areainstructauthors', 'advwork');
    $areas['instructreviewers']        = get_string('areainstructreviewers', 'advwork');
    $areas['submission_content']       = get_string('areasubmissioncontent', 'advwork');
    $areas['submission_attachment']    = get_string('areasubmissionattachment', 'advwork');
    $areas['conclusion']               = get_string('areaconclusion', 'advwork');
    $areas['overallfeedback_content']  = get_string('areaoverallfeedbackcontent', 'advwork');
    $areas['overallfeedback_attachment'] = get_string('areaoverallfeedbackattachment', 'advwork');

    return $areas;
}

/**
 * Serves the files from the advwork file areas
 *
 * Apart from module intro (handled by pluginfile.php automatically), advwork files may be
 * media inserted into submission content (like images) and submission attachments. For these two,
 * the fileareas submission_content and submission_attachment are used.
 * Besides that, areas instructauthors, instructreviewers and conclusion contain the media
 * embedded using the mod_form.php.
 *
 * @package  mod_advwork
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the advwork's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function advwork_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea === 'instructauthors' or $filearea === 'instructreviewers' or $filearea === 'conclusion') {
        // The $args are supposed to contain just the path, not the item id.
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_advwork/$filearea/0/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            send_file_not_found();
        }
        send_stored_file($file, null, 0, $forcedownload, $options);

    } else if ($filearea === 'submission_content' or $filearea === 'submission_attachment') {
        $itemid = (int)array_shift($args);
        if (!$advwork = $DB->get_record('advwork', array('id' => $cm->instance))) {
            return false;
        }
        if (!$submission = $DB->get_record('advwork_submissions', array('id' => $itemid, 'advworkid' => $advwork->id))) {
            return false;
        }

        // make sure the user is allowed to see the file
        if (empty($submission->example)) {
            if ($USER->id != $submission->authorid) {
                if ($submission->published == 1 and $advwork->phase == 50
                        and has_capability('mod/advwork:viewpublishedsubmissions', $context)) {
                    // Published submission, we can go (advwork does not take the group mode
                    // into account in this case yet).
                } else if (!$DB->record_exists('advwork_assessments', array('submissionid' => $submission->id, 'reviewerid' => $USER->id))) {
                    if (!has_capability('mod/advwork:viewallsubmissions', $context)) {
                        send_file_not_found();
                    } else {
                        $gmode = groups_get_activity_groupmode($cm, $course);
                        if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                            // check there is at least one common group with both the $USER
                            // and the submission author
                            $sql = "SELECT 'x'
                                      FROM {advwork_submissions} s
                                      JOIN {user} a ON (a.id = s.authorid)
                                      JOIN {groups_members} agm ON (a.id = agm.userid)
                                      JOIN {user} u ON (u.id = ?)
                                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                                     WHERE s.example = 0 AND s.advworkid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
                            $params = array($USER->id, $advwork->id, $submission->id);
                            if (!$DB->record_exists_sql($sql, $params)) {
                                send_file_not_found();
                            }
                        }
                    }
                }
            }
        }

        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_advwork/$filearea/$itemid/$relativepath";
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        // finally send the file
        // these files are uploaded by students - forcing download for security reasons
        send_stored_file($file, 0, 0, true, $options);

    } else if ($filearea === 'overallfeedback_content' or $filearea === 'overallfeedback_attachment') {
        $itemid = (int)array_shift($args);
        if (!$advwork = $DB->get_record('advwork', array('id' => $cm->instance))) {
            return false;
        }
        if (!$assessment = $DB->get_record('advwork_assessments', array('id' => $itemid))) {
            return false;
        }
        if (!$submission = $DB->get_record('advwork_submissions', array('id' => $assessment->submissionid, 'advworkid' => $advwork->id))) {
            return false;
        }

        if ($USER->id == $assessment->reviewerid) {
            // Reviewers can always see their own files.
        } else if ($USER->id == $submission->authorid and $advwork->phase == 50) {
            // Authors can see the feedback once the advwork is closed.
        } else if (!empty($submission->example) and $assessment->weight == 1) {
            // Reference assessments of example submissions can be displayed.
        } else if (!has_capability('mod/advwork:viewallassessments', $context)) {
            send_file_not_found();
        } else {
            $gmode = groups_get_activity_groupmode($cm, $course);
            if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                // Check there is at least one common group with both the $USER
                // and the submission author.
                $sql = "SELECT 'x'
                          FROM {advwork_submissions} s
                          JOIN {user} a ON (a.id = s.authorid)
                          JOIN {groups_members} agm ON (a.id = agm.userid)
                          JOIN {user} u ON (u.id = ?)
                          JOIN {groups_members} ugm ON (u.id = ugm.userid)
                         WHERE s.example = 0 AND s.advworkid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
                $params = array($USER->id, $advwork->id, $submission->id);
                if (!$DB->record_exists_sql($sql, $params)) {
                    send_file_not_found();
                }
            }
        }

        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_advwork/$filearea/$itemid/$relativepath";
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        // finally send the file
        // these files are uploaded by students - forcing download for security reasons
        send_stored_file($file, 0, 0, true, $options);
    }

    return false;
}

/**
 * File browsing support for advwork file areas
 *
 * @package  mod_advwork
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function advwork_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    /** @var array internal cache for author names */
    static $submissionauthors = array();

    $fs = get_file_storage();

    if ($filearea === 'submission_content' or $filearea === 'submission_attachment') {

        if (!has_capability('mod/advwork:viewallsubmissions', $context)) {
            return null;
        }

        if (is_null($itemid)) {
            // no itemid (submissionid) passed, display the list of all submissions
            require_once($CFG->dirroot . '/mod/advwork/fileinfolib.php');
            return new advwork_file_info_submissions_container($browser, $course, $cm, $context, $areas, $filearea);
        }

        // make sure the user can see the particular submission in separate groups mode
        $gmode = groups_get_activity_groupmode($cm, $course);

        if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            // check there is at least one common group with both the $USER
            // and the submission author (this is not expected to be a frequent
            // usecase so we can live with pretty ineffective one query per submission here...)
            $sql = "SELECT 'x'
                      FROM {advwork_submissions} s
                      JOIN {user} a ON (a.id = s.authorid)
                      JOIN {groups_members} agm ON (a.id = agm.userid)
                      JOIN {user} u ON (u.id = ?)
                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                     WHERE s.example = 0 AND s.advworkid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
            $params = array($USER->id, $cm->instance, $itemid);
            if (!$DB->record_exists_sql($sql, $params)) {
                return null;
            }
        }

        // we are inside some particular submission container

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        if (!$storedfile = $fs->get_file($context->id, 'mod_advwork', $filearea, $itemid, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_advwork', $filearea, $itemid);
            } else {
                // not found
                return null;
            }
        }

        // Checks to see if the user can manage files or is the owner.
        // TODO MDL-33805 - Do not use userid here and move the capability check above.
        if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
            return null;
        }

        // let us display the author's name instead of itemid (submission id)

        if (isset($submissionauthors[$itemid])) {
            $topvisiblename = $submissionauthors[$itemid];

        } else {

            $userfields = get_all_user_name_fields(true, 'u');
            $sql = "SELECT s.id, $userfields
                      FROM {advwork_submissions} s
                      JOIN {user} u ON (s.authorid = u.id)
                     WHERE s.example = 0 AND s.advworkid = ?";
            $params = array($cm->instance);
            $rs = $DB->get_recordset_sql($sql, $params);

            foreach ($rs as $submissionauthor) {
                $title = s(fullname($submissionauthor)); // this is generally not unique...
                $submissionauthors[$submissionauthor->id] = $title;
            }
            $rs->close();

            if (!isset($submissionauthors[$itemid])) {
                // should not happen
                return null;
            } else {
                $topvisiblename = $submissionauthors[$itemid];
            }
        }

        $urlbase = $CFG->wwwroot . '/pluginfile.php';
        // do not allow manual modification of any files!
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $topvisiblename, true, true, false, false);
    }

    if ($filearea === 'overallfeedback_content' or $filearea === 'overallfeedback_attachment') {

        if (!has_capability('mod/advwork:viewallassessments', $context)) {
            return null;
        }

        if (is_null($itemid)) {
            // No itemid (assessmentid) passed, display the list of all assessments.
            require_once($CFG->dirroot . '/mod/advwork/fileinfolib.php');
            return new advwork_file_info_overallfeedback_container($browser, $course, $cm, $context, $areas, $filearea);
        }

        // Make sure the user can see the particular assessment in separate groups mode.
        $gmode = groups_get_activity_groupmode($cm, $course);
        if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            // Check there is at least one common group with both the $USER
            // and the submission author.
            $sql = "SELECT 'x'
                      FROM {advwork_submissions} s
                      JOIN {user} a ON (a.id = s.authorid)
                      JOIN {groups_members} agm ON (a.id = agm.userid)
                      JOIN {user} u ON (u.id = ?)
                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                     WHERE s.example = 0 AND s.advworkid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
            $params = array($USER->id, $cm->instance, $itemid);
            if (!$DB->record_exists_sql($sql, $params)) {
                return null;
            }
        }

        // We are inside a particular assessment container.
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        if (!$storedfile = $fs->get_file($context->id, 'mod_advwork', $filearea, $itemid, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_advwork', $filearea, $itemid);
            } else {
                // Not found
                return null;
            }
        }

        // Check to see if the user can manage files or is the owner.
        if (!has_capability('moodle/course:managefiles', $context) and $storedfile->get_userid() != $USER->id) {
            return null;
        }

        $urlbase = $CFG->wwwroot . '/pluginfile.php';

        // Do not allow manual modification of any files.
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
    }

    if ($filearea == 'instructauthors' or $filearea == 'instructreviewers' or $filearea == 'conclusion') {
        // always only itemid 0

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_advwork', $filearea, 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_advwork', $filearea, 0);
            } else {
                // not found
                return null;
            }
        }
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $areas[$filearea], false, true, true, false);
    }
}

////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding advwork nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the advwork module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function advwork_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm) {
    global $CFG;

    if (has_capability('mod/advwork:submit', context_module::instance($cm->id))) {
        $url = new moodle_url('/mod/advwork/submission.php', array('cmid' => $cm->id));
        $mysubmission = $navref->add(get_string('mysubmission', 'advwork'), $url);
        $mysubmission->mainnavonly = true;
    }
}

/**
 * Extends the settings navigation with the advwork settings

 * This function is called when the context for the page is a advwork module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $advworknode {@link navigation_node}
 */
function advwork_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $advworknode=null) {
    global $PAGE;

    //$advworkobject = $DB->get_record("advwork", array("id" => $PAGE->cm->instance));

    if (has_capability('mod/advwork:editdimensions', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/advwork/editform.php', array('cmid' => $PAGE->cm->id));
        $advworknode->add(get_string('editassessmentform', 'advwork'), $url, settings_navigation::TYPE_SETTING);
    }
    if (has_capability('mod/advwork:allocate', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/advwork/allocation.php', array('cmid' => $PAGE->cm->id));
        $advworknode->add(get_string('allocate', 'advwork'), $url, settings_navigation::TYPE_SETTING);
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function advwork_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-advwork-*'=>get_string('page-mod-advwork-x', 'advwork'));
    return $module_pagetype;
}

////////////////////////////////////////////////////////////////////////////////
// Calendar API                                                               //
////////////////////////////////////////////////////////////////////////////////

/**
 * Updates the calendar events associated to the given advwork
 *
 * @param stdClass $advwork the advwork instance record
 * @param int $cmid course module id
 */
function advwork_calendar_update(stdClass $advwork, $cmid) {
    global $DB;

    // get the currently registered events so that we can re-use their ids
    $currentevents = $DB->get_records('event', array('modulename' => 'advwork', 'instance' => $advwork->id));

    // the common properties for all events
    $base = new stdClass();
    $base->description  = format_module_intro('advwork', $advwork, $cmid, false);
    $base->courseid     = $advwork->course;
    $base->groupid      = 0;
    $base->userid       = 0;
    $base->modulename   = 'advwork';
    $base->instance     = $advwork->id;
    $base->visible      = instance_is_visible('advwork', $advwork);
    $base->timeduration = 0;

    if ($advwork->submissionstart) {
        $event = clone($base);
        $event->name = get_string('submissionstartevent', 'mod_advwork', $advwork->name);
        $event->eventtype = advwork_EVENT_TYPE_SUBMISSION_OPEN;
        $event->type = empty($advwork->submissionend) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        $event->timestart = $advwork->submissionstart;
        $event->timesort  = $advwork->submissionstart;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    if ($advwork->submissionend) {
        $event = clone($base);
        $event->name = get_string('submissionendevent', 'mod_advwork', $advwork->name);
        $event->eventtype = advwork_EVENT_TYPE_SUBMISSION_CLOSE;
        $event->type      = CALENDAR_EVENT_TYPE_ACTION;
        $event->timestart = $advwork->submissionend;
        $event->timesort  = $advwork->submissionend;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    if ($advwork->assessmentstart) {
        $event = clone($base);
        $event->name = get_string('assessmentstartevent', 'mod_advwork', $advwork->name);
        $event->eventtype = advwork_EVENT_TYPE_ASSESSMENT_OPEN;
        $event->type      = empty($advwork->assessmentend) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        $event->timestart = $advwork->assessmentstart;
        $event->timesort  = $advwork->assessmentstart;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    if ($advwork->assessmentend) {
        $event = clone($base);
        $event->name = get_string('assessmentendevent', 'mod_advwork', $advwork->name);
        $event->eventtype = advwork_EVENT_TYPE_ASSESSMENT_CLOSE;
        $event->type      = CALENDAR_EVENT_TYPE_ACTION;
        $event->timestart = $advwork->assessmentend;
        $event->timesort  = $advwork->assessmentend;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    // delete any leftover events
    foreach ($currentevents as $oldevent) {
        $oldevent = calendar_event::load($oldevent);
        $oldevent->delete();
    }
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_advwork_core_calendar_provide_event_action(calendar_event $event,
                                                         \core_calendar\action_factory $factory) {

    $cm = get_fast_modinfo($event->courseid)->instances['advwork'][$event->instance];

    return $factory->create_instance(
        get_string('viewadvworksummary', 'advwork'),
        new \moodle_url('/mod/advwork/view.php', array('id' => $cm->id)),
        1,
        true
    );
}

////////////////////////////////////////////////////////////////////////////////
// Course reset API                                                           //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the course reset form with advwork specific settings.
 *
 * @param MoodleQuickForm $mform
 */
function advwork_reset_course_form_definition($mform) {

    $mform->addElement('header', 'advworkheader', get_string('modulenameplural', 'mod_advwork'));

    $mform->addElement('advcheckbox', 'reset_advwork_submissions', get_string('resetsubmissions', 'mod_advwork'));
    $mform->addHelpButton('reset_advwork_submissions', 'resetsubmissions', 'mod_advwork');

    $mform->addElement('advcheckbox', 'reset_advwork_assessments', get_string('resetassessments', 'mod_advwork'));
    $mform->addHelpButton('reset_advwork_assessments', 'resetassessments', 'mod_advwork');
    $mform->disabledIf('reset_advwork_assessments', 'reset_advwork_submissions', 'checked');

    $mform->addElement('advcheckbox', 'reset_advwork_phase', get_string('resetphase', 'mod_advwork'));
    $mform->addHelpButton('reset_advwork_phase', 'resetphase', 'mod_advwork');
}

/**
 * Provides default values for the advwork settings in the course reset form.
 *
 * @param stdClass $course The course to be reset.
 */
function advwork_reset_course_form_defaults(stdClass $course) {

    $defaults = array(
        'reset_advwork_submissions'    => 1,
        'reset_advwork_assessments'    => 1,
        'reset_advwork_phase'          => 1,
    );

    return $defaults;
}

/**
 * Performs the reset of all advwork instances in the course.
 *
 * @param stdClass $data The actual course reset settings.
 * @return array List of results, each being array[(string)component, (string)item, (string)error]
 */
function advwork_reset_userdata(stdClass $data) {
    global $CFG, $DB;

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.
    shift_course_mod_dates('advwork', array('submissionstart', 'submissionend', 'assessmentstart', 'assessmentend'),
        $data->timeshift, $data->courseid);
    $status = array();
    $status[] = array('component' => get_string('modulenameplural', 'advwork'), 'item' => get_string('datechanged'),
        'error' => false);

    if (empty($data->reset_advwork_submissions)
            and empty($data->reset_advwork_assessments)
            and empty($data->reset_advwork_phase) ) {
        // Nothing to do here.
        return $status;
    }

    $advworkrecords = $DB->get_records('advwork', array('course' => $data->courseid));

    if (empty($advworkrecords)) {
        // What a boring course - no advworks here!
        return $status;
    }

    require_once($CFG->dirroot . '/mod/advwork/locallib.php');

    $course = $DB->get_record('course', array('id' => $data->courseid), '*', MUST_EXIST);

    foreach ($advworkrecords as $advworkrecord) {
        $cm = get_coursemodule_from_instance('advwork', $advworkrecord->id, $course->id, false, MUST_EXIST);
        $advwork = new advwork($advworkrecord, $cm, $course);
        $status = array_merge($status, $advwork->reset_userdata($data));
    }

    return $status;
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_advwork_get_fontawesome_icon_map() {
    return [
        'mod_advwork:userplan/task-info' => 'fa-info text-info',
        'mod_advwork:userplan/task-todo' => 'fa-square-o',
        'mod_advwork:userplan/task-done' => 'fa-check text-success',
        'mod_advwork:userplan/task-fail' => 'fa-remove text-danger',
    ];
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.4
 */
function advwork_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB, $USER;

    $updates = course_check_module_updates_since($cm, $from, array('instructauthors', 'instructreviewers', 'conclusion'), $filter);

    // Check if there are new submissions, assessments or assessments grades in the advwork.
    $updates->submissions = (object) array('updated' => false);
    $updates->assessments = (object) array('updated' => false);
    $updates->assessmentgrades = (object) array('updated' => false);

    $select = 'advworkid = ? AND authorid = ? AND (timecreated > ? OR timegraded > ? OR timemodified > ?)';
    $params = array($cm->instance, $USER->id, $from, $from, $from);
    $submissions = $DB->get_records_select('advwork_submissions', $select, $params, '', 'id');
    if (!empty($submissions)) {
        $updates->submissions->updated = true;
        $updates->submissions->itemids = array_keys($submissions);
    }

    // Get assessments updates (both submissions reviewed by me or reviews by others).
    $select = "SELECT a.id
                 FROM {advwork_assessments} a
                 JOIN {advwork_submissions} s ON a.submissionid = s.id
                 WHERE s.advworkid = ? AND (a.timecreated > ? OR a.timemodified > ?) AND (s.authorid = ? OR a.reviewerid = ?)";
    $params = array($cm->instance, $from, $from, $USER->id, $USER->id);
    $assessments = $DB->get_records_sql($select, $params);
    if (!empty($assessments)) {
        $updates->assessments->updated = true;
        $updates->assessments->itemids = array_keys($assessments);
    }
    // Finally assessment aggregated grades.
    $select = 'advworkid = ? AND userid = ? AND timegraded > ?';
    $params = array($cm->instance, $USER->id, $from);
    $assessmentgrades = $DB->get_records_select('advwork_aggregations', $select, $params, '', 'id');
    if (!empty($assessmentgrades)) {
        $updates->assessmentgrades->updated = true;
        $updates->assessmentgrades->itemids = array_keys($assessmentgrades);
    }

    // Now, teachers should see other students updates.
    $canviewallsubmissions = has_capability('mod/advwork:viewallsubmissions', $cm->context);
    $canviewallassessments = has_capability('mod/advwork:viewallassessments', $cm->context);
    if ($canviewallsubmissions || $canviewallassessments) {

        $insql = '';
        $inparams = array();
        // To filter by users in my groups when separated groups are forced.
        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            $groupusers = array_keys(groups_get_activity_shared_group_members($cm));
            if (empty($groupusers)) {
                return $updates;
            }
            list($insql, $inparams) = $DB->get_in_or_equal($groupusers);
        }

        if ($canviewallsubmissions) {
            $updates->usersubmissions = (object) array('updated' => false);
            $select = 'advworkid = ? AND (timecreated > ? OR timegraded > ? OR timemodified > ?)';
            $params = array($cm->instance, $from, $from, $from);
            if (!empty($insql)) {
                $select .= " AND authorid $insql";
                $params = array_merge($params, $inparams);
            }
            $usersubmissions = $DB->get_records_select('advwork_submissions', $select, $params, '', 'id');
            if (!empty($usersubmissions)) {
                $updates->usersubmissions->updated = true;
                $updates->usersubmissions->itemids = array_keys($usersubmissions);
            }
        }

        if ($canviewallassessments) {
            $updates->userassessments = (object) array('updated' => false);
            $select = "SELECT a.id
                         FROM {advwork_assessments} a
                         JOIN {advwork_submissions} s ON a.submissionid = s.id
                        WHERE s.advworkid = ? AND (a.timecreated > ? OR a.timemodified > ?)";
            $params = array($cm->instance, $from, $from);
            if (!empty($insql)) {
                $select .= " AND s.reviewerid $insql";
                $params = array_merge($params, $inparams);
            }
            $userassessments = $DB->get_records_sql($select, $params);
            if (!empty($userassessments)) {
                $updates->userassessments->updated = true;
                $updates->userassessments->itemids = array_keys($userassessments);
            }

            $updates->userassessmentgrades = (object) array('updated' => false);
            $select = 'advworkid = ? AND timegraded > ?';
            $params = array($cm->instance, $USER->id);
            if (!empty($insql)) {
                $select .= " AND userid $insql";
                $params = array_merge($params, $inparams);
            }
            $userassessmentgrades = $DB->get_records_select('advwork_aggregations', $select, $params, '', 'id');
            if (!empty($userassessmentgrades)) {
                $updates->userassessmentgrades->updated = true;
                $updates->userassessmentgrades->itemids = array_keys($userassessmentgrades);
            }
        }
    }
    return $updates;
}
