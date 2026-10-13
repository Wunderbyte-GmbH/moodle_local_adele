<!--
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
-->

<!--
// Live region for status messages (#575 B4, WCAG 4.1.3 Status Messages).
//
// Everything the application would otherwise only show - a node that became
// accessible after a recompute, a saved path, a refused save - is written to
// store.state.announcement and read out here without moving the focus.
//
// Mounted once, in the application root, and empty until something happens:
// a live region has to exist in the document before the text appears in it,
// otherwise screen readers ignore the change.
//
// polite, not assertive: these messages accompany the work, they do not
// interrupt it.
//
// @package     local_adele
// @copyright   2026 Wunderbyte GmbH
// @copyright   2026 Ralf Erlebach
// @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
-->

<template>
  <div
    class="local-adele-sr-only"
    role="status"
    aria-live="polite"
    aria-atomic="true"
    data-testid="learningpath-announcer"
  >
    {{ store.state.announcement }}
  </div>
</template>

<script setup>
import { useStore } from 'vuex';

const store = useStore();
</script>

<style scoped>
/*
 * Visually hidden, but readable: display:none and visibility:hidden would
 * take the text out of the accessibility tree as well, which is the one
 * thing this element must not do.
 */
.local-adele-sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
