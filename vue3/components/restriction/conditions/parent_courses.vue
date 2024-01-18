<template>
  <div class="form-check">
    {{ restriction.description }}
    <div v-if="data">
      <select 
        v-model="data.min_courses" 
        @change="emitSelectedParentCourse"
      >
        <option 
          disabled 
          value="" 
          selected
        >
          Choose a number
        </option>
        <option 
          v-for="number in parentCourses" 
          :key="number" 
          :value="number"
        >
          {{ number }}
        </option>
      </select>
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
  }
});
const data = ref({
  min_courses: 0,
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
  store.state.learninggoal[0].json.tree.nodes.forEach((node) => {
    if (node.id == store.state.node.node_id) {
      if (node.parentCourse == undefined ||
        node.parentCourse.includes('starting_node')) {
        parentCourses.value = 0
      } else{
        parentCourses.value = node.parentCourse.length
        parentCoursesId = node.parentCourse
      }
    }
  })
  let CoursesId = [];
  if (parentCoursesId != null) {
    store.state.learninggoal[0].json.tree.nodes.forEach((node) => {
      if (parentCoursesId.includes(node.id)) {
        CoursesId.push(node.data.course_node_id[0])
      }
    })
  }
  data.value.courses_id = CoursesId
});

</script>