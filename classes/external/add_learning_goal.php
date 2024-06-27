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
 * Web service to add learning goals data.
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_disealytics\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

use context_course;
use dml_exception;
use block_disealytics\learningdata;

use external_api;
use external_function_parameters;
use external_value;
use moodle_exception;
use stdClass;

/**
 * Class add_learning_goal
 */
class add_learning_goal extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {

        return new external_function_parameters([
                'courseid' => new external_value(
                    PARAM_INT,
                    'course id',
                    VALUE_REQUIRED
                ),
                'duedate' => new external_value(
                    PARAM_INT,
                    'A date, that shows the deadline of the goal',
                    VALUE_REQUIRED
                ),
                'goalname' => new external_value(
                    PARAM_RAW,
                    'A name, that is saved for the goal',
                    VALUE_REQUIRED
                ),
        ]);
    }

    /**
     * Executes the process of creating a new learning goal.
     *
     * @param int    $courseid         The ID of the course in which the goal is being created.
     * @param int    $duedate          The due date timestamp for the goal.
     * @param string $goalname          The description of the new goal.
     * @return string                  A message indicating the success of creating the new goal.
     * @throws moodle_exception       If the parameters or data validation fails.
     * @throws dml_exception          If there are database-related errors.
     */
    public static function execute(int $courseid, int $duedate, string $goalname): string {
        global $DB, $PAGE, $CFG, $USER;

        self::validate_parameters(self::execute_parameters(), [
                'courseid' => $courseid,
                'duedate' => $duedate,
                'goalname' => $goalname]);
        require_once($CFG->dirroot . '/blocks/disealytics/classes/learningdata.php');

        $context = context_course::instance($courseid);
        $PAGE->set_context($context);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $PAGE->set_course($course);

        $goal = new stdClass();
        $goal->userid = $USER->id;
        $goal->courseid = $courseid;
        $goal->description = $goalname;
        $goal->duedate = $duedate;
        $goal->finished = 0;
        learningdata::add_goal_to_database($goal);

        return 'New goal successfully created.';
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
