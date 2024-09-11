<?php
/**
 * Created by PhpStorm.
 * User: Gabriel
 * Date: 5/23/2018
 * Time: 12:13 PM
 */

    /**
     * Displays a progress bar
     *
     * @param $progressbarclass                 Class of the progress bar
     * @param $progresspercentageclass          Class of the progress percentage bar
     * @param $progresspercentageid             Id of the progress percentage bar
     * @param $progressbartext                  Label of the progress bar
     * @param $percentage                       Percentage of the progress bar
     * @param $tooltip                          Message to be displayed when hovering over the progress bar
     */
    function display_relative_position_bars($progressbarclass, $progresspercentageclass, $progresspercentageid, $progressbartext, $percentage, $tooltip, $isgeneralstudentmodelpage) {
        echo '$("#progress-bars").append("<div class=\'bold-font\'>'. $progressbartext .'</div><div class=\''. $progressbarclass.'\'><div id=\''. $progresspercentageid . '\' class=\''. $progresspercentageclass . '\'></div></div><br>");';

        if(!$isgeneralstudentmodelpage) {
            echo '$("#'. $progresspercentageid .'").parent().attr(\'title\', "'. $tooltip .'");';
        }

        echo 'updateProgress("'. $progresspercentageid . '", ' . $percentage . ');';
        echo '$("div[id^=\'progress-percentage-previous-\']").addClass(\'striped-green-backgroud\');';
    }

    /**
     * Get the percentage of elements a specified value is bigger than other elements in the array
     *
     * @param $values               Array of values
     * @param $value                The value
     * @return float|int            Percentage
     */
    function get_value_relative_percentage($values, $value) {
        sort($values);

        $elementorder = 0;
        while($values[$elementorder] < $value) {
            $elementorder++;
        }

        $elementscount = count($values) - 1;

        if($elementorder == 0) {
            $percentage = 0;
        } else {
            $percentage = (($elementorder) / $elementscount) * 100;
        }

        return $percentage;
    }


    /**
     * Get the relative position of a student in a list of students models for a given capability. The position is calculated as a percentage of the peers the student overcomes.
     *
     * @param $capabilityname           Name of the capability
     * @param $models                   List with models of students.
     * @param $userid            Id of the student to calculate the relative position
     * @return float|int                Relative position as percentage
     */
    function get_student_relative_position($capabilityname, $models, $userid) {
        $capabilityvalues = [];
        $currentusercapabilityvalue = null;

        foreach ($models as $studentmodel) {
        	if(is_null($studentmodel)) {
            	$studentmodel = new stdClass();
                $studentmodel->entries = Array();
            }
            $capabilityentries = array_filter($studentmodel->entries, function($entry) use($capabilityname){
                return $entry->capability == $capabilityname;
            });

            $capabilityentry = reset($capabilityentries);
			
            $capabilityvalues[] = $capabilityentry->capabilityoverallvalue;
            
            if($studentmodel->userid == $userid) {
                $currentusercapabilityvalue = $capabilityentry->capabilityoverallvalue;
            }
        }
        $percentage = get_value_relative_percentage($capabilityvalues, $currentusercapabilityvalue);
        return $percentage;
    }

    foreach ($capabilities as $capability) {
        $capabilityname = $capability->name;
        if(is_null($percentages) || !isset($percentages)) {
        	$percentages = new stdClass();
        }
        
       	$percentages->$capabilityname = get_student_relative_position($capabilityname, $studentsmodels, $currentuserid);
        
        if(is_null($percentagesprevioussession) || !isset($percentagesprevioussession)) {
        	$percentagesprevioussession = new stdClass();
        }
        $percentagesprevioussession->$capabilityname = get_student_relative_position($capabilityname, $studentsmodelsprevioussession, $currentuserid);
        	
		if($capabilityname == 'C' && $isgeneralstudentmodelpage) {
            $percentages->$capabilityname = get_value_relative_percentage($averagesubmissiongradesallusers, $currentuseraveragegrade);
        }
        
    }
    
?>

<div class="moodle-header">Your position relative to other peers in the class</div>
<!-- progress bar -->
<div id="progress-bars">
    <!--
    <div id="progress-bar">
        <div id="progress-percentage"></div>
    </div> -->
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

<script type="text/javascript">
    function updateProgress(elementId, percentage) {
        var elem = document.getElementById(elementId);
        var width = 0;
        var id = setInterval(frame, 10);
        function frame() {
            if (width >= percentage) {
                clearInterval(id);
            } else {
                width++;
                elem.style.width = width + '%';
            }
        }
    }

    function createProgressBars() {
        <?php foreach ($capabilities as $capability) {
                    $capabilityname = $capability->name;
                    $capabilitynametodisplay = $capabilityname;
                    switch ($capabilitynametodisplay) {
                        case 'K':
                            $capabilitynametodisplay = "competence";
                            break;
                        case 'J':
                            $capabilitynametodisplay = "assessment capability";
                            break;
                        case 'C':
                            $capabilitynametodisplay = "submission grade";
                            break;
                    }

                    $percentage = $percentages->$capabilityname;

                    if($isgeneralstudentmodelpage && $capabilityname == 'C') {
                        $progressbartext = get_string('competenceaveragecprogresslabel', 'advwork', ['competence' => $capabilitynametodisplay, 'percentage' => (int)$percentage]);
                    } else if ($isgeneralstudentmodelpage) {
                        $progressbartext = get_string('competenceoverallcumulatedprogresslabel', 'advwork', ['competence' => $capabilitynametodisplay, 'percentage' => (int)$percentage]);
                    } else {
                        $progressbartext = get_string('competenceprogresslabel', 'advwork', ['competence' => $capabilitynametodisplay, 'percentage' => (int)$percentage]);
                    }

                    display_relative_position_bars("progress-bar", "progress-percentage", "progress-percentage-" . $capabilityname,
                            $progressbartext, $percentage, "The position of your ". $capabilitynametodisplay . " according to the current session.", $isgeneralstudentmodelpage);

                    if(!$isgeneralstudentmodelpage) {
                        $percentageprevioussession = $percentagesprevioussession->$capabilityname;
                        display_relative_position_bars("progress-bar-previous", "progress-percentage-previous", "progress-percentage-previous-" . $capabilityname,
                            "", $percentageprevioussession, "The position of your ". $capabilitynametodisplay . " according to the previous session.", $isgeneralstudentmodelpage);
                    }
                }
            ?>
    }

    $(document).ready(function() {
        createProgressBars();
    });

</script>