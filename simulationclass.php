<?php
 // Start the session once at the beginning

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
 // Inizializza la sessione

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['classSelection'])) {
        $_SESSION['classSelection'] = $_POST['classSelection']; // Salva nella sessione
    }
}

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
        $randomMin = isset($_POST['randomMin']) ? (float)$_POST['randomMin'] : 0.0;
        $randomMax = isset($_POST['randomMax']) ? (float)$_POST['randomMax'] : 0.1;

        // Calcola il valore casuale
        $randomness;
        if ($randomMin < $randomMax) {
            $randomness = mt_rand($randomMin * 100, $randomMax * 100) / 100; // Genera un numero tra min e max
        } else {
            $randomness = $randomMin; // Fallback se i valori non sono validi
        }
        if ($m0Model=="flat"){
            if(isset($_POST['sessionSelection'])) {
                create_flat_allocation_on_that_class($peerNumber, $classSelection, true, $randomness);
            } else {
                create_flat_allocation_on_that_class($peerNumber, $classSelection, false, $randomness);
            }
        }

        # ottenuto il peer assessment relativo alla distribuzione si ottiene il modello m0

    }
    elseif (isset($_POST['teacher_settings_btn'])) {
        echo "Peer Number: Teacher<br>";
    }
}

function create_flat_allocation_on_that_class($peerNumber, $classSelection, $continue_session = true, $randomness) {
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

    // JSON da costruire
    $json_output = [
        "parameters" => [
            "strategy" => "maxEntropy",
            "termination" => "corrected30",
            "mapping" => "weightedSum",
            "domain" => [
                1,
                0.95,
                0.85,
                0.75,
                0.65,
                0.55,
                0
            ]
        ],
        "peer-assessments" => []
    ];

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
            $assigned_peers[$random_peer] = "0.00";

            // Incrementa il contatore di assegnazioni per questo peer
            $peer_assignment_count[$random_peer]++;

            // Rimuovi il peer selezionato dalla lista dei restanti
            $remain_students = array_filter($remain_students, function($userid) use ($random_peer) {
                return $userid !== $random_peer;
            });

            $attempts++;
        }

        // Aggiungi i peer assegnati al JSON
        $json_output["peer-assessments"][$current_userid] = $assigned_peers;
    }

    $filePath = "simulatedclass/$classSelection/";
    $fileName = "{$classSelection}_peerassessment.json";

    // Crea la directory se non esiste
    if (!file_exists($filePath)) {
        mkdir($filePath, 0777, true); // Crea la directory con permessi completi
    }

    // Salva il file JSON
    $json_string = json_encode($json_output, JSON_PRETTY_PRINT);
    file_put_contents($filePath . $fileName, $json_string);

    // Creazione del modello m0 
    send_data($filePath, $classSelection);

    //Assegnazione voti
    make_the_peer_assessment_session($classSelection, $randomness);

    // Creazione del modello M1
    send_data_for_model($filePath, $classSelection);
}

function make_the_peer_assessment_session($classSelection, $randomness) {
    
    // Percorso del file JSON
    $filePath = "simulatedclass/{$classSelection}/{$classSelection}_peerassessment.json";
    
    // Verifica se il file esiste
    if (!file_exists($filePath)) {
        throw new Exception("File $filePath non trovato.");
    }

    // Leggi il contenuto del file
    $jsonData = file_get_contents($filePath);
    $data = json_decode($jsonData, true);

    if (!$data || !isset($data['peer-assessments'])) {
        throw new Exception("Struttura JSON non valida nel file $filePath.");
    }

    // Itera su ogni ID nella sezione "peer-assessments"
    foreach ($data['peer-assessments'] as $userId => $assessments) {
        foreach ($assessments as $assignedId => $score) {
            // Calcola il voto utilizzando calcolate_score_k_by_j()
            $newScore = calcolate_score_k_by_j($userId, $assignedId, $classSelection, $randomness);
            
            // Aggiorna il voto nel JSON
            $data['peer-assessments'][$userId][$assignedId] = $newScore;
        }
    }
    // Scrivi i dati modificati nuovamente nel file JSON
    $updatedJsonData = json_encode($data, JSON_PRETTY_PRINT);
    
    // Salva nel file
    if (file_put_contents($filePath, $updatedJsonData) === false) {
        throw new Exception("Impossibile salvare il file $filePath.");
    }

    echo "Peer assessment sessione aggiornata con successo.\n";
}

function calcolate_score_k_by_j($userId, $assignedId, $classSelection, $randomness) {
    // Recupera i valori k_real e j_sim
    $k_real = get_k_real($userId, $classSelection);
    $j_sim = get_j_sim($assignedId, $classSelection);

    // Calcola un voto base come la media ponderata tra k_real e j_sim
    $base_score = ($k_real + $j_sim) / 2;

    // Calcola una piccola variazione casuale attorno al voto base
    $random_factor = 1 + (rand(-100, 100) / 10000) * $randomness;

    // Applica la variabilità al voto
    $score = $base_score * $random_factor;

    // Assicurati che il punteggio finale sia tra 0 e 1
    $score = max(0, min(1, $score));

    // Ritorna il voto finale
    return $score;
}


function get_k_real($userID, $classSelection) {
    // Percorso del file JSON
    $filePath = "simulatedclass/{$classSelection}/{$classSelection}_mr.json";

    // Verifica se il file esiste
    if (!file_exists($filePath)) {
        throw new Exception("File $filePath non trovato.");
    }

    // Leggi il contenuto del file
    $jsonData = file_get_contents($filePath);
    $data = json_decode($jsonData, true);

    if (!$data || !is_array($data)) {
        throw new Exception("Struttura JSON non valida nel file $filePath.");
    }

    // Cerca il valore di capabilityoverallvalue per l'utente specifico
    foreach ($data as $entry) {
        if (
            isset($entry['userid'], $entry['capabilityid'], $entry['domainvalueid'], $entry['capabilityoverallvalue']) &&
            $entry['userid'] == $userID &&
            $entry['capabilityid'] == "1" &&
            $entry['domainvalueid'] == "1"
        ) {
            return $entry['capabilityoverallvalue'];
        }
    }
}

function get_j_sim($userID, $classSelection) {
    // Percorso del file JSON
    $filePath = "simulatedclass/{$classSelection}/{$classSelection}_m0.json";

    // Verifica se il file esiste
    if (!file_exists($filePath)) {
        throw new Exception("File $filePath non trovato.");
    }

    // Leggi il contenuto del file
    $jsonData = file_get_contents($filePath);
    $data = json_decode($jsonData, true);

    // Debug per verificare la struttura del JSON
    if (!$data) {
        throw new Exception("Errore nel parsing del JSON: " . json_last_error_msg());
    }

    // Verifica la presenza della chiave 'student-models'
    if (!isset($data['student-models'])) {
        throw new Exception("Chiave 'student-models' non trovata nel file $filePath. Contenuto del JSON: " . json_encode($data));
    }

    // Verifica se l'utente specificato esiste
    if (!isset($data['student-models'][$userID])) {
        throw new Exception("Dati per l'utente con ID $userID non trovati.");
    }

    // Recupera i dati dello studente
    $studentData = $data['student-models'][$userID];

    // Verifica se il campo 'J' esiste per l'utente specificato
    if (isset($studentData['J']['value'])) {
        return $studentData['J']['value']; // Restituisce il valore di 'J'
    } else {
        throw new Exception("Valore 'J' non trovato per l'utente con ID $userID.");
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

function send_data($filePath, $classSelection) {
    $webservice = new WebServiceBN();

    // Costruisce il percorso completo del file JSON da leggere
    $jsonFilePath = $filePath . $classSelection . "_peerassessment.json";

    // Controlla se il file esiste
    if (!file_exists($jsonFilePath)) {
        throw new Exception("Il file $jsonFilePath non esiste.");
    }

    // Legge il contenuto del file JSON
    $jsonContent = file_get_contents($jsonFilePath);
    $sessiondata = json_decode($jsonContent, true);

    // Invia i dati al servizio web
    $studentmodelsjsonresponse = $webservice->post_session_data($sessiondata);

    // Costruisce il percorso per salvare la risposta
    $responseFilePath = $filePath . $classSelection;

    // Salva la risposta in due formati
    $output = var_export($studentmodelsjsonresponse, true);
    $output = trim($output, "'");
    file_put_contents($responseFilePath . "_m0.json", $output);
    file_put_contents($responseFilePath, json_encode($studentmodelsjsonresponse, JSON_PRETTY_PRINT));
}

function send_data_for_model($filePath, $classSelection) {
    $webservice = new WebServiceBN();

    // Costruisce il percorso completo del file JSON da leggere
    $jsonFilePath = $filePath . $classSelection . "_peerassessment.json";

    // Controlla se il file esiste
    if (!file_exists($jsonFilePath)) {
        throw new Exception("Il file $jsonFilePath non esiste.");
    }

    // Legge il contenuto del file JSON
    $jsonContent = file_get_contents($jsonFilePath);
    $sessiondata = json_decode($jsonContent, true);

    // Invia i dati al servizio web
    $studentmodelsjsonresponse = $webservice->post_session_data($sessiondata);

    // Trova il prossimo numero disponibile per il file _m#
    $nextModelNumber = get_next_model_number($filePath, $classSelection);

    // Costruisce il nome del file
    $responseFilePath = $filePath . $classSelection . "_m" . $nextModelNumber . ".json";

    // Salva la risposta
    $output = var_export($studentmodelsjsonresponse, true);
    $output = trim($output, "'");
    file_put_contents($responseFilePath, $output);

    echo "Risultato salvato correttamente in $responseFilePath.\n";
}

/**
 * Trova il prossimo numero disponibile per un file _m#.
 */
function get_next_model_number($filePath, $classSelection) {
    $modelNumber = 1;

    // Scansiona i file nella directory
    $files = scandir($filePath);

    // Cerca i file con il pattern $classSelection_m#
    foreach ($files as $file) {
        if (preg_match('/' . preg_quote($classSelection) . '_m(\d+)\.json$/', $file, $matches)) {
            $number = intval($matches[1]);
            if ($number >= $modelNumber) {
                $modelNumber = $number + 1; // Incrementa al numero successivo
            }
        }
    }

    return $modelNumber;
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
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-target="overview">Overview</a>
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
                
                <!-- Randomness -->
                <div class="mb-3">
                    <label class="form-label">Randomness</label>
                    <div class="row">
                        <div class="col">
                            <input type="number" class="form-control" id="randomMin" name="randomMin" value="0" step="0.01" required>
                            <small class="form-text">Minimum</small>
                        </div>
                        <div class="col">
                            <input type="number" class="form-control" id="randomMax" name="randomMax" value="0.1" step="0.01" required>
                            <small class="form-text">Maximum</small>
                        </div>
                    </div>
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
                    <!-- Randomness -->
                    <div class="mb-3">
                        <label class="form-label">Randomness</label>
                        <div class="row">
                            <div class="col">
                                <input type="number" class="form-control" id="randomMin" name="randomMin" value="0" step="0.01" required>
                                <small class="form-text">Minimum</small>
                            </div>
                            <div class="col">
                                <input type="number" class="form-control" id="randomMax" name="randomMax" value="0.1" step="0.01" required>
                                <small class="form-text">Maximum</small>
                            </div>
                        </div>
                    </div>
                </div>

                

                <!-- Grade Button -->
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary" name="teacher_settings">Grade</button>
                </div>
            </form>
        </section>

        <section id="overview" class="mb-5">
            <h2>Overview</h2>
            <?php
            

            
function getLargestMnFile($directory, $classSelection) {
    $files = scandir($directory);
    $largestN = 0;
    $fileMn = null;

    foreach ($files as $file) {
        if (preg_match('/' . preg_quote($classSelection) . '_m(\d+)\.json$/', $file, $matches)) {
            $n = (int)$matches[1];
            if ($n > $largestN) {
                $largestN = $n;
                $fileMn = "$directory/$file";
            }
        }
    }

    return $fileMn;
}


// Funzione per verificare l'esistenza dei file
            function isValidClass($classSelection) {
                $directory = "simulatedclass/$classSelection";
                $file_m0 = "$directory/{$classSelection}_m0.json";
                $file_mr = "$directory/{$classSelection}_mr.json";
                return file_exists($file_m0) && file_exists($file_mr);
            }

            // Crea un elenco delle classi valide
            $baseDirectory = "simulatedclass";
            $validClasses = [];
            if (is_dir($baseDirectory)) {
                $directories = scandir($baseDirectory);
                foreach ($directories as $dir) {
                    if ($dir !== "." && $dir !== ".." && isValidClass($dir)) {
                        $validClasses[] = $dir;
                    }
                }
            }

            // Mostra sempre il menu a tendina
            echo '<form method="post" action="simulationclass.php">';
            echo '<label for="classSelection" class="form-label">Session to see:</label>';
            echo '<select id="classSelection" name="classSelection" class="form-select mb-3">';
            foreach ($validClasses as $class) {
                // Seleziona automaticamente l'opzione corrispondente alla classe in sessione
                $selected = (isset($_SESSION['classSelection']) && $_SESSION['classSelection'] === $class) ? "selected" : "";
                echo "<option value='$class' $selected>$class</option>";
            }
            echo '</select>';
            echo '<button type="submit" class="btn btn-primary">Load Session</button>';
            echo '</form>';

            // Gestisci la selezione della classe
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['classSelection'])) {
                $_SESSION['classSelection'] = $_POST['classSelection']; // Salva la classe selezionata in sessione
            }

            // Mostra la tabella se c'è una classe selezionata in sessione
            if (isset($_SESSION['classSelection'])) {
                $classSelection = $_SESSION['classSelection'];
                $directory = "simulatedclass/$classSelection";
                $file_m0 = "$directory/{$classSelection}_m0.json";
                $file_mr = "$directory/{$classSelection}_mr.json";

                // Mostra un messaggio e i dati se i file esistono
                if (isValidClass($classSelection)) {
                    echo "<h3>Data for $classSelection</h3>";
                   
                    
    
// Funzione per ottenere UserID distinti da un file JSON
function getDistinctUserIds($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }

    $data = json_decode(file_get_contents($filePath), true);
    $userIds = [];

    if (is_array($data)) {
        foreach ($data as $entry) {
            if (isset($entry['UserID']) && !in_array($entry['UserID'], $userIds)) {
                $userIds[] = $entry['UserID'];
            }
        }
    }

    return $userIds;
}

// Ottieni gli UserID distinti dal file MR
$userIds = getDistinctUserIds($file_mr);

// Determina se esiste il file Mn
$file_mn = getLargestMnFile($directory, $classSelection);
$mnColumnExists = $file_mn !== null;

// Inizio della tabella
echo '<table class="table table-bordered">';
echo '<thead class="table-light">';
echo '<tr>';
echo '<th>Student ID</th>';
echo '<th>MR</th>';
echo '<th>M0</th>';
echo '<th>MPA</th>';
if ($mnColumnExists) {
    echo "<th>Mn</th>";
    echo "<th>Mn-MR</th>";
    echo "<th>Mn-MPA</th>";
}
echo '</tr>';
echo '</thead>';
echo '<tbody>';

// Popola la tabella con righe basate sugli UserID distinti
foreach ($userIds as $userId) {
    echo '<tr>';
    echo "<td>$userId</td>";
    echo '<td>-</td>'; // Placeholder per MR
    echo '<td>-</td>'; // Placeholder per M0
    echo '<td>-</td>'; // Placeholder per MPA
    if ($mnColumnExists) {
        echo '<td>-</td>'; // Placeholder per Mn
        echo '<td>-</td>'; // Placeholder per Mn-MR
        echo '<td>-</td>'; // Placeholder per Mn-MPA
    }
    echo '</tr>';
}
echo '</tbody>';
echo '</table>';


    // Cerca il file m#.json con il numero più grande
    $file_mn = getLargestMnFile($directory, $classSelection);
    if ($file_mn) {
        echo "<tr><td>Largest Mn File</td><td><a href='$file_mn'>Download</a></td></tr>";
    }
    
                    echo "<tr><td>M0 File</td><td><a href='$file_m0'>Download</a></td></tr>";
                    echo "<tr><td>MR File</td><td><a href='$file_mr'>Download</a></td></tr>";
                    echo '</tbody>';
                    echo '</table>';
                } else {
                    echo "<p class='text-danger'>Files not found in: $directory</p>";
                }
            }
            ?>
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
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                const target = this.getAttribute('data-target');
                document.querySelectorAll('section').forEach(section => {
                    section.classList.add('hidden');
                });
                document.getElementById(target).classList.remove('hidden');
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('class-settings').classList.remove('hidden');
        });
    
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
