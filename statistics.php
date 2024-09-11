<?php
/**
 * Created by PhpStorm.
 * User: Gabriel
 * Date: 6/19/2018
 * Time: 12:10 PM
 */

class Statistics {
    /**
     * Used to compute the median value of an array
     *
     * @param $array            The array
     * @return float|int        Median value
     */
    public static function median($array) {
        return Statistics::quartile50($array);
    }

    /**
     * Used to compute the first quartile of an array
     *
     * @param $array            The array
     * @return float|int        First quartile
     */
    public static function quartile25($array) {
        return Statistics::quartile($array, 0.25);
    }

    /**
     * Used to compute the second quartile of an array
     *
     * @param $array            The array
     * @return float|int        Second quartile
     */
    public static function quartile50($array) {
        return Statistics::quartile($array, 0.5);
    }

    /**
     * Used to compute the third quartile of an array
     *
     * @param $array            The array
     * @return float|int        Third quartile
     */
    public static function quartile75($array) {
        return Statistics::quartile($array, 0.75);
    }

    /**
     * Used to compute the specified quartile of an array
     *
     * @param $array            The array
     * @param $quartile         Quartile to compute
     * @return float|int        Value of the quartile
     */
    public static function quartile($array, $quartile) {
        $pos = (count($array) - 1) * $quartile;

        $base = floor($pos);
        $rest = $pos - $base;

        if( isset($array[$base+1]) ) {
            return $array[$base] + $rest * ($array[$base+1] - $array[$base]);
        } else {
            return $array[$base];
        }
    }

    /**
     * Used to compute the average value of an array
     *
     * @param $array            The array
     * @return float|int        Average value
     */
    public static function average($array) {
        return array_sum($array) / count($array);
    }
}