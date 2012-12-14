<?php

require_once($CFG->dirroot . '/mod/remarks/backup/moodle2/backup_remarks_stepslib.php');
require_once($CFG->dirroot . '/mod/remarks/backup/moodle2/backup_remarks_settingslib.php');

/**
 * remarks backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_remarks_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        $this->add_step(new backup_remarks_activity_structure_step('remarks_structure', 'remarks.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot,"/");

        // Link to the list of remarks assignments
        $search="/(".$base."\/mod\/remarks\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@REMARKSINDEX*$2@$', $content);

        // Link to remarks view by moduleid
        $search="/(".$base."\/mod\/remarks\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@REMARKSVIEWBYID*$2@$', $content);

        return $content;
    }
}
