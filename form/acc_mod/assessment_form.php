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
 * This file defines an mform to assess a submission by acc_mod grading strategy
 *
 * @package    advworkform_acc_mod
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(__FILE__)).'/assessment_form.php');    // parent class definition

/**
 * Class representing a form for assessing submissions by acc_mod grading strategy
 *
 * @uses moodleform
 */
class advwork_acc_mod_assessment_form extends advwork_assessment_form {

    /**
     * Define the elements to be displayed at the form
     *
     * Called by the parent::definition()
     *
     * @return void
     */
    protected function definition_inner(&$mform) {
        $fields     = $this->_customdata['fields'];
        $current    = $this->_customdata['current'];
        $nodims     = $this->_customdata['nodims'];     // number of assessment dimensions

        $mform->addElement('hidden', 'nodims', $nodims);
        $mform->setType('nodims', PARAM_INT);

        // minimal grade value to select - used by the 'compare' rule below
        // (just an implementation detail to make the rule work, this element is
        // not processed by the server)
        $mform->addElement('hidden', 'minusone', -1);
        $mform->setType('minusone', PARAM_INT);

        for ($i = 0; $i < $nodims; $i++) {
            // dimension header
            $dimtitle = get_string('dimensionnumber', 'advworkform_acc_mod', $i+1);
            $mform->addElement('header', 'dimensionhdr__idx_'.$i, $dimtitle);

            // dimension id
            $mform->addElement('hidden', 'dimensionid__idx_'.$i, $fields->{'dimensionid__idx_'.$i});
            $mform->setType('dimensionid__idx_'.$i, PARAM_INT);

            // grade id
            $mform->addElement('hidden', 'gradeid__idx_'.$i);   // value set by set_data() later
            $mform->setType('gradeid__idx_'.$i, PARAM_INT);

            // dimension description
            $desc = '<div id="id_dim_'.$fields->{'dimensionid__idx_'.$i}.'_desc" class="fitem description acc_mod">'."\n";
            $desc .= format_text($fields->{'description__idx_'.$i}, $fields->{'description__idx_'.$i.'format'});
            $desc .= "\n</div>";
            $mform->addElement('html', $desc);

            // grade for this aspect
            $label = get_string('dimensiongradefor', 'advworkform_acc_mod', $dimtitle);
            $options = make_grades_menu($fields->{'grade__idx_' . $i});
            $options = array('-1' => get_string('choosedots')) + $options;
            $mform->addElement('select', 'grade__idx_' . $i, $label, $options);
            $mform->addRule(array('grade__idx_' . $i, 'minusone') , get_string('mustchoosegrade', 'advworkform_acc_mod'), 'compare', 'gt');

            // comment
            $label = get_string('dimensioncommentfor', 'advworkform_acc_mod', $dimtitle);
            //$mform->addElement('editor', 'peercomment__idx_' . $i, $label, null, array('maxfiles' => 0));
            $mform->addElement('textarea', 'peercomment__idx_' . $i, $label, array('cols' => 60, 'rows' => 5));
        }
        $this->set_data($current);
    }
}
