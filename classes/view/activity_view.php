<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_disealytics\view;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/blocks/disealytics/classes/view/base_view.php');

require_once($CFG->dirroot . '/blocks/disealytics/classes/data/task.php');

use block_disealytics\data\course;
use block_disealytics\data\style;
use block_disealytics\data\task;
use block_disealytics\learningdata;
use coding_exception;
use core\chart_bar;
use core\chart_series;
use DateTime;
use dml_exception;
use Exception;
use stdClass;

/**
 * Class activity_view
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_view extends base_view {
    /**
     * @var learningdata
     */
    private learningdata $learningdata;
    /**
     * Title of the view.
     */
    private const TITLE = 'activity-view';

    /**
     * activity_view constructor.
     *
     * @param mixed $timeframe
     * @param learningdata $learningdata
     * @throws Exception
     */
    public function __construct($timeframe, learningdata $learningdata) {
        parent::__construct($timeframe);
        $this->learningdata = $learningdata;
    }


    /**
     * Get the output for the viewmode: module.
     * @return array
     * @throws coding_exception
     * @throws Exception
     */
    protected function get_module_output(): array {
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            $this->output["viewmode"] = true;
        }
        $this->output["viewmode_module"] = true;

        // Texts.
        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('activity_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('activity_help_info_text_expanded', 'block_disealytics');

        global $COURSE;
        $COURSE->coursename = $COURSE->fullname;
        $COURSE->courseid = $COURSE->id;
        $courseout = $this->get_course_output($COURSE);
        $this->output = array_merge($this->output, $courseout);
        return $this->output;
    }

    /**
     * Get the output for the courses.
     *
     * @param stdClass $course
     * @param bool $global
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    private function get_course_output(stdClass $course, bool $global = false): array {
        $monday = (new DateTime("previous week monday"))->format("U");
        $sunday = (new DateTime("now"))->format("U");
        $output = null;
        $output["nodata"] = false;

        $output["coursename"] = $course->coursename;
        $tasks = task::block_disealytics_get_user_tasks($monday, $sunday, $course->courseid);
        $output["datadate"] = get_string("nodata", 'block_disealytics');
        if (count($tasks) > 0) {
            global $DB;
            // Get Time of last run of Log Task.
            $transformtime = 0;
            $transformtimes = array_column(
                $DB->get_records('task_log', ["classname" => "block_disealytics\\task\\tasktransform"]),
                'timestart'
            );
            if (count($transformtimes) > 0) {
                $transformtime = max($transformtimes);
            }

            if ($transformtime) {
                $output['datadate'] = userdate($transformtime, get_string('strftimedatefullshort', 'langconfig'));
            }
            $groupedtasks = task::block_disealytics_group_and_reduce($tasks);
            // Reverse it for easy popping.
            $groupedtasks = array_reverse($groupedtasks, true);
            $chart1 = new chart_bar();
            $chart1->set_stacked(true);
            $groupedtasks = task::block_disealytics_make_task_buckets($groupedtasks, "weekdays");
            $colors =
                [style::BLOCK_DISEALYTICS_ORANGE, style::BLOCK_DISEALYTICS_SECONDARY_BLUE,
                    style::BLOCK_DISEALYTICS_HIGHLIGHT_BLUE];
            while (count($groupedtasks) > 0) {
                $key = array_key_last($groupedtasks);
                $values = array_pop($groupedtasks);
                $series = new chart_series("$key", array_values($values));
                $color = array_pop($colors);
                $series->set_color($color);
                array_unshift($colors, $color);
                $chart1->add_series($series);
            }
            $chart1->set_labels(array_map(function ($day) {
                return substr(get_string($day, 'block_disealytics'), 0, 2);
            }, [
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday',
                'Sunday',
            ]));
            $output['maincharts'][] = ['chartdata' => json_encode($chart1), 'withtable' => true,
                'uniqid' => uniqid('block_disealytics_')];
                $dates = learningdata::get_current_halfyear_dates();
                $start = $dates["start"];
                $end = $dates["end"];
                $halfyeartasks = task::block_disealytics_get_user_tasks($start->format("U"), $end->format("U"), $course->courseid);
                $halfyeartasks = task::block_disealytics_group_and_reduce($halfyeartasks);
                $halfyeartasks = array_reverse($halfyeartasks, true);

                $halfyeartasks = task::block_disealytics_make_task_buckets(
                    $halfyeartasks,
                    "weeks",
                    base_view::get_weeknrs($start, $end)
                );
                $chart2 = new chart_bar();
                $chart2->set_stacked(true);
            while (count($halfyeartasks) > 0) {
                $key = array_key_last($halfyeartasks);
                $values = array_pop($halfyeartasks);
                $series = new chart_series("$key", array_values($values));
                $series->set_color(array_pop($colors));
                $chart2->add_series($series);
            }
                $chart2->set_labels(base_view::get_weeknrs($start, $end));
                $output['detailcharts'][] = ['chartdata' => json_encode($chart2), 'withtable' => true,
                    'uniqid' => uniqid('block_disealytics_')];
        } else {
            $output["nodata"] = true;
        }
        return $output;
    }

    /**
     * Get the output for the viewmode: halfyear.
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_halfyear_output(): array {
        global $USER;
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            $this->output["viewmode"] = true;
        }
        $this->output["viewmode_halfyear"] = true;
        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('activity_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('activity_help_info_text_expanded', 'block_disealytics');

        $outputs = [];
        $allcoursesofusercurrentsemester = course::get_all_courses_of_user_current_semester($USER->id);
        foreach ($allcoursesofusercurrentsemester as $course) {
            $outputs[] = $this->get_course_output($course);
        }
        $this->output["nodata"] = true;
        foreach ($outputs as $output) {
            if ($output["nodata"] === false) {
                $this->output["nodata"] = false;
                break;
            }
        }
        $this->output["outputs"] = $outputs;
        return $this->output;
    }

    /**
     * Get the output for the viewmode: global.
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_global_output(): array {
        global $USER;
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            $this->output["viewmode"] = true;
        }
        $this->output["viewmode_global"] = true;
        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('activity_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('activity_help_info_text_expanded', 'block_disealytics');

        $outputs = [];
        $this->output["categories"] = [];
        $allusercourses = course::get_all_courses_of_user($USER->id);
        $semesterfilter = get_user_preferences("block_disealytics_" . self::TITLE, reset($allusercourses)->categoryname);
        foreach ($allusercourses as $course) {
            $categorydata = $course->categoryname;

            $issemester = ($semesterfilter === $categorydata);
            $categoryexists = false;
            foreach ($this->output["categories"] as $category) {
                if ($category["name"] === $categorydata) {
                    $categoryexists = true;
                    break;
                }
            }

            if (!$categoryexists) {
                $this->output["categories"][] = ["name" => $categorydata, "selected" => $issemester];
            }

            if ($issemester) {
                $outputs[] = $this->get_course_output($course, true);
            }
        }

        $this->output["nodata"] = true;
        foreach ($outputs as $output) {
            if ($output["nodata"] === false) {
                $this->output["nodata"] = false;
                break;
            }
        }

        $this->output["outputs"] = $outputs;
        return $this->output;
    }
}
