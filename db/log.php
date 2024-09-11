<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Definition of log events
 *
 * @package    mod_advwork
 * @category   log
 * @copyright  2010 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    // advwork instance log actions
    array('module'=>'advwork', 'action'=>'add', 'mtable'=>'advwork', 'field'=>'name'),
    array('module'=>'advwork', 'action'=>'update', 'mtable'=>'advwork', 'field'=>'name'),
    array('module'=>'advwork', 'action'=>'view', 'mtable'=>'advwork', 'field'=>'name'),
    array('module'=>'advwork', 'action'=>'view all', 'mtable'=>'advwork', 'field'=>'name'),
    // submission log actions
    array('module'=>'advwork', 'action'=>'add submission', 'mtable'=>'advwork_submissions', 'field'=>'title'),
    array('module'=>'advwork', 'action'=>'update submission', 'mtable'=>'advwork_submissions', 'field'=>'title'),
    array('module'=>'advwork', 'action'=>'view submission', 'mtable'=>'advwork_submissions', 'field'=>'title'),
    // assessment log actions
    array('module'=>'advwork', 'action'=>'add assessment', 'mtable'=>'advwork_submissions', 'field'=>'title'),
    array('module'=>'advwork', 'action'=>'update assessment', 'mtable'=>'advwork_submissions', 'field'=>'title'),
    // example log actions
    array('module'=>'advwork', 'action'=>'add example', 'mtable'=>'advwork_submissions', 'field'=>'title'),
    array('module'=>'advwork', 'action'=>'update example', 'mtable'=>'advwork_submissions', 'field'=>'title'),
    array('module'=>'advwork', 'action'=>'view example', 'mtable'=>'advwork_submissions', 'field'=>'title'),
    // example assessment log actions
    array('module'=>'advwork', 'action'=>'add reference assessment', 'mtable'=>'advwork_submissions', 'field'=>'title'),
    array('module'=>'advwork', 'action'=>'update reference assessment', 'mtable'=>'advwork_submissions', 'field'=>'title'),
    array('module'=>'advwork', 'action'=>'add example assessment', 'mtable'=>'advwork_submissions', 'field'=>'title'),
    array('module'=>'advwork', 'action'=>'update example assessment', 'mtable'=>'advwork_submissions', 'field'=>'title'),
    // grading evaluation log actions
    array('module'=>'advwork', 'action'=>'update aggregate grades', 'mtable'=>'advwork', 'field'=>'name'),
    array('module'=>'advwork', 'action'=>'update clear aggregated grades', 'mtable'=>'advwork', 'field'=>'name'),
    array('module'=>'advwork', 'action'=>'update clear assessments', 'mtable'=>'advwork', 'field'=>'name'),
);
