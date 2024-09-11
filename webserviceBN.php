<?php
/**
 * Created by PhpStorm.
 * User: Gabriel
 * Date: 3/29/2018
 * Time: 12:24 PM
 */
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_reporting(E_WARNING);*/
class WebServiceBN {

    /**
     * Method used to call the BNS to get its parameters
     *
     * @return BNS parameters
     */
    function get_parameters() {
        $opts = array('http' =>
            array(
                'method'  => 'GET',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => ''
            )
        );

        $context = stream_context_create($opts);

        $json_parameters = file_get_contents("http://twiki.di.uniroma1.it/BNservice/v0", false, $context);
        $parameters = json_decode($json_parameters);
		
		/* DEBUG */
		var_dump($parameters);
		/* JSON SESSION DATA sono da inviare alla BN */
        $myfile1 = fopen("webserviceBN_parameters_from_bn.txt", "a") or die("Unable to open file!");
        fwrite($myfile1, $parameters);



        return $parameters;
    }

    /**
     * Sends the data about the sessions to the Bayesian Network Service to model the students
     *
     * @param    stdClass $sessiondata       the data about the sessions
     * @param    string $url                 the url to the Bayesian Network Service API
     * @return   string                      the models of the students represented as a json string
     */
    function post_session_data($sessiondata)
    {
        var_dump($sessiondata);
        $lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.';
        var_dump($lorem);
        /*
        return "{
             \"best\": {
                \"ID\": \"5\",
                \"grade\": \"E\",
                \"max\": 1.4372146485589374,
                \"value\": 0.6315427973846489
            },
            \"parameters\": {
                \"cptc\": \"Marco4\",
                \"cptg\": \"Gauss\",
                \"cptj\": \"Andrea\",
                \"cptk\": \"flat\",
                \"domain\": [
                    1,
                    0.95,
                    0.85,
                    0.75,
                    0.65,
                    0.55,
                    0
                ],
                \"mapping\": \"weightedSum\",
                \"strategy\": \"maxEntropy\",
                \"termination\": \"corrected30\"
            },
            \"status\": \"OK\",
            \"student-models\": {
                \"3\": {
                    \"C\": {
                        \"grade\": \"B\",
                        \"probs\": [
                            0,
                            1,
                            0,
                            0,
                            0,
                            0
                        ],
                        \"value\": 0.8999999999999999
                    },
                    \"J\": {
                        \"grade\": \"B\",
                        \"probs\": [
                            0.21409586760399946,
                            0.5243647210213739,
                            0.14549029614113265,
                            0.0654996587805747,
                            0.03785185946205463,
                            0.012697596990864833
                        ],
                        \"value\": 0.8691166727421648
                    },
                    \"K\": {
                        \"grade\": \"B\",
                        \"probs\": [
                            0.17675611171607958,
                            0.5237334790746059,
                            0.15726402109365162,
                            0.08987193556146866,
                            0.041997368558008515,
                            0.010377083996185718
                        ],
                        \"value\": 0.8604710310920286
                    }
                },
                \"4\": {
                    \"C\": {
                        \"grade\": \"F\",
                        \"probs\": [
                            0,
                            0,
                            0,
                            0,
                            0,
                            1
                        ],
                        \"value\": 0.275
                    },
                    \"J\": {
                        \"grade\": \"F\",
                        \"probs\": [
                            1.9524021168887684e-30,
                            1.5975895729119388e-7,
                            0.002129073377668882,
                            0.05292727131696311,
                            0.26023469592619586,
                            0.6847087996202148
                        ],
                        \"value\": 0.38318822985834755
                    },
                    \"K\": {
                        \"grade\": \"F\",
                        \"probs\": [
                            0.0011420850190938503,
                            0.005400474700096963,
                            0.008841196712097179,
                            0.021840368546956295,
                            0.055089406218868424,
                            0.9076864688028873
                        ],
                        \"value\": 0.31100259812836606
                    }
                },
                \"5\": {
                    \"C\": {
                        \"grade\": \"B\",
                        \"probs\": [
                            0,
                            1,
                            0,
                            0,
                            0,
                            0
                        ],
                        \"value\": 0.8999999999999999
                    },
                    \"J\": {
                        \"grade\": \"D\",
                        \"probs\": [
                            5.8347742267849915e-9,
                            0.04769669238283178,
                            0.2378207826111919,
                            0.30828082843059534,
                            0.28558338696275093,
                            0.12061830377785596
                        ],
                        \"value\": 0.6535003005403845
                    },
                    \"K\": {
                        \"grade\": \"C\",
                        \"probs\": [
                            0.07100362938923788,
                            0.4129963721563436,
                            0.14000634178271723,
                            0.1677559681071155,
                            0.14397278154131432,
                            0.06426490702327145
                        ],
                        \"value\": 0.7744160430525592
                    }
                },
                \"6\": {
                    \"C\": {
                        \"grade\": \"D\",
                        \"probs\": [
                            0,
                            0,
                            0,
                            1,
                            0,
                            0
                        ],
                        \"value\": 0.7
                    },
                    \"J\": {
                        \"grade\": \"D\",
                        \"probs\": [
                            4.9285504840435285e-8,
                            0.03998682632040302,
                            0.2932388534298321,
                            0.2848500369151107,
                            0.23856715194981074,
                            0.14335708209933876
                        ],
                        \"value\": 0.6525377890733776
                    },
                    \"K\": {
                        \"grade\": \"E\",
                        \"probs\": [
                            0.006572806930179418,
                            0.034233068113270634,
                            0.07088566540118986,
                            0.5323831476168704,
                            0.2517086746338208,
                            0.10421663730466883
                        ],
                        \"value\": 0.6462797637507062
                    }
                },
                \"7\": {
                    \"C\": {
                        \"grade\": \"C\",
                        \"probs\": [
                            0,
                            0,
                            1,
                            0,
                            0,
                            0
                        ],
                        \"value\": 0.8
                    },
                    \"J\": {
                        \"grade\": \"C\",
                        \"probs\": [
                            0.07925226449088665,
                            0.5125054428067132,
                            0.2241428209155561,
                            0.10480268601028901,
                            0.06001483615421275,
                            0.019281949622342365
                        ],
                        \"value\": 0.8325134311829753
                    },
                    \"K\": {
                        \"grade\": \"C\",
                        \"probs\": [
                            0.04991769134917503,
                            0.11665934402719838,
                            0.5910216778431446,
                            0.14820551042826816,
                            0.07450912369254556,
                            0.019686652659668274
                        ],
                        \"value\": 0.7803436619611638
                    }
                }
            }
        }
        ";*/
		
        $jsonsessiondata = json_encode($sessiondata, JSON_NUMERIC_CHECK );

        $opts = array(
            'http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/json',
                'content' => $jsonsessiondata
            )
        );

        $context = stream_context_create($opts);
        // End POST parameter

        $url = "https://twiki.di.uniroma1.it/BNservice/v0/BN";
		
		/* Questa è già la risposta dal server BN dopo l'invio dei dati tramite $context */
        $json_student_models = file_get_contents($url, false, $context);


       


        //sessiondata è quello che arriva
        $myfile = fopen("wb1_sessiondata.txt", "a") or die("Unable to open file!");
        fwrite($myfile, $sessiondata);
        //jsonsessiondata che è sessiondata encoded
        $myfile2 = fopen("wb2_jsonsessiondata.txt", "a") or die("Unable to open file!");
        fwrite($myfile2, $jsonsessiondata);
        //context da vedere come
        $myfile3 = fopen("wb3_context.txt", "a") or die("Unable to open file!");
        fwrite($myfile3, $context);
        //json_student_models che è il file di risposta dalla BN
        $myfile4 = fopen("wb4_json_student_models.txt", "a") or die("Unable to open file!");
        fwrite($myfile4, $json_student_models);






        //fwrite($myfile, $json_student_models);
        return $json_student_models;
    }
}

?>

