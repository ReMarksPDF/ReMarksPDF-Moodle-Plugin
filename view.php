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
 * Prints a particular instance of remarks
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package   mod_remarks
 * @copyright 2010 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/// (Replace remarks with the name of your module and remove this line)

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->libdir . '/plagiarismlib.php');
require_once($CFG->dirroot . '/mod/remarks/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$r  = optional_param('r', 0, PARAM_INT);  // remarks instance ID - it should be named as the first character of the module

if ($id) {
    $cm         = get_coursemodule_from_id('remarks', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $remarks  = $DB->get_record('remarks', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($r) {
    $remarks  = $DB->get_record('remarks', array('id' => $r), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $remarks->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('remarks', $remarks->id, $course->id, false, MUST_EXIST);
} else {
    print_error('identifierrequired', 'remarks');
}

require_login($course, true, $cm);

add_to_log($course->id, 'remarks', 'view', "view.php?id=$cm->id", "", $cm->id);

/// Print the page header

$PAGE->set_url('/mod/remarks/view.php', array('id' => $cm->id));
$PAGE->set_title($remarks->name);
$PAGE->set_heading($course->shortname);
$PAGE->set_button(update_module_button($cm->id, $course->id, get_string('modulename', 'remarks')));

// other things you may want to set - remove if not needed
//$PAGE->set_cacheable(false);
//$PAGE->set_focuscontrol('some-html-id');

// Output starts here
echo $OUTPUT->header();
$context = get_context_instance(CONTEXT_MODULE, $cm->id);

// Determine if the user has special rights when calling functionality from ReMarksPDF:
$canadmin = has_capability('mod/remarks:administer', $context);
$canmark = has_capability('mod/remarks:mark', $context);

require_once($CFG->dirroot . '/mod/remarks/upload_form.php');
$mform = new mod_remarks_upload_form(null, array());
if ($fromform=$mform->get_data()) {

    $submitterid = $fromform->submitter; // The ID of the group or user this upload/submission is for
    $capupload = has_capability('mod/remarks:upload', $context, null, false); // Whether this _user_ has the upload capability
    $capsubmit = has_capability('mod/remarks:submit', $context, null, false); // Whether this _user_ has the submit capability
    $items = remarks_get_submitter_items($submitterid, $remarks->id);
    // cansubmit - whether this submitter can make (another) submission.
    list($uploaded, $submitted, $cansubmit) = remarks_get_submission_status($submitterid, $items);
    $draftfileid = $fromform->submission; // The id of the file in user drafts area.
    $filelength = strlen($mform->get_file_content('submission'));

    // FUTURE: determine if this is a request to upload 'save'?, submit?, upload&submit 'save & submit'?
    $uploadfilerequested = true; // determine more dynamically later
    $submitfilerequested = true; // determine more dynamically later

    if ($uploadfilerequested && $capupload && $cansubmit) {
        $uploadrecord = remarks_save_file($remarks, $context, $course, $submitterid, $draftfileid, $filelength);
    } else {
        // FUTURE: load detail from db about existing draftfileid in the event that this is not an upload request
    }
    if (!empty($uploadrecord) && $submitfilerequested && $capsubmit && $cansubmit) {
        $submissionrecord = remarks_submit_file($remarks, $context, $uploadrecord);
    }

}

echo $OUTPUT->box_start('generalbox boxaligncenter');
echo format_module_intro('assignment', $remarks, $cm->id);
echo $OUTPUT->box_end();
plagiarism_print_disclosure($cm->id);

echo $OUTPUT->box_start('generalbox boxaligncenter remarksdetail');
if (!empty($remarks->timeopen)) {
    print get_string('timeopen', 'remarks') .' ' . userdate($remarks->timeopen) . "<br />\n";
}
if (!empty($remarks->grade)) {
    print get_string('possiblegrade', 'remarks') .' ' . (int) $remarks->grade . "<br />\n";
}

echo $OUTPUT->box_end();


if($remarks->remarkstype == MOD_REMARKS_INDIVIDUALMODE) {
    remarks_print_submitterbox($remarks, $USER->id, $context);
} else {
    // Group mode:
    $groups = groups_get_all_groups($course->id, $USER->id, $remarks->groupingid, 'g.*');
    if (empty($groups)) {
        print_string('groupsmodenogroups','remarks');
    } else {
        foreach ($groups as $group) {
            remarks_print_submitterbox($remarks, $group->id, $context);
        }
    }
}

if ($canadmin) {
    echo $OUTPUT->box_start('generalbox remarksdesignerhelp');
    print get_string('designerrights','remarks');
    echo $OUTPUT->box_end();
} else if ($canmark) {
    echo $OUTPUT->box_start('generalbox remarksmarkerhelp');
    print get_string('markerrights','remarks');
    echo $OUTPUT->box_end();
}

// Finish the page
echo $OUTPUT->footer();
