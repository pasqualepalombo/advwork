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
 * Creazione della classe simulata
 *
 * @package    mod_advwork
 * @copyright  2024 Pasquale Palombo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

#Advwork Library
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');

#Moodle Library for creating/handling users
require_once($CFG->libdir . '/moodlelib.php'); // Include il user_create_user
require_once($CFG->dirroot . '/user/lib.php'); // Include la libreria utenti di Moodle.
require_once($CFG->libdir . '/datalib.php'); // Include le funzioni del database di Moodle.

use core_user; // Usa il namespace corretto per la classe core_user.

$id         = required_param('id', PARAM_INT); 
$w          = optional_param('w', 0, PARAM_INT);  // advwork instance ID
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
    $wid=$id;
} else {
    $advworkrecord = $DB->get_record('advwork', array('id' => $w), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $advworkrecord->course), '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('advwork', $advworkrecord->id, $course->id, false, MUST_EXIST);
    $wid=$w;
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

#SIM FUNCTIONS
$stud_num_to_create = "";
$important_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    # get how many students to create
    $stud_num_to_create = test_input($_POST["stud_num_to_create"]);
    
    #base students data
    $userdata = [
        'username' => 'sim_student',
        'password' => '123',
        'firstname' => 'SIM',
        'lastname' => 'STUDENT',
        'email' => 'student@sim.com'
    ];

    // Chiama la funzione per creare il nuovo utente.
    try {
        $new_user = create_custom_user($userdata);
        $important_message = "Utente creato con successo. ID utente: " . $new_user->id;
    } catch (Exception $e) {
        $important_message = "Errore nella creazione dell'utente: " . $e->getMessage();
    }
}

function test_input($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

function create_custom_user($userdata) {
    // Controlla che i dati necessari siano presenti.
    if (empty($userdata['username']) || empty($userdata['password']) || empty($userdata['email'])) {
        throw new Exception('Dati mancanti: username, password o email non sono presenti.');
    }

    // Creare un oggetto utente.
    $user = new stdClass();
    $user->username = $userdata['username'];
    $user->password = hash_internal_user_password($userdata['password']);  // Hash la password.
    $user->firstname = $userdata['firstname'];
    $user->lastname = $userdata['lastname'];
    $user->email = $userdata['email'];
    $user->confirmed = 1;  // Conferma l'utente.
    $user->mnethostid = $GLOBALS['CFG']->mnet_localhost_id; // ID host.
    $user->auth = 'manual';  // Metodo di autenticazione (manuale).
    $user->timecreated = time();
    $user->timemodified = time();

    // Creare l'utente utilizzando la funzione `user_create_user()`.
    $new_user = user_create_user($user);

    return $new_user;
}

function read_how_many_sim_students_already_exists($prefix = 'sim_student', $return_array = true){
    global $DB;

    $sql = "SELECT * FROM {user} WHERE username LIKE :prefix AND deleted = 0"; 
    $params = ['prefix' => $prefix . '%'];

    // Se si vuole restituire l'array con i risultati.
    if ($return_array) {
        // Esegui la query e ottieni i record.
        $students = $DB->get_records_sql($sql, $params);
        return $students;  // Restituisce un array di oggetti utente.
    } else {
        // Se si vuole solo il numero degli utenti che corrispondono.
        $sql_count = "SELECT COUNT(*) FROM {user} WHERE username LIKE :prefix AND deleted = 0";
        $count = $DB->count_records_sql($sql_count, $params);
        return $count;  // Restituisce il numero di studenti trovati.
    }
}

$num_students = read_how_many_sim_students_already_exists('sim_student', false);

#OUTPUT STARTS HERE

echo $output->header();
echo $output->heading(format_string('Simulated Students'));

?>
<div class="container">
  <p>Total number of simulated students: <?php echo $num_students; ?></p>
    <p>
        <form action="simulationclass.php?id=<?php echo $id; ?>" method="POST">
            How many students to create: <input type="number" name="stud_num_to_create">
            <input type="submit">
        </form>
        <span class="badge bg-warning"><?php echo $important_message;?></span>
    </p>
</div>
<p>Creazione di una classe virtuale con assessement automatico...</p>
<p>Creazione studenti: no</p>
<p>Impostazione dei gruppi per ADVWORK: no</p>
<p>Assegnazione dei gruppi: no</p>
<p>Simulazione degli assessment: no</p>
<button type="button" class="btn btn-light" id=""><a href="view.php?id=<?php echo $id; ?>">Back to ADVWORKER: View</a></button>