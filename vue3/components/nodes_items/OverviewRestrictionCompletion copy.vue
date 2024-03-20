<script setup>
    // Import needed libraries
    import { onMounted, ref, watch } from 'vue';
    import { useStore } from 'vuex';

    
    // Load Store 
    const store = useStore();
    const props = defineProps({
      node: {
        type: Object,
        required: true,
      },
      learningpath: {
        type: Object,
        required: true,
      },
    });
    // Colors for restriction and completion
    const restrictionColor = ref('#87b8ce');
    const completionColor = ref('#df843b');

    // Create a ref for conditions
    const conditions = ref([]);
    const showCard = ref(false);

    onMounted(async () => {
        restrictionColor.value = store.state.strings.LIGHT_STEEL_BLUE;
        completionColor.value = store.state.strings.DARK_ORANGE;
        conditions.value = {
        completion: {
            count: 0,
            conditions: null,
        },
        restriction: {
            count: 0,
            conditions: null,
        },
        };
        triggerGetConditions()
    // watch values from selected node
    watch(() => props.learningpath, async () => {
        triggerGetConditions()
    }, { deep: true } );
  });

const shownCondition = ref(null)
const toggleCompletion = (type) => {
  showCard.value = !showCard.value
  shownCondition.value = type
}

function triggerGetConditions() {
    if (props.learningpath.json && props.learningpath.json.tree) {
      props.learningpath.json.tree.nodes.forEach((node) => {
          if (node.id == props.node.node_id) {
              if (node.completion != undefined) {
                conditions.value.completion = getConditions(node.completion.nodes, 'completion') 
              }
              if (node.restriction != undefined) {
                conditions.value.restriction = getConditions(node.restriction.nodes, 'restriction') 
              }
          }
      })
    }
}
function getConditions(completion_nodes, type) {
    let count = 0
    let conditions = []
    completion_nodes.forEach((node_completion) => {
        if (node_completion.type != 'feedback' && !(
          store.state.view=='student' && !node_completion.data.visibility
        )) {
            let valid = false
            if(store.state.view=='student') {
              if (type == 'completion') {
                valid = getValidStatus(props.node.completion.completioncriteria[node_completion.data.label])
              } else {
                valid = getValidStatus(props.node.completion.restrictioncriteria[node_completion.data.label])
              }
            }
            count ++
            conditions.push({
              name: node_completion.data.description,
              valid: valid,
            })
        }
    })
    return {
        count: count,
        conditions: conditions,
    }
}

function getValidStatus(validation) {
  if (validation == null ||!validation) {
    return false
  } else if (validation == true) {
    return true
  } else if (typeof validation == 'object') {
    for (const key in validation) {
      if (validation[key]) {
        return true
      }
    }
    return false
  }
  return false
}

const toggleCards = () => {
  showCard.value = !showCard.value;
};

</script>

<template>
  <div>
    <div v-if="store.state.view=='student'">
      <div 
        v-if="node.completion && (
          (node.completion.restrictionnode && node.completion.restrictionnode.valid) ||
          (node.completion.completionnode && node.completion.completionnode.valid)
        )"
      >
        <div
          class="card-container"
          :class="{ 'card-hover': showCard }"
          @click="toggleCompletion('completion')"
        >
          <div 
            class="completion" 
            :style="{ color: completionColor }"
          >
            Completions
            <i class="ml-2 fa-solid fa-check-to-slot" />
          </div>
          <button 
            v-if="showCard" 
            class="cancel-button" 
            @click="toggleCompletion('completion')"
          >
            <i
              class="fa-solid fa-times cancel-icon"
              @click="toggleCompletion('completion')"
            />
          </button>
        </div>
      </div>
      <div v-else>
        <div
          class="card-container"
          :class="{ 'card-hover': showCard }"
          @click="toggleCompletion('restriction')"
        >
          <div 
            class="restriction" 
            :style="{ color: restrictionColor }"
          >
            {{ store.state.strings.nodes_items_restrictions }}
            <i class="ml-2 fa-solid fa-key" />
          </div>
          <button 
            v-if="showCard" 
            class="cancel-button" 
            @click="toggleCompletion('restriction')"
          >
            <i
              class="fa-solid fa-times cancel-icon" 
              @click="toggleCompletion('restriction')"
            />
          </button>
        </div>
      </div>
      <div 
        v-if="showCard" 
        class="additional-card left" 
        :style="{ backgroundColor: shownCondition=='completion' ? completionColor : restrictionColor}"
      >
        <div v-if="shownCondition=='restriction' && conditions.restriction.count > 0 ">
          <ul class="list-group mt-3">
            <li 
              v-for="(condition, index) in conditions.restriction.conditions" 
              :key="index"
              class="list-group-item"
            >
              {{ condition.name }}
              <i 
                v-if="condition.valid" 
                class="fas fa-check fa-xl"
                style="color: #63E6BE; font-weight: bold; text-shadow: 0 0 2px #000;"
              />
            </li>
          </ul>
        </div>

        <div v-else-if="shownCondition=='completion' && conditions.completion.count > 0 ">
          <ul class="list-group mt-3">
            <li 
              v-for="(condition, index) in conditions.completion.conditions" 
              :key="index"
              class="list-group-item"
            >
              {{ condition.name }}
              <i 
                v-if="condition.valid" 
                class="fas fa-check fa-xl"
                style="color: #63E6BE; font-weight: bold; text-shadow: 0 0 2px #000;"
              />
            </li>
          </ul>
        </div>

        <div v-else>
          <ul class="list-group mt-3">
            <li class="list-group-item">
              {{ store.state.strings.nodes_items_no_conditions }}
            </li>
          </ul>
        </div>
      </div>
    </div>
    <div v-else>
      <div 
        v-if="conditions.restriction" 
        class="card-container"
        :class="{ 'card-hover': showCard }"
        @click="toggleCards"
      >
        <div 
          class="restriction" 
          :style="{ color: restrictionColor }"
        >
          <i class="fa-solid fa-key" />
          <span class="count">
            {{ conditions.restriction.count }}
          </span>
        </div>
        <div 
          class="completion" 
          :style="{ color: completionColor }"
        >
          <i class="fa-solid fa-check-to-slot" />
          <span class="count">
            {{ conditions.completion.count }}
          </span>
        </div>
        <button 
          v-if="showCard" 
          class="cancel-button" 
          @click.stop="toggleCards"
        >
          <i 
            v-if="showCard" 
            class="fa-solid fa-times cancel-icon" 
            @click.stop="toggleCards" 
          />
        </button>
      </div>
  
      <!-- Left Card -->
      <div 
        v-if="showCard" 
        class="additional-card left" 
        :style="{ backgroundColor: restrictionColor }"
      >
        <!-- Content for the left card -->
        <i class="fa-solid fa-key" />
        <b>
          {{ store.state.strings.nodes_items_restriction }}
        </b>
        <div v-if="conditions.restriction.count > 0 ">
          <ul class="list-group mt-3">
            <li 
              v-for="(condition, index) in conditions.restriction.conditions" 
              :key="index"
              class="list-group-item"
            >
              {{ condition.name }}
            </li>
          </ul>
        </div>
        <div v-else>
          <ul class="list-group mt-3">
            <li class="list-group-item">
              {{ store.state.strings.nodes_items_no_restrictions }}
            </li>
          </ul>
        </div>
      </div>
  
      <!-- Right Card -->
      <div 
        v-if="showCard" 
        class="additional-card right" 
        :style="{ backgroundColor: completionColor }"
      >
        <!-- Content for the left card -->
        <i class="fa-solid fa-key" />
        <b>
          {{ store.state.strings.nodes_items_completion }}
        </b>
        <div v-if="conditions.completion.count > 0 ">
          <ul class="list-group mt-3">
            <li 
              v-for="(condition, index) in conditions.completion.conditions" 
              :key="index"
              class="list-group-item"
            >
              {{ condition.name }}
            </li>
          </ul>
        </div>
        <div v-else>
          <ul class="list-group mt-3">
            <li class="list-group-item">
              {{ store.state.strings.nodes_items_no_restrictions }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.card-container {
  cursor: pointer;
  width: 100%;
  display: -webkit-box;
  width: 100%;
  padding: 5px;
  border-radius: 8px;
  background-color: #EAEAEA;
  font-weight: bold; /* Make the text bold */
}

.card-container:hover {
  background-color: rgb(213, 207, 207); /* Change background color on hover */
}

.restriction,
.completion {
  display: flex;
  align-items: flex-end; /* Align items at the bottom within each child */
  margin-right: 10px; /* Add margin to separate items within each child */
}

.additional-card {
  width: 300px !important;
  padding: 10px !important;
  text-align: center !important;
  border-radius: 8px;
  margin-top: 10px;
  position: absolute;
  top: 70%;
}

.left {
  right: 105%;
}

.right {
  left: 105%;
}

.cancel-button {
  float: inline-end;
  margin-left: auto; 
  background-color: rgb(109, 107, 107);
  color: white;
  border: none;
  border-radius: 4px;
}
</style>
