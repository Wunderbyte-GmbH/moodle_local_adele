<template>
  <div class="form-check">
    <div v-if="tests.length == 0">
        {{ store.state.strings.no_catquiz_class }}
    </div>
    <div v-else>
      {{ completion.description }}
      <DropdownInput
        :selected-test-id="selectedTest"
        :tests="tests"
        @update:value="updatedTest"
      />
      <div v-if="parentscales.length > 0">
        <select
          :id="`completion-${completion.node_id}-parent-scale`"
          v-model="selectedparentscale"
          :name="`completion-${completion.node_id}-parent-scale`"
          @change="updateScales"
        >
          <option
            v-for="parentScale in parentscales"
            :key="parentScale.id"
            :value="parentScale.id"
          >
            {{ parentScale.name }}
          </option>
        </select>
      </div>
      <div v-if="scales.parent">
        <table class="table table-bordered table-striped bg-white">
            <thead class="thead-light">
              <tr>
                <th>
                  {{ store.state.strings.conditions_parent_scale_name }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                :class="[
                  scales.parent && (scales.parent.scale || scales.parent.attempts > 0) ? 'green-row' : 'empty-row'
                ]"
              >
                <td class="position-relative">
                  <div
                    class="item-container"
                    @click="showDetails(scales.parent.name, ['parent'])"
                  >
                    {{ scales.parent.name }}
                    <div
                      v-if="scales.parent.showDetails"
                      class="icon-container"
                    >
                      <i class="fa-solid fa-arrow-right" />
                    </div>
                  </div>
                  <div
                    v-if="scales.parent && scales.parent.showDetails"
                    class="dynamic-content-container"
                  >
                    <label :for="`completion-${completion.node_id}-scale-value`">
                      {{ store.state.strings.conditions_scale_value }}
                    </label>
                    <input
                      :id="`completion-${completion.node_id}-scale-value`"
                      v-model="scalevalue"
                      :name="`completion-${completion.node_id}-scale-value`"
                      class="form-control"
                    >
                    <label
                      :for="`completion-${completion.node_id}-attempts`"
                      class="mt-3"
                    >
                      {{ store.state.strings.conditions_attempts }}
                    </label>
                    <input
                      :id="`completion-${completion.node_id}-attempts`"
                      v-model="attempts"
                      :name="`completion-${completion.node_id}-attempts`"
                      class="form-control"
                    >
                    <button
                      class="btn btn-primary rounded-pill"
                      @click="setValues(scales.parent.id, 'parent')"
                    >
                      {{ store.state.strings.conditions_set_values }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
        </table>
        <button
          v-if="scales.sub && scales.sub.length > 0"
          class="btn btn-primary rounded-pill"
          @click="toggleTable"
        >
          {{ showTable ? store.state.strings.conditions_catquiz_hide_table : store.state.strings.conditions_catquiz_show_table }}
        </button>

        <div v-else>
          {{ store.state.strings.conditions_no_scales }}
        </div>
        <div
          v-if="showTable"
          class="mt-3"
        >
          <table class="table table-bordered table-striped bg-white">
            <thead class="thead-light">
              <tr>
                <th>
                  {{ store.state.strings.conditions_name }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="scale in scales.sub"
                :key="scale.id"
                :class="[
                  (scale.scale || scale.attempts > 0) ? 'green-row' : 'empty-row'
                ]"
              >
                <td class="position-relative">
                  <div
                    class="item-container"
                    @click="showDetails(scale.name, ['sub'])"
                  >
                    {{ scale.name }}
                    <div
                      v-if="scale.showDetails"
                      class="icon-container"
                    >
                      <i class="fa-solid fa-arrow-right" />
                    </div>
                  </div>
                  <div
                    v-if="scale.showDetails"
                    class="dynamic-content-container"
                  >
                    <label :for="`completion-${completion.node_id}-sub-scale-value`">
                      {{ store.state.strings.conditions_scale_value }}
                    </label>
                    <input
                      :id="`completion-${completion.node_id}-sub-scale-value`"
                      v-model="scalevalue"
                      :name="`completion-${completion.node_id}-sub-scale-value`"
                      class="form-control"
                    >
                    <label
                      :for="`completion-${completion.node_id}-sub-attempts`"
                      class="mt-3"
                    >
                      {{ store.state.strings.conditions_attempts }}
                    </label>
                    <input
                      :id="`completion-${completion.node_id}-sub-attempts`"
                      v-model="attempts"
                      :name="`completion-${completion.node_id}-sub-attempts`"
                      class="form-control"
                    >
                    <button
                      class="btn btn-primary rounded-pill"
                      @click="setValues(scale.id, 'sub')"
                    >
                      {{ store.state.strings.conditions_set_values }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useStore } from 'vuex';
import DropdownInput from '../../nodes_items/DropdownInput.vue'

// Load Store
const store = useStore();
const props = defineProps({
  modelValue:{
    type: Object,
    default: null,
  },
  completion: {
    type: Object,
    default: null,
  }})
const data = ref([])
const tests = ref([])
const parentscales = ref([])
const selectedparentscale = ref('')
const selectedparentscale_courseid = ref('')
const selectedtest_component_id = ref('')
const scales = ref([])
const selectedTest = ref(null)
const emit = defineEmits(['update:modelValue'])
const showTable = ref(false)
const scalevalue = ref('');
const attempts = ref('');
onMounted(async () => {
  // Get all tests
  tests.value = await store.dispatch('fetchCatquizTests')
  if (props.completion.value !== undefined) {
    data.value = props.completion.value;
    if (props.completion.value.testid !== undefined) {
      selectedTest.value = props.completion.value.testid;
    }
    if (props.completion.value.testid_courseid !== undefined) {
      selectedparentscale_courseid.value = props.completion.value.testid_courseid;
    }
    if (props.completion.value.componentid !== undefined) {
      selectedtest_component_id.value = props.completion.value.componentid;
    }
    if (props.completion.value.scales !== undefined) {
      scales.value = props.completion.value.scales;
    }
    if (props.completion.value.parentscales !== undefined) {
      selectedparentscale.value = props.completion.value.parentscales;
      parentscales.value = await store.dispatch('fetchCatquizParentScales')
    }
  }
  // watch values from selected node
  watch(() => selectedTest.value, async () => {

    if (selectedTest.value == 0) {
      parentscales.value = await store.dispatch('fetchCatquizParentScales')
      scales.value = []
      data.value = {
        testid: selectedTest.value,
        testid_courseid: selectedparentscale_courseid.value,
        componentid: selectedtest_component_id.value,
      }
    } else if (selectedTest.value !== null) {
      scales.value = await store.dispatch('fetchCatquizScales', {testid: selectedTest.value})
      parentscales.value = []
      data.value = {
        testid: selectedTest.value,
        testid_courseid: selectedparentscale_courseid.value,
        componentid: selectedtest_component_id.value,
        scales: scales.value,
      }
    } else {
      parentscales.value = []
      scales.value = []
      data.value = {}
    }
  }, { deep: true } );
});

const updateScales = async () => {

  scales.value = await store.dispatch('fetchCatquizParentScale', {scaleid: selectedparentscale.value})
  data.value.scales = scales.value
  data.value.parentscales = selectedparentscale.value
  data.value.selectedparentscale_courseid = selectedparentscale_courseid.value
  data.value.componentid = selectedtest_component_id.value
}

// watch values from selected node
watch(() => data.value, () => {
  emit('update:modelValue', data.value);
}, { deep: true } );

const toggleTable = () => {
  showTable.value = !showTable.value;
  hideDetails(null, ['sub', 'parent'])
};

const showDetails = (name, scaletypes) => {
  hideDetails(name, ['sub', 'parent'])
  scaletypes.forEach((type) => {
    let scale = null
    if (type == 'parent') {
      scale = scales.value[type]
    } else {
      scale = scales.value[type].find((s) => s.name === name);
    }
    if (scale) {
      scale.showDetails = !scale.showDetails;
    }
    if (scale.scale) {
      scalevalue.value = scale.scale;
    }else{
      scalevalue.value = '';
    }
    if (scale.attempts) {
      attempts.value = scale.attempts;
    }else{
      attempts.value = '';
    }
  })
};

const hideDetails = (name, scaletypes) => {
  scaletypes.forEach((type) => {
    if (scales.value[type]) {
      if (type == 'parent') {
        scales.value[type].showDetails = false;
      } else {
        scales.value[type].forEach((detail) => {
          if (detail.name !== name) {
            detail.showDetails = false;
          }
        });
      }
    }
  });
};

const setValues = (id, scaletype) => {
  hideDetails(null, ['sub', 'parent'])
  if (scaletype == 'parent') {
    data.value.scales[scaletype].scale = scalevalue.value;
    data.value.scales[scaletype].attempts = attempts.value;
  } else {
    const indexToUpdate = data.value.scales.sub.findIndex((scale) => scale.id === id);
    if (indexToUpdate !== -1) {
      data.value.scales.sub[indexToUpdate].scale = scalevalue.value;
      data.value.scales.sub[indexToUpdate].attempts = attempts.value;
    }
  }
}

const updatedTest = (test) => {
  if (test) {
    selectedTest.value = test.id || null;
    selectedparentscale_courseid.value = test.courseid || null;
    selectedtest_component_id.value = test.componentid || null;
  } else {
    selectedTest.value = null;
    selectedparentscale_courseid.value = null;
    selectedtest_component_id.value = null;
  }
}

</script>

<style scoped>

.dynamic-content-container {
  position: absolute;
  top: 0;
  left: 100%;
  background-color: #fff;
  border: 1px solid #ccc;
  padding: 10px;
  border-radius: 5px;
  margin-left: 10px;
  min-width: 250px;
  z-index: 1;
}

.empty-row {
  cursor: pointer;
  background-color: #e6e5e5 !important;
  transition: background-color 0.3s ease, color 0.3s ease;
}
.empty-row:hover {
  background-color: #b8b8b8 !important;
}

.green-row {
  cursor: pointer;
  background-color: #e0f7d5 !important;
  transition: background-color 0.3s ease, color 0.3s ease;
}
.green-row:hover {
  background-color: #b8e5a1 !important;
}

.icon-container {
    margin-left: auto;
    padding-left: 10px;
}
.item-container {
    display: flex;
    align-items: center;
    cursor: pointer;
}

</style>