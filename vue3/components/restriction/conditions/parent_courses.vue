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
 * parent courses component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div class="form-check">
    {{ restriction.description }}
    <div class="form-group">
      <div v-if="data.courses_id && data.courses_id.length > 0">
        <label
          class="form-label"
          :for="`restriction-${restriction.node_id}-min`"
        >
          {{ store.state.strings.restriction_select_number }}
        </label>
        <select
          :id="`restriction-${restriction.node_id}-min`"
          v-model="data.min_courses"
          :name="`restriction-${restriction.node_id}-min`"
          class="form-select"
          @change="emitSelectedParentCourse"
        >
          <option
            disabled
            value=""
            selected
          >
            {{ store.state.strings.restriction_choose_number }}
          </option>
          <option
            v-for="number in parentCourses"
            :key="number"
            :value="number"
          >
            {{ number }}
          </option>
        </select>
        / {{ data.courses_id.length }}
      </div>
      <div v-else>
        {{ store.state.strings.restriction_no_select_number }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex';

const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  },
  restriction: {
    type: Object,
    required: true,
  },
  // The path as the editor holds it right now. The store keeps the path as
  // it was last loaded: a predecessor added since (and not saved yet) is
  // missing there, and "n of m" would offer too few numbers.
  learningpath: {
    type: Object,
    default: null,
  },
});
const data = ref({
  min_courses: 1,
  courses_id: [],
});
const parentCourses = ref(0)
const emit = defineEmits(['update:modelValue'])

const store = useStore();

const emitSelectedParentCourse = () => {
  emit('update:modelValue', data.value);
};

// Initialize the input with the modelValue
onMounted(() => {
  if (props.restriction.value && props.restriction.value.min_courses) {
    data.value.min_courses = props.restriction.value.min_courses
  }
  let parentCoursesId = null;
  const path = props.learningpath?.json?.tree?.nodes ? props.learningpath : store.state.learningpath
  path.json.tree.nodes.forEach((node) => {
    if (node.id == store.state.node.node_id) {
      if (node.parentCourse == undefined ||
        node.parentCourse.includes('starting_node')) {
        parentCourses.value = 0
      } else{
        parentCourses.value = node.parentCourse.length
        data.value.courses_id = node.parentCourse
      }
    }
  })
  emitSelectedParentCourse();
});

</script>

<style scoped>
.form-check {
  margin-bottom: 10px;
}

.form-group {
  margin-bottom: 15px;
}

.form-label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
}

.form-select {
  padding: 8px;
  font-size: 14px;
  border: 1px solid #ced4da;
  border-radius: 4px;
}
</style>