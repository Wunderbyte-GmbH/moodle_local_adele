<template>
  <div class="form-group">
    <div class="input-group mb-3">
      <select 
        v-model="data.selectedOption"
        class="form-select"
        @change="onChange()"
      >
        <option 
          disabled 
          value="" 
          selected
        >
          Select the duration start
        </option>
        <option 
          v-for="(label, key) in options" 
          :key="key" 
          :value="key"
        >
          {{ label }}
        </option>
      </select>
    </div>
    <div 
      class="row input-group mb-3"
      style="margin-left: 0;"
    >
      <input
        v-model="data.selectedDuration"
        class="col-md-6 form-control"
        :placeholder="store.state.strings.course_name_condition_timed_duration_duration_value"
        @change="onChange()"
      >
      <select 
        v-model="data.durationValue"
        class="col-md-6 form-select ml-0" 
        @change="onChange()"
      >
        <option 
          disabled 
          value="" 
          selected
        >
          Select a duration format
        </option>
        <option 
          v-for="(label, key) in durationOptions" 
          :key="key" 
          :value="key"
        >
          {{ label }}
        </option>
      </select>
    </div>
    {{ store.state.strings.nodes_warning_time_heading }}
    <TimeWarning />
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useStore } from 'vuex';
import TimeWarning from '../../nodes_items/TimeWarning.vue'

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
  selectedOption: null,
  durationValue: null,
  selectedDuration: null,
});

// Options for the first select
const options = ref({
  0: store.state.strings.course_select_condition_timed_duration_learning_path,
  1: store.state.strings.course_select_condition_timed_duration_node,
});

// Options for the duration select
const durationOptions = ref({
  0: store.state.strings.course_select_condition_timed_duration_days,
  1: store.state.strings.course_select_condition_timed_duration_weeks,
  2: store.state.strings.course_select_condition_timed_duration_months,
});


const emit = defineEmits(['update:modelValue'])

const onChange = () => {
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

watch(() => data.value.selectedDuration, (newValue, oldValue) => {
  const parsedValue = parseInt(newValue, 10);

  // Check if it's not a number (NaN), less than 1, or a float
  if (isNaN(parsedValue) || parsedValue < 1 || parsedValue !== parseFloat(newValue)) {
    // Reset to old value if it was valid, otherwise, default to 1
    data.value.selectedDuration = oldValue && oldValue > 0 && parseInt(oldValue, 10) === parseFloat(oldValue) ? oldValue : '';
  } else {
    data.value.selectedDuration = parsedValue.toString();
  }
}, { deep: true });

</script>

<style scoped>

.form-select {
  width: 100%; /* Make the inputs fill their container */
  padding: 8px;
  font-size: 14px;
  border: 1px solid #ced4da;
  border-radius: 4px;
}

</style>