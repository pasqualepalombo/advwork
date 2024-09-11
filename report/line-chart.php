<?php 
/*
 * Pagina report line-chart
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
if(isset($_GET["stdid"])){
	$stdid = $_GET["stdid"];
}else{
	$stdid = -1;
}

//controllo se mostrare i valori della classe insieme a quelli dello studente
$showclass=true;
if(isset($_GET["voticlasse"])){
	$showclass = true;
}else{
	$showclass = false;
}


$showGradeSubmission=true;
if(isset($_GET["showGradeSubmission"])){
	$showGradeSubmission = true;
}else{
	$showGradeSubmission = false;
}

$showj=true;
if(isset($_GET["showj"])){
	$showj = true;
}else{
	$showj = false;
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

if($stdid==0){
	//se non si ha nessuno studente si mostrano sempre i dati della classe
	$showclass=true;
}
		
	$datigrafico=array();
	$studenteselezionato=array();
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
					//se e' stato selezionato uno studente, 
					//se ha partecipato alla sessione lo inserisco in array (poi usero' l'array per il grafico studente)
					if($stdid!=0){
						if($stdid==$record->studentid){
							$studenteselezionato[$sessione->advworkid]["id"]=$record->studentid;
							$studenteselezionato[$sessione->advworkid]["SubmissionGrade"]=$advwork->get_student_grades($course->id, $record->studentid)[getIndexbyssid($sessione->advworkid)]*100;
							$studenteselezionato[$sessione->advworkid]["AssessmentGrade"]=$advwork->get_j($course->id, $record->studentid)*100;
						}
					}
					$i++;
				}
			}
		}
	
	
echo $OUTPUT->header();
?>
<style>
.select-box{
	display:inline-block;
	margin:20px;
	}
.select {
	width: 230px;
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
}
</style>
<div>
<h2 style="text-align: center;">Line chart </h2> 
<div style="text-align:center">
<form>
<input type="hidden" name="cid" value="<?php echo $id;?>">
<input type="hidden" name="wid" value="<?php echo $wid;?>">
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
<div class="select-box">
<input type="checkbox" name="showGradeSubmission" value="1" <?php if($showGradeSubmission) echo "checked";?> ><strong>Show grade for submission</strong> &nbsp;&nbsp;
<input type="checkbox" name="showj" value="1" <?php if($showj) echo "checked";?> ><strong>Show grade for assessment (J)</strong> &nbsp;&nbsp;
<?php 
//Mostro la check box per selezionare se mostrare o meno andamento generale
if($stdid!=0){ ?> 
<input type="checkbox" name="voticlasse" value="ok" <?php if($showclass) echo "checked";?> ><strong>Show classroom votes</strong>
<?php }?>
<div class="select-box">
 <button type="submit" class="button"><strong>Report</strong></button> 
</div>
</div>

</form>
<?php if(count($datigrafico)>0){ 
  if($showGradeSubmission || $showj){ ?>
<div id="linechart_material" style="width: 100%; height: 500px;"></div>
<?php 
  }else{ ?>
<p class="messaggio-grafico">Please select at least one variable among K and J to be showed.</p>
<?php 
  }//fine else showGradeSubmission e showj entrambi false
}else{ ?>
  
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
<script src="https://www.gstatic.com/charts/loader.js"></script>
<script>
  google.charts.load('current', {packages: ['corechart', 'line']});
  google.charts.setOnLoadCallback(drawChart);

function drawChart() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', 'Session');
	
<?php 
//mostro i dati di tutta la classe solo se richiesto
if($showclass){?>

<?php 
//aggiungo i campi della parte di grafico relativa a k
if($showGradeSubmission){?>
    data.addColumn('number', 'Average grade for submission');
    data.addColumn({
      type: 'boolean',
      role: 'certainty'
    }); // certainty col.
	data.addColumn({id:'point', type:'number', role:'interval'});
<?php } ?>
<?php if($showj){?>
    data.addColumn('number', 'Average grade for assessment');
	    data.addColumn({
      type: 'boolean',
      role: 'certainty'
    }); // certainty col.
	data.addColumn({id:'point', type:'number', role:'interval'});
	<?php }//fine showj ?>
	
<?php }//fine $showclass ?>
	
<?php if($stdid!=0){ 
	$cognome=$studenti[$stdid]->studentlastname;
	$nome=$studenti[$stdid]->studentfirstname;
	?>
	<?php 
//aggiungo i campi della parte di grafico relativa a k
if($showGradeSubmission){ ?>
	data.addColumn('number', '<?php echo $cognome." ".$nome; ?> grade for submission');
    data.addColumn({
      type: 'boolean',
      role: 'certainty'
    }); // certainty col.
	data.addColumn({id:'pointgrade', type:'number', role:'interval'});
	<?php } ?>
<?php if($showj){ ?>
	data.addColumn('number', '<?php echo $cognome." ".$nome; ?> grade for assessment');
	    data.addColumn({
      type: 'boolean',
      role: 'certainty'
    }); // certainty col.
	data.addColumn({id:'pointgrade', type:'number', role:'interval'});
		<?php } ?>
<?php } ?>
	
    data.addRows([
	
	<?php 
	
			// Inizializzazione variabili per il voto studente
			$grade_sub_prev = "null";
			$grade_ass_prev = "null";
			
			$grade_sub = "null";
			$grade_ass = "null";
			$dot_sub = "null";
			$dot_ass = "null";
			
			$part_sub = "true";
			$part_ass = "true";
	
	$primo=1;
	$studentcount=0;//conto le sessioni studente
	foreach($datigrafico as $key => $dato){		
		
		$nomessione=$dato["SessionName"];
		$avgsubmission=average($dato["Submissions"]);
		$avgassessment=average($dato["Assessments"]);
		$dotsubmission=$avgsubmission;//senno null
		$dotassessment=$avgassessment;//senno null
		$partecipationsubmission="true";
		$partecipationassessment="true";
		
		if(!$primo)
			echo ",";
		else
			$primo=0;
			
		$rigastudente="";
		if($stdid!=0){// se e' selezionato uno studente mostro i suoi voti sul grafico
				
			$studentcount++;
			
			// Controllo se lo studente ha partecipato alla sessione allora
			if(array_key_exists($key,$studenteselezionato)){
				//punto presente inserito
				$grade_sub = nulltozero($studenteselezionato[$key]["SubmissionGrade"]);
				$grade_ass = nulltozero($studenteselezionato[$key]["AssessmentGrade"]);
				$dot_sub = $grade_sub;
				$dot_ass = $grade_ass;
				$part_sub = "true";
				$part_ass = "true";
				
				//ultimo punto presente inserito
				
				  $grade_sub_prev = $grade_sub;
				  $grade_ass_prev = $grade_ass;

				
			}else{// Lo studente non ha partecipato alla sessione
				//mantengo linea ad altezza ultimo voto
				//se lo studente ha partecipato ad altre sessioni
				if($studentcount <= $studenti[$stdid]->sessionsnumber && $grade_sub_prev !="null"){
					
					$grade_sub = $grade_sub_prev;
					$grade_ass = $grade_ass_prev;
					
					/* calcolo punto su retta in verticale
					 * come?
					   calcolo numero di step (sessioni alle quali o studente non ha partecipato)
                        che mancano per prossimo punto
						 * se voto precedente < voto successivo
					 * punto=(abs(ultimasessionepartecipata-prossimasessionepartecipata)/numerostep)+prossimasessionepartecipata
					 * 
					 * cerco dunque la prossima sessione a cui lo studente ha partecipato
					 */
					 
					$nstep=1;
					$ksucc=0;
					$jsucc=0;
					foreach($datigrafico as $k => $d){
						
						if($k>$key){
							$nstep++;
							
							if(array_key_exists($k,$studenteselezionato)){
								//a questa sessione lo studente ha partecipato
								
								$ksucc=nulltozero($studenteselezionato[$k]["SubmissionGrade"]);
								$jsucc=nulltozero($studenteselezionato[$k]["AssessmentGrade"]);
								
								break;
								
							}
							
							
						}
					}
					
					// Stima voti sessione non partecipata
					
					// Stima K
					if($grade_sub_prev<$ksucc)
					  $grade_sub = (abs($grade_sub_prev-$ksucc)/$nstep)+$grade_sub_prev;
					elseif($grade_sub_prev>$ksucc)
					  $grade_sub = $grade_sub_prev - (abs($grade_sub_prev-$ksucc)/$nstep);
					elseif($grade_sub_prev==$ksucc)
					  $grade_sub=$ksucc;
					  
					  
					// Stima J
					if($grade_ass_prev<$jsucc)
					  $grade_ass = (abs($grade_ass_prev-$jsucc)/$nstep)+$grade_ass_prev;
					elseif($grade_ass_prev>$jsucc)
					  $grade_ass = $grade_ass_prev - (abs($grade_ass_prev-$jsucc)/$nstep);
					elseif($grade_ass_prev==$jsucc)
					  $grade_sub==$jsucc;
					  
					
				}else{
					//senno niente tratteggio, lo studente non ha partecipato ad altre sessioni
					$grade_sub = "null";
					$grade_ass = "null";
					
				}
				//echo $studentcount."<br>";
				$part_sub = "false";
				$part_ass = "false";
				
				$dot_sub = "null";
				$dot_ass = "null";
				
			}
			$rigastudente=="";
			if($showGradeSubmission)
			   $rigastudente.=",$grade_sub,$part_sub,$dot_sub";
			   
			if($showj)   
			   $rigastudente.=",$grade_ass,$part_ass,$dot_ass";
		}
		$rigasessione="";
		
		if($showGradeSubmission)
			$rigasessione.=",$avgsubmission,$partecipationsubmission,$dotsubmission";
		if($showj) 
			$rigasessione.=",$avgassessment,$partecipationassessment,$dotassessment";
		


			if(!$showclass)
				$rigasessione="";
				
				
		$riga= "['$nomessione'".$rigasessione;
		$riga.=" $rigastudente]";
		
		echo $riga;
		
	}//fine foreach
	
	
			$colori="'#43459d', '#1c91c0', '#e2431e', '#f1ca3a'";
		
		
		//mostro i colori relativi a k e j se selezionati
		$c1="";
		$c2="";
		if($showGradeSubmission){
			$c1.="'#43459d'";
			$c2.=", '#e2431e'";
		}
		if($showj){
			if($c1=="")
				$c1.="'#1c91c0'";
			else
				$c1.=", '#1c91c0'";
				
			
				$c2.=",'#f1ca3a'";
	
		
		}
		
		$colori= $c1.$c2;
		
		if($showGradeSubmission && $showj){
		$serie="0: { lineWidth: 2 },
            1: { lineWidth: 2 },
            2: { lineWidth: 5 },
            3: { lineWidth: 5 },";
		}else{
		  $serie="0: { lineWidth: 2 },
            1: { lineWidth: 5 },";
		}
			
			if(!$showclass){
								
				 
				$colori="'#e2431e', '#f1ca3a'";
				
				
				$colori="";
				
				if($showGradeSubmission)
					$colori="'#e2431e'";
					
			
				if($showj){
					if($colori=="")
						$colori.="'#f1ca3a'";
					else
						$colori.=", '#f1ca3a'";
				}
				
				$serie="0: { lineWidth: 5 },
                        1: { lineWidth: 5 },";
			}

	?>

    ]);

    var options = {
	title: 'Sessions results',
    //width: 930,
    hAxis: {title: 'Sessions'},
    vAxis: {minValue: 0, maxValue: 100},
      chart: {
        title: 'Box Office Earnings in First Two Weeks of Opening',
        subtitle: 'in millions of dollars (USD)'
      },
	  curveType: 'function',
      height: 500,

      theme: 'material',
	  interval: {
            point: {
              style: 'points',
              fillOpacity: 1,
              pointSize: 10,

            },
				  <?php if($stdid!=0){ 
//se ho anche le righe dello studente, differenzio i punti
	?>
			  pointgrade: {
              style: 'points',
              fillOpacity: 1,
              pointSize: 20,

            },
	<?php } ?>
          },
	  colors: [<?php echo $colori; ?>],
	  <?php if($stdid!=0){ 
//se ho anche le righe dello studente, differenzio le righe
	?>
	  series: {
            <?php echo $serie; ?>
	  }
	  <?php } ?>

    };

    var chart = new google.visualization.LineChart(document.getElementById('linechart_material'));

    chart.draw(data, options);
  }


</script>
<?php
echo $OUTPUT->footer();
die();

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