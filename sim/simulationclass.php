<?php

/**
 * Aggregates the grades for submission and grades for assessments
 *
 * @package    mod_advwork
 * @copyright  me stesso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');


// the params to be re-passed to view.php
$page       = optional_param('page', 0, PARAM_INT);
$cm         = get_coursemodule_from_id('advwork', $cmid, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$advwork   = $DB->get_record('advwork', array('id' => $cm->instance), '*', MUST_EXIST);
$advwork   = new advwork($advwork, $cm, $course);

$PAGE->set_url($advwork->aggregate_url(), compact('confirm', 'page', 'sortby', 'sorthow'));

require_login($course, false, $cm);
