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
 * A form used by teachers to give feedback to reviewers on assessments
 *
 * @package    mod_advwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class advwork_feedbackreviewer_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        $current    = $this->_customdata['current'];
        $advwork   = $this->_customdata['advwork'];
        $editoropts = $this->_customdata['editoropts'];
        $options    = $this->_customdata['options'];

        $mform->addElement('header', 'assessmentsettings', get_string('assessmentsettings', 'advwork'));

        if (!empty($options['editableweight'])) {
            $mform->addElement('select', 'weight',
                    get_string('assessmentweight', 'advwork'), advwork::available_assessment_weights_list());
            $mform->setDefault('weight', 1);
        }

        $mform->addElement('static', 'gradinggrade', get_string('gradinggradecalculated', 'advwork'));
        if (!empty($options['overridablegradinggrade'])) {
            $grades = array('' => get_string('notoverridden', 'advwork'));
            for ($i = (int)$advwork->gradinggrade; $i >= 0; $i--) {
                $grades[$i] = $i;
            }
            $mform->addElement('select', 'gradinggradeover', get_string('gradinggradeover', 'advwork'), $grades);

            $mform->addElement('editor', 'feedbackreviewer_editor', get_string('feedbackreviewer', 'advwork'), null, $editoropts);
            $mform->setType('feedbackreviewer_editor', PARAM_RAW);
        }

        $mform->addElement('hidden', 'asid');
        $mform->setType('asid', PARAM_INT);

        $mform->addElement('submit', 'save', get_string('saveandclose', 'advwork'));

        $this->set_data($current);
    }

    function validation($data, $files) {
        global $CFG, $USER, $DB;

        $errors = parent::validation($data, $files);
        return $errors;
    }
}
