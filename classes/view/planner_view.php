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

use block_disealytics\data\course;
use block_disealytics\data\planner;
use block_disealytics\learningdata;
use coding_exception;
use core_date;
use DateTime;
use dml_exception;
use Exception;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/blocks/disealytics/classes/view/base_view.php');
require_once($CFG->dirroot . '/blocks/disealytics/classes/learningdata.php');
require_once($CFG->dirroot . '/blocks/disealytics/classes/data/planner.php');

/**
 * Class planner_view
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planner_view extends base_view {
    /**
     * Title of the view.
     */
    private const TITLE = 'planner-view';
    /**
     * @var learningdata
     */
    private learningdata $learningdata;

    /**
     * Constructor for the planner view
     *
     * @param $timeframe
     * @param learningdata $learningdata
     * @throws Exception
     */
    public function __construct($timeframe, learningdata $learningdata) {
        parent::__construct($timeframe);
        $this->learningdata = $learningdata;
    }

    /**
     * Build the planner.
     *
     * @param planner $planner
     * @return array of planner data
     * @throws coding_exception
     * @throws dml_exception
     * @throws Exception
     */
    private function block_disealytics_build_planner(planner $planner): array {
        global $COURSE, $USER;
        $outputplanner = [];

        $defaultcourseid = $COURSE->id;
        $allusercourses = course::get_all_courses_of_user_current_semester($USER->id);
        $outputplanner["courses"] = array_values($allusercourses);

        foreach ($outputplanner["courses"] as $course) {
            $course->isDefault = ($course->courseid === $defaultcourseid);
        }

        $outputplanner["this_day"] = $planner->block_disealytics_get_this_day();
        $outputplanner["this_month"] = $planner->block_disealytics_get_this_month();
        $outputplanner["this_year"] = $planner->block_disealytics_get_this_year();

        $outputplanner["this_month_name"] = $planner->block_disealytics_get_this_month_name();

        foreach ($planner->block_disealytics_get_weekday_names() as $name) {
            $outputplanner["daynames_short"][] = ["dayname" => $name];
        }

        foreach ($planner->block_disealytics_get_days_for_month() as $daydata) {
            $outputplanner["daynumbers"][] = $daydata;
        }
        return $outputplanner;
    }

    /**
     * Get the dates for the planner.
     *
     * @param planner $planner
     * @param int $courseid
     * @return array of dates
     * @throws coding_exception
     * @throws Exception
     */
    private function get_dates_for_planner(planner $planner, int $courseid): array {
        $outputdates = [];

        // Get data from database.
        $events = $planner->get_dates_for_course($courseid);

        // Format chronologically.
        $outputdates['time_container'] = [
                'all_events' => [],
                'events_today' => [],
                'events_thismonth' => [],
                'events_inpast' => [],
        ];

        $today = new DateTime("now", core_date::get_user_timezone_object());
        $thismonthstart = (new DateTime())
            ->setDate($planner->block_disealytics_get_this_year(), $planner->block_disealytics_get_this_month(), 1)
            ->setTime(0, 0);
        $thismonthend = (new DateTime())
            ->setDate($planner->block_disealytics_get_this_year(), $planner->block_disealytics_get_this_month(), 1)
            ->modify('last day of this month')
            ->setTime(23, 59, 59);

        foreach ($events as $event) {
            $start = DateTime::createFromFormat('U', $event->timestampStart);

            $outputdates['time_container']['all_events'][] = $event;

            if ($start->format('Y-m-d') === $today->format('Y-m-d')) {
                $outputdates['time_container']['events_today'][] = $event;
            }
            if ($start >= $thismonthstart && $start <= $thismonthend) {
                $outputdates['time_container']['events_thismonth'][] = $event;
            }
            if ($start <= $thismonthstart && $start <= $today) {
                $outputdates['time_container']['events_inpast'][] = $event;
            }

            // Set event type.
            if ($event->eventType == 1) {
                $event->eventTypeConference = get_string('planner_event_type_value_1', 'block_disealytics');
                $event->eventTypeName = get_string('planner_event_type_value_1', 'block_disealytics');
            }
            if ($event->eventType == 2) {
                $event->eventTypePresence = get_string('planner_event_type_value_2', 'block_disealytics');
                $event->eventTypeName = get_string('planner_event_type_value_2', 'block_disealytics');
            }
            if ($event->eventType == 3) {
                $event->eventTypeOther = get_string('planner_event_type_value_3', 'block_disealytics');
                $event->eventTypeName = get_string('planner_event_type_value_3', 'block_disealytics');
            }
        }

        // Sort goals chronologically within each container.
        foreach ($outputdates['time_container'] as &$container) {
            usort($container, function ($a, $b) {
                return ($a->timestampStart - $b->timestampStart);
            });
        }

        return $outputdates;
    }

    /**
     * Get the output for the viewmode: module.
     * @return void
     * @throws coding_exception|dml_exception
     * @throws Exception
     */
    protected function get_module_output(): void {
        global $COURSE;
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            // Build planner.
            $selecteddate = json_decode(get_user_preferences('block_disealytics_planner_currentdate', 0), true);
            $planner = planner::block_disealytics_create_planner($selecteddate);
            $this->output["viewmode_module"]['calendar'] = $this->block_disealytics_build_planner($planner);
            $this->output["viewmode_module"]['events'] = $this->get_dates_for_planner($planner, $COURSE->id);
        }

        // Texts.
        $this->output["title"] =
                get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] =
                get_string('planner_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] =
                get_string('planner-view_help_info_text_expanded', 'block_disealytics');
    }

    /**
     * Get the output for the viewmode: halfyear.
     * @return void
     * @throws dml_exception
     * @throws moodle_exception
     * @throws coding_exception
     * @throws Exception
     */
    protected function get_halfyear_output(): void {
        global $USER;
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            // Build planner.
            $selecteddate = json_decode(get_user_preferences('block_disealytics_planner_currentdate', 0), true);
            $planner = planner::block_disealytics_create_planner($selecteddate);
            $this->output["viewmode_halfyear"]['calendar'] = $this->block_disealytics_build_planner($planner);
            $allusercourses = course::get_all_courses_of_user_current_semester($USER->id);
            $this->output["viewmode_halfyear"]['course'] = [];
            foreach ($allusercourses as $course) {
                $courseevents = $this->get_dates_for_planner($planner, $course->courseid);
                $course->events = $courseevents;
                $this->output["viewmode_halfyear"]['course'][] = $course;
            }
        }

        // Texts.
        $this->output["title"] =
                get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] =
                get_string('planner_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] =
                get_string('planner-view_help_info_text_expanded', 'block_disealytics');
    }

    /**
     * Get the output for the viewmode: global.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws Exception
     */
    protected function get_global_output(): void {
        global $USER;
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            // Build planner.
            $selecteddate = json_decode(get_user_preferences('block_disealytics_planner_currentdate', 0), true);
            $planner = planner::block_disealytics_create_planner($selecteddate);
            $this->output["viewmode_global"]['calendar'] = $this->block_disealytics_build_planner($planner);
            $allusercourses = course::get_all_courses_of_user($USER->id);
            $semesterfilter = get_user_preferences("block_disealytics_" . self::TITLE, reset($allusercourses)->categoryname);
            $this->output["viewmode_global"]['course'] = [];
            $this->output["categories"] = [];
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
                    $courseevents = $this->get_dates_for_planner($planner, $course->courseid);
                    $course->events = $courseevents;
                    $this->output["viewmode_global"]['course'][] = $course;
                }
            }
        }

        // Texts.
        $this->output["title"] =
                get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] =
                get_string('planner_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] =
                get_string('planner-view_help_info_text_expanded', 'block_disealytics');
    }
}
