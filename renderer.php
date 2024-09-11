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
 * advwork module renderering methods are defined here
 *
 * @package    mod_advwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * advwork module renderer class
 *
 * @copyright 2009 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_advwork_renderer extends plugin_renderer_base {

    ////////////////////////////////////////////////////////////////////////////
    // External API - methods to render advwork renderable components
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Renders advwork message
     *
     * @param advwork_message $message to display
     * @return string html code
     */
    protected function render_advwork_message(advwork_message $message) {

        $text   = $message->get_message();
        $url    = $message->get_action_url();
        $label  = $message->get_action_label();

        if (empty($text) and empty($label)) {
            return '';
        }

        switch ($message->get_type()) {
        case advwork_message::TYPE_OK:
            $sty = 'ok';
            break;
        case advwork_message::TYPE_ERROR:
            $sty = 'error';
            break;
        default:
            $sty = 'info';
        }

        $o = html_writer::tag('span', $message->get_message());

        if (!is_null($url) and !is_null($label)) {
            $o .= $this->output->single_button($url, $label, 'get');
        }

        return $this->output->container($o, array('message', $sty));
    }


    /**
     * Renders full advwork submission
     *
     * @param advwork_submission $submission
     * @return string HTML
     */
    protected function render_advwork_submission(advwork_submission $submission) {
        global $CFG;

        $o  = '';    // output HTML code
        $anonymous = $submission->is_anonymous();
        $classes = 'submission-full';
        if ($anonymous) {
            $classes .= ' anonymous';
        }
        $o .= $this->output->container_start($classes);
        $o .= $this->output->container_start('header');

        $title = format_string($submission->title);

        if ($this->page->url != $submission->url) {
            $title = html_writer::link($submission->url, $title);
        }

        $o .= $this->output->heading($title, 3, 'title');

        if (!$anonymous) {
            $author = new stdclass();
            $additionalfields = explode(',', user_picture::fields());
            $author = username_load_fields_from_object($author, $submission, 'author', $additionalfields);
            $userpic            = $this->output->user_picture($author, array('courseid' => $this->page->course->id, 'size' => 64));
            $userurl            = new moodle_url('/user/view.php',
                                            array('id' => $author->id, 'course' => $this->page->course->id));
            $a                  = new stdclass();
            $a->name            = fullname($author);
            $a->url             = $userurl->out();
            $byfullname         = get_string('byfullname', 'advwork', $a);
            $oo  = $this->output->container($userpic, 'picture');
            $oo .= $this->output->container($byfullname, 'fullname');

            $o .= $this->output->container($oo, 'author');
        }

        $created = get_string('userdatecreated', 'advwork', userdate($submission->timecreated));
        $o .= $this->output->container($created, 'userdate created');

        if ($submission->timemodified > $submission->timecreated) {
            $modified = get_string('userdatemodified', 'advwork', userdate($submission->timemodified));
            $o .= $this->output->container($modified, 'userdate modified');
        }

        $o .= $this->output->container_end(); // end of header

        $content = file_rewrite_pluginfile_urls($submission->content, 'pluginfile.php', $this->page->context->id,
                                                        'mod_advwork', 'submission_content', $submission->id);
        $content = format_text($content, $submission->contentformat, array('overflowdiv'=>true));
        if (!empty($content)) {
            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $content .= plagiarism_get_links(array('userid' => $submission->authorid,
                    'content' => $submission->content,
                    'cmid' => $this->page->cm->id,
                    'course' => $this->page->course));
            }
        }
        $o .= $this->output->container($content, 'content');

        $o .= $this->helper_submission_attachments($submission->id, 'html');

        $o .= $this->output->container_end(); // end of submission-full

        return $o;
    }
    /**
     * Returns a download button with a single button.
     *
     * Theme developers: DO NOT OVERRIDE! Please override function
     * {@link core_renderer::render_single_button()} instead.
     *
     * @param string|moodle_url $url
     * @param string $label button text
     * @param string $method get or post submit method
     * @param array $options associative array {disabled, title, etc.}
     * @return string HTML fragment
     */
    public function download_button($url, $label) {
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
        return "<a href=\"$url\" download><button style=\"$style\" name=\"export\" type=\"submit\"> $label </button></a>";
    }
    /**
     * Renders short summary of the submission
     *
     * @param advwork_submission_summary $summary
     * @return string text to be echo'ed
     */
    protected function render_advwork_submission_summary(advwork_submission_summary $summary) {

        $o  = '';    // output HTML code
        $anonymous = $summary->is_anonymous();
        $classes = 'submission-summary';

        if ($anonymous) {
            $classes .= ' anonymous';
        }

        $gradestatus = '';

        if ($summary->status == 'notgraded') {
            $classes    .= ' notgraded';
            $gradestatus = $this->output->container(get_string('nogradeyet', 'advwork'), 'grade-status');

        } else if ($summary->status == 'graded') {
            $classes    .= ' graded';
            $gradestatus = $this->output->container(get_string('alreadygraded', 'advwork'), 'grade-status');
        }

        $o .= $this->output->container_start($classes);  // main wrapper
        $o .= html_writer::link($summary->url, format_string($summary->title), array('class' => 'title'));

        if (!$anonymous) {
            $author             = new stdClass();
            $additionalfields = explode(',', user_picture::fields());
            $author = username_load_fields_from_object($author, $summary, 'author', $additionalfields);
            $userpic            = $this->output->user_picture($author, array('courseid' => $this->page->course->id, 'size' => 35));
            $userurl            = new moodle_url('/user/view.php',
                                            array('id' => $author->id, 'course' => $this->page->course->id));
            $a                  = new stdClass();
            $a->name            = fullname($author);
            $a->url             = $userurl->out();
            $byfullname         = get_string('byfullname', 'advwork', $a);

            $oo  = $this->output->container($userpic, 'picture');
            $oo .= $this->output->container($byfullname, 'fullname');
            $o  .= $this->output->container($oo, 'author');
        }

        $created = get_string('userdatecreated', 'advwork', userdate($summary->timecreated));
        $o .= $this->output->container($created, 'userdate created');

        if ($summary->timemodified > $summary->timecreated) {
            $modified = get_string('userdatemodified', 'advwork', userdate($summary->timemodified));
            $o .= $this->output->container($modified, 'userdate modified');
        }

        $o .= $gradestatus;
        $o .= $this->output->container_end(); // end of the main wrapper
        return $o;
    }

    /**
     * Renders a link to the general student model page
     *
     * @param advwork_general_student_model_url $link
     * @return string text to be echo'ed
     */
    protected function render_advwork_general_student_model_url(advwork_general_student_model_url $link) {
        $o  = '';    // output HTML code

        $classes = '';
        $o .= $this->output->container_start($classes);  // main wrapper
        $o .= html_writer::link($link->url, format_string($link->title), array('class' => 'collapsibleregioncaption'));
        $o .= $this->output->container_end(); // end of the main wrapper

        return $o;
    }

    /**
     * Renders full advwork example submission
     *
     * @param advwork_example_submission $example
     * @return string HTML
     */
    protected function render_advwork_example_submission(advwork_example_submission $example) {

        $o  = '';    // output HTML code
        $classes = 'submission-full example';
        $o .= $this->output->container_start($classes);
        $o .= $this->output->container_start('header');
        $o .= $this->output->container(format_string($example->title), array('class' => 'title'));
        $o .= $this->output->container_end(); // end of header

        $content = file_rewrite_pluginfile_urls($example->content, 'pluginfile.php', $this->page->context->id,
                                                        'mod_advwork', 'submission_content', $example->id);
        $content = format_text($content, $example->contentformat, array('overflowdiv'=>true));
        $o .= $this->output->container($content, 'content');

        $o .= $this->helper_submission_attachments($example->id, 'html');

        $o .= $this->output->container_end(); // end of submission-full

        return $o;
    }

    /**
     * Renders short summary of the example submission
     *
     * @param advwork_example_submission_summary $summary
     * @return string text to be echo'ed
     */
    protected function render_advwork_example_submission_summary(advwork_example_submission_summary $summary) {

        $o  = '';    // output HTML code

        // wrapping box
        $o .= $this->output->box_start('generalbox example-summary ' . $summary->status);

        // title
        $o .= $this->output->container_start('example-title');
        $o .= html_writer::link($summary->url, format_string($summary->title), array('class' => 'title'));

        if ($summary->editable) {
            $o .= $this->output->action_icon($summary->editurl, new pix_icon('i/edit', get_string('edit')));
        }
        $o .= $this->output->container_end();

        // additional info
        if ($summary->status == 'notgraded') {
            $o .= $this->output->container(get_string('nogradeyet', 'advwork'), 'example-info nograde');
        } else {
            $o .= $this->output->container(get_string('gradeinfo', 'advwork' , $summary->gradeinfo), 'example-info grade');
        }

        // button to assess
        $button = new single_button($summary->assessurl, $summary->assesslabel, 'get');
        $o .= $this->output->container($this->output->render($button), 'example-actions');

        // end of wrapping box
        $o .= $this->output->box_end();

        return $o;
    }

    /**
     * Renders the user plannner tool
     *
     * @param advwork_user_plan $plan prepared for the user
     * @return string html code to be displayed
     */
    protected function render_advwork_user_plan(advwork_user_plan $plan) {
        $o  = '';    // Output HTML code.
        $numberofphases = count($plan->phases);
        $o .= html_writer::start_tag('div', array(
            'class' => 'userplan',
            'aria-labelledby' => 'mod_advwork-userplanheading',
            'aria-describedby' => 'mod_advwork-userplanaccessibilitytitle',
        ));
        $o .= html_writer::span(get_string('userplanaccessibilitytitle', 'advwork', $numberofphases),
            'accesshide', array('id' => 'mod_advwork-userplanaccessibilitytitle'));
        $o .= html_writer::link('#mod_advwork-userplancurrenttasks', get_string('userplanaccessibilityskip', 'advwork'),
            array('class' => 'accesshide'));
        foreach ($plan->phases as $phasecode => $phase) {
            $o .= html_writer::start_tag('dl', array('class' => 'phase'));
            $actions = '';

            if ($phase->active) {
                // Mark the section as the current one.
                $icon = $this->output->pix_icon('i/marked', '', 'moodle', ['role' => 'presentation']);
                $actions .= get_string('userplancurrentphase', 'advwork').' '.$icon;

            } else {
                // Display a control widget to switch to the given phase or mark the phase as the current one.
                foreach ($phase->actions as $action) {
                    if ($action->type === 'switchphase') {
                        if ($phasecode == advwork::PHASE_ASSESSMENT && $plan->advwork->phase == advwork::PHASE_SUBMISSION
                                && $plan->advwork->phaseswitchassessment) {
                            $icon = new pix_icon('i/scheduled', get_string('switchphaseauto', 'mod_advwork'));
                        } else {
                            $icon = new pix_icon('i/marker', get_string('switchphase'.$phasecode, 'mod_advwork'));
                        }
                        $actions .= $this->output->action_icon($action->url, $icon, null, null, true);
                    }
                }
            }

            if (!empty($actions)) {
                $actions = $this->output->container($actions, 'actions');
            }
            $classes = 'phase' . $phasecode;
            if ($phase->active) {
                $title = html_writer::span($phase->title, 'phasetitle', ['id' => 'mod_advwork-userplancurrenttasks']);
                $classes .= ' active';
            } else {
                $title = html_writer::span($phase->title, 'phasetitle');
                $classes .= ' nonactive';
            }
            $o .= html_writer::start_tag('dt', array('class' => $classes));
            $o .= $this->output->container($title . $actions);
            $o .= html_writer::start_tag('dd', array('class' => $classes. ' phasetasks'));
            $o .= $this->helper_user_plan_tasks($phase->tasks);
            $o .= html_writer::end_tag('dd');
            $o .= html_writer::end_tag('dl');
        }
        $o .= html_writer::end_tag('div');
        return $o;
    }

    /**
     * Renders the result of the submissions allocation process
     *
     * @param advwork_allocation_result $result as returned by the allocator's init() method
     * @return string HTML to be echoed
     */
    protected function render_advwork_allocation_result(advwork_allocation_result $result) {
        global $CFG;

        $status = $result->get_status();

        if (is_null($status) or $status == advwork_allocation_result::STATUS_VOID) {
            debugging('Attempt to render advwork_allocation_result with empty status', DEBUG_DEVELOPER);
            return '';
        }

        switch ($status) {
        case advwork_allocation_result::STATUS_FAILED:
            if ($message = $result->get_message()) {
                $message = new advwork_message($message, advwork_message::TYPE_ERROR);
            } else {
                $message = new advwork_message(get_string('allocationerror', 'advwork'), advwork_message::TYPE_ERROR);
            }
            break;

        case advwork_allocation_result::STATUS_CONFIGURED:
            if ($message = $result->get_message()) {
                $message = new advwork_message($message, advwork_message::TYPE_INFO);
            } else {
                $message = new advwork_message(get_string('allocationconfigured', 'advwork'), advwork_message::TYPE_INFO);
            }
            break;

        case advwork_allocation_result::STATUS_EXECUTED:
            if ($message = $result->get_message()) {
                $message = new advwork_message($message, advwork_message::TYPE_OK);
            } else {
                $message = new advwork_message(get_string('allocationdone', 'advwork'), advwork_message::TYPE_OK);
            }
            break;

        default:
            throw new coding_exception('Unknown allocation result status', $status);
        }

        // start with the message
        $o = $this->render($message);

        // display the details about the process if available
        $logs = $result->get_logs();
        if (is_array($logs) and !empty($logs)) {
            $o .= html_writer::start_tag('ul', array('class' => 'allocation-init-results'));
            foreach ($logs as $log) {
                if ($log->type == 'debug' and !$CFG->debugdeveloper) {
                    // display allocation debugging messages for developers only
                    continue;
                }
                $class = $log->type;
                if ($log->indent) {
                    $class .= ' indent';
                }
                $o .= html_writer::tag('li', $log->message, array('class' => $class)).PHP_EOL;
            }
            $o .= html_writer::end_tag('ul');
        }

        return $o;
    }

    /**
     * Renders the advwork grading report
     *
     * @param advwork_grading_report $gradingreport
     * @return string html code
     */
    protected function render_advwork_grading_report(advwork_grading_report $gradingreport) {

        $data                  = $gradingreport->get_data();
        $options               = $gradingreport->get_options();
        $grades                = $data->grades;
        $userinfo              = $data->userinfo;
        $capabilitiesquartiles = $data->capabilitiesquartiles;

        $quartiles = new grading_report_quartiles();

        $quartiledata = $this->get_capability_quartile_data($capabilitiesquartiles, "C", false);
        $quartiles->submissiongradesinglesessionfirstquartile = $quartiledata->firstquartile;
        $quartiles->submissiongradesinglesessionthirdquartile = $quartiledata->thirdquartile;

        $quartiledata = $this->get_capability_quartile_data($capabilitiesquartiles, "C", true);
        $quartiles->submissiongradecumulatedfirstquartile = $quartiledata->firstquartile;
        $quartiles->submissiongradecumulatedthirdquartile = $quartiledata->thirdquartile;

        $quartiledata = $this->get_capability_quartile_data($capabilitiesquartiles, "K", false);
        $quartiles->competencesinglesessionfirstquartile = $quartiledata->firstquartile;
        $quartiles->competencesinglesessionthirdquartile = $quartiledata->thirdquartile;

        $quartiledata = $this->get_capability_quartile_data($capabilitiesquartiles, "K", true);
        $quartiles->competencecumulatedfirstquartile = $quartiledata->firstquartile;
        $quartiles->competencecumulatedthirdquartile = $quartiledata->thirdquartile;

        $quartiledata = $this->get_capability_quartile_data($capabilitiesquartiles, "J", false);
        $quartiles->assessmentcapabilitysinglesessionfirstquartile = $quartiledata->firstquartile;
        $quartiles->assessmentcapabilitysinglesessionthirdquartile = $quartiledata->thirdquartile;

        $quartiledata = $this->get_capability_quartile_data($capabilitiesquartiles, "J", true);
        $quartiles->assessmentcapabilitycumulatedfirstquartile = $quartiledata->firstquartile;
        $quartiles->assessmentcapabilitycumulatedthirdquartile = $quartiledata->thirdquartile;

        if (empty($grades)) {
            return '';
        }

        $table = new html_table();
        $table->attributes['class'] = 'grading-report';

        $sortbyfirstname = $this->helper_sortable_heading(get_string('firstname'), 'firstname', $options->sortby, $options->sorthow);
        $sortbylastname = $this->helper_sortable_heading(get_string('lastname'), 'lastname', $options->sortby, $options->sorthow);
        if (self::fullname_format() == 'lf') {
            $sortbyname = $sortbylastname . ' / ' . $sortbyfirstname;
        } else {
            $sortbyname = $sortbyfirstname . ' / ' . $sortbylastname;
        }

        $sortbysubmisstiontitle = $this->helper_sortable_heading(get_string('submission', 'advwork'), 'submissiontitle',
            $options->sortby, $options->sorthow);
        $sortbysubmisstionlastmodified = $this->helper_sortable_heading(get_string('submissionlastmodified', 'advwork'),
            'submissionmodified', $options->sortby, $options->sorthow);
        $sortbysubmisstion = $sortbysubmisstiontitle . ' / ' . $sortbysubmisstionlastmodified;

        $table->head = array();
        $table->head[] = $sortbyname;
        $table->head[] = $sortbysubmisstion;

        // If we are in submission phase ignore the following headers (columns).
        if ($options->advworkphase != advwork::PHASE_SUBMISSION) {
            $table->head[] = $this->helper_sortable_heading(get_string('receivedgrades', 'advwork'));
            $table->head[] = $this->helper_sortable_heading(get_string('givengrades', 'advwork'));
            if($options->showsinglesessiongrades) {
                $table->head[] = $this->helper_sortable_heading(get_string('submissiongradesinglesession', 'advwork'),
                    'submissiongradesinglesession', $options->sortby, $options->sorthow);
                $table->head[] = $this->helper_sortable_heading(get_string('competencesinglesession', 'advwork'),
                    'competencesinglesession', $options->sortby, $options->sorthow);
                $table->head[] = $this->helper_sortable_heading(get_string('assessmentcapabilitysinglesession', 'advwork'),
                    'assessmentcapabilitysinglesession', $options->sortby, $options->sorthow);
            }

            if($options->showcumulatedgrades) {
                $table->head[] = $this->helper_sortable_heading(get_string('competencecumulated', 'advwork'),
                    'competencecumulated', $options->sortby, $options->sorthow);
                $table->head[] = $this->helper_sortable_heading(get_string('assessmentcapabilitycumulated', 'advwork'),
                    'assessmentcapabilitycumulated', $options->sortby, $options->sorthow);
            }
        }
        $table->rowclasses  = array();
        $table->colclasses  = array();
        $table->data        = array();

        foreach ($grades as $participant) {
            if (!isset($participant->userid)) {
                break;
            }
            $numofreceived  = count($participant->reviewedby);
            $numofgiven     = count($participant->reviewerof);
            $published      = $participant->submissionpublished;

            // compute the number of <tr> table rows needed to display this participant
            if ($numofreceived > 0 and $numofgiven > 0) {
                $numoftrs       = advwork::lcm($numofreceived, $numofgiven);
                $spanreceived   = $numoftrs / $numofreceived;
                $spangiven      = $numoftrs / $numofgiven;
            } elseif ($numofreceived == 0 and $numofgiven > 0) {
                $numoftrs       = $numofgiven;
                $spanreceived   = $numoftrs;
                $spangiven      = $numoftrs / $numofgiven;
            } elseif ($numofreceived > 0 and $numofgiven == 0) {
                $numoftrs       = $numofreceived;
                $spanreceived   = $numoftrs / $numofreceived;
                $spangiven      = $numoftrs;
            } else {
                $numoftrs       = 1;
                $spanreceived   = 1;
                $spangiven      = 1;
            }

            for ($tr = 0; $tr < $numoftrs; $tr++) {
                $row = new html_table_row();
                if ($published) {
                    $row->attributes['class'] = 'published';
                }
                // column #1 - participant - spans over all rows
                if ($tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_participant($participant, $userinfo);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'participant grading-report-name-col';
                    $row->cells[] = $cell;
                }
                // column #2 - submission - spans over all rows
                if ($tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_submission($participant);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'submission';
                    $row->cells[] = $cell;
                }

                // If we are in submission phase ignore the following columns.
                if ($options->advworkphase == advwork::PHASE_SUBMISSION) {
                    $table->data[] = $row;
                    continue;
                }

                // column #3 - received grades
                if ($tr % $spanreceived == 0) {
                    $idx = intval($tr / $spanreceived);
                    $assessment = self::array_nth($participant->reviewedby, $idx);
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_assessment($assessment, $options->showreviewernames, $userinfo,
                        get_string('gradereceivedfrom', 'advwork'));
                    $cell->rowspan = $spanreceived;
                    $cell->attributes['class'] = '';
                    if (is_null($assessment) or is_null($assessment->grade)) {
                        $cell->attributes['class'] .= ' null';
                    } else {
                        $cell->attributes['class'] .= ' notnull';
                    }
                    $row->cells[] = $cell;
                }

                // column #4 - given grades
                if ($tr % $spangiven == 0) {
                    $idx = intval($tr / $spangiven);
                    $assessment = self::array_nth($participant->reviewerof, $idx);
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_assessment($assessment, $options->showauthornames, $userinfo,
                        get_string('gradegivento', 'advwork'));
                    $cell->rowspan = $spangiven;
                    $cell->attributes['class'] = '';
                    if (is_null($assessment) or is_null($assessment->grade)) {
                        $cell->attributes['class'] .= ' null';
                    } else {
                        $cell->attributes['class'] .= ' notnull';
                    }
                    $row->cells[] = $cell;
                }

                // column #5 - submission grade single session
                if ($options->showsinglesessiongrades and $tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_grade($participant->submissiongradesinglesession);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'submissiongrade grading-report-metric-col';
                    if($participant->submissiongradesinglesession / 100 <= $quartiles->submissiongradesinglesessionfirstquartile) {
                        $cell->attributes['class'] .= ' red-font';
                    }

                    if($participant->submissiongradesinglesession / 100 >= $quartiles->submissiongradesinglesessionthirdquartile) {
                        $cell->attributes['class'] .= ' green-font';
                    }

                    $row->cells[] = $cell;
                }

                // column #6 - competence single session
                if ($options->showsinglesessiongrades and $tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_grade($participant->competencesinglesession);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'submissiongrade grading-report-metric-col';
                    if($participant->competencesinglesession / 100 <= $quartiles->competencesinglesessionfirstquartile) {
                        $cell->attributes['class'] .= ' red-font';
                    }

                    if($participant->competencesinglesession / 100 >= $quartiles->competencesinglesessionthirdquartile) {
                        $cell->attributes['class'] .= ' green-font';
                    }

                    $row->cells[] = $cell;
                }

                // column #7 - assessment capability single session
                if ($options->showsinglesessiongrades and $tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_grade($participant->assessmentcapabilitysinglesession);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'submissiongrade grading-report-metric-col';
                    if($participant->assessmentcapabilitysinglesession / 100 <= $quartiles->assessmentcapabilitysinglesessionfirstquartile) {
                        $cell->attributes['class'] .= ' red-font';
                    }

                    if($participant->assessmentcapabilitysinglesession / 100 >= $quartiles->assessmentcapabilitysinglesessionthirdquartile) {
                        $cell->attributes['class'] .= ' green-font';
                    }

                    $row->cells[] = $cell;
                }

                // column #8 - competence cumulated
                if ($options->showsinglesessiongrades and $tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_grade($participant->competencecumulated);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'submissiongrade grading-report-metric-col';
                    if($participant->competencecumulated / 100 <= $quartiles->competencecumulatedfirstquartile) {
                        $cell->attributes['class'] .= ' red-font';
                    }

                    if($participant->competencecumulated / 100 >= $quartiles->competencecumulatedthirdquartile) {
                        $cell->attributes['class'] .= ' green-font';
                    }

                    $row->cells[] = $cell;
                }

                // column #9 - assessment capability cumulated
                if ($options->showsinglesessiongrades and $tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_grade($participant->assessmentcapabilitycumulated);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'submissiongrade grading-report-metric-col';
                    if($participant->assessmentcapabilitycumulated / 100 <= $quartiles->assessmentcapabilitycumulatedfirstquartile) {
                        $cell->attributes['class'] .= ' red-font';
                    }

                    if($participant->assessmentcapabilitycumulated / 100 >= $quartiles->assessmentcapabilitycumulatedthirdquartile) {
                        $cell->attributes['class'] .= ' green-font';
                    }

                    $row->cells[] = $cell;
                }

                $table->data[] = $row;
            }
        }

        return html_writer::table($table);
    }

    /**
     * Renders the general student models grading report
     *
     * @param advwork_general_student_models_grading_report $gradingreport
     * @return string html code
     */
    protected function render_advwork_general_student_models_grading_report(advwork_general_student_models_grading_report $gradingreport) {

        $data                  = $gradingreport->get_data();
        $options               = $gradingreport->get_options();

        $studentmodelsdata     = $data->studentmodelsdata;

        $table = new html_table();
        $table->attributes['class'] = 'grading-report';

        $table->head = array();
        $table->head[] = $this->helper_sortable_heading(get_string('name'), 'name', $options->sortby, $options->sorthow);
        $table->head[] = $this->helper_sortable_heading(get_string('submissiongradestring', 'advwork'), 'submissiongrade',
            $options->sortby, $options->sorthow);
        $table->head[] = $this->helper_sortable_heading(get_string('competence', 'advwork'),
            'competence', $options->sortby, $options->sorthow);
        $table->head[] = $this->helper_sortable_heading(get_string('assessmentcapability', 'advwork'),
            'assessmentcapability', $options->sortby, $options->sorthow);
        $table->head[] = $this->helper_sortable_heading(get_string('continuity', 'advwork'),
            'continuity', $options->sortby, $options->sorthow);
        $table->head[] = $this->helper_sortable_heading(get_string('stability', 'advwork'),
            'stability', $options->sortby, $options->sorthow);
        $table->head[] = $this->helper_sortable_heading(get_string('reliability', 'advwork'),
            'reliability', $options->sortby, $options->sorthow);

        $table->rowclasses  = array();
        $table->colclasses  = array();
        $table->data        = array();

        foreach ($studentmodelsdata as $participant) {
            $numoftrs = 1;

            for ($tr = 0; $tr < $numoftrs; $tr++) {
                $row = new html_table_row();

                $cell = new html_table_cell();
                $cell->text = $this->helper_grading_report_name($participant);
                $cell->rowspan = $numoftrs;
                $cell->attributes['class'] = 'participant';
                $row->cells[] = $cell;

                if(is_nan($participant->submissiongrade) or $participant->submissiongrade == 0) {
                    $submissiongradevalue = "-";
                } else {
                    $submissiongradevalue = number_format((float)$participant->submissiongrade, 2, '.', '');
                }

                $cell = new html_table_cell();
                $cell->text = $this->helper_grading_report_grade($submissiongradevalue);
                $cell->rowspan = $numoftrs;
                $cell->attributes['class'] = 'submissiongrade';
                $row->cells[] = $cell;

                if(is_nan($participant->competence) or $participant->competence == 0) {
                    $competencevalue = "-";
                } else {
                    $competencevalue = number_format((float)$participant->competence, 2, '.', '');
                }

                $cell = new html_table_cell();
                $cell->text = $this->helper_grading_report_grade($competencevalue);
                $cell->rowspan = $numoftrs;
                $cell->attributes['class'] = 'submissiongrade';
                $row->cells[] = $cell;

                if(is_nan($participant->assessmentcapability) or $participant->assessmentcapability == 0) {
                    $assessmentcapabilityvalue = "-";
                } else {
                    $assessmentcapabilityvalue = number_format((float)$participant->assessmentcapability, 2, '.', '');
                }
                $cell = new html_table_cell();
                $cell->text = $this->helper_grading_report_grade($assessmentcapabilityvalue);
                $cell->rowspan = $numoftrs;
                $cell->attributes['class'] = 'submissiongrade';
                $row->cells[] = $cell;

                if(is_nan($participant->continuity) or $participant->continuity == 0) {
                    $continuityvalue = "-";
                } else {
                    $continuityvalue = number_format((float)$participant->continuity, 2, '.', '');
                }
                $cell = new html_table_cell();
                $cell->text = $this->helper_grading_report_grade($continuityvalue);
                $cell->rowspan = $numoftrs;
                $cell->attributes['class'] = 'submissiongrade';
                $row->cells[] = $cell;

                if(is_nan($participant->stability) or $participant->stability == 0) {
                    $stabilityvalue = "-";
                } else {
                    $stabilityvalue = number_format((float)$participant->stability, 2, '.', '');
                }
                $cell = new html_table_cell();
                $cell->text = $this->helper_grading_report_grade($stabilityvalue);
                $cell->rowspan = $numoftrs;
                $cell->attributes['class'] = 'submissiongrade';
                $row->cells[] = $cell;

                if(is_nan($participant->reliability) or $participant->reliability == 0) {
                    $reliabilityvalue = "-";
                } else {
                    $reliabilityvalue = number_format((float)$participant->reliability, 2, '.', '');
                }
                $cell = new html_table_cell();
                $cell->text = $this->helper_grading_report_grade($reliabilityvalue);
                $cell->rowspan = $numoftrs;
                $cell->attributes['class'] = 'submissiongrade';
                $row->cells[] = $cell;

                $table->data[] = $row;
            }
        }

        return html_writer::table($table);
    }

    /**
     * Used to get quartile data entry for the specified capability
     *
     * @param $capabilitiesquartiledata         Data with all the computed quartiles for all the capabilities
     * @param $capabilityname                   Name of the capability to filter
     * @param $iscumulated                      Flag to indicate if we want the quartile data for the single session or cumulated
     * @return an object with quartile data
     */
    protected function get_capability_quartile_data($capabilitiesquartiledata, $capabilityname, $iscumulated){
    	if(is_null($capabilitiesquartiledata)) {
        	$capabilitiesquartiledata = Array();
        }
        $quartiledataentries = array_filter($capabilitiesquartiledata, function($entry) use($capabilityname, $iscumulated){
            return $entry->capabilityname == $capabilityname && $entry->iscumulated == $iscumulated;
        });

        $quartiledata = reset($quartiledataentries);
        return $quartiledata;
    }

    /**
     * Renders the feedback for the author of the submission
     *
     * @param advwork_feedback_author $feedback
     * @return string HTML
     */
    protected function render_advwork_feedback_author(advwork_feedback_author $feedback) {
        return $this->helper_render_feedback($feedback);
    }

    /**
     * Renders the feedback for the reviewer of the submission
     *
     * @param advwork_feedback_reviewer $feedback
     * @return string HTML
     */
    protected function render_advwork_feedback_reviewer(advwork_feedback_reviewer $feedback) {
        return $this->helper_render_feedback($feedback);
    }

    /**
     * Helper method to rendering feedback
     *
     * @param advwork_feedback_author|advwork_feedback_reviewer $feedback
     * @return string HTML
     */
    private function helper_render_feedback($feedback) {

        $o  = '';    // output HTML code
        $o .= $this->output->container_start('feedback feedbackforauthor');
        $o .= $this->output->container_start('header');
        $o .= $this->output->heading(get_string('feedbackby', 'advwork', s(fullname($feedback->get_provider()))), 3, 'title');

        $userpic = $this->output->user_picture($feedback->get_provider(), array('courseid' => $this->page->course->id, 'size' => 32));
        $o .= $this->output->container($userpic, 'picture');
        $o .= $this->output->container_end(); // end of header

        $content = format_text($feedback->get_content(), $feedback->get_format(), array('overflowdiv' => true));
        $o .= $this->output->container($content, 'content');

        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * Renders the full assessment
     *
     * @param advwork_assessment $assessment
     * @return string HTML
     */
    protected function render_advwork_assessment(advwork_assessment $assessment) {

        $o = ''; // output HTML code
        $anonymous = is_null($assessment->reviewer);
        $classes = 'assessment-full';
        if ($anonymous) {
            $classes .= ' anonymous';
        }

        $o .= $this->output->container_start($classes);
        $o .= $this->output->container_start('header');

        if (!empty($assessment->title)) {
            $title = s($assessment->title);
        } else {
            $title = get_string('assessment', 'advwork');
        }
        if (($assessment->url instanceof moodle_url) and ($this->page->url != $assessment->url)) {
            $o .= $this->output->container(html_writer::link($assessment->url, $title), 'title');
        } else {
            $o .= $this->output->container($title, 'title');
        }

        if (!$anonymous) {
            $reviewer   = $assessment->reviewer;
            $userpic    = $this->output->user_picture($reviewer, array('courseid' => $this->page->course->id, 'size' => 32));

            $userurl    = new moodle_url('/user/view.php',
                                       array('id' => $reviewer->id, 'course' => $this->page->course->id));
            $a          = new stdClass();
            $a->name    = fullname($reviewer);
            $a->url     = $userurl->out();
            $byfullname = get_string('assessmentby', 'advwork', $a);
            $oo         = $this->output->container($userpic, 'picture');
            $oo        .= $this->output->container($byfullname, 'fullname');

            $o .= $this->output->container($oo, 'reviewer');
        }

        if (is_null($assessment->realgrade)) {
            $o .= $this->output->container(
                get_string('notassessed', 'advwork'),
                'grade nograde'
            );
        } else {
            $a              = new stdClass();
            $a->max         = $assessment->maxgrade;
            $a->received    = $assessment->realgrade;
            $o .= $this->output->container(
                get_string('gradeinfo', 'advwork', $a),
                'grade'
            );

            if (!is_null($assessment->weight) and $assessment->weight != 1) {
                $o .= $this->output->container(
                    get_string('weightinfo', 'advwork', $assessment->weight),
                    'weight'
                );
            }
        }

        $o .= $this->output->container_start('actions');
        foreach ($assessment->actions as $action) {
            $o .= $this->output->single_button($action->url, $action->label, $action->method);
        }
        $o .= $this->output->container_end(); // actions

        $o .= $this->output->container_end(); // header

        if (!is_null($assessment->form)) {
            $o .= print_collapsible_region_start('assessment-form-wrapper', uniqid('advwork-assessment'),
                    get_string('assessmentform', 'advwork'), '', false, true);
            $o .= $this->output->container(self::moodleform($assessment->form), 'assessment-form');
            $o .= print_collapsible_region_end(true);

            if (!$assessment->form->is_editable()) {
                $o .= $this->overall_feedback($assessment);
            }
        }

        $o .= $this->output->container_end(); // main wrapper

        return $o;
    }

    /**
     * Renders the assessment of an example submission
     *
     * @param advwork_example_assessment $assessment
     * @return string HTML
     */
    protected function render_advwork_example_assessment(advwork_example_assessment $assessment) {
        return $this->render_advwork_assessment($assessment);
    }

    /**
     * Renders the reference assessment of an example submission
     *
     * @param advwork_example_reference_assessment $assessment
     * @return string HTML
     */
    protected function render_advwork_example_reference_assessment(advwork_example_reference_assessment $assessment) {
        return $this->render_advwork_assessment($assessment);
    }

    /**
     * Renders the overall feedback for the author of the submission
     *
     * @param advwork_assessment $assessment
     * @return string HTML
     */
    protected function overall_feedback(advwork_assessment $assessment) {

        $content = $assessment->get_overall_feedback_content();

        if ($content === false) {
            return '';
        }

        $o = '';

        if (!is_null($content)) {
            $o .= $this->output->container($content, 'content');
        }

        $attachments = $assessment->get_overall_feedback_attachments();

        if (!empty($attachments)) {
            $o .= $this->output->container_start('attachments');
            $images = '';
            $files = '';
            foreach ($attachments as $attachment) {
                $icon = $this->output->pix_icon(file_file_icon($attachment), get_mimetype_description($attachment),
                    'moodle', array('class' => 'icon'));
                $link = html_writer::link($attachment->fileurl, $icon.' '.substr($attachment->filepath.$attachment->filename, 1));
                if (file_mimetype_in_typegroup($attachment->mimetype, 'web_image')) {
                    $preview = html_writer::empty_tag('img', array('src' => $attachment->previewurl, 'alt' => '', 'class' => 'preview'));
                    $preview = html_writer::tag('a', $preview, array('href' => $attachment->fileurl));
                    $images .= $this->output->container($preview);
                } else {
                    $files .= html_writer::tag('li', $link, array('class' => $attachment->mimetype));
                }
            }
            if ($images) {
                $images = $this->output->container($images, 'images');
            }

            if ($files) {
                $files = html_writer::tag('ul', $files, array('class' => 'files'));
            }

            $o .= $images.$files;
            $o .= $this->output->container_end();
        }

        if ($o === '') {
            return '';
        }

        $o = $this->output->box($o, 'overallfeedback');
        $o = print_collapsible_region($o, 'overall-feedback-wrapper', uniqid('advwork-overall-feedback'),
            get_string('overallfeedback', 'advwork'), '', false, true);

        return $o;
    }

    /**
     * Renders a perpage selector for advwork listings
     *
     * The scripts using this have to define the $PAGE->url prior to calling this
     * and deal with eventually submitted value themselves.
     *
     * @param int $current current value of the perpage parameter
     * @return string HTML
     */
    public function perpage_selector($current=10) {

        $options = array();
        foreach (array(10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 200, 300, 400, 500, 1000) as $option) {
            if ($option != $current) {
                $options[$option] = $option;
            }
        }
        $select = new single_select($this->page->url, 'perpage', $options, '', array('' => get_string('showingperpagechange', 'mod_advwork')));
        $select->label = get_string('showingperpage', 'mod_advwork', $current);
        $select->method = 'post';

        return $this->output->container($this->output->render($select), 'perpagewidget');
    }

    /**
     * Renders the user's final grades
     *
     * @param advwork_final_grades $grades with the info about grades in the gradebook
     * @return string HTML
     */
    protected function render_advwork_final_grades(advwork_final_grades $grades) {

        $out = html_writer::start_tag('div', array('class' => 'finalgrades'));

        if (!empty($grades->submissiongrade)) {
            $cssclass = 'grade submissiongrade';
            if ($grades->submissiongrade->hidden) {
                $cssclass .= ' hiddengrade';
            }
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', get_string('submissiongrade', 'mod_advwork'), array('class' => 'gradetype')) .
                html_writer::tag('div', $grades->submissiongrade->str_long_grade, array('class' => 'gradevalue')),
                array('class' => $cssclass)
            );
        }

        if (!empty($grades->assessmentgrade)) {
            $cssclass = 'grade assessmentgrade';
            if ($grades->assessmentgrade->hidden) {
                $cssclass .= ' hiddengrade';
            }
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', get_string('gradinggrade', 'mod_advwork'), array('class' => 'gradetype')) .
                html_writer::tag('div', $grades->assessmentgrade->str_long_grade, array('class' => 'gradevalue')),
                array('class' => $cssclass)
            );
        }

        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
     * Renders the user's overall grades: overall submission grade and overall assessment grade
     *
     * @param advwork_overall_grades $grades with the info about grades in the gradebook
     * @return string HTML
     */
    /*
    protected function render_advwork_overall_grades(advwork_overall_grades $grades) {

        $out = html_writer::start_tag('div', array('class' => 'finalgrades'));

        if (!empty($grades->overallsubmissiongrade)) {
            $cssclass = 'grade submissiongrade';
            if ($grades->overallsubmissiongrade->hidden) {
                $cssclass .= ' hiddengrade';
            }
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', get_string('submissiongrade', 'mod_advwork'), array('class' => 'gradetype')) .
                html_writer::tag('div', number_format((float)$grades->overallsubmissiongrade, 2, '.', ''), array('class' => 'gradevalue')),
                array('class' => $cssclass)
            );
        }

        if (!empty($grades->overallassessmentgrade)) {
            $cssclass = 'grade assessmentgrade';
            if ($grades->overallassessmentgrade->hidden) {
                $cssclass .= ' hiddengrade';
            }
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', get_string('gradinggrade', 'mod_advwork'), array('class' => 'gradetype')) .
                html_writer::tag('div', number_format((float)$grades->overallassessmentgrade, 2, '.', ''), array('class' => 'gradevalue')),
                array('class' => $cssclass)
            );
        }

        $out .= html_writer::end_tag('div');

        return $out;
    }*/

    /**
     * Renders the user's reliability metrics: continuity, stability and reliability
     *
     * @param advwork_reliability_metrics $reliabilitymetrics with the info about reliability grades
     * @return string HTML
     */
    protected function render_advwork_reliability_metrics(advwork_reliability_metrics $reliabilitymetrics) {

        $out = html_writer::start_tag('div', array('class' => 'reliability-metrics'));

        if (!empty($reliabilitymetrics->continuitysessionlevel)) {
            $cssclass = 'grade continuitymetric';
            if ($reliabilitymetrics->continuitysessionlevel->hidden) {
                $cssclass .= ' hiddengrade';
            }
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', get_string("continuitysessionlevel", "advwork"), array('class' => 'gradetype')) .
                html_writer::tag('div', number_format((float)$reliabilitymetrics->continuitysessionlevel, 2, '.', ''), array('class' => 'gradevalue')),
                array('class' => $cssclass)
            );
        }

        if (!empty($reliabilitymetrics->continuitychunks)) {
            $cssclass = 'grade continuitymetric';
            if ($reliabilitymetrics->continuitychunks->hidden) {
                $cssclass .= ' hiddengrade';
            }
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', get_string("continuitychunks", "advwork"), array('class' => 'gradetype')) .
                html_writer::tag('div', number_format((float)$reliabilitymetrics->continuitychunks, 2, '.', ''), array('class' => 'gradevalue')),
                array('class' => $cssclass)
            );
        }

        if (!is_null($reliabilitymetrics->stability)) {
            $cssclass = 'grade stabilitymetric';
            if ($reliabilitymetrics->stability->hidden) {
                $cssclass .= ' hiddengrade';
            }
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', get_string("stability", "advwork"), array('class' => 'gradetype')) .
                html_writer::tag('div', number_format((float)$reliabilitymetrics->stability, 2, '.', ''), array('class' => 'gradevalue')),
                array('class' => $cssclass)
            );
        }

        if (!empty($reliabilitymetrics->reliability)) {
            $cssclass = 'grade reliabilitymetric';
            if ($reliabilitymetrics->reliability->hidden) {
                $cssclass .= ' hiddengrade';
            }
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', get_string("reliability", "advwork"), array('class' => 'gradetype')) .
                html_writer::tag('div', number_format((float)$reliabilitymetrics->reliability, 2, '.', ''), array('class' => 'gradevalue')),
                array('class' => $cssclass)
            );
        }

        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
     * Renders the model of the student
     *
     * @param advwork_student_model $advwork_student_model with the model of the student
     * @return string HTML
     */
    protected function render_advwork_student_model(advwork_student_model $student_model) {

        $out = html_writer::start_tag('div', array('class' => 'student-model'));

        $capabilities = $this->get_capabilities();  // maybe this function should belong to locallib.php
        foreach ($capabilities as $capability) {
            $capabilityname = $capability->name;

            $capabilityentries = array_filter($student_model->entries, function($entry) use($capabilityname){
                return $entry->capability == $capabilityname;
            });

            $capabilityentry = reset($capabilityentries);

            $capabilitygrade = $capabilityentry->capabilityoverallgrade;
            $capabilityvalue = $capabilityentry->capabilityoverallvalue;

            $texttodisplay = "";
            switch ($capabilityname) {
                case "K":
                    $texttodisplay = "Competence";
                    break;
                case "J":
                    $texttodisplay = "Assessment Capability";
                    break;
                case "C":
                    $texttodisplay = "Submission Grade";
                    break;

            }

            $gradevaluetext = number_format((float)$capabilityvalue * 100, 2, '.', '')  . " ( " .  $capabilitygrade . " )";

            $cssclass = 'grade capability';
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', $texttodisplay, array('class' => 'gradevalue')) .
                html_writer::tag('div', $gradevaluetext
                    , array('class' => 'gradetype')),
                array('class' => $cssclass)
            );

        }

        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
     * Used to render the average submission grade for a session in a section div with a box
     *
     * @param advwork_average_submission_grade_session $objecttorender         Object with the average submission grade for a submission
     * @return string HTML
     */
    protected function render_advwork_average_submission_grade_session(advwork_average_submission_grade_session $objecttorender) {
        $out = html_writer::start_tag('div', array('class' => 'student-model'));

        $texttodisplay = get_string('averagesubmissiongrade', 'advwork');
        $cssclass = 'grade capability';
        $out .= html_writer::tag(
            'div',
            html_writer::tag('div', $texttodisplay, array('class' => 'gradetype')) .
            html_writer::tag('div', number_format((float)$objecttorender->averagesubmissiongrade * 100, 2, '.', '')
                , array('class' => 'gradevalue')),
            array('class' => $cssclass)
        );

        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
     * Used to render the standard deviation of the submissions' grades for a session
     *
     * @param advwork_standard_deviation_submissions_grades_session $objecttorender    Object with the standard deviation of the submissions' grades for a session
     * @return string HTML
     */
    protected function render_advwork_standard_deviation_submissions_grades_session(advwork_standard_deviation_submissions_grades_session $objecttorender) {
        $out = html_writer::start_tag('div', array('class' => 'student-model'));

        $texttodisplay = get_string('standarddeviationsubmissionsgrades', 'advwork');
        $cssclass = 'grade capability';
        $out .= html_writer::tag(
            'div',
            html_writer::tag('div', $texttodisplay, array('class' => 'gradetype')) .
            html_writer::tag('div', number_format((float)$objecttorender->standarddeviation * 100, 2, '.', '')
                , array('class' => 'gradevalue')),
            array('class' => $cssclass)
        );

        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
     * Used to render the standard deviation of the average submissions' grades for the sessions
     *
     * @param advwork_standard_deviation_submissions_grades_session $objecttorender    Object with the standard deviation of the average submissions' grades for the sessions
     * @return string HTML
     */
    protected function render_advwork_standard_deviation_average_submissions_grades(advwork_standard_deviation_average_submissions_grades $objecttorender) {
        $out = html_writer::start_tag('div', array('class' => 'student-model'));

        $texttodisplay = get_string('standarddeviationaveragesubmissionsgrades', 'advwork');
        $cssclass = 'grade capability';
        $out .= html_writer::tag(
            'div',
            html_writer::tag('div', $texttodisplay, array('class' => 'gradetype')) .
            html_writer::tag('div', number_format((float)$objecttorender->standarddeviation * 100, 2, '.', '')
                , array('class' => 'gradevalue')),
            array('class' => $cssclass)
        );

        $out .= html_writer::end_tag('div');

        return $out;
    }


    /**
     * Renders multiple models of the student
     *
     * @param advwork_student_models $advwork_student_models with the models of the student
     * @return string HTML
     */
    protected function render_advwork_student_models(advwork_student_models $advwork_student_models) {

        $out = html_writer::start_tag('div', array('class' => 'student-model'));

        $studentmodels = $advwork_student_models->studentmodels;
        $capabilities = $this->get_capabilities();  // maybe this function should belong to locallib.php
        foreach ($studentmodels as $studentmodel) {
            $out .= html_writer::tag('div', $studentmodel->advworkname, array('class' => 'bold-font'));
            foreach ($capabilities as $capability) {
                $capabilityname = $capability->name;

                $capabilityentries = array_filter($studentmodel->entries, function($entry) use($capabilityname){
                    return $entry->capability == $capabilityname;
                });

                $capabilityentry = reset($capabilityentries);

                $capabilitygrade = $capabilityentry->capabilityoverallgrade;
                $capabilityvalue = $capabilityentry->capabilityoverallvalue;

                $texttodisplay = "";
                switch ($capabilityname) {
                    case "K":
                        $texttodisplay = "Competence";
                        break;
                    case "J":
                        $texttodisplay = "Assessment Capability";
                        break;
                    case "C":
                        $texttodisplay = "Submission Grade";
                        break;

                }

                $gradevaluetext = number_format((float)$capabilityvalue * 100, 2, '.', '')  . " ( " .  $capabilitygrade . " )";

                $cssclass = 'grade capability';
                $out .= html_writer::tag(
                    'div',
                    html_writer::tag('div', $texttodisplay, array('class' => 'gradevalue')) .
                    html_writer::tag('div', $gradevaluetext
                        , array('class' => 'gradetype')),
                    array('class' => $cssclass)
                );
            }
            $out .= html_writer::tag('br', '');
        }

        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
     * Get all the capabilities
     *
     * @return array with all the capabilities defined in the system
     */
    function get_capabilities() {
        global $DB;

        $capabilitiesTable = "advwork_capabilities";
        return $DB->get_records($capabilitiesTable);
    }

    ////////////////////////////////////////////////////////////////////////////
    // Internal rendering helper methods
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Renders a list of files attached to the submission
     *
     * If format==html, then format a html string. If format==text, then format a text-only string.
     * Otherwise, returns html for non-images and html to display the image inline.
     *
     * @param int $submissionid submission identifier
     * @param string format the format of the returned string - html|text
     * @return string formatted text to be echoed
     */
    protected function helper_submission_attachments($submissionid, $format = 'html') {
        global $CFG;
        require_once($CFG->libdir.'/filelib.php');

        $fs     = get_file_storage();
        $ctx    = $this->page->context;
        $files  = $fs->get_area_files($ctx->id, 'mod_advwork', 'submission_attachment', $submissionid);

        $outputimgs     = '';   // images to be displayed inline
        $outputfiles    = '';   // list of attachment files

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $filepath   = $file->get_filepath();
            $filename   = $file->get_filename();
            $fileurl    = moodle_url::make_pluginfile_url($ctx->id, 'mod_advwork', 'submission_attachment',
                            $submissionid, $filepath, $filename, true);
            $embedurl   = moodle_url::make_pluginfile_url($ctx->id, 'mod_advwork', 'submission_attachment',
                            $submissionid, $filepath, $filename, false);
            $embedurl   = new moodle_url($embedurl, array('preview' => 'bigthumb'));
            $type       = $file->get_mimetype();
            $image      = $this->output->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));

            $linkhtml   = html_writer::link($fileurl, $image . substr($filepath, 1) . $filename);
            $linktxt    = "$filename [$fileurl]";

            if ($format == 'html') {
                if (file_mimetype_in_typegroup($type, 'web_image')) {
                    $preview     = html_writer::empty_tag('img', array('src' => $embedurl, 'alt' => '', 'class' => 'preview'));
                    $preview     = html_writer::tag('a', $preview, array('href' => $fileurl));
                    $outputimgs .= $this->output->container($preview);

                } else {
                    $outputfiles .= html_writer::tag('li', $linkhtml, array('class' => $type));
                }

            } else if ($format == 'text') {
                $outputfiles .= $linktxt . PHP_EOL;
            }

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $outputfiles .= plagiarism_get_links(array('userid' => $file->get_userid(),
                    'file' => $file,
                    'cmid' => $this->page->cm->id,
                    'course' => $this->page->course->id));
            }
        }

        if ($format == 'html') {
            if ($outputimgs) {
                $outputimgs = $this->output->container($outputimgs, 'images');
            }

            if ($outputfiles) {
                $outputfiles = html_writer::tag('ul', $outputfiles, array('class' => 'files'));
            }

            return $this->output->container($outputimgs . $outputfiles, 'attachments');

        } else {
            return $outputfiles;
        }
    }

    /**
     * Renders the tasks for the single phase in the user plan
     *
     * @param stdClass $tasks
     * @return string html code
     */
    protected function helper_user_plan_tasks(array $tasks) {
        $out = '';
        foreach ($tasks as $taskcode => $task) {
            $classes = '';
            $accessibilitytext = '';
            $icon = null;
            if ($task->completed === true) {
                $classes .= ' completed';
                $accessibilitytext .= get_string('taskdone', 'advwork') . ' ';
            } else if ($task->completed === false) {
                $classes .= ' fail';
                $accessibilitytext .= get_string('taskfail', 'advwork') . ' ';
            } else if ($task->completed === 'info') {
                $classes .= ' info';
                $accessibilitytext .= get_string('taskinfo', 'advwork') . ' ';
            } else {
                $accessibilitytext .= get_string('tasktodo', 'advwork') . ' ';
            }
            if (is_null($task->link)) {
                $title = html_writer::tag('span', $accessibilitytext, array('class' => 'accesshide'));
                $title .= $task->title;
            } else {
                $title = html_writer::tag('span', $accessibilitytext, array('class' => 'accesshide'));
                $title .= html_writer::link($task->link, $task->title);
            }
            $title = $this->output->container($title, 'title');
            $details = $this->output->container($task->details, 'details');
            $out .= html_writer::tag('li', $title . $details, array('class' => $classes));
        }
        if ($out) {
            $out = html_writer::tag('ul', $out, array('class' => 'tasks'));
        }
        return $out;
    }

    /**
     * Renders a text with icons to sort by the given column
     *
     * This is intended for table headings.
     *
     * @param string $text    The heading text
     * @param string $sortid  The column id used for sorting
     * @param string $sortby  Currently sorted by (column id)
     * @param string $sorthow Currently sorted how (ASC|DESC)
     *
     * @return string
     */
    protected function helper_sortable_heading($text, $sortid=null, $sortby=null, $sorthow=null) {
        global $PAGE;

        $out = html_writer::tag('span', $text, array('class'=>'text'));

        if (!is_null($sortid)) {
            if ($sortby !== $sortid or $sorthow !== 'ASC') {
                $url = new moodle_url($PAGE->url);
                $url->params(array('sortby' => $sortid, 'sorthow' => 'ASC'));
                $out .= $this->output->action_icon($url, new pix_icon('t/sort_asc', get_string('sortasc', 'advwork')),
                    null, array('class' => 'iconsort sort asc'));
            }
            if ($sortby !== $sortid or $sorthow !== 'DESC') {
                $url = new moodle_url($PAGE->url);
                $url->params(array('sortby' => $sortid, 'sorthow' => 'DESC'));
                $out .= $this->output->action_icon($url, new pix_icon('t/sort_desc', get_string('sortdesc', 'advwork')),
                    null, array('class' => 'iconsort sort desc'));
            }
        }
        return $out;
}

    /**
     * @param stdClass $participant
     * @param array $userinfo
     * @return string
     */
    protected function helper_grading_report_participant(stdclass $participant, array $userinfo) {
        $userid = $participant->userid;
        $out  = $this->output->user_picture($userinfo[$userid], array('courseid' => $this->page->course->id, 'size' => 35));
        $out .= html_writer::tag('span', fullname($userinfo[$userid]));

        return $out;
    }

    /**
     * @param stdClass $participant
     * @return string
     */
    protected function helper_grading_report_name(stdclass $participant) {
        $out = html_writer::tag('span', $participant->name);

        return $out;
    }

    /**
     * @param stdClass $participant
     * @return string
     */
    protected function helper_grading_report_submission(stdclass $participant) {
        global $CFG;

        if (is_null($participant->submissionid)) {
            $out = $this->output->container(get_string('nosubmissionfound', 'advwork'), 'info');
        } else {
            $url = new moodle_url('/mod/advwork/submission.php',
                                  array('cmid' => $this->page->context->instanceid, 'id' => $participant->submissionid));
            $out = html_writer::link($url, format_string($participant->submissiontitle), array('class'=>'title'));

            $lastmodified = get_string('userdatemodified', 'advwork', userdate($participant->submissionmodified));
            $out .= html_writer::tag('div', $lastmodified, array('class' => 'lastmodified'));
        }

        return $out;
    }

    /**
     * @todo Highlight the nulls
     * @param stdClass|null $assessment
     * @param bool $shownames
     * @param string $separator between the grade and the reviewer/author
     * @return string
     */
    protected function helper_grading_report_assessment($assessment, $shownames, array $userinfo, $separator) {
        global $CFG;

        if (is_null($assessment)) {
            return get_string('nullgrade', 'advwork');
        }
        $a = new stdclass();
        $a->grade = is_null($assessment->grade) ? get_string('nullgrade', 'advwork') : $assessment->grade;
        $a->gradinggrade = is_null($assessment->gradinggrade) ? get_string('nullgrade', 'advwork') : $assessment->gradinggrade;
        $a->weight = $assessment->weight;
        // grrr the following logic should really be handled by a future language pack feature
        if (is_null($assessment->gradinggradeover)) {
            if ($a->weight == 1) {
                $grade = get_string('formatpeergrade', 'advwork', $a);
            } else {
                $grade = get_string('formatpeergradeweighted', 'advwork', $a);
            }
        } else {
            $a->gradinggradeover = $assessment->gradinggradeover;
            if ($a->weight == 1) {
                $grade = get_string('formatpeergradeover', 'advwork', $a);
            } else {
                $grade = get_string('formatpeergradeoverweighted', 'advwork', $a);
            }
        }
        $url = new moodle_url('/mod/advwork/assessment.php',
                              array('asid' => $assessment->assessmentid));
        $grade = html_writer::link($url, $grade, array('class'=>'grade'));

        if ($shownames) {
            $userid = $assessment->userid;
            $name   = $this->output->user_picture($userinfo[$userid], array('courseid' => $this->page->course->id, 'size' => 16));
            $name  .= html_writer::tag('span', fullname($userinfo[$userid]), array('class' => 'fullname'));
            $name   = $separator . html_writer::tag('span', $name, array('class' => 'user'));
        } else {
            $name   = '';
        }

        return $this->output->container($grade . $name, 'assessmentdetails');
    }

    /**
     * Formats the aggreagated grades
     */
    protected function helper_grading_report_grade($grade, $over=null) {
        $a = new stdclass();
        $a->grade = is_null($grade) ? get_string('nullgrade', 'advwork') : $grade;
        if (is_null($over)) {
            $text = get_string('formataggregatedgrade', 'advwork', $a);
        } else {
            $a->over = is_null($over) ? get_string('nullgrade', 'advwork') : $over;
            $text = get_string('formataggregatedgradeover', 'advwork', $a);
        }
        return $text;
    }

    ////////////////////////////////////////////////////////////////////////////
    // Static helpers
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Helper method dealing with the fact we can not just fetch the output of moodleforms
     *
     * @param moodleform $mform
     * @return string HTML
     */
    protected static function moodleform(moodleform $mform) {

        ob_start();
        $mform->display();
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

    /**
     * Helper function returning the n-th item of the array
     *
     * @param array $a
     * @param int   $n from 0 to m, where m is th number of items in the array
     * @return mixed the $n-th element of $a
     */
    protected static function array_nth(array $a, $n) {
        $keys = array_keys($a);
        if ($n < 0 or $n > count($keys) - 1) {
            return null;
        }
        $key = $keys[$n];
        return $a[$key];
    }

    /**
     * Tries to guess the fullname format set at the site
     *
     * @return string fl|lf
     */
    protected static function fullname_format() {
        $fake = new stdclass(); // fake user
        $fake->lastname = 'LLLL';
        $fake->firstname = 'FFFF';
        $fullname = get_string('fullnamedisplay', '', $fake);
        if (strpos($fullname, 'LLLL') < strpos($fullname, 'FFFF')) {
            return 'lf';
        } else {
            return 'fl';
        }
    }

}

/**
 * Holds data about the first and third quartile for all the capabilities (single session or cumulated)
 */
class grading_report_quartiles {
    public $submissiongradesinglesessionfirstquartile = null;

    public $submissiongradesinglesessionthirdquartile = null;

    public $submissiongradecumulatedfirstquartile = null;

    public $submissiongradecumulatedthirdquartile = null;

    public $competencesinglesessionfirstquartile = null;

    public $competencesinglesessionthirdquartile = null;

    public $competencecumulatedfirstquartile = null;

    public $competencecumulatedthirdquartile = null;

    public $assessmentcapabilitysinglesessionfirstquartile = null;

    public $assessmentcapabilitysessionthirdquartile = null;

    public $assessmentcapabilitycumulatedfirstquartile = null;

    public $assessmentcapabilitycumulatedthirdquartile = null;
}
