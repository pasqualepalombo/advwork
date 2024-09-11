<?php

// Base class for the Enums
abstract class BasicEnum {
    private static $constcachearray = NULL;

    /**
     * Get the constants of an enum
     *
     * @return array with constants
     */
    private static function getconstants() {
        if (self::$constcachearray == NULL) {
            self::$constcachearray = [];
        }
        $calledclass = get_called_class();
        if (!array_key_exists($calledclass, self::$constcachearray)) {
            $reflect = new ReflectionClass($calledclass);
            self::$constcachearray[$calledclass] = $reflect->getconstants();
        }
        return self::$constcachearray[$calledclass];
    }

    /**
     * Checks if the enum contains the specified name
     *
     * @param $name                 Name to be checked
     * @param bool $strict          Specifies whether the search is case sensitive or not
     * @return bool                 True if it contains, otherwise false
     */
    public static function isvalidname($name, $strict = false) {
        $constants = self::getconstants();

        if ($strict) {
            return array_key_exists($name, $constants);
        }

        $keys = array_map('strtolower', array_keys($constants));
        return in_array(strtolower($name), $keys);
    }

    /**
     * Checks if the enum contains the specified value
     *
     * @param $value                Value to be checked
     * @param bool $strict          Specifies whether the search is case sensitive or not
     * @return bool                 True if it contains, otherwise false
     */
    public static function isvalidvalue($value, $strict = true) {
        $values = array_values(self::getconstants());
        return in_array($value, $values, $strict);
    }
}