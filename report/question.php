<?php

/* created by gabriele trasolini */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once(dirname(dirname(__FILE__)).'/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

/**
 * 
 * Contestualizzazione pagina nella sessione selezionata dall'utente
 * 
 */

$id         = optional_param('cid', 0, PARAM_INT); // course_module ID, or
$wid         = optional_param('wid', 0, PARAM_INT); // advwork ID
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
if ($id) {
    $cm             = get_coursemodule_from_id('advwork', $wid, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
    $advworkrecord = $DB->get_record('advwork', array('id' => $cm->instance), '*', MUST_EXIST);

}
require_login($course, true, $cm);
require_capability('mod/advwork:view', $PAGE->context);
$advwork = new advwork($advworkrecord, $cm, $course);

$linkreport="./index.php?cid=$id&wid=$wid";

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
$studenti = $DB->get_records_sql($sqlstudenti);

$docente=false;
	//individuo il tipo di utente basandomi sulla capability
 	if (has_capability('mod/advwork:viewallassessments', $PAGE->context)) {
		$docente=true;
	}else{
		$docente=false;//studente
	}
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
$nomestud=$studenti[$stdid]->studentlastname. " ".$studenti[$stdid]->studentfirstname;
$emailstud=$studenti[$stdid]->studentemail;

function create_DOM($percorso){
    $xmlString = "";
    foreach ( file($percorso) as $node ) {
        $xmlString .= trim($node);
    }
    $doc = new DOMDocument();
    if (!$doc->loadXML($xmlString)) {
        die ("Errore\n");
      }
    return $doc;
}
$doc_qst = create_DOM(__DIR__ . "/answer.xml");
$doc_qst->validate();
$answers = $doc_qst->getElementsByTagName("answer");





echo $OUTPUT->header();

?>
<?php echo '<?xml version="1.0" encoding="UTF-8"?>';?>
<!DOCTYPE html
PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<style>
    .form {
    margin-right: 150px;
    margin-bottom: 150px;
}
/* The container */
.container {
  display: block;
  position: relative;
  padding-left: 35px;
  margin-bottom: 12px;
  cursor: pointer;
  font-size: 22px;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
}

/* Hide the browser's default checkbox */
.container input {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  height: 0;
  width: 0;
}

/* Create a custom checkbox */
.checkmark {
  position: absolute;
  top: 0;
  left: 0;
  height: 25px;
  width: 25px;
  background-color: #ddd;
}

/* On mouse-over, add a grey background color */
.container:hover input ~ .checkmark {
  background-color: #ccc;
}

/* When the checkbox is checked, add a grey background */
.container input:checked ~ .checkmark {
  background-color: grey;
}

/* Create the checkmark/indicator (hidden when not checked) */
.checkmark:after {
  content: "";
  position: absolute;
  display: none;
}

/* Show the checkmark when checked */
.container input:checked ~ .checkmark:after {
  display:block;
}

/* Style the checkmark/indicator */
.container .checkmark:after {
  left: 9px;
  top: 5px;
  width: 5px;
  height: 10px;
  border: solid white;
  border-width: 0 3px 3px 0;
  -webkit-transform: rotate(45deg);
  -ms-transform: rotate(45deg);
  transform: rotate(45deg);
}
.button {
  background-color:#ddd;
  border: none;
  color: black;
  padding: 5px 10px;
  text-decoration: none;
  display: inline-block;
  border-radius: 16px;
  border: transparent;
  box-shadow: 5px 5px 12px -6px #000000;
  cursor: pointer;
  font-size: 14px;
  border:1px solid;

}
textarea {
  width: 100%;
  height: 150px;
  padding: 12px 20px;
  box-sizing: border-box;
  border: 2px solid #ccc;
  border-radius: 4px;
  background-color: #f8f8f8;
  font-size: 16px;
  resize: none;
}
.table1 {
border-spacing: 0 10px;
font-family: 'Open Sans', sans-serif;
font-weight: bold;
margin-left: 150px;
margin-right: 10px;
margin-bottom: 50px;
width: 80%;

}
.table1 th {
padding: 10px 20px;
background:#ddd;
color: black;
border-bottom: 2px solid;
border-right: 2px solid;
border-top: 2px solid;
border-left: 2px solid; 
font-size: 0.9em;
font-weight: bold;
}
.table1 th:first-child {
text-align: left;
}
.table1 th:last-child {
text-align: center;
}
.table1 td {
vertical-align: middle;
padding: 10px;
font-size: 14px;
text-align: center;
border-top: 2px solid #56433D;
border-bottom: 2px solid #56433D;
border-right: 2px solid #56433D;
}
.table1 td:first-child {
border-left: 2px solid #56433D;

}
.table1 td:nth-child(2){
text-align: left;
}
.showth1 {
  display: inline-block;
}


</style>
<?php 
if(isset($_POST['send'])) {
  if ($stdid==0){
    echo '<div class="alert alert-danger alert-dismissible fade in">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <strong>You are a teacher!</strong> This topic is for students, you can not answer.
  </div>';

  }
  else { 
  $value = $_POST['check'];
  $motivation = htmlspecialchars($_POST['expl']) ;
  foreach($answers as $answer) {
      if(strcasecmp($answer->getAttribute("email"), $emailstud) == 0){
        $count++;
        echo '<script> alert("You have already answered the question");
        history.go(-1);      
        </script>';
        exit();      
      }
      
    }
  
      $nuova_ans = $doc_qst->createElement("answer");
      $nuova_ans->setAttribute("id", uniqid("a", false));
      $nuova_ans->setAttribute("name", $nomestud);
      $nuova_ans->setAttribute("email", $emailstud);
      $nuova_ans->setAttribute("value", $value);
      $nuova_ans->setAttribute("motivation", htmlspecialchars_decode($motivation));
      $root_ans = $doc_qst->documentElement;
      $root_ans->appendChild($nuova_ans);
      $path2 = __DIR__ . "/answer.xml";
      
        
      $doc_qst->save($path2);
      echo '<div class="alert alert-success">
      <strong>Great!</strong> Thank you for contributing with your opinion.
      </div>';
  ?> <a style="margin-left: 45%;" href="<?php echo $linkreport; ?>">Go Back</a>
    
  
<?php 
}
}
if (isset($_POST['show'])){
  ?> 
  <a style="margin-bottom: 20px;" href="<?php echo $linkreport; ?>">Go Back</a>
  <div style="position: relative; background-color:white;">
  <table class="table1">
  <tr>
      <th><strong>Name</strong></th>
      <th><strong>Email</strong></th>
      <th><strong>Value</strong></th>
      <th><strong>Motivation</strong></th>
  </tr>
  <?php 
  foreach ($answers as $answer){  
    $id_ans = $answer->getAttribute("id");
    $name_ans = $answer->getAttribute("name");
    $email_ans = $answer->getAttribute("email");
    $value_ans = $answer->getAttribute("value");
    $motiv_ans = $answer->getAttribute("motivation");
  ?>
  <tr>
            <td><?php echo $name_ans; ?></td>
            <td><?php echo $email_ans; ?></td>
            <td><?php echo $value_ans; ?></td>
            <td><?php echo $motiv_ans; ?></td>
            </td>
        </tr>
  <?php 
  }
  ?> </table>
  </div>
<?php
}
?>
<?php if ($stdid==0) {
  if(isset($_POST['show'])){
    echo ' <form method="POST" action="" style="display:none;">
    <h3 class="showth1" >Show results of the question..</h3>
    <button type="submit" class="button" name="show" ><strong>Show</strong></button>
    </form>';
    
  }else {
    echo ' <form method="POST" action="">
    <h3 class="showth1">Show results of the question..</h3>
    <button type="submit" class="button" name="show"><strong>Show</strong></button>
    </form>';
    
  
  }
}
else echo '<h3>Help us giving your opinion..</h3>';
  ?>


 


<form action="" method="POST" >
<input type="hidden" name="cid" value="<?php echo $id;?>">
<input type="hidden" name="wid" value="<?php echo $wid;?>">
  <div class="container2">
      
    <h3 style="font-weight: lighter;">Student feedback in a peer-evaluation environment is fundamental. It helps provide diverse perspectives, fosters personal growth, and can enhance peer-to-peer learning.</h3>
    <h3 style="font-weight: lighter;">In these sessions, the peer evaluation rating range was set from 1 to 100.</h3>
    <h3 style="font-weight: lighter;">Do you think this is a correct range or would you perhaps prefer a narrower range, such as from 1 to 10?</h3>
    <hr>
    <p>Select at most one:</p>
  </div>
<label class="container">1 to 100
  <input type="radio" name="check" value="1 to 100" required>
  <span class="checkmark"></span>
</label>
<label class="container">1 to 10
  <input type="radio" name="check" value="1 to 10">
  <span class="checkmark"></span>
</label>
<label class="container">Other
  <input type="radio" name="check" value="other">
  <span class="checkmark"></span>
</label>
    
</br>

    <label><h3 style="font-weight: lighter;">Motivation</h3></label></br>
    <textarea class="textarea" placeholder="Give us a reason for your answer.." name="expl" required></textarea>

    

    <button type="submit" class="button" name="send"><strong>Send</strong></button>
    <button type="reset" class="button"><strong>Cancel</strong></button>
  </div>
  

</form>
<a style="margin-left: 45%;" href="<?php echo $linkreport; ?>">Go Back</a>

<?php
echo $OUTPUT->footer();
die();
?>