<template>
  <div class="form-check">
    <div class="input-group mb-3 d-flex flex-column align-items-center">
      <span class="input-group-text rounded-end-0">{{ descriptions.start }}</span>
      <input
        type="datetime-local"
        class="form-control"
        style="
          width: 80%;
          border-radius: 0.5rem !important;
        "
        :value="data.start"
        @input="updateSelectedDateTime('start', $event)"
      >
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useStore } from 'vuex';

const store = useStore()

const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  }, 
  restriction: {
    type: Object,
    required: true,
  },
  });
const data = ref({
  start: null,
  end: null,
});

const options = {
  0: store.state.strings.course_select_condition_timed_duration_learning_path,
  1: store.state.strings.course_select_condition_timed_duration_node,
}

const duration = {
  0: store.state.strings.course_select_condition_timed_duration_days,
  1: store.state.strings.course_select_condition_timed_duration_weeks,
  2: store.state.strings.course_select_condition_timed_duration_months,
}

const emit = defineEmits(['update:modelValue'])

const updateSelectedDuration = (type, event) => {
  data.value[type] = event.target.value;
  emit('update:modelValue', data.value);
};

// Initialize the input with the modelValue
onMounted(() => {
  if (props.modelValue != null) {
    data.value = props.modelValue;
  }
});

// Watch for changes in modelValue
watch(() => props.modelValue, (newValue) => {
  data.value = newValue;
}, { deep: true } );

</script>