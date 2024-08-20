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
    mobile: {
      type: Boolean,
      default: false,
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

  const iconState = ref('initial');
  const iconClass = ref('fa-info');

  onMounted(() => {
    if (
      props.data.completion.feedback &&
      props.data.completion.feedback.status == 'completed'
    ) {
      if (
          props.data.animations &&
          props.data.animations.restrictiontime > store.state.lastseen
        ) {
        setTimeout(() => {
            iconState.value = 'animated';
            setTimeout(() => {
              iconClass.value = 'fa-check';
            }, 750);
        }, 1000);
      } else {
        iconClass.value = 'fa-check';
      }
    }
  });

  const handleAnimationEnd = () => {
    iconClass.value = 'fa-check'; // Change the icon to a checkmark
  };

</script>

<template>
  <div
    :class="{
      'card-hover': showCard,
      [data.node_id + '_node_info_listener']: true,
      'icon-container': !mobile,
      'card-container': mobile
    }"
  >
    <div
      class="information"
      :style="{ backgroundColor: backgroundColor }"
      @click.stop="toggleCard"
    >
      <i
        :class="['fa', iconClass, {'fa-info-mobile': mobile, 'icon-animated': iconState === 'animated'}]"
        @animationend="handleAnimationEnd"
      />
    </div>
    <transition :name="mobile ? 'fade' : 'unfold'">
      <div
        v-if="showCard"
        :class="{
          'additional-card': !mobile
        }"
        :style="{ backgroundColor: backgroundColorInfo}"
        @mousedown.stop
        @mousemove.stop
        @mouseup.stop
      >
        <ul class="list-group">
          <li
            v-if="description && typeof description === 'string'"
            class="list-group-item"
            style="user-select: text;"
            @mousedown.stop
            @mousemove.stop
            @mouseup.stop
          >
            <i class="fa fa-pencil" />
            <b>
              Description
            </b>
            <div
              class="list-group-text"
              style="user-select: text;"
              @mousedown.stop
              @mousemove.stop
              @mouseup.stop
            >
              {{ description }}
            </div>
          </li>
          <li
            v-if="estimate_duration && typeof estimate_duration === 'string'"
            class="list-group-item"
            style="user-select: text;"
            @mousedown.stop
            @mousemove.stop
            @mouseup.stop
          >
            <i class="fa fa-spinner" />
            <b>
              Dates and Duration
            </b>
            <div
              class="list-group-text"
              style="user-select: text;"
              @mousedown.stop
              @mousemove.stop
              @mouseup.stop
            >
              <b>
                Estimated Duration:
              </b>
              {{ estimate_duration }}
            </div>
            <div
              v-if="ending_date.start_date"
              class="list-group-text"
              style="user-select: text;"
              @mousedown.stop
              @mousemove.stop
              @mouseup.stop
            >
              <b>
                Start Date:
              </b>
              {{ ending_date.start_date }}
            </div>
            <div
              v-if="ending_date.end_date"
              class="list-group-text"
              style="user-select: text;"
              @mousedown.stop
              @mousemove.stop
              @mouseup.stop
            >
              <b>
                End Date:
              </b>
              {{ ending_date.end_date }}
            </div>
            <div
              v-if="subscribbed_date"
              class="list-group-text"
              style="user-select: text;"
              @mousedown.stop
              @mousemove.stop
              @mouseup.stop
            >
              <b>
                First subscribbed to node:
              </b>
              {{ subscribbed_date }}
            </div>
          </li>
          <li
            class="list-group-item"
            style="user-select: text;"
            @mousedown.stop
            @mousemove.stop
            @mouseup.stop
          >
            <i class="fa fa-lock" />
            <b>
              Restriction
            </b>
            <div class="list-group-text" style="user-select: text;" @mousedown.stop @mousemove.stop @mouseup.stop>
              <div v-if="props.parentnode && props.parentnode.restriction && restriction">
                <div
                  v-for="restriction_string in restriction"
                  :key="restriction_string"
                  style="user-select: text;"
                  @mousedown.stop
                  @mousemove.stop
                  @mouseup.stop
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
            style="user-select: text;"
            @mousedown.stop
            @mousemove.stop
            @mouseup.stop
          >
            <i
              class="fa fa-tasks"
            />
            <b>
              Completion
            </b>
            <div class="list-group-text" style="user-select: text;" @mousedown.stop @mousemove.stop @mouseup.stop>
              <div v-if="props.parentnode && props.parentnode.completion && completion">
                <div
                  v-for="completion_string in completion"
                  :key="completion_string"
                  style="user-select: text;"
                  @mousedown.stop
                  @mousemove.stop
                  @mouseup.stop
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
            style="user-select: text;"
            @mousedown.stop
            @mousemove.stop
            @mouseup.stop
          >
            <i
              class="fa-solid fa-play-circle"
            />
            <b>
              Completion Inbetween
            </b>
            <div class="list-group-text" style="user-select: text;" @mousedown.stop @mousemove.stop @mouseup.stop>
              <div v-if="completion_inbetween && completion_inbetween.length > 0 && completion_inbetween != ''">
                <div
                  v-for="completion_string in completion_inbetween"
                  :key="completion_string"
                  style="user-select: text;"
                  @mousedown.stop
                  @mousemove.stop
                  @mouseup.stop
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

@keyframes rotateAndFade {
  0% {
    transform: rotate(0deg);
    opacity: 1;
  }
  50% {
    transform: rotate(180deg);
    opacity: 0.1;
  }
  100% {
    transform: rotate(360deg);
    opacity: 1;
  }
}

.icon-animated {
  animation: rotateAndFade 1.5s forwards;
}

.card-container {
  justify-content: center;
  align-items: center;
  padding: 5px;
  border-radius: 8px;
  background-color: #EAEAEA;
  text-align: center;
  cursor: default;
  user-select: text;
}

.list-group-text {
  text-align: left;
  cursor: text;
  user-select: text;
}

.icon-container {
  position: absolute;
  top: -25px;
  right: -25px;
  display: inline-flex; /* Use flexbox for centering */
  justify-content: center;
  align-items: center;
  cursor: pointer;
}

.information {
  cursor: pointer;
  display: inline-block;
  width: 50px; /* Diameter of the round button */
  height: 50px; /* Diameter of the round button */
  border-radius: 50%; /* Makes the div round */
  border: 1px solid rgba(0,0,0,0.2);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.38); /* Adds depth with a shadow */
  z-index: 3;
}

.information:hover {
  background-color: #ad0050 !important; /* Darker background on hover for feedback */
  box-shadow: 0 6px 8px rgba(0,0,0,0.2); /* Larger shadow on hover for depth */
}

.fa-info,
.fa-check{
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
  top: 20px;
  text-align: start;
  z-index: 2;
  cursor: default;
  user-select: text;
}

/* Starting state for entering */
.unfold-enter-from, .unfold-leave-to {
  transform: scaleY(0);
  opacity: 0;
  transform-origin: top; /* Ensures scaling happens left to right */
}

/* Ending state for entering and starting state for leaving */
.unfold-enter-to, .unfold-leave-from {
  transform: scaleY(1);
  opacity: 1;
  transform-origin: top;
}

/* Active state for entering and leaving */
.unfold-enter-active, .unfold-leave-active {
  transition: transform 0.3s ease-out, opacity 0.3s ease-out;
  visibility: visible;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.5s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

</style>
