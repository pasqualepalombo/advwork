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
$message_model = '';
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
    if (isset($_POST['start_new_model'])) {
        start_new_model($courseid, $advwork->id);
    }
    elseif (isset($_POST['update_json_data'])) {
        read_update_models($courseid, $advwork->id);
    }
    elseif (isset($_POST['update_assessment_btn'])) {
        $values_array = [
            'first_aspect_desc' => $_POST['first_aspect_desc'],
            'first_weight' => intval($_POST['first_weight']),
            'second_aspect_desc' => $_POST['second_aspect_desc'],
            'second_weight' => intval($_POST['second_weight']),
            'third_aspect_desc' => $_POST['third_aspect_desc'],
            'third_weight' => intval($_POST['third_weight']),
            'sub_grades' => intval($_POST['sub_grades']),
            'asm_grades' => intval($_POST['asm_grades'])
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
    elseif (isset($_POST['create_fkj_lig_grades_btn'])) {
        process_fkj_grades($advwork->id, true);
    }
    elseif (isset($_POST['create_fkj_grades_btn'])) {
        process_fkj_grades($advwork->id, false);
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
function start_new_model($courseid, $advworkid) {
    global $DB;
    global $message_model;

    $students_array = array_keys(get_students_in_course($courseid, true));

    # Controlla che gli array forniti siano validi
    if (empty($courseid) || empty($advworkid) || empty($students_array)) {
        $message_model = 'Parametri forniti invalidi';
        throw new InvalidArgumentException("Parametri invalidi forniti alla funzione.");
    }

    # Definizione dei valori fissi (al momento è oscura la modellazione base che da la BN, devo chiedere a Sterbini)
    # Tutti questi dati sono i default della BN
    $probabilities = [0.05446, 0.16073, 0.14386, 0.11835, 0.12399, 0.39862];
    $capability_grades = ['E', 'E', 'E', 'E', 'E', 'E', 'C', 'C', 'C', 'C', 'C', 'C', 'F', 'F', 'F', 'F', 'F', 'F'];
    $capability_values = [0.57970, 0.57878, 0.29539]; # Valori predefiniti per ogni dominio
    $iscumulated = 0; # Fisso a 0

    $capability_ids = range(1, 6); # Da 1 a 6 (per ogni capacità)
    $domain_value_ids = range(1, 3); # Da 1 a 3 (per ogni dominio)

    $all_records = []; # Array per salvare tutti i dati per il JSON

    foreach ($students_array as $student_id) {
        $grade_index = 0; # Indice per le capabilityoverallgrade e probabilità

        # Loop per ogni capabilityid e domainvalueid
        foreach ($capability_ids as $capability_id) {
            foreach ($domain_value_ids as $domain_value_id) {
                # Prepara i dati per l'inserimento
                $record = new stdClass();
                $record->courseid = $courseid;
                $record->advworkid = $advworkid;
                $record->userid = $student_id;
                $record->capabilityid = $capability_id;
                $record->domainvalueid = $domain_value_id;
                $record->probability = $probabilities[$grade_index % count($probabilities)];
                $record->capabilityoverallgrade = $capability_grades[$grade_index];
                $record->capabilityoverallvalue = $capability_values[$domain_value_id - 1];
                $record->iscumulated = $iscumulated;

                # Inserisce la riga nel database
                $DB->insert_record('advwork_student_models', $record);
                
                # Aggiunge il record all'array per il JSON
                $all_records[] = $record;

                $grade_index++; # Incrementa l'indice per la prossima riga
            }
        }
    }

    # Salva i dati in un file JSON
    save_to_json_new_model($courseid, $advworkid, $all_records);

    $message_model = "Fornito un modello default e salvato in json";

    return true;
}

# Funzione per salvare i dati in un file JSON
function save_to_json_new_model($courseid, $advworkid, $records) {
    # Percorso del file JSON con m0 perchè è il modello base di partenza
    $filename = __DIR__ . "/jsonsimulation/student_models_course_{$courseid}_advwork_{$advworkid}_m0.json";

    # Codifica i dati in JSON
    $json_data = json_encode($records, JSON_PRETTY_PRINT);

    # Scrive i dati nel file
    if (file_put_contents($filename, $json_data) === false) {
        throw new Exception("Errore nel salvataggio del file JSON: $filename");
    }
}

function read_update_models($courseid, $advworkid) {
    global $DB;

    # Recupera i dati dalla tabella mdl_advwork_student_models
    $records = $DB->get_records('advwork_student_models', [
        'courseid' => $courseid,
        'advworkid' => $advworkid
    ]);

    if (empty($records)) {
        throw new Exception("Nessun record trovato per il corso $courseid e il lavoro avanzato $advworkid.");
    }

    # Rimuove il campo 'id' da ogni record
    foreach ($records as &$record) {
        unset($record->id);
    }
    
    # Chiama la funzione per salvare i dati in un file JSON
    save_to_json_update_model($courseid, $advworkid, $records);
}

function save_to_json_update_model($courseid, $advworkid, $records) {
    # Determina il percorso base dei file JSON
    $base_dir = __DIR__ . '/jsonsimulation'; # Cartella di destinazione per i file JSON

    # Crea la cartella se non esiste
    if (!is_dir($base_dir)) {
        if (!mkdir($base_dir, 0777, true) && !is_dir($base_dir)) {
            throw new Exception("Errore nella creazione della cartella $base_dir");
        }
    }

    $base_filename = "student_models_course_{$courseid}_advwork_{$advworkid}";

    # Controlla se esiste già un file che termina con "_mpa"
    $mpa_file = "$base_dir/{$base_filename}_mpa.json";
    if (!file_exists($mpa_file)) {
        # Salva il primo file come "_mpa"
        $filename = $mpa_file;
    } else {
        # Trova il numero incrementale più alto per i file successivi
        $existing_files = glob("$base_dir/{$base_filename}_m*.json");
        $max_number = 0;

        foreach ($existing_files as $file) {
            if (preg_match("/_m([0-9]+)\.json$/", $file, $matches)) {
                $max_number = max($max_number, (int)$matches[1]);
            }
        }

        # Incrementa il numero per il prossimo file
        $next_number = $max_number + 1;
        $filename = "$base_dir/{$base_filename}_m{$next_number}.json";
    }

    # Converte i dati in JSON
    $json_data = json_encode(array_values($records), JSON_PRETTY_PRINT);

    # Salva i dati nel file
    if (file_put_contents($filename, $json_data) === false) {
        throw new Exception("Errore nel salvataggio del file JSON: $filename");
    }

    echo "File JSON salvato: $filename\n";
}


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

    #impostazione con votazione 100 per submission e assessment
    $newGrade = intval($values['sub_grades']);
    $newGradingGrade = intval($values['asm_grades']);
    $recordsUpdated = $DB->execute(
        "UPDATE {advwork}
         SET grade = :grade, gradinggrade = :gradinggrade
         WHERE id = :advworkid",
        [
            'grade' => $newGrade,
            'gradinggrade' => $newGradingGrade,
            'advworkid' => $advworkid,
        ]
    );
    $message_form = "Assessment Form e Votazione aggiornati con successo";
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
        $content = '<p dir="ltr" style="text-align: left;">Submission_content_by_' .$student->username .'</p>';
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
    
    $submissions = get_submissions($advworkid);

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

function get_author_k_value($assessment) {
    global $DB;

    # Ottieni il valore di submissionid dall'oggetto assessment
    if (!isset($assessment->submissionid)) {
        throw new Exception("Errore: submissionid non trovato nell'oggetto assessment.");
    }
    $submissionid = $assessment->submissionid;

    # Cerca il valore di authorid dalla tabella mdl_advwork_submissions
    $authorid = $DB->get_field('advwork_submissions', 'authorid', ['id' => $submissionid], IGNORE_MISSING);
    if ($authorid === false) {
        throw new Exception("Errore: authorid non trovato per submissionid $submissionid.");
    }

    # Cerca il valore di capabilityoverallvalue nella tabella mdl_advwork_student_models
    $capabilityoverallvalue = $DB->get_field('advwork_student_models', 'capabilityoverallvalue', [
        'userid' => $authorid,
        'capabilityid' => 1,
        'domainvalueid' => 1
    ], IGNORE_MISSING);

    if ($capabilityoverallvalue === false) {
        throw new Exception("Errore: capabilityoverallvalue non trovato per authorid $authorid con capabilityid 1 e domainvalueid 1.");
    }

    return $capabilityoverallvalue;
}

function get_reviewer_j_value($assessment) {
    global $DB;

    # Ottieni il valore di reviewerid dall'oggetto assessment
    if (!isset($assessment->reviewerid)) {
        throw new Exception("Errore: reviewerid non trovato nell'oggetto assessment.");
    }
    $reviewerid = $assessment->reviewerid;

    # Cerca il valore di capabilityoverallvalue nella tabella mdl_advwork_student_models
    $capabilityoverallvalue = $DB->get_field('advwork_student_models', 'capabilityoverallvalue', [
        'userid' => $reviewerid,
        'capabilityid' => 2,
        'domainvalueid' => 1
    ], IGNORE_MISSING);

    if ($capabilityoverallvalue === false) {
        throw new Exception("Errore: capabilityoverallvalue non trovato per reviewerid $reviewerid con capabilityid 2 e domainvalueid 1.");
    }

    return $capabilityoverallvalue;
}

function count_assessments_with_same_submission($assessment) {
    global $DB;

    # Verifica che l'oggetto $assessment contenga il campo submissionid
    if (!isset($assessment->submissionid)) {
        throw new Exception("Errore: submissionid non trovato nell'oggetto assessment.");
    }

    # Estrai il submissionid dall'oggetto assessment
    $submissionid = $assessment->submissionid;

    # Conta gli assessments con lo stesso submissionid
    $count = $DB->count_records('advwork_assessments', ['submissionid' => $submissionid]);

    if ($count === false) {
        throw new Exception("Errore: impossibile contare gli assessments per submissionid $submissionid.");
    }

    return $count;
}

function calculate_score($k_author, $j_reviewer, $assessments_per_submission) {
    # Controllo dei parametri
    if ($k_author < 0 || $k_author > 1) {
        throw new InvalidArgumentException("k_author deve essere compreso tra 0 e 1");
    }
    if ($j_reviewer < 0 || $j_reviewer > 1) {
        throw new InvalidArgumentException("j_reviewer deve essere compreso tra 0 e 1");
    }
    if ($assessments_per_submission <= 0) {
        throw new InvalidArgumentException("assessments_per_submission deve essere maggiore di 0");
    }

    # Suddividi il range [0, 1] in fasce
    $thresholds = [];
    for ($i = 1; $i <= $assessments_per_submission; $i++) {
        $thresholds[] = $i / $assessments_per_submission;
    }

    # Determina la fascia di bravura in base a k_author
    $category = 0;
    foreach ($thresholds as $i => $threshold) {
        if ($k_author <= $threshold) {
            $category = $i;
            break;
        }
    }

    # Imposta le incertezze in base alla fascia
    $uncertainties = [0.35, 0.2, 0.1]; # Incertezze per fasce bassa, media e alta
    $uncertainty = $uncertainties[min($category, count($uncertainties) - 1)];

    # Calcola il voto base
    $base_score = ($k_author + $j_reviewer) / 2 * 100;

    # Applica l'incertezza
    $lower_bound = $base_score * (1 - $uncertainty);
    $upper_bound = $base_score * (1 + $uncertainty);
    $final_score = mt_rand($lower_bound * 100, $upper_bound * 100) / 100;

    return round($final_score, 2);
}

function get_reviewer_j_lig_value($assessment_per_submission, $ligc) {
    # Validazione dei parametri
    if ($assessment_per_submission <= 0) {
        throw new Exception("Il numero di reviewer deve essere maggiore di zero.");
    }
    if ($ligc < 1 || $ligc > $assessment_per_submission) {
        throw new Exception("Il contatore \$ligc deve essere compreso tra 1 e il numero di reviewer.");
    }

    # Calcolo degli intervalli
    $step = 1 / $assessment_per_submission; # Lunghezza di ogni intervallo
    $j_value = $step * $ligc; # Calcolo del valore stimato j

    return round($j_value, 2); # Restituisce il valore con due cifre decimali
}

function process_fkj_grades($advworkid, $lig = true) {
    global $DB;
    global $message_grades;
    
    $submissions = get_submissions($advworkid);
    $submission_ids = array_keys($submissions);
    
    list($in_sql, $params) = $DB->get_in_or_equal($submission_ids);
    $assessments = $DB->get_records_select('advwork_assessments', "submissionid $in_sql", $params);
    if (!$assessments) {$message_grades = 'Non ci sono assessment per questo advwork';return;}

    $aspects = $DB->get_records('advworkform_acc_mod', ['advworkid' => $advworkid]);
    if (!$aspects) {$message_grades = 'Assessment Form non configurato';return;}
    $aspect_ids = array_keys($aspects);
    
    $ligc = 1;

    foreach ($assessments as $assessment) {
        $sum_weighted_grades = 0;
        $sum_weights = 0;

        $assessments_per_submission = count_assessments_with_same_submission($assessment);

        foreach ($aspect_ids as $aspect_id) {
            $aspect = $aspects[$aspect_id];
            
            $j_reviewer = 0;
            $k_author = get_author_k_value($assessment);

            if($lig){
                $j_reviewer = get_reviewer_j_lig_value($assessments_per_submission, $ligc);
            }
            else{
                $j_reviewer = get_reviewer_j_value($assessment);
            }
            
            $grade = calculate_score($k_author, $j_reviewer, $assessments_per_submission);
            
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
            $final_grade = $final_grade * 10;
        } else {
            #evitare la divisione per zero
            $final_grade = 0;
        }

        $assessment->grade = $final_grade;
        $assessment->timemodified = time();

        $DB->update_record('advwork_assessments', $assessment);

        # Incrementa il contatore e riparti da 1 se necessario
        $ligc = ($ligc % $assessments_per_submission) + 1;
    }
}

function process_lig_students($advworkid) {
    global $DB;
    global $message_grades;

    $submissions = get_submissions($advworkid);

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

    $submissions = get_submissions($advworkid);

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
    #Se viene specificato di non toccare i modelli precedenti allora prende tutti i modelli 
    #salvati in mdl_advwork_student_models che sono presenti nel corso e setta il corseid a 9999
    #cosi da non prenderli in considerazione.
    
    #BUG il problema è che in realtà ogni modellazione sembra a se stante. Anche cancellando tutti 
    #i modelli precedenti, la votazione della BN non cambia di una virgola. Dunque è praticamente inutile
    #includere questa modifica per ora.

    #Not Selected
    if ($bn_radio==0){
        return;
    }
    #Use BN
    if ($bn_radio==1){
        #ottieni tutti gli studenti del corso
        #ottieni gli id degli studenti
        #aggiorna la tabella con advworkid=advworkid attuale
    }
    #Not Use BN
    if ($bn_radio==2){
        #ottieni tutti gli studenti del corso
        #ottieni gli id degli studenti
        #aggiorna la tabella con advworkid=9999 
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

# OVERVIEW FUNCTIONS
function check_if_json_data_exists($courseid, $advworkid, $return_count = false) {
    $directory = __DIR__ . '/jsonsimulation/';
    # Costruisce il pattern per i file
    $pattern = "student_models_course_{$courseid}_advwork_{$advworkid}_m*";
    # Usa glob per cercare i file corrispondenti al pattern
    $files = glob($directory . $pattern);
    
    # Se $return_count è true, restituisci il numero di file trovati
    if ($return_count) {return count($files);}
    # Altrimenti restituisci true se esiste almeno un file, altrimenti false
    return !empty($files);
}


function get_student_name($id) {
    global $DB;

    # Verifica se l'ID esiste nella tabella mdl_user
    $user = $DB->get_record('user', ['id' => $id], 'firstname, lastname');

    if (!$user) {
        throw new Exception("Studente con ID $id non trovato.");
    }

    return [
        'firstname' => $user->firstname,
        'lastname' => $user->lastname,
    ];
}

function get_k_j_from_specific_json_of_this_course_advwork($courseid, $advworkid, $post, $userid) {
    $base_dir = __DIR__ . '/jsonsimulation';
    $filename = "$base_dir/student_models_course_{$courseid}_advwork_{$advworkid}_{$post}.json";

    if (!file_exists($filename)) {
        throw new Exception("File JSON non trovato: $filename");
    }

    $json_data = file_get_contents($filename);
    $records = json_decode($json_data, true);

    if ($records === null) {
        throw new Exception("Errore nella decodifica del file JSON: $filename");
    }

    $k_value = null;
    $j_value = null;

    foreach ($records as $record) {
        # Verifica che l'userid corrisponda e il domainvalueid sia 1
        if ($record['userid'] == $userid && $record['domainvalueid'] == 1) {
            # Ottieni il valore di capabilityoverallvalue con capabilityid=1 per k_value
            if ($record['capabilityid'] == 1) {
                $k_value = $record['capabilityoverallvalue'];
            }
            # Ottieni il valore di capabilityoverallvalue con capabilityid=2 per j_value
            if ($record['capabilityid'] == 2) {
                $j_value = $record['capabilityoverallvalue'];
            }
        }
    }

    # Controlla che entrambi i valori siano stati trovati
    if ($k_value === null || $j_value === null) {
        throw new Exception("Valori non trovati per userid=$userid, courseid=$courseid, advworkid=$advworkid, post=$post");
    }

    return [
        'k_value' => $k_value,
        'j_value' => $j_value,
    ];
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
                <div class ="col"><h4>Edit Form Assessment, Weights, Submission Grades, Assessment Grades:</h4></div>
            </div>
            <div class="row d-flex align-items-center">
                <div class ="col">
                    <div class="row"><div class="col"><p></br></p></div></div>
                    <div class="row">
                        <div class="col-4">Aspect 1 Description: <input type="text" value = "SIMA1D" name="first_aspect_desc"></div>
                        <div class="col-4">Weight:
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
                        <div class="col-4">Submission Grades: 
                            <select name="sub_grades" class="form-select">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="40">40</option>
                                <option value="50">50</option>
                                <option value="60">60</option>
                                <option value="70">70</option>
                                <option value="80">80</option>
                                <option value="90">90</option>
                                <option value="100" selected >100</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-4">Aspect 2 Description: <input type="text" value = "SIMA2D" name="second_aspect_desc"></div>
                        <div class="col-4">Weight:
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
                        <div class="col-4">Assessment Grades:
                            <select name="asm_grades" class="form-select">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="40">40</option>
                                <option value="50">50</option>
                                <option value="60">60</option>
                                <option value="70">70</option>
                                <option value="80">80</option>
                                <option value="90">90</option>
                                <option value="100" selected>100</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-4">Aspect 3 Description: <input type="text" value = "SIMA3D" name="third_aspect_desc"></div>
                        <div class="col-4">Weight:
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
                    <div class="row text-center">
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
    <div class="row d-flex align-items-center">
        <div class ="col"><h4>Class Settings:</h4></div>
    </div>
    <p>Total number of simulated students: <?php echo read_how_many_sim_students_already_exists('sim_student', false); ?></p>
    <p>
        <form action="simulationclass.php?id=<?php echo $id; ?>" method="POST">
            <div class="row d-flex align-items-center">
                <div class ="col-3">How many students to create:</div>
                <div class ="col-2"><input type="number" value=4 name="students_number_to_create"></div>
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
                <div class ="col-2"><input type="number" value=4 name="students_number_to_enroll"></div>
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

<div class="container GROUPS">
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

<div class="container bg-light MODELS">
    <p>
        <form action="simulationclass.php?id=<?php echo $id; ?>" method="POST">
            <div class="row d-flex align-items-center">
                <div class ="col"><h4>BN Model preset:</h4></div>
            </div>
            <div class="row d-flex align-items-center">
                <div class ="col">
                    <div class="row"><div class="col"><p></br></p></div></div>
                    <div class="row text-center">
                        <div class="col"><button type="submit" class="btn btn-primary" name="start_new_model">Start a new model</button></div>
                        <div class="col"><button type="submit" class="btn btn-primary" name="update_json_data">Save new Json Data</button></div>
                    </div>
                    <div class="row"><div class="col"><p></br></p></div></div>
                </div>
            </div>
        </form>
        <?php echo display_function_message($message_model); ?>
    </p>
</div>

<div class="container ALLOCATION">
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
                <div class ="col"><h4>Assessment Grade Rule:</h4></div>
            </div>
            <div class="row d-flex align-items-center text-center">
                <div class ="col"><button type="submit" class="btn btn-primary" name="create_fkj_grades_btn">K(A) | J(B)</button></div>
                <div class ="col"><button type="submit" class="btn btn-primary" name="create_fkj_lig_grades_btn">K(A) | J(L.I.G.)</button></div>
            </div>
            <div class="row"><div class="col"><p></br></p></div></div>
        </form>
        <?php echo display_function_message($message_grades);?>
    </p>
</div>

<div class="container TEACHER_GRADES">
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

<?php 
    if (check_if_json_data_exists($courseid, $advwork->id)){
        $students_array = array_keys(get_students_in_course($courseid, true));
        $saved_models_number = check_if_json_data_exists($courseid, $advwork->id, true);
        # Inizio container
        echo '<div class="container bg-light OVERVIEW">
                <div class="row"><div class="col"><p></br></p></div></div>
                <div class="row d-flex align-items-center text-center">
                    <div class ="col"><h4>Overview:</h4></div>
                </div>
                <div class="row"><div class="col"><p></br></p></div></div>
                <div class="row d-flex align-items-center">
                    <div class="col"><h4>STUDENTS</h4></div>
                    <div class="col"><h4>M0</h4></div>
                    <div class="col"><h4>MPA</h4></div>';
        for ($i = 1; $i <= $saved_models_number - 2; $i++) {
            $post = "M{$i}";
            echo '<div class="col"><h4>'.$post .'</h4></div>';
        }
        echo '<div class="col"><h4>DISTANCE</h4></div>
                </div>';
        
        foreach ($students_array as $student) {
            $student_name = get_student_name($student);
            $k_j_values = get_k_j_from_specific_json_of_this_course_advwork($courseid, $advwork->id,"m0", $student);
            $k_j_initial_value = $k_j_values;
            echo '<div class="row d-flex align-items-center">';
            echo '<div class ="col">' .$student_name['firstname'] . ' ' .$student_name['lastname'] . '</div>';
            echo '<div class ="col">
                        <p>K: '.$k_j_values['k_value'] .'</p>
                        <p>J: '.$k_j_values['j_value'] .'</p>
                    </div>
                ';
            $k_j_values = get_k_j_from_specific_json_of_this_course_advwork($courseid, $advwork->id,"mpa", $student);
            echo '<div class ="col">
                        <p>K: '.$k_j_values['k_value'] .'</p>
                        <p>J: '.$k_j_values['j_value'] .'</p>
                    </div>
                ';
            for ($i = 1; $i <= $saved_models_number - 2; $i++) {
                $post = "m{$i}";
                $k_j_values = get_k_j_from_specific_json_of_this_course_advwork($courseid, $advwork->id, $post, $student);
                echo '<div class="col">
                            <p>K: ' . $k_j_values['k_value'] . '</p>
                            <p>J: ' . $k_j_values['j_value'] . '</p>
                        </div>';
            }
            $k_j_values['k_value']=$k_j_values['k_value']-$k_j_initial_value['k_value'];
            $k_j_values['j_value']=$k_j_values['j_value']-$k_j_initial_value['j_value'];
            echo '<div class="col">';
            if ($k_j_values['k_value']>=0){echo '<p style="color: green; font-weight: bold;">K: ' . $k_j_values['k_value'] . '</p>';}
            else {echo '<p style="color: red; font-weight: bold;">K: ' . $k_j_values['k_value'] . '</p>';}
            if ($k_j_values['j_value']>=0){echo '<p style="color: green; font-weight: bold;">K: ' . $k_j_values['j_value'] . '</p>';}
            else {echo '<p style="color: red; font-weight: bold;">K: ' . $k_j_values['j_value'] . '</p>';}
            echo '</div></div>';
        }
        
        # Fine container
        echo '</div></div>';
    }



    

?>


<div class="container bg-light RETURN">
    <p>
    <div class="row d-flex align-items-center">
        <div class="col text-center">
            <button type="button" class="btn btn-light" id=""><a href="view.php?id=<?php echo $id; ?>">Back to ADVWORKER: View</a></button>
        </div>
    </div>
    </p>
</div>

<?php 
$PAGE->requires->js_call_amd('mod_advwork/advworkview', 'init');
echo $output->footer();
?>
