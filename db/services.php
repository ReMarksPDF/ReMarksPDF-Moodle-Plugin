<?php
$functions = array(
    'mod_remarks_get_assignment_list' => array(
        'classname'   => 'mod_remarks_external',   //class containing the external function
        'methodname'  => 'get_assignment_list',    //external function name
        'classpath'   => 'mod/remarks/externallib.php', //file containing the class/external function
        'description' => 'Returns list of remarks assignment type in a course.', //human readable description of the web service function
        'type'        => 'read',                   //database rights of the web service function (read, write)
    ),
    'mod_remarks_get_sharefile' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'get_sharefile',
        'description' => 'retreive remarks sharefile',
        'type'        => 'read',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_set_sharefile' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'set_sharefile',
        'description' => 'create/update remarks sharefile',
        'type'        => 'write',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_get_sharefile' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'get_sharefile',
        'description' => 'retrieve remarks sharefile',
        'type'        => 'read',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_get_submitter_list' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'get_submitter_list',
        'description' => 'retrieve list of (potential) submitters',
        'type'        => 'read',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_get_groups' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'get_groups',
        'description' => 'retrieve list of groups of submitters',
        'type'        => 'read',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_get_markers' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'get_markers',
        'description' => 'retrieve list of markers',
        'type'        => 'read',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_get_designers' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'get_designers',
        'description' => 'retrieve list of admins',
        'type'        => 'read',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_get_marker_mappings' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'get_marker_mappings',
        'description' => 'retrieve map of markers to submitters',
        'type'        => 'read',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_set_marker_mappings' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'set_marker_mappings',
        'description' => 'map markers to submitters',
        'type'        => 'write',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_get_submission_list' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'get_submission_list',
        'description' => 'get list of assignment submissions',
        'type'        => 'read',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_get_submission' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'get_submission',
        'description' => 'get assignment submission',
        'type'        => 'read',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_set_submission' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'set_submission',
        'description' => 'set assignment submission',
        'type'        => 'write',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_release_submissions' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'release_submissions',
        'description' => 'release assignment submissions',
        'type'        => 'write',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_get_originality_report_link' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'get_originality_report_link',
        'description' => 'get link to assignment submission originality report',
        'type'        => 'read',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
    'mod_remarks_get_version' => array(
        'classname'   => 'mod_remarks_external',
        'methodname'  => 'get_version',
        'description' => 'get version number of remarks assignment',
        'type'        => 'read',
        'classpath'   => 'mod/remarks/externallib.php',
    ),
);

$services = array(
    'remarks' => array( //the name of the web service
          'functions' => array (
                    'moodle_webservice_get_siteinfo',
                    'moodle_enrol_get_users_courses',
                    'core_webservice_get_site_info',
                    'core_enrol_get_users_courses',
                    'mod_remarks_get_assignment_list',
                    'mod_remarks_set_sharefile',
                    'mod_remarks_get_sharefile',
                    'mod_remarks_get_submitter_list',
                    'mod_remarks_get_submission_list',
                    'mod_remarks_get_groups',
                    'mod_remarks_get_markers',
                    'mod_remarks_get_designers',
                    'mod_remarks_get_marker_mappings',
                    'mod_remarks_set_marker_mappings',
                    'mod_remarks_get_submission',
                    'mod_remarks_set_submission',
                    'mod_remarks_release_submissions',
                    'mod_remarks_get_originality_report_link',
                    'mod_remarks_get_version',
                ),
          'restrictedusers' => 0, //if enabled, the Moodle administrator must link some user to this service
          'enabled' => 1, //if enabled, the service can be reachable on a default installation
       )
);
