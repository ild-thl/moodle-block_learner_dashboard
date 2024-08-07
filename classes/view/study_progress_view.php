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
// phpcs:ignore
namespace block_disealytics\view;

use block_disealytics\data\assignment;
use block_disealytics\data\course;
use block_disealytics\data\style;
use coding_exception;
use core\chart_bar;
use core\chart_series;
use dml_exception;
use Exception;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/blocks/disealytics/classes/view/base_view.php');
require_once($CFG->dirroot . '/blocks/disealytics/classes/data/assignment.php');
require_once($CFG->dirroot . '/blocks/disealytics/classes/data/style.php');

/**
 * Class study_progress_view
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class study_progress_view extends base_view {
    /**
     * Title of the view.
     */
    private const TITLE = 'study-progress-view';
    /**
     * score
     * @var float
     */
    private float $score;
    /**
     * score halfyear
     * @var float
     */
    private float $scorehalfyear;
    /**
     * score global
     * @var float
     */
    private float $scoreglobal;
    /**
     * SCOREMIDTHRESHOLD
     */
    private const SCOREMIDTHRESHOLD = -0.34;
    /**
     * SCOREHIGHTHRESHOLD
     */
    private const SCOREHIGHTHRESHOLD = 0.34;
    /**
     * weight of assignment
     */
    private const ASSIGNMENTWEIGHT = .50;
    /**
     * weight of document
     */
    private const DOCUMENTWEIGHT = .35;
    /**
     * weight of activity
     */
    private const ACTIVITYWEIGHT = .15;

    /**
     * study_progress_view constructor.
     *
     * @param $timeframe
     * @throws Exception
     * @throws moodle_exception
     */
    public function __construct($timeframe) {
        global $COURSE;
        parent::__construct($timeframe);
        if ($COURSE->id != 1 && count(assignment::block_disealytics_get_course_assignments($COURSE->id)) > 0) {
            $this->score = $this->compute_score('module');
        } else {
            unset($this->score);
        }
    }

    /**
     * Compute the score.
     *
     * @param $type
     * @return float
     * @throws moodle_exception
     */
    private function compute_score($type): float {
        global $USER, $COURSE;

        $maxassignscore = 0;
        $assignscore = 0;

        switch ($type) {
            case 'halfyear':
                $courses = course::get_all_courses_of_user_current_semester($USER->id);
                break;
            case 'global':
                $courses = course::get_all_courses_of_user($USER->id);
                break;
            case 'module':
            default:
                $course = new stdClass();
                $course->courseid = $COURSE->id;
                $courses[] = $course;
                break;
        }

        foreach ($courses as $course) {
            foreach (assignment::block_disealytics_get_course_assignments($course->courseid) as $assign) {
                switch ($assign->block_disealytics_gen_grade_status()) {
                    case assignment::GRADE_STATUS_FAILED:
                        $assignscore--;
                        break;
                    case assignment::GRADE_STATUS_OKAY:
                        $assignscore++;
                        break;
                    case assignment::STATUS_EMPTY:
                        switch ($assign->block_disealytics_gen_submission_status()) {
                            case assignment::SUBMISSION_STATUS_SUBMITTED:
                                $assignscore++;
                                break;
                            case assignment::SUBMISSION_STATUS_NOTSUBMITTED:
                                $assignscore--;
                                break;
                        }
                        break;
                }
                $maxassignscore++;
            }
        }

        if ($maxassignscore > 0) {
            $assignscore /= $maxassignscore;
        } else {
            $assignscore = 0;
        }

        // Neutral (0) placeholders for not yet implemented score components.
        $documentscore = 0;
        $activityscore = 0;

        return $assignscore * self::ASSIGNMENTWEIGHT + $documentscore
                * self::DOCUMENTWEIGHT + $activityscore * self::ACTIVITYWEIGHT;
    }

    /**
     * Create the study progress chart.
     * @return void
     */
    protected function create_study_progress_chart(): void {
        $chart = new chart_bar();
        $chart->set_stacked(true);
        $series = new chart_series("", [
                self::ACTIVITYWEIGHT * 100,
                self::DOCUMENTWEIGHT * 100,
                self::ASSIGNMENTWEIGHT * 100,
        ]);
        $series2 = new chart_series("", [
                100 - (self::ACTIVITYWEIGHT * 100),
                100 - (self::DOCUMENTWEIGHT * 100),
                100 - (self::ASSIGNMENTWEIGHT * 100),
        ]);
        $series->set_color(style::BLOCK_DISEALYTICS_HIGHLIGHT_BLUE);
        $series2->set_color(style::BLOCK_DISEALYTICS_SECONDARY_BLUE);

        $labels = [get_string("study-progress_activity", "block_disealytics"),
            get_string("study-progress_doc", "block_disealytics"),
            get_string("study-progress_assign", "block_disealytics")];
        $series->set_labels($labels);
        $series2->set_labels([]);
        $chart->add_series($series);
        $chart->add_series($series2);
        $chart->set_legend_options(['display' => false]);
        $chart->set_labels($labels);
        $xaxis = $chart->get_yaxis(0, true);
        $xaxis->set_min(0);
        $xaxis->set_max(100);
        $this->output['barchart'] = ['chartdata' => json_encode($chart), 'withtable' => false,
                'uniqid' => uniqid('block_disealytics_')];
        $this->output['activityweight'] = self::ACTIVITYWEIGHT * 100;
        $this->output['documentweight'] = self::DOCUMENTWEIGHT * 100;
        $this->output['assignmentweight'] = self::ASSIGNMENTWEIGHT * 100;

        $this->output['data'] = 'true';
    }

    /**
     * Create the score angle.
     *
     * @param float $val (-1..1 as float)
     * @return float angle to rotate the needle from vertical (-90..90)
     */
    private function scoretoangle(float $val): float {
        return -90 + (($val + 1) * (180 / 2));
    }

    /**
     * Get the output for the viewmode: module.
     * @return void
     * @throws coding_exception
     */
    protected function get_module_output(): void {
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            $this->output["viewmode"] = true;
        }
        $this->output["viewmode_module"] = true;

        // Texts.
        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('study-progress_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('study-progress_help_info_text_expanded', 'block_disealytics');

        // Lade je nach Score die passende Darstellung.
        if (isset($this->score)) {
            if ($this->score < self::SCOREMIDTHRESHOLD) {
                $this->output["infotext"] = get_string('study-progress_infotext_bad', 'block_disealytics');
            }
            if ($this->score >= self::SCOREMIDTHRESHOLD && $this->score < self::SCOREHIGHTHRESHOLD) {
                $this->output["infotext"] = get_string('study-progress_infotext_average', 'block_disealytics');
            }

            if ($this->score >= self::SCOREHIGHTHRESHOLD) {
                $this->output["infotext"] =
                        get_string('study-progress_infotext_good', 'block_disealytics');
            }
            $this->output['assignscore'] = round($this->score, 2);
            $this->output["angle"] = round(style::map($this->score, -1, 1, -90, 90), 2);

            $this->create_study_progress_chart();
        } else {
            $this->output['nodata'] = get_string('nodata', 'block_disealytics');
        }
    }

    /**
     * Get the output for the viewmode: halfyear.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function get_halfyear_output(): void {
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            $this->output["viewmode_halfyear"] = true;
        }

        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('study-progress_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('study-progress_help_info_text_expanded', 'block_disealytics');

        $this->scorehalfyear = $this->compute_score('halfyear');

        if (isset($this->scorehalfyear)) {
            if ($this->scorehalfyear < self::SCOREMIDTHRESHOLD) {
                $this->output["infotext"] = get_string('study-progress_infotext_bad', 'block_disealytics');
            }
            if ($this->scorehalfyear >= self::SCOREMIDTHRESHOLD && $this->scorehalfyear < self::SCOREHIGHTHRESHOLD) {
                $this->output["infotext"] = get_string('study-progress_infotext_average', 'block_disealytics');
            }

            if ($this->scorehalfyear >= self::SCOREHIGHTHRESHOLD) {
                $this->output["infotext"] =
                        get_string('study-progress_infotext_good', 'block_disealytics');
            }
            $this->output['assignscore'] = round($this->scorehalfyear, 2);
            $this->output["angle"] = round(style::map($this->scorehalfyear, -1, 1, -90, 90), 2);

            $this->create_study_progress_chart();
        } else {
            $this->output['nodata'] = get_string('nodata', 'block_disealytics');
        }
    }

    /**
     * Get the output for the viewmode: global.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function get_global_output(): void {
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            $this->output["viewmode_global"] = true;
        }
        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('study-progress_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('study-progress_help_info_text_expanded', 'block_disealytics');

        $this->scoreglobal = $this->compute_score('global');
        if (isset($this->scoreglobal)) {
            if ($this->scoreglobal < self::SCOREMIDTHRESHOLD) {
                $this->output["infotext"] = get_string('study-progress_infotext_bad', 'block_disealytics');
            }
            if ($this->scoreglobal >= self::SCOREMIDTHRESHOLD && $this->scoreglobal < self::SCOREHIGHTHRESHOLD) {
                $this->output["infotext"] = get_string('study-progress_infotext_average', 'block_disealytics');
            }

            if ($this->scoreglobal >= self::SCOREHIGHTHRESHOLD) {
                $this->output["infotext"] =
                        get_string('study-progress_infotext_good', 'block_disealytics');
            }
            $this->output['assignscore'] = round($this->scoreglobal, 2);
            $this->output["angle"] = round(style::map($this->scoreglobal, -1, 1, -90, 90), 2);

            $this->create_study_progress_chart();
        } else {
            $this->output['nodata'] = get_string('nodata', "block_disealytics");
        }
    }
}
