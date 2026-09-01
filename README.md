Adaptive e-Learning Paths (moodle-local_adele)
==============================================

[![Moodle Plugin CI](https://github.com/Wunderbyte-GmbH/moodle_local_adele/actions/workflows/moodle-plugin-ci.yml/badge.svg?branch=main)](https://github.com/Wunderbyte-GmbH/moodle_local_adele/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3Amain)

AdeLe - Adaptive e-Learning Paths - lets you build learning paths from independent Moodle courses: a graph of nodes, each with its own completion and access rules, edited graphically and evaluated per learner.

AdeLe is not a single plugin but a set of three that work as one system. They are developed together and declare each other as dependencies, so they can only be installed and updated as a set.

* **local_adele** is the learning path itself: the graphical editor, the node structure, the completion and restriction logic, and the Vue 3 frontend.
* **mod_adele** is the in-course entry point: it embeds a learning path in an ordinary course and decides which of that course's participants the path applies to.
* **enrol_adele** is the enrolment layer: it turns the learning path state into actual course enrolments and role assignments, and reconciles them.

This README documents **local_adele** - the first bullet point above. The other two plugins are documented in their own repositories.

Because the responsibilities are split this way, no rule exists twice: the learning path is defined here, embedded by mod_adele, and every enrolment it causes is owned by enrol_adele. That is also why a partial installation does not work - a missing sibling means a missing part of the mechanism.


Requirements
------------

This plugin requires Moodle 4.5+

It also requires the other AdeLe plugins. All three are developed together and must be installed in matching versions:

* **mod_adele (AdeLe activity)** - required dependency, declared in version.php\
  https://github.com/Wunderbyte-GmbH/moodle-mod_adele
* **enrol_adele (AdeLe enrolment)** - required dependency, declared in version.php\
  https://github.com/Wunderbyte-GmbH/moodle-enrol_adele

Optionally, **local_catquiz** can be used as a completion condition for computerized adaptive testing. That integration is guarded, so the plugin works without it.


Motivation for this plugin
--------------------------

Moodle can sequence activities inside a course, but a curriculum rarely fits into one. Prerequisites, alternative routes and "finish either of these two before that one" span courses, and expressing them through course-level restrictions means encoding the same rule in several places, where it drifts apart.

AdeLe moves that structure into one object. A learning path is a graph whose nodes point at courses; each node carries its own conditions for being accessible and for being complete. The graph is edited visually, evaluated per learner, and everything downstream - what a learner sees, which courses they may enter - follows from it.


Installation
------------

Install the plugin like any other plugin to folder
/local/adele

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins


Usage & Settings
----------------

After installing the plugin, learning paths are managed at Site administration -> Plugins -> Local plugins -> AdeLe, and edited through the graphical editor.

To configure the plugin and its behaviour, please visit:
Site administration -> Plugins -> Local plugins -> AdeLe

There, you find settings for:

* **Course pool** - which courses may be selected as nodes, filtered by tags, categories and role-based visibility.
* **Restriction and completion types** - which condition types are offered in the editor.
* **Enrolment behaviour** - which role a learning path assigns, and whether an additional assistant role is granted.

A learning path is made visible to learners by adding the **mod_adele** activity to a course. Which participants of that course the path applies to is decided there, not here.


Capabilities
------------

This plugin introduces these additional capabilities:

* **local/adele:view** - see learning paths.
* **local/adele:edit** - create and edit learning paths.
* **local/adele:teacheredit** - edit the learning paths one is assigned to as a teacher.
* **local/adele:canmanage** - manage learning paths across the site.
* **local/adele:assist** - act as a learning path assistant.


Scheduled Tasks
---------------

This plugin also introduces these additional scheduled tasks:

* **\local_adele\task\check_lp_ownership** - Checks and repairs the ownership of learning paths.\ By default, the task is enabled.

Learner state is recomputed in response to Moodle events - course completion, quiz attempts, enrolments, path updates - rather than on a schedule, so a change is visible immediately.


How this plugin works
---------------------

A learning path is stored as a JSON graph: nodes with the courses they point at, edges between them, and per-node conditions. For each subscribed learner a second record holds their state through that graph - which node is accessible, which is completed, plus any manual overrides a teacher has set.

That per-learner record is the only copy of the learner's progress. It is written when they are enrolled into a course that hosts the path, updated by the recompute pipeline, and removed by enrol_adele when no embedding carries them any more - deferred, and only after a re-check.


Theme support
-------------

This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic theme. However, we can't support any other theme than Boost.


Plugin repositories
-------------------

This plugin is not published in the Moodle plugins repository.

The latest development version can be found on Github:
https://github.com/Wunderbyte-GmbH/moodle_local_adele


Bug and problem reports / Support requests
------------------------------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/Wunderbyte-GmbH/moodle_local_adele/issues

We will do our best to solve your problems, but please note that due to limited resources we can't always provide per-case support.


Feature proposals
-----------------

Due to limited resources, the functionality of this plugin is primarily implemented for our own local needs and published as-is to the community. We are aware that members of the community will have other needs and would love to see them solved by this plugin.

Please issue feature proposals on Github:
https://github.com/Wunderbyte-GmbH/moodle_local_adele/issues

Please create pull requests on Github:
https://github.com/Wunderbyte-GmbH/moodle_local_adele/pulls

We are always interested to read about your feature proposals or even get a pull request from you, but please accept that we can handle your issues only as feature _proposals_ and not as feature _requests_.


Moodle release support
----------------------

Due to limited resources, this plugin is only maintained for the most recent major release of Moodle as well as the most recent LTS release of Moodle. Bugfixes are backported to the LTS release. However, new features and improvements are not necessarily backported to the LTS release.

Apart from these maintained releases, previous versions of this plugin which work in legacy major releases of Moodle are still available as-is without any further updates in the Moodle Plugins repository.

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on Github.

This plugin is designed to be compatible with all currently supported versions of Moodle, leveraging its latest APIs. However, if you are using a legacy version of Moodle, we kindly advise against installing or using this plugin. Instead, we strongly recommend updating your Moodle instance to a supported version to ensure security and compliance with current technological standards. Thank you for your understanding.


Translating this plugin
-----------------------

This Moodle plugin is provided with English and German language packs only. Translations into other languages must be managed through AMOS (https://lang.moodle.org), where they will become part of Moodle's official language pack.

As the plugin creator, we continue to maintain the German translation. For all other languages, we kindly ask you to contribute your translations directly in AMOS. These contributions will be reviewed by Moodle's official language pack maintainers before being included in the official repository.

Thank you for supporting the global Moodle community!


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to send us a pull request on Github with modifications.


Maintainers
-----------

The plugin is maintained by\
Wunderbyte GmbH


Copyright
---------

The copyright of this plugin is held by\
Wunderbyte GmbH

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.
