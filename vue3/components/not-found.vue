<!-- // This file is part of Moodle - http://moodle.org/
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
 * Validate if the string does excist.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
    <div class="not-found">
        <h2>{{strings.route_not_found_site_name}}</h2>
        <h3>{{strings.route_not_found}}</h3>

        <div class="learninggoals-edit-add">
            <router-link :to="{ name: 'learninggoals-edit-overview' }" tag="button" class="btn btn-primary">{{strings.learninggoal_form_title_add}}</router-link>
        </div>
    </div>
</template>

<script>
    import { mapState } from 'vuex';

    export default {
        name: "not-found",
        computed: mapState(['strings']),
        created: function() {
        }
    }
</script>