<?php

$chartdata = [];
$style = "width: 100%;
                  border: 1px solid;
                  color: black;
                  background-color: white;
                  text-align: center;
                  text-decoration: none;
                  display: inline-block;
                  font-size: 16px;
                  margin: 4px 2px;
                  transition-duration: 0.4s;
                  cursor: pointer;";

/**
 * Get the data for the chart.
 * The chart data contains the average submissions grades for all the sessions
 *
 * @param $averagesubmissionsgrades             The average submissions grades for all the sessions
 * @return array                                Chart data
 */
function getchartdata($averagesubmissionsgrades) {
    $data = [];
    foreach ($averagesubmissionsgrades as $sessionname => $submissiongradeaverage) {
        $pointvalue = [];
        $pointvalue[] = $sessionname;
        $pointvalue[] = $submissiongradeaverage * 100;

        $data[] = $pointvalue;
    }

    return $data;
}

$chartdata = getchartdata($averagesubmissionsgrades);

?>


<script type="text/javascript">
    if (typeof jQuery == 'undefined') {
        var script = document.createElement('script');
        script.type = "text/javascript";
        script.src = "https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js";
        document.getElementsByTagName('head')[0].appendChild(script);
    }
</script>

<!-- google charts -->
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

<script type="text/javascript">
    google.charts.load('current', {packages: ['corechart', 'line']});
    google.charts.setOnLoadCallback(displayStudentModelHistoricalEvolution);

    function drawAverageSubmissionsGradesHistoricalEvolutionChart(elementId, values) {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Session');
        data.addColumn('number', 'Average submission grade');

        data.addRows(values);

        var options = {
            hAxis: {
                title: 'advwork session'
            },
            vAxis: {
                title: 'Average submission grade',
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
        <?php   $chartname = "average_submissions_grades_historical_evolution_chart";
        echo '$("#average_grades_historical_charts").append("<div id=\''. $chartname.'\'></div><br>");';
        echo 'drawAverageSubmissionsGradesHistoricalEvolutionChart("'. $chartname .'", ' . json_encode($chartdata, JSON_NUMERIC_CHECK) . ');';
        ?>

    }

</script>
<table>
    <tr style="width: 100%">
        <td style="width: 80%">
            <div class="moodle-header">Average submissions' grades for the sessions</div>
        </td>
        <td style="width: 20%">
            <input type="button" style=<?php echo "\"$style\"";?> value=<?php echo "\"".get_string('delete_empty_advwork', 'advwork')."\"";?> onClick="mostraConferma()">
        </td>
    </tr>
</table>

<div id="average_grades_historical_charts">
</div>

<script>
    function mostraConferma(){
        var r = confirm(<?php echo "\"".get_string('delete_empty_advwork_confirmation', 'advwork')."\"";?>);
        if (r == true) {
            url = "<?php echo $advwork->general_student_model_url_teacher($_GET['id']); ?>";
            var param = {delete_empty_session: "yes"};
            post(url, param);
        }else{
            alert(<?php echo "\"".get_string('delete_empty_advwork_denied_message', 'advwork')."\"";?>);
        }
    }
    function post(path, params, method='post') {

        const form = document.createElement('form');
        form.method = method;
        form.action = path;

        for (const key in params) {
            if (params.hasOwnProperty(key)) {
                const hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = key;
                hiddenField.value = params[key];

                form.appendChild(hiddenField);
            }
        }

        document.body.appendChild(form);
        form.submit();
    }

</script>