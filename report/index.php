<?php 
/*
 * Pagina principale relativa ai report della sessione OpenAnwer
 * 
 * 
 * Created by: Bruno Marino
 * 
 * */
 
 
 /*
  *     Librerie e file necessari
  * 
  * */
  
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

if ($id) {
    $cm             = get_coursemodule_from_id('advwork', $wid, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
}
 
require_login($course, true, $cm);
require_capability('mod/advwork:view', $PAGE->context);

/*
 *      URL dei tipi di report
 * */

$linkbox="./box-plot.php?cid=$id&wid=$wid";
$linkline="./line-chart.php?cid=$id&wid=$wid&showk=1&showj=1";
$linkbar="./bar-chart.php?cid=$id&wid=$wid";
$linktab="./tab-report.php?cid=$id&wid=$wid";
$linkquestion="./question.php?cid=$id&wid=$wid";

echo $OUTPUT->header();
?>
<style>
.report-box{
	display:inline-block;
	vertical-align:middle;
	margin:20px;
	text-decoration:none;
	cursor:pointer;
	border:1px solid;
	}
</style>
<div>
<p style="text-align: center; font-weight:bold;">Do you want to <a href="<?php echo $linkquestion; ?>">Help us</a> improve the sessions first? Give us your opinion</p>
<h2 style="text-align: center;">Reports</h2>
<p style="text-align: center;">This is a set of charts with which you can view your results and compare your assessments with those of other students</p>
<div style="text-align:center">
<a class="report-box" href="<?php echo $linkbox; ?>">
<img src="./img/box-plot.jpeg" width="331" height="242" />
<p>Box plot</p>
</a>
<a class="report-box" href="<?php echo $linkline; ?>">
<img src="./img/line-chart.jpeg" width="331" height="242" />
<p>Line chart</p>
</a>
<a class="report-box" href="<?php echo $linkbar; ?>">
<img src="./img/bar-chart.jpeg" width="331" height="242" />
<p>Overlapping bar chart</p>
</a>
<a class="report-box" href="<?php echo $linktab; ?>">
<img src="./img/tabular-report.jpg" width="331" height="242" />
<p>Tabular report</p>
</a>
</div>
</div>
<?php
echo $OUTPUT->footer();
die();
?>
   