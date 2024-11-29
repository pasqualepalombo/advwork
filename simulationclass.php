<?php
/**
 * Creazione della classe simulata, prima versione
 * 
 * Questa versione richiede vari input da parte dell'utente.
 * La prossima farà in modo da avere un'unico pannello di controllo
 * e inviare la creazione, submission e assessment in maniera unica.
 *
 * @package    mod_advwork
 * @copyright  2024 Pasquale Palombo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

#Advwork Library
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');
require_once(__DIR__.'/allocation/random/lib.php');

#Moodle Library for creating/handling users
require_once($CFG->libdir . '/moodlelib.php'); # Include il user_create_user
require_once($CFG->dirroot . '/user/lib.php'); # Include la libreria utenti di Moodle.
require_once($CFG->libdir . '/datalib.php'); # Include le funzioni del database di Moodle.
require_once($CFG->libdir . '/enrollib.php'); // Include le funzioni di iscrizione.

#Moodle Library for managing groups and grouping
require_once($CFG->dirroot . '/group/lib.php');

use core_user; # Usa il namespace corretto per la classe core_user.

$id         = required_param('id', PARAM_INT); #id dell'activity, non del corso
$w          = optional_param('w', 0, PARAM_INT);  # forse scarto
$editmode   = optional_param('editmode', null, PARAM_BOOL);
$page       = optional_param('page', 0, PARAM_INT);
$perpage    = optional_param('perpage', null, PARAM_INT);
$sortby     = optional_param('sortby', 'lastname', PARAM_ALPHA);
$sorthow    = optional_param('sorthow', 'ASC', PARAM_ALPHA);
$eval       = optional_param('eval', null, PARAM_PLUGIN);

# Cotrollo se il parametro ID è stato passato.
if ($id) {
    $cm             = get_coursemodule_from_id('advwork', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $advworkrecord = $DB->get_record('advwork', array('id' => $cm->instance), '*', MUST_EXIST);
    $wid=$id; # forse scarto
} else {
    $advworkrecord = $DB->get_record('advwork', array('id' => $w), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $advworkrecord->course), '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('advwork', $advworkrecord->id, $course->id, false, MUST_EXIST);
    $wid=$w; # forse scarto
}

require_login($course, true, $cm);
require_capability('mod/advwork:simulationclass', $PAGE->context);


$advwork = new advwork($advworkrecord, $cm, $course);
$courseid = $advwork->course->id;
$courseteachersid = $advwork->get_course_teachers($courseid);
$iscourseteacher = in_array($USER->id, $courseteachersid);
$advwork->setCapabilitiesDB();
$advwork->setDomainValueDB();
$PAGE->set_url($advwork->view_url());
$advwork->set_module_viewed();
$output = $PAGE->get_renderer('mod_advwork');

$PAGE->set_title('Simulation Class');

#SIM MESSAGES
$message_form = '';
$message_create = '';
$message_enroll = '';
$message_submission = '';
$message_groups = '';
$message_allocation = '';
$message_grades = '';
$message_teacher = '';
$random_tries = 0;

#SIM FORM HANDLER
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_assessment_btn'])) {
        $values_array = [
            'first_aspect_desc' => $_POST['first_aspect_desc'],
            'first_weight' => intval($_POST['first_weight']),
            'second_aspect_desc' => $_POST['second_aspect_desc'],
            'second_weight' => intval($_POST['second_weight']),
            'third_aspect_desc' => $_POST['third_aspect_desc'],
            'third_weight' => intval($_POST['third_weight'])
        ];
        update_assessment_form($values_array, $advwork->id);
    }
    elseif (isset($_POST['create_students_btn'])) {
        $students_number_to_create = intval($_POST["students_number_to_create"]);
        create_simulation_students($students_number_to_create);
    }
    elseif (isset($_POST['enroll_students_btn'])) {
        $students_number_to_enroll= intval($_POST["students_number_to_enroll"]);
        enroll_simulated_users($students_number_to_enroll, $courseid);
    }
    elseif (isset($_POST['create_submissions_btn'])) {
        create_submissions($courseid, $advwork->id);
    }
    elseif (isset($_POST['create_groups_btn'])) {
        $groups_size = intval($_POST["group_size_to_create"]);
        create_groups_for_course($courseid, $groups_size);
    }
    elseif (isset($_POST['create_grouping_btn'])) {
        create_grouping_with_all_groups($courseid, 'SIM Grouping');
    }
    elseif (isset($_POST['create_random_allocation_btn'])) {
        $reviewers_size = intval($_POST["reviewers_size"]);
        if ($reviewers_size < 3) {
            $reviewers_size = 3;
        }
        $random_attempts = intval($_POST["random_attempts"]);
        create_allocation_among_groups($courseid, $advwork->id,$reviewers_size, $random_attempts);
    }
    elseif (isset($_POST['create_sequential_allocation_btn'])) {
        $reviewers_size = intval($_POST["reviewers_size"]);
        if ($reviewers_size < 3) {
            $reviewers_size = 3;
        }
        create_sequential_allocation_among_groups($courseid, $advwork->id,$reviewers_size);
    }
    elseif (isset($_POST['create_grades_random_btn'])) {
        process_grades($advwork->id);
    }
    elseif (isset($_POST['create_lig_students_btn'])) {
        process_lig_students($advwork->id);
    }
    elseif (isset($_POST['create_lig_grades_btn'])) {
        process_lig_grades($advwork->id);
    }
    elseif (isset($_POST['teacher_evaluation'])) {
        $num = intval($_POST['teacher_number']);
        automatic_teacher_evaluation($num,$output, $advwork, $courseid);
    }
    elseif (isset($_POST['update_bn_models'])) {
        $bn_radio = intval($_POST['bn_radio']);
        update_bn_models($courseid, $advwork->id, $bn_radio);
    }
}

#SIM FUNCTIONS
function update_assessment_form($values, $advworkid) {
    global $DB;
    global $message_form;
    $rows = [];

    #Primo Aspect
    $rows[] = (object) [
        'advworkid' => $advworkid,
        'sort' => 1,
        'description' => '<p dir="ltr" style="text-align: left;">' . $values['first_aspect_desc'] . '</p>',
        'descriptionformat' => 1,
        'grade' => 10,
        'weight' => intval($values['first_weight'])
    ];

    #Secondo Aspect
    $rows[] = (object) [
        'advworkid' => $advworkid,
        'sort' => 2,
        'description' => '<p dir="ltr" style="text-align: left;">' . $values['second_aspect_desc'] . '</p>',
        'descriptionformat' => 1,
        'grade' => 10,
        'weight' => intval($values['second_weight'])
    ];

    //Terzo Aspect
    $rows[] = (object) [
        'advworkid' => $advworkid,
        'sort' => 3,
        'description' => '<p dir="ltr" style="text-align: left;">' . $values['third_aspect_desc'] . '</p>',
        'descriptionformat' => 1,
        'grade' => 10,
        'weight' => intval($values['third_weight'])
    ];

    foreach ($rows as $row) {
        $existing_record = $DB->get_record('advworkform_acc_mod', ['advworkid' => $advworkid, 'sort' => $row->sort]);

        if ($existing_record) {
            $row->id = $existing_record->id;
            $DB->update_record('advworkform_acc_mod', $row);
        } else {
            $DB->insert_record('advworkform_acc_mod', $row);
        }
    }
    $message_form = "Assessment Form aggiornato con successo";
}

function read_how_many_sim_students_already_exists($prefix = 'sim_student', $return_array = true){
    global $DB;

    $sql = "SELECT * FROM {user} WHERE username LIKE :prefix AND deleted = 0"; 
    $params = ['prefix' => $prefix . '%'];

    # Se si vuole restituire l'array con i risultati.
    if ($return_array) {
        $students = $DB->get_records_sql($sql, $params);
        return $students;
    } else {
        # Se si vuole solo il numero degli utenti che corrispondono.
        $sql_count = "SELECT COUNT(*) FROM {user} WHERE username LIKE :prefix AND deleted = 0";
        $count = $DB->count_records_sql($sql_count, $params);
        return $count;
    }
}

function display_function_message($message){
    if (!empty($message)) {
        echo '<div class="alert alert-warning" role="alert"> ' . $message . '</div>';
    }
}

function create_simulation_students($students_number_to_create){
    global $message_create;
    $students_number_already_created = read_how_many_sim_students_already_exists('sim_student', false);
    
    if ($students_number_already_created >= $students_number_to_create) {
        
        $message_create = 'Si hanno a disposizione abbastanza studenti';
    }
    elseif ($students_number_already_created < $students_number_to_create) {
        $remaining_students = $students_number_to_create - $students_number_already_created;
        for ($x = $students_number_already_created + 1; $x <= $students_number_to_create; $x++) {

            $userdata = [
                'username' => 'sim_student_' . $x,
                'password' => '123',
                'firstname' => 'SIM ' . $x,
                'lastname' => 'STUDENT',
                'email' => 'student' . $x . '@sim.com'
            ];
            
            # Chiama la funzione per creare il nuovo utente.
            try {
                $new_user = create_custom_user($userdata);
                $message_create = "Utenti creato con successo.";
            } catch (Exception $e) {
                $message_create = "Errore nella creazione dell'utente: " . $e->getMessage();
            }

        }
    }
}

function create_custom_user($userdata) {
    # Controlla che i dati necessari siano presenti.
    if (empty($userdata['username']) || empty($userdata['password']) || empty($userdata['email'])) {
        throw new Exception('Dati mancanti: username, password o email non sono presenti.');
    }

    $user = new stdClass();
    $user->username = $userdata['username'];
    $user->password = hash_internal_user_password($userdata['password']);
    $user->firstname = $userdata['firstname'];
    $user->lastname = $userdata['lastname'];
    $user->email = $userdata['email'];
    $user->confirmed = 1;
    $user->mnethostid = $GLOBALS['CFG']->mnet_localhost_id;
    $user->auth = 'manual';
    $user->timecreated = time();
    $user->timemodified = time();

    # user_create_user è la funziona di moodle
    $new_user = user_create_user($user);

    return $new_user;
}

function get_students_in_course($courseid, $return_array = true) {
    global $DB;
    
    $sql = "
        SELECT DISTINCT u.id, u.username, u.firstname, u.lastname, u.email
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {context} ctx ON ra.contextid = ctx.id
        JOIN {role} r ON ra.roleid = r.id
        WHERE e.courseid = :courseid
        AND r.shortname = 'student'";  // Ruolo di studente.
    
    $params = ['courseid' => $courseid];

    # Se si vuole restituire l'array di studenti.
    if ($return_array) {
        $students = $DB->get_records_sql($sql, $params);
        return $students;
    } else {
        # Se si vuole solo il numero degli studenti.
        $sql_count = "
            SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ra.contextid = ctx.id
            JOIN {role} r ON ra.roleid = r.id
            WHERE e.courseid = :courseid
            AND r.shortname = 'student'";
        
        $count = $DB->count_records_sql($sql_count, $params);
        return $count;
    }
}

function enroll_simulated_users($students_number_to_enroll, $courseid){    
    global $message_enroll;
    global $DB;

    $students_number_already_created = read_how_many_sim_students_already_exists('sim_student', false);

    if ($students_number_to_enroll <= $students_number_already_created) {
        
        $students = read_how_many_sim_students_already_exists('sim_student', true);
        $students_to_enroll = array_slice($students, 0, $students_number_to_enroll);
        
        if (empty($courseid) || empty($students_to_enroll)) {
            $message_enroll = 'Il corso o gli studenti non sono stati definiti correttamente.';
        }
        
        $enrol = $DB->get_record('enrol', array('courseid' => $courseid, 'enrol' => 'manual'), '*', MUST_EXIST);

        $enrol_manual = enrol_get_plugin('manual');

        if ($enrol && $enrol_manual) {
            foreach ($students_to_enroll as $student) {
                $student_id = $student->id; 
                $enrol_manual->enrol_user($enrol, $student_id, 5); // 5 è l'ID del ruolo di 'student' di mdl_role.
            }
            $message_enroll = "Studenti iscritti con successo al corso";
        } else {
            $message_enroll = 'Non è stato possibile trovare il metodo di iscrizione manuale per il corso.';
        } 
    }
    else {
        $message_enroll = 'Non ci sono abbastanza studenti per iscriverli tutti';
    }
}

function get_submissions($advwork_id, $return_array = true) {
    global $DB;

    $sql = "
        SELECT *
        FROM mdl_advwork_submissions
        WHERE advworkid = :advworkid";

    $params = ['advworkid' => $advwork_id];

    // Se si vuole restituire l'array di submission
    if ($return_array) {
        $submissions = $DB->get_records_sql($sql, $params);
        return $submissions;
    } else {
        // Se si vuole solo il numero delle submission
        $sql_count = "
            SELECT COUNT(*)
            FROM mdl_advwork_submissions
            WHERE advworkid = :advworkid";

        $count = $DB->count_records_sql($sql_count, $params);
        return $count;
    }
}

function get_submission_authors_id($advwork_id) {
    global $DB;

    $sql = "
        SELECT authorid
        FROM mdl_advwork_submissions
        WHERE advworkid = :advworkid";

    $params = ['advworkid' => $advwork_id];
    $submissions = $DB->get_records_sql($sql, $params);
    return $submissions;
}

function create_submissions($courseid, $advwork_id) {
    global $DB;
    global $message_submission;
    # si creano solo le submission degli studenti che non ne hanno una
    $enrolled_students = get_students_in_course($courseid, true);
    $submission_authors_already_exited = get_submission_authors_id($advwork_id);
    $latest_students = array_filter($enrolled_students, function($student) use ($submission_authors_already_exited) {
        foreach ($submission_authors_already_exited as $author) {
            if ($student->id === $author->authorid) {
                return false;
            }
        }
        return true;
    });
    
    foreach ($latest_students as $student){
        $title = 'Submission_title_by_' .$student->username;
        $content = '<p dir="ltr" style="text-align: left;">Sumission_content_by_' .$student->username .'</p>';
        $authorid = $student->id;
        $data = new stdClass();
        $data->advworkid = $advwork_id;
        $data->authorid = $student->id;
        $data->timecreated = time();
        $data->timemodified = time();
        $data->title = $title;
        $data->content = $content;
        $data->feedbackauthorformat = 1;
        $data->contentformat = 1; # 1 per testo)
        
        $DB->insert_record('advwork_submissions', $data);
    }
        
    $message_submission = 'Tutti gli studenti ora hanno la propria submission.';
}

function count_groups_in_course($courseid) {
    global $DB;
    $sql = "SELECT COUNT(id) 
            FROM {groups} 
            WHERE courseid = :courseid";
    $params = ['courseid' => $courseid];
    $count = $DB->count_records_sql($sql, $params);
    return $count;
}

function create_groups_for_course($courseid, $group_size) { 
    global $DB;
    global $message_groups;

    $students = get_students_in_course($courseid, true);
    $students_array = array_values($students);

    $total_students = count($students_array);
    
    # Verifica che il gruppo abbia almeno 4 persone
    if ($group_size < 4) {
        $message_groups = "Dimensione non valida, i gruppi devono avere almeno 4 persone.";
        $message_groups = $group_size;
        return;
    }
    
    $num_groups = ceil($total_students / $group_size);

    # Se la dimensione non permette di avere gruppi validi(cioè tutti almeno 4 persone)
    # questo significa che si possono avere gruppi di dimensione variabile come 8 8 e 7
    while (($total_students % $group_size < 4 && $total_students % $group_size > 0) || $group_size < 4) {
        $message_groups = "Dimensione non valida. Riduzione della dimensione del gruppo.";
        $group_size--;
        if ($group_size < 4) {
            $message_groups = "Impossibile creare gruppi con almeno 4 membri. Operazione annullata.";
            return;
        }
        $num_groups = ceil($total_students / $group_size);
    }

    if ($num_groups == count_groups_in_course($courseid)){
        $message_groups = 'Gruppi già presenti, non ne sono stati creati altri';
        return;
    }

    for ($i = 0; $i < $num_groups; $i++) {
        $group_name = "SIM Group " . ($i + 1);
        $group_data = new stdClass();
        $group_data->courseid = $courseid;
        $group_data->name = $group_name;
        $group_data->description = "Gruppo creato in maniera automatica.";
        $groupid = groups_create_group($group_data);

        for ($j = 0; $j < $group_size; $j++) {
            $student_index = ($i * $group_size) + $j;
            if ($student_index < $total_students) {
                $student_id = $students_array[$student_index]->id;
                groups_add_member($groupid, $student_id);
            }
        }
    }
    $message_groups = "Gruppi creati con successo per il corso con ID: $courseid";
}

function get_course_grouping_name($courseid) {
    global $DB;

    $course = $DB->get_record('course', ['id' => $courseid], 'defaultgroupingid');
    
    if (empty($course->defaultgroupingid)) {
        return "nessuno";
    }

    $grouping = $DB->get_record('groupings', ['id' => $course->defaultgroupingid], 'name');
    
    return $grouping ? $grouping->name : "nessuno";
}

function set_groups_course_setting($courseid, $groupingid) {
    global $DB;
    $data = new stdClass();
    $data->id = $courseid;
    $data->groupmode = SEPARATEGROUPS;
    $data->groupmodeforce = 1;
    $data->defaultgroupingid = $groupingid;
    $DB->update_record('course', $data);
}

function create_grouping_with_all_groups($courseid, $groupingname) {
    global $CFG, $DB;
    global $message_groups;

    $groupingdata = new stdClass();
    $groupingdata->courseid = $courseid;
    $groupingdata->name = $groupingname;
    $groupingdata->description = 'Grouping with all groups';
    $groupingdata->descriptionformat = FORMAT_HTML;
    $groupingdata->timecreated = time();
    $groupingdata->timemodified = time();

    $groupingid = groups_create_grouping($groupingdata);
    
    $groups = $DB->get_records('groups', array('courseid' => $courseid));

    foreach ($groups as $group) {
        groups_assign_grouping($groupingid, $group->id);
    }

    set_groups_course_setting($courseid, $groupingid);
    $message_groups = "Grouping effettuato con successo";
    return $groupingid;
}

function check_the_allocation($courseid, $advworkid) {
    global $DB;
    #TODO qui bisogna finalizzare i return una volta che le allocazioni funzionano
    $sql_submissions = "
        SELECT id
        FROM {advwork_submissions}
        WHERE advworkid = :advworkid
    ";
    
    $submission_ids = $DB->get_fieldset_sql($sql_submissions, ['advworkid' => $advworkid]);
    
    if (empty($submission_ids)) {
        return 'no';
    }
    
    $submissionids_placeholder = implode(',', array_fill(0, count($submission_ids), '?'));
    $sql_assessments = "
        SELECT reviewerid, COUNT(*) as review_count
        FROM {advwork_assessments}
        WHERE submissionid IN ($submissionids_placeholder)
        GROUP BY reviewerid
        HAVING COUNT(*) = 3
    ";

    $reviewers_with_three_reviews = $DB->get_records_sql($sql_assessments, $submission_ids);
    $students_number = get_students_in_course($courseid, false);
    $reviewers_number = count($reviewers_with_three_reviews);
    
    if ($students_number == $reviewers_number) {
        return 'ok';
    }
    else {
        return "$reviewers_number" + ',' + "$students_number";
    }
}

function get_single_submission_by_author_advwork($advwork_id, $authorid) {
    global $DB;

    $sql = "
        SELECT *
        FROM mdl_advwork_submissions
        WHERE advworkid = :advworkid AND authorid = :authorid";

    $params = ['advworkid' => $advwork_id, 'authorid' => $authorid];

    $submission = $DB->get_records_sql($sql, $params);
    return $submission;
}

function assign_submissions_to_students($student_ids, $submissions, $reviewers_size, $random_attempts) { 
    global $message_allocation;

    $total_students = count($student_ids);
    $total_submissions = count($submissions);
    
    #Verifica di compatibilità (sarà da cambiare i valori in automatico come i gruppi?)
    if ($total_submissions * $reviewers_size != $total_students * $reviewers_size) {
        $message_allocation = "Impossibile creare assegnazioni: verifica la compatibilità tra numero di studenti e submissions.";
        return [];
    }

    $assignments = [];
    #Traccia quante volte ogni studente è assegnato
    $assigned_count = array_fill_keys($student_ids, 0);
    
    foreach ($submissions as $index => $submission_id) {
        $student_id = $student_ids[$index];
        
        #Filtra gli studenti per escludere quello corrente e chi ha già il numero massimo di assegnazioni
        $possible_students = array_filter($student_ids, function($id) use ($student_id, $assigned_count, $reviewers_size) {
            return $id !== $student_id && $assigned_count[$id] < $reviewers_size;
        });
        
        #Verifica che ci siano abbastanza studenti disponibili
        #Visto che è casuale è possibile una sfortuna nello shuffle, perciò ci prova diverse volte
        #Sennò va in sequenziale visto che è sempre possibile, avverte in caso
        if (count($possible_students) < $reviewers_size) {
            $random_tries += 1;
            if ($random_tries >= $random_attempts) {
                $random_tries = 0;
                $message_allocation = "Non è stata trovata un'allazione casuale. Si è passati alla versione sequenziale";
                $assignments = assign_sequential_submissions_to_students($student_ids, $submissions, $reviewers_size);
                return $assignments;
            }
            else {
                assign_submissions_to_students($student_ids, $submissions, $reviewers_size, $random_attempts);
            }
            
        }
    
        shuffle($possible_students);
        $assigned_students = array_slice($possible_students, 0, $reviewers_size);
        
        $assignments[$submission_id] = $assigned_students;
        foreach ($assigned_students as $assigned_student) {
            $assigned_count[$assigned_student]++;
        }
    }

    
    return $assignments;
}


function write_assessments($assignments) {
    global $DB;
    global $message_allocation;

    foreach ($assignments as $submissionid => $reviewers) {
        
        #Se esistono già degli assessments alla submission li cancella (poichè possono variare i numeri dei peers)
        $DB->delete_records('advwork_assessments', ['submissionid' => $submissionid]);
        
        foreach ($reviewers as $reviewerid) {
            $data = new stdClass();
            $data->submissionid = $submissionid;
            $data->reviewerid = $reviewerid;
            $data->weight = 1;
            $data->timecreated = time();
            $data->feedbackauthorattachment = 0;
            $data->feedbackauthorformat = 1;
            $data->feedbackreviewerformat = 1;

            $DB->insert_record('advwork_assessments', $data);
        }
    }
    $message_allocation = "Allocazione effettuata con  successo";
}

function create_allocation_among_groups($courseid, $advworkid, $reviewers_size, $random_attempts) {
    global $DB;
    global $message_allocation;

    $groups_and_students = array();
    $groups = groups_get_all_groups($courseid);

    $student_ids;
    $submissions = [];

    if (!$groups) {
        $message_allocation = "Nessun gruppo trovato o sono vuoti";
        return;
    }
    
    foreach ($groups as $group) {
        $student_ids = array_keys(groups_get_members($group->id, 'u.id'));

        foreach ($student_ids as $authorid){
            $submission = get_single_submission_by_author_advwork($advworkid, $authorid);
            $first_key = array_key_first($submission);
            $submissions[] = $submission[$first_key]->id;
        }
 
        $assignments = assign_submissions_to_students($student_ids, $submissions, $reviewers_size, $random_attempts);

    
        write_assessments($assignments);

        $submissions = [];
    }

}

function assign_sequential_submissions_to_students($student_ids, $submissions, $reviewers_size) {
    
    #il numero delle sub è incrementale insieme a quello dello studente,
    #perciò l'n-esima sub è dell'n-esimo studente
    
    $assignments = [];
    $num_students = count($student_ids);

    for ($i = 0; $i < count($submissions); $i++) {
        $submission_id = $submissions[$i];
        $student_id = $student_ids[$i];
        
        $reviewers = [];
        
        $current_index = ($i + 1) % $num_students;
        
        while (count($reviewers) < $reviewers_size) {
            if ($student_ids[$current_index] !== $student_id) {
                $reviewers[] = $student_ids[$current_index];
            }
            $current_index = ($current_index + 1) % $num_students;
        }
        $assignments[$submission_id] = $reviewers;
    }
    
    return $assignments;
    

}

function create_sequential_allocation_among_groups($courseid, $advworkid, $reviewers_size) {
    global $DB;
    global $message_allocation;

    $groups_and_students = array();
    $groups = groups_get_all_groups($courseid);

    $student_ids;
    $submissions = [];

    if (!$groups) {
        $message_allocation = "Nessun gruppo trovato o sono vuoti";
        return;
    }
    
    foreach ($groups as $group) {
        $student_ids = array_keys(groups_get_members($group->id, 'u.id'));

        foreach ($student_ids as $authorid){
            $submission = get_single_submission_by_author_advwork($advworkid, $authorid);
            $first_key = array_key_first($submission);
            $submissions[] = $submission[$first_key]->id;
        }
 
        $assignments = assign_sequential_submissions_to_students($student_ids, $submissions, $reviewers_size);

        write_assessments($assignments);

        $submissions = [];
    }

}

function process_grades($advworkid) {
    global $DB;
    global $message_grades;

    $submissions = $DB->get_records('advwork_submissions', ['advworkid' => $advworkid]);

    if (!$submissions) {
        $message_grades = 'Non ci sono submission per questo advwork';
        return;
    }

    $submission_ids = array_keys($submissions);

    list($in_sql, $params) = $DB->get_in_or_equal($submission_ids);
    $assessments = $DB->get_records_select('advwork_assessments', "submissionid $in_sql", $params);

    if (!$assessments) {
        $message_grades = 'Non ci sono assessment per questo advwork';
        return;
    }

    $aspects = $DB->get_records('advworkform_acc_mod', ['advworkid' => $advworkid]);

    if (!$aspects) {
        $message_grades = 'Assessment Form non configurato';
        return;
    }

    $aspect_ids = array_keys($aspects);
    
    foreach ($assessments as $assessment) {
        $sum_weighted_grades = 0;
        $sum_weights = 0;

        foreach ($aspect_ids as $aspect_id) {
            $aspect = $aspects[$aspect_id];

            $grade = rand(0, 10);

            $grade_record = (object) [
                'assessmentid' => $assessment->id,
                'strategy' => 'acc_mod',
                'dimensionid' => $aspect_id,
                'grade' => $grade,
                'timecreated' => time(),
                'timemodified' => time(),
            ];

            $DB->insert_record('advwork_grades', $grade_record);

            $max_grade = $aspect->grade;
            $weight = $aspect->weight; 
            $sum_weighted_grades += ($grade / $max_grade) * $weight;
            $sum_weights += $weight;
        }

        if ($sum_weights > 0) {
            $final_grade = $sum_weighted_grades / $sum_weights;
            $final_grade = $final_grade * 100;
        } else {
            #evitare la divisione per zero
            $final_grade = 0;
        }

        $assessment->grade = $final_grade;
        $assessment->timemodified = time();

        $DB->update_record('advwork_assessments', $assessment);
    }
}

function process_lig_students($advworkid) {
    global $DB;
    global $message_grades;

    #ottengo le submission
    $submissions = $DB->get_records('advwork_submissions', ['advworkid' => $advworkid]);
    if (!$submissions) {
        $message_grades = 'Non ci sono submission per questo advwork';
        return;
    }
    $submission_ids = array_keys($submissions);

    list($in_sql, $params) = $DB->get_in_or_equal($submission_ids);
    $assessments = $DB->get_records_select('advwork_assessments', "submissionid $in_sql", $params);

    if (!$assessments) {
        $message_grades = 'Non ci sono assessment per questo advwork';
        return;
    }

    $aspects = $DB->get_records('advworkform_acc_mod', ['advworkid' => $advworkid]);

    if (!$aspects) {
        $message_grades = 'Assessment Form non configurato';
        return;
    }

    $aspect_ids = array_keys($aspects);
    
    foreach ($assessments as $assessment) {
        #da submissionid posso ottenere lo studente con authorid
        $title = get_submission_title($assessment->submissionid);
        $type = get_lig_student_type($title);

        if ($type==0) {
            $message_grades = 'Errore nella suddivisione in categorie LIG';
            return;
        }

        $sum_weighted_grades = 0;
        $sum_weights = 0;

        foreach ($aspect_ids as $aspect_id) {
            $aspect = $aspects[$aspect_id];
            $grade = 0;

            if($type == 1) {$grade = rand(9, 10);}
            if($type == 2) {$grade = rand(7, 8);}
            if($type == 3) {$grade = rand(3, 6);}            

            $grade_record = (object) [
                'assessmentid' => $assessment->id,
                'strategy' => 'acc_mod',
                'dimensionid' => $aspect_id,
                'grade' => $grade,
                'timecreated' => time(),
                'timemodified' => time(),
            ];

            $DB->insert_record('advwork_grades', $grade_record);

            $max_grade = $aspect->grade;
            $weight = $aspect->weight; 
            $sum_weighted_grades += ($grade / $max_grade) * $weight;
            $sum_weights += $weight;
        }

        if ($sum_weights > 0) {
            $final_grade = $sum_weighted_grades / $sum_weights;
            $final_grade = $final_grade * 100;
        } else {
            #evitare la divisione per zero
            $final_grade = 0;
        }

        $assessment->grade = $final_grade;
        $assessment->timemodified = time();

        $DB->update_record('advwork_assessments', $assessment);
    }
}

function process_lig_grades($advworkid) {
    global $DB;
    global $message_grades;

    #ottengo le submission
    $submissions = $DB->get_records('advwork_submissions', ['advworkid' => $advworkid]);
    if (!$submissions) {
        $message_grades = 'Non ci sono submission per questo advwork';
        return;
    }
    $submission_ids = array_keys($submissions);

    list($in_sql, $params) = $DB->get_in_or_equal($submission_ids);
    $assessments = $DB->get_records_select('advwork_assessments', "submissionid $in_sql", $params);

    if (!$assessments) {
        $message_grades = 'Non ci sono assessment per questo advwork';
        return;
    }

    $aspects = $DB->get_records('advworkform_acc_mod', ['advworkid' => $advworkid]);

    if (!$aspects) {
        $message_grades = 'Assessment Form non configurato';
        return;
    }

    $aspect_ids = array_keys($aspects);
    
    foreach ($assessments as $assessment) {

        $sum_weighted_grades = 0;
        $sum_weights = 0;
        $counter = 1;
        foreach ($aspect_ids as $aspect_id) {
            $aspect = $aspects[$aspect_id];
            $grade = 0;

            if($counter == 1) {$grade = rand(9, 10);}
            if($counter == 2) {$grade = rand(7, 8);}
            if($counter == 3) {$grade = rand(3, 6);}            

            $counter += 1;
            
            $grade_record = (object) [
                'assessmentid' => $assessment->id,
                'strategy' => 'acc_mod',
                'dimensionid' => $aspect_id,
                'grade' => $grade,
                'timecreated' => time(),
                'timemodified' => time(),
            ];

            $DB->insert_record('advwork_grades', $grade_record);

            $max_grade = $aspect->grade;
            $weight = $aspect->weight; 
            $sum_weighted_grades += ($grade / $max_grade) * $weight;
            $sum_weights += $weight;
        }

        if ($sum_weights > 0) {
            $final_grade = $sum_weighted_grades / $sum_weights;
            $final_grade = $final_grade * 100;
        } else {
            #evitare la divisione per zero
            $final_grade = 0;
        }

        $assessment->grade = $final_grade;
        $assessment->timemodified = time();

        $DB->update_record('advwork_assessments', $assessment);
    }
}

function get_submission_title($submissionid) {
    global $DB;
    $record = $DB->get_record('advwork_submissions', ['id' => $submissionid], 'title', MUST_EXIST);
    return $record->title;
}

function get_lig_student_type($title) {
    #estrai il numero finale dal titolo
    if (preg_match('/_student_(\d+)$/', $title, $matches)) {
        $number = (int)$matches[1];
        $remainder = $number % 3;
        switch ($remainder) {
            case 1:
                return 1;
            case 2:
                return 2;
            case 0:
                return 3;
            default:
                return 0;
        }
    }
    else {
        return 0;
    }
}

# STUDENT MODELS
function update_bn_models($courseid, $advworkid, $bn_radio){
    #Not Selected
    if ($bn_radio==0){
        return;
    }
    #Use BN
    if ($bn_radio==1){
        return;
    }
    #Not Use BN
    if ($bn_radio==2){
        return;
    }
}

# AUTOMATIC TEACHER GRADES
# poichè ho aggiunto un'assesssment dove uno studente è bravo, uno è medio, uno insufficiente
# credo sia utile che il voto del prof debba confermare l'ipotesi della bravura dell'alunno.
# cioè studenti 1, 4, 7... sono tutti da A e B. I 2,5,8... sono C e D e cosi via.
function get_most_appropriate_answer_to_grade_next($output, $advwork, $courseid) {
    global $message_teacher;

    $groupid = groups_get_activity_group($advwork->cm, true);
    $nextbestsubmissiontograde = $advwork->get_next_best_submission_to_grade($advwork, $courseid, $groupid);
    if(empty($nextbestsubmissiontograde)) {
        $message_teacher = "Nessuna submission da valutare trovata";
        return;}
    return $nextbestsubmissiontograde->id;
}

function automatic_teacher_evaluation($num,$output, $advwork, $courseid){
    for ($i = 0; $i < $num; $i++) {
        global $DB;
        global $USER;

        #prendo l'id della submission
        $submission_id = get_most_appropriate_answer_to_grade_next($output, $advwork, $courseid);
        echo "<script type='text/javascript'>alert('$submission_id');</script>";

        #creazione riga su advwork_assessments
        $new_record = new stdClass();
        $new_record->submissionid = $submission_id;
        $new_record->reviewerid = $USER->id;
        $new_record->weight = 1;
        $new_record->timecreated = time();
        $assessment_id = $DB->insert_record('advwork_assessments', $new_record);

        #creazione delle righe su advwork_grades con i relativi acc_mod
        $acc_mod_records = $DB->get_records('advworkform_acc_mod', ['advworkid' => $advwork->id]);
        $dimension_ids = [];
        $dimension_grades = [];
        $dimension_weights = [];
        foreach ($acc_mod_records as $record) {
            $dimension_ids[] = $record->id;
            $dimension_grades[] = $record->grade;
            $dimension_weights[] = $record->weight;
        }
        
        $grades = [rand(6, 10), rand(6, 10), rand(6, 10)];

        foreach ($dimension_ids as $index => $dimension_id) {
            $new_record = new stdClass();
            $new_record->assessmentid = $assessment_id;
            $new_record->strategy = 'acc_mod';
            $new_record->dimensionid = $dimension_id;
            $new_record->grade = $grades[$index];
            $DB->insert_record('advwork_grades', $new_record);
        }

        #aggiornamento di adv_assessement per la media pesata
        $weighted_sum = 0;
        $total_weights = 0;
        foreach ($grades as $index => $grade) {
            $normalized_grade = $grade / $dimension_grades[$index];
            $weighted_sum += $normalized_grade * $dimension_weights[$index];
            $total_weights += $dimension_weights[$index];
        }
        if ($total_weights == 0) {
            return 0;
        }
        
        $data = new stdClass();
        $data->id = $assessment_id;
        $data->timemodified = time();
        $data->grade = ($weighted_sum / $total_weights)*100;
        $DB->update_record('advwork_assessments', $data);
    }
}

#LIG DEBUG
function get_capability_grades($userid, $capid) {
    global $DB;

    $sql = "SELECT capabilityoverallgrade
            FROM {advwork_student_models}
            WHERE userid = :userid AND capabilityid = :capid AND domainvalueid = 1";
    $params = ['userid' => $userid, 'capid' => $capid];
    $records = $DB->get_records_sql($sql, $params);
    if (!empty($records)) {
            echo " " . reset($records)->capabilityoverallgrade;
    }
}

function get_var_capability_grades($userid, $capid) {
    global $DB;
    
    $sql = "SELECT capabilityoverallgrade
            FROM {advwork_student_models}
            WHERE userid = :userid AND capabilityid = 1 AND domainvalueid = 1";
    $params = ['userid' => $userid];
    $records = $DB->get_records_sql($sql, $params);
    $k_value = reset($records)->capabilityoverallgrade;

    $sql = "SELECT capabilityoverallgrade
            FROM {advwork_student_models}
            WHERE userid = :userid AND capabilityid = 2 AND domainvalueid = 1";
    $params = ['userid' => $userid];
    $records = $DB->get_records_sql($sql, $params);
    $j_value = reset($records)->capabilityoverallgrade;

    echo " " . $k_value . " " . $j_value;

}

function get_capability_value($userid, $capid) {
    global $DB;

    $sql = "SELECT capabilityoverallvalue
            FROM {advwork_student_models}
            WHERE userid = :userid AND capabilityid = :capid AND domainvalueid = 1";
    $params = ['userid' => $userid, 'capid' => $capid];
    $records = $DB->get_records_sql($sql, $params);
    if (empty($records)) {return;}
    
    get_lig_assignment(reset($records)->capabilityoverallvalue);
}

function get_var_capability_value($userid, $capid) {
    global $DB;
    
    $sql = "SELECT capabilityoverallvalue
            FROM {advwork_student_models}
            WHERE userid = :userid AND capabilityid = 1 AND domainvalueid = 1";
    $params = ['userid' => $userid];
    $records = $DB->get_records_sql($sql, $params);
    $k_value = reset($records)->capabilityoverallvalue;

    $sql = "SELECT capabilityoverallvalue
            FROM {advwork_student_models}
            WHERE userid = :userid AND capabilityid = 2 AND domainvalueid = 1";
    $params = ['userid' => $userid];
    $records = $DB->get_records_sql($sql, $params);
    $j_value = reset($records)->capabilityoverallvalue;
    
    if ($capid == 4){
        # 3J:1K
        #echo " " .$k_value . " " . $j_value;
        $calc = ((3*$j_value + 1*$k_value)/4);
        get_lig_assignment($calc);
    }
    if ($capid == 5){
        # 2J:1K
        #echo " " .$k_value . " " . $j_value;
        $calc = ((2*$j_value + 1*$k_value)/3);
        get_lig_assignment($calc);
    }
}

function get_lig_assignment($capovval){
    if ($capovval>=0.95){
        echo " GOOD";
    }
    elseif($capovval>=0.75){
        echo " INTERMEDIATE";
    }
    else {
        echo " LOWER";
    }

    #for groups logic
    return $capovval;
}

function get_distinct_user_ids($courseid, $advworkid, $capid) {
    global $DB;

    $sql = "SELECT DISTINCT userid 
            FROM {advwork_student_models} 
            WHERE courseid = :courseid AND advworkid = :advworkid";

    $params = [
        'courseid' => $courseid,
        'advworkid' => $advworkid
    ];

    $userids = $DB->get_records_sql($sql, $params);

    $userid_array = [];

    if (!empty($userids)) {
        foreach ($userids as $userid) {
            $userid_array[] = $userid->userid;
            echo "<p> ID: " .$userid->userid;
            if ($capid == 4 or $capid == 5){
                echo get_var_capability_grades($userid->userid, $capid);
                echo get_var_capability_value($userid->userid, $capid);
            }
            else {
                echo get_capability_grades($userid->userid, $capid);
                echo get_capability_value($userid->userid, $capid);
            }
            echo "</p>";
        }
    }
}

#OUTPUT STARTS HERE

echo $output->header();
echo $output->heading(format_string('Simulated Students'));

?>

<div class="container INFO">
    <p>Course Name: <?php echo $course->fullname;?>, ID: <?php echo $courseid;?></p>
    <p>Module Name: <?php echo $advwork->name;?>, ID: <?php echo $advwork->id; ?></p>
</div>

<div class="container bg-light FORM">
    <p>
        <form action="simulationclass.php?id=<?php echo $id; ?>" method="POST">
            <div class="row d-flex align-items-center">
                <div class ="col-3">Edit Form Assessment and weights:</div>
                <div class ="col-9">
                    <div class="row"><div class="col"><p></br></p></div></div>
                    <div class="row">
                        <div class="col">Aspect 1 Desc: <input type="text" value = "SIMA1D" name="first_aspect_desc"></div>
                        <div class="col">Weight:
                            <select name="first_weight" class="form-select">
                                <option value="10">10%</option>
                                <option value="20">20%</option>
                                <option value="30">30%</option>
                                <option value="40">40%</option>
                                <option value="50">50%</option>
                                <option value="60">60%</option>
                                <option value="70">70%</option>
                                <option value="80">80%</option>
                                <option value="90">90%</option>
                                <option value="100">100%</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">Aspect 2 Desc: <input type="text" value = "SIMA2D" name="second_aspect_desc"></div>
                        <div class="col">Weight:
                            <select name="second_weight" class="form-select">
                                <option value="10">10%</option>
                                <option value="20">20%</option>
                                <option value="30">30%</option>
                                <option value="40">40%</option>
                                <option value="50">50%</option>
                                <option value="60">60%</option>
                                <option value="70">70%</option>
                                <option value="80">80%</option>
                                <option value="90">90%</option>
                                <option value="100">100%</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">Aspect 3 Desc: <input type="text" value = "SIMA3D" name="third_aspect_desc"></div>
                        <div class="col">Weight:
                            <select name="third_weight" class="form-select">
                                <option value="10">10%</option>
                                <option value="20">20%</option>
                                <option value="30">30%</option>
                                <option value="40">40%</option>
                                <option value="50">50%</option>
                                <option value="60">60%</option>
                                <option value="70">70%</option>
                                <option value="80">80%</option>
                                <option value="90">90%</option>
                                <option value="100">100%</option>
                            </select>
                        </div>
                    </div>
                    <div class="row"><div class="col"><p></br></p></div></div>
                    <div class="row">
                        <div class="col-4"></div>
                        <div class="col"><button type="submit" class="btn btn-primary" name="update_assessment_btn">Update Assessment Info</button></div>
                    </div>
                    <div class="row"><div class="col"><p></br></p></div></div>
                </div>
            </div>
        </form>
        <?php echo display_function_message($message_form); ?>
    </p>
</div>

<div class="container CREATE">
    <p>Total number of simulated students: <?php echo read_how_many_sim_students_already_exists('sim_student', false); ?></p>
    <p>
        <form action="simulationclass.php?id=<?php echo $id; ?>" method="POST">
            <div class="row d-flex align-items-center">
                <div class ="col-3">How many students to create:</div>
                <div class ="col-2"><input type="number" name="students_number_to_create"></div>
                <div class ="col"><button type="submit" class="btn btn-primary" name="create_students_btn">Create Simulated Students</button></div>
            </div>
        </form>
        <?php echo display_function_message($message_create); ?>
    </p>
</div>

<div class="container ENROLL">
    <p>How many simulated students enrolled on this course: <?php echo get_students_in_course($courseid, false); ?></p>
    <p>
        <form action="simulationclass.php?id=<?php echo $id; ?>" method="POST">
            <div class="row d-flex align-items-center">
                <div class ="col-3">How many students to enroll in total:</div>
                <div class ="col-2"><input type="number" name="students_number_to_enroll"></div>
                <div class ="col"><button type="submit" class="btn btn-primary" name="enroll_students_btn">Enroll Simulated Students</button></div>
            </div>
        </form>
        <?php echo display_function_message($message_enroll); ?>
    </p>
</div>

<div class="container SUBMISSIONS">
    <p>
        <form action="simulationclass.php?id=<?php echo $id; ?>" method="POST">
            <div class="row d-flex align-items-center">
                <div class ="col-5">Submissions number: <?php echo get_submissions($advwork->id, false);?> / <?php 
                    echo get_students_in_course($courseid, false); ?></div>
                <div class ="col"><button type="submit" class="btn btn-primary" name="create_submissions_btn">Create Simulated Submissions</button></div>
            </div>
        </form>
        <?php echo display_function_message($message_submission);?>
    </p>
</div>

<div class="container bg-light GROUPS">
    <p>
        <form action="simulationclass.php?id=<?php echo $id; ?>" method="POST">
            <div class="row"><div class="col"><p></br></p></div></div>
            <div class="row d-flex align-items-center">
                <div class ="col-3">
                    <div>Active groups: <?php echo count_groups_in_course($courseid);?></div>
                    <div>Group dimension:</div>
                    <div>Active grouping: <?php echo get_course_grouping_name($courseid); ?></div>
                </div>
                <div class ="col-2">
                    <div><p></p></div>
                    <div><input type="number" value = 4 name="group_size_to_create"></div>
                    <div><p></p></div>
                </div>
                <div class="col">
                    <div><button type="submit" class="btn btn-primary" name="create_groups_btn">Create Groups</button></br></div>
                    <div><p></p></div>
                    <div><button type="submit" class="btn btn-primary" name="create_grouping_btn">Create Grouping</button></div>
                </div>

            </div>
            <div class="row"><div class="col"><p></br></p></div></div>
        </form>
        <?php echo display_function_message($message_groups);?>
    </p>
</div>

<div class="container bg-light ALLOCATION">
    <p>
        <form action="simulationclass.php?id=<?php echo $id; ?>" method="POST">
            <div class="row"><div class="col"><p></br></p></div></div>
            <div class="row d-flex align-items-center">
                <div class ="col-3">
                    <div><p>Allocation:</p></div>
                    <div><p>Reviewers number:</p></div>
                    <div><p>Random Attempts before Sequential:</p></div>
                </div>
                <div class ="col-2">
                    <div><p></p></div>
                    <div><p><input type="number" value = 3 name="reviewers_size"></p></div>
                    <div><p><input type="number" value = 10 name="random_attempts"></p></div>
                    <div><p></p></div>
                </div>
                <div class="col">
                    <div><button type="submit" class="btn btn-primary" name="create_random_allocation_btn">Random Reviewers Allocation</button></br></div>
                    <div><p></p></div>
                    <div><button type="submit" class="btn btn-primary" name="create_sequential_allocation_btn">Sequential Reviewers Allocation</button></div>
                </div>
            </div>
            <div class="row"><div class="col"><p></br></p></div></div>
        </form>
        <?php echo display_function_message($message_allocation);?>
    </p>
</div>

<div class="container bg-light GRADES">
    <p>
        <form action="simulationclass.php?id=<?php echo $id; ?>" method="POST">
            <div class="row"><div class="col"><p></br></p></div></div>
            <div class="row d-flex align-items-center">
                <div class ="col-5">Grades Rules: </div>
                <div class ="col"><button type="submit" class="btn btn-primary" name="create_grades_random_btn">Random Grades</button></div>
                <div class ="col"><button type="submit" class="btn btn-primary" name="create_lig_students_btn">L.I.G. Students</button></div>
                <div class ="col"><button type="submit" class="btn btn-primary" name="create_lig_grades_btn">L.I.G. Grades</button></div>
            </div>
            <div class="row"><div class="col"><p></br></p></div></div>
        </form>
        <?php echo display_function_message($message_grades);?>
    </p>
</div>

<div class="container bg-light TEACHER_GRADES">
    <p>
        <form action="simulationclass.php?id=<?php echo $id; ?>" method="POST">
            <div class="row"><div class="col"><p></br></p></div></div>
            <div class="row d-flex align-items-center">
                <div class ="col-3">Automatic Teacher Grades number: </div>
                <div class ="col-2"><input type="number" value = 1 name="teacher_number"></div>
                <div class ="col"><button type="submit" class="btn btn-primary" name="teacher_evaluation">Evaluate</button></div>
            </div>
            <div class="row"><div class="col"><p></br></p></div></div>
        </form>
        <?php echo display_function_message($message_teacher);?>
    </p>
</div>

<div class="container bg-light STUD_MODELS">
    <p>
        <form action="simulationclass.php?id=<?php echo $id; ?>" method="POST">
            <div class="row"><div class="col"><p></br></p></div></div>
            <div class="row d-flex align-items-center">
                <div class ="col-3">BN Models </div>
                <div class ="col-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="bn_radio" value="1">
                        <label class="form-check-label" for="flexRadioDefault1">
                            Use Previous BN Models
                        </label>
                        </div>
                        <div class="form-check">
                        <input class="form-check-input" type="radio" name="bn_radio" value="2">
                        <label class="form-check-label" for="flexRadioDefault2">
                            Do not use Previous BN Models
                        </label>
                    </div>
                </div>
                <div class ="col"><button type="submit" class="btn btn-primary" name="update_bn_models">Update</button></div>
            </div>
            <div class="row"><div class="col"><p></br></p></div></div>
        </form>
        <?php echo display_function_message($message_teacher);?>
    </p>
</div>

<div class="container bg-light LIG">
    <div class="row"><div class="col"><p></br></p></div></div>
        <div class="row d-flex align-items-center">
            <div class ="col-3">
                <h3>K Skill</h3>
                <?php echo get_distinct_user_ids($courseid, $advwork->id,1); ?>
            </div>
            <div class ="col-3">
                <h3>J Skill</h3>
                <?php echo get_distinct_user_ids($courseid, $advwork->id,2); ?>
            </div>
            <div class ="col-3">
                <h3>3J|1K Skill</h3>
                <?php echo get_distinct_user_ids($courseid, $advwork->id,4); ?>
            </div>
            <div class ="col-3">
                <h3>2J|1K Skill</h3>
                <?php echo get_distinct_user_ids($courseid, $advwork->id,5); ?>
            </div>
        </div>
    <div class="row"><div class="col"><p></br></p></div></div>
</div>

<div class="container bg-light RETURN">
    <p>
    <div class="row d-flex align-items-center">
        <div class="col">
            <button type="button" class="btn btn-light" id=""><a href="view.php?id=<?php echo $id; ?>">Back to ADVWORKER: View</a></button>
        </div>
    </div>
    </p>
</div>

<?php 
$PAGE->requires->js_call_amd('mod_advwork/advworkview', 'init');
echo $output->footer();
?>
