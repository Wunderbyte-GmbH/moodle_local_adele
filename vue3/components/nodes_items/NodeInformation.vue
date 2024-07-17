<script setup>
  // Import needed libraries
  import { computed, onMounted, onUnmounted, ref } from 'vue';
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
  const estimate_duration = ref({})

  const restriction = computed(() => props.data.completion.feedback.restriction.before || null )
  const completion = computed(() => props.data.completion.feedback.completion.before || null )

  const ending_date = computed(() => {
    let return_date = {
      start_date: null,
      end_date: null,
    }
    if (props.data.completion) {
      Object.entries(props.data.completion.restrictioncriteria).forEach(([key, value]) => {
        if (key.includes('timed')) {
          Object.entries(value).forEach(([condition, times]) => {
            if (times.inbetween_info != undefined) {
              Object.entries(times.inbetween_info).forEach(([start_end, time]) => {
                if (
                  start_end == 'starttime' &&
                  (return_date.start_date == null ||return_date.start_date > time)
                  ) {
                    return_date.start_date = time
                } else if (
                  start_end == 'endtime' &&
                  (return_date.end_date == null ||return_date.end_date > time)
                ) {
                  return_date.end_date = time
                }
              });
            }
          });
        }
      });
    }
    return_date = format_dates(return_date)
    return return_date;
  })

  const subscribbed_date = computed(() => {
    if (props.data.first_enrolled) {
      const date = new Date(props.data.first_enrolled * 1000);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      });
    }
    return null;
  })

  const completion_inbetween = computed(() => {
    if (props.data.completion) {
      return props.data.completion.feedback.completion.inbetween
    }
    return null
  })

  const format_dates = (return_date) => {
    const options = {
      weekday: 'long', // "Monday"
      year: 'numeric', // "2024"
      month: 'long', // "April"
      day: 'numeric', // "29"
      hour: '2-digit', // "13"
      minute: '2-digit', // "28"
      second: '2-digit', // "12"
      hour12: false
    };
    Object.entries(return_date).forEach(([type, dateString]) => {
      if (dateString != undefined) {
        const date = new Date(dateString);
        return_date[type] = date.toLocaleDateString('en-US', options);
      }
    })
    return return_date;
  }

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

  const closeOnOutsideClick = (event) => {
    if (!event.target.closest('.' + props.data.node_id + '_node_info_listener')) {
      showCard.value = false;
    }
  };

  onMounted(() => {
    description.value = props.data.description ||null
    estimate_duration.value = props.data.estimate_duration ||null
    document.addEventListener('click', closeOnOutsideClick);
  })

  onUnmounted(() => {
    document.removeEventListener('click', closeOnOutsideClick);
  });

</script>

<template>
  <div
    class="icon-container "
    :class="{ 'card-hover': showCard, [data.node_id + '_node_info_listener']: true}"
    @click="toggleCard()"
  >
    <div
      class="information"
      :style="{ backgroundColor: backgroundColor }"
    >
      <i class="fa fa-info" />
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
            <i class="fa fa-pencil" />
            <b>
              Description
            </b>
            <div class="list-group-text">
              {{ description }}
            </div>
          </li>
          <li
            v-if="estimate_duration"
            class="list-group-item"
          >
            <i class="fa fa-spinner" />
            <b>
              Dates and Duration
            </b>
            <div class="list-group-text">
              <b>
                Estimated Duration:
              </b>
              {{ estimate_duration }}
            </div>
            <div
              v-if="ending_date.start_date"
              class="list-group-text"
            >
              <b>
                Start Date:
              </b>
              {{ ending_date.start_date }}
            </div>
            <div
              v-if="ending_date.end_date"
              class="list-group-text"
            >
              <b>
                End Date:
              </b>
              {{ ending_date.end_date }}
            </div>
            <div
              v-if="subscribbed_date"
              class="list-group-text"
            >
              <b>
                First subscribbed to node:
              </b>
              {{ subscribbed_date }}
            </div>
          </li>
          <li
            class="list-group-item"
          >
            <i class="fa fa-lock" />
            <b>
              Restriction
            </b>
            <div class="list-group-text">
              <div v-if="props.parentnode && props.parentnode.restriction && restriction">
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
            class="list-group-item"
          >
            <i
              class="fa fa-tasks"
            />
            <b>
              Completion
            </b>
            <div class="list-group-text">
              <div v-if="props.parentnode && props.parentnode.completion && completion">
                <div
                  v-for="completion_string in completion"
                  :key="completion_string"
                >
                  <div v-if="completion_string != ''">
                    - {{ completion_string }}
                  </div>
                </div>
              </div>
              <div v-else>
                Nothing is defined
              </div>
            </div>
          </li>
          <li
            class="list-group-item"
          >
            <i
              class="fa-solid fa-play-circle"
            />
            <b>
              Completion Inbetween
            </b>
            <div class="list-group-text">
              <div v-if="completion_inbetween && completion_inbetween.length > 0 && completion_inbetween != ''">
                <div
                  v-for="completion_string in completion_inbetween"
                  :key="completion_string"
                >
                  <div v-if="completion_string != ''">
                    - {{ completion_string }}
                  </div>
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
  z-index: 101;
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
  z-index: 11;
  left: -472px;
  bottom: -200px;
  text-align: start;
}

/* Starting state for entering */
.unfold-enter-from, .unfold-leave-to {
  transform: scaleX(0);
  opacity: 0;
  transform-origin: right; /* Ensures scaling happens left to right */
}

/* Ending state for entering and starting state for leaving */
.unfold-enter-to, .unfold-leave-from {
  transform: scaleX(1);
  opacity: 1;
  transform-origin: right;
}

/* Active state for entering and leaving */
.unfold-enter-active, .unfold-leave-active {
  transition: transform 0.3s ease-out, opacity 0.3s ease-out;
  visibility: visible;
}

</style>
