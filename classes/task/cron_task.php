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
 * A scheduled task for advwork cron.
 *
 * @package    mod_advwork
 * @copyright  2019 Simey Lameze <simey@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_advwork\task;

defined('MOODLE_INTERNAL') || die();

/**
 * The main scheduled task for the advwork.
 *
 * @package   mod_advwork
 * @copyright 2019 Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'mod_advwork');
    }

    /**
     * Run advwork cron.
     */
    public function execute() {
        global $CFG, $DB;

        $now = time();

        mtrace(' processing advwork subplugins ...');

        // Check if there are some advworks to switch into the assessment phase.
        $advworks = $DB->get_records_select("advwork",
            "phase = 20 AND phaseswitchassessment = 1 AND submissionend > 0 AND submissionend < ?", [$now]);

        if (!empty($advworks)) {
            mtrace('Processing automatic assessment phase switch in ' . count($advworks) . ' advwork(s) ... ', '');
            require_once($CFG->dirroot . '/mod/advwork/locallib.php');
            foreach ($advworks as $advwork) {
                $cm = get_coursemodule_from_instance('advwork', $advwork->id, $advwork->course, false, MUST_EXIST);
                $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
                $advwork = new \advwork($advwork, $cm, $course);
                $advwork->switch_phase(\advwork::PHASE_ASSESSMENT);

                $params = [
                    'objectid' => $advwork->id,
                    'context' => $advwork->context,
                    'courseid' => $advwork->course->id,
                    'other' => [
                        'targetadvworkphase' => $advwork->phase,
                        'previousadvworkphase' => \advwork::PHASE_SUBMISSION,
                    ]
                ];
                $event = \mod_advwork\event\phase_automatically_switched::create($params);
                $event->trigger();

                // Disable the automatic switching now so that it is not executed again by accident.
                // That can happen if the teacher changes the phase back to the submission one.
                $DB->set_field('advwork', 'phaseswitchassessment', 0, ['id' => $advwork->id]);
            }
            mtrace('done');
        }
    }
}
