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
 * Main block file
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2008 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This class builds a marking block on the front page which loads assignments and submissions
 * dynamically into a tree structure using AJAX. All marking occurs in pop-up windows and each node
 * removes itself from the tree after its pop up is graded.
 *
 * @copyright 2008-2010 Matt Gibson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ajax_marking extends block_base {

    /**
     * Standard init function, sets block title and version number
     *
     * @return void
     */
    function init() {
        $this->title = get_string('ajaxmarking', 'block_ajax_marking');
        $this->version = 2010050601;
    }

    /**
     * Standard specialization function
     *
     * @return void
     */
    function specialization() {
            $this->title = get_string('marking', 'block_ajax_marking');
    }

    /**
     * Standard get content function returns $this->content containing the block HTML etc
     *
     * @return object
     */
    function get_content() {

        if ($this->content !== null) {
            return $this->content;
        }
        
        global $CFG, $USER, $DB, $PAGE;

        require_once($CFG->dirroot.'/blocks/ajax_marking/lib.php');

        $courses = block_ajax_marking_get_my_teacher_courses();

        if (count($courses) > 0) { // Grading permissions exist in at least one course, so display the block

            //start building content output
            $this->content = new stdClass;

            // make the non-ajax list whatever happens. Then allow the AJAX tree to usurp it if
            // necessary
//            require_once($CFG->dirroot.'/blocks/ajax_marking/actions/html_list.php');
//            
//            $this->content->text .= '<div id="block_ajax_marking_html_list">';
//            $this->content->text .= $htmllist;
//            $this->content->text .= '</div>';
            $this->content->footer = '';

            // Build the AJAX stuff on top of the plain HTML list
            if ($CFG->enableajax && $USER->ajax && !$USER->screenreader) {

                // Add a style to hide the HTML list and prevent flicker
                $s  = '<script type="text/javascript" defer="defer">
                      /* <![CDATA[ */ 
                      var styleElement = document.createElement("style");
                      styleElement.type = "text/css";
                      if (styleElement.styleSheet) {
                          styleElement.styleSheet.cssText = "#block_ajax_marking_html_list { display: none; } " +
                                                            "#treetabs { display: none; } ";
                      } else {
                          styleElement.appendChild(document.createTextNode("#block_ajax_marking_html_list {display: none;}"));
                          styleElement.appendChild(document.createTextNode("#treetabs {display: none;}"));
                      }
                      document.getElementsByTagName("head")[0].appendChild(styleElement);
                      /* ]]> */</script>';

                $this->content->text .=  $s;

                // Set up the javascript module, with any data that the JS will need
                $jsmodule = array(
                    'name'     => 'block_ajax_marking',
                    'fullpath' => '/blocks/ajax_marking/module.js.php',
                    'requires' => array('yui2-treeview', 'yui2-button', 'yui2-connection', 'yui2-json', 'yui2-container', 'tabview'),
                    'strings' => array(
                        array('totaltomark',         'block_ajax_marking'),
                        array('instructions',        'block_ajax_marking'),
                        array('nogradedassessments', 'block_ajax_marking'),
                        array('nothingtomark',       'block_ajax_marking'),
                        array('refresh',             'block_ajax_marking'),
                        array('configure',           'block_ajax_marking'),
                        array('connectfail',         'block_ajax_marking'),
                        array('nogroups',            'block_ajax_marking'),
                        array('settingsheadertext',  'block_ajax_marking'),
                        array('showthisassessment',  'block_ajax_marking'),
                        array('showthiscourse',      'block_ajax_marking'),
                        array('showwithgroups',      'block_ajax_marking'),
                        array('hidethisassessment',  'block_ajax_marking'),
                        array('hidethiscourse',      'block_ajax_marking'),
                        array('coursedefault',       'block_ajax_marking'),
                        array('hidethiscourse',      'block_ajax_marking')
                    )
                );

                // Add the basic HTML for the rest of the stuff to fit into
                $this->content->text .= '
                    <div id="block_ajax_marking_top_bar">
                        <div id="total">
                            <div id="totalmessage">'.get_string('totaltomark', 'block_ajax_marking').': <span id="count"></span></div>
                            <div id="count"></div>
                            <div id="mainicon"></div>
                        </div>
                        <div id="status"></div>
                    </div>
                    <div id="treetabs">
                        <ul>
                            <li><a href="#coursestree">Courses</a></li>
                            <li><a href="#groupstree">Groups</a></li>
                            <li><a href="#configtree">Config</a></li>
                        </ul>
                        <div>
                            <div id="coursestree">course tree goes here</div>
                            <div id="groupstree">Still in beta for 2.0 - groups tree will go here once done</div>
                            <div id="configtree">Still in beta for 2.0 - settings stuff will go here once done</div>
                        </div>
                    </div>';
                $this->content->footer .= '<div id="block_ajax_marking_refresh_button"></div>
                                           <div id="block_ajax_marking_configure_button"></div>';

                // Don't warn about javascript if the screenreader option is set - it was deliberate
                if (!$USER->screenreader) {
                    $this->content->text .= '<noscript><p>'.get_string('nojavascript', 'block_ajax_marking').'</p></noscript>';
                }

//                if ($CFG->debug == DEBUG_DEVELOPER) {
//                    $PAGE->requires->yui2_lib('logger');
//                }

                // Set things going
                $PAGE->requires->js_init_call('M.block_ajax_marking.initialise', null, true, $jsmodule);

            }

        } else {
            // no grading permissions in any courses - don't display the block (student). Exception for
            // when the block is just installed and the user can edit. Might look broken otherwise.
            if (has_capability('moodle/course:manageactivities', $PAGE->context)) { 
                $this->content->text .= get_string('nogradedassessments', 'block_ajax_marking');
                $this->content->footer = '';
            } else {
                // this will stop the other functions like has_content() from running all the way through this again
                $this->content = false;
            }

        }
        
        return $this->content;
    }

    /**
     * Standard function - does the block allow configuration for specific instances of itself
     * rather than sitewide?
     *
     * @return bool false
     */
    function instance_allow_config() {
        return false;
    }

    /**
     * Runs the check for plugins after the first install.
     *
     * @return void
     */
//    function after_install() {
//
//        echo 'after install';
//
//        global $CFG;
//
//        include_once($CFG->dirroot.'/blocks/ajax_marking/db/upgrade.php');
//        //block_ajax_marking_update_modules();
//
//    }
    
    
}