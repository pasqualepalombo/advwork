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
 * Library of internal classes and functions for module advwork
 *
 * All the advwork specific functions, needed to implement the module
 * logic, should go to here. Instead of having bunch of function named
 * advwork_something() taking the advwork instance as the first
 * parameter, we use a class advwork that provides all methods.
 *
 * @package    mod_advwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/lib.php');     // we extend this library here
require_once($CFG->libdir . '/gradelib.php');   // we use some rounding and comparing routines here
require_once($CFG->libdir . '/filelib.php');
require_once (__DIR__.'/studentmodeling/studentmodelsrequest.php');
require_once (__DIR__.'/webserviceBN.php');
require_once (__DIR__.'/enum/basicenum.php');
require_once (__DIR__.'/enum/capabilities.php');
require_once (__DIR__.'/statistics.php');


/**
 * Full-featured advwork API
 *
 * This wraps the advwork database record with a set of methods that are called
 * from the module itself. The class should be initialized right after you get
 * $advwork, $cm and $course records at the begining of the script.
 */
class advwork {

    /** error status of the {@link self::add_allocation()} */
    const ALLOCATION_EXISTS             = -9999;

    /** the internal code of the advwork phases as are stored in the database */
    const PHASE_SETUP                   = 10;
    const PHASE_SUBMISSION              = 20;
    const PHASE_ASSESSMENT              = 30;
    const PHASE_EVALUATION              = 40;
    const PHASE_CLOSED                  = 50;

    /** the internal code of the examples modes as are stored in the database */
    const EXAMPLES_VOLUNTARY            = 0;
    const EXAMPLES_BEFORE_SUBMISSION    = 1;
    const EXAMPLES_BEFORE_ASSESSMENT    = 2;

    /** @var stdclass advwork record from database */
    public $dbrecord;

    /** @var cm_info course module record */
    public $cm;

    /** @var stdclass course record */
    public $course;

    /** @var stdclass context object */
    public $context;

    /** @var int advwork instance identifier */
    public $id;

    /** @var string advwork activity name */
    public $name;

    /** @var string introduction or description of the activity */
    public $intro;

    /** @var int format of the {@link $intro} */
    public $introformat;

    /** @var string instructions for the submission phase */
    public $instructauthors;

    /** @var int format of the {@link $instructauthors} */
    public $instructauthorsformat;

    /** @var string instructions for the assessment phase */
    public $instructreviewers;

    /** @var int format of the {@link $instructreviewers} */
    public $instructreviewersformat;

    /** @var int timestamp of when the module was modified */
    public $timemodified;

    /** @var int current phase of advwork, for example {@link advwork::PHASE_SETUP} */
    public $phase;

    /** @var bool optional feature: students practise evaluating on example submissions from teacher */
    public $useexamples;

    /** @var bool optional feature: students perform peer assessment of others' work (deprecated, consider always enabled) */
    public $usepeerassessment;

    /** @var bool optional feature: students perform self assessment of their own work */
    public $useselfassessment;

    /** @var float number (10, 5) unsigned, the maximum grade for submission */
    public $grade;

    /** @var float number (10, 5) unsigned, the maximum grade for assessment */
    public $gradinggrade;

    /** @var string type of the current grading strategy used in this advwork, for example 'accumulative' */
    public $strategy;

    /** @var string the name of the evaluation plugin to use for grading grades calculation */
    public $evaluation;

    /** @var int number of digits that should be shown after the decimal point when displaying grades */
    public $gradedecimals;

    /** @var int number of allowed submission attachments and the files embedded into submission */
    public $nattachments;

     /** @var string list of allowed file types that are allowed to be embedded into submission */
    public $submissionfiletypes = null;

    /** @var bool allow submitting the work after the deadline */
    public $latesubmissions;

    /** @var int maximum size of the one attached file in bytes */
    public $maxbytes;

    /** @var int mode of example submissions support, for example {@link advwork::EXAMPLES_VOLUNTARY} */
    public $examplesmode;

    /** @var int if greater than 0 then the submission is not allowed before this timestamp */
    public $submissionstart;

    /** @var int if greater than 0 then the submission is not allowed after this timestamp */
    public $submissionend;

    /** @var int if greater than 0 then the peer assessment is not allowed before this timestamp */
    public $assessmentstart;

    /** @var int if greater than 0 then the peer assessment is not allowed after this timestamp */
    public $assessmentend;

    /** @var bool automatically switch to the assessment phase after the submissions deadline */
    public $phaseswitchassessment;

    /** @var string conclusion text to be displayed at the end of the activity */
    public $conclusion;

    /** @var int format of the conclusion text */
    public $conclusionformat;

    /** @var int the mode of the overall feedback */
    public $overallfeedbackmode;

    /** @var int maximum number of overall feedback attachments */
    public $overallfeedbackfiles;

    /** @var string list of allowed file types that can be attached to the overall feedback */
    public $overallfeedbackfiletypes = null;

    /** @var int maximum size of one file attached to the overall feedback */
    public $overallfeedbackmaxbytes;

    /**
     * @var advwork_strategy grading strategy instance
     * Do not use directly, get the instance using {@link advwork::grading_strategy_instance()}
     */
    protected $strategyinstance = null;

    /**
     * @var advwork_evaluation grading evaluation instance
     * Do not use directly, get the instance using {@link advwork::grading_evaluation_instance()}
     */
    protected $evaluationinstance = null;

    /**
     * Initializes the advwork API instance using the data from DB
     *
     * Makes deep copy of all passed records properties.
     *
     * For unit testing only, $cm and $course may be set to null. This is so that
     * you can test without having any real database objects if you like. Not all
     * functions will work in this situation.
     *
     * @param stdClass $dbrecord advwork instance data from {advwork} table
     * @param stdClass|cm_info $cm Course module record
     * @param stdClass $course Course record from {course} table
     * @param stdClass $context The context of the advwork instance
     */
    public function __construct(stdclass $dbrecord, $cm, $course, stdclass $context=null) {
        $this->dbrecord = $dbrecord;
        foreach ($this->dbrecord as $field => $value) {
            if (property_exists('advwork', $field)) {
                $this->{$field} = $value;
            }
        }
        if (is_null($cm) || is_null($course)) {
            throw new coding_exception('Must specify $cm and $course');
        }
        $this->course = $course;
        if ($cm instanceof cm_info) {
            $this->cm = $cm;
        } else {
            $modinfo = get_fast_modinfo($course);
            $this->cm = $modinfo->get_cm($cm->id);
        }
        if (is_null($context)) {
            $this->context = context_module::instance($this->cm->id);
        } else {
            $this->context = $context;
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Static methods                                                             //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Return list of available allocation methods
     *
     * @return array Array ['string' => 'string'] of localized allocation method names
     */
    public static function installed_allocators() {
        $installed = core_component::get_plugin_list('advworkallocation');
        $forms = array();
        foreach ($installed as $allocation => $allocationpath) {
            if (file_exists($allocationpath . '/lib.php')) {
                $forms[$allocation] = get_string('pluginname', 'advworkallocation_' . $allocation);
            }
        }
        // usability - make sure that manual allocation appears the first
        if (isset($forms['manual'])) {
            $m = array('manual' => $forms['manual']);
            unset($forms['manual']);
            $forms = array_merge($m, $forms);
        }
        return $forms;
    }

    /**
     * Returns an array of options for the editors that are used for submitting and assessing instructions
     *
     * @param stdClass $context
     * @uses EDITOR_UNLIMITED_FILES hard-coded value for the 'maxfiles' option
     * @return array
     */
    public static function instruction_editors_options(stdclass $context) {
        return array('subdirs' => 1, 'maxbytes' => 0, 'maxfiles' => -1,
                     'changeformat' => 1, 'context' => $context, 'noclean' => 1, 'trusttext' => 0);
    }

    /**
     * Given the percent and the total, returns the number
     *
     * @param float $percent from 0 to 100
     * @param float $total   the 100% value
     * @return float
     */
    public static function percent_to_value($percent, $total) {
        if ($percent < 0 or $percent > 100) {
            throw new coding_exception('The percent can not be less than 0 or higher than 100');
        }

        return $total * $percent / 100;
    }

    /**
     * Returns an array of numeric values that can be used as maximum grades
     *
     * @return array Array of integers
     */
    public static function available_maxgrades_list() {
        $grades = array();
        for ($i=100; $i>=0; $i--) {
            $grades[$i] = $i;
        }
        return $grades;
    }

    /**
     * Returns the localized list of supported examples modes
     *
     * @return array
     */
    public static function available_example_modes_list() {
        $options = array();
        $options[self::EXAMPLES_VOLUNTARY]         = get_string('examplesvoluntary', 'advwork');
        $options[self::EXAMPLES_BEFORE_SUBMISSION] = get_string('examplesbeforesubmission', 'advwork');
        $options[self::EXAMPLES_BEFORE_ASSESSMENT] = get_string('examplesbeforeassessment', 'advwork');
        return $options;
    }

    /**
     * Returns the list of available grading strategy methods
     *
     * @return array ['string' => 'string']
     */
    public static function available_strategies_list() {
        $installed = core_component::get_plugin_list('advworkform');
        $forms = array();
        foreach ($installed as $strategy => $strategypath) {
            if (file_exists($strategypath . '/lib.php')) {
                $forms[$strategy] = get_string('pluginname', 'advworkform_' . $strategy);
            }
        }
        return $forms;
    }

    /**
     * Returns the list of available grading evaluation methods
     *
     * @return array of (string)name => (string)localized title
     */
    public static function available_evaluators_list() {
        $evals = array();
        foreach (core_component::get_plugin_list_with_file('advworkeval', 'lib.php', false) as $eval => $evalpath) {
            $evals[$eval] = get_string('pluginname', 'advworkeval_' . $eval);
        }
        return $evals;
    }

    /**
     * Return an array of possible values of assessment dimension weight
     *
     * @return array of integers 0, 1, 2, ..., 16
     */
    public static function available_dimension_weights_list() {
        $weights = array();
        for ($i=16; $i>=0; $i--) {
            $weights[$i] = $i;
        }
        return $weights;
    }

    /**
     * Return an array of possible values of assessment weight
     *
     * Note there is no real reason why the maximum value here is 16. It used to be 10 in
     * advwork 1.x and I just decided to use the same number as in the maximum weight of
     * a single assessment dimension.
     * The value looks reasonable, though. Teachers who would want to assign themselves
     * higher weight probably do not want peer assessment really...
     *
     * @return array of integers 0, 1, 2, ..., 16
     */
    public static function available_assessment_weights_list() {
        $weights = array();
        for ($i=16; $i>=0; $i--) {
            $weights[$i] = $i;
        }
        return $weights;
    }

    /**
     * Helper function returning the greatest common divisor
     *
     * @param int $a
     * @param int $b
     * @return int
     */
    public static function gcd($a, $b) {
        return ($b == 0) ? ($a):(self::gcd($b, $a % $b));
    }

    /**
     * Helper function returning the least common multiple
     *
     * @param int $a
     * @param int $b
     * @return int
     */
    public static function lcm($a, $b) {
        return ($a / self::gcd($a,$b)) * $b;
    }

    /**
     * Returns an object suitable for strings containing dates/times
     *
     * The returned object contains properties date, datefullshort, datetime, ... containing the given
     * timestamp formatted using strftimedate, strftimedatefullshort, strftimedatetime, ... from the
     * current lang's langconfig.php
     * This allows translators and administrators customize the date/time format.
     *
     * @param int $timestamp the timestamp in UTC
     * @return stdclass
     */
    public static function timestamp_formats($timestamp) {
        $formats = array('date', 'datefullshort', 'dateshort', 'datetime',
                'datetimeshort', 'daydate', 'daydatetime', 'dayshort', 'daytime',
                'monthyear', 'recent', 'recentfull', 'time');
        $a = new stdclass();
        foreach ($formats as $format) {
            $a->{$format} = userdate($timestamp, get_string('strftime'.$format, 'langconfig'));
        }
        $day = userdate($timestamp, '%Y%m%d', 99, false);
        $today = userdate(time(), '%Y%m%d', 99, false);
        $tomorrow = userdate(time() + DAYSECS, '%Y%m%d', 99, false);
        $yesterday = userdate(time() - DAYSECS, '%Y%m%d', 99, false);
        $distance = (int)round(abs(time() - $timestamp) / DAYSECS);
        if ($day == $today) {
            $a->distanceday = get_string('daystoday', 'advwork');
        } elseif ($day == $yesterday) {
            $a->distanceday = get_string('daysyesterday', 'advwork');
        } elseif ($day < $today) {
            $a->distanceday = get_string('daysago', 'advwork', $distance);
        } elseif ($day == $tomorrow) {
            $a->distanceday = get_string('daystomorrow', 'advwork');
        } elseif ($day > $today) {
            $a->distanceday = get_string('daysleft', 'advwork', $distance);
        }
        return $a;
    }

    /**
     * Converts the argument into an array (list) of file extensions.
     *
     * The list can be separated by whitespace, end of lines, commas colons and semicolons.
     * Empty values are not returned. Values are converted to lowercase.
     * Duplicates are removed. Glob evaluation is not supported.
     *
     * @deprecated since Moodle 3.4 MDL-56486 - please use the {@link core_form\filetypes_util}
     * @param string|array $extensions list of file extensions
     * @return array of strings
     */
    public static function normalize_file_extensions($extensions) {

        debugging('The method advwork::normalize_file_extensions() is deprecated.
            Please use the methods provided by the \core_form\filetypes_util class.', DEBUG_DEVELOPER);

        if ($extensions === '') {
            return array();
        }

        if (!is_array($extensions)) {
            $extensions = preg_split('/[\s,;:"\']+/', $extensions, null, PREG_SPLIT_NO_EMPTY);
        }

        foreach ($extensions as $i => $extension) {
            $extension = str_replace('*.', '', $extension);
            $extension = strtolower($extension);
            $extension = ltrim($extension, '.');
            $extension = trim($extension);
            $extensions[$i] = $extension;
        }

        foreach ($extensions as $i => $extension) {
            if (strpos($extension, '*') !== false or strpos($extension, '?') !== false) {
                unset($extensions[$i]);
            }
        }

        $extensions = array_filter($extensions, 'strlen');
        $extensions = array_keys(array_flip($extensions));

        foreach ($extensions as $i => $extension) {
            $extensions[$i] = '.'.$extension;
        }

        return $extensions;
    }

    /**
     * Cleans the user provided list of file extensions.
     *
     * @deprecated since Moodle 3.4 MDL-56486 - please use the {@link core_form\filetypes_util}
     * @param string $extensions
     * @return string
     */
    public static function clean_file_extensions($extensions) {

        debugging('The method advwork::clean_file_extensions() is deprecated.
            Please use the methods provided by the \core_form\filetypes_util class.', DEBUG_DEVELOPER);

        $extensions = self::normalize_file_extensions($extensions);

        foreach ($extensions as $i => $extension) {
            $extensions[$i] = ltrim($extension, '.');
        }

        return implode(', ', $extensions);
    }

    /**
     * Check given file types and return invalid/unknown ones.
     *
     * Empty whitelist is interpretted as "any extension is valid".
     *
     * @deprecated since Moodle 3.4 MDL-56486 - please use the {@link core_form\filetypes_util}
     * @param string|array $extensions list of file extensions
     * @param string|array $whitelist list of valid extensions
     * @return array list of invalid extensions not found in the whitelist
     */
    public static function invalid_file_extensions($extensions, $whitelist) {

        debugging('The method advwork::invalid_file_extensions() is deprecated.
            Please use the methods provided by the \core_form\filetypes_util class.', DEBUG_DEVELOPER);

        $extensions = self::normalize_file_extensions($extensions);
        $whitelist = self::normalize_file_extensions($whitelist);

        if (empty($extensions) or empty($whitelist)) {
            return array();
        }

        // Return those items from $extensions that are not present in $whitelist.
        return array_keys(array_diff_key(array_flip($extensions), array_flip($whitelist)));
    }

    /**
     * Is the file have allowed to be uploaded to the advwork?
     *
     * Empty whitelist is interpretted as "any file type is allowed" rather
     * than "no file can be uploaded".
     *
     * @deprecated since Moodle 3.4 MDL-56486 - please use the {@link core_form\filetypes_util}
     * @param string $filename the file name
     * @param string|array $whitelist list of allowed file extensions
     * @return false
     */
    public static function is_allowed_file_type($filename, $whitelist) {

        debugging('The method advwork::is_allowed_file_type() is deprecated.
            Please use the methods provided by the \core_form\filetypes_util class.', DEBUG_DEVELOPER);

        $whitelist = self::normalize_file_extensions($whitelist);

        if (empty($whitelist)) {
            return true;
        }

        $haystack = strrev(trim(strtolower($filename)));

        foreach ($whitelist as $extension) {
            if (strpos($haystack, strrev($extension)) === 0) {
                // The file name ends with the extension.
                return true;
            }
        }

        return false;
    }

    ////////////////////////////////////////////////////////////////////////////////
    // advwork API                                                               //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Fetches all enrolled users with the capability mod/advwork:submit in the current advwork
     *
     * The returned objects contain properties required by user_picture and are ordered by lastname, firstname.
     * Only users with the active enrolment are returned.
     *
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @param int $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set)
     * @param int $limitnum return a subset containing this number of records (optional, required if $limitfrom is set)
     * @return array array[userid] => stdClass
     */
    public function get_potential_authors($musthavesubmission=true, $groupid=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        list($sql, $params) = $this->get_users_with_capability_sql('mod/advwork:submit', $musthavesubmission, $groupid);

        if (empty($sql)) {
            return array();
        }

        list($sort, $sortparams) = users_order_by_sql('tmp');
        $sql = "SELECT *
                  FROM ($sql) tmp
              ORDER BY $sort";

        return $DB->get_records_sql($sql, array_merge($params, $sortparams), $limitfrom, $limitnum);
    }

    /**
     * Returns the total number of users that would be fetched by {@link self::get_potential_authors()}
     *
     * @param bool $musthavesubmission if true, count only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return int
     */
    public function count_potential_authors($musthavesubmission=true, $groupid=0) {
        global $DB;

        list($sql, $params) = $this->get_users_with_capability_sql('mod/advwork:submit', $musthavesubmission, $groupid);

        if (empty($sql)) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                  FROM ($sql) tmp";

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Fetches all enrolled users with the capability mod/advwork:peerassess in the current advwork
     *
     * The returned objects contain properties required by user_picture and are ordered by lastname, firstname.
     * Only users with the active enrolment are returned.
     *
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @param int $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set)
     * @param int $limitnum return a subset containing this number of records (optional, required if $limitfrom is set)
     * @return array array[userid] => stdClass
     */
    public function get_potential_reviewers($musthavesubmission=false, $groupid=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        list($sql, $params) = $this->get_users_with_capability_sql('mod/advwork:peerassess', $musthavesubmission, $groupid);

        if (empty($sql)) {
            return array();
        }

        list($sort, $sortparams) = users_order_by_sql('tmp');
        $sql = "SELECT *
                  FROM ($sql) tmp
              ORDER BY $sort";

        return $DB->get_records_sql($sql, array_merge($params, $sortparams), $limitfrom, $limitnum);
    }

    /**
     * Returns the total number of users that would be fetched by {@link self::get_potential_reviewers()}
     *
     * @param bool $musthavesubmission if true, count only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return int
     */
    public function count_potential_reviewers($musthavesubmission=false, $groupid=0) {
        global $DB;

        list($sql, $params) = $this->get_users_with_capability_sql('mod/advwork:peerassess', $musthavesubmission, $groupid);

        if (empty($sql)) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                  FROM ($sql) tmp";

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Fetches all enrolled users that are authors or reviewers (or both) in the current advwork
     *
     * The returned objects contain properties required by user_picture and are ordered by lastname, firstname.
     * Only users with the active enrolment are returned.
     *
     * @see self::get_potential_authors()
     * @see self::get_potential_reviewers()
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @param int $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set)
     * @param int $limitnum return a subset containing this number of records (optional, required if $limitfrom is set)
     * @return array array[userid] => stdClass
     */
    public function get_participants($musthavesubmission=false, $groupid=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        list($sql, $params) = $this->get_participants_sql($musthavesubmission, $groupid);

        if (empty($sql)) {
            return array();
        }

        list($sort, $sortparams) = users_order_by_sql('tmp');
        $sql = "SELECT *
                  FROM ($sql) tmp
              ORDER BY $sort";

        return $DB->get_records_sql($sql, array_merge($params, $sortparams), $limitfrom, $limitnum);
    }

    /**
     * Returns the total number of records that would be returned by {@link self::get_participants()}
     *
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return int
     */
    public function count_participants($musthavesubmission=false, $groupid=0) {
        global $DB;

        list($sql, $params) = $this->get_participants_sql($musthavesubmission, $groupid);

        if (empty($sql)) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                  FROM ($sql) tmp";

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Checks if the given user is an actively enrolled participant in the advwork
     *
     * @param int $userid, defaults to the current $USER
     * @return boolean
     */
    public function is_participant($userid=null) {
        global $USER, $DB;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        list($sql, $params) = $this->get_participants_sql();

        if (empty($sql)) {
            return false;
        }

        $sql = "SELECT COUNT(*)
                  FROM {user} uxx
                  JOIN ({$sql}) pxx ON uxx.id = pxx.id
                 WHERE uxx.id = :uxxid";
        $params['uxxid'] = $userid;

        if ($DB->count_records_sql($sql, $params)) {
            return true;
        }

        return false;
    }

    /**
     * Groups the given users by the group membership
     *
     * This takes the module grouping settings into account. If a grouping is
     * set, returns only groups withing the course module grouping. Always
     * returns group [0] with all the given users.
     *
     * @param array $users array[userid] => stdclass{->id ->lastname ->firstname}
     * @return array array[groupid][userid] => stdclass{->id ->lastname ->firstname}
     */
    public function get_grouped($users) {
        global $DB;
        global $CFG;

        $grouped = array();  // grouped users to be returned
        if (empty($users)) {
            return $grouped;
        }
        if ($this->cm->groupingid) {
            // Group advwork set to specified grouping - only consider groups
            // within this grouping, and leave out users who aren't members of
            // this grouping.
            $groupingid = $this->cm->groupingid;
            // All users that are members of at least one group will be
            // added into a virtual group id 0
            $grouped[0] = array();
        } else {
            $groupingid = 0;
            // there is no need to be member of a group so $grouped[0] will contain
            // all users
            $grouped[0] = $users;
        }
        $gmemberships = groups_get_all_groups($this->cm->course, array_keys($users), $groupingid,
                            'gm.id,gm.groupid,gm.userid');
        foreach ($gmemberships as $gmembership) {
            if (!isset($grouped[$gmembership->groupid])) {
                $grouped[$gmembership->groupid] = array();
            }
            $grouped[$gmembership->groupid][$gmembership->userid] = $users[$gmembership->userid];
            $grouped[0][$gmembership->userid] = $users[$gmembership->userid];
        }
        return $grouped;
    }

    /**
     * Returns the list of all allocations (i.e. assigned assessments) in the advwork
     *
     * Assessments of example submissions are ignored
     *
     * @return array
     */
    public function get_allocations() {
        global $DB;

        $sql = 'SELECT a.id, a.submissionid, a.reviewerid, s.authorid
                  FROM {advwork_assessments} a
            INNER JOIN {advwork_submissions} s ON (a.submissionid = s.id)
                 WHERE s.example = 0 AND s.advworkid = :advworkid';
        $params = array('advworkid' => $this->id);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns the total number of records that would be returned by {@link self::get_submissions()}
     *
     * @param mixed $authorid int|array|'all' If set to [array of] integer, return submission[s] of the given user[s] only
     * @param int $groupid If non-zero, return only submissions by authors in the specified group
     * @return int number of records
     */
    public function count_submissions($authorid='all', $groupid=0) {
        global $DB;

        $params = array('advworkid' => $this->id);
        $sql = "SELECT COUNT(s.id)
                  FROM {advwork_submissions} s
                  JOIN {user} u ON (s.authorid = u.id)";
        if ($groupid) {
            $sql .= " JOIN {groups_members} gm ON (gm.userid = u.id AND gm.groupid = :groupid)";
            $params['groupid'] = $groupid;
        }
        $sql .= " WHERE s.example = 0 AND s.advworkid = :advworkid";

        if ('all' === $authorid) {
            // no additional conditions
        } elseif (!empty($authorid)) {
            list($usql, $uparams) = $DB->get_in_or_equal($authorid, SQL_PARAMS_NAMED);
            $sql .= " AND authorid $usql";
            $params = array_merge($params, $uparams);
        } else {
            // $authorid is empty
            return 0;
        }

        return $DB->count_records_sql($sql, $params);
    }


    /**
     * Returns submissions from this advwork
     *
     * Fetches data from {advwork_submissions} and adds some useful information from other
     * tables. Does not return textual fields to prevent possible memory lack issues.
     *
     * @see self::count_submissions()
     * @param mixed $authorid int|array|'all' If set to [array of] integer, return submission[s] of the given user[s] only
     * @param int $groupid If non-zero, return only submissions by authors in the specified group
     * @param int $limitfrom Return a subset of records, starting at this point (optional)
     * @param int $limitnum Return a subset containing this many records in total (optional, required if $limitfrom is set)
     * @return array of records or an empty array
     */
    public function get_submissions($authorid='all', $groupid=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        $authorfields      = user_picture::fields('u', null, 'authoridx', 'author');
        $gradeoverbyfields = user_picture::fields('t', null, 'gradeoverbyx', 'over');
        $params            = array('advworkid' => $this->id);
        $sql = "SELECT s.id, s.advworkid, s.example, s.authorid, s.timecreated, s.timemodified,
                       s.title, s.grade, s.gradeover, s.gradeoverby, s.published,
                       $authorfields, $gradeoverbyfields
                  FROM {advwork_submissions} s
                  JOIN {user} u ON (s.authorid = u.id)";
        if ($groupid) {
            $sql .= " JOIN {groups_members} gm ON (gm.userid = u.id AND gm.groupid = :groupid)";
            $params['groupid'] = $groupid;
        }
        $sql .= " LEFT JOIN {user} t ON (s.gradeoverby = t.id)
                 WHERE s.example = 0 AND s.advworkid = :advworkid";

        if ('all' === $authorid) {
            // no additional conditions
        } elseif (!empty($authorid)) {
            list($usql, $uparams) = $DB->get_in_or_equal($authorid, SQL_PARAMS_NAMED);
            $sql .= " AND authorid $usql";
            $params = array_merge($params, $uparams);
        } else {
            // $authorid is empty
            return array();
        }
        list($sort, $sortparams) = users_order_by_sql('u');
        $sql .= " ORDER BY $sort";

        return $DB->get_records_sql($sql, array_merge($params, $sortparams), $limitfrom, $limitnum);
    }

    /**
     * Returns submissions from this advwork that are viewable by the current user (except example submissions).
     *
     * @param mixed $authorid int|array If set to [array of] integer, return submission[s] of the given user[s] only
     * @param int $groupid If non-zero, return only submissions by authors in the specified group. 0 for all groups.
     * @param int $limitfrom Return a subset of records, starting at this point (optional)
     * @param int $limitnum Return a subset containing this many records in total (optional, required if $limitfrom is set)
     * @return array of records and the total submissions count
     * @since  Moodle 3.4
     */
    public function get_visible_submissions($authorid = 0, $groupid = 0, $limitfrom = 0, $limitnum = 0) {
        global $DB, $USER;

        $submissions = array();
        $select = "SELECT s.*";
        $selectcount = "SELECT COUNT(s.id)";
        $from = " FROM {advwork_submissions} s";
        $params = array('advworkid' => $this->id);

        // Check if the passed group (or all groups when groupid is 0) is visible by the current user.
        if (!groups_group_visible($groupid, $this->course, $this->cm)) {
            return array($submissions, 0);
        }

        if ($groupid) {
            $from .= " JOIN {groups_members} gm ON (gm.userid = s.authorid AND gm.groupid = :groupid)";
            $params['groupid'] = $groupid;
        }
        $where = " WHERE s.advworkid = :advworkid AND s.example = 0";

        if (!has_capability('mod/advwork:viewallsubmissions', $this->context)) {
            // Check published submissions.
            $advworkclosed = $this->phase == self::PHASE_CLOSED;
            $canviewpublished = has_capability('mod/advwork:viewpublishedsubmissions', $this->context);
            if ($advworkclosed && $canviewpublished) {
                $published = " OR s.published = 1";
            } else {
                $published = '';
            }

            // Always get submissions I did or I provided feedback to.
            $where .= " AND (s.authorid = :authorid OR s.gradeoverby = :graderid $published)";
            $params['authorid'] = $USER->id;
            $params['graderid'] = $USER->id;
        }

        // Now, user filtering.
        if (!empty($authorid)) {
            list($usql, $uparams) = $DB->get_in_or_equal($authorid, SQL_PARAMS_NAMED);
            $where .= " AND s.authorid $usql";
            $params = array_merge($params, $uparams);
        }

        $order = " ORDER BY s.timecreated";

        $totalcount = $DB->count_records_sql($selectcount.$from.$where, $params);
        if ($totalcount) {
            $submissions = $DB->get_records_sql($select.$from.$where.$order, $params, $limitfrom, $limitnum);
        }
        return array($submissions, $totalcount);
    }


    /**
     * Returns a submission record with the author's data
     *
     * @param int $id submission id
     * @return stdclass
     */
    public function get_submission_by_id($id) {
        global $DB;

        // we intentionally check the advworkid here, too, so the advwork can't touch submissions
        // from other instances
        $authorfields      = user_picture::fields('u', null, 'authoridx', 'author');
        $gradeoverbyfields = user_picture::fields('g', null, 'gradeoverbyx', 'gradeoverby');
        $sql = "SELECT s.*, $authorfields, $gradeoverbyfields
                  FROM {advwork_submissions} s
            INNER JOIN {user} u ON (s.authorid = u.id)
             LEFT JOIN {user} g ON (s.gradeoverby = g.id)
                 WHERE s.example = 0 AND s.advworkid = :advworkid AND s.id = :id";
        $params = array('advworkid' => $this->id, 'id' => $id);
        return $DB->get_record_sql($sql, $params, MUST_EXIST);
    }

    /**
     * Returns a submission submitted by the given author
     *
     * @param int $id author id
     * @return stdclass|false
     */
    public function get_submission_by_author($authorid) {
        global $DB;

        if (empty($authorid)) {
            return false;
        }
        $authorfields      = user_picture::fields('u', null, 'authoridx', 'author');
        $gradeoverbyfields = user_picture::fields('g', null, 'gradeoverbyx', 'gradeoverby');
        $sql = "SELECT s.*, $authorfields, $gradeoverbyfields
                  FROM {advwork_submissions} s
            INNER JOIN {user} u ON (s.authorid = u.id)
             LEFT JOIN {user} g ON (s.gradeoverby = g.id)
                 WHERE s.example = 0 AND s.advworkid = :advworkid AND s.authorid = :authorid";
        $params = array('advworkid' => $this->id, 'authorid' => $authorid);
        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Returns published submissions with their authors data
     *
     * @return array of stdclass
     */
    public function get_published_submissions($orderby='finalgrade DESC') {
        global $DB;

        $authorfields = user_picture::fields('u', null, 'authoridx', 'author');
        $sql = "SELECT s.id, s.authorid, s.timecreated, s.timemodified,
                       s.title, s.grade, s.gradeover, COALESCE(s.gradeover,s.grade) AS finalgrade,
                       $authorfields
                  FROM {advwork_submissions} s
            INNER JOIN {user} u ON (s.authorid = u.id)
                 WHERE s.example = 0 AND s.advworkid = :advworkid AND s.published = 1
              ORDER BY $orderby";
        $params = array('advworkid' => $this->id);
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns full record of the given example submission
     *
     * @param int $id example submission od
     * @return object
     */
    public function get_example_by_id($id) {
        global $DB;
        return $DB->get_record('advwork_submissions',
                array('id' => $id, 'advworkid' => $this->id, 'example' => 1), '*', MUST_EXIST);
    }

    /**
     * Returns the list of example submissions in this advwork with reference assessments attached
     *
     * @return array of objects or an empty array
     * @see advwork::prepare_example_summary()
     */
    public function get_examples_for_manager() {
        global $DB;

        $sql = 'SELECT s.id, s.title,
                       a.id AS assessmentid, a.grade, a.gradinggrade
                  FROM {advwork_submissions} s
             LEFT JOIN {advwork_assessments} a ON (a.submissionid = s.id AND a.weight = 1)
                 WHERE s.example = 1 AND s.advworkid = :advworkid
              ORDER BY s.title';
        return $DB->get_records_sql($sql, array('advworkid' => $this->id));
    }

    /**
     * Returns the list of all example submissions in this advwork with the information of assessments done by the given user
     *
     * @param int $reviewerid user id
     * @return array of objects, indexed by example submission id
     * @see advwork::prepare_example_summary()
     */
    public function get_examples_for_reviewer($reviewerid) {
        global $DB;

        if (empty($reviewerid)) {
            return false;
        }
        $sql = 'SELECT s.id, s.title,
                       a.id AS assessmentid, a.grade, a.gradinggrade
                  FROM {advwork_submissions} s
             LEFT JOIN {advwork_assessments} a ON (a.submissionid = s.id AND a.reviewerid = :reviewerid AND a.weight = 0)
                 WHERE s.example = 1 AND s.advworkid = :advworkid
              ORDER BY s.title';
        return $DB->get_records_sql($sql, array('advworkid' => $this->id, 'reviewerid' => $reviewerid));
    }

    /**
     * Prepares renderable submission component
     *
     * @param stdClass $record required by {@see advwork_submission}
     * @param bool $showauthor show the author-related information
     * @return advwork_submission
     */
    public function prepare_submission(stdClass $record, $showauthor = false) {

        $submission         = new advwork_submission($this, $record, $showauthor);
        $submission->url    = $this->submission_url($record->id);

        return $submission;
    }

    /**
     * Prepares renderable submission summary component
     *
     * @param stdClass $record required by {@see advwork_submission_summary}
     * @param bool $showauthor show the author-related information
     * @return advwork_submission_summary
     */
    public function prepare_submission_summary(stdClass $record, $showauthor = false) {

        $summary        = new advwork_submission_summary($this, $record, $showauthor);
        $summary->url   = $this->submission_url($record->id);

        return $summary;
    }

    /**
     * Prepares renderable example submission component
     *
     * @param stdClass $record required by {@see advwork_example_submission}
     * @return advwork_example_submission
     */
    public function prepare_example_submission(stdClass $record) {

        $example = new advwork_example_submission($this, $record);

        return $example;
    }

    /**
     * Prepares renderable example submission summary component
     *
     * If the example is editable, the caller must set the 'editable' flag explicitly.
     *
     * @param stdClass $example as returned by {@link advwork::get_examples_for_manager()} or {@link advwork::get_examples_for_reviewer()}
     * @return advwork_example_submission_summary to be rendered
     */
    public function prepare_example_summary(stdClass $example) {

        $summary = new advwork_example_submission_summary($this, $example);

        if (is_null($example->grade)) {
            $summary->status = 'notgraded';
            $summary->assesslabel = get_string('assess', 'advwork');
        } else {
            $summary->status = 'graded';
            $summary->assesslabel = get_string('reassess', 'advwork');
        }

        $summary->gradeinfo           = new stdclass();
        $summary->gradeinfo->received = $this->real_grade($example->grade);
        $summary->gradeinfo->max      = $this->real_grade(100);

        $summary->url       = new moodle_url($this->exsubmission_url($example->id));
        $summary->editurl   = new moodle_url($this->exsubmission_url($example->id), array('edit' => 'on'));
        $summary->assessurl = new moodle_url($this->exsubmission_url($example->id), array('assess' => 'on', 'sesskey' => sesskey()));

        return $summary;
    }

    /**
     * Prepares renderable assessment component
     *
     * The $options array supports the following keys:
     * showauthor - should the author user info be available for the renderer
     * showreviewer - should the reviewer user info be available for the renderer
     * showform - show the assessment form if it is available
     * showweight - should the assessment weight be available for the renderer
     *
     * @param stdClass $record as returned by eg {@link self::get_assessment_by_id()}
     * @param advwork_assessment_form|null $form as returned by {@link advwork_strategy::get_assessment_form()}
     * @param array $options
     * @return advwork_assessment
     */
    public function prepare_assessment(stdClass $record, $form, array $options = array()) {

        $assessment             = new advwork_assessment($this, $record, $options);
        $assessment->url        = $this->assess_url($record->id);
        $assessment->maxgrade   = $this->real_grade(100);

        if (!empty($options['showform']) and !($form instanceof advwork_assessment_form)) {
            debugging('Not a valid instance of advwork_assessment_form supplied', DEBUG_DEVELOPER);
        }

        if (!empty($options['showform']) and ($form instanceof advwork_assessment_form)) {
            $assessment->form = $form;
        }

        if (empty($options['showweight'])) {
            $assessment->weight = null;
        }

        if (!is_null($record->grade)) {
            $assessment->realgrade = $this->real_grade($record->grade);
        }

        return $assessment;
    }

    /**
     * Prepares renderable example submission's assessment component
     *
     * The $options array supports the following keys:
     * showauthor - should the author user info be available for the renderer
     * showreviewer - should the reviewer user info be available for the renderer
     * showform - show the assessment form if it is available
     *
     * @param stdClass $record as returned by eg {@link self::get_assessment_by_id()}
     * @param advwork_assessment_form|null $form as returned by {@link advwork_strategy::get_assessment_form()}
     * @param array $options
     * @return advwork_example_assessment
     */
    public function prepare_example_assessment(stdClass $record, $form = null, array $options = array()) {

        $assessment             = new advwork_example_assessment($this, $record, $options);
        $assessment->url        = $this->exassess_url($record->id);
        $assessment->maxgrade   = $this->real_grade(100);

        if (!empty($options['showform']) and !($form instanceof advwork_assessment_form)) {
            debugging('Not a valid instance of advwork_assessment_form supplied', DEBUG_DEVELOPER);
        }

        if (!empty($options['showform']) and ($form instanceof advwork_assessment_form)) {
            $assessment->form = $form;
        }

        if (!is_null($record->grade)) {
            $assessment->realgrade = $this->real_grade($record->grade);
        }

        $assessment->weight = null;

        return $assessment;
    }

    /**
     * Prepares renderable example submission's reference assessment component
     *
     * The $options array supports the following keys:
     * showauthor - should the author user info be available for the renderer
     * showreviewer - should the reviewer user info be available for the renderer
     * showform - show the assessment form if it is available
     *
     * @param stdClass $record as returned by eg {@link self::get_assessment_by_id()}
     * @param advwork_assessment_form|null $form as returned by {@link advwork_strategy::get_assessment_form()}
     * @param array $options
     * @return advwork_example_reference_assessment
     */
    public function prepare_example_reference_assessment(stdClass $record, $form = null, array $options = array()) {

        $assessment             = new advwork_example_reference_assessment($this, $record, $options);
        $assessment->maxgrade   = $this->real_grade(100);

        if (!empty($options['showform']) and !($form instanceof advwork_assessment_form)) {
            debugging('Not a valid instance of advwork_assessment_form supplied', DEBUG_DEVELOPER);
        }

        if (!empty($options['showform']) and ($form instanceof advwork_assessment_form)) {
            $assessment->form = $form;
        }

        if (!is_null($record->grade)) {
            $assessment->realgrade = $this->real_grade($record->grade);
        }

        $assessment->weight = null;

        return $assessment;
    }

    /**
     * Removes the submission and all relevant data
     *
     * @param stdClass $submission record to delete
     * @return void
     */
    public function delete_submission(stdclass $submission) {
        global $DB;

        $assessments = $DB->get_records('advwork_assessments', array('submissionid' => $submission->id), '', 'id');
        $this->delete_assessment(array_keys($assessments));

        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'mod_advwork', 'submission_content', $submission->id);
        $fs->delete_area_files($this->context->id, 'mod_advwork', 'submission_attachment', $submission->id);

        $DB->delete_records('advwork_submissions', array('id' => $submission->id));

        // Event information.
        $params = array(
            'context' => $this->context,
            'courseid' => $this->course->id,
            'relateduserid' => $submission->authorid,
            'other' => array(
                'submissiontitle' => $submission->title
            )
        );
        $params['objectid'] = $submission->id;
        $event = \mod_advwork\event\submission_deleted::create($params);
        $event->add_record_snapshot('advwork', $this->dbrecord);
        $event->trigger();
    }

    /**
     * Returns the list of all assessments in the advwork with some data added
     *
     * Fetches data from {advwork_assessments} and adds some useful information from other
     * tables. The returned object does not contain textual fields (i.e. comments) to prevent memory
     * lack issues.
     *
     * @return array [assessmentid] => assessment stdclass
     */
    public function get_all_assessments() {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        list($sort, $params) = users_order_by_sql('reviewer');
        $sql = "SELECT a.id, a.submissionid, a.reviewerid, a.timecreated, a.timemodified,
                       a.grade, a.gradinggrade, a.gradinggradeover, a.gradinggradeoverby,
                       $reviewerfields, $authorfields, $overbyfields,
                       s.title
                  FROM {advwork_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {advwork_submissions} s ON (a.submissionid = s.id)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.advworkid = :advworkid AND s.example = 0
              ORDER BY $sort";
        $params['advworkid'] = $this->id;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get the complete information about the given assessment
     *
     * @param int $id Assessment ID
     * @return stdclass
     */
    public function get_assessment_by_id($id) {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        $sql = "SELECT a.*, s.title, $reviewerfields, $authorfields, $overbyfields
                  FROM {advwork_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {advwork_submissions} s ON (a.submissionid = s.id)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE a.id = :id AND s.advworkid = :advworkid";
        $params = array('id' => $id, 'advworkid' => $this->id);

        return $DB->get_record_sql($sql, $params, MUST_EXIST);
    }

    /**
     * Get the complete information about the user's assessment of the given submission
     *
     * @param int $sid submission ID
     * @param int $uid user ID of the reviewer
     * @return false|stdclass false if not found, stdclass otherwise
     */
    public function get_assessment_of_submission_by_user($submissionid, $reviewerid) {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        $sql = "SELECT a.*, s.title, $reviewerfields, $authorfields, $overbyfields
                  FROM {advwork_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {advwork_submissions} s ON (a.submissionid = s.id AND s.example = 0)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.id = :sid AND reviewer.id = :rid AND s.advworkid = :advworkid";
        $params = array('sid' => $submissionid, 'rid' => $reviewerid, 'advworkid' => $this->id);

        return $DB->get_record_sql($sql, $params, IGNORE_MISSING);
    }

    /**
     * Get the complete information about all assessments of the given submission
     *
     * @param int $submissionid
     * @return array
     */
    public function get_assessments_of_submission($submissionid) {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        list($sort, $params) = users_order_by_sql('reviewer');
        $sql = "SELECT a.*, s.title, $reviewerfields, $overbyfields
                  FROM {advwork_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {advwork_submissions} s ON (a.submissionid = s.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.example = 0 AND s.id = :submissionid AND s.advworkid = :advworkid
              ORDER BY $sort";
        $params['submissionid'] = $submissionid;
        $params['advworkid']   = $this->id;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get the complete information about all assessments allocated to the given reviewer
     *
     * @param int $reviewerid
     * @return array
     */
    public function get_assessments_by_reviewer($reviewerid, $nextbestsubmissiontograde = null) {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');

        $sql = "SELECT a.*, $reviewerfields, $authorfields, $overbyfields,
                       s.id AS submissionid, s.title AS submissiontitle, s.timecreated AS submissioncreated,
                       s.timemodified AS submissionmodified
                  FROM {advwork_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {advwork_submissions} s ON (a.submissionid = s.id)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.example = 0 AND reviewer.id = :reviewerid AND s.advworkid = :advworkid";
        $params = array('reviewerid' => $reviewerid, 'advworkid' => $this->id);

        // the next best submission to be graded by the teacher has priority and should be displayed in the top of the list
        if(!empty($nextbestsubmissiontograde)) {
            $sql .= " ORDER BY s.id = :nextbestsubmissiontograde DESC";
            $params["nextbestsubmissiontograde"] = $nextbestsubmissiontograde;
        }

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get the complete information about a user
     *
     * @param int $userid           Id of the user
     * @return the user
     */
    public function get_user_information($userid) {
        global $DB;

        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');

        $sql = "SELECT $authorfields
                  FROM {user} author
                 WHERE author.id = :userid";
        $params = array('userid' => $userid);

        return reset($DB->get_records_sql($sql, $params));
    }

    /**
     * Get allocated assessments not graded yet by the given reviewer
     *
     * @see self::get_assessments_by_reviewer()
     * @param int $reviewerid the reviewer id
     * @param null|int|array $exclude optional assessment id (or list of them) to be excluded
     * @return array
     */
    public function get_pending_assessments_by_reviewer($reviewerid, $exclude = null) {

        $assessments = $this->get_assessments_by_reviewer($reviewerid);

        foreach ($assessments as $id => $assessment) {
            if (!is_null($assessment->grade)) {
                unset($assessments[$id]);
                continue;
            }
            if (!empty($exclude)) {
                if (is_array($exclude) and in_array($id, $exclude)) {
                    unset($assessments[$id]);
                    continue;
                } else if ($id == $exclude) {
                    unset($assessments[$id]);
                    continue;
                }
            }
        }

        return $assessments;
    }

    /**
     * Allocate a submission to a user for review
     *
     * @param stdClass $submission Submission object with at least id property
     * @param int $reviewerid User ID
     * @param int $weight of the new assessment, from 0 to 16
     * @param bool $bulk repeated inserts into DB expected
     * @return int ID of the new assessment or an error code {@link self::ALLOCATION_EXISTS} if the allocation already exists
     */
    public function add_allocation(stdclass $submission, $reviewerid, $weight=1, $bulk=false) {
        global $DB;

        if ($DB->record_exists('advwork_assessments', array('submissionid' => $submission->id, 'reviewerid' => $reviewerid))) {
            return self::ALLOCATION_EXISTS;
        }

        $weight = (int)$weight;
        if ($weight < 0) {
            $weight = 0;
        }
        if ($weight > 16) {
            $weight = 16;
        }

        $now = time();
        $assessment = new stdclass();
        $assessment->submissionid           = $submission->id;
        $assessment->reviewerid             = $reviewerid;
        $assessment->timecreated            = $now;         // do not set timemodified here
        $assessment->weight                 = $weight;
        $assessment->feedbackauthorformat   = editors_get_preferred_format();
        $assessment->feedbackreviewerformat = editors_get_preferred_format();

        return $DB->insert_record('advwork_assessments', $assessment, true, $bulk);
    }

    /**
     * Delete assessment record or records.
     *
     * Removes associated records from the advwork_grades table, too.
     *
     * @param int|array $id assessment id or array of assessments ids
     * @todo Give grading strategy plugins a chance to clean up their data, too.
     * @return bool true
     */
    public function delete_assessment($id) {
        global $DB;

        if (empty($id)) {
            return true;
        }

        $fs = get_file_storage();

        if (is_array($id)) {
            $DB->delete_records_list('advwork_grades', 'assessmentid', $id);
            foreach ($id as $itemid) {
                $fs->delete_area_files($this->context->id, 'mod_advwork', 'overallfeedback_content', $itemid);
                $fs->delete_area_files($this->context->id, 'mod_advwork', 'overallfeedback_attachment', $itemid);
            }
            $DB->delete_records_list('advwork_assessments', 'id', $id);

        } else {
            $DB->delete_records('advwork_grades', array('assessmentid' => $id));
            $fs->delete_area_files($this->context->id, 'mod_advwork', 'overallfeedback_content', $id);
            $fs->delete_area_files($this->context->id, 'mod_advwork', 'overallfeedback_attachment', $id);
            $DB->delete_records('advwork_assessments', array('id' => $id));
        }

        return true;
    }

    /**
     * Returns instance of grading strategy class
     *
     * @return stdclass Instance of a grading strategy
     */
    public function grading_strategy_instance() {
        global $CFG;    // because we require other libs here

        if (is_null($this->strategyinstance)) {
            $strategylib = __DIR__ . '/form/' . $this->strategy . '/lib.php';
            if (is_readable($strategylib)) {
                require_once($strategylib);
            } else {
                throw new coding_exception('the grading forms subplugin must contain library ' . $strategylib);
            }
            $classname = 'advwork_' . $this->strategy . '_strategy';
            $this->strategyinstance = new $classname($this);
            if (!in_array('advwork_strategy', class_implements($this->strategyinstance))) {
                throw new coding_exception($classname . ' does not implement advwork_strategy interface');
            }
        }
        return $this->strategyinstance;
    }

    /**
     * Sets the current evaluation method to the given plugin.
     *
     * @param string $method the name of the advworkeval subplugin
     * @return bool true if successfully set
     * @throws coding_exception if attempting to set a non-installed evaluation method
     */
    public function set_grading_evaluation_method($method) {
        global $DB;

        $evaluationlib = __DIR__ . '/eval/' . $method . '/lib.php';

        if (is_readable($evaluationlib)) {
            $this->evaluationinstance = null;
            $this->evaluation = $method;
            $DB->set_field('advwork', 'evaluation', $method, array('id' => $this->id));
            return true;
        }

        throw new coding_exception('Attempt to set a non-existing evaluation method.');
    }

    /**
     * Returns instance of grading evaluation class
     *
     * @return stdclass Instance of a grading evaluation
     */
    public function grading_evaluation_instance() {
        global $CFG;    // because we require other libs here

        if (is_null($this->evaluationinstance)) {
            if (empty($this->evaluation)) {
                $this->evaluation = 'best';
            }
            $evaluationlib = __DIR__ . '/eval/' . $this->evaluation . '/lib.php';
            if (is_readable($evaluationlib)) {
                require_once($evaluationlib);
            } else {
                // Fall back in case the subplugin is not available.
                $this->evaluation = 'best';
                $evaluationlib = __DIR__ . '/eval/' . $this->evaluation . '/lib.php';
                if (is_readable($evaluationlib)) {
                    require_once($evaluationlib);
                } else {
                    // Fall back in case the subplugin is not available any more.
                    throw new coding_exception('Missing default grading evaluation library ' . $evaluationlib);
                }
            }
            $classname = 'advwork_' . $this->evaluation . '_evaluation';
            $this->evaluationinstance = new $classname($this);
            if (!in_array('advwork_evaluation', class_parents($this->evaluationinstance))) {
                throw new coding_exception($classname . ' does not extend advwork_evaluation class');
            }
        }
        return $this->evaluationinstance;
    }

    /**
     * Returns instance of submissions allocator
     *
     * @param string $method The name of the allocation method, must be PARAM_ALPHA
     * @return stdclass Instance of submissions allocator
     */
    public function allocator_instance($method) {
        global $CFG;    // because we require other libs here

        $allocationlib = __DIR__ . '/allocation/' . $method . '/lib.php';
        if (is_readable($allocationlib)) {
            require_once($allocationlib);
        } else {
            throw new coding_exception('Unable to find the allocation library ' . $allocationlib);
        }
        $classname = 'advwork_' . $method . '_allocator';
        return new $classname($this);
    }

    /**
     * @return moodle_url of this advwork's view page
     */
    public function view_url() {
        global $CFG;
        return new moodle_url('/mod/advwork/view.php', array('id' => $this->cm->id));
    }

    /**
     * @return moodle_url of the page for editing this advwork's grading form
     */
    public function editform_url() {
        global $CFG;
        return new moodle_url('/mod/advwork/editform.php', array('cmid' => $this->cm->id));
    }

    /**
     * @return moodle_url of the page for previewing this advwork's grading form
     */
    public function previewform_url() {
        global $CFG;
        return new moodle_url('/mod/advwork/editformpreview.php', array('cmid' => $this->cm->id));
    }

    /**
     * @param int $assessmentid The ID of assessment record
     * @return moodle_url of the assessment page
     */
    public function assess_url($assessmentid) {
        global $CFG;
        $assessmentid = clean_param($assessmentid, PARAM_INT);
        return new moodle_url('/mod/advwork/assessment.php', array('asid' => $assessmentid));
    }

    /**
     * @param int $assessmentid The ID of assessment record
     * @return moodle_url of the example assessment page
     */
    public function exassess_url($assessmentid) {
        global $CFG;
        $assessmentid = clean_param($assessmentid, PARAM_INT);
        return new moodle_url('/mod/advwork/exassessment.php', array('asid' => $assessmentid));
    }

    /**
     * @return moodle_url of the page to view a submission, defaults to the own one
     */
    public function submission_url($id=null) {
        global $CFG;
        return new moodle_url('/mod/advwork/submission.php', array('cmid' => $this->cm->id, 'id' => $id));
    }

    /**
     * @return moodle_url of the page to view the general student model for the current user
     */
    public function general_student_model_url($id) {
        global $CFG;
        return new moodle_url('/mod/advwork/studentmodeling/generalstudentmodel.php', array('id' => $id));
    }

    /**
     * @return moodle_url of the page to view the general student model for the current teacher
     */
    public function general_student_model_url_teacher($id) {
        global $CFG;
        return new moodle_url('/mod/advwork/studentmodeling/teacher/generalstudentmodel.php', array('id' => $id));
    }


    /**
     * @param int $id example submission id
     * @return moodle_url of the page to view an example submission
     */
    public function exsubmission_url($id) {
        global $CFG;
        return new moodle_url('/mod/advwork/exsubmission.php', array('cmid' => $this->cm->id, 'id' => $id));
    }

    /**
     * @param int $sid submission id
     * @param array $aid of int assessment ids
     * @return moodle_url of the page to compare assessments of the given submission
     */
    public function compare_url($sid, array $aids) {
        global $CFG;

        $url = new moodle_url('/mod/advwork/compare.php', array('cmid' => $this->cm->id, 'sid' => $sid));
        $i = 0;
        foreach ($aids as $aid) {
            $url->param("aid{$i}", $aid);
            $i++;
        }
        return $url;
    }

    /**
     * @param int $sid submission id
     * @param int $aid assessment id
     * @return moodle_url of the page to compare the reference assessments of the given example submission
     */
    public function excompare_url($sid, $aid) {
        global $CFG;
        return new moodle_url('/mod/advwork/excompare.php', array('cmid' => $this->cm->id, 'sid' => $sid, 'aid' => $aid));
    }

    /**
     * @return moodle_url of the mod_edit form
     */
    public function updatemod_url() {
        global $CFG;
        return new moodle_url('/course/modedit.php', array('update' => $this->cm->id, 'return' => 1));
    }
	
	/* @return on the same page and creating, if needed, the virtual students #TODO */
	public function createclasssimulation_url() {
        global $CFG;
        return new moodle_url('/mod/advwork/simulationclass.php', array('id' => $this->cm->id));
        }

    /**
     * @param string $method allocation method
     * @return moodle_url to the allocation page
     */
    public function allocation_url($method=null) {
        global $CFG;
        $params = array('cmid' => $this->cm->id);
        if (!empty($method)) {
            $params['method'] = $method;
        }
        return new moodle_url('/mod/advwork/allocation.php', $params);
    }

    /**
     * @param int $phasecode The internal phase code
     * @return moodle_url of the script to change the current phase to $phasecode
     */
    public function switchphase_url($phasecode) {
        global $CFG;
        $phasecode = clean_param($phasecode, PARAM_INT);
        return new moodle_url('/mod/advwork/switchphase.php', array('cmid' => $this->cm->id, 'phase' => $phasecode));
    }

    /**
     * @return moodle_url to the aggregation page
     */
    public function aggregate_url() {
        global $CFG;
        return new moodle_url('/mod/advwork/aggregate.php', array('cmid' => $this->cm->id));
    }

    /**
     * @return moodle_url of this advwork's toolbox page
     */
    public function toolbox_url($tool) {
        global $CFG;
        return new moodle_url('/mod/advwork/toolbox.php', array('id' => $this->cm->id, 'tool' => $tool));
    }

    /**
     * advwork wrapper around {@see add_to_log()}
     * @deprecated since 2.7 Please use the provided event classes for logging actions.
     *
     * @param string $action to be logged
     * @param moodle_url $url absolute url as returned by {@see advwork::submission_url()} and friends
     * @param mixed $info additional info, usually id in a table
     * @param bool $return true to return the arguments for add_to_log.
     * @return void|array array of arguments for add_to_log if $return is true
     */
    public function log($action, moodle_url $url = null, $info = null, $return = false) {
        debugging('The log method is now deprecated, please use event classes instead', DEBUG_DEVELOPER);

        if (is_null($url)) {
            $url = $this->view_url();
        }

        if (is_null($info)) {
            $info = $this->id;
        }

        $logurl = $this->log_convert_url($url);
        $args = array($this->course->id, 'advwork', $action, $logurl, $info, $this->cm->id);
        if ($return) {
            return $args;
        }
        call_user_func_array('add_to_log', $args);
    }

    /**
     * Is the given user allowed to create their submission?
     *
     * @param int $userid
     * @return bool
     */
    public function creating_submission_allowed($userid) {

        $now = time();
        $ignoredeadlines = has_capability('mod/advwork:ignoredeadlines', $this->context, $userid);

        if ($this->latesubmissions) {
            if ($this->phase != self::PHASE_SUBMISSION and $this->phase != self::PHASE_ASSESSMENT) {
                // late submissions are allowed in the submission and assessment phase only
                return false;
            }
            if (!$ignoredeadlines and !empty($this->submissionstart) and $this->submissionstart > $now) {
                // late submissions are not allowed before the submission start
                return false;
            }
            return true;

        } else {
            if ($this->phase != self::PHASE_SUBMISSION) {
                // submissions are allowed during the submission phase only
                return false;
            }
            if (!$ignoredeadlines and !empty($this->submissionstart) and $this->submissionstart > $now) {
                // if enabled, submitting is not allowed before the date/time defined in the mod_form
                return false;
            }
            if (!$ignoredeadlines and !empty($this->submissionend) and $now > $this->submissionend ) {
                // if enabled, submitting is not allowed after the date/time defined in the mod_form unless late submission is allowed
                return false;
            }
            return true;
        }
    }

    /**
     * Is the given user allowed to modify their existing submission?
     *
     * @param int $userid
     * @return bool
     */
    public function modifying_submission_allowed($userid) {

        $now = time();
        $ignoredeadlines = has_capability('mod/advwork:ignoredeadlines', $this->context, $userid);

        if ($this->phase != self::PHASE_SUBMISSION) {
            // submissions can be edited during the submission phase only
            return false;
        }
        if (!$ignoredeadlines and !empty($this->submissionstart) and $this->submissionstart > $now) {
            // if enabled, re-submitting is not allowed before the date/time defined in the mod_form
            return false;
        }
        if (!$ignoredeadlines and !empty($this->submissionend) and $now > $this->submissionend) {
            // if enabled, re-submitting is not allowed after the date/time defined in the mod_form even if late submission is allowed
            return false;
        }
        return true;
    }

    /**
     * Is the given reviewer allowed to create/edit their assessments?
     *
     * @param int $userid
     * @return bool
     */
    public function assessing_allowed($userid) {

        if ($this->phase != self::PHASE_ASSESSMENT) {
            // assessing is allowed in the assessment phase only, unless the user is a teacher
            // providing additional assessment during the evaluation phase
            if ($this->phase != self::PHASE_EVALUATION or !has_capability('mod/advwork:overridegrades', $this->context, $userid)) {
                return false;
            }
        }

        $now = time();
        $ignoredeadlines = has_capability('mod/advwork:ignoredeadlines', $this->context, $userid);

        if (!$ignoredeadlines and !empty($this->assessmentstart) and $this->assessmentstart > $now) {
            // if enabled, assessing is not allowed before the date/time defined in the mod_form
            return false;
        }
        if (!$ignoredeadlines and !empty($this->assessmentend) and $now > $this->assessmentend) {
            // if enabled, assessing is not allowed after the date/time defined in the mod_form
            return false;
        }
        // here we go, assessing is allowed
        return true;
    }

    /**
     * Are reviewers allowed to create/edit their assessments of the example submissions?
     *
     * Returns null if example submissions are not enabled in this advwork. Otherwise returns
     * true or false. Note this does not check other conditions like the number of already
     * assessed examples, examples mode etc.
     *
     * @return null|bool
     */
    public function assessing_examples_allowed() {
        if (empty($this->useexamples)) {
            return null;
        }
        if (self::EXAMPLES_VOLUNTARY == $this->examplesmode) {
            return true;
        }
        if (self::EXAMPLES_BEFORE_SUBMISSION == $this->examplesmode and self::PHASE_SUBMISSION == $this->phase) {
            return true;
        }
        if (self::EXAMPLES_BEFORE_ASSESSMENT == $this->examplesmode and self::PHASE_ASSESSMENT == $this->phase) {
            return true;
        }
        return false;
    }

    /**
     * Are the peer-reviews available to the authors?
     *
     * @return bool
     */
    public function assessments_available() {
        return $this->phase == self::PHASE_CLOSED;
    }

    /**
     * Switch to a new advwork phase
     *
     * Modifies the underlying database record. You should terminate the script shortly after calling this.
     *
     * @param int $newphase new phase code
     * @return bool true if success, false otherwise
     */
    public function switch_phase($newphase) {
        global $DB;

        $known = $this->available_phases_list();
        if (!isset($known[$newphase])) {
            return false;
        }

        if (self::PHASE_CLOSED == $newphase) {
            // push the grades into the gradebook
            $advwork = new stdclass();
            foreach ($this as $property => $value) {
                $advwork->{$property} = $value;
            }
            $advwork->course     = $this->course->id;
            $advwork->cmidnumber = $this->cm->id;
            $advwork->modname    = 'advwork';
            advwork_update_grades($advwork);
        }

        $DB->set_field('advwork', 'phase', $newphase, array('id' => $this->id));
        $this->phase = $newphase;
        $eventdata = array(
            'objectid' => $this->id,
            'context' => $this->context,
            'other' => array(
                'advworkphase' => $this->phase
            )
        );
        $event = \mod_advwork\event\phase_switched::create($eventdata);
        $event->trigger();
        return true;
    }

    /**
     * Saves a raw grade for submission as calculated from the assessment form fields
     *
     * @param array $assessmentid assessment record id, must exists
     * @param mixed $grade        raw percentual grade from 0.00000 to 100.00000
     * @return false|float        the saved grade
     */
    public function set_peer_grade($assessmentid, $grade) {
        global $DB;

        if (is_null($grade)) {
            return false;
        }
        $data = new stdclass();
        $data->id = $assessmentid;
        $data->grade = $grade;
        $data->timemodified = time();
        $DB->update_record('advwork_assessments', $data);
        return $grade;
    }

    /**
     * Prepares data object with all advwork grades to be rendered
     *
     * @param int $userid the user we are preparing the report for
     * @param int $groupid if non-zero, prepare the report for the given group only
     * @param int $page the current page (for the pagination)
     * @param int $perpage participants per page (for the pagination)
     * @param string $sortby lastname|firstname|submissiontitle|submissiongrade|gradinggrade
     * @param string $sorthow ASC|DESC
     * @return stdclass data for the renderer
     */
    public function prepare_grading_report_data($userid, $groupid, $page, $perpage, $sortby, $sorthow) {
        global $DB;
        global $COURSE;

        $canviewall     = has_capability('mod/advwork:viewallassessments', $this->context, $userid);
        $isparticipant  = $this->is_participant($userid);

        if (!$canviewall and !$isparticipant) {
            // who the hell is this?
            return array();
        }

        $originalsortby = $sortby;

        if (!in_array($sortby, array('lastname', 'firstname', 'submissiontitle', 'submissionmodified',
                'submissiongrade', 'gradinggrade'))) {
            $sortby = 'lastname';
        }

        if (!($sorthow === 'ASC' or $sorthow === 'DESC')) {
            $sorthow = 'ASC';
        }

        // get the list of user ids to be displayed
        if ($canviewall) {
            $participants = $this->get_participants(false, $groupid);
        } else {
            // this is an ordinary advwork participant (aka student) - display the report just for him/her
            $participants = array($userid => (object)array('id' => $userid));
        }

        // we will need to know the number of all records later for the pagination purposes
        $numofparticipants = count($participants);

        if ($numofparticipants > 0) {
            // load all fields which can be used for sorting and paginate the records
            list($participantids, $params) = $DB->get_in_or_equal(array_keys($participants), SQL_PARAMS_NAMED);
            $params['advworkid1'] = $this->id;
            $params['advworkid2'] = $this->id;
            $sqlsort = array();
            $sqlsortfields = array($sortby => $sorthow) + array('lastname' => 'ASC', 'firstname' => 'ASC', 'u.id' => 'ASC');
            foreach ($sqlsortfields as $sqlsortfieldname => $sqlsortfieldhow) {
                $sqlsort[] = $sqlsortfieldname . ' ' . $sqlsortfieldhow;
            }
            $sqlsort = implode(',', $sqlsort);
            $picturefields = user_picture::fields('u', array(), 'userid');
            $sql = "SELECT $picturefields, s.title AS submissiontitle, s.timemodified AS submissionmodified,
                           s.grade AS submissiongrade, ag.gradinggrade
                      FROM {user} u
                 LEFT JOIN {advwork_submissions} s ON (s.authorid = u.id AND s.advworkid = :advworkid1 AND s.example = 0)
                 LEFT JOIN {advwork_aggregations} ag ON (ag.userid = u.id AND ag.advworkid = :advworkid2)
                     WHERE u.id $participantids
                  ORDER BY $sqlsort";
            $participants = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        } else {
            $participants = array();
        }

        // this will hold the information needed to display user names and pictures
        $userinfo = array();

        // get the user details for all participants to display
        $additionalnames = get_all_user_name_fields();
        foreach ($participants as $participant) {
            if (!isset($userinfo[$participant->userid])) {
                $userinfo[$participant->userid]            = new stdclass();
                $userinfo[$participant->userid]->id        = $participant->userid;
                $userinfo[$participant->userid]->picture   = $participant->picture;
                $userinfo[$participant->userid]->imagealt  = $participant->imagealt;
                $userinfo[$participant->userid]->email     = $participant->email;
                foreach ($additionalnames as $addname) {
                    $userinfo[$participant->userid]->$addname = $participant->$addname;
                }
            }
        }

        // load the submissions details
        $submissions = $this->get_submissions(array_keys($participants));

        // get the user details for all moderators (teachers) that have overridden a submission grade
        foreach ($submissions as $submission) {
            if (!isset($userinfo[$submission->gradeoverby])) {
                $userinfo[$submission->gradeoverby]            = new stdclass();
                $userinfo[$submission->gradeoverby]->id        = $submission->gradeoverby;
                $userinfo[$submission->gradeoverby]->picture   = $submission->overpicture;
                $userinfo[$submission->gradeoverby]->imagealt  = $submission->overimagealt;
                $userinfo[$submission->gradeoverby]->email     = $submission->overemail;
                foreach ($additionalnames as $addname) {
                    $temp = 'over' . $addname;
                    $userinfo[$submission->gradeoverby]->$addname = $submission->$temp;
                }
            }
        }

        // get the user details for all reviewers of the displayed participants
        $reviewers = array();

        if ($submissions) {
            list($submissionids, $params) = $DB->get_in_or_equal(array_keys($submissions), SQL_PARAMS_NAMED);
            list($sort, $sortparams) = users_order_by_sql('r');
            $picturefields = user_picture::fields('r', array(), 'reviewerid');
            $sql = "SELECT a.id AS assessmentid, a.submissionid, a.grade, a.gradinggrade, a.gradinggradeover, a.weight,
                           $picturefields, s.id AS submissionid, s.authorid
                      FROM {advwork_assessments} a
                      JOIN {user} r ON (a.reviewerid = r.id)
                      JOIN {advwork_submissions} s ON (a.submissionid = s.id AND s.example = 0)
                     WHERE a.submissionid $submissionids
                  ORDER BY a.weight DESC, $sort";
            $reviewers = $DB->get_records_sql($sql, array_merge($params, $sortparams));
            foreach ($reviewers as $reviewer) {
                if (!isset($userinfo[$reviewer->reviewerid])) {
                    $userinfo[$reviewer->reviewerid]            = new stdclass();
                    $userinfo[$reviewer->reviewerid]->id        = $reviewer->reviewerid;
                    $userinfo[$reviewer->reviewerid]->picture   = $reviewer->picture;
                    $userinfo[$reviewer->reviewerid]->imagealt  = $reviewer->imagealt;
                    $userinfo[$reviewer->reviewerid]->email     = $reviewer->email;
                    foreach ($additionalnames as $addname) {
                        $userinfo[$reviewer->reviewerid]->$addname = $reviewer->$addname;
                    }
                }
            }
        }

        // get the user details for all reviewees of the displayed participants
        $reviewees = array();
        if ($participants) {
            list($participantids, $params) = $DB->get_in_or_equal(array_keys($participants), SQL_PARAMS_NAMED);
            list($sort, $sortparams) = users_order_by_sql('e');
            $params['advworkid'] = $this->id;
            $picturefields = user_picture::fields('e', array(), 'authorid');
            $sql = "SELECT a.id AS assessmentid, a.submissionid, a.grade, a.gradinggrade, a.gradinggradeover, a.reviewerid, a.weight,
                           s.id AS submissionid, $picturefields
                      FROM {user} u
                      JOIN {advwork_assessments} a ON (a.reviewerid = u.id)
                      JOIN {advwork_submissions} s ON (a.submissionid = s.id AND s.example = 0)
                      JOIN {user} e ON (s.authorid = e.id)
                     WHERE u.id $participantids AND s.advworkid = :advworkid
                  ORDER BY a.weight DESC, $sort";
            $reviewees = $DB->get_records_sql($sql, array_merge($params, $sortparams));
            foreach ($reviewees as $reviewee) {
                if (!isset($userinfo[$reviewee->authorid])) {
                    $userinfo[$reviewee->authorid]            = new stdclass();
                    $userinfo[$reviewee->authorid]->id        = $reviewee->authorid;
                    $userinfo[$reviewee->authorid]->picture   = $reviewee->picture;
                    $userinfo[$reviewee->authorid]->imagealt  = $reviewee->imagealt;
                    $userinfo[$reviewee->authorid]->email     = $reviewee->email;
                    foreach ($additionalnames as $addname) {
                        $userinfo[$reviewee->authorid]->$addname = $reviewee->$addname;
                    }
                }
            }
        }

        // get the student models (single session and cumulated)
        $studentmodels = $this->get_student_models($COURSE->id, $this->id, 0);
        $studentmodelscumulated = $this->get_student_models($COURSE->id, $this->id, 1);

        // finally populate the object to be rendered
        $grades = $participants;

        foreach ($participants as $participant) {
            // set up default (null) values
            $grades[$participant->userid]->submissionid = null;
            $grades[$participant->userid]->submissiontitle = null;
            $grades[$participant->userid]->submissiongrade = null;
            $grades[$participant->userid]->submissiongradeover = null;
            $grades[$participant->userid]->submissiongradeoverby = null;
            $grades[$participant->userid]->submissionpublished = null;
            $grades[$participant->userid]->competencesinglesession = null;
            $grades[$participant->userid]->assessmentcapabilitysinglesession = null;
            $grades[$participant->userid]->submissiongradesinglesession = null;
            $grades[$participant->userid]->competencecumulated = null;
            $grades[$participant->userid]->assessmentcapabilitycumulated = null;
            $grades[$participant->userid]->submissiongradecumulated = null;
            $grades[$participant->userid]->reviewedby = array();
            $grades[$participant->userid]->reviewerof = array();
        }
        unset($participants);
        unset($participant);

        foreach ($submissions as $submission) {
            $grades[$submission->authorid]->submissionid = $submission->id;
            $grades[$submission->authorid]->submissiontitle = $submission->title;
            $grades[$submission->authorid]->submissiongrade = $this->real_grade($submission->grade);  // displayed in the report
            $grades[$submission->authorid]->submissiongradeover = $this->real_grade($submission->gradeover);
            $grades[$submission->authorid]->submissiongradeoverby = $submission->gradeoverby;
            $grades[$submission->authorid]->submissionpublished = $submission->published;
        }
        unset($submissions);
        unset($submission);

        foreach($reviewers as $reviewer) {
            $info = new stdclass();
            $info->userid = $reviewer->reviewerid;
            $info->assessmentid = $reviewer->assessmentid;
            $info->submissionid = $reviewer->submissionid;
            $info->grade = $this->real_grade($reviewer->grade);
            $info->gradinggrade = $this->real_grading_grade($reviewer->gradinggrade);
            $info->gradinggradeover = $this->real_grading_grade($reviewer->gradinggradeover);
            $info->weight = $reviewer->weight;
            $grades[$reviewer->authorid]->reviewedby[$reviewer->reviewerid] = $info;
        }
        unset($reviewers);
        unset($reviewer);

        foreach($reviewees as $reviewee) {
            $info = new stdclass();
            $info->userid = $reviewee->authorid;
            $info->assessmentid = $reviewee->assessmentid;
            $info->submissionid = $reviewee->submissionid;
            $info->grade = $this->real_grade($reviewee->grade);
            $info->gradinggrade = $this->real_grading_grade($reviewee->gradinggrade);
            $info->gradinggradeover = $this->real_grading_grade($reviewee->gradinggradeover);
            $info->weight = $reviewee->weight;
            $grades[$reviewee->reviewerid]->reviewerof[$reviewee->authorid] = $info;
        }
        unset($reviewees);
        unset($reviewee);

        foreach ($grades as $grade) {
            $grade->gradinggrade = $this->real_grading_grade($grade->gradinggrade);   // displayed in the report
        }

        foreach ($studentmodels as $studentmodel) {
            $gradevalue = number_format((float)$studentmodel->capabilityoverallvalue * 100, 2, '.', '');
            if(!is_null($grades[$studentmodel->userid])) {
            	switch ($studentmodel->capability) {
                	case Capabilities::K:
                    	$grades[$studentmodel->userid]->competencesinglesession = $gradevalue;
                    	break;
                	case Capabilities::J:
                    	$grades[$studentmodel->userid]->assessmentcapabilitysinglesession = $gradevalue;
                    	break;
               	 	case Capabilities::C:
                    	$grades[$studentmodel->userid]->submissiongradesinglesession = $gradevalue;
                    	break;
            	}
            }
        }


        foreach ($studentmodelscumulated as $studentmodel) {
            $gradevalue = number_format((float)$studentmodel->capabilityoverallvalue * 100, 2, '.', '');
            switch ($studentmodel->capability) {
                case Capabilities::K:
                    $grades[$studentmodel->userid]->competencecumulated = $gradevalue;
                    break;
                case Capabilities::J:
                    $grades[$studentmodel->userid]->assessmentcapabilitycumulated = $gradevalue;
                    break;
                case Capabilities::C:
                    $grades[$studentmodel->userid]->submissiongradecumulated = $gradevalue;
                    break;
            }
        }

        $data = new stdclass();
        $data->grades = $grades;
        $data->userinfo = $userinfo;
        $data->totalcount = $numofparticipants;
        $data->maxgrade = $this->real_grade(100);
        $data->maxgradinggrade = $this->real_grading_grade(100);

        switch($originalsortby) {
            case 'submissiongradesinglesession':
                $data->grades = $this->sort_array($data->grades, 'submissiongradesinglesession', $sorthow);
                break;
            case 'competencesinglesession':
                $data->grades = $this->sort_array($data->grades, 'competencesinglesession', $sorthow);
                break;
            case 'assessmentcapabilitysinglesession':
                $data->grades = $this->sort_array($data->grades, 'assessmentcapabilitysinglesession', $sorthow);
                break;
            case 'submissiongradecumulated':
                $data->grades = $this->sort_array($data->grades, 'submissiongradecumulated', $sorthow);
                break;
            case 'competencecumulated':
                $data->grades = $this->sort_array($data->grades, 'competencecumulated', $sorthow);
                break;
            case 'assessmentcapabilitycumulated':
                $data->grades = $this->sort_array($data->grades, 'assessmentcapabilitycumulated', $sorthow);
                break;
        }

        return $data;
    }

    /**
     * Prepares data object with all general student model grades to be rendered
     *
     * @param int $userid the user we are preparing the report for (teacher)
     * @param int $courseid the id of the course
     * @param string $sortby column used to sort by
     * @param string $sorthow ASC|DESC
     * @return stdclass data for the renderer
     */
    function prepare_general_student_models_report_data($userid, $courseid, $sortby, $sorthow) {
        global $DB;

        $courseteachersid = $this->get_course_teachers($courseid);
        $iscourseteacher = in_array($userid, $courseteachersid);

        if (!$iscourseteacher) {
            return array();
        }

        if (!in_array($sortby, array('name', 'submissiongrade', 'competence', 'assessmentcapability', 'continuity', 'stability', 'reliability'))) {
            $sortby = 'submissiongrade';
        }

        if (!($sorthow === 'ASC' or $sorthow === 'DESC')) {
            $sorthow = 'ASC';
        }

        $data = [];
        $studentsenrolledincourse = $this->get_students_enrolled_to_course($DB, $courseid);
        foreach ($studentsenrolledincourse as $student) {
            $studentid = $student->userid;
            $studentgrades = $this->get_student_grades($courseid, $studentid);
            $averagestudentgrades = $this->compute_average_value($studentgrades);

            $generalstudentmodel = $this->get_general_student_model($courseid, $studentid);

            // get the general competence value
            $capabilityname = "K";
            $capabilityentries = array_filter($generalstudentmodel->entries, function($entry) use($capabilityname){
                return $entry->capability == $capabilityname;
            });
            $capabilityentry = reset($capabilityentries);
            $studentcompetence = $capabilityentry->capabilityoverallvalue;

            // get the general assessment capability value
            $capabilityname = "J";
            $capabilityentries = array_filter($generalstudentmodel->entries, function($entry) use($capabilityname){
                return $entry->capability == $capabilityname;
            });
            $capabilityentry = reset($capabilityentries);
            $studentassessmentcapability = $capabilityentry->capabilityoverallvalue;

            $studentoverallgrades = $this->get_overall_grades($DB, $courseid, $studentid);
            
			$data[$studentid]=new stdclass();
            $data[$studentid]->name                 = $student->name;
            $data[$studentid]->submissiongrade      = $averagestudentgrades * 100;
            $data[$studentid]->competence           = $studentcompetence * 100;
            $data[$studentid]->assessmentcapability = $studentassessmentcapability * 100;
            $data[$studentid]->continuity           = $studentoverallgrades->reliability_metrics->continuitysessionlevel;
            $data[$studentid]->stability            = $studentoverallgrades->reliability_metrics->stability;
            $data[$studentid]->reliability          = $studentoverallgrades->reliability_metrics->reliability;
            
            

        }

        switch($sortby) {
            case 'name':
                $data = $this->sort_array($data, 'name', $sorthow);
                break;
            case 'submissiongrade':
                $data = $this->sort_numeric_array($data, 'submissiongrade', $sorthow);
                break;
            case 'competence':
                $data = $this->sort_numeric_array($data, 'competence', $sorthow);
                break;
            case 'assessmentcapability':
                $data = $this->sort_numeric_array($data, 'assessmentcapability', $sorthow);
                break;
            case 'continuity':
                $data = $this->sort_numeric_array($data, 'continuity', $sorthow);
                break;
            case 'stability':
                $data = $this->sort_numeric_array($data, 'stability', $sorthow);
                break;
            case 'reliability':
                $data = $this->sort_numeric_array($data, 'reliability', $sorthow);
                break;
        }

        return $data;
    }

    function get_k($courseid, $studentid) {
        $generalstudentmodel = $this->get_general_student_model($courseid, $studentid);

        // get the general competence value
        $capabilityname = "K";
        $capabilityentries = array_filter($generalstudentmodel->entries, function($entry) use($capabilityname){
            return $entry->capability == $capabilityname;
        });
        $capabilityentry = reset($capabilityentries);
        return $capabilityentry->capabilityoverallvalue;
    }
    function get_j($courseid, $studentid) {
        $generalstudentmodel = $this->get_general_student_model($courseid, $studentid);

        $capabilityname = "J";
        $capabilityentries = array_filter($generalstudentmodel->entries, function($entry) use($capabilityname){
            return $entry->capability == $capabilityname;
        });
        $capabilityentry = reset($capabilityentries);
        return $capabilityentry->capabilityoverallvalue;
    }
    /**
     * Used to sort an array of objects based on the specified property
     *
     * @param $array                    Array to be sorted
     * @param $property                 Property to sort by
     * @param $sorthow                  Order of sorting ASC|DESC
     * @return $array of the objects sorted
     */
    function sort_array($array, $property, $sorthow) {
        usort($array, function($a, $b) use ($property, $sorthow) {
            if ($sorthow === 'ASC') {
                return strcmp($a->$property, $b->$property);
            } else {
                return strcmp($a->$property, $b->$property) * (-1);
            }
        });

        return $array;
    }

    /**
     * Used to sort an array of objects based on the specified property
     *
     * @param $array                    Array to be sorted
     * @param $property                 Property to sort by
     * @param $sorthow                  Order of sorting ASC|DESC
     * @return $array of the objects sorted
     */
    function sort_numeric_array($array, $property, $sorthow) {
        usort($array, function($a, $b) use ($property, $sorthow) {
            if(is_nan($a->$property)) {
                $a->$property = 0;
            }

            if(is_nan($b->$property)) {
                $b->$property = 0;
            }

            if ($sorthow === 'ASC') {
                return $a->$property > $b->$property;
            } else {
                return $a->$property < $b->$property;
            }
        });

        return $array;
    }

    /**
     * Creates an array with the quartiles (first and third quartile) for each capability (single session and cumulated)
     *
     * @param $courseid             Id of the course
     * @param $advwork             advwork instance
     * @return array with objects that contain the quartiles
     */
    function compute_capabilities_quartiles($courseid, $advwork) {
        global $DB;

        $studentsmodelssinglesession = $this->get_student_models($courseid, $advwork->id, 0);
        $studentsmodelscumulated     = $this->get_student_models($courseid, $advwork->id, 1);

        $capabilities = $this->get_capabilities();
        $studentsenrolled = $this->get_students_enrolled_to_course($DB, $courseid);

        $capabilitiesquartiles = [];
        foreach ($capabilities as $capability) {
            $capabilityname = $capability->name;

            $singlesessioncapabilitiesvalues = array_filter($this->get_capability_values($studentsmodelssinglesession, $studentsenrolled, $capabilityname));
            sort($singlesessioncapabilitiesvalues);

            $firstquartile = Statistics::quartile25($singlesessioncapabilitiesvalues);
            $thirdquartile = Statistics::quartile75($singlesessioncapabilitiesvalues);

            $capabilitiesquartiles[] = $this->create_capability_quartile_object($capabilityname, false, $firstquartile, $thirdquartile);

            $cumulatedcapabilitiesvalues = array_filter($this->get_capability_values($studentsmodelscumulated, $studentsenrolled, $capability->name));
            sort($cumulatedcapabilitiesvalues);
            $firstquartile = Statistics::quartile25($cumulatedcapabilitiesvalues);
            $thirdquartile = Statistics::quartile75($cumulatedcapabilitiesvalues);
            $capabilitiesquartiles[] = $this->create_capability_quartile_object($capabilityname, true, $firstquartile, $thirdquartile);
        }

        return $capabilitiesquartiles;
    }

    /**
     * Used to get the values of the specified capability for the students
     *
     * @param $studentsmodels                       Models of students for a advwork session
     * @param $studentsenrolled                     Students enrolled to the course
     * @param $capabilityname                       Name of the capability to get the values for
     * @return array with the values of the capability
     */
    function get_capability_values($studentsmodels, $studentsenrolled, $capabilityname) {
        $capabilityvalues = [];

        foreach ($studentsenrolled as $student) {
            $capabilityentries = array_filter($studentsmodels, function($entry) use($capabilityname, $student){
                return $entry->capability == $capabilityname && $entry->userid == $student->userid;
            });

            $capabilityentry = reset($capabilityentries);
            $capabilityvalue = $capabilityentry->capabilityoverallvalue;
            if(is_numeric($capabilityvalue)) {
                $capabilityvalues[] = $capabilityvalue;
            }

        }

        return $capabilityvalues;
    }

    /**
     * Used to create a quartile object that contains the first and third quartile for the values of a capability
     *
     * @param $capabilityname           Name of the capability
     * @param $iscumulated              Flag to indicate if the capability is single session or cumulated
     * @param $firstquartile            Value of the first quartile
     * @param $thirdquartile            Value of the third quartile
     * @return capability_quartiles     Object that contains the data
     */
    function create_capability_quartile_object($capabilityname, $iscumulated, $firstquartile, $thirdquartile) {
        $capabilityquartiles = new capability_quartiles();
        $capabilityquartiles->capabilityname = $capabilityname;
        $capabilityquartiles->iscumulated = $iscumulated;
        $capabilityquartiles->firstquartile = $firstquartile;
        $capabilityquartiles->thirdquartile = $thirdquartile;

        return $capabilityquartiles;
    }


    /**
     * Save the specified student models in database ("advwork_student_models" table)
     *
     * @param $studentmodels        student models
     * @param $courseid             id of the course the models are for
     * @param $advworkid           id of the advwork instance the models are for
     * @param $capabilities         capabilities to model (K, J, C)
     * @param $domainvalues         values of the capabilities
     * @param $cumulated            specifies whether the student models are for single session or cumulated
     * @return void
     */
    public function save_student_models_in_database($studentmodelsresponse, $courseid, $advworkid, $cumulated) {
        global $DB;

        $studentmodelsproperty = "student-models";
        $studentmodels = $studentmodelsresponse->$studentmodelsproperty;
        $capabilitiestable = "advwork_capabilities";
        $capabilities = $DB->get_records($capabilitiestable);
        $domainvaluestable = "advwork_domain_values";
        $domainvalues  = $DB->get_records($domainvaluestable);

        foreach ($studentmodels as $studentid => $studentmodel) {

            foreach ($capabilities as $capability) {
                $capabilityname = $capability->name;
                $capabilitydata = $studentmodel->$capabilityname;
                $capabilitygrade = $capabilitydata->grade;
                $capabilityvalue = $capabilitydata->value;
                $capabilityprobs = $capabilitydata->probs;
                $indexprobs = 0;

                $domainvaluescount = count($domainvalues);
                $domainvaluescounter = 1;

                foreach ($domainvalues as $domainvalue) {
                    // skip last entry as we need to insert probabilities for 6 values, not also for the 7th which is the closing of the interval
                    if ($domainvaluescounter == $domainvaluescount) {
                        break;
                    }

                    $record = new stdclass();
                    $record->courseid = $courseid;
                    $record->advworkid = $advworkid;
                    $record->userid = $studentid;
                    $record->capabilityid = $capability->id;
                    $record->domainvalueid = $domainvalue->id;
                    $record->probability = $studentmodel->$capabilityname->probs[$indexprobs];
                    $record->capabilityoverallgrade = $capabilitygrade;
                    $record->capabilityoverallvalue = $capabilityvalue;
                    $record->iscumulated = $cumulated;


                    $advworkstudentmodelstable = "advwork_student_models";
                    $record->id = $DB->insert_record($advworkstudentmodelstable, $record);


                    $indexprobs++;
                    $domainvaluescounter++;
                }
            }
        }
    }

    public function get_student_not_in_this_studentmodelsresponse($studentmodels, $advworkid) {
        global $DB;

        $query = "SELECT authorid FROM mdl_advwork_submissions WHERE advworkid = $advworkid";
        foreach ($studentmodels as $studentid => $studentmodel) {
            $query = $query." and authorid <> $studentid";
        }
        echo "query: $query";
        return $DB->get_records_sql($query);
    }

    /**
     * Save the next best peer to be graded by the teacher for a given advwork session in the database
     *
     * @param $courseid                 Id of the course for the advwork session
     * @param $advworkid               advwork instance id
     * @param $peerid                   Next best peer to be graded the teacher
     * @param $grade                    Letter grade predicted by the Bayesian Network Service
     * @param $max                      Grade predicted by the Bayesian Network Service using the max entropy algorithm
     * @param $value                    Numerical value predicted by the Bayesian Network Service
     */
    public function save_next_best_peer_to_grade_in_database($courseid, $advworkid, $studentmodels)
    {
        global $DB;

        $nextbestpeertogradeproperty = "best";
        $nextbestpeertograde = $studentmodels->$nextbestpeertogradeproperty;
        if(!empty($nextbestpeertograde)) {
            $record = new stdclass();
            $record->courseid = $courseid;
            $record->advworkid = $advworkid;
            $record->peerid = $nextbestpeertograde->ID;
            $record->grade = $nextbestpeertograde->grade;
            $record->max = $nextbestpeertograde->max;
            $record->value = $nextbestpeertograde->value;

            $advworkbestpeertable = "advwork_best_peer";
            $record->id = $DB->insert_record($advworkbestpeertable, $record);
        }
    }

    /**
     * Used to get the next best submission to grade by the teacher for a session
     *
     * @param $advwork             advwork instance
     * @param $courseid             Id of the course
     * @return the next best submission to grade by the teacher
     */
    public function get_next_best_submission_to_grade($advwork, $courseid, $groupid = null) {
        global $USER;

        $nextbestsubmissiontograde = null;
        $courseteachersid = $advwork->get_course_teachers($courseid);
        $iscourseteacher = in_array($USER->id, $courseteachersid);
        if(isloggedin() && $iscourseteacher) {
            if (is_null($groupid) || $groupid==0)
                $nextbestsubmissiontograde = $advwork->get_next_best_submission_to_grade_from_db($courseid, $advwork->id);
            else
                $nextbestsubmissiontograde = $advwork->get_next_best_submission_to_grade_from_db_by_gruopid($courseid, $advwork->id, $groupid);
        }

        return $nextbestsubmissiontograde;
    }

    /**
     * Get the next best submission to be graded by the teacher for the given advwork session
     *
     * @param $courseid             Course of the advwork session
     * @param $advworkid           Id of the advwork session
     * @return mixed                Id of the next best submission to be graded by the teacher
     */
    public function get_next_best_submission_to_grade_from_db($courseid, $advworkid) {
        global $DB;

        $submissionauthorid = $DB->get_record_sql('SELECT peerid FROM `mdl_advwork_best_peer` WHERE id IN 
                                                          (SELECT MAX(id) as maxid FROM `mdl_advwork_best_peer` WHERE id IN 
                                                            (SELECT id FROM `mdl_advwork_best_peer` WHERE courseid = ? AND advworkid = ?))',
            array($courseid, $advworkid));

        // get the id of the submission provided by the peer for the advwork session
        $advworksubmissionstable = "advwork_submissions";
        $submission = $DB->get_record($advworksubmissionstable, array('advworkid' => $advworkid, 'authorid' => $submissionauthorid->peerid));

        return $submission;
    }

    /**
     * Get the next best submission to be graded by the teacher for the given advwork session and for a given group
     *
     * @param $courseid             Course of the advwork session
     * @param $advworkid           Id of the advwork session
     * @return mixed                Id of the next best submission to be graded by the teacher
     */
    public function get_next_best_submission_to_grade_from_db_by_gruopid($courseid, $advworkid, $groupid) {
        global $DB;

        $submissionauthorid = $DB->get_record_sql('SELECT peerid FROM `mdl_advwork_best_peer` WHERE id IN 
                                                          (SELECT MAX(id) as maxid FROM `mdl_advwork_best_peer` WHERE id IN 
                                                            (SELECT best_peer.id FROM `mdl_advwork_best_peer` as best_peer join mdl_groups_members on peerid = userid WHERE courseid = ? AND advworkid = ? AND groupid = ?))',
            array($courseid, $advworkid, $groupid));

        // get the id of the submission provided by the peer for the advwork session
        $advworksubmissionstable = "advwork_submissions";
        $submission = $DB->get_record($advworksubmissionstable, array('advworkid' => $advworkid, 'authorid' => $submissionauthorid->peerid));

        return $submission;
    }

    /**
     * Calculates the real value of a grade
     *
     * @param float $value percentual value from 0 to 100
     * @param float $max   the maximal grade
     * @return string
     */
    public function real_grade_value($value, $max) {
        $localized = true;
        if (is_null($value) or $value === '') {
            return null;
        } elseif ($max == 0) {
            return 0;
        } else {
            return format_float($max * $value / 100, $this->gradedecimals, $localized);
        }
    }

    /**
     * Calculates the raw (percentual) value from a real grade
     *
     * This is used in cases when a user wants to give a grade such as 12 of 20 and we need to save
     * this value in a raw percentual form into DB
     * @param float $value given grade
     * @param float $max   the maximal grade
     * @return float       suitable to be stored as numeric(10,5)
     */
    public function raw_grade_value($value, $max) {
        if (is_null($value) or $value === '') {
            return null;
        }
        if ($max == 0 or $value < 0) {
            return 0;
        }
        $p = $value / $max * 100;
        if ($p > 100) {
            return $max;
        }
        return grade_floatval($p);
    }

    /**
     * Calculates the real value of grade for submission
     *
     * @param float $value percentual value from 0 to 100
     * @return string
     */
    public function real_grade($value) {
        return $this->real_grade_value($value, $this->grade);
    }

    /**
     * Calculates the real value of grade for assessment
     *
     * @param float $value percentual value from 0 to 100
     * @return string
     */
    public function real_grading_grade($value) {
        return $this->real_grade_value($value, $this->gradinggrade);
    }

    /**
     * Sets the given grades and received grading grades to null
     *
     * This does not clear the information about how the peers filled the assessment forms, but
     * clears the calculated grades in advwork_assessments. Therefore reviewers have to re-assess
     * the allocated submissions.
     *
     * @return void
     */
    public function clear_assessments() {
        global $DB;

        $submissions = $this->get_submissions();
        if (empty($submissions)) {
            // no money, no love
            return;
        }
        $submissions = array_keys($submissions);
        list($sql, $params) = $DB->get_in_or_equal($submissions, SQL_PARAMS_NAMED);
        $sql = "submissionid $sql";
        $DB->set_field_select('advwork_assessments', 'grade', null, $sql, $params);
        $DB->set_field_select('advwork_assessments', 'gradinggrade', null, $sql, $params);
    }

    /**
     * Sets the grades for submission to null
     *
     * @param null|int|array $restrict If null, update all authors, otherwise update just grades for the given author(s)
     * @return void
     */
    public function clear_submission_grades($restrict=null) {
        global $DB;

        $sql = "advworkid = :advworkid AND example = 0";
        $params = array('advworkid' => $this->id);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND authorid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $DB->set_field_select('advwork_submissions', 'grade', null, $sql, $params);
    }

    /**
     * Calculates grades for submission for the given participant(s) and updates it in the database
     *
     * @param null|int|array $restrict If null, update all authors, otherwise update just grades for the given author(s)
     * @return void
     */
    public function aggregate_submission_grades($restrict=null) {
        global $DB;

        // fetch a recordset with all assessments to process
        $sql = 'SELECT s.id AS submissionid, s.grade AS submissiongrade,
                       a.weight, a.grade
                  FROM {advwork_submissions} s
             LEFT JOIN {advwork_assessments} a ON (a.submissionid = s.id)
                 WHERE s.example=0 AND s.advworkid=:advworkid'; // to be cont.
        $params = array('advworkid' => $this->id);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND s.authorid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $sql .= ' ORDER BY s.id'; // this is important for bulk processing

        $rs         = $DB->get_recordset_sql($sql, $params);
        $batch      = array();    // will contain a set of all assessments of a single submission
        $previous   = null;       // a previous record in the recordset

        foreach ($rs as $current) {
            if (is_null($previous)) {
                // we are processing the very first record in the recordset
                $previous   = $current;
            }
            if ($current->submissionid == $previous->submissionid) {
                // we are still processing the current submission
                $batch[] = $current;
            } else {
                // process all the assessments of a sigle submission
                $this->aggregate_submission_grades_process($batch);
                // and then start to process another submission
                $batch      = array($current);
                $previous   = $current;
            }
        }
        // do not forget to process the last batch!
        $this->aggregate_submission_grades_process($batch);
        $rs->close();
    }

    /**
     * Sets the aggregated grades for assessment to null
     *
     * @param null|int|array $restrict If null, update all reviewers, otherwise update just grades for the given reviewer(s)
     * @return void
     */
    public function clear_grading_grades($restrict=null) {
        global $DB;

        $sql = "advworkid = :advworkid";
        $params = array('advworkid' => $this->id);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND userid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $DB->set_field_select('advwork_aggregations', 'gradinggrade', null, $sql, $params);
    }

    /**
     * Calculates grades for assessment for the given participant(s)
     *
     * Grade for assessment is calculated as a simple mean of all grading grades calculated by the grading evaluator.
     * The assessment weight is not taken into account here.
     *
     * @param null|int|array $restrict If null, update all reviewers, otherwise update just grades for the given reviewer(s)
     * @return void
     */
    public function aggregate_grading_grades($restrict=null) {
        global $DB;

        // fetch a recordset with all assessments to process
        $sql = 'SELECT a.reviewerid, a.gradinggrade, a.gradinggradeover,
                       ag.id AS aggregationid, ag.gradinggrade AS aggregatedgrade
                  FROM {advwork_assessments} a
            INNER JOIN {advwork_submissions} s ON (a.submissionid = s.id)
             LEFT JOIN {advwork_aggregations} ag ON (ag.userid = a.reviewerid AND ag.advworkid = s.advworkid)
                 WHERE s.example=0 AND s.advworkid=:advworkid'; // to be cont.
        $params = array('advworkid' => $this->id);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND a.reviewerid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $sql .= ' ORDER BY a.reviewerid'; // this is important for bulk processing

        $rs         = $DB->get_recordset_sql($sql, $params);
        $batch      = array();    // will contain a set of all assessments of a single submission
        $previous   = null;       // a previous record in the recordset

        foreach ($rs as $current) {
            if (is_null($previous)) {
                // we are processing the very first record in the recordset
                $previous   = $current;
            }
            if ($current->reviewerid == $previous->reviewerid) {
                // we are still processing the current reviewer
                $batch[] = $current;
            } else {
                // process all the assessments of a sigle submission
                $this->aggregate_grading_grades_process($batch);
                // and then start to process another reviewer
                $batch      = array($current);
                $previous   = $current;
            }
        }
        // do not forget to process the last batch!
        $this->aggregate_grading_grades_process($batch);
        $rs->close();
    }

    /**
     * Calculates the overall grades (submission grades and assesment grades) and reliability metrics for all the students enrolled in a course
     *
     * @return void
     */
    public function aggregate_overall_grades() {
        global $COURSE;
        global $DB;
        global $PAGE;

        // get the students enrolled to the course
        $students =  $this->get_students_enrolled_to_course($DB, $COURSE->id);

        foreach ($students as $student) {
            $studentoverallgrades = $this->compute_overall_grades($DB, $PAGE, $COURSE->id, $student->userid);
            $record = new stdclass();
            $record->courseid = $student->courseid;
            $record->userid = $student->userid;
            $record->overallsubmissiongrade = $studentoverallgrades->overallsubmissiongrade;
            $record->overallassessmentgrade = $studentoverallgrades->overallassessmentgrade;
            $record->continuitysessionlevel = $studentoverallgrades->reliability_metrics->continuitysessionlevel;
            $record->continuitychunks = $studentoverallgrades->reliability_metrics->continuitychunks;
            $record->stability = $studentoverallgrades->reliability_metrics->stability;
            $record->reliability = $studentoverallgrades->reliability_metrics->reliability;
            $record->timegraded = time();

            if(!$DB->record_exists('advwork_overall_grades', array('courseid' => $student->courseid, 'userid' => $student->userid))) {
                $record->id = $DB->insert_record('advwork_overall_grades', $record);
            } else {
                $existingrecord = $DB->get_record('advwork_overall_grades', array('courseid' => $student->courseid, 'userid' => $student->userid));
                $record->id = $existingrecord->id;
                $DB->update_record('advwork_overall_grades', $record);
            }
        }
    }

    /**
     * Get the students enrolled in the specified course
     *
     * @param $DB               Moodle Database API object
     * @param $courseid         id of the course
     * @return array with the students
     */
    public function get_students_enrolled_to_course($DB, $courseid) {
        $courseteachersids = $this->get_course_teachers($courseid);
        $courseteachersarray = "(";
        foreach ($courseteachersids as $courseteacherid) {
            $courseteachersarray .= $courseteacherid;
            $courseteachersarray .= ",";
        }
        $courseteachersarray = substr_replace($courseteachersarray, "", -1);
        $courseteachersarray .= ")";

        return $DB->get_records_sql('SELECT u.id as userId, c.id as courseId, CONCAT(u.firstname, \' \', u.lastname) AS name
                                                    FROM mdl_user u
                                                    INNER JOIN mdl_user_enrolments ue ON ue.userid = u.id
                                                    INNER JOIN mdl_enrol e ON e.id = ue.enrolid
                                                    INNER JOIN mdl_course c ON e.courseid = c.id
                                                    WHERE c.id = ? and u.id not in '. $courseteachersarray,
            array($courseid));
    }



    /**
     * Returns the mform the teachers use to put a feedback for the reviewer
     *
     * @param mixed moodle_url|null $actionurl
     * @param stdClass $assessment
     * @param array $options editable, editableweight, overridablegradinggrade
     * @return advwork_feedbackreviewer_form
     */
    public function get_feedbackreviewer_form($actionurl, stdclass $assessment, $options=array()) {
        global $CFG;
        require_once(__DIR__ . '/feedbackreviewer_form.php');

        $current = new stdclass();
        $current->asid                      = $assessment->id;
        $current->weight                    = $assessment->weight;
        $current->gradinggrade              = $this->real_grading_grade($assessment->gradinggrade);
        $current->gradinggradeover          = $this->real_grading_grade($assessment->gradinggradeover);
        $current->feedbackreviewer          = $assessment->feedbackreviewer;
        $current->feedbackreviewerformat    = $assessment->feedbackreviewerformat;
        if (is_null($current->gradinggrade)) {
            $current->gradinggrade = get_string('nullgrade', 'advwork');
        }
        if (!isset($options['editable'])) {
            $editable = true;   // by default
        } else {
            $editable = (bool)$options['editable'];
        }

        // prepare wysiwyg editor
        $current = file_prepare_standard_editor($current, 'feedbackreviewer', array());

        return new advwork_feedbackreviewer_form($actionurl,
                array('advwork' => $this, 'current' => $current, 'editoropts' => array(), 'options' => $options),
                'post', '', null, $editable);
    }

    /**
     * Returns the mform the teachers use to put a feedback for the author on their submission
     *
     * @mixed moodle_url|null $actionurl
     * @param stdClass $submission
     * @param array $options editable
     * @return advwork_feedbackauthor_form
     */
    public function get_feedbackauthor_form($actionurl, stdclass $submission, $options=array()) {
        global $CFG;
        require_once(__DIR__ . '/feedbackauthor_form.php');

        $current = new stdclass();
        $current->submissionid          = $submission->id;
        $current->published             = $submission->published;
        $current->grade                 = $this->real_grade($submission->grade);
        $current->gradeover             = $this->real_grade($submission->gradeover);
        $current->feedbackauthor        = $submission->feedbackauthor;
        $current->feedbackauthorformat  = $submission->feedbackauthorformat;
        if (is_null($current->grade)) {
            $current->grade = get_string('nullgrade', 'advwork');
        }
        if (!isset($options['editable'])) {
            $editable = true;   // by default
        } else {
            $editable = (bool)$options['editable'];
        }

        // prepare wysiwyg editor
        $current = file_prepare_standard_editor($current, 'feedbackauthor', array());

        return new advwork_feedbackauthor_form($actionurl,
                array('advwork' => $this, 'current' => $current, 'editoropts' => array(), 'options' => $options),
                'post', '', null, $editable);
    }

    /**
     * Returns the information about the user's grades as they are stored in the gradebook
     *
     * The submission grade is returned for users with the capability mod/advwork:submit and the
     * assessment grade is returned for users with the capability mod/advwork:peerassess. Unless the
     * user has the capability to view hidden grades, grades must be visible to be returned. Null
     * grades are not returned. If none grade is to be returned, this method returns false.
     *
     * @param int $userid the user's id
     * @return advwork_final_grades|false
     */
    public function get_gradebook_grades($userid) {
        global $CFG;
        require_once($CFG->libdir.'/gradelib.php');

        if (empty($userid)) {
            throw new coding_exception('User id expected, empty value given.');
        }

        // Read data via the Gradebook API
        $gradebook = grade_get_grades($this->course->id, 'mod', 'advwork', $this->id, $userid);

        $grades = new advwork_final_grades();

        if (has_capability('mod/advwork:submit', $this->context, $userid)) {
            if (!empty($gradebook->items[0]->grades)) {
                $submissiongrade = reset($gradebook->items[0]->grades);
                if (!is_null($submissiongrade->grade)) {
                    if (!$submissiongrade->hidden or has_capability('moodle/grade:viewhidden', $this->context, $userid)) {
                        $grades->submissiongrade = $submissiongrade;
                        $grades->instanceitemid = $this->id;
                    }
                }
            }
        }

        if (has_capability('mod/advwork:peerassess', $this->context, $userid)) {
            if (!empty($gradebook->items[1]->grades)) {
                $assessmentgrade = reset($gradebook->items[1]->grades);
                if (!is_null($assessmentgrade->grade)) {
                    if (!$assessmentgrade->hidden or has_capability('moodle/grade:viewhidden', $this->context, $userid)) {
                        $grades->assessmentgrade = $assessmentgrade;
                    }
                }
            }
        }

        if (!is_null($grades->submissiongrade) or !is_null($grades->assessmentgrade)) {
            return $grades;
        }

        return false;
    }

    /**
     * Get all the grade items from the database
     *
     * @param $DB           Moodle Database API object
     * @param $courseid     id of the course to get the grade items for
     * @return array with the grade items
     */
    private function get_grade_items_instances($DB, $courseid) {
        $sql = 'SELECT DISTINCT iteminstance FROM mdl_grade_items WHERE courseid = ?';
        $params = array($courseid);
        $result = $DB->get_records_sql_menu($sql,$params);

        return array_keys($result);
    }

    /**
     * Used to populate the request object to the BNS with peer assessments
     *
     * @param $requestdata                  Request data to the BNS
     * @param $peerassessments              Peer assessments
     */
    function get_peer_assessments_data($requestdata, $peerassessments) {
        $peerassessmentsproperty = "peer-assessments";
        $requestdata->$peerassessmentsproperty = [];
        foreach ($peerassessments as $peergrade) {
            $reviewerid = "reviewer id";
            $submissionauthorid = "submission author id";
            $peergrade->grade = number_format((float)$peergrade->grade / 100, 2, '.', '');
            $submissionauthoridproperty = $peergrade->$submissionauthorid;
            $requestdata->$peerassessmentsproperty[$peergrade->$reviewerid][$submissionauthoridproperty] = $peergrade->grade;
        }
    }

    /**
     * Used to populate the request object to the BNS with teacher grades
     *
     * @param $requestdata              Request data to the BNS
     * @param $teachergrades            Teacher grades
     */
    function get_teacher_grades_data($requestdata, $teachergrades) {
        $teachergradesproperty = "teacher-grades";
        foreach ($teachergrades as $teachergrade) {
            $teachergradeproperty = "teacher grade";
            $teachergrade->$teachergradeproperty = number_format((float)$teachergrade->$teachergradeproperty / 100, 2, '.', '');
            $submissionauthoridproperty = "submission author id";
            $requestdata->$teachergradesproperty[$teachergrade->$submissionauthoridproperty] = $teachergrade->$teachergradeproperty;
        }
    }

    /**
     * Used to populate the request object to the BNS with the student models of the previous session
     *
     * @param $advwork                 The current advwork session
     * @param $requestdata              Request data to the BNS
     * @param $peerassessments          The assessments done by the students
     * @param $courseid                 Id of the course to get the student models for
     */
    function get_student_models_data($advwork, $requestdata, $peerassessments, $courseid) {
        $studentmodelsproperty = "student-models";

        foreach ($peerassessments as $peergrade) {
            $reviewerid = "reviewer id";
            $reviewersids[] = $peergrade->$reviewerid;
        }

        // find the last advwork session before the current one
        $previousadvworkid = $advwork->get_advwork_session_before_specified($courseid, $advwork->id);
        $previousadvworkid = reset($previousadvworkid)->id;
        if(empty($previousadvworkid)) {
            return;
        }

        // populate the request object with the student models for the previous session
        $previoussessionstudentmodels = $advwork->get_student_models($courseid, $previousadvworkid, 1);
        foreach ($previoussessionstudentmodels as $studentmodel) {
            // get the previous student models only for the students that attended this session
            if(is_null($reviewersids)) {
            	$reviewersids = Array();
            }
            if(in_array($studentmodel->userid, $reviewersids)) {
                $requestdata->$studentmodelsproperty[$studentmodel->userid][$studentmodel->capability][] = $studentmodel->probability;
            }
        }
    }

    /**
     * Used to populate the request object to the BNS with the parameters
     *
     * @param $requestdata          Request data to the BNS
     */
    function get_request_parameters($requestdata) {
        $parameters = new stdClass();
        $strategyproperty = "strategy";
        $terminationproperty = "termination";
        $mappingproperty = "mapping";
        $domainproperty = "domain";
        $parameters->$strategyproperty = "maxEntropy";
        $parameters->$terminationproperty = "corrected30";
        $parameters->$mappingproperty = "weightedSum";
        $parameters->$domainproperty = [1, 0.95, 0.85, 0.75, 0.65, 0.55, 0.0];
        $requestdata->parameters = $parameters;
    }

    /**
     * Used to send the session data to the BNS.
     * After receiving the student models, saves the new ones in the database and also the next best peer to be graded by the teacher
     *
     * @param $courseid             Id of the course for which we send the session data
     * @param $advwork             Id of the advwork instance for which we send the data
     * @param $sessiondata          The data to be sent to the BNS
     * @param $cumulated            Specifies whether we make a call with cumulated student models or not
     */
    function send_session_data_to_BNS($courseid, $advwork, $sessiondata, $cumulated, $deleteStudent=1) {
        $webservice = new WebServiceBN();

        $studentmodelsjsonresponse = $webservice->post_session_data($sessiondata);


        $studentmodels = json_decode($studentmodelsjsonresponse);

        if ($deleteStudent==1) {
            // delete if student models exist for the current advwork session
            $this->delete_student_models($courseid, $advwork->id, $cumulated);
        }


        $this->save_student_models_in_database($studentmodels, $courseid, $advwork->id, $cumulated);

        $advwork->save_next_best_peer_to_grade_in_database($courseid, $advwork->id, $studentmodels);
    }


    /**
     * Used to delete the student models for an advwork session (single session or cumulated)
     *
     * @param $courseid                 Course id
     * @param $advworkid               advwork session id
     * @param $cumulated                Flag to indicate whether the single session student models are deleted or the cumulated ones
     */
    function delete_student_models($courseid, $advworkid, $cumulated) {
        global $DB;

        $studentmodelstable = "advwork_student_models";
        $DB->delete_records($studentmodelstable, array('courseid' => $courseid, 'advworkid' => $advworkid, 'iscumulated' => $cumulated));
    }

    /**
     * Used to send the session data to the BNS
     * The session data is made of peer assessments and teacher grades
     * The session data could be individual for a single session or cumulated along multiple sessions
     * The cumulated session data is the data for a session and also the student models for the previous session (so for the first session there are no student models to send)
     *
     * @param $courseid                 Id of the course to send the session data
     * @param $advwork                 advwork instance to send the data
     * @param $courseteacher            Id of the teacher of the course
     */
    function send_session_data($courseid, $advwork, $courseteachersid, $cm) {
        $i=0;
        $allowedgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid); // any group in grouping
        if (count($allowedgroups) == 0) {
            $this->send_session_data_by_group($advwork, $courseteachersid, null, 1, $courseid);
        }

        foreach ($allowedgroups as $g) {
            if ($i == 0) { // delete all the student model in the DB only before adding the first group
                $deleteStudent = 1;
            } else {
                $deleteStudent = 0;
            }
            $this->send_session_data_by_group($advwork, $courseteachersid, $g, $deleteStudent, $courseid);
            $i++;
        }

    }

    /**
     * @param advwork $advwork
     * @param $courseteachersid
     * @param $g
     * @param int $i
     * @param Id $courseid
     * @return void
     */
    public function send_session_data_by_group($advwork, $courseteachersid, $g, $deleteStudent, $courseid) {

        if (is_null($g)) {
            $peergrades = $advwork->get_peer_grades($advwork->id, $courseteachersid);                 // modify this to receive a list with the teachers of the course
            $teachergrades = $advwork->get_teacher_grades($advwork->id, $courseteachersid);
        } else {
            $peergrades = $advwork->get_peer_grades_with_groups($advwork->id, $courseteachersid, $g->id);
            $teachergrades = $advwork->get_teacher_grades_with_groups($advwork->id, $courseteachersid, $g->id);
        }

        $requestdata = new student_models_request();

        $this->get_peer_assessments_data($requestdata, $peergrades);
        $this->get_teacher_grades_data($requestdata, $teachergrades);
        $this->get_request_parameters($requestdata);


        /*echo "------Request data student models not cumulated <br>";
        print_r(json_encode($requestdata, JSON_NUMERIC_CHECK));
        echo "------<br><br>";*/


        $this->send_session_data_to_BNS($courseid, $advwork, $requestdata, 0, $deleteStudent);


        $firstsession = $advwork->get_advwork_session_before_specified($courseid, $advwork->id);
        if (!empty($firstsession)) {
            $requestdatacumulated = new student_models_request();
            $requestdatacumulated->import($requestdata);
            $this->get_student_models_data($advwork, $requestdatacumulated, $peergrades, $courseid);

            /*
            echo "------Request data student models cumulated <br>";
            print_r(json_encode($requestdatacumulated, JSON_NUMERIC_CHECK));
            echo "------<br>";*/

            $this->send_session_data_to_BNS($courseid, $advwork, $requestdatacumulated, 1, $deleteStudent);
        }
    }

    /**
     * Normalize a value into 0-1 scale
     *
     * @param $mingrade         minimum possible grade
     * @param $maxgrade         maximum possible grade
     * @param $value            value to normalize
     * @return float|int        normalized value
     */
    private function normalize_value($mingrade, $maxgrade, $value) {
        return ($value - $mingrade) / ($maxgrade - $mingrade);
    }

    /**
     * Used to compute the standard deviation for an array of values.
     * It will raise a warning if you have fewer than 2 values in the array
     *
     * @param array $values                                                 values to compute the standard deviation for
     * @param bool $sample [optional] Defaults to false                     used to specify if it is a sample, not the entire population
     * @return float|bool                                                   the standard deviation or false on error
     */
    public function compute_standard_deviation(array $values, $sample = false) {
        $n = count($values);
        if ($n === 0) {
            trigger_error("The array has zero elements", E_USER_WARNING);
            return false;
        }
        if ($sample && $n === 1) {
            trigger_error("The array has only 1 element", E_USER_WARNING);
            return false;
        }
        $mean = array_sum($values) / $n;
        $carry = 0.0;
        foreach ($values as $val) {
            $d = ((double) $val) - $mean;
            $carry += $d * $d;
        };
        if ($sample) {
            --$n;
        }
        return sqrt($carry / $n);
    }


    /**
     * Compute the overall grades (overall submission and overall assessment grades) and also the reliability (stability and continuity) values
     *
     * @param $PAGE                                         Moodle PAGE API object
     * @param $itemsinstances                               graded items instances
     * @param $courseid                                     id of the course
     * @param $userid                                       id of the student to compute the grades for
     * @return advwork_overall_metrics          object with all the grades
     */
    public function compute_overall_grades($DB, $PAGE, $courseid, $userid) {
        $submissionsgrades = array();
        $assessmentsgrades = array();

        $itemsinstances = $this->get_grade_items_instances($DB, $courseid);
        foreach ($itemsinstances as $iteminstance) {
            // Read data via the Gradebook API
            $gradebook = grade_get_grades($courseid, 'mod', 'advwork', $iteminstance, $userid);

            if (has_capability('mod/advwork:submit', $PAGE->context, $userid)) {
                if (!empty($gradebook->items[0]->grades)) {
                    $submissiongrade = reset($gradebook->items[0]->grades);
                    if (!is_null($submissiongrade->grade)) {
                        if (!$submissiongrade->hidden or has_capability('moodle/grade:viewhidden', $PAGE->context, $userid)) {
                            $submissionsgrades[] = $submissiongrade->grade;
                        }
                    }
                }
            }

            if (has_capability('mod/advwork:peerassess', $PAGE->context, $userid)) {
                if (!empty($gradebook->items[1]->grades)) {
                    $assessmentgrade = reset($gradebook->items[1]->grades);
                    if (!is_null($assessmentgrade->grade)) {
                        if (!$assessmentgrade->hidden or has_capability('moodle/grade:viewhidden', $PAGE->context, $userid)) {
                            $assessmentsgrades[] = $assessmentgrade->grade;
                        }
                    }
                }
            }
        }

        $studentgrades = $this->get_student_grades($courseid, $userid);

        $overallgrades = new advwork_overall_grades();
        $overallgrades->overallsubmissiongrade = $this->compute_overall_submission_grade($submissionsgrades);
        $overallgrades->overallassessmentgrade = $this->compute_overall_assessment_grade($assessmentsgrades);

        $overallgrades->reliability_metrics = new advwork_reliability_metrics();
        $sessionsattendance = $this->compute_sessions_attendance($courseid, $userid);
        $overallgrades->reliability_metrics->continuitysessionlevel = $this->compute_continuity_session_level($sessionsattendance);
        $overallgrades->reliability_metrics->continuitychunks = $this->compute_continuity_based_on_chunks($sessionsattendance);

        if(count($studentgrades) <= 1 || empty($this->compute_standard_deviation($studentgrades))) {
            $overallgrades->reliability_metrics->stability = 0;
        } else {
            $overallgrades->reliability_metrics->stability = 100 - ($this->compute_standard_deviation($studentgrades) * 100);
        }

        $overallgrades->reliability_metrics->reliability = $this->compute_reliability($overallgrades->reliability_metrics->continuitysessionlevel, $overallgrades->reliability_metrics->stability);

        return $overallgrades;
    }

    /**
     * Used to get the student grades for all the sessions the student attended
     * A student grade is the C from the student model (correctness of the answer)
     *
     * @param $courseid         Course to get the student grades for
     * @param $userid           Id of the user to get the grades for
     * @return array            Grades of the student
     */
    function get_student_grades($courseid, $userid) {
        global $DB;

        $studentmodelsallsessions = $this->get_student_models_all_sessions($DB, $courseid, $userid);
        $studentmodels = $studentmodelsallsessions->studentmodels;

        $studentgrades = [];
        foreach ($studentmodels as $studentmodel) {
            $capabilityname = "C";

            $capabilityentries = array_filter($studentmodel->entries, function ($entry) use ($capabilityname) {
                return $entry->capability == $capabilityname;
            });

            $capabilityentry = reset($capabilityentries);
            $studentgrades[] = $capabilityentry->capabilityoverallvalue;
        }

        return $studentgrades;
    }

    /**
     * Used to get the submissions grades for the specified session
     *
     * @param $advwork             advwork instance
     * @param $courseid             Id of the course
     * @return array with the grades for the submissions
     */
    function get_submissions_grades_for_session($advwork, $courseid) {
        global $DB;

        $studentsenrolledtocourse = $this->get_students_enrolled_to_course($DB, $courseid);
        $submissionsgrades = [];
        foreach ($studentsenrolledtocourse as $student) {
            $studentmodel = $this->get_student_model($courseid, $advwork->id, $student->userid, false);

            $capabilityname = "C";

            $capabilityentries = array_filter($studentmodel, function ($entry) use ($capabilityname) {
                return $entry->capability == $capabilityname;
            });

            $capabilityentry = reset($capabilityentries);
            $submissionsgrades[] = $capabilityentry->capabilityoverallvalue;
        }

        return $submissionsgrades;
    }

    /**
     * Computes the average of the submissions' grades for a session
     *
     * @param $advwork                 advwork instance
     * @param $courseid                 Id of the course
     * @return float|int                Average of the submissions' grades for a session
     */
    function compute_average_submission_grade_session($advwork, $courseid) {
        $submissionsgradessession = $this->get_submissions_grades_for_session($advwork, $courseid);
        $averagesubmissiongradesession = $this->compute_average_value($submissionsgradessession);
		
        if(empty($submissionsgradessession) || $averagesubmissiongradesession == -1.0) {
            return 0;
        }

        return $averagesubmissiongradesession;
    }

    /**
     * Used to get from the database the overall grades
     *
     * @param $DB                               Moodle Database API object
     * @param $courseid                         id of the course
     * @param $userid                           id of the student
     * @return advwork_overall_grades          object with the overall grades
     */
    function get_overall_grades($DB, $courseid, $userid) {
        $advworkoverallgradestable = 'advwork_overall_grades';

        if($DB->record_exists($advworkoverallgradestable, array('courseid' => $courseid, 'userid' => $userid))) {
            $record = $DB->get_record($advworkoverallgradestable, array('courseid' => $courseid, 'userid' => $userid));
        }

        $overallgrades = new advwork_overall_grades();
        $overallgrades->overallsubmissiongrade = $record->overallsubmissiongrade;
        $overallgrades->overallassessmentgrade = $record->overallassessmentgrade;

        $overallgrades->reliability_metrics = new advwork_reliability_metrics();
        $overallgrades->reliability_metrics->continuitysessionlevel = $record->continuitysessionlevel;
        $overallgrades->reliability_metrics->continuitychunks = $record->continuitychunks;
        $overallgrades->reliability_metrics->stability = $record->stability;
        $overallgrades->reliability_metrics->reliability = $record->reliability;
        return $overallgrades;
    }

    /**
     * Computes the average value in an array of values
     * First it filters the array
     *
     * @param $values           array with values
     * @return float|int        average value
     */
    function compute_average_value($values) {
        $filteredvalues = array_filter($values);
        if (count($filteredvalues)==0) {
        	return -1.0;
        }
        return array_sum($filteredvalues)/count($filteredvalues);
    }

    /**
     * Used to compute the overall submission grade based on the grades received by a student for his submissions
     * The overall submission grade is the average of the grades received for his submissions
     *
     * @param $submissionsgrades    grades for the student submissions
     * @return float|int            overall submission grade
     */
    function compute_overall_submission_grade($submissionsgrades) {
        if(empty($submissionsgrades)) {
            return 0;
        }

        return $this->compute_average_value($submissionsgrades);
    }

    /**
     * Used to compute the overall assessment grade based on the grades received by a student for his assessments
     * The overall assessment grade is the average of the grades received for his assessments
     *
     * @param $assessmentsgrades    grades for the student assessments
     * @return float|int            overall assessment grade
     */
    function compute_overall_assessment_grade($assessmentsgrades) {
        if(empty($assessmentsgrades)) {
            return 0;
        }

        return $this->compute_average_value($assessmentsgrades);
    }


    /**
     * Used to computed the sessions attendance
     * The attendance is represented as a bit array where 1 means attendance and 0 means no attendance
     * E.g. 1010 -> the student attended sessions 1 and 3
     *
     * @param $courseid     id of the course to compute the attendance
     * @param $userid       user to compute the attendance for
     * @return array        bit array with the sessions attendance
     */
    function compute_sessions_attendance($courseid, $userid) {
        global $DB;

        $sessionsobj = $DB->get_records_sql("SELECT id FROM `mdl_advwork` WHERE course = :course ORDER BY id", array("course" => $courseid));
        $attendedsessionsobj = $DB->get_records_sql("SELECT advworkid FROM `mdl_advwork_submissions` WHERE advworkid IN 
                                                            (SELECT id FROM `mdl_advwork` WHERE course = :courseid) and authorid = :authorid
                                                            ORDER BY advworkid", array("courseid" => $courseid, "authorid" => $userid));

        $sessions = [];
        foreach ($sessionsobj as $session) {
            $sessions[] = $session->id;
        }

        $attendedsessions = [];
        foreach ($attendedsessionsobj as $attendedsession) {
            $attendedsessions[] = $attendedsession->advworkid;
        }

        $sessionsattendance = [];
        foreach($sessions as $session) {
            if(in_array($session, $attendedsessions)) {
                $sessionsattendance[] = 1;
            } else {
                $sessionsattendance[] = 0;
            }
        }

        return $sessionsattendance;
    }


    /**
     * Computes the reliability
     * Reliability is 0.6 * continuity + 0.4 * stability
     *
     * @param $continuity           continuity
     * @param $stability            stability
     * @return float|int            reliability
     */
    function compute_reliability($continuity, $stability) {
        $continuityweight = 0.6;
        $stabilityweight = 0.4;
        return $continuity * $continuityweight + $stability * $stabilityweight;
    }

    /**
     * Compute the continuity factor for the specified user at session level
     * The algorithm takes into account the number of attended sessions by the student and also their relative time position to the end of the course
     *
     * @param $sessionsattendance   bit array with the attendance to the sessions
     * @return float|int            value of the continuity
     */
    function compute_continuity_session_level($sessionsattendance) {
        $elementscount = count($sessionsattendance);
        $firstsessionweight = 2/($elementscount*($elementscount+1));
        $continuity = 0;
        for($i = 1; $i <= $elementscount; $i++) {
            $currentelementweight = $firstsessionweight * $i;

            if($sessionsattendance[$i-1]) {
                $continuity = $continuity + $currentelementweight;
            }
        }

        return $continuity * 100;
    }

    /**
     * Computes the continuity of a chunk
     * The continuity is the number of attended sessions in the chunk divided by the number of sessions in the chunk
     *
     * @param $chunk            chunk with sessions
     * @return float|int        continuity of the chunk
     */
    function compute_chunk_continuity($chunk) {
        $attendedsessions = 0;
        foreach($chunk as $element) {
            if($element) {
                $attendedsessions++;
            }
        }

        $sessionsinchunk = count($chunk);
        return $attendedsessions / $sessionsinchunk;
    }

    /**
     * Computes the weight of the chunk with the oldest sessions
     * The weight of the oldest chunk is 2 / ($chunkscount * ($chunkscount + 1))
     *
     * @param $chunkscount          number of chunks
     * @return float|int            weight of the oldest chunk
     */
    function compute_oldest_chunk_weight($chunkscount) {
        return 2 / ($chunkscount * ($chunkscount + 1));
    }

    /**
     * Computes the continuity based on chunks
     * The sessions are split in chunks based on their relative timp position from the end of the course.
     * Compute the continuity of each chunk. Each chunk continuity has a weight in the continuity of the student. The chunks with sessions more recent have a higher weight.
     *
     * @param $sessionsattendance       attendance of the student to sessions
     * @return float|int                continuity of the student based on this algorithm
     */
    function compute_continuity_based_on_chunks($sessionsattendance) {
        // split the sessions into chunks
        $sessionsattendance = array_reverse($sessionsattendance);   // the most recent chunks have one additional session
        $sessionscount = count($sessionsattendance);
        $chunks = [];

        // if there are less than 6 session, there are special cases
        if($sessionscount < 6) {
            switch ($sessionscount) {
                // a chunk with one session
                case 1:
                    $chunks = array_chunk($sessionsattendance, 1);
                    break;
                // two chunks with one session each
                case 2:
                    $chunks = array_chunk($sessionsattendance, 1);
                    break;
                // two chunks: one with two sessions and one with a single sessions
                case 3:
                    $chunks = array_chunk($sessionsattendance, 2);
                    break;
                // two chunks with two sessions each
                case 4:
                    $chunks = array_chunk($sessionsattendance, 2);
                    break;
                // two chunks: one with two sessions and one with two sessions
                case 5:
                    $chunks = array_chunk($sessionsattendance, 3);
                    break;
                }
            } else {
            // chunks are made of two or three sessions
            // 6 sessions --> 2 chunks: 3, 3
            // 7 --> 3 chunks: 3, 2, 2
            // 8 --> 3 chunks: 3, 3, 2
            // 9 --> 3 chunks: 3, 3, 3
            // 10 --> 4 chunks: 3, 3, 2, 2
            // 13 --> 5 chunks: 3, 3, 3, 2, 2
            // if the number of sessions is disible by three each chunks has three sessions
            if($sessionscount % 3 == 0) {
                $chunks = array_chunk($sessionsattendance, 3);
            } else {
                // the chunks with the most recent sessions have 3 sessions, others have 2
                $chunkswithextraelement = $sessionscount % (ceil($sessionscount / 3));       // these chunks have 3 elements
                for($i = 1; $i <= $chunkswithextraelement; $i++) {
                    $chunks[] = array_slice($sessionsattendance, 0, 3);
                    array_splice($sessionsattendance, 0, 3);
                }

                $chunksoftwo = array_chunk($sessionsattendance, 2);
                $chunks = array_merge($chunks, $chunksoftwo);
            }
        }

        $continuity = 0;

        // if there are at least 6 sessions, then we have chunks of 3 and 2 elements and each chunk weight is computed based on the oldest chunk weight (see compute_oldest_chunk_weight method)
        if($sessionscount >= 6) {
            $chunkindex = 1;
            $chunkscount = count($chunks);
            $oldestchunkweight = $this->compute_oldest_chunk_weight($chunkscount);

            foreach ($chunks as $chunk) {
                $continuity += ($this->compute_chunk_continuity($chunk) * ($oldestchunkweight * ($chunkscount - $chunkindex + 1)));
                $chunkindex++;
            }
        } else if($sessionscount >= 2 && $sessionscount < 6) {
            // there are 2 chunks: the chunk with the newest sessions has a weight of 0.7 and the other has a weight of 0.3
            $continuity = $this->compute_chunk_continuity($chunks[0]) * 0.7 + $this->compute_chunk_continuity($chunks[1]) * 0.3;
        } else {
            // a single chunk
            $continuity = $this->compute_chunk_continuity($chunks[0]);
        }

        return $continuity * 100;
    }

    /**
     * Get the models of the specified student for all the advwork sessions
     *
     * @param $DB                               Moodle Database API object
     * @param $advwork                         advwork instance
     * @param $courseid                         id of the current course
     * @param $userid                           id of the student to get the models for
     * @return advwork_student_models          models of the student
     */
    function get_student_models_all_sessions($DB, $courseid, $userid) {
        $courseadvworks = $this->get_course_advwork_sessions($DB, $courseid);

        $studentmodels = new advwork_student_models();
        $studentmodels->studentmodels = [];
        foreach ($courseadvworks as $session) {
            $advworkid = $session->id;
            $advworkstudentmodelresponse = $this->get_student_model($courseid, $advworkid, $userid, true);
            if (empty($advworkstudentmodelresponse)) {
                $advworkstudentmodelresponse = $this->get_student_model($courseid, $advworkid, $userid, false);
            }
            $studentmodel = new advwork_student_model();
            $studentmodel->advworkid = $session->id;
            $studentmodel->advworkname = $session->name;
            $studentmodel->entries = $advworkstudentmodelresponse;
            $studentmodels->studentmodels[] = $studentmodel;
        }

        return $studentmodels;
    }

    /**
     * Get the general model of the student
     * The general model is the model computed after the last session of advwork the student attended
     * The algorithm looks for the model in the last advwork session, in case there is no model searches in the previous one
     * and so on until a model is found
     *
     * @param $DB                           Moodle Database API object
     * @param $advwork                     advwork instance
     * @param $courseid                     id of the current course
     * @param $userid                       id of the student to get the general model for
     * @return advwork_student_model       general model of the student
     */
    function get_general_student_model($courseid, $userid) {
        $generalstudentmodelresponse = [];

        $advworkidlastsession = $this->get_last_advwork_session_id_student_attended($courseid, $userid);
        if(!empty($advworkidlastsession)) {
            $generalstudentmodelresponse = $this->get_student_model($courseid, $advworkidlastsession, $userid, true);
            if (empty($generalstudentmodelresponse)) {
                $generalstudentmodelresponse = $this->get_student_model($courseid, $advworkidlastsession, $userid, false);
            }
        }

        $generalstudentmodel = new advwork_student_model();
        $generalstudentmodel->userid = $userid;
        $generalstudentmodel->entries = $generalstudentmodelresponse;
        return $generalstudentmodel;
    }

    /**
     * Get the id of the last advwork session that the student attended
     *
     * @param $courseid                 id of the current course
     * @param $userid                   id of the student to get the last advwork session he attended
     * @return the id of the last advwork session attended by student
     */
    function get_last_advwork_session_id_student_attended($courseid, $userid) {
        $lastadvworkid = $this->get_last_advwork_session_for_course($courseid)->id;
        $generalstudentmodelresponse = [];
        $advworkidtocheck = $lastadvworkid;

        // get the student model for the last session the student has a model for
        while(empty($generalstudentmodelresponse)) {
            $generalstudentmodelresponse = $this->get_student_model($courseid, $advworkidtocheck, $userid, true);
            if (empty($generalstudentmodelresponse)) {
                $generalstudentmodelresponse = $this->get_student_model($courseid, $advworkidtocheck, $userid, false);
            }
            if(!empty($generalstudentmodelresponse)) {
                return $advworkidtocheck;
            }
            $advworkidtocheck = $this->get_advwork_session_before_specified($courseid, $advworkidtocheck);
            $advworkidtocheck = reset($advworkidtocheck)->id;
            if(empty($advworkidtocheck)) {
                break;
            }
        }

        return null;
    }

    /**
     * Get the student model before specified advwork session
     *
     * @param $courseid                             id of the current course
     * @param $advworkid                           id of the advwork instance to find a session before
     * @param $userid                               id of the student to get the model
     * @param $iscumulated                          specifies whether the model is cumulated or not
     * @return null|advwork_student_model          model of the student before the specified advwork session, otherwise empty
     */
    public function get_student_model_before_specified_session($courseid, $advworkid, $userid, $iscumulated) {
        $studentmodelresponse = [];

        $advworkidtocheck = $this->get_advwork_session_before_specified($courseid, $advworkid);
        $advworkidtocheck = reset($advworkidtocheck)->id;
        if(empty($advworkidtocheck)) {
            return null;
        }
        $studentmodelresponse = $this->get_student_model($courseid, $advworkidtocheck, $userid, $iscumulated);

        if(empty($studentmodelresponse)) {
            return null;
        }

        $studentmodel = new advwork_student_model();
        $studentmodel->userid = $userid;
        $studentmodel->entries = $studentmodelresponse;
        return $studentmodel;
    }

    /**
     * Used to get the teacher of the specified course
     *
     * @param $courseid         Id of a course
     * @return                  Teacher of the course
     */
    public function get_course_teachers($courseid){
        global $DB;

        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $coursecontext = context_course::instance($courseid);
        $courseteachers = get_role_users($teacherroleid, $coursecontext);
        $courseteachersids = array_column($courseteachers, 'id');

        return $courseteachersids;
    }

    public function get_number_peers($sessionid) {
        global $DB;
        return $DB->get_records_sql("SELECT advworkid, COUNT(id) as number FROM mdl_advwork_submissions WHERE advworkid=$sessionid GROUP by advworkid");
    }

    /**
     * Used to get the peer grades for the specified session
     *
     * @param $sessionid                    Id of the session
     * @param $teachersids                  Id of the teachers of the course
     * @return array with peer grades
     */
    public function get_peer_grades($sessionid, $teachersids) {
        global $DB;

        $teachersidsparameter = "";
        foreach ($teachersids as $teacherid) {
            $teachersidsparameter .= $teacherid;
            $teachersidsparameter .= ",";
        }
        $teachersidsparameter = substr_replace($teachersidsparameter, "", -1);
        
        //return $DB->get_records_sql('CALL sp_get_peer_grades (?, ?);', array($sessionid, $teachersidsparameter));

        return $DB->get_records_sql("SELECT concat(reviewers.id, submissions.`Author Id`) as id, reviewers.id as 'Reviewer Id', submissions.`Author Id` as 'Submission Author Id', assessments.grade as 'Grade'
   FROM (
       SELECT enhancedwork.name as 'advwork Session', submissions.id as 'Submission Id', authors.id as 'Author Id', CONCAT(authors.FIRSTNAME, ' ', authors.LASTNAME) as 'Submission Author', 
   FROM_UNIXTIME(submissions.timecreated) as 'Time Submitted', submissions.grade as 'Grade'
   FROM mdl_advwork_submissions as submissions 
   JOIN mdl_user AS authors ON authors.id = authorid
   JOIN mdl_advwork as enhancedwork on enhancedwork.id = submissions.advworkid
   WHERE submissions.advworkid = $sessionid ORDER BY submissions.grade DESC
   )
   as submissions
   JOIN mdl_advwork_assessments as assessments ON assessments.submissionid = submissions.`Submission Id`
   JOIN mdl_user AS reviewers ON reviewers.id = assessments.reviewerid
   WHERE assessments.reviewerid NOT IN ($teachersidsparameter);");

    }
    /**
     * Used to get the peer grades for the specified session and for specified group
     *
     * @param $sessionid                    Id of the session
     * @param $teachersids                  Id of the teachers of the course
     * @return array with peer grades
     */
    public function get_peer_grades_with_groups($sessionid, $teachersids, $groupid) {
        global $DB;

        $teachersidsparameter = "";
        foreach ($teachersids as $teacherid) {
            $teachersidsparameter .= $teacherid;
            $teachersidsparameter .= ",";
        }
        $teachersidsparameter = substr_replace($teachersidsparameter, "", -1);
        //echo "teachersidsparameter: $teachersidsparameter<br>";

        return $DB->get_records_sql("SELECT concat(reviewers.id, submissions.`Author Id`) as id, reviewer_group.groupid as r_group , reviewers.id as 'Reviewer Id', submissions.`Author Id` as 'Submission Author Id', assessments.grade as 'Grade', submisioner_group.groupid as s_group
   FROM (
       SELECT enhancedwork.name as 'advwork Session', submissions.id as 'Submission Id', authors.id as 'Author Id', CONCAT(authors.FIRSTNAME, ' ', authors.LASTNAME) as 'Submission Author', 
   FROM_UNIXTIME(submissions.timecreated) as 'Time Submitted', submissions.grade as 'Grade'
   FROM mdl_advwork_submissions as submissions 
   JOIN mdl_user AS authors ON authors.id = authorid
   JOIN mdl_advwork as enhancedwork on enhancedwork.id = submissions.advworkid
   WHERE submissions.advworkid = $sessionid ORDER BY submissions.grade DESC 
   )
   as submissions
   JOIN mdl_advwork_assessments as assessments ON assessments.submissionid = submissions.`Submission Id`
   JOIN mdl_user AS reviewers ON reviewers.id = assessments.reviewerid
   JOIN (SELECT groupings.groupingid, groupings.groupid, member.userid FROM mdl_groupings_groups as groupings join mdl_groups_members as member on groupings.groupid = member.groupid WHERE groupings.groupid=$groupid) as reviewer_group on reviewer_group.userid = reviewers.id
   JOIN (SELECT groupings.groupingid, groupings.groupid, member.userid FROM mdl_groupings_groups as groupings join mdl_groups_members as member on groupings.groupid = member.groupid WHERE groupings.groupid=$groupid) as submisioner_group on submisioner_group.userid = submissions.`Author Id`
   WHERE assessments.reviewerid NOT IN ($teachersidsparameter);
");

    }

    /**
     * Used to get the teacher grades for the specified session and for specified group
     *
     * @param $sessionid                    Id of the session
     * @param $teacherid                    Id of the teacher of the course
     * @return array with the teacher grades
     */
    public function get_teacher_grades_with_groups($sessionid, $teachersids, $groupid) {
        global $DB;

        $teachersidsparameter = "";
        foreach ($teachersids as $teacherid) {
            $teachersidsparameter .= $teacherid;
            $teachersidsparameter .= ",";
        }
        $teachersidsparameter = substr_replace($teachersidsparameter, "", -1);
		echo "teachersidsparameter: $teachersidsparameter groupid: $groupid<br>";

        return $DB->get_records_sql("SELECT submissions.`Author Id` as 'Submission Author Id', assessments.reviewerid as 'Teacher Id', assessments.Grade as 'Teacher Grade' 
        FROM ( SELECT enhancedwork.name as 'advwork Session', submissions.id as 'Submission Id', authors.id as 'Author Id', CONCAT(authors.FIRSTNAME, ' ', authors.LASTNAME) as 'Submission Author', FROM_UNIXTIME(submissions.timecreated) as 'Time Submitted', submissions.grade as 'Grade' 
               FROM mdl_advwork_submissions as submissions JOIN mdl_user AS authors ON authors.id = authorid JOIN mdl_advwork as enhancedwork on enhancedwork.id = submissions.advworkid 
               WHERE submissions.advworkid = $sessionid
               ORDER BY submissions.grade DESC ) as submissions JOIN mdl_advwork_assessments as assessments ON assessments.submissionid = submissions.`Submission Id` 
               JOIN (SELECT groupings.groupingid, groupings.groupid, member.userid FROM mdl_groupings_groups as groupings join mdl_groups_members as member on groupings.groupid = member.groupid WHERE groupings.groupid=$groupid) as submisioner_group on submisioner_group.userid = submissions.`Author Id` 
        WHERE assessments.reviewerid IN ($teachersidsparameter)");

    }
    /**
     * @param $advworkid
     * @param $submissionid
     * @return $authorid
     * @throws dml_exception
     */
    function get_author_by_submission($advworkid, $submissionid) {
        global $DB;

        $result = $DB->get_records_sql("SELECT  submissions.id as 'submissionid', authors.id as 'authorid'
   FROM mdl_advwork_submissions as submissions 
   JOIN mdl_user AS authors ON authors.id = authorid
   JOIN mdl_advwork as enhancedwork on enhancedwork.id = submissions.advworkid
   WHERE submissions.advworkid = $advworkid AND submissions.id=$submissionid");


        $authorid = $result[$submissionid]->authorid;
        return $authorid;
    }

    /**
     * Used to get the teacher grades for the specified session
     *
     * @param $sessionid                    Id of the session
     * @param $teacherid                    Id of the teacher of the course
     * @return array with the teacher grades
     */
    public function get_teacher_grades($sessionid, $teachersids) {
        global $DB;

        $teachersidsparameter = "";
        foreach ($teachersids as $teacherid) {
            $teachersidsparameter .= $teacherid;
            $teachersidsparameter .= ",";
        }
        $teachersidsparameter = substr_replace($teachersidsparameter, "", -1);

        //return $DB->get_records_sql('CALL sp_get_teacher_grades (?, ?);', array($sessionid, $teachersidsparameter));
        return $DB->get_records_sql("SELECT submissions.`Author Id` as 'Submission Author Id', assessments.reviewerid as 'Teacher Id', assessments.Grade as 'Teacher Grade' 
        FROM ( SELECT enhancedwork.name as 'advwork Session', submissions.id as 'Submission Id', authors.id as 'Author Id', CONCAT(authors.FIRSTNAME, ' ', authors.LASTNAME) as 'Submission Author', FROM_UNIXTIME(submissions.timecreated) as 'Time Submitted', submissions.grade as 'Grade' 
               FROM mdl_advwork_submissions as submissions JOIN mdl_user AS authors ON authors.id = authorid JOIN mdl_advwork as enhancedwork on enhancedwork.id = submissions.advworkid 
               WHERE submissions.advworkid = $sessionid 
               ORDER BY submissions.grade DESC ) as submissions JOIN mdl_advwork_assessments as assessments ON assessments.submissionid = submissions.`Submission Id` 
        WHERE assessments.reviewerid IN ($teachersidsparameter)");

    }


    /**
     * Get the models of the students for the specified advwork session
     *
     * @param $DB                   Moodle Database API object
     * @param $courseid             id of the course for the advwork instance
     * @param $advworkid           id of the advwork session
     * @param $iscumulated          Specifies whether we should take the models for the individual single session or the cumulated ones
     * @return array with the models
     */
    public function get_student_models($courseid, $advworkid, $iscumulated) {
        global $DB;

        return $DB->get_records_sql('SELECT sm.id, sm.courseid, sm.advworkid, sm.userid, c.name as \'capability\', dm.value as \'domainvalue\', 
                                        sm.probability, sm.capabilityoverallgrade, sm.capabilityoverallvalue
                                        FROM mdl_advwork_student_models sm INNER JOIN 
                                             mdl_advwork_capabilities c on sm.capabilityid = c.id INNER JOIN
                                             mdl_advwork_domain_values dm on sm.domainvalueid = dm.id
                                        WHERE sm.courseid = ? AND sm.advworkid = ? AND sm.iscumulated = ?
                                        ORDER BY sm.userid', array($courseid, $advworkid, $iscumulated));
    }

    /**
     * Get the model of the specified student for the specified advwork session
     *
     * @param $courseid             id of the course for the advwork instance
     * @param $advworkid           id of the advwork session
     * @param $studentid            id of the student
     * @param $iscumulated          Specifies whether we should take the models for the individual single session or the cumulated ones
     * @return array with records of the student model
     */
    public function get_student_model($courseid, $advworkid, $studentid, $iscumulated) {
        global $DB;

        return $DB->get_records_sql('SELECT sm.id, sm.courseid, sm.advworkid, sm.userid, c.name as \'capability\', dm.value as \'domainvalue\', 
                                        sm.probability, sm.capabilityoverallgrade, sm.capabilityoverallvalue
                                        FROM mdl_advwork_student_models sm INNER JOIN 
                                             mdl_advwork_capabilities c on sm.capabilityid = c.id INNER JOIN
                                             mdl_advwork_domain_values dm on sm.domainvalueid = dm.id
                                        WHERE sm.courseid = ? AND sm.advworkid = ? AND sm.userid = ? AND sm.iscumulated = ? 
                                        ORDER BY sm.userid', array($courseid, $advworkid, $studentid, $iscumulated));
    }

    /**
     * Get all the capabilities defined in the system used to model the student
     *
     * @return array with all the capabilities defined in the system
     */
    function get_capabilities() {
        global $DB;

        $capabilitiesTable = "advwork_capabilities";
        return $DB->get_records($capabilitiesTable);
    }

    /**
     * Get the advwork sessions for a course
     *
     * @param $DB           Moodle Database API object
     * @param $courseid     id of the course to get the advwork sessions for
     */
    public function get_course_advwork_sessions($DB, $courseid) {
        return $DB->get_records_sql('SELECT * FROM mdl_advwork WHERE course = ?',array($courseid));
    }

    /**
     * Get the last advwork session for a course
     *
     * @param $courseid     id of the course to get the last advwork session
     * @return              record with the last advwork session
     */
    public function get_last_advwork_session_for_course($courseid) {
        global $DB;
        return $DB->get_record_sql('SELECT * FROM `mdl_advwork` WHERE course = ? AND id IN (SELECT MAX(id) FROM `mdl_advwork` WHERE course = ?)', array($courseid, $courseid));
    }

    /**
     * Get the last advwork session before a specified one for a course
     *
     * @param $courseid                 id of the course to get the session
     * @param $advworkid               session we should find another session before
     * @return the desired session (it is an array because get_record_sql does not support limit statement so we should exact the first one)
     */
    public function get_advwork_session_before_specified($courseid, $advworkid) {
        global $DB;
        return $DB->get_records_sql('SELECT * FROM mdl_advwork WHERE course = ? AND id < ? ORDER BY id DESC LIMIT 1', array($courseid, $advworkid));
    }

    /**
     * Return the editor options for the submission content field.
     *
     * @return array
     */
    public function submission_content_options() {
        global $CFG;
        require_once($CFG->dirroot.'/repository/lib.php');

        return array(
            'trusttext' => true,
            'subdirs' => false,
            'maxfiles' => $this->nattachments,
            'maxbytes' => $this->maxbytes,
            'context' => $this->context,
            'return_types' => FILE_INTERNAL | FILE_EXTERNAL,
          );
    }

    /**
     * Return the filemanager options for the submission attachments field.
     *
     * @return array
     */
    public function submission_attachment_options() {
        global $CFG;
        require_once($CFG->dirroot.'/repository/lib.php');

        $options = array(
            'subdirs' => true,
            'maxfiles' => $this->nattachments,
            'maxbytes' => $this->maxbytes,
            'return_types' => FILE_INTERNAL | FILE_CONTROLLED_LINK,
        );

        $filetypesutil = new \core_form\filetypes_util();
        $options['accepted_types'] = $filetypesutil->normalize_file_types($this->submissionfiletypes);

        return $options;
    }

    /**
     * Return the editor options for the overall feedback for the author.
     *
     * @return array
     */
    public function overall_feedback_content_options() {
        global $CFG;
        require_once($CFG->dirroot.'/repository/lib.php');

        return array(
            'subdirs' => 0,
            'maxbytes' => $this->overallfeedbackmaxbytes,
            'maxfiles' => $this->overallfeedbackfiles,
            'changeformat' => 1,
            'context' => $this->context,
            'return_types' => FILE_INTERNAL,
        );
    }

    /**
     * Return the filemanager options for the overall feedback for the author.
     *
     * @return array
     */
    public function overall_feedback_attachment_options() {
        global $CFG;
        require_once($CFG->dirroot.'/repository/lib.php');

        $options = array(
            'subdirs' => 1,
            'maxbytes' => $this->overallfeedbackmaxbytes,
            'maxfiles' => $this->overallfeedbackfiles,
            'return_types' => FILE_INTERNAL | FILE_CONTROLLED_LINK,
        );

        $filetypesutil = new \core_form\filetypes_util();
        $options['accepted_types'] = $filetypesutil->normalize_file_types($this->overallfeedbackfiletypes);

        return $options;
    }

    /**
     * Performs the reset of this advwork instance.
     *
     * @param stdClass $data The actual course reset settings.
     * @return array List of results, each being array[(string)component, (string)item, (string)error]
     */
    public function reset_userdata(stdClass $data) {

        $componentstr = get_string('pluginname', 'advwork').': '.format_string($this->name);
        $status = array();

        if (!empty($data->reset_advwork_assessments) or !empty($data->reset_advwork_submissions)) {
            // Reset all data related to assessments, including assessments of
            // example submissions.
            $result = $this->reset_userdata_assessments($data);
            if ($result === true) {
                $status[] = array(
                    'component' => $componentstr,
                    'item' => get_string('resetassessments', 'mod_advwork'),
                    'error' => false,
                );
            } else {
                $status[] = array(
                    'component' => $componentstr,
                    'item' => get_string('resetassessments', 'mod_advwork'),
                    'error' => $result,
                );
            }
        }

        if (!empty($data->reset_advwork_submissions)) {
            // Reset all remaining data related to submissions.
            $result = $this->reset_userdata_submissions($data);
            if ($result === true) {
                $status[] = array(
                    'component' => $componentstr,
                    'item' => get_string('resetsubmissions', 'mod_advwork'),
                    'error' => false,
                );
            } else {
                $status[] = array(
                    'component' => $componentstr,
                    'item' => get_string('resetsubmissions', 'mod_advwork'),
                    'error' => $result,
                );
            }
        }

        if (!empty($data->reset_advwork_phase)) {
            // Do not use the {@link advwork::switch_phase()} here, we do not
            // want to trigger events.
            $this->reset_phase();
            $status[] = array(
                'component' => $componentstr,
                'item' => get_string('resetsubmissions', 'mod_advwork'),
                'error' => false,
            );
        }

        return $status;
    }

    /**
     * Check if the current user can access the other user's group.
     *
     * This is typically used for teacher roles that have permissions like
     * 'view all submissions'. Even with such a permission granted, we have to
     * check the advwork activity group mode.
     *
     * If the advwork is not in a group mode, or if it is in the visible group
     * mode, this method returns true. This is consistent with how the
     * {@link groups_get_activity_allowed_groups()} behaves.
     *
     * If the advwork is in a separate group mode, the current user has to
     * have the 'access all groups' permission, or share at least one
     * accessible group with the other user.
     *
     * @param int $otheruserid The ID of the other user, e.g. the author of a submission.
     * @return bool False if the current user cannot access the other user's group.
     */
    public function check_group_membership($otheruserid) {
        global $USER;

        if (groups_get_activity_groupmode($this->cm) != SEPARATEGROUPS) {
            // The advwork is not in a group mode, or it is in a visible group mode.
            return true;

        } else if (has_capability('moodle/site:accessallgroups', $this->context)) {
            // The current user can access all groups.
            return true;

        } else {
            $thisusersgroups = groups_get_all_groups($this->course->id, $USER->id, $this->cm->groupingid, 'g.id');
            $otherusersgroups = groups_get_all_groups($this->course->id, $otheruserid, $this->cm->groupingid, 'g.id');
            $commongroups = array_intersect_key($thisusersgroups, $otherusersgroups);

            if (empty($commongroups)) {
                // The current user has no group common with the other user.
                return false;

            } else {
                // The current user has a group common with the other user.
                return true;
            }
        }
    }

    /**
     * Check whether the given user has assessed all his required examples before submission.
     *
     * @param  int $userid the user to check
     * @return bool        false if there are examples missing assessment, true otherwise.
     * @since  Moodle 3.4
     */
    public function check_examples_assessed_before_submission($userid) {

        if ($this->useexamples and $this->examplesmode == self::EXAMPLES_BEFORE_SUBMISSION
            and !has_capability('mod/advwork:manageexamples', $this->context)) {

            // Check that all required examples have been assessed by the user.
            $examples = $this->get_examples_for_reviewer($userid);
            foreach ($examples as $exampleid => $example) {
                if (is_null($example->assessmentid)) {
                    $examples[$exampleid]->assessmentid = $this->add_allocation($example, $userid, 0);
                }
                if (is_null($example->grade)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Check that all required examples have been assessed by the given user.
     *
     * @param  stdClass $userid     the user (reviewer) to check
     * @return mixed bool|state     false and notice code if there are examples missing assessment, true otherwise.
     * @since  Moodle 3.4
     */
    public function check_examples_assessed_before_assessment($userid) {

        if ($this->useexamples and $this->examplesmode == self::EXAMPLES_BEFORE_ASSESSMENT
                and !has_capability('mod/advwork:manageexamples', $this->context)) {

            // The reviewer must have submitted their own submission.
            $reviewersubmission = $this->get_submission_by_author($userid);
            if (!$reviewersubmission) {
                // No money, no love.
                return array(false, 'exampleneedsubmission');
            } else {
                $examples = $this->get_examples_for_reviewer($userid);
                foreach ($examples as $exampleid => $example) {
                    if (is_null($example->grade)) {
                        return array(false, 'exampleneedassessed');
                    }
                }
            }
        }
        return array(true, null);
    }

    /**
     * Trigger module viewed event and set the module viewed for completion.
     *
     * @since  Moodle 3.4
     */
    public function set_module_viewed() {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        // Mark viewed.
        $completion = new completion_info($this->course);
        $completion->set_module_viewed($this->cm);

        $eventdata = array();
        $eventdata['objectid'] = $this->id;
        $eventdata['context'] = $this->context;

        // Trigger module viewed event.
        $event = \mod_advwork\event\course_module_viewed::create($eventdata);
        $event->add_record_snapshot('course', $this->course);
        $event->add_record_snapshot('advwork', $this->dbrecord);
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->trigger();
    }

    /**
     * Validates the submission form or WS data.
     *
     * @param  array $data the data to be validated
     * @return array       the validation errors (if any)
     * @since  Moodle 3.4
     */
    public function validate_submission_data($data) {
        global $DB, $USER;

        $errors = array();
        if (empty($data['id']) and empty($data['example'])) {
            // Make sure there is no submission saved meanwhile from another browser window.
            $sql = "SELECT COUNT(s.id)
                      FROM {advwork_submissions} s
                      JOIN {advwork} w ON (s.advworkid = w.id)
                      JOIN {course_modules} cm ON (w.id = cm.instance)
                      JOIN {modules} m ON (m.name = 'advwork' AND m.id = cm.module)
                     WHERE cm.id = ? AND s.authorid = ? AND s.example = 0";

            if ($DB->count_records_sql($sql, array($data['cmid'], $USER->id))) {
                $errors['title'] = get_string('err_multiplesubmissions', 'mod_advwork');
            }
        }

        $getfiles = file_get_drafarea_files($data['attachment_filemanager']);
        if (empty($getfiles->list) and html_is_blank($data['content_editor']['text'])) {
            $errors['content_editor'] = get_string('submissionrequiredcontent', 'mod_advwork');
            $errors['attachment_filemanager'] = get_string('submissionrequiredfile', 'mod_advwork');
        }

        return $errors;
    }

    /**
     * Adds or updates a submission.
     *
     * @param stdClass $submission The submissin data (via form or via WS).
     * @return the new or updated submission id.
     * @since  Moodle 3.4
     */
    public function edit_submission($submission) {
        global $USER, $DB;

        if ($submission->example == 0) {
            // This was used just for validation, it must be set to zero when dealing with normal submissions.
            unset($submission->example);
        } else {
            throw new coding_exception('Invalid submission form data value: example');
        }
        $timenow = time();
        if (is_null($submission->id)) {
            $submission->advworkid     = $this->id;
            $submission->example        = 0;
            $submission->authorid       = $USER->id;
            $submission->timecreated    = $timenow;
            $submission->feedbackauthorformat = editors_get_preferred_format();
        }
        $submission->timemodified       = $timenow;
        $submission->title              = trim($submission->title);
        $submission->content            = '';          // Updated later.
        $submission->contentformat      = FORMAT_HTML; // Updated later.
        $submission->contenttrust       = 0;           // Updated later.
        $submission->late               = 0x0;         // Bit mask.
        if (!empty($this->submissionend) and ($this->submissionend < time())) {
            $submission->late = $submission->late | 0x1;
        }
        if ($this->phase == self::PHASE_ASSESSMENT) {
            $submission->late = $submission->late | 0x2;
        }

        // Event information.
        $params = array(
            'context' => $this->context,
            'courseid' => $this->course->id,
            'other' => array(
                'submissiontitle' => $submission->title
            )
        );
        $logdata = null;
        if (is_null($submission->id)) {
            $submission->id = $DB->insert_record('advwork_submissions', $submission);
            $params['objectid'] = $submission->id;
            $event = \mod_advwork\event\submission_created::create($params);
            $event->trigger();
        } else {
            if (empty($submission->id) or empty($submission->id) or ($submission->id != $submission->id)) {
                throw new moodle_exception('err_submissionid', 'advwork');
            }
        }
        $params['objectid'] = $submission->id;

        // Save and relink embedded images and save attachments.
        $submission = file_postupdate_standard_editor($submission, 'content', $this->submission_content_options(),
            $this->context, 'mod_advwork', 'submission_content', $submission->id);

        $submission = file_postupdate_standard_filemanager($submission, 'attachment', $this->submission_attachment_options(),
            $this->context, 'mod_advwork', 'submission_attachment', $submission->id);

        if (empty($submission->attachment)) {
            // Explicit cast to zero integer.
            $submission->attachment = 0;
        }
        // Store the updated values or re-save the new submission (re-saving needed because URLs are now rewritten).
        $DB->update_record('advwork_submissions', $submission);
        $event = \mod_advwork\event\submission_updated::create($params);
        $event->add_record_snapshot('advwork', $this->dbrecord);
        $event->trigger();

        // Send submitted content for plagiarism detection.
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_advwork', 'submission_attachment', $submission->id);

        $params['other']['content'] = $submission->content;
        $params['other']['pathnamehashes'] = array_keys($files);

        $event = \mod_advwork\event\assessable_uploaded::create($params);
        $event->set_legacy_logdata($logdata);
        $event->trigger();

        return $submission->id;
    }

    /**
     * Helper method for validating if the current user can view the given assessment.
     *
     * @param  stdClass   $assessment assessment object
     * @param  stdClass   $submission submission object
     * @return void
     * @throws moodle_exception
     * @since  Moodle 3.4
     */
    public function check_view_assessment($assessment, $submission) {
        global $USER;

        $isauthor = $submission->authorid == $USER->id;
        $isreviewer = $assessment->reviewerid == $USER->id;
        $canviewallassessments  = has_capability('mod/advwork:viewallassessments', $this->context);
        $canviewallsubmissions  = has_capability('mod/advwork:viewallsubmissions', $this->context);

        $canviewallsubmissions = $canviewallsubmissions && $this->check_group_membership($submission->authorid);

        if (!$isreviewer and !$isauthor and !($canviewallassessments and $canviewallsubmissions)) {
            print_error('nopermissions', 'error', $this->view_url(), 'view this assessment');
        }

        if ($isauthor and !$isreviewer and !$canviewallassessments and $this->phase != self::PHASE_CLOSED) {
            // Authors can see assessments of their work at the end of advwork only.
            print_error('nopermissions', 'error', $this->view_url(), 'view assessment of own work before advwork is closed');
        }
    }

    /**
     * Helper method for validating if the current user can edit the given assessment.
     *
     * @param  stdClass   $assessment assessment object
     * @param  stdClass   $submission submission object
     * @return void
     * @throws moodle_exception
     * @since  Moodle 3.4
     */
    public function check_edit_assessment($assessment, $submission) {
        global $USER;

        $this->check_view_assessment($assessment, $submission);
        // Further checks.
        $isreviewer = ($USER->id == $assessment->reviewerid);

        $assessmenteditable = $isreviewer && $this->assessing_allowed($USER->id);
        if (!$assessmenteditable) {
            throw new moodle_exception('nopermissions', 'error', '', 'edit assessments');
        }

        list($assessed, $notice) = $this->check_examples_assessed_before_assessment($assessment->reviewerid);
        if (!$assessed) {
            throw new moodle_exception($notice, 'mod_advwork');
        }
    }

    /**
     * Adds information to an allocated assessment (function used the first time a review is done or when updating an existing one).
     *
     * @param  stdClass $assessment the assessment
     * @param  stdClass $submission the submission
     * @param  stdClass $data       the assessment data to be added or Updated
     * @param  stdClass $strategy   the strategy instance
     * @return float|null           Raw percentual grade (0.00000 to 100.00000) for submission
     * @since  Moodle 3.4
     */
    public function edit_assessment($assessment, $submission, $data, $strategy) {
        global $DB;

        $cansetassessmentweight = has_capability('mod/advwork:allocate', $this->context);

        // Let the grading strategy subplugin save its data.
        $rawgrade = $strategy->save_assessment($assessment, $data);

        // Store the data managed by the advwork core.
        $coredata = (object)array('id' => $assessment->id);
        if (isset($data->feedbackauthor_editor)) {
            $coredata->feedbackauthor_editor = $data->feedbackauthor_editor;
            $coredata = file_postupdate_standard_editor($coredata, 'feedbackauthor', $this->overall_feedback_content_options(),
                $this->context, 'mod_advwork', 'overallfeedback_content', $assessment->id);
            unset($coredata->feedbackauthor_editor);
        }
        if (isset($data->feedbackauthorattachment_filemanager)) {
            $coredata->feedbackauthorattachment_filemanager = $data->feedbackauthorattachment_filemanager;
            $coredata = file_postupdate_standard_filemanager($coredata, 'feedbackauthorattachment',
                $this->overall_feedback_attachment_options(), $this->context, 'mod_advwork', 'overallfeedback_attachment',
                $assessment->id);
            unset($coredata->feedbackauthorattachment_filemanager);
            if (empty($coredata->feedbackauthorattachment)) {
                $coredata->feedbackauthorattachment = 0;
            }
        }
        if (isset($data->weight) and $cansetassessmentweight) {
            $coredata->weight = $data->weight;
        }
        // Update the assessment data if there is something other than just the 'id'.
        if (count((array)$coredata) > 1 ) {
            $DB->update_record('advwork_assessments', $coredata);
            $params = array(
                'relateduserid' => $submission->authorid,
                'objectid' => $assessment->id,
                'context' => $this->context,
                'other' => array(
                    'advworkid' => $this->id,
                    'submissionid' => $assessment->submissionid
                )
            );

            if (is_null($assessment->grade)) {
                // All advwork_assessments are created when allocations are made. The create event is of more use located here.
                $event = \mod_advwork\event\submission_assessed::create($params);
                $event->trigger();
            } else {
                $params['other']['grade'] = $assessment->grade;
                $event = \mod_advwork\event\submission_reassessed::create($params);
                $event->trigger();
            }
        }
        return $rawgrade;
    }

    /**
     * Evaluates an assessment.
     *
     * @param  stdClass $assessment the assessment
     * @param  stdClass $data       the assessment data to be updated
     * @param  bool $cansetassessmentweight   whether the user can change the assessment weight
     * @param  bool $canoverridegrades   whether the user can override the assessment grades
     * @return void
     * @since  Moodle 3.4
     */
    public function evaluate_assessment($assessment, $data, $cansetassessmentweight, $canoverridegrades) {
        global $DB, $USER;

        $data = file_postupdate_standard_editor($data, 'feedbackreviewer', array(), $this->context);
        $record = new stdclass();
        $record->id = $assessment->id;
        if ($cansetassessmentweight) {
            $record->weight = $data->weight;
        }
        if ($canoverridegrades) {
            $record->gradinggradeover = $this->raw_grade_value($data->gradinggradeover, $this->gradinggrade);
            $record->gradinggradeoverby = $USER->id;
            $record->feedbackreviewer = $data->feedbackreviewer;
            $record->feedbackreviewerformat = $data->feedbackreviewerformat;
        }
        $DB->update_record('advwork_assessments', $record);
    }

    /**
     * Trigger submission viewed event.
     *
     * @param stdClass $submission submission object
     * @since  Moodle 3.4
     */
    public function set_submission_viewed($submission) {
        $params = array(
            'objectid' => $submission->id,
            'context' => $this->context,
            'courseid' => $this->course->id,
            'relateduserid' => $submission->authorid,
            'other' => array(
                'advworkid' => $this->id
            )
        );

        $event = \mod_advwork\event\submission_viewed::create($params);
        $event->trigger();
    }

    /**
     * Evaluates a submission.
     *
     * @param  stdClass $submission the submission
     * @param  stdClass $data       the submission data to be updated
     * @param  bool $canpublish     whether the user can publish the submission
     * @param  bool $canoverride    whether the user can override the submission grade
     * @return void
     * @since  Moodle 3.4
     */
    public function evaluate_submission($submission, $data, $canpublish, $canoverride) {
        global $DB, $USER;

        $data = file_postupdate_standard_editor($data, 'feedbackauthor', array(), $this->context);
        $record = new stdclass();
        $record->id = $submission->id;
        if ($canoverride) {
            $record->gradeover = $this->raw_grade_value($data->gradeover, $this->grade);
            $record->gradeoverby = $USER->id;
            $record->feedbackauthor = $data->feedbackauthor;
            $record->feedbackauthorformat = $data->feedbackauthorformat;
        }
        if ($canpublish) {
            $record->published = !empty($data->published);
        }
        $DB->update_record('advwork_submissions', $record);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Internal methods (implementation details)                                  //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Given an array of all assessments of a single submission, calculates the final grade for this submission
     *
     * This calculates the weighted mean of the passed assessment grades. If, however, the submission grade
     * was overridden by a teacher, the gradeover value is returned and the rest of grades are ignored.
     *
     * @param array $assessments of stdclass(->submissionid ->submissiongrade ->gradeover ->weight ->grade)
     * @return void
     */
    protected function aggregate_submission_grades_process(array $assessments) {
        global $DB;

        $submissionid   = null; // the id of the submission being processed
        $current        = null; // the grade currently saved in database
        $finalgrade     = null; // the new grade to be calculated
        $sumgrades      = 0;
        $sumweights     = 0;

        foreach ($assessments as $assessment) {
            if (is_null($submissionid)) {
                // the id is the same in all records, fetch it during the first loop cycle
                $submissionid = $assessment->submissionid;
            }
            if (is_null($current)) {
                // the currently saved grade is the same in all records, fetch it during the first loop cycle
                $current = $assessment->submissiongrade;
            }
            if (is_null($assessment->grade)) {
                // this was not assessed yet
                continue;
            }
            if ($assessment->weight == 0) {
                // this does not influence the calculation
                continue;
            }
            $sumgrades  += $assessment->grade * $assessment->weight;
            $sumweights += $assessment->weight;
        }
        if ($sumweights > 0 and is_null($finalgrade)) {
            $finalgrade = grade_floatval($sumgrades / $sumweights);
        }
        // check if the new final grade differs from the one stored in the database
        if (grade_floats_different($finalgrade, $current)) {
            // we need to save new calculation into the database
            $record = new stdclass();
            $record->id = $submissionid;
            $record->grade = $finalgrade;
            $record->timegraded = time();
            $DB->update_record('advwork_submissions', $record);
        }
    }

    /**
     * Given an array of all assessments done by a single reviewer, calculates the final grading grade
     *
     * This calculates the simple mean of the passed grading grades. If, however, the grading grade
     * was overridden by a teacher, the gradinggradeover value is returned and the rest of grades are ignored.
     *
     * @param array $assessments of stdclass(->reviewerid ->gradinggrade ->gradinggradeover ->aggregationid ->aggregatedgrade)
     * @param null|int $timegraded explicit timestamp of the aggregation, defaults to the current time
     * @return void
     */
    protected function aggregate_grading_grades_process(array $assessments, $timegraded = null) {
        global $DB;

        $reviewerid = null; // the id of the reviewer being processed
        $current    = null; // the gradinggrade currently saved in database
        $finalgrade = null; // the new grade to be calculated
        $agid       = null; // aggregation id
        $sumgrades  = 0;
        $count      = 0;

        if (is_null($timegraded)) {
            $timegraded = time();
        }

        foreach ($assessments as $assessment) {
            if (is_null($reviewerid)) {
                // the id is the same in all records, fetch it during the first loop cycle
                $reviewerid = $assessment->reviewerid;
            }
            if (is_null($agid)) {
                // the id is the same in all records, fetch it during the first loop cycle
                $agid = $assessment->aggregationid;
            }
            if (is_null($current)) {
                // the currently saved grade is the same in all records, fetch it during the first loop cycle
                $current = $assessment->aggregatedgrade;
            }
            if (!is_null($assessment->gradinggradeover)) {
                // the grading grade for this assessment is overridden by a teacher
                $sumgrades += $assessment->gradinggradeover;
                $count++;
            } else {
                if (!is_null($assessment->gradinggrade)) {
                    $sumgrades += $assessment->gradinggrade;
                    $count++;
                }
            }
        }
        if ($count > 0) {
            $finalgrade = grade_floatval($sumgrades / $count);
        }

        // Event information.
        $params = array(
            'context' => $this->context,
            'courseid' => $this->course->id,
            'relateduserid' => $reviewerid
        );

        // check if the new final grade differs from the one stored in the database
        if (grade_floats_different($finalgrade, $current)) {
            $params['other'] = array(
                'currentgrade' => $current,
                'finalgrade' => $finalgrade
            );

            // we need to save new calculation into the database
            if (is_null($agid)) {
                // no aggregation record yet
                $record = new stdclass();
                $record->advworkid = $this->id;
                $record->userid = $reviewerid;
                $record->gradinggrade = $finalgrade;
                $record->timegraded = $timegraded;
                $record->id = $DB->insert_record('advwork_aggregations', $record);
                $params['objectid'] = $record->id;
                $event = \mod_advwork\event\assessment_evaluated::create($params);
                $event->trigger();
            } else {
                $record = new stdclass();
                $record->id = $agid;
                $record->gradinggrade = $finalgrade;
                $record->timegraded = $timegraded;
                $DB->update_record('advwork_aggregations', $record);
                $params['objectid'] = $agid;
                $event = \mod_advwork\event\assessment_reevaluated::create($params);
                $event->trigger();
            }
        }
    }

    /**
     * Returns SQL to fetch all enrolled users with the given capability in the current advwork
     *
     * The returned array consists of string $sql and the $params array. Note that the $sql can be
     * empty if a grouping is selected and it has no groups.
     *
     * The list is automatically restricted according to any availability restrictions
     * that apply to user lists (e.g. group, grouping restrictions).
     *
     * @param string $capability the name of the capability
     * @param bool $musthavesubmission ff true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return array of (string)sql, (array)params
     */
    protected function get_users_with_capability_sql($capability, $musthavesubmission, $groupid) {
        global $CFG;
        /** @var int static counter used to generate unique parameter holders */
        static $inc = 0;
        $inc++;

        // If the caller requests all groups and we are using a selected grouping,
        // recursively call this function for each group in the grouping (this is
        // needed because get_enrolled_sql only supports a single group).
        if (empty($groupid) and $this->cm->groupingid) {
            $groupingid = $this->cm->groupingid;
            $groupinggroupids = array_keys(groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid, 'g.id'));
            $sql = array();
            $params = array();
            foreach ($groupinggroupids as $groupinggroupid) {
                if ($groupinggroupid > 0) { // just in case in order not to fall into the endless loop
                    list($gsql, $gparams) = $this->get_users_with_capability_sql($capability, $musthavesubmission, $groupinggroupid);
                    $sql[] = $gsql;
                    $params = array_merge($params, $gparams);
                }
            }
            $sql = implode(PHP_EOL." UNION ".PHP_EOL, $sql);
            return array($sql, $params);
        }

        list($esql, $params) = get_enrolled_sql($this->context, $capability, $groupid, true);

        $userfields = user_picture::fields('u');

        $sql = "SELECT $userfields
                  FROM {user} u
                  JOIN ($esql) je ON (je.id = u.id AND u.deleted = 0) ";

        if ($musthavesubmission) {
            $sql .= " JOIN {advwork_submissions} ws ON (ws.authorid = u.id AND ws.example = 0 AND ws.advworkid = :advworkid{$inc}) ";
            $params['advworkid'.$inc] = $this->id;
        }

        // If the activity is restricted so that only certain users should appear
        // in user lists, integrate this into the same SQL.
        $info = new \core_availability\info_module($this->cm);
        list ($listsql, $listparams) = $info->get_user_list_sql(false);
        if ($listsql) {
            $sql .= " JOIN ($listsql) restricted ON restricted.id = u.id ";
            $params = array_merge($params, $listparams);
        }

        return array($sql, $params);
    }

    /**
     * Returns SQL statement that can be used to fetch all actively enrolled participants in the advwork
     *
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return array of (string)sql, (array)params
     */
    protected function get_participants_sql($musthavesubmission=false, $groupid=0) {

        list($sql1, $params1) = $this->get_users_with_capability_sql('mod/advwork:submit', $musthavesubmission, $groupid);
        list($sql2, $params2) = $this->get_users_with_capability_sql('mod/advwork:peerassess', $musthavesubmission, $groupid);

        if (empty($sql1) or empty($sql2)) {
            if (empty($sql1) and empty($sql2)) {
                return array('', array());
            } else if (empty($sql1)) {
                $sql = $sql2;
                $params = $params2;
            } else {
                $sql = $sql1;
                $params = $params1;
            }
        } else {
            $sql = $sql1.PHP_EOL." UNION ".PHP_EOL.$sql2;
            $params = array_merge($params1, $params2);
        }

        return array($sql, $params);
    }

    /**
     * @return array of available advwork phases
     */
    protected function available_phases_list() {
        return array(
            self::PHASE_SETUP       => true,
            self::PHASE_SUBMISSION  => true,
            self::PHASE_ASSESSMENT  => true,
            self::PHASE_EVALUATION  => true,
            self::PHASE_CLOSED      => true,
        );
    }

    /**
     * Converts absolute URL to relative URL needed by {@see add_to_log()}
     *
     * @param moodle_url $url absolute URL
     * @return string
     */
    protected function log_convert_url(moodle_url $fullurl) {
        static $baseurl;

        if (!isset($baseurl)) {
            $baseurl = new moodle_url('/mod/advwork/');
            $baseurl = $baseurl->out();
        }

        return substr($fullurl->out(), strlen($baseurl));
    }

    /**
     * Removes all user data related to assessments (including allocations).
     *
     * This includes assessments of example submissions as long as they are not
     * referential assessments.
     *
     * @param stdClass $data The actual course reset settings.
     * @return bool|string True on success, error message otherwise.
     */
    protected function reset_userdata_assessments(stdClass $data) {
        global $DB;

        $sql = "SELECT a.id
                  FROM {advwork_assessments} a
                  JOIN {advwork_submissions} s ON (a.submissionid = s.id)
                 WHERE s.advworkid = :advworkid
                       AND (s.example = 0 OR (s.example = 1 AND a.weight = 0))";

        $assessments = $DB->get_records_sql($sql, array('advworkid' => $this->id));
        $this->delete_assessment(array_keys($assessments));

        $DB->delete_records('advwork_aggregations', array('advworkid' => $this->id));

        return true;
    }

    /**
     * Removes all user data related to participants' submissions.
     *
     * @param stdClass $data The actual course reset settings.
     * @return bool|string True on success, error message otherwise.
     */
    protected function reset_userdata_submissions(stdClass $data) {
        global $DB;

        $submissions = $this->get_submissions();
        foreach ($submissions as $submission) {
            $this->delete_submission($submission);
        }

        return true;
    }

    /**
     * Hard set the advwork phase to the setup one.
     */
    protected function reset_phase() {
        global $DB;

        $DB->set_field('advwork', 'phase', self::PHASE_SETUP, array('id' => $this->id));
        $this->phase = self::PHASE_SETUP;
    }

    public function setCapabilitiesDB () {
        global $DB;

        $risu = $DB->get_record_sql("select count(id) as numero_capability from mdl_advwork_capabilities");
        //echo "Acccesso diretto:".$risu->numero_capability."<br>";
        if ($risu->numero_capability==0){
            $tupla = new stdclass();
            $tupla->name = "K";
            $DB->insert_record("advwork_capabilities", $tupla);
            $tupla->name = "J";
            $DB->insert_record("advwork_capabilities", $tupla);
            $tupla->name = "C";
            $DB->insert_record("advwork_capabilities", $tupla);
        }
    }
    public function setDomainValueDB () {
        global $DB;

        $risu = $DB->get_record_sql("select count(id) as numero_value from mdl_advwork_domain_values");
        //echo "Acccesso diretto:".$risu->numero_value."<br>";
        if ($risu->numero_value==0){
            $tupla = new stdclass();
            $tupla->courseid = 2;
            $tupla->value = 1;
            $DB->insert_record("advwork_domain_values", $tupla);
            $tupla->courseid = 2;
            $tupla->value = 0.95;
            $DB->insert_record("advwork_domain_values", $tupla);
            $tupla->courseid = 2;
            $tupla->value = 0.85;
            $DB->insert_record("advwork_domain_values", $tupla);
            $tupla->courseid = 2;
            $tupla->value = 0.75;
            $DB->insert_record("advwork_domain_values", $tupla);
            $tupla->courseid = 2;
            $tupla->value = 0.65;
            $DB->insert_record("advwork_domain_values", $tupla);
            $tupla->courseid = 2;
            $tupla->value = 0.55;
            $DB->insert_record("advwork_domain_values", $tupla);
            $tupla->courseid = 2;
            $tupla->value = 0;
            $DB->insert_record("advwork_domain_values", $tupla);
        }
    }
    public function isThisSessionEmpty($advworkid) {
        global $DB;

        $risu = $DB->get_record_sql("SELECT * FROM (
SELECT sessioni.id, COUNT(submissions.id) as submission_count FROM mdl_advwork as sessioni LEFT JOIN mdl_advwork_submissions as submissions on sessioni.id = submissions.advworkid  GROUP BY sessioni.id
) as submissions_count
WHERE submission_count = 0 AND submissions_count.id=$advworkid");
        if (empty($risu)) {
            return false;
        } else {
            return true;
        }
    }
    public function deleteEmptySessions(){
        global $DB;

        $risu = $DB->get_records_sql("SELECT * FROM (
SELECT sessioni.id, COUNT(submissions.id) as submission_count FROM mdl_advwork as sessioni LEFT JOIN mdl_advwork_submissions as submissions on sessioni.id = submissions.advworkid WHERE sessioni.phase <> 20 GROUP BY sessioni.id
) as submissions_count
WHERE submission_count = 0 ");
        if (empty($risu)) return;
        foreach ($risu as $sessione){
            $DB->delete_records("advwork", array(id=>$sessione->id));
        }
    }
}

////////////////////////////////////////////////////////////////////////////////
// Renderable components
////////////////////////////////////////////////////////////////////////////////

/**
 * Represents the user planner tool
 *
 * Planner contains list of phases. Each phase contains list of tasks. Task is a simple object with
 * title, link and completed (true/false/null logic).
 */
class advwork_user_plan implements renderable {

    /** @var int id of the user this plan is for */
    public $userid;
    /** @var advwork */
    public $advwork;
    /** @var array of (stdclass)tasks */
    public $phases = array();
    /** @var null|array of example submissions to be assessed by the planner owner */
    protected $examples = null;

    /**
     * Prepare an individual advwork plan for the given user.
     *
     * @param advwork $advwork instance
     * @param int $userid whom the plan is prepared for
     */
    public function __construct(advwork $advwork, $userid) {
        global $DB;

        $this->advwork = $advwork;
        $this->userid   = $userid;

        //---------------------------------------------------------
        // * SETUP | submission | assessment | evaluation | closed
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phasesetup', 'advwork');
        $phase->tasks = array();
        if (has_capability('moodle/course:manageactivities', $advwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('taskintro', 'advwork');
            $task->link = $advwork->updatemod_url();
            $task->completed = !(trim($advwork->intro) == '');
            $phase->tasks['intro'] = $task;
        }
        if (has_capability('moodle/course:manageactivities', $advwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('taskinstructauthors', 'advwork');
            $task->link = $advwork->updatemod_url();
            $task->completed = !(trim($advwork->instructauthors) == '');
            $phase->tasks['instructauthors'] = $task;
        }
        if (has_capability('mod/advwork:editdimensions', $advwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('editassessmentform', 'advwork');
            $task->link = $advwork->editform_url();
            if ($advwork->grading_strategy_instance()->form_ready()) {
                $task->completed = true;
            } elseif ($advwork->phase > advwork::PHASE_SETUP) {
                $task->completed = false;
            }
            $phase->tasks['editform'] = $task;
        }
        if ($advwork->useexamples and has_capability('mod/advwork:manageexamples', $advwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('prepareexamples', 'advwork');
            if ($DB->count_records('advwork_submissions', array('example' => 1, 'advworkid' => $advwork->id)) > 0) {
                $task->completed = true;
            } elseif ($advwork->phase > advwork::PHASE_SETUP) {
                $task->completed = false;
            }
            $phase->tasks['prepareexamples'] = $task;
        }
        if (empty($phase->tasks) and $advwork->phase == advwork::PHASE_SETUP) {
            // if we are in the setup phase and there is no task (typical for students), let us
            // display some explanation what is going on
            $task = new stdclass();
            $task->title = get_string('undersetup', 'advwork');
            $task->completed = 'info';
            $phase->tasks['setupinfo'] = $task;
        }
        $this->phases[advwork::PHASE_SETUP] = $phase;

        //---------------------------------------------------------
        // setup | * SUBMISSION | assessment | evaluation | closed
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phasesubmission', 'advwork');
        $phase->tasks = array();
        if (has_capability('moodle/course:manageactivities', $advwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('taskinstructreviewers', 'advwork');
            $task->link = $advwork->updatemod_url();
            if (trim($advwork->instructreviewers)) {
                $task->completed = true;
            } elseif ($advwork->phase >= advwork::PHASE_ASSESSMENT) {
                $task->completed = false;
            }
            $phase->tasks['instructreviewers'] = $task;
        }
        /*
        //SIM SUBMISSION PHASE - CREATE SIMULATION CLASS BUTTON//
		$task->title = get_string('createsimulationclass', 'advwork');
		$task->link = $advwork->createclasssimulation_url();
        */
        if ($advwork->useexamples and $advwork->examplesmode == advwork::EXAMPLES_BEFORE_SUBMISSION
                and has_capability('mod/advwork:submit', $advwork->context, $userid, false)
                    and !has_capability('mod/advwork:manageexamples', $advwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('exampleassesstask', 'advwork');
            $examples = $this->get_examples();
            $a = new stdclass();
            $a->expected = count($examples);
            $a->assessed = 0;
            foreach ($examples as $exampleid => $example) {
                if (!is_null($example->grade)) {
                    $a->assessed++;
                }
            }
            $task->details = get_string('exampleassesstaskdetails', 'advwork', $a);
            if ($a->assessed == $a->expected) {
                $task->completed = true;
            } elseif ($advwork->phase >= advwork::PHASE_ASSESSMENT) {
                $task->completed = false;
            }
            $phase->tasks['examples'] = $task;
        }
        if (has_capability('mod/advwork:submit', $advwork->context, $userid, false)) {
            $task = new stdclass();
            $task->title = get_string('tasksubmit', 'advwork');
            $task->link = $advwork->submission_url();
            if ($DB->record_exists('advwork_submissions', array('advworkid'=>$advwork->id, 'example'=>0, 'authorid'=>$userid))) {
                $task->completed = true;
            } elseif ($advwork->phase >= advwork::PHASE_ASSESSMENT) {
                $task->completed = false;
            } else {
                $task->completed = null;    // still has a chance to submit
            }
            $phase->tasks['submit'] = $task;
        }
        if (has_capability('mod/advwork:allocate', $advwork->context, $userid)) {
            if ($advwork->phaseswitchassessment) {
                $task = new stdClass();
                $allocator = $DB->get_record('advworkallocation_scheduled', array('advworkid' => $advwork->id));
                if (empty($allocator)) {
                    $task->completed = false;
                } else if ($allocator->enabled and is_null($allocator->resultstatus)) {
                    $task->completed = true;
                } else if ($advwork->submissionend > time()) {
                    $task->completed = null;
                } else {
                    $task->completed = false;
                }
                $task->title = get_string('setup', 'advworkallocation_scheduled');
                $task->link = $advwork->allocation_url('scheduled');
                $phase->tasks['allocatescheduled'] = $task;
            }
            $task = new stdclass();
            $task->title = get_string('allocate', 'advwork');
            $task->link = $advwork->allocation_url();
            $numofauthors = $advwork->count_potential_authors(false);
            $numofsubmissions = $DB->count_records('advwork_submissions', array('advworkid'=>$advwork->id, 'example'=>0));
            $sql = 'SELECT COUNT(s.id) AS nonallocated
                      FROM {advwork_submissions} s
                 LEFT JOIN {advwork_assessments} a ON (a.submissionid=s.id)
                     WHERE s.advworkid = :advworkid AND s.example=0 AND a.submissionid IS NULL';
            $params['advworkid'] = $advwork->id;
            $numnonallocated = $DB->count_records_sql($sql, $params);
            if ($numofsubmissions == 0) {
                $task->completed = null;
            } elseif ($numnonallocated == 0) {
                $task->completed = true;
            } elseif ($advwork->phase > advwork::PHASE_SUBMISSION) {
                $task->completed = false;
            } else {
                $task->completed = null;    // still has a chance to allocate
            }
            $a = new stdclass();
            $a->expected    = $numofauthors;
            $a->submitted   = $numofsubmissions;
            $a->allocate    = $numnonallocated;
            $task->details  = get_string('allocatedetails', 'advwork', $a);
            unset($a);
            $phase->tasks['allocate'] = $task;

            if ($numofsubmissions < $numofauthors and $advwork->phase >= advwork::PHASE_SUBMISSION) {
                $task = new stdclass();
                $task->title = get_string('someuserswosubmission', 'advwork');
                $task->completed = 'info';
                $phase->tasks['allocateinfo'] = $task;
            }

        }
        if ($advwork->submissionstart) {
            $task = new stdclass();
            $task->title = get_string('submissionstartdatetime', 'advwork', advwork::timestamp_formats($advwork->submissionstart));
            $task->completed = 'info';
            $phase->tasks['submissionstartdatetime'] = $task;
        }
        if ($advwork->submissionend) {
            $task = new stdclass();
            $task->title = get_string('submissionenddatetime', 'advwork', advwork::timestamp_formats($advwork->submissionend));
            $task->completed = 'info';
            $phase->tasks['submissionenddatetime'] = $task;
        }
        if (($advwork->submissionstart < time()) and $advwork->latesubmissions) {
            $task = new stdclass();
            $task->title = get_string('latesubmissionsallowed', 'advwork');
            $task->completed = 'info';
            $phase->tasks['latesubmissionsallowed'] = $task;
        }
        if (isset($phase->tasks['submissionstartdatetime']) or isset($phase->tasks['submissionenddatetime'])) {
            if (has_capability('mod/advwork:ignoredeadlines', $advwork->context, $userid)) {
                $task = new stdclass();
                $task->title = get_string('deadlinesignored', 'advwork');
                $task->completed = 'info';
                $phase->tasks['deadlinesignored'] = $task;
            }
        }
        $this->phases[advwork::PHASE_SUBMISSION] = $phase;

        //---------------------------------------------------------
        // setup | submission | * ASSESSMENT | evaluation | closed
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phaseassessment', 'advwork');
        $phase->tasks = array();
        $phase->isreviewer = has_capability('mod/advwork:peerassess', $advwork->context, $userid);

        
        if ($advwork->phase == advwork::PHASE_SUBMISSION and $advwork->phaseswitchassessment
                and has_capability('mod/advwork:switchphase', $advwork->context, $userid)) {
            $task = new stdClass();
            $task->title = get_string('switchphase30auto', 'mod_advwork', advwork::timestamp_formats($advwork->submissionend));
            $task->completed = 'info';
            $phase->tasks['autoswitchinfo'] = $task;
        }
        if ($advwork->useexamples and $advwork->examplesmode == advwork::EXAMPLES_BEFORE_ASSESSMENT
                and $phase->isreviewer and !has_capability('mod/advwork:manageexamples', $advwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('exampleassesstask', 'advwork');
            $examples = $advwork->get_examples_for_reviewer($userid);
            $a = new stdclass();
            $a->expected = count($examples);
            $a->assessed = 0;
            foreach ($examples as $exampleid => $example) {
                if (!is_null($example->grade)) {
                    $a->assessed++;
                }
            }
            $task->details = get_string('exampleassesstaskdetails', 'advwork', $a);
            if ($a->assessed == $a->expected) {
                $task->completed = true;
            } elseif ($advwork->phase > advwork::PHASE_ASSESSMENT) {
                $task->completed = false;
            }
            $phase->tasks['examples'] = $task;
        }
        if (empty($phase->tasks['examples']) or !empty($phase->tasks['examples']->completed)) {
            $phase->assessments = $advwork->get_assessments_by_reviewer($userid);
            $numofpeers     = 0;    // number of allocated peer-assessments
            $numofpeerstodo = 0;    // number of peer-assessments to do
            $numofself      = 0;    // number of allocated self-assessments - should be 0 or 1
            $numofselftodo  = 0;    // number of self-assessments to do - should be 0 or 1
            foreach ($phase->assessments as $a) {
                if ($a->authorid == $userid) {
                    $numofself++;
                    if (is_null($a->grade)) {
                        $numofselftodo++;
                    }
                } else {
                    $numofpeers++;
                    if (is_null($a->grade)) {
                        $numofpeerstodo++;
                    }
                }
            }
            unset($a);
            if ($numofpeers) {
                $task = new stdclass();
                if ($numofpeerstodo == 0) {
                    $task->completed = true;
                } elseif ($advwork->phase > advwork::PHASE_ASSESSMENT) {
                    $task->completed = false;
                }
                $a = new stdclass();
                $a->total = $numofpeers;
                $a->todo  = $numofpeerstodo;
                $task->title = get_string('taskassesspeers', 'advwork');
                $task->details = get_string('taskassesspeersdetails', 'advwork', $a);
                unset($a);
                $phase->tasks['assesspeers'] = $task;
            }
            if ($advwork->useselfassessment and $numofself) {
                $task = new stdclass();
                if ($numofselftodo == 0) {
                    $task->completed = true;
                } elseif ($advwork->phase > advwork::PHASE_ASSESSMENT) {
                    $task->completed = false;
                }
                $task->title = get_string('taskassessself', 'advwork');
                $phase->tasks['assessself'] = $task;
            }
        }
        if ($advwork->assessmentstart) {
            $task = new stdclass();
            $task->title = get_string('assessmentstartdatetime', 'advwork', advwork::timestamp_formats($advwork->assessmentstart));
            $task->completed = 'info';
            $phase->tasks['assessmentstartdatetime'] = $task;
        }
        if ($advwork->assessmentend) {
            $task = new stdclass();
            $task->title = get_string('assessmentenddatetime', 'advwork', advwork::timestamp_formats($advwork->assessmentend));
            $task->completed = 'info';
            $phase->tasks['assessmentenddatetime'] = $task;
        }
        if (isset($phase->tasks['assessmentstartdatetime']) or isset($phase->tasks['assessmentenddatetime'])) {
            if (has_capability('mod/advwork:ignoredeadlines', $advwork->context, $userid)) {
                $task = new stdclass();
                $task->title = get_string('deadlinesignored', 'advwork');
                $task->completed = 'info';
                $phase->tasks['deadlinesignored'] = $task;
            }
        }
        $this->phases[advwork::PHASE_ASSESSMENT] = $phase;

        //---------------------------------------------------------
        // setup | submission | assessment | * EVALUATION | closed
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phaseevaluation', 'advwork');
        $phase->tasks = array();
        if (has_capability('mod/advwork:overridegrades', $advwork->context)) {
            $expected = $advwork->count_potential_authors(false);
            $calculated = $DB->count_records_select('advwork_submissions',
                    'advworkid = ? AND (grade IS NOT NULL OR gradeover IS NOT NULL)', array($advwork->id));
            $task = new stdclass();
            $task->title = get_string('calculatesubmissiongrades', 'advwork');
            $a = new stdclass();
            $a->expected    = $expected;
            $a->calculated  = $calculated;
            $task->details  = get_string('calculatesubmissiongradesdetails', 'advwork', $a);
            if ($calculated >= $expected) {
                $task->completed = true;
            } elseif ($advwork->phase > advwork::PHASE_EVALUATION) {
                $task->completed = false;
            }
            $phase->tasks['calculatesubmissiongrade'] = $task;

            $expected = $advwork->count_potential_reviewers(false);
            $calculated = $DB->count_records_select('advwork_aggregations',
                    'advworkid = ? AND gradinggrade IS NOT NULL', array($advwork->id));
            $task = new stdclass();
            $task->title = get_string('calculategradinggrades', 'advwork');
            $a = new stdclass();
            $a->expected    = $expected;
            $a->calculated  = $calculated;
            $task->details  = get_string('calculategradinggradesdetails', 'advwork', $a);
            if ($calculated >= $expected) {
                $task->completed = true;
            } elseif ($advwork->phase > advwork::PHASE_EVALUATION) {
                $task->completed = false;
            }
            $phase->tasks['calculategradinggrade'] = $task;

        } elseif ($advwork->phase == advwork::PHASE_EVALUATION) {
            $task = new stdclass();
            $task->title = get_string('evaluategradeswait', 'advwork');
            $task->completed = 'info';
            $phase->tasks['evaluateinfo'] = $task;
        }

        if (has_capability('moodle/course:manageactivities', $advwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('taskconclusion', 'advwork');
            $task->link = $advwork->updatemod_url();
            if (trim($advwork->conclusion)) {
                $task->completed = true;
            } elseif ($advwork->phase >= advwork::PHASE_EVALUATION) {
                $task->completed = false;
            }
            $phase->tasks['conclusion'] = $task;
        }

        $this->phases[advwork::PHASE_EVALUATION] = $phase;

        //---------------------------------------------------------
        // setup | submission | assessment | evaluation | * CLOSED
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phaseclosed', 'advwork');
        $phase->tasks = array();
        $this->phases[advwork::PHASE_CLOSED] = $phase;

        // Polish data, set default values if not done explicitly
        foreach ($this->phases as $phasecode => $phase) {
            $phase->title       = isset($phase->title)      ? $phase->title     : '';
            $phase->tasks       = isset($phase->tasks)      ? $phase->tasks     : array();
            if ($phasecode == $advwork->phase) {
                $phase->active = true;
            } else {
                $phase->active = false;
            }
            if (!isset($phase->actions)) {
                $phase->actions = array();
            }

            foreach ($phase->tasks as $taskcode => $task) {
                $task->title        = isset($task->title)       ? $task->title      : '';
                $task->link         = isset($task->link)        ? $task->link       : null;
                $task->details      = isset($task->details)     ? $task->details    : '';
                $task->completed    = isset($task->completed)   ? $task->completed  : null;
            }
        }

        // Add phase switching actions.
        if (has_capability('mod/advwork:switchphase', $advwork->context, $userid)) {
            $nextphases = array(
                advwork::PHASE_SETUP => advwork::PHASE_SUBMISSION,
                advwork::PHASE_SUBMISSION => advwork::PHASE_ASSESSMENT,
                advwork::PHASE_ASSESSMENT => advwork::PHASE_EVALUATION,
                advwork::PHASE_EVALUATION => advwork::PHASE_CLOSED,
            );
            foreach ($this->phases as $phasecode => $phase) {
                if ($phase->active) {
                    if (isset($nextphases[$advwork->phase])) {
                        $task = new stdClass();
                        $task->title = get_string('switchphasenext', 'mod_advwork');
                        $task->link = $advwork->switchphase_url($nextphases[$advwork->phase]);
                        $task->details = '';
                        $task->completed = null;
                        $phase->tasks['switchtonextphase'] = $task;
                    }

                } else {
                    $action = new stdclass();
                    $action->type = 'switchphase';
                    $action->url  = $advwork->switchphase_url($phasecode);
                    $phase->actions[] = $action;
                }
            }
        }
    }

    /**
     * Returns example submissions to be assessed by the owner of the planner
     *
     * This is here to cache the DB query because the same list is needed later in view.php
     *
     * @see advwork::get_examples_for_reviewer() for the format of returned value
     * @return array
     */
    public function get_examples() {
        if (is_null($this->examples)) {
            $this->examples = $this->advwork->get_examples_for_reviewer($this->userid);
        }
        return $this->examples;
    }
}

/**
 * Common base class for submissions and example submissions rendering
 *
 * Subclasses of this class convert raw submission record from
 * advwork_submissions table (as returned by {@see advwork::get_submission_by_id()}
 * for example) into renderable objects.
 */
abstract class advwork_submission_base {

    /** @var bool is the submission anonymous (i.e. contains author information) */
    protected $anonymous;

    /* @var array of columns from advwork_submissions that are assigned as properties */
    protected $fields = array();

    /** @var advwork */
    protected $advwork;

    /**
     * Copies the properties of the given database record into properties of $this instance
     *
     * @param advwork $advwork
     * @param stdClass $submission full record
     * @param bool $showauthor show the author-related information
     * @param array $options additional properties
     */
    public function __construct(advwork $advwork, stdClass $submission, $showauthor = false) {

        $this->advwork = $advwork;

        foreach ($this->fields as $field) {
            if (!property_exists($submission, $field)) {
                throw new coding_exception('Submission record must provide public property ' . $field);
            }
            if (!property_exists($this, $field)) {
                throw new coding_exception('Renderable component must accept public property ' . $field);
            }
            $this->{$field} = $submission->{$field};
        }

        if ($showauthor) {
            $this->anonymous = false;
        } else {
            $this->anonymize();
        }
    }

    /**
     * Unsets all author-related properties so that the renderer does not have access to them
     *
     * Usually this is called by the contructor but can be called explicitely, too.
     */
    public function anonymize() {
        $authorfields = explode(',', user_picture::fields());
        foreach ($authorfields as $field) {
            $prefixedusernamefield = 'author' . $field;
            unset($this->{$prefixedusernamefield});
        }
        $this->anonymous = true;
    }

    /**
     * Does the submission object contain author-related information?
     *
     * @return null|boolean
     */
    public function is_anonymous() {
        return $this->anonymous;
    }
}

/**
 * Renderable object containing a basic set of information needed to display the submission summary
 *
 * @see advwork_renderer::render_advwork_submission_summary
 */
class advwork_submission_summary extends advwork_submission_base implements renderable {

    /** @var int */
    public $id;
    /** @var string */
    public $title;
    /** @var string graded|notgraded */
    public $status;
    /** @var int */
    public $timecreated;
    /** @var int */
    public $timemodified;
    /** @var int */
    public $authorid;
    /** @var string */
    public $authorfirstname;
    /** @var string */
    public $authorlastname;
    /** @var string */
    public $authorfirstnamephonetic;
    /** @var string */
    public $authorlastnamephonetic;
    /** @var string */
    public $authormiddlename;
    /** @var string */
    public $authoralternatename;
    /** @var int */
    public $authorpicture;
    /** @var string */
    public $authorimagealt;
    /** @var string */
    public $authoremail;
    /** @var moodle_url to display submission */
    public $url;

    /**
     * @var array of columns from advwork_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array(
        'id', 'title', 'timecreated', 'timemodified',
        'authorid', 'authorfirstname', 'authorlastname', 'authorfirstnamephonetic', 'authorlastnamephonetic',
        'authormiddlename', 'authoralternatename', 'authorpicture',
        'authorimagealt', 'authoremail');
}

class advwork_general_student_model_url implements renderable {
    public $title;

    public $url;
}

/**
 * Renderable object containing all the information needed to display the submission
 *
 * @see advwork_renderer::render_advwork_submission()
 */
class advwork_submission extends advwork_submission_summary implements renderable {

    /** @var string */
    public $content;
    /** @var int */
    public $contentformat;
    /** @var bool */
    public $contenttrust;
    /** @var array */
    public $attachment;

    /**
     * @var array of columns from advwork_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array(
        'id', 'title', 'timecreated', 'timemodified', 'content', 'contentformat', 'contenttrust',
        'attachment', 'authorid', 'authorfirstname', 'authorlastname', 'authorfirstnamephonetic', 'authorlastnamephonetic',
        'authormiddlename', 'authoralternatename', 'authorpicture', 'authorimagealt', 'authoremail');
}

/**
 * Renderable object containing a basic set of information needed to display the example submission summary
 *
 * @see advwork::prepare_example_summary()
 * @see advwork_renderer::render_advwork_example_submission_summary()
 */
class advwork_example_submission_summary extends advwork_submission_base implements renderable {

    /** @var int */
    public $id;
    /** @var string */
    public $title;
    /** @var string graded|notgraded */
    public $status;
    /** @var stdClass */
    public $gradeinfo;
    /** @var moodle_url */
    public $url;
    /** @var moodle_url */
    public $editurl;
    /** @var string */
    public $assesslabel;
    /** @var moodle_url */
    public $assessurl;
    /** @var bool must be set explicitly by the caller */
    public $editable = false;

    /**
     * @var array of columns from advwork_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array('id', 'title');

    /**
     * Example submissions are always anonymous
     *
     * @return true
     */
    public function is_anonymous() {
        return true;
    }
}

/**
 * Renderable object containing all the information needed to display the example submission
 *
 * @see advwork_renderer::render_advwork_example_submission()
 */
class advwork_example_submission extends advwork_example_submission_summary implements renderable {

    /** @var string */
    public $content;
    /** @var int */
    public $contentformat;
    /** @var bool */
    public $contenttrust;
    /** @var array */
    public $attachment;

    /**
     * @var array of columns from advwork_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array('id', 'title', 'content', 'contentformat', 'contenttrust', 'attachment');
}


/**
 * Common base class for assessments rendering
 *
 * Subclasses of this class convert raw assessment record from
 * advwork_assessments table (as returned by {@see advwork::get_assessment_by_id()}
 * for example) into renderable objects.
 */
abstract class advwork_assessment_base {

    /** @var string the optional title of the assessment */
    public $title = '';

    /** @var advwork_assessment_form $form as returned by {@link advwork_strategy::get_assessment_form()} */
    public $form;

    /** @var moodle_url */
    public $url;

    /** @var float|null the real received grade */
    public $realgrade = null;

    /** @var float the real maximum grade */
    public $maxgrade;

    /** @var stdClass|null reviewer user info */
    public $reviewer = null;

    /** @var stdClass|null assessed submission's author user info */
    public $author = null;

    /** @var array of actions */
    public $actions = array();

    /* @var array of columns that are assigned as properties */
    protected $fields = array();

    /** @var advwork */
    public $advwork;

    /**
     * Copies the properties of the given database record into properties of $this instance
     *
     * The $options keys are: showreviewer, showauthor
     * @param advwork $advwork
     * @param stdClass $assessment full record
     * @param array $options additional properties
     */
    public function __construct(advwork $advwork, stdClass $record, array $options = array()) {

        $this->advwork = $advwork;
        $this->validate_raw_record($record);

        foreach ($this->fields as $field) {
            if (!property_exists($record, $field)) {
                throw new coding_exception('Assessment record must provide public property ' . $field);
            }
            if (!property_exists($this, $field)) {
                throw new coding_exception('Renderable component must accept public property ' . $field);
            }
            $this->{$field} = $record->{$field};
        }

        if (!empty($options['showreviewer'])) {
            $this->reviewer = user_picture::unalias($record, null, 'revieweridx', 'reviewer');
        }

        if (!empty($options['showauthor'])) {
            $this->author = user_picture::unalias($record, null, 'authorid', 'author');
        }
    }

    /**
     * Adds a new action
     *
     * @param moodle_url $url action URL
     * @param string $label action label
     * @param string $method get|post
     */
    public function add_action(moodle_url $url, $label, $method = 'get') {

        $action = new stdClass();
        $action->url = $url;
        $action->label = $label;
        $action->method = $method;

        $this->actions[] = $action;
    }

    /**
     * Makes sure that we can cook the renderable component from the passed raw database record
     *
     * @param stdClass $assessment full assessment record
     * @throws coding_exception if the caller passed unexpected data
     */
    protected function validate_raw_record(stdClass $record) {
        // nothing to do here
    }
}


/**
 * Represents a rendarable full assessment
 */
class advwork_assessment extends advwork_assessment_base implements renderable {

    /** @var int */
    public $id;

    /** @var int */
    public $submissionid;

    /** @var int */
    public $weight;

    /** @var int */
    public $timecreated;

    /** @var int */
    public $timemodified;

    /** @var float */
    public $grade;

    /** @var float */
    public $gradinggrade;

    /** @var float */
    public $gradinggradeover;

    /** @var string */
    public $feedbackauthor;

    /** @var int */
    public $feedbackauthorformat;

    /** @var int */
    public $feedbackauthorattachment;

    /** @var array */
    protected $fields = array('id', 'submissionid', 'weight', 'timecreated',
        'timemodified', 'grade', 'gradinggrade', 'gradinggradeover', 'feedbackauthor',
        'feedbackauthorformat', 'feedbackauthorattachment');

    /**
     * Format the overall feedback text content
     *
     * False is returned if the overall feedback feature is disabled. Null is returned
     * if the overall feedback content has not been found. Otherwise, string with
     * formatted feedback text is returned.
     *
     * @return string|bool|null
     */
    public function get_overall_feedback_content() {

        if ($this->advwork->overallfeedbackmode == 0) {
            return false;
        }

        if (trim($this->feedbackauthor) === '') {
            return null;
        }

        $content = file_rewrite_pluginfile_urls($this->feedbackauthor, 'pluginfile.php', $this->advwork->context->id,
            'mod_advwork', 'overallfeedback_content', $this->id);
        $content = format_text($content, $this->feedbackauthorformat,
            array('overflowdiv' => true, 'context' => $this->advwork->context));

        return $content;
    }

    /**
     * Prepares the list of overall feedback attachments
     *
     * Returns false if overall feedback attachments are not allowed. Otherwise returns
     * list of attachments (may be empty).
     *
     * @return bool|array of stdClass
     */
    public function get_overall_feedback_attachments() {

        if ($this->advwork->overallfeedbackmode == 0) {
            return false;
        }

        if ($this->advwork->overallfeedbackfiles == 0) {
            return false;
        }

        if (empty($this->feedbackauthorattachment)) {
            return array();
        }

        $attachments = array();
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->advwork->context->id, 'mod_advwork', 'overallfeedback_attachment', $this->id);
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $filepath = $file->get_filepath();
            $filename = $file->get_filename();
            $fileurl = moodle_url::make_pluginfile_url($this->advwork->context->id, 'mod_advwork',
                'overallfeedback_attachment', $this->id, $filepath, $filename, true);
            $previewurl = new moodle_url(moodle_url::make_pluginfile_url($this->advwork->context->id, 'mod_advwork',
                'overallfeedback_attachment', $this->id, $filepath, $filename, false), array('preview' => 'bigthumb'));
            $attachments[] = (object)array(
                'filepath' => $filepath,
                'filename' => $filename,
                'fileurl' => $fileurl,
                'previewurl' => $previewurl,
                'mimetype' => $file->get_mimetype(),

            );
        }

        return $attachments;
    }
}


/**
 * Represents a renderable training assessment of an example submission
 */
class advwork_example_assessment extends advwork_assessment implements renderable {

    /**
     * @see parent::validate_raw_record()
     */
    protected function validate_raw_record(stdClass $record) {
        if ($record->weight != 0) {
            throw new coding_exception('Invalid weight of example submission assessment');
        }
        parent::validate_raw_record($record);
    }
}


/**
 * Represents a renderable reference assessment of an example submission
 */
class advwork_example_reference_assessment extends advwork_assessment implements renderable {

    /**
     * @see parent::validate_raw_record()
     */
    protected function validate_raw_record(stdClass $record) {
        if ($record->weight != 1) {
            throw new coding_exception('Invalid weight of the reference example submission assessment');
        }
        parent::validate_raw_record($record);
    }
}


/**
 * Renderable message to be displayed to the user
 *
 * Message can contain an optional action link with a label that is supposed to be rendered
 * as a button or a link.
 *
 * @see advwork::renderer::render_advwork_message()
 */
class advwork_message implements renderable {

    const TYPE_INFO     = 10;
    const TYPE_OK       = 20;
    const TYPE_ERROR    = 30;

    /** @var string */
    protected $text = '';
    /** @var int */
    protected $type = self::TYPE_INFO;
    /** @var moodle_url */
    protected $actionurl = null;
    /** @var string */
    protected $actionlabel = '';

    /**
     * @param string $text short text to be displayed
     * @param string $type optional message type info|ok|error
     */
    public function __construct($text = null, $type = self::TYPE_INFO) {
        $this->set_text($text);
        $this->set_type($type);
    }

    /**
     * Sets the message text
     *
     * @param string $text short text to be displayed
     */
    public function set_text($text) {
        $this->text = $text;
    }

    /**
     * Sets the message type
     *
     * @param int $type
     */
    public function set_type($type = self::TYPE_INFO) {
        if (in_array($type, array(self::TYPE_OK, self::TYPE_ERROR, self::TYPE_INFO))) {
            $this->type = $type;
        } else {
            throw new coding_exception('Unknown message type.');
        }
    }

    /**
     * Sets the optional message action
     *
     * @param moodle_url $url to follow on action
     * @param string $label action label
     */
    public function set_action(moodle_url $url, $label) {
        $this->actionurl    = $url;
        $this->actionlabel  = $label;
    }

    /**
     * Returns message text with HTML tags quoted
     *
     * @return string
     */
    public function get_message() {
        return s($this->text);
    }

    /**
     * Returns message type
     *
     * @return int
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Returns action URL
     *
     * @return moodle_url|null
     */
    public function get_action_url() {
        return $this->actionurl;
    }

    /**
     * Returns action label
     *
     * @return string
     */
    public function get_action_label() {
        return $this->actionlabel;
    }
}


/**
 * Renderable component containing all the data needed to display the grading report
 */
class advwork_grading_report implements renderable {

    /** @var stdClass returned by {@see advwork::prepare_grading_report_data()} */
    protected $data;
    /** @var stdClass rendering options */
    protected $options;

    /**
     * Grades in $data must be already rounded to the set number of decimals or must be null
     * (in which later case, the [mod_advwork,nullgrade] string shall be displayed)
     *
     * @param stdClass $data prepared by {@link advwork::prepare_grading_report_data()}
     * @param stdClass $options display options (showauthornames, showreviewernames, sortby, sorthow, showsubmissiongrade, showgradinggrade)
     */
    public function __construct(stdClass $data, stdClass $options) {
        $this->data     = $data;
        $this->options  = $options;
    }

    /**
     * @return stdClass grading report data
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * @return stdClass rendering options
     */
    public function get_options() {
        return $this->options;
    }

    /**
     * Prepare the data to be exported to a external system via Web Services.
     *
     * This function applies extra capabilities checks.
     * @return stdClass the data ready for external systems
     */
    public function export_data_for_external() {
        $data = $this->get_data();
        $options = $this->get_options();

        foreach ($data->grades as $reportdata) {
            // If we are in submission phase ignore the following data.
            if ($options->advworkphase == advwork::PHASE_SUBMISSION) {
                unset($reportdata->submissiongrade);
                unset($reportdata->gradinggrade);
                unset($reportdata->submissiongradeover);
                unset($reportdata->submissiongradeoverby);
                unset($reportdata->submissionpublished);
                unset($reportdata->reviewedby);
                unset($reportdata->reviewerof);
                continue;
            }

            if (!$options->showsubmissiongrade) {
                unset($reportdata->submissiongrade);
                unset($reportdata->submissiongradeover);
            }

            if (!$options->showgradinggrade and $tr == 0) {
                unset($reportdata->gradinggrade);
            }

            if (!$options->showreviewernames) {
                foreach ($reportdata->reviewedby as $reviewedby) {
                    $reviewedby->userid = 0;
                }
            }

            if (!$options->showauthornames) {
                foreach ($reportdata->reviewerof as $reviewerof) {
                    $reviewerof->userid = 0;
                }
            }
        }

        return $data;
    }
}

/**
 * Renderable component containing all the data needed to display the general student models grading report
 */
class advwork_general_student_models_grading_report implements renderable {

    /** @var stdClass returned by {@see advwork::prepare_general_student_models_report_data()} */
    protected $data;
    /** @var stdClass rendering options */
    protected $options;

    /*
    * @param stdClass $data prepared by {@link advwork::prepare_general_student_models_report_data()}
    */
        public function __construct(stdClass $data, stdClass $options) {
        $this->data     = $data;
        $this->options  = $options;
    }

    /**
     * @return stdClass grading report data
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * @return stdClass rendering options
     */
    public function get_options() {
        return $this->options;
    }
}


/**
 * Base class for renderable feedback for author and feedback for reviewer
 */
abstract class advwork_feedback {

    /** @var stdClass the user info */
    protected $provider = null;

    /** @var string the feedback text */
    protected $content = null;

    /** @var int format of the feedback text */
    protected $format = null;

    /**
     * @return stdClass the user info
     */
    public function get_provider() {

        if (is_null($this->provider)) {
            throw new coding_exception('Feedback provider not set');
        }

        return $this->provider;
    }

    /**
     * @return string the feedback text
     */
    public function get_content() {

        if (is_null($this->content)) {
            throw new coding_exception('Feedback content not set');
        }

        return $this->content;
    }

    /**
     * @return int format of the feedback text
     */
    public function get_format() {

        if (is_null($this->format)) {
            throw new coding_exception('Feedback text format not set');
        }

        return $this->format;
    }
}


/**
 * Renderable feedback for the author of submission
 */
class advwork_feedback_author extends advwork_feedback implements renderable {

    /**
     * Extracts feedback from the given submission record
     *
     * @param stdClass $submission record as returned by {@see self::get_submission_by_id()}
     */
    public function __construct(stdClass $submission) {

        $this->provider = user_picture::unalias($submission, null, 'gradeoverbyx', 'gradeoverby');
        $this->content  = $submission->feedbackauthor;
        $this->format   = $submission->feedbackauthorformat;
    }
}


/**
 * Renderable feedback for the reviewer
 */
class advwork_feedback_reviewer extends advwork_feedback implements renderable {

    /**
     * Extracts feedback from the given assessment record
     *
     * @param stdClass $assessment record as returned by eg {@see self::get_assessment_by_id()}
     */
    public function __construct(stdClass $assessment) {

        $this->provider = user_picture::unalias($assessment, null, 'gradinggradeoverbyx', 'overby');
        $this->content  = $assessment->feedbackreviewer;
        $this->format   = $assessment->feedbackreviewerformat;
    }
}


/**
 * Holds the final grades for the activity as are stored in the gradebook
 */
class advwork_final_grades implements renderable {

    /** @var object the info from the gradebook about the grade for submission */
    public $submissiongrade = null;

    public $instanceitemid = '';

    /** @var object the infor from the gradebook about the grade for assessment */
    public $assessmentgrade = null;
}

/**
 * Holds the overall grades and reliability metrics for a student
 */
class advwork_overall_grades implements renderable {

    /** @var object the info about the overall grade for submissions */
    public $overallsubmissiongrade = null;

    /** @var object the info about the overall grade for assessment */
    public $overallassessmentgrade = null;

    /** @var object the info about the reliability metrics */
    public $reliability_metrics = null;

}

/**
 * Holds the reliability metrics
 */
class advwork_reliability_metrics implements renderable {
    public $continuitysessionlevel = null;

    public $continuitychunks = null;

    public $stability = null;

    public $reliability = null;
}

/**
 * Holds the entries for a student model for a advwork session
 */
class advwork_student_model implements renderable {
    public $advworkid = null;

    public $advworkname = null;

    public $userid = null;

    public $entries = null;
}

/**
 * Holds multiple student models
 */
class advwork_student_models implements renderable {
    public $studentmodels = null;
}

/**
 * Holds the average submission grade for a session
 */
class advwork_average_submission_grade_session implements renderable {
    public $averagesubmissiongrade = null;
}

/**
 * Holds the standard deviation of the submissions' grades for a session
 */
class advwork_standard_deviation_submissions_grades_session implements renderable {
    public $standarddeviation = null;
}

/**
 * Holds the standard deviation of the average submissions' grades for the sessions
 */
class advwork_standard_deviation_average_submissions_grades implements renderable {
    public $standarddeviation = null;
}

/**
 * Holds the first and third quartile of the values of a capability
 */
class capability_quartiles {
    public $capabilityname;

    public $iscumulated;

    public $firstquartile;

    public $thirdquartile;
}