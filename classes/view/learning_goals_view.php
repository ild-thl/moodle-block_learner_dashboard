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
use block_disealytics\learningdata;
use coding_exception;
use DateTime;
use dml_exception;
use Exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/blocks/disealytics/classes/view/base_view.php');
require_once($CFG->dirroot . '/blocks/disealytics/classes/learningdata.php');

/**
 * Class learning_goals_view
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_goals_view extends base_view {
    /**
     * Title of the view.
     */
    private const TITLE = 'learning-goals-view';
    /**
     * @var learningdata
     */
    private learningdata $learningdata;

    /**
     * learning_goals_view constructor.
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
     * Get the output for the viewmode: module.
     * @return void
     * @throws coding_exception|dml_exception
     * @throws Exception
     */
    protected function get_module_output(): void {
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
        $this->output["title"] =
                get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] =
                get_string('learning-goals_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] =
                get_string('learning-goals_help_info_text_expanded', 'block_disealytics');

        // Goals.
        $goals = $this->learningdata->get_goals();
        $finishedgoals = $this->learningdata->get_finished_goals();
        $this->set_learning_goals($goals, $finishedgoals);

        if (count($goals) === 0) {
            $this->output["nodata"] = true;
        }
    }

    /**
     * Set the learning goals.
     *
     * @param array $goals
     * @param array $finishedgoals
     * @return void
     * @throws Exception
     */
    private function set_learning_goals(array $goals, array $finishedgoals): void {
        $this->output['goals'] = $goals;
        $this->output['number_of_goals'] = count($goals);
        $this->output['number_of_finished_goals'] = count($finishedgoals);

        // Format chronologically.
        $this->output['time_container'] = [
                'goals_today' => [],
                'goals_tomorrow' => [],
                'goals_thisweek' => [],
                'goals_thismonth' => [],
                'goals_infuture' => [],
                'goals_finished_past' => [],
        ];

        $today = new DateTime();
        $tomorrow = (new DateTime())->modify('+1 day');
        $thisweekend = (new DateTime())->modify('next Sunday')->setTime(23, 59, 59);
        $thismonthend = (new DateTime())->modify('last day of this month')->setTime(23, 59, 59);

        foreach ($goals as $goal) {
            $duedate = new DateTime($goal->formattedduedate);

            if ($goal->finished === 'true') {
                $this->output['time_container']['goals_finished_past'][] = $goal;
            } else if ($duedate->format('Y-m-d') === $today->format('Y-m-d') || $duedate < $today) {
                $this->output['time_container']['goals_today'][] = $goal;
            } else if ($duedate->format('Y-m-d') === $tomorrow->format('Y-m-d')) {
                $this->output['time_container']['goals_tomorrow'][] = $goal;
            } else if ($duedate >= $tomorrow && $duedate <= $thisweekend) {
                $this->output['time_container']['goals_thisweek'][] = $goal;
            } else if ($duedate >= $tomorrow && $duedate <= $thismonthend) {
                $this->output['time_container']['goals_thismonth'][] = $goal;
            } else {
                $this->output['time_container']['goals_infuture'][] = $goal;
            }
        }

        // Sort goals chronologically within each container.
        foreach ($this->output['time_container'] as &$container) {
            usort($container, function ($a, $b) {
                return (new DateTime($a->formattedduedate))->getTimestamp() - (new DateTime($b->formattedduedate))->getTimestamp();
            });
        }
    }

    /**
     * Get the output for the viewmode: halfyear.
     * @throws coding_exception
     * @throws Exception
     */
    protected function get_halfyear_output(): void {
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            $this->output["viewmode_halfyear"] = true;
        }
        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('learning-goals_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('learning-goals_help_info_text_expanded', 'block_disealytics');
        $goalsarray = $this->learningdata->get_goals_semester();
        $finishedgoalsarray = $this->learningdata->get_finished_goals_semester();
        $this->set_learning_goals($goalsarray, $finishedgoalsarray);

        if (count($goalsarray) === 0) {
            $this->output["nodata"] = true;
        }
    }

    /**
     * Get the output for the viewmode: global.
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
            $this->output["viewmode_global"] = true;
        }
        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('learning-goals_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('learning-goals_help_info_text_expanded', 'block_disealytics');

        $this->output["categories"] = [];

        $goalsforselectedsemester = [];

        $allusercourses = course::get_all_courses_of_user($USER->id);
        $semesterfilter = get_user_preferences("block_disealytics_" . self::TITLE, reset($allusercourses)->categoryname);

        foreach ($allusercourses as $course) {
            if ($semesterfilter === $course->categoryname) {
                $goalsforselectedsemester[] = $course->courseid;
            }
            $categorydata = $course->categoryname;

            $issemesterfilter = ($semesterfilter === $categorydata);
            $categoryexists = false;
            foreach ($this->output["categories"] as $category) {
                if ($category["name"] === $categorydata) {
                    $categoryexists = true;
                    break;
                }
            }

            if (!$categoryexists) {
                $this->output["categories"][] = ["name" => $categorydata, "selected" => $issemesterfilter];
            }
        }

        $goalsarray = $this->learningdata->get_goals_global($goalsforselectedsemester);
        $finishedgoalsarray = $this->learningdata->get_finished_goals_global($goalsforselectedsemester);
        $this->set_learning_goals($goalsarray, $finishedgoalsarray);

        if (count($goalsarray) === 0) {
            $this->output["nodata"] = true;
        }
    }
}
