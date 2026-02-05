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
  }
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
  store.state.learningpath.json.tree.nodes.forEach((node) => {
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