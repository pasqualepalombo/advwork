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
 * This file defines a base class for all assessment forms
 *
 * @package    mod_advwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php'); // parent class definition

/**
 * Base class for all assessment forms
 *
 * This defines the common fields that all assessment forms need.
 * Strategies should define their own class that inherits from this one, and
 * implements the definition_inner() method.
 *
 * @uses moodleform
 */
class advwork_assessment_form extends moodleform {

    /**
     * Add the fields that are common for all grading strategies.
     *
     * If the strategy does not support all these fields, then you can override
     * this method and remove the ones you don't want with
     * $mform->removeElement().
     * Strategy subclassess should define their own fields in definition_inner()
     *
     * @return void
     */
    public function definition() {
        global $CFG;

        $mform          = $this->_form;
        $this->mode     = $this->_customdata['mode'];       // influences the save buttons
        $this->strategy = $this->_customdata['strategy'];   // instance of the strategy api class
        $this->advwork = $this->_customdata['advwork'];   // instance of the advwork api class
        $this->options  = $this->_customdata['options'];    // array with addiotional options

        // Disable shortforms
        $mform->setDisableShortforms();

        // add the strategy-specific fields
        $this->definition_inner($mform);

        // add the data common for all subplugins
        $mform->addElement('hidden', 'strategy', $this->advwork->strategy);
        $mform->setType('strategy', PARAM_PLUGIN);

        if ($this->advwork->overallfeedbackmode and $this->is_editable()) {
            $mform->addElement('header', 'overallfeedbacksection', get_string('overallfeedback', 'mod_advwork'));
            $mform->addElement('editor', 'feedbackauthor_editor', get_string('feedbackauthor', 'mod_advwork'), null,
                $this->advwork->overall_feedback_content_options());
            if ($this->advwork->overallfeedbackmode == 2) {
                $mform->addRule('feedbackauthor_editor', null, 'required', null, 'client');
            }
            if ($this->advwork->overallfeedbackfiles) {
                $mform->addElement('filemanager', 'feedbackauthorattachment_filemanager',
                    get_string('feedbackauthorattachment', 'mod_advwork'), null,
                    $this->advwork->overall_feedback_attachment_options());
            }
        }

        if (!empty($this->options['editableweight']) and $this->is_editable()) {
            $mform->addElement('header', 'assessmentsettings', get_string('assessmentweight', 'advwork'));
            $mform->addElement('select', 'weight',
                    get_string('assessmentweight', 'advwork'), advwork::available_assessment_weights_list());
            $mform->setDefault('weight', 1);
        }

        $buttonarray = array();
        if ($this->mode == 'preview') {
            $buttonarray[] = $mform->createElement('cancel', 'backtoeditform', get_string('backtoeditform', 'advwork'));
        }
        if ($this->mode == 'assessment') {
            if (!empty($this->options['pending'])) {
                $buttonarray[] = $mform->createElement('submit', 'saveandshownext', get_string('saveandshownext', 'advwork'));
            }
            $buttonarray[] = $mform->createElement('submit', 'saveandclose', get_string('saveandclose', 'advwork'));
            $buttonarray[] = $mform->createElement('submit', 'saveandcontinue', get_string('saveandcontinue', 'advwork'));
            $buttonarray[] = $mform->createElement('cancel');
        }
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Add any strategy specific form fields.
     *
     * @param stdClass $mform the form being built.
     */
    protected function definition_inner(&$mform) {
        // By default, do nothing.
    }

    /**
     * Is the form frozen (read-only)?
     *
     * @return boolean
     */
    public function is_editable() {
        return !$this->_form->isFrozen();
    }

    /**
     * Return the form custom data.
     *
     * @return array an array containing the custom data
     * @since  Moodle 3.4
     */
    public function get_customdata() {
        return $this->_customdata;
    }
}
