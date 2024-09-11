<?php 
/*
 * Pagina report bar-chart
 * 
 * 
 * Created by: Bruno Marino
 * 
 * */
 
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once(dirname(dirname(__FILE__)).'/locallib.php');
require_once($CFG->libdir.'/completionlib.php');


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

$linkbox="./box-plot.php?cid=$id&wid=$wid";
$linkline="./line-chart.php?cid=$id&wid=$wid";
$linkbar="./bar-chart.php?cid=$id&wid=$wid";
$linkreport="./index.php?cid=$id&wid=$wid";

$advwork = new advwork($advworkrecord, $cm, $course);

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


//recupero le sessioni advworker concluse
$sessioni = $DB->get_records_sql($sqlsessioni);

//recupero tutti gli studenti che hanno partecipato a tutte le sessioni advworker concluse
$studenti = $DB->get_records_sql($sqlstudenti);

//recupero tutti i dati di tutti gli studenti che hanno partecipato ad ogni sessione
$records = $DB->get_records_sql($sqlalldata);

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


//print_object($studenti);
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
		

		foreach($sessioni as $sessione){
				//echo $sessione->advworksession;
			$datigrafico[$sessione->advworkid]["SessionName"]=$sessione->advworksession;
			$i=0;
			foreach($records as $record){
				if($record->advworkid==$sessione->advworkid){
                    //risultati accoppiati con id =studentid
                    $datigrafico[$sessione->advworkid]["Results"][$record->studentid]=array("SubmissionGrade"=>nulltozero($advwork->get_student_grades($course->id, $record->studentid)[getIndexbyssid($sessione->advworkid)]*100),"AssessmentGrade"=>nulltozero($advwork->get_j($course->id, $record->studentid)*100));
                    //risultati disaccoppiati
                    $datigrafico[$sessione->advworkid]["Submissions"][$i]=nulltozero($advwork->get_student_grades($course->id, $record->studentid)[getIndexbyssid($sessione->advworkid)]*100);
                    $datigrafico[$sessione->advworkid]["Assessments"][$i]=nulltozero($advwork->get_j($course->id, $record->studentid)*100);
					
					$i++;
				}
				
			}
			//ordino i voti per Submissions
				sort($datigrafico[$sessione->advworkid]["Submissions"]);
				//ordino i voti per Assessments
				sort($datigrafico[$sessione->advworkid]["Assessments"]);
		}
	
	//print_object($datigrafico);
	
echo $OUTPUT->header();
?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
.select-box{
	display:inline-block;
	margin:20px;
	}
.select{
	width:230px;
}	
.messaggio-grafico{
	color:red;
	font-size:16px;
}
.button {
  background-color: #E2E2E2;
  border: none;
  color: black;
  padding: 8px 15px;
  text-decoration: none;
  display: inline-block;
  border-radius: 16px;
	border: transparent;
	box-shadow: 5px 5px 15px -6px #000000;
  cursor: pointer;
  font-size: 14px;
  border:1px solid;
}
.box select {
  border-radius: 10px 10px 10px 10px; 
  background-color:#E2E2E2;
  color: black;
  padding: 8px;
  border: none;
  font-size: 15px;
  box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
  -webkit-appearance: button;
  appearance: button;
  outline: none;
  border:1px solid;

}



.box select option {
  padding: 30px;
}
</style>
<div>
<h2 style="text-align: center;">Overlapping bar chart</h2>
<div style="text-align:center">
<form>
<input type="hidden" name="cid" value="<?php echo $id;?>">
<input type="hidden" name="wid" value="<?php echo $wid;?>">
<div class="box" style="display:inline-block">
<strong>Select advworker session</strong>
<br />
<select id="sessione" name="ssid" class="select">
<option value="<?php echo $idsessione;?>"><?php echo $nomesessione;?></option>
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
if($docente){ ?>
<div class="box" style="display: inline-block;">
<strong>Select student</strong>
<br />
<select id="studente" name="stdid" class="select">
<option value="<?php echo $idstudente;?>"><?php echo $nomestudente;?></option>
<?php foreach($studenti as $studente){
	
			if($ssid==0){
	?>
<option <?php if($studente->studentid==$stdid) echo "selected";?> value="<?php echo $studente->studentid;?>"><?php echo  $studente->studentlastname ." ". $studente->studentfirstname;?></option>
<?php }//fine if
else{
	//stampo lo studente solo se ha partecipato alla sessione selezionata
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
<?php if(count($datigrafico)>0){ ?>
<style>
#xasse{
	width:100%;
	border-bottom: 1px solid  #808080;
	height:20px;
	position: relative;
}
.valoreasse{
	width:40px;
	text-align:center;
	position: absolute;
	bottom: -20px;
}
.zero{
	left: -20px;
}
.dieci{
	left: 10%;
	left: -webkit-calc(10% - 20px);
	left: -moz-calc(10% - 20px);
	left: calc(10% - 20px);
}
.venti{
	left: 20%;
	left: -webkit-calc(20% - 20px);
	left: -moz-calc(20% - 20px);
	left: calc(20% - 20px);
}
.trenta{
	left: 30%;
	left: -webkit-calc(30% - 20px);
	left: -moz-calc(30% - 20px);
	left: calc(30% - 20px);
}
.quaranta{
	left: 40%;
	left: -webkit-calc(40% - 20px);
	left: -moz-calc(40% - 20px);
	left: calc(40% - 20px);
}
.cinquanta{
	left: 50%;
	left: -webkit-calc(50% - 20px);
	left: -moz-calc(50% - 20px);
	left: calc(50% - 20px);
}
.sessanta{
	left: 60%;
	left: -webkit-calc(60% - 20px);
	left: -moz-calc(60% - 20px);
	left: calc(60% - 20px);
}
.settanta{
	left: 70%;
	left: -webkit-calc(70% - 20px);
	left: -moz-calc(70% - 20px);
	left: calc(70% - 20px);
}
.ottanta{
	left: 80%;
	left: -webkit-calc(80% - 20px);
	left: -moz-calc(80% - 20px);
	left: calc(80% - 20px);
}
.novanta{
	left: 90%;
	left: -webkit-calc(90% - 20px);
	left: -moz-calc(90% - 20px);
	left: calc(90% - 20px);
}
.cento{
	right: -20px;
}
.sessione{
	position: relative;
}
.barra{
	height:40px;
	color:white;
	text-align:right;
	position: absolute;
	top:-40px;
	font-weight:bold;
	line-height:40px;
	font-size:14px;
	}
.barraprev{
	height:20px;
	color:white;
	text-align:right;
	position: absolute;
	top:-40px;
	font-weight:bold;
	line-height:20px;
	font-size:10px;
	}
.valore{
	position:absolute;
	right:0;
	width:40px;
	text-align:center;
	}
.valoremax{
	position:absolute;
	right:-40px;
	color:#808080;
	width:40px;
	text-align:center;
	}	
.altezzabarra{
	height:40px;	
	}
.altezzamezzabarra{
	height:20px;
}
.nomebarra{
	position:absolute;
	width:100px;
	left:-110px;
	top:-40px;
	font-size:12px;
	}
.nomebarra span{
	font-size:10px;
	}
#currentsubmissionmax{
	background-color: #a3a3c5;
	}
#currentsubmissiongrade{
	background-color: #191970;
	}
#previussubmissionmax{
	background-color: #dddddd;
	}
#previussubmissiongrade{
	background-color: #acacac;
	}
	
#currentassessmentmax{
	background-color: #d2e8ff;
	}
#currentassessmentgrade{
	background-color: #1E90FF;
	}
#previusassessmentmax{
	background-color: #dddddd;
	}
#previusassessmentgrade{
	background-color: #acacac;
	}
.quadratolegenda{
	display:inline-block;
	width:25px;
	height:13px;
	text-align: center;
	border: 1px solid;
}
.blu{
	background-color:#191970;
	}
.bluchiaro{
	background-color:#a3a3c5;
}
.azzurro{
	background-color:#1E90FF;
	}
.azzurrochiaro{
	background-color:#d2e8ff;
}
.grigio{
	background-color:#acacac;
	}
.grigiochiaro{
	background-color:#dddddd;
}
.allineato{
	text-align:left;
	font-weight: bold;
	
    
}
.popup {
	text-align:left;
  position: relative;
  display: inline-block;
  cursor: pointer;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
  font-weight: bold;
}

/* The actual popup */
.popup .popuptext {
  visibility: hidden;
  width: 400px;
  background-color: #E2E2E2;
  color: black;
  text-align: center;
  border-radius: 6px;
  padding: 8px 0;
  position: absolute;
  z-index: 1;
  bottom: 100%;
  left: 0%;
  margin-left: 0px;
  border: 1px solid;
}

/* Popup arrow */
.popup .popuptext::after {
  content: "";
  position: absolute;
  top: 100%;
  left: 50%;
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
<?php

/*
 *     4 casi:
 *     - singola sessione con studente (mostra precedente se esiste)
 *     - singola sessione senza studente (mostra precedente se esiste)
 *     - tutte le sessioni con studente (mostra medie)
 *     - tutte le sessioni senza studente (mostra medie)
 * */	

$currentsubmissionmax=0;
$currentsubmissiongrade=0;

$previussubmissionmax=0;
$previussubmissiongrade=0;

$currentassessmentmax=0;
$currentassessmentgrade=0;

$previusassessmentmax=0;
$previusassessmentgrade=0;

$legendasubmission="";
$legendamaxsubmission="";
$legendaassessment="";
$legendamaxassessment="";
$legentavotoprecedente="";
$legendamaxprecedente="";
 
 
$hapartecipato=true;//mostro o non mostro il grafico se lo studente ha/non ha partecipato
$hapartecipatoprecedente=true;  

if($ssid!=0 && $stdid!=0){//singola sessione con studente selezionato
	
	$currentsubmissionmax=max($datigrafico[$ssid]["Submissions"]);
	$currentassessmentmax=max($datigrafico[$ssid]["Assessments"]);
	if(array_key_exists($stdid,$datigrafico[$ssid]["Results"])){
	    $currentsubmissiongrade=nulltozero($datigrafico[$ssid]["Results"][$stdid]["SubmissionGrade"]);
		$currentassessmentgrade=nulltozero($datigrafico[$ssid]["Results"][$stdid]["AssessmentGrade"]);
	}else{
		$hapartecipato=false;
	}
	
	
	//controllo se esiste sessione precedente

	$hasprevius=false;
	//variabile per la sessione corrente
	$currentsession=reset($datigrafico);
	//variabile per la sessione precedente
	$prevsession=$currentsession;
	foreach($datigrafico as $riga){
		
		//echo $prevsession["SessionName"].$datigrafico[$ssid]["SessionName"]."<br>";
		
		if($currentsession["SessionName"]==$datigrafico[$ssid]["SessionName"])
			break;
		
		//se alla sessione corrente ha partecipato lo studente
		//la segno come previus session
		if(array_key_exists($stdid,$currentsession["Results"]))
			$prevsession = $currentsession;
		
		$currentsession =$riga;
		
	}
	//se la sessione ha lo stesso nome della corrente, allora non ci sono sessioni precedenti
	if($prevsession["SessionName"]!=$datigrafico[$ssid]["SessionName"])
		$hasprevius=true;
	
	if(!$hasprevius){
		//non ho sessioni precedenti
		$previussubmissiongrade=-1;
		$previusasssessmentgrade=-1;
	}else{
		//esiste sessione precedente, prendo i dati
		$previussubmissionmax=max($prevsession["Submissions"]);
		$previusassessmentmax=max($prevsession["Assessments"]);
	if(array_key_exists($stdid,$prevsession["Results"])){		
		$previussubmissiongrade=nulltozero($prevsession["Results"][$stdid]["SubmissionGrade"]);
		$previusassessmentgrade=nulltozero($prevsession["Results"][$stdid]["AssessmentGrade"]);
	}else{//lo studente non ha partecipato
		$previussubmissiongrade=-1;
		$previusasssessmentgrade=-1;
	}
		
	}
	
	/*Creo la legenda per questo grafico*/
	
	$legendasubmission=$sessioni[$ssid]->advworksession ." ".$studenti[$stdid]->studentlastname." ".
	$studenti[$stdid]->studentfirstname." submission grade";
	$legendamaxsubmission=$sessioni[$ssid]->advworksession ." max submission grade";
	$legendaassessment=$sessioni[$ssid]->advworksession ." ".$studenti[$stdid]->studentlastname." ".
	$studenti[$stdid]->studentfirstname." assessment grade";
	$legendamaxassessment=$sessioni[$ssid]->advworksession ." max assessment grade";
	
	if($previussubmissiongrade!=-1){
		$legentavotoprecedente=$prevsession["SessionName"]." ".$studenti[$stdid]->studentlastname." ".
		$studenti[$stdid]->studentfirstname. " grade";
		$legendamaxprecedente=$prevsession["SessionName"]." max grade";
	}
	
}elseif($ssid!=0 && $stdid==0){//singola sessione senza studente selezionato
	
	$currentsubmissionmax=max($datigrafico[$ssid]["Submissions"]);
	$currentassessmentmax=max($datigrafico[$ssid]["Assessments"]);
	$currentsubmissiongrade=average($datigrafico[$ssid]["Submissions"]);
	$currentassessmentgrade=average($datigrafico[$ssid]["Assessments"]);
	
	//controllo se esiste sessione precedente
	$hasprevius=false;
	//variabile per la sessione corrente
	$currentsession=reset($datigrafico);
	//variabile per la sessione precedente
	$prevsession=$currentsession;
	foreach($datigrafico as $riga){
		
		if($currentsession["SessionName"]==$datigrafico[$ssid]["SessionName"])
			break;
		
		$prevsession = $currentsession;
		$currentsession =$riga;
		
	}
	//se la sessione ha lo stesso nome della corrente, allora non ci sono sessioni precedenti
	if($prevsession["SessionName"]!=$datigrafico[$ssid]["SessionName"])
		$hasprevius=true;
	
	//print_object($prevsession);

	//$prevsession = prev($array);

	if(!$hasprevius){
		//non ho sessioni precedenti
		$previussubmissiongrade=-1;
		$previusasssessmentgrade=-1;
	}else{
		//esiste sessione precedente, prendo i dati
	$previussubmissionmax=max($prevsession["Submissions"]);
	$previusassessmentmax=max($prevsession["Assessments"]);
	$previussubmissiongrade=average($prevsession["Submissions"]);
	$previusassessmentgrade=average($prevsession["Submissions"]);
	
	}
	
	/*Creo la legenda per questo grafico*/
	$legendasubmission=$sessioni[$ssid]->advworksession ." average submission grade";
	$legendamaxsubmission=$sessioni[$ssid]->advworksession ." max submission grade";
	$legendaassessment=$sessioni[$ssid]->advworksession ." average assessment grade";
	$legendamaxassessment=$sessioni[$ssid]->advworksession ." max assessment grade";	
	$legentavotoprecedente=$prevsession["SessionName"]. " average grade";
	$legendamaxprecedente=$prevsession["SessionName"]." max grade";
	
	
}elseif($ssid==0 && $stdid!=0){//tutte le sessioni con studente selezionato
	
	//calcolo voto medio dello studente di tutte le sessioni che ha partecipato
	//calcolo media dei voti di tutte le sessioni
	
	//la sessione non ha precedenti
	$previussubmissiongrade=-1;
	$previusasssessmentgrade=-1;
	
	$arraysubmission=array();
	$arrayassessment=array();
	$arraymaxsubmission=array();
	$arraymaxassessment=array();
	
	
	foreach($datigrafico as $dato){
		
		if(array_key_exists($stdid,$dato["Results"])){
			$arraysubmission[]=nulltozero($dato["Results"][$stdid]["SubmissionGrade"]);
			$arrayassessment[]=nulltozero($dato["Results"][$stdid]["AssessmentGrade"]);
		}
		$arraymaxsubmission[]=max($dato["Submissions"]);
		$arraymaxassessment[]=max($dato["Assessments"]);
	
	}
	
	
	
	$currentsubmissionmax=average($arraymaxsubmission);
	$currentassessmentmax=average($arraymaxassessment);
	$currentsubmissiongrade=average($arraysubmission);
    $currentassessmentgrade=average($arrayassessment);
	
	
	/*Creo la legenda per questo grafico*/
	$legendasubmission="All sessions ".$studenti[$stdid]->studentlastname." ". $studenti[$stdid]->studentfirstname." average submission grade";
	$legendamaxsubmission="All sessions average max submission grade";
	$legendaassessment="All sessions ".$studenti[$stdid]->studentlastname." ". $studenti[$stdid]->studentfirstname." average assessment grade";
	$legendamaxassessment="All sessions average max assessment grade";	
	//$legentavotoprecedente=$prevsession["SessionName"]. " average grade";
	//$legendamaxprecedente=$prevsession["SessionName"]." max grade";
	
	
	
}elseif($ssid==0 && $stdid==0){//tutte le sessioni, tutti gli studenti
	
	//calcolo voto medio di tutte le sessioni
	//calcolo media dei voti di tutte le sessioni
	
	//la sessione non ha precedenti
	$previussubmissiongrade=-1;
	$previusasssessmentgrade=-1;
	
	$arraysubmission=array();
	$arrayassessment=array();
	$arraymaxsubmission=array();
	$arraymaxassessment=array();
	
	
	foreach($datigrafico as $dato){
		
		$arraysubmission=array_merge($arraysubmission,$dato["Submissions"]);
		$arrayassessment=array_merge($arrayassessment,$dato["Assessments"]);
		$arraymaxsubmission[]=max($dato["Submissions"]);
		$arraymaxassessment[]=max($dato["Assessments"]);
	
	}
	
	
	$currentsubmissionmax=average($arraymaxsubmission);
	$currentassessmentmax=average($arraymaxassessment);
	$currentsubmissiongrade=average($arraysubmission);
    $currentassessmentgrade=average($arrayassessment);
	
	/*Creo la legenda per questo grafico*/
	$legendasubmission="All sessions average submission grade";
	$legendamaxsubmission="All sessions average max submission grade";
	$legendaassessment="All sessions average assessment grade";
	$legendamaxassessment="All sessions average max assessment grade";	
	
} 

?><br /><br /><br />
<div style="width: 100%;">
     <div id="grafico" style="width:800px;margin:0 auto;">
		<div id="graficointerno" style="width:500px;margin:0 auto;">
		<p>You can click on the legend to get an explanation of the graph</p>

		    <div id="legenda" class="popup" onclick="myFunction()">
			<span class="popuptext" id="myPopup" style="font-weight: normal;">This type of report highlights grade variations between one session and another. Additionally, it shows your exact level and how far this is from the best or top-performing students in the class (similarly, from the worst and the average grade)</span>
			<div class="popup" ><span class="quadratolegenda blu"></span> <?php echo $legendasubmission; ?><br />
			<span class="quadratolegenda bluchiaro"></span> <?php echo $legendamaxsubmission; ?></div>
			<div class="popup"><span class="quadratolegenda azzurro"></span> <?php echo $legendaassessment; ?>
			<br />
			<span class="quadratolegenda azzurrochiaro"></span> <?php echo $legendamaxassessment; ?></div>
			<?php if($previussubmissiongrade!=-1){ ?>
			<div class="popup"><span class="quadratolegenda grigio"></span> <?php echo $legentavotoprecedente; ?>
			<br />
			 <span class="quadratolegenda grigiochiaro"></span> <?php echo $legendamaxprecedente; ?></div>
			</div><?php } ?>
			<br /><br /><br />
			<?php if($hapartecipato){?>
			<div class="altezzabarra"></div>
			<div class="sessione">
			<div class="nomebarra"><span>Current Submission grade</span></div>
			<div id="currentsubmissionmax"  class="barra" style="width:<?php echo $currentsubmissionmax;?>%;">
			<span class="valoremax"><?php echo $currentsubmissionmax;?></span></div>
			<div id="currentsubmissiongrade"  class="barra" style="width:<?php echo $currentsubmissiongrade;?>%;">
			<span class="valore"><?php echo $currentsubmissiongrade;?></span></div>			
			</div>
			
			<?php if($previussubmissiongrade!=-1){ ?>
			<div class="altezzabarra"></div>
			<div class="sessione">
			<div class="nomebarra"><span>Previus</span></div>
			<div id="previussubmissionmax"  class="barraprev" style="width:<?php echo $previussubmissionmax;?>%;">
			<span class="valoremax"><?php echo $previussubmissionmax;?></span></div>
			<div id="previussubmissiongrade"  class="barraprev" style="width:<?php echo $previussubmissiongrade;?>%;">
			<span class="valore"><?php echo $previussubmissiongrade;?></span></div>			
			</div>
			<?php }else{ ?>
			<div>Cannot find previus session.</div>
			<?php } ?>
			
			<div class="altezzamezzabarra"></div>
			<div class="altezzabarra"></div>
			<div class="sessione">
			<div class="nomebarra"><span>Current Assessment grade</span></div>
			<div id="currentassessmentmax"  class="barra" style="width:<?php echo $currentassessmentmax;?>%;">
			<span class="valoremax"><?php echo $currentassessmentmax;?></span></div>
			<div id="currentassessmentgrade"  class="barra" style="width:<?php echo $currentassessmentgrade;?>%;">
			<span class="valore"><?php echo $currentassessmentgrade;?></span></div>			
			</div>
			
			<?php 
			
			if($previusasssessmentgrade!=-1){ ?>
			<div class="altezzabarra"></div>
			<div class="sessione">
			<div class="nomebarra"><span>Previus</span></div>
			<div id="previusassessmentmax"  class="barraprev" style="width:<?php echo $previusassessmentmax;?>%;">
			<span class="valoremax"><?php echo $previusassessmentmax;?></span></div>
			<div id="previusassessmentgrade"  class="barraprev" style="width:<?php echo $previusassessmentgrade;?>%;">
			<span class="valore"><?php echo $previusassessmentgrade;?></span></div>			
			</div>
			<?php }else{ ?>
			<div>Cannot find previus session.</div>
			<?php } ?>
			
			<!-- Asse x  numerato da 0 a 100-->
			<div id="xasse">
			<span class="valoreasse zero">0</span>
			<span class="valoreasse dieci">10</span>
			<span class="valoreasse venti">20</span>
			<span class="valoreasse trenta">30</span>
			<span class="valoreasse quaranta">40</span>
			<span class="valoreasse cinquanta">50</span>
			<span class="valoreasse sessanta">60</span>
			<span class="valoreasse settanta">70</span>
			<span class="valoreasse ottanta">80</span>
			<span class="valoreasse novanta">90</span>
			<span class="valoreasse cento">100</span>
			</div>
			<?php 			}else{ ?>
The student did not participate in the selected advworker session.
			<?php
			} ?>
		</div>
	</div>
</div>

<?php }else{ ?>
<p class="messaggio-grafico">Please select an existing session or student!</p>
<?php } ?>
<br />

<br />
<br />
<div>
<a href="<?php echo $linkreport; ?>">Go Back</a>
</div>
</div>
</div>
<script>
// When the user clicks on div, open the popup
function myFunction() {
  var popup = document.getElementById("myPopup");
  popup.classList.toggle("show");
}
</script>

<?php
echo $OUTPUT->footer();
die();


/*
 *     Funzioni per ricavare i dati per la costruzione del grafico
 * 
 * */
//funzione che calcola la media di un array e arrotonda a intero
function average($array) {
  $n =  array_sum($array) / count($array);  
  return number_format($n,0);
}

//funzione che trasforma null in 0
function nulltozero($x){
	
	if($x==NULL)	
		return 0;
	else
		return $x;
}
?>