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

namespace block_disealytics\view;

use block_disealytics\data\course;
use block_disealytics\data\style;
use block_disealytics\learningdata;
use coding_exception;
use core\chart_bar;
use core\chart_series;
use dml_exception;
use Exception;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/blocks/disealytics/classes/view/base_view.php');
require_once($CFG->dirroot . '/blocks/disealytics/classes/learningdata.php');

/**
 * Class progress_bar_view
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_bar_view extends base_view {
    /**
     * Title of the view.
     */
    private const TITLE = 'progress-bar-view';
    /**
     * @var learningdata
     */
    private learningdata $learningdata;

    /**
     * progress_bar_view constructor.
     *
     * @param $timeframe
     * @param learningdata $learningdata
     * @throws Exception
     */
    public function __construct($timeframe, learningdata $learningdata) {
        parent::__construct($timeframe);
        $this->learningdata = $learningdata;
    }

    /**
     * Checks if name exists.
     *
     * @param $name
     * @param $array
     * @return bool
     */
    private function check_name_exists($name, $array): bool {
        foreach ($array as $obj) {
            if ($name === $obj->documentname) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get user pages.
     * @return array of pages.
     * @throws moodle_exception
     * @throws dml_exception
     */
    private function get_user_pages(): array {
        global $DB, $COURSE;
        $output = [];
        $data = $this->learningdata->get_optional_inputs();
        $course = $DB->get_record('course', ['id' => $COURSE->id]);
        $modinfo = get_fast_modinfo($course);
        $cms = $modinfo->get_cms();
        $filescount = 0;
        $filenames = [];
        foreach ($cms as $cm) {
            if ($cm->visible && !$cm->deletioninprogress) {
                switch ($cm->modname) {
                    case "resource":
                    case "page":
                    case "url":
                        if (!$this->check_name_exists($cm->name, $data)) {
                            $addfile = new stdClass();
                            $addfile->name = $cm->name;
                            $filenames[] = $addfile;
                        }
                        $filescount++;
                        break;
                    case "folder":
                        // Gets the context associated with the folder ($cm->id == folder.instanceid).
                        // Sometimes more than one context is associated with the instanceid,
                        // therefore we need to get all the records.
                        $possiblecontexts = $DB->get_records("context", ["instanceid" => $cm->id]);
                        foreach ($possiblecontexts as $foldercontext) {
                            // Gets files stored in that context.
                            $foldercontents = $DB->get_records("files", ["contextid" => $foldercontext->id]);
                            foreach ($foldercontents as $file) {
                                if ($file->filename != ".") {
                                    $addfile = new stdClass();
                                    $addfile->name = $cm->name . $file->filepath . $file->filename;
                                    $filenames[] = $addfile;
                                    $filescount++;
                                }
                            }
                        }
                        break;
                    // Cases for new relevant modules (e.g. book) can be added below!
                    default:
                }
            }
        }

        $output['file_names'] = $filenames;
        $output['data'] = $data;
        $output['number_of_documents'] = $filescount;

        if ($filescount > 0) {
            $this->output['data_in_course'] = true;
        }

        return $output;
    }

    /**
     * Generate chart and preview.
     *
     * @param $data
     * @param $initalbars
     * @param $course
     * @return bool
     * @throws coding_exception
     */
    private function generate_chart_and_preview($data, $initalbars, $course): bool {
        if (!empty($data) && $course->courseid != 1) {
            $readpages = [];
            $lastpages = [];
            $leftpages = [];
            $names = [];
            $readpagespreview = [];
            $lastpagespreview = [];
            $leftpagespreview = [];
            $namespreview = [];
            $dataforpreview = array_slice($data, 0, $initalbars);

            foreach ($data as $date) {
                $readpages[] = $date->currentpage;
                $lastpages[] = $date->lastpage;
                $leftpages[] = $date->lastpage - $date->currentpage;
                $names[] = $date->documentname;
            }

            foreach ($dataforpreview as $date) {
                $readpagespreview[] = $date->currentpage;
                $lastpagespreview[] = $date->lastpage;
                $leftpagespreview[] = $date->lastpage - $date->currentpage;
                $namespreview[] = $date->documentname;
            }

            $chartpreview = new chart_bar();
            $chartpreview->set_horizontal(true);
            $chartpreview->set_stacked(true);
            $series1 = new chart_series(get_string('pages_read', 'block_disealytics'), $readpagespreview);
            $series1->set_color(style::BLOCK_DISEALYTICS_HIGHLIGHT_BLUE);
            $chartpreview->add_series($series1);
            $series2 = new chart_series(get_string('pages_left', 'block_disealytics'), $leftpagespreview);
            $series2->set_color(style::BLOCK_DISEALYTICS_SECONDARY_BLUE);
            $chartpreview->get_xaxis(0, true)->set_max((int) max($lastpagespreview) * 3);
            $chartpreview->add_series($series2);
            $chartpreview->set_labels($namespreview);
            $chartpreview->get_xaxis(0, true)->set_max((int) max($lastpagespreview));

            $chart = new chart_bar();
            $chart->set_horizontal(true);
            $chart->set_stacked(true);
            $series1 = new chart_series(get_string('pages_read', 'block_disealytics'), $readpages);
            $series1->set_color(style::BLOCK_DISEALYTICS_HIGHLIGHT_BLUE);
            $chart->add_series($series1);
            $series2 = new chart_series(get_string('pages_left', 'block_disealytics'), $leftpages);
            $series2->set_color(style::BLOCK_DISEALYTICS_SECONDARY_BLUE);
            $chart->add_series($series2);
            $chart->set_labels($names);
            $chart->get_xaxis(0, true)->set_max((int) max($lastpages));

            $course->show_dots = count($data) > $initalbars;
            $course->charts_preview[] = [
                    'chartdata' => json_encode($chartpreview),
                    'uniqid' => uniqid('block_disealytics_preview_'),
            ];
            $course->charts[] = [
                    'chartdata' => json_encode($chart), 'withtable' => true,
                    'uniqid' => uniqid('block_disealytics_'),
            ];

            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the output for the viewmode: module.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_module_output(): void {
        global $COURSE;
        // Texts.
        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('progress-bar_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('progress-bar_help_info_text', 'block_disealytics');
        $this->output["optional_input_help_info_text"] = get_string('optional_inputs_help_info_text', 'block_disealytics');
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            $this->output["viewmode_module"] = [];

            // Data for optional input modal.
            try {
                $this->output["optional_inputs"] = $this->get_user_pages();

                if (count($this->output["optional_inputs"]["file_names"]) > 0) {
                    $this->output["files_left"] = true;
                }
            } catch (dml_exception | moodle_exception $e) {
                debugging('Caught exception: ' . $e->getMessage());
            }

            // Display data for summary view.
            $course = new stdClass();
            $course->courseid = $COURSE->id;
            $data = $this->learningdata->get_optional_inputs(
                function ($v) {
                    return !empty($v->documentname);
                },
                $course->courseid
            );
            $initalbars = 3;
            if ($this->generate_chart_and_preview($data, $initalbars, $course)) {
                $this->output["viewmode_module"]['course'][] = $course;
            } else {
                $this->output["viewmode_module"]['nodata'] = true;
            }
        }
    }

    /**
     * Get the output for the viewmode: halfyear.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_halfyear_output(): void {
        global $USER;
        // Texts.
        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('progress-bar_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('progress-bar_help_info_text', 'block_disealytics');
        $this->output["optional_input_help_info_text"] = get_string('optional_inputs_help_info_text', 'block_disealytics');
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            $allusercourses = course::get_all_courses_of_user_current_semester($USER->id);

            foreach ($allusercourses as $course) {
                // Data for optional input modal.
                try {
                    $this->output["optional_inputs"] = $this->get_user_pages();

                    if (count($this->output["optional_inputs"]["file_names"]) > 0) {
                        $this->output["files_left"] = true;
                    }
                } catch (dml_exception | moodle_exception $e) {
                    debugging('Caught exception: ' . $e->getMessage());
                }

                // Display data for summary view.
                $data = $this->learningdata->get_optional_inputs(
                    function ($v) {
                        return !empty($v->documentname);
                    },
                    $course->courseid
                );
                $initalbars = 3;
                if ($this->generate_chart_and_preview($data, $initalbars, $course)) {
                    $this->output["viewmode_halfyear"]['course'][] = $course;
                }
            }
            if (empty($this->output["viewmode_halfyear"]['course'])) {
                $this->output["viewmode_halfyear"]['nodata'] = true;
            }
        }
    }

    /**
     * Get the output for the viewmode: global.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_global_output(): void {
        global $USER;
        // Texts.
        $this->output["title"] = get_string(self::TITLE, 'block_disealytics');
        $this->output["help_info_text"] = get_string('progress-bar_help_info_text', 'block_disealytics');
        $this->output["help_info_text_expanded"] = get_string('progress-bar_help_info_text', 'block_disealytics');
        // Viewmode settings.
        $iseditmode = get_user_preferences("block_disealytics_editing", "0");
        $this->output["isexpanded"] = get_user_preferences("block_disealytics_expanded_view", 'none') == self::TITLE;
        // If in editing mode.
        if ($iseditmode == 1) {
            $this->output["editmode"] = true;
        } else {
            $allusercourses = course::get_all_courses_of_user($USER->id);
            $semesterfilter = get_user_preferences("block_disealytics_" . self::TITLE, reset($allusercourses)->categoryname);
            $this->output["viewmode_global"]['course'] = [];
            $this->output["categories"] = [];
            foreach ($allusercourses as $course) {
                $categorydata = $course->categoryname;

                $issemester = ($semesterfilter === $categorydata);
                $categoryexists = false;
                foreach ($this->output["categories"] as $category) {
                    if ($category["name"] === $categorydata) {
                        $categoryexists = true;
                        break;
                    }
                }

                if (!$categoryexists) {
                    $this->output["categories"][] = ["name" => $categorydata, "selected" => $issemester];
                }

                if ($issemester) {
                    // Data for optional input modal.
                    try {
                        $this->output["optional_inputs"] = $this->get_user_pages();

                        if (count($this->output["optional_inputs"]["file_names"]) > 0) {
                            $this->output["files_left"] = true;
                        }
                    } catch (dml_exception | moodle_exception $e) {
                        debugging('Caught exception: ' . $e->getMessage());
                    }

                    // Display data for summary view.
                    $data = $this->learningdata->get_optional_inputs(
                        function ($v) {
                            return !empty($v->documentname);
                        },
                        $course->courseid
                    );
                    $initalbars = 3;
                    if ($this->generate_chart_and_preview($data, $initalbars, $course)) {
                        $this->output["viewmode_global"]['course'][] = $course;
                    }
                }
            }
            if (empty($this->output["viewmode_global"]['course'])) {
                $this->output["viewmode_global"]['nodata'] = true;
            }
        }
    }
}
