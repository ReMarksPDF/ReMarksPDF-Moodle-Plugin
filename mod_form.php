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
 * The main remarks configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package   mod_remarks
 * @copyright 2010 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_remarks_mod_form extends moodleform_mod {

    function definition() {

        global $COURSE,$DB,$USER;
        $mform =& $this->_form;

        /// Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

        /// Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('remarksname', 'remarks'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'remarksname', 'remarks');

        /// Adding the standard "intro" and "introformat" fields
        $this->add_intro_editor();

        $dtoptions = array('startyear' => (date('Y') -1), 'stopyear' => date('Y') + 5, 'timezone' => $USER->timezone, 'applydst' => true,'step'=>1,'optional'=>0);
        $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen','remarks'), $dtoptions);
        $thistimetomorrow = (int)((time() + 86400) / 3600) * 3600;
        $mform->setDefault('timeopen', $thistimetomorrow); // Default to opening about this time tomorrow
        $mform->addElement('date_time_selector', 'timedue', get_string('timedue','remarks'), $dtoptions);
        $weekfromtomorrow = $thistimetomorrow + 604800;
        $mform->setDefault('timedue', $weekfromtomorrow); // Default to being due in about 8 days time
        $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose','remarks'), $dtoptions);
        $mform->setDefault('timeclose', $weekfromtomorrow); // Default to being due in about 8 days time
        $assignmenttypes = array(MOD_REMARKS_INDIVIDUALMODE => get_string('individualmode', 'remarks'), MOD_REMARKS_GROUPMODE => get_string('groupmode', 'remarks'));
        $mform->addElement('select', 'remarkstype', get_string('assignmenttype', 'remarks'), $assignmenttypes);
        $groupinglist = $DB->get_records_select('groupings', 'courseid = ?', array($COURSE->id),'', 'id,name');
        foreach(array_keys($groupinglist) as $gid ) {
            $groupings[$gid] = $groupinglist[$gid]->name;
        }
        if (empty($groupings)) {
            $groupings = array(get_string('nogroupingsavailable','remarks'));
        } else {
            $groupings = array(0 => get_string('allgroupingsgroups', 'remarks')) + array_reverse($groupings,true);
        }
        $mform->addElement('select', 'groupingid', get_string('grouping', 'remarks'), $groupings);
        $mform->disabledIf('groupingid', 'remarkstype', 'eq', MOD_REMARKS_INDIVIDUALMODE);

        $mform->addElement('modgrade', 'grade', get_string('grade'), false);
        $mform->setDefault('grade', 100);

        $coursecontext = get_context_instance(CONTEXT_COURSE, $COURSE->id);
        plagiarism_get_form_elements_module($mform, $coursecontext);

        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();

        // add standard buttons, common to all modules
        $this->add_action_buttons();

    }
    function definition_after_data() {
        global $CFG, $COURSE;
        $mform =& $this->_form;
        $id = $mform->getSubmitValue('update');
        if ($id) {
            $timeopen = $mform->getElementValue('timeopen');
            if ($timeopen < time()) {
                # TODO: If uploads have occured already {
                #    $mform->disabledIf('timeopen', 'type', 'neq', 0);
                #    $mform->disabledIf('timeopen', 'type', 'eq', 0);
                #}
            }
        }
    }
    # TODO: Implement validation function or apply serverside rules around values being entered to make sure they make sense.
}
