<script setup>
import { ref, watch, onMounted, inject } from 'vue';
import router from '../../router/router';

const props = defineProps({
  goal: {
      type: Object,
      default: null,
    },
});
 
// Load Store and Router
const store = inject('store');

const emit = defineEmits([
    'change-GoalName',
    'change-GoalDescription',
]);

// Define constants that will be referenced
const goalname = ref('')
const goaldescription = ref('')

onMounted(() => {
  goalname.value = props.goal.name
  goaldescription.value = props.goal.description
})

// Watch changes on goalname
watch(goalname, (newGoalName) => {
    store.state.learningpath.name = newGoalName;
    emit('change-GoalName', newGoalName);
});

// Watch changes on goaldescription
watch(goaldescription, (newGoalDescription) => {
    store.state.learningpath.description = newGoalDescription;
    emit('change-GoalDescription', newGoalDescription);
});

// Watch changes on goaldescription
watch(() => store.state.learningpath, async () => {
  goalname.value = store.state.learningpath.name
  goaldescription.value = store.state.learningpath.description
}, { deep: true } );

// Edit learning path deletion
const editLearningpath = async (singlelearningpathid) => {
  // '/local/adele/index.php#/learningpaths/edit/' + 
  const tooltips = document.querySelectorAll('.tooltip');
  tooltips.forEach(tooltip => {
    tooltip.remove()
  });
  store.state.learningPathID = singlelearningpathid
  window.open('/local/adele/index.php#/learningpaths/edit/' + singlelearningpathid, '_blank');
};

</script>

<template>
  <div>
    <div v-if="store.state.view!='teacher'">
      <h4 class="font-weight-bold">
        {{ store.state.strings.fromlearningtitel }}
      </h4>
      <div class="mb-2">
        <input
          id="goalnameplaceholder"
          v-model="goalname"
          v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
          class="form-control fancy-input"
          :placeholder="store.state.strings.goalnameplaceholder"
          type="text"
          autofocus
        >
      </div>
      <h4 class="font-weight-bold">
        {{ store.state.strings.fromlearningdescription }}
      </h4>
      <div class="mb-2">
        <textarea
          id="goalsubjectplaceholder"
          v-model="goaldescription"
          v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
          class="form-control fancy-input"
          :placeholder="store.state.strings.goalsubjectplaceholder"
        />
      </div>
      <h4 class="font-weight-bold">
        {{ store.state.strings.from_default_node_image }}
      </h4>
      <div class="mb-2">
        Upload your default node image
      </div>
    </div>
    <div v-else>
      <div class="card border-primary mb-3">
        <div class="card-header bg-primary text-white">
          <h5 class="card-title mb-0">
            {{ store.state.strings.fromlearningtitel }}
          </h5>
        </div>
        <div class="card-body">
          <h4>{{ goalname }}</h4>
          <span v-if="goalname">
            <button 
              type="button" 
              class="btn btn-outline-primary btn-sm"
              :title="store.state.strings.charthelper_go_to_learningpath"
              @click.prevent="editLearningpath(props.goal.id)" 
            >
              {{ store.state.strings.modals_edit}}
            </button>
          </span>
          <span v-else>
            {{ store.state.strings.charthelper_no_name }}
          </span>
        </div>
      </div>
      <div class="card border-secondary">
        <div class="card-header bg-secondary text-white">
          <h5 class="card-title mb-0">
            {{ store.state.strings.fromlearningdescription }}
          </h5>
        </div>
        <div class="card-body">
          <p class="card-text">
            {{ goaldescription ? goaldescription : store.state.strings.charthelper_no_name }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>