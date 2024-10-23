<!DOCTYPE html>
<?php
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');

$csvfiles = glob($_SESSION['csvhistorydirectory'] . '/*.csv');

//if there are csv files it will show the table 
if($csvfiles){
	$numcsvhistoryfiles=count($csvfiles);

	// if it's pressed the download zip files button
	if (isset($_POST['download_zip'])) {
    
    $zipFileName = $_SESSION['csvhistorydirectory'].'.zip'; // directory of csv files
    // creation of a new zip archive and open
    $zip = new ZipArchive();
    if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        // open the directory, read the csv files and add it to the zip
        $files = scandir($_SESSION['csvhistorydirectory']);
        foreach ($files as $file) {
            $filePath = $_SESSION['csvhistorydirectory'] . '/' . $file;
            if (is_file($filePath) && pathinfo($file, PATHINFO_EXTENSION) == 'csv') {
                $zip->addFile($filePath, $file);
            }
        }
        $zip->close();

        // setting headers for the download of the zip file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
        header('Content-Length: ' . filesize($zipFileName));

        // read the zipfile and send it to the browser
        readfile($zipFileName);

        // delete the zip file from the server
        unlink($zipFileName);
    } else {
        echo 'Errore nella creazione del file ZIP.';
    }
	}
	$i=0;
	$csvmaxold=0;

	// inserting the csv file's name in $ordine, to be sorted based on the first number of names
	foreach ($csvfiles as $csvfile) {
		// get only the name of the file without path
		$filenamecsv = pathinfo(basename($csvfile), PATHINFO_FILENAME);
		$ordine[$i]=$filenamecsv;
		$i++;
	}





	// function to order strings based on the number they have before the "-". it's for the csv files
	function confronto($a, $b) {
		// estract the number from every string and convert it into int
		$numeroA = intval(explode("-", $a)[0]);
		$numeroB = intval(explode("-", $b)[0]);

		// comparing numbers and return the result
		if ($numeroA == $numeroB) {
			return 0;
		}

		return ($numeroA < $numeroB) ? -1 : 1;
	}

	// sort the string array using the custom compare function "confronto"
	usort($ordine, "confronto");

	//$_GET['pag'] is useful to manage the number of the file csv we want to see. saved on $pag
	if(isset($_GET['pag'])){
		if($_GET['pag'] < $numcsvhistoryfiles && $_GET['pag'] >= 0){
			$pag=$_GET['pag'];
		}else{
			$pag=$numcsvhistoryfiles-1;
		}
	}else{
		$pag=$numcsvhistoryfiles-1;
	}

	if($pag > 0){
		$pagprec=$pag-1;
	}else{
		$pagprec=$pag;
	}

	if($pag < $numcsvhistoryfiles-1){
		$pagnext=$pag+1;
	}else{
		$pagnext=$pag;
	}
	?>

	<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>File csv del corso</title>
		<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
	</head>

	<body>
	<?php
	// setting csv file's directory
	$csvFile = $_SESSION['csvhistorydirectory']."/".$ordine[$pag].".csv";
	?>
	<div style='background-color: #343a40; display: flex; color: yellow'>
		<div style='width:100%;'>
			<form style='margin-left: 1%;' action="" method="get">
				<label for="pag"  class='h3'>Inserisci il numero del voto inserito (contando da 0 in poi) per avere la sua tabella associata oppure utilizza le frecce di fianco al nome del file qui sulla destra (l'ultimo voto inserito è il numero <?php echo $numcsvhistoryfiles-1?>)</label>
				<div style="display:flex">
					<input type="number" min="0" max="<?php echo $numcsvhistoryfiles-1?>" class='input-group-text' id="pag" name="pag">
					<input style='margin-left: 1%;' type="submit" value="Invia" class='btn btn-primary'>
				</div>
			</form>
		</div>

		<div style='width:100%; text-align:center;'>
			<h1><a href='<?php echo htmlspecialchars($_SERVER['PHP_SELF'])?>?pag=<?php echo "$pagprec";?>'><</a><?php echo "$ordine[$pag]";?><a href='<?php echo htmlspecialchars($_SERVER['PHP_SELF'])?>?pag=<?php echo "$pagnext";?>'>></a></h1>
			<h1><?php echo "Numero voto: $pag"?></h1>
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
					<button class='btn btn-primary mb-2' type="submit" name="download_zip">Scarica tutti i CSV come ZIP</button>
			</form>
		</div>  
	</div>

	<?php
	// open the csv file on read mode
	if (($handle = fopen($csvFile, 'r')) !== FALSE) {
		echo "<div class='table-responsive'>";
		echo "<table class='table table-striped table-bordered'>";

		// read all the csv row by row
		$isHeader = true;
		while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
			// the first line is the header
			if ($isHeader) {
				echo '<thead class="thead-dark"><tr>';
				foreach ($data as $header) {
					echo '<th style="text-align: center;">' . htmlspecialchars($header) . '</th>';
				}
				echo '</tr></thead><tbody>';
				$isHeader = false;
			} else {
				// next datas after the header
				echo '<tr>';
				foreach ($data as $cell) {
					echo '<td style="text-align: center;">' . htmlspecialchars($cell) . '</td>';
				}
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo "</div>";

		// close the csv file
		fclose($handle);
	}else{
		echo 'Errore nell\'apertura del file CSV.';
	}
	?>
	<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
	</body>
	</html>
	<?php 
}else{
	// if there are no csv in the directory, no table shown
	echo "<h1 style='text-align: center;'> Nessun file csv </h1>";
}
?>