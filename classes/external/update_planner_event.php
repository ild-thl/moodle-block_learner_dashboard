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
 * Web service to update the planner dates
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
use dml_exception;
use external_api;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;
use stdClass;

/**
 * Class update_planner_event
 */
class update_planner_event extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
                'updatetype' => new external_value(
                    PARAM_TEXT,
                    'The updatetype of the event (add, delete or update).',
                    VALUE_REQUIRED
                ),
                'dateid' => new external_value(
                    PARAM_INT,
                    'The date id, 0 if no date is created yet.',
                    VALUE_REQUIRED
                ),
                'name' => new external_value(
                    PARAM_RAW,
                    'A name, that is saved for the date.',
                    VALUE_OPTIONAL
                ),
                'courseid' => new external_value(
                    PARAM_INT,
                    'The course id, that is saved for the date.',
                    VALUE_OPTIONAL
                ),
                'timestart' => new external_value(
                    PARAM_INT,
                    'The timestamp the date starts.',
                    VALUE_OPTIONAL
                ),
                'timeduration' => new external_value(
                    PARAM_INT,
                    'The time the date lasts, 0 if no end date or duration is given.',
                    VALUE_OPTIONAL
                ),
                'location' => new external_value(
                    PARAM_RAW,
                    'A location, that is saved for the date.',
                    VALUE_OPTIONAL
                ),
                'eventtype' => new external_value(
                    PARAM_INT,
                    'An eventtype, that is saved for the date. Options: 1, 2, 3',
                    VALUE_OPTIONAL
                ),
                'repetitions' => new external_value(
                    PARAM_INT,
                    'A number of repetitions or 0 if there is no repetition.',
                    VALUE_OPTIONAL
                ),
        ]);
    }

    /**
     * Executes the service.
     *
     * @param string $updatetype
     * @param int|null $dateid
     * @param string|null $name
     * @param int|null $courseid
     * @param int|null $timestart
     * @param int|null $timeduration
     * @param string|null $location
     * @param int|null $eventtype
     * @param int|null $repetitions
     * @return string
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function execute(
        string $updatetype,
        ?int $dateid,
        ?string $name = null,
        ?int $courseid = null,
        ?int $timestart = null,
        ?int $timeduration = null,
        ?string $location = null,
        ?int $eventtype = null,
        ?int $repetitions = null
    ): string {
        global $USER;

        self::validate_parameters(self::execute_parameters(), [
                'updatetype' => $updatetype,
                'dateid' => $dateid,
                'name' => $name,
                'courseid' => $courseid,
                'timestart' => $timestart,
                'timeduration' => $timeduration,
                'location' => $location,
                'eventtype' => $eventtype,
                'repetitions' => $repetitions,
        ]);

        if ($updatetype == 'add') {
            if ($repetitions > 0) {
                $dateobjects = [];
                // Create date objects with incremented timestamps.
                for ($i = 0; $i < $repetitions; $i++) {
                    $date = new stdClass();
                    $date->name = $name;
                    $date->courseid = $courseid;
                    $date->userid = $USER->id;
                    $date->location = $location;
                    $date->eventtype = $eventtype;
                    // Increment by a week (in seconds).
                    $date->timestart = $timestart + ($i * 7 * 24 * 60 * 60);
                    $date->timeduration = $timeduration;
                    $dateobjects[] = $date;
                }
                planner::add_date_series_to_database($dateobjects);
            } else {
                $date = new stdClass();
                $date->name = $name;
                $date->courseid = $courseid;
                $date->userid = $USER->id;
                $date->location = $location;
                $date->eventtype = $eventtype;
                $date->timestart = $timestart;
                $date->timeduration = $timeduration;
                $date->repeatid = 0;
                planner::add_date_to_database($date);
            }
        } else if ($updatetype == 'update') {
            $date = new stdClass();
            $date->id = $dateid;
            $date->name = $name;
            $date->courseid = $courseid;
            $date->userid = $USER->id;
            $date->location = $location;
            $date->eventtype = $eventtype;
            $date->timestart = $timestart;
            $date->timeduration = $timeduration;
            $date->repeatid = 0;
            planner::update_date($date);
        } else if ($updatetype == 'delete') {
            planner::delete_date($dateid);
        }

        return 'Operation was successful.';
    }

    /**
     * Describes the return structure of the service
     *
     * @return external_value jsonobj
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_RAW);
    }
}
