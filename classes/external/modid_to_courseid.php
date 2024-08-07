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
 * Web service to change the mod id to the course id.
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
use external_api;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;

/**
 * Class modid_to_courseid
 */
class modid_to_courseid extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
                'modid' => new external_value(
                    PARAM_INT,
                    'ID of a corurse module',
                    VALUE_REQUIRED
                ),
        ]);
    }

    /**
     * Executes the service.
     *
     * @param int $modid the id of the course module to get the corresponding course to
     * @return false|int $coursemod->course
     * @throws invalid_parameter_exception
     * @throws dml_exception
     */
    public static function execute(int $modid) {

        self::validate_parameters(self::execute_parameters(), [
                'modid' => $modid,
        ]);
        global $DB;
        $coursemod = $DB->get_record('course_modules', ['id' => $modid], 'id, course');
        return $coursemod->course;
    }

    /**
     * Describes the return structure of the service
     *
     * @return external_value jsonobj
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_INT, 'CourseID for the given Module ID');
    }
}
