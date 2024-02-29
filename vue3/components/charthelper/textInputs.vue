<script setup>
import { ref, watch, onMounted, inject } from 'vue';

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
</script>

<template>
  <div>
    <div v-if="store.state.view!='teacher'">
      <h4 class="font-weight-bold">
        {{ store.state.strings.fromlearningtitel }}
      </h4>
      <div>
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
      <div>
        <textarea
          id="goalsubjectplaceholder"
          v-model="goaldescription"
          v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
          class="form-control fancy-input"
          :placeholder="store.state.strings.goalsubjectplaceholder"
        />
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
          <div v-if="goalname">
            {{ goalname }}
            <a 
              :href="'/local/adele/index.php#/learningpaths/edit/' + props.goal.id" 
              target="_blank"
            >
              <i class="fa fa-link" />
            </a>
          </div>
          <div v-else>
            No name provided.
          </div>
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
            {{ goaldescription ? goaldescription : 'No description provided.' }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>