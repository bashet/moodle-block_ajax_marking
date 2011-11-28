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
 * This receives AJAX requests for config settings to be saved and processes them, sending a true
 * or false response, depending on whether all the data checked out and saved OK.
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__).'/../../../config.php');

require_login();

// Get POST data
$menuitemindex = required_param('menuitemindex', PARAM_INT);
$tablename     = required_param('tablename',     PARAM_ALPHAEXT);
$instanceid    = required_param('instanceid',    PARAM_INT);
$settingtype   = required_param('settingtype',   PARAM_ALPHAEXT);
$settingvalue  = optional_param('settingvalue',  null, PARAM_INT); // null = inherit
$groupid       = optional_param('groupid', 0,    PARAM_INT);

// Check for validity
$allowedtables = array('course', 'course_modules');
if (!in_array($tablename, $allowedtables)) {
    die('Invalid Table');
}
$instance = $DB->get_record($tablename, array('id' => $instanceid));
if (!$instance) {
    die('Invlaid table row');
}
$allowedsettings = array('display', 'groupsdisplay', 'group');
if (!in_array($settingtype, $allowedsettings)) {
    die('Invalid setting type');
}
if ($settingvalue > 1) {
    die('Invlaid setting value');
}
if ($groupid) {
    $group = $DB->get_record('groups', array('id' => $groupid));
    if (!$group) {
        die('Invalid group id');
    }
}

$existingsetting = $DB->get_record('block_ajax_marking', array('tablename' => $tablename,
                                                               'instanceid' => $instanceid,
                                                               'userid' => $USER->id));
if (!$existingsetting) {
    $existingsetting = new stdClass();
    $existingsetting->tablename  = $tablename;
    $existingsetting->instanceid = $instanceid;
    $existingsetting->userid     = $USER->id;
    $existingsetting->id = $DB->insert_record('block_ajax_marking', $existingsetting);
    if (!$existingsetting->id) {
        die('Could not create new setting');
    }
}

$existinggroupsettings = $DB->get_records('block_ajax_marking_groups',
                                   array('configid' => $existingsetting->id));

switch ($settingtype) {

    case 'display':
        $existingsetting->display = $settingvalue;
        $success = $DB->update_record('block_ajax_marking', $existingsetting);

        // For a course level node, we also want to update all child nodes, otherwise it could get
        // complex
        break;

    case 'groupsdisplay':
        $existingsetting->groupsdisplay = $settingvalue;
        $success = $DB->update_record('block_ajax_marking', $existingsetting);
        break;

    case 'group':

        if (!$groupid) {
            die('Need a group ID for a showgroup operation');
        }

        // Do we have an existing setting?
        $havegroupsettting = false;
        foreach ($existinggroupsettings as $groupsetting) {
            if ($groupsetting->groupid == $groupid) {
                $havegroupsettting = true;
                break; // Leaving $groupsetting as the one we want
            }
        }
        if ($havegroupsettting) {
            if (is_null($settingvalue)) {
                $params = array('id' => $groupsetting->id);
                $success = $DB->delete_records('block_ajax_marking_groups', $params);
            } else {
                $groupsetting->display = $settingvalue;
                $success = $DB->update_record('block_ajax_marking_groups', $groupsetting);
            }
        } else {
            if (is_null($settingvalue)) { // nothing to change
                $success = true;
            } else {
                $groupsetting = new stdClass();
                $groupsetting->configid = $existingsetting->id;
                $groupsetting->groupid = $groupid;
                $groupsetting->display = $settingvalue;
                $success = $DB->insert_record('block_ajax_marking_groups', $groupsetting);
            }
        }
        break;

}

$response = new stdClass();
$response->configsave = array('menuitemindex' => $menuitemindex,
                              'settingtype' => $settingtype,
                              'success' => $success,
                              'newsetting' => $settingvalue);

echo json_encode($response);
