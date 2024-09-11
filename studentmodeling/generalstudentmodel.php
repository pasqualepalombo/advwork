<?php
/**
 * Created by PhpStorm.
 * User: Gabriel
 * Date: 5/22/2018
 * Time: 4:14 PM
 */


require(__DIR__.'/../../../config.php');
require_once(__DIR__.'/../locallib.php');

$id         = optional_param('id', 0, PARAM_INT);

$cm             = get_coursemodule_from_id('advwork', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$advworkrecord = $DB->get_record('advwork', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/advwork:view', $PAGE->context);

$advwork = new advwork($advworkrecord, $cm, $course);

$PAGE->set_url($advwork->general_student_model_url($advwork->id));

// Mark viewed.
$advwork->set_module_viewed();


$userplan = new advwork_user_plan($advwork, $USER->id);

foreach ($userplan->phases as $phase) {
    if ($phase->active) {
        $currentphasetitle = $phase->title;
    }
}

$PAGE->set_title($advwork->name);
$PAGE->set_heading($course->fullname);

$output = $PAGE->get_renderer('mod_advwork');

/// Output starts here

echo $output->header();

/**
 * Display the models of the specified user for all the advwork sessions
 *
 * @param $output           object used to render data
 * @param $advwork         advwork instance
 * @param $courseid         id of the current course
 * @param  $userid          id of the student to display the models for
 * @return void
 *
 */
function display_student_models_all_sessions($output, $advwork, $courseid) {
    global $DB;
    global $USER;

    $studentmodels = $advwork->get_student_models_all_sessions($DB, $courseid, $USER->id);
    if (!empty($studentmodels)) {
        print_collapsible_region_start('', 'advwork-viewlet-yourstudentmodels', get_string('yourstudentmodels', 'advwork'));
        echo $output->box_start('generalbox grades-yourstudentmodels');
        echo $output->render($studentmodels);
        echo $output->box_end();
        print_collapsible_region_end();
    }
}

/**
 * Display the general student model
 *
 * @param $output                   object used to render data
 * @param $advwork                 advwork instance
 * @param $courseid                 id of the course to get the overall grades for student
 * @return void
 */
function display_general_student_model($output, $advwork, $courseid) {
    global $USER;

    $generalstudentmodel = $advwork->get_general_student_model($courseid, $USER->id);

    // the correctness is the average of C for all sessions
    $studentgrades = $advwork->get_student_grades($courseid, $USER->id);
    $capabilityname = "C";
    $capabilityentries = array_filter($generalstudentmodel->entries, function($entry) use($capabilityname){
        return $entry->capability == $capabilityname;
    });
    $capabilityentry = reset($capabilityentries);

    $averagegrade = $advwork->compute_average_value($studentgrades);
    $capabilityentry->capabilityoverallvalue = $averagegrade;

    if($averagegrade < 0.55) {
        $capabilityentry->capabilityoverallgrade = "F";
    } else if ($averagegrade < 0.65) {
        $capabilityentry->capabilityoverallgrade = "E";
    } else if ($averagegrade < 0.75) {
        $capabilityentry->capabilityoverallgrade = "D";
    } else if ($averagegrade < 0.85) {
        $capabilityentry->capabilityoverallgrade = "C";
    } else if ($averagegrade < 0.95) {
        $capabilityentry->capabilityoverallgrade = "B";
    } else {
        $capabilityentry->capabilityoverallgrade = "A";
    }

    if (!empty($generalstudentmodel)) {
        print_collapsible_region_start('', 'advwork-viewlet-yourstudentmodel', get_string('yourgeneralstudentmodel', 'advwork'));
        echo $output->box_start('generalbox grades-yourstudentmodel');
        echo $output->render($generalstudentmodel);
        echo $output->box_end();
        print_collapsible_region_end();
    }
}

/**
 * Displays the student model evolution during the sessions and also the relative position to other peers in the class
 *
 * @param $DB               Moodle Database API object
 * @param $advwork         advwork instance
 * @param $courseid         id of the current course
 * @param $userid           id of the student to display the model for
 * @return void
 */
function display_student_model_historical_progress($advwork, $courseid) {
    global $isgeneralstudentmodelpage;
    global $DB;
    global $USER;
    global $studentmodelsallsessions;
    global $capabilities;
    global $studentsmodels;
    global $studentsmodelsprevioussession;
    global $currentuserid;
    global $coursesessions;
    global $currentuseraveragegrade;
    global $averagesubmissiongradesallusers;

    $currentuserid = $USER->id;

    $isgeneralstudentmodelpage = true;
    $coursesessions = $advwork->get_course_advwork_sessions($DB, $courseid);
    $studentmodelsallsessions = $advwork->get_student_models_all_sessions($DB, $courseid, $USER->id); // models of the current student for all sessions
    $capabilities = $advwork->get_capabilities();

    $studentsenrolledtocourse = $advwork->get_students_enrolled_to_course($DB, $courseid);
    $studentsmodels = [];
    $studentsmodelsprevioussession = [];
    $averagesubmissiongradesallusers = [];
    foreach ($studentsenrolledtocourse as $student) {
        $studentsmodels[] = $advwork->get_general_student_model($courseid, $student->userid);

        $advworkidlastsession = $advwork->get_last_advwork_session_id_student_attended($courseid, $student->userid);
        $studentsmodelsprevioussession[] = $advwork->get_student_model_before_specified_session($courseid, $advworkidlastsession, $student->userid, 0);

        $studentgrades = $advwork->get_student_grades($courseid, $student->userid);
        $averagestudentgrades = $advwork->compute_average_value($studentgrades);
        if(is_numeric($averagestudentgrades) && !is_nan($averagestudentgrades)) {
            $averagesubmissiongradesallusers[] = $averagestudentgrades;
        }

         if($currentuserid == $student->userid) {
             $currentuseraveragegrade = $averagestudentgrades;
         }
    }

    include('studentrelativeposition.php');
    include('studentmodelhistoricalprogress.php');
}

$courseid = $cm->course;
display_general_student_model($output, $advwork, $courseid);
display_student_model_historical_progress($advwork, $courseid);
display_student_models_all_sessions($output, $advwork, $courseid);

echo $output->footer();