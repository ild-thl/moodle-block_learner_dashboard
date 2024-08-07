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

use coding_exception;
use dml_exception;
use html_writer;
use moodle_exception;
use stdClass;

/**
 * Class assignment
 * @package block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignment {
    /**
     * The submission of the assignment is neutral.
     */
    public const SUBMISSION_STATUS_NEUTRAL = 10;
    /**
     * The submission of the assignment is incomplete.
     */
    public const SUBMISSION_STATUS_INCOMPLETE = 11;
    /**
     * The submission of the assignment has been submitted.
     */
    public const SUBMISSION_STATUS_SUBMITTED = 12;
    /**
     * The submission of the assignment has not been submitted.
     */
    public const SUBMISSION_STATUS_NOTSUBMITTED = 13;
    /**
     * The submission of the assignment is a self-check.
     */
    public const SUBMISSION_STATUS_SELFCHECK = 14;
    /**
     * The grade status of the assignment is neutral.
     */
    public const GRADE_STATUS_NEUTRAL = 20;
    /**
     * The grade status of the assignment is incomplete.
     */
    public const GRADE_STATUS_INCOMPLETE = 21;
    /**
     * The grade status of the assignment is okay.
     */
    public const GRADE_STATUS_OKAY = 22;
    /**
     * The grade status of the assignment is failed.
     */
    public const GRADE_STATUS_FAILED = 23;
    /**
     * The grade status of the assignment is info.
     */
    public const GRADE_STATUS_INFO = 24;
    /**
     * The status of the assignment is empty.
     */
    public const STATUS_EMPTY = 100;
    /**
     * The grade type of the assignment is text.
     */
    const GRADETYPE_TEXT = '3';
    /**
     * The grade type of the assignment is scale.
     */
    const GRADETYPE_SCALE = '2';
    /**
     * The grade type of the assignment is value.
     */
    const GRADETYPE_VALUE = '1';
    /**
     * The grade type of the assignment is none.
     */
    const GRADETYPE_NONE = '0';
    /**
     * The list to hold all assignments.
     * @var array[assignment]
     */
    private static array $assignmentlists = [];
    /**
     * The ID of the assignment.
     * @var int
     */
    private int $id;
    /**
     * The assignment object.
     * @var stdClass
     */
    private $assign;
    /**
     * The grade item of the assignment.
     * @var stdClass
     */
    private $gradeitem;
    /**
     * The grade grade of the assignment.
     * @var stdClass
     */
    private $gradegrade;
    /**
     * The hover text for the assignment.
     * @var string
     */
    private string $hovertext;
    /**
     * The submission status of the assignment.
     * @var int
     */
    private int $submissonstatus;
    /**
     * The grade status of the assignment.
     * @var int
     */
    private int $gradestatus;
    /**
     * The module information of the assignment.
     * @var stdClass
     */
    private $modinfo;
    /**
     * The attempts of the assignment.
     * @var array
     */
    private array $attempts;

    /**
     * Constructor for the assignment class.
     *
     * @param $moduleinfo The module information of the assignment
     * @param int|null $courseid The ID of the course
     * @throws dml_exception
     */
    public function __construct($moduleinfo, ?int $courseid = null) {
        global $DB, $USER, $COURSE;
        $this->modinfo = $moduleinfo;
        $this->id = $moduleinfo->instance;

        $this->assign = $DB->get_record('assign', ['id' => $this->id]);
        $this->gradeitem = $DB->get_record(
            'grade_items',
            ['courseid' => $courseid ?? $COURSE->id, 'itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $this->id]
        );
        $this->gradegrade = $DB->get_record(
            'grade_grades',
            ['itemid' => $this->gradeitem->id,
            'userid' => $USER->id]
        );
        $this->attempts = [];
        foreach ($DB->get_records('assign_submission', ['assignment' => $this->id, 'userid' => $USER->id]) as $submission) {
            $this->attempts[$submission->attemptnumber]['submission'] = $submission;
        }
        foreach ($DB->get_records('assign_grades', ['assignment' => $this->id, 'userid' => $USER->id]) as $grade) {
            $this->attempts[$grade->attemptnumber]['grade'] = $grade;
        }
        $this->gradestatus = self::STATUS_EMPTY;
    }

    /**
     * Get the visible assignments for a specific course.
     *
     * @param int $courseid The ID of the course.
     * @return array[assignment] Returns an array of visible assignments for the specified course.
     * @throws moodle_exception
     */
    public static function block_disealytics_get_course_assignments(int $courseid): array {
        if (array_key_exists($courseid, self::$assignmentlists)) {
            return self::$assignmentlists[$courseid];
        }
        $assignments = [];
        foreach (get_fast_modinfo($courseid)->get_cms() as $cm) {
            if ($cm->modname == 'assign' && $cm->visible && !$cm->deletioninprogress) {
                $assignments[] = new assignment($cm, $courseid);
            }
        }
        return self::$assignmentlists[$courseid] = $assignments;
    }

    /**
     * Check if the assignment needs submission.
     * @return bool Returns true if the assignment needs submission, false otherwise.
     */
    public function block_disealytics_needs_submission(): bool {
        return $this->assign->nosubmissions == 0;
    }

    /**
     * Check if the assignment is a team submission.
     * @return bool Returns true if it is a team submission, false otherwise.
     */
    private function block_disealytics_is_teamsubmission(): bool {
        return $this->assign->teamsubmission;
    }

    /**
     * Check if the assignment has a submission.
     * @return bool Returns true if there is a submission, false otherwise.
     */
    private function block_disealytics_has_submission(): bool {
        if (count($this->attempts) > 0) {
            foreach ($this->attempts as $attempt) {
                if ($attempt['submission']->status == 'submitted' && $attempt['submission']->latest == 1) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if the assignment has a self-check.
     * @throws dml_exception
     */
    public function block_disealytics_has_selfcheck(): bool {
        global $DB, $USER;

        $completion = $DB->get_record('course_modules_completion', ['coursemoduleid' => $this->modinfo->id, 'userid' => $USER->id]);
        if ($completion) {
            return $completion->completionstate == 1;
        } else {
            return false;
        }
    }

    /**
     * Get the set due date of the assignment.
     * @return Returns the due date of the assignment.
     */
    private function block_disealytics_get_duedate() {
        return $this->assign->duedate;
    }

    /**
     * Check if the assignment has a submission that was submitted on time.
     * @return bool Returns true if there is a submission that was submitted on time, false otherwise.
     */
    private function block_disealytics_has_submission_ontime(): bool {
        if ($this->block_disealytics_has_submission()) {
            foreach ($this->attempts as $attempt) {
                if ($attempt['submission']->latest == 1) {
                    if ($attempt['submission']->timemodified <= $this->assign->duedate) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Check if the assignment item gets graded.
     * @return bool Returns true if the assignment gets graded, false otherwise.
     */
    public function block_disealytics_gets_graded(): bool {
        return in_array($this->gradeitem->gradetype, [self::GRADETYPE_SCALE, self::GRADETYPE_VALUE], true);
    }

    /**
     * Check if the assignment has been graded.
     * @return bool Returns true if the assignment has been graded, false otherwise.
     */
    public function block_disealytics_has_grade(): bool {
        return $this->block_disealytics_get_grade() !== null;
    }

    /**
     * Get the grade type of assignment.
     * @return Returns the grade type of assignment.
     */
    public function block_disealytics_get_gradetype() {
        return $this->gradeitem->gradetype;
    }

    /**
     * Get the scale grade text for the assignment.
     * @return string|bool Returns the scale grade text if the grade type is scale, or false otherwise.
     * @throws dml_exception
     */
    private function block_disealytics_get_scale_grade_text() {
        if ($this->gradeitem->gradetype !== self::GRADETYPE_SCALE) {
            return false;
        }
        global $DB;
        $scaletexts = explode(',', $DB->get_record("scale", ['id' => $this->gradegrade->rawscaleid])->scale);
        return trim($scaletexts[$this->block_disealytics_get_grade() - 1]);
    }

    /**
     * Check if the assignment has remaining tries.
     * @return bool Returns true if the assignment has remaining tries, false otherwise.
     */
    private function block_disealytics_has_tries_left(): bool {

        $donesubmissions = array_filter($this->attempts, function ($e) {
            return $e["submission"]->status == "draft" || $e["submission"]->status == "submitted";
        });
        $count = count($donesubmissions);
        // Methode manuell && unbegrenzt viele Versuche.
        return ($this->assign->attemptreopenmethod == 'manual' && $this->assign->maxattempts == -1) ||
            // Manuell und noch Versuche offen.
            ($this->assign->attemptreopenmethod == 'manual' && $this->assign->maxattempts > $count) ||
                // Methode bis-bestehen && !bestanden && (unbegrenzt Versuche || mehr als 1 Versuch offen).
                ($this->assign->attemptreopenmethod == 'untilpass' &&
                        $this->block_disealytics_get_grade() < $this->block_disealytics_get_gradepass() &&
                        ($this->assign->maxattempts == -1 ||
                                $this->assign->maxattempts > $count));
    }

    /**
     * Get the maximum grade for the assignment.
     * @return float|int Returns the maximum grade for the assignment.
     */
    public function block_disealytics_get_maxgrade() {
        return $this->assign->grade;
    }

    /**
     * Get the grade for the assignment.
     * @return float|int|null Returns the grade for the assignment or null if it's not given.
     */
    public function block_disealytics_get_grade() {
        // Check if gradegrade is set and not null.
        if ($this->gradegrade && property_exists($this->gradegrade, 'finalgrade')) {
            return $this->gradegrade->finalgrade;
        } else {
            // Handle the case when gradegrade or finalgrade is not set.
            return null;
        }
    }

    /**
     * Get the grade that is needed to pass the assignment.
     * @return float|int Returns the grade for the assignment.
     */
    public function block_disealytics_get_gradepass() {
        return $this->gradeitem->gradepass;
    }

    /**
     * Build a link for the assignment to use in assignment_view.php.
     * @return string Returns the HTML link for assignment if it is available, or the modinfo's name itself if not available.
     */
    public function block_disealytics_build_name_link(): string {
        global $CFG;
        if ($this->modinfo->available) {
            return html_writer::link($CFG->wwwroot . "/mod/assign/view.php?id=" . $this->modinfo->id, $this->modinfo->name);
        } else {
            return $this->modinfo->name;
        }
    }

    /**
     * Get name for assignment.
     * @return string Returns the name of the assignment.
     */
    public function block_disealytics_get_assignment_name(): string {
        return $this->modinfo->name;
    }

    /**
     * Generate the HTML representation of the assignment status to use in assignment_view.php.
     *
     * @return string Returns the HTML code representing the assignment status.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function block_disealytics_gen_status_html(): string {
        $this->block_disealytics_gen_submission_status();
        switch ($this->gradestatus) {
            case self::GRADE_STATUS_FAILED:
                $this->hovertext = get_string('assignment_view_hover_failed', 'block_disealytics');
                return "<i title=\"$this->hovertext\" class=\"fa fa-times\" style=\"color: var(--diseared);\"></i>";
            case self::GRADE_STATUS_OKAY:
                $this->hovertext = get_string('assignment_view_hover_okay', 'block_disealytics');
                return "<i title=\"$this->hovertext\" class=\"fa fa-check\" style=\"color: var(--diseagreen);\"></i>";
            case self::GRADE_STATUS_INCOMPLETE:
                $this->hovertext = get_string('assignment_view_hover_incomplete', 'block_disealytics');
                return "<i title=\"$this->hovertext\" class=\"fa fa-warning\" style=\"color: var(--diseayellow);\"></i>";
            case self::STATUS_EMPTY:
                switch ($this->submissonstatus) {
                    case self::SUBMISSION_STATUS_NOTSUBMITTED:
                        $this->hovertext = get_string('assignment_view_hover_notsubmitted', 'block_disealytics');
                        return "<i title=\"$this->hovertext\" class=\"fa fa-times\" style=\"color: darkgray;\"></i>";
                    case self::SUBMISSION_STATUS_SUBMITTED:
                        $this->hovertext = get_string('assignment_view_hover_submitted', 'block_disealytics');
                        return "<i title=\"$this->hovertext\" class=\"fa fa-check\" style=\"color: darkgray;\"></i>";
                    case self::SUBMISSION_STATUS_INCOMPLETE:
                        $this->hovertext =
                                get_string('assignment_view_hover_incomplete', 'block_disealytics');
                        return "<i title=\"$this->hovertext\" class=\"fa fa-warning\" style=\"color: var(--diseayellow);\"></i>";
                    case self::SUBMISSION_STATUS_SELFCHECK:
                        $this->hovertext =
                                get_string('assignment_view_hover_selfcheck', 'block_disealytics');
                        return "<i title=\"$this->hovertext\" class=\"fa fa-check\" style=\"color: var(--diseayellow);\"></i>";
                    case self::SUBMISSION_STATUS_NEUTRAL:
                    case self::STATUS_EMPTY:
                    default:
                        $this->hovertext = get_string('assignment_view_hover_neutral', 'block_disealytics');
                        return "<i title=\"$this->hovertext\" class=\"fa fa-circle\" style=\"color: darkgray;\"></i>";
                }
            case self::GRADE_STATUS_INFO:
                return "<i title=\"$this->hovertext\" class=\"fa fa-info\" style=\"color: darkgray;\"></i>";
            case self::GRADE_STATUS_NEUTRAL:
            default:
                $this->hovertext = get_string('assignment_view_hover_neutral', 'block_disealytics');
                return "<i title=\"$this->hovertext\" class=\"fa fa-circle\" style=\"color: darkgray;\"></i>";
        }
    }

    /**
     * Generate the submission status for the assignment.
     *
     * @return int Returns the submission status:
     * self::SUBMISSION_STATUS_NOTSUBMITTED if the assignment has not been submitted.
     * self::SUBMISSION_STATUS_SUBMITTED if the assignment has been submitted.
     * self::SUBMISSION_STATUS_INCOMPLETE if the assignment's submission is incomplete.
     * self::SUBMISSION_STATUS_NEUTRAL if the submission status is neutral.
     *
     * @throws dml_exception
     */
    public function block_disealytics_gen_submission_status(): int {
        if ($this->block_disealytics_has_selfcheck() && !$this->block_disealytics_has_submission()) {
            return $this->submissonstatus = self::SUBMISSION_STATUS_SELFCHECK;
        }

        if ($this->modinfo->available) {
            if ($this->block_disealytics_needs_submission()) {
                if (!$this->block_disealytics_is_teamsubmission()) {
                    $opensubmissionsfrom = $this->assign->allowsubmissionsfromdate;
                    if ($opensubmissionsfrom > 0 && $opensubmissionsfrom > time()) {
                        return $this->submissonstatus = self::SUBMISSION_STATUS_INCOMPLETE;
                    } else {
                        if (
                            $this->block_disealytics_get_duedate() == 0
                                || $this->block_disealytics_has_submission_ontime()
                        ) {
                            if ($this->block_disealytics_gets_graded()) {
                                $this->gradestatus = $this->block_disealytics_gen_grade_status();
                            }
                            return $this->submissonstatus = self::SUBMISSION_STATUS_SUBMITTED;
                        } else {
                            if ($this->block_disealytics_has_submission()) {
                                if ($this->block_disealytics_gets_graded()) {
                                    $this->gradestatus = $this->block_disealytics_gen_grade_status();
                                }
                                return $this->submissonstatus = self::SUBMISSION_STATUS_INCOMPLETE;
                            } else {
                                if ($this->block_disealytics_gets_graded()) {
                                    $this->gradestatus = $this->block_disealytics_gen_grade_status();
                                }
                                return $this->submissonstatus = self::SUBMISSION_STATUS_NOTSUBMITTED;
                            }
                        }
                    }
                } else {
                    $this->gradestatus = self::STATUS_EMPTY;
                    return $this->submissonstatus = self::STATUS_EMPTY;
                }
            } else {
                if ($this->block_disealytics_gets_graded()) {
                    $this->gradestatus = $this->block_disealytics_gen_grade_status();
                    return $this->submissonstatus = self::STATUS_EMPTY;
                } else {
                    $this->gradestatus = self::STATUS_EMPTY;
                    return $this->submissonstatus = self::SUBMISSION_STATUS_NEUTRAL;
                }
            }
        } else { // Assignment not available.
            $this->gradestatus = self::STATUS_EMPTY;
            return $this->submissonstatus = self::SUBMISSION_STATUS_INCOMPLETE;
        }
    }

    /**
     * Generate the grade status for the learning dashboard item.
     *
     * @return int Returns the grade status:
     * self::GRADE_STATUS_FAILED if the item's grade is failed.
     * self::GRADE_STATUS_OKAY if the item's grade is okay.
     * self::GRADE_STATUS_INCOMPLETE if the item's grade is incomplete.
     * self::GRADE_STATUS_INFO if the grade type is scale and additional information is available.
     * self::GRADE_STATUS_NEUTRAL if the grade status is neutral.
     *
     * @throws dml_exception
     * @throws coding_exception
     */
    public function block_disealytics_gen_grade_status(): int {
        switch ($this->block_disealytics_get_gradetype()) {
            case self::GRADETYPE_SCALE:
                global $DB;
                $coursescale = null;
                if ($this->gradegrade) {
                    $coursescale = $DB->get_record("scale", ['id' => $this->gradegrade->rawscaleid]);
                }
                if ($coursescale && $coursescale->scale == get_string('assignment_view_specific_scale', 'block_disealytics')) {
                    if ($this->block_disealytics_has_grade()) {
                        switch ($this->block_disealytics_get_grade()) {
                            case 1:
                                if ($this->block_disealytics_has_tries_left()) {
                                    return self::GRADE_STATUS_INCOMPLETE;
                                } else {
                                    return self::GRADE_STATUS_FAILED;
                                }
                            case 2:
                                return self::GRADE_STATUS_INCOMPLETE;
                            case 3:
                                return self::GRADE_STATUS_OKAY;
                            default:
                                return self::GRADE_STATUS_NEUTRAL;
                        }
                    } else {
                        return self::STATUS_EMPTY;
                    }
                } else {
                    if ($this->block_disealytics_has_grade()) {
                        $this->hovertext = $this->block_disealytics_get_scale_grade_text();
                        return self::GRADE_STATUS_INFO;
                    } else {
                        return self::STATUS_EMPTY;
                    }
                }
            case self::GRADETYPE_VALUE:
                if ($this->block_disealytics_has_grade()) {
                    if ($this->block_disealytics_get_gradepass() > 0) {
                        if ($this->block_disealytics_get_grade() >= $this->block_disealytics_get_gradepass()) {
                            return self::GRADE_STATUS_OKAY;
                        } else {
                            if ($this->block_disealytics_has_tries_left()) {
                                return self::GRADE_STATUS_INCOMPLETE;
                            } else {
                                return self::GRADE_STATUS_FAILED;
                            }
                        }
                    } else {
                        return self::GRADE_STATUS_NEUTRAL;
                    }
                } else {
                    return self::STATUS_EMPTY;
                }
            default:
                return self::GRADE_STATUS_NEUTRAL;
        }
    }
}
