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
 * Structure step to restore one remarks activity
 */
class restore_remarks_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $remarks = new restore_path_element('remarks', '/activity/remarks');
        $paths[] = $remarks;

        if ($userinfo) {
            $upload = new restore_path_element('remarks_upload', '/activity/remarks/uploads/upload');
            $submission = new restore_path_element('remarks_submission', '/activity/remarks/uploads/upload/submissions/submission');
            $paths[] = $upload;
            $paths[] = $submission;
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_remarks($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        if ($data->remarkstype == MOD_REMARKS_GROUPMODE) {
            $data->groupingid = $this->get_mappingid('grouping', $data->groupingid);
        }

        // insert the remarks record
        $newitemid = $DB->insert_record('remarks', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_remarks_upload($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->remarksid = $this->get_new_parentid('remarks');
        $remarksobj = $DB->get_record('remarks', array('id' => $data->remarksid));
        if ($remarksobj->remarkstype == MOD_REMARKS_INDIVIDUALMODE) {
            $groupsmode = false;
        } else {
            $groupsmode = true;
        }

        $data->uploaduserid = $this->get_mappingid('user', $data->uploaduserid);
        if ($groupsmode) {
            $data->uploadedfor = $this->get_mappingid('group', $data->uploadedfor);
        } else {
            $data->uploadedfor = $this->get_mappingid('user', $data->uploadedfor);
        }
        $data->fileid = 0; //Fix this in after_execute

        $newitemid = $DB->insert_record('remarks_upload', $data);
        $this->set_mapping('remarks_upload', $oldid, $newitemid, true);
    }

    protected function process_remarks_submission($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->uploadid = $this->get_new_parentid('remarks_upload');

        $uploadobj = $DB->get_record('remarks_upload', array('id' => $data->uploadid));
        $remarksobj = $DB->get_record('remarks', array('id' => $uploadobj->remarksid));

        if ($remarksobj->remarkstype == MOD_REMARKS_INDIVIDUALMODE) {
            $groupsmode = false;
        } else {
            $groupsmode = true;
        }

        $data->submittedbyuserid = $this->get_mappingid('user', $data->submittedbyuserid);
        if ($groupsmode) {
            $data->submittedforid = $this->get_mappingid('group', $data->submittedforid);
        } else {
            $data->submittedforid = $this->get_mappingid('user', $data->submittedforid);
        }
        $data->fileid = 0; //Fix this in after_execute

        $newitemid = $DB->insert_record('remarks_submission', $data);
        $this->set_mapping('remarks_submission', $oldid, $newitemid, true);
    }

    protected function process_remarks_markermap($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->remarksid = $this->get_new_parentid('remarks');
        $data->markerid = $this->get_mappingid('user', $data->markerid);

        $remarksobj = $DB->get_record('remarks', array('id' => $data->remarksid));
        if ($remarksobj->remarkstype == MOD_REMARKS_INDIVIDUALMODE) {
            $data->markeeid = $this->get_mappingid('user', $data->markeeid);
        } else {
            $data->markeeid = $this->get_mappingid('group', $data->markeeid);
        }

        $newitemid = $DB->insert_record('remarks_markermap', $data);
        $this->set_mapping('remarks_markermap', $oldid, $newitemid, true);
    }

    protected function after_execute() {
        global $DB, $CFG;
        $remarksid = $this->get_new_parentid('remarks');
        $this->add_related_files('mod_remarks', 'intro', null);
        $this->add_related_files('mod_remarks', 'sharefile', null);
        $this->add_related_files('mod_remarks', 'upload', 'remarks_upload');
        $this->add_related_files('mod_remarks', 'submission', 'remarks_submission');

        /*
        * Locate upload and submission records we have just inserted, without reference to the relevant fileids
        * Now that the files have been created, we can update the relevant db records
        */
        require_once($CFG->dirroot . '/lib/moodlelib.php');
        $fs = get_file_storage();
        $contextsql = 'SELECT c.id, c.id as junk ' .
                'FROM {modules} m ' .
                ' INNER JOIN {course_modules} cm on cm.module = m.id ' .
                ' INNER JOIN {context} c on c.instanceid = cm.id ' .
                'WHERE c.contextlevel = 70 AND m.name = \'remarks\' AND cm.instance=?';
        $contexts = $DB->get_records_sql($contextsql, array($remarksid));
        $context = array_shift($contexts);
        $contextid = $context->id;
        $uploadssql = 'SELECT ru.* from {remarks_upload} ru ' .
                ' INNER JOIN {remarks} r ON r.id=ru.remarksid ' .
                'WHERE r.id = ? AND ru.fileid=0';
        $uploads = $DB->get_records_sql($uploadssql, array($remarksid));
        if (!empty($uploads)) {
            foreach ($uploads as $upload) {
                $uploadfiles = $fs->get_area_files($contextid, 'mod_remarks', 'upload', $upload->id, $sort="id", false);
                if (is_array($uploadfiles) && count($uploadfiles) === 1) {
                    $uploadfile = array_shift($uploadfiles);
                    $upload->fileid = $uploadfile->get_id();
                    $DB->update_record('remarks_upload', $upload);
                }
            }
        }
        $submissionssql = 'SELECT rs.* from {remarks_submission} rs ' .
                ' INNER JOIN {remarks_upload} ru ON ru.id=rs.uploadid ' .
                ' INNER JOIN {remarks} r ON r.id=ru.remarksid ' .
                'WHERE r.id = ? AND rs.fileid=0';
        $submissions = $DB->get_records_sql($submissionssql, array($remarksid));
        if (!empty($submissions)) {
            foreach ($submissions as $submission) {
                $submissionfiles = $fs->get_area_files($contextid, 'mod_remarks', 'submission', $submission->id, $sort="id", false);
                if (is_array($submissionfiles) && count($submissionfiles) === 1) {
                    $submissionfile = array_shift($submissionfiles);
                    $submission->fileid = $submissionfile->get_id();
                    $DB->update_record('remarks_submission', $submission);
                }
            }
        }
    }
}
