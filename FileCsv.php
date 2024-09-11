<?php
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_reporting(E_WARNING); */


class FileCsv {

    public $nomeFile;
    public $dati;

    public function __construct($nomeFile, $dati) {
        $this->nomeFile = $nomeFile;
        $this->dati = $dati;
    }
    public function creaFile() {
        $file = $this->apriFile('w');
        foreach ($this->dati as $riga) {
            fputcsv($file, $riga);
        }
        fclose($file);
        return true;
    }

    public function creaFileConIntestazione($intestazione) {
        $file = $this->apriFile('w');
        foreach ($intestazione as $riga) {
            fputcsv($file, $riga);
        }
        fclose($file);
        return true;
    }

    public function aggiungiRiga($riga) {
        $file = $this->apriFile('a');
        fputcsv($file, $riga);
        fclose($file);
    }

    public function aggiungiRighe($dati) {
        $file = $this->apriFile('a');
        foreach ($dati as $riga) {
            fputcsv($file, $riga);
        }
        fclose($file);
    }

    public function apriFile($modo) {
        $file = fopen($this->nomeFile, $modo);
        if ($file === false) {
            die('Error opening the file ' . $this->nomeFile);
        }
        return $file;
    }
    public function creaCSVdaArray($dati) {
        $intestazione = Array();
        foreach ($dati[0] as $chiave => $valore) {
            $intestazione[] = $chiave;
        }
        $this->creaFileConIntestazione($intestazione);
        $this->aggiungiRighe($dati);
    }
}
?>