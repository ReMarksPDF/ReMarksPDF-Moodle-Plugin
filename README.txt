Install instructions:

* Deploy the ReMarks Assignment codebase to <wwwroot>/mod/remarks/
* Hit 'notifications' in the site administration menu
* Click on 'update noodle database' if the plugin does not automatically install.

* To take advantage of integration between ReMarks and a plagiarism plugin, if your moodle is a 2.1, you'll need to apply a minor patch to Moodle core (this patch is already included as part of Moodle 2.2)
The patch is plagiarismAPI0001.patch, and can be applied on a nix box with a command like: `patch -p1 < plagiarismAPI0001.patch`

For integration with turnitin, follow the instructions at:
http://docs.moodle.org/21/en/Plagiarism_Prevention_Turnitin_Settings

For integration with urkund, follow the instructions at:
http://docs.moodle.org/21/en/Plagiarism_Prevention_URKUND_Settings
