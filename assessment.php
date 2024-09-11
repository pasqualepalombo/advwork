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
 * Assess a submission or view the single assessment
 *
 * Assessment id parameter must be passed. The script displays the submission and
 * the assessment form. If the current user is the reviewer and the assessing is
 * allowed, new assessment can be saved.
 * If the assessing is not allowed (for example, the assessment period is over
 * or the current user is eg a teacher), the assessment form is opened
 * in a non-editable mode.
 * The capability 'mod/advwork:peerassess' is intentionally not checked here.
 * The user is considered as a reviewer if the corresponding assessment record
 * has been prepared for him/her (during the allocation). So even a user without the
 * peerassess capability (like a 'teacher', for example) can become a reviewer.
 *
 * @package    mod_advwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');

$asid       = required_param('asid', PARAM_INT);  // assessment id
$assessment = $DB->get_record('advwork_assessments', array('id' => $asid), '*', MUST_EXIST);
$submission = $DB->get_record('advwork_submissions', array('id' => $assessment->submissionid, 'example' => 0), '*', MUST_EXIST);
$advwork   = $DB->get_record('advwork', array('id' => $submission->advworkid), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $advwork->course), '*', MUST_EXIST);
$cm         = get_coursemodule_from_instance('advwork', $advwork->id, $course->id, false, MUST_EXIST);

require_login($course, false, $cm);
if (isguestuser()) {
    print_error('guestsarenotallowed');
}
$advwork = new advwork($advwork, $cm, $course);

$PAGE->set_url($advwork->assess_url($assessment->id));
$PAGE->set_title($advwork->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('assessingsubmission', 'advwork'));



$cansetassessmentweight = has_capability('mod/advwork:allocate', $advwork->context);
$canoverridegrades      = has_capability('mod/advwork:overridegrades', $advwork->context);
$isreviewer             = ($USER->id == $assessment->reviewerid);


$advwork->check_view_assessment($assessment, $submission);







$groupid = groups_get_activity_group($advwork->cm, true);





// only the reviewer is allowed to modify the assessment
if ($isreviewer and $advwork->assessing_allowed($USER->id)) {
    $assessmenteditable = true;
} else {
    $assessmenteditable = false;
}

// check that all required examples have been assessed by the user
if ($assessmenteditable) {

    list($assessed, $notice) = $advwork->check_examples_assessed_before_assessment($assessment->reviewerid);
    if (!$assessed) {
        echo $output->header();
        echo $output->heading(format_string($advwork->name));
        notice(get_string($notice, 'advwork'), new moodle_url('/mod/advwork/view.php', array('id' => $cm->id)));
        echo $output->footer();
        exit;
    }
}

// load the grading strategy logic
$strategy = $advwork->grading_strategy_instance();

if (is_null($assessment->grade) and !$assessmenteditable) {
    $mform = null;
} else {
    // Are there any other pending assessments to do but this one?
    if ($assessmenteditable) {
        $pending = $advwork->get_pending_assessments_by_reviewer($assessment->reviewerid, $assessment->id);
    } else {
        $pending = array();
    }
    // load the assessment form and process the submitted data eventually
    $mform = $strategy->get_assessment_form($PAGE->url, 'assessment', $assessment, $assessmenteditable,
                                        array('editableweight' => $cansetassessmentweight, 'pending' => !empty($pending)));

    // Set data managed by the advwork core, subplugins set their own data themselves.
    $currentdata = (object)array(
        'weight' => $assessment->weight,
        'feedbackauthor' => $assessment->feedbackauthor,
        'feedbackauthorformat' => $assessment->feedbackauthorformat,
    );
    if ($assessmenteditable and $advwork->overallfeedbackmode) {
        $currentdata = file_prepare_standard_editor($currentdata, 'feedbackauthor', $advwork->overall_feedback_content_options(),
            $advwork->context, 'mod_advwork', 'overallfeedback_content', $assessment->id);
        if ($advwork->overallfeedbackfiles) {
            $currentdata = file_prepare_standard_filemanager($currentdata, 'feedbackauthorattachment',
                $advwork->overall_feedback_attachment_options(), $advwork->context, 'mod_advwork', 'overallfeedback_attachment',
                $assessment->id);
        }
    }
    $mform->set_data($currentdata);

    if ($mform->is_cancelled()) {
        redirect($advwork->view_url());
    } elseif ($assessmenteditable and ($data = $mform->get_data())) {

        // Add or update assessment.
        $rawgrade = $advwork->edit_assessment($assessment, $submission, $data, $strategy);
        $a = is_null($rawgrade);
        // check if we should send the session data for modeling to the BNS
        if(!is_null($rawgrade)) {
            // send the session data to the BNS
            $courseid = $advwork->course->id;
            $courseteachersid = $advwork->get_course_teachers($courseid);
            $iscourseteacher = in_array($USER->id, $courseteachersid);

            if (isloggedin() && $iscourseteacher) {
                $advwork->send_session_data($courseid, $advwork, $courseteachersid, $cm);
            }
        }

        // And finally redirect the user's browser.
        if (!is_null($rawgrade) and isset($data->saveandclose)) {
            redirect($advwork->view_url());
        } else if (!is_null($rawgrade) and isset($data->saveandshownext)) {
            $next = reset($pending);
            if (!empty($next)) {
                redirect($advwork->assess_url($next->id));
            } else {
                redirect($PAGE->url); // This should never happen but just in case...
            }
        } else {
            // either it is not possible to calculate the $rawgrade
            // or the reviewer has chosen "Save and continue"
            redirect($PAGE->url);
        }
    }
}

// load the form to override gradinggrade and/or set weight and process the submitted data eventually
if ($canoverridegrades or $cansetassessmentweight) {
    $options = array(
        'editable' => true,
        'editableweight' => $cansetassessmentweight,
        'overridablegradinggrade' => $canoverridegrades);
    $feedbackform = $advwork->get_feedbackreviewer_form($PAGE->url, $assessment, $options);
    if ($data = $feedbackform->get_data()) {
        $advwork->evaluate_assessment($assessment, $data, $cansetassessmentweight, $canoverridegrades);
        redirect($advwork->view_url());
    }
}

// output starts here
$output = $PAGE->get_renderer('mod_advwork');      // advwork renderer
echo $output->header();
echo $output->heading(format_string($advwork->name));
echo $output->heading(get_string('assessedsubmission', 'advwork'), 3);

$submission = $advwork->get_submission_by_id($submission->id);     // reload so can be passed to the renderer
echo $output->render($advwork->prepare_submission($submission, has_capability('mod/advwork:viewauthornames', $advwork->context)));

// show instructions for assessing as they may contain important information
// for evaluating the assessment
if (trim($advwork->instructreviewers)) {
    $instructions = file_rewrite_pluginfile_urls($advwork->instructreviewers, 'pluginfile.php', $PAGE->context->id,
        'mod_advwork', 'instructreviewers', null, advwork::instruction_editors_options($PAGE->context));
    print_collapsible_region_start('', 'advwork-viewlet-instructreviewers', get_string('instructreviewers', 'advwork'));
    echo $output->box(format_text($instructions, $advwork->instructreviewersformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
    print_collapsible_region_end();
}

// extend the current assessment record with user details
$assessment = $advwork->get_assessment_by_id($assessment->id);

if ($isreviewer) {
    $options    = array(
        'showreviewer'  => true,
        'showauthor'    => has_capability('mod/advwork:viewauthornames', $advwork->context),
        'showform'      => $assessmenteditable or !is_null($assessment->grade),
        'showweight'    => true,
    );
    $assessment = $advwork->prepare_assessment($assessment, $mform, $options);
    $assessment->title = get_string('assessmentbyyourself', 'advwork');
    echo $output->render($assessment);

} else {
    $options    = array(
        'showreviewer'  => has_capability('mod/advwork:viewreviewernames', $advwork->context),
        'showauthor'    => has_capability('mod/advwork:viewauthornames', $advwork->context),
        'showform'      => $assessmenteditable or !is_null($assessment->grade),
        'showweight'    => true,
    );
    $assessment = $advwork->prepare_assessment($assessment, $mform, $options);
    echo $output->render($assessment);
}

if (!$assessmenteditable and $canoverridegrades) {
    $feedbackform->display();
}

echo $output->footer();
