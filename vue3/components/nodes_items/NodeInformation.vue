<script setup>
  // Import needed libraries
  import { computed, onMounted, ref } from 'vue';
  import { useStore } from 'vuex';

  const props = defineProps({
    data: {
      type: Object,
      required: true,
    },
    parentnode: {
      type: Object,
      required: true,
    },
  });
  const store = useStore();

  // Create a ref for conditions
  const showCard = ref(false);

  const toggleCard = () => {
    showCard.value = !showCard.value
  }
  const backgroundColor = computed(() => store.state.strings.DARK_RED)
  const backgroundColorInfo = computed(() => store.state.strings.LIGHT_GRAY)

  const description = ref({})
  const restriction = computed(() => getConditions(props.parentnode, 'restriction'))
  const completion = computed(() => getConditions(props.parentnode, 'completion'))

  const getConditions = (parentnode, type) => {
    let condition_strings = []
    if (parentnode[type].nodes) {
      parentnode[type].nodes.forEach((node) => {
        if (node.parentCondition && node.parentCondition.includes('starting_condition')) {
          let current_node = node
          let condi_string = ''
          let i = 0
          while (current_node && i < 4) {
            if (current_node.data.visibility) {
              if (condi_string != '') {
                condi_string += ', '
              }
              condi_string += current_node.data.description
            }
            current_node = findNextNode(current_node, parentnode[type].nodes)
            i += 1
          }
          condition_strings.push(condi_string)
        }
      })
    }
    return condition_strings
  }
  const findNextNode = (parentnode, nodes) => {
    if (!parentnode.childCondition || parentnode.childCondition.length === 0) {
        return null;
    }
    let nextNodeId = parentnode.childCondition[0];
    if (nextNodeId.includes('_feedback')) {
      nextNodeId = parentnode.childCondition[1];
    }
    let nextNode = nodes.find(node => node.id === nextNodeId); 
    return nextNode
  }

  onMounted(() => {
    description.value = props.data.description ||null
  })

</script>

<template>
  <div
    class="icon-container"
    :class="{ 'card-hover': showCard }"
    @click="toggleCard()"
  >
    <div 
      class="information" 
      :style="{ backgroundColor: backgroundColor }"
    >
      <i class="fa-solid fa-info" />
    </div>
    <transition name="unfold">
      <div 
        v-if="showCard" 
        class="additional-card" 
        :style="{ backgroundColor: backgroundColorInfo}"
      >
        <ul class="list-group">
          <li 
            v-if="description"
            class="list-group-item"
          >
            <i class="fa-solid fa-pen" />
            <b>
              Description
            </b>
            <div class="list-group-text">
              {{ description }}
            </div>
          </li>
          <li 
            v-if="props.parentnode.restriction.nodes"
            class="list-group-item"
          >
            <i class="fa-solid fa-lock" />
            <b>
              Restriction
            </b>
            <div class="list-group-text">
              <div v-if="restriction.length > 0">
                <div 
                  v-for="restriction_string in restriction"
                  :key="restriction_string"
                >
                  - {{ restriction_string }}
                </div>
              </div>
              <div v-else>
                Nothing is defined
              </div>
            </div>
          </li>
          <li 
            v-if="props.parentnode.completion.nodes"
            class="list-group-item"
          >
            <i class="fa-solid fa-bars-progress" />
            <b>
              Completion
            </b>
            <div class="list-group-text">
              <div v-if="completion.length > 0">
                <div 
                  v-for="completion_string in completion"
                  :key="completion_string"
                >
                  - {{ completion_string }}
                </div>
              </div>
              <div v-else>
                Nothing is defined
              </div>
            </div>
          </li>
        </ul>
      </div>
    </transition>
  </div>
</template>

<style scoped>

.list-group-text{
  text-align: left;
}

.icon-container {
  position: absolute;
  top: -25px;
  right: -25px;
  display: inline-flex; /* Use flexbox for centering */
  justify-content: center;
  align-items: center;
  cursor: pointer;
  z-index: 1;
}

.information {
  display: inline-block;
  width: 50px; /* Diameter of the round button */
  height: 50px; /* Diameter of the round button */
  border-radius: 50%; /* Makes the div round */
  border: 1px solid rgba(0,0,0,0.2);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.38); /* Adds depth with a shadow */
}

.information:hover {
  background-color: #ad0050 !important; /* Darker background on hover for feedback */
  box-shadow: 0 6px 8px rgba(0,0,0,0.2); /* Larger shadow on hover for depth */
}

.fa-info {
  font-size: 30px; /* Make the icon larger */
  color: white; /* Change the color if needed */
  margin-top: 7px;
}

.additional-card {
  width: 500px;
  padding: 10px;
  text-align: center;
  border-radius: 8px;
  position: absolute;
  left: 40px;
  bottom: 40px;
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
