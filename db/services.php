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
 * advwork external functions and service definitions.
 *
 * @package    mod_advwork
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.4
 */

defined('MOODLE_INTERNAL') || die;

$functions = array(

    'mod_advwork_get_advworks_by_courses' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'get_advworks_by_courses',
        'description'   => 'Returns a list of advworks in a provided list of courses, if no list is provided all advworks that
                            the user can view will be returned.',
        'type'          => 'read',
        'capabilities'  => 'mod/advwork:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'mod_advwork_get_advwork_access_information' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'get_advwork_access_information',
        'description'   => 'Return access information for a given advwork.',
        'type'          => 'read',
        'capabilities'  => 'mod/advwork:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'mod_advwork_get_user_plan' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'get_user_plan',
        'description'   => 'Return the planner information for the given user.',
        'type'          => 'read',
        'capabilities'  => 'mod/advwork:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'mod_advwork_view_advwork' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'view_advwork',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => 'mod/advwork:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_add_submission' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'add_submission',
        'description'   => 'Add a new submission to a given advwork.',
        'type'          => 'write',
        'capabilities'  => 'mod/advwork:submit',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_update_submission' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'update_submission',
        'description'   => 'Update the given submission.',
        'type'          => 'write',
        'capabilities'  => 'mod/advwork:submit',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_delete_submission' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'delete_submission',
        'description'   => 'Deletes the given submission.',
        'type'          => 'write',
        'capabilities'  => 'mod/advwork:submit',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_get_submissions' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'get_submissions',
        'description'   => 'Retrieves all the advwork submissions or the one done by the given user (except example submissions).',
        'type'          => 'read',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_get_submission' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'get_submission',
        'description'   => 'Retrieves the given submission.',
        'type'          => 'read',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_get_submission_assessments' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'get_submission_assessments',
        'description'   => 'Retrieves all the assessments of the given submission.',
        'type'          => 'read',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_get_assessment' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'get_assessment',
        'description'   => 'Retrieves the given assessment.',
        'type'          => 'read',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_get_assessment_form_definition' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'get_assessment_form_definition',
        'description'   => 'Retrieves the assessment form definition.',
        'type'          => 'read',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_get_reviewer_assessments' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'get_reviewer_assessments',
        'description'   => 'Retrieves all the assessments reviewed by the given user.',
        'type'          => 'read',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_update_assessment' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'update_assessment',
        'description'   => 'Add information to an allocated assessment.',
        'type'          => 'write',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_get_grades' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'get_grades',
        'description'   => 'Returns the assessment and submission grade for the given user.',
        'type'          => 'read',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_evaluate_assessment' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'evaluate_assessment',
        'description'   => 'Evaluates an assessment (used by teachers for provide feedback to the reviewer).',
        'type'          => 'write',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_get_grades_report' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'get_grades_report',
        'description'   => 'Retrieves the assessment grades report.',
        'type'          => 'read',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_view_submission' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'view_submission',
        'description'   => 'Trigger the submission viewed event.',
        'type'          => 'write',
        'capabilities'  => 'mod/advwork:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_advwork_evaluate_submission' => array(
        'classname'     => 'mod_advwork_external',
        'methodname'    => 'evaluate_submission',
        'description'   => 'Evaluates a submission (used by teachers for provide feedback or override the submission grade).',
        'type'          => 'write',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);
