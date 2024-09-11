<?php 
/*
 * Pagina report table
 * 
 * 
 * Created by: Bruno Marino
 * 
 * */
 
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once(dirname(dirname(__FILE__)).'/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

$linkpagina=$_SERVER['REQUEST_URI'];

$idsessione=0;
$nomesessione="All";
$idstudente=0;
$nomestudente="All";
$ssid  = 0; 
$stdid = 0;
if(isset($_GET["ssid"])){
	$ssid = $_GET["ssid"];
}else{
	$ssid = -1;
}
if(isset($_GET["stdid"])){
	$stdid = $_GET["stdid"];
}else{
	$stdid = -1;
}


$id         = optional_param('cid', 0, PARAM_INT); // course_module ID, or
$wid        = optional_param('wid', 0, PARAM_INT); // advwork ID
$editmode   = optional_param('editmode', null, PARAM_BOOL);
$page       = optional_param('page', 0, PARAM_INT);
$perpage    = optional_param('perpage', null, PARAM_INT);
$sortby     = optional_param('sortby', 'lastname', PARAM_ALPHA);
$sorthow    = optional_param('sorthow', 'ASC', PARAM_ALPHA);
$eval       = optional_param('eval', null, PARAM_PLUGIN);

if ($id) {
    $cm             = get_coursemodule_from_id('advwork', $wid, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
    $advworkrecord = $DB->get_record('advwork', array('id' => $cm->instance), '*', MUST_EXIST);
}

require_login($course, true, $cm);
require_capability('mod/advwork:view', $PAGE->context);

$advwork = new advwork($advworkrecord, $cm, $course);

$linkreport="./index.php?cid=$id&wid=$wid";

$style = "
                  border: 1px solid;
                  color: black;
                  background-color: #ddd;
                  text-align: center;
                  text-decoration: none;
                  display: inline-block;
                  font-size: 16px;
                  margin: 4px 2px;
                  transition-duration: 0.4s;
                  cursor: pointer;";

$sqlsessioni="
 SELECT distinct ktable.advworkid, advworksession, ktable.course
FROM (
select DISTINCT submissions.id AS number,advwork.id AS advworkid,advwork.name AS advworksession,authors.id AS studentid,authors.lastname AS studentlastname,authors.firstname AS studentfirstname,authors.email AS studentemail,from_unixtime(submissions.timecreated) AS timesubmitted,ROUND(submissions.grade) AS submissiongrade, advwork.course as course
from ((mdl_advwork_submissions submissions join mdl_user authors on((authors.id = submissions.authorid))) join mdl_advwork advwork on((advwork.id = submissions.advworkid)))  
order by authors.lastname,authors.firstname
) ktable,
(select advwork.id AS advworkid,authors.id AS reviewerid,ROUND(avg(assessments.gradinggrade)) AS assessmentgrade
from (((mdl_advwork_submissions submissions  join mdl_advwork advwork on((advwork.id = submissions.advworkid))) 
join mdl_advwork_assessments assessments on((assessments.submissionid = submissions.id))) join mdl_user authors on((authors.id = assessments.reviewerid))) 
Group by  advwork.id,authors.id
order by authors.lastname) jtable

WHERE ktable.advworkid=jtable.advworkid AND ktable.studentid=jtable.reviewerid AND course = $course->id

ORDER By advworkid asc
";

$sqlstudenti="
SELECT distinct studentid,studentlastname,studentfirstname, COUNT(studentid) as sessionsnumber,studentemail, course
FROM (
select DISTINCT submissions.id AS number,advwork.id AS advworkid,advwork.name AS advworksession,authors.id AS studentid,authors.lastname AS studentlastname,authors.firstname AS studentfirstname,authors.email AS studentemail,from_unixtime(submissions.timecreated) AS timesubmitted,ROUND(submissions.grade) AS submissiongrade, advwork.course as course
from ((mdl_advwork_submissions submissions join mdl_user authors on((authors.id = submissions.authorid))) join mdl_advwork advwork on((advwork.id = submissions.advworkid))) 
order by authors.lastname,authors.firstname
) ktable,
(select advwork.id AS advworkid,authors.id AS reviewerid,ROUND(avg(assessments.gradinggrade)) AS assessmentgrade
from (((mdl_advwork_submissions submissions  join mdl_advwork advwork on((advwork.id = submissions.advworkid))) 
join mdl_advwork_assessments assessments on((assessments.submissionid = submissions.id))) join mdl_user authors on((authors.id = assessments.reviewerid)))
Group by  advwork.id,authors.id
order by authors.lastname) jtable

WHERE ktable.advworkid=jtable.advworkid AND ktable.studentid=jtable.reviewerid AND course = $course->id
GROUP BY studentid, studentlastname, studentfirstname
ORDER By studentlastname, studentfirstname
";


$sqlalldata="
SELECT ktable.*,jtable.assessmentgrade FROM (
select DISTINCT submissions.id AS number,advwork.id AS advworkid,advwork.name AS advworksession,authors.id AS studentid,authors.lastname AS studentlastname,authors.firstname AS studentfirstname,authors.email AS studentemail,from_unixtime(submissions.timecreated) AS timesubmitted,ROUND(submissions.grade) AS submissiongrade, advwork.course as course
from ((mdl_advwork_submissions submissions join mdl_user authors on((authors.id = submissions.authorid))) join mdl_advwork advwork on((advwork.id = submissions.advworkid))) 
order by authors.lastname,authors.firstname
) ktable
,
(select advwork.id AS advworkid,authors.id AS reviewerid,ROUND(avg(assessments.gradinggrade)) AS assessmentgrade
from (((mdl_advwork_submissions submissions  join mdl_advwork advwork on((advwork.id = submissions.advworkid))) 
join mdl_advwork_assessments assessments on((assessments.submissionid = submissions.id))) join mdl_user authors on((authors.id = assessments.reviewerid)))
Group by  advwork.id,authors.id
order by authors.lastname) jtable

WHERE ktable.advworkid=jtable.advworkid AND ktable.studentid=jtable.reviewerid AND course = $course->id
ORDER BY advworkid ASC
";


$sqlaverage="
SELECT average_table.studentid, user_table.lastname as studentlastname, user_table.firstname as studentfirstname, user_table.email as studentemail, average_table.average as submissiongrade, average_table.sessionnumber 
FROM 
(SELECT modello.userid as studentid, AVG(modello.capabilityoverallvalue) as average, COUNT(DISTINCT advwork) as sessionnumber 
FROM 
(SELECT sm.advworkid as advwork, MAX(sm.probability) as probability, sm.userid as student 
FROM mdl_advwork_student_models sm INNER JOIN mdl_advwork_capabilities c on sm.capabilityid = c.id INNER JOIN mdl_advwork_domain_values dm on sm.domainvalueid = dm.id 
WHERE sm.courseid = $course->id AND c.name='C' 
GROUP BY sm.advworkid, sm.userid) 
as max_p JOIN mdl_advwork_student_models as modello 
WHERE max_p.student = modello.userid AND max_p.advwork = modello.advworkid AND max_p.probability = modello.probability 
GROUP BY studentid) 

as average_table JOIN mdl_user as user_table ON average_table.studentid = user_table.id
";


//recupero le sessioni advworker concluse
$sessioni = $DB->get_records_sql($sqlsessioni);

//recupero tutti gli studenti che hanno partecipato a tutte le sessioni advworker concluse
$studenti = $DB->get_records_sql($sqlstudenti);

//recupero tutti i dati di tutti gli studenti che hanno partecipato ad ogni sessione
$records = $DB->get_records_sql($sqlalldata);

//recupero i record delle medie
$average = $DB->get_records_sql($sqlaverage);

$ris = $DB->get_records_sql("SELECT id FROM mdl_advwork WHERE course = $course->id ORDER BY id");

function getIndexbyssid($ssid) { // i voti dello studente vengono dalla funzione get_student_grades() che ritorna i voti con un array indicizzato sugli interi invece sui advworkid. Questa funzione associa l'advworkid (o ssid) con l'indice intero dell'array dei voti.
    global $ris;

    $indexGrades= 0;
    foreach ($ris as $val) {
        if ($val->id == $ssid) {
            break;
        }
        $indexGrades++;
    }
    return $indexGrades;
}

//print_object($records);
	$docente=false;
	//individuo il tipo di utente basandomi sulla capability
 	if (has_capability('mod/advwork:viewallassessments', $PAGE->context)) {
		$docente=true;
	}else{
		$docente=false;//studente
	}



/*
 * 
 *    Docente:
 *     2 Select
 *        1) sessione (tutte o specifica) ssid
 *        2) studente (generale o studente specifico) stdid
 *    Studente:
 *     1 Select
 *        1) sessione (tutte o specifica con riga dove sta studente) ssid 
 *        (prendi stdid da variabili moodle)
 * 
 */
 
 
if($docente){ 
	if($ssid=="-1"){//variabile non settata, prendo l'id del advwork
		$ssid=$advworkrecord->id;  
	}
	if($stdid=="-1"){//variabile non settata
		$stdid=0;  
	}
}else{//se non docente
	if($ssid=="-1"){//variabile non settata
		$ssid=$advworkrecord->id;  
	}
	//$stdid sempre uguale a id studente
	$stdid=$USER->id;
} 
		
	$datigrafico=array();



if($ssid==0){ //studente non selezionato
		foreach($sessioni as $sessione){
				//echo $sessione->advworksession;
			$datigrafico[$sessione->advworkid]["SessionName"]=$sessione->advworksession;
			$i=0;
			foreach($records as $record){
				if($record->advworkid==$sessione->advworkid){
                    $submissionGrade = number_format((float)$advwork->get_student_grades($course->id, $record->studentid)[getIndexbyssid($sessione->advworkid)] * 100, 2, '.', '');
                    $j = number_format((float)$advwork->get_j($course->id, $record->studentid) * 100, 2, '.', '');

                    //risultati accoppiati con id =studentid
                    $datigrafico[$sessione->advworkid]["Results"][$record->studentid]=array("SubmissionGrade"=>nulltozero($submissionGrade),"AssessmentGrade"=>nulltozero($j));

                    //risultati disaccoppiati
                    $datigrafico[$sessione->advworkid]["Submissions"][$i]=nulltozero($submissionGrade);
                    $datigrafico[$sessione->advworkid]["Assessments"][$i]=nulltozero($j);
					
					$i++;
				}

				//ordino i voti per Submissions
                if (!is_null($datigrafico[$sessione->advworkid]["Submissions"])) 
				sort($datigrafico[$sessione->advworkid]["Submissions"]);
				//ordino i voti per Assessments
                if (!is_null($datigrafico[$sessione->advworkid]["Assessments"])) 
				sort($datigrafico[$sessione->advworkid]["Assessments"]);
			}
			
		
		}
	}else{ //studente selezionato
		
		if($ssid!=-2){ //report != da average
			
		//echo $ssid;
		foreach($sessioni as $sessione){
			//se l'id dello studente e' uguale a quello selezionato mostro i dati
			if($sessione->advworkid==$ssid){
			//echo $sessione->advworksession;
			$datigrafico[$sessione->advworkid]["SessionName"]=$sessione->advworksession;
			$i=0;
			foreach($records as $record){
				if($record->advworkid==$sessione->advworkid){
                    $submissionGrade = number_format((float)$advwork->get_student_grades($course->id, $record->studentid)[getIndexbyssid($ssid)] * 100, 2, '.', '');
                    $j = number_format((float)$advwork->get_j($course->id, $record->studentid) * 100, 2, '.', '');
                    //risultati accoppiati con id =studentid
                    $datigrafico[$sessione->advworkid]["Results"][$record->studentid]=array("SubmissionGrade"=>nulltozero($submissionGrade),"AssessmentGrade"=>nulltozero($j));
                    //risultati disaccoppiati
                    $datigrafico[$sessione->advworkid]["Submissions"][$i]=nulltozero($submissionGrade);
                    $datigrafico[$sessione->advworkid]["Assessments"][$i]=nulltozero($j);
					
					$i++;
				}
				//ordino i voti per Submissions
                if (!is_null($datigrafico[$sessione->advworkid]["Submissions"])) 
				sort($datigrafico[$sessione->advworkid]["Submissions"]);
				//ordino i voti per Assessments
                if (!is_null($datigrafico[$sessione->advworkid]["Assessments"])) 
				sort($datigrafico[$sessione->advworkid]["Assessments"]);
			}
			break;
				}
		}
		
		}//if ssid != -2
	
	}
	
	/*
	 *     creo array contenente righe report
	 * */
	$righe=array();
		
		//se il report non e' "average" e nemmeno "progressive"
		if($ssid!=-2 && $ssid!=-3){
		foreach($datigrafico as $dato){
		//exit;
		$nomesess=$dato["SessionName"];
	
		foreach($dato["Results"] as $key => $risultato){
			$nomestud=$studenti[$key]->studentlastname. " ".$studenti[$key]->studentfirstname;
			$emailstud=$studenti[$key]->studentemail;
			$votosubmission=$risultato["SubmissionGrade"];
			$votoassessment=$risultato["AssessmentGrade"];
			
			
					//stampo i dati solo se non ci sono studenti selezionati 
		//o solo se gli studenti selezionati hanno partecipato alla sessione
		if($stdid==0){
		//compilo riga
		$righe[]= array("advworkerSession"=>$nomesess,"StudentName"=>$nomestud,"StudentEmail"=>$emailstud,"SubmissionGrade"=>$votosubmission,"AssessmentGrade"=>$votoassessment);
		}else{
			if($stdid==$key){
		      //compilo riga
		      		$righe[]= array("advworkerSession"=>$nomesess,"StudentName"=>$nomestud,"StudentEmail"=>$emailstud,"SubmissionGrade"=>$votosubmission,"AssessmentGrade"=>$votoassessment);
			}
		}
		

		}//fine foreach interno
			

	}//fine foreach esterno 
	}elseif($ssid==-2){ //report = average
		//$ssid=-2
		
		foreach($average as $media){
			$nomestud=$media->studentlastname. " ".$media->studentfirstname;
			$emailstud=$media->studentemail;
			$votosubmission=nulltozero(number_format((float)$media->submissiongrade * 100, 2, '.', ''));
			//$votoassessment=nulltozero($media->assessmentgrade);
			$numerosessioni=$media->sessionnumber;
			
			
					//stampo i dati solo se non ci sono studenti selezionati 
		//o solo se gli studenti selezionati hanno partecipato alla sessione
		if($stdid==0){
			//compilo riga
			$righe[]= array("StudentName"=>$nomestud,"StudentEmail"=>$emailstud,"AvgSubmissionGrade"=>$votosubmission,"SessionsNumber"=>$numerosessioni);
		}else{
			if($stdid==$media->studentid){
				//compilo riga
				$righe[]= array("StudentName"=>$nomestud,"StudentEmail"=>$emailstud,"AvgSubmissionGrade"=>$votosubmission,"SessionsNumber"=>$numerosessioni);
			}
		}
		

		}//fine foreach interno
		
		
		
		
		
	}elseif($ssid==-3){ //report = progressive

		
		if($stdid==0){
		//Per ogni studente creo record con il progressivo di K e J delle sessioni alle quali ha partecipato
		foreach($studenti as $stdnt){
			
			$righe[$stdnt->studentid][]=$stdnt->studentlastname. " ".$stdnt->studentfirstname;
			$righe[$stdnt->studentid][]=$stdnt->sessionsnumber;
			//Per ogni sessione cerco i dati dello studente

            $righe[$stdnt->studentid][]=nulltozero(number_format((float)$advwork->get_j($course->id, $stdnt->studentid) * 100, 2, '.', ''));
            $righe[$stdnt->studentid][]=nulltozero(number_format((float)$advwork->get_k($course->id, $stdnt->studentid) * 100, 2, '.', ''));

			foreach($sessioni as $sessione){
				$trovato=0;
				foreach($records as $recordsessione){
				
					if($recordsessione->studentid==$stdnt->studentid && $sessione->advworkid == $recordsessione->advworkid){
						$trovato=1;
                        $submissionGrade = number_format((float)$advwork->get_student_grades($course->id, $recordsessione->studentid)[getIndexbyssid($sessione->advworkid)] * 100, 2, '.', '');
						$righe[$stdnt->studentid][]=nulltozero($submissionGrade);
						break;
					}
				
				}
				//controlla trovato
				if($trovato==0){
					$righe[$stdnt->studentid][]="";
				}
			}//fine per ogni sessione

		}// fine foreach studente
	  }//fine se nessuno studente selezionato
	  /*else{
		//studente selezionato
		
			$righe[$stdid][]=$studenti[$stdid]->studentlastname. " ".$studenti[$stdid]->studentfirstname;
			$righe[$stdid][]=$studenti[$stdid]->sessionsnumber;
			//Per ogni sessione cerco i dati dello studente
			
			
			foreach($sessioni as $sessione){
				$trovato=0;
				foreach($records as $recordsessione){
				
					if($recordsessione->studentid==$stdid && $sessione->advworkid==$recordsessione->advworkid){
						$trovato=1;
						//K
						$righe[$stdid][]=nulltozero($recordsessione->submissiongrade);
						//J
						$righe[$stdid][]=nulltozero($recordsessione->assessmentgrade);
						break;
					}
				
				}
				//controlla trovato
				if($trovato==0){
					//K
					$righe[$stdid][]="";
					//J
					$righe[$stdid][]="";
				}
			}//fine per ogni sessione
		  
		  }*/
	}//report progressive

	 //gestione richiesta esportazione dati
/**
 * @param $ssid
 * @param $sessione
 * @param $stdid
 * @param $studenti
 * @param array $righe
 * @return void
 */
function creaFileCSV($ssid, $sessione, $stdid, $studenti, array $righe) {
    global $sessioni;

    $nsession = "All-advworker";
    $nstudent = "all-student";
    if ($ssid != 0 && $ssid != -2)
        $nsession = $sessione->advworksession;
    if ($ssid == -2)
        $nsession = "Average-advworker";
    if ($ssid == -3)
        $nsession = "Progressive-advworker";
    if ($stdid != 0)
        $nstudent = $studenti->studentlastname . "-" . $studenti->studentfirstname;

    $nsession = str_replace(" ", "-", $nsession);
    $nsession = str_replace("/", "-", $nsession);
    $nstudent = str_replace(" ", "-", $nstudent);

    $filename = $nsession . "-" . $nstudent . ".csv";

    $path = dirname((__DIR__)) . "/FileCsv.php";
    require_once($path);
    $intestazione = array();
    if ($ssid==-3) {
        $i=0;
        $intestazione[]="StudentName";
        $intestazione[]="TotalSessions";
        $intestazione[]="J";
        $intestazione[]="K";

        foreach($sessioni as $s){
            $i++;
            $intestazione[]="Grade ($i)";
        }
    } else {
        foreach ($righe[0] as $chiave => $valore) {
            $intestazione[] = $chiave;
        }
    }


    $file = new FileCsv($filename, array_merge(array($intestazione), $righe));
    $file->creaFile();
    return $filename;
}

$nomeFile=creaFileCSV($ssid, $sessioni[$ssid], $stdid, $studenti[$stdid], $righe);


	
echo $OUTPUT->header();
?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
.select-box{
	display:inline-block;
	margin:20px;
	}
.select{
	width:250px;
}	
.messaggio-grafico{
	color:red;
	font-size:16px;
}
.button {
  background-color:#ddd;
  border: none;
  color: black;
  padding: 8px 15px;
  text-decoration: none;
  display: inline-block;
  border-radius: 16px;
  border: transparent;
  box-shadow: 5px 5px 12px -6px #000000;
  cursor: pointer;
  font-size: 14px;
  border:1px solid;

}
.box select {
  border-radius: 10px 10px 10px 10px; 
  background-color:#ddd;
  color: black;
  padding: 8px;
  border:#000000;
  font-size: 15px;
  box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
  -webkit-appearance: button;
  appearance: button;
  outline: none;
  margin-top: 10px;
  border:1px solid;
}
.popup {
border-radius: 10px 10px 10px 10px; 

  background-color: #ddd;
  border: none;
  color: black;
  padding: 12px 30px;
  cursor: pointer;
  font-size: 20px;
  position: relative;
  display: inline-block;
  cursor: pointer;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
}

.popup .popuptext {
  visibility: hidden;
  width: 400px;
  background-color: #ddd;
  color: black;
  text-align: center;
  border-radius: 6px;
  padding: 8px 0;
  position: absolute;
  z-index: 1;
  bottom: 125%;
  left: 0%;
  margin-left: -80px;
  border: 0.2px solid;
}
.popup .popuptext::after {
  content: "";
  position: absolute;
  top: 100%;
  left: 40%;
  margin-left: -5px;
  border-width: 5px;
  border-style: solid;
  border-color: #555 transparent transparent transparent;
}

/* Toggle this class - hide and show the popup */
.popup .show {
  visibility: visible;
  -webkit-animation: fadeIn 1s;
  animation: fadeIn 1s;
}

/* Add animation (fade in the popup) */
@-webkit-keyframes fadeIn {
  from {opacity: 0;} 
  to {opacity: 1;}
}

@keyframes fadeIn {
  from {opacity: 0;}
  to {opacity:1 ;}
}

</style>
<div>
<h2 style="text-align: center;">Tabular</h2>
<div style="text-align:center">
<form>
<input type="hidden" name="cid" value="<?php echo $id;?>">
<input type="hidden" name="wid" value="<?php echo $wid;?>">
<div class="box" style="margin-top: 40px; display:inline-block;">
<strong>Select advworker session</strong>
<br />
<select id="sessione" name="ssid" class="select">
<option value="<?php echo $idsessione;?>"><?php echo $nomesessione;?></option>
<option value="-2" <?php if($ssid==-2) echo "selected";?> >Average</option>
<option value="-3" <?php if($ssid==-3) echo "selected";?> >Progressive</option>
<?php foreach($sessioni as $sessione){?>
<option <?php if($sessione->advworkid==$ssid) echo "selected";?> value="<?php echo $sessione->advworkid;?>"><?php echo  $sessione->advworksession;?></option>
<?php }?>
</select>
<div class="select-box">
 <button type="submit" class="button"><strong>Report</strong></button> 
</div>
</div>

<?php 
//solo i docenti possono selezionare gli studenti da visualizzare
if($docente){?>
<div class="box" style="display: inline-block;">
<strong>Select student</strong>
<br />
<select id="studente" name="stdid" class="select">
<option value="<?php echo $idstudente;?>"><?php echo $nomestudente;?></option>
<?php foreach($studenti as $studente){
			if($ssid==0 || $ssid==-2 || $ssid==-3){
	?>
<option <?php if($studente->studentid==$stdid) echo "selected";?> value="<?php echo $studente->studentid;?>"><?php echo  $studente->studentlastname ." ". $studente->studentfirstname;?></option>
<?php }//fine if
else{
	//stampo lo studente solo se ha paHow to read the graphrtecipato alla sessione selezionata
  if(array_key_exists($studente->studentid,$datigrafico[$ssid]["Results"])){
?>
<option <?php if($studente->studentid==$stdid) echo "selected";?> value="<?php echo $studente->studentid;?>"><?php echo  $studente->studentlastname ." ". $studente->studentfirstname;?></option>
  <?php
       }//fine if studente ha partecipato alla sessione
	}//fine else
}//fine foreach?>
</select>

</div>
<?php } ?>


</form>
    <a href= <?php echo $nomeFile;?> download><button class="popup" onmouseover="myFunction()" name="export" style = <?php echo "\"".$style."\"";?> type="submit"><i class="fa fa-download"></i>
	<span class="popuptext" id="myPopup">With this option, you can download and export the data to a CSV (Comma Separated Values) file. It is a file characterized by the presence of tabular data in text form. You can later open the file as a table in Excel.</span>  <strong>Export data</strong>  </button></a>
<br />
<?php if($ssid==-3){?>
<div>
<p>
<?php 		$i=0;
		foreach($sessioni as $sessione){ 
			$i++;
			//echo "($i) -> ".$sessione->advworksession. "<br />";
  }?>
</p><br />
</div>
<?php }?>
<?php if(count($datigrafico)>0 || $ssid==-2 || $ssid==-3){ ?>
       <div id="table_div"></div>
<?php }else{ ?>
<p class="messaggio-grafico">Please select an existing session or student!</p>
<?php } ?>
<br />
<br />
<div>
<a href="<?php echo $linkreport; ?>">Go Back</a>
</div>
</div>
</div>
<script>
function myFunction() {
  var popup = document.getElementById("myPopup");
  popup.classList.toggle("show");
}
</script>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
      google.charts.load('current', {'packages':['table']});
      google.charts.setOnLoadCallback(drawTable);

      function drawTable() {
        var data = new google.visualization.DataTable();
		
		<?php 
	if($ssid!=-3){
		$submissionstring="Submission grade";
		$assessmentstring="Assessment grade";
		if($ssid!=-2){ ?>
        data.addColumn('string', 'advworker session');
        <?php }else{
			
			$submissionstring="Avg submission grade";
			$assessmentstring="Avg assessment grade";
			
			} ?>
		data.addColumn('string', 'Student name');
        data.addColumn('string', 'Student email');
		data.addColumn('number', '<?php echo $submissionstring; ?>');

          <?php if($ssid!=-2){ ?>
          data.addColumn('number', '<?php echo $assessmentstring; ?>');
          <?php } if($ssid==-2){ ?>
        data.addColumn('number', 'Sessions number');
        <?php } 
	}//fine ssid!=-3
	else{//ssid==-3 quindi progressive table
		?>
		data.addColumn('string', 'Student');
		data.addColumn('string', 'Total sessions');
        data.addColumn('string', 'J');
        data.addColumn('string', 'K');
		<?php 
		$i=0;
		foreach($sessioni as $sessione){ 
			$i++;
			?>
			data.addColumn('string', 'Grade (<?php echo $i;?>)');
		<?php } ?>
	<?php }// fine ssid==-3
	//echo $ssid;
	?>
        data.addRows([
		
		<?php 
	if($ssid!=-3){
	$primo=1;
	foreach($righe as $riga){
		
				
		if(!$primo)
			echo ",";
		else
			$primo=0;
			
		
		$nomestud=$riga["StudentName"];
		$emailstud=$riga["StudentEmail"];
		
		
		if($ssid!=-2){
			$nomesessione=$riga["advworkerSession"];
			$votosubmission=$riga["SubmissionGrade"];
			$votoassessment=$riga["AssessmentGrade"];
			echo $rigadati= "['$nomesessione','$nomestud','$emailstud',{v: $votosubmission, f: '$votosubmission'},{v: $votoassessment, f: '$votoassessment'}]";
		}else{
			$numsession=$riga["SessionsNumber"];
			$votosubmission=$riga["AvgSubmissionGrade"];
			//$votoassessment=$riga["AvgAssessmentGrade"];
			echo $rigadati= "['$nomestud','$emailstud',{v: $votosubmission, f: '$votosubmission'},{v: $numsession, f: '$numsession'}]";
		}
	}//fine foreach
          //['TDP1',  'piero', 'piero@email',{v: 74, f: '74'}, {v: 89, f: '89'}],
	}//ssid!=-3
	else{
		//print_object($righe);
		foreach($righe as $riga){
			$rigadati.= " [";
			$primo=1;
			
			
			foreach($riga as $colonna){
				if(!$primo)
					$rigadati.= ",";
				else
					$primo=0;
				$rigadati.="'$colonna'";
			}
			$rigadati.= "],";
		}
		
		echo $rigadati;
	}
   ?>       
          
        ]);
		
		    // if no data add row for annotation -- No Data Copy
    if (data.getNumberOfRows() === 0) {
      data.addRows([
        ['',  'Student not partecipate to this session', '', null , null],
      ]);
    }

        var table = new google.visualization.Table(document.getElementById('table_div'));

        table.draw(data, {showRowNumber: true, width: '100%', height: '100%'});
      }
</script>
<?php
echo $OUTPUT->footer();
die();


//preparo l'intestazione per il download del file
/*function download_send_headers($filename) {
    // disable caching
    $now = gmdate("D, d M Y H:i:s");
    header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
    header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
    header("Last-Modified: {$now} GMT");

    // force download  
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");

    // disposition / encoding on response body
    header("Content-Disposition: attachment;filename={$filename}");
    header("Content-Transfer-Encoding: binary");
}*/

//funzione che trasforma null in 0
function nulltozero($x){
	
	if($x==NULL)	
		return 0;
	else
		return $x;
}
?>