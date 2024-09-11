<?php
    $models = $studentmodelsallsessions->studentmodels;

    $chartdata = [];

    /**
     * Get an array representing an empty session.
     * An empty session is a session that student did not attend and the student has 0 for each capability.
     *
     * @param $capabilities         Capabilities defined in the system
     * @param $sessionname          Name of the session the student did not attend
     * @return array                Empty session values
     */
    function getemptysessioncapabilityvalues($capabilities, $sessionname) {
        $emptysessioncapabilityvalues = [];
        $emptysessioncapabilityvalues[] = $sessionname;
        foreach ($capabilities as $capability) {
            $emptysessioncapabilityvalues[] = 0;
        }
        return $emptysessioncapabilityvalues;
    }

    /**
     * Get an array representing the capability values for a session.
     * The capability values are the grades received by the student for each capability.
     *
     * @param $sessionname         Name of the session
     * @param $capabilities        Capabilities defined in the system
     * @param $studentmodel        Model of the student that contains the grades for the capabilities
     * @return array               Session grades values
     */
    function getsessioncapabilityvalues($sessionname, $capabilities, $studentmodel) {
        $sessioncapabilityvalues = [];
        $sessioncapabilityvalues[] = $sessionname;
        foreach ($capabilities as $capability) {
            $capabilityname = $capability->name;
            $capabilityentries = array_filter($studentmodel->entries, function($entry) use($capabilityname){
                return $entry->capability == $capabilityname;
            });

            $capabilityentry = reset($capabilityentries);
            $capabilityvalue = $capabilityentry->capabilityoverallvalue;
            $sessioncapabilityvalues[] = $capabilityvalue;
        }

        return $sessioncapabilityvalues;
    }

    /**
     * Used to create the tooltips
     * The tooltip contains HTML content with the values for all the capabilities as a table
     *
     * @param $values               values of the capabilities
     * @param $capabilities         the capabilities
     * @return string               tooltip content as HTML
     */
    function gettooltip($values, $capabilities) {
        $index = 0;
        $tooltipcontent = "<table>";
        foreach ($values as $value) {
            // name of the session skip, it is not necessary to display it in the tooltip
            if($index == 0) {
                $index++;
                continue;
            }

            $tooltipcontent .= '<tr><td><b>'. array_values($capabilities)[$index-1]->name .':</b><td>'. ($value * 100).'</td></tr>';
            $index++;
        }
        $tooltipcontent .= "</table>";
        return $tooltipcontent;
    }

    /**
     * Used to create the chart data with the same tooltip for each line
     *
     * @param $sessioncapabilityvalues          values of the capabilities
     * @param $capabilities                     capabilities defined in the system
     * @return array                            chart data
     */
    function createchartdatawithtooltip($sessioncapabilityvalues, $capabilities) {
        $datawithtooltip = [];
        $index = 0;
        $tooltip = gettooltip($sessioncapabilityvalues, $capabilities);
        foreach ($sessioncapabilityvalues as $value) {
            if(is_numeric($value)) {
                $datawithtooltip[] = $value * 100;
            } else {
                $datawithtooltip[] = $value;
            }

            // name of the session, it does not have any tooltip, just add the value
            if($index == 0) {
                $index++;
                continue;
            }

            $datawithtooltip[] = $tooltip;
        }
        return $datawithtooltip;
    }

    /**
     * Get the data for the chart.
     * The chart data contains the session capability values for each session
     *
     * @param $coursesessions       All the sessions the student could have attended
     * @param $studentmodels        Models of the student for the course sessions
     * @param $capabilities         Capabilities defined in the system
     * @return array                Chart data
     */
    function getchartdata($coursesessions, $studentmodels, $capabilities) {
        $data = [];
        foreach ($coursesessions as $session) {
            $sessionid = $session->id;
            $studentmodelsessionentries = array_filter($studentmodels, function($model) use($sessionid){
                return $model->advworkid == $sessionid;
            });
            $studentmodel = reset($studentmodelsessionentries);

            // the student does not have a model for the session insert empty entry
            if(empty($studentmodel->entries)) {
                $sessioncapabilityvalues = getemptysessioncapabilityvalues($capabilities, $session->name);
                $data[] = createchartdatawithtooltip($sessioncapabilityvalues, $capabilities);
                continue;
            }

            $sessioncapabilityvalues = getsessioncapabilityvalues($session->name, $capabilities, $studentmodel);
            $data[] = createchartdatawithtooltip($sessioncapabilityvalues, $capabilities);
        }

        return $data;
    }

    $chartdata = getchartdata($coursesessions, $models, $capabilities);

    ?>
    
    <!-- google charts -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

    <script type="text/javascript">
        google.charts.load('current', {packages: ['corechart', 'line']});
        google.charts.setOnLoadCallback(displayStudentModelHistoricalEvolution);
    
        function drawHistoricalEvolutionChart(elementId, values) {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Session');

            <?php foreach ($capabilities as $capability) {
                    $capabilityname = $capability->name;
                    switch ($capabilityname) {
                        case 'K':
                            $capabilityname = "Competence";
                            break;
                        case 'J':
                            $capabilityname = "Assessment capability";
                            break;
                        case 'C':
                            $capabilityname = "Submission grade";
                            break;
                    }

                    echo 'data.addColumn("number", "' . $capabilityname . '");';
                    echo 'data.addColumn({type: "string", role: "tooltip", p: {"html": true}});';
                }
            ?>

         data.addRows(values);

         var options = {
             hAxis: {
                 title: 'advwork session'
             },
             vAxis: {
                 title: 'Metrics',
                 maxValue: 100,
                 minValue: 0
             },
             legend: {
             },
             backgroundColor: '#f1f8e9',
             tooltip: {
                 isHtml: true
             }
         };

         var chart = new google.visualization.LineChart(document.getElementById(elementId));
         chart.draw(data, options);
     }

     function displayStudentModelHistoricalEvolution() {
         <?php   $chartname = "historical_evolution_chart";
                 echo '$("#historical_charts").append("<div id=\''. $chartname.'\'></div><br>");';
                 echo 'drawHistoricalEvolutionChart("'. $chartname .'", ' . json_encode($chartdata, JSON_NUMERIC_CHECK) . ');';
         ?>

        }

    </script>
    
    <div class="moodle-header">Historical evolution of the student model in time based on competence</div>
    <div id="historical_charts">
    </div>
    




