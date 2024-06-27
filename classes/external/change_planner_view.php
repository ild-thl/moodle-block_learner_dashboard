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
 * Web service to change the planner view.
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_disealytics\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

use block_disealytics\data\planner;
use coding_exception;
use context_course;
use core_date;
use DateTime;
use dml_exception;

use Exception;
use external_api;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;

/**
 * Class change_planner_view
 */
class change_planner_view extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
                'courseid' => new external_value(PARAM_INT, 'course id', VALUE_REQUIRED),
                'day' => new external_value(PARAM_INT, 'The selected day', VALUE_REQUIRED),
                'month' => new external_value(PARAM_INT, 'The selected month', VALUE_REQUIRED),
                'year' => new external_value(PARAM_INT, 'The selected year', VALUE_REQUIRED),
                'direction' => new external_value(PARAM_TEXT, 'The direction of the view change', VALUE_REQUIRED),
        ]);
    }

    /**
     * Executes the service.
     *
     * @param int $courseid
     * @param int $day
     * @param int $month
     * @param int $year
     * @param string $direction
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws Exception
     */
    public static function execute(int $courseid, int $day, int $month, int $year, string $direction): string {
        global $DB, $PAGE, $CFG;

        self::validate_parameters(self::execute_parameters(), [
                'courseid' => $courseid,
                'day' => $day,
                'month' => $month,
                'year' => $year,
                'direction' => $direction,
        ]);
        require_once($CFG->dirroot . '/blocks/disealytics/classes/data/planner.php');

        $context = context_course::instance($courseid);
        $PAGE->set_context($context);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $PAGE->set_course($course);

        $now = new DateTime("now", core_date::get_user_timezone_object());

        if ($direction == 'forwards' || $direction == 'backwards') {
            if (($direction == 'forwards' && $month == 12) || ($direction == 'backwards' && $month == 1)) {
                $month = ($direction == 'forwards') ? 1 : 12;
                $year += ($direction == 'forwards') ? 1 : -1;
            } else {
                $month += ($direction == 'forwards') ? 1 : -1;
            }

            // Check if the next month and year are the same as the current month and year.
            if ($month === intval($now->format('m')) && $year === intval($now->format('Y'))) {
                $day = intval($now->format('d'));
            } else {
                $day = 1;
            }
        }

        planner::block_disealytics_change_month_display_in_database($day, $month, $year);

        return 'Date changed.';
    }

    /**
     * Describes the return structure of the service.
     *
     * @return external_value jsonobj
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_RAW);
    }
}
