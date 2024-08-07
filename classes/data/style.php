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
 * Class style
 * @package block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class style {
    /**
     * Highlight blue color.
     */
    const BLOCK_DISEALYTICS_HIGHLIGHT_BLUE = "#0f6cbf";
    /**
     * Secondary blue color.
     */
    const BLOCK_DISEALYTICS_SECONDARY_BLUE = "#bfcade";
    /**
     * Orange color.
     */
    const BLOCK_DISEALYTICS_ORANGE = "#ff9500";

    /**
     * Map a value from one range to another.
     *
     * @param mixed $val
     * @param mixed $inputmin
     * @param mixed $inputmax
     * @param mixed $outputmin
     * @param mixed $outputmax
     * @return float|int
     */
    public static function map($val, $inputmin, $inputmax, $outputmin, $outputmax) {
        $inputrange = $inputmax - $inputmin;
        $outputrange = $outputmax - $outputmin;
        $scale = $outputrange / $inputrange;
        $trueval = $val - $inputmin;
        return $outputmin + ($trueval * $scale);
    }
}
