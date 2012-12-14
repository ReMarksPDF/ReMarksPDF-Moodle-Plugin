<?php
defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/formslib.php");

class mod_remarks_upload_form extends moodleform {

    function definition() {

        global $COURSE,$DB,$USER;
        $mform =& $this->_form;
        //$mform->addElement('filemanager', 'submission', get_string('submission', 'remarks'), null,
        //            array('subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1, 'accepted_types' => array('*.pdf') ));
        $mform->addElement('filepicker', 'submission', get_string('submission', 'remarks'), null, array('maxbytes' => 0, 'accepted_types' => '*.pdf'));


        if (empty($this->_customdata['remarks'])) {
            $this->_customdata['remarks'] = '';
        }
        if (empty($this->_customdata['submitter'])) {
            $this->_customdata['submitter'] = '';
        }

        $mform->addElement('hidden', 'remarks', $this->_customdata['remarks']);
        $mform->setType('remarks', PARAM_INT);
        $mform->addElement('hidden', 'submitter', $this->_customdata['submitter']);
        $mform->setType('submitter', PARAM_INT);
        $mform->addElement('hidden', 'r', $this->_customdata['remarks']);
        $mform->setType('r', PARAM_INT);
        $mform->addElement('submit', 'savesubmit', get_string('savesubmit','remarks'));
        // TODO: add support for just uploading, and just submitting already uploaded file
        // TODO: for assignments with draft capability turned on
        //$mform->addElement('submit', 'submit', get_string('submit','remarks'));
        //$mform->addElement('submit', 'save', get_string('save','remarks'));
    }
    function definition_after_data() {
        global $CFG, $COURSE;
        $mform =& $this->_form;
#        if ($id = $mform->getElementValue('update')) {
#            $timeopen = $mform->getElementValue('timeopen');
#            if ($timeopen < time()) {
                #    $mform->disabledIf('timeopen', 'type', 'neq', 0);
                #    $mform->disabledIf('timeopen', 'type', 'eq', 0);
                #}
 #           }
#        }
    }
}
