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
 * A form used by teachers to give feedback to authors on their submission
 *
 * @package    mod_advwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class advwork_feedbackauthor_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        $current    = $this->_customdata['current'];
        $advwork   = $this->_customdata['advwork'];
        $editoropts = $this->_customdata['editoropts'];
        $options    = $this->_customdata['options'];

        $mform->addElement('header', 'feedbackauthorform', get_string('feedbackauthor', 'advwork'));

        if (!empty($options['editablepublished'])) {
            $mform->addElement('checkbox', 'published', get_string('publishsubmission', 'advwork'));
            $mform->addHelpButton('published', 'publishsubmission', 'advwork');
            $mform->setDefault('published', false);
        }

        $mform->addElement('static', 'grade', get_string('gradecalculated', 'advwork'));

        $grades = array('' => get_string('notoverridden', 'advwork'));
        for ($i = (int)$advwork->grade; $i >= 0; $i--) {
            $grades[$i] = $i;
        }
        $mform->addElement('select', 'gradeover', get_string('gradeover', 'advwork'), $grades);

        $mform->addElement('editor', 'feedbackauthor_editor', get_string('feedbackauthor', 'advwork'), null, $editoropts);
        $mform->setType('feedbackauthor_editor', PARAM_RAW);

        $mform->addElement('hidden', 'submissionid');
        $mform->setType('submissionid', PARAM_INT);

        $mform->addElement('submit', 'save', get_string('saveandclose', 'advwork'));

        $this->set_data($current);
    }

    function validation($data, $files) {
        global $CFG, $USER, $DB;

        $errors = parent::validation($data, $files);
        return $errors;
    }
}
