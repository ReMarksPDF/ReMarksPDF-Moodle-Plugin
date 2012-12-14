<?php

 /* @copyright 2011 Remarks Pty
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Determine if a provided userid is a valid submitter in the specified remarks assignment context
 * This function could be called repetitively
 * @param object $context - the context to be considered
 * @param object $assignment - various details about the remarks assignment activity - type & grouping id
 * @param int $submitterid - the id of the user being checked
 * @return bool
 */
function remarks_is_valid_submitter($context, $assignment, $submitterid) {
    static $validsubmitters;
    if (!isset($validsubmitters)) {
        if (empty($assignment->remarkstype) || ($assignment->remarkstype == MOD_REMARKS_INDIVIDUALMODE)) {
            // This is an individual mode assignment - valid submitters are users with submit capability
            $validsubmitters = get_enrolled_users($context, 'mod/remarks:submit', 0, 'u.id,u.email', 'u.id');
        } else {
            // This is a groupmode assignment - valid submitters are groups
            if (!empty($assignment->groupingid)) {
                // grouping has been specified for this assignment, only groups in that grouping are valid
                $groupingid = $assignment->groupingid;
            } else {
                $groupingid = 0;
            }
            $validsubmitters = groups_get_all_groups($assignment->course, 0, $groupingid, $fields='g.id, g.name');
        }
    }
    if (isset($validsubmitters[$submitterid])) {
        return true;
    } else {
        return false;
    }
}

/** * Determine if a provided userid is a valid marker in the specified context
 * This function could be called repetitively
 * @param object $context - the context to be considered
 * @param int $markerid - the id of the user being checked
 * @return bool
 */
function remarks_is_valid_marker($context, $markerid) {
    static $validmarkers;
    if (!isset($validmarkers)) {
        $validmarkers = get_enrolled_users($context, 'mod/remarks:mark', 0, 'u.id,u.email', 'u.id');
    }
    if (isset($validmarkers[$markerid])) {
        return true;
    } else {
        return false;
    }
}

/**
 * Determine if a provided userid is an allocated marker for a specific submission
 * @param int $submissionid - the id of the submission
 * @param int $markerid - the id of the user being checked
 * @return bool
 */
function remarks_is_allocated_marker($markerid, $submissionid) {
    global $DB;
    $sql = 'SELECT * ' .
            'FROM {remarks_submission} rs' .
            ' INNER JOIN {remarks_upload} ru on rs.uploadid = ru.id ' .
            ' INNER JOIN {remarks_markermap} rmm on rmm.markeeid = ru.uploadedfor AND rmm.remarksid=ru.remarksid ' .
            'WHERE rs.id = :submissionid' .
            ' AND rmm.markerid = :markerid';
    $details = $DB->get_records_sql($sql, array('submissionid' => $submissionid, 'markerid' => $markerid));
    if (count($details)) {
        return true;
    } else {
        return false;
    }
}


/**
 * Print box relating to a single (potential) submitter (can be group or user)
 * @param object $remarks - describing the relevant remarks coursemodule activity
 * @param int $submitterid - the id of the relevant user or group
 * @param object $cmcontext - the context of the remarks coursemodule activity
 */
function remarks_print_submitterbox($remarks, $submitterid, $cmcontext) {
    global $DB, $OUTPUT;
    $capupload = has_capability('mod/remarks:upload', $cmcontext, null, false);
    $capsubmit = has_capability('mod/remarks:submit', $cmcontext, null, false);

    $items = remarks_get_submitter_items($submitterid, $remarks->id);
    // Get info about whether this submitter have already uploaded, if they have already submitted,
    // and if they can submit again.
    list($uploaded, $submitted, $cansubmit) = remarks_get_submission_status($submitterid, $items);

    // Work out where this assignment is in its lifecycle: preopen->open->due->closed
    $timenow = time();
    if ($remarks->timeopen > $timenow) {
        $remarksstatus = MOD_REMARKS_STATUS_PREOPEN;
    } else if ($remarks->timedue > $timenow) {
        $remarksstatus = MOD_REMARKS_STATUS_OPEN;
    } else if ($remarks->timeclose > $timenow) {
        $remarksstatus = MOD_REMARKS_STATUS_DUE;
    } else {
        $remarksstatus = MOD_REMARKS_STATUS_CLOSED;
    }

    $classlist = " submitterbox";
    if ($remarksstatus == MOD_REMARKS_STATUS_DUE && !$submitted) {
        $classlist .= " remarksoverdue";
    }
    if ($remarksstatus == MOD_REMARKS_STATUS_CLOSED && !$submitted) {
        $classlist .= " remarksmissed";
    }
    if ($submitted) {
        $classlist .= " remarkssubmitted";
    }
    if ($submitted) {
        $classlist .= "remarksuploaded";
    }
    echo $OUTPUT->box_start('generalbox boxaligncenter' . $classlist);
    remarks_print_submitterbox_title($remarks, $submitterid);

    foreach ($items as $item) {
        remarks_print_item($item, ($capsubmit && $cansubmit), ($capupload && $cansubmit), $remarksstatus, $cmcontext);
    }
    if ($remarksstatus == MOD_REMARKS_STATUS_CLOSED) {
        if (!$submitted) {
            if ($remarks->remarkstype == MOD_REMARKS_GROUPMODE) {
                print get_string('closedwithoutgroupsubmission', 'remarks');
            } else {
                print get_string('closedwithoutsubmission', 'remarks');
            }
        }
    } else if ($remarksstatus == MOD_REMARKS_STATUS_PREOPEN) {
        print get_string('preopen', 'remarks');
    } else if ($cansubmit) {
        // Assignment is either open, or due
        if ($capupload) {
            // User is allowed and able to upload a file
            remarks_print_blank_item($remarks, $submitterid, $remarksstatus, $capsubmit);
        } else {
            // Submitter is allowed to submit, but this user doesn't have the capability required to upload
            print_string('uploadnoperm', 'remarks');
        }
    }
    echo $OUTPUT->box_end();
}


function remarks_print_submitterbox_title($remarks, $submitterid) {
    print '<div class="submitterboxhead">';
    if ($remarks->remarkstype == MOD_REMARKS_GROUPMODE) {
        print '<div class="groupdetails">';
        // Print details about the group: groupname, member names.
        $group = groups_get_group($submitterid);
        print get_string('groupname', 'remarks') . ' ' . '<span class="groupname">' . $group->name . "<br /></span>\n";
        $members = groups_get_members($submitterid, 'u.*', 'lastname ASC');
        $membernames = array();
        foreach ($members as $member) {
            $membernames[] = fullname($member);
        }
        print '<span class="membernames">' . implode(', ', $membernames) . '</span>';
        print '</div>';
    }
    print '<div class="remarksduetime">' . get_string('remarksduetime', 'remarks') .' ' . userdate($remarks->timedue) . "<br /></div>\n";
    print '</div>';
}

// An item is an upload or submission for a particular submitter
// item should have the following properties:
// - id - the id of the upload record
// - fileid - the id of the upload file
// - uploaduserid
// - uploadedfor
// - grade
// optionally:
// - submisisonid
// - timesubmisison
// - submittedbyuserid
// - submissionfileid
// - optionally:
//   - released
//   - timereleased
//   - mark
//
function remarks_print_item($item, $cansubmit, $canupload, $remarksstatus, $context) {
    global $CFG;
    print '<div class="remarksitem">';
    $fs = get_file_storage();
    $uploadfile = $fs->get_file_by_id($item->fileid);
    $uploadfileurl = "{$CFG->wwwroot}/pluginfile.php/{$context->id}/mod_remarks/upload" .
            $uploadfile->get_filepath() . $uploadfile->get_itemid() . '/' .
            $uploadfile->get_filename();
    if (empty($item->submissionid)) {
        // Upload not yet submitted - link to uploaded file should contain info about upload time:
        $linkstr = get_string('responseuploaded', 'remarks') . ' ' .
                userdate($item->timeupload) . ".";
        $submissionlinks = '';
        // FUTURE: Display a form allowing user to submit uploaded file
    } else {
        // Link to uploaded file should contain info about submission time:
        $linkstr = get_string('responsesubmitted', 'remarks') . ' ' .
                userdate($item->timesubmission);
        $submissionfile = $fs->get_file_by_id($item->submissionfileid);
        $submissionlinks = plagiarism_get_links(array(
                'userid' => $item->submittedbyuserid,
                'file'=>$submissionfile,
                'cmid'=>$context->instanceid,
                ));
    }
    print '<a href="' .$uploadfileurl . '">' . $linkstr . '</a> ' . $submissionlinks . '<br />';
    if (!empty($item->released)) {
        $submissionfile = $fs->get_file_by_id($item->submissionfileid);
        $submissionfileurl = "{$CFG->wwwroot}/pluginfile.php/{$context->id}/mod_remarks/submission" .
                $submissionfile->get_filepath() . $submissionfile->get_itemid() . '/' .
                $submissionfile->get_filename();
        $releasedstr = get_string('submissionreleased', 'remarks');
        if (!empty($item->timereleased)) {
            $releasedstr .= ' ' . userdate($item->timereleased);
        }
        $releasedstr .= ".";
        print '<a href="' .$submissionfileurl . '">' . $releasedstr . '</a><br />';
        print get_string('grade') . ' ' . (($item->grade * $item->mark)/100) . "<br />\n";
    } else {
        print get_string('remarksnotreleased', 'remarks') . "<br />\n";
    }
    print '</div>';
}

// A blank item is an upload form that lets a user start a submission
function remarks_print_blank_item($remarks, $submitterid, $remarksstatus, $cansubmit) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/remarks/upload_form.php');
    $target = new moodle_url($CFG->wwwroot . '/mod/remarks/view.php?r=' .$remarks->id);
    // FUTURE: give form necessary info to know if save & submit is permitted, or just save, or both options
    $mform = new mod_remarks_upload_form('view.php?r=' . $remarks->id, array('remarks'=>$remarks->id, 'submitter'=>$submitterid, 'draftsok' => 0, 'uploadid' => 0));
    $mform->display();
}


function remarks_save_file($remarks, $remarkscontext, $course, $submitterid, $draftfileid, $filelength) {
    global $DB, $USER;
    // Check this user has upload capability in this assignment
    // and that they are authorised in relation to the submitterid (which can be an individual, or group)
    require_capability('mod/remarks:upload', $remarkscontext);

    if ($remarks->remarkstype == MOD_REMARKS_GROUPMODE) {
        // Check that the relevant submitterid is a group that can submit to this assignment
        $permittedgroups = groups_get_all_groups($course->id, $USER->id, $remarks->groupingid, 'g.id,g.id');
        if (!isset($permittedgroups[$submitterid])) {
            throw new moodle_exception('nopermission');
        }
        // Check that this user is a member of the group they are submitting for
        if(!groups_is_member($submitterid, $USER->id)) {
            throw new moodle_exception('nopermission');
        }
    }

    // Check that we have a file
    if (empty($filelength)) {
        throw new moodle_exception('emptyfilesubmitted');
    }

    // insert placeholder record into upload table
    $uploadrecord = new stdClass();
    $uploadrecord->remarksid = $remarks->id;
    $uploadrecord->fileid = 0;
    $uploadrecord->timeupload = time();
    $uploadrecord->uploaduserid = $USER->id;
    $uploadrecord->uploadedfor = $submitterid;
    $uploadrecord->id = $uploadid = $DB->insert_record('remarks_upload', $uploadrecord);

    // Save file to upload file area
    $fs = get_file_storage();
    $usercontext = get_context_instance(CONTEXT_USER, $USER->id);
    $moodledraftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftfileid, 'id', false);
    if(count($moodledraftfiles) != 1) {
        throw new moodle_exception('wrongnumberfiles');
    }
    $moodledraftfile = array_shift($moodledraftfiles);
    $upfileinfo = array('contextid'=>$remarkscontext->id, 'component'=> 'mod_remarks', 'filearea'=>'upload', 'itemid'=>$uploadid);
    $uploadfile = $fs->create_file_from_storedfile($upfileinfo, $moodledraftfile);
    $uploadrecord->fileid = $uploadfile->get_id();
    if ($uploadfile->get_filesize()) {
        // Delete file in moodle's "Draft files" area - different to remarks drafts
        $moodledraftfile->delete();
    }

    // Update upload record with file id
    $DB->update_record('remarks_upload', $uploadrecord);
    return $uploadrecord;
}

/**
 * Create an assignment submission from an upload record
 * @param object $remarks - the remarks assignment object
 * @param object $context - the context of the remarks assignment
 * @param object $uploadrecord - details about the file that was uploaded, which is now being submitted
 * @return bool
 */
function remarks_submit_file ($remarks, $context, $uploadrecord) {
    global $DB, $USER;

    // Check this user has submit capability for this submitter in this assignment
    require_capability('mod/remarks:submit', $context);
    // The file to be submitted will be submitted on behalf of the entity it was uploaded for:
    $submitterid = $uploadrecord->uploadedfor;

    if ($remarks->remarkstype == MOD_REMARKS_GROUPMODE) {
        // Check that the relevant submitterid is a group that can submit to this assignment
        $permittedgroups = groups_get_all_groups($remarks->course, $USER->id, $remarks->groupingid, 'g.id,g.id');
        if (!isset($permittedgroups[$submitterid])) {
            throw new moodle_exception('nopermission');
        }
        // Check that this user is a member of the group they are submitting for
        if(!groups_is_member($submitterid, $USER->id)) {
            throw new moodle_exception('nopermission');
        }
    }
    // insert record into submission table
    $submissionrecord = new stdClass();
    $submissionrecord->uploadid = $uploadrecord->id;
    $submissionrecord->timesubmission = time();
    $submissionrecord->submittedbyuserid = $USER->id;
    $submissionrecord->submittedforid = $submitterid;
    $submissionrecord->draftrfc = 0;
    $submissionrecord->resubmit = 0;
    $submissionrecord->mark = 0;
    $submissionrecord->version = 1;
    $submissionrecord->released = 0;
    $submissionrecord->originalityscore = 0;
    $submissionrecord->originalityknown = 0;
    $submissionrecord->fileid = 0;
    $submissionrecord->id = $DB->insert_record('remarks_submission', $submissionrecord);

    $fs = get_file_storage();
    $uploadfile = $fs->get_file_by_id($uploadrecord->fileid);
    if(empty($uploadfile)) {
        throw new moodle_exception('filemissing');
    }

    // Save file to submission file area
    $filename = $remarks->course . '-' . $remarks->id . '-' . $submitterid . '-' . $submissionrecord->id . '-1' . '.pdf';
    $subfileinfo = array('contextid'=>$context->id, 'component'=> 'mod_remarks', 'filearea'=>'submission', 'itemid'=>$submissionrecord->id, 'filename' => $filename);
    $submissionfile = $fs->create_file_from_storedfile($subfileinfo, $uploadfile);

    // Update submission record with file id
    $submissionrecord->fileid = $submissionfile->get_id();
    $DB->update_record('remarks_submission', $submissionrecord);

    // Trigger an event to let core know that a file was submitted
    // so they can be submitted for plagiarism detection, or other purposes.
    $eventdata = new stdClass();
    $eventdata->modulename = 'remarks';
    $eventdata->cmid = $context->instanceid;
    $eventdata->itemid = $submissionrecord->id;
    $eventdata->courseid = $remarks->course;
    $eventdata->userid = $USER->id;
    $eventdata->files = array($submissionfile);
    events_trigger('assessable_file_uploaded', $eventdata);

    return $submissionrecord;
}


function remarks_get_submission_users($remarkstype, $submittedforid) {
    global $DB;
    $fieldlist = 'u.id, u.firstname, u.lastname, u.email, u.username, u.idnumber';
    if ($remarkstype == MOD_REMARKS_INDIVIDUALMODE) {
        // individual mode assignment;
        $users = $DB->get_records_sql('SELECT ' . $fieldlist . ' FROM {user} u WHERE id = :userid', array('userid' => $submittedforid));
    } else {
        // group mode
        $users = groups_get_members($submittedforid, 'u.id, u.firstname, u.lastname, u.email, u.username, u.idnumber','u.id ASC');
    }
    if (empty($users)) {
        $users = array();
    }
    return $users;
}

function remarks_generate_releasename($submission, $userid) {
    global $DB;
    static $coursenames = array();
    static $remarksnames = array();
    $courseid = $submission->courseid;
    if (!isset($coursenames[$courseid])) {
        $course = $DB->get_record('course',array('id' => $courseid));
        $shortname = $course->shortname;
        $coursenames[$courseid] = preg_replace('/[\s_-]/','',$shortname);
    }
    $remarksid = $submission->remarksid;
    if (!isset($remarksnames[$remarksid])) {
        $remarks = $DB->get_record('remarks', array('id' => $remarksid));
        $remarksname = $remarks->name;
        $remarksnames[$remarksid] = preg_replace('/[\s_-]/','',$remarksname);
    }
    $coursename = $coursenames[$courseid];
    $remarksname = $remarksnames[$remarksid];

    $releasefilename = $coursename . '-' .
            $remarksname . '-' .
            $submission->id . '-' .
            $submission->version .
            '.pdf';
    return $releasefilename;
}

// Get a list of uploads and submitted uploads for a given submitter & remarksassignment
function remarks_get_submitter_items($submitterid, $remarksid) {
    global $DB;
    // Get a list of 'items' (uploads & submissions) this submitter is involved with.
    $sql = 'SELECT ru.*, rs.id as submissionid, rs.timesubmission, rs.submittedbyuserid, ' .
            ' rs.draftrfc, rs.resubmit, rs.mark, rs.version, rs.released, rs.timereleased, ' .
            ' rs.fileid as submissionfileid, ' .
            ' rs.originalityscore, rs.originalityknown,' .
            ' r.grade, r.course ' .
            'FROM {remarks_upload} ru' .
            ' LEFT JOIN {remarks_submission} rs on ru.id=rs.uploadid ' .
            ' INNER JOIN {remarks} r on r.id=ru.remarksid ' .
            'WHERE ru.uploadedfor = :submitterid' .
            ' AND ru.remarksid = :remarksid ' .
            'ORDER BY rs.timesubmission, ru.timeupload';
    $items = $DB->get_records_sql($sql, array('submitterid' => $submitterid, 'remarksid' => $remarksid));
    if (empty($items)) {
        $items = array();
    }
    return $items;
}

// Determine if submitter is still allowed to submit.
// There will be a submission limit - initially 1
function remarks_get_submission_status ($submitterid, $items) {
    $submitted = $uploaded = false;
    foreach ($items as $item) {
        // Iterate over to determine if this submitter has a) uploaded, b) submitted
        if (!empty($item->id)) {
            $uploaded = true;
        }
        if (!empty($item->submissionid)) {
            $submitted = true;
        }
    }
    $cansubmit = !$submitted; // FUTURE: track drafts & resubmit functionality.
    return array($uploaded, $submitted, $cansubmit);
}

// Function that returns the subject for release email
function remarks_get_release_subject($submissionid) {
    // FUTURE: make this text more dynamic - include the course name and assignment name
    return get_string('assignmentsubmissionreleased','remarks');
}

// Function that returns the text that should go in release email
function remarks_get_release_text($submissionid) {
    // FUTURE: make this text more dynamic - include the course name and assignment name
    return get_string('submissionreleasedtextemail','remarks');
}

