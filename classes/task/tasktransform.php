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

namespace block_disealytics\task;

use ArrayIterator;
use coding_exception;
use core\task\scheduled_task;
use dml_exception;
use lang_string;
use stdClass;

/**
 * Version details.
 *
 * {tasktransform} class definition
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tasktransform extends scheduled_task {
    /**
     * Get the name of the task.
     * @throws coding_exception
     */
    public function get_name() {
        return get_string('task_tasktransform', 'block_disealytics');
    }

    /**
     * Execute the task.
     *
     * @return void
     * @throws dml_exception|coding_exception
     */
    public function execute(): void {
        global $DB;

        // Time as unix timestamp. Returns 0 on first run.
        $time = $this->get_last_run_time();

        $selectstring =
                'l.id, l.component, l.action, l.target, ' . 'l.userid, c.id as courseid, l.relateduserid, l.timecreated ';

        // SQL Query to get logdata in interval of one week.
        $query1 = 'SELECT ' . $selectstring . ' FROM {logstore_standard_log} l ' . 'LEFT JOIN {course} c ' .
                'ON l.courseid = c.id ' .
                'WHERE (l.relateduserid IN (SELECT DISTINCT userid FROM {block_disealytics_consent} disea2 ' .
                'WHERE disea2.choice = 1 AND disea2.timecreated < ' . $time . ')' .
                'OR l.userid IN (SELECT DISTINCT userid FROM {block_disealytics_consent} disea2 ' .
                'WHERE disea2.choice = 1 AND disea2.timecreated < ' . $time . '))' . 'AND l.timecreated >' .
                $time .

                ' UNION SELECT DISTINCT ' . $selectstring . ' FROM {logstore_standard_log} l ' . 'LEFT JOIN {course} c ' .
                'ON l.courseid = c.id ' .
                'WHERE l.relateduserid IN (SELECT DISTINCT userid FROM {block_disealytics_consent} disea2 ' .
                'WHERE disea2.choice = 1 AND disea2.timemodified >' . $time . ') ' .
                'OR l.userid IN (SELECT DISTINCT userid FROM {block_disealytics_consent} disea2 ' .
                'WHERE disea2.choice = 1 AND disea2.timemodified >' . $time . ') ';

        // Get Logdata from database.
        $data = array_values($DB->get_records_sql($query1));

        $uidswconsent = array_column($DB->get_records('block_disealytics_consent', ['choice' => 1], '', 'id, userid'), 'userid');
        $data = array_filter($data, function ($v) use ($uidswconsent) {
            return $v->courseid != -1 && (in_array($v->userid, $uidswconsent) || in_array($v->relateduserid, $uidswconsent));
        });

        $data = self::filterdata($data);

        $componentfilename = get_config('block_disealytics', 'components');
        mtrace("Componentfile is: " . $componentfilename);

        if ($componentfilename) {
            $data = self::redefinecomponents($data, $componentfilename);
            if ($data) {
                self::transformtasks($data);
                mtrace("Tasktransformation successful!");
            } else {
                mtrace("Tasktransformation aborted due to missing component redefinition file!");
            }
        } else {
            mtrace("Tasktransformation aborted due to missing component redefinition file!");
        }
    }

    /**
     * Get the task's custom data.
     *
     * @param stdClass $item
     * @return stdClass
     */
    private static function newtask(stdClass $item): stdClass {
        $task = new stdClass();
        $task->component = $item->component;
        $task->target = $item->target;
        $task->action = $item->action;
        $task->eventname = "$item->component/$item->target/$item->action";
        $task->courseid = $item->courseid;
        $task->userid = $item->userid;
        $task->timestart = intval($item->timecreated);
        $task->n_events = 1;
        $task->duration = $item->duration;
        $task->timecreated = time();
        return $task;
    }

    /**
     * Transform the data into tasks.
     *
     * @param array $data
     * @return void
     * @throws dml_exception|coding_exception
     */
    private static function transformtasks(array $data): void {
        $databyuserid = [];
        // Group data by userid.
        foreach ($data as $log) {
            $databyuserid[$log->userid][] = $log;
        }
        $tasks = [];
        foreach ($databyuserid as $bucket) {
            if (count($bucket) != 0) {
                $iterator = new ArrayIterator($bucket);
                $task = self::newtask($iterator->current());
                $tasks[] = $task;
                $iterator->next();
                while ($iterator->valid()) {
                    $item = $iterator->current();
                    if ("$item->component/$item->target/$item->action" == $task->eventname && $item->courseid == $task->courseid) {
                        // Task ist the same, update its values!
                        $task->n_events++;
                        $task->duration += $item->duration;
                    } else {
                        // New task is created.
                        $task = self::newtask($item);
                        $tasks[] = $task;
                    }
                    $iterator->next();
                }
            }
        }
        global $DB;
        $DB->insert_records('block_disealytics_user_tasks', $tasks);
    }

    /**
     * Filter the data.
     *
     * @param array $data
     * @return array
     * @throws dml_exception
     */
    private static function filterdata(array $data): array {

        $blacklistfilename = get_config('block_disealytics', 'filterblacklist');
        if (!$blacklistfilename) {
            mtrace("Blacklistfile not found, not filtering!");
            return $data;
        }
        mtrace("Filterfile is: " . $blacklistfilename);

        $filecontent = self::readcsvfile($blacklistfilename, '10');
        if ($filecontent == null) {
            mtrace("Blacklistfile not found, not filtering!");
            return $data;
        }
        mtrace("Filtering data!");
        return array_filter($data, function ($logrow) use ($filecontent) {
            foreach ($filecontent as $blacklistline) {
                if (self::findrowmatch($logrow, $blacklistline)) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Read a csv file.
     *
     * @param string $fullpath
     * @param int $itemid
     * @return array|null
     */
    private static function readcsvfile(string $fullpath, int $itemid): ?array {
        if ($fullpath === "") {
            return null;
        }
        $pathinfo = pathinfo($fullpath);
        $filecontent = null;
        $file = get_file_storage()->get_file(
            '1',
            'block_disealytics',
            'disea',
            $itemid,
            $pathinfo['dirname'],
            $pathinfo['basename']
        );
        $lines = explode(PHP_EOL, $file->get_content());
        $names = str_getcsv(array_shift($lines), ';');
        $namec = count($names);
        foreach ($lines as $line) {
            if ($line != '') {
                $linedata = [];
                for ($n = 0; $n < $namec; $n++) {
                    $linedata[$names[$n]] = str_getcsv($line, ';')[$n];
                }
                $filecontent[] = $linedata;
            }
        }
        return $filecontent;
    }

    /**
     * Find a row match.
     *
     * @param stdClass $logrow
     * @param array $blacklistline
     * @return bool
     */
    private static function findrowmatch(stdClass $logrow, array $blacklistline): bool {
        foreach ($blacklistline as $property => $value) {
            if (strtolower($logrow->$property) != strtolower($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Redefine components.
     *
     * @param array $data
     * @param string $componentfilename
     * @return array|bool
     */
    private static function redefinecomponents(array $data, string $componentfilename) {

        $filecontent = self::readcsvfile($componentfilename, '20');

        if ($filecontent == null) {
            mtrace("Component Redefinition File not found, not redefining!");
            return false;
        }
        mtrace('Redefining Components and setting durations');
        $databyuserid = [];
        // Group data by userid.
        foreach ($data as $log) {
            $databyuserid[$log->userid][] = $log;
        }
        foreach ($databyuserid as $bucket) {
            $iterator = new ArrayIterator($bucket);
            while ($iterator->valid()) {
                $prev = $iterator->current();
                $iterator->next();
                if ($iterator->valid()) {
                    // Duration of an event is the number of seconds to the next event.
                    $prev->duration = $iterator->current()->timecreated - $prev->timecreated;
                } else {
                    // The last event hat duration 0.
                    $prev->duration = 0;
                }
            }
            foreach ($bucket as $logrow) {
                foreach ($filecontent as $compreplacement) {
                    if (
                        self::findrowmatch(
                            $logrow,
                            ['component' => $compreplacement['component'], 'target' => $compreplacement['target'],
                            'action' => $compreplacement['action']]
                        )
                    ) {
                        // Set durations!
                        if (!in_array($compreplacement["cat_map"], ["Starting", "Ending"])) {
                            // Special cases are in here, they always have duration 0.
                            $logrow->duration = 0;
                        } else {
                            if (!in_array("", [$compreplacement["medmad_tau"], $compreplacement["median"]])) {
                                // Normal cases go here.
                                $tau = self::secondsfrom($compreplacement["medmad_tau"]);
                                $median = self::secondsfrom($compreplacement["median"]);
                                if ($logrow->duration > $tau || $logrow->duration == 0) {
                                    $logrow->duration = $median;
                                }
                            }
                        }

                        // Redefine Components!
                        if ($compreplacement['spec'] != '') {
                            if ($compreplacement['spec'] == 'course_or_site_home') {
                                if ($logrow->courseid && in_array($logrow->courseid, ['-1', '0', '1'], true)) {
                                    $logrow->component = 'Site home';
                                } else {
                                    $logrow->component = 'Course home';
                                }
                            } else if ($compreplacement['spec'] == 'user_or_participant_profile') {
                                if ($logrow->userid == $logrow->relateduserid) {
                                    $logrow->component = 'User profile';
                                } else {
                                    $logrow->component = 'Participant profile';
                                }
                            }
                        } else {
                            $logrow->component = $compreplacement['comp_map'];
                        }
                        break;
                    }
                }
            }
        }
        // Merge userid-buckets back into a single log list.
        $logs = [];
        foreach ($databyuserid as $bucket) {
            $logs = array_merge($logs, $bucket);
        }
        uasort($logs, function ($a, $b) {
            return strnatcmp($a->id, $b->id);
        });
        return $logs;
    }

    /**
     * Convert a string to seconds.
     *
     * @param string $str
     * @return float|int
     */
    private static function secondsfrom(string $str) {
        $seconds = 0;
        $str = explode(" ", $str);
        $seconds *= $str[0];
        $time = explode(":", $str[2]);
        $seconds += intval($time[0]) * 60 * 60 + intval($time[1]) * 60 + intval($time[2]);
        return $seconds;
    }
}
