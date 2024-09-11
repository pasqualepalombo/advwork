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
 * @package   mod_advwork
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_advwork_activity_task
 */

/**
 * Structure step to restore one advwork activity
 */
class restore_advwork_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();

        $userinfo = $this->get_setting_value('userinfo'); // are we including userinfo?

        ////////////////////////////////////////////////////////////////////////
        // XML interesting paths - non-user data
        ////////////////////////////////////////////////////////////////////////

        // root element describing advwork instance
        $advwork = new restore_path_element('advwork', '/activity/advwork');
        $paths[] = $advwork;

        // Apply for 'advworkform' subplugins optional paths at advwork level
        $this->add_subplugin_structure('advworkform', $advwork);

        // Apply for 'advworkeval' subplugins optional paths at advwork level
        $this->add_subplugin_structure('advworkeval', $advwork);

        // example submissions
        $paths[] = new restore_path_element('advwork_examplesubmission',
                       '/activity/advwork/examplesubmissions/examplesubmission');

        // reference assessment of the example submission
        $referenceassessment = new restore_path_element('advwork_referenceassessment',
                                   '/activity/advwork/examplesubmissions/examplesubmission/referenceassessment');
        $paths[] = $referenceassessment;

        // Apply for 'advworkform' subplugins optional paths at referenceassessment level
        $this->add_subplugin_structure('advworkform', $referenceassessment);

        // End here if no-user data has been selected
        if (!$userinfo) {
            return $this->prepare_activity_structure($paths);
        }

        ////////////////////////////////////////////////////////////////////////
        // XML interesting paths - user data
        ////////////////////////////////////////////////////////////////////////

        // assessments of example submissions
        $exampleassessment = new restore_path_element('advwork_exampleassessment',
                                 '/activity/advwork/examplesubmissions/examplesubmission/exampleassessments/exampleassessment');
        $paths[] = $exampleassessment;

        // Apply for 'advworkform' subplugins optional paths at exampleassessment level
        $this->add_subplugin_structure('advworkform', $exampleassessment);

        // submissions
        $paths[] = new restore_path_element('advwork_submission', '/activity/advwork/submissions/submission');

        // allocated assessments
        $assessment = new restore_path_element('advwork_assessment',
                          '/activity/advwork/submissions/submission/assessments/assessment');
        $paths[] = $assessment;

        // Apply for 'advworkform' subplugins optional paths at assessment level
        $this->add_subplugin_structure('advworkform', $assessment);

        // aggregations of grading grades in this advwork
        $paths[] = new restore_path_element('advwork_aggregation', '/activity/advwork/aggregations/aggregation');

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_advwork($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        $data->submissionstart = $this->apply_date_offset($data->submissionstart);
        $data->submissionend = $this->apply_date_offset($data->submissionend);
        $data->assessmentstart = $this->apply_date_offset($data->assessmentstart);
        $data->assessmentend = $this->apply_date_offset($data->assessmentend);

        // insert the advwork record
        $newitemid = $DB->insert_record('advwork', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_advwork_examplesubmission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->advworkid = $this->get_new_parentid('advwork');
        $data->example = 1;
        $data->authorid = $this->task->get_userid();

        $newitemid = $DB->insert_record('advwork_submissions', $data);
        $this->set_mapping('advwork_examplesubmission', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_advwork_referenceassessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('advwork_examplesubmission');
        $data->reviewerid = $this->task->get_userid();

        $newitemid = $DB->insert_record('advwork_assessments', $data);
        $this->set_mapping('advwork_referenceassessment', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_advwork_exampleassessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('advwork_examplesubmission');
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid);

        $newitemid = $DB->insert_record('advwork_assessments', $data);
        $this->set_mapping('advwork_exampleassessment', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_advwork_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->advworkid = $this->get_new_parentid('advwork');
        $data->example = 0;
        $data->authorid = $this->get_mappingid('user', $data->authorid);

        $newitemid = $DB->insert_record('advwork_submissions', $data);
        $this->set_mapping('advwork_submission', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_advwork_assessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('advwork_submission');
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid);

        $newitemid = $DB->insert_record('advwork_assessments', $data);
        $this->set_mapping('advwork_assessment', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_advwork_aggregation($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->advworkid = $this->get_new_parentid('advwork');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('advwork_aggregations', $data);
        $this->set_mapping('advwork_aggregation', $oldid, $newitemid, true);
    }

    protected function after_execute() {
        // Add advwork related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_advwork', 'intro', null);
        $this->add_related_files('mod_advwork', 'instructauthors', null);
        $this->add_related_files('mod_advwork', 'instructreviewers', null);
        $this->add_related_files('mod_advwork', 'conclusion', null);

        // Add example submission related files, matching by 'advwork_examplesubmission' itemname
        $this->add_related_files('mod_advwork', 'submission_content', 'advwork_examplesubmission');
        $this->add_related_files('mod_advwork', 'submission_attachment', 'advwork_examplesubmission');

        // Add reference assessment related files, matching by 'advwork_referenceassessment' itemname
        $this->add_related_files('mod_advwork', 'overallfeedback_content', 'advwork_referenceassessment');
        $this->add_related_files('mod_advwork', 'overallfeedback_attachment', 'advwork_referenceassessment');

        // Add example assessment related files, matching by 'advwork_exampleassessment' itemname
        $this->add_related_files('mod_advwork', 'overallfeedback_content', 'advwork_exampleassessment');
        $this->add_related_files('mod_advwork', 'overallfeedback_attachment', 'advwork_exampleassessment');

        // Add submission related files, matching by 'advwork_submission' itemname
        $this->add_related_files('mod_advwork', 'submission_content', 'advwork_submission');
        $this->add_related_files('mod_advwork', 'submission_attachment', 'advwork_submission');

        // Add assessment related files, matching by 'advwork_assessment' itemname
        $this->add_related_files('mod_advwork', 'overallfeedback_content', 'advwork_assessment');
        $this->add_related_files('mod_advwork', 'overallfeedback_attachment', 'advwork_assessment');
    }
}
