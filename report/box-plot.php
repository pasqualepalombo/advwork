<?php 
/*
 * Pagina report box-plot
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

$advwork = new advwork($advworkrecord, $cm, $course);

$linkreport="./index.php?cid=$id&wid=$wid";

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

// ottieni l'indice corrispondente alla sessione di advwork attualmente visualizzata
if ($ssid>0) {
    $ris = $DB->get_records_sql("SELECT id FROM mdl_advwork WHERE course = $course->id ORDER BY id");
    $indexGrades= 0;
    foreach ($ris as $val) {
        if ($val->id == $ssid) {
            break;
        }
        $indexGrades++;
    }
}
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
		
	if($ssid==0){ // report senza sessioni selezionate
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
	}else{ //report con sessione selezionata

			foreach($sessioni as $sessione){
				if($sessione->advworkid==$ssid){

			$datigrafico[$sessione->advworkid]["SessionName"]=$sessione->advworksession;
			$i=0;
			foreach($records as $record){
				if($record->advworkid==$sessione->advworkid){
                    //risultati accoppiati con id =studentid
                    $datigrafico[$sessione->advworkid]["Results"][$record->studentid]=array("SubmissionGrade"=>nulltozero($advwork->get_student_grades($course->id, $record->studentid)[getIndexbyssid($ssid)]*100),"AssessmentGrade"=>nulltozero($advwork->get_j($course->id, $record->studentid)*100));
                    //risultati disaccoppiati
                    $datigrafico[$sessione->advworkid]["Submissions"][$i]=nulltozero($advwork->get_student_grades($course->id, $record->studentid)[getIndexbyssid($ssid)]*100);
                    $datigrafico[$sessione->advworkid]["Assessments"][$i]=nulltozero($advwork->get_j($course->id, $record->studentid)*100);
					
					$i++;
				}
				
			}//fine foreach interno
			//ordino i voti per Submissions
				sort($datigrafico[$sessione->advworkid]["Submissions"]);
				//ordino i voti per Assessments
				sort($datigrafico[$sessione->advworkid]["Assessments"]);
			break;
			}//fine if
		}//fine foreach esterno
		
	}
	//print_object($datigrafico);
	
echo $OUTPUT->header();
?>

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
  margin-top: 20px;
  border:1px solid;
}



.box select option {
  padding: 30px;
}
</style>

<div>
<h2 style="text-align: center;">Box plot</h2>

<div style="text-align:center">
<form>
<input type="hidden" name="cid" value="<?php echo $id;?>">
<input type="hidden" name="wid" value="<?php echo $wid;?>">
<div class="box" style="display: inline-block;">
<strong >Select advworker session</strong> 
<br />
<select id="sessione" name="ssid" class="select">
<option value="<?php echo $idsessione;?>"><?php echo $nomesessione;?></option>
<?php foreach($sessioni as $sessione){?>
<option <?php if($sessione->advworkid==$ssid) echo "selected";?> value="<?php echo $sessione->advworkid;?>"><?php echo  $sessione->advworksession;?></option>
<?php }?>
</select>
<div class="select-box">
 <button type="submit" class= "button"><strong>Report</strong></button> 
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
			if($ssid==0){
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
<?php if(count($datigrafico)>0){ ?>
<div id="chart_div" style="width: 100%; height: 500px;"></div>
<?php }else{ ?>
<p class="messaggio-grafico">Please select an existing session or student!</p>
<?php } ?>
<br />
<div>
<h3>How to read the graph</h3>
<p>The graph on this page is called Box Plot,
 it is used to show the distribution of a dataset. In this case, votes distribution over advworker sessions. 
 The data in blue represent the trend of the votes for the submission, 
 while the data in light blue represent the trend of the votes for the peer evaluations. 
  The orange dots represent the student's exact mark. 
  Below there is an infographic to help you better understand the graph.
 </p>
 <img src="./img/box-plot-illustration.png" width="750" height="326" />
</div>
<br />
<br />
<div>
<a href="<?php echo $linkreport; ?>">Go Back</a>
</div>
</div>
</div>



<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
      google.charts.load('current', {'packages':['corechart']});
      google.charts.setOnLoadCallback(drawChart);

function drawChart() {
    
    var data = new google.visualization.DataTable();
    data.addColumn('string', 'x');
    data.addColumn('number', 'Grade for submission');
    data.addColumn({id:'min', type:'number', role:'interval'});
    data.addColumn({id:'q1', type:'number', role:'interval'});
    data.addColumn({id:'median', type:'number', role:'interval'});
	data.addColumn({id:'q3', type:'number', role:'interval'});
	data.addColumn({id:'max', type:'number', role:'interval'});
<?php 
	//se ho id studente !=0, mostro il voto dello studente sul grafico come pallino
if($stdid!=0){?>
	data.addColumn({id:'student', type:'number', role:'interval'});//for student selected
<?php }?>	
    data.addColumn('number', 'Grade for assessment');
    data.addColumn({id:'min', type:'number', role:'interval'});
    data.addColumn({id:'q1', type:'number', role:'interval'});
    data.addColumn({id:'median', type:'number', role:'interval'});
	data.addColumn({id:'q3', type:'number', role:'interval'});
	data.addColumn({id:'max', type:'number', role:'interval'});
<?php 
	//se ho id studente !=0, mostro il voto dello studente sul grafico come pallino
if($stdid!=0){?>
    data.addColumn({id:'student', type:'number', role:'interval'});//for student selected
<?php }?>

    data.addRows([
	
	<?php 
	
	$primo=1;
	foreach($datigrafico as $dato){
		
		$nomessione=$dato["SessionName"];
		
		// dati relativi le submissions
		$max_submission=max($dato["Submissions"]);
		$min_submission=min($dato["Submissions"]);
		$q1_submission=quartile_25($dato["Submissions"]);
		$mediana_submission=mediana($dato["Submissions"]);
		$q3_submission=quartile_75($dato["Submissions"]);
		
		//dati relativi agli assessments
		$max_assessment=max($dato["Assessments"]);
		$min_assessment=min($dato["Assessments"]);
		$q1_assessment=quartile_25($dato["Assessments"]);
		$mediana_assessment=mediana($dato["Assessments"]);
		$q3_assessment=quartile_75($dato["Assessments"]);
		
		if(!$primo)
			echo ",";
		else
			$primo=0;
			
		// Se sono uno studente o se il docente ha selezionato uno studente,
		// evidenzio il suo dato sul grafico
		
		$votosubmission="";
		$votoassessment="";
		if($stdid!=0){
			
			//controllo se lo studente ha partecipato alla sessione
			if(array_key_exists($stdid,$dato["Results"])){
			   $votosubmission=",".$dato["Results"][$stdid]["SubmissionGrade"];
			   $votoassessment=",".$dato["Results"][$stdid]["AssessmentGrade"];
			}else{ //studente non ha partecipato metto il pallino pari a null, quindi non appare
				$votosubmission=", null";
				$votoassessment=", null";
			}
			
		}
		
		//box plot submission
		$rigadati= "['$nomessione',$mediana_submission,$min_submission,$q1_submission";
		$rigadati.= ",$mediana_submission,$q3_submission,$max_submission $votosubmission";
		//fine box plot submission
		//box plot assessment
		$rigadati.= ",$mediana_assessment,$min_assessment,$q1_assessment";
		$rigadati.= ",$mediana_assessment,$q3_assessment,$max_assessment $votoassessment]";
		//fine box plot assessment
		
		echo $rigadati;
	} 
	
	
	?>
					]);
//[nomesession,
    //altezzamassima,minimo,minimoq1,mediana,massimoq3,massimo, (voto stud, fac)


  var options = {
    title: 'Sessions results',
    //width: 930,
    hAxis: {title: 'Sessions'},
    vAxis: {minValue: 0, maxValue: 100},
    intervals: { 'lineWidth':2, 'boxWidth': .8, style: 'boxes',fillOpacity: .5 },
	interval: {
            min: {
              style: 'bars',
              fillOpacity: 1             
            },
            max: {
              style: 'bars',
              fillOpacity: 1              
            }
			<?php 
			//se ho id studente mostro i suoi voti sul grafico come pallini
			//quindi formatto il campo "student" con lo style:"points"
			if($stdid!=0){?>,
			student: {
              style: 'points',
			  color: 'orange',
              fillOpacity: 1 ,
            
            }
			  <?php }?>
			
          },

    colors:['#191970','#1E90FF'],
    dataOpacity: 0
  };

  var chart = new google.visualization.ColumnChart(document.getElementById('chart_div'));

  chart.draw(data, options);

}

</script>

<?php
echo $OUTPUT->footer();
die();


/*
 *     Funzioni per ricavare i dati per la costruzione del grafico
 * 
 * */
//funzione che calcola la mediana (=q2)
function mediana($array) {
  return quartile_50($array);
}
//funzione che calcola il primo quartile q1 
function quartile_25($array) {
  return quartile($array, 0.25);
}
//calcolo q2 
function quartile_50($array) {
  return quartile($array, 0.5);
}
//funzione che calcola ultimo quartile q3 
function quartile_75($array) {
  return quartile($array, 0.75);
}

//funzione che eseque il calcolo del quartile 
function quartile($array, $quartile) {
  
  //posizione dell'elemento
  $pos = (count($array) - 1) * $quartile;
 
  //floor arrotonda per difetto all'intero piu' vicino
  $base = floor($pos);
  //resto fra posizione e base (sara' 0 o 0.5)
  $rest = $pos - $base;
 
  $result=0;
  //prendo il valore del quartile
  if( isset($array[$base+1]) ) {
    $result= $array[$base] + $rest * ($array[$base+1] - $array[$base]);
  } else {
    $result= $array[$base];
  }
  
  //ritorno il risultato arrotondato a intero
  return number_format ($result,0);
  
} 
//funzione che trasforma null in 0
function nulltozero($x){
	
	if($x==NULL)	
		return 0;
	else
		return $x;
}

?>