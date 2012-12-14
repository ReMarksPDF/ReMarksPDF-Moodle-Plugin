<?php
defined('MOODLE_INTERNAL') || die();

class remarks_exception extends Exception {
    public $message;
    public $code;

    /**
     * Constructor
     */
    function __construct($message, $code) {
        $this->message = $message;
        $this->code = $code;

        parent::__construct($message, $code);
    }
}
class remarks_unknown_exception extends remarks_exception {
}
class remarks_parameter_exception extends remarks_exception {
}
class remarks_permission_exception extends remarks_exception {
}
class remarks_versioning_exception extends remarks_exception {
}
class remarks_access_exception extends remarks_exception {
}
class remarks_dependency_exception extends remarks_exception {
}
?>
