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
 * Web service to write the user preferences.
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace block_disealytics\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

use coding_exception;
use dml_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;

/**
 * Class write_user_preference
 */
class write_user_preference extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            ['info' => new external_single_structure([
                        'action' => new external_value(PARAM_ALPHAEXT, 'The type of update to perform', VALUE_REQUIRED),
                        'name' => new external_value(PARAM_ALPHAEXT, 'The setting to update', VALUE_REQUIRED),
                        'value' => new external_value(PARAM_RAW, 'The value to write', VALUE_OPTIONAL)]),
            ]
        );
    }

    /**
     * Execute the service.
     *
     * @param $info
     * @return string $result
     * @throws invalid_parameter_exception
     * @throws coding_exception|dml_exception
     */
    public static function execute($info): string {
        self::validate_parameters(self::execute_parameters(), [
                'info' => $info,
        ]);

        switch ($info['action']) {
            case "write":
                switch ($info['name']) {
                    case 'expanded_view':
                        if ((get_user_preferences('block_disealytics_' . $info["name"])) == $info["value"]) {
                            set_user_preference('block_disealytics_' . $info["name"], 'none');
                        } else {
                            set_user_preference('block_disealytics_' . $info["name"], $info["value"]);
                        }
                        break;
                    case 'viewmode':
                        if (get_user_preferences('block_disealytics_' . $info["name"], 'viewmode_module') !== $info["value"]) {
                            set_user_preference('block_disealytics_' . $info["name"], $info["value"]);
                        }
                        break;
                    case 'views':
                        if (get_user_preferences('block_disealytics_' . $info["name"]) !== $info["value"]) {
                            set_user_preference('block_disealytics_' . $info["name"], $info["value"]);
                        }
                        break;
                }
                break;

            case "select_category":
                if ((get_user_preferences('block_disealytics_' . $info["name"]) !== $info["value"])) {
                    set_user_preference('block_disealytics_' . $info["name"], $info["value"]);
                }
                break;
            case "toggle":
                if (get_user_preferences('block_disealytics_' . $info["name"], '0')) {
                    set_user_preference('block_disealytics_' . $info["name"], '0');
                } else {
                    set_user_preference('block_disealytics_' . $info["name"], '1');
                }
                break;
            case "revoke_consent":
                global $DB, $USER;
                $rec = $DB->get_record('block_disealytics_consent', ['userid' => $USER->id]);
                if ($rec) {
                    $rec->choice = 0;
                    $rec->timemodified = time();
                    $DB->update_record('block_disealytics_consent', $rec);
                    if ($info["value"] == "delete") {
                        $DB->delete_records('block_disealytics_user_goals', ["userid" => $USER->id]);
                        $DB->delete_records('block_disealytics_user_pages', ["userid" => $USER->id]);
                        $DB->delete_records('block_disealytics_user_dates', ["userid" => $USER->id]);
                    }
                }
                break;
            default:
                throw new invalid_parameter_exception('Invalid updatetype parameter.');
        }
        $response = [
                'updatetype' => $info['action'],
                'setting' => get_user_preferences("block_disealytics_{$info["name"]}"),
        ];
        return json_encode($response);
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
