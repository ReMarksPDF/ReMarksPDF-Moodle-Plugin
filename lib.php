<?php
/**
 * Library of interface functions and constants for module remarks
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the remarks specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package   mod_remarks
 * @copyright 2011 Remarks Pty Limited
 */

defined('MOODLE_INTERNAL') || die();

/** example constant */
//define('NEWMODULE_ULTIMATE_ANSWER', 42);

/**
 * If you for some reason need to use global variables instead of constants, do not forget to make them
 * global as this file can be included inside a function scope. However, using the global variables
 * at the module level is not a recommended.
 */
//global $NEWMODULE_GLOBAL_VARIABLE;
//$NEWMODULE_QUESTION_OF = array('Life', 'Universe', 'Everything');

define('MOD_REMARKS_INDIVIDUALMODE', 0);
define('MOD_REMARKS_GROUPMODE', 1);

define('MOD_REMARKS_STATUS_PREOPEN', 1);
define('MOD_REMARKS_STATUS_OPEN', 2);
define('MOD_REMARKS_STATUS_DUE', 3);
define('MOD_REMARKS_STATUS_CLOSED', 4);


define('MOD_REMARKS_ERR_UNKNOWN', 2000);
define('MOD_REMARKS_ERR_PARAMINVALID', 2001);
define('MOD_REMARKS_ERR_ITEMMISSING', 2002);
define('MOD_REMARKS_ERR_NOTPERMITTED', 2003);
define('MOD_REMARKS_ERR_OLDVERSION', 2004);
define('MOD_REMARKS_ERR_BADVERSION', 2005);
define('MOD_REMARKS_ERR_SUBMISSIONRELEASED', 2006);
define('MOD_REMARKS_ERR_MOODLENOTPATCHED', 2007);
/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $remarks An object from the form in mod_form.php
 * @return int The id of the newly inserted remarks record
 */
function remarks_add_instance($remarks) {
    global $DB;

    $remarks->timecreated = time();
    $remarks->timemodified = time();

    $gradeparams = array('itemname'=>$remarks->name, 'grademax' => $remarks->grade, 'grademin' => 0);
    $remarks->id = $DB->insert_record('remarks', $remarks);
    grade_update('mod/remarks', $remarks->course, 'mod', 'remarks', $remarks->id, 0, null, $gradeparams);
    return $remarks->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $remarks An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function remarks_update_instance($remarks) {
    global $DB;

    $remarks->timemodified = time();
    $remarks->id = $remarks->instance;

    $gradeparams = array('itemname'=>$remarks->name, 'grademax' => $remarks->grade, 'grademin' => 0);
    grade_update('mod/remarks', $remarks->course, 'mod', 'remarks', $remarks->id, 0, null, $gradeparams);

    return $DB->update_record('remarks', $remarks);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function remarks_delete_instance($id) {
    global $DB;

    $sql = 'SELECT r.id, r.*, cm.id as cmid, c.id as contextid ' .
            'FROM {modules} m ' .
            ' INNER JOIN {course_modules} cm ON cm.module = m.id ' .
            ' INNER JOIN {remarks} r on r.id=cm.instance '.
            ' INNER JOIN {context} c on c.instanceid=cm.id '.
            'WHERE m.name = \'remarks\' ' .
            ' AND r.id = ? ' .
            ' AND c.contextlevel = ' . CONTEXT_MODULE;
    $remarks = $DB->get_record_sql($sql, array($id));
    if (! $remarks ) {
        return false;
    }

    // Delete any files related to the remarks activity
    $fs = get_file_storage();
    $fs->delete_area_files($remarks->contextid);

    // Delete any uploads associated with this remarks
    $uploads = $DB->get_records('remarks_upload', array('remarksid' => $remarks->id));
    if (!empty($uploads)) {
        foreach ($uploads as $upload) {
            // Delete any submisions related to the upload
            $DB->delete_records('remarks_submission', array('uploadid' => $upload->id));
        }
    }
    // Delete the upload records themselves:
    $DB->delete_records('remarks_upload', array('remarksid' => $remarks->id));

    // Delete any record we have of submitters being mapped to markers in this remarks
    $DB->delete_records('remarks_markermap', array('remarksid' => $remarks->id));

    // And finally delete the actual remarks record
    $DB->delete_records('remarks', array('id' => $remarks->id));

    return true;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 */
function remarks_user_outline($course, $user, $mod, $remarks) {
    $return = new stdClass;
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function remarks_user_complete($course, $user, $mod, $remarks) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in remarks activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function remarks_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function remarks_cron () {
    return true;
}

/**
 * Must return an array of users who are participants for a given instance
 * of remarks. Must include every user involved in the instance,
 * independient of his role (student, teacher, admin...). The returned
 * objects must contain at least id property.
 * See other modules as example.
 *
 * @param int $remarksid ID of an instance of this module
 * @return boolean|array false if no participants, array of objects otherwise
 */
function remarks_get_participants($remarksid) {
    return false;
}

/**
 * This function returns if a scale is being used by one remarks
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $remarksid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 */
function remarks_scale_used($remarksid, $scaleid) {
    global $DB;

    $return = false;

    //$rec = $DB->get_record("remarks", array("id" => "$remarksid", "scale" => "-$scaleid"));
    //
    //if (!empty($rec) && !empty($scaleid)) {
    //    $return = true;
    //}

    return $return;
}

/**
 * Checks if scale is being used by any instance of remarks.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any remarks
 */
function remarks_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('remarks', 'grade', -$scaleid)) {
        return true;
    } else {
        return false;
    }
}

/**
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function remarks_uninstall() {
    return true;
}

/**
 * Serves the modules files.
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function remarks_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB, $USER;
    require_once('locallib.php');

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_login($course, false, $cm);

    if (!$remarks = $DB->get_record('remarks', array('id'=>$cm->instance))) {
        return false;
    }

    $itemid = (int)array_shift($args);
    $marker = has_capability('mod/remarks:mark', $context);
    $admin = has_capability('mod/remarks:administer', $context);
    if ($filearea == 'sharefile') {
        if (empty($marker) && empty($admin)) {
            return false;
        }
    } else if ($filearea == 'upload') {
        // Check that USER is authorised to get remarks draft file
        $allowed=false;
        // The only people allowed to download the remarks draft are the uploaders,
        $upload = $DB->get_record('remarks_upload', array('id'=>$itemid));
        if ($remarks->remarkstype == MOD_REMARKS_INDIVIDUALMODE) {
            // This is an individual-mode assignment
            if ($upload->uploadedfor == $USER->id) {
                // The current user is the uploader
                $allowed = true;
            }
        } else {
            // This is a groupmode assignment
            $groupid = $upload->uploadedforid;
            if (groups_is_member($groupid, $USER->id)) {
                // The current user is in the assignment group
                $allowed = true;
            }
        }
        if (!$allowed) {
            return false;
        }
    } else if ($filearea == 'submission') {
        $allowed=false;
        if (!empty($admin)) {
            // the requesting user is an admin, they're allowed to download it.
            $allowed = true;
        } else if (!empty($marker) && remarks_is_allocated_marker($itemid, $USER->id)) {
            // The requesting user is a marker allocated to this submission, they're allowed to download it
            $allowed = true;
        } else {
            // The only other people allowed to download the file are the submitters,
            // And then only if the submission is 'released'
            $submission = $DB->get_record('remarks_submission', array('id'=>$itemid));
            if (empty($submission->released)) {
                return false;
            }
            if ($remarks->remarkstype == MOD_REMARKS_INDIVIDUALMODE) {
                // This is an individual-mode assignment
                if ($submission->submittedforid == $USER->id) {
                    // The current user is the submitter
                    $allowed = true;
                }
            } else {
                // This is a groupmode assignment
                $groupid = $submission->submittedforid;
                if (groups_is_member($groupid, $USER->id)) {
                    // The current user is in the submitting group
                    $allowed = true;
                }
            }
        }
        if (!$allowed) {
            return false;
        }
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_remarks/$filearea/$itemid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true);
}

// Get a detailed record about a single submission
// including cmid
function remarks_get_submission_record ($submissionid) {
    $records = remarks_get_submission_records(array($submissionid));
    $record = array_shift($records);
    return $record;
}

// Get a detailed record about a set of submissions
// including cmid
function remarks_get_submission_records ($submissionids) {
    global $DB;
    if (!is_array($submissionids) || empty($submissionids)) {
        return 0;
    }
    list($insql, $params) = $DB->get_in_or_equal($submissionids);
    $sql = 'SELECT rs.*, ru.fileid as uploadfileid, r.course as courseid, r.id as remarksid, r.remarkstype, r.grade, cm.id as cmid ' .
            'FROM {modules} m ' .
            ' INNER JOIN {course_modules} cm on cm.module=m.id' .
            ' INNER JOIN {remarks} r on r.id = cm.instance' .
            ' INNER JOIN {remarks_upload} ru on ru.remarksid = r.id' .
            ' INNER JOIN {remarks_submission} rs on rs.uploadid = ru.id ' .
            "WHERE m.name = 'remarks'" .
            " AND rs.id $insql";
    $submissions = $DB->get_records_sql($sql, $params);
    return $submissions;
}

function mod_remarks_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_GROUPMEMBERSONLY:        return false;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}
