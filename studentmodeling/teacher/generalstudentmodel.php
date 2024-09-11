<?php

require(__DIR__.'/../../../../config.php');
require_once(__DIR__.'/../../locallib.php');

$id         = optional_param('id', 0, PARAM_INT);
$sortby     = optional_param('sortby', 'submissiongrade', PARAM_ALPHA);
$sorthow    = optional_param('sorthow', 'ASC', PARAM_ALPHA);

$cm             = get_coursemodule_from_id('advwork', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$advworkrecord = $DB->get_record('advwork', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/advwork:view', $PAGE->context);

$advwork = new advwork($advworkrecord, $cm, $course);

$PAGE->set_url($advwork->general_student_model_url_teacher($id));

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

if (isset($_POST['delete_empty_session'])) {    // toglie le sessione vuote premendo il bottone che sta nel file submissionsgradeshistoricalprogress.php. Questo controllo va messo qui per permettere di ricaricare la pagina.
    echo "Eseguo la query (".$_POST['delete_empty_session'].")<br>";
    if ($advwork->isThisSessionEmpty($advwork->id)==false) {
        $advwork->deleteEmptySessions();
        header('Location: '.$_SERVER['REQUEST_URI']); // ricarica la pagina, sennò si vedrebbe ancora la sessione cancellata nel grafico.
    }
}

$output = $PAGE->get_renderer('mod_advwork');

/// Output starts here

echo $output->header();

//echo "User id: ".$USER->id;
/**
 * Get the average submissions grades for all the sessions
 *
 * @param $advwork             advwork instance
 * @param $courseid             Id of the course
 * @return array with the average submissions grades for all the sessions
 */
function get_average_submissions_grades($advwork, $courseid) {
    global $DB;

    $averagesubmissionsgrades = [];
    $advworksessions = $advwork->get_course_advwork_sessions($DB, $courseid);
    foreach ($advworksessions as $advworksession) {
        $averagesubmissiongradesession = $advwork->compute_average_submission_grade_session($advworksession, $courseid);
        $averagesubmissionsgrades[] = $averagesubmissiongradesession;
    }

    return $averagesubmissionsgrades;
}

/**
 * Displays in a chart the average submissions grades for the sessions
 *
 * @param $advwork             advwork instance
 * @param $courseid             Id of the course
 */
function display_average_submissions_grades_sessions($advwork, $courseid) {
    global $DB;
    global $averagesubmissionsgrades;

    $advworksessions = $advwork->get_course_advwork_sessions($DB, $courseid);
	
    $averagesubmissionsgrades = [];
    foreach ($advworksessions as $advworksession) {
        $sessionname = $advworksession->name;

        $averagesubmissiongradesession = $advwork->compute_average_submission_grade_session($advworksession, $courseid);
        $averagesubmissionsgrades[$sessionname] = $averagesubmissiongradesession;
    }

    include('submissionsgradeshistoricalprogress.php');
}

/**
 * Display the standard deviation of the average submissions grades for the sessions
 *
 * @param $output                   object used to render data
 * @param $advwork                 advwork instance
 * @param $courseid                 id of the course
 * @return void
 */
function display_standard_deviation_average_submission_grades($output, $advwork, $courseid) {
    $averagesubmissionsgrades = get_average_submissions_grades($advwork, $courseid);
    $averagesubmissionsgradesstandarddeviation = $advwork->compute_standard_deviation($averagesubmissionsgrades);

    $objecttorender = new advwork_standard_deviation_average_submissions_grades();
    $objecttorender->standarddeviation = $averagesubmissionsgradesstandarddeviation;

    if(!empty($averagesubmissionsgrades) && !is_nan($averagesubmissionsgradesstandarddeviation)) {
        print_collapsible_region_start('', 'advwork-viewlet-standarddeviationaveragesubmissionsgrades', get_string('standarddeviationaveragesubmissionsgradesallsessions', 'advwork'));
        echo $output->box_start('generalbox grades-standarddeviationaveragesubmissionsgrades');
        echo $output->render($objecttorender);
        echo $output->box_end();
        print_collapsible_region_end();
    }
}

/**
 * Displays in a table the general student models and the reliability metrics for each student
 * The current columns are: student name, average submission grade, competence, assessment capability, continuity, stability, reliability
 *
 * @param $output                   Object used to render data
 * @param $advwork                 advwork instance
 * @param $courseid                 Id of the course
 * @param $sortby                   Column to sort by
 * @param $sorthow                  ASC|DESC
 */
function display_general_student_models_table($output, $advwork, $courseid, $sortby, $sorthow) {
    global $USER;

    $data = new stdClass();
    $data->studentmodelsdata = $advwork->prepare_general_student_models_report_data($USER->id, $courseid, $sortby, $sorthow);

    if ($data) {
        // grading report display options
        $reportopts                          = new stdclass();
        $reportopts->sortby                  = $sortby;
        $reportopts->sorthow                 = $sorthow;

        print_collapsible_region_start('', 'advwork-viewlet-generalstudentmodelsgradereport', get_string('generalstudentmodelsgradereport', 'advwork'));
        echo $output->box_start('generalbox generalstudentmodelsgradereport');
        echo $output->render(new advwork_general_student_models_grading_report($data, $reportopts));
        echo $output->box_end();
        print_collapsible_region_end();

        $path = dirname(__DIR__, 2)."/FileCsv.php";
        require_once($path);
        $intestazione = Array('name', 'submissiongrade', 'competence', 'assessmentcapability', 'continuity', 'stability', 'reliability');
        $dati = Array($intestazione);
        foreach ($data->studentmodelsdata as $riga) {
            $submissiongrade = number_format((float) $riga->submissiongrade, 2, '.', '');
            $competence = number_format((float) $riga->competence, 2, '.', '');
            $assessmentcapability = number_format((float) $riga->assessmentcapability, 2, '.', '');
            $continuity = number_format((float) $riga->continuity, 2, '.', '');
            $stability = number_format((float) $riga->stability, 2, '.', '');
            $reliability = number_format((float) $riga->reliability, 2, '.', '');
            $dati[] = Array($riga->name, $submissiongrade, $competence, $assessmentcapability, $continuity, $stability, $reliability);
        }
        $nomeFile = "general-student-report.csv";
        $file = new FileCsv($nomeFile, $dati);
        $file->creaFile();
        echo $output->download_button($nomeFile, "Export data (CSV)");
    }
}

$courseid = $cm->course;

display_general_student_models_table($output, $advwork, $courseid, $sortby, $sorthow);
display_average_submissions_grades_sessions($advwork, $courseid);
display_standard_deviation_average_submission_grades($output, $advwork, $courseid);

echo $output->footer();