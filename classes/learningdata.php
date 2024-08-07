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

namespace block_disealytics;

use block_disealytics\data\course;
use DateTime;
use dml_exception;
use Exception;
use stdClass;

/**
 * Class learningdata
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learningdata {
    /**
     * Goal table that holds all learning goals.
     */
    private const GOALTABLE = 'block_disealytics_user_goals';
    /**
     * Table that holds all optional inputs.
     */
    private const OPTIONALINPUTSTABLE = 'block_disealytics_user_pages';
    /**
     * helper number
     * @var int
     */
    private static int $number = 0;
    /**
     * storage to hold data.
     * @var array
     */
    private array $storage;

    /**
     * learningdata constructor.
     */
    public function __construct() {
        $this->storage = [];
        $this->storage['assignments'] = [];
        $this->storage['submissions'] = [];
        $this->storage['grades'] = [];
        $this->storage['logs'] = [];
        $this->storage['goals'] = [];
        $this->storage['finished_goals'] = [];
        $this->storage['optional_inputs'] = [];
    }

    /**
     * Add a goal to the database.
     * @param stdClass $newgoal
     * @return bool|int
     * @throws dml_exception
     */
    public static function add_goal_to_database(stdClass $newgoal) {
        global $DB;
        $now = (new DateTime())->format('U');
        $newgoal->timecreated = $now;
        $newgoal->timemodified = $now;
        return $DB->insert_record(self::GOALTABLE, $newgoal);
    }

    /**
     * Update a goal.
     * @param stdClass $updatedgoal
     * @return void
     * @throws dml_exception
     */
    public static function update_goal(stdClass $updatedgoal): void {
        global $DB;
        $now = (new DateTime())->format('U');
        if ($DB->record_exists(self::GOALTABLE, ['id' => $updatedgoal->id])) {
            $updatedgoal->timemodified = $now;
            $DB->update_record(self::GOALTABLE, $updatedgoal);
        }
    }

    /**
     * Delete a goal.
     * @param int $id
     * @return void
     * @throws dml_exception
     */
    public static function delete_goal(int $id): void {
        global $DB;
        if ($DB->record_exists(self::GOALTABLE, ['id' => $id])) {
            $DB->delete_records(self::GOALTABLE, ['id' => $id]);
        }
    }

    /**
     * Write optional input.
     * @param stdClass $data
     * @return bool
     * @throws dml_exception
     */
    public static function write_optional_input(stdClass $data): bool {
        global $DB, $USER;
        $now = (new DateTime())->format('U');
        $data->userid = $USER->id;
        $data->timecreated = $now;
        $data->timemodified = $now;
        return $DB->insert_record(self::OPTIONALINPUTSTABLE, $data);
    }

    /**
     * Update optional input.
     * @param stdClass $data
     * @return void
     * @throws dml_exception
     */
    public static function update_optional_input(stdClass $data): void {
        global $DB;
        $now = (new DateTime())->format('U');
        if ($DB->record_exists(self::OPTIONALINPUTSTABLE, ['id' => $data->id])) {
            $data->timemodified = $now;
            $DB->update_record(self::OPTIONALINPUTSTABLE, $data);
        }
    }

    /**
     * Delete optional input
     * @param int $id
     * @return void
     * @throws dml_exception
     */
    public static function delete_optional_input(int $id): void {
        global $DB;
        if ($DB->record_exists(self::OPTIONALINPUTSTABLE, ['id' => $id])) {
            $DB->delete_records(self::OPTIONALINPUTSTABLE, ['id' => $id]);
        }
    }

    /**
     * Get submissions
     * @return array
     * @throws dml_exception
     */
    public function get_submissions(): array {
        if (!$this->storage['submissions']) {
            foreach ($this->get_assignments() as $assignment) {
                global $DB, $USER;
                $this->storage['submissions'][$assignment->id] =
                        $DB->get_record(
                            'assign_submission',
                            ['userid' => $USER->id, 'assignment' => $assignment->id],
                            'id, status, timemodified'
                        );
            }
        }
        return $this->storage['submissions'];
    }

    /**
     * Get assignments
     * @return array
     * @throws dml_exception
     */
    public function get_assignments(): array {
        if (!$this->storage['assignments']) {
            global $DB, $COURSE;
            $this->storage['assignments'] =
                    $DB->get_records('assign', ['course' => $COURSE->id], '', 'id, name, duedate, grade, nosubmissions');
        }
        return $this->storage['assignments'];
    }

    /**
     * Get grades
     * @return array
     * @throws dml_exception
     */
    public function get_grades(): array {
        if (!$this->storage['grades']) {
            foreach ($this->get_assignments() as $assignment) {
                global $DB, $USER;
                $this->storage['grades'][$assignment->id] =
                        $DB->get_record('assign_grades', ['userid' => $USER->id, 'assignment' => $assignment->id], 'id, grade');
            }
        }
        return $this->storage['grades'];
    }

    /**
     * Get logs
     * @param mixed $filter
     * @return mixed
     * @throws dml_exception
     */
    public function get_logs($filter = null): mixed {
        if (!$this->storage['logs']) {
            global $DB, $USER;
            $this->storage['logs'] = $DB->get_records(
                'logstore_standard_log',
                ['userid' => $USER->id],
                '',
                'id, courseid, timecreated, action, target'
            );
        }
        return $filter ? array_filter($this->storage['logs'], $filter) : $this->storage['logs'];
    }

    /**
     * Get goals
     * @return array
     * @throws dml_exception
     * @throws Exception
     */
    public function get_goals(): array {
        if (!$this->storage['goals']) {
            global $DB, $USER, $COURSE;
            $goals = $DB->get_records(
                self::GOALTABLE,
                ['userid' => $USER->id, 'courseid' => $COURSE->id]
            );
            $resultgoals = [];
            foreach ($goals as $goal) {
                $resultgoals[] = $this->add_goal_to_storage($goal);
            }
            $this->storage['goals'] = $resultgoals;
        }
        return $this->storage['goals'];
    }

    /**
     * Add goal to storage
     *
     * @param mixed $dbgoal
     * @return stdClass
     * @throws dml_exception
     */
    private function add_goal_to_storage(mixed $dbgoal): stdClass {
        global $COURSE;
        $target = self::generate_unique_number();
        // Implement attributes to use in the mustache template.
        $storagegoal = new stdClass();
        $storagegoal->target = $target;
        $storagegoal->goalid = $dbgoal->id;
        $storagegoal->name = $dbgoal->description;
        $storagegoal->coursename = $COURSE->id != $dbgoal->courseid ? $this->get_coursename_from_id($dbgoal->courseid) : "";
        $storagegoal->finished = $dbgoal->finished === '1' ? 'true' : 'false';
        $storagegoal->timestamp = $dbgoal->duedate;
        $timestamp = $dbgoal->duedate;
        $storagegoal->formattedduedate = $dbgoal->duedate === '0' ? '' : date('Y-m-d', ($timestamp));
        return $storagegoal;
    }

    /**
     * Generate unique number
     * @return string
     *
     */
    public static function generate_unique_number(): string {
        return self::$number++;
    }

    /**
     * Get coursename from id
     * @param int $courseid
     * @return mixed
     * @throws dml_exception
     */
    private function get_coursename_from_id(int $courseid): mixed {
        global $DB;
        return $DB->get_field('course', 'fullname', ['id' => $courseid]);
    }

    /**
     * Get goals for semester
     * @return array
     * @throws dml_exception
     * @throws Exception
     */
    public function get_goals_semester(): array {
        global $DB, $USER;
        $semestercourseids = course::get_all_courses_of_user_current_semester($USER->id);
        $courseids = array_column($semestercourseids, 'courseid');
        $resultgoals = [];
        foreach ($courseids as $courseid) {
            $goals = $DB->get_records(self::GOALTABLE, ['userid' => $USER->id, 'courseid' => $courseid]);

            foreach ($goals as $goal) {
                $resultgoals[] = $this->add_goal_to_storage($goal);
            }
        }
        $this->storage['goals'] = $resultgoals;
        return $this->storage['goals'];
    }

    /**
     * Get goals globally
     *
     * @param null $semestercourseids
     * @return array
     * @throws dml_exception
     */
    public function get_goals_global($semestercourseids = null): array {
        global $DB, $USER;
        $resultgoals = [];

        foreach ($semestercourseids as $courseid) {
            $goals = $DB->get_records(self::GOALTABLE, ['userid' => $USER->id, 'courseid' => $courseid]);
            foreach ($goals as $goal) {
                $resultgoals[] = $this->add_goal_to_storage($goal);
            }
        }

        $this->storage['goals'] = $resultgoals;

        return $this->storage['goals'];
    }

    /**
     * Get finished goals
     *
     * @param int|null $courseid
     * @return mixed
     * @throws dml_exception
     */
    public function get_finished_goals(?int $courseid = null): mixed {
        if (!$this->storage['finished_goals']) {
            global $DB, $USER, $COURSE;
            $goals = $DB->get_records(
                self::GOALTABLE,
                ['userid' => $USER->id, 'courseid' => $courseid ?? $COURSE->id]
            );
            $finishedgoals = [];
            foreach ($goals as $goal) {
                if ($goal->finished === '1') {
                    $finishedgoals[] = $this->add_goal_to_storage($goal);
                }
            }
            $this->storage['finished_goals'] = $finishedgoals;
        }
        return $this->storage['finished_goals'];
    }

    /**
     * Get finished goals for the semester
     * @return array
     * @throws dml_exception
     * @throws Exception
     */
    public function get_finished_goals_semester(): array {
        global $DB, $USER;
        $semestercourseids = course::get_all_courses_of_user_current_semester($USER->id);
        $courseids = array_column($semestercourseids, 'courseid');
        $finishedgoals = [];
        foreach ($courseids as $courseid) {
            $goals = $DB->get_records(self::GOALTABLE, ['userid' => $USER->id, 'courseid' => $courseid]);
            foreach ($goals as $goal) {
                if ($goal->finished === '1') {
                    $finishedgoals[] = $this->add_goal_to_storage($goal);
                }
            }
        }
        $this->storage['finished_goals'] = $finishedgoals;
        return $this->storage['finished_goals'];
    }

    /**
     * Get finished goals globally
     *
     * @param null $semestercourseids
     * @return array
     * @throws dml_exception
     */
    public function get_finished_goals_global($semestercourseids = null): array {
        global $DB, $USER;
        $finishedgoals = [];
        foreach ($semestercourseids as $courseid) {
            $goals = $DB->get_records(self::GOALTABLE, ['userid' => $USER->id, 'courseid' => $courseid]);
            foreach ($goals as $goal) {
                if ($goal->finished === '1') {
                    $finishedgoals[] = $this->add_goal_to_storage($goal);
                }
            }
            $this->storage['finished_goals'] = $finishedgoals;
        }
        return $this->storage['finished_goals'];
    }

    /**
     * Get optional inputs
     *
     * @param null $filter
     * @param null $courseid
     * @return array
     * @throws dml_exception
     */
    public function get_optional_inputs($filter = null, $courseid = null): array {
        global $DB, $USER, $COURSE;
        if ($courseid != null) {
            $optionalinputs = $DB->get_records(
                self::OPTIONALINPUTSTABLE,
                ['userid' => $USER->id, 'courseid' => $courseid]
            );
        } else {
            $optionalinputs = $DB->get_records(
                self::OPTIONALINPUTSTABLE,
                ['userid' => $USER->id, 'courseid' => $COURSE->id]
            );
        }
        $resultoptionalinputs = [];
        foreach ($optionalinputs as $optionalinput) {
            $target = self::generate_unique_number();
            $newoptionalinput = new stdClass();
            $newoptionalinput->target = $target;
            $newoptionalinput->optionalinputid = $optionalinput->id;
            $newoptionalinput->documentname = $optionalinput->name;
            $newoptionalinput->currentpage = $optionalinput->currentpage;
            $newoptionalinput->lastpage = $optionalinput->lastpage;
            $newoptionalinput->expenditureoftime = $optionalinput->expenditureoftime;
            $resultoptionalinputs[] = $newoptionalinput;
        }
        $this->storage['optional_inputs'] = $resultoptionalinputs;

        return $filter ? array_filter($this->storage['optional_inputs'], $filter) : $this->storage['optional_inputs'];
    }

    /**
     * Get current halfyear dates
     *
     * @return array
     * @throws Exception
     */
    public static function get_current_halfyear_dates(): array {
        $now = new DateTime("now");
        $month = intval($now->format("m"));
        $year = intval($now->format("Y"));
        // Start of Year, current semester started last year.
        if ($month <= 2) {
            return ["start" => new DateTime(($year - 1) . "-08-01"), "end" => new DateTime($year . "-04-00")];
            // During the year, semester is this year.
        } else if ($month <= 8) {
            return ["start" => new DateTime($year . "-01-01"), "end" => new DateTime($year . "-10-00")];
            // End of year, semester starts this year, ends next year.
        } else {
            return ["start" => new DateTime($year . "-08-01"), "end" => new DateTime(($year + 1) . "-10-00")];
        }
    }
}
