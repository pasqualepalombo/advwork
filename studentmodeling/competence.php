<?php

/**
 * Holds data about a competence
 */
class competence {
    /** @var grade in interval [A-F] for the competence */
    public $grade = null;

    /** @var distributions of the grade over the values from the interval [A-F] */
    public $gradedistributions = null;

    /** @var numeric value */
    public $value = null;
}