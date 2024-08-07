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
require_once($CFG->dirroot . '/blocks/disealytics/classes/data/assignment.php');

use block_disealytics\data\assignment;
use block_disealytics\data\course;
use coding_exception;
use dml_exception;
use moodle_exception;

/**
 * Class assignment_view
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignment_view extends base_view {
    /**
     * Title of the view.
     */
    private const TITLE = 'assignment-view';

    /**
     * Filter courses by category.
     *
     * @param string $selectedcategory
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function filter_courses_by_category(string $selectedcategory): void {
        global $USER;

        $output = [];
        $output["title"] = get_string(self::TITLE, 'block_disealytics');
        $output["help_info_text"] = get_string('assignment_help_info_text', 'block_disealytics');
        $output["help_info_text_expanded"] = get_string('assignment_help_info_text_expanded', 'block_disealytics');
        $output["viewmode_global"] = true;
        $output["tables"] = [];
        $output["categories"] = [];

        $allusercourses = course::get_all_courses_of_user($USER->id);

        foreach ($allusercourses as $course) {
            if ($course->categoryname == $selectedcategory) {
                $assignments = assignment::block_disealytics_get_course_assignments((int) $course->courseid);

                if ($course->courseid != 1 && count($assignments) > 0) {
                    $table = [];
                    $table["table"] = 'yes';
                    $table["assignstring"] = get_string('assignment', 'block_disealytics');
                    $table["submitstring"] = get_string('status', 'block_disealytics');
                    $table["coursename"] = $course->coursename;
                    $table["assigns"] = [];

                    foreach ($assignments as $assignment) {
                        $tableelement = [
                                "html_a" => $assignment->block_disealytics_build_name_link(),
                                "status" => $assignment->block_disealytics_gen_status_html(),
                        ];

                        $table["assigns"][] = $tableelement;
                    }

                    $output["tables"][] = $table;
                }
            }
        }
    }

    /**
     * Get the output data for the assignment view.
     * This function retrieves the output data for the assignment view, including title, help information, and assignment details.
     *
     * @throws coding_exception
     * @throws moodle_exception
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
        global $COURSE;
        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('assignment_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('assignment_help_info_text_expanded', 'block_disealytics');

        if ($COURSE->id != 1 && count(assignment::block_disealytics_get_course_assignments($COURSE->id)) > 0) {
            $assignments = assignment::block_disealytics_get_course_assignments($COURSE->id);
            $this->output['table'] = 'yes';
            $this->output["assignstring"] = get_string('assignment', 'block_disealytics');
            $this->output["submitstring"] = get_string('status', 'block_disealytics');
            $this->output["assigns"] = [];
            foreach ($assignments as $index => $assignment) {
                $tableelement = [
                        "html_a" => $assignment->block_disealytics_build_name_link(),
                        "status" => $assignment->block_disealytics_gen_status_html(),
                ];

                $this->output["table_elements_all"][] = $tableelement;
                if ($index < 3) {
                    $this->output["table_elements_first_three"][] = $tableelement;
                } else if ($index == 4) {
                    $this->output["table_elements_fourth_assign"][] = [
                            "name" => $assignment->block_disealytics_get_assignment_name(),
                            "status" => $assignment->block_disealytics_gen_status_html(),
                    ];
                }
            }
        } else {
            $this->output["nodata"] = true;
            $this->output['info'] = get_string('nodata', 'block_disealytics');
        }

        if (count(assignment::block_disealytics_get_course_assignments($COURSE->id)) > 3) {
            $this->output["more_than_three"] = 'yes';
        }
    }

    /**
     * Get the output data for the assignment view in halfyear mode.
     * @throws moodle_exception
     * @throws coding_exception
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
            $this->output["viewmode_halfyear"] = true;
        }
        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('assignment_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('assignment_help_info_text_expanded', 'block_disealytics');
        $this->output["tables"] = [];

        $allusercourses = course::get_all_courses_of_user_current_semester($USER->id);

        $this->output["data_in_course"] = false;

        foreach ($allusercourses as $course) {
            $assignments = assignment::block_disealytics_get_course_assignments($course->courseid);

            $this->block_disealytics_generate_assignments($course, $assignments);
        }
    }

    /**
     * Get the output data for the assignment view in global mode.
     * @throws coding_exception
     * @throws moodle_exception
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
        $this->output["help_info_text"] = get_string('assignment_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('assignment_help_info_text_expanded', 'block_disealytics');
        $this->output["tables"] = [];
        $this->output["categories"] = [];
        $allusercourses = course::get_all_courses_of_user($USER->id);
        $semesterfilter = get_user_preferences("block_disealytics_" . self::TITLE, reset($allusercourses)->categoryname);
        $this->output["data_in_course"] = false;
        foreach ($allusercourses as $course) {
            $assignments = assignment::block_disealytics_get_course_assignments((int) $course->courseid);
            if ($semesterfilter === $course->categoryname || !$semesterfilter) {
                $this->block_disealytics_generate_assignments($course, $assignments);
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
    }

    /**
     * Generate the assignments for the view.
     * @param $course
     * @param array $assignments
     * @return void
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function block_disealytics_generate_assignments($course, array $assignments): void {
        if ($course->courseid != 1 && count($assignments) > 0) {
            $table = [];
            $table["table"] = true;
            $table["assignstring"] = get_string('assignment', 'block_disealytics');
            $table["submitstring"] = get_string('status', 'block_disealytics');
            $table["coursename"] = $course->coursename;
            $table["assigns"] = [];
            $table["first_three"] = [];
            $table["after_first_three"] = [];
            $table["table_elements_fourth_assign"] = [];
            foreach ($assignments as $index => $assignment) {
                $tableelement = [
                        "html_a" => $assignment->block_disealytics_build_name_link(),
                        "status" => $assignment->block_disealytics_gen_status_html(),
                ];
                $table["assigns"][] = $tableelement;

                if ($index < 3) {
                    $table["first_three"][] = $tableelement;
                } else if ($index === 4) {
                    $table["table_elements_fourth_assign"][] = [
                            "name" => $assignment->block_disealytics_get_assignment_name(),
                            "status" => $assignment->block_disealytics_gen_status_html(),
                    ];
                }
                if ($index >= 3) {
                    $table["after_first_three"][] = $tableelement;
                }
            }
            $this->output["tables"][] = $table;
            $this->output["first_three_elements"] = $table["first_three"];
            $this->output["after_first_three_elements"] = $table["after_first_three"];
            $this->output["data_in_course"] = true;
        }
        if (count(assignment::block_disealytics_get_course_assignments($course->courseid)) > 3) {
            $this->output["more_than_three"][] = 'yes';
        }
    }
}
