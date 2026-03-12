# AdeLe - Adaptive eLearning Paths (backend plugin) #

Adele is a Moodle local plugin for building and operating adaptive **learning paths** in moodle. 

The plugin provides a graphical interface for defining path structures of independent Moodle courses, assigning users to learning paths, configuring node completion and access restrictions, and reacting to Moodle events such as course completion, quiz attempts, enrolments, and path updates.

The plugin further introduces corresponding roles and capabilities in Moodle. For using the adele functionality in moodle, you also need to install **mod_adele** as the front end course activity. It also supports the **local_catquiz** plugin for computerized adaptive testing (CAT).

## Key features ##

- Create and maintain learning paths with a dedicated graphical editor.
- Automatically ssign users to learning paths and track user–path relations.
- Configure **completion logic** and **restriction logic** for nodes.
- Filter the pool of selectable courses by role-based visibility, tags, categories, and restriction types.
- Integrate with Moodle quiz events and adaptive quiz / catquiz-related events.
- Control enrolment behavior, including which role is assigned through a learning path and whether an additional “assistant” role is granted.
- Use a Vue 3 frontend with Vue Router, Vuex, notifications, and Vue Flow-based graph tooling.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/local/adele

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## License ##

2026 Wunderbyte GmbH <info@wunderbyte.at>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
