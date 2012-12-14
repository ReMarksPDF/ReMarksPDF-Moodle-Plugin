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
 * This file replaces the legacy STATEMENTS section in db/install.xml,
 * lib.php/modulename_install() post installation hook and partially defaults.php
 *
 * @package   mod_remarks
 * @copyright 2010 Your Name <your@email.adress>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Post installation procedure
 */
function xmldb_remarks_install() {
    global $DB, $CFG;
    $systemcontext = get_system_context();

    // Admin has just installed our module.
    // To be at all useful, we need webservices, and xmlrpc in particular.
    // Assert that these are enabed for the admin.
    if (empty($CFG->enablewebservices)) {
        set_config('enablewebservices', '1');
    }
    if (empty($CFG->webserviceprotocols)) {
        $webserviceprotocols = array();
    } else {
        $webserviceprotocols = explode(',', $CFG->webserviceprotocols);
    }
    if (!in_array('xmlrpc', $webserviceprotocols)) {
        $webserviceprotocols[] = 'xmlrpc';
        $protocollist = implode(',', $webserviceprotocols);
        set_config('webserviceprotocols', $protocollist);
    }

    //Likewise, we give authenticated users the rights to generate a security token, & use xmlrpc:
    $sql = "SELECT * " .
            "FROM {context} cx " .
            " INNER JOIN {role_capabilities} rc on rc.contextid = cx.id " .
            "WHERE cx.contextlevel = " . CONTEXT_SYSTEM . " " .
            " AND rc.capability = ? " .
            " AND rc.roleid = ?";
    $roleid = $CFG->defaultuserroleid;
    if (empty($roleid)) {
        // Defaultuserroleid might not be defined during moodle's initial install
        $userrole = $DB->get_record('role', array('shortname' => 'user'));
        $roleid = $userrole->id;
    }
    $capability = 'webservice/xmlrpc:use';
    $authusercaps = $DB->get_records_sql($sql, array($capability, $roleid));
    if (empty($authusercaps)) {
        assign_capability($capability, CAP_ALLOW, $roleid, $systemcontext->id);
    }
    $capability = 'moodle/webservice:createtoken';
    $authusercaps = $DB->get_records_sql($sql, array($capability, $roleid));
    if (empty($authusercaps)) {
        assign_capability($capability, CAP_ALLOW, $roleid, $systemcontext->id);
    }
}
