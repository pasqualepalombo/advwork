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
 * View a single (usually the own) submission, submit own work.
 *
 * @package    mod_advwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');

$cmid = required_param('cmid', PARAM_INT); // Course module id.
$id = optional_param('id', 0, PARAM_INT); // Submission id.
$edit = optional_param('edit', false, PARAM_BOOL); // Open the page for editing?
$assess = optional_param('assess', false, PARAM_BOOL); // Instant assessment required.
$delete = optional_param('delete', false, PARAM_BOOL); // Submission removal requested.
$confirm = optional_param('confirm', false, PARAM_BOOL); // Submission removal request confirmed.

$cm = get_coursemodule_from_id('advwork', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);
if (isguestuser()) {
    print_error('guestsarenotallowed');
}

$advworkrecord = $DB->get_record('advwork', array('id' => $cm->instance), '*', MUST_EXIST);
$advwork = new advwork($advworkrecord, $cm, $course);

$PAGE->set_url($advwork->submission_url(), array('cmid' => $cmid, 'id' => $id));

if ($edit) {
    $PAGE->url->param('edit', $edit);
}

if ($id) { // submission is specified
    $submission = $advwork->get_submission_by_id($id);

} else { // no submission specified
    if (!$submission = $advwork->get_submission_by_author($USER->id)) {
        $submission = new stdclass();
        $submission->id = null;
        $submission->authorid = $USER->id;
        $submission->example = 0;
        $submission->grade = null;
        $submission->gradeover = null;
        $submission->published = null;
        $submission->feedbackauthor = null;
        $submission->feedbackauthorformat = editors_get_preferred_format();
    }
}

$ownsubmission  = $submission->authorid == $USER->id;
$canviewall     = has_capability('mod/advwork:viewallsubmissions', $advwork->context);
$cansubmit      = has_capability('mod/advwork:submit', $advwork->context);
$canallocate    = has_capability('mod/advwork:allocate', $advwork->context);
$canpublish     = has_capability('mod/advwork:publishsubmissions', $advwork->context);
$canoverride    = (($advwork->phase == advwork::PHASE_EVALUATION) and has_capability('mod/advwork:overridegrades', $advwork->context));
$candeleteall   = has_capability('mod/advwork:deletesubmissions', $advwork->context);
$userassessment = $advwork->get_assessment_of_submission_by_user($submission->id, $USER->id);
$isreviewer     = !empty($userassessment);
$editable       = ($cansubmit and $ownsubmission);
$deletable      = $candeleteall;
$ispublished    = ($advwork->phase == advwork::PHASE_CLOSED
                    and $submission->published == 1
                    and has_capability('mod/advwork:viewpublishedsubmissions', $advwork->context));

if (empty($submission->id) and !$advwork->creating_submission_allowed($USER->id)) {
    $editable = false;
}
if ($submission->id and !$advwork->modifying_submission_allowed($USER->id)) {
    $editable = false;
}

$canviewall = $canviewall && $advwork->check_group_membership($submission->authorid);

$editable = ($editable && $advwork->check_examples_assessed_before_submission($USER->id));
$edit = ($editable and $edit);

if (!$candeleteall and $ownsubmission and $editable) {
    // Only allow the student to delete their own submission if it's still editable and hasn't been assessed.
    if (count($advwork->get_assessments_of_submission($submission->id)) > 0) {
        $deletable = false;
    } else {
        $deletable = true;
    }
}

if ($submission->id and $delete and $confirm and $deletable) {
    require_sesskey();
    $advwork->delete_submission($submission);

    redirect($advwork->view_url());
}

$seenaspublished = false; // is the submission seen as a published submission?

if ($submission->id and ($ownsubmission or $canviewall or $isreviewer)) {
    // ok you can go
} elseif ($submission->id and $ispublished) {
    // ok you can go
    $seenaspublished = true;
} elseif (is_null($submission->id) and $cansubmit) {
    // ok you can go
} else {
    print_error('nopermissions', 'error', $advwork->view_url(), 'view or create submission');
}

if ($submission->id) {
    // Trigger submission viewed event.
    $advwork->set_submission_viewed($submission);
}

if ($assess and $submission->id and !$isreviewer and $canallocate and $advwork->assessing_allowed($USER->id)) {
    require_sesskey();
    $assessmentid = $advwork->add_allocation($submission, $USER->id);
    redirect($advwork->assess_url($assessmentid));
}

if ($edit) {
    require_once(__DIR__.'/submission_form.php');

    $submission = file_prepare_standard_editor($submission, 'content', $advwork->submission_content_options(),
        $advwork->context, 'mod_advwork', 'submission_content', $submission->id);

    $submission = file_prepare_standard_filemanager($submission, 'attachment', $advwork->submission_attachment_options(),
        $advwork->context, 'mod_advwork', 'submission_attachment', $submission->id);

    $mform = new advwork_submission_form($PAGE->url, array('current' => $submission, 'advwork' => $advwork,
        'contentopts' => $advwork->submission_content_options(), 'attachmentopts' => $advwork->submission_attachment_options()));

    if ($mform->is_cancelled()) {
        redirect($advwork->view_url());

    } elseif ($cansubmit and $formdata = $mform->get_data()) {

        $formdata->id = $submission->id;
        // Creates or updates submission.
        $submission->id = $advwork->edit_submission($formdata);

        redirect($advwork->submission_url($submission->id));
    }
}

// load the form to override grade and/or publish the submission and process the submitted data eventually
if (!$edit and ($canoverride or $canpublish)) {
    $options = array(
        'editable' => true,
        'editablepublished' => $canpublish,
        'overridablegrade' => $canoverride);
    $feedbackform = $advwork->get_feedbackauthor_form($PAGE->url, $submission, $options);
    if ($data = $feedbackform->get_data()) {
        $advwork->evaluate_submission($submission, $data, $canpublish, $canoverride);
        redirect($advwork->view_url());
    }
}

$PAGE->set_title($advwork->name);
$PAGE->set_heading($course->fullname);
if ($edit) {
    $PAGE->navbar->add(get_string('mysubmission', 'advwork'), $advwork->submission_url(), navigation_node::TYPE_CUSTOM);
    $PAGE->navbar->add(get_string('editingsubmission', 'advwork'));
} elseif ($ownsubmission) {
    $PAGE->navbar->add(get_string('mysubmission', 'advwork'));
} else {
    $PAGE->navbar->add(get_string('submission', 'advwork'));
}

// Output starts here
$output = $PAGE->get_renderer('mod_advwork');
echo $output->header();
echo $output->heading(format_string($advwork->name), 2);
echo $output->heading(get_string('mysubmission', 'advwork'), 3);

// show instructions for submitting as thay may contain some list of questions and we need to know them
// while reading the submitted answer
if (trim($advwork->instructauthors)) {
    $instructions = file_rewrite_pluginfile_urls($advwork->instructauthors, 'pluginfile.php', $PAGE->context->id,
        'mod_advwork', 'instructauthors', null, advwork::instruction_editors_options($PAGE->context));
    print_collapsible_region_start('', 'advwork-viewlet-instructauthors', get_string('instructauthors', 'advwork'));
    echo $output->box(format_text($instructions, $advwork->instructauthorsformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
    print_collapsible_region_end();
}

// if in edit mode, display the form to edit the submission

if ($edit) {
    if (!empty($CFG->enableplagiarism)) {
        require_once($CFG->libdir.'/plagiarismlib.php');
        echo plagiarism_print_disclosure($cm->id);
    }
    $mform->display();
    echo $output->footer();
    die();
}

// Confirm deletion (if requested).
if ($deletable and $delete) {
    $prompt = get_string('submissiondeleteconfirm', 'advwork');
    if ($candeleteall) {
        $count = count($advwork->get_assessments_of_submission($submission->id));
        if ($count > 0) {
            $prompt = get_string('submissiondeleteconfirmassess', 'advwork', ['count' => $count]);
        }
    }
    echo $output->confirm($prompt, new moodle_url($PAGE->url, ['delete' => 1, 'confirm' => 1]), $advwork->view_url());
}

// else display the submission

if ($submission->id) {
    if ($seenaspublished) {
        $showauthor = has_capability('mod/advwork:viewauthorpublished', $advwork->context);
    } else {
        $showauthor = has_capability('mod/advwork:viewauthornames', $advwork->context);
    }
    echo $output->render($advwork->prepare_submission($submission, $showauthor));
} else {
    echo $output->box(get_string('noyoursubmission', 'advwork'));
}

// If not at removal confirmation screen, some action buttons can be displayed.
if (!$delete) {
    // Display create/edit button.
    if ($editable) {
        if ($submission->id) {
            $btnurl = new moodle_url($PAGE->url, array('edit' => 'on', 'id' => $submission->id));
            $btntxt = get_string('editsubmission', 'advwork');
        } else {
            $btnurl = new moodle_url($PAGE->url, array('edit' => 'on'));
            $btntxt = get_string('createsubmission', 'advwork');
        }
        echo $output->single_button($btnurl, $btntxt, 'get');
    }

    // Display delete button.
    if ($submission->id and $deletable) {
        $url = new moodle_url($PAGE->url, array('delete' => 1));
        echo $output->single_button($url, get_string('deletesubmission', 'advwork'), 'get');
    }

    // Display assess button.
    if ($submission->id and !$edit and !$isreviewer and $canallocate and $advwork->assessing_allowed($USER->id)) {
        $url = new moodle_url($PAGE->url, array('assess' => 1));
        echo $output->single_button($url, get_string('assess', 'advwork'), 'post');
    }
}

if (($advwork->phase == advwork::PHASE_CLOSED) and ($ownsubmission or $canviewall)) {
    if (!empty($submission->gradeoverby) and strlen(trim($submission->feedbackauthor)) > 0) {
        echo $output->render(new advwork_feedback_author($submission));
    }
}

// and possibly display the submission's review(s)

if ($isreviewer) {
    // user's own assessment
    $strategy   = $advwork->grading_strategy_instance();
    $mform      = $strategy->get_assessment_form($PAGE->url, 'assessment', $userassessment, false);
    $options    = array(
        'showreviewer'  => true,
        'showauthor'    => $showauthor,
        'showform'      => !is_null($userassessment->grade),
        'showweight'    => true,
    );
    $assessment = $advwork->prepare_assessment($userassessment, $mform, $options);
    $assessment->title = get_string('assessmentbyyourself', 'advwork');

    if ($advwork->assessing_allowed($USER->id)) {
        if (is_null($userassessment->grade)) {
            $assessment->add_action($advwork->assess_url($assessment->id), get_string('assess', 'advwork'));
        } else {
            $assessment->add_action($advwork->assess_url($assessment->id), get_string('reassess', 'advwork'));
        }
    }
    if ($canoverride) {
        $assessment->add_action($advwork->assess_url($assessment->id), get_string('assessmentsettings', 'advwork'));
    }

    echo $output->render($assessment);

    if ($advwork->phase == advwork::PHASE_CLOSED) {
        if (strlen(trim($userassessment->feedbackreviewer)) > 0) {
            echo $output->render(new advwork_feedback_reviewer($userassessment));
        }
    }
}

if (has_capability('mod/advwork:viewallassessments', $advwork->context) or ($ownsubmission and $advwork->assessments_available())) {
    // other assessments
    $strategy       = $advwork->grading_strategy_instance();
    $assessments    = $advwork->get_assessments_of_submission($submission->id);
    $showreviewer   = has_capability('mod/advwork:viewreviewernames', $advwork->context);
    foreach ($assessments as $assessment) {
        if ($assessment->reviewerid == $USER->id) {
            // own assessment has been displayed already
            continue;
        }
        if (is_null($assessment->grade) and !has_capability('mod/advwork:viewallassessments', $advwork->context)) {
            // students do not see peer-assessment that are not graded yet
            continue;
        }
        $mform      = $strategy->get_assessment_form($PAGE->url, 'assessment', $assessment, false);
        $options    = array(
            'showreviewer'  => $showreviewer,
            'showauthor'    => $showauthor,
            'showform'      => !is_null($assessment->grade),
            'showweight'    => true,
        );
        $displayassessment = $advwork->prepare_assessment($assessment, $mform, $options);
        if ($canoverride) {
            $displayassessment->add_action($advwork->assess_url($assessment->id), get_string('assessmentsettings', 'advwork'));
        }
        echo $output->render($displayassessment);

        if ($advwork->phase == advwork::PHASE_CLOSED and has_capability('mod/advwork:viewallassessments', $advwork->context)) {
            if (strlen(trim($assessment->feedbackreviewer)) > 0) {
                echo $output->render(new advwork_feedback_reviewer($assessment));
            }
        }
    }
}

if (!$edit and $canoverride) {
    // display a form to override the submission grade
    $feedbackform->display();
}

// If portfolios are enabled and we are not on the edit/removal confirmation screen, display a button to export this page.
// The export is not offered if the submission is seen as a published one (it has no relation to the current user.
if (!empty($CFG->enableportfolios)) {
    if (!$delete and !$edit and !$seenaspublished and $submission->id and ($ownsubmission or $canviewall or $isreviewer)) {
        if (has_capability('mod/advwork:exportsubmissions', $advwork->context)) {
            require_once($CFG->libdir.'/portfoliolib.php');

            $button = new portfolio_add_button();
            $button->set_callback_options('mod_advwork_portfolio_caller', array(
                'id' => $advwork->cm->id,
                'submissionid' => $submission->id,
            ), 'mod_advwork');
            $button->set_formats(PORTFOLIO_FORMAT_RICHHTML);
            echo html_writer::start_tag('div', array('class' => 'singlebutton'));
            echo $button->to_html(PORTFOLIO_ADD_FULL_FORM, get_string('exportsubmission', 'advwork'));
            echo html_writer::end_tag('div');
        }
    }
}

echo $output->footer();
