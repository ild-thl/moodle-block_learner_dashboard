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

use block_disealytics\learningdata;
use coding_exception;
use DateInterval;
use DatePeriod;
use Exception;

/**
 * Class base_view
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_view {
    /**
     * @var array Output
     */
    public array $output;
    /**
     * @var  Start date
     */
    protected  $start;
    /**
     * @var  End date
     */
    protected  $end;

    /**
     * base_view constructor.
     *
     * @param  $timeframe
     * @throws Exception
     */
    public function __construct( $timeframe) {
        $timeframedates = learningdata::get_current_halfyear_dates();
        $this->start = $timeframedates["start"];
        $this->end = $timeframedates["end"];
    }

    /**
     * Gives an array with all the calenderweeks in between the input dates.
     *
     * @param  $unit
     * @return array
     * @throws Exception
     */
    protected function dividetimeframe( $unit): array {
        $interval = new DateInterval('P1' . $unit);
        $daterange = new DatePeriod($this->start, $interval, $this->end);
        $names = [];
        foreach ($daterange as $date) {
            $name = $date->format($unit);
            $names[$name] = 0;
        }
        return $names;
    }

    /**
     * Gives an array with all the calenderweeks in between the input dates.
     *
     * @param  $start
     * @param  $end
     * @return array
     * @throws Exception
     */
    protected static function get_weeknrs( $start, $end): array {
        $interval = new DateInterval('P1W');
        $daterange = new DatePeriod($start, $interval, $end);
        $names = [];
        foreach ($daterange as $date) {
            $name = $date->format("W");
            $names[] = $name;
        }
        return $names;
    }

    /**
     * Generates the output for the views.
     * @return array
     * @throws coding_exception
     */
    public function get_output(): array {
        $viewmode = get_user_preferences("block_disealytics_viewmode", "viewmode_module");
        if ($viewmode == "viewmode_module") {
            $this->get_module_output();
        } else if ($viewmode == "viewmode_halfyear") {
            $this->get_halfyear_output();
        } else {
            $this->get_global_output();
        }
        return $this->output;
    }

    /**
     * Generates the output for the module view.
     */
    abstract protected function get_module_output();
    /**
     * Generates the output for the halfyear view.
     */
    abstract protected function get_halfyear_output();
    /**
     * Generates the output for the global view.
     */
    abstract protected function get_global_output();
}

/**
 * Timeframe weeks
 */
const TIMEFRAME_WEEKS = "W";
/**
 * Timeframe months
 */
const TIMEFRAME_MONTHS = "M";
