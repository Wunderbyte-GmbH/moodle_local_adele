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

<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const medaille = ref(null)

onMounted(() => {
  switch (props.data.completion.completionnode.priority) {
      case '1':
        medaille.value = '#FFD700'
        break;
      case '2':
        medaille.value = '#C0C0C0'
        break;
      default:
        medaille.value = '#CD7F32'
    }
})

</script>
 
<template>
  <div>
    <i
      :class="store.state.version ? 'fa-solid fa-award fa-2xl' : 'fa fa-trophy fa-2xl'" 
      :style="{ color: medaille, textShadow: '0 0 2px #000' }"
    />
  </div>
</template>