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

/**
 * Block disealytics is defined here.
 *
 * @package    block_disealytics
 * @copyright 2021 onwards https://disea-projekt.de/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG, $PAGE;

require_once($CFG->dirroot . '/blocks/disealytics/classes/learningdata.php');

/**
 * Class block_disealytics
 */
class block_disealytics extends block_base {
    /**
     * Initializes class member variables.
     *
     * @throws coding_exception
     */
    public function init(): void {
        // Needed by Moodle to differentiate between blocks.
        $this->title = get_string('pluginname', 'block_disealytics');
    }

    /**
     * Gets the content of the block.
     * @throws dml_exception|coding_exception
     * @throws moodle_exception
     */
    public function get_content(): stdClass {
        // Since get_content gets called multiple times, this check always has to happen before actually calculating the content.
        if ($this->content !== null) {
            return $this->content;
        }
        global $CFG, $COURSE, $OUTPUT, $USER;
        $this->content = new stdClass();
        $this->content->text = "";

        // TODO: Rework this? Workaround to errors happening when index.php is viewed without being logged in.
        if (!isloggedin()) {
            $this->content->text = get_string('login_alert', 'block_disealytics');
            return $this->content;
        }

        if (strpos($this->page->pagetype, 'course-view-') === false) {
            $this->content->text = get_string('course_alert', 'block_disealytics');
            return $this->content;
        }

        $url = new moodle_url('/blocks/disealytics/consent.php', ['id' => $this->page->course->id]);
        if (!$this->getuserconsent()) {
            $plugintitle = get_string('plugin-title', 'block_disealytics');
            $consentmsg = get_string('consent_start_msg', 'block_disealytics');
            $consentbtn = get_string('consent_start_btn', 'block_disealytics');

            $templatecontext = (object) [
                    'url' => $url,
                    'plugintitle' => $plugintitle,
                    'consentmsg' => $consentmsg,
                    'consentbtn' => $consentbtn,
            ];

            $content = $OUTPUT->render_from_template('block_disealytics/config_menu_start', $templatecontext);
            $this->content->text = $content;
            return $this->content;
        } else {
            set_user_preference('block_disealytics_editing', '0');
            set_user_preference('block_disealytics_expanded_view', 'none');
            set_user_preference('block_disealytics_planner_currentdate', 0);

            // Set viewmode.
            $viewmode = get_user_preferences('block_disealytics_viewmode', 'viewmode_module');

            // Set up views from files.
            $views = [];
            foreach (glob($CFG->dirroot . '/blocks/disealytics/classes/view/*.php') as $filename) {
                $basename = basename($filename, '.php');
                if (!in_array($basename, ['base_view'])) {
                    $viewname = str_replace('_', '-', $basename);
                    // Set the view to enabled (1).
                    $views[] = (object) ['viewname' => $viewname, 'enabled' => 1];
                }
            }

            // Check if there are settings for the views in the database.
            $viewsinpref = json_decode(get_user_preferences('block_disealytics_views'), true);

            // If there are none, set them in the database.
            if (is_null($viewsinpref)) {
                set_user_preference('block_disealytics_views', json_encode($views));
                $viewsinpref = json_decode(get_user_preferences('block_disealytics_views'), true);
            } else {
                // If there are, load them.
                $viewsinpref = $this->loadviewsettings($viewsinpref);

                // Check if there are any PHP view files missing in the views and add them at the end.
                // Extract the viewnames from $views and $viewsinpref into separate arrays.
                $viewnames = array_column($views, 'viewname');
                $preferredviewnames = array_column($viewsinpref, 'viewname');

                // Check if there are any viewnames missing in $views.
                $missingnames = array_diff($viewnames, $preferredviewnames);

                // Add the missing viewnames to $views with enabled set to 1.
                foreach ($missingnames as $missingname) {
                    $viewsinpref[] = (object) ['viewname' => $missingname, 'enabled' => 1];
                }
                set_user_preference('block_disealytics_views', json_encode($viewsinpref));
            }

            // Hand over the data from the database to the update_view.js.
            $this->page->requires->js_call_amd(
                'block_disealytics/update_view',
                'init',
                [$viewsinpref, $viewmode, $COURSE->id, $url->out()]
            );
        }
        $footertext = get_string('testfooter', 'block_disealytics');
        $this->content->footer = $footertext;

        return $this->content;
    }

    /**
     * Loads and processes view settings based on user preferences.
     *
     * @param array $viewsinpref An array containing view preferences, typically obtained from user settings.
     *
     * @return array An array of processed view settings.
     */
    private function loadviewsettings(array $viewsinpref): array {
        $preferredviews = [];

        foreach ($viewsinpref as $view) {
            $viewname = $view['viewname'];
            $enabled = $view['enabled'];

            global $CFG;
            $viewunderscore = str_replace('-', '_', $viewname);

            // Load the PHP file for the view (e.g., 'some_view.php') based on the view name.
            $filepath = $CFG->dirroot . '/blocks/disealytics/classes/view/' . $viewunderscore . '.php';

            if (file_exists($filepath)) {
                require_once($filepath);
                // Add the view settings to the list of preferred views.
                $preferredviews[] = (object) ['viewname' => $viewname, 'enabled' => $enabled];
            }
        }

        return $preferredviews;
    }

    /**
     * Checks if the user has given consent to use the DiSEA Dashboard.
     * @throws dml_exception
     */
    private function getuserconsent(): bool {
        global $DB, $USER;
        $consent = $DB->get_record('block_disealytics_consent', ['userid' => $USER->id], 'choice');
        return !empty($consent) && ($consent->choice);
    }

    /**
     * Checks for applicable formats.
     * @return array
     */
    public function applicable_formats(): array {
        return [
                'all' => false,
                'course-view' => true,
                'mod' => false,
        ];
    }

    /**
     * Checks for configurations.
     * @return bool
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Checks for header.
     * @return bool
     */
    public function hide_header(): bool {
        return true;
    }
}
