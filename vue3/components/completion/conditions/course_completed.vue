<template>
  <div class="form-check">
    <div v-if="course_node.course_node_id && course_node.course_node_id.length == 1">
      {{ completion.description }}
    </div>
    <div v-else-if="course_node.course_node_id">
      <label 
        class="form-label" 
        for="courseSelect"
      >
        Select the minimum amount of finished courses
      </label>
      <select 
        v-model="data.min_courses"
        class="form-select" 
        @change="emitSelectedParentCourse"
      >
        <option 
          disabled 
          value="" 
          selected
        >
          Choose a number of courses
        </option>
        <option 
          v-for="number in nodeCourses" 
          :key="number" 
          :value="number"
        >
          {{ number }}
        </option>
      </select>
      / {{ course_node.course_node_id.length }}
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useStore } from 'vuex';

const store = useStore();
const course_node = ref({});
const nodeCourses = ref(0)
const emit = defineEmits(['update:modelValue'])

const data = ref({
  min_courses: 0,
});

const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  },
  completion: {
    type: Object,
    default: null,
  },
});

onMounted(() => {
  course_node.value = store.state.node;
  if (props.completion.value && props.completion.value.min_courses) {
    data.value.min_courses = props.completion.value.min_courses
  }
  nodeCourses.value = course_node.value.course_node_id.length
})

// watch values from selected node
watch(() => data.value, () => {
  emit('update:modelValue', data.value);
}, { deep: true } );

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