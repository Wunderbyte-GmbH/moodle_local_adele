<template>
  <div class="form-check">
    {{ store.state.strings.completion_manual_check }}
    <div>
      <input
        type="checkbox"
        id="enableTextarea"
        @click="toggleCustomInformation"
        :checked="isTextareaEnabled"
      />
      <label for="enableTextarea">{{ store.state.strings.enabletextarea_manual_check }}</label>
    </div>
    <textarea
      class="form-control"
      v-model="textInput"
      :name="store.state.strings.info_placeholder_manual_check"
      :disabled="!isTextareaEnabled"
      rows="4"
      @input="resetButtonColor"
      :placeholder="store.state.strings.info_placeholder_manual_check"
    ></textarea>
    <button
    :class="['btn', 'mt-2', { 'btn-success': isButtonClicked, 'btn-primary': !isButtonClicked }]"
      @click="updateInformation"
      :disabled="!isTextareaEnabled || !textInput"
    >
      {{ store.state.strings.btnupdatetext_manual_check }}
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useStore } from 'vuex'; // Import Vuex store

// Initialize the store
const store = useStore();

// Define the props for the component
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

const textInput = ref(props.completion?.information || '');
const isTextareaEnabled = ref(props.completion?.informationedited || false);
const isButtonClicked = ref(false);
const emit = defineEmits(['update:modelValue']);
const toggleCustomInformation = () => {
  isTextareaEnabled.value = !isTextareaEnabled.value;
};

const updateInformation = () => {
  if (props.completion) {
    props.completion.information = textInput.value;
    props.completion.informationedited = true;
    emit('update:modelValue', 'newinformation');
    isButtonClicked.value = true;
  }
};

const resetButtonColor = () => {
  isButtonClicked.value = false;
};
</script>

<style scoped>
.btn-success {
  background-color: green !important;
  border-color: green !important;
}
</style>