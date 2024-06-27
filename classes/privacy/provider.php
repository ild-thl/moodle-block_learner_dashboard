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

namespace block_disealytics\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use dml_exception;

/**
 * Privacy provider for block_disealytics.
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class provider implements core_userlist_provider, \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider {
    /**
     * Get the list of metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'block_disealytics_user_dates',
            [
                        'id' => 'privacy:metadata:user_dates_id',
                        'name' => 'privacy:metadata:user_dates_name',
                        'usermodified' => 'privacy:metadata:user_dates_usermodified',
                        'courseid' => 'privacy:metadata:user_dates_courseid',
                        'userid' => 'privacy:metadata:user_dates_userid',
                        'timecreated' => 'privacy:metadata:user_dates_timecreated',
                        'timemodified' => 'privacy:metadata:user_dates_timemodified',
                        'timestart' => 'privacy:metadata:user_dates_timestart',
                        'timeduration' => 'privacy:metadata:user_dates_timeduration',
                        'location' => 'privacy:metadata:user_dates_location',
                        'eventtype' => 'privacy:metadata:user_dates_eventtype',
                        'repeatid' => 'privacy:metadata:user_dates_repeatid',
                ],
            'privacy:metadata:block_disealytics_user_dates'
        );
        $collection->add_database_table(
            'block_disealytics_user_goals',
            [
                        'id' => 'privacy:metadata:user_goal_id',
                        'usermodified' => 'privacy:metadata:user_goal_usermodified',
                        'courseid' => 'privacy:metadata:user_goal_courseid',
                        'userid' => 'privacy:metadata:user_goal_userid',
                        'timecreated' => 'privacy:metadata:user_goal_timecreated',
                        'timemodified' => 'privacy:metadata:user_goal_timemodified',
                        'timecompleted' => 'privacy:metadata:user_goal_timecompleted',
                        'duedate' => 'privacy:metadata:user_goal_duedate',
                        'description' => 'privacy:metadata:user_goal_description',
                        'finished' => 'privacy:metadata:user_goal_finished',
                ],
            'privacy:metadata:block_disealytics_user_goals'
        );
        $collection->add_database_table(
            'block_disealytics_user_pages',
            [
                        'id' => 'privacy:metadata:user_pages_id',
                        'usermodified' => 'privacy:metadata:user_pages_usermodified',
                        'courseid' => 'privacy:metadata:user_pages_courseid',
                        'userid' => 'privacy:metadata:user_pages_userid',
                        'timecreated' => 'privacy:metadata:user_pages_timecreated',
                        'timemodified' => 'privacy:metadata:user_pages_timemodified',
                        'timecompleted' => 'privacy:metadata:user_pages_timecompleted',
                        'name' => 'privacy:metadata:user_pages_name',
                        'currentpage' => 'privacy:metadata:user_pages_currentpage',
                        'lastpage' => 'privacy:metadata:user_pages_lastpage',
                        'expenditureoftime' => 'privacy:metadata:user_pages_expenditureoftime',
                ],
            'privacy:metadata:block_disealytics_user_pages'
        );
        $collection->add_database_table(
            'block_disealytics_consent',
            [
                        'id' => 'privacy:metadata:consent_id',
                        'userid' => 'privacy:metadata:consent_userid',
                        'counter' => 'privacy:metadata:consent_counter',
                        'choice' => 'privacy:metadata:consent_choice',
                        'timecreated' => 'privacy:metadata:consent_timecreated',
                        'timemodified' => 'privacy:metadata:consent_timemodified',
                ],
            'privacy:metadata:block_disealytics_consent'
        );
        $collection->add_database_table(
            'block_disealytics_user_tasks',
            [
                        'id' => 'privacy:metadata:user_id',
                        'component' => 'privacy:metadata:user_component',
                        'target' => 'privacy:metadata:user_target',
                        'action' => 'privacy:metadata:user_action',
                        'eventname' => 'privacy:metadata:user_eventname',
                        'courseid' => 'privacy:metadata:user_courseid',
                        'userid' => 'privacy:metadata:user_userid',
                        'timestart' => 'privacy:metadata:user_timestart',
                        'n_events' => 'privacy:metadata:user_n_events',
                        'duration' => 'privacy:metadata:user_duration',
                        'timecreated' => 'privacy:metadata:user_timecreated',
                ],
            'privacy:metadata:block_disealytics_user_tasks'
        );

        return $collection;
    }

    // Testing: tool_generator_000090 - lena becker id 92.

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT c.id
        FROM {block_disealytics_user_goals} g
        JOIN {context} c ON c.instanceid = g.userid
        WHERE g.userid = :userid AND c.contextlevel = :contextuser
        GROUP BY c.id";

        $params = ['contextuser' => CONTEXT_USER, 'userid' => $userid];

        $contextlist->add_from_sql($sql, $params);
        echo "<br>contextlist: <br>";
        foreach ($contextlist->get_contexts() as $context) {
            echo "Context ID: " . $context->id . ", Context Level: " . $context->contextlevel . "<br>";
        }
        echo "<br>contextlist END <br>";
        return $contextlist;
    }

    /**
     * Get the list of users who have data in the specified context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        $sql = "SELECT g.userid, p.userid
        FROM {block_disealytics_user_goals} g
        JOIN {block_disealytics_user_pages} p ON g.userid = p.userid
        JOIN {block_disealytics_user_dates} d ON g.userid = d.userid
        JOIN {context} ctx ON ctx.instanceid = g.userid AND ctx.contextlevel = :contextuser";

        $params = ['contextid' => $context->id, 'contextuser' => CONTEXT_USER];
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Delete all data for the specified user.
     *
     * @param approved_userlist $userlist
     * @return void
     * @throws dml_exception
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context instanceof context_user) {
            $DB->delete_records('block_disealytics_user_dates', ['userid' => $context->instanceid]);
            $DB->delete_records('block_disealytics_user_goals', ['userid' => $context->instanceid]);
            $DB->delete_records('block_disealytics_user_pages', ['userid' => $context->instanceid]);
            $DB->delete_records('block_disealytics_consent', ['userid' => $context->instanceid]);
            $DB->delete_records('block_disealytics_user_tasks', ['userid' => $context->instanceid]);
        }
    }

    /**
     * Delete all data for the specified user in the specified context.
     *
     * @param context $context
     * @return void
     * @throws dml_exception
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context->contextlevel == CONTEXT_USER) {
            $DB->delete_records('block_disealytics_user_dates', ['userid' => $context->instanceid]);
            $DB->delete_records('block_disealytics_user_goals', ['userid' => $context->instanceid]);
            $DB->delete_records('block_disealytics_user_pages', ['userid' => $context->instanceid]);
            $DB->delete_records('block_disealytics_consent', ['userid' => $context->instanceid]);
            $DB->delete_records('block_disealytics_user_tasks', ['userid' => $context->instanceid]);
        }
    }

    /**
     * Export all user data for the specified user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     * @throws dml_exception
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        $user = $contextlist->get_user();
        global $DB;
        $contexts = $contextlist->get_contexts();
        foreach ($contexts as $context) {
            $sqldate = "SELECT * FROM {block_disealytics_user_dates} WHERE userid = :userid";
            $paramsdate = ['userid' => $user->id];
            $datedata = $DB->get_records_sql($sqldate, $paramsdate);

            $sqlgoal = "SELECT * FROM {block_disealytics_user_goals} WHERE userid = :userid";
            $paramsgoal = ['userid' => $user->id];
            $goaldata = $DB->get_records_sql($sqlgoal, $paramsgoal);

            $sqlpages = "SELECT * FROM {block_disealytics_user_pages} WHERE userid = :userid";
            $paramspages = ['userid' => $user->id];
            $pagesdata = $DB->get_records_sql($$sqlpages, $paramspages);

            $sqlconsent = "SELECT * FROM {block_disealytics_consent} WHERE userid = :userid";
            $paramsconsent = ['userid' => $user->id];
            $consentdata = $DB->get_records_sql($sqlconsent, $paramsconsent);

            $sqltasks = "SELECT * FROM {block_disealytics_user_tasks} WHERE userid = :userid";
            $paramstasks = ['userid' => $user->id];
            $tasksdata = $DB->get_records_sql($sqltasks, $paramstasks);

            $exporteddata = (object) [
                    'dateData' => $datedata,
                    'goalData' => $goaldata,
                    'pagesData' => $pagesdata,
                    'consentData' => $consentdata,
                    'tasksData' => $tasksdata,
            ];
            writer::with_context($context)->export_data(['dates and goal and pages and consent and tasks'], $exporteddata);
        }
    }

    // Testing: tool_generator_000072.

    /**
     * Delete all user data for the specified user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     * @throws dml_exception
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        echo "<br> DISEA_disealytics : delete_data_for_user called <br>";
        $contexts = $contextlist->get_contexts();

        if (!empty($contexts)) {
            $context = reset($contexts);
            echo "Test first if context is user context <br>";
            if ($context->contextlevel == CONTEXT_USER) {
                echo "Deleting data for user in context: Context ID - $context->id,
                Context Level - $context->contextlevel,
                User ID - $context->instanceid<br>";

                $DB->delete_records('block_disealytics_user_dates', ['userid' => $context->instanceid]);
                $DB->delete_records('block_disealytics_user_goals', ['userid' => $context->instanceid]);
                $DB->delete_records('block_disealytics_user_pages', ['userid' => $context->instanceid]);
                $DB->delete_records('block_disealytics_consent', ['userid' => $context->instanceid]);
                $DB->delete_records('block_disealytics_user_tasks', ['userid' => $context->instanceid]);

                echo "Data for user deleted successfully.";
            }
        }
    }
}
