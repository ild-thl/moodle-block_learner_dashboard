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
use core_date;
use DateTime;
use dml_exception;
use Exception;
use IntlDateFormatter;
use stdClass;

/**
 * Class planner
 * @package block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planner {
    /**
     * @var $day The day of the month.
     */
    private $day;
    /**
     * @var $dayname The name of the day.
     */
    private $dayname;
    /**
     * @var $month The month of the year.
     */
    private $month;
    /**
     * @var $monthname The name of the month.
     */
    private $monthname;
    /**
     * @var $year The year.
     */
    private $year;
    /**
     * @var $time The time.
     */
    private $time;
    /**
     * @var $storage The storage.
     */
    private $storage;
    /**
     * The DATETABLE to save all user events/dates.
     */
    private const DATETABLE = 'block_disealytics_user_dates';

    /**
     * planner constructor.
     * @param int $selecteddate The selected date.
     * @throws Exception
     */
    public function __construct($selecteddate) {
        $this->storage['dates'] = [];
        $now = new DateTime("now", core_date::get_user_timezone_object());
        if ($selecteddate === 0) {
            $this->day = intval($now->format('d'));
            $this->month = intval($now->format('m'));
            $this->year = intval($now->format('Y'));
        } else {
            $this->day = $selecteddate['day'];
            $this->month = $selecteddate['month'];
            $this->year = $selecteddate['year'];
        }
        $this->monthName = $this->block_disealytics_get_this_month_name();
    }

    /**
     * Create a new planner object.
     * @param int $selecteddate The selected date.
     * @return planner The planner object.
     * @throws Exception
     */
    public static function block_disealytics_create_planner($selecteddate): planner {
        return new planner($selecteddate);
    }

    /**
     * Get the current day.
     * @return int The day.
     */
    public function block_disealytics_get_this_day(): int {
        return $this->day;
    }

    /**
     * Get the current month.
     * @return int The month.
     */
    public function block_disealytics_get_this_month(): int {
        return $this->month;
    }

    /**
     * Get the current year.
     * @return int The year.
     */
    public function block_disealytics_get_this_year(): int {
        return $this->year;
    }

    /**
     * Get this month's name.
     * @return string The month name.
     * @throws coding_exception
     */
    public function block_disealytics_get_this_month_name(): string {
        global $USER;

        // Get the user's language preference.
        $userlanguage = get_user_preferences('lang', $USER->lang);

        // Create a DateTime object.
        $datetime = (new DateTime())->setDate($this->year, $this->month, $this->day);

        // Create an IntlDateFormatter with the user's preferred language.
        $formatter = new IntlDateFormatter($userlanguage, IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMMM');

        // Get the month name using the formatter.
        $this->monthName = $formatter->format($datetime);

        return $this->monthName;
    }

    /**
     * Get the current day name.
     * @param int $timestampstart The timestamp of the day.
     * @return string The day name.
     * @throws coding_exception
     */
    public function block_disealytics_get_this_day_name($timestampstart): string {
        global $USER;

        // Get the user's language preference.
        $userlanguage = get_user_preferences('lang', $USER->lang);

        // Create a DateTime object from the provided timestamp.
        $datetime = (new DateTime())->setTimestamp($timestampstart);

        // Create an IntlDateFormatter with the user's preferred language.
        $formatter = new IntlDateFormatter($userlanguage, IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'EEEE');

        // Get the full weekday name using the formatter.
        $fulldayname = $formatter->format($datetime);

        // Get the first two letters of the weekday name.
        $this->dayName = substr($fulldayname, 0, 2);

        return $this->dayName;
    }

    /**
     * Get the weekday names.
     * @return array Array of weekday names.
     */
    public static function block_disealytics_get_weekday_names(): array {
        // Create a table for the calendar.
        $mon = get_string('planner-view_monday_short', 'block_disealytics');
        $tue = get_string('planner-view_tuesday_short', 'block_disealytics');
        $wed = get_string('planner-view_wednesday_short', 'block_disealytics');
        $thu = get_string('planner-view_thursday_short', 'block_disealytics');
        $fri = get_string('planner-view_friday_short', 'block_disealytics');
        $sat = get_string('planner-view_saturday_short', 'block_disealytics');
        $sun = get_string('planner-view_sunday_short', 'block_disealytics');

        return [$mon, $tue, $wed, $thu, $fri, $sat, $sun];
    }

    /**
     * Get the days for the month.
     * @return array Array of days for the month.
     * @throws Exception
     */
    public function block_disealytics_get_days_for_month(): array {
        $selectedmonth = $this->month;
        $selectedyear = $this->year;
        $selectedday = $this->day;

        // Create a DateTime object for the first day of the month.
        $firstday = new DateTime("$selectedyear-$selectedmonth-01");

        // Get the number of days in the month.
        $daysinmonth = date('t', strtotime("$selectedyear-$selectedmonth-01"));

        $daynumbers = [];

        // Determine the offset for the first day of the month.
        $offset = $firstday->format('N') - 1;

        // Initialize a counter for the days.
        $daycounter = 1;

        // Start the loop to build the calendar.
        while ($daycounter <= $daysinmonth + $offset) {
            // Loop through each day of the week (Monday to Sunday).
            for ($i = 1; $i <= 7; $i++) {
                // Calculate the day number to display.
                $daynumbertoshow = $daycounter - $offset;

                // Check if the current day is within the valid range for the month.
                if ($daynumbertoshow >= 1 && $daynumbertoshow <= $daysinmonth) {
                    // Format the day with leading zeros.
                    $formattedday = sprintf('%02d', $daynumbertoshow);

                    // Create a DateTime object for the current day.
                    $selecteddate = new DateTime("$selectedyear-$selectedmonth-$formattedday");
                    $now = new DateTime("now", core_date::get_user_timezone_object());
                    $currentday = intval($now->format('d'));

                    // Check if the current day is today.
                    $iscurrentday = ($currentday == $selectedday) && ($selectedday == $daynumbertoshow);

                    // Check if there are appointments on the current day.
                    $appointmentsonday = $this->getAppointmentsForDay($selecteddate->getTimestamp());

                    // Include the full date information in the array.
                    $daynumbers[] = [
                            'daynumber' => $formattedday,
                            'isCurrentDay' => $iscurrentday,
                            'isOtherMonth' => false,
                            'fullDate' => $selecteddate->format('Y/m/d'), // Add this line.
                            'hasAppointment' => $appointmentsonday,
                    ];
                } else {
                    // Calculate the day number for the previous or next month.
                    $daynumbertoshow = ($daynumbertoshow <= 0)
                            ? date('t', strtotime("$selectedyear-$selectedmonth-01 -1 month")) + $daynumbertoshow
                            : $daynumbertoshow - $daysinmonth;

                    // Format the day with leading zeros.
                    $formattedday = sprintf('%02d', abs($daynumbertoshow));

                    // Create a DateTime object for the previous or next month.
                    $othermonthdate = new DateTime("$selectedyear-$selectedmonth-$formattedday");

                    // Show the day number for the previous or next month.
                    $daynumbers[] = [
                            'daynumber' => $formattedday,
                            'isCurrentDay' => false,
                            'isOtherMonth' => true,
                            'fullDate' => $othermonthdate->format('Y/m/d'),
                    ];
                }

                // Increment the counter.
                $daycounter++;
            }
        }

        return $daynumbers;
    }

    /**
     * Change the month display in the database.
     * @param int $day The day.
     * @param int $month The month.
     * @param int $year The year.
     * @return void
     * @throws coding_exception
     */
    public static function block_disealytics_change_month_display_in_database($day, $month, $year): void {
        $selecteddate = [
                'day' => $day,
                'month' => $month,
                'year' => $year,
        ];
        set_user_preference('block_disealytics_planner_currentdate', json_encode($selecteddate));
    }

    /**
     * Get the appointments of the database.
     * @throws dml_exception
     * @throws Exception
     */
    public function get_dates() {
        if (!$this->storage['dates']) {
            global $DB, $USER;
            $dates = $DB->get_records(
                self::DATETABLE,
                ['userid' => $USER->id]
            );
            $resultdates = [];
            foreach ($dates as $date) {
                $resultdates[] = $this->add_date_to_storage($date);
            }
            $this->storage['dates'] = $resultdates;
        }
        return $this->storage['dates'];
    }

    /**
     * Get the appointments for the regarding course.
     * @param int $courseid The course ID.
     * @return array Array of appointments for the day.
     * @throws dml_exception
     * @throws Exception
     */
    public function get_dates_for_course($courseid): array {
        global $DB, $USER;
        $dates = $DB->get_records(
            self::DATETABLE,
            ['userid' => $USER->id, 'courseid' => $courseid]
        );
        $resultdates = [];
        foreach ($dates as $date) {
            $resultdates[] = $this->add_date_to_storage($date);
        }
        return $resultdates;
    }

    /**
     * Get appointments for a specific day.
     *
     * @param int $timestamp Timestamp of the day.
     * @return array Array of appointments for the day.
     * @throws dml_exception
     */
    private function getappointmentsforday(int $timestamp): array {
        $appointments = [];

        // Filter appointments for the current day.
        foreach ($this->get_dates() as $appointment) {
            $appointmentdate = date('Y/m/d', $appointment->timestampStart);
            if ($appointmentdate == date('Y/m/d', $timestamp)) {
                $appointments[] = $appointment;
            }
        }

        return $appointments;
    }

    /**
     * Add a date to the storage.
     * @param stdClass $dbdate The database date.
     * @return stdClass The storage date.
     * @throws Exception
     */
    private function add_date_to_storage($dbdate): stdClass {
        global $DB;
        $storagedate = new stdClass();
        $storagedate->dateid = $dbdate->id;
        $storagedate->name = $dbdate->name;
        $storagedate->courseid = $dbdate->courseid;
        $timestampstart = $dbdate->timestart;
        $storagedate->timestampStart = $timestampstart;
        if ($dbdate->timeduration > 0) {
            $storagedate->hasEnd = true;
            $timestampend = $dbdate->timestart + $dbdate->timeduration;
            $storagedate->timestampEnd = $timestampend;
        } else {
            $storagedate->hasEnd = false;
        }
        $storagedate->location = $dbdate->location;
        $storagedate->eventType = $dbdate->eventtype;
        $course = $DB->get_record('course', ['id' => $dbdate->courseid]);
        $storagedate->courseName = $course->fullname;

        // Get weekday name for the date.
        $storagedate->dayNameShort = $this->block_disealytics_get_this_day_name($timestampstart);
        $storagedate->dayNumber = date('j', $timestampstart);

        return $storagedate;
    }

    /**
     * Add a date to the database.
     * @param stdClass $newdate The new date.
     * @return int The inserted ID.
     * @throws dml_exception
     */
    public static function add_date_to_database(stdClass $newdate) {
        global $DB;
        $now = (new DateTime())->format('U');
        $newdate->timecreated = $now;
        $newdate->timemodified = $now;

        // Insert the record and get the last inserted ID.
        $insertedid = $DB->insert_record(self::DATETABLE, $newdate);

        // Return the last inserted ID.
        return $insertedid;
    }

    /**
     * Add a date series to the database.
     * @param array $dateobjects The date objects.
     * @return void
     * @throws dml_exception
     */
    public static function add_date_series_to_database(array $dateobjects): void {
        global $DB;
        $count = count($dateobjects);
        $insertedid = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($i == 0) {
                $originaldate = $dateobjects[0];
                $insertedid = self::add_date_to_database($originaldate);
                $originaldate->id = $insertedid;
                $originaldate->repeatid = $insertedid;
                $DB->update_record(self::DATETABLE, $originaldate);
            } else {
                $date = $dateobjects[$i];
                $date->repeatid = $insertedid;
                self::add_date_to_database($date);
            }
        }
    }

    /**
     * Update a date in the database.
     * @param stdClass $updateddate The updated date.
     * @return void
     * @throws dml_exception
     */
    public static function update_date(stdClass $updateddate): void {
        global $DB;
        $now = (new DateTime())->format('U');
        if ($DB->record_exists(self::DATETABLE, ['id' => $updateddate->id])) {
            $updateddate->timemodified = $now;
            $DB->update_record(self::DATETABLE, $updateddate);
        }
    }

    /**
     * Delete a date from the database.
     * @param int $id The ID of the date.
     * @return void
     * @throws dml_exception
     */
    public static function delete_date($id): void {
        global $DB;
        if ($DB->record_exists(self::DATETABLE, ['id' => $id])) {
            $DB->delete_records(self::DATETABLE, ['id' => $id]);
        }
    }
}
