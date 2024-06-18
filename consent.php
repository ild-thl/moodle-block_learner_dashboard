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

/**
 * Consent file.
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
global $CFG;
require_once($CFG->dirroot . '/blocks/disealytics/classes/form/consent_form.php');
use block_disealytics\form\consent_form;

global $DB, $USER, $PAGE, $OUTPUT;

$PAGE->set_url(new moodle_url('/blocks/disealytics/consent.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title(get_string('pluginname', 'block_disealytics'));

// Get Course ID from url to be able to redirect.
$courseid = optional_param('id', null, PARAM_INT);
// Check id user is already in Database.
$consentdata = $DB->get_record('block_disealytics_consent', ['userid' => $USER->id]);

// Create redirecting url.
$url = $CFG->wwwroot.'/blocks/disealytics/consent.php?id=' . $courseid;
$courseurl = $CFG->wwwroot . '/course/view.php?id=' . $courseid;

$counter = get_config("block_disealytics", "counter") ?: 1;

// Make the form.
$mform = new consent_form($url, ["prevchoice" => $consentdata ? $consentdata->choice : 0]);

// Check response from consent_form.
if ($mform->is_cancelled()) {
    if (!$consentdata || $consentdata->counter < $counter) {
        // If user wasn't in database and wants to cancel, stay on this page.
        redirect($url);
    } else {
        // If user is already in database and cancels, return to course.
        redirect($courseurl);
    }
} else if (($formdata = data_submitted()) && confirm_sesskey()) {
    if ($formdata->useragrees == null) {
        redirect($url, get_string('no_choice', 'block_disealytics'), null, \core\output\notification::NOTIFY_ERROR);
    }

    if (!$consentdata) {
        // If user is not in the database.
        $consententry = new stdClass();
        $consententry->userid = $USER->id;
        $consententry->counter = $counter;
        $consententry->choice = intval($formdata->useragrees);
        $consententry->timecreated = time();
        $consententry->timemodified = time();
        $DB->insert_record('block_disealytics_consent', $consententry);
        redirect($courseurl, get_string('database_insert', 'block_disealytics'));
    } else {
        // If user is in database, it needs to be updated.
        $consentdata->choice = intval($formdata->useragrees);
        $consentdata->counter = $counter;
        $consentdata->timemodified = time();
        $DB->update_record('block_disealytics_consent', $consentdata);
        redirect($courseurl, get_string('database_update', 'block_disealytics'));
    }
}

echo $OUTPUT->header();

$templatecontext = (object)[
    'consent' => get_config("block_disealytics", 'consent_text') ?: "",
];

echo $OUTPUT->render_from_template('block_disealytics/agreement', $templatecontext);
$mform->display();
echo $OUTPUT->footer();
