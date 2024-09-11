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
 * The main advwork configuration form
 *
 * The UI mockup has been proposed in MDL-18688
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/dev/lib/formslib.php
 *
 * @package    mod_advwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once(__DIR__ . '/locallib.php');

require_once($CFG->libdir . '/filelib.php');

/**
 * Module settings form for advwork instances
 */
class mod_advwork_mod_form extends moodleform_mod {

    /** @var object the course this instance is part of */
    protected $course = null;

    /**
     * Constructor
     */
    public function __construct($current, $section, $cm, $course) {
        $this->course = $course;
        parent::__construct($current, $section, $cm, $course);
    }

    /**
     * Defines the advwork instance configuration form
     *
     * @return void
     */
    public function definition() {
        global $CFG;

        $advworkconfig = get_config('advwork');
        $mform = $this->_form;

        // General --------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // advwork name
        $label = get_string('advworkname', 'advwork');
        $mform->addElement('text', 'name', $label, array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Introduction
        $this->standard_intro_elements(get_string('introduction', 'advwork'));

        // Grading settings -----------------------------------------------------------
        $mform->addElement('header', 'gradingsettings', get_string('gradingsettings', 'advwork'));
        $mform->setExpanded('gradingsettings');

        $label = get_string('strategy', 'advwork');
        $mform->addElement('select', 'strategy', $label, advwork::available_strategies_list());
        $mform->setDefault('strategy', $advworkconfig->strategy);
        $mform->addHelpButton('strategy', 'strategy', 'advwork');

        $grades = advwork::available_maxgrades_list();
        $gradecategories = grade_get_categories_menu($this->course->id);

        $label = get_string('submissiongrade', 'advwork');
        $mform->addGroup(array(
            $mform->createElement('select', 'grade', '', $grades),
            $mform->createElement('select', 'gradecategory', '', $gradecategories),
            ), 'submissiongradegroup', $label, ' ', false);
        $mform->setDefault('grade', $advworkconfig->grade);
        $mform->addHelpButton('submissiongradegroup', 'submissiongrade', 'advwork');

        $mform->addElement('text', 'submissiongradepass', get_string('gradetopasssubmission', 'advwork'));
        $mform->addHelpButton('submissiongradepass', 'gradepass', 'grades');
        $mform->setDefault('submissiongradepass', '');
        $mform->setType('submissiongradepass', PARAM_RAW);

        $label = get_string('gradinggrade', 'advwork');
        $mform->addGroup(array(
            $mform->createElement('select', 'gradinggrade', '', $grades),
            $mform->createElement('select', 'gradinggradecategory', '', $gradecategories),
            ), 'gradinggradegroup', $label, ' ', false);
        $mform->setDefault('gradinggrade', $advworkconfig->gradinggrade);
        $mform->addHelpButton('gradinggradegroup', 'gradinggrade', 'advwork');

        $mform->addElement('text', 'gradinggradepass', get_string('gradetopassgrading', 'advwork'));
        $mform->addHelpButton('gradinggradepass', 'gradepass', 'grades');
        $mform->setDefault('gradinggradepass', '');
        $mform->setType('gradinggradepass', PARAM_RAW);

        $options = array();
        for ($i = 5; $i >= 0; $i--) {
            $options[$i] = $i;
        }
        $label = get_string('gradedecimals', 'advwork');
        $mform->addElement('select', 'gradedecimals', $label, $options);
        $mform->setDefault('gradedecimals', $advworkconfig->gradedecimals);

        // Submission settings --------------------------------------------------------
        $mform->addElement('header', 'submissionsettings', get_string('submissionsettings', 'advwork'));

        $label = get_string('instructauthors', 'advwork');
        $mform->addElement('editor', 'instructauthorseditor', $label, null,
                            advwork::instruction_editors_options($this->context));

        $options = array();
        for ($i = 7; $i >= 0; $i--) {
            $options[$i] = $i;
        }
        $label = get_string('nattachments', 'advwork');
        $mform->addElement('select', 'nattachments', $label, $options);
        $mform->setDefault('nattachments', 1);

        $label = get_string('allowedfiletypesforsubmission', 'advwork');
        $mform->addElement('filetypes', 'submissionfiletypes', $label);
        $mform->addHelpButton('submissionfiletypes', 'allowedfiletypesforsubmission', 'advwork');
        $mform->disabledIf('submissionfiletypes', 'nattachments', 'eq', 0);

        $options = get_max_upload_sizes($CFG->maxbytes, $this->course->maxbytes, 0, $advworkconfig->maxbytes);
        $mform->addElement('select', 'maxbytes', get_string('maxbytes', 'advwork'), $options);
        $mform->setDefault('maxbytes', $advworkconfig->maxbytes);
        $mform->disabledIf('maxbytes', 'nattachments', 'eq', 0);

        $label = get_string('latesubmissions', 'advwork');
        $text = get_string('latesubmissions_desc', 'advwork');
        $mform->addElement('checkbox', 'latesubmissions', $label, $text);
        $mform->addHelpButton('latesubmissions', 'latesubmissions', 'advwork');

        // Assessment settings --------------------------------------------------------
        $mform->addElement('header', 'assessmentsettings', get_string('assessmentsettings', 'advwork'));

        $label = get_string('instructreviewers', 'advwork');
        $mform->addElement('editor', 'instructreviewerseditor', $label, null,
                            advwork::instruction_editors_options($this->context));

        $label = get_string('useselfassessment', 'advwork');
        $text = get_string('useselfassessment_desc', 'advwork');
        $mform->addElement('checkbox', 'useselfassessment', $label, $text);
        $mform->addHelpButton('useselfassessment', 'useselfassessment', 'advwork');

        // Feedback -------------------------------------------------------------------
        $mform->addElement('header', 'feedbacksettings', get_string('feedbacksettings', 'advwork'));

        $mform->addElement('select', 'overallfeedbackmode', get_string('overallfeedbackmode', 'mod_advwork'), array(
            0 => get_string('overallfeedbackmode_0', 'mod_advwork'),
            1 => get_string('overallfeedbackmode_1', 'mod_advwork'),
            2 => get_string('overallfeedbackmode_2', 'mod_advwork')));
        $mform->addHelpButton('overallfeedbackmode', 'overallfeedbackmode', 'mod_advwork');
        $mform->setDefault('overallfeedbackmode', 1);

        $options = array();
        for ($i = 7; $i >= 0; $i--) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'overallfeedbackfiles', get_string('overallfeedbackfiles', 'advwork'), $options);
        $mform->setDefault('overallfeedbackfiles', 0);
        $mform->disabledIf('overallfeedbackfiles', 'overallfeedbackmode', 'eq', 0);

        $label = get_string('allowedfiletypesforoverallfeedback', 'advwork');
        $mform->addElement('filetypes', 'overallfeedbackfiletypes', $label);
        $mform->addHelpButton('overallfeedbackfiletypes', 'allowedfiletypesforoverallfeedback', 'advwork');
        $mform->disabledIf('overallfeedbackfiletypes', 'overallfeedbackfiles', 'eq', 0);

        $options = get_max_upload_sizes($CFG->maxbytes, $this->course->maxbytes);
        $mform->addElement('select', 'overallfeedbackmaxbytes', get_string('overallfeedbackmaxbytes', 'advwork'), $options);
        $mform->setDefault('overallfeedbackmaxbytes', $advworkconfig->maxbytes);
        $mform->disabledIf('overallfeedbackmaxbytes', 'overallfeedbackmode', 'eq', 0);
        $mform->disabledIf('overallfeedbackmaxbytes', 'overallfeedbackfiles', 'eq', 0);

        $label = get_string('conclusion', 'advwork');
        $mform->addElement('editor', 'conclusioneditor', $label, null,
                            advwork::instruction_editors_options($this->context));
        $mform->addHelpButton('conclusioneditor', 'conclusion', 'advwork');

        // Example submissions --------------------------------------------------------
        $mform->addElement('header', 'examplesubmissionssettings', get_string('examplesubmissions', 'advwork'));

        $label = get_string('useexamples', 'advwork');
        $text = get_string('useexamples_desc', 'advwork');
        $mform->addElement('checkbox', 'useexamples', $label, $text);
        $mform->addHelpButton('useexamples', 'useexamples', 'advwork');

        $label = get_string('examplesmode', 'advwork');
        $options = advwork::available_example_modes_list();
        $mform->addElement('select', 'examplesmode', $label, $options);
        $mform->setDefault('examplesmode', $advworkconfig->examplesmode);
        $mform->disabledIf('examplesmode', 'useexamples');

        // Availability ---------------------------------------------------------------
        $mform->addElement('header', 'accesscontrol', get_string('availability', 'core'));

        $label = get_string('submissionstart', 'advwork');
        $mform->addElement('date_time_selector', 'submissionstart', $label, array('optional' => true));

        $label = get_string('submissionend', 'advwork');
        $mform->addElement('date_time_selector', 'submissionend', $label, array('optional' => true));

        $label = get_string('submissionendswitch', 'mod_advwork');
        $mform->addElement('checkbox', 'phaseswitchassessment', $label);
        $mform->disabledIf('phaseswitchassessment', 'submissionend[enabled]');
        $mform->addHelpButton('phaseswitchassessment', 'submissionendswitch', 'mod_advwork');

        $label = get_string('assessmentstart', 'advwork');
        $mform->addElement('date_time_selector', 'assessmentstart', $label, array('optional' => true));

        $label = get_string('assessmentend', 'advwork');
        $mform->addElement('date_time_selector', 'assessmentend', $label, array('optional' => true));

        $coursecontext = context_course::instance($this->course->id);
        plagiarism_get_form_elements_module($mform, $coursecontext, 'mod_advwork');

        // Common module settings, Restrict availability, Activity completion etc. ----
        $features = array('groups' => true, 'groupings' => true,
                'outcomes' => true, 'gradecat' => false, 'idnumber' => false);

        $this->standard_coursemodule_elements();

        // Standard buttons, common to all modules ------------------------------------
        $this->add_action_buttons();
    }

    /**
     * Prepares the form before data are set
     *
     * Additional wysiwyg editor are prepared here, the introeditor is prepared automatically by core.
     * Grade items are set here because the core modedit supports single grade item only.
     *
     * @param array $data to be set
     * @return void
     */
    public function data_preprocessing(&$data) {
        if ($this->current->instance) {
            // editing an existing advwork - let us prepare the added editor elements (intro done automatically)
            $draftitemid = file_get_submitted_draft_itemid('instructauthors');
            $data['instructauthorseditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_advwork', 'instructauthors', 0,
                                advwork::instruction_editors_options($this->context),
                                $data['instructauthors']);
            $data['instructauthorseditor']['format'] = $data['instructauthorsformat'];
            $data['instructauthorseditor']['itemid'] = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid('instructreviewers');
            $data['instructreviewerseditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_advwork', 'instructreviewers', 0,
                                advwork::instruction_editors_options($this->context),
                                $data['instructreviewers']);
            $data['instructreviewerseditor']['format'] = $data['instructreviewersformat'];
            $data['instructreviewerseditor']['itemid'] = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid('conclusion');
            $data['conclusioneditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_advwork', 'conclusion', 0,
                                advwork::instruction_editors_options($this->context),
                                $data['conclusion']);
            $data['conclusioneditor']['format'] = $data['conclusionformat'];
            $data['conclusioneditor']['itemid'] = $draftitemid;
        } else {
            // adding a new advwork instance
            $draftitemid = file_get_submitted_draft_itemid('instructauthors');
            file_prepare_draft_area($draftitemid, null, 'mod_advwork', 'instructauthors', 0);    // no context yet, itemid not used
            $data['instructauthorseditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);

            $draftitemid = file_get_submitted_draft_itemid('instructreviewers');
            file_prepare_draft_area($draftitemid, null, 'mod_advwork', 'instructreviewers', 0);    // no context yet, itemid not used
            $data['instructreviewerseditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);

            $draftitemid = file_get_submitted_draft_itemid('conclusion');
            file_prepare_draft_area($draftitemid, null, 'mod_advwork', 'conclusion', 0);    // no context yet, itemid not used
            $data['conclusioneditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);
        }
    }

    /**
     * Set the grade item categories when editing an instance
     */
    public function definition_after_data() {

        $mform =& $this->_form;

        if ($id = $mform->getElementValue('update')) {
            $instance   = $mform->getElementValue('instance');

            $gradeitems = grade_item::fetch_all(array(
                'itemtype'      => 'mod',
                'itemmodule'    => 'advwork',
                'iteminstance'  => $instance,
                'courseid'      => $this->course->id));

            if (!empty($gradeitems)) {
                foreach ($gradeitems as $gradeitem) {
                    // here comes really crappy way how to set the value of the fields
                    // gradecategory and gradinggradecategory - grrr QuickForms
                    $decimalpoints = $gradeitem->get_decimals();
                    if ($gradeitem->itemnumber == 0) {
                        $submissiongradepass = $mform->getElement('submissiongradepass');
                        $submissiongradepass->setValue(format_float($gradeitem->gradepass, $decimalpoints));
                        $group = $mform->getElement('submissiongradegroup');
                        $elements = $group->getElements();
                        foreach ($elements as $element) {
                            if ($element->getName() == 'gradecategory') {
                                $element->setValue($gradeitem->categoryid);
                            }
                        }
                    } else if ($gradeitem->itemnumber == 1) {
                        $gradinggradepass = $mform->getElement('gradinggradepass');
                        $gradinggradepass->setValue(format_float($gradeitem->gradepass, $decimalpoints));
                        $group = $mform->getElement('gradinggradegroup');
                        $elements = $group->getElements();
                        foreach ($elements as $element) {
                            if ($element->getName() == 'gradinggradecategory') {
                                $element->setValue($gradeitem->categoryid);
                            }
                        }
                    }
                }
            }
        }

        parent::definition_after_data();
    }

    /**
     * Validates the form input
     *
     * @param array $data submitted data
     * @param array $files submitted files
     * @return array eventual errors indexed by the field name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // check the phases borders are valid
        if ($data['submissionstart'] > 0 and $data['submissionend'] > 0 and $data['submissionstart'] >= $data['submissionend']) {
            $errors['submissionend'] = get_string('submissionendbeforestart', 'mod_advwork');
        }
        if ($data['assessmentstart'] > 0 and $data['assessmentend'] > 0 and $data['assessmentstart'] >= $data['assessmentend']) {
            $errors['assessmentend'] = get_string('assessmentendbeforestart', 'mod_advwork');
        }

        // check the phases do not overlap
        if (max($data['submissionstart'], $data['submissionend']) > 0 and max($data['assessmentstart'], $data['assessmentend']) > 0) {
            $phasesubmissionend = max($data['submissionstart'], $data['submissionend']);
            $phaseassessmentstart = min($data['assessmentstart'], $data['assessmentend']);
            if ($phaseassessmentstart == 0) {
                $phaseassessmentstart = max($data['assessmentstart'], $data['assessmentend']);
            }
            if ($phasesubmissionend > 0 and $phaseassessmentstart > 0 and $phaseassessmentstart < $phasesubmissionend) {
                foreach (array('submissionend', 'submissionstart', 'assessmentstart', 'assessmentend') as $f) {
                    if ($data[$f] > 0) {
                        $errors[$f] = get_string('phasesoverlap', 'mod_advwork');
                        break;
                    }
                }
            }
        }

        // Check that the submission grade pass is a valid number.
        if (!empty($data['submissiongradepass'])) {
            $submissiongradefloat = unformat_float($data['submissiongradepass'], true);
            if ($submissiongradefloat === false) {
                $errors['submissiongradepass'] = get_string('err_numeric', 'form');
            } else {
                if ($submissiongradefloat > $data['grade']) {
                    $errors['submissiongradepass'] = get_string('gradepassgreaterthangrade', 'grades', $data['grade']);
                }
            }
        }

        // Check that the grade pass is a valid number.
        if (!empty($data['gradinggradepass'])) {
            $gradepassfloat = unformat_float($data['gradinggradepass'], true);
            if ($gradepassfloat === false) {
                $errors['gradinggradepass'] = get_string('err_numeric', 'form');
            } else {
                if ($gradepassfloat > $data['gradinggrade']) {
                    $errors['gradinggradepass'] = get_string('gradepassgreaterthangrade', 'grades', $data['gradinggrade']);
                }
            }
        }

        return $errors;
    }
}
