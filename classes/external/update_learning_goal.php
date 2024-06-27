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
 * Web service to update a learning goal.
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_disealytics\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

use dml_exception;
use block_disealytics\learningdata;
use external_api;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;
use stdClass;

/**
 * Class update_learning_goal
 */
class update_learning_goal extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'goalid' => new external_value(
                PARAM_INT,
                'Goal id',
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
            'finished' => new external_value(
                PARAM_BOOL,
                'Checks if target is finished',
                VALUE_REQUIRED
            ),
        ]);
    }

    /**
     * Executes the service.
     *
     *
     * @param string $goalid the goal ID
     * @param int $duedate the goal ID
     * @param string $goalname the goal name
     * @param bool $finished finished target
     * @return string $result
     * @throws invalid_parameter_exception
     * @throws dml_exception
     */
    public static function execute(string $goalid, int $duedate, string $goalname, bool $finished): string {

        global $CFG;

        // Validate parameters.
        self::validate_parameters(self::execute_parameters(), [
                'goalid' => $goalid,
                'duedate' => $duedate,
                'goalname' => $goalname,
                'finished' => $finished,
        ]);
        require_once($CFG->dirroot . '/blocks/disealytics/classes/learningdata.php');
        $goal = new stdClass();
        $goal->id = $goalid;
        $goal->description = $goalname;
        $goal->duedate = $duedate;
        $goal->finished = $finished;
        learningdata::update_goal($goal);
        return 'Goal ID: ' . $goalid . ' updated name with: ' . $goalname . ' and due date ' . $duedate . '.';
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
