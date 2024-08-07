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
 * Web service to load the categories.
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_disealytics\external;

use block_disealytics\data\course;
use dml_exception;
use external_api;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;

/**
 * Class category_view
 */
class category_view extends external_api {
    /**
     * Executes the service.
     *
     * @param $selectedcategory
     * @return false|string $result
     * @throws dml_exception|invalid_parameter_exception
     */
    public static function execute($selectedcategory) {
        global $USER;
        self::validate_parameters(self::execute_parameters(), [
                'selectedcategory' => $selectedcategory,
        ]);
        $allusercourses = course::get_all_courses_of_user($USER->id);
        $response = [];
        foreach ($allusercourses as $course) {
            if ($course->categoryname == $selectedcategory) {
                $response[] = $course;
            }
        }
        // Return response to ajax call and hide everything that doesn't match the category.
        return json_encode($response);
    }

    /**
     * Executes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'selectedcategory' => new external_value(
                PARAM_TEXT,
                'Name of the category',
                VALUE_REQUIRED,
                ""
            ),

        ]);
    }
    /**
     * Returns the result.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_RAW, 'Data in JSON-Format');
    }
}
