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
 * Web service to handle optional inputs.
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_disealytics\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

use block_disealytics\learningdata;
use coding_exception;
use context_course;
use dml_exception;
use external_api;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;
use stdClass;

/**
 * Class optional_input
 */
class optional_input extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
                'calltype' => new external_value(
                    PARAM_RAW,
                    'Defines the type calltype. Calltype might be adding, deleting or updating',
                    VALUE_REQUIRED
                ),
                'id' => new external_value(
                    PARAM_INT,
                    'ID of the optional input',
                    VALUE_OPTIONAL
                ),
                'courseid' => new external_value(
                    PARAM_INT,
                    'currently visiting course id',
                    VALUE_OPTIONAL
                ),
                'name' => new external_value(
                    PARAM_RAW,
                    'Name of the document',
                    VALUE_OPTIONAL
                ),
                'currentpage' => new external_value(
                    PARAM_INT,
                    'Page that the user finished reading last',
                    VALUE_OPTIONAL
                ),
                'lastpage' => new external_value(
                    PARAM_INT,
                    'Final page of the document',
                    VALUE_OPTIONAL
                ),
                'expenditureoftime' => new external_value(
                    PARAM_INT,
                    'Expenditure of time',
                    VALUE_OPTIONAL
                ),
        ]);
    }

    /**
     * Executes the service.
     *
     * @param string $calltype defines the calltype ('add', 'update', 'delete').
     * @param int $id name of the document
     * @param int $courseid currently visiting course id
     * @param string $name name of the document
     * @param int $currentpage last page that was read in the document
     * @param int $lastpage last page of the document
     * @param int $expenditureoftime expenditure of time
     * @return string $result
     * @throws invalid_parameter_exception
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function execute(
        string $calltype,
        int $id = -1,
        int $courseid = -1,
        string $name = '',
        int $currentpage = 0,
        int $lastpage = 1,
        int $expenditureoftime = 0
    ): string {
        global $DB, $PAGE, $CFG;
        self::validate_parameters(self::execute_parameters(), ['calltype' => $calltype, 'id' => $id, 'name' => $name,
                'currentpage' => $currentpage, 'lastpage' => $lastpage, 'courseid' => $courseid,
                'expenditureoftime' => $expenditureoftime]);
        require_once($CFG->dirroot . '/blocks/disealytics/classes/learningdata.php');

        if ($courseid != -1) {
            $context = context_course::instance($courseid);
            $PAGE->set_context($context);
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $PAGE->set_course($course);
        }

        $data = new stdClass();
        if ($id !== -1) {
            $data->id = $id;
        }
        $data->name = $name === '' ? null : $name;
        $data->currentpage = $currentpage;
        $data->lastpage = $lastpage;
        $data->courseid = $courseid;
        $data->expenditureoftime = $expenditureoftime;
        if ($calltype === 'add') {
            learningdata::write_optional_input($data);
        } else if ($calltype === 'update') {
            learningdata::update_optional_input($data);
        } else if ($calltype === 'delete') {
            learningdata::delete_optional_input($id);
        }
        return 'Executed optional input ' . $calltype;
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
