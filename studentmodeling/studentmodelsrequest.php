<?php
/**
 * Created by PhpStorm.
 * User: Gabriel
 * Date: 5/2/2018
 * Time: 2:12 PM
 */

/**
 * Holds session data to make the request to the Bayesian Network Service API
 */
class student_models_request {
    public $parameters = null;

    /**
     * Used to import the properties of another object into current one
     *
     * @param student_models_request $object         Object to import properties from
     */
    public function import(student_models_request $object)
    {
        foreach (get_object_vars($object) as $key => $value) {
            $this->$key = $value;
        }
    }
}