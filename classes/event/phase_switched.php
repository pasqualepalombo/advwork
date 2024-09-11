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
 * The mod_advwork phase switched event.
 *
 * @package    mod_advwork
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_advwork\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The mod_advwork phase switched event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int advworkphase: advwork phase.
 * }
 *
 * @package    mod_advwork
 * @since      Moodle 2.7
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class phase_switched extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'advwork';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has switched the phase of the advwork with course module id " .
            "'$this->contextinstanceid' to '{$this->other['advworkphase']}'.";
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'advwork', 'update switch phase', 'view.php?id=' . $this->contextinstanceid,
                $this->other['advworkphase'], $this->contextinstanceid);
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventphaseswitched', 'mod_advwork');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/advwork/view.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['advworkphase'])) {
            throw new \coding_exception('The \'advworkphase\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'advwork', 'restore' => 'advwork');
    }

    public static function get_other_mapping() {
        // Nothing to map.
        return false;
    }
}
