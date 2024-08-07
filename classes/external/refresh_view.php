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
 * Web service to refresh the view.
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
use Exception;
use block_disealytics\learningdata;
use external_api;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;
use ReflectionClass;
use ReflectionException;

/**
 * Class refresh_view
 */
class refresh_view extends external_api {
    /**
     * Execute the service.
     *
     * @param int $courseid
     * @param string $viewlist
     * @return false|string $result
     * @throws invalid_parameter_exception
     * @throws Exception
     */
    public static function execute(int $courseid, string $viewlist) {

        self::validate_parameters(self::execute_parameters(), [
                'courseid' => $courseid,
                'viewlist' => $viewlist,
        ]);

        global $PAGE, $CFG, $DB;
        $views = json_decode($viewlist);
        require_once($CFG->dirroot . '/blocks/disealytics/classes/learningdata.php');

        $context = context_course::instance($courseid);
        $PAGE->set_context($context);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $PAGE->set_course($course);

        // Load UserSettings or sensible defaults.
        $editing = get_user_preferences('block_disealytics_editing', '0');
        $expandedview = get_user_preferences('block_disealytics_expanded_view', "none");

        // Build response.
        $response = [];
        $response["views"] = [];
        $response["editing"] = $editing;
        $response["expanded_view"] = $expandedview;

        self::processviews($views, $response["views"]);

        return json_encode($response);
    }

    /**
     * Process views and populate the response array.
     *
     * This function processes the given views, instantiates the corresponding view classes,
     * and populates the response array with the necessary data for each enabled view.
     *
     * @param array $views The array of views to process.
     * @param array $response The reference to the response array to populate.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    private static function processviews(array $views, array &$response): void {
        global $CFG;
        $learningdata = new learningdata();
        $timeframe = 0;

        foreach ($views as $view) {
            if ($view->enabled == 1) {
                $viewname = $view->viewname;

                $viewunderscore = str_replace('-', '_', $viewname);
                require_once($CFG->dirroot . '/blocks/disealytics/classes/view/' . $viewunderscore . '.php');

                $view = (new ReflectionClass('block_disealytics\\view\\' .
                        str_replace('-', '_', $viewname)))->newInstance($timeframe, $learningdata);
                $response[$viewname]["template_path"] = "block_disealytics/" . $viewname;
                $response[$viewname]["data"] = [];
                $response[$viewname]["data"]["view_type"] = $viewname;
                $output = $view->get_output();
                foreach (array_keys($output) as $field) {
                    $response[$viewname]["data"][$field] = $output[$field];
                }
            }
        }
    }

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
                'courseid' => new external_value(PARAM_INT, 'courseid', VALUE_DEFAULT, 1),
                'viewlist' => new external_value(PARAM_TEXT, 'viewlist', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Describes the return structure of the service
     *
     * @return external_value jsonobj
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_RAW, 'Data in JSON-format');
    }
}
