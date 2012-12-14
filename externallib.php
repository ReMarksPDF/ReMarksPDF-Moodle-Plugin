<?php
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/mod/remarks/lib.php");
require_once("$CFG->dirroot/mod/remarks/locallib.php");

require_once("$CFG->dirroot/mod/remarks/exception.php");

// My understanding of the following line is that it is redundant, but harmless.
// Practical observation by at least one hosting company suggests it is
// essential for functionality in some environments.
// Please do not remove it without serious consideration. Peter Bulmer Catalyst IT 2012-08-01.
require_once("$CFG->libdir/zend/Zend/XmlRpc/Server.php");

Zend_XmlRpc_Server_Fault::attachFaultException('remarks_exception');
class mod_remarks_external extends external_api {
 
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_assignment_list_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course', VALUE_REQUIRED),
            )
        );
    }

    public static function get_assignment_list_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'remarks assignment id'),
                    'name' => new external_value(PARAM_TEXT, 'multilang compatible name, course unique'),
                    'groupmode' => new external_value(PARAM_INT, 'whether the assignment is a group assignment'),
                    'duetime' => new external_value(PARAM_INT, 'the general due time'),
                    'isdesigner' => new external_value(PARAM_INT, 'whether this user has designer capability in this assignment'),
                    'ismarker' => new external_value(PARAM_INT, 'whether this user has marker capability in this assignment'),
                )
            )
        );
    }

    /**
     * get_assignment_list (actual function)
     * @param integer $courseid id of course to list remarks assignments from
     * @return array of objects describing remarks assignments
     */
    public static function get_assignment_list($courseid) {
        global $CFG, $DB;
        try {
            $params = self::validate_parameters(self::get_assignment_list_parameters(), array('courseid'=>$courseid));
            if (empty($courseid)) {
                throw new remarks_parameter_exception('Invalid courseid', MOD_REMARKS_ERR_PARAMINVALID);
            }
        } catch (Exception $e) {
            throw new remarks_parameter_exception('Invalid or missing course id', MOD_REMARKS_ERR_PARAMINVALID);
        }

        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        if (empty($context)) {
            throw new remarks_parameter_exception('no such course', MOD_REMARKS_ERR_ITEMMISSING);
        }
        if (!has_capability('mod/remarks:list', $context)) {
            throw new remarks_permission_exception('not permitted to list', MOD_REMARKS_ERR_NOTPERMITTED);
        }
        $sql =  "SELECT r.id, r.name, r.remarkstype, r.timedue, cm.id as cmid " .
                "FROM {modules} m" .
                " INNER JOIN {course_modules} cm on cm.module=m.id" .
                " INNER JOIN {remarks} r on r.id=cm.instance " .
                "WHERE m.name = 'remarks'" .
                " AND cm.course = :courseid";
        $params = array('courseid' => $courseid);
        $assignments = $DB->get_records_sql($sql, $params);
        if(empty($assignments)) {
            return array();
        }
        foreach ($assignments as $i => $assignment) {
            $assignmentcontext = get_context_instance(CONTEXT_MODULE, $assignment->cmid);
            $returnassignment = array();
            $returnassignment['id'] = (int) $assignment->id;
            $returnassignment['name'] = $assignment->name;
            $returnassignment['groupmode'] = (int) $assignment->remarkstype;
            $returnassignment['duetime'] = (int) $assignment->timedue;
            $returnassignment['isdesigner'] = (int) has_capability('mod/remarks:administer', $context);
            $returnassignment['ismarker'] = (int) has_capability('mod/remarks:mark', $context);
            $returnassignments[] = $returnassignment;
        }
        return $returnassignments;
    }

    /**
     * Returns description of set_sharefile parameters
     * @return external_function_parameters
     */
    public static function set_sharefile_parameters() {
        return new external_function_parameters(
            array(
                'assignmentid' => new external_value(PARAM_INT, 'remarks assignment id'),
                'filecontent' => new external_value(PARAM_TEXT, 'file content')
            )
        );
    }

    /**
     * Returns description of set_sharefile returns
     * @return external_multiple_structure
     */
    public static function set_sharefile_returns() {
        return new external_value(PARAM_INT, 'bytes received');
    }
    /**
     * Uploading a sharefile for a remarks activity
     *
     * @param int $assignmentid - the id of the remarks activity
     * @param string $filecontent - file in question
     * @return int
     */
    public static function set_sharefile($assignmentid, $filecontent) {
        global $USER, $CFG, $DB;
        $fileinfo = self::validate_parameters(self::set_sharefile_parameters(), array('assignmentid' => $assignmentid, 'filecontent'=>$filecontent));

        if (!isset($fileinfo['filecontent'])) {
            throw new remarks_param_exception('no filecontent supplied', MOD_REMARKS_ERR_PARAMINVALID);
        }
        if (!isset($fileinfo['assignmentid'])) {
            throw new remarks_param_exception('assignment not specified', MOD_REMARKS_ERR_PARAMINVALID);
        }
        $sql = 'SELECT cm.id as cmid, cm.instance, cm.course FROM {modules} m ' .
                ' INNER JOIN {course_modules} cm on cm.module = m.id ' .
                'WHERE m.name like \'remarks\' AND cm.instance = :assignmentid';
        $assignments = $DB->get_records_sql($sql, array('assignmentid' => $assignmentid));
        if (empty($assignments)) {
            throw new remarks_param_exception('no such assignment', MOD_REMARKS_ERR_ITEMMISSING);
        }
        $assignment = array_shift($assignments);
        $context = get_context_instance(CONTEXT_MODULE, $assignment->cmid);
        // Anyone with the administer capability may set a new sharefile
        if (!has_capability('mod/remarks:administer', $context)) {
            throw new remarks_permission_exception('Admin priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
        }

        $fs = get_file_storage();
        // Collect together all the info about the sharefile
        $sfinfo = array(
                'contextid' => $context->id,
                'component' => 'mod_remarks',
                'filearea' => 'sharefile',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => "sharefile.rzip",
        );

        // check existing file
        $file = $fs->get_file($sfinfo['contextid'], $sfinfo['component'], $sfinfo['filearea'], $sfinfo['itemid'], $sfinfo['filepath'], $sfinfo['filename']);
        if (!empty($file)) {
            // File exists - delete it so that we can store the new file
            $file->delete();
        }

        $fs->create_file_from_string($sfinfo, base64_decode($fileinfo['filecontent']));
        $file = $fs->get_file($sfinfo['contextid'], $sfinfo['component'], $sfinfo['filearea'], $sfinfo['itemid'], $sfinfo['filepath'], $sfinfo['filename']);
        $filesize = $file->get_filesize();
        add_to_log($assignment->course, 'remarks', 'uploadsharefile', '', '');
        return $filesize;
    }

    /**
     * Returns description of get_sharefile parameters
     * @return external_function_parameters
     */
    public static function get_sharefile_parameters() {
        return new external_function_parameters(
            array(
                'assignmentid' => new external_value(PARAM_INT, 'id of remarks assignment'),
            )
        );
    }

    /**
     * Returns description of get_sharefile returns
     * @return external_multiple_structure
     */
    public static function get_sharefile_returns() {
        return new external_value(PARAM_TEXT, 'file content');
    }
    /**
     * Download a sharefile for a remarks activity
     *
     * @param int $assignmentid - the id of the remarks activity
     * @return string $filecontent - file in question
     */
    public static function get_sharefile($assignmentid) {
        global $USER, $CFG, $DB;
        $fileinfo = self::validate_parameters(self::get_sharefile_parameters(), array('assignmentid' => $assignmentid));
        if (!isset($fileinfo['assignmentid'])) {
            throw new remarks_parameter_exception('Assignment not specified', MOD_REMARKS_ERR_PARAMINVALID);
        }

        $sql = 'SELECT cm.id as cmid, cm.instance, cm.course FROM {modules} m ' .
                ' INNER JOIN {course_modules} cm on cm.module = m.id ' .
                'WHERE m.name like \'remarks\' AND cm.instance = :assignmentid';
        $assignments = $DB->get_records_sql($sql, array('assignmentid' => $assignmentid));
        if (empty($assignments)) {
            throw new remarks_parameter_exception('No such assignment', MOD_REMARKS_ERR_ITEMMISSING);
        }
        $assignment = array_shift($assignments);
        $context = get_context_instance(CONTEXT_MODULE, $assignment->cmid);
        // Anyone with the mark or administer capability may retreive the sharefile
        if (!(has_capability('mod/remarks:administer', $context) || has_capability('mod/remarks:mark', $context))){
            throw new remarks_permission_exception('Admin or marker priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
        }

        $fs = get_file_storage();
        // Collect together all the info about the sharefile
        $sfinfo = array(
                'contextid' => $context->id,
                'component' => 'mod_remarks',
                'filearea' => 'sharefile',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => "sharefile.rzip",
        );

        // check existing file
        $file = $fs->get_file($sfinfo['contextid'], $sfinfo['component'], $sfinfo['filearea'], $sfinfo['itemid'], $sfinfo['filepath'], $sfinfo['filename']);
        if (empty($file)) {
            // File does not exist
            // Not strictly an error - just return ''
            return '';
        }
        $filecontent = base64_encode($file->get_content());
        add_to_log($assignment->course, 'remarks', 'downloadsharefile', '', '', $assignment->cmid);

        return $filecontent;
    }


    /**
     * Returns description of get_submitter_list parameters
     * @return external_function_parameters
     */
    public static function get_submitter_list_parameters() {
        return new external_function_parameters(
            array(
                'assignmentid' => new external_value(PARAM_INT, 'id of remarks assignment'),
            )
        );
    }

    /**
     * Returns description of get_submitter_list returns
     * @return external_multiple_structure
     */
    public static function get_submitter_list_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'submitter id'),
                    'fullname' => new external_value(PARAM_TEXT, 'full name of submitter'),
                    'email' => new external_value(PARAM_TEXT, 'email address(es) of submitter'),
                    'duetime' => new external_value(PARAM_INT, 'due time for submitter'),
                )
            )
        );
    }
    /**
     * Obtain a list of submitters & potential submitters
     * for a groups-mode remarks assignment, submitters are groups
     * for an individual-mode remarks assignment, submitters are individual users
     *
     * @param int $assignmentid - the id of the remarks activity
     * @return string $filecontent - file in question
     */
    public static function get_submitter_list($assignmentid) {
        global $USER, $CFG, $DB;
        $fileinfo = self::validate_parameters(self::get_submitter_list_parameters(), array('assignmentid' => $assignmentid));
        if (!isset($fileinfo['assignmentid'])) {
            throw new invalid_parameter_exception('Assignment not specified', MOD_REMARKS_ERR_PARAMINVALID);
        }

        $sql = 'SELECT cm.id as cmid, cm.instance, cm.course, r.remarkstype, r.groupingid, r.timedue FROM {modules} m ' .
                ' INNER JOIN {course_modules} cm on cm.module = m.id ' .
                ' INNER JOIN {remarks} r on r.id = cm.instance ' .
                'WHERE m.name like \'remarks\' AND cm.instance = :assignmentid';
        $assignments = $DB->get_records_sql($sql, array('assignmentid' => $assignmentid));
        if (empty($assignments)) {
            throw new invalid_parameter_exception('No such assignment', MOD_REMAKRS_ERR_ITEMMISSING);
        }
        $assignment = array_shift($assignments);
        $context = get_context_instance(CONTEXT_MODULE, $assignment->cmid);
        // Anyone without the admin or mark capability doesn't have permission to be here
        $admin = has_capability('mod/remarks:administer', $context);
        $marker = has_capability('mod/remarks:mark', $context);
        if (!$marker && !$admin) {
            throw new remarks_permission_exception('Admin or marker priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
        }

        if (!$admin) {
            // Markers should only see items on the submission list that they will be marking
            // Obtain list of who marker will be marking
            $sql = 'SELECT markeeid, markeeid from {remarks_markermap} WHERE markerid = :markerid and remarksid = :assignmentid';
            $params = array('markerid' => $USER->id, 'assignmentid' => $assignment->instance);
            $markinglist = $DB->get_records_sql($sql, $params);
            if (empty($markinglist)) {
                $markinglist = array();
            }
        }
        $submitters = array();
        if ($assignment->remarkstype) {
            # Get a list of groups in specified grouping or course
            if (empty($assignment->groupingid)) {
                $assignment->groupingid = 0;
            }

            $groups = groups_get_all_groups($assignment->course, 0, $assignment->groupingid, $fields='g.id,g.name as fullname');
            foreach ($groups as $gid => $group) {
                if (!$admin && empty($markinglist[$gid])) {
                    // Remarkspdf user isn't an admin, and this submitter isn't on their marking list
                    continue;
                }
                $submitter = (array) $group;
                // Get a list of users in each group & set a comma separated list of emails
                $members = groups_get_members($gid, 'u.id,u.email', 'u.id ASC');
                $emails = array();
                if (!empty($members)) {
                    foreach ($members as $member) {
                        $emails[] = $member->email;
                    }
                }
                $submitter['email'] = implode(',', $emails);
                $submitter['duetime'] = $assignment->timedue; # FUTURE: - check for duetime extensions
                $submitters[] = $submitter;
            }
        } else {
            // Get a list of enrolled users
            $enrolledusers = get_enrolled_users($context, 'mod/remarks:submit', 0, 'u.id,u.firstname,u.lastname,u.email', 'u.id');
            foreach ($enrolledusers as $uid => $enrolleduser) {
                if (!$admin && empty($markinglist[$uid])) {
                    // Remarkspdf user isn't an admin, and this submitter isn't on their marking list
                    continue;
                }
                $submitter = array();
                $submitter['id'] = $enrolleduser->id;
                $submitter['fullname'] = fullname($enrolleduser);
                $submitter['email'] = $enrolleduser->email;
                $submitter['duetime'] = $assignment->timedue; # FUTURE: - check for duetime extensions
                $submitters[] = $submitter;
            }
        }

        $submittercount = count($submitters);
        add_to_log($assignment->course, 'remarks', 'get submitter list', '', '', $assignment->cmid);
        return $submitters;
    }

    /**
     * Returns description of get_groups parameters
     * @return external_function_parameters
     */
    public static function get_groups_parameters() {
        return new external_function_parameters(
            array(
                'assignmentid' => new external_value(PARAM_INT, 'id of remarks assignment'),
            )
        );
    }

    /**
     * Returns description of get_groups returns
     * @return external_multiple_structure
     */
    public static function get_groups_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'name' => new external_value(PARAM_TEXT, 'name of group'),
                    'members' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'id of submitter')
                    )
                )
            )
        );
    }
    /**
     * Obtain a list of groups of submitters
     * for a groups-mode remarks assignment, groups of submitters are moodle groupings
     * for an individual-mode remarks assignment,  groups are moodle groupings
     *
     * @param int $assignmentid - the id of the remarks activity
     * @return array groups of submitters
     */
    public static function get_groups($assignmentid) {
        global $USER, $CFG, $DB;
        $fileinfo = self::validate_parameters(self::get_groups_parameters(), array('assignmentid' => $assignmentid));
        if (!isset($fileinfo['assignmentid'])) {
            throw new invalid_parameter_exception('Assignment not specified', MOD_REMARKS_ERR_PARAMINVALID);
        }

        $sql = 'SELECT cm.id as cmid, cm.course, r.remarkstype FROM {modules} m ' .
                ' INNER JOIN {course_modules} cm on cm.module = m.id ' .
                ' INNER JOIN {remarks} r on r.id = cm.instance ' .
                'WHERE m.name like \'remarks\' AND cm.instance = :assignmentid';
        $assignments = $DB->get_records_sql($sql, array('assignmentid' => $assignmentid));
        if (empty($assignments)) {
            throw new remarks_parameter_exception('no such assignment', MOD_REMARKS_ERR_ITEMMISSING);
        }
        $assignment = array_shift($assignments);
        $context = get_context_instance(CONTEXT_MODULE, $assignment->cmid);
        // Anyone without the administer capability doesn't have permission to be here
        if(!has_capability('mod/remarks:administer', $context)) {
            throw new remarks_permission_exception('Admin priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
        }

        $returngroups = array();
        if ($assignment->remarkstype) {
            // This is a groups-mode assignment,
            // as such, 'groups' to remarks pdf are groups of groups,
            // groupings to Moodle.
            $groupings = groups_get_all_groupings($assignment->course);
            if (empty($groupings)) {
                $groupings = array();
            }
            foreach ($groupings as $groupingid => $grouping) {
                $membergroups = groups_get_all_groups($assignment->course, 0, $groupingid, $fields='g.id,g.name as fullname');
                $members = array_keys($membergroups);
                $returngroups[] = array(
                        'name' => $grouping->name,
                        'members' => $members,
                );
            }
        } else {
            $groups = groups_get_all_groups($assignment->course, 0, 0, $fields='g.id,g.name');
            if (empty($groups)) {
                $groups = array();
            }
            foreach ($groups as $gid => $group) {
                $members = groups_get_members($gid, 'u.id, u.id','u.id ASC');
                if (empty($members)) {
                    $members = array();
                }
                $members = array_keys($members);
                $returngroups[] = array(
                        'name' => $group->name,
                        'members' => $members,
                );
            }
        }

        $groupsreturned = count($returngroups);
        add_to_log($assignment->course, 'remarks', 'get groups', '', '', $assignment->cmid);
        return $returngroups;
    }

    /**
     * Returns description of get_markers parameters
     * @return external_function_parameters
     */
    public static function get_markers_parameters() {
        return new external_function_parameters(
            array(
                'assignmentid' => new external_value(PARAM_INT, 'id of remarks assignment'),
            )
        );
    }

    /**
     * Returns description of get_markers returns
     * @return external_multiple_structure
     */
    public static function get_markers_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'user id of marker'),
                    'firstname' => new external_value(PARAM_TEXT, 'first name of marker'),
                    'lastname' => new external_value(PARAM_TEXT, 'last name of marker'),
                    'fullname' => new external_value(PARAM_TEXT, 'full name of marker'),
                    'email' => new external_value(PARAM_TEXT, 'email address of marker'),
                )
            )
        );
    }
    /**
     * Obtain a list of markers
     *
     * @param int $assignmentid - the id of the remarks activity
     * @return array of markers
     */
    public static function get_markers($assignmentid) {
        global $USER, $CFG, $DB;
        $fileinfo = self::validate_parameters(self::get_markers_parameters(), array('assignmentid' => $assignmentid));
        if (!isset($fileinfo['assignmentid'])) {
            throw new invalid_parameter_exception('Assignment not specified', MOD_REMARKS_ERR_PARAMINVALID);
        }

        $sql = 'SELECT cm.id as cmid, cm.course FROM {modules} m ' .
                ' INNER JOIN {course_modules} cm on cm.module = m.id ' .
                'WHERE m.name like \'remarks\' AND cm.instance = :assignmentid';
        $assignments = $DB->get_records_sql($sql, array('assignmentid' => $assignmentid));
        if (empty($assignments)) {
            throw new remarks_parameter_exception('no such assignment', MOD_REMARKS_ERR_ITEMMISSING);
        }
        $assignment = array_shift($assignments);
        $context = get_context_instance(CONTEXT_MODULE, $assignment->cmid);
        // Anyone without the administer capability doesn't have permission to be here
        if(!has_capability('mod/remarks:administer', $context)) {
            throw new remarks_permission_exception('Admin priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
        }

        // Get array of users with mark capability in this assignment
        $markers = get_enrolled_users($context, 'mod/remarks:mark', 0, 'u.id,u.firstname,u.lastname,u.email', 'u.id');
        $returnmarkers = array();
        if (empty($markers)) {
            $markers = array();
        }
        foreach ($markers as $uid => $marker) {
            // manipulate array of objects into form ready for sending over web services
            $returnmarker = array();
            $returnmarker['id'] = $marker->id;
            $returnmarker['firstname'] = $marker->firstname;
            $returnmarker['lastname'] = $marker->lastname;
            $returnmarker['fullname'] = fullname($marker);
            $returnmarker['email'] = $marker->email;
            $returnmarkers[] = $returnmarker;
        }

        $markersreturned = count($returnmarkers);
        add_to_log($assignment->course, 'remarks', 'get markers', '', '', $assignment->cmid);
        return $returnmarkers;
    }

    /**
     * Returns description of get_designers parameters
     * @return external_function_parameters
     */
    public static function get_designers_parameters() {
        return new external_function_parameters(
            array(
                'assignmentid' => new external_value(PARAM_INT, 'id of remarks assignment'),
            )
        );
    }

    /**
     * Returns description of get_designers returns
     * @return external_multiple_structure
     */
    public static function get_designers_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'user id of designer'),
                    'firstname' => new external_value(PARAM_TEXT, 'first name of designer'),
                    'lastname' => new external_value(PARAM_TEXT, 'last name of designer'),
                    'fullname' => new external_value(PARAM_TEXT, 'full name of designer'),
                    'email' => new external_value(PARAM_TEXT, 'email address of designer'),
                )
            )
        );
    }
    /**
     * Obtain a list of designers
     *
     * @param int $assignmentid - the id of the remarks activity
     * @return array of designers
     */
    public static function get_designers($assignmentid) {
        global $USER, $CFG, $DB;
        $fileinfo = self::validate_parameters(self::get_designers_parameters(), array('assignmentid' => $assignmentid));
        if (!isset($fileinfo['assignmentid'])) {
            throw new invalid_parameter_exception('Assignment not specified', MOD_REMARKS_ERR_PARAMINVALID);
        }

        $sql = 'SELECT cm.id as cmid, cm.course FROM {modules} m ' .
                ' INNER JOIN {course_modules} cm on cm.module = m.id ' .
                'WHERE m.name like \'remarks\' AND cm.instance = :assignmentid';
        $assignments = $DB->get_records_sql($sql, array('assignmentid' => $assignmentid));
        if (empty($assignments)) {
            throw new remarks_parameter_exception('no such assignment', MOD_REMARKS_ERR_ITEMMISSING);
        }
        $assignment = array_shift($assignments);
        $context = get_context_instance(CONTEXT_MODULE, $assignment->cmid);
        // Anyone without the administer capability doesn't have permission to be here
        if(!has_capability('mod/remarks:administer', $context)) {
            throw new remarks_permission_exception('Admin priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
        }

        // Get array of users with mark capability in this assignment
        $designers = get_enrolled_users($context, 'mod/remarks:administer', 0, 'u.id,u.firstname,u.lastname,u.email', 'u.id');
        $returndesigners = array();
        if (empty($designers)) {
            $designers = array();
        }
        foreach ($designers as $uid => $designer) {
            // manipulate array of objects into form ready for sending over web services
            $returndesigner = array();
            $returndesigner['id'] = $designer->id;
            $returndesigner['firstname'] = $designer->firstname;
            $returndesigner['lastname'] = $designer->lastname;
            $returndesigner['fullname'] = fullname($designer);
            $returndesigner['email'] = $designer->email;
            $returndesigners[] = $returndesigner;
        }

        $designersreturned = count($returndesigners);
        add_to_log($assignment->course, 'remarks', 'get designers', '', '', $assignment->cmid);
        return $returndesigners;
    }

    /**
     * Returns description of get_submission_list parameters
     * @return external_function_parameters
     */
    public static function get_submission_list_parameters() {
        return new external_function_parameters(
            array(
                'assignmentid' => new external_value(PARAM_INT, 'id of remarks assignment'),
            )
        );
    }

    /**
     * Returns description of get_submission_list returns
     * @return external_multiple_structure
     */
    public static function get_submission_list_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'submissionid' => new external_value(PARAM_INT, 'id of submission'),
                    'version' => new external_value(PARAM_INT, 'version number of submission (>=1)'),
                    'mark' => new external_value(PARAM_FLOAT, 'current mark of submission, (0.000 - 100.000)'),
                    'submitterid' => new external_value(PARAM_INT, 'id of submitter'),
                    'submissiontime' => new external_value(PARAM_INT, 'time the submission was made (unix timestamp)'),
                    'duetime' => new external_value(PARAM_INT, 'time this submission was due (unix timestamp)'),
                    'originalityknown' => new external_value(PARAM_INT, 'if originality score of submission is known'),
                    'originalityscore' => new external_value(PARAM_INT, 'originality score of submission: 0-100'),
                    'released' => new external_value(PARAM_INT, 'Whether the submission has been released'),
                    'releasetime' => new external_value(PARAM_INT, 'When the submission was released'),
                )
            )
        );
    }
    /**
     * Obtain a list of submissions
     *
     * @param int $assignmentid - the id of the remarks activity
     * @return array of submission details
     */
    public static function get_submission_list($assignmentid) {
        global $USER, $CFG, $DB;
        $fileinfo = self::validate_parameters(self::get_submission_list_parameters(), array('assignmentid' => $assignmentid));
        if (!isset($fileinfo['assignmentid'])) {
            throw new invalid_parameter_exception('Assignment not specified', MOD_REMARKS_ERR_PARAMINVALID);
        }

        $sql = 'SELECT cm.id as cmid, cm.course, r.timedue, cm.instance FROM {modules} m ' .
                ' inner join {course_modules} cm on cm.module = m.id ' .
                ' inner join {remarks} r on r.id = cm.instance ' .
                'WHERE m.name like \'remarks\' AND cm.instance = :assignmentid';
        $assignments = $DB->get_records_sql($sql, array('assignmentid' => $assignmentid));
        if (empty($assignments)) {
            throw new remarks_parameter_exception('no such assignment', MOD_REMARKS_ERR_ITEMMISSING);
        }
        $assignment = array_shift($assignments);
        $context = get_context_instance(CONTEXT_MODULE, $assignment->cmid);
        // Anyone without the admin or mark capability doesn't have permission to be here
        $admin = has_capability('mod/remarks:administer', $context);
        $marker = has_capability('mod/remarks:mark', $context);
        if (!$marker && !$admin) {
            throw new remarks_permission_exception('Admin or marker priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
        }

        if (!$admin) {
            // Markers should only see items on the submission list that they will be marking
            // Obtain list of who marker will be marking
            $sql = 'SELECT markeeid, markeeid from {remarks_markermap} WHERE markerid = :markerid and remarksid = :assignmentid';
            $params = array('markerid' => $USER->id, 'assignmentid' => $assignment->instance);
            $markinglist = $DB->get_records_sql($sql, $params);
            if (empty($markinglist)) {
                $markinglist = array();
            }
        }
        $sql = "SELECT s.id, s.version, s.mark, s.originalityscore, s.originalityknown, " .
                " s.submittedforid, s.timesubmission, s.released, s.timereleased," .
                " s.fileid, s.submittedbyuserid" .
                " FROM {remarks_submission} s" .
                " INNER JOIN {remarks_upload} u on u.id=s.uploadid " .
                " WHERE u.remarksid = :remarksid" .
                " ORDER BY s.id";
        $submissions = $DB->get_records_sql($sql, array('remarksid' => $assignmentid));
        // Form a detailed list of submissions that the current user is allowed to see:
        $returnsubmissions = array();
        $fs = get_file_storage();
        //Part of the following section depends on either having a moodle >= 2.2, or
        //a specially patched 2.x
        $patchedmoodle = function_exists('plagiarism_get_file_results');
        foreach ($submissions as $sid => $submission) {
            if (!$admin && empty($markinglist[$submission->submittedforid])) {
                // The calling user is a marker (ie not a full admin) and
                // The user or group this submission is for isn't assigned to them
                // Don't include it on the list of submissions to return
                continue;
            }
            $returnsubmission=array();
            $returnsubmission['submissionid'] = (int)$submission->id;
            $returnsubmission['version'] = (int) $submission->version;
            $returnsubmission['mark'] = (float) $submission->mark;
            $returnsubmission['submitterid'] = (int)$submission->submittedforid;
            $returnsubmission['submissiontime'] = (int)$submission->timesubmission;
            $returnsubmission['duetime'] = (int) $assignment->timedue; // Future: check for extensions
            $returnsubmission['originalityknown'] = (int) $submission->originalityknown;
            $returnsubmission['originalityscore'] = (float) $submission->originalityscore;
            $returnsubmission['released'] = (int) $submission->released;
            $returnsubmission['releasetime'] = (int) $submission->timereleased;

            if (!empty($CFG->enableplagiarism)
                    && empty($returnsubmission['originalityknown'])
                    && $patchedmoodle) {
                require_once("$CFG->dirroot/lib/plagiarismlib.php");
                // The originality score isn't known for this submisison,
                // even though plagiarism checking is enabled.
                // Check to see if more info is availble:
                $submissionfile = $fs->get_file_by_id($submission->fileid);
                $submissionresults = plagiarism_get_file_results($assignment->cmid,
                        $submission->submittedbyuserid,
                        $submissionfile);
                // use the result from the first enabled plagiarism plugin
                $submissionresult = array_shift($submissionresults);
                if (!empty($submissionresult['analyzed'])) {
                    $updatesubmission = new stdClass();
                    $updatesubmission->id = $submission->id;
                    $returnsubmission['originalityknown'] = 1;
                    $updatesubmission->originalityknown = 1;
                    $returnsubmission['originalityscore'] = $submissionresult['score'];
                    $updatesubmission->originalityscore = $submissionresult['score'];
                    $DB->update_record('remarks_submission', $updatesubmission);
                }
            }

            $returnsubmissions[] = $returnsubmission;
        }

        $submissioncount = count($returnsubmissions);
        add_to_log($assignment->course, 'remarks', 'get submission list', '', '', $assignment->cmid);
        return $returnsubmissions;
    }

    /**
     * Returns description of get_marker_mappings parameters
     */
    public static function get_marker_mappings_parameters() {
        return new external_function_parameters(
            array(
                'assignmentid' => new external_value(PARAM_INT, 'id of remarks assignment'),
            )
        );
    }

    /**
     * Returns description of get_marker_mappings returns
     */
    public static function get_marker_mappings_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'markerid' => new external_value(PARAM_INT, 'userid of marker'),
                    'markees' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'id of assigned markee (user/group)')
                    )
                )
            )
        );
    }
    /**
     * Obtain a list of objects describing a marker, and a their list of assigned markees
     * @param int $assignmentid - the id of the remarks activity
     */
    public static function get_marker_mappings($assignmentid) {
        global $USER, $CFG, $DB;
        $fileinfo = self::validate_parameters(self::get_marker_mappings_parameters(), array('assignmentid' => $assignmentid));
        if (!isset($fileinfo['assignmentid'])) {
            throw new invalid_parameter_exception('Assignment not specified', MOD_REMARKS_ERR_PARAMINVALID);
        }

        $sql = 'SELECT cm.id as cmid, cm.course, cm.instance FROM {modules} m ' .
                ' INNER JOIN {course_modules} cm on cm.module = m.id ' .
                'WHERE m.name like \'remarks\' AND cm.instance = :assignmentid';
        $assignments = $DB->get_records_sql($sql, array('assignmentid' => $assignmentid));
        if (empty($assignments)) {
            throw new remarks_parameter_exception('no such assignment', MOD_REMARKS_ERR_ITEMMISSING);
        }
        $assignment = array_shift($assignments);
        $context = get_context_instance(CONTEXT_MODULE, $assignment->cmid);
        // Only admins are allowed to examine the mapping list
        if (!has_capability('mod/remarks:administer', $context)) {
            throw new remarks_permission_exception('Admin priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
        }

        $sql = 'SELECT markeeid, markeeid from {remarks_markermap} WHERE markerid = :markerid and remarksid = :assignmentid';
        $markers = get_enrolled_users($context, 'mod/remarks:mark', 0, 'u.id,u.firstname,u.lastname,u.email', 'u.id');
        if (!is_array($markers)) {
            $markers = array();
        }

        $returnmaps = array();
        $markercount = count($markers);
        $markeecount = 0;
        foreach (array_keys($markers) as $markerid) {
            // Obtain list of markees for this markerid
            $params = array('markerid' => $markerid, 'assignmentid' => $assignment->instance);
            $markinglist = $DB->get_records_sql($sql, $params);
            if (empty($markinglist)) {
                $markinglist = array();
            }
            $markeelist = array_keys($markinglist);
            $markeecount += count($markeelist);
            $returnmaps[] = array(
                    'markerid' => $markerid,
                    'markees' => $markeelist,
            );
        }

        add_to_log($assignment->course, 'remarks', 'get marker mappings', '', '', $assignment->cmid);
        return $returnmaps;
    }
    /**
     * Returns description of set_marker_mappings parameters
     */
    public static function set_marker_mappings_parameters() {
        return new external_function_parameters(
            array(
                'assignmentid' => new external_value(PARAM_INT, 'remarks assignment id'),
                'mappings' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'markerid' => new external_value(PARAM_INT, 'userid of marker'),
                            'markeeid' => new external_value(PARAM_INT, 'markee id (userid or groupid)'),
                        )
                    )
                )
            )
        );
    }

    /**
     * Returns description of set_marker_mappings returns
     */
    public static function set_marker_mappings_returns() {
        return new external_single_structure(
            array(
                'new' => new external_value(PARAM_INT, 'number of mappings resulting in new mapping being recorded'),
                'nochange' => new external_value(PARAM_INT, 'number of mappings resulting in no change'),
                'deleted' => new external_value(PARAM_INT, 'number of mappings records deleted'),
                'errors' => new external_value(PARAM_INT, 'number of insertion errors'),
                'invalid' => new external_value(PARAM_INT, 'number of invalid mappings not processed'),
            )
        );
    }
    /**
     * Update moodle records to reflect mappings advised by remarkspdf
     * @param int $assignmentid - the id of the remarks activity
     * @param array $mappings - array of marker & markee ids
     */
    public static function set_marker_mappings($assignmentid, $mappings) {
        global $USER, $CFG, $DB;
        $fileinfo = self::validate_parameters(self::set_marker_mappings_parameters(), array('assignmentid' => $assignmentid, 'mappings' => $mappings));
        if (!isset($fileinfo['assignmentid'])) {
            throw new invalid_parameter_exception('Assignment not specified', MOD_REMARKS_ERR_PARAMINVALID);
        }
        $assignmentid = $fileinfo['assignmentid'];
        $mappings = $fileinfo['mappings'];
        $result = array('new' => 0, 'nochange' => 0, 'deleted' => 0, 'errors' => 0, 'invalid' => 0);

        $sql = 'SELECT cm.id as cmid, cm.course, cm.instance, r.remarkstype FROM {modules} m ' .
                ' INNER JOIN {course_modules} cm on cm.module = m.id ' .
                ' INNER JOIN {remarks} r on r.id = cm.instance ' .
                'WHERE m.name like \'remarks\' AND cm.instance = :assignmentid';
        $assignments = $DB->get_records_sql($sql, array('assignmentid' => $assignmentid));
        if (empty($assignments)) {
            throw new remarks_parameter_exception('no such assignment', MOD_REMARKS_ERR_ITEMMISSING);
        }
        $assignment = array_shift($assignments);
        $context = get_context_instance(CONTEXT_MODULE, $assignment->cmid);
        // Only admins are allowed to set the mapping list
        if (!has_capability('mod/remarks:administer', $context)) {
            throw new remarks_permission_exception('Admin priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
        }
        // Get a db list of marker_mappings
        $keystr = $DB->sql_concat('markerid', "'_'", 'markeeid');
        $sql = 'SELECT ' . $keystr . ' as mappingkey, markerid, markeeid from {remarks_markermap} WHERE remarksid = :assignmentid';
        $dbrecords = $DB->get_records_sql($sql, array('assignmentid' => $assignmentid));
        if (empty($dbrecords)) {
            $dbrecords = array();
        }
        // Iterate over supplied mappings, unsetting db records if they exist, inserting them if they don't
        $done = array(); // All mappings we've already reviewed
        foreach ($mappings as $mapping) {
            $markeeok = remarks_is_valid_submitter($context, $assignment, $mapping['markeeid']);
            $markerok = remarks_is_valid_marker($context, $mapping['markerid']);
            $validmapping = $markerok && $markeeok;
            $mappingkey = $mapping['markerid'] . '_' . $mapping['markeeid'];
            if (!$validmapping || isset($done[$mappingkey])) {
                // Somethign wrong with supplied mapping - don't process
                $result['invalid']++;
                continue;
            }
            if (isset($dbrecords[$mappingkey])) {
                // This mapping exists in db & detail supplied by remarkspdf
                // mark it as done, so we don't delete it later
                $done[$mappingkey] = 1;
                $result['nochange']++;
            } else {
                // This doesn't exist in the db - add it
                $newmapping = new stdClass();
                $newmapping->markerid = $mapping['markerid'];
                $newmapping->markeeid = $mapping['markeeid'];
                $newmapping->remarksid = $assignmentid;
                $newmapping->id = $DB->insert_record('remarks_markermap', $newmapping);
                if ($newmapping->id) {
                    $done[$mappingkey] = 1;
                    $result['new']++;
                } else {
                    $result['errors']++;
                }
            }
        }
        // Delete anything db records, and not otherwise reviewed
        foreach ($dbrecords as $mappingkey => $dbrecord) {
            if (isset($done[$mappingkey])) {
                continue; //This record was also in the list sent by remarks - leave it be.
            }
            // This record is in the db, but wasn't listed by remarkspdf,
            // or is invalid in some way (invalid marker or markee)
            $DB->delete_records('remarks_markermap',
                    array(
                            'remarksid' => $assignmentid,
                            'markerid' => $dbrecord->markerid,
                            'markeeid' => $dbrecord->markeeid,
                    )
            );
            $result['deleted']++;
        }
        $resultstring =
                $result['new'] . " new mappings inserted. " .
                $result['deleted'] . " mappings deleted. " .
                $result['nochange'] . " unchanged. " .
                $result['invalid'] . ' invalid. ' .
                $result['errors'] . 'errors.';
        add_to_log($assignment->course, 'remarks', 'set marker mappings', '', '', $assignment->cmid);
        return $result;
    }

    /**
     * Returns description of get_submission parameters
     * @return external_function_parameters
     */
    public static function get_submission_parameters() {
        return new external_function_parameters(
            array(
                'submissionid' => new external_value(PARAM_INT, 'id of remarks submission'),
            )
        );
    }

    /**
     * Returns description of get_submission returns
     */
    public static function get_submission_returns() {
        return new external_single_structure(
            array(
                'filecontent' => new external_value(PARAM_TEXT, 'file content'),
                'filename' => new external_value(PARAM_TEXT, 'name of file'),
                'version' => new external_value(PARAM_INT, 'version number of current file'),
                'mark' => new external_value(PARAM_FLOAT, 'current mark of submission'),
            )
        );
    }
    /**
     * Download a submitted file
     *
     * @param int $submissionid - the id of the submission
     * @return string $filecontent - file in question
     */
    public static function get_submission($submissionid) {
        global $USER, $CFG, $DB;
        $fileinfo = self::validate_parameters(self::get_submission_parameters(), array('submissionid' => $submissionid));
        $submissionid = $fileinfo['submissionid'];
        if (empty($submissionid)) {
            throw new remarks_parameter_exception('Submissionid not specified', MOD_REMARKS_ERR_PARAMINVALID);
        }
        $submission = remarks_get_submission_record($submissionid);
        if (empty($submission)) {
            throw new remarks_parameter_exception('no such submission', MOD_REMARKS_ERR_ITEMMISSING);
        }
        $context = get_context_instance(CONTEXT_MODULE, $submission->cmid);

        $admin = has_capability('mod/remarks:administer', $context);
        $marker = has_capability('mod/remarks:mark', $context);
        if (!$marker && !$admin) {
            // User is neither a marker, nor an admin - bail.
            throw new remarks_permission_exception('Admin or marker priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
        }
        if (!$admin && !remarks_is_allocated_marker($USER->id, $submissionid)) {
            // User isn't an admin, and isn't a marker allocated to this submisssion - bail.
            throw new remarks_permission_exception('Marker not allocated to specified submission', MOD_REMARKS_ERR_NOTPERMITTED);
        }

        // User is authorized to view this submission
        $fs = get_file_storage();
        $submissionfile = $fs->get_file_by_id($submission->fileid);
        $result = array();
        $result['filecontent'] = (string) base64_encode($submissionfile->get_content());
        $result['filename'] = (string) $submissionfile->get_filename();
        $result['version'] = (int) $submission->version;
        $result['mark'] = (float) $submission->mark;

        add_to_log($submission->courseid, 'remarks', "download submission", '', "$submissionid", $submission->cmid, $USER->id);
        return $result;
    }
    /**
     * Returns description of set_submission parameters
     * @return external_function_parameters
     */
    public static function set_submission_parameters() {
        return new external_function_parameters(
            array(
                'submissionid' => new external_value(PARAM_INT, 'id of remarks submission'),
                'filecontent' => new external_value(PARAM_TEXT, 'file content'),
                'mark' => new external_value(PARAM_FLOAT, 'new mark for submission'),
                'baseversion' => new external_value(PARAM_INT, 'version number this file is based off'),
            )
        );
    }

    /**
     * Returns description of set_submission returns
     */
    public static function set_submission_returns() {
        return new external_value(PARAM_INT, 'bytes received');
    }
    /**
     * Upload a marked file
     */
    public static function set_submission($submissionid, $filecontent, $mark, $baseversion) {
        global $USER, $CFG, $DB;
        $fileinfo = self::validate_parameters(self::set_submission_parameters(), array('submissionid' => $submissionid, 'filecontent' => $filecontent, 'mark' => $mark, 'baseversion' => $baseversion));
        $submissionid = $fileinfo['submissionid'];
        $filecontent = $fileinfo['filecontent'];
        $mark = $fileinfo['mark'];
        $baseversion = $fileinfo['baseversion'];
        if (empty($submissionid)) {
            throw new remarks_parameter_exception('Submissionid not specified', MOD_REMARKS_ERR_PARAMINVALID);
        }
        $submission = remarks_get_submission_record($submissionid);
        if (empty($submission)) {
            throw new remarks_parameter_exception('no such submission', MOD_REMARKS_ERR_ITEMMISSING);
        }
        $context = get_context_instance(CONTEXT_MODULE, $submission->cmid);

        $admin = has_capability('mod/remarks:administer', $context);
        $marker = has_capability('mod/remarks:mark', $context);
        if (!$marker && !$admin) {
            // User is neither a marker, nor an admin - bail.
            throw new remarks_permission_exception('Admin or marker priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
        }
        if (!$admin && !remarks_is_allocated_marker($USER->id, $submissionid)) {
            // User isn't an admin, and isn't a marker allocated to this submisssion - bail.
            throw new remarks_permission_exception('Marker not allocated to specified submission', MOD_REMARKS_ERR_NOTPERMITTED);
        }
        if (!empty($submission->released)) {
            throw new remarks_access_exception('submission already released', MOD_REMARKS_ERR_SUBMISSIONRELEASED);
        }

        if ($submission->version > $baseversion) {
            // Version of file we have is newer - client to merge changes & resubmit
            throw new remarks_versioning_exception('old base version', MOD_REMARKS_ERR_OLDVERSION);
        }
        if ($submission->version < $baseversion) {
            // The client thinks they have a newer base version than we have. Error.
            throw new remarks_versioning_exception("bad base version ($baseversion > current version " . $submission->version . " )", MOD_REMARKS_ERR_BADVERSION);
        }

        // User is authorized to modify this submission.
        $fs = get_file_storage();
        $submissionfile = $fs->get_file_by_id($submission->fileid);
        $newfilename = $submission->courseid . '-' . $submission->remarksid . '-' . $submission->submittedforid . '-' .
                $submission->id . '-' . ($submission->version + 1). '.pdf';

        $fileinfo = array( 'contextid' => $context->id, 'component' => 'mod_remarks',
                'filearea' => 'submission', 'itemid' => $submission->id,
                'filepath' => '/', 'filename' => $newfilename,
        );

        $newsubmissionfile = $fs->create_file_from_string($fileinfo, base64_decode($filecontent));
        $submission->fileid = $newsubmissionfile->get_id();
        $submission->mark = $mark;
        $submission->version++;
        $DB->update_record('remarks_submission', $submission);

        $newfilesize = $newsubmissionfile->get_filesize();
        if (empty($newfilesize)) {
            throw new remarks_unknown_exception('Problem storing file', MOD_REMARKS_ERR_UNKNOWN);
        }
        $submissionfile->delete();

        add_to_log($submission->courseid, 'remarks', "set submission", '', "$submissionid", $submission->cmid);
        return $newfilesize;
    }

    /**
     * Returns description of release_submissions parameters
     */
    public static function release_submissions_parameters() {
        return new external_function_parameters(
            array(
                'submissionids' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'id of submission to release')
                )
            )
        );
    }

    /**
     * Returns description of release_submission returns
     */
    public static function release_submissions_returns() {
        return new external_single_structure(
            array(
                'newlyreleased' => new external_value(PARAM_INT, 'number of submissions released as a result of this call'),
                'alreadyreleased' => new external_value(PARAM_INT, 'number of submissions released prior to this call'),
                'invalid' => new external_value(PARAM_INT, 'number of invalid submission ids not processed'),
                'errors' => new external_value(PARAM_INT, 'number of errors during processing'),
            )
        );
    }
    /**
     * release submissions:
     * update record to indicate that submission has been released.
     * copy file to user's file area for relevant users.
     * enter value in gradebook for relevant users.
     */
    public static function release_submissions($submissionids) {
        global $USER, $CFG, $DB;
        require_once($CFG->libdir.'/gradelib.php');
        try {
            $params = self::validate_parameters(self::release_submissions_parameters(), array('submissionids' => $submissionids));
            if (!isset($params['submissionids'])) {
                throw new remarks_parameter_exception('submissionids not specified', MOD_REMARKS_ERR_PARAMINVALID);
            }
        } catch (Exception $e) {
            throw new remarks_parameter_exception('Invalid or missing submissionids', MOD_REMARKS_ERR_PARAMINVALID);
        }
        $releasesubmissionids = $params['submissionids'];
        $submissionrecords = remarks_get_submission_records($releasesubmissionids);
        if (empty($submissionrecords)) {
            $submissionrecords = array();
        }

        //First make sure USER has permission to administer all the relevant remarks assignments
        foreach ($submissionrecords as $submissionrecord) {
            $cmid = $submissionrecord->cmid;
            if (empty($contexts[$cmid])) {
                $contexts[$cmid] = get_context_instance(CONTEXT_MODULE, $cmid);
            }
            $context = $contexts[$cmid];
            if (!has_capability('mod/remarks:administer', $context)) {
                throw new remarks_permission_exception('Admin priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
            }
        }

        // prepare array to store result details
        $results = array('newlyreleased' => 0, 'alreadyreleased' => 0, 'invalid' => 0, 'errors' => 0);
        foreach($releasesubmissionids as $submissionid) {
            if (empty($submissionrecords[$submissionid])) {
                // No record of that submission id in db
                $results['invalid']++;
                continue;
            }
            $submissionrecord = $submissionrecords[$submissionid];
            if (!empty($submissionrecord->released)) {
                // This submission has already been released - nothing to do.
                $results['alreadyreleased']++;
                continue;
            }

            // Update db submission record to detail the fact that it has been released.
            $submissionrecord->released = 1;
            $submissionrecord->timereleased = time();
            try {
                $DB->update_record('remarks_submission', $submissionrecord);
            } catch (Exception $e) {
                // Just record that the update failed
                // and skip onto the next submission release
                $result['errors']++;
                continue;
            }
            // identify relevant users for this submission
            $submissionusers = remarks_get_submission_users($submissionrecord->remarkstype, $submissionrecord->submittedforid);
            // copy file to users' file areas
            $fs = get_file_storage();
            $submissionfile = $fs->get_file_by_id($submissionrecord->fileid);
            // foreach submission user, copy the file into that user's private file storage area.
            foreach ($submissionusers as $uid => $submissionuser) {
                if (empty($usercontexts[$uid])) {
                    $usercontexts[$uid] = get_context_instance(CONTEXT_USER, $uid);
                }
                $usercontext = $usercontexts[$uid];
                // Save file to user file area
                $filename = remarks_generate_releasename($submissionrecord, $uid);
                $releasefileinfo = array('contextid'=>$usercontext->id, 'component'=> 'user', 'filearea'=>'private', 'itemid'=> 0, 'filepath' => '/', 'filename' => $filename);
                $releasefile = $fs->create_file_from_storedfile($releasefileinfo, $submissionfile);
            }
            // Set grades in gradebook for this submission.
            // Create an array of grades to record.
            $grades = array();
            // Submissions are graded between 0.000 - 100.000,
            // A mark of any value can be set for an assignment.
            // The raw grade for the gradebook is the product of the two, divided by 100.
            $rawgrade = (($submissionrecord->mark * $submissionrecord->grade)/100);
            if ($rawgrade < 0) {
                $rawgrade = 0;
            }
            foreach ($submissionusers as $uid => $submissionuser) {
                $existinggrades = grade_get_grades($submissionrecord->courseid, 'mod', 'remarks', $submissionrecord->remarksid, $uid);
                if (empty($existinggrades->items[0]->grades)) {
                    // No existing grade - this new one must be the best.
                    $grades[$uid] = array('userid' => $uid, 'rawgrade' => $rawgrade);
                    continue;
                } else {
                    $existinggrade = array_shift($existinggrades->items[0]->grades);
                    $existinggradeval = $existinggrade->grade;
                }

                if ($existinggradeval >= $rawgrade) {
                    //Existing grade is better - don't update
                    continue;
                }
                // This grade must be better - update grade
                $grades[$uid] = array('userid' => $uid, 'rawgrade' => $rawgrade);
            }
            grade_update('mod/remarks', $submissionrecord->courseid, 'mod', 'remarks', $submissionrecord->remarksid, 0, $grades);

            $emailsubject = remarks_get_release_subject($submissionid);
            $messagetext = remarks_get_release_text($submissionid);
            foreach ($submissionusers as $uid => $submissionuser) {
                email_to_user($submissionuser, get_string('noreplyname'), $emailsubject, $messagetext, '', '', '', false, '', '', $wordwrapwidth=79);
            }
            $results['newlyreleased']++;
        }
        return $results;
    }

    /**
     * Returns description of get_originality_report_link parameters
     * @return external_function_parameters
     */
    public static function get_originality_report_link_parameters() {
        return new external_function_parameters(
            array(
                'submissionid' => new external_value(PARAM_INT, 'id of remarks submission'),
            )
        );
    }

    /**
     * Returns description of get_originality_report_link returns
     */
    public static function get_originality_report_link_returns() {
        return new external_value(PARAM_TEXT, 'link');
    }
    /**
     * Get link to originality report
     *
     * @param int $submissionid - the id of the submission
     * @return string link - link in question
     */
    public static function get_originality_report_link($submissionid) {
        global $USER, $CFG, $DB, $COURSE;
        require_once("$CFG->dirroot/lib/plagiarismlib.php");

        $fileinfo = self::validate_parameters(self::get_originality_report_link_parameters(), array('submissionid' => $submissionid));
        $submissionid = $fileinfo['submissionid'];
        if (empty($submissionid)) {
            throw new remarks_parameter_exception('Submissionid not specified', MOD_REMARKS_ERR_PARAMINVALID);
        }
        $submission = remarks_get_submission_record($submissionid);
        if (empty($submission)) {
            throw new remarks_parameter_exception('no such submission', MOD_REMARKS_ERR_ITEMMISSING);
        }
        $context = get_context_instance(CONTEXT_MODULE, $submission->cmid);
        $cm = $DB->get_record('course_modules', array('id' => $submission->cmid));
        $COURSE = $DB->get_record('course',array('id' => $cm->course));

        $admin = has_capability('mod/remarks:administer', $context);
        $marker = has_capability('mod/remarks:mark', $context);
        if (!$marker && !$admin) {
            // User is neither a marker, nor an admin - bail.
            throw new remarks_permission_exception('Admin or marker priviliges needed', MOD_REMARKS_ERR_NOTPERMITTED);
        }
        if (!$admin && !remarks_is_allocated_marker($USER->id, $submissionid)) {
            // User isn't an admin, and isn't a marker allocated to this submisssion - bail.
            throw new remarks_permission_exception('Marker not allocated to specified submission', MOD_REMARKS_ERR_NOTPERMITTED);
        }

        // User is authorized to view this submission
        add_to_log($submission->courseid, 'remarks', "get originality report link", '', "$submissionid", $submission->cmid);

        // Make sure any plagiarism service knows about this user
        ob_start();
        plagiarism_update_status($COURSE, $cm);
        ob_end_clean();


        $fs = get_file_storage();
        $submissionfile = $fs->get_file_by_id($submission->fileid);

        require_once($CFG->libdir . '/plagiarismlib.php');
        if (!function_exists(plagiarism_get_file_results)) {
            throw new remarks_dependency_exception('plagiarism_get_file_results - no such function', MOD_REMARKS_ERR_MOODLENOTPATCHED);
        }
        // For group assignments, there are effectively a number of authors,
        // but the file is submitted to plagiarism services under the userid of the person who
        // actually submitted the file.
        $userid = $submission->submittedbyuserid;
        $allresults = plagiarism_get_file_results($submission->cmid, $userid, $submissionfile);
        $result = array_shift($allresults); // Take the result from 1st plagiarism plugin in use
        if (!empty($result['analyzed'])) {
            return $result['reporturl'];
        } else {
            return '';
        }
    }

    /**
     * Returns description of get_version parameters
     * @return external_function_parameters
     */
    public static function get_version_parameters() {
        return new external_function_parameters(
            array()
        );
    }

    /**
     * Returns description of get_version returns
     */
    public static function get_version_returns() {
        return new external_value(PARAM_TEXT, 'version');
    }
    /**
     * Get remarks version
     * @return string version number
     */
    public static function get_version() {
        global $DB;
        $remarks = $DB->get_record('modules',array('name' =>'remarks'));
        return $remarks->version;
    }
}
