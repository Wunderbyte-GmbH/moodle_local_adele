<script setup>
    // Import needed libraries
    import CourseRating from '../nodes_items/CourseRating.vue';
    import { computed, onMounted, ref } from 'vue';
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
    const showCard = ref(false);

  onMounted(async () => {
      restrictionColor.value = store.state.strings.LIGHT_STEEL_BLUE;
      completionColor.value = store.state.strings.DARK_ORANGE;
  });

const shownCondition = ref(null)
const toggleCompletion = (type) => {
  showCard.value = !showCard.value
  shownCondition.value = type
}

const computedTriggerGetConditions = computed(() => {
  let returnComputed = {
      completion: {
          count: 0,
          conditions: null,
      },
      restriction: {
          count: 0,
          conditions: null,
      },
  };
  if (props.learningpath.json && props.learningpath.json.tree) {
    props.learningpath.json.tree.nodes.forEach((node) => {
        if (node.id == props.node.node_id) {
            if (node.completion != undefined) {
              returnComputed.completion = getConditions(node.completion.nodes, 'completion')
            }
            if (node.restriction != undefined) {
              returnComputed.restriction = getConditions(node.restriction.nodes, 'restriction')
            }
        }
    })
  }
  return returnComputed
})
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
                valid = getValidStatus(props.node.completion.completioncriteria.completed[node_completion.data.label])
              } else {
                valid = getValidStatus(props.node.completion.restrictioncriteria.completed[node_completion.data.label])
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
  <div
    class="icon-container"
    :class="{ 'card-hover': showCard }"
  >
    <div v-if="store.state.view=='student'">
      <div
        v-if="node.completion && (
          (node.completion.restrictionnode && node.completion.restrictionnode.valid) ||
          (node.completion.completionnode && node.completion.completionnode.valid)
        )"
        @click="toggleCompletion('completion')"
      >
        <div
          class="completion"
          :style="{ color: completionColor }"
        >
          <CourseRating :data="props.node" />
        </div>
      </div>
      <div v-else>
        <div
          @click="toggleCompletion('restriction')"
        >
          <div
            class="restriction"
            :style="{ color: restrictionColor }"
          >
            <i class="fas fa-key" />
          </div>
        </div>
      </div>
      <transition name="unfold">
        <div
          v-if="showCard"
          class="additional-card-student"
          :style="{ backgroundColor: shownCondition=='completion' ? completionColor : restrictionColor}"
        >
          <div v-if="shownCondition=='restriction' && computedTriggerGetConditions.restriction.count > 0 ">
            <ul class="list-group mt-3">
              <li
                v-for="(condition, index) in computedTriggerGetConditions.restriction.conditions"
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

          <div v-else-if="shownCondition=='completion' && computedTriggerGetConditions.completion.count > 0 ">
            <ul class="list-group mt-3">
              <li
                v-for="(condition, index) in computedTriggerGetConditions.completion.conditions"
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
      </transition>
    </div>
    <div v-else>
      <div
        v-if="computedTriggerGetConditions.restriction"
        class="card-container"
        :class="{ 'card-hover': showCard }"
        @click="toggleCards"
      >
        <div
          class="restriction"
          :style="{ color: restrictionColor }"
        >
          <i class="fas fa-key" />
          <span class="count">
            {{ computedTriggerGetConditions.restriction.count }}
          </span>
        </div>
        <div
          class="completion"
          :style="{ color: completionColor }"
        >
          <i
            :class="store.state.version ? 'fa-solid fa-check-to-slot' : 'fas fa-check-square'"
          />
          <span class="count">
            {{ computedTriggerGetConditions.completion.count }}
          </span>
        </div>
        <button
          v-if="showCard"
          class="cancel-button"
          @click.stop="toggleCards"
        >
          <i
            v-if="showCard"
            class="fas fa-times cancel-icon"
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
        <i class="fas fa-key" />
        <b>
          {{ store.state.strings.nodes_items_restriction }}
        </b>
        <div v-if="computedTriggerGetConditions.restriction.count > 0 ">
          <ul class="list-group mt-3">
            <li
              v-for="(condition, index) in computedTriggerGetConditions.restriction.conditions"
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
        <i class="fas fa-key" />
        <b>
          {{ store.state.strings.nodes_items_completion }}
        </b>
        <div v-if="computedTriggerGetConditions.completion.count > 0 ">
          <ul class="list-group mt-3">
            <li
              v-for="(condition, index) in computedTriggerGetConditions.completion.conditions"
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

.icon-container {
  position: absolute;
  top: -20px;
  right: -20px;
  display: inline-flex; /* Use flexbox for centering */
  justify-content: center;
  align-items: center;
  cursor: pointer;
}

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
  justify-content: center;
  align-items: center;
  width: 40px; /* Diameter of the round button */
  height: 40px; /* Diameter of the round button */
  border-radius: 50%; /* Makes the div round */
  border: 1px solid rgba(0,0,0,0.2);
  background-color: #f0f0f0; /* Light background for the button */
  box-shadow: 0 2px 4px rgba(0,0,0,0.2); /* Adds depth with a shadow */
  transition: background-color 0.3s, box-shadow 0.3s;
}

.completion:hover, .restriction:hover {
  background-color: #e2e2e2; /* Darker background on hover for feedback */
  box-shadow: 0 4px 6px rgba(0,0,0,0.2); /* Larger shadow on hover for depth */
}

.fa-check-to-slot,
.fa-key,
.fa-check-square {
  font-size: 20px; /* Adjust icon size as needed */
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

.additional-card-student {
  width: 300px;
  padding: 10px;
  text-align: center;
  border-radius: 8px;
  position: absolute;
  left: 40px;
  bottom: 40px;
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

/* Starting state for entering */
.unfold-enter-from, .unfold-leave-to {
  transform: scaleX(0);
  opacity: 0;
  transform-origin: left; /* Ensures scaling happens left to right */
}

/* Ending state for entering and starting state for leaving */
.unfold-enter-to, .unfold-leave-from {
  transform: scaleX(1);
  opacity: 1;
}

/* Active state for entering and leaving */
.unfold-enter-active, .unfold-leave-active {
  transition: transform 0.5s ease-out, opacity 0.5s ease-out;
  visibility: visible;
}

</style>
