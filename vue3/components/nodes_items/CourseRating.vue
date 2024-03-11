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

const totalRanks = 4
const stars = ref(0)

onMounted(() => {
  const rating = props.data.completion.completionnode.priority
  stars.value = totalRanks - rating + 1
})

const totalStars = [1,2,3,4]

 </script>
 
<template>
  <div>
    <div class="rating">
      <span 
        v-for="star in totalStars" 
        :key="star"
        :class="{ filled: star <= stars }"
      >
        &#9733;
      </span>
    </div>
  </div>
</template>

<style>
  .rating {
    display: inline-block;
  }

  .rating span {
    font-size: 24px;
  }

  .rating .filled {
    color: gold;
  }
</style>