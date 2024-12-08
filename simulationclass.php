<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
/**
 * Creazione della classe simulata
 *
 * @package    class_simulation
 * @copyright  2024 Pasquale Palombo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once (__DIR__.'/webserviceBN.php');

# FORM HANDLING
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['class_settings_btn'])) {
        $studentsNumber = $_POST['studentsNumber'];
        $distribution = $_POST['distribution'];
        if ($distribution=="gaussian"){
            $median = $_POST['median'];
            $standardeviation = $_POST['standardDeviation'];
            $skewness = $_POST['skewness'];
            create_new_class_gaussian($studentsNumber, $median, $standardeviation, $skewness);
        }
        if ($distribution=="random"){
        create_new_class_random($studentsNumber);
        }
    }
    elseif (isset($_POST['pas_settings_btn'])) {
        $m0Model = $_POST['m0Model'];
        $peerNumber = (int)$_POST['peerNumber'];
        $classSelection = $_POST['classSelection'];
        $sessionSelection;
        if (isset($_POST['sessionSelection'])) {
            $sessionSelection = $_POST['sessionSelection'];
        }

        if ($m0Model=="flat"){
            if(isset($_POST['sessionSelection'])) {
                create_flat_allocation_on_that_class($peerNumber, $classSelection, true);
            } else {
                create_flat_allocation_on_that_class($peerNumber, $classSelection, false);
            }
        }
    }
    elseif (isset($_POST['teacher_settings_btn'])) {
        echo "Peer Number: Teacher<br>";
    }
}

function create_flat_allocation_on_that_class($peerNumber, $classSelection, $continue_session = true) {
    if ($continue_session) {
        // TODO: Implementare la gestione della sessione
    }

    // Ottieni gli studenti ordinati usando la funzione già esistente
    $ordered_students = models_ordering_for_flat($classSelection);

    // Estrai gli 'userid' distinti
    $userids = [];
    foreach ($ordered_students as $key => $student_group) {
        // Cicliamo su ogni gruppo di studenti per estrarre gli 'userid'
        foreach ($student_group as $student) {
            if (!in_array($student['userid'], $userids)) {
                $userids[] = $student['userid'];
            }
        }
    }

    // Inizializza l'array per tracciare quante volte un 'userid' è stato scelto
    $peer_assignment_count = array_fill_keys($userids, 0);

    // Cicla per ogni userid
    foreach ($userids as $current_userid) {
        // Crea l'array $remain_student con tutti gli id tranne quello corrente
        $remain_students = array_filter($userids, function($userid) use ($current_userid) {
            return $userid !== $current_userid;
        });

        // Crea un array di peer assegnati per il current_userid
        $assigned_peers = [];
        
        // Limita la selezione dei peer per ogni userid
        $attempts = 0; // Contatore per evitare cicli infiniti
        while (count($assigned_peers) < $peerNumber && $attempts < 100) {
            // Seleziona un peer casuale tra i restanti che non ha ancora raggiunto il limite
            $available_peers = array_filter($remain_students, function($peer) use ($peer_assignment_count, $peerNumber) {
                return $peer_assignment_count[$peer] < $peerNumber;
            });

            // Se non ci sono peer disponibili, esci dal ciclo
            if (empty($available_peers)) {
                break;
            }

            // Scegli un peer casuale
            $random_peer = $available_peers[array_rand($available_peers)];

            // Aggiungi il peer alla lista di assegnazione per il current_userid
            $assigned_peers[$current_userid][$random_peer] = "0.00";

            // Incrementa il contatore di assegnazioni per questo peer
            $peer_assignment_count[$random_peer]++;

            // Rimuovi il peer selezionato dalla lista dei restanti
            $remain_students = array_filter($remain_students, function($userid) use ($random_peer) {
                return $userid !== $random_peer;
            });

            $attempts++;
        }

        // Stampa i peer assegnati per ogni current_userid
        echo "For User ID $current_userid:\n";
        foreach ($assigned_peers[$current_userid] as $peer_id => $value) {
            echo "Peer ID $peer_id: $value\n";
        }
        echo "--------------------------\n";
    }
}



function models_ordering_for_flat($classSelection) {
    // Costruisci il percorso del file JSON
    $filePath = "simulatedclass/$classSelection/{$classSelection}_mr.json";

    // Controlla se il file esiste
    if (!file_exists($filePath)) {
        echo "File not found: $filePath";
        return;
    }

    echo "File found: $filePath<br>";

    // Leggi il contenuto del file JSON
    $jsonData = file_get_contents($filePath);
    $data = json_decode($jsonData, true);

    // Controllo di validità del contenuto
    if (!$data || !is_array($data)) {
        echo "Invalid JSON data.";
        return;
    }

    // Raggruppa i dati per userid
    $groupedData = [];
    foreach ($data as $entry) {
        $userid = $entry['userid'];
        $groupedData[$userid][] = $entry;
    }

    // Estrai il valore J per ogni utente
    $userValues = [];
    foreach ($groupedData as $userid => $entries) {
        foreach ($entries as $entry) {
            if ($entry['domainvalueid'] == "1" && $entry['capabilityid'] == "2") {
                $userValues[$userid] = floatval($entry['capabilityoverallvalue']);
                break;
            }
        }
    }

    // Ordina gli utenti per il valore J
    asort($userValues);

    // Riorganizza i dati ordinati
    $orderedData = [];
    foreach (array_keys($userValues) as $userid) {
        $orderedData[$userid] = $groupedData[$userid];
    }

    // Puoi ora restituire $orderedData per ulteriore elaborazione
    return $orderedData;
}


function create_new_class_gaussian($studentsNumber, $median, $standardeviation, $skewness){
    # $median, $standarddeviation e $skewness ancora non vengono usati
    # courseid e advworkid sono solo per legarli ad un corso per comodita
    $data = [];
    $startUserId = 8;

    for ($i = 0; $i < $studentsNumber; $i++) {
        $userId = $startUserId + $i;

        // Generazione di K, J e C
        $k = generate_gaussian(0.5, 0.1); // Media 0.5, deviazione standard 0.1
        $j = generate_gaussian($k * 0.25, 0.05); // J al 25% di K
        $c = generate_gaussian($k, 0.05); // C vicino a K

        // Normalizza J e C nel range [0.3, 1]
        $j = max(0.3, min(1.0, $j));
        $c = max(0.3, min(1.0, $c));

        foreach ([1 => $k, 2 => $j, 3 => $c] as $capabilityId => $capabilityValue) {
            // Assegna il grade in base al valore di capabilityValue
            if ($capabilityValue >= 0.95) {
                $grade = "A";
            } elseif ($capabilityValue >= 0.85) {
                $grade = "B";
            } elseif ($capabilityValue >= 0.75) {
                $grade = "C";
            } elseif ($capabilityValue >= 0.65) {
                $grade = "D";
            } elseif ($capabilityValue >= 0.55) {
                $grade = "E";
            } else {
                $grade = "F";
            }
        
            $domainProbabilities = generate_normalized_probabilities(6);
        
            foreach ($domainProbabilities as $domainValueId => $probability) {
                $data[] = [
                    "userid" => (string)$userId,
                    "capabilityid" => (string)$capabilityId,
                    "domainvalueid" => (string)($domainValueId + 1),
                    "probability" => number_format($probability, 5),
                    "capabilityoverallgrade" => $grade,  // Assegna il grade calcolato
                    "capabilityoverallvalue" => number_format($capabilityValue, 5),
                    "iscumulated" => "0"
                ];
            }
        }
        
    }

    // Salvataggio in un file JSON
    create_new_class('gaussian',$data);
}

function generate_gaussian($mean, $stdDev) {
    // Generazione di un valore gaussiano usando il metodo di Box-Muller
    $u1 = mt_rand() / mt_getrandmax();
    $u2 = mt_rand() / mt_getrandmax();
    return $mean + $stdDev * sqrt(-2 * log($u1)) * cos(2 * pi() * $u2);
}

function create_new_class_random($studentsNumber) {
    $data = [];
    $idCounter = 1;
    $startUserId = 8;

    for ($i = 0; $i < $studentsNumber; $i++) {
        $userId = $startUserId + $i;

        // Generazione di K, J e C con valori casuali
        $k = round(mt_rand() / mt_getrandmax(), 5);  // Valore casuale tra 0 e 1
        $j = round($k * 0.25 + mt_rand() / mt_getrandmax() * 0.1 - 0.05, 5); // J al 25% di K con piccole fluttuazioni
        $c = round($k + mt_rand() / mt_getrandmax() * 0.1 - 0.05, 5);  // C vicino a K con piccole fluttuazioni

        // Normalizza J e C nel range [0.3, 1]
        $j = max(0.3, min(1.0, $j));
        $c = max(0.3, min(1.0, $c));

        foreach ([1 => $k, 2 => $j, 3 => $c] as $capabilityId => $capabilityValue) {
            $domainProbabilities = generate_normalized_probabilities(6);

            foreach ($domainProbabilities as $domainValueId => $probability) {
                $data[] = [
                    "id" => $idCounter++,
                    "courseid" => "21",
                    "advworkid" => "3",
                    "userid" => (string)$userId,
                    "capabilityid" => (string)$capabilityId,
                    "domainvalueid" => (string)($domainValueId + 1),
                    "probability" => number_format($probability, 5),
                    "capabilityoverallgrade" => ($capabilityId === 3 ? "B" : "C"),
                    "capabilityoverallvalue" => number_format($capabilityValue, 5),
                    "iscumulated" => "0"
                ];
            }
        }
    }

    // Salvataggio in un file JSON
    create_new_class('random',$data);
}

function generate_normalized_probabilities($count) {
    $values = [];
    for ($i = 0; $i < $count; $i++) {
        $values[] = mt_rand() / mt_getrandmax();
    }
    $sum = array_sum($values);
    return array_map(function($value) use ($sum) {
        return $value / $sum;
    }, $values);
}

function create_new_class($distribution, $data) {
    // Inizializza il contatore per il numero incrementale
    $counter = 1;

    // Crea il prefisso della cartella utilizzando la stringa $distribution e il contatore
    $baseFolderName = "simulatedclass/{$distribution}_class_{$counter}";

    // Verifica se la cartella esiste già
    while (is_dir($baseFolderName)) {
        $counter++;  // Incrementa il contatore
        $baseFolderName = "simulatedclass/{$distribution}_class_{$counter}";  // Crea un nuovo nome per la cartella
    }

    // Crea la cartella con il nome corretto
    mkdir($baseFolderName, 0777, true);  // Aggiunto il flag 'true' per creare anche le cartelle superiori se necessario

    // Definisci il percorso del file JSON
    $jsonFile = $baseFolderName . "/" . $distribution . "_class_{$counter}_mr.json";

    // Scrivi il file JSON nella cartella appena creata
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

    // Restituisce il nome della cartella creata
    return $baseFolderName;
}

# PEERASESSMENTSESSION
function generate_class_options() {
    $folder_path = 'simulatedclass';
    $directories = array_filter(glob($folder_path . '/*'), 'is_dir');
    $classes = [];

    foreach ($directories as $dir) {
        if (preg_match('/^(.*)_class_(\d+)$/', basename($dir), $matches)) {
            $classes[] = [
                'prefix' => $matches[1],
                'id' => $matches[2]
            ];
        }
    }

    if (empty($classes)) {
        echo '<option value="no_class">no class available</option>';
    } else {
        foreach ($classes as $class) {
            echo '<option value="' . $class['prefix'] . '_class_' . $class['id'] . '">' . $class['prefix'] . '_class_' . $class['id'] . '</option>';
        }
    }
}



# DEBUG
function write_log($message, $log_file = 'logfile.log') {
    // Apre il file di log in modalità append (aggiunge alla fine del file)
    $file = fopen($log_file, 'a');
    
    // Verifica se il file è stato aperto correttamente
    if ($file) {
        // Ottiene la data e l'ora attuali
        $timestamp = date('Y-m-d H:i:s');
        
        // Scrive il messaggio nel file di log con il timestamp
        fwrite($file, "[$timestamp] $message\n");
        
        // Chiude il file dopo aver scritto
        fclose($file);
    } else {
        // Se non riesce ad aprire il file, stampa un errore
        echo "Impossibile aprire il file di log.";
    }
}

function check_directories() {
    // Nome della cartella da verificare
    $directory = 'simulatedclass';

    // Verifica se la cartella esiste
    if (!is_dir($directory)) {
        // Se la cartella non esiste, la crea
        mkdir($directory);
    }
}

function send_data() {
    $webservice = new WebServiceBN();
    $jsonFile = 'data.json';
    $jsonContent = file_get_contents($jsonFile);
    $sessiondata = json_decode($jsonContent, true);
    $studentmodelsjsonresponse = $webservice->post_session_data($sessiondata);
    $output = var_export($studentmodelsjsonresponse, true);
        file_put_contents('simulation_response.txt', $output);
        file_put_contents('simulation_response.json', json_encode($studentmodelsjsonresponse, JSON_PRETTY_PRINT));

}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Class Simulation">
    <meta name="author" content="Pasquale Palombo">
    <title>Class Simulation</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <style>
        /* Per la funzionalità di far apparire/sparire la sezione */
        .hidden {
            display: none;
        }
        /* Layout per il footer fisso */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        /* Fa sì che il contenuto principale si espanda */
        main {
            flex: 1; 
        }
        /* Impostazione standard */
        footer {
            position: relative;
        }
    </style>
</head>

<body>
    <?php check_directories(); ?>
    <!-- Hero Section -->
    <header class="bg-primary text-white text-center py-5">
        <div class="container">
            <a href="http://localhost/mod/advwork/simulationclass.php" class="text-white text-decoration-none">
                <h1 class="display-4">Class Simulation for Massive Online Open Courses</h1>
            </a>
        </div>
    </header>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" data-target="class-settings">Class Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-target="peer-assessment">Peer Assessment</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-target="teacher-settings">Teacher Settings</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    

    <!-- Main Content -->
    <main class="container my-5">
    <section id="class-settings" class="mb-5">
        <h2>Class Settings</h2>
        <form action="http://localhost/mod/advwork/simulationclass.php" method="POST">
            <!-- Students Number -->
            <div class="mb-3">
                <label for="studentsNumber" class="form-label">Students Number</label>
                <input type="number" class="form-control" id="studentsNumber" name="studentsNumber" value="12" required>
            </div>

            <!-- Distribution -->
            <div class="mb-3">
                <label for="distribution" class="form-label">Distribution</label>
                <select class="form-select" id="distribution" name="distribution" onchange="updateFormFields()">
                    <option value="gaussian">Normal distribution</option>
                    <option value="random">Random distribution</option>
                    <option value="json">From json file</option>
                </select>
            </div>

            <!-- Fields for Normal Distribution -->
            <div id="normal-fields" style="display: none;">
                <label for="median" class="form-label">Median</label>
                <input type="number" class="form-control" id="median" name="median" value="0">
                <label for="stdDeviation" class="form-label">Standard Deviation</label>
                <input type="number" class="form-control" id="standard-deviation" name="standardDeviation" value="0">
                <label for="skewness" class="form-label">Skewness</label>
                <input type="number" class="form-control" id="skewness" name="skewness" value="0">
            </div>

            <!-- Fields for JSON File -->
            <div id="json-fields" style="display: none;">
                <label for="fileUpload" class="form-label">Choose a file to upload:</label>
                <input type="file" id="fileUpload" name="fileUpload" class="form-control mb-3">
            </div>

            <!-- Submit Button -->
            <div class="mb-3">
                <button type="submit" class="btn btn-primary" name="class_settings_btn">Create Class</button>
            </div>
        </form>
    </section>

        <section id="peer-assessment" class="mb-5">
            <h2>Peer Assessment Session Settings</h2>
            <form action="http://localhost/mod/advwork/simulationclass.php" method="POST">
                <!-- Class Selection -->
                <div class="mb-3">
                    <label for="classSelection" class="form-label">Class Selection</label>
                    <select class="form-select" id="classSelection" name="classSelection" onchange="checkSessionFiles()">
                        <?php generate_class_options(); ?>
                    </select>
                </div>
                <div id="sessionFiles"></div>
                <div id="sessionSelectionContainer" style="display: none;" class="mb-3">
                    <label for="sessionSelection" class="form-label">Continue on which session?</label>
                    <select class="form-select" id="sessionSelection" name="sessionSelection">
                        <!-- Popolato dinamicamente -->
                    </select>
                </div>

                <!-- Chose M0 Model -->
                <div class="mb-3">
                    <label for="m0Model" class="form-label">Choose M0 Model</label>
                    <select class="form-select" id="m0Model" name="m0Model">
                        <option value="flat">Flat</option>
                    </select>
                </div>

                <!-- Peer Number -->
                <div class="mb-3">
                    <label for="peerNumber" class="form-label">Peer Number</label>
                    <input type="number" class="form-control" id="peerNumber" name="peerNumber" value="3" required>
                </div>

                <!-- Process PA Button -->
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary" name="pas_settings_btn">Process PA</button>
                </div>
            </form>
        </section>

        <section id="teacher-settings" class="mb-5">
            <h2>Teacher Settings</h2>
            <form action="http://localhost/mod/advwork/simulationclass.php" method="POST">
                <!-- Grade On -->
                <div class="mb-3">
                    <label class="form-label">Grade on:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="gradeRandom" name="gradeOptions[]" value="random" checked>
                        <label class="form-check-label" for="gradeRandom">Random</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="gradeSuitable" name="gradeOptions[]" value="most_suitable">
                        <label class="form-check-label" for="gradeSuitable">Most Suitable</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="gradeSubmission" name="gradeOptions[]" value="submission">
                        <label class="form-check-label" for="gradeSubmission">
                            Submission:
                            <select class="form-select d-inline-block w-auto ms-2" name="submissionOption">
                                <option value="submission_1">Submission 1</option>
                            </select>
                        </label>
                    </div>
                </div>

                <!-- Randomness -->
                <div class="mb-3">
                    <label for="randomnessSlider" class="form-label">Randomness (0,0.2)</label>
                    <input type="range" class="form-range" id="randomnessSlider" name="randomness" min="0" max="0.2" step="0.01" value="0.1">
                </div>

                <!-- Grade Button -->
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary" name="teacher_settings">Grade</button>
                </div>
            </form>
        </section>

        <section id="overview" class="mb-5">
            <h2>Overview</h2>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>MR</th>
                        <th>M0</th>
                        <th>MPA</th>
                        <th>M1</th>
                        <th>M1-MR</th>
                        <th>M1-M0</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Row 1 -->
                    <tr>
                        <td>John Doe</td>
                        <td>K: 0.85<br>J: 0.12</td>
                        <td>K: 0.44<br>J: 0.56</td>
                        <td>K: 0.76<br>J: 0.33</td>
                        <td>K: 0.67<br>J: 0.45</td>
                        <td class="fw-bold">K: 0.21<br>J: 0.15</td>
                        <td class="fw-bold">K: 0.39<br>J: 0.27</td>
                    </tr>
                    <!-- Row 2 -->
                    <tr>
                        <td>Jane Smith</td>
                        <td>K: 0.90<br>J: 0.43</td>
                        <td>K: 0.62<br>J: 0.18</td>
                        <td>K: 0.72<br>J: 0.29</td>
                        <td>K: 0.88<br>J: 0.31</td>
                        <td class="fw-bold">K: 0.13<br>J: 0.05</td>
                        <td class="fw-bold">K: 0.22<br>J: 0.11</td>
                    </tr>
                    <!-- Row 3 -->
                    <tr>
                        <td>Emma Brown</td>
                        <td>K: 0.65<br>J: 0.34</td>
                        <td>K: 0.59<br>J: 0.49</td>
                        <td>K: 0.74<br>J: 0.22</td>
                        <td>K: 0.81<br>J: 0.26</td>
                        <td class="fw-bold">K: 0.16<br>J: 0.08</td>
                        <td class="fw-bold">K: 0.42<br>J: 0.10</td>
                    </tr>
                    <!-- Additional rows -->
                    <tr>
                        <td>Chris Johnson</td>
                        <td>K: 0.89<br>J: 0.32</td>
                        <td>K: 0.45<br>J: 0.54</td>
                        <td>K: 0.68<br>J: 0.25</td>
                        <td>K: 0.79<br>J: 0.15</td>
                        <td class="fw-bold">K: 0.23<br>J: 0.11</td>
                        <td class="fw-bold">K: 0.36<br>J: 0.09</td>
                    </tr>
                    <tr>
                        <td>Sophia Davis</td>
                        <td>K: 0.71<br>J: 0.27</td>
                        <td>K: 0.39<br>J: 0.62</td>
                        <td>K: 0.84<br>J: 0.47</td>
                        <td>K: 0.66<br>J: 0.29</td>
                        <td class="fw-bold">K: 0.14<br>J: 0.07</td>
                        <td class="fw-bold">K: 0.51<br>J: 0.20</td>
                    </tr>
                    <tr>
                        <td>Daniel Wilson</td>
                        <td>K: 0.83<br>J: 0.23</td>
                        <td>K: 0.51<br>J: 0.33</td>
                        <td>K: 0.79<br>J: 0.44</td>
                        <td>K: 0.61<br>J: 0.30</td>
                        <td class="fw-bold">K: 0.19<br>J: 0.12</td>
                        <td class="fw-bold">K: 0.27<br>J: 0.13</td>
                    </tr>
                    <tr>
                        <td>Olivia Martinez</td>
                        <td>K: 0.75<br>J: 0.31</td>
                        <td>K: 0.58<br>J: 0.40</td>
                        <td>K: 0.70<br>J: 0.22</td>
                        <td>K: 0.85<br>J: 0.34</td>
                        <td class="fw-bold">K: 0.15<br>J: 0.08</td>
                        <td class="fw-bold">K: 0.29<br>J: 0.17</td>
                    </tr>
                    <tr>
                        <td>Lucas White</td>
                        <td>K: 0.92<br>J: 0.41</td>
                        <td>K: 0.47<br>J: 0.50</td>
                        <td>K: 0.65<br>J: 0.18</td>
                        <td>K: 0.80<br>J: 0.23</td>
                        <td class="fw-bold">K: 0.17<br>J: 0.10</td>
                        <td class="fw-bold">K: 0.34<br>J: 0.21</td>
                    </tr>
                    <tr>
                        <td>Mia Lee</td>
                        <td>K: 0.88<br>J: 0.36</td>
                        <td>K: 0.49<br>J: 0.28</td>
                        <td>K: 0.72<br>J: 0.31</td>
                        <td>K: 0.69<br>J: 0.27</td>
                        <td class="fw-bold">K: 0.18<br>J: 0.05</td>
                        <td class="fw-bold">K: 0.24<br>J: 0.14</td>
                    </tr>
                    <tr>
                        <td>Ella King</td>
                        <td>K: 0.78<br>J: 0.30</td>
                        <td>K: 0.55<br>J: 0.47</td>
                        <td>K: 0.80<br>J: 0.26</td>
                        <td>K: 0.74<br>J: 0.32</td>
                        <td class="fw-bold">K: 0.20<br>J: 0.12</td>
                        <td class="fw-bold">K: 0.37<br>J: 0.16</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4">
        <div class="container">
            <p>Tesi @<a href="https://github.com/pasqualepalombo/advwork" class="text-white me-2">Github</a></p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript -->
    <script>
        // Funzione per mostrare solo la sezione selezionata
        function showSection(targetId) {
            // Nascondi tutte le sezioni
            document.querySelectorAll('section').forEach(section => {
                section.classList.add('hidden');
            });

            // Mostra la sezione corrispondente
            document.getElementById(targetId).classList.remove('hidden');

            // Aggiorna la classe attiva sulla navbar
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });

            // Aggiungi la classe attiva al link cliccato
            document.querySelector(`.nav-link[data-target="${targetId}"]`).classList.add('active');
        }

        // Imposta l'evento di clic sui link della navbar
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function (event) {
                event.preventDefault(); // Evita il comportamento predefinito del link
                const target = this.getAttribute('data-target'); // Ottieni l'id target
                showSection(target); // Mostra la sezione selezionata
            });
        });

        // Mostra la sezione iniziale (class-settings) al caricamento della pagina
        document.addEventListener('DOMContentLoaded', () => {
            showSection('class-settings');
        });

        function updateFormFields() {
            const distribution = document.getElementById("distribution").value;
            const normalFields = document.getElementById("normal-fields");
            const jsonFields = document.getElementById("json-fields");

            // Reset visibility
            normalFields.style.display = "none";
            jsonFields.style.display = "none";

            // Show appropriate fields based on selection
            if (distribution === "gaussian") {
                normalFields.style.display = "block";
            } else if (distribution === "json") {
                jsonFields.style.display = "block";
            }
        }

        // Initialize form visibility on page load
        document.addEventListener("DOMContentLoaded", updateFormFields);

        function checkSessionFiles() {
            const selectedClass = document.getElementById('classSelection').value;
            const sessionContainer = document.getElementById('sessionSelectionContainer');
            const sessionDropdown = document.getElementById('sessionSelection');
            const sessionFilesDiv = document.getElementById('sessionFiles');

            if (selectedClass === "no_class") {
                sessionContainer.style.display = "none";
                sessionFilesDiv.innerText = "No class selected.";
                return;
            }

            fetch(`check_sessions.php?class=${encodeURIComponent(selectedClass)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.sessions && data.sessions.length > 0) {
                        sessionFilesDiv.innerHTML = "Sessions found.";
                        sessionDropdown.innerHTML = ""; // Svuota eventuali opzioni precedenti

                        // Aggiungi l'opzione "Create a new session"
                        const createOption = document.createElement('option');
                        createOption.value = "create_new";
                        createOption.textContent = "Create a new session";
                        sessionDropdown.appendChild(createOption);

                        // Aggiungi le altre sessioni trovate
                        data.sessions.forEach(session => {
                            const option = document.createElement('option');
                            option.value = session;
                            option.textContent = session;
                            sessionDropdown.appendChild(option);
                        });

                        sessionContainer.style.display = "block";
                    } else {
                        sessionFilesDiv.innerHTML = `No session files found for ${selectedClass}. It will create a new one.`;
                        sessionContainer.style.display = "none";
                    }
                })
                .catch(error => {
                    sessionFilesDiv.innerText = "Error checking files.";
                    sessionContainer.style.display = "none";
                    console.error("Error:", error);
                });
        }


    </script>
</body>

</html>
