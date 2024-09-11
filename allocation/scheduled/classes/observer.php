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
 * Event observers for advworkallocation_scheduled.
 *
 * @package advworkallocation_scheduled
 * @copyright 2013 Adrian Greeve <adrian@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace advworkallocation_scheduled;
defined('MOODLE_INTERNAL') || die();

/**
 * Class for advworkallocation_scheduled observers.
 *
 * @package advworkallocation_scheduled
 * @copyright 2013 Adrian Greeve <adrian@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Triggered when the '\mod_advwork\event\course_module_viewed' event is triggered.
     *
     * This does the same job as {@link advworkallocation_scheduled_cron()} but for the
     * single advwork. The idea is that we do not need to wait for cron to execute.
     * Displaying the advwork main view.php can trigger the scheduled allocation, too.
     *
     * @param \mod_advwork\event\course_module_viewed $event
     * @return bool
     */
    public static function advwork_viewed($event) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/advwork/locallib.php');

        $advwork = $event->get_record_snapshot('advwork', $event->objectid);
        $course   = $event->get_record_snapshot('course', $event->courseid);
        $cm       = $event->get_record_snapshot('course_modules', $event->contextinstanceid);

        $advwork = new \advwork($advwork, $cm, $course);
        $now = time();

        // Non-expensive check to see if the scheduled allocation can even happen.
        if ($advwork->phase == \advwork::PHASE_SUBMISSION and $advwork->submissionend > 0 and $advwork->submissionend < $now) {

            // Make sure the scheduled allocation has been configured for this advwork, that it has not
            // been executed yet and that the passed advwork record is still valid.
            $sql = "SELECT a.id
                      FROM {advworkallocation_scheduled} a
                      JOIN {advwork} w ON a.advworkid = w.id
                     WHERE w.id = :advworkid
                           AND a.enabled = 1
                           AND w.phase = :phase
                           AND w.submissionend > 0
                           AND w.submissionend < :now
                           AND (a.timeallocated IS NULL OR a.timeallocated < w.submissionend)";
            $params = array('advworkid' => $advwork->id, 'phase' => \advwork::PHASE_SUBMISSION, 'now' => $now);

            if ($DB->record_exists_sql($sql, $params)) {
                // Allocate submissions for assessments.
                $allocator = $advwork->allocator_instance('scheduled');
                $result = $allocator->execute();
                // Todo inform the teachers about the results.
            }
        }
        return true;
    }
}
