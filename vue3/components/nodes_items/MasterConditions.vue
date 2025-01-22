<script setup>
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex';

const store = useStore();
const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

// Create a reactive master data object with default values
const masterdata = ref({});

const showmasterconditions = ref(false)

// Initialize the masterdata from props
onMounted(() => {
  masterdata.value = props.data
  masterdata.value = setDefaultValue(masterdata.value)
});

const setDefaultValue = (data) => {
  if (
    data.completion.master === null ||
    data.completion.master === undefined
  ) {
    data.completion.master = {
      completion: false,
      restriction: false
    };
  } else {
    if (data.completion.master.completion === undefined) {
      data.completion.master.completion = false;
    }
    if (data.completion.master.restriction === undefined) {
      data.completion.master.restriction = false;
    }
  }
  return data;
};

const toggleVisibility = () => {
  showmasterconditions.value = !showmasterconditions.value
};

</script>

<template>
  <div>
    <button
      class="btn btn-secondary dropdown-toggle master-dropdown"
      type="button"
      :id="masterdata.node_id + '_dropdown_menu_button'"
      @click="toggleVisibility"
    >

      {{ store.state.strings.course_master_conditions }}
    </button>
    <div v-if="showmasterconditions">

      <div class="form-check">
        <input
          :id="masterdata.node_id + '_master_restriction'"
          class="form-check-input"
          type="checkbox"
          v-model="masterdata.completion.master.restriction"
        >
        <label :for="masterdata.node_id + '_master_restriction'">
          {{ store.state.strings.course_master_condition_restriction }}
        </label>
      </div>

      <div class="form-check">
        <input
          :id="masterdata.node_id + '_master_completion'"
          class="form-check-input"
          type="checkbox"
          v-model="masterdata.completion.master.completion"
        >
        <label :for="masterdata.node_id + '_master_completion'">
          {{ store.state.strings.course_master_condition_completion }}
        </label>
      </div>

    </div>
  </div>
</template>

<style scoped>
.master-dropdown {
  width: 100%;
  margin-top: 1rem;
  background: aliceblue;
}
</style>