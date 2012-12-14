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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_remarks_activity_task
 */

/**
 * Define the complete remarks structure for backup, with file and id annotations
 */
class backup_remarks_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {
        global $DB, $CFG;
        $activityid = $this->task->get_activityid();
        $remarksobj = $DB->get_record('remarks', array('id' => $activityid));
        require_once($CFG->dirroot . '/mod/remarks/lib.php');
        if ($remarksobj->remarkstype == MOD_REMARKS_GROUPMODE) {
            $groupsmode = 1;
        } else {
            $groupsmode = 0;
        }
        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $remarks = new backup_nested_element('remarks', array('id'), array(
            'name', 'intro', 'introformat', 'timecreated',
            'timemodified', 'timedue', 'timeopen', 'timeclose',
            'remarkstype', 'groupingid', 'grade'
            ));

        $uploads = new backup_nested_element('uploads');

        $upload = new backup_nested_element('upload', array('id'), array(
            'fileid', 'timeupload', 'uploaduserid', 'uploadedfor'));

        $submissions = new backup_nested_element('submissions');

        $submission = new backup_nested_element('submission', array('id'), array(
            'timesubmission', 'submittedbyuserid', 'submittedforid',
            'draftrfc', 'resubmit', 'fileid', 'mark', 'version',
            'released', 'timereleased', 'originalityscore', 'originalityknown'));

        $markermaps = new backup_nested_element('markermaps');

        $markermap = new backup_nested_element('markermap', array('id'), array(
            'markerid', 'markeeid'));

        // Build the tree

        // Apply for 'remarks' subplugins optional stuff at remarks level (not multiple)
        // Remember that order is important, try moving this line to the end and compare XML
        //$this->add_subplugin_structure('remarks', $remarks, false);

        $remarks->add_child($uploads);
        $uploads->add_child($upload);

        $upload->add_child($submissions);
        $submissions->add_child($submission);

        $remarks->add_child($markermaps);
        $markermaps->add_child($markermap);

        // Apply for 'remarks' subplugins optional stuff at submission level (not multiple)
        //$this->add_subplugin_structure('remarks', $submission, false);

        // Define sources
        $remarks->set_source_table('remarks', array('id' => backup::VAR_ACTIVITYID));

        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            $upload->set_source_table('remarks_upload', array('remarksid' => backup::VAR_PARENTID));
            $submission->set_source_table('remarks_submission', array('uploadid' => backup::VAR_PARENTID));
            $markermap->set_source_table('remarks_markermap', array('remarksid' => backup::VAR_PARENTID));
        }

        // Define id annotations
        $upload->annotate_ids('user', 'uploaduserid');

        if ($groupsmode) {
            if ($remarksobj->groupingid) {
                $remarks->annotate_ids('grouping', 'groupingid');
            }
            $upload->annotate_ids('group', 'uploadedfor');
            $submission->annotate_ids('group', 'submittedforid');
        }
        $submission->annotate_ids('user', 'submittedbyuserid');

        $markermap->annotate_ids('user', 'markerid');
        if ($groupsmode) {
            // The other entitiy in the marking relationship is a group:
            $markermap->annotate_ids('group', 'markeeid');
        } else {
            // The other entitiy in the marking relationship is a user:
            $markermap->annotate_ids('user', 'markeeid');
        }

        // Define file annotations
        $remarks->annotate_files('mod_remarks', 'intro', null); // This file area hasn't itemid
        $remarks->annotate_files('mod_remarks', 'sharefile', null); // This file area uses id 0
        $upload->annotate_files('mod_remarks', 'upload', 'id');
        $submission->annotate_files('mod_remarks', 'submission', 'id');

        // Return the root element (remarks), wrapped into standard activity structure
        return $this->prepare_activity_structure($remarks);
    }
}
