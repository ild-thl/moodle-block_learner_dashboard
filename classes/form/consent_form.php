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

namespace block_disealytics\form;

use coding_exception;
use moodleform;

require_once(__DIR__ . '/../../../../config.php');
global $CFG;
require_once("$CFG->libdir/formslib.php");
require_login();

/**
 * Form to give consent.
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consent_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     * @throws coding_exception
     */
    public function definition(): void {
        $choice = $this->_customdata['prevchoice'];

        $mform = $this->_form;
        $mform->addElement('radio', 'useragrees', '', get_string('disagree', 'block_disealytics'), '0');
        $mform->addElement('radio', 'useragrees', '', get_string('agree', 'block_disealytics'), '1');
        $mform->setDefault('useragrees', $choice);
        $this->add_action_buttons();
    }
}
