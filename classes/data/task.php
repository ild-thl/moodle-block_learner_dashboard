<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_disealytics\data;

/**
 * Class task
 * @package block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task {
    /**
     * Get all tasks of a user in a course in a given time frame.
     *
     * @param int $from
     * @param int $to
     * @param int $courseid
     * @return array
     */
    public static function block_disealytics_get_user_tasks($from, $to, $courseid): array {
        global $USER, $DB;

        return $DB->get_records_select(
            "block_disealytics_user_tasks",
            "courseid = :courseid AND userid = :userid AND timestart >= :from AND timestart <= :to",
            ['userid' => $USER->id, 'courseid' => $courseid, 'from' => $from, 'to' => $to]
        );
    }

    /**
     * Group the tasks by component and reduce the number of groups to three.
     *
     * @param $tasks
     * @return array
     */
    public static function block_disealytics_group_and_reduce($tasks): array {
        $groupedtasks = [];
        foreach ($tasks as $task) {
            $groupedtasks[strtolower($task->component)][] = $task;
        }
        uasort($groupedtasks, function ($a, $b) {
            // Sort the task groups based on the number ob tasks in the group.
            // Sort based on the sum of the duration of the tasks in a group, e.g.
            return array_sum(array_column($b, 'duration')) <=> array_sum(array_column($a, 'duration'));
        });
        // Get only the first three groups, or less if there are less.
        return array_slice($groupedtasks, 0, min(3, count($groupedtasks)), true);
    }
    /**
     * Make buckets of tasks based on the type.
     *
     * @param array $tasks
     * @param string $type
     * @param array $weeknrs
     * @return array
     */
    public static function block_disealytics_make_task_buckets($tasks, $type, $weeknrs = []) {
        if ($type == "weekdays") {
            $durations = [];
            foreach ($tasks as $component => $grouped) {
                $byday = [];
                for ($i = 1; $i < 8; $i++) {
                    $byday[$i] = 0;
                }
                foreach ($grouped as $task) {
                    $daynr = date("N", $task->timestart);
                    $byday[$daynr] += $task->duration;
                }
                foreach ($byday as $day => $duration) {
                    $byday[$day] = round($duration / 60.0);
                }
                $durations[$component] = $byday;
            }
            return $durations;
        }
        if ($type == "weeks") {
            $durations = [];
            foreach ($tasks as $component => $grouped) {
                $byweeks = [];
                foreach ($weeknrs as $nr) {
                    $byweeks[$nr] = 0;
                }
                foreach ($grouped as $task) {
                    $weeknr = date_create_from_format("U", $task->timestart)->format("W");
                    $byweeks[str_pad($weeknr, 2, "0", STR_PAD_LEFT)] += $task->duration;
                }
                foreach ($byweeks as $weeknr => $duration) {
                    $byweeks[$weeknr] = round($duration / 60.0);
                }
                $durations[$component] = $byweeks;
            }
            return $durations;
        }
        return null;
    }
}
