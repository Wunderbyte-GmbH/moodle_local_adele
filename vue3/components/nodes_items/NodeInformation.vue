<script setup>
  // Import needed libraries
  import { computed, onMounted, ref, watch } from 'vue';
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
    startanimation: {
      type: Boolean,
      default: true,
    },
  });
  const store = useStore();

  // Create a ref for conditions
  const showCard = ref(false);
  const iconState = ref('initial');
  const iconClass = ref('fa-info');

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
    if (typeof dateString === "string") {
      // Extract day, month, year, hours, and minutes from the string
      const [datePart, timePart] = dateString.split(' ');
      
      if (datePart && timePart) { // Ensure both parts are present
        const [day, month, year] = datePart.split('.').map(Number);
        const [hours, minutes] = timePart.split(':').map(Number);

        // Create a Date object using UTC to avoid timezone issues
        const date = new Date(Date.UTC(year, month - 1, day, hours, minutes));

        // Convert to locale string
        return_date[type] = date.toLocaleString('en-US', options);
      }
    }
  });

  return return_date;
}

  onMounted(() => {
    triggerAnimation()
    if (props.startanimation && props.data.animations && props.data.animations.seencompletion === false) {
    const stop = watch(() => props.startanimation, (newValue, oldValue) => {
      if (newValue === false) {
        iconState.value = 'fadingOut';
        setTimeout(() => {
          iconClass.value = 'fa-info';
          iconState.value = 'fadingIn';
          setTimeout(() => {
            triggerAnimation()
            stop();
          }, 500);
        }, 500);
      }
    });
  }
  });

  const triggerAnimation = () => {
    if (
      props.data.completion &&
      props.data.completion.feedback &&
      props.data.completion.feedback.status == 'completed'
    ) {
      if (
        props.data.animations &&
        props.data.animations.seencompletion == false
      ) {
        setTimeout(() => {
          iconState.value = 'rotating';
          setTimeout(() => {
            iconClass.value = 'fa-check';
          }, 500);
        }, 1000);
      } else {
        iconClass.value = 'fa-check';
      }
    }
  };

  const cardStyle = ref({
    zIndex: 3,
  });
  const handleFocus = () => {
    cardStyle.value.zIndex = 4;
  };

  const handleBlur = () => {
    cardStyle.value.zIndex = 3;
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
    @focus="handleFocus"
    @blur="handleBlur"
    tabindex="0"
    :style="cardStyle"
  >
    <div
      class="information"
      :style="{ backgroundColor: backgroundColor }"
      @click.stop="toggleCard"
      :class="{ 'information-rotating': iconState === 'rotating' }"
    >
      <i
        :class="['fa', iconClass, {
          'fa-info-mobile': mobile,
          'icon-fadingIn': iconState === 'fadingIn',
          'icon-fadingOut': iconState === 'fadingOut'
        }]"
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
          dadadad {{ ending_date.start_date }}        {{ ending_date.end_date }}
          <li
            v-if="props.data.description"
            class="list-group-item"
            style="user-select: text;"
            @mousedown.stop
            @mousemove.stop
            @mouseup.stop
          >
            <i class="fa fa-pencil" />
            <b>
              {{ store.state.strings.completion_description_feedback}}
            </b>
            <div
              class="list-group-text"
              style="user-select: text;"
              @mousedown.stop
              @mousemove.stop
              @mouseup.stop
            >
              {{ props.data.description}}
            </div>
          </li>
          <li
            class="list-group-item"
            style="user-select: text;"
            @mousedown.stop
            @mousemove.stop
            @mouseup.stop
          >
            <i class="fa fa-spinner" />
            <b>
              {{ store.state.strings.completion_dates_duration_feedback }}
            </b>
            <div
            v-if="estimate_duration && typeof estimate_duration === 'string'"
              class="list-group-text"
              style="user-select: text;"
              @mousedown.stop
              @mousemove.stop
              @mouseup.stop
            >
              <b>
                {{ store.state.strings.completion_estimated_duration_feedback }}
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
                {{ store.state.strings.completion_start_date_feedback }}
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
                {{ store.state.strings.completion_end_date_feedback }}
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
                {{ store.state.strings.completion_first_subscription_feedback }}
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
              {{ store.state.strings.completion_restriction_feedback }}
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
               {{ store.state.strings.completion_nothing_defined_feedback }}
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
              {{ store.state.strings.completion_completion_feedback }}
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
                    - <span v-html="completion_string"></span>
                  </div>
                </div>
              </div>
              <div v-else>
                {{ store.state.strings.completion_nothing_defined_feedback }}
              </div>
            </div>
          </li>
        </ul>
      </div>
    </transition>
  </div>
</template>

<style scoped>

@keyframes rotateY {
  0% {
    transform: rotateY(0deg);
    opacity: 1;
  }
  50% {
    transform: rotateY(90deg);
    opacity: 0;
  }
  100% {
    transform: rotateY(180deg);
    opacity: 1;
  }
}

.information {
  cursor: pointer;
  display: inline-block;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  border: 1px solid rgba(0,0,0,0.2);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.38);
  transform-style: preserve-3d; /* Preserve 3D context */
  perspective: 1000px; /* Hides the back side during rotation */
  z-index: 5;
}

.information-rotating {
  animation: rotateY 1s forwards;
}

.fa-info,
.fa-check {
  font-size: 30px;
  color: white;
  margin-top: 7px;
}

.information-rotating .fa-check {
  transform: rotateY(180deg);
}

@keyframes fadingIn {
  0% {
    opacity: 0;
  }
  100% {
    opacity: 1;
  }
}
.icon-fadingIn {
  animation: fadingIn 0.5s ease-out forwards;
  transition: transform 0.5s, opacity 0.5s ease;
}

@keyframes fadingOut {
  0% {
    opacity: 1;
  }
  100% {
    opacity: 0;
  }
}
.icon-fadingOut {
  animation: fadingOut 0.5s ease-out forwards;
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

.information:hover {
  background-color: #ad0050 !important;
  box-shadow: 0 6px 8px rgba(0,0,0,0.2);
}

.additional-card {
  width: 500px;
  padding: 10px;
  text-align: center;
  border-radius: 8px;
  position: absolute;
  top: 20px;
  text-align: start;
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
