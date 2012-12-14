What is ReMarksPDF-Moodle_Plugin?

The ReMarksPDF-Moodle-Plugin connects ReMarksPDF desktop and ReMarksPDF iPad with Moodle 2.1, 2.2 and 2.3. ReMarksPDF is an easy-to-use PDF editor for educators to annotate, collaborate and report on student electronic assessment submissions. ReMarksPDF is the ultimate e-Grading solution.

Key features of the ReMarksPDF desktop versions include:
* Mark on-line and off-line;
* Interactive rubrics (Holistic, grading, etc);
* Criterion-based grading;
* Automatic insertion of text based comments, known as Auto Text;
* Automatic insertion of sound based comments (enabling mark by voice), also known as Sounds;
* Automatic insertion of video based comments (enabling links to streamed video;
* Share text, sound and video comment libraries with colleagues over the Internet;
* Associate marks, criteria and comments with student assessment;
* Automatic addition of marks;
* Highlight colours with designated meanings, or in other words, Colour code your documents;
* Specialist stamps designed for marking, showing the emotion of the marker for more personalised feedback to students;
* Ability to designate macros for Auto Text, Sounds, and Video links;
* Handwriting and drawing tools;
* Import and export .cvs database files, linking marking to student documents, and uploading to a reporting system.
* Drag and drop dashboard graph gallery, indicating individual and relative student performance.
* Style tool specifically designed to rapidly incorporate English Style and Grammar comments for essays, plus the ability to build specialist comment libraries in any discipline;
* Advanced moderation capabilities enabling statistical and visual comparison of markers, individual and global moderation of student assessment;
* Quality assurance tools;
* Security;
* Integration with Learning Management Systems - Blackboard 8 and 9.1 (SP 7, 8, 9) and Moodle 2.1, 2.2, 2.3.
* Multilingual commentary (7 languages);
* ReMarksPDF has been successfully evaluated in University marking trials.
* Manuals training and support; and
* Ease of use.

Key features of the ReMarksPDF iPad version include:
* Mark on-line and off-line using an iPad;
* Interactive rubrics;
* Criterion-based grading;
* Automatic insertion of text, sound and ink based comments, known as Notes;
* Share text, sound and video comment libraries with colleagues over the Internet;
* Associate marks, criteria and comments with student assessment;
* Automatic addition of marks;
* Specialist stamps designed for marking, showing the emotion of the marker for more personalised feedback to students;
* Integration with Learning Management Systems - Blackboard 8 and 9.1 (SP 7, 8, 9) and Moodle 2.1, 2.2, 2.3.
* ReMarksPDF iPad version has been successfully evaluated in University marking trials.
* Manuals training and support; and
* Ease of use.


ReMarksPDF-Moodle-Plugin Install Instructions:

* Deploy the ReMarks Assignment codebase to <wwwroot>/mod/remarks/
* Hit 'notifications' in the site administration menu
* Click on 'update noodle database' if the plugin does not automatically install.
* For Moodle 2.1, for you to take advantage of integration between ReMarkPDFs and the Turnitin plagiarism plugin, you'll need to apply a minor patch to Moodle core (this patch is already included as part of Moodle 2.2).The patch is plagiarismAPI0001.patch, and can be applied on a nix box with a command like: `patch -p1 < plagiarismAPI0001.patch`

For integration with Turnitin, follow the instructions at:
http://docs.moodle.org/21/en/Plagiarism_Prevention_Turnitin_Settings

For integration with Urkund, follow the instructions at:
http://docs.moodle.org/21/en/Plagiarism_Prevention_URKUND_Settings
